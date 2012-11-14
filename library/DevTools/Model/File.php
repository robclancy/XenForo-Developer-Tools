<?php

class DevTools_Model_File extends XenForo_Model
{
	public function runFullSync()
	{
		//$this->writeAllFiles();die();
		$this->syncTemplates('DevTools_File_Template_Admin', -1);
		$this->syncTemplates('DevTools_File_Template_Master', 0);

		foreach ($this->getModelFromCache('XenForo_Model_AddOn')->getAllAddOns() AS $addon)
		{
			$this->syncPhrases($addon['addon_id']);
		}
	}

	public function writeAllFiles()
	{
		$this->writeTemplates('DevTools_File_Template_Admin', -1);
		$this->writeTemplates('DevTools_File_Template_Master', 0);
		$this->writePhrases();
	}

	public function syncPhrases($addonId)
	{
		$phrases = $this->getModelFromCache('XenForo_Model_Phrase')->getMasterPhrasesInAddOn($addonId);
		$phraseFile = new DevTools_File_Phrase();
		$files = array();
		foreach ($phrases AS $phrase)
		{
			$files[$phrase['phrase_id']] = array_merge($phrase, array(
				'id' => $phrase['phrase_id'],
				'title' => $phrase['title'],
				'contents' => $phrase['phrase_text'],
				'lastModifiedTime' => $phrase['last_file_update'],
			));

			$files[$phrase['phrase_id']]['fileName'] = $phraseFile->getFileName($files[$phrase['phrase_id']]);
			$files[$phrase['phrase_id']]['filePath'] = $phraseFile->getDirectory($files[$phrase['phrase_id']]) . DIRECTORY_SEPARATOR . $files[$phrase['phrase_id']]['fileName'];
		}

		$extraFiles = array();
		$this->getFilesFromDirectory($phraseFile->getDirectory(array('addon_id' => $addonId)), $extraFiles);
		foreach ($extraFiles AS $k => $file)
		{
			$file['global_cache'] = strpos($file['fileName'], 'global.txt') == strlen($file['fileName']) - 10;
			$files[$k] = $file;
		}

		DevTools_File_Phrase::setOriginalFiles($files);

		$deletedFiles = array();
		$newFiles = array();
		foreach ($files AS $f)
		{
			$file = new DevTools_File_Phrase($f['filePath'], $f['id']);
			if ($file->isNewFile())
			{
				$newFiles[$f['filePath']] = $file;
			}
			else
			{
				$file->detectChangesAndUpdate();
			}
		}

		foreach ($newFiles AS $file)
		{
			$file->detectChangesAndUpdate();
		}
	}

	public function writePhrases()
	{
		DevTools_Helper_File::createDirectory(XenForo_Application::getInstance()->getRootDir() . DIRECTORY_SEPARATOR . 'phrases');

		$phrases = $this->getModelFromCache('XenForo_Model_Phrase')->getAllPhrasesInLanguage(0);
		$phraseFile = new DevTools_File_Phrase();
		foreach ($phrases AS $phrase)
		{
			$phrase['id'] = $phrase['phrase_id'];
			$filePath = $phraseFile->getDirectory($phrase) . DIRECTORY_SEPARATOR . $phraseFile->getFileName($phrase);
			if ( ! file_exists($filePath))
			{
				$phraseFile->printDebugInfo('Writing ' . $phraseFile->getDataType() . ' "' . $phrase['title'] . '" to ' . $filePath . '...');
				DevTools_Helper_File::write($filePath, $phrase['phrase_text']);

				$file = new DevTools_File_Phrase($filePath);
				$file->touchDb();
				$phraseFile->printDebugInfo(" done\n");
			}
		}

		foreach ($this->getModelFromCache('XenForo_Model_AddOn')->getAllAddOns() AS $addon)
		{
			DevTools_Helper_File::createDirectory($phraseFile->getDirectory($addon));
		}
	}

