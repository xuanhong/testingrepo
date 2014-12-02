<?php

/**
* Data writer for threads.
*
* @package XenForo_Discussion
*/
class Brivium_MetadataEssential_DataWriter_Discussion_Thread extends XFCP_Brivium_MetadataEssential_DataWriter_Discussion_Thread
{
	public function save() {
		$saved = parent::save();
		if (isset($GLOBALS['BRME_CPF_actionAddThread'])) {
			$GLOBALS['BRME_CPF_actionAddThread']->brmeActionSave($this);
			unset($GLOBALS['BRME_CPF_actionAddThread']);
		}
		return $saved;
	}
}