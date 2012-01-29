<?php

/* 
 * Copyright (c) 2011 André Mekkawi <contact@andremekkawi.com>
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereport.sf.net/license.html
 */

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

$cliargs = array_slice($_SERVER['argv'], 1);
$syntax = "Syntax: php find.php [options] <directory>\nSee http://diskusagereport.sf.net/docs/index.html for help.\n";

while (!is_null($cliarg = array_shift($cliargs))) {
	$shifted = true;
	
	switch ($cliarg) {
		case '/?':
		case '-?':
		case '-h':
		case '--help':
			fwrite($STDERR, $syntax);
			// TODO: Output help.
			break;
		/*case '-i':
			array_push($args['include'], $shifted = array_shift($cliargs));
			break;
		case '-e':
			array_push($args['exclude'], $shifted = array_shift($cliargs));
			break;*/
		case '-d':
			$find->setDelim($shifted = array_shift($cliargs));
			break;
		case '-ds':
			$find->setDS($shifted = array_shift($cliargs));
			break;
		
		default:
			$directory = $cliarg;
			//$cliargs = array();
	}
	
	if (is_null($shifted)) {
		fwrite($STDERR, "Missing value after argument $cliarg\n".$syntax);
		exit(1);
	}
}

// ==============================
// Validate and clean arguments
// ==============================

if (is_null($directory)) {
	fwrite($STDERR, "directory argument is missing\n".$syntax); exit(1);
}

switch($ret = $find->run($directory, null, $STDERR)) {
	case FIND_NOT_DIRECTORY:
		fwrite($STDERR, "The <directory> does not exist or is not a directory.\n");
		break;
	case FIND_FAILED_RESOLVE:
		fwrite($STDERR, "Failed to resolve <directory> to its full path. You may not have access (read and exec) to the directory or its parent directories.\n");
		break;
	case FIND_FAILED_STAT:
		fwrite($STDERR, "Failed to retrieve info (via stat) on <directory>. You may not have access to the directory or its parent directories.\n");
		break;
}

fclose($STDERR);
exit($ret === TRUE ? 0 : 1);
?>
