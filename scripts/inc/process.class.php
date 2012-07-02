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

define('PROCESS_VERSION', '1.0');
define('LIST_VERSION', 2);

define('PROCESS_OK', 0);
define('PROCESS_INVALID_FILELIST', 1);
define('PROCESS_INVALID_REPORTDIR', 2);
define('PROCESS_FAILED_REPORTDIR_MKDIR', 3);
define('PROCESS_FAILED_OPEN_FILELIST', 4);
define('PROCESS_INVALID_HEADER', 5);
define('PROCESS_WARN_WRITEFAIL', 6);
define('PROCESS_INVALID_CHARACTERS', 7);
define('PROCESS_UNEXPECTED_HEADER', 8);
define('PROCESS_FAILED_REPORTDIR_PARENT', 9);

define('PROCESS_VERBOSE_QUIET', 0);
define('PROCESS_VERBOSE_NORMAL', 1);
define('PROCESS_VERBOSE_HIGHER', 2);
define('PROCESS_VERBOSE_HIGHEST', 3);

/**
 * Process the output of find.php or find.sh and create static report files.
 * 
 */
class Process {
	
	// Variables with getters/setters.
	var $_name;
	var $_fileList;
	var $_reportDir;
	var $_totalsDepth;
	var $_top100Depth;
	var $_maxLineLength;
	var $_maxTreeSize;
	var $_noTree;
	var $_delim;
	var $_ds;
	var $_sizeGroups;
	var $_modifiedGroups;
	var $_warningCallback;
	var $_verboseLevel;
	var $_includeFullPath;
	var $_suffix;
	
	// Internal only
	var $_lineRegEx;
	var $_errors;
	var $_header;
	var $_listVersion;
	
	var $_dirStack;
	var $_dirLookup; //TODO: Rename to 'dirTree'
	var $_dirLookupStack;
	var $_dirLookupSize;
		
	var $_col_type;
	var $_col_date;
	var $_col_time;
	var $_col_size;
	var $_col_depth;
	var $_col_path;
	
	function Process() {
		$this->_name = NULL;
		$this->_fileList = NULL;
		$this->_reportDir = NULL;
		$this->_totalsDepth = 6;
		$this->_top100Depth = 3;
		$this->_maxLineLength = 1024;
		$this->_maxTreeSize = 800 * 1024;
		$this->_noTree = FALSE;
		$this->_delim = "\x00";
		$this->_ds = DIRECTORY_SEPARATOR;
		$this->_warningCallback = NULL;
		$this->_verboseLevel = 1;
		$this->_includeFullPath = FALSE;
		$this->_suffix = ".txt";
		$this->_listVersion = 1;
		
		$this->_col_type = 0;
		$this->_col_date = 1;
		$this->_col_time = 2;
		$this->_col_size = 3;
		$this->_col_depth = 4;
		$this->_col_path = 5;
	}
	
	function run() {
		$this->_errors = array();
		$this->_header = NULL;
		
		$this->_dirStack = array();
		$this->_dirLookup = array();
		$this->_dirLookupStack = array();
		$this->_dirLookupSize = 0;
		
		// Create the regular expression to validate lines.
		$this->_createLineRegEx();
		
		// Verify the report directory is valid.
		if (file_exists($this->_reportDir)) {
			if (!is_dir($this->_reportDir)) {
				return PROCESS_INVALID_REPORTDIR;
			}
		}
		
		// Make sure the parent of the report directory exists.
		elseif (!is_dir(dirname($this->_reportDir))) {
			return PROCESS_FAILED_REPORTDIR_PARENT;
		}
		
		// Create the report directory if it does not exist.
		elseif (!mkdir($this->_reportDir)) {
			return PROCESS_FAILED_REPORTDIR_MKDIR;
		}
		
		// Clean up the reportDir path
		$this->_reportDir = rtrim(realpath($this->_reportDir), DIRECTORY_SEPARATOR);
		
		return $this->_readLines();
	}
	
