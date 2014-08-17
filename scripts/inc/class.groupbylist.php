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

	public function merge(GroupByList $other) {
		$count = $this->getGroupCount();
		for ($i = 0; $i < $count; $i++) {
			$this->groupCounts[$i] += $other->groupCounts[$i];
			$this->groupSizes[$i] += $other->groupSizes[$i];
		}
		$this->size = null;
	}

	public function getSize() {
		if ($this->size === null) {
			$size = 4;
			foreach ($this->groupCounts as $groupCount) {
				$size += strlen($groupCount.'') + 1;
			}
			foreach ($this->groupSizes as $groupSize) {
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
		$indexList = array();
		$valuesList = array();
		for ($i = 0, $count = $this->getGroupCount(); $i < $count; $i++) {
			if ($this->groupCounts[$i] > 0) {
				$indexList[] = $i;
				$valuesList[] = array($this->groupSizes[$i], $this->groupCounts[$i]);
			}
		}
		return json_encode(array($indexList, $valuesList));
	}

	public function getJSONSize() {
		return $this->getSize();
	}
}

class GroupBySizeList extends GroupByList {

	protected $groups;

	function __construct($groups) {
		$this->groups = $groups;
		parent::__construct();
	}

	public function getGroupCount() {
		return count($this->groups);
	}

	public function getGroupIndex(FileInfo $fileInfo) {
		foreach ($this->groups as $i => $group) {
			if ($fileInfo->size >= $group['size'])
				return $i;
		}

		return false;
	}
}

class GroupByModifiedDates extends GroupByList {

	protected $groups;

	function __construct($groups) {
		$this->groups = $groups;
		parent::__construct();
	}

	public function getGroupCount() {
		return count($this->groups);
	}

	public function getGroupIndex(FileInfo $fileInfo) {
		foreach ($this->groups as $i => $group) {
			if ($fileInfo->date <= $group['date'])
				return $i;
		}

		return false;
	}
}
