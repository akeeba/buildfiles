<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\BuildLang;

use Akeeba\Engine\Postproc\Connector\S3v4\Configuration;
use Akeeba\Engine\Postproc\Connector\S3v4\Connector;

/**
 * Read only configuration parameters repository
 *
 * @property-read  string    $packageName      The base name of the package; must correspond to the Weblate project slug
 * @property-read  string    $packageNameURL   The URL to the software information page
 * @property-read  string    $softwareName     The name of the software
 * @property-read  string    $softwareType     The type of software: 'package' (default), 'library', 'standalone', 'none'
 * @property-read  string    $authorName       Copyright holder
 * @property-read  string    $authorUrl        URL to the copyright holder's page
 * @property-read  string    $license          Translation license, default "GNU GPL v3 or later"
 * @property-read  string    $weblateURL       The base URL of the Weblate installation
 * @property-read  string    $weblateProject   The name of the weblate project. Default: the packageName
 * @property-read  string    $weblateApiKey    The API key to access your Weblate installation, used to pull translation stats
 * @property-read  string    $minPercent       The minimum translation percentage required to build / publish a package
 * @property-read  string    $prototypeHTML    Relative path to the template HTML page
 * @property-read  string    $prototypeTable   Relative path to the table's template HTML
 * @property-read  string    $s3Access         Amazon S3 Access Key
 * @property-read  string    $s3Private        Amazon S3 Secret Key
 * @property-read  string    $s3Signature      Amazon S3 signature method, 'v2' (default) or 'v4'
 * @property-read  string    $s3Bucket         Amazon S3 bucket where the files are uploaded
 * @property-read  string    $s3Region         Amazon S3 region, used when s3Signature == 'v4'
 * @property-read  string    $s3Path           Path into Amazon S3 / hostname where the packages are stored
 * @property-read  string    $s3CDNHostname    Hostname that holds the packages
 * @property-read  string    $version          Version of the generated packages
 * @property-read  string    $outputDirectory  Where the generated packages are stored. Default: system temp directory
 * @property-read  string[]  $ignoreFolders    Which folders I should ignore when building a "standalone" translation package
 * @property-read  string[]  $addFolders       Which folders I should scan, on top of the repo root, when building a
 *                                             "standalone" translation package. Format
 *                                             [ 'folderInPackage' => '/path/to/source/folder', ... ]
 * @property-read  string    $angieGlob        Glob (relative to temp folder) used to look for ANGIE files
 * @property-read  string    $angieVirtualDir  Virtual folder in ZIP archive where ANGIE files live
 * @property-read  string    $filePathPrefix   Prefix in the ZIP file for the standalone package files
 * @property-read  string[]  $angieMap         Map of ANGIE installers to human readable strings
 * @property-read  bool      $keepOutput       Should I keep the packages after generating them? Default: false (delete)
 * @property-read  bool      $uploadToS3       Should I upload the packages to S3? Default: true
 * @property-read  bool      $quiet            Suppress output?
 * @property-read  Connector $s3               Amazon S3 connector
 */
class Parameters
{
	private $packageName;

	private $softwareName;

	private $authorName = 'Akeeba Ltd';

	private $authorUrl = 'https://www.akeeba.com';

	private $license = 'GNU GPL v3 or later';

	private $packageNameURL;

	private $s3Access;

	private $s3Private;

	private $s3Signature = 'v2';

	private $s3Bucket;

	private $s3Region = '';

	private $s3Path;

	private $s3CDNHostname = 'cdn.akeeba.com';

	private $version;

	private $prototypeHTML = 'translations/_pages/index.html';

	private $prototypeTable = 'translations/_pages/table.html';

	private $softwareType = 'package';

	private $weblateURL = '';

	private $weblateProject = '';

	private $weblateApiKey = '';

	private $minPercent = '50';

	private $outputDirectory = null;

	private $keepOutput = false;

	private $uploadToS3 = true;

	private $quiet = false;

	private $ignoreFolders = [];

	private $addFolders = [];

	private $angieMap = [];

	private $angieMapSource = "";

	private $filePathPrefix = '';

	/**
	 * A connector to Amazon S3
	 *
	 * @var  Connector
	 */
	private $_s3Connector;

	private $angieGlob = '';

	private $angieVirtualDir = '';

	/**
	 * Parameters constructor.
	 *
	 * @param   string $iniFile         The INI file(s) to load properties from
	 * @param   array  $extraProperties Any additional properties to pass
	 */
	public function __construct(string $iniFile, array $extraProperties = [])
	{
		$properties = $this->parseFile($iniFile);
		$properties = array_merge($properties, $extraProperties);

		$this->initializeFromArray($properties);
	}

