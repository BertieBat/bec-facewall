<?php
/**
 * Public shortcode wrapper and administrator reference.
 *
 * This catalog describes every implemented scope in facewall_action().
 * The disabled membership_fees branch is not a working shortcode.
 */
defined('ABSPATH') || exit;

function facewall_shortcode_catalog() {
    return [
        ['scope' => 'current', 'title' => 'Current members', 'description' => 'Photographic grid of current BEC members.', 'note' => 'This is also the default when scope is omitted.'],
        ['scope' => 'probationary', 'title' => 'Probationary members', 'description' => 'Photo grid of probationary members.', 'note' => ''],
        ['scope' => 'grace', 'title' => 'Members in grace', 'description' => 'Photo grid of memberships in the grace status.', 'note' => ''],
        ['scope' => 'lybnty', 'title' => 'Last Year But Not This Year', 'description' => 'Photo grid of previous-year members not renewed this year.', 'note' => 'Uses the membership status configured in the existing FaceWall query.'],
        ['scope' => 'joint', 'title' => 'Joint members', 'description' => 'Joint membership pairs, including the separate unusual-pairs section.', 'note' => 'Both members must have FaceWall public visibility enabled.'],
        ['scope' => 'life', 'title' => 'Life members', 'description' => 'Photo grid of Life, Joint Life and Honorary Life members.', 'note' => ''],
        ['scope' => 'student', 'title' => 'Student / under 30', 'description' => 'Displays student-discount members and a second grid of members under 30.', 'note' => ''],
        ['scope' => 'officer', 'title' => 'Officers by year', 'description' => 'Text list of historic BEC officers grouped by club year.', 'note' => ''],
        ['scope' => 'officer_grid_year', 'title' => 'Officer photo grid', 'description' => 'Photo grid of BEC officers grouped by club year.', 'note' => 'Use year="current" for the current committee only. Omitting year, or using year="all", shows current and earlier years. Specific numeric years are not supported.'],
        ['scope' => 'historical_membership_list', 'title' => 'Historical membership list', 'description' => 'Text list of historic memberships grouped by club year.', 'note' => ''],
        ['scope' => 'historical_membership_grid', 'title' => 'Historical membership photo grid', 'description' => 'Photographic grid of historical BEC members.', 'note' => ''],
        ['scope' => 'prorata_fees', 'title' => 'Prorated membership fees', 'description' => 'Table of prorated subscription amounts by membership type and month.', 'note' => 'Legacy helper; values are retrieved from CiviCRM. Verify fees before publishing.'],
    ];
}

/** Keep FaceWall markup isolated from the website's own navigation and grid. */
function facewall_render_shortcode($atts = [], $content = null, $tag = '') {
    $rendered = facewall_action($atts, $content, $tag);
    if (!is_string($rendered) || trim($rendered) === '') {
        return '';
    }
    $options = is_array($atts) ? array_change_key_case($atts, CASE_LOWER) : [];
    $scope = (string) ($options['scope'] ?? 'current');
    $allowed = array_column(facewall_shortcode_catalog(), 'scope');
    if (!in_array($scope, $allowed, true)) {
        return '';
    }
    return '<div class="facewall-display facewall-scope-' . esc_attr($scope) . '">'
        . $rendered . '</div>';
}

/**
 * A very small, completely scoped stylesheet is intentionally enqueued
 * before the page header. Legacy global Bootstrap/custom.css is NOT loaded.
 * Do not enqueue it from within shortcode output, after wp_head().
 */
function facewall_enqueue_public_styles() {
    $file = dirname(__DIR__) . '/css/facewall.css';
    wp_enqueue_style(
        'bec-facewall',
        plugins_url('css/facewall.css', dirname(__DIR__) . '/facewall.php'),
        [],
        is_file($file) ? (string) filemtime($file) : '1.1.2'
    );

    // Target only the Shape5 dropdown labels on pages where FaceWall exists.
    // This separate style loads after FaceWall's own scoped grid stylesheet.
    $menu_file = dirname(__DIR__) . '/css/facewall-menu-contrast.css';
    wp_enqueue_style(
        'bec-facewall-menu-contrast',
        plugins_url('css/facewall-menu-contrast.css', dirname(__DIR__) . '/facewall.php'),
        ['bec-facewall'],
        is_file($menu_file) ? (string) filemtime($menu_file) : '1.1.2'
    );
}
add_action('wp_enqueue_scripts', 'facewall_enqueue_public_styles');

function facewall_shortcodes_menu() {
    add_submenu_page(
        'facewall_plugin',
        'FaceWall Shortcodes',
        'Shortcodes',
        'manage_options',
        'facewall_shortcodes',
        'facewall_shortcodes_admin_page'
    );
}
add_action('admin_menu', 'facewall_shortcodes_menu');

function facewall_shortcodes_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to view FaceWall shortcodes.');
    }
    echo '<div class="wrap"><h1>FaceWall Shortcodes</h1>';
    echo '<p>Insert these shortcodes into a WordPress page or post using a Shortcode block. '
        . 'Only members with the FaceWall Public setting enabled are included in public member displays.</p>';
    echo '<p><strong>Default:</strong> <code>' . esc_html('[facewall]') . '</code> '
        . 'shows current members. The <code>scope</code> attribute selects a different view.</p>';
    echo '<table class="widefat striped"><thead><tr>'
        . '<th scope="col">Display</th><th scope="col">Shortcode</th>'
        . '<th scope="col">What it shows</th><th scope="col">Notes</th>'
        . '</tr></thead><tbody>';
    foreach (facewall_shortcode_catalog() as $item) {
        $example = '[facewall scope="' . $item['scope'] . '"]';
        echo '<tr><th scope="row">' . esc_html($item['title']) . '</th>';
        echo '<td><code style="white-space:nowrap;user-select:all;">' . esc_html($example) . '</code>';
        if ($item['scope'] === 'officer_grid_year') {
            echo '<p><code style="white-space:nowrap;user-select:all;">'
                . esc_html('[facewall scope="officer_grid_year" year="current"]')
                . '</code></p>';
        }
        echo '</td><td>' . esc_html($item['description']) . '</td>';
        echo '<td>' . esc_html($item['note']) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<p><strong>Note:</strong> The legacy <code>membership_fees</code> scope is inactive '
        . 'in this plugin; its fee display is maintained separately in WPCode and is therefore '
        . 'not listed as an available shortcode.</p>';
    echo '</div>';
}
