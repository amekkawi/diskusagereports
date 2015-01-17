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
