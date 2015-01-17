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
 * An exception thrown when a setting in the scan file's header is invalid.
 */
class HeaderSettingException extends HeaderException {

	const INVALID_SETTING = 'INVALID_SETTING';
	const UNKNOWN_SETTING = 'UNKNOWN_SETTING';
	const MISSING_SETTING = 'MISSING_SETTING';

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
