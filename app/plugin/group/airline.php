<?php
/**
 * A plugin used to process the group data of type "airline"
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package Cargo ITS
 * @version 1.0.dev
 */
class App_Plugin_Group_Airline implements App_Plugin, App_Plugin_Group
{
	public $name = "Airline";
	public $name_plural = "Airlines";

	private static $data_table = "data_airlines";

	/**
	 * Returns the plugin's configuration
	 *
	 * @return array
	 */
	public function get_Config()
	{
		$cfg = array(
			'type'=> "airline",
			'name'=> $this-> name,
			'name_plural'=> $this-> name_plural,
		);

		return $cfg;
	}

	/**
	 * Returns the plugin's configuration for the editor.
	 * This is only used when editing the group from the interface
	 *
	 * @param string $editor_module Name of the editor module
	 * @return array
	 */
	public function get_Editor_Config($editor_module)
	{
		$cfg = array(
			'group'=> $this-> get_Config(),
			// JavaScript related configuration
			'javascript'=> array(
				'jqwidgets'=> array('scrollbar', 'listbox', 'combobox'), // Extra jqx modules which are needed for this group type
				'xjs'=> array(
					'jqform'=> true
				),
				'modules'=> array(
					$editor_module=> array()
				)
			)
		);
		$cfg['javascript']['modules'][$editor_module]['datafields'] = array(
			// group general
			array('name'=> "id", 'type'=> "int"),
			array('name'=> "name"),
			array('name'=> "count_elements", 'type'=> "int"),
			// airline specific
			array('name'=> "airline_id", 'type'=> "int", 'map'=> 'airline>id'),
			array('name'=> "awbp", 'type'=> "string", 'map'=> 'airline>AWB_prefix'),
			array('name'=> "iata", 'type'=> "string", 'map'=> 'airline>IATA'),
			array('name'=> "icao", 'type'=> "string", 'map'=> 'airline>ICAO'),
			array('name'=> "country", 'type'=> "string", 'map'=> 'airline>country'),
			array('name'=> "domain", 'type'=> "string", 'map'=> 'airline>domain'),
			array('name'=> "master", 'map'=> 'xdata>master'),
			array('name'=> "master_id", 'type'=> 'int', 'map'=> 'xdata>master_id'),
			array('name'=> "fullname", 'map'=> 'airline>fullname'),
			array('name'=> "submiton", 'type'=> "bool", 'map'=> 'airline>submiton')
		);
		$cfg['javascript']['modules'][$editor_module]['columns'] = array(
			array('text'=> "Airline", 'dataField'=> "name", 'width'=> 240),
			array('text'=> "Issues", 'dataField'=> "count_elements", 'width'=> 60, 'align'=> "center", 'cellsalign'=> "center", 'filtertype'=> "number"),
			array('text'=> "AWBp", 'dataField'=> "awbp", 'width'=> 50, 'align'=> "center", 'cellsalign'=> "center"),
			array('text'=> "IATA", 'dataField'=> "iata", 'width'=> 40, 'align'=> "center", 'cellsalign'=> "center"),
			array('text'=> "ICAO", 'dataField'=> "icao", 'width'=> 50, 'align'=> "center", 'cellsalign'=> "center"),
			array('text'=> "Country", 'dataField'=> "country", 'width'=> 140, 'filtertype'=> "checkedlist"),
			array('text'=> "Master Handler", 'dataField'=> "master"),
			array('text'=> "Redirect Domain", 'dataField'=> "domain"),
			array('text'=> "Submit on?", 'dataField'=> "submiton", 'width'=> 80, 'align'=> "center", 'columntype'=> "checkbox", 'filtertype'=> "bool")
		);

		return $cfg;
	}

	/**
	 * Optionally appends one or more foreign tables to the group table ( for when the group contains complex data )
	 * @param QueryFactory $q
	 */
	public function attach_Foreign_Data(QueryFactory &$q)
	{
		$q-> table(array(self::$data_table, 'air'), QueryFactory::autoalias_Add_Prefix("id,name,IATA,ICAO,AWB_prefix,country,domain,logo_image,status", '_air_'), 'left', '`grp`.`id` = `air`.`group_id`');
	}

	/**
	 * Plugin controlled split-by-prefixes, when using Foreign Data
	 * @param MVC_Database_Result $r
	 */
	public function split_Prefixes(MVC_Database_Result &$r)
	{
		$r-> split_by_prefixes = array(
			// extra tables
			'airline'=> "_air_"
		);
	}

