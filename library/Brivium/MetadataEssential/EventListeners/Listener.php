<?php

class Brivium_MetadataEssential_EventListeners_Listener
{
	public static function loadClassController($class, &$extend)
	{
		$classes = array(
			'ControllerAdmin_Forum',
			'ControllerPublic_Thread',
			'ControllerPublic_Forum',
		);
		foreach($classes AS $clas){if ($class == 'XenForo_' .$clas ){$extend[] = 'Brivium_MetadataEssential_' .$clas;}}
	}
	
	public static function loadClassModel($class, &$extend)
	{
		$classes = array(
			'Thread',
		);
		foreach($classes AS $clas){if ($class == 'XenForo_Model_' .$clas ){$extend[] = 'Brivium_MetadataEssential_Model_' .$clas;}}
	}
	public static function loadClassDataWriter($class, &$extend)
	{
		$classes = array(
			'Discussion_Thread',
			'Forum',
		);
		foreach($classes AS $clas){if ($class == 'XenForo_DataWriter_' .$clas ){$extend[] = 'Brivium_MetadataEssential_DataWriter_' .$clas;}}
	}
	public static function templatePostRender($templateName, &$content, array &$containerData, XenForo_Template_Abstract $template)
    {
		if(is_null(self::$_show)){
			$visitor = XenForo_Visitor::getInstance();
			self::$_show = true;
			$excludeGroups = XenForo_Application::get('options')->BRME_excludeUserGroup;
			if($excludeGroups){
				$belongstogroups = $visitor['user_group_id'];
				if (!empty($visitor['secondary_group_ids']))
				{
					$belongstogroups .= ','.$visitor['secondary_group_ids'];
				}
				$groupcheck = explode(',',$belongstogroups);
				unset($belongstogroups);
				foreach ($groupcheck AS $groupId)
				{
					if (in_array($groupId, $excludeGroups))
					{
						self::$_show = false;
						break;
					}
				}
			}
		}
		if(self::$_show){
			switch ($templateName) {
				case 'thread_edit':
					$newTemplate = $template->create('BRME_thread_create',$template->getParams());
					$posAdd = strpos($content,'<dl class="ctrlUnit submitUnit">');
					$number = 32;
					$posEdit = strpos($content,'</form>');
					
					if($posAdd!==false){
						$firstPart = substr($content,0, $posAdd);
						$secondPart = substr($content, $posAdd);
						$content = $firstPart . $newTemplate->render() .$secondPart;
					}elseif($posEdit!==false){
						$number = 7;
						$firstPart = substr($content,0, $posEdit);
						$secondPart = substr($content, $posEdit);
						$content = $firstPart . $newTemplate->render() .$secondPart;
					}
					break;
			}
		}
    }
	
	protected static  $_show = null;
	public static function templateHook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
    {
		if(is_null(self::$_show)){
			$visitor = XenForo_Visitor::getInstance();
			self::$_show = true;
			$excludeGroups = XenForo_Application::get('options')->BRME_excludeUserGroup;
			if($excludeGroups){
				$belongstogroups = $visitor['user_group_id'];
				if (!empty($visitor['secondary_group_ids']))
				{
					$belongstogroups .= ','.$visitor['secondary_group_ids'];
				}
				$groupcheck = explode(',',$belongstogroups);
				unset($belongstogroups);
				foreach ($groupcheck AS $groupId)
				{
					if (in_array($groupId, $excludeGroups))
					{
						self::$_show = false;
						break;
					}
				}
			}
		}
		
		switch ($hookName) {
			case 'forum_edit_basic_information': //admin hook
				$newTemplate = $template->create('BRME_' . $hookName, $template->getParams());
				$contents .= $newTemplate->render();
				break;
			case 'thread_create':
				if(self::$_show){
					$newTemplate = $template->create('BRME_' . $hookName, $template->getParams());
					$contents .= $newTemplate->render();
				}
				break;
			case 'page_container_head':
				$metaData = array(
					'description'	=>	'',
					'keywords'	=>	'',
					'author'	=>	'',
				);
				if (isset($GLOBALS['BRME_metadata'])&& is_array($GLOBALS['BRME_metadata'])) {
					$metaData = array_merge($metaData,$GLOBALS['BRME_metadata']);
				}
				if (isset($GLOBALS['brmeOptions'])&& is_array($GLOBALS['brmeOptions'])) {
					$brmeOptions = $GLOBALS['brmeOptions'];
				}
				$newTemplate = $template->create('BRME_' . $hookName, $template->getParams());
				$newTemplate->setParams(array('metaData'=>$metaData,'brmeOptions'=>$brmeOptions));
				$contents .= $newTemplate->render();
				break;
		}

    }
	
	
}