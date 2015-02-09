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
 * Class LargeCollection
 */
class LargeCollection implements IKeyedJSON {

	protected $key;

	/**
	 * @var bool Whether or not items can be added to the collection.
	 */
	protected $isReadOnly = false;

	/**
	 * @var string File suffix for temp files.
	 */
	protected $tempSuffix = '.tmp';

	/**
	 * @var string File prefix for temp files.
	 */
	protected $tempPrefix = 'root';

	/**
	 * @var int Total number of items in the collection.
	 */
	protected $totalLength = 0;

	/**
	 * @var int Total size in bytes of the collection.
	 */
	protected $totalSize = 0;

	/**
	 * @var int|int[] Number of temp files saved to disk.
	 */
	protected $tempFiles;

	/**
	 * @var int Size in bytes of the items in the buffer.
	 */
	protected $bufferSize = 0;

	/**
	 * @var int Number of items in the buffer.
	 */
	protected $bufferLength = 0;

	/**
	 * @var int Maximum number of bytes for items stored in memory before being saved to a temp file.
	 * This does not include some extra bytes needed per item for sorting, so the actual RAM usage will be slightly higher.
	 */
	protected $maxBufferSize = 204800;

	/**
	 * @var int Maximum number of files that can be opened at once by the iterator returned by getIterator.
	 */
	protected $maxOpenFiles = 30;

	/**
	 * @var array
	 */
	protected $list;

	/**
	 * @var IComparator[]
	 */
	protected $comparators;

	/**
	 * @var ICollectionIO
	 */
	protected $io;

	/**
	 * @param ICollectionIO $io
	 * @param IComparator[] $comparators
	 * @param array         $options
	 *
	 * @throws Exception
	 */
	public function __construct(ICollectionIO $io, array $comparators, array $options = array()) {
		$this->io = $io;
		$this->tempFiles = 0;

		$this->comparators = $comparators;
		if (empty($comparators))
			throw new Exception("LargeCollection must have at least one comparator.");

		foreach (array('tempPrefix','tempSuffix','key') as $option) {
			if (isset($options[$option]) && is_string($options[$option]))
				$this->$option = $options[$option];
		}

		if (isset($options['maxBufferSize'])) {
			if (!is_int($options['maxBufferSize']) || $options['maxBufferSize'] < 1024)
				throw new Exception(get_class($this) . "'s maxBufferSize option must be an int no less than 1024.");
			$this->maxBufferSize = $options['maxBufferSize'];
		}

		if (isset($options['maxOpenFiles'])) {
			if (!is_int($options['maxOpenFiles']) || $options['maxOpenFiles'] < 5)
				throw new Exception(get_class($this) . "'s maxOpenFiles option must be an int no less than 5.");
			$this->maxOpenFiles = $options['maxOpenFiles'];
		}

		$this->clearBuffer();
	}

	/**
	 * @return IComparator[]
	 */
	public function getComparators() {
		return $this->comparators;
	}

	/**
	 * Get the total size in bytes of the collection.
	 * @return int
	 */
	public function getSize() {
		return $this->totalSize;
	}

	/**
	 * @inheritdoc
	 */
	public function getKey() {
		return $this->key;
	}

	/**
	 * @inheritdoc
	*/
	public function setKey($key) {
		$this->key = $key;
	}

	/**
	 * @inheritdoc
	 */
	public function getJSONSize() {
		return $this->totalSize;
	}

	/**
	 * Get the number of items in the collection.
	 * @return int
	 */
	public function getLength() {
		return $this->totalLength;
	}

	/**
	 * Get the number of items in the buffer.
	 * When a new temp file is started the buffer resets to 0.
	 * @return int
	 */
	public function getBufferLength() {
		return $this->bufferLength;
	}

	/**
	 * Get the number of bytes in the buffer.
	 * When a new temp file is started the buffer resets to 0.
	 * @return int
	 */
	public function getBufferSize() {
		return $this->bufferLength;
	}

	public function getMaxBufferSize() {
		return $this->maxBufferSize;
	}

	public function getMaxOpenFiles() {
		return $this->maxOpenFiles;
	}

