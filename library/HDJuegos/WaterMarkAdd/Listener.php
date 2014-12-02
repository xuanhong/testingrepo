<?php

class HDJuegos_WaterMarkAdd_Listener
{

	
	/**
	 * Extend stock xenforo models
	 * @param string	The name of the class to be created
	 * @param array		A modifiable list of classes that wish to extend the class.
	 */
	public static function listenModel($class, array &$extend)
	{
		$options = XenForo_Application::get('options');
		
		if ($class == 'XenForo_Model_Attachment' && $options->HDJuegos_WaterMarkAdd_EnableAddon)
		{
			$extend[] = 'HDJuegos_WaterMarkAdd_ModelOverride_Attachment';
		}
		if ($class == 'XfRu_UserAlbums_Model_Images' && $options->HDJuegos_WaterMarkAdd_EnableXfRuAlbums)
		{
			$extend[] = 'HDJuegos_WaterMarkAdd_ModelOverride_Images';
		}
	}
	public static function template_create($templateName, array &$params, XenForo_Template_Abstract $template)
	{
    switch ($templateName) {
        case 'forum_list':
            $template->preloadTemplate('HDJuegos_WaterMarkAdd_Footer');
            break;
		}
	}
	public static function template_hook ($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
    {
        
        if ($hookName == 'forum_list_nodes')
        {
             $ourTemplate = $template->create('HDJuegos_WaterMarkAdd_Footer', $template->getParams());
                //Render
             $rendered = $ourTemplate->render();
               
             $contents .= $rendered;
           
        }
    }


}