	/**
	 * Initialize this object from an array of values, presumably one read from the provided INI file
	 *
	 * @param   array $values
	 *
	 * @return  void
	 */
	private function initializeFromArray(array $values)
	{
		$map = [
			'langbuilder.packagename'               => 'packageName',
			'langbuilder.software'                  => 'softwareName',
			'langbuilder.softwaretype'              => 'softwareType',
			'langbuilder.authorname'                => 'authorName',
			'langbuilder.authorurl'                 => 'authorUrl',
			'langbuilder.license'                   => 'license',
			'langbuilder.protohtml'                 => 'prototypeHTML',
			'langbuilder.prototable'                => 'prototypeTable',
			'langbuilder.baseURL'                   => 'weblateURL',
			'langbuilder.project'                   => 'weblateProject',
			'langbuilder.apiKey'                    => 'weblateApiKey',
			'langbuilder.minPercent'                => 'minPercent',
			'langbuilder.standalone.ignore_folders' => 'ignoreFolders',
			'langbuilder.standalone.add_folders'    => 'addFolders',
			'langbuilder.angie.map'                 => 'angieMapSource',
			'langbuilder.backup.angieglob'          => 'angieGlob',
			'langbuilder.backup.angiedir'           => 'angieVirtualDir',
			'langbuilder.standalone.prefix'         => 'filePathPrefix',
			's3.access'                             => 's3Access',
			's3.private'                            => 's3Private',
			's3.signature'                          => 's3Signature',
			's3.bucket'                             => 's3Bucket',
			's3.region'                             => 's3Region',
			's3.path'                               => 's3Path',
			's3.cdnhostname'                        => 's3CDNHostname',
			'extra.version'                         => 'version',
			'extra.outputDirectory'                 => 'outputDirectory',
			'extra.keepOutput'                      => 'keepOutput',
			'extra.uploadToS3'                      => 'uploadToS3',
			'extra.quiet'                           => 'quiet',
		];

		foreach ($map as $arrayKey => $propertyName)
		{
			if (!isset($values[$arrayKey]))
			{
				continue;
			}

			if (!property_exists($this, $propertyName))
			{
				continue;
			}

			$this->{$propertyName} = $values[$arrayKey];
		}

		// Create an URL-friendly version of the package name
		$this->packageNameURL = str_replace(' ', '-', strtolower(trim($this->packageName)));

		// Create a default version number if none is specified
		if (empty($this->version))
		{
			$this->version = gmdate('Ymd.His');
		}

		// Default output directory: a subdirectory of the system temp directory
		if (empty($this->outputDirectory))
		{
			$this->outputDirectory = sys_get_temp_dir() . '/akeeba-language-builder/' . $this->packageName;

			if (!is_dir($this->outputDirectory))
			{
				mkdir($this->outputDirectory, 0755, true);
			}
		}

		// If we're not uploading to S3 we have to keep the output files
		if (!$this->uploadToS3)
		{
			$this->keepOutput = true;
		}

		// Set a default Weblate project name if necessary
		if (empty($this->weblateProject))
		{
			$this->weblateProject = $this->packageName;
		}

		// Normalize ignoreFolders
		if (!empty($this->ignoreFolders))
		{
			$this->ignoreFolders = array_map('trim', explode(',', trim($this->ignoreFolders)));
		}

		// Normalize addFolders
		if (!empty($this->addFolders))
		{
			$temp = array_map('trim', explode(',', trim($this->addFolders)));
			$this->addFolders = [];

			foreach ($temp as $value)
			{
				list($source, $as) = explode(';', $value);
				$source                = trim($source);
				$as                    = trim($as);
				$this->addFolders[$as] = $source;
			}
		}

		// Convert ANGIE map from JSON to array
		if (!empty($this->angieMapSource))
		{
			$this->angieMap = json_decode($this->angieMapSource, true) ?? [];
		}
	}

	/**
	 * Parse a properties INI file. You can give it multiple files by separating their names with a semicolon.
	 *
	 * @param   string $iniFile The INI file(s) to read
	 *
	 * @return  array  The properties read from the file(s)
	 */
	private function parseFile(string $iniFile): array
	{
		if (strpos($iniFile, ';') === false)
		{
			$properties = parse_ini_file($iniFile, true, INI_SCANNER_RAW);

			return (!is_array($properties) || empty($properties)) ? [] : $properties;
		}

		$fileList   = explode(';', $iniFile);
		$properties = [];

		foreach ($fileList as $iniFile)
		{
			if (!file_exists($iniFile))
			{
				continue;
			}

			$newProps   = $this->parseFile($iniFile);
			$properties = array_merge($properties, $newProps);
		}

		return $properties;
	}

	/**
	 * Magic getter. Allows read-only access to the private properties.
	 *
	 * @param   string $name
	 *
	 * @return  mixed
	 */
	public function __get(string $name)
	{
		if (property_exists($this, $name) && (substr($name, 0, 1) != '_'))
		{
			return $this->{$name};
		}

		if ($name === 's3')
		{
			return $this->getS3();
		}

		throw new \InvalidArgumentException("Property $name does not exist");
	}

	/**
	 * Get the cached S3 connector. Creates one if it doesn't already exist.
	 *
	 * @return  Connector
	 */
	public function getS3(): Connector
	{
		if (is_null($this->_s3Connector))
		{
			$config = new Configuration($this->s3Access, $this->s3Private, $this->s3Signature, $this->s3Region);

			$this->_s3Connector = new Connector($config);
		}

		return $this->_s3Connector;
	}
}
