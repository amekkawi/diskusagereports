<?php

/* 
 * Copyright (c) 2011 André Mekkawi <contact@andremekkawi.com>
 * Revision: $Revision$
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereport.sf.net/license.html
 */

// ======================================================
// Customize the following two arrays to change the
// grouping for the "Last Modified" and "File Sizes" tabs.

// Labels for size ranges.
$sizeGroups = array(
	array('label' => '1 GB or More', 'size' => 1024 * 1024 * 1024),
	array('label' => '500 MB - 1 GB', 'size' => 1024 * 1024 * 500),
	array('label' => '250 MB - 500 MB', 'size' => 1024 * 1024 * 250),
	array('label' => '125 MB - 250 MB', 'size' => 1024 * 1024 * 125),
	array('label' => '75 MB - 125 MB', 'size' => 1024 * 1024 * 75),
	array('label' => '25 MB - 75 MB', 'size' => 1024 * 1024 * 25),
	array('label' => '10 MB - 25 MB', 'size' => 1024 * 1024 * 10),
	array('label' => '5 MB - 10 MB', 'size' => 1024 * 1024 * 5),
	array('label' => '1 MB - 5 MB', 'size' => 1024 * 1024 * 1),
	array('label' => '500 KB - 1 MB', 'size' => 1024 * 500),
	array('label' => '250 KB - 500 KB', 'size' => 1024 * 250),
	array('label' => '100 KB - 250 KB', 'size' => 1024 * 100),
	array('label' => '50 KB - 100 KB', 'size' => 1024 * 50),
	array('label' => '25 KB - 50 KB', 'size' => 1024 * 25),
	array('label' => '10 KB - 25 KB', 'size' => 1024 * 10),
	array('label' => '5 KB - 10 KB', 'size' => 1024 * 5),
	array('label' => '1 KB - 5 KB', 'size' => 1024 * 1),
	array('label' => 'Less than 1 KB', 'size' => 0)
);

// Labels for age ranges.
$dateFormat = 'Y-m-d';
$modifiedGroups = array(
	array('label' => '10 Years or More', 'date' => '-10 year'),
	array('label' => '5 - 10 Years', 'date' => '-5 year'),
	array('label' => '2 - 5 Years', 'date' => '-2 year'),
	array('label' => '1 - 2 Years', 'date' => '-1 year'),
	array('label' => '270 - 365 Days', 'date' => '-270 day'),
	array('label' => '180 - 270 Days', 'date' => '-180 day'),
	array('label' => '90 - 180 Days', 'date' => '-90 day'),
	array('label' => '60 - 90 Days', 'date' => '-60 day'),
	array('label' => '30 - 60 Days', 'date' => '-30 day'),
	array('label' => '15 - 30 Days', 'date' => '-15 day'),
	array('label' => '7 - 15 Days', 'date' => '-7 day'),
	array('label' => '1 - 7 Days', 'date' => '-1 day'),
	array('label' => 'Today', 'date' => 'today'),
	array('label' => 'Future', 'date' => '9999-99-99')
);

// ======================================================

// export TZ=UTC; find "DIRECTORYNAME" -type d -or -type f -printf '%y %TY-%Tm-%Td %TT %s %d %h %f\n' > "OUTFILENAME"; unset TZ
// cat diskusage-gs.txt | sed -En -e 's/^d/&/p' -e 's/^f.+\.(jpg)$/&/p' | php scripts/process.php ../diskusage-data/test2
// php scripts/find.php `pwd` | sed -E -e 's/^.*\.svn.*$//' -e 's/^.*diskusage-[a-z0-9]+\.txt.*$//' -e 's/^.*\.settings.*$//' -e 's/^.*\$dev.*$//' -e 's/^.*\.DS_Store.*$//' -e 's/^.*\.tmp_.*$//' -e '/^$/d' | php scripts/process.php -n "Disk Usage Reports Code" ../diskusage-data/test2

// Make sure this script is run from the command line.
if (php_sapi_name() != "cli") {
	echo "Must be run from the command line.\n";
	exit(1);
}

// Show/hide debugging output (if any).
define('DEBUG', FALSE);

require_once('inc/functions.inc.php');
require_once('inc/process.class.php');

// Backwards compatibility includes.
if(!function_exists('json_encode')) {
	require_once('inc/json_encode.php');
}
if(!function_exists('file_put_contents')) {
	require_once('inc/file_put_contents.php');
}

$processor = new Process();

