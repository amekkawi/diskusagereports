<?php

abstract class GroupByList implements IKeyedJSON {

	protected $size = null;
	protected $key = null;
	protected $groupCounts;
	protected $groupSizes;

	function __construct() {
		$this->groupCounts = array_fill(0, $this->getGroupCount(), 0);
		$this->groupSizes = array_fill(0, $this->getGroupCount(), 0);
	}

	public abstract function getGroupCount();

	/**
	 * Get the index of the group that the $fileInfo belongs to.
	 *
	 * @param FileInfo $fileInfo
	 *
	 * @return int|false The index of the group or false if it does not belong in a group.
	 */
	public abstract function getGroupIndex(FileInfo $fileInfo);

	public function add(FileInfo $fileInfo) {
		$index = $this->getGroupIndex($fileInfo);
		$this->groupCounts[$index]++;
		$this->groupSizes[$index] += $fileInfo->size;
		$this->size = null;
	}

	public function getSize() {
		if ($this->size === null) {
			$size = 4;
			foreach ($this->groupCounts as $groupCount) {
				$size += strlen($groupCount.'') + 1;
			}
			foreach ($this->$groupSize as $groupSize) {
				$size += strlen($groupSize.'') + 1;
			}
			$this->size = max(4, $size);
		}

		return $this->size;
	}

	public function getGroupCounts() {
		return $this->groupCounts;
	}

	public function getGroupSizes() {
		return $this->groupSizes;
	}

	public function getKey() {
		return $this->key;
	}

	public function setKey($key) {
		$this->key = $key;
	}

	public function toJSON() {
		return json_encode(array($this->groupCounts, $this->groupSizes));
	}

	public function getJSONSize() {
		return $this->getSize();
	}
}
