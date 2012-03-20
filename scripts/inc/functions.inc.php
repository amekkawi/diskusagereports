<?php

/* 
 * Copyright (c) 2011 André Mekkawi <diskusage@andremekkawi.com>
 * Version: $Source Version$
 * 
 * LICENSE
 * 
 * This source file is subject to the MIT license in the file LICENSE.txt.
 * The license is also available at http://diskusagereports.com/license.html
 */

// Determine if the system supports 64-bit integers.
define('LARGE_INT', defined('PHP_INT_MAX') && strlen(PHP_INT_MAX.'') > 14);

function FormatDate($date, $format) {
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

function BigVal($num) {
	return LARGE_INT ? intval($num) : floatval($num);
}

function BigAdd($a, $b) {
	return BigVal($a) + BigVal($b);
}

function BigComp($a, $b) {
	return BigVal($a) - BigVal($b);
}

function BinarySearch($list, $needle, $comparator) {
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
 ?>