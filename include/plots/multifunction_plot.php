<?php
/**
 * Multifunction Plot Module  
 * Handles full-featured multifunction plots with ship/date switching (mode=8)
 */

function InsertMultifunctionPlot()
{
  global $file_history_id, $order, $ship, $date, $ship_id, $SERVER;

  // Include the plot_all module for rendering multiple plots
  include 'include/plots/plot_all.php';
  
  /* ------1. PRE-DEFINED GROUPS ----- */
  $groups = array(
    'wind_dir' => array('PL_WDIR', 'PL_WDIR2', 'PL_WDIR3'),
    'wind_speed' => array('PL_WSPD', 'PL_WSPD2', 'PL_WSPD3'),
  );

  $selectedVars = array();
  $selectedGroup = isset($_REQUEST['group']) ? $_REQUEST['group'] : '';
  $hs = isset($_REQUEST['hs']) ? $_REQUEST['hs'] : '';
  $he = isset($_REQUEST['he']) ? $_REQUEST['he'] : '';

  if ($hs === '') $hs = '00:00';
  if ($he === '') $he = '23:59';

  if (!empty($selectedGroup) && isset($groups[$selectedGroup])) {
    $selectedVars = $groups[$selectedGroup];
  } elseif (!empty($_REQUEST['vars']) && is_array($_REQUEST['vars'])) {
    $selectedVars = array_filter($_REQUEST['vars'], 'trim');
  }

  /* ---------- 2. BUILD OPTIONS ---------- */
  $groupOptions = '<option value="">-- Choose a group --</option>';
  foreach ($groups as $key => $vars) {
    $sel = ($selectedGroup === $key) ? ' selected' : '';
    $label = ucwords(str_replace('_', ' ', $key));
    $groupOptions .= "<option value=\"$key\"$sel>$label</option>";
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

  /* ---------- 3. SHIP DICTIONARY FOR DROPDOWN ---------- */
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
  $displayDate = date('M j, Y', strtotime($sqlDateDash));

  /* ---------- 4. PERSISTENT FORM ---------- */
  $actionUrl = "index.php?ship=$ship&id=$ship_id&date=$date&order=$order&history_id=$file_history_id&mode=8";

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
                    onblur="formatTimeInput(this)" 
                    placeholder="00:00">
        To: <input type="text" name="he" id="he" value="$he" size="5" maxlength="5" 
                    style="text-align:center; font-family:monospace;" 
                    onblur="formatTimeInput(this)" 
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

<script>
// Form sync and button handlers
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

  // JavaScript for Plot All button
  echo "\n<script>\n";
  echo "const variableGroupsFromDB = ";
  
  if ($order > 100) {
    $groupQuery = "SELECT DISTINCT kv.variable_name, kv.order_value 
                   FROM merged_qc_summary mqcs 
                   INNER JOIN known_variable kv ON mqcs.known_variable_id=kv.variable_id 
                   WHERE merged_file_history_id=$file_history_id 
                   ORDER BY kv.order_value";
       }
       // Special handling for 'd' flag: split PL_CRS variables from other d variables
       elseif ($prefix === 'd') {
         $plCrsVars = array();
         $otherDVars = array();
         
         foreach ($vars as $var) {
           if (strpos($var, 'PL_CRS') === 0) {
             $plCrsVars[] = $var;
           } else {
             $otherDVars[] = $var;
           }
         }
         
         // Add PL_CRS variables group if any exist
         if (!empty($plCrsVars)) {
           array_push($jsGroups, array(
             'name' => 'Platform Course',
             'prefix' => 'd_plcrs',
             'vars' => $plCrsVars
           ));
         }
         
         // Add other 'd' variables group if any exist
         if (!empty($otherDVars)) {
           array_push($jsGroups, array(
             'name' => 'Earth Relative Wind Course',
             'prefix' => 'd_other',
             'vars' => $otherDVars
           ));
         }
       } else {
    $groupQuery = "SELECT DISTINCT kv.variable_name, kv.order_value 
                   FROM qc_summary qcs 
                   INNER JOIN known_variable kv ON qcs.known_variable_id=kv.variable_id 
                   WHERE daily_file_history_id=$file_history_id 
                   ORDER BY kv.order_value";
  }
  
  db_query($groupQuery);
  $varsByOrder = array();
  
  if (db_error()) {
    echo '[]';
  } else {
    while ($varRow = db_get_row()) {
      $orderValue = $varRow->order_value;
      $groupPrefix = substr($orderValue, 0, 1);
      
      if (!isset($varsByOrder[$groupPrefix])) {
        $varsByOrder[$groupPrefix] = array();
      }
      $varsByOrder[$groupPrefix][] = $varRow->variable_name;
    }
    
    $jsGroups = array();
    foreach ($varsByOrder as $prefix => $vars) {
      $firstVar = reset($vars);
      // Don't use GetVariableTitle as initial since it may add ' Group' suffix
      // Instead, use pattern-based logic first, then fall back
      $groupName = '';
      
      // Better group names based on patterns
      if (in_array('LAT', $vars) || in_array('lat', $vars)) {
        $groupName = 'Position (Lat/Lon)';
      } elseif (preg_grep('/^PL_HD/i', $vars)) {
        $groupName = 'Platform Heading';
      } elseif (preg_grep('/^PL_CRS/i', $vars)) {
        $groupName = 'Platform Course';
      } elseif (in_array('PL_WDIR', $vars) || preg_grep('/^WDIR_R/i', $vars)) {
        $groupName = 'Platform Relative Wind Direction';
      } elseif (preg_grep('/^WDIR_E/i', $vars) || preg_grep('/^WDIR\d/i', $vars) || preg_grep('/^WDIR$/i', $vars)) {
        $groupName = 'Earth Relative Wind Direction';
      } elseif (preg_grep('/^PL_WSPD/i', $vars)) {
        $groupName = 'Earth Relative Wind Speed';
      } elseif (preg_grep('/^WSPD_E/i', $vars)) {
        $groupName = 'Earth Relative Wind Speed';
      } elseif (preg_grep('/^WSPD_R/i', $vars)) {
        $groupName = 'Platform Relative Wind Speed';
      } elseif (preg_grep('/^WSPD/i', $vars) || in_array('SPD', $vars)) {
        $groupName = 'Earth Relative Wind Speed';
      } elseif (in_array('T', $vars) || in_array('TA', $vars)) {
        $groupName = 'Air Temperature';
      } elseif (in_array('TS', $vars) || in_array('SST', $vars)) {
        $groupName = 'Sea Temperature';
      } elseif (in_array('P', $vars) || in_array('PA', $vars)) {
        $groupName = 'Pressure';
      } elseif (in_array('RH', $vars)) {
        $groupName = 'Humidity';
      } elseif (in_array('RAD', $vars) || in_array('SW', $vars)) {
        $groupName = 'Radiation';
      } elseif (in_array('PL_SPD', $vars) || in_array('SOG', $vars)) {
        $groupName = 'Speed';
      }
      
      // If no pattern matched, use fallback
      if (empty($groupName)) {
        $groupName = !empty($firstVar) ? GetVariableTitle($firstVar) : 'Group ' . $prefix;
      }
      
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

        foreach ($vars as $var) {
          if (strpos($var, 'PL_CRS') === 0) {
            $plCrsVars[] = $var;
          } else {
            $otherDVars[] = $var;
          }
        }

        // Add PL_CRS variables group if any exist
        if (!empty($plCrsVars)) {
          array_push($jsGroups, array(
            'name' => 'Platform Course',
            'prefix' => 'd_plcrs',
            'vars' => $plCrsVars
          ));
        }

        // Add other 'd' variables group if any exist
        if (!empty($otherDVars)) {
          array_push($jsGroups, array(
            'name' => 'Earth Relative Wind Course',
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
  echo "              '&mode=8' +\n";
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
  echo "});\n\n";
  
  echo "document.getElementById('plotAllBtn').addEventListener('click', function() {\n";
  echo "    const varsSelect = document.querySelector('select[name=\"vars[]\"]');\n";
  echo "    if (!varsSelect) {\n";
  echo "        alert('No variables available');\n";
  echo "        return;\n";
  echo "    }\n";
  echo "    \n";
  echo "    const availableVars = Array.from(varsSelect.options).map(opt => opt.value);\n";
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
  echo "    const activeGroups = [];\n";
  echo "    variableGroupsFromDB.forEach(group => {\n";
  echo "        const matchedVars = group.vars.filter(v => availableVars.includes(v));\n";
  echo "        if (matchedVars.length > 0) {\n";
  echo "            activeGroups.push({ name: group.name, vars: matchedVars });\n";
  echo "        }\n";
  echo "    });\n";
  echo "    \n";
  echo "    if (activeGroups.length === 0) {\n";
  echo "        alert('No matching variable groups found');\n";
  echo "        return;\n";
  echo "    }\n";
  echo "    \n";
  echo "    let url = 'index.php?ship=' + encodeURIComponent(callSign) + \n";
  echo "              '&id=' + shipId + \n";
  echo "              '&date=' + dateVal +\n";
  echo "              '&order=$order' +\n";
  echo "              '&history_id=$file_history_id' +\n";
  echo "              '&mode=8' +\n";
  echo "              '&plot_all=1' +\n";
  echo "              '&hs=' + encodeURIComponent(hsVal) +\n";
  echo "              '&he=' + encodeURIComponent(heVal);\n";
  echo "    \n";
  echo "    url += '&var_groups=' + encodeURIComponent(JSON.stringify(activeGroups));\n";
  echo "    window.location.href = url;\n";
  echo "});\n";
  echo "</script>\n";

  // Check for plot_all mode
  $plotAll = isset($_REQUEST['plot_all']) && $_REQUEST['plot_all'] == '1';
  $varGroups = array();
  
  if ($plotAll && isset($_REQUEST['var_groups'])) {
    $varGroups = json_decode($_REQUEST['var_groups'], true);
    if (!is_array($varGroups)) {
      $varGroups = array();
    }
  }

  if (!$plotAll && empty($selectedVars)) {
    echo "<p style=\"text-align:center; color:#666;\">Select variables above.</p>";
    return;
  }

  $validTime = function ($t) {
    return preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $t);
  };
  $filterStart = ($hs && $validTime($hs)) ? $hs : null;
  $filterEnd = ($he && $validTime($he)) ? $he : null;

  // Plot All mode
  if ($plotAll && !empty($varGroups)) {
    RenderPlotAll($varGroups, $allVars, $filterStart, $filterEnd);
    return;
  }

  // Single plot mode would go here...
}
