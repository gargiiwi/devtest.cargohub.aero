<?php
/**
 * Contains exchange rates for currencies - Accessible by anyone but can only be updated by admin (and automatically by the system)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2.8
 */
class App_Ctrl_Datasource_Exchange extends App_Ctrl_Base
{
	public static $_module_name = "Currency Exchange Rates";

	/**
	 * The contact model
	 *
	 * @var App_Model_Data_Money
	 */
	private $money;

	/**
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct(true);

		// Data model for loading currency exchange rates
		$this-> money = $this-> factory_Model('Data_Money', null, 'xe');
	}

	/**
	 * Index action for the Issues display and management page
	 *
	 * @param MVC_Request $in
	 */
	public function action_Index(MVC_Request $in)
	{
		$this-> CFG['components'] = array('modal');
		$this-> CFG['modules'][] = "datasource/_exchange";
		$this-> CFG['jqwidgets'][] = array("tabs", "expander");
		$this-> CFG['jqwidgets'][] = array("listbox", "dropdownlist", "combobox", "calendar", "datetimeinput");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete"); // Slices 4 and 5 - Grid and some grid plugins
		$this-> CFG['jqwidgets'][] = array("data.export", "grid.export"); // "grid.storage"
		$this-> CFG['xjs'] = array(
			'global' => true,
			'dateformat'=> true
		);

		// Automatic update for "live" data (for now, only USD source):
		// Check in secondary table if a record with no date exists (hist_d) is 0000-00-00 -> this means "live data". It's updated automatically if older than 1hr (max 24 updates/day)
		// If not found, live data is retrieved and the check record is created.

		// List of various source currencies that should be checked (picked at random) - All ar verified until at least one is found to be "old"
		$check_list = array("XDR", "USD", "EUR", "JPY", "GBP");
		shuffle($check_list);
		foreach($check_list as $source_test) {
			$found_cache = $this-> money-> check_Cache($source_test, null, 3600);
			if(!$found_cache) {
	 			$r= $this-> money-> update_Remotely_APILayerNet(null, $source_test);
	 			break; // When one was found, don't update others this cycle
			}
		}

		// prepare output
		$this-> prepare_Header(self::$_module_name);

		// Persistent grid state (session)
		if(isset(MVC_Core::$ss-> cache['grid_state']['currency_exchange']))
			$this-> CFG['datasource_exchange']['grid_state'] = MVC_Core::$ss-> cache['grid_state']['currency_exchange'];
		else {
			// Default grid state
			$this-> CFG['datasource_exchange']['grid_state'] = array(
				'pagenum'=> 0,
				'pagesize'=> 10,
				'sort'=> array(
					'column'=> "upd_ts",
					'direction'=> "desc"
				),
				'filters'=> null
			);
		}

		// Read list of currencies that the software knows (from the config file) - Used for the dropdown selectors
		$currency_data = MVC_Core::$spyc-> loadFile(ROOT .DS. 'app'. DS. 'data'. DS. 'currencies.yaml');
		$curr_list = array();
		foreach($currency_data as &$entry)
			$curr_list[] = $entry['iso'];
		$this-> CFG['datasource_exchange']['curr_list'] = $curr_list;

		$this-> module = array(
			'icon'=> "exchange.png",
			'icon_alt'=> "(XE)",
			'title'=> self::$_module_name,
			'description'=> "Rate on which monetary values can be transformed"
		);

		// which body template to use, and run the view
		$this-> site_section = "datasource". DS. "exchange.html";
		$this-> view("private.html");

	}

