<?php

class DevTools_Installer
{
	public static function install()
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
		
		$this->writeTemplateFiles();
	}
	
	/**
	 * Placed in separate function so it can easily be called from cron jobs
	 * 
	 * @return void    
	 */
	public static function writeTemplateFiles()
	{
		new DevTools_Helper_Xattr;
		XenForo_Model::create('DevTools_Model_TemplateFile')->writeTemplatesToFileSystem();
	}

	public static function uninstall(){}
}