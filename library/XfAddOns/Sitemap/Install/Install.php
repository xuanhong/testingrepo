<?php

class XfAddOns_Sitemap_Install_Install
{

	/**
	 * We will refuse installation of the add-on if the version is not correct
	 * @throws XenForo_Exception
	 */
	public static function install()
	{
		if (XenForo_Application::$versionId < 1020052)
		{
			throw new XenForo_Exception('This add-on requires XenForo 1.2.0 RC2 or higher.', true);
		}	
	}
	
}