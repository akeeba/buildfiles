<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Akeeba Build Tools - Fix Weblate language files
 *
 * Purpose of this tool:
 *
 * Importing Joomla! INI files to Weblate is buggy. It makes this:
 * COM_EXAMPLE_FOO="This is an example"
 * into this broken thing
 * COM_EXAMPLE_FOO=""_QQ_"This is an example"_QQ_""
 * It also seems to have its own mind about how double quotes are encoded. This tool fixes these broken strings.
 */

use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;
use GetOptionKit\OptionResult;

require_once __DIR__ . '/../vendor/autoload.php';

$year = gmdate('Y');
echo <<<ENDBANNER
Akeeba Build Tools - Fix Weblate language files 1.0
Fixes language strings broken by Weblate 
-------------------------------------------------------------------------------
Copyright ©2010-$year Akeeba Ltd
Distributed under the GNU General Public License v3 or later
-------------------------------------------------------------------------------

ENDBANNER;

class Scanner
{
	private $repoRoot;

	private $cliOptions;

	public function __construct(string $repoRoot, OptionResult $cliOptions)
	{
		$this->repoRoot   = $repoRoot;
		$this->cliOptions = $cliOptions;
	}

	public function run($myRoot = null)
	{
		if (is_null($myRoot))
		{
			$myRoot      = $this->repoRoot;
		}

		foreach (new DirectoryIterator($myRoot) as $item)
		{
			if ($item->isDot())
			{
				continue;
			}

			if ($item->isDir())
			{
				$this->run($item->getPathname());

				continue;
			}

			if (!$item->isFile())
			{
				continue;
			}

			if ($item->getExtension() != 'ini')
			{
				continue;
			}

			$this->processLanguageFile($item->getPathname());
		}
	}

	protected function processLanguageFile($filePath)
	{
		$contents = file_get_contents($filePath);

		// Do I even have to process this file?
		if (strpos($contents, '"_QQ_"') === false)
		{
			return;
		}

		echo "$filePath\n";

		$lines = explode("\n", $contents);
		$result = '';

		foreach ($lines as $line)
		{
			$line = trim($line);
			$isComment = substr($line, 0, 1) == ';';
			$isLanguageKey = strpos($line, '=') !== false;

			if (empty($line) || $isComment || !$isLanguageKey)
			{
				$result .= $line . "\n";

				continue;
			}

			list($key, $value) = explode('=', $line, 2);

			// Remove surrounding double quotes
			if (substr($value, 0, 1) == '"')
			{
				$value  = substr($value, 1);
			}

			if (substr($value, -1) == '"')
			{
				$value  = substr($value, 0, -1);
			}

			// Remove surrounding "_QQ_" (the Weblate bug)
			if (substr($value, 0, 6) == '"_QQ_"')
			{
				$value  = substr($value, 6);
			}

			if (substr($value, -6) == '"_QQ_"')
			{
				$value  = substr($value, 0, -6);
			}

			// Replace inline escaped double quote \" with single double quote
			$value = str_replace('\\"', '"', $value);

			// Replace inline old-style "_QQ_" with double quote "
			$value = str_replace('"_QQ_"', '"', $value);

			// Normalize inline double quotes to escaped double quotes
			$value = str_replace('"', '\\"', $value);

			// Construct and add the line
			$result .= $key . '="' . $value . "\"\n";

			// Someone managed to cock up so badly that we ended up with escaped \"_QQ_\" in some files...
			$result = str_replace('="\"_QQ_\"', '="', $result);

			$result = rtrim($result, "\n");

			if (substr($result, -9) == '\"_QQ_\""')
			{
				$result = substr($result, 0, -9) . '"';
			}

			$result .= "\n";
		}

		$result = rtrim($result, "\n");

		// If the contents didn't change we won't touch the file, avoiding an expensive reread of the file by Weblate.
		if ($result == $contents)
		{
			echo "\tunchanged\n";
			return;
		}

		echo "\tCONVERTED\n";

		// Write the file back
		file_put_contents($filePath, $result);
	}
}

$specs = new OptionCollection;
$specs->add('r|repo?', 'Directory holding INI files to scan recursively (default: current working directory).')
	->isa('String');

try
{
	$parser = new OptionParser($specs);
	$result = $parser->parse($argv);
}
catch (Exception $e)
{
	echo $e->getMessage();
	exit (255);
}

if (!$result->count())
{
	$self = basename($argv[0]);
	echo <<< ERRORPREFIX

Usage:
	$self [arguments]

Available arguments:

ERRORPREFIX;

	$printer = new ConsoleOptionPrinter();

	echo $printer->render($parser->specs);

	exit(1);
}

try
{
	$scanner      = new Scanner($result->get('repo'), $result);
	$scanner->run();
}
catch (Exception $e)
{
	echo $e->getMessage();
}
