<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */
 
echo <<<ENDBANNER
langConvert version 1.0
Copyright ©2011 Nicholas K. Dionysopoulos / Akeeba Ltd
===============================================================================
langConvert is Free  Software, distributed under  the terms  of the GNU General
Public License, version 3 of the license  or –at your option– any later version.
-------------------------------------------------------------------------------

ENDBANNER;

$filename = $argv[$argc-1];
if(realpath($filename) == realpath(__FILE__)) {
echo <<<ENDUSAGE
Usage:
	php ./convert.php filename.ini

ENDUSAGE;
die();
}

$fp = @fopen($filename, 'rt');
if($fp === false) {
	echo "Could not open $filename for reading.\n";
	die();
} else {
	echo "Converting ".basename($filename)." ...\n";
}

$sanitizer = '/[^A-Za-z0-9.\-_]*/';
$newINI = '';
$keyMapping = array();
while($line = fgets($fp)) {
	if(empty($line)) {
		$newINI .= "\n";
	} elseif(in_array(substr($line,0,1), array('#',';'))) {
		$newINI .= ';'.substr($line,1)."\n";
	} else {
		$line = trim($line);
		@list($key, $value) = explode('=', $line, 2);
		$key = rtrim($key);
		$value = ltrim($value);

		// Works around PHP-style copyright headers...
		if(empty($key)) continue;
		if(empty($value)) continue;
		
		// Key sanitization
		$oldkey = strtoupper($key);
		$key = strip_tags($key);
		$key = str_replace(' ', '_', $key);
		$key = str_replace('-', '_', $key);
		$key = str_replace('__', '_', $key);
		$key = strtoupper(preg_replace($sanitizer, '', $key));
		if($key != $oldkey) {
			$keyMapping[$oldkey] = $key;
		}
		
		// Value sanitization
		if( (substr($value,0,1) == '"') && (substr($value,-1) == '"') ) {
			$value = trim($value,'"');
		}
		//$value = str_replace('"', '"_QQ_"', $value);
		$value = str_replace('"', '&#34;', $value);
		$value = "\"$value\"";
		
		$newINI .= "$key=$value\n";
	}
}
fclose($fp);

// Backup the INI file
$backupfile = $filename.'.bak';
if(!file_exists($backupfile)) {
	copy($filename, $backupfile);
}

// Overwrite with the update file
echo "Writing new file...\n";
file_put_contents($filename, $newINI);

// Write the key mapping file
if(!empty($keyMapping)) {
	$kmfilename = $filename.'.map';
	echo "NOTE: Keys have been modified. Please consult ".basename($kmfilename)."\n";
	$fp = fopen($kmfilename, 'wt');
	fwrite($fp,"Key mappings (old key => new key)\n\n");
	foreach($keyMapping as $old => $new) {
		fwrite($fp,"$old\t=>\t$new\n");
	}
	fclose($fp);
}