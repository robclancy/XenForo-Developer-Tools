<?php

abstract class DevTools_Helper_File
{
	// NOTE: for root directories you have to create them manually on install by themselves
	// as in pass in path/to/install/templates. /path/to/templates/admin will fail to create
	// the templates directory and therefore fail to work at all
	public static function write($filePath, $contents, array $attributes = array(), $flags = 0)
	{
		self::createDirectory(dirname($filePath));
		self::makeWritableByFtpUser(dirname($filePath));

		if (file_exists($filePath))
		{
			if (!isset($attributes['id']) || xattr_get($filePath, 'id') != $attributes['id'])
			{
				return false;
			}
		}

		if (empty($contents))
		{
			if (!touch($filePath))
			{
				return false;
			}
		}
		else
		{
			if (!file_put_contents($filePath, $contents))
			{
				return false;
			}
		}

		foreach ($attributes AS $name => $value)
		{
			xattr_set($filePath, $name, $value, $flags);
		}

		self::makeWritableByFtpUser($filePath);

		return true;
	}

	public static function updateAttribute($filePath, $name, $value, $flags = 0)
	{
		self::makeWritableByFtpUser($filePath);
		xattr_set($filePath, $name, $value, $flags);
	}

	public static function createDirectory($path, $createIndexHtml = false)
	{
		$created = XenForo_Helper_File::createDirectory($path, $createIndexHtml);
		// XenForo won't create a directory in the root, manually do it here
		if (!$created)
		{
			$path = preg_replace('#/+$#', '', $path);
			$path = str_replace('\\', '/', $path);
			$parts = explode('/', $path);
			$checkOnRoot = implode('/', array_slice($parts, 0, count($parts) - 1));
			if (XenForo_Application::getInstance()->getRootDir() == $checkOnRoot)
			{
				if (!file_exists($path))
				{
					if (mkdir($path))
					{
						$created = true;
					}
				}
			}
		}

		return $created;
	}

	public static function makeWritableByFtpUser($file)
	{
		return XenForo_Helper_File::makeWritableByFtpUser($file);
	}
}