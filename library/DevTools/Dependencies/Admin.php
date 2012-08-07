<?php

class DevTools_Dependencies_Admin extends XFCP_DevTools_Dependencies_Admin
{
	protected function _handleCustomPreloadedData(array &$data)
	{
		$templateFileModel = XenForo_Model::create('DevTools_Model_TemplateFile');

		$templateFileModel->detectFileChanges(-1);





		//XenForo_Model::create('DevTools_Model_TemplateFile')->writeTemplatesToFileSystem();

		/*$dir = new DirectoryIterator(XenForo_Application::getInstance()->getRootDir() . '/templates/admin');
		$files = array();
		$newFiles = array();
		foreach ($dir AS $file)
		{
			if ($file->isDot() || $file->isDir())
			{
				continue;
			}

			$ext = XenForo_Helper_File::getFileExtension($file->getFilename());
			if ($ext == 'html' || $ext == 'css')
			{
				$id = XenForo_Helper_File::getFileExtension(str_replace('.' . $ext, '', $file->getFilename()));
				if (is_numeric($id))
				{
					list ($title) = explode('.', $file->getFilename());
					$files[$id] = array(
						'filename' => $file->getFilename(),
						'title' => $title
					);
				}
				else
				{
					$newFiles[] = $file;
				}
			}
		}

		$addonId = XenForo_Application::getConfig()->development->default_addon;
		foreach ($newFiles AS $file)
		{
			$writer = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');
			$writer->set('template', file_get_contents($file->getFilepath()));
			list ($title, $ext) = explode('.', $file->getFilename());
			$writer->set('title', $title);
			$writer->set('addon_id', $addonId);
			$writer->save();

			$newFilename = $title . '.' . $writer->get('template_id') . '.' . $ext;
			rename($file->getFilepath, dirname($file->getFilePath()) . '/' . $newFilename);
			$files[$writer->get('template_id')] = array(
				'filename' => $newFilename,
				'title' => $title
			);
		}

		XenForo_Template_Admin::setTemplateFiles($files);*/

		parent::_handleCustomPreloadedData($data);
	}
}