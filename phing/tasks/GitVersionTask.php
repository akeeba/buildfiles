<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace tasks;

// Required for Zend Server 6 on Mac OS X
use Phing\Exception\BuildException;
use Phing\Task;

putenv("DYLD_LIBRARY_PATH=''");

/**
 * Git latest tree hash to Phing property
 *
 * @package   akeebabuilder
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU GPL version 3 or, at your option, any later version
 * @version   $Id$
 * @author    nicholas
 */
class GitVersionTask extends Task
{
	/**
	 * Git.date
	 *
	 * @var   string
	 */
	private $propertyName = "git.version";

	/**
	 * The working copy.
	 *
	 * @var   string
	 */
	private $workingCopy;

	/**
	 * Sets the path to the working copy
	 *
	 * @param   string  $workingCopy
	 */
	public function setWorkingCopy($workingCopy)
	{
		$this->workingCopy = $workingCopy;
	}

	/**
	 * Returns the path to the working copy
	 *
	 * @return  string
	 */
	public function getWorkingCopy()
	{
		return $this->workingCopy;
	}

	/**
	 * Sets the name of the property to use
	 *
	 * @param   string  $propertyName
	 */
	function setPropertyName($propertyName)
	{
		$this->propertyName = $propertyName;
	}

	/**
	 * Returns the name of the property to use
	 *
	 * @return  string
	 */
	function getPropertyName()
	{
		return $this->propertyName;
	}

	/**
	 * The main entry point
	 *
	 * @throws  BuildException
	 */
	function main()
	{
		if ($this->workingCopy == '..')
		{
			$this->workingCopy = '../';
		}

		$cwd               = getcwd();
		$this->workingCopy = realpath($this->workingCopy);

		chdir($this->workingCopy);
		exec('git log --format=%h -n1 ' . escapeshellarg(realpath($this->workingCopy)), $out);
		chdir($cwd);

		$this->project->setProperty($this->getPropertyName(), strtoupper(trim($out[0])));
	}
}
