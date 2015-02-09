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

/**
 * Builds a lookup for finding items in sorted collection save files.
 */
class RangeLookup implements ICollectionListener {

	/**
	 * @var array
	 */
	public $ranges = array();

	/**
	 * @var int The number of range groupings.
	 */
	protected $subRanges;

	/**
	 * Construct a RangeLookup.
	 *
	 * @param int $subRanges The number of sort groups that range lookups will be created for. See {@link ISaveWatcher::onSave}'s $sortIndex.
	 */
	function __construct($subRanges = 0) {
		$this->subRanges = $subRanges;
		if ($subRanges > 0)
			$this->ranges = array_fill(0, $subRanges, array());
	}

	/**
	 * @inheritdoc
	 */
	public function onSave($sortIndex, $sortKey, $fileIndex, $length, $size, $firstItem, $lastItem, $path) {
		if (!isset($sortIndex))
			return;

		$range = array(
			$this->subRanges === 0 ? $firstItem[0] : $firstItem[0][$sortIndex],
			$this->subRanges === 0 ? $lastItem[0] : $lastItem[0][$sortIndex],
			$fileIndex,
		);

		if ($this->subRanges === 0) {
			$this->ranges[] = $range;
		}
		else {
			$this->ranges[$sortIndex][] = $range;
		}
	}

	/**
	 * Get the range lookup data with the start/end values reduced only
	 * what is necessary to determine if a value is in a file.
	 *
	 * For example, it changes...
	 *
	 * array(
	 *   array('Apartments', 'Apple', 1),
	 *   array('Apollo', 'Bobbles', 2),
	 *   array('Copper', 'Helix', 3),
	 * )
	 *
	 * ... to ...
	 *
	 * array(
	 *   array('Apa', 'App', 1),
	 *   array('Apo', 'B', 2),
	 *   array('C', 'H', 3),
	 * )
	 *
	 * @return array
	 */
	public function getReduced() {
		if ($this->subRanges <= 0)
			return $this->reduce($this->ranges);

		$ret = array();
		foreach ($this->ranges as $ranges) {
			$ret[] = $this->reduce($ranges);
		}

		return $ret;
	}

