<?php

/**
 * Class used to generate the sitemap contents for forums
 */
class XfAddOns_Sitemap_Sitemap_Forum extends XfAddOns_Sitemap_Sitemap_Base
{

	/**
	 * Forum Model used for permissions
	 * @var XenForo_Model_Forum
	 */
	private $forumModel;

	/**
	 * Constructor.
	 * Initializes the map with the root set as urlset
	 */
	public function __construct()
	{
		parent::__construct();
		$this->forumModel = XenForo_Model::create('XenForo_Model_Forum');
	}

	/**
	 * Generate the forum part of the sitemap
	 */
	public function generate()
	{
		$ret = array();
		XfAddOns_Sitemap_Logger::debug('Generating forums...');
		$this->generateStep();
		if (!$this->isEmpty)
		{
			$ret[] = $this->save($this->getSitemapName('forums'));
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
			SELECT * FROM xf_node node
			INNER JOIN xf_forum forum ON node.node_id=forum.node_id
			ORDER BY node.node_id
			";
		$st = new Zend_Db_Statement_Mysqli($db, $sql);
		$st->execute();

		while ($data = $st->fetch())
		{
			$url = XenForo_Link::buildPublicLink('canonical:forums', $data);
			if ($this->canView($data))
			{
				$this->addUrl($url, $data['last_post_date']);
			}
			else
			{
				XfAddOns_Sitemap_Logger::debug('-- Excluded: ' . $url);
			}
		}
		$st->closeCursor();
	}

	/**
	 * Check if the default (not registered) user can view the forum. We only expose through the sitemap the
	 * information about the forums that are visible to all the public
	 *
	 * @param array $data		array with information for the forum
	 * @return boolean
	 */
	private function canView($data)
	{
		$nodeId = $data['node_id'];

		$errorPhrase = '';
		$nodePermissions = $this->defaultVisitor->getNodePermissions($nodeId);
		return $this->forumModel->canViewForum($data, $errorPhrase, $nodePermissions, $this->defaultVisitor->toArray());
	}


}