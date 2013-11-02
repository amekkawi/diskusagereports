<?php

require("inc/interfaces.php");
require("inc/class.util.php");
require("inc/class.options.php");
require("inc/class.largemap.php");
require("inc/class.largecollection.php");
require("inc/class.filestream.php");
require("inc/class.fileiterator.php");
require("inc/class.multifilesorter.php");

ini_set('display_errors', 1);
error_reporting(E_ALL);

interface SortOutputSaveHandler {
	public function onSave($index, $sortIndex, $firstItem, $lastItem, $path);
}

class SingleSortOutput implements CollectionOutput {

	/**
	 * @var $report Report
	 */
	protected $report;

	/**
	 * @var $saveHandler SortOutputSaveHandler
	 */
	protected $saveHandler;

	public function __construct($report, SortOutputSaveHandler $saveHandler = null) {
		$this->report = $report;
		$this->saveHandler = $saveHandler;
	}

	public function openFile($prefix, $index, $ext, $mode = 'w') {
		return new FileStream($this->report->buildPath($prefix . '_' . $index . '.' . $ext), $mode);
	}

	public function deleteFile($prefix, $index, $ext) {
		return unlink($this->report->buildPath($prefix . '_' . $index . '.' . $ext));
	}

	public function renameTo($fromPath, $prefix, $index, $ext) {
		return rename($fromPath, $this->report->buildPath($prefix . '_' . $index . '.' . $ext));
	}

	public function compare($a, $b) {
		if ($a[0] < $b[0])
			return -1;
		if ($a[0] > $b[0])
			return 1;
		return 0;
	}

	public function onSave($index, $firstItem, $lastItem, $size, $path) {
		if ($size !== false) {
			$this->report->outFiles++;
			$this->report->outSize += $size;
		}
		if ($this->saveHandler !== null)
			$this->saveHandler->onSave($index, null, $firstItem, $lastItem, $path);
	}
}

class MultiSortOutput implements CollectionOutput {

	/**
	 * @var $report Report
	 */
	protected $report;

	/**
	 * @var $saveHandler SortOutputSaveHandler
	 */
	protected $saveHandler;

	protected $sortIndex;
	protected $sortName;
	protected $reverseSort;

	public function __construct($report, $sortIndex, $sortName, SortOutputSaveHandler $saveHandler = null, $reverseSort = false) {
		$this->report = $report;
		$this->sortIndex = $sortIndex;
		$this->sortName = $sortName;
		$this->saveHandler = $saveHandler;
		$this->reverseSort = $reverseSort;
	}

	public function openFile($prefix, $index, $ext, $mode) {
		return new FileStream($this->report->buildPath($prefix . '_' . $this->sortName . '_' . $index . '.' . $ext), $mode);
	}

	public function deleteFile($prefix, $index, $ext) {
		return unlink($this->report->buildPath($prefix . '_' . $this->sortName . '_' . $index . '.' . $ext));
	}

	public function renameTo($fromPath, $prefix, $index, $ext) {
		return rename($fromPath, $this->report->buildPath($prefix . '_' . $this->sortName . '_' . $index . '.' . $ext));
	}

	public function compare($a, $b) {
		$sortIndex = $this->sortIndex;
		if ($a[0][$sortIndex] < $b[0][$sortIndex])
			return $this->reverseSort ? 1 : -1;
		if ($a[0][$sortIndex] > $b[0][$sortIndex])
			return $this->reverseSort ? -1 : 1;
		return 0;
	}

	public function onSave($index, $firstItem, $lastItem, $size, $path) {
		if ($size !== false) {
			$this->report->outFiles++;
			$this->report->outSize += $size;
		}
		if ($this->saveHandler !== null)
			$this->saveHandler->onSave($index, $this->sortIndex, $firstItem, $lastItem, $path);
	}
}

class DirInfo extends FileInfo {

	protected $report;
	protected $dirList;
	protected $fileList;
	protected $maxInlineSize;

	/**
	 * @var $parent DirInfo
	 */
	public $parent = null;

	public $subDirCount = 0;

	public $directFileCount = 0;
	public $subFileCount = 0;

	public $directSize = 0;
	public $subSize = 0;

