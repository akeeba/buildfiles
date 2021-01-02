<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\BuildLang;


use Akeeba\Engine\Postproc\Connector\S3v4\Acl;
use Akeeba\Engine\Postproc\Connector\S3v4\Input;
use Akeeba\LinkLibrary\Scanner\Component;
use Akeeba\LinkLibrary\Scanner\Library;
use Akeeba\LinkLibrary\Scanner\Module;
use Akeeba\LinkLibrary\Scanner\Plugin;
use Akeeba\LinkLibrary\Scanner\Template;
use Akeeba\LinkLibrary\ScannerInterface;
use RuntimeException;
use ZipArchive;

class Builder
{
	/**
	 * Absolute path to the root of the repository
	 *
	 * @var  string
	 */
	protected $repositoryRoot = '';

	/**
	 * Configuration parameters
	 *
	 * @var  Parameters
	 */
	protected $parameters;

	/**
	 * Administrator language files
	 *
	 * @var  array
	 */
	protected $adminLangFiles = [];

	/**
	 * Site language files
	 *
	 * @var  array
	 */
	protected $siteLangFiles = [];

	/**
	 * Pre-build ANGIE installer files
	 *
	 * @var  array
	 */
	protected $angieFiles = [];

	/**
	 * Translation progress per language code. Used as a cache for getTranslationProgress.
	 *
	 * @var  array
	 */
	private $translationProgress = [];

	/**
	 * Builder constructor.
	 *
	 * @param   string     $repositoryRoot Absolute path to the repository root
	 * @param   Parameters $parameters     Configuration parameters
	 */
	public function __construct(string $repositoryRoot, Parameters $parameters)
	{
		$this->repositoryRoot = $repositoryRoot;
		$this->parameters     = $parameters;

		$this->scanLanguages();
		$this->scanANGIELanguages();
	}

	/**
	 * Get the unique language codes for both frontend and backend
	 *
	 * @return  array
	 */
	public function getLanguageCodes(): array
	{
		$siteLangCodes  = array_keys($this->siteLangFiles);
		$adminLangCodes = array_keys($this->adminLangFiles);
		$codes          = array_merge($siteLangCodes, $adminLangCodes);

		return array_unique($codes);
	}

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
		asort($langCodes);

		foreach ($langCodes as $code)
		{
			if ($this->parameters->minPercent >= 0.01)
			{
				if (!isset($completion[$code]))
				{
					continue;
				}

				if ($completion[$code] < $this->parameters->minPercent)
				{
					continue;
				}
			}

			if (!$this->parameters->quiet)
			{
				echo "Packaging $code";
			}

			try
			{
				// Add the successfully built packages to a list
				$tempPath        = $this->buildPackageFor($code, $tempDirectory);
				$packages[$code] = basename($tempPath);
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

			// Upload the temporary package file to S3 and delete it afterwards
			if ($this->parameters->uploadToS3)
			{
				$uploadPath = trim($path, '/') . '/' . trim($softwareSlug, '/') . '/' . $packages[$code];

				if (!$this->parameters->quiet)
				{
					echo " and uploading to s3://$bucket/$uploadPath\n";
				}

				$inputDefinition = Input::createFromFile($tempPath);
				$this->parameters->s3->putObject($inputDefinition, $bucket, $uploadPath, Acl::ACL_PUBLIC_READ);
				unset($inputDefinition);

				if (!$this->parameters->keepOutput)
				{
					@unlink($tempPath);
				}
			}
			elseif (!$this->parameters->quiet)
			{
				echo "\n";
			}
		}

		// Build and upload the HTML index file
		if (!$this->parameters->quiet)
		{
			echo "Generating index.html";
		}

		$tempHtml = $this->buildHTML($packages);

		if ($this->parameters->keepOutput)
		{
			file_put_contents($this->parameters->outputDirectory . '/index.html', $tempHtml);
		}

		if ($this->parameters->uploadToS3)
		{
			$uploadPath = trim($path, '/') . '/' . trim($softwareSlug, '/') . '/index.html';

			if (!$this->parameters->quiet)
			{
				echo " and uploading to s3://$bucket/$uploadPath\n";
			}

			$inputDefinition = Input::createFromData($tempHtml);
			$this->parameters->s3->putObject($inputDefinition, $bucket, $uploadPath, Acl::ACL_PUBLIC_READ, [
				'Content-Type' => 'text/html'
			]);
			unset($inputDefinition);

			if ($this->parameters->keepOutput)
			{
				file_put_contents($tempDirectory . '/index.html', $tempHtml);
			}
		}
		elseif (!$this->parameters->quiet)
		{
			echo "\n";
		}
	}

