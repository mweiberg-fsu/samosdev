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

/**
 * Get proper display title for variable names
 * Maps variable names (including numbered variants) to their display titles
 * Examples: P, P2, P3 → Pressure; T, T2, T3 → Air Temperature
 */
function GetVariableTitle($varName)
{
  // Handle exact matches first
  $exactMatches = array(
    'LAT' => 'Position (Lat/Lon)',
    'LON' => 'Position (Lat/Lon)',
    
    'P' => 'Pressure',
    'PA' => 'Pressure',
    
    'T' => 'Air Temperature',
    'TA' => 'Air Temperature',
    
    'RH' => 'Relative Humidity',
    
    'TS' => 'Sea Temperature',
    'SST' => 'Sea Temperature',
    
    'TD' => 'Dew Point',
    'TW' => 'Wet Bulb',
    'WB' => 'Wet Bulb',
    
    'E' => 'Vapor Pressure',
    
    'PRECIP' => 'Precipitation Accumulation',
    'RRATE' => 'Rain Rate',
    'R_RATE' => 'Rain Rate',
    
    'RAD_SW' => 'Shortwave Radiation',
    'RAD_LW' => 'Longwave Radiation',
    'RAD_PAR' => 'Photosynthetic Radiation',
    'PAR' => 'Photosynthetic Radiation',
    
    'PL_CRS' => 'Platform Course',
    'PL_HD' => 'Platform Heading',
    'PL_SPD' => 'Platform Speed Over Ground',
    'PL_SOW' => 'Platform Speed Over Water',
    
    'WDIR' => 'Platform Relative Wind Direction',
    'PL_WDIR' => 'Platform Relative Wind Direction',
    'WDIR_R' => 'Platform Relative Wind Direction',
    
    'WSPD_R' => 'Platform Relative Wind Speed',
    
    'WDIR_E' => 'Earth Relative Wind Direction',
    'WSPD_E' => 'Earth Relative Wind Speed',
    
    'PL_WSPD' => 'Platform Relative Wind Speed',
    'WSPD' => 'Earth Relative Wind Speed',
    'SPD' => 'Earth Relative Wind Speed',
    'DIR' => 'Earth Relative Wind Direction',
  );
  
  // Check exact match first
  if (isset($exactMatches[$varName])) {
    return $exactMatches[$varName];
  }
  
  // Handle numbered variants (e.g., P2, P3, T2, T3, RH2, etc.)
  // Extract the base variable name (remove trailing numbers)
  $baseVar = preg_replace('/\d+$/', '', $varName);
  
  // Try to match the base variable
  if (!empty($baseVar) && isset($exactMatches[$baseVar])) {
    return $exactMatches[$baseVar];
  }
  
  // Handle prefixed variants
  if (strpos($varName, 'PL_') === 0) {
    $suffix = substr($varName, 3);
    if (isset($exactMatches[$suffix])) {
      return $exactMatches[$suffix];
    }
  }
  
  // Case-insensitive fallback
  $upperVar = strtoupper($varName);
  if (isset($exactMatches[$upperVar])) {
    return $exactMatches[$upperVar];
  }
  
  // Fallback: return the variable name with number stripped and proper case
  return ucfirst(strtolower($baseVar ?: $varName)) . ' Group';
}