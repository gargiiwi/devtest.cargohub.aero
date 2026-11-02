<?php

/**
 * Used to grab the "Input" from HTTP (Get/Post/Request), and optionally filter it
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Framework
 * @version 2.7
 */
class MVC_Request
{
	const _SERVER =	1; // Read only - Information about server and accessing client
	const _GET =	2;
	const _POST =	3;
	const _COOKIE =	5; // Read only - Use MVC_Persist for the ability to write cookies as well
	const _REQUEST = 7;

	const ERROR_superglobal = "MVC_Request :: Unable to determine which Request SuperGlobal is being used";
	const ERROR_cantchange = "MVC_Request :: Unable to remove _COOKIES or change _SERVER environment";

	/**
	 * Superglobal to use when reading values ( get / post / request )
	 * @var int
	 */
	private $_superglobal = self::_REQUEST;

	/**
	 * Initializes the request object using one of the superglobals as data source
	 * @param string $superglobal
	 */
	public function __construct($superglobal = "request")
	{
		$this->set_Request_Type($superglobal);
	}

	/**
	 * List of filters that are applied to some input variables.
	 * Multiple filters can be applied on the same input variable !
	 * @var array[MVC_Filter]
	 */
	private $_filters = array();

	/**
	 * Adds a filter on the input parameter, which will be applied when asking for the parameter
	 *
	 * @param string $parameter Used as key into the superglobal array
	 * @param string $filter_type What kind of filter?
	 * @param mixed $filter_cfg Other parameters for the filter (required only by some)
	 */
	public function add_Filter($parameter, $filter_type, $filter_cfg = null)
	{
		if (!isset($this->_filters[$parameter]))
			$this->_filters[$parameter] = array();
		// add each filter
		$this->_filters[$parameter][] = new MVC_Filter($filter_type, $filter_cfg);
	}

	/**
	 * Removes all filters, allowing setting new ones
	 *
	 * @param string $parameter
	 */
	public function clean_Filters($parameter)
	{
		unset($this->_filters[$parameter]);
	}

	/**
	 * Retrieves the content of a superglobal entry (if it exists), or null if it does not.
	 * Filters are only applied on an existing parameter
	 *
	 * @param string $name The input parameter contained in the set superglobal
	 * @return mixed
	 */
	public function __get($name)
	{
		switch ($this->_superglobal) {
			case self::_REQUEST:
				if (!isset($_REQUEST[$name])) return null;
				$input = $_REQUEST[$name];
				break;
			case self::_GET:
				if (!isset($_GET[$name])) return null;
				$input = $_GET[$name];
				break;
			case self::_POST:
				if (!isset($_POST[$name])) return null;
				$input = $_POST[$name];
				break;
			case self::_COOKIE:
				if (!isset($_COOKIE[$name])) return null;
				$input = $_COOKIE[$name];
				break;
			case self::_SERVER:
				if (!isset($_SERVER[$name])) return null;
				$input = $_SERVER[$name];
				break;
			default:
				throw new Exception(self::ERROR_superglobal);
		}

		if (isset($this->_filters[$name])) {
			// run all the filters on the input
			foreach ($this->_filters[$name] as $filter)
				$input = $filter->process($input);
		}
		return $input;
	}

	/**
	 * Injects a value into the active superglobal as if it was received from input.
	 * - Used when data needs to be processed before being accessed from another part of the code
	 * @param string $name Input parameter name
	 * @param mixed $value
	 */
	public function __set($name, $value)
	{
		switch ($this->_superglobal) {
			case self::_REQUEST:
				$_REQUEST[$name] = $value;
				break;
			case self::_GET:
				$_GET[$name] = $value;
				break;
			case self::_POST:
				$_POST[$name] = $value;
				break;
			// Cookie and Server cannot be changed
			case self::_COOKIE:
			case self::_SERVER:
				throw new Exception(self::ERROR_cantchange);
			default:
				throw new Exception(self::ERROR_superglobal);
		}
	}

	/**
	 * Checks if a superglobal entry exists
	 * @param string $name
	 * @return bool
	 */
	public function __isset($name)
	{
		switch ($this->_superglobal) {
			case self::_REQUEST:
				return isset($_REQUEST[$name]);
			case self::_GET:
				return isset($_GET[$name]);
			case self::_POST:
				return isset($_POST[$name]);
			case self::_COOKIE:
				return isset($_COOKIE[$name]);
			case self::_SERVER:
				return isset($_SERVER[$name]);
			default:
				throw new Exception(self::ERROR_superglobal);
		}
	}

	/**
	 * Removes an entry from a superglobal
	 * -- Also cleans any applied filter objects
	 * @param string $name
	 */
	public function __unset($name)
	{
		switch ($this->_superglobal) {
			case self::_REQUEST:
				unset($_REQUEST[$name]);
				break;
			case self::_GET:
				unset($_GET[$name]);
				break;
			case self::_POST:
				unset($_POST[$name]);
				break;
			// Cookie and Server cannot be changed
			case self::_COOKIE:
			case self::_SERVER:
				throw new Exception(self::ERROR_cantchange);
			default:
				throw new Exception(self::ERROR_superglobal);
		}

		if (isset($this->_filters[$name]))
			unset($this->_filters[$name]);
	}

	/**
	 * Return request array directly. Note that in this case filtering is NOT applied !
	 * @return array
	 */
	public function &request_toArray()
	{
		switch ($this->_superglobal) {
			case self::_REQUEST:
				return $_REQUEST;
			case self::_GET:
				return $_GET;
			case self::_POST:
				return $_POST;
			case self::_COOKIE:
				return $_COOKIE;
			case self::_SERVER:
				return $_SERVER;
			default:
				throw new Exception(self::ERROR_superglobal);
		}
	}

	/**
	 * Sets the type of request data to use when getting values
	 *
	 * @param string $superglobal
	 */
	public function set_Request_Type($superglobal = 'request')
	{
		switch (strtolower($superglobal)) {
			case 'request':
				$this->_superglobal = self::_REQUEST;
				break;
			case 'get':
				$this->_superglobal = self::_GET;
				break;
			case 'post':
				$this->_superglobal = self::_POST;
				break;
			case 'cookie':
				$this->_superglobal = self::_COOKIE;
				break;
			case 'server':
				$this->_superglobal = self::_SERVER;
				break;
			default:
				throw new Exception("Unknown/Unsuported SuperGlobal: " . $superglobal);
		}
	}
}
