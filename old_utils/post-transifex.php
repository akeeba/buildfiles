<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Fixes the translation folders after you pull them in from Transifex…
 */

function fixitup($dir)
{
	echo "\t$dir\n";

	$allLangs = array();
	$dh = opendir($dir);
	while ($subdir = readdir($dh))
	{
		if (substr($subdir, 0, 1) == '.')
		{
			continue;
		}
		if (!is_dir($dir . '/' . $subdir))
		{
			continue;
		}
		if (substr($subdir, 2, 1) == '-')
		{
			continue;
		}

		$allLangs[] = $subdir;
	}
	closedir($dh);

	foreach ($allLangs as $tLang)
	{
		$jLang = str_replace('_', '-', $tLang);
		$sourceDir = $dir . '/' . $tLang;
		$destDir = $dir . '/' . $jLang;
		if (!is_dir($destDir))
		{
			mkdir($destDir);
		}

		$dh = opendir($sourceDir);
		while ($file = readdir($dh))
		{
			if (!is_file($sourceDir . '/' . $file))
			{
				continue;
			}

			$sourceFile = $sourceDir . '/' . $file;
			$destFile = $destDir . '/' . str_replace($tLang, $jLang, $file);
			copy($sourceFile, $destFile);
			unlink($sourceFile);
		}
		closedir($dh);
		rmdir($sourceDir);
	}
}

$root = dirname(__FILE__) . '/translations';
// Fix component language files
echo "Fixing component translations\n";
$dh = opendir($root . '/component');
while ($file = readdir($dh))
{
	if (substr($file, 0, 1) == '.')
	{
		continue;
	}
	if (!is_dir($root . '/component/' . $file))
	{
		continue;
	}

	fixitup($root . '/component/' . $file);
}
closedir($dh);

// Fix module language files
echo "Fixing modules translations\n";
$dh = opendir($root . '/modules');
while ($file = readdir($dh))
{
	if (substr($file, 0, 1) == '.')
	{
		continue;
	}
	if (!is_dir($root . '/modules/' . $file))
	{
		continue;
	}

	$dh2 = opendir($root . '/modules/' . $file);
	while ($subfolder = readdir($dh2))
	{
		if (substr($subfolder, 0, 1) == '.')
		{
			continue;
		}
		if (!is_dir($root . '/modules/' . $file . '/' . $subfolder))
		{
			continue;
		}

		fixitup($root . '/modules/' . $file . '/' . $subfolder);
	}
	closedir($dh2);
}
closedir($dh);

// Fix plugin language files
echo "Fixing plugins translations\n";
$dh = opendir($root . '/plugins');
while ($file = readdir($dh))
{
	if (substr($file, 0, 1) == '.')
	{
		continue;
	}
	if (!is_dir($root . '/plugins/' . $file))
	{
		continue;
	}

	$dh2 = opendir($root . '/plugins/' . $file);
	while ($subfolder = readdir($dh2))
	{
		if (substr($subfolder, 0, 1) == '.')
		{
			continue;
		}
		if (!is_dir($root . '/plugins/' . $file . '/' . $subfolder))
		{
			continue;
		}

		fixitup($root . '/plugins/' . $file . '/' . $subfolder);
	}
	closedir($dh2);
}
closedir($dh);
