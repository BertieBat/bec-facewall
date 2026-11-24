<?php
/**
 * Run the ACTUAL FaceWall rebuild functions against disposable MariaDB tables.
 * Never run this test outside CI's explicit facewall_ci database.
 */
error_reporting(E_ALL);
if (getenv('FACEWALL_TEST_DB_NAME') !== 'facewall_ci') {
    echo "Full rebuild fixture skipped outside disposable facewall_ci database.\n";
    exit(0);
}
define('ABSPATH', __DIR__ . '/../');
define('DAY_IN_SECONDS', 86400);
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/facewall-fixture-uploads');
$GLOBALS['fake_options'] = [];

function get_current_blog_id() { return 1; }
function get_option($key, $fallback = false) { return $GLOBALS['fake_options'][$key] ?? $fallback; }
function update_option($key, $value, $autoload = null) { $GLOBALS['fake_options'][$key] = $value; return true; }

require dirname(__DIR__) . '/includes/security.php';
require dirname(__DIR__) . '/includes/maintenance.php';
require dirname(__DIR__) . '/current_list.php';
require dirname(__DIR__) . '/officer_grid_year.php';
require dirname(__DIR__) . '/membership_list.php';

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli(
    getenv('FACEWALL_TEST_DB_HOST') ?: '127.0.0.1',
    getenv('FACEWALL_TEST_DB_USER') ?: 'root',
    getenv('FACEWALL_TEST_DB_PASSWORD') ?: '',
    'facewall_ci',
    (int) (getenv('FACEWALL_TEST_DB_PORT') ?: 3306)
);
if ($conn->connect_errno) { throw new RuntimeException('Disposable DB connection failed'); }

class FacewallFixtureDb {
    public $db;
    public $last_error = '';
    public function __construct($db) { $this->db = $db; }
    public function prepare($sql, ...$args) {
        foreach ($args as $arg) {
            $at = strpos($sql, '%');
            if ($at === false) { throw new RuntimeException('Unexpected SQL placeholder count'); }
            $type = substr($sql, $at, 2);
            if ($type !== '%s' && $type !== '%d') {
                throw new RuntimeException('Unexpected SQL placeholder type');
            }
            $value = $type === '%d'
                ? (string) (int) $arg
                : "'" . $this->db->real_escape_string((string) $arg) . "'";
            $sql = substr_replace($sql, $value, $at, 2);
        }
        return $sql;
    }
    public function query($sql) {
        $result = $this->db->query($sql);
        $this->last_error = $this->db->error;
        return $result === false ? false : $this->db->affected_rows;
    }
    public function get_results($sql) {
        $result = $this->db->query($sql);
        $this->last_error = $this->db->error;
        if (!($result instanceof mysqli_result)) { return null; }
        $rows = [];
        while ($row = $result->fetch_object()) { $rows[] = $row; }
        $result->free();
        return $rows;
    }
    public function get_var($sql) {
        $result = $this->db->query($sql);
        $this->last_error = $this->db->error;
        if (!($result instanceof mysqli_result)) { return null; }
        $row = $result->fetch_row();
        $result->free();
        return $row === null ? null : $row[0];
    }
}
global $wpdb;
$wpdb = new FacewallFixtureDb($conn);
function fixture_assert($success, $message) {
    if (!$success) { throw new RuntimeException($message); }
}
function sql_or_fail($sql) {
    global $wpdb;
    fixture_assert($wpdb->query($sql) !== false, 'Fixture SQL failed (see private CI log): ' . $wpdb->last_error);
}

$schema = [
    'civicrm_contact' =>
        'id INT PRIMARY KEY, first_name VARCHAR(80), nick_name VARCHAR(80), last_name VARCHAR(80), display_name VARCHAR(255), image_URL VARCHAR(255), is_deceased TINYINT, deceased_date DATE NULL, birth_date DATE',
    'custom_value_1_BEC' =>
        'entity_id INT PRIMARY KEY, Membership_Number INT, facewall_public_84 TINYINT',
    'civicrm_membership_type' => 'id INT PRIMARY KEY, name VARCHAR(80)',
    'civicrm_membership_status' => 'id INT PRIMARY KEY, name VARCHAR(80)',
    'civicrm_membership' =>
        'id INT PRIMARY KEY, contact_id INT, membership_type_id INT, join_date DATE, start_date DATE, end_date DATE, status_id INT, is_test TINYINT, source VARCHAR(80)',
    'civicrm_membership_log' =>
        'id INT PRIMARY KEY AUTO_INCREMENT, membership_id INT, membership_type_id INT, status_id INT, start_date DATE, end_date DATE',
    'civicrm_value_committee_14' => 'id INT PRIMARY KEY AUTO_INCREMENT, entity_id INT, post_33 VARCHAR(80)',
    'civicrm_option_value' => 'id INT PRIMARY KEY AUTO_INCREMENT, label VARCHAR(80), option_group_id INT, weight INT',
    'civicrm_BEC_historical_membership' =>
        'contact_id INT NOT NULL, display_name VARCHAR(255), club_year INT NOT NULL, join_date DATE NULL, start_date DATE NULL, end_date DATE NULL, membership_type INT, UNIQUE KEY unique_member_year (contact_id, club_year)',
    'civicrm_BEC_historical_summary' =>
        'id INT PRIMARY KEY, contact_id INT, display_name VARCHAR(255), summary TEXT, image_URL VARCHAR(255)',
    'civicrm_BEC_historical_officer_membership' =>
        'id INT PRIMARY KEY AUTO_INCREMENT, contact_id INT, display_name VARCHAR(255), post VARCHAR(80), club_year INT, join_date DATE, start_date DATE, end_date DATE, weight INT, image_URL VARCHAR(255)',
    'civicrm_BEC_current_year' =>
        'id INT PRIMARY KEY AUTO_INCREMENT, mem_years INT, display_name VARCHAR(255), age INT',
];