	function _readLines() {
		
		$memLimit = FALSE;
		$nextMemPercent = $this->_verboseLevel == PROCESS_VERBOSE_HIGHEST ? 1 : ($this->_verboseLevel == PROCESS_VERBOSE_QUIET ? 100 : 80);
		if ($this->_verboseLevel != PROCESS_VERBOSE_QUIET && function_exists('memory_get_usage')) {
			if (preg_match('/^([0-9]+)([GMK]?)(B?)$/', strtoupper(ini_get('memory_limit').''), $matches)) {
				$memLimit = intval($matches[1]);
				switch ($matches[2]) {
					case "G":
						$memLimit *= 1024;
					case "M":
						$memLimit *= 1024;
					case "K":
						$memLimit *= 1024;
				}
			}
		}
		
		if ($this->_verboseLevel >= PROCESS_VERBOSE_HIGHER) echo "Processing filelist...\n";
		
		// Attempt to open the file list.
		if (($fh = fopen($this->_fileList, 'r')) === FALSE) {
			return PROCESS_FAILED_OPEN_FILELIST;
		}
		
		$lineNum = 0;
		while (($line = fgets($fh, $this->_maxLineLength + 2)) !== FALSE) {
			$line = rtrim($line, "\n\r");
			
			// Ignore blank lines
			if (trim($line) != '') {
				
				// Process the header.
				if (substr($line, 0, 1) == '#') {
					if (($ret = $this->_processHeader($line)) !== TRUE) {
						fclose($fh);
						return $ret;
					}
				}
				
				elseif (substr($line, 0, 1) == '!') {
					$this->_processError($line);
				}
				
				// Validate the line, and if it is valid then process it.
				elseif (is_array(($split = $this->_validateLine($line, $lineNum)))) {
					$this->_processLine($split);
				}
				
				// Return a critical failure if an error constant was returned from verify.
				elseif ($split !== FALSE) {
					fclose($fh);
					return $split;
				}
			}
			
			if ($memLimit !== FALSE) {
				$currMem = memory_get_usage();
				$memPercent = $currMem / $memLimit * 100;
				if ($memPercent > $nextMemPercent) {
					$nextMemPercent = ceil($memPercent);
					echo "Used " . intval($memPercent) . "% of memory limit (" . ini_get('memory_limit') . ")\n";
				}
			}
			
			$lineNum++;
		}
		
		fclose($fh);
		
		// Add the root directory to the stack if one was never added.
		if (count($this->_dirStack) == 0) {
			$this->_processDirectory('', '');
		}
		
		$this->_checkDirStack();
		$this->_saveDirTree();
		$this->_saveSettings();
		
		return PROCESS_OK;
	}
	
