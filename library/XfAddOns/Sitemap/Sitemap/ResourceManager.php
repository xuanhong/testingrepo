<?php

/**
 * Class used to generate the sitemap contents for forums
 */
class XfAddOns_Sitemap_Sitemap_ResourceManager extends XfAddOns_Sitemap_Sitemap_Base
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
		XfAddOns_Sitemap_Logger::debug('Generating resources...');
		$this->generateStep();
		if (!$this->isEmpty)
		{
			$ret[] = $this->save($this->getSitemapName('resources'));
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
		$sql = "SELECT * FROM xf_resource ORDER BY resource_id";
		$st = new Zend_Db_Statement_Mysqli($db, $sql);
		$st->execute();

		while ($data = $st->fetch())
		{
			$url = XenForo_Link::buildPublicLink('canonical:resources', $data);
			$this->addUrl($url, $data['last_update']);
		}
		$st->closeCursor();
	}

}