// Default arguments (most arguments are stored within $processor.
$args = array(
	'timezone' => function_exists('date_default_timezone_get') ? @date_default_timezone_get() : 'America/New_York'
);

$cliargs = array_slice($_SERVER['argv'], 1);
$syntax = "Syntax: php process.php [options] <reportdir> [<filelist>]\nSee http://diskusagereport.sf.net/docs/ for help.\n";

// Process command line arguments.
while (!is_null($cliarg = array_shift($cliargs))) {
	$shifted = true;
	
	switch ($cliarg) {
		case '/?':
		case '-?':
		case '-h':
		case '--help':
			echo $syntax;
			// TODO: Output help.
			break;
		case '-tz':
			$args['timezone'] = $shifted = array_shift($cliargs);
			break;
		case '-d':
			$processor->setDelim($shifted = array_shift($cliargs));
			break;
		case '-t':
			$processor->setTotalsDepth(intval($shifted = array_shift($cliargs)));
			if (!preg_match('/^[0-9]+$/', $shifted)) echo "$cliarg must be followed by a number.\n"; exit(1);
			break;
		case '-nt':
			$processor->setNoTree(true);
			break;
		case '-mt':
			$processor->setMaxTreeSize(intval($shifted = array_shift($cliargs)));
			if (!preg_match('/^[0-9]+$/', $shifted)) echo "$cliarg must be followed by a number.\n"; exit(1);
			break;
		case '-ds':
			$processor->setDS($shifted = array_shift($cliargs));
			break;
		case '-td':
			$processor->setTop100Depth(intval($shifted = array_shift($cliargs)));
			if (!preg_match('/^[0-9]+$/', $shifted)) echo "$cliarg must be followed by a number.\n"; exit(1);
			break;
		case '-n':
			$processor->setName($shifted = array_shift($cliargs));
			break;
		case '-l':
			$processor->setMaxLineLength(intval($shifted = array_shift($cliargs)));
			if (!preg_match('/^[0-9]+$/', $shifted)) echo "$cliarg must be followed by a number.\n"; exit(1);
			break;
		default:
			$processor->setReportDir($cliarg);
			$processor->setFileList(array_shift($cliargs));
			$cliargs = array();
	}
	
	// If we shifted and found nothing, output an error.
	if (is_null($shifted)) {
		echo "Missing value after argument $cliarg\n".$syntax;
		exit(1);
	}
}

// Make sure the <reportdir> was set.
if (is_null($processor->getReportDir())) {
	echo "<reportdir> argument is missing\n".$syntax;
	exit(1);
}

// Read the file list from STDIN if it was not specified.
if (is_null($processor->getFileList())) {
	$processor->setFileList('php://stdin');
}

// Otherwise, make sure the <filelist> exists.
elseif (!is_file($processor->getFileList())) {
	echo "The <filelist> '" . $processor->getFileList() . "' does not exist or is not a file.\n";
	exit(1);
}

// Set the timezone.
if (!(function_exists("date_default_timezone_set") ? @(date_default_timezone_set($args['timezone'])) : @(putenv("TZ=".$args['timezone'])))) {
	echo "'timezone' config was set to an invalid identifier.";
	exit(1);
}

// Format the dates in $modifiedGroups.
for ($i = 0; $i < count($modifiedGroups); $i++) {
	$modifiedGroups[$i]['date'] = FormatDate($modifiedGroups[$i]['date'], $dateFormat);
}

function WarningHandler() {
	$args = func_get_args();
	$error = array_shift($args);
	
	if ($args[0] == PROCESS_WARN_WRITEFAIL) {
		echo 'Failed to write: ' . $args[1] . (isset($args[2]) ? ' for ' . $args[2] : '') . "\n";
	}
}

$processor->setSizeGroups($sizeGroups);
$processor->setModifiedGroups($modifiedGroups);
$processor->setWarningCallback('WarningHandler');

switch ($ret - $processor->run()) {
	case PROCESS_FAILED_OPEN_FILELIST:
		echo "The <filelist> could not be opened.\n";
		break;
	case PROCESS_INVALID_REPORTDIR:
		echo "The <reportdir> already exists and is not a directory.\n";
		break;
	case PROCESS_INVALID_HEADER:
		echo "The header line in the <filelist> is invalid.\n";
		break;
	case PROCESS_FAILED_REPORTDIR_MKDIR:
		echo "The <reportdir> could not be created.\n";
		break;
}

exit($ret === TRUE ? 0 : $ret);
?>
