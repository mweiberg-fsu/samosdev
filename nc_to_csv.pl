#!/bin/env perl

##################################################################
#
# nc_to_csv.pl
#
# Jacob Rettig
# 8 June 2009
#
# Michael McDonald
# Jan 22, 2020
# kicked date range from jan 1, 2020 to jan 1, 2100
#
#
# usage:
#
# %> ./nc_to_csv.pl SHIP date_start date_end order_no version out_path
#
#     NETcdf_file = The netCDF file to update subset info for
#
##################################################################


# Version 004 changed by Neely Fawaz 5/22/15
# Changed value $test_date to be defined as $test_date = $_date_start;

use lib "./";
# The perl-netcdf interface
use NetCDF;

# Use predefined paths
use IncludeDirs::Perl_dirs qw(:DEFAULT);

# Database interface subroutines

require "perl_libs/perl_db_interface.pl";
$time_dir = "$codes_dir/perl_libs";

# Set constant for # of mins in a day
$min_day = 1440;

# Test number of inputs arguments.
$num_arg = @ARGV;
if ( $num_arg == 6 ) {
    $_ship = $ARGV[0];
    $_date_start = $ARGV[1];
    $_date_end = $ARGV[2];
    $_order_no = $ARGV[3];
    $_version = $ARGV[4];
    $_out_path = $ARGV[5];
}else {
    print("Incorrect number of arguments: $num_arg\n");
    exit_gracefully(" Please use format \"> ./nc_to_csv.pl SHIP date_start date_end order_no version out_path\"");
}

# Test second input: date_start in form YYYYMMDD
if (($_date_start < 19800101) || ($_date_start > 21000101))
{
   exit_gracefully("start date out of range; Date must be between Jan. 1, 1980 and Jan. 1, 2100");
}

# Test third input: date_end in form YYYYMMDD
if (($_date_end < 19800101) || ($_date_end > 21000101))
{
   exit_gracefully("end date out of range; Date must be between Jan. 1, 1980 and Jan. 1, 2100");
}

# Test third input: date_end in form YYYYMMDD
if ($_date_start > $_date_end)
{
   exit_gracefully("start date must be before the end date");
}

$coaps_start_time = `$time_dir/convdate.pl $_date_start`;
$coaps_end_time = `$time_dir/convdate.pl $_date_end`;
$coaps_curr_time = $coaps_start_time;

while($coaps_curr_time <= $coaps_end_time) {
    
   # $test_date = `$time_dir/invdate.pl $coaps_curr_time`;
   $test_date = $_date_start;
    chomp($test_date);
    
    # Make sure $test_date is in the right format (trim off hours and minutes)
    $len_test = length($test_date);
    if ($len_test > 8)
    {
	$test_date = substr($test_date, 0, 8);
    }

    print "\nWorking on $_ship, $test_date, $_order_no, $_version\n";
    
    if("$_order_no" ne "0" && $_order_no < 100) {
	$order = sprintf("%02d",$_order_no);
	$infile = "${_ship}_${test_date}v${_version}${order}.nc";
	$outfile = "${_ship}_${test_date}v${_version}${order}.csv";
	create_subset_from_nc();
	print "\tDone.\n";
    }else {
	# Get latest order number
	# DB call to check highest order for the corresponding file to create the
	# correct file with the correct order number here.
	$order_no =  Perl_db::get_latest_merged_order_no($_ship, $test_date, $_order_no, $_version);
	if ((100 <= $order_no) && ($order_no < 200) ) {
	    
	    # Get latest version
	    # DB call to check highest order for the corresponding file to create the
	    # correct file with the correct order number here.
	    $version = Perl_db::get_latest_merged_version($_ship, $test_date, $_order_no, $_version);
	    if ((200 <= $version) && ($version < 400) ) {
		
		$order = substr( $order_no, 1, 2 );
		$infile = "${_ship}_${test_date}v${version}${order}.nc";
		$outfile = "${_ship}_${test_date}v${version}${order}.csv";
		create_subset_from_nc();
		print "\tDone.\n";
	    } else {
		print "\tNo file for $_ship, $test_date, $_order_no, $_version\n";
	    }
	}else {
	    print "\tNo file for $_ship, $test_date, $_order_no, $_version\n";
	}
    }
    
    $coaps_curr_time += $min_day;
}

sub create_subset_from_nc {
    check_infile();
    getdata();
    makefile();
}