	public $parents = array();
	public $dirs;
	public $files;

	function __construct(Report $report) {
		$this->report = $report;
		$this->maxInlineSize = 1024;
		$this->dirList = new LargeCollection($report->subDirOutputs, array(
			'maxLength' => 200
		));
		$this->fileList = new LargeCollection($report->fileListOutputs, array(
			'maxLength' => 900
		));
	}

	public function setFromOptions(Options $options) {
		$this->type = 'd';
		$this->path = '';
		$this->dirname = '';
		$this->basename = $options->basename === null || $options->basename == '' ? '.' : $options->basename;
		$this->hash = md5('');
		$this->dirList->setKey($this->hash);
		$this->dirList->prefix = 'subdirs_' . $this->hash;
		$this->fileList->setKey($this->hash);
		$this->fileList->prefix = 'files_' . $this->hash;
	}

	public function setFromLine(Options $options, $line) {
		parent::setFromLine($options, $line);
		$this->dirList->setKey($this->hash);
		$this->dirList->prefix = 'subdirs_' . $this->hash;
		$this->fileList->setKey($this->hash);
		$this->fileList->prefix = 'files_' . $this->hash;
	}

	public function onPop() {
		$parent = $this->parent;
		while ($parent !== null) {
			$this->parents[] = array(
				$parent->basename,
				$parent->hash
			);
			$parent = $parent->parent;
		}

		$reportListMap = $this->report->fileListMap;
		$fileList = $this->fileList;

		// Multi-part lists must always be saved.
		if ($fileList->isMultiPart()) {
			$this->files = json_encode($fileList->save());
		}

		// If it is small enough, store it with the directory entry.
		elseif ($fileList->getSize() < 100) {
			$this->files = $fileList->toJSON();
		}

		// Attempt to store it in the map.
		elseif (($this->files = $reportListMap->add($fileList)) !== false) {
			$this->files = json_encode($this->files);
		}

		// Otherwise, force it to save.
		else {
			$this->files = json_encode($fileList->save());
		}

		$subDirsMap = $this->report->subDirMap;
		$dirsList = $this->dirList;

		// Multi-part lists must always be saved.
		if ($dirsList->isMultiPart()) {
			$this->dirs = json_encode($dirsList->save());
		}

		// If it is small enough, store it with the directory entry.
		elseif ($dirsList->getSize() < 100) {
			$this->dirs = $dirsList->toJSON();
		}

		// Attempt to store it in the map.
		elseif (($this->dirs = $subDirsMap->add($dirsList)) !== false) {
			$this->dirs = json_encode($this->dirs);
		}

		// Otherwise, force it to save.
		else {
			$this->dirs = json_encode($dirsList->save());
		}
	}

	public function onChildPop(DirInfo $dirInfo) {
		$this->subDirCount += $dirInfo->subDirCount + 1;
		$this->subSize += $dirInfo->directSize + $dirInfo->subSize;
		$this->subFileCount += $dirInfo->directFileCount + $dirInfo->subFileCount;

		$this->dirList->add(array(
			$dirInfo->basename,
			$dirInfo->directSize + $dirInfo->subSize,
			$dirInfo->directFileCount + $dirInfo->subFileCount,
			$dirInfo->subDirCount
		), $dirInfo->toSubdirJSON());
	}

	public function processFileInfo(FileInfo $fileInfo) {
		$this->directFileCount++;
		$this->directSize += $fileInfo->size;

		$this->fileList->add(array(
			$fileInfo->basename,
			$fileInfo->size,
			$fileInfo->date . ' ' . $fileInfo->time
		), $fileInfo->toJSON());
	}

	public function toJSON() {
		return '{'
		. '"n":' . $this->getEncodedBasename()
		. ',"d":' . json_encode($this->subDirCount)
		. ',"F":' . json_encode($this->directFileCount)
		. ',"f":' . json_encode($this->subFileCount)
		. ',"S":' . json_encode($this->directSize)
		. ',"s":' . json_encode($this->subSize)
		. ',"L":' . $this->dirs
		. ',"l":' . $this->files
		. ',"p":' . json_encode($this->parents)
		. '}';
	}

