<?php
require_once 'phing/Task.php';
require_once('langtask/S3.php');
require_once 'pclzip.php';

/**
 * Class LangTask
 *
 * Build the language files and uploads them to the CDN server.
 *
 * Example:
 * <code>
 *     <lang version="${ext.version}" />
 * </code>
 */
class LangTask extends Task
{
	/**
	 * The manifest version.
	 *
	 * @var 	string
	 */
	private $version = null;

	/**
	 * Sets the manifest version.
	 */
	function setVersion($version)
	{
		$this->version = $version;
	}

	/**
	 * Returns the manifest version.
	 */
	function getVersion()
	{
		return $this->version;
	}

	/**
	 * Scans the repository directories for language files.
	 *
	 * @param 	string	$root	The root path of the translations directory.
	 *
	 * @return 	array	The languages.
	 */
	private function scan($root)
	{
		$ret = array();

		// Scan component frontend languages
		$this->mergeLangRet($ret, $this->scanLangDir($root.'/component/frontend'), 'frontend');

		// Scan component backend languages
		$this->mergeLangRet($ret, $this->scanLangDir($root.'/component/backend'), 'backend');

		// Scan modules, admin
		try {
			foreach(new DirectoryIterator($root.'/modules/admin') as $mname) {
				if($mname->isDot()) continue;
				if(!$mname->isDir()) continue;
				$module = $mname->getFilename();
				$this->mergeLangRet($ret, $this->scanLangDir($root.'/modules/admin/'.$module), 'backend');
			}
		} catch (Exception $exc) {
			//echo $exc->getTraceAsString();
		}

		// Scan modules, site
		try {
			foreach(new DirectoryIterator($root.'/modules/site') as $mname) {
				if($mname->isDot()) continue;
				if(!$mname->isDir()) continue;
				$module = $mname->getFilename();
				$this->mergeLangRet($ret, $this->scanLangDir($root.'/modules/site/'.$module), 'backend');
			}
		} catch (Exception $exc) {
			//echo $exc->getTraceAsString();
		}

		// Scan plugins
		try {
			foreach(new DirectoryIterator($root.'/plugins') as $fldname) {
				if($fldname->isDot()) continue;
				if(!$fldname->isDir()) continue;
				$path = $root.'/plugins/'.$fldname->getFilename();
				// Scan this folder for plugins
				try {
					foreach(new DirectoryIterator($path) as $pname) {
						if($pname->isDot()) continue;
						if(!$pname->isDir()) continue;
						$plugin = $pname->getFilename();
						$this->mergeLangRet($ret, $this->scanLangDir($path.'/'.$plugin), 'backend');
					}
				} catch (Exception $exc) {
					//echo $exc->getTraceAsString();
				}
			}
		} catch (Exception $exc) {
			//echo $exc->getTraceAsString();
		}

		return $ret;
	}

	/**
	 * @param array		$ret		The merged languages.
	 * @param string	$temp		The language path to merge.
	 * @param string 	$area		The area, either frontend or backend.
	 */
	private function mergeLangRet(&$ret, $temp, $area = 'frontend')
	{
		foreach($temp as $lang => $files) {
			$existing = array();
			if(array_key_exists($lang, $ret)) {
				if(array_key_exists($area, $ret[$lang])) {
					$existing = $ret[$lang][$area];
				}
			}
			$ret[$lang][$area] = array_merge($existing, $files);
		}
	}

	/**
	 * Scans a directory for language files.
	 *
	 * @param 	string	$path	The path of the directory to scan.
	 *
	 * @return 	array
	 */
	private function scanLangDir($path)
	{
		$langs = array();
		try {
			foreach(new DirectoryIterator($path) as $file) {
				if($file->isDot()) continue;
				if(!$file->isDir()) continue;
				$langs[] = $file->getFileName();
			}
		} catch (Exception $exc) {
			//echo $exc->getTraceAsString();
		}

		$ret = array();
		foreach($langs as $lang) {
			try {
				foreach(new DirectoryIterator($path.'/'.$lang) as $file) {
					if(!$file->isFile()) continue;
					$fname = $file->getFileName();
					if(substr($fname,-4) != '.ini') continue;
					$ret[$lang][] = $path.'/'.$lang.'/'.$fname;
				}
			} catch (Exception $exc) {
				//echo $exc->getTraceAsString();
			}
		}

		return $ret;
	}

