<?php
/*

version 4 : Neeraj Jawahirani 2025-16-04
	- initizalized and check if array or not   $variables[$var]

*/

include_once 'include/global.inc.php';
include_once 'charts.php';

$_SERVER['db'] = logDB::connect($_SERVER['dsn'], $_SERVER['options']);

$json_result = array();

$ship = $ship_callsign = $_REQUEST['ship'];
$date = $datetime_collected = $_REQUEST['date'];
$order = $order_no = (isset($_REQUEST['order'])) ? (int) $_REQUEST['order'] : 0;
$version_no = (isset($_REQUEST['version_no'])) ? (int) $_REQUEST['version_no'] : 0;
$var = $_REQUEST['var'];
$fbound = ($_REQUEST['fbound'] == 1) ? true : false;
$units = $_REQUEST['units'];
$hs = (isset($_REQUEST['hs'])) ? (int) $_REQUEST['hs'] : 0;
$he = (isset($_REQUEST['he'])) ? (int) $_REQUEST['he'] : 23;
$mode = (isset($_REQUEST['mode'])) ? (int) $_REQUEST['mode'] : 6;

$query = "SELECT minimum_value, maximum_value FROM known_variable WHERE variable_name = '".$var."'";

db_query($query);

while ($row = db_get_row()) {
    $minimum = $row->minimum_value;
    $maximum = $row->maximum_value;
}

$cmd = PERL." -I".SAMOS_CODES_DIR." ".SAMOS_CODES_DIR."/nc_to_csv.pl $ship ".substr($date,0,8)." ".substr($date,0,8)." $order $version_no ''";

$output = array();
exec($cmd, $output, $return_val);
//print_r($output);
$headers = false;
$start = false;
$variables = array();
$flags = array();
$first = -9999;
$last = false;

$times = array();

foreach($output as $line) {
  //echo $line . "\n";
  if($start == false) {
    if(trim($line) == "DATA START")
      $start = true;
  }elseif(!is_array($headers)) {
    $headers = explode(",",$line);
    for ($j = 4; $j < (is_array($data) ? count($data) : 0); $j += 2) {
      if (trim($headers[$j]) == "$var") {
        $i = $v_col = $j;
      }
    }
  } else {
    $data = explode(",",$line);
    if(count($data) == count($headers)) {
      	$t = trim($data[1]);
      	if($hs > $t/10000 || $t/10000 > $he+1)
        	continue;
      	$variables['time'][trim($data[1])] = trim($data[1]);

      	array_push($times, trim($data[2]));	
      	if($first == -9999)
		$first = trim($data[1]);

      	for($i = 4; $i < count($data); $i+=2) {
        	$variable_name = trim($headers[$i]);
        	$value = trim($data[$i]);
       	 	$flag = trim($data[$i+1]);
			if(trim($headers[$i]) != "$var")
	  			continue;
      	
        	if (!$fbound && (($value < $minimum) || ($value > $maximum))) {
	    		$variables[trim($headers[$i])][trim($data[1])] = -9999;
        	} else {
	    		$variables[trim($headers[$i])][trim($data[1])] = trim($data[$i]);
        	}
			
			$flags[trim($headers[$i])][trim($data[1])] = trim($data[$i+1]);
			// For mode=6 (Plot view), exclude Z flags from range calculation
			// For mode=9 (Plot new), include Z flags
			$includeZFlags = ($mode == 9);
			if(trim($data[$i]) != -8888 && trim($data[$i]) != -9999 && ($fbound || ($includeZFlags && trim($data[$i+1]) == 'Z') || trim($data[$i+1]) == 'L' || trim($data[$i+1]) == 'N')) {
	  			if(!isset($var_range[trim($headers[$i])]['max']) || $var_range[trim($headers[$i])]['max'] < trim($data[$i]))
	    			$var_range[trim($headers[$i])]['max'] = trim($data[$i]);
	  			if(!isset($var_range[trim($headers[$i])]['min']) || $var_range[trim($headers[$i])]['min'] > trim($data[$i]))
	    			$var_range[trim($headers[$i])]['min'] = trim($data[$i]);
			}
			$last = trim($data[1]);
      }
    }
  }
}

/*
if($first%10000 != 0) {
  for($t = $first-100; true; $t-=100) {
    //echo "inserting time $t<br/>\n";
    if(!isset($variables[$var][$t])) {
      $variables[$var][$t] = $variables[$var][$first];
      $flags[$var][$t] = '#';
    }
    if($t%10000 == 0)
      break;
  }
 }
 */
