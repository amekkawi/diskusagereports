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

class RangeLookup implements ISaveWatcher {

	public $ranges = array();

	public function onSave($index, $sortIndex, $firstItem, $lastItem, $path) {
		$range = array(
			$sortIndex === null ? $firstItem[0] : $firstItem[0][$sortIndex],
			$sortIndex === null ? $lastItem[0] : $lastItem[0][$sortIndex],
			$index
		);

		if ($sortIndex !== null)
			$this->ranges[$sortIndex][] = $range;
		else
			$this->ranges[] = $range;
	}
}

class Report {

	/**
	 * @var Options
	 */
	public $options;

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

	public $combinedOutput;

	/**
	 * @var LargeMap
	 */
	public $fileListMap;

	public $outFiles = 0;
	public $outSize = 0;

	protected $maxDirListSize;

	public function __construct(Options $options) {
		$this->options = $options;

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

		$this->subDirMap = new LargeMap(new CollectionOutput($this), 10 * 1024, 5 * 1024);
		$this->subDirMap->prefix = 'subdirsmap';

		$this->fileListOutputs = array(
			new MultiSortOutput($this, 0, 'name'),
			new MultiSortOutput($this, 1, 'size'),
			new MultiSortOutput($this, 2, 'date')
		);

		$this->combinedOutput = new SingleSortOutput($this);

		$this->fileListMap = new LargeMap(new CollectionOutput($this), 80 * 1024, 40 * 1024);
		$this->fileListMap->prefix = 'filesmap';
	}

	public function buildPath($extension) {
		return $this->options->buildPath($extension);
	}
}