	function _processHeader($line) {
		
		if ($this->_verboseLevel == PROCESS_VERBOSE_HIGHEST) echo "Processing header...\n";
		
		// Fail if the dirStack already contains directories.
		// TODO: TEST
		if (count($this->_dirStack) != 0) {
			return PROCESS_UNEXPECTED_HEADER;
		}
		
		// Fail if the header is too short or too long.
		if (strlen($line) < 2 || strlen($line) > $this->_maxLineLength) {
			return PROCESS_INVALID_HEADER;
		}
		
		// Version 2 and later syntax.
		if (substr($line, 1, 2) == '# ') {
			
			// Adjust the column indexes.
			$this->_col_type = 0;
			$this->_col_date = 1;
			$this->_col_time = 2;
			$this->_col_size = 3;
			$this->_col_depth = -1;
			$this->_col_path = 4;
			
			// A single splace is always the delimiter.
			$this->_delim = ' ';
			
			$splitHeader = explode(' ', substr($line, 3), 6);
			
			// Make sure the header has the minimum number of columns.
			if (count($splitHeader) < 6) {
				return PROCESS_INVALID_HEADER;
			}
			
			// Make sure the list version is supported.
			// TODO: TEST
			elseif (($this->_listVersion = intval(substr($splitHeader[0], 1))) > LIST_VERSION) {
				return PROCESS_INVALID_HEADER;
			}
			
			else {
				// Recreate the lineRegEx with the correct delim.
				$this->_createLineRegEx();
				
				// Override the directory separator.
				$this->_ds = $splitHeader[1];
				
				// Default format values. Null values are required to be set by the header.
				$format = array( 'datetime' => null );
				
				// Set format values from the header.
				$formatValues = explode(':', $splitHeader[4]);
				foreach ($formatValues as $key => $value) {
					if (!empty($value)) {
						switch ($key) {
							case 0: $format['datetime'] = $value; break;
						}
					}
				}
				
				// Make sure all the required format values are set.
				foreach ($format as $value) {
					if (empty($value)) {
						return PROCESS_INVALID_HEADER;
					}
				}
				
				// Find the position of the separator between the dirname and basename.
				$index = -1;
				while (!isset($basename) && ($index = strpos($splitHeader[5], ' ', $index + 1)) !== FALSE) {
					// Count the number of slashes before the space.
					$slashes = 0;
					for ($i = $index - 1; $i >= 0; $i--) {
						if (substr($splitHeader[5], $i, 1) == '\\') $slashes++;
						else break;
					}
					
					// If the slashes if even, then this space is not being escaped.
					if ($slashes % 2 == 0) {
						$dirname = str_replace('\\ ', ' ', str_replace('\\\\', '\\', substr($splitHeader[5], 0, $index)));
						$basename = str_replace('\\ ', ' ', str_replace('\\\\', '\\', substr($splitHeader[5], $index + 1)));
					}
				}
				
				// Fail if we could not determine the basename.
				if (empty($basename)) {
					return PROCESS_INVALID_HEADER;
				}
				
				$this->_header = array(
					'dirname' => $dirname,
					'basename' => $basename,
					'datetime' => $splitHeader[2] . " " . $splitHeader[3],
					'format' => $format
				);
			}
		}
		
		// Version 1 syntax
		// TODO: TEST with old version
		else {
			
			// The first character after the pound-sign is the delim.
			$this->_delim = substr($line, 1, 1);
			
			// Recreate the lineRegEx with the new delim.
			$this->_createLineRegEx();
				
			// Explode the remaining part of the header.
			$splitHeader = explode($this->_delim, substr($line, 2));
			
			// Only check that the header has a *minimum* number of columns,
			// to allow future versions to add more.
			if (count($splitHeader) < 3) {
				return PROCESS_INVALID_HEADER;
			}
			
			// Make sure all the strings are UTF-8 valid
			for ($i = 1; $i < count($splitHeader); $i++) {
				if (json_encode($splitHeader[$i]) == 'null' && ($splitHeader[$i] = iconv('Windows-1252', 'UTF-8', $splitHeader[$i])) === FALSE) {
					return PROCESS_INVALID_CHARACTERS;
				}
			}
			
			// Override the directory separator.
			$this->_ds = $splitHeader[0];
			
			$this->_header = array(
				'dirname' => $splitHeader[1],
				'basename' => $splitHeader[2],
				'datetime' => $splitHeader[3]
			);
		}
		
		// Make sure the directory separator is a single character.
		if (strlen($this->_ds) != 1) {
			return PROCESS_INVALID_HEADER;
		}
		
		return TRUE;
	}
	
	function _processError($line) {
		
		// Explode the error line.
		$split = explode($this->_delim, $line);
		
		// Change the '!' to an error code.
		$split[0] = 'finderror';
		
		// Make sure all the strings are UTF-8 valid
		for ($i = 1; $i < count($split); $i++) {
			if (json_encode($split[$i]) == 'null' && ($split[$i] = iconv('Windows-1252', 'UTF-8', $split[$i])) === FALSE) {
				$split[$i] = '((Error message contained invalid characters))';
			}
		}
		
		if ($this->_verboseLevel == PROCESS_VERBOSE_HIGHEST) echo "Processing error: " . implode(' ', array_slice($split, 1)) . "\n";
		
		// Push to the normal error list and let the UI handle it.
		array_push($this->_errors, $split);
	}
	
