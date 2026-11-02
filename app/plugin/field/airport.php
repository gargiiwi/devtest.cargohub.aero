<?php
/**
 * Special field plugin which stores airport information externally in a database table, and the field value is actually an index into the data
 * Loads the full table on first instance, so the proper context can be referenced by the numeric ID
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package Cargo ITS
 * @version 1.0.dev
 */
class App_Plugin_Field_Airport
{
	public $name = "Airport";

	private static $db_table = "data_airports";

	/**
	 * All the airport data loaded/cached into memory, so it can be searched quickly when displaying the field
	 * @var unknown_type
	 */
	public static $_data = null;

	/**
	 * Constructor can be used to preload airport data in certain cases (like when the plugin is accessed many times consecutively, for example for the list/grid)
	 * In all other cases ( when airport is being searched ), the plugin should NOT preload airport data !
	 * FIXME: This is not functional, the functions depend on self::$_data being filled with content
	 *
	 * @param bool $no_preload
	 * @throws Exception
	 */
	public function __construct($no_preload = null)
	{
		if($no_preload)
			return;

		if(is_null(self::$_data)) {
			$q=QueryFactory::select()
			-> table(self::$db_table)
			-> order('`id` ASC');

			$r = MVC_Core::$db-> query($q);

			if(!$r || count($r)==0) // not found
				throw new Exception("Impossible to load Airport data, check the database !");

			self::$_data = array();
			foreach($r as $row) {
				$id = array_shift($row);
				self::$_data[(int)$id] = $row;
			}
		}
	}

