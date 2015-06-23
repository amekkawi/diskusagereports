<?php
/*
 * Copyright (c) 2015 André Mekkawi <license@diskusagereports.com>
 * Version: @@SourceVersion
 *
 * LICENSE
 *
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */

class GroupByFileType implements IKeyedJSON {

	protected $typeCounts = array();
	protected $typeSizes = array();

	protected $caseSensitive = false;
	protected $characterPattern = '/^[0-9A-Za-z_\-~\^]+$/';
	protected $minChars = 1;
	protected $maxChars = 4;

	protected $key;
	protected $jsonSize;

	public function add(FileInfo $fileInfo) {
		$ext = $this->getFileType($fileInfo->basename);

		if (!isset($this->typeCounts[$ext])) {
			$this->typeCounts[$ext] = 0;
			$this->typeSizes[$ext] = 0;
		}

		$this->typeCounts[$ext]++;
		$this->typeSizes[$ext] += $fileInfo->size;
	}

	public function getFileType($fileName) {
		$dotIndex = strrpos($fileName, '.');

		// Return empty string if does not have a period or starts with a period.
		if ($dotIndex === false || $dotIndex == 0)
			return '';

		$len = strlen($fileName) - $dotIndex - 1;

		// Return empty string if too short or long.
		if ($len < $this->minChars || $len > $this->maxChars)
			return '';

		$ext = substr($fileName, $dotIndex + 1);

		if (!$this->caseSensitive)
			$ext = strtolower($ext);

		// Return an empty string if it does not match the character pattern.
		if (!preg_match($this->characterPattern, $ext))
			return '';

		return $ext;
	}

	public function merge(GroupByFileType $other) {
		foreach ($other->typeSizes as $ext => $num) {
			if (!isset($this->typeCounts[$ext])) {
				$this->typeCounts[$ext] = 0;
				$this->typeSizes[$ext] = 0;
			}

			$this->typeCounts[$ext] += $other->typeCounts[$ext];
			$this->typeSizes[$ext] += $other->typeSizes[$ext];
		}
	}

	/**
	 * Get the stringified JSON for this object.
	 *
	 * @return string
	 */
	public function toJSON() {
		$json = '';
		foreach ($this->typeSizes as $ext => $num) {
			$json .=
				($json == '' ? '' : ',') .
				'['
				. json_encode($ext)
				. ',' . json_encode($num)
				. ',' . json_encode($this->typeCounts[$ext])
				. ']';
		}
		return '[' . $json . ']';
	}

	/**
	 * Get the estimated size of the JSON, or false if it cannot be converted to JSON.
	 *
	 * @return int|bool
	 */
	public function getJSONSize() {
		if ($this->jsonSize === null) {
			$size = 2; // brackets

			foreach ($this->typeSizes as $ext => $num) {
				$size +=
					4 + // brackets + 2 commas
					strlen($ext) + 2 + // ext, quotes
					strlen($num.'') + strlen($this->typeCounts[$ext].''); // counts
			}

			$this->jsonSize = $size;
		}

		return $this->jsonSize;
	}

	/**
	 * Get the key that identifies this object in a collection.
	 *
	 * @return string
	 */
	public function getKey() {
		return $this->key;
	}

	/**
	 * Set the key that identifies this object in a collection.
	 *
	 * @param string $key
	 */
	public function setKey($key) {
		$this->key = $key;
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	public function save() {
		// TODO: Allow it to save to individual files.
		throw new Exception(get_class($this) . ' cannot be saved.');
	}
}
