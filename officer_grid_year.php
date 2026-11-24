<?php
// Loaded exclusively by the FaceWall WordPress plugin.
defined('ABSPATH') || exit;
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
 *
 * function display_facewall_normal_new($fw_title, $fw_mem_status, $fw_mem_type)
 * function display_facewall_under_30($fw_title, $fw_mem_status, $fw_mem_type)
 * function display_facewall_probationary($fw_title, $fw_mem_status, $fw_mem_type)
 * function display_all_membership()
 * function consolidateArray($array) - row 514
 * function membership_details($row, $memberships)
 * function display_officer_list($fw_title, $fw_mem_status, $fw_mem_type)
 * function display_officer_grid_year($year)
 * function facewall_pad_grid($column, $max_column, $html_output)
 * function build_officer_historical_table($a)
 * function build_BEC_historical_summary_table($a)
 */

global $wpdb;

require_once __DIR__ . '/current_list.php';
require_once __DIR__ . '/includes/calendar.php';

function display_facewall_normal_new($fw_title, $fw_mem_status, $fw_mem_type)
{
	global $wpdb;
	$facewall_output = '';

	$query=("SELECT cust.Membership_Number, c.first_name, c.nick_name, c.last_name, CONCAT(c.last_name, c.first_name),  mtype.name, mstatus.name AS m_status, mem.id, hist.image_URL, c.image_URL AS contact_image_URL, hist.summary, cust.facewall_public_84
		from civicrm_contact AS c
		LEFT JOIN custom_value_1_BEC as cust ON c.id = cust.entity_id
		LEFT JOIN civicrm_BEC_historical_summary as hist ON c.id  = hist.contact_id
		LEFT JOIN civicrm_membership AS mem  ON c.id = mem.contact_id AND mem.membership_type_id < 18
		LEFT JOIN civicrm_membership_type AS mtype ON  mem.membership_type_id = mtype.id
		LEFT JOIN civicrm_membership_status AS mstatus ON mem.status_id = mstatus.id
		WHERE mtype.id ".$fw_mem_type." AND status_id ".$fw_mem_status." AND c.is_deceased != 1 AND cust.facewall_public_84 = 1
		ORDER BY last_name, first_name");

	// Prepare the query
	$final = $wpdb->get_results($query);
	$final_count = is_array($final) ? count($final) : 0;
	if ($final_count === 0) {
		return '<p>No members found.</p>';
	}
	if ($final_count == 0) {
		$facewall_output = 'There are none';
		return $facewall_output;
	}

	// find the highest membership number in the data
	$current_mem_num = max(array_column($final, 'Membership_Number'))+1;

	// setup some variables
        $column = 1;
	$max_column = 5;
	$display_row = 1;
	$display_row_div = 1;
	$image_dir = "wp/wp-content/uploads/civicrm/custom/";
	$base_dir = "/home/bertie/public_html/";
	$member_dir = "images/members/";
	$root_dir = "https://bec-cave.org.uk/";
	$thumb_dir = "tn/";
	$thumb_width = 174;
	$thumb_height = 205;
	//$memberships = '';

	// include CSS to format it properly
	$facewall_output .= '<h2>'.$fw_title.' - '.$final_count.' members</h2>';

	// then loop thru the rest of the records 
	for($final_index = 0; $final_index < $final_count ; $final_index++)
		{
			$row = $final[$final_index];

			// now place the output for each member
			if ( $display_row_div == 1 ){ 
				$facewall_output .= '<div class="items-row cols-4 row-'.$display_row.' row-fluid clearfix bec_members_row">';  
				$display_row++;
				$display_row_div++;
			} 
      
			$facewall_output .= facewall_render_member_card(
                $column,
                facewall_h($row->first_name) . ' ' . facewall_h($row->last_name),
                facewall_photo_html($row->image_URL, $row->first_name.' '.$row->last_name, facewall_is_public($row->facewall_public_84), $row->contact_image_URL),
                facewall_summary_html($row->summary)
            );
            if ( $column < $max_column ){
				$column++;
			} 
			else {
				$column = 1;
		  		$display_row_div = 1;
		  		$facewall_output .= '</div>';  
			}
		// and loop back around
		}
		

	// finally clean up the remaining divs
	// CSS Grid handles partial rows; do not close a row twice.
	if ($display_row_div !== 1) {
		$facewall_output .= '</div>';
	}
	$facewall_output .= '</br>Number of records ' . $final_index ;
	
	return  $facewall_output;
	
}


function display_facewall_under_30($fw_title, $fw_mem_status, $fw_mem_type)
{
	global $wpdb;
	$facewall_output = '';

	$lastAGM = facewall_most_recent_agm();
	//$lastAGM = strtotime("first Saturday of October ".date("Y", strtotime("-1 year")));

	$ageLimit = 30;
	//
	//$facewall_output .= '<h2>'.$lastAGM.' '.date('d-m-y',$lastAGM).' </h2>';
	//$facewall_output .= '<h2>'.$lastAGM.'</h2>';


	//$sql = "SELECT * FROM members WHERE birthdate <= '".date("Y-m-d", $lastAGM)."' AND (DATEDIFF('".date("Y-m-d", $lastAGM)."', birthdate) / 365.25) < $ageLimit";


	$query=("SELECT cust.facewall_public_84, cust.Membership_Number, c.first_name, c.nick_name, c.last_name, c.birth_date,  mtype.name, mstatus.name AS m_status, mem.id, hist.image_URL, c.image_URL AS contact_image_URL, hist.summary, DATE_FORMAT(FROM_DAYS(DATEDIFF(NOW(),c.birth_date)), '%Y') + 0 AS age
		from civicrm_contact AS c
		LEFT JOIN custom_value_1_BEC as cust ON c.id = cust.entity_id
		LEFT JOIN civicrm_BEC_historical_summary as hist ON c.id  = hist.contact_id
		LEFT JOIN civicrm_membership AS mem  ON c.id = mem.contact_id AND mem.membership_type_id < 18
		LEFT JOIN civicrm_membership_type AS mtype ON  mem.membership_type_id = mtype.id
		LEFT JOIN civicrm_membership_status AS mstatus ON mem.status_id = mstatus.id
		WHERE mtype.id <9 
			AND status_id <4 
			AND c.is_deceased != 1 AND cust.facewall_public_84 = 1
			AND c.birth_date > DATE_SUB('".$lastAGM."', INTERVAL 30 YEAR)
		ORDER BY last_name, first_name");

	// Prepare the query
	$final = $wpdb->get_results($query);
	$final_count = is_array($final) ? count($final) : 0;
	if ($final_count === 0) {
		return '<p>No members found.</p>';
	}

	// find the highest membership number in the data
	$current_mem_num = max(array_column($final, 'Membership_Number'))+1;

	// setup some variables
        $column = 1;
	$max_column = 5;
	$display_row = 1;
	$display_row_div = 1;
	$image_dir = "wp/wp-content/uploads/civicrm/custom/";
	$base_dir = "/home/bertie/public_html/";
	$member_dir = "images/members/";
	$root_dir = "https://bec-cave.org.uk/";
	$thumb_dir = "tn/";
	$thumb_width = 174;
	$thumb_height = 205;
	//$memberships = '';

	// include CSS to format it properly
	$facewall_output .= '<h2>'.$fw_title.' at the last AGM - '.$lastAGM.'<br>'.$final_count.' members</h2>';

	// then loop thru the rest of the records 
	for($final_index = 0; $final_index < $final_count ; $final_index++)
		{
			$row = $final[$final_index];

			// now place the output for each member
			if ( $display_row_div == 1 ){ 
				$facewall_output .= '<div class="items-row cols-4 row-'.$display_row.' row-fluid clearfix bec_members_row">';  
				$display_row++;
				$display_row_div++;
			} 
      
			$facewall_output .= facewall_render_member_card(
                $column,
                facewall_h($row->first_name) . ' ' . facewall_h($row->last_name),
                facewall_photo_html($row->image_URL, $row->first_name.' '.$row->last_name, facewall_is_public($row->facewall_public_84), $row->contact_image_URL),
                facewall_summary_html($row->summary)
            );
            if ( $column < $max_column ){
				$column++;
			} 
			else {
				$column = 1;
		  		$display_row_div = 1;
		  		$facewall_output .= '</div>';  
			}
		// and loop back around
		}
		

	// finally clean up the remaining divs
	// CSS Grid handles partial rows; do not close a row twice.
	if ($display_row_div !== 1) {
		$facewall_output .= '</div>';
	}
	$facewall_output .= '</br>Number of records ' . $final_index ;
	
	return  $facewall_output;
	
}

//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
//
function display_facewall_probationary($fw_title, $fw_mem_status, $fw_mem_type)
{
	global $wpdb;
	$facewall_output = '';

	$query=("SELECT cust.facewall_public_84, cust.Membership_Number, c.first_name, c.nick_name, c.last_name, CONCAT(c.last_name, c.first_name),  mtype.name, mstatus.name AS m_status, mem.id, mem.join_date, hist.image_URL, c.image_URL AS contact_image_URL, hist.summary
		from civicrm_contact AS c
		LEFT JOIN custom_value_1_BEC as cust ON c.id = cust.entity_id
		LEFT JOIN civicrm_BEC_historical_summary as hist ON c.id  = hist.contact_id
		LEFT JOIN civicrm_membership AS mem  ON c.id = mem.contact_id AND mem.membership_type_id < 18
		LEFT JOIN civicrm_membership_type AS mtype ON  mem.membership_type_id = mtype.id
		LEFT JOIN civicrm_membership_status AS mstatus ON mem.status_id = mstatus.id
		WHERE mtype.id ".$fw_mem_type." AND status_id ".$fw_mem_status." AND c.is_deceased != 1 AND cust.facewall_public_84 = 1
		ORDER BY mem.join_date, last_name, first_name");

	// Prepare the query
	$final = $wpdb->get_results($query);
	$final_count = is_array($final) ? count($final) : 0;
	if ($final_count === 0) {
		return '<p>No members found.</p>';
	}

	// find the highest membership number in the data
	$current_mem_num = max(array_column($final, 'Membership_Number'))+1;

	// setup some variables
        $column = 1;
	$max_column = 5;
	$display_row = 1;
	$display_row_div = 1;
	$image_dir = "wp/wp-content/uploads/civicrm/custom/";
	$base_dir = "/home/bertie/public_html/";
	$member_dir = "images/members/";
	$root_dir = "https://bec-cave.org.uk/";
	$thumb_dir = "tn/";
	$thumb_width = 174;
	$thumb_height = 205;
	//$memberships = '';

	// include CSS to format it properly
	$facewall_output .= '<h2>'.$fw_title.' - '.$final_count.' members</h2>';

	// then loop thru the rest of the records 
	for($final_index = 0; $final_index < $final_count ; $final_index++)
		{
			$row = $final[$final_index];

			// now place the output for each member
			if ( $display_row_div == 1 ){ 
				$facewall_output .= '<div class="items-row cols-4 row-'.$display_row.' row-fluid clearfix bec_members_row">';  
				$display_row++;
				$display_row_div++;
			} 

			// calculate years & months from today
			/**
			$start_date = strtotime($row->join_date);
			$today = strtotime("now");
			$diff = $today - $start_date;
			$num_years = floor($diff / (365 * 60 * 60 * 24));
			$remaining_seconds = $diff % (365 * 60 * 60 * 24);
			$num_months = floor($remaining_seconds / (30 * 60 * 60 * 24));

			**/


			$start = new DateTime($row->join_date);
			$today = new DateTime();
			$interval = date_diff($start, $today);

			$years = $interval->y;
			$months = $interval->m;

			if ($years == 1) {
			    $yearText = "1 year";
			} elseif ($years > 1) {
			    $yearText = "{$years} years";
			} else {
			    $yearText = "";
			}

			if ($months == 1) {
			    $monthText = "1 month";
			} elseif ($months > 1) {
			    $monthText = "{$months} months";
			} else {
			    $monthText = "";
			}

			if ($years > 0 && $months > 0) {
			    $result = "{$yearText} and {$monthText}";
			} else {
			    $result = "{$yearText}{$monthText}";
			}

//echo $result;


//echo "The difference is " . $num_years . " years and " . $num_months . " months.";

			$facewall_output .= facewall_render_member_card(
                $column,
                $result . '<br>' . date("Y, F", strtotime($row->join_date)) . '<br>' . facewall_h($row->first_name) . ' ' . facewall_h($row->last_name),
                facewall_photo_html($row->image_URL, $row->first_name.' '.$row->last_name, facewall_is_public($row->facewall_public_84), $row->contact_image_URL),
                facewall_summary_html($row->summary)
            );
            if ( $column < $max_column ){
				$column++;
			} 
			else {
				$column = 1;
		  		$display_row_div = 1;
		  		$facewall_output .= '</div>';  
			}
		// and loop back around
		}
		

	// finally clean up the remaining divs
	// CSS Grid handles partial rows; do not close a row twice.
	if ($display_row_div !== 1) {
		$facewall_output .= '</div>';
	}
	$facewall_output .= '</br>Number of records ' . $final_index ;
	
	return  $facewall_output;
	
}


//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

function display_all_membership()
{
	// displays a photo grid of every member, ever

	// This funcation is now run in a wordpress cron called via a hook
	// build_BEC_historical_summary_table($a);

	
	global $wpdb;
	$facewall_output = '';
	$final_query = ("SELECT hist.*, c.image_URL AS contact_image_URL FROM civicrm_BEC_historical_summary AS hist
		INNER JOIN civicrm_contact AS c ON c.id = hist.contact_id
		INNER JOIN custom_value_1_BEC AS consent ON consent.entity_id = hist.contact_id AND consent.facewall_public_84 = 1
		ORDER BY hist.id DESC");

	// Prepare the query
	$final = $wpdb->get_results($final_query);
	$final_count = is_array($final) ? count($final) : 0;
	if ($final_count === 0) {
		return '<p>No members found.</p>';
	}

	// find the highest membership number in the data
	// $current_mem_num = max(array_column($final, 'mem_num'))+1;
	$current_mem_num = max(array_column($final, 'id'))+1;


	// setup some variables
        $column = 1;
	$max_column = 5;
	$display_row = 1;
	$display_row_div = 1;
	$image_dir = "wp/wp-content/uploads/civicrm/custom/";
	$base_dir = "/home/bertie/public_html/";
	$member_dir = "images/members/";
	$root_dir = "https://bec-cave.org.uk/";
	$thumb_dir = "tn/";
	$thumb_width = 174;
	$thumb_height = 205;
	//$memberships = '';

	// include CSS to format it properly

	// then loop thru the rest of the records 
	for($final_index = 0; $final_index < $final_count ; $final_index++)
		{
			$row = $final[$final_index];

			// now place the output for each member
			if ( $display_row_div == 1 ){ 
				$facewall_output .= '<div class="items-row cols-4 row-'.$display_row.' row-fluid clearfix bec_members_row">';  
				$display_row++;
				$display_row_div++;
			} 
      
			$facewall_output .= facewall_render_member_card(
                $column,
                facewall_h($row->id),
                facewall_photo_html($row->image_URL, $row->display_name, true, $row->contact_image_URL),
                facewall_summary_html($row->summary),
                facewall_h($row->display_name)
            );
            if ( $column < $max_column ){
				$column++;
			} 
			else {
				$column = 1;
		  		$display_row_div = 1;
		  		$facewall_output .= '</div>';  
			}
		// and loop back around
		}
		

	// finally clean up the remaining divs
	// CSS Grid handles partial rows; do not close a row twice.
	if ($display_row_div !== 1) {
		$facewall_output .= '</div>';
	}
	$facewall_output .= '</br>Number of records ' . $final_index ;
	
	return  $facewall_output;
	
}



//pppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppppp


function consolidateArray($array) {
    $grouped = [];
    foreach ($array as $element) {
        $title = $element['title'];
        if (!isset($grouped[$title])) {
            $grouped[$title] = [];
        }
        $grouped[$title][] = $element;
    }

    $consolidated = [];
    foreach ($grouped as $title => $group) {
        usort($group, function ($a, $b) {
            return $a['start'] - $b['start'];
        });

        $rangeStart = $group[0]['start'];
        $rangeEnd = $group[0]['end'];
        for ($i = 1; $i < count($group); $i++) {
            $current = $group[$i];
            if ($current['start'] <= $rangeEnd) {
                $rangeEnd = max($rangeEnd, $current['end']);
            } else {
                $consolidated[] = [
                    'title' => $title,
                    'start' => $rangeStart,
                    'end' => $rangeEnd,
                ];
                $rangeStart = $current['start'];
                $rangeEnd = $current['end'];
            }
        }

        $consolidated[] = [
            'title' => $title,
            'start' => $rangeStart,
            'end' => $rangeEnd,
        ];
    }

    return $consolidated;
}

function membership_details($row, $memberships)
{
	$mems = $memberships;
	if ($row->name == 'Committee')
	{
		$post = $row->source;
	} else {
		$post = $row->name;
	}
	switch ($post) {
		case "Assistant Caving Secretary":
			$post = "Asst. Cave Sec";
			break;
		case "Belfry Bulletin Editor":
			$post = "BB Editor";
			break;
		case "Ladies Representative":
			$post = "Ladies Rep";
			break;
		case "London Representative":
			$post = "London Rep";
			break;
		case "Assistant Hut Warden":
			$post = "Asst, Hut Warden";
			break;
		case "Membership Secretary":
			$post = "Membership Sec";
			break;
		case "Assistant Secretary":
			$post = "Assistant Sec";
			break;
		case "Climbing Secretary":
			$post = "Climbing Sec";
			break;
		case "Committee Chairman":
			$post = "Committee Chair";
			break;
		default:
			break;
	}
	$mems[] = ['title'=> $post, 'start'=>$row->start, 'end'=>$row->end];
	//echo 'mems <pre>'.print_r($mems,1).'</pre>';
	return $mems;
}



//###############################################################################################################

function display_officer_list($fw_title, $fw_mem_status, $fw_mem_type)
{
	// displays a list of officers grouped by year
	
	
	// gather all the data from the various tables
	// this is run on a cron.  
	// if you want to see updates live then uncomment this line
	//
	//build_officer_historical_table($a);
	
	global $wpdb;
	$facewall_output = '';
	$visible_committee_year = facewall_current_committee_year();
	$final_query = $wpdb->prepare("SELECT contact_id, display_name, post, club_year, join_date, start_date, end_date, weight
		FROM civicrm_BEC_historical_officer_membership AS hist
		INNER JOIN custom_value_1_BEC AS consent ON consent.entity_id = hist.contact_id AND consent.facewall_public_84 = 1
		WHERE `club_year` <= %d
		ORDER BY club_year DESC, weight ASC",
		$visible_committee_year);

	// Prepare the query
	$final = $wpdb->get_results($final_query);
	$final_count = is_array($final) ? count($final) : 0;
	if ($final_count === 0) {
		return '<p>No members found.</p>';
	}

	// find the max year value in the data
	$club_year = max(array_column($final, 'club_year'));
	$facewall_output .= '<h2>'.$club_year.'</h2>';

	for($final_index = 0; $final_index < $final_count ; $final_index++)
		{
			$row2 = $final[$final_index];
			
			if ($row2->club_year != $club_year)
			{
				$club_year = (int) $row2->club_year;
				$facewall_output .= '<h2>'.$club_year.'</h2>';

			} 
			//$facewall_output .= $row2->club_year. ' ' ;		
			
			$facewall_output .= '       '.facewall_h($row2->display_name). ' - '.facewall_h($row2->post);
			$start_time = strtotime($row2->start_date);
			$end_time = strtotime($row2->end_date);


			if ( date("Y",$start_time) <= '1960' )
			{
				if (( date("Y",$start_time) <= '1960' ) && ( date("Y",$start_time) <= '1960' ))
				{}


				//$mem_club_year = $x;
			}
			else
			{
				// handle people JOINING a role part way through the year
				if (( date("d",$start_time) == '01' ) && ( date("m",$start_time) == '10' ) )
				{
					//continue;
				}
				else
				{
					if (date("Y",$start_time) == $club_year) 
					{
						$facewall_output .= ' - from: '.date("F j, Y ",$start_time);
					}
				}
				
				// handle people LEAVING a role part way through the year
				if (( date("d",$end_time) == '30' ) && ( date("m",$end_time) == '09' )) 
				{
					//continue;
				}
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
	//return $result;
	//
	//$facewall_output = $result;
	//print("<pre>".print_r($final,true)."</pre>");

	
	return  $facewall_output;
	
}

//==========================================================================================================================================

function display_officer_grid_year($year)
{
	// displays a photo grid of officers grouped by year
	
	// gather all the data from the various tables
	// this is run on a cron.  
	// if you want to see updates live then uncomment this line
	//
	// build_officer_historical_table();

	global $wpdb;
	$facewall_output = '';
	if ($year == 'current'){
		$fw_year = '=';
	}
	else {
		$fw_year = '<=';
	}
	
	/**
	if (date("m") >=9 ){
		$search_year = date("Y")+1;}
		// If we are in October show next years committee
	else{
		$search_year = date("Y");}
	**/
	// The same AGM handover rule is used for the officer grid and poster.
	$search_year = facewall_current_committee_year();


	$final_query = ("SELECT hist.contact_id, hist.display_name, hist.post, hist.club_year, hist.join_date, hist.start_date, hist.end_date, hist.weight, hist.image_URL,
		c.image_URL AS contact_image_URL
		FROM civicrm_BEC_historical_officer_membership AS hist
		INNER JOIN civicrm_contact AS c ON c.id = hist.contact_id
		INNER JOIN custom_value_1_BEC AS consent ON consent.entity_id = hist.contact_id AND consent.facewall_public_84 = 1
		WHERE `club_year` ".$fw_year." ".$search_year."
		ORDER BY club_year DESC, weight ASC;
		");

	// Prepare the query
	$final = $wpdb->get_results($final_query);
	$final_count = is_array($final) ? count($final) : 0;
	if ($final_count === 0) {
		return '<p>No members found.</p>';
	}

	// find the max year value in the data
	$club_year = max(array_column($final, 'club_year'));

	// setup some variables
        $column = 1;
	$max_column = 5;
	$display_row = 1;
	$display_row_div = 1;
	$image_dir = "wp/wp-content/uploads/civicrm/custom/";
	$base_dir = "/home/bertie/public_html/";
	$member_dir = "images/members/";
	$root_dir = "https://bec-cave.org.uk/";
	$thumb_dir = "tn/";
	$thumb_width = 174;
	$thumb_height = 205;

	// include CSS to format it properly


	// handle the first row which contains the current year
	$facewall_output .= '<div class="items-row cols-4 row-'.$display_row.' row-fluid clearfix bec_year_row">';  
	$facewall_output .= '<h2>'.$club_year.'</h2></div>';
	$display_row++;
	// The year heading is a full-width row; no empty photo cells needed.
	// then reset the column to 1
	$column = 1;

	// then loop thru the rest of the records 
	for($final_index = 0; $final_index < $final_count ; $final_index++)
		{
			$row2 = $final[$final_index];

			// handle the year first
			if ($row2->club_year != $club_year)
			{
				$club_year = (int) $row2->club_year;

				// Close only a partially filled row before opening a year heading.
				if ($display_row_div !== 1) {
					$facewall_output .= '</div>';
				}
				// then place the year on the page
				$facewall_output .= '<div class="items-row cols-4 row-'.$display_row.' row-fluid clearfix bec_year_row">';  
				$facewall_output .= '<h2>'.$club_year.'</h2></div>';
				$column = 1;
				$display_row_div = 1;
				$display_row++;
			} 


			//
			// now place the output for each post 
			if ( $display_row_div == 1 ){ 
				$facewall_output .= '<div class="items-row cols-4 row-'.$display_row.' row-fluid clearfix bec_members_row">';  
				$display_row++;
				$display_row_div++;
			} 
      
			$facewall_output .= '<div class="span2">';    
		        $facewall_output .= '<div class="item column-'.$column.'" itemprop="blogPost" itemscope="" itemtype="http://schema.org/BlogPosting">';
			$facewall_output .= '<div class="page-header">';

			// fix posts which are too long for the div
			$display_post = $row2->post ;
			switch ($display_post) {
				case 'Belfry Bulletin Editor':
					$display_post = 'BB Editor';
					break;
				case 'Floating Committee Member':
					$display_post = 'Floating';
					break;
			}

			$facewall_output .= '<h5 itemprop="name" max-width=250px>'.facewall_h($display_post).'</h5>';
			
			// and then the name
			$facewall_output .= '       '.facewall_h($row2->display_name);
			$start_time = strtotime($row2->start_date);
			$end_time = strtotime($row2->end_date);

			// deal with people joing or leaving part way thru a year
			if ( date("Y",$start_time) <= '1960' )
			{
				if (( date("Y",$start_time) <= '1960' ) && ( date("Y",$start_time) <= '1960' ))
				{}
				//$mem_club_year = $x;
			}
			else
			{
				// handle people JOINING a role part way through the year
				if (( date("d",$start_time) == '01' ) && ( date("m",$start_time) == '10' ) )
				{
					//continue;
				}
				else
				{
					if (date("Y",$start_time) == $club_year) 
					{
						$facewall_output .= ' - from: '.date("F j, Y ",$start_time);
					}
				}
				
				// handle people LEAVING a role part way through the year
				if (( date("d",$end_time) == '30' ) && ( date("m",$end_time) == '09' )) 
				{
					//continue;
				}
				else
				{
					if (date("Y",$end_time) == $club_year) 
					{
						$facewall_output .= ' - until: '.date("F j, Y ",$end_time);
					}
				}

			}

 			$facewall_output .= '</div>';
			$facewall_output .= '<div class="pull-left item-image">';
			$facewall_output .= '<a>';

			
			// now place their picture on the page
			$facewall_output .= facewall_photo_html($row2->image_URL, $row2->display_name, true, $row2->contact_image_URL);
			// cleanup the divs and increment counters

			$facewall_output .= '</a>';
			$facewall_output .= '</div>';
			$facewall_output .= '</div>';
			$facewall_output .= '</div>';
	  		if ( $column < $max_column ){
				$column++;
			} 
			else {
				$column = 1;
		  		$display_row_div = 1;
		  		$facewall_output .= '</div>';  
			}
		// and loop back around
		}


	// finally clean up the remaining divs
	// CSS Grid handles partial rows; do not close a row twice.
	if ($display_row_div !== 1) {
		$facewall_output .= '</div>';
	}
	$facewall_output .= '</br>Number of records ' . $final_index ;
	
	return  $facewall_output;
	
}

function facewall_pad_grid($column, $max_column, $html_output)
{
	while ($column <= $max_column) 	{
		if ($column == 1) 
		{
			break;
		}
		$html_output .= '<div class="span2">';    
	        $html_output .= '<div class="item column-'.$column.'" itemprop="blogPost" itemscope="" itemtype="http://schema.org/BlogPosting">';
		$html_output .= '<div class="page-header">';
		$html_output .= '</div>';
		$html_output .= '</div>';
		$html_output .= '</div>';
		$column++;
	} 
	// and then if we are at the end of the columns reset it to the first div again
	if ($column == 6) {
		$html_output .= '</div>';
	}
	return $html_output;
}


function build_officer_historical_table()
{
	if (!function_exists('facewall_maintenance_transaction_active') || !facewall_maintenance_transaction_active()) {
		return 'build_officer_historical_table - failed (requires maintenance transaction)';
	}
	//gather all the data from the various tables
	global $wpdb;
	$facewall_output = '';
	$query=("SELECT SQL_CALC_FOUND_ROWS 
			civicrm_contact.id  as cv_contact_id , 
			civicrm_contact.display_name  as cv_display_name , 
			civicrm_contact.image_URL as cv_image_URL ,
			membership.membership_type_id  as cv_membership_type_id , 
			membership.join_date  as cv_join_date , 
			membership.start_date  as cv_start_date , 
			membership.end_date  as cv_end_date , 
			civicrm_value_committee_14.post_33  as cv_post ,
			civicrm_option_value.weight as cv_weight
		FROM civicrm_contact        
		  LEFT JOIN civicrm_membership as membership
			ON membership.contact_id = civicrm_contact.id    
		  LEFT JOIN civicrm_value_committee_14 civicrm_value_committee_14 
		      ON civicrm_value_committee_14.entity_id = membership.id 
		  LEFT JOIN civicrm_option_value
		      ON civicrm_option_value.label = civicrm_value_committee_14.post_33 
		      AND civicrm_option_value.option_group_id = 149
		WHERE membership.membership_type_id = 18     
		");

	//  inject the data back into a new table adding a row for every club year of membership.
	$result = $wpdb->get_results($query);
	
	if (!is_array($result) || !$result) {
		error_log('FaceWall: officer source unavailable, preserving existing reporting data.');
		return 'build_officer_historical_table - failed (source unavailable)';
	}
	$result_count = count($result);

	// We are going to store the output in a new table
	// but first we are going to empty it
	if ($wpdb->query('DELETE FROM civicrm_BEC_historical_officer_membership') === false) {
		error_log('Facewall: failed to clear historical officer table: ' . $wpdb->last_error);
		return 'build_officer_historical_table - failed (delete)';
	}

	//now loop through the rults and inject the data into a new table
	// but we need to think about year vs club year.
	// around 1960 the club year shifted from calander year to 1st October - 30 September
	for($result_index = 0; $result_index < $result_count ; $result_index++)
		{
			$row = $result[$result_index];

			$start_date = date("Y",strtotime($row->cv_start_date));
			$end_date = date("Y",strtotime($row->cv_end_date));
		
			for($x = $start_date; $x <= $end_date; $x++)
			{
				$start_time = strtotime($row->cv_start_date);
				//if ( date("d",$start_time) == '01' && date("d",$start_time) == '10' )

				if ( date("Y",$start_time) <= '1960' )
					{
					$mem_club_year = $x;
					}
				else
					{
						// since the club year starts in October the year before we need to skip that year 
						// thereby just recording the major part of the year.
						if (( date("d",$start_time) == '01' ) && ( date("m",$start_time) == '10' ) && ( date("Y",$start_time) == $x )  )
						{
							continue;
						}
						else
						{
							$mem_club_year = $x;
						}
					}
				$insert = $wpdb->prepare(
					"INSERT INTO civicrm_BEC_historical_officer_membership (contact_id, display_name, post, club_year, join_date, start_date, end_date, weight, image_URL) VALUES (%d, %s, %s, %d, %s, %s, %s, %d, %s)",
					(int) $row->cv_contact_id,
					(string) $row->cv_display_name,
					(string) $row->cv_post,
					(int) $mem_club_year,
					(string) $row->cv_join_date,
					(string) $row->cv_start_date,
					(string) $row->cv_end_date,
					(int) $row->cv_weight,
					(string) $row->cv_image_URL
				);
				if ($wpdb->query($insert) === false) {
					error_log('Facewall: officer history insert failed for contact ' . (int) $row->cv_contact_id . ': ' . $wpdb->last_error);
					return 'build_officer_historical_table - failed (insert)';
				}
				
			}
	}
	$msg = "build_officer_historical_table - completed";
	return $msg;
}

//))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))))

function build_BEC_historical_summary_table()
{
	if (!function_exists('facewall_maintenance_transaction_active') || !facewall_maintenance_transaction_active()) {
		return 'build_BEC_historical_summary_table - failed (requires maintenance transaction)';
	}
	//gather all the data from the various tables
	
	global $wpdb;
	//  use mtype to filter out BCA and discounts
	$query=("SELECT cust.facewall_public_84, cust.Membership_Number AS mem_num, c.id AS contact_id, c.first_name, c.nick_name, c.last_name, CONCAT(c.first_name, ' ', c.last_name) AS display_name, c.image_URL, mtype.name, mstatus.name AS m_status, mem.id, mem.source,
		YEAR(mem.start_date) AS 'start', 
		YEAR(mem.end_date) AS 'end'
		from civicrm_contact AS c
		LEFT JOIN custom_value_1_BEC 		AS cust 	ON c.id = cust.entity_id
		LEFT JOIN civicrm_membership 		AS mem  	ON c.id = mem.contact_id AND mem.membership_type_id < 19
		LEFT JOIN civicrm_membership_type 	AS mtype 	ON mem.membership_type_id = mtype.id
		LEFT JOIN civicrm_membership_status 	AS mstatus 	ON mem.status_id = mstatus.id
		WHERE mtype.id IN (1,2,3,4,5,6,7,18) AND status_id <90 AND is_test = 0 AND cust.Membership_Number BETWEEN 1 AND 4999
		ORDER BY cust.Membership_Number DESC, mtype.id
		");
	//  inject the data back into a new table adding a row for every club year of membership.
	$result = $wpdb->get_results($query);
	if (!is_array($result) || !$result) {
		error_log('FaceWall: summary source unavailable, preserving existing reporting data.');
		return 'build_BEC_historical_summary_table - failed (source unavailable)';
	}

	// setup some variables
        $column = 1;
	$max_column = 5;
	$display_row = 1;
	$display_row_div = 1;
	$image_dir = "wp/wp-content/uploads/civicrm/custom/";
	$base_dir = "/home/bertie/public_html/";
	$member_dir = "images/members/";
	$root_dir = "https://bec-cave.org.uk/";
	$thumb_dir = "tn/";
	$thumb_width = 174;
	$thumb_height = 205;
	$result_count = count($result);

	// We are going to store the output in a new table
	// but first we are going to empty it
	if ($wpdb->query('DELETE FROM civicrm_BEC_historical_summary') === false) {
		error_log('Facewall: failed to clear historical summary table: ' . $wpdb->last_error);
		return 'build_BEC_historical_summary_table - failed (delete)';
	}
	//---------------------------------------------------------------------------------
	//now loop through the results and inject the data into a new table
	for($result_index = 0; $result_index < $result_count ; $result_index++)
		{
			$row  = $result[$result_index];
			if ($result_index != $result_count-1){
				$row2 = $result[$result_index+1];
			}
			if (!isset($memberships)){
				$memberships = [];	//set the array variable otherwise it is flagged as unset variable in php logs. 
			}

			// handle the year first
			if ($result_index != $result_count-1){
				if ($row->mem_num == $row2->mem_num){
					$memberships = membership_details($row, $memberships);
					continue;
					} 
			}
			$memberships = membership_details($row, $memberships);
			$memberships = consolidateArray($memberships);


			// Retain a validated photo reference for consenting members EVEN
			// when thumbnail generation cannot run (missing file, permissions
			// or absent full-scale directory). Previously, a failed thumbnail
			// discarded the reference entirely, hiding existing cached images.
			$photo_name = facewall_image_filename($row->image_URL);
			$new_image_URL = (facewall_is_public($row->facewall_public_84) && $photo_name !== '')
				? 'tn_' . $photo_name : '';
			if ($new_image_URL !== '') {
				facewall_create_thumbnail($base_dir, $image_dir, $thumb_dir, $thumb_width, $photo_name);
			}

			// work out the summary info
			$summary = '';
			$i = 0;
			while($i < count($memberships))
				{
				$summary .= $memberships[$i]['title'].' ('. $memberships[$i]['start'].'-'. $memberships[$i]['end'].')'.'<br>';
				$i++;
				}	
			// clear out the membership info or it will flow to the next.
			unset($memberships);
			//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
			/**
			$insert = ("INSERT INTO civicrm_BEC_historical_summary (id, contact_id, display_name, summary, image_URL)
				VALUES ('$row->mem_num','$row->contact_id', '$row->display_name', '$summary', '$new_image_URL')");
			$insert_result = $wpdb->get_results($insert);
			**/

			// Assuming $wpdb is your WordPress database object

			$insert = $wpdb->prepare(
			    "INSERT INTO civicrm_BEC_historical_summary (id, contact_id, display_name, summary, image_URL) VALUES (%d, %d, %s, %s, %s) ON DUPLICATE KEY UPDATE contact_id = VALUES(contact_id), display_name = VALUES(display_name), summary = VALUES(summary), image_URL = VALUES(image_URL)",
			    $row->mem_num,
			    $row->contact_id,
			    $row->display_name,
			    $summary,
			    $new_image_URL
			);

			if ($wpdb->query($insert) === false) {
				error_log('Facewall: historical summary insert failed for membership number ' . (int) $row->mem_num . ': ' . $wpdb->last_error);
				return 'build_BEC_historical_summary_table - failed (insert)';
			}

	}
	$msg = "build_BEC_historical_summary_table - completed";
	return $msg;

}

?>