	/**
	 * Add an item to the collection.
	 *
	 * @param string|string[] $compareVal Value(s) used for sorted outputs.
	 * @param string          $itemJSON   Stringified JSON for the item.
	 * @throws Exception
	 */
	public function add($compareVal, $itemJSON) {
		if ($this->isReadOnly)
			throw new Exception("Cannot add to LargeCollection once its contents have been read.");

		$addLen = strlen($itemJSON) + 1;

		$compareVal = $this->cleanNewLines($compareVal);

		// Clean newlines from the compare value(s).
		if (is_string($compareVal)) {
			$compareVal = str_replace("\n", "", $compareVal);
		}
		elseif (is_array($compareVal)) {
			foreach ($compareVal as $i => $compareValItem) {
				if (is_string($compareValItem))
					$compareVal[$i] = str_replace("\n", "", $compareValItem);
			}
		}

		// Save the buffer to a temp file and clear the buffer if it is over the max.
		if ($this->bufferSize + $addLen > $this->maxBufferSize)
			$this->saveTemp();

		$this->bufferSize += $addLen;
		$this->bufferLength++;

		$this->totalSize += $addLen;
		$this->totalLength++;

		$this->list[] = array($compareVal, $itemJSON);
	}

	protected function cleanNewLines($compareVal) {
		// Clean newlines from the compare value(s).
		if (is_string($compareVal)) {
			$compareVal = str_replace("\n", "", $compareVal);
		}
		elseif (is_array($compareVal)) {
			foreach ($compareVal as $i => $compareValItem) {
				if (is_string($compareValItem))
					$compareVal[$i] = str_replace("\n", "", $compareValItem);
			}
		}

		return $compareVal;
	}

	/**
	 * Save the buffer to a temp file and then clear the buffer.
	 * @throws IOException
	 */
	protected function saveTemp() {
		$this->tempFiles++;
		$saveStart = microtime(true);
		$outSize = 0;

		// Save a temp file for each output.
		$comparatorCount = 0;
		foreach ($this->comparators as $k => $comparator) {
			$comparatorCount++;
			$tempFile = $this->io->openFile($this->tempPrefix . '_' . $k, $this->tempFiles, $this->tempSuffix, 'w');

			// Sort for this output.
			usort($this->list, array($comparator, 'compare'));

			// Write each list item serialized on its own line.
			foreach ($this->list as &$item) {

				// Cache the serialization to avoid re-serializatoin for other outputs.
				if (empty($item[2])) {
					$item[1] = serialize($item);
					$item[2] = true;
				}

				$tempFile->write($item[1] . "\n");
			}

			// Record the number of bytes written and close the file.
			$outSize += $tempFile->tell();
			$tempFile->close();
		}

		if (Logger::doLevel(Logger::LEVEL_DEBUG1))
			Logger::log("Saved " . count($this->list) . " item(s) at {$outSize} bytes to {$comparatorCount} temp file(s) for '{$this->tempPrefix}' #{$this->tempFiles} in " . sprintf('%.2f', microtime(true) - $saveStart) . " sec.", Logger::LEVEL_DEBUG1);

		$this->clearBuffer();
	}

	/**
	 * Reset the buffered item list.
	 */
	protected function clearBuffer() {
		$this->list = array();
		$this->bufferLength = 0;
		$this->bufferSize = 0;
	}

