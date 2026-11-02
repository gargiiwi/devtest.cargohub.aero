<?php
/**
 * System for grouping information under the same identifier
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Models
 * @version 2019.01.20
 */
class App_Model_Group extends MVC_Model
{
	/**
	 * Accepted group types (for which there is code to handle the data operations)
	 * -- If a new type is implemented, add it here
	 * @var array[string]
	 */
	public static $accepted_types = array( 
		'general',	// Category group, without any specific purpose
		'airline',	// Airline group, specific for air industry, is used to group issues of types like "Air Cargo Claim", "Air Cargo Incident", "Delivery Delay", "Aircraft Incident" etc.
		'marine',	// Anything related to ships
		'land',		// Groups related to land transport (truck/lorry or rail)
		'dept',		// Organization's department name
		'cert',		// Certification Type
		'customer',	// Business relation manager - The group represents the name of a primary customer (of all kinds)
		'area',		// Software platform area - Used by Development Item and Support ticket (to be moved from "general")
		'class'	,	// Class of item - A sort of category
		'training',  // Group for the training 
                'asset'// Group for the Asset 
//		'transport',	// NOT USED - Method of transportation group, to define how cargo is being moved from one location to another

	);

	/**
	 * The plugin which is used to handle group operations
	 * @var App_Plugin_Group
	 */
	private $plugin;

	/**
	 * Group Type (one of the $accepted_types)
	 * @var string
	 */
	private $group_type;

	/**
	 * Connect the group model to a certain group plugin (only one type used at a time)
	 */
	public function __construct($group_type)
	{
		if(!in_array($group_type, self::$accepted_types))
			throw new Exception("Invalid group type: {$group_type}");

		$this-> plugin = MVC_Core::factory_Plugin("group", $group_type);
		$this-> group_type = $group_type;
	}

	/**
	 * Returns the stored group type ( which was passed as a parameter to the constructor )
	 * @return string
	 */
	public function get_Type()
	{
		return $this-> group_type;
	}

	/**
	 * Forwarder for the get_Config() method of the linked plugin (necessar for the controller/frontend)
	 *
	 * @param bool $editor_mode If true, it reads the extended configuration used when the plugin is used as data editor (and not just when reading information)
	 */
	public function get_Config($editor_mode = false, $editor_module = "@editor")
	{
		if($editor_mode)
			return $this-> plugin-> get_Editor_Config($editor_module);
		else
			return $this-> plugin-> get_Config();
	}

//----- Group CRUD -----//

	/**
	 * Adds a group into the system. Other models and interfaces might use groups in various ways.
	 *
	 * @param string $type
	 * @param string $name
	 * @param mixed $xdata Extra data for a group - It's significance and format depends on group type
	 * @return boolean
	 */
	public function create($type, $group_data, $xdata_encoded = false)
	{
		if(!in_array($type, self::$accepted_types))
			return false; // Wrong group type (not supported by the system)

		$insert = array('type'=> $type);
		if(isset($group_data['name']))
			$insert['name'] = $group_data['name'];
		if(isset($group_data['xdata']))
			if($xdata_encoded)
				$insert['xdata'] = $group_data['xdata'];
			else
				$insert['xdata'] = json_encode($group_data['xdata']);

		$q= QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['grp'])
		-> values($insert);

