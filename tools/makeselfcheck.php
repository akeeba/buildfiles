<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

$zipPath = $argv[1];
$component = $argv[2];
$version = $argv[3];
$date = $argv[4];

$zip = new ZipArchive();
$res = $zip->open($zipPath);

if ($res === false)
{
	die("Could not open $zipPath for reading\n");
}

$killList = [];
$dirList = [];
$fileList = [];

for ($i = 0; $i < $zip->numFiles; $i++)
{
	$filename = $zip->getNameIndex($i);

	// Mark .DS_Store / Thumbs.db files for removal
	if (in_array(basename($filename), ['.DS_Store', 'Thumbs.db']))
	{
		$killList[] = $i;
		continue;
	}

	// Skip backend/fileslist.php
	if ($filename == 'backend/fileslist.php')
	{
		continue;
	}

	// Skip files in the root of the archive
	if (!stristr($filename, '/'))
	{
		continue;
	}

	// Ignore index.html and index.htm files
	if ((basename($filename) == 'index.html') || (basename($filename) == 'index.htm'))
	{
		continue;
	}

	// Ignore .ini and .txt files
	if (in_array(substr($filename, -4), ['.ini','.txt']))
	{
		continue;
	}

	// Ignore .txt files
	if (substr($filename, -4) == '.txt')
	{
		continue;
	}

	// Get the top-level directory
	$parts = explode('/', $filename, 2);
	$basePath = $parts[0];
	$filename = $parts[1];

	// Get the relative path to the site's root
	switch ($basePath)
	{
		case 'backend':
			$filename = "administrator/components/$component/$filename";
			break;

		case 'frontend':
			$filename = "components/$component/$filename";
			break;

		case 'cli':
			$filename = "cli/$filename";
			break;

		case 'media':
			$filename = "media/$component/$filename";
			break;

		case 'plugins':
			$baseName = basename($filename);
			$dirName = dirname($filename);

			$filename = "plugins/$filename";
			break;

		case 'language':
			$parts = explode('/', $filename, 2);
			$extraPath = $parts[0];
			$filename = $parts[1];

			if ($extraPath == 'frontend')
			{
				$filename = "language/$filename";
			}
			else
			{
				$filename = "administrator/language/$filename";
			}
			break;

		case 'modules':
			// admin, site
			$parts = explode('/', $filename, 2);
			$extraPath = $parts[0];
			$filename = $parts[1];

			if ($extraPath == 'site')
			{
				$filename = "modules/$filename";
			}
			else
			{
				$filename = "administrator/modules/$filename";
			}
			break;
	}

	// Add the path to dirList
	$relativePath = dirname($filename);

	if (!in_array($relativePath, $dirList))
	{
		$dirList[] = $relativePath;
	}

	// Get the file contents and calculate length, MD5, SHA1 – unless it's a directory
	$fileContents = $zip->getFromIndex($i);

	if ((substr($filename, -1) != '/') && ($fileContents !== false))
	{
		$fileData = [
			strlen($fileContents),
			md5($fileContents),
			sha1($fileContents)
		];

		$fileList[$filename] = $fileData;
	}
}

// Remove files in killList
if (!empty($killList))
{
	foreach ($killList as $killIndex)
	{
		$zip->deleteIndex($killIndex);
	}
}

// Build the component/backend/fileslist.php file
$phpFile = "<?php defined('_JEXEC') or die;\n";
$phpFile .= '$phpFileChecker = array(' . "\n\t'version' => '" . $version . "',\n\t'date' => '" . $date . "',\n\t'directories' => array(\n";

foreach ($dirList as $dir)
{
	$phpFile .= "\t\t'$dir',\n";
}

$phpFile .= "\t),\n\t'files' => array(\n";

foreach ($fileList as $filename => $fileData)
{
	$phpFile .= "\t\t'$filename' => array(";

	foreach ($fileData as $data)
	{
		$phpFile .= "'$data', ";
	}

	$phpFile = substr($phpFile, 0, -2);

	$phpFile .= "),\n";
}

$phpFile .= "\t)\n);";

$zip->close();
$res = $zip->open($zipPath);
$zip->addFromString('backend/fileslist.php', $phpFile);
$zip->close();