	public function toSubdirJSON() {
		return '{'
		. '"h":' . json_encode($this->hash)
		. ',"n":' . $this->getEncodedBasename()
		. ',"d":' . json_encode($this->subDirCount)
		. ',"f":' . json_encode($this->directFileCount + $this->subFileCount)
		. ',"s":' . json_encode($this->directSize + $this->subSize)
		. '}';
	}

	public function toMinimalJSON() {
		return '['
		. json_encode($this->hash)
		. ',' . $this->getEncodedBasename()
		. ']';
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

	protected function getEncodedBasename() {
		$basename = @json_encode($this->basename);

		// Attempt to convert it from Windows-1252 to UTF, if the json_encode failed.
		if ($basename === 'null') {
			$basename = iconv('Windows-1252', 'UTF-8//IGNORE', $this->basename);

			// Convert the name to a byte array if all else fails.
			if ($basename === false)
				$basename = array_values(unpack('C*', $this->basename));
		}

		return $basename;
	}

	public function toJSON() {
		return
			'['
			. json_encode($this->type)
			. ',' . $this->getEncodedBasename()
			. ',' . json_encode($this->size)
			. ',' . json_encode($this->date)
			. ',' . json_encode($this->time)
			. ']';
	}
}

class ScanReader {

	const DEBUG = false;

	/**
	 * @var Report
	 */
	protected $report;

	public function __construct(Report $report) {
		$this->report = $report;
	}

	public function read($filename) {

		// Attempt to open the file list.
		try {
			$stream = new FileStream($filename, 'r');
		}
		catch (IOException $e) {
			throw new ScanException(ScanException::FOPEN_FAIL);
		}

		$options = new Options();
		$iterator = new FileIterator($stream);
		$fileInfo = new FileInfo();
		$dirList = $this->report->directoryList;

		$currentDir = new DirInfo($this->report);
		$currentDir->setFromOptions($options);

		$headerAllowed = true;

		$progressLastReport = time();
		$progressLastLines = 0;
		$progressLastBytes = 0;
		$progressLastOutFiles = 0;
		$progressLastOutSize = 0;

		foreach ($iterator as $lineNum => $line) {

			if (time() - $progressLastReport >= 3) {
				if ($iterator->length() !== null) {
					$progressPercent = floor($iterator->position() / $iterator->length() * 1000) / 10;
					echo sprintf('%4.1f', $progressPercent) . "%: ";
				}

				echo "Processed " . Util::FormatNumber($lineNum - $progressLastLines) . " lines from " . Util::FormatBytes($iterator->position() - $progressLastBytes) . ". Wrote " . Util::FormatBytes($this->report->outSize - $progressLastOutSize) . " to " . Util::FormatNumber($this->report->outFiles - $progressLastOutFiles) . " files.\n";
				$progressLastReport = time();
				$progressLastBytes = $iterator->position();
				$progressLastOutFiles = $this->report->outFiles;
				$progressLastLines = $lineNum;
				$progressLastOutSize = $this->report->outSize;
			}

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
					$newDir = new DirInfo($this->report);
					$newDir->setFromLine($options, $line);

					while ($currentDir->path != $newDir->dirname) {

						if ($currentDir->parent === null)
							throw new ScanException(ScanException::POPDIR_NOPARENT);

						if (self::DEBUG)
							echo "Popping dir: {$currentDir->path}\n";

						$popDir = $currentDir;
						$currentDir = $currentDir->parent;

						$popDir->onPop();
						$dirList->add($popDir->hash, json_encode($popDir->hash) . ":" . $popDir->toJSON());

						$currentDir->onChildPop($popDir);
					}

					$newDir->parent = $currentDir;
					$currentDir = $newDir;

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
						$dirList->add($popDir->hash, json_encode($popDir->hash) . ":" . $popDir->toJSON());

						$currentDir->onChildPop($popDir);
					}

					$currentDir->processFileInfo($fileInfo);
				}
			}
			catch (LineException $e) {
				echo "LineException on line $lineNum: " . $e->getMessage() . "\n";
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

		// Save any open maps.
		$this->report->subDirMap->save();
		$this->report->fileListMap->save();

		// Save the directory list.
		$startDirLists = microtime(true);
		echo "Saving dir lists...\n";
		$dirList->save();
		echo "Took " . sprintf('%.2f', microtime(true) - $startDirLists) . " sec\n";

		// Save the directory lookup
		echo "Saving dir lookup...\n";
		$lookupSize = file_put_contents($this->report->buildPath('dirmap_lookup.dat'), json_encode($this->report->directoryLookup->ranges));
		if ($lookupSize === false)
			throw new ScanException("Failed to write dirmap_lookup.dat.");
		$this->report->outFiles++;
		$this->report->outSize += $lookupSize;

		echo "Complete! Processed " . Util::FormatNumber($iterator->key()) . " lines from " . Util::FormatBytes($iterator->position()) . ". Wrote " . Util::FormatBytes($this->report->outSize) . " in " . Util::FormatNumber($this->report->outFiles) . " files.\n";

		$stream->close();
	}
}

