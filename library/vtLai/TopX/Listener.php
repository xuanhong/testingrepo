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
	
class vtLai_TopX_Listener
{
	protected static $_readyToDisplay=0,$_detectedHookAdded=0;
	public static function templateHook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
	{
		$options=XenForo_Application::get('options');
		if(!self::$_detectedHookAdded && $hookName == 'vtlai_topx')
		{
			if($options->vtLaiTopXPositionOrder=='prepend')
				$contents='<!-- vt.lai TopX Place -->'.$contents;
			else
				$contents.='<!-- vt.lai TopX Place -->';
			self::$_detectedHookAdded=1;
		}
		else if(!self::$_detectedHookAdded && $hookName == $options->vtLaiTopXPosition)
		{
			if($options->vtLaiTopXPositionOrder=='prepend')
				$contents='<!-- vt.lai TopX Place -->'.$contents;
			else
				$contents.='<!-- vt.lai TopX Place -->';
			self::$_detectedHookAdded=1;
		}
	}
	
	public static function templateCreate(&$templateName, array &$params, XenForo_Template_Abstract $template) 
	{		
		if ($templateName == 'PAGE_CONTAINER') {
			if(isset($params['contentTemplate']) && $params['contentTemplate'] == 'forum_list')
			{
				$template->preloadTemplate('vtlai_topx_main');
				self::$_readyToDisplay=1;
			}
		}
	}
	
	public static function templatePostRender($templateName, &$content, array &$containerData, XenForo_Template_Abstract $template)
	{
		if($templateName == 'PAGE_CONTAINER' && self::$_readyToDisplay)
		{
			$options=XenForo_Application::get('options');
			$visitor=XenForo_Visitor::getInstance();
			if(!$options->vtLaiTopXGuestEnable && !$visitor->getUserId())
				return;
			$styleId=$template->getParam('_styleId');
			$isMobileStyle=in_array($styleId,explode(',',$options->vtLaiTopXMobileIds));
			if($isMobileStyle)
			{
				$topXTemplate=$template->create('vtlai_topx_mobile', $template->getParams());
				$topXTemplate->setParam('threads', vtLai_TopX_Modal::getData('all',$options->vtLaiTopXResultCount),1);
				$topXTemplate->setParam('enablePrefix',$options->vtLaiTopXPrefixEnable);
				$rendered=$topXTemplate->render();

			}
			else
			{
				$topXTemplate=$template->create('vtlai_topx_main', $template->getParams());
				$topXTemplate->setParam('tabs', self::_getTabs());
				$topXTemplate->setParam('resultCountList', self::_getResultList());
				$topXTemplate->setParam('resultCount', $options->vtLaiTopXResultCount);
				$topXTemplate->setParam('reloadInterval', $options->vtLaiTopXReloadTime);
				$topXTemplate->setParam('tooltipMargin', $options->vtLaiTopXTooltipMargin);
				$topXTemplate->setParam('rightBoxEnable', $options->vtLaiTopXRightBoxEnable);
				$topXTemplate->setParam('rightBox', $options->vtLaiTopXRightBoxContent);
				$topXTemplate->setParam('time', time());
				$rendered=$topXTemplate->render();
			}
			$content=str_replace('<!-- vt.lai TopX Place -->',$rendered,$content);
		}
	}
	
	public static function setRoutes(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
		//$data['routesPublic']['vtlaitopx']=array
		//(
		//	'route_class'=>'vtLai_TopX_Controller',
		//	'build_link'=>'data_only'
		//);
		//XenForo_Link::setHandlerInfoForGroup('public', $data['routesPublic']);
	}
	
	
	protected static function _getTabs()
	{
		$options=XenForo_Application::get('options');
		$userId=XenForo_Visitor::getInstance()->getUserId();
		
		$tabs=array();
		$i=0;
		
		if($options->vtLaiTopXLastThreadEnable)
		{
			$tabs[$i++]=array('name'=>new XenForo_Phrase('vtlai_topx_last_thread'),'forumids'=>'lastthread');
		}
		
		$tabStr=$options->vtLaiTopXTabs;
		$tabList=preg_split('/(\r\n|\n)/',$tabStr);
		
		foreach($tabList as &$tabItem)
		{
			$tabItem=trim($tabItem);
			if($tabItem && $tabItem[0]!='#')
			{
				$arr=explode('|',$tabItem);
				$tabs[$i++]=array('name'=>$arr[0],'forumids'=>$arr[1]);
			}
		}
		
		if($userId && $options->vtLaiTopXMyThreadEnable)
		{
			$tabs[$i++]=array('name'=>new XenForo_Phrase('vtlai_topx_my_thread'),'forumids'=>'mythread');
		}
		if($userId && $options->vtLaiTopXMyPostEnable)
		{
			$tabs[$i++]=array('name'=>new XenForo_Phrase('vtlai_topx_my_post'),'forumids'=>'mypost');
		}
		return $tabs;
	}
	
	protected static function _getResultList()
	{
		$resultStr=XenForo_Application::get('options')->vtLaiTopXResultList;
		$resultList=explode(',',$resultStr);
		if($resultList)
			return $resultList;
		return XenForo_Application::get('options')->vtLaiTopXResultCount;
	}
}