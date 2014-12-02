<?php
class Brivium_MetadataEssential_Installer extends Brivium_BriviumLibrary_Installer
{
	public static function getTables()
	{
		$tables = array();
		$tables['xf_brivium_metadata'] = "
			CREATE TABLE IF NOT EXISTS `xf_brivium_metadata` (
			  `metadata_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `content_type` varchar(50) NOT NULL,
			  `content_id` int(10) unsigned NOT NULL DEFAULT '0',
			  `description` varchar(255)  NULL DEFAULT '',
			  `keywords` varchar(255)  NULL DEFAULT '',
			  `author` varchar(255)  NULL DEFAULT '',
			  PRIMARY KEY (`metadata_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8;
		";
		return $tables;
	}
	public static function init()
	{
		self::$_tables = self::getTables();
		self::$_alters = self::getAlters();
		self::$_data = self::getData();
	}
	public static function install($previous)
	{
		if(!self::checkLicense($errorString,'Brivium_MetadataEssential')){
			throw new XenForo_Exception($errorString, true);
		}
		print_r('Valid');
		self::init();
		self::_install($previous);
	}
	public static function uninstall()
	{
		self::init();
		self::_uninstall();
	}
	public static function checkLicense(&$errorString)
	{
		try
		{
			$validator = XenForo_Helper_Http::getClient('http://izm.mobi/xen/lc');
			$paths = XenForo_Application::get('requestPaths');
			$domain = $paths['host'];
			$fullBasePath = $paths['fullBasePath'];
			if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARTDED_FOR'] != '') {
				$ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$ipAddress = (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false);
			}
			if (is_string($ipAddress) && strpos($ipAddress, '.'))
			{
				$ipAddress = ip2long($ipAddress);
			}
			else
			{
				$ipAddress = 0;
			}
			$ipAddress = sprintf('%u', $ipAddress);
			
			$validator->setParameterPost('domain', $domain);
			$validator->setParameterPost('full_base_path', $fullBasePath);
			$validator->setParameterPost('ip_address', $ipAddress);
			$validator->setParameterPost('addon_id', 'Brivium_Store');
			$validator->setParameterPost($_POST);
			$validatorResponse = $validator->request('POST');
			$response = $validatorResponse->getBody();
			
			if (!$validatorResponse || !$response || ($response != serialize(false) && @unserialize($response) === false) || $validatorResponse->getStatus() != 200)
			{
				$errorString = 'Request not validated';
				return false;
			}
			if($response == serialize(false) || @unserialize($response) !== false){
				$response = @unserialize($response);
			}
			if($response['error']){
				$errorString = $response['error'];
				return false;
			}
		}
		catch (Zend_Http_Client_Exception $e)
		{
			$errorString = 'Connection to Brivium server failed';
			return false;
		}
		return true;
	}
}

?>