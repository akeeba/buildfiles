<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

//require_once 'phing/Task.php';

/**
 * Git latest tree hash to Phing property
 *
 * @version   $Id$
 * @package   akeebabuilder
 * @copyright Copyright (c)2010-2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU GPL version 3 or, at your option, any later version
 * @author    nicholas
 */
class AutoVersionTask extends Task
{
	/**
	 * The name of the Phing property to set
	 *
	 * @var   string
	 */
	private $propertyName = 'auto.version';

	/**
	 * The working copy of the Git repository.
	 *
	 * @var   string
	 */
	private $workingCopy;

	/**
	 * The path to the CHANGELOG file
	 *
	 * @var string
	 */
	private $changelog;

	public function setWorkingCopy(string $workingCopy): void
	{
		$this->workingCopy = $workingCopy;
	}

	public function getWorkingCopy(): string
	{
		return $this->workingCopy;
	}

	function setPropertyName(string $propertyName): void
	{
		$this->propertyName = $propertyName;
	}

	function getPropertyName(): string
	{
		return $this->propertyName ?: 'auto.version';
	}

	public function setChangelog(string $path): void
	{
		$this->changelog = $path;
	}

	public function getChangelog(): string
	{
		return $this->changelog;
	}

	/**
	 * The main entry point
	 *
	 * @throws  BuildException
	 */
	public function main()
	{
		// Get the version from the changelog or the last git tag
		if ($version = $this->getChangelogVersion() ?: $this->getLatestGitTag())
		{
			$version = $this->bumpVersion($version);
		}
		else
		{
			$version = $this->getFakeVersion();
		}

		$this->project->setProperty($this->getPropertyName(), $version);
	}

	private function getChangelogVersion(): ?string
	{
		// If no CHANGELOG is set up try to detect the correct one.
		if (empty($this->changelog))
		{
			$rootDir = rtrim($this->project->getProperty('dirs.root'), '/' . DIRECTORY_SEPARATOR);
			$changeLogs = [
				'CHANGELOG',
				'CHANGELOG.md',
				'CHANGELOG.php',
				'CHANGELOG.txt',
			];

			foreach ($changeLogs as $possibleFile)
			{
				$possibleFile = $rootDir . '/' . $possibleFile;

				if (@file_exists($possibleFile))
				{
					$this->changelog = $possibleFile;
				}
			}
		}

		// No changelog specified? Bummer.
		if (empty($this->changelog))
		{
			return null;
		}

		// Get the contents of the changelog.
		$content = @file_get_contents($this->changelog);

		if (empty($content))
		{
			return null;
		}

		// Remove a leading die() statement
		$lines = array_map('trim', explode("\n", $content));

		if (strpos($lines[0], '<?') !== false)
		{
			array_shift($lines);
		}

		// Remove empty lines
		$lines = array_filter($lines, function ($x) {
			return !empty($x);
		});

		// The first line should be "Something something something VERSION" or just "VERSION"
		$firstLine = array_shift($lines);

		if (!preg_match('/((\d+\.?)+)(((a|alpha|b|beta|rc|dev)\d)*(-[^\s]*)?)?/', $firstLine, $matches))
		{
			return null;
		}

		$version = $matches[0];

		if (is_array($version))
		{
			$version = array_shift($version);
		}

		return $version;
	}

	private function getLatestGitTag(): ?string
	{
		$workingCopy = $this->workingCopy ?: $this->project->getProperty('dirs.root') ?: '../';

		if ($workingCopy == '..')
		{
			$workingCopy = '../';
		}

		$cwd         = getcwd();
		$workingCopy = realpath($workingCopy);

		chdir($workingCopy);
		exec('git describe --abbrev=0 --tags', $out);
		chdir($cwd);

		if (empty($out))
		{
			return null;
		}

		return ltrim(trim($out[0]), 'v.');
}

	private function getFakeVersion(): string
	{
		$workingCopy = $this->workingCopy ?: $this->project->getProperty('dirs.root') ?: '../';

		if ($workingCopy == '..')
		{
			$workingCopy = '../';
		}

		$cwd         = getcwd();
		$workingCopy = realpath($workingCopy);

		chdir($workingCopy);
		exec('git log --format=%h -n1', $out);
		chdir($cwd);

		if (empty($out))
		{
			return 'dev' . gmdate('YmdHi');
		}

		return 'rev' . trim($out[0]);
	}

	private function bumpVersion(string $version): string
	{
		$devSuffix = '-dev' . gmdate('YmdHi');

		if (!preg_match('/((\d+\.?)+)(((a|alpha|b|beta|rc|dev)\d)*(-[^\s]*)?)?/', $version, $matches))
		{
			return $version . $devSuffix;
		}

		$mainVersion = rtrim($matches[1], '.');
		$stability   = $matches[4];
		$patch       = ltrim($matches[6], '-');

		if (empty($stability) && preg_match('/(a|alpha|b|beta|rc|dev)\d/', $patch))
		{
			$stability = $patch;
			$patch     = '';
		}

		// If the patch starts with dev, rev, git, svn replace it and return
		if (!empty($patch) && (strlen($patch) >= 3) && in_array(substr($patch, 0, 3), ['dev','rev','git','svn']))
		{
			return $mainVersion .
				(empty($stability) ? '' : ('.' . $stability)) .
				$devSuffix;
		}

		// If we have an unstable release bump the alpha/beta/rc level and remove the patch level
		if (!empty($stability))
		{
			preg_match('/(a|alpha|b|beta|rc|dev)(\d)/', $stability, $matches);
			$prefix    = $matches[1];
			$revision  = (int) ($matches[2] ?: 0);
			$stability = $prefix . ++$revision;
			$patch     = '';
		}
		// TODO Otherwise, increase the sub–minor version
		else
		{
			$bits = explode('.', $mainVersion);

			while (count($bits) < 3)
			{
				$bits[] = 0;
			}

			$bits[2]++;

			$mainVersion = implode('.', $bits);
		}

		return $mainVersion .
			(empty($stability) ? '' : ('.' . $stability)) .
			$devSuffix;
	}
}
