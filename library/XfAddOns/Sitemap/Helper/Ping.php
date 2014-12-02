<?php

/**
 * Methods to ping the search engines services after a new sitemap has been generated
 */
class XfAddOns_Sitemap_Helper_Ping
{

	/**
	 * Ping a  URL. This method opens a connection to the specified URL, ignores any output and return
	 *
	 * @param string $url	The string with the url that we wish to ping
	 */
	private static function pingUrl($url)
	{
		// not enabled if PHP is not compiled with CURL
		if (!function_exists('curl_init'))
		{
			return;
		}

		// setup the curl request
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $url);
	    @curl_setopt($ch, CURLOPT_TIMEOUT, 5);
	    @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    @curl_setopt($ch, CURLOPT_HEADER, false);			// no headers
	    @curl_setopt($ch, CURLOPT_USERAGENT, 'XenForo Sitemap submission');
	    @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	    @curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
	    @curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));		// fix for Expect: header

	    $htmlresponse = @curl_exec($ch);
	    curl_close($ch);
	}

	/**
	 * Ping Google with the sitemap URL
	 * @param string $sitemap
	 */
	public static function pingGoogle($sitemap)
	{
		$url = 'http://www.google.com/webmasters/tools/ping?sitemap=';
		$url .= urlencode($sitemap);

		XfAddOns_Sitemap_Logger::debug('Pinging to Google: ' . $url);
		self::pingUrl($url);
	}

	/**
	 * Ping Google with the sitemap URL
	 * @param string $sitemap
	 */
	public static function pingBing($sitemap)
	{
		$url = 'http://www.bing.com/webmaster/ping.aspx?sitemap=';
		$url .= urlencode($sitemap);

		XfAddOns_Sitemap_Logger::debug('Pinging to Bing: ' . $url);
		self::pingUrl($url);
	}

}