<?php

/**
* A constant that must be made available for any other XenForo class to be included.
*
* @var boolean
*/
define('XENFORO_AUTOLOADER_SETUP', true);
define('DEVTOOLS_AUTOLOADER_SETUP', true);

class XenForo_Autoloader
{
	/**
	* Instance manager.
	*
	* @var XenForo_Autoloader
	*/
	private static $_instance;

	/**
	* Path to directory containing the application's library.
	*
	* @var string
	*/
	protected $_rootDir = '.';

	/**
	* Stores whether the autoloader has been setup yet.
	*
	* @var boolean
	*/
	protected $_setup = false;

	/**
	* Protected constructor. Use {@link getInstance()} instead.
	*/
	protected function __construct()
	{
	}

	/**
	* Setup the autoloader. This causes the environment to be setup as necessary.
	*
	* @param string Path to application library directory. See {@link $_rootDir}
	*/
	public function setupAutoloader($rootDir)
	{
		if ($this->_setup)
		{
			return;
		}

		$this->_rootDir = $rootDir;
		$this->_setupAutoloader();

		$this->_setup = true;
	}

	/**
	* Internal method that actually applies the autoloader. See {@link setupAutoloader()}
	* for external usage.
	*/
	protected function _setupAutoloader()
	{
		if (@ini_get('open_basedir'))
		{
			// many servers don't seem to set include_path correctly with open_basedir, so don't use it
			set_include_path($this->_rootDir . PATH_SEPARATOR . '.');
		}
		else
		{
			set_include_path($this->_rootDir . PATH_SEPARATOR . '.' . PATH_SEPARATOR . get_include_path());
		}

		/*require_once('Zend/Loader/Autoloader.php');

		$autoloader = Zend_Loader_Autoloader::getInstance();
		$autoloader->pushAutoloader(array($this, 'autoload'));*/
		spl_autoload_register(array($this, 'autoload'));
	}

	/**
	* Autoload the specified class.
	*
	* @param string $class Name of class to autoload
	*
	* @return boolean
	*/
	public function autoload($class)
	{
		if (class_exists($class, false) || interface_exists($class, false))
		{
			return true;
		}

		if ($class == 'utf8_entity_decoder')
		{
			return true;
		}

		if (substr($class, 0, 5) == 'XFCP_')
		{
			throw new XenForo_Exception('Cannot load class using XFCP. Load the class using the correct loader first.');
		}

		$filename = $this->autoloaderClassToFile($class);
		if (!$filename)
		{
			return false;
		}

		// Check for DevTools version, if it exists and extends the XFCP class we do some hacky stuff to overwrite XenForo
		if (strpos($class, 'XenForo_') === 0)
		{
			$dtClass = 'DevTools_' . substr($class, 8);
			$dtFilename = $this->autoloaderClassToFile($dtClass);
			if (file_exists($dtFilename))
			{
				$contents = trim(file_get_contents($dtFilename), '<?php');
				$definition = 'class ' . $dtClass . ' extends XFCP_' . $dtClass;
				if (($pos = strpos($contents, $definition)) !== false)
				{
					$xfContents = trim(file_get_contents($filename), '<?php');
					$xfDefinition = 'class ' . $class;
					$xfPos = strpos($xfContents, $xfDefinition);
					if ($xfPos === false)
					{
						return false;
					}
					// Load the XenForo file but with the class renamed to XFCP_DevTools_*
					eval(substr($xfContents, 0, $xfPos) . 'class XFCP_' . $dtClass . substr($xfContents, $xfPos + strlen($xfDefinition)));

					// Now we can load the DevTools file, but we need to rename it's definition to $class
					eval(substr($contents, 0, $pos) . 'class ' . $class . ' extends XFCP_' . $dtClass . substr($contents, $pos + strlen($class) + strlen($dtClass) + 21));

					return (class_exists($class, false) || interface_exists($class, false));
				}
			}
		}

		if (file_exists($filename))
		{
			include($filename);
			return (class_exists($class, false) || interface_exists($class, false));
		}

		return false;
	}

	/**
	* Resolves a class name to an autoload path.
	*
	* @param string Name of class to autoload
	*
	* @return string|false False if the class contains invalid characters.
	*/
	public function autoloaderClassToFile($class)
	{
		if (preg_match('#[^a-zA-Z0-9_]#', $class))
		{
			return false;
		}

		return $this->_rootDir . '/' . str_replace('_', '/', $class) . '.php';
	}

	/**
	 * Gets the autoloader's root directory.
	 *
	 * @return string
	 */
	public function getRootDir()
	{
		return $this->_rootDir;
	}

	/**
	* Gets the autoloader instance.
	*
	* @return XenForo_Autoloader
	*/
	public static final function getInstance()
	{
		if (!self::$_instance)
		{
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	* Manually sets the autoloader instance. Use this to inject a modified version.
	*
	* @param XenForo_Autoloader|null
	*/
	public static function setInstance(XenForo_Autoloader $loader = null)
	{
		self::$_instance = $loader;
	}
}