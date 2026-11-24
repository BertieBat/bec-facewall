<?php
/**
 * Release C unit regressions: shared CiviCRM queries, historical year
 * expansion, stable summaries, and collision-safe grouping.
 */
error_reporting(E_ALL);
define('ABSPATH', dirname(__DIR__) . '/');

require dirname(__DIR__) . '/includes/calendar.php';
require dirname(__DIR__) . '/includes/member-query.php';
require dirname(__DIR__) . '/includes/history.php';

function release_c_assert($ok, $message) {
    if (!$ok) { throw new RuntimeException($message); }
}

$filters = ['<8', '< 4', '<9', '= 3', '=4', '=2', '= 15', 'IN (3,5,7)', 'IN (4,5,6)'];
foreach ($filters as $filter) {
    release_c_assert(facewall_member_filter_sql($filter) === $filter,
        'Valid BEC eligibility filter changed: ' . $filter);
}
foreach (['', '1 OR 1=1', 'IN (1); DROP TABLE contacts', '< 4 UNION SELECT', '<-1',
    'IN (1, abc)', '=2--', '!= 4', 'IS NULL'] as $bad) {
    release_c_assert(facewall_member_filter_sql($bad) === null,
        'Unsafe or malformed SQL filter accepted: ' . $bad);
}

foreach (['legacy', 'current', 'under30', 'probationary'] as $view) {
    $sql = facewall_member_grid_query($view, '< 4', '<8', '2026-10-03');
    release_c_assert(strpos($sql, 'SELECT ') === 0 &&
        substr_count($sql, 'FROM civicrm') === 0, 'View SQL select unexpectedly changed');
    release_c_assert(strpos($sql, 'from civicrm_contact AS c') !== false,
        'View query does not use the CiviCRM contact table');
    release_c_assert(strpos($sql, 'cust.facewall_public_84 = 1') !== false &&
        strpos($sql, 'c.is_deceased != 1') !== false,
        'View query lost privacy or deceased-member restrictions');
    release_c_assert(substr_count($sql, 'LEFT JOIN civicrm_membership AS mem') === 1 &&
        substr_count($sql, 'LEFT JOIN civicrm_membership_type AS mtype') === 1,
        'Common joins duplicated inside ' . $view);
    if ($view !== 'legacy') {
        release_c_assert(strpos($sql, 'c.image_URL AS contact_image_URL') !== false &&
            strpos($sql, 'hist.summary') !== false,
            'Historical summary or photo fallback missing from ' . $view);
    }
}
$current = facewall_member_grid_query('current', '< 4', '<8');
$life = facewall_member_grid_query('current', '< 4', 'IN (4,5,6)');
$probationary = facewall_member_grid_query('probationary', '< 4', '=2');
$under30 = facewall_member_grid_query('under30', '=99', '=99', '2026-10-03');
release_c_assert(strpos($current, 'mtype.id <8 AND status_id < 4') !== false,
    'Current membership type/status restriction changed');
release_c_assert(strpos($life, 'mtype.id IN (4,5,6)') !== false,
    'Life memberships must retain the same BEC type filter');
release_c_assert(strpos($probationary, 'mtype.id =2') !== false &&
    strpos($probationary, 'ORDER BY mem.join_date, last_name, first_name') !== false,
    'Probationary type or join-date sort changed');
release_c_assert(strpos($under30, 'mtype.id <9 AND status_id <4') !== false &&
    strpos($under30, "DATE_SUB('2026-10-03', INTERVAL 30 YEAR)") !== false &&
    strpos($under30, '=99') === false,
    'Under-30 view failed to retain its fixed type/status and most recent AGM rules');
release_c_assert(strpos(facewall_member_grid_query('current', '1 OR 1=1', '<8'), 'WHERE 1 = 0') !== false,
    'Invalid public member query filter must fail closed');

foreach ([
    ['1959-10-01', 1961, [1959, 1960, 1961]],
    ['1960-10-01', 1962, [1960, 1961, 1962]],
    ['1961-09-30', 1963, [1961, 1962, 1963]],
    ['1961-10-01', 1963, [1962, 1963]],
    ['2024-10-01', 2026, [2025, 2026]],
    ['2024-10-02', 2026, [2024, 2025, 2026]],
    ['2024-01-01', 2024, [2024]],
    ['2025-10-01', 2025, []],
    ['2025-01-01', 2024, []],
] as [$start, $end, $years]) {
    release_c_assert(facewall_history_years($start, $end) === $years,
        'Historic 1960/October year logic changed: ' . $start);
}
release_c_assert(facewall_history_years('not-a-date', 2026) === [],
    'Invalid historical date must not generate years');

// A short local implementation of the existing formatting functions is NOT
// used: load actual historic formatters from the existing view source.
require dirname(__DIR__) . '/officer_grid_year.php';
function history_row($member_number, $contact, $type, $start, $end, $display = 'Example') {
    return (object) [
        'mem_num' => $member_number, 'contact_id' => $contact,
        'name' => $type, 'source' => $type,
        'start' => $start, 'end' => $end,
        'display_name' => $display, 'image_URL' => '',
        'facewall_public_84' => 1,
    ];
}
$one = history_row(101, 600, 'Ordinary', 2019, 2024);
$two = history_row(101, 600, 'Ordinary', 2023, 2026);
$three = history_row(101, 600, 'Committee', 2025, 2026);
$three->source = 'Membership Secretary';
$other = history_row(202, 601, 'Life', 1995, 2026, 'Other Person');
$grouped = facewall_history_summary_groups([$one, $other, $two, $three]);
release_c_assert(count($grouped) === 2 &&
    count($grouped[0]['records']) === 3 &&
    $grouped[0]['last'] === $three,
    'History must group non-adjacent membership rows by contact and number');
release_c_assert(facewall_history_summary_text($grouped[0]['records']) ===
    'Ordinary (2019-2026)<br>Membership Sec (2025-2026)<br>',
    'Overlapping historic membership periods or committee abbreviations changed');
release_c_assert(facewall_history_summary_text($grouped[1]['records']) ===
    'Life (1995-2026)<br>',
    'Other member summaries leaked into the first member');

$collision = history_row(101, 888, 'Life', 2020, 2024, 'Different Person');
try {
    facewall_history_summary_groups([$one, $collision]);
    throw new RuntimeException('Two contacts with one membership number were silently merged');
} catch (RuntimeException $expected) {
    release_c_assert(strpos($expected->getMessage(), 'Membership number assigned') !== false,
        'Historical collision did not raise the expected privacy-safe failure');
}
foreach ([
    history_row(0, 42, 'Ordinary', 2024, 2026),
    history_row(101, 0, 'Ordinary', 2024, 2026)
] as $invalid) {
    try {
        facewall_history_summary_groups([$invalid]);
        throw new RuntimeException('Invalid historical identity was accepted');
    } catch (RuntimeException $expected) {
        release_c_assert(strpos($expected->getMessage(), 'missing identifiers') !== false,
            'Invalid membership identity must be rejected');
    }
}
echo "FaceWall Release C query and historical-processing tests passed.\n";