	function _validateLine($line, $lineNum) {
		if (strlen($line) > $this->_maxLineLength) {
			if ($this->_verboseLevel >= PROCESS_VERBOSE_HIGHER) echo "Line $lineNum invalid: Exceeds max line length.\n";
			array_push($this->_errors, array('invalidline', 'maxlinelength', $line));
		}

		// Validate the line up to the path column.
		elseif (!preg_match($this->_lineRegEx, $line)) {
			if ($this->_verboseLevel >= PROCESS_VERBOSE_HIGHER) echo "Line $lineNum is invalid: Does not match correct pattern.\n";
			array_push($this->_errors, array('invalidline', 'regex', $line));
		}

		// Split the line and validate its length.
		elseif (count($split = explode($this->_delim, $line, 5)) != 5) {
			if ($this->_verboseLevel >= PROCESS_VERBOSE_HIGHER) echo "Line $lineNum is invalid: Incorrect column count. $line\n";
			array_push($this->_errors, array('invalidline', 'columncount', $split));
		}
		
		// Make sure the path is at least one character long.
		elseif (strlen($split[$this->_col_path]) == 0) {
			if ($this->_verboseLevel >= PROCESS_VERBOSE_HIGHER) echo "Line $lineNum is invalid: The path must be at least one character long.\n";
			array_push($this->_errors, array('invalidline', 'column', 'path', $this->_col_path, $split));
		}

		// If a json_encode fails then the text is not UTF-8.
		// Attempt to convert it from Windows-1252.
		elseif (json_encode($split[$this->_col_path]) == 'null'
			&& ($split[$this->_col_path] = iconv('Windows-1252', 'UTF-8', $split[$this->_col_path])) === FALSE) {
			
			return PROCESS_INVALID_CHARACTERS;
		}
		
		else {
			// Only if all the checks passed.
			return $split;
		}
		
		// False if an error was pushed to the array.
		return FALSE;
	}
	
	function _processLine($split) {
		// Break up the path into dirname/basename.
		if (($dirname = dirname($split[$this->_col_path])) == '.') $dirname = '';
		$basename = basename($split[$this->_col_path]);
		
		$this->_checkDirStack($dirname);
		
		// Convert the file list's UTC date/time to the report's timezone.
		$localtime = $this->_makeLocalTime($split[$this->_col_date], $split[$this->_col_time]);
		$split[$this->_col_date] = date('Y-m-d', $localtime);
		$split[$this->_col_time] = date('H:i:s', $localtime);
		
		// Add the root directory to the stack, if the stack is empty
		// (and we're past depth zero in version 1 lists).
		if (count($this->_dirStack) == 0 && ($this->_listVersion > 1 || $split[$this->_col_depth] != '0')) {
			$this->_processDirectory('', '');
		}
		
		if ($split[$this->_col_type] == 'd') {
			$this->_processDirectory($split[$this->_col_path], $basename);
		}
		else {
			$this->_processFile($split[$this->_col_type], $basename, $split[$this->_col_size], $split[$this->_col_date], $split[$this->_col_time]);
		}
	}
	
	function _checkDirStack($dirname = NULL) {
		
		// Pop off directories till we find one whose path matches the current dirname.
		while (count($this->_dirStack) > (is_null($dirname) ? 0 : 1) && (is_null($dirname) || $this->_dirStack[count($this->_dirStack)-1]['path'] != $dirname)) {
			
			$pop = array_pop($this->_dirStack);
			$dlpop = array_pop($this->_dirLookupStack); //TODO: Rename treepop
			
			if ($this->_verboseLevel == PROCESS_VERBOSE_HIGHEST) echo 'Exit dir: ' . $pop['path'] . (count($this->_dirStack) > 1 ? ' (now ' . $this->_dirStack[count($this->_dirStack)-1]['path'] . ')' : '') . "\n";
			
			if (!$this->_noTree) {
				// Increment the directory lookup size.
				$this->_dirLookupSize += strlen(json_encode($dlpop));
				
				// Disable the tree if it's too large.
				if ($this->_dirLookupSize > $this->_maxTreeSize) {
					$this->_noTree = TRUE;
				}
			}
			
			$pop['parents'] = array();
			foreach ($this->_dirStack as $parent) {
				array_push($pop['parents'], array(
					'name' => $parent['name'],
					'hash' => md5($parent['path'])
				));
			}
			
			// Remove the path so it is not saved.
			$path = $pop['path'];
			unset($pop['path']);
			
			// Save the directory data.
			if (file_put_contents($this->_reportDir . DIRECTORY_SEPARATOR . md5($path) . $this->_suffix, json_encode($pop)) === FALSE) {
				if (!is_null(_warningCallback)) call_user_func($this->_warningCallback, PROCESS_WARN_WRITEFAIL, $this->_reportDir . DIRECTORY_SEPARATOR . md5($path), $path);
				array_push($errors, array('writefail', $path, md5($path)));
			}
		}
	}
	
