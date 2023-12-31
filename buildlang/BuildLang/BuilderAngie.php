<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\BuildLang;
use Akeeba\Engine\Postproc\Connector\S3v4\Acl;
use Akeeba\Engine\Postproc\Connector\S3v4\Input;
use JPAMaker;
use RuntimeException;

require_once __DIR__ . '/../../phing/tasks/library/jpa.php';

/**
 * Language Builder for ANGIE
 *
 * ANGIE is a special case. It does not create a package per language but a package per installer. This means that you
 * have one package for the generic ANGIE, another one for the ANGIE for WordPress platform package, another one for
 * the ANGIE for Drupal platform and so on.
 */
class BuilderAngie extends Builder
{
	public function buildAll()
	{
		$langCodes     = $this->getLanguageCodes();
		$packages      = [];
		$tempDirectory = $this->parameters->outputDirectory;
		$bucket        = $this->parameters->s3Bucket;
		$path          = $this->parameters->s3Path;
		$softwareSlug  = $this->parameters->packageNameURL;
		$completion    = [];

		if ($this->parameters->minPercent >= 0.01)
		{
			$completion = $this->getTranslationProgress();
		}

		// Build all packages
		foreach ($this->siteLangFiles as $area => $files)
		{
			// Filter out the files which will be included in the package
			$includeFiles = array_filter($files, function ($x) use ($completion) {
				if (empty($completion))
				{
					return true;
				}

				$code = basename($x, '.ini');

				if (!in_array($code, $completion))
				{
					return false;
				}

				return $completion[$code] >= $this->parameters->minPercent;
			});

			if (empty($includeFiles))
			{
				continue;
			}

			if (!$this->parameters->quiet)
			{
				echo "Packaging $area";
			}

			try
			{
				// Add the successfully built packages to a list
				$tempPath        = $this->buildANGIEPackageFor($area, $includeFiles, $tempDirectory);
				$packages[$area] = basename($tempPath);
			}
			catch (RuntimeException $e)
			{
				if (!$this->parameters->quiet)
				{
					$msg = $e->getMessage();

					echo " has FAILED ($msg)\n";
				}

				// Ignore packages that failed to build
				continue;
			}

			echo "\n";

		}

		// Important: The JPA files are NOT uploaded to S3. We'll include them in the Akeeba Backup / Solo language packs.
	}

	/**
	 * Builds the ANGIE language package for the specified ANGIE area ("angie" is the main language, anything else is
	 * an ANGIE platform).
	 *
	 * @param   string  $area             Which area I am building for ("angie" is the main language, anything else is
	 *                                    an ANGIE platform).
	 * @param   array   $includeFiles     Which files should I include in the package (absolute paths)
	 * @param   string  $targetDirectory  The target directory of the JPA fil
	 *
	 * @return  string  The absolute file path of the JPA package I created
	 */
	protected function buildANGIEPackageFor(string $area, array $includeFiles, string $targetDirectory): string
	{
		$jpaPath = rtrim($targetDirectory, '/\\') . '/language-' . $area . '.jpa';

		$jpa = new JPAMaker();
		$jpa->create($jpaPath);

		foreach ($includeFiles as $file)
		{
			$pathInJPA = 'installation/' . (($area == 'angie') ? 'angie' : 'platform')
				. '/language/' . basename($file);
			$jpa->addFile($file, $pathInJPA);
			$jpa->finalize();
		}

		return $jpaPath;
	}

	/**
	 * Scan the languages in this repository
	 *
	 * @return  void
	 */
	protected function scanLanguages()
	{
		$this->siteLangFiles = [];

		foreach (new \DirectoryIterator($this->repositoryRoot) as $oFolder)
		{
			if (!$oFolder->isDir() || $oFolder->isDot())
			{
				continue;
			}

			$package = $oFolder->getFilename();

			$this->siteLangFiles[$package] = [];

			foreach (new \DirectoryIterator($oFolder->getPathname()) as $oFile)
			{
				if (!$oFile->isFile() || ($oFile->getExtension() != 'ini'))
				{
					continue;
				}

				$this->siteLangFiles[$package][] = $oFile->getPathname();
			}
		}

		ksort($this->siteLangFiles);
		$baseLang = $this->siteLangFiles['angie'];
		unset($this->siteLangFiles['angie']);
		$this->siteLangFiles = array_merge(['angie' => $baseLang], $this->siteLangFiles);
	}

	/**
	 * Get the unique language codes for both frontend and backend
	 *
	 * @return  array
	 */
	public function getLanguageCodes(): array
	{
		$codes = [];

		foreach ($this->siteLangFiles as $area => $files)
		{
			foreach ($files as $file)
			{
				$codes[] = basename($file, '.ini');
			}
		}

		return array_unique($codes);
	}

	/**
	 * @param   string  $code        The area being built, e.g. "angie", "wordpress" etc
	 * @param   string  $baseName    The basename of the built package
	 * @param   array   $completion  Completion % per language. IGNORED.
	 *
	 * @return  array
	 */
	protected function getExtraReplacementsForLangTable(string $code, string $baseName, array $completion): array
	{
		$url               = 'https://' . $this->parameters->s3CDNHostname . '/' .
			$this->parameters->s3Path . '/' .
			$this->parameters->packageNameURL . '/' .
			$baseName;
		$name              = $this->parameters->angieMap[$code] ?? ('ANGIE for ' . ucfirst($code));
		$extraReplacements = [
			// Package download URL
			'[PACKAGEURL]' => $url,
			'[AREANAME]'   => $name,
		];

		return $extraReplacements;
	}

}
