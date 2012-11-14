<?php

abstract class DevTools_File_Abstract
{
	public static $s = DIRECTORY_SEPARATOR;

	/* This should be an array of...
		(int) id,
		(str) dbName, - this is so we can copy a file from one install to another without issues
		(str) fileName,
		(str) contents,
		(int) lastModifiedTime,
		(str) filePath,
		(array) attributes - will containe id and dbname as well as anything extra
	 */
	protected $_data = null;

	protected $_filePath = '';

	protected $_id = 0;

	protected static $_updatedFiles = array();

	// This will be an array of all the files for this type each structured the same as $_data above.
	// It is gathered from the database to map out where everything should be to detect changes
	protected static $_originalFiles = array();

	public function __construct($path = '', $id = null, $originalFiles = null)
	{
		$this->_filePath = $path;
		$this->_id = $id;
		$this->_loadFile();
		if ($originalFiles !== null)
		{
			$this->setOriginalFiles($originalFiles);
		}
	}

	public static function setOriginalFiles($originalFiles)
	{
		self::$_originalFiles = $originalFiles;
	}

	protected function _oldData($attribute = null)
	{
		$files = $this->getOriginalFiles();
		if (!isset($files[$this->_data['id']]))
		{
			return false;
		}

		if ($attribute !== null)
		{
			if (isset($files[$this->_data['id']][$attribute]))
			{
				return $files[$this->_data['id']][$attribute];
			}

			return false;
		}

		return $files[$this->_data['id']];
	}

	// NOTE: when the file doesn't exist make $this->_data false to mark it as deleted
	protected function _loadFile()
	{
		if ( ! file_exists($this->_filePath))
		{
			if ($this->_id > 0)
			{
				foreach ($this->getOriginalFiles() AS $file)
				{
					list ($title, $id) = DevTools_Helper_File::getIdAndTitleFromFileName($file['fileName']);
					if ($id == $this->_id)
					{
						$this->_filePath = $file['filePath'];
					}
				}
			}

			if ( ! file_exists($this->_filePath))
			{
				$this->_data = false;
				return;
			}
		}

		$file = new SplFileInfo($this->_filePath);
		if ( ! $file->isFile() OR ! $file->isReadable() OR ! $file->isWritable())
		{
			return;
		}

		list ($title, $id) = DevTools_Helper_File::getIdAndTitleFromFileName($file->getFilename());
		$this->_data = array(
			'id' => $id,
			'title' => $title,
			'fileName' => $file->getFilename(),
			'contents' => file_get_contents($file->getPathname()),
			'lastModifiedTime' => $file->getMTime(),
			'filePath' => $file->getPathname(),
		);
	}

	public function detectChangesAndUpdate()
	{
		if ( ! $this->isDeleted() AND ! $this->isNewFile() AND ! $this->_oldData('lastModifiedTime'))
		{
			$this->touchDb();
			// Go to next load for this now
			return;
		}

		if ( ! $this->detectFileChanged())
		{
			if ( ! $this->detectFileDeleted())
			{
				$this->detectFileNew();
			}
		}
	}

	public function detectFileChanged()
	{
		if ($this->isRenamed() OR $this->isMoved() OR $this->isModified())
		{
			if ( ! in_array($this->_data['id'], self::$_updatedFiles))
			{
				$this->printDebugInfo('{datatype}: Detected file changes (' . $this->_oldData('fileName') . ")...\n");
				if ($this->_updateFileInDb())
				{
					$this->touchDb();
					$this->printDebugInfo("- updates done\n\n");
				}
				self::$_updatedFiles[] = $this->_data['id']; // sometimes things go twice, hack around it with this
				return true;
			}
		}

		return false;
	}

	public function detectFileDeleted()
	{
		if ($this->isDeleted())
		{
			foreach ($this->getOriginalFiles() AS $file)
			{
				if ($file['filePath'] == $this->_filePath)
				{
					$this->printDebugInfo('{datatype}: Detected file deleted (' . $file['fileName'] . ")...\n");
					$this->_deleteFileFromDb($file);
					$this->printDebugInfo("- deleted from database\n\n");
					return true;
				}
			}
		}

		return false;
	}

	public function detectFileNew()
	{
		if ($this->isNewFile())
		{
			$this->printDebugInfo('{datatype}: Detected new file (' . $this->_data['fileName'] . ")...\n");
			if ($id = $this->_insertFileToDb())
			{
				$this->printDebugInfo("- added to database ($id)\n");
				$newFileName = $this->getFileName(array_merge($this->_data, array('id' => $id)));
				rename($this->_data['filePath'], str_replace($this->_data['fileName'], '', $this->_data['filePath']) . self::$s . $newFileName);
				$this->touchDb($id);
				$this->printDebugInfo('- renamed file to "' . $newFileName . "\"\n\n");
			}
			return true;
		}

		return false;
	}

