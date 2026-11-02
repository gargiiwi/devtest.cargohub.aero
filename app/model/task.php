<?php
/**
 * "Tasks" module: An integral part of an issue tracking system
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage DataModels
 * @version 2020.02.12
 */
class App_Model_Task extends MVC_Model
{
	// Allowed values
	private static $allowed_linked_type = array('record');
//----- Tasks CRUD -----//
	/**
	 * Create a new task associated to an an item
	 * 
	 * @param array $task_data
	 * @param string $link_type
	 * @param int $link_id Item to which this task is linked (Type defaults to "record")
	 * @param int $dt_override Override note's Unixtime (not current time, but something else) - Only used when importing external data
	 *
	 * @return int The insert id
	 */
	public function create($task_data, $link_type = null, $link_id = null, $dt_override = null)
	{
		$mandatory_fields = array('org_id', 'title', 'added_by');
		$diff = array_diff($mandatory_fields, array_keys($task_data));
		if(!empty($diff))
			return MVC_Core::$debug-> dump("Mandatory fields missing from Task_Data: ".implode(', ', $diff), __METHOD__, 'error');
		// any key outside mandatory+optional fields is discarded (to avoid database errors)
		$fields = array_merge($mandatory_fields, array(
			'assign_to', 'assign_mode', 'assign_name', 'delegate_id', 'dt', 'status', 'description', 'progress','task_ref','task_type',
			'deadline', 'dl_action_id', 'reminder', 'rem_action_id', 'ovd_intv', 'ovd_action_id', 'ovd_repeat', 'ovd_rep_limit', 'action_stage', 'action_ntf_type'));
		foreach($task_data as $field=> $value)
			if(!in_array($field, $fields))
				unset($task_data[$field]);
		// Pre-Process
		if(!empty($link_type)) {
			if($link_type != "record")
				return MVC_Core::$debug-> dump("Only links of type Record are supported", __METHOD__, 'error');
			$task_data['link_type'] = $link_type;
			$task_data['link_id'] = $link_id;
		}
		$task_data['dt'] = !empty($dt_override)? $dt_override: date("Y-m-d H:i:s"); // Defaults to "now" (time when task is created)
		// Trim spaces before/after title/description, as well as length limit
		$task_data['title'] = substr(trim($task_data['title']), 0, 200);
		$task_data['task_ref'] = trim($task_data['task_ref']);
		$task_data['task_type'] = trim($task_data['task_type']);
		if(!empty($task_data['description']))
			$task_data['description'] = trim($task_data['description']);
		// Create the task record
		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['tsk'])
		-> values($task_data);
		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return false;
		$iid = MVC_Core::$db-> insert_id;
		// Initial log entry with most important information about the task (reference if the task is updated after)
		$create_log_0 = array('created'=> $task_data['title'], 'status'=> 'new'); // Yes, the new status might be "assigned", but this is an indicated that this is the new task entry
		if(isset($task_data['assign_to'])) {
			$create_log_0['assign_to'] = (int)$task_data['assign_to'];
			$create_log_0['assign_mode'] = $task_data['assign_mode'];
			$create_log_0['assign_name'] = $task_data['assign_name'];
		}
                if(isset($task_data['task_ref'])) {
			$create_log_0['task_ref'] = $task_data['task_ref'];
		}
                if(isset($task_data['task_type'])) {
			$create_log_0['task_type'] = $task_data['task_type'];
		}
		if(isset($task_data['deadline']))
			$create_log_0['deadline'] = $task_data['deadline'];
		if(isset($task_data['reminder']))
			$create_log_0['reminder'] = $task_data['reminder'];
		if(isset($task_data['ovd_intv']))
			$create_log_0['ovd_intv'] = (int)$task_data['ovd_intv'];
		// Add in the history the first assignment
		$this-> history_Create($iid, $task_data['added_by'], $create_log_0, "--- Initial task definition ---");
		// insert succesful, return its ID
                
                
                
