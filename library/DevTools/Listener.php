<?php

class DevTools_Listener
{
	protected static $_checked = false;

	public static function loadClass($class, array &$extend)
	{
		$extendedClasses = array(
			'XenForo_DataWriter_Template',
			'XenForo_DataWriter_AdminTemplate'
		);

		foreach ($extendedClasses AS $extendedClass)
		{
			if ($class == $extendedClass)
			{
				// Do substr incase there is some class added at some point with XenForo in it twice
				$extend[] = 'DevTools' . substr($class, 7);
			}
		}

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