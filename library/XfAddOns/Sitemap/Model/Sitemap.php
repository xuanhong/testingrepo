<?php

/**
 * Class used to generate the sitemap index. Usually, this is the entry point
 * for generating the site-wide sitemap, as this will split the information as needed
 * 
 * Extend this "model" if you need to add additional types, most likely you want to extend the "getAdditionalSitemaps" method
 */
class XfAddOns_Sitemap_Model_Sitemap
{

	/**
	 * Directory that will store the sitemaps
	 */
	protected $sitemapDir;

	/**
	 * Map to the options configuration, and holds the features that are enabled in the sitemap (e.g. threads, forums, members)
	 * @var array
	 */
	protected $enabledOptions;

	/**
	 * If true, after the sitemap has been generated, we should notify the services that there is a new sitemap available
	 * @var boolean
	 */
	protected $isPing;

	/**
	 * If true, a message will be added to the error log when the process completed
	 * @var boolean
	 */
	protected $logSuccess;

	/**
	 * Constructor.
	 * Initializes the map with the root set as sitemapindex
	 */
	public function __construct()
	{
		$options = XenForo_Application::getOptions();
		$this->enabledOptions = $options->xenforo_sitemap_enable;
		$this->isPing = $options->xenforo_sitemap_ping;
		$this->logSuccess = $options->xfa_sitemap_log_creation;
		$this->sitemapDir = $options->xenforo_sitemap_directory;
	}

	/**
	 * Returns a list of all the classes that must be run to generate a sitemap. This method will check for the basic ones, and
	 * add-ons can overrides this method to return a custom class to run
	 */
	protected function getSitemapClasses()
	{
		$options = XenForo_Application::getOptions();
		$ret = array();
		
		// Default forum sitemaps
		
		if ($this->enabledOptions['forums'])
		{
			$ret[] = 'XfAddOns_Sitemap_Sitemap_Forum';
		}
		if (isset($this->enabledOptions['pages']) && $this->enabledOptions['pages'])
		{
			$ret[] = 'XfAddOns_Sitemap_Sitemap_Page';
		}		
		if ($this->enabledOptions['threads'])
		{
			$ret[] = 'XfAddOns_Sitemap_Sitemap_Thread';
		}
		if ($this->enabledOptions['members'])
		{
			$ret[] = 'XfAddOns_Sitemap_Sitemap_Member';
		}
		if ($this->enabledOptions['forumsPagination'])
		{
			$ret[] = 'XfAddOns_Sitemap_Sitemap_ForumPagination';
		}
		if ($this->enabledOptions['threadsPagination'])
		{
			$ret[] = 'XfAddOns_Sitemap_Sitemap_ThreadPagination';
		}	
		if (!empty($options->xfa_sitemap_urls))
		{
			$ret[] = 'XfAddOns_Sitemap_Sitemap_AdditionalUrls';
		}
		
		// Resource Manager
		if (isset($options->xfa_sitemap_resources) && $options->xfa_sitemap_resources['resources'])
		{
			$ret[] = 'XfAddOns_Sitemap_Sitemap_ResourceManager';
		}
		if (isset($options->xfa_sitemap_resources) && $options->xfa_sitemap_resources['resource_updates'])
		{
			$ret[] = 'XfAddOns_Sitemap_Sitemap_ResourceManagerUpdates';
		}
		
		$additional = $this->getAdditionalSitemaps();
		if (is_array($additional))
		{
			$ret = array_merge($ret, $additional); 
		}
		return $ret;
	}

	/**
	 * This method is meant for add-ons to override.
	 * Return any additional sitemaps that are needed here.
	 * 
	 * Any Sitemap you do must have a generate() method, the sitemap will iterate over all the registered Sitemap classes
	 * and call "generate" on each of them
	 * 
	 * To be a good citizen this would something like
	 * 		$sitemaps = array();		// your array
	 * 		$sitemaps[] = 'MyCustom_Sitemap';
	 * 		return array_merge(parent::getAdditionalSitemaps(), $sitemaps);
	 */
	protected function getAdditionalSitemaps()
	{
		return array();
	}
	
	/**
	 * Generate the sitemap. This method will add the content for forums and threads
	 */
	public function runAllAvailableSiteMaps()
	{
		$allSitemaps = array();
		
		$classes = $this->getSitemapClasses();
		foreach ($classes as $klass)
		{
			$sitemaps = array();
			try
			{
				$delegate = new $klass;
				$sitemaps = $delegate->generate();
			}
			catch (Exception $ex)
			{
				XenForo_Error::logException($ex, false);				
			}
			
			if (is_array($sitemaps))
			{
				$allSitemaps = array_merge($allSitemaps, $sitemaps);
			}
		}
		
		// generate the index file
		$index = new XfAddOns_Sitemap_Sitemap_Index($allSitemaps);
		$index->generate();

		// ping Google with the new sitemap
		if ($this->isPing)
		{
			$this->pingServices();
		}

		// push it to the logs, so we can see that something happened
		if ($this->logSuccess)
		{
			XfAddOns_Sitemap_Logger::info('Sitemap has been generated');
		}
	}

	/**
	 * Ping the services with the newly generated sitemap
	 */
	private function pingServices()
	{
		$options = XenForo_Application::getOptions();
		$url = $options->boardUrl . '/' . $this->sitemapDir . '/sitemap.xml.gz';
		XfAddOns_Sitemap_Helper_Ping::pingGoogle($url);
		XfAddOns_Sitemap_Helper_Ping::pingBing($url);
	}

	/**
	 * Check if the directory in which the sitemap will be stored is writable and
	 * can store the sitemaps
	 * @return boolean
	 */
	public function isDirectoryWritable()
	{
		return is_writable($this->sitemapDir);
	}

	/**
	 * Returns the directory that we are trying to use to store the sitemaps. This method can be
	 * called for debugging the path in which the sitemap is going to be written
	 * @return string
	 */
	public function getBaseDirectory()
	{
		return getcwd() . '/' . $this->sitemapDir;
	}




}