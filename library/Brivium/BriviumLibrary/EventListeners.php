<?php

/**
 * Helper Brivium Addon for copyright.
 *
 * @package Brivium_BriviumLibrary
 */
class Brivium_BriviumLibrary_EventListeners
{
	protected static $_copyrightNotice = '<div id="BRCopyright" class="concealed footerLegal" style="clear:both"><div class="pageContent muted"><a href="http://brivium.com/" class="concealed" title="Brivium Limited">XenForo Add-ons by Brivium &trade;  &copy; 2012-2013 Brivium LLC.</span></a></div></div>';
	protected static $_setCopyright = null;
	
	protected static function _setCopyrightNotice($copyrightNotice = ''){
		if($copyrightNotice){
			self::$_copyrightNotice = (string) $copyrightNotice;
		}
	}
	protected static function _setCopyrightAddonList($copyrightNotice = ''){
		if($copyrightNotice){
			self::$_copyrightNotice = (string) $copyrightNotice;
		}
	}
	protected static function _templateHook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
    {
		switch ($hookName) {
			case 'page_container_breadcrumb_bottom':
				if(self::$_copyrightNotice && self::$_setCopyright===null){
					$contents = $contents.self::$_copyrightNotice;
					self::$_setCopyright = true;
				}
				break;
		}
    }
	
}