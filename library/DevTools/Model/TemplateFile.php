<?php

class DevTools_Model_TemplateFile extends XenForo_Model
{
	protected static $_styleFiles = array();

	protected $_templates = array();

	public function writeTemplatesToFileSystem()
	{
		new DevTools_Helper_Xattr;
		
		$styles = array(-1 => 'admin', 0 => 'master');
		/* TODO: other styles
		$styles += $this->_getDb()->fetchPairs('
			SELECT style_id, title
			FROM xf_style
		');*/

		$templates = array();
		foreach ($styles AS $style => $title)
		{
			$templates[$style] = $this->getTemplates($style);
		}

		$rootDir = XenForo_Application::getInstance()->getRootDir() . '/templates/';
		foreach ($templates AS $styleId => $temps)
		{
			$dir = $rootDir;
			if ($styleId < 1)
			{
				$dir .= $styles[$styleId] . '/';
			}
			else
			{
				$dir .= XenForo_Link::getTitleForUrl($styles[$styleId]) . '.' . $styleId . '/';
				// TODO: other styles
				continue;
			}

			if (empty($temps))
			{
				// At least create the folder
				XenForo_Helper_File::createDirectory($dir);
			}

			foreach ($temps AS $template)
			{
				$filePath = $dir;
				if (!empty($template['addon_id']))
				{
					$filePath .= $template['addon_id'] . '/';
				}

				$filePath .= $this->addFileExtension($template['title']);
				DevTools_Helper_TemplateFile::write($filePath, $template['template'], array('id' => $template['template_id']));
				$this->updateLastUpdateTime($template, filemtime($filePath));
			}
		}

		$addons = $this->getModelFromCache('XenForo_Model_AddOn')->getAllAddOns();
		foreach ($addons AS $addon)
		{
			XenForo_Helper_File::createDirectory($rootDir . '/admin/' . $addon['addon_id']);
			XenForo_Helper_File::createDirectory($rootDir . '/master/' . $addon['addon_id']);
			// TODO: other styles here
		}
	}

	public function getAllStyleFiles()
	{
		if (empty(self::$_styleFiles))
		{
			$rootDir = new DirectoryIterator(XenForo_Application::getInstance()->getRootDir() . '/templates');
			$files = array();
			foreach ($rootDir AS $dir)
			{
				if ($dir->isDot() || substr($dir->getFilename(), 0, 1) == '.')
				{
					continue;
				}

				if ($dir->isDir())
				{
					if ($dir->getFilename() == 'admin')
					{
						$styleId = -1;
					}
					else if ($dir->getFilename() == 'master')
					{
						$styleId = 0;
					}
					else
					{
						list ($yo, $styleId) = explode('.', $dir->getFilename());
						// TODO: other styles
						continue;
					}

					$files[$styleId] = array();
					$dirIterate = new DirectoryIterator($dir->getPathname());
					foreach ($dirIterate AS $addon)
					{
						if ($addon->isDot() || substr($addon->getFilename(), 0, 1) == '.')
						{
							continue;
						}

						if ($addon->isFile())
						{
							if (in_array(XenForo_Helper_File::getFileExtension($addon->getFilename()), array('html', 'css')))
							{
								$files[$styleId][$addon->getFilename()] = array(
									'title' => str_replace('.html', '', $addon->getFilename()),
									'templateId' => (int) xattr_get($addon->getPathname(), 'id'),
									'addonId' => '',
									'styleId' => $styleId,
									'path' => $addon->getPathname(),
									'mTime' => filemtime($addon->getPathname())
								);
							}
						}
						else if ($addon->isDir())
						{
							$addonIterate = new DirectoryIterator($addon->getPathname());
							foreach ($addonIterate AS $file)
							{
								if ($file->isDot() || substr($file->getFilename(), 0, 1) == '.')
								{
									continue;
								}

								if (in_array(XenForo_Helper_File::getFileExtension($file->getFilename()), array('html', 'css')))
								{
									$files[$styleId][$addon->getFilename()][$file->getFilename()] = array(
										'title' => str_replace('.html', '', $file->getFilename()),
										'templateId' => (int) xattr_get($file->getPathname(), 'id'),
										'addonId' => $addon->getFilename(),
										'styleId' => $styleId,
										'path' => $file->getPathname(),
										'mTime' => filemtime($file->getPathname())
									);
								}
							}
						}
					}
				}
			}

			self::$_styleFiles = $files;
		}

		return self::$_styleFiles;
	}

	public function getStyleFiles($styleId)
	{
		$allFiles = $this->getAllStyleFiles();
		if (isset($allFiles[$styleId]))
		{
			return $allFiles[$styleId];
		}

		return false;
	}

