<?php

class DevTools_Helper_Xattr
{
	
	static $_setup = false;
	
	static $fileAttributes 	= null;
	
	public function __construct()
	{
		if (self::$_setup == true)
		{
			return;
		}
		
		self::loadFileAttributes();
		
		if ( ! function_exists('xattr_set'))
		{
			$this->defineXattrSet();
			register_shutdown_function(array('DevTools_Helper_Xattr', 'writeFileAttributes'));
		}
		
		if ( ! function_exists('xattr_get'))
		{
			$this->defineXattrGet();
		}
		
		self::$_setup = false;
	}
	
	public static function loadFileAttributes()
	{
		if (self::$fileAttributes != null)
		{
			return;
		}
		
		$filename = self::getAttributeFile();
		
		if ( ! file_exists($filename))
		{
			self::$fileAttributes = (object) array();
		}
		else
		{
			self::$fileAttributes = json_decode(file_get_contents($filename));
		}
	}
	
	public static function writeFileAttributes()
	{
		$filename = self::getAttributeFile();
		$attributes = json_encode(self::$fileAttributes);
		file_put_contents($filename, $attributes);
	}
	
	public static function getAttributeFile()
	{
		$ds 		= DIRECTORY_SEPARATOR;
		$up 		= $ds . '..';
		$rootDir 	= dirname(__FILE__) . $up . $up . $up;
		$filename 	= $rootDir . $ds . 'templates' . $ds . '.fileAttributes';
		
		return $filename;
	}
	
	public function defineXattrSet()
	{
		
		function xattr_set($filename, $name, $value)
		{
			$filename = realpath($filename);
			
			if ( ! isset(DevTools_Helper_Xattr::$fileAttributes->{$filename}))
			{
				DevTools_Helper_Xattr::$fileAttributes->{$filename} = (object) array();
			}
			
			DevTools_Helper_Xattr::$fileAttributes->{$filename}->{$name} = $value;
		}
		
	}
	
	public function defineXattrGet()
	{
		
		function xattr_get($filename, $name)
		{
			$filename = realpath($filename);
			
			if ( ! isset(DevTools_Helper_Xattr::$fileAttributes->{$filename}, DevTools_Helper_Xattr::$fileAttributes->{$filename}->{$name}))
			{
				return false;
			}
			
			return DevTools_Helper_Xattr::$fileAttributes->{$filename}->{$name};
		}
		
	}
	
}