<?php
require_once 'phing/Task.php';
require_once 'phing/tasks/ext/svn/SvnBaseTask.php';

class GitDateTask extends SvnBaseTask
{
    private $propertyName = "git.date";

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
     * The main entry point
     *
     * @throws BuildException
     */
    function main()
    {
		$this->setup('info');
		
		if($this->workingCopy == '..') $this->workingCopy = '../';
		
		exec('git log --format=%at -n1 '.escapeshellarg($this->workingCopy), $out);
		$this->project->setProperty($this->getPropertyName(), strtoupper(trim($out[0])));
    }
}