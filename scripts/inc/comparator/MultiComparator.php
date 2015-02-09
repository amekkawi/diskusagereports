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

class MultiComparator implements IComparator {

	protected $sortIndex;
	protected $reverseSort;
	protected $secondarySortIndexes;
	protected $reverseSecondarySortIndexes;

	public function __construct($sortIndex, array $options = array()) {
		$this->sortIndex = $sortIndex;
		$this->reverseSort = !empty($options['reverseSort']);
		$this->secondarySortIndexes = isset($options['secondarySortIndexes']) ? $options['secondarySortIndexes'] : null;
		$this->reverseSecondarySortIndexes = isset($options['reverseSecondarySortIndexes']) ? $options['reverseSecondarySortIndexes'] : $this->reverseSort;
	}

	public function compare($a, $b) {
		$sortIndex = $this->sortIndex;
		if ($a[0][$sortIndex] < $b[0][$sortIndex])
			return $this->reverseSort ? 1 : -1;
		if ($a[0][$sortIndex] > $b[0][$sortIndex])
			return $this->reverseSort ? -1 : 1;

		if (isset($this->secondarySortIndexes)) {
			foreach ($this->secondarySortIndexes as $secondarySortIndex) {
				if ($a[0][$secondarySortIndex] < $b[0][$secondarySortIndex])
					return $this->reverseSecondarySortIndexes ? 1 : -1;
				if ($a[0][$secondarySortIndex] > $b[0][$secondarySortIndex])
					return $this->reverseSecondarySortIndexes ? -1 : 1;
			}
		}

		return 0;
	}
}