	function _saveDirTree() {
		if ($this->_verboseLevel >= PROCESS_VERBOSE_HIGHER) echo "Saving dir tree...\n";
		
		// Save the directory list.
		if (!$this->_noTree && file_put_contents($this->_reportDir . DIRECTORY_SEPARATOR . 'directories' . $this->_suffix, json_encode($this->_dirLookup)) === FALSE) {
			if (!is_null(_warningCallback)) call_user_func($this->_warningCallback, PROCESS_WARN_WRITEFAIL, $this->_reportDir . DIRECTORY_SEPARATOR . 'directories');
			array_push($this->_errors, array('writefail', 'directories', 'directories'));
		}
	}
	
	function _saveSettings() {
		if ($this->_verboseLevel >= PROCESS_VERBOSE_HIGHER) echo "Saving settings...\n";
		
		$settings = array(
			'version' => '1.0',
			'name' => $this->_name,
			'created' => date('M j, Y g:i:s A T'),
			'directorytree' => !$this->_noTree,
			'root' => md5(''), // The root path is always an empty string.
			'sizes' => $this->_sizeGroups,
			'modified' => $this->_modifiedGroups,
			'ds' => $this->_ds,
			'errors' => $this->_errors
		);
		
		if ($this->_includeFullPath && isset($this->_header['dirname'])) {
			$settings['path'] = $this->_header['dirname'];
		}
		
		// Save the settings file.
		if (file_put_contents($this->_reportDir . DIRECTORY_SEPARATOR . 'settings' . $this->_suffix, json_encode($settings)) === FALSE) {
			if (!is_null(_warningCallback)) call_user_func($this->_warningCallback, PROCESS_WARN_WRITEFAIL, $this->_reportDir . DIRECTORY_SEPARATOR . 'settings');
		}
	}
	
	function _processDirectory($path, $basename) {
		
		// Set an empty basename to the one set in the header, if the header has been set.
		if ($basename == '' && isset($this->_header['basename'])) {
			$basename = $this->_header['basename'];
		}
		
		if ($this->_verboseLevel == PROCESS_VERBOSE_HIGHEST) echo "Enter dir: $path ($basename)\n";
		
		$hash = md5($path);
		
		$newDir = array(
			'name' => $basename == '' ? '.' : $basename,
			'path' => $path,
			'bytes' => 0,
			'totalbytes' => 0,
			'num' => 0,
			'totalnum' => 0,
			'totalsubdirs' => 0,
			'subdirs' => array(),
			'files' => array()
		);
		
		// Set total arrays if allowed at this depth.
		if (count($this->_dirStack) < $this->_totalsDepth) {
			$newDir['sizes'] = array();
			$newDir['modified'] = array();
			$newDir['types'] = array();
		}
		
		// Set top 100 array if allowed at this depth.
		if (count($this->_dirStack) < $this->_top100Depth) {
			$newDir['top100'] = array();
		}
		
		// Add this directory to the directory tree (if it's not being skipped).
		if (!$this->_noTree) {
			// Add the directory to the hash lookup.
			$this->_dirLookup[$hash] = array(
				'name' => &$newDir['name'], //TODO: remove ref? was here in case name was changed when header was processed.
				'totalbytes' => &$newDir['totalbytes'],
				'totalnum' => &$newDir['totalnum'],
				'subdirs' => array()
			);

			// Add this directory to its parent (if one exists).
			if (count($this->_dirLookupStack) > 0) {
				array_push($this->_dirLookupStack[count($this->_dirLookupStack)-1]['subdirs'], $hash);
			}

			// Add the directory to the lookup stack.
			$this->_dirLookupStack[] = &$this->_dirLookup[$hash];
		}
			
		// Add this directory to its parent (if one exists).
		if (count($this->_dirStack) > 0) {
			array_push($this->_dirStack[count($this->_dirStack)-1]['subdirs'], array(
				'name' => &$newDir['name'], //TODO: remove ref? see above
				'totalbytes' => &$newDir['totalbytes'],
				'totalnum' => &$newDir['totalnum'],
				'hash' => $hash
			));
		}
			
		// Add the directory to the stack.
		array_push($this->_dirStack, $newDir);
	}
	
