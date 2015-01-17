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
