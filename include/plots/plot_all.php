<?php
/**
 * Plot All Module
 * Handles rendering multiple plots grouped by database order values
 */

function RenderPlotAll($varGroups, $allVars, $filterStart, $filterEnd, $title = 'All Variable Groups')
{
  global $file_history_id, $order, $ship, $date, $ship_id, $SERVER;
  $debugPlot = isset($_REQUEST['debug_plot']) && $_REQUEST['debug_plot'] == '1';
  $debugRows = array();
  
  echo "<h2 style='text-align:center; color:#2c3e50; margin:20px 0;'>$title</h2>";
  echo '<style>
    .plot-menu-wrap { position: relative; width: 790px; margin: 10px auto; border: 1px solid #ccc; }
    .plot-menu { position: absolute; top: 8px; right: 8px; z-index: 25; }
    .plot-menu > summary { list-style: none; cursor: pointer; width: 30px; height: 30px; line-height: 28px; text-align: center; font-size: 18px; border: 1px solid #bbb; border-radius: 4px; background: #fff; }
    .plot-menu > summary::-webkit-details-marker { display: none; }
    .plot-menu-dropdown { position: absolute; right: 0; margin-top: 6px; background: #fff; border: 1px solid #bbb; border-radius: 6px; min-width: 150px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); padding: 6px; }
    .plot-menu-dropdown button { display: block; width: 100%; margin: 4px 0; padding: 7px 10px; font-size: 13px; text-align: left; cursor: pointer; border: 1px solid #d0d0d0; border-radius: 4px; background: #fff; }
    .plot-menu-dropdown button:hover { background: #f5f5f5; }
  </style>';
  
  $plotIndex = 0;
  foreach ($varGroups as $group) {
    $groupName = $group['name'];
    $groupVars = $group['vars'];
    
    if (empty($groupVars)) continue;
    
    // Fetch data for this group - USE PARALLEL REQUESTS
    $plotData = array();
    
    // Build all URLs first
    $urlMap = array();
    foreach ($groupVars as $var) {
      if (!isset($allVars[$var])) {
        if ($debugPlot) {
          $debugRows[] = array('group' => $groupName, 'var' => $var, 'status' => 'skipped', 'reason' => 'not_in_allVars');
        }
        continue;
      }
      $info = $allVars[$var];
      $ver = $info['ver'];

      $baseParams = http_build_query(array(
        'ship' => $ship,
        'date' => $date,
        'order' => $order,
        'var' => $var,
        'version_no' => $ver,
        'units' => $info['units'],
        'fbound' => isset($_REQUEST['fbound']) ? $_REQUEST['fbound'] : 1,
        'hs' => isset($_REQUEST['hs']) ? $_REQUEST['hs'] : '00:00',
        'he' => isset($_REQUEST['he']) ? $_REQUEST['he'] : '23:59',
      ));

      $urlMap[$var] = array(
        'data' => "$SERVER/charts/plot_chart.php?$baseParams",
        'flags' => "$SERVER/charts/plot_series_flags.php?$baseParams"
      );
    }
    
    // Fetch all URLs in parallel using curl_multi
    $mh = curl_multi_init();
    $curlHandles = array();
    
    foreach ($urlMap as $var => $urls) {
      $chData = curl_init();
      curl_setopt($chData, CURLOPT_URL, $urls['data']);
      curl_setopt($chData, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($chData, CURLOPT_TIMEOUT, 30);
      curl_multi_add_handle($mh, $chData);
      $curlHandles[$var]['data'] = $chData;
      
      $chFlags = curl_init();
      curl_setopt($chFlags, CURLOPT_URL, $urls['flags']);
      curl_setopt($chFlags, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($chFlags, CURLOPT_TIMEOUT, 30);
      curl_multi_add_handle($mh, $chFlags);
      $curlHandles[$var]['flags'] = $chFlags;
    }
    
    $running = null;
    do {
      curl_multi_exec($mh, $running);
      curl_multi_select($mh);
    } while ($running > 0);
    
    $results = array();
    foreach ($curlHandles as $var => $handles) {
      $results[$var] = array(
        'data' => curl_multi_getcontent($handles['data']),
        'flags' => curl_multi_getcontent($handles['flags'])
      );
      curl_multi_remove_handle($mh, $handles['data']);
      curl_multi_remove_handle($mh, $handles['flags']);
      curl_close($handles['data']);
      curl_close($handles['flags']);
    }
    curl_multi_close($mh);
    
    // Fetch Z flags from database for all variables in this group
    $zFlagsByVar = array();
    foreach ($groupVars as $var) {
      $zFlagsByVar[$var] = ' ';
      
      if ($order > 100) {
        $zQuery = "SELECT kv.known_variable_id, mqcs.*, kv.variable_name 
                   FROM merged_qc_summary mqcs 
                   INNER JOIN known_variable kv ON mqcs.known_variable_id = kv.variable_id 
                   WHERE merged_file_history_id = $file_history_id 
                   AND kv.variable_name = '$var'";
      } else {
        $zQuery = "SELECT kv.known_variable_id, qcs.*, kv.variable_name 
                   FROM qc_summary qcs 
                   INNER JOIN known_variable kv ON qcs.known_variable_id = kv.variable_id 
                   WHERE daily_file_history_id = $file_history_id 
                   AND kv.variable_name = '$var'";
      }
      
      db_query($zQuery);
      if ($zRow = db_get_row()) {
        if ($zRow->z == 1) {
          $zFlagsByVar[$var] = 'Z';
        }
      }
    }
    
    // Process results
    foreach ($groupVars as $var) {
      if (!isset($results[$var])) {
        if ($debugPlot) {
          $debugRows[] = array('group' => $groupName, 'var' => $var, 'status' => 'skipped', 'reason' => 'no_curl_result');
        }
        continue;
      }
      
      $jsonData = $results[$var]['data'];
      $jsonFlags = $results[$var]['flags'];
      
      if ($jsonData === false || $jsonFlags === false) {
        if ($debugPlot) {
          $debugRows[] = array('group' => $groupName, 'var' => $var, 'status' => 'skipped', 'reason' => 'curl_false_data_or_flags');
        }
        continue;
      }

      $values = json_decode($jsonData, true);
      $flags_raw = json_decode($jsonFlags, true);
      if (!is_array($values) || !is_array($flags_raw)) {
        if ($debugPlot) {
          $debugRows[] = array('group' => $groupName, 'var' => $var, 'status' => 'skipped', 'reason' => 'invalid_json', 'data_preview' => substr((string) $jsonData, 0, 100));
        }
        continue;
      }

      $flag_by_index = array_values($flags_raw);
      $points = array();
      $flag_index = 0;
      $rawCount = count($values);
      $timeFiltered = 0;
      $nullFiltered = 0;

      foreach ($values as $ts => $val) {
        $timePart = substr($ts, 11, 5);
        if ($filterStart && $timePart < $filterStart) { $timeFiltered++; $flag_index++; continue; }
        if ($filterEnd && $timePart > $filterEnd) { $timeFiltered++; $flag_index++; continue; }
        if ($val === null || $val === '') { $nullFiltered++; $flag_index++; continue; }

        // Use Z flag if available, otherwise use flag from endpoint
        $flag = isset($flag_by_index[$flag_index]) ? $flag_by_index[$flag_index] : ' ';
        if ($flag === ' ' || $flag === '') {
          $flag = $zFlagsByVar[$var] ?? ' ';
        }

        $points[] = array(
          'date' => $ts,
          'value' => $val,
          'flag' => $flag
        );
        $flag_index++;
      }

      if (!empty($points)) {
        $plotData[$var] = array('points' => $points);
        if ($debugPlot) {
          $debugRows[] = array(
            'group' => $groupName,
            'var' => $var,
            'status' => 'plotted',
            'reason' => 'ok',
            'raw' => $rawCount,
            'time_filtered' => $timeFiltered,
            'null_filtered' => $nullFiltered,
            'plotted_points' => count($points)
          );
        }
      } elseif ($debugPlot) {
        $debugRows[] = array(
          'group' => $groupName,
          'var' => $var,
          'status' => 'skipped',
          'reason' => 'no_points_after_filters',
          'raw' => $rawCount,
          'time_filtered' => $timeFiltered,
          'null_filtered' => $nullFiltered,
          'plotted_points' => 0
        );
      }
    }
    
    if (empty($plotData)) continue;

    if ($debugPlot) {
      $seriesBySignature = array();
      foreach ($plotData as $varName => $series) {
        $pointsForSig = isset($series['points']) && is_array($series['points']) ? $series['points'] : array();
        $sigSource = array();
        foreach ($pointsForSig as $p) {
          $sigSource[] = array(
            'date' => isset($p['date']) ? $p['date'] : null,
            'value' => isset($p['value']) ? $p['value'] : null,
          );
        }
        $signature = md5(json_encode($sigSource));
        if (!isset($seriesBySignature[$signature])) {
          $seriesBySignature[$signature] = array();
        }
        $seriesBySignature[$signature][] = $varName;
      }

      foreach ($seriesBySignature as $sameSeriesVars) {
        if (count($sameSeriesVars) > 1) {
          $sameList = implode(', ', $sameSeriesVars);
          foreach ($sameSeriesVars as $v) {
            $others = array_values(array_filter($sameSeriesVars, function ($x) use ($v) {
              return $x !== $v;
            }));
            $debugRows[] = array(
              'group' => $groupName,
              'var' => $v,
              'status' => 'info',
              'reason' => 'overlaps_exactly_with: ' . implode(', ', $others),
              'raw' => '',
              'time_filtered' => '',
              'null_filtered' => '',
              'plotted_points' => count($plotData[$v]['points'])
            );
          }
        }
      }
    }
    
    // Build units and longNames for this group
    $unitsMap = array();
    $longNames = array();
    foreach (array_keys($plotData) as $var) {
      $unitsMap[$var] = isset($allVars[$var]['units']) ? $allVars[$var]['units'] : '';
      $varIdQuery = "SELECT long_name FROM known_variable WHERE variable_name = '$var'";
      db_query($varIdQuery);
      if ($varRow = db_get_row()) {
        // Use the database long_name, but fallback to GetVariableTitle for better formatting
        $longName = $varRow->long_name;
        // If the long_name looks like a generic group name (e.g., "P3 Group", "T2 Group")
        // or contains a digit followed by "Group", use GetVariableTitle instead
        if (preg_match('/\d\s+Group$|^(\w+)\d\s*Group$/i', $longName)) {
          $longName = GetVariableTitle($var);
        }
        $longNames[$var] = $longName;
      } else {
        $longNames[$var] = GetVariableTitle($var);
      }
    }
    
    // Detect if this group has polar-compatible variables
    $hasPolarVariables = false;
    $polarCompatibleVars = array('PL_CRS', 'PL_CRS2', 'PL_HD', 'PL_HD2', 'PL_WDIR', 'PL_WDIR2', 'DIR', 'DIR2', 'DIR3', 'WDIR', 'WDIR2', 'SPD', 'SPD2', 'WSPD', 'WSPD2');
    $degreeUnitPattern = '/degree|deg|°/i';
    
    foreach (array_keys($plotData) as $var) {
      if (in_array($var, $polarCompatibleVars)) {
        $hasPolarVariables = true;
        break;
      }
      $unit = isset($unitsMap[$var]) ? $unitsMap[$var] : '';
      if (preg_match($degreeUnitPattern, $unit)) {
        $hasPolarVariables = true;
        break;
      }
    }
    
    $chartId = 'combinedChart_' . $plotIndex;
    
    // Group title + chart/menu container
    echo "<h3 style='text-align:center; color:#34495e; margin:30px 0 10px; border-top:2px solid #eee; padding-top:20px;'>$groupName</h3>";
    echo "<div class=\"plot-menu-wrap\">";
    echo "  <details class=\"plot-menu\">";
    echo "    <summary title=\"Plot actions\">&#9776;</summary>";
    echo "    <div class=\"plot-menu-dropdown\">";
    echo "      <button onclick=\"downloadCombinedPlot('$chartId')\">Download PNG</button>";
    echo "      <button onclick=\"downloadCombinedCSV('$chartId')\">Download CSV</button>";
    echo "      <button onclick=\"openZoomModal_$plotIndex()\">Zoom & Pan</button>";
    echo "      <button onclick=\"openShipTrackModal()\">Ship Track</button>";
    echo "    </div>";
    echo "  </details>";
    echo "  <div id=\"$chartId\" style=\"width:100%; height:520px; position:relative;\"></div>";
    echo "</div>";
    
    $jsPayload = json_encode(array(
      'plotData' => $plotData,
      'units' => $unitsMap,
      'longNames' => $longNames,
      'ship' => $ship,
      'shipName' => get_ship_name($ship_id),
      'date' => $date,
      'order' => $order,
      'hs' => isset($_REQUEST['hs']) ? $_REQUEST['hs'] : '00:00',
      'he' => isset($_REQUEST['he']) ? $_REQUEST['he'] : '23:59'
    ));
    
    echo "<script>
      // Initialize chart payloads object and store payload immediately
      window.__chartPayloads = window.__chartPayloads || {};
      window.__chartPayloads['$chartId'] = $jsPayload;
      
      // Custom zoom modal opener for this chart (must be global)
      function openZoomModal_$plotIndex() {
        window.__originalChartData = window.__chartPayloads['$chartId'];
        if (typeof openZoomModal === 'function') {
          openZoomModal('$chartId');
        }
      }

      document.addEventListener('DOMContentLoaded', () => {
        const payload_$plotIndex = window.__chartPayloads['$chartId'];
        if (typeof renderCombinedPlot === 'function') {
          renderCombinedPlot(payload_$plotIndex, '$chartId');
        }
      });
    </script>";
    
    $plotIndex++;
  }
  
  if ($plotIndex == 0) {
    echo "<p style='text-align:center; color:#e74c3c;'>No data found for any variable groups.</p>";
  }

  if ($debugPlot) {
    echo "<div style='width:95%; margin:20px auto; border:1px solid #ccc; padding:10px; background:#fafafa;'>";
    echo "<h3 style='margin:0 0 10px; color:#2c3e50;'>Plot-All Debug (debug_plot=1)</h3>";
    echo "<table style='width:100%; border-collapse:collapse; font-size:12px;'>";
    echo "<tr style='background:#eee;'>";
    echo "<th style='border:1px solid #ccc; padding:4px;'>Group</th>";
    echo "<th style='border:1px solid #ccc; padding:4px;'>Variable</th>";
    echo "<th style='border:1px solid #ccc; padding:4px;'>Status</th>";
    echo "<th style='border:1px solid #ccc; padding:4px;'>Reason</th>";
    echo "<th style='border:1px solid #ccc; padding:4px;'>Raw</th>";
    echo "<th style='border:1px solid #ccc; padding:4px;'>Time filtered</th>";
    echo "<th style='border:1px solid #ccc; padding:4px;'>Null filtered</th>";
    echo "<th style='border:1px solid #ccc; padding:4px;'>Plotted</th>";
    echo "</tr>";

    foreach ($debugRows as $row) {
      $statusColor = '#fdecec';
      if ($row['status'] === 'plotted') {
        $statusColor = '#e8f7e8';
      } elseif ($row['status'] === 'info') {
        $statusColor = '#eef4ff';
      }
      echo "<tr style='background:$statusColor;'>";
      echo "<td style='border:1px solid #ccc; padding:4px;'>" . htmlspecialchars($row['group']) . "</td>";
      echo "<td style='border:1px solid #ccc; padding:4px;'>" . htmlspecialchars($row['var']) . "</td>";
      echo "<td style='border:1px solid #ccc; padding:4px;'>" . htmlspecialchars($row['status']) . "</td>";
      echo "<td style='border:1px solid #ccc; padding:4px;'>" . htmlspecialchars($row['reason']) . "</td>";
      echo "<td style='border:1px solid #ccc; padding:4px;'>" . (isset($row['raw']) ? (int) $row['raw'] : '') . "</td>";
      echo "<td style='border:1px solid #ccc; padding:4px;'>" . (isset($row['time_filtered']) ? (int) $row['time_filtered'] : '') . "</td>";
      echo "<td style='border:1px solid #ccc; padding:4px;'>" . (isset($row['null_filtered']) ? (int) $row['null_filtered'] : '') . "</td>";
      echo "<td style='border:1px solid #ccc; padding:4px;'>" . (isset($row['plotted_points']) ? (int) $row['plotted_points'] : '') . "</td>";
      echo "</tr>";
    }

    echo "</table></div>";
  }
  
  // Include required JS files
  echo "<script src=\"https://d3js.org/d3.v6.min.js\"></script>";
  echo "<script src=\"js/combined-plot.js\"></script>";
  echo "<script src=\"js/zoom-pan.js\"></script>";
  echo "<script src=\"js/polar-plot.js\"></script>";
  echo '<script src="js/ship-track.js"></script>';
  echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />';
  echo '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
  
  // Include modals
  include 'modals.php';
  RenderZoomModal();
  RenderPolarModal();
  RenderShipTrackModal();
  RenderModalFunctions();
}
