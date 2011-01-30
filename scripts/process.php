<?php

// export TZ=UTC; find "DIRECTORYNAME" -printf '%y %TY-%Tm-%Td %TT %s %d %h %f\n' > "OUTFILENAME"; unset TZ

if (!isset($_SERVER['argc'])) {
	echo "Must be run from the command line.\n"; exit(1);
}

$args = array(
	'name' => null,
	'filelist' => null,
	'reportdir' => null,
	'timezone' => 'America/New_York',
	'totalsdepth' => 6,
	'top100depth' => 3,
	'maxlinelength' => 1024,
	'notree' => false,
	'delim' => "\x00",
	'ds' => '/'
);

// syntax: php process.php [-tz '<timezone>'] [-d '<delim>'] [-t <totalsdepth>] [-nt (no tree)] [-ds '<directoryseparator>'] [-td <top100depth>] [-n <reportname>] <reportdir> [<filelist>]

$cliargs = array_slice($_SERVER['argv'], 1);
$syntax = "Syntax: php process.php [options] <reportdir> [<filelist>]\nUse -? for help.\n";

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
	
	if (is_null($shifted)) {
		echo "Missing value after argument $cliarg\n".$syntax; exit(1);
	}
}

if (is_null($args['reportdir'])) {
	echo "reportdir argument is missing\n".$syntax; exit(1);
}

if (!is_null($args['filelist']) && !is_file($args['filelist'])) {
	echo "The <fileslist> does not exist or is not a file.\n"; exit(1);
}

if (!is_dir($args['reportdir'])) {
	echo "The <reportdir> does not exist or is not a directory.\n"; exit(1);
}

if (is_null($args['filelist'])) {
	$args['filelist'] = 'php://stdin';
}

define('COL_TYPE', 0);
define('COL_DATE', 1);
define('COL_TIME', 2);
define('COL_SIZE', 3);
define('COL_DEPTH', 4);
define('COL_PARENT', 5);
define('COL_NAME', 6);

if (!(function_exists("date_default_timezone_set") ? @(date_default_timezone_set($args['timezone'])) : @(putenv("TZ=".$args['timezone'])))) {
	echo "'timezone' config was set to an invalid identifier."; exit(1);
}

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

if (($fh = fopen($args['filelist'], 'r')) === FALSE) {
	echo "Failed to open <fileslist> for reading.\n"; exit(1);
}

$dirStack = array();
$dirLookup = array();
$errors = array();

// Read in all lines.
while (($line = fgets($fh, $args['maxlinelength'])) !== FALSE) {
	
	// Trim line separator and split line.
	$split = explode($args['delim'], rtrim($line, "\n\r"), 7);
	
	if (count($split) != 7) {
		echo "Invalid Line (".count($split).")\n";
		array_push($errors, "Invalid column count (".count($split)."):" . $split);
	}
	elseif (!preg_match('/^[a-z]$/i', $split[COL_TYPE])) {
		array_push($errors, array('invalidline', COL_TYPE, $split));
	}
	elseif (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $split[COL_DATE])) {
		array_push($errors, array('invalidline', COL_DATE, $split));
	}
	elseif (!preg_match('/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/', $split[COL_TIME])) {
		array_push($errors, array('invalidline', COL_TIME, $split));
	}
	elseif (!preg_match('/^[0-9]+$/', $split[COL_SIZE])) {
		array_push($errors, array('invalidline', COL_SIZE, $split));
	}
	elseif (!preg_match('/^[0-9]+$/', $split[COL_DEPTH])) {
		array_push($errors, array('invalidline', COL_DEPTH, $split));
	}
	elseif (strlen($split[COL_PARENT]) == 0) {
		array_push($errors, array('invalidline', COL_PARENT, $split));
	}
	elseif (strlen($split[COL_NAME]) == 0) {
		array_push($errors, array('invalidline', COL_NAME, $split));
	}
	else {
		while (count($dirStack) > 0 && $dirStack[count($dirStack)-1]['path'] != $split[COL_PARENT]) {
			$pop = array_pop($dirStack);
			//echo 'Exit Dir: ' . $pop['path'] . "\n";
			
			$pop['parents'] = array();
			foreach ($dirStack as $parent) {
				array_push($pop['parents'], array(
					'name' => $parent['name'],
					'hash' => md5($parent['path'])
				));
			}
			
			if (file_put_contents(ConcatPath($args['ds'], $args['reportdir'], md5($pop['path'])), json_encode($pop)) === FALSE) {
				echo 'Failed to write: ' . ConcatPath($args['ds'], $args['reportdir'], md5($pop['path'])) . "\n";
				array_push($errors, array('writefail', $pop['path'], md5($pop['path'])));
			}
		}
		
		if ($split[COL_TYPE] == 'd') {
			$newDir = array(
				'name' => $split[COL_NAME],
				'bytes' => '0',
				'totalbytes' => '0',
				'num' => '0',
				'totalnum' => '0',
				'subdirs' => array(),
				'files' => array()
			);
			
			if (count($dirStack) < $args['totalsdepth']) {
				$newDir['sizes'] = array();
				$newDir['modified'] = array();
				$newDir['types'] = array();
				$newDir['top100'] = array();
			}
			
			if ($split[COL_DEPTH] == '0') {
				//echo 'Root Dir: ' . $split[COL_NAME] . ' (' . md5($split[COL_NAME]) . ')' . "\n";
				$newDir['path'] = $split[COL_NAME];
			}
			else {
				//echo 'New Dir:  ' . $split[COL_PARENT] . $args['ds'] . $split[COL_NAME] . "\n";
				$newDir['path'] = $split[COL_PARENT] . $args['ds'] . $split[COL_NAME];
			}
			
			if (!$args['notree']) {
				// Add the directory to the hash lookup.
				$dirLookup[md5($newDir['path'])] = array(
					'name' => $split[COL_NAME],
					'totalbytes' => &$newDir['totalbytes'],
					'totalnum' => &$newDir['totalnum'],
					'subdirs' => &$newDir['subdirs']
				);
			}
			
			if (count($dirStack) > 0) {
				array_push($dirStack[count($dirStack)-1]['subdirs'], array(
					'name' => $split[COL_NAME],
					'totalbytes' => &$newDir['totalbytes'],
					'totalnum' => &$newDir['totalnum'],
					'hash' => md5($newDir['path'])
				));
			}
			array_push($dirStack, $newDir);
		}
		else {
			//echo 'File:     ' . $split[COL_PARENT] . $args['ds'] . $split[COL_NAME] . "\n";
			AddFileData($split);
			array_push($dirStack[count($dirStack)-1]['files'], array(
				'name' => $split[COL_NAME],
				'date' => $split[COL_DATE],
				'time' => $split[COL_TIME],
				'size' => $split[COL_SIZE]
			));
		}
	}
}

