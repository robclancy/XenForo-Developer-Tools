<?php

class DevTools_Installer
{
	public static function install($existingAddon, $addonData)
	{
		$db = XenForo_Application::getDb();

		try
		{
			$db->query('ALTER TABLE xf_admin_template ADD COLUMN last_file_update INT UNSIGNED NOT NULL DEFAULT 0');
		}
		catch (Exception $e) {}

		try
		{
			$db->query('ALTER TABLE xf_template ADD COLUMN last_file_update INT UNSIGNED NOT NULL DEFAULT 0');
		}
		catch (Exception $e) {}

		try
		{
			$db->query('ALTER TABLE xf_phrase ADD COLUMN last_file_update INT UNSIGNED NOT NULL DEFAULT 0');
		}
		catch (Exception $e) {}

		if (!$existingAddon)
		{
			self::writeTemplateFiles();
		}
	}

	/**
	 * Placed in separate function so it can easily be called from cron jobs
	 *
	 * @return void
	 */
	public static function writeTemplateFiles()
	{
		XenForo_Model::create('DevTools_Model_File')->writeAllFiles();
	}

	public static function uninstall(){}
}