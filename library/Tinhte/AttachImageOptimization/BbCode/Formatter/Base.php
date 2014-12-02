<?php

class Tinhte_AttachImageOptimization_BbCode_Formatter_Base extends XFCP_Tinhte_AttachImageOptimization_BbCode_Formatter_Base
{

    public function renderTagAttach(array $tag, array $rendererStates)
    {
        $imageTypes = array(
            'gif' => 'image/gif',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpe' => 'image/jpeg',
            'png' => 'image/png'
        );
        
        if (empty($rendererStates['viewAttachments']))
            $rendererStates['viewAttachments'] = true;
        
        $attachmentId = intval($this->stringifyTree($tag['children']));
        if (!empty($rendererStates['attachments'][$attachmentId]))
        {
            $extension = XenForo_Helper_File::getFileExtension($rendererStates['attachments'][$attachmentId]['filename']);

            if (!empty($rendererStates['attachments'][$attachmentId]['temp_hash']))
            {
                $rendererStates['attachments'][$attachmentId]['temp_hash'] = '';
            }
            
            if (in_array($extension, array_keys($imageTypes)))
            {
                $rendererStates['viewAttachments'] = true;
            }
        }
        else
        {
            if ($tag['option'] == 'full' && $tag['children'] && $rendererStates['viewAttachments'] && $rendererStates['lightBox'] && $attachmentId)
            {
                $cache = XenForo_Application::getCache();
                if ($cache)
                {
                    $attachment_check = unserialize($cache->load('attachment_cache_' . md5($attachmentId)));
                    if (!$attachment_check)
                    {
                        $attachment_check = $this->_getAttachmentModel()->getAttachmentById($attachmentId);
                        $cache->save(serialize($attachment_check), 'attachment_cache_' . md5($attachmentId), array(), 3600);
                    }
                }
                else
                {
                    $attachment_check = $this->_getAttachmentModel()->getAttachmentById($attachmentId);
                }
                
                if ($attachment_check && in_array(XenForo_Helper_File::getFileExtension($attachment_check['filename']), array_keys($imageTypes)))
                {
                    $attachment = $this->_getAttachmentModel()->prepareAttachment($attachment_check);
                    
                    if (!empty($attachment['temp_hash']))
                    {
                        $attachment['temp_hash'] = '';
                    }

                    $rendererStates['canView'] = true;
                    $rendererStates['validAttachment'] = true;
                    $rendererStates['viewAttachments'] = true;
                    $rendererStates['attachments'][$attachment['attachment_id']] = $attachment;
                }
            }
        }

        return parent::renderTagAttach($tag, $rendererStates);
    }
    
    public function renderTagQuote(array $tag, array $rendererStates)
    {
		$xenOptions = XenForo_Application::getOptions();
    	$keys = array_keys($tag['children']);
    	
    	if ($keys && !$xenOptions->Tinhte_AIO_QuoteEnable)
    	{
			$this->checkTagChildren($tag);
    	}
    	
    	return parent::renderTagQuote($tag, $rendererStates);
    }
    
    protected function checkTagChildren(array &$tag)
    {
    	if (!empty($tag['children']))
    	{
	    	foreach ($tag['children'] as &$child)
	    	{
	    		if (is_array($child))
	    		{
			    	if (isset($child['tag']) && $child['tag'] == 'attach')
			    	{
			    		$child['option'] = '';
			    	}
			    	
			    	$this->checkTagChildren($child);
	    		}
	    	}
    	}
    	
    	return $tag;
    }
    
    protected function _getAttachmentModel()
    {
        return XenForo_Model::create('XenForo_Model_Attachment');
    }

}