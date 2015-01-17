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

class LargeCollection implements IKeyedJSON {

	protected $key = null;

	protected $suffix = '.txt';
	protected $prefix = 'root';

	protected $totalLength = 0;
	protected $totalSize = 0;

	/**
	 * Number of temp files saved to disk.
	 * @var int
	 */
	protected $tempFiles = 0;

	protected $bufferSize = 0;
	protected $bufferLength = 0;

	protected $maxSize = false;
	protected $maxLength = false;

	protected $maxTempSize = 204800;
	protected $maxOpenFiles = 30;

	protected $asObject;

	protected $list;

	/**
	 * @var ICollectionOutput[]
	 */
	protected $outputs;

	protected $combinedOutput = null;
	protected $saveWatcher = null;

	/**
	 * @param ICollectionOutput[] $outputs
	 * @param array $options
	 *
	 * @throws Exception
	 */
	public function __construct(array $outputs = null, array $options = array()) {
		$this->outputs = $outputs;

		if (!is_array($outputs))
			throw new Exception(get_class($this) . "'s outputs argument must be an array.");

		if (isset($options['prefix']) && is_string($options['prefix']))
			$this->prefix = $options['prefix'];

		if (isset($options['suffix']) && is_string($options['suffix']))
			$this->suffix = $options['suffix'];

		if (isset($options['maxSize']))
			$this->maxSize = is_int($options['maxSize']) && $options['maxSize'] > 0 ? $options['maxSize'] : false;

		if (isset($options['maxLength']))
			$this->maxLength = is_int($options['maxLength']) && $options['maxLength'] > 0 ? $options['maxLength'] : false;

		if (isset($options['maxTempSize'])) {
			if (!is_int($options['maxTempSize']) || $options['maxTempSize'] < 1024)
				throw new Exception(get_class($this) . "'s maxTempSize option must be an int no less than 1024.");
			$this->maxTempSize = $options['maxTempSize'];
		}

		if (isset($options['maxOpenFiles'])) {
			if (!is_int($options['maxOpenFiles']) || $options['maxOpenFiles'] < 5)
				throw new Exception(get_class($this) . "'s maxOpenFiles option must be an int no less than 5.");
			$this->maxOpenFiles = $options['maxOpenFiles'];
		}

		if ($this->maxSize === false && $this->maxLength === false)
			throw new Exception("Either " . get_class($this) . "'s maxSize or maxLength options must be set and must be a int greater than 0.");

		if (isset($options['asObject'])) {
			if (!is_bool($options['asObject']))
				throw new Exception(get_class($this) . "'s asObject option must be a boolean.");

			$this->asObject = $options['asObject'];
		}

		if (isset($options['combinedOutput'])) {
			if (!is_object($options['combinedOutput']) || !($options['combinedOutput'] instanceof ICollectionIO))
				throw new Exception(get_class($this) . "'s combinedOutput option must an instanceof CollectionOutputAdapter.");

			$this->combinedOutput = $options['combinedOutput'];
		}

		if (isset($options['saveWatcher'])) {
			if (!is_object($options['saveWatcher']) || !($options['saveWatcher'] instanceof ISaveWatcher))
				throw new Exception(get_class($this) . "'s saveWatcher option must be an instance of ISaveWatcher.");

			$this->saveWatcher = $options['saveWatcher'];
		}

		if (isset($options['key']) && is_string($options['key']))
			$this->key = $options['key'];

		$this->startNew();
	}

	public function getSuffix() {
		return $this->suffix;
	}

	public function getSize() {
		return $this->totalSize;
	}

	public function getJSONSize() {
		return $this->tempFiles > 0 ? false : $this->totalSize;
	}

	public function getLength() {
		return $this->totalLength;
	}

	public function getBufferLength() {
		return $this->bufferLength;
	}

	public function getBufferSize() {
		return $this->bufferLength;
	}

	public function getMaxSize() {
		return $this->maxSize;
	}

	public function getMaxLength() {
		return $this->maxLength;
	}

	public function getMaxTempSize() {
		return $this->maxTempSize;
	}

	public function getMaxOpenFiles() {
		return $this->maxOpenFiles;
	}

	public function isMultiPart() {
		return $this->isOverMax($this->totalSize, $this->totalLength);
	}

	public function getKey() {
		return $this->key;
	}

	public function setKey($key) {
		$this->key = $key;
	}

