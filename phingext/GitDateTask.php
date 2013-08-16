<?php
require_once 'phing/Task.php';
require_once 'phing/tasks/ext/svn/SvnBaseTask.php';

class GitDateTask extends SvnBaseTask
{
	/**
	 * Git.date
	 *
	 * @var 	string
	 */
	private $propertyName = "git.date";

	/**
	 * The date format. Uses Unix timestamp by default.
	 *
	 * @var 	string
	 *
	 * @see		http://www.php.net/manual/en/function.date.php
	 */
	private $format = 'U';

	/**
	 * The working copy.
	 *
	 * @var		string
	 */
	private $workingCopy;

	/**
	 * Sets the name of the property to use
	 */
	function setPropertyName($propertyName)
	{
		$this->propertyName = $propertyName;
	}

	/**
	 * Returns the name of the property to use
	 */
	function getPropertyName()
	{
		return $this->propertyName;
	}

	/**
	 * Sets the path to the working copy
	 */
	function setWorkingCopy($wc)
	{
		$this->workingCopy = $wc;
	}

	/**
	 * Gets the date format
	 */
	function getFormat()
	{
		return $this->format;
	}

	/**
	 * Sets the date format
	 *
	 * @param 	$format
	 */
	function setFormat($format)
	{
		$this->format = $format;
	}

	/**
	 * The main entry point
	 *
	 * @throws BuildException
	 */
	function main()
	{
		$this->setup('info');

		if($this->workingCopy == '..') $this->workingCopy = '../';

		exec('git log --format=%at -n1 '.escapeshellarg($this->workingCopy), $timestamp);
		$date = date($this->format, trim($timestamp[0]));
		$this->project->setProperty($this->getPropertyName(), $date);
	}
}