<?php

$startTime = microtime(true);
$fileDir = dirname($_SERVER["SCRIPT_FILENAME"]);

require($fileDir . '/library/DevTools/Autoloader.php');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . '/library');

XenForo_Application::initialize($fileDir . '/library', $fileDir);
XenForo_Application::set('page_start_time', $startTime);

$deps = new XenForo_Dependencies_Public;
$deps->preLoadData();

$templateFileModel = XenForo_Model::create('DevTools_Model_TemplateFile');
$templateFileModel->detectFileChanges(isset($_GET['admin']) ? -1 : 0, true);