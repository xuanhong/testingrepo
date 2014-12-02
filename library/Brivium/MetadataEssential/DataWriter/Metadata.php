<?php

class Brivium_MetadataEssential_DataWriter_Metadata extends XenForo_DataWriter
{

	/**
	 * Title of the phrase that will be created when a call to set the
	 * existing data fails (when the data doesn't exist).
	 *
	 * @var string
	 */
	protected $_existingDataErrorPhrase = 'POS_requested_table_not_found';

	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_brivium_metadata' 	=> array(
				'metadata_id'  		=> array('type' => self::TYPE_UINT, 'autoIncrement' => true),
				'content_type'     	=> array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 100),
				'content_id'     	=> array('type' => self::TYPE_UINT, 'default' => 0),
				'description'   	=> array('type' => self::TYPE_STRING, 'default' => ''),
				'keywords'   		=> array('type' => self::TYPE_STRING,'default' => ''),
				'author'			=> array('type' => self::TYPE_STRING,'default' => ''),
			)
		);
	}

	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|false
	*/
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data))
		{
			return false;
		}
		return array('xf_brivium_metadata' => $this->_getMetadataModel()->getTableById($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'metadata_id = ' . $this->_db->quote($this->getExisting('metadata_id'));
	}
	
	/**
	 * Pre-save handling.
	 */
	protected function _preSave()
	{
	}

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		
	}

	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
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