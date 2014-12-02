<?php

/**
 * This class is a delegate to actually downloading the sitemap.
 * While it is easier just hitting the file directly (i.e. sitemap/sitemap.xml.gz), this class can be overriden by other
 * functionality (like subdomain bindings)
 */
class XfAddOns_Sitemap_ControllerPublic_Sitemap extends XenForo_ControllerPublic_Abstract
{

	/**
	 * Main index just outputs the robots.txt file
	 */
	public function actionDownload()
	{
		$sitemap = $this->_input->filterSingle('sitemap', XenForo_Input::STRING);
		
		$options = XenForo_Application::getOptions();
		$path = $options->xenforo_sitemap_directory . '/' . $sitemap;
		if (!is_file($path))
		{
			header('HTTP/1.1 404 Not Found');
			print "$path 404 Not Found";
			exit;
		}
		if (!preg_match('/(\.xml$)|(\.gz$)/', $file))
		{
			header('HTTP/1.1 404 Not Found');
			print "$path 404 Not Found";
			exit;
		}
		
		header('Content-Type: application/x-gzip');
		header("Content-Length: ". filesize($path));
		readfile($path);
		exit;
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
			$output[$key] = new XenForo_Phrase('xfa_sitemap_downloading_sitemap');
		}
		return $output;
	}	
	
}