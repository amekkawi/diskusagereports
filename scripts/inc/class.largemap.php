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

	/**
	 * @var ICollectionIO
	 */
	protected $output;

	protected $suffix = '.txt';

	protected $outCount = 0;
	protected $outSize = 0;

	protected $maxFileSize;
	protected $maxEntrySize;
	protected $maxOpenFiles;

	protected $openOuts = array();

	public function __construct(ICollectionIO $output, $maxFileSize, $maxPerEntry, array $options = array()) {
		$this->output = $output;
		$this->maxFileSize = $maxFileSize;
		$this->maxEntrySize = $maxPerEntry;

		if (isset($options['maxOpenFiles'])) {
			if (!is_int($options['maxOpenFiles']) || $options['maxOpenFiles'] < 4)
				throw new Exception(get_class($this) . "'s maxOpenFiles option must be an int no less than 4.");
			$this->maxOpenFiles = $options['maxOpenFiles'];
		}

		if (isset($options['suffix']) && is_string($options['suffix']))
			$this->suffix = $options['suffix'];
	}

	public function getSuffix() {
		return $this->suffix;
	}

	public function getMaxEntrySize() {
		return $this->maxEntrySize;
	}

	public function getMaxFileSize() {
		return $this->maxFileSize;
	}

	public function getMaxOpenFiles() {
		return $this->maxOpenFiles;
	}

	public function getSegmentCount() {
		return $this->outCount;
	}

	public function add(IKeyedJSON $item) {
		$key = $item->getKey();
		$keyJSON = json_encode($key);

		$jsonSize = $item->getJSONSize();
		if ($jsonSize === false)
			return false;

		if (strLen($keyJSON) + $jsonSize + 2 > $this->maxFileSize)
			return false;

		return $this->addJSON($key, $item->toJSON());
	}

	public function addJSON($key, $itemJSON) {
		$keyJSON = json_encode($key);
		$addLen = strLen($keyJSON) + strlen($itemJSON) + 2;
		if ($addLen > $this->maxFileSize)
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

			if ($openOut->size + $len + 2 < $this->maxFileSize) {
				//echo "Found open map out #{$openOut->index} with size {$openOut->size} to fit $len.\n"; //usleep(50000);
				return $openOut;
			}
		}

		if (count($this->openOuts) >= $this->maxOpenFiles && $largestOut !== null) {
			$this->closeOut($largestOut);
			$largestOut->stream = $this->openOutFile();
			$largestOut->index = $this->outCount;
			//echo "Replaced with map out #{$largestOut->index}.\n"; //usleep(150000);
			return $largestOut;
		}

		$newHandle = $this->openOutFile();
		$newOut = new LargeMapOpenOut($this->outCount, $newHandle);
		$this->openOuts[] = $newOut;

		//echo "Opened new map out #{$newOut->index}.\n"; //usleep(150000);

		return $newOut;
	}

	protected function openOutFile() {
		return $this->output->openFile($this->prefix, ++$this->outCount, $this->suffix, 'w');
	}

	protected function closeOut(LargeMapOpenOut $openOut) {
		$openOut->stream->write('}');
		$openOut->stream->close();
		$this->output->onSave($openOut->index, null, null, $openOut->size, $openOut->stream->getPath());
		$openOut->size = 0;
	}

}