		if(MVC_Core::$db-> query($q)) {
			$iid = MVC_Core::$db-> insert_id;
			// primary group data updated, call the plugin's "update" method, to update it's foreign data
			$this-> plugin-> save_Foreign_Data($iid, $group_data, true);
			return $iid;
		} else
			return false;
	}

	/**
	 * Reads information about an existing group
	 *
	 * @param int $group_id
	 */
	public function read($group_id)
	{
		$q= QueryFactory::select()
		-> table(MVC_Core::$db-> tab['grp'])
		-> where("`id` = ?", $group_id)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if($r && count($r)==1)
			return $r-> current();
		else
			return null;
	}

	/**
	 * Updates an existing group. The type can't be changed once the group has been created !
	 *
	 * @param int $group_id
	 * @param array $new_group_data
	 * @param bool $xdata_encoded xdata is already encoded (don't json-encode)
	 * @param string $string_expressions This parameter is sent to query factory, and is used to disable string escaping (in order to allow expressions, for example to increment a counter)
	 */
	public function update($group_id, $new_group_data, $xdata_encoded = false, $string_expressions = false)
	{
		$q= QueryFactory::update()
		-> table(MVC_Core::$db-> tab['grp'])
		-> where("`id` = ?", $group_id);

		$update = array();
		if(isset($new_group_data['name']))
			$update['name'] = trim($new_group_data['name']);
		if(isset($new_group_data['count_elements']))
			$update['count_elements'] = $new_group_data['count_elements'];
		if(isset($new_group_data['xdata']))
			$update['xdata'] = $xdata_encoded? $new_group_data['xdata']: json_encode($new_group_data['xdata']);

		$q-> values($update, null, $string_expressions);

		$r = MVC_Core::$db-> query($q);
		if($r) {
			// primary group data updated, call the plugin's "update" method, to update it's foreign data
			return $this-> plugin-> save_Foreign_Data($group_id, $new_group_data, true);
		} else
			return false;
	}

	/**
	 * Removes a group from the system.
	 * Doesn't attempt to remove items that are connected to this group, because this model has no knowledge of what items use this group
	 *
	 * @param int $group_id
	 */
	public function delete($group_id)
	{
		$q= QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['grp'])
		-> where("`id` = ?", $group_id)
		-> limit(1);

		if(MVC_Core::$db-> query($q)) {
			// Call the delete_Foreign_Data function from the plugin, in case the group deletion has been successful
			return $this-> plugin-> delete_Foreign_Data($group_id);
		} else
			return false;
	}

//----- Lists -----//

	/**
	 * Reads a list of groups ordered by ID or name, of a certain group type
	 * -- Supports paging
	 *
	 * @param string $group_type
	 * @param Callback $qfo_filter If no callback specified, it simply reads all the tasks of the specified type_id
	 * @param array $filter_params
	 * @param string $order_by Which field to be used for ordering (id, name)
	 * @param int $count_rows Paging Size
	 * @param int $start_row Paging Start
	 * @return MVC_Database_Result (iterable)
	 */
	public function get_List($wtr = array(), $qfo_filter = null, $filter_params = array(), $order_by = null, $count_rows = 1000, $start_row = 0)
	{
		if(is_null($order_by))
			$order_by = '`grp`.name ASC';

		$q = $this-> get_List_QueryFactory_Helper($qfo_filter, $filter_params, $order_by, $count_rows, $start_row);
		$q-> where("`grp`.type = ?", $this-> group_type); // always of a certain group type

		// What to read?
		$wtr = self::wtr_Helper($wtr);

		if(in_array('group', $wtr))
			$q-> table(MVC_Core::$db-> table('grp'), "id,name,count_elements,xdata");
		else
			$q-> table(MVC_Core::$db-> table('grp'), "id,name"); // quick read, no extra data, no decoding

		if(in_array('foreign', $wtr))
			$this-> plugin-> attach_Foreign_Data($q);

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r) == 0)
			return false;

		if(in_array('foreign', $wtr))
			$this-> plugin-> split_Prefixes($r);

		// read the returned data and decode it if necessary
		$all = array();
		foreach($r as $group) {
			$group['id'] = (int)$group['id'];
			if(in_array('group', $wtr)) {
				$group['count_elements'] = (int)$group['count_elements'];
				$group['xdata'] = @json_decode($group['xdata'], true);
			}

			if(in_array('group', $wtr) || in_array('foreign', $wtr))
				// post process extra group data inside the plugin
				$this-> plugin-> group_Post_Process($group);

			$all[] = $group;
		}

		return $all;
	}

	/**
	 * Calls a custom method of the group plugin (if it exists). Otherwise it fails silently and returns "false"
	 * @param MVC_Request $rq
	 */
	public function plugin_Call(MVC_Request $rq)
	{
		$method_name = "custom_".$rq-> method;

		if(method_exists($this-> plugin, $method_name))
			return call_user_func(array($this-> plugin, $method_name), $rq);
		else
			return false;
	}
}