<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace tasks;

use Phing\Exception\BuildException;
use Phing\Io\File;
use Phing\Project;
use Phing\Task;
use Phing\Type\FileSet;

/**
 * Part of the Phing tasks collection by Ryan Chouinard.
 *
 * @copyright Copyright (c) 2010 Ryan Chouinard
 * @license   New BSD License
 * @author    Ryan Chouinard <rchouinard@gmail.com>
 * @deprecated
 */
class YuiCompressorTask extends Task
{

	/**
	 * @var string
	 */
	protected $_javaPath;

	/**
	 * @var PhingFile
	 */
	protected $_jarPath;

	/**
	 * @var PhingFile
	 */
	protected $_targetDir;

	/**
	 * @var array
	 */
	protected $_fileSets;

	/**
	 * @return void
	 */
	public function __construct()
	{
		$defaultJarPath = realpath(
			__DIR__ . '/library/yui-compressor.jar'
		);

		$this->_javaPath = 'java';
		$this->_jarPath  = new File($defaultJarPath);
		$this->_fileSets = [];

		parent::__construct();
	}

	/**
	 * @return boolean
	 */
	public function init()
	{
		return true;
	}

	/**
	 * @return void
	 */
	public function main()
	{
		$this->_checkJarPath();
		$this->_checkTargetDir();

		/* @var $fileSet FileSet */
		foreach ($this->_fileSets as $fileSet)
		{

			$files = $fileSet->getDirectoryScanner($this->project)->getIncludedFiles();

			foreach ($files as $file)
			{

				$targetDir = new File($this->_targetDir, dirname($file));
				if (!$targetDir->exists())
				{
					$targetDir->mkdirs();
				}
				unset ($targetDir);

				$targetFilename = $file;

				if ((substr($targetFilename, -4) == '.css') && (substr($targetFilename, -8) != '.min.css'))
				{
					$targetFilename = substr($targetFilename, 0, -4) . '.min.css';
				}
				elseif ((substr($targetFilename, -3) == '.js') && (substr($targetFilename, -7) != '.min.js'))
				{
					$targetFilename = substr($targetFilename, 0, -3) . '.min.js';
				}

				$source = new File($fileSet->getDir($this->project), $file);
				$target = new File($this->_targetDir, $targetFilename);

				$this->log("Processing {$file}");
				$cmd = escapeshellcmd($this->_javaPath) . ' -jar ' . escapeshellarg($this->_jarPath) . ' -o '
				       . escapeshellarg($target->getAbsolutePath()) . ' ' . escapeshellarg($source->getAbsolutePath());
				$this->log('Executing: ' . $cmd);
				$this->log('Executing: ' . $cmd, Project::MSG_DEBUG);
				@exec($cmd, $output, $return);

				if ($return !== 0)
				{
					$this->log("Failed processing {$file}!", Project::MSG_ERR);
				}
			}
		}
	}

	/**
	 * @return FileSet
	 */
	public function createFileSet()
	{
		$num = array_push($this->_fileSets, new FileSet());

		return $this->_fileSets[$num - 1];
	}

	public function setJarPath(File $path)
	{
		$this->_jarPath = $path;
	}

	protected function _checkJarPath()
	{
		if ($this->_jarPath === null)
		{
			throw new BuildException('Path to YUI compressor jar file must be specified');
		}
		elseif (!$this->_jarPath->exists())
		{
			throw new BuildException('Unable to locate jar file at specified path');
		}
	}

	public function setTargetDir(File $path)
	{
		$this->_targetDir = $path;
	}

	/**
	 * @return void
	 */
	protected function _checkTargetDir()
	{
		if ($this->_targetDir === null)
		{
			throw new BuildException('Target directory must be specified');
		}
	}

}
