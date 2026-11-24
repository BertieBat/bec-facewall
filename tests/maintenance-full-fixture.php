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

// Fixture for BEC's membership-type boundary at IDs 7 / 8 / 9,
// including birthdays immediately before, on and after the 2026 AGM.
sql_or_fail("INSERT INTO civicrm_membership_type VALUES
    (7, 'Joint Probationary'), (8, 'Special'), (9, 'BCA Active')");
sql_or_fail("INSERT INTO civicrm_contact VALUES
    (71, 'Fixture', '', 'Type Seven', 'Fixture Type Seven', NULL, 0, NULL, '1996-10-04'),
    (72, 'Fixture', '', 'Type Eight', 'Fixture Type Eight', NULL, 0, NULL, '1996-10-03'),
    (73, 'Fixture', '', 'Type Nine', 'Fixture Type Nine', NULL, 0, NULL, '1996-10-02'),
    (74, 'Fixture', '', 'Turns Thirty', 'Fixture Turns Thirty', NULL, 0, NULL, '1996-10-03'),
    (75, 'Fixture', '', 'Still Twenty Nine', 'Fixture Still Twenty Nine', NULL, 0, NULL, '1996-10-04')");
sql_or_fail("INSERT INTO custom_value_1_BEC VALUES
    (71, 1071, 1), (72, 1072, 1), (73, 1073, 1),
    (74, 1074, 1), (75, 1075, 1)");
sql_or_fail("INSERT INTO civicrm_membership VALUES
    (171, 71, 7, '2024-01-01', '2024-01-01', '2027-09-30', 2, 0, 'Fixture'),
    (172, 72, 8, '2024-01-01', '2024-01-01', '2027-09-30', 2, 0, 'Fixture'),
    (173, 73, 9, '2024-01-01', '2024-01-01', '2027-09-30', 2, 0, 'Fixture'),
    (174, 74, 1, '2024-01-01', '2024-01-01', '2027-09-30', 2, 0, 'Fixture'),
    (175, 75, 1, '2024-01-01', '2024-01-01', '2027-09-30', 2, 0, 'Fixture')");

// Overlap the existing ordinary member's period: the membership log and
// legacy table must never produce two rows for one contact in a club year.
sql_or_fail("INSERT INTO civicrm_membership VALUES
    (103, 42, 1, '2024-10-01', '2025-01-01', '2026-09-30', 2, 0, 'Overlapping fixture')");
sql_or_fail("INSERT INTO civicrm_membership_log (membership_id, membership_type_id, status_id, start_date, end_date)
    VALUES (103, 1, 2, '2025-01-01', '2026-09-30')");


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
fixture_assert((int) $wpdb->get_var('SELECT COUNT(*) FROM civicrm_BEC_historical_membership WHERE contact_id = 42 AND club_year = 2025') === 1,
    'Overlapping log and legacy periods duplicated the 2025 membership');
fixture_assert((int) $wpdb->get_var('SELECT COUNT(*) FROM (
    SELECT contact_id, club_year FROM civicrm_BEC_historical_membership
    GROUP BY contact_id, club_year HAVING COUNT(*) > 1
) AS duplicates') === 0, 'Membership history contains duplicate contact/year records');

// Snapshot today's intentional type thresholds; Release A is not authorised
// to harmonise the <8 public shortcode and <9 admin/poster policy.
fixture_assert((int) $wpdb->get_var('SELECT COUNT(*) FROM civicrm_membership
    WHERE contact_id = 71 AND membership_type_id < 8') === 1,
    'Type 7 should be within the current shortcode boundary');
fixture_assert((int) $wpdb->get_var('SELECT COUNT(*) FROM civicrm_membership
    WHERE contact_id = 72 AND membership_type_id < 8') === 0,
    'Type 8 should remain outside the current shortcode boundary');
fixture_assert((int) $wpdb->get_var('SELECT COUNT(*) FROM civicrm_membership
    WHERE contact_id = 72 AND membership_type_id < 9') === 1,
    'Type 8 should remain inside legacy admin/poster boundary');
fixture_assert((int) $wpdb->get_var('SELECT COUNT(*) FROM civicrm_membership
    WHERE contact_id = 73 AND membership_type_id < 9') === 0,
    'Type 9 must remain outside the admin/poster boundary');

// SQL actually selects the correct birth-date boundary on AGM day:
$under30_ids = $wpdb->get_results("SELECT c.id FROM civicrm_contact AS c
    INNER JOIN civicrm_membership AS m ON m.contact_id = c.id
    INNER JOIN custom_value_1_BEC AS consent ON consent.entity_id = c.id
        AND consent.facewall_public_84 = 1
    WHERE m.membership_type_id < 9
      AND c.birth_date > DATE_SUB('2026-10-03', INTERVAL 30 YEAR)");
$under30_ids = array_map(static function ($row) { return (int) $row->id; }, $under30_ids);
fixture_assert(in_array(71, $under30_ids, true) && in_array(75, $under30_ids, true) &&
    !in_array(72, $under30_ids, true) && !in_array(73, $under30_ids, true) &&
    !in_array(74, $under30_ids, true),
    'Under-30 AGM eligibility includes an exactly-30 member or excludes someone still 29');

// Execute the actual shared public query in MariaDB to prove that the
// refactor retained membership type eligibility and FaceWall consent checks.
$public_members = $wpdb->get_results(facewall_member_grid_query('current', '< 4', '<8'));
fixture_assert(is_array($public_members), 'Shared current-members SQL could not be executed');
$member_numbers = array_map(static function ($row) { return (int) $row->Membership_Number; }, $public_members);
fixture_assert(in_array(1001, $member_numbers, true) &&
    in_array(1071, $member_numbers, true) &&
    !in_array(1072, $member_numbers, true) &&
    !in_array(1073, $member_numbers, true),
    'Shared current query changed the exact BEC membership-type eligibility');
$admin_members = $wpdb->get_results(facewall_member_grid_query('legacy', '< 4', '<9'));
$admin_numbers = array_map(static function ($row) { return (int) $row->Membership_Number; }, $admin_members);
fixture_assert(in_array(1072, $admin_numbers, true) &&
    !in_array(1073, $admin_numbers, true),
    'Legacy admin grid changed the intentional <9 membership filter');
$bad_filter = $wpdb->get_results(facewall_member_grid_query('current', '1 OR 1=1', '<8'));
fixture_assert(is_array($bad_filter) && count($bad_filter) === 0,
    'Invalid query filters must not broaden public CiviCRM member access');

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

// Two different people sharing a BEC membership number cannot safely
// occupy the legacy summary table's primary key. Refuse the rebuild before
// deleting summaries, and roll back any prior membership-history changes.
sql_or_fail("INSERT INTO civicrm_contact VALUES
    (9999, 'Collision', '', 'Example', 'Collision Example', NULL, 0, NULL, '1990-01-01')");
sql_or_fail("INSERT INTO custom_value_1_BEC VALUES (9999, 1001, 1)");
sql_or_fail("INSERT INTO civicrm_membership VALUES
    (9999, 9999, 1, '2024-01-01', '2024-01-01', '2027-09-30', 2, 0, 'Collision fixture')");
fixture_assert(facewall_maintenance_run() === 'failed',
    'Duplicate member numbers across distinct contacts must abort reporting rebuild');
fixture_assert(get_option('facewall_maintenance_status')['failed_stage'] === 'membership_summary',
    'Membership-number collision must fail at historical summary stage');
foreach ($committed as $table => $count) {
    fixture_assert((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $table) === $count,
        'Collision discarded last committed reports: ' . $table);
}
fixture_assert((int) $wpdb->get_var('SELECT COUNT(*) FROM civicrm_BEC_historical_summary WHERE contact_id = 42') === 1,
    'A membership number collision replaced an unrelated member summary');
sql_or_fail('DELETE FROM civicrm_membership WHERE id = 9999');
sql_or_fail('DELETE FROM custom_value_1_BEC WHERE entity_id = 9999');
sql_or_fail('DELETE FROM civicrm_contact WHERE id = 9999');



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