	/**
	 * Main entry point for task.
	 *
	 * @return 	bool
	 */
	public function main()
	{
		// Load the properties
		$props = parse_ini_file( 'build.properties');

		// Get parameters from build.properties
		$author 		= $props['langbuilder.author'];
		$authorUrl 		= $props['langbuilder.authorurl'];
		$hostUrl 		= $props['langbuilder.hosturl'];
		$license		= $props['langbuilder.license'];
		$minJVersion	= $props['langbuilder.minjversion'];
		$packageName 	= $props['langbuilder.packagename'];
		$softwareName 	= $props['langbuilder.software'];
		$timezone 		= $props['langbuilder.timezone'];

		// Instantiate S3
		$s3 = new S3($props['s3.access'], $props['s3.private']);
		$s3Bucket = $props['s3.bucket'];
		$s3Path = $props['s3.path'];

		// Scan languages
		$root = realpath(dirname(__FILE__).'/../../translations');
		$langs = $this->scan($root);
		ksort($langs);
		$numlangs = count($langs);
		echo "Found $numlangs languages\n\n";

		// Initialise $version
		$version = $this->version;

		date_default_timezone_set($timezone);

		$date = gmdate('d M Y');
		$year = gmdate('Y');

		$langToName = parse_ini_file('langtask/map.ini');

		$langHTMLTable = '';
		$row = 1;
		foreach($langs as $tag => $files) {
			$langName = $langToName[$tag];
			echo "Building $langName ($tag)...\n";

			// Get paths to temp and output files
			@mkdir(realpath(dirname(__FILE__).'/../..').'/release/languages');
			$j20ZIPPath = dirname(__FILE__).'/../../release/languages/'.$packageName.'-'.$tag.'.zip';
			$tempXMLPath = realpath(dirname(__FILE__).'/../..').'/release/'.$tag.'.xml';

			// Start new ZIP files
			@unlink($j20ZIPPath);
			$zip20 = new PclZip( $j20ZIPPath );

			// Produce the Joomla! manifest contents
			$j20XML = <<<ENDHEAD
<?xml version="1.0" encoding="utf-8"?>
<extension type="file" version="$minJVersion" method="upgrade" client="site">
    <name><![CDATA[$packageName-$tag]]></name>
    <author><![CDATA[$author]]></author>
    <authorurl>$authorUrl</authorurl>
	<copyright>Copyright (C)$year $author. All rights reserved.</copyright>
	<license>$license</license>
    <version>$version</version>
    <creationDate>$date</creationDate>
    <description><![CDATA[$langName translation file for $softwareName]]></description>
	<fileset>

ENDHEAD;

			if(array_key_exists('backend', $files)){
				$j20XML .= "\t\t<files folder=\"backend\" target=\"administrator/language/$tag\">\n";
				foreach($files['backend'] as $file) {
					$j20XML .= "\t\t\t<filename>".baseName($file)."</filename>\n";
				}
				$j20XML .= "\t\t</files>\n";
			}
			if(array_key_exists('frontend', $files)){
				$j20XML .= "\t\t<files folder=\"frontend\" target=\"language/$tag\">\n";
				foreach($files['frontend'] as $file) {
					$j20XML .= "\t\t\t<filename>".baseName($file)."</filename>\n";
				}
				$j20XML .= "\t\t</files>\n";
			}
			$j20XML .= "\t</fileset>\n</extension>";

			// Add the manifest (J! 2.x)
			@unlink($tempXMLPath);
			@file_put_contents($tempXMLPath, $j20XML);
			$zip20->add($tempXMLPath,
				PCLZIP_OPT_ADD_PATH, '',
				PCLZIP_OPT_REMOVE_PATH, dirname($tempXMLPath)
			);
			@unlink($tempXMLPath);

			// Add back-end files to archives
			if(array_key_exists('backend', $files)){
				foreach($files['backend'] as $file) {
					$zip20->add($file,
						PCLZIP_OPT_ADD_PATH, 'backend' ,
						PCLZIP_OPT_REMOVE_PATH, dirname($file) );
				}
			}
			// Add front-end files to archives
			if(array_key_exists('frontend', $files)){
				foreach($files['frontend'] as $file) {
					$zip20->add($file,
						PCLZIP_OPT_ADD_PATH, 'frontend' ,
						PCLZIP_OPT_REMOVE_PATH, dirname($file) );
				}
			}

			// Close archives
			unset($zip20);

			$parts = explode('-', $tag);
			$country = strtolower($parts[1]);
			if($tag == 'ca-ES') {
				$country = 'catalonia';
			}

			$base20 = basename($j20ZIPPath);

			$row = 1 - $row;
			$langHTMLTable .= <<<ENDHTML
	<tr class="row$row">
		<td width="16"><img src="$hostUrl/language/flags/$country.png" /></td>
		<td width="50" align="center"><tt>$tag</tt></td>
		<td width="250">$langName</td>
		<td>
			<a href="$hostUrl/language/$packageName/$base20">Download for Joomla! $minJVersion+</a>
		</td>
	</tr>

ENDHTML;

			// Upload translation files
			echo "\tUploading ".basename($j20ZIPPath)."\n";
			$s3->putObjectFile($j20ZIPPath, $s3Bucket, $s3Path.'/'.$packageName.'/'.basename($j20ZIPPath), S3::ACL_PUBLIC_READ);
		}

		$html = @file_get_contents(dirname(__FILE__).'/../../translations/_pages/index.html');
		$html = str_replace('[DATE]', gmdate('d M Y H:i:s'), $html);
		$html = str_replace('[LANGTABLE]', $langHTMLTable, $html);
		$html = str_replace('[YEAR]', gmdate('Y'), $html);

		echo "Uploading index.html file\n";
		$tempHTMLPath = realpath(dirname(__FILE__).'/../..').'/release/index.html';
		@file_put_contents($tempHTMLPath, $html);
		$s3->putObjectFile($tempHTMLPath, $s3Bucket, $s3Path.'/'.$packageName.'/index.html', S3::ACL_PUBLIC_READ);
		@unlink($tempHTMLPath);

		return true;
	}
}