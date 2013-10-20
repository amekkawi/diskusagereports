<?php

interface ListOutput {
	public function getMaxSegments();
	public function openTempFile($prefix, $index, $mode);
	public function openOutFile($prefix, $index, $mode = 'w');
	public function compare($a, $b);
}

class LargeList {

	public $prefix = 'root_';

	protected $maxPerTemp;
	protected $tempCount = 0;
	protected $tempSize;
	protected $list;
	protected $outputs;

	public function __construct(array $outputs = null) {
		$this->maxPerTemp = 80 * 1024;
		$this->outputs = $outputs;
		$this->startNew();
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

		if ($this->tempSize + $addLen > $this->maxPerTemp) {
			$this->saveTemp();
		}

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

}
