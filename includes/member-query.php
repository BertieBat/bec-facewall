<?php
/**
 * Shared read-only CiviCRM source query for FaceWall's individual member
 * grids. Membership type/status filters remain exactly as selected by each
 * shortcode; this does not change the site's existing BEC eligibility rules.
 */
defined('ABSPATH') || exit;

/**
 * Only allow simple numeric comparisons and numeric IN lists. Historically
 * these filters were concatenated into SQL; accepting arbitrary text would
 * permit SQL injection through unexpected callers.
 */
function facewall_member_filter_sql($filter) {
    $filter = trim((string) $filter);
    if (preg_match('/^(?:<=|>=|=|<|>)\s*[0-9]+$/D', $filter) ||
        preg_match('/^IN\s*\(\s*[0-9]+(?:\s*,\s*[0-9]+)*\s*\)$/iD', $filter)) {
        return $filter;
    }
    return null;
}

/**
 * Build the existing membership views from one stable set of joins.
 *
 * $kind: legacy / current / under30 / probationary
 * All values in $fields are fixed SQL literals owned by this plugin.
 */
function facewall_member_grid_query($kind, $status_filter = '', $type_filter = '', $agm_date = null) {
    $fields = [
        'legacy' =>
            'cust.Membership_Number, c.first_name, c.nick_name, c.last_name, CONCAT(c.last_name, c.first_name), c.image_URL, mtype.name, mstatus.name AS m_status, mem.id, cust.facewall_public_84',
        'current' =>
            'cust.Membership_Number, c.first_name, c.nick_name, c.last_name, CONCAT(c.last_name, c.first_name), mtype.name, mstatus.name AS m_status, mem.id, hist.image_URL, c.image_URL AS contact_image_URL, hist.summary, cust.facewall_public_84',
        'under30' =>
            "cust.facewall_public_84, cust.Membership_Number, c.first_name, c.nick_name, c.last_name, c.birth_date, mtype.name, mstatus.name AS m_status, mem.id, hist.image_URL, c.image_URL AS contact_image_URL, hist.summary, DATE_FORMAT(FROM_DAYS(DATEDIFF(NOW(),c.birth_date)), '%Y') + 0 AS age",
        'probationary' =>
            'cust.facewall_public_84, cust.Membership_Number, c.first_name, c.nick_name, c.last_name, CONCAT(c.last_name, c.first_name), mtype.name, mstatus.name AS m_status, mem.id, mem.join_date, hist.image_URL, c.image_URL AS contact_image_URL, hist.summary',
    ];
    if (!isset($fields[$kind])) {
        return '';
    }

    $sql = 'SELECT ' . $fields[$kind]
        . "\n\t\tfrom civicrm_contact AS c"
        . "\n\t\tLEFT JOIN custom_value_1_BEC as cust ON c.id = cust.entity_id";
    if ($kind !== 'legacy') {
        $sql .= "\n\t\tLEFT JOIN civicrm_BEC_historical_summary as hist ON c.id = hist.contact_id";
    }
    $sql .= "\n\t\tLEFT JOIN civicrm_membership AS mem ON c.id = mem.contact_id AND mem.membership_type_id < 18"
        . "\n\t\tLEFT JOIN civicrm_membership_type AS mtype ON mem.membership_type_id = mtype.id"
        . "\n\t\tLEFT JOIN civicrm_membership_status AS mstatus ON mem.status_id = mstatus.id";

    if ($kind === 'under30') {
        // The under-30 view intentionally ignores the caller's type/status
        // strings and uses BEC's fixed <9 / <4 rules.
        $agm_date = $agm_date === null ? facewall_most_recent_agm() : facewall_calendar_today($agm_date);
        $sql .= "\n\t\tWHERE mtype.id <9 AND status_id <4"
            . "\n\t\t\tAND c.is_deceased != 1 AND cust.facewall_public_84 = 1"
            . "\n\t\t\tAND c.birth_date > DATE_SUB('" . $agm_date . "', INTERVAL 30 YEAR)"
            . "\n\t\tORDER BY last_name, first_name";
    } else {
        $type = facewall_member_filter_sql($type_filter);
        $status = facewall_member_filter_sql($status_filter);
        if ($type === null || $status === null) {
            // Invalid callers cannot inject SQL or return a broader list.
            return $sql . "\n\t\tWHERE 1 = 0";
        }
        $sql .= "\n\t\tWHERE mtype.id " . $type . ' AND status_id ' . $status
            . ' AND c.is_deceased != 1 AND cust.facewall_public_84 = 1'
            . ($kind === 'probationary'
                ? "\n\t\tORDER BY mem.join_date, last_name, first_name"
                : "\n\t\tORDER BY last_name, first_name");
    }
    return $sql;
}
