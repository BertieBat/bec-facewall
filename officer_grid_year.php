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
 * function build_officer_historical_table($a)
 * function build_BEC_historical_summary_table($a)
 */

global $wpdb;

require_once __DIR__ . '/current_list.php';
require_once __DIR__ . '/includes/calendar.php';
require_once __DIR__ . '/includes/history.php';

function display_facewall_normal_new($fw_title, $fw_mem_status, $fw_mem_type)
{
	global $wpdb;
	$facewall_output = '';

	$query = facewall_member_grid_query('current', $fw_mem_status, $fw_mem_type);

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
				$facewall_output .= facewall_render_grid_row_open($display_row);  
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


	$query = facewall_member_grid_query('under30', '', '', $lastAGM);

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
				$facewall_output .= facewall_render_grid_row_open($display_row);  
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

	$query = facewall_member_grid_query('probationary', $fw_mem_status, $fw_mem_type);

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
				$facewall_output .= facewall_render_grid_row_open($display_row);  
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
				$facewall_output .= facewall_render_grid_row_open($display_row);  
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
	$facewall_output .= facewall_render_grid_row_open($display_row, 'year');  
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
				$facewall_output .= facewall_render_grid_row_open($display_row, 'year');  
				$facewall_output .= '<h2>'.$club_year.'</h2></div>';
				$column = 1;
				$display_row_div = 1;
				$display_row++;
			} 


			//
			// now place the output for each post 
			if ( $display_row_div == 1 ){ 
				$facewall_output .= facewall_render_grid_row_open($display_row);  
				$display_row++;
				$display_row_div++;
			} 
      
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

			
			
			// and then the name
			$officer_heading_tail = '       '.facewall_h($row2->display_name);
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
						$officer_heading_tail .= ' - from: '.date("F j, Y ",$start_time);
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
						$officer_heading_tail .= ' - until: '.date("F j, Y ",$end_time);
					}
				}

			}

 			$facewall_output .= facewall_render_member_card(
                $column,
                facewall_h($display_post),
                facewall_photo_html($row2->image_URL, $row2->display_name, true, $row2->contact_image_URL),
                null,
                $officer_heading_tail,
                true,
                false
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

			$end_date = date("Y", strtotime($row->cv_end_date));
		
			foreach (facewall_history_years($row->cv_start_date, $end_date) as $mem_club_year)
			{
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
    if (!function_exists('facewall_maintenance_transaction_active') ||
        !facewall_maintenance_transaction_active()) {
        return 'build_BEC_historical_summary_table - failed (requires maintenance transaction)';
    }

    global $wpdb;
    // Same source and eligibility rules as before. Identity is the CiviCRM
    // contact ID together with the BEC membership number, not the number alone.
    $query = "SELECT cust.facewall_public_84, cust.Membership_Number AS mem_num,
        c.id AS contact_id, c.first_name, c.nick_name, c.last_name,
        CONCAT(c.first_name, ' ', c.last_name) AS display_name,
        c.image_URL, mtype.name, mstatus.name AS m_status, mem.id, mem.source,
        YEAR(mem.start_date) AS 'start',
        YEAR(mem.end_date) AS 'end'
        from civicrm_contact AS c
        LEFT JOIN custom_value_1_BEC AS cust ON c.id = cust.entity_id
        LEFT JOIN civicrm_membership AS mem ON c.id = mem.contact_id
            AND mem.membership_type_id < 19
        LEFT JOIN civicrm_membership_type AS mtype ON mem.membership_type_id = mtype.id
        LEFT JOIN civicrm_membership_status AS mstatus ON mem.status_id = mstatus.id
        WHERE mtype.id IN (1,2,3,4,5,6,7,18)
          AND status_id <90 AND is_test = 0
          AND cust.Membership_Number BETWEEN 1 AND 4999
        ORDER BY cust.Membership_Number DESC, c.id, mtype.id";

    $rows = $wpdb->get_results($query);
    if (!is_array($rows) || !$rows) {
        error_log('FaceWall: historical summary source unavailable; existing history preserved.');
        return 'build_BEC_historical_summary_table - failed (source unavailable)';
    }

    // Verify every historical summary key BEFORE clearing committed rows.
    // An ambiguous number must fail rather than overwrite another contact.
    try {
        $groups = facewall_history_summary_groups($rows);
    } catch (Throwable $error) {
        error_log('FaceWall: historical summary has ambiguous membership identifiers.');
        return 'build_BEC_historical_summary_table - failed (membership identifiers)';
    }
    if (!$groups) {
        return 'build_BEC_historical_summary_table - failed (source unavailable)';
    }

    if ($wpdb->query('DELETE FROM civicrm_BEC_historical_summary') === false) {
        error_log('FaceWall: failed to clear historical summary table.');
        return 'build_BEC_historical_summary_table - failed (delete)';
    }

    // Keep thumbnail generation as a maintenance-only action. Failures do
    // not erase a consenting member's validated historical photo reference.
    $base_dir = '';
    $image_dir = '';
    $thumb_dir = '';
    $thumb_width = 174;
    foreach ($groups as $group) {
        $row = $group['last'];
        $summary = facewall_history_summary_text($group['records']);

        $photo_name = facewall_image_filename($row->image_URL);
        $image_reference = (facewall_is_public($row->facewall_public_84) && $photo_name !== '')
            ? 'tn_' . $photo_name : '';
        if ($image_reference !== '') {
            facewall_create_thumbnail($base_dir, $image_dir, $thumb_dir, $thumb_width, $photo_name);
        }

        $insert = $wpdb->prepare(
            "INSERT INTO civicrm_BEC_historical_summary
                (id, contact_id, display_name, summary, image_URL)
             VALUES (%d, %d, %s, %s, %s)
             ON DUPLICATE KEY UPDATE
                contact_id = VALUES(contact_id),
                display_name = VALUES(display_name),
                summary = VALUES(summary),
                image_URL = VALUES(image_URL)",
            (int) $row->mem_num,
            (int) $row->contact_id,
            (string) $row->display_name,
            $summary,
            $image_reference
        );
        if ($wpdb->query($insert) === false) {
            error_log('FaceWall: historical summary insert failed.');
            return 'build_BEC_historical_summary_table - failed (insert)';
        }
    }

    return 'build_BEC_historical_summary_table - completed';
}

?>
