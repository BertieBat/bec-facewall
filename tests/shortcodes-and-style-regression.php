<?php
/**
 * FaceWall shortcode reference and theme stylesheet isolation regressions.
 * No WordPress database or external website is required.
 */
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/../');

$GLOBALS['registered_actions'] = [];
$GLOBALS['admin_menus'] = [];
$GLOBALS['enqueued_styles'] = [];
$GLOBALS['facewall_test_is_admin'] = false;

function add_action($name, $function) { $GLOBALS['registered_actions'][$name][] = $function; }
function add_submenu_page(...$args) { $GLOBALS['admin_menus'][] = $args; }
function current_user_can($capability) { return $GLOBALS['facewall_test_is_admin']; }
function wp_die($message) { throw new RuntimeException('Permission denied: ' . $message); }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function plugins_url($file, $base = '') { return 'https://test.example/wp-content/plugins/facewall/' . ltrim($file, '/'); }
function wp_enqueue_style($handle, $url, $dependencies, $version) {
    $GLOBALS['enqueued_styles'][] = compact('handle', 'url', 'dependencies', 'version');
}
function facewall_action($atts = [], $content = null, $tag = '') {
    return $GLOBALS['facewall_test_rendered'] ?? '<p>FaceWall test view</p>';
}
function assert_shortcodes($condition, $description) {
    if (!$condition) { throw new RuntimeException($description); }
}

require dirname(__DIR__) . '/includes/shortcodes.php';

$scopes = array_column(facewall_shortcode_catalog(), 'scope');
$source = file_get_contents(dirname(__DIR__) . '/facewall.php');
preg_match_all('/\bcase "([^"]+)":/', $source, $matches);
$live = array_values(array_diff($matches[1], ['membership_fees']));
assert_shortcodes(count($scopes) === count(array_unique($scopes)), 'Shortcode catalog contains duplicates');
assert_shortcodes($scopes === $live, 'Shortcode catalog is out of sync with live scope switch');

$normal = facewall_render_shortcode([]);
assert_shortcodes(strpos($normal, 'facewall-display facewall-scope-current') !== false,
    'Default FaceWall shortcode is missing its CSS namespace');
$joint = facewall_render_shortcode(['scope' => 'joint']);
assert_shortcodes(strpos($joint, 'facewall-scope-joint') !== false,
    'Joint member view missing scoped styling class');
assert_shortcodes(facewall_render_shortcode(['scope' => 'not-a-view']) === '',
    'Unknown scope must not display an invalid wrapper');

// When third-party CSS optimisation fails to load the external stylesheet,
// the actual shortcode must still include a LOCAL (not global) hover style.
// It is needed because membership summaries appear before the image in HTML.
$GLOBALS['facewall_test_rendered'] =
    '<div class="items-row bec_members_row"><div class="pull-left item-image" tabindex="0">'
    . '<div class="fw_middle"><div class="text">Ordinary (2019–2027)</div></div>'
    . '<img class="fw_image" src="/safe-image.jpg"></div></div>';
$overlay = facewall_render_shortcode(['scope' => 'current']);
assert_shortcodes(strpos($overlay, '<style class="bec-facewall-overlay-fallback">') !== false,
    'The shortcode did not include a fallback for the missing external stylesheet');
assert_shortcodes(strpos($overlay, '.facewall-display .fw_middle{position:absolute!important;') !== false,
    'The fallback must overlay the photograph instead of moving text above it');
assert_shortcodes(strpos($overlay, 'opacity:0!important;') !== false &&
    strpos($overlay, 'visibility:hidden!important;') !== false,
    'The membership summary must be hidden until hover or focus');
assert_shortcodes(strpos($overlay, '.facewall-display .item-image:hover>.fw_middle') !== false &&
    strpos($overlay, '.facewall-display .item-image:focus-within>.fw_middle') !== false &&
    strpos($overlay, 'opacity:1!important;visibility:visible!important;') !== false,
    'Hover and keyboard/tap focus must reveal the overlay');
assert_shortcodes(strpos($overlay, 'navbar') === false &&
    strpos($overlay, 'nav-collapse') === false &&
    strpos($overlay, 'body:') === false,
    'Fallback may not change the website theme navigation');
unset($GLOBALS['facewall_test_rendered']);


assert_shortcodes(isset($GLOBALS['registered_actions']['wp_enqueue_scripts']),
    'FaceWall stylesheet must enqueue before the page HTML body');
facewall_enqueue_public_styles();
assert_shortcodes(count($GLOBALS['enqueued_styles']) === 2, 'Expected two FaceWall-owned styles');
$sheet = $GLOBALS['enqueued_styles'][0];
assert_shortcodes($sheet['handle'] === 'bec-facewall' &&
    strpos($sheet['url'], '/css/facewall.css') !== false,
    'FaceWall grid CSS must be enqueued as the first plugin stylesheet');
