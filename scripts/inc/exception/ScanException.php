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
