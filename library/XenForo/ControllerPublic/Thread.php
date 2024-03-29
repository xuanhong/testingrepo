<?php

/**
 * Controller for handling actions on threads.
 *
 * @package XenForo_Thread
 */
class XenForo_ControllerPublic_Thread extends XenForo_ControllerPublic_Abstract
{
	/**
	 * Adds 'forum' to the list of $containerParams if it exists in $params
	 */
	protected function _postDispatch($controllerResponse, $controllerName, $action)
	{
		if (isset($controllerResponse->params['forum']))
		{
			$controllerResponse->containerParams['forum'] = $controllerResponse->params['forum'];
		}
	}

	/**
	 * Displays a thread.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionIndex()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($threadFetchOptions, $forumFetchOptions) = $this->_getThreadForumFetchOptions();
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId, $threadFetchOptions, $forumFetchOptions);

		$visitor = XenForo_Visitor::getInstance();
		$threadModel = $this->_getThreadModel();
		$postModel = $this->_getPostModel();

		if ($threadModel->isRedirect($thread))
		{
			$redirect = $this->getModelFromCache('XenForo_Model_ThreadRedirect')->getThreadRedirectById($thread['thread_id']);
			if (!$redirect)
			{
				return $this->responseNoPermission();
			}
			else
			{
				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
					$redirect['target_url']
				);
			}
		}

		$page = max(1, $this->_input->filterSingle('page', XenForo_Input::UINT));
		$postsPerPage = XenForo_Application::get('options')->messagesPerPage;

		$this->canonicalizePageNumber($page, $postsPerPage, $thread['reply_count'] + 1, 'threads', $thread);
		$this->canonicalizeRequestUrl(
			XenForo_Link::buildPublicLink('threads', $thread, array('page' => $page))
		);

		$postFetchOptions = $this->_getPostFetchOptions($thread, $forum);
		$postFetchOptions += array(
			'perPage' => $postsPerPage,
			'page' => $page
		);

		$posts = $postModel->getPostsInThread($threadId, $postFetchOptions);

		// TODO: add a sanity check to ensure we got posts (invalid thread if page 1, invalid page otherwise)

		$posts = $postModel->getAndMergeAttachmentsIntoPosts($posts);

		$inlineModOptions = array();
		$maxPostDate = 0;
		$firstUnreadPostId = 0;

		$deletedPosts = 0;
		$moderatedPosts = 0;

		$pagePosition = 0;

		$permissions = $visitor->getNodePermissions($thread['node_id']);
		foreach ($posts AS &$post)
		{
			$post['position_on_page'] = ++$pagePosition;

			$postModOptions = $postModel->addInlineModOptionToPost(
				$post, $thread, $forum, $permissions
			);
			$inlineModOptions += $postModOptions;

			$post = $postModel->preparePost($post, $thread, $forum, $permissions);

			if ($post['post_date'] > $maxPostDate)
			{
				$maxPostDate = $post['post_date'];
			}

			if ($post['isDeleted'])
			{
				$deletedPosts++;
			}
			if ($post['isModerated'])
			{
				$moderatedPosts++;
			}

			if (!$firstUnreadPostId && $post['isNew'])
			{
				$firstUnreadPostId = $post['post_id'];
			}
		}

		if ($firstUnreadPostId)
		{
			$requestPaths = XenForo_Application::get('requestPaths');
			$unreadLink = $requestPaths['requestUri'] . '#post-' . $firstUnreadPostId;
		}
		else if ($thread['isNew'])
		{
			$unreadLink = XenForo_Link::buildPublicLink('threads/unread', $thread);
		}
		else
		{
			$unreadLink = '';
		}

		$attachmentHash = null;
		if (!empty($thread['draft_extra']))
		{
			$draftExtra = @unserialize($thread['draft_extra']);
			if (!empty($draftExtra['attachment_hash']))
			{
				$attachmentHash = $draftExtra['attachment_hash'];
			}
		}

		$attachmentParams = $this->_getForumModel()->getAttachmentParams($forum, array(
			'thread_id' => $thread['thread_id']
		), null, null, $attachmentHash);

		if ($thread['discussion_type'] == 'poll')
		{
			$pollModel = $this->_getPollModel();
			$poll = $pollModel->getPollByContent('thread', $threadId);
			if ($poll)
			{
				$poll = $pollModel->preparePoll($poll, $threadModel->canVoteOnPoll($thread, $forum));
				$poll['canEdit'] = $threadModel->canEditPoll($thread, $forum);
			}
		}
		else
		{
			$poll = false;
		}

		$threadModel->markThreadRead($thread, $forum, $maxPostDate);
		$threadModel->logThreadView($threadId);

		$viewParams = $this->_getDefaultViewParams($forum, $thread, $posts, $page, array(
			'deletedPosts' => $deletedPosts,
			'moderatedPosts' => $moderatedPosts,

			'inlineModOptions' => $inlineModOptions,

			'firstPost' => reset($posts),
			'lastPost' => end($posts),
			'unreadLink' => $unreadLink,

			'poll' => $poll,

			'attachmentParams' => $attachmentParams,
			'attachmentConstraints' => $this->_getAttachmentModel()->getAttachmentConstraints(),

			'showPostedNotice' => $this->_input->filterSingle('posted', XenForo_Input::UINT),

			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
		));

		return $this->responseView('XenForo_ViewPublic_Thread_View', 'thread_view', $viewParams);
	}

	protected function _getThreadForumFetchOptions()
	{
		$visitor = XenForo_Visitor::getInstance();

		$threadFetchOptions = array(
			'readUserId' => $visitor['user_id'],
			'watchUserId' => $visitor['user_id'],
			'draftUserId' => $visitor['user_id'],
			'join' => XenForo_Model_Thread::FETCH_AVATAR
		);
		$forumFetchOptions = array(
			'readUserId' => $visitor['user_id']
		);

		return array($threadFetchOptions, $forumFetchOptions);
	}

	protected function _getPostFetchOptions(array $thread, array $forum)
	{
		$postFetchOptions = $this->_getPostModel()->getPermissionBasedPostFetchOptions($thread, $forum) + array(
			'join' => XenForo_Model_Post::FETCH_USER | XenForo_Model_Post::FETCH_USER_PROFILE | XenForo_Model_Post::FETCH_BBCODE_CACHE,
			'likeUserId' => XenForo_Visitor::getUserId()
		);
		if (!empty($postFetchOptions['deleted']))
		{
			$postFetchOptions['join'] |= XenForo_Model_Post::FETCH_DELETION_LOG;
		}

		return $postFetchOptions;
	}

	/**
	 * Gets a preview of the first post in a thread.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPreview()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$visitor = XenForo_Visitor::getInstance();

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$threadModel = $this->_getThreadModel();
		$postModel = $this->_getPostModel();

		if ($threadModel->isRedirect($thread))
		{
			return $this->responseView('XenForo_ViewPublic_Thread_Preview', '', array('post' => false));
		}

		$post = $postModel->getPostById($thread['first_post_id'], array(
			'join' => XenForo_Model_Post::FETCH_USER
		));
		if ($post['thread_id'] != $threadId || !$postModel->canViewPost($post, $thread, $forum))
		{
			return $this->responseView('XenForo_ViewPublic_Thread_Preview', '', array('post' => false));
		}

		$viewParams = array(
			'post' => $post,
			'thread' => $thread,
			'forum' => $forum
		);

		return $this->responseView('XenForo_ViewPublic_Thread_Preview', 'thread_list_item_preview', $viewParams);
	}

	/**
	 * Jumps to the first unread post in the thread.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionUnread()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		$visitorUserId = XenForo_Visitor::getUserId();
		$visitor = XenForo_Visitor::getInstance();

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$threadFetchOptions = array('readUserId' => $visitorUserId);
		$forumFetchOptions = array('readUserId' => $visitorUserId);
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId, $threadFetchOptions, $forumFetchOptions);

		if (!$visitorUserId)
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('threads', $thread)
			);
		}

		$readDate = $this->_getThreadModel()->getMaxThreadReadDate($thread, $forum);
		$postModel = $this->_getPostModel();

		$ignoredUserIds = (!empty($visitor['ignored']) ? unserialize($visitor['ignored']) : array());
		$ignoredUserIds = array_keys($ignoredUserIds);

		$fetchOptions = $postModel->getPermissionBasedPostFetchOptions($thread, $forum);
		$firstUnread = $postModel->getNextPostInThread($threadId, $readDate, $fetchOptions, $ignoredUserIds);
		if (!$firstUnread)
		{
			$firstUnread = $postModel->getLastPostInThread($threadId, $fetchOptions);
		}

		if ($firstUnread)
		{
			$page = floor($firstUnread['position'] / XenForo_Application::get('options')->messagesPerPage) + 1;

			$hashTag = ($firstUnread['position'] > 0 ? '#post-' . $firstUnread['post_id'] : '');

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('threads', $thread, array('page' => $page)) . $hashTag
			);
		}
		else
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Link::buildPublicLink('threads', $thread)
			);
		}
	}

	public function actionShowPosts()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$visitor = XenForo_Visitor::getInstance();

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$threadFetchOptions = array('readUserId' => $visitor['user_id']);
		$forumFetchOptions = array('readUserId' => $visitor['user_id']);
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId, $threadFetchOptions, $forumFetchOptions);

		$page = $this->_input->filterSingle('page', XenForo_Input::UINT);
		$postsPerPage = XenForo_Application::get('options')->messagesPerPage;

		$threadModel = $this->_getThreadModel();
		$postModel = $this->_getPostModel();

		$postFetchOptions = $this->_getPostFetchOptions($thread, $forum);
		$postFetchOptions += array(
			'perPage' => $postsPerPage,
			'page' => $page
		);

		$postIds = $this->_input->filterSingle('messageIds', array(XenForo_Input::STRING, 'array' => true));
		$postIds = array_map('intval', preg_replace('/^post-(\d+)$/', '\1', $postIds));

		if ($extraPostId = $this->_input->filterSingle('post_id', XenForo_Input::UINT))
		{
			$postIds[] = $extraPostId;
		}

		$posts = $postModel->getPostsByIds($postIds, $postFetchOptions);
		$posts = $postModel->getAndMergeAttachmentsIntoPosts($posts);

		$inlineModOptions = array();
		$maxPostDate = 0;

		$permissions = $visitor->getNodePermissions($thread['node_id']);
		foreach ($posts AS $key => &$post)
		{
			// only allow posts from the specified thread to be loaded (permissions reasons)
			if ($post['thread_id'] != $thread['thread_id'])
			{
				unset($posts[$key]);
				continue;
			}

			$post = $postModel->preparePost($post, $thread, $forum, $permissions);
		}

		if (empty($posts))
		{
			return $this->responseError(new XenForo_Phrase('no_posts_matching_criteria_specified_were_found'));
		}

		$viewParams = $this->_getDefaultViewParams($forum, $thread, $posts, $page, array(
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
		));

		return $this->responseView('XenForo_ViewPublic_Thread_ViewPosts', '', $viewParams);
	}

	public function actionShowNewPosts()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$visitor = XenForo_Visitor::getInstance();

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$threadFetchOptions = array('readUserId' => $visitor['user_id']);
		$forumFetchOptions = array('readUserId' => $visitor['user_id']);
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId, $threadFetchOptions, $forumFetchOptions);

		if (!$this->_request->isXmlHttpRequest())
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL_PERMANENT,
				XenForo_Link::buildPublicLink('threads', $thread)
			);
		}

		$lastDate = $this->_input->filterSingle('last_date', XenForo_Input::UINT);
		$viewParams = $this->_getNewPosts($thread, $forum, $lastDate, 5);

		return $this->responseView(
			'XenForo_ViewPublic_Thread_ViewNewPosts',
			'thread_reply_new_posts',
			$viewParams
		);
	}

	/**
	 * Displays a form to add a reply to a thread.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionReply()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$this->_assertCanReplyToThread($thread, $forum);

		$quickReplyAttachmentHash = $this->_input->filterSingle('attachment_hash', XenForo_Input::STRING);

		$attachmentParams = $this->_getForumModel()->getAttachmentParams($forum, array(
			'thread_id' => $thread['thread_id']
		), null, null, $quickReplyAttachmentHash);

		$attachments = !empty($attachmentParams['attachments']) ? $attachmentParams['attachments'] : array();
		$defaultMessage = '';
		$quotePost = null;

		if ($quoteId = $this->_input->filterSingle('quote', XenForo_Input::UINT))
		{
			$postModel = $this->_getPostModel();
			$quotePost = $postModel->getPostById($quoteId, array(
				'join' => XenForo_Model_Post::FETCH_USER
			));
			if ($quotePost && $quotePost['thread_id'] == $threadId && $postModel->canViewPost($quotePost, $thread, $forum))
			{
				$defaultMessage = $postModel->getQuoteTextForPost($quotePost);
			}
		}
		else if ($this->_input->inRequest('more_options'))
		{
			$defaultMessage = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		}

		$viewParams = array(
			'post' => $quotePost,
			'thread' => $thread,
			'forum' => $forum,
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),

			'attachmentParams' => $attachmentParams,
			'attachments' => $attachments,
			'attachmentConstraints' => $this->_getAttachmentModel()->getAttachmentConstraints(),

			'defaultMessage' => $defaultMessage,

			'watchState' => $this->_getThreadWatchModel()->getThreadWatchStateForVisitor($threadId),

			'captcha' => XenForo_Captcha_Abstract::createDefault(),

			'canLockUnlockThread' => $this->_getThreadModel()->canLockUnlockThread($thread, $forum),
			'canStickUnstickThread' => $this->_getThreadModel()->canStickUnstickThread($thread, $forum)
		);

		return $this->responseView('XenForo_ViewPublic_Thread_Reply', 'thread_reply', $viewParams);
	}

	/**
	 * Inserts a new reply into an existing thread.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionAddReply()
	{
		$this->_assertPostOnly();

		if ($this->_input->inRequest('more_options'))
		{
			return $this->responseReroute(__CLASS__, 'reply');
		}

		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$visitor = XenForo_Visitor::getInstance();

		$ftpHelper = $this->getHelper('ForumThreadPost');
		$threadFetchOptions = array('readUserId' => $visitor['user_id']);
		$forumFetchOptions = array('readUserId' => $visitor['user_id']);
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId, $threadFetchOptions, $forumFetchOptions);

		$this->_assertCanReplyToThread($thread, $forum);

		if (!XenForo_Captcha_Abstract::validateDefault($this->_input))
		{
			return $this->responseCaptchaFailed();
		}

		$input = $this->_input->filter(array(
			'attachment_hash' => XenForo_Input::STRING,

			'watch_thread_state' => XenForo_Input::UINT,
			'watch_thread' => XenForo_Input::UINT,
			'watch_thread_email' => XenForo_Input::UINT,

			'_set' => array(XenForo_Input::UINT, 'array' => true),
			'discussion_open' => XenForo_Input::UINT,
			'sticky' => XenForo_Input::UINT,
		));
		$input['message'] = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$input['message'] = XenForo_Helper_String::autoLinkBbCode($input['message']);

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
		$writer->set('user_id', $visitor['user_id']);
		$writer->set('username', $visitor['username']);
		$writer->set('message', $input['message']);
		$writer->set('message_state', $this->_getPostModel()->getPostInsertMessageState($thread, $forum));
		$writer->set('thread_id', $threadId);
		$writer->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_ATTACHMENT_HASH, $input['attachment_hash']);
		$writer->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);
		$writer->setOption(XenForo_DataWriter_DiscussionMessage_Post::OPTION_MAX_TAGGED_USERS, $visitor->hasPermission('general', 'maxTaggedUsers'));

		$spamModel = $this->_getSpamPreventionModel();

		if (!$writer->hasErrors()
			&& $writer->get('message_state') == 'visible'
			&& $spamModel->visitorRequiresSpamCheck()
		)
		{
			$spamExtraParams = array(
				'permalink' => XenForo_Link::buildPublicLink('canonical:threads', $thread)
			);
			switch ($spamModel->checkMessageSpam($input['message'], $spamExtraParams, $this->_request))
			{
				case XenForo_Model_SpamPrevention::RESULT_MODERATED:
					$writer->set('message_state', 'moderated');
					break;

				case XenForo_Model_SpamPrevention::RESULT_DENIED;
					$writer->error(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'));
					break;
			}
		}

		$writer->preSave();

		if (!$writer->hasErrors())
		{
			$this->assertNotFlooding('post');
		}

		$writer->save();
		$post = $writer->getMergedData();

		$spamModel->logContentSpamCheck('post', $post['post_id']);
		$this->_getDraftModel()->deleteDraft('thread-' . $thread['thread_id']);

		$this->_getThreadWatchModel()->setVisitorThreadWatchStateFromInput($threadId, $input);

		$threadUpdateData = array();

		if (!empty($input['_set']['discussion_open']) && $this->_getThreadModel()->canLockUnlockThread($thread, $forum))
		{
			if ($thread['discussion_open'] != $input['discussion_open'])
			{
				$threadUpdateData['discussion_open'] = $input['discussion_open'];
			}
		}

		// discussion sticky state - moderator permission required
		if (!empty($input['_set']['sticky']) && $this->_getForumModel()->canStickUnstickThreadInForum($forum))
		{
			if ($thread['sticky'] != $input['sticky'])
			{
				$threadUpdateData['sticky'] = $input['sticky'];
			}
		}

		if ($threadUpdateData)
		{
			$threadWriter = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
			$threadWriter->setExistingData($thread['thread_id']);
			$threadWriter->bulkSet($threadUpdateData);
			$threadWriter->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forum);
			$threadWriter->save();
		}

		$canViewPost = $this->_getPostModel()->canViewPost($post, $thread, $forum);

		$page = floor(($thread['reply_count'] + 1) / XenForo_Application::get('options')->messagesPerPage) + 1;

		// this is a standard redirect
		if (!$this->_noRedirect() || !$this->_input->inRequest('last_date') || !$canViewPost)
		{
			$this->_getThreadModel()->markThreadRead($thread, $forum, XenForo_Application::$time);

			if (!$canViewPost)
			{
				$return = XenForo_Link::buildPublicLink('threads', $thread, array('page' => $page, 'posted' => 1));
			}
			else
			{
				$return = XenForo_Link::buildPublicLink('posts', $post);
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				$return,
				new XenForo_Phrase('your_message_has_been_posted')
			);
		}
		else
		{
			// load a selection of posts that are newer than the last post viewed
			$lastDate = $this->_input->filterSingle('last_date', XenForo_Input::UINT);
			$viewParams = $this->_getNewPosts($thread, $forum, $lastDate, 3);

			return $this->responseView(
				'XenForo_ViewPublic_Thread_ViewNewPosts',
				'thread_reply_new_posts',
				$viewParams
			);
		}
	}

	protected function _getNewPosts(array $thread, array $forum, $lastDate, $limit = 3)
	{
		$postModel = $this->_getPostModel();
		$visitor = XenForo_Visitor::getInstance();

		$postFetchOptions = $this->_getPostFetchOptions($thread, $forum);
		$postFetchOptions += array(
			'limit' => ($limit + 1),
		);

		$posts = $postModel->getNewestPostsInThreadAfterDate(
			$thread['thread_id'], $lastDate, $postFetchOptions
		);

		// We fetched one more post than needed, if more than $limit posts were returned,
		// we can show the 'there are more posts' notice
		if (count($posts) > $limit)
		{
			$postPermissionOptions = $postModel->getPermissionBasedPostFetchOptions($thread, $forum);
			$firstUnshownPost = $postModel->getNextPostInThread($thread['thread_id'], $lastDate, $postPermissionOptions);

			// remove the extra post
			array_pop($posts);
		}
		else
		{
			$firstUnshownPost = false;
		}

		// put the posts into oldest-first order
		$posts = array_reverse($posts, true);

		$posts = $postModel->getAndMergeAttachmentsIntoPosts($posts);

		$permissions = $visitor->getNodePermissions($thread['node_id']);

		foreach ($posts AS &$post)
		{
			$post = $postModel->preparePost($post, $thread, $forum, $permissions);
		}

		// mark thread as read if we're showing the remaining posts in it or they've been read
		if ($visitor['user_id'])
		{
			if (!$firstUnshownPost || $firstUnshownPost['post_date'] <= $thread['thread_read_date'])
			{
				$this->_getThreadModel()->markThreadRead($thread, $forum, XenForo_Application::$time);
			}
		}

		$page = floor(($thread['reply_count'] + 1) / XenForo_Application::get('options')->messagesPerPage) + 1;

		return $this->_getDefaultViewParams($forum, $thread, $posts, $page, array(
			'firstUnshownPost' => $firstUnshownPost,
			'lastPost' => end($posts),
		));
	}

	/**
	 * Shows a preview of the reply.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionReplyPreview()
	{
		$this->_assertPostOnly();

		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$this->_assertCanReplyToThread($thread, $forum);

		$message = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$message = XenForo_Helper_String::autoLinkBbCode($message);

		/** @var $taggingModel XenForo_Model_UserTagging */
		$taggingModel = $this->getModelFromCache('XenForo_Model_UserTagging');
		$taggingModel->getTaggedUsersInMessage($message, $message);

