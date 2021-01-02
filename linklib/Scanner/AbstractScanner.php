<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LinkLibrary\Scanner;

use Akeeba\LinkLibrary\LinkHelper;
use Akeeba\LinkLibrary\MapResult;
use Akeeba\LinkLibrary\ScannerInterface;
use Akeeba\LinkLibrary\ScanResult;
use DirectoryIterator;
use DOMDocument;
use RuntimeException;

abstract class AbstractScanner implements ScannerInterface
{
	/**
	 * The absolute path to the extension's root folder.
	 *
	 * @var   string
	 */
	protected $extensionRoot = '';

	/**
	 * The absolute path to the extension's language folder.
	 *
	 * @var   string
	 */
	protected $languageRoot = '';

	/**
	 * The absolute path to the target Joomla! site's root.
	 *
	 * @var   string
	 */
	protected $siteRoot = '';

	/**
	 * The XML manifest of the extension
	 *
	 * @var   DOMDocument
	 */
	protected $xmlManifest = null;

	/**
	 * The "type" attribute the XML manifest's root node must have.
	 *
	 * @var   string
	 */
	protected $manifestExtensionType = '';

	/**
	 * The results of scanning the extension
	 *
	 * @var   ScanResult
	 */
	private $scanResult = null;

	/**
	 * The results of mapping the scanned extension folders to a site root
	 *
	 * @var   MapResult
	 */
	private $mapResult = null;

	/**
	 * The root folder of the translation files (other than inside the extension itself, holding all languages)
	 *
	 * @var  string
	 */
	private static $translationsRoot = null;

	/**
	 * Constructor.
	 *
	 * The languageRoot is optional and applies only if the languages are stored in a directory other than the one
	 * specified in the extension's XML file.
	 *
	 * @param   string  $extensionRoot  The absolute path to the extension's root folder
	 * @param   string  $languageRoot   The absolute path to the extension's language folder (optional)
	 */
	public function __construct($extensionRoot, $languageRoot = null)
	{
		// Make sure the extension root exists
		if (!is_dir($extensionRoot) || !is_readable($extensionRoot))
		{
			throw new RuntimeException("Cannot scan extension in non-existent or unreadable folder $this->extensionRoot");
		}

		$this->extensionRoot = $extensionRoot;

		// Make sure the language root exists
		if (!empty($languageRoot))
		{
			$this->languageRoot = $languageRoot;

			if (!is_dir($languageRoot) || !is_readable($languageRoot))
			{
				throw new RuntimeException("Cannot scan translations in non-existent or unreadable folder $this->extensionRoot");
			}
		}

		// Scan the extension immediately
		$this->getScanResults();
	}

	/**
	 * Find the XML manifest in an extension's directory and return the DOMDocument for it.
	 *
	 * @param   string   $root           The folder to look into.
	 * @param   string   $extensionType  The expected type of the extension, null to not perform this check.
	 *
	 * @return  DOMDocument|null  The DOMDocument for the XML manifest or null if none was found.
	 */
	protected static function findXmlManifest($root, $extensionType = null)
	{
		foreach (new DirectoryIterator($root) as $fileInfo)
		{
			if ($fileInfo->isDot() || !$fileInfo->isFile())
			{
				continue;
			}

			if ($fileInfo->getExtension() != 'xml')
			{
				continue;
			}

			$xmlDoc = new DOMDocument;
			$xmlDoc->load($fileInfo->getRealPath(), LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_NOENT | LIBXML_NONET);

			$rootNodes    = $xmlDoc->getElementsByTagname('extension');

			if ($rootNodes->length < 1)
			{
				unset($xmlDoc);
				continue;
			}

			$root = $rootNodes->item(0);

			if (!$root->hasAttributes())
			{
				unset($xmlDoc);
				continue;
			}

			if (!empty($extensionType) && ($root->getAttribute('type') != $extensionType))
			{
				unset($xmlDoc);
				continue;
			}

			return $xmlDoc;
		}

		return null;
	}

	/**
	 * Return the XML manifest for this extension
	 *
	 * @return  DOMDocument|null
	 */
	protected function getXMLManifest()
	{
		if (is_null($this->xmlManifest))
		{
			$this->xmlManifest = self::findXmlManifest($this->extensionRoot, $this->manifestExtensionType);
		}

		if (is_null($this->xmlManifest))
		{
			throw new RuntimeException("Cannot find manifest for extension in $this->extensionRoot // $this->manifestExtensionType");
		}

		return $this->xmlManifest;
	}

	/**
	 * Returns the extension root folder
	 *
	 * @return  string
	 */
	public final function getExtensionRoot(): string
	{
		return $this->extensionRoot;
	}

	/**
	 * Returns the language root folder
	 *
	 * @return  string
	 */
	public final function getLanguageRoot(): string
	{
		return $this->languageRoot;
	}

