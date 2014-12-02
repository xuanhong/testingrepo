<?php
/*======================================================================*\
|| #################################################################### ||
|| # vt.Lai TopX 1.2 For XenForo  by SinhVienIT.net                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2013 Vu Thanh Lai. All Rights Reserved.               # ||
|| # Please do not remove this comment lines.                         # ||
|| # -------------------- LAST MODIFY INFOMATION -------------------- # ||
|| # Last Modify: 14-08-2013 12:00:00 PM by: Vu Thanh Lai             # ||
|| # Please do not remove these comment line if use my code or a part # ||
|| #################################################################### ||
\*======================================================================*/

class vtLai_TopX_Modal
{
	protected static $_limitResult=null;
	protected static $_addSql=null;
	public static function getData($forumIds,$limit=10,$forceDisableMakeUp=0)
	{
		if($limit<0)
			$limit=10;
		if(self::$_addSql==null)
		{
			if(!$forceDisableMakeUp && XenForo_Application::get('options')->vtLaiTopXUserMakeUpEnable)
			{
				self::$_addSql['select']=',u.user_group_id,u.display_style_group_id';
				self::$_addSql['join']=' LEFT JOIN xf_user u ON t.last_post_user_id=u.user_id ';
			}
			else
			{
				self::$_addSql['select']=self::$_addSql['join']='';
			}
		}
		switch($forumIds)
		{
			case 'all':
				$sql=self::_getAllSql($limit);
				break;
			case 'lastthread':
				$sql=self::_getLastThreadSql($limit);
				break;
			case 'mythread':
				$sql=self::_getMyThreadSql($limit);
				break;
			case 'mypost':
				$sql=self::_getMyPostSql($limit);
				break;
			default:
				$sql=self::_getForumIdsSql($forumIds,$limit);
		};
		$db=XenForo_Application::get('db');
		$visitor=XenForo_Visitor::getInstance();
		$options=XenForo_Application::get('options');
		if(!$options->vtLaiTopXTimeFormatType && isset($visitor['timezone']))
		{
			@date_default_timezone_set($visitor['timezone']);
		}
		$threads=$db->fetchAll($sql);
		
		if($forumIds=='all' && self::$_limitResult)
		{
			$limitCount=self::$_limitResult;
		}
		else
		{
			$limitCount=array();
		}
		
		foreach($threads as $key=>&$thread)
		{
			//--Nếu đã lấy đủ số kết quả cần thì thoát
			if($limit<=0)
				break;
			//--Nếu bài này thuộc chuyên mục bị limit thì kiểm tra limit
			if(isset($limitCount[$thread['node_id']]))
			{
				if($limitCount[$thread['node_id']]<=0)
				{
					unset($threads[$key]);
					continue;
				}
				else
					$limitCount[$thread['node_id']]--;
			}
			
			$thread['user']=array('user_id'=>$thread['last_post_user_id'],'username'=>$thread['last_post_username']);
			if(isset($thread['user_group_id']))
				$thread['user']['user_group_id']=$thread['user_group_id'];
			if(isset($thread['display_style_group_id']))
				$thread['user']['display_style_group_id']=$thread['display_style_group_id'];
			$thread['forum']=array('node_id'=>$thread['node_id'],'title'=>$thread['forum_title']);
			if(!$options->vtLaiTopXTimeFormatType)
			{
				$thread['time']=date(XenForo_Application::get('options')->vtLaiTopXDateFormat,$thread['last_post_date']);
			}
			$limit--;
		}
		
		return $threads;
	}
	
	protected static function _getForumIdsSql($forumIds,$limit=10)
	{
		$forumIdArray=explode(',',$forumIds);
		foreach($forumIdArray as $key=>&$forumId)
		{
			$forumId=intval($forumId);
			if(!$forumId)
				unset($forumIdArray[$key]);
		}
		if(!$forumIdArray)
			throw new XenForo_Exception('Không xác định được forumId cần lấy bài',1);
		$forumIds=implode(',',$forumIdArray);
		return "SELECT t.*,n.title AS forum_title".self::$_addSql['select']." FROM xf_thread t LEFT JOIN xf_node n using(node_id) ".self::$_addSql['join']." WHERE `discussion_state`='visible' ".(($forumIds)?" AND `node_id` IN ({$forumIds}) ":'')."ORDER BY `last_post_date` DESC LIMIT {$limit}";
	}
	
