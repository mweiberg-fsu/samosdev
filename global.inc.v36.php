<?php
/*
    07/15/14 by Neely Fawaz during SAMOS migration:
 	Commented out unnecessary if/else structure
	Updated absolute path names for defined SAMOS_PROCESSING_DIR and SAMOS_FTP_DIR
	Added variables $public_dir, $internal_dir, and	$host to be used in other web files
		that use this file as an include (including the main.inc.php file from the public site includes)
 	The goal is that this file should be the only web code file to contain absolute paths.
	Any other files should contain only relative paths that utilize the variables in this file
 		to create their full paths
   Commented out all usage of $_SERVER['RELATIVE_PATH']
   
    Updated 04/11/16 by Jonathan Reynes:
	Took out all operational site constants that were commented out.
	Added PERL variable
	
    Update 05/13/16 by Jonathan Reynes:
        commented out 	$_SERVER['dsn_op'] just to get cross_copy to work. Will change when migration is complete
        Also, going to add a global to get the root url 

    v15 Jocelyn Elya 08/02/2016
        - Added $ftp_url, FTP_URL, $thredds_url, and THREDDS_URL for use in
          data_availability.php and links.inc.php.
          
    v16 Jocelyn Elya 08/04/2016
        - Updated SAMOS_FTP_DIR to /Net/samosdev_pub
        
    v17 Jocelyn Elya 08/04/2016
        - Updated final else statement in GET_BASE to pull from ftp
          directory rather than the data/public directory.
          
    v18 Jocelyn Elya 08/23/2016
       - Moved json_encoded, pretty_json, and getActiveShipCallSigns from
         html/Preliminary_ifile_json.php to here so they could be used by both
         Preliminary_ifile_json.php and Preliminary_iquality_json.php
         
    v19 Jocelyn Elya 08/26/2016
        - Removed unused "define('....OP'..." variables that were never really
          implemented in apps/cross_copy_ship.php.

    v20 Jocelyn Elya and Jonathan Reynes 09/09/2016
        - Updated definitions of many SAMOS_* directories to use the links
          directory references again. This was necessary to fix bugs in
          apps/pictures.php and other codes.
        - Updated $file_viewer_directories keys and values (used in
          html/file_viewer.php) so that links directory (and many SAMOS_*
          directories) would be accessible via an absolute path instead of a
          relative path. Calls to file_viewer.php in apps/data.php were updated
          accordingly.
          
    v21 Jocelyn Elya 09/22/2016
        - Updated $ftp_url and $thredds_url to newer versions.
    
    v22 William McCall
        - Switched SITE_URL to https
    
    v23 William McCall
        - Reformated code to be consistently spaced

    v24 William McCall
        - Removed qotd function and magpie library
        
    v25 Kris Suchdeve
		- Adding Volvo Ocean Race (VOR)
		  user/db connect
		
	v26 Kris Suchdeve
		- Edited getActiveShipCallSigns to include
		  double digit test ship callsigns

...

	v30 Michael McDonald
		- Added back IS_DEVELOPMENT variable
		
	v31 2020-02-24 Michael McDonald
		- removed IS_DEVELOPMENT and replaced with switch block (see below).
		- Added switch block that allows global.inc.php to be universal for live/dev servers.

	v32 2020-03-21 Michael McDonald
		- debugging
		- depricated ini_set session.gc_maxlifetime

	v33 2020-06-20 Michael McDonald (emm)
		- date_default_timezone_set('UTC');
		- add SAMOS_COPY_SHIP constant for cross copy Task list drop down

	v34 2024-10-09 ~emm
		- moved PEAR includes before DB_PORTABILITY_ALL constant was referenced.
		- removed depricated get_magic_quotes_gpc() call.
		- $host is now pulled fom get_cfg_var("host_samos") set in php.ini
		- add mysql/mysqli switch for newer phptype in php8
		- move debug/verbose functions to separate include/debugging.php file
		
	v35 2025-06-27 Neeraj Jawahirani
		- Added global variables/constants for CSS files.
	
	v36 2025-07-10 Neeraj Jawahirani
		- Chaning for DB_PROBABILITY_ALL to DB_PROBABILITY_NONE for testing purposes

*/
require "include/debugging.php";