	/**
	 * Get the currently configured Joomla! site root path
	 *
	 * @return  string
	 */
	public final function getSiteRoot(): string
	{
		return $this->siteRoot;
	}

	/**
	 * Set the Joomla! site root path
	 *
	 * @param   string  $path
	 *
	 * @return  void
	 */
	public final function setSiteRoot(string $path)
	{
		$path = realpath($path);

		if ($this->siteRoot != $path)
		{
			$this->mapResult = null;
		}

		$this->siteRoot = $path;
	}

	/**
	 * Retrieves the scan results
	 *
	 * @return  ScanResult
	 */
	public final function getScanResults(): ScanResult
	{
		if (empty($this->scanResult))
		{
			$this->scanResult = $this->scan();
		}

		return $this->scanResult;
	}

	/**
	 * Returns the link map. If the link map does not exist it will be created first.
	 *
	 * @return  MapResult
	 */
	public final function getLinkMap(): MapResult
	{
		if (empty($this->mapResult))
		{
			$this->mapResult = $this->map();
		}

		return $this->mapResult;
	}

	/**
	 * Removes the link map targets. If the link map does not exist it will be created first.
	 *
	 * IMPORTANT: This removes the map targets no matter if they are links or real folders / files.
	 *
	 * @return  void
	 */
	public final function unlink()
	{
		$map = $this->getLinkMap();

		$dirs = $map->dirs;
		$files = $map->files;
		$hardfiles = $map->hardfiles;

		if (!empty($dirs)) foreach($dirs as $from => $to)
		{
			LinkHelper::recursiveUnlink($to);
		}

		if (!empty($files)) foreach($files as $from => $to)
		{
			LinkHelper::unlink($to);
		}

		if (!empty($hardfiles)) foreach($hardfiles as $from => $to)
		{
			LinkHelper::unlink($to);
		}
	}

	/**
	 * Links the map targets. If the link map does not exist it will be created first.
	 *
	 * @return  void
	 */
	public final function relink()
	{
		$map = $this->getLinkMap();

		$dirs = $map->dirs;
		$files = $map->files;
		$hardfiles = $map->hardfiles;

		if (!empty($dirs)) foreach($dirs as $from => $to)
		{
			LinkHelper::symlink($from, $to);
		}

		if (!empty($files)) foreach($files as $from => $to)
		{
			LinkHelper::symlink($from, $to);
		}

		if (!empty($hardfiles)) foreach($hardfiles as $from => $to)
		{
			try
			{
				LinkHelper::hardlink($from, $to);

				continue;
			}
			catch (\Exception $e)
			{
				// Hard link failure. We can live with that since usually it's referring to CLI scripts
				echo "An error occurred while linking $from -> $to:";
				echo "\t" . $e->getMessage() . "\n";
			}

			// If an exception was raised I retry with symlinks; necessary crossing filesystems, e.g. encrypted home
			try
			{
				LinkHelper::symlink($from, $to);
			}
			catch (\Exception $e)
			{
				// Yeah, well, screw it.
			}
		}
	}

	/**
	 * Scans a <language> node in the XML manifest and returns information about the languagess.
	 *
	 * @param   \DOMElement  $node  The node to scan
	 *
	 * @return  array
	 */
	protected final function scanLanguageNode(\DOMElement $node)
	{
		$folder = $this->extensionRoot;
		$files  = [];

		if ($node->hasAttribute('folder'))
		{
			$folder .= '/' . $node->getAttribute('folder');
		}

		if ($node->hasChildNodes())
		{
			foreach ($node->childNodes as $langFile)
			{
				if (!($langFile instanceof \DOMElement))
				{
					continue;
				}

				$tag = $langFile->getAttribute('tag');

				if (!isset($files[$tag]))
				{
					$files[$tag] = [];
				}

				$files[$tag][] = $folder . '/' . $langFile->textContent;
			}
		}

		return array($folder, $files);
	}

	/**
	 * Scan a folder for Joomla! INI language files. The folder must have the structure languageTag => files e.g.
	 * en-GB/en-GB.com_foobar.ini
	 * en-GB/en-GB.com_foobar.sys.ini
	 * fr-FR/fr-FR.com_foobar.ini
	 * fr-FR/fr-FR.com_foobar.sys.ini
	 * ...
	 *
	 * The returned array is keyed on language, e.g.
	 * [
	 *   'en-GB' => ['/path/to/en-GB/en-GB.com_foobar.ini', '/path/to/en-GB/en-GB.com_foobar.sys.ini'],
	 *   'fr-FR' => ['/path/to/fr-FR/fr-FR.com_foobar.ini', '/path/to/fr-FR/fr-FR.com_foobar.sys.ini'],
	 *    ...
	 * ]
	 *
	 * @param   string  $langPath  The path to scan
	 *
	 * @return  array  The discovered language files
	 */
	protected final function scanLanguageFolder(string $langPath): array
	{
		$ret = [];

		// Make sure the folder exists
		if (!is_dir($langPath))
		{
			return $ret;
		}

		// Iterate the outermost folders (language tags)
		$langFolders = new DirectoryIterator($langPath);

		foreach ($langFolders as $folder)
		{
			if (!$folder->isDir() || $folder->isDot())
			{
				continue;
			}

			$tag = $folder->getFilename();
			$ret[$tag] = [];

			// Iterate the innermost files of each language folder (language files)
			$langFiles = new DirectoryIterator($folder->getPathname());

			foreach ($langFiles as $file)
			{
				if (!$file->isFile())
				{
					continue;
				}

				if ($file->getExtension() != 'ini')
				{
					continue;
				}

				$ret[$tag][] = $file->getRealPath();
			}
		}
		return $ret;
	}

