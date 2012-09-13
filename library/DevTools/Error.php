<?php

abstract class DevTools_Error extends XFCP_DevTools_Error
{
	// Copy this whole method because I can't use late static binding with PHP 5.2 :(
	public static function unexpectedException(Exception $e)
	{
		@header('Content-Type: text/html; charset=utf-8', true, 500);

		$upgradePending = false;
		try
		{
			$db = XenForo_Application::getDb();
			if ($db->isConnected())
			{
				$dbVersionId = $db->fetchOne("SELECT option_value FROM xf_option WHERE option_id = 'currentVersionId'");
				if ($dbVersionId && $dbVersionId != XenForo_Application::$versionId)
				{
					$upgradePending = true;
				}
			}
		}
		catch (Exception $e) {}

		if (XenForo_Application::debugMode())
		{
			$showTrace = true;
		}
		else if (XenForo_Visitor::hasInstance())
		{
			$showTrace = XenForo_Visitor::getInstance()->is_admin;
		}
		else
		{
			$showTrace = false;
		}

		if ($upgradePending)
		{
			echo self::_getPhrasedTextIfPossible(
				'The board is currently being upgraded. Please check back later.',
				'board_currently_being_upgraded'
			);
		}
		else if (!empty($showTrace))
		{
			echo self::getExceptionTrace($e);
		}
		else if ($e instanceof Zend_Db_Exception)
		{
			$message = $e->getMessage();

			echo self::_getPhrasedTextIfPossible(
				'An unexpected database error occurred. Please try again later.',
				'unexpected_database_error_occurred'
			);
			echo "\n<!-- " . htmlspecialchars($message) . " -->";
		}
		else
		{
			echo self::_getPhrasedTextIfPossible(
				'An unexpected error occurred. Please try again later.',
				'unexpected_error_occurred'
			);
		}
	}

	public static function getExceptionTrace(Exception $e)
	{
		$cwd = str_replace('\\', '/', dirname(dirname(dirname(__FILE__)))) . '/';

		$file = str_replace($cwd, '', $e->getFile());
		$trace = str_replace($cwd, '', $e->getTraceAsString());
		$str = PHP_EOL . "An exception occurred: {$e->getMessage()} in {$file} on line {$e->getLine()}" . PHP_EOL . $trace . PHP_EOL;
		DevTools_ChromePhp::error($str);
		return (PHP_SAPI == 'cli' ? '' : '<pre>') . $str;
	}
}