	protected function buildHTML(array $packages): string
	{
		$langTable  = '';
		$completion = [];

		if ($this->parameters->minPercent >= 0.01)
		{
			$completion = $this->getTranslationProgress();
		}

		$replacements = [
			// e.g. "Example Software"
			'[SOFTWARE]'       => $this->parameters->softwareName,
			// e.g. "component", "plugin", "software"...
			'[SOFTWARETYPE]'   => $this->parameters->softwareType,
			// e.g. "example_soft"
			'[PACKAGENAME]'    => $this->parameters->packageName,
			// e.g. "http://www.example.com/downloads/example_soft"
			'[PACKAGENAMEURL]' => $this->parameters->packageNameURL,
			// e.g. "https://translate.example.com"
			'[WEBLATEURL]'     => $this->parameters->weblateURL,
			// e.g. "example_soft"
			'[WEBLATEPROJECT]' => $this->parameters->weblateProject,
			// e.g. "Acme Corp"
			'[AUTHORNAME]'     => $this->parameters->authorName,
			// e.g. "https://www.example.net/acme_corp"
			'[AUTHORURL]'      => $this->parameters->authorUrl,
			// e.g. "GPLv3"
			'[LICENSE]'        => $this->parameters->license,
			// Auto-generated, e.g. "2017-01-02 03:04:05 GMT"
			'[DATE]'           => gmdate('Y-m-d H:i:s T'),
			// Auto-generated, e.g. "2017"
			'[YEAR]'           => gmdate('Y'),
		];

		$templateTableRow = file_get_contents(rtrim($this->repositoryRoot, '/\\') . '/' . $this->parameters->prototypeTable);

		foreach ($packages as $code => $baseName)
		{
			try
			{
				$extraReplacements = $this->getExtraReplacementsForLangTable($code, $baseName, $completion);
				$allReplacements   = array_merge($replacements, $extraReplacements);
				$langTable         .= str_replace(array_keys($allReplacements), array_values($allReplacements), $templateTableRow);
			}
			catch (\OutOfBoundsException $e)
			{
				continue;
			}
		}

		$replacements['[LANGTABLE]'] = $langTable;

		$inFile = rtrim($this->repositoryRoot, '/\\') . '/' . $this->parameters->prototypeHTML;
		$html   = file_get_contents($inFile);

		if ($html !== false)
		{
			$html = str_replace(array_keys($replacements), array_values($replacements), $html);
		}

		return $html;
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

		// Add a manifest
		$manifestName = basename($baseFileName, '.zip') . '.xml';
		$manifest     = $this->getManifestXMLFor($code);
		$zip->addFromString($manifestName, $manifest);

		// Add backend files
		if (isset($this->adminLangFiles[$code]) && count($this->adminLangFiles[$code]))
		{
			foreach ($this->adminLangFiles[$code] as $filePath)
			{
				$zip->addFile($filePath, 'backend/' . basename($filePath));
			}
		}

		// Add frontend files
		if (isset($this->siteLangFiles[$code]) && count($this->siteLangFiles[$code]))
		{
			foreach ($this->siteLangFiles[$code] as $filePath)
			{
				$zip->addFile($filePath, 'frontend/' . basename($filePath));
			}
		}

		// Add ANGIE files
		if (count($this->angieFiles))
		{
			foreach ($this->angieFiles as $filePath)
			{
				$zip->addFile($filePath, $this->parameters->angieVirtualDir . '/' . basename($filePath));
			}
		}

		$zip->close();

		return $zipPath;
	}

