<?php

// export TZ=UTC; find "DIRECTORYNAME" -type d -or -type f -printf '%y %TY-%Tm-%Td %TT %s %d %h %f\n' > "OUTFILENAME"; unset TZ
// cat diskusage-gs.txt | sed -En -e 's/^d/&/p' -e 's/^f.+\.(jpg)$/&/p' | php scripts/process.php ../diskusage-data/test2
// php scripts/find.php `pwd` | sed -E -e 's/^.*\.svn.*$//' -e 's/^.*diskusage-[a-z0-9]+\.txt.*$//' -e 's/^.*\.settings.*$//' -e 's/^.*\$dev.*$//' -e 's/^.*\.DS_Store.*$//' -e 's/^.*\.tmp_.*$//' -e '/^$/d' | php scripts/process.php -n "Disk Usage Reports Code" ../diskusage-data/test2

// Determine if the system supports 64-bit integers.
define('LARGE_INT', defined('PHP_INT_MAX') && strlen(PHP_INT_MAX.'') > 14);

// Show/hide debugging output.
define('DEBUG', FALSE);

// Backwards compatibility includes.
if(!function_exists('json_encode') ) {
	require_once('inc/json_encode.php');
}

// Make sure this is being run from the command line.
if (!isset($_SERVER['argc'])) {
	echo "Must be run from the command line.\n"; exit(1);
}

// Default arguments.
$args = array(
	'name' => null,
	'filelist' => null,
	'reportdir' => null,
	'timezone' => @date_default_timezone_get(), //'America/New_York',
	'totalsdepth' => 6,
	'top100depth' => 3,
	'maxlinelength' => 1024,
	'notree' => false,
	'delim' => "\x00",
	'ds' => DIRECTORY_SEPARATOR
);

$cliargs = array_slice($_SERVER['argv'], 1);
$syntax = "Syntax: php process.php [options] <reportdir> [<filelist>]\nSee http://diskusagereport.sourceforge.net/docs/ for help.\n";

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
			$args['delim'] = $shifted = array_shift($cliargs);
			break;
		case '-t':
			$args['totalsdepth'] = $shifted = array_shift($cliargs);
			break;
		case '-nt':
			$args['notree'] = true;
			break;
		case '-ds':
			$args['ds'] = $shifted = array_shift($cliargs);
			break;
		case '-td':
			$args['top100depth'] = $shifted = array_shift($cliargs);
			break;
		case '-n':
			$args['name'] = $shifted = array_shift($cliargs);
			break;
		case '-l':
			$args['maxlinelength'] = $shifted = array_shift($cliargs);
			break;
		default:
			$args['reportdir'] = $cliarg;
			$args['filelist'] = array_shift($cliargs);
			$cliargs = array();
	}
	
	// If we shifted and found nothing, output an error.
	if (is_null($shifted)) {
		echo "Missing value after argument $cliarg\n".$syntax; exit(1);
	}
}

// Check required arguments.
if (is_null($args['reportdir'])) {
	echo "reportdir argument is missing\n".$syntax; exit(1);
}
if (!is_null($args['filelist']) && !is_file($args['filelist'])) {
	echo "The <fileslist> does not exist or is not a file.\n"; exit(1);
}

// Verify the report directory is valid.
if (!is_dir($args['reportdir'])) {
	echo "The <reportdir> does not exist or is not a directory.\n"; exit(1);
}

// Read the file list from STDIN if it was not specified.
if (is_null($args['filelist'])) {
	$args['filelist'] = 'php://stdin';
}

define('LINE_REGEX', '/^[df]'.preg_quote($args['delim']).'[0-9]{4}-[0-9]{2}-[0-9]{2}'.preg_quote($args['delim']).'[0-9]{2}:[0-9]{2}:[0-9]{2}'.preg_quote($args['delim']).'[0-9]+'.preg_quote($args['delim']).'[0-9]+'.preg_quote($args['delim']).'/');
define('COL_TYPE', 0);
define('COL_DATE', 1);
define('COL_TIME', 2);
define('COL_SIZE', 3);
define('COL_DEPTH', 4);
define('COL_PARENT', 5);
define('COL_NAME', 6);

