<?php
/**
 * This is a plugin that prepares a CMS page to use the global airline search
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package Cargo ITS
 * @version 2.5
 */
class App_Plugin_CMS_AirlineSearch implements App_Plugin, App_Plugin_CMS
{
	/**
	 * Name of the database table containing the airline information
	 * @var string
	 */
	private static $airline_table = "data_airlines";

	/**
	 * Name of the database table containing country information (used in several places)
	 * @var string
	 */
	private static $country_table = "data_countries";

	/**
	 * Reference to the DryTpl object used by the CMS module, also used for generation output in the render_Output() method
	 *
	 * @var Drytemplate
	 */
	private $drytpl;

	/**
	 * Key under this plugin instance is loaded into the CMS
	 * -- (not used yet)
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Constructor Initializes PHP side to access the data of the Airline search box
	 *
	 * @param mixed $params
	 */
	function __construct($params)
	{
		if(is_array($params))
			$this-> drytpl = &$params[0];
	}

	/**
	 * Returns the plugin's configuration
	 *
	 * @return array
	 */
	public function get_Config()
	{
		// Plugin name
		$cfg = array(
			'type'=> "airlinesearch",
			'name'=> "Airline Search"
		);

		// Read all available issue types, needed for javascript for the issuetype submit selector
		$m_issue = new App_Model_Issue();
		$r = $m_issue-> ist_Get_List();

		$all_ist = array();
		if($r && count($r)>0) {
			$r-> index_field = "id";
			foreach($r as $id=> $row)
				$all_ist[$id] = $row;
		}

		// JavaScript related configuration
		$cfg['javascript'] = array(
			'jqwidgets'=> array("scrollbar", "listbox", "combobox"),
			'config'=> array(
				'width'=> 500,
				'all_ist'=> $all_ist
			)//,
// 			'xjs'=> array(
// 				'jqform'=> true
// 			)
		);

		return $cfg;
	}

	/**
	 * Renders the HTML output that will display the box
	 *
	 * @param string $param_string
	 */
	public function render_Output($param_string = null)
	{
		// $param_string not used (YET)
		return $this-> drytpl-> render_File("_plugins". DS. "airlinesearch.html");
	}

	/**
	 * JSON response (used for AJAX requests)
	 * -- TODO: Move this somewhere in the core where it can be used by anything
	 *
	 * @param mixed $data What to encode and return as json
	 * @param bool $do_not_encode Sends the data as a string (assuming it's already JSON), don't attempt to encode it
	 */
	protected static function send_JSON($data, $do_not_encode = false)
	{
		// Content is of JSON type, so jQuery knows to decode it automatically. Also, such content is not cached
		header('Content-type: application/json');
		echo $do_not_encode? $data: json_encode($data);
	}

	/**
	 * All AJAX request addressed to this plugin are redirected here, and various actions are performed depending on the parameters
	 */
	public function op_Hub(MVC_Request $in)
	{
		if(method_exists($this, 'op_'.$in-> op)) {
			call_user_func(array($this, 'op_'.$in-> op), $in);
		} else {
			$msg = sprintf("Operation \"%s\" unavailable in plugin: %s", $in-> op, get_class($this));

			self::send_JSON(array(
				'success'=> false,
				'reason'=> $msg
			));
		}
	}

