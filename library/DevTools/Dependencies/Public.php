<?php

class DevTools_Dependencies_Public extends XFCP_DevTools_Dependencies_Public
{
	protected function _handleCustomPreloadedData(array &$data)
	{
		$templateFileModel = XenForo_Model::create('DevTools_Model_TemplateFile');

		//$templateFileModel->writeTemplatesToFileSystem();
		$templateFileModel->detectFileChanges(0);

		parent::_handleCustomPreloadedData($data);
	}
}