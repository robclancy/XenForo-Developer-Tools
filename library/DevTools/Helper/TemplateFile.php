<?php

abstract class DevTools_Helper_TemplateFile
{
	public static function write($filePath, $contents, array $attributes = array(), $flags = 0)
	{
		XenForo_Helper_File::createDirectory(dirname($filePath));
		XenForo_Helper_File::makeWritableByFtpUser(dirname($filePath));
		XenForo_Helper_File::makeWritableByFtpUser(dirname(dirname($filePath)));

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

		XenForo_Helper_File::makeWritableByFtpUser($filePath);

		return true;
	}

	public static function updateAttribute($filePath, $name, $value, $flags = 0)
	{
		XenForo_Helper_File::makeWritableByFtpUser($filePath);
		xattr_set($filePath, $name, $value, $flags);
	}
}