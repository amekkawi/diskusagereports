<?php

interface ListOutput {
	public function getMaxSegments();
	public function isOverMax($size, $count);
	public function openTempFile($prefix, $index, $mode);
	public function deleteTempFile($prefix, $index);
	public function openOutFile($prefix, $index, $mode = 'w');
	public function compare($a, $b);
}

class LargeList {

	public $prefix = 'root';

	protected $maxPerTemp;
	protected $totalSize = 0;
	protected $tempCount = 0;
	protected $tempSize;
	protected $list;
	protected $outputs;

	public function __construct(array $outputs = null) {
		$this->maxPerTemp = 80 * 1024;
		$this->outputs = $outputs;
		$this->startNew();
	}

	public function getTotalSize() {
		return $this->totalSize;
	}

	public function toJSON() {
		if ($this->tempCount > 0)
			throw new Exception("Cannot convert list with multiple segments to JSON");

		$ret = '';
		foreach ($this->list as $item) {
			$ret .= ',' . $item[1];
		}

		if ($ret == '')
			return '[]';

		$ret[0] = '[';
		return $ret . ']';
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

		if ($this->tempSize > 0 && $this->tempSize + $addLen > $this->maxPerTemp) {
			$this->saveTemp();
		}

		$this->totalSize += $addLen;
		$this->tempSize += $addLen;
		$this->list[] = array($compareVal, $itemJSON);
	}

	protected function saveTemp() {
		$this->tempCount++;

		echo "Saving temp #{$this->tempCount} with " . count($this->list) . " items at {$this->tempSize} bytes...\n";

		foreach ($this->outputs as $output) {
			$tempFile = $output->openTempFile($this->prefix, $this->tempCount, 'w');

			// Sort each output.
			usort($this->list, array($output, 'compare'));

			// Write each list item serialized on its own line.
			foreach ($this->list as $item) {
				if (!isset($item[2]))
					$item[2] = serialize($item);

				fwrite($tempFile, $item[2] . "\n");
			}

			fclose($tempFile);
		}

		$this->startNew();
	}

	protected function startNew() {
		$this->list = array();
		$this->tempSize = 0;
	}

	public function compare($a, $b) {
		if ($a[0] < $b[0])
			return -1;
		if ($a[0] > $b[0])
			return 1;
		return 0;
	}

	public function save() {

		foreach ($this->outputs as $output) {
			// Sort the list.
			usort($this->list, array($output, 'compare'));

			// Create a list of iterators with the list as one of them.
			$iterators = array(new ArrayIterator($this->list));
			$currentValues = array();

			// Get the current unserialized value for the list.
			$currentValues[] = $iterators[0]->valid() ? $iterators[0]->current() : null;

			// Add iterators for any temp files and get their current unserialized values.
			for ($i = 1; $i <= $this->tempCount; $i++) {
				$iterator = new FileIterator($output->openTempFile($this->prefix, $i, 'r'));
				$iterator->closeOnEnd();
				$iterators[] = $iterator;
				$currentValues[] = $iterator->valid() ? unserialize($iterator->current()) : null;
			}

			$outIndex = 1;
			$outSize = 0;
			$outLines = 0;
			$outFile = $output->openOutFile($this->prefix, $outIndex);

			do {
				$topIndex = null;
				$topVal = null;

				foreach ($currentValues as $i => $currentValue) {
					if ($currentValue !== null) {
						if ($topIndex === null || $output->compare($topVal, $currentValue) < 0) {
							$topIndex = $i;
							$topVal = $currentValue;
						}
					}
				}

				if ($topIndex !== null) {
					$topSize = strlen($topVal[1]) + 1;

					// Move to the next file if this will make the current one too large.
					if ($outSize > 0 && $output->isOverMax($outSize + $topSize + 2, $outLines + 1)) {
						fwrite($outFile, ']');
						fclose($outFile);
						$outIndex++;
						$outSize = 0;
						$outLines = 0;
						$outFile = $output->openOutFile($this->prefix, $outIndex);
					}

					fwrite($outFile, ($outSize > 0 ? ',' : '[') . $topVal[1]);
					$outSize += $topSize + 1;
					$outLines++;

					$iterator = $iterators[$topIndex];
					$iterator->next();
					$currentValues[$topIndex] = $iterator->valid() ? (is_string($iterator->current()) ? unserialize($iterator->current()) : $iterator->current()) : null;
				}

			} while ($topIndex !== null);

			// Delete temp files.
			for ($i = 1; $i <= $this->tempCount; $i++) {
				$output->deleteTempFile($this->prefix, $i);
			}
		}
	}

}