class ReportMapOutput implements MapOutput {

	/**
	 * @var $report Report
	 */
	protected $report;

	protected $maxPerOut;
	protected $maxPerItem;

	public function __construct($report, $maxPerOut, $maxPerItem) {
		$this->report = $report;
		$this->maxPerOut = $maxPerOut;
		$this->maxPerItem = $maxPerItem;
	}

	public function openFile($prefix, $index, $ext, $mode) {
		return new FileStream($this->report->buildPath($prefix . '_' . $index . '.' . $ext), $mode);
	}

	public function getMaxPerOut() {
		return $this->maxPerOut;
	}

	public function getMaxPerItem() {
		return $this->maxPerItem;
	}

	public function onSave($index, $size, $path) {
		$this->report->outFiles++;
		$this->report->outSize += $size;
	}
}

class RangeLookup implements SortOutputSaveHandler {

	public $ranges = array();

	public function onSave($index, $sortIndex, $firstItem, $lastItem, $path) {
		$this->ranges[] = array(
			$sortIndex === null ? $firstItem[0] : $firstItem[0][$sortIndex],
			$sortIndex === null ? $lastItem[0] : $lastItem[0][$sortIndex],
			$index
		);
	}
}

class Report {

	/**
	 * @var string
	 */
	public $directory;

	/**
	 * @var RangeLookup
	 */
	public $directoryLookup;

	/**
	 * @var LargeCollection
	 */
	public $directoryList;

	/**
	 * @var LargeMap
	 */
	public $subDirMap;

	public $fileListOutputs;

	/**
	 * @var LargeMap
	 */
	public $fileListMap;

	public $outFiles = 0;
	public $outSize = 0;

	protected $maxDirListSize;

	public function __construct($directory) {
		$this->directory = rtrim(realpath($directory), DIRECTORY_SEPARATOR);

		$this->directoryLookup = new RangeLookup();

		$this->maxDirListSize = 40 * 1024;

		$this->directoryList = new LargeCollection(array(
			new SingleSortOutput($this, $this->directoryLookup)
		), array(
			'prefix' => 'dirmap',
			'maxSize' => $this->maxDirListSize,
			'asObject' => true
		));

		$this->subDirOutputs = array(
			new MultiSortOutput($this, 0, 'name'),
			new MultiSortOutput($this, 1, 'size'),
			new MultiSortOutput($this, 2, 'count'),
			new MultiSortOutput($this, 3, 'dirs')
		);

		$this->subDirMap = new LargeMap(new ReportMapOutput($this, 10 * 1024, 5 * 1024));
		$this->subDirMap->prefix = 'subdirsmap';

		$this->fileListOutputs = array(
			new MultiSortOutput($this, 0, 'name'),
			new MultiSortOutput($this, 1, 'size'),
			new MultiSortOutput($this, 2, 'date')
		);

		$this->fileListMap = new LargeMap(new ReportMapOutput($this, 80 * 1024, 40 * 1024));
		$this->fileListMap->prefix = 'filesmap';
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

	/**
	 * @param string $reason
	 * @param string $line
	 * @param int    $column
	 */
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
	echo "\n" . get_class($e) . ": " . $e->getMessage() . "\n";
	echo $e->getTraceAsString()."\n";
}
