<?php
/*
 v05 : Neeraj Jawahirani 2025-22-04
 	- Added isset() checks for undefined array indexes to prevent errors.
 	- Handled missing data for flags, filling in missing values appropriately.
 	- Updated flag handling logic to check if flags are set in the $flags array before use.
	- Ensured proper initialization and assignment of flags in the chart data.

*/

//header("Content-Type: text/plain");

include_once '../include/global.inc.php';
include_once 'charts.php';

$_SERVER['db'] =& logDB::connect($_SERVER['dsn'], $_SERVER['options']);

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

$query = "SELECT minimum_value, maximum_value FROM known_variable WHERE variable_name = '".$var."'";

db_query($query);

while ($row = db_get_row()) {
    $minimum = $row->minimum_value;
    $maximum = $row->maximum_value;
}

$cmd = PERL." -I".SAMOS_CODES_DIR." ".SAMOS_CODES_DIR."/nc_to_csv.pl $ship ".substr($date,0,8)." ".substr($date,0,8)." $order $version_no ''";

// Execute the command and capture output
$output = array();
exec($cmd, $output, $return_val);

// Check if the command execution was successful
if ($return_val !== 0) {
    echo "Error executing command: $cmd\n";
    exit;
}

$headers = false;
$start = false;
$variables = array();
$flags = array();
$first = -9999;
$last = false;
$times = array();
$specialTimestampFlags = array();

// Loop through the output lines
foreach ($output as $line) {
    if ($start == false) {
        if (trim($line) == "DATA START") {
            $start = true;
        }
    } elseif (!is_array($headers)) {
        $headers = explode(",", $line);
        for ($j = 4; $j < count($headers); $j += 2) {
            if (trim($headers[$j]) == "$var") {
                $i = $v_col = $j;
            }
        }
    } else {
        $data = explode(",", $line);
        if (count($data) == count($headers)) {
            $currentTimestamp = isset($data[1]) ? trim($data[1]) : '';
            if ($currentTimestamp === '' || !preg_match('/^\d+$/', $currentTimestamp)) {
                for ($i = 4; $i < count($data); $i += 2) {
                    if (trim($headers[$i]) != "$var") {
                        continue;
                    }

                    $value = trim($data[$i]);
                    $flag = trim($data[$i + 1]);

                    if ($value === '' || $value === null) {
                        continue;
                    }

                    $missingTimestampMarker = ((string)$value === '-8888' || $flag === '$') ? '-8888' : '-9999';
                    $specialTimestampFlags[$missingTimestampMarker] = ($missingTimestampMarker === '-8888') ? '$' : '#';
                }
                continue;
            }

            $variables['time'][trim($data[1])] = trim($data[1]);

            if ($first == -9999)
                $first = trim($data[1]);

            for ($i = 4; $i < count($data); $i += 2) {
                $variable_name = trim($headers[$i]);
                $value = trim($data[$i]);
                $flag = trim($data[$i + 1]);

                if (trim($headers[$i]) != "$var")
                    continue;

                // Skip storing flag if value is empty (no data point to plot)
                if ($value === '' || $value === null) {
                    continue;
                }

                // Only add time for non-empty values to keep times and flags in sync
                array_push($times, trim($data[2]));

                $variables[$variable_name][trim($data[1])] = $value;

                // Ensure flag is set for variable
                if (!isset($flags[$variable_name])) {
                    $flags[$variable_name] = array();
                }

                // Check for bound values and assign flags
                if (!$fbound && (($value < $minimum) || ($value > $maximum))) {
                    $flags[$variable_name][trim($data[1])] = 'Z';
                } else {
                    $flags[$variable_name][trim($data[1])] = $flag;
                }

                if ($value != -8888 && $value != -9999 && ($fbound || $flag == 'Z' || $flag == 'L' || $flag == 'N')) {
                    if (!isset($var_range[$variable_name]['max']) || $var_range[$variable_name]['max'] < $value)
                        $var_range[$variable_name]['max'] = $value;
                    if (!isset($var_range[$variable_name]['min']) || $var_range[$variable_name]['min'] > $value)
                        $var_range[$variable_name]['min'] = $value;
                }
                $last = trim($data[1]);
            }
        }
    }
}

