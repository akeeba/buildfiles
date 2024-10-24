<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace tasks;

use DirectoryIterator;
use Phing\Exception\BuildException;
use Phing\Project;
use Phing\Task;

/**
 * Class XmlVersionTask
 *
 * Changes the version and date of XML manifest files for Joomla extensions
 *
 * Example:
 *
 * <xlversion repository="/path/to/repository" version="1.2.3" date="2019-03-05" />
 */
class XmlVersionTask extends Task
{
	/**
	 * The path to the repository containing all the extensions
	 *
	 * @var   string
	 */
	private $repository = null;

	private $version = null;

	private $date = null;

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

	public function setVersion(string $version)
	{
		$this->version = $version;
	}

	public function setDate(string $date)
	{
		$this->date = $date;
	}

	private function scan($baseDir, $level = 0)
	{
		if (!is_dir($baseDir))
		{
			return;
		}

		$di = new DirectoryIterator($baseDir);

		/** @var DirectoryIterator $entry */
		foreach ($di as $entry)
		{
			if ($entry->isDot())
			{
				continue;
			}

			if ($entry->isLink())
			{
				continue;
			}

			if ($entry->isDir())
			{
				if ($level < 4)
				{
					$this->scan($entry->getPathname(), $level + 1);
				}

				continue;
			}

			if (!$entry->isFile() || !$entry->isReadable())
			{
				continue;
			}

			switch ($entry->getExtension())
			{
				case 'xml':
					echo $entry->getPathname();

					$result = $this->convert($entry->getPathname());

					echo $result ? "  -- CONVERTED\n" : "  -- (invalid)\n";
					break;

				case 'json':
					if ($entry->getBasename() != 'joomla.asset.json')
					{
						continue 2;
					}

					echo $entry->getPathname();

					$result = $this->convertJSON($entry->getPathname());

					echo $result ? "  -- CONVERTED\n" : "  -- (invalid)\n";
					break;

				default:
					continue 2;
			}

		}
	}

	private function convert($filePath)
	{
		$fileData = file_get_contents($filePath);

		if (strpos($fileData, '<extension ') === false)
		{
			return false;
		}

		$pattern     = '#<creationDate>.*</creationDate>#';
		$replacement = "<creationDate>{$this->date}</creationDate>";
		$fileData    = preg_replace($pattern, $replacement, $fileData);

		if (is_null($fileData))
		{
			return false;
		}

		$pattern     = '#<version>.*</version>#';
		$replacement = "<version>{$this->version}</version>";
		$fileData    = preg_replace($pattern, $replacement, $fileData);

		if (is_null($fileData))
		{
			return false;
		}

		file_put_contents($filePath, $fileData);

		return true;
	}

	private function convertJSON(string $filePath)
	{
		$fileData = @file($filePath);

		$fileData = array_map(
			function ($line) {
				if ((strpos(trim($line), '"version"') === 0)
				    && (strpos($line, ':') !== false))
				{
					$parts    = explode(':', $line, 2);
					$parts[1] = sprintf('"%s",', $this->version);
					$line     = implode(': ', $parts);
				}

				return rtrim($line, "\n\r");
			}, $fileData
		);

		file_put_contents($filePath, implode("\n", $fileData));

		return true;
	}

	/**
	 * Main entry point for task.
	 *
	 * @return    bool
	 */
	public function main()
	{
		$this->log("Modifying XML manifests under " . $this->repository, Project::MSG_INFO);

		if (empty($this->repository))
		{
			$this->repository = realpath($this->project->getBasedir() . '/../..');
		}

		if (!is_dir($this->repository))
		{
			throw new BuildException("Repository folder {$this->repository} is not a valid directory");
		}

		$paths = [
			realpath($this->repository) . '/component',
			realpath($this->repository) . '/plugins',
			realpath($this->repository) . '/modules',
			realpath($this->repository) . '/templates/admin',
			realpath($this->repository) . '/templates/site',
		];

		array_walk($paths, [$this, 'scan']);

		return true;
	}

}
