<?php
/**
 * Akeeba Build Tools
 *
 * @package        buildfiles
 * @license        GPL v3
 * @copyright      2010-2017 Akeeba Ltd
 */

namespace Akeeba\LinkLibrary;

/**
 * Symbolic and hard link helper
 *
 * The code in this class is designed to create and delete symbolic links to folders and files, as well as hard links to
 * files under the three major OS: Windows, Linux and macOS.
 */
abstract class LinkHelper
{
	/**
	 * Are we running under Microsoft Windows?
	 *
	 * @var   bool|null
	 */
	private static $isWindows = null;

	/**
	 * Detects whether we are running under Microsoft Windows
	 *
	 * @return  bool
	 */
	private static function isWindows(): bool
	{
		if (is_null(self::$isWindows))
		{
			self::$isWindows = substr(PHP_OS, 0, 3) == 'WIN';
		}

		return self::$isWindows;
	}

	/**
	 * Normalize Windows or mixed Windows and UNIX paths to UNIX style
	 *
	 * @param   string  $path  The path to normalize
	 *
	 * @return  string
	 */
	public static function TranslateWinPath(string $path): string
	{
		/** @var  bool  $is_unc  Is this a UNC (network share) path? */
		$is_unc = false;

		if (self::isWindows())
		{
			// Is this a UNC path?
			$is_unc = (substr($path, 0, 2) == '\\\\') || (substr($path, 0, 2) == '//');

			// Change potential windows directory separator
			if ((strpos($path, '\\') > 0) || (substr($path, 0, 1) == '\\'))
			{
				$path = strtr($path, '\\', '/');
			}
		}

		// Remove multiple consequtive slashes
		while (strpos($path, '//') !== false)
		{
			$path = str_replace('//', '/', $path);
		}

		// Restore UNC paths (their double leading slash is converted to a single slash)
		if ($is_unc)
		{
			$path = '//' . ltrim($path, '/');
		}

		return $path;
	}

	/**
	 * Create a link.
	 *
	 * @param   string  $from  The location of the file which already exists
	 * @param   string  $to    The symlink to be created
	 * @param   string  $type  The type of link to create: symlink (symbolic link) or link (hard link)
	 * @param   string  $path  The path that $to and $form are relative to
	 *
	 * @return  void
	 *
	 * @throw   \RuntimeException  If the link ($to) cannot be created / replaced
	 */
	public static function makeLink(string $from, string $to, string $type = 'symlink', string $path = null)
	{
		$isWindows = self::isWindows();
		$realTo    = $to;
		$realFrom  = $from;

		// If from / to are relative to a path let's combine them
		if (!empty($path))
		{
			$realTo   = $path . '/' . $to;
			$realFrom = $path . '/' . $from;
		}

		// Windows doesn't play nice with paths containing mixed UNIX and Windows path separators
		if ($isWindows)
		{
			$realTo   = self::TranslateWinPath($realTo);
			$realFrom = self::TranslateWinPath($realFrom);
		}

		// Make sure the path exists
		if (!is_dir(dirname($realTo)))
		{
			mkdir(dirname($realTo), 0755, true);
		}

		// Get the real absolute paths
		$realFrom = self::getRealPath($realFrom);
		$realTo   = self::getRealPath($realTo);

		// If the target already exists we need to remove it first
		if (is_file($realTo) || is_dir($realTo) || is_link($realTo) || file_exists($realTo))
		{
			$res = self::unlink($realTo);

			if (!$res)
			{
				throw new \RuntimeException("Cannot delete link target $realTo");
			}
		}

		if ($type == 'symlink')
		{
			if ($isWindows)
			{
				$extraArguments = '';

				if (is_dir($realFrom))
				{
					$extraArguments = ' /D ';
				}

				$relativeFrom = self::getRelativePath($realTo, $realFrom);
				$cmd          = 'mklink ' . $extraArguments . ' "' . $realTo . '" "' . $relativeFrom . '"';
				$res          = exec($cmd);
			}
			else
			{
				$relativeFrom = self::getRelativePath($realTo, $realFrom);
				$res          = @symlink($relativeFrom, $realTo);
			}
		}
		else // $type == 'link'
		{
			$res = @link($realFrom, $realTo);
		}

		if (!$res)
		{
			if ($type == 'symlink')
			{
				throw new \RuntimeException("Cannot create symbolic link $realTo");
			}
			elseif ($type == 'link')
			{
				throw new \RuntimeException("Cannot create hard link $realTo");
			}
		}
	}

	/**
	 * Create a symbolic link to a directory or file
	 *
	 * @param   string  $from  The location of the file / directory which already exists
	 * @param   string  $to    The symlink to be created (where the new symlinked file / directory will live)
	 *
	 * @return  void
	 */
	public static function symlink(string $from, string $to)
	{
		self::makeLink($from, $to, 'symlink');
	}

	/**
	 * Create a hard link to a file.
	 *
	 * If you accidentally try to create a hardlink to a directory you will get a warning and a symlink will be created
	 * instead.
	 *
	 * @param   string  $from  The location of the file which already exists
	 * @param   string  $to    The hard link to be created (where the new hard linked file / directory will live)
	 *
	 * @return  void
	 */
	public static function hardlink(string $from, string $to)
	{
		$linkType = 'link';

		if (is_dir($from))
		{
			$linkType = 'symlink';

			trigger_error("Cannot create hardlink to directory $from; making a symlink instead", E_USER_WARNING);
		}

		self::makeLink($from, $to, $linkType);
	}