	public function getTemplates($styleId)
	{
		if (!isset($this->_templates[$styleId]))
		{
			if ($styleId == -1)
			{
				$this->_templates[$styleId] = $this->fetchAllKeyed('
					SELECT *, -1 AS style_id
					FROM xf_admin_template
				', 'template_id');
			}
			else
			{
				$this->_templates[$styleId] = $this->fetchAllKeyed('
					SELECT *
					FROM xf_template
					WHERE style_id = ?
				', 'template_id', $styleId);
			}
		}

		return $this->_templates[$styleId];
	}

	public function getTemplateById($styleId, $templateId)
	{
		$templates = $this->getTemplates($styleId);
		if (isset($templates[$templateId]))
		{
			return $templates[$templateId];
		}

		return false;
	}

	// TODO: lots of duplicate code here, should probably flatten the array or a new method
	public function detectFileChanges($styleId, $detectModified = false)
	{
		$files = $this->getStyleFiles($styleId);
		$templates = $this->getTemplates($styleId);

		$newFiles = array();
		foreach ($files AS $file)
		{
			if (isset($file['title']))
			{
				// New file
				if (!$file['templateId'])
				{
					$newFiles[] = $file;
				}
				// TODO: deleted on 'other' side
				else if (!isset($templates[$file['templateId']]))
				{
					print_r($file);
					continue;
				}
				else
				{

					if ($detectModified && $file['mTime'] > $templates[$file['templateId']]['last_file_update'])
					{
						$this->updateModifiedTemplate($file['styleId'], $file['templateId'], $file['path']);
					}

					// Renamed
					if ($templates[$file['templateId']]['title'] != $file['title'])
					{
						$this->rename($templates[$file['templateId']], $file['title']);
					}
					// Addon changed
					if ($templates[$file['templateId']]['addon_id'] != $file['addonId'])
					{
						$this->changeAddon($templates[$file['templateId']], $file['addonId']);
					}

					// Mark the file as existing otherwise it gets deleted later
					$templates[$file['templateId']]['exists'] = true;
				}
			}
			else
			{
				foreach ($file AS $f)
				{
					// New file
					if (!$f['templateId'])
					{
						$newFiles[] = $f;
					}
					// TODO: deleted on 'other' side
					else if (!isset($templates[$f['templateId']]))
					{
						continue;
					}
					else
					{
						if ($detectModified && $f['mTime'] > $templates[$f['templateId']]['last_file_update'])
						{
							$this->updateModifiedTemplate($f['styleId'], $f['templateId'], $f['path']);
						}

						// Renamed
						if ($templates[$f['templateId']]['title'] != $f['title'])
						{
							$this->rename($templates[$f['templateId']], $f['title']);
						}
						// Addon changed
						if ($templates[$f['templateId']]['addon_id'] != $f['addonId'])
						{
							$this->changeAddon($templates[$f['templateId']], $f['addonId']);
						}

						// Mark the file as existing otherwise it gets deleted later
						$templates[$f['templateId']]['exists'] = true;
					}
				}
			}
		}

		foreach ($newFiles AS $file)
		{
			$templateId = $this->insert($file['title'], file_get_contents($file['path']), $file['styleId'], $file['addonId']);
			DevTools_Helper_TemplateFile::updateAttribute($file['path'], 'id', $templateId);
		}

		foreach ($templates AS $template)
		{
			if (empty($template['exists']))
			{
				$this->delete($template);
			}
		}
	}

	// TODO: create a getWriter($styleId) method

	public function updateModifiedTemplate($styleId, $templateId, $filePath)
	{
		$template = $this->getTemplateById($styleId, $templateId);
		if (($mTime = filemtime($filePath)) > $template['last_file_update'])
		{
			$contents = file_get_contents($filePath);

			$propertyPreSave = $this->_stylePropertiesPreSave($styleId, $contents);

			if ($styleId == -1)
			{
				$writer = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');
				$writer->setOption(XenForo_DataWriter_AdminTemplate::OPTION_DATA_FROM_FILE, true);
			}
			else
			{
				$writer = XenForo_DataWriter::create('XenForo_DataWriter_Template');
				$writer->setOption(XenForo_DataWriter_Template::OPTION_DATA_FROM_FILE, true);
				$writer->set('style_id', $styleId);
			}
			$writer->set('template', $contents);
			$writer->setExistingData($templateId);
			$writer->save();

			$this->_stylePropertiesPostSave($styleId, $propertyPreSave[0], $propertyPreSave[1]);

			$this->updateLastUpdateTime($template, $mTime);
		}
	}

	public function updateLastUpdateTime(array $template, $time)
	{
		// TODO: update datawriter properly and use it
		$this->_getDb()->query('
			UPDATE xf_' . ($template['style_id'] == -1 ? 'admin_' : '') . 'template
			SET last_file_update = ?
			WHERE template_id = ?
		', array($time, $template['template_id']));
	}

	public function insert($templateName, $contents, $styleId, $addonId)
	{
		$propertyPreSave = $this->_stylePropertiesPreSave($styleId, $contents);

		if ($styleId == -1)
		{
			$writer = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');
			$writer->setOption(XenForo_DataWriter_AdminTemplate::OPTION_DATA_FROM_FILE, true);
		}
		else
		{
			$writer = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			$writer->setOption(XenForo_DataWriter_Template::OPTION_DATA_FROM_FILE, true);
			$writer->set('style_id', $styleId);
		}
		$writer->set('template', $contents);
		$writer->set('title', $templateName);
		$writer->set('addon_id', $addonId);
		$writer->save();

		$this->_stylePropertiesPostSave($styleId, $propertyPreSave[0], $propertyPreSave[1]);

		return $writer->get('template_id');
	}

	public function rename(array $template, $newTitle)
	{
		if ($template['style_id'] == -1)
		{
			$writer = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');
			$writer->setOption(XenForo_DataWriter_AdminTemplate::OPTION_DATA_FROM_FILE, true);
		}
		else
		{
			$writer = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			$writer->setOption(XenForo_DataWriter_Template::OPTION_DATA_FROM_FILE, true);
		}

		$writer->setExistingData($template['template_id']);
		$writer->set('title', $newTitle);
		$writer->save();
	}

	public function changeAddon(array $template, $newAddonId)
	{
		if ($newAddonId != 'XenForo' && !$this->getModelFromCache('XenForo_Model_AddOn')->getAddonById($newAddonId))
		{
			// addon doesn't exist, lets clear it
			$newAddonId = '';
		}

		if ($template['style_id'] == -1)
		{
			$writer = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');
			$writer->setOption(XenForo_DataWriter_AdminTemplate::OPTION_DATA_FROM_FILE, true);
		}
		else
		{
			$writer = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			$writer->setOption(XenForo_DataWriter_Template::OPTION_DATA_FROM_FILE, true);
		}

		$writer->setExistingData($template['template_id']);
		$writer->set('addon_id', $newAddonId);
		$writer->save();
	}

	protected function _stylePropertiesPreSave($styleId, $contents)
	{
		$propertyModel = $this->getModelFromCache('XenForo_Model_StyleProperty');
		$properties = $propertyModel->keyPropertiesByName(
			$propertyModel->getEffectiveStylePropertiesInStyle($styleId)
		);
		$propertyChanges = $propertyModel->translateEditorPropertiesToArray(
			$contents, $contents, $properties
		);

		return array($propertyChanges, $properties);
	}

	protected function _stylePropertiesPostSave($styleId, $changes, $properties)
	{
		$this->getModelFromCache('XenForo_Model_StyleProperty')->saveStylePropertiesInStyleFromTemplate($styleId, $changes, $properties);
	}

	public function delete(array $template)
	{
		// make a backup
		$filePath = XenForo_Application::getInstance()->getRootDir() . '/templates/';
		if ($template['style_id'] == -1)
		{
			$filePath .= 'admin/';
		}
		else if ($template['style_id'] == 0)
		{
			$filePath .= 'master/';
		}
		else
		{
			// TODO: other styles
			//$filePath .= XenForo_Link::getTitleForUrl($styles[$styleId]) . '.' . $styleId . '/';
			return;
		}

		if (!empty($template['addon_id']))
		{
			$filePath .= $template['addon_id'] . '/';
		}

		$filePath .= '.trash/__' . $this->addFileExtension($template['title']);

		DevTools_Helper_TemplateFile::write($filePath, $template['template']);

		// Delete from db
		if ($template['style_id'] == -1)
		{
			$writer = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');
			$writer->setOption(XenForo_DataWriter_AdminTemplate::OPTION_DATA_FROM_FILE, true);
		}
		else
		{
			$writer = XenForo_DataWriter::create('XenForo_DataWriter_Template');
			$writer->setOption(XenForo_DataWriter_Template::OPTION_DATA_FROM_FILE, true);
		}

		$writer->setExistingData($template['template_id']);
		$writer->delete();
	}

	public function addFileExtension($file)
	{
		if (XenForo_Helper_File::getFileExtension($file) != 'css')
		{
			$file .= '.html';
		}

		return $file;
	}
}