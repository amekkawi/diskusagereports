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

$args = array(
	'directory' => null,
	'include' => array(),
	'exclude' => array(),
	'ds' => DIRECTORY_SEPARATOR,
	'delim' => "\x00"
);

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
		case '-i':
			array_push($args['include'], $shifted = array_shift($cliargs));
			break;
		case '-e':
			array_push($args['exclude'], $shifted = array_shift($cliargs));
			break;
		case '-d':
			$args['delim'] = $shifted = array_shift($cliargs);
			$find->setDelim($args['delim']);
			break;
		case '-ds':
			$args['ds'] = $shifted = array_shift($cliargs);
			$find->setDS($args['ds']);
			break;
		
		default:
			$args['directory'] = $cliarg;
			$cliargs = array();
	}
	
	if (is_null($shifted)) {
		fwrite($STDERR, "Missing value after argument $cliarg\n".$syntax);
		exit(1);
	}
}

// ==============================
// Validate and clean arguments
// ==============================

if (is_null($args['directory'])) {
	fwrite($STDERR, "directory argument is missing\n".$syntax); exit(1);
}

switch($ret = $find->run($args['directory'], null, $STDERR)) {
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

if (!is_dir($args['directory'])) {
	fwrite($STDERR, "The <directory> does not exist or is not a directory.\n"); exit(1);
}

if (($args['directory'] = realpath($args['directory'])) === FALSE) {
	fwrite($STDERR, "Failed to resolve <directory> to its full path. You may not have access (read and exec) to the directory or its parent directories.\n"); exit(1);
}

if (($stat = stat($args['directory'])) === FALSE) {
	fwrite($STDERR, "Failed to retrieve info (via stat) on <directory>. You may not have access to the directory or its parent directories.\n"); exit(1);
}

// Clean the right side of the directory path, if it is at least two chracters long.
if (strlen($args['directory']) >= 2) {
	$args['directory'] = rtrim($args['directory'], DIRECTORY_SEPARATOR);
}

// ==============================
// List directories and files
// ==============================

echo 'path: ' . $args['directory'] . "\n";
echo 'dirname: ' . dirname($args['directory']) . "\n";
echo 'basename: ' . basename($args['directory']) . "\n\n";

if (dirname($args['directory']) == basename($args['directory']) &&
	realpath(dirname($args['directory'])) == realpath(basename($args['directory']))) {
	
	echo "is root\n";
}

OutputDirectoryEntry(dirname($args['directory']), basename($args['directory']), $stat);

echo 'd' . $args['delim'] . date('Y-m-d', intval($stat['mtime'])) . $args['delim'] . date('H:i:s', intval($stat['mtime'])) . $args['delim'] . $stat['size'] . $args['delim'] . '0' . $args['delim'] . dirname($args['directory']) . $args['delim'] . basename($args['directory']) . "\n";

echo "\n";

//ProcessFolder($args['directory'], 1);

fclose($STDERR);

function OutputDirectoryEntry($dirname, $basename, $stat) {
	global $args;
	
	// On Windows both dirname and basename will return 'C:' for a root path.
	if (substr($dirname, -1) == ':' && $dirname == $basename) {
		$dirname = '';
	}
	
	echo 'd' . $args['delim'] . date('Y-m-d', intval($stat['mtime'])) . $args['delim'] . date('H:i:s', intval($stat['mtime'])) . $args['delim'] . $stat['size'] . $args['delim'] . '0' . $args['delim'] . $dirname . $args['delim'] . $basename. "\n";
}

function ProcessFolder($directory, $depth) {
	global $args, $STDERR;
	
	if (($dirh = opendir($directory)) === FALSE) {
		fwrite($STDERR, "Failed to open directory for listing files: ".$directory."\n");
	}
	else {
		while (($file = readdir($dirh)) !== FALSE) {
	        if ($file != '.' && $file != '..') {
		        $filepath = ConcatPath($args['ds'], $directory, $file);
		        if (($stat = stat($filepath)) !== FALSE) {
		        	if (!($islink = is_link($filepath))) {
		        		$isdir = is_dir($filepath);
			        	echo ($isdir ? 'd' : 'f') . $args['delim'] . date('Y-m-d', intval($stat['mtime'])) . $args['delim'] . date('H:i:s', intval($stat['mtime'])) . $args['delim'] . $stat['size'] . $args['delim'] . $depth . $args['delim'] . $directory . $args['delim'] . $file . "\n";
			        	if (!$islink && $isdir) {
			        		ProcessFolder($filepath, $depth + 1);
			        	}
		        	}
		        }
		        else {
		        	fwrite($STDERR, 'Failed to stat: ' . $filepath."\n");
		        }
	        }
	    }
		closedir($dirh);
	}
}

function ConcatPath($sep) {
	$str = "";
	$args = func_get_args();
	array_shift($args); // Remove first arg, which is $sep.
	
	for ($i = 0; $i < count($args); $i++) {
		$args[$i] = $args[$i].'';
		if ($i > 0 && strpos($args[$i], $sep) === 0) { $args[$i] = substr($args[$i], 1); }
		if ($i + 1 < count($args) && strrpos($args[$i], $sep) === strlen($args[$i]) - 1) { $args[$i] = substr($args[$i], 0, strlen($args[$i]) - 1); }
		if ($i > 0) $str .= $sep;
		$str .= $args[$i];
	}
	
	return $str;
}
?>