	/**
	 * Internal method that reduces a range (or sub-range).
	 *
	 * @param $ranges
	 * @return array
	 */
	protected function reduce($ranges) {
		$ret = array();
		for ($ri = 0, $rl = count($ranges); $ri < $rl; $ri++) {
			if (!is_string($ranges[$ri][0]) || !is_string($ranges[$ri][1]))
				return $ranges;

			$range = $ranges[$ri];
			$prevEnd = $ri == 0 ? '' : $ranges[$ri-1][1];
			$nextStart = $ri + 1 < $rl ? $ranges[$ri+1][0] : '';

			$startSubstr = $range[0];
			$endSubstr = $range[1];

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

/**
 * Generates a report.
 */
class Report implements ICollectionIO, ICollectionListener {

	/**
	 * @var Options
	 */
	public $options;

	/**
	 * @var LargeCollection
	 */
	protected $directoryStore;

	/**
	 * @var IComparator[]
	 */
	public $subDirComparators;

	/**
	 * @var LargeCollection
	 */
	public $subDirStore;

	/**
	 * @var CollectionWriter
	 */
	public $subDirWriter;

	/**
	 * @var IComparator[]
	 */
	public $fileListComparators;

	/**
	 * @var CollectionWriter
	 */
	public $fileListWriter;

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

	/**
	 * @var int Number of report files written to disk.
	 */
	public $outFiles = 0;

	/**
	 * @var int Total size of report files written to disk.
	 */
	public $outSize = 0;

	/**
	 * @var DirInfo The current directory being processed.
	 */
	protected $currentDirInfo = null;

	/**
	 * @var bool Whether or not the header is allowed at this point.
	 */
	protected $headerAllowed = true;

	/**
	 * Constructs a Report.
	 *
	 * @param Options $options
	 */
	public function __construct(Options $options) {
		$this->options = $options;

		// Storage and lookup for directory entries
		$this->directoryStore = new LargeCollection($this, array(
			new SingleComparator(),
		), array(
			'tempPrefix' => 'dirmap',
			'maxBufferSize' => $this->options->getMaxTempKB() * 1024
		));

		// Sub-directory lists
		// ===================================

		$this->subDirComparators = array(
			'name' => new MultiComparator(0),
			'size' => new MultiComparator(1, array('reverseSort' => true, 'secondarySortIndexes' => array(0), 'reverseSecondarySortIndexes' => false)),
			'count' => new MultiComparator(2, array('reverseSort' => true, 'secondarySortIndexes' => array(0), 'reverseSecondarySortIndexes' => false)),
			'dirs' => new MultiComparator(3, array('reverseSort' => true, 'secondarySortIndexes' => array(0), 'reverseSecondarySortIndexes' => false)),
		);

		$this->subDirStore = new LargeCollection($this, array(
			new SingleComparator(),
		), array(
			'tempPrefix' => 'subdirsmap',
			'maxBufferSize' => $this->options->getMaxTempKB() * 1024,
		));

		$this->subDirWriter = new CollectionWriter($this, array(
			'maxSize' => intval(floor($this->options->getMaxSubDirsMapKB() * Options::MAX_STORE_PERCENTAGE)) * 1024,
			'pageSize' => $this->options->getPageSize(),
			'combined' => true,
		));

		// File lists
		// ===================================

		$this->fileListWriter = new CollectionWriter($this, array(
			'maxSize' => intval(floor($this->options->getMaxFileListMapKB() * Options::MAX_STORE_PERCENTAGE)) * 1024,
			'pageSize' => $this->options->getPageSize(),
			'combined' => true,
		));

		$this->fileListComparators = array(
			'name' => new MultiComparator(0),
			'size' => new MultiComparator(1, array('reverseSort' => true, 'secondarySortIndexes' => array(0), 'reverseSecondarySortIndexes' => false)),
			'modified' => new MultiComparator(2, array('reverseSort' => true, 'secondarySortIndexes' => array(0), 'reverseSecondarySortIndexes' => false)),
		);

		$this->fileListMap = new LargeMap(
			$this,
			$this->options->getMaxFileListMapKB() * 1024,
			intval(floor($this->options->getMaxFileListMapKB() / 2)) * 1024,
			array(
				'prefix' => 'filesmap',
				'listener' => $this,
			)
		);

		// Other stores
		// ===================================

		// Storage for top file lists.
		$this->topListMap = new LargeMap(
			$this,
			$this->options->getMaxFileListMapKB() * 1024,
			intval(floor($this->options->getMaxFileListMapKB() / 2)) * 1024,
			array(
				'prefix' => 'topmap',
				'listener' => $this,
			)
		);

		// Storage for file size summaries.
		$this->fileSizesMap = new LargeMap(
			$this,
			$this->options->getMaxFileListMapKB() * 1024,
			intval(floor($this->options->getMaxFileListMapKB() / 2)) * 1024,
			array(
				'prefix' => 'filesizes',
				'listener' => $this,
			)
		);

		// Storage for modified date summaries.
		$this->modifiedDatesMap = new LargeMap(
			$this,
			$this->options->getMaxFileListMapKB() * 1024,
			intval(floor($this->options->getMaxFileListMapKB() / 2)) * 1024,
			array(
				'prefix' => 'modifieddates',
				'listener' => $this,
			)
		);
	}

	public function buildPath($extension) {
		return $this->options->buildPath($extension);
	}

	/**
	 * Process a header line from a scan file.
	 *
	 * The header can only be processed once and it must be before any dir/file lines are processed.
	 *
	 * @param string $line
	 *
	 * @return bool Whether the header was processed.
	 * @throws HeaderException
	 * @throws HeaderSettingException
	 */
	public function processHeader($line) {
		if (!$this->headerAllowed)
			return false;

		$this->options->processHeader($line);
		$this->currentDirInfo = new DirInfo($this);
		$this->currentDirInfo->init();
		$this->headerAllowed = false;
		return true;
	}

	/**
	 * Processes a directory entry in the scan file.
	 *
	 * @param DirInfo $dirInfo
	 * @throws ScanException
	 */
	public function processDirInfo(DirInfo $dirInfo) {

		// Create a basic root directory if there was no header line.
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

	/**
	 * Process a file entry in the scan file.
	 *
	 * @param FileInfo $fileInfo
	 * @throws ScanException
	 */
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

	/**
	 * Finalize the report and save maps/lookups/settings to disk.
	 *
	 * @throws Exception
	 * @throws ScanException
	 */
	public function save() {
		// Create a root directory entry if a header was not processed.
		if ($this->headerAllowed) {
			$this->currentDirInfo = new DirInfo($this);
			$this->currentDirInfo->init();
			$this->headerAllowed = false;
		}

		// Process any remaining directories.
		while ($this->currentDirInfo !== null) {
			if (Logger::doLevel(Logger::LEVEL_DEBUG3))
				Logger::log("Popping dir: {$this->currentDirInfo->path}", Logger::LEVEL_DEBUG3);

			$popDir = $this->currentDirInfo;

			$popDir->onPop();
			$this->directoryStore->add($popDir->hash, json_encode($popDir->hash) . ":" . $popDir->toJSON());

			$this->currentDirInfo = $this->currentDirInfo->getParent();
			if ($this->currentDirInfo !== null) {
				$this->currentDirInfo->onChildPop($popDir);
			}
		}

		if (Logger::doLevel(Logger::LEVEL_VERBOSE))
			Logger::log("Saving maps...", Logger::LEVEL_VERBOSE);

		// Save directory map.
		$start = microtime(true);
		$dirsLookup = new RangeLookup();
		CollectionWriter::Build($this, array(
			'maxSize' => $this->options->getMaxDirMapKB() * 1024,
			'asObject' => true,
			'listeners' => array($this),
		))->save($this->directoryStore, 'dirmap', $this->options->getSuffix(), $dirsLookup);

		if (Logger::doLevel(Logger::LEVEL_VERBOSE))
			Logger::log("Saved directory map files. Took " . sprintf('%.2f', microtime(true) - $start) . " sec", Logger::LEVEL_VERBOSE);

		// Save sub-directory lists map.
		$start = microtime(true);
		$subDirsLookup = new RangeLookup();
		CollectionWriter::Build($this, array(
			'maxSize' => $this->options->getMaxSubDirsMapKB() * 1024,
			'asObject' => true,
			'listeners' => array($this),
		))->save($this->subDirStore, 'subdirsmap', $this->options->getSuffix(), $subDirsLookup);

		if (Logger::doLevel(Logger::LEVEL_VERBOSE))
			Logger::log("Saved sub-directory map files. Took " . sprintf('%.2f', microtime(true) - $start) . " sec", Logger::LEVEL_VERBOSE);

		// Save large maps.
		$this->fileListMap->save();
		$this->topListMap->save();
		$this->fileSizesMap->save();
		$this->modifiedDatesMap->save();

		if (Logger::doLevel(Logger::LEVEL_VERBOSE))
			Logger::log("Saving lookups and settings...", Logger::LEVEL_VERBOSE);

		// Save the directory lookup.
		$lookupSize = file_put_contents($this->buildPath('dirmap_lookup' . $this->options->getSuffix()), json_encode($dirsLookup->getReduced()));
		if ($lookupSize === false)
			throw new ScanException('Failed to write dirmap_lookup' . $this->options->getSuffix() . '.');

		$this->outFiles++;
		$this->outSize += $lookupSize;

		// Save the sub-directory lookup.
		$lookupSize = file_put_contents($this->buildPath('subdirmap_lookup' . $this->options->getSuffix()), json_encode($subDirsLookup->getReduced()));
		if ($lookupSize === false)
			throw new ScanException('Failed to write subdirmap_lookup' . $this->options->getSuffix() . '.');

		$this->outFiles++;
		$this->outSize += $lookupSize;

		// Save settings.
		$settingsSize = file_put_contents($this->buildPath('settings' . $this->options->getSuffix()), $this->options->toJSON());
		if ($lookupSize === false)
			throw new ScanException('Failed to write settings' . $this->options->getSuffix() . '.');

		$this->outFiles++;
		$this->outSize += $settingsSize;
	}

	/**
	 * Pop off directories from the current directory until the current
	 * directory's path matches the {@link FileInfo}'s dirname.
	 *
	 * <p>Does the following for each popped directory:
	 *
	 * - Calls {@link DirInfo::onPop()} on popped directories.
	 * - Calls {@link DirInfo::onChildPop()} on the parent of popped directories.
	 * - Adds popped directories' JSON to the {@link directoryStore}.
	 *
	 * @param FileInfo $fileInfo
	 *
	 * @throws ScanException with a message of ScanException::POPDIR_NOPARENT if no directory's path matched the FileInfo's dirname.
	 */
	protected function popParents(FileInfo $fileInfo) {
		while ($this->currentDirInfo->path != $fileInfo->dirname) {

			$popDir = $this->currentDirInfo;

			// Set the current dir to the popped dir's parent.
			// Fail if there is no parent.
			if (($this->currentDirInfo = $popDir->getParent()) === null)
				throw new ScanException(ScanException::POPDIR_NOPARENT);

			if (Logger::doLevel(Logger::LEVEL_DEBUG3))
				Logger::log("Popping dir: {$popDir->path}", Logger::LEVEL_DEBUG3);

			// Alert the popped dir that it is being popped.
			$popDir->onPop();

			// Add the popped dir's JSON to the directory list.
			$this->directoryStore->add($popDir->hash, json_encode($popDir->hash) . ":" . $popDir->toJSON());

			// Alert the popped dir's parent that its child has been popped.
			$this->currentDirInfo->onChildPop($popDir);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function openFile($prefix, $index, $suffix, $mode) {
		return new FileStream($this->buildPath($prefix . '_' . $index . $suffix), $mode);
	}

	/**
	 * @inheritdoc
	 */
	public function deleteFile($prefix, $index, $suffix) {
		return @unlink($this->buildPath($prefix . '_' . $index . $suffix));
	}

	/**
	 * @inheritdoc
	 */
	public function renameTo($fromPath, $prefix, $index, $suffix) {
		return rename($fromPath, $this->buildPath($prefix . '_' . $index . $suffix));
	}

	/**
	 * @inheritdoc
	 */
	public function onSave($sortIndex, $sortKey, $fileIndex, $length, $size, $firstItem, $lastItem, $path) {
		if (!empty($size)) {
			$this->outFiles++;
			$this->outSize += $size;

			if (Logger::doLevel(Logger::LEVEL_VERY_VERBOSE))
				Logger::log('Saved file ' . basename($path) . ' with ' . number_format($length) . ' items at ' . number_format($size) . ' bytes.', Logger::LEVEL_VERY_VERBOSE);
		}
	}

}
