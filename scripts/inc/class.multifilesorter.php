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
 * Wraps multiple sorted iterators in a single sorted iterator.
 */
class MultiIteratorSorter implements Iterator {

	/**
	 * @var Iterator[]
	 */
	protected $iterators;

	/**
	 * @var IComparator
	 */
	protected $comparator;

	/**
	 * @var Iterator
	 */
	protected $topIterator;

	/**
	 * @var int
	 */
	protected $key = 0;

	/**
	 * @param Iterator[]  $iterators
	 * @param IComparator $comparator
	 */
	function __construct(array $iterators, IComparator $comparator) {
		$this->iterators = $iterators;
		$this->comparator = $comparator;
		$this->topIterator = $this->findTopIterator();
	}

	/**
	 * Get the iterator that has the next value to return for the MultiIteratorSorter.
	 * @return Iterator
	 */
	protected function findTopIterator() {
		$topIterator = null;
		$comparator = $this->comparator;

		foreach ($this->iterators as $i => $iterator) {
			if ($iterator->valid()) {
				if ($topIterator === null || $comparator->compare($iterator->current(), $topIterator->current()) < 0) {
					$topIterator = $iterator;
				}
			}
		}

		return $topIterator;
	}

	/**
	 * @inheritdoc
	 */
	public function current() {
		return $this->topIterator === null ? null : $this->topIterator->current();
	}

	/**
	 * @inheritdoc
	 */
	public function next() {
		if ($this->topIterator === null)
			return;

		$this->key++;
		$this->topIterator->next();
		$this->topIterator = $this->findTopIterator();
	}

	/**
	 * @inheritdoc
	 */
	public function key() {
		return $this->topIterator === null ? null : $this->key;
	}

	/**
	 * @inheritdoc
	 */
	public function valid() {
		return $this->topIterator !== null;
	}

	/**
	 * @inheritdoc
	 */
	public function rewind() {
		foreach ($this->iterators as $iterator) {
			$iterator->rewind();
		}
	}
}
