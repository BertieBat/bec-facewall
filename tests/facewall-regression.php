<?php
// Simulate the WordPress bootstrap guard without loading a WordPress site.
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/../'); }
/**
 * Small, dependency-free regression check for the FaceWall cron PHP fatal
 * and the historical membership duplicate-key insert.
 *
 * Run with: php tests/facewall-regression.php
 */
error_reporting(E_ALL);

// The real scheduled task can include the helper after WordPress loaded it.
require dirname(__DIR__) . '/membership_list.php';
require dirname(__DIR__) . '/membership_list.php';
if (!function_exists('display_membership_list')) {
    throw new RuntimeException('FaceWall membership functions were not registered.');
}

class FacewallTestDatabase
{
    public $queries = [];
    public $prepared = [];
    public $last_error = '';

    public function prepare($sql, ...$arguments)
    {
        $this->prepared[] = ['sql' => $sql, 'arguments' => $arguments];
        return $sql; // Test only; no SQL is executed.
    }

    public function query($sql)
    {
        $this->queries[] = $sql;
        return 1;
    }
}

global $wpdb;
$wpdb = new FacewallTestDatabase();
$membership = (object) [
    'cv_start_date' => '2025-01-01',
    'cv_join_date' => '0000-00-00',
    'cv_end_date' => '2025-09-30',
    'cv_contact_id' => 42,
    'cv_display_name' => "O'Connor",
    'cv_membership_type_id' => 1,
    'cv_is_deceased' => 0,
    'cv_deceased_date' => null,
];

ob_start();
$success = get_membership_data([$membership, $membership]);
$output = ob_get_clean();
if ($success !== true || $output !== '') {
    throw new RuntimeException('Historical membership processing failed or unexpectedly echoed output.');
}
if (count($wpdb->queries) !== 2) {
    throw new RuntimeException('Expected one attempted insert per input membership row.');
}
foreach ($wpdb->prepared as $prepared) {
    if (strpos($prepared['sql'], 'ON DUPLICATE KEY UPDATE') === false) {
        throw new RuntimeException('Membership insert is not collision-safe.');
    }
    if (($prepared['arguments'][3] ?? '') !== '2025-01-01') {
        throw new RuntimeException('Invalid legacy join date was not replaced with the valid start date.');
    }
}
echo "FaceWall regression checks passed.\n";
