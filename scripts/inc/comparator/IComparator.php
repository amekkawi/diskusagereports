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
