<?php

class IOException extends Exception {

	/**
	 * @var FileStream
	 */
	protected $writer;

	public function __construct($message = "", FileStream $writer) {
		parent::__construct($message);
		$this->writer = $writer;
	}

	/**
	 * @return FileStream
	 */
	public function getWriter() {
		return $this->writer;
	}
}

class FileStream {

	/**
	 * @var resource
	 */
	protected $handle;

	/**
	 * @var string
	 */
	protected $path;

	/**
	 * @var string
	 */
	protected $mode;

	/**
	 * @var boolean
	 */
	protected $isOpen;

	/**
	 * @param string $path
	 * @param string $mode
	 *
	 * @throws IOException
	 */
	public function __construct($path, $mode = 'r') {
		$this->path = $path;
		$this->mode = $mode;
		$this->handle = fopen($path, $mode);
		if ($this->handle === false)
			throw new IOException("Failed to open file for '$mode': $path", $this);
		$this->isOpen = true;
	}

	/**
	 * @return resource
	 */
	public function getHandle() {
		return $this->handle;
	}

	/**
	 * @return string
	 */
	public function getMode() {
		return $this->mode;
	}

	/**
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * @return boolean
	 */
	public function isOpen() {
		return $this->isOpen;
	}

	/**
	 * Writes the contents of string to the file stream.
	 *
	 * @param string   $string The string that is to be written.
	 * @param int|null $length If the length argument is given, writing will stop after
	 *                         length bytes have been written or the end of string is reached,
	 *                         whichever comes first.
	 *
	 * @return int Number of bytes written.
	 * @throws IOException
	 */
	public function write($string, $length = null) {
		if ($length === null)
			$result = fwrite($this->handle, $string);
		else
			$result = fwrite($this->handle, $string, $length);

		if ($result === false)
			throw new IOException("Failed to write to file.", $this);

		return $result;
	}

	/**
	 * Gets line from file stream.
	 *
	 * @param int|null $length Reading ends when length - 1 bytes have been read, or a newline
	 *                         (which is included in the return value), or an EOF (whichever
	 *                         comes first). If no length is specified, it will keep reading
	 *                         from the stream until it reaches the end of the line.
	 *
	 * @return string Returns a string of up to length - 1 bytes read from the file pointed to by handle.
	 *                If there is no more data to read in the file pointer, then FALSE is returned.
	 *
	 * @throws IOException
	 */
	public function gets($length = null) {
		$string = $length === null ? fgets($this->handle) : fgets($this->handle, $length);

		if ($string === false && !(@feof($this->handle)))
			throw new IOException("Failed to read line from file.", $this);

		return $string;
	}

	public function flush() {
		if (fflush($this->handle) === false)
			throw new IOException("Failed to flush the stream.", $this);
	}

	public function eof() {
		return feof($this->handle);
	}

	public function tell() {
		if (($result = ftell($this->handle)) === false)
			throw new IOException("Failed to get the current position of the file stream.", $this);

		return $result;
	}

	public function stat() {
		return @fstat($this->handle);
	}

	public function rewind() {
		if (rewind($this->handle) === false)
			throw new IOException("Failed to rewind the file stream.", $this);
	}

	public function seek($offset, $whence = SEEK_SET) {
		if (fseek($this->handle, $offset, $whence) !== 0)
			throw new IOException("Failed to seek the file stream.", $this);
	}

	public function seekToEnd() {
		$this->seek(-1, SEEK_END);
	}

	/**
	 * Close the file.
	 *
	 * @param bool $ignoreError Whether or not to throw an IOException if the file fails to close.
	 *
	 * @throws IOException
	 */
	public function close($ignoreError = true) {
		$this->isOpen = false;
		if (fclose($this->handle) === false && !$ignoreError)
			throw new IOException("Failed to close file.", $this);
	}
}
