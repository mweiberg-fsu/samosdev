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
    if (preg_match('/^(DIR\d*|PL_WDIR\d*)$/i', $varName)) {
      $dirVars[] = $varName;
    }
    if (preg_match('/^PL_WSPD\d*$/i', $varName)) {
      $colorVars[] = $varName;
    }
  }

  sort($dirVars);
  sort($colorVars);

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
        <span style="color:#888; font-size:11px; margin-left:6px;">DIR, DIR1, DIR2, ...</span>
      </td>
    </tr>
    <tr>
      <th>Gradient Variable</th>
      <td>
        <select name="color_var" style="width:220px;">
          $colorOptions
        </select>
        <span style="color:#888; font-size:11px; margin-left:6px;">PL_WSPD, PL_WSPD2, ...</span>
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
    echo 'Missing required variables for polar plotting. Need at least one DIR* variable and one PL_WSPD* variable.';
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
    'dirVar' => $selectedDirVar,
    'colorVar' => $selectedColorVar,
    'ship' => $ship,
    'shipName' => get_ship_name($ship_id),
    'date' => $date,
    'hs' => $hs,
    'he' => $he,
  ));

  echo '<div id="polarSingleChart" style="width:790px; height:620px; margin:20px auto; border:1px solid #d5d5d5; background:#fff;"></div>';
  echo "<script src=\"https://d3js.org/d3.v6.min.js\"></script>";
  echo "<script>\n";
  echo "(function(){\n";
  echo "  const payload = $payload;\n";
  echo "  const container = document.getElementById('polarSingleChart');\n";
  echo "  if (!container || !window.d3) return;\n";
  echo "\n";
  echo "  const parseTime = d3.utcParse('%Y-%m-%d %H:%M:%S');\n";
  echo "  const dirVar = payload.dirVar;\n";
  echo "  const colorVar = payload.colorVar;\n";
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
  echo "  })).filter(p => p.time && p.value != null);\n";
  echo "\n";
  echo "  const colorMap = new Map();\n";
  echo "  colorSeriesRaw.forEach(p => {\n";
  echo "    const cv = normalizeValue(p.value);\n";
  echo "    if (cv != null) colorMap.set(p.date, cv);\n";
  echo "  });\n";
  echo "\n";
  echo "  const merged = dirSeries.map(p => ({\n";
  echo "    date: p.date,\n";
  echo "    time: p.time,\n";
  echo "    dir: ((p.value % 360) + 360) % 360,\n";
  echo "    colorValue: colorMap.has(p.date) ? colorMap.get(p.date) : null\n";
  echo "  })).filter(p => p.colorValue != null);\n";
  echo "\n";
  echo "  if (!merged.length) {\n";
  echo "    container.innerHTML = '<div style=\"padding:20px; color:#b00020; text-align:center;\">No overlapping DIR and wind speed data found for the selected range.</div>';\n";
  echo "    return;\n";
  echo "  }\n";
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
  echo "      const rawDiff = Math.abs(p.dir - prev.dir);\n";
  echo "      let shortest = p.dir - prev.dir;\n";
  echo "      if (shortest > 180) shortest -= 360;\n";
  echo "      if (shortest < -180) shortest += 360;\n";
  echo "      const gapBreak = dt > (5 * 60 * 1000);\n";
  echo "      const wrapBreak = (rawDiff > 180 && Math.abs(shortest) < 90);\n";
  echo "      const jumpBreak = Math.abs(shortest) > 120;\n";
  echo "      if (gapBreak || wrapBreak || jumpBreak) {\n";
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
}