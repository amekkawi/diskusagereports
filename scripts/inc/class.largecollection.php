<?php

class LargeCollection implements KeyedJSON {

	public $prefix = 'root';

	protected $key = null;

	protected $totalLength = 0;
	protected $totalSize = 0;

	protected $tempFiles = 0;

	protected $bufferSize = 0;
	protected $bufferLength = 0;

	protected $maxSize = false;
	protected $maxLength = false;

	protected $maxTempSize = 204800;
	protected $maxOpenFiles = 30;

	protected $asObject;

	protected $list;
	protected $outputs;

	public function __construct(array $outputs = null, array $options = array()) {
		$this->outputs = $outputs;

		if (!is_array($outputs))
			throw new Exception(get_class($this) . "'s outputs argument must be an array.");

		if (isset($options['prefix']) && is_string($options['prefix']))
			$this->prefix = $options['prefix'];

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

		if (isset($options['key']) && is_string($options['key']))
			$this->key = $options['key'];

		$this->startNew();
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

		/** @var $output CollectionOutput */
		foreach ($this->outputs as $output) {
			$tempFile = $output->openFile($this->prefix, $this->tempFiles, 'tmp', 'w');

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

		//$outSize = round($outSize / max(1, count($this->outputs)));
		//echo "Saved temp file #{$this->tempFiles} x " . count($this->outputs) . " each with " . count($this->list) . " items at ~$outSize bytes...\n";

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

	protected function compactSegments($segments, $maxSegments, CollectionOutput $output) {
		$segmentsPer = ceil($segments / $maxSegments);
		$newSegments = ceil($segments / $segmentsPer);

		//$start = microtime(true);
		//echo "Compacting $segments segments into $newSegments with up to $segmentsPer each...\n";

		for ($newSeg = 1; $newSeg <= $newSegments; $newSeg++) {
			$iterators = array();
			for ($oldSeg = 1 + ($newSeg - 1) * $segmentsPer; $oldSeg <= min($segments, $newSeg * $segmentsPer); $oldSeg++) {
				$iterators[] = new FileIterator(
					$output->openFile($this->prefix, $oldSeg, 'tmp', 'r'), array(
					'unserialize' => true,
					'unlinkOnEnd' => true
				));
			}

			$compactedFile = $output->openFile('compact', $newSeg, 'tmp', 'w');
			$sorter = new MultiFileSorter($iterators, $output);
			foreach ($sorter as $item) {
				$compactedFile->write(serialize($item) . "\n");
			}
			$compactedFile->close();

			if ($output->renameTo($compactedFile->getPath(), $this->prefix, $newSeg, 'tmp') === false)
				throw Exception("Failed to rename compacted file.");
		}

		//echo " Took " . sprintf('%.2f', microtime(true) - $start) . " sec\n";

		return $newSegments;
	}

	public function save() {

		$ret = array();

		/** @var $output CollectionOutput */
		foreach ($this->outputs as $output) {
			// Sort the buffer.
			usort($this->list, array($output, 'compare'));

			$bufferIterator = new ArrayIterator($this->list);

			// Create a list of iterators with the buffer as one of them.
			$iterators = array($bufferIterator);

			$tempFiles = $this->tempFiles;
			if ($tempFiles > $this->maxOpenFiles)
				$tempFiles = $this->compactSegments($tempFiles, $this->maxOpenFiles, $output);

			// Add iterators for temp files.
			for ($i = 1; $i <= $tempFiles; $i++) {
				$iterators[] = new FileIterator(
					$output->openFile($this->prefix, $i, 'tmp', 'r'), array(
					'unserialize' => true,
					'unlinkOnEnd' => true
				));
			}

			$sorter = new MultiFileSorter($iterators, $output);

			$outIndex = 1;
			$outSize = 0;
			$outLines = 0;
			$outFile = $output->openFile($this->prefix, $outIndex, 'dat', 'w');
			$firstItem = null;
			$lastItem = null;

			foreach ($sorter as $item) {
				$itemSize = strlen($item[1]) + 1;

				// Move to the next file if this will make the current one too large.
				if ($outSize > 0 && $this->isOverMax($outSize + $itemSize + 2, $outLines + 1)) {
					$outFile->write($this->asObject ? '}' : ']');
					$outFile->close();
					$output->onSave($outIndex, $firstItem, $lastItem, $outSize + 1, $outFile->getPath());
					$outIndex++;
					$outSize = 0;
					$outLines = 0;
					$outFile = $output->openFile($this->prefix, $outIndex, 'dat', 'w');
				}

				$lastItem = $item;
				if ($outSize === 0)
					$firstItem = $item;

				$outFile->write(($outSize > 0 ? ',' : ($this->asObject ? '{' : '[')) . $item[1]);
				$outSize += $itemSize;
				$outLines++;
			}

			$outFile->write($this->asObject ? '}' : ']');
			$output->onSave($outIndex, $firstItem, $lastItem, $outSize + 1, $outFile->getPath());
			$outFile->close();

			$ret[] = $outIndex;
		}

		return $ret;
	}

}
