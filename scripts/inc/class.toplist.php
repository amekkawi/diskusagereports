<?php

class TopList {

	protected $max = 100;
	protected $lower = 0;
	protected $list = array();

	public function add(FileInfo $info) {
		if ($info->size > $this->lower || count($this->list) < $this->max) {

			// Add the new item and sort the list.
			$json = array(
				$info->basename,
				$info->size,
				$info->size,
				$info->dirname,
				$info->getParent()->hash
			);
			$this->list[] = array($info->size, json_encode($json));
			usort($this->list, array($this, 'compare'));

			// Pop off the last item if too large.
			$count = count($this->list);
			if ($count > $this->max) {
				array_pop($this->list);
				$count--;
			}

			// Determine the new lower.
			$this->lower = $this->list[$count - 1][0];
		}
	}

	public function merge(TopList $topList) {
		if ($topList->getUpper() > $this->lower || count($this->list) < $this->max) {
			foreach ($topList->list as $item) {
				if ($item[0] > $this->lower)
					$this->list[] = $item;
				elseif ($item[0] <= $this->lower)
					break;
			}

			usort($this->list, array($this, 'compare'));
			$count = count($this->list);

			if ($count > $this->max) {
				array_splice($this->list, $this->max);
				$count = $this->max;
			}

			if ($count > 0)
				$this->lower = $this->list[$count - 1][0];
		}
	}

	public function getUpper() {
		if (empty($this->list))
			return null;

		return $this->list[0][0];
	}

	public function getLower() {
		return $this->lower;
	}

	public function compare($a, $b) {
		return $b[0] - $a[0];
	}

	public function getList() {
		return $this->list;
	}
}
