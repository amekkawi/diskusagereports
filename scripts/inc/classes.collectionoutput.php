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

class CollectionOutput implements ICollectionIO {

	/**
	 * @var $report Report
	 */
	protected $report;

	/**
	 * @var $saveHandler ISaveWatcher
	 */
	protected $saveHandler;

	public function __construct($report, ISaveWatcher $saveHandler = null) {
		$this->report = $report;
		$this->saveHandler = $saveHandler;
	}

	public function openFile($prefix, $index, $suffix, $mode = 'w') {
		return new FileStream($this->report->buildPath($prefix . '_' . $index . $suffix), $mode);
	}

	public function deleteFile($prefix, $index, $suffix) {
		return unlink($this->report->buildPath($prefix . '_' . $index . $suffix));
	}

	public function renameTo($fromPath, $prefix, $index, $suffix) {
		return rename($fromPath, $this->report->buildPath($prefix . '_' . $index . $suffix));
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

class SingleSortOutput extends CollectionOutput implements ICollectionOutput {

	public function compare($a, $b) {
		if ($a[0] < $b[0])
			return -1;
		if ($a[0] > $b[0])
			return 1;
		return 0;
	}
}

class MultiSortOutput implements ICollectionOutput {

	/**
	 * @var $report Report
	 */
	protected $report;

	/**
	 * @var $saveHandler ISaveWatcher
	 */
	protected $saveHandler;

	protected $sortIndex;
	protected $sortName;
	protected $reverseSort;

	public function __construct($report, $sortIndex, $sortName, ISaveWatcher $saveHandler = null, $reverseSort = false) {
		$this->report = $report;
		$this->sortIndex = $sortIndex;
		$this->sortName = $sortName;
		$this->saveHandler = $saveHandler;
		$this->reverseSort = $reverseSort;
	}

	public function openFile($prefix, $index, $suffix, $mode) {
		return new FileStream($this->report->buildPath($prefix . '_' . $this->sortName . '_' . $index . $suffix), $mode);
	}

	public function deleteFile($prefix, $index, $suffix) {
		return unlink($this->report->buildPath($prefix . '_' . $this->sortName . '_' . $index . $suffix));
	}

	public function renameTo($fromPath, $prefix, $index, $suffix) {
		return rename($fromPath, $this->report->buildPath($prefix . '_' . $this->sortName . '_' . $index . $suffix));
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
