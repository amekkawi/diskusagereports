<?php

class FileIterator implements Iterator {

	private $handle = null;
	private $readLength;

	private $lineNum = 0;
	private $line = null;
	private $readBytes = 0;
	private $length = null;

	public function __construct($fileHandle, $readLength = 1024) {
		$this->handle = $fileHandle;
		$this->readLength = $readLength;

		if (is_array($stat = @fstat($this->handle)) && $stat['mode'] & 0100000)
			$this->length = $stat['size'];

		$this->next();
	}

	public function length() {
		return $this->length;
	}

	public function current() {
		return $this->line;
	}

	public function next() {
		$line = fgets($this->handle, $this->readLength);

		if ($line === false) {
			$this->line = null;
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
