<?php

/**
 * Class used to generate the sitemap contents for forums
 */
class XfAddOns_Sitemap_Sitemap_AdditionalUrls extends XfAddOns_Sitemap_Sitemap_Base
{

	/**
	 * We have a text field in the options, with additional urls that can be included in the sitemap
	 */
	public function generate()
	{
		$ret = array();
		$this->generateStep();
		if (!$this->isEmpty)
		{
			$ret[] = $this->save($this->getSitemapName('urls'));
		}
		return $ret;
	}	
	
	/**
	 * Append the information about the forums to the sitemap
	 */
	protected function generateStep()
	{
		$this->initialize();
		$options = XenForo_Application::getOptions();

		$urls = preg_split('/[\r\n]+/', $options->xfa_sitemap_urls);
		foreach ($urls as $url)
		{
			$url = trim($url);
			$this->addUrl($url, XenForo_Application::$time);
		}
	}

}