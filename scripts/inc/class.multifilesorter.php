<?php
class MultiFileSorter implements Iterator {

	protected $iterators;
	protected $output;
	protected $values = array();
	protected $topIndex;
	protected $key = 0;

	function __construct(array $iterators, CollectionOutput $output) {
		$this->iterators = $iterators;
		$this->output = $output;

		// Get the first value for each iterator.
		foreach ($iterators as $i => $iterator) {
			$this->values[$i] = $iterator->valid() ? $iterator->current() : null;
		}

		$this->findTop();
	}

	protected function findTop() {
		$this->topIndex = null;
		$topVal = null;
		foreach ($this->values as $i => $value) {
			if ($value !== null) {
				if ($topVal === null || $this->output->compare($value, $topVal) < 0) {
					$this->topIndex = $i;
					$topVal = $value;
				}
			}
		}
	}

	/**
	 * @param FileIterator $iterator
	 *
	 * @return mixed|null
	 * @throws Exception
	 */
	protected function nextIteratorValue($iterator) {
		$iterator->next();
		return $iterator->valid() ? $iterator->current() : null;
	}

	/**
	 * Return the current element.
	 *
	 * @return mixed Can return any type.
	 */
	public function current() {
		return $this->topIndex === null ? null : $this->values[$this->topIndex];
	}

	/**
	 * Move forward to next element.
	 *
	 * @return void Any returned value is ignored.
	 */
	public function next() {
		if ($this->topIndex === null)
			return;

		$this->key++;
		$this->values[$this->topIndex] = $this->nextIteratorValue($this->iterators[$this->topIndex]);
		$this->findTop();
	}

	/**
	 * Return the key of the current element.
	 *
	 * @return mixed scalar on success, or null on failure.
	 */
	public function key() {
		return $this->topIndex === null ? null : $this->key;
	}

	/**
	 * Checks if current position is valid.
	 *
	 * @return boolean The return value will be casted to boolean and then evaluated.
	 *                 Returns true on success or false on failure.
	 */
	public function valid() {
		return $this->topIndex !== null;
	}

	public function rewind() {
		if ($this->key > 1)
			throw new Exception("Cannot rewind FileIterator");
	}

}
