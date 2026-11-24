<?php
/**
 * Read-only diagnostic UI and explicitly authorised maintenance controls.
 * No member records are displayed, and anonymous requests cannot run jobs.
 */
defined('ABSPATH') || exit;

function facewall_maintenance_health($status, $now = null) {
    $now = $now ?? time();
    if (!is_array($status) || empty($status['last_attempt'])) {
        return ['level' => 'unknown', 'message' => 'No maintenance run has been recorded yet.'];
    }
    if (($status['state'] ?? '') === 'failed') {
        return ['level' => 'failed', 'message' => 'The most recent rebuild failed. Previously committed data should be intact.'];
    }
    $success = !empty($status['last_success']) ? strtotime($status['last_success']) : false;
    if (!$success) {
        return ['level' => 'unknown', 'message' => 'No successful rebuild has been recorded.'];
    }
    if ($now - $success > 8 * DAY_IN_SECONDS) {
        return ['level' => 'stale', 'message' => 'The last successful reporting rebuild is more than eight days old.'];
    }
    return ['level' => 'healthy', 'message' => 'The latest recorded reporting rebuild completed successfully.'];
}

function facewall_maintenance_diagnostics_url() {
    return admin_url('admin.php?page=facewall_diagnostics');
}

function facewall_maintenance_diagnostics_menu() {
    add_submenu_page(
        'facewall_plugin',
        'Maintenance diagnostics',
        'Diagnostics',
        'manage_options',
        'facewall_diagnostics',
        'facewall_maintenance_diagnostics_page'
    );
}
add_action('admin_menu', 'facewall_maintenance_diagnostics_menu');

