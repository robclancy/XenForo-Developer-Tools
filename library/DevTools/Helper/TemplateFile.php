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

	public static function templatePostSave(XenForo_DataWriter $writer, $styleId)
	{
		$templateFileModel = XenForo_Model::create('DevTools_Model_TemplateFile');
		// TODO: other styles
		$styles = array(
			-1 => 'admin',
			0 => 'master'
		);

		if (!isset($styles[$styleId]))
		{
			return;
		}

		$oldPath = false;
		if ($writer->isUpdate())
		{
			$oldPath = XenForo_Application::getInstance()->getRootDir() . '/templates/' . $styles[$styleId] . '/';
			if ($writer->getExisting('addon_id'))
			{
				$oldPath .= $writer->getExisting('addon_id') . '/';
			}
			$oldPath .= $templateFileModel->addFileExtension($writer->getExisting('title'));
		}

		$newPath = false;
		if ($writer->isChanged('addon_id') || $writer->isChanged('title'))
		{
			$newPath = XenForo_Application::getInstance()->getRootDir() . '/templates/' . $styles[$styleId] . '/';
			if ($writer->get('addon_id'))
			{
				$newPath .= $writer->get('addon_id') . '/';
			}

			$newPath .= $templateFileModel->addFileExtension($writer->get('title'));
		}

		if (!$oldPath)
		{
			$oldPath = $newPath;
		}

		if (!self::write($oldPath, $writer->get('template'), array('id' => $writer->get('template_id'))))
		{
			throw new XenForo_Exception("Failed to write template file to $oldPath");
		}

		if ($newPath && $oldPath)
		{
			rename($oldPath, $newPath);
		}
	}

	public static function templatePostDelete(XenForo_DataWriter $writer, $styleId)
	{
		$templateFileModel = XenForo_Model::create('DevTools_Model_TemplateFile');
		// TODO: other styles
		$styles = array(
			-1 => 'admin',
			0 => 'master'
		);

		if (!isset($styles[$styleId]))
		{
			return;
		}

		// We just move the file to .tash/__filename.html/css as a backup
		$path = XenForo_Application::getInstance()->getRootDir() . '/templates/' . $styles[$styleId] . '/';
		if ($writer->get('addon_id'))
		{
			$path .= $writer->get('addon_id') . '/';
		}
		$newPath = $path;
		$path .= $templateFileModel->addFileExtension($writer->get('title'));
		$newPath .= '.trash/__' . $templateFileModel->addFileExtension($writer->get('title'));
		if (file_exists($path))
		{
			XenForo_Helper_File::createDirectory(dirname($newPath));
			XenForo_Helper_File::makeWritableByFtpUser(dirname($newPath));
			rename($path, $newPath);
		}
	}
}