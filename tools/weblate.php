<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Akeeba Build Tools - Weblate component JSON exporter
 */

use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;
use Cz\Git\GitRepository;
use GetOptionKit\OptionResult;

require_once __DIR__ . '/../vendor/autoload.php';

$year = gmdate('Y');
echo <<<ENDBANNER
Akeeba Build Tools - Weblate component JSON exporter 1.0
Creates a JSON file which lets you import the translations in Weblate as components 
-------------------------------------------------------------------------------
Copyright ©2010-$year Akeeba Ltd
Distributed under the GNU General Public License v3 or later
-------------------------------------------------------------------------------

ENDBANNER;

class OwnGitRepository extends GitRepository
{
	public function getRemoteUrl($name, $push = false)
	{
		$flags = $push ? '--push' : '';

		$command = self::processCommand(["git remote get-url $flags", $name]);

		return $this->extractFromCommand($command);
	}

	public function listRemotes()
	{
		return $this->extractFromCommand('git remote', 'trim');
	}

}

class RepoInfo
{
	private $path;

	private $organization;

	private $repository;

	private $branch;

	public function __construct($path = null)
	{
		if (empty($path))
		{
			$path = getcwd();
		}

		$this->path = $path;

		$this->scan();
	}

	public function scan()
	{
		$repo         = new OwnGitRepository($this->path);
		$remotes      = $repo->listRemotes();
		$remote       = $remotes[0];
		$urls         = $repo->getRemoteUrl($remote);
		$url          = $urls[0];
		$this->branch = $repo->getCurrentBranchName();

		if (strpos($url, 'github.com') === false)
		{
			throw new RuntimeException('The repository must be hosted on GitHub for this script to work');
		}

		$this->parseUrl($url);
	}

	protected function parseUrl($url)
	{
		list ($junk, $repoPath) = explode('github.com', $url);
		$repoPath = ltrim($repoPath, ':/');
		$repoPath = (substr($repoPath, -4) == '.git') ? substr($repoPath, 0, -4) : $repoPath;
		list($this->organization, $this->repository) = explode('/', $repoPath);
	}

	/**
	 * @return null|string
	 */
	public function getPath()
	{
		return $this->path;
	}

	/**
	 * @return mixed
	 */
	public function getOrganization()
	{
		return $this->organization;
	}

	/**
	 * @return mixed
	 */
	public function getRepository()
	{
		return $this->repository;
	}

	/**
	 * @return mixed
	 */
	public function getBranch()
	{
		return $this->branch;
	}
}

class Scanner
{
	private $repo;

	private $langDir = 'translations';

	private $cliOptions;

	public function __construct(RepoInfo $repo, OptionResult $cliOptions)
	{
		$this->repo       = $repo;
		$this->cliOptions = $cliOptions;
		$this->langDir    = $cliOptions->get('directory');
	}

	public function run()
	{
		$myRoot      = $this->repo->getPath() . '/' . $this->langDir;
		$returnArray = [];

		foreach (new DirectoryIterator($myRoot) as $oArea)
		{
			if (!$oArea->isDir() || $oArea->isDot())
			{
				continue;
			}

			$area = $oArea->getFilename();

			$areaDir = $myRoot . '/' . $area;
			$slug    = array();

			switch ($area)
			{
				case 'component':
					$slug[] = 'component';
					break;

				case 'modules':
					$slug[] = 'mod';
					break;

				case 'plugins':
					$slug[] = 'plg';
					break;

				default:
					break;
			}

			if (empty($slug))
			{
				continue;
			}

			foreach (new DirectoryIterator($areaDir) as $oFolder)
			{
				if (!$oFolder->isDir() || $oFolder->isDot())
				{
					continue;
				}

				$folder    = $oFolder->getFilename();
				$slug[]    = $folder;
				$folderDir = $areaDir . '/' . $folder;

				// Is this a component?
				if (is_dir($folderDir . '/en-GB'))
				{
					$returnArray = array_merge($returnArray, $this->generateObjectFor($slug, $folderDir));

					array_pop($slug);

					continue;
				}

				// Is this a module or plugin?
				foreach (new DirectoryIterator($folderDir) as $oExtension)
				{
					if (!$oExtension->isDir() || $oExtension->isDot())
					{
						continue;
					}

					$extension    = $oExtension->getFilename();
					$slug[]       = $extension;
					$extensionDir = $folderDir . '/' . $extension;

					if (is_dir($extensionDir . '/en-GB'))
					{
						$returnArray = array_merge($returnArray, $this->generateObjectFor($slug, $extensionDir));
					}

					array_pop($slug);
				}

				array_pop($slug);
			}
		}

		usort($returnArray, function ($a, $b) {
			if ($a['slug'] == $b['slug'])
			{
				return 0;
			}

			return $a['slug'] < $b['slug'] ? -1 : 1;
		});

		return $returnArray;
	}

