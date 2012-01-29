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
	
	var $_lastError;
	
	function Find() {
		$this->_ds = DIRECTORY_SEPARATOR;
		$this->delim = "\x00";
	}
	
	function run($directory, $out = NULL, $err = NULL) {
		if (is_link($directory) || !is_dir($directory)) {
			return FIND_NOT_DIRECTORY;
		}
		
		// Attempt to resolve the path (in case it is relative).
		if (($directory = realpath($directory)) === FALSE) {
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
			return FIND_FAILED_STDERR;
		}
		
		// Clean the right side of the directory path, if it is at least two chracters long.
		if (strlen($directory) >= 2) {
			$directory = rtrim($directory, DIRECTORY_SEPARATOR);
		}
		
		$dirname = dirname($directory);
		$basename = basename($directory);
		//$stack = array();
		
		// process directory
		echo 'path: ' . $directory . "\n";
		echo 'dirname: ' . dirname($directory) . "\n";
		echo 'basename: ' . basename($directory) . "\n\n";
		
		// Support *nix root directories.
		if ($dirname == DIRECTORY_SEPARATOR && $basename == '') {
			//array_push($stack, '');
			$dirname = '';
			$basename = DIRECTORY_SEPARATOR;
		}
		
		elseif (substr($dirname, -1) == ':' && $dirname == $basename) {
			$dirname = '';
		}
		
		$this->_outputEntry($out, 'd', $dirname, $basename, $stat);
		
		// On Windows both directory and name will return 'C:' for a root path.
		if (substr($directory, -1) == ':' && $directory == $name) {
			//$directory = '';
		}
		
		//$this->_processDirectory($out, $err, $directory, 0);
		//$this->_processDirectory($out, $err, $stack, $basename, 0);
		
		// Close streams if they were opened in this method.
		if (isset($cout)) fclose($out);
		if (isset($cerr)) fclose($err);
				
		return FIND_OK;
	}
	
	function _processDirectory(&$out, &$err, $directory, $depth) {
		if (($dirh = opendir($dirPath)) === FALSE) {
			fwrite($err, "Failed to open directory for listing files: " . $directory . "\n");
		}
		else {
			while (($file = readdir($dirh)) !== FALSE) {
				
				// Skip dot and double-dot notation.
		        if ($file != '.' && $file != '..') {
		        	$this->_processDirectoryEntry($out, $err, $directory, $depth, $file);
		        }
		    }
			closedir($dirh);
		}
	}
	
	function _processDirectoryEntry(&$out, &$err, $directory, $depth, $name) {
		$path = $directory . DIRECTORY_SEPARATOR . $name;
		 
		// Attempt to stat file.
		if (($stat = lstat($path)) !== FALSE) {

			if (is_link($path)) {
				$type = 'l';
			}
			elseif (is_dir($path)) {
				$type = 'd';
			}
			elseif (is_file($path)) {
				$type = 'f';
			}
			else {
				$type = '-';
			}
			
			//echo $type . $args['delim'] . date('Y-m-d', intval($stat['mtime'])) . $args['delim'] . date('H:i:s', intval($stat['mtime'])) . $args['delim'] . $stat['size'] . $args['delim'] . $depth . $args['delim'] . $directory . $args['delim'] . $file . "\n";
			$this->_outputEntry($out, $type, $directory, $name, $stat);
			
			if ($isdir) {
				$this->_processDirectory($out, $err, $path, $depth + 1);
			}
		}
		else {
			fwrite($err, 'Failed to stat: ' . $filepath."\n");
		}
	}
	
	function _outputEntry(&$out, $type, $directory, $name, $stat) {
		// Make sure the directory separator for the output is correct.
		if ($this->_ds != DIRECTORY_SEPARATOR) {
			$directory = str_replace(DIRECTORY_SEPARATOR, $this->_ds, $directory);
		}
		
		fwrite($out, $type . $this->_delim . date('Y-m-d', intval($stat['mtime'])) . $this->_delim . date('H:i:s', intval($stat['mtime'])) . $this->_delim . $stat['size'] . $this->_delim . '0' . $this->_delim . $directory . $this->_delim . $name . "\n");
	}
	
	function _processDirectoryOLD($out, $err, &$stack, $basename, $depth) {
		
		// Push the new directory name on the stack.
		array_push($stack, $basename);
		
		$dirPath = implode(DIRECTORY_SEPARATOR, $stack);
		$dirOutPath = implode($this->_ds, $stack);
		
		echo "dirPath: $dirPath\n";
		echo "dirOutPath: $dirOutPath\n";
		
		return;
		
		// The path will be empty if the directory is a *nix root path.
		if ($dirPath == '') {
			$dirPath = DIRECTORY_SEPARATOR;
			$dirOutPath = $this->_ds;
		}
		
		if (($dirh = opendir($dirPath)) === FALSE) {
			fwrite($err, "Failed to open directory for listing files: " . $basename . "\n");
		}
		else {
			while (($file = readdir($dirh)) !== FALSE) {
		        if ($file != '.' && $file != '..') {
			        $filepath = ConcatPath($this->_ds, $basename, $file);
			        if (($stat = stat($filepath)) !== FALSE) {
			        	if (!($islink = is_link($filepath))) {
			        		$isdir = is_dir($filepath);
				        	echo ($isdir ? 'd' : 'f') . $args['delim'] . date('Y-m-d', intval($stat['mtime'])) . $args['delim'] . date('H:i:s', intval($stat['mtime'])) . $args['delim'] . $stat['size'] . $args['delim'] . $depth . $args['delim'] . $basename . $args['delim'] . $file . "\n";
				        	if (!$islink && $isdir) {
				        		ProcessFolder($filepath, $depth + 1);
				        	}
			        	}
			        }
			        else {
			        	fwrite($err, 'Failed to stat: ' . $filepath."\n");
			        }
		        }
		    }
			closedir($dirh);
		}
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
	
	function getLastError() {
		return $this->_lastError;
	}
}
?>