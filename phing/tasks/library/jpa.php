<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

define('_JPA_MAJOR', 1); // JPA Format major version number
define('_JPA_MINOR', 0); // JPA Format minor version number

/**
 * Creates JPA archives
 */
class JPAMaker
{
	/**
	 * Full pathname to the archive file
	 *
	 * @var   string
	 */
	private $dataFileName;

	/**
	 * Number of files in the archive
	 *
	 * @var   int
	 */
	private $fileCount;

	/**
	 * Size in bytes of the data read from the filesystem
	 *
	 * @var   int
	 */
	private $uncompressedSize;

	/**
	 * Size in bytes of the data stored in the archive
	 *
	 * @var   int
	 */
	private $compressedSize;

	/**
	 * File names to never include in the archive (case sensitive)
	 *
	 * @var   array
	 */
	private $ignoredFiles = ['.gitignore', '.ds_store', 'thumbs.db', '.DS_Store'];

	/**
	 * Standard header signature
	 */
	const standardHeaderSignature = "\x4A\x50\x41";

	/**
	 * Entity Block signature
	 */
	const entityHeaderSignature = "\x4A\x50\x46";


	/**
	 * Creates or overwrites a new JPA archive
	 *
	 * @param   string  $archive  The full path to the JPA archive
	 *
	 * @return  void
	 */
	public function create($archive)
	{
		$this->dataFileName = $archive;

		// Try to kill the archive if it exists
		if (file_exists($this->dataFileName))
		{
			@unlink($this->dataFileName);
		}

		@touch($this->dataFileName);

		$fp = @fopen($this->dataFileName, "wb");

		if ($fp !== false)
		{
			@ftruncate($fp, 0);
			@fclose($fp);
		}

		if (!is_writable($archive))
		{
			throw new RuntimeException("Can't open $archive for writing");
		}

		// Write the initial instance of the archive header
		$this->writeArchiveHeader();
	}

	/**
	 * Adds a file to the archive
	 *
	 * @param   string  $from  The full pathname to the file you want to add to the archive
	 * @param   string  $to    [optional] The relative pathname to store in the archive
	 *
	 * @return  void
	 */
	public function addFile($from, $to = null)
	{
		// Skip the following files: .gitignore, .DS_Store, Thumbs.db
		$basename = strtolower(basename($from));

		if (in_array($basename, array('.gitignore', '.ds_store', 'thumbs.db')))
		{
			return;
		}

		// See if it's a directory
		$isDir = is_dir($from);

		// Get real size before compression
		$fileSize = $isDir ? 0 : filesize($from);

		// Set the compression method
		$compressionMethod = function_exists('gzcompress') ? 1 : 0;

		// Decide if we will compress
		if ($isDir)
		{
			$compressionMethod = 0; // don't compress directories...
		}

		$storedName = empty($to) ? $from : $to;
		$storedName = self::TranslateWinPath($storedName);

		/* "Entity Description Block" segment. */
		// File size
		$unc_len = $fileSize;
		$c_len   = $unc_len;
		$storedName .= ($isDir) ? "/" : "";

		if ($compressionMethod == 1)
		{
			$udata = @file_get_contents($from);

			if ($udata === false)
			{
				throw new RuntimeException("File $from is unreadable");
			}

			// Proceed with compression
			$zdata = @gzcompress($udata);

			if ($zdata === false)
			{
				// If compression fails, let it behave like no compression was available
				$compressionMethod = 0;
			}
			else
			{
				unset($udata);
				$zdata = substr(substr($zdata, 0, strlen($zdata) - 4), 2);
				$c_len = strlen($zdata);
			}

		}

		$this->compressedSize += $c_len;
		$this->uncompressedSize += $fileSize;
		$this->fileCount++;

		// Get file permissions
		$perms = @fileperms($from);

		// Calculate Entity Description Block length
		$blockLength = 21 + strlen($storedName);

		// Open data file for output
		$fp = @fopen($this->dataFileName, "ab");

		if ($fp === false)
		{
			throw new RuntimeException("Could not open archive file '{$this->dataFileName}' for append!");
		}

		// Entity Description Block header
		$this->writeToArchive($fp, self::entityHeaderSignature);
		// Entity Description Block header length
		$this->writeToArchive($fp, pack('v', $blockLength));
		// Length of entity path
		$this->writeToArchive($fp, pack('v', strlen($storedName)));
		// Entity path
		$this->writeToArchive($fp, $storedName);
		// Entity type
		$this->writeToArchive($fp, pack('C', ($isDir ? 0 : 1)));
		// Compression method
		$this->writeToArchive($fp, pack('C', $compressionMethod));
		// Compressed size
		$this->writeToArchive($fp, pack('V', $c_len));
		// Uncompressed size
		$this->writeToArchive($fp, pack('V', $unc_len));
		// Entity permissions
		$this->writeToArchive($fp, pack('V', $perms));

		/* "File data" segment. */
		if ($compressionMethod == 1)
		{
			// Just dump the compressed data
			$this->writeToArchive($fp, $zdata);

			unset($zdata);
			fclose($fp);

			return;
		}

		if (!$isDir)
		{
			// Copy the file contents, ignore directories
			$zdatafp = @fopen($from, "rb");

			while (!feof($zdatafp))
			{
				$zdata = fread($zdatafp, 524288);
				$this->writeToArchive($fp, $zdata);
			}

			fclose($zdatafp);
		}

		fclose($fp);
	}