	private function generateObjectFor($slugArray, $rootDir)
	{
		$root         = $this->repo->getPath();
		$masterTitle  = $this->slugToTitle($slugArray);
		$files        = glob($rootDir . '/en-GB/*.ini');
		$slug         = implode($slugArray, '_');
		$return       = [];
		$basePriority = 100;

		// Adjust the base priority depending on the kind of file: 120 for component, 100 for plugin, 80 for module
		if ($slugArray[0] == 'mod')
		{
			$basePriority -= 20;
		}
		elseif ($slugArray[0] == 'component')
		{
			$basePriority += 20;

			// Backend component files get an extra +20 priority points boost, making them top translation priority!
			if ($slugArray[1] == 'backend')
			{
				$basePriority += 20;
			}
		}

		foreach ($files as $f)
		{
			$priority = $basePriority;
			$title    = $masterTitle;

			// Adjust the priority depending on extension: -20 for .sys / .menu files.
			if (substr($f, -8) == '.sys.ini')
			{
				$title    .= ' (system language strings)';
				$slug     .= '_sys';
				$priority -= 20;
			}
			elseif (substr($f, -9) == '.menu.ini')
			{
				$title    .= ' (menu option strings)';
				$slug     .= '_menu';
				$priority -= 20;
			}

			$file_proto    = basename($f);
			$file_proto    = substr($file_proto, 5);
			$file_proto    = $rootDir . '/*/*' . $file_proto;
			$file_proto    = substr($file_proto, strlen($root) + 1);
			$file_template = str_replace('*', 'en-GB', $file_proto);

			$auth = '';

			if ($this->cliOptions->has('username') && $this->cliOptions->has('token'))
			{
				$auth = urlencode($this->cliOptions->get('username')) . ':' . urlencode($this->cliOptions->get('token')) . '@';
			}

			$gitHubRepoPath = $this->repo->getOrganization() . '/' . $this->repo->getRepository();
			$gitHubCloneURL = 'https://' . $auth . 'github.com/' . $gitHubRepoPath . '.git';
			$appName        = $this->cliOptions->get('weblate');

			// If we are told to use SSH for accessing git we ignore the authentication and use an SSH Git setup
			if ($this->cliOptions->get('gitssh'))
			{
				$gitHubCloneURL = 'git@github.com:' . $gitHubRepoPath . '.git';
			}

			$gitHubPushUrl            = $gitHubCloneURL;
			$reusedTranslationComponentRepo = $this->cliOptions->get('reuse');
			$project                  = $this->cliOptions->get('project');

			if ($reusedTranslationComponentRepo && $project)
			{
				// If the reused component is ourselves we have to ignore this option.
				$selfURI = 'weblate://' . $project . '/' . $slug;

				if ($selfURI != $reusedTranslationComponentRepo)
				{
					$gitHubCloneURL = $reusedTranslationComponentRepo;
					$gitHubPushUrl  = '';
				}
			}

			$return[] = [
				'name'                          => $title,
				'slug'                          => $slug,
				'vcs'                           => 'git',
				'repo'                          => $gitHubCloneURL,
				'push'                          => $gitHubPushUrl,
				'repoweb'                       => 'https://github.com/' . $gitHubRepoPath . '/blob/%(branch)s/%(file)s#L%(line)s',
				'branch'                        => $this->repo->getBranch(),
				'filemask'                      => $file_proto,
				'template'                      => $file_template,
				'allow_translation_propagation' => 1,
				'save_history'                  => 1,
				'enable_suggestions'            => 1,
				'suggestion_voting'             => 1,
				'suggestion_autoaccept'         => 2,
				'commit_message'                => 'Translated using ' . $appName . ' (%(language_name)s)\r\n\r\nCurrently translated at %(translated_percent)s%% (%(translated)s of %(total)s strings)\r\n\r\nTranslation: %(project)s/%(component)s\r\nTranslate-URL: %(url)s',
				#'comitter_email'                => $this->cliOptions->get('email'),
				#'comitter_name'                 => $appName,
				'file_format'                   => 'joomla',
				'license'                       => 'GNU GPL v3',
				'license_url'                   => 'https://www.gnu.org/licenses/gpl-3.0.en.html',
				'merge_style'                   => 'rebase',
				'new_lang'                      => 'contact',
				'edit_template'                 => 1,
				'add_message'                   => 'Added translation using ' . $appName . ' (%(language_name)s)',
				'delete_message'                => 'Deleted translation using ' . $appName . ' (%(language_name)s)',
				'priority'                      => $priority,
				'commit_pending_age'            => 24,
				'push_on_commit'                => 0,
			];
		}

		return $return;
	}

