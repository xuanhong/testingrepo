<?php

class Brivium_MetadataEssential_ControllerAdmin_Forum extends XFCP_Brivium_MetadataEssential_ControllerAdmin_Forum
{
	public function actionEdit()
	{
		$response = parent::actionEdit();
		if(!empty($response->params['forum']['node_id'])){
			$response->params['forum']['metaData'] = $this->getModelFromCache('Brivium_MetadataEssential_Model_Metadata')->getMetadata('forum',$response->params['forum']['node_id']);
		}
		return $response;
	}

	public function actionSave()
	{		
		$GLOBALS['BRME_CAF_actionSave'] = $this;
		return parent::actionSave();
	}
	public function brmeActionSave(XenForo_DataWriter_Forum $writer)
	{		
		$metaOptions = XenForo_Application::get('options')->BRME_forumMetadata;
		$metadataModel = $this->_getMetadataModel();
		$data = array();
		
		if($metaOptions['description']=='user'){
			$data['description'] = XenForo_Template_Helper_Core::helperSnippet($this->_input->filterSingle('BRME_meta_description', XenForo_Input::STRING),155);
		}else{
			$data['description'] = XenForo_Template_Helper_Core::helperSnippet($writer->get('description'),155);
		}
		$data['keywords'] = $this->_input->filterSingle('BRME_meta_keywords', XenForo_Input::STRING);
		
		$forum = $writer->getMergedData();
		
		//$data['author'] = $forum['username']?$forum['username']:'';
		$data['content_type'] = 'forum';
		$data['content_id'] = $forum['node_id'];
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
	