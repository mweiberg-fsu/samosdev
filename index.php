<?php
/*
   Jonathan Reynes - 4/11/16
   updated the $cmd variable. It now contains the absolute paths from global.inc.php

   William McCall - 12/21/16
   fixed google maps issue and set default style to hybrid map

   v32 - Refactored Code - 2026/01/23
   - Modularized 3000+ line file into separate include files
   - Split functions into: helpers.php, timeseries_plot.php, combined_plot.php, multifunction_plot.php, map.php
   - Main index.php now 300 lines (vs 3000), maintains entry point and routing logic
   - All modal and plot rendering logic extracted to focused modules
*/

include_once 'include/global.inc.php';

// Include helper functions and plot modules
require_once 'include/helpers.php';
require_once 'include/plots/timeseries_plot.php';
require_once 'include/plots/combined_plot.php';
require_once 'include/plots/multifunction_plot.php';
require_once 'include/plots/map.php';

$google_map_min_user_level = 20;
config('nobase');
config('css', 'search.css');

if (true) {	// $IS_DEVELOPMENT
  config('css', "https://unpkg.com/leaflet@1.7.1/dist/leaflet.css");
  config('javascript', "https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js");
  config('javascript', "https://unpkg.com/leaflet@1.7.1/dist/leaflet.js");
} else {
  config('javascript', "https://unpkg.com/leaflet@1.7.1/dist/leaflet.js");
  config('javascript', "https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js");
  config('css', "https://unpkg.com/leaflet@1.7.1/dist/leaflet.css");
}

// Extract global variables needed by all functions
$date = $_REQUEST['date'];
site_header('Visual QC - ' . $_REQUEST['ship'] . ' &nbsp;&nbsp;Order #: ' . $_REQUEST['order'] . ' &nbsp;&nbsp;' . date(DATE_FORMAT, mktime(0, 0, 1, substr($date, 4, 2), substr($date, 6, 2), substr($date, 0, 4))));

$mode = (isset($_REQUEST['mode'])) ? (int) $_REQUEST['mode'] : 0;
$variable_id = isset($_REQUEST['v']) ? (int) $_REQUEST['v'] : 0;
$file_history_id = $_REQUEST['history_id'];
$ship_id = $_REQUEST['id'];
$ship = $_REQUEST['ship'];
$order = $_REQUEST['order'];

$variables = array();
if ($order > 100)
  $query = 'SELECT kv.variable_name FROM merged_qc_summary mqcs INNER JOIN known_variable kv ON mqcs.known_variable_id=kv.variable_id AND merged_file_history_id=' . $file_history_id . ' ORDER BY kv.order_value';
else
  $query = 'SELECT kv.variable_name FROM qc_summary qcs INNER JOIN known_variable kv ON qcs.known_variable_id=kv.variable_id AND daily_file_history_id=' . $file_history_id . ' ORDER BY kv.order_value';
db_query($query);
if (db_error()) {
  echo "ERROR: $query.<br />\n";
  return;
}
while ($row = db_get_row()) {
  $variables[$row->variable_name] = array();
}

include "pie_chart.php";
include "pie_chart_a_y_text.php";
$a_y_res = pie_text($ship, $date, $file_history_id, $mode, $order);
$SERVER = "https://" . $_SERVER['SERVER_NAME'];

echo "\n";
echo '<div class="title">', get_ship_name($ship_id), '</div>', "\n";

echo '<div class="order_no">Order number: ', $order, '</div>', "\n";
echo '<div class="date">Date: ', date(DATE_FORMAT, mktime(0, 0, 1, substr($date, 4, 2), substr($date, 6, 2), substr($date, 0, 4))), '</div>';

