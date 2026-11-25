<?php
/**
 * Release D: exact shortcode routing compatibility and dead-code guardrails.
 *
 * This is a pure registry/dispatch test: the actual member-grid markup,
 * queries and database/rollback behaviours remain covered by earlier tests.
 */
error_reporting(E_ALL);
define('ABSPATH', dirname(__DIR__) . '/');

$GLOBALS['release_d_actions'] = [];
$GLOBALS['release_d_calls'] = [];
function add_action($name, $handler) { $GLOBALS['release_d_actions'][] = [$name, $handler]; }
function shortcode_atts($defaults, $atts, $shortcode = '') {
    return array_merge($defaults, array_intersect_key((array) $atts, $defaults));
}
function release_d_record($name, $args) {
    $GLOBALS['release_d_calls'][] = [$name, $args];
    return $name . '|';
}
function display_facewall_normal_new(...$args) { return release_d_record(__FUNCTION__, $args); }
function display_facewall_probationary(...$args) { return release_d_record(__FUNCTION__, $args); }
function display_facewall_joint(...$args) { return release_d_record(__FUNCTION__, $args); }
function display_facewall_joint_invalid(...$args) { return release_d_record(__FUNCTION__, $args); }
function display_facewall_under_30(...$args) { return release_d_record(__FUNCTION__, $args); }
function display_officer_list(...$args) { return release_d_record(__FUNCTION__, $args); }
function display_officer_grid_year(...$args) { return release_d_record(__FUNCTION__, $args); }
function display_membership_list(...$args) { return release_d_record(__FUNCTION__, $args); }
function display_all_membership(...$args) { return release_d_record(__FUNCTION__, $args); }
function display_prorata_fees(...$args) { return release_d_record(__FUNCTION__, $args); }
function release_d_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
require dirname(__DIR__) . '/includes/shortcodes.php';

$expected = [
    'current' => [['display_facewall_normal_new', ['Current members of the BEC', '< 4', '<8']]],
    'probationary' => [['display_facewall_probationary', ['Probationary members', '< 4', '=2']]],
    'grace' => [['display_facewall_normal_new', ['Grace members', '= 3', '<9']]],
    'lybnty' => [['display_facewall_normal_new', ['Last Year But not this Year', '= 4', '<9']]],
    'joint' => [
        ['display_facewall_joint', ['Joint members in the BEC', '< 4', 'IN (3,5,7)']],
        ['display_facewall_joint_invalid', ['Other Joint members in the BEC', '< 4', 'IN (3,5,7)']],
    ],
    'life' => [['display_facewall_normal_new', ['Life members of the BEC', '< 4', 'IN (4,5,6)']]],
    'student' => [
        ['display_facewall_normal_new', ['Members claiming student or under 30 discount', '< 4', '= 15']],
        ['display_facewall_under_30', ['Members under 30', '< 4', '= 15']],
    ],
    'officer' => [['display_officer_list', [0, 0, 0]]],
    'officer_grid_year' => [['display_officer_grid_year', ['all']]],
    'historical_membership_list' => [['display_membership_list', ['Historical membership list', '', '']]],
    'historical_membership_grid' => [['display_all_membership', []]],
    'prorata_fees' => [['display_prorata_fees', []]],
];

$catalog = facewall_shortcode_catalog();
$scopes = array_column($catalog, 'scope');
release_d_assert($scopes === array_keys($expected), 'Registry drifted from the twelve supported scopes');
release_d_assert(count($scopes) === count(array_unique($scopes)), 'Registry contains duplicate scopes');
foreach ($catalog as $definition) {
    release_d_assert(isset($definition['title'], $definition['description'], $definition['note'],
        $definition['handler'], $definition['args']), 'Registry entry is missing required fields');
    release_d_assert(is_callable($definition['handler']) && is_array($definition['args']),
        'Registry contains an invalid renderer: ' . $definition['scope']);
    release_d_assert(strpos($definition['scope'], 'membership_fees') === false,
        'Retired membership_fees scope must not become active');
}
foreach ($expected as $scope => $calls) {
    $GLOBALS['release_d_calls'] = [];
    $output = facewall_dispatch_shortcode(['scope' => $scope]);
    release_d_assert($GLOBALS['release_d_calls'] === $calls,
        'Release D changed view handler/arguments for scope: ' . $scope);
    release_d_assert($output === implode('', array_map(static function ($entry) {
        return $entry[0] . '|';
    }, $calls)), 'Release D changed the order or output of a composite scope');
}
$GLOBALS['release_d_calls'] = [];
release_d_assert(facewall_dispatch_shortcode([]) === 'display_facewall_normal_new|' &&
    $GLOBALS['release_d_calls'] === $expected['current'],
    '[facewall] must still show current members by default');
$GLOBALS['release_d_calls'] = [];
release_d_assert(facewall_dispatch_shortcode(['SCOPE' => 'officer_grid_year', 'YEAR' => 'current'])
    === 'display_officer_grid_year|' &&
    $GLOBALS['release_d_calls'] === [['display_officer_grid_year', ['current']]],
    'The current officer committee year attribute must be passed through unchanged');
$GLOBALS['release_d_calls'] = [];
foreach ([
    ['scope' => 'membership_fees'],
    ['scope' => 'not-a-view'],
    ['scope' => ['joint']],
] as $invalid) {
    release_d_assert(facewall_dispatch_shortcode($invalid) === '' &&
        $GLOBALS['release_d_calls'] === [], 'An unknown shortcode invoked a handler');
}
$main = file_get_contents(dirname(__DIR__) . '/bec-facewall.php');
release_d_assert(strpos($main, 'return facewall_dispatch_shortcode($atts, $tag);') !== false,
    'The WordPress shortcode entry point is not routed through the registry');
foreach ([
    'admin_grace_list', 'admin_lybnty_list', 'admin_joint_list',
    'admin_life_list', 'admin_student_list', 'admin_officer_list',
    'admin_officer_grid_year', 'admin_historical_membership_list',
] as $retired) {
    release_d_assert(!preg_match('/function\s+' . preg_quote($retired, '/') . '\s*\(/', $main),
        'Inactive administration callback still present: ' . $retired);
}
$grids = file_get_contents(dirname(__DIR__) . '/officer_grid_year.php');
release_d_assert(strpos($grids, 'function facewall_pad_grid(') === false,
    'Unused old HTML padding code is still active');
release_d_assert(strpos($main, 'facewall_handle_poster_download') !== false &&
    strpos($main, 'check_admin_referer') !== false,
    'Poster access control must remain registered');
echo "FaceWall Release D routing and legacy cleanup regressions passed.\n";