// Set the timezone.
if (!(function_exists("date_default_timezone_set") ? @(date_default_timezone_set($args['timezone'])) : @(putenv("TZ=".$args['timezone'])))) {
	echo "'timezone' config was set to an invalid identifier."; exit(1);
}

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
	array('label' => '10 Years or More', 'date' => date($dateFormat, strtotime('-10 year'))),
	array('label' => '5 - 10 Years', 'date' => date($dateFormat, strtotime('-5 year'))),
	array('label' => '2 - 5 Years', 'date' => date($dateFormat, strtotime('-2 year'))),
	array('label' => '1 - 2 Years', 'date' => date($dateFormat, strtotime('-1 year'))),
	array('label' => '270 - 365 Days', 'date' => date($dateFormat, strtotime('-270 day'))),
	array('label' => '180 - 270 Days', 'date' => date($dateFormat, strtotime('-180 day'))),
	array('label' => '90 - 180 Days', 'date' => date($dateFormat, strtotime('-90 day'))),
	array('label' => '60 - 90 Days', 'date' => date($dateFormat, strtotime('-60 day'))),
	array('label' => '30 - 60 Days', 'date' => date($dateFormat, strtotime('-30 day'))),
	array('label' => '15 - 30 Days', 'date' => date($dateFormat, strtotime('-15 day'))),
	array('label' => '7 - 15 Days', 'date' => date($dateFormat, strtotime('-7 day'))),
	array('label' => '1 - 7 Days', 'date' => date($dateFormat, strtotime('-1 day'))),
	array('label' => 'Today', 'date' => date($dateFormat)),
	array('label' => 'Future', 'date' => '9999-99-99')
);

// Attempt to open the file list.
if (($fh = fopen($args['filelist'], 'r')) === FALSE) {
	echo "Failed to open <fileslist> for reading.\n"; exit(1);
}

// Get details about the file.
$fstat = fstat($fh);

// Initialize report variables.
$dirStack = array();
$dirLookup = array();
$dirLookupStack = array();
$errors = array();
$relativePath = '';
$root = null;
$fread = 0;
$fpercent = 0;

