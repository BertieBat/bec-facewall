<?php
/**
 * FaceWall maintenance: InnoDB-only, all-or-nothing reporting-table rebuilds.
 *
 * No schema changes and no table rename/CREATE privileges are required.
 * All four existing FaceWall reporting tables MUST use InnoDB. Otherwise the
 * job refuses to modify them rather than performing a non-rollbackable DELETE.
 */
defined('ABSPATH') || exit;

/** Only privacy-safe internal failures may appear in diagnostics. */
class FacewallMaintenanceFailure extends RuntimeException {}

function facewall_maintenance_tables() {
    return [
        'civicrm_BEC_historical_membership',
        'civicrm_BEC_historical_summary',
        'civicrm_BEC_historical_officer_membership',
        'civicrm_BEC_current_year',
    ];
}

function facewall_maintenance_stages() {
    return [
        'membership_history' => ['label' => 'Historical membership', 'callback' => 'build_membership_list', 'table' => 'civicrm_BEC_historical_membership'],
        'membership_summary' => ['label' => 'Historical summary', 'callback' => 'build_BEC_historical_summary_table', 'table' => 'civicrm_BEC_historical_summary'],
        'officer_history' => ['label' => 'Officer history', 'callback' => 'build_officer_historical_table', 'table' => 'civicrm_BEC_historical_officer_membership'],
        'chart_data' => ['label' => 'Chart data', 'callback' => 'build_chart_data_tables', 'table' => 'civicrm_BEC_current_year'],
    ];
}

/** Prevent accidental direct writes outside the locked transaction runner. */
function facewall_maintenance_transaction_active() {
    return !empty($GLOBALS['facewall_maintenance_transaction_active']);
}

function facewall_maintenance_check_engines($db, $tables = null) {
    $tables = $tables ?? facewall_maintenance_tables();
    foreach ($tables as $table) {
        // Names are internal constants; never accept a table name from HTTP.
        if (!in_array($table, facewall_maintenance_tables(), true)) {
            return 'Unexpected maintenance table';
        }
        $sql = $db->prepare(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
            $table
        );
        $engine = $db->get_var($sql);
        if (!is_string($engine) || strcasecmp($engine, 'InnoDB') !== 0) {
            return 'Reporting table ' . $table . ' must use InnoDB (detected: ' .
                (is_scalar($engine) && $engine !== '' ? (string) $engine : 'unavailable') . ')';
        }
    }
    return '';
}

/** Save only operational metadata; never include member names or query text. */
function facewall_maintenance_record($state, $stage, $message, $started, $results = []) {
    $old = function_exists('get_option') ? get_option('facewall_maintenance_status', []) : [];
    if (!is_array($old)) {
        $old = [];
    }
    $now = gmdate('c');
    $status = [
        'state' => $state,
        'last_attempt' => $now,
        'last_success' => $state === 'success' ? $now : ($old['last_success'] ?? ''),
        'last_failure' => $state === 'failed' ? $now : ($old['last_failure'] ?? ''),
        'failed_stage' => $state === 'failed' ? $stage : '',
        'message' => substr((string) $message, 0, 240),
        'duration_seconds' => round(max(0, microtime(true) - $started), 2),
        'stages' => $results,
    ];
    if (function_exists('update_option')) {
        update_option('facewall_maintenance_status', $status, false);
    }
    error_log('FaceWall maintenance: ' . $state . ': ' . $stage . ': ' . $status['message']);
    if ($state === 'failed') {
        facewall_maintenance_maybe_email_failure($status);
    }
    return $status;
}

function facewall_maintenance_maybe_email_failure($status) {
    if (!function_exists('get_option') ||
        get_option('facewall_maintenance_email_alerts', '0') !== '1' ||
        !function_exists('wp_mail')) {
        return;
    }
    $now = time();
    $last = (int) get_option('facewall_maintenance_last_alert', 0);
    if ($last > $now - DAY_IN_SECONDS) {
        return; // At most one notification per day, even during repeated failures.
    }
    $recipient = get_option('admin_email', '');
    if (!is_string($recipient) || !is_email($recipient)) {
        return;
    }
    // Avoid leaking any member details or raw SQL in notification messages.
    $body = "The FaceWall scheduled reporting rebuild failed.\n"
          . 'Stage: ' . $status['failed_stage'] . "\n"
          . 'Reason: ' . $status['message'] . "\n"
          . "The previous reporting data was rolled back where the database uses InnoDB.\n"
          . "Review WordPress > FaceWall > Diagnostics.\n";
    try {
        if (wp_mail($recipient, '[FaceWall] Reporting rebuild failed', $body)) {
            update_option('facewall_maintenance_last_alert', $now, false);
        }
    } catch (Throwable $e) {
        // Mail failures must not alter the result of a reporting rebuild.
        error_log('FaceWall maintenance: optional notification could not be delivered.');
    }
}

/**
 * Runner called only by facewall_cron or an authenticated maintenance test.
 *
 * Source checks are performed inside a single InnoDB transaction. DELETE
 * statements and all row inserts commit together. Readers continue seeing
 * previously committed reporting data until COMMIT; errors trigger ROLLBACK.
 */