		return $iid;
	}
	/**
	 * Read a task by it's ID
	 * @param int $task_id
	 * @param array $wtr Indicates what information to read ( if null or empty, just verify that the task id exists ): "task", "link", "creator", "assigned", "updates"
	 *
	 * @return array
	 */
	public function read($task_id, $wtr = array('task'))
	{
		$q=QueryFactory::select()
		-> where("`tsk`.id = ?", $task_id)
		-> limit(1);
		// What to read?
		$wtr = self::wtr_Helper($wtr);
		if(in_array('task', $wtr))
			$q-> table(array(MVC_Core::$db-> tab['tsk'],'tsk')); // all fields for task - including description, since this a single item read
		else
			$q-> table(array(MVC_Core::$db-> tab['tsk'],'tsk'), 'id,link_type,link_id'); // check if task id exists
		// This can only be used when link_type = "record"
		if(in_array('record', $wtr))
			$q-> table(array(MVC_Core::$db-> tab['iss'],'iss'), QueryFactory::autoalias_Add_Prefix("track,type_id,org_id,group_id", '_iss_'), 'left', '`tsk`.link_id = `iss`.id');
		// Disabled because assignee might be user/department/group of users, not just user
// 		if(in_array('assignee', $wtr))
// 			$q-> table(array(MVC_Core::$db-> tab['usr'],'usra'), QueryFactory::autoalias_Add_Prefix("id,name,email", '_usra_'), 'left', '`tsk`.assigned_to = `usra`.id');
		if(in_array('creator', $wtr))
			$q-> table(array(MVC_Core::$db-> tab['usr'],'usrc'), QueryFactory::autoalias_Add_Prefix("id,name,email", '_usrc_'), 'left', '`tsk`.added_by = `usrc`.id');
		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false; // task not found or database error
		$r-> index_field = 'id';
		$r-> split_by_prefixes = array(
			// extra tables
			'record'=> "_iss_",
// 				'assigned_to'=> "_usra_",
			'added_by'=> "_usrc_"
		);
		$task = $r-> current();
		// read history as well if requested
		// TODO: More flexibility here...
		if(in_array('updates', $wtr)) {
			$task['updates'] = array();
			$tsu_r = $this-> get_History_List($task_id);
			if($tsu_r) {
				foreach($tsu_r as $row)
					$task['updates'][] = $row;
			}
		}
		return $task;
	}
	/**
	 * Updates the task by modifying some of it's parameters (assign_to, assign_mode, assign_name, deadline, status, title, description, progress)
	 * -- link_type, link_id, added_by, dt - can't be modified - Tasks are locked to the same element after they've been created.
	 * @param int $task_id
	 * @param array $update_data
	 * @param int $update_by The user ID who makes the update (Defaults to 0, when no user is known - External or System updates)
	 * @param string $comment This comment is registered in the task updates (can be empty)
	 * @param bool $system_update If true, it means the update is not recorded in the history table ($update_by, $update_comment have no purpose)
	 * - (Also enables updating certain restricted values, for automated system operations)
	 * @return bool Update success
	 */
	public function update($task_id, $update_data, $update_by = 0, $comment = null, $system_update = false)
	{
		// filter out keys which do not belong in the tasks table (or can't be updated by this method)
		$fields = array(
			'assign_to', 'assign_mode', 'delegate_id', 'assign_name', 'deadline', 'reminder', 'ovd_intv', 'ovd_rep_limit',
			'action_ntf_type', 'status', 'title', 'description', 'progress','task_ref','task_type',
			// These are not coming from the UI, but they might be present in the update data
			'dl_action_id', 'rem_action_id', 'ovd_action_id', 'ovd_repeat', 'action_stage'
		);
		if($system_update)
			// Only changed by data correction functions, and not by a normal process
			array_push($fields,
				'org_id', 'link_type', 'link_id', 'added_by', 'dt'
			);
		foreach($update_data as $field=> $value)
			if(!in_array($field, $fields))
				unset($update_data[$field]);
		// Trim spaces before/after title/description, as well as length limit
		if(!empty($update_data['title']))
			$update_data['title'] = substr(trim($update_data['title']), 0, 200); // Limit length, these titles should not be monsters
		if(!empty($update_data['description']))
			$update_data['description'] = trim($update_data['description']);
		if(!empty($update_data['task_ref']))
			$update_data['task_ref'] = trim($update_data['task_ref']);	
		if(!empty($update_data['task_type']))
			$update_data['task_type'] = trim($update_data['task_type']);	
		// Certain user-editable fields are numbers (stored in history) - A little bit cleaner JSON storage
		if(isset($update_data['assign_to']))
			$update_data['assign_to'] = (int)$update_data['assign_to'];
		if(isset($update_data['ovd_intv']))
			$update_data['ovd_intv'] = (int)$update_data['ovd_intv'];
		if(isset($update_data['ovd_rep_limit']))
			$update_data['ovd_rep_limit'] = (int)$update_data['ovd_rep_limit'];
		if(isset($update_data['progress']))
			$update_data['progress'] = (int)$update_data['progress'];
		// update the table
                if(!empty($update_data)){ //IWI Create a files upload section in task
		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['tsk'])
		-> values($update_data)
		-> where("`id` = ?", $task_id)
		-> limit(1);
		$r = MVC_Core::$db-> query($q);
		// Error?
		if(!$r)
			return false;
                } //IWI Create a files upload section in task
		// System updates do not create history records
		if($system_update)
			return true;
		// Description is sliced to the first 1000 characters before storing in the History (not displayed anywhere yet)
		if(!empty($update_data['description']))
			$update_data['description'] = substr($update_data['description'], 0, 1000);
		$this-> history_Create($task_id, $update_by, $update_data, $comment);
		return true;
	}
	/**
	 * Deletes an existing task and all it's update history
	 * @param int $task_id
	 */
	public function delete($task_id)
	{
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['tsk'])
		-> where("`id` = ?", $task_id)
		-> limit(1);
		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return false;
		// success, also delete all updates
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['tsu'])
		-> where("`task_id` = ?", $task_id);
		MVC_Core::$db-> query($q);
		return true;
	}
