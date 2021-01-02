<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Joomla manifest XML file version change
 *
 * This scripts trawls the component, modules and plugins folders up to three levels deep for Joomla! XML manifest files
 * and changes the version and date of these files to the ones specified in the command line.
 */

define('MAX_DIR_LEVEL', 4);

function show_header()
{
	$year = gmdate('Y');
	echo <<<ENDBANNER
Akeeba Build Tools - Manifest XML Version Change

Changes the version and date of Joomla XML manifest files in the repository.

-------------------------------------------------------------------------------
Copyright ©2010-$year Akeeba Ltd
Distributed under the GNU General Public License v3 or later
-------------------------------------------------------------------------------

ENDBANNER;
}

function usage()
{
	$thisDate = gmdate('Y-m-d');
	$file     = basename(__FILE__);
	echo <<<ENDUSAGE

Usage:
	php $file /path/to/repository VERSION DATE

Example:
	php $file /path/to/repository "1.2.3.b1" "$thisDate"

ENDUSAGE;

}

function scan($baseDir, $level = 0)
{
	$di = new DirectoryIterator($baseDir);

	foreach ($di as $entry)
	{
		if ($entry->isDot())
		{
			continue;
		}

		if ($entry->isLink())
		{
			continue;
		}

		if ($entry->isDir())
		{
			if ($level < MAX_DIR_LEVEL)
			{
				scan($entry->getPathname(), $level + 1);
			}

			continue;
		}

		if (!$entry->isFile() || !$entry->isReadable())
		{
			continue;
		}

		if ($entry->getExtension() != 'xml')
		{
			continue;
		}

		echo $entry->getPathname();

		$result = convert($entry->getPathname());

		echo $result ? "  -- CONVERTED\n" : "  -- (invalid)\n";
	}
}

function convert($filePath)
{
	global $toDate, $toVersion;

	$fileData = file_get_contents($filePath);

	if (strpos($fileData, '<extension ') === false)
	{
		return false;
	}

	$pattern     = '#<creationDate>.*</creationDate>#';
	$replacement = "<creationDate>$toDate</creationDate>";
	$fileData    = preg_replace($pattern, $replacement, $fileData);

	if (is_null($fileData))
	{
		return false;
	}

	$pattern     = '#<version>.*</version>#';
	$replacement = "<version>$toVersion</version>";
	$fileData    = preg_replace($pattern, $replacement, $fileData);

	if (is_null($fileData))
	{
		return false;
	}

	file_put_contents($filePath, $fileData);

	return true;
}

show_header();

global $argv, $argc, $toVersion, $toDate;

if ($argc < 3)
{
	usage();

	exit(1);
}

$baseDir   = $argv[1];
$toVersion = $argv[2];
$toDate    = $argv[3];

echo "\nScanning $baseDir\n\n";

$paths = [
	$baseDir . '/component',
	$baseDir . '/plugins',
	$baseDir . '/modules',
];

array_walk($paths, 'scan');