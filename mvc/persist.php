<?php
/**
 * Wrapper for persistent data (which exists between page loads) - Session vars or Cookie vars
 * Provides support for getting, setting or unsetting variables
 * (Note that for Cookies to work, no output must be sent to the browser while the object is still in use !)
 *
 * @todo Support memory based storage (APC, Memcache) in the future, or a Nosql mechanism based on SQLite3, or simply "Files" (serialized, or JSON encoded)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Framework
 * @version 2.7
 */
class MVC_Persist
{
	/**
	 * What type of vars to use ( session or cookie )
	 * @var string
	 */
	private $mode;

	/**
	 * Cookie age (only for cookie mode) - Defaults to 30 days
	 * @var int
	 */
	private $age = 2592000;

	/**
	 * Certain storage engines use a "key" to sepparate data from one session to another.
	 * -- SESSION engine: While the session engine itself doesn't need it, when present it means an array key inside $_SESSION variable
	 * -- COOKIE engine: Key not currently supported (should become cookie name instead?)
	 * @var string
	 */
	private $key = null;

	private static $session_started = false;

	/**
	 * Initializes with one of the supported modes
	 * @param string $mode
	 * @param int $age For certain storage engines, it indicates how long the persistent data survives (in seconds)
	 *
	 * @throws Exception
	 */
	public function __construct($mode, $age = null, $key = null)
	{
		$this-> mode = strtolower($mode);
		if(!in_array($this-> mode, array('session', 'cookie')))
			throw new Exception("Unsupported Data Persistence mode: ". $mode);

		if(!empty($key))
			$this-> key = $key;

		if($this-> mode == 'session' && self::$session_started == false) {
			session_start();
			self::$session_started = true;
			if(!empty($this-> key) && !isset($_SESSION[$key]))
				$_SESSION[$key] = array();

		} elseif($this-> mode == 'cookie' && !is_null($age)) {
			$this-> age = $age;
		}
	}

	/**
	 * Value set overloader
	 * @param string $var
	 * @param mixed $val
	 * @throws Exception
	 */
	public function __set($var, $val)
	{
		switch($this-> mode) {
			case 'session':
				if(empty($this-> key))
					$_SESSION[$var] = $val;
				else
					$_SESSION[$this-> key][$var] = $val;
				break;
			case 'cookie':
				$_COOKIE[$var] = $val;
 				$json_cookie = str_replace(
 					array(",",";"," ","\t","\r","\n","\013","\014", "\""), // , "&", "\\", "/"
 					array("%2C","%3B","%20","%09","%0D","%0A","%0B","%0C", "%22"), // , "%38", "%92", "%2F"
 					json_encode($val)
 				);
				setrawcookie($var, $json_cookie, time() + $this-> age, '/');
				break;
		}
	}

	/**
	 * Value get overloader
	 * @param string $var
	 */
	public function &__get($var)
	{
		$r = null;
		switch($this-> mode) {
			case 'session':
				if(empty($this-> key)) {
					if(isset($_SESSION[$var]))
						return $_SESSION[$var];
				} else {
					if(isset($_SESSION[$this-> key][$var]))
						return $_SESSION[$this-> key][$var];
				}
				break;
			case 'cookie':
				// TODO: direct access to the cookies not working like this, must have different implementation
				if(isset($_COOKIE[$var])) {
					if(is_array($_COOKIE[$var]))
						$r = $_COOKIE[$var];
					else
						$r = @json_decode($_COOKIE[$var], true);
				}
				break;
		}
		return $r;
	}

	/**
	 * Value check overloader
	 * @param string $var
	 */
	public function __isset($var)
	{
		switch($this-> mode) {
			case 'session':
				if(empty($this-> key))
					return isset($_SESSION[$var]);
				else
					return isset($_SESSION[$this-> key][$var]);
			case 'cookie':
				return isset($_COOKIE[$var]);
		}
	}

	/**
	 * Value remove overloader
	 * @param string $var
	 */
	public function __unset($var)
	{
		switch($this-> mode) {
			case 'session':
				if(empty($this-> key))
					unset($_SESSION[$var]);
				else
					unset($_SESSION[$this-> key][$var]);
				break;
			case 'cookie':
				unset($_COOKIE[$var]);
				setrawcookie($var, null, time()-31536000, '/');
				break;
		}
	}
}