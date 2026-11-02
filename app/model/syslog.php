<?php
/**
 * System Log (Raw Data) - Tracks changes to the system (Automatically tracked or Manual entry/update)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage DataModels
 * @version 2020.05.22
 */
class App_Model_Syslog extends MVC_Model
{

	// Logging failure codes (get recorded as "logerr") - stored into "op"
	const	ERR_INVALID_AREA =		'inv_area',	// Invalid area specified - only those mentioned into self::$valid_areas can be stored
			ERR_NO_OP = 			'no_op',	// No operation specified (op is empty/null)
			ERR_ENCODE_FAIL =		'enc_fail',	// Data that comes to the logging function cannot be encoded (is resource or recursive)
			ERR_TOO_LARGE =			'data_size'	// Incoming data (encoded) is too large (>64K) and cannot be stored
	;

	/**
	 * Which areas are supported by the logging system ?
	 * @var array
	 */
	private static $valid_areas = array(
		"logerr",	// Error in Log function (should not be used by caller!)
		"bpe",		// Admin - Record Blueprint Editor
		// Manual entries
		"var",		// Various, unknown
		"addition",	// New feature or element added to the software
		"change",	// Some part of the application is changed (in the code)
		"fix",		// A problem was found and fixed
		"config",	// Configuration updated (no changes in code)
		"rollback"	// Some feature or element has been reverted to previous version due to incompatibilities or problems appearing after an addition/change/fix
	);

	/**
	 * Store entry in log - Data may be null
	 * If encountering error, it stores a "logerr" entry but does not return the ID of the "logerr" entry (to signal that something went wrong)
	 *
	 * @param int $user_id Which superuser performed the operation
	 * @param string $area Which area ? - See self::$valid_areas
	 * @param string $op
	 * @param mixed $data May be null, in which case nothing is stored in the "data" field (stays null)
	 * @param string|null $comment Some operations might write a comment
	 * @return int The ID of the new log entry
	 */
	public function log($user_id, $area, $op, $data = null, $comment = null)
	{
		$logerr = array('area'=> $area, 'op'=> $op);

		if(!is_null($data)) {
			$json_data = json_encode($data);
			if($json_data === false) {
				$this-> log($user_id, 'logerr', self::ERR_ENCODE_FAIL, $logerr, $comment);
				return MVC_Core::$debug-> dump("Encoding of log data failed", __METHOD__, 'warning');
			}
		}

		if(empty($area) || !in_array($area, self::$valid_areas)) {
			if(!is_null($data))
				$logerr['count'] = @count($data, 1);
			$this-> log($user_id, 'logerr', self::ERR_INVALID_AREA, $logerr, $comment);

			return MVC_Core::$debug-> dump(sprintf("Invalid area [ %s ] specified in the log entry", $area), __METHOD__, 'warning');
		}

		// The automatic entries cannot have an empty op (Manual item can)
		if(in_array($area, array('bpe')) && empty($op)) {
			if(!is_null($data))
				$logerr['count'] = @count($data, 1);
			$this-> log($user_id, 'logerr', self::ERR_NO_OP, $logerr, $comment);

			return MVC_Core::$debug-> dump(sprintf("No operation specified for area: %s", $area), __METHOD__, 'warning');
		} else
			$op = null;

		$entry = array(
			'user_id'=> $user_id,
			'dt'=> date("Y-m-d H:i:s"),
			'area'=> $area,
			'op'=> $op,
			'data'=> is_null($data)? null: MVC_Core::pretty_JSON($json_data),
			'comment'=> $comment
		);

		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['log'])
		-> values($entry);

		if(MVC_Core::$db-> query($q)===false)
			return MVC_Core::$debug-> dump("Database insert fail", __METHOD__, 'error');

		// if successful, returns the insert_id (if it exists?)
		return MVC_Core::$db-> insert_id;
	}

	/**
	 * Dumps a log entry data as (somewhat) human-readable text, in JSON format - As stored in the database
	 *
	 * @param int $id
	 * @return string Valid JSON from the log entry
	 */
	public function dump_json($id)
	{

	}


	/**
	 * Reads a log entry by ID (returns raw php data)
	 *
	 * @param int $id
	 * @return array
	 */
	public function read($id)
	{

	}

	/**
	 * Dumps a log entry data as (somewhat) human-readable text, in YAML format
	 *
	 * @param int $id
	 * @return string Valid YAML for the log entry
	 */
	public function dump_yaml($id)
	{

	}

	/**
	 * Attempts to decode and explain the data in the log entry (TODO: Later, not a priority)
	 *
	 * @param int $id
	 */
	public function explain($id)
	{

	}

	/**
	 * Updates a log entry (dt, area, op, comment)
	 * -- Note that if area is "logerr", "bpe" (system-generated entries), the dt/area/op cannot be changed. For others they can, but "logerr", "bpe" can't be set
	 *
	 * @param int $id
	 * @param array $update
	 * @return bool
	 */
	public function update($id, $update = null)
	{
		if(!is_array($update))
			return MVC_Core::$debug-> dump("Update data missing or incorrect format", __METHOD__, 'error');

		// Filter invalid update elements
		// any key outside accepted fields is discarded (to avoid database errors)
		$fields = array('dt', 'area', 'op', 'data', 'comment');
		foreach($update as $field=> $value)
			if(!in_array($field, $fields))
				unset($update[$field]);

		if(empty($update))
			return MVC_Core::$debug-> dump("Nothing to update", __METHOD__, 'warning');

		if(empty($update['area']) || !in_array($update['area'], self::$valid_areas))
			return MVC_Core::$debug-> dump(sprintf("Invalid area [ %s ] specified in the log update", $update['area']), __METHOD__, 'warning');

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['log'])
		-> where("`id` = ?", $id)
		-> values($update)
		-> limit(1);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Removes a System Log entry
	 * @param int $id
	 */
	public function delete($id)
	{
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['log'])
		-> where("`id` = ?", $id)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return false;

		return true;
	}

	/**
	 * Reads log entries (used by Admin :: Syslog)
	 *
	 * @param Callback $qfo_filter If no callback specified, it simply reads all the tasks of the specified type_id
	 * @param array $filter_params
	 * @param string $order_by Which field to be used for ordering
	 * @param int $count_rows
	 * @param int $start_row
	 * @param bool $calc_found_rows If true, uses SQL_CALC_FOUND_ROWS to determine the total number of rows if limits were not set
	 * @return array
	 */
	public function get_List($qfo_filter = null, $filter_params = array(), $order_by = null, $count_rows = 1000, $start_row = 0, $calc_found_rows = false)
	{
		$q = $this-> get_List_QueryFactory_Helper($qfo_filter, $filter_params, $order_by, $count_rows, $start_row, $calc_found_rows);
		if(empty($q))
			return false; // Error, filter failed

		$q	-> table(MVC_Core::$db-> table('log'), array('id', 'user_id', 'dt', 'area', 'op', 'comment')) // Data element is skipped, it will be read and decoded only on entry viewing/editing
			-> table(MVC_Core::$db-> table('usr'), QueryFactory::autoalias_Add_Prefix("name", 'user_'), 'left', '`log`.user_id = `usr`.id');

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false;// failed to read any records

		if($calc_found_rows)
			$this-> get_List_Save_FoundRows();

		$entries = array();
		foreach($r as $row) {
			// A bit of post-processing
			$row['id'] = (int)$row['id'];
			$entries[] = $row;
		}

		return $entries;
	}
}