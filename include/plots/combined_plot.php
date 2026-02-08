<?php
/**
 * Combined Plot Module
 * Handles simple combined plots without ship/date switching (mode=7)
 * Includes Plot All functionality to display multiple variable groups
 */

function InsertCombinedPlot()
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

  /* ---------- 2. SIMPLE FORM - NO SHIP/DATE SWITCHING ---------- */
  $actionUrl = "index.php?ship=$ship&id=$ship_id&date=$date&order=$order&history_id=$file_history_id&mode=7";

  echo <<<FORM
<form method="POST" action="$actionUrl" id="combinedPlotForm">
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
          Update Plot
        </button>
        <button type="button" id="plotAllBtn" style="margin-left:15px; padding:8px 25px; font-size:14px; background:#3498db; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold;"
                onmouseover="this.style.background='#2980b9'" onmouseout="this.style.background='#3498db'">
          Plot All
        </button>
      </td>
    </tr>
  </table>
</form>
FORM;

  // Output the JavaScript for Plot All functionality
  echo "\n<script>\n";
  echo "// Build variable groupings dynamically from the database order values\n";
  echo "const variableGroupsFromDB = ";
  
  // Query database to get variable names and their order values (group identifiers)
  if ($order > 100) {
    $groupQuery = "SELECT DISTINCT kv.variable_name, kv.order_value 
                   FROM merged_qc_summary mqcs 
                   INNER JOIN known_variable kv ON mqcs.known_variable_id=kv.variable_id 
                   WHERE merged_file_history_id=$file_history_id 
                   ORDER BY kv.order_value";
  } else {
    $groupQuery = "SELECT DISTINCT kv.variable_name, kv.order_value 
                   FROM qc_summary qcs 
                   INNER JOIN known_variable kv ON qcs.known_variable_id=kv.variable_id 
                   WHERE daily_file_history_id=$file_history_id 
                   ORDER BY kv.order_value";
  }
  
  db_query($groupQuery);
  $varsByOrder = array();
  
  // Check for database errors
  if (db_error()) {
    echo '[]';
  } else {
    while ($varRow = db_get_row()) {
      $orderValue = $varRow->order_value;
      $groupPrefix = substr($orderValue, 0, 1); // Get first char only as group identifier
      
      if (!isset($varsByOrder[$groupPrefix])) {
        $varsByOrder[$groupPrefix] = array();
      }
      $varsByOrder[$groupPrefix][] = $varRow->variable_name;
    }
    
    // Convert to the format expected by JavaScript
    $jsGroups = array();
    foreach ($varsByOrder as $prefix => $vars) {
      $firstVar = reset($vars);
      
      // Simply use GetVariableTitle to determine the group name
      // It already handles all exact matches and numbered variants (SPD1, SPD2, etc.)
      $groupName = GetVariableTitle($firstVar);
      
      // Special handling for 'q' flag: split TS variables from other q variables
      if ($prefix === 'q') {
        $tsVars = array();
        $otherVars = array();
        
        foreach ($vars as $var) {
          if (strpos($var, 'TS') === 0) {
            $tsVars[] = $var;
          } else {
            $otherVars[] = $var;
          }
        }
        
        // Add TS variables group if any exist
        if (!empty($tsVars)) {
          array_push($jsGroups, array(
            'name' => 'Sea Temperature',
            'prefix' => 'q_ts',
            'vars' => $tsVars
          ));
        }
        
        // Add other 'q' variables group if any exist
        if (!empty($otherVars)) {
          array_push($jsGroups, array(
            'name' => 'Salinity & Conductivity',
            'prefix' => 'q_other',
            'vars' => $otherVars
          ));
        }
      }
      // Special handling for 'd' flag: split PL_CRS variables from other d variables
      elseif ($prefix === 'd') {
        $plCrsVars = array();
        $otherDVars = array();
        $windDirVars = array();
        $spdVars = array();

        foreach ($vars as $var) {
          if (strpos($var, 'PL_CRS') === 0) {
            $plCrsVars[] = $var;
          } elseif (preg_match('/^WSPD/i', $var)) {
            $spdVars[] = $var;
          } elseif (preg_match('/^WDIR/i', $var)) {
            $windDirVars[] = $var;
          } else {
            $otherDVars[] = $var;
          }
        }

        // Add PL_CRS variables group if any exist
        if (!empty($plCrsVars)) {
          array_push($jsGroups, array(
            'name' => GetVariableTitle($plCrsVars[0]),
            'prefix' => 'd_plcrs',
            'vars' => $plCrsVars
          ));
        }

        // Add SPD variables group if any exist
        if (!empty($spdVars)) {
          array_push($jsGroups, array(
            'name' => GetVariableTitle($spdVars[0]),
            'prefix' => 'd_spd',
            'vars' => $spdVars
          ));
        }

        // Add wind direction variables group if any exist
        if (!empty($windDirVars)) {
          array_push($jsGroups, array(
            'name' => GetVariableTitle($windDirVars[0]),
            'prefix' => 'd_wdir',
            'vars' => $windDirVars
          ));
        }

        // Add other 'd' variables group if any exist
        if (!empty($otherDVars)) {
          array_push($jsGroups, array(
            'name' => GetVariableTitle($otherDVars[0]),
            'prefix' => 'd_other',
            'vars' => $otherDVars
          ));
        }
      } else {
        array_push($jsGroups, array(
          'name' => $groupName,
          'prefix' => $prefix,
          'vars' => $vars
        ));
      }
    }
    
    echo json_encode($jsGroups);
  }
  
  echo ";\n\n";
  echo "console.log('Variable groups loaded from database:', variableGroupsFromDB);\n\n";
  echo "// Plot All button handler\n";
  echo "document.getElementById('plotAllBtn').addEventListener('click', function() {\n";
  echo "    const varsSelect = document.querySelector('select[name=\"vars[]\"]');\n";
  echo "    if (!varsSelect) {\n";
  echo "        alert('No variables available');\n";
  echo "        return;\n";
  echo "    }\n";
  echo "    \n";
  echo "    const availableVars = Array.from(varsSelect.options).map(opt => opt.value);\n";
  echo "    console.log('Available variables:', availableVars);\n";
  echo "    \n";
  echo "    const hsVal = document.getElementById('hs').value || '00:00';\n";
  echo "    const heVal = document.getElementById('he').value || '23:59';\n";
  echo "    \n";
  echo "    // Find which groups have at least one available variable\n";
  echo "    const activeGroups = [];\n";
  echo "    variableGroupsFromDB.forEach(group => {\n";
  echo "        const matchedVars = group.vars.filter(v => availableVars.includes(v));\n";
  echo "        \n";
  echo "        if (matchedVars.length > 0) {\n";
  echo "            activeGroups.push({ \n";
  echo "                name: group.name, \n";
  echo "                vars: matchedVars \n";
  echo "            });\n";
  echo "        }\n";
  echo "    });\n";
  echo "    \n";
  echo "    console.log('Active groups (from database order):', activeGroups);\n";
  echo "    \n";
  echo "    if (activeGroups.length === 0) {\n";
  echo "        alert('No matching variable groups found');\n";
  echo "        return;\n";
  echo "    }\n";
  echo "    \n";
  echo "    // Build URL with plot_all mode\n";
  echo "    let url = 'index.php?ship=" . urlencode($ship) . "' + \n";
  echo "              '&id=$ship_id' + \n";
  echo "              '&date=$date' +\n";
  echo "              '&order=$order' +\n";
  echo "              '&history_id=$file_history_id' +\n";
  echo "              '&mode=7' +\n";
  echo "              '&plot_all=1' +\n";
  echo "              '&hs=' + encodeURIComponent(hsVal) +\n";
  echo "              '&he=' + encodeURIComponent(heVal);\n";
  echo "    \n";
  echo "    url += '&var_groups=' + encodeURIComponent(JSON.stringify(activeGroups));\n";
  echo "    \n";
  echo "    console.log('Plot All URL:', url);\n";
  echo "    window.location.href = url;\n";
  echo "});\n";
  echo "</script>\n";

  // Simple plotting - check if plot_all mode
  $plotAll = isset($_REQUEST['plot_all']) && $_REQUEST['plot_all'] == '1';
  $varGroups = array();
  
  if ($plotAll && isset($_REQUEST['var_groups'])) {
    $varGroups = json_decode($_REQUEST['var_groups'], true);
    if (!is_array($varGroups)) {
      $varGroups = array();
    }
  }
  
  // If not plot_all mode and no variables selected, show message
  if (!$plotAll && empty($selectedVars)) {
    echo "<p style='text-align:center; color:#666;'>Select variables above and click Update Plot.</p>";
    return;
  }
  
  // Validate time format
  $validTime = function ($t) {
    return preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $t);
  };
  $filterStart = ($hs && $validTime($hs)) ? $hs : null;
  $filterEnd = ($he && $validTime($he)) ? $he : null;
  
  /* ---------- PLOT ALL MODE ---------- */
  if ($plotAll && !empty($varGroups)) {
    include_once 'include/plots/plot_all.php';
    RenderPlotAll($varGroups, $allVars, $filterStart, $filterEnd);
  }

  $allVarsSelected = !empty($allVars) && count($selectedVars) === count($allVars);
  if (!$plotAll && $allVarsSelected) {
    include_once 'include/plots/plot_all.php';
    $singleVarGroups = array();
    foreach ($selectedVars as $var) {
      $singleVarGroups[] = array(
        'name' => GetVariableTitle($var),
        'vars' => array($var)
      );
    }
    RenderPlotAll($singleVarGroups, $allVars, $filterStart, $filterEnd, 'All Variables');
    return;
  }

  /* ---------- SINGLE PLOT MODE ---------- */

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
      // Z flag is either 'Z' (1) or empty/null
      if ($zRow->z == 1) {
        $zFlagsByVar[$var] = 'Z';
      }
    }
  }

  // Process results
  $plotData = array();
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

    if (!empty($points)) {
      $plotData[$var] = array('points' => $points);
    }
  }

  if (empty($plotData)) {
    echo "<p style='text-align:center; color:red;'>No data available.</p>";
    return;
  }

  // Build units and long names
  $unitsMap = array();
  $longNames = array();
  foreach ($allVars as $var => $info) {
    $unitsMap[$var] = isset($info['units']) ? $info['units'] : '';
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

  // Output plot
  $jsPayload = json_encode(array(
    'plotData' => $plotData,
    'units' => $unitsMap,
    'longNames' => $longNames,
    'ship' => $ship,
    'shipName' => get_ship_name($ship_id),
    'date' => $date,
    'order' => $order,
    'hs' => $hs,
    'he' => $he
  ));

  echo '<div id="combinedChart" style="width:790px; height:520px; margin:20px auto; border:1px solid #ccc;"></div>';
    echo '<div style="text-align:center; margin:15px;">
      <button onclick="downloadCombinedPlot(\'combinedChart\')" style="padding:8px 16px; font-size:14px; cursor:pointer; margin-right:5px; background:transparent; color:#27ae60; border:2px solid #27ae60; border-radius:4px; font-weight:bold; transition:all 0.3s ease;" onmouseover="this.style.background=\'#27ae60\'; this.style.color=\'white\';" onmouseout="this.style.background=\'transparent\'; this.style.color=\'#27ae60\';">Download PNG</button>
      <button onclick="downloadCombinedCSV(\'combinedChart\')" style="padding:8px 16px; font-size:14px; cursor:pointer; margin-right:5px; background:transparent; color:#27ae60; border:2px solid #27ae60; border-radius:4px; font-weight:bold; transition:all 0.3s ease;" onmouseover="this.style.background=\'#27ae60\'; this.style.color=\'white\';" onmouseout="this.style.background=\'transparent\'; this.style.color=\'#27ae60\';">Download CSV</button>
      <button onclick="openZoomModal(\'combinedChart\')" style="padding:8px 16px; font-size:14px; cursor:pointer; margin-right:5px; background:transparent; color:#007cba; border:2px solid #007cba; border-radius:4px; transition:all 0.3s ease;" onmouseover="this.style.background=\'#007cba\'; this.style.color=\'white\';" onmouseout="this.style.background=\'transparent\'; this.style.color=\'#007cba\';">Zoom & Pan</button>
      <button onclick="openPolarModal()" style="padding:8px 16px; font-size:14px; cursor:pointer; margin-right:5px; background:transparent; color:#007cba; border:2px solid #007cba; border-radius:4px; transition:all 0.3s ease;" onmouseover="this.style.background=\'#007cba\'; this.style.color=\'white\';" onmouseout="this.style.background=\'transparent\'; this.style.color=\'#007cba\';">Polar Plot</button>
      <button onclick="openShipTrackModal()" style="padding:8px 16px; font-size:14px; cursor:pointer; background:transparent; color:#007cba; border:2px solid #007cba; border-radius:4px; transition:all 0.3s ease;" onmouseover="this.style.background=\'#007cba\'; this.style.color=\'white\';" onmouseout="this.style.background=\'transparent\'; this.style.color=\'#007cba\';">Ship Track</button>
      </div>';

  echo "<script src=\"https://d3js.org/d3.v6.min.js\"></script>";
  echo "<script src=\"js/combined-plot.js\"></script>";
  echo '<script src="js/zoom-pan.js"></script>';
  echo '<script src="js/polar-plot.js"></script>';
  echo '<script src="js/ship-track.js"></script>';
  echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />';
  echo '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
  echo "<script>
    // Store payload for zoom modal
    window.__originalChartData = $jsPayload;
    window.__originalPolarData = $jsPayload;
    
    document.addEventListener('DOMContentLoaded', () => {
      const payload = $jsPayload;
      if (typeof renderCombinedPlot === 'function') {
        renderCombinedPlot(payload, 'combinedChart');
      }
    });
  </script>";

  // ZOOM MODAL
  include 'include/plots/modals.php';
  RenderZoomModal();
  RenderPolarModal();
  RenderShipTrackModal();
  RenderModalFunctions();
}