	/**
	 * Compact files so there are no more than the specified max.
	 * This is to prevent iterating over too many files, causing PHP issues when opening too many files.
	 *
	 * @param int         $segments    The number of files.
	 * @param int         $maxSegments The maximum number of files allowed.
	 * @param mixed       $comparatorKey
	 * @param IComparator $comparator
	 * @return int The new number of files.
	 * @throws Exception
	 * @throws IOException
	 */
	protected function compactSegments($segments, $maxSegments, $comparatorKey, IComparator $comparator) {
		// The number of existing files that will need to be combined into a single new file.
		$segmentsPer = ceil($segments / $maxSegments);

		// The number of new files after being compacted.
		$newSegments = ceil($segments / $segmentsPer);

		for ($newSeg = 1; $newSeg <= $newSegments; $newSeg++) {

			// Open the files that will be compacted into this new file.
			$iterators = array();
			for ($oldSeg = 1 + ($newSeg - 1) * $segmentsPer; $oldSeg <= min($segments, $newSeg * $segmentsPer); $oldSeg++) {
				$iterators[] = new FileIterator(
					$this->io->openFile($this->tempPrefix . '_' . $comparatorKey, $oldSeg, $this->tempSuffix, 'r'), array(
						'unserialize' => true,
						'unlinkOnEnd' => true,
					)
				);
			}

			// Compact the original files into a temporary compacted file.
			$compactedFile = $this->io->openFile($this->tempPrefix . '_' . $comparatorKey . '_compact', $newSeg, $this->tempSuffix, 'w');
			$sorter = new MultiIteratorSorter($iterators, $comparator);
			foreach ($sorter as $item) {
				$compactedFile->write(serialize($item) . "\n");
			}
			$compactedFile->close();

			// Move the new file to its final path.
			// By now the old files have been removed.
			if ($this->io->renameTo($compactedFile->getPath(), $this->tempPrefix . '_' . $comparatorKey, $newSeg, $this->tempSuffix) === false)
				throw new Exception("Failed to rename compacted file.");
		}

		return $newSegments;
	}

	/**
	 * Get an Iterator for the specified comparator key.
	 *
	 * @param string $key
	 * @param bool   $unlinkOnEnd Optionally unlink the temp files after the iterator completes.
	 * @return Iterator
	 * @throws Exception
	 */
	public function getIterator($key, $unlinkOnEnd = false) {
		if (!$this->isReadOnly) {
			$this->isReadOnly = true;

			// Get copies of the original list and temp file count.
			$origTempFiles = $this->tempFiles;
			$list = $this->list;

			// Set arrays to list/tempFile to hold values for each comparator.
			$this->list = array();
			$this->tempFiles = array();

			foreach ($this->comparators as $k => $comparator) {
				// Sort the in-memory buffer for the output.
				usort($list, array($comparator, 'compare'));
				$this->list[$k] = $list;
				$this->tempFiles[$k] = $origTempFiles;

				// Compact the temp files so there are no more than the specified max,
				// preventing PHP issues when opening too many files.
				if ($origTempFiles > $this->maxOpenFiles) {
					$compactStart = microtime(true);
					$this->tempFiles[$k] = $this->compactSegments($origTempFiles, $this->maxOpenFiles, $k, $comparator);
					Logger::log("Compacted {$origTempFiles} temp files into {$this->tempFiles[$k]} with up to " . ceil($origTempFiles / $this->tempFiles[$k]) . " each in " . sprintf('%.2f', microtime(true) - $compactStart) . " sec", Logger::LEVEL_VERBOSE);
				}
			}
		}

		// List of iterators with the in-memory buffer as one of them.
		$iterators = array(
			new ArrayIterator($this->list[$key])
		);

		// Add iterators for the temp files.
		for ($i = 1; $i <= $this->tempFiles[$key]; $i++) {
			$iterators[] = new FileIterator(
				$this->io->openFile($this->tempPrefix . '_' . $key, $i, $this->tempSuffix, 'r'), array(
					'unserialize' => true,
					'unlinkOnEnd' => !!$unlinkOnEnd,
				)
			);
		}

		return new MultiIteratorSorter($iterators, $this->comparators[$key]);
	}

	/**
	 * Get the stringified JSON for this object.
	 *
	 * @return string
	 */
	public function toJSON() {
		$key = current(array_keys($this->comparators));
		$json = implode(',', iterator_to_array($this->getIterator($key, true)));
		foreach ($this->getIterator($key, true) as $item) {
			$json[] = $item;
		}
		$this->clear();
	}

	/**
	 * Clean up any temp files.
	 */
	public function clear() {
		foreach ($this->comparators as $key => $comparator) {
			for ($i = 0, $l = is_array($this->tempFiles) ? $this->tempFiles[$key] : $this->tempFiles; $i < $l; $i++) {
				$this->io->deleteFile($this->tempPrefix . '_' . $key, $i, $this->tempSuffix);
			}
		}

		$this->clearBuffer();
		$this->tempFiles = 0;
	}
}
