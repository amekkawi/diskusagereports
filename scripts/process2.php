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

require("inc/interfaces.php");
require("inc/exceptions.php");
require("inc/class.util.php");
require("inc/class.options.php");
require("inc/class.largemap.php");
require("inc/class.largecollection.php");
require("inc/class.filestream.php");
require("inc/class.fileiterator.php");
require("inc/class.multifilesorter.php");
require("inc/classes.collectionoutput.php");
require("inc/classes.entryinfo.php");
require("inc/class.scanreader.php");
require("inc/class.report.php");

ini_set('display_errors', 1);
error_reporting(E_ALL);

$stdErr = fopen('php://stderr', 'w');
$syntax = '';
$syntaxLong = '';

try {
	// Make sure this script is run from the command line.
	if (php_sapi_name() != "cli") {
		fwrite($stdErr, "Must be run from the command line.\n"); fclose($stdErr);
		exit(1);
	}

	$cliargs = array_slice($_SERVER['argv'], 1);
	$options = new Options();
	$scanFile = null;

	// Process command line arguments.
	while (($cliarg = $cliargOrig = array_shift($cliargs)) !== null) {
		switch ($cliarg) {
			case '/?':
			case '-?':
			case '-h':
			case '--help':
				fwrite($stdErr, $syntax_long);
				fclose($stdErr);
				exit(1);
			case '-tz':
				$options->setTimezone($cliarg = array_shift($cliargs));
				break;
			case '-d':
				$options->setDelim($cliarg = array_shift($cliargs));
				break;
			case '-t':
				if (!preg_match('/^(all|off|[0-9]+)$/', strtolower($cliarg = array_shift($cliargs)))) { fwrite($stdErr, "$cliargOrig must be followed by 'all', 'off' or a number no less than 0.\n".$syntax); fclose($stdErr); exit(1); }
				if ($cliarg == 'all') $cliarg = true;
				elseif ($cliarg == 'off') $cliarg = false;
				else $cliarg = intval($cliarg);
				$options->setFileSizesDepth($cliarg);
				$options->setFileTypesDepth($cliarg);
				$options->setModifiedDatesDepth($cliarg);
				break;
			case '-nt':
				$options->setDisableDirectoryTree(true);
				break;
			case '-mt':
				if (!preg_match('/^[0-9]+$/', $cliarg = array_shift($cliargs))) { fwrite($stdErr, "$cliargOrig must be followed by a number.\n".$syntax); fclose($stdErr); exit(1); }
				//$processor->setMaxTreeSize(intval($cliarg));
				break;
			case '-ds':
				$options->setDirectorySeparator($cliarg = array_shift($cliargs));
				break;
			case '-td':
				if (!preg_match('/^[0-9]+$/', $cliarg = array_shift($cliargs))) { fwrite($stdErr, "$cliargOrig must be followed by a number.\n".$syntax); fclose($stdErr); exit(1); }
				$options->setTopListDepth(intval($cliarg));
				break;
			case '-n':
				$options->setReportName($cliarg = array_shift($cliargs));
				break;
			case '-l':
				if (!preg_match('/^[0-9]+$/', $cliarg = array_shift($cliargs))) { fwrite($stdErr, "$cliargOrig must be followed by a number.\n".$syntax); fclose($stdErr); exit(1); }
				$options->setMaxLineLength(intval($cliarg));
				break;
			case '-q':
				$options->setVerbosity(Options::VERBOSITY_QUIET);
				break;
			case '-v':
				$options->setVerbosity(Options::VERBOSITY_VERBOSE);
				break;
			case '-vv':
				$options->setVerbosity(Options::VERBOSITY_VERY_VERBOSE);
				break;
			case '-fp':
				$options->setIncludeFullPath(true);
				break;
			case '-su':
				$options->setSuffix($cliarg = array_shift($cliargs));
				break;
			case '-ss':
				if (!preg_match('/^[0-9]+$/', $cliarg = array_shift($cliargs))) {
					fwrite($stdErr, "$cliargOrig must be followed by a number.\n".$syntax);
					fclose($stdErr);
					exit(1);
				}
				$options->setProgressMessageSeconds(intval($cliarg));
				break;

			/** @noinspection PhpMissingBreakStatementInspection */
			case '-':
				if ($options->getReportDirectory() !== null && $options->getScanFile() !== null) {
					fwrite($stdErr, "Unexpected argument: $cliarg\n" . $syntax);
					fclose($stdErr);
					exit(1);
				}
				elseif ($cliarg = array_shift($cliargs) === null) {
					continue;
				}
			default:
				if ($options->getReportDirectory() === null) {
					$options->setReportDirectory($cliarg);
				}
				elseif ($scanFile === null) {
					$scanFile = $cliarg;
				}
				else {
					fwrite($stdErr, "Unexpected argument: $cliarg\n" . $syntax);
					fclose($stdErr);
					exit(1);
				}
		}

		// If we shifted and found nothing, output an error.
		if ($cliarg === null) {
			fwrite($stdErr, "Missing value after argument $cliargOrig\n" . $syntax);
			fclose($stdErr);
			exit(1);
		}
	}

	// Make sure the <reportdir> was set.
	if ($options->getReportDirectory() === null) {
		fwrite($stdErr, "<reportdir> argument is missing\n" . $syntax);
		fclose($stdErr);
		exit(1);
	}

	// Read the file list from STDIN if it was not specified.
	if ($scanFile === null) {
		$scanFile = 'php://stdin';
	}

	// Otherwise, make sure the <filelist> exists.
	elseif (!is_file($scanFile)) {
		fwrite($stdErr, "The <filelist> '" . $scanFile . "' does not exist or is not a file.\n");
		fclose($stdErr);
		exit(1);
	}

	// Attempt to set the default timezone if it was not set.
	if ($options->getTimezone() === null)
		$options->setTimezone(function_exists('date_default_timezone_get') ? @date_default_timezone_get() : 'America/New_York');

	// Set the timezone.
	if (!(function_exists("date_default_timezone_set") ? @(date_default_timezone_set($options->getTimezone())) : @(putenv("TZ=".$options->getTimezone())))) {
		fwrite($stdErr, "'timezone' config was set to an invalid identifier.\n");
		fclose($stdErr);
		exit(1);
	}

	$reader = new ScanReader(new Report($options));
	$reader->read($scanFile);
}
catch (Exception $e) {
	fwrite($stdErr, "\n" . get_class($e) . ": " . $e->getMessage() . "\n");
	fwrite($stdErr, $e->getTraceAsString()."\n");
	fclose($stdErr);
	exit(1);
}

fclose($stdErr);