	/**
	 * Recursively delete a directory
	 *
	 * @param   string  $dir  The directory to remove
	 *
	 * @return  bool  True on success
	 */
	public static function recursiveUnlink(string $dir): bool
	{
		$return = true;

		try
		{
			$dh = new \DirectoryIterator($dir);

			foreach ($dh as $file)
			{
				if ($file->isDot())
				{
					continue;
				}

				$pathname = $file->getPathname();

				if ($file->isDir())
				{
					// We have to try the rmdir in case this is a Windows directory symlink OR an empty folder.
					$deleteFolderResult = @rmdir($pathname);

					// If rmdir failed (non-empty, real folder) we have to recursively delete it
					if (!$deleteFolderResult)
					{
						$deleteFolderResult = self::recursiveUnlink($pathname);
						$return             = $return && $deleteFolderResult;
					}

					if (!$deleteFolderResult)
					{
						throw new \RuntimeException("Failed deleting folder {$pathname}");
					}
				}

				if (!file_exists($pathname) && !is_dir($pathname))
				{
					continue;
				}

				// We have to try the rmdir in case this is a Windows directory symlink.
				$deleteFileResult = @rmdir($pathname);

				if (!$deleteFileResult)
				{
					$deleteFileResult = @unlink($pathname);
				}

				$return           = $return && $deleteFileResult;

				if (!$deleteFileResult)
				{
					throw new \RuntimeException("Failed deleting file {$pathname}");
				}
			}

			$return = $return && @rmdir($dir);

			return $return;
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	/**
	 * Get the real path
	 *
	 * Resolves references to '/./', '/../' and extra '/' characters in the input path and
	 * returns the canonicalized absolute pathname. Trailing delimiters, such as \ and /,
	 * are also removed. Unlike PHP's realpath(), this function does not return false if
	 * the file does not exist. This is why we need this function.
	 *
	 * @param 	string 	$pathToConvert	The path to convert to a real path
	 *
	 * @return 	string 	The real path
	 */
	public function getRealPath(string $pathToConvert): string
	{
		// Compatibility fix for Windows paths
		$pathToConvert = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $pathToConvert);

		// Explode $pathToConvert
		// array_filter(..., 'strlen') prevent empty array $parts values (e.g. when $pathToConvert contains '//')
		$parts = array_filter(explode(DIRECTORY_SEPARATOR, $pathToConvert), 'strlen');

		$realPathParts = [];

		foreach ($parts as $part)
		{
			if ($part == '.')
			{
				continue;
			}

			if ($part == '..')
			{
				array_pop($realPathParts);
			}
			else
			{
				$realPathParts[] = $part;
			}
		}

		return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $realPathParts);
	}

	/**
	 * Get the relative path between two folders
	 *
	 * @param   string  $pathToConvert  Convert this folder to a location relative to $from
	 * @param   string  $basePath       Base folder
	 *
	 * @return  string  The relative path
	 */
	public static function getRelativePath(string $pathToConvert, string $basePath): string
	{
		// Some compatibility fixes for Windows paths
		$pathToConvert = is_dir($pathToConvert) ? rtrim($pathToConvert, '\/') . '/' : $pathToConvert;
		$basePath      = is_dir($basePath) ? rtrim($basePath, '\/') . '/' : $basePath;
		$pathToConvert = str_replace('\\', '/', $pathToConvert);
		$basePath      = str_replace('\\', '/', $basePath);

		$pathToConvert = explode('/', $pathToConvert);
		$basePath      = explode('/', $basePath);
		$relPath       = $basePath;

		foreach ($pathToConvert as $depth => $dir)
		{
			// find first non-matching dir
			if ($dir === $basePath[$depth])
			{
				// ignore this directory
				array_shift($relPath);
			}
			else
			{
				// get number of remaining dirs to $pathToConvert
				$remaining = count($pathToConvert) - $depth;

				if ($remaining > 1)
				{
					// add traversals up to first matching dir
					$padLength = (count($relPath) + $remaining - 1) * -1;
					$relPath   = array_pad($relPath, $padLength, '..');
					break;
				}
				else
				{
					$relPath[0] = '.' . DIRECTORY_SEPARATOR . $relPath[0];
				}
			}
		}

		return implode(DIRECTORY_SEPARATOR, $relPath);
	}

	/**
	 * Link-safe file and directory deletion. It recognizes when the target is a symbolic or hard link and delete it in
	 * the Operating System-appropriate way. If the target is found to be a real file it will be unlinked normally.
	 * Finally, if the target is a real directory it will be deleted recursively.
	 *
	 * @param   string  $target  The target link / file / folder to delete.
	 *
	 * @return  bool  True on success
	 */
	public static function unlink(string $target): bool
	{
		$isWindows = self::isWindows();

		if ($isWindows)
		{
			$target   = self::TranslateWinPath($target);
		}

		// Windows can't unlink() directory symlinks; it needs rmdir() to be used instead
		if ($isWindows && is_dir($target))
		{
			$res = @rmdir($target);
		}
		else
		{
			$res = @unlink($target);
		}

		// Invalid symlinks on WIndows are not reported as directories but require @rmdir to delete them nonetheless.
		if (!$res && $isWindows)
		{
			$res = @rmdir($target);
		}

		// Is this is an actual directory, not an old symlink, by any chance?
		if (!$res && is_dir($target))
		{
			$res = self::recursiveUnlink($target);
		}

		return $res;
	}
}