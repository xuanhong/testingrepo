<?php

/**
 * Controller for handling actions on threads.
 *
 * @package XenForo_Thread
 */
class Brivium_MetadataEssential_ControllerPublic_Thread extends XFCP_Brivium_MetadataEssential_ControllerPublic_Thread
{
	/**
	 * Displays a form to edit a thread.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$response = parent::actionEdit();
		if(!empty($response->params['thread']['thread_id'])){
			$response->params['thread']['metaData'] = $this->getModelFromCache('Brivium_MetadataEssential_Model_Metadata')->getMetadata('thread',$response->params['thread']['thread_id']);
		}
		return $response;
	}
	/**
	 * Updates an existing thread.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$GLOBALS['BRME_CPF_actionAddThread'] = $this;
		return parent::actionSave();
	}
	public function brmeActionSave(XenForo_DataWriter_Discussion_Thread $writer) {
		$metaOptions = $this->_getMetadataOptions();
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
		$metadataModel->updateMetadata($data,$data['content_type'],$data['content_id']);
	}
	
	protected function _getDefaultViewParams(array $forum, array $thread, array $posts, $page = 1, array $viewParams = array())
	{
		$viewParams = parent::_getDefaultViewParams($forum, $thread, $posts, $page, $viewParams);
		if(!empty($thread['thread_id'])){
			$threadModel = $this->_getThreadModel();
			$metaOptions = $this->_getMetadataOptions();
			//$excludeForum = XenForo_Application::get('options')->BRTAS_excludeForum;
			$metaData  = $this->getModelFromCache('Brivium_MetadataEssential_Model_Metadata')->getMetadata('thread',$thread['thread_id']);
			if(isset($viewParams['firstPost']['message']) && (!$metaData['description'] || $metaOptions['description']=='content')){
				$metaData['description'] = XenForo_Template_Helper_Core::helperSnippet($viewParams['firstPost']['message'],155);
			}
			$GLOBALS['BRME_metadata'] = $metaData;
			$GLOBALS['brmeOptions'] = $this->_getMetadataOptions();
		}
		return $viewParams;
	}
	protected function _getMetadataOptions()
	{
		return XenForo_Application::get('options')->BRME_threadMetadata;
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