while (count($dirStack) > 0) {
	$pop = array_pop($dirStack);
	//echo 'Exit Dir: ' . $pop['path'] . "\n";
	
	$pop['parents'] = array();
	foreach ($dirStack as $parent) {
		array_push($pop['parents'], array(
			'name' => $parent['name'],
			'hash' => md5($parent['path'])
		));
	}
	
	if (file_put_contents(ConcatPath($args['ds'], $args['reportdir'], md5($pop['path'])), json_encode($pop)) === FALSE) {
		echo 'Failed to write: ' . ConcatPath($args['ds'], $args['reportdir'], md5($pop['path'])) . "\n";
		array_push($errors, array('writefail', $pop['path'], md5($pop['path'])));
	}
}

if (!$args['notree'] && file_put_contents(ConcatPath($args['ds'], $args['reportdir'], 'directories'), json_encode($dirLookup)) === FALSE) {
	echo 'Failed to write: ' . ConcatPath($args['ds'], $args['reportdir'], 'directories') . "\n";
	array_push($errors, array('writefail', 'directories', 'directories'));
}

if (file_put_contents(ConcatPath($args['ds'], $args['reportdir'], 'settings'), json_encode(array(
		'version' => '1.0',
		'name' => $args['name'],
		'created' => date('M j, Y g:i:s T'),
		'directorytree' => !$args['notree'],
		'root' => md5('coas'),
		'sizes' => $sizeGroups,
		'modified' => $modifiedGroups,
		'ds' => $args['ds'],
		'errors' => $errors
	))) === FALSE) {
	
	echo 'Failed to write: ' . ConcatPath($args['ds'], $args['reportdir'], 'settings') . "\n";
}

fclose($fh);

function AddFileData($data) {
	global $args, $dirStack, $sizeGroups, $modifiedGroups;
	
	for ($i = 0; $i < count($dirStack); $i++) {
		
		$dirStack[$i]['totalbytes'] = bcadd($dirStack[$i]['totalbytes'], $data[COL_SIZE]);
		$dirStack[$i]['totalnum'] = bcadd($dirStack[$i]['totalnum'], '1');
		
		if ($i == count($dirStack) - 1) {
			$dirStack[$i]['bytes'] = bcadd($dirStack[$i]['bytes'], $data[COL_SIZE]);
			$dirStack[$i]['num'] = bcadd($dirStack[$i]['num'], '1');
		}
		
		if ($i < $args['totalsdepth']) {
			for ($g = 0; $g < count($sizeGroups); $g++) {
				if (bccomp($sizeGroups[$g]['size'].'', $data[COL_SIZE], 0) <= 0) {
					$dirStack[$i]['sizes'][$g] = array_key_exists($g, $dirStack[$i]['sizes'])
						? array(bcadd($dirStack[$i]['sizes'][$g][0], $data[COL_SIZE]), bcadd($dirStack[$i]['sizes'][$g][1], '1'))
						: array($data[COL_SIZE], '1');
					break;
				}
			}
		
			for ($g = 0; $g < count($modifiedGroups); $g++) {
				if (strcmp($modifiedGroups[$g]['date'], $data[COL_DATE]) >= 0) {
					$dirStack[$i]['modified'][$g] = array_key_exists($g, $dirStack[$i]['modified'])
						? array(bcadd($dirStack[$i]['modified'][$g][0], $data[COL_SIZE]), bcadd($dirStack[$i]['modified'][$g][1], '1'))
						: array($data[COL_SIZE], '1');
					break;
				}
			}
			
			$ext = explode('.', strtolower($data[COL_NAME]));
			if ($ext !== FALSE) {
				if (count($ext) > 1) $ext = $ext[count($ext) - 1];
				else $ext = '';
				
				$dirStack[$i]['types'][$ext] = array_key_exists($ext, $dirStack[$i]['types'])
						? array(bcadd($dirStack[$i]['types'][$ext][0], $data[COL_SIZE]), bcadd($dirStack[$i]['types'][$ext][1], '1'))
						: array($data[COL_SIZE], '1');
			}
		}
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