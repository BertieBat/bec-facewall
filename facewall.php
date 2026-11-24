<?php
/*
   Plugin Name: facewall
   Plugin URI: https://github.com/speleo 
   description: Displays a custom grid facewall of active members from a CiviCRM database on Wordpress
   Version: 1.1.4
   Author: Henry Bennett
   Author URI: https://github.com/speleo
   
*/

defined( 'ABSPATH' ) || exit;

//require_once __DIR__ . '/includes/pdf.php';


// Add menu
function facewall_menu() {
    add_menu_page("Facewall", "Facewall","manage_options", "facewall_plugin", "admin_current_list",plugins_url('/facewall/img/facewall.png'));
    //add_submenu_page("facewall_plugin","Current", 		"Current",		"manage_options", "current_members", 	"admin_current_list");
    //add_submenu_page("facewall_plugin","Grace", 		"Grace",		"manage_options", "grace_members", 	"admin_grace_list");
    //add_submenu_page("facewall_plugin","LYBNTY", 		"LYBNTY",		"manage_options", "lybnty_members", 	"admin_lybnty_list");
    //add_submenu_page("facewall_plugin","Joint", 		"Joint",		"manage_options", "joint_members", 	"admin_joint_list");
    //add_submenu_page("facewall_plugin","Life", 		"Life",			"manage_options", "life_members", 	"admin_life_list");
    //add_submenu_page("facewall_plugin","Student", 		"Student",		"manage_options", "student_members", 	"admin_student_list");
    //add_submenu_page("facewall_plugin","Officer", 		"Officer",		"manage_options", "officer", 		"admin_officer_list");
    //add_submenu_page("facewall_plugin","Officer_grid_year", 	"Officer_grid_year",	"manage_options", "officer_grid_year", 	"admin_officer_grid_year");
    //add_submenu_page("facewall_plugin","Member List", 	"Member List",		"manage_options", "historical_list", 	"admin_historical_membership_list");
    //add_submenu_page("facewall_plugin","Everyone ever", 	"Everyone ever",	"manage_options", "historical_grid", 	"admin_historical_membership_grid");
    // test menu item
    add_submenu_page("facewall_plugin","A1 Poster", 		"A1 Poster",		"manage_options", "poster", 		"admin_poster");
    // the option below is for an A1 with printers bleeds
    add_submenu_page("facewall_plugin","RA1 Poster", 		"RA1 Poster",		"manage_options", "poster_RA1", 	"admin_poster_RA1");
    add_submenu_page("facewall_plugin","SRA1 Poster", 		"SRA1 Poster",		"manage_options", "poster_SRA1", 	"admin_poster_SRA1");
    //add_submenu_page("facewall_plugin","Prorata_fees",	"Prorata fees",		"manage_options", "prorata_fees", 	"admin_prorata_fees");


    //add_submenu_page("myplugin","Add new Entry", "Add new Entry","manage_options", "addnewentry", "addEntry");
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


function facewall_action($atts = [], $content = null, $tag = '' ){
	// normalize attribute keys, lowercase
	$atts = array_change_key_case( (array) $atts, CASE_LOWER );
	$fw_atts = shortcode_atts( 
		array(
			'scope' => 'current',
			'year' => 'all'
		), $atts, $tag );
	$scope = $fw_atts['scope'];
	$year  = $fw_atts['year'];

	switch ($scope) {
		case "current":
			$scope = 'current';
			$fw_title = 'Current members of the BEC';
			$fw_mem_status = '< 4';
			$fw_mem_type = '<8';
			return display_facewall_normal_new($fw_title, $fw_mem_status, $fw_mem_type);
		case "probationary":
			$fw_title = 'Probationary members';
			$fw_mem_status = '< 4';
			$fw_mem_type = '=2';
			return display_facewall_probationary($fw_title, $fw_mem_status, $fw_mem_type);
		case "grace":
			$scope = 'grace';
			$fw_title = 'Grace members';
			$fw_mem_status = '= 3';
			$fw_mem_type = '<9';
			return display_facewall_normal_new($fw_title, $fw_mem_status, $fw_mem_type);
		case "lybnty":
			$scope = 'lybnty';
			$fw_title = 'Last Year But not this Year';
			$fw_mem_status = '= 4';
			$fw_mem_type = '<9';
			return display_facewall_normal_new($fw_title, $fw_mem_status, $fw_mem_type);
		case "joint":
			$scope = 'joint';
			$fw_title = 'Joint members in the BEC';
			$fw_mem_status = '< 4';
			$fw_mem_type = 'IN (3,5,7)';
			$return = display_facewall_joint($fw_title, $fw_mem_status, $fw_mem_type);
	
			$scope = 'joint';
			$fw_title = 'Other Joint members in the BEC';
			$fw_mem_status = '< 4';
			$fw_mem_type = 'IN (3,5,7)';
			$return .= display_facewall_joint_invalid($fw_title, $fw_mem_status, $fw_mem_type);
			return $return;
		case "life":
			$scope = 'life';
			$fw_title = 'Life members of the BEC';
			$fw_mem_status = '< 4';
			$fw_mem_type = 'IN (4,5,6)';
			return display_facewall_normal_new($fw_title, $fw_mem_status, $fw_mem_type);
		case "student":
			$scope = 'student';
			$fw_title = 'Members claiming student or under 30 discount';
			$fw_mem_status = '< 4';
			$fw_mem_type = '= 15';
			$output  = display_facewall_normal_new($fw_title, $fw_mem_status, $fw_mem_type);
			$fw_title = 'Members under 30';
			$output .= display_facewall_under_30($fw_title, $fw_mem_status, $fw_mem_type);
			return $output;
		case "officer":
			//return display_officer_list($fw_title, $fw_mem_status, $fw_mem_type);
			return display_officer_list(0,0,0);
		case "officer_grid_year":
			return display_officer_grid_year($year);
		case "historical_membership_list":
			return display_membership_list('Historical membership list', '', '');
		case "historical_membership_grid":
			return display_all_membership();
		case "prorata_fees":
			return display_prorata_fees();
		case "membership_fees":
			// not used - See WPcode instead
			//
			//return display_membership_fees();

		}
	return '';
}


//add_shortcode('facewall','current_list');

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

function admin_grace_list(){
	$scope = 'grace';
	$fw_title = 'Grace members';
	$fw_mem_status = '= 3';
	$fw_mem_type = '<9';
	echo display_facewall_normal($fw_title, $fw_mem_status, $fw_mem_type);
}

function admin_lybnty_list(){
	$scope = 'lybnty';
	$fw_title = 'Last Year But not this Year';
	$fw_mem_status = '= 4';
	$fw_mem_type = '<9';
	echo display_facewall_normal($fw_title, $fw_mem_status, $fw_mem_type);
}

function admin_joint_list(){
	$scope = 'joint';
	$fw_title = 'Joint members in the BEC';
	$fw_mem_status = '< 4';
	$fw_mem_type = 'IN (3,5,7)';
	echo display_facewall_joint($fw_title, $fw_mem_status, $fw_mem_type);
}

function admin_life_list(){
	$scope = 'life';
	$fw_title = 'Life members of the BEC';
	$fw_mem_status = '< 4';
	$fw_mem_type = 'IN (4,5,6)';
	echo display_facewall_normal($fw_title, $fw_mem_status, $fw_mem_type);
}

function admin_student_list(){
	$scope = 'student';
	$fw_title = 'Members claiming student discount';
	$fw_mem_status = '< 4';
	$fw_mem_type = '= 15';
	echo display_facewall_normal($fw_title, $fw_mem_status, $fw_mem_type);
}

function admin_officer_list(){
	echo display_officer_list($fw_title, $fw_mem_status, $fw_mem_type);
}

function admin_officer_grid_year(){
	$scope = 'student';
	$fw_title = 'Members claiming student discount';
	$fw_mem_status = '< 4';
	$fw_mem_type = '= 15';
	echo display_officer_grid_year($fw_title, $fw_mem_status, $fw_mem_type);
}


function admin_historical_membership_list(){
	$scope = 'current';
	$fw_title = 'Current members of the BEC';
	$fw_mem_status = '< 4';
	$fw_mem_type = '<9';
	echo display_membership_list($fw_title, $fw_mem_status, $fw_mem_type);
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

?>
