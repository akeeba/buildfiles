<?php
require_once 'phing/Task.php';
require_once dirname(__FILE__).'/LinkTask.php';

if(stristr(php_uname(), 'windows')) {
	define('AKEEBA_RELINK_WINDOWS', 1);
}

function isLink($path)
{
	if(defined('AKEEBA_RELINK_WINDOWS')) {
		return file_exists($path);
	} else {
		return is_link($path);
	}
}

function symlink_dir($from, $to)
{
	if(is_dir($to))
	{
		if(AKEEBA_RELINK_WINDOWS)
		{
			$cmd = 'rmdir /s /q "'.$to.'"';
		}
		else
		{
			$cmd = 'rm -rf "'.$to.'"';
		}
		exec($cmd);
	}

	if(defined('AKEEBA_RELINK_WINDOWS')) {
		$cmd = 'mklink /D "'.$to.'" "'.$from.'"';
		exec($cmd);
	} else {
		@symlink($from, $to);
	}
}

function symlink_file($from, $to)
{
	if(file_exists($to))
	{
		if(AKEEBA_RELINK_WINDOWS)
		{
			$cmd = 'del /f /q "'.$to.'"';
		}
		else
		{
			$cmd = 'rm -f "'.$to.'"';
		}
		exec($cmd);
	}

	if(defined('AKEEBA_RELINK_WINDOWS')) {
		$cmd = 'mklink "'.$to.'" "'.$from.'"';
		exec($cmd);
	} else {
		@symlink($from, $to);
	}
}

function hardlink_file($from, $to)
{
	if(file_exists($to))
	{
		if(AKEEBA_RELINK_WINDOWS)
		{
			$cmd = 'del /f /q "'.$to.'"';
		}
		else
		{
			$cmd = 'rm -f "'.$to.'"';
		}
		exec($cmd);
	}

	if(defined('AKEEBA_RELINK_WINDOWS')) {
		$cmd = 'mklink /H "'.$to.'" "'.$from.'"';
		exec($cmd);
	} else {
		@link($from, $to);
	}
}

function realpath2($path)
{
	if(defined('AKEEBA_RELINK_WINDOWS')) {
		return str_replace('/', '\\', $path);
	} else {
		return str_replace('\\', '/', $path);
	}
}

/**
 * Class LinkSiteTask
 *
 * Generates links for the extension for the defined site(s). Basically installing
 * all the extension files/folders of the repository which are needed to run the
 * extension.
 *
 * Note that the database is left untouched and tables need to be installed manually,
 * e.g. by installing the extension via the extension manager of Joomla!.
 *
 * Single target link example:
 * <code>
 *     <linksite siteroot="/Path/To/Your/Site" />
 * </code>
 */
class LinkSiteTask extends LinkTask
{
	/**
	 * The path to the sources (repository root).
	 *
	 * @var 	string
	 */
	private $_root = null;

	/**
	 * The path to the site's root.
	 *
	 * @var 	string
	 */
	private $_siteRoot = null;

	/**
	 * The version of the Joomla! site we're linking to.
	 *
	 * @var 	string
	 */
	private $_joomlaVersion = '1.5';

	/**
	 * Information about the modules.
	 *
	 * @var 	array
	 */
	private $_modules = array();

	/**
	 * Information about the plugins.
	 *
	 * @var 	array
	 */
	private $_plugins = array();

	/**
	 * Information about the component.
	 *
	 * @var 	array
	 */
	private $_component = array();

	/**
	 * Setter for _root.
	 *
	 * @var 	string
	 */
	public function setRoot($root)
	{
		$this->_root = $root;
	}

	/**
	 * Getter for _root.
	 *
	 * @return 	string
	 */
	public function getRoot()
	{
		return $this->_root;
	}

	/**
	 * Setter for _siteRoot.
	 *
	 * @var 	string
	 */
	public function setSiteRoot($siteRoot)
	{
		$this->_siteRoot = $siteRoot;
	}

	/**
	 * Getter for _siteRoot.
	 *
	 * @return 	string
	 */
	public function getSiteRoot()
	{
		return $this->_siteRoot;
	}

