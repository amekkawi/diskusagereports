<?php

class TopList implements IKeyedJSON {

	protected $max = 100;
	protected $lower = 0;
	protected $list = array();
	protected $size = null;
	protected $key = null;

	public function add(FileInfo $info) {
		if ($info->size > $this->lower || count($this->list) < $this->max) {

			// Add the new item and sort the list.
			$this->list[] = array(
				$info->size,
				'['
					. $info->getEncodedBasename()
					. ',' . json_encode($info->size)
					. ',' . json_encode($info->dirname)
					. ',' . json_encode($info->getParent()->hash)
				. ']'
			);
			usort($this->list, array($this, 'compare'));

			// Pop off the last item if too large.
			$count = count($this->list);
			if ($count > $this->max) {
				array_pop($this->list);
				$count--;
			}

			// Determine the new lower.
			$this->lower = $this->list[$count - 1][0];
			$this->size = null;
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

			$this->size = null;
		}
	}

	public function getSize() {
		if ($this->size === null) {
			$size = 1;
			foreach ($this->list as $item) {
				$size += strlen($item[1]) + 1;
			}
			$this->size = max(2, $size);
		}

		return $this->size;
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

	public function getKey() {
		return $this->key;
	}

	public function setKey($key) {
		$this->key = $key;
	}

	public function toJSON() {
		$ret = '';
		foreach ($this->list as $item) {
			$ret .= ',' . $item[1];
		}

		return '[' . substr($ret, 1) . ']';
	}

	public function getJSONSize() {
		return $this->getSize();
	}


}