define('MAINTENANCE_MODE', false);
define('MAINTENANCE_ETA', '12:00pm on Friday');
define('MAINTENANCE_MSG', 'The samos system is currently being maintenanced, try back at '.MAINTENANCE_ETA.'.');
define('MAINTENANCE_ALLOWED_USER', 'tes');

define('SAMOS_GUEST_LEVEL', '1');
define('SAMOS_MODIFY_LEVEL', '2');
define('SAMOS_DELETE_LEVEL', '3');

define('DATE_FORMAT', 'm-d-Y');

// We need to determine what the originating server/host is.
// SERVER_NAME will be set when running via apache in a virtualhost.
if (isset($_SERVER['SERVER_NAME'])) {
 $myservername = $_SERVER['SERVER_NAME'];
 define('COMMAND_LINE', false);
} else {
 //Otherwise we need to fall back to gethostname for CLI executed php code. 
 //as cli code des not operate within a virtualhost and therefore is not defined.
 $myservername = gethostname();
 define('COMMAND_LINE', true);
}
switch($myservername) {
 // We list all cases where *operational* code execution originates from here.
 case 'samos-proc.coaps.fsu.edu':
 case 'samos-web.coaps.fsu.edu':
 case 'samos.coaps.fsu.edu':

  // ---------------- FOR LIVE SITE ---------------- //
  // BEGIN all the OPS operational/production specific variables here.
  //date_default_timezone_set('UTC');
  error_reporting(E_ERROR);
  define('LOGIN_SESSION_ID', 'SAMOS_working');
  define('SAMOS_DB_HOST',   'samos-proc.coaps.fsu.edu');	
  define('SAMOS_DB_NAME',   'SAMOS_working');
  define('SAMOS_PROCESSING_DIR', '/Net/samosproc');	
  define('SAMOS_FTP_DIR', '/Net/samosproc_pub');
  define('SAMOS_TO_EMAIL', 'samos_data@coaps.fsu.edu');
  define('SAMOS_ANALYST_EMAIL', 'samosdqe@coaps.fsu.edu');
  define('SAMOS_COPY_SHIP', 'copy ship op&rarr;dev');
  define('SAMOS_WATERMARK', 'samos');
  define('SAMOS_BGCOLOR', '#FFFFCC');
  define('SAMOS_CHANGE_COLOR', '#CCFFFF');
  define('GOOGLE_MAPS_API_KEY', 'ABQIAAAAEo0_aeKfaX0pEjGuswnVDRS82dL-O_bvGl72wahYoxACpb4ZNhSOHfyv2pU9NxymBfFbiM0aI8REqg');
  //CSS Constants
  define('SAMOS_CSS_NAV_LI_A_HOVER', 'green');
  define('SAMOS_CSS_NAV_LI_A_DEFAULT', 'blue');
  define('SAMOS_CSS_A_LINK_A_VISTED', 'blue');
  define('SAMOS_CSS_A_HOVER', 'green');
  define('SAMOS_CSS_BODY_COLOR', '#e5e5ea');
  
  $full_path = '/Net/samosweb';
  $public_dir = '/Net/samosweb/html';
  $internal_dir = '/Net/samosweb';
  $host = get_cfg_var("host_samos"); //2024-10-09 ~emm
  // END OPS operational/production specific variables.
  // ---------------- / FOR LIVE SITE ---------------- //
  break;
 default:
  // We *default* to the development case if no
  // operational/production server_name has case match above.
  // This is helpfull when we are testing/developing code on new servers,
  // to prevent accident operational/production execution. 
  // ---------------- FOR DEVELOPMENTAL SITE ----------------//
  // BEGIN all the DEV development/testing specific variables here.
  //date_default_timezone_set('UTC');
  error_reporting(E_ERROR);
  define('LOGIN_SESSION_ID', 'SAMOS_development');
  define('SAMOS_DB_HOST', 'samosdev-proc.coaps.fsu.edu');	
  define('SAMOS_DB_NAME', 'SAMOS_development');
  define('SAMOS_PROCESSING_DIR', '/Net/samosdev');	
  define('SAMOS_FTP_DIR', '/Net/samosdev_pub');
  define('SAMOS_TO_EMAIL', 'samos_data+test@coaps.fsu.edu');
  define('SAMOS_ANALYST_EMAIL', 'samosdqe+test@coaps.fsu.edu');
  define('SAMOS_COPY_SHIP', 'copy ship dev&rarr;op');
  define('SAMOS_WATERMARK', 'samos_dev');
  define('SAMOS_BGCOLOR', '#CCFFFF');
  define('SAMOS_CHANGE_COLOR', '#FFFFCC');
  define('GOOGLE_MAPS_API_KEY', 'ABQIAAAAEo0_aeKfaX0pEjGuswnVDRTqsXgD1XKWSWSCzaHV1lm1AjXWvBSvSiVdCF7B3FKo5p190rfRKJ2BRA');
  //CSS Constants
  define('SAMOS_CSS_NAV_LI_A_HOVER', 'blue');
  define('SAMOS_CSS_NAV_LI_A_DEFAULT', 'green');
  define('SAMOS_CSS_A_LINK_A_VISTED', 'green');
  define('SAMOS_CSS_A_HOVER', 'blue');
  define('SAMOS_CSS_BODY_COLOR', '#333399');
  $full_path = '/Net/samosdevweb';
  $public_dir = '/Net/samosdevweb/html';
  $internal_dir = '/Net/samosdevweb';
  $host = get_cfg_var("host_samos"); //2024-10-09 ~emm
  // END DEV development/testing specific variables.
  // ---------------- / FOR DEVELOPMENTAL SITE ---------------- //
}

