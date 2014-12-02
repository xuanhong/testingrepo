<?php

/**
 * Class used to generate the sitemap contents for forums
 */
class XfAddOns_Sitemap_Sitemap_ResourceManagerUpdates extends XfAddOns_Sitemap_Sitemap_Base
{

	/**
	 * Generate the forum part of the sitemap
	 */
	public function generate()
	{
		if (!class_exists('XenResource_ControllerPublic_Resource'))
		{
			XfAddOns_Sitemap_Logger::debug('Resource manager not present, skipping');
			return;
		}
		
		$ret = array();
		XfAddOns_Sitemap_Logger::debug('Generating resource updates...');
		$this->generateStep();
		if (!$this->isEmpty)
		{
			$ret[] = $this->save($this->getSitemapName('resources.updates'));
		}
		return $ret;
	}	
	
	/**
	 * Append the information about the forums to the sitemap
	 */
	protected function generateStep()
	{
		$this->initialize();

		$db = XenForo_Application::getDb();
		$sql = "
			SELECT
				resource_update.*, resource.title
			FROM xf_resource_update resource_update
			INNER JOIN xf_resource resource ON resource_update.resource_id = resource.resource_id
			ORDER BY
				resource_update_id
			";
		$st = new Zend_Db_Statement_Mysqli($db, $sql);
		$st->execute();

		$resourceCache = -1;
		while ($data = $st->fetch())
		{
			$isFirst = $resourceCache != $data['resource_id'];
			$resourceCache = $data['resource_id'];
			if ($isFirst)
			{
				continue;
			}

			$url = XenForo_Link::buildPublicLink('canonical:resources/update', $data, array('update' => $data['resource_update_id']));
			$this->addUrl($url, $data['post_date']);
		}
		$st->closeCursor();
	}

}