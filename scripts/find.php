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

if (!defined('E_DEPRECATED')) define('E_DEPRECATED', 8192);

// Disable warnings, notices and deprecation messages.
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

require_once('inc/find.class.php');

$STDERR = fopen('php://stderr', 'w+');

if (php_sapi_name() != "cli") {
	fwrite($STDERR, "Must be run from the command line.\n"); exit(1);
}

if (!(function_exists("date_default_timezone_set") ? @(date_default_timezone_set('UTC')) : @(putenv("TZ=UTC")))) {
	echo fwrite($STDERR, "Timezone could not be set to UTC."); exit(1);
}

$find = new Find();
$directory = NULL;
$force32Bit = false;

$cliargs = array_slice($_SERVER['argv'], 1);
$syntax = "Syntax: php find.php [-d <char|'null'>] [-ds <char>] [--force32bit]\n                     [-] <directory-to-scan>\nUse -h for full help or visit diskusagereports.com/docs.\n";

$syntax_long = <<<EOT

Syntax: php find.php [-d <char|'null'>] [-ds <char>] [--force32bit]
                     [-] <directory-to-scan>

Arguments:

-d <char|'null'>
Optionally specify the field delimiter for each line in the output.
Must be a single ASCII character or the word 'null' for the null character.
The default is the space character.

-ds <directoryseparator>
Optionally specify the directory separator used between directory names.
The default is the directory separator for the operating system.

--force32bit
Force the script to execute on 32-bit versions of PHP.
This may lead to incorrect totals if find.php encounters files over 2 GB.

- (hyphen)
If the <directory-to-scan> is the same as one of the arguments for this script
(e.g. '-d'), you must use a minus sign as an argument before it. You should
do this if you ever expect the <directory-to-scan> to start with a minus sign.

<directory-to-scan>
The directory that the list of sub-directories and files will be created for.

See also: diskusagereports.com/docs


EOT;

while (!is_null($cliarg = $cliargOrig = array_shift($cliargs))) {
	switch ($cliarg) {
		case '/?':
		case '-?':
		case '-h':
		case '--help':
			fwrite($STDERR, $syntax_long);
			exit(1);
		/*case '-i':
			array_push($args['include'], $cliarg = array_shift($cliargs));
			break;
		case '-e':
			array_push($args['exclude'], $cliarg = array_shift($cliargs));
			break;*/
		case '-d':
			if (!is_null($cliarg = array_shift($cliargs))) {
				if ($cliarg != "null" && strlen($cliarg) != 1) {
					fwrite($STDERR, "The field delimiter must be exactly one character long.\n".$syntax); exit(1);
				}
				
				$find->setDelim($cliarg == "null" ? "\x00" : $cliarg);
			}
			break;
		case '-ds':
			if (!is_null($cliarg = array_shift($cliargs))) {
				if (strlen($cliarg) != 1) {
					fwrite($STDERR, "The directory separator must be exactly one character long.\n".$syntax); exit(1);
				}
				
				$find->setDS($cliarg);
			}
			break;
		case '--force32bit':
			$force32Bit = true;
			break;
		
		case '-':
			if (is_null($cliarg = array_shift($cliargs)))
				continue;
		default:
			if (!is_null($directory)) {
				fwrite($STDERR, "Unexpected argument: $cliarg\n".$syntax); exit(1);
			}
			
			$directory = $cliarg;
	}
	
	if (is_null($cliarg)) {
		fwrite($STDERR, "Missing value after argument $cliargOrig\n".$syntax);
		exit(1);
	}
}

// ==============================
// Validate and clean arguments
// ==============================

if (is_null($directory)) {
	fwrite($STDERR, "The <directory-to-scan> argument is missing.\n".$syntax); exit(1);
}

if (!is_int( 9223372036854775807 ) && !$force32Bit) {
	fwrite($STDERR, "You are running a 32-bit version of PHP.\nThis may lead to incorrect totals if find.php encounters files over 4 GB.\nUse --force32bit to override.");
	exit(1);
}

switch($ret = $find->run($directory, NULL, $STDERR)) {
	case FIND_NOT_DIRECTORY:
		fwrite($STDERR, "The <directory-to-scan> does not exist or is not a directory.\n");
		break;
	case FIND_FAILED_RESOLVE:
		fwrite($STDERR, "Failed to resolve <directory-to-scan> to its full path. You may not have access (read and exec) to the directory or its parent directories.\n");
		break;
	case FIND_FAILED_STAT:
		fwrite($STDERR, "Failed to retrieve info (via stat) on <directory-to-scan>. You may not have access to the directory or its parent directories.\n");
		break;
}

fclose($STDERR);
exit($ret === TRUE ? 0 : 1);
?>
