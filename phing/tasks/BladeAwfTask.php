<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace tasks;

use DirectoryIterator;
use Exception;
use Phing\Exception\BuildException;
use Phing\Project;
use Phing\Task;
use Phing\Type\DirSet;

/**
 * Class BladeAWFTask
 *
 * Precompiles Blade templates for AWF applications.
 */
class BladeAwfTask extends Task
{
	/**
	 * AWF site root (we need to load AWF)
	 *
	 * @var   string
	 */
	protected $site = '';

	/**
	 * The AWF application name
	 *
	 * @var   string
	 */
	protected $appName = '';

	/**
	 * The folder inside the $site root where the AWF framework is stored
	 *
	 * @var   string
	 */
	protected $awfFolder = 'Awf';

	/**
	 * Source top-level view template directories
	 *
	 * @var  DirSet[]
	 */
	protected $dirsets = [];

	/**
	 * The name of the folder where the precompiled templates are stored. This is always on the same level as the top-
	 * level source view template directory.
	 *
	 * @var string
	 */
	protected $precompiledName = 'PrecompiledTemplates';

	/**
	 * Adds a set of files (nested dirset attribute).
	 */
	public function createDirSet()
	{
		$num = array_push($this->dirsets, new DirSet());

		return $this->dirsets[$num - 1];
	}

	public function setSite($site)
	{
		$this->site = $site;

		if (!is_dir($site))
		{
			throw new BuildException("The folder “{$site}” does not exist");
		}
	}

	/**
	 * @param   string  $appName
	 *
	 * @return  void
	 */
	public function setAppName($appName)
	{
		$this->appName = $appName;
	}

	/**
	 * @param   string  $awfFolder
	 *
	 * @return  void
	 */
	public function setAwfFolder(string $awfFolder)
	{
		$this->awfFolder = $awfFolder;
	}

	/**
	 * Initialization
	 */
	public function init()
	{
		return true;
	}

	/**
	 * Main entry point for task
	 *
	 * @return  bool
	 *
	 * @throws  Exception
	 */
	public function main()
	{
		if (count($this->dirsets) == 0)
		{
			throw new BuildException("You must specify a nested dirset");
		}

		// Include the autoloader
		if (!class_exists('Awf\\Autoloader\\Autoloader'))
		{
			if (false == include $this->site . '/' . $this->awfFolder . '/Autoloader/Autoloader.php')
			{
				throw new BuildException('Cannot load AWF autoloader');
			}
		}

		// Do not remove. Required for magic autoloading of necessary files.
		class_exists('\\Awf\\Utils\\Collection');

		// Load the platform defines
		if (!defined('APATH_BASE') && !class_exists('\\Awf\\Container\\Container'))
		{
			require_once $this->site . '/defines.php';
		}

		try
		{
			$container = new \Awf\Container\Container(array(
				'application_name'	=> $this->appName
			));

			$blade = $container->blade;
		}
		catch (Exception $e)
		{
			throw new BuildException($e->getMessage());
		}

		foreach ($this->dirsets as $dirSet)
		{
			$baseDir = $dirSet->getDir($this->project);
			$roots = $dirSet->getDirectoryScanner($this->project)->getIncludedDirectories();

			if (empty($roots))
			{
				$this->log("Empty DirSet. Skipping over.", Project::MSG_WARN);

				continue;
			}

			foreach ($roots as $root)
			{
				$root = $baseDir . '/' . $root;

				if (!is_dir($root))
				{
					$this->log("Folder “{$root}” does not exist.", Project::MSG_WARN);

					continue;
				}

				$root = realpath($root);

				$this->log("Precompiling Blade templates in folder “{$root}”.", Project::MSG_INFO);

				// Location of precompiled templates
				$outRoot = dirname($root) . '/PrecompiledTemplates';

				$dirIterator = new DirectoryIterator($root);

				// Loop View directories
				/** @var DirectoryIterator $viewDir */
				foreach ($dirIterator as $viewDir)
				{
					if (!$viewDir->isDir())
					{
						continue;
					}

					if ($viewDir->isDot())
					{
						continue;
					}

					$tmplPath     = $viewDir->getRealPath();
					$outPath      = $outRoot . '/' . $viewDir->getBasename();

					// Do I have to dive into a tmpl directory...?
					if (is_dir( $tmplPath . '/tmpl'))
					{
						// Note that $outPath is inside the PrecompiledTemplates folder, thius it never uses a tmpl subdirectory!
						$tmplPath     .= '/tmpl';
					}

					if (!is_dir($tmplPath))
					{
						continue;
					}

					// Look for .blade.php files
					$tmplIterator = new DirectoryIterator($tmplPath);

					/** @var DirectoryIterator $file */
					foreach ($tmplIterator as $file)
					{
						if (!$file->isFile())
						{
							continue;
						}

						$basename = $file->getBasename();

						if (substr($basename, -10) != '.blade.php')
						{
							continue;
						}

						$inFile  = $file->getRealPath();
						$outFile = $outPath . '/' . substr(basename($inFile), 0, -10) . '.php';

						$this->log("Precompiling $inFile to $outFile", Project::MSG_VERBOSE);

						try
						{
							$compiled = $blade->compile($inFile);
						}
						catch (Exception $e)
						{
							throw new BuildException("Cannot compile Blade file $inFile");
						}

						if (!is_dir($outPath))
						{
							mkdir($outPath, 0755, true);
						}

						if (!file_put_contents($outFile, $compiled))
						{
							throw new BuildException("Cannot write to pre-compiled Blade file $compiled");
						}
					}
				}
			}
		}

		return true;
	}
}