	public function writeTemplates($fileClass, $styleId)
	{
		DevTools_Helper_File::createDirectory(XenForo_Application::getInstance()->getRootDir() . DIRECTORY_SEPARATOR . 'templates');

		$templates = $this->getTemplates($styleId);
		$fileTemplate = new $fileClass();
		foreach ($templates AS $template)
		{
			$template['id'] = $template['template_id'];
			$filePath = $fileTemplate->getDirectory($template) . DIRECTORY_SEPARATOR . $fileTemplate->getFileName($template);
			if ( ! file_exists($filePath))
			{
				$contents = $this->getModelFromCache('XenForo_Model_StyleProperty')->replacePropertiesInTemplateForEditor(
					$template['template'], $styleId,
					$fileTemplate->getPropertiesInStyle($styleId)
				);
				$contents = $this->getModelFromCache('XenForo_Model_Template')->replaceIncludesWithLinkRel($contents);

				$fileTemplate->printDebugInfo('Writing ' . $fileTemplate->getDataType(). ' "' . $template['title'] . '" to ' . $filePath . '...');
				DevTools_Helper_File::write($filePath, $contents);
				$file = new $fileClass($filePath);
				$file->touchDb();
				$fileTemplate->printDebugInfo(" done\n");
			}
		}

		foreach ($this->getModelFromCache('XenForo_Model_AddOn')->getAllAddOns() AS $addon)
		{
			DevTools_Helper_File::createDirectory($fileTemplate->getDirectory($addon));
		}
	}

	public function syncTemplates($fileClass, $styleId)
	{
		$templates = $this->getTemplates($styleId);
		$files = array();
		$fileTemplate = new $fileClass();
		foreach ($templates AS $template)
		{
			$files[$template['template_id']] = array_merge($template, array(
				'id' => $template['template_id'],
				'title' => $template['title'],
				'filePath' => $fileTemplate->getDirectory($template),
				'contents' => $template['template'],
				'lastModifiedTime' => $template['last_file_update']
			));

			$files[$template['template_id']]['fileName'] = $fileTemplate->getFileName($files[$template['template_id']]);
			$files[$template['template_id']]['filePath'] .= DIRECTORY_SEPARATOR . $files[$template['template_id']]['fileName'];
		}

		$this->getFilesFromDirectory($fileTemplate->getDirectory(), $files);
		$fileClass::setOriginalFiles($files);

		$deletedFiles = array();
		$newFiles = array();
		foreach ($files AS $f)
		{
			$file = new $fileClass($f['filePath'], $f['id']);
			if ($file->isNewFile())
			{
				$newFiles[$f['filePath']] = $file;
			}
			else
			{
				$file->detectChangesAndUpdate();
			}
		}

		foreach ($newFiles AS $file)
		{
			$file->detectChangesAndUpdate();
		}
	}

	public function getFilesFromDirectory($dir, &$files)
	{
		$dir = new DirectoryIterator($dir);
		foreach ($dir AS $file)
		{
			if ($file->isDot() OR substr($file->getFilename(), 0, 1) == '.' OR $file->getFilename() == 'Thumbs.db' OR $file->getFilename() == 'desktop.ini')
			{
				continue;
			}

			if ($file->isDir())
			{
				$this->getFilesFromDirectory($file->getPathname(), $files);
				continue;
			}

			if ($file->isFile())
			{
				list($title, $id) = DevTools_Helper_File::getIdAndTitleFromFileName($file->getFileName());
				if ( ! $id OR ! isset($files[$id]) OR $files[$id]['filePath'] != $file->getPathname())
				{
					$files[-count($files) - 1] = array(
						'id' => $id,
						'title' => $title,
						'fileName' => $file->getFilename(),
						'filePath' => $file->getPathname(),
						'contents' => file_get_contents($file->getPathname()),
						'lastModifiedTime' => $file->getMTime(),
					);
				}
			}
		}
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
}