<?php
/**
 * FaceWall WordPress admin navigation regression.
 * Simulates WordPress' implicit parent-submenu creation.
 * Run with: php tests/admin-menu-regression.php
 */
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/../');

$GLOBALS['test_actions'] = [];
$GLOBALS['test_parent_pages'] = [];
$GLOBALS['test_submenus'] = [];

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['test_actions'][$hook][] = [
        'callback' => $callback,
        'priority' => (int) $priority,
    ];
}
function plugins_url($path = '', $base = '') {
    return 'https://example.test/wp-content/plugins/' . ltrim($path, '/');
}
function add_menu_page($title, $menu_title, $capability, $slug, $callback, $icon = '') {
    $GLOBALS['test_parent_pages'][$slug] = compact('title', 'menu_title', 'capability', 'slug', 'callback', 'icon');
}
function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback) {
    // Like WordPress, register an implicit parent submenu the first time a
    // distinct child page is added to a top-level menu.
    if (!isset($GLOBALS['test_submenus'][$parent_slug])) {
        $GLOBALS['test_submenus'][$parent_slug] = [];
    }
    if ($parent_slug !== $menu_slug && count($GLOBALS['test_submenus'][$parent_slug]) === 0) {
        $GLOBALS['test_submenus'][$parent_slug][] = [
            'title' => $GLOBALS['test_parent_pages'][$parent_slug]['menu_title'] ?? '',
            'slug' => $parent_slug,
            'callback' => $GLOBALS['test_parent_pages'][$parent_slug]['callback'] ?? '',
        ];
    }
    $GLOBALS['test_submenus'][$parent_slug][] = [
        'title' => $menu_title,
        'slug' => $menu_slug,
        'callback' => $callback,
        'capability' => $capability,
    ];
}
function remove_submenu_page($menu_slug, $submenu_slug) {
    foreach ($GLOBALS['test_submenus'][$menu_slug] ?? [] as $index => $item) {
        if ($item['slug'] === $submenu_slug) {
            unset($GLOBALS['test_submenus'][$menu_slug][$index]);
            return $item;
        }
    }
    return false;
}
function assert_admin_menu($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}

require dirname(__DIR__) . '/facewall.php';

assert_admin_menu(isset($GLOBALS['test_actions']['admin_menu']), 'Admin menu callbacks were not registered');
usort($GLOBALS['test_actions']['admin_menu'], static function ($a, $b) {
    return $a['priority'] <=> $b['priority'];
});
foreach ($GLOBALS['test_actions']['admin_menu'] as $item) {
    call_user_func($item['callback']);
}

$parent = $GLOBALS['test_parent_pages']['facewall_plugin'] ?? null;
assert_admin_menu(is_array($parent), 'The FaceWall top-level navigation was removed');
assert_admin_menu($parent['capability'] === 'manage_options', 'FaceWall must remain administrator-only');
assert_admin_menu($parent['callback'] === 'facewall_maintenance_diagnostics_page',
    'Clicking the FaceWall heading must open Diagnostics, not the current member grid');
assert_admin_menu(function_exists($parent['callback']), 'The parent dashboard callback is unavailable');

$submenus = array_values($GLOBALS['test_submenus']['facewall_plugin'] ?? []);
$slugs = array_column($submenus, 'slug');
$titles = array_column($submenus, 'title');
assert_admin_menu($slugs === ['facewall_diagnostics', 'facewall_photo_repair', 'facewall_shortcodes'],
    'Only Diagnostics, Photo Repair and Shortcodes should remain: ' . implode(', ', $slugs));
assert_admin_menu($titles === ['Diagnostics', 'Photo Repair', 'Shortcodes'],
    'The visible submenu labels are incorrect: ' . implode(', ', $titles));

foreach ($submenus as $item) {
    assert_admin_menu(($item['capability'] ?? '') === 'manage_options',
        'FaceWall child navigation must be restricted to administrators');
    assert_admin_menu(function_exists($item['callback']), 'Submenu callback is missing: ' . $item['slug']);
}

assert_admin_menu(!in_array('facewall_plugin', $slugs, true),
    'WordPress automatically generated a redundant FaceWall submenu');
foreach (['poster', 'poster_RA1', 'poster_SRA1'] as $removed) {
    assert_admin_menu(!in_array($removed, $slugs, true), 'Obsolete poster menu is still visible: ' . $removed);
}
// Hiding poster navigation must not remove the existing administrator-only
// handler. This is a navigation change, not a PDF or privacy rewrite.
assert_admin_menu(isset($GLOBALS['test_actions']['admin_post_facewall_download_poster']),
    'Poster download endpoint was unexpectedly removed');
assert_admin_menu(function_exists('facewall_handle_poster_download'),
    'Existing protected poster download handler disappeared');

echo "FaceWall administrator menu regression checks passed.\n";
