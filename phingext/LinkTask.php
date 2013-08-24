<?php
define('IS_WINDOWS', substr(PHP_OS, 0, 3) == 'WIN');

require_once 'phing/Task.php';
require_once 'phing/tasks/ext/SymlinkTask.php';

/**
 * Class LinkTask
 *
 * Generates symlinks and hardlinks based on a target / link combination.
 * Can also individually link contents of a directory.
 *
 * Single target link example:
 * <code>
 *     <link target="/some/shared/file" link="${project.basedir}/htdocs/my_file" />
 * </code>
 *
 * Link entire contents of directory
 *
 * This will go through the contents of "/my/shared/library/*"
 * and create a link for each entry into ${project.basedir}/library/
 * <code>
 *     <link link="${project.basedir}/library">
 *         <fileset dir="/my/shared/library">
 *             <include name="*" />
 *         </fileset>
 *     </link>
 * </code>
 *
 * The type property can be used to override the link type, which is by default a
 * symlink. Example for a hardlink:
 * <code>
 *     <link target="/some/shared/file" link="${project.basedir}/htdocs/my_file" type="hardlink" />
 * </code>
 */
class LinkTask extends SymlinkTask
{
	/**
	 * Link type.
	 *
	 * @var 	string
	 */
	protected $_type = 'symlink';

	/**
	 * Setter for _type.
	 *
	 * @param 	string $type
	 */
	public function setType($type)
	{
		$this->_type = $type;
	}

	/**
	 * Getter for _type.
	 *
	 * @return 	string
	 */
	public function getType()
	{
		return $this->_type;
	}

	/**
	 * Main entry point for task
	 *
	 * @return bool
	 */
	public function main()
	{
		$map  = $this->getMap();
		$to   = $this->getLink();
		$type = $this->getType();

		// Single file symlink
		if(is_string($map)) {
			$from = $map;
			$this->doLink($from, $to, $type);
			return true;
		}

		// Multiple symlinks
		foreach($map as $name => $from) {
			$realTo = $to . DIRECTORY_SEPARATOR . $name;
			$this->doLink($from, $realTo, $type);
		}

		return true;
	}

	/**
	 * Links a file or folder.
	 *
	 * @param string $from	Where to link from.
	 * @param string $to	Where to link to.
	 * @param string $type	The link type. Possible values: 'symlink' (default), 'hardlink'.
	 */
	protected function doLink($from, $to, $type)
	{
		// Translate windows paths
		if(IS_WINDOWS) {
			// Windows doesn't play nice with paths containing UNIX path separators
			$to = $this->TranslateWinPath($to);
			$from = $this->TranslateWinPath($from);
			// Windows doesn't play nice with relative paths in symlinks
			$from = @realpath($from);
		}

		// Unlink
		if(is_file($to) || is_dir($to) || is_link($to) || file_exists($to)) {
			if(IS_WINDOWS && is_dir($to)) {
				// Windows can't unlink() directory symlinks; it needs rmdir() to be used instead
				$res = @rmdir($to);
			} elseif (is_file($to) || is_dir($to)) {
				$res = @unlink($to);
			}
			if(!$res) {
				$this->log('Failed unlink: ' . $to, Project::MSG_ERR);
				return;
			}
		}

		$this->log('Linking (' . $type .'): ' . $from . ' to ' . $to, Project::MSG_INFO);

		if($type == 'symlink')
		{
			$res = @symlink($from, $to);
		}
		elseif($type == 'hardlink')
		{
			$res = @link($from, $to);
		}

		if(!$res)
		{
			if($type == 'symlink') {
				$this->log('Failed symlink: ' . $to, Project::MSG_ERR);
			} elseif($type == 'hardlink') {
				$this->log('Failed hardlink: ' . $to, Project::MSG_ERR);
			}
		}
	}

	/**
	 * Translates the path for Windows.
	 *
	 * @param 	$path			The untranslated path.
	 *
	 * @return 	mixed|string	The translated path.
	 *
	 * @see		http://en.wikipedia.org/wiki/Path_(computing)
	 */
	protected function TranslateWinPath($path)
	{
		// Initialise the good and bad directory separators
		$DsGood = IS_WINDOWS ? '\\' : '/';
		$DsBad  = IS_WINDOWS ? '/' : '\\';

		if (IS_WINDOWS)
		{
			// Fix potential incorrect Windows directory separator
			if ((strpos($path, $DsBad) > 0) || (substr($path, 0, 1) == $DsBad)){
				$path = strtr($path, $DsBad, $DsGood);
			}

			// Is this an UNC path?
			$is_unc = (substr($path, 0, 2) == ($DsGood . $DsGood));

			// Fix UNC paths
			if ($is_unc)
			{
				$path = $DsGood . $DsGood .ltrim($path, $DsGood);
			}
		}

		// Remove multiple slashes
		$path = str_replace('///', '/', $path);
		$path = str_replace('//', '/', $path);

		return $path;
	}
}