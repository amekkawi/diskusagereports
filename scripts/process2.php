<?php

require("inc/class.util.php");
require("inc/class.options.php");
require("inc/class.largelist.php");
require("inc/class.largemap.php");
require("inc/class.fileiterator.php");

//ini_set('display_errors', 1);
//error_reporting(E_ALL);

class DirOutput implements ListOutput {

	protected $report;
	protected $sortIndex;
	protected $sortName;
	protected $reverseSort;

	public function __construct($report, $sortIndex, $sortName, $reverseSort = false) {
		$this->report = $report;
		$this->sortIndex = $sortIndex;
		$this->sortName = $sortName;
		$this->reverseSort = $reverseSort;
	}

	public function getMaxSegments() {
		return false;
	}

	public function openTempFile($prefix, $index, $mode) {
		return fopen($this->report->buildPath($prefix . '_' . $this->sortName . '_' . $index . '.tmp'), $mode);
	}

	public function deleteTempFile($prefix, $index) {
		return unlink($this->report->buildPath($prefix . '_' . $this->sortName . '_' . $index . '.tmp'));
	}

	public function openOutFile($prefix, $index, $mode = 'w') {
		return fopen($this->report->buildPath($prefix . '_' . $this->sortName . '_' . $index . '.dat'), $mode);
	}

	public function compare($a, $b) {
		$sortIndex = $this->sortIndex;
		if ($a[0][$sortIndex] < $b[0][$sortIndex])
			return $this->reverseSort ? 1 : -1;
		if ($a[0][$sortIndex] > $b[0][$sortIndex])
			return $this->reverseSort ? -1 : 1;
		return 0;
	}

	public function isOverMax($size, $count) {
		return /*$size > 40 * 1024 || */ $count > 900;
	}

}

class DirInfo extends FileInfo {

	protected $report;

	public $parent;

	public $subDirCount = 0;

	public $directFileCount = 0;
	public $subFileCount = 0;

	public $directSize = 0;
	public $subSize = 0;

	public $fileList;

	function __construct(Report $report, DirInfo $parent = null) {
		$this->parent = $parent;
		$this->report = $report;
		$this->fileList = new LargeList($report->fileListOutputs);
	}

	public function setFromOptions(Options $options) {
		$this->type = 'd';
		$this->path = '';
		$this->dirname = '';
		$this->basename = $options->basename === null || $options->basename == '' ? '.' : $options->basename;
		$this->hash = md5('');
		$this->fileList->prefix = $this->hash . '_files';
	}

	public function setFromLine(Options $options, $line) {
		parent::setFromLine($options, $line);
		$this->fileList->prefix = $this->hash . '_files';
	}


	public function onPop() {
		$parents = array();
		$parent = $this->parent;
		while ($parent !== null) {
			$parents[] = array(
				'name' => $parent->basename,
				'hash' => $parent->hash
			);
			$parent = $parent->parent;
		}

		$reportListMap = $this->report->fileListMap;
		if ($this->fileList->getTotalSize() < $reportListMap->getMaxPerItem()) {
			$fileListJSON = $this->fileList->toJSON();
			if (strlen($fileListJSON) > 10) {
				$this->fileList = $reportListMap->add($this->hash, $fileListJSON);
			}
			else {
				$this->fileList = $fileListJSON;
			}
		}
		else {
			$this->fileList->save();
		}
	}

	public function onChildPop(DirInfo $dirInfo) {
		$this->subDirCount += $dirInfo->subDirCount + 1;
		$this->subSize += $dirInfo->directSize + $dirInfo->subSize;
		$this->subFileCount += $dirInfo->directFileCount + $dirInfo->subFileCount;
	}

	public function processFileInfo(FileInfo $fileInfo) {
		$this->directFileCount++;
		$this->directSize += $fileInfo->size;
		$this->fileList->add(array($fileInfo->basename, $fileInfo->size, $fileInfo->date . ' ' . $fileInfo->time), $fileInfo->toJSON());
	}

}

class FileInfo {
	public $type;
	public $date;
	public $time;
	public $size;
	public $path;
	public $dirname;
	public $basename;
	public $hash;

