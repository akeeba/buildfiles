<?php
require_once 'phing/Task.php';
require_once dirname(__FILE__).'/LinkTask.php';

/**
 * Class LinkLangTask
 *
 * Generates links for language files, based on a target / link combination.
 *
 * Single target link example:
 * <code>
 *     <linklang target="${dirs.root.abs}/translations/plugins" link="${dirs.root.abs}/plugins" />
 * </code>
 */
class LinkLangTask extends LinkTask
{
	/**
	 * Main entry point for task.
	 *
	 * @return 	bool
	 */
	public function main()
	{
		$map  = $this->getMap();
		$root = $this->getLink();
		$type = $this->getType();
		$target = $map;

		foreach(new DirectoryIterator($root) as $oArea)
		{
			if (!$oArea->isDir()) continue;
			if ($oArea->isDot())  continue;
			$area = $oArea->getFilename();
			$areaDir = $root.'/'.$area;

			$this->log("\t$area\n", Project::MSG_INFO);

			foreach(new DirectoryIterator($areaDir) as $oModule)
			{
				if (!$oModule->isDir()) continue;
				if ($oModule->isDot())  continue;

				$module = $oModule->getFilename();
				$moduleDir = $areaDir.'/'.$module;

				$this->log("\t\t$module", Project::MSG_INFO);

				$from = $target.'/'.$area.'/'.$module.'/en-GB';
				$to = $moduleDir.'/language/en-GB';

				if (!is_dir($from))
				{
					// Some things may be untranslated
					$this->log("\tNot translated\n", Project::MSG_ERR);
					continue;
				}

				$this->doLink($from, $to, $type);
			}
		}

		return true;
	}
}