// Read in all lines.
while (($line = fgets($fh, $args['maxlinelength']+2)) !== FALSE) {
	$fread += strlen($line);
	$line = trim($line);
	
	if (DEBUG && $fstat['size'] > 0 && $fpercent != floor($fread / $fstat['size'] * 100)) {
		$fpercent = floor($fread / $fstat['size'] * 100);
		echo $fpercent . "% - " . number_format(memory_get_usage()) .  "\n";
	}
	
	echo strlen($line)."\n";
	
	// Skip blank lines
	if ($line == '') { }
	
	elseif (strlen($line) > $args['maxlinelength']) {
		array_push($errors, array('invalidline', 'maxlinelength', $line));
	}
	
	// Validate the line up to the parent dir column.
	elseif (!preg_match(LINE_REGEX, $line)) {
		array_push($errors, array('invalidline', 'regex', $line));
	}
	
	// Split the line and validate its length;
	elseif (count($split = explode($args['delim'], rtrim($line, "\n\r"), 8)) != 7) {
		array_push($errors, array('invalidline', 'columncount', $split));
	}
	
	// Make sure the parent and file name are at least one character.
	elseif (strlen($split[COL_PARENT]) == 0) {
		array_push($errors, array('invalidline', 'column', 'parent', COL_PARENT, $split));
	}
	elseif (strlen($split[COL_NAME]) == 0) {
		array_push($errors, array('invalidline', 'column', 'name', COL_NAME, $split));
	}
	
	else {
		// Check if we have left the current directory in the stack.
		while (count($dirStack) > 1 && $dirStack[count($dirStack)-1]['path'] != $split[COL_PARENT]) {
			$pop = array_pop($dirStack);
			array_pop($dirLookupStack);
			
			if (DEBUG) echo 'Exit Dir: ' . $pop['path'] . "\n";
			
			$pop['parents'] = array();
			foreach ($dirStack as $parent) {
				array_push($pop['parents'], array(
					'name' => $parent['name'],
					'hash' => md5($parent['path'])
				));
			}
			
			// Remove the path so it is not saved.
			$path = $pop['path'];
			unset($pop['path']);
			
			// Save the directory data.
			if (file_put_contents(ConcatPath($args['ds'], $args['reportdir'], md5($path)), json_encode($pop)) === FALSE) {
				echo 'Failed to write: ' . ConcatPath($args['ds'], $args['reportdir'], md5($path)) . "\n";
				array_push($errors, array('writefail', $path, md5($path)));
			}
		}
		
		// Convert the file list's UTC date/time to the report's timezone
		$localtime = gmmktime(
			intval(substr($split[COL_TIME], 0, 2)), // hour
			intval(substr($split[COL_TIME], 3, 2)), // minute
			intval(substr($split[COL_TIME], 6, 2)), // second
			intval(substr($split[COL_DATE], 5, 2)), // month
			intval(substr($split[COL_DATE], 8, 2)), // day
			intval(substr($split[COL_DATE], 0, 4))  // year
		);
		$split[COL_DATE] = date('Y-m-d', $localtime);
		$split[COL_TIME] = date('H:i:s', $localtime);
		
		if ($split[COL_TYPE] == 'd') {
			$newDir = array(
				'name' => $split[COL_NAME],
				'path' => ConcatPath($args['ds'], $split[COL_PARENT], $split[COL_NAME]),
				'bytes' => 0,
				'totalbytes' => 0,
				'num' => 0,
				'totalnum' => 0,
				'subdirs' => array(),
				'files' => array()
			);
			
			$hash = md5($newDir['path']);
			
			if (DEBUG) echo 'Enter Dir: ' . $newDir['path'] . "\n";
			
			// Set totals arrays if allowed at this depth.
			if (count($dirStack) < $args['totalsdepth']) {
				$newDir['sizes'] = array();
				$newDir['modified'] = array();
				$newDir['types'] = array();
			}
			
			// Set top 100 array if allowed at this depth.
			if (count($dirStack) < $args['top100depth']) {
				$newDir['top100'] = array();
			}
			
			// Make note of the root directory's path.
			if ($split[COL_DEPTH] == '0') {
				$root = $newDir['path'];
			}
			
			// Add this directory to the directory list, if is not being skipped.
			if (!$args['notree']) {
				// Add the directory to the hash lookup.
				$dirLookup[$hash] = array(
					'name' => $split[COL_NAME],
					'totalbytes' => &$newDir['totalbytes'],
					'totalnum' => &$newDir['totalnum'],
					'subdirs' => array()
				);
				
				// Add this directory to its parent (if one exists).
				if (count($dirLookupStack) > 0) {
					array_push($dirLookupStack[count($dirLookupStack)-1]['subdirs'], $hash);
				}
				
				// Add the directory to the lookup stack.
				array_push($dirLookupStack, &$dirLookup[$hash]);
			}
			
			// Add this directory to its parent (if one exists).
			if (count($dirStack) > 0) {
				array_push($dirStack[count($dirStack)-1]['subdirs'], array(
					'name' => $split[COL_NAME],
					'totalbytes' => &$newDir['totalbytes'],
					'totalnum' => &$newDir['totalnum'],
					'hash' => $hash
				));
			}
			
			// Add the directory to the stack.
			array_push($dirStack, $newDir);
			
			// Create a relative path to be used by 'top 100'.
			$relativePath = '';
			for ($i = 0; $i < count($dirStack); $i++) {
				$relativePath = ConcatPath($args['ds'], $relativePath, $dirStack[$i]['name']);
			}
			$relativePath = substr($relativePath, 1);
		}
		else {
			//if (DEBUG) echo 'File:     ' . $split[COL_PARENT] . $args['ds'] . $split[COL_NAME] . "\n";
			AddFileData($split);
			array_push($dirStack[count($dirStack)-1]['files'], array(
				'name' => $split[COL_NAME],
				'date' => $split[COL_DATE],
				'time' => $split[COL_TIME],
				'size' => BigVal($split[COL_SIZE])
			));
		}
	}
}

