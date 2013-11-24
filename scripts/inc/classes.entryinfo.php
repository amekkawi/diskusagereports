<?php
/*
 * Copyright (c) 2013 André Mekkawi <license@diskusagereports.com>
 * Version: @@SourceVersion
 *
 * LICENSE
 *
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */

class DirInfo extends FileInfo {

	/**
	 * @var LargeCollection
	 */
	protected $dirList;

	/**
	 * @var null|LargeCollection
	 */
	protected $fileList = null;

	/**
	 * @var null|TopList
	 */
	protected $topList = null;

	/**
	 * @var bool
	 */
	protected $isOwnTopList = false;

	/**
	 * @var null|GroupBySizeList
	 */
	protected $fileSizesList = null;

	/**
	 * @var bool
	 */
	protected $isOwnFileSizesList = false;

	/**
	 * @var null|GroupBySizeList
	 */
	protected $modifiedDatesList = null;

	/**
	 * @var bool
	 */
	protected $isOwnModifiedDatesList = false;

	/**
	 * @var array All parent DirInfo.
	 */
	protected $parents = array();

	/**
	 * @var int
	 */
	protected $depth = 0;

	/**
	 * @var RangeLookup
	 */
	protected $subDirLookup;

	public $subDirCount = 0;

	public $directFileCount = 0;
	public $subFileCount = 0;

	public $directSize = 0;
	public $subSize = 0;
	public $dirs;
	public $files;
	public $top;
	public $fileSizes;
	public $modifiedDates;

	function __construct(Report $report, $line = null) {
		parent::__construct($report);

		$options = $report->options;

		$this->type = 'd';
		$this->path = '';
		$this->dirname = '';
		$this->hash = md5('');

		$basename = $options->getBasename();
		$this->basename = $basename === null || $basename == '' ? '.' : $basename;

		if (is_string($line))
			$this->setFromLine($line);
	}

	public function init() {
		$report = $this->report;
		$options = $this->options;

		$this->subDirLookup = new RangeLookup(4);
		$this->dirList = new LargeCollection($report->subDirOutputs, array(
			'maxLength' => $options->getMaxSubDirsFilePages() * $options->getMaxPerPage(),
			'combinedOutput' => $report->combinedOutput,
			'key' => $this->hash,
			'prefix' => 'subdirs_' . $this->hash,
			'maxTempSize' => $options->getMaxTempKB() * 1024,
			'saveWatcher' => $this->subDirLookup
		));

		$fileListDepth = $options->getFileListDepth();
		if ($fileListDepth === true || (is_int($fileListDepth) && $this->depth <= $fileListDepth)) {
			$this->fileList = new LargeCollection($report->fileListOutputs, array(
				'maxLength' => $options->getMaxFileListFilePages() * $options->getMaxPerPage(),
				'combinedOutput' => $report->combinedOutput,
				'key' => $this->hash,
				'prefix' => 'files_' . $this->hash,
				'maxTempSize' => $options->getMaxTempKB() * 1024
			));
		}

		$topListDepth = $options->getTopListDepth();
		if ($topListDepth === true || (is_int($topListDepth) && $this->depth <= $topListDepth)) {
			$this->topList = new TopList();
			$this->topList->setKey($this->hash);
			$this->isOwnTopList = true;
		}
		elseif (is_int($topListDepth)) {
			$this->topList = $this->parents[$topListDepth]->topList;
			$this->isOwnTopList = false;
		}

		$fileSizesDepth = $options->getFileSizesDepth();
		if ($fileSizesDepth === true || (is_int($fileSizesDepth) && $this->depth <= $fileSizesDepth)) {
			$this->fileSizesList = new GroupBySizeList($this->options->sizeGroups);
			$this->fileSizesList->setKey($this->hash);
			$this->isOwnFileSizesList = true;
		}
		elseif (is_int($fileSizesDepth)) {
			$this->fileSizesList = $this->parents[$fileSizesDepth]->fileSizesList;
			$this->isOwnFileSizesList = false;
		}

		$modifiedDatesDepth = $options->getModifiedDatesDepth();
		if ($modifiedDatesDepth === true || (is_int($modifiedDatesDepth) && $this->depth <= $modifiedDatesDepth)) {
			$this->modifiedDatesList = new GroupBySizeList($this->options->sizeGroups);
			$this->modifiedDatesList->setKey($this->hash);
			$this->isOwnModifiedDatesList = true;
		}
		elseif (is_int($modifiedDatesDepth)) {
			$this->modifiedDatesList = $this->parents[$modifiedDatesDepth]->modifiedDatesList;
			$this->isOwnModifiedDatesList = false;
		}
	}