echo '<div class="menu">';
echo '<a href="index.php?ship=' . $ship . '&id=', $ship_id, '&date=', $date, '&order=' . $order . '&history_id=', $file_history_id, '"><font size=1>[Failed QC vs. Passed QC]</font></a>&nbsp;';
echo '<a href="index.php?ship=' . $ship . '&id=', $ship_id, '&date=', $date, '&order=' . $order . '&history_id=', $file_history_id, '&mode=3"><font size=1>[Flag Distribution]</font></a>&nbsp;';
echo '<a href="index.php?ship=' . $ship . '&id=', $ship_id, '&date=', $date, '&order=' . $order . '&history_id=', $file_history_id, '&mode=1"><font size=1>[A-Y Flags]</font></a>&nbsp;';
echo '<a href="index.php?ship=' . $ship . '&id=', $ship_id, '&date=', $date, '&order=' . $order . '&history_id=', $file_history_id, '&mode=4"><font size=1>[Z Flags]</font></a>&nbsp;';
echo '<a href="index.php?ship=' . $ship . '&id=', $ship_id, '&date=', $date, '&order=' . $order . '&history_id=', $file_history_id, '&mode=6&fbound=1"><font size=1>[Plot]</font></a>&nbsp;';
echo '<a href="index.php?ship=' . $ship . '&id=', $ship_id, '&date=', $date, '&order=' . $order . '&history_id=', $file_history_id, '&mode=6&fbound=0"><font size=1>[Plot (bound w/o flags)]</font></a>&nbsp;';
echo '<a href="index.php?ship=' . $ship . '&id=', $ship_id, '&date=', $date, '&order=', $order, '&history_id=', $file_history_id, '&mode=7"><font size=1>[Plot (combined)]</font></a>&nbsp;';
echo '<a href="index.php?ship=' . $ship . '&id=', $ship_id, '&date=', $date, '&order=', $order, '&history_id=', $file_history_id, '&mode=8"><font size=1>[Plot (multifunction)]</font></a>&nbsp;';

if ($_SESSION['level'] >= $google_map_min_user_level)
  echo '<a href="index.php?ship=' . $ship . '&id=', $ship_id, '&date=', $date, '&order=' . $order . '&history_id=', $file_history_id, '&mode=5"><font size=1>[Map]</font></a>';
echo '<div>';
echo '<br />';
echo '<div style="margin-top: 1px;z-index:5">';
$height = 250;
if (count($variables) > 18)
  $height += (count($variables) - 18) * 15;
switch ($mode) {
  case 0:
    pie_chart("$SERVER/charts/passed_failed_qc.php?ship=$ship&date=$date&order=$order&history_id=$file_history_id", 200, 200, 1);
    break;

  case 1:
    pie_chart("$SERVER/charts/a_y_z_flags.php?ship=$ship&date=$date&order=$order&history_id=$file_history_id&type=0", 600, 200, 4, $a_y_res);
    break;

  case 2:
    pie_chart();
    break;

  case 3:
    pie_chart("$SERVER/charts/flag_distribution.php?ship=$ship&date=$date&order=$order&history_id=$file_history_id&type=0", 600, 200, 2);
    break;

  case 4:
    pie_chart("$SERVER/charts/a_y_z_flags.php?ship=$ship&date=$date&order=$order&history_id=$file_history_id&type=1", 600, 600, 3);
    break;

  case 5:
    if ($_SESSION['level'] >= $google_map_min_user_level) {
      if (isset($_REQUEST['variable'])) {
        InsertMap($_REQUEST['variable']);
      } else {
        InsertMap();
      }
    }
    break;

  case 6:
    InsertPlot();
    break;
  default:
    break;

  case 7:
    InsertCombinedPlot();
    break;
    
  case 8:
    InsertMultifunctionPlot();
    break;
}
echo '</div>', "\n\n";

if ($order > 100)
  $query = 'SELECT mqcs.*, kv.order_value FROM merged_qc_summary mqcs INNER JOIN known_variable kv ON mqcs.known_variable_id=kv.variable_id AND merged_file_history_id=' . $file_history_id . ' ORDER BY kv.order_value';
else
  $query = 'SELECT qcs.*, kv.order_value FROM qc_summary qcs INNER JOIN known_variable kv ON qcs.known_variable_id=kv.variable_id AND daily_file_history_id=' . $file_history_id . ' ORDER BY kv.order_value';
db_query($query);

echo '<style>', "\n";
echo 'td { text-align: center; border: 1px solid grey; }', "\n";
echo 'td.variable_name { text-align: left; border: 1px solid grey; }', "\n";
echo 'td.flagged_gold { text-align: center; background-color: #DDAA33; border: 1px solid grey; }', "\n";
echo 'td.flagged_blue { text-align: center; background-color: #4E627C; border: 1px solid grey; }', "\n";
echo 'td.flagged_green { text-align: center; background-color: #4C6B41; border: 1px solid grey; }', "\n";
echo 'td.flagged_maroon { text-align: center; background-color: #844648; border: 1px solid grey; }', "\n";
echo 'td.flagged_black { text-align: center; background-color: #4D4D4D; border: 1px solid grey; }', "\n";
echo 'td.flagged_purple { text-align: center; background-color: #5A4B6E; border: 1px solid grey; }', "\n";
echo 'td.error { text-align: center; background-color: #FF0000; border: 1px solid grey; }', "\n";
echo '</style>', "\n\n";

