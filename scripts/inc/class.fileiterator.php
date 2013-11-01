<?php

class FileIterator implements Iterator {

	protected $stream = null;
	protected $readLength;

	protected $lineNum = 0;
	protected $line = null;
	protected $readBytes = 0;
	protected $length = null;
	protected $closeOnEnd = false;

	public function __construct(FileStream $stream, $readLength = 40240) {
		$this->stream = $stream;
		$this->readLength = $readLength;

		if (is_array($stat = $stream->stat()) && $stat['mode'] & 0100000)
			$this->length = $stat['size'];

		$this->next();
	}

	public function closeOnEnd() {
		$this->closeOnEnd = true;
	}

	public function position() {
		return $this->readBytes;
	}

	public function length() {
		return $this->length;
	}

	public function current() {
		return $this->line;
	}

	public function next() {
		if (!$this->stream->isOpen())
			return;

		$line = $this->stream->gets($this->readLength);

		if ($line === false) {
			$this->line = null;

			if ($this->closeOnEnd)
				$this->stream->close();
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
