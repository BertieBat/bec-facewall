<?php
/**
 * Regression contract for joint public-grid eligibility.
 * Run: php tests/joint-pair-eligibility.php
 */
$source=file_get_contents(dirname(__DIR__).'/current_list.php');
$start=strpos($source,'function display_facewall_joint($fw_title');
if ($start === FALSE) throw new RuntimeException('Public joint view missing');
$view=substr($source,$start);
foreach ([
  'a.relationship_type_id=8',
  "LOWER(TRIM(rt.label_a_b))='partner of'",
  "LOWER(TRIM(rt.label_b_a))='partner of'",
  "a.orientation='a_b'",
  'a.is_active=1',
  'near_priv.facewall_public_84=1',
  'far_priv.facewall_public_84=1',
  'near_contact_id_1.is_deceased=0',
  'far_contact_id_2.is_deceased=0',
  'm.membership_type_id IN (1,2,3,4,5,6,7)',
  's.is_current_member=1',
  'm.is_test=0',
  'm.is_override=0',
  "s.name='Grace'",
  'm.membership_type_id=17'
] as $part) {
  if (strpos($view,$part)===FALSE) throw new RuntimeException('Missing joint-pair eligibility condition: '.$part);
}
if (substr_count($view,'m.membership_type_id=17') < 2) {
  throw new RuntimeException('Both partners must hold Current Joint Discounts');
}
if (substr_count($view,'EXISTS (') < 4) {
  throw new RuntimeException('Both partners require underlying and discount membership evidence');
}
echo "PASS: joint public grid retains privacy and active relationship safeguards\n";
echo "PASS: both partners require active BEC and Joint Discount evidence\n";
