<?php
// These functions must run inside WordPress.
defined('ABSPATH') || exit;
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/history.php';
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
**/

global $wpdb;

// Some scheduled tasks include this helper after WordPress has already loaded it.
if (!function_exists('display_membership_list')) {

function display_membership_list($fw_title, $fw_mem_status, $fw_mem_type)
{
	//build_membership_list();
	return show_membership_data();
	//create_membership_csv_files();
}

function build_membership_list()
{
	if (!function_exists('facewall_maintenance_transaction_active') || !facewall_maintenance_transaction_active()) {
		return 'build_membership_list - failed (requires maintenance transaction)';
	}
	// this function is called from a cron job and is run weekly
	// half the data comes from the membership_log but old records aren't in that
	// so we grab those from the membership table and merge it in.
	global $wpdb;
	$query_old=("SELECT 
			civicrm_contact.id  as cv_contact_id , 
			civicrm_contact.is_deceased as cv_is_deceased,
			civicrm_contact.deceased_date as cv_deceased_date,
			civicrm_contact.display_name  as cv_display_name , 
			membership.membership_type_id  as cv_membership_type_id , 
			membership.join_date  as cv_join_date , 
			membership.start_date  as cv_start_date , 
			membership.end_date  as cv_end_date

		FROM civicrm_contact      
     
		  LEFT JOIN civicrm_membership as membership
			ON membership.contact_id = civicrm_contact.id    
    
		WHERE membership.membership_type_id IN (1,2,3,4,5,6,7) 
		AND membership.is_test != 1
		AND membership.status_id NOT IN (7,8)
		");

	$query_log=("SELECT DISTINCT 
			sql_table.start_date AS cv_start_date,
			sql_table.end_date AS cv_end_date,
		    	contact.id AS cv_contact_id,
			contact.is_deceased AS cv_is_deceased,
			contact.deceased_date AS cv_deceased_date,
			contact.display_name AS cv_display_name,
			membership.join_date AS cv_join_date,
			membership_type.name AS cv_membership_type_name,
			sql_table.membership_type_id AS cv_membership_type_id
    
		FROM civicrm_membership_log 	AS sql_table
		JOIN civicrm_membership_type 	AS membership_type ON membership_type.id = sql_table.membership_type_id
		JOIN civicrm_membership_status 	AS membership_status ON membership_status.id = sql_table.status_id
		JOIN civicrm_membership 		AS membership ON membership.id = sql_table.membership_id
		JOIN civicrm_contact 			AS contact ON contact.id  = membership.contact_id

		WHERE sql_table.membership_type_id IN (1,2,3,4,5,6,7)
		AND membership.is_test != 1
		AND membership.status_id NOT IN (7,8)
		ORDER BY cv_display_name
		");

	// Check sources before clearing the table: a database outage must not erase history.
	$log_rows = $wpdb->get_results($query_log);
	$old_rows = $wpdb->get_results($query_old);
	if (!is_array($log_rows) || !is_array($old_rows) || (!$log_rows && !$old_rows)) {
		error_log('Facewall: historical membership source unavailable; existing table retained. ' . $wpdb->last_error);
		return 'build_membership_list - skipped (source unavailable)';
	}
	if ($wpdb->query('DELETE FROM civicrm_BEC_historical_membership') === false) {
		error_log('Facewall: historical membership DELETE failed: ' . $wpdb->last_error);
		return 'build_membership_list - failed (delete)';
	}
	if (get_membership_data($log_rows) === false || get_membership_data($old_rows) === false) {
		return 'build_membership_list - failed (insert)';
	}
	//return show_membership_data();
	//
	//chain on some other stuff
	//build_chart_data_tables();

	$msg = "build_membership_list - completed";
	return $msg;
}



function get_membership_data($query)
{
	//gather all the data from the various tables
	global $wpdb;

/**
	1	Ordinary	
	2	Probationary	
	3	Joint	
	4	Life	
	5	Joint Members Life	
	6	Honorary Life	
	7	Joint Probationary	
	8	Special	
	9	BCA - Active	
	10	BCA - Surface	
	11	BCA - Student	
	12	BCA - Other	
	15	Student Discount	
	16	BCA - Publications Only	
	17	Joint Discount	
	18	Committee	
**/

	//  inject the data back into a new table adding a row for every club year of membership.
	$result = is_array($query) ? $query : $wpdb->get_results($query);
	if (!is_array($result)) {
		error_log('Facewall: historical membership query failed: ' . $wpdb->last_error);
		return false;
	}
	if (!$result) {
		return 0;
	}
	$result_count = count($result);


	// now loop through the results and inject the data into a new table
	// but we need to think about year vs club year.
	// around 1960 the club year shifted from calander year to 1st October - 30 September
	for($result_index = 0; $result_index < $result_count ; $result_index++)
		{
			$facewall_output = '';
			$row = $result[$result_index];
			if (empty($row->cv_start_date) || strtotime($row->cv_start_date) === false) {
				continue;
			}



			// if the end date isn't set then set it to current year xxxx
			if (empty($row->cv_end_date) || $row->cv_end_date === '0000-00-00')
				{
				$end_date = date("Y");
			}
			else{
				$end_date = date("Y",strtotime($row->cv_end_date));
			}
		
			// loop thru the years from start_date to end_date
			foreach (facewall_history_years($row->cv_start_date, $end_date) as $mem_club_year)
			{
				$start_time = strtotime($row->cv_start_date);
				if (is_null($row->cv_deceased_date))
					{
						//$death_time = strtotime($row->cv_deceased_date); 
						$death_time = date("Y") +1;

					}
				else
					{
						//$death_time = strtotime($row->cv_deceased_date); 
						//$death_time = date("Y",$row->cv_deceased_date);
						$zzz = new DateTime($row->cv_deceased_date);  
						$death_time = $zzz->format('Y'); 

					//$death_time = date("Y") +1;
					}

				
				//  debug
				//
				/**
				$tmpfilename = '/home/bertie/public_html/wp/wp-content/plugins/facewall/facewall_debug_members_list.txt'; // Temporary file location
				$f = fopen($tmpfilename, 'a+');
				//fwrite($f,date("d/m : H:i :").$line.' ('.$execution_time.' seconds)'.PHP_EOL);
				if ($death_time != 2025)
				{
					fwrite($f,$row->cv_display_name.' '.$death_time.PHP_EOL);
				}
				fclose($f);
				**/
				//  end debug

				// loop if end date is in the next year or beyond
				if ($mem_club_year > date("Y"))
				{
					continue;
				}
				// loop if member is deceased after this date
				if (
					// loop if deceased and no deceased date)
					($row->cv_is_deceased == 1) && is_null($row->cv_deceased_date)
					
					// date("Y",$row->cv_deceased_date) >= date("Y",$mem_club_year))
					)
				{
					continue;
				}
				//elseif (date("Y",$death_time) >= date("Y",$mem_club_year))
				//elseif (date("Y",$death_time) >= date("Y",$mem_club_year))
				elseif ($death_time <= $mem_club_year)
				{
					continue;
				}



				// CiviCRM legacy data can contain blank or zero join dates.
				// Never insert an invalid date into a strict SQL DATE column.
				$join_date = (string) ($row->cv_join_date ?? '');
				$join_parts = explode('-', $join_date);
				if (count($join_parts) !== 3 ||
					!ctype_digit($join_parts[0]) || !ctype_digit($join_parts[1]) || !ctype_digit($join_parts[2]) ||
					!checkdate((int) $join_parts[1], (int) $join_parts[2], (int) $join_parts[0])) {
					$join_date = (string) $row->cv_start_date;
				}

				// A unique index on club_year/contact_id prevents two rows for the same
				// member/year. Keep the membership-log row ahead of the legacy fallback.
				$insert_query = $wpdb->prepare(
					"INSERT INTO civicrm_BEC_historical_membership
					 (contact_id, display_name, club_year, join_date, start_date, end_date, membership_type)
					 VALUES (%d, %s, %d, %s, %s, NULLIF(%s, ''), %d)
					 ON DUPLICATE KEY UPDATE contact_id = contact_id",
					(int) $row->cv_contact_id,
					(string) $row->cv_display_name,
					(int) $mem_club_year,
					$join_date,
					(string) $row->cv_start_date,
					(string) $row->cv_end_date,
					(int) $row->cv_membership_type_id
				);
				if ($wpdb->query($insert_query) === false) {
					error_log('Facewall: historical membership insert failed for contact ' . (int) $row->cv_contact_id . ', year ' . (int) $mem_club_year . ': ' . $wpdb->last_error);
					return false;
				}
				
			}
	}
	return true;
}
//-----------------------------------------------------------------------------------------------------------------

function show_membership_data()
{
	global $wpdb;
	$facewall_output = '';
	$final_query = ("SELECT contact_id, display_name, club_year, join_date, start_date, end_date, membership_type
		FROM civicrm_BEC_historical_membership AS history
		INNER JOIN custom_value_1_BEC AS consent ON consent.entity_id = history.contact_id AND consent.facewall_public_84 = 1
		ORDER BY club_year DESC, display_name ASC 
		");
	// Prepare the query
	$final = $wpdb->get_results($final_query);
	$final_count = is_array($final) ? count($final) : 0;
	if ($final_count === 0) {
		return '<p>No members found.</p>';
	}
	// find the max year value in the data
	$club_year = max(array_column($final, 'club_year'));
	$facewall_output .= '<h2>Club Year '.$club_year. ' - '.array_count_values(array_column($final,'club_year'))[$club_year] .' members</h2>';
	for($final_index = 0; $final_index < $final_count ; $final_index++)
		{
			$row2 = $final[$final_index];
			// put in a header
			if ($row2->club_year != $club_year)
			{
				$club_year = (int) $row2->club_year;
				//$facewall_output .= '<h2>'.$club_year.'</h2>';
				$facewall_output .= '<h2>Club Year '.$club_year. ' - '.array_count_values(array_column($final,'club_year'))[$club_year] .' members</h2>';
			}		
			// setup some variables		
			$facewall_output .= '       '.facewall_h($row2->display_name);
			$start_time = strtotime($row2->start_date);
			$end_time = strtotime($row2->end_date);
			if ( date("Y",$start_time) <= '1960' )
			{
				// ???
				if (( date("Y",$start_time) <= '1960' ) && ( date("Y",$start_time) <= '1960' ))
				{}
			}
			else
			{
				// handle people JOINING a role part way through the year
				if (( date("d",$start_time) == '01' ) && ( date("m",$start_time) == '10' ) )
				{}
				else
				{
					if (date("Y",$start_time) == $club_year) 
					{ 
						$facewall_output .= ' - from: '.date("F j, Y ",$start_time);
					}
				}
				
				// handle people LEAVING a role part way through the year
				if (( date("d",$end_time) == '30' ) && ( date("m",$end_time) == '09' )) 
				{}
				else
				{
					if (date("Y",$end_time) == $club_year) 
					{ 
						$facewall_output .= ' - until: '.date("F j, Y ",$end_time);
					}
				}
			}
			$facewall_output .= '</br>';
		}
	$facewall_output .= '</br>Number of records ' . $final_index ;
	return  $facewall_output;
}

function build_chart_data_tables()
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
