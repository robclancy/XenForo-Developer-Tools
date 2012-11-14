<?php

class DevTools_Listener
{
	protected static $_checked = false;

	protected static $_syncOutput;

	protected static $_errors = false;

	public static function loadClass($class, array &$extend)
	{
		if (defined('DEVTOOLS_AUTOLOADER_SETUP'))
		{
			return;
		}

		// Note: the autoloader will extend these itself so we return above
		$extendedClasses = array(
			'XenForo_DataWriter_Template',
			'XenForo_DataWriter_AdminTemplate',
			'XenForo_DataWriter_Phrase',
		);

		foreach ($extendedClasses AS $extendedClass)
		{
			if ($class == $extendedClass)
			{
				// Do substr incase there is some class added at some point with XenForo in it twice
				$extend[] = 'DevTools' . substr($class, 7);
			}
		}
	}

	public static function frontControllerPreDispatch(XenForo_FrontController $fc, XenForo_RouteMatch &$routeMatch)
	{
		if (defined('DEVTOOLS_AUTOLOADER_SETUP') OR self::$_checked OR $routeMatch->getResponseType() != 'html')
		{
			return;
		}

		$paths 	= XenForo_Application::getInstance()->loadRequestPaths();
		$url 	= $paths['fullBasePath'] . 'filesync.php';

		if (class_exists('XenForo_Dependencies_Admin', false))
		{
			$url .= '?admin=1';
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		ob_start();
		curl_exec($ch);
		curl_close($ch);
		self::$_syncOutput = ob_get_contents();
		$logs = explode('<pre>', self::$_syncOutput);
		$errors = array();
		foreach ($logs AS $log)
		{
			$lines = explode("\n", $log);
			foreach ($lines AS $line)
			{
				if (strpos($line, 'E:') === 0)
				{
					$origLine = str_replace('E: ', '', $line);
					$line = '<strong>' . htmlspecialchars($origLine);
					$pos = strpos($line, ':');
					$line = substr($line, 0, $pos) . '</strong><i>' . substr($line, $pos);
					$pos = strpos($line, ' in ' . DIRECTORY_SEPARATOR); // hacked him!
					$line = substr($line, 0, $pos) . '</i>' . substr($line, $pos);
					$errors[] = $line;
					DevTools_ChromePhp::error(trim($origLine));
				}
				else
				{
					DevTools_ChromePhp::log(trim($line));
				}
			}
		}
		ob_end_clean();
		self::$_checked = true;
		self::$_errors = $errors;
	}

	public static function templatePostRender($templateName, &$content, array &$containerData, XenForo_Template_Abstract $template)
	{
		if (self::$_errors AND class_exists('XenForo_Dependencies_Admin', false) AND $templateName == 'PAGE_CONTAINER')
		{
			$pos = strpos($content, '<div id="header">') + 17;
			$content = substr($content, 0, $pos) . $template->create('devtools_errors', array('errors' => self::$_errors)) . substr($content, $pos);
		}
	}

	public static function templateHook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
	{
		if (self::$_errors AND class_exists('XenForo_Dependencies_Public', false) AND $hookName == 'body')
		{
			$contents = $template->create('devtools_errors', array('errors' => self::$_errors)) . $contents;
		}
	}
}