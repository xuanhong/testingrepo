<?php

// figure out friendly urls
$prefix = '';
if (strpos($_SERVER['REQUEST_URI'], 'index.php') >= 0)
{
	$prefix = 'index.php?';
}

// remap the URI
$uri = $prefix . 'xfa-robots/';
$_SERVER['REQUEST_URI'] = $uri;
$_SERVER['SCRIPT_NAME'] = 'index.php';
$_SERVER['SCRIPT_FILENAME'] = 'index.php';

// do default dispatch
$startTime = microtime(true);
$fileDir = dirname(__FILE__);

require($fileDir . '/library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');

XenForo_Application::initialize($fileDir . '/library', $fileDir);
XenForo_Application::set('page_start_time', $startTime);

$fc = new XenForo_FrontController(new XenForo_Dependencies_Public());
$fc->run();