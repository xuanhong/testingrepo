<?php
/**
* Data writer for Forums.
*
* @package XenForo_Forum
*/
class Brivium_MetadataEssential_DataWriter_Forum extends XFCP_Brivium_MetadataEssential_DataWriter_Forum
{
	public function save() {
		$saved = parent::save();
		if (isset($GLOBALS['BRME_CAF_actionSave'])) {
			$GLOBALS['BRME_CAF_actionSave']->brmeActionSave($this);
			unset($GLOBALS['BRME_CAF_actionSave']);
		}
		return $saved;
	}
}