//----- Lists -----//
	/**
	 * Gets the full list of tasks for an issue ID, user ID or status type, or even no filtering if no ID is specified
	 * -- By default, sorted by deadline, ascending (oldest first)
	 *
	 * @param array $wtr What to Read ? ( null, "task", "record", "creator", "assigned )
	 * @param Callback $qfo_filter If no callback specified, it simply reads all the tasks of the specified type_id
	 * @param array $filter_params
	 * @param string $order_by Which field to be used for ordering
	 * @param int $count_rows
	 * @param int $start_row
	 * @param bool $calc_found_rows Used for paging system
	 * @return MVC_Database_Result
	 */
	public function get_List($wtr = array('task'), $qfo_filter = null, $filter_params = array(), $order_by = null, $count_rows = 1000, $start_row = 0, $calc_found_rows = false)
	{
		$q = $this-> get_List_QueryFactory_Helper($qfo_filter, $filter_params, $order_by, $count_rows, $start_row, $calc_found_rows);
		// What to read?
		$wtr = self::wtr_Helper($wtr);
		if(in_array('task', $wtr))
			$q-> table(MVC_Core::$db-> table('tsk')); // read all fields (including description)
		elseif(in_array('tasklimited', $wtr))
			$q-> table(MVC_Core::$db-> table('tsk'), 'id,org_id,link_type,task_type,task_ref,link_id,assign_to,assign_mode,delegate_id,assign_name,added_by,dt,deadline,reminder,action_stage,status,title,progress'); // read all fields (excluding description and fields used for notifications)
		else
			$q-> table(MVC_Core::$db-> table('tsk'), 'id,org_id,link_type,task_type,task_ref,link_id,action_stage,status'); // read only the IDs of tasks and what items they are for
		// Valid only when Link Type = "record", link information about the attached record
		if(in_array('record', $wtr)) {
			$q-> where("`tsk`.link_type = 'record'");
			$q-> table(MVC_Core::$db-> table('iss'), QueryFactory::autoalias_Add_Prefix("track,cache_primary,cache_secondary", '_iss_'), 'left', '`tsk`.link_id = `iss`.id'); // cache_tertiary,cache_quaternary,cache_quinary
			$q-> table(MVC_Core::$db-> table('ist'), QueryFactory::autoalias_Add_Prefix("id,key,name", '_ist_'), 'left', '`iss`.type_id = `ist`.id');
			$q-> table(MVC_Core::$db-> table('grp'), QueryFactory::autoalias_Add_Prefix("id,type,name", '_grp_'), 'left', '`grp`.id = `iss`.group_id');
		}
		// Link the user that defined the task
		if(in_array('creator', $wtr))
			$q-> table(array(MVC_Core::$db-> tab['usr'],'usr'), QueryFactory::autoalias_Add_Prefix("id,name,email,contact_id", '_cre_'), 'left', '`tsk`.added_by = `usr`.id');
		// Link the user which is delegated for department tasks (if one exists)
		if(in_array('delegate', $wtr))
			$q-> table(array(MVC_Core::$db-> tab['usr'],'dlg'), QueryFactory::autoalias_Add_Prefix("name,email,contact_id", '_dlg_'), 'left', '`tsk`.delegate_id = `dlg`.id');
		// Link the organization owning the task
		if(in_array('org', $wtr))
			$q-> table(MVC_Core::$db-> table('org'), QueryFactory::autoalias_Add_Prefix("id,type_id,name,contact_id", '_org_'), 'left', '`tsk`.org_id = `org`.id');
		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false; // error or empty result
		$r-> index_field = 'id';
		$r-> split_by_prefixes = array(
			// extra tables
			'added_by'=> "_cre_",
			'delegate'=> "_dlg_",
			'org'=> "_org_",
			// Only when reading record-related tasks
			'rec'=> "_iss_",
			'rt'=> "_ist_",
			'group'=> "_grp_"
		);
		// After running the main query, grab and store "found_rows"
		if($calc_found_rows)
			$this-> get_List_Save_FoundRows();
		// Read and prepare the primary tasks data
		$tasks = array();
		// ... also make ID lists of tasks with assign_mode "user" or "dep" (these will not be used when $wtr doesn't contain "assigned")
		$assig_list = array(
			'user'=> array(),
			'dep'=> array(),
			'usrgrp'=> array()
		);
		foreach($r as $row) {
			// A bit of post-processing
			$row['id'] = (int)$row['id'];
			$row['org_id'] = (int)$row['org_id'];
			$row['link_id'] = (int)$row['link_id'];
			if(isset($row['assign_to']))
				$row['assign_to'] = (int)$row['assign_to'];
			if(isset($row['delegate_id']))
				$row['delegate_id'] = (int)$row['delegate_id'];
			if(isset($row['progress']))
				$row['progress'] = (int)$row['progress'];
			if(in_array('creator', $wtr))
				if(!is_null($row['added_by']['id']))
					$row['added_by']['id'] = (int)$row['added_by']['id'];
			if(in_array('assigned', $wtr)) {
				if(!empty($row['assign_to']) && !in_array($row['assign_to'], $assig_list[$row['assign_mode']]))
					$assig_list[$row['assign_mode']][] = (int)$row['assign_to'];
			} elseif(in_array('task', $wtr) || in_array('tasklimited', $wtr)) {
				$row['assign'] = array(
					'id'=> (int)$row['assign_to']?: null,
					'name'=> $row['assign_name']
				);
				unset($row['assign_name']);
			}
                         $row['task_ref']=$row['task_ref'];
                        $row['task_ref_link']='<a target="_blank" href="/record/process:'.$row['task_ref'].'">'.$row['task_ref'].'</a>';
			$tasks[] = $row;
		}
		// If we have "assigned", grab data and inject it
		if(in_array('assigned', $wtr)) {
			if(count($assig_list['user'])) {
				// User mode assign - Read list of users
				$q2=QueryFactory::select()
				-> table(MVC_Core::$db-> tab['usr'], 'id,name,email,contact_id')
				-> idlist_Filter($assig_list['user'], "id");
				$r2 = MVC_Core::$db-> query($q2);
				if($r2 && count($r2)) {
					QueryFactory::idlist_Cleanup(count($assig_list['user']));
					$assig_list['user'] = array(); // Started as ID list, will contain actual user data
					foreach($r2 as $as_row) {
						$assig_list['user'][(int)$as_row['id']] = $as_row;
					}
				}
			}
			if(count($assig_list['dep'])) {
				// Department mode assign - Read list of departments
				$q3=QueryFactory::select()
				-> table(MVC_Core::$db-> tab['dep'], 'id,name,count_members,contact_id')
				-> idlist_Filter($assig_list['dep'], "id");
				$r3 = MVC_Core::$db-> query($q3);
				if($r3 && count($r3)) {
					QueryFactory::idlist_Cleanup(count($assig_list['dep']));
					$assig_list['dep'] = array(); // Started as ID list, will contain actual department data
					foreach($r3 as $as_row) {
						$assig_list['dep'][(int)$as_row['id']] = $as_row;
					}
				}
			}
			// ... data grabbed, inject into $tasks from the $assig_list arrays
			foreach($tasks as &$task)
				if($task['assign_to']) {
					// Check if the user or department still exists (appears in $assig_list[$task['assign_mode']]). If not, silently update the task to remove the missing ID
					if(!isset($assig_list[$task['assign_mode']][$task['assign_to']])) {
						$this-> update($task['id'], array(
							'assign_mode'=> 'user',
							'assign_to'=> null
						), 0, null, true); // true = silent update
						$task['assign_mode'] = null;
						$task['assign_to'] = null;
					} else
						$task['assign'] = $assig_list[$task['assign_mode']][$task['assign_to']];
				}
		}
		return $tasks;
	}
	/**
	 * Updates multiple tasks by modifying parameters (user side, see list of $fields)
	 * -- link_type, link_id, added_by, dt - can't be modified - Tasks are locked to the same element after they've been created.
	 * -- Every task that match is updated with the same values.
	 * >> This doesn't create "History" for tasks! (Like normal single update with $system_update)
	 *
	 * @param [Callback] $query_callback Manadatory callback function which must set some conditions or ALL system tasks are updated
	 * @param array $update_data
	 * @return int Affected rows
	 */
	public function update_Bulk($query_callback, $update_data)
	{
		if(!is_callable($query_callback))
			return false;
		// filter out keys which do not belong in the tasks table (or can't be updated by this method)
		$fields = array(
			'assign_to', 'assign_mode', 'assign_name', 'deadline', 'reminder', 'ovd_intv', 'ovd_rep_limit',
			'action_ntf_type', 'status', 'title', 'description', 'progress' , 'task_type' , 'task_ref' 
		);
		foreach($update_data as $field=> $value)
			if(!in_array($field, $fields))
				unset($update_data[$field]);
		// Anti-Raoul filter
		if(!empty($update_data['title']))
			$update_data['title'] = substr(trim($update_data['title']), 0, 200); // Limit length, these titles should not be monsters
		if(!empty($update_data['description']))
			$update_data['description'] = trim($update_data['description']);
		if(!empty($update_data['task_type']))
			$update_data['task_type'] = trim($update_data['task_type']);
		if(!empty($update_data['task_ref']))
			$update_data['task_ref'] = trim($update_data['task_ref']);
		// update the table
		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['tsk'])
		-> values($update_data);
		$cb_r = call_user_func($query_callback, $q);
		if(empty($cb_r))
			return false; // Callback must return TRUE for the update to proceed !
		$r = MVC_Core::$db-> query($q);
		if(!$r)
			// error, failed to update tasks
			return false;
		return MVC_Core::$db-> affected_rows;
	}
	/**
	 * Task counter for a link element + optionally organization filter (all notes related to a certain element which were created by users of one organization)
	 * - Returns an array with first element being the total number of tasks found, and the second the number of active ones. Returns "false" if no tasks were found or there was an error.
	 *
	 * @param string $link_type
	 * @param int $link_id Link ID to search - If missing, counts all tasks (including unlinked)
	 * @param int $org_id
	 * @return array|bool
	 */
	public function count($link_type, $link_id = null, $org_id = null)
	{
		if(!in_array($link_type, self::$allowed_linked_type))
			return false; // error, linked_with not specified or invalid value
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['tsk'], array('id', 'status'))
		-> where("`link_type` = ?", $link_type);
		if(!empty($link_id))
			$q-> where("`link_id` = ?", $link_id);
		if(!empty($org_id))
			$q-> where("`org_id` = ?", $org_id);
		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return false; // Could not run the query ?
		$total = 0; $active = 0;
		foreach($r as $row) {
			$total++;
			if(!in_array($row['status'], array('completed', 'failed', 'expired', 'closed'))) // closed = hidden
				$active++;
		}
		return array($total, $active);
	}
	/**
	 * Creates an entry in the Task Updates (History of the task)
	 * @param int $task_id
	 * @param int $update_by ID of the user who updated the task (or 0 if the update is triggered by a non-user process)
	 * @param array $update_data What is placed into "update_data"
	 * @param string $comment Optional
	 */
	public function history_Create($task_id, $update_by, $update_data = null, $comment = null)
	{
		// update was successful, record the update history.
		$update_record = array(
			'task_id'=> $task_id,
			'update_by'=> $update_by
		);
		// status change?
		if(isset($update_data['status'])) {
			$update_record['status_change'] = $update_data['status'];
			unset($update_data['status']);
		}
		if(!empty($update_data))
			$update_record['update_data'] = json_encode($update_data);
		// comment on the update?
		if(!empty($comment))
			$update_record['comment'] = substr(trim($comment), 0, 250);
		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['tsu'])
		-> values($update_record);
		return MVC_Core::$db-> query($q);
	}
	/**
	 * Reads the update history (which contain notes) for a task id
	 * TODO: Rework this to have the ability to also read updates from all tasks, but for a specific user or organization (a more general List method)
	 * -- TODO: Supporting paging would also be nice, like main get_List method
	 *
	 * @param int $task_id
	 * @return array
	 */
	public function get_History_List($task_id, $wtr = array('update', 'user'))
	{
		$q=QueryFactory::select()
		-> where("`task_id` = ?", $task_id)
		-> order('`dt` DESC');
		if(in_array('update', $wtr))
			$q-> table(array(MVC_Core::$db-> tab['tsu'],'tsu')); // all fields
		else
			$q-> table(array(MVC_Core::$db-> tab['tsu'],'tsu'), 'status_change'); // read just the status change if the "update" entry is not in $wtr
		// User which performed the update
		if(in_array('user', $wtr))
			$q-> table(array(MVC_Core::$db-> tab['usr'],'usr'), QueryFactory::autoalias_Add_Prefix("id,name,email", '_usr_'), 'left', '`tsu`.update_by = `usr`.id');
		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return false;
		$r-> split_by_prefixes = array(
			// extra tables
			'user'=> "_usr_",
		);
		return $r;
	}
	/**
	 * Deletes all tasks and task histories for a certain link (Type and ID)
	 * -- Used only when a linked element is deleted, so data is properly cleaned
	 * @param string $link_type
	 * @param int $link_id
	 */
	public function delete_All($link_type, $link_id)
	{
		// FIXME: Review this method if necessary. Currently it's not used.
		// first, read the list of tasks for an issue to get the IDs
// 		$tasks = $this-> get_List(array('task'), null, null, null, 0); // read all (no limit)
		// $link_type, $link_id, 'link_id',
// 		$id_list = array();
// 		foreach($tasks as $task) {
			// TODO: Update count_tasks for the users which have received these tasks
// 			$task['assigned_to']
// 			$id_list[] = $task['id'];
// 		}
		// Delete the tasks
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['tsk'])
		-> where("`link_type = ?, $link_type")
		-> where("`link_id` = ?", $link_id);
		MVC_Core::$db-> query($q);
		// TODO: Clean the history elements
		// TODO: Clean any linked actions
