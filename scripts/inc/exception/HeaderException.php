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
	const INVALID_CHARACTERS = 'INVALID_CHARACTERS';
	const UNEXPECTED_HEADER = 'UNEXPECTED_HEADER';

	/**
	 * @param string $reason A constant specified in this class (e.g. TOO_SHORT).
	 */
	public function __construct($reason) {
		parent::__construct($reason);
	}
}
