<?php

/**
 * Class used to generate the sitemap contents for pages
 */
class XfAddOns_Sitemap_Sitemap_Page extends XfAddOns_Sitemap_Sitemap_Base
{

	/**
	 * Page Model used for permissions
	 * @var XenForo_Model_Page
	 */
	private $pageModel;

	/**
	 * Constructor.
	 * Initializes the map with the root set as urlset
	 */
	public function __construct()
	{
		parent::__construct();
		$this->pageModel = XenForo_Model::create('XenForo_Model_Page');
	}

	/**
	 * Generate the page part of the sitemap
	 */
	public function generate()
	{
		$ret = array();
		XfAddOns_Sitemap_Logger::debug('Generating pages...');
		$this->generateStep();
		if (!$this->isEmpty)
		{
			$ret[] = $this->save($this->getSitemapName('pages'));
		}
		return $ret;
	}	
	
	/**
	 * Append the information about the pages to the sitemap
	 */
	protected function generateStep()
	{
		$this->initialize();

		$db = XenForo_Application::getDb();
		$sql = "
			SELECT * FROM xf_node node
			INNER JOIN xf_page page ON node.node_id=page.node_id
			ORDER BY node.node_id
			";
		$st = new Zend_Db_Statement_Mysqli($db, $sql);
		$st->execute();

		while ($data = $st->fetch())
		{
			$url = XenForo_Link::buildPublicLink('canonical:pages', $data);
			if ($this->canView($data))
			{
				$this->addUrl($url, $data['modified_date']);
			}
			else
			{
				XfAddOns_Sitemap_Logger::debug('-- Excluded: ' . $url);
			}
		}
		$st->closeCursor();
	}

	/**
	 * Check if the default (not registered) user can view the page. We only expose through the sitemap the
	 * information about the pages that are visible to all the public
	 *
	 * @param array $data		array with information for the page
	 * @return boolean
	 */
	private function canView($data)
	{
		$nodeId = $data['node_id'];

		$errorPhrase = '';
		$nodePermissions = $this->defaultVisitor->getNodePermissions($nodeId);
		
		return $this->pageModel->canViewPage($data, $errorPhrase, $nodePermissions, $this->defaultVisitor->toArray());
	}


}