	public static function postDataWriterSave(XenForo_DataWriter $writer, array $extraData = array())
	{

	}

	public static function postDataWriterDelete(XenForo_DataWriter $writer, array $extraData = array())
	{

	}

	public function isNewFile()
	{
		if ($this->_data === null)
		{
			return false;
		}

		return empty($this->_data['id']) OR ! $this->_oldData();
	}

	public function isRenamed()
	{
		if ($this->_data === null)
		{
			return false;
		}

		if ($oldName = $this->_oldData('fileName'))
		{
			return $this->_data['fileName'] != $oldName;
		}

		return false;
	}

	public function isMoved()
	{
		if ($this->_data === null)
		{
			return false;
		}

		if ($oldPath = $this->_oldData('filePath'))
		{
			return $this->_data['filePath'] != $oldPath;
		}

		return false;
	}

	public function isModified()
	{
		if ($this->_data === null)
		{
			return false;
		}

		if ($this->_oldData() AND $this->_oldData('lastModifiedTime'))
		{
			return $this->_data['lastModifiedTime'] > $this->_oldData('lastModifiedTime');
		}

		return false;
	}

	public function isDeleted()
	{
		return $this->_data === false;
	}

	abstract protected function _insertFileToDb();

	abstract protected function _updateFileInDb();

	// Have to do it like this once again due to a lack of late static binding
	abstract public function getOriginalFiles();

	protected function _deleteFileFromDb(array $oldData)
	{
		$this->trashFile($oldData);
	}

	abstract public function touchDb($id = null);

	public function trashFile(array $oldData)
	{
		$trashPath = $this->getDirectory($oldData) . self::$s . '.trash' . self::$s . '__' . $oldData['id'] . '.' . $this->getFileName($oldData);
		if (DevTools_Helper_File::createDirectory(dirname($trashPath)))
		{
			DevTools_Helper_File::write($trashPath, $oldData['contents']);
			$this->printDebugInfo("- backed up to \"$trashPath\"\n");
		}
	}

	public function getFileName(array $data)
	{
		return $data['fileName'];
	}

	abstract public function getDirectory(array $data = array());

	// For CLI and debug use
	public function printDebugInfo($str)
	{
		static $started = false;

		if ($this->showDebugInfo())
		{
			if (PHP_SAPI != 'cli' AND !$started)
			{
				echo '<pre>';
				$started = true;
			}
			$str = str_replace('{datatype}', $this->getDataType(), $str);
			DevTools_ChromePhp::log($str);
			echo $str;
		}
	}

	public function showDebugInfo()
	{
		return XenForo_Application::getConfig()->showDevToolsDebugInfo;
	}

	abstract public function getDataType();

	// FIXME: a lot of this is specific to templates, we need to handle it all better
	public function assertNoDwErrors(XenForo_DataWriter $dw, $checkMethod, $dataType)
	{
		switch (strtolower($checkMethod))
		{
			case 'delete';
			case 'predelete':
				$checkMethod = 'preDelete';
				break;

			case 'save':
			case 'presave':
			default:
				$checkMethod = 'preSave';
				break;
		}

		$dw->$checkMethod();

		if ($errors = $dw->getErrors())
		{
			$filePath = $this->getDirectory($dw->getMergedData()) . self::$s . $this->getFileName($dw->getMergedData());
			if (count($errors) == 1)
			{
				$error = htmlspecialchars_decode($errors[key($errors)]);
				// Yes Nathan, I could use regex but fuu
				if (strpos($error, 'Line ') === 0)
				{
					$pos = strpos($error, ':');
					$line = substr($error, 5, $pos - 5);
					$error = trim(substr($error, $pos + 1));
				}
				$errorString = 'E: ' . $dataType . ' Error: ' . $error . " in " . $filePath;
				if (isset($line))
				{
					$errorString .= ", line $line \n\n";
				}
				else
				{
					$errorString .= "\n\n";
				}
			}
			else
			{
				foreach ($errors AS &$err)
				{
					$err = htmlspecialchars_decode($err);
				}

				$errorString = 'E: ' . $dataType . " Error: \nin " . $filePath . "\n";
				$errorString .= implode("\n", $errors) . "\n\n";
			}

			XenForo_Helper_File::log('devtools-error', str_replace("\n", ' ', $errorString));

			$this->printDebugInfo($errorString);

			die(); // throwing wasn't working with console so just doing this
			throw new XenForo_Exception($errorString);
		}
	}
}