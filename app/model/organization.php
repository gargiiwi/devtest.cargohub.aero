<?php
/**
 * "Organization" related data access and input/output processing
 *
 * @author Wavetrex Infinity (wavetrex@gmail.com)
 * @package CargoConnect
 * @subpackage Models
 * @version 2021.02.17
 */
class App_Model_Organization extends MVC_Model
{
//----- Organization CRUD -----//

	/**
	 * Adds an organization and it's profile to the system
	 *
	 * @param array $org_data
	 */
	public function create($org_data)  
	{  
		$mandatory_fields = array('name', 'manager_id');
		$diff = array_diff($mandatory_fields, array_keys($org_data));
		if(!empty($diff))
			return $diff; // error, not all fields available in $org_data (the missing fields are returned)

		// any key outside mandatory+optional fields is discarded (to avoid database errors)
		$fields = array_merge($mandatory_fields, array('type_id', 'features', 'privileges', 'access_ids'));
		foreach($org_data as $field=> $value)
			if(!in_array($field, $fields))
				unset($org_data[$field]);

		// Pre-Process
		if(isset($org_data['features']))
			$org_data['features'] = json_encode($org_data['features']);

		// These are the organization-wide privilege list (simply strings). Departments might override them, or some users might override as well both the organization and department list.
		// Depending on how the organization is configured, their users might be more "free" or more restricted.
		if(is_array($org_data['privileges']))
			$org_data['privileges'] = implode(',', $org_data['privileges']);

		// The access ids is a keyed array containing arrays of values - It's stored as is, and can be overwritten by department or user settings.
		if(isset($org_data['access_ids']))
			$org_data['access_ids'] = json_encode($org_data['access_ids']);

		// prepare query
		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['org'])
		-> values($org_data);

		// and run it
		try {
			MVC_Core::$db-> query($q);

			return MVC_Core::$db-> insert_id;
		} catch(Exception $e) {
			// error, could not add organization - data too long or some other database error
			return false;
		}
	}

	/**
	 * Reads the information for one organization, while decoding the special fields. Can also read additional data, like manager information
	 *
	 * @param int $org_id
 	 * @param array $wtr Indicates what information to read ( organization, manager, departments )
 	 * @param bool $decode Decode fields ( can be disabled, for example when information is sent directly to JS so decoding can take place there instead )
 	 * @return array Organization data
	 */
	public function read($org_id, $wtr = array('organization'), $decode = true)
	{
		$q=QueryFactory::select()
		-> where("`org`.`id` = ?", $org_id)
		-> limit(1);

		$wtr = self::wtr_Helper($wtr);

		if(in_array('organization', $wtr))
			$q-> table(MVC_Core::$db-> table('org')); // all fields in the primary table
		else
			$q-> table(MVC_Core::$db-> table('org'), 'id,type_id,update_dt,name,ist_or'); // read some base fields.

		if(in_array('contact', $wtr))
			// Read detailed contact information for the organization (contact xdata)
			$q-> table(MVC_Core::$db-> table('ctx'), QueryFactory::autoalias_Add_Prefix("name,email,xdata,count_msgs", '_ctx_'), 'left', '`org`.`contact_id` = `ctx`.`id`');

		if(in_array('manager', $wtr))
			$q-> table(array(MVC_Core::$db-> tab['usr'],'mgr'), QueryFactory::autoalias_Add_Prefix("id,user,name,email", '_mgr_'), 'left', "`mgr`.id = `org`.manager_id");

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return MVC_Core::$debug-> dump("Organization not found or reading error occured", __METHOD__, null, 'error');

		// organization was found, read the data
		$r-> split_by_prefixes = array(
			// tidy up
			'count'=> "count_",
			// extra tables
			'contact'=> "_ctx_",
			'manager'=> "_mgr_"
		);
		$org = $r-> current();

		// Post-Process
		$org['id'] = (int)$org['id'];
		if(!empty($org['contact_id']))
			$org['contact_id'] = (int)$org['contact_id'];
		// Global organization element counters ( A bit outdated - Some are still useful )
		if(!empty($org['count']['ctx']))
			$org['count']['ctx'] = (int)$org['count']['ctx'];
		if(!empty($org['count']['files']))
			$org['count']['files'] = (int)$org['count']['files'];
		if(!empty($org['count']['issues']))
			$org['count']['issues'] = (int)$org['count']['issues'];
		if(!empty($org['count']['msgs']))
			$org['count']['msgs'] = (int)$org['count']['msgs'];
		if(!empty($org['count']['users']))
			$org['count']['users'] = (int)$org['count']['users'];

		// Decode contact xdata if present
		if(in_array('contact', $wtr) && !empty($org['contact']['xdata']))
			$org['contact']['xdata'] = @json_decode($org['contact']['xdata'], true);

		// JSON encoded org data
		if($decode && in_array('organization', $wtr)) {
			if(!empty($org['features']))
				$org['features'] = @json_decode($org['features'], true);

			if(!empty($org['privileges']))
				$org['privileges'] = explode(",", $org['privileges']);

			if(!empty($org['access_ids']))
				$org['access_ids'] = @json_decode($org['access_ids'], true);
		}

		if(!empty($org['ist_or']))
			$org['ist_or'] = @json_decode($org['ist_or'], true);

		if(!empty($org['count']['operations']))
			$org['count']['operations'] = @json_decode($org['count']['operations'], true);

		// Organization type data
		if(in_array('orgtype', $wtr))
			$org['orgtype'] = self::orgtype_Read($org['type_id']);

		// Load departments if requested ( dep table )
		if(in_array('departments', $wtr)) {
			$m_dep = new App_Model_Department();
			$org['departments'] = $m_dep-> get_List($org['id'], array('department', 'chief'));
			if(!$org['departments'])
				unset($org['departments']);
		}

		// Load groups that this organization is connected to ( g2o and grp tables )
		if(in_array('groups', $wtr)) {
			$org['group_sets'] = $this-> get_Group_Sets($org['id']);
			if(!$org['group_sets'])
				unset($org['group_sets']);
		}

		return $org;
	}

	/**
	 * Updates a organization's information
	 *
	 * @param int $org_id
	 * @param array $new_org_data
	 * @param bool $xdata_encoded TODO: Deprecated, no longer used ; Check callers and remove the parameter
	 * @param string $string_expressions This parameter is sent to query factory, and is used to disable string escaping (in order to allow expressions, for example to increment a counter)
	 */
	public function update($org_id, $new_org_data, $xdata_encoded = false, $string_expressions = false)
	{
		// filter out keys which do not belong in the organizations table (or can't be updated by this method)
//            Add option to set or change logo from organization start
		$fields = array('type_id', 'status', 'name', 'manager_id', 'features', 'privileges', 'access_ids', 'count_users', 'count_issues', 'count_msgs', 'count_files', 'files_total_kbytes', 'count_operations', 'contact_id','org_logo');
//                Add option to set or change logo from organization end
		foreach($new_org_data as $field=> $value)
			if(!in_array($field, $fields))
				unset($new_org_data[$field]);

		// pre-process (encode) certain fields
		if(isset($new_org_data['features']) || isset($new_org_data['access_ids']) || isset($new_org_data['count_operations'])) {
			// read the existing data, to merge values on some of the encoded fields instead of overwriting
			$q=QueryFactory::select()
			-> table(array(MVC_Core::$db-> tab['org'],'org'), 'features,access_ids,count_operations') // the json-encoded fields
			-> where("`org`.id = ?", $org_id)
			-> limit(1);

			$r = MVC_Core::$db-> query($q);
			if(!$r) // Oops, organization ID not found, something's wrong
				return false;

			MVC_Core::merge_JSON_Fields($new_org_data, $r-> current(), array('features', 'access_ids', 'count_operations'));
		}

		// Privileges are not merged, they are overwritten (if they exist in the $new_org_data) - If array, it gets converted to CSV, if string, it remains like it is
		if(isset($new_org_data['privileges']) && is_array($new_org_data['privileges']))
			$new_org_data['privileges'] = implode(',', $new_org_data['privileges']);

		// update organizations table
		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['org'])
		-> values($new_org_data, null, $string_expressions)
		-> where("`id` = ?", $org_id)
		-> limit(1);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Removes a organization from the system.
	 * This will be the most complex deletion function in the entire application, as EVERYTHING is connected to organizations.
	 *
	 * @param int $org_id
	 */
	public function delete($org_id)
	{  
		// TODO: Maybe? Statistical data for deletion... record the delete process somewhere

		// 1 Read the list of users that belong to this organization, because some records are connected only to users, so we need those user's IDs
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['usr'], 'id')
		-> where('`org_id` = ?', $org_id);

		$r = MVC_Core::$db-> query($q);
		if($r && count($r)>0) {
			// This organization has users, remove the data associated with each of these users which is not already connected to the organization id
			$user_list = array();
			foreach($r as $row)
				$user_list[] = (int)$row['id'];

			// TODO: (Possibly) Move all this in the "user" model, with a mass-delete function

			// 1.1 Active sessions, so any possible user which might be still logged in will get kicked out
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['ass'])
			-> where("`user_id` IN(?)", $user_list);
			MVC_Core::$db-> query($q);

			// 1.2 Session history for the removed users
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['ssh'])
			-> where("`user_id` IN(?)", $user_list);
			MVC_Core::$db-> query($q);

			// 1.3 Communication containers, no longer needed for users which do not exist anymore
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['con'])
			-> where("`user_id` IN(?)", $user_list);
			MVC_Core::$db-> query($q);

			// 1.4 All notes added/changed by the deleted users, be it issue notes, task notes, personal notes or anything.
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['nte'])
			-> where("`editor_id` IN(?)", $user_list);
			MVC_Core::$db-> query($q);

			// 1.5 User profiles, containing various settings that these user made
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['upr'])
			-> where("`user_id` IN(?)", $user_list);
			MVC_Core::$db-> query($q);

			// 1.6 User data related to groups (usually varied preferences)
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['u2g'])
			-> where("`user_id` IN(?)", $user_list);
			MVC_Core::$db-> query($q);

			// 1.7 Remove the users themselves from the users table
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['usr'])
			-> where("`org_id` = ?", $org_id);
			MVC_Core::$db-> query($q);

			// Done with users, moving on...
		}

