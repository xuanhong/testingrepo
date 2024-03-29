<?php

class XenForo_Model_Warning extends XenForo_Model
{
	public function getWarningDefinitionById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_warning_definition
			WHERE warning_definition_id = ?
		', $id);
	}

	public function getWarningDefinitions()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_warning_definition
			ORDER BY points_default
		', 'warning_definition_id');
	}

	public function prepareWarningDefinition(array $warning, $includeConversationInfo = false)
	{
		$warning['title'] = new XenForo_Phrase($this->getWarningDefinitionTitlePhraseName($warning['warning_definition_id']));

		if ($includeConversationInfo)
		{
			$warning['conversationTitle'] =  new XenForo_Phrase(
				$this->getWarningDefinitionConversationTitlePhraseName($warning['warning_definition_id'])
			);
			$warning['conversationMessage'] =  new XenForo_Phrase(
				$this->getWarningDefinitionConversationTextPhraseName($warning['warning_definition_id'])
			);
		}

		return $warning;
	}

	public function prepareWarningDefinitions(array $warnings)
	{
		foreach ($warnings AS &$warning)
		{
			$warning = $this->prepareWarningDefinition($warning);
		}

		return $warnings;
	}

	public function getWarningDefinitionTitlePhraseName($id)
	{
		return 'warning_definition_' . $id . '_title';
	}

	public function getWarningDefinitionConversationTitlePhraseName($id)
	{
		return 'warning_definition_' . $id . '_conversation_title';
	}

	public function getWarningDefinitionConversationTextPhraseName($id)
	{
		return 'warning_definition_' . $id . '_conversation_text';
	}

	public function getWarningDefinitionMasterPhraseValues($id)
	{
		$phraseModel = $this->getModelFromCache('XenForo_Model_Phrase');

		return array(
			'title' => $phraseModel->getMasterPhraseValue($this->getWarningDefinitionTitlePhraseName($id)),
			'conversationTitle' => $phraseModel->getMasterPhraseValue($this->getWarningDefinitionConversationTitlePhraseName($id)),
			'conversationText' => $phraseModel->getMasterPhraseValue($this->getWarningDefinitionConversationTextPhraseName($id))
		);
	}

	public function getWarningActionById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT *
			FROM xf_warning_action
			WHERE warning_action_id = ?
		', $id);
	}

	public function getWarningActions()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_warning_action
			ORDER BY points
		', 'warning_action_id');
	}

	public function getWarningById($id)
	{
		return $this->_getDb()->fetchRow('
			SELECT warning.*, user.*, warn_user.username AS warn_username
			FROM xf_warning AS warning
			LEFT JOIN xf_user AS user ON (user.user_id = warning.user_id)
			LEFT JOIN xf_user AS warn_user ON (warn_user.user_id = warning.warning_user_id)
			WHERE warning.warning_id = ?
		', $id);
	}

	public function getWarningsByUser($userId)
	{
		return $this->fetchAllKeyed('
			SELECT warning.*, warn_user.username AS warn_username
			FROM xf_warning AS warning
			LEFT JOIN xf_user AS warn_user ON (warn_user.user_id = warning.warning_user_id)
			WHERE warning.user_id = ?
			ORDER BY warning.warning_date DESC
		', 'warning_id', $userId);
	}

	public function countWarningsByUser($userId)
	{
		return $this->_getDb()->fetchOne('
			SELECT COUNT(*)
			FROM xf_warning AS warning
			WHERE warning.user_id = ?
		', $userId);
	}

	public function prepareWarning(array $warning)
	{
		$handler = $this->getWarningHandler($warning['content_type']);
		if ($handler)
		{
			$warning = $handler->prepareWarning($warning);
		}

		return $warning;
	}

	public function prepareWarnings(array $warnings)
	{
		foreach ($warnings AS &$warning)
		{
			$warning = $this->prepareWarning($warning);
		}

		return $warnings;
	}

	/**
	 * Determines if a user can delete this warning
	 *
	 * @param array $warning
	 * @param string $errorPhraseKey
	 * @param array|null $viewingUser
	 *
	 * @return boolean
	 */
	public function canDeleteWarning(array $warning, &$errorPhraseKey = '', array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		if (!$viewingUser['user_id'])
		{
			return false;
		}

		if ($warning['warning_user_id'] == $viewingUser['user_id'])
		{
			return true;
		}

		return XenForo_Permission::hasPermission($viewingUser['permissions'], 'general', 'manageWarning');
	}

	public function userWarningPointsChanged($userId, $newPoints, $oldPoints)
	{
		if ($newPoints == $oldPoints)
		{
			return;
		}

		if ($newPoints > $oldPoints)
		{
			$this->_userWarningPointsIncreased($userId, $newPoints, $oldPoints);
		}
		else
		{
			$this->_userWarningPointsDecreased($userId, $newPoints, $oldPoints);
		}
	}

	protected function _userWarningPointsIncreased($userId, $newPoints, $oldPoints)
	{
		$actions = $this->getWarningActions();
		if (!$actions)
		{
			return;
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		foreach ($actions AS $action)
		{
			if ($action['points'] <= $oldPoints)
			{
				continue; // already triggered - not necessarily true when an action is added though, but probably ok
			}
			else if ($action['points'] > $newPoints)
			{
				continue; // no trigger yet
			}

			$this->triggerWarningAction($userId, $action);
		}

		XenForo_Db::commit($db);
	}

	protected function _userWarningPointsDecreased($userId, $newPoints, $oldPoints)
	{
		$triggers = $this->getUserWarningActionTriggers($userId);
		if (!$triggers)
		{
			return;
		}

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		foreach ($triggers AS $trigger)
		{
			if ($trigger['trigger_points'] > $newPoints)
			{
				// points have fallen below trigger, remove it
				$this->removeWarningActionTrigger($userId, $trigger);
			}
		}

		XenForo_Db::commit($db);
	}

	public function getUserWarningActionTriggers($userId)
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_warning_action_trigger
			WHERE user_id = ?
			ORDER BY trigger_points
		', 'action_trigger_id', $userId);
	}

	public function triggerWarningAction($userId, array $action)
	{
		$minUnbanDate = 0;
		$insertTrigger = true;

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		switch ($action['action'])
		{
			case 'ban_length':
				$newBanEnd = (
					$action['ban_length_type'] == 'permanent' ? 0
					: strtotime("+$action[ban_length] $action[ban_length_type]")
				);

				$ban = $this->getModelFromCache('XenForo_Model_Banning')->getBannedUserById($userId);
				if ($ban)
				{
					if (!$ban['end_date'] || ($ban['end_date'] > $newBanEnd && $newBanEnd > 0))
					{
						// perma-banned or longer ban - don't trigger
						// note that this could be a temp points ban, but we don't know how long that will last
						$insertTrigger = false;
						break;
					}
				}

				$dw = XenForo_DataWriter::create('XenForo_DataWriter_UserBan', XenForo_DataWriter::ERROR_SILENT);
				if ($ban)
				{
					$dw->setExistingData($ban);
				}
				else
				{
					$dw->set('user_id', $userId);
					$dw->set('ban_user_id', 0);
					$dw->set('user_reason', new XenForo_Phrase('warning_ban_reason'));
				}

				$dw->set('end_date', $newBanEnd);
				$dw->save();

				$insertTrigger = false;
				break;

			case 'ban_points':
				$ban = $this->getModelFromCache('XenForo_Model_Banning')->getBannedUserById($userId);
				if ($ban)
				{
					if (!$ban['end_date'])
					{
						// perma-banned already - maybe manual, maybe from warnings
						$insertTrigger = false;
						break;
					}
					else
					{
						// temp banned - this isn't from a points based warning
						$minUnbanDate = $ban['end_date'];
					}
				}

				$dw = XenForo_DataWriter::create('XenForo_DataWriter_UserBan', XenForo_DataWriter::ERROR_SILENT);
				if ($ban)
				{
					$dw->setExistingData($ban);
				}
				else
				{
					$dw->set('user_id', $userId);
					$dw->set('ban_user_id', 0);
					$dw->set('user_reason', new XenForo_Phrase('warning_ban_reason'));
				}

				$dw->set('end_date', 0);
				$dw->set('triggered', 1);
				$dw->save();

				break;

			case 'discourage':
				$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
				if ($userDw->setExistingData($userId))
				{
					$userDw->set('is_discouraged', 1);
					$userDw->save();
				}
				break;

			case 'groups':
				if ($action['extra_user_group_ids'])
				{
					$this->getModelFromCache('XenForo_Model_User')->addUserGroupChange(
						$userId, 'warning_action_' . $action['warning_action_id'], $action['extra_user_group_ids']
					);
				}
				else
				{
					$insertTrigger = false;
				}
				break;
		}

		$triggerId = 0;

		if ($insertTrigger)
		{
			$this->_getDb()->insert('xf_warning_action_trigger', array(
				'warning_action_id' => $action['warning_action_id'],
				'user_id' => $userId,
				'action_date' => XenForo_Application::$time,
				'trigger_points' => $action['points'],
				'action' => $action['action'],
				'min_unban_date' => $minUnbanDate
			));
			$triggerId = $this->_getDb()->lastInsertId();
		}

		XenForo_Db::commit($db);

		return $triggerId;

	}

	public function removeWarningActionTrigger($userId, array $trigger)
	{
		$triggers = $this->getUserWarningActionTriggers($userId);
		unset($triggers[$trigger['action_trigger_id']]);

		$db = $this->_getDb();
		XenForo_Db::beginTransaction($db);

		switch ($trigger['action'])
		{
			case 'ban_points':
				if ($trigger['min_unban_date'] && XenForo_Application::$time < $trigger['min_unban_date'])
				{
					// too soon to unban - another ban would out run this
					break;
				}

				// can only remove if last of the trigger type
				$remove = true;
				foreach ($triggers AS $otherTrigger)
				{
					if ($otherTrigger['action'] == 'ban_points')
					{
						$remove = false;
						break;
					}
				}
				if ($remove)
				{
					$ban = $this->getModelFromCache('XenForo_Model_Banning')->getBannedUserById($userId);
					if ($ban)
					{
						if ($ban['end_date'] != 0 || !$ban['triggered'])
						{
							// this isn't our ban, don't remove it
							break;
						}

						$this->getModelFromCache('XenForo_Model_User')->liftBan($userId);
					}
				}
				break;

			case 'discourage':
				// can only remove if this is the last of the trigger type
				$remove = true;
				foreach ($triggers AS $otherTrigger)
				{
					if ($otherTrigger['action'] == 'discourage')
					{
						$remove = false;
						break;
					}
				}
				if ($remove)
				{
					$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User', XenForo_DataWriter::ERROR_SILENT);
					if ($userDw->setExistingData($userId))
					{
						$userDw->set('is_discouraged', 0);
						$userDw->save();
					}
				}
				break;

			case 'groups':
				$this->getModelFromCache('XenForo_Model_User')->removeUserGroupChange(
					$userId, 'warning_action_' . $trigger['warning_action_id']
				);
				break;
		}

		$db->delete('xf_warning_action_trigger', 'action_trigger_id = ' . $db->quote($trigger['action_trigger_id']));

		XenForo_Db::commit($db);
	}

	/**
	 * Gets the warning handler for a specific type of content.
	 *
	 * @param string $contentType
	 *
	 * @return XenForo_WarningHandler_Abstract|false
	 */
	public function getWarningHandler($contentType)
	{
		$handlerClass = $this->getContentTypeField($contentType, 'warning_handler_class');
		if (!$handlerClass || !class_exists($handlerClass))
		{
			return false;
		}

		$handlerClass = XenForo_Application::resolveDynamicClass($handlerClass);
		return new $handlerClass();
	}

	/**
	 * Gets the warning handlers for all content types.
	 *
	 * @return array Array of XenForo_WarningHandler_Abstract objects
	 */
	public function getWarningHandlers()
	{
		$handlerClasses = $this->getContentTypesWithField('warning_handler_class');
		$handlers = array();
		foreach ($handlerClasses AS $contentType => $handlerClass)
		{
			if (!class_exists($handlerClass))
			{
				continue;
			}

			$handlerClass = XenForo_Application::resolveDynamicClass($handlerClass);
			$handlers[$contentType] = new $handlerClass();
		}

		return $handlers;
	}

	public function getExpiredWarnings()
	{
		return $this->fetchAllKeyed('
			SELECT *
			FROM xf_warning
			WHERE expiry_date < ?
				AND expiry_date > 0
				AND is_expired = 0
		', 'warning_id', XenForo_Application::$time);
	}

	public function processExpiredWarnings()
	{
		foreach ($this->getExpiredWarnings() AS $warning)
		{
			$dw = XenForo_DataWriter::create('XenForo_DataWriter_Warning', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($warning, true);
			$dw->set('is_expired', 1);
			$dw->save();
		}
	}

	public function getWarningDefinitionsForAdminQuickSearch(array $phraseMatches)
	{
		if ($phraseMatches)
		{
			return $this->fetchAllKeyed('
				SELECT *
				FROM xf_warning_definition
				WHERE warning_definition_id IN (' . $this->_getDb()->quote($phraseMatches) . ')
			', 'warning_definition_id');
		}

		return array();
	}
}