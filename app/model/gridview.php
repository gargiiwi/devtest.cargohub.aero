<?php
/**
 * "Grid View" is a collection of information used to configure how the main records grid is displayed
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage DataModels
 * @version 2.9
 */
class App_Model_GridView extends MVC_Model
{
	// Allowed values
	private static $allowed_visibility_types = array('user', 'dep', 'group', 'org', 'admin');
	private static $allowed_source_types = array('default', 'user', 'autofix', 'copy');

	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 * View's stored data.
	 *
	 * @param int $org_id Organization for which this grid view is created
	 * @param array $data
	 * Mandatory fields:
	 * - name:        A name for this view (Used in the load/save/delete menus and options). Also used by the future reporting module to use a certain view to create the report
	 * - rt_key:      Which record type is it used for
	 * - link_id:     User, Department, Organization or Group ID - Since "visibility" defaults to "user", this should be user ID
	 * - columns:     JSON data related to columns visibility for the record type
	 * Optional fields:
	 * - visibility:  Who can see this view. Options: 'user', 'dep', 'group', 'org' - Defaults to 'user'
	 * - source:      What process created this view. Options: 'default', 'user', 'autofix', 'copy' - Defaults to 'default' (Automatic view created when there is none)
	 * - state:       JSON data exported by jqxGrid itself (used to restore state) - If missing, the state will not be initialized (and should go to a global default)
	 * - width:       Width of the module (grid) - Defaults to 0 (global width)
	 * - autoref_s:   Number of seconds to autorefresh the grid - Defaults to 0 (no autorefresh)
	 * - hide_trackid:Should this view hide the important TrackID column (which contains the HTML link to the record overview) ? Options: 1, 0 - Defaults to 0 (Show the TrackID)
	 * @return int|boolean False for error
	 */
	public function create($org_id, $data)
	{
		if(empty($org_id))
			return MVC_Core::$debug-> dump("Organization ID is mandatory for data insert", __METHOD__, 'error');

		$mandatory_fields = array('name', 'rt_key', 'columns');
		if(isset($data['visibility']) && in_array($data['visibility'], array('user', 'dep', 'group')))
			$mandatory_fields[] = 'link_id';

		$diff = array_diff($mandatory_fields, array_keys($data));
		if(!empty($diff))
			return MVC_Core::$debug-> dump("Mandatory fields missing from View Data: ".implode(', ', $diff), __METHOD__, 'error');

		// any key outside mandatory+optional fields is discarded (to avoid database errors)
		$fields = array_merge($mandatory_fields, array('link_id', 'visibility', 'source', 'state', 'width', 'autoref_s', 'hide_trackid', 'utime')); // Note: utime should not be normally writeable (but active until conversion is complete)
		foreach($data as $field=> $value)
			if(!in_array($field, $fields))
				unset($data[$field]);

		// Verify 'visibility' and 'source' if they contain proper values
		if(isset($data['visibility'])) {
			if(!in_array($data['visibility'], self::$allowed_visibility_types))
				return MVC_Core::$debug-> dump("Visibility is not an accepted type: {$data['visibility']}", __METHOD__, 'error');
		}
		if(isset($data['source'])) {
			if(!in_array($data['source'], self::$allowed_source_types))
				return MVC_Core::$debug-> dump("Source is not an accepted type: {$data['source']}", __METHOD__, 'error');
		}

		if(isset($data['hide_trackid']))
			$data['hide_trackid'] = (bool)$data['hide_trackid'];

		$data['org_id'] = $org_id;

		// Create the Data
		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['gvw'])
		-> values($data);

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return MVC_Core::$debug-> dump("Grid View insert has failed", __METHOD__, 'error');

