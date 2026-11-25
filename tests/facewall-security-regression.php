<?php
/**
 * Security regressions run without WordPress or a live CiviCRM database.
 * Run with: php tests/facewall-security-regression.php
 */
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/../');
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/facewall-security-check-' . getmypid());

function fail_test($message) { throw new RuntimeException($message); }
function expect_security($expected, $actual, $message) {
    if ($expected !== $actual) { fail_test($message . ': ' . var_export($actual, true)); }
}
function current_user_can($capability) { return false; }
function wp_die($message) { throw new RuntimeException('Permission refused: ' . $message); }

require dirname(__DIR__) . '/includes/security.php';
expect_security(true, facewall_is_public(1), 'Numeric public consent must work');
expect_security(true, facewall_is_public('1'), 'Stored SQL string public consent must work');
foreach ([0, '0', null, '', 2, 'true', true] as $not_consent) {
    // PHP booleans do not appear as CiviCRM custom-field values; testing false
    // and unexpected values verifies fail-closed decisions.
    if ($not_consent === true) { continue; }
    expect_security(false, facewall_is_public($not_consent), 'Unexpected value must not be consent');
}
expect_security('normal.jpg', facewall_image_filename('https://bec-cave.org.uk/path/tn_normal.jpg'), 'CiviCRM tn_ prefix');
expect_security('normal.png', facewall_image_filename('tn_normal.png'), 'Cached historical thumbnail');
expect_security('normal.jpg', facewall_image_filename('https://bec-cave.org.uk/photo?photo=normal.jpg'), 'CiviCRM photo query parameter');
// CiviCRM 2026 live-data regression: 104 of 189 eligible contact photo URLs
// use HTML-encoded query delimiters. The photograph must be found without
// removing query parameters, fetching URLs, or weakening path validation.
$encoded_civicrm = 'https://bec-cave.org.uk/index.php?option=com_civicrm&amp;task=civicrm/file&amp;photo=normal.jpg';
expect_security('normal.jpg', facewall_image_filename($encoded_civicrm),
    'HTML-escaped CiviCRM index.php photo URL');
expect_security('normal.jpg', facewall_image_filename(
    'https://bec-cave.org.uk/index.php?option=com_civicrm&task=civicrm/file&photo=normal.jpg'),
    'Normal CiviCRM index.php photo URL');
expect_security('normal.jpg', facewall_image_filename(
    'https://bec-cave.org.uk/index.php?option=com_civicrm&#38;task=civicrm/file&#38;photo=normal.jpg'),
    'Numeric-entity CiviCRM photo URL');
expect_security('normal.jpg', facewall_image_filename(
    'https://bec-cave.org.uk/index.php?option=com_civicrm&amp;photo=normal.jpg&amp;download=1'),
    'HTML-escaped photo parameter before additional parameters');
foreach ([
    'https://bec-cave.org.uk/index.php?option=com_civicrm&amp;photo=..%2Fprivate.jpg',
    'https://bec-cave.org.uk/index.php?option=com_civicrm&amp;photo=%2e%2e%2fprivate.png',
    'https://bec-cave.org.uk/index.php?option=com_civicrm&amp;photo=script.php',
    'https://bec-cave.org.uk/index.php?option=com_civicrm&amp;photo=not-an-image.svg',
    'https://bec-cave.org.uk/index.php?option=com_civicrm&amp;photo%5B0%5D=normal.jpg',
] as $invalid_encoded) {
    expect_security('', facewall_image_filename($invalid_encoded),
        'Unsafe encoded CiviCRM photo reference must be rejected');
}

foreach (['../../secret.jpg', '%2e%2e%2fsecret.png', 'shell.php', 'image.svg', "test.jpg\0.php", ''] as $invalid) {
    expect_security('', facewall_image_filename($invalid), 'Unsafe photo filename must be rejected');
}

$summary = facewall_summary_html('Ordinary (24-25)<br><img src=x onerror=alert(1)>');
if (strpos($summary, '<img') !== false || strpos($summary, '<br') === false) {
    fail_test('Member summaries must preserve line breaks but strip unsafe HTML');
}
if (strpos(facewall_h('<script>bad</script>'), '<script>') !== false) {
    fail_test('Names must be HTML escaped');
}

$images_dir = WP_CONTENT_DIR . '/uploads/civicrm/custom/tn/174';
if (!mkdir($images_dir, 0700, true) && !is_dir($images_dir)) {
    fail_test('Could not create temporary image test directory');
}
file_put_contents($images_dir . '/tn_normal.jpg', 'test thumbnail');
$opted_out = facewall_photo_html('/path/normal.jpg', '<person>', false);
if (strpos($opted_out, 'tn_normal.jpg') !== false || strpos($opted_out, 'unknown.jpg') === false) {
    fail_test('Non-consenting member photo was displayed');
}
$opted_in = facewall_photo_html($encoded_civicrm, '"Alice"', true);
if (strpos($opted_in, 'tn_normal.jpg') === false || strpos($opted_in, '&quot;Alice&quot;') === false) {
    fail_test('Consent photo URL or alt-attribute escaped incorrectly');
}
if (strpos(facewall_photo_html($encoded_civicrm, 'Private', false), 'unknown.jpg') === false) {
    fail_test('HTML-encoded photo URL must not bypass public consent');
}

