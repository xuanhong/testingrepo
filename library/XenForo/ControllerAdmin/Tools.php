<?php

class XenForo_ControllerAdmin_Tools extends XenForo_ControllerAdmin_Abstract
{
	public function actionIndex()
	{
		$visitor = XenForo_Visitor::getInstance();

		$viewParams = array(
			'canManageCron' => $visitor->hasAdminPermission('cron'),
			'canImport' => $visitor->hasAdminPermission('import'),
			'canCaptcha' => $visitor->hasAdminPermission('option'),
			'canViewLogs' => $visitor->hasAdminPermission('viewLogs'),
			'canRebuildCache' => $visitor->hasAdminPermission('rebuildCache'),
		);

		return $this->responseView('XenForo_ViewAdmin_Tools_Splash', 'tools_splash', $viewParams);
	}

	public function actionRebuild()
	{
		$this->assertAdminPermission('rebuildCache');

		/* @var $searchModel XenForo_Model_Search */
		$searchModel = XenForo_Model::create('XenForo_Model_Search');

		$searchContentTypeOptions = array();
		foreach ($searchModel->getSearchDataHandlers() AS $contentType => $handler)
		{
			$searchContentTypeOptions[$contentType] = $handler->getSearchContentTypePhrase();
		}

		$viewParams = array(
			'searchContentTypes' => $searchContentTypeOptions,
			'success' => $this->_input->filterSingle('success', XenForo_Input::BOOLEAN)
		);
		$containerParams = array(
			'hasManualDeferred' => $this->getModelFromCache('XenForo_Model_Deferred')->countRunnableDeferreds(true)
		);

		return $this->responseView('XenForo_ViewAdmin_Tools_Rebuild', 'tools_rebuild', $viewParams, $containerParams);
	}

	/**
	 * General purpose cache rebuilder system.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionCacheRebuild()
	{
		$input = $this->_input->filter(array(
			'caches' => XenForo_Input::JSON_ARRAY,
			'position' => XenForo_Input::UINT,

			'cache' => XenForo_Input::STRING,
			'options' => XenForo_Input::ARRAY_SIMPLE,

			'process' => XenForo_Input::UINT
		));

		if ($input['cache'])
		{
			$input['caches'][] = array($input['cache'], $input['options']);
		}

		$doRebuild = ($this->_request->isPost() && $input['process']);

		if ($doRebuild)
		{
			$redirect = $this->getDynamicRedirect(false, false);
		}
		else
		{
			$redirect = $this->getDynamicRedirect(false);
		}

		$caches = $input['caches'];
		$position = $input['position'];

		$output = $this->getHelper('CacheRebuild')->rebuildCache(
			$input, $redirect, XenForo_Link::buildAdminLink('tools/cache-rebuild'), $doRebuild
		);

		if ($output instanceof XenForo_ControllerResponse_Abstract)
		{
			return $output;
		}
		else
		{
			$viewParams = $output;

			$containerParams = array(
				'containerTemplate' => 'PAGE_CONTAINER_SIMPLE'
			);

			return $this->responseView('XenForo_ViewAdmin_Tools_CacheRebuild', 'tools_cache_rebuild', $viewParams, $containerParams);
		}
	}

	public function actionTriggerDeferred()
	{
		$this->_assertPostOnly();
		$this->assertAdminPermission('rebuildCache');

		$input = $this->_input->filter(array(
			'cache' => XenForo_Input::STRING,
			'options' => XenForo_Input::ARRAY_SIMPLE,
		));

		if ($input['cache'])
		{
			$obj = XenForo_Deferred_Abstract::create($input['cache']);
			if ($obj)
			{
				XenForo_Application::defer($input['cache'], $input['options'], 'Rebuild' . $input['cache'], true);
			}
		}

		$this->_request->setParam('redirect',
			XenForo_Link::buildAdminLink('tools/rebuild', false, array('success' => 1))
		);

		return $this->responseReroute(__CLASS__, 'runDeferred');
	}

	public function actionRunDeferred()
	{
		$redirect = $this->getDynamicRedirectIfNot(XenForo_Link::buildAdminLink('tools/run-deferred'));

		$input = $this->_input->filter(array(
			'execute' => XenForo_Input::UINT,
		));

		/* @var $deferModel XenForo_Model_Deferred */
		$deferModel = $this->getModelFromCache('XenForo_Model_Deferred');
		$status = '';
		$canCancel = false;

		if (XenForo_Helper_Cookie::getCookie('cancel_defer'))
		{
			$deferModel->cancelFirstRunnableDeferred();
			XenForo_Helper_Cookie::deleteCookie('cancel_defer');
		}

