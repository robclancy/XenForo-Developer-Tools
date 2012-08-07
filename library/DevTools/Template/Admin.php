<?php

class DevTools_Template_Admin extends XFCP_DevTools_Template_Admin
{
	protected static $_fileTemplateCache = array();

	protected static $_templates = null;

	protected static $_templateFiles = null;

	public function __construct($templateName, array $params = array())
	{
		if (!isset(self::$_fileTemplateCache[$templateName]))
		{
			$template = $this->_getTemplateByTitle($templateName);
			if ($template && isset(self::$_templateFiles[$template['template_id']]) && self::$_templateFiles[$template['template_id']]['title'] != $templateName)
			{
				$templateName = self::$_templateFiles[$template['template_id']]['title'];
			}

			$templatePath = XenForo_Application::getInstance()->getRootDir() . '/templates/admin/' . $templateName;
			if (XenForo_Helper_File::getFileExtension($templatePath) == 'css')
			{
				$templatePath = str_replace('.css', '.' . $template['template_id'] . '.css', $templatePath);
			}
			else
			{
				$templatePath .= '.' . $template['template_id'] . '.html';
			}

			if (!file_exists($templatePath))
			{
				if ($template)
				{
					DevTools_Helper_TemplateFile::createAdminFile($templatePath, $template);
				}
			}
			else
			{
				$registryModel = XenForo_Model::create('XenForo_Model_DataRegistry');
				$templateModTimes = $registryModel->get('adminTemplateModTimes');
				if (!$templateModTimes || !isset($templateModTimes[$templateName]))
				{
					$templateModTimes[$templateName] = filemtime($templatePath);
				}
				else if ($templateModTimes[$templateName] != filemtime($templatePath))
				{
					$contents = file_get_contents($templatePath);
					$propertyModel = XenForo_Model::create('XenForo_Model_StyleProperty');
					$properties = $propertyModel->keyPropertiesByName(
						$propertyModel->getEffectiveStylePropertiesInStyle(-1)
					);
					$propertyChanges = $propertyModel->translateEditorPropertiesToArray(
						$contents, $contents, $properties
					);

					$writer = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');
					$writer->setExistingData($template['template_id']);
					$writer->set('template', $contents);
					$writer->set('title', $templateName);
					$writer->save();

					$propertyModel->saveStylePropertiesInStyleFromTemplate(-1, $propertyChanges, $properties);

					$templateModTimes[$templateName] = filemtime($templatePath);
				}

				$registryModel->set('adminTemplateModTimes', $templateModTimes);
			}

			self::$_fileTemplateCache[$templateName] = true;
		}

		parent::__construct($templateName, $params);
	}

	public static function setTemplateFiles($templateFiles)
	{
		self::$_templateFiles = $templateFiles;
	}

	public static function getTemplateFiles()
	{
		return self::$_templateFiles;
	}

	protected function _getTemplateByTitle($title)
	{
		$templates = $this->_getTemplates();
		return isset($templates[$title]) ? $templates[$title] : false;
	}

	protected function _getTemplates()
	{
		if (self::$_templates === null)
		{
			self::$_templates = XenForo_Model::create('XenForo_Model_AdminTemplate')->getAllAdminTemplates();
		}

		return self::$_templates;
	}
}