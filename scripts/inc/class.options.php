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

	/**
	 * @var null|string The directory path that will contain the report.
	 */
	protected $reportDirectory = null;

	/**
	 * @var int The version of the file listing.
	 */
	protected $scanVersion = 1;

	/**
	 * @var string The character that separates columns.
	 */
	protected $delim = "\x00";

	/**
	 * @var string The director separator.
	 */
	protected $directorySeparator = DIRECTORY_SEPARATOR;

	/**
	 * @var string The time the files were scanned.
	 */
	protected $scantime = null;

	/**
	 * @var string
	 */
	protected $dirname = '';

	/**
	 * @var string
	 */
	protected $basename = null;

	/**
	 * @var string
	 */
	protected $datetimeformat = 'timestamp';

	/**
	 * @var bool
	 */
	protected $escaped = false;

	/**
	 * @var int The maximum length that a line can be in the file list.
	 */
	protected $maxLineLength = 1024;

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
	protected $lineRegEx;

	/**
	 * @var int The maximum size that a temp file can be.
	 */
	protected $maxTempKB = 200;

	/**
	 * @var bool Whether or not to display the full path of the root directory in the report.
	 */
	protected $includeFullPath = false;

	/**
	 * @var null|string The text will display in the header of the report.
	 */
	protected $reportName = null;

	/**
	 * @var bool Whether or not to allow the directory tree UI.
	 */
	protected $disableDirectoryTree = false;

	/**
	 * @var int The minimum number of seconds that must elapse before another progress message
	 *          (e.g. 'Processed X lines ...') is outputted. Default is 15 seconds.
	 */
	protected $progressMessageSeconds = 15;

	/**
	 * @var string The suffix of report files.
	 */
	protected $suffix = '.txt';

	/**
	 * @var string The timezone for the report.
	 */
	protected $timezone = null;

	/**
	 * @var bool|int The maximum sub-directory depth that will contain a file listing.
	 *               Setting to true is infinite depth, and false disables it entirely.
	 */
	protected $fileListDepth = true;

	/**
	 * @var bool|int The maximum sub-directory depth that will contain a top files listing.
	 *               Setting to true is infinite depth, and false disables it entirely.
	 */
	protected $topListDepth = 6;

	/**
	 * @var bool|int The maximum sub-directory depth that will contain a file sizes listing.
	 *               Setting to true is infinite depth, and false disables it entirely.
	 */
	protected $fileSizesDepth = 6;

	/**
	 * @var bool|int The maximum sub-directory depth that will contain a file types listing.
	 *               Setting to true is infinite depth, and false disables it entirely.
	 */
	protected $fileTypesDepth = 6;

	/**
	 * @var bool|int The maximum sub-directory depth that will contain a modified dates listing.
	 *               Setting to true is infinite depth, and false disables it entirely.
	 */
	protected $modifiedDatesDepth = 6;

	/**
	 * @var int The maximum number of items that are displayed per page.
	 */
	protected $maxPerPage = 100;

	/**
	 * @var int The maximum size that a 'dirmap' file can be.
	 */
	protected $maxDirMapKB = 40;

	/**
	 * @var int The maximum size that a 'filesmap' file can be.
	 */
	protected $maxFileListMapKB = 80;

	/**
	 * @var int The maximum number of pages that a 'files' file can contain per sort.
	 */
	protected $maxFileListFilePages = 2;

	/**
	 * @var int The maximum size that a 'subdirsmap' file can be.
	 */
	protected $maxSubDirsMapKB = 20;

	/**
	 * @var int The maximum number of pages that a 'subdirs' file can contain per sort.
	 */
	protected $maxSubDirsFilePages = 2;

	public $sizeGroups;

	public function __construct() {
		$this->buildLineRegEx();

		$this->sizeGroups = array(
			array('label' => '1 GB or More', 'size' => 1024 * 1024 * 1024),
			array('label' => '500 MB - 1 GB', 'size' => 1024 * 1024 * 500),
			array('label' => '250 MB - 500 MB', 'size' => 1024 * 1024 * 250),
			array('label' => '125 MB - 250 MB', 'size' => 1024 * 1024 * 125),
			array('label' => '75 MB - 125 MB', 'size' => 1024 * 1024 * 75),
			array('label' => '25 MB - 75 MB', 'size' => 1024 * 1024 * 25),
			array('label' => '10 MB - 25 MB', 'size' => 1024 * 1024 * 10),
			array('label' => '5 MB - 10 MB', 'size' => 1024 * 1024 * 5),
			array('label' => '1 MB - 5 MB', 'size' => 1024 * 1024 * 1),
			array('label' => '500 KB - 1 MB', 'size' => 1024 * 500),
			array('label' => '250 KB - 500 KB', 'size' => 1024 * 250),
			array('label' => '100 KB - 250 KB', 'size' => 1024 * 100),
			array('label' => '50 KB - 100 KB', 'size' => 1024 * 50),
			array('label' => '25 KB - 50 KB', 'size' => 1024 * 25),
			array('label' => '10 KB - 25 KB', 'size' => 1024 * 10),
			array('label' => '5 KB - 10 KB', 'size' => 1024 * 5),
			array('label' => '1 KB - 5 KB', 'size' => 1024 * 1),
			array('label' => 'Less than 1 KB', 'size' => 0)
		);
	}

	public function isValidLine($line) {
		return preg_match($this->lineRegEx, $line);
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
		$this->directorySeparator = chr(intval($splitHeader[2]));

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
		$this->directorySeparator = $splitHeader[0];

		// Make sure the directory separator is a single character.
		if (strlen($this->directorySeparator) != 1)
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

	public function buildPath($extension) {
		return $this->reportDirectory . DIRECTORY_SEPARATOR . $extension;
	}

	/**
	 * @return string
	 */
	public function getBasename() {
		return $this->basename;
	}

	/**
	 * @return string
	 */
	public function getDirname() {
		return $this->dirname;
	}

	/**
	 * @return int
	 */
	public function getScanVersion() {
		return $this->scanVersion;
	}

	/**
	 * @return string
	 */
	public function getScantime() {
		return $this->scantime;
	}

	/**
	 * @return boolean
	 */
	public function getEscaped() {
		return $this->escaped;
	}

	/**
	 * @return string
	 */
	public function getDatetimeformat() {
		return $this->datetimeformat;
	}

	/**
	 * @param string $delim
	 */
	public function setDelim($delim) {
		$this->delim = $delim;
	}

	/**
	 * @return string
	 */
	public function getDelim() {
		return $this->delim;
	}

	/**
	 * @param boolean $disableDirectoryTree
	 *
	 * @throws OptionException
	 */
	public function setDisableDirectoryTree($disableDirectoryTree) {
		if (!is_bool($disableDirectoryTree))
			throw new OptionException('%s must be a string', '$disableDirectoryTree');
		$this->disableDirectoryTree = $disableDirectoryTree;
	}

	/**
	 * @return boolean
	 */
	public function getDisableDirectoryTree() {
		return $this->disableDirectoryTree;
	}

	/**
	 * @param string $directorySeparator
	 */
	public function setDirectorySeparator($directorySeparator) {
		$this->directorySeparator = $directorySeparator;
	}

	/**
	 * @return string
	 */
	public function getDirectorySeparator() {
		return $this->directorySeparator;
	}

	/**
	 * @param bool|int $fileListDepth
	 *
	 * @throws OptionException
	 */
	public function setFileListDepth($fileListDepth) {
		if ((!is_int($fileListDepth) && !is_bool($fileListDepth)) || (is_int($fileListDepth) && $fileListDepth < 0))
			throw new OptionException('%s must be a boolean or an integer no less than 0', '$fileListDepth');
		$this->fileListDepth = $fileListDepth;
	}

	/**
	 * @return bool|int
	 */
	public function getFileListDepth() {
		return $this->fileListDepth;
	}

	/**
	 * @param bool|int $fileSizesDepth
	 *
	 * @throws OptionException
	 */
	public function setFileSizesDepth($fileSizesDepth) {
		if ((!is_int($fileSizesDepth) && !is_bool($fileSizesDepth)) || (is_int($fileSizesDepth) && $fileSizesDepth < 0))
			throw new OptionException('%s must be a boolean or an integer no less than 0', '$fileSizesDepth');
		$this->fileSizesDepth = $fileSizesDepth;
	}

	/**
	 * @return bool|int
	 */
	public function getFileSizesDepth() {
		return $this->fileSizesDepth;
	}

	/**
	 * @param bool|int $fileTypesDepth
	 *
	 * @throws OptionException
	 */
	public function setFileTypesDepth($fileTypesDepth) {
		if ((!is_int($fileTypesDepth) && !is_bool($fileTypesDepth)) || (is_int($fileTypesDepth) && $fileTypesDepth < 0))
			throw new OptionException('%s must be a boolean or an integer no less than 0', '$fileTypesDepth');
		$this->fileTypesDepth = $fileTypesDepth;
	}

	/**
	 * @return bool|int
	 */
	public function getFileTypesDepth() {
		return $this->fileTypesDepth;
	}

	/**
	 * @param int $maxTempKB
	 *
	 * @throws OptionException
	 */
	public function setMaxTempKB($maxTempKB) {
		if (!is_int($maxTempKB) || $maxTempKB < 10)
			throw new OptionException('%s must be an integer no less than 10', '$maxTempKB');
		$this->maxTempKB = $maxTempKB;
	}

	/**
	 * @return int
	 */
	public function getMaxTempKB() {
		return $this->maxTempKB;
	}

	/**
	 * @param boolean $includeFullPath
	 */
	public function setIncludeFullPath($includeFullPath) {
		$this->includeFullPath = $includeFullPath;
	}

	/**
	 * @return boolean
	 */
	public function getIncludeFullPath() {
		return $this->includeFullPath;
	}

	/**
	 * @param int $maxLineLength
	 *
	 * @throws OptionException
	 */
	public function setMaxLineLength($maxLineLength) {
		if (!is_int($maxLineLength) || $maxLineLength < 1024)
			throw new OptionException('%s must be an integer no less than 1024', '$maxLineLength');
		$this->maxLineLength = $maxLineLength;
	}

	/**
	 * @return int
	 */
	public function getMaxLineLength() {
		return $this->maxLineLength;
	}

	/**
	 * @param bool|int $modifiedDatesDepth
	 *
	 * @throws OptionException
	 */
	public function setModifiedDatesDepth($modifiedDatesDepth) {
		if ((!is_int($modifiedDatesDepth) && !is_bool($modifiedDatesDepth)) || (is_int($modifiedDatesDepth) && $modifiedDatesDepth < 0))
			throw new OptionException('%s must be a boolean or an integer no less than 0', '$modifiedDatesDepth');
		$this->modifiedDatesDepth = $modifiedDatesDepth;
	}

	/**
	 * @return bool|int
	 */
	public function getModifiedDatesDepth() {
		return $this->modifiedDatesDepth;
	}

	/**
	 * @param int $progressMessageSeconds
	 *
	 * @throws OptionException
	 */
	public function setProgressMessageSeconds($progressMessageSeconds) {
		if (!is_int($progressMessageSeconds) || $progressMessageSeconds < 1)
			throw new OptionException('%s must be an integer no less than 1', '$progressMessageSeconds');
		$this->progressMessageSeconds = $progressMessageSeconds;
	}

	/**
	 * @return int
	 */
	public function getProgressMessageSeconds() {
		return $this->progressMessageSeconds;
	}

	/**
	 * @param null|string $reportDirectory
	 *
	 * @throws OptionException
	 */
	public function setReportDirectory($reportDirectory) {
		if (!is_string($reportDirectory) || strlen($reportDirectory) < 1)
			throw new OptionException('%s must be a string no less than 1 characters long', '$reportDirectory');
		$this->reportDirectory = rtrim(realpath($reportDirectory), DIRECTORY_SEPARATOR);
	}

	/**
	 * @return null|string
	 */
	public function getReportDirectory() {
		return $this->reportDirectory;
	}

	/**
	 * @param null|string $reportName
	 *
	 * @throws OptionException
	 */
	public function setReportName($reportName) {
		if (!is_string($reportName) || strlen($reportName) < 1)
			throw new OptionException('%s must be a string no less than 1 characters long', '$reportName');
		$this->reportName = $reportName;
	}

	/**
	 * @return null|string
	 */
	public function getReportName() {
		return $this->reportName;
	}

	/**
	 * @param string $suffix
	 *
	 * @throws OptionException
	 */
	public function setSuffix($suffix) {
		if (!is_string($suffix) || strlen($suffix) < 2)
			throw new OptionException('%s must be a string no less than 2 characters long', '$suffix');
		$this->suffix = $suffix;
	}

	/**
	 * @return string
	 */
	public function getSuffix() {
		return $this->suffix;
	}

	/**
	 * @param string $timezone
	 *
	 * @throws OptionException
	 */
	public function setTimezone($timezone) {
		if (!is_string($timezone) || strlen($timezone) < 1)
			throw new OptionException('%s must be a string no less than 1 characters long', '$timezone');
		$this->timezone = $timezone;
	}

	/**
	 * @return string
	 */
	public function getTimezone() {
		return $this->timezone;
	}

	/**
	 * @param bool|int $topListDepth
	 *
	 * @throws OptionException
	 */
	public function setTopListDepth($topListDepth) {
		if ((!is_int($topListDepth) && !is_bool($topListDepth)) || (is_int($topListDepth) && $topListDepth < 0))
			throw new OptionException('%s must be a boolean or an integer no less than 0', '$topListDepth');
		$this->topListDepth = $topListDepth;
	}

	/**
	 * @return bool|int
	 */
	public function getTopListDepth() {
		return $this->topListDepth;
	}

	/**
	 * @param int $maxPerPage
	 *
	 * @throws OptionException
	 */
	public function setMaxPerPage($maxPerPage) {
		if (!is_int($maxPerPage) || $maxPerPage <= 0 || $maxPerPage % 100 != 0)
			throw new OptionException('%s must be a positive integer divisible by 100', '$maxPerPage');
		$this->maxPerPage = $maxPerPage;
	}

	/**
	 * @return int
	 */
	public function getMaxPerPage() {
		return $this->maxPerPage;
	}

	/**
	 * @param int $maxSubDirsMapKB
	 *
	 * @throws OptionException
	 */
	public function setMaxSubDirsMapKB($maxSubDirsMapKB) {
		if (!is_int($maxSubDirsMapKB) || $maxSubDirsMapKB < 1)
			throw new OptionException('%s must be an integer no less than 1', '$maxSubDirsMapKB');
		$this->maxSubDirsMapKB = $maxSubDirsMapKB;
	}

	/**
	 * @return int
	 */
	public function getMaxSubDirsMapKB() {
		return $this->maxSubDirsMapKB;
	}

	/**
	 * @param int $maxSubDirsFilePages
	 *
	 * @throws OptionException
	 */
	public function setMaxSubDirsFilePages($maxSubDirsFilePages) {
		if (!is_int($maxSubDirsFilePages) || $maxSubDirsFilePages < 1)
			throw new OptionException('%s must be an integer no less than 1', '$maxSubDirsFilePages');
		$this->maxSubDirsFilePages = $maxSubDirsFilePages;
	}

	/**
	 * @return int
	 */
	public function getMaxSubDirsFilePages() {
		return $this->maxSubDirsFilePages;
	}

	/**
	 * @param int $maxFileListMapKB
	 *
	 * @throws OptionException
	 */
	public function setMaxFileListMapKB($maxFileListMapKB) {
		if (!is_int($maxFileListMapKB) || $maxFileListMapKB < 1)
			throw new OptionException('%s must be an integer no less than 1', '$maxFileListMapKB');
		$this->maxFileListMapKB = $maxFileListMapKB;
	}

	/**
	 * @return int
	 */
	public function getMaxFileListMapKB() {
		return $this->maxFileListMapKB;
	}

	/**
	 * @param int $maxFileListFilePages
	 *
	 * @throws OptionException
	 */
	public function setMaxFileListFilePages($maxFileListFilePages) {
		if (!is_int($maxFileListFilePages) || $maxFileListFilePages < 1)
			throw new OptionException('%s must be an integer no less than 1', '$maxFileListFilePages');
		$this->maxFileListFilePages = $maxFileListFilePages;
	}

	/**
	 * @return int
	 */
	public function getMaxFileListFilePages() {
		return $this->maxFileListFilePages;
	}

	/**
	 * @param int $maxDirMapKB
	 *
	 * @throws OptionException
	 */
	public function setMaxDirMapKB($maxDirMapKB) {
		if (!is_int($maxDirMapKB) || $maxDirMapKB < 1)
			throw new OptionException('%s must be an integer no less than 1', '$maxDirMapKB');
		$this->maxDirMapKB = $maxDirMapKB;
	}

	/**
	 * @return int
	 */
	public function getMaxDirMapKB() {
		return $this->maxDirMapKB;
	}


}
