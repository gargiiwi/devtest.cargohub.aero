<?php
/**
 * MVC Controller base class, used to manage the different sections of the application ( aka Controllers )
 *
 * @author Highstrike (highstrike@gmail.com)
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoITS
 * @version 1.0.dev
 */
abstract class MVC_Ctrl
{
	/**
	 * The output data (which might be used as variables for a template system, or encoded as JSON for an AJAX request)
	 * @var array
	 */
	protected $_out = array();

	/**
	 * Models used by the instanced controller
	 * @var array[MVC_Model]
	 */
	protected $m = array();

// ===== System methods, should not be called from inside the controller ===== //

	/**
	 * Creates a model object and attaches it to itself so it can be used in the various actions
	 *
	 * @param string $name
	 * @param string $key Key to retrieve the model later using __get()
	 * @param array $constructor_params
	 * @return MVC_Model
	 */
	public function &factory_Model($name, $key = null, $constructor_params = null)
	{
		if(is_null($key))
			$key = strtolower($name);
		$class = 'App_Model_'. ucfirst(strtolower($name));
		$this-> m[$key] = new $class($constructor_params);
		return $this-> m[$key];
	}

	/**
	 * Quick setting of output content
	 *
	 * @param string $var
	 * @param mixed $value
	 */
	public function __set($var, $value)
	{
		$this->_out[$var] = $value;
	}

	/**
	 * Removes an element from the output data
	 *
	 * @param string $var
	 */
	public function __unset($var)
	{
		unset($this->_out[$var]);
	}

	/**
	 * Quick access to the connected models
	 *
	 * @param string $var
	 */
	public function &__get($var)
	{
		if(isset($this-> m[$var]))
			return $this-> m[$var];
		throw new Exception("Attempting to use a model reference which does not exist: {$var}");
	}

	/**
	 * Checks to see if a model has been defined
	 *
	 * @param string $var
	 */
	public function __isset($var)
	{
		return isset($this-> m[$var]);
	}

	/**
	 * Simple helper that shows debug for "php"-based debugging ( Other debugger drivers don't need it )
	 */
	public function show_Debug()
	{
		if(MVC_Core::$settings['core']['debug']['level']!='DISABLED' && MVC_Core::$settings['core']['debug']['mode']=='php')
			$this-> debug = MVC_Core::$debug-> render_Buffer();
	}

	/**
	 * JSON response (used for AJAX requests)
	 *
	 * @param mixed $data What to encode and return as json
	 * @param bool $do_not_encode Sends the data as a string (assuming it's already JSON), don't attempt to encode it
	 */
	public function send_JSON($data, $do_not_encode = false)
	{
		// Content is of JSON type, so jQuery knows to decode it automatically. Also, such content is not cached
		header('Content-type: application/json');
		echo $do_not_encode? $data: json_encode($data);
		return true;
	}

	/**
	 * Embed JSON in a html response (used to send a mixed HTML + JSON data in an AJAX request)
	 * -- The JS frontend knows how to pick this and transform it into usable variables
	 * -- Must be sent BEFORE any other HTML, just like any other HTTP header !
	 * -- The Meta header contains the number of JSON strings sent and their length (to check if headers have been transmitted correctly)
	 *
	 * @param mixed $data What to encode and send as header
	 * @param string $key When $data is something which is not an array, use this as the variable name
	 * @param bool $do_not_encode Sends the data as a string (assuming it's already JSON), don't attempt to encode it
	 */
	public function send_JSON_Headers($data, $key = null, $do_not_encode = false)
	{
	 
		if($do_not_encode) {
			if(!is_string($data))
				return false; // error
			// send just one header (+ the metadata header), without encoding it - If $data is not proper JSON, the JS side will fail to decode...
			header('X-JSON-Meta: {"count":1,"lengths":['.strlen($data).'],"keys":["'.$key.'"]}');
			header('X-JSON-0: '.$data);
		} else {
			// send out multiple headers, one for each entry in the $data array - Since PHP Arrays are like JavaScript Objects (values can have named keys), also send the keys in the Meta header
			if(is_array($data)) {
				$encoded_data = array(); $lengths = array(); $keys = array();
				foreach($data as $name=> $data_row) {
				 
					$encoded_row = json_encode(($data_row));
					$encoded_data[] = $encoded_row;
					$lengths[] = strlen($encoded_row);
					$keys[] = $name;
				} 
				header(sprintf('X-JSON-Meta: {"count":%d,"lengths":[%s],"keys":%s}', count($data), implode(',', $lengths), json_encode($keys)));
				foreach($encoded_data as $idx=> $row) 
					{
					header(sprintf('X-JSON-%d: %s', $idx, $row)); 
				 
					 }
					 
			} else {
				// not an array, assume we're dealing with an object or scalar value, send just one header
				$encoded_data = json_encode($data);
				header('X-JSON-Meta: {"count":1,"lengths":['.strlen($encoded_data).'],"keys":["'.$key.'"]}');
				header('X-JSON-0: '.$encoded_data);
			}
		}
	}
	 

}