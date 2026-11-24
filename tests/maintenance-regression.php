<?php
/**
 * Dependency-free regression tests for FaceWall's transactional maintenance.
 * Run: php tests/maintenance-regression.php
 */
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/../');
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);

$GLOBALS['fake_options'] = [];
$GLOBALS['fake_emails'] = [];
$GLOBALS['fake_admin'] = false;
function get_current_blog_id() { return 1; }
function get_option($name, $default = false) { return $GLOBALS['fake_options'][$name] ?? $default; }
function update_option($name, $value, $autoload = null) {
    if (!empty($GLOBALS['fake_update_error'])) { throw new RuntimeException('Simulated option storage failure'); }
    $GLOBALS['fake_options'][$name] = $value;
    return true;
}
function is_email($address) { return filter_var($address, FILTER_VALIDATE_EMAIL) !== false; }
function wp_mail($to, $subject, $message) {
    if (!empty($GLOBALS['fake_mail_error'])) { throw new RuntimeException('Simulated mail transport failure'); }
    $GLOBALS['fake_emails'][] = compact('to', 'subject', 'message');
    return true;
}
function add_action($hook, $callback) { $GLOBALS['fake_hooks'][$hook][] = $callback; }
function current_user_can($capability) { return $GLOBALS['fake_admin']; }
function wp_die($message) { throw new RuntimeException('DENIED: ' . $message); }
function check_admin_referer($action) { throw new RuntimeException('Nonce must not be checked before access control'); }

require dirname(__DIR__) . '/includes/maintenance.php';
require dirname(__DIR__) . '/includes/diagnostics.php';

function assert_maintenance($ok, $description) {
    if (!$ok) { throw new RuntimeException($description); }
}

class FacewallFakeDatabase {
    public $engines = [];
    public $rows = [];
    public $queries = [];
    public $locking = true;
    public $lock_broken = false;
    public $locked = false;
    public $in_transaction = false;
    public $last_error = '';
    private $snapshot = [];

    public function __construct() {
        foreach (facewall_maintenance_tables() as $table) {
            $this->engines[$table] = 'InnoDB';
            $this->rows[$table] = ['original'];
        }
    }

    public function prepare($sql, ...$values) {
        foreach ($values as $value) {
            $replacement = "'" . str_replace("'", "''", (string) $value) . "'";
            $sql = preg_replace('/%s/', $replacement, $sql, 1);
        }
        return $sql;
    }

    public function get_var($sql) {
        $this->queries[] = $sql;
        if (strpos($sql, 'GET_LOCK(') !== false) {
            if ($this->lock_broken) { return null; }
            if (!$this->locking) { return 0; }
            $this->locked = true;
            return 1;
        }
        if (strpos($sql, 'RELEASE_LOCK(') !== false) {
            $this->locked = false;
            return 1;
        }
        if (strpos($sql, 'information_schema.TABLES') !== false) {
            if (!preg_match("/TABLE_NAME = '([^']+)'/", $sql, $matches)) {
                return null;
            }
            return $this->engines[$matches[1]] ?? null;
        }
        if (preg_match('/^SELECT COUNT\(\*\) FROM (civicrm_BEC_\w+)$/', $sql, $matches)) {
            return count($this->rows[$matches[1]]);
        }
        throw new RuntimeException('Unexpected fake SQL lookup');
    }

    public function query($sql) {
        $this->queries[] = $sql;
        if ($sql === 'START TRANSACTION') {
            assert_maintenance(!$this->in_transaction, 'Transaction already active');
            $this->in_transaction = true;
            $this->snapshot = $this->rows;
            return 1;
        }
        if ($sql === 'COMMIT') {
            assert_maintenance($this->in_transaction, 'Committed without transaction');
            $this->in_transaction = false;
            return 1;
        }
        if ($sql === 'ROLLBACK') {
            assert_maintenance($this->in_transaction, 'Rollback without transaction');
            $this->rows = $this->snapshot;
            $this->in_transaction = false;
            return 1;
        }
        if (preg_match('/^DELETE FROM (civicrm_BEC_\w+)$/', $sql, $matches)) {
            assert_maintenance($this->in_transaction, 'Report deleted outside transaction');
            $this->rows[$matches[1]] = [];
            return 1;
        }
        if (preg_match('/^INSERT INTO (civicrm_BEC_\w+)/', $sql, $matches)) {
            assert_maintenance($this->in_transaction, 'Report inserted outside transaction');
            $this->rows[$matches[1]][] = 'new';
            return 1;
        }
        throw new RuntimeException('Unexpected fake write');
    }
}