	function _processFile($type, $basename, $size, $date, $time) {
		
		// 'ls' will output '-' instead of 'f' for files.
		if ($type == "-") $type = "f";
		
		if ($this->_verboseLevel == PROCESS_VERBOSE_HIGHEST) echo "Processing file: $type $basename\n";
		
		// Clear the size for special files types.
		if ($specialFile = $type != 'f' && $type != 'd' && $type != 'l') {
			$size = 0;
		}
		
		// Get the current directory in the stack (for code readability).
		$currDir = &$this->_dirStack[count($this->_dirStack)-1];
		
		$newFile = array(
			'name' => $basename,
			'date' => $date,
			'time' => $time,
			'size' => BigVal($size)
		);
		
		// Save the type only for non-files.
		if ($type != 'f') {
			$newFile['type'] = $type;
		}
		
		array_push($currDir['files'], $newFile);
		
		$currDir['bytes'] = BigAdd($currDir['bytes'], $size);
		$currDir['num']++;
		
		// Determine the root path for the 'top 100' paths.
		$rootPath = !isset($this->_header['basename']) || $this->_header['basename'] == '' ? '.'
			: ($this->_header['basename'] == $this->_ds ? ''
				: $this->_header['basename']);
		
		// Increment totals for directories in the stack.
		for ($i = 0; $i < count($this->_dirStack); $i++) {
			
			// Byte and file count totals.
			$this->_dirStack[$i]['totalbytes'] = BigAdd($this->_dirStack[$i]['totalbytes'], $size);
			$this->_dirStack[$i]['totalnum']++;
			
			// Increment the modified, sizes and extension totals.
			if ($i < $this->_totalsDepth) {
				for ($g = 0; $g < count($this->_sizeGroups); $g++) {
					if (BigComp($this->_sizeGroups[$g]['size'], $size) <= 0) {
						$this->_dirStack[$i]['sizes'][$g] = array_key_exists($g, $this->_dirStack[$i]['sizes'])
							? array(BigAdd($this->_dirStack[$i]['sizes'][$g][0], $size), $this->_dirStack[$i]['sizes'][$g][1] + 1)
							: array(BigVal($size), 1);
						break;
					}
				}
			
				for ($g = 0; $g < count($this->_modifiedGroups); $g++) {
					if (strcmp($this->_modifiedGroups[$g]['date'], $date) >= 0) {
						$this->_dirStack[$i]['modified'][$g] = array_key_exists($g, $this->_dirStack[$i]['modified'])
							? array(BigAdd($this->_dirStack[$i]['modified'][$g][0], $size), $this->_dirStack[$i]['modified'][$g][1] + 1)
							: array(BigVal($size), 1);
						break;
					}
				}
				
				$ext = $this->_getFileExtension($basename);
				$this->_dirStack[$i]['types'][$ext] = array_key_exists($ext, $this->_dirStack[$i]['types'])
						? array(BigAdd($this->_dirStack[$i]['types'][$ext][0], $size), $this->_dirStack[$i]['types'][$ext][1] + 1)
						: array(BigVal($size), 1);
			}
			
			// Add the file to the top 100 lists, if it is greater than a file already in it.
			if ($i < $this->_top100Depth) {
				$index = BinarySearch($this->_dirStack[$i]['top100'], $size, array('Process', '_top100Comparator'));
				if ($index < 0) $index = abs($index + 1);
				if (count($this->_dirStack[$i]['top100']) < 100 || $index < 100) {
					array_splice($this->_dirStack[$i]['top100'], $index, 0, array(array(
						'name' => $basename,
						'size' => BigVal($size),
						'hash' => md5($currDir['path']),
						'path' => $rootPath . $this->_ds . $currDir['path'],
						'date' => $date,
						'time' => $time
					)));
					
					if (count($this->_dirStack[$i]['top100']) > 100) {
						array_pop($this->_dirStack[$i]['top100']);
					}
				}
			}
		}
	}
	
