<?php

class DevTools_DataWriter_Phrase extends XFCP_DevTools_DataWriter_Phrase
{
	const OPTION_DATA_FROM_FILE = 'dataFromFile';

	protected function _getDefaultOptions()
	{
		$options = parent::_getDefaultOptions();

		$options[self::OPTION_DATA_FROM_FILE] = false;
		return $options;
	}

	protected function _postSave()
	{
		parent::_postSave();

		if ( ! $this->getOption(self::OPTION_DATA_FROM_FILE))
		{
			DevTools_File_Phrase::postDataWriterSave($this);
		}
	}

	protected function _postDelete()
	{
		parent::_postDelete();

		if ( ! $this->getOption(self::OPTION_DATA_FROM_FILE))
		{
			DevTools_File_Phrase::postDataWriterDelete($this);
		}
	}
}