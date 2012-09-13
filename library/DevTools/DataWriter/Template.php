<?php

class DevTools_DataWriter_Template extends XFCP_DevTools_DataWriter_Template
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

		if (!$this->getOption(self::OPTION_DATA_FROM_FILE) AND $this->get('style_id') == 0)
		{
			DevTools_File_Template_Master::postDataWriterSave($this);
		}
	}

	protected function _postDelete()
	{
		parent::_postDelete();

		if (!$this->getOption(self::OPTION_DATA_FROM_FILE) AND $this->get('style_id') == 0)
		{
			DevTools_File_Template_Master::postDataWriterDelete($this);
		}
	}
}