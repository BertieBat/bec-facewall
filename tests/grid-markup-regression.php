<?php
/**
 * Check grid HTML doesn't close the surrounding WordPress/theme layout.
 * In particular, 1 / 5 / 6 members must all have balanced grid wrappers.
 */
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/../');

class FacewallGridTestDB {
    public $rows = [];
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
    assert_grid_markup(display_facewall_normal_new('Active', '< 4', '< 8'), "Current photo grid with $count members");
    assert_grid_markup(display_all_membership(), "Historical photo grid with $count members");
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

echo "FaceWall photo grid layout and closing-tag regressions passed.\n";
