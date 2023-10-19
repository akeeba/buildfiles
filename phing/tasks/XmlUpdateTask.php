<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Massages a Joomla XML update file to remove duplicate versions
 *
 * Example:
 *
 * ```xml
 * <xmlupdate xml="${update.all}" tofile="${dirs.root}/update/foobar.xml" />
 * ```
 */
class XmlUpdateTask extends Task
{

	private ?string $xml = null;

	private ?string $tofile = null;

	public function setXml(string $xml)
	{
		$this->xml = $xml;
	}

	public function setTofile(string $tofile)
	{
		$this->tofile = $tofile;
	}

	/**
	 * @inheritDoc
	 */
	public function main()
	{
		$xml = new DOMDocument();
		$xml->loadXML($this->xml);
		$versions = [];

		/** @var DOMNode $updateItem */
		foreach ($xml->documentElement->getElementsByTagName('update') as $updateItem)
		{
			/** @var DOMNode $subNode */
			foreach ($updateItem->childNodes as $subNode)
			{
				if ($subNode->nodeName === 'version')
				{
					$version    = trim($subNode->textContent);

					if (!in_array($version, $versions))
					{
						$versions[] = $version;
					}
					else
					{
						$xml->documentElement->removeChild($updateItem);
					}

					continue 2;
				}
			}
		}

		$generated = $xml->saveXML();

		$generated = preg_replace_callback(
			'#<updates>(.*)</updates>#s',
			function (array $matches) {
				$internal = str_replace("\n", "", $matches[0]);

				$internal = preg_replace('#\s{2,}#', ' ', $internal);
				$internal = preg_replace('#> <#', '><', $internal);

				return $internal;
			},
			$generated
		);

		file_put_contents($this->tofile, $generated);
	}
}