function test_stages($fail_at = '') {
    $result = [];
    foreach (facewall_maintenance_stages() as $key => $definition) {
        $table = $definition['table'];
        $result[$key] = [
            'table' => $table,
            'callback' => static function () use ($key, $table, $fail_at) {
                global $wpdb;
                assert_maintenance(facewall_maintenance_transaction_active(), 'Stage must run in transaction');
                $wpdb->query('DELETE FROM ' . $table);
                if ($key === $fail_at) {
                    return 'simulated - failed (insert)';
                }
                $wpdb->query('INSERT INTO ' . $table . ' VALUES ("new")');
                return 'simulated - completed';
            },
        ];
    }
    return $result;
}

global $wpdb;
$wpdb = new FacewallFakeDatabase();
$result = facewall_maintenance_run(test_stages());
assert_maintenance($result === 'completed', 'Successful pipeline did not commit');
assert_maintenance(get_option('facewall_maintenance_status')['state'] === 'success', 'Success status missing');
assert_maintenance(!facewall_maintenance_transaction_active() && !$wpdb->locked, 'Runner leaked a lock');
assert_maintenance(count(get_option('facewall_maintenance_status')['stages']) === 4, 'Missing stage counts');
foreach (facewall_maintenance_tables() as $table) {
    assert_maintenance($wpdb->rows[$table] === ['new'], 'Successful pipeline did not replace ' . $table);
}

// A failure after DELETE must restore every original reporting table, even
// those already rebuilt in an earlier stage of the same transaction.
$wpdb = new FacewallFakeDatabase();
$result = facewall_maintenance_run(test_stages('membership_summary'));
assert_maintenance($result === 'failed', 'Failed stage must fail the run');
assert_maintenance(get_option('facewall_maintenance_status')['failed_stage'] === 'membership_summary', 'Failed stage not recorded');
assert_maintenance(in_array('ROLLBACK', $wpdb->queries, true), 'Failed stage did not roll back');
assert_maintenance(!in_array('COMMIT', $wpdb->queries, true), 'Failed stage committed partial data');
foreach (facewall_maintenance_tables() as $table) {
    assert_maintenance($wpdb->rows[$table] === ['original'], 'Rollback did not preserve ' . $table);
}

// MyISAM (or insufficient metadata permissions) must never permit a DELETE.
$wpdb = new FacewallFakeDatabase();
$wpdb->engines['civicrm_BEC_current_year'] = 'MyISAM';
$result = facewall_maintenance_run(test_stages());
assert_maintenance($result === 'failed', 'Nontransactional table must block the job');
assert_maintenance(!in_array('START TRANSACTION', $wpdb->queries, true), 'Engine gate should run before any transaction');
assert_maintenance(!in_array('DELETE FROM civicrm_BEC_current_year', $wpdb->queries, true), 'MyISAM data was deleted');
assert_maintenance(!$wpdb->locked, 'Engine check failure leaked a database lock');

// Concurrent runs are intentionally skipped without overwriting history.
$wpdb = new FacewallFakeDatabase();
$wpdb->locking = false;
$previous = get_option('facewall_maintenance_status');
assert_maintenance(facewall_maintenance_run(test_stages()) === 'skipped (already running)', 'Lock contention should skip');
assert_maintenance(get_option('facewall_maintenance_status') === $previous, 'Lock contention overwrote diagnostics');

// Missing database lock metadata must be a visible failure, not mistaken for
// an already-running job (which intentionally preserves the previous status).
$wpdb = new FacewallFakeDatabase();
$wpdb->lock_broken = true;
assert_maintenance(facewall_maintenance_run(test_stages()) === 'failed',
    'Broken database lock query must be reported as failure');
assert_maintenance(get_option('facewall_maintenance_status')['failed_stage'] === 'initialisation',
    'Database lock failure status was not recorded');

// Refuse incomplete maintenance steps before entering a transaction.
$wpdb = new FacewallFakeDatabase();
$incomplete = test_stages();
unset($incomplete['chart_data']);
assert_maintenance(facewall_maintenance_run($incomplete) === 'failed', 'Incomplete stages should fail');
assert_maintenance(!in_array('START TRANSACTION', $wpdb->queries, true), 'Incomplete stages started a transaction');

