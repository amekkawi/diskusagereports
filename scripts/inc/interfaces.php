<?php

interface Keyed {
	/**
	 * @return string
	 */
	public function getKey();

	/**
	 * @param string $key
	 */
	public function setKey($key);
}

interface JSON {
	/**
	 * @return string The stringified JSON.
	 */
	public function toJSON();

	/**
	 * @return int The estimated size of the JSON.
	 *             Returns false if it cannot be converted to JSON.
	 */
	public function getJSONSize();
}

interface KeyedJSON extends Keyed, JSON {

}

interface Comparator {
	/**
	 * @param $a
	 * @param $b
	 *
	 * @return int A negative number if $a < $b.
	 *             A positive number if $a > $b.
	 *             Zero (0) if $a == $b.
	 */
	public function compare($a, $b);
}

interface CollectionOutputAdapter {
	/**
	 * @param        $prefix
	 * @param        $index
	 * @param string $mode
	 *
	 * @return FileStream
	 */
	public function openOutFile($prefix, $index, $mode = 'w');

	/**
	 * @param $index
	 * @param $firstItem
	 * @param $lastItem
	 * @param $size
	 * @param $path
	 */
	public function onSave($index, $firstItem, $lastItem, $size, $path);
}

interface CollectionTempAdapter {
	/**
	 * @param $prefix
	 * @param $index
	 * @param $mode
	 *
	 * @return FileStream
	 */
	public function openTempFile($prefix, $index, $mode);

	/**
	 * @param $prefix
	 * @param $index
	 *
	 * @return boolean
	 */
	public function deleteTempFile($prefix, $index);
}

interface CollectionOutput extends CollectionOutputAdapter, CollectionTempAdapter, Comparator {

}
