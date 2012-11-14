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

		if (empty($contents))
		{
			if ( ! touch($filePath))
			{
				return false;
			}
		}
		else
		{
			if ( ! file_put_contents($filePath, $contents))
			{
				return false;
			}
		}

		self::makeWritableByFtpUser($filePath);

		return true;
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

	public static function getIdAndTitleFromFileName($fileName)
	{
		// Format for filenames is.. title.id.extra.extension
		// So we check for first '.' and if there is a second one get what is inbetween for the id
		// if it is an int that is it, if not it must be a new file

		// Special case for CSS files
		$suffix = strpos($fileName, '.css') !== false ? '.css' : '';

		if (($pos = strpos($fileName, '.')) !== false)
		{
			$id = 0;
			if (($pos2 = strpos($fileName, '.', $pos + 1)) !== false)
			{
				$id = (int) substr($fileName, $pos + 1, $pos2 - $pos);
			}

			return array(substr($fileName, 0, $pos). $suffix, $id);
		}

		return array($fileName . $suffix, 0);
	}
}