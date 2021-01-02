<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Fixes the translation INI files to be compatible with Joomla! 1.5 and 1.6
 */

function recursiveApply($path)
{
	$dh = opendir($path);
	while ($entry = readdir($dh))
	{
		if ($entry == '.')
		{
			continue;
		}
		if ($entry == '..')
		{
			continue;
		}
		if (substr($entry, 0, 1) == '.')
		{
			continue;
		}
		if (substr($entry, 0, 1) == '_')
		{
			continue;
		}

		$myDir = $path . DIRECTORY_SEPARATOR . $entry;

		if (is_link($myDir))
		{
			continue;
		}
		if (is_dir($myDir))
		{
			recursiveApply($myDir);
		}
		if (is_file($myDir))
		{
			if (substr($myDir, -4) == '.ini')
			{
				fixTranslation($myDir);
			}
		}
	}
	closedir($dh);
}

function fixTranslation($filename)
{
	static $count = 0;

	$count++;

	echo sprintf('%05u', $count) . "\t$filename\n";
	echo str_repeat('-', 79) . "\n";

	$fp = fopen($filename, 'rt');
	if ($fp == false)
	{
		echo "\tCOULD NOT OPEN FILE!\n";

		return;
	}
	$out = '';
	echo "\tReading file\n";

	$inEmptyLine = false;
	while (!feof($fp))
	{
		$line = fgets($fp);
		$trimmed = trim($line);

		// Transform comments
		if (substr($trimmed, 0, 1) == '#')
		{
			$out .= ';' . substr($trimmed, 1) . "\n";
			continue;
		}

		if (substr($trimmed, 0, 1) == ';')
		{
			$out .= "$trimmed\n";
			continue;
		}

		// Detect blank lines
		if (empty($trimmed))
		{
			if ($inEmptyLine)
			{
				continue;
			}
			$inEmptyLine = true;
			$out .= "\n";
			continue;
		}

		$inEmptyLine = false;

		// Process key-value pairs
		list($key, $value) = explode('=', $trimmed, 2);
		$value = trim($value, '"');
		$value = str_replace('"_QQ_"', '"', $value);
		$value = str_replace('\\"', "'", $value);
		$value = str_replace('"', '"_QQ_"', $value);
		$key = strtoupper($key);
		$key = trim($key);
		$out .= "$key=\"$value\"\n";
	}
	$out = rtrim($out, "\n") . "\n";
	fclose($fp);

	#echo $out."\n\n";
	echo "\tWriting fixed file\n";
	file_put_contents($filename, $out);
}


echo <<<ENDBANNER
Akeeba Translation INI Fixer
===============================================================================
Copyright (c)2014 Nicholas K. Dionysopoulos - All legal rights reserved

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.


ENDBANNER;


$path = __DIR__ . '/translations';
recursiveApply($path);