# This subroutine check if the .nc file is good.
sub check_infile {
    
    # Get first three values using string manipulation internal functions.
    # NOTE: THE DIVIDER BETWEEN THE CALL SIGN AND DATE MUST BE AN "_".
    $index = index( $infile, "_" );
    
    $ship = substr($infile, 0, $index);
    $date_for = substr( $infile,   $index + 1, 8 );
    $year     = substr( $infile,   $index + 1, 4 );
    $month    = substr( $date_for, 4,          2 );
    $index = index( $infile, "v" );
    $version = substr( $infile, $index + 1, 3 );
    if($_order_no != 0 && $_order_no < 100) {
	$order = "1" . substr( $infile, $index + 4, 2 );
    }
    
    # Print some info out
    print("SHIP : $ship\n");
    print("DATE FOR: $date_for\n");
    print("YEAR: $year\n");
    print("VERSION: $version\n");
    print("ORDER: $order\n");
    
    $path__root = $public_research;
    if($version < 200) {
	$path__root = $public_quick;
    }elsif($version < 220) {
	$path__root = $processing_research;
    }elsif($version < 250) {
	$path__root = $autoqc_research;
    }elsif($version < 300) {
	$path__root = $visualqc_research;
    }
    
    $ncfile  = "$path__root/$ship/$year/$infile";
    $index   = rindex( $infile, "." );
    $csvfile = "$outfile";
    
    # Check that SAMOS v25X NetCDF exists in the correct location and open it if
    # it does exist; if it does not print error statement and exit gracefully.
    if ( -e $ncfile ) {
	$ncid = NetCDF::open( "$ncfile", NetCDF::READ );
    }
    else {
	print("The file $ncfile does not exist.\n");
	exit_gracefully("The file $infile does not exist in the correct directory $path__root/$ship/$year/\n");
    }
    
    # Initialize and get value for call sign from metadata in the NetCDF file.
    $shiptest = "";
    $status = NetCDF::attget( $ncid, NetCDF::GLOBAL, "ID", \$shiptest );
    $shiptest =~ s/\0+//g;
    
    # NOTE:
    # For some yet unknown reason there is an extra character \0 at the end of the
    # ship name in shiptest that chomp cannot get rid of.  It is not a consistent
    # problem (maybe only when merging happens).  So, just eliminating it.  --TS
    
    # Test for matching call sign; exit gracefully if they do not match.
    print("TEST CALL SIGN: $ship $shiptest\n\n");
    if ( $shiptest ne $ship ) {
	$status = NetCDF::close($ncid);
	exit_gracefully("call sign in file name: $ship does NOT match call sign of metadata: $shiptest\n");
    }
    
    # Close NetCDF file.
    $status = NetCDF::close($ncid);
}

# This subroutine check if the .nc file is good.
sub getdata {
    
    # Open netCDF file
    $ncid = NetCDF::open( $ncfile, RD );
    print "ncid:  $ncid\n";
    print "ncfile:  $ncfile\n";
    
    # Get number of records
    $rec_id  = NetCDF::dimid( $ncid, "time" );
    $dimname = "";
    $numrec  = -1;
    NetCDF::diminq( $ncid, $rec_id, $dimname, $numrec );
    print "numrec:  $numrec\n";
    
    # Get (max) flag length
    $f_id    = NetCDF::dimid( $ncid, "f_string" );
    $dimname = "";
    $flglen  = -1;
    NetCDF::diminq( $ncid, $f_id, $dimname, $flglen );
    print "flglen:  $flglen\n";
    
    # Get flags
    $flag_id = NetCDF::varid( $ncid, "flag" );
    for ( $n = 0 ; $n < $numrec ; ++$n ) {
	@start = ( $n, 0 );
	@count = ( 1, $flglen );
	
	@fileflags = ();
	
	# Get flag
	NetCDF::varget( $ncid, $flag_id, \@start, \@count, \@fileflags );
	
	$flags[$n] = [@fileflags];    # push reference to copy of @fileflags
    }
    
    $numvars = $#{$flags[0]} + 1;
    print "numvars:  $numvars\n";
    
    @vardata = ();
    $qcindex = "";
    $special = "";
    $missing = "";
 
    @missing = ("0") x ($numvars);
    @special = ("0") x ($numvars);
    
    @start = (0);
    @count = ($numrec);
    
# Loop through variables
# This is trickier than it sounds...how to determine how many vars you have?
#for ( $i = 0; $i < $numvars; $i++ )  --> does not work...will miss some vars b/c
#                                         some vars have same qcindex
#while ( NetCDF::varget( $ncid, $i, \@start, \@count, \@vardata ) == 0 )
#                                     --> does not work either...will bomb on vars
#                                         that have different dimensions
#while ( $qcindex != $numvars )       --> semiworks
# Method used:  loop until see last qcindex
# Note that if the last qcindexed variable appears in the netcdf file before some other
#    qcindexed vars, then those other vars won't be correct.  In other words, this method
#    assumes that variables are ordered by qcindex for the most part.
    $i = 0;
    while ( $qcindex != $numvars ) {
	NetCDF::varget( $ncid, $i, \@start, \@count, \@vardata );
	  NetCDF::attget( $ncid, $i, "qcindex",       \$qcindex );
	  NetCDF::varinq( $ncid, $i, $varname, $type, $ndims, \@dimids, $natts );
	  print "var$qcindex: $varname\n";
	  if($varname ne 'time' && $varname ne 'lat' && $varname ne 'lon') {
	      NetCDF::attget( $ncid, $i, "missing_value", \$missing );
		NetCDF::attget( $ncid, $i, "special_value", \$special );
		
		@missing[ $qcindex - 1 ] = $missing;
		@special[ $qcindex - 1 ] = $special;
		
		#print "missing$qcindex: $missing\n";
		#print "special$qcindex: $special\n";
	    }
	  
	  # Pidgeon-hole reference to vardata in right hole by qcindex
	  $data[ $qcindex - 1 ] = [@vardata];
	  	  
	  $varnames[ $qcindex - 1 ] = $varname;
	  
	  ++$i;
      }
    
    NetCDF::varget( $ncid, $numvars, \@start, \@count, \@vardata );
    NetCDF::attget( $ncid, $i, "qcindex",       \$qcindex );
    NetCDF::varinq( $ncid, $numvars, $varname, $type, $ndims, \@dimids, $natts );
    @date_var = @vardata;
    $date_var_name = $varname;
    print "var$qcindex: $varname\n";
    
    NetCDF::varget( $ncid, $numvars+1, \@start, \@count, \@vardata );
    NetCDF::attget( $ncid, $i, "qcindex",       \$qcindex );
    NetCDF::varinq( $ncid, $numvars+1, $varname, $type, $ndims, \@dimids, $natts );
    @time_var = @vardata;
    $time_var_name = $varname;
    print "var$qcindex: $varname\n";
    
    # Close netCDF file
    NetCDF::close($ncid);
}

