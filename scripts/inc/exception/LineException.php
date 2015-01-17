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
 * An exception thrown for an invalid line in the scan file.
 */
class LineException extends Exception {
	const TOO_LONG = 'TOO_LONG';
	const PATTERN_MISMATCH = 'PATTERN_MISMATCH';
	const COLUMN_COUNT = 'COLUMN_COUNT';
	const EMPTY_PATH = 'EMPTY_PATH';
	const INVALID_ENCODING = 'INVALID_ENCODING';

	/**
	 * @var string The raw line from the scan file.
	 */
	public $line;

	/**
	 * @var int The column number that is invalid, or -1 if it is a problem with the entire line.
	 */
	public $column;

	/**
	 * @param string $reason One of the constants specified in this class.
	 * @param string $line The raw line from the scan file.
	 * @param int    $column The column number that is invalid, or -1 if it is a problem with the entire line.
	 */
	public function __construct($reason, $line, $column = -1) {
		parent::__construct($reason);
		$this->line = $line;
		$this->column = $column;
	}
}
