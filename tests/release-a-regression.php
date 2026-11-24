<?php
/**
 * Release A: deterministic AGM, under-30, poster-layout and view-boundary
 * checks with no live WordPress/CiviCRM database or PDF service.
 */
error_reporting(E_ALL);
define('ABSPATH', dirname(__DIR__) . '/');
define('PDF_UNIT', 'mm');
define('PDF_CREATOR', 'FaceWall test');
define('PDF_MARGIN_LEFT', 15.0);
define('PDF_MARGIN_RIGHT', 15.0);
define('PDF_MARGIN_BOTTOM', 15.0);
$GLOBALS['test_local_day'] = '2026-11-24';
function wp_date($format) {
    if ($format !== 'Y-m-d') { throw new RuntimeException('Unexpected wp_date format'); }
    return $GLOBALS['test_local_day'];
}
function current_user_can($cap) { return $cap === 'manage_options'; }
function wp_die($message) { throw new RuntimeException($message); }

function release_a_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}

/** Minimal TCPDF seam: captures real create_poster() positioning and SQL. */
class TCPDF_STATIC {
    public static $page_formats = [
        'A1' => [1683.780, 2383.937],
        'RA1' => [1729.134, 2437.795],
        'SRA1' => [1814.173, 2551.181],
    ];
    public static function getPageSizeFromFormat($size) {
        if (!isset(self::$page_formats[$size])) {
            throw new RuntimeException('Unknown paper size');
        }
        return self::$page_formats[$size];
    }
}
class TCPDF {
    public static $instance;
    public $images = [];
    public $output_called = false;
    public function __construct(...$args) { self::$instance = $this; }
    public function __call($name, $args) {
        switch (strtolower($name)) {
            case 'gety': return 80.0;
            case 'getx': return 15.0;
            case 'getstringwidth': return 35.0;
            case 'image':
                $this->images[] = ['x' => $args[1], 'y' => $args[2],
                    'width' => $args[3], 'height' => $args[4]];
                return null;
            case 'output':
                $this->output_called = true;
                return null;
            default: return null;
        }
    }
}
class FacewallReleaseADatabase {
    public $rows = [];
    public $last_query = '';
    public function prepare($sql, ...$args) {
        foreach ($args as $value) {
            $p = strpos($sql, '%d');
            if ($p === false) { throw new RuntimeException('Unexpected SQL parameter'); }
            $sql = substr_replace($sql, (string) (int) $value, $p, 2);
        }
        return $sql;
    }
    public function get_results($sql) {
        $this->last_query = $sql;
        return $this->rows;
    }
    public function query($sql) {
        throw new RuntimeException('Release A tests must not change CiviCRM data');
    }
}
global $wpdb;
$wpdb = new FacewallReleaseADatabase();
require dirname(__DIR__) . '/includes/calendar.php';
require dirname(__DIR__) . '/includes/poster-layout.php';
require dirname(__DIR__) . '/officer_grid_year.php';
require dirname(__DIR__) . '/poster.php';

foreach ([
    '2024' => '2024-10-05',
    '2025' => '2025-10-04',
    '2026' => '2026-10-03',
    '2027' => '2027-10-02',
] as $year => $expected) {
    release_a_assert(facewall_first_october_saturday((int) $year) === $expected,
        'First Saturday of October incorrect for ' . $year);
}

foreach ([
    '2026-01-01' => ['2025-10-04', 2026],
    '2026-10-02' => ['2025-10-04', 2026],
    '2026-10-03' => ['2026-10-03', 2027],
    '2026-10-04' => ['2026-10-03', 2027],
    '2026-11-24' => ['2026-10-03', 2027],
    '2026-12-31' => ['2026-10-03', 2027],
    '2027-01-01' => ['2026-10-03', 2027],
    '2027-10-01' => ['2026-10-03', 2027],
    '2027-10-02' => ['2027-10-02', 2028],
] as $today => [$agm, $committee_year]) {
    release_a_assert(facewall_most_recent_agm($today) === $agm,
        'Incorrect last AGM for ' . $today);
    release_a_assert(facewall_current_committee_year($today) === $committee_year,
        'Incorrect committee club year for ' . $today);
}
try {
    facewall_calendar_today('2026-02-30');
    throw new RuntimeException('Accepted a non-existent date');
} catch (InvalidArgumentException $expected) {
    // Correctly rejected.
}

// Real display functions must use the shared calendar instead of
// a previous-year assumption or raw calendar-year comparisons.
$GLOBALS['test_local_day'] = '2026-10-02';
$wpdb->rows = [];
display_facewall_under_30('Under 30', '< 4', '< 9');
release_a_assert(strpos($wpdb->last_query, "c.birth_date > DATE_SUB('2025-10-04', INTERVAL 30 YEAR)") !== false,
    'Under-30 view used the wrong AGM/date boundary before handover');

