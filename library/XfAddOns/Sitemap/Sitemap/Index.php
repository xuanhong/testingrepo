<?php

/**
 * This class generates the index. This is a little different from all the others in the sense that it is
 * a single container for the rest of the sitemaps
 */
class XfAddOns_Sitemap_Sitemap_Index extends XfAddOns_Sitemap_Sitemap_Base
{
	
	/**
	 * @var array
	 */
	private $sitemaps;
	
	/**
	 * Usually null, if set this will override the name of the sitemap that we generate.
	 * @var string
	 */
	private $sitemapName;
	
	/**
	 * Constructor. Initializes the list of sitemaps to include in the index
	 * @param array $sitemaps
	 */
	public function __construct($sitemaps, $sitemapName = null, $boardUrl = null)
	{
		parent::__construct('sitemapindex');
		$this->sitemaps = $sitemaps;
		$this->sitemapName = $sitemapName;
		
		$options = XenForo_Application::getOptions();
		$this->boardUrl = $boardUrl ? $boardUrl : $options->boardUrl;
	}
	
	/**
	 * Generate the index of the sitemap
	 *
	 */
	public function generate()
	{
		XfAddOns_Sitemap_Logger::debug('Generating index file...');
		$this->initialize();	
		foreach ($this->sitemaps as $loc)
		{
			$sitemapNode = $this->dom->createElement('sitemap');
			$this->root->appendChild($sitemapNode);
	
			$url = $this->boardUrl . '/' . $loc;
			$this->addNode($sitemapNode, 'loc', $url);
		}
		
		$indexName = $this->sitemapName ? $this->sitemapName : 'sitemap.xml';
		$this->save($this->sitemapDir . '/' . $indexName);
	}	
	
}