	public function toJSON() {
		if ($this->tempFiles > 0)
			throw new Exception("Cannot convert list with multiple segments to JSON");

		$ret = '';
		foreach ($this->list as $item) {
			$ret .= ',' . $item[1];
		}

		if ($ret == '')
			return $this->asObject ? '{}' : '[]';

		$ret[0] = $this->asObject ? '{' : '[';
		return $ret . ($this->asObject ? '}' : ']');
	}

	public function add($compareVal, $itemJSON) {
		$addLen = strlen($itemJSON) + 1;

		if (is_string($compareVal)) {
			$compareVal = str_replace("\n", "", $compareVal);
		}
		elseif (is_array($compareVal)) {
			foreach ($compareVal as $i => $compareValItem) {
				if (is_string($compareValItem))
					$compareVal[$i] = str_replace("\n", "", $compareValItem);
			}
		}

		if ($this->bufferSize + $addLen > $this->maxTempSize)
			$this->saveTemp();

		$this->bufferSize += $addLen;
		$this->bufferLength++;

		$this->totalSize += $addLen;
		$this->totalLength++;

		$this->list[] = array($compareVal, $itemJSON);
	}

	protected function saveTemp() {
		$this->tempFiles++;
		$outSize = 0;

		/** @var $output ICollectionOutput */
		foreach ($this->outputs as $output) {
			$tempFile = $output->openFile($this->prefix, $this->tempFiles, '.tmp', 'w');

			// Sort each output.
			usort($this->list, array($output, 'compare'));

			// Write each list item serialized on its own line.
			foreach ($this->list as $item) {
				if (!isset($item[2]))
					$item[2] = serialize($item);

				$tempFile->write($item[2] . "\n");
			}

			$outSize += $tempFile->tell();
			$tempFile->close();
		}

		if (Logger::doLevel(Logger::LEVEL_DEBUG1)) {
			$outSize = round($outSize / max(1, count($this->outputs)));
			Logger::log("Saved temp file #{$this->tempFiles} x " . count($this->outputs) . " each with " . count($this->list) . " items at ~$outSize bytes.", Logger::LEVEL_DEBUG1);
		}

		$this->startNew();
	}

	protected function startNew() {
		$this->list = array();
		$this->bufferLength = 0;
		$this->bufferSize = 0;
	}

	protected function isOverMax($size, $length) {
		return (($this->maxSize !== false && $this->maxSize < $size)
			|| ($this->maxLength !== false && $this->maxLength < $length));
	}

	/**
	 * Compact temp files so there are no more than the specified max.
	 * This is to prevent iterating over too many files, causing PHP issues when opening too many files.
	 *
	 * @param int               $segments The number of temp files.
	 * @param int               $maxSegments The maximum number of temp files allowed.
	 * @param ICollectionOutput $output ICollectionOutput used to determine sorting.
	 *
	 * @return int The new number of temp files.
	 * @throws Exception
	 */
	protected function compactSegments($segments, $maxSegments, ICollectionOutput $output) {
		// The number of existing temp files that will need to be combined into a single new temp file.
		$segmentsPer = ceil($segments / $maxSegments);

		// The number of new temp files after being compacted.
		$newSegments = ceil($segments / $segmentsPer);

		for ($newSeg = 1; $newSeg <= $newSegments; $newSeg++) {

			// Open the temp files that will be compacted into this new temp file.
			$iterators = array();
			for ($oldSeg = 1 + ($newSeg - 1) * $segmentsPer; $oldSeg <= min($segments, $newSeg * $segmentsPer); $oldSeg++) {
				$iterators[] = new FileIterator(
					$output->openFile($this->prefix, $oldSeg, '.tmp', 'r'), array(
					'unserialize' => true,
					'unlinkOnEnd' => true
				));
			}

			// Compact the temp files into the new file (at a temporary path).
			$compactedFile = $output->openFile('compact', $newSeg, '.tmp', 'w');
			$sorter = new MultiFileSorter($iterators, $output);
			foreach ($sorter as $item) {
				$compactedFile->write(serialize($item) . "\n");
			}
			$compactedFile->close();

			// Move the new temp file to its final path.
			if ($output->renameTo($compactedFile->getPath(), $this->prefix, $newSeg, '.tmp') === false)
				throw new Exception("Failed to rename compacted file.");
		}

		return $newSegments;
	}

