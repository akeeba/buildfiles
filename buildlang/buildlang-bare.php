<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Language Package Builder
 */

use Akeeba\BuildLang\Builder;
use Akeeba\BuildLang\BuilderBare;
use Akeeba\BuildLang\Parameters;

require_once __DIR__ . '/include.php';
require_once __DIR__ . '/../linklib/include.php';

$year = gmdate('Y');
echo <<<ENDBANNER
Akeeba Build Tools - Language Builder 2.0
Automatically build and upload language ZIP files 
-------------------------------------------------------------------------------
Copyright ©2010-$year Akeeba Ltd
Distributed under the GNU General Public License v3 or later
-------------------------------------------------------------------------------

WARNING: This is a legacy script, designed to provide CLI options compatibility
         with version 1.0. We recommend using tools/build-language.php instead.

ENDBANNER;

if ($argc < 3)
{
	$script = basename($argv[0]);

	echo <<< USAGE

Usage:
	$script /path/to/build.properties /path/to/repository_root [version]

USAGE;

	exit(255);
}

$propsFile       = $argv[1];
$rootDirectory   = realpath($argv[2]);
$version         = '0.0.' . gmdate('YdmHis');

if ($argc > 3)
{
	$version = $argv[3];
}

$extraProperties = ['extra.version' => $version];

/**
 * If you want to test without actually uploading anything to S3 use the environment variable LANGBUILD_NOUPLOAD.
 * Linux / macOS : LANGBUILD_NOUPLOAD=1 php /path/to/buildlang-bare.php /foo/bar/build.properties `pwd` 1.2.3
 * Windows       : set LANGBUILD_NOUPLOAD=1 & php C:\path\to\buildlang-bare.php Z:\foo\bar\build.properties %CD% 1.2.3
 */
if (getenv('LANGBUILD_NOUPLOAD'))
{
	$temp = sys_get_temp_dir();
	echo "DEBUG MODE: Files will not be uploaded. Look in  $temp\n";

	$extraProperties['extra.uploadToS3'] = false;
}

try
{
	$parameters = new Parameters($propsFile, $extraProperties);
	$builder    = new BuilderBare($rootDirectory, $parameters);
	$builder->buildAll();
}
catch (Exception $e)
{
	echo "WHOOPSIE!\n";
	echo $e->getMessage();
	exit (1);
}