	/**
	 * Main entry point for task.
	 *
	 * @return 	bool
	 */
	public function main()
	{
		echo "\n";
		$this->log("Processing links for ".$this->_siteRoot, Project::MSG_INFO);

		// Set repository root path
		$root = $this->getRoot();
		if(empty($root)) {
			$root = dirname(__FILE__) . '/../..';
		}
		$this->_root = $root;

		// Detect the site's version
		$this->_detectJoomlaVersion();

		// Load information about the bundled extensions
		$this->_scanComponent();
		$this->_fetchModules();
		$this->_fetchPlugins();

		// Unlink and link component, modules and plugins
		$this->unlinkComponent();
		$this->linkComponent();

		$this->unlinkModules();
		$this->linkModules();

		$this->unlinkPlugins();
		$this->linkPlugins();

		return true;
	}

	/**
	 * Detect the exact version of a Joomla! site.
	 */
	private function _detectJoomlaVersion()
	{
		define('_JEXEC', 1);
		define('JPATH_BASE', $this->_siteRoot);

		$file15 = $this->_siteRoot.'/libraries/joomla/version.php';
		$file16 = $this->_siteRoot.'/includes/version.php';
		$file25Plus = $this->_siteRoot.'/libraries/cms/version/version.php';

		if(@file_exists($file15))
		{
			$file = $file15;
		} elseif(@file_exists($file16)) {
			$file = $file16;
		} elseif(@file_exists($file25Plus)) {
			$file = $file25Plus;
		} else {
			die('ERROR: Joomla! version.php not defined in _detectJoomlaVersion().');
		}

		$this->_joomlaVersion = $this->_getJoomlaShortVersion($file);
	}

	/**
	 * Gets the Joomla! Short Version of a version.php.
	 *
	 * When using the LinkExtensionTask multiple times in one run, the class
	 * JVersion is declared multiple times which results in a fatal error.
	 * This method fixes it with a workaround.
	 *
	 * @param 	string $file	The file path of the version.php of Joomla!
	 *
	 * @return 	string The Joomla! short version.
	 *
	 * @see	http://stackoverflow.com/a/10052180
	 */
	private function _getJoomlaShortVersion($file)
	{
		// Add an MD5 hash of the site path to the class name to prevent redeclaring
		// the same class when multiple sites with the same Joomla version are used.
		$className = 'JVersion'.md5($this->_siteRoot);

		$content = file_get_contents($file);
		$content = preg_replace('/class JVersion/i', 'class '.$className, $content);
		eval('?>'.$content);
		$v = new $className;
		$shortVersion = $v->getShortVersion();

		return $shortVersion;
	}

	/**
	 * Gets the information for all included modules
	 */
	private function _fetchModules()
	{
		// Check if we have site/admin subdirectories, or just a bunch of modules
		$scanPath = $this->_root.'/modules';
		if( is_dir($scanPath.'/admin') || is_dir($scanPath.'/site') ) {
			$paths = array(
				$scanPath.'/admin',
				$scanPath.'/site',
			);
		} else {
			$paths = array(
				$scanPath
			);
		}

		// Iterate directories
		$this->_modules = array();
		foreach($paths as $path) {
			if( !is_dir($path) && !isLink($path) ) continue;
			foreach(new DirectoryIterator($path) as $fileInfo) {
				if($fileInfo->isDot()) continue;
				if(!$fileInfo->isDir()) continue;

				$modPath = $path.'/'.$fileInfo->getFilename();
				$info = $this->_scanModule($modPath);

				if(!is_array($info)) continue;
				if(!array_key_exists('module', $info)) continue;

				$this->_modules[] = $info;
			}
		}
	}

	/**
	 * Gets the information for all included plugins
	 */
	private function _fetchPlugins()
	{
		// Check if we have site/admin subdirectories, or just a bunch of modules
		$scanPath = $this->_root.'/plugins';
		if( is_dir($scanPath.'/system') || is_dir($scanPath.'/content') || is_dir($scanPath.'/user') ) {
			$paths = array();
			foreach(new DirectoryIterator($scanPath) as $fileInfo) {
				if($fileInfo->isDot()) continue;
				if(!$fileInfo->isDir()) continue;

				$paths[] = $scanPath.'/'.$fileInfo->getFilename();
			}
		} else {
			$paths = array(
				$scanPath
			);
		}

		// Iterate directories
		$this->_plugins = array();
		foreach($paths as $path) {
			if( !is_dir($path) && !isLink($path) ) continue;
			foreach(new DirectoryIterator($path) as $fileInfo) {
				if($fileInfo->isDot()) continue;
				if(!$fileInfo->isDir()) continue;

				$plgPath = $path.'/'.$fileInfo->getFilename();
				$info = $this->_scanPlugin($plgPath);

				if(!is_array($info)) continue;
				if(!array_key_exists('plugin', $info)) continue;

				$this->_plugins[] = $info;
			}
		}
	}

