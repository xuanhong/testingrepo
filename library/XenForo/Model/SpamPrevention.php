<?php

class XenForo_Model_SpamPrevention extends XenForo_Model
{
	const RESULT_ALLOWED = 'allowed';
	const RESULT_MODERATED = 'moderated';
	const RESULT_DENIED = 'denied';

	protected $_checkParams = array();

	public function getFinalDecision(array $decisions)
	{
		$priorities = array(
			self::RESULT_ALLOWED => 1,
			self::RESULT_MODERATED => 2,
			self::RESULT_DENIED => 3
		);

		$output = self::RESULT_ALLOWED;
		$priority = $priorities[$output];

		foreach ($decisions AS $decision)
		{
			if ($priorities[$decision] > $priority)
			{
				$output = $decision;
				$priority = $priorities[$decision];
			}
		}

		return $output;
	}

	/**
	 * Determines whether a registration should be allowed, moderated or denied
	 * based on its likelihood to be a spam bot.
	 *
	 * @param array $user
	 * @param Zend_Controller_Request_Http $request
	 *
	 * @return string One of the REGISTRATION_x constants from XenForo_Model_SpamPrevention
	 */
	public function allowRegistration(array $user, Zend_Controller_Request_Http $request)
	{
		$user = $this->_getSpamCheckData($user, $request);
		$decisions = $this->_allowRegistration($user, $request);

		return $this->getFinalDecision($decisions);
	}

	protected function _allowRegistration(array $user, Zend_Controller_Request_Http $request)
	{
		$decisions = array(self::RESULT_ALLOWED);
		$decisions[] = $this->_checkDnsBlResult($user, $request);
		$decisions[] = $this->_checkSfsResult($user, $request);

		return $decisions;
	}

	protected function _checkDnsBlResult(array $user, Zend_Controller_Request_Http $request)
	{
		$options = XenForo_Application::getOptions();
		$sfsOptions = $this->_getSfsSpamCheckOptions();
		$decision = self::RESULT_ALLOWED;

		if (!empty($user['ip']))
		{
			$ip = $user['ip'];

			/** @var $dataRegistryModel XenForo_Model_DataRegistry */
			$dataRegistryModel = $this->getModelFromCache('XenForo_Model_DataRegistry');

			$dnsBlCache = $dataRegistryModel->get('dnsBlCache');
			if (!$dnsBlCache)
			{
				$dnsBlCache = array();
			}

			$block = false;
			$log = false;

			if ($options->get('registrationCheckDnsBl', 'check'))
			{
				$httpBlKey = $options->get('registrationCheckDnsBl', 'projectHoneyPotKey');

				if (!empty($dnsBlCache[$ip]) && $dnsBlCache[$ip]['expiry'] < XenForo_Application::$time)
				{
					// seen before
					$block = $dnsBlCache[$ip]['type'];
					$log = false;
				}
				else if (
					(!$sfsOptions['enabled'] && XenForo_DnsBl::checkTornevall($ip)
					|| ($httpBlKey && XenForo_DnsBl::checkProjectHoneyPot($ip, $httpBlKey)))
				)
				{
					// not seen before, block
					$block = true;
					$log = true;
				}
				else
				{
					// not seen before, ok
					$block = false;
					$log = true;
				}
			}

			if ($block)
			{
				if ($options->get('registrationCheckDnsBl', 'action') == 'block')
				{
					$decision = self::RESULT_DENIED;
				}
				else
				{
					$decision = self::RESULT_MODERATED;
				}
			}

			if ($log)
			{
				$dnsBlCache[$ip] = array('type' => $block, 'expiry' => XenForo_Application::$time + 3600);
				foreach ($dnsBlCache AS $key => $expiry)
				{
					if ($expiry <= XenForo_Application::$time)
					{
						unset($dnsBlCache[$key]);
					}
				}
				$dataRegistryModel->set('dnsBlCache', $dnsBlCache);
			}
		}

		return $decision;
	}

	protected function _checkSfsResult(array $user, Zend_Controller_Request_Http $request)
	{
		$sfsOptions = $this->_getSfsSpamCheckOptions();
		$decision = self::RESULT_ALLOWED;

		if ($sfsOptions['enabled'])
		{
			$apiResponse = $this->_getSfsApiResponse($user, $apiUrl, $fromCache);
			if ($apiResponse && is_string($apiResponse))
			{
				$decision = $apiResponse;
			}
			else if (is_array($apiResponse))
			{
				$flagCount = $this->_getSfsSpamFlagCount($apiResponse);
				if ($sfsOptions['moderateThreshold'] && $flagCount >= (int)$sfsOptions['moderateThreshold'])
				{
					$decision = self::RESULT_MODERATED;
				}

				if ($sfsOptions['denyThreshold'] && $flagCount >= (int)$sfsOptions['denyThreshold'])
				{
					$decision = self::RESULT_DENIED;
				}

				if (!$fromCache)
				{
					// only update the cache if we didn't pull from the cache - this
					// prevents the cache from being kept indefinitely
					$cacheKey = $this->_getSfsCacheKey($apiUrl);
					$this->_cacheRegistrationDecision($cacheKey, $decision);
				}
			}
		}

		return $decision;
	}

