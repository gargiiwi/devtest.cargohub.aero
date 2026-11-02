<?php
/**
 * General "Data" tables model, used to access any data_* tables.
 * -- Has no knowledge of the internal organization of those tables, so the CRUD is very basic
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage DataModels
 * @version 2019.06.17
 */
class App_Model_Data extends MVC_Model
{
	/**
	 * Accepted data types (for which there is code to handle the data operations)
	 * -- If a new type is implemented, add it here
	 * @var array[string]
	 */
	public static $accepted_types = array(
		'xe',		// Currency list
		'airlines',	// Airline name
		'airports',	// World airport
		'countries'	// World countries
	);

	/**
	 * The table that is accessed by the instance of the model (without the data_ prefix)
	 * @var string
	 */
	protected $table;

	/**
	 * This is a model that needs the database, so set up to use it
	 */
	public function __construct($data_table)
	{
		if(!in_array($data_table, self::$accepted_types))
			throw new Exception("Invalid data table: {$data_table}");

		$this-> table = $data_table;
	}

	//----- Data CRUD -----//

	/**
	 * Attempts to create a new record in the data table, or replace an existing one if
	 * @param array $content Must be correct, or there will be database error
	 */
	public function create($content, $replace = false)
	{
		if($replace)
			$q= QueryFactory::replace();
		else
			$q=QueryFactory::insert();
		$q	-> table("data_".$this-> table)
			-> values($content);

		if(MVC_Core::$db-> query($q)===false)
			return false; // error, unable to insert or replace

		// if successful, returns the insert_id (if it exists?)
		return MVC_Core::$db-> insert_id;
	}

	/**
	 * Attempts to read a record using an unique key (by default, in the "id" column)
	 * -- TODO: Ability to do joins and other features when reading a data row, by using WTR and Query prepare Callback
	 *
	 * @param string $key
	 * @param string $key_column
	 */
	public function read($key, $key_column = 'id')
	{
		$q=QueryFactory::select()
		-> table("data_".$this-> table)
		-> where("`{$key_column}` = ?", $key)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);

		if($r && count($r)==1) {
			return $r-> current();
		} else
			return false;
	}

	/**
	 * Attempts to update a record by using an unique key (by default, in the "id" column)
	 * @param string $key
	 * @param array $new_content
	 * @param string $key_column
	 */
	public function update($key, $new_content, $key_column = 'id')
	{
		$q=QueryFactory::update()
		-> table("data_".$this-> table)
		-> values($new_content)
		-> where("`{$key_column}` = ?", $key)
		-> limit(1);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Attempts to delete a record by using an unique key (by default, in the "id" column)
	 * @param string $key
	 * @param string $key_column
	 */
	public function delete($key, $key_column = 'id')
	{
		$q=QueryFactory::delete()
		-> table("data_".$this-> table)
		-> where("`{$key_column}` = ?", $key)
		-> limit(1);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Attempts to read multiple data records, optionally filtering using a callback filter
	 *
	 * @param Callback $qfo_filter If no callback specified, it simply reads all the tasks of the specified type_id
	 * @param array $filter_params
	 * @param string $order_by Which field to be used for ordering
	 * @param int $count_rows
	 * @param int $start_row
	 * @param bool $calc_found_rows If true, uses SQL_CALC_FOUND_ROWS to determine the total number of rows if limits were not set
	 * @return MVC_Database_Result
	 */
	public function get_List($qfo_filter = null, $filter_params = array(), $order_by = null, $count_rows = 1000, $start_row = 0, $calc_found_rows = false)
	{
		$q = $this-> get_List_QueryFactory_Helper($qfo_filter, $filter_params, is_null($order_by)? "`id` ASC": $order_by, $count_rows, $start_row, $calc_found_rows);
		if(empty($q))
			return false; // Error, filter failed

		$q-> table("data_".$this-> table);

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false;// failed to read any records

		return $r;
	}

}