// 		if(!empty($id_list)) {
// 			// third, delete the history for all of them
// 			$q=QueryFactory::delete()
// 			-> table(MVC_Core::$db-> tab['tsu'])
// 			-> where("`task_id` IN(?)", $id_list);
// 			MVC_Core::$db-> query($q);
// 		}
	}
	public function get_Task_Count(){
		$wtr=array("creator");
	    $q=array();
		$q=QueryFactory::select();
	    $q-> table(MVC_Core::$db-> table('tsk'),'count(tsk.status) statusCount');
	    $q-> where("`tsk`.link_type = 'record'");
		$user = new App_Model_User();
		if(isset(MVC_Core::$ss-> user['status'])&& MVC_Core::$ss-> user['status']=='admin')
			$q-> where("`tsk`.assign_mode = 'user' AND `tsk`.assign_to = ? AND `tsk`.org_id =? ",MVC_Core::$ss-> user['id'],MVC_Core::$ss-> user['org_id']);
		else
			$q-> where("`tsk`.assign_mode = 'user' AND `tsk`.assign_to = ? ",MVC_Core::$ss-> user['id']);			
		$q-> where("`tsk`.status = 'new' OR `tsk`.status = 'assigned' OR `tsk`.status = 'inprogress' OR `tsk`.status = 'onhold'");
	    $q-> table(MVC_Core::$db-> table('iss'), QueryFactory::autoalias_Add_Prefix(false, '_iss_'), 'left', '`tsk`.link_id = `iss`.id'); // cache_tertiary,cache_quaternary,cache_quinary
	    $q-> table(MVC_Core::$db-> table('ist'), QueryFactory::autoalias_Add_Prefix(false, '_ist_'), 'left', '`iss`.type_id = `ist`.id');
	    $q-> table(MVC_Core::$db-> table('grp'), QueryFactory::autoalias_Add_Prefix(false, '_grp_'), 'left', '`grp`.id = `iss`.group_id');
		if(in_array('creator', $wtr))
	        $q-> table(MVC_Core::$db-> table('usr'), QueryFactory::autoalias_Add_Prefix(false, '_usr_'), 'left', '`tsk`.added_by = `usr`.id');
      /*  if(in_array('org', $wtr))
            $q-> table(MVC_Core::$db-> table('org'), QueryFactory::autoalias_Add_Prefix(false, '_org_'), 'left', '`tsk`.org_id = `org`.id');	*/
		$r = MVC_Core::$db-> query($q); 
		$taskCount = $r->current();
		$taskCoCountDetails=$taskCount['statusCount']; 
		if($taskCoCountDetails == ""||$taskCoCountDetails<=0){
			return 0;
		}
		return $taskCoCountDetails;
	}
    /**
     * Update those task where user is disabled/invisible
     * @return bool
     */
    public function check_Inactive_Users_Task(){
        try{
            $now = new DateTime();
            $ovdArr = array();
            $remArr = array();
            $dlArr = array();
            $taskarr = array();
            $dataJson = array();
            $q = MVC_Core::$db->query("select t.id as ts_id,t.org_id,t.link_id,u.id as us_id ,u.status,t.status,t.assign_to,t.action_stage,t.ovd_action_id,t.rem_action_id,t.dl_action_id  from tasks t ,users u where  u.id = t.assign_to  and (u.status = 'invisible' or u.status ='disabled') and t.action_stage != 'end' limit 50");
            if(!$q || count($q)==0)
                return false;// failed to read any records
            if($q){
                foreach ($q as $row){
                    if(!is_null($row['ovd_action_id']) && $row['ovd_action_id'] > 0){
                        $ovdArr[]= $row['ovd_action_id'];
                    }
                    if(!is_null($row['rem_action_id']) && $row['rem_action_id'] > 0){
                        $remArr[]= $row['rem_action_id'];
                    }
                    if(!is_null($row['dl_action_id']) && $row['dl_action_id'] > 0){
                        $dlArr[]= $row['dl_action_id'];
                    }
                    $ouput = array_merge($ovdArr,$remArr);
                    $outputFinal = array_merge($ouput,$dlArr);
                    if($row['status'] != 'completed' && $row['status'] != 'expired' && $row['status'] != 'closed'){
                        $dataJson['type'] = 'email';
                        $dataJson['mode'] = 'user';
                        $dataJson['to'] = $row['us_id'];
                        $dataJson['by'] = $row['us_id'];
                        $dataJson['link']['type'] = 'record';
                        $dataJson['link']['id'] = $row['link_id'];
                        MVC_Core::$db->query("INSERT INTO actions (org_id, create_dt, trigger_dt, executor, operation, creator_id, data_json,comment) VALUES ('". $row['org_id']."','".$now-> format("Y-m-d H:i:s")."','".$now-> format("Y-m-d H:i:s")."','task','failure','".$row['ts_id']."','".json_encode($dataJson)."','')");
                        MVC_Core::$db->query("UPDATE tasks t SET t.status='failed',t.action_stage = 'end', t.ovd_repeat = NULL, t.rem_action_id = -2,t.ovd_action_id = -2,dl_action_id = -2, ovd_intv = NULL, reminder = NULL where t.id = ".$row['ts_id']."");
                    }else{
                        $taskarr[] = $row['ts_id'];
                    }
                }
                if(count($outputFinal) > 0){
                    MVC_Core::$db->query("DELETE FROM actions  WHERE id IN (".implode(",",$outputFinal).")");
                }
                if(count($taskarr) > 0){
                    MVC_Core::$db->query("UPDATE tasks t SET t.action_stage = 'end', t.ovd_repeat = NULL, t.rem_action_id = -2,t.ovd_action_id = -2,dl_action_id = -2, ovd_intv = NULL, reminder = NULL where t.id IN (".implode(",",$taskarr).")");
                }
            }
        }catch (Exception $e){
            echo 'Message: ' .$e->getMessage();
        }
        return true;
    }
    /**
     * Update ovd task where task status is completed/failed but the action stage is still ovd
     * @param null $linkId
     * @return bool
     */
    public function check_Ovd_task($linkId = null){
        try{
            $now = new DateTime();
            $ovdArr = array();
            $remArr = array();
            $dlArr = array();
            $taskarr = array();
            $dataJson = array();
            if(is_null($linkId)){
                $q = MVC_Core::$db->query("select t.id as ts_id,t.org_id,t.assign_to,t.link_id,t.status,t.assign_to,t.action_stage,t.ovd_action_id,t.rem_action_id,t.dl_action_id  from tasks t where  (t.status ='failed' or t.status ='closed' or t.status = 'expired' or t.status = 'completed') and t.action_stage = 'ovd' limit 50");
            }else{
                $q = MVC_Core::$db->query("select t.id as ts_id,t.org_id,t.assign_to,t.link_id,t.status,t.assign_to,t.action_stage,t.ovd_action_id,t.rem_action_id,t.dl_action_id  from tasks t where t.link_id = ".$linkId. " ");
            }
            if(!$q || count($q)==0)
                return false;// failed to read any records
            if($q){
                foreach ($q as $row){
                    if(!is_null($row['ovd_action_id']) && $row['ovd_action_id'] > 0){
                        $ovdArr[]= $row['ovd_action_id'];
                    }
                    if(!is_null($row['rem_action_id']) && $row['rem_action_id'] > 0){
                        $remArr[]= $row['rem_action_id'];
                    }
                    if(!is_null($row['dl_action_id']) && $row['dl_action_id'] > 0){
                        $dlArr[]= $row['dl_action_id'];
                    }
                    $ouput = array_merge($ovdArr,$remArr);
                    $outputFinal = array_merge($ouput,$dlArr);
                    if($row['status'] != 'completed' && $row['status'] != 'expired' && $row['status'] != 'closed'){
                        $dataJson['type'] = 'email';
                        $dataJson['mode'] = 'user';
                        $dataJson['to'] = $row['assign_to'];
                        $dataJson['by'] = $row['assign_to'];
                        $dataJson['link']['type'] = 'record';
                        $dataJson['link']['id'] = $row['link_id'];
                        MVC_Core::$db->query("INSERT INTO actions (org_id, create_dt, trigger_dt, executor, operation, creator_id, data_json,comment) VALUES ('". $row['org_id']."','".$now-> format("Y-m-d H:i:s")."','".$now-> format("Y-m-d H:i:s")."','task','failure','".$row['ts_id']."','".json_encode($dataJson)."','')");
                        MVC_Core::$db->query("UPDATE tasks t SET t.status='failed',t.action_stage = 'end', t.ovd_repeat = NULL, t.rem_action_id = -2,t.ovd_action_id = -2,dl_action_id = -2, ovd_intv = NULL, reminder = NULL where t.id = ".$row['ts_id']." ");
                    }else{
                        $taskarr[] = $row['ts_id'];
                    }
                }
                if(count($outputFinal) > 0){
                    MVC_Core::$db->query("DELETE FROM actions  WHERE id IN (".implode(",",$outputFinal).")");
                }
                if(count($taskarr) > 0){
                    MVC_Core::$db->query("UPDATE tasks t SET t.action_stage = 'end', t.ovd_repeat = NULL, t.rem_action_id = -2,t.ovd_action_id = -2,dl_action_id = -2, ovd_intv = NULL, reminder = NULL where t.id IN (".implode(",",$taskarr).")");
                }
            }
        }catch (Exception $e){
            echo 'Message: ' .$e->getMessage();
        }
        return true;
    }
	public function getCompletedDate_TaskStatus($taskId){
		$completed = "completed";
		$q = QueryFactory::select()->table(MVC_Core::$db->tab['tsu'])->where("`task_id`=?", $taskId)->where("`status_change`=?",$completed);
		$r =  MVC_Core::$db->query($q)->current();
		return $r;
	}
        
        
         //        IWI Create a files upload section in task start  
    public function taskFileMapping($mappingData) {
//        print_r($mappingData);

        if (count($mappingData['file']) > 0) {
            foreach ($mappingData['file'] as $flRow) {
                if ($flRow['name'] != '') {
                    $task_files_data = array(
                        'user_id' => $mappingData['user_id'],
                        'task_id' => $mappingData['task_id'],
                        'file_id' => $flRow['id'],
                    );
                    $q = QueryFactory::insert()
                            ->table('task_files')
                            ->values($task_files_data);
                    $r = MVC_Core::$db->query($q);
                }
            }
        }
    }

    //        IWI Create a files upload section in task end  
    
    
    

