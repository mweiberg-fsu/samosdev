<?php
/**
 * Plot All Module
 * Handles rendering multiple plots grouped by database order values
 */

function RenderPlotAll($varGroups, $allVars, $filterStart, $filterEnd)
{
  global $file_history_id, $order, $ship, $date, $ship_id, $SERVER;
  
  echo "<h2 style='text-align:center; color:#2c3e50; margin:20px 0;'>All Variable Groups</h2>";
  
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
      if (!isset($allVars[$var])) continue;
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
      if (!isset($results[$var])) continue;
      
      $jsonData = $results[$var]['data'];
      $jsonFlags = $results[$var]['flags'];
      
      if ($jsonData === false || $jsonFlags === false) continue;

      $values = json_decode($jsonData, true);
      $flags_raw = json_decode($jsonFlags, true);
      if (!is_array($values) || !is_array($flags_raw)) continue;

      $flag_by_index = array_values($flags_raw);
      $points = array();
      $flag_index = 0;

      foreach ($values as $ts => $val) {
        $timePart = substr($ts, 11, 5);
        if ($filterStart && $timePart < $filterStart) { $flag_index++; continue; }
        if ($filterEnd && $timePart > $filterEnd) { $flag_index++; continue; }
        if ($val === null || $val === '') { $flag_index++; continue; }

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
      }
    }
    
    if (empty($plotData)) continue;
    
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
    
    $chartId = 'combinedChart_' . $plotIndex;
    
    // Group title
    echo "<h3 style='text-align:center; color:#34495e; margin:30px 0 10px; border-top:2px solid #eee; padding-top:20px;'>$groupName</h3>";
    echo "<div id=\"$chartId\" style=\"width:790px; height:520px; margin:10px auto; border:1px solid #ccc; position:relative;\"></div>";
    
    // Buttons for this plot
            echo "<div style=\"text-align:center; margin:15px;\">
              <button onclick=\"downloadCombinedPlot('$chartId')\" style=\"padding:8px 16px; font-size:14px; cursor:pointer; margin-right:5px; background:transparent; color:#27ae60; border:2px solid #27ae60; border-radius:4px; font-weight:bold; transition:all 0.3s ease;\" onmouseover=\"this.style.background='#27ae60'; this.style.color='white';\" onmouseout=\"this.style.background='transparent'; this.style.color='#27ae60';\">Download PNG</button>
              <button onclick=\"downloadCombinedCSV('$chartId')\" style=\"padding:8px 16px; font-size:14px; cursor:pointer; margin-right:5px; background:transparent; color:#27ae60; border:2px solid #27ae60; border-radius:4px; font-weight:bold; transition:all 0.3s ease;\" onmouseover=\"this.style.background='#27ae60'; this.style.color='white';\" onmouseout=\"this.style.background='transparent'; this.style.color='#27ae60';\">Download CSV</button>
              <button onclick=\"openZoomModal_$plotIndex()\" style=\"padding:8px 16px; font-size:14px; cursor:pointer; margin-right:5px; background:transparent; color:#007cba; border:2px solid #007cba; border-radius:4px; transition:all 0.3s ease;\" onmouseover=\"this.style.background='#007cba'; this.style.color='white';\" onmouseout=\"this.style.background='transparent'; this.style.color='#007cba';\">Zoom & Pan</button>
              <button onclick=\"openPolarModal_$plotIndex()\" style=\"padding:8px 16px; font-size:14px; cursor:pointer; margin-right:5px; background:transparent; color:#007cba; border:2px solid #007cba; border-radius:4px; transition:all 0.3s ease;\" onmouseover=\"this.style.background='#007cba'; this.style.color='white';\" onmouseout=\"this.style.background='transparent'; this.style.color='#007cba';\">Polar Plot</button>
              <button onclick=\"openShipTrackModal()\" style=\"padding:8px 16px; font-size:14px; cursor:pointer; background:transparent; color:#007cba; border:2px solid #007cba; border-radius:4px; transition:all 0.3s ease;\" onmouseover=\"this.style.background='#007cba'; this.style.color='white';\" onmouseout=\"this.style.background='transparent'; this.style.color='#007cba';\">Ship Track</button>
              </div>";
    
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
      document.addEventListener('DOMContentLoaded', () => {
        const payload_$plotIndex = $jsPayload;
        if (typeof renderCombinedPlot === 'function') {
          renderCombinedPlot(payload_$plotIndex, '$chartId');
        }
        
        // Store payload for zoom modal
        window.__chartPayloads = window.__chartPayloads || {};
        window.__chartPayloads['$chartId'] = payload_$plotIndex;
      });
      
      // Custom zoom modal opener for this chart
      function openZoomModal_$plotIndex() {
        window.__originalChartData = window.__chartPayloads['$chartId'];
        if (typeof openZoomModal === 'function') {
          openZoomModal();
        }
      }

      function openPolarModal_$plotIndex() {
        window.__originalPolarData = window.__chartPayloads['$chartId'];
        if (typeof openPolarModal === 'function') {
          openPolarModal();
        }
      }
    </script>";
    
    $plotIndex++;
  }
  
  if ($plotIndex == 0) {
    echo "<p style='text-align:center; color:#e74c3c;'>No data found for any variable groups.</p>";
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
