<?php

$startTime = microtime(true);
$fileDir = dirname($_SERVER["SCRIPT_FILENAME"]);

require($fileDir . '/library/DevTools/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');

XenForo_Application::initialize($fileDir . '/library', $fileDir);
XenForo_Application::set('page_start_time', $startTime);

if (PHP_SAPI == 'cli')
{
	XenForo_Model::create('DevTools_Model_File')->runFullSync();
	//echo 'Execution time: ' . number_format(microtime(true) - $startTime, 3) . "\n";
	exit;
}

ob_start();

// TODO: break this into parts etc based on GET
$class = isset($_GET['admin']) == -1 ? 'DevTools_File_Template_Admin' : 'DevTools_File_Template_Master';
XenForo_Model::create('DevTools_Model_File')->syncTemplates($class, isset($_GET['admin']) ? -1 : 0);
XenForo_Model::create('DevTools_Model_File')->syncPhrases(XenForo_Application::getConfig()->development->default_addon);

ob_flush();

//$templateFileModel = XenForo_Model::create('DevTools_Model_TemplateFile');
//$templateFileModel->detectFileChanges(isset($_GET['admin']) ? -1 : 0, true);