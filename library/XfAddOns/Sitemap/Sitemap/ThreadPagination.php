<?php

/**
 * Class used to generate the sitemap contents for forums
 */
class XfAddOns_Sitemap_Sitemap_ThreadPagination extends XfAddOns_Sitemap_Sitemap_BasePagination
{

	/**
	 * Forum Model used for permissions
	 * @var XenForo_Model_Forum
	 */
	private $forumModel;

	/**
	 * Thread Model used for permission
	 * @var XenForo_Model_Thread
	 */
	private $threadModel;	
	
	/**
	 * Constructor.
	 * Initializes the map with the root set as urlset
	 */
	public function __construct()
	{
		parent::__construct();
		$this->forumModel = XenForo_Model::create('XenForo_Model_Forum');
		$this->threadModel = XenForo_Model::create('XenForo_Model_Thread');
	}

	/**
	 * Generate the threads part of the sitemap
	 */
	public function generate()
	{
		$ret = array();
		XfAddOns_Sitemap_Logger::debug('Generating threads with pagination...');
		while (!$this->isFinished)
		{
			XfAddOns_Sitemap_Logger::debug('-- Starting at ' . $this->lastId . ' and page ' . $this->lastPage . ' and generating ' . $this->maxUrls .' urls...');
			$this->generateStep($this->maxUrls);
			if (!$this->isEmpty)
			{
				$ret[] = $this->save($this->getSitemapName('threads.pags'));
			}
		}
		return $ret;
	}	
	
	/**
	 * Append the information about the forums to the sitemap
	 */
	protected function generateStep($totalElements)
	{
		$this->initialize();

		$db = XenForo_Application::getDb();
		$sql = "
			SELECT * FROM xf_thread thread
			WHERE
				thread_id > ? AND
				discussion_state = 'visible' AND
				discussion_type <> 'redirect'
			ORDER
				BY thread.thread_id
			";
		$st = new Zend_Db_Statement_Mysqli($db, $sql);
		$st->execute( array( $this->lastId ) );

		$totalPages = 0;
		while ($data = $st->fetch())
		{
			$this->lastId = $data['thread_id'];
			if (!$this->canView($data))
			{
				continue;
			}

			$totalPages = $this->getTotalpages($data['thread_id']);
			if ($totalPages < 2)
			{
				continue;
			}

			while ($this->lastPage <= $totalPages)
			{
				$params = array( 'page' => $this->lastPage, );
				$url = XenForo_Link::buildPublicLink('canonical:threads', $data, $params);
				$this->addUrl($url, $data['post_date']);

				$this->lastPage++;

				$totalElements--;
				if ($totalElements <= 0)
				{
					break 2;
				}
			}
			$this->lastPage = 2;
		}

		$this->isFinished = !$st->fetch() && $this->lastPage > $totalPages;
		$st->closeCursor();
	}

	/**
	 * Return the total pages that a node may have. This is based off the threads per forum
	 *
	 * @param int $nodeId
	 */
	private function getTotalPages($threadId)
	{
		// options
		$options = XenForo_Application::getOptions();
		$db = XenForo_Application::getDb();
		$totalThreads = $db->fetchOne("SELECT count(*) FROM xf_post WHERE message_state = 'visible' AND thread_id = ?", array( $threadId ));

		return ceil($totalThreads / $options->messagesPerPage);
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
		return $this->forumModel->canViewForum($data, $errorPhrase, $nodePermissions, $this->defaultVisitor->toArray()) &&
			$this->threadModel->canViewThreadAndContainer($data, $data, $errorPhrase, $nodePermissions, $this->defaultVisitor->toArray());
	}


}