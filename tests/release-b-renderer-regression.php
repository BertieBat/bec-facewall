<?php
/**
 * Shared component invariants and migration coverage for Release B.
 *
 * The detailed views also have 41 before/after SHA-256 HTML fixtures in
 * release-b-render-snapshots.php. This test guards architecture and escaping
 * responsibilities without needing WordPress/CiviCRM or a web browser.
 */
error_reporting(E_ALL);
define('ABSPATH', dirname(__DIR__) . '/');
require dirname(__DIR__) . '/includes/render.php';

function shared_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}

$rows = [
    facewall_render_grid_row_open(0),
    facewall_render_grid_row_open(7),
    facewall_render_grid_row_open(8, 'year'),
];
shared_assert($rows[0] === '<div class="items-row cols-4 row-0 row-fluid clearfix bec_members_row">',
    'Legacy photo row markup changed');
shared_assert($rows[1] === '<div class="items-row cols-4 row-7 row-fluid clearfix bec_members_row">',
    'Numbered member-grid markup changed');
shared_assert($rows[2] === '<div class="items-row cols-4 row-8 row-fluid clearfix bec_year_row">',
    'Officer-year heading row markup changed');

// Data is explicitly escaped by callers; the shared card must not change
// encoded text or accept new HTML from member values.
$name = htmlspecialchars('<Eve & Co>', ENT_QUOTES, 'UTF-8');
$img = '<img class="fw_image" src="/safe.jpg" alt="Test">';
$card = facewall_render_member_card(3, $name, $img, 'Ordinary (2020-2026)', ' - member');
shared_assert(strpos($card, '&lt;Eve &amp; Co&gt;') !== false &&
    strpos($card, '<Eve & Co>') === false,
    'Renderer corrupted pre-escaped member names');
shared_assert(strpos($card, 'class="item column-3"') !== false &&
    strpos($card, 'itemtype="http://schema.org/BlogPosting"') !== false,
    'Card grid column/schema markup changed');
shared_assert(strpos($card, 'tabindex="0"') !== false &&
    strpos($card, 'role="group"') !== false,
    'Hover card must remain focusable');
shared_assert(strpos($card, '</h5> - member</div>') !== false,
    'Historic member name or officer suffix moved outside the page header');
shared_assert(strpos($card, '<div class="fw_middle"><div class="text">Ordinary (2020-2026)</div></div>') !== false,
    'Membership hover text was dropped');
shared_assert(strpos($card, 'class="fw_middle"') < strpos($card, '<img'),
    'Hover overlay must precede the photograph for existing CSS');

$officer = facewall_render_member_card(2, 'BB Editor', $img, null,
    '       Member Example - from: October 3, 2026 ', true, false);
shared_assert(strpos($officer, '<a>' . $img . '</a>') !== false &&
    strpos($officer, 'fw_middle') === false &&
    strpos($officer, 'tabindex') === false,
    'No-overlay officer card or legacy anchor markup changed');
shared_assert(substr_count($officer, '<div') === substr_count($officer, '</div>'),
    'No-overlay card has unbalanced divs');

foreach (['current_list.php' => 5, 'officer_grid_year.php' => 5] as $path => $count) {
    $source = file_get_contents(dirname(__DIR__) . '/' . $path);
    shared_assert(substr_count($source, 'facewall_render_member_card(') === $count,
        $path . ' has unconverted or extra photo-card call sites');
    shared_assert(strpos($source, '$facewall_output .= \'<div class="span2">\';') === false &&
        strpos($source, '$joint_output .= \'<div class="span2">\';') === false,
        $path . ' still builds duplicate card wrappers');
}
echo "FaceWall Release B shared-card and row-component invariants passed.\n";