$menu_style = $GLOBALS['enqueued_styles'][1];
assert_shortcodes($menu_style['handle'] === 'bec-facewall-menu-contrast' &&
    strpos($menu_style['url'], '/css/facewall-menu-contrast.css') !== false &&
    $menu_style['dependencies'] === ['bec-facewall'],
    'Shape5 menu contrast CSS must be enqueued after the scoped grid CSS');

facewall_shortcodes_menu();
assert_shortcodes(count($GLOBALS['admin_menus']) === 1 &&
    $GLOBALS['admin_menus'][0][0] === 'facewall_plugin' &&
    $GLOBALS['admin_menus'][0][2] === 'Shortcodes' &&
    $GLOBALS['admin_menus'][0][3] === 'manage_options',
    'Shortcode reference must be a FaceWall administrator submenu');
try {
    facewall_shortcodes_admin_page();
    throw new RuntimeException('Non-admin was allowed into shortcode reference');
} catch (RuntimeException $e) {
    assert_shortcodes(strpos($e->getMessage(), 'Permission denied:') === 0,
        'Shortcode reference permission check is missing');
}
$GLOBALS['facewall_test_is_admin'] = true;
ob_start();
facewall_shortcodes_admin_page();
$html = ob_get_clean();
assert_shortcodes(substr_count($html, '<tr>') === count($scopes) + 1,
    'Every supported scope needs exactly one admin table row');
assert_shortcodes(strpos($html, '[facewall]') !== false &&
    strpos($html, 'scope=&quot;officer_grid_year&quot;') !== false &&
    strpos($html, 'year=&quot;current&quot;') !== false,
    'Shortcode reference omitted usage examples');
assert_shortcodes(strpos($html, 'Specific numeric years are not supported') !== false,
    'Officer-grid year parameter behaviour is not explained');

$css = file_get_contents(dirname(__DIR__) . '/css/facewall.css');
$css = preg_replace('~/\*.*?\*/~s', '', $css);
assert_shortcodes(strpos($css, '@media') !== false, 'Responsive grid styling is missing');
assert_shortcodes(!preg_match('/@media\\s*\\(hover:\\s*none\\)/', $css),
    'Touch-only rule must not force membership details visible above the image');
assert_shortcodes(strpos($css, 'visibility: hidden;') !== false &&
    strpos($css, 'visibility: visible;') !== false &&
    strpos($css, '.item-image:focus-within') !== false,
    'Base FaceWall CSS must hide history normally and reveal it on focus/hover');

// Every top-level selector and every selector nested in @media must begin
// within FaceWall's own namespace. Never target the website theme.
preg_match_all('/(?:^|})\s*([^{}]+)\{/m', $css, $rules);
foreach ($rules[1] as $selector_group) {
    $selector_group = trim($selector_group);
    if (strpos($selector_group, '@media') === 0) {
        continue;
    }
    foreach (explode(',', $selector_group) as $selector) {
        assert_shortcodes(strpos(trim($selector), '.facewall-display') === 0,
            'Unscoped CSS selector could override the website menu: ' . trim($selector));
    }
}
// The white-on-white sticky dropdown is part of the Shape5/Vertex theme,
// not the FaceWall photo grid. These rules must apply ONLY to pages with
// FaceWall markup, including when the menu is floated elsewhere in <body>.
$menu_css = file_get_contents(dirname(__DIR__) . '/css/facewall-menu-contrast.css');
assert_shortcodes(strpos($menu_css, 'body:has(.facewall-display)') !== false,
    'Menu contrast correction is not limited to FaceWall pages');
assert_shortcodes(strpos($menu_css, '#s5_nav') !== false &&
    strpos($menu_css, '#subMenusContainer') !== false &&
    strpos($menu_css, '.s5_sub_a_span') !== false,
    'CSS does not target both standard and floating Shape5 submenu labels');
assert_shortcodes(strpos($menu_css, 'color: #23343c !important;') !== false &&
    strpos($menu_css, 'color: #ffffff !important;') !== false,
    'Menu needs a dark normal label and light text on its cyan highlight');
assert_shortcodes(strpos($menu_css, ':focus-within') !== false,
    'Keyboard navigation must retain visible highlighted menu text');
$menu_css_uncommented = preg_replace('~/\\*.*?\\*/~s', '', $menu_css);
preg_match_all('/(?:^|})\\s*([^{}]+)\\{/m', $menu_css_uncommented, $menu_rules);
foreach ($menu_rules[1] as $selectors) {
    $selectors = trim($selectors);
    if (strpos($selectors, '@supports') === 0) {
        continue;
    }
    foreach (explode(',', $selectors) as $selector) {
        assert_shortcodes(strpos(trim($selector), 'body:has(.facewall-display)') === 0,
            'Menu CSS leaks into other pages or global theme controls: ' . trim($selector));
    }
}

foreach (['current_list.php', 'officer_grid_year.php'] as $path) {
    $php = file_get_contents(dirname(__DIR__) . '/' . $path);
    assert_shortcodes(!preg_match('/<link rel=["\x27]stylesheet["\x27]/', $php),
        'Shortcode output is still injecting a stylesheet: ' . $path);
}
echo "FaceWall shortcode documentation and scoped stylesheet checks passed.\n";
