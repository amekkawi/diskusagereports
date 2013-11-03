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

class Options {

	const MAX_SUPPORTED_SCAN_VERSION = 2;

	const VERBOSITY_QUIET = 0;
	const VERBOSITY_NORMAL = 1;
	const VERBOSITY_VERBOSE = 2;
	const VERBOSITY_VERY_VERBOSE = 3;
	const VERBOSITY_DEBUG = 4;

	/**
	 * @var int The verbosity level of output.
	 */
	public $verbosity = self::VERBOSITY_NORMAL;

	/**
	 * @var int The version of the file listing.
	 */
	public $scanVersion = 1;

	/**
	 * @var string The character that separates columns.
	 */
	public $delim = "\x00";

	/**
	 * @var string The director separator.
	 */
	public $ds = DIRECTORY_SEPARATOR;

	/**
	 * @var string The time the files were scanned.
	 */
	public $scantime = null;

	/**
	 * @var string
	 */
	public $dirname = '';

	/**
	 * @var string
	 */
	public $basename = null;

	/**
	 * @var string
	 */
	public $datetimeformat = 'timestamp';

	/**
	 * @var bool
	 */
	public $escaped = false;

	/**
	 * @var int The maximum length that a line can be in the file list.
	 */
	public $maxLineLength = 1024;

	// Default to version 1 columns indexes.
	public $colCount = 6;
	public $col_type = 0;
	public $col_date = 1;
	public $col_time = 2;
	public $col_size = 3;
	public $col_depth = 4;
	public $col_path = 5;

	/**
	 * @var string RegEx pattern that validates a line.
	 */
	public $lineRegEx;

	/**
	 * @var bool Whether or not to display the full path of the root directory in the report.
	 */
	public $includeFullPath = false;

	/**
	 * @var null|string The text will display in the header of the report.
	 */
	public $reportName = null;

	/**
	 * @var bool Whether or not to allow the directory tree UI.
	 */
	public $disableDirectoryTree = false;

	/**
	 * @var int The minimum number of seconds that must elapse before another progress message
	 *          (e.g. 'Processed X lines ...') is outputted. Default is 15 seconds.
	 */
	public $progressMessageSeconds = 15;

	/**
	 * @var string The suffix of report files.
	 */
	public $suffix = '.txt';

	/**
	 * @var string The timezone for the report.
	 */
	public $timezone = null;

	public $fileListDepth = true;
	public $topListDepth = 3;
	public $fileSizesDepth = 6;
	public $fileTypesDepth = 6;
	public $modifiedDatesDepth = 6;

	public function __construct() {
		$this->buildLineRegEx();
	}

