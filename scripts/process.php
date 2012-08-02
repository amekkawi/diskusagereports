<?php

/* 
 * Copyright (c) 2011 André Mekkawi <diskusage@andremekkawi.com>
 * Version: $Source Version$
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */

// ======================================================
// Customize the following two arrays to change the
// grouping for the "Last Modified" and "File Sizes" tabs.

define('START_TIME', time());

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

if (DEBUG) echo "Includes...\n";

require_once('inc/functions.inc.php');
require_once('inc/process.class.php');

// Backwards compatibility includes.
if(!function_exists('json_encode')) {
	require_once('inc/json_encode.php');
}
if(!function_exists('file_put_contents')) {
	require_once('inc/file_put_contents.php');
}

if (DEBUG) echo "Creating processor...\n";

$processor = new Process();

if (DEBUG) echo "Setting timezone...\n";

// Default arguments (most arguments are stored within $processor)
$args = array(
	'timezone' => function_exists('date_default_timezone_get') ? @date_default_timezone_get() : 'America/New_York',
);

if (DEBUG) echo "Processing command line arguments...\n";

$cliargs = array_slice($_SERVER['argv'], 1);
$syntax = "Syntax: php process.php [OPTIONS] [-] <report-directory> [[-] <filelist>]\nUse -h for full help or visit diskusagereports.com/docs.\n";

$syntax_long = <<<EOT
Syntax: php process.php [OPTIONS] <report-directory> [<filelist>]

<report-directory>
The directory where the report files will be saved. This should point to a
directory under the 'data' directory.
    Examples:
        /var/www/html/diskusage/data/myreport
        C:\Inetpub\wwwroot\diskusage\data\myreport

<filelist>
The file that was created using one of the 'find' scripts (e.g. find.php).
If you ommit this, process.php will attempt to read the file list from STDIN.

The OPTIONS are:
      
      - (hyphen)
      If the <report-directory> or <filelist> are the same as one of the
      OPTIONS for this script (e.g. "-d"), you must use a minus sign as an
      argument before it. You should do this if you ever expect the
      <directory-to-scan> to start with a minus sign.
      
      -d <delim>
      The field delimiter that each line of the filelist will be split using.
      The default is the NULL character. Will be ignored if <filelist> has a
      header line (see notes).
      
      -ds <directoryseparator>
      Specify the directory separator used in the file list. This is useful
      if the list from step 1 was generated on a different operating system
      which uses a different directory separator. For example, Windows uses
      a backslash (\) while Linux/BSD/Mac/etc systems use a forward slash (/).
      The default is the directory separator for the operating system
      processing the report.  Will be ignored if <filelist> has a header
      line (see notes).
      
      -fp
      Display the full path of the directories in the report. This is off by
      default since it could potentially pose a security risk.
      
      -l <num>
      Lines in the report that are longer than <num> will not be processed.
      This is just a failsafe to prevent the script from processing a list
      file that is not formatted properly. The default is 1024.
      
      -mt <bytes>
      The maximum number of bytes that the 'directory tree' file can be.
      The default is 819200. If the 'directory tree' file gets larger than
      this number, then the script will act as if -nt had been specified.
      
      -n <reportname>
      This text will display in the header of the report.
      
      -nt
      Disable the directory tree that appears on the left side of the report.
      
      -q
      Do not output any text to STDOUT. The script will return a non-zero
      if it fails.
      
      -ss <seconds>
      The minimum number of seconds that must elapse before another status
      message (e.g. 'Read X bytes, processed X lines...') is outputted.
      Default is 15 seconds.
      
      -su <suffix>
      Set the suffix of report files. This is '.txt' by default. You must
      also edit the 'suffix' variable in index.html to include any suffix
      besides the default or an empty suffix.
      
      -t <depth>
      Limit the "File Sizes", "Modified", and "File Types" totals to only
      <depth> directories deep in the report. This is useful if the directory
      being reported on has many files, which can cause the report to take a
      long time to generate. For example, if this is set to 3 the directory
      ./a, ./a/b and ./a/b/c will have these totals available, but ./a/b/c/d
      will not. The default is 6.

      -td <depth>
      Similar to -t but instead limits the "Top 100" list to only <depth>
      directories deep in the report. This is useful if the directory being
      reported on has many files, which can cause the report to take a long
      time to generate. The default is 3.
      
      -tz <timezone>
      Set the report timezone. These are the same timezones as
      http://php.net/manual/en/timezones.php. The default is the system's
      timezone (if it can be determined).
      
      -v
      Output additional information as the script executes.
      
      -vv
      Output more information than -v.
      
