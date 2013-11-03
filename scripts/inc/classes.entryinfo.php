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

	protected $report;

	/**
	 * @var LargeCollection
	 */
	protected $dirList;

	/**
	 * @var LargeCollection
	 */
	protected $fileList;

	/**
	 * @var int
	 */
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
			'maxLength' => 200,
			'combinedOutput' => $report->combinedOutput
		));
		$this->fileList = new LargeCollection($report->fileListOutputs, array(
			'maxLength' => 200,
			'combinedOutput' => $report->combinedOutput
		));
	}

	public function setFromOptions(Options $options) {
		$this->type = 'd';
		$this->path = '';
		$this->dirname = '';
		$this->basename = $options->getBasename() === null || $options->getBasename() == '' ? '.' : $options->getBasename();
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
