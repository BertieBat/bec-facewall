<?php
/*
   Plugin Name: facewall
   Plugin URI: https://github.com/speleo 
   description: Displays a custom grid facewall of active members from a CiviCRM database on Wordpress
   Version: 1.1.9
   Author: Henry Bennett
   Author URI: https://github.com/speleo
   
*/

defined( 'ABSPATH' ) || exit;

// Add menu
function facewall_menu() {
    add_menu_page(
        'FaceWall', 'FaceWall', 'manage_options', 'facewall_plugin',
        'admin_current_list', plugins_url('/facewall/img/facewall.png')
    );
    // Current members are the parent page; only active administrative
    // destinations are registered here. Diagnostics, Photo Repair and
    // Shortcodes register their own submenus in their respective modules.
    add_submenu_page('facewall_plugin', 'A1 Poster', 'A1 Poster',
        'manage_options', 'poster', 'admin_poster');
    add_submenu_page('facewall_plugin', 'RA1 Poster', 'RA1 Poster',
        'manage_options', 'poster_RA1', 'admin_poster_RA1');
    add_submenu_page('facewall_plugin', 'SRA1 Poster', 'SRA1 Poster',
        'manage_options', 'poster_SRA1', 'admin_poster_SRA1');
}

add_action("admin_menu", "facewall_menu");

require_once __DIR__ . '/includes/maintenance.php';
require_once __DIR__ . '/includes/diagnostics.php';
require_once __DIR__ . '/includes/photo-repair.php';
require_once __DIR__ . '/includes/shortcodes.php';

/** WordPress runs the transactional pipeline from the registered cron event. */
function modify_database_table_cron_job() {
    return facewall_maintenance_run();
}
add_action( 'facewall_cron', 'modify_database_table_cron_job' );

function shortcode_init(){
	add_shortcode('facewall','facewall_render_shortcode');
}
add_action('init', 'shortcode_init');


/**
 * WordPress shortcode entry point. Routing and public documentation use
 * the same restricted registry (includes/shortcodes.php).
 */
function facewall_action($atts = [], $content = null, $tag = '') {
    return facewall_dispatch_shortcode($atts, $tag);
}

require_once __DIR__ . '/current_list.php';
//include "officer_list.php";
require_once __DIR__ . '/officer_grid_year.php';
require_once __DIR__ . '/membership_list.php';
require_once __DIR__ . '/poster.php';
require_once __DIR__ . '/membership_fees.php';


//-----------------------------------------------------------------------------
// to get the output to the front end we use "return" to substitute the shortcode

//-----------------------------------------------------------------------------
// to get the output from the admin backend we need to echo

function admin_current_list(){
	$scope = 'current';
	$fw_title = 'Current members of the BEC';
	$fw_mem_status = '< 4';
	$fw_mem_type = '<9';
	echo display_facewall_normal($fw_title, $fw_mem_status, $fw_mem_type);
}

/**
 * Display an administrator-only link. Stream the PDF from admin-post.php before
 * any wp-admin HTML headers are sent, with a nonce to prevent CSRF.
 */
function facewall_admin_poster_page($sheet_size) {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to download this poster.');
    }
    $download_url = wp_nonce_url(
        add_query_arg([
            'action' => 'facewall_download_poster',
            'size' => $sheet_size,
        ], admin_url('admin-post.php')),
        'facewall_download_poster'
    );
    echo '<div class="wrap"><h1>FaceWall ' . esc_html($sheet_size)
        . ' poster</h1><p>Downloads include only consenting FaceWall members.</p>'
        . '<p><a class="button button-primary" href="' . esc_url($download_url)
        . '">Download PDF poster</a></p></div>';
}

function admin_poster() {
    facewall_admin_poster_page('A1');
}
function admin_poster_RA1() {
    facewall_admin_poster_page('RA1');
}
function admin_poster_SRA1() {
    facewall_admin_poster_page('SRA1');
}

function facewall_handle_poster_download() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to download this poster.');
    }
    check_admin_referer('facewall_download_poster');
    $size = isset($_GET['size']) && is_string($_GET['size'])
        ? strtoupper(sanitize_key(wp_unslash($_GET['size']))) : '';
    if (!in_array($size, ['A1', 'RA1', 'SRA1'], true)) {
        wp_die('Invalid FaceWall poster size.');
    }
    create_poster('', $size);
    exit;
}
add_action('admin_post_facewall_download_poster', 'facewall_handle_poster_download');

