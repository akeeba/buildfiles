<?php
/**
 * Akeeba Build Files
 *
 * @package    buildfiles
 * @copyright  (c) 2010-2017 Akeeba Ltd
 */

require_once __DIR__ . '/JPAFileSet.php';

/**
 * This is a FileSet with the ability to specify permissions.
 */
class ZipmeFileSet extends JpaFileSet
{
	/**
	 * The files to include in the archive
	 *
	 * @var   null|array
	 */
	private $files = null;

	/**
	 * Constructor
	 *
	 * @param   FileSet  $fileset
	 */
	public function __construct(FileSet $fileset = null)
	{
		parent::__construct($fileset);

		/**
		 * Expand/dereference symbolic links in order to include nested symlinks inside the ZIP archive. This is also
		 * required to prevent archiving unwanted files. Otherwise symlinked files are detected as directories and our
		 * checks within main() fails!
		*/
		$this->setExpandSymbolicLinks(true);
	}
}