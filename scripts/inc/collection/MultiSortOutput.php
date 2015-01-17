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

class MultiSortOutput implements ICollectionOutput {

	/**
	 * @var Report
	 */
	protected $report;

	/**
	 * @var ISaveWatcher
	 */
	protected $saveHandler;

	protected $sortIndex;
	protected $sortName;
	protected $reverseSort;

	protected $secondarySortIndexes;
	protected $reverseSecondarySortIndexes;

	public function __construct($report, $sortIndex, $sortName, array $options = array(), ISaveWatcher $saveHandler = null) {
		$this->report = $report;
		$this->sortIndex = $sortIndex;
		$this->sortName = $sortName;
		$this->reverseSort = !empty($options['reverseSort']);

		$this->secondarySortIndexes = isset($options['secondarySortIndexes']) ? $options['secondarySortIndexes'] : null;
		$this->reverseSecondarySortIndexes = isset($options['reverseSecondarySortIndexes']) ? $options['reverseSecondarySortIndexes'] : $this->reverseSort;

		$this->saveHandler = $saveHandler;
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

		if (isset($this->secondarySortIndexes)) {
			foreach ($this->secondarySortIndexes as $secondarySortIndex) {
				if ($a[0][$secondarySortIndex] < $b[0][$secondarySortIndex])
					return $this->reverseSecondarySortIndexes ? 1 : -1;
				if ($a[0][$secondarySortIndex] > $b[0][$secondarySortIndex])
					return $this->reverseSecondarySortIndexes ? -1 : 1;
			}
		}

		return 0;
	}

	public function onSave($index, $firstItem, $lastItem, $size, $path) {
		if ($size !== false) {
			$this->report->outFiles++;
			$this->report->outSize += $size;

			if (Logger::doLevel(Logger::LEVEL_VERY_VERBOSE))
				Logger::log('Saved file ' . basename($path) . " at $size bytes.", Logger::LEVEL_VERY_VERBOSE);
		}
		if ($this->saveHandler !== null)
			$this->saveHandler->onSave($index, $this->sortIndex, $firstItem, $lastItem, $path);
	}
}
