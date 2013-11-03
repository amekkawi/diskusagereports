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

class HeaderException extends Exception {

	const TOO_SHORT = 'TOO_SHORT';
	const INVALID_DATETIME = 'INVALID_DATETIME';
	const COLUMNS_MISSING = 'COLUMNS_MISSING';
	const UNSUPPORTED_SCAN_VERSION = 'UNSUPPORTED_SCAN_VERSION';
	const INVALID_COLUMN_SEPARATOR = 'INVALID_COLUMN_SEPARATOR';
	const INVALID_DIR_SEPARATOR = 'INVALID_DIR_SEPARATOR';
	const INVALID_SETTING = 'INVALID_SETTING';
	const UNKNOWN_SETTING = 'UNKNOWN_SETTING';
	const MISSING_SETTING = 'MISSING_SETTING';
	const INVALID_CHARACTERS = 'INVALID_CHARACTERS';
	const UNEXPECTED_HEADER = 'UNEXPECTED_HEADER';

	public function __construct($reason) {
		parent::__construct($reason);
	}
}

class HeaderSettingException extends HeaderException {
	public $name;

	public function __construct($reason, $name) {
		parent::__construct($reason);
		$this->name = $name;
	}
}

class LineException extends Exception {
	const TOO_LONG = 'TOO_LONG';
	const PATTERN_MISMATCH = 'PATTERN_MISMATCH';
	const COLUMN_COUNT = 'COLUMN_COUNT';
	const EMPTY_PATH = 'EMPTY_PATH';
	const INVALID_ENCODING = 'INVALID_ENCODING';

	public $line;
	public $column;

	/**
	 * @param string $reason
	 * @param string $line
	 * @param int    $column
	 */
	public function __construct($reason, $line, $column = -1) {
		parent::__construct($reason);
		$this->line = $line;
		$this->column = $column;
	}
}

class ScanException extends Exception {
	const FOPEN_FAIL = 'FOPEN_FAIL';
	const HEADER_EXCEPTION = 'HEADER_EXCEPTION';
	const POPDIR_NOPARENT = 'POPDIR_NOPARENT';

	public function __construct($reason) {
		parent::__construct($reason);
	}
}

class OptionException extends Exception {

	protected $param;
	protected $reason;

	public function __construct($param, $reason) {
		parent::__construct($this->getReason($param));
	}

	public function getReason($param = null) {
		return sprintf($this->reason, $param === null ? $this->param : $param);
	}

	public function getParam() {
		return $this->param;
	}
}