	protected static function _getAllSql($limit=10)
	{
		if(self::$_limitResult===null)
		{
			self::_fetchLimitResult();
			if(self::$_limitResult)
				$limit*=2;
		}
		$options=XenForo_Application::get('options');
		$blackForumIds=trim($options->vtLaiTopXBlackForumIds);
		$blackUserIds=trim($options->vtLaiTopXBlackUserIds);
		
		return "SELECT t.*,n.title AS forum_title".self::$_addSql['select']." FROM xf_thread t LEFT JOIN xf_node n using(node_id) ".self::$_addSql['join']." WHERE `discussion_state`='visible' ".(($blackForumIds)?" AND node_id NOT IN ($blackForumIds) ":'').(($blackUserIds)?" AND `last_post_user_id` NOT IN ($blackUserIds)":'')." ORDER BY `last_post_date` DESC LIMIT $limit";
		
	}
	
	protected static function _getMyPostSql($limit=10)
	{
		$userId=XenForo_Visitor::getInstance()->getUserId();
		if(!$userId)
			throw new XenForo_Exception('Không xác định được userId',1);
		//return "SELECT t.*,n.title AS forum_title FROM xf_thread t LEFT JOIN xf_node n using(node_id) WHERE `discussion_state`='visible' AND t.thread_id IN (SELECT DISTINCT thread_id FROM xf_post WHERE `message_state`='visible' AND user_id = '{$userId}') ORDER BY `last_post_date` DESC LIMIT $limit";
		return "SELECT DISTINCT t.*,n.title AS forum_title".self::$_addSql['select']." FROM xf_post p LEFT JOIN xf_thread t using (thread_id) LEFT JOIN xf_node n using(node_id) ".self::$_addSql['join']." WHERE p.user_id='$userId' AND t.reply_count>0 AND `discussion_state`='visible' AND `message_state`='visible' ORDER BY p.post_date DESC LIMIT $limit";
	}
	
	protected static function _getMyThreadSql($limit=10)
	{
		$userId=XenForo_Visitor::getInstance()->getUserId();
		if(!$userId)
			throw new XenForo_Exception('Không xác định được userId',1);
		return "SELECT t.*,n.title AS forum_title".self::$_addSql['select']." FROM xf_thread t LEFT JOIN xf_node n using(node_id) ".self::$_addSql['join']." WHERE `discussion_state`='visible' AND t.user_id='{$userId}' ORDER BY `post_date` DESC LIMIT $limit";
	}
	
	protected static function _getLastThreadSql($limit =10)
	{
		$options=XenForo_Application::get('options');
		$blackForumIds=trim($options->vtLaiTopXBlackForumIds);
		$blackUserIds=trim($options->vtLaiTopXBlackUserIds);
		
		return "SELECT t.*,n.title AS forum_title".self::$_addSql['select']." FROM xf_thread t LEFT JOIN xf_node n using(node_id) ".self::$_addSql['join']." WHERE `discussion_state`='visible' ".(($blackForumIds)?" AND node_id NOT IN ($blackForumIds) ":'').(($blackUserIds)?" AND `user_id` NOT IN ($blackUserIds)":'')." ORDER BY `post_date` DESC LIMIT $limit";
	}
	
	protected static function _fetchLimitResult()
	{
		$limitOptionStr=XenForo_Application::get('options')->vtLaiTopXLimitResult;
		$tmpArray=preg_split('#(\r\n|\n)#',$limitOptionStr);
		self::$_limitResult=array();
		if($tmpArray)
		{
			foreach($tmpArray as $row)
			{
				if(preg_match('#^\s*(?P<node_id>[0-9]+)\s*\|\s*(?P<count>[0-9]+)#',$row,$m))
				{
					self::$_limitResult[$m['node_id']]=intval($m['count']);
				}
			}
		}
	}
}