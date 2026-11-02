<?php
/**
 * "Organization" related data access and input/output processing
 * -- A department is a subdivision of an organization, and is closely related to it.
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoClaims
 * @version 1.0.dev
 */
class App_Model_Department extends MVC_Model
{
	//----- Organization departments CRUD -----//

	/**
	 * Adds an organization department to the system and associate it with it's parent organization
	 *
	 * @param int $org_id
	 * @param array $dep_data Name, department chief ID, Optional override of organization ACLs, others...
	 * @param bool $encoded Indicates if $new_dep_data's fields are already encoded (join, json_encode, etc.)
	 */
	public function create($org_id, $dep_data = array(), $encoded = false)
	{ 
		// First, check if the organization ID actually exists, return error if not
		$m_org = new App_Model_Organization();
		$org = $m_org-> read($org_id);
		if($org===false) {
			trigger_error("department::create|Invalid organization ID", E_USER_WARNING);
			return false; // Error, invalid organization
		}

		if(isset($org['features']['departments']) && $org['features']['departments'] === false) {
			trigger_error("department::create|Organization is not allowed to use departments", E_USER_WARNING);
			return false; // Error, this organization has the departments feature disabled
		}

		if(isset($org['features']['limits']['departments']) && $org['features']['departments'] >= $org['features']['limits']['departments']) {
			trigger_error("department::create|Maximum number of departments reached", E_USER_NOTICE);
			return false; // Error, this organization already has the maximum number of departments created, can't create another
		}

		// test mandatory fields
		$mandatory_fields = array('name');
		$diff = array_diff($mandatory_fields, array_keys($dep_data));
		if(!empty($diff)) {
			trigger_error("department::create|One or more mandatory fields missing from $dep_data", E_USER_WARNING);
			return $diff; // error, not all fields available in $org_data (the missing fields are returned)
		}

		// any key outside mandatory+optional fields is discarded (to avoid database errors)
		$fields = array_merge($mandatory_fields, array('chief_id', 'privileges_or', 'access_ids_or', 'contact_id', 'ist_or', 'listed', 'use4ist'));
		foreach($dep_data as $field=> $value)
			if(!in_array($field, $fields))
				unset($dep_data[$field]);

		// Pre-Process (encode)
		if(!$encoded) {
			// These are the department-wide privilege list (simply strings). Can be overridden by user-specific list
			// Depending on how the department is configured, it's members might be more "free" or more restricted.
			if(isset($dep_data['privileges_or']) && is_array($dep_data['privileges_or']))
				$dep_data['privileges_or'] = implode(',', $dep_data['privileges_or']);

			// The access ids is a keyed array containing arrays of values - It's stored as is, and can be overwritten by per-user settings.
			if(isset($dep_data['access_ids_or']))
				$dep_data['access_ids_or'] = json_encode($dep_data['access_ids_or']);
		}

		$dep_data['org_id'] = $org_id;
		if(!empty($dep_data['chief_id']))
			$dep_data['count_members'] = 1; // set to 1 when chief is specified
		else
			unset($dep_data['chief_id']);

		// prepare query
		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['dep'])
		-> values($dep_data);

		// and run it
		$r = MVC_Core::$db-> query($q);
		if(!$r) {
			trigger_error("department::create|Unable to create department record", E_USER_WARNING);
			return false; // Error, could not insert the department record (possibly database error?)
		}

		$iid = MVC_Core::$db-> insert_id;

