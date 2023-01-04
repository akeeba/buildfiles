<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

class DocBookToEpubTask extends Task
{
	/**
	 * Path where the DocBook XML Stylesheet distribution is stored
	 *
	 * @var   PhingFile
	 */
	protected $xsltRoot;

	/**
	 * The source DocBook XML file to convert
	 *
	 * @var   PhingFile
	 */
	protected $docBookFile;

	/**
	 * Path where the ePub content will be generated (the HTML files are created under its /OEBPS subfolder)
	 *
	 * @see   https://sourceforge.net/projects/docbook/files/epub3/
	 *
	 * @var   PhingFile
	 */
	protected $epubPath;

	/**
	 * Runs the XML to HTML file conversion step for a given category
	 *
	 * @return  void
	 */
	public function main()
	{
		// Load the XSLT filters
		$xslPath = $this->xsltRoot->getAbsolutePath() . '/epub3/chunk.xsl';
		$xslDoc  = new DOMDocument();

		$this->log(sprintf("Loading XSLT file %s", $xslPath), Project::MSG_INFO);

		if (!$xslDoc->load($xslPath))
		{
			throw new \RuntimeException(sprintf("Cannot load XSLT file %s", $xslPath));
		}

		// Load the XML document
		$xmlDoc = new DOMDocument();

		$source = $this->docBookFile->getAbsolutePath();

		if (!$xmlDoc->load($source, LIBXML_DTDATTR | LIBXML_NOENT | LIBXML_NONET | LIBXML_XINCLUDE))
		{
			throw new \RuntimeException(sprintf("Cannot load DocBook XML file %s", $source));
		}

		// Apply XInclude directives (include sub-files)
		$xmlDoc->xinclude(LIBXML_DTDATTR | LIBXML_NOENT | LIBXML_NONET | LIBXML_XINCLUDE);

		// Setup the XSLT processor
		$parameters = array(
			'base.dir'                      => $this->epubPath->getAbsolutePath() . '/OEBPS/',
			'epub.stylesheet'               => 'style.css',
			'body.start.indent'             => 0,
			'variablelist.term.break.after' => 1,
			'variablelist.term.separator'   => '&quot;&quot;',
			'variablelist.max.termlength'   => 12,
			'section.autolabel'             => 1,
			'toc.section.depth'             => 5,
			'highlight.source'              => 1,
			'paper.type'                    => 'A4',
		);

		$xslt = new XSLTProcessor();
		$xslt->importStylesheet($xslDoc);

		if (!$xslt->setParameter('', $parameters))
		{
			throw new \RuntimeException("Cannot set XSLTProcessor parameters");
		}

		// Process it!
		set_time_limit(0);

		$oldval = $xslt->setSecurityPrefs(XSL_SECPREF_NONE);
		$result = $xslt->transformToXml($xmlDoc);

		$xslt->setSecurityPrefs($oldval);

		unset($xslt);

		if ($result === false)
		{
			throw new \RuntimeException(sprintf("Failed to process DocBook XML file %s", $source));
		}
	}

	/**
	 * Set the xsltRoot property: absolute path with the DocBook XSL Stylesheets distribution
	 *
	 * @param   PhingFile $xsltRoot
	 *
	 * @return  void
	 */
	public function setXsltRoot(PhingFile $xsltRoot)
	{
		$this->xsltRoot = $xsltRoot;
	}

	/**
	 * Set the source property: absolute filename of the DocBook XML file to process
	 *
	 * @param   PhingFile $docBookFile
	 *
	 * @return  void
	 */
	public function setDocBookFile(PhingFile $docBookFile)
	{
		$this->docBookFile = $docBookFile;
	}

	/**
	 * Set the target property: absolute filename of the resulting .fo file
	 *
	 * @param   PhingFile $epubPath
	 *
	 * @return  void
	 */
	public function setEpubPath(PhingFile $epubPath)
	{
		$this->epubPath = $epubPath;
	}
}
