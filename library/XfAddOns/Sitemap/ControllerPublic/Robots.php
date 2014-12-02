<?php

/**
 * What this controller does is, implement logic to dispatch the robots.txt file
 * We can invoke this with /xfa_robots usually, but most likely there is mapping from a file
 * on the root directory 
 */
class XfAddOns_Sitemap_ControllerPublic_Robots extends XenForo_ControllerPublic_Abstract
{

	/**
	 * Main index just outputs the robots.txt file
	 */
	public function actionIndex()
	{
		$options = XenForo_Application::getOptions();
		
		// setup all the data from the options
		$params['prefix'] = $options->useFriendlyUrls ? '/' : '/index.php?';
		$params['googleAdsense'] = $options->xfa_robots_googleAdsense;
		$params['attachments'] = $options->xfa_robots_attachments;
		$params['memberProfiles'] = $options->xfa_robots_memberProfiles;
		$params['profilePosts'] = $options->xfa_robots_profilePosts;
		$params['onlineUsers'] = $options->xfa_robots_onlineUsers;
		$params['recentActivity'] = $options->xfa_robots_recentActivity;
		
		$params['extraDisallow'] = array();
		if ($options->xfa_robots_extraDisallow)
		{
			$params['extraDisallow'] = preg_split("/[\r\n]+/", $options->xfa_robots_extraDisallow);
		}
		
		// Any additional rule at the end
		$params['extraRules'] = $options->xfa_robots_extra;
		
		// the location for the sitemap is generated using the server name to deal with custom subdomains
		$params['sitemap'] = $this->getSitemapLocation();
		
		// set the header to plain
		$this->_response->setHeader('Content-Type', 'text/plain; charset=UTF-8', true);
		
		
		// we have to dispatch to a custom view, otherwise the Raw Renderer will not really parse the template
		return $this->responseView('XfAddOns_Sitemap_ViewPublic_Robots', 'xfa_robots', $params);
	}
	
	/**
	 * Return the location for the sitemap. This location is usually /sitemap/sitemap.xml.gz, but different add-ons may
	 * chage this functionality
	 */
	public function getSitemapLocation()
	{
		$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
		$fileExtension = function_exists('gzopen') ? '.xml.gz' : '.xml';
		return $protocol . '://' . $_SERVER['HTTP_HOST'] . '/sitemap/sitemap' . $fileExtension;
	}
	
	
	/**
	 * This is what will be returned on the "online users" screen when the person queries it
	 * @param array $activities
	 * @return multitype:XenForo_Phrase
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		// generate the output
		$output = array();
		foreach ($activities AS $key => $activity)
		{
			$output[$key] = new XenForo_Phrase('xfa_sitemap_viewing_robots_txt');
		}
		return $output;
	}
	
	
	
	
	
}