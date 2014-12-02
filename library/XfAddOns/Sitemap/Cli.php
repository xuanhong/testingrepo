<?php

// disable limits
@set_time_limit(0);
ini_set('memory_limit', '256M');

chdir(dirname(__FILE__) . '/../../..');

if (!is_file('./library/config.php'))
{
	print 'We do not appear to be running from the correct directory, needs to be XF root and is: ' . getcwd() . "\r\n";;
	exit;
}
else
{
	print 'Running from directory: ' . getcwd() . "\r\n";
}

$startTime = microtime(true);
require('./library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader('./library');

XenForo_Application::initialize('./library');
XenForo_Application::set('page_start_time', $startTime);
// XenForo_Application::setDebugMode(true);

$db = XenForo_Application::getDb();
$db->setProfiler(false);

// and run the sitemap
XfAddOns_Sitemap_CronEntry_RebuildSitemap::run();

