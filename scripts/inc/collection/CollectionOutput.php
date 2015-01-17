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
	 * @var Report
	 */
	protected $report;

	/**
	 * @var ISaveWatcher
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

			if (Logger::doLevel(Logger::LEVEL_VERY_VERBOSE))
				Logger::log('Saved file ' . basename($path) . " at $size bytes.", Logger::LEVEL_VERY_VERBOSE);
		}
		if ($this->saveHandler !== null)
			$this->saveHandler->onSave($index, null, $firstItem, $lastItem, $path);
	}
}

