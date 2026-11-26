<?php
/**
 * Check grid HTML doesn't close the surrounding WordPress/theme layout.
 * In particular, 1 / 5 / 6 members must all have balanced grid wrappers.
 */
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/../');

class FacewallGridTestDB {
    public $rows = [];
    public $total = null;
    public $count_queries = [];
    public function get_var($sql) { $this->count_queries[] = $sql; return $this->total; }
    public function get_results($sql) { return $this->rows; }
}
$GLOBALS['wpdb'] = new FacewallGridTestDB();
require dirname(__DIR__) . '/current_list.php';
require dirname(__DIR__) . '/officer_grid_year.php';

function grid_member($number, $year = 2026) {
    return (object) [
        'first_name' => 'Member', 'last_name' => (string) $number,
        'display_name' => 'Member ' . $number,
        'id' => $number, 'Membership_Number' => $number, 'club_year' => $year,
        'image_URL' => '', 'facewall_public_84' => '1',
        'summary' => 'Ordinary member',
        'post' => 'Secretary', 'weight' => 10,
        'join_date' => '2020-01-01', 'start_date' => '2020-01-01',
        'end_date' => '2027-09-30',
        'birth_date' => '1990-01-01',
        'civicrm_contact_sort_name_a' => 'Joint A',
        'civicrm_contact_b_sort_name_b' => 'Joint B',
        'civicrm_contact_image_URL_a' => '',
        'civicrm_contact_b_image_URL_b' => '',
        'near_contact_id' => $number, 'far_contact_id' => $number + 1,
        'near_summary' => 'Joint', 'far_summary' => 'Joint',
    ];
}

function assert_grid_markup($html, $label) {
    if (!is_string($html)) { throw new RuntimeException($label . ' did not return HTML'); }
    $opens = preg_match_all('/<div\b/i', $html);
    $closes = preg_match_all('/<\/div\s*>/i', $html);
    if ($opens !== $closes) {
        throw new RuntimeException($label . ' has unbalanced DIV tags (' . $opens . ' opens / ' . $closes . ' closes)');
    }
    if (substr_count($html, '<h5') !== substr_count($html, '</h5>')) {
        throw new RuntimeException($label . ' has unbalanced member name headings');
    }
    if (strpos($html, '<link') !== false) {
        throw new RuntimeException($label . ' injected a page-wide stylesheet into the content');
    }
    return true;
}

foreach ([1, 2, 5, 6, 11] as $count) {
    $GLOBALS['wpdb']->rows = [];
    for ($i = 1; $i <= $count; $i++) {
        $GLOBALS['wpdb']->rows[] = grid_member($i);
    }
    assert_grid_markup(display_facewall_normal('Active', '< 4', '< 8'), "Legacy photo grid with $count members");
    $current_grid = display_facewall_normal_new('Active', '< 4', '< 8');
    assert_grid_markup($current_grid, "Current photo grid with $count members");
    if (substr_count($current_grid, 'class="fw_middle"') !== $count ||
        substr_count($current_grid, 'tabindex="0"') !== $count) {
        throw new RuntimeException("Each current member card must have a keyboard-focusable hover overlay ($count members)");
    }
    $history_grid = display_all_membership();
    assert_grid_markup($history_grid, "Historical photo grid with $count members");
    if (substr_count($history_grid, 'class="fw_middle"') !== $count ||
        substr_count($history_grid, 'tabindex="0"') !== $count) {
        throw new RuntimeException("Historical member cards must retain hover and keyboard focus");
    }
    assert_grid_markup(display_officer_grid_year('current'), "Officer photo grid with $count members");
}

// Year transitions must close partial rows before the next heading.
$GLOBALS['wpdb']->rows = [
    grid_member(1, 2026),
    grid_member(2, 2026),
    grid_member(3, 2025),
    grid_member(4, 2025),
    grid_member(5, 2024),
];
assert_grid_markup(display_officer_grid_year('all'), 'Officer grid with year changes');

$GLOBALS['wpdb']->rows = [grid_member(1), grid_member(2)];
assert_grid_markup(display_facewall_joint('Joint', '< 4', 'IN (3,5,7)'), 'Joint photo grid');
assert_grid_markup(display_facewall_joint_invalid('Joint', '< 4', 'IN (3,5,7)'), 'Unusual joint photo grid');
$joint_standard = display_facewall_joint('Joint', '< 4', 'IN (3,5,7)');
$joint_unusual = display_facewall_joint_invalid('Joint', '< 4', 'IN (3,5,7)');
foreach ([$joint_standard, $joint_unusual] as $html) {
    if (substr_count($html, 'class="fw_middle"') !== substr_count($html, 'tabindex="0"')) {
        throw new RuntimeException('Every joint-membership history overlay must be keyboard-focusable');
    }
}

// The aggregate count must not add non-consenting cards or affect other scopes.
$GLOBALS['wpdb']->rows = [grid_member(1), grid_member(2)];
$GLOBALS['wpdb']->total = '3';
$current = display_facewall_normal_new('Current members of the BEC', '< 4', '<8', true);
if (strpos($current, '<h2>Current members of the BEC - 3 members</h2>') === false || strpos($current, '<p>Number of members displayed 2.</p>') === false || substr_count($current, 'class="fw_middle"') !== 2) {
    throw new RuntimeException('Current heading must distinguish total membership from public cards');
}
$sql = $GLOBALS['wpdb']->count_queries[0] ?? '';
if (strpos($sql, 'COUNT(DISTINCT c.id)') === false || strpos($sql, 'facewall_public_84') !== false || strpos($sql, 'mtype.id < 8') === false || strpos($sql, 'mem.status_id < 4') === false || strpos($sql, 'c.is_deceased != 1') === false) {
    throw new RuntimeException('Aggregate query must count distinct eligible contacts without consent filtering');
}
$other = display_facewall_normal_new('Grace members', '= 3', '<9');
if (strpos($other, '2 members</h2>') === false || strpos($other, 'Number of records 2') === false || count($GLOBALS['wpdb']->count_queries) !== 1) {
    throw new RuntimeException('Other scopes must retain their existing heading and avoid the aggregate query');
}
$GLOBALS['wpdb']->total = null;
if (strpos(display_facewall_normal_new('Current', '< 4', '<8', true), '2 members</h2>') === false) {
    throw new RuntimeException('Failed aggregate must fall back to the public count');
}
$GLOBALS['wpdb']->rows = [];
$GLOBALS['wpdb']->total = '3';
if (strpos($empty = display_facewall_normal_new('Current', '< 4', '<8', true), '<h2>Current - 3 members</h2>') === false || strpos($empty, 'Number of members displayed 0.') === false) {
    throw new RuntimeException('All-private membership must still show the aggregate only');
}
$GLOBALS['wpdb']->total = null;

echo "FaceWall photo grid layout and closing-tag regressions passed.\n";
