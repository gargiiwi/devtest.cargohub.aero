<?php
/**
 * Class that handles request input filtering and pre-processing
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Framework
 * @version 2.7
 */
class MVC_Filter
{
	/**
	 * What kind of filter this is ?
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Optional filter configuration
	 * @var mixed
	 */
	private $cfg;

	public function __construct($filter_type, $filter_cfg = null)
	{
		if(method_exists($this, 'filter_'.$filter_type)) {
			$this-> type = $filter_type;
			$this-> cfg = $filter_cfg;
		}
	}

	/**
	 * Processes input according to the type of filter
	 *
	 * @param mixed $input_data
	 */
	public function process($input_data)
	{
		if(!empty($this-> type)) {
			// process input recursively if it's an array (apply the filters on all members)
			if(is_array($input_data)) {
				foreach($input_data as &$input_member)
					$input_member = $this-> process($input_member);
			} else
				return call_user_func(array($this, 'filter_'.$this-> type), trim($input_data));
		} else {
			// returns the data as is, and fire a debug notification that an unexisting filter type was called
			MVC_Core::$debug-> dump($this-> type, "Filter type doesn't exist", null, "warning");
			return $input_data;
		}
	}

	// Filtering methods. Those with Is in them require a certain type of format. Those with not just process the input before returning

	/**
	 * Checks if the Input is a number, and returns null if not
	 * @param mixed $input
	 * @return int|null
	 */
	private function filter_Is_Numeric($input)
	{
		return is_numeric($input)? $input: null;
	}

	/**
	 * Checks if the Input contains letters only and no punctuation, returns null if not. Also converts to lowercase
	 * @param mixed $input
	 * @return string|null
	 */
	private function filter_Is_TextID($input)
	{
		$input = strtolower($input);
		return preg_match("/^[a-z]*$/", $input)? $input: null;
	}

	/**
	 * Checks if the input is a valid Tracking ID, in the format "AAA-AAA-AAAA", where A might be [A-Z0-9]
	 * @param mixed $input
	 * @return string|null
	 */
	private function filter_Is_TrackID($input)
	{
		$input = strtoupper($input);
		return preg_match("/^[A-Z0-9]{3}-[A-Z0-9]{3}-[A-Z0-9]{4}$/", $input)? $input: null;
	}

	/**
	 * Checks if the input is a correctly format E-mail
	 * @param unknown $input
	 */
	private function filter_Is_Email($input)
	{
		// Some patterns ?
//		'/^(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){255,})(?!(?:(?:\\x22?\\x5C[\\x00-\\x7E]\\x22?)|(?:\\x22?[^\\x5C\\x22]\\x22?)){65,}@)(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22))(?:\\.(?:(?:[\\x21\\x23-\\x27\\x2A\\x2B\\x2D\\x2F-\\x39\\x3D\\x3F\\x5E-\\x7E]+)|(?:\\x22(?:[\\x01-\\x08\\x0B\\x0C\\x0E-\\x1F\\x21\\x23-\\x5B\\x5D-\\x7F]|(?:\\x5C[\\x00-\\x7F]))*\\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-+[a-z0-9]+)*\\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-+[a-z0-9]+)*)|(?:\\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\\]))$/iD'
//		'/(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,6}))/'
		return filter_var($input, FILTER_VALIDATE_EMAIL);
	}

	/**
	 * Transforms the text "true", "yes", "1" or "false", "no", "0" into php boolean true/false
	 * @param string $input
	 * @return boolean|null
	 */
	private function filter_Boolean($input)
	{
		if($input == 'true' || $input == '1' || stripos($input, 'yes')===0)
			return true;
		if($input == 'false' || $input == '0' || stripos($input, 'no')===0)
			return false;
		// If not recognized, null (not available)
		return null;
	}

	/**
	 * Decodes an input as a CSV (comma separated values) and returns the array
	 * @param string $input
	 * @return array
	 */
	private function filter_CSV($input)
	{
		if(strlen($input)==0)
			return array();
		return str_getcsv($input);
	}

	/**
	 * Decodes an input as a list of IDs separated by comma (CSV, but numbers only). Every element is made sure to be Int
	 * @param string $input
	 * @return array
	 */
	private function filter_IDList($input)
	{
		if(strlen($input)==0)
			return array();
		$idlist = explode(',', $input);
		foreach($idlist as &$el)
			$el = (int)$el;
		return $idlist;
	}

	/**
	 * Tries to parse the input as a date/time
	 * @param string $input
	 * @return int|null
	 */
	private function filter_UnixTime($input)
	{
		$unixtime = strtotime($input);
		return $unixtime!==false? $unixtime: null;
	}

	/**
	 * Input must consist of 11 digits, with or without other characters between them
	 * @param string $input
	 * @return array Returns an array of a prefix (first 3 digits) and a serial (8 digits)
	 */
	private function filter_MAWB($input)
	{
		$mawb = preg_replace('/\D+/', '', $input);
		if(strlen($mawb) != 11)
			return null;
		return array(
			'p'=> substr($mawb, 0, 3),
			's'=> substr($mawb, 3, 8)
		);
	}
}