	public function save() {

		$ret = 0;
		$lastHandlerIndex = count($this->outputs) - 1;

		// Process each output handler.
		/** @var $output ICollectionOutput */
		foreach ($this->outputs as $handlerIndex => $output) {
			// Sort the in-memory buffer.
			usort($this->list, array($output, 'compare'));

			// Create a list of iterators with the in-memory buffer as one of them.
			$bufferIterator = new ArrayIterator($this->list);
			$iterators = array($bufferIterator);

			// Compact the temp files so there are no more than the specified max,
			// preventing PHP issues when opening too many files.
			$tempFiles = $this->tempFiles;
			if ($tempFiles > $this->maxOpenFiles) {
				$compactStart = microtime(true);
				$origTempFiles = $tempFiles;
				$tempFiles = $this->compactSegments($tempFiles, $this->maxOpenFiles, $output);
				Logger::log("Compacted $origTempFiles temp files into $tempFiles with up to " . ceil($origTempFiles / $tempFiles) . " each in " . sprintf('%.2f', microtime(true) - $compactStart) . " sec", Logger::LEVEL_VERBOSE);
			}

			// Add iterators for the temp files.
			for ($i = 1; $i <= $tempFiles; $i++) {
				$iterators[] = new FileIterator(
					$output->openFile($this->prefix, $i, '.tmp', 'r'), array(
					'unserialize' => true,
					'unlinkOnEnd' => true
				));
			}

			$sorter = new MultiFileSorter($iterators, $output);

			if (($iteratorCount = count($iterators)) > 1) {
				$sortStart = microtime(true);
			}

			$outIndex = 1;
			$outSize = 0;
			$outLines = 0;
			$outHandler = $this->combinedOutput === null ? $output : $this->combinedOutput;
			$openMode = $this->combinedOutput === null ? 'w' : ($handlerIndex == 0 ? 'w' : 'a');

			/** @var $outFile FileStream */
			$outFile = $outHandler->openFile($this->prefix, $outIndex, $this->suffix, $openMode);
			$firstItem = null;
			$lastItem = null;

			// Combined output files need extra delimiters.
			if ($this->combinedOutput !== null)
				$outFile->write($handlerIndex == 0 ? '[' : ',');

			// Read all lines via the multi-sorter.
			foreach ($sorter as $item) {
				$itemSize = strlen($item[1]) + 1;

				// Move to the next file if this will make the current one too large.
				if ($outSize > 0 && $this->isOverMax($outSize + $itemSize + 2, $outLines + 1)) {
					$outFile->write($this->asObject ? '}' : ']');

					// Combined output files need a trailing array delimiter,
					// and only close after the last handler.
					if ($handlerIndex == $lastHandlerIndex && $this->combinedOutput !== null) {
						$outFile->write(']');
						$outFile->close();
						$this->combinedOutput->onSave($outIndex, null, null, filesize($outFile->getPath()), $outFile->getPath());
					}
					else {
						$outFile->close();
					}

					$output->onSave($outIndex, $firstItem, $lastItem, $this->combinedOutput !== null ? false : $outSize + 1, $outFile->getPath());
					if ($this->saveWatcher !== null)
						$this->saveWatcher->onSave($outIndex, $handlerIndex, $firstItem, $lastItem, $outFile->getPath());

					$outIndex++;
					$outSize = 0;
					$outLines = 0;
					$outFile = $outHandler->openFile($this->prefix, $outIndex, $this->suffix, $openMode);

					// Combined output files need extra delimiters.
					if ($this->combinedOutput !== null)
						$outFile->write($handlerIndex == 0 ? '[' : ',');
				}

				$lastItem = $item;
				if ($outSize === 0)
					$firstItem = $item;

				$outFile->write(($outSize > 0 ? ',' : ($this->asObject ? '{' : '[')) . $item[1]);
				$outSize += $itemSize;
				$outLines++;
			}

			$outFile->write($this->asObject ? '}' : ']');
			$output->onSave($outIndex, $firstItem, $lastItem, $this->combinedOutput !== null ? false : $outSize + 1, $outFile->getPath());

			if ($this->saveWatcher !== null)
				$this->saveWatcher->onSave($outIndex, $handlerIndex, $firstItem, $lastItem, $outFile->getPath());

			// Combined output files need a trailing array delimiter,
			// and only close after the last handler.
			if ($handlerIndex == $lastHandlerIndex && $this->combinedOutput !== null) {
				$outFile->write(']');
				$outFile->close();
				$this->combinedOutput->onSave($outIndex, null, null, filesize($outFile->getPath()), $outFile->getPath());
			}
			else {
				$outFile->close();
			}

			if ($iteratorCount > 1) {
				Logger::log("Sorted $iteratorCount temp files in " . sprintf('%.2f', microtime(true) - $sortStart) . " sec.", Logger::LEVEL_VERBOSE);
			}

			$ret = $outIndex;
		}

		return $ret;
	}

}