/*

		// TODO: Update this method to the new sharing system (disconnect deleted organization from all issues)
		// TODO: If the organization was owner, set no current owner

		// 2 Read the list of issues that belong ( are visible only ) to the deleted organization
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['iss'], 'id,group_id')
		-> where('`org_id` = ?', $org_id);

		$r = MVC_Core::$db-> query($q);
		if($r && count($r)>0) {
			// Some issues were found, remove the data for those specific issues
			$issue_list = array();
			$issue_in_group_counts = array(); // add up the number of issues in each group, with the purpose of reducing 'count_elements' in the groups table
			foreach($r as $row) {
				$issue_list[] = (int)$row['id'];
				if(!isset($issue_in_group_counts[$row['group_id']]))
					$issue_in_group_counts[$row['group_id']] = 1;
				else
					$issue_in_group_counts[$row['group_id']]++;
			}

			// TODO: (Possibly) Move all this in the "issue" model, with a mass-delete function

			// to avoid running very long queries, chunk the list of issues into 255 IDs
			$issue_id_lists = array_chunk($issue_list, 255);

			foreach($issue_id_lists as $id_list) {
				// 2.1 Issue X-fields (the big one!)
				$q=QueryFactory::delete()
				-> table(MVC_Core::$db-> tab['ixf'])
				-> where("`issue_id` IN(?)", $id_list);
				MVC_Core::$db-> query($q);

				// 2.2 Issue updates (history)
				$q=QueryFactory::delete()
				-> table(MVC_Core::$db-> tab['isu'])
				-> where("`issue_id` IN(?)", $id_list);
				MVC_Core::$db-> query($q);

				// 2.3 Notes connected to the deleted issues
				// (some/all might have already been deleted when removing the users - However, it's possible that notes were intentionally left in place when an user has been removed)
				$q=QueryFactory::delete()
				-> table(MVC_Core::$db-> tab['nte'])
				-> where("`linked_with` = 'issue'")
				-> where("`linked_id` IN(?)", $id_list);
				MVC_Core::$db-> query($q);
			}

			// 2.4 Remove the issues which are private for this organization
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['iss'])
			-> where('`org_id` = ?', $org_id);
			MVC_Core::$db-> query($q);

			// 2.5 Update the group counters for the deleted issues
			foreach($issue_in_group_counts as $group_id => $issue_count) {
				$q=QueryFactory::update()
				-> table(MVC_Core::$db-> tab['grp'])
				-> where("`id` = ?", $group_id)
				-> values(array('count_elements'=> "`count_elements` - {$issue_count}"), null, true);
				MVC_Core::$db-> query($q);
			}

			// Done with issues, moving on...
		}
*/

		// 3 Read the list of tasks that were created by the organization
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['tsk'], 'id')
		-> where('`org_id` = ?', $org_id);

		$r = MVC_Core::$db-> query($q);
		if($r && count($r)>0) {
			// Some issues were found, remove the data for those specific issues
			$task_list = array();
			foreach($r as $row)
				$task_list[] = (int)$row['id'];

			// 3.1 Task updates (history)
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['tsu'])
			-> where("`task_id` IN(?)", $task_list);
			MVC_Core::$db-> query($q);

			// 3.2 Remove the tasks
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['tsk'])
			-> where('`org_id` = ?', $org_id);
			MVC_Core::$db-> query($q);

			// Done with tasks, moving on...
		}

		// 4 Remove all communication (messages) that is registered to this organization (includes user messages, issue messages, incoming and outgoing e-mail messages, ...)
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['msg'])
		-> where('`org_id` = ?', $org_id);
		MVC_Core::$db-> query($q);

		// 5 Canned messages are an organization feature as well, so remove those belonging to the deleted one
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['can'])
		-> where('`org_id` = ?', $org_id);
		MVC_Core::$db-> query($q);

		// 6 Departments

		// 6.1 Departments of this organization
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['dep'])
		-> where('`org_id` = ?', $org_id);
		MVC_Core::$db-> query($q);

		// 6.2 User to departments for all deleted departments
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['u2d'])
		-> where('`org_id` = ?', $org_id);
		MVC_Core::$db-> query($q);

		// 7 References to files belonging to the organization also removed
		// -- The files themselves are not deleted here due to the way the system stores files (using content-hash, so it's possible that one stored file has actually multiple references, belonging to other organizations)
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['fil'])
		-> where('`org_id` = ?', $org_id);
		MVC_Core::$db-> query($q);

		// 8 IP Rules (restricitons, permissions)
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['ipr'])
		-> where('`org_id` = ?', $org_id);
		MVC_Core::$db-> query($q);

		// 9 All notes connected to the organization - This will delete organization, department and user notes as well as task notes and private issue notes
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['nte'])
		-> where("`org_id` = ?", $org_id);
		MVC_Core::$db-> query($q);

		// 10 Read the groups that this organization has access to (or is connected to)
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['g2o'], 'group_id')
		-> where('`org_id` = ?', $org_id);

		$r = MVC_Core::$db-> query($q);
		if($r && count($r)>0) {
			// 10.1 TODO: depending on group type, for each group_id use the group plugin system to remove any references to this organization from the group data

			// 10.2 Remove the group to organization connection
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['g2o'])
			-> where("`org_id` = ?", $org_id);
			MVC_Core::$db-> query($q);
		}

		// 11 Remove all domains for this organization, so the website is no longer accesible on them
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['ord'])
		-> where("`org_id` = ?", $org_id);
		MVC_Core::$db-> query($q);

		// 12 Delete all report types which have been created for this organization
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['rep'])
		-> where("`org_id` = ?", $org_id);
		MVC_Core::$db-> query($q);

		// 13 Finally, remove the organization itself - She's dead, Jim!
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['org'])
		-> where("`id` = ?", $org_id);
		MVC_Core::$db-> query($q);

		return true;
	}