	/**
	 * Scans a module directory to fetch the extension information
	 *
	 * @param string $path
	 * @return array
	 */
	private function _scanModule($path)
	{
		// Find the XML files
		foreach(new DirectoryIterator($path) as $fileInfo) {
			if($fileInfo->isDot()) continue;
			if(!$fileInfo->isFile()) continue;
			$fname = $fileInfo->getFilename();
			if(substr($fname, -4) != '.xml') continue;

			$xmlDoc = new DOMDocument;
			$xmlDoc->load($path.'/'.$fname, LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_NOENT | LIBXML_NONET);

			$rootNodes = $xmlDoc->getElementsByTagname('install');
			$altRootNodes = $xmlDoc->getElementsByTagname('extension');
			if($altRootNodes->length >= 1) {
				unset($rootNodes);
				$rootNodes = $altRootNodes;
			}
			if($rootNodes->length < 1) {
				unset($xmlDoc);
				continue;
			}

			$root = $rootNodes->item(0);
			if(!$root->hasAttributes()) {
				unset($xmlDoc);
				continue;
			}
			if($root->getAttribute('type') != 'module') {
				unset($xmlDoc);
				continue;
			}

			$module = '';
			$files = $xmlDoc->getElementsByTagName('files')->item(0)->childNodes;
			foreach($files as $file) {
				if($file->hasAttributes()) {
					$module = $file->getAttribute('module');
				}
			}

			// Get the languages
			if($xmlDoc->getElementsByTagName('languages')->length < 1) {
				$langFolder = null;
				$langFiles = array();
			} else {
				$langTag = $xmlDoc->getElementsByTagName('languages')->item(0);
				$langFolder = $path.'/'.$langTag->getAttribute('folder');
				$langFiles = array();
				foreach($langTag->childNodes as $langFile) {
					if(!($langFile instanceof DOMElement)) continue;
					$tag = $langFile->getAttribute('tag');
					$lfPath = $langFolder.'/'.$langFile->textContent;
					$langFiles[$tag][] = $lfPath;
				}
			}

			// Get the media folder
			$mediaFolder = null;
			$mediaDestination = null;
			$mediaPath = null;
			$allMediaTags = $xmlDoc->getElementsByTagName('media');
			if($allMediaTags->length >= 1) {
				$mediaFolder = $allMediaTags->item(0)->getAttribute('folder');
				$mediaDestination = $allMediaTags->item(0)->getAttribute('destination');
				$mediaPath = $path.'/'.$mediaFolder;
			}

			if(empty($module)) {
				unset($xmlDoc);
				continue;
			}

			$ret = array(
				'module'			=> $module,
				'path'				=> $path,
				'client'			=> $root->getAttribute('client'),
				'langPath'			=> $langFolder,
				'langFiles'			=> $langFiles,
				'mediaFolder'		=> $mediaFolder,
				'mediaDestination'	=> $mediaDestination,
				'mediaPath'			=> $mediaPath,
			);

			unset($xmlDoc);
			return $ret;
		}
	}

