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

/**
 * Iterates through lines in a {@link FileStream}.
 */
class FileIterator implements Iterator {

	/**
	 * @var FileStream|null
	 */
	protected $stream = null;

	/**
	 * @var int The number of lines read.
	 */
	protected $lineNum = 0;

	/**
	 * @var null|string The current line.
	 */
	protected $line = null;

	/**
	 * @var int The total number of bytes read.
	 */
	protected $readBytes = 0;

	/**
	 * @var null|int The maximum number of bytes to read from the stream. Null if it cannot be determined.
	 */
	protected $length = null;

	/**
	 * @var int See $options in __construct
	 */
	protected $readLength = 40240;

	/**
	 * @var bool See $options in __construct
	 */
	protected $closeOnEnd = false;

	/**
	 * @var bool See $options in __construct
	 */
	protected $unlinkOnEnd = false;

	/**
	 * @var bool See $options in __construct
	 */
	protected $unserialize = false;

	/**
	 * Create a FileIterator for the specified stream.
	 *
	 * <p>Options:
	 * * $options['readLength'] See {@link FileStream::gets()}.
	 * * $options['unserialize'] Set to true to unserialize each line.
	 * * $options['closeOnEnd'] Set to true to close the stream once the end has been reached.
	 * * $options['unlinkOnEnd'] Set to true to unlink (delete) the stream's file once the end has been reached. See {@link FileStream::Unlink}.
	 *
	 * @param FileStream $stream The stream to read from.
	 * @param array      $options
	 * @throws Exception
	 */
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

	/**
	 * @return int Get the number of bytes read from the stream.
	 */
	public function position() {
		return $this->readBytes;
	}

	/**
	 * @return int|null Get the maximum number of bytes to read from the stream. Null if it cannot be determined.
	 */
	public function length() {
		return $this->length;
	}

	/**
	 * @inheritdoc
	 */
	public function current() {
		return $this->line;
	}

	/**
	 * @inheritdoc
	 */
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

	/**
	 * @inheritdoc
	 */
	public function key() {
		return $this->lineNum > 0 ? $this->lineNum : null;
	}

	/**
	 * @inheritdoc
	 */
	public function valid() {
		return $this->line !== null;
	}

	/**
	 * @inheritdoc
	 */
	public function rewind() {
		if ($this->lineNum == 0)
			return;

		$this->stream->rewind();
		$this->lineNum = 0;
		$this->readBytes = 0;
		$this->next();
	}
}