// ---------------- COMMON VARIABLES ---------------- //
// DEFINE ALL COMMON VARIABLES HERE:
define('SAMOS_VIEW_PASS',  'CYw4qsxVMJydTR2h');
define('SAMOS_VIEW_USER',  'samos_view');
define('SAMOS_ACD_PASS',   'uE4MyaCV6bje2zVu');
define('SAMOS_ACD_USER',   'samos_acd');
define('SAMOS_AC_PASS',    'jWPPzv2nMhpfaVsE');
define('SAMOS_AC_USER',    'samos_ac');
define('SAMOS_VOR_DB_NAME','volvo_17-18');
define('SAMOS_VOR_USER',   'volvo_17-18');
define('SAMOS_VOR_PASS',   'qxXqu8T6gXoQK5j4');
// LOCATIONS FOR PUBLIC SITE:
// Internal & public web code base path directories
// - NO trailing slash
$host_coaps = "coaps.fsu.edu";
$ftp_url = "ftp://ftp.coaps.fsu.edu/samos_pub/data";
$thredds_url = "http://tds.coaps.fsu.edu/thredds/catalog_samos.html";
define('PUBLIC_DIR', $public_dir);
define('INTERNAL_DIR', $internal_dir);
define('HOST_SAMOS', $host);
define('HOST_COAPS', $host_coaps);
define('FTP_URL', $ftp_url);
define('THREDDS_URL', $thredds_url);
// Email addresses
define('SAMOS_FROM_EMAIL', 'samos_data@coaps.fsu.edu');
// ---------------- / COMMON VARIABLES ---------------- //


//obtain path to executable perl
define('PERL', '/usr/bin/perl');
	
// directories
define('CSS_DIR', '/css');
define('SAMOS_METADATA_DIR', INTERNAL_DIR.'/metadata');
// INCOMING describes the place where all the original emails are
define('SAMOS_INCOMING_DIR', 'links/incoming');
define('SAMOS_CODES_DIR', SAMOS_PROCESSING_DIR.'/codes');
define('SAMOS_SCRATCH_DIR', 'links/scratch');
define('SAMOS_ERRORLOGS_DIR', 'logs/errorlogs');
define('SAMOS_DATA_DIR', 'links/data');
define('SAMOS_QA_DIR', 'links/qa_reports');
define('SAMOS_SQL_BACKUP_INTERVAL', 86400);