		$viewParams = array(
			'thread' => $thread,
			'forum' => $forum,
			'message' => $message
		);

		return $this->responseView('XenForo_ViewPublic_Thread_ReplyPreview', 'thread_reply_preview', $viewParams);
	}

	public function actionSaveDraft()
	{
		$this->_assertPostOnly();

		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$this->_assertCanReplyToThread($thread, $forum);

		$extra = array(
			'attachment_hash' => $this->_input->filterSingle('attachment_hash', XenForo_Input::STRING)
		);
		$message = $this->getHelper('Editor')->getMessageText('message', $this->_input);
		$forceDelete = $this->_input->filterSingle('delete_draft', XenForo_Input::BOOLEAN);

		if (!strlen($message) || $forceDelete)
		{
			$draftSaved = false;
			$draftDeleted = $this->_getDraftModel()->deleteDraft("thread-$thread[thread_id]") || $forceDelete;
		}
		else
		{
			$this->_getDraftModel()->saveDraft("thread-$thread[thread_id]", $message, $extra);
			$draftSaved = true;
			$draftDeleted = false;
		}

		$lastDate = $this->_input->filterSingle('last_date', XenForo_Input::UINT);
		$lastKnownDate = $this->_input->filterSingle('last_known_date', XenForo_Input::UINT);
		$lastKnownDate = max($lastDate, $lastKnownDate);

		if ($lastDate)
		{
			$newPostCount = count($this->_getPostModel()->getNewestPostsInThreadAfterDate($threadId, $lastKnownDate));
		}
		else
		{
			$newPostCount = 0;
		}

		$viewParams = array(
			'thread' => $thread,
			'newPostCount' => $newPostCount,
			'lastDate' => $lastDate,
			'draftSaved' => $draftSaved,
			'draftDeleted' => $draftDeleted
		);
		return $this->responseView('XenForo_ViewPublic_Thread_SaveDraft', 'thread_save_draft', $viewParams);
	}

	/**
	 * Displays a form to edit a thread.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEdit()
	{
		$this->_assertRegistrationRequired();

		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$this->_assertCanEditThread($thread, $forum);

		$threadModel = $this->_getThreadModel();

		$viewParams = array(
			'thread' => $thread,
			'forum' => $forum,

			'prefixes' => $this->_getPrefixModel()->getUsablePrefixesInForums($forum['node_id']),

			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),

			'canLockUnlockThread' => $threadModel->canLockUnlockThread($thread, $forum),
			'canStickUnstickThread' => $threadModel->canStickUnstickThread($thread, $forum),

			'canDeleteThread' => $threadModel->canDeleteThread($thread, $forum, 'soft'),
			'canHardDeleteThread' => $threadModel->canDeleteThread($thread, $forum, 'hard'),

			'canAlterState' => array(
				'visible' => $threadModel->canAlterThreadState($thread, $forum, 'visible'),
				'moderated' => $threadModel->canAlterThreadState($thread, $forum, 'moderated'),
				'deleted' => $threadModel->canAlterThreadState($thread, $forum, 'deleted'),
			),

			'showForumLink' => $this->_input->filterSingle('showForumLink', XenForo_Input::BOOLEAN)
		);

		if ($this->_input->filterSingle('_listItemEdit', XenForo_Input::UINT))
		{
			return $this->responseView('XenForo_ViewPublic_Thread_ListItemEdit', 'thread_list_item_edit', $viewParams);
		}
		else
		{
			return $this->responseView('XenForo_ViewPublic_Thread_Edit', 'thread_edit', $viewParams);
		}
	}

	/**
	 * Displays a form to edit a thread title.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionEditTitle()
	{
		$this->_assertRegistrationRequired();

		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$threadModel = $this->_getThreadModel();

		if (!$threadModel->canEditThreadTitle($thread, $forum, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		if ($this->isConfirmedPost())
		{
			$dwInput = $this->_input->filter(array(
				'title' => XenForo_Input::STRING,
				'prefix_id' => XenForo_Input::UINT
			));

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
			$dw->setExistingData($threadId);
			$dw->bulkSet($dwInput);
			$dw->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forum);
			$dw->preSave();

			if ($forum['require_prefix'] && !$dw->get('prefix_id'))
			{
				$dw->error(new XenForo_Phrase('please_select_a_prefix'), 'prefix_id');
			}

			$dw->save();

			$this->_updateModeratorLogThreadEdit($thread, $dw);
			$thread = $dw->getMergedData();

			// regular redirect
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('threads', $thread)
			);
		}
		else
		{
			$viewParams = array(
				'thread' => $thread,
				'forum' => $forum,

				'prefixes' => $this->_getPrefixModel()->getUsablePrefixesInForums($forum['node_id']),

				'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
			);

			return $this->responseView('XenForo_ViewPublic_Thread_EditTitle', 'thread_edit_title', $viewParams);
		}
	}

	/**
	 * Alternative route into actionEdit - adding a _listItemEdit parameter
	 *
	 * @return XenForo_ControllerResponse_Reroute
	 */
	public function actionListItemEdit()
	{
		$this->_request->setParam('_listItemEdit', true);

		return $this->responseReroute('XenForo_ControllerPublic_Thread', 'edit');
	}

	/**
	 * Updates an existing thread.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionSave()
	{
		$this->_assertPostOnly();
		$this->_assertRegistrationRequired();

		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$this->_assertCanEditThread($thread, $forum);

		$dwInput = $this->_input->filter(array(
			'title' => XenForo_Input::STRING,
			'prefix_id' => XenForo_Input::UINT,
			'discussion_state' => XenForo_Input::STRING,
			'discussion_open' => XenForo_Input::UINT,
			'sticky' => XenForo_Input::UINT
		));

		$threadModel = $this->_getThreadModel();

		if (!$threadModel->canLockUnlockThread($thread, $forum))
		{
			unset($dwInput['discussion_open']);
		}

		if (!$threadModel->canStickUnstickThread($thread, $forum))
		{
			unset($dwInput['sticky']);
		}

		if (!$threadModel->canAlterThreadState($thread, $forum, $dwInput['discussion_state']))
		{
			unset($dwInput['discussion_state']);
		}

		if (!$this->_getPrefixModel()->verifyPrefixIsUsable($dwInput['prefix_id'], $forum['node_id']))
		{
			$dwInput['prefix_id'] = 0; // not usable, just blank it out
		}

		// TODO: check prefix requirements?

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
		$dw->setExistingData($threadId);
		$dw->bulkSet($dwInput);
		$dw->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forum);
		$dw->save();

		$this->_updateModeratorLogThreadEdit($thread, $dw);

		// special case for the discussion list inline editor
		if ($this->_input->filterSingle('_returnDiscussionListItem', XenForo_Input::UINT))
		{
			$visitorUserId = XenForo_Visitor::getUserId();

			$threadFetchOptions = array(
				'readUserId' => $visitorUserId,
				'postCountUserId' => $visitorUserId,
				'watchUserId' => $visitorUserId,
				'join' => XenForo_Model_Thread::FETCH_USER | XenForo_Model_Thread::FETCH_DELETION_LOG
			);
			$forumFetchOptions = array(
				'readUserId' => $visitorUserId
			);

			list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId, $threadFetchOptions, $forumFetchOptions);

			$thread['forum'] = $forum;

			$viewParams = array(
				'thread' => $thread,
				'forum' => $forum,
				'showForumLink' => $this->_input->filterSingle('showForumLink', XenForo_Input::BOOLEAN)
			);

			return $this->responseView('XenForo_ViewPublic_Thread_Save_ThreadListItem', 'thread_list_item', $viewParams);
		}

		$thread = $dw->getMergedData();

		// regular redirect
		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('threads', $thread)
		);
	}

	/**
	 * Logs the moderator actions for thread edits.
	 *
	 * @param array $thread
	 * @param XenForo_DataWriter_Discussion_Thread $dw
	 * @param array $skip Array of keys to skip logging for
	 */
	protected function _updateModeratorLogThreadEdit(array $thread, XenForo_DataWriter_Discussion_Thread $dw, array $skip = array())
	{
		$newData = $dw->getMergedNewData();
		if ($newData)
		{
			$oldData = $dw->getMergedExistingData();
			$basicLog = array();

			foreach ($newData AS $key => $value)
			{
				$oldValue = (isset($oldData[$key]) ? $oldData[$key] : '-');
				switch ($key)
				{
					case 'sticky':
						XenForo_Model_Log::logModeratorAction('thread', $thread, ($value ? 'stick' : 'unstick'));
						break;

					case 'discussion_open':
						XenForo_Model_Log::logModeratorAction('thread', $thread, ($value ? 'unlock' : 'lock'));
						break;

					case 'discussion_state':
						if ($value == 'visible' && $oldValue == 'moderated')
						{
							XenForo_Model_Log::logModeratorAction('thread', $thread, 'approve');
						}
						else if ($value == 'visible' && $oldValue == 'deleted')
						{
							XenForo_Model_Log::logModeratorAction('thread', $thread, 'undelete');
						}
						else if ($value == 'deleted')
						{
							XenForo_Model_Log::logModeratorAction(
								'thread', $thread, 'delete_soft', array('reason' => '')
							);
						}
						else if ($value == 'moderated')
						{
							XenForo_Model_Log::logModeratorAction('thread', $thread, 'unapprove');
						}
						break;

					case 'title':
						XenForo_Model_Log::logModeratorAction(
							'thread', $thread, 'title', array('old' => $oldValue)
						);
						break;

					case 'prefix_id':
						if ($oldValue)
						{
							$phrase = new XenForo_Phrase('thread_prefix_' . $oldValue);
							$oldValue = $phrase->render();
						}
						else
						{
							$oldValue = '-';
						}
						XenForo_Model_Log::logModeratorAction(
							'thread', $thread, 'prefix', array('old' => $oldValue)
						);
						break;

					default:
						if (!in_array($key, $skip))
						{
							$basicLog[$key] = $oldValue;
						}
				}
			}

			if ($basicLog)
			{
				XenForo_Model_Log::logModeratorAction('thread', $thread, 'edit', $basicLog);
			}
		}
	}

	public function actionQuickUpdate()
	{
		$this->_assertPostOnly();
		$this->_assertRegistrationRequired();

		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$threadModel = $this->_getThreadModel();

		$input = $this->_input->filter(array(
			'discussion_open' => XenForo_Input::UINT,
			'sticky' => XenForo_Input::UINT,
		));

		$set = $this->_input->filterSingle('set', XenForo_Input::ARRAY_SIMPLE, array('array' => true));

		$dwInput = array();

		if (isset($set['discussion_open']) && $threadModel->canLockUnlockThread($thread, $forum))
		{
			$dwInput['discussion_open'] = $input['discussion_open'];
		}

		if (isset($set['sticky']) && $threadModel->canStickUnstickThread($thread, $forum))
		{
			$dwInput['sticky'] = $input['sticky'];
		}

		if ($dwInput)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
			$dw->setExistingData($threadId);
			$dw->bulkSet($dwInput);
			$dw->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forum);
			$dw->save();

			$this->_updateModeratorLogThreadEdit($thread, $dw);
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('threads', $thread)
		);
	}

	/**
	 * Deletes an existing thread.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionDelete()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$threadModel = $this->_getThreadModel();

		$hardDelete = $this->_input->filterSingle('hard_delete', XenForo_Input::UINT);
		$deleteType = ($hardDelete ? 'hard' : 'soft');

		$this->_assertCanDeleteThread($thread, $forum, $deleteType);

		if ($this->isConfirmedPost()) // delete the thread
		{
			$options = array(
				'reason' => $this->_input->filterSingle('reason', XenForo_Input::STRING)
			);

			$threadModel->deleteThread($threadId, $deleteType, $options);

			XenForo_Model_Log::logModeratorAction(
				'thread', $thread, 'delete_' . $deleteType, array('reason' => $options['reason'])
			);

			XenForo_Helper_Cookie::clearIdFromCookie($threadId, 'inlinemod_threads');

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('forums', $forum)
			);
		}
		else // show a delete confirmation dialog
		{
			return $this->responseView(
				'XenForo_ViewPublic_Thread_Delete',
				'thread_delete',
				array(
					'thread' => $thread,
					'forum' => $forum,
					'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),

					'canHardDelete' => $threadModel->canDeleteThread($thread, $forum, 'hard'),
				)
			);
		}
	}

	/**
	 * Moves a thread to a different forum.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionMove()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$threadModel = $this->_getThreadModel();

		if (!$threadModel->canMoveThread($thread, $forum, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		if ($this->isConfirmedPost()) // move the thread
		{
			$input = $this->_input->filter(array(
				'node_id' => XenForo_Input::UINT,
				'title' => XenForo_Input::STRING,
				'prefix_id' => XenForo_Input::UINT,

				'create_redirect' => XenForo_Input::STRING,
				'redirect_ttl_value' => XenForo_Input::UINT,
				'redirect_ttl_unit' => XenForo_Input::STRING
			));
			$inputTitle = $this->_input->filterSingle('title', XenForo_Input::STRING);

			$viewableNodes = $this->getModelFromCache('XenForo_Model_Node')->getViewableNodeList();
			if (isset($viewableNodes[$input['node_id']]))
			{
				$targetNode = $viewableNodes[$input['node_id']];
			}
			else
			{
				return $this->responseNoPermission();
			}

			if ($input['create_redirect'] == 'permanent')
			{
				$options = array('redirect' => true, 'redirectExpiry' => 0);
			}
			else if ($input['create_redirect'] == 'expiring')
			{
				$expiryDate = strtotime('+' . $input['redirect_ttl_value'] . ' ' . $input['redirect_ttl_unit']);
				$options = array('redirect' => true, 'redirectExpiry' => $expiryDate);
			}
			else
			{
				$options = array('redirect' => false);
			}

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
			$dw->setExistingData($threadId);
			$dw->set('node_id', $input['node_id']);
			if ($this->_getThreadModel()->canEditThread($thread, $forum) && $input['title'] !== '')
			{
				if (!$this->_getPrefixModel()->verifyPrefixIsUsable($input['prefix_id'], $input['node_id']))
				{
					$input['prefix_id'] = 0; // not usable, just blank it out
				}

				$dw->set('title', $input['title']);
				$dw->set('prefix_id', $input['prefix_id']);
			}
			$dw->save();

			XenForo_Model_Log::logModeratorAction('thread', $thread, 'move', array('from' => $forum['title']));
			$this->_updateModeratorLogThreadEdit($thread, $dw, array('node_id'));

			if ($dw->isChanged('node_id') && $options['redirect'])
			{
				$this->getModelFromCache('XenForo_Model_ThreadRedirect')->createRedirectThread(
					XenForo_Link::buildPublicLink('threads', $thread), $thread,
					"thread-$thread[thread_id]-$thread[node_id]-", $options['redirectExpiry']
				);
			}

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('threads', $thread)
			);
		}
		else
		{
			return $this->responseView(
				'XenForo_ViewPublic_Thread_Move',
				'thread_move',
				array(
					'thread' => $thread,
					'forum' => $forum,
					'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),

					// we're showing the current forum prefixes intentionally; it's the best option right now
					'prefixes' => $this->_getPrefixModel()->getUsablePrefixesInForums($forum['node_id']),
					'forcePrefixes' => (XenForo_Application::get('threadPrefixes') ? true : false),

					'firstThread' => $thread,
					'nodeOptions' => $this->getModelFromCache('XenForo_Model_Node')->getViewableNodeList(),

					'canEditTitle' => $this->_getThreadModel()->canEditThread($thread, $forum)
				)
			);
		}
	}

	/**
	 * Displays a confirmation of watching (or stopping the watch of) a thread.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionWatchConfirm()
	{
		$ftpHelper = $this->getHelper('ForumThreadPost');

		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		if (!$this->_getThreadModel()->canWatchThread($thread, $forum))
		{
			return $this->responseNoPermission();
		}

		$threadWatch = $this->getModelFromCache('XenForo_Model_ThreadWatch')->getUserThreadWatchByThreadId(
			XenForo_Visitor::getUserId(), $thread['thread_id']
		);

		$viewParams = array(
			'thread' => $thread,
			'forum' => $forum,
			'threadWatch' => $threadWatch,
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
		);

		return $this->responseView('XenForo_ViewPublic_Thread_Watch', 'thread_watch', $viewParams);
	}

	/**
	 * Inserts/updates/deletes a thread watch.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionWatch()
	{
		$this->_assertPostOnly();

		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		list($thread, $forum) = $this->getHelper('ForumThreadPost')->assertThreadValidAndViewable($threadId);

		if (!$this->_getThreadModel()->canWatchThread($thread, $forum))
		{
			return $this->responseNoPermission();
		}

		if ($this->_input->filterSingle('stop', XenForo_Input::STRING))
		{
			$newState = '';
		}
		else if ($this->_input->filterSingle('email_subscribe', XenForo_Input::UINT))
		{
			$newState = 'watch_email';
		}
		else
		{
			$newState = 'watch_no_email';
		}

		$this->_getThreadWatchModel()->setThreadWatchState(XenForo_Visitor::getUserId(), $threadId, $newState);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('threads', $thread),
			null,
			array('linkPhrase' => ($newState ? new XenForo_Phrase('unwatch_thread') : new XenForo_Phrase('watch_thread')))
		);
	}

	/**
	 * Votes on the poll in this thread.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPollVote()
	{
		$this->_assertPostOnly();

		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$pollModel = $this->_getPollModel();
		$poll = $pollModel->getPollByContent('thread', $threadId);
		if (!$poll || !$this->_getThreadModel()->canVoteOnPoll($thread, $forum))
		{
			return $this->responseNoPermission();
		}

		if (!$pollModel->canVoteOnPoll($poll, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		if ($poll['multiple'])
		{
			$responses = $this->_input->filterSingle('response_multiple', XenForo_Input::UINT, array('array' => true));
		}
		else
		{
			$responses = $this->_input->filterSingle('response', XenForo_Input::UINT);
		}

		if (!$responses)
		{
			return $this->responseError(new XenForo_Phrase('please_vote_for_at_least_one_option'));
		}

		$pollModel->voteOnPoll($poll['poll_id'], $responses);

		if ($this->_noRedirect())
		{
			return $this->responseReroute(__CLASS__, 'pollResults');
		}

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildPublicLink('threads', $thread)
		);
	}

	/**
	 * Views the results of the poll in this thread. Also doubles as viewing voters
	 * for a particular response.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPollResults()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$pollModel = $this->_getPollModel();
		$poll = $pollModel->getPollByContent('thread', $threadId);
		if (!$poll)
		{
			return $this->responseNoPermission();
		}

		$poll = $pollModel->preparePoll($poll, false);
		$poll['canEdit'] = $this->_getThreadModel()->canEditPoll($thread, $forum);

		$pollResponseId = $this->_input->filterSingle('poll_response_id', XenForo_Input::UINT);
		if ($pollResponseId)
		{
			if (!isset($poll['responses'][$pollResponseId]) || !$poll['public_votes'])
			{
				return $this->responseNoPermission();
			}
			else
			{
				$viewParams = array(
					'forum' => $forum,
					'thread' => $thread,
					'poll' => $poll,
					'response' => $poll['responses'][$pollResponseId],
					'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),
				);

				return $this->responseView('XenForo_ViewPublic_Thread_PollVoters', 'thread_poll_voters', $viewParams);
			}
		}

		$viewParams = array(
			'thread' => $thread,
			'forum' => $forum,
			'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),

			'poll' => $poll
		);

		return $this->responseView('XenForo_ViewPublic_Thread_PollResults', 'thread_poll_results', $viewParams);
	}

	/**
	 * Edits the poll in this thread.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionPollEdit()
	{
		$threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

		$ftpHelper = $this->getHelper('ForumThreadPost');
		list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

		$pollModel = $this->_getPollModel();
		$poll = $pollModel->getPollByContent('thread', $threadId);
		if (!$poll)
		{
			return $this->responseNoPermission();
		}

		if (!$this->_getThreadModel()->canEditPoll($thread, $forum, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}

		$canEditMultiple = (!$poll['multiple'] || !$poll['voter_count']);
		$canEditPublic = ($poll['public_votes'] || !$poll['voter_count']);
		$canDisableCloseDate = ($poll['close_date'] ? true : false);

		if ($this->_request->isPost())
		{
			$input = $this->_input->filter(array(
				'question' => XenForo_Input::STRING,
				'existing_responses' => array(XenForo_Input::STRING, 'array' => true),
				'new_responses' => array(XenForo_Input::STRING, 'array' => true),
				'multiple' => XenForo_Input::UINT,
				'public_votes' => XenForo_Input::UINT,
				'close_date' => XenForo_Input::UINT,

				'close' => XenForo_Input::UINT,
				'close_length' => XenForo_Input::UINT,
				'close_units' => XenForo_Input::STRING
			));

			$responses = $pollModel->getPollResponsesInPoll($poll['poll_id']);

			XenForo_Db::beginTransaction();

			$pollDw = XenForo_DataWriter::create('XenForo_DataWriter_Poll');
			$pollDw->setExistingData($poll);
			$pollDw->set('question', $input['question']);
			if ($canEditMultiple)
			{
				$pollDw->set('multiple', $input['multiple']);
			}
			if ($canEditPublic)
			{
				$pollDw->set('public_votes', $input['public_votes']);
			}
			if ($canDisableCloseDate)
			{
				$pollDw->set('close_date', $input['close_date']);
			}
			else if ($input['close'])
			{
				$pollDw->set('close_date', $pollDw->preVerifyCloseDate(strtotime('+' . $input['close_length'] . ' ' . $input['close_units'])));
			}
			else
			{
				$pollDw->set('close_date', 0);
			}

			$deleteCount = 0;
			foreach ($responses AS $response)
			{
				if (!isset($input['existing_responses'][$response['poll_response_id']]))
				{
					continue;
				}

				$updateText = $input['existing_responses'][$response['poll_response_id']];

				$responseDw = XenForo_DataWriter::create('XenForo_DataWriter_PollResponse');
				$responseDw->setExistingData($response, true);
				if ($updateText === '')
				{
					$responseDw->delete();
					$deleteCount++;
				}
				else
				{
					$responseDw->set('response', $updateText);
					$responseDw->save();
				}
			}

			$pollDw->addResponses($input['new_responses']);
			if ($deleteCount == count($responses) && !$pollDw->hasNewResponses())
			{
				$pollDw->delete();
			}
			else
			{
				$pollDw->save();
			}

			XenForo_Model_Log::logModeratorAction('thread', $thread, 'poll_edit');

			XenForo_Db::commit();

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('threads', $thread)
			);
		}
		else
		{
			$viewParams = array(
				'thread' => $thread,
				'forum' => $forum,
				'nodeBreadCrumbs' => $ftpHelper->getNodeBreadCrumbs($forum),

				'poll' => $pollModel->preparePoll($poll, false),
				'canEditMultiple' => $canEditMultiple,
				'canEditPublic' => $canEditPublic,
				'canDisableCloseDate' => $canDisableCloseDate
			);

			return $this->responseView('XenForo_ViewPublic_Thread_PollEdit', 'thread_poll_edit', $viewParams);
		}
	}

	/**
	 * Fetches the default set of view parameters required to view posts in a thread.
	 *
	 * @param array $forum The forums that contains the posts.
	 * @param array $thread The thread that contains the posts.
	 * @param array $posts Array of individual posts to be displayed.
	 * @param integer $page The current page number.
	 * @param array $viewParams Optional array of additional view parameters.
	 *
	 * @return array
	 */
	protected function _getDefaultViewParams(array $forum, array $thread, array $posts, $page = 1, array $viewParams = array())
	{
		$threadModel = $this->_getThreadModel();

		$page = max(1, $page);
		$postsPerPage = XenForo_Application::get('options')->messagesPerPage;

		return array(
			'thread' => $thread,
			'forum' => $forum,
			'posts' => $posts,

			'ignoredNames' => $this->_getIgnoredContentUserNames($posts),

			'page' => $page,
			'postsPerPage' => $postsPerPage,
			'totalPosts' => $thread['reply_count'] + 1,
			'postsRemaining' => max(0, $thread['reply_count'] + 1 - ($page * $postsPerPage)),

			'canReply' => $threadModel->canReplyToThread($thread, $forum),
			'canQuickReply' => $threadModel->canQuickReply($thread, $forum),
			'canEditThread' => $threadModel->canEditThread($thread, $forum),
			'canEditTitle' => $threadModel->canEditThreadTitle($thread, $forum),
			'canDeleteThread' => $threadModel->canDeleteThread($thread, $forum, 'soft'),
			'canMoveThread' => $threadModel->canMoveThread($thread, $forum),
			'canStickUnstickThread' => $threadModel->canStickUnstickThread($thread, $forum),
			'canLockUnlockThread' => $threadModel->canLockUnlockThread($thread, $forum),
			'canWatchThread' => $threadModel->canWatchThread($thread, $forum),
			'canViewIps' => $threadModel->canViewIps($thread, $forum),
			'canViewAttachments' => $threadModel->canViewAttachmentsInThread($thread, $forum),
			'canViewWarnings' => $this->getModelFromCache('XenForo_Model_User')->canViewWarnings(),
			'watchState' => $threadModel->getThreadWatchStateFromThread($thread),
		) + $viewParams;
	}

	/**
	 * Asserts that the currently browsing user can reply to the specified thread.
	 *
	 * @param array $thread
	 * @param array $forum
	 */
	protected function _assertCanReplyToThread(array $thread, array $forum)
	{
		if (!$this->_getThreadModel()->canReplyToThread($thread, $forum, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	}

	/**
	 * Asserts that the currently browsing user can edit this thread.
	 *
	 * @param array $thread
	 * @param array $forum
	 */
	protected function _assertCanEditThread(array $thread, array $forum)
	{
		if (!$this->_getThreadModel()->canEditThread($thread, $forum, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	}

	/**
	 * Asserts that the currently browsing user can delete this thread.
	 *
	 * @param array $thread
	 * @param array $forum
	 * @param string $deleteType Type of deletion (soft or hard)
	 */
	protected function _assertCanDeleteThread(array $thread, array $forum, $deleteType)
	{
		if (!$this->_getThreadModel()->canDeleteThread($thread, $forum, $deleteType, $errorPhraseKey))
		{
			throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
		}
	}

	/**
	 * Session activity details.
	 * @see XenForo_Controller::getSessionActivityDetailsForList()
	 */
	public static function getSessionActivityDetailsForList(array $activities)
	{
		$threadIds = array();
		foreach ($activities AS $activity)
		{
			if (!empty($activity['params']['thread_id']))
			{
				$threadIds[$activity['params']['thread_id']] = intval($activity['params']['thread_id']);
			}
		}

		$threadData = array();

		if ($threadIds)
		{
			/* @var $threadModel XenForo_Model_Thread */
			$threadModel = XenForo_Model::create('XenForo_Model_Thread');

			$visitor = XenForo_Visitor::getInstance();
			$permissionCombinationId = $visitor['permission_combination_id'];

			$threads = $threadModel->getThreadsByIds($threadIds, array(
				'join' => XenForo_Model_Thread::FETCH_FORUM,
				'permissionCombinationId' => $permissionCombinationId
			));
			foreach ($threads AS $thread)
			{
				$visitor->setNodePermissions($thread['node_id'], $thread['node_permission_cache']);
				if ($threadModel->canViewThreadAndContainer($thread, $thread))
				{
					$thread['title'] = XenForo_Helper_String::censorString($thread['title']);

					$threadData[$thread['thread_id']] = array(
						'title' => $thread['title'],
						'url' => XenForo_Link::buildPublicLink('threads', $thread),
						'previewUrl' => ($threadModel->hasPreview($thread, $thread) ? XenForo_Link::buildPublicLink('threads/preview', $thread) : '')
					);
				}
			}
		}

		$output = array();
		foreach ($activities AS $key => $activity)
		{
			$thread = false;
			if (!empty($activity['params']['thread_id']))
			{
				$threadId = $activity['params']['thread_id'];
				if (isset($threadData[$threadId]))
				{
					$thread = $threadData[$threadId];
				}
			}

			if ($thread)
			{
				$output[$key] = array(
					new XenForo_Phrase('viewing_thread'),
					$thread['title'],
					$thread['url'],
					$thread['previewUrl']
				);
			}
			else
			{
				$output[$key] = new XenForo_Phrase('viewing_thread');
			}
		}

		return $output;
	}

	/**
	 * @return XenForo_Model_Forum
	 */
	protected function _getForumModel()
	{
		return $this->getModelFromCache('XenForo_Model_Forum');
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}

	/**
	 * @return XenForo_Model_ThreadWatch
	 */
	protected function _getThreadWatchModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadWatch');
	}

	/**
	 * @return XenForo_Model_Poll
	 */
	protected function _getPollModel()
	{
		return $this->getModelFromCache('XenForo_Model_Poll');
	}

	/**
	 * @return XenForo_Model_Post
	 */
	protected function _getPostModel()
	{
		return $this->getModelFromCache('XenForo_Model_Post');
	}

	/**
	 * @return XenForo_Model_ThreadPrefix
	 */
	protected function _getPrefixModel()
	{
		return $this->getModelFromCache('XenForo_Model_ThreadPrefix');
	}

	/**
	 * @return XenForo_Model_Attachment
	 */
	protected function _getAttachmentModel()
	{
		return $this->getModelFromCache('XenForo_Model_Attachment');
	}

	/**
	 * @return XenForo_Model_SpamPrevention
	 */
	protected function _getSpamPreventionModel()
	{
		return $this->getModelFromCache('XenForo_Model_SpamPrevention');
	}

	/**
	 * @return XenForo_Model_Draft
	 */
	protected function _getDraftModel()
	{
		return $this->getModelFromCache('XenForo_Model_Draft');
	}
}