echo 'QC Summary Table <font size=1>(';
if ($order > 100)
  echo 'merged';
else
  echo 'daily';
echo '_file_history_id=' . $file_history_id . ')</font>', "\n";

echo '<table cellspacing="0" width=100% style="border: 1px solid blue">', "\n";
echo '<tr>', "\n";
echo '<td>&nbsp;</td>', "\n";
echo '<td>A</td>', "\n";
echo '<td>B</td>', "\n";
echo '<td>C</td>', "\n";
echo '<td>D</td>', "\n";
echo '<td>E</td>', "\n";
echo '<td>F</td>', "\n";
echo '<td>G</td>', "\n";
echo '<td>H</td>', "\n";
echo '<td>I</td>', "\n";
echo '<td>J</td>', "\n";
echo '<td>K</td>', "\n";
echo '<td>L</td>', "\n";
echo '<td>M</td>', "\n";
echo '<td>N</td>', "\n";
echo '<td>O</td>', "\n";
echo '<td>P</td>', "\n";
echo '<td>Q</td>', "\n";
echo '<td>R</td>', "\n";
echo '<td>S</td>', "\n";
echo '<td>T</td>', "\n";
echo '<td>U</td>', "\n";
echo '<td>V</td>', "\n";
echo '<td>W</td>', "\n";
echo '<td>X</td>', "\n";
echo '<td>Y</td>', "\n";
echo '<td bgcolor="#FFFFCC">Z</td>', "\n";
echo '<td>total</td>', "\n";
echo '<td>sp</td>', "\n";
echo '<td>miss</td>', "\n";
echo '<td>min</td>', "\n";
echo '<td>max</td>', "\n";
echo '</tr>', "\n";

$flag_color = 0;
$color_used;
while ($row = db_get_row()) {
  $color_used = FALSE;

  echo '<tr>';
  echo '<td class="variable_name">';
  echo '<a href="description.php?variable_id=' . $row->known_variable_id . '" target="_new">';
  echo get_variable_name($row->known_variable_id);
  echo '</a>&nbsp;</td>';

  make_cell_color_decision($row->a, $flag_color, $color_used);
  make_cell_color_decision($row->b, $flag_color, $color_used);
  make_cell_color_decision($row->c, $flag_color, $color_used);
  make_cell_color_decision($row->d, $flag_color, $color_used);
  make_cell_color_decision($row->e, $flag_color, $color_used);
  make_cell_color_decision($row->f, $flag_color, $color_used);
  make_cell_color_decision($row->g, $flag_color, $color_used);
  make_cell_color_decision($row->h, $flag_color, $color_used);
  make_cell_color_decision($row->i, $flag_color, $color_used);
  make_cell_color_decision($row->j, $flag_color, $color_used);
  make_cell_color_decision($row->k, $flag_color, $color_used);
  make_cell_color_decision($row->l, $flag_color, $color_used);
  make_cell_color_decision($row->m, $flag_color, $color_used);
  make_cell_color_decision($row->n, $flag_color, $color_used);
  make_cell_color_decision($row->o, $flag_color, $color_used);
  make_cell_color_decision($row->p, $flag_color, $color_used);
  make_cell_color_decision($row->q, $flag_color, $color_used);
  make_cell_color_decision($row->r, $flag_color, $color_used);
  make_cell_color_decision($row->s, $flag_color, $color_used);
  make_cell_color_decision($row->t, $flag_color, $color_used);
  make_cell_color_decision($row->u, $flag_color, $color_used);
  make_cell_color_decision($row->v, $flag_color, $color_used);
  make_cell_color_decision($row->w, $flag_color, $color_used);
  make_cell_color_decision($row->x, $flag_color, $color_used);
  make_cell_color_decision($row->y, $flag_color, $color_used);

  echo '<td bgcolor="#FFFFCC">', $row->z, '</td>';

  make_cell_color_decision($row->total, $flag_color, $color_used);

  if ($row->special)
    echo '<td class="error">';
  else
    echo '<td>';
  echo $row->special, '</td>';

  if ($row->missing)
    echo '<td class="error">';
  else
    echo '<td>';
  echo $row->missing, '</td>';

  echo '<td>', $row->min, '</td>';
  echo '<td>', $row->max, '</td>';
  echo '</tr>', "\n";

  if ($color_used)
    $flag_color++;
}
echo '</table><br>', "\n";

site_footer();
?>