	/**
	 * Scans a plugin directory to fetch the extension information
	 *
	 * @param string $path
	 * @return array
	 */
	private function _scanPlugin($path)
	{
		// Find the XML files
		foreach(new DirectoryIterator($path) as $fileInfo) {
			if($fileInfo->isDot()) continue;
			if(!$fileInfo->isFile()) continue;
			$fname = $fileInfo->getFilename();
			if(substr($fname, -4) != '.xml') continue;

			$xmlDoc = new DOMDocument;
			$xmlDoc->load($path.'/'.$fname, LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_NOENT | LIBXML_NONET);

			$rootNodes = $xmlDoc->getElementsByTagname('install');
			$altRootNodes = $xmlDoc->getElementsByTagname('extension');
			if($altRootNodes->length >= 1) {
				unset($rootNodes);
				$rootNodes = $altRootNodes;
			}
			if($rootNodes->length < 1) {
				unset($xmlDoc);
				continue;
			}

			$root = $rootNodes->item(0);
			if(!$root->hasAttributes()) {
				unset($xmlDoc);
				continue;
			}
			if($root->getAttribute('type') != 'plugin') {
				unset($xmlDoc);
				continue;
			}

			$folder = $root->getAttribute('group');

			$plugin = '';
			$files = $xmlDoc->getElementsByTagName('files')->item(0)->childNodes;
			foreach($files as $file) {
				if($file->hasAttributes()) {
					$plugin = $file->getAttribute('plugin');
				}
			}

			// Get the languages
			if($xmlDoc->getElementsByTagName('languages')->length < 1) {
				$langFolder = null;
				$langFiles = array();
			} else {
				$langTag = $xmlDoc->getElementsByTagName('languages')->item(0);
				$langFolder = $path.'/'.$langTag->getAttribute('folder');
				$langFiles = array();
				foreach($langTag->childNodes as $langFile) {
					if(!($langFile instanceof DOMElement)) continue;
					$tag = $langFile->getAttribute('tag');
					$lfPath = $langFolder.'/'.$langFile->textContent;
					$langFiles[$tag][] = $lfPath;
				}
			}

			// Get the media folder
			$mediaFolder = null;
			$mediaDestination = null;
			$mediaPath = null;
			$allMediaTags = $xmlDoc->getElementsByTagName('media');
			if($allMediaTags->length >= 1) {
				$mediaFolder = $allMediaTags->item(0)->getAttribute('folder');
				$mediaDestination = $allMediaTags->item(0)->getAttribute('destination');
				$mediaPath = $path.'/'.$mediaFolder;
			}

			if(empty($plugin)) {
				unset($xmlDoc);
				continue;
			}

			$ret = array(
				'plugin'			=> $plugin,
				'folder'			=> $folder,
				'path'				=> $path,
				'langPath'			=> $langFolder,
				'langFiles'			=> $langFiles,
				'mediaFolder'		=> $mediaFolder,
				'mediaDestination'	=> $mediaDestination,
				'mediaPath'			=> $mediaPath,
			);

			unset($xmlDoc);
			return $ret;
		}
	}

