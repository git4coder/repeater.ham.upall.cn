<?php
include('./config.php');
$zoom = (int)$zoom;

$north = $north - abs($north - $south) / 4;
$westEastDiffValue = abs($west - $east);
$west = $west + $westEastDiffValue / 4;
$east = $east - $westEastDiffValue / 4;

$s = "
  SELECT
    r.`name`,
    r.`configuration`,
    r.`via`,
    l.`name` AS location,
    l.`longitude`,
    l.`latitude`
  FROM `repeater` AS r
  LEFT JOIN `location` AS l ON r.`location_id` = l.`id`
  WHERE r.`location_id` IN (
    SELECT
      id
    FROM `location`
    WHERE 1
      AND `longitude` >= '$west'
      AND `longitude` <= '$east'
      AND `latitude` <= '$north'
      AND `latitude` >= '$south'
  )
      ";
$dosql->safecheck = false;
$dosql->Execute($s, 'repeater');
$data = json_decode('{}');
$data->data = array();
while ($province = $dosql->GetArray('repeater')) {
  $province['longitude'] = (float)$province['longitude'];
  $province['latitude'] = (float)$province['latitude'];
  $data->data[] = $province;
}
// $data->params = $_GET;
// $data->sql = $s;

header('Content-Type: text/javascript;charset=utf-8');
header('Access-Control-Allow-Origin: *');
echo json_encode($data, JSON_UNESCAPED_UNICODE);