	/**
	 * Returns the XML manifest file for a language
	 *
	 * @param   string $code
	 *
	 * @return  string
	 */
	protected function getManifestXMLFor(string $code): string
	{
		$langCodes = $this->getLanguageCodes();
		$langInfo  = new LanguageInfo($code);

		if (!in_array($code, $langCodes))
		{
			throw new \OutOfBoundsException("Can not build XML manifest for $code because it does not exist in the repository.");
		}

		$xmlSource         = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<extension type="file" version="1.6" method="upgrade" client="site">
    <name />
    <author />
    <authorurl />
	<copyright />
	<license />
    <version />
    <creationDate />
    <description />
	<fileset />
</extension>
XML;
		$xml               = new \SimpleXMLElement($xmlSource, LIBXML_COMPACT | LIBXML_NONET);
		$xml->name         = $this->parameters->packageName . ' - ' . $langInfo->getName();
		$xml->author       = $this->parameters->authorName;
		$xml->authorurl    = $this->parameters->authorUrl;
		$xml->copyright    = sprintf('Copyright (C) 2010-%s %s. All rights reserved.', gmdate('Y'), $this->parameters->authorName);
		$xml->license      = $this->parameters->license;
		$xml->version      = $this->parameters->version;
		$xml->creationDate = gmdate('d M Y');;
		$xml->description = sprintf('%s language files for %s', $langInfo->getName(), $this->parameters->softwareName);

		/** @var \SimpleXMLElement $fileset */
		$fileset = $xml->fileset;

		// Add administrator languages to the manifest
		if (isset($this->adminLangFiles[$code]) && count($this->adminLangFiles[$code]))
		{
			$adminContainer = $fileset->addChild('files');
			$adminContainer->addAttribute('folder', 'backend');
			$adminContainer->addAttribute('target', sprintf('administrator/language/%s', $langInfo->getCode()));

			foreach ($this->adminLangFiles[$code] as $filePath)
			{
				$adminContainer->addChild('filename', basename($filePath));
			}
		}

		// Add site languages to the manifest
		if (isset($this->siteLangFiles[$code]) && count($this->siteLangFiles[$code]))
		{
			$adminContainer = $fileset->addChild('files');
			$adminContainer->addAttribute('folder', 'frontend');
			$adminContainer->addAttribute('target', sprintf('language/%s', $langInfo->getCode()));

			foreach ($this->siteLangFiles[$code] as $filePath)
			{
				$adminContainer->addChild('filename', basename($filePath));
			}
		}

		if (count($this->angieFiles))
		{
			/** @var \SimpleXMLElement $fileset */
			$fileset        = $xml->fileset;
			$adminContainer = $fileset->addChild('files');
			$adminContainer->addAttribute('folder', 'angie');
			$adminContainer->addAttribute('target', 'administrator/components/com_akeeba/Master/Installers');

			foreach ($this->angieFiles as $filePath)
			{
				$adminContainer->addChild('filename', basename($filePath));
			}
		}

		return $xml->asXML();
	}

	/**
	 * Scan the languages in this repository
	 *
	 * @return  void
	 */
	protected function scanLanguages()
	{
		// Discover all the extensions in the repository
		/** @var ScannerInterface[] $extensions */
		$extensions = [];
		$extensions = array_merge($extensions, Component::detect($this->repositoryRoot));
		$extensions = array_merge($extensions, Library::detect($this->repositoryRoot));
		$extensions = array_merge($extensions, Module::detect($this->repositoryRoot));
		$extensions = array_merge($extensions, Plugin::detect($this->repositoryRoot));
		$extensions = array_merge($extensions, Template::detect($this->repositoryRoot));

		$this->siteLangFiles  = [];
		$this->adminLangFiles = [];

		// Scan the language files for each extension and add them to the global list
		foreach ($extensions as $extension)
		{
			$scanResults = $extension->getScanResults();

			if (is_array($scanResults->siteLangFiles) && !empty($scanResults->siteLangFiles))
			{
				$this->siteLangFiles = array_merge_recursive($this->siteLangFiles, $scanResults->siteLangFiles);
			}

			if (is_array($scanResults->adminLangFiles) && !empty($scanResults->adminLangFiles))
			{
				$this->adminLangFiles = array_merge_recursive($this->adminLangFiles, $scanResults->adminLangFiles);
			}
		}
	}

