<?php
/**
 * BEC calendar rules. Keep all computations in WordPress's local calendar
 * date; the annual AGM/committee handover is the first Saturday in October.
 *
 * Membership eligibility and public-consent rules are intentionally not
 * changed by these helpers.
 */
defined('ABSPATH') || exit;

/** Return an ISO date, using the website timezone by default. */
function facewall_calendar_today($today = null) {
    $today = $today === null
        ? (function_exists('wp_date') ? wp_date('Y-m-d') : date('Y-m-d'))
        : (string) $today;
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $today, new DateTimeZone('UTC'));
    if (!$parsed || $parsed->format('Y-m-d') !== $today) {
        throw new InvalidArgumentException('Expected an ISO calendar date (YYYY-MM-DD)');
    }
    return $today;
}

/** The first Saturday of October, independent of server timezone/DST. */
function facewall_first_october_saturday($year) {
    $year = (int) $year;
    if ($year < 1900 || $year > 2100) {
        throw new InvalidArgumentException('Unexpected club calendar year');
    }
    $first = new DateTimeImmutable(sprintf('%04d-10-01', $year), new DateTimeZone('UTC'));
    $days_until_saturday = (6 - (int) $first->format('N') + 7) % 7;
    return $first->modify('+' . $days_until_saturday . ' days')->format('Y-m-d');
}

/** Latest AGM date, including AGM day itself. */
function facewall_most_recent_agm($today = null) {
    $today = facewall_calendar_today($today);
    $year = (int) substr($today, 0, 4);
    $agm = facewall_first_october_saturday($year);
    return $today >= $agm ? $agm : facewall_first_october_saturday($year - 1);
}

/**
 * On the AGM day, the display moves to the next committee year.
 * For instance, 2026-10-02 -> 2026 and 2026-10-03 -> 2027.
 */
function facewall_current_committee_year($today = null) {
    $today = facewall_calendar_today($today);
    $year = (int) substr($today, 0, 4);
    return $today >= facewall_first_october_saturday($year) ? $year + 1 : $year;
}
