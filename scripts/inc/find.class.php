<?php
define('FIND_OK', 0);
define('FIND_NOT_DIRECTORY', 1);
define('FIND_FAILED_RESOLVE', 2);
define('FIND_FAILED_STAT', 3);
define('FIND_FAILED_STDOUT', 4);
define('FIND_FAILED_STDERR', 5);

class Find {
	var $_ds;
	var $_delim;
	
	function Find() {
		$this->_ds = DIRECTORY_SEPARATOR;
		$this->delim = "\x00";
	}
	
	function run($directory, $out = NULL, $err = NULL) {
		if (is_link($directory) || !is_dir($directory)) {
			return FIND_NOT_DIRECTORY;
		}
		
		// Attempt to resolve the path (in case it is relative).
		if (($realpath = realpath($directory)) === FALSE) {
			return FIND_FAILED_RESOLVE;
		}
		
		// Attempt to stat the starting directory.
		if (($stat = stat($directory)) === FALSE) {
			return FIND_FAILED_STAT;
		}
		
		// Set out and error streams if missing.
		if (is_null($out) && ($cout = ($out = fopen('php://stdout', 'w+')) !== FALSE) === FALSE) {
			return FIND_FAILED_STDOUT;
		}
		if (is_null($err) && ($cerr = ($err = fopen('php://stderr', 'w+')) !== FALSE) === FALSE) {
			if (isset($cout)) fclose($out);
			return FIND_FAILED_STDERR;
		}
		
		// Clean the right side of the directory path, if it is at least two chracters long.
		if (strlen($realpath) >= 2) {
			$realpath = rtrim($realpath, DIRECTORY_SEPARATOR);
		}
		
		$dirname = dirname($realpath);
		$basename = basename($realpath);
		
		// Support *nix root directories.
		if ($dirname == DIRECTORY_SEPARATOR && $basename == '') {
			$dirname = '';
			$basename = DIRECTORY_SEPARATOR;
		}
		
		// On Windows both $dirname and $basename will return 'C:' for a root path.
		elseif (substr($dirname, -1) == ':' && $dirname == $basename) {
			$dirname = '';
		}
		
		// Output the header for the find results.
		fwrite($out, '#' . $this->_delim . $this->_ds . $this->_delim . str_replace(DIRECTORY_SEPARATOR, $this->_ds, $dirname) . $this->_delim . $basename . $this->_delim . date('YmdHis') . "\n");
		
		$this->_processDirectory($out, $err, $realpath, '', 0);
		
		// Close streams if they were opened in this method.
		if (isset($cout)) fclose($out);
		if (isset($cerr)) fclose($err);
				
		return FIND_OK;
	}
	
	function _processDirectory(&$out, &$err, $rootpath, $pathext, $depth) {
		$fullpath = $rootpath . DIRECTORY_SEPARATOR . $pathext;
		
		if (($dirh = opendir($fullpath)) === FALSE) {
			fwrite($err, "Failed to open directory for listing files: " . $fullpath . "\n");
			$this->_outputError($out, 'OPENDIR_FAIL', array($fullpath));
		}
		else {
			while (($entry = readdir($dirh)) !== FALSE) {
				
				// Skip dot and double-dot notation.
		        if ($entry != '.' && $entry != '..') {
		        	$this->_processDirectoryEntry($out, $err, $rootpath, $pathext, $depth, $entry);
		        }
		    }
		    
			closedir($dirh);
		}
	}
	
	function _processDirectoryEntry(&$out, &$err, $rootpath, $pathext, $depth, $entry) {
		$fullpath = $rootpath . DIRECTORY_SEPARATOR . $pathext . DIRECTORY_SEPARATOR . $entry;
		 
		// Attempt to stat file.
		if (($stat = lstat($fullpath)) !== FALSE) {

			if (is_link($fullpath)) {
				$type = 'l';
			}
			elseif (is_dir($fullpath)) {
				$type = 'd';
			}
			elseif (is_file($fullpath)) {
				$type = 'f';
			}
			else {
				$type = '?';
			}
			
			$this->_outputEntry($out, $type, $pathext, $depth, $entry, $stat);
			
			if ($type == 'd') {
				$this->_processDirectory($out, $err, $rootpath, ($pathext == '' ? '' : $pathext . DIRECTORY_SEPARATOR) . $entry, $depth + 1);
			}
		}
		else {
			fwrite($err, 'Failed to stat: ' . $fullpath . "\n");
			$this->_outputError($out, 'STAT_FAIL', array($fullpath));
		}
	}
	
	function _outputEntry(&$out, $type, $pathext, $depth, $entry, $stat) {
		// Make sure the directory separator for the output is correct.
		if ($this->_ds != DIRECTORY_SEPARATOR) {
			$directory = str_replace(DIRECTORY_SEPARATOR, $this->_ds, $directory);
		}
		
		fwrite($out, implode($this->_delim, array(
			$type, 
			date('Y-m-d', intval($stat['mtime'])), 
			date('H:i:s', intval($stat['mtime'])), 
			$stat['size'],
			$depth,
			$pathext,
			$entry
		)) . "\n");
	}
	
	function _outputError(&$out, $id, $arguments = array()) {
		fwrite($out, implode($this->_delim, array_merge(array(
			'!',
			$id
		), $arguments)) . "\n");
	}
	
	function getDS() {
		return $this->_ds;
	}
	
	function setDS($ds) {
		$this->_ds = $ds;
	}
	
	function getDelim() {
		return $this->_delim;
	}
	
	function setDelim($delim) {
		$this->_delim = $delim;
	}
}
?>