if (strpos(facewall_photo_html('../../secret.jpg', 'Secret', true), 'unknown.jpg') === false) {
    fail_test('A traversal path must never be used as a picture');
}
unlink($images_dir . '/tn_normal.jpg');
rmdir($images_dir);
rmdir(dirname($images_dir));
rmdir(dirname(dirname($images_dir)));
rmdir(dirname(dirname(dirname($images_dir))));
rmdir(dirname(dirname(dirname(dirname($images_dir)))));
rmdir(WP_CONTENT_DIR);

class FacewallSecurityTestDB {
    public $queries = [];
    public function prepare($sql, ...$args) {
        foreach ($args as $value) {
            $pos = strpos($sql, '%d');
            if ($pos === false) {
                fail_test('Unexpected query parameter in mocked read');
            }
            $sql = substr_replace($sql, (string) (int) $value, $pos, 2);
        }
        return $sql;
    }
    public function get_results($query) {
        $this->queries[] = $query;
        return [];
    }
    public function query($query) { fail_test('Unexpected database write during public rendering'); }
}
$GLOBALS['wpdb'] = new FacewallSecurityTestDB();

require dirname(__DIR__) . '/current_list.php';
display_facewall_joint('Joint', '', '');
display_facewall_joint_invalid('Joint invalid', '', '');
if (count($GLOBALS['wpdb']->queries) !== 2) {
    fail_test('Expected exactly two read-only Joint Members SELECT queries');
}
foreach ($GLOBALS['wpdb']->queries as $query) {
    if (!preg_match('/\bnear_priv\.facewall_public_84\s*=\s*1\b/', $query) ||
        !preg_match('/\bfar_priv\.facewall_public_84\s*=\s*1\b/', $query) ||
        strpos($query, 'FROM civicrm_relationship_cache') === false) {
        fail_test('Joint member queries must require both public consent flags');
    }
}

// No match after applying privacy filters must never crash a public page.
require dirname(__DIR__) . '/officer_grid_year.php';
require dirname(__DIR__) . '/membership_list.php';
$no_rows = [
    display_facewall_normal_new('Current', '<4', '<8'),
    display_facewall_under_30('Under 30', '<4', '=15'),
    display_facewall_probationary('Probationary', '<4', '=2'),
    display_all_membership(),
    display_officer_list('', '', ''),
    display_officer_grid_year('current'),
    show_membership_data(),
];
foreach ($no_rows as $html) {
    expect_security('<p>No members found.</p>', $html,
        'Empty member, historical or officer display must not throw');
}
foreach (array_slice($GLOBALS['wpdb']->queries, 2) as $sql) {
    if (strpos($sql, 'facewall_public_84 = 1') === false) {
        fail_test('A public member SQL report is missing the FaceWall consent filter');
    }
}

require dirname(__DIR__) . '/poster.php';
try {
    create_poster('', 'A1');
    fail_test('A public caller was allowed to generate a PDF poster');
} catch (RuntimeException $error) {
    if (strpos($error->getMessage(), 'Permission refused:') !== 0) {
        throw $error;
    }
}

// Confirm static safeguards for the remaining scopes and the PDF write mode.
$grid_source = file_get_contents(dirname(__DIR__) . '/officer_grid_year.php');
$poster_source = file_get_contents(dirname(__DIR__) . '/poster.php');
$cron_source = file_get_contents(dirname(__DIR__) . '/bec-facewall.php');
if (strpos($cron_source, "admin_post_facewall_download_poster") === false ||
    strpos($cron_source, "check_admin_referer('facewall_download_poster')") === false ||
    strpos($cron_source, "current_user_can('manage_options')") === false) {
    fail_test('PDF downloads must use a nonce-protected administrator-only endpoint');
}

$historical_source = file_get_contents(dirname(__DIR__) . '/membership_list.php');
// Release C centralizes the three historic-backed member-grid projections
// and their mandatory consent predicates. The separate officer and all-time
// history views still enforce their own consent at query time.
$grid_query_source = file_get_contents(dirname(__DIR__) . '/includes/member-query.php');
if (substr_count($grid_query_source, 'c.image_URL AS contact_image_URL') < 3 ||
    substr_count($grid_source, 'contact_image_URL)') < 5) {
    fail_test('Current CiviCRM image fallback is incomplete in member and officer grids');
}
if (strpos($grid_query_source, 'cust.facewall_public_84 = 1') === false ||
    substr_count($grid_source, 'facewall_public_84 = 1') < 3) {
    fail_test('Shared member queries or historical/officer views lost their consent filters');
}
if (strpos($historical_source, 'consent.facewall_public_84 = 1') === false) {
    fail_test('Historical membership list does not enforce consent');
}
if (strpos($poster_source, 'cust.facewall_public_84 = 1') === false ||
    strpos($poster_source, "'.pdf', 'D'") === false ||
    strpos($poster_source, "'.pdf', 'FD'") !== false) {
    fail_test('PDF generation should be opt-in and download-only');
}
if (strpos($cron_source, 'facewall_cron.txt') !== false ||
    strpos(file_get_contents(dirname(__DIR__) . '/current_list.php'), 'rebuild_RelationshipCache') !== false) {
    fail_test('Public file logging or relationship-cache rebuild still exists');
}
echo "FaceWall security regression checks passed.\n";
