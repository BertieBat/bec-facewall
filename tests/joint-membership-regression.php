<?php
/**
 * Joint-members regression checks.
 * Run with: php tests/joint-membership-regression.php
 *
 * Uses fake CiviCRM API3 responses; never connects to the live database.
 */
error_reporting(E_ALL);

$GLOBALS['facewall_test_calls'] = [];

function civicrm_initialize() {
	return true;
}

function civicrm_api3($entity, $action, $params) {
	$GLOBALS['facewall_test_calls'][] = ['entity' => $entity, 'params' => $params];
	if ($entity === 'Membership') {
		$id = (int) ($params['contact_id'] ?? 0);
		if ($id === 44) {
			throw new RuntimeException('Simulated CiviCRM failure');
		}
		if ($id === 45) {
			return ['is_error' => 1, 'values' => null];
		}
		if ($id !== 42) {
			return ['is_error' => 0, 'values' => []];
		}
		if (isset($params['membership_type_ID']) || isset($params['membership_type_id'])) {
			throw new RuntimeException('Joint memberships were incorrectly restricted to Ordinary');
		}
		return [
			'is_error' => 0,
			'values' => [
				1001 => [
					'membership_type_id' => 3,
					'status_id' => 2,
					'start_date' => '2024-10-01',
					'end_date' => '2025-09-30',
				],
				1002 => [
					'membership_type_id' => 17,
					'status_id' => 2,
					'start_date' => '2025-10-01',
					'end_date' => null,
				],
			],
		];
	}
	if ($entity === 'MembershipType') {
		$id = (int) $params['id'];
		return ['values' => [
			['name' => $id === 3 ? 'Joint & <Discount>' : 'Joint Discount'],
		]];
	}
	if ($entity === 'MembershipStatus') {
		return ['values' => [['name' => 'Current']]];
	}
	throw new RuntimeException('Unexpected API request: ' . $entity);
}

require dirname(__DIR__) . '/current_list.php';

function expect_same($expected, $actual, $message) {
	if ($expected !== $actual) {
		throw new RuntimeException($message . ': got ' . var_export($actual, true));
	}
}

expect_same(
	'Joint &amp; &lt;Discount&gt; (24-25) Current<br />Joint Discount (25-?) Current<br />',
	get_membership_info(42, 1),
	'Joint membership details must work on PHP 8 and escape labels'
);
expect_same(
	'Joint &amp; &lt;Discount&gt; (24-25)<br />Joint Discount (25-?)<br />',
	get_membership_info(42, 0),
	'Hidden membership status must be omitted'
);
expect_same('', get_membership_info(43, 1), 'Empty membership history should return a string');
expect_same('', get_membership_info(44, 1), 'CiviCRM exceptions should not leak an array or crash the page');
expect_same('', get_membership_info(45, 1), 'Malformed API results should be handled');
expect_same('', get_membership_info(0, 1), 'Invalid contact should not query CiviCRM');

$typeCalls = 0;
$statusCalls = 0;
foreach ($GLOBALS['facewall_test_calls'] as $call) {
	if ($call['entity'] === 'MembershipType') {
		$typeCalls++;
	}
	if ($call['entity'] === 'MembershipStatus') {
		$statusCalls++;
	}
}
expect_same(2, $typeCalls, 'Type lookups should be cached between repeated renders');
expect_same(1, $statusCalls, 'Status lookups should be cached between repeated renders');

echo "FaceWall Joint Members regression checks passed.\n";
