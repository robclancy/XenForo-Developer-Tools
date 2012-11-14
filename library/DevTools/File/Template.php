<?php

abstract class DevTools_File_Template extends DevTools_File_Abstract
{
	protected static $_adminTemplateModel = null;
	protected static $_templateModel = null;
	protected static $_propertyCache = array();
	protected static $_propertyModel = null;

	abstract public function getTemplateType();

	abstract protected function _getDataWriter();

	public function templateTouchDb($styleId)
	{
		XenForo_Application::getDb()->query('
			UPDATE xf_' . ($styleId == -1 ? 'admin_' : '') . 'template
			SET last_file_update = ?
			WHERE template_id = ?
		', array(XenForo_Application::$time, $this->_data['id']));
	}

	protected function _loadFile()
	{
		parent::_loadFile();

		if (is_array($this->_data))
		{
			$addonId = substr(dirname($this->_data['filePath']), strrpos(dirname($this->_data['filePath']), '/') + 1);
			if ($addonId == $this->getTemplateType())
			{
				$addonId = '';
			}

			$this->_data += array(
				'addon_id' => $addonId,
			);
		}
	}

	abstract protected function _updateDb();

	protected function _updateTemplateDb($styleId)
	{
		$dw = $this->_getDataWriter();
		$new = $this->isNewFile();
		$modified = ($new OR $this->isModified());
		if (!$new)
		{
			$dw->setExistingData($this->_data['id']);
		}

		if ($styleId != -1)
		{
			$dw->set('style_id', $styleId);
		}

		$title = $this->_data['fileName'];
		if (substr($title, -5) == '.html')
		{
			$title = substr($title, 0, -5);
		}

		$data = array(
			'title' => $title,
			'addon_id' => $this->_data['addon_id']
		);

		if ($modified)
		{
			$properties = $this->getPropertiesInStyle($styleId);

			$propertyChanges = $this->_getPropertyModel()->translateEditorPropertiesToArray(
				$this->_data['contents'], $contents, $properties
			);
			$contents = $this->_getTemplateModel()->replaceLinkRelWithIncludes($contents);

			$data['template'] = $contents;
		}

		$dw->bulkSet($data);
		try
		{
			$this->assertNoDwErrors($dw, 'save', $this->getDataType());
		}
		catch (XenForo_Exception $e)
		{
			return 0;
		}
		catch (Exception $e)
		{
			throw $e;
		}

		$dw->save();

		if (!$new AND $dw->isChanged('title'))
		{
			$this->printDebugInfo("- updated title to \"$title\"\n");
		}

		if (!$new AND $dw->isChanged('addon_id'))
		{
			$this->printDebugInfo('- updated addon_id to "' . $dw->get('addon_id') . "\"\n");
		}

		if ($modified)
		{
			if (!$new)
			{
				$this->printDebugInfo("- updated template contents\n");
			}

			$this->_getPropertyModel()->saveStylePropertiesInStyleFromTemplate(
				$styleId, $propertyChanges, $properties
			);
		}

		return $dw->get('template_id');
	}

	protected function _deleteFileFromDb(array $oldData)
	{
		parent::_deleteFileFromDb($oldData);

		$dw = $this->_getDataWriter();
		$dw->setExistingData($oldData);

		try
		{
			$this->assertNoDwErrors($dw, 'delete', $this->getDataType());
		}
		catch (XenForo_Exception $e)
		{
			return 0;
		}
		catch (Exception $e)
		{
			throw $e;
		}
		$dw->delete();
	}

	protected function _insertFileToDb()
	{
		return $this->_updateDb();
	}

	protected function _updateFileInDb()
	{
		return $this->_updateDb();
	}

	public static function postDataWriterSave(XenForo_DataWriter $writer, array $extraData = array())
	{
		if (!isset($extraData['styleId']) || !isset($extraData['self']))
		{
			return;
		}

		$styleId = $extraData['styleId'];
		$self = $extraData['self'];
		$oldPath = false;
		if ($writer->isUpdate())
		{
			$oldPath = $self->getDirectory($writer->getMergedExistingData()) . self::$s . $self->getFileName($writer->getMergedExistingData());
		}

		$newPath = false;
		if ($writer->isChanged('addon_id') OR $writer->isChanged('title'))
		{
			$newPath = $self->getDirectory($writer->getMergedData()) . self::$s . $self->getFileName($writer->getMergedData());
		}

		if (!$oldPath)
		{
			$oldPath = $newPath;
		}

		$contents = XenForo_Model::create('XenForo_Model_StyleProperty')->replacePropertiesInTemplateForEditor(
			$writer->get('template'), $styleId,
			$self->getPropertiesInStyle($styleId)
		);
		$contents = XenForo_Model::create('XenForo_Model_Template')->replaceIncludesWithLinkRel($contents);

		if (!DevTools_Helper_File::write($oldPath, $contents, array('id' => $writer->get('template_id'), 'dbName' => XenForo_Application::getConfig()->db->dbname)))
		{
			throw new XenForo_Exception("Failed to write template file to $oldPath");
			return;
		}

		if ($newPath && $oldPath)
		{
			rename($oldPath, $newPath);
		}

		$self->touchDb();
	}

	public static function postDataWriterDelete(XenForo_DataWriter $writer, array $extraData = array())
	{
		if (!isset($extraData['styleId']) || !isset($extraData['self']))
		{
			return;
		}

		$styleId = $extraData['styleId'];
		$self = $extraData['self'];

		$contents = XenForo_Model::create('XenForo_Model_StyleProperty')->replacePropertiesInTemplateForEditor(
			$writer->get('template'), $styleId,
			$self->getPropertiesInStyle($styleId)
		);
		$contents = XenForo_Model::create('XenForo_Model_Template')->replaceIncludesWithLinkRel($contents);

		$self->trashFile(array(
			'id' => $writer->get('template_id'),
			'contents' => $contents,
			'addon_id' => $writer->get('addon_id'),
			'fileName' => $self->getFileName($writer->getMergedData()),
			'title' => $writer->get('title')
		));

		unlink($self->getDirectory($writer->getMergedData()) . self::$s . $self->getFileName($writer->getMergedData()));

		$self->touchDb();
	}

	public function getDirectory(array $data = array())
	{
		$dir = XenForo_Application::getInstance()->getRootDir() . self::$s . 'templates' . self::$s . $this->getTemplateType();
		if (!empty($data['addon_id']))
		{
			$dir .= self::$s . $data['addon_id'];
		}

		return $dir;
	}

	public function getFileName(array $data)
	{
		if (strpos($data['title'], '.') === false)
		{
			return $data['title'] . '.html';
		}

		return $data['title'];
	}

	public function getPropertiesInStyle($styleId)
	{
		if (!isset(self::$_propertyCache[$styleId]))
		{
			$propertyModel = $this->_getPropertyModel();
			self::$_propertyCache[$styleId] = $propertyModel->keyPropertiesByName(
				$propertyModel->getEffectiveStylePropertiesInStyle($styleId)
			);
		}

		return self::$_propertyCache[$styleId];
	}

	/**
	 * @return XenForo_Model_AdminTemplate
	 */
	protected function _getAdminTemplateModel()
	{
		if (!self::$_adminTemplateModel)
		{
			self::$_adminTemplateModel = XenForo_Model::create('XenForo_Model_AdminTemplate');
		}

		return self::$_adminTemplateModel;
	}

	/**
	 * @return XenForo_Model_Template
	 */
	protected function _getTemplateModel()
	{
		if (!self::$_templateModel)
		{
			self::$_templateModel = XenForo_Model::create('XenForo_Model_Template');
		}

		return self::$_templateModel;
	}

	protected function _getPropertyModel()
	{
		if (!self::$_propertyModel)
		{
			self::$_propertyModel = XenForo_Model::create('XenForo_Model_StyleProperty');
		}

		return self::$_propertyModel;
	}
}