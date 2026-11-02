<?php

/**
 * Database Model base class, provides functionality for accessing the database
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage MVC Framework
 * @version 2019.01.08
 */
abstract class MVC_Model
{
	/**
	 * Collection of objects which might be used by this model ( Databases, Caches, File resources, other models, anything... )
	 * -- Unique keys must be used for each object
	 *
	 * @var array
	 */
	private $entities;

	/**
	 * Returns a reference to an object connected to this model
	 *
	 * @param string $object_key
	 * @return Object
	 * @throws Exception
	 */
	public function &__get($object_key)
	{
		if(isset($this-> entities[$object_key]))
			return $this-> entities[$object_key];
		else
			throw new Exception("Attempting to use the key [{$object_key}] which does not exist in model: ".get_class($this));
	}

	/**
	 * Stores a reference to an object for it to be retrieved by __get
	 *
	 * @param string $object_key
	 * @param Object $object
	 */
	public function __set($object_key, $object)
	{
		if(isset($this-> entities[$object_key]))
			throw new Exception("Entity key [{$object_key}] already used in the model: ".get_class($this));

		$this-> entities[$object_key] = &$object;
	}

	/**
	 * Just a way to store an error message
	 * @var string
	 */
	public $last_error = null;

//===== List Reading Helpers =====//

	public $found_rows = 0;

	/**
	 * Most get_List methods use a similar pattern of assemblying the query, filtering, sorting and limiting results returned
	 * - Must be at the beginning, since this method is creating the QFO
	 *
 	 * @param Callback $qfo_filter Callback to use for custom filtering or returned data
	 * @param array $filter_params
	 * @param string $order_by Which field to be used for ordering (and direction)
	 * @param int $count_rows Limit on the number of rows read. Set this to 0 or null to remove the limit, or on actual page size when server-side paging is active
	 * @param int $start_row Row from which reading starts (used for the limit() method)
	 * @param bool $calc_found_rows Used for paging system
	 * @return QueryFactory
	 */
	protected function &get_List_QueryFactory_Helper($qfo_filter = null, $filter_params = array(), $order_by = null, $count_rows = 1000, $start_row = 0, $calc_found_rows = false)
	{
		$q=QueryFactory::select();

		if($calc_found_rows) {
			$q-> flags("SQL_CALC_FOUND_ROWS");
			$this-> found_rows = 0; // reset previous value
		}

		// filter callback
		if(is_callable($qfo_filter)) {
			call_user_func_array($qfo_filter, array(&$q, $filter_params));
			// when filter fails, it nulls the QueryFactory object, and get_List must fail
			if(is_null($q))
				return $q;
		}

		// order by
		if(!is_null($order_by))
			$q-> order($order_by);

		// limits
		if($count_rows > 0)
			if($start_row > 0)
				$q-> limit($count_rows, $start_row);
			else
				$q-> limit($count_rows);

		return $q;
	}

	/**
	 * Allows joining extra tables on a main list read
	 * - This could be inserted at any point within get_List
	 *
	 * @param QueryFactory $q
	 * @param array $custom
	 * @param array $split_by_prefixes
	 */
	protected function get_List_Custom_Joins(QueryFactory &$q, $custom_joins, &$split_by_prefixes = null)
	{
		// custom join $wtr (could be anything)
		foreach($custom_joins as $c_read) {
			$q-> table($c_read['tab'], $c_read['fields'], isset($c_read['join'])? $c_read['join']: 'left', $c_read['on']);
			if(isset($c_read['split'])) {
				if(empty($split_by_prefixes))
					$split_by_prefixes = array();
				$split_by_prefixes[$c_read['split'][0]] = $c_read['split'][1];
			}
		}
	}

	/**
	 * Very simple query that returns the found rows in case a query was run with limits in place and SQL_CALC_FOUND_ROWS flag was set
	 */
	public function get_List_Save_FoundRows()
	{
		$fr_row = MVC_Core::$db-> query("SELECT FOUND_ROWS() AS `found_rows`;")-> current();
		$this-> found_rows = (int)$fr_row['found_rows'];
		return $this-> found_rows;
	}

//===== Others =====//

	/**
	 * What To Read helper ( for assemblying queries )
	 *
	 * @param mixed $wtr
	 * @return array()
	 */
	public static function wtr_Helper($wtr)
	{
		if(is_null($wtr))
			$wtr = array();
		elseif(is_string($wtr))
			$wtr = explode(',', $wtr);
		elseif(!is_array($wtr))
			$wtr = array();
		return $wtr;
	}
}