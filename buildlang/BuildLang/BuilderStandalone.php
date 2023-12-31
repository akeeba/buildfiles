<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\BuildLang;


use DirectoryIterator;
use ZipArchive;

/**
 * Builds the language files of a standalone script
 */
class BuilderStandalone extends Builder
{
	public function __construct($repositoryRoot, Parameters $parameters)
	{
		$this->siteLangFiles  = [];
		$this->adminLangFiles = [];

		parent::__construct($repositoryRoot, $parameters);

		if (count($this->parameters->addFolders))
		{
			foreach ($this->parameters->addFolders as $virtual => $real)
			{
				$real = rtrim($this->repositoryRoot, '/') . '/' . $real;
				$this->scanLanguages($real);
			}
		}

		foreach (new DirectoryIterator($this->repositoryRoot) as $subfolder)
		{
			if (!$subfolder->isDir() || $subfolder->isDot())
			{
				continue;
			}

			$folderName = $subfolder->getFilename();

			if (in_array($folderName, $this->parameters->ignoreFolders))
			{
				continue;
			}

			$this->scanLanguages($subfolder->getPathname());
		}
	}

	/**
	 * Scan the languages in this repository
	 *
	 * @return  void
	 */
	protected function scanLanguages($folder = null)
	{
		if (empty($folder))
		{
			$folder = $this->repositoryRoot;
		}

		foreach (new DirectoryIterator($folder) as $subfolder)
		{
			if (!$subfolder->isDir() || $subfolder->isDot())
			{
				continue;
			}

			$folderName = $subfolder->getFilename();

			if (in_array($folderName, $this->parameters->ignoreFolders))
			{
				continue;
			}

			$areaDir = $folder . '/' . $folderName;

			foreach (new DirectoryIterator($areaDir) as $oFile)
			{
				if ($oFile->isDir() || $oFile->isDot())
				{
					continue;
				}

				$langCode = $oFile->getFilename();
				$langCode = substr($langCode, 0, strpos($langCode, '.'));

				if (!isset($this->siteLangFiles[$langCode]))
				{
					$this->siteLangFiles[$langCode] = [];
				}

				$this->siteLangFiles[$langCode][] = $oFile->getRealPath();
			}
		}
	}

	/**
	 * Builds the language ZIP package for a specific language
	 *
	 * @param   string $code            Language code, e.g. en-GB
	 * @param   string $targetDirectory The target directory where the ZIP file will be built
	 *
	 * @return  string  The full path to the ZIP file created by this script
	 */
	protected function buildPackageFor(string $code, string $targetDirectory): string
	{
		$langCodes = $this->getLanguageCodes();

		if (!in_array($code, $langCodes))
		{
			throw new \OutOfBoundsException("Can not build XML manifest for $code because it does not exist in the repository.");
		}

		$baseFileName = $this->parameters->packageName . '-' . $code . '.zip';
		$zipPath      = rtrim($targetDirectory, '/\\') . '/' . $baseFileName;

		if (is_file($zipPath))
		{
			@unlink($zipPath);
		}

		$zip          = new ZipArchive();
		$createStatus = $zip->open($zipPath, ZipArchive::CREATE);

		if ($createStatus !== true)
		{
			throw new \RuntimeException("Can not create ZIP file $zipPath");
		}

		// Add the files (nominally, frontend)
		foreach ($this->siteLangFiles[$code] as $filePath)
		{
			$zip->addFile($filePath, $this->getPackageName($filePath, $code));
		}

		// Add the ANGIE files, if present
		if (!empty($this->angieFiles))
		{
			foreach ($this->angieFiles as $angieFile)
			{
				$angieFileName = rtrim($this->parameters->angieVirtualDir, '/') . '/' . basename($angieFile);

				$zip->addFile($angieFile, $angieFileName);
			}
		}

		$zip->close();

		return $zipPath;
	}

	protected function getPackageName(string $filePath, string $langCode)
	{
		// Get the basename of the file we're adding to the archive
		$basename = basename($filePath);
		$prefix = ($this->parameters->filePathPrefix == '') ? '' : $this->parameters->filePathPrefix . '/';

		// If there are no add folders we do a Kickstart-style package: flat INI files, without directories.
		if (!count($this->parameters->addFolders))
		{
			return $prefix . $basename;
		}

		// Otherwise we have a Solo-style package: dirName/languageCode/baseName e.g. akeeba/en-GB/en-GB.com_akeeba.ini

		// Let's see if the file belongs to one of the add folders (it's stored under a virtual folder)
		foreach ($this->parameters->addFolders as $as => $folder)
		{
			$realFolder = realpath(rtrim($this->repositoryRoot, '/') . '/' . $folder);
			$realCheck  = realpath(dirname(dirname($filePath)));

			if ($realFolder == $realCheck)
			{
				return $prefix . trim($as, '/') . '/' . $langCode . '/'.  $basename;
			}
		}

		/**
		 * Hm, it's a real folder. Instead of a virtual folder we need to use the real directory the file's in.
		 * The file is something like
		 * /foo/bar/baz/solo/akeebabackup/en-GB/en-GB.com_akeebabackup.ini
		 * and we need to store it as
		 * akeebabackup/en-GB/en-GB.com_akeebabackup.ini
		 */
		$outerDir = basename(dirname(dirname($filePath)));

		return $prefix . $outerDir . '/' . $langCode . '/' . $basename;
	}
}
