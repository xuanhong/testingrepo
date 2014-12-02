<?php

$startTime = microtime(true);
$fileDir = dirname(__FILE__);

require($fileDir . '/library/XenForo/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');

XenForo_Application::initialize($fileDir . '/library', $fileDir);
XenForo_Application::set('page_start_time', $startTime);

// create the route
$route = new XenForo_RouteMatch('XfAddOns_Sitemap_ControllerPublic_Sitemap', 'index');

// create the custom controller
$request = new Zend_Controller_Request_Http();
$response = new Zend_Controller_Response_Http();
$controller = new XfAddOns_Sitemap_ControllerPublic_Sitemap($request, $response, $route);

// dispatch the action that we need
$controllerResponse = $controller->actionDownload();

// and render the view
$viewRenderer = new XenForo_ViewRenderer_Raw(new XenForo_Dependencies_Public(), $response, $request);
$content = $viewRenderer->renderView(
		$controllerResponse->viewName, $controllerResponse->params, $controllerResponse->templateName,
		$controllerResponse->subView
);

header('Content-Type: text/plain');
echo $content;