Notes:

      o You should set the -tz option as trying to determine the system's
        timezone is unreliable.
        
      o You may execute process.php on a separate server than the 'find'
        script if you are worried about it using CPU time.
        
      o The directory separator used in <filelist> must be a forward slash
        if this script is executed on a *nix system.
        
      o If the <filelist> has a header line (starts with a #) then the -d
        and -ds OPTIONS will be ignored since the header explicitly
        defines what their values should be.

See also: diskusagereports.com/docs


EOT;

// Process command line arguments.
while (!is_null($cliarg = $cliargOrig = array_shift($cliargs))) {
	switch ($cliarg) {
		case '/?':
		case '-?':
		case '-h':
		case '--help':
			echo $syntax_long;
			exit(1);
		case '-tz':
			$args['timezone'] = $cliarg = array_shift($cliargs);
			break;
		case '-d':
			$processor->setDelim($cliarg = array_shift($cliargs));
			break;
		case '-t':
			if (!preg_match('/^[0-9]+$/', $cliarg = array_shift($cliargs))) { echo "$cliargOrig must be followed by a number.\n".$syntax; exit(1); }
			$processor->setTotalsDepth(intval($cliarg));
			break;
		case '-nt':
			$processor->setNoTree(TRUE);
			break;
		case '-mt':
			if (!preg_match('/^[0-9]+$/', $cliarg = array_shift($cliargs))) { echo "$cliargOrig must be followed by a number.\n".$syntax; exit(1); }
			$processor->setMaxTreeSize(intval($cliarg));
			break;
		case '-ds':
			$processor->setDS($cliarg = array_shift($cliargs));
			break;
		case '-td':
			if (!preg_match('/^[0-9]+$/', $cliarg = array_shift($cliargs))) { echo "$cliargOrig must be followed by a number.\n".$syntax; exit(1); }
			$processor->setTop100Depth(intval($cliarg));
			break;
		case '-n':
			$processor->setName($cliarg = array_shift($cliargs));
			break;
		case '-l':
			if (!preg_match('/^[0-9]+$/', $cliarg = array_shift($cliargs))) { echo "$cliargOrig must be followed by a number.\n".$syntax; exit(1); }
			$processor->setMaxLineLength(intval($cliarg));
			break;
		case '-q':
			$processor->setVerboseLevel(PROCESS_VERBOSE_QUIET);
			break;
		case '-v':
			$processor->setVerboseLevel(PROCESS_VERBOSE_HIGHER);
			break;
		case '-vv':
			$processor->setVerboseLevel(PROCESS_VERBOSE_HIGHEST);
			break;
		case '-fp':
			$processor->setIncludeFullPath(true);
			break;
		case '-su':
			$processor->setSuffix($cliarg = array_shift($cliargs));
			break;
		case '-ss':
			if (!preg_match('/^[0-9]+$/', $cliarg = array_shift($cliargs))) {
				echo "$cliargOrig must be followed by a number.\n".$syntax; exit(1);
			}
			$processor->setMinStatusSeconds(intval($cliarg));
			break;
		case '-':
			if (!is_null($processor->getReportDir()) && !is_null($processor->getFileList())) {
				echo "Unexpected argument: $cliarg\n" . $syntax;
				exit(1);
			}
			elseif (is_null($cliarg = array_shift($cliargs))) {
				continue;
			}
		default:
			if (is_null($processor->getReportDir())) {
				$processor->setReportDir($cliarg);
			}
			elseif (is_null($processor->getFileList())) {
				$processor->setFileList($cliarg);
			}
			else {
				echo "Unexpected argument: $cliarg\n" . $syntax;
				exit(1);
			}
	}
	
	// If we shifted and found nothing, output an error.
	if (is_null($cliarg)) {
		echo "Missing value after argument $cliargOrig\n".$syntax;
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
	echo "'timezone' config was set to an invalid identifier.\n";
	exit(1);
}

// Format the dates in $modifiedGroups.
for ($i = 0; $i < count($modifiedGroups); $i++) {
	$modifiedGroups[$i]['date'] = FormatDate($modifiedGroups[$i]['date'], $dateFormat);
}

function FormatBytes($bytes) {
	if ($bytes >= 1024 * 1024 * 1024 * 1024)
		return number_format($bytes / 1024 / 1024 / 1024 / 1024, 2) . ' TB';
	elseif ($bytes >= 1024 * 1024 * 1024)
		return number_format($bytes / 1024 / 1024 / 1024, 2) . ' GB';
	elseif ($bytes >= 1024 * 1024)
		return number_format($bytes / 1024 / 1024, 2) . ' MB';
	elseif ($bytes >= 1024)
		return number_format($bytes / 1024, 2) . ' KB';
	else
		return number_format($bytes) . ' byte' . ($bytes == 1 ? '' : 's');
}

function EventHandler() {
	global $processor;
	
	$args = func_get_args();
	$eventCode = array_shift($args);
	
	$infoPrefix = '  ';
	$warnPrefix = '> ';
	
	switch ($eventCode) {
		case PROCESS_INFO_PROCESSING_FILELIST:
			echo $infoPrefix . "Processing filelist...\n";
			break;
		case PROCESS_INFO_PROCESSING_HEADER:
			echo $infoPrefix . "Processing header...\n";
			break;
		case PROCESS_INFO_PROCESSING_ERROR:
			echo $infoPrefix . 'Processing error: ' . $args[0] . "\n";
			break;
		case PROCESS_INFO_PROCESSING_FILE:
			echo $infoPrefix . 'Processing file: ' . $args[0] . ' ' . $args[1] . "\n";
			break;
		case PROCESS_INFO_SAVETREE:
			echo $infoPrefix . "Saving dir tree...\n";
			break;
		case PROCESS_INFO_SAVESETTINGS:
			echo $infoPrefix . "Saving settings...\n";
			break;
		case PROCESS_INFO_ENTERDIR:
			if ($args[2] == 0)
				echo $infoPrefix . "Enter root dir" . (empty($args[0]) ? '' : ": " . $args[0]) . "\n";
			else
				echo $infoPrefix . 'Enter dir: ' . $args[0] . "\n";
			break;
		case PROCESS_INFO_EXITDIR:
			if ($args[2] == 0)
				echo $infoPrefix . 'Exit root dir' . (empty($args[0]) ? '' : ": " . $args[0]) . "\n";
			else
				echo $infoPrefix . 'Exit dir: ' . $args[0] . (is_null($args[1]) ? '' : ' (now ' . $args[1] . ')') . "\n";
			break;
		case PROCESS_INFO_COMPLETE:
			$infoPrefix .= 'Complete! ';
		case PROCESS_INFO_STATUS:
			$timeDiff = time() - START_TIME;
			list ($bytes, $maxBytes, $lines, $written, $files) = $args;
			echo $infoPrefix . 'Processed ' . number_format($args[2]) . ' lines from ' . FormatBytes($args[0]) . (!is_null($args[1]) ? ' (' . number_format($args[0] * 100 / $args[1], 1) . '%)' : '') . ', wrote ' . FormatBytes($args[3]) . " in " . number_format($args[4]) . " file" . ($args[4] == 1 ? '' : 's') . ", took " . number_format($timeDiff) .  " second" . ($timeDiff == 1 ? '' : 's') . ".\n";
			break;
		case PROCESS_INFO_MEMORY:
			echo $infoPrefix . 'Used ' . $args[0] . '% of memory limit (' . $args[1] . ")\n";
			break;
		case PROCESS_INFO_TREEDISABLED:
			echo $infoPrefix . 'Directory tree exceeded maximum size (' . number_format($processor->getMaxTreeSize()) . " bytes), and has been disabled.\n";
			break;
		case PROCESS_WARN_TOOLONG:
			echo $warnPrefix . 'Line ' . $args[0] . " invalid. Exceeds max line length.\n";
			break;
		case PROCESS_WARN_BADMATCH:
			echo $warnPrefix . 'Line ' . $args[0] . " is invalid. Does not match correct pattern.\n";
			break;
		case PROCESS_WARN_COLCOUNT:
			echo $warnPrefix . 'Line ' . $args[0] . " is invalid. Incorrect column count.\n";
			break;
		case PROCESS_WARN_EMPTYPATH:
			echo $warnPrefix . 'Line ' . $args[0] . " is invalid. The path must be at least one character long.\n";
			break;
		case PROCESS_WARN_WRITEFAIL:
			echo $warnPrefix . "Failed to write '" . $args[0] . (isset($args[1]) ? "' for '" . $args[1] : '') . "'.\n";
			break;
	}
}

$processor->setSizeGroups($sizeGroups);
$processor->setModifiedGroups($modifiedGroups);
$processor->setEventCallback('EventHandler');

$ret = $processor->run();

$processor->dumpProfiles();

if ($ret != PROCESS_OK && $processor->getVerboseLevel() > PROCESS_VERBOSE_QUIET) {
	$details = $processor->getFailDetails();
	
	switch ($ret) {
		case PROCESS_FAIL_OPEN_FILELIST:
			echo "FAIL: The <filelist> could not be opened.\n";
			break;
		case PROCESS_FAIL_INVALID_REPORTDIR:
			echo "FAIL: The <reportdir> already exists and is not a directory.\n";
			break;
		case PROCESS_FAIL_INVALID_HEADER:
			echo "FAIL: The header line in the <filelist> is invalid:\n" . $details['line'] . "\n";
			break;
		case PROCESS_FAIL_REPORTDIR_PARENT:
			echo "FAIL: The parent directory of <reportdir> does not exist.\n";
			break;
		case PROCESS_FAIL_REPORTDIR_MKDIR:
			echo "FAIL: The <reportdir> could not be created.\n";
			break;
		case PROCESS_FAIL_INVALID_CHARACTERS:
			echo "FAIL: <filelist> contains characters that are not UTF-8, Windows-1252 or ISO-8859-1.\n";
			break;
		case PROCESS_FAIL_UNEXPECTED_HEADER:
			echo "FAIL: <filelist> contains a header line in an unexpected location. It must always be the first non-error line in the file:\n" . $processor->getFailLine() . "\n";
			break;
		case PROCESS_FAIL_UNSUPPORTED_LIST_VERSION:
			echo "FAIL: <filelist> uses a version that does script does not support:\n" . $processor->getFailLine() . "\n";
			break;
		case PROCESS_FAIL_SETTINGS_WRITEFAIL:
			echo "FAIL: Failed to write settings file.\n";
			break;
	}
}

exit($ret);
?>
