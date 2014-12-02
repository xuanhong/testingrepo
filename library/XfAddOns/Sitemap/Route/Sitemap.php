<?php

/**
 * Route controller that intercepts the robots.txt file as a route
 * and dispatches to the controller that handles this logic
 */
class XfAddOns_Sitemap_Route_Sitemap
{
	
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		$route = $router->getRouteMatch('XfAddOns_Sitemap_ControllerPublic_Sitemap', $routePath);
		// setting the response to raw is the right thing to do, and will prevent the container from being used
		$route->setResponseType('raw');
		return $route;
	}	
	
	
}