// A list of "approved" absolute paths for our file viewer
$file_viewer_directories = array (
	"SAMOS_ABS_METADATA_DIR" =>  SAMOS_METADATA_DIR,
	"SAMOS_ABS_INCOMING_DIR" =>  INTERNAL_DIR . '/' . SAMOS_INCOMING_DIR,
	"SAMOS_ABS_ERRORLOGS_DIR" => INTERNAL_DIR . '/' . SAMOS_ERRORLOGS_DIR,
	"SAMOS_ABS_DATA_DIR" =>      INTERNAL_DIR . '/' . SAMOS_DATA_DIR,
	"SAMOS_ABS_QA_DIR" =>        INTERNAL_DIR . '/' . SAMOS_QA_DIR,
	"SAMOS_ABS_SCRATCH_DIR" =>   INTERNAL_DIR . '/' . SAMOS_SCRATCH_DIR
);

if (! COMMAND_LINE ) {
	// full path to the site
	$_SERVER['SITE_ROOT'] = $_SERVER['DOCUMENT_ROOT'] /*. $_SERVER['RELATIVE_PATH']*/;

	// url of the site
	$_SERVER['SITE_URL'] = 'https://' . $_SERVER['SERVER_NAME'] /*. $_SERVER['RELATIVE_PATH']*/;

	// https url of the site
	$_SERVER['SITE_URL_SSL'] = 'https://' . $_SERVER['SERVER_NAME'] /*. $_SERVER['RELATIVE_PATH']*/;
}
else {
     $_SERVER['SITE_ROOT'] = $full_path;
}

define('SAMOS_SQL_DIR', $_SERVER['SITE_ROOT'].'/sql');

// 2024-10-09 ~emm we need to include the PEAR libraries *before* we use the portability constant below
include "include/db.inc.php";
include "include/samos.db.inc.php";

// 2024-10-14 ~emm
// mysql  -> MySQL (no longer supported in >= php8)
// mysqli -> MySQL (supports new authentication protocol) (requires PHP 5)
//if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
  $phptype = "mysqli";
} else {
  $phptype = "mysql";
}

// the only username and password I use is the one with full access
// WHY!!!!
$_SERVER['dsn'] = $phptype.'://'.SAMOS_ACD_USER.':'.SAMOS_ACD_PASS.'@'.SAMOS_DB_HOST.'/'.SAMOS_DB_NAME;

/*  2025-07-11 Do not set pear probability option cause it causes issues with case senstivity in DB column names - NJ 
$_SERVER['options'] = array(
                            'debug'=> 5,
                            'portability' => DB_PORTABILITY_NONE,
                            );
*/
// magic quotes (2024-10-09 ~emm removed in php5)
//$_SERVER['MQ'] = get_magic_quotes_gpc();

// controls the header, footer, nav stuff
include_once "include/samos-automated.db.inc.php";
include_once "include/layout.inc.php"; // where session_start() is called
include_once "include/util.inc.php";
include_once "include/user.inc.php";
include_once "include/protect.inc.php";
include_once "include/navigation.inc.php";
include_once "include/form.inc.php";
include_once "include/mail.inc.php";