//file_images show in the task tab _start
    public function deleteTaskFile($file_id) {
        $q = QueryFactory::delete()
                ->table('task_files')
                ->where("`file_id` = ?", $file_id)
                ->limit(1);
        $r = MVC_Core::$db->query($q);
        if (!$r) {
            return false;
        } else {
            return true;
        }
    }

//file_images show in the task tab _end

//    IWI doc section file upload start
    public function updateAttachFiles($sendAttachedData = array()) {
		$q_del=QueryFactory::delete()
		-> table('task_files')
		-> where("`task_id` = ?", $sendAttachedData['task_id']);
		// echo $q_del;
		MVC_Core::$db-> query($q_del);
        
		foreach($sendAttachedData['selectedAttachIds'] as $file_id){
		$data = array(
            'user_id' => $sendAttachedData['user_id'],
            'task_id' => $sendAttachedData['task_id'],
            'file_id' => $file_id,
        );
		 $q_file = QueryFactory::insert()
                    ->table('task_files')
                    ->values($data);
            $r_file = MVC_Core::$db->query($q_file);
		}
       
        if (!$r || count($r) == 0) {
            return MVC_Core::$debug->dump("Error on creating a note/document", __METHOD__, 'error');
        } else {
            return true;
        }
    }

//    IWI doc section file upload end
    
    
    
    
    