//$last_good = $variables[$var][$first];\
$last_good = isset($variables[$var][$first]) ? $variables[$var][$first] : null;

for($t = $first; $t < $last; $t+=100) {
  if($t%10000 == 6000) {
    $t+=4000;
    if($t >= $last)
      break;
  }
  if(!isset($variables[$var][$t])) {
    // Value is missing entirely - set to null to create gap in plot
    $variables[$var][$t] = null;
    $flags[$var][$t] = '#';
  } elseif((int)$variables[$var][$t] == -8888) {
    // -8888 indicates special missing value
    $variables[$var][$t] = null;
    $flags[$var][$t] = '$';
  } elseif((int)$variables[$var][$t] == -9999) {
    // -9999 indicates missing value
    $variables[$var][$t] = null;
    $flags[$var][$t] = '#';
  } else {
    $last_good = $variables[$var][$t];
  }
}


if (isset($variables["$var"]) && is_array($variables["$var"])) {
    ksort($variables["$var"]);
} else {
    // Handle the case when $variables[$var] is not set or is not an array
    $variables[$var] = [];
    ksort($variables[$var]); // Now you can safely sort an empty array
}
//print_r($times);


$data_res = array();
$i = 0;
$j = 0;
$k = 0;

if (isset($var_range[$var]['min'], $var_range[$var]['max']) && $var_range[$var]['min'] == $var_range[$var]['max']) {
  $var_range[$var]['min'] -= .5;
  $var_range[$var]['max'] += .5;
}

// create legend
$chart['chart_data'][0][0] = "time";
$chart['chart_data'][1][0] = "$var";
$chart['chart_value_text'][0][0] = '';
$chart['chart_value_text'][1][0] = '';
if(isset($variables["$var"])) {
  foreach($variables["$var"] as $t=>$d) {
    if($hs > $t/10000 || $t/10000 > $he+1)
      continue;
    
    // Skip Z-flagged data points in Plot view (mode=6)
    if($mode == 6 && isset($flags["$var"][$t]) && $flags["$var"][$t] == 'Z')
      continue;

    if($t%10000==0) {
      $chart['chart_data'][0][] = $t/10000;
    }else {
      $chart['chart_data'][0][] = '';
    }	

    // Include null values in the output to create gaps in plots
    if($d === null) {
      $chart['chart_data'][1][] = null;
      $data_res[$i] = null;
    }
    elseif(isset($var_range[$var]['min']) && $d < $var_range[$var]['min']){
      $chart['chart_data'][1][] = $var_range[$var]['min'];
      $data_res[$i] = $var_range[$var]['min'];
    }
    elseif(isset($var_range[$var]['max']) && $d > $var_range[$var]['max']) {
      $chart['chart_data'][1][] = $var_range[$var]['max'];
      $data_res[$i] = $var_range[$var]['max'];
    }
    else {
      $chart['chart_data'][1][] = $d;
      $data_res[$i] = $d;
    }

    $chart['chart_value_text'][0][] = '';
    // Output flag for this data point
    if($flags["$var"][$t] == 'Z')
      $chart['chart_value_text'][1][] = '';
    else
      $chart['chart_value_text'][1][] = $flags["$var"][$t];

    $i = $i + 1;
    if($i%60 == 0)
	$j = $j + 1;
   
  }

 }

//print_r($time);
//print_r($data_res);
//print_r_html($chart);

//Get timestamp for json result
foreach($times as $minutes){
        $addingFiveMinutes= strtotime('1980-01-01 00:00:00 +'. $minutes . ' minute');
        //echo date('Y-m-d H:i:s', $addingFiveMinutes) . "\n";
}

for($k = 0; $k < count($times); $k++){
	$addingFiveMinutes= strtotime('1980-01-01 00:00:00 +'. $times[$k] . ' minute');
	$json_result[date('Y-m-d H:i:s', $addingFiveMinutes)] = $data_res[$k];
}	


if(isset($_GET['pretty'])){
        pretty_json(json_encode($json_result));
}
else{
        header("Content-Type: application/json");
        //echo json_encode($json_result, JSON_UNESCAPED_SLASHES);
      echo stripslashes(json_encode($json_result));
      
}



?>