	public function setFromLine(Options $options, $line) {

		if (strlen($line) > $options->maxLineLength)
			throw new LineException(LineException::TOO_LONG, $line);

		// Validate the line up to the path column.
		if (!preg_match($options->lineRegEx, $line))
			throw new LineException(LineException::PATTERN_MISMATCH, $line);

		// Split the line and validate its length.
		if (count($split = explode($options->delim, $line, $options->colCount)) != $options->colCount)
			throw new LineException(LineException::COLUMN_COUNT, $split);

		// Make sure the path is at least one character long.
		if (strlen($split[$options->col_path]) == 0)
			throw new LineException(LineException::EMPTY_PATH, $split, $options->col_path);

		$this->type = $split[$options->col_type];
		$this->date = $split[$options->col_date];
		$this->time = $split[$options->col_time];
		$this->size = Util::BigVal($split[$options->col_size]);
		$this->path = $split[$options->col_path];
		$this->hash = md5($this->path);

		if ($this->type == '-')
			$this->type = 'f';

		// Break up the path into dirname/basename.
		if (($this->dirname = dirname($this->path)) == '.') $this->dirname = '';
		$this->basename = basename($this->path);
	}

	public function toJSON() {
		$basename = @json_encode($this->basename);

		// Attempt to convert it from Windows-1252 to UTF, if the json_encode failed.
		if ($basename === 'null') {
			$basename = iconv('Windows-1252', 'UTF-8//IGNORE', $this->basename);

			// Convert the name to a byte array if all else fails.
			if ($basename === false)
				$basename = array_values(unpack('C*', $this->basename));
		}

		return
			'['
			. json_encode($this->type)
			. ',' . $basename
			. ',' . json_encode($this->size)
			. ',' . json_encode($this->date)
			. ',' . json_encode($this->time)
			. ']';
	}
}

class ScanReader {

	const DEBUG = false;

	protected $report;

	public function __construct(Report $report) {
		$this->report = $report;
	}

	public function read($filename) {

		// Attempt to open the file list.
		if (($fh = fopen($filename, 'r')) === FALSE)
			throw new ScanException(ScanException::FOPEN_FAIL);

		$options = new Options();
		$iterator = new FileIterator($fh);
		$fileInfo = new FileInfo();

		$currentDir = new DirInfo($this->report);
		$currentDir->setFromOptions($options);

		$headerAllowed = true;

		foreach ($iterator as $lineNum => $line) {

			// Ignore blank lines
			if (trim($line) == '')
				continue;

			try {
				$flag = substr($line, 0, 1);

				// Process the header.
				if ($flag == '#') {
					if (!$headerAllowed)
						throw new ScanException(ScanException::HEADER_EXCEPTION);

					$options->processHeader($line);
					$headerAllowed = false;
				}

				elseif ($flag == '!') {
					//$this->processError($line);
				}

				elseif ($flag == 'd') {
					$currentDir = new DirInfo($this->report, $currentDir);
					$currentDir->setFromLine($options, $line);
					if (self::DEBUG)
						echo "Entering dir: {$currentDir->path}\n";
				}

				elseif ($flag == 'f' || $flag == '-') {
					$fileInfo->setFromLine($options, $line);
					$headerAllowed = false;

					if (self::DEBUG)
						echo "    File: {$fileInfo->path}\n";

					while ($currentDir->path != $fileInfo->dirname) {

						if ($currentDir->parent === null)
							throw new ScanException(ScanException::POPDIR_NOPARENT);

						if (self::DEBUG)
							echo "Popping dir: {$currentDir->path}\n";

						$popDir = $currentDir;
						$currentDir = $currentDir->parent;

						$popDir->onPop();
						$currentDir->onChildPop($popDir);
					}

					$currentDir->processFileInfo($fileInfo);
				}
			}
			catch (LineException $e) {
				echo "LineException on line $lineNum: $e->getMessage()\n";
			}
		}

		// Process any remaining directories.
		do {
			if (self::DEBUG)
				echo "Popping dir: {$currentDir->path}\n";

			$popDir = $currentDir;

			$popDir->onPop();

			if ($currentDir->parent !== null) {
				$currentDir = $currentDir->parent;
				$currentDir->onChildPop($popDir);
			}

		} while ($currentDir->parent !== null);

		foreach (get_object_vars($currentDir) as $attribute => $val)
			if (is_scalar($val)) {
				ob_start();
				var_dump($val);
				echo printf("%s", "$attribute: " . trim(ob_get_clean())) . "\n";
			}

		fclose($fh);
	}
}

