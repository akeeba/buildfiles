<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Script to move the language files of an Akeeba repository for a Joomla! extension back to the translations folder
 * in the root of the site.
 */

use Akeeba\LinkLibrary\Scanner\AbstractScanner;
use Akeeba\LinkLibrary\Scanner\Component;
use Akeeba\LinkLibrary\Scanner\Library;
use Akeeba\LinkLibrary\Scanner\Module;
use Akeeba\LinkLibrary\Scanner\Plugin;
use Akeeba\LinkLibrary\Scanner\Template;
use Akeeba\LinkLibrary\ScannerInterface;

class LanguageLinker
{
	/**
	 * The root folder of the repository
	 *
	 * @var   string
	 */
	private $repoRoot;

	/**
	 * The name of the language folder (translations, translation, language, ...)
	 *
	 * @var   string
	 */
	private $languageFolder;

	/**
	 * The extensions detected in the repository
	 *
	 * @var   ScannerInterface[]
	 */
	private $extensions;

	public function __construct($repoRoot, $languageFolder = null)
	{
		$this->repoRoot       = $repoRoot;
		$this->languageFolder = is_null($languageFolder) ? basename(AbstractScanner::getTranslationsRoot($repoRoot)) : $languageFolder;

		$this->scanRepositoryExtensions($repoRoot);
	}

	/**
	 * Move the language files FROM each extension's language folder TO the repository's translations folder
	 *
	 * @return void
	 */
	public function moveLanguageFiles()
	{
		/** @var ScannerInterface $extension */
		foreach ($this->extensions as $extension)
		{
			$scanResults = $extension->getScanResults();
			$langFolderRoot = $this->repoRoot . '/' . $this->languageFolder . '/';

			switch ($scanResults->extensionType)
			{
				case 'component':
					if (is_array($scanResults->siteLangFiles) && isset($scanResults->siteLangFiles['en-GB']))
					{
						$targetDir = $langFolderRoot . "{$scanResults->extensionType}/frontend/en-GB/";
						$this->copyFiles($scanResults->siteLangFiles['en-GB'], $targetDir);
					}

					if (is_array($scanResults->adminLangFiles) && isset($scanResults->adminLangFiles['en-GB']))
					{
						$targetDir = $langFolderRoot . "{$scanResults->extensionType}/backend/en-GB/";
						$this->copyFiles($scanResults->adminLangFiles['en-GB'], $targetDir);
					}
					break;

				case 'module':
				case 'template':
				case 'library':
					// Drop the mod_/tpl_ prefix
					$bareExtensionName = substr($scanResults->extension, 4);
					$prefix = ($scanResults->extensionType == 'library') ? 'librarie' : $scanResults->extensionType;

					if (is_array($scanResults->siteLangFiles) && isset($scanResults->siteLangFiles['en-GB']))
					{
						$siteAdmin = ($scanResults->extensionType == 'library') ? 'frontend' : 'site';
						$targetDir = $langFolderRoot . "{$prefix}s/$siteAdmin/$bareExtensionName/en-GB/";
						$this->copyFiles($scanResults->siteLangFiles['en-GB'], $targetDir);
					}

					if (is_array($scanResults->adminLangFiles) && isset($scanResults->adminLangFiles['en-GB']))
					{
						$siteAdmin = ($scanResults->extensionType == 'library') ? 'frontend' : 'site';
						$targetDir = $langFolderRoot . "{$prefix}s/$siteAdmin/$bareExtensionName/en-GB/";
						$this->copyFiles($scanResults->adminLangFiles['en-GB'], $targetDir);
					}
					break;

				case 'plugin':
					// Drop the plg_ prefix
					list($plgPrefix, $folder, $pluginName) = explode('_', $scanResults->getJoomlaExtensionName(), 3);

					if (is_array($scanResults->adminLangFiles) && isset($scanResults->adminLangFiles['en-GB']))
					{
						$targetDir = $langFolderRoot . "plugins/$folder/$pluginName/en-GB/";
						$this->copyFiles($scanResults->adminLangFiles['en-GB'], $targetDir);
					}
					break;
			}
		}
	}

	protected function copyFiles(array $fileList, string $targetDir)
	{
		if (!is_dir($targetDir))
		{
			mkdir($targetDir, 0755, true);
		}

		foreach ($fileList as $source)
		{
			$target = rtrim($targetDir, '\\/') . '/' . basename($source);
			echo "$source => $target\n";
			@copy($source, $target);
		}
	}

	/**
	 * @param $repoRoot
	 *
	 * @return void
	 */
	private function scanRepositoryExtensions($repoRoot)
	{
		$extensions = [];
		$extensions = array_merge($extensions, Component::detect($repoRoot));
		$extensions = array_merge($extensions, Library::detect($repoRoot));
		$extensions = array_merge($extensions, Module::detect($repoRoot));
		$extensions = array_merge($extensions, Plugin::detect($repoRoot));
		$extensions = array_merge($extensions, Template::detect($repoRoot));

		$this->extensions = $extensions;
	}

}

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
	php $file /path/to/repository

ENDUSAGE;
}

$year = gmdate('Y');
echo <<<ENDBANNER
Akeeba Build Tools - MoveBackLanguages 1.0
Populate the translations folder of a repository
-------------------------------------------------------------------------------
Copyright ©2010-$year Akeeba Ltd
Distributed under the GNU General Public License v3 or later
-------------------------------------------------------------------------------

ENDBANNER;

if ($argc < 2)
{
	showUsage();
	die();
}

$repoRoot = $argv[1];

if (!class_exists('Akeeba\\LinkLibrary\\Relink'))
{
	require_once __DIR__ . '/../linklib/include.php';
}

$langLinker = new LanguageLinker($repoRoot);
$langLinker->moveLanguageFiles();