//----- Lists -----//

	public $found_rows = 0;

	/**
	 * Reads a list of organizations by filtering with the help of a callback that can add conditions to the QueryFactory object
	 * -- Supports paging
	 *
	 * @param array $wtr Indicates what information to read ( if null or empty, reads only id/name )
	 * @param Callback $qfo_filter If no callback specified, it simply reads all the organizations in the system
	 * @param array $filter_params
	 * @param string $order_by Which field to be used for ordering - Defaults to order by name, alphabetically ascending
	 * @param int $count_rows Paging Size
	 * @param int $start_row Paging Start
	 * @return array
	 */
	public function get_List($wtr = array('organization'), $qfo_filter = null, $filter_params = array(), $order_by = null, $count_rows = 100, $start_row = 0, $calc_found_rows = false)
	{
		$q = $this-> get_List_QueryFactory_Helper($qfo_filter, $filter_params, $order_by?: "`org`.`name` ASC", $count_rows, $start_row);
		if(is_null($q))
			return false; // Error, $qfo_filter failed

		if($calc_found_rows)
			$q-> flags("SQL_CALC_FOUND_ROWS");

		// What to read?
		$wtr = self::wtr_Helper($wtr);

		if(in_array('organization', $wtr))
			// important organization details (used for the admin's organization list)
			$q-> table(array(MVC_Core::$db-> tab['org'],'org'), 'id,type_id,status,update_dt,name,contact_id,manager_id,features,count_users,count_issues,count_msgs,count_files,files_total_kbytes,count_operations');
		elseif(in_array('access_ids', $wtr))
			// Read the base access rights for users of this organization
			$q-> table(array(MVC_Core::$db-> tab['org'],'org'), 'id,type_id,name,access_ids');
		else
			// Simple mode (used mostly in selectors/dropdowns)
			$q-> table(array(MVC_Core::$db-> tab['org'],'org'), 'id,type_id,name');

		if(in_array('manager', $wtr))
			// join information about the organization's manager
			$q-> table(array(MVC_Core::$db-> tab['usr'],'usr'), QueryFactory::autoalias_Add_Prefix("name,email,status", '_usr_'), 'left', '`org`.manager_id = `usr`.id');

		// custom join $wtr (could be anything)
		if(isset($wtr['joins']))
			$this-> get_List_Custom_Joins($q, $wtr['joins']);

		$r = MVC_Core::$db-> query($q);
		
		if(!$r || count($r)==0)
			return MVC_Core::$debug-> dump("Organization list result empty", "Organization Model::get_List", null, 'warning');

		$r-> split_by_prefixes = array(
			// organize a bit
			'count'=> "count_",
			// extra table(s)
			'manager'=> "_usr_"
		);

		// get all data and perform decodes on some special fields
		$all = array();
		foreach($r as $org) {
			// Post-Process
			$org['id'] = (int)$org['id'];
			if(in_array('organization', $wtr)) { // When returning details, decode them

				// INTs
				$org['manager_id'] = (int)$org['manager_id'];
				$org['count']['users'] = (int)$org['count']['users'];
				$org['count']['issues'] = (int)$org['count']['issues'];
				$org['count']['msgs'] = (int)$org['count']['msgs'];
				$org['count']['files'] = (int)$org['count']['files'];
				$org['files_total_kbytes'] = (int)$org['files_total_kbytes'];
				$org['count']['operations'] = (int)$org['count']['operations'];

				if(!empty($org['features']))
					$org['features'] = @json_decode($org['features'], true);

				// Display fix for missing data from features:
				if(!isset($org['features']['departments']))		$org['features']['departments'] = 0;
				if(!isset($org['features']['domains']))			$org['features']['domains'] = 0;
				if(!isset($org['features']['groups_total']))	$org['features']['groups_total'] = 0;

/* 					if(!empty($org['privileges']))
						$org['privileges'] = explode(",", $org['privileges']);

					if(!empty($org['access_ids']))
						$org['access_ids'] = @json_decode($org['access_ids'], true);
 */
			}

			if(in_array('access_ids', $wtr)) { // Decode organization base access_ids
				$org['access_ids'] = @json_decode($org['access_ids'], true);
			}

			// If requested, attach from organization-type data ( right now, just name and shortname )
			if(in_array('orgtype', $wtr)) {
				 $orgtype_data = self::orgtype_Read($org['type_id']);

				 $org['orgtype'] = array(
				 	'name'=> $orgtype_data['name'],
				 	'shortname'=> $orgtype_data['shortname']
				 );
			}

			$all[] = $org;
		}

		// Check if the calc_found flag is used
		if($calc_found_rows) {
			$fr_row = MVC_Core::$db-> query("SELECT FOUND_ROWS() AS `found_rows`;")-> current();
			$this-> found_rows = (int)$fr_row['found_rows'];
		}

		return $all;
	}

	//===== Groups to Organization connection (g2o) =====//

	/**
	 * Recalculates and updates the group connection counts for an organization (stored in "features" columns), after a group has been attached or detached from it
	 */
	private function recalc_Group2Org_Counts($org_id)
	{
		$q=QueryFactory::select()
		-> table(array(MVC_Core::$db-> tab['g2o'], 'g2o'), array('count'=> "count(*)"))
		-> table(array(MVC_Core::$db-> tab['grp'], 'grp'), 'type', 'left', '`grp`.`id` = `g2o`.`group_id`')
		-> where("`org_id` = ?", $org_id)
		-> group("`type`");

		// This happens rarely, no problem in running a full table scan every time groups are changed
		$r = MVC_Core::$db-> query($q);
		if($r) {
			$features = array(
				'group_counts'=> array(),
				'groups_total'=> 0
			);
			foreach($r as $row) {
				if(empty($row['type']))
					continue;
				$features['group_counts'][$row['type']] = (int)$row['count'];
				$features['groups_total'] += (int)$row['count'];
			}

			// update the organization to reflect the correct groups count
			$this-> update($org_id, array(
				'features'=> $features
			));
		}
	}

	/**
	 * Connects one or more Groups to an Organization
	 * This usually gives access to the organization to the content linked to those specific groups
	 * -- Important note! This method makes no attempt to detect if a organization ID or group ID really exist, please make sure they do before calling it!
	 *
	 * @param int $org_id
	 * @param array|int $groups_ids Group ID's as an array, CSV string or one single group as one value
	 * @param string $comment Optional comment for this relationship (in most cases it will remain null)
	 */
	public function attach_Groups2Org($org_id, $groups_ids, $comment = null)
	{
		if(empty($org_id))
			return false; // $org_id must not be empty

		if(is_numeric($groups_ids))
			$groups_ids = array($groups_ids);
		elseif(is_string($groups_ids))
			$groups_ids = explode(',', $groups_ids);

		if(empty($groups_ids))
			return false; // Error, nothing to attach

		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['g2o']);

		foreach($groups_ids as $id)
			$q-> values(array(
				'org_id'=> $org_id,
				'group_id'=> $id,
				'comment'=> $comment
			));

		// and run it
		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return false; // Perhaps the organization is already connected to the group? (Or the query simply failed)

		// Insert successful, count the number of groups this org is attached to with the purpose of caching the data into the "features" field
		$this-> recalc_Group2Org_Counts($org_id);
 		return count($groups_ids);
	}

	/**
	 * Tests if a organization is connected to a group, and returns the comment - if it exists, or simply bool(true)
	 *
	 * @param int $org_id
	 * @param int $group_id
	 * @return string The relationship information
	 */
	public function get_Group2Org_Comment($org_id, $group_id)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['g2o'], 'comment')
		-> where("`org_id` = ?", $org_id)
		-> where("`group_id` = ?", $group_id);

		$r = MVC_Core::$db-> query($q);
		if($r && count($r)==1) {
			$g2o = $r-> current();
			if(!empty($g2o['comment']))
				return $g2o['comment'];
			else
				return true;
		}
		return false;
	}

	/**
	 * Updates the relationship between the organization and a group ( update number of elements or comment )
	 *
	 * @param int $org_id
	 * @param int $group_ids
	 * @param array $grp_update What to update - Must only contain fields which exist in the DB, or an error will occur
	 * @param string $comment
	 */
	public function update_Group2Org($org_id, $group_ids, $grp_update)
	{
		if(!empty($grp_update['count_el']))
			$grp_update['count_el'] = (int)$grp_update['count_el'];
		if(!empty($grp_update['comment']))
			$grp_update['comment'] = (string)$grp_update['comment'];
		else
			unset($grp_update['comment']);

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['g2o'])
		-> values($grp_update)
		-> where("`org_id` = ?", $org_id);

		if(is_array($group_ids))
			$q-> where("`group_id` IN(?)", $group_ids);
		elseif(is_numeric($group_ids))
			$q-> where("`group_id` = ?", (int)$group_ids);
		else
			return false;

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Scan [iss] and [iod] tables for records, count how many are in a group and return all the values. This updates number of records per group for an organization
	 * @param int $org_id
	 * @return int
	 */
	public function count_Group2Org_Elements($org_id)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('iod'), array('count'=> "count(*)"))
		-> where("`iod`.org_id = ?", $org_id)
		-> where("`iod`.is_enabled = 1")
		-> table(MVC_Core::$db-> table('iss'), 'group_id', 'left', '`iss`.id = `iod`.issue_id')
		-> group(`iss`.'group_id');

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false;

		$scanresult = array('total'=> 0, 'counts'=> array());
		foreach($r as $row) {
			$scanresult['counts'][(int)$row['group_id']] = (int)$row['count'];
			$scanresult['total']+= $row['count'];
		}

		return $scanresult;
	}

	/**
	 * Detaches an organization from a group (and destroys the comment and other settings)
	 *
	 * @param int $org_id
	 * @param int $group_ids
	 */
	public function detach_Groups_from_Org($org_id, $group_ids)
	{
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['g2o'])
		-> where("`org_id` = ?", $org_id);

		if(is_array($group_ids))
			$q-> where("`group_id` IN(?)", $group_ids);
		elseif(is_numeric($group_ids))
			$q-> where("`group_id` = ?", $group_ids);
		else
			return false;

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return false;

		$this-> recalc_Group2Org_Counts($org_id);
		return true;
	}

	/**
	 * Gets the groups this organization has access to (or is a member of)
	 * @TODO Sort parameters in this method (and any callers)
	 *
	 * @param int $org_id
	 * @param string $group_type Optionally limit the results by group type (Only works when $complete_data is true)
	 * @param bool $complete_data True = Reads the full group information + comment; False = reads only the group_id and count_el(ements) - To build lists of ID's
	 * @param bool $receive_only True = Limits the list of groups to only those that have "f_receive" set to 1
	 *
	 * @return MVC_Database_Result (iterable)
	 */
	public function get_Groups($org_id, $group_type = null, $complete_data = true, $receive_only = false)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('g2o'), $complete_data? 'f_create,f_receive,count_el,comment': array('id'=> "group_id", "count_el"))
		-> where("`g2o`.org_id = ?", $org_id);

		if($receive_only)
			$q-> where("`g2o`.f_receive = 1");

		if($complete_data) {
			$q
			-> table(MVC_Core::$db-> table('grp'), 'id,name,xdata'.(is_null($group_type)? ',type': ''), 'left', "`grp`.id = `g2o`.group_id")
			-> where("`grp`.id IS NOT NULL")
			-> order("`grp`.name ASC");
			 
			 
			 

			// optionally filter by group type
			if(!is_null($group_type))
				$q-> where("`grp`.type = ?", (string)$group_type);
		}
		
		//echo $q; exit();
		//echo $q; exit();
		
 
		return MVC_Core::$db-> query($q);
	}

	/**
	 * Read the organizations which are linked to a certain group
	 * -- Useful to filter organizations which should see/have access to an issue (having a group), but also for other functionality (like selecting a master handler for a group in admin mode)
	 *
	 * @param int $group_id
	 * @param int $org_type Optionally limit the results by organization type
	 * @param int $quick_mode Only reads the ID and Names of the organizations (nothing else)
	 */
	public function get_Group_Linked_Organizations($group_id, $org_type = null, $quick_mode = false)
	{
		if($quick_mode)
			$q=QueryFactory::select()
			-> table(array(MVC_Core::$db-> tab['g2o'], 'g2o'), false)
			-> table(array(MVC_Core::$db-> tab['org'], 'org'), 'id,name', 'left', "`org`.id = `g2o`.org_id");
		else
			$q=QueryFactory::select()
			-> table(array(MVC_Core::$db-> tab['g2o'], 'g2o'), 'comment')
			-> table(array(MVC_Core::$db-> tab['org'], 'org'), 'id,name,xdata'.(is_null($org_type)? ',type_id': ''), 'left', "`org`.id = `g2o`.org_id");

		$q-> where("`g2o`.group_id = ?", $group_id);

		// optionally filter by org type
		if(!is_null($org_type))
			$q-> where("`org`.`type_id` = ?", (int)$org_type);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Extended version of get_Groups, which reads all groups (of all types) to which an organization is connected to and groups them unders "sets" of groups
	 *   Also uses the group plugins to get more information about each group ( using get_Module_Config )
	 * TODO: Determine if this method is really needed or it can be removed.
	 * @param int $org_id
	 * @return array
	 */
	public function get_Group_Sets($org_id)
	{
		$groups_r = $this-> get_Groups($org_id);
		if($groups_r && count($groups_r) > 0) {
			$group_sets = array(); $plugins = array();
			foreach($groups_r as $group) {

				$gt = $group['type'];
				if(!isset($group_sets[$gt])) {
					// Load the group plugin so proper data is set up
					$plugins[$gt] = MVC_Core::factory_Plugin('group', $gt);
					$group_sets[$gt] = $plugins[$gt]-> get_Module_Config();
					$group_sets[$gt]['count'] = 0;
					$group_sets[$gt]['groups'] = array();
				}

				// ... also decode xdata using group plugins (might contain useful information)
				if(!empty($group['xdata']))
					$group['xdata'] = $plugins[$gt]-> decode_XData($group['xdata']);

				unset($group['type']); // remove unnecessary/duplicate data and store
				$group_sets[$gt]['groups'][] = $group;
				$group_sets[$gt]['count']++;
			}

			return $group_sets;
		} else
			return false; // This organization has no connection to any group
	}

	//===== Issuetypes to Organization connection (i2o) =====//

	/**
	 * Recalculates and updates the number of issues (of all available types) and also global issue counter (org > count_issues)
	 * @param unknown_type $org_id
	 */
	private function recalc_Issue_Counts($org_id)
	{

	}

	/**
	 * Gets all the issue types that can be available for the organization, and also the connection information
	 *
	 * @param int $org_id
	 * @param bool $only_active Reads only active issue types ( which exist in the i2o table ), otherwise reads all types
	 * @param bool $only_receiving Reads only the issue types which are set to receive data
	 *
	 * @return MVC_Database_Result (iterable)
	 */
	public function ist_Get_List($org_id, $only_enabled = false, $only_receiving = false)
	{
		if(empty($org_id) || !is_numeric($org_id))
			return false; // No org id? No data!

		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('i2o'), "enabled,send,receive")
		// Right-Join Issue_Types (all are read, but i2o data is shown only when it exists)
		-> table(MVC_Core::$db-> table('ist'), 'id,key,scope,name', $only_enabled? 'left': 'right', "`ist`.key = `i2o`.istkey AND `i2o`.org_id = {$org_id}")
		-> order("`ist`.name ASC")
		-> where("`ist`.scope IN('global','org')");
		if($only_enabled)
			$q-> where("`i2o`.enabled = 1");
		if($only_receiving)
			$q-> where("`i2o`.receive = 1");

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Creates or updates the records indicating which issue types are available for this organization.
	 * TODO: Maybe check if the type in the list actually exists ?
	 *
	 * @param int $org_id
	 * @param array $ist2org_list List of keys (textual). If a key is present, it means the issue type is being enabled
	 */
	public function ist_Save_List($org_id, $ist2org_list)
	{
		// Update everything to 0,...
		MVC_Core::$db-> query(
			QueryFactory::update()
			-> table(MVC_Core::$db-> tab['i2o'])
			-> values(array('enabled'=> 0))
			-> where("`org_id` = ?", $org_id)
		);

		// ... then back to 1 for the enabled ones
		MVC_Core::$db-> query(
			QueryFactory::update()
			-> table(MVC_Core::$db-> tab['i2o'])
			-> values(array('enabled'=> 1))
			-> where("`org_id` = ?", $org_id)
			-> where("`istkey` IN(?)", $ist2org_list)
		);

		// ... then insert-ignore to create the missing default records
		$q=QueryFactory::insert()
		-> flags('IGNORE')
		-> table(MVC_Core::$db-> table('i2o'));
		foreach($ist2org_list as $key)
			$q-> values(array(
				'istkey'=> $key,
				'org_id'=> $org_id,
				'enabled'=> true
			));

		MVC_Core::$db-> query($q);
	}

	//===== Domains (Internet Addresses) =====//

	/**
	 * Recalculates and updates the domain counts for an organization (stored in "features" columns), after a domain has been created or removed
	 */
	private function recalc_Domain_Counts($org_id)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['ord'], array('count'=> "count(*)"))
		-> where("`org_id` = ?", $org_id);

		// This happens rarely, no problem in running a full table scan every time groups are changed
		$r = MVC_Core::$db-> query($q);
		if($r) {
			$row = $r-> current();

			// update the organization to reflect the correct domains count
			$this-> update($org_id, array(
				'features'=> array(
					'domains'=> (int)$row['count'],
				)
			));
		}
	}

	/**
	 * Creates a domain which is associated with an organization. It is usually used to change the visual style the organization's customers will see
	 * @param int $org_id
	 * @param array $domain_data
	 */
	public function domain_Create($org_id, $domain_data)
	{
		// any key outside correct fields is discarded (to avoid database errors)
		$fields = array('domain', 'html_settings', 'public_settings');
		foreach($domain_data as $field=> $value)
			if(!in_array($field, $fields))
				unset($domain_data[$field]);

		// Pre-Process
		MVC_Core::merge_JSON_Fields($domain_data, array('html_settings'=> "{}", 'public_settings'=> "{}"), array('html_settings', 'public_settings'));
		$domain_data['org_id'] = $org_id;

		// prepare query
		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['ord'])
		-> values($domain_data);

		// and run it
		$r = MVC_Core::$db-> query($q);
		if($r) {
			$this-> recalc_Domain_Counts($org_id);
			return true;
		} else
			return false;
	}

	/**
	 * Reads a domain's information and decodes settings and overrides
	 * - Can also read information about the organization owning the domain ( used for certain settings ), as well as the organization type
	 *
	 * @param string $domain
	 * @param array $wtr What to read [ domain, organization, orgtype ]
	 * @return array
	 */
	public function domain_Read($domain, $wtr = array('domain'))
	{
		$wtr = self::wtr_Helper($wtr);

		$q=QueryFactory::select()
		-> where("`domain` = ?", $domain)
		-> limit(1);

		if(in_array('domain', $wtr))
			$q-> table(MVC_Core::$db-> table('ord')); // All domain record fields
		else
			$q-> table(MVC_Core::$db-> table('ord'), 'https,org_id'); // Don't read the domain record itself, but can be used to check if the domain exists or not in the system

		if(in_array('organization', $wtr))
			$q-> table(MVC_Core::$db-> table('org'), QueryFactory::autoalias_Add_Prefix("type_id,name,ist_or", '_org_'), 'left', '`ord`.org_id = `org`.id');

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return false;

		// domain found, read the data

		$r-> split_by_prefixes = array(
			// extra tables
			'organization'=> "_org_"
		);
		$ord = $r-> current();

		$ord['org_id'] = (int)$ord['org_id'];
		$ord['https'] = (bool)$ord['https']; // If set to 1, the domain should work with https (ssl certificate is installed)
		if(in_array('domain', $wtr)) {
			$ord['html_settings'] = @json_decode($ord['html_settings'], true);
			$ord['public_settings'] = @json_decode($ord['public_settings'], true);
			if(!empty($ord['ist_or']))
				$ord['ist_or'] = @json_decode($ord['ist_or'], true);
		}

		if(in_array('organization', $wtr)) {
			if(!empty($ord['organization']['ist_or']))
				$ord['organization']['ist_or'] = @json_decode($ord['organization']['ist_or'], true);

			if(in_array('orgtype', $wtr)) {
				$ord['organization']['orgtype'] = $this-> orgtype_Read($ord['organization']['type_id']);
			}
		}

		return $ord;
	}

	/**
	 * Updates a domain information's json-encoded settings. Organization ID can't be changed !
	 *
	 * @param string $domain
	 * @param array $new_domain_settings
 	 * @param bool $encoded Indicates if $new_org_data's fields are already json_encoded
	 */
	public function domain_Update($domain, $new_domain_settings = array(), $encoded = false)
	{
		// filter out keys which do not belong in the organizations table (or can't be updated by this method)
		$fields = array('html_settings', 'public_settings');
		foreach($new_domain_settings as $field=> $value)
			if(!in_array($field, $fields))
				unset($new_domain_settings[$field]);

		// pre-process (encode)
		if(isset($new_domain_settings['html_settings']) || $new_domain_settings['public_settings']) {
			// read the existing data, to merge values on some of the encoded fields instead of overwriting
			$q=QueryFactory::select()
			-> table(MVC_Core::$db-> tab['ord'], "html_settings,public_settings")
			-> where("`domain` = ?", $domain)
			-> limit(1);

			$r = MVC_Core::$db-> query($q);
			if(!$r) // Oops, domain ID not found, something's wrong
				return false;
			MVC_Core::merge_JSON_Fields($new_domain_settings, $r-> current(), array('html_settings', 'public_settings'));
		}

		// update organizations table
		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['ord'])
		-> values($new_domain_settings)
		-> where("`domain` = ?", $domain)
		-> limit(1);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Removes a domain from the database.
	 *   After they logout or close browser, users cannot enter anymore from this address !
	 *
	 * @param string $domain
	 * @param string $org_id While not necesarely requied, spcifying an org_id beside the domain itself is a security feature, to avoid deleting somebody else's domain
	 */
	public function domain_Delete($domain, $org_id)
	{
		// prepare query
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['ord'])
		-> where("`domain` = ?", $domain)
		-> where("`org_id` = ?", $org_id);

		// and run it
		$r = MVC_Core::$db-> query($q);
		if($r) {
			$this-> recalc_Domain_Counts($org_id);
			return true;
		} else
			return false;
	}

	/**
	 * Reads the list of domains and decode the JSON in the html_settings and public_settings
	 * -- Optionally, returns only the list of domains ( one field ) - Quick Mode
	 *
	 * @param int $org_id
	 * @param bool $quick_mode
	 * @return array
	 */
	public function domain_Get_List($org_id, $quick_mode = false)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['ord'], "domain". ($quick_mode? '': ",html_settings,public_settings"))
		-> where("`org_id` = ?", $org_id)
		-> order("`domain` ASC");

		$r = MVC_Core::$db-> query($q);
		if($r) {
			$domains = array();
			foreach($r as $dom) {
				// decode html_settings and public_settings
				if(!$quick_mode) {
					$dom['html_settings'] = @json_decode($dom['html_settings'], true);
					$dom['public_settings'] = @json_decode($dom['public_settings'], true);
				}
				$domains[] = $dom;
			}

			return $domains;
		} else
			return false;
	}

	//===== Organization Type Data (Supports Caching) =====//

	/**
	 * Cache for the organization type data, to avoid unnecessary YAML decodes
	 *
	 * @var array
	 */
	protected static $orgtype_data = array();

	/**
	 * Read organization type data (YAML format)
	 *
	 * @param string $type_id
	 * @param bool $restricted True = Remove the settings information
	 * @return array Reference to the cached data
	 */
	public static function &orgtype_Read($type_id, $restricted = false)
	{
		// If not cached, attempt to read orgtype data
		if(!isset(self::$orgtype_data[$type_id])) {
			$filename = ROOT .DS. 'app'. DS. 'data'. DS. 'org_types'. DS. $type_id. '.yaml';

			if(file_exists($filename)) {
				// Found, load and decode
				self::$orgtype_data[$type_id] = MVC_Core::$spyc-> loadFile($filename);
				self::$orgtype_data[$type_id]['id'] = $type_id;

				if($restricted) {
					unset(self::$orgtype_data[$type_id]['controllers'], self::$orgtype_data[$type_id]['mainmenu']);
					unset(self::$orgtype_data[$type_id]['default_features'], self::$orgtype_data[$type_id]['default_privileges'], self::$orgtype_data[$type_id]['default_access_ids']);
				}
			} else
				// Not found, store "false"
				self::$orgtype_data[$type_id] = false;
		}

		// Always return a reference to the element from the cache
		return self::$orgtype_data[$type_id];
	}

	/**
	 * Scans the data folder containing organization types for .yaml files and read all types.
	 * Builds an array with all data
	 */
	public static function &orgtypes_ScanAll()
	{
		$orgtypes = array();
		foreach(array_diff(scandir(ROOT. DS. 'app'. DS. 'data'. DS. 'org_types'), array('.', '..', '.svn')) as $filename) {
			$id = str_replace('.yaml', '', $filename);
			$orgtypes[$id] = MVC_Core::$spyc-> loadFile(ROOT .DS. 'app'. DS. 'data'. DS. 'org_types'. DS. $filename);
		}
		return $orgtypes;
	}

	//===== Organization Partnerships =====//

	/**
	 * Creates a partnership record between two organizations
	 *
	 * @param int $org_id The organization
	 * @param int $partner_id It's partner organization
	 * @param string $description Details about what this partnership is about
	 * @param array $relation_data Information about this relation ( features, automations )
	 */
	public function partnership_Create($org_id, $partner_id, $description = null, $relation_data = null)
	{
		if($org_id == $partner_id)
			return false; // error, attempting to link an organization to itself

		// Check if partner exists, and read basic info
		$partner = $this-> read($partner_id, array(), false);
		if(!$partner)
			return false; // error, the partner doesn't exist

		$partnership_data = array(
			'org_id'=> $org_id,
			'partner_id'=> $partner_id,
			'partner_type'=> $partner['type_id']
		);
		if(!is_null($description))
			$partnership_data['description'] = $description;
		if(!is_null($relation_data))
			$partnership_data['relation_data'] = json_encode($relation_data);

		// prepare query
		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['par'])
		-> values($partnership_data);

		// and run it
		return MVC_Core::$db-> query($q);
	}

	/**
	 * Connects one or more organizations to another organization as partners, without verifiying ID's
	 * - This method is similar to the Create one, except for not being able to set a $relation_data
	 *
	 * @param int $org_id
	 * @param string $partner_type Must be a valid organization type
	 * @param array|string $partner_ids
	 * @param string $description
	 */
	public function partnership_Multi_Link($org_id, $partner_type, $partner_ids, $description = null)
	{
		if(empty($org_id))
			return false; // $org_id must not be empty

		if(is_numeric($partner_ids))
			$partner_ids = array($partner_ids);
		elseif(is_string($partner_ids))
			$partner_ids = explode(',', $partner_ids);

		if(empty($partner_ids))
			return false; // Error, nothing to attach

		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['par']);

		foreach($partner_ids as $id) {
			if($org_id == $id)
				continue; // Don't attempt to link an organization to itself
			$q-> values(array(
				'org_id'=> $org_id,
				'partner_id'=> $id,
				'partner_type'=> $partner_type,
				'description'=> $description
			));
		}

		// and run it
		$r = MVC_Core::$db-> query($q);
		if($r)
			return true;
		else
			return false; // Error, possibly one of the partners is already connected?
	}

	/**
	 * Reads the relation between two organizations
	 *
	 * @param int $org_id The organization
	 * @param int $partner_id The partner organization
	 * @return array
	 */
	public function partnership_Read($org_id, $partner_id)
	{
		$q=QueryFactory::select()
		-> table(array(MVC_Core::$db-> tab['par'],'par'))
		-> table(array(MVC_Core::$db-> tab['org'],'org'), array('partner_name'=> "name"), 'left', '`par`.partner_id = `org`.id')
		-> where("`org_id` = ?", $org_id)
		-> where("`partner_id` = ?", $partner_id)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false;

		// found !
		$r-> split_by_prefixes = array(
			// organize a bit
			'partner'=> "partner_",
			'issues'=> "issues_"
		);

		$par = $r-> current();

		// Post-Processing
		$par['org_id'] = (int)$par['org_id'];
		$par['partner']['id'] = (int)$par['partner']['id'];
		if(!empty($par['relation_data']))
			$par['relation_data'] = @json_decode($par['relation_data'], true);
		$par['issues']['shared'] = (int)$par['issues']['shared'];
		$par['issues']['sent_total'] = (int)$par['issues']['sent_total'];

		$orgtype_data = self::orgtype_Read($par['partner']['type']);
		$par['partner']['type_name'] = $orgtype_data['shortname'];

		return $par;
	}

	/**
	 * TODO: Updates the partnership information
	 *
	 * @param int $org_id
	 * @param int $partner_id
	 * @param string $comment
	 */
	public function partnership_Update($org_id, $partner_id, $update_data = null)
	{
		/*
		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['par'])
		-> where("`org_id` = ?", $org_id)
		-> where("`partner_id` = ?", $partner_id)
		-> values();

		return MVC_Core::$db-> query($q);
		*/
	}

	/**
	 * Disconnects an organization from another (parthership)
	 *
	 * @param int $org_id
	 * @param int $partner_id
	 */
	public function partnership_Delete($org_id, $partner_id)
	{
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['par'])
		-> where("`org_id` = ?", $org_id)
		-> where("`partner_id` = ?", $partner_id);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Reads partnerships relations for an organization
	 * -- Optionally, returns only ID and Name - Quick Mode, used for dropdowns
	 *
	 * @param int $org_id
	 * @param bool $quick_mode
	 * @param string|array[string] $partner_types Only read parthers of a certain type (or types, in case of an array)
	 * @param array $org_id_filter A list of organization ID's used as a filter (attempts to read only organizations who's ID's ar in the array)
	 * @return array
	 */
	public function partnership_Get_List($org_id, $quick_mode = false, $partner_types = null, $org_id_filter = null)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('par'), "partner_id,partner_type". ($quick_mode? '': ",description,issues_shared,issues_sent_total"))
		-> table(MVC_Core::$db-> table('org'), "name,features", 'left', '`par`.partner_id = `org`.id') // Only the organization name is needed.
		-> where("`org_id` = ?", $org_id);

		// Extra type(s) filter
		if(!empty($partner_types))
			if(is_array($partner_types))
				$q-> where('`par`.partner_type IN(?)', $partner_types);
			else
				$q-> where('`par`.partner_type = ?', $partner_types);

		if(!empty($org_id_filter))
			$q-> where("`partner_id` IN(?)", $org_id_filter);

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false;

		if(!$quick_mode)
			$r-> split_by_prefixes = array(
				// organize a bit
				'partner'=> "partner_",
				'issues'=> "issues_"
			);

		$partners = array();
		foreach($r as $par) {
			if(!$quick_mode) {
				$par['partner']['id'] = (int)$par['partner']['id'];
				$par['issues']['shared'] = (int)$par['issues']['shared'];
				$par['issues']['sent_total'] = (int)$par['issues']['sent_total'];

				$orgtype_data = self::orgtype_Read($par['partner']['type']);
				$par['partner']['type_name'] = $orgtype_data['name'];
			} else {
				$par['id'] = (int)$par['partner_id'];
				$par['type_id'] = $par['partner_type'];

				// Extract some needed values from features
				$features = json_decode($par['features'], true);
				$par['true_att'] = (bool)$par['features']['real_attach'];

				unset($par['partner_id'], $par['partner_type'], $par['features']);

			}

			$partners[] = $par;
		}
		return $partners;
	}


	/**
	 * Reads an user profile default for an organization
	 *
	 * @param int $org_id
	 * @param string $pkey
	 * @param bool $do_not_decode
	 */
	public function profile_Default_Read($org_id, $pkey, $do_not_decode = false)
	{
		// When the profile was not found, attempt to read the "default" for the user's organization, then copy that to the user's personal profile ( auto-initialization )
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['upd'], 'data')
		-> where("`org_id` = ?", $org_id)
		-> where("`pkey` = ?", $pkey)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false;
		else {
			$row = $r-> current();
			return $do_not_decode? $row['data']: (empty($row['data'])? false: @json_decode($row['data'], true));
		}
	}

	/**
	 * Creates or Updates an organization user default profile
	 * -- On update it overwrites any setting that might be there with the provided data
	 *
	 * @param int $org_id
	 * @param string $pkey Profile key (to locate profile entries)
	 * @param array $data If null or empty, the profile key is deleted instead
	 * @param bool $data_encoded Indicates that the data has been pre-encoded as JSON before calling this method (possibly directly from JS before reaching PHP)
	 * @param array $pname Profile name (not mandatory)
	 * @param int $source_user_id
	 */
	public function profile_Default_Write($org_id, $pkey, $data = null, $data_encoded = false, $pname = null, $source_user_id = null)
	{
		$q=QueryFactory::replace()
		-> table(MVC_Core::$db-> tab['upd'])
		-> values(array(
			"org_id"=> $org_id,
			"pkey"=> $pkey,
			"pname"=> $pname,
			"source_user"=> $source_user_id,
			"data"=> $data_encoded? $data: json_encode($data)
		));

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Updates a profile default description without chaning data. Returns the number of affected rows (1) or (0) if row was not found.
	 *
	 * @param int $org_id
	 * @param string $pkey
	 * @param string $pname
	 * @param int $variant
	 */
	public function profile_Default_Update_Pname($org_id, $pkey, $pname = null, $variant = 1)
	{
		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['upd'])
		-> where("`org_id` = ?", $org_id)
		-> where("`pkey` = ?", $pkey)
		-> values(array(
			'pname'=> $pname
		));

		MVC_Core::$db-> query($q);
		return MVC_Core::$db-> affected_rows;
	}

	/**
	 * Reads the list of one organization's profile defaults
	 * @param int $org_id
	 * @param bool $read_source_user If true, links the users table to read the name of the person who's profile record was used to create this organization default
	 */
	public function profile_Default_Get_List($org_id, $read_source_user = false)
	{
		if(empty($org_id))
			return false; // Error $org_id empty, not specified, can't run query

		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('upd'), "pkey,pname,ctime,source_user")
		-> where("`upd`.org_id = ?", $org_id)
		-> order('`pkey` ASC');

		if($read_source_user)
			$q-> table(MVC_Core::$db-> table('usr'), array("source_user_name"=> "name"), 'left', '`upd`.source_user = `usr`.id');

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false; // error or empty result

		return $r;
	}

	/**
	 * Sets a contact ID to be used as target contact for issue communication for an issue type
	 * - This method creates a record (and silently does nothing when same contact already used under the same conditions)
	 *
	 * @param int $org_id
	 * @param string $istkey
	 * @param int $contact_id
	 * @param int $dep_id
	 */
	public function targetContact_Set($org_id, $istkey, $contact_id, $dep_id = null)
	{
		$q=QueryFactory::insert()
 		-> flags('ignore')
		-> table(MVC_Core::$db-> tab['otc'])
		-> values(array(
			'org_id'=> $org_id,
			'istkey'=> $istkey,
			'contact_id'=> $contact_id,
			'dep_id'=> empty($dep_id)? null: $dep_id
		));

		$r = MVC_Core::$db-> query($q);

		// Department only target and org only target are mutually exclusive
		$qd=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['otc'])
			-> where("`org_id` = ?", $org_id)
			-> where("`istkey` = ?", $istkey)
			-> where("`contact_id` = ?", $contact_id);

		if(empty($dep_id))
			$qd-> where("`dep_id` IS NOT NULL");
		else
			$qd-> where("`dep_id` IS NULL");
		MVC_Core::$db-> query($qd);

 		return $r;
	}

	/**
	 * Finds the ID of a target contact used for issue communication for an issue type
	 * - Department ID is optional, but when present it is also used in the search
	 *
	 * @param int $org_id
	 * @param string $rtkey Extra filter by Record Type Key
	 * @param int $deps_ids
	 * @param bool $incl_orgwide Include orgwide targets in the results (Should be used for grabbing contacts, should not be used for editing them)
	 * @return array An array of contact IDs that can be used as targets
	 */
	public function targetContact_Get_IDs($org_id, $rtkey = null, $deps_ids = null, $incl_orgwide = false)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['otc'], 'contact_id')
		-> where("`org_id` = ?", $org_id)
		-> group("`contact_id`"); // only returns the same ID once.

		if(!empty($rtkey))
			$q-> where("`istkey` = ?", $rtkey);

		if(is_array($deps_ids))
			$q-> where("`dep_id` IN(?)".($incl_orgwide?" or `dep_id` IS NULL":''), $deps_ids);
		elseif(is_numeric($deps_ids))
			$q-> where("`dep_id` = ?".($incl_orgwide?" or `dep_id` IS NULL":''), $deps_ids);
		elseif($deps_ids === false) // Reject contacts that are only for a dep
			$q-> where("`dep_id` IS NULL");

		$r = MVC_Core::$db-> query($q);
		$all = array();
		if(!$r || count($r)==0)
			return $all; // No contacts linked under provided conditions

		foreach($r as $tlink)
			$all[] = (int)$tlink['contact_id'];

		return $all;
	}

	/**
	 * Global search mechanism to find the organization and department for which an e-mail is a target contact
	 *
	 * @param string $email
	 * @param string $rtkey Filter only certain record types in the search
	 * @param bool $full_contact_info null = Don't read contact at all, just the link; false = Read just Name/Email for contacts; true = Fully decode contact info (xdata)
	 * @return array Returns the matching contact (if it exists), or null if the e-mail was not found
	 */
	public function targetContact_Find_Email($email, $rtkey = null, $full_contact_info = false)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('otc'), false) // Nothing here
		-> table(MVC_Core::$db-> table('ctx'), "id,org_id,dep_id,user_id,type,ctime,email,name".($full_contact_info? ",xdata": ""), 'left', '`otc`.contact_id = `ctx`.id')
		-> where("`ctx`.email = ?", $email);

		if(!empty($rtkey))
			$q-> where("`otc`.istkey = ?", $rtkey);

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r) == 0)
			return null; // Nothing found (or DB error)

		// Some post-processing
		$tcon = $r-> current();

		$tcon['id'] = (int)$tcon['id'];
		if(!is_null($tcon['org_id']))
			$tcon['org_id'] = (int)$tcon['org_id'];
		if(!is_null($tcon['user_id']))
			$tcon['user_id'] = (int)$tcon['user_id'];

		if($full_contact_info)
			$tcon['xdata'] = @json_decode($tcon['xdata'], true);

		return $tcon;
	}

	/**
	 * Global search for linked contacts used to find the organization and department(s) for which the contact is a target
	 *
	 * @param int $con_id ID of the conctact to be checked
	 * @param string $rtkey The contact must be a target for this particular record type (and no others)
	 * @return array containing: 'org_id', 'dep_ids'=> array(...) | null if this contact isn't a target !
	 */
	public function targetContact_Check($con_id, $rtkey)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('otc'), array('org_id', 'dep_id'))
		-> where("`contact_id` = ?", $con_id)
		-> where("`istkey` = ?", $rtkey);

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r) == 0)
			return null; // Nothing found (or DB error)

		$result = array(
			'org_id'=> null,
			'dep_ids'=> array()
		);
		// Multiple departments could get the link
		foreach($r as $tgt) {
			if(is_null($result['org_id']))
				// Only needed once, even if received multiple times
				$result['org_id'] = $tgt['org_id'];
			if(!empty($tgt['dep_id']))
				$result['dep_ids'][] = (int)$tgt['dep_id'];
		}
		if(empty($result['dep_ids']))
			$result['entire_org'] = true; // If no departments were found but there was still a result, it means the entire organization is a target

		return $result;
	}

	/**
	 * Unlinks a contact ID as a target contact for an organization, from all issue types or only some of them
	 *
	 * @param int $org_id
	 * @param int $contact_id
	 * @param string|array $istkeys
	 */
	public function targetContact_Unlink($org_id, $contact_id, $istkeys = null)
	{
		if(is_string($istkeys))
			$istkeys = explode(',', $istkeys);

		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['otc'])
		-> where("`org_id` = ?", $org_id)
		-> where("`contact_id` = ?", $contact_id);

		if(!is_null($istkeys))
			$q-> where("`istkey` IN(?)", $istkeys);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Lists the issue types and departments for which the specified contact is used as an issue target.
	 *
	 * @param int $org_id
	 * @param int $contact_id
	 * @return array|bool If this contact is not used as a target for the specified organization it returns false. Otherwise it returns the list
	 */
	public function targetContact_UsedFor($org_id, $contact_id)
	{
		if(empty($org_id) || empty($contact_id))
			return false;

		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('otc'), 'dep_id,istkey')
		-> table(MVC_Core::$db-> table('ist'), array('istname'=> 'name'), 'left', "`ist`.key = `otc`.istkey AND `ist`.scope IN('global','org')")
		-> table(MVC_Core::$db-> table('dep'), array('dep_name'=> 'name'), 'left', "`dep`.id = `otc`.dep_id")
		-> where("`otc`.org_id = ?", $org_id)
		-> where("`otc`.contact_id = ?", $contact_id);

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false; // No contacts linked under provided conditions

		return $r;
	}
        
