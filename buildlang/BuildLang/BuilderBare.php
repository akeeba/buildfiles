<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\BuildLang;


use DirectoryIterator;

/**
 * Builds the language files from a bare language-only repository
 */
class BuilderBare extends Builder
{
	/**
	 * Scan the languages in this repository
	 *
	 * @return  void
	 */
	protected function scanLanguages($folderToScan = null)
	{
		if (empty($folderToScan))
		{
			$folderToScan = $this->repositoryRoot;
		}

		$this->siteLangFiles  = [];
		$this->adminLangFiles = [];

		foreach (new DirectoryIterator($folderToScan) as $oArea)
		{
			if (!$oArea->isDir() || $oArea->isDot())
			{
				continue;
			}

			$area = $oArea->getFilename();

			$areaDir = $folderToScan . '/' . $area;
			$slug    = array();

			switch ($area)
			{
				case 'template':
				case 'templates':
					$slug[] = 'tpl';
					break;

				case 'library':
				case 'libraries':
					$slug[] = 'lib';
					break;

				case 'component':
					$slug[] = 'com';
					break;

				case 'modules':
					$slug[] = 'mod';
					break;

				case 'plugins':
					$slug[] = 'plg';
					break;

				default:
					break;
			}

			if (empty($slug))
			{
				continue;
			}

			foreach (new DirectoryIterator($areaDir) as $oFolder)
			{
				if (!$oFolder->isDir() || $oFolder->isDot())
				{
					continue;
				}

				$folder    = $oFolder->getFilename();
				$slug[]    = $folder;
				$folderDir = $areaDir . '/' . $folder;

				// Is this a component?
				if (is_dir($folderDir . '/en-GB'))
				{
					$this->scanLanguageFiles($slug, $folderDir);

					array_pop($slug);

					continue;
				}

				// Is this a module or plugin?
				foreach (new DirectoryIterator($folderDir) as $oExtension)
				{
					if (!$oExtension->isDir() || $oExtension->isDot())
					{
						continue;
					}

					$extension    = $oExtension->getFilename();
					$slug[]       = $extension;
					$extensionDir = $folderDir . '/' . $extension;

					if (is_dir($extensionDir . '/en-GB'))
					{
						$this->scanLanguageFiles($slug, $extensionDir);
					}

					array_pop($slug);
				}

				array_pop($slug);
			}
		}
	}

	/**
	 * Scan the language files of a specific component area, plugin, module, etc and add those files to the site or
	 * admin, per-language file list.
	 *
	 * @param $slugArray
	 * @param $rootDir
	 *
	 * @return void
	 */
	private function scanLanguageFiles($slugArray, $rootDir)
	{
		$isBackend = 1;

		switch ($slugArray[0])
		{
			case 'tpl':
			case 'mod':
			case 'com':
				$isBackend = in_array($slugArray[1], ['admin', 'administrator', 'backend']);
				break;

			// Libraries and plugins are always backend files
		}

		foreach (new DirectoryIterator($rootDir) as $oFolder)
		{
			if (!$oFolder->isDir() || $oFolder->isDot())
			{
				continue;
			}

			$langCode = $oFolder->getFilename();

			if ($isBackend && !isset($this->adminLangFiles[$langCode]))
			{
				$this->adminLangFiles[$langCode] = [];
			}
			elseif (!$isBackend && !isset($this->siteLangFiles[$langCode]))
			{
				$this->siteLangFiles[$langCode] = [];
			}

			foreach (new DirectoryIterator($oFolder->getPathname()) as $oFile)
			{
				if (!$oFile->isFile())
				{
					continue;
				}

				if ($isBackend)
				{
					$this->adminLangFiles[$langCode][] = $oFile->getPathname();

					continue;
				}

				$this->siteLangFiles[$langCode][] = $oFile->getPathname();
			}
		}
	}
}
