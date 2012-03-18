<?php
/*
 * Based on Zend Framework's JSON encoding
 * Copyright (c) 2005-2010, Zend Technologies USA, Inc. All rights reserved.
 * 
 * Version: $Source Version$
 */ 

/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 */

function json_encode($value) {
	if (is_object($value)) {
		return 'OBJECT not supported';
	} else if (is_array($value)) {
		return _json_encode_encodeArray($value);
	}
	return _json_encode_encodeDatum($value);
}

function _json_encode_encodeDatum(&$value) {
	$result = 'null';

	if (is_int($value) || is_float($value)) {
		$result = (string) $value;
		$result = str_replace(",", ".", $result);
	} elseif (is_string($value)) {
		$result = _json_encode_encodeString($value);
	} elseif (is_bool($value)) {
		$result = $value ? 'true' : 'false';
	}

	return $result;
}

function _json_encode_encodeArray(&$array)
{
	$tmpArray = array();

	// Check for associative array
	if (!empty($array) && (array_keys($array) !== range(0, count($array) - 1))) {
		// Associative array
		$result = '{';
		foreach ($array as $key => $value) {
			$key = (string) $key;
			$tmpArray[] = _json_encode_encodeString($key)
			. ':'
			. json_encode($value);
		}
		$result .= implode(',', $tmpArray);
		$result .= '}';
	} else {
		// Indexed array
		$result = '[';
		$length = count($array);
		for ($i = 0; $i < $length; $i++) {
			$tmpArray[] = json_encode($array[$i]);
		}
		$result .= implode(',', $tmpArray);
		$result .= ']';
	}

	return $result;
}

function _json_encode_encodeString(&$string)
{
	// Escape these characters with a backslash:
	// " \ / \n \r \t \b \f
	$search  = array('\\', "\n", "\t", "\r", "\b", "\f", '"', '/');
	$replace = array('\\\\', '\\n', '\\t', '\\r', '\\b', '\\f', '\"', '\\/');
	$string  = str_replace($search, $replace, $string);

	// Escape certain ASCII characters:
	// 0x08 => \b
	// 0x0c => \f
	$string = str_replace(array(chr(0x08), chr(0x0C)), array('\b', '\f'), $string);
	$string = _json_encode_encodeUnicodeString($string);

	return '"' . $string . '"';
}

function _json_encode_encodeUnicodeString($value)
{
	$strlen_var = strlen($value);
	$ascii = "";

	/**
	 * Iterate over every character in the string,
	 * escaping with a slash or encoding to UTF-8 where necessary
	 */
	for($i = 0; $i < $strlen_var; $i++) {
		$ord_var_c = ord($value[$i]);

		switch (true) {
			case (($ord_var_c >= 0x20) && ($ord_var_c <= 0x7F)):
				// characters U-00000000 - U-0000007F (same as ASCII)
				$ascii .= $value[$i];
				break;

			case (($ord_var_c & 0xE0) == 0xC0):
				// characters U-00000080 - U-000007FF, mask 110XXXXX
				// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
				$char = pack('C*', $ord_var_c, ord($value[$i + 1]));
				$i += 1;
				$utf16 = _json_encode_utf82utf16($char);
				$ascii .= sprintf('\u%04s', bin2hex($utf16));
				break;

			case (($ord_var_c & 0xF0) == 0xE0):
				// characters U-00000800 - U-0000FFFF, mask 1110XXXX
				// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
				$char = pack('C*', $ord_var_c,
				ord($value[$i + 1]),
				ord($value[$i + 2]));
				$i += 2;
				$utf16 = _json_encode_utf82utf16($char);
				$ascii .= sprintf('\u%04s', bin2hex($utf16));
				break;

			case (($ord_var_c & 0xF8) == 0xF0):
				// characters U-00010000 - U-001FFFFF, mask 11110XXX
				// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
				$char = pack('C*', $ord_var_c,
				ord($value[$i + 1]),
				ord($value[$i + 2]),
				ord($value[$i + 3]));
				$i += 3;
				$utf16 = _json_encode_utf82utf16($char);
				$ascii .= sprintf('\u%04s', bin2hex($utf16));
				break;

			case (($ord_var_c & 0xFC) == 0xF8):
				// characters U-00200000 - U-03FFFFFF, mask 111110XX
				// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
				$char = pack('C*', $ord_var_c,
				ord($value[$i + 1]),
				ord($value[$i + 2]),
				ord($value[$i + 3]),
				ord($value[$i + 4]));
				$i += 4;
				$utf16 = _json_encode_utf82utf16($char);
				$ascii .= sprintf('\u%04s', bin2hex($utf16));
				break;

			case (($ord_var_c & 0xFE) == 0xFC):
				// characters U-04000000 - U-7FFFFFFF, mask 1111110X
				// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
				$char = pack('C*', $ord_var_c,
				ord($value[$i + 1]),
				ord($value[$i + 2]),
				ord($value[$i + 3]),
				ord($value[$i + 4]),
				ord($value[$i + 5]));
				$i += 5;
				$utf16 = _json_encode_utf82utf16($char);
				$ascii .= sprintf('\u%04s', bin2hex($utf16));
				break;
		}
	}

	return $ascii;
}

function _json_encode_utf82utf16($utf8)
{
	// Check for mb extension otherwise do by hand.
	if( function_exists('mb_convert_encoding') ) {
		return mb_convert_encoding($utf8, 'UTF-16', 'UTF-8');
	}

	switch (strlen($utf8)) {
		case 1:
			// this case should never be reached, because we are in ASCII range
			// see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
			return $utf8;

		case 2:
			// return a UTF-16 character from a 2-byte UTF-8 char
			// see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
			return chr(0x07 & (ord($utf8{0}) >> 2))
			. chr((0xC0 & (ord($utf8{0}) << 6))
			| (0x3F & ord($utf8{1})));

		case 3:
			// return a UTF-16 character from a 3-byte UTF-8 char
			// see: http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
			return chr((0xF0 & (ord($utf8{0}) << 4))
			| (0x0F & (ord($utf8{1}) >> 2)))
			. chr((0xC0 & (ord($utf8{1}) << 6))
			| (0x7F & ord($utf8{2})));
	}

	// ignoring UTF-32 for now, sorry
	return '';
}
?>