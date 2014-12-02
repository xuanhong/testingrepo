<?php

class Brivium_MetadataEssential_Model_Metadata extends XenForo_Model
{
	/**
	 * Get all metadatas , in their relative display order.
	 *
	 * @return array Format: [] => metadata info
	 */
	public function getAllMetadatas()
	{
		return $this->fetchAllKeyed('
				SELECT *
				FROM xf_brivium_metadata
				ORDER BY `order` ASC, `title` 
			', 'metadata_id');
	}
	
	/**
	 * Returns metadata records based on metadata_id.
	 *
	 * @param string $metadataId
	 *
	 * @return array|false
	 */
	public function getMetadataById($metadataId)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_brivium_metadata
			WHERE  metadata_id = ?
		', array( $metadataId));
	}
	/**
	 * Prepares a collection of metadata fetching related conditions into an SQL clause
	 *
	 * @param array $conditions List of conditions
	 * @param array $fetchOptions Modifiable set of fetch options (may have joins pushed on to it)
	 *
	 * @return string SQL clause (at least 1=1)
	 */
	public function prepareMetadataConditions(array $conditions, array &$fetchOptions)
	{
		$sqlConditions = array();
		$db = $this->_getDb();

		if (!empty($conditions['description']))
		{
			if (is_array($conditions['description']))
			{
				$sqlConditions[] = 'metadata.description LIKE ' . XenForo_Db::quoteLike($conditions['description'][0], $conditions['description'][1], $db);
			}
			else
			{
				$sqlConditions[] = 'metadata.description LIKE ' . XenForo_Db::quoteLike($conditions['description'], 'lr', $db);
			}
		}
		if (!empty($conditions['metadata_id']))
		{
			if (is_array($conditions['metadata_id']))
			{
				$sqlConditions[] = 'metadata.metadata_id IN (' . $db->quote($conditions['metadata_id']) . ')';
			}
			else
			{
				$sqlConditions[] = 'metadata.metadata_id = ' . $db->quote($conditions['metadata_id']);
			}
		}
		
		if (!empty($conditions['content_type']))
		{
			$sqlConditions[] = 'metadata.content_type = ' . $db->quote($conditions['content_type']);
		}
		if (!empty($conditions['content_id']))
		{
			$sqlConditions[] = 'metadata.content_id = ' . $db->quote($conditions['content_id']);
		}
		
		return $this->getConditionsForClause($sqlConditions);
	}
	public function prepareMetadataFetchOptions(array $fetchOptions)
	{
		$selectFields = '';
		$joinMetadatas = '';
		$orderBy = '';
		if (!empty($fetchOptions['order']))
		{
			switch ($fetchOptions['order'])
			{
				case 'content_type':
				case 'content_id':
					$orderBy = 'metadata.' . $fetchOptions['order'];
					break;
				default:
					$orderBy = 'metadata.metadata_id';	
			}
			if (!isset($fetchOptions['orderDirection']) || $fetchOptions['orderDirection'] == 'desc')
			{
				$orderBy .= ' DESC';
			}
			else
			{
				$orderBy .= ' ASC';
			}
		}
		return array(
			'selectFields' => $selectFields,
			'joinMetadatas'   => $joinMetadatas,
			'orderClause'  => ($orderBy ? "ORDER BY $orderBy" : '')
		);
	}
	
	/**
	 * Gets metadatas that match the given conditions.
	 *
	 * @param array $conditions Conditions to apply to the fetching
	 * @param array $fetchOptions Collection of options that relate to fetching
	 *
	 * @return array Format: [metadata id] => info
	 */
	public function getMetadatas(array $conditions, array $fetchOptions = array())
	{
		$whereConditions = $this->prepareMetadataConditions($conditions, $fetchOptions);

		$sqlClauses = $this->prepareMetadataFetchOptions($fetchOptions);
		$limitOptions = $this->prepareLimitFetchOptions($fetchOptions);
		
		return $this->fetchAllKeyed($this->limitQueryResults(			'
				SELECT metadata.*
					' . $sqlClauses['selectFields'] . '
				FROM xf_brivium_metadata AS metadata
				' . $sqlClauses['joinMetadatas'] . '
				WHERE ' . $whereConditions . '
				' . $sqlClauses['orderClause'] . '
			', $limitOptions['limit'], $limitOptions['offset']
		), 'metadata_id');
	}
	