// Catch any remaining directories in the stack.
while (count($dirStack) > 0) {
	$pop = array_pop($dirStack);
	array_pop($dirLookupStack);
	
	if (DEBUG) echo 'Exit Dir: ' . $pop['path'] . "\n";
	
	$pop['parents'] = array();
	foreach ($dirStack as $parent) {
		array_push($pop['parents'], array(
			'name' => $parent['name'],
			'hash' => md5($parent['path'])
		));
	}
	
	// Remove the path so it is not saved.
	$path = $pop['path'];
	unset($pop['path']);
	
	// Save the directory data.
	if (file_put_contents(ConcatPath($args['ds'], $args['reportdir'], md5($path)), json_encode($pop)) === FALSE) {
		echo 'Failed to write: ' . ConcatPath($args['ds'], $args['reportdir'], md5($path)) . "\n";
		array_push($errors, array('writefail', $path, md5($path)));
	}
}

// Save the directory list.
if (!$args['notree'] && file_put_contents(ConcatPath($args['ds'], $args['reportdir'], 'directories'), json_encode($dirLookup)) === FALSE) {
	echo 'Failed to write: ' . ConcatPath($args['ds'], $args['reportdir'], 'directories') . "\n";
	array_push($errors, array('writefail', 'directories', 'directories'));
}

// Save the settings file.
if (file_put_contents(ConcatPath($args['ds'], $args['reportdir'], 'settings'), json_encode(array(
		'version' => '1.0',
		'name' => $args['name'],
		'created' => date('M j, Y g:i:s A T'),
		'directorytree' => !$args['notree'],
		'root' => md5($root),
		'sizes' => $sizeGroups,
		'modified' => $modifiedGroups,
		'ds' => $args['ds'],
		'errors' => $errors
	))) === FALSE) {
	
	echo 'Failed to write: ' . ConcatPath($args['ds'], $args['reportdir'], 'settings') . "\n";
}

fclose($fh);

exit(count($errors));

function GetExtension($name) {
	$name = strtolower($name);
	$index = strrpos($name, '.');
	
	if ($index === FALSE || $index == 0 || $index == strlen($name)-1) {
		return '';
	}
	elseif (true || preg_match('/^[0-9a-z_\-]{1,10}$/', substr($name, $index+1))) {
		return substr($name, $index+1);
	}
	else {
		return '';
	}
}

