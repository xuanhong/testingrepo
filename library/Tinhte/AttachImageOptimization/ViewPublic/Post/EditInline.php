<?php

class Tinhte_AttachImageOptimization_ViewPublic_Post_EditInline extends XFCP_Tinhte_AttachImageOptimization_ViewPublic_Post_EditInline
{

    public function renderHtml()
    {
        parent::renderHtml();
        
        if ($this->_params['editorTemplate']->getParam('showWysiwyg'))
        {
	        $attachPatern = '/\[ATTACH(.*?)\](.*?)\[\/ATTACH\]/si';
	        $count = @preg_match_all($attachPatern, $this->_params['post']['message'], $matches);
	        
	        if ($count)
	        {
	        	$cache      = XenForo_Application::getCache();
	            $imageTypes = array(
	            		'gif' =>'image/gif',
	            		'jpg' =>'image/jpeg',
	            		'jpeg'=>'image/jpeg',
	            		'jpe' =>'image/jpeg',
	            		'png' =>'image/png'
	            	);
	            
	        	foreach ($matches[0] as $position=>$match)
	        	{
	        		if ($match && intval($matches[2][$position]) > 0)
	        		{
	        			if($cache)
	        			{
	        				$attachment = unserialize($cache->load('attachment_cache_' . md5(intval($matches[2][$position]))));
	        				if(!$attachment)
	        				{
	        					$attachment = $this->_getAttachment(intval($matches[2][$position]));
	        					$extension  = XenForo_Helper_File::getFileExtension($attachment['filename']);
	        			
	        					if(in_array($extension, array_keys($imageTypes)))
	        					{
	        						$cache->save(serialize($attachment), 'attachment_cache_' . md5(intval($matches[2][$position])), array(), 3600);
	        					}
	        				}
	        			}
	        			else
	        			{
	        				$attachment = $this->_getAttachment(intval($matches[2][$position]));
	        			}
	        			
	        			if($attachment && $attachment['thumbnail_width'])
	        			{
	        				$attachment = $this->_getAttachmentModel()->prepareAttachment($attachment);
	        				
	        				if ($matches[1][$position])
	        				{
	        					$replace = '<img class="attachFull bbCodeImage" src="' . XenForo_Link::buildPublicLink('attachments', array('attachment_id' => $attachment['attachment_id'])) . '" alt="attachFull' . $attachment['attachment_id'] . '" data-mce-src="' . XenForo_Link::buildPublicLink('attachments', array('attachment_id' => $attachment['attachment_id'])) . '" />';
	        				}
	        				else 
	        				{
	        					$replace    = '<img class="attachThumb bbCodeImage" src="' . $attachment['thumbnailUrl'] . '" alt="attachThumb' . $attachment['attachment_id'] . '" data-mce-src="' . $attachment['thumbnailUrl'] . '" />';
	        				}
	        			}
	        			
	        			if (!empty($replace))
	        			{
	        				$htmlMessage = str_replace($match, $replace, $this->_params['editorTemplate']->getParam('messageHtml'));
	        			
	        				$this->_params['editorTemplate']->setParam('messageHtml', $htmlMessage);
	        			}
	        		}
	        	}
	        }
        }
        
    }

    protected function _getAttachment($attachmentId)
    {
        $attachment = $this->_getAttachmentModel()->getAttachmentById($attachmentId);
        if(!$attachment)
        {
            return false;
        }

        return $attachment;
    }

    protected function _getAttachmentModel()
    {
        return XenForo_Model_DataRegistry::create('XenForo_Model_Attachment');
    }

}