		// Update the organization so the number of departments is increased
		if(isset($org['features']['departments']))
			$org['features']['departments']++;
		else
			$org['features']['departments'] = 1;

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['org'])
		-> values(array('features'=> json_encode($org['features'])))
		-> where("`id` = ?", $org_id)
		-> limit(1);
		$r = MVC_Core::$db-> query($q);
		if(!$r) {
			trigger_error("department::create|Failed to update organization department count", E_USER_NOTICE);
		}

		if(!empty($dep_data['chief_id'])) {
			// Add the Chief as a member of the department
			$r = $this-> attach_User_to_Dep($iid, $dep_data['chief_id'], array(), false); // false = skip an unnecessary update query
			if(!$r)
				trigger_error("department::create|Failed to attach chief to department", E_USER_NOTICE);
		}

		// Return the Insert ID (Non-critical errors might have occured, but department was created)
		return $iid;
	}

	/**
	 * Reads information about a department
	 *
	 * @param int $dep_id The department's database ID
	 * @param array $wtr Indicates what information to read ( if null or empty, reads only id/name )
 	 * @param bool $decode Decode fields ( can be disabled, for example when information is sent directly to JS so decoding can take place there instead )
	 * @return array
	 */
	public function read($dep_id, $wtr = array('department'), $decode = true)
	{
		if(!$dep_id)
			return false; // Error, the department id must exist and be non-null

		$q=QueryFactory::select()
		-> where("`dep`.`id` = ?", $dep_id)
		-> limit(1);

		$wtr = self::wtr_Helper($wtr);

		if(in_array('department', $wtr))
			$q-> table(MVC_Core::$db-> table('dep')); // all fields in the primary table
		else
			$q-> table(MVC_Core::$db-> table('dep'), 'id,name'); // read just id, name

		if(in_array('chief', $wtr))
			$q-> table(MVC_Core::$db-> table('usr'), QueryFactory::autoalias_Add_Prefix("name,email,contact_id,status,inbox_id,sentbox_id", '_usr_'), 'left', '`dep`.chief_id = `usr`.id');

		if(in_array('organization', $wtr))
			$q-> table(MVC_Core::$db-> table('org'), QueryFactory::autoalias_Add_Prefix("type_id,name", '_org_'), 'left', '`dep`.org_id = `org`.id'); // ,features ?? Needed ?

		$r = MVC_Core::$db-> query($q);
		if($r && count($r)==1) {
			$r-> split_by_prefixes = array(
				// tidy up
				'count'=> "count_",
				// extra table(s)
				'chief'=> "_usr_",
				'organization'=> "_org_"
			);

			$dep = $r-> current();

			// Post-Process
			$dep['id'] = (int)$dep['id'];

			if(in_array('department', $wtr) && $decode) {
				if(!empty($dep['privileges_or']))
					$dep['privileges_or'] = explode(",", $dep['privileges_or']);

				if(!empty($dep['access_ids_or']))
					$dep['access_ids_or'] = @json_decode($dep['access_ids_or'], true);

				if(!empty($dep['ist_or']))
					$dep['ist_or'] = @json_decode($dep['access_ids_or'], true);

				// Explode the use4 CSV's into arrays (use4grp is not mandatory, and when empty it means - accepts all data groups)
				if(!empty($dep['use4ist']))
					$dep['use4ist'] = explode(',', $dep['use4ist']);
				else
					$dep['use4ist'] = array();
				if(!empty($dep['use4grp'])) {
					$dep['use4grp'] = explode(',', $dep['use4grp']);
					foreach($dep['use4grp'] as &$grp_id)
						$grp_id = (int)$grp_id;
				}
			}

			// Load a list of department members if requested (u2d table)
			if(in_array('members', $wtr)) {
				$dep['members'] = $this-> get_Dep_User_List($dep_id);
				if(!$dep['members'])
					unset($dep['members']);
			}

			return $dep;
		}
		// not found !
		return false;
	}

	/**
	 * Updates department information
	 *
	 * @param int $dep_id
	 * @param array $new_dep_data
	 * @param bool $encoded Indicates if $new_dep_data's fields are already encoded (join, json_encode, etc.)
	 * @param string $string_expressions This parameter is sent to query factory, and is used to disable string escaping (in order to allow expressions, for example to increment a counter)
	 */
	public function update($dep_id, $new_dep_data = array(), $encoded = false, $string_expressions = false)
	{
		// filter out keys which do not belong in the organizations table (or can't be updated by this method)
		$fields = array('name', 'privileges_or','chief_id', 'access_ids_or', 'count_members', 'count_ctx', 'contact_id', 'ist_or', 'listed', 'use4ist', 'use4grp');
		foreach($new_dep_data as $field=> $value)
			if(!in_array($field, $fields))
				unset($new_dep_data[$field]);

		// pre-process (encode)
		if(!$encoded) {
			if(isset($new_dep_data['privileges_or']))
				$new_dep_data['privileges_or'] = implode(',', $new_dep_data['privileges_or']);

			if(isset($new_dep_data['access_ids_or']))
				$new_dep_data['access_ids_or'] = json_encode($new_dep_data['access_ids_or']);

			// TODO: Encode the other fields
		}

		// Fix for "true" = 1, "false" = 0
		if(!empty($new_dep_data['listed']))
			$new_dep_data['listed'] = $new_dep_data['listed']=='true';

		// update departments table
		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['dep'])
		-> values($new_dep_data, null, $string_expressions)
		-> where("`id` = ?", $dep_id)
		-> limit(1);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Deletes a department from an organization
	 * -- First it sets the active department of all users which have this department as current to be 0 (none, inherit only the company's privileges/access_ids)
	 * -- Disconnects all users from this department in ( u2d ). This will remove any privileges/access_ids overrides for the users in that department (and other customizations which might be present in that data)
	 * -- Removes the department record itself
	 * -- Also updates the organization to reduce the count in "features" field
	 *
	 * @param int $dep_id
	 * @return array Returns some information about the just-removed department (name, chied_id, how many members it had + list of users that had it as active dep) - or false if the department ID was not found
	 */
	public function delete($dep_id)
	{
		// Try to read the department to see if it actually exists (and also some data about the organization it's part of)
		$q=QueryFactory::select()
		-> table(array(MVC_Core::$db-> tab['dep'], 'dep'), 'id,name,chief_id,count_members')
		-> table(array(MVC_Core::$db-> tab['org'], 'org'), QueryFactory::autoalias_Add_Prefix("id,type_id,name,features", '_org_'), 'left', '`dep`.org_id = `org`.id')
		-> where("`dep`.`id` = ?", $dep_id)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if(!$r) {
			trigger_error("department::delete|Department ID not found", E_USER_WARNING);
			return false; // Error, department not found (possibly database error?)
		}

		$r-> split_by_prefixes = array(
			// extra table(s)
			'org'=> "_org_"
		);
		$dep = $r-> current();
		// decode the org "features" field
		$dep['org']['features'] = @json_decode($dep['org']['features'], true);

		// When there are members, more processing is necessary
		if($dep['count_members'] > 0) {
			// Get the list of users that are connected to this department (The Members)
			$q=QueryFactory::select()
			-> table(array(MVC_Core::$db-> tab['u2d'], 'u2d'), false)
			-> table(array(MVC_Core::$db-> tab['usr'], 'usr'), "id,status,cdep_id,name,email,language,inbox_id", 'left', '`u2d`.user_id = `usr`.id')
			-> where("`u2d`.`dep_id` = ?", $dep_id);

			$r = MVC_Core::$db-> query($q);
			if($r && count($r)> 0) {
				$members_cdep = array();
				foreach($r as $u2d) {
					// If a member has this department as it's active one, add him to a list of members that might need a notification that their active department has been deleted
					if($u2d['cdep_id'] == $dep_id) {
						$members_cdep[] = $u2d;

						// Update this member, set their active department to be 0 - At next logon they should get a different set of access rights if this department had ACLs overrides, or the users had ACLs overrides themselves
						$q=QueryFactory::update()
						-> table(MVC_Core::$db-> tab['usr'])
						-> values(array(
							'cdep_id'=> 0
						))
						-> where("`id` = ?", $u2d['id']);
						MVC_Core::$db-> query($q);
					}
				}
				$dep['members_cdep'] = $members_cdep;

				// Remove all the records from u2d referring to this department (they won't be valid anymore since the dep_id disappears) - This will erase any ACLs overrides for the users in this department
				$q=QueryFactory::delete()
				-> table(MVC_Core::$db-> tab['u2d'])
				-> where('`dep_id` = ?', $dep_id);
				MVC_Core::$db-> query($q);
			} else
				trigger_error("department::delete|Unable to read the department member list or no members were found although the department's count_members is non zero.", E_USER_NOTICE);
		}

		// Remove the department record itself
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['dep'])
		-> where('`id` = ?', $dep_id);
		$r = MVC_Core::$db-> query($q);

		if(!$r) {
			trigger_error("department::delete|Unable to delete department, possible database error", E_USER_WARNING);
			return false; // Error, department could not be deleted
		}

		// Update the organization record, field "features", to reduce the department count
		$dep['org']['features']['departments']--;

 		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['org'])
		-> values(array('features'=> json_encode($dep['org']['features'])))
		-> where("`id` = ?", $dep['org']['id'])
		-> limit(1);
		$r = MVC_Core::$db-> query($q);
		if(!$r) {
			trigger_error("department::create|Failed to update organization department count", E_USER_NOTICE);
		}

		// Finally, return the data about the just-deleted department
		return $dep;
	}

	/**
	 * Reads the list of departments for the specified organization (or false if the organization doesn't use departments - or has none defined yet)
	 *
	 * @param int $org_id Organization ID
	 * @param array $wtr Indicates what information to read ( if null or empty, reads only id/name )
	 * @param Callback $qfo_filter If no callback specified, it simply reads all the departments in the organization
	 * @param array $filter_params
	 * @param string $order_by Which field to be used for ordering - Defaults to order by name, alphabetically ascending
	 * @param int $count_rows Paging Size
	 * @param int $start_row Paging Start
	 * @return MVC_Database_Result (iterable)
	 */
	public function get_List($org_id, $wtr = array('department'), $qfo_filter = null, $filter_params = array(), $order_by = "`dep`.name ASC", $count_rows = 100, $start_row = 0)
	{
		$q = $this-> get_List_QueryFactory_Helper($qfo_filter, $filter_params, $order_by, $count_rows, $start_row);
		$q-> where("`dep`.org_id = ?", $org_id);

		// What to read?
		$wtr = self::wtr_Helper($wtr);

		if(in_array('department', $wtr))
			// department details ( all fields in the primary table )
			$q-> table(MVC_Core::$db-> table('dep'));
		elseif(in_array('dep_target', $wtr))
			// Used when reading departments of another organization for the purpose of sharing an issue with an organization and allocating the issue to the proper departments
			// CHECK/FIX: Not sure if "use4ist" is actually needed inside get_List
			$q-> table(MVC_Core::$db-> table('dep'), 'id,name,contact_id,privileges_or,access_ids_or'); // ,use4ist
		else
			// read basic data (for simple lists)
			$q-> table(MVC_Core::$db-> table('dep'), 'id,name,contact_id,count_members,count_ctx');

		if(in_array('chief', $wtr))
			// join information about the organization's manager
			$q-> table(MVC_Core::$db-> table('usr'), QueryFactory::autoalias_Add_Prefix("user,status,name,email", '_usr_'), 'left', '`dep`.chief_id = `usr`.id');

		// TODO: Read "Department" contact if requested
		// if(in_array('contact', $wtr));

		// custom join $wtr (could be anything)
		if(isset($wtr['joins']))
			$this-> get_List_Custom_Joins($q, $wtr['joins']);

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return MVC_Core::$debug-> dump("Cannot read the list of departments or no departments currently available", __METHOD__, null, 'warning');

		$r-> split_by_prefixes = array(
			// extra table(s)
			'chief'=> "_usr_"
		);

		// get all data and perform decodes on some special fields
		$all = array();
		foreach($r as $idx=> $dep) {
			// Post-Process
			$dep['id'] = (int)$dep['id'];
			$dep['contact_id'] = (int)$dep['contact_id'];
			if(isset($dep['count_members']))
                $dep['count_members'] = $this->countDeptMembers((int)$dep['id']);
//                        IWI show departments issue count in issue list menu start
                $dep['count_issues'] = $this->countDeptIssues((int)$dep['id']);
//                IWI show departments issue count in issue list menu end
//				$dep['count_members'] = (int)$dep['count_members'];

			if(in_array('dep_target', $wtr)) {
				if(!empty($dep['privileges_or']))
					$dep['privileges_or'] = explode(",", $dep['privileges_or']);
				if(!empty($dep['access_ids_or']))
					$dep['access_ids_or'] = @json_decode($dep['access_ids_or'], true);
//  			if(!empty($dep['use4ist']))
//  				$dep['use4ist'] = explode(',', $dep['use4ist']);
//  			else
//  				$dep['use4ist'] = array();
			}

			// When returning details, decode them
			if(in_array('department', $wtr)) {
				if(!empty($dep['chief_id']))
					$dep['chief_id'] = (int)$dep['chief_id'];
				else
					unset($dep['chief_id']);

				if(!empty($dep['privileges_or']))
					$dep['privileges_or'] = explode(",", $dep['privileges_or']);
				if(!empty($dep['access_ids_or']))
					$dep['access_ids_or'] = @json_decode($dep['access_ids_or'], true);

				if(!empty($dep['ist_or']))
					$dep['ist_or'] = @json_decode($dep['ist_or'], true);

				unset($dep['org_id']); // not necessary, was passed on when calling the method
			}
			$all[] = $dep;
		}
		return $all;
	}

	//----- User to Department Relation CRUD -----//

	/**
	 * Connects an existing user to an existing department, optionally adding other data ( like comment or custom privileges )
	 *
	 * @param int $dep_id
	 * @param int $user_id
	 * @param array $rel_data it can contain a comment, cust_privs (custom ACLs for this user inside the department),
	 * -- disabled, hidden - to add the user as disabled or hidden
	 */
	public function attach_User_to_Dep($dep_id, $user_id, $rel_data = array(), $update_dep = true)
	{
		// Read quick information about the user (to see if he exists, and what organizaation)
		$m_user = new App_Model_User();
		$user = $m_user-> read($user_id, array(), false);

		if($user === false)
			return false; // error, user not found

		// Pre-Process
		if(isset($rel_data['privileges_or']))
			$rel_data['privileges_or'] = implode(',' ,$rel_data['privileges_or']);
		if(isset($rel_data['access_ids_or']))
			$rel_data['access_ids_or'] = json_encode($rel_data['access_ids_or']);

		if(isset($rel_data['org_id'])) {
			if($rel_data['org_id'] != $user['org_id'])
				return false; // oops ! The specified user is not from the specified organization ID !
		} else
			// use the org_id of the specified user when org_id not present in the relation data
			$rel_data['org_id'] = $user['org_id'];
		$rel_data['user_id'] = $user['id'];
		$rel_data['dep_id'] = $dep_id;

		$q=QueryFactory::insert()
		-> table(array(MVC_Core::$db-> tab['u2d'], 'u2d'), "org_id,user_id,dep_id,comment,privileges_or,access_ids_or,disabled,hidden")
		-> values($rel_data);

		try {
			$r = MVC_Core::$db-> query($q);
			if($r) {
				// If insert happened successfully, update the member counter for departments
				if($update_dep)
					$this-> update($dep_id, array(
						'count_members'=> '`count_members` + 1'
					), false, true);
			}
			return $r;
		} catch(Exception $e) {
			// error, could not add the user, maybe duplicate key?
			return false;
		}
	}

	/**
	 * Tests if a user is part of a department and returns the relation information ( comment, if hidden or disabled, custom ACLs )
	 *
	 * @param int $dep_id
	 * @param int $user_id
 	 * @param bool $decode Decode fields ( can be disabled, for example when information is sent directly to JS so decoding can take place there instead )
	 * @return array The relationship information
	 */
	public function read_User_Dep_Relation($dep_id, $user_id, $decode = true)
	{
		$q=QueryFactory::select()
		-> table(array(MVC_Core::$db-> tab['u2d'], 'u2d'), "comment,privileges_or,access_ids_or,disabled,hidden")
		-> where("`dep_id` = ?", $dep_id)
		-> where("`user_id` = ?", $user_id);

		$r = MVC_Core::$db-> query($q);
		if($r && count($r)==1) {
			$u2d = $r-> current();

			// Post-process
			if($decode) {
				if(!empty($u2d['privileges_or']))
					$u2d['privileges_or'] = explode(",", $u2d['privileges_or']);
				else
					$u2d['privileges_or'] = array();

				if(!empty($u2d['access_ids_or']))
					$u2d['access_ids_or'] = @json_decode($u2d['access_ids_or'], true);
			}

			$u2d['disabled'] = (bool)$u2d['disabled'];
			$u2d['hidden'] = (bool)$u2d['hidden'];

			return $u2d;
		} else
			return false;
	}

	/**
	 * Updates the relationship between a user and a department (might set custom ACLs, comment, or the hidden/disabled flags)
	 *
	 * @param int $dep_id
	 * @param int $user_id
	 * @param int $new_rel_data Modified relation data
	 * @param bool $encoded Indicates if $new_rel_data's fields are already encoded (join, json_encode, etc.)
	 */
	public function update_User_Dep_Relation($dep_id, $user_id, $new_rel_data = array(), $encoded = false)
	{
		// Pre-Process
		if(!$encoded) {
			if(!empty($new_rel_data['privileges_or']))
				$new_rel_data['privileges_or'] = implode(',' ,$new_rel_data['privileges_or']);
			if(!empty($new_rel_data['access_ids_or']))
				$new_rel_data['access_ids_or'] = json_encode($new_rel_data['access_ids_or']);
		}

		$q=QueryFactory::update()
		-> table(array(MVC_Core::$db-> tab['u2d'], 'u2d'), "comment,privileges_or,access_ids_or,disabled,hidden") // note that this has no effect, TODO: Check new_rel_data before the update call
		-> values($new_rel_data)
		-> where("`dep_id` = ?", $dep_id)
		-> where("`user_id` = ?", $user_id)
		-> limit(1);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Removes an user from a department
	 * -- And decreases the member count for the department.
	 * -- If the user is the department chief, the department will become chief-less and will need to be edited again
	 *
	 * @param int $dep_id
	 * @param int $user_id
	 * @param bool $force_clean Ignore if user exists or not, just remove the link. No stats updates.
	 */
	public function detach_User_from_Department($dep_id, $user_id, $force_clean = false)
	{
		// Attempt to read the user-2-dep relation, to see if the user is actually a member of the department
		$u2d = $this-> read_User_Dep_Relation($dep_id, $user_id, false);
		if($u2d == false)
			return false; // error, nothing to detach as the user is not a member

		// Read the department to see if it exists and if the user is the current chief. If so, set chief_id to 0 (beside reducing count_members)
		$dep = $this-> read($dep_id);
		if($dep === false)
			return false; // error, department not found

		if(!$force_clean) {
			// Read quick information about the user
			$m_user = new App_Model_User();
			$user = $m_user-> read($user_id, array(), false);

			if($user === false)
				return false; // error, user not found
		}

		// All checks out, remove the user from the department
		// Remove the department record itself
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['u2d'])
		-> where('`dep_id` = ?', $dep_id)
		-> where('`user_id` = ?', $user_id);

		try {
			$r = MVC_Core::$db-> query($q);

			if($r) {
				if($force_clean) {
					// When force cleaning, recount members (count links), and update department with exact value (fix statistics)
					$q2=QueryFactory::select()
					-> table(MVC_Core::$db-> tab['u2d'], array('count'=> "count(*)"))
					-> where('`dep_id` = ?', $dep_id);

					$member_count_r = MVC_Core::$db-> query($q2)-> current();
					$this-> update($dep_id, array(
						'count_members'=> $member_count_r['count']
					));

				} else {
					// Check if this is the user's active department, if so update the user to change his current department to 0 (no department)
					if($dep_id == $user['cdep_id']) {
						$m_user-> update($user_id, array(
							'cdep_id'=> 0
						));
					}

					// Update the department, reduce the counter and if the user was the chief, set the chief_id value to 0
					$update = array(
						'count_members'=> $dep['count']['members'] - 1
					);
					if($user_id == $dep['chief_id'])
						$update['chief_id'] = 0;

					$this-> update($dep_id, $update);
				}
			}
			return $r;
		} catch(Exception $e) {
			// error, could not remove the relation or update user/department, database error
			return false;
		}
	}

	/**
	 * Reads all users which are members of a department (and other optional data)
	 * - Automatically skips deactivated users, but can be shown for administrative purposes
	 *
	 * @param int $dep_id
	 * @param bool $full_mode true = Read all relation and user data (or just user id, name, status), false = Only basic information (for a list) - Quick mode
	 * @param bool $show_disabled If true, shows deactivated users from the list (for example, when superusers are editing department membership).
	 */
	public function get_Dep_User_List($dep_id, $full_mode = false, $show_disabled = false)
	{
		$q=QueryFactory::select()
		-> table(array(MVC_Core::$db-> tab['u2d'], 'u2d'), $full_mode? "comment,privileges_or,access_ids_or,disabled,hidden": "user_id,comment,disabled,hidden")
		-> table(array(MVC_Core::$db-> tab['usr'], 'usr'), QueryFactory::autoalias_Add_Prefix($full_mode? "id,user,status,cdep_id,name,email,contact_id": "id,name,status,cdep_id,contact_id", '_usr_'), 'left', '`u2d`.user_id = `usr`.id')
		-> where("`u2d`.`dep_id` = ?", $dep_id)
		-> order("`usr`.`name` ASC");

		if(!$show_disabled)
			$q-> where("`usr`.status != 'disabled' AND `usr`.status != 'invisible'");

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false; // error or empty result

		$r-> split_by_prefixes = array(
			// extra table(s)
			'user'=> "_usr_"
		);

		// Post-Process
		$all = array();
		foreach($r as $u2d) {
			// Self correcting member lists - If missing users (were deleted) are detected, automatically remove the missing user from department ( forced, no stats updates )
			if(is_null($u2d['user']['id'])) {
				$this-> detach_User_from_Department($dep_id, $u2d['user_id'], true);
				continue;
			}
			unset($u2d['user_id']);

			if($full_mode) {
				// in "full mode", there is a tree of information
				$u2d['privileges_or'] = explode(',', $u2d['privileges_or']);
				if(!empty($u2d['access_ids_or']))
					$u2d['access_ids_or'] = @json_decode($u2d['access_ids_or'], true);

				$u2d['user']['id'] = (int)$u2d['user']['id'];
				if($u2d['user']['cdep_id'] == $dep_id)
					$u2d['department']['current'] = true;
				$u2d['department']['disabled'] = (int)$u2d['disabled'];
				$u2d['department']['hidden'] = (int)$u2d['hidden'];
				unset($u2d['disabled'], $u2d['hidden']);
			} else {
				// in "quick mode", privileges, access ids are not read (not necessary), and there is only one organizational level
				$u2d['id'] = (int)$u2d['user']['id'];
				$u2d['name'] = $u2d['user']['name'];
				$u2d['status'] = $u2d['user']['status'];
				if($u2d['user']['cdep_id'] == $dep_id)
					$u2d['current'] = true;
				$u2d['disabled'] = (int)$u2d['disabled'];
				$u2d['hidden'] = (int)$u2d['hidden'];
				unset($u2d['user']);
			}

			$all[] = $u2d;
		}
		return $all;
	}

	/**
	 * Reads all departments in which an user is a member of (and his optional ACLs overrides)
	 * @param int $user_id
	 * @param int $org_id User's organization, for a secondary check not to show wrong entries
	 * @param bool $full_mode true = Read all relation data (or just department id, name), false = Only basic information (for a list) - Quick mode
	 * @return array|bool
	 */
	public function get_User_Dep_Relation_List($user_id, $org_id, $full_mode = true)
	{
		$q=QueryFactory::select()
		-> table(array(MVC_Core::$db-> tab['u2d'], 'u2d'), $full_mode? "comment,privileges_or,access_ids_or,disabled,hidden": "disabled,hidden")
		-> table(array(MVC_Core::$db-> tab['dep'], 'dep'), QueryFactory::autoalias_Add_Prefix($full_mode? "id,name,count_members,count_ctx,contact_id,listed": "id,name,contact_id", '_dep_'), 'left', '`u2d`.dep_id = `dep`.id')
		-> where("`u2d`.`user_id` = ?", $user_id)
		-> where("`u2d`.`org_id` = ?", $org_id)
		-> order("`dep`.`name` ASC");

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false; // error or empty result

		$r-> split_by_prefixes = array(
			// extra table(s)
			'department'=> "_dep_"
		);

		// Post-Process
		$all = array();
		foreach($r as $u2d) {
			if($full_mode) {
				// in "full mode", there is a tree of information
				$u2d['privileges_or'] = explode(',', $u2d['privileges_or']);
				if(!empty($u2d['access_ids_or']))
					$u2d['access_ids_or'] = @json_decode($u2d['access_ids_or'], true);

				$u2d['department']['id'] = (int)$u2d['department']['id'];
				$u2d['department']['disabled'] = (int)$u2d['disabled'];
				$u2d['department']['hidden'] = (int)$u2d['hidden'];
				unset($u2d['disabled'], $u2d['hidden']);
			} else {
				// in "quick mode", privileges, access ids are not read (not necessary), and there is only one organizational level
				$u2d['id'] = (int)$u2d['department']['id'];
				$u2d['name'] = $u2d['department']['name'];
				$u2d['disabled'] = (int)$u2d['disabled'];
				$u2d['hidden'] = (int)$u2d['hidden'];
				unset($u2d['department']);
			}

			$all[] = $u2d;
		}
		return $all;
	}

