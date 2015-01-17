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
 * An exception thrown when a scan file's header is invalid or unexpected.
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

	/**
	 * @param string $reason A constant specified in this class (e.g. TOO_SHORT).
	 */
	public function __construct($reason) {
		parent::__construct($reason);
	}
}

/**
 * An exception thrown when a setting in the scan file's header is invalid.
 */
class HeaderSettingException extends HeaderException {

	/**
	 * @var string The name of the setting.
	 */
	public $name;

	/**
	 * @param string $reason One of the following constants specified in this class: INVALID_SETTING, UNKNOWN_SETTING, MISSING_SETTING.
	 * @param string $name The name of the setting.
	 */
	public function __construct($reason, $name) {
		parent::__construct($reason);
		$this->name = $name;
	}
}

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

/**
 * An exception thrown while processing a scan file.
 */
class ScanException extends Exception {
	const FOPEN_FAIL = 'FOPEN_FAIL';
	const HEADER_EXCEPTION = 'HEADER_EXCEPTION';
	const POPDIR_NOPARENT = 'POPDIR_NOPARENT';

	/**
	 * @param string $reason Either one of the constants specified in this file, or a human-readable error message.
	 */
	public function __construct($reason) {
		parent::__construct($reason);
	}
}

/**
 * An exception thrown by {@link Options} for invalid option values.
 */
class OptionException extends Exception {

	/**
	 * @var string The name of the option.
	 */
	protected $param;

	/**
	 * @var string The human-readable error message.
	 */
	protected $reason;

	/**
	 * @param string $reason A human-readable error message.
	 * @param int    $param The name of the option.
	 */
	public function __construct($reason, $param) {
		$this->reason = $reason;
		$this->param = $param;
		parent::__construct($this->getReason($param));
	}

	/**
	 * Get the human-readable error message.
	 * @param null|string $param Optionally specify the way the param name should be displayed.
	 * @return string
	 */
	public function getReason($param = null) {
		return sprintf($this->reason, $param === null ? $this->param : $param);
	}

	/**
	 * Get the name of the option.
	 * @return string
	 */
	public function getParam() {
		return $this->param;
	}
}