	/**
	 * Scan the component directory and get some useful info
	 * @return type
	 */
	private function _scanComponent()
	{
		$path = $this->_root.'/component';

		// Find the XML files
		foreach(new DirectoryIterator($path) as $fileInfo) {
			if($fileInfo->isDot()) continue;
			if(!$fileInfo->isFile()) continue;
			$fname = $fileInfo->getFilename();
			if(substr($fname, -4) != '.xml') continue;

			$xmlDoc = new DOMDocument;
			$xmlDoc->load($path.'/'.$fname, LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_NOENT | LIBXML_NONET);

			$rootNodes = $xmlDoc->getElementsByTagname('install');
			$altRootNodes = $xmlDoc->getElementsByTagname('extension');
			if($altRootNodes->length >= 1) {
				unset($rootNodes);
				$rootNodes = $altRootNodes;
			}
			if($rootNodes->length < 1) {
				unset($xmlDoc);
				continue;
			}

			$root = $rootNodes->item(0);
			if(!$root->hasAttributes()) {
				unset($xmlDoc);
				continue;
			}
			if($root->getAttribute('type') != 'component') {
				unset($xmlDoc);
				continue;
			}

			// Get the component name
			$component = strtolower($xmlDoc->getElementsByTagName('name')->item(0)->textContent);
			if(substr($component,0,4) != 'com_') $component = 'com_'.$component;

			// Get the <files> tags for front and back-end
			$siteFolder = $path;
			$allFilesTags = $xmlDoc->getElementsByTagName('files');
			$nodePath0 = $allFilesTags->item(0)->getNodePath();
			$nodePath1 = $allFilesTags->item(1)->getNodePath();
			if(in_array($nodePath0, array('/install/files','/extension/files'))) {
				$siteFilesTag = $allFilesTags->item(0);
				$adminFilesTag = $allFilesTags->item(1);
			} else {
				$siteFilesTag = $allFilesTags->item(1);
				$adminFilesTag = $allFilesTags->item(0);
			}

			// Get the site and admin folders
			if($siteFilesTag->hasAttribute('folder')) $siteFolder = $path.'/'.$siteFilesTag->getAttribute('folder');
			if($adminFilesTag->hasAttribute('folder')) $adminFolder = $path.'/'.$adminFilesTag->getAttribute('folder');

			// Get the media folder
			$mediaFolder = null;
			$allMediaTags = $xmlDoc->getElementsByTagName('media');
			if($allMediaTags->length >= 1) {
				$mediaFolder = $path.'/'.$allMediaTags->item(0)->getAttribute('folder');
			}

			// Do we have a CLI folder
			$cliFolder = $path.'/cli';
			if(!is_dir($cliFolder)) {
				$cliFolder = '';
			}

			// Get the <languages> tags for front and back-end
			$langFolderSite = $path;
			$langFolderAdmin = $path;
			$allLanguagesTags = $xmlDoc->getElementsByTagName('languages');
			$nodePath0 = $allLanguagesTags->item(0)->getNodePath();
			$nodePath1 = $allLanguagesTags->item(1)->getNodePath();

			if(in_array($nodePath0, array('/install/languages','/extension/languages'))) {
				$siteLanguagesTag = $allLanguagesTags->item(0);
				$adminLanguagesTag = $allLanguagesTags->item(1);
			} else {
				$siteLanguagesTag = $allLanguagesTags->item(1);
				$adminLanguagesTag = $allLanguagesTags->item(0);
			}

			// Get the site and admin language folders
			if($siteLanguagesTag->hasAttribute('folder')) $langFolderSite = $path.'/'.$siteLanguagesTag->getAttribute('folder');
			if($adminLanguagesTag->hasAttribute('folder')) $langFolderAdmin = $path.'/'.$adminLanguagesTag->getAttribute('folder');

			// Get the frontend languages
			$langFilesSite = array();
			if($siteLanguagesTag->hasChildNodes()) foreach($siteLanguagesTag->childNodes as $langFile)
			{
				if(!($langFile instanceof DOMElement)) continue;
				$tag = $langFile->getAttribute('tag');
				$langFilesSite[$tag][] = $langFolderSite.'/'.$langFile->textContent;
			}

			// Get the backend languages
			$langFilesAdmin = array();
			if($adminLanguagesTag->hasChildNodes()) foreach($adminLanguagesTag->childNodes as $langFile)
			{
				if(!($langFile instanceof DOMElement)) continue;
				$tag = $langFile->getAttribute('tag');
				$langFilesAdmin[$tag][] = $langFolderAdmin.'/'.$langFile->textContent;
			}

			if(empty($component)) {
				unset($xmlDoc);
				continue;
			}

			$this->_component = array(
				'component'		=> $component,
				'siteFolder'	=> $siteFolder,
				'adminFolder'	=> $adminFolder,
				'mediaFolder'	=> $mediaFolder,
				'cliFolder'		=> $cliFolder,
				'siteLangPath'	=> $langFolderSite,
				'siteLangFiles'	=> $langFilesSite,
				'adminLangPath'	=> $langFolderAdmin,
				'adminLangFiles'=> $langFilesAdmin,
			);

			unset($xmlDoc);
			return;
		}
	}

	/**
	 * Maps the folders and files for the component
	 * @return array
	 */
	private function _mapComponent()
	{
		$files = array();
		// Frontend and backend directories
		$dirs = array(
			$this->_component['siteFolder']  => $this->_siteRoot.'/components/'.$this->_component['component'],
			$this->_component['adminFolder'] => $this->_siteRoot.'/administrator/components/'.$this->_component['component'],
		);
		// Media directory
		if($this->_component['mediaFolder']) {
			$dirs[$this->_component['mediaFolder']] =
				$this->_siteRoot.'/media/'.$this->_component['component'];
		}
		// CLI files
		if($this->_component['cliFolder']) {
			foreach(new DirectoryIterator($this->_component['cliFolder']) as $fileInfo) {
				if($fileInfo->isDot()) continue;
				if(!$fileInfo->isFile()) continue;
				$fname = $fileInfo->getFileName();
				if(substr($fname, -4) != '.php') continue;

				$files[$this->_component['cliFolder'].'/'.$fname] =
					$this->_siteRoot.'/cli/'.$fname;
			}
		}

		// Front-end language files
		$basePath = $this->_siteRoot.'/language/';
		if(!empty($this->_component['siteLangFiles'])) {
			foreach($this->_component['siteLangFiles'] as $tag => $lfiles) {
				$path = $basePath.$tag.'/';
				foreach($lfiles as $lfile) {
					$files[$lfile] = $path.basename($lfile);
				}
			}
		}

		// Back-end language files
		$basePath = $this->_siteRoot.'/administrator/language/';
		if(!empty($this->_component['adminLangFiles'])) {
			foreach($this->_component['adminLangFiles'] as $tag => $lfiles) {
				$path = $basePath.$tag.'/';
				foreach($lfiles as $lfile) {
					$files[$lfile] = $path.basename($lfile);
				}
			}
		}

		return array(
			'dirs'	=> $dirs,
			'files'	=> $files,
		);
	}