$_DATA_FLAGS['a'] = $_DATA_FLAGS['A'] = 'unknown units';
$_DATA_FLAGS['b'] = $_DATA_FLAGS['B'] = 'out of realistic bounds';
$_DATA_FLAGS['c'] = $_DATA_FLAGS['C'] = 'non-sequential time';
$_DATA_FLAGS['d'] = $_DATA_FLAGS['D'] = 'failed the T>=Tw>=Td test';
$_DATA_FLAGS['e'] = $_DATA_FLAGS['E'] = 'failed the true wind test';
$_DATA_FLAGS['f'] = $_DATA_FLAGS['F'] = 'platform velocity unrealistic';
$_DATA_FLAGS['g'] = $_DATA_FLAGS['G'] = '>4 std. dev. from climatology';
$_DATA_FLAGS['h'] = $_DATA_FLAGS['H'] = 'data discontinuity';
$_DATA_FLAGS['i'] = $_DATA_FLAGS['I'] = 'interesting feature';
$_DATA_FLAGS['j'] = $_DATA_FLAGS['J'] = 'poor quality by visual inspection';
$_DATA_FLAGS['k'] = $_DATA_FLAGS['K'] = 'suspect/use with caution';
$_DATA_FLAGS['l'] = $_DATA_FLAGS['L'] = 'platform position over land';
$_DATA_FLAGS['m'] = $_DATA_FLAGS['M'] = 'known instrument malfunction';
$_DATA_FLAGS['n'] = $_DATA_FLAGS['N'] = 'vessel in port';
$_DATA_FLAGS['o'] = $_DATA_FLAGS['O'] = 'multiple original units';
$_DATA_FLAGS['p'] = $_DATA_FLAGS['P'] = 'position/movement uncertain';
$_DATA_FLAGS['q'] = $_DATA_FLAGS['Q'] = 'questionable data';
$_DATA_FLAGS['r'] = $_DATA_FLAGS['R'] = 'replaced with interpolated value';
$_DATA_FLAGS['s'] = $_DATA_FLAGS['S'] = 'data spike (visual)';
$_DATA_FLAGS['t'] = $_DATA_FLAGS['T'] = 'time duplicate';
$_DATA_FLAGS['u'] = $_DATA_FLAGS['U'] = 'failed statistical threshold test (SASSI)';
$_DATA_FLAGS['v'] = $_DATA_FLAGS['V'] = 'data spike (SASSI)';
$_DATA_FLAGS['x'] = $_DATA_FLAGS['X'] = 'data step/discontinuity (SASSI)';
$_DATA_FLAGS['y'] = $_DATA_FLAGS['Y'] = 'suspect value between steps (SASSI)';
$_DATA_FLAGS['z'] = $_DATA_FLAGS['Z'] = 'data passed evaluation';


/*-----------------------------

  Globally used Functions

  -----------------------------*/

function redirect_delay($seconds, $message, $href = '', $no_text = 0) {
  echo "$message\n";
  if ($no_text == 0)
    echo '<br />Redirecting you in '.$seconds.' second(s)', 
         '... or click <a href="'.$href.'">here.</a><br />',"\n";
 
  echo '<script type="text/javascript">',"\n",
       'function go()', "\n",
       '{', "\n",
       '  window.location.href="'.$href.'";', "\n",
       '}', "\n",
       'setTimeout("go()", '.$seconds.'*1000);', "\n",
       '</script>',"\n";
}

// check if an email address is valid or not (there is a default php method of this now...)
function is_valid_email($email) {
  // Email addresses need to match this pattern
  $email_regex = ":^([-!#\$%&'*+./0-9=?A-Z^_`a-z{|}~ ])+" .
                 "@([-!#\$%&'*+/0-9=?A-Z^_`a-z{|}~ ]+\\.)+" .
                 "[a-zA-Z]{2,6}\$:i";
 
  if (!empty($email))
    return preg_match($email_regex, $email);
  else
    return FALSE;
}

function javascript_popup($page, $title) {
  echo '<script type="text/javascript">',"\n",
       '<!--',"\n",
       "function open_users_page()\n{\n  window.open(\"$page\",\"$title\",\"toolbar=no,location=no,directories=no,scrollbars=no,menubar=no,resizable=no,width=600,height=400\");\n}","\n",
       '-->',"\n",
       '</script>',"\n";
}

// show all errors
function display_errors($errors) { 
  echo '<div class="errors">';

  echo 'The following is incorrect with your submission:<ul>',"\n";
 
  foreach ($errors as $error)
    echo "<li>$error</li>\n";

  echo "</ul>",
       '</div>';
}

// Display an option list with one selected
function display_options($options, $current) {
  foreach ($options as $k => $v) {
    echo '<option value="', $k, '"',
         ($k == $current ? ' selected="selected"' : ''),
         '>', htmlentities($v), "</option>\n";
  }
}

function show_options($options, $current) {
  foreach ($options as $k => $v) {
    $out .= '<option value="'. $k. '"'.
            ($k == $current ? ' selected="selected"' : '').
            '>'. htmlentities($v). "</option>\n";
  }
  return $out;
}

