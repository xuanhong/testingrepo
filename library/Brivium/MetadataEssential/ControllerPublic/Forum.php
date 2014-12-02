<?php

/**
 * Controller for handling actions on forums.
 *
 * @package XenForo_Forum
 */
class Brivium_MetadataEssential_ControllerPublic_Forum extends XFCP_Brivium_MetadataEssential_ControllerPublic_Forum
{
	public function actionForum()
	{
		$response = parent::actionForum();
		if(!empty($response->params['forum']['node_id'])){
			$GLOBALS['BRME_metadata'] = $this->getModelFromCache('Brivium_MetadataEssential_Model_Metadata')->getMetadata('forum',$response->params['forum']['node_id']);
			$GLOBALS['brmeOptions'] = XenForo_Application::get('options')->BRME_forumMetadata;
		}
		return $response;
	}
	public function actionIndex()
	{
		$response = parent::actionIndex();
		if(!method_exists('XenForo_ControllerPublic_Forum','actionForum')){
			if(!empty($response->params['forum']['node_id'])){
				$GLOBALS['BRME_metadata'] = $this->getModelFromCache('Brivium_MetadataEssential_Model_Metadata')->getMetadata('forum',$response->params['forum']['node_id']);
				$GLOBALS['brmeOptions'] = XenForo_Application::get('options')->BRME_forumMetadata;
			}
		}
		return $response;
	}
	/**
	 * Inserts a new thread into this forum.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAddThread()
	{
		$GLOBALS['BRME_CPF_actionAddThread'] = $this;
		return parent::actionAddThread();
	}
	public function brmeActionSave(XenForo_DataWriter_Discussion_Thread $writer) {
		$metaOptions = XenForo_Application::get('options')->BRME_threadMetadata;
		$metadataModel = $this->_getMetadataModel();
		$data = array();
		$firstMessageWriter = $writer->getFirstMessageDw();
		
		if($metaOptions['description']=='user'){
			$data['description'] = XenForo_Template_Helper_Core::helperSnippet($this->_input->filterSingle('BRME_meta_description', XenForo_Input::STRING),155);
		}else{
			$data['description'] = '';
		}
		$data['keywords'] = $this->_input->filterSingle('BRME_meta_keywords', XenForo_Input::STRING);
		
		$thread = $writer->getMergedData();
		
		$data['author'] = $thread['username']?$thread['username']:'';
		$data['content_type'] = 'thread';
		$data['content_id'] = $thread['thread_id'];
		
		$metadataModel->insertMetadata($data);
	}
	/**
	 * Load table model from cache.
	 *
	 * @return Brivium_MetadataEssential_Model_Metadata
	 */
	protected function _getMetadataModel()
	{
		return $this->getModelFromCache('Brivium_MetadataEssential_Model_Metadata');
	}
}