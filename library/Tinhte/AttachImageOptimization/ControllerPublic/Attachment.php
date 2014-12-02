<?php

class Tinhte_AttachImageOptimization_ControllerPublic_Attachment extends XFCP_Tinhte_AttachImageOptimization_ControllerPublic_Attachment
{

    public function actionIndex()
    {
        $attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);
        $cache = XenForo_Application::getCache();
        $imageTypes = array(
            'gif' => 'image/gif',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpe' => 'image/jpeg',
            'png' => 'image/png'
        );

        if ($cache)
        {
            $attachment = unserialize($cache->load('attachment_cache_' . md5($attachmentId)));
            if (!$attachment)
            {
                $attachment = $this->_getAttachmentOrError($attachmentId);
                $extension = XenForo_Helper_File::getFileExtension($attachment['filename']);
                
                if (in_array($extension, array_keys($imageTypes)))
                {
                    $cache->save(serialize($attachment), 'attachment_cache_' . md5($attachmentId), array(), 3600);
                }
            }
        }
        else
        {
            $attachment = $this->_getAttachmentOrError($attachmentId);
        }

        $extension = XenForo_Helper_File::getFileExtension($attachment['filename']);
        
        if (!in_array($extension, array_keys($imageTypes)))
        {
            return parent::actionIndex();
        }

        $attachmentModel = $this->_getAttachmentModel();

        $filePath = $attachmentModel->getAttachmentDataFilePath($attachment);
        
        if (!file_exists($filePath) || !is_readable($filePath))
        {
            return $this->responseError(new XenForo_Phrase('attachment_cannot_be_shown_at_this_time'));
        }

        $this->canonicalizeRequestUrl(
        	XenForo_Link::buildPublicLink('attachments', $attachment)
        );

        $eTag = $this->_request->getServer('HTTP_IF_NONE_MATCH');
        
        $this->_routeMatch->setResponseType('raw');
        
        if ($eTag && $eTag == $attachment['attach_date'])
        {
            return $this->responseView('XenForo_ViewPublic_Attachment_View304');
        }

        $viewParams = array(
            'attachment' => $attachment,
            'attachmentFile' => $filePath
        );

        return $this->responseView('XenForo_ViewPublic_Attachment_View', '', $viewParams);
    }

}