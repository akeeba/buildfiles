<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Produce translation statistics
 */

$translationPaths = array(
	'component/translations/backend',
	'component/translations/frontend',
	'modules/mod_akadmin/translations',
	'plugins/aklazy'
);

function getNumberOfKeys($filename)
{
	$fp = fopen($filename, 'rt');
	if ($fp == false)
	{
		return 0;
	}

	$keys = 0;

	while (!feof($fp))
	{
		$line = fgets($fp);
		$trimmed = trim($line);
		if (empty($trimmed))
		{
			continue;
		}
		if (substr($trimmed, 0, 1) == ';')
		{
			continue;
		}
		if (substr($trimmed, 0, 1) == '#')
		{
			continue;
		}
		$keys++;
	}

	fclose($fp);

	return $keys;
}

function getQualityMark($percentage)
{
	if ($percentage < 50)
	{
		return "Unacceptable";
	}
	elseif ($percentage < 60)
	{
		return "Very Poor";
	}
	elseif ($percentage < 70)
	{
		return "Poor";
	}
	elseif ($percentage < 80)
	{
		return "Fair";
	}
	elseif ($percentage < 90)
	{
		return "Good";
	}
	elseif ($percentage <= 99)
	{
		return "Very Good";
	}
	else
	{
		return "Excellent";
	}
}

$langs = array();

foreach ($translationPaths as $root)
{
	echo "\nStatistics for $root\n";

	// Initialize
	$stats = array();

	// Grab key stats from all files
	$files = glob("$root/*.ini");
	foreach ($files as $filename)
	{
		$base = basename($filename, '.ini');
		list($language, $key) = explode('.', $base, 2);
		$transKeys = getNumberOfKeys($filename);

		$stats[$key][$language] = $transKeys;

		if (!array_key_exists($language, $langs))
		{
			$langs[$language] = 0;
		}
	}

	// Show stats for each key
	foreach ($stats as $key => $table)
	{
		asort($table);
		if (!array_key_exists('en-GB', $table))
		{
			continue;
		}
		echo "\t$key :\n";
		$ref = $table['en-GB'];
		$langs['en-GB'] += $ref;
		foreach ($table as $language => $keys)
		{
			if ($language == 'en-GB')
			{
				continue;
			}
			$langs[$language] += $keys;
			$percentage = (int)(100 * ($keys / $ref));
			if (($percentage == 100) && ($keys < $ref))
			{
				$percentage = 99;
			}
			$xofx = sprintf('%03u of %03u', $keys, $ref);
			echo "\t\t$language\t$xofx\t[$percentage%]\t" . getQualityMark($percentage) . "\n";
		}
	}
}

echo "\n\n-------------------------------------------------------------------------------\nOVERALL TRANSLATION STATISTICS\n-------------------------------------------------------------------------------\n";
$totalLangs = count($langs);

$ref = $langs['en-GB'];
unset($langs['en-GB']);

asort($langs);

$totalKeys = 0;
foreach ($langs as $language => $keys)
{
	$totalKeys += $keys;
	$percentage = (int)(100 * ($keys / $ref));
	if (($percentage == 100) && ($keys < $ref))
	{
		$percentage = 99;
	}
	$xofx = sprintf('%03u of %03u', $keys, $ref);
	echo "\t$language\t$xofx\t[$percentage%]\t" . getQualityMark($percentage) . "\n";
}

$avgKeys = $totalKeys / ($totalLangs - 1);
$avgQuality = 100 * $avgKeys / $keys;

echo "\nTOTAL LANGUAGES : $totalLangs\n";
echo "AVERAGE QUALITY : " . sprintf('%02u', $avgQuality) . '% (' . getQualityMark($avgQuality) . ")\n\n";
