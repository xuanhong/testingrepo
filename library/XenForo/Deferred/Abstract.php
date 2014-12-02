<?php

abstract class XenForo_Deferred_Abstract
{
	abstract public function execute(array $deferred, array $data, $targetRunTime, &$status);

	protected function __construct()
	{
		@set_time_limit(120);
		ignore_user_abort(true);
		XenForo_Application::getDb()->setProfiler(false);
	}

	public function canCancel()
	{
		return false;
	}

	public static function create($class)
	{
		if (strpos($class, '_') === false)
		{
			$class = 'XenForo_Deferred_' . $class;
		}

		$class = XenForo_Application::resolveDynamicClass($class);
		$object = new $class();
		if (!($object instanceof XenForo_Deferred_Abstract))
		{
			return false;
		}
		return $object;
	}
}