<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

class WordPressEntryPointTask extends Task
{
	private $file = '';

	protected $name = null;

	protected $version = null;

	/**
	 * @inheritDoc
	 */
	public function main()
	{
		// Make sure we are given a file and that it exists.
		if (empty($this->file))
		{
			throw new BuildException('You must specify a WordPress entry point file to modify.');
		}

		if (!is_file($this->file))
		{
			throw new BuildException(sprintf('%s is not a file.', $this->file));
		}

		// Get the replacements
		$replacements = $this->getReplacements();

		if (empty($replacements))
		{
			return;
		}

		// Read the file
		$contents = @file_get_contents($this->file);

		if ($contents === false)
		{
			throw new BuildException(sprintf('%s cannot be read from.', $this->file));
		}

		// Replace the contents in the file
		$contents = $this->replace($contents, $replacements);

		// Write the results back to the file
		$result = @file_put_contents($this->file, $contents);

		if ($result === false)
		{
			throw new BuildException(sprintf('%s cannot be written to.', $this->file));
		}
	}

	public function setFile(string $file)
	{
		$this->file = $file;
	}

	public function setVersion(string $version)
	{
		$this->version = $version;
	}

	public function setName(string $name)
	{
		$this->name = $name;
	}

	private function getReplacements(): array
	{
		$ret = [];

		if ($this->name !== null)
		{
			$ret['Plugin Name'] = $this->name;
		}

		if ($this->version !== null)
		{
			$ret['Version'] = $this->version;
		}

		return $ret;
	}

	private function replace(string $contents, array $replacements)
	{
		$lines = array_map(
			function (string $line) use ($replacements): string
			{
				foreach ($replacements as $key => $newValue)
				{
					$test = $key . ':';

					if (strpos($line, $test) === 0)
					{
						return sprintf('%s: %s', $key, $newValue);
					}
				}

				return $line;
			},
			explode("\n", $contents)
		);

		return implode("\n", $lines);
	}

}