	/**
	 * Searches in the "data_airline" table by using various criteria, with an incoming query string
	 * @param string $q
	 * @param int $limit How many results
	 * @return MVC_Database_Result
	 */
	private function search_Airline($search, $limit = 10)
	{
		$q=QueryFactory::select()
		-> table(array(self::$airline_table, 'airline'))
		// TODO: Convert countries in the airlines table into their ISO3166 when the "countries" table is complete
		-> table(array(self::$country_table, 'cty'), false, 'left', '`airline`.`country` = `cty`.`name_english`')
		-> order('`name` ASC')
		-> limit($limit); // maximum results, for now

		// Limit search results depending if we're logged in (as admin) and the status of the airline entry ("disabled" status cannot be controlled through the interface, only by editing the database)
		if(isset(MVC_Core::$ss-> user['is_admin'])) {
			$q-> where("`airline`.`status` != 'disabled'");
		} else {
			$q-> where("`airline`.`status` = 'registered' OR `airline`.`status` = 'inactive' OR `airline`.`status` = 'nosubmit'");
		}

		// When the search string is empty, return nothing.
		if(strlen($search) == 0)
			return false;
		// because of the specialized input type, if the query has two characters, it searches for IATA code
		elseif(strlen($search) == 2)
		$q-> where("`IATA` = ?", $search);
		// if it has three characters, searches by either ICAO code or by AWB prefix ( numbers or letters )
		elseif(strlen($search) == 3) {
			if(is_numeric($search))
				$q-> where("`AWB_prefix` = ?", $search);
			else
				$q-> where("`ICAO` = ? OR `name` LIKE ?", $search, $search."%");
			// otherwise, it searches the name or country
		} else {
			// if first three characters are numeric, this is most likely an AWB number
			if(is_numeric(substr($search, 0, 3)))
				$q-> where("`AWB_prefix` = ?", substr($search, 0, 3));
			else {
				// finally, if nothing else works, plain old search
				// ... but first, cut everything after the | character, and any spaces before and after
				if(($pipep=strpos($search, '|'))!==false) {
					$search = substr($search, 0, $pipep-1);
				}
				$q	-> where("`name` LIKE ? OR `airline`.`country` LIKE ? OR `cty`.`name_national` LIKE ?", '%'.$search.'%', $search.'%', $search.'%');
			}
		}

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Returns the data for the airline suggestion combobox
	 */
	protected function op_Suggest_Airline()
	{
		$in = new MVC_Request();
		$q = $in-> q;

		if(strlen($q)<2) // Bugged Combobox, does initial request even if not asked for ...
			return self::send_JSON(array(
				array('id'=> 0, 'text'=> "Please type two or more characters to initiate search"),
			));

		if(substr($q, 0, 12)=="No results: ")
			$q = substr($q, 12);

		$r = $this-> search_Airline($in-> q);

		if($r && count($r)>0) {

			$all = array();
			foreach($r as $row) {
				$airline_text = $row['name'];
				if(!empty($row['AWB_prefix']) || !empty($row['IATA']) || !empty($row['ICAO']))
					$airline_text.=' |';
				if(!empty($row['AWB_prefix']))
					$airline_text.= ' '.$row['AWB_prefix'];
				if(!empty($row['AWB_prefix']) && (!empty($row['IATA']) || !empty($row['ICAO'])))
					$airline_text.= ' -';
				if(!empty($row['IATA']))
					$airline_text.= ' '.$row['IATA'];
				elseif(!empty($row['ICAO']))
				$airline_text.= ' '.$row['ICAO'];

				$all[] = array(
					'id'=> $row['id'],
					'text'=> $airline_text
				);
			}
			self::send_JSON($all);
		} else
			self::send_JSON(array(
				array('id'=> 0, 'text'=> "No results: ". $q)
			));
	}

	/**
	 * Reads information about an airline to be displayed in the search system
	 */
	protected function op_Get_Airline()
	{
		$in = new MVC_Request();

		if($in-> id) {
			$q=QueryFactory::select()
			-> table(array(self::$airline_table, 'airline'))
			// TODO: Convert countries in the airlines table into their ISO3166 when the "countries" table is complete
			-> table(array(self::$country_table, 'cty'), false, 'left', '`airline`.`country` = `cty`.`name_english`')
			-> table(array(MVC_Core::$db-> tab['ord'], 'ord'), QueryFactory::autoalias_Add_Prefix("org_id,public_settings", '_ord_'), 'left', '(`domain`)')
			-> where('`airline`.`id` = ?', $in-> id)
			-> limit(1);

			$r = MVC_Core::$db-> query($q);

			if($r && count($r)==1) {
				// found airline, return data
				$r-> split_by_prefixes = array(
					// extra tables
					'ord'=> "_ord_"
				);

				$airline = $r-> current();

				// decode the JSON from the associated domain's public_settings
				if(!is_null($airline['ord']['public_settings'])) {
					// extract IST list settings, only ones that are needed here (what kind of stuff can be posted by visitors for this airline)
					$public_settings = @json_decode($airline['ord']['public_settings'], true);
					$airline['ord']['ist'] = $public_settings['ist'];
					unset($airline['ord']['public_settings']);
				}

				// fullname ( containing IATA, ICAO )
				$airline['fullname'] = $airline['name'];
				if(!empty($airline['AWB_prefix']) || !empty($airline['IATA']) || !empty($airline['ICAO']))
					$airline['fullname'].=' |';
				if(!empty($airline['AWB_prefix']))
					$airline['fullname'].= ' '.$airline['AWB_prefix'];
				if(!empty($airline['AWB_prefix']) && (!empty($airline['IATA']) || !empty($airline['ICAO'])))
					$airline['fullname'].= ' -';
				if(!empty($airline['IATA']))
					$airline['fullname'].= ' '.$airline['IATA'];
				elseif(!empty($airline['ICAO']))
				$airline['fullname'].= ' '.$airline['ICAO'];

				// logo or no logo ?
				//print_r( $airline);
				if(empty($airline['logo_image']))
					$airline['logo_image'] = 'nologo.png';

				// ... and send the data to the frontend
				self::send_JSON(array(
					'success'=> true,
					'airline'=> $airline
				));
				return;
			}
		} else {
			self::send_JSON(array(
				'success'=> false,
				'reason'=> "Unable to display details about selected airline"
			));
		}
	}

}