	function _getFileExtension($name) {
		$name = strtolower($name);
		$index = strrpos($name, '.');

		if ($index === FALSE || $index == 0 || $index == strlen($name)-1) {
			return '';
		}
		elseif (TRUE || preg_match('/^[0-9a-z_\-]{1,10}$/', substr($name, $index+1))) {
			return substr($name, $index+1);
		}
		else {
			return '';
		}
	}
	
	function _makeLocalTime($date, $time) {
		return gmmktime(
			intval(substr($time, 0, 2)), // hour
			intval(substr($time, 3, 2)), // minute
			intval(substr($time, 6, 2)), // second
			intval(substr($date, 5, 2)), // month
			intval(substr($date, 8, 2)), // day
			intval(substr($date, 0, 4))  // year
		);
	}
	
	function _top100Comparator($listitem, $needle) {
		return BigVal($listitem['size']) - BigVal($needle);
	}
	
	function _createLineRegEx() {
		switch ($this->_listVersion) {
			case 1:
				// Create the regular expression to validate lines.
				$this->_lineRegEx = '/^' . 
					implode(preg_quote($this->_delim), array(
						'[dflcbpu]',
						'[0-9]{4}-[0-9]{2}-[0-9]{2}', // Date
						'[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?', // Time
						'[0-9]+', // Size
						'[0-9]+' // Depth
					)) . preg_quote($this->_delim) . '/';
				break;
			
			case 2:
				$this->_lineRegEx = '/^[dflcbpus\-] [0-9\-]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2} [0-9]+ ./';
				break;
		}
	}
	
	function getName() {
		return $this->_name;
	}
	function setName($name) {
		$this->_name = $name;
	}
	function getFileList() {
		return $this->_fileList;
	}
	function setFileList($fileList) {
		$this->_fileList = $fileList;
	}
	function getReportDir() {
		return $this->_reportDir;
	}
	function setReportDir($reportDir) {
		$this->_reportDir = $reportDir;
	}
	function getTotalsDepth() {
		return $this->_totalsDepth;
	}
	function setTotalsDepth($totalsDepth) {
		$this->_totalsDepth = $totalsDepth;
	}
	function getTop100Depth() {
		return $this->_top100Depth;
	}
	function setTop100Depth($top100Depth) {
		$this->_top100Depth = $top100Depth;
	}
	function getMaxLineLength() {
		return $this->_maxLineLength;
	}
	function setMaxLineLength($maxLineLength) {
		$this->_maxLineLength = $maxLineLength;
	}
	function getMaxTreeSize() {
		return $this->_maxTreeSize;
	}
	function setMaxTreeSize($maxTreeSize) {
		$this->_maxTreeSize = $maxTreeSize;
	}
	function getNoTree() {
		return $this->_noTree;
	}
	function setNoTree($noTree) {
		$this->_noTree = $noTree;
	}
	function getDelim() {
		return $this->_delim;
	}
	function setDelim($delim) {
		$this->_delim = $delim;
	}
	function getDS() {
		return $this->_ds;
	}
	function setDS($ds) {
		$this->_ds = $ds;
	}
	function getSizeGroups() {
		return $this->_sizeGroups;
	}
	function setSizeGroups($sizeGroups) {
		$this->_sizeGroups = $sizeGroups;
	}
	function getModifiedGroups() {
		return $this->_modifiedGroups;
	}
	function setModifiedGroups($modifiedGroups) {
		$this->_modifiedGroups = $modifiedGroups;
	}
	function getWarningCallback() {
		return $this->_warningCallback;
	}
	function setWarningCallback($warningCallback) {
		$this->_warningCallback = $warningCallback;
	}
	function getVerboseLevel() {
		return $this->_verboseLevel;
	}
	function setVerboseLevel($verboseLevel) {
		$this->_verboseLevel = $verboseLevel;
	}
	function getIncludeFullPath() {
		return $this->_includeFullPath;
	}
	function setIncludeFullPath($includeFullPath) {
		$this->_includeFullPath = $includeFullPath;
	}
	function getSuffix() {
		return $this->_suffix;
	}
	function setSuffix($suffix) {
		$this->_suffix = $suffix;
	}
}
?>