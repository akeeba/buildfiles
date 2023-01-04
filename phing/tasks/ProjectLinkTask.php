<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

//require_once 'phing/Task.php';
require_once __DIR__ . '/../../linklib/include.php';

/**
 * Class InternalLinkTask
 *
 * Performs internal relinking.
 *
 * Example:
 *
 * <projectlink repository="/path/to/repository" />
 */
class ProjectLinkTask extends Task
{
	/**
	 * The path to the repository containing all the extensions
	 *
	 * @var   string
	 */
	private $repository = null;

	/**
	 * Set the repository root folder
	 *
	 * @param   string  $repository  The new repository root folder
	 *
	 * @return  void
	 */
	public function setRepository(string $repository)
	{
		$this->repository = $repository;
	}

	/**
	 * Main entry point for task.
	 *
	 * @return    bool
	 */
	public function main()
	{
		$this->log("Relinking " . $this->repository, Project::MSG_INFO);

		if (empty($this->repository))
		{
			$this->repository = realpath($this->project->getBasedir() . '/../..');
		}

		if (!is_dir($this->repository))
		{
			throw new BuildException("Repository folder {$this->repository} is not a valid directory");
		}

		$linker = new \Akeeba\LinkLibrary\ProjectLinker($this->repository);

		try
		{
			$linker->addInternalLanguageMapping();
		}
		catch (RuntimeException $e)
		{
			// If you run this before `phing git` creates the XML manifest this fails. So we need to swallow the error.
		}

		$linker->link();

		return true;
	}


}