	/**
	 * Reads currency exchange rates and sends to the grid.
	 * Also does calculation by using the "filter" row
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Get_List(MVC_Request $rq)
	{
		$count_rows = $rq-> pagesize;
		$start_row = $rq-> pagenum * $rq-> pagesize;

		// Requested sort order?
		if($rq-> sortdatafield && $rq-> sortorder) {
			$ss_sort = "`data_xe`.{$rq-> sortdatafield} {$rq-> sortorder}";
		} else
			$ss_sort = "`data_xe`.upd_ts ASC";  // default sorting by update time, oldest first

		list($ss_filters, $filter_callback) = self::helper_Grid_Filters($rq);

		$rates = $this-> money-> get_List(
			/**
			 * Filtering function - Also includes security
			 * @param QueryFactory $q
			 * @uses:
			 * - $rq = Request Model
			 * - $ss_filters, $filter_callback = Necessary for grid filters
			 */
			function(QueryFactory &$q) use ($rq, $ss_filters, $filter_callback) {
				// When ISODATE is present in the request, add another filter, to show records with "hist_d". If not present, show the ones without a date (live data)
				if(!empty($rq-> isodate)) {
					$q-> where("`hist_d` = ?", $rq-> isodate);
				} else
					$q-> where("`hist_d` IS NULL");

				foreach($ss_filters as $fkey=> $filter_set) {
					if($fkey != 'calc')
					// Which table and database field is used for the filter column ? - "calc" field has no effect, since it's not a normal filter
						$filter_callback($q, $filter_set, "`{$fkey}`");
				}
			},
		null, $ss_sort, $rq-> pagesize, $rq-> pagenum * $rq-> pagesize, true); // true = CALC FOUND ROWS!

		if(!$rates)
			return $this-> send_JSON(array(
				"found_rows"=> 0,
				"data"=> array()
			));

		// The special "Calculation filter"
		if(isset($ss_filters['calc']))
			$calculate_source = (int)preg_replace("/([^0-9\\.])/i", "", $ss_filters['calc'][0]['v']);

		// a bit of processing on the values before sending the data
		$rows = array();
		foreach($rates as $row) {
			if(isset($calculate_source))
				$row['calc'] = (float)$row['xrate'] * $calculate_source;
			$rows[] = $row;
		}

		// Send the issue data to the jqxGrid
		$this-> send_JSON(array(
			"found_rows"=> $this-> money-> get_List_Save_FoundRows(),
			"data"=> $rows
		));
	}

	/**
	 * Updates exchange rate for a currency pair. Inverse rate is calculated automatically
	 * @param MVC_Request $post
	 */
	public function action_Update_XRate(MVC_Request $post)
	{
		if(!$this-> user-> is_Unrestricted())
			return header("HTTP/1.0 403 One does not simply edit exchange rates...");

		if(!is_numeric($post-> xrate))
			return header("HTTP/1.0 400 You have not entered a proper numeric exchange rate");

		$rate_test = (float)$post-> xrate;
		if($rate_test > 10000 || $rate_test < 0.0001)
			return header("HTTP/1.0 400 The exchange rate is too high or too low to be realistic. Please check your input");

		$update = array(
			'xrate'=> round($rate_test, 6),
			'xinv'=> round(1/$rate_test, 6),
			'update_mode'=> "manual"
		);

		$q=QueryFactory::update()
		-> table("data_xe")
		-> values($update)
		-> where("`from_iso` = ?", $post-> from)
		-> where("`to_iso` = ?", $post-> to)
		-> where("`hist_d` = ?", $post-> xdate)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return header("HTTP/1.0 500 There was an error while attempting to update exchange rate");

		$this-> send_JSON(array('success'=> true, 'response'=> "Exchange rate updated !"));
	}

	/**
	 * Verifies if currency exchange rates exist for a certain date, if not, they are created/replaced
	 * @param MVC_Request $post
	 */
	public function action_XChk(MVC_Request $post)
	{
		if(!$post-> isodate)
			return header("HTTP/1.0 400 Date not present in the request");

		// For historical dates, we're mostly interested in XDR to other currencies...
		// TODO: Implement the ability to read historical values for other currencies (USD, EUR, etc.)
		$found_cache = $this-> money-> check_Cache("XDR", $post-> isodate);
		if(!$found_cache) {
 			$r= $this-> money-> update_Remotely_APILayerNet($post-> isodate, "XDR");
 			if($r===false)
 				return header("HTTP/1.0 500 There was a problem accessing APILayer. eXchangeE rates not updated");
 			elseif(is_object($r))
 			// Error message ?
 				return header("HTTP/1.0 {$r-> code} APILayer response: {$r-> info}");
		}

		$this-> send_JSON(array('success'=> true, 'response'=> "Showing currencies from date: {$post-> isodate}"));
	}

}