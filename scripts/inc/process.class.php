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

define('PROCESS_PROFILING', false);

define('PROCESS_VERSION', '1.0');
define('LIST_VERSION', 2);

define('PROCESS_OK', 0);

// Critical failure codes, returned by run().
define('PROCESS_FAIL_INVALID_FILELIST', 1);
define('PROCESS_FAIL_INVALID_REPORTDIR', 2);
define('PROCESS_FAIL_REPORTDIR_MKDIR', 3);
define('PROCESS_FAIL_OPEN_FILELIST', 4);
define('PROCESS_FAIL_INVALID_HEADER', 5);
define('PROCESS_FAIL_INVALID_CHARACTERS', 6);
define('PROCESS_FAIL_UNEXPECTED_HEADER', 7);
define('PROCESS_FAIL_REPORTDIR_PARENT', 8);
define('PROCESS_FAIL_UNSUPPORTED_LIST_VERSION', 9);
define('PROCESS_FAIL_SETTINGS_WRITEFAIL', 10);

// Informational Codes.
define('PROCESS_INFO_PROCESSING_FILELIST', 0);
define('PROCESS_INFO_PROCESSING_HEADER', 1);
define('PROCESS_INFO_PROCESSING_ERROR', 2);
define('PROCESS_INFO_PROCESSING_FILE', 3);
define('PROCESS_INFO_SAVETREE', 4);
define('PROCESS_INFO_SAVESETTINGS', 5);
define('PROCESS_INFO_ENTERDIR', 6);
define('PROCESS_INFO_EXITDIR', 7);
define('PROCESS_INFO_STATUS', 8);
define('PROCESS_INFO_MEMORY', 9);
define('PROCESS_INFO_COMPLETE', 10);
define('PROCESS_INFO_TREEDISABLED', 11);

// Warning Codes.
define('PROCESS_WARN_TOOLONG', 50);
define('PROCESS_WARN_BADMATCH', 51);
define('PROCESS_WARN_COLCOUNT', 52);
define('PROCESS_WARN_EMPTYPATH', 53);
define('PROCESS_WARN_WRITEFAIL', 54);

