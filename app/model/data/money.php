<?php
/**
 * Specialized "Data" object related to money storage and calculation
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage DataModels
 * @version 2.8
 */
class App_Model_Data_Money extends App_Model_Data
{
	// "b5bcf32985475342127cd3a6ab761bad" // Anirban key - Use on live, fuck him.
	// "7caf9e44e594e7ccdf37cfb116761fa1" // Wavetrex key - Use on dev, for testing purposes
	// "468b1162fb1202967d179dccc5cd0598" // Raoul key - "Basic"
	// TODO: Store this in config file, like database passwords and such, and NOT in the code ( as it can be accessed through SVN )
	protected static $apilayer_key = "468b1162fb1202967d179dccc5cd0598";

	/**
	 * List of all currencies known to the system. Loaded from a YAML file on first use.
	 * @var array
	 */
	protected static $_currencies = array();

	/**
	 * All currencies, indexed by the ISO code (necessary for some search operations/filters)
	 * @var array
	 */
	private static $_currency_iso_index = array();

	/**
	 * Secondary table used to check if rates from a certain date exist in the cache
	 * @var string
	 */
	protected static $cachecheck = "data_xechk";

	/**
	 * Load exchange rates at first instance
	 */
	public function __construct()
	{
		parent::__construct('xe');

		if(empty(self::$_currencies)) {
			$currency_data = MVC_Core::$spyc-> loadFile(ROOT .DS. 'app'. DS. 'data'. DS. 'currencies.yaml');
			foreach($currency_data as &$entry) {
				self::$_currencies[(int)$entry['curr']] = &$entry;
				self::$_currency_iso_index[$entry['iso']] = &$entry;
			}
		}
	}

	/**
	 * Loads the list of currencies for the currency filter (2nd column)
	 */
	public function get_Currencies_ISO_List()
	{
		return array_keys(self::$_currency_iso_index);
	}

	/**
	 * Calculates currency conversion from the $source field of type "money" into the $target
	 * - If target "value" is empty, it's set with the calculated value
	 * - If not empty, it's left intact
	 * @param array $source Array of two values which must exist: "int_value" => One of the accepted currencies; "value" => Amount as float
	 * @param array $target Array of two values: "int_value" => Target currency, used as the base for calculation
	 * @param string $isodate Date for which currency value is calculated
	 */
	public function currency_Convert($source, &$target, $isodate = null)
	{
		if(!is_array($source) || !is_array($target) || empty($source['int_value']) || empty($target['int_value']))
			return false;

		// Same currency ? nothing to convert, just copy the value
		if($target['int_value'] == $source['int_value']) {
			$target['value'] = $source['value'];
			return 1;
		}

		// Find the $source and $target ISO codes
		$source_iso = self::$_currencies[$source['int_value']]['iso'];
		$target_iso = self::$_currencies[$target['int_value']]['iso'];

		// If $target_iso is "USD", swap source and target and use xinv
		// This is done to allow updating APILayer on free mode. It will dissapear when source can be selected
// 		if($target_iso == "USD" && $source_iso != "USD") {
// 			$_swap = $source_iso;
// 			$source_iso = $target_iso;
// 			$target_iso = $_swap;
// 			$_swap = true;
// 		}

		// With the payed account, this should work for any currency that is being calculated (but it's usually XDR)
		$found_cache = $this-> check_Cache($source_iso, $isodate);
		// Attempt to update exchange rates before doing the conversion
		if(!$found_cache) {
 			$r= $this-> update_Remotely_APILayerNet($isodate, $source_iso);
 			if($r===false)
 				MVC_Core::$debug-> dump("There was a problem accessing APILayer. eXchangeE rates not updated", __METHOD__, null, 'warning');
 			elseif(is_object($r))
 			// Error message ?
 				MVC_Core::$debug-> dump("{$r-> code} APILayer response: {$r-> info}", __METHOD__, null, 'warning');
		}

		// Read the exchange rate for $source and $target, and the specified $isodate if present. If not, 0000-00-00 is used, which means the "live" rate (update more often).
		if(empty($isodate))
			$isodate = '0000-00-00';
		$q=QueryFactory::select()
		-> table("data_xe", array('xrate', 'xinv'))
		-> where("`from_iso` = ?", $source_iso)
		-> where("`to_iso` = ?", $target_iso)
		-> where("`hist_d` = ?", $isodate);

		$xe = MVC_Core::$db-> query($q)-> current();
		if(empty($xe))
			return false; // Row not found - This exchange rate doesn't exist

		// Found a match ! Calculate
// 		if(isset($_swap))
// 			$target['value'] = (float)$source['value'] * $xe['xinv'];
// 		else
			$target['value'] = round((float)$source['value'] * $xe['xrate'], 3);
		return $xe['xrate'];
	}

