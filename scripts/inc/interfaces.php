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

interface IKeyed {
	/**
	 * @return string
	 */
	public function getKey();

	/**
	 * @param string $key
	 */
	public function setKey($key);
}

interface IJSON {
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

interface IKeyedJSON extends IKeyed, IJSON {

}

interface IComparator {
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

interface ICollectionIO {

	/**
	 * @param string $prefix
	 * @param int    $index
	 * @param string $ext
	 * @param string $mode
	 *
	 * @return FileStream
	 */
	public function openFile($prefix, $index, $ext, $mode);

	/**
	 * @param int    $index
	 * @param mixed  $firstItem
	 * @param mixed  $lastItem
	 * @param int    $size
	 * @param string $path
	 */
	public function onSave($index, $firstItem, $lastItem, $size, $path);

	/**
	 * @param string $prefix
	 * @param int    $index
	 * @param string $ext
	 *
	 * @return boolean
	 */
	public function deleteFile($prefix, $index, $ext);

	/**
	 * @param string $fromPath
	 * @param string $prefix
	 * @param int    $index
	 * @param string $ext
	 *
	 * @return boolean
	 */
	public function renameTo($fromPath, $prefix, $index, $ext);
}

interface ICollectionOutput extends ICollectionIO, IComparator {

}

interface ISaveWatcher {
	public function onSave($index, $sortIndex, $firstItem, $lastItem, $path);
}
