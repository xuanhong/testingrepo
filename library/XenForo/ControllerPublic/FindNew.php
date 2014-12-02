<?php

class XenForo_ControllerPublic_FindNew extends XenForo_ControllerPublic_Abstract
{
	public function actionThreads()
	{
		return $this->responseReroute(__CLASS__, 'posts');
	}
	
	/**
	 * Finds new/unread posts.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPosts()
	{
		$this->_routeMatch->setSections('forums');
		$threadModel = $this->_getThreadModel();
		$visitor = XenForo_Visitor::getInstance();

		$searchId = $this->_input->filterSingle('search_id', XenForo_Input::UINT);
		if (!$searchId)
		{
			return $this->findNewPosts();
		}

		$searchModel = $this->_getSearchModel();

		$search = $searchModel->getSearchById($searchId);
		if (!$search
			|| $search['user_id'] != XenForo_Visitor::getUserId()
			|| !in_array($search['search_type'], array('new-posts', 'recent-posts'))
		)
		{
			return $this->findNewPosts();
		}

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$perPage = XenForo_Application::get('options')->discussionsPerPage;

		$pageResultIds = $searchModel->sliceSearchResultsToPage($search, $page, $perPage);
		$threadIds = XenForo_Application::arrayColumn($pageResultIds, 1);

		$threadsMatched = $threadModel->getThreadsByIds($threadIds, array(
			'join' =>
				XenForo_Model_Thread::FETCH_FORUM |
				XenForo_Model_Thread::FETCH_USER |
				XenForo_Model_Thread::FETCH_FIRSTPOST,
			'permissionCombinationId' => $visitor['permission_combination_id'],
			'readUserId' => $visitor['user_id'],
			'includeForumReadDate' => true,
			'watchUserId' => $visitor['user_id'],
			'forumWatchUserId' => $visitor['user_id'],
			'postCountUserId' => $visitor['user_id']
		));
		$threads = array();
		$inlineModOptions = array();
		foreach ($threadIds AS $threadId)
		{
			if (!isset($threadsMatched[$threadId]))
			{
				continue;
			}

			$thread = $threadsMatched[$threadId];

			$thread['permissions'] = XenForo_Permission::unserializePermissions($thread['node_permission_cache']);
			if (!$threadModel->canViewThreadAndContainer($thread, $thread, $null, $thread['permissions']))
			{
				continue;
			}

			$thread = $threadModel->prepareThread($thread, $thread, $thread['permissions']);

			$thread['forum'] = array(
				'node_id' => $thread['node_id'],
				'node_name' => $thread['node_name'],
				'title' => $thread['node_title']
			);

			$threadModOptions = $threadModel->addInlineModOptionToThread($thread, $thread, $thread['permissions']);
			$inlineModOptions += $threadModOptions;

			$threads[$thread['thread_id']] = $thread;
		}

		if (!$threads)
		{
			return $this->getNoPostsResponse();
		}

		$resultStartOffset = ($page - 1) * $perPage + 1;
		$resultEndOffset = ($page - 1) * $perPage + count($threadIds);

		$viewParams = array(
			'search' => $search,
			'threads' => $threads,
			'inlineModOptions' => $inlineModOptions,

			'threadStartOffset' => $resultStartOffset,
			'threadEndOffset' => $resultEndOffset,

			'ignoredNames' => $this->_getIgnoredContentUserNames($threads),

			'page' => $page,
			'perPage' => $perPage,
			'totalThreads' => $search['result_count'],
			'nextPage' => ($resultEndOffset < $search['result_count'] ? ($page + 1) : 0),

			'showingNewPosts' => ($search['search_type'] == 'new-posts')
		);

		return $this->getFindNewWrapper(
			$this->responseView('XenForo_ViewPublic_FindNew_Posts', 'find_new_posts', $viewParams),
			'posts'
		);
	}

	public function findNewPosts()
	{
		$threadModel = $this->_getThreadModel();
		$searchModel = $this->_getSearchModel();

		$userId = XenForo_Visitor::getUserId();
		$visitor = XenForo_Visitor::getInstance();

		$limitOptions = array(
			'limit' => XenForo_Application::get('options')->maximumSearchResults
		);

		$days = $this->_input->filterSingle('days', XenForo_Input::UINT);
		$recent = $this->_input->filterSingle('recent', XenForo_Input::UINT);
		$watched = $this->_input->filterSingle('watched', XenForo_Input::UINT);

		if ($userId && !$days && !$recent)
		{
			$threadIds = $threadModel->getUnreadThreadIds($userId, $limitOptions, $watched);

			$searchType = 'new-posts';
		}
		else
		{
			if ($days < 1)
			{
				$days = max(7, XenForo_Application::get('options')->readMarkingDataLifetime);
			}

			$fetchOptions = $limitOptions + array(
				'order' => 'last_post_date',
				'orderDirection' => 'desc',
				'watchUserId' => $userId,
				'forumWatchUserId' => $userId,
				'join' => XenForo_Model_Thread::FETCH_FORUM_OPTIONS
			);

			$threadIds = array_keys($threadModel->getThreads(array(
				'last_post_date' => array('>', XenForo_Application::$time - 86400 * $days),
				'not_discussion_type' => 'redirect',
				'deleted' => false,
				'moderated' => false,
				'find_new' => true,
				'watch_only' => $watched
			), $fetchOptions));

			$searchType = 'recent-posts';
		}

		$threads = $threadModel->getThreadsByIds(
			$threadIds,
			array(
				'join' =>
					XenForo_Model_Thread::FETCH_FORUM |
					XenForo_Model_Thread::FETCH_USER,
				'permissionCombinationId' => $visitor['permission_combination_id']
			)
		);
		foreach ($threads AS $key => $thread)
		{
			$thread['permissions'] = XenForo_Permission::unserializePermissions($thread['node_permission_cache']);

			if (!$threadModel->canViewThreadAndContainer($thread, $thread, $null, $thread['permissions'])
				|| $visitor->isIgnoring($thread['user_id'])
			)
			{
				unset($threads[$key]);
			}
		}

		$results = array();
		foreach ($threadIds AS $threadId)
		{
			if (isset($threads[$threadId]))
			{
				$results[] = array(
					XenForo_Model_Search::CONTENT_TYPE => 'thread',
					XenForo_Model_Search::CONTENT_ID => $threadId
				);
			}
		}

		if (!$results)
		{
			return $this->getNoPostsResponse();
		}

		$search = $searchModel->insertSearch($results, $searchType, '', array(), 'date', false);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('find-new/posts', $search)
		);
	}

	public function getNoPostsResponse()
	{
		$days = $this->_input->filterSingle('days', XenForo_Input::UINT);
		$recent = $this->_input->filterSingle('recent', XenForo_Input::UINT);

		$this->_routeMatch->setSections('forums');

		return $this->getFindNewWrapper($this->responseView('XenForo_ViewPublic_FindNew_PostsNone', 'find_new_posts_none', array(
			'days' => $days,
			'recent' => $recent
		)), 'posts');
	}

	public function getFindNewWrapper(XenForo_ControllerResponse_View $subView, $selectedTab)
	{
		$tabs = $this->_getWrapperTabs();

		$view = $this->responseView('XenForo_ViewPublic_FindNew_Wrapper', 'find_new_wrapper', array(
			'tabs' => $tabs,
			'showTabs' => count($tabs) > 1,
			'selectedTab' => $selectedTab
		));
		$view->subView = $subView;

		return $view;
	}

	protected function _getWrapperTabs()
	{
		return array(
			'posts' => array(
				'href' => XenForo_Link::buildPublicLink('find-new/posts'),
				'title' => new XenForo_Phrase('new_posts')
			)
		);
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		return new XenForo_Phrase('viewing_latest_content');
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}

	/**
	 * @return XenForo_Model_Search
	 */
	protected function _getSearchModel()
	{
		return $this->getModelFromCache('XenForo_Model_Search');
	}
}