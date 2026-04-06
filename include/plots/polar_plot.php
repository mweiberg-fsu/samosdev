<?php
/**
 * Polar Plot Module
 * Renders one polar plot with configurable direction and color variables (mode=12)
 */

function InsertPolarPlot()
{
  global $file_history_id, $order, $ship, $date, $ship_id, $SERVER;

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
  while ($r = db_get_row()) {
    if ($r->variable_name === 'time') continue;
    $allVars[$r->variable_name] = array(
      'units' => $r->units,
      'ver' => max((int) $r->process_version_no, 100)
    );
  }

  $dirVars = array();
  $colorVars = array();

  foreach ($allVars as $varName => $meta) {
    if (preg_match('/^(DIR\d*|PL_?WDIR\d*)$/i', $varName)) {
      $dirVars[] = $varName;
    }
    if (preg_match('/^(PL_?WSPD\d*|P\d*|T\d*|RH\d*|TS\d*|SSPS\d*|CNDC\d*|PRECIP\d*|RAD_SW\d*|RAD_LW\d*|RAD_PAR\d*)$/i', $varName)) {
      $colorVars[] = $varName;
    }
  }

  natcasesort($dirVars);
  natcasesort($colorVars);
  $dirVars = array_values($dirVars);
  $colorVars = array_values($colorVars);

  $selectedDirVar = isset($_REQUEST['dir_var']) ? $_REQUEST['dir_var'] : (isset($dirVars[0]) ? $dirVars[0] : '');
  $selectedColorVar = isset($_REQUEST['color_var']) ? $_REQUEST['color_var'] : (isset($colorVars[0]) ? $colorVars[0] : '');

  $dirOptions = '';
  foreach ($dirVars as $v) {
    $sel = ($v === $selectedDirVar) ? ' selected' : '';
    $dirOptions .= "<option value=\"$v\"$sel>$v</option>";
  }

  $colorOptions = '';
  foreach ($colorVars as $v) {
    $sel = ($v === $selectedColorVar) ? ' selected' : '';
    $colorOptions .= "<option value=\"$v\"$sel>$v</option>";
  }

  $actionUrl = "index.php?ship=$ship&id=$ship_id&date=$date&order=$order&history_id=$file_history_id&mode=12";

  echo <<<FORM
<form method="GET" action="index.php" id="polarPlotForm">
  <input type="hidden" name="ship" value="$ship">
  <input type="hidden" name="id" value="$ship_id">
  <input type="hidden" name="date" value="$date">
  <input type="hidden" name="order" value="$order">
  <input type="hidden" name="history_id" value="$file_history_id">
  <input type="hidden" name="mode" value="12">

  <table class="search" style="margin:20px auto; width:790px; border-collapse:collapse;">
    <tr>
      <th style="width:180px;">Direction Variable</th>
      <td>
        <select name="dir_var" style="width:220px;">
          $dirOptions
        </select>
      </td>
    </tr>
    <tr>
      <th>Gradient Variable</th>
      <td>
        <select name="color_var" style="width:220px;">
          $colorOptions
        </select>
      </td>
    </tr>
    <tr>
      <th>Time Range</th>
      <td>
        From: <input type="text" name="hs" value="$hs" size="5" maxlength="5" style="text-align:center; font-family:monospace;" placeholder="00:00">
        To: <input type="text" name="he" value="$he" size="5" maxlength="5" style="text-align:center; font-family:monospace;" placeholder="23:59">
        <span style="color:#888; font-size:11px; margin-left:5px;">UTC</span>
      </td>
    </tr>
    <tr>
      <th>Plot</th>
      <td>
        <button type="submit" style="padding:8px 25px; font-size:14px; background:#27ae60; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold;"
                onmouseover="this.style.background='#219a52'" onmouseout="this.style.background='#27ae60'">
          Update Polar Plot
        </button>
      </td>
    </tr>
  </table>
</form>
FORM;

  if (empty($dirVars) || empty($colorVars)) {
    echo '<div style="width:790px; margin:20px auto; padding:16px; border:1px solid #ddd; background:#fafafa; color:#555;">';
    echo 'Missing required variables for polar plotting. Need at least one DIR* variable and one supported gradient variable.';
    echo '</div>';
    return;
  }

  if (!isset($allVars[$selectedDirVar]) || !isset($allVars[$selectedColorVar])) {
    echo '<div style="width:790px; margin:20px auto; padding:16px; border:1px solid #ddd; background:#fafafa; color:#555;">';
    echo 'Select valid variables and click Update Polar Plot.';
    echo '</div>';
    return;
  }

  $selectedMeta = array(
    $selectedDirVar => $allVars[$selectedDirVar],
    $selectedColorVar => $allVars[$selectedColorVar]
  );

  $urlMap = array();
  foreach ($selectedMeta as $var => $info) {
    $baseParams = http_build_query(array(
      'ship' => $ship,
      'date' => $date,
      'order' => $order,
      'var' => $var,
      'version_no' => $info['ver'],
      'units' => $info['units'],
      'fbound' => 1,
      'hs' => $hs,
      'he' => $he,
      'mode' => 12,
    ));
    $urlMap[$var] = "$SERVER/charts/plot_chart.php?$baseParams";
  }

  $mh = curl_multi_init();
  $curlHandles = array();
  foreach ($urlMap as $var => $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_multi_add_handle($mh, $ch);
    $curlHandles[$var] = $ch;
  }

  $running = null;
  do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
  } while ($running > 0);

  $rawResults = array();
  foreach ($curlHandles as $var => $ch) {
    $rawResults[$var] = curl_multi_getcontent($ch);
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
  }
  curl_multi_close($mh);

  $plotData = array();
  foreach ($rawResults as $var => $jsonText) {
    $series = json_decode($jsonText, true);
    if (!is_array($series)) {
      $series = array();
    }

    $points = array();
    foreach ($series as $ts => $value) {
      $points[] = array(
        'date' => $ts,
        'value' => $value,
      );
    }

    usort($points, function($a, $b) {
      return strcmp($a['date'], $b['date']);
    });

    $plotData[$var] = array('points' => $points);
  }

  $unitsMap = array(
    $selectedDirVar => $allVars[$selectedDirVar]['units'],
    $selectedColorVar => $allVars[$selectedColorVar]['units'],
  );

  $payload = json_encode(array(
    'plotData' => $plotData,
    'units' => $unitsMap,
    'longNames' => array(
      $selectedDirVar => $selectedDirVar,
      $selectedColorVar => $selectedColorVar,
    ),
    'dirVar' => $selectedDirVar,
    'colorVar' => $selectedColorVar,
    'ship' => $ship,
    'shipName' => get_ship_name($ship_id),
    'date' => $date,
    'order' => $order,
    'hs' => $hs,
    'he' => $he,
  ));

  echo '<style>
    .polar-plot-wrap { position: relative; width: 790px; margin: 20px auto; border: 1px solid #d5d5d5; background: #fff; }
    .polar-plot-menu { position: absolute; top: 8px; right: 8px; z-index: 10; }
    .polar-plot-menu > summary { list-style: none; cursor: pointer; width: 30px; height: 30px; line-height: 28px; text-align: center; font-size: 18px; border: 1px solid #bbb; border-radius: 4px; background: #fff; }
    .polar-plot-menu > summary::-webkit-details-marker { display: none; }
    .polar-plot-menu-dropdown { position: absolute; right: 0; margin-top: 6px; background: #fff; border: 1px solid #bbb; border-radius: 6px; min-width: 150px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); padding: 6px; }
    .polar-plot-menu-dropdown button { display: block; width: 100%; margin: 4px 0; padding: 7px 10px; font-size: 13px; text-align: left; cursor: pointer; border: 1px solid #d0d0d0; border-radius: 4px; background: #fff; }
    .polar-plot-menu-dropdown button:hover { background: #f5f5f5; }
  </style>';

  echo '<div class="polar-plot-wrap">';
  echo '  <details class="polar-plot-menu">';
  echo '    <summary title="Plot actions">&#9776;</summary>';
  echo '    <div class="polar-plot-menu-dropdown">';
  echo '      <button onclick="downloadPolarSinglePng()">Download PNG</button>';
  echo '      <button onclick="downloadPolarSingleCsv()">Download CSV</button>';
  echo '      <button onclick="openPolarZoomModal()">Zoom &amp; Pan</button>';
  echo '    </div>';
  echo '  </details>';
  echo '  <div id="polarSingleChart" style="width:790px; height:620px;"></div>';
  echo '</div>';
  echo "<script src=\"https://d3js.org/d3.v6.min.js\"></script>";
  echo "<script>\n";
  echo "(function(){\n";
  echo "  const payload = $payload;\n";
  echo "  const container = document.getElementById('polarSingleChart');\n";
  echo "  if (!container || !window.d3) return;\n";
  echo "\n";
  echo "  window.__polarSingleExport = null;\n";
  echo "\n";
  echo "  window.downloadPolarSinglePng = function() {\n";
  echo "    const chartContainer = document.getElementById('polarSingleChart');\n";
  echo "    if (!chartContainer) return;\n";
  echo "    const svg = chartContainer.querySelector('svg');\n";
  echo "    if (!svg) return;\n";
  echo "\n";
  echo "    const svgClone = svg.cloneNode(true);\n";
  echo "    const width = Number(svgClone.getAttribute('width')) || 790;\n";
  echo "    const height = Number(svgClone.getAttribute('height')) || 620;\n";
  echo "    const serializer = new XMLSerializer();\n";
  echo "    const svgString = serializer.serializeToString(svgClone);\n";
  echo "    const blob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' });\n";
  echo "    const url = URL.createObjectURL(blob);\n";
  echo "\n";
  echo "    const img = new Image();\n";
  echo "    img.onload = function() {\n";
  echo "      const canvas = document.createElement('canvas');\n";
  echo "      canvas.width = width;\n";
  echo "      canvas.height = height;\n";
  echo "      const ctx = canvas.getContext('2d');\n";
  echo "      ctx.fillStyle = '#ffffff';\n";
  echo "      ctx.fillRect(0, 0, width, height);\n";
  echo "      ctx.drawImage(img, 0, 0);\n";
  echo "\n";
  echo "      canvas.toBlob(function(pngBlob) {\n";
  echo "        if (!pngBlob) return;\n";
  echo "        const info = window.__polarSingleExport || {};\n";
  echo "        const shipName = String((info.payload || {}).shipName || (info.payload || {}).ship || 'polar-plot').replace(/[^a-zA-Z0-9]/g, '_');\n";
  echo "        const dateStr = String((info.payload || {}).date || '').substring(0, 8) || new Date().toISOString().slice(0, 10).replace(/-/g, '');\n";
  echo "        const filename = shipName + '_' + dateStr + '_' + (info.dirVar || 'DIR') + '_polar.png';\n";
  echo "\n";
  echo "        const downloadUrl = URL.createObjectURL(pngBlob);\n";
  echo "        const link = document.createElement('a');\n";
  echo "        link.download = filename;\n";
  echo "        link.href = downloadUrl;\n";
  echo "        link.click();\n";
  echo "        URL.revokeObjectURL(downloadUrl);\n";
  echo "      });\n";
  echo "\n";
  echo "      URL.revokeObjectURL(url);\n";
  echo "    };\n";
  echo "\n";
  echo "    img.src = url;\n";
  echo "  };\n";
  echo "\n";
  echo "  window.downloadPolarSingleCsv = function() {\n";
  echo "    const info = window.__polarSingleExport;\n";
  echo "    if (!info || !Array.isArray(info.merged) || !info.merged.length) return;\n";
  echo "\n";
  echo "    const escapeCsv = (v) => {\n";
  echo "      const s = String(v == null ? '' : v);\n";
  echo "      if (s.indexOf(',') >= 0 || s.indexOf('\\\"') >= 0 || s.indexOf('\\n') >= 0) {\n";
  echo "        return '\\\"' + s.replace(/\\\"/g, '\\\"\\\"') + '\\\"';\n";
  echo "      }\n";
  echo "      return s;\n";
  echo "    };\n";
  echo "\n";
  echo "    let csv = 'Timestamp,' + escapeCsv(info.dirVar + '_raw_deg') + ',' + escapeCsv(info.dirVar + '_smoothed_deg') + ',' + escapeCsv(info.colorVar) + '\\n';\n";
  echo "    info.merged.forEach(p => {\n";
  echo "      const raw = Number.isFinite(p.dirRaw) ? p.dirRaw.toFixed(3) : '';\n";
  echo "      const smooth = Number.isFinite(p.dir) ? p.dir.toFixed(3) : '';\n";
  echo "      const colorVal = Number.isFinite(p.colorValue) ? p.colorValue.toFixed(3) : '';\n";
  echo "      csv += escapeCsv(p.date) + ',' + escapeCsv(raw) + ',' + escapeCsv(smooth) + ',' + escapeCsv(colorVal) + '\\n';\n";
  echo "    });\n";
  echo "\n";
  echo "    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });\n";
  echo "    const url = URL.createObjectURL(blob);\n";
  echo "    const shipName = String((info.payload || {}).shipName || (info.payload || {}).ship || 'polar-plot').replace(/[^a-zA-Z0-9]/g, '_');\n";
  echo "    const dateStr = String((info.payload || {}).date || '').substring(0, 8) || new Date().toISOString().slice(0, 10).replace(/-/g, '');\n";
  echo "    const filename = shipName + '_' + dateStr + '_' + (info.dirVar || 'DIR') + '_polar.csv';\n";
  echo "\n";
  echo "    const link = document.createElement('a');\n";
  echo "    link.href = url;\n";
  echo "    link.download = filename;\n";
  echo "    link.click();\n";
  echo "    URL.revokeObjectURL(url);\n";
  echo "  };\n";
  echo "\n";
  echo "  const parseTime = d3.utcParse('%Y-%m-%d %H:%M:%S');\n";
  echo "  const formatHoverTime = d3.utcFormat('%Y-%m-%d %H:%M');\n";
  echo "  const dirVar = payload.dirVar;\n";
  echo "  const colorVar = payload.colorVar;\n";
  echo "  const unitsMap = payload.units || {};\n";
  echo "  const dirUnit = String(unitsMap[dirVar] || 'deg').split(' (')[0];\n";
  echo "  const colorUnit = String(unitsMap[colorVar] || '').split(' (')[0];\n";
  echo "\n";
  echo "  const dirSeriesRaw = ((payload.plotData || {})[dirVar] || {}).points || [];\n";
  echo "  const colorSeriesRaw = ((payload.plotData || {})[colorVar] || {}).points || [];\n";
  echo "\n";
  echo "  const normalizeValue = (v) => {\n";
  echo "    const n = v == null ? null : Number(v);\n";
  echo "    if (n == null || Number.isNaN(n) || n === -9999 || n === -8888) return null;\n";
  echo "    return n;\n";
  echo "  };\n";
  echo "\n";
  echo "  const dirSeries = dirSeriesRaw.map(p => ({\n";
  echo "    date: p.date,\n";
  echo "    time: parseTime(p.date),\n";
  echo "    value: normalizeValue(p.value)\n";
  echo "  })).filter(p => p.time && p.value != null).sort((a, b) => a.time - b.time);\n";
  echo "\n";
  echo "  const colorMap = new Map();\n";
  echo "  colorSeriesRaw.forEach(p => {\n";
  echo "    const cv = normalizeValue(p.value);\n";
  echo "    if (cv != null) colorMap.set(p.date, cv);\n";
  echo "  });\n";
  echo "\n";
  echo "  const mergedRaw = dirSeries.map(p => ({\n";
  echo "    date: p.date,\n";
  echo "    time: p.time,\n";
  echo "    dirRaw: ((p.value % 360) + 360) % 360,\n";
  echo "    colorValue: colorMap.has(p.date) ? colorMap.get(p.date) : null\n";
  echo "  })).filter(p => p.colorValue != null);\n";
  echo "\n";
  echo "  if (!mergedRaw.length) {\n";
  echo "    container.innerHTML = '<div style=\"padding:20px; color:#b00020; text-align:center;\">No overlapping DIR and wind speed data found for the selected range.</div>';\n";
  echo "    return;\n";
  echo "  }\n";
  echo "\n";
  echo "  const normalize360 = (deg) => ((deg % 360) + 360) % 360;\n";
  echo "  const shortestDelta = (curr, prev) => {\n";
  echo "    let d = curr - prev;\n";
  echo "    if (d > 180) d -= 360;\n";
  echo "    if (d < -180) d += 360;\n";
  echo "    return d;\n";
  echo "  };\n";
  echo "\n";
  echo "  const unwrapped = [];\n";
  echo "  let runningDir = mergedRaw[0].dirRaw;\n";
  echo "  unwrapped.push({ ...mergedRaw[0], dirUnwrapped: runningDir });\n";
  echo "  for (let i = 1; i < mergedRaw.length; i++) {\n";
  echo "    const delta = shortestDelta(mergedRaw[i].dirRaw, mergedRaw[i - 1].dirRaw);\n";
  echo "    runningDir += delta;\n";
  echo "    unwrapped.push({ ...mergedRaw[i], dirUnwrapped: runningDir });\n";
  echo "  }\n";
  echo "\n";
  echo "  const smoothRadius = 2;\n";
  echo "  const maxSmoothGapMs = 10 * 60 * 1000;\n";
  echo "  const merged = unwrapped.map((p, idx) => {\n";
  echo "    let sum = 0;\n";
  echo "    let count = 0;\n";
  echo "    const centerTime = p.time.getTime();\n";
  echo "    for (let j = Math.max(0, idx - smoothRadius); j <= Math.min(unwrapped.length - 1, idx + smoothRadius); j++) {\n";
  echo "      if (Math.abs(unwrapped[j].time.getTime() - centerTime) <= maxSmoothGapMs) {\n";
  echo "        sum += unwrapped[j].dirUnwrapped;\n";
  echo "        count += 1;\n";
  echo "      }\n";
  echo "    }\n";
  echo "    const smoothedUnwrapped = count ? (sum / count) : p.dirUnwrapped;\n";
  echo "    return {\n";
  echo "      ...p,\n";
  echo "      dirUnwrappedSmoothed: smoothedUnwrapped,\n";
  echo "      dir: normalize360(smoothedUnwrapped)\n";
  echo "    };\n";
  echo "  });\n";
  echo "\n";
  echo "  window.__polarSingleExport = {\n";
  echo "    merged,\n";
  echo "    dirVar,\n";
  echo "    colorVar,\n";
  echo "    payload\n";
  echo "  };\n";
  echo "\n";
  echo "  const width = container.clientWidth || 790;\n";
  echo "  const height = container.clientHeight || 620;\n";
  echo "  const margin = { top: 40, right: 110, bottom: 40, left: 40 };\n";
  echo "  const cx = (width - margin.left - margin.right) / 2 + margin.left;\n";
  echo "  const plotYOffset = 20;\n";
  echo "  const cy = (height - margin.top - margin.bottom) / 2 + margin.top + plotYOffset;\n";
  echo "  const outerRadius = Math.min(width - margin.left - margin.right, height - margin.top - margin.bottom) / 2 - 10;\n";
  echo "  const innerRadius = Math.max(24, outerRadius * 0.16);\n";
  echo "\n";
  echo "  const timeExtent = d3.extent(merged, d => d.time);\n";
  echo "  const colorExtent = d3.extent(merged, d => d.colorValue);\n";
  echo "\n";
  echo "  const r = d3.scaleTime().domain(timeExtent).range([innerRadius, outerRadius]);\n";
  echo "  const colorScale = d3.scaleSequential(d3.interpolateTurbo).domain(colorExtent);\n";
  echo "\n";
  echo "  const angleToXY = (deg, radius) => {\n";
  echo "    const a = (deg / 180) * Math.PI - Math.PI / 2;\n";
  echo "    return { x: Math.cos(a) * radius, y: Math.sin(a) * radius };\n";
  echo "  };\n";
  echo "\n";
  echo "  const splitSegments = (points) => {\n";
  echo "    const out = [];\n";
  echo "    let current = [];\n";
  echo "    for (let i = 0; i < points.length; i++) {\n";
  echo "      const p = points[i];\n";
  echo "      if (current.length === 0) { current.push(p); continue; }\n";
  echo "      const prev = current[current.length - 1];\n";
  echo "      const dt = Math.abs(p.time - prev.time);\n";
  echo "      const gapBreak = dt > (5 * 60 * 1000);\n";
  echo "      const jumpBreak = Math.abs(p.dirUnwrappedSmoothed - prev.dirUnwrappedSmoothed) > 120;\n";
  echo "      if (gapBreak || jumpBreak) {\n";
  echo "        if (current.length > 1) out.push(current);\n";
  echo "        current = [p];\n";
  echo "      } else {\n";
  echo "        current.push(p);\n";
  echo "      }\n";
  echo "    }\n";
  echo "    if (current.length > 1) out.push(current);\n";
  echo "    return out;\n";
  echo "  };\n";
  echo "\n";
  echo "  const svg = d3.select(container).append('svg')\n";
  echo "    .attr('width', width)\n";
  echo "    .attr('height', height);\n";
  echo "\n";
  echo "  const tooltip = (() => {\n";
  echo "    const existing = d3.select('#polar-single-tooltip');\n";
  echo "    if (!existing.empty()) return existing;\n";
  echo "    return d3.select('body')\n";
  echo "      .append('div')\n";
  echo "      .attr('id', 'polar-single-tooltip')\n";
  echo "      .style('position', 'absolute')\n";
  echo "      .style('background', 'rgba(22, 32, 45, 0.92)')\n";
  echo "      .style('border', '1px solid rgba(255,255,255,0.2)')\n";
  echo "      .style('border-radius', '8px')\n";
  echo "      .style('padding', '8px 10px')\n";
  echo "      .style('color', '#f7f9fb')\n";
  echo "      .style('font-size', '12px')\n";
  echo "      .style('pointer-events', 'none')\n";
  echo "      .style('opacity', 0)\n";
  echo "      .style('z-index', 10001);\n";
  echo "  })();\n";
  echo "\n";
  echo "  const g = svg.append('g').attr('transform', 'translate(' + cx + ',' + cy + ')');\n";
  echo "\n";
  echo "  const timeTicks = r.ticks(4);\n";
  echo "  g.selectAll('.grid-circle')\n";
  echo "    .data(timeTicks)\n";
  echo "    .enter().append('circle')\n";
  echo "    .attr('r', d => r(d))\n";
  echo "    .attr('fill', 'none')\n";
  echo "    .attr('stroke', '#b7c7d6')\n";
  echo "    .attr('stroke-dasharray', '4 6');\n";
  echo "\n";
  echo "  const directions = [0, 90, 180, 270];\n";
  echo "  const labels = {0:'N',90:'E',180:'S',270:'W'};\n";
  echo "  g.selectAll('.angle-line')\n";
  echo "    .data(directions)\n";
  echo "    .enter().append('line')\n";
  echo "    .attr('x1', 0).attr('y1', 0)\n";
  echo "    .attr('x2', d => angleToXY(d, outerRadius).x)\n";
  echo "    .attr('y2', d => angleToXY(d, outerRadius).y)\n";
  echo "    .attr('stroke', '#8fa5ba');\n";
  echo "\n";
  echo "  g.selectAll('.angle-label')\n";
  echo "    .data(directions)\n";
  echo "    .enter().append('text')\n";
  echo "    .attr('x', d => angleToXY(d, outerRadius + 16).x)\n";
  echo "    .attr('y', d => angleToXY(d, outerRadius + 16).y)\n";
  echo "    .attr('text-anchor', 'middle')\n";
  echo "    .attr('dominant-baseline', 'middle')\n";
  echo "    .style('font-size', '12px')\n";
  echo "    .style('font-weight', 'bold')\n";
  echo "    .text(d => labels[d]);\n";
  echo "\n";
  echo "  const segments = splitSegments(merged);\n";
  echo "  segments.forEach(seg => {\n";
  echo "    for (let i = 1; i < seg.length; i++) {\n";
  echo "      const a = seg[i - 1];\n";
  echo "      const b = seg[i];\n";
  echo "      const pa = angleToXY(a.dir, r(a.time));\n";
  echo "      const pb = angleToXY(b.dir, r(b.time));\n";
  echo "      const c = (a.colorValue + b.colorValue) / 2;\n";
  echo "      g.append('line')\n";
  echo "        .attr('x1', pa.x).attr('y1', pa.y)\n";
  echo "        .attr('x2', pb.x).attr('y2', pb.y)\n";
  echo "        .attr('stroke', colorScale(c))\n";
  echo "        .attr('stroke-width', 2.2)\n";
  echo "        .attr('stroke-linecap', 'round');\n";
  echo "    }\n";
  echo "  });\n";
  echo "\n";
  echo "  g.selectAll('.polar-hover-point')\n";
  echo "    .data(merged)\n";
  echo "    .enter()\n";
  echo "    .append('circle')\n";
  echo "    .attr('class', 'polar-hover-point')\n";
  echo "    .attr('cx', d => angleToXY(d.dir, r(d.time)).x)\n";
  echo "    .attr('cy', d => angleToXY(d.dir, r(d.time)).y)\n";
  echo "    .attr('r', 6)\n";
  echo "    .attr('fill', 'transparent')\n";
  echo "    .style('cursor', 'crosshair')\n";
  echo "    .on('mousemove', (event, d) => {\n";
  echo "      const dirText = Number.isFinite(d.dir) ? d.dir.toFixed(2) : '';\n";
  echo "      const colorText = Number.isFinite(d.colorValue) ? d.colorValue.toFixed(2) : '';\n";
  echo "      const dirLabel = dirUnit ? (dirText + ' ' + dirUnit) : dirText;\n";
  echo "      const colorLabel = colorUnit ? (colorText + ' ' + colorUnit) : colorText;\n";
  echo "\n";
  echo "      tooltip\n";
  echo "        .style('opacity', 1)\n";
  echo "        .html('<div style=\"font-weight:700; margin-bottom:4px; color:#9fd2ff;\">' + formatHoverTime(d.time) + '</div>' +\n";
  echo "              '<div>' + dirVar + ': ' + dirLabel + '</div>' +\n";
  echo "              '<div>' + colorVar + ': ' + colorLabel + '</div>')\n";
  echo "        .style('left', (event.pageX + 12) + 'px')\n";
  echo "        .style('top', (event.pageY - 18) + 'px');\n";
  echo "    })\n";
  echo "    .on('mouseleave', () => {\n";
  echo "      tooltip.style('opacity', 0);\n";
  echo "    });\n";
  echo "\n";
  echo "  const startPoint = merged[0];\n";
  echo "  const endPoint = merged[merged.length - 1];\n";
  echo "\n";
  echo "  const drawEndpoint = (point, label, fillColor) => {\n";
  echo "    if (!point) return;\n";
  echo "    const pos = angleToXY(point.dir, r(point.time));\n";
  echo "\n";
  echo "    g.append('circle')\n";
  echo "      .attr('cx', pos.x)\n";
  echo "      .attr('cy', pos.y)\n";
  echo "      .attr('r', 5.5)\n";
  echo "      .attr('fill', fillColor)\n";
  echo "      .attr('stroke', '#ffffff')\n";
  echo "      .attr('stroke-width', 1.8);\n";
  echo "\n";
  echo "    g.append('text')\n";
  echo "      .attr('x', pos.x + 8)\n";
  echo "      .attr('y', pos.y - 8)\n";
  echo "      .style('font-size', '11px')\n";
  echo "      .style('font-weight', '700')\n";
  echo "      .style('fill', '#2c3e50')\n";
  echo "      .text(label);\n";
  echo "  };\n";
  echo "\n";
  echo "  drawEndpoint(startPoint, 'Start', '#1e88e5');\n";
  echo "  drawEndpoint(endPoint, 'End', '#e53935');\n";
  echo "\n";
  echo "  const title = (payload.shipName || payload.ship || '') + ' | ' + payload.date + ' | ' + payload.hs + '-' + payload.he + ' UTC';\n";
  echo "  svg.append('text')\n";
  echo "    .attr('x', width / 2)\n";
  echo "    .attr('y', 22)\n";
  echo "    .attr('text-anchor', 'middle')\n";
  echo "    .style('font-size', '14px')\n";
  echo "    .style('font-weight', 'bold')\n";
  echo "    .text('Polar Plot: ' + dirVar + ' colored by ' + colorVar);\n";
  echo "\n";
  echo "  svg.append('text')\n";
  echo "    .attr('x', width / 2)\n";
  echo "    .attr('y', 38)\n";
  echo "    .attr('text-anchor', 'middle')\n";
  echo "    .style('font-size', '11px')\n";
  echo "    .style('fill', '#4b5d70')\n";
  echo "    .text(title);\n";
  echo "\n";
  echo "  const legendX = width - 70;\n";
  echo "  const legendY = 100;\n";
  echo "  const legendH = 250;\n";
  echo "\n";
  echo "  const gradientId = 'polarColorScale';\n";
  echo "  const defs = svg.append('defs');\n";
  echo "  const grad = defs.append('linearGradient')\n";
  echo "    .attr('id', gradientId)\n";
  echo "    .attr('x1', '0%').attr('y1', '100%')\n";
  echo "    .attr('x2', '0%').attr('y2', '0%');\n";
  echo "\n";
  echo "  for (let i = 0; i <= 10; i++) {\n";
  echo "    const t = i / 10;\n";
  echo "    grad.append('stop')\n";
  echo "      .attr('offset', (t * 100) + '%')\n";
  echo "      .attr('stop-color', d3.interpolateTurbo(t));\n";
  echo "  }\n";
  echo "\n";
  echo "  svg.append('rect')\n";
  echo "    .attr('x', legendX)\n";
  echo "    .attr('y', legendY)\n";
  echo "    .attr('width', 16)\n";
  echo "    .attr('height', legendH)\n";
  echo "    .attr('fill', 'url(#' + gradientId + ')')\n";
  echo "    .attr('stroke', '#777');\n";
  echo "\n";
  echo "  const legendScale = d3.scaleLinear().domain(colorExtent).range([legendY + legendH, legendY]);\n";
  echo "  const legendAxis = d3.axisRight(legendScale).ticks(6);\n";
  echo "  svg.append('g')\n";
  echo "    .attr('transform', 'translate(' + (legendX + 16) + ',0)')\n";
  echo "    .call(legendAxis);\n";
  echo "\n";
  echo "  svg.append('text')\n";
  echo "    .attr('x', legendX - 12)\n";
  echo "    .attr('y', legendY - 12)\n";
  echo "    .style('font-size', '11px')\n";
  echo "    .style('font-weight', 'bold')\n";
  echo "    .text(colorVar);\n";
  echo "})();\n";
  echo "</script>\n";

  // ── Polar Zoom Modal ─────────────────────────────────────────────────────
  echo '
<style>
  #polarZoomModal {
    --pzm-width: 90vw;
    --pzm-height: 90vh;
    --pzm-max-width: calc(100vw - 32px);
    --pzm-max-height: calc(100vh - 32px);
  }
  @media (max-width: 768px) {
    #polarZoomModal { --pzm-width: 95vw; --pzm-height: 95vh; --pzm-max-width: calc(100vw - 16px); --pzm-max-height: calc(100vh - 16px); }
  }
  @media (min-width: 1920px) {
    #polarZoomModal { --pzm-width: 85vw; --pzm-height: 85vh; }
  }
</style>
<div id="polarZoomModal" style="
    display:none; position:fixed; top:0; left:0; right:0; bottom:0;
    width:100%; height:100%;
    background:radial-gradient(circle at 15% 20%, rgba(41,128,185,0.35), rgba(44,62,80,0.9));
    z-index:9999; justify-content:center; align-items:center;
    padding:0; margin:0; overflow:hidden;">
  <div style="
      position:relative; background:#ffffff; padding:0;
      border-radius:16px; box-shadow:0 20px 50px rgba(0,0,0,0.4);
      width:var(--pzm-width); height:var(--pzm-height);
      max-width:var(--pzm-max-width); max-height:var(--pzm-max-height);
      overflow:hidden; display:flex; flex-direction:column;
      box-sizing:border-box; border:1px solid rgba(255,255,255,0.4);">
    <div style="
        display:flex; justify-content:space-between; align-items:center;
        padding:14px 18px;
        background:linear-gradient(135deg, #0f4c75, #1b6ca8, #3282b8);
        color:white; border-bottom:2px solid rgba(255,255,255,0.2);
        border-radius:16px 16px 0 0; flex-shrink:0; box-sizing:border-box;
        gap:10px; flex-wrap:wrap; font-family:\'Space Grotesk\',\'Segoe UI\',sans-serif;">
      <div style="display:flex; flex-direction:column; gap:4px;">
        <h2 style="margin:0; font-size:20px; font-weight:700; letter-spacing:0.2px;">Zoom &amp; Pan</h2>
        <span style="font-size:12px; opacity:0.8;">Polar Plot &mdash; mouse wheel to zoom, drag to pan</span>
      </div>
      <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; align-items:center;">
        <button onclick="resetPolarZoom()" style="height:34px; padding:0 12px; font-size:12px; cursor:pointer; background:#e67e22; color:white; border:none; border-radius:6px; font-weight:700; white-space:nowrap; flex-shrink:0;">Reset</button>
        <button onclick="closePolarZoomModal()" style="height:34px; padding:0 12px; font-size:12px; cursor:pointer; background:#e74c3c; color:white; border:none; border-radius:6px; font-weight:700; white-space:nowrap; flex-shrink:0;">Close</button>
      </div>
    </div>
    <div style="
        flex:1; overflow:hidden; display:flex; flex-direction:column;
        box-sizing:border-box; min-height:0; min-width:0;
        padding:10px;
        background:linear-gradient(180deg, rgba(255,255,255,0.95), rgba(243,248,252,0.95));">
      <div id="polarZoomChartContainer" style="
          flex:1; width:100%; box-sizing:border-box; overflow:hidden;
          background:#ffffff; border-radius:12px; border:1px solid #d9e3ef;"></div>
    </div>
    <div style="
        text-align:center; color:#5a6b7b; font-size:12px;
        padding:10px 15px; flex-shrink:0; background:#f4f7fb;
        border-top:1px solid #d9e3ef; box-sizing:border-box;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        font-family:\'Space Grotesk\',\'Segoe UI\',sans-serif;">
      Zoom: mouse wheel &nbsp;|&nbsp; Pan: click + drag &nbsp;|&nbsp; Reset: button
    </div>
  </div>
</div>';

  echo "<script>\n";
  echo "window.closePolarZoomModal = function() {\n";
  echo "  var modal = document.getElementById('polarZoomModal');\n";
  echo "  if (modal) modal.style.display = 'none';\n";
  echo "  var tip = document.getElementById('polar-zoom-tooltip');\n";
  echo "  if (tip) tip.style.opacity = 0;\n";
  echo "};\n";
  echo "\n";
  echo "window.resetPolarZoom = function() {};\n";
  echo "\n";
  echo "window.openPolarZoomModal = function() {\n";
  echo "  var info = window.__polarSingleExport;\n";
  echo "  if (!info || !Array.isArray(info.merged) || !info.merged.length) return;\n";
  echo "  var modal = document.getElementById('polarZoomModal');\n";
  echo "  var container = document.getElementById('polarZoomChartContainer');\n";
  echo "  if (!modal || !container) return;\n";
  echo "  modal.style.display = 'flex';\n";
  echo "  container.innerHTML = '';\n";
  echo "\n";
  echo "  modal.onclick = function(e) { if (e.target === modal) closePolarZoomModal(); };\n";
  echo "\n";
  echo "  var merged = info.merged;\n";
  echo "  var dirVar = info.dirVar;\n";
  echo "  var colorVar = info.colorVar;\n";
  echo "  var payload = info.payload;\n";
  echo "  var unitsMap = payload.units || {};\n";
  echo "  var dirUnit = String(unitsMap[dirVar] || 'deg').split(' (')[0];\n";
  echo "  var colorUnit = String(unitsMap[colorVar] || '').split(' (')[0];\n";
  echo "  var formatHoverTime = d3.utcFormat('%Y-%m-%d %H:%M');\n";
  echo "\n";
  echo "  var width = container.clientWidth || 800;\n";
  echo "  var height = container.clientHeight || 700;\n";
  echo "  var margin = { top: 40, right: 110, bottom: 40, left: 40 };\n";
  echo "  var cx = (width - margin.left - margin.right) / 2 + margin.left;\n";
  echo "  var cy = (height - margin.top - margin.bottom) / 2 + margin.top + 20;\n";
  echo "  var outerRadius = Math.min(width - margin.left - margin.right, height - margin.top - margin.bottom) / 2 - 10;\n";
  echo "  var innerRadius = Math.max(24, outerRadius * 0.16);\n";
  echo "\n";
  echo "  var timeExtent = d3.extent(merged, function(d) { return d.time; });\n";
  echo "  var colorExtent = d3.extent(merged, function(d) { return d.colorValue; });\n";
  echo "  var r = d3.scaleTime().domain(timeExtent).range([innerRadius, outerRadius]);\n";
  echo "  var colorScale = d3.scaleSequential(d3.interpolateTurbo).domain(colorExtent);\n";
  echo "\n";
  echo "  var angleToXY = function(deg, radius) {\n";
  echo "    var a = (deg / 180) * Math.PI - Math.PI / 2;\n";
  echo "    return { x: Math.cos(a) * radius, y: Math.sin(a) * radius };\n";
  echo "  };\n";
  echo "\n";
  echo "  var splitSegments = function(points) {\n";
  echo "    var out = [], current = [];\n";
  echo "    for (var i = 0; i < points.length; i++) {\n";
  echo "      var p = points[i];\n";
  echo "      if (current.length === 0) { current.push(p); continue; }\n";
  echo "      var prev = current[current.length - 1];\n";
  echo "      var dt = Math.abs(p.time - prev.time);\n";
  echo "      var gapBreak = dt > (5 * 60 * 1000);\n";
  echo "      var jumpBreak = Math.abs(p.dirUnwrappedSmoothed - prev.dirUnwrappedSmoothed) > 120;\n";
  echo "      if (gapBreak || jumpBreak) { if (current.length > 1) out.push(current); current = [p]; }\n";
  echo "      else { current.push(p); }\n";
  echo "    }\n";
  echo "    if (current.length > 1) out.push(current);\n";
  echo "    return out;\n";
  echo "  };\n";
  echo "\n";
  echo "  var svg = d3.select(container).append('svg').attr('width', width).attr('height', height);\n";
  echo "\n";
  echo "  var tooltip = (function() {\n";
  echo "    var ex = d3.select('#polar-zoom-tooltip');\n";
  echo "    if (!ex.empty()) return ex;\n";
  echo "    return d3.select('body').append('div').attr('id','polar-zoom-tooltip')\n";
  echo "      .style('position','absolute').style('background','rgba(22,32,45,0.92)')\n";
  echo "      .style('border','1px solid rgba(255,255,255,0.2)').style('border-radius','8px')\n";
  echo "      .style('padding','8px 10px').style('color','#f7f9fb').style('font-size','12px')\n";
  echo "      .style('pointer-events','none').style('opacity',0).style('z-index',10001);\n";
  echo "  })();\n";
  echo "\n";
  echo "  var g = svg.append('g').attr('transform','translate(' + cx + ',' + cy + ')');\n";
  echo "\n";
  echo "  var zb = d3.zoom().scaleExtent([0.5, 12])\n";
  echo "    .on('start', function() { tooltip.style('opacity', 0); })\n";
  echo "    .on('zoom', function(event) {\n";
  echo "      g.attr('transform','translate(' + cx + ',' + cy + ') ' + event.transform);\n";
  echo "    });\n";
  echo "  svg.call(zb);\n";
  echo "\n";
  echo "  window.resetPolarZoom = function() {\n";
  echo "    svg.transition().duration(500).call(zb.transform, d3.zoomIdentity);\n";
  echo "  };\n";
  echo "\n";
  echo "  var timeTicks = r.ticks(4);\n";
  echo "  g.selectAll('.pzmgc').data(timeTicks).enter().append('circle')\n";
  echo "    .attr('r', function(d) { return r(d); })\n";
  echo "    .attr('fill','none').attr('stroke','#b7c7d6').attr('stroke-dasharray','4 6');\n";
  echo "\n";
  echo "  var dirs = [0,90,180,270];\n";
  echo "  var lbls = {0:'N',90:'E',180:'S',270:'W'};\n";
  echo "  g.selectAll('.pzmal').data(dirs).enter().append('line')\n";
  echo "    .attr('x1',0).attr('y1',0)\n";
  echo "    .attr('x2', function(d) { return angleToXY(d, outerRadius).x; })\n";
  echo "    .attr('y2', function(d) { return angleToXY(d, outerRadius).y; })\n";
  echo "    .attr('stroke','#8fa5ba');\n";
  echo "  g.selectAll('.pzmlbl').data(dirs).enter().append('text')\n";
  echo "    .attr('x', function(d) { return angleToXY(d, outerRadius + 16).x; })\n";
  echo "    .attr('y', function(d) { return angleToXY(d, outerRadius + 16).y; })\n";
  echo "    .attr('text-anchor','middle').attr('dominant-baseline','middle')\n";
  echo "    .style('font-size','12px').style('font-weight','bold')\n";
  echo "    .text(function(d) { return lbls[d]; });\n";
  echo "\n";
  echo "  var segments = splitSegments(merged);\n";
  echo "  segments.forEach(function(seg) {\n";
  echo "    for (var i = 1; i < seg.length; i++) {\n";
  echo "      var a = seg[i-1], b = seg[i];\n";
  echo "      var pa = angleToXY(a.dir, r(a.time)), pb = angleToXY(b.dir, r(b.time));\n";
  echo "      g.append('line').attr('x1',pa.x).attr('y1',pa.y).attr('x2',pb.x).attr('y2',pb.y)\n";
  echo "        .attr('stroke', colorScale((a.colorValue + b.colorValue) / 2))\n";
  echo "        .attr('stroke-width', 2.2).attr('stroke-linecap','round');\n";
  echo "    }\n";
  echo "  });\n";
  echo "\n";
  echo "  g.selectAll('.pzmhp').data(merged).enter().append('circle')\n";
  echo "    .attr('class','pzmhp')\n";
  echo "    .attr('cx', function(d) { return angleToXY(d.dir, r(d.time)).x; })\n";
  echo "    .attr('cy', function(d) { return angleToXY(d.dir, r(d.time)).y; })\n";
  echo "    .attr('r', 6).attr('fill','transparent').style('cursor','crosshair')\n";
  echo "    .on('mousemove', function(event, d) {\n";
  echo "      var dt = Number.isFinite(d.dir) ? d.dir.toFixed(2) : '';\n";
  echo "      var ct = Number.isFinite(d.colorValue) ? d.colorValue.toFixed(2) : '';\n";
  echo "      tooltip.style('opacity',1)\n";
  echo "        .html('<div style=\"font-weight:700;margin-bottom:4px;color:#9fd2ff;\">' + formatHoverTime(d.time) + '</div>' +\n";
  echo "              '<div>' + dirVar + ': ' + dt + (dirUnit ? ' ' + dirUnit : '') + '</div>' +\n";
  echo "              '<div>' + colorVar + ': ' + ct + (colorUnit ? ' ' + colorUnit : '') + '</div>')\n";
  echo "        .style('left',(event.pageX+12)+'px').style('top',(event.pageY-18)+'px');\n";
  echo "    })\n";
  echo "    .on('mouseleave', function() { tooltip.style('opacity',0); });\n";
  echo "\n";
  echo "  var drawEP = function(point, label, fill) {\n";
  echo "    if (!point) return;\n";
  echo "    var pos = angleToXY(point.dir, r(point.time));\n";
  echo "    g.append('circle').attr('cx',pos.x).attr('cy',pos.y).attr('r',5.5)\n";
  echo "      .attr('fill',fill).attr('stroke','#ffffff').attr('stroke-width',1.8);\n";
  echo "    g.append('text').attr('x',pos.x+8).attr('y',pos.y-8)\n";
  echo "      .style('font-size','11px').style('font-weight','700').style('fill','#2c3e50').text(label);\n";
  echo "  };\n";
  echo "  drawEP(merged[0], 'Start', '#1e88e5');\n";
  echo "  drawEP(merged[merged.length-1], 'End', '#e53935');\n";
  echo "\n";
  echo "  var title = (payload.shipName || payload.ship || '') + ' | ' + payload.date + ' | ' + payload.hs + '-' + payload.he + ' UTC';\n";
  echo "  svg.append('text').attr('x',width/2).attr('y',22).attr('text-anchor','middle')\n";
  echo "    .style('font-size','14px').style('font-weight','bold')\n";
  echo "    .text('Polar Plot: ' + dirVar + ' colored by ' + colorVar);\n";
  echo "  svg.append('text').attr('x',width/2).attr('y',38).attr('text-anchor','middle')\n";
  echo "    .style('font-size','11px').style('fill','#4b5d70').text(title);\n";
  echo "\n";
  echo "  var legendX = width - 70, legendY = 100, legendH = 250;\n";
  echo "  var gid = 'polarZoomColorScale';\n";
  echo "  var defs = svg.append('defs');\n";
  echo "  var grad = defs.append('linearGradient').attr('id',gid)\n";
  echo "    .attr('x1','0%').attr('y1','100%').attr('x2','0%').attr('y2','0%');\n";
  echo "  for (var i = 0; i <= 10; i++) {\n";
  echo "    var t = i / 10;\n";
  echo "    grad.append('stop').attr('offset',(t*100)+'%').attr('stop-color',d3.interpolateTurbo(t));\n";
  echo "  }\n";
  echo "  svg.append('rect').attr('x',legendX).attr('y',legendY).attr('width',16).attr('height',legendH)\n";
  echo "    .attr('fill','url(#'+gid+')').attr('stroke','#777');\n";
  echo "  var ls = d3.scaleLinear().domain(colorExtent).range([legendY+legendH, legendY]);\n";
  echo "  svg.append('g').attr('transform','translate('+(legendX+16)+',0)').call(d3.axisRight(ls).ticks(6));\n";
  echo "  svg.append('text').attr('x',legendX-12).attr('y',legendY-12)\n";
  echo "    .style('font-size','11px').style('font-weight','bold').text(colorVar);\n";
  echo "};\n";
  echo "</script>\n";
}