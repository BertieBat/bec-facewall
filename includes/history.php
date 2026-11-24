<?php
/**
 * BEC's historical year expansion and membership-summary grouping.
 *
 * Pure functions: no SQL, no member data writes. We deliberately preserve
 * the existing 1960 calendar-year / October 1 rules, pending a separate
 * historical-policy decision by BEC.
 */
defined('ABSPATH') || exit;

/**
 * Years included in an historical membership or officer period.
 * The caller determines the final year (today for open memberships).
 * An October 1 start after 1960 skips the short first calendar year,
 * exactly as the existing reporting jobs have historically done.
 */
function facewall_history_years($start_date, $end_year) {
    $start_time = strtotime((string) $start_date);
    if ($start_time === false) {
        return [];
    }
    $start_year = (int) date('Y', $start_time);
    $end_year = (int) $end_year;
    if ($end_year < $start_year) {
        return [];
    }
    // Avoid unbounded historical loops on malformed dates.
    if ($start_year < 1800 || $end_year > 2200) {
        return [];
    }
    $omit_initial_october_year = $start_year > 1960
        && date('m-d', $start_time) === '10-01';

    $years = [];
    for ($year = $start_year; $year <= $end_year; $year++) {
        if ($omit_initial_october_year && $year === $start_year) {
            continue;
        }
        $years[] = $year;
    }
    return $years;
}

/**
 * Merge source rows by BOTH membership number and contact ID. The historical
 * summary table uses membership number as a primary key; two contacts with
 * that number cannot be represented safely. Throw before deleting existing
 * reporting rows instead of silently overwriting the first contact.
 *
 * Keep source order within a group, since the last source row historically
 * supplies the display name and photograph for that membership number.
 */
function facewall_history_summary_groups($rows) {
    if (!is_array($rows)) {
        throw new InvalidArgumentException('Historical source result is not an array');
    }
    $owners = [];
    $groups = [];
    foreach ($rows as $row) {
        $number = (int) ($row->mem_num ?? 0);
        $contact = (int) ($row->contact_id ?? 0);
        if ($number < 1 || $contact < 1) {
            throw new RuntimeException('Historical source contains missing identifiers');
        }
        if (isset($owners[$number]) && $owners[$number] !== $contact) {
            throw new RuntimeException('Membership number assigned to more than one contact');
        }
        $owners[$number] = $contact;
        $key = $number . ':' . $contact;
        if (!isset($groups[$key])) {
            $groups[$key] = ['records' => [], 'last' => null];
        }
        $groups[$key]['records'][] = $row;
        $groups[$key]['last'] = $row;
    }
    return array_values($groups);
}

/** Preserve historical summary spelling, ordering, and break tags. */
function facewall_history_summary_text($records) {
    $memberships = [];
    foreach ($records as $row) {
        $memberships = membership_details($row, $memberships);
    }
    $memberships = consolidateArray($memberships);
    $summary = '';
    foreach ($memberships as $membership) {
        $summary .= $membership['title'] . ' (' . $membership['start']
            . '-' . $membership['end'] . ')<br>';
    }
    return $summary;
}
