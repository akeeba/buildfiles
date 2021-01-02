<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Class BladeJoomlaTask
 *
 * Precompiles Blade templates for Joomla components.
 */
class BladeJoomlaTask extends Task
{
	/**
	 * Joomla site root (we need to load FOF)
	 *
	 * @var   string
	 */
	protected $site = '';

	/**
	 * The component's name with the com_ prefix
	 *
	 * @var   string
	 */
	protected $component = '';

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

	public function setComponent($component)
	{
		$this->component = $component;
	}

	public function setSite($site)
	{
		$this->site = $site;

		if (!is_dir($site))
		{
			throw new BuildException("The folder “{$site}” does not exist");
		}

		if (!is_file($site . '/configuration.php'))
		{
			throw new BuildException("The folder “{$site}” does not seem to be a Joomla! site");
		}
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

		if (!defined('_JEXEC'))
		{
			define('_JEXEC', 1);
		}

		define('JPATH_BASE', $this->site);
		require_once JPATH_BASE . '/includes/defines.php';

		// Load the framework include files
		require_once JPATH_LIBRARIES . '/import.legacy.php';

		// Load the CMS import file (newer Joomla! 3 versions)
		$cmsImportPath = JPATH_LIBRARIES . '/cms.php';

		if (file_exists($cmsImportPath))
		{
			@include_once $cmsImportPath;
		}

		// Load requirements for various versions of Joomla!
		JLoader::import('joomla.base.object');
		JLoader::import('joomla.application.application');
		JLoader::import('joomla.application.applicationexception');
		JLoader::import('joomla.log.log');
		JLoader::import('joomla.registry.registry');
		JLoader::import('joomla.filter.input');
		JLoader::import('joomla.filter.filterinput');
		JLoader::import('joomla.factory');

		if (version_compare(JVERSION, '3.4.9999', 'ge'))
		{
			// Joomla! 3.5 and later does not load the configuration.php unless you explicitly tell it to.
			JFactory::getConfig(JPATH_CONFIGURATION . '/configuration.php');
		}

		if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
		{
			throw new RuntimeException('FOF 3.0 is not installed', 500);
		}

		try
		{
			$container = FOF30\Container\Container::getInstance($this->component);
			$blade = new \FOF30\View\Compiler\Blade($container);
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
