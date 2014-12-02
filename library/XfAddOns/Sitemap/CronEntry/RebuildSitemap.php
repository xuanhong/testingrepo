<?php

/**
 * Nightly rebuild of the sitemap for the site
 */
class XfAddOns_Sitemap_CronEntry_RebuildSitemap
{

	/**
	 * Static method called by the Cron Engine
	 */
	public static function run()
	{
		// disable limits if possible
		@set_time_limit(0);

		// start running
		if (!class_exists('DomDocument'))
		{
			throw new Exception('You don\'t seem to have the DOM module installed. Cannot continue');
		}

		/* @var $sitemap XfAddOns_Sitemap_Model_Sitemap */
		$sitemap = XenForo_Model::create('XfAddOns_Sitemap_Model_Sitemap');
		if (!$sitemap->isDirectoryWritable())
		{
			$directory = $sitemap->getBaseDirectory();
			throw new Exception('The path ' . $directory . ' is not writable. Maybe you need to chmod 777');
		}

		// most likely we run through the admin, so check if we don't have the route filters
		if (!XenForo_Application::isRegistered('routeFiltersOut'))
		{
			/* @var $registryModel XenForo_Model_DataRegistry  */
			$registryModel = XenForo_Model::create('XenForo_Model_DataRegistry');
			$routeFiltersOut = $registryModel->get('routeFiltersOut');
			XenForo_Application::set('routeFiltersOut', $routeFiltersOut);
		}
		
		$sitemap->runAllAvailableSiteMaps();
	}


}