	public function processHeader($line) {
		if (substr($line, 1, 2) != '# ') {
			// Process it as an old-style header.
			$this->processOldHeader($line);
			return;
		}

		// Fail if the header is too short or too long.
		if (strlen($line) < 2)
			throw new HeaderException(HeaderException::TOO_SHORT);

		// Adjust the column indexes.
		$this->colCount = 5;
		$this->col_type = 0;
		$this->col_date = 1;
		$this->col_time = 2;
		$this->col_size = 3;
		$this->col_depth = null;
		$this->col_path = 4;

		$splitHeader = explode(' ', substr($line, 3), 6);

		// Make sure the header has the minimum number of columns.
		if (count($splitHeader) < 6)
			throw new HeaderException(HeaderException::COLUMNS_MISSING);

		// Make sure the scan version is supported.
		if (($this->scanVersion = intval(substr($splitHeader[0], 1))) > Options::MAX_SUPPORTED_SCAN_VERSION)
			throw new HeaderException(HeaderException::UNSUPPORTED_SCAN_VERSION);

		// Make sure the field and directory separators are valid.
		if (!preg_match('/^[0-9]{1,3}$/', $splitHeader[1]) || intval($splitHeader[1]) >= 256)
			throw new HeaderException(HeaderException::INVALID_COLUMN_SEPARATOR);

		if (!preg_match('/^[0-9]{1,3}$/', $splitHeader[2]) || intval($splitHeader[2]) >= 256)
			throw new HeaderException(HeaderException::INVALID_DIR_SEPARATOR);

		// Set the field separator.
		$this->delim = chr(intval($splitHeader[1]));

		// Set the directory separator.
		$this->ds = chr(intval($splitHeader[2]));

		// Set when the file scan was made.
		$this->scantime = substr($splitHeader[3] . " " . $splitHeader[4], 0, 19);

		// Validate the header's timestamp.
		if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $this->scantime))
			throw new HeaderException(HeaderException::INVALID_DATETIME);

		// Settings from file scan's header.
		$settings = Util::ExplodeEscaped(" ", $splitHeader[5]);

		// Process the settings.
		foreach ($settings as $setting) {
			if (strlen(trim($setting))) {
				$setting = explode(":", $setting, 2);
				$setting[0] = strtolower($setting[0]);
				$hasValue = count($setting) > 1;

				// Validate the setting.
				switch ($setting[0]) {
					case "basename":
						if (!$hasValue)
							throw new HeaderSettingException(HeaderException::INVALID_SETTING, $setting[0]);
						break;
					case "dirname":
						if (!$hasValue)
							throw new HeaderSettingException(HeaderException::INVALID_SETTING, $setting[0]);
						break;
					case "datetimeformat":
						if (!$hasValue || $setting[1] !== 'timestamp')
							throw new HeaderSettingException(HeaderException::INVALID_SETTING, $setting[0]);
						break;
					case "escaped":
						// TODO: Why are we ignoring the value?
						$setting[1] = true;
						break;
					default:
						throw new HeaderSettingException(HeaderException::UNKNOWN_SETTING, $setting[0]);
				}

				$this->$setting[0] = $setting[1];
			}
		}

		if (is_null($this->basename))
			throw new HeaderSettingException(HeaderException::MISSING_SETTING, 'basename');

		// Rebuild the lineRegEx.
		$this->buildLineRegEx();
	}

	protected function processOldHeader($line) {

		// Fail if the header is too short or too long.
		if (strlen($line) < 2)
			throw new HeaderException(HeaderException::TOO_SHORT);

		// The first character after the pound-sign is the delim.
		$this->delim = substr($line, 1, 1);

		// Explode the remaining part of the header.
		$splitHeader = explode($this->delim, substr($line, 2));

		// Only check that the header has a *minimum* number of columns,
		// to allow future versions to add more.
		if (count($splitHeader) < 3)
			throw new HeaderException(HeaderException::COLUMNS_MISSING);

		// Make sure all the strings are UTF-8 valid
		for ($i = 1; $i < count($splitHeader); $i++) {
			if (json_encode($splitHeader[$i]) == 'null' && ($splitHeader[$i] = iconv('Windows-1252', 'UTF-8', $splitHeader[$i])) === FALSE)
				throw new HeaderException(HeaderException::INVALID_CHARACTERS);
		}

		// Override the directory separator.
		$this->ds = $splitHeader[0];

		// Make sure the directory separator is a single character.
		if (strlen($this->ds) != 1)
			throw new HeaderException(HeaderException::INVALID_DIR_SEPARATOR);

		$this->dirname = $splitHeader[1];
		$this->basename = $splitHeader[2];
		$this->scantime = substr($splitHeader[3], 0, 19);

		// Validate the header's timestamp.
		if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $this->scantime))
			throw new HeaderException(HeaderException::INVALID_DATETIME);

		// Rebuild the lineRegEx.
		$this->buildLineRegEx();
	}

	// Create the regular expression to validate lines.
	protected function buildLineRegEx() {
		switch ($this->scanVersion) {
			case 1:
				$this->lineRegEx = '/^' .
					implode(preg_quote($this->delim), array(
						'[dflcbpu]',
						'[0-9]{4}-[0-9]{2}-[0-9]{2}', // Date
						'[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?', // Time
						'[0-9]+', // Size
						'[0-9]+' // Depth
					)) . preg_quote($this->delim) . '/';
				break;

			case 2:
				$this->lineRegEx = '/^' .
					implode(preg_quote($this->delim), array(
						'[dflcbpus\-]',
						'[0-9]{4}-[0-9]{2}-[0-9]{2}', // Date
						'[0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?', // Time
						'[0-9]+', // Size
					)) . preg_quote($this->delim) . '/';
				break;
		}
	}
}
