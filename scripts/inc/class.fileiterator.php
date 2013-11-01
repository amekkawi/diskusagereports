<?php

class FileIterator implements Iterator {

	protected $handle = null;
	protected $readLength;

	protected $lineNum = 0;
	protected $line = null;
	protected $readBytes = 0;
	protected $length = null;
	protected $closeOnEnd = false;

	public function __construct($fileHandle, $readLength = 40240) {
		$this->handle = $fileHandle;
		$this->readLength = $readLength;

		if (is_array($stat = @fstat($this->handle)) && $stat['mode'] & 0100000)
			$this->length = $stat['size'];

		$this->next();
	}

	public function closeOnEnd() {
		$this->closeOnEnd = true;
	}

	public function length() {
		return $this->length;
	}

	public function current() {
		return $this->line;
	}

	public function next() {
		if ($this->handle === null)
			return;

		$line = fgets($this->handle, $this->readLength);

		if ($line === false) {
			$this->line = null;

			if ($this->closeOnEnd) {
				fclose($this->handle);
				$this->handle = null;
			}
		}
		else {
			$this->lineNum++;
			$this->readBytes += strlen($line);
			$this->line = rtrim($line, "\n\r");
		}
	}

	public function key() {
		return $this->lineNum > 0 ? $this->lineNum : null;
	}

	public function valid() {
		return $this->line !== null;
	}

	public function rewind() {
		if ($this->lineNum > 1)
			throw new Exception("Cannot rewind FileIterator");
	}
}
