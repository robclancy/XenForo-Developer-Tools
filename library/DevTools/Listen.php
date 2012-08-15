<?php

class DevTools_Listen
{

	protected static $_checked = false;
	
	public static function load_class_model($class, array &$extend)
	{
		if (self::$_checked OR defined('DEVTOOLS_AUTOLOADER_SETUP'))
		{
			return;
		}
		
		$paths 	= XenForo_Application::getInstance()->loadRequestPaths();
		$url 	= $paths['fullBasePath'] . 'templatesync.php';
		
		if (class_exists('XenForo_Dependencies_Admin', false))
		{
			$url .= '?admin=1';
		}
		
		file_get_contents($url);
		
		self::$_checked = true;
	}	

}