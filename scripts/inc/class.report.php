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
	protected $subRanges;

	function __construct($subRanges = 0) {
		$this->subRanges = $subRanges;
		if ($subRanges > 0)
			$this->ranges = array_fill(0, $subRanges, array());
	}

	public function onSave($index, $sortIndex, $firstItem, $lastItem, $path) {
		$range = array(
			$sortIndex === null ? $firstItem[0] : $firstItem[0][$sortIndex],
			$sortIndex === null ? $lastItem[0] : $lastItem[0][$sortIndex],
			$index
		);

		if ($sortIndex !== null) {
			$this->ranges[$sortIndex][] = $range;
		}
		else
			$this->ranges[] = $range;
	}

	public function getReduced() {
		if ($this->subRanges <= 0)
			return $this->reduce($this->ranges);

		$ret = array();
		foreach ($this->ranges as $ranges) {
			$ret[] = $this->reduce($ranges);
		}

		return $ret;
	}

	protected function reduce($ranges) {
		$ret = array();
		for ($ri = 0, $rl = count($ranges); $ri < $rl; $ri++) {
			if (!is_string($ranges[$ri][0]) || !is_string($ranges[$ri][1]))
				return $ranges;

			$range = $ranges[$ri];
			$prevEnd = $ri == 0 ? '' : $ranges[$ri-1][1];
			$nextStart = $ri + 1 < $rl ? $ranges[$ri+1][0] : '';

			$jl = max(strlen($prevEnd), strlen($range[0]), strlen($range[1]));
			for ($j = 1; $j <= $jl; $j++) {
				$lastSubstr = substr($prevEnd, 0, $j);
				$startSubstr = substr($range[0], 0, $j);
				$endSubstr = substr($range[1], 0, $j);

				if ($startSubstr !== $lastSubstr && $startSubstr !== $endSubstr)
					break;
			}

			$kl = max(strlen($nextStart), strlen($range[1]));
			for ($k = $j; $k <= $kl; $k++) {
				$nextSubstr = substr($nextStart, 0, $k);
				$endSubstr = substr($range[1], 0, $k);

				if ($endSubstr !== $nextSubstr)
					break;
			}

			if ($this->subRanges > 0) {
			//echo "\n" . $range[0] . ' -> ' . $startSubstr . "\n";
			//echo $range[1] . ' -> ' . $endSubstr . "\n";
			}

			$ret[] = array($startSubstr, $endSubstr, $range[2]);
		}

		return $ret;
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
	protected $directoryLookup;

	/**
	 * @var LargeCollection
	 */
	protected $directoryList;

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

	/**
	 * @var LargeMap
	 */
	public $topListMap;

	/**
	 * @var LargeMap
	 */
	public $fileSizesMap;

	/**
	 * @var LargeMap
	 */
	public $modifiedDatesMap;

	public $outFiles = 0;
	public $outSize = 0;

	/**
	 * @var DirInfo
	 */
	protected $currentDirInfo = null;

	protected $headerAllowed = true;

	public function __construct(Options $options) {
		$this->options = $options;

		$this->directoryLookup = new RangeLookup();

		$this->directoryList = new LargeCollection(array(
			new SingleSortOutput($this, $this->directoryLookup)
		), array(
			'prefix' => 'dirmap',
			'maxSize' => $this->options->getMaxDirMapKB() * 1024,
			'asObject' => true,
			'suffix' => $this->options->getSuffix(),
			'maxTempSize' => $this->options->getMaxTempKB() * 1024
		));

		$this->subDirOutputs = array(
			new MultiSortOutput($this, 0, 'name'),
			new MultiSortOutput($this, 1, 'size'),
			new MultiSortOutput($this, 2, 'count'),
			new MultiSortOutput($this, 3, 'dirs')
		);

		$this->subDirMap = new LargeMap(new CollectionOutput($this), $this->options->getMaxSubDirsMapKB() * 1024, intval(floor($this->options->getMaxSubDirsMapKB() / 2)) * 1024);
		$this->subDirMap->prefix = 'subdirsmap';

		$this->fileListOutputs = array(
			new MultiSortOutput($this, 0, 'name'),
			new MultiSortOutput($this, 1, 'size'),
			new MultiSortOutput($this, 2, 'date')
		);

		$this->combinedOutput = new SingleSortOutput($this);

		$this->fileListMap = new LargeMap(new CollectionOutput($this), $this->options->getMaxFileListMapKB() * 1024, intval(floor($this->options->getMaxFileListMapKB() / 2)) * 1024);
		$this->fileListMap->prefix = 'filesmap';

		$this->topListMap = new LargeMap(new CollectionOutput($this), $this->options->getMaxFileListMapKB() * 1024, intval(floor($this->options->getMaxFileListMapKB() / 2)) * 1024);
		$this->topListMap->prefix = 'topmap';

		$this->fileSizesMap = new LargeMap(new CollectionOutput($this), $this->options->getMaxFileListMapKB() * 1024, intval(floor($this->options->getMaxFileListMapKB() / 2)) * 1024);
		$this->fileSizesMap->prefix = 'filesizes';

		$this->modifiedDatesMap = new LargeMap(new CollectionOutput($this), $this->options->getMaxFileListMapKB() * 1024, intval(floor($this->options->getMaxFileListMapKB() / 2)) * 1024);
		$this->modifiedDatesMap->prefix = 'modifieddates';
	}

	public function buildPath($extension) {
		return $this->options->buildPath($extension);
	}

	public function processHeader($line) {
		if (!$this->headerAllowed)
			return false;

		$this->options->processHeader($line);
		$this->currentDirInfo = new DirInfo($this);
		$this->currentDirInfo->init();
		$this->headerAllowed = false;
		return true;
	}

	public function processDirInfo(DirInfo $dirInfo) {
		if ($this->headerAllowed) {
			$this->currentDirInfo = new DirInfo($this);
			$this->currentDirInfo->init();
			$this->headerAllowed = false;
		}

		$this->popParents($dirInfo);

		$dirInfo->setParent($this->currentDirInfo);
		$dirInfo->init();

		$this->currentDirInfo = $dirInfo;

		if (Logger::doLevel(Logger::LEVEL_DEBUG2))
			Logger::log("Entering dir: {$this->currentDirInfo->path}", Logger::LEVEL_DEBUG2);
	}

	public function processFileInfo(FileInfo $fileInfo) {
		if ($this->headerAllowed) {
			$this->currentDirInfo = new DirInfo($this);
			$this->currentDirInfo->init();
			$this->headerAllowed = false;
		}

		if (Logger::doLevel(Logger::LEVEL_DEBUG3))
			Logger::log("File: {$fileInfo->path}", Logger::LEVEL_DEBUG3);

		$this->popParents($fileInfo);

		$fileInfo->setParent($this->currentDirInfo);
		$fileInfo->init();

		$this->currentDirInfo->processFileInfo($fileInfo);
	}

	public function save() {
		if ($this->headerAllowed) {
			$this->currentDirInfo = new DirInfo($this);
			$this->currentDirInfo->init();
			$this->headerAllowed = false;
		}

		// Process any remaining directories.
		do {
			if (Logger::doLevel(Logger::LEVEL_DEBUG3))
				Logger::log("Popping dir: {$this->currentDirInfo->path}", Logger::LEVEL_DEBUG3);

			$popDir = $this->currentDirInfo;

			$popDir->onPop();

			if ($this->currentDirInfo->getParent() !== null) {
				$this->currentDirInfo = $this->currentDirInfo->getParent();
				$this->currentDirInfo->onChildPop($popDir);
			}
		} while ($this->currentDirInfo->getParent() !== null);

		// Save any open maps.
		$this->subDirMap->save();
		$this->fileListMap->save();
		$this->topListMap->save();
		$this->fileSizesMap->save();
		$this->modifiedDatesMap->save();

		$startDirLists = microtime(true);
		if (Logger::doLevel(Logger::LEVEL_VERBOSE)) {
			Logger::log("Saved directory files...", Logger::LEVEL_VERBOSE);
		}

		// Save the directory list.
		$this->directoryList->save();

		if (Logger::doLevel(Logger::LEVEL_VERBOSE))
			Logger::log("Saved directory files. Took " . sprintf('%.2f', microtime(true) - $startDirLists) . " sec", Logger::LEVEL_VERBOSE);

		if (Logger::doLevel(Logger::LEVEL_VERBOSE))
			Logger::log("Saving dir lookup...", Logger::LEVEL_VERBOSE);

		// Save the directory lookup
		$lookupSize = file_put_contents($this->buildPath('dirmap_lookup' . $this->options->getSuffix()), json_encode($this->directoryLookup->getReduced()));
		if ($lookupSize === false)
			throw new ScanException('Failed to write dirmap_lookup' . $this->options->getSuffix() . '.');

		$this->outFiles++;
		$this->outSize += $lookupSize;
	}

	protected function popParents(FileInfo $fileInfo) {
		while ($this->currentDirInfo->path != $fileInfo->dirname) {

			if ($this->currentDirInfo->getParent() === null)
				throw new ScanException(ScanException::POPDIR_NOPARENT);

			if (Logger::doLevel(Logger::LEVEL_DEBUG3))
				Logger::log("Popping dir: {$this->currentDirInfo->path}", Logger::LEVEL_DEBUG3);

			$popDir = $this->currentDirInfo;
			$this->currentDirInfo = $this->currentDirInfo->getParent();

			$popDir->onPop();
			$this->directoryList->add($popDir->hash, json_encode($popDir->hash) . ":" . $popDir->toJSON());

			$this->currentDirInfo->onChildPop($popDir);
		}
	}
}
