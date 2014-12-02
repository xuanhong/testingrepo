<?php

class Tinhte_AttachImageOptimization_ViewPublic_Attachment_View304 extends XFCP_Tinhte_AttachImageOptimization_ViewPublic_Attachment_View304
{
	public function renderRaw()
	{
		$parent = parent::renderRaw();
		
		$this->_response->setHeader('Expires', gmdate('D, d M Y H:i:s \G\M\T', strtotime('+30 days')), true);
		$this->_response->setHeader('Cache-Control', 'public', true);
		
		return $parent;
	}
}