		// insert succesful, return it's ID
		return MVC_Core::$db-> insert_id;
	}

	/**
	 * Reads a grid view located by it's ID
	 *
	 * @param int $id
	 * @return array
	 */
	public function read($id)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('gvw'))
		-> where("`id` = ?", $id);

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false; // View not found or query error

		return $r-> current();
	}

	/**
	 * Udpate a grid view located by it's ID
	 * @param int $id
	 * @param array $update_data
	 * Similar to the insert, however the following fields cannot be changed once the grid view has been created: 'rt_key'
	 * @param int $update_org If not empty, the grid view is moved to another organization. Admin only operation!
	 * @return bool
	 */
	public function update($id, $update_data, $update_org = null)
	{
		// any key outside mandatory+optional fields is discarded (to avoid database errors)
		$fields = array('name', 'link_id', 'columns', 'visibility', 'source', 'state', 'width', 'autoref_s', 'hide_trackid');
		foreach($update_data as $field=> $value)
			if(!in_array($field, $fields))
				unset($update_data[$field]);

		// Verify 'visibility' and 'source' if they contain proper values
		if(isset($update_data['visibility'])) {
			if(!in_array($update_data['visibility'], self::$allowed_visibility_types))
				return MVC_Core::$debug-> dump("Visibility is not an accepted type: {$update_data['visibility']}", __METHOD__, 'error');
		}
		if(isset($update_data['source'])) {
			if(!in_array($update_data['source'], self::$allowed_source_types))
				return MVC_Core::$debug-> dump("Source is not an accepted type: {$update_data['source']}", __METHOD__, 'error');
		}

		if(!empty($update_org))
			$update_data['org_id'] = $update_org;

		// update the table
		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['gvw'])
		-> values($update_data)
		-> where("`id` = ?", $id)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			// error, failed to update
			return MVC_Core::$debug-> dump("Griv View Update has failed", __METHOD__, 'error');

		return true;
	}

	/**
	 * Delete a grid view located by it's ID
	 * @param int $id
	 * @return bool
	 */
	public function delete($id)
	{
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['gvw'])
		-> where("`id` = ?", $id)
		-> limit(1);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Helper for getting results for grid views
	 *
	 * @param MVC_Database_Result $r
	 * @param string $output_mode
	 */
	private function get_List_Output_Helper(MVC_Database_Result $r, $output_mode)
	{
		$res = array();
		foreach($r as $rec) {
			switch($output_mode) {
				case 'menu':
					switch($rec['visibility']) {
						case 'user':
							$visi_text = "My own"; break;
						case 'dep':
							$visi_text = "Department"; break;
						case 'org':
							$visi_text = "Organization"; break;
					}

					$res[] = array(
						'id'=> (int)$rec['id'],
						'utime'=> $rec['utime'],
						'name'=> $rec['name'],
						'visibility'=> $rec['visibility'],
						'visi_text'=> $visi_text
					);
					break;
			}

		}

		return $res;
	}

	/**
	 * // Reads views related to an organization for management purposes - Supports paging and custom filtering
	 *
	 * @param Callback $qfo_filter If no callback specified, it simply reads all the tasks of the specified type_id
	 * @param array $filter_params
	 * @param string $order_by Which field to be used for ordering
	 * @param int $count_rows
	 * @param int $start_row
	 * @param bool $calc_found_rows If true, uses SQL_CALC_FOUND_ROWS to determine the total number of rows if limits were not set
	 * @return MVC_Database_Result
	 */
	public function get_List($org_id, $qfo_filter = null, $filter_params = array(), $order_by = null, $count_rows = 1000, $start_row = 0, $calc_found_rows = false)
	{
		$q = $this-> get_List_QueryFactory_Helper($qfo_filter, $filter_params, $order_by, $count_rows, $start_row);
		if($calc_found_rows)
			$q-> flags("SQL_CALC_FOUND_ROWS");

		$q	-> table(MVC_Core::$db-> table('gvw'), array('id', 'name', 'visibility', 'link_id', 'source', 'utime', 'width'))
			-> where("`gvw`.org_id = ?", $org_id)
			-> table(MVC_Core::$db-> table('ist'), QueryFactory::autoalias_Add_Prefix("name,key", '_rt_'), 'left', "`ist`.key = `gvw`.rt_key AND `ist`.scope = 'global'");

		if(!empty($filter_params['visi_link'])) {
			switch($filter_params['visi_link']) {
				case 'user':
					$q-> table(MVC_Core::$db-> table('usr', true, 'link'), array('link_name'=> 'name'), 'left', "`link`.id = `gvw`.link_id");
					break;
				case 'dep':
					$q-> table(MVC_Core::$db-> table('dep', true, 'link'), array('link_name'=> 'name'), 'left', "`link`.id = `gvw`.link_id");
					break;
			}
		}

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false;// failed to read any records

		$r-> split_by_prefixes = array(
			// extra table(s)
			'rt'=> "_rt_"
		);

		// Check if the calc_found flag is used

		if($calc_found_rows)
			$this-> get_List_Save_FoundRows();

		return $r;
	}

	/**
	 * Reads multiple grid views a compact form, usually for displaying in user's menu (but also for department and organization)
	 *
	 * @param string $read_mode
	 * - "user" - Returns all views related to the user ( $locator should contain 'user_id', 'dep_id', 'org_id' keys with their specific values - All relevant views are merged together )
	 * - "dep" - Returns only views for a specific department ( $locator should contain the department ID )
	 * - "org" - Returns only views for an organization ( $locator should contain the organization ID - In this case the 'org_id' is searched for, and not 'link_id' )
	 * Note: Paging currently not supported due to the merged nature of the results.
	 * @param array|int $locator
	 * @param string $output_mode
	 * @param string $sort_mode How the views are sorted: "alpha" = All relevant, alphabetically ; "grouped" = User first, Dep second, Org last ; "time" = In the reverse time order, most recently updated first
	 *
	 */
	public function get_Compact_List($read_mode, $locator, $output_mode = null, $sort_mode = 'alpha')
	{
		switch($read_mode) {
			case 'user':
				// User mode - Reads views related to an user (personal, active department and organization)
				if(!isset($locator['user_id']))
					return MVC_Core::$debug-> dump("User mode does not function without an User ID", __METHOD__, 'error');

				$q=QueryFactory::select()
				-> table(MVC_Core::$db-> table('gvw'), array('id', 'name', 'visibility', 'source', 'utime'))
				-> where("`rt_key` = ?", $locator['rt_key'])
				-> where("`org_id` = ?", $locator['org_id']);
				// Reads views relevant to an user specified in the $locator
				switch($sort_mode) {
					case 'alpha':
						// Reads all views with a single query, using CASE WHEN (), then sort the complete results alphabetically by name
						$q-> where("CASE WHEN `visibility` = 'user' THEN `link_id` = ? ELSE 1 END", $locator['user_id']);
 						if(isset($locator['dep_id']))
 							$q-> where("CASE WHEN `visibility` = 'dep' THEN `link_id` = ? ELSE 1 END", $locator['dep_id']);

						$q-> order('`name` ASC');
						break;
					case 'grouped':
						// Reads all views in three separate queries, user first, then department, then org - Mix results together by appending the views
						$q-> order('`name` ASC');
						$q2 = clone $q;
						$q3 = clone $q;
						$q-> WHERE("`visibility` = 'user' AND `link_id` = ?", $locator['user_id']);

						$r = MVC_Core::$db-> query($q);
						$res = $this-> get_List_Output_Helper($r, $output_mode);

						if(isset($locator['dep_id'])) {
							$q2-> WHERE("`visibility` = 'dep' AND `link_id` = ?", $locator['dep_id']);
							$r2 = MVC_Core::$db-> query($q2);
							$res = array_merge($res, $this-> get_List_Output_Helper($r2, $output_mode));
						}

						$q3-> WHERE("`visibility` = 'org'");
						$r3 = MVC_Core::$db-> query($q3);
						$res = array_merge($res, $this-> get_List_Output_Helper($r3, $output_mode));

						return $res;
					case 'time':
						// Reads all views with a single query, using CASE WHEN (), then sort the complete results alphabetically by name
						$q-> where("CASE WHEN `visibility` = 'user' THEN `link_id` = {$locator['user_id']} ELSE 1 END");
						if(isset($locator['dep_id']))
							$q-> where("CASE WHEN `visibility` = 'dep' THEN `link_id` = {$locator['dep_id']} ELSE 1 END");

						$q-> order('`utime` DESC');
						break;
					default:
						return MVC_Core::$debug-> dump("Incorect view sorting mode", __METHOD__, 'error');
				}

				$r = MVC_Core::$db-> query($q);
				if(!$r || count($r)==0)
					return false; // No views found or query error

				return $this-> get_List_Output_Helper($r, $output_mode);
			case 'dep':
				break;
			case 'org':
				break;
		}
	}

}