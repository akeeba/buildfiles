<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Move the plugin and module language files into the /translations folder of the repository
 */

function moveTheFiles($root, $target)
{
	foreach (new DirectoryIterator($root) as $oArea)
	{
		if (!$oArea->isDir())
		{
			continue;
		}
		if ($oArea->isDot())
		{
			continue;
		}
		$area = $oArea->getFilename();

		$areaDir = $root . '/' . $area;
		@mkdir($target . '/' . $area, 0755, true);

		foreach (new DirectoryIterator($areaDir) as $oModule)
		{
			if (!$oModule->isDir())
			{
				continue;
			}
			if ($oModule->isDot())
			{
				continue;
			}
			$module = $oModule->getFilename();

			$moduleDir = $areaDir . '/' . $module;
			@mkdir($target . '/' . $area . '/' . $module);

			$files = array();

			foreach (new DirectoryIterator($moduleDir) as $oFile)
			{
				$filename = $oFile->getFilename();
				if ($oFile->isDot())
				{
					continue;
				}
				if ($oFile->isDir())
				{
					if ($filename != 'translation')
					{
						continue;
					}
					$transDir = $moduleDir . '/' . $filename;
					foreach (new DirectoryIterator($transDir) as $oTransFile)
					{
						if (!$oTransFile->isFile())
						{
							continue;
						}
						if ($oTransFile->isLink())
						{
							continue;
						}
						$f = $oTransFile->getFilename();
						if (substr($f, -4) != '.ini')
						{
							continue;
						}
						$tag = substr($f, 0, 5);
						$files[] = array(
							'from' => $moduleDir . '/translation/' . $f,
							'to'   => $target . '/' . $area . '/' . $module . '/' . $tag . '/' . $f
						);
					}
				}

				if (!$oFile->isFile())
				{
					continue;
				}
				if ($oFile->isLink())
				{
					continue;
				}
				if (substr($filename, -4) != '.ini')
				{
					continue;
				}
				$tag = substr($f, 0, 5);
				$files[] = array(
					'from' => $moduleDir . '/' . $filename,
					'to'   => $target . '/' . $area . '/' . $module . '/' . $tag . '/' . $filename
				);
			}

			foreach ($files as $file)
			{
				echo basename($file['from']) . "\n";
				@mkdir(dirname($file['to']), 0755, true);
				@copy($file['from'], $file['to']);
				@unlink($file['from']);
			}
		}
	}
}

$root = dirname(__FILE__) . '/modules';
$target = dirname(__FILE__) . '/translations/modules';
moveTheFiles($root, $target);

$root = dirname(__FILE__) . '/plugins';
$target = dirname(__FILE__) . '/translations/plugins';
moveTheFiles($root, $target);
