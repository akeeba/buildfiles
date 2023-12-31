<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Script to move the language files of an Akeeba repository for a Joomla! extension back to the translations folder
 * in the root of the site.
 */

use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;

if (!class_exists('GetOptionKit\\OptionCollection'))
{
	echo "You must run composer install in the BuildFiles repository before using this script.\n";

	exit(254);
}

$specs = new OptionCollection;

$specs->add('t|translations:', 'Absolute path to translations folder, typically /path/to/repository/translations.')
	->isa('dir');

$specs->add('p|params+', 'Absolute path to parameters file. You can use multiple files by using this option multiple times. Each file you add overrides same-named parameters defined in the files before it.')
	->isa('file');

$specs->add('o|output?', 'Absolute path where packages are output.')
	->isa('dir');

$specs->add('v|version?', 'The version to use when building language packages.')
	->isa('version');

// Flags
$specs->add('d|default-params', 'Add the default parameter files to the --params options already defined (the default files are: build.ini in translations folder and two parents; build.properties two parents up from the translations folder).');
$specs->add('q|quiet', 'Suppress output.');
$specs->add('n|no-upload', 'Do not upload the packages to S3. Implies --keep.');
$specs->add('k|keep', 'Keep the generated files.');
$specs->add('h|help', 'Show help and quit.');

try
{
	$parser = new OptionParser($specs);
	$result = $parser->parse($argv);
}
catch (Exception $e)
{
	echo "Could not understand command line options.\n";
	echo $e->getMessage();

	exit (255);
}

return $result;
