<?php

// find "DIRECTORYNAME" -printf '%y %TY-%Tm-%Td %TT %s %d %h %f\n' > "OUTFILENAME"

if (!isset($_SERVER['argc']) || $_SERVER['argc'] < 3) {
	echo "Syntax: process.php <fileslist> <reportdir>\n"; exit;
}

define('TIMEZONE', 'America/New_York');
define('MAXDETAILDEPTH', 399);
define('MAXLINELENGTH', 1024);
define('DELIM', ' ');
define('DS', '/');

define('COL_TYPE', 0);
define('COL_DATE', 1);
define('COL_TIME', 2);
define('COL_SIZE', 3);
define('COL_DEPTH', 4);
define('COL_PARENT', 5);
define('COL_NAME', 6);

if (!(function_exists("date_default_timezone_set") ? @(date_default_timezone_set(TIMEZONE)) : @(putenv("TZ=".TIMEZONE)))) {
	echo "'timezone' config was set to an invalid identifier."; exit;
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

$filesList = $argv[1];
$reportDir = $argv[2];

if (!is_file($filesList)) {
	echo "The <fileslist> does not exist or is not a file.\n"; exit;
}
elseif (!is_dir($reportDir)) {
	echo "The <reportdir> does not exist or is not a directory.\n"; exit;
}

if (($fh = fopen($filesList, 'r')) === FALSE) {
	echo "Failed to open <fileslist> for reading.\n"; exit;
}

if (file_put_contents(ConcatPath(DS, $reportDir, 'settings'), json_encode(array(
		'root' => md5('coas'),
		'sizes' => $sizeGroups,
		'modified' => $modifiedGroups,
		'ds' => DS
	))) === FALSE) {
	
	echo 'Failed to write: ' . ConcatPath(DS, $reportDir, 'settings') . "\n";
}

$paths = array();

// Read in all lines.
while (($line = fgets($fh, MAXLINELENGTH)) !== FALSE) {
	
	// Trim line separator and split line.
	$split = explode(DELIM, rtrim($line, "\n\r"));
	
	while (count($paths) > 0 && $paths[count($paths)-1]['path'] != $split[COL_PARENT]) {
		$pop = array_pop($paths);
		//echo 'Exit Dir: ' . $pop['path'] . "\n";
		
		if (file_put_contents(ConcatPath(DS, $reportDir, md5($pop['path'])), json_encode($pop)) === FALSE) {
			echo 'Failed to write: ' . ConcatPath(DS, $reportDir, md5($pop['path'])) . "\n";
		}
	}
	
	if ($split[COL_TYPE] == 'd') {
		$newPath = array(
			'bytes' => '0',
			'totalbytes' => '0',
			'num' => '0',
			'totalnum' => '0',
			'subdirs' => array(),
			'files' => array(),
			'sizes' => array(),
			'modified' => array(),
			'types' => array()
		);
		
		if ($split[COL_DEPTH] == '0') {
			//echo 'Root Dir: ' . $split[COL_NAME] . ' (' . md5($split[COL_NAME]) . ')' . "\n";
			$newPath['path'] = $split[COL_NAME];
		}
		else {
			//echo 'New Dir:  ' . $split[COL_PARENT] . DS . $split[COL_NAME] . "\n";
			$newPath['path'] = $split[COL_PARENT] . DS . $split[COL_NAME];
		}
		
		if (count($paths) > 0) {
			array_push($paths[count($paths)-1]['subdirs'], array(
				'name' => $split[COL_NAME],
				'totalbytes' => &$newPath['totalbytes'],
				'totalnum' => &$newPath['totalnum'],
				'hash' => md5($newPath['path'])
			));
		}
		
		array_push($paths, $newPath);
	}
	else {
		//echo 'File:     ' . $split[COL_PARENT] . DS . $split[COL_NAME] . "\n";
		AddFileData($split);
		array_push($paths[count($paths)-1]['files'], array(
			'name' => $split[COL_NAME],
			'date' => $split[COL_DATE],
			'time' => $split[COL_TIME],
			'size' => $split[COL_SIZE]
		));
	}
}

while (count($paths) > 0) {
	$pop = array_pop($paths);
	//echo 'Exit Dir: ' . $pop['path'] . "\n";
	
	if (file_put_contents(ConcatPath(DS, $reportDir, md5($pop['path'])), json_encode($pop)) === FALSE) {
		echo 'Failed to write: ' . ConcatPath(DS, $reportDir, md5($pop['path'])) . "\n";
	}
}

fclose($fh);

function AddFileData($data) {
	global $paths, $sizeGroups, $modifiedGroups;
	
	for ($i = 0; $i < count($paths); $i++) {
		
		$paths[$i]['totalbytes'] = bcadd($paths[$i]['totalbytes'], $data[COL_SIZE]);
		$paths[$i]['totalnum'] = bcadd($paths[$i]['totalnum'], '1');
		
		if ($i == count($paths) - 1) {
			$paths[$i]['bytes'] = bcadd($paths[$i]['bytes'], $data[COL_SIZE]);
			$paths[$i]['num'] = bcadd($paths[$i]['num'], '1');
		}
		
		if ($i <= MAXDETAILDEPTH) {
			for ($g = 0; $g < count($sizeGroups); $g++) {
				if (bccomp($sizeGroups[$g]['size'].'', $data[COL_SIZE], 0) <= 0) {
					$paths[$i]['sizes'][$g] = array_key_exists($g, $paths[$i]['sizes'])
						? array(bcadd($paths[$i]['sizes'][$g][0], $data[COL_SIZE]), bcadd($paths[$i]['sizes'][$g][1], '1'))
						: array($data[COL_SIZE], '1');
					break;
				}
			}
		
			for ($g = 0; $g < count($modifiedGroups); $g++) {
				if (strcmp($modifiedGroups[$g]['date'], $data[COL_DATE]) >= 0) {
					//echo $modifiedGroups[$g]['date'] . ' <= ' . $data[COL_DATE] . "\n"; exit;
					$paths[$i]['modified'][$g] = array_key_exists($g, $paths[$i]['modified'])
						? array(bcadd($paths[$i]['modified'][$g][0], $data[COL_SIZE]), bcadd($paths[$i]['modified'][$g][1], '1'))
						: array($data[COL_SIZE], '1');
					break;
				}
			}
			
			$ext = explode('.', strtolower($data[COL_NAME]));
			if ($ext !== FALSE) {
				if (count($ext) > 1) $ext = $ext[count($ext) - 1];
				else $ext = '';
				
				$paths[$i]['types'][$ext] = array_key_exists($ext, $paths[$i]['types'])
						? array(bcadd($paths[$i]['types'][$ext][0], $data[COL_SIZE]), bcadd($paths[$i]['types'][$ext][1], '1'))
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