sub makefile {
    if( -d $_out_path ) {
	open CSV, "> $_out_path/$csvfile"
	    || exit_gracefully( "Could not open $_out_path/$csvfile for writing" );
	select(CSV);
    }else {
	print "$_out_path does not exist!  Sending file to STDOUT\n";
	print "DATA START\n";
    }
    
    $seperator1 = $seperator2 = ",";
    spacer($date_var_name,"");
    spacer($time_var_name,"");
    $i = 0;
    for ( $i = 0; $i < $numvars - 1; ++$i ) {
	spacer($varnames[$i],$varnames[$i]." flag");
	if($varnames[$i] = "time") {
	    $time_index = $i;
	}
    }
    $seperator2 = "";
    spacer($varnames[$i],$varnames[$i]." flag");
    print "\n";
    
    for ( $i = 0; $i < $numrec; ++$i ) {
	$seperator1 = $seperator2 = ",";
	spacer(@date_var[$i],"");
	spacer(@time_var[$i],"");
	for ( $j = 0; $j < $numvars - 1; ++$j ) {
	    $flag = "";
	    if($flags[$i][$j] ne "") {
		$flag = sprintf("%c", $flags[$i][$j]);
	    }
	    #if(@missing[$j] == $data[$j][$i] || @special[$j] == $data[$j][$i]) {
		#spacer("",$flag);
	    #} else {
		spacer($data[$j][$i],$flag);
	    #}
	}
	$flag = "  ";
	if($flags[$i][$j] ne "") {
	    $flag = sprintf("%c", $flags[$i][$j]);
	}
	$seperator2 = "";
	spacer($data[$j][$i],$flag);
	print "\n";
    }

    if( -d $_out_path ) {
	select(STDOUT);
	
	close CSV;
    }
}

sub spacer
{
    local(@invar) = @_;
    $spaces = "";
    $val = @invar[0];
    $flag = @invar[1];

    $is_plain_decimal = ($val =~ /^(\-?\d+\.?\d*|\-?\.\d+)$/);
    $is_scientific = ($val =~ /^(\-?(?:\d+\.?\d*|\.\d+))[eEdD][\+\-]?\d+$/);

    if($is_plain_decimal) {
	$val = sprintf("%.4f",$val);
	$val += 0;
    }

    $varlength = length ($val);
    # Never truncate numeric values (especially scientific notation),
    # or the exponent may be dropped (e.g., 5.144000169e-05 -> 5.144000169).
    if (!$is_plain_decimal && !$is_scientific && $varlength > 11)
    {
	$val = substr($val,0,11);
	$varlength = length ($val);
    }
    if ($varlength <= 11)
    {
	$numspaces = (11 - $varlength);
	$spaces1 = " " x $numspaces;
    }

    $flaglength = length ($flag);
    if ($flaglength <= 11)
    {
	$numspaces = (11 - $flaglength);
	$spaces2 = " " x $numspaces;
    }

    
    

    print("$spaces1$val$seperator1");
    if($flag ne "") {
	print("$spaces2$flag$seperator2");
    }
}

# This subroutine handles error messages and was originally written by Tina Suen
sub exit_gracefully
{
    ($error_str) = @_;
    
    print "Error:  $error_str\n";
    exit(1);
    
}