// Verbosity Levels.
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
	var $_eventCallback;
	var $_verboseLevel;
	var $_includeFullPath;
	var $_suffix;
	var $_minStatusSeconds;
	
	// Internal only
	var $_lineRegEx;
	var $_errors;
	var $_header;
	var $_listVersion;
	var $_failDetails;
	var $_bytesRead;
	var $_bytesWritten;
	var $_filesWritten;
	
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
		$this->_eventCallback = NULL;
		$this->_verboseLevel = PROCESS_VERBOSE_NORMAL;
		$this->_includeFullPath = FALSE;
		$this->_suffix = ".txt";
		$this->_listVersion = 1;
		$this->_failDetails = array();
		$this->_bytesRead = 0;
		$this->_bytesWritten = 0;
		$this->_filesWritten = 0;
		$this->_bytesReadMax = NULL;
		$this->_minStatusSeconds = 15;
		
		// Default to version 1 columns.
		$this->_colCount = 6;
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
				return PROCESS_FAIL_INVALID_REPORTDIR;
			}
		}
		
		// Make sure the parent of the report directory exists.
		elseif (!is_dir(dirname($this->_reportDir))) {
			return PROCESS_FAIL_REPORTDIR_PARENT;
		}
		
		// Create the report directory if it does not exist.
		elseif (!mkdir($this->_reportDir)) {
			return PROCESS_FAIL_REPORTDIR_MKDIR;
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
		
		// Get the current time.
		$time = time();
		
		if ($this->_verboseLevel >= PROCESS_VERBOSE_HIGHER)
			$this->_raiseEvent(PROCESS_INFO_PROCESSING_FILELIST);
		
		// Attempt to open the file list.
		if (($fh = fopen($this->_fileList, 'r')) === FALSE) {
			return PROCESS_FAIL_OPEN_FILELIST;
		}
		
		$this->_bytesReadMax = NULL;
		if (is_array($stat = @fstat($fh)) && $stat['mode'] & 0100000) {
			$this->_bytesReadMax = $stat['size'];
		}
		
		$lineNum = 1;
		while (($line = fgets($fh, $this->_maxLineLength + 2)) !== FALSE) {
			$this->_bytesRead += strlen($line);
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
			
			if (PROCESS_PROFILING) $this->_startProfile('_readLines status');
			if ($this->_verboseLevel >= PROCESS_VERBOSE_NORMAL && $this->_minStatusSeconds > 0 && $lineNum % 100 == 0 && time() - $time >= $this->_minStatusSeconds) {
				if ($this->_bytesReadMax < $this->_bytesRead) $this->_bytesReadMax = null;
				
				$this->_raiseEvent(PROCESS_INFO_STATUS, $this->_bytesRead, $this->_bytesReadMax, $lineNum, $this->_bytesWritten, $this->_filesWritten);
				$time = time();
			}
			if (PROCESS_PROFILING) $this->_endProfile('_readLines status');
			
			if (PROCESS_PROFILING) $this->_startProfile('_readLines memcheck');
			if ($memLimit !== FALSE) {
				$currMem = memory_get_usage();
				$memPercent = $currMem / $memLimit * 100;
				if ($memPercent > $nextMemPercent) {
					$nextMemPercent = ceil($memPercent);
					$this->_raiseEvent(PROCESS_INFO_MEMORY, intval($memPercent), ini_get('memory_limit'));
				}
			}
			if (PROCESS_PROFILING) $this->_endProfile('_readLines memcheck');
			
			$lineNum++;
		}
		
		fclose($fh);
		
		// Add the root directory to the stack if one was never added.
		if (count($this->_dirStack) == 0) {
			$this->_processDirectory('', '');
		}
		
		$this->_checkDirStack();
		$this->_saveDirTree();
		
		if (!$this->_saveSettings()) {
			return PROCESS_FAIL_SETTINGS_WRITEFAIL;
		}
		
		if ($this->_verboseLevel >= PROCESS_VERBOSE_NORMAL) {
			if ($this->_bytesReadMax < $this->_bytesRead) $this->_bytesReadMax = null;
			$this->_raiseEvent(PROCESS_INFO_COMPLETE, $this->_bytesRead, $this->_bytesReadMax, $lineNum, $this->_bytesWritten, $this->_filesWritten);
		}
		
		return PROCESS_OK;
	}
	
	function _processHeader($line) {
		
		if (PROCESS_PROFILING) $this->_startProfile('_processHeader');
		
		if ($this->_verboseLevel == PROCESS_VERBOSE_HIGHEST)
			$this->_raiseEvent(PROCESS_INFO_PROCESSING_HEADER);
		
		// Fail if the dirStack already contains directories or a header has already been processed.
		if (count($this->_dirStack) != 0 || is_array($this->_header)) {
			$this->_failDetails = array('line' => $line);
			return PROCESS_FAIL_UNEXPECTED_HEADER;
		}
		
		// Fail if the header is too short or too long.
		if (strlen($line) < 2 || strlen($line) > $this->_maxLineLength) {
			$this->_failDetails = array('line' => $line);
			return PROCESS_FAIL_INVALID_HEADER;
		}
		
		// Version 2 and later syntax.
		if (substr($line, 1, 2) == '# ') {
			
			// Adjust the column indexes.
			$this->_colCount = 5;
			$this->_col_type = 0;
			$this->_col_date = 1;
			$this->_col_time = 2;
			$this->_col_size = 3;
			$this->_col_depth = null;
			$this->_col_path = 4;
			
			$splitHeader = explode(' ', substr($line, 3), 6);
			
			// Make sure the header has the minimum number of columns.
			if (count($splitHeader) < 6) {
				$this->_failDetails = array('line' => $line);
				return PROCESS_FAIL_INVALID_HEADER;
			}
			
			// Make sure the list version is supported.
			elseif (($this->_listVersion = intval(substr($splitHeader[0], 1))) > LIST_VERSION) {
				$this->_failDetails = array('line' => $line);
				return PROCESS_FAIL_UNSUPPORTED_LIST_VERSION;
			}
			
			// Make sure the field and directory separators are valid.
			elseif (!preg_match('/^[0-9]{1,3}$/', $splitHeader[1]) || intval($splitHeader[1]) >= 256 ||
					!preg_match('/^[0-9]{1,3}$/', $splitHeader[2]) || intval($splitHeader[2]) >= 256) {
				$this->_failDetails = array('line' => $line);
				return PROCESS_FAIL_INVALID_HEADER;
			}
			
			else {
				// Override the field separator.
				$this->_delim = chr(intval($splitHeader[1]));
				
				// Recreate the lineRegEx with the correct delim.
				$this->_createLineRegEx();
				
				// Override the directory separator.
				$this->_ds = chr(intval($splitHeader[2]));
				
				// Default header settings.
				// Null values are required to be set by the file list's header.
				$this->_header = array(
					'dirname' => '',
					'basename' => null,
					'datetime' => substr($splitHeader[3] . " " . $splitHeader[4], 0, 19),
					'datetimeformat' => 'timestamp',
					'escaped' => false
				);
				
				// Settings from file list's header.
				$settings = self::ExplodeEscaped(" ", $splitHeader[5]);
				
				// Process the settings.
				$invalidSetting = false;
				foreach ($settings as $split) {
					if (strlen(trim($split))) {
						$split = explode(":", $split, 2);
						
						// Validate the setting.
						switch ($split[0]) {
							case "basename":
								if (empty($split[1]))
									$invalidSetting = true;
								break;
							case "dirname":
								if (count($split) == 1)
									$invalidSetting = true;
								break;
							case "datetimeformat":
								if ($split[1] !== 'timestamp')
									$invalidSetting = true;
								break;
							case "escaped":
								$split[1] = true;
								break;
							default:
								$invalidSetting = true;
						}
						
						// Stop the foreach if an invalid setting was found.
						if ($invalidSetting)
							break;
						
						$this->_header[$split[0]] = $split[1];
					}
				}
				
				// Make sure no settings are null.
				foreach ($this->_header as $value) {
					if (is_null($value)) {
						$invalidSetting = true;
						break;
					}
				}
				
				if ($invalidSetting) {
					$this->_failDetails = array('line' => $line);
					return PROCESS_FAIL_INVALID_HEADER;
				}
			}
		}
		
		// Version 1 syntax
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
				$this->_failDetails = array('line' => $line);
				return PROCESS_FAIL_INVALID_HEADER;
			}
			
			// Make sure all the strings are UTF-8 valid
			for ($i = 1; $i < count($splitHeader); $i++) {
				if (json_encode($splitHeader[$i]) == 'null' && ($splitHeader[$i] = iconv('Windows-1252', 'UTF-8', $splitHeader[$i])) === FALSE) {
					return PROCESS_FAIL_INVALID_CHARACTERS;
				}
			}
			
			// Override the directory separator.
			$this->_ds = $splitHeader[0];
		
			// Make sure the directory separator is a single character.
			if (strlen($this->_ds) != 1) {
				$this->_failDetails = array('line' => $line);
				return PROCESS_FAIL_INVALID_HEADER;
			}
			
			$this->_header = array(
				'dirname' => $splitHeader[1],
				'basename' => $splitHeader[2],
				'datetime' => substr($splitHeader[3], 0, 19)
			);
		}
		
		// Validate the header's timestamp.
		if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $this->_header['datetime'])) {
			$this->_failDetails = array('line' => $line);
			return PROCESS_FAIL_INVALID_HEADER;
		}
		
		if (PROCESS_PROFILING) $this->_endProfile('_processHeader');
		
		return TRUE;
	}
	
	function _processError($line) {
		
		if (PROCESS_PROFILING) $this->_startProfile('_processError');
		
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
		
		// TODO: Check what the imploding is doing.
		if ($this->_verboseLevel == PROCESS_VERBOSE_HIGHEST)
			$this->_raiseEvent(PROCESS_INFO_PROCESSING_ERROR, implode(' ', array_slice($split, 1)));
		
		// Push to the normal error list and let the UI handle it.
		array_push($this->_errors, $split);
		
		if (PROCESS_PROFILING) $this->_endProfile('_processError');
	}
	
	function _validateLine($line, $lineNum) {
		
		if (PROCESS_PROFILING) $this->_startProfile('_validateLine');
		$ret = FALSE;
		
		if (strlen($line) > $this->_maxLineLength) {
			if ($this->_verboseLevel > PROCESS_VERBOSE_QUIET)
				$this->_raiseEvent(PROCESS_WARN_TOOLONG, $lineNum, $line);
			
			array_push($this->_errors, array('invalidline', 'maxlinelength', $line));
		}

		// Validate the line up to the path column.
		elseif (!preg_match($this->_lineRegEx, $line)) {
			if ($this->_verboseLevel > PROCESS_VERBOSE_QUIET)
				$this->_raiseEvent(PROCESS_WARN_BADMATCH, $lineNum, $line);
			
			array_push($this->_errors, array('invalidline', 'regex', $line));
		}

		// Split the line and validate its length.
		elseif (count($split = explode($this->_delim, $line, $this->_colCount)) != $this->_colCount) {
			if ($this->_verboseLevel > PROCESS_VERBOSE_QUIET)
				$this->_raiseEvent(PROCESS_WARN_COLCOUNT, $lineNum, $line);
			
			array_push($this->_errors, array('invalidline', 'columncount', $split));
		}
		
		// Make sure the path is at least one character long.
		elseif (strlen($split[$this->_col_path]) == 0) {
			if ($this->_verboseLevel > PROCESS_VERBOSE_QUIET)
				$this->_raiseEvent(PROCESS_WARN_EMPTYPATH, $lineNum, $line);
			
			array_push($this->_errors, array('invalidline', 'column', 'path', $this->_col_path, $split));
		}

		// If a json_encode fails then the text is not UTF-8.
		// Attempt to convert it from Windows-1252.
		elseif (json_encode($split[$this->_col_path]) == 'null'
			&& ($split[$this->_col_path] = iconv('Windows-1252', 'UTF-8', $split[$this->_col_path])) === FALSE) {
			
			$ret = PROCESS_FAIL_INVALID_CHARACTERS;
		}
		
		else {
			if ($split[$this->_col_type] == 'l') {
				$split[$this->_col_path] = explode(' -> ', $split[$this->_col_path], 2)[0];
			}

			// Only if all the checks passed.
			$ret = $split;
		}
		
		if (PROCESS_PROFILING) $this->_endProfile('_validateLine');
		
		// False if an error was pushed to the array.
		return $ret;
	}
	
	function _processLine($split) {
		if (PROCESS_PROFILING) $this->_startProfile('_processLine');
		
		if (PROCESS_PROFILING) $this->_startProfile('_processLine breakup');
		
		// Break up the path into dirname/basename.
		if (($dirname = dirname($split[$this->_col_path])) == '.') $dirname = '';
		$basename = basename($split[$this->_col_path]);
		
		if (PROCESS_PROFILING) $this->_endProfile('_processLine breakup');
		
		$this->_checkDirStack($dirname);
		
		if (PROCESS_PROFILING) $this->_startProfile('_processLine timezone');
		
		// Convert the file list's UTC date/time to the report's timezone.
		$localtime = $this->_makeLocalTime($split[$this->_col_date], $split[$this->_col_time]);
		$split[$this->_col_date] = date('Y-m-d', $localtime);
		$split[$this->_col_time] = date('H:i:s', $localtime);
		
		if (PROCESS_PROFILING) $this->_endProfile('_processLine timezone');
		
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
		
		if (PROCESS_PROFILING) $this->_endProfile('_processLine');
	}
	
	function _checkDirStack($dirname = NULL) {
		
		if (PROCESS_PROFILING) $this->_startProfile('_checkDirStack');
		
		// Pop off directories till we find one whose path matches the current dirname.
		while (count($this->_dirStack) > (is_null($dirname) ? 0 : 1) && (is_null($dirname) || $this->_dirStack[count($this->_dirStack)-1]['path'] != $dirname)) {
			
			if (PROCESS_PROFILING) $this->_startProfile('_checkDirStack pop');
			
			$pop = array_pop($this->_dirStack);
			$dlpop = array_pop($this->_dirLookupStack); //TODO: Rename treepop
			
			if (PROCESS_PROFILING) $this->_endProfile('_checkDirStack pop');
			
			if ($this->_verboseLevel == PROCESS_VERBOSE_HIGHEST)
				$this->_raiseEvent(PROCESS_INFO_EXITDIR, $pop['path'], count($this->_dirStack) > 1 ? $this->_dirStack[count($this->_dirStack)-1]['path'] : null, count($this->_dirStack));
			
			if (PROCESS_PROFILING) $this->_startProfile('_checkDirStack tree');
			
			if (!$this->_noTree) {
				// Increment the directory lookup size.
				$this->_dirLookupSize += strlen(json_encode($dlpop));
				
				// Disable the tree if it's too large.
				if ($this->_dirLookupSize > $this->_maxTreeSize) {
					$this->_noTree = TRUE;
					
					if ($this->_verboseLevel >= PROCESS_VERBOSE_NORMAL)
						$this->_raiseEvent(PROCESS_INFO_TREEDISABLED);
				}
			}
			
			if (PROCESS_PROFILING) $this->_endProfile('_checkDirStack tree');
			
			if (PROCESS_PROFILING) $this->_startProfile('_checkDirStack parents');
			
			$pop['parents'] = array();
			foreach ($this->_dirStack as $parent) {
				array_push($pop['parents'], array(
					'name' => $parent['name'],
					'hash' => md5($parent['path'])
				));
			}
			
			if (PROCESS_PROFILING) $this->_endProfile('_checkDirStack parents');
			
			if (PROCESS_PROFILING) $this->_startProfile('_checkDirStack pop');
			
			// Remove the path so it is not saved.
			$path = $pop['path'];
			unset($pop['path']);
			
			if (PROCESS_PROFILING) $this->_endProfile('_checkDirStack pop');
			
			if (PROCESS_PROFILING) $this->_startProfile('_checkDirStack save');
			
			// Save the directory data.
			if (($bytes = file_put_contents($this->_reportDir . DIRECTORY_SEPARATOR . md5($path) . $this->_suffix, json_encode($pop))) === FALSE) {
				if ($this->_verboseLevel > PROCESS_VERBOSE_QUIET)
					$this->_raiseEvent(PROCESS_WARN_WRITEFAIL, $this->_reportDir . DIRECTORY_SEPARATOR . md5($path), $path);
				array_push($errors, array('writefail', $path, md5($path)));
			}
			
			$this->_bytesWritten += $bytes;
			$this->_filesWritten++;
			
			if (PROCESS_PROFILING) $this->_endProfile('_checkDirStack save');
		}
		
		if (PROCESS_PROFILING) $this->_endProfile('_checkDirStack');
	}
	
	function _saveDirTree() {
		if (!$this->_noTree) {
			if ($this->_verboseLevel >= PROCESS_VERBOSE_HIGHER)
				$this->_raiseEvent(PROCESS_INFO_SAVETREE);
			
			// Save the directory list.
			if (($bytes = file_put_contents($this->_reportDir . DIRECTORY_SEPARATOR . 'directories' . $this->_suffix, json_encode($this->_dirLookup))) === FALSE) {
				if ($this->_verboseLevel > PROCESS_VERBOSE_QUIET)
					$this->_raiseEvent(PROCESS_WARN_WRITEFAIL, $this->_reportDir . DIRECTORY_SEPARATOR . 'directories');
				array_push($this->_errors, array('writefail', 'directories', 'directories'));
			}
			
			$this->_bytesWritten += $bytes;
			$this->_filesWritten++;
		}
	}
	
	function _saveSettings() {
		if ($this->_verboseLevel >= PROCESS_VERBOSE_HIGHER)
			$this->_raiseEvent(PROCESS_INFO_SAVESETTINGS);
		
		$settings = array(
			'version' => '1.0',
			'listversion' => $this->_listVersion,
			'name' => $this->_name,
			'created' => date('M j, Y g:i:s A T'),
			'directorytree' => !$this->_noTree,
			'root' => md5(''), // The root path is always an empty string.
			'sizes' => $this->_sizeGroups,
			'modified' => $this->_modifiedGroups,
			'ds' => $this->_ds,
			'errors' => $this->_errors,
			'escaped' => isset($this->_header['escaped']) ? $this->_header['escaped'] : false
		);
		
		if ($this->_includeFullPath && isset($this->_header['dirname'])) {
			$settings['path'] = $this->_header['dirname'];
		}
		
		// Save the settings file.
		if (($bytes = file_put_contents($this->_reportDir . DIRECTORY_SEPARATOR . 'settings' . $this->_suffix, json_encode($settings))) === FALSE) {
			return false;
		}
		
		$this->_bytesWritten += $bytes;
		$this->_filesWritten++;
		
		return true;
	}
	
	function _processDirectory($path, $basename) {
		
		if (PROCESS_PROFILING) $this->_startProfile('_processDirectory');
		
		// Set an empty basename to the one set in the header, if the header has been set.
		if ($basename == '' && isset($this->_header['basename'])) {
			$basename = $this->_header['basename'];
		}
		
		if ($this->_verboseLevel == PROCESS_VERBOSE_HIGHEST)
			$this->_raiseEvent(PROCESS_INFO_ENTERDIR, $path, $basename, count($this->_dirStack));
		
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
		
		if (PROCESS_PROFILING) $this->_endProfile('_processDirectory');
	}
	
	function _processFile($type, $basename, $size, $date, $time) {
		
		if (PROCESS_PROFILING) $this->_startProfile('_processFile');
		
		if (PROCESS_PROFILING) $this->_startProfile('_processFile a');
		
		// 'ls' will output '-' instead of 'f' for files.
		if ($type == "-") $type = "f";
		
		if ($this->_verboseLevel == PROCESS_VERBOSE_HIGHEST)
			$this->_raiseEvent(PROCESS_INFO_PROCESSING_FILE, $type, $basename, $size, $date, $time);
		
		// Clear the size for special files types.
		if ($specialFile = $type != 'f' && $type != 'd' && $type != 'l') {
			$size = 0;
		}
		
		if (PROCESS_PROFILING) $this->_endProfile('_processFile a');
		
		if (PROCESS_PROFILING) $this->_startProfile('_processFile b');
		
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
		
		if (PROCESS_PROFILING) $this->_endProfile('_processFile b');
		
		if (PROCESS_PROFILING) $this->_startProfile('_processFile c');
		
		// Determine the root path for the 'top 100' paths.
		$rootPath = !isset($this->_header['basename']) || $this->_header['basename'] == '' ? '.'
			: ($this->_header['basename'] == $this->_ds ? ''
				: $this->_header['basename']);
		
		if (PROCESS_PROFILING) $this->_endProfile('_processFile c');
		
		if (PROCESS_PROFILING) $this->_startProfile('_processFile for');
		
		// Increment totals for directories in the stack.
		for ($i = 0; $i < count($this->_dirStack); $i++) {
			
			if (PROCESS_PROFILING) $this->_startProfile('_processFile totals');
			
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
			
			if (PROCESS_PROFILING) $this->_endProfile('_processFile totals');
			
			if (PROCESS_PROFILING) $this->_startProfile('_processFile top100');
			
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
			
			if (PROCESS_PROFILING) $this->_endProfile('_processFile top100');
		}
		
		if (PROCESS_PROFILING) $this->_endProfile('_processFile for');
		
		if (PROCESS_PROFILING) $this->_endProfile('_processFile');
	}
	
	function _getFileExtension($name) {
		$name = strtolower($name);
		$index = strrpos($name, '.');

		if ($index === FALSE || $index == 0 || $index == strlen($name)-1) {
			return '';
		}
		elseif (preg_match('/^[0-9a-z_\-~\^]{1,10}$/', $ext = substr($name, $index+1))) {
			return $ext;
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
	
	// Create the regular expression to validate lines.
	function _createLineRegEx() {
		switch ($this->_listVersion) {
			case 1:
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
				$this->_lineRegEx = '/^' . 
					implode(preg_quote($this->_delim), array(
						'[dflcbpus\-]',
						'[0-9]{4}-[0-9]{2}-[0-9]{2}', // Date
						'[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?', // Time
						'[0-9]+', // Size
					)) . preg_quote($this->_delim) . '/';
				break;
		}
	}
	
	function ExplodeEscaped($delim, $str, $limit = 0, $escape = "\\") {
		$arr = array();
		$index = -1;
	
		while (($limit == 0 || $limit >= count($arr)) && ($index = strpos($str, $delim, $index + 1)) !== FALSE) {
				
			// Count the number of escape characters before the delim.
			$escapes = 0;
			for ($i = $index - 1; $i >= 0; $i--) {
				if (substr($str, $i, 1) == $escape) $escapes++;
				else break;
			}
			
			// This delim is not being escaped if an even number of escape characters.
			if ($escapes % 2 == 0) {
				array_push($arr, str_replace($escape . $delim, $delim, str_replace($escape . $escape, $escape, substr($str, 0, $index))));
				
				if ($index + 1 > strlen($str))
					$str = null;
				else {
					$str = substr($str, $index + 1);
					$index = -1;
				}
			}
		}
		
		if (!is_null($str))
			array_push($arr, str_replace($escape . $delim, $delim, str_replace($escape . $escape, $escape, $str)));
		
		return $arr;
	}
	
	function _raiseEvent() {
		if (PROCESS_PROFILING) $this->_startProfile('_raiseEvent');
		if (!is_null($this->_eventCallback)) {
			$args = func_get_args();
			call_user_func_array($this->_eventCallback, $args);
			//call_user_func($this->_eventCallback, PROCESS_WARN_WRITEFAIL, $this->_reportDir . DIRECTORY_SEPARATOR . 'settings');
		}
		if (PROCESS_PROFILING) $this->_endProfile('_raiseEvent');
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
	function getEventCallback() {
		return $this->_eventCallback;
	}
	function setEventCallback($callback) {
		$this->_eventCallback = $callback;
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
	function getFailDetails() {
		return $this->_failDetails;
	}
	function getMinStatusSeconds() {
		return $this->_minStatusSeconds;
	}
	function setMinStatusSeconds($seconds) {
		$this->_minStatusSeconds = $seconds;
	}
	
	var $_profiles = array();
	var $_profileLastStart = NULL;
	var $_profileStack = array();
	
	function dumpProfiles() {
		if (PROCESS_PROFILING) {
			if (count($this->_profileStack)) {
				echo "Remaining in stack:\n";
				var_dump($this->_profileStack);
				exit(1);
			}
			
			$longestKey = strlen("Profile Key");
			$longestTime = strlen("Time");
			$longestCount = strlen("Count");
			$longestAverage = strlen("Average");
			
			ksort($this->_profiles);
			
			foreach ($this->_profiles as $key => $val) {
				
				$this->_profiles[$key][2] = number_format($this->_profiles[$key][0] / $this->_profiles[$key][1], 8);
				
				$this->_profiles[$key][0] = number_format($this->_profiles[$key][0], 8);
				$this->_profiles[$key][1] = number_format($this->_profiles[$key][1]);
				
				$longestKey = max(strlen($key), $longestKey);
				$longestTime = max(strlen($this->_profiles[$key][0]), $longestTime);
				$longestCount = max(strlen($this->_profiles[$key][1]), $longestCount);
				$longestAverage = max(strlen($this->_profiles[$key][2]), $longestAverage);
			}
			
			echo "\n";
			printf("% -".($longestKey)."s  % -" . $longestTime . "s  % -" . $longestCount . "s  % -" . $longestAverage . "s\n", "Profile Key", "Time", "Count", "Average");
			printf("%'-".($longestKey)."s  %'-" . $longestTime . "s  %'-" . $longestCount . "s  %'-" . $longestAverage . "s\n", "", "", "", "");
			foreach ($this->_profiles as $key => $val) {
				printf("% -".($longestKey)."s  % " . $longestTime . "s  % " . $longestCount . "s  % " . $longestAverage . "s\n", $key, $val[0], $val[1], $val[2]);
			}
			echo "\n";
		}
	}
	
	function _startProfile($name) {
		$end = microtime();
		
		if (($index = (count($this->_profileStack) - 1)) >= 0) {
			$this->_incrementProfile($this->_profileStack[$index], $this->_profileLastStart, $end, false);
		}
		
		array_push($this->_profileStack, $name);
		$this->_profileLastStart = microtime();
	}
	
	function _endProfile($name) {
		$end = microtime();
		if (!is_null($this->_profileLastStart)) {
			
			if ($name !== array_pop($this->_profileStack)) {
				var_dump($this->_profileStack);
				echo "Bad _endProfile: $name\n"; exit;
			}
			
			$this->_incrementProfile($name, $this->_profileLastStart, $end);
			
			$this->_profileLastStart = microtime();
		}
	}
	
	function _incrementProfile($name, $start, $end, $incrementCounter = TRUE) {
		// split the time into components
		list($startUSec, $startSec) = explode(' ', $start);
		list($endUSec, $endSec) = explode(' ', $end);
			
		// typecast them to the required types
		$startSec = (int) $startSec;
		$startUSec = (float) $startUSec;
			
		$endSec = (int) $endSec;
		$endUSec = (float) $endUSec;
			
		if ($startUSec > $endUSec) {
			$val = ($endSec - 1 - $startSec) + ($startUSec + $endUSec);
		}
		else {
			$val = ($endSec - $startSec) + ($endUSec - $startUSec);
		}
			
		if (!array_key_exists($name, $this->_profiles))
			$this->_profiles[$name] = array(0, 0);
		
		$this->_profiles[$name][0] += $val;
		if ($incrementCounter) $this->_profiles[$name][1] += 1;
	}
}
?>