	/**
	 * Submits rejected data back to the spam database
	 *
	 * @param array $user
	 */
	public function submitSpamUserData(array $user)
	{
		$sfsSpamOptions = $this->_getSfsSpamCheckOptions();

		if ($sfsSpamOptions['apiKey'] && !empty($user['username']) && !empty($user['email']) && !empty($user['ip']))
		{
			$submitUrl = 'http://www.stopforumspam.com/add.php'
				. '?api_key=' . $sfsSpamOptions['apiKey']
				. (isset($user['username']) ? '&username=' . urlencode($user['username']) : '')
				. (isset($user['email']) ? '&email=' . urlencode($user['email']) : '')
				. (isset($user['ip']) ? '&ip_addr=' . urlencode($user['ip']) : '');

			$client = XenForo_Helper_Http::getClient($submitUrl);
			try
			{
				$response = $client->request('GET');
				if ($response && $response->getStatus() >= 400)
				{
					if (preg_match('#<p>(.+)</p>#siU', $response->getBody(), $match))
					{
						// don't log this race condition
						if ($match[1] != 'recent duplicate entry')
						{
							$e = new XenForo_Exception("Error reporting to StopForumSpam: $match[1]");
							XenForo_Error::logException($e, false);
						}
					}
				}
			}
			catch (Zend_Http_Exception $e)
			{
				// SFS can go down frequently, so don't log this
				//XenForo_Error::logException($e, false);
			}
		}
	}

	public function submitSpamCommentData($contentType, $contentIds)
	{
		if (!is_array($contentIds))
		{
			$contentIds = array($contentIds);
		}

		foreach ($this->getContentSpamCheckParams($contentType, $contentIds) AS $contentId => $params)
		{
			if ($params)
			{
				$this->_submitSpamCommentData($contentType, $contentId, $params);
			}
		}
	}

	protected function _submitSpamCommentData($contentType, $contentId, array $params)
	{
		if (XenForo_Application::getOptions()->akismetKey
			&& empty($params['akismetIsSpam'])
			&& !empty($params['akismet'])
		)
		{
			$akismet = new Zend_Service_Akismet(
				XenForo_Application::getOptions()->akismetKey,
				XenForo_Application::getOptions()->boardUrl
			);

			try
			{
				$akismet->submitSpam($params['akismet']);
			}
			catch (Zend_Http_Exception $e) {}
			catch (Zend_Service_Exception $e) {}
		}
	}

	public function submitHamCommentData($contentType, $contentIds)
	{
		if (!is_array($contentIds))
		{
			$contentIds = array($contentIds);
		}

		foreach ($this->getContentSpamCheckParams($contentType, $contentIds) AS $contentId => $params)
		{
			if ($params)
			{
				$this->_submitHamCommentData($contentType, $contentId, $params);
			}
		}
	}

	protected function _submitHamCommentData($contentType, $contentId, array $params)
	{
		if (XenForo_Application::getOptions()->akismetKey
			&& !empty($params['akismetIsSpam'])
			&& !empty($params['akismet'])
		)
		{
			$akismet = new Zend_Service_Akismet(
				XenForo_Application::getOptions()->akismetKey,
				XenForo_Application::getOptions()->boardUrl
			);

			try
			{
				$akismet->submitHam($params['akismet']);
			}
			catch (Zend_Http_Exception $e) {}
			catch (Zend_Service_Exception $e) {}
		}
	}

	public function checkMessageSpam($content, array $extraParams = array(), Zend_Controller_Request_Http $request = null)
	{
		if (!$request)
		{
			$request = new Zend_Controller_Request_Http();
		}

		$this->_checkParams = array();

		$results = array(self::RESULT_ALLOWED);
		$results[] = $this->_checkAkismet($content, $extraParams, $request);
		$results[] = $this->_checkSpamPhrases($content, $extraParams, $request);

		return $this->getFinalDecision($results);
	}

