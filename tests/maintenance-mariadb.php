<?php
/**
 * Real MariaDB integration test for atomic reporting-table rebuilds.
 * Runs ONLY against the explicit disposable facewall_ci database.
 *
 * Required environment:
 * FACEWALL_TEST_DB_NAME=facewall_ci
 * FACEWALL_TEST_DB_HOST / _USER / _PASSWORD / _PORT
 */
error_reporting(E_ALL);
if (getenv('FACEWALL_TEST_DB_NAME') !== 'facewall_ci') {
    fwrite(STDOUT, "MariaDB integration test skipped outside disposable facewall_ci database.\n");
    exit(0);
}
defined('ABSPATH') || define('ABSPATH', __DIR__ . '/../');
defined('DAY_IN_SECONDS') || define('DAY_IN_SECONDS', 86400);
$GLOBALS['fake_options'] = [];
function get_current_blog_id() { return 1; }
function get_option($name, $default = false) { return $GLOBALS['fake_options'][$name] ?? $default; }
function update_option($name, $value, $autoload = null) { $GLOBALS['fake_options'][$name] = $value; return true; }

require dirname(__DIR__) . '/includes/maintenance.php';

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = new mysqli(
    getenv('FACEWALL_TEST_DB_HOST') ?: '127.0.0.1',
    getenv('FACEWALL_TEST_DB_USER') ?: 'root',
    getenv('FACEWALL_TEST_DB_PASSWORD') ?: '',
    getenv('FACEWALL_TEST_DB_NAME'),
    (int) (getenv('FACEWALL_TEST_DB_PORT') ?: 3306)
);
if ($mysqli->connect_errno) {
    throw new RuntimeException('Cannot connect to disposable CI MariaDB database.');
}

class FacewallIntegrationDb {
    public $connection;
    public $last_error = '';
    public function __construct($connection) { $this->connection = $connection; }
    public function prepare($sql, ...$args) {
        foreach ($args as $arg) {
            $sql = preg_replace('/%s/', "'" . $this->connection->real_escape_string((string) $arg) . "'", $sql, 1);
        }
        return $sql;
    }
    public function query($sql) {
        $result = $this->connection->query($sql);
        $this->last_error = $this->connection->error;
        return $result === false ? false : $this->connection->affected_rows;
    }
    public function get_var($sql) {
        $result = $this->connection->query($sql);
        $this->last_error = $this->connection->error;
        if ($result === false) {
            return null;
        }
        if (!($result instanceof mysqli_result)) {
            return null;
        }
        $row = $result->fetch_row();
        $result->free();
        return $row === null ? null : $row[0];
    }
}

function expect_db($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
function stage_list($fail_stage = '') {
    $stages = [];
    foreach (facewall_maintenance_stages() as $name => $definition) {
        $table = $definition['table'];
        $stages[$name] = [
            'table' => $table,
            'callback' => static function () use ($name, $table, $fail_stage) {
                global $wpdb;
                if ($wpdb->query('DELETE FROM ' . $table) === false) {
                    return 'test - failed (delete)';
                }
                if ($name === $fail_stage) {
                    return 'test - failed (insert)';
                }
                if ($wpdb->query("INSERT INTO " . $table . " (marker) VALUES ('fresh')") === false) {
                    return 'test - failed (insert)';
                }
                return 'test - completed';
            },
        ];
    }
    return $stages;
}

global $wpdb;
$wpdb = new FacewallIntegrationDb($mysqli);

// Separate connection to test whether an uncommitted DELETE stays hidden.
$observer = new mysqli(
    getenv('FACEWALL_TEST_DB_HOST') ?: '127.0.0.1',
    getenv('FACEWALL_TEST_DB_USER') ?: 'root',
    getenv('FACEWALL_TEST_DB_PASSWORD') ?: '',
    'facewall_ci',
    (int) (getenv('FACEWALL_TEST_DB_PORT') ?: 3306)
);
if ($observer->connect_errno) {
    throw new RuntimeException('Cannot open independent MariaDB reader.');
}
$tables = facewall_maintenance_tables();
foreach ($tables as $table) {
    expect_db($wpdb->query('CREATE TABLE IF NOT EXISTS ' . $table .
        ' (id INT AUTO_INCREMENT PRIMARY KEY, marker VARCHAR(32) NOT NULL) ENGINE=InnoDB') !== false,
        'Cannot initialise disposable reporting table');
}

function seed_old($tables) {
    global $wpdb;
    foreach ($tables as $table) {
        expect_db($wpdb->query('DELETE FROM ' . $table) !== false, 'Could not reset test data');
        expect_db($wpdb->query("INSERT INTO " . $table . " (marker) VALUES ('old')") !== false, 'Could not seed original data');
    }
}
function assert_markers($tables, $expected) {
    global $wpdb;
    foreach ($tables as $table) {
        $actual = $wpdb->get_var('SELECT marker FROM ' . $table . ' ORDER BY id LIMIT 1');
        $count = $wpdb->get_var('SELECT COUNT(*) FROM ' . $table);
        expect_db($actual === $expected && (int) $count === 1,
            'Unexpected contents of ' . $table . ', expected ' . $expected);
    }
}

seed_old($tables);
// Prove a second database connection sees old data during the transaction.
$stages = stage_list();
$first = array_key_first($stages);
$original = $stages[$first]['callback'];
$stages[$first]['callback'] = static function () use ($original, $observer, $tables) {
    $result = $original();
    $read = $observer->query('SELECT marker FROM ' . $tables[0] . ' LIMIT 1');
    $row = $read ? $read->fetch_row() : null;
    if ($read) { $read->free(); }
    expect_db($row !== null && $row[0] === 'old', 'Readers observed partially rebuilt reporting data');
    return $result;
};
expect_db(facewall_maintenance_run($stages) === 'completed', 'Happy-path InnoDB rebuild failed');
assert_markers($tables, 'fresh');

seed_old($tables);
expect_db(facewall_maintenance_run(stage_list('officer_history')) === 'failed', 'Injected stage failure did not fail run');
assert_markers($tables, 'old');
expect_db(get_option('facewall_maintenance_status')['failed_stage'] === 'officer_history', 'Failure diagnostics wrong');

seed_old($tables);
expect_db($wpdb->query('ALTER TABLE civicrm_BEC_current_year ENGINE=MyISAM') !== false, 'Cannot set up nontransactional table');
expect_db(facewall_maintenance_run(stage_list()) === 'failed', 'MyISAM should prevent transaction');
assert_markers($tables, 'old');
expect_db($wpdb->query('ALTER TABLE civicrm_BEC_current_year ENGINE=InnoDB') !== false, 'Failed to restore test table engine');

foreach ($tables as $table) {
    $wpdb->query('DROP TABLE IF EXISTS ' . $table);
}
$observer->close();
$mysqli->close();
echo "FaceWall MariaDB atomic rebuild integration tests passed.\n";