// Fill in missing data for flags (for time continuity)
// NOTE: Do NOT forward-fill actual data values - let gaps appear in plots
$last_good = isset($variables[$var][$first]) ? $variables[$var][$first] : 0;

for ($t = $first; $t < $last; $t += 100) {
    if ($t % 10000 == 6000) {
        $t += 4000;
        if ($t >= $last) break;
    }

    if (!isset($variables[$var][$t])) {
        // Value is missing entirely - set to null to create gap in plot
        $variables[$var][$t] = null;
        $flags[$var][$t] = '#';
    } elseif ((int)$variables[$var][$t] == -8888) {
        // -8888 indicates special missing value
        $variables[$var][$t] = null;
        $flags[$var][$t] = '$';
    } elseif ((int)$variables[$var][$t] == -9999) {
        // -9999 indicates missing value
        $variables[$var][$t] = null;
        $flags[$var][$t] = '#';
    } else {
        $last_good = $variables[$var][$t];
    }
}

ksort($variables["$var"]);

$flags_arr = array();
$data_res = array();
$i = 0;
$j = 0;
$k = 0;

if ($var_range[$var]['min'] == $var_range[$var]['max']) {
    $var_range[$var]['min'] -= 0.5;
    $var_range[$var]['max'] += 0.5;
}

// Create chart legend
$chart['chart_data'][0][0] = "time";
$chart['chart_data'][1][0] = "$var";
$chart['chart_value_text'][0][0] = '';
$chart['chart_value_text'][1][0] = '';

$flags_arr[0] = ' ';

// Process variables and flags for chart
if (isset($variables["$var"])) {
    foreach ($variables["$var"] as $t => $d) {
        if ($hs > $t / 10000 || $t / 10000 > $he + 1)
            continue;

        // Skip null values (missing/special data indicators)
        if ($d === null) {
            continue;
        }

        if ($t % 10000 == 0) {
            $chart['chart_data'][0][] = $t / 10000;
        } else {
            $chart['chart_data'][0][] = '';
        }

        // Ensure value is within range
        if (isset($var_range[$var]['min']) && $d < $var_range[$var]['min']) {
            $chart['chart_data'][1][] = $var_range[$var]['min'];
            $data_res[$i] = $var_range[$var]['min'];
        } elseif (isset($var_range[$var]['max']) && $d > $var_range[$var]['max']) {
            $chart['chart_data'][1][] = $var_range[$var]['max'];
            $data_res[$i] = $var_range[$var]['max'];
        } else {
            $chart['chart_data'][1][] = $d;
            $data_res[$i] = $d;
        }

        $chart['chart_value_text'][0][] = '';

        // Output flag for this data point
        $chart['chart_value_text'][1][] = isset($flags["$var"][$t]) ? $flags["$var"][$t] : ' ';
        $flags_arr[$j] = isset($flags["$var"][$t]) ? $flags["$var"][$t] : ' ';

        $j++;
    }
}

// Prepare timestamp for JSON result
foreach ($times as $minutes) {
    $addingFiveMinutes = strtotime('1980-01-01 00:00:00 +' . $minutes . ' minute');
    //echo date('Y-m-d H:i:s', $addingFiveMinutes) . "\n";
}

for ($k = 0; $k < count($times); $k++) {
    $addingFiveMinutes = strtotime('1980-01-01 00:00:00 +' . $times[$k] . ' minute');
    $json_result[date('Y-m-d H:i:s', $addingFiveMinutes)] = $flags_arr[$k];
}

foreach ($specialTimestampFlags as $missingTimestampMarker => $missingTimestampFlag) {
    if (!isset($json_result[$missingTimestampMarker])) {
        $json_result[$missingTimestampMarker] = $missingTimestampFlag;
    }
}

// Output JSON result
if (isset($_GET['pretty'])) {
    pretty_json(json_encode($json_result));
} else {
    header("Content-Type: application/json");
    echo stripslashes(json_encode($json_result));
}

?>