	/**
	 * Parses the last scan and generates a link map
	 *
	 * @return  MapResult
	 */
	public function map()
	{
		$scan = $this->getScanResults();

		// Initialize
		$hardfiles = [];
		$files     = [];
		$dirs      = [];

		// Media directory
		if ($scan->mediaFolder)
		{
			$destination = $this->siteRoot . '/media/' . $scan->getJoomlaExtensionName();

			if (!empty($scan->mediaDestination))
			{
				$destination = $this->siteRoot . '/media/' . $scan->mediaDestination;
			}

			$dirs[$scan->mediaFolder] = $destination;
		}

		// CLI files
		if ($scan->cliFolder)
		{
			foreach (new \DirectoryIterator($scan->cliFolder) as $fileInfo)
			{
				if ($fileInfo->isDot() || !$fileInfo->isFile())
				{
					continue;
				}

				if ($fileInfo->getExtension() == 'xml')
				{
					continue;
				}

				$hardfiles[$fileInfo->getRealPath()] = $this->siteRoot . '/cli/' . $fileInfo->getFilename();
			}
		}

		// Front-end language files
		if (!empty($scan->siteLangFiles))
		{
			$basePath = $this->siteRoot . '/language/';

			foreach ($scan->siteLangFiles as $tag => $languageFiles)
			{
				$path = $basePath . $tag . '/';

				if (!is_dir($path))
				{
					continue;
				}

				foreach ($languageFiles as $langFile)
				{
					$files[$langFile] = $path . basename($langFile);
				}
			}
		}

		// Back-end language files
		if (!empty($scan->adminLangFiles))
		{
			$basePath = $this->siteRoot . '/administrator/language/';

			foreach ($scan->adminLangFiles as $tag => $languageFiles)
			{
				$path = $basePath . $tag . '/';

				if (!is_dir($path))
				{
					continue;
				}

				foreach ($languageFiles as $langFile)
				{
					$files[$langFile] = $path . basename($langFile);
				}
			}
		}

		// API language files
		if (!empty($scan->apiLangFiles))
		{
			$basePath = $this->siteRoot . '/api/language/';

			foreach ($scan->apiLangFiles as $tag => $languageFiles)
			{
				$path = $basePath . $tag . '/';

				if (!is_dir($path))
				{
					continue;
				}

				foreach ($languageFiles as $langFile)
				{
					$files[$langFile] = $path . basename($langFile);
				}
			}
		}

		$result            = new MapResult();
		$result->dirs      = $dirs;
		$result->files     = $files;
		$result->hardfiles = $hardfiles;

		return $result;
	}

	/**
	 * Get a unique extension name. For modules and templates this includes the indicator site_ or admin_ before the
	 * actual name of the extension.
	 *
	 * @return  string
	 */
	public final function getKeyName()
	{
		return $this->getScanResults()->getJoomlaExtensionName(true);
	}


	/**
	 * Get the root folder for the translation files in all languages. This is a directory in the root of the repository
	 * called "translations". Inside it you have all language files using the following structure:
	 *
	 * component
	 *      frontend
	 *          en-GB, ...
	 *      backend
	 *          en-GB, ...
	 * plugins
	 *      somePluginFolder
	 *          pluginName
	 *              en-GB, ...
	 *          otherPlugins...
	 *      otherPluginFolders...
	 * modules
	 *      site
	 *          frontendModuleName
	 *              en-GB, ...
	 *          otherFrontendModules,...
	 *      admin
	 *          frontendModuleName
	 *              en-GB, ...
	 *          otherFrontendModules,...
	 *
	 * @param   string  $siteRoot  The site root to check for the translations root
	 *
	 * @return  string
	 */
	public static function getTranslationsRoot(string $siteRoot): string
	{
		if (is_null(self::$translationsRoot))
		{
			self::$translationsRoot = '';
			$possibleFolders        = ['translations', 'translation', 'languages', 'language', 'weblate', 'translate', 'strings'];

			foreach ($possibleFolders as $folder)
			{
				$path = $siteRoot . '/' . $folder;

				if (is_dir($path))
				{
					self::$translationsRoot = $path;

					break;
				}
			}
		}

		return self::$translationsRoot;
	}
}
