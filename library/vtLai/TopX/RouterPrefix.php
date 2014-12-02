<?php
/*======================================================================*\
|| #################################################################### ||
|| # vt.Lai TopX 1.4 For XenForo  by SinhVienIT.net                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2013 Vu Thanh Lai. All Rights Reserved.               # ||
|| # Please do not remove this comment lines.                         # ||
|| # -------------------- LAST MODIFY INFOMATION -------------------- # ||
|| # Last Modify: 08-08-2013 11:00:00 PM by: Vu Thanh Lai             # ||
|| # Please do not remove these comment line if use my code or a part # ||
|| #################################################################### ||
\*======================================================================*/

class vtLai_TopX_RouterPrefix implements XenForo_Route_Interface
{
	public function match($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router)
	{
		//$routePath: edit|upload...suffix
		//print_r($routePath);
		//print_r($router);
		//1st: Class Name
		//2nd: Method name
		return $router->getRouteMatch('vtLai_TopX_Controller', 'Index', 'vtLaiTopX', $routePath);
	}
}