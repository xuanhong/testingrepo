<?php

abstract class Brivium_BriviumLibrary_Installer
{
	protected static $_db = null;
	protected static $_tables = null;
	protected static $_alters = null;
	protected static $_data = null;
	
	
	
	protected static function _getDb()
	{
		if (self::$_db === null){self::$_db = XenForo_Application::get('db');}
		return self::$_db;
	}
	public static function addColumn($table, $field, $attr)
	{
		if (!self::checkIfExist($table, $field)) {
			return self::_getDb()->query("ALTER TABLE `" . $table . "` ADD `" . $field . "` " . $attr);
		}
	}
	public static function removeColumn($table, $field)
	{
		if (self::checkIfExist($table, $field)) {
			return self::_getDb()->query("ALTER TABLE `" . $table . "` DROP `" . $field . "`");
		}
	}
	public static function checkIfExist($table, $field)
	{
		if (self::_getDb()->fetchRow('SHOW columns FROM `' . $table . '` WHERE Field = ?', $field)) {
			return true;
		}
		else {
			return false;
		}
	}
	protected static function _install($previous = array())
	{
		$db = self::_getDb();
		if(self::$_tables!==null && is_array(self::$_tables)){
			foreach (self::$_tables AS $tableSql)
			{
				try
				{
					$db->query($tableSql);
				}
				catch (Zend_Db_Exception $e) {}
			}
		}
		if(self::$_alters!==null && is_array(self::$_alters)){
			foreach (self::$_alters AS $tableName => $tableAlters)
			{
				if($tableAlters && is_array($tableAlters)){
					foreach ($tableAlters AS $tableColumn => $attributes)
					{
						try
						{
							self::addColumn($tableName, $tableColumn, $attributes);
						}
						catch (Zend_Db_Exception $e) {}
					}
				}
			}
		}
		if(self::$_data!==null && is_array(self::$_data)){
			foreach (self::$_data AS $dataSql)
			{
				$db->query($dataSql);
			}
		}
	}

	protected static function _uninstall()
	{
		$db = self::_getDb();
		
		if(self::$_tables!==null && is_array(self::$_tables)){
			foreach (self::$_tables AS $tableName => $tableSql)
			{
				try
				{
					$db->query("DROP TABLE IF EXISTS `$tableName`");
				}
				catch (Zend_Db_Exception $e) {}
			}
		}
		if(self::$_alters!==null && is_array(self::$_alters)){
			foreach (self::$_alters AS $tableName => $tableAlters)
			{
				if($tableAlters && is_array($tableAlters)){
					foreach ($tableAlters AS $tableColumn => $attributes)
					{
						try
						{
							self::removeColumn($tableName, $tableColumn);
						}
						catch (Zend_Db_Exception $e) {}
					}
				}
			}
		}
	}
	public static function init()
	{
		self::$_tables = self::getTables();
		self::$_alters = self::getAlters();
		self::$_data = self::getData();
	}
	public static function install($previous)
	{
		self::init();
		self::_install($previous);
	}
	public static function uninstall()
	{
		self::init();
		self::_uninstall();
	}
	public static function getTables()
	{
		return array();
	}
	public static function getAlters()
	{
		return array();
	}	
	public static function getData()
	{
		return array();
	}
}