//    IWI show description update history start

    public function create_task_log($task_id) {
        $task_data = $this->read($task_id);
        $task_data['task_id'] = $task_id;
        $current_description = $task_data['description'];
        unset($task_data['id']);

        $q_last_data = QueryFactory::select()->table('task_update_log')
                        ->where("`task_id` = ?", $task_id)
                        ->order('`id` DESC')->limit(1);
        $r_last_data = MVC_Core::$db->query($q_last_data)->current();

        if (trim($r_last_data['description']) === trim($current_description)) {
            return true;
        } else {
            $q = QueryFactory::insert()
                    ->table('task_update_log')
                    ->values($task_data);
            $r = MVC_Core::$db->query($q);
            if (!$r)
                return false;
            $iid = MVC_Core::$db->insert_id;
            return $iid;
        }
    }

    public function get_task_log($task_id) {
        $q = QueryFactory::select()->table('task_update_log')
                ->where("`task_id` = ?", $task_id)
                ->order('`id` DESC');

        $r = MVC_Core::$db->query($q);
        if (!$r)
            return false;

        return $r;
    }

//        IWI show description update history end
    
//    IWI All Task grid changes 16072026 start
    public function updateBulkTaskStatus($task_ids,$update_data){
    $q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['tsk'])
		-> values($update_data)
		-> where("`id` IN (?)", $task_ids);
//    echo $q;exit();
		$r = MVC_Core::$db-> query($q);
                if (!$r)
            return false;

        return $r;
    }
//    IWI All Task grid changes 16072026 end
}