	/**
	 * Return the translation progress by language, pulling this information from Weblate
	 *
	 * @return  array
	 */
	protected function getTranslationProgress(): array
	{
		if (!empty($this->translationProgress))
		{
			return $this->translationProgress;
		}

		$baseUrl     = $this->parameters->weblateURL;
		$project     = $this->parameters->weblateProject;
		$url         = "$baseUrl/api/projects/$project/statistics/?format=json";
		$ch          = curl_init($url);
		$curlOptions = [
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_VERBOSE        => true,
			CURLOPT_HEADER         => false,
			CURLINFO_HEADER_OUT    => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CAINFO         => __DIR__ . '/../../phing/tasks/library/cacert.pem',
			CURLOPT_HTTPHEADER     => [
				'Authorization: Token ' . $this->parameters->weblateApiKey,
			],
		];

		@curl_setopt_array($ch, $curlOptions);

		$response     = curl_exec($ch);
		$errNo        = curl_errno($ch);
		$error        = curl_error($ch);
		$lastHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($errNo)
		{
			throw new \RuntimeException("cURL Error $errNo: $error", 500);
		}

		if ($lastHttpCode != 200)
		{
			throw new \RuntimeException("Unexpected HTTP status $lastHttpCode", $lastHttpCode);
		}

		$rawResult = json_decode($response, true);

		if (is_null($rawResult))
		{
			throw new \RuntimeException("Invalid return from Weblate (cannot parse as JSON)", 500);
		}

		$this->translationProgress = [];

		foreach ($rawResult as $entry)
		{
			$code                             = $entry['code'];
			$translationPercent               = $entry['translated_percent'];
			$this->translationProgress[$code] = $translationPercent;
		}

		return $this->translationProgress;
	}

	/**
	 * @param   string  $code        The language code build built
	 * @param   string  $baseName    The basename of the built package
	 * @param   array   $completion  Completion % per language
	 *
	 * @return  array
	 */
	protected function getExtraReplacementsForLangTable(string $code, string $baseName, array $completion): array
	{
		$info = new LanguageInfo($code);

		$url = 'https://' . $this->parameters->s3CDNHostname . '/' .
			$this->parameters->s3Path . '/' .
			$this->parameters->packageNameURL . '/' .
			$baseName;

		$percent           = $completion[$code] ?? 0;
		$bsType            = ($percent < 75) ? 'danger' : (($percent < 90) ? 'warning' : 'success');
		$extraReplacements = [
			// Package download URL
			'[PACKAGEURL]'          => $url,
			// Country of the language
			'[LANGCOUNTRY]'         => $info->getCountry(),
			'[LANGNAME]'            => $info->getName(),
			'[LANGCODE]'            => $info->getCode(),
			'[PERCENT]'             => $percent,
			'[BS_PROGRESSBAR_TYPE]' => $bsType,
		];

		return $extraReplacements;
	}

	/**
	 * Find pre-build ANGIE language packs (JPA format)
	 *
	 * @return  void
	 */
	private function scanANGIELanguages()
	{
		$this->angieFiles = [];
		$angieGlob        = $this->parameters->angieGlob;

		if (empty($angieGlob))
		{
			return;
		}

		$glob  = rtrim($this->parameters->outputDirectory, '\\/') . '/' . $angieGlob;
		$files = glob($glob, GLOB_BRACE);

		$this->angieFiles = array_map('realpath', $files);
	}

}
