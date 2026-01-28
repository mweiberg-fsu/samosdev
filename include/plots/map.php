<?php
/**
 * Map Module
 * Handles geographic data mapping visualization
 */

function InsertMap($var = 'time')
{
  global $file_history_id, $order, $ship, $date, $ship_id, $variables;
  
  // Get the version number
  if ($order > 100)
    $query = "SELECT max, min, variable_name, process_version_no, units FROM merged_qc_summary INNER JOIN known_variable kv ON known_variable_id=variable_id AND merged_file_history_id=$file_history_id INNER JOIN merged_file_history mfh USING(merged_file_history_id) INNER JOIN version_no vn USING (version_id) ORDER BY kv.order_value";
  else
    $query = "SELECT max, min, variable_name, process_version_no, units FROM qc_summary INNER JOIN known_variable kv ON known_variable_id=variable_id AND daily_file_history_id=$file_history_id INNER JOIN daily_file_history dfh USING(daily_file_history_id) INNER JOIN version_no vn USING (version_id) ORDER BY kv.order_value";
  
  db_query($query);
  if (db_error()) {
    echo "ERROR: $query.<br />\n";
    return;
  }
  
  while ($row = db_get_row()) {
    $version_no = $row->process_version_no;
    if ($version_no < 100)
      $version_no = 100;
  }
  
  $data_map_args = array(
    "ship" => $ship,
    "date" => $date,
    "version" => $version_no,
    "order" => $order,
    "variable" => $var,
  );
  
  $query_string = '/charts/index.php?mode=5&id=' . $ship_id . '&history_id=' . $file_history_id . '&';
  foreach ($data_map_args as $key => $value) {
    if ($key == 'variable') {
      continue;
    }
    $param = $key . '=' . $value . '&';
    $query_string .= $param;
  }
  
  echo '<form method="POST">';
  foreach ($variables as $var_name => $ship_var) {
    if ($var_name == 'lat' || $var_name == 'lon') {
      continue;
    }
    echo '<button type="submit" formaction="' . $query_string . 'variable=' . $var_name . '">' . $var_name . '</button>';
  }
  echo '</form>';
  
  // Link CSS file for data map styling
  echo '<link href="/css/data_map.css" rel="stylesheet">';
  echo '<div id="datamap" style="width: 600px; height: 400px"></div>';
  echo '<script id="data-map-args">' . json_encode($data_map_args) . '</script>';
  echo '<script src="../js/data_map.js?"></script>';
}
