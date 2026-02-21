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
    $sel = in_array($r->variable_name, $selectedVars) ? ' selected' : '';
    $varOptions .= "<option value=\"{$r->variable_name}\"$sel>{$r->variable_name}</option>";
  }

  /* ---------- 2. FORM WITH SHIP/DATE SWITCHING ---------- */
  
  // Build ship dictionary for dropdown
  $shipDict = array();
  $shipQuery = "SELECT ship_id, call_sign, ship_name FROM ship ORDER BY ship_name";
  db_query($shipQuery);
  
  while ($shipRow = db_get_row()) {
    $shipDict[$shipRow->ship_id] = array($shipRow->call_sign, $shipRow->ship_name);
  }
  
  if (empty($shipDict)) {
    $shipDict = array(
      71 => array('WDC9417', 'Atlantic Explorer'),
      32 => array('KAQP', 'Atlantis'),
      76 => array('WTED', 'Bell M. Shimada'),
      61 => array('WTEB', 'Fairweather'),
      146 => array('ZGOJ7', 'Falkor (too)'),
      81 => array('WTEK', 'Ferdinand Hassler'),
      57 => array('WTEO', 'Gordon Gunter'),
      39 => array('NEPP', 'Healy'),
      54 => array('WTDF', 'Henry B. Bigelow'),
      87 => array('VLMJ', 'Investigator'),
      12 => array('WDA7827', 'Kilo Moana'),
      49 => array('WTER', 'Nancy Foster'),
      86 => array('WARL', 'Neil Armstrong'),
      150 => array('VMIC', 'Nuyina'),
      66 => array('WTDH', 'Okeanos Explorer'),
      62 => array('WTDO', 'Oregon II'),
      51 => array('WTEP', 'Oscar Dyson'),
      64 => array('WTEE', 'Oscar Elton Sette'),
      69 => array('WTDL', 'Pisces'),
      60 => array('WTEF', 'Rainier'),
      85 => array('WTEG', 'Reuben Lasker'),
      77 => array('WSQ2674', 'Robert Gordon Sproul'),
      73 => array('KAOU', 'Roger Revelle'),
      55 => array('WTEC', 'Ron Brown'),
      91 => array('WSAF', 'Sally Ride'),
      149 => array('WDN7246', 'Sikuliaq'),
      153 => array('WDN7246C', 'Sikuliaq'),
      14 => array('KTDQ', 'T.G. Thompson'),
      75 => array('ZMFR', 'Tangaroa'),
      79 => array('WTEA', 'Thomas Jefferson'),
    );
  }
  
  uasort($shipDict, function($a, $b) {
    return strcasecmp($a[1], $b[1]);
  });
  
  $shipOptions = '';
  foreach ($shipDict as $sid => $info) {
    $callSign = $info[0];
    $shipName = $info[1];
    $sel = (strval($sid) == strval($ship_id)) ? ' selected' : '';
    $shipOptions .= "<option value=\"{$sid}|{$callSign}\"$sel>{$shipName} ({$callSign})</option>";
  }
  
  $dateOnly = substr($date, 0, 8);
  $sqlDateDash = substr($dateOnly, 0, 4) . '-' . substr($dateOnly, 4, 2) . '-' . substr($dateOnly, 6, 2);
  
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
      <th>Date &amp; Time Range</th>
      <td>
        <input type="date" name="date_picker" id="datePicker" value="$sqlDateDash" 
               style="padding:4px; font-family:monospace; font-size:14px;">
        &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
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
      <th>Switch Ship</th>
      <td>
        <select name="switch_ship" id="switchShipSelect" style="width:280px;">
          $shipOptions
        </select>
      </td>
    </tr>
    <tr>
      <th>Plot</th>
      <td>
        <button type="button" id="updatePlotBtn" style="padding:8px 25px; font-size:14px; background:#27ae60; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold;"
                onmouseover="this.style.background='#219a52'" onmouseout="this.style.background='#27ae60'">
          Plot Combined
        </button>
      </td>
    </tr>
  </table>
</form>

