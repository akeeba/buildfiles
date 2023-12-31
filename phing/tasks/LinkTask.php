<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

//require_once 'phing/Task.php';
//require_once 'phing/tasks/ext/SymlinkTask.php';
require_once __DIR__ . '/../../linklib/include.php';

/**
 * Class LinkTask
 *
 * Generates symlinks and hardlinks based on a target / link combination.
 * Can also individually link contents of a directory.
 *
 * Single target link example:
 * <code>
 *     <link target="/some/shared/file" link="${project.basedir}/htdocs/my_file" />
 * </code>
 *
 * Link entire contents of directory
 *
 * This will go through the contents of "/my/shared/library/*"
 * and create a link for each entry into ${project.basedir}/library/
 * <code>
 *     <link link="${project.basedir}/library">
 *         <fileset dir="/my/shared/library">
 *             <include name="*" />
 *         </fileset>
 *     </link>
 * </code>
 *
 * The type property can be used to override the link type, which is by default a
 * symlink. Example for a hardlink:
 * <code>
 *     <link target="/some/shared/file" link="${project.basedir}/htdocs/my_file" type="hardlink" />
 * </code>
 */
class LinkTask extends SymlinkTask
{
	/**
	 * Link type.
	 *
	 * @var    string
	 */
	protected $type = 'symlink';

	/**
	 * Setter for _type.
	 *
	 * @param    string $type
	 */
	public function setType($type)
	{
		$this->type = $type;
	}

	/**
	 * Getter for _type.
	 *
	 * @return    string
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * Main entry point for task
	 *
	 * @return bool
	 */
	public function main()
	{
		$map  = $this->getMap();
		$to   = $this->getLink();
		$type = $this->getType();

		// Single file symlink
		if (is_string($map))
		{
			$from = $map;
			\Akeeba\LinkLibrary\LinkHelper::makeLink($from, $to, $type);

			return true;
		}

		// Multiple symlinks
		foreach ($map as $name => $from)
		{
			$realTo = $to . DIRECTORY_SEPARATOR . $name;
			\Akeeba\LinkLibrary\LinkHelper::makeLink($from, $realTo, $type);
		}

		return true;
	}
}
