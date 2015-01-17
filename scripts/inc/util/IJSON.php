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