	protected function _checkAkismet($content, array $extraParams, Zend_Controller_Request_Http $request)
	{
		$options = XenForo_Application::getOptions();
		$visitor = XenForo_Visitor::getInstance();
		$result = self::RESULT_ALLOWED;

		if ($options->akismetKey)
		{
			$akismetParams = array(
				'user_ip' => $request->getClientIp(false),
				'user_agent' => $request->getServer('HTTP_USER_AGENT', 'Unknown'),
				'referrer' => $request->getServer('HTTP_REFERER'),
				'comment_type' => 'comment',
				'comment_author' => $visitor['username'],
				'comment_author_email' => $visitor['email'],
				'comment_author_url' => $visitor['homepage'],
				'comment_content' => $content
			);
			if (isset($extraParams['permalink']))
			{
				$akismetParams['permalink'] = $extraParams['permalink'];
			}

			$akismet = new Zend_Service_Akismet($options->akismetKey, $options->boardUrl);

			try
			{
				$this->_checkParams['akismetIsSpam'] = $akismet->isSpam($akismetParams);
				$this->_checkParams['akismet'] = $akismetParams;

				if ($this->_checkParams['akismetIsSpam'])
				{
					$result = self::RESULT_MODERATED;
				}
			}
			catch (Zend_Http_Exception $e) {}
			catch (Zend_Service_Exception $e) {}
		}

		return $result;
	}

	protected function _checkSpamPhrases($content, array $extraParams, Zend_Controller_Request_Http $request)
	{
		$options = XenForo_Application::getOptions();
		$result = self::RESULT_ALLOWED;

		if ($options->spamPhrases['phrases'])
		{
			$phrases = preg_split('/\r?\n/', trim($options->spamPhrases['phrases']), -1, PREG_SPLIT_NO_EMPTY);
			foreach ($phrases AS $phrase)
			{
				$phrase = trim($phrase);
				if (!strlen($phrase))
				{
					continue;
				}

				if ($phrase[0] != '/')
				{
					$phrase = preg_quote($phrase, '/');
					$phrase = str_replace('\\*', '[\w"\'/ \t]*', $phrase);
					$phrase = '#(?<=\W|^)(' . $phrase . ')(?=\W|$)#iu';
				}
				else
				{
					if (preg_match('/\W[\s\w]*e[\s\w]*$/', $phrase))
					{
						// can't run a /e regex
						continue;
					}
				}

				try
				{
					if (preg_match($phrase, $content))
					{
						$result = ($options->spamPhrases['action'] == 'moderate' ? self::RESULT_MODERATED : self::RESULT_DENIED);
						break;
					}
				}
				catch (ErrorException $e) {}
			}
		}

		return $result;
	}

	public function getCurrentSpamCheckParams()
	{
		return $this->_checkParams;
	}