	public function onPop() {
		if ($this->fileList !== null) {
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
		}

		if ($this->isOwnTopList && $this->topList !== null) {
			$topListMap = $this->report->topListMap;
			/** @var $topList TopList */
			$topList = $this->topList;

			// If it is small enough, store it with the directory entry.
			if ($topList->getSize() < 100) {
				//echo "Storing top list (" . $topList->getSize() . ") with dir..\n";
				$this->top = $topList->toJSON();
			}

			// Attempt to store it in the map.
			elseif (($this->top = $topListMap->add($topList)) !== false) {
				//echo "Saving top list to map..\n";
				$this->top = json_encode($this->top);
			}

			// Otherwise, force it to save.
			else {
				echo "Saving top list to file..\n";
				// TODO: This will throw an error.
				$this->top = json_encode($topList->save());
			}
		}

		if ($this->isOwnFileSizesList && $this->fileSizesList !== null) {
			$fileSizesMap = $this->report->fileSizesMap;
			/** @var $fileSizesList GroupBySizeList */
			$fileSizesList = $this->fileSizesList;

			// If it is small enough, store it with the directory entry.
			if ($fileSizesList->getSize() < 100) {
				//echo "A $this->path\n";
				$this->fileSizes = $fileSizesList->toJSON();
			}

			// Attempt to store it in the map.
			elseif (($this->fileSizes = $fileSizesMap->add($fileSizesList)) !== false) {
				//echo "B $this->path\n";
				$this->fileSizes = json_encode($this->fileSizes);
			}

			// Otherwise, force it to save.
			else {
				//echo "C $this->path\n";
				$this->fileSizes = json_encode($fileSizesList->save());
			}
		}

		if ($this->isOwnModifiedDatesList && $this->modifiedDatesList !== null) {
			$modifiedDatesMap = $this->report->modifiedDatesMap;
			/** @var $modifiedDatesList GroupByModifiedDates */
			$modifiedDatesList = $this->modifiedDatesList;

			// If it is small enough, store it with the directory entry.
			if ($modifiedDatesList->getSize() < 100) {
				//echo "A $this->path\n";
				$this->modifiedDates = $modifiedDatesList->toJSON();
			}

			// Attempt to store it in the map.
			elseif (($this->modifiedDates = $modifiedDatesMap->add($modifiedDatesList)) !== false) {
				//echo "B $this->path\n";
				$this->modifiedDates = json_encode($this->modifiedDates);
			}

			// Otherwise, force it to save.
			else {
				//echo "C $this->path\n";
				// TODO: This will throw an error.
				$this->modifiedDates = json_encode($modifiedDatesList->save());
			}
		}

		$subDirsMap = $this->report->subDirMap;
		$dirsList = $this->dirList;

		// Multi-part lists must always be saved.
		if ($dirsList->isMultiPart()) {
			$dirsList->save();
			$this->dirs = json_encode($this->subDirLookup->getReduced());
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
			$dirsList->save();
			$this->dirs = json_encode($this->subDirLookup->getReduced());
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

		if ($this->isOwnTopList && $dirInfo->isOwnTopList) {
			$this->topList->merge($dirInfo->topList);
		}

		if ($this->isOwnFileSizesList && $dirInfo->isOwnFileSizesList) {
			$this->fileSizesList->merge($dirInfo->fileSizesList);
		}

		if ($this->isOwnModifiedDatesList && $dirInfo->isOwnModifiedDatesList) {
			$this->modifiedDatesList->merge($dirInfo->modifiedDatesList);
		}
	}

	public function processFileInfo(FileInfo $fileInfo) {
		$this->directFileCount++;
		$this->directSize += $fileInfo->size;

		if ($this->fileList !== null) {
			$this->fileList->add(array(
				$fileInfo->basename,
				$fileInfo->size,
				$fileInfo->date . ' ' . $fileInfo->time
			), $fileInfo->toJSON());
		}

		if ($this->topList !== null) {
			$this->topList->add($fileInfo);
		}

		if ($this->fileSizesList !== null) {
			$this->fileSizesList->add($fileInfo);
		}

		if ($this->modifiedDatesList !== null) {
			$this->modifiedDatesList->add($fileInfo);
		}
	}

	public function toJSON() {
		$parents = array();
		/** @var $parent DirInfo */
		foreach ($this->parents as $parent) {
			if ($parent->parent !== null) {
				$parents[] = $parent->toMinimalJSON();
			}
		}

		return '{'
		. '"n":' . $this->getEncodedBasename()
		. ',"d":' . json_encode($this->subDirCount)
		. ',"F":' . json_encode($this->directFileCount)
		. ',"f":' . json_encode($this->subFileCount)
		. ',"S":' . json_encode($this->directSize)
		. ',"s":' . json_encode($this->subSize)
		. ',"L":' . $this->dirs
		. ($this->fileList === null ? '' : ',"l":' . $this->files)
		. ($this->top === null ? '' : ',"t":' . $this->top)
		. ($this->fileSizes === null ? '' : ',"u":' . $this->fileSizes)
		. ($this->modifiedDates === null ? '' : ',"m":' . $this->modifiedDates)
		. ',"p":' . '[' . implode(',', $parents) . ']'
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

	public function setParent(DirInfo $parent) {
		parent::setParent($parent);

		if ($parent !== null) {
			$this->parents = $parent->parents;
			$this->parents[] = $parent;
			$this->depth = count($this->parents);
		}
		else {
			$this->parents = array();
			$this->depth = 0;
		}
	}

	/**
	 * @return array
	 */
	public function getParents() {
		return $this->parents;
	}
}

class FileInfo {

	/**
	 * @var Report
	 */
	protected $report;

	/**
	 * @var Options
	 */
	protected $options;

	/**
	 * @var $parent DirInfo
	 */
	protected $parent = null;

	protected $encodedBasename = null;

	public $type;
	public $date;
	public $time;
	public $size;
	public $path;
	public $dirname;
	public $basename;
	public $hash;

	function __construct(Report $report) {
		$this->report = $report;
		$this->options = $report->options;
	}

	public function setFromLine($line) {
		$options = $this->options;

		if (strlen($line) > $options->getMaxLineLength())
			throw new LineException(LineException::TOO_LONG, $line);

		// Validate the line up to the path column.
		if (!$options->isValidLine($line))
			throw new LineException(LineException::PATTERN_MISMATCH, $line);

		// Split the line and validate its length.
		if (count($split = explode($options->getDelim(), $line, $options->colCount)) != $options->colCount)
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
		$this->encodedBasename = null;
	}

	public function init() {

	}

	public function getEncodedBasename() {
		if ($this->encodedBasename !== null)
			return $this->encodedBasename;

		$basename = @json_encode($this->basename);

		// Attempt to convert it from Windows-1252 to UTF, if the json_encode failed.
		if ($basename === 'null') {
			$basename = iconv('Windows-1252', 'UTF-8//IGNORE', $this->basename);

			// Convert the name to a byte array if all else fails.
			if ($basename === false)
				$basename = array_values(unpack('C*', $this->basename));
		}

		return $this->encodedBasename = $basename;
	}

	/**
	 * @return DirInfo|null
	 */
	public function getParent() {
		return $this->parent;
	}

	public function setParent(DirInfo $parent) {
		$this->parent = $parent;
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
