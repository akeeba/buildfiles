<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Script to relink the repository's extensions to a Joomla site
 */

/**
 * Displays the usage of this tool
 *
 * @return  void
 */
function showUsage()
{
	$file = basename(__FILE__);
	echo <<<ENDUSAGE

Usage:
	php $file /path/to/site /path/to/repository [--dry-run] [--silent]

ENDUSAGE;
}

$year = gmdate('Y');
echo <<<ENDBANNER
Akeeba Build Tools - Relinker 3.1
No-configuration extension linker
-------------------------------------------------------------------------------
Copyright ©2010-$year Akeeba Ltd
Distributed under the GNU General Public License v3 or later
-------------------------------------------------------------------------------

ENDBANNER;

if ($argc < 3)
{
	showUsage();
	die();
}

if (!class_exists('Akeeba\\LinkLibrary\\Relink'))
{
	require_once __DIR__ . '/../linklib/include.php';
}

$siteRoot = $argv[1];
$repoRoot = $argv[2];

$dryRun = in_array('--dry-run', $argv);
$silent = in_array('--silent', $argv);

if (!$silent)
{
	error_reporting(E_ALL);
	ini_set('display_errors', true);
}

try
{
	$relink = new \Akeeba\LinkLibrary\Relink($repoRoot);
	$relink->setVerbose(!$silent);
	$relink->setDryRun($dryRun);
	$relink->relink($siteRoot);
}
catch (Throwable $e)
{
	echo <<< TEXT

ERROR
===============================================================================
{$e->getMessage()}
#0 {$e->getFile()}({$e->getLine()})
{$e->getTraceAsString()}

TEXT;

}
