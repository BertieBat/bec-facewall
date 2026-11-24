<?php
// These functions must run inside WordPress.
defined('ABSPATH') || exit;
require_once __DIR__ . '/includes/security.php';
/**
 * Helper class for BEC Facewall! module
 * 
 * @subpackage Modules
 * @link http://docs.joomla.org/J3.x:Creating_a_simple_module/Developing_a_Basic_Module
 * @license        GNU/GPL, see LICENSE.php
 * free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */

/**
 *   this file contains
 *   	function display_membership_list($fw_title, $fw_mem_status, $fw_mem_type)
 *   	function build_membership_list()
 *   	function get_membership_data($query)
 *   	function show_membership_data()
 *   	function build_chart_data_tables()
{
    if (!function_exists('facewall_maintenance_transaction_active') ||
        !facewall_maintenance_transaction_active()) {
        return 'build_chart_data_tables - failed (requires maintenance transaction)';
    }
    global $wpdb;

    // DELETE and INSERT run inside the same InnoDB transaction as the other
    // history tables. Never use TRUNCATE, which implicitly commits in MySQL.
    if ($wpdb->query('DELETE FROM civicrm_BEC_current_year') === false) {
        error_log('FaceWall: chart data DELETE failed.');
        return 'build_chart_data_tables - failed (delete)';
    }

    // Group by contact (not display name): people can share the same name.
    // Aggregating the display name is compatible with ONLY_FULL_GROUP_BY.
    $sql = "INSERT INTO civicrm_BEC_current_year (mem_years, display_name, age)
        SELECT COUNT(*) AS mem_years, MAX(h.display_name) AS display_name,
               TIMESTAMPDIFF(YEAR, c.birth_date, CURDATE()) AS age
        FROM civicrm_BEC_historical_membership AS h
        LEFT JOIN civicrm_contact AS c ON h.contact_id = c.id
        WHERE h.contact_id IN (
            SELECT contact_id FROM civicrm_BEC_historical_membership
            WHERE club_year = YEAR(CURDATE())
        )
        GROUP BY h.contact_id, c.birth_date
        ORDER BY mem_years DESC";
    if ($wpdb->query($sql) === false) {
        error_log('FaceWall: chart data insert failed.');
        return 'build_chart_data_tables - failed (insert)';
    }

    return 'build_chart_data_tables - completed';
}


} // End duplicate-include guard.

?>
