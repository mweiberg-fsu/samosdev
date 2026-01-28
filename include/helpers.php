<?php
/**
 * Helper Functions Module
 * Utility functions used throughout the application
 */

/**
 * Determines cell color based on flag status
 * Used in data table rendering to highlight flagged values
 */
function make_cell_color_decision($row, $flag_color, $color_used)
{
  global $flag_color, $color_used;
  if ($row) {
    switch ($flag_color % 6) {
      case 0:
        echo '<td class="flagged_gold">';
        break;
      case 1:
        echo '<td class="flagged_blue">';
        break;
      case 2:
        echo '<td class="flagged_green">';
        break;
      case 3:
        echo '<td class="flagged_maroon">';
        break;
      case 4:
        echo '<td class="flagged_black">';
        break;
      default:
        echo '<td class="flagged_purple">';
        break;
    }
    $color_used = TRUE;
  } else
    echo '<td>';
  echo $row, '</td>';
}

/**
 * Get flags from json result "plot_series_flags.php"
 * Used in InsertPlot function for time series plots
 */
function flags_array($url)
{
  $json = file_get_contents($url);
  $data = array();
  $data = json_decode($json, true);

  $flags_arr = array();

  foreach ($data as $i) {
    array_push($flags_arr, $i);
  }

  return $flags_arr;
}
