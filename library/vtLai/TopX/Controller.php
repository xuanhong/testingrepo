<?php
/*======================================================================*\
|| #################################################################### ||
|| # vt.Lai TopX 1.4 For XenForo  by SinhVienIT.net                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2013 Vu Thanh Lai. All Rights Reserved.               # ||
|| # Please do not remove this comment lines.                         # ||
|| # -------------------- LAST MODIFY INFOMATION -------------------- # ||
|| # Last Modify: 14-08-2013 11:00:00 AM by: Vu Thanh Lai             # ||
|| # Please do not remove these comment line if use my code or a part # ||
|| #################################################################### ||
\*======================================================================*/

class vtLai_TopX_Controller extends XenForo_ControllerPublic_Abstract
{
	public function actionIndex()
	{
		$forumIds=$this->_input->filterSingle('forumids',XenForo_Input::STRING);
		$limit=$this->_input->filterSingle('resultCount',XenForo_Input::INT);
		$threads=vtLai_TopX_Modal::getData($forumIds,$limit);
		$options=XenForo_Application::get('options');
		$viewParams = array(
			'threads' => $threads,
			'enablePrefix'=>$options->vtLaiTopXPrefixEnable,
			'timeFormatType'=>$options->vtLaiTopXTimeFormatType
		);
		/*$cache=XenForo_Application::get('options')->vtLaiTopXCacheSystem;
		if($forumIds=='mythread' || $forumIds=='mypost')
		{
			$cache=0;
		}
		if($cache)
		{
			$path='vtlai/topXCache/'.md5($forumIds.$limit);
			$f=@fopen($path,'w+');
			if($f)
			{
				fwrite($f,serialize($threads));
				fclose($f);
			}
		}*/
		return $this->responseView('vtLai_TopX_View_Index', 'vtlai_topx_item', $viewParams);
	}
	
	
}