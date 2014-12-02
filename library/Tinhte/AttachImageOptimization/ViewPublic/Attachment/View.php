<?php

class Tinhte_AttachImageOptimization_ViewPublic_Attachment_View extends XFCP_Tinhte_AttachImageOptimization_ViewPublic_Attachment_View
{

    public function renderRaw()
    {
        $parent = parent::renderRaw();
        
        $attachment = $this->_params['attachment'];

        $extension = XenForo_Helper_File::getFileExtension($attachment['filename']);
        $imageTypes = array(
            'gif' => 'image/gif',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpe' => 'image/jpeg',
            'png' => 'image/png'
        );

        if (in_array($extension, array_keys($imageTypes)))
        {
            $this->_response->setHeader('Expires', gmdate('D, d M Y H:i:s \G\M\T', strtotime('+30 days')), true);
            $this->_response->setHeader('Last-Modified', gmdate('D, d M Y H:i:s \G\M\T', $attachment['attach_date']), true);
            $this->_response->setHeader('Cache-Control', 'public', true);
        }

        return $parent;
    }

}