	/**
	 * Updates the Standard Header with current information
	 *
	 * @return  void
	 */
	public function finalize()
	{
		$this->writeArchiveHeader();
	}

	/**
	 * Outputs a Standard Header at the top of the file
	 *
	 * @return  void
	 */
	private function writeArchiveHeader()
	{
		$fp = @fopen($this->dataFileName, 'r+');

		if ($fp === false)
		{
			throw new RuntimeException('Could not open ' . $this->dataFileName . ' for writing. Check permissions and open_basedir restrictions.');
		}

		// ID string (JPA)
		$this->writeToArchive($fp, self::standardHeaderSignature);
		// Header length; fixed to 19 bytes
		$this->writeToArchive($fp, pack('v', 19));
		// Major version
		$this->writeToArchive($fp, pack('C', _JPA_MAJOR));
		// Minor version
		$this->writeToArchive($fp, pack('C', _JPA_MINOR));
		// File count
		$this->writeToArchive($fp, pack('V', $this->fileCount));
		// Size of files when extracted
		$this->writeToArchive($fp, pack('V', $this->uncompressedSize));
		// Size of files when stored
		$this->writeToArchive($fp, pack('V', $this->compressedSize));

		@fclose($fp);
	}

	/**
	 * Pure binary write to file
	 *
	 * @param   resource  $fp    Handle to a file
	 * @param   string    $data  The data to write to the file
	 *
	 * @return  void
	 */
	private function writeToArchive($fp, $data)
	{
		$len = strlen($data);
		$ret = fwrite($fp, $data, $len);

		if ($ret === false)
		{
			throw new RuntimeException("Can't write to archive");
		}
	}

	// Convert Windows paths to UNIX
	private static function TranslateWinPath($p_path)
	{
		if (stristr(php_uname(), 'windows'))
		{
			// Change potential windows directory separator
			if ((strpos($p_path, '\\') > 0) || (substr($p_path, 0, 1) == '\\'))
			{
				$p_path = strtr($p_path, '\\', '/');
			}
		}

		return $p_path;
	}

	/**
	 * Get the list of ignored files
	 *
	 * @return  array
	 */
	public function getIgnoredFiles(): array
	{
		return $this->ignoredFiles;
	}

	/**
	 * Set the list of ignored files
	 *
	 * @param   array  $ignoredFiles
	 */
	public function setIgnoredFiles(array $ignoredFiles)
	{
		$this->ignoredFiles = $ignoredFiles;
	}

	/**
	 * Add to the list of ignored files
	 *
	 * @param   array  $ignoredFiles
	 */
	public function addIgnoredFiles(array $ignoredFiles)
	{
		$this->ignoredFiles = array_merge($ignoredFiles, $this->ignoredFiles);
		$this->ignoredFiles = array_unique($this->ignoredFiles);
	}

}