foreach ($schema as $table => $columns) {
    sql_or_fail('CREATE TABLE IF NOT EXISTS ' . $table . ' (' . $columns . ') ENGINE=InnoDB');
}
sql_or_fail("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ONLY_FULL_GROUP_BY'");

sql_or_fail("INSERT INTO civicrm_contact VALUES
    (42, 'Test', '', 'Member', 'Test Member', 'portrait.jpg', 0, NULL, '1990-01-01')");
sql_or_fail("INSERT INTO custom_value_1_BEC VALUES (42, 1001, 1)");
sql_or_fail("INSERT INTO civicrm_membership_type VALUES (1, 'Ordinary'), (18, 'Committee')");
sql_or_fail("INSERT INTO civicrm_membership_status VALUES (2, 'Current')");
sql_or_fail("INSERT INTO civicrm_membership VALUES
    (101, 42, 1, '2024-10-01', '2024-10-01', '2027-09-30', 2, 0, 'Renewal'),
    (102, 42, 18, '2024-10-01', '2024-10-01', '2026-09-30', 2, 0, 'Membership Secretary')");
sql_or_fail("INSERT INTO civicrm_membership_log (membership_id, membership_type_id, status_id, start_date, end_date)
    VALUES (101, 1, 2, '2024-10-01', '2027-09-30')");
sql_or_fail("INSERT INTO civicrm_value_committee_14 (entity_id, post_33)
    VALUES (102, 'Membership Secretary')");
sql_or_fail("INSERT INTO civicrm_option_value (label, option_group_id, weight)
    VALUES ('Membership Secretary', 149, 10)");

sql_or_fail("INSERT INTO civicrm_BEC_historical_membership
    (contact_id, display_name, club_year, start_date, end_date, membership_type)
    VALUES (999, 'Prior', 2020, '2020-01-01', '2020-12-31', 1)");
sql_or_fail("INSERT INTO civicrm_BEC_historical_summary
    VALUES (999, 999, 'Prior', 'Old summary', '')");
sql_or_fail("INSERT INTO civicrm_BEC_historical_officer_membership
    (contact_id, display_name, post, club_year, weight)
    VALUES (999, 'Prior', 'Old officer', 2020, 1)");
sql_or_fail("INSERT INTO civicrm_BEC_current_year (mem_years, display_name, age)
    VALUES (99, 'Prior', 99)");

fixture_assert(facewall_maintenance_run() === 'completed',
    'Actual four-stage rebuild did not complete. Inspect private CI SQL error output.');
foreach (facewall_maintenance_tables() as $table) {
    fixture_assert((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $table) > 0,
        'Actual rebuild left table empty: ' . $table);
}
fixture_assert((int) $wpdb->get_var('SELECT COUNT(*) FROM civicrm_BEC_historical_membership WHERE contact_id = 42') > 0,
    'Membership history was not generated');
fixture_assert((int) $wpdb->get_var('SELECT COUNT(*) FROM civicrm_BEC_historical_summary WHERE contact_id = 42') === 1,
    'Historical summary was not generated');
fixture_assert($wpdb->get_var('SELECT image_URL FROM civicrm_BEC_historical_summary WHERE contact_id = 42') === 'tn_portrait.jpg',
    'Photo reference was lost when the source image was unavailable during the rebuild');
fixture_assert((int) $wpdb->get_var('SELECT COUNT(*) FROM civicrm_BEC_historical_officer_membership WHERE contact_id = 42') > 0,
    'Officer history was not generated');
fixture_assert((int) $wpdb->get_var("SELECT COUNT(*) FROM civicrm_BEC_current_year WHERE display_name = 'Test Member'") === 1,
    'Chart data was not generated');

$committed = [];
foreach (facewall_maintenance_tables() as $table) {
    $committed[$table] = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $table);
}
// Force an SQL error in the summary step AFTER the earlier membership step
// deleted/rebuilt data. The whole run must roll back to committed rows.
sql_or_fail("CREATE TRIGGER facewall_fixture_fail_summary BEFORE INSERT
    ON civicrm_BEC_historical_summary FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Injected CI summary failure'");
fixture_assert(facewall_maintenance_run() === 'failed',
    'Forced summary SQL error was not reported');
fixture_assert(get_option('facewall_maintenance_status')['failed_stage'] === 'membership_summary',
    'Failed stage was not identified');
foreach ($committed as $table => $count) {
    fixture_assert((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $table) === $count,
        'Real SQL failure did not roll back ' . $table);
}
sql_or_fail('DROP TRIGGER facewall_fixture_fail_summary');

// Simulate inaccessible CiviCRM source and verify preflight refuses deletion.
sql_or_fail('DROP TABLE civicrm_membership_log');
fixture_assert(facewall_maintenance_run() === 'failed',
    'Missing CiviCRM source should abort the rebuild');
foreach ($committed as $table => $count) {
    fixture_assert((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $table) === $count,
        'Missing source lost previous history: ' . $table);
}

foreach (array_keys($schema) as $table) {
    sql_or_fail('DROP TABLE IF EXISTS ' . $table);
}
$conn->close();
echo "FaceWall full CiviCRM-like rebuild integration passed.\n";
