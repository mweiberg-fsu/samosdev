<?php
/**
 * Plot All Groups Module
 * Simple interface with just "Plot All Groups" button and time selection (mode=10)
 * Plots like variables in one figure
 */

function InsertPlotAllGroups()
{
  global $file_history_id, $order, $ship, $date, $ship_id, $SERVER;

  /* ---------- 1. GET ALL VARIABLES ---------- */
  $hs = isset($_REQUEST['hs']) ? $_REQUEST['hs'] : '00:00';
  $he = isset($_REQUEST['he']) ? $_REQUEST['he'] : '23:59';

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
  $availableVars = array();
  while ($r = db_get_row()) {
    if ($r->variable_name === 'time') continue;
    $ver = max((int) $r->process_version_no, 100);
    $allVars[$r->variable_name] = array('units' => $r->units, 'ver' => $ver);
    $availableVars[] = $r->variable_name;
  }

  $variableOptions = '';
  foreach ($availableVars as $varName) {
    $safeVarName = htmlspecialchars($varName, ENT_QUOTES, 'UTF-8');
    $variableOptions .= "<option value=\"$safeVarName\">$safeVarName</option>";
  }

  /* ---------- 2. SIMPLE FORM ---------- */
  $actionUrl = "index.php?ship=$ship&id=$ship_id&date=$date&order=$order&history_id=$file_history_id&mode=10";

  echo <<<FORM
<form method="POST" action="$actionUrl" id="plotAllGroupsForm">
  <table class="search" style="margin:20px auto; width:790px; border-collapse:collapse;">
    <tr>
      <th style="width:180px;">Time Range</th>
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
        <button type="button" id="plotAllGroupsBtn" style="padding:8px 25px; font-size:14px; background:#27ae60; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold;"
          onmouseover="this.style.background='#219a52'" onmouseout="this.style.background='#27ae60'">
          Plot All Groups
        </button>
      </td>
    </tr>
    <tr>
      <th>Variables</th>
      <td>
        <select multiple size="8" disabled style="width:280px; background:#f7f7f7; color:#555; cursor:not-allowed;">
          $variableOptions
        </select>
        <span style="color:#888; font-size:11px; margin-left:8px;">Display only</span>
      </td>
    </tr>
  </table>
</form>
FORM;

  // Output the JavaScript for Plot All Groups functionality
  echo "\n<script>\n";
  echo "// Build variable groupings dynamically from the database order values\n";
  echo "const variableGroupsFromDB = ";
  
  // Build variable groups based on database order (same logic as combined plot)
  if ($order > 100) {
    $groupQuery = "SELECT DISTINCT kv.variable_name, kv.order_value 
                   FROM merged_qc_summary mqcs 
                   INNER JOIN known_variable kv ON mqcs.known_variable_id = kv.variable_id 
                   WHERE merged_file_history_id = $file_history_id 
                   ORDER BY kv.order_value";
  } else {
    $groupQuery = "SELECT DISTINCT kv.variable_name, kv.order_value 
                   FROM qc_summary qcs 
                   INNER JOIN known_variable kv ON qcs.known_variable_id = kv.variable_id 
                   WHERE daily_file_history_id = $file_history_id 
                   ORDER BY kv.order_value";
  }

  db_query($groupQuery);
  $varsByOrder = array();
  while ($varRow = db_get_row()) {
    if ($varRow->variable_name === 'time') continue;

    $orderValue = $varRow->order_value;
    $groupPrefix = substr($orderValue, 0, 1);

    if (!isset($varsByOrder[$groupPrefix])) {
      $varsByOrder[$groupPrefix] = array();
    }
    $varsByOrder[$groupPrefix][] = $varRow->variable_name;
  }

  // Build JS groups array using combined plot grouping rules
  $jsGroups = array();

  foreach ($varsByOrder as $prefix => $vars) {
    if (empty($vars)) continue;

    $firstVar = reset($vars);
    $groupName = GetVariableTitle($firstVar);

    // Special handling for 'q' prefix: split TS vars from other q vars
    if ($prefix === 'q') {
      $tsVars = array();
      $otherVars = array();

      foreach ($vars as $var) {
        if (preg_match('/^TS/i', $var)) {
          $tsVars[] = $var;
        } else {
          $otherVars[] = $var;
        }
      }

      if (!empty($tsVars)) {
        array_push($jsGroups, array(
          'name' => 'Sea Temperature',
          'prefix' => 'q_ts',
          'vars' => $tsVars
        ));
      }

      if (!empty($otherVars)) {
        array_push($jsGroups, array(
          'name' => 'Salinity & Conductivity',
          'prefix' => 'q_other',
          'vars' => $otherVars
        ));
      }
    }
    // Special handling for 'd' prefix: split PL_CRS / SPD / WDIR / others
    elseif ($prefix === 'd') {
      $plCrsVars = array();
      $spdVars = array();
      $windDirVars = array();
      $otherDVars = array();

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

      if (!empty($plCrsVars)) {
        array_push($jsGroups, array(
          'name' => GetVariableTitle($plCrsVars[0]),
          'prefix' => 'd_plcrs',
          'vars' => $plCrsVars
        ));
      }

      if (!empty($spdVars)) {
        array_push($jsGroups, array(
          'name' => GetVariableTitle($spdVars[0]),
          'prefix' => 'd_spd',
          'vars' => $spdVars
        ));
      }

      if (!empty($windDirVars)) {
        array_push($jsGroups, array(
          'name' => GetVariableTitle($windDirVars[0]),
          'prefix' => 'd_wdir',
          'vars' => $windDirVars
        ));
      }

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
  echo ";\n\n";
  echo "console.log('Variable groups loaded:', variableGroupsFromDB);\n\n";
  echo "// Plot All Groups button handler\n";
  echo "document.getElementById('plotAllGroupsBtn').addEventListener('click', function() {\n";
  echo "    const hsVal = document.getElementById('hs').value || '00:00';\n";
  echo "    const heVal = document.getElementById('he').value || '23:59';\n";
  echo "    \n";
  echo "    const availableVars = " . json_encode($availableVars) . ";\n";
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
  echo "    console.log('Active groups:', activeGroups);\n";
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
  echo "              '&mode=10' +\n";
  echo "              '&plot_all=1' +\n";
  echo "              '&hs=' + encodeURIComponent(hsVal) +\n";
  echo "              '&he=' + encodeURIComponent(heVal);\n";
  echo "    \n";
  echo "    url += '&var_groups=' + encodeURIComponent(JSON.stringify(activeGroups));\n";
  echo "    \n";
  echo "    console.log('Plot All Groups URL:', url);\n";
  echo "    window.location.href = url;\n";
  echo "});\n";
  echo "</script>\n";

  // Check if plot_all mode
  $plotAll = isset($_REQUEST['plot_all']) && $_REQUEST['plot_all'] == '1';
  $varGroups = array();
  
  if ($plotAll && isset($_REQUEST['var_groups'])) {
    $varGroups = json_decode($_REQUEST['var_groups'], true);
    if (!is_array($varGroups)) {
      $varGroups = array();
    }
  }
  
  // If not plot_all mode, show message
  if (!$plotAll) {
    echo "<p style='text-align:center; color:#666;'>Click 'Plot All Groups' to display all variable groups.</p>";
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
    RenderPlotAll($varGroups, $allVars, $filterStart, $filterEnd, 'All Variable Groups');
    return;
  }
}
