<?php
require_once('class.JavaScriptPacker.php');

function fail($str) {
	$stderr = fopen('php://stderr', 'w+');
	fwrite($stderr, $str . '\n');
	fclose($stderr);
	exit(1);
}

/*
 * level of encoding, int or string :
 * 0,10,62,95 or 'None', 'Numeric', 'Normal', 'High ASCII'.
 * default: 62.
 */

$source = '';
$bytes = 0;
if (($h = fopen('php://stdin', 'r')) !== FALSE) {
	while (!feof($h)) {
		$buf = fread($h, 1024 * 8);
		if ($buf === FALSE) {
			@fclose($h);
			fail('Failed to fread() after ' . $bytes . ' bytes.');
		}
		else if ($buf === '') {
			break;
		}
		else {
			$bytes += strlen($buf);
			$source .= $buf;
		}
	}
	
	@fclose($h);
	
	$packer = new JavaScriptPacker($source, 'None', true, false);
	$packed = $packer->pack();
	echo $packed;
}
else {
	fail('Failed to open STDIN.');
}
?>