	/**
	 * Loads the exact data for an airport, by database ID
	 * @param int $id
	 */
	public function load_Data($id)
	{
		$q=QueryFactory::select()
		-> table(self::$db_table)
		-> where("`id` = ?", $id)
		-> limit(1);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Searches an airport in the database
	 *
	 * @param string $search Query text
	 * @param bool $ignore_iata
	 * @param int $limit Maximum number of results to return
	 * @return array Results
	 */
	public function search_Data($search, $ignore_iata = false, $limit = 15)
	{
		$q=QueryFactory::select()
		-> table(self::$db_table)
		-> order('`name` ASC')
		-> limit($limit); // maximum results, for now

		// When the search string is empty, return nothing.
		if(strlen($search) == 0)
			return false;
		// because of the specialized input type, if the query has three characters, it searches for IATA code
		elseif(strlen($search) == 3 && !$ignore_iata)
			$q-> where("`IATA` = ?", $search);
		// otherwise, it searches the name or country
		else
			$q	-> where("`name` LIKE ?", '%'.$search.'%')
				-> where_or("`country` LIKE ?", '%'.$search.'%');

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Field definition for the next field being processed
	 * @var array
	 */
	public $fdef;

	/**
	 * Indicates in which column in IXF this plugin stores it's data (value or int_value)
	 * @var string
	 */
	public $data_column = "int_value";

	/**
	 * Checks if the incoming data maps to one of the loaded airports, and converts the incoming $fdata (which should be a text) into
	 *
	 * @param mixed $fdata
	 * @param bool $allow_empty If True, it permits an empty value ( for the ability to clear the airport )
	 * @return string|boolean
	 */
	public function check(&$fdata, $allow_empty = true)
	{
		if(!$allow_empty && empty($fdata))
			return "No Airport specified";

		if(!empty($fdata) && !isset(self::$_data[$fdata]))
			return "Invalid Airport ID specified";

		return false;
	}

	public function get_Field_Store(&$fdata)
	{
		if(empty($fdata))
			return array();

		return array('int_value'=> $fdata);
	}

	public function get_Default()
	{
		$field_store = array(
			'value'=> $this-> fdef['o']['def']
		);
	}

	/**
	 * Used in the template rendering, this should match the pseudo-serialized version of the field output
	 *
	 * @param string $key
	 * @param string $primary_field_name
	 * @return array
	 */
	public function get_Serialized_Placeholders($key, $primary_field_name = null, $group = null)
	{
		if(!$primary_field_name)
			$primary_field_name = "Airport";

		$phs = array();
		$phs[] = array(
			'n'=> $primary_field_name.' / IATA',
			'group'=> $group,
			'ph'=> '{'.$key.'.iata}'
		);
		$phs[] = array(
			'n'=> $primary_field_name,
			'group'=> $group,
			'ph'=> '{'.$key.'.airport}'
		);
		$phs[] = array(
			'n'=> $primary_field_name.' / Country',
			'group'=> $group,
			'ph'=> '{'.$key.'.country}'
		);
		return $phs;
	}

	/**
	 * Decodes the stored special field into the usabled data
	 * @param array $xf Field data
	 * @param bool $serialize Serialize the field data (for the grid)
	 * @return array
	 */
	public function decode($xf, $serialize = false)
	{
		$col = $this-> data_column;

		if($serialize)
			// not really serialized, but this field is displayed into TWO columns, not one, so each one needs it's own string.
			return array(
				'airport'=> self::$_data[$xf[$col]]['IATA'].': '.self::$_data[$xf[$col]]['name'],
				'country'=> self::$_data[$xf[$col]]['country']
			);
		else
			return array(
				'key'=> (int)$xf[$col],
				'value'=> self::$_data[$xf[$col]]
			);
	}

	/**
	 * Sets up the form objects for this field type
	 * @param MVC_Form $form
	 * @param string $key
	 * @param array $xfields // all incoming form values
	 */
	public function setup_Form(MVC_Form &$form, $key, &$xfields)
	{
		$value = (is_array($xfields) && isset($xfields[$key]))? $xfields[$key]['value']: null;

		if(!is_null($value)) {
			$default = $xfields[$key]['key'];
			$default_text = $value['IATA'].': '.$value['name'].', '.$value['country'];
		} elseif(isset($this-> fdef['o']) && isset($this-> fdef['o']['def'])) {
			$default = (int)$this-> fdef['o']['def']; $value = self::$_data[$default];
			$default_text = $value['IATA'].': '.$value['name'].', '.$value['country'];
		} else {
			$default = null;
			$default_text = "";
		}

		$required = isset($this-> fdef['o']) && isset($this-> fdef['o']['req']) && ($this-> fdef['o']['req'] == true);

		$form-> add_Element("Scalar", $key, null, $default, null, 'xfd', array('label'=> $this-> fdef['n'], 'req'=> (bool)$required));
		$form-> add_Element("Scalar", $key.'_text', null, $default_text, null, 'xfd');
	}

	public function form_Compat($xfield, $key)
	{
		return $xfield['int_value']; // Airport ID is stored in int_value
	}

	/**
	 * Converts a field search value from the format it is displayed (and possibly searched for) to the format it is stored
	 * @param array &$filter_item
	 * @param bool $attached_country The search is coming from the attached country column, not from the main airport column
	 */
	public function prepare_Data_Search(&$filter_item, &$filter_info)
	{
		if(!empty($filter_info['att_data_search'])) {
			// searches for the exact country in the preloaded $_data
			$airport_ids = array();
			foreach(self::$_data as $k=> $row) {
				if($filter_item['v'] == $row['country'])
					$airport_ids[] = (int)$k; // found an airport in this country !
			}
		} else {
			// searches for the string inside the preloaded data.
			// If it has three characters, attempts to find in IATA code first, if not found in the airport name, for other character counts just finds the airport name
			if(strlen($filter_item['v'])==3) {
				foreach(self::$_data as $k=> $row) {
					if(strtolower($filter_item['v'])==strtolower($row['IATA'])) {
						$filter_item['v'] = (int)$k; // exact match ! (IATA codes are unique)
						$filter_item['c'] = 'EQUAL';
						return;
					}
				}
			}

			// not found as IATA code, try searching inside the airport name, and make a list of valid IDs for this search
			$airport_ids = array();
			foreach(self::$_data as $k=> $row) {
				if(stripos($row['name'], $filter_item['v'])!==false)
					$airport_ids[] = (int)$k;
			}

		}
		// return the list of found IDs
		if(count($airport_ids)>1) {
			$filter_item['v'] = $airport_ids;
			$filter_item['c'] = 'IN_SET';
		} else if(count($airport_ids)==1) {
			$filter_item['v'] = reset($airport_ids);
			$filter_item['c'] = 'EQUAL';
		} else
			$filter_item['v'] = null;
	}

}