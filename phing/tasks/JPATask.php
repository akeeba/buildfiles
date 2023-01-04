<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

//require_once "phing/Task.php";
//require_once 'phing/tasks/system/MatchingTask.php';
//include_once 'phing/util/SourceFileScanner.php';
//include_once 'phing/mappers/MergeMapper.php';
//include_once 'phing/util/StringHelper.php';
require_once __DIR__ . '/library/jpa.php';
require_once __DIR__ . '/library/JPAFileSet.php';

/**
 * Creates a JPA v.1.0. archive
 */
class JpaTask extends MatchingTask
{
	/**
	 * The output file
	 *
	 * @var   PhingFile
	 */
	private $jpaFile;

	/**
	 * The directory that holds the data to include in the archive
	 *
	 * @var   PhingFile
	 */
	private $baseDir;

	/**
	 * File path prefix in JPA archive
	 *
	 * @var   string
	 */
	private $prefix = null;

	/**
	 * Should I include empty dirs in the archive.
	 *
	 * @var   bool
	 */
	private $includeEmpty = true;

	/**
	 * The filesets to include to the archive
	 *
	 * @var   array
	 */
	private $filesets = array();

	/**
	 * Add a new fileset.
	 *
	 * @return   JpaFileSet
	 */
	public function createFileSet()
	{
		$this->fileset    = new JpaFileSet();
		$this->filesets[] = $this->fileset;

		return $this->fileset;
	}

	/**
	 * Add a new fileset.
	 *
	 * @return   JpaFileSet
	 */
	public function createJpaFileSet()
	{
		$this->fileset    = new JpaFileSet();
		$this->filesets[] = $this->fileset;

		return $this->fileset;
	}

	/**
	 * Set the name/location of where to create the JPA file.
	 *
	 * @param   PhingFile  $destFile  The location of the output JPA file
	 */
	public function setDestFile(PhingFile $destFile)
	{
		$this->jpaFile = $destFile;
	}

	/**
	 * Set the include empty directories flag.
	 *
	 * @param   boolean  $bool  Should empty directories be included in the archive?
	 *
	 * @return  void
	 */
	public function setIncludeEmptyDirs($bool)
	{
		$this->includeEmpty = (boolean)$bool;
	}

	/**
	 * This is the base directory to look in for files to archive.
	 *
	 * @param   PhingFile  $baseDir  The base directory to scan
	 *
	 * @return  void
	 */
	public function setBasedir(PhingFile $baseDir)
	{
		$this->baseDir = $baseDir;
	}

	/**
	 * Sets the file path prefix for files in the JPA archive
	 *
	 * @param   string  $prefix  Prefix
	 *
	 * @return  void
	 */
	public function setPrefix(string $prefix)
	{
		$this->prefix = $prefix;
	}

	/**
	 * do the work
	 *
	 * @throws BuildException
	 */
	public function main()
	{
		if ($this->jpaFile === null)
		{
			throw new BuildException("jpafile attribute must be set!", $this->getLocation());
		}

		if ($this->jpaFile->exists() && $this->jpaFile->isDirectory())
		{
			throw new BuildException("jpafile is a directory!", $this->getLocation());
		}

		if ($this->jpaFile->exists() && !$this->jpaFile->canWrite())
		{
			throw new BuildException("Can not write to the specified jpafile!", $this->getLocation());
		}

		$savedFileSets = $this->filesets;

		try
		{
			if (empty($this->filesets))
			{
				throw new BuildException("You must supply some nested filesets.", $this->getLocation());
			}

			$this->log("Building JPA: " . $this->jpaFile->__toString(), Project::MSG_INFO);

			$jpa = new JPAMaker();
			$jpa->create($this->jpaFile->getAbsolutePath());

			foreach ($this->filesets as $fs)
			{
				$files     = $fs->getFiles($this->project, $this->includeEmpty);
				$fsBasedir = (null != $this->baseDir) ? $this->baseDir : $fs->getDir($this->project);

				foreach ($files as $file)
				{
					$f = new PhingFile($fsBasedir, $file);

					$pathInJPA = $this->prefix . $f->getPathWithoutBase($fsBasedir);
					$jpa->addFile($f->getPath(), $pathInJPA);
					$this->log("Adding " . $f->getPath() . " as " . $pathInJPA . " to archive.", Project::MSG_VERBOSE);
				}
			}

			$jpa->finalize();
		}
		catch (IOException $ioe)
		{
			$msg            = "Problem creating JPA: " . $ioe->getMessage();
			$this->filesets = $savedFileSets;

			throw new BuildException($msg, $ioe, $this->getLocation());
		}

		$this->filesets = $savedFileSets;
	}
}
