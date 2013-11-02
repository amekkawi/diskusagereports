<?php

class FileIterator implements Iterator {

	protected $stream = null;
	protected $readLength = 40240;

	protected $lineNum = 0;
	protected $line = null;
	protected $readBytes = 0;
	protected $length = null;
	protected $closeOnEnd = false;
	protected $unlinkOnEnd = false;
	protected $unserialize = false;

	public function __construct(FileStream $stream, array $options = array()) {
		$this->stream = $stream;

		if (isset($options['readLength']) && is_int($options['readLength']))
			$this->readLength = $options['readLength'];

		if (isset($options['unserialize']) && is_bool($options['unserialize']))
			$this->unserialize = $options['unserialize'];

		if (isset($options['closeOnEnd']) && is_bool($options['closeOnEnd']))
			$this->closeOnEnd = $options['closeOnEnd'];

		if (isset($options['unlinkOnEnd']) && is_bool($options['unlinkOnEnd']))
			$this->unlinkOnEnd = $options['unlinkOnEnd'];

		if (is_array($stat = $stream->stat()) && $stat['mode'] & 0100000)
			$this->length = $stat['size'];

		$this->next();
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

			if ($this->unlinkOnEnd)
				$this->stream->unlink();
			elseif ($this->closeOnEnd)
				$this->stream->close();
		}
		else {
			$this->lineNum++;
			$this->readBytes += strlen($line);
			$this->line = rtrim($line, "\n\r");
			if ($this->unserialize) {
				$this->line = unserialize($line);
				if ($this->line === false)
					throw new Exception("Failed to unserialize line: $line");
			}
		}
	}

	public function key() {
		return $this->lineNum > 0 ? $this->lineNum : null;
	}

	public function valid() {
		return $this->line !== null;
	}

	public function rewind() {
		if ($this->lineNum == 0)
			return;

		$this->stream->rewind();
		$this->lineNum = 0;
		$this->readBytes = 0;
		$this->next();
	}
}
