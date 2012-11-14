<?php

class DevTools_File_Phrase extends DevTools_File_Abstract
{
	protected static $_originalFiles = array();

	protected static $_phraseModel = null;

	public function getDataType()
	{
		return 'Phrase';
	}

	protected function _loadFile()
	{
		parent::_loadFile();

		if (is_array($this->_data))
		{
			$addonId = substr(dirname($this->_data['filePath']), strrpos(dirname($this->_data['filePath']), '/') + 1);
			if ($addonId == 'phrases')
			{
				$addonId = '';
			}

			$this->_data += array(
				'addon_id' => $addonId,
				'global_cache' => strpos($this->_data['filePath'], 'global.txt') == strlen($this->_data['filePath']) - 10
			);
		}
	}

	protected function _insertFileToDb()
	{
		return $this->_updateDb();
	}

	protected function _updateFileInDb()
	{
		return $this->_updateDb();
	}

	protected function _updateDb()
	{
		list ($title, $id) = DevTools_Helper_File::getIdAndTitleFromFileName($this->_data['fileName']);
		$data = array(
			'title' => $title,
			'phrase_text' => $this->_data['contents'],
			'language_id' => 0,
			'global_cache' => $this->_data['global_cache'],
			'addon_id' => $this->_data['addon_id'],
		);

		if (!$this->_getPhraseModel()->canModifyPhraseInLanguage($data['language_id']))
		{
			$this->printDebugInfo('E: ' . new XenForo_Phrase('this_phrase_can_not_be_modified') . "\n");
			return 0;
		}

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Phrase');
		$writer->setOption(XenForo_DataWriter_Phrase::OPTION_DATA_FROM_FILE, true);

		if ( ! $this->isNewFile())
		{
			$writer->setExistingData($this->_data['id']);
		}

		$writer->bulkSet($data);
		if ($writer->isChanged('title') OR $writer->isChanged('phrase_text') OR $writer->get('language_id') > 0)
		{
			$writer->updateVersionId();
		}

		try
		{
			$this->assertNoDwErrors($writer, 'save', $this->getDataType());
		}
		catch (XenForo_Exception $e)
		{
			return 0;
		}
		catch (Exception $e)
		{
			throw $e;
		}

		$writer->save();

		if ( ! $this->isNewfile())
		{
			$this->printDebugInfo("- phrase updated in database\n");
		}

		return $writer->get('phrase_id');
	}

	protected function _deleteFileFromDb(array $oldData)
	{
		parent::_deleteFileFromDb($oldData);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Phrase');
		$dw->setOption(XenForo_DataWriter_Phrase::OPTION_DATA_FROM_FILE, true);
		$dw->setExistingData($oldData['id']);
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

	public function touchDb($id = null)
	{
		XenForo_Application::getDb()->query('
			UPDATE xf_phrase
			SET last_file_update = ?
			WHERE phrase_id = ?
		', array(XenForo_Application::$time, $id ? $id : $this->_data['id']));
	}

	public static function postDataWriterSave(XenForo_DataWriter $writer, array $extraData = array())
	{
		$self = new self();

		$oldPath = false;
		$oldData = array_merge($writer->getMergedExistingData(), array('id' => $writer->get('phrase_id')));
		if ($writer->isUpdate())
		{
			$oldPath = $self->getDirectory($writer->getMergedExistingData()) . self::$s . $self->getFileName($oldData);
		}

		$newPath = false;
		$newData = array_merge($writer->getMergedData(), array('id' => $writer->get('phrase_id')));
		if ($writer->isChanged('addon_id') OR $writer->isChanged('title'))
		{
			$newPath = $self->getDirectory($newData) . self::$s . $self->getFileName($newData);
		}

		if ( ! $oldPath)
		{
			$oldPath = $newPath;
		}

		if ( ! DevTools_Helper_File::write($oldPath, $writer->get('phrase_text'), array('id' => $writer->get('phrase_id'))))
		{
			throw new XenForo_Exception("Failed to write phrase file to $oldPath");
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
		$self = new self();

		$phrase = array(
			'id' => $writer->get('phrase_id'),
			'contents' => $writer->get('phrase_text'),
			'addon_id' => $writer->get('addon_id'),
			'title' => $writer->get('title')
		);
		$phrase['fileName'] = $self->getFileName($phrase);
		$self->trashFile($phrase);

		unlink($self->getDirectory($phrase) . self::$s . $phrase['fileName']);
	}

	public function getDirectory(array $data = array())
	{
		// TODO: languages
		$dir = XenForo_Application::getInstance()->getRootDir() . self::$s . 'phrases';
		if (!empty($data['addon_id']))
		{
			$dir .= self::$s . $data['addon_id'];
		}

		return $dir;
	}

	public function getFileName(array $data)
	{
		return $data['title'] . (empty($data['id']) ? '' : '.' . $data['id']) . (empty($data['global_cache']) ? '' : '.global') . '.txt';
	}

	public function getOriginalFiles()
	{
		return self::$_originalFiles;
	}

	public static function setOriginalFiles($originalFiles)
	{
		self::$_originalFiles = $originalFiles;
	}

	protected function _getPhraseModel()
	{
		if (self::$_phraseModel === null)
		{
			self::$_phraseModel = XenForo_Model::create('XenForo_Model_Phrase');
		}

		return self::$_phraseModel;
	}
}