function AddFileData($data) {
	global $args, $dirStack, $sizeGroups, $modifiedGroups, $relativePath;
	
	for ($i = 0; $i < count($dirStack); $i++) {
		
		$dirStack[$i]['totalbytes'] = BigAdd($dirStack[$i]['totalbytes'], $data[COL_SIZE]);
		$dirStack[$i]['totalnum']++;
		
		if ($i == count($dirStack) - 1) {
			$dirStack[$i]['bytes'] = BigAdd($dirStack[$i]['bytes'], $data[COL_SIZE]);
			$dirStack[$i]['num']++;
		}
		
		if ($i < $args['totalsdepth']) {
			for ($g = 0; $g < count($sizeGroups); $g++) {
				if (BigComp($sizeGroups[$g]['size'], $data[COL_SIZE]) <= 0) {
					$dirStack[$i]['sizes'][$g] = array_key_exists($g, $dirStack[$i]['sizes'])
						? array(BigAdd($dirStack[$i]['sizes'][$g][0], $data[COL_SIZE]), $dirStack[$i]['sizes'][$g][1] + 1)
						: array(BigVal($data[COL_SIZE]), 1);
					break;
				}
			}
		
			for ($g = 0; $g < count($modifiedGroups); $g++) {
				if (strcmp($modifiedGroups[$g]['date'], $data[COL_DATE]) >= 0) {
					$dirStack[$i]['modified'][$g] = array_key_exists($g, $dirStack[$i]['modified'])
						? array(BigAdd($dirStack[$i]['modified'][$g][0], $data[COL_SIZE]), $dirStack[$i]['modified'][$g][1] + 1)
						: array(BigVal($data[COL_SIZE]), 1);
					break;
				}
			}
			
			$ext = GetExtension($data[COL_NAME]);
			$dirStack[$i]['types'][$ext] = array_key_exists($ext, $dirStack[$i]['types'])
					? array(BigAdd($dirStack[$i]['types'][$ext][0], $data[COL_SIZE]), $dirStack[$i]['types'][$ext][1] + 1)
					: array(BigVal($data[COL_SIZE]), 1);
		}
		
		if ($i < $args['top100depth']) {
			$index = BinarySearch($dirStack[$i]['top100'], $data[COL_SIZE], 'Top100Comparator');
			if ($index < 0) $index = abs($index + 1);
			if (count($dirStack[$i]['top100']) < 100 || $index < 100) {
				array_splice($dirStack[$i]['top100'], $index, 0, array(array(
					'name' => $data[COL_NAME],
					'size' => BigVal($data[COL_SIZE]),
					'hash' => md5($dirStack[count($dirStack)-1]['path']),
					'path' => $relativePath,
					'date' => $data[COL_DATE],
					'time' => $data[COL_TIME]
				)));
				
				if (count($dirStack[$i]['top100']) > 100) {
					array_pop($dirStack[$i]['top100']);
				}
			}
		}
	}
}

function BigVal($num) {
	return LARGE_INT ? intval($num) : floatval($num);
}

function BigAdd($a, $b) {
	return BigVal($a) + BigVal($b);
	
	// TODO: Remove
	if (LARGE_INT) {
		return intval($a) + intval($b);
	}
	elseif (function_exists('bcadd')) {
		return bcadd($a.'', $b.'');
	}
	elseif (function_exists('gmp_add')) {
		return gmp_strval(gmp_add($a.'', $b.''));
	}
	else {
		trigger_error('Neither bcadd or gmp_add are avalable and the system does not seem to be 64-bit.', E_USER_ERRO);
	}
}

function BigComp($a, $b) {
	return BigVal($a) - BigVal($b);
	
	// TODO: Remove
	if (LARGE_INT) {
		return intval($a) - intval($b);
	}
	elseif (function_exists('bccomp')) {
		return bccomp($a.'', $b.'');
	}
	elseif (function_exists('gmp_cmp')) {
		return gmp_cmp($a.'', $b.'');
	}
	else {
		trigger_error('Neither bcadd or gmp_add are avalable and the system does not seem to be 64-bit.', E_USER_ERRO);
	}
}

function Top100Comparator($listitem, $needle) {
	return BigVal($listitem['size']) - BigVal($needle);
}

function BinarySearch($list, $needle, $comparator) {
	$low = 0;
	$high = count($list) - 1;
	$comp = -1;
	$mid = 0;
	
	while ($low <= $high) {
		$mid = floor(($low + $high) / 2);
		
		$comp = $comparator($list[$mid], $needle);
		
		if ($comp < 0) {
			$high = $mid - 1;
		}
		else if ($comp > 0) {
			$low = $mid + 1;
		}
		else {
			return $mid;
		}
	}
	
	if ($comp < 0) return -1 - $mid;
	if ($comp > 0) return -2 - $mid;
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
