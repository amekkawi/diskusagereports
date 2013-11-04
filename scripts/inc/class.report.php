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
	}

	public function buildPath($extension) {
		return $this->options->buildPath($extension);
	}

	public function processHeader($line) {
		if (!$this->headerAllowed)
			return false;

		$this->options->processHeader($line);
		$this->currentDirInfo = new DirInfo($this);
		$this->headerAllowed = false;
		return true;
	}

	public function processDirInfo(DirInfo $dirInfo) {
		if ($this->headerAllowed) {
			$this->currentDirInfo = new DirInfo($this);
			$this->headerAllowed = false;
		}

		while ($this->currentDirInfo->path != $dirInfo->dirname) {

			if ($this->currentDirInfo->parent === null)
				throw new ScanException(ScanException::POPDIR_NOPARENT);

			//if (self::DEBUG)
			//	echo "Popping dir: {$this->currentDirInfo->path}\n";

			$popDir = $this->currentDirInfo;
			$this->currentDirInfo = $this->currentDirInfo->parent;

			$popDir->onPop();
			$this->directoryList->add($popDir->hash, json_encode($popDir->hash) . ":" . $popDir->toJSON());

			$this->currentDirInfo->onChildPop($popDir);
		}

		$dirInfo->parent = $this->currentDirInfo;
		$this->currentDirInfo = $dirInfo;

		//if (self::DEBUG)
		//	echo "Entering dir: {$this->currentDirInfo->path}\n";
	}

	public function processFileInfo(FileInfo $fileInfo) {
		if ($this->headerAllowed) {
			$this->currentDirInfo = new DirInfo($this);
			$this->headerAllowed = false;
		}

		//if (self::DEBUG)
		//	echo "    File: {$fileInfo->path}\n";

		while ($this->currentDirInfo->path != $fileInfo->dirname) {

			if ($this->currentDirInfo->parent === null)
				throw new ScanException(ScanException::POPDIR_NOPARENT);

			//if (self::DEBUG)
			//	echo "Popping dir: {$this->currentDirInfo->path}\n";

			$popDir = $this->currentDirInfo;
			$this->currentDirInfo = $this->currentDirInfo->parent;

			$popDir->onPop();
			$this->directoryList->add($popDir->hash, json_encode($popDir->hash) . ":" . $popDir->toJSON());

			$this->currentDirInfo->onChildPop($popDir);
		}

		$this->currentDirInfo->processFileInfo($fileInfo);
	}

	public function save() {
		if ($this->headerAllowed) {
			$this->currentDirInfo = new DirInfo($this);
			$this->headerAllowed = false;
		}

		// Process any remaining directories.
		do {
			//if (self::DEBUG)
			//	echo "Popping dir: {$this->currentDirInfo->path}\n";

			$popDir = $this->currentDirInfo;

			$popDir->onPop();

			if ($this->currentDirInfo->parent !== null) {
				$this->currentDirInfo = $this->currentDirInfo->parent;
				$this->currentDirInfo->onChildPop($popDir);
			}

		} while ($this->currentDirInfo->parent !== null);

		// Save any open maps.
		$this->subDirMap->save();
		$this->fileListMap->save();

		// Save the directory list.
		//$startDirLists = microtime(true);
		//echo "Saving dir lists...\n";
		$this->directoryList->save();
		//echo "Took " . sprintf('%.2f', microtime(true) - $startDirLists) . " sec\n";

		// Save the directory lookup
		//echo "Saving dir lookup...\n";
		$lookupSize = file_put_contents($this->buildPath('dirmap_lookup' . $this->options->getSuffix()), json_encode($this->directoryLookup->ranges));
		if ($lookupSize === false)
			throw new ScanException('Failed to write dirmap_lookup' . $this->options->getSuffix() . '.');

		$this->outFiles++;
		$this->outSize += $lookupSize;
	}
}
