<?php
// Public views require WordPress and the shared privacy helpers.
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

global $wpdb;

function display_facewall_normal($fw_title, $fw_mem_status, $fw_mem_type)
{
	global $wpdb;
	$facewall_output = '';

	$query=("SELECT cust.Membership_Number, c.first_name, c.nick_name, c.last_name, CONCAT(c.last_name, c.first_name), c.image_URL, mtype.name, mstatus.name AS m_status, mem.id, cust.facewall_public_84
		from civicrm_contact AS c
		LEFT JOIN custom_value_1_BEC as cust ON c.id = cust.entity_id
		LEFT JOIN civicrm_membership AS mem  ON c.id = mem.contact_id AND mem.membership_type_id < 18
		LEFT JOIN civicrm_membership_type AS mtype ON  mem.membership_type_id = mtype.id
		LEFT JOIN civicrm_membership_status AS mstatus ON mem.status_id = mstatus.id
		WHERE mtype.id ".$fw_mem_type." AND status_id ".$fw_mem_status." AND c.is_deceased != 1 AND cust.facewall_public_84 = 1
		ORDER BY last_name, first_name");

	// Prepare the query
	$result = $wpdb->get_results($query);

	if (!$result) 
	{
		$facewall_output .= '<h2>'.$fw_title.'</h2>';
		$facewall_output .= 'no members found matching this search';  
		return $facewall_output;
	}

        $column = 1;
	$max_column = 5;
	$row = 1;
	$row_div = 1;
	//$image_dir = "media/civicrm/custom/";
	$image_dir = "wp/wp-content/uploads/civicrm/custom/";
	$base_dir = "/home/bertie/public_html/";
	$member_dir = "images/members/";
	$root_dir = "https://bec-cave.org.uk/";
	$thumb_dir = "tn/";
	$thumb_width = 174;
	$thumb_height = 205;
	$result_index = 0 ;
	$result_count = count($result);

	//ob_start();
	$facewall_output .= '<h2>'.$fw_title.' - '.$result_count.' members</h2>';

	for($result_index = 0; $result_index < $result_count ; $result_index++)
	{
		$row = $result[$result_index];
		if ( $row_div == 1 ){ 
			$facewall_output .= '<div class="items-row cols-4 row-0 row-fluid clearfix bec_members_row">';  
			$row_div++;
			} 
      
		$facewall_output .= '<div class="span2">';    
	        $facewall_output .= '<div class="item column-'.$column.'" itemprop="blogPost" itemscope="" itemtype="http://schema.org/BlogPosting">';
		$facewall_output .= '<div class="page-header">';
		$facewall_output .= '<h5 itemprop="name" max-width=250px>'.facewall_h($row->first_name).' '.facewall_h($row->last_name).'</h5>';
 		$facewall_output .= '</div>';
		$facewall_output .= '<div class="pull-left item-image">';
		$facewall_output .= '<a>';
		$facewall_output .= facewall_photo_html($row->image_URL, $row->first_name.' '.$row->last_name, facewall_is_public($row->facewall_public_84));
		$facewall_output .= '</a>';
		$facewall_output .= '</div>';
		$facewall_output .= '</div>';
		$facewall_output .= '</div>';
	  	if ( $column < $max_column ){
			$column++;
			} 
		else {
			$column = 1;
		  	$row_div = 1;
		  	$facewall_output .= '</div>';  
			}
	}
	// Close the last partial grid row; CSS Grid handles empty cells.
	if ($row_div !== 1) {
		$facewall_output .= '</div>';
	}
	$facewall_output .= 'Number of members ' . $result_index ;
	//return $result;
	
	return  $facewall_output;
	
}



function display_facewall_joint_invalid($fw_title, $fw_mem_status, $fw_mem_type)
{
	// This function will identify a list of joint members which are invalid
	// for instance only one is a current member
	// 
	global $wpdb;
	$joint_output = '';

	$query=("SELECT 
			`a`.`id` 				AS `id`, 
			`near_contact_id_1`.`display_name` 	AS civicrm_contact_sort_name_a, 
			`far_contact_id_2`.`display_name` 	AS civicrm_contact_b_sort_name_b,
			`a`.`near_contact_id` 			AS `near_contact_id`,
			`a`.`far_contact_id`			AS `far_contact_id`,
			`a`.`relationship_id` 			AS `relationship_id`,
			`a`.`orientation` 			AS `orientation`,
			`a`.`start_date`			AS `start_date`,
			`a`.`end_date`				AS `end_date`,
			`a`.`is_active`				AS `is_active`,
			`a`.`relationship_type_id`		AS relationship_type_id,
			near_contact_id_1.image_URL 		AS civicrm_contact_image_URL_a,
			far_contact_id_2.image_URL 		AS civicrm_contact_b_image_URL_b
	
		FROM civicrm_relationship_cache AS a
		LEFT JOIN civicrm_contact 	AS near_contact_id_1 ON a.near_contact_id =  near_contact_id_1.id
		LEFT JOIN civicrm_contact 	AS far_contact_id_2  ON a.far_contact_id  =  far_contact_id_2.id
		INNER JOIN custom_value_1_BEC AS near_priv ON near_priv.entity_id = a.near_contact_id AND near_priv.facewall_public_84 = 1
		INNER JOIN custom_value_1_BEC AS far_priv ON far_priv.entity_id = a.far_contact_id AND far_priv.facewall_public_84 = 1

		WHERE ( a.relationship_type_id = 8 
			AND orientation = 'a_b' 
			AND is_active = 1
			AND (
				(end_date >= CURDATE())
		                	OR
				(end_date IS NULL)
			    )
			AND (
                            -- check if the at least one of the contacts is a current member 
			    
			    (
			    	(	
					a.near_contact_id IN (
					SELECT mem.contact_id
					FROM civicrm_membership AS mem
					WHERE mem.contact_id = a.near_contact_id
						AND mem.membership_type_id IN (1,2,3,4,5,6,7)
						AND mem.status_id = 2
						AND mem.is_override = 0
					)
       
				AND 	
					a.far_contact_id NOT IN (
					SELECT mem.contact_id
					FROM civicrm_membership AS mem
					WHERE mem.contact_id = a.far_contact_id
						AND mem.membership_type_id IN (1,2,3,4,5,6,7)
						AND mem.status_id = 2
						AND mem.is_override = 0
					)
				)
			    )
			OR
			    (

			    	(	
					a.near_contact_id NOT IN (
					SELECT mem.contact_id
					FROM civicrm_membership AS mem
					WHERE mem.contact_id = a.near_contact_id
						AND mem.membership_type_id IN (1,2,3,4,5,6,7)
						AND mem.status_id = 2
						AND mem.is_override = 0
					)
       
				AND 	
					a.far_contact_id IN (
					SELECT mem.contact_id
					FROM civicrm_membership AS mem
					WHERE mem.contact_id = a.far_contact_id
						AND mem.membership_type_id IN (1,2,3,4,5,6,7)
						AND mem.status_id = 2
						AND mem.is_override = 0
					)
				)
			    )
			)
			AND (
			    -- check that at least one of them has active joint membership
			    (
			    	(	
					a.near_contact_id IN (
					SELECT mem.contact_id
					FROM civicrm_membership AS mem
					WHERE mem.contact_id = a.near_contact_id
						AND mem.membership_type_id = 17
						AND mem.status_id = 2
						AND mem.is_override = 0
					)
       
				AND 	
					a.far_contact_id NOT IN (
					SELECT mem.contact_id
					FROM civicrm_membership AS mem
					WHERE mem.contact_id = a.far_contact_id
						AND mem.membership_type_id = 17
						AND mem.status_id = 2
						AND mem.is_override = 0
					)
				)
			    )
			OR
			    (

			    	(	
					a.near_contact_id NOT IN (
					SELECT mem.contact_id
					FROM civicrm_membership AS mem
					WHERE mem.contact_id = a.near_contact_id
						AND mem.membership_type_id = 17 
						AND mem.status_id = 2
						AND mem.is_override = 0
					)
       
				AND 	
					a.far_contact_id IN (
					SELECT mem.contact_id
					FROM civicrm_membership AS mem
					WHERE mem.contact_id = a.far_contact_id
						AND mem.membership_type_id = 17
						AND mem.status_id = 2
						AND mem.is_override = 0
					)
				)
			    )
			)

		)");  

	// Prepare the query
	$result = $wpdb->get_results($query);

	if (!$result) 
	{
		$joint_output .= '<h2>'.$fw_title.'</h2>';
		$joint_output .= 'no members found matching this search';  
		return $joint_output;
	}

        $column = 1;
	$max_column = 2;
	$row = 1;
	$row_div = 1;
	$image_dir = "wp/wp-content/uploads/civicrm/custom/";
	$base_dir = "/home/bertie/public_html/";
	$member_dir = "images/members/";
	$root_dir = "https://bec-cave.org.uk/";
	$thumb_dir = "tn/";
	$thumb_width = 174;
	$thumb_height = 205;
	$result_index = 0 ;
	$result_count = count($result);
	$member_count = 2 * $result_count;

	$joint_output .= '<h2>'.$fw_title.' - '.$member_count.' members</h2>';

	for($result_index = 0; $result_index < $result_count ; $result_index++)
	{
		$row = $result[$result_index];
		if ( $row_div == 1 ){ 
			$joint_output .= '<div class="items-row cols-4 row-0 row-fluid clearfix bec_members_row">';  
			$row_div++;
			} 


		$joint_output .= '<div class="span2">';    
	        $joint_output .= '<div class="item column-'.$column.'" itemprop="blogPost" itemscope="" itemtype="http://schema.org/BlogPosting">';
		$joint_output .= '<div class="page-header">';
		$joint_output .= '<h5 itemprop="name" max-width=250px>'.facewall_h($row->civicrm_contact_sort_name_a).'</h5>';
 		$joint_output .= '</div>';
		$joint_output .= '<div class="pull-left item-image">';

		$memberships = get_membership_info($row->near_contact_id, 1);
		$joint_output .= '<div class="fw_middle">';
    		$joint_output .= '<div class="text">'.$memberships.'</div>';
		$joint_output .= '</div>';

		$joint_output .= facewall_photo_html($row->civicrm_contact_image_URL_a, $row->civicrm_contact_sort_name_a, true);
		$joint_output .= '</div>';
		$joint_output .= '</div>';
		$joint_output .= '</div>';
		//----------------------
		$joint_output .= '<div class="span2">';    
	        $joint_output .= '<div class="item column-'.$column.'" itemprop="blogPost" itemscope="" itemtype="http://schema.org/BlogPosting">';
		$joint_output .= '<div class="page-header">';
		$joint_output .= '<h5 itemprop="name" max-width=250px>'.facewall_h($row->civicrm_contact_b_sort_name_b).'</h5>';
 		$joint_output .= '</div>';
		$joint_output .= '<div class="pull-left item-image">';

		$memberships = get_membership_info($row->far_contact_id, 1);
		$joint_output .= '<div class="fw_middle">';
    		$joint_output .= '<div class="text">'.$memberships.'</div>';
		$joint_output .= '</div>';

		$joint_output .= facewall_photo_html($row->civicrm_contact_b_image_URL_b, $row->civicrm_contact_b_sort_name_b, true);
		$joint_output .= '</div>';
		$joint_output .= '</div>';
		$joint_output .= '</div>';

		$column++;
	  	$row_div = 1;
	  	$joint_output .= '</div>';  
			
	}
	$joint_output .= 'Number of members ' . $member_count ;
	return $joint_output;

}

/**
 * Show a contact's membership history alongside their entry on the joint
 * members page. Always return HTML text, including when the API is unavailable.
 */
function get_membership_info($contact_id, $show_status) {
	$contact_id = (int) $contact_id;
	if ($contact_id <= 0 || !function_exists('civicrm_initialize') || !function_exists('civicrm_api3')) {
		return '';
	}

	// Reuse labels across the pair cards rather than querying identical types
	// and statuses repeatedly.
	static $type_names = [];
	static $status_names = [];

	try {
		civicrm_initialize();
		// Do not restrict to type 1 (Ordinary): joint members also have other
		// membership types and possibly a separate joint discount.
		$result = civicrm_api3('Membership', 'get', [
			'contact_id' => $contact_id,
			'options' => ['limit' => 0],
		]);

		if (!is_array($result) || !empty($result['is_error'])
			|| !isset($result['values']) || !is_array($result['values'])) {
			error_log('Facewall: invalid Membership API result for contact ' . $contact_id);
			return '';
		}

		$details = [];
		foreach ($result['values'] as $membership) {
			if (!is_array($membership)) {
				continue;
			}

			$type_id = (int) ($membership['membership_type_id'] ?? 0);
			$status_id = (int) ($membership['status_id'] ?? 0);
			if ($type_id <= 0) {
				continue;
			}

			if (!array_key_exists($type_id, $type_names)) {
				$type_result = civicrm_api3('MembershipType', 'get', [
					'return' => ['name'],
					'id' => $type_id,
					'sequential' => 1,
				]);
				$type_names[$type_id] = (string) ($type_result['values'][0]['name'] ?? '');
			}

			$type_name = $type_names[$type_id];
			if ($type_name === '') {
				continue;
			}

			$start_timestamp = !empty($membership['start_date'])
				? strtotime($membership['start_date']) : false;
			$end_timestamp = !empty($membership['end_date'])
				? strtotime($membership['end_date']) : false;
			$start_year = $start_timestamp !== false ? date('y', $start_timestamp) : '?';
			$end_year = $end_timestamp !== false ? date('y', $end_timestamp) : '?';

			$line = htmlspecialchars($type_name, ENT_QUOTES, 'UTF-8')
				. ' (' . $start_year . '-' . $end_year . ')';

			if ($show_status && $status_id > 0) {
				if (!array_key_exists($status_id, $status_names)) {
					$status_result = civicrm_api3('MembershipStatus', 'get', [
						'return' => ['name'],
						'id' => $status_id,
						'sequential' => 1,
					]);
					$status_names[$status_id] = (string) ($status_result['values'][0]['name'] ?? '');
				}
				if ($status_names[$status_id] !== '') {
					$line .= ' ' . htmlspecialchars($status_names[$status_id], ENT_QUOTES, 'UTF-8');
				}
			}

			$details[] = $line;
		}
		return $details ? implode('<br />', $details) . '<br />' : '';
	} catch (\Throwable $e) {
		error_log('Facewall: membership lookup failed for contact ' . $contact_id . ': ' . $e->getMessage());
		return '';
	}
}

// This is the good working copy of the code - might need some tweaking
function display_facewall_joint($fw_title, $fw_mem_status, $fw_mem_type)
{
	global $wpdb;
	$joint_output = '';

	$query=("SELECT 
			`a`.`id` 				AS `id`, 
			`near_contact_id_1`.`display_name` 	AS civicrm_contact_sort_name_a, 
			`far_contact_id_2`.`display_name` 	AS civicrm_contact_b_sort_name_b,
			`a`.`near_contact_id` 			AS `near_contact_id`,
			`a`.`far_contact_id`			AS `far_contact_id`,
			`a`.`relationship_id` 			AS `relationship_id`,
			`a`.`orientation` 			AS `orientation`,
			near_contact_id_1.image_URL 		AS civicrm_contact_image_URL_a,
			far_contact_id_2.image_URL 		AS civicrm_contact_b_image_URL_b,
			near_hist.summary			AS near_summary,
			far_hist.summary			AS far_summary
	
		FROM civicrm_relationship_cache AS a
		LEFT JOIN civicrm_BEC_historical_summary 	AS near_hist 		ON a.near_contact_id  = near_hist.contact_id
		LEFT JOIN civicrm_BEC_historical_summary 	AS far_hist  		ON a.far_contact_id  = far_hist.contact_id
		LEFT JOIN civicrm_contact 			AS near_contact_id_1 	ON a.near_contact_id =  near_contact_id_1.id
		LEFT JOIN civicrm_contact 			AS far_contact_id_2  	ON a.far_contact_id  =  far_contact_id_2.id
		INNER JOIN custom_value_1_BEC AS near_priv ON near_priv.entity_id = a.near_contact_id AND near_priv.facewall_public_84 = 1
		INNER JOIN custom_value_1_BEC AS far_priv ON far_priv.entity_id = a.far_contact_id AND far_priv.facewall_public_84 = 1

		WHERE ( relationship_type_id = 8 
			AND orientation = 'a_b' 
			AND is_active = 1

			AND a.near_contact_id IN (
				SELECT mem.contact_id
				FROM civicrm_membership AS mem
				WHERE mem.contact_id = a.near_contact_id
					AND mem.membership_type_id = 1
					AND mem.status_id IN (1,2,3)
					AND mem.is_override = 0
				)
       
		       	AND a.far_contact_id IN (
				SELECT mem.contact_id
				FROM civicrm_membership AS mem
				WHERE mem.contact_id = a.far_contact_id
					AND mem.membership_type_id = 1
					AND mem.status_id IN (1,2,3)
				AND mem.is_override = 0
				)
		)");  

	// Prepare the query
	$result = $wpdb->get_results($query);

	if (!$result) 
	{
		$joint_output .= '<h2>'.$fw_title.'</h2>';
		$joint_output .= 'no members found matching this search';  
		return $joint_output;
	}

        $column = 1;
	$max_column = 2;
	$row = 1;
	$row_div = 1;
	//$image_dir = "media/civicrm/custom/";
	$image_dir = "wp/wp-content/uploads/civicrm/custom/";
	$base_dir = "/home/bertie/public_html/";
	$member_dir = "images/members/";
	$root_dir = "https://bec-cave.org.uk/";
	$thumb_dir = "tn/";
	$thumb_width = 174;
	$thumb_height = 205;
	$result_index = 0 ;
	$result_count = count($result);
	$member_count = 2 * $result_count;

	$joint_output .= '<h2>'.$fw_title.' - '.$member_count.' members</h2>';

	for($result_index = 0; $result_index < $result_count ; $result_index++)
	{
		$row = $result[$result_index];
		if ( $row_div == 1 ){ 
			$joint_output .= '<div class="items-row cols-4 row-0 row-fluid clearfix bec_members_row">';  
			$row_div++;
			} 
      
		$joint_output .= '<div class="span2">';    
	        $joint_output .= '<div class="item column-'.$column.'" itemprop="blogPost" itemscope="" itemtype="http://schema.org/BlogPosting">';
		$joint_output .= '<div class="page-header">';
		//$joint_output .= '<h5 itemprop="name" max-width=250px>'.$row->first_name.' '.$row->last_name.'</h5>';
		$joint_output .= '<h5 itemprop="name" max-width=250px>'.facewall_h($row->civicrm_contact_sort_name_a).'</h5>';
 		$joint_output .= '</div>';
		$joint_output .= '<div class="pull-left item-image">';

		//$memberships = get_membership_info($row->near_contact_id, 0);
		$joint_output .= '<div class="fw_middle">';
		//$joint_output .= '<div class="text">'.$memberships.'</div>';
    		$joint_output .= '<div class="text">'.facewall_summary_html($row->near_summary).'</div>';
		$joint_output .= '</div>';


		//$joint_output .= '<a>';
		$joint_output .= facewall_photo_html($row->civicrm_contact_image_URL_a, $row->civicrm_contact_sort_name_a, true);
		$joint_output .= '</div>';
		$joint_output .= '</div>';
		$joint_output .= '</div>';
		//----------------------
		$joint_output .= '<div class="span2">';    
	        $joint_output .= '<div class="item column-'.$column.'" itemprop="blogPost" itemscope="" itemtype="http://schema.org/BlogPosting">';
		$joint_output .= '<div class="page-header">';
		$joint_output .= '<h5 itemprop="name" max-width=250px>'.facewall_h($row->civicrm_contact_b_sort_name_b).'</h5>';
 		$joint_output .= '</div>';
		$joint_output .= '<div class="pull-left item-image">';

		//$memberships = get_membership_info($row->far_contact_id, 0);
		$joint_output .= '<div class="fw_middle">';
    		$joint_output .= '<div class="text">'.facewall_summary_html($row->far_summary).'</div>';
		$joint_output .= '</div>';

		//$joint_output .= '<a>';
		$joint_output .= facewall_photo_html($row->civicrm_contact_b_image_URL_b, $row->civicrm_contact_b_sort_name_b, true);
		$joint_output .= '</div>';
		$joint_output .= '</div>';
		$joint_output .= '</div>';

		$column++;
	  	$row_div = 1;
	  	$joint_output .= '</div>';  
			
	}
	$joint_output .= 'Number of members ' . $member_count ;
	return $joint_output;

}


/**
 * Build cached thumbnails only from bounded, validated local CiviCRM images.
 * Called by the maintenance job, never by public shortcode rendering.
 */
function facewall_create_thumbnail($base_dir, $image_dir, $thumb_dir, $thumb_width, $image)
{
    if (!defined('WP_CONTENT_DIR')) {
        return false;
    }
    $filename = facewall_image_filename($image);
    if ($filename === '') {
        return false;
    }
    $source_dir = realpath(WP_CONTENT_DIR . '/uploads/civicrm/custom');
    if (!$source_dir) {
        return false;
    }
    $source = realpath($source_dir . '/' . $filename);
    if (!$source || strpos($source, $source_dir . DIRECTORY_SEPARATOR) !== 0 ||
        !is_file($source) || filesize($source) > 15 * 1024 * 1024) {
        return false;
    }
    $info = @getimagesize($source);
    if (!$info || $info[0] < 1 || $info[1] < 1 ||
        $info[0] * $info[1] > 24000000 ||
        !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
        return false;
    }
    $small_dir = $source_dir . '/tn/174';
    $full_dir = $source_dir . '/tn/full_scale';
    if (!is_dir($small_dir) || !is_dir($full_dir)) {
        return false; // Directories are managed by CiviCRM/hosting, not web requests.
    }
    $small_path = $small_dir . '/tn_' . $filename;
    $full_path = $full_dir . '/tn_' . $filename;
    if (is_file($small_path) && is_file($full_path)) {
        return true;
    }
    $src = $info[2] === IMAGETYPE_JPEG ? @imagecreatefromjpeg($source) : @imagecreatefrompng($source);
    if (!$src) {
        return false;
    }
    $source_w = imagesx($src);
    $source_h = imagesy($src);
    $width = 174;
    $height = 205;
    // Centre-crop without stretching the subject.
    $crop_w = min($source_w, (int) max(1, round($source_h * $width / $height)));
    $crop_h = min($source_h, (int) max(1, round($source_w * $height / $width)));
    $crop_x = (int) floor(($source_w - $crop_w) / 2);
    $crop_y = (int) floor(($source_h - $crop_h) / 2);
    $small = imagecreatetruecolor($width, $height);
    $full = imagecreatetruecolor($crop_w, $crop_h);
    if (!$small || !$full) {
        if ($small) { imagedestroy($small); }
        if ($full) { imagedestroy($full); }
        imagedestroy($src);
        return false;
    }
    imagecopyresampled($small, $src, 0, 0, $crop_x, $crop_y, $width, $height, $crop_w, $crop_h);
    imagecopyresampled($full, $src, 0, 0, $crop_x, $crop_y, $crop_w, $crop_h, $crop_w, $crop_h);
    $write = $info[2] === IMAGETYPE_PNG ? 'imagepng' : 'imagejpeg';
    $saved_small = is_file($small_path) || $write($small, $small_path);
    $saved_full = is_file($full_path) || $write($full, $full_path);
    imagedestroy($small);
    imagedestroy($full);
    imagedestroy($src);
    return $saved_small && $saved_full;
}
