<?php

class XfAddOns_Sitemap_Logger
{
	
	/**
	 * Log a message at a DEBUG level
	 */
	public static function debug($msg)
	{
		$options = XenForo_Application::getOptions();
		if (!$options->xfa_sitemap_log_creation)
		{
			return;
		}
		if (!XenForo_Application::debugMode())
		{
			return;
		}
				
		$logMsg = self::getLogMessage('DEBUG', $msg);
		@file_put_contents('sitemap/sitemap.log', $logMsg, FILE_APPEND);
	}

	/**
	 * Log a message at an INFO level
	 */
	public static function info($msg)
	{
		$logMsg = self::getLogMessage('INFO', $msg);
		@file_put_contents('sitemap/sitemap.log', $logMsg, FILE_APPEND);
	}

	/**
	 * Formats the log message with date and level
	 */
	private static function getLogMessage($level, $msg)
	{
		$logMsg = "";
		$logMsg .= "{$level}: ";
		$logMsg .= date('m/d/Y H:i:s');
		$logMsg .= ": {$msg}\r\n";
		return $logMsg;
	}
	
	
	
	
}