// Unexpectedly empty history would wipe a published list; it must roll back.
$wpdb = new FacewallFakeDatabase();
$empty = test_stages();
$empty['membership_history']['callback'] = static function () {
    global $wpdb;
    $wpdb->query('DELETE FROM civicrm_BEC_historical_membership');
    return 'simulated - completed';
};
assert_maintenance(facewall_maintenance_run($empty) === 'failed', 'Empty history was committed');
assert_maintenance($wpdb->rows['civicrm_BEC_historical_membership'] === ['original'],
    'Unexpectedly empty history was not rolled back');

// Unexpected exception messages can contain names or SQL: never put them
// into WordPress options or optional administrator emails.
$wpdb = new FacewallFakeDatabase();
$unexpected = test_stages();
$unexpected['officer_history']['callback'] = static function () {
    throw new RuntimeException('Member Jane Smith: SQL INSERT INTO secret_table');
};
assert_maintenance(facewall_maintenance_run($unexpected) === 'failed', 'Exception failed to abort the job');
$public_message = get_option('facewall_maintenance_status')['message'];
assert_maintenance(strpos($public_message, 'Jane Smith') === false && strpos($public_message, 'INSERT INTO') === false,
    'Private exception details leaked into maintenance status');

// Notification transport failures are isolated from reporting state.
$GLOBALS['fake_options']['facewall_maintenance_email_alerts'] = '1';
$GLOBALS['fake_options']['admin_email'] = 'administrator@example.test';
$GLOBALS['fake_mail_error'] = true;
$wpdb = new FacewallFakeDatabase();
assert_maintenance(facewall_maintenance_run(test_stages('chart_data')) === 'failed',
    'Failure reporting crashed due to notification transport failure');
unset($GLOBALS['fake_mail_error']);
$GLOBALS['fake_options']['facewall_maintenance_email_alerts'] = '0';

// The database COMMIT is authoritative even if WordPress option storage
// becomes unavailable immediately after committing all reporting data.
$GLOBALS['fake_update_error'] = true;
$wpdb = new FacewallFakeDatabase();
assert_maintenance(facewall_maintenance_run(test_stages()) === 'completed',
    'A successful COMMIT was misclassified after diagnostics storage failure');
unset($GLOBALS['fake_update_error']);

// The previous success remains visible after a failed attempt.
$wpdb = new FacewallFakeDatabase();
facewall_maintenance_run(test_stages());
$success_date = get_option('facewall_maintenance_status')['last_success'];
$wpdb = new FacewallFakeDatabase();
facewall_maintenance_run(test_stages('officer_history'));
assert_maintenance(get_option('facewall_maintenance_status')['last_success'] === $success_date, 'Failure erased last success');

// Alerts are opt-in, rate limited, and contain no member records or SQL.
$GLOBALS['fake_emails'] = [];
$GLOBALS['fake_options']['facewall_maintenance_email_alerts'] = '1';
$GLOBALS['fake_options']['admin_email'] = 'administrator@example.test';
$wpdb = new FacewallFakeDatabase();
facewall_maintenance_run(test_stages('chart_data'));
facewall_maintenance_run(test_stages('chart_data'));
assert_maintenance(count($GLOBALS['fake_emails']) === 1, 'Failed-run email should be limited to once per day');
assert_maintenance(strpos($GLOBALS['fake_emails'][0]['message'], 'SQL:') === false, 'Raw SQL leaked into alert');
$GLOBALS['fake_options']['facewall_maintenance_email_alerts'] = '0';

// Health reports distinguish a recent success, failure and stale data.
$now = time();
$healthy = facewall_maintenance_health(['state' => 'success', 'last_attempt' => gmdate('c', $now), 'last_success' => gmdate('c', $now)], $now);
$stale = facewall_maintenance_health(['state' => 'success', 'last_attempt' => gmdate('c', $now), 'last_success' => gmdate('c', $now - 9 * DAY_IN_SECONDS)], $now);
$failed = facewall_maintenance_health(['state' => 'failed', 'last_attempt' => gmdate('c', $now)], $now);
assert_maintenance($healthy['level'] === 'healthy' && $stale['level'] === 'stale' && $failed['level'] === 'failed', 'Health classification failed');

// Admin routes must refuse unauthorised callers before checking the nonce.
try {
    facewall_maintenance_require_admin('facewall_maintenance_queue');
    throw new RuntimeException('Non-admin reached maintenance controls');
} catch (RuntimeException $e) {
    assert_maintenance(strpos($e->getMessage(), 'DENIED:') === 0, 'Admin action did not enforce capability first');
}
echo "FaceWall transactional maintenance regressions passed.\n";