$GLOBALS['test_local_day'] = '2026-10-03';
display_facewall_under_30('Under 30', '< 4', '< 9');
release_a_assert(strpos($wpdb->last_query, "c.birth_date > DATE_SUB('2026-10-03', INTERVAL 30 YEAR)") !== false,
    'Under-30 view must exclude someone turning exactly 30 on AGM day');
display_officer_grid_year('current');
release_a_assert(strpos($wpdb->last_query, 'club_year = 2027') !== false,
    'Current officer grid did not switch to 2027 on AGM day');
display_officer_grid_year('all');
release_a_assert(strpos($wpdb->last_query, 'club_year <= 2027') !== false,
    'Historical officer grid used a different year boundary');
display_officer_list(0, 0, 0);
release_a_assert(strpos($wpdb->last_query, 'club_year') !== false &&
    strpos($wpdb->last_query, '<= 2027') !== false,
    'Officer list must include the new committee after AGM day');

// Preserve existing membership-type boundaries explicitly. These are BEC
// policy choices, not part of Release A's behaviour corrections.
$wpdb->rows = [];
display_facewall_normal_new('Current', '< 4', '<8');
release_a_assert(strpos($wpdb->last_query, 'mtype.id <8') !== false,
    'Current shortcode must retain its existing type<8 boundary');
display_facewall_normal('Admin', '< 4', '<9');
release_a_assert(strpos($wpdb->last_query, 'mtype.id <9') !== false,
    'Legacy admin grid must retain the existing type<9 boundary');
release_a_assert(strpos($wpdb->last_query, 'cust.facewall_public_84 = 1') !== false,
    'Public photo membership queries require opt-in consent');

// Pure grid maths, including 1-column and 1-row cases.
foreach ([1, 2, 7, 10, 11, 25, 50, 140, 300] as $members) {
    $usable_h = 445.0;
    $layout = facewall_poster_grid_layout($members, 841.0, $usable_h, 15.0, 15.0);
    release_a_assert($layout['rows'] === min(10, $members) &&
        $layout['columns'] === (int) ceil($members / $layout['rows']),
        'Poster row/column counts incorrect for ' . $members . ' members');
    foreach (['image_width','image_height','spacer_x','spacer_y','start_x'] as $key) {
        release_a_assert(is_finite($layout[$key]), 'Poster dimension is not finite: ' . $key);
    }
    release_a_assert($layout['image_width'] > 0 && $layout['image_height'] > 0 &&
        $layout['spacer_x'] >= 0 && $layout['spacer_y'] >= 0,
        'Poster dimensions negative/zero for ' . $members . ' members');
    release_a_assert($layout['image_height'] * $layout['rows'] +
        ($layout['rows'] - 1) * 5 + $layout['rows'] * 3 <= $usable_h + 0.001,
        'Poster images extend below printable area for ' . $members . ' members');
    if ($layout['columns'] === 1) {
        release_a_assert($layout['spacer_x'] === 0.0 && $layout['image_width'] <= 60,
            'Single-column poster was not bounded and centred');
    }
}
try {
    facewall_poster_grid_layout(0, 841, 440, 15, 15);
    throw new RuntimeException('Empty poster layout was allowed');
} catch (InvalidArgumentException $expected) {
    // Correctly rejected.
}

// Use the actual PDF entry point with fake TCPDF, rather than testing only the
// mathematical helper. Real PDF photos remain a staging/production check.
foreach ([1, 7, 10, 11, 160] as $members) {
    $rows = [];
    for ($i = 1; $i <= $members; $i++) {
        $rows[] = (object) [
            'first_name' => 'Test', 'last_name' => 'Member ' . $i,
            'image_URL' => '', 'post' => '',
        ];
    }
    $wpdb->rows = $rows;
    foreach (['A1','RA1','SRA1'] as $size) {
        create_poster('', $size);
        release_a_assert(TCPDF::$instance->output_called &&
            count(TCPDF::$instance->images) === $members,
            'PDF poster did not render exactly one photo per member: ' . $size);
        release_a_assert(strpos($wpdb->last_query, 'club_year = 2027') !== false &&
            strpos($wpdb->last_query, 'mtype.id <9') !== false &&
            strpos($wpdb->last_query, 'cust.facewall_public_84 = 1') !== false,
            'Poster changed committee-year, membership-type, or privacy filter');
        foreach (TCPDF::$instance->images as $image) {
            release_a_assert(is_finite($image['width']) && is_finite($image['height']) &&
                $image['width'] > 0 && $image['height'] > 0,
                'PDF image dimensions invalid');
        }
    }
}

echo "FaceWall Release A calendar, eligibility boundary and PDF layout regressions passed.\n";
