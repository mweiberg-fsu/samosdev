<?php
/**
 * Plot New Module
 * Handles individual plots per variable with combined plot styling (mode=9)
 * Each variable gets its own separate plot with full features from combined plot
 */

function InsertPlotNew()
{
  global $file_history_id, $order, $ship, $date, $ship_id, $SERVER;

  /* ---------- 1. GET VARIABLES ---------- */
  $selectedVars = array();
  $selectAllOnLoad = empty($_REQUEST['vars']) || !is_array($_REQUEST['vars']);
  $hs = isset($_REQUEST['hs']) ? $_REQUEST['hs'] : '00:00';
  $he = isset($_REQUEST['he']) ? $_REQUEST['he'] : '23:59';

  if (!empty($_REQUEST['vars']) && is_array($_REQUEST['vars'])) {
    $selectedVars = array_filter($_REQUEST['vars'], 'trim');
  }

  $varOptions = '';
  $allVars = array();

  $q = ($order > 100)
    ? "SELECT variable_name, units, process_version_no
           FROM merged_qc_summary m
           JOIN known_variable kv ON m.known_variable_id = kv.variable_id
           JOIN merged_file_history mh USING(merged_file_history_id)
           JOIN version_no vn USING(version_id)
           WHERE merged_file_history_id = $file_history_id
           ORDER BY kv.order_value"
    : "SELECT variable_name, units, process_version_no
           FROM qc_summary q
           JOIN known_variable kv ON q.known_variable_id = kv.variable_id
           JOIN daily_file_history dh USING(daily_file_history_id)
           JOIN version_no vn USING(version_id)
           WHERE daily_file_history_id = $file_history_id
           ORDER BY kv.order_value";

  db_query($q);
  while ($r = db_get_row()) {
    if ($r->variable_name === 'time') continue;
    $ver = max((int) $r->process_version_no, 100);
    $allVars[$r->variable_name] = array('units' => $r->units, 'ver' => $ver);
    $sel = ($selectAllOnLoad || in_array($r->variable_name, $selectedVars)) ? ' selected' : '';
    $varOptions .= "<option value=\"{$r->variable_name}\"$sel>{$r->variable_name}</option>";
  }

  if ($selectAllOnLoad) {
    $selectedVars = array_keys($allVars);
  }

  /* ---------- 2. FORM ---------- */
  $actionUrl = "index.php?ship=$ship&id=$ship_id&date=$date&order=$order&history_id=$file_history_id&mode=9";

  echo <<<FORM
<form method="POST" action="$actionUrl" id="plotNewForm">
  <table class="search" style="margin:20px auto; width:790px; border-collapse:collapse;">
    <tr>
      <th style="width:180px;">Select variables<br><span class="small">(Ctrl+Click)</span></th>
      <td>
        <select name="vars[]" multiple size="8" style="width:200px;">
          $varOptions
        </select>
      </td>
    </tr>
    <tr>
      <th>Time Range</th>
      <td>
        From: <input type="text" name="hs" id="hs" value="$hs" size="5" maxlength="5" 
                    style="text-align:center; font-family:monospace;" 
                    placeholder="00:00">
        To: <input type="text" name="he" id="he" value="$he" size="5" maxlength="5" 
                    style="text-align:center; font-family:monospace;" 
                    placeholder="23:59">
        <span style="color:#888; font-size:11px; margin-left:5px;">UTC</span>
      </td>
    </tr>
    <tr>
      <th>Plot</th>
      <td>
        <button type="submit" style="padding:8px 25px; font-size:14px; background:#27ae60; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold;"
                onmouseover="this.style.background='#219a52'" onmouseout="this.style.background='#27ae60'">
          Plot
        </button>
      </td>
    </tr>
  </table>
</form>
FORM;

  // If initial load (no variables selected), show message
  if ($selectAllOnLoad) {
    echo "<p style='text-align:center; color:#666;'>Select variables above and click Plot.</p>";
    return;
  }

  // If no variables selected, show message
  if (empty($selectedVars)) {
    echo "<p style='text-align:center; color:#666;'>Select variables above and click Plot.</p>";
    return;
  }
  
  // Validate time format
  $validTime = function ($t) {
    return preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $t);
  };
  $filterStart = ($hs && $validTime($hs)) ? $hs : null;
  $filterEnd = ($he && $validTime($he)) ? $he : null;

  /* ---------- 3. FETCH DATA FOR EACH VARIABLE ---------- */
  // Build URLs and fetch data in parallel
  $urlMap = array();
  foreach ($selectedVars as $var) {
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
      'hs' => $hs,
      'he' => $he,
    ));

    $urlMap[$var] = array(
      'data' => "$SERVER/charts/plot_chart.php?$baseParams",
      'flags' => "$SERVER/charts/plot_series_flags.php?$baseParams"
    );
  }

  // Fetch in parallel
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

  // Fetch Z flags from database for all selected variables
  $zFlagsByVar = array();
  foreach ($selectedVars as $var) {
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

  /* ---------- 4. RENDER EACH VARIABLE IN ITS OWN PLOT ---------- */
  $plotIndex = 0;
  
  // Include once - D3 and shared JS
  echo '<script src="https://d3js.org/d3.v6.min.js"></script>';
  echo '<script src="js/combined-plot.js"></script>';
  echo '<script src="js/zoom-pan.js"></script>';
  echo '<script src="js/polar-plot.js"></script>';
  echo '<script src="js/ship-track.js"></script>';
  echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />';
  echo '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';

  foreach ($selectedVars as $var) {
    if (!isset($results[$var])) continue;

    $values = json_decode($results[$var]['data'], true);
    $flags_raw = json_decode($results[$var]['flags'], true);
    if (!is_array($values) || !is_array($flags_raw)) continue;

    $flag_by_index = array_values($flags_raw);
    $points = array();
    $flag_index = 0;

    foreach ($values as $ts => $val) {
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

    if (empty($points)) continue;

    // Build plot data for this single variable
    $plotData = array($var => array('points' => $points));
    
    // Get units and long name
    $units = isset($allVars[$var]['units']) ? $allVars[$var]['units'] : '';
    $unitsMap = array($var => $units);
    
    $longName = GetVariableTitle($var);
    $varIdQuery = "SELECT long_name FROM known_variable WHERE variable_name = '$var'";
    db_query($varIdQuery);
    if ($varRow = db_get_row()) {
      $dbLongName = $varRow->long_name;
      if (!preg_match('/\d\s+Group$|^(\w+)\d\s*Group$/i', $dbLongName)) {
        $longName = $dbLongName;
      }
    }
    $longNames = array($var => $longName);

    // Check if polar compatible
    $hasPolarVariables = false;
    $polarCompatibleVars = array('PL_CRS', 'PL_CRS2', 'PL_CRS3', 'PL_HD', 'PL_HD2', 'PL_HD3',
                                  'DIR', 'DIR2', 'DIR3', 'ER_WDIR', 'ER_WDIR2', 'ER_WDIR3',
                                  'PL_WDIR', 'PL_WDIR2', 'PL_WDIR3', 'SPD', 'SPD1', 'SPD2', 'SPD3',
                                  'PL_SPD', 'PL_SPD2', 'PL_SPD3');
    
    if (in_array($var, $polarCompatibleVars) || 
        preg_match('/(deg|degree|degrees|°)/i', $units)) {
      $hasPolarVariables = true;
    }

    // Output plot
    $chartId = 'chart_' . $plotIndex;
    $jsPayload = json_encode(array(
      'plotData' => $plotData,
      'units' => $unitsMap,
      'longNames' => $longNames,
      'ship' => $ship,
      'shipName' => get_ship_name($ship_id),
      'date' => $date,
      'order' => $order,
      'hs' => $hs,
      'he' => $he,
      'showTitle' => true
    ));

    echo '<div id="' . $chartId . '" style="width:790px; margin:20px auto; border:1px solid #ccc;"></div>';
    echo '<div style="text-align:center; margin:15px;">
      <button onclick="downloadCombinedPlot(\'' . $chartId . '\')" style="padding:8px 16px; font-size:14px; cursor:pointer; margin-right:5px; background:transparent; color:#27ae60; border:2px solid #27ae60; border-radius:4px; font-weight:bold; transition:all 0.3s ease;" onmouseover="this.style.background=\'#27ae60\'; this.style.color=\'white\';" onmouseout="this.style.background=\'transparent\'; this.style.color=\'#27ae60\';">Download PNG</button>
      <button onclick="downloadCombinedCSV(\'' . $chartId . '\')" style="padding:8px 16px; font-size:14px; cursor:pointer; margin-right:5px; background:transparent; color:#27ae60; border:2px solid #27ae60; border-radius:4px; font-weight:bold; transition:all 0.3s ease;" onmouseover="this.style.background=\'#27ae60\'; this.style.color=\'white\';" onmouseout="this.style.background=\'transparent\'; this.style.color=\'#27ae60\';">Download CSV</button>
      <button onclick="openZoomModal(\'' . $chartId . '\')" style="padding:8px 16px; font-size:14px; cursor:pointer; margin-right:5px; background:transparent; color:#007cba; border:2px solid #007cba; border-radius:4px; transition:all 0.3s ease;" onmouseover="this.style.background=\'#007cba\'; this.style.color=\'white\';" onmouseout="this.style.background=\'transparent\'; this.style.color=\'#007cba\';">Zoom & Pan</button>';
    
    echo '
      <button onclick="openShipTrackModal_' . $plotIndex . '()" style="padding:8px 16px; font-size:14px; cursor:pointer; background:transparent; color:#007cba; border:2px solid #007cba; border-radius:4px; transition:all 0.3s ease;" onmouseover="this.style.background=\'#007cba\'; this.style.color=\'white\';" onmouseout="this.style.background=\'transparent\'; this.style.color=\'#007cba\';">Ship Track</button>
      </div>';

    echo "<script>
    // Store payload for zoom modal
    window.__originalChartData_$plotIndex = $jsPayload;
    window.__originalPolarData_$plotIndex = $jsPayload;
    
    document.addEventListener('DOMContentLoaded', () => {
      const payload = $jsPayload;
      if (typeof renderCombinedPlot === 'function') {
        renderCombinedPlot(payload, '$chartId');
      }
    });
    </script>";

    $plotIndex++;
  }
  
  // Include modals once at the end
  if ($plotIndex > 0) {
    include 'include/plots/modals.php';
    RenderZoomModal();
    RenderPolarModal();
    RenderShipTrackModal();
    RenderModalFunctions();
  }
}
