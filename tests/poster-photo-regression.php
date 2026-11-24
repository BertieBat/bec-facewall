<?php
/**
 * Poster-photo incident regression: missing full-scale cache must fall back
 * to a local, consented small thumbnail, with an admin-only coverage audit.
 */
error_reporting(E_ALL);
define('ABSPATH', dirname(__DIR__) . '/');
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/facewall-poster-photos-' . getmypid());

$GLOBALS['poster_audit_admin'] = false;
function current_user_can($cap) { return $GLOBALS['poster_audit_admin'] && $cap === 'manage_options'; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

class FacewallPosterAuditTestDatabase {
    public $queries = [];
    public $rows = [];
    public function get_results($sql) {
        $this->queries[] = $sql;
        if (strpos($sql, 'consent.facewall_public_84 = 1') === false ||
            strpos($sql, 'mtype.id < 9') === false ||
            strpos($sql, 'mem.status_id < 4') === false ||
            strpos($sql, 'c.is_deceased != 1') === false ||
            strpos($sql, 'SELECT DISTINCT c.id') === false) {
            throw new RuntimeException('Poster audit did not use consenting/current member eligibility');
        }
        return $this->rows;
    }
    public function query($sql) {
        throw new RuntimeException('Poster image audit must never modify CiviCRM data');
    }
}

function poster_assert($cond, $message) {
    if (!$cond) { throw new RuntimeException($message); }
}

require dirname(__DIR__) . '/includes/security.php';
require dirname(__DIR__) . '/includes/photo-audit.php';

$base = WP_CONTENT_DIR . '/uploads/civicrm/custom';
$small = $base . '/tn/174';
$full = $base . '/tn/full_scale';
$app_dir = WP_CONTENT_DIR . '/uploads/bec-membership-photos';
poster_assert(mkdir($small, 0700, true), 'Could not create small test cache');
poster_assert(mkdir($full, 0700, true), 'Could not create poster test cache');
poster_assert(mkdir($app_dir, 0700, true), 'Could not create application photo test folder');
foreach ([
    $small . '/tn_both.jpg',
    $full . '/tn_both.jpg',
    $small . '/tn_small-only.jpg',
    $full . '/tn_full-only.png',
    $base . '/original-only.jpg',
    $app_dir . '/application-24-s4mc5z2y5fhuafx6dhu0eehq.jpg',
    $app_dir . '/application-18-1ocyo3mvapv3fyb70lfi6qle.jpg',
] as $file) {
    file_put_contents($file, 'Local test fixture');
}

poster_assert(facewall_full_size_thumbnail_path('small-only.jpg', true) === '',
    'Full-size path must remain a strict test of the full-size file');
poster_assert(facewall_poster_image_path('both.jpg', true) === $full . '/tn_both.jpg',
    'Full-size must remain the preferred photo when both caches exist');
// Live CiviCRM stores some URLs as escaped HTML, not raw URLs. The PDF
// must resolve them to the same local cache without contacting index.php.
$escaped_url = 'https://bec-cave.org.uk/index.php?option=com_civicrm&amp;task=civicrm/file&amp;photo=both.jpg';
poster_assert(facewall_poster_image_path($escaped_url, true) === $full . '/tn_both.jpg',
    'Poster does not resolve HTML-escaped CiviCRM photo links');
poster_assert(facewall_poster_image_path($escaped_url, false) === '',
    'HTML-escaped poster reference bypassed public consent');
poster_assert(facewall_poster_image_path('small-only.jpg', true) === $small . '/tn_small-only.jpg',
    'Poster discarded an existing website photo when full-size cache is missing');
poster_assert(facewall_poster_image_path('full-only.png', true) === $full . '/tn_full-only.png',
    'Poster should prefer its full-size cache even if small cache is missing');
poster_assert(facewall_poster_image_path('original-only.jpg', true) === '',
    'Read-only poster unexpectedly used an unprocessed original');
poster_assert(facewall_poster_image_path('small-only.jpg', false) === '',
    'PDF fallback bypassed explicit public consent');
poster_assert(facewall_poster_image_path('gone.jpg', true, 'small-only.jpg') ===
    $small . '/tn_small-only.jpg', 'Historical image fallback was not checked');
poster_assert(facewall_poster_image_path('gone.jpg', false, 'small-only.jpg') === '',
    'Historical image fallback bypassed public consent');
poster_assert(facewall_poster_image_path('../bad.jpg', true, 'both.jpg') ===
    $full . '/tn_both.jpg', 'Valid historical image was not used after invalid current reference');
foreach (['../bad.jpg', 'image.svg', 'https://example.test/evil.php'] as $unsafe) {
    poster_assert(facewall_poster_image_path($unsafe, true) === '',
        'Unsafe/non-image filename was treated as a local poster photo');
}
// Verify the real poster entry point uses the validated preference helper.
$poster = file_get_contents(dirname(__DIR__) . '/poster.php');
poster_assert(strpos($poster, 'facewall_poster_image_path($row->image_URL, true, $row->historical_image_URL)') !== false,
    'Actual poster code does not use the safe full/small thumbnail fallback');

$db = new FacewallPosterAuditTestDatabase();
$db->rows = [
    (object) ['id' => 11, 'display_name' => 'Both Cached', 'image_URL' => 'both.jpg'],
    (object) ['id' => 12, 'display_name' => 'Only Small', 'image_URL' => 'small-only.jpg'],
    (object) ['id' => 13, 'display_name' => 'Only Full', 'image_URL' => 'full-only.png'],
    (object) ['id' => 14, 'display_name' => 'Original Only', 'image_URL' => 'original-only.jpg'],
    (object) ['id' => 15, 'display_name' => 'No Source', 'image_URL' => 'gone.jpg'],
    (object) ['id' => 16, 'display_name' => '<script>Person</script>', 'image_URL' => ''],
    (object) ['id' => 17, 'display_name' => 'Malformed', 'image_URL' => '../bad.jpg'],
    (object) ['id' => 18, 'display_name' => 'History Only', 'image_URL' => '', 'historical_image_URL' => 'both.jpg'],
    (object) ['id' => 19, 'display_name' => 'HTML Encoded Current', 'image_URL' => $escaped_url],
    (object) ['id' => 5327, 'display_name' => 'Ed Connolly', 'image_URL' =>
        'https://bec-cave.org.uk/wp/wp-content/uploads/bec-membership-photos/application-24-s4mc5z2y5fhuafx6dhu0eehq.jpg'],
    (object) ['id' => 5325, 'display_name' => 'Tim Lewis', 'image_URL' =>
        'https://bec-cave.org.uk/wp/wp-content/uploads/bec-membership-photos/application-18-1ocyo3mvapv3fyb70lfi6qle.jpg'],
];
poster_assert(isset(facewall_poster_photo_audit($db)['error']) && $db->queries === [],
    'Non-admin audit must not query or reveal member records');
$GLOBALS['poster_audit_admin'] = true;
$audit = facewall_poster_photo_audit($db);
$expected = [
    'members' => 11, 'complete' => 3, 'full_only' => 1,
    'small_only' => 1, 'needs_repair' => 3, 'application_source' => 2,
    'alternate_source' => 0, 'no_source' => 1,
    'no_reference' => 2, 'history_selected' => 1,
];
poster_assert($audit['counts'] === $expected,
    'Poster image audit failed to distinguish small-only, missing-original and no-reference states');
poster_assert(count($audit['issues']) === 9,
    'Audit must exclude complete caches from attention list');
$html = facewall_poster_photo_audit_html($audit);
poster_assert(strpos($html, 'Web thumbnail available') !== false &&
    strpos($html, 'run Photo Repair') !== false &&
    strpos($html, 'Historical FaceWall') !== false &&
    strpos($html, 'BEC application original exists') !== false,
    'Audit did not explain repairable and low-resolution photos');
poster_assert(strpos($html, '<script>Person</script>') === false &&
    strpos($html, '&lt;script&gt;Person&lt;/script&gt;') !== false,
    'Admin audit output did not escape member display names');
poster_assert(strpos(json_encode($audit['counts']), 'display_name') === false,
    'Aggregate status must not store member identifiers');
$GLOBALS['poster_audit_admin'] = false;
poster_assert(facewall_poster_photo_audit_html($audit) === '',
    'Non-admin user can see the private photo audit list');

foreach ([
    $small . '/tn_both.jpg',
    $full . '/tn_both.jpg',
    $small . '/tn_small-only.jpg',
    $full . '/tn_full-only.png',
    $base . '/original-only.jpg',
    $app_dir . '/application-24-s4mc5z2y5fhuafx6dhu0eehq.jpg', $app_dir . '/application-18-1ocyo3mvapv3fyb70lfi6qle.jpg',
] as $file) { @unlink($file); }
foreach ([$small, $full, $app_dir, $base . '/tn', $base, dirname($base),
    dirname(dirname($base)), dirname(dirname(dirname($base)))] as $folder) {
    @rmdir($folder);
}
echo "FaceWall poster image fallback and admin audit regressions passed.\n";
