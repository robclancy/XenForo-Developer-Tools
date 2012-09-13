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
		$data = array(
			'title' => str_replace('.txt', '', str_replace('.global.txt', '', $this->_data['fileName'])),
			'phrase_text' => $this->_data['contents'],
			'language_id' => 0,
			'global_cache' => $this->_data['global_cache'],
			'addon_id' => $this->_data['addon_id'],
		);

		if (!$this->_getPhraseModel()->canModifyPhraseInLanguage($data['language_id']))
		{
			$this->printDebugInfo('E: ' . new XenForo_Phrase('this_phrase_can_not_be_modified') . "\n");
			return 0;//$this->responseError(new XenForo_Phrase('this_phrase_can_not_be_modified'));
		}

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_Phrase');

		if (!$this->isNewFile())
		{
			$writer->setExistingData($this->_data['id']);
		}

		$writer->bulkSet($data);
		if ($writer->isChanged('title') || $writer->isChanged('phrase_text') || $writer->get('language_id') > 0)
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

		if (!$this->isNewfile())
		{
			$this->printDebugInfo("- phrase updated in database\n");
		}

		return $writer->get('phrase_id');
	}

	protected function _deleteFileFromDb(array $oldData)
	{
		parent::_deleteFileFromDb($oldData);

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Phrase');
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

	public function touchDb()
	{
		XenForo_Application::getDb()->query('
			UPDATE xf_phrase
			SET last_file_update = ?
			WHERE phrase_id = ?
		', array(XenForo_Application::$time, $this->_data['id']));
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
		return $data['title'] . (empty($data['global_cache']) ? '' : '.global') . '.txt';
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