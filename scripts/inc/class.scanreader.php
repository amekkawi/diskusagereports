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

class ScanReader {

	const DEBUG = false;

	/**
	 * @var Report
	 */
	protected $report;

	public function __construct(Report $report) {
		$this->report = $report;
	}

	public function read($filename) {

		// Attempt to open the file list.
		try {
			$stream = new FileStream($filename, 'r');
		}
		catch (IOException $e) {
			throw new ScanException(ScanException::FOPEN_FAIL);
		}

		$options = $this->report->options;
		$iterator = new FileIterator($stream);
		$fileInfo = new FileInfo();
		$dirList = $this->report->directoryList;

		$currentDir = new DirInfo($this->report);
		$currentDir->setFromOptions($options);

		$headerAllowed = true;

		$progressLastReport = time();
		$progressLastLines = 0;
		$progressLastBytes = 0;
		$progressLastOutFiles = 0;
		$progressLastOutSize = 0;

		foreach ($iterator as $lineNum => $line) {

			if (time() - $progressLastReport >= 3) {
				if ($iterator->length() !== null) {
					$progressPercent = floor($iterator->position() / $iterator->length() * 1000) / 10;
					echo sprintf('%4.1f', $progressPercent) . "%: ";
				}

				echo "Processed " . Util::FormatNumber($lineNum - $progressLastLines) . " lines from " . Util::FormatBytes($iterator->position() - $progressLastBytes) . ". Wrote " . Util::FormatBytes($this->report->outSize - $progressLastOutSize) . " to " . Util::FormatNumber($this->report->outFiles - $progressLastOutFiles) . " files.\n";
				$progressLastReport = time();
				$progressLastBytes = $iterator->position();
				$progressLastOutFiles = $this->report->outFiles;
				$progressLastLines = $lineNum;
				$progressLastOutSize = $this->report->outSize;
			}

			// Ignore blank lines
			if (trim($line) == '')
				continue;

			try {
				$flag = substr($line, 0, 1);

				// Process the header.
				if ($flag == '#') {
					if (!$headerAllowed)
						throw new ScanException(ScanException::HEADER_EXCEPTION);

					$options->processHeader($line);
					$headerAllowed = false;
				}

				elseif ($flag == '!') {
					//$this->processError($line);
				}

				elseif ($flag == 'd') {
					$newDir = new DirInfo($this->report);
					$newDir->setFromLine($options, $line);

					while ($currentDir->path != $newDir->dirname) {

						if ($currentDir->parent === null)
							throw new ScanException(ScanException::POPDIR_NOPARENT);

						if (self::DEBUG)
							echo "Popping dir: {$currentDir->path}\n";

						$popDir = $currentDir;
						$currentDir = $currentDir->parent;

						$popDir->onPop();
						$dirList->add($popDir->hash, json_encode($popDir->hash) . ":" . $popDir->toJSON());

						$currentDir->onChildPop($popDir);
					}

					$newDir->parent = $currentDir;
					$currentDir = $newDir;

					if (self::DEBUG)
						echo "Entering dir: {$currentDir->path}\n";
				}

				elseif ($flag == 'f' || $flag == '-') {
					$fileInfo->setFromLine($options, $line);
					$headerAllowed = false;

					if (self::DEBUG)
						echo "    File: {$fileInfo->path}\n";

					while ($currentDir->path != $fileInfo->dirname) {

						if ($currentDir->parent === null)
							throw new ScanException(ScanException::POPDIR_NOPARENT);

						if (self::DEBUG)
							echo "Popping dir: {$currentDir->path}\n";

						$popDir = $currentDir;
						$currentDir = $currentDir->parent;

						$popDir->onPop();
						$dirList->add($popDir->hash, json_encode($popDir->hash) . ":" . $popDir->toJSON());

						$currentDir->onChildPop($popDir);
					}

					$currentDir->processFileInfo($fileInfo);
				}
			}
			catch (LineException $e) {
				echo "LineException on line $lineNum: " . $e->getMessage() . "\n";
			}
		}

		// Process any remaining directories.
		do {
			if (self::DEBUG)
				echo "Popping dir: {$currentDir->path}\n";

			$popDir = $currentDir;

			$popDir->onPop();

			if ($currentDir->parent !== null) {
				$currentDir = $currentDir->parent;
				$currentDir->onChildPop($popDir);
			}

		} while ($currentDir->parent !== null);

		// Save any open maps.
		$this->report->subDirMap->save();
		$this->report->fileListMap->save();

		// Save the directory list.
		$startDirLists = microtime(true);
		echo "Saving dir lists...\n";
		$dirList->save();
		echo "Took " . sprintf('%.2f', microtime(true) - $startDirLists) . " sec\n";

		// Save the directory lookup
		echo "Saving dir lookup...\n";
		$lookupSize = file_put_contents($this->report->buildPath('dirmap_lookup.dat'), json_encode($this->report->directoryLookup->ranges));
		if ($lookupSize === false)
			throw new ScanException("Failed to write dirmap_lookup.dat.");
		$this->report->outFiles++;
		$this->report->outSize += $lookupSize;

		echo "Complete! Processed " . Util::FormatNumber($iterator->key()) . " lines from " . Util::FormatBytes($iterator->position()) . ". Wrote " . Util::FormatBytes($this->report->outSize) . " in " . Util::FormatNumber($this->report->outFiles) . " files.\n";

		$stream->close();
	}
}