	private function slugToTitle(array $slugArray)
	{
		$return = "Language strings for " . implode(" ", $slugArray);

		switch ($slugArray[0])
		{
			case 'component':
				$return = 'Component';
				// 1 [component]: frontend, backend
				$return .= ' ' . ucfirst($slugArray[1]);
				// 2 [component]: n/a
				break;

			case 'mod':
			case 'module':
				// 1 [mod]: admin, site
				$return = ($slugArray[1] == 'site') ? 'Frontend' : 'Backend';
				$return .= ' Module ';
				// 2 [mod]: <module name>
				$return .= $slugArray[2];
				break;

			case 'plg':
			case 'plugin':
				// 1 [mod]: <folder>
				$return = ucfirst($slugArray[1]);
				$return .= ' Plugin ';
				// 2 [mod]: <module name>
				$return .= $slugArray[2];
				break;
		}

		return $return;
	}
}

$specs = new OptionCollection;
$specs->add('u|username:', 'GitHub username, required for committing language files.')
	->isa('String');
$specs->add('t|token:', 'GitHub personal access token, required for committing language files.')
	->isa('String');
$specs->add('e|email?', 'Translation committer email (default "noreply@example.com").')
	->isa('String')
	->defaultValue("noreply@example.com");
$specs->add('s|gitssh?', 'Use SSH to access Git.')
	->isa('Boolean')
	->defaultValue(false);
$specs->add('x|reuse?', 'Reuse a repository e.g. weblate://project/component')
	->isa('String')
	->defaultValue("");
$specs->add('p|project?', 'Weblate project, required when the --reuse option is set')
	->isa('String')
	->defaultValue("");
$specs->add('w|weblate?', 'Title of the Weblate installation and committer name (default "Weblate").')
	->isa('String')
	->defaultValue("Weblate");
$specs->add('o|output?', 'Output file (default "weblate.json").')
	->isa('String')
	->defaultValue("weblate.json");
$specs->add('r|repo?', 'Repository working copy to scan (default: current working directory).')
	->isa('String');
$specs->add('d|directory?', 'Language directory in the repository (default: "translations").')
	->isa('String')
	->defaultValue("translations");
$specs->add('m|merge?', "Merge the output with an existing JSON file")
	->isa('Boolean')
	->defaultValue(false);

try
{
	$parser = new OptionParser($specs);
	$result = $parser->parse($argv);
}
catch (Exception $e)
{
	echo $e->getMessage();
	exit (255);
}

if (!$result->count())
{
	$self = basename($argv[0]);
	echo <<< ERRORPREFIX

Usage:
	$self [arguments]

Available arguments:

ERRORPREFIX;

	$printer = new ConsoleOptionPrinter();

	echo $printer->render($parser->specs);

	exit(1);
}

try
{
	$repoInfo     = new RepoInfo($result->get('repo'));
	$scanner      = new Scanner($repoInfo, $result);
	$returnObject = $scanner->run();
	$outFile      = $result->get('output');

	if ($result->get('merge') && file_exists($outFile))
	{
		$temp = file_get_contents($outFile);
		$existing = json_decode($temp, true);

		if (is_array($existing))
		{
			$returnObject = array_merge_recursive($existing, $returnObject);
		}
	}

	$json = json_encode($returnObject, JSON_PRETTY_PRINT);

	file_put_contents($outFile, $json);
}
catch (Exception $e)
{
	echo $e->getMessage();
}
