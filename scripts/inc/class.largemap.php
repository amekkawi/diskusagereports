<?php

interface MapOutput {
	/**
	 * @return integer The maximum size that a single item can be in a map file.
	 */
	public function getMaxPerItem();

	/**
	 * @return integer The maximum size of a single map file.
	 */
	public function getMaxPerOut();

	/**
	 * Open a map file stream.
	 *
	 * @param string $prefix The prefix for the map file name.
	 * @param integer $index The map file index to open.
	 * @param string $ext
	 * @param string $mode The fopen() mode.
	 * @return FileStream The file stream.
	 */
	public function openFile($prefix, $index, $ext, $mode);

	public function onSave($index, $size, $path);
}

class LargeMapOpenOut {
	public $index;
	public $stream;
	public $size = 0;

	public function __construct($index, FileStream $stream) {
		$this->index = $index;
		$this->stream = $stream;
	}
}

class LargeMap {

	public $prefix = 'root';

	protected $output;
	protected $outCount = 0;
	protected $maxPerOut;
	protected $outSize = 0;

	protected $openOuts = array();
	protected $maxOpenOuts = 50;

	public function __construct(MapOutput $output) {
		$this->output = $output;
		$this->maxPerOut = $this->output->getMaxPerOut();
	}

	public function getMaxPerItem() {
		return $this->output->getMaxPerItem();
	}

	public function getMaxPerOut() {
		return $this->maxPerOut;
	}

	public function getSegmentCount() {
		return $this->outCount;
	}

	public function add(KeyedJSON $item) {
		$key = $item->getKey();
		$keyJSON = json_encode($key);

		$jsonSize = $item->getJSONSize();
		if ($jsonSize === false)
			return false;

		if (strLen($keyJSON) + $jsonSize + 2 > $this->maxPerOut)
			return false;

		return $this->addJSON($key, $item->toJSON());
	}

	public function addJSON($key, $itemJSON) {
		$keyJSON = json_encode($key);
		$addLen = strLen($keyJSON) + strlen($itemJSON) + 2;
		if ($addLen > $this->maxPerOut)
			return false;

		$out = $this->findOut($addLen);
		$out->stream->write(($out->size > 0 ? ',' : '{') . $keyJSON . ':' . $itemJSON);
		$out->size += $addLen;
		return $this->outCount;
	}

	public function save() {
		foreach ($this->openOuts as $openOut) {
			$this->closeOut($openOut);
		}

		$this->openOuts = array();
	}

	protected function findOut($len) {
		/** @var $largestOut LargeMapOpenOut */
		$largestOut = null;

		/** @var $openOut LargeMapOpenOut */
		foreach ($this->openOuts as $openOut) {
			if ($largestOut === null || $largestOut->size > $openOut->size)
				$largestOut = $openOut;

			if ($openOut->size + $len + 2 < $this->maxPerOut) {
				//echo "Found open map out #{$openOut->index} with size {$openOut->size} to fit $len.\n"; usleep(50000);
				return $openOut;
			}
		}

		if (count($this->openOuts) >= $this->maxOpenOuts && $largestOut !== null) {
			$this->closeOut($largestOut);
			$largestOut->stream = $this->openOutFile();
			$largestOut->index = $this->outCount;
			//echo "Replaced with map out #{$largestOut->index}.\n"; usleep(150000);
			return $largestOut;
		}

		$newHandle = $this->openOutFile();
		$newOut = new LargeMapOpenOut($this->outCount, $newHandle);
		//echo "Opened new map out #{$newOut->index}.\n"; usleep(150000);
		$this->openOuts[] = $newOut;
		return $newOut;
	}

	protected function openOutFile() {
		return $this->output->openFile($this->prefix, ++$this->outCount, 'dat', 'w');
	}

	protected function closeOut(LargeMapOpenOut $openOut) {
		$openOut->stream->write('}');
		$openOut->stream->close();
		$this->output->onSave($openOut->index, $openOut->size, $openOut->stream->getPath());

		//echo "Closing map out #{$openOut->index} with size {$openOut->size}.\n"; //usleep(50000);
		$openOut->size = 0;
	}

}