function facewall_maintenance_run($stages = null) {
    global $wpdb;
    $started = microtime(true);
    $stages = $stages ?? facewall_maintenance_stages();
    $lock_name = 'bec_facewall_rebuild_' . (function_exists('get_current_blog_id') ? get_current_blog_id() : 1);
    $lock = 0;
    $transaction = false;
    $stage_name = 'initialisation';
    $results = [];

    try {
        $lock_result = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock_name));
        if ($lock_result === null || $lock_result === false) {
            throw new FacewallMaintenanceFailure('Database advisory lock unavailable');
        }
        $lock = (int) $lock_result;
        if ($lock !== 1) {
            // Another job owns the lock; do not overwrite its health status.
            error_log('FaceWall maintenance: skipped because another rebuild owns the database lock.');
            return 'skipped (already running)';
        }

        $stage_name = 'stage_check';
        $expected = facewall_maintenance_stages();
        if (array_keys($stages) !== array_keys($expected)) {
            throw new FacewallMaintenanceFailure('Maintenance stage list is incomplete or out of order');
        }
        foreach ($stages as $key => $definition) {
            if (!isset($definition['table'], $definition['callback']) ||
                $definition['table'] !== $expected[$key]['table'] ||
                !is_callable($definition['callback'])) {
                throw new FacewallMaintenanceFailure('Maintenance stage configuration is invalid');
            }
        }

        $stage_name = 'database_check';
        $unsafe = facewall_maintenance_check_engines($wpdb);
        if ($unsafe !== '') {
            throw new FacewallMaintenanceFailure($unsafe);
        }

        // If no current data can be committed, all previous data remains intact.
        $stage_name = 'transaction';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new FacewallMaintenanceFailure('Unable to start an InnoDB transaction');
        }
        $transaction = true;
        $GLOBALS['facewall_maintenance_transaction_active'] = true;

        foreach ($stages as $name => $definition) {
            $stage_name = (string) $name;
            if (!isset($definition['callback'], $definition['table']) ||
                !in_array($definition['table'], facewall_maintenance_tables(), true) ||
                !is_callable($definition['callback'])) {
                throw new FacewallMaintenanceFailure('Invalid scheduled maintenance step');
            }

            $step_started = microtime(true);
            $result = call_user_func($definition['callback']);
            if (!is_string($result) || !preg_match('/ - completed$/', $result)) {
                throw new FacewallMaintenanceFailure('Stage returned an unsuccessful result');
            }
            $table = $definition['table']; // validated against internal table allowlist
            $count = $wpdb->get_var('SELECT COUNT(*) FROM ' . $table);
            if ($count === null || $count === false || !is_numeric($count)) {
                throw new FacewallMaintenanceFailure('Could not validate rebuilt reporting table');
            }
            // The source checks for histories require real input; never
            // replace an existing published history with an empty table.
            if ($stage_name !== 'chart_data' && (int) $count === 0) {
                throw new FacewallMaintenanceFailure('Rebuild produced no history rows');
            }
            $results[$stage_name] = [
                'rows' => (int) $count,
                'seconds' => round(microtime(true) - $step_started, 2),
            ];
        }

        $stage_name = 'commit';
        if ($wpdb->query('COMMIT') === false) {
            throw new FacewallMaintenanceFailure('Database COMMIT failed');
        }
        $transaction = false;
        $GLOBALS['facewall_maintenance_transaction_active'] = false;
        try {
            facewall_maintenance_record('success', '', 'All reporting tables committed together', $started, $results);
        } catch (Throwable $logging_error) {
            // A completed database COMMIT must not be misreported as failed
            // just because the optional status storage was unavailable.
            error_log('FaceWall: committed successfully but could not save diagnostics.');
        }
        return 'completed';
    } catch (Throwable $e) {
        $rollback_failed = false;
        if ($transaction) {
            try {
                $rollback_failed = $wpdb->query('ROLLBACK') === false;
            } catch (Throwable $rollback_error) {
                $rollback_failed = true;
            }
        }
        $transaction = false;
        $GLOBALS['facewall_maintenance_transaction_active'] = false;
        // Unexpected exceptions can contain SQL or personal information.
        // Display/email only controlled failure messages, never raw exceptions.
        $message = $e instanceof FacewallMaintenanceFailure
            ? $e->getMessage()
            : 'Unexpected maintenance exception (' . get_class($e) . ')';
        if ($rollback_failed) {
            $message = 'Rollback failed or uncertain; check reports before retrying';
        }
        try {
            facewall_maintenance_record('failed', $stage_name, $message, $started, $results);
        } catch (Throwable $logging_error) {
            error_log('FaceWall: failed to persist maintenance diagnostics.');
        }
        return 'failed';
    } finally {
        $GLOBALS['facewall_maintenance_transaction_active'] = false;
        if ($lock === 1) {
            try {
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
            } catch (Throwable $unlock_error) {
                error_log('FaceWall: database advisory lock could not be explicitly released.');
            }
        }
    }
}
