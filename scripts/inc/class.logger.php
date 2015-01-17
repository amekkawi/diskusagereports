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
 * Logging helper class.
 */
class Logger {

	const LEVEL_QUIET = 0;
	const LEVEL_NORMAL = 1;
	const LEVEL_VERBOSE = 2;
	const LEVEL_VERY_VERBOSE = 3;
	const LEVEL_DEBUG1 = 4;
	const LEVEL_DEBUG2 = 5;
	const LEVEL_DEBUG3 = 6;

	/**
	 * @var int The current log level.
	 */
	protected static $level = self::LEVEL_NORMAL;

	/**
	 * @var null Cached IO stream to stderr.
	 */
	protected static $stdErr = null;

	/**
	 * @return int Get the log level.
	 */
	public static function getLevel() {
		return self::$level;
	}

	/**
	 * Set the log level.
	 *
	 * @param int $level
	 * @throws Exception
	 */
	public static function setLevel($level) {
		if (!is_int($level))
			throw new Exception("Logger level must be an integer");

		self::$level = max(self::LEVEL_QUIET, min(self::LEVEL_DEBUG3, $level));
	}

	/**
	 * Check if logging should be performed for the specified level.
	 *
	 * @param int $level
	 * @return bool
	 */
	public static function doLevel($level) {
		return $level <= self::$level;
	}

	/**
	 * Log a message to stdout.
	 *
	 * @param string $message
	 * @param int    $level
	 * @param bool   $newLine Set to false to not include a newline (\n) at the end of the message.
	 */
	public static function log($message, $level = self::LEVEL_NORMAL, $newLine = true) {
		if ($level <= self::$level) {
			if ($level > 2) echo str_repeat(' ', $level - 2);
			echo $message;
			if ($newLine) echo "\n";
		}
	}

	/**
	 * Log an error message to stderr.
	 *
	 * @param string $message
	 * @param bool   $newLine Set to false to not include a newline (\n) at the end of the message.
	 */
	public static function error($message, $newLine = true) {
		if (self::$stdErr === null)
			self::$stdErr = fopen('php://stderr', 'w');

		fwrite(self::$stdErr, $message . ($newLine ? "\n" : ''));
	}
}
