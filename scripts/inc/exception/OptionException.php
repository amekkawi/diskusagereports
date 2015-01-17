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
