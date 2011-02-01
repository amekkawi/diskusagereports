<?php

$STDERR = fopen('php://stderr', 'w+');

if (!isset($_SERVER['argc'])) {
	fwrite($STDERR, "Must be run from the command line.\n"); exit(1);
}

$args = array(
	'directory' => null,
	'include' => array(),
	'exclude' => array(),
	'ds' => DIRECTORY_SEPARATOR,
	'delim' => "\x00"
);

$cliargs = array_slice($_SERVER['argv'], 1);
$syntax = "Syntax: php find.php [options] <directory>\nSee http://diskusagereport.sourceforge.net/docs/index.html for help.\n";

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
			break;
		case '-ds':
			$args['ds'] = $shifted = array_shift($cliargs);
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

if (is_null($args['directory'])) {
	fwrite($STDERR, "directory argument is missing\n".$syntax); exit(1);
}

if (!is_dir($args['directory'])) {
	fwrite($STDERR, "The <directory> does not exist or is not a directory.\n"); exit(1);
}

if (!(function_exists("date_default_timezone_set") ? @(date_default_timezone_set('UTC')) : @(putenv("TZ=UTC")))) {
	echo fwrite($STDERR, "Timezone could not be set to UTC."); exit(1);
}

if (($stat = stat($args['directory'])) === FALSE) {
	fwrite($STDERR, "Could not determine stats of root directory.\n"); exit(1);
}
else {
	echo 'd' . $args['delim'] . date('Y-m-d', intval($stat['mtime'])) . $args['delim'] . date('H:i:s', intval($stat['mtime'])) . $args['delim'] . $stat['size'] . $args['delim'] . '0' . $args['delim'] . dirname($args['directory']) . $args['delim'] . basename($args['directory']) . "\n";
	ProcessFolder($args['directory'], 1);	
}

fclose($STDERR);

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
		        	$isdir = is_dir($filepath);
		        	echo ($isdir ? 'd' : 'f') . $args['delim'] . date('Y-m-d', intval($stat['mtime'])) . $args['delim'] . date('H:i:s', intval($stat['mtime'])) . $args['delim'] . $stat['size'] . $args['delim'] . $depth . $args['delim'] . $directory . $args['delim'] . $file . "\n";
		        	if ($isdir) {
		        		ProcessFolder($filepath, $depth + 1);
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