function facewall_maintenance_diagnostics_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to view maintenance diagnostics.');
    }
    $status = get_option('facewall_maintenance_status', []);
    $health = facewall_maintenance_health($status);
    $alerts = get_option('facewall_maintenance_email_alerts', '0') === '1';
    $next = wp_next_scheduled('facewall_cron');
    echo '<div class="wrap"><h1>FaceWall maintenance diagnostics</h1>';
    echo '<p><strong>Health:</strong> ' . esc_html($health['message']) . '</p>';
    if (!is_array($status)) {
        $status = [];
    }
    $fields = [
        'state' => 'Last result',
        'last_attempt' => 'Last attempted (UTC)',
        'last_success' => 'Last successful (UTC)',
        'last_failure' => 'Last failure (UTC)',
        'failed_stage' => 'Failed stage',
        'message' => 'Result summary',
        'duration_seconds' => 'Duration (seconds)',
    ];
    echo '<table class="widefat striped"><tbody>';
    foreach ($fields as $field => $caption) {
        echo '<tr><th scope="row">' . esc_html($caption) . '</th><td>'
            . esc_html((string) ($status[$field] ?? 'Not recorded')) . '</td></tr>';
    }
    echo '<tr><th scope="row">Next WordPress scheduled run</th><td>'
        . esc_html($next ? gmdate('Y-m-d H:i:s', $next) . ' UTC' : 'No WordPress event scheduled')
        . '</td></tr></tbody></table>';

    if (!empty($status['stages']) && is_array($status['stages'])) {
        echo '<h2>Most recent stage results</h2><table class="widefat striped"><thead><tr>'
            . '<th>Step</th><th>Rows</th><th>Duration (seconds)</th></tr></thead><tbody>';
        foreach ($status['stages'] as $stage => $details) {
            echo '<tr><td>' . esc_html((string) $stage) . '</td><td>'
                . esc_html((string) ($details['rows'] ?? '')) . '</td><td>'
                . esc_html((string) ($details['seconds'] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    if (isset($_GET['facewall_notice']) && is_string($_GET['facewall_notice'])) {
        $notice = sanitize_key(wp_unslash($_GET['facewall_notice']));
        if ($notice === 'queued') {
            echo '<div class="notice notice-success"><p>Rebuild requested through WordPress scheduling.</p></div>';
        } elseif ($notice === 'already_queued') {
            echo '<div class="notice notice-info"><p>A maintenance rebuild is already queued.</p></div>';
        } elseif ($notice === 'queue_failed') {
            echo '<div class="notice notice-error"><p>WordPress could not schedule the rebuild.</p></div>';
        } elseif ($notice === 'saved') {
            echo '<div class="notice notice-success"><p>Email notification settings saved.</p></div>';
        }
    }
    echo '<h2>Request a rebuild</h2><p>Runs through WP-Cron, not while this admin page is being displayed. '
        . 'Four reporting tables are committed together only when they use InnoDB.</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="facewall_maintenance_queue">';
    wp_nonce_field('facewall_maintenance_queue');
    submit_button('Queue one reporting rebuild', 'secondary');
    echo '</form>';

    echo '<h2>Failure alerts</h2>';
    echo '<p>Optional emails go to the WordPress administrator address, at most once in 24 hours. '
        . 'No member names or records are included.</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="facewall_maintenance_alerts">';
    wp_nonce_field('facewall_maintenance_alerts');
    echo '<label><input type="checkbox" name="notify" value="1" '
        . checked($alerts, true, false) . '> Email the site administrator after a rebuild failure</label>';
    submit_button('Save alert setting');
    echo '</form></div>';
}

function facewall_maintenance_require_admin($nonce) {
    if (!current_user_can('manage_options')) {
        wp_die('Only administrators can change FaceWall maintenance settings.');
    }
    check_admin_referer($nonce);
}

function facewall_maintenance_queue_action() {
    facewall_maintenance_require_admin('facewall_maintenance_queue');
    // Avoid scheduling duplicate immediate one-off runs.
    $existing = wp_get_scheduled_event('facewall_cron');
    $state = 'queue_failed';
    if ($existing && $existing->timestamp <= time() + 600) {
        $state = 'already_queued';
    } elseif (wp_schedule_single_event(time() + 60, 'facewall_cron')) {
        $state = 'queued';
    }
    wp_safe_redirect(add_query_arg('facewall_notice', $state, facewall_maintenance_diagnostics_url()));
    exit;
}
add_action('admin_post_facewall_maintenance_queue', 'facewall_maintenance_queue_action');

function facewall_maintenance_alerts_action() {
    facewall_maintenance_require_admin('facewall_maintenance_alerts');
    $enabled = isset($_POST['notify']) && $_POST['notify'] === '1';
    update_option('facewall_maintenance_email_alerts', $enabled ? '1' : '0', false);
    wp_safe_redirect(add_query_arg('facewall_notice', 'saved', facewall_maintenance_diagnostics_url()));
    exit;
}
add_action('admin_post_facewall_maintenance_alerts', 'facewall_maintenance_alerts_action');

function facewall_maintenance_admin_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $status = get_option('facewall_maintenance_status', []);
    $health = facewall_maintenance_health($status);
    if (!in_array($health['level'], ['failed', 'stale'], true)) {
        return;
    }
    echo '<div class="notice notice-warning"><p><strong>FaceWall:</strong> '
        . esc_html($health['message']) . ' <a href="'
        . esc_url(facewall_maintenance_diagnostics_url()) . '">Review diagnostics</a></p></div>';
}
add_action('admin_notices', 'facewall_maintenance_admin_notice');

/**
 * Keep the existing WP-Cron schedule intact. Ensure a weekly fallback only
 * when WordPress has no scheduled FaceWall rebuild at all. Run on admin_init
 * so normal public traffic does not perform schedule bookkeeping.
 */
function facewall_maintenance_ensure_schedule() {
    if (!current_user_can('manage_options') || wp_next_scheduled('facewall_cron')) {
        return;
    }
    wp_schedule_event(time() + HOUR_IN_SECONDS, 'weekly', 'facewall_cron');
}
add_action('admin_init', 'facewall_maintenance_ensure_schedule');