	/**
	 * Verifies if exchange rates exist for a certain currency
	 * - Live ones are updated every hour (or more often), when $isodate is null, and historic rates only once.
	 *
	 * @param string $isocurr ISO 4217 currency (AAA) https://en.wikipedia.org/wiki/ISO_4217
	 * @param string $isodate ISO 8601 date (yyyy-MM-dd) https://en.wikipedia.org/wiki/ISO_8601
	 * @param int $cachelife For "Today" ($isodate = null), how old can the record be before it's considered outdated)
	 * @param bool True if the record exists (or for live, if it's fresh enough), False if it doesn't exist or is too old. Needs updating/create
	 */
	public function check_Cache($isocurr, $isodate = null, $cachelife = 3600)
	{
		$q=QueryFactory::select()
		-> table(self::$cachecheck)
		-> where('`source_iso` = ', $isocurr);

		if(empty($isodate))
			$q-> where("`hist_d` IS NULL");
		else
			$q-> where("`hist_d` = ?", $isodate);

		$ret = MVC_Core::$db-> query($q)-> current();
		if(!$ret)
			return false; // Not found in cache

		// Found it!
		if(empty($isodate)) {
			// Live data needs to be updated if "retrieved" is older than $cachelife
			$retrieved = strtotime($ret['retrieved']);
			if($retrieved > time()- $cachelife)
				return true; // Found and recent enough
			else
				return false; // Not recent enough, needs updating !
		} else
			return true; // Found for date. Nothing else to do here
	}

	/**
	 * Internally updates the history cache verification record, used by check_Cache() method
	 * @param string $isocurr
	 * @param string $isodate
	 */
	protected function update_Cache($isocurr, $isodate = null)
	{
		if(empty($isodate))
			$isodate = "0000-00-00";

		$q=QueryFactory::replace()
		-> table(self::$cachecheck)
		-> values(array(
			'source_iso'=> $isocurr,
			'hist_d'=> $isodate
		));

		MVC_Core::$db-> query($q);
	}

	/**
	 * Runs a remote-access request to http://apilayer.net/api/live to grab all currency rates from USD to others . Updates all rows containing USD as source or target ( 1/rate )
	 * @param $isodate Date for which the rates should be updated (historic). If missing it means "live data"
	 * @param $isocurr Which currency to update (Free accounts only support USD)
	 */
	public function update_Remotely_APILayerNet($isodate = null, $isocurr = "USD")
	{
		$endpoints = array_keys(self::$_currency_iso_index);
		// Find and remove $isocurr from the $endpoints, no need to convert one currency to itself !
		$endpoints = array_diff($endpoints, array($isocurr));

		$query_data = array(
			'access_key'=> self::$apilayer_key,
			'source'=> $isocurr,
			'currencies'=> implode(',', $endpoints)
		);

		if(empty($isodate))
			// Update live when no date is present
			$request_uri = "http://apilayer.net/api/live?". http_build_query($query_data);
		else {
			$query_data['date'] = $isodate;
			$request_uri = "http://apilayer.net/api/historical?". http_build_query($query_data);
		}

		$response_json = file_get_contents($request_uri);
		$response = @json_decode($response_json, true);
		if(empty($response))
			return false;

		if(!$response['success']) {
			$r = new stdClass();
			$r-> code = $response['error']['code'];
			$r-> info = $response['error']['info'];
			return $r;
		}

		// All good, read the data and start calculating
		// First, calculate all inverse rates (XYZ to USD)
		$quotes = $response['quotes'];
		$inverse = array();
		foreach($quotes as $quote=> $xrate)
			$inverse[substr($quote, 3, 3).substr($quote, 0, 3)] = 1/$xrate;
		$quotes = array_merge($quotes, $inverse);

		// Replace existing rates
		foreach($quotes as $quote=> $xrate) {
			$ins = array(
				'from_iso'=> substr($quote, 0, 3),
				'to_iso'=> substr($quote, 3, 3),
				'xrate'=> round($xrate, 6),
				'xinv'=> round(1/$xrate, 6),
				'update_mode'=> "apilayer"
			);
			if(!empty($isodate))
				$ins['hist_d'] = $isodate;
			else
				$ins['hist_d'] = "0000-00-00"; // Means "no date"

			// Find the currency codes in self::$_currency_iso_index and append them to the array
			$ins['from_code'] = self::$_currency_iso_index[$ins['from_iso']]['curr'];
			$ins['to_code'] = self::$_currency_iso_index[$ins['to_iso']]['curr'];

			$this-> create($ins, true); // Use Replace
		}

		// Update cache record
		$this-> update_Cache($isocurr, $isodate);
		return true; // Data was retrieved, currency cache updated
	}
}