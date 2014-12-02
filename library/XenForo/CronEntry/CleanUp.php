<?php

/**
 * Cron entry for various scheduled data clean ups.
 */
class XenForo_CronEntry_CleanUp
{
	/**
	 * Clean up tasks that should be done hourly. This task cannot be relied on
	 * to run every hour, consistently.
	 */
	public static function runHourlyCleanUp()
	{
		// delete unassociated attachments
		$unassociatedAttachCutOff = XenForo_Application::$time - 86400;
		$attachmentModel = XenForo_Model::create('XenForo_Model_Attachment');
		$attachmentModel->deleteUnassociatedAttachments($unassociatedAttachCutOff);
		$attachmentModel->deleteUnusedAttachmentData();

		// delete expired sessions
		$class = XenForo_Application::resolveDynamicClass('XenForo_Session');
		$session = new $class();
		$session->deleteExpiredSessions();

		// delete expired admin sessions
		$session = new $class(array('admin' => true));
		$session->deleteExpiredSessions();

		// delete expired session activities
		$sessionModel = XenForo_Model::create('XenForo_Model_Session');
		$sessionCleanUpCutOff = XenForo_Application::$time - 3600;
		$sessionModel->updateUserLastActivityFromSessions();
		$sessionModel->deleteSessionActivityOlderThanCutOff($sessionCleanUpCutOff);

		// delete expired thread redirects
		$threadRedirectModel = XenForo_Model::create('XenForo_Model_ThreadRedirect');
		$redirects = $threadRedirectModel->getExpiredThreadRedirects(XenForo_Application::$time);
		$threadRedirectModel->deleteThreadRedirects(array_keys($redirects));

		XenForo_Model::create('XenForo_Model_Alert')->deleteOldReadAlerts();
		XenForo_Model::create('XenForo_Model_Alert')->deleteOldUnreadAlerts();

		XenForo_Model::create('XenForo_Model_NewsFeed')->deleteOldNewsFeedItems();

		XenForo_Model::create('XenForo_Model_Login')->cleanUpLoginAttempts();

		XenForo_Model::create('XenForo_Model_CaptchaQuestion')->deleteOldCaptchas();

		XenForo_Model::create('XenForo_Model_BbCode')->trimBbCodeCache();

		XenForo_Model::create('XenForo_Model_UserConfirmation')->cleanUpUserConfirmationRecords();

		XenForo_Model::create('XenForo_Model_SpamPrevention')->cleanupContentSpamCheck();
	}

	/**
	 * Clean up tasks that should be done daily. This task cannot be relied on
	 * to run daily, consistently.
	 */
	public static function runDailyCleanUp()
	{
		$db = XenForo_Application::getDb();

		// delete old thread/forum read marking data
		$readMarkingCutOff = XenForo_Application::$time - (XenForo_Application::get('options')->readMarkingDataLifetime * 86400);
		$db->delete('xf_thread_read', 'thread_read_date < ' . $readMarkingCutOff);
		$db->delete('xf_forum_read', 'forum_read_date < ' . $readMarkingCutOff);

		// delete old searches
		$db->delete('xf_search', 'search_date < ' . (XenForo_Application::$time - 86400));

		XenForo_Model::create('XenForo_Model_Log')->pruneAdminLogEntries();
		XenForo_Model::create('XenForo_Model_Log')->pruneModeratorLogEntries();
		XenForo_Model::create('XenForo_Model_EditHistory')->pruneEditHistory();
		XenForo_Model::create('XenForo_Model_Template')->pruneEditHistory();
		XenForo_Model::create('XenForo_Model_Draft')->pruneDrafts();
		XenForo_Model::create('XenForo_Model_Ip')->pruneIps();
	}

	/**
	 * Downgrades expired user upgrades.
	 */
	public static function runUserDowngrade()
	{
		/* @var $upgradeModel XenForo_Model_UserUpgrade */
		$upgradeModel = XenForo_Model::create('XenForo_Model_UserUpgrade');

		$upgradeModel->downgradeUserUpgrades(
			$upgradeModel->getExpiredUserUpgrades()
		);
	}
}