//        Add option to set or change logo from organization start
        public function getOrgDetails($org_id)
	{
            $q=QueryFactory::select()
		-> table(array(MVC_Core::$db-> tab['org'],'org'), 'id,org_logo')		
		-> where("id = ?", $org_id);

		$r = MVC_Core::$db-> query($q);
                return  $r-> current();
        }
//        Add option to set or change logo from organization end 

        
//        IWI Get Total active users count of this org start
        public function getOrgUserCount($org_id)
	{
            $q=QueryFactory::select()
		-> table(array(MVC_Core::$db-> tab['usr'],'usr'), 'count(id) as tot_active_users')		
		-> where("org_id = ?", $org_id)	
		-> where("status != ?", 'disabled')
		-> where("status != ?", 'invisible');

		$r = MVC_Core::$db-> query($q);
                return  $r-> current();
        }
//        IWI Get Total active users count of this org end 
        
//       IWI report cron settings for admin start 
    public function addNewReportCron($getData) {
        $q = QueryFactory::insert()
                ->table('report_corn_management')
                ->values($getData);
        try {
            MVC_Core::$db->query($q);

            return MVC_Core::$db->insert_id;
        } catch (Exception $e) {
            // error, could not add organization - data too long or some other database error
            return false;
        }
    }

    public function editNewReportCron($id, $getData) {

        $q = QueryFactory::update()
                ->table('report_corn_management')
                ->values($getData)
                ->where("`id` = ?", $id)
                ->limit(1);

//        echo $q;
        try {
            MVC_Core::$db->query($q);

            return MVC_Core::$db->affected_rows;
        } catch (Exception $e) {
            // error, could not add organization - data too long or some other database error
            return false;
        }
    }

    public function getReportCron($report_type,$org_id) {

        $q = QueryFactory::select()
                ->table(
                        array('report_corn_management', 'rcm'),
                        array(
                            'id' => 'id',
                            'org_id' => 'org_id',
                            'rcm_id' => 'id',
                            'report_type' => 'report_type',
                            'user_id' => 'user_id',
                            'user_name' => 'user_name',
                            'user_email' => 'user_email',
                            'email_frequency' => 'email_frequency',
                            'email_day' => 'email_day',
                            'send_by_filter' => 'send_by_filter',
                            'status' => 'status',
                            'rcm_created_at' => 'created_at',
                            'latest_sending_date_time' => 'MAX(rcsl.sending_date_time)',
                            'total_logs' => 'COUNT(rcsl.id)'
                        )
                )
                ->table(
                        array('report_corn_sending_log', 'rcsl'),
                        array(),
                        'LEFT',
                        'rcm.id = rcsl.rcm_id'
                );
                if($report_type!=''){
                $q->where('rcm.report_type = ?', $report_type);
                }
        if($org_id!=''){
            $q ->where('rcm.org_id = ?', $org_id);
        }                
                $q ->group('rcm.id')
                ->group('rcm.report_type')
                ->group('rcm.user_id')
                ->group('rcm.user_name')
                ->group('rcm.user_email')
                ->group('rcm.email_frequency')
                ->group('rcm.email_day')
                ->group('rcm.send_by_filter')
                ->group('rcm.status')
                ->group('rcm.created_at')
                ->order('rcm.id DESC');

//echo $q;
        $r = MVC_Core::$db->query($q);

        if (!$r || count($r) == 0) {
            return array();
        } else {
            $all = array();
            foreach ($r as $row) {
                $send_by_filter = json_decode($row['send_by_filter']);
//                echo "<pre>";
//                print_r($send_by_filter);
                $filterHtml = '';
                unset($send_by_filter->user_issue_id);
                foreach ($send_by_filter as $key => $fltr) {
                    $val = $fltr;

                    if ($key === 'users_type') {
                        $ustType =array(
                            1 => "Internal Users",
                            0 => "External Users",
                        );
                        $val = $ustType[$fltr] ;
                    }
                    if ($key === 'training_result') {
                        $statusMap =array(
                            2 => "Pending",
                            1 => "Pass",
                            0 => "Fail",
                            3 => "No Training"
                        );
                        $val = $statusMap[$fltr] ;
                    }
                    if ($key === 'audit_scheduled_in') {
                        $val = $val.' Days' ;
                    }

                    $label = ucwords(str_replace('_', ' ', $key));
                    $filterHtml .= '<span class="label">' . $label . ':</span><span class="' . (($key === 'training_result') ? "training-result " . strtolower(str_replace(' ', '', $val)) : "") . ' ' . (($key === 'users_type') ? "users-type " . strtolower(str_replace(' ', '', $val)) : "") . '">' . $val . '</span>';
                }

//                echo "</pre>";
                $row['filterHtml'] = $filterHtml;
                $all[] = $row;
            }
            return $all;
        }
    }

    public function getReportCronDetails($id) {
        $q = QueryFactory::select()
                ->table('report_corn_management')
                ->where("id = ?", $id);

        $r = MVC_Core::$db->query($q);
        return $r->current();
    }

    public function deleteReportCron($id) {
        $q = QueryFactory::delete()
                ->table('report_corn_management')
                ->where("id = ?", $id);
        $r = MVC_Core::$db->query($q);
        if ($r) {
            return true;
        } else
            return false;
    }

    public function add_read_sign_mail_log($getData) {
        $q = QueryFactory::insert()
                ->table('report_corn_sending_log')
                ->values($getData);
        try {
            MVC_Core::$db->query($q);

            return MVC_Core::$db->insert_id;
        } catch (Exception $e) {
            // error, could not add organization - data too long or some other database error
            return false;
        }
    }

    public function getReportCronLog($cronSettingId) {
        $q = QueryFactory::select()
                        ->table(array('report_corn_sending_log', 'rcsl'))
                        ->table(
                                array('users', 'usr'),
                                array('user_name' => 'name',),
                                'LEFT',
                                'usr.id = rcsl.user_id'
                        )
                        ->where("rcsl.rcm_id = ?", $cronSettingId)->order('rcsl.id DESC');

        $r = MVC_Core::$db->query($q);

        if (!$r || count($r) == 0) {
            return array();
        } else {
            $all = array();
            foreach ($r as $row) {
                $row['folder_path'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname($row['file_path']));
                $row['file_path'] = basename($row['file_path']);
                $all[] = $row;
            }
            return $all;
        }
    }

