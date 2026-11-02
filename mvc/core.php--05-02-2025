<?php
/**
 * MVC System used to sepparate code from the layout/views, and also organize things better
 * This is a mini-framework used by the CargoITS Software
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Framework
 * @version 2020.10.13
 */
class MVC_Core
{
	/**
	 * The decoded "Settings" file
	 * @var array
	 */
	public static $settings = array();

	/**
	 * The currently used settings file ( will be overwritten by self::save_Settings() )
	 * @var string
	 */
	public static $settings_file;

	/**
	 * The Sypc object used to load and dump config files.
	 * @var Spyc
	 */
	public static $spyc;

	/**
	 * The debug system object
	 * @var MVC_Debug
	 */
	public static $debug;

	/**
	 * The session object (so all modules and models can use it easy)
	 * @var MVC_Persist
	 */
	public static $ss;

	/**
	 * Global cache object, initially being inside session, but possibly moved in the future to it's own storage system ( without changing other parts of the code )
	 * TODO: Efficiency feature - MVC_Persist (Cache) should actually use a form of memory cache (APC or Memcached)
	 * @var MVC_Persist
	 */
	public static $cache;

	/**
	 * The primary Database connection (can be used by any modules and models)
	 * @var MVC_Database
	 */
	public static $db;

	/**
	 * Initializes the MVC core by setting up paths, loading configuration, etc.
	 */
	public static function init($settings_file = 'settings.yaml')
	{
		// allow loading libraries easy
		set_include_path(get_include_path().
			PATH_SEPARATOR. ROOT. DS.
			PATH_SEPARATOR. ROOT. DS. 'lib'.
			PATH_SEPARATOR. ROOT. DS. 'lib'. DS. 'phpmailer'
		);


 
		// autoloader
		spl_autoload_register(array(__CLASS__, 'autoload'));

		self::$settings_file = $settings_file;
		self::$spyc = new Spyc(); // needed to load the $settings_file
		self::$settings = self::$spyc-> loadFile(ROOT .DS. self::$settings_file);

		// setup debug system
		if(!isset(self::$settings['core']['debug']))
			// if debug parameters are not specified, initialize them to no debugging at all
			self::$settings['core']['debug'] = array(
				'level'=> "DISABLED"
			);
		// the object is always present, even if disabled, so errors can be properly captured!
		// depending on the initial parameters, the module decides if debug output is sent, to where
		self::$debug = new MVC_Debug(
			self::$settings['core']['debug']['level'],
			isset(self::$settings['core']['debug']['mode'])? self::$settings['core']['debug']['mode']: "php",
			isset(self::$settings['core']['debug']['settings'])? self::$settings['core']['debug']['settings']: array()
		);

		// Session always exist (since it's present in PHP anyway), just that MVC_Persist has some extra functions and verifications
		self::$ss = new MVC_Persist('session');
		self::$cache = new MVC_Persist('session', null, 'cache');

		// Connect to the database (All modules use a database, so no reason not to connect directly in Core)
		// -- It is possible that the MVC_Database will throw an exception if the connection fails to initialize, so capture the "Init" exception in order to display an error
		self::$db = new MVC_Database(self::$settings['database']);
		QueryFactory::set_Connection(self::$db);
	}

	/**
	 * Destroys the database and session objects which were created in self::init().
	 * Also writes in the debug system that the script is about to exit gracefully, then remove the debug object as well (which will send it's last remaining data)
	 * Then, ends the script.
	 * @param string $redirect Optional, address to redirect the user's browser after shutdown
	 * @param bool $save_original_uri Saves original URI before redirecting (Only if Redirect is true)
	 */
	public static function shutdown($redirect = null, $save_original_uri = false)
	{
		if(!empty($redirect)) {
			if($save_original_uri)
				MVC_Core::$ss-> original_request_uri = $_SERVER['REQUEST_URI'];
			header("Location: ".$redirect);
		}

		QueryFactory::unset_Connection();
		self::$db = null;
		self::$ss = null;
//		self::$debug-> dump("OK, engine stop.". (!empty($redirect)? " (Redirecting to: ".$redirect.")": ""), "MVC_Core");
		self::$debug = null;
		self::$settings = null;
		self::$spyc = null;
		if(!empty($redirect)) {
			$redir_page = file_get_contents(ROOT. DS. 'app'. DS. 'redirect.html');
			echo str_replace("{redirecturl}", $redirect, $redir_page);
		}
		die;
//		if(!App_Router::$ajax)
//			echo "\n<!-- This software has been built and is maintained by Wavetrex Multimedia Studios http://www.wavetrex.eu/ Contact: wavetrex@gmail.com -->";
	}

