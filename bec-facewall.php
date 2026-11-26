<?php
/*
   Plugin Name: BEC FaceWall
   Plugin URI: https://github.com/BertieBat/bec-facewall 
   description: Displays a custom grid facewall of active members from a CiviCRM database on Wordpress
   Version: 1.1.16
   Author: Henry Bennett
   Author URI: https://github.com/speleo
   
*/

defined( 'ABSPATH' ) || exit;

/**
 * Keep FaceWall's top-level admin item, but open Diagnostics rather than
 * generating an expensive member grid in wp-admin.
 *
 * Diagnostics, Photo Repair and Shortcodes register their own submenus.
 * Poster generation remains admin-only and unchanged; the three obsolete
 * poster navigation links are intentionally not registered.
 */
function facewall_menu() {
    add_menu_page(
        'BEC FaceWall', 'BEC FaceWall', 'manage_options', 'facewall_plugin',
        'facewall_maintenance_diagnostics_page', plugins_url('img/facewall.png', __FILE__)
    );
}
add_action('admin_menu', 'facewall_menu');

/**
 * WordPress inserts the parent page as an extra "FaceWall" submenu when the
 * first child is registered. Remove only that duplicate after all modules
 * have registered; retain Diagnostics, Photo Repair and Shortcodes.
 * The parent slug is kept for existing bookmarks and plugin compatibility.
 */
function facewall_remove_redundant_admin_submenu() {
    remove_submenu_page('facewall_plugin', 'facewall_plugin');
}
add_action('admin_menu', 'facewall_remove_redundant_admin_submenu', 999);

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

