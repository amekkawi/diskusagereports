<?php

/* 
 * Copyright (c) 2011 André Mekkawi <contact@andremekkawi.com>
 * Revision: $Revision$
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereport.sf.net/license.html
 */

define('PROCESS_VERSION', '1.0');

define('PROCESS_INVALID_FILELIST', 1);
define('PROCESS_INVALID_REPORTDIR', 2);
define('PROCESS_FAILED_REPORTDIR_MKDIR', 3);
define('PROCESS_FAILED_OPEN_FILELIST', 4);
define('PROCESS_INVALID_HEADER', 5);

define('PROCESS_COL_TYPE', 0);
define('PROCESS_COL_DATE', 1);
define('PROCESS_COL_TIME', 2);
define('PROCESS_COL_SIZE', 3);
define('PROCESS_COL_DEPTH', 4);
define('PROCESS_COL_PATH', 5);

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
	
	// Internal only
	var $_lineRegEx;
	var $_errors;
	var $_header;
	
	var $_dirStack;
	var $_dirLookup;
	var $_dirLookupStack;
	var $_dirLookupSize;
	
	function Process() {
		$this->_name = null;
		$this->_fileList = null;
		$this->_reportDir = null;
		$this->_totalsDepth = 6;
		$this->_top100Depth = 3;
		$this->_maxLineLength = 1024;
		$this->_maxTreeSize = 800 * 1024;
		$this->_noTree = false;
		$this->_delim = "\x00";
		$this->_ds = DIRECTORY_SEPARATOR;
	}
	
	function run() {
		$this->_errors = array();
		$this->_header = NULL;
		
		$this->_dirStack = array();
		$this->_dirLookup = array();
		$this->_dirLookupStack = array();
		$this->_dirLookupSize = 0;
		
		// Create the regular expression to validate lines.
		$this->_lineRegEx = '/^' . 
			implode(preg_quote($this->_delim), array(
				'[!dfl]',
				'[0-9]{4}-[0-9]{2}-[0-9]{2}', // Date
				'[0-9]{2}:[0-9]{2}:[0-9]{2}', // Time
				'[0-9]+', // Size
				'[0-9]+' // Depth
			)) . preg_quote($this->_delim) . '/';
		
		// Verify the report directory is valid.
		if (file_exists($this->_reportDir)) {
			if (!is_dir($this->_reportDir)) {
				return PROCESS_INVALID_REPORTDIR;
			}
		}
		
		// Create the report directory if it does not exist.
		elseif (!mkdir($this->_reportDir)) {
			return PROCESS_FAILED_REPORTDIR_MKDIR;
		}
		
		return $this->_readLines();
	}
	
	function _readLines() {
		// Attempt to open the file list.
		if (($fh = fopen($this->_fileList, 'r')) === FALSE) {
			return PROCESS_FAILED_OPEN_FILELIST;
		}
		
		while (($line = fgets($fh, $this->_maxLineLength + 2)) !== FALSE) {
			$line = rtrim($line, '\n\r');
			
			// Ignore blank lines
			if (trim($line) != '') {
				
				// Process the header.
				if (substr($line, 0, 1) == '#') {
					if (($ret = $this->_processHeader($line)) !== TRUE) {
						return $ret;
					}
				}
				
				else {
					$this->_processLine($line);
					var_dump($this->_dirStack);
					exit;
				}
			}
		}
		
		return TRUE;
	}
	
	function _processHeader($line) {
		
		if (strlen($line) < 2 || strlen($line) > $this->_maxLineLength) {
			return PROCESS_INVALID_HEADER;
		}
		
		// The first character after the pound-sign is the delim.
		$this->_delim = substr($line, 1, 1);
			
		// Explode the remaining part of the header.
		$splitHeader = explode($this->_delim, substr($line, 2));
		
		// Check that the header has a minimum number of columns,
		// to allow future versions to add more.
		if (count($splitHeader) < 3) {
			return PROCESS_INVALID_HEADER;
		}
		
		// Override the directory separator.
		$this->_ds = $splitHeader[0];
		
		if (strlen($this->_ds) != 1) {
			return PROCESS_INVALID_HEADER;
		}
		
		$this->_header = array(
			'dirname' => $splitHeader[1],
			'basename' => $splitHeader[2],
			'datetime' => $splitHeader[3]
		);
		
		return TRUE;
	}
	
	function _processLine($line) {
		if (strlen($line) > $this->_maxLineLength) {
			array_push($this->_errors, array('invalidline', 'maxlinelength', $line));
		}

		// Validate the line up to the path column.
		elseif (!preg_match($this->_lineRegEx, $line)) {
			array_push($this->_errors, array('invalidline', 'regex', $line));
		}

		// Split the line and validate its length;
		elseif (count($split = explode($this->_delim, $line)) != 6) {
			array_push($this->_errors, array('invalidline', 'columncount', $split));
		}

		// Make sure the path is at least one character long.
		elseif (strlen($split[PROCESS_COL_PATH]) == 0) {
			array_push($this->_errors, array('invalidline', 'column', 'path', PROCESS_COL_PATH, $split));
		}

		// TODO: Update to handle Windows-1252 characters.
		elseif (json_encode($split[PROCESS_COL_PATH]) == 'null') {
			echo 'Invalid characters in the path: ' . $split[PROCESS_COL_PATH] . "\n";
			exit(1);
		}

		else {
			// Break up the path into dirname/basename.
			
			//$this->_checkDirStack($split[PROCESS_COL_PATH]);
			
			// Convert the file list's UTC date/time to the report's timezone.
			$localtime = $this->_makeLocalTime($split[PROCESS_COL_DATE], $split[PROCESS_COL_TIME]);
			$split[PROCESS_COL_DATE] = date('Y-m-d', $localtime);
			$split[PROCESS_COL_TIME] = date('H:i:s', $localtime);
			
			if ($split[PROCESS_COL_TYPE] == 'd') {
				$this->_processDirectory($split[PROCESS_COL_PATH]);
			}
			else {
				// TODO
			}
		}
	}
	
	function _processDirectory($path) {
		
		// Add the root directory to the stack, if the stack is empty.
		if ($path != '' && count($this->_dirStack) == 0) {
			$this->_processDirectory('');
		}
		
		$basename = basename($path);
		
		if ($basename == '') {
			if (!is_null($this->_header) && $this->_header['basename']) {
				$basename = $this->_header['basename'];
			}
			else {
				$basename = '.';
			}
		}
		
		$hash = md5($path);
		
		$newDir = array(
			'name' => $basename,
			'path' => $path,
			'bytes' => 0,
			'totalbytes' => 0,
			'num' => 0,
			'totalnum' => 0,
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
		
		// Add this directory to the directory list, if is not being skipped.
		if (!$this->_noTree) {
			// Add the directory to the hash lookup.
			$this->_dirLookup[$hash] = array(
				'name' => $basename,
				'totalbytes' => &$newDir['totalbytes'],
				'totalnum' => &$newDir['totalnum'],
				'subdirs' => array()
			);

			// Add this directory to its parent (if one exists).
			if (count($this->_dirLookupStack) > 0) {
				array_push($this->_dirLookupStack[count($this->_dirLookupStack)-1]['subdirs'], $hash);
			}

			// Add the directory to the lookup stack.
			array_push($this->_dirLookupStack, &$this->_dirLookup[$hash]);
		}
			
		// Add this directory to its parent (if one exists).
		if (count($this->_dirStack) > 0) {
			array_push($this->_dirStack[count($this->_dirStack)-1]['subdirs'], array(
				'name' => $basename,
				'totalbytes' => &$newDir['totalbytes'],
				'totalnum' => &$newDir['totalnum'],
				'hash' => $hash
			));
		}
			
		// Add the directory to the stack.
		array_push($this->_dirStack, $newDir);
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
}
?>