	/**
	 * Performs Post-Processing on Airline data (Decode xdata, transform some values)
	 * @param array $group
	 */
	public function group_Post_Process(&$group)
	{
		// Airline combined name ( Name | AWB - IATA/ICAO )
		$airline_text = $group['airline']['name'];
		if(!empty($group['airline']['AWB_prefix']) || !empty($group['airline']['IATA']) || !empty($group['airline']['ICAO']))
			$airline_text.=' |';
		if(!empty($group['airline']['AWB_prefix']))
			$airline_text.= ' '.$group['airline']['AWB_prefix'];
		if(!empty($group['airline']['AWB_prefix']) && (!empty($group['airline']['IATA']) || !empty($group['airline']['ICAO'])))
			$airline_text.= ' -';
		if(!empty($group['airline']['IATA']))
			$airline_text.= ' '.$group['airline']['IATA'];
		elseif(!empty($group['airline']['ICAO']))
		$airline_text.= ' '.$group['airline']['ICAO'];

		$group['airline']['id'] = (int)$group['airline']['id'];
		$group['airline']['fullname'] = $airline_text;
		$group['airline']['submiton'] = $group['airline']['status'] == 'registered';

		// Extra info
		$group['xdata']['fullname'] = $group['airline']['fullname'];
		$group['xdata']['search_id'] = $group['airline']['AWB_prefix'];
	}

	/**
	 * Updates Airline data_table with it's relation to groups
	 * @param int $group_id
	 * @param array $group_data
	 * @param bool $update True when this is an update, False when it's a create
	 *   (in this case however it's ignored, since the Airline foreign data always exists, just the connection changes)
	 */
	public function save_Foreign_Data($group_id, $group_data, $update)
	{
		if(!isset($group_data['airline']))
			return true; // nothing to update

		// Post-Processing of attached Airline data
		$airline_data = $group_data['airline'];
		$airline_id = $airline_data['id']; unset($airline_data['id']);
		$airline_data['group_id'] = $group_id;
		$airline_data['status'] = (isset($airline_data['submiton']) && $airline_data['submiton'] == 'false')? 'nosubmit': 'registered'; unset($airline_data['submiton']);

		$q=QueryFactory::update()
		-> table(array(self::$data_table, 'air'))
		-> where("`id` = ?", $airline_id)
		-> values($airline_data);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Updates airline data by removing references to the group that doesn't exist anymore
	 * @see App_Plugin_Group::delete_Foreign_Data()
	 */
	public function delete_Foreign_Data($group_id)
	{
		$airline_data = array(
			'group_id'=> null,
			'domain'=> null,
			'status'=> "inactive"
		);

		$q=QueryFactory::update()
		-> table(array(self::$data_table, 'air'))
		-> where("`group_id` = ?", $group_id)
		-> values($airline_data);

		return MVC_Core::$db-> query($q);
	}

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
				$q-> where("`ICAO` = ?", $search);
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

	public function custom_suggest_airline(MVC_Request $rq)
	{
		$q = $rq-> q;

		if(strlen($q)<2) // Bugged Combobox, does initial request even if not asked for ...
			return array(
				array('id'=> 0, 'text'=> "Please type two or more characters to initiate search"),
			);

		if(substr($q, 0, 12)=="No results: ")
			$q = substr($q, 12);

		$r = $this-> search_Airline($rq-> q);

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
			return $all;
		} else
			return array(
				array('id'=> 0, 'text'=> "No results: ". $q)
			);
	}

	public function custom_get_airline(MVC_Request $rq)
	{
		if(!$rq-> id)
			return false;

		$q=QueryFactory::select()
		-> table(array(self::$airline_table, 'airline'))
		// TODO: Convert countries in the airlines table into their ISO3166 when the "countries" table is complete
		-> table(array(self::$country_table, 'cty'), false, 'left', '`airline`.`country` = `cty`.`name_english`')
		-> table(array(MVC_Core::$db-> tab['ord'], 'ord'), QueryFactory::autoalias_Add_Prefix("org_id,public_settings", '_ord_'), 'left', '(`domain`)')
		-> where('`airline`.`id` = ?', $rq-> id)
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
			if(empty($airline['logo_image']))
				$airline['logo_image'] = 'nologo.png';

			// ... and send the data to the frontend
			return $airline;
		}
	}

}