//----- Department automatic record linking CRUD -----//

	/**
	 * Creates a auto-record-link for a certain department and record type
	 *
	 * @param int $src_dep Source department (the one in which a record is sent/created)
	 * @param string $rt_key Record Type
	 * @param string $link_deps CSV list (or Array) or department ID's which will be linked on record create/receive
	 * @param string $comment Optional comment for the link
	 */
	public function reclink_Store($src_dep, $rt_key, $link_deps, $comment = null, $create_check = false)
	{
		if($create_check) {
			// First, check if $rt_key is already used for the same $src_dep, as there can only be one "links" record for record type
			$check = $this-> reclink_Read($src_dep, $rt_key);
			if(!empty($check))
				return 1;
		}

		if(!is_array($link_deps))
			$link_deps = explode(',', $link_deps);

		if(($pos = array_search($src_dep, $link_deps))!==false) {
			return 3;
			// Remove source department from list if accidentally selected
//			array_splice($link_deps, $pos, 1);
		}

		$q=QueryFactory::replace()
		-> table(MVC_Core::$db-> table('drl'))
		-> values(array(
			'src_dep'=> $src_dep,
			'rt_key'=> $rt_key,
			'link_deps'=> implode(',', $link_deps),
			'comment'=> $comment
		));

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return 2;
	}

	/**
	 * Reads the links for one department and one record type.
	 * - This is used mainly for the purpose of linking records but also for verification when creating a new link
	 * @param int $src_dep
	 * @param string $rt_key
	 * @param bool $read_deps Read information about the linked departments (their names)
	 * @return array
	 */
	public function reclink_Read($src_dep, $rt_key, $read_deps = false)
	{
		if(empty($src_dep) || empty($rt_key))
			return false;

		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('drl'), array('link_deps', 'comment', 'disc_src'))
		-> where("`src_dep` = ?", $src_dep)
		-> where("`rt_key` = ?", $rt_key);

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false; // error or empty result

		$links = $r-> current();
		$links['link_deps'] = explode(',', $links['link_deps']);
		if($read_deps) {
			// TODO: Read deps from list
		}

		return $links;
	}

	/**
	 * Deletes an existing RecLink for the current department and one of the record types
	 *
	 * @param int $src_dep
	 * @param string $rt_key
	 */
	public function reclink_Delete($src_dep, $rt_key)
	{
		if(empty($src_dep) || empty($rt_key))
			return false;

		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> table('drl'))
		-> where("`src_dep` = ?", $src_dep)
		-> where("`rt_key` = ?", $rt_key);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Reads the list of auto-record-links which apply to one department, and optionally filter by record type
	 *
	 * @param int $src_dep Source department (the one in which a record is sent/created)
	 * @param string $rt_key Record Type
	 * @return MVC_Database_Result
	 */
	public function reclink_GetList($src_dep, $rt_key=null)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('drl'), array('rt_key', 'link_deps', 'comment'))
		-> table(MVC_Core::$db-> table('ist'), array('rt_name'=> 'name', 'rt_id'=> 'id'), 'left', '`ist`.key = `drl`.rt_key')
		-> where("`drl`.src_dep = ?", $src_dep)
		-> where("`ist`.scope = 'global'")
		-> order("`ist`.name ASC");

		if(!empty($rt_key))
			$q-> where("`drl`.rt_key = ?", $rt_key);

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false; // error or empty result

		// Returns the result object
		return $r;
	}

	/**
	 * Get the list of departments which are set to send data to the target.
	 * This one is only used in the management interface. Reads multiple records from the links table which point to the target department
	 *
	 * @param int $tgt_dep Target department
	 * @param string $rt_key Record Type
	 * @return MVC_Database_Result
	 */
	public function reclink_GetIncomingList($tgt_dep, $rt_key=null)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('drl'), array('src_dep', 'rt_key', 'comment'))
		-> table(MVC_Core::$db-> table('ist'), array('rt_name'=> 'name', 'rt_id'=> 'id'), 'left', '`ist`.key = `drl`.rt_key')
		-> table(MVC_Core::$db-> table('dep'), array('src_name'=> 'name', 'listed'), 'left', '`dep`.id = `drl`.src_dep')
		-> where("FIND_IN_SET(?, `drl`.link_deps)", $tgt_dep)
		-> where("`ist`.scope = 'global'")
		-> order("`ist`.name ASC");

		if(!empty($rt_key))
			$q-> where("`drl`.rt_key = ?", $rt_key);

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false; // error or empty result

		// Returns the result object
		return $r;
	}

    /**
     * returns total members of a department
     * @param      $dep_id
     * @param bool $full_mode
     *
     * @return bool|int
     * @throws Exception
     */
    public function countDeptMembers($dep_id,$full_mode = true){
        $q=QueryFactory::select()
            -> table(array(MVC_Core::$db-> tab['u2d'], 'u2d'), $full_mode? "comment,privileges_or,access_ids_or,disabled,hidden": "user_id,comment,disabled,hidden")
            -> table(array(MVC_Core::$db-> tab['usr'], 'usr'), QueryFactory::autoalias_Add_Prefix($full_mode? "id,user,status,cdep_id,name,email,contact_id": "id,name,status,cdep_id,contact_id", '_usr_'), 'left', '`u2d`.user_id = `usr`.id')
            -> where("`u2d`.`dep_id` = ?", $dep_id)
            -> where("`usr`.status != 'disabled' AND `usr`.status != 'invisible'");

        $r = MVC_Core::$db-> query($q);
        if(!$r)
			return false; // error or empty result
        return count($r);
    }
    

//                IWI show departments issue count in issue list menu start

    public function countDeptIssues($dep_id, $full_mode = true) {
        $current_rt = $_SESSION['cache']['current_rt']['id'];
        $q = QueryFactory::select()
                ->table(MVC_Core::$db->table('iod'), array('issue_count' => "COUNT(`iod`.issue_id)"))
                ->table(MVC_Core::$db->table('iss'), 'type_id', 'left', ' `iss`.id=`iod`.issue_id')
//                ->where("`iod`.dep_ids = ?", $dep_id)
                ->where("FIND_IN_SET(?, `iod`.dep_ids)", $dep_id)
                ->where("`iss`.type_id = ?", $current_rt);
//echo $q;
        $r = MVC_Core::$db->query($q);
        $r = $r->current();
//        print_r($r);
        if (!$r)
            return false; // error or empty result
        return $r['issue_count'];
    }

//                IWI show departments issue count in issue list menu end
}