if(!function_exists("GregorianToJD")) {
// This function converts a Gregorian Date (which is the
// system used by most of the world including the U.S.)
// to a Julian Date where each day of the year is given
// a unique number (1 to 365) 
// function added by Richard Gange 
  function GregorianToJD ($month, $day, $year) { 
    if ($month > 2)
      $month = $month - 3; 
    else { 
      $month = $month + 9; 
      $year = $year - 1; 
    } 
    $c = floor($year / 100); 
    $ya = $year - (100 * $c); 
    $j = floor((146097 * $c) / 4); 
    $j += floor((1461 * $ya) / 4);
    $j += floor(((153 * $month) + 2) / 5); 
    $j += $day + 1721119; 
    return $j; 
  } 
}

// 5/9/2016 Jonathan Reynes: This function returns the base path based on the version
function GET_BASE($file, $version) {
  if (strlen($file) == 0)
    $file = $_SERVER['argv'][1];

  if ($version == 1)
    $base = SAMOS_PROCESSING_DIR . '/data/processing';    //Net/samosdev.[append]
  else if ($version == 220)
    $base = SAMOS_PROCESSING_DIR . '/data/autoqc';
  else if ($version == 250)
    $base = SAMOS_PROCESSING_DIR . '/data/visualqc';
  else
    $base = SAMOS_FTP_DIR . '/data';

  return $base;
}

// Jonathan Reynes: This functions checks to see whether you are using a netcdf file, for security purposes
function ISNETCDF($file) {
  if (!preg_match('/\.nc$/', $file)) {
    echo "That's not a " .$type. " file!";
    printFooter();
    exit;
  }
}

function json_encoded($data) {
  switch ($type = gettype($data)) {
    case 'NULL':
      return 'null';
    case 'boolean':
      return ($data ? 'true' : 'false');
    case 'integer':
    case 'double':
    case 'float':
      return $data;
    case 'string':
      return '"' . str_replace("/", "\\/", str_replace("\\'", "'", addslashes($data) )) . '"';
    case 'object':
      $data = get_object_vars($data);
    case 'array':
      $output_index_count = 0;
      $output_indexed = array();
      $output_associative = array();
      foreach ($data as $key => $value) {
        $output_indexed[] = json_encoded($value);
        $output_associative[] = json_encoded(strval($key)) . ':' . json_encoded($value);
        if ($output_index_count !== NULL && $output_index_count++ !== $key)
          $output_index_count = NULL;
      }
      if ($output_index_count !== NULL)
        return '[' . implode(',', $output_indexed) . ']';
      else
        return '{' . implode(',', $output_associative) . '}';
    default:
      return ''; // Not supported
  }
}

function pretty_json($json) {
  $tab = '&nbsp;&nbsp;&nbsp;&nbsp;';
  $indent_level = 0;
  for ($i = 0; $i < strlen($json); $i++) {
    switch ($json[$i]) {
      case '{':
        echo $json[$i];
        $indent_level++;
        echo '<br>';
        echo str_repeat($tab, $indent_level);
        break;
      case ':':
        echo $json[$i];
        echo ' ';
        break;
      case ',':
        echo $json[$i];
        echo '<br>';
        echo str_repeat($tab, $indent_level);
        break;
      case '}':
        $indent_level--;
        echo '<br>'.str_repeat($tab, $indent_level);
        echo $json[$i];
        break;
      default:
        echo $json[$i];
    }
  }
}

function getActiveShipCallSigns() {
  $call_signs = array();
  $query = "SELECT DISTINCT s.vessel_name, s.vessel_call_sign "
    ." FROM ship s "
    ."JOIN daily_file df "
    ."ON s.ship_id = df.ship_id "
    ."JOIN daily_file_piece dfp "
    ."ON df.daily_file_id = dfp.daily_file_id "
    ."JOIN daily_file_history dfh "
    ."ON dfp.daily_file_piece_id = dfh.daily_file_piece_id "
    ."JOIN version_no vn "
    ."ON dfh.version_id = vn.version_id "
    ."WHERE vn.process_version_no = 100 and "
    ."s.date_of_separation is NULL "
	  ."and s.vessel_call_sign NOT LIKE 'SHIP%'"
    ."GROUP BY s.ship_id, s.vessel_call_sign;";
  
  db_query($query);
  
  while ($row = db_get_row())
    $call_signs[] = $row->vessel_call_sign;
	
  return $call_signs;
}


?>