<script>
// Form sync
(function() {
  const urlParams = new URLSearchParams(window.location.search);
  const shipIdParam = urlParams.get('id');
  const shipParam = urlParams.get('ship');
  const dateParam = urlParams.get('date');
  const varsParam = urlParams.getAll('vars[]');
  
  // Sync ship dropdown
  const shipSelect = document.getElementById('switchShipSelect');
  if (shipSelect && (shipIdParam || shipParam)) {
    let matched = false;
    
    if (shipIdParam) {
      for (let i = 0; i < shipSelect.options.length; i++) {
        const option = shipSelect.options[i];
        const optionShipId = option.value.split('|')[0];
        if (optionShipId == shipIdParam) {
          option.selected = true;
          matched = true;
          break;
        }
      }
    }
    
    if (!matched && shipParam) {
      for (let i = 0; i < shipSelect.options.length; i++) {
        const option = shipSelect.options[i];
        const optionCallSign = option.value.split('|')[1];
        const cleanShipParam = shipParam.replace(/[A-Z]$/, '');
        const cleanOptionCallSign = optionCallSign ? optionCallSign.replace(/[A-Z]$/, '') : '';
        
        if (optionCallSign === shipParam || cleanOptionCallSign === cleanShipParam) {
          option.selected = true;
          matched = true;
          break;
        }
      }
    }
  }
  
  // Sync date picker
  const datePicker = document.getElementById('datePicker');
  if (datePicker && dateParam) {
    const dateStr = dateParam.substring(0, 8);
    const formattedDate = dateStr.substring(0, 4) + '-' + dateStr.substring(4, 6) + '-' + dateStr.substring(6, 8);
    datePicker.value = formattedDate;
  }
  
  // Sync variable selections
  const varsSelect = document.querySelector('select[name="vars[]"]');
  if (varsSelect && varsParam.length > 0) {
    for (let option of varsSelect.options) {
      if (varsParam.includes(option.value)) {
        option.selected = true;
      }
    }
  }
})();
</script>
FORM;

  echo "\n<script>\n";
  
  // Update Plot button handler
  echo "document.getElementById('updatePlotBtn').addEventListener('click', function() {\n";
  echo "    const shipSelect = document.getElementById('switchShipSelect');\n";
  echo "    let shipId = '$ship_id';\n";
  echo "    let callSign = '$ship';\n";
  echo "    \n";
  echo "    if (shipSelect && shipSelect.value) {\n";
  echo "        const parts = shipSelect.value.split('|');\n";
  echo "        shipId = parts[0];\n";
  echo "        callSign = parts[1];\n";
  echo "    }\n";
  echo "    \n";
  echo "    const datePicker = document.getElementById('datePicker');\n";
  echo "    let dateVal = '$date';\n";
  echo "    if (datePicker && datePicker.value) {\n";
  echo "        dateVal = datePicker.value.replace(/-/g, '') + '000001';\n";
  echo "    }\n";
  echo "    \n";
  echo "    const hsVal = document.getElementById('hs').value || '00:00';\n";
  echo "    const heVal = document.getElementById('he').value || '23:59';\n";
  echo "    \n";
  echo "    let url = 'index.php?ship=' + encodeURIComponent(callSign) + \n";
  echo "              '&id=' + shipId + \n";
  echo "              '&date=' + dateVal +\n";
  echo "              '&order=$order' +\n";
  echo "              '&history_id=$file_history_id' +\n";
  echo "              '&mode=7' +\n";
  echo "              '&hs=' + encodeURIComponent(hsVal) +\n";
  echo "              '&he=' + encodeURIComponent(heVal);\n";
  echo "    \n";
  echo "    const varsSelect = document.querySelector('select[name=\"vars[]\"]');\n";
  echo "    if (varsSelect) {\n";
  echo "        const selectedVars = Array.from(varsSelect.selectedOptions).map(opt => opt.value);\n";
  echo "        selectedVars.forEach(v => {\n";
  echo "            url += '&vars[]=' + encodeURIComponent(v);\n";
  echo "        });\n";
  echo "    }\n";
  echo "    \n";
  echo "    window.location.href = url;\n";
  echo "});\n";
  echo "</script>\n";

  // If no variables selected, wait for user action
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
  $hasPolarVariables = false;
  $polarCompatibleVars = array('PL_CRS', 'PL_CRS2', 'PL_CRS3', 'PL_HD', 'PL_HD2', 'PL_HD3',
                                'DIR', 'DIR2', 'DIR3', 'ER_WDIR', 'ER_WDIR2', 'ER_WDIR3',
                                'PL_WDIR', 'PL_WDIR2', 'PL_WDIR3', 'SPD', 'SPD1', 'SPD2', 'SPD3',
                                'PL_SPD', 'PL_SPD2', 'PL_SPD3');
  
  foreach ($allVars as $var => $info) {
    $unitsMap[$var] = isset($info['units']) ? $info['units'] : '';
    
    // Check if this variable is polar-compatible
    if (in_array($var, $polarCompatibleVars) || 
        preg_match('/(deg|degree|degrees|°)/i', $unitsMap[$var])) {
      $hasPolarVariables = true;
    }
    
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
    'he' => $he,
    'showTitle' => true
  ));

  echo '<style>
    .plot-menu-wrap { position: relative; width: 790px; margin: 20px auto; border: 1px solid #ccc; }
    .plot-menu { position: absolute; top: 8px; right: 8px; z-index: 25; }
    .plot-menu > summary { list-style: none; cursor: pointer; width: 30px; height: 30px; line-height: 28px; text-align: center; font-size: 18px; border: 1px solid #bbb; border-radius: 4px; background: #fff; }
    .plot-menu > summary::-webkit-details-marker { display: none; }
    .plot-menu-dropdown { position: absolute; right: 0; margin-top: 6px; background: #fff; border: 1px solid #bbb; border-radius: 6px; min-width: 150px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); padding: 6px; }
    .plot-menu-dropdown button { display: block; width: 100%; margin: 4px 0; padding: 7px 10px; font-size: 13px; text-align: left; cursor: pointer; border: 1px solid #d0d0d0; border-radius: 4px; background: #fff; }
    .plot-menu-dropdown button:hover { background: #f5f5f5; }
  </style>';

  echo '<div class="plot-menu-wrap">';
  echo '  <details class="plot-menu">';
  echo '    <summary title="Plot actions">&#9776;</summary>';
  echo '    <div class="plot-menu-dropdown">';
  echo '      <button onclick="downloadCombinedPlot(\'combinedChart\')">Download PNG</button>';
  echo '      <button onclick="downloadCombinedCSV(\'combinedChart\')">Download CSV</button>';
  echo '      <button onclick="openZoomModal(\'combinedChart\')">Zoom & Pan</button>';
  echo '      <button onclick="openShipTrackModal()">Ship Track</button>';
  echo '      <button onclick="togglePlotFlags(\'combinedChart\', this)">Hide Flags</button>';
  echo '    </div>';
  echo '  </details>';
  echo '  <div id="combinedChart" style="width:100%; min-height:520px;"></div>';
  echo '</div>';

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
