<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

define('IS_WINDOWS', substr(PHP_OS, 0, 3) == 'WIN');

require_once 'phing/Task.php';

/**
 * This task will add any new file to the version control
 */
class SvnaddTask extends ExecTask
{
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
	 * Main entry point for task
	 */
	public function main()
	{
		$cwd               = getcwd();
		$this->workingCopy = realpath($this->workingCopy);

		chdir($this->workingCopy);
		// The same command is ran two times to avoid errors if no files were added
		exec('svn status | grep -v "^.[ \t]*\..*" | grep "^?" && svn status | grep -v "^.[ \t]*\..*" | grep "^?" | awk \'{print $2}\' | xargs svn add', $out);
		chdir($cwd);

		$this->project->setProperty('svn.output', "Added files: ".implode("\r\n", $out));
	}
}