	private function _mapModule($module)
	{
		$files = array();
		$dirs = array();

		$basePath = $this->_siteRoot.'/';
		if($module['client'] != 'site') $basePath .= 'administrator/';
		$basePath .= 'modules/'.$module['module'];

		$dirs[$module['path']] = $basePath;

		// Language files
		if($module['client'] != 'site') {
			$basePath = $this->_siteRoot.'/administrator/language/';
		} else {
			$basePath = $this->_siteRoot.'/language/';
		}
		if(!empty($module['langFiles'])) {
			foreach($module['langFiles'] as $tag => $lfiles) {
				$path = $basePath.$tag.'/';
				foreach($lfiles as $lfile) {
					$files[$lfile] = $path.basename($lfile);
				}
			}
		}

		// Media directory
		if($module['mediaPath']) {
			$dirs[$module['mediaPath']] = $this->_siteRoot.'/'.$module['mediaFolder'].'/'.$module['mediaDestination'];
		}

		return array(
			'dirs'	=> $dirs,
			'files'	=> $files,
		);
	}

	private function _mapPlugin($plugin)
	{
		$files = array();
		$dirs = array();

		if(version_compare($this->_joomlaVersion, '1.6.0', 'ge')) {
			// Joomla! 1.6 or later -- just link one folder
			$basePath = $this->_siteRoot.'/plugins/'.$plugin['folder'].'/'.$plugin['plugin'];
			$dirs[$plugin['path']] = $basePath;
		} else {
			// Joomla! 1.5 -- we've got to scan for files and directories
			$basePath = $this->_siteRoot.'/plugins/'.$plugin['folder'].'/';
			foreach(new DirectoryIterator($plugin['path']) as $fileInfo) {
				if($fileInfo->isDot()) continue;
				$fname = $fileInfo->getFileName();
				if($fileInfo->isDir()) {
					$dirs[$plugin['path'].'/'.$fname] = $basePath.$fname;
				} elseif($fileInfo->isFile()) {
					$dirs[$plugin['path'].'/'.$fname] = $basePath.$fname;
				}
			}
		}

		// Language files
		$basePath = $this->_siteRoot.'/administrator/language/';
		if(!empty($plugin['langFiles'])) {
			foreach($plugin['langFiles'] as $tag => $lfiles) {
				$path = $basePath.$tag.'/';
				foreach($lfiles as $lfile) {
					$files[$lfile] = $path.basename($lfile);
				}
			}
		}

		// Media directory
		if($plugin['mediaPath']) {
			$dirs[$plugin['mediaPath']] = $this->_siteRoot.'/'.$plugin['mediaFolder'].'/'.$plugin['mediaDestination'];
		}

		return array(
			'dirs'	=> $dirs,
			'files'	=> $files,
		);
	}

	/**
	 * Unlinks the component
	 */
	public function unlinkComponent()
	{
		$this->log("Unlinking component ".$this->_component['component'], Project::MSG_INFO);

		$map = $this->_mapComponent();
		extract($map);

		$dirs = array_values($dirs);
		$files = array_values($files);

		$this->_unlinkDirectories($dirs);
		if(!empty($files)) $this->_unlinkFiles($files);
	}