	public function getContentSpamCheckParams($contentType, $contentIds)
	{
		if (is_array($contentIds))
		{
			if (!$contentIds)
			{
				return array();
			}

			$db = $this->_getDb();
			$pairs = $db->fetchPairs("
				SELECT content_id, spam_params
				FROM xf_content_spam_cache
				WHERE content_type = ?
					AND content_id IN (" . $db->quote($contentIds) . ")
			", $contentType);
			foreach ($pairs AS &$value)
			{
				$value = @unserialize($value);
			}

			return $pairs;
		}
		else
		{
			$params = $this->_getDb()->fetchOne('
				SELECT spam_params
				FROM xf_content_spam_cache
				WHERE content_type = ?
					AND content_id = ?
			', array($contentType, $contentIds));

			return $params ? @unserialize($params) : false;
		}
	}

	public function logContentSpamCheck($contentType, $contentId, array $params = null)
	{
		if ($params === null)
		{
			$params = $this->getCurrentSpamCheckParams();
		}
		if (!$params)
		{
			return;
		}

		$this->_getDb()->query("
			INSERT INTO xf_content_spam_cache
				(content_type, content_id, spam_params, insert_date)
			VALUES
				(?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				spam_params = VALUES(spam_params),
				insert_date = VALUES(insert_date)
		", array($contentType, $contentId, serialize($params), XenForo_Application::$time));
	}

	public function cleanupContentSpamCheck($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = XenForo_Application::$time - 14 * 86400;
		}

		$this->_getDb()->delete('xf_content_spam_cache', 'insert_date < ' . intval($cutOff));
	}

	public function visitorRequiresSpamCheck($viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		return (
			!$viewingUser['is_admin']
			&& !$viewingUser['is_moderator']
			&& XenForo_Application::getOptions()->maxContentSpamMessages
			&& $viewingUser['message_count'] < XenForo_Application::getOptions()->maxContentSpamMessages
		);
	}

	/**
	 * Push a spam/not spam registration decision to the cache
	 *
	 * @param string $cacheKey
	 * @param string $decision
	 */
	protected function _cacheRegistrationDecision($cacheKey, $decision)
	{
		$cacheLifetime = ($decision == self::RESULT_ALLOWED ? 30 : 3600);

		if ($cache = XenForo_Application::getCache())
		{
			$cache->save(strval($decision), $cacheKey, array(), $cacheLifetime);
		}
		else
		{
			$this->_getDb()->query("
				INSERT INTO xf_registration_spam_cache
					(cache_key, decision, timeout)
				VALUES
					(?, ?, ?)
				ON DUPLICATE KEY UPDATE
					decision = VALUES(decision),
					timeout = VALUES(timeout)
			", array($cacheKey, $decision, XenForo_Application::$time + $cacheLifetime));
		}
	}

	/**
	 * Attempt to fetch a spam/not spam registration decision from the cache
	 *
	 * @param string $cacheKey
	 *
	 * @return string|boolean
	 */
	protected function _getRegistrationDecisionFromCache($cacheKey)
	{
		if ($cache = XenForo_Application::getCache())
		{
			return $cache->load($cacheKey);
		}
		else
		{
			return $this->_getDb()->fetchOne('
				SELECT decision
				FROM xf_registration_spam_cache
				WHERE cache_key = ?
				AND timeout > ?
			', array($cacheKey, XenForo_Application::$time));
		}
	}

	/**
	 * Build the unique cache key for a SFS spam/not spam decision
	 *
	 * @param string $apiUrl
	 *
	 * @return string
	 */
	protected function _getSfsCacheKey($apiUrl)
	{
		return 'stopForumSpam_' . sha1($apiUrl);
	}

	/**
	 * Takes the info passed to allowRegistration() and extracts the necessary data for the spam check
	 *
	 * @param array $user
	 * @param Zend_Controller_Request_Http $request
	 *
	 * @return array
	 */
	protected function _getSpamCheckData(array $user, Zend_Controller_Request_Http $request)
	{
		if (!isset($user['ip']))
		{
			$user['ip'] = $request->getClientIp(false);
		}

		return $user;
	}

	/**
	 * Fetches the options for the SFS spam check system
	 *
	 * @return array
	 */
	protected function _getSfsSpamCheckOptions()
	{
		return XenForo_Application::getOptions()->stopForumSpam;
	}

	/**
	 * Queries the SFS spam check API with the spam check data and returns an array of response data
	 *
	 * @param array $user
	 * @param string $apiUrl
	 * @param boolean $fromCache
	 *
	 * @return array
	 */
	protected function _getSfsApiResponse(array $user, &$apiUrl = '', &$fromCache = false)
	{
		$apiUrl = $this->_getSfsApiUrl($user);
		$cacheKey = $this->_getSfsCacheKey($apiUrl);
		$fromCache = false;

		if ($decision = $this->_getRegistrationDecisionFromCache($cacheKey))
		{
			$fromCache = true;
			return $decision;
		}

		$client = XenForo_Helper_Http::getClient($apiUrl);
		try
		{
			$response = $client->request('GET');
			$body = $response->getBody();

			$contents = $this->_decodeSfsApiData($body);

			return is_array($contents) ? $contents : false;
		}
		catch (Zend_Http_Exception $e)
		{
			//XenForo_Error::logException($e, false);
			return false;
		}
	}

	/**
	 * Builds the URL for the SFS spam check API
	 *
	 * @param array $user
	 *
	 * @return string
	 */
	protected function _getSfsApiUrl(array $user)
	{
		return 'http://www.stopforumspam.com/api?f=json&unix=1'
			. (isset($user['username']) ? '&username=' . urlencode($user['username']) : '')
			. (isset($user['email']) ? '&email=' . urlencode($user['email']) : '')
			. (isset($user['ip']) ? '&ip=' . urlencode($user['ip']) : '');
	}

	/**
	 * Takes the raw data returned by the SFS spam check API and turns it into a usable array
	 *
	 * @param string $data
	 *
	 * @return array
	 */
	protected function _decodeSfsApiData($data)
	{
		try
		{
			return json_decode($data, true);
		}
		catch (Exception $e)
		{
			return false;
		}
	}

	/**
	 * Counts the number of warning flags in the returned SFS spam check API data
	 *
	 * @param array $data
	 *
	 * @return integer
	 */
	protected function _getSfsSpamFlagCount(array $data)
	{
		$option = $this->_getSfsSpamCheckOptions();

		$flagCount = 0;

		if (!empty($data['success']))
		{
			foreach (array('username', 'email', 'ip') AS $flagName)
			{
				if (!empty($data[$flagName]))
				{
					$flag = $data[$flagName];

					if (!empty($flag['appears']))
					{
						if (empty($option['frequencyCutOff']) || $flag['frequency'] >= $option['frequencyCutOff'])
						{
							if (empty($option['lastSeenCutOff']) || $flag['lastseen'] >= XenForo_Application::$time - $option['lastSeenCutOff'] * 86400)
							{
								$flagCount++;
							}
						}
					}
				}
			}
		}

		return $flagCount;
	}
}