		if ($input['execute'] && $this->_request->isPost())
		{
			$continued = $deferModel->run(true, null, $status, $canCancel);
			if (!$continued)
			{
				return $this->responseRedirect(XenForo_ControllerResponse_Redirect::SUCCESS, $redirect);
			}
		}

		$viewParams = array(
			'status' => $status,
			'canCancel' => $canCancel,
			'redirect' => $redirect
		);

		$containerParams = array(
			'containerTemplate' => 'PAGE_CONTAINER_SIMPLE',
			'allowManualDeferredRun' => false
		);

		return $this->responseView('XenForo_ViewAdmin_Tools_RunDeferred', 'tools_run_deferred', $viewParams, $containerParams);
	}

	public function actionCleanUpPermissions()
	{
		$this->_assertPostOnly();
		$this->assertAdminPermission('rebuildCache');

		/** @var $permissionModel XenForo_Model_Permission */
		$permissionModel = $this->getModelFromCache('XenForo_Model_Permission');

		$permissionModel->deleteUnusedPermissionCombinations();

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			XenForo_Link::buildAdminLink('tools/rebuild', false, array('success' => 1))
		);
	}

	public function actionTestFacebook()
	{
		if (!XenForo_Application::get('options')->facebookAppId)
		{
			$group = $this->getModelFromCache('XenForo_Model_Option')->getOptionGroupById('facebook');
			$url = XenForo_Link::buildAdminLink('options/list', $group);
			return $this->responseError(new XenForo_Phrase('to_test_facebook_integration_must_enter_application_info', array('url' => $url)));
		}

		$fbRedirectUri = XenForo_Link::buildAdminLink('canonical:tools/test-facebook', false, array('x' => '?/&=', 'y' => 2));

		if ($this->_input->filterSingle('test', XenForo_Input::UINT))
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				XenForo_Helper_Facebook::getFacebookRequestUrl($fbRedirectUri)
			);
		}

		$info = false;
		$userToken = false;

		$code = $this->_input->filterSingle('code', XenForo_Input::STRING);
		if ($code)
		{
			$token = XenForo_Helper_Facebook::getAccessTokenFromCode($code, $fbRedirectUri);
			$fbError = XenForo_Helper_Facebook::getFacebookRequestErrorInfo($token, 'access_token');
			if ($fbError)
			{
				return $this->responseError($fbError);
			}

			$userToken = $token['access_token'];

			$info = XenForo_Helper_Facebook::getUserInfo($userToken);
			$fbError = XenForo_Helper_Facebook::getFacebookRequestErrorInfo($info, 'id');
			if ($fbError)
			{
				return $this->responseError($fbError);
			}
		}

		$viewParams = array(
			'fbInfo' => $info,
			'userToken' => $userToken
		);

		return $this->responseView('XenForo_ViewAdmin_Tools_TestFacebook', 'tools_test_facebook', $viewParams);
	}

	public function actionFileCheck()
	{
		if ($this->_request->isPost())
		{
			$hashes = XenForo_Install_Data_FileSums::getHashes();

			XenForo_CodeEvent::fire('file_health_check', array($this, &$hashes));

			$errors = XenForo_Helper_Hash::compareHashes($hashes);

			$viewParams = array(
				'errors' => $errors,
				'hashes' => $hashes,
			);
		}
		else
		{
			$viewParams = array('form' => true);
		}

		return $this->responseView('XenForo_ViewAdmin_Tools_FileCheck', 'tools_file_check', $viewParams);
	}

	public function actionTransmogrifier()
	{
		return $this->responseView(
			'XenForo_ViewAdmin_Tools_Transmogrifier',
			'emergency'
		);
	}

	/**
	 * Resets the transmogrifier - caution!
	 */
	public function actionTransmogrifierReset()
	{
		$this->_assertPostOnly();

		if (!$this->_input->filterSingle('transmogrification_confirmation', XenForo_Input::UINT))
		{
			return $this->responseError('It is not possible to reset the transmogrifier at this time.');
		}

		$transmogrificationModel = $this->getModelFromCache('XenForo_Model_Transmogrifier');

		$viewParams = $transmogrificationModel->resetTransmogrifier();

		return $this->responseView(
			'XenForo_ViewAdmin_Tools_TransmogrifierReset',
			'emergency_reset',
			$viewParams
		);
	}

	public function actionIp2Long()
	{
		if ($this->isConfirmedPost())
		{
			$viewParams = array(
				'ip' => ip2long($this->_input->filterSingle('ip', XenForo_Input::STRING))
			);

			return $this->responseView('XenForo_ViewAdmin_Tools_Ip2Long', '', $viewParams);
		}
	}

	public function actionPhpinfo()
	{
		phpinfo();
		die();
	}
}