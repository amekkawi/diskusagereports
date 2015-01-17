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
 * Support for IO actions performed by collections.
 */
interface ICollectionIO {

	/**
	 * Open a file for the given prefix, index and suffix.
	 *
	 * @param string $prefix
	 * @param int    $index
	 * @param string $suffix
	 * @param string $mode
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
	 * @return boolean
	 */
	public function renameTo($fromPath, $prefix, $index, $suffix);
}