	/**
	 * Gets the count of metadatas with the specified criteria.
	 *
	 * @param array $conditions Conditions to apply to the fetching
	 *
	 * @return integer
	 */
	public function countMetadatas(array $conditions)
	{
		$fetchOptions = array();
		$whereConditions = $this->prepareMetadataConditions($conditions, $fetchOptions);
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM `xf_brivium_metadata` AS `metadata`
			WHERE ' . $whereConditions . '
		');
	}
	
	
	public function insertMetadata($data){
		
		$defaultData = array(
			'content_type' 		=>	'',
			'content_id' 		=>	0,
			'description' 		=>	'',
			'keywords' 			=>	'',
			'author' 			=>	'',
		);
		$defaultData = array_merge($defaultData, $data);
		$this->_getDb()->query('
			INSERT ' . (XenForo_Application::get('options')->enableInsertDelayed ? 'DELAYED' : '') . ' INTO xf_brivium_metadata
				(content_type,content_id,description,keywords,author)
			VALUES
				(?,?,?,?,?)
		', $defaultData);
		return true;
	}
	
	public function updateMetadata($data,$contentType,$contentId){
		if(!$this->getMetadata($contentType,$contentId)){
			return $this->insertMetadata($data);
		}
		$db = $this->_getDb();
		$defaultData = array(
			'content_type' 		=>	'',
			'content_id' 		=>	0,
			'description' 		=>	'',
			'keywords' 			=>	'',
			'author' 			=>	'',
		);
		$defaultData = array_merge($defaultData, $data);
		$condition = 'content_type = ' . $db->quote($contentType) . ' AND content_id = ' . $db->quote($contentId);
		$db->update('xf_brivium_metadata',
			array(
				'description' => $defaultData['description'],
				'keywords' => $defaultData['keywords'],
				'author' => $defaultData['author'],
			),$condition
		);
		return true;
	}
	
	public function getMetadata($contentType,$contentId){
		
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_brivium_metadata
			WHERE  content_type = ? AND content_id = ?
		', array( $contentType,$contentId));
	}
	
	
	public function cleanString($string){
		
		$string = str_ireplace('prbreak]', 'prebreak]', $string);
		$string = preg_replace('#\n{3,}#', "\n\n", trim($string));
		$string = preg_replace('#\[(quote)[^\]]*\].*\[/\\1\]#siU', ' ', $string);
		$string = preg_replace('#\[(attach|media|img)[^\]]*\].*\[/\\1\]#siU', ' ', $string);
		while ($string != ($newString = preg_replace('#\[([a-z0-9]+)(=[^\]]*)?\](.*)\[/\1\]#siU', '\3', $string)))
		{
			$string = $newString;
		}
		$string = str_replace('[*]', '', $string);
		if ($trimLoc = stripos($string, '[prebreak]'))
		{
			$prbreak = '';

			if (($breakLoc = stripos($string, '[/prebreak]', $trimLoc+10)) && ($length = $breakLoc - $trimLoc-10))
			{
				$link = XenForo_Link::buildPublicLink('full:threads', $post);
				$prbreak = " [url='".$link."']".substr($string, $trimLoc+10, $length).'[/url]...';
			}

			$string = substr($string, 0, $trimLoc).$prbreak;
		}
		$string = htmlspecialchars($string);
		$string = XenForo_Helper_String::wholeWordTrim($string, 250);
		return $string;
	}
	
	
	
}