	/**
	 * Unlinks the modules
	 */
	public function unlinkModules()
	{
		if(empty($this->_modules)) return;
		foreach($this->_modules as $module)
		{
			$this->log("Unlinking module ".$module['module'].' ('.$module['client'].")", Project::MSG_INFO);

			$map = $this->_mapModule($module);
			extract($map);

			$dirs = array_values($dirs);
			$files = array_values($files);

			$this->_unlinkDirectories($dirs);
			if(!empty($files)) $this->_unlinkFiles($files);
		}
	}

	/**
	 * Unlinks the plugins
	 */
	public function unlinkPlugins()
	{
		if(empty($this->_plugins)) return;
		foreach($this->_plugins as $plugin)
		{
	 	  	$this->log("Unlinking plugin ".$plugin['plugin'].' ('.$plugin['folder'].")", Project::MSG_INFO);

			$map = $this->_mapPlugin($plugin);
			extract($map);

			$dirs = array_values($dirs);
			$files = array_values($files);

			$this->_unlinkDirectories($dirs);
			if(!empty($files)) $this->_unlinkFiles($files);
		}
	}

	/**
	 * Relinks the component
	 */
	public function linkComponent()
	{
		$this->log("Linking component ".$this->_component['component'], Project::MSG_INFO);

		$map = $this->_mapComponent();
		extract($map);

		foreach($dirs as $from => $to)
		{
			symlink_dir(realpath2($from), realpath2($to));
		}

		foreach($files as $from => $to)
		{
			symlink_file(realpath2($from), realpath2($to));
		}
	}

	/**
	 * Relinks the modules
	 */
	public function linkModules()
	{
		if(empty($this->_modules)) return;
		foreach($this->_modules as $module)
		{
			$this->log("Linking module ".$module['module'].' ('.$module['client'], Project::MSG_INFO);

			$map = $this->_mapModule($module);
			extract($map);

			foreach($dirs as $from => $to)
			{
				symlink_dir(realpath2($from), realpath2($to));
			}

			foreach($files as $from => $to)
			{
				symlink_file(realpath2($from), realpath2($to));
			}
		}
	}

	/**
	 * Relinks the plugins
	 */
	public function linkPlugins()
	{
		if(empty($this->_plugins)) return;
		foreach($this->_plugins as $plugin)
		{
			$this->log("Linking plugin ".$plugin['plugin'].' ('.$plugin['folder'].")", Project::MSG_INFO);

			$map = $this->_mapPlugin($plugin);
			extract($map);

			foreach($dirs as $from => $to)
			{
				symlink_dir(realpath2($from), realpath2($to));
			}

			foreach($files as $from => $to)
			{
				symlink_file(realpath2($from), realpath2($to));
			}
		}
	}

	/**
	 * Remove a list of directories
	 * @param array $dirs
	 * @return boolean
	 */
	private function _unlinkDirectories($dirs)
	{
		foreach($dirs as $dir) {
			if(isLink($dir)) {
				$result = unlink(realpath2($dir));
			} elseif(is_dir($dir)) {
				$result = $this->_rmrecursive($dir);
			} else {
				$result = true;
			}
			if($result === false) return $result;
		}
		return $result;
	}

	/**
	 * Remove a list of files
	 * @param array $files
	 * @return boolean
	 */
	private function _unlinkFiles($files)
	{
		foreach($files as $file) {
			if(isLink($file) || is_file($file)) {
				$result = unlink(realpath2($file));
			} else {
				$result = true;
			}
			if($result === false) return $result;
		}
		return $result;
	}

	/**
	 * Recursively delete a directory
	 * @param string $dir
	 * @return bool
	 */
	private function _rmrecursive($dir)
	{
		// When the directory is a symlink, don't delete recursively. That would
		// fuck up the plugins.
		if(isLink($dir)) {
			return @unlink(realpath2($dir));
		}

		$handle = opendir($dir);
		while( false != ($item = readdir($handle))) {
			if( !in_array($item, array('.','..')) ) {
				$path = $dir.'/'.$item;
				if(isLink($path)) {
					$result = @unlink(realpath2($path));
				} elseif(is_file($path)) {
					$result = @unlink(realpath2($path));
				} elseif(is_dir($path)) {
					$result = $this->_rmrecursive(realpath2($path));
				} else {
					$result = @unlink(realpath2($path));
				}
				if(!$result) return false;
			}
		}
		closedir($handle);

		if(!rmdir($dir)) return false;

		return true;
	}
}