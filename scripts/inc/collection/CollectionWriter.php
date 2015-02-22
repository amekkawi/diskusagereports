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
 * Writes a LargeCollection to disk or converts it to JSON.
 */
class CollectionWriter {

	/**
	 * @var int The maximum number of entries per saved file.
	 */
	protected $maxLength = 0;

	/**
	 * @var int The maximum bytes per file. If pageSize is also specified this will be the average bytes per file.
	 */
	protected $maxSize = 0;

	/**
	 * @var int The number of items per "page". Used to fit as many pages into the maxSize.
	 */
	protected $pageSize = 0;

	/**
	 * @var bool Whether or not to combine the items from the comparators into one file or write them to separate files.
	 */
	protected $combined = false;

	/**
	 * @var bool true to write braces (an object) around the output, false to write brackets (an array).
	 */
	protected $asObject = false;

	/**
	 * @var ICollectionIO
	 */
	protected $io;

	/**
	 * @var ICollectionListener[]
	 */
	protected $listeners = array();

	public static function Build(ICollectionIO $io, array $options = array()) {
		return new CollectionWriter($io, $options);
	}

	public function __construct(ICollectionIO $io, array $options = array()) {
		$this->io = $io;

		// Integer options.
		foreach (array('maxSize','maxLength','pageSize') as $option) {
			if (isset($options[$option])) {
				if (!is_int($options[$option]) || $options[$option] <= 0)
					throw new Exception(get_class($this) . "'s {$option} option must an int greater than 0.");
				$this->$option = $options[$option];
			}
		}

		// Boolean options.
		foreach (array('combined','asObject') as $option) {
			if (isset($options[$option])) {
				if (!is_bool($options[$option]))
					throw new Exception(get_class($this) . "'s {$option} option must a bool.");
				$this->$option = $options[$option];
			}
		}

		if (isset($options['listeners'])) {
			foreach ($options['listeners'] as $listener) {
				$this->addListener($listener);
			}
		}

		if ($this->maxSize === 0 && $this->maxLength === 0)
			throw new Exception(get_class($this) . ' must have either maxSize or maxLength options set.');

		if ($this->pageSize !== 0 && $this->maxSize === 0)
			throw new Exception(get_class($this) . ' must have maxSize set if pageSize is set.');

		if ($this->maxLength !== 0 && $this->pageSize !== 0)
			throw new Exception(get_class($this) . ' cannot have maxLength set if pageSize is set.');

		if ($this->maxSize !== 0 && $this->pageSize === 0 && $this->combined)
			throw new Exception(get_class($this) . ' cannot have a maxSize with combined unless pageSize is set.');
	}

	public function addListener(ICollectionListener $listener) {
		$this->listeners[] = $listener;
	}

	/**
	 * Write the collection to disk.
	 *
	 * @param LargeCollection     $collection
	 * @param string              $prefix
	 * @param string              $suffix
	 * @param ICollectionListener $listener
	 * @return array
	 * @throws Exception
	 * @throws IOException
	 */
	public function save(LargeCollection $collection, $prefix, $suffix, ICollectionListener $listener = null) {
		$listeners = $this->listeners;
		if (isset($listener))
			$listeners[] = $listener;

		$io = $this->io;
		$comparators = $collection->getComparators();
		$comparatorKeys = array_keys($comparators);
		$lastSortIndex = count($comparatorKeys) - 1;

		$maxLength = $this->maxLength > 0 ? $this->maxLength : 0;
		$maxSize = $this->maxSize > 0 ? $this->maxSize : 0;

		// Determine the number of "pages" per file, if a maxSize and pageSize are defined.
		if ($this->pageSize > 0 && $this->maxSize > 0) {

			// Total number of pages.
			$totalPages = ceil($collection->getLength() / $this->pageSize);

			// Average size per page.
			$sizePerPage = $collection->getSize() / $totalPages;

			if ($this->combined)
				$sizePerPage *= count($comparatorKeys);

			// Number of pages that would fit within maxSize.
			$pagesPerFile = max(1, floor($maxSize / $sizePerPage));

			$maxLength = $pagesPerFile * $this->pageSize;

			// Reset maxSize since it was just for calculating maxLength.
			$maxSize = 0;

			if (Logger::doLevel(Logger::LEVEL_DEBUG1))
				Logger::log("Adjusted maxLength to {$maxLength}. {$pagesPerFile} page(s) per file at " . ($sizePerPage * $pagesPerFile) . " bytes).", Logger::LEVEL_DEBUG1);
		}

		$fileSizeBySortIndex = array();
		$fileLengthBySortIndex = array();
		$fileIndexByKey = array_fill_keys($comparatorKeys, 0);
		$fileIndex = 0;

		foreach ($comparatorKeys as $sortIndex => $sortKey) {
			if ($lastSortIndex > 0)
				$sortStart = microtime(true);

			$iterator = $collection->getIterator($sortKey, true);
			$fileIndex = 1;
			$fileBytes = 0;
			$fileLength = 0;
			$firstItem = null;
			$lastItem = null;

			if (!isset($fileSizeBySortIndex[$fileIndex])) {
				$fileSizeBySortIndex[$fileIndex] = 0;
				$fileLengthBySortIndex[$fileIndex] = 0;
			}

			$openMode = $this->combined ? ($sortIndex == 0 ? 'w' : 'a') : 'w';
			$file = $io->openFile($prefix . ($this->combined ? '' : ($lastSortIndex > 0 ? "_$sortKey" : '')), $fileIndex, $suffix, $openMode);

			// Combined output files need extra delimiters.
			if ($this->combined) {
				$fileSizeBySortIndex[$fileIndex]++;
				$file->write($sortIndex == 0 ? '[' : ',');
			}

			// Read all lines via the multi-sorter.
			foreach ($iterator as $item) {
				$itemSize = strlen($item[1]) + 1;

				// Move to the next file if this will make the current one too large,
				// but only if we've writen at least one line to this file.
				if ($fileLength > 0 && $this->isOverMax($fileLength + 1, $fileBytes + $itemSize + 2, $maxLength, $maxSize)) {
					$fileSizeBySortIndex[$fileIndex]++;
					$fileBytes++;
					$file->write($this->asObject ? '}' : ']');

					// Combined output files need a trailing array delimiter.
					if ($sortIndex == $lastSortIndex && $this->combined) {
						$fileSizeBySortIndex[$fileIndex]++;
						$file->write(']');
						$file->close();

						if (DEBUG) {
							if (($debugCheck = filesize($file->getPath())) !== $fileSizeBySortIndex[$fileIndex])
								throw new Exception("Out size did not match (a): {$debugCheck} !== {$fileSizeBySortIndex[$fileIndex]} for " . $file->getPath());
						}

						// Fire an event for completing a combined file.
						foreach ($listeners as $listener) {
							$listener->onSave(null, null, $fileIndex, $fileLengthBySortIndex[$fileIndex], $fileSizeBySortIndex[$fileIndex], null, null, $file->getPath());
						}
					}
					else {
						$file->close();

						if (DEBUG) {
							if (!$this->combined && ($debugCheck = filesize($file->getPath())) !== $fileBytes)
								throw new Exception("Out size did not match (b): {$debugCheck} !== {$fileBytes} for " . $file->getPath());
						}
					}

					// Fire events for completing an iterator for a file.
					foreach ($listeners as $listener) {
						$listener->onSave($sortIndex, $sortKey, $fileIndex, $fileLength, $this->combined ? false : $fileBytes + 1, $firstItem, $lastItem, $file->getPath());
					}

					$fileIndex++;
					if (!isset($fileSizeBySortIndex[$fileIndex])) {
						$fileSizeBySortIndex[$fileIndex] = 0;
						$fileLengthBySortIndex[$fileIndex] = 0;
					}

					$fileBytes = 0;
					$fileLength = 0;
					$file = $io->openFile($prefix . ($this->combined ? '' : (count($comparatorKeys) > 1 ? "_$sortKey" : '')), $fileIndex, $suffix, $openMode);

					// Combined output files need extra delimiters.
					if ($this->combined) {
						$file->write($sortIndex == 0 ? '[' : ',');
						$fileSizeBySortIndex[$fileIndex]++;
					}
				}

				$lastItem = $item;
				if ($fileBytes === 0)
					$firstItem = $item;

				$file->write(($fileBytes > 0 ? ',' : ($this->asObject ? '{' : '[')) . $item[1]);
				$fileBytes += $itemSize;
				$fileSizeBySortIndex[$fileIndex] += $itemSize;

				$fileLength++;
				$fileLengthBySortIndex[$fileIndex]++;
			}

			$file->write($this->asObject ? '}' : ']');
			$fileSizeBySortIndex[$fileIndex]++;
			$fileBytes++;

			// Fire events for completing an iterator for a file.
			foreach ($listeners as $listener) {
				$listener->onSave($sortIndex, $sortKey, $fileIndex, $fileLength, $this->combined ? false : $fileBytes + 1, $firstItem, $lastItem, $file->getPath());
			}

			// Combined output files need a trailing array delimiter.
			if ($sortIndex == $lastSortIndex && $this->combined) {
				$file->write(']');
				$file->close();
				$fileSizeBySortIndex[$fileIndex]++;

				if (DEBUG) {
					if (($debugCheck = filesize($file->getPath())) !== $fileSizeBySortIndex[$fileIndex])
						throw new Exception("Out size did not match (c): {$debugCheck} !== {$fileSizeBySortIndex[$fileIndex]} for " . $file->getPath());
				}

				// Fire an event for completing a combined file.
				foreach ($listeners as $listener) {
					$listener->onSave(null, null, $fileIndex, $fileLengthBySortIndex[$fileIndex], $fileSizeBySortIndex[$fileIndex], null, null, $file->getPath());
				}
			}
			else {
				$file->close();

				if (DEBUG) {
					if (!$this->combined && ($debugCheck = filesize($file->getPath())) !== $fileBytes)
						throw new Exception("Out size did not match (d): {$debugCheck} !== {$fileBytes} for " . $file->getPath());
				}
			}

			if (isset($sortStart))
				Logger::log("Sorted " . ($lastSortIndex+1) . " temp files in " . sprintf('%.2f', microtime(true) - $sortStart) . " sec.", Logger::LEVEL_DEBUG1);

			$fileIndexByKey[$sortKey] = $fileIndex;
		}

		return array(
			'fileIndex' => $this->combined ? $fileIndex : $fileIndexByKey,
			'maxLength' => $maxLength,
			'maxSize' => $maxSize,
		);
	}

	/**
	 * Get the stringified JSON for this object.
	 *
	 * @param LargeCollection $collection
	 * @return string
	 */
	public function toJSON(LargeCollection $collection) {
		$key = current(array_keys($collection->getComparators()));
		$arr = array_map('array_pop', iterator_to_array($collection->getIterator($key, true)));
		$json = implode(',', $arr);
		$collection->clear();
		return $this->asObject ? '{' . $json . '}' : "[{$json}]";
	}

	protected function isOverMax($length, $size, $maxLength, $maxSize) {
		return ($maxLength > 0 && $length > $maxLength)
			|| ($maxSize > 0 && $size > $maxSize);
	}

	/**
	 * @return int
	 */
	public function getMaxLength() {
		return $this->maxLength;
	}

	/**
	 * @return int
	 */
	public function getMaxSize() {
		return $this->maxSize;
	}

	/**
	 * @return int
	 */
	public function getPageSize() {
		return $this->pageSize;
	}

	/**
	 * @return boolean
	 */
	public function isCombined() {
		return $this->combined;
	}

	/**
	 * @return boolean
	 */
	public function isAsObject() {
		return $this->asObject;
	}
}
