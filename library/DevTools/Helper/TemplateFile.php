<?php

class DevTools_Helper_TemplateFile
{
	public static function createPublicFile($path, $templateName, $styleId)
	{
		$templateModel = XenForo_Model::create('XenForo_Model_Template');
	}

	public static function createAdminFile($path, array $template)
	{
		if (!$template)
		{
			return false;
		}

		if (file_put_contents($path, $template['template']) === false)
		{
			return false;
		}

		XenForo_Helper_File::makeWritableByFtpUser($path);
		return true;
	}
}