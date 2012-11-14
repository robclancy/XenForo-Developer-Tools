<?php

class DevTools_File_Template_Admin extends DevTools_File_Template
{
	protected static $_originalFiles = array();

	public function getDataType()
	{
		return 'Admin Template';
	}

	public function getTemplateType()
	{
		return 'admin';
	}

	public function touchDb($id = null)
	{
		$this->templateTouchDb(-1);
	}

	protected function _getDataWriter()
	{
		$dw = XenForo_DataWriter::create('XenForo_DataWriter_AdminTemplate');
		$dw->setOption(XenForo_DataWriter_AdminTemplate::OPTION_DATA_FROM_FILE, true);
		return $dw;
	}

	protected function _updateDb()
	{
		return $this->_updateTemplateDb(-1);
	}

	public static function postDataWriterSave(XenForo_DataWriter $writer, array $extraData = array())
	{
		$extraData = array_merge(array(
			'styleId' => -1,
			'self' => new self(''),
		), $extraData);

		parent::postDataWriterSave($writer, $extraData);
	}

	public static function postDataWriterDelete(XenForo_DataWriter $writer, array $extraData = array())
	{
		$extraData = array_merge(array(
			'styleId' => -1,
			'self' => new self(''),
		), $extraData);

		parent::postDataWriterDelete($writer, $extraData);
	}

	public function getOriginalFiles()
	{
		return self::$_originalFiles;
	}

	public static function setOriginalFiles($originalFiles)
	{
		self::$_originalFiles = $originalFiles;
	}
}