	/**
	 * Determines if the request is done through HTTPS
	 * @param $return_text If True, Returns the protocol name instead of boolean True/False
	 * @return boolean|string True if yes, False if not (or if $return_text is True, https / http)
	 */
	public static function HTTPS($return_text = false)
	{
		$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
		// If non-secure on the first attempt, try with forwarded protocol
		if(!$is_https)
			if(!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on')
				$is_https = true;

		if($return_text)
			return $is_https? "https": "http";
		return $is_https;
	}

	/**
	 * Saves the settings file
	 * TODO: Make this function more flexible (finding and replacing SPYC output with added comments, and comments should come from some language file)
	 * @param string $settings_file Optionally, specify a custom settings_file in which the data is saved
	 */
/*
	// DISABLED, due to incomplete implementation and the fact that current active domain affect the self::$settings variable
	// TODO: Find another way to save settings
 	static public function save_Settings($settings_file = null)
	{
		// add some comments inside the settings file, to be easier to see when editing manually
		// TODO: Move this somewhere else, and replace the code here by a foreach(), in order to keep the core.php clean
		$settings_yaml = str_replace(
			array(
				"---\n",
				"\ncore:\n",
				"\ndatabase:\n",
				"\nhtml:\n",
				"\nlanguages:\n"
			),
			array(
				"# The new Settings system for CargoClaims App\n---\n",
				"\n# Core settings go directly here\ncore:\n",
				"\n\n# Database connection related settings\ndatabase:\n",
				"\n\n# Settings related to the visual appearance of the application (these go into the output HTML)\nhtml:\n",
				"\n\n# Available languages (These can be regenerated by analysing the language folders)\nlanguages:\n"
			),
		self::$spyc-> dump(self::$settings));

		file_put_contents(ROOT .DS. (is_null($settings_file)?self::$settings_file:$settings_file), $settings_yaml);
	}
 */
	/**
	 * Autoloader system, used to locate files in the system by simply knowing their class
	 * @param string $class
	 */
	public static function autoload($class)
	{
		 
		
		// Attempt to locate the class in the folder system using prefixes
$class_frag = explode('_', strtolower($class));
$location = implode(DIRECTORY_SEPARATOR, $class_frag) . '.php';

if (self::real_path($location)) {
     require_once $location;
    return;
}

self::$debug->driver->output([
    'type' => "error",
    'data' => $class,
    'label' => "Core->Autoload fail",
]);
	}
	

	/**
	 * Returns the real path of a file name ( if absolute or relative )
	 *
	 * @param string $filename
	 */
	public static function real_path($filename)
	{
		// Check for absolute path
		if (realpath($filename) == $filename)
			return $filename;

		// Otherwise, treat as relative path
		$paths = explode(PATH_SEPARATOR, get_include_path());
		foreach($paths as $path) {
			if(substr($path, -1) == DS) $fullpath = $path . $filename;
			else $fullpath = $path . DS . $filename;

			if (file_exists($fullpath))
				return $fullpath;
		}

		return false;
	}

	/**
	 * Returns a controller object
	 * @param string $name Which controller?
	 * @return App_Ctrl_Base Note: Not MVC_Model, as the Base contains necessary methods for all controllers
	 */
	public static function factory_Controller($name)
	{
		$class = 'App_Ctrl_'. ucfirst(strtolower($name));
		$controller = new $class();
		return $controller;
	}

	/**
	 * Returns a model object
	 * @param string $name Which controller?
	 * @return MVC_Model
	 */
	public static function factory_Model($name)
	{
		$class = 'App_Model_'. ucfirst(strtolower($name));
		$model = new $class();
		return $model;
	}

	/**
	 * Captures the exception that might be thrown by a plugin on instancing (for further inspection)
	 * -- Plugins might not be implemented correctly and crash, but the software should gracefully handle them
	 * -- Note: This is not cleared automatically, but it overwritten by next caught exception
	 *
	 * @var Plugin_Exception
	 */
	private static $plugin_exception;

	/**
	 * Returns a plugin object
	 * @param string $type Plugin type (currently implemented: "field", "group")
	 * @param string $name Which plugin?
	 * @param array $params Parameters to send to the plugin constructor
	 * @param object $master The object which instanced this plugin (used for the plugin to communicate back to it's master)
	 * @return App_Plugin_*
	 */
	public static function factory_Plugin($type, $name, $params = null, $master = null)
	{
		$class = 'App_Plugin_'. ucfirst(strtolower($type)). '_'. ucfirst(strtolower(str_replace('_', '', $name)));
		// The plugin might not exist or have a problem in it's constructor, don't crash, just return null
		try {
			return new $class($params, $master);
		} catch (Plugin_Exception $e) {
			self::$plugin_exception = $e;
			return null;
		}
	}

	/**
	 * Returns the exception that might have been thrown in factory_Plugin
	 *
	 * @return Plugin_Exception
	 */
	public static function get_Plugin_Exception()
	{
		return self::$plugin_exception;
	}

	/**
	 * A little helper that creates "IDs" which are usable in URL's from standard text, by removing any characters which are not alphanumeric and underscore
	 * @param string $from
	 * @return string
	 */
	public static function generate_Named_Id($from = null)
	{
		if(is_null($from))
			return 'id_' . substr(md5(uniqid('id')), -8);
		else
			return trim(preg_replace('/[^a-z0-9_]+/i', '_', strtolower($from)),'_');
	}

	/**
	 * Makes a standard (no-breaks) JSON into a "pretty" JSON which can be seen easier by humans
	 * @param string $json
	 */
	public static function pretty_JSON($json)
	{
		$result = ''; $level = 0; $in_quotes = false; $in_escape = false; $ends_line_level = NULL;
		$json_length = strlen($json);

		for( $i = 0; $i < $json_length; $i++ ) {
			$char = $json[$i];
			$new_line_level = NULL;
			$post = "";
			if( $ends_line_level !== NULL ) {
				$new_line_level = $ends_line_level;
				$ends_line_level = NULL;
			}
			if ( $in_escape ) {
				$in_escape = false;
			} else if( $char === '"' ) {
				$in_quotes = !$in_quotes;
			} else if( ! $in_quotes ) {
				switch( $char ) {
					case '}': case ']':
						$level--;
						$ends_line_level = NULL;
						$new_line_level = $level;
						break;

					case '{': case '[':
						$level++;
					case ',':
						$ends_line_level = $level;
						break;

					case ':':
						$post = " ";
						break;

					case " ": case "\t": case "\n": case "\r":
						$char = "";
						$ends_line_level = $new_line_level;
						$new_line_level = NULL;
						break;
				}
			} else if ( $char === '\\' ) {
				$in_escape = true;
			}
			if( $new_line_level !== NULL ) {
				$result .= "\n".str_repeat( "  ", $new_line_level );
			}
			$result .= $char.$post;
		}

		return $result;
	}

	/**
	 * Little helper that combines content from existing JSON encoded fields with new data fields, and stores the result in the $new_data
	 *
	 * @param mixed $new_data_fields Array with new data fields
	 * @param string $original_JSON_fields Array with original data fields (in stored format)
	 * @param null|array $field_list If an array, it will only merge/encode these fields, and not all present in $new_data_fields
	 * @param bool $numeric_check When encoding after merge, numeric values are encoded as numbers/integers (even if they are in strings)
	 */
	public static function merge_JSON_Fields(&$new_data_fields, $original_JSON_fields, $field_list = null, $numeric_check = true)
	{
		if(is_null($field_list))
			$field_list = array_keys($new_data_fields);

		foreach($field_list as $field) {
			if(isset($new_data_fields[$field])) {

				// merge existing data with new data
				$merged_field = array_merge(json_decode($original_JSON_fields[$field], true)?:array(), $new_data_fields[$field]);

				// null values are eliminated from $new_data_fields before encoding
				foreach($merged_field as $key=> $value)
					if(is_null($value))
						unset($merged_field[$key]);

					// and finally, json_encode
					$new_data_fields[$field] = empty($merged_field)? null: @json_encode($merged_field, $numeric_check? JSON_NUMERIC_CHECK: 0);
			}
		}
	}

	/**
	 * Converts a number into letters or vice versa (with order inversion) - low obfuscation of numberic IDs for accessing resources (files, documents, contacts)
	 * - Direct:	[0123456789] => [wryipouteq]
	 * - Reverse:	[qwertyuiop] => [9081726354]
	 * Any other combination of characters will result in an error.
	 *
	 * @param string $text
	 */
	public static function qp_conv($text)
	{
		$qp = array('q','w','e','r','t','y','u','i','o','p'); $ninefour = array('9','0','8','1','7','2','6','3','5','4');
		if(is_numeric($text[0]))
			return str_replace($ninefour, $qp, strrev($text));
		else
			return str_replace($qp, $ninefour, strrev(strtolower($text)));
	}
}

/**
 * Some interesting methods that deal with multi-level arrays (arrays that contain other array in a tree-like structure)
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoClaims
 * @version 1.0.dev
 */
class Tree_Mapper
{
	/**
	 * The keymap
	 * @var array
	 */
	protected $keymap = array();

	/**
	 * Set the...
	 * @param array $array Array that will be studied
	 */
	public function __construct(&$array = array())
	{
		if(!empty($array))
			$this-> create_Keymap($array);
	}

	/**
	 * Constructs a list of all the successive array keys that are needed to reach non-array elements in a multi-level array
	 * The elements of the map are in the same order that the function "array_walk_recursive" would discover them.
	 * -- while AWR sends the key to the element processing function, it only keeps the key of the last-level element (making it impossible to locate the element directly by that key)
	 * -- This map however contains the full "path" to each non-array element
	 *
	 * @param array $arr The array to map, or nothing to use the array that has been set in the constructor
	 * @param array $pkey Keys until the call (prefix)
	 * @return array The map
	 */
	protected function create_Keymap($arr = array(), $pkey = array())
	{
		foreach($arr as $key=> &$value) {
			$ckey = array_merge($pkey, array($key)); // key list so far
			if(is_array($value))
				// go deep
				$this-> create_Keymap($value, $ckey);
			else
				$this-> keymap[] = $ckey; // reached a leaf, record it in the keymap
		}
	}

	/**
	 * Returns the generated keymap
	 * @return array
	 */
	public function get_Keymap()
	{
		return $this-> keymap;
	}

	/**
	 * Searches inside an external array using a keylist
	 * -- This method can use the elements of a "keymap" which was generated at construct time
	 * @param array $container
	 * @param multitype: array, int $keylist or index into $this-> keymap
	 * @return multitype: mixed, null Returns a reference to the value pointed by the keylist or null if not found in the initial container
	 */
	public function &search_By_Keylist(&$container, $keylist)
	{
		if(is_int($keylist))
			$keylist = $this-> keymap[$keylist];
		foreach($keylist as $key)
			if(isset($container[$key]))
				$container = &$container[$key];
			else {
				$null = null;
				return $null; // error, element not found
			}
		return $container;
	}

	/**
	 * Adds an item to a container in the position specified by a keylist, or simply creates an empty array at that location, if $item is not specified
	 * -- This method can use the elements of a "keymap" which was generated at construct time
	 * @param array $container
	 * @param multitype: array, int $keylist
	 * @param mixed $item
	 */
	public function add_By_Keylist(&$container, $keylist, &$item = null)
	{
		if(is_int($keylist))
			$keylist = $this-> keymap[$keylist];
		foreach($keylist as $key) {
			if(!isset($container[$key]))
				$container[$key] = array();
			$container = &$container[$key];
		}
		if(!is_null($item))
			$container = $item;
	}
}

/**
 * Base interface for any type of application plugin (there are multiple types)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Plugins
 * @version 2018.05
 */
interface App_Plugin
{
	/**
	 * Returns the plugin's configuration information
	 */
	public function get_Config();
}

/**
 * Wrapper class for Plugin Exceptions
 */
class Plugin_Exception extends Exception
{
	/**
	 * Returns an array containing the exception information in a format that can be stored in the log
	 */
	public function get_Error()
	{
		return array(
			'code'=> $this-> getCode(),
			'error'=> $this-> getMessage(),
		);
	}
}