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
	 * @param mixed $a
	 * @param mixed $b
	 *
	 * @return int A negative number if $a < $b.
	 *             A positive number if $a > $b.
	 *             Zero (0) if $a == $b.
	 */
	public function compare($a, $b);
}

interface ICollectionIO {

	/**
	 * Open a file for the given prefix, index and suffix.
	 *
	 * @param string $prefix
	 * @param int    $index
	 * @param string $suffix
	 * @param string $mode
	 *
	 * @return FileStream
	 */
	public function openFile($prefix, $index, $suffix, $mode);

	/**
	 * Fired when a file is saved to disk.
	 *
	 * @param int    $index
	 * @param mixed  $firstItem
	 * @param mixed  $lastItem
	 * @param int    $size
	 * @param string $path
	 */
	public function onSave($index, $firstItem, $lastItem, $size, $path);

	/**
	 * Fired when a file is deleted from disk.
	 *
	 * @param string $prefix
	 * @param int    $index
	 * @param string $suffix
	 *
	 * @return boolean
	 */
	public function deleteFile($prefix, $index, $suffix);

	/**
	 * Fired when a file is renamed on disk.
	 *
	 * @param string $fromPath
	 * @param string $prefix
	 * @param int    $index
	 * @param string $suffix
	 *
	 * @return boolean
	 */
	public function renameTo($fromPath, $prefix, $index, $suffix);
}

interface ICollectionOutput extends ICollectionIO, IComparator {

}

interface ISaveWatcher {

	/**
	 * Fired when a sorted collection file is saved to disk.
	 *
	 * @param int        $index
	 * @param int        $sortIndex
	 * @param array|null $firstItem The first item in the collection.
	 * @param array|null $lastItem  The last item in the collection.
	 * @param            $path      Path of the file saved.
	 *
	 * @return mixed
	 */
	public function onSave($index, $sortIndex, $firstItem, $lastItem, $path);
}