class ReportMapOutput implements MapOutput {

	protected $report;
	protected $maxPerOut;
	protected $maxPerItem;

	public function __construct($report, $maxPerOut, $maxPerItem) {
		$this->report = $report;
		$this->maxPerOut = $maxPerOut;
		$this->maxPerItem = $maxPerItem;
	}

	public function openOutFile($prefix, $index, $mode = 'w') {
		return fopen($this->report->buildPath($prefix . '_' . $index . '.dat'), $mode);
	}

	public function getMaxPerOut() {
		return $this->maxPerOut;
	}

	public function getMaxPerItem() {
		return $this->maxPerItem;
	}

}

class Report {
	public $directory;
	public $fileListOutputs;
	public $fileListMap;

	public function __construct($directory) {
		$this->directory = rtrim(realpath($directory), DIRECTORY_SEPARATOR);

		$this->fileListOutputs = array(
			new DirOutput($this, 0, 'name'),
			new DirOutput($this, 1, 'size'),
			new DirOutput($this, 2, 'date')
		);

		$this->fileListMap = new LargeMap(new ReportMapOutput($this, 80 * 1024, 40 * 1024));
		$this->fileListMap->prefix = 'filelists';
	}

	public function buildPath($extension) {
		return $this->directory . DIRECTORY_SEPARATOR . $extension;
	}
}

class HeaderException extends Exception {

	const TOO_SHORT = 'TOO_SHORT';
	const INVALID_DATETIME = 'INVALID_DATETIME';
	const COLUMNS_MISSING = 'COLUMNS_MISSING';
	const UNSUPPORTED_SCAN_VERSION = 'UNSUPPORTED_SCAN_VERSION';
	const INVALID_COLUMN_SEPARATOR = 'INVALID_COLUMN_SEPARATOR';
	const INVALID_DIR_SEPARATOR = 'INVALID_DIR_SEPARATOR';
	const INVALID_SETTING = 'INVALID_SETTING';
	const UNKNOWN_SETTING = 'UNKNOWN_SETTING';
	const MISSING_SETTING = 'MISSING_SETTING';
	const INVALID_CHARACTERS = 'INVALID_CHARACTERS';
	const UNEXPECTED_HEADER = 'UNEXPECTED_HEADER';

	public function __construct($reason) {
		parent::__construct($reason);
	}
}

class HeaderSettingException extends HeaderException {
	public $name;

	public function __construct($reason, $name) {
		parent::__construct($reason);
		$this->name = $name;
	}
}

class LineException extends Exception {
	const TOO_LONG = 'TOO_LONG';
	const PATTERN_MISMATCH = 'PATTERN_MISMATCH';
	const COLUMN_COUNT = 'COLUMN_COUNT';
	const EMPTY_PATH = 'EMPTY_PATH';
	const INVALID_ENCODING = 'INVALID_ENCODING';

	public $line;
	public $column;

	public function __construct($reason, $line, $column = -1) {
		parent::__construct($reason);
		$this->line = $line;
		$this->column = $column;
	}
}

class ScanException extends Exception {
	const FOPEN_FAIL = 'FOPEN_FAIL';
	const HEADER_EXCEPTION = 'HEADER_EXCEPTION';
	const POPDIR_NOPARENT = 'POPDIR_NOPARENT';

	public function __construct($reason) {
		parent::__construct($reason);
	}
}

try {
	$reader = new ScanReader(new Report('/Users/amekkawi/Sites/git/diskusage-data/v2test/'));
	$reader->read('/Users/amekkawi/Sites/git/diskusage/amekkawi.dat');
}
catch (Exception $e) {
	var_dump($e);
}