//        IWI report cron settings for admin end
    
    
    
//    IWI create api for getting record details start
    public function getSecretKey($org_id) {
        $q = QueryFactory::select()
                ->table('org_api_secret_key')
                ->where("org_id = ?", $org_id);

        $r = MVC_Core::$db->query($q);
        return $r->current();
    }
    public function createSecretKey($org_id) {
        $secretKey = md5(uniqid(rand(), true));
        $q = QueryFactory::insert()
                ->table('org_api_secret_key')
                ->values(array('org_id'=>$org_id,'secret_key'=>$secretKey));
        try {
            MVC_Core::$db->query($q);
            return MVC_Core::$db->insert_id;
        } catch (Exception $e) {
            return false;
        }
    }
    public function regenerateSecretKey($org_id) {
        $secretKey = md5(uniqid(rand(), true));
        $q=QueryFactory::update()
		-> table('org_api_secret_key')
		->values(array('org_id'=>$org_id,'secret_key'=>$secretKey))
		-> where("`org_id` = ?", $org_id)
		-> limit(1);
        try {
            MVC_Core::$db->query($q);
            return MVC_Core::$db->affected_rows;
        } catch (Exception $e) {
            return false;
        }
    }
    public function toggleSecretKeyStatus($org_id,$updateData) {
        $q=QueryFactory::update()
		-> table('org_api_secret_key')
		->values($updateData)
		-> where("`org_id` = ?", $org_id)
		-> limit(1);
        try {
            MVC_Core::$db->query($q);
            return MVC_Core::$db->affected_rows;
        } catch (Exception $e) {
            return false;
        }
    }
//    IWI create api for getting record details end
}