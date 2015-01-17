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
 * Support for identifying this object by a key.
 */
interface IKeyed {

	/**
	 * Get the key that identifies this object in a collection.
	 * @return string
	 */
	public function getKey();

	/**
	 * Set the key that identifies this object in a collection.
	 * @param string $key
	 */
	public function setKey($key);
}

/**
 * Support for serializing the contents of this object to JSON.
 */
interface IJSON {

	/**
	 * Get the stringified JSON for this object.
	 * @return string
	 */
	public function toJSON();

	/**
	 * Get the estimated size of the JSON, or false if it cannot be converted to JSON.
	 * @return int|bool
	 */
	public function getJSONSize();
}

/**
 * Support for identifying this object by key and serializing its contents to JSON.
 */
interface IKeyedJSON extends IKeyed, IJSON {

}

/**
 * Support for comparing two objects.
 */
interface IComparator {
	/**
	 * @param mixed $a
	 * @param mixed $b
	 * @return int A negative number if $a < $b.
	 *             A positive number if $a > $b.
	 *             Zero (0) if $a == $b.
	 */
	public function compare($a, $b);
}

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

interface ICollectionOutput extends ICollectionIO, IComparator {

}

/**
 * Interface for classes that listens to writing collection files to disk.
 */
interface ISaveWatcher {

	/**
	 * Fired when a sorted collection file is saved to disk.
	 *
	 * @param int        $index     The file's index number.
	 * @param int|null   $sortIndex The sorting group that is being saved. Null if there are no sort groups.
	 * @param array|null $firstItem The first item in the collection.
	 * @param array|null $lastItem  The last item in the collection.
	 * @param string     $path      Path of the file saved.
	 */
	public function onSave($index, $sortIndex, $firstItem, $lastItem, $path);
}
