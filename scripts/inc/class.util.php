<?php
/*
 * Copyright (c) @@CopyYear André Mekkawi <diskusage@andremekkawi.com>
 * Version: @@SourceVersion
 *
 * LICENSE
 *
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */

define('UTIL_IS_LARGE_INT', defined('PHP_INT_MAX') && strlen(PHP_INT_MAX.'') > 14);

class Util {

	public static function ExplodeEscaped($delim, $str, $limit = 0, $escape = "\\") {
		$arr = array();
		$index = -1;

		while (($limit == 0 || $limit >= count($arr)) && ($index = strpos($str, $delim, $index + 1)) !== FALSE) {

			// Count the number of escape characters before the delim.
			$escapes = 0;
			for ($i = $index - 1; $i >= 0; $i--) {
				if (substr($str, $i, 1) == $escape) $escapes++;
				else break;
			}

			// This delim is not being escaped if an even number of escape characters.
			if ($escapes % 2 == 0) {
				array_push($arr, str_replace($escape . $delim, $delim, str_replace($escape . $escape, $escape, substr($str, 0, $index))));

				if ($index + 1 > strlen($str))
					$str = null;
				else {
					$str = substr($str, $index + 1);
					$index = -1;
				}
			}
		}

		if (!is_null($str))
			array_push($arr, str_replace($escape . $delim, $delim, str_replace($escape . $escape, $escape, $str)));

		return $arr;
	}

	public static function FormatDate($date, $format) {
		if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date)) {
			return $date;
		}
		elseif (class_exists('DateTime')) {
			$date = new DateTime($date);
			return $date->format($format);
		}
		else {
			return date($format, strtotime($date));
		}
	}

	public static function BigVal($num) {
		return UTIL_IS_LARGE_INT ? intval($num) : floatval($num);
	}

	public static function BigAdd($a, $b) {
		return self::BigVal($a) + self::BigVal($b);
	}

	public static function BigComp($a, $b) {
		return self::BigVal($a) - self::BigVal($b);
	}

	public static function BinarySearch($list, $needle, $comparator) {
		$low = 0;
		$high = count($list) - 1;
		$comp = -1;
		$mid = 0;

		while ($low <= $high) {
			$mid = floor(($low + $high) / 2);

			$comp = call_user_func($comparator, $list[$mid], $needle);

			if ($comp < 0) {
				$high = $mid - 1;
			}
			else if ($comp > 0) {
				$low = $mid + 1;
			}
			else {
				return $mid;
			}
		}

		if ($comp < 0) return -1 - $mid;
		if ($comp > 0) return -2 - $mid;
	}

	public static function FormatBytes($bytes) {
		if ($bytes >= 1024 * 1024 * 1024 * 1024)
			return number_format($bytes / 1024 / 1024 / 1024 / 1024, 2) . ' TB';
		elseif ($bytes >= 1024 * 1024 * 1024)
			return number_format($bytes / 1024 / 1024 / 1024, 2) . ' GB';
		elseif ($bytes >= 1024 * 1024)
			return number_format($bytes / 1024 / 1024, 2) . ' MB';
		elseif ($bytes >= 1024)
			return number_format($bytes / 1024, 2) . ' KB';
		else
			return number_format($bytes) . ' byte' . ($bytes == 1 ? '' : 's');
	}
}
