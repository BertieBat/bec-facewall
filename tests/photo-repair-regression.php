<?php
/**
 * Regression tests for member-photo recovery without a live CiviCRM database.
 */
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/../');
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/facewall-photo-repair-test-' . getmypid());

$GLOBALS['test_options'] = [];
$GLOBALS['test_actions'] = [];
function add_action($hook, $callback) { $GLOBALS['test_actions'][$hook][] = $callback; }
function get_current_blog_id() { return 1; }
function get_option($key, $default = false) { return $GLOBALS['test_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['test_options'][$key] = $value; return true; }
function wp_mkdir_p($path) { return mkdir($path, 0700, true) || is_dir($path); }
function wp_schedule_single_event($time, $hook) { $GLOBALS['test_scheduled'][] = $hook; return true; }
function current_user_can($cap) { return false; }
function wp_die($message) { throw new RuntimeException('Refused: ' . $message); }
function test_photo($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }

class FacewallPhotoTestDB {
    public $queries = [];
    public function prepare($sql, ...$args) {
        foreach ($args as $value) {
            if (strpos($sql, '%d') !== false) {
                $sql = preg_replace('/%d/', (string) (int) $value, $sql, 1);
            } elseif (strpos($sql, '%s') !== false) {
                $sql = preg_replace('/%s/', "'" . addslashes((string) $value) . "'", $sql, 1);
            } else {
                throw new RuntimeException('Unexpected SQL placeholder');
            }
        }
        return $sql;
    }
    public function get_var($sql) {
        $this->queries[] = $sql;
        if (strpos($sql, 'GET_LOCK(') !== false || strpos($sql, 'RELEASE_LOCK(') !== false) {
            return 1;
        }
        throw new RuntimeException('Unexpected SQL read');
    }
    public function get_results($sql) {
        $this->queries[] = $sql;
        test_photo(strpos($sql, 'consent.facewall_public_84 = 1') !== false,
            'Photo repair query included people without consent');
        test_photo(strpos($sql, 'LIMIT 20') !== false &&
            strpos($sql, 'c.id > 0') !== false, 'Photo repair must be bounded and cursor-based');
        return [
            (object) ['id' => 1, 'image_URL' => 'cached.jpg'],
            (object) ['id' => 2, 'image_URL' => 'camera photo (1).jpg'],
            (object) ['id' => 3, 'image_URL' => '../unsafe.jpg'],
            (object) ['id' => 4, 'image_URL' => 'missing.jpg'],
            (object) ['id' => 5, 'image_URL' => 'script.svg'],
        ];
    }
    public function query($sql) { throw new RuntimeException('Photo repair must never write to member DB tables'); }
}

$GLOBALS['wpdb'] = new FacewallPhotoTestDB();
require dirname(__DIR__) . '/includes/security.php';
require dirname(__DIR__) . '/current_list.php';
require dirname(__DIR__) . '/includes/photo-repair.php';

test_photo(facewall_image_filename('camera%20photo%20%281%29.jpg') === 'camera photo (1).jpg',
    'Legacy photo names with spaces or parentheses should be accepted');
test_photo(facewall_image_filename('ñame+.jpeg') === 'ñame+.jpeg',
    'Unicode camera filenames should be permitted');
foreach (['../etc/passwd.jpg', 'dir/%2e%2e/evil.png', 'image.svg', 'data:text/html,evil.jpg'] as $unsafe) {
    test_photo(facewall_image_filename($unsafe) === '',
        'Unsafe photo filename was allowed: ' . $unsafe);
}

$base = WP_CONTENT_DIR . '/uploads/civicrm/custom';
$small = $base . '/tn/174';
$full = $base . '/tn/full_scale';
test_photo(mkdir($small, 0700, true), 'Could not make test thumbnail directory');
test_photo(mkdir($full, 0700, true), 'Could not make test poster directory');
file_put_contents($small . '/tn_cached.jpg', 'existing thumbnail; original deliberately absent');

test_photo(function_exists('imagecreatetruecolor') && function_exists('imagejpeg'),
    'CI PHP must have GD enabled for photo generation regression');
$image = imagecreatetruecolor(300, 280);
imagejpeg($image, $base . '/camera photo (1).jpg');
imagedestroy($image);

test_photo(strpos(facewall_photo_html('old-photo.jpg', 'Missing', true), 'unknown.jpg') !== false,
    'Public renderer should not generate missing thumbnails automatically');

facewall_photo_repair_batch();
$status = facewall_photo_repair_status();
test_photo(($status['state'] ?? '') === 'completed', 'Small repair batch did not complete');
foreach (['checked' => 5, 'existing' => 1, 'created' => 1, 'invalid' => 2, 'failed' => 1] as $field => $count) {
    test_photo(($status[$field] ?? null) === $count,
        'Photo repair ' . $field . ' expected ' . $count . ', got ' . ($status[$field] ?? 'unset'));
}
test_photo(is_file($small . '/tn_camera photo (1).jpg'), 'Consented missing thumbnail was not recreated');
test_photo(is_file($full . '/tn_camera photo (1).jpg'), 'Full-scale cached photo was not recreated');
test_photo(is_file($small . '/tn_cached.jpg'), 'Existing thumbnail was overwritten or deleted');
test_photo(strpos(json_encode($status), 'camera photo') === false &&
    strpos(json_encode($status), 'id') === false,
    'Public diagnostic status exposed photo names or identifiers');

$recovered = facewall_photo_html('tn_outdated.jpg', '"Example"', true, 'camera photo (1).jpg');
test_photo(strpos($recovered, 'tn_camera%20photo%20%281%29.jpg') !== false &&
    strpos($recovered, '&quot;Example&quot;') !== false,
    'Stale summary photo did not recover through current CiviCRM reference');
$opted_out = facewall_photo_html('tn_outdated.jpg', 'Private', false, 'camera photo (1).jpg');
test_photo(strpos($opted_out, 'unknown.jpg') !== false &&
    strpos($opted_out, 'tn_camera%20photo') === false,
    'Fallback bypassed FaceWall consent');

test_photo(isset($GLOBALS['test_actions']['facewall_repair_photos']) &&
    isset($GLOBALS['test_actions']['admin_post_facewall_photo_repair_queue']),
    'Repair worker and admin queue action must be registered');
try {
    facewall_photo_repair_page();
    throw new RuntimeException('Non-admin was granted photo repair access');
} catch (RuntimeException $exception) {
    test_photo(strpos($exception->getMessage(), 'Refused:') === 0,
        'Photo repair page failed to enforce admin permission');
}

foreach ([$small . '/tn_cached.jpg', $small . '/tn_camera photo (1).jpg',
    $full . '/tn_camera photo (1).jpg', $base . '/camera photo (1).jpg'] as $path) {
    @unlink($path);
}
foreach ([$small, $full, $base . '/tn', $base, dirname($base),
    dirname(dirname($base)), dirname(dirname(dirname($base)))] as $directory) {
    @rmdir($directory);
}
echo "FaceWall photo recovery and safe batch repair regressions passed.\n";
