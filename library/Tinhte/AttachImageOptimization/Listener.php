<?php

class Tinhte_AttachImageOptimization_Listener {
    
    public static function load_class($class, array &$extend)
    {
        switch ($class)
        {
            case 'XenForo_ViewPublic_Attachment_View':
                $extend[] = 'Tinhte_AttachImageOptimization_ViewPublic_Attachment_View';
                break;
            case 'XenForo_ViewPublic_Attachment_View304':
                $extend[] = 'Tinhte_AttachImageOptimization_ViewPublic_Attachment_View304';
                break;
            case 'XenForo_ViewPublic_Post_Edit':
                $extend[] = 'Tinhte_AttachImageOptimization_ViewPublic_Post_Edit';
                break;
            case 'XenForo_ViewPublic_Post_EditInline':
                $extend[] = 'Tinhte_AttachImageOptimization_ViewPublic_Post_EditInline';
                break;
            case 'XenForo_ControllerPublic_Attachment':
                $extend[] = 'Tinhte_AttachImageOptimization_ControllerPublic_Attachment';
                break;
            case 'XenForo_BbCode_Formatter_Base':
                $extend[] = 'Tinhte_AttachImageOptimization_BbCode_Formatter_Base';
                break;
        }
    }
    
    public static function init(XenForo_Dependencies_Abstract $dependencies, array $data)
    {
    	XenForo_Template_Helper_Core::$helperCallbacks += array(
			'getattachfilename' => array('Tinhte_AttachImageOptimization_Listener', 'get_attach_file_name')
    	);
    }
    
    public static function get_attach_file_name($attachmentFilename)
    {
    	if ($attachmentFilename)
    	{
    		$filename = explode('.', $attachmentFilename);
    		
    		unset($filename[count($filename) - 1]);
    		
    		if ($filename)
    		{
    			return implode('', $filename);
    		}
    	}
    	
    	return '';
    }
    
    public static function template_post_render($templateName, &$content, array &$containerData, XenForo_Template_Abstract $template)
    {
        switch($templateName)
        {
            case 'bb_code_tag_attach':
                $xenOptions = XenForo_Application::getOptions();
                $cdnEnable = $xenOptions->Tinhte_AIO_CDNEnable;
                $cdnDomain = $xenOptions->Tinhte_AIO_CDNDomain;
                     
                $params = $template->getParams();
                if (isset($params['attachment']['thumbnailUrl']))
                {
                    if ($cdnEnable)
                    {
	                    $params['attachment_link'] = XenForo_Link::buildPublicLink('attachments', $params['attachment']);
	                    $params['attachment_thumbnail'] = $params['attachment']['thumbnailUrl'];
	                    
	                    if (substr($params['attachment_link'], 0, 7) != 'http://' &&
	                            substr($params['attachment_link'], 0, 8) != 'https://'
	                            )
	                    {
	                        $params['attachment_link'] = self::_getValidUrl($cdnDomain).$params['attachment_link'];
	                    }
	                    
	                    if (substr($params['attachment_thumbnail'], 0, 7) != 'http://' &&
	                            substr($params['attachment_thumbnail'], 0, 8) != 'https://'
	                            )
	                    {
	                        $params['attachment_thumbnail'] = self::_getValidUrl($cdnDomain).$params['attachment_thumbnail'];
	                    }
                    
                    	$params['cdnDomain'] = self::_getValidUrl($cdnDomain);
                    }
                    else
                    {
                    	$params['attachment_link'] = XenForo_Link::buildPublicLink('full:attachments', $params['attachment']);
                    	$params['attachment_thumbnail'] = $params['attachment']['thumbnailUrl'];
                    }
                
                    $hookTemplate = $template->create('TinhTe_AIO_Attach', $params);

                    $content = $hookTemplate->render();
                }
                break;
        }
    }
    
    public static function template_hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
    {
        switch ($hookName)
        {
            case 'attached_file_thumbnail':
                $xenOptions = XenForo_Application::getOptions();
                $cdnEnable = $xenOptions->Tinhte_AIO_CDNEnable;
                $cdnDomain = $xenOptions->Tinhte_AIO_CDNDomain;
                
                if (isset($hookParams['attachment']['thumbnailUrl']))
                {
                    if ($cdnEnable)
                    {
	                    $hookParams['attachment_link'] = XenForo_Link::buildPublicLink('attachments', $hookParams['attachment']);
	                    $hookParams['attachment_thumbnail'] = $hookParams['attachment']['thumbnailUrl'];
	                    
	                    if (substr($hookParams['attachment_link'], 0, 7) != 'http://' &&
	                            substr($hookParams['attachment_link'], 0, 8) != 'https://'
	                            )
	                    {
	                        $hookParams['attachment_link'] = self::_getValidUrl($cdnDomain).$hookParams['attachment_link'];
	                    }
	                    
	                    if (substr($hookParams['attachment_thumbnail'], 0, 7) != 'http://' &&
	                            substr($hookParams['attachment_thumbnail'], 0, 8) != 'https://'
	                            )
	                    {
	                        $hookParams['attachment_thumbnail'] = self::_getValidUrl($cdnDomain).$hookParams['attachment_thumbnail'];
	                    }
	                    
	                    $hookParams['cdnDomain'] = self::_getValidUrl($cdnDomain);
                    }
                    else
                    {
                    	$hookParams['attachment_link'] = XenForo_Link::buildPublicLink('full:attachments', $hookParams['attachment']);
                    	$hookParams['attachment_thumbnail'] = $hookParams['attachment']['thumbnailUrl'];
                    }
                
                    $hookTemplate = $template->create('TinhTe_AIO_Attached', $hookParams);
                    
                    $contents = $hookTemplate->render();
                }
                break;
        }
    }

    protected static function _getValidUrl($url)
    {
        $url = trim($url);

        if (!$url)
        {
            return false;
        }
        
        if (substr($url, strlen($url) - 1, 1) != '/')
        {
        	$url .= '/';
        }

        switch ($url[0])
        {
            case '#':
            case '/':
            case ' ':
            case "\r":
            case "\n":
                return false;
        }

        if (preg_match('/\r?\n/', $url))
        {
            return false;
        }

        if (preg_match('#^(https?|ftp)://#i', $url))
        {
            return $url;
        }

        return 'http://' . $url;
    }
}
?>
