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

interface ICollectionListener {
	public function onSave($sortIndex, $sortKey, $fileIndex, $length, $size, $firstItem, $lastItem, $path);
}
