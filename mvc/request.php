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
	const _REQUEST =7;

	const ERROR_superglobal = "MVC_Request :: Unable to determine which Request SuperGlobal is being used";
	const ERROR_cantchange = "MVC_Request :: Unable to remove _COOKIES or change _SERVER environment";
	private $_superglobal=self::_REQUEST;
	public function __construct($superglobal = "request")
	{
		$this-> set_Request_Type($superglobal);
	}
	private $_filters = array();
	public function add_Filter($parameter, $filter_type, $filter_cfg = null)
	{
		if(!isset($this->_filters[$parameter]))
			$this->_filters[$parameter] = array();
		// add each filter
		$this->_filters[$parameter][] = new MVC_Filter($filter_type, $filter_cfg);
	}
	public function clean_Filters($parameter)
	{
		unset($this->_filters[$parameter]);
	}
	public function __get($name)
	{
		switch($this->_superglobal) {
			case self::_REQUEST:
				if(!isset($_REQUEST[$name])) return null;
				$input = $_REQUEST[$name]; break;
			case self::_GET:
				if(!isset($_GET[$name])) return null;
				$input = $_GET[$name]; break;
			case self::_POST:
				if(!isset($_POST[$name])) return null;
				$input = $_POST[$name]; break;
			case self::_COOKIE:
				if(!isset($_COOKIE[$name])) return null;
				$input = $_COOKIE[$name]; break;
			case self::_SERVER:
				if(!isset($_SERVER[$name])) return null;
				$input = $_SERVER[$name]; break;
			default:
				throw new Exception(self::ERROR_superglobal);
		}
		if(isset($this->_filters[$name])) {
			foreach($this->_filters[$name] as $filter){
				$input = $filter-> process($input);
			}
		}
		return $input;
	}
	public function __set($name, $value)
	{
		switch($this->_superglobal) {
			case self::_REQUEST:
				$_REQUEST[$name] = $value; break;
			case self::_GET:
				$_GET[$name] = $value; break;
			case self::_POST:
				$_POST[$name] = $value; break;
			// Cookie and Server cannot be changed
			case self::_COOKIE:
			case self::_SERVER:
				throw new Exception(self::ERROR_cantchange);
			default:
				throw new Exception(self::ERROR_superglobal);
		}
	}
	public function __isset($name)
	{
		switch($this->_superglobal) {
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
	public function __unset($name)
	{
		switch($this->_superglobal) {
			case self::_REQUEST:
				unset($_REQUEST[$name]); break;
			case self::_GET:
				unset($_GET[$name]); break;
			case self::_POST:
				unset($_POST[$name]); break;
			// Cookie and Server cannot be changed
			case self::_COOKIE:
			case self::_SERVER:
				throw new Exception(self::ERROR_cantchange);
			default:
				throw new Exception(self::ERROR_superglobal);
		}

		if(isset($this->_filters[$name]))
			unset($this->_filters[$name]);
	}
	public function &request_toArray()
	{
		switch($this->_superglobal) {
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
	public function set_Request_Type($superglobal='request')
	{
		switch(strtolower($superglobal)) {
			case 'request':
				$this->_superglobal=self::_REQUEST; break;
			case 'get':
				$this->_superglobal=self::_GET; break;
			case 'post':
				$this->_superglobal=self::_POST; break;
			case 'cookie':
				$this->_superglobal=self::_COOKIE; break;
			case 'server':
				$this->_superglobal=self::_SERVER; break;
			default:
				throw new Exception("Unknown/Unsuported SuperGlobal: ".$superglobal);
		}
	}
}