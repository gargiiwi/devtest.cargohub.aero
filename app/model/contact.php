<?php
/**
 * "Contacts" related data access
 * -- Was originally part of communication module, but due to extensive use of contacts for various purposes, everything moved into own class
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package Cargo ITS
 * @version 1.0.dev
 */
class App_Model_Contact extends MVC_Model
{ 
//----- Contacts CRUD -----//

	/**
	 * Accepted contact types
	 * -- Contacts are added in the system under various conditions, some automatic, others manual by users
	 * @var array[string]
	 */
	private static $accepted_contact_types = array(
		'system',		// This contact is preset in the database and is used as the sender for automatic notifications
		// -- Cannot be created or edited, system contact(s) need to be directly inserted in the database
		'global',		// Yellowpages-type contact, everyone has access to these, including users outside the system ( assuming an interface is provided for them to access this list )
		'organization',	// This is an organization-wide contact, which is visible by all users having the privilege: org_view_contacts, and managed by users with: org_manage_contacts
		'org_self',		// Represents addesses relevant to the organization itself, used by external targets to send messages to the organization.
		// -- Used as choices for sending data to this organization using e-mails (instead of in-system methods)
		// -- Also, used for accessing external mail boxes using the IMAP/POP3 library, the contact record contains the server connection information (including password)
		// -- org_admin required to edit these contacts and also view sensitive information (like the IMAP/POP3 connection info)
		'issue_target', // Address used for submitting issues to the organization
		'department',	// Department-wide contact, visible to users from the same department
		'dep_self',		// Address relevant to this department, allowing external parties to send a message to representatives of this department
		// -- Might have other associated meta-data, to allow the system to select a department contact automatically depending on various factors
		'personal',		// Personal user's contact, used in the messaging module as possible targets for outgoing messages
		'user_self',	// The user's own e-mail addreses, work on the same principle as the 'org_self' or 'dep_self' but are only relevant to the user himself
		// -- Only the user can edit these, and only the user can see the server connect info
		'auto_out',		// This contact is added when the system is used to send out an e-mail which doesn't fall into the other categories
		// -- In this case, the user_id represents the user that initiated the action that required sending out an e-mail
		'auto_in',		// This contact is added when the system receives an e-mail from an address which does not exist already as another type, and is used to capture random messages that come via the IMAP/POP3 library
		// -- Owner is set depending if the incoming message is sent to an "org_self" or "user_self" address
		'isspost',		// This is an issue related contact, created when a user submits an issue under an e-mail address which isn't yet present in contacts of type "isspost"
		// -- If the e-mail already exists in contacts, type "isspost", that contact is reused.
		// -- If the same e-mail is present in the "user_self" contacts, that self-contact is used and the user becomes the "creator" of this issue, even when submitted from the frontend - not logged in.
		'isslink',		// Custom contact created from the staff side, after the issue has been posted.
		// -- As with "isspost", it can be used as primary target for conversations initiated and linked to the issue.
		'unknown',		// Possibly ex-staff member. These contacts cannot be associated with any other entity in the system, and cannot be used as e-mail based login
		// -- Not possible to create directly, only to covert other contacts and address collection into "unknown" contacts
		'draft'			// Used when submitting some other data which needs to be linked to contacts. When the submit process is complete these contacts are converted to another type, or they might become orphaned
		// -- The system should periodically check for orphaned "draft" contacts and clear them from the database.
	);

	/**
	 * Creates a contact record used for external communication
	 *
	 * @param string $type One of: 'system','organization','personal','org_self','user_self','auto_out','auto_in','isspost','isssend'
	 * @param int $org_id Organization which owns this contact record
	 * -- For automatic incoming (isspost, auto_in) the system sets this when the message is allocated to an organization
	 * -- For automatic outgoing (isssend, auto_out) this represents the org. of the logged in user when the message is sent out
	 * -- For drafts, there is no organization (yet). It will be set when submitting the form containing the draft contacts, or when the "in-progress" draft contact is actually saved
	 * @param array $contact_data Email, Name and other custom fields (xdata) field
	 * @param int $user_id User that created this contact (organization, org_self, department, dep_self, auto_out, isspost) or that owns the contact (personal, user_self) - For rest it will be null
	 * @param int $dep_id Department ID (optional) for organization/department contacts; Null for the rest
	 * @param bool $xdata_encoded Indicates that xdata is already encoded (string instead of array/object)
	 * @return int Insert ID of the contact record that has been created
	 * @TODO Fix the order of the parameters, it's a bit weird now ? (Needs to be changed in all calling functions)
	*/
	public function create($type, $org_id = null, $contact_data = array(), $user_id = null, $dep_id = null, $xdata_encoded = false)
	{
		// check the type to be in the accepted types
		if(!in_array($type, self::$accepted_contact_types))
			return false; // Wrong contact type (not supported by the system)

		// Create the contact row
		$contact = array(
			'type'=> $type,
			'org_id'=> $org_id,
			'ctime'=> date("Y-m-d H:i:s")
		);

		// Check if E-mail field field is present, if not, return error
		if(isset($contact_data['email']))
			$contact['email'] = $contact_data['email'];
		else
			return false;

		// While name is not mandatory, it goes as an independent database field because contacts are sorted and can be searched by Name when displaying lists
		if(isset($contact_data['name']))
			$contact['name'] = $contact_data['name'];

		// Add the User ID to the contact information for certain types
		if(!is_null($user_id) && in_array($type, array('global', 'organization', 'org_self', 'department', 'dep_self', 'personal', 'user_self', 'auto_out', 'isspost', 'isslink', 'draft')))
			$contact['user_id'] = $user_id;

		if(!is_null($dep_id) && in_array($type, array('organization', 'org_self', 'department', 'dep_self')))
			$contact['dep_id'] = $dep_id;

		// Encode xdata if necessary
		if(isset($contact_data['xdata']))
			$contact['xdata'] = $xdata_encoded? $contact_data['xdata']: json_encode($contact_data['xdata']);

		// Other optional entries
		if(isset($contact_data['count_msgs']))
			$contact['count_msgs'] = $contact_data['count_msgs'];
		if(isset($contact_data['label_id']))
			$contact['label_id'] = $contact_data['label_id'];

		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['ctx'])
		-> values($contact);

		if(MVC_Core::$db-> query($q))
			return MVC_Core::$db-> insert_id;
		else
			return false;
	}

	/**
	 * Reads a contact record by it's ID
	 * -- Used usually after it's been selected from a list, or for checking purposes
	 *
	 * TODO: $wtr for contacts
	 *
	 * @param int $id
	 * @param bool $read_xdata Read and decode contact extra data (or not)
	 * @param bool $read_note Read the associated note record (Not yet implemented)
	 * @return array The contact information
	 */
	public function read($id, $read_xdata = true, $read_note = false)
	{
		$q= QueryFactory::select()
		-> where("`id` = ?", $id)
		-> limit(1);

		if($read_xdata)
			$q-> table(MVC_Core::$db-> tab['ctx']);
		else
			$q-> table(MVC_Core::$db-> tab['ctx'], 'id,state,org_id,dep_id,user_id,type,ctime,email,name,count_msgs,note_id'); // All fields except xdata (which might be the longest by far)

		$r = MVC_Core::$db-> query($q);
		if($r && count($r)==1) {
			$contact = $r-> current();
			// decode xdata
			if($read_xdata)
				$contact['xdata'] = @json_decode($contact['xdata'], true);

			if($read_note) {
				// TODO: Read the associated note
			}

			return $contact;
		} else
			return false; // not found or other DB error
	}

	/**
	 * Searches for contacts by their type and email, and optionally filtering by other parameters (name, org_id, user_id or dep_id)
	 * -- Returns an array of contacts, either with one element if the exact contact has been found, or multiple elements if more than one match the search
	 * -- E-mail supports wildcard search in the format ?@address.tld (or *@...) (Match all contacts from a certain domain)
	 *
	 * @param string|array $types_or_idlist Type(s) of contact (on certain types there should only be one unique e-mail address per contact type), or a preset list of ID's to be searched for the correct one(s)
	 * -- Note: If this parameter is empty, the method might not return the correct contact(s)
	 * @param string $email
	 * @param string $name Also search by name
	 * @param int $org_id Organization ID is necessary for certain types of contacts
	 * @param int $dep_id Department id for certain types of contacts
	 * @param int $user_id User ID is necessary for certain types of contacts
	 * -- Note: Not all of these should exist at the same time
	 * @param bool $return_id Only returns the ID (when another operation only needs the id, instead of full data)
	 * @param bool $return_one Return first match (ignore any others)
	 * @return array|int The contact information or the ID
	 */
	public function locate($types_or_idlist, $email, $name = null, $org_id = null, $user_id = null, $dep_id = null, $return_id = false, $return_one = false)
	{
		$q= QueryFactory::select()
		-> table(MVC_Core::$db-> table('ctx'), $return_id? 'id': null)
		-> order('`ctime` ASC'); // Find oldest created first

		if(is_array($types_or_idlist))
			$q-> where("`id` IN(?)", $types_or_idlist);
		else {
			if(!empty($types_or_idlist)) {
				$types_or_idlist = explode('|', $types_or_idlist);
				$q-> where("`type` IN(?)", $types_or_idlist);
			}

			if(!is_null($org_id) && count(array_intersect($types_or_idlist, array('organization', 'org_self')))>0) // 'auto_out', 'auto_in', 'isspost', 'isslink'
				$q-> where("(CASE WHEN `ctx`.type IN('organization', 'org_self') THEN `ctx`.org_id = ? ELSE 1 END)", $org_id);
// 				$q-> where("`org_id` = ?", $org_id);
			if(!is_null($dep_id) && count(array_intersect($types_or_idlist, array('organization', 'org_self', 'department', 'dep_self')))>0)
				$q-> where("(CASE WHEN `ctx`.type IN('department', 'dep_self') THEN `ctx`.dep_id = ? ELSE 1 END)", $dep_id);
// 				$q-> where("`dep_id` = ?", $dep_id);
			if(!is_null($user_id) && count(array_intersect($types_or_idlist, array('organization', 'org_self', 'personal', 'user_self', 'auto_out')))>0)
				$q-> where("(CASE WHEN `ctx`.type IN('personal', 'user_self') THEN `ctx`.user_id = ? ELSE 1 END)", $user_id);
// 				$q-> where("`user_id` = ?", $user_id);

			// Draft contacts must not be older than 2 hours (System self cleans unlinked drafts anyway during automatic maintenance, but it might be possible that drafts are still in place)
			if(count(array_intersect($types_or_idlist, array('draft')))>0) {
				$q-> where("(CASE WHEN `ctx`.type = 'draft' THEN `ctx`.ctime > ? ELSE 1 END)", date("Y-m-d H:i:s", time()-7200));
			}
		}

		if($email[0] == '*' || $email[0] == '?') // Wildcard e-mail
			$q-> where("`substring_index(`email`, '@', -1)` = ?", substr($email, 2));
		else // Direct e-mail
			$q-> where("`email` = ?", $email);

		if(!is_null($name)) // Also filter by name ( in some cases, there are multiple contacts with different names and same e-mail )
			$q-> where("`name` = ?", $name);

		if($return_one)
			$q-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if($r && count($r)>0) {
			$all = array();
			foreach($r as $contact) {
				if($return_id) {
					$all[] = (int)$contact['id'];
				} else {
					$contact['id'] = (int)$contact['id'];
					$contact['org_id'] = (int)$contact['org_id'];
					if(!is_null($contact['dep_id']))
						$contact['dep_id'] = (int)$contact['dep_id'];
					if(!is_null($contact['user_id']))
						$contact['user_id'] = (int)$contact['user_id'];
					// decode xdata
					$contact['xdata'] = @json_decode($contact['xdata'], true);
					$all[] = $contact;
				}
			}
			if($return_one)
				return reset($all);
			else
				return $all;
		} else
			return false; // not found or other DB error
	}

	/**
	 * Updates or corrects anything in the contact data, including org or user ID's, but cannot change type !
	 *
	 * @param int $id
	 * @param array $new_contact_data
	 * @param bool $xdata_encoded Indicates that xdata is already encoded
	 * @param bool $string_expressions This parameter is sent to query factory, and is used to disable string escaping (in order to allow expressions, for example to increment a counter)
	 */
	public function update($id, $new_contact_data, $xdata_encoded = false, $string_expressions = false)
	{
		// any key outside the correct fields is discarded (to avoid database errors)
		$fields = array('state', 'org_id', 'dep_id', 'user_id', 'label_id', 'type', 'ctime', 'email', 'name', 'xdata', 'count_msgs', 'note_id');
		foreach($new_contact_data as $field=> $value) {
			if(!in_array($field, $fields))
				unset($new_contact_data[$field]);
		}

		if(isset($new_contact_data['xdata']) && !$xdata_encoded)
			$new_contact_data['xdata'] = json_encode($new_contact_data['xdata']);

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['ctx'])
		-> where("`id` = ?", $id)
		-> values($new_contact_data, null, $string_expressions);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Copies an existing contact into a new one, allowing certin fields to be changed
	 * @todo Make this function smarter, to allows new values to be injected in the copy, instread of straight copy
	 *
	 * @param int $id
	 * @return int Returns the ID of the copy
	 */
	public function copy($id)
	{
		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['ctx'])
		-> values(
			QueryFactory::select()
			-> table(MVC_Core::$db-> tab['ctx'], array('id'=> null, 'state', 'org_id', 'dep_id', 'user_id', 'label_id', 'type', 'ctime', 'email', 'name', 'xdata', 'count_msgs'=> 0, 'note_id'))
			-> where("`id` = ?", $id)
		);

		if(MVC_Core::$db-> query($q))
			return MVC_Core::$db-> insert_id;
		else
			return false;
	}

	/**
	 * Counts how many messages are using a contact. Updates "count_msgs" field with the found value
	 * @param unknown $id
	 */
	public function count_Messages($id)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['msg'], array('count_msgs'=> "COUNT(*)"))
		-> where("`from_id` = ?", $id)
		-> where_or("FIND_IN_SET(?, `recipients`)", $id);

		$res = MVC_Core::$db-> query($q)-> current();
		$this-> update($id, $res);
		return (int)$res['count_msgs'];
	}

	/**
	 * Updates contact label for a list of contact.
	 *
	 * @param array|string $contact_list
	 * @param int $label_id Must not be empty
	 */
	public function set_Label($contact_list, $label_id)
	{
		if(empty($label_id))
			return false;

		if(is_string($contact_list))
			$contact_list = explode(',', $contact_list);
		elseif(is_numeric($contact_list))
			$contact_list = array((int)$contact_list);
		elseif(!is_array($contact_list) || empty($contact_list))
			return false; // Error, empty contact list or something unsupported

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['ctx'])
		-> where("`id` IN(?)", $contact_list)
		-> values(array(
			'label_id'=> $label_id
		));

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Remove label ID from all contacts using a label ID.
	 * If the Label ID is not used, this method does nothing
	 *
	 * @param int $label_id
	 */
	public function cleanup_Label($label_id)
	{
		if(empty($label_id))
			return false;

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['ctx'])
		-> where("`label_id` = ?", $label_id)
		-> values(array(
			'label_id'=> null
		));

		return MVC_Core::$db-> query($q);

	}

	/**
	 * Delete an existing contact (locating it by ID)
	 * -- usually as the action of an interface command
	 * -- Also remove a matching contact account record
	 *
	 * @param int $id
	 */
	public function delete($id)
	{
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['ctx'])
		-> where("`id` = ?", $id)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if(MVC_Core::$db-> affected_rows > 0) {
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['cac'])
			-> where("`contact_id` = ?", $id)
			-> limit(1);
			MVC_Core::$db-> query($q);

			// TODO: Find, remove (and repair) the deleted ID in "managed_ctx" from a contact account;
		}

		return $r;
	}

	/**
	 * Delete all contacts of an organization, of an user, or linked with a certain issue or department
	 * -- Used mainly when the org, user, issue or department is deleted (along with everything else)
	 *
	 * @param string $locator Can be one of "org", "user", "con"
	 * @param int $id The id for which contacts are cleaned
	 * @param array $types Only delete contacts of these type / Defaults to certain types for "org" and "user" locator
	 * -- Function return false (error) if type(s) is/are not specified for "con" locator
	 */
	public function cleanup($locator = 'org', $id, $types = null)
	{
		if(!in_array($locator, array("org", "user", "con")))
			return false; // wrong locator

		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['ctx'])
		-> where("`{$locator}_id` = ?", $id);

		if(!is_null($types)) {
			if(is_string($types))
				$types = explode(',', $types);
			$q-> where("`type` IN(?)", $types);
		} else {
			// By default, if types is null it only deletes certain types for "org" or "user" locator
			if($locator == 'org')
				$q-> where("`type` IN(?)", array("organization", "org_self", "auto_in"));
			if($locator == 'user')
				$q-> where("`type` IN(?)", array("personal", "user_self", "auto_out"));
			if($locator == 'con')
				return false;
		}

		return MVC_Core::$db-> query($q);
	}

//----- Lists -----//

	public $found_rows = 0;

	/**
	 * Returns a list of contacts (has complex filtering conditions), used for both listing and search
	 *
	 * @param int $org_id The organization for which contacts are read (Optional, but necessary for types other than "isspost", "isslink")
	 * @param int $locator User ID, Dep ID or Contact ID list (For when "connection" or "idlist")
	 * @param string $type Can be one of the accepted contact types or "connection", in which case $locator is the connection. Supports multiple types at once
	 * @param array $wtr What to read: contact, org, user, xdata
	 * @param string $order_by How to order these contacts (by default by contact name)
	 * @param int $count_rows Maximum contacts to return (default: 100)
	 * @param int $start_row Start row when returning contacts, used for server-side pagination
	 * @param callback $qfo_filter Optional filtering function - Receives the QueryFactory Object as parameter
	 * @param mixed $filter_params Extra parameters passed to the QFO filter
	 * @param bool $calc_found_rows Runs one extra query so sever-side paging is possible
	 */
	public function get_List($org_id = null, $locator = null, $type = null, $wtr = array('contact'), $order_by = '`ctx`.name ASC', $count_rows = 100, $start_row = 0, $qfo_filter = null, $filter_params = null, $calc_found_rows = false)
	{
		$q = $this-> get_List_QueryFactory_Helper($qfo_filter, $filter_params, $order_by, $count_rows, $start_row);
		if(is_null($q))
			return false; // Error, $qfo_filter failed

		if($calc_found_rows)
			$q-> flags("SQL_CALC_FOUND_ROWS");

		// Hard filter on lists - Only show contacts which are "active" - This comes before anything else.
		if(!in_array('allstates', $wtr))
			$q-> where("`ctx`.state = 'active'");

		if(!is_null($type)) {
			if(is_array($type)) {
				// Multiple types specified - Special use (search system)
				$q-> where('`ctx`.type IN(?)', $type);

				if(!is_null($org_id)) {
					// Organization id, even if present, is not used for some special types ( system, global, auto_in )
					$q-> where("(CASE WHEN `ctx`.type IN('system', 'global', 'auto_in') THEN 1 ELSE `ctx`.`org_id` = ? END)", $org_id);
				}

				// Locator might contain multiple values for certain types of contacts
				if(is_array($locator)) {
					if(isset($locator['user_id']))
						$q-> where("(CASE WHEN `ctx`.type IN('personal', 'auto_out') THEN `ctx`.user_id = ? ELSE 1 END)", $locator['user_id']);
					if(isset($locator['dep_id']))
						$q-> where("(CASE WHEN `ctx`.type IN('department', 'dep_self') THEN `ctx`.dep_id = ? ELSE 1 END)", $locator['dep_id']);
				}
			} else {
				// Single type specified - What is read depends on type
				if($type != 'global' && $type != 'system' && !is_null($org_id))
					$q-> where('`ctx`.org_id = ?', $org_id);

				if($type == 'idlist') {
					// pseudo-type used for reading a specific list of contacts (located by their ID)
					if(is_array($locator) && count($locator)>0)
						$q-> where("`ctx`.`id` IN(?)", $locator);
					else
						return false;
				} elseif($type == 'personal' || $type == 'user_self' || $type == 'auto_out') {
					// For personal and user_self shows the user's own addresses, for auto_out only shows the auto-generated contacts that were created by this user
					$q	-> where('`ctx`.type = ?', $type);
					// FIXME: Dangerous, if locator is missing it might not return the expected results !
					if(!empty($locator))
						$q-> where("`ctx`.user_id = ?", $locator);
				} elseif($type == 'department' || $type == 'dep_self') {
					// For department contacts the $locator represents the department id and should be present for normal users (certain users however can access all department-level contacts)
					$q	-> where('`ctx`.type = ?', $type);
					if(!empty($locator))
						$q	-> where("`ctx`.dep_id = ?", $locator);
				} else {
					// All other types do not depend on the User ID, even if $locator is not null
					if(!in_array($type, self::$accepted_contact_types))
						return false; // Wrong contact type (not supported by the system)

					$q-> where('`ctx`.type = ?', $type);
				}
			}
		} elseif(is_numeric($org_id)) {
			// If no type is present, but $org_id is a number, allows overview of organization-connected contacts
			$q-> where('`ctx`.org_id = ?', $org_id);
		} elseif(!is_callable($qfo_filter))
			// When $qfo_filter is not present, does not allow reading of ALL contacts without any kind of filtering.
			return false;

		// What to read?
		$wtr = self::wtr_Helper($wtr);

		if(in_array('contact', $wtr))
			// full contact info (optional: with xdata)
			$q-> table(MVC_Core::$db-> table('ctx'), 'id,org_id,dep_id,user_id,type,ctime,email,name,count_msgs,note_id'. (in_array('xdata', $wtr)? ',xdata': ''));
		else
			// basic data
			$q-> table(MVC_Core::$db-> table('ctx'), 'id,org_id,email,name,user_id');

		if(in_array('org', $wtr))
			// Organization that owns this contact record
			$q-> table(MVC_Core::$db-> table('org'), QueryFactory::autoalias_Add_Prefix("name,type_id", '_org_'), 'left', '`ctx`.org_id = `org`.id');

		if(in_array('dep', $wtr))
			// Department linked to this contact
			$q-> table(MVC_Core::$db-> table('dep'), QueryFactory::autoalias_Add_Prefix("name,chief_id", '_Dep_'), 'left', '`ctx`.dep_id = `dep`.id');

		if(in_array('user', $wtr))
			// The user that has created or is the owner of this contact record
			$q-> table(MVC_Core::$db-> table('usr'), QueryFactory::autoalias_Add_Prefix("name,status,cdep_id,inbox_id,sentbox_id,count_msgs,count_unread", '_usr_'), 'left', '`ctx`.user_id = `usr`.id');

		// custom join $wtr (could be anything)
		if(isset($wtr['joins']))
			$this-> get_List_Custom_Joins($q, $wtr['joins']);

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r)==0)
			return false; // error or empty result

		// Organize
		$r-> split_by_prefixes = array(
			'organization'=> "_org_",
			'department'=> "_dep_",
			'user'=> "_usr_"
		);

		// Some post-processing
		$all = array();
		foreach($r as $row) {
			$row['id'] = (int)$row['id'];
			$row['org_id'] = (int)$row['org_id'];
			if(in_array('contact', $wtr)) {
				if(!is_null($row['dep_id']))
					$row['dep_id'] = (int)$row['dep_id'];
				if(!is_null($row['user_id']))
					$row['user_id'] = (int)$row['user_id'];

				if(in_array('xdata', $wtr))
					$row['xdata'] = @json_decode($row['xdata'], true);
			}

			$all[] = $row;
		}

		// Check if the calc_found flag is used
		if($calc_found_rows) {
			$fr_row = MVC_Core::$db-> query("SELECT FOUND_ROWS() AS `found_rows`;")-> current();
			$this-> found_rows = (int)$fr_row['found_rows'];
		}

		return $all;
	}

	/**
	 * Helper function that decodes and categorizes contact xdata in a way that is easily displayed/edited (contact field groups)
	 *
	 * @param array $xdata Raw contact xdata
	 * @param bool $load_nls Loads the Contact structure from NLS (can be disabled if it was preloaded before this function)
	 *
	 * @return array Decoded contact data
	 */
	public function decode_Xdata($xdata, $load_nls = true)
	{
		// Read the contact field groups and settings so the dynamic contact editor can be constructed
		if($load_nls)
			MVC_NLS::load_Groups("contact");

		$fields_structure = MVC_NLS::get_Object("contact", true);

		// Process the fields stucture so it can easily be used in the template and xdata decoder
		foreach($fields_structure as &$group) {
			foreach($group['fields'] as $field=> $string) {
				if(strpos($string, "~"))
					list($field_label, $menu_text) = explode("~", $string);
				else
					$menu_text = $field_label = $string;
				$group['fields'][$field] = array(
					'menu_text'=> $menu_text,
					'field_label'=> $field_label
				);
			}
		}

		// Format and decode xdata
		$xdata_trans = array();
		$used_fields = array();

		foreach($fields_structure as $group_key => &$group) {

			foreach($group['fields'] as $key=> $field) {
				// Avoid field duplication (some fields might be specified in the fields structure in multiple places, for the purpose of building the Add/Edit menu)
				if(isset($used_fields[$key]))
					continue;

				// Autogen(erated) group fields will be set even if there is nothing encoded for these fields in the xdata
				if(isset($group['meta']['autogen']) && $group['meta']['autogen'] == true && !isset($xdata[$key]))
					$xdata[$key] = "";

				// Non mandatory fields are only shown if the data actually exists in $xdata
				if(isset($xdata[$key])) {
					// Create group information the first time a key is encountered in this group
					if(!isset($xdata_trans[$group_key])) {
						$xdata_trans[$group_key] = new stdClass();
						$xdata_trans[$group_key]-> meta = $group['meta'];
						$xdata_trans[$group_key]-> meta['key'] = $group_key;
						$xdata_trans[$group_key]-> fields = array();
					}

					$xdata_trans[$group_key]-> fields[] = array(
						'key'=> $key,
						'label'=> $field['field_label'],
						// In the future, they will be more than text (perhaps complex editors - phone number with country selector, date selector, etc. For now, just simple text
						'info'=> (string)$xdata[$key]
					);
					$used_fields[$key] = true;
				}
			}
		}

		return $xdata_trans;
	}

	//----- Recently used contacts (Uses Cookies !) -----//

	/**
	 * Adds the specified contacts to the list of recently used, or move it to front if already exists
	 * -- The cookie name contains User ID, so different users on the same computer will have different lists.
	 * -- However, if no user is logged in, there is only one unique list for each browser/cookie repository.
	 *
	 * @param int $contact_id
	 */
	public function add_To_Recent($contact_id)
	{
		if(!is_numeric($contact_id))
			return; // Avoids breaking the cookie when no/wrong information was passed

		$cookie_name = "its_recent_contacts";
		// User mode?
		if(isset(MVC_Core::$ss-> user))
			$cookie_name.="_".MVC_Core::$ss-> user['id'];

		// Read the cookie (content should be a json string)
		$cookies = new MVC_Persist('cookie');
		$recent_contacts = $cookies-> {$cookie_name};
		// Initialize if necessary ( if there is nothing saved yet )
		if(!$recent_contacts || !is_array($recent_contacts))
			$recent_contacts = array();

		// Check if the contact is already in the list, if yes, remove it so it can be added "on top"
		if(($p = array_search($contact_id, $recent_contacts))!==false)
			unset($recent_contacts[$p]);

		// If already at max, delete the oldest one
		if(count($recent_contacts)>24)
			array_pop($recent_contacts);

		// Add the specified contact in front
		array_unshift($recent_contacts, $contact_id);

		// Finally, save the cookie and return the count
		$cookies-> {$cookie_name} = $recent_contacts;
		return count($recent_contacts);
	}

	//----- Issue-connected contacts -----//

	/**
	 * Creates a link between an existing contact and an existing issue
	 * -- This is the multi-contact support for issues
	 * -- Does not verify if the issue and contact exists ! Please make sure they do before calling the method
	 *
	 * @param int $issue_id
	 * @param int $contact_id
	 * @param array $params Other parameters: role, label, cnv_id, visibility, org_id, user_id (all optional or defaulted by the database)
	 */
	public function con2iss_Create($issue_id, $contact_id, $params = array())
	{
		// Any key outside the correct fields is discarded (to avoid database errors)
		$fields = array('role', 'label', 'cnv_id', 'visibility', 'org_id', 'user_id');
		foreach($params as $field=> $value)
			if(!in_array($field, $fields))
				unset($params[$field]);

		// Values check
		if(isset($params['role']) && !in_array($params['role'], array('submitter', 'transfer', 'recipient', 'assignee', 'custom')))
			unset($params['role']);
		if(isset($params['visibility']) && !in_array($params['visibility'], array('org', 'shared', 'public')))
			unset($params['visibility']);
		if(isset($params['label']) && strlen($params['label']>40))
			$params['label'] = substr($params['label'], 0, 40);

		$params['issue_id'] = $issue_id;
		$params['contact_id'] = $contact_id;
		$params['dt_link'] = time();

		try {
			$q=QueryFactory::insert()
			-> table(MVC_Core::$db-> tab['c2i'])
			-> values($params);

			return MVC_Core::$db-> query($q);
		} catch(Exception $e) {
			// Possibly the uniqueness flag was triggered (the contact might be already linked in the same conditions)
			return false;
		}
	}

	/**
	 * Reads a single issue contact link entry.
	 * - Optionally reads the linked contact itself
	 *
	 * @param int $issue_id
	 * @param int $contact_id
	 * @param int $org_id
	 * @param bool $read_contact Read the linked contact too
	 * @param bool $full_contact_info  false = Read just Name/Email for contacts; true = Fully decode contact info (xdata)
	 */
	public function con2iss_Read($issue_id, $contact_id, $org_id = null, $read_contact = false, $full_contact_info = false)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('c2i'))
		-> where("`c2i`.issue_id = ?", $issue_id)
		-> where("`c2i`.contact_id = ?", $contact_id)
		-> limit(1);

		if($read_contact)
			$q-> table(MVC_Core::$db-> table('ctx'), QueryFactory::autoalias_Add_Prefix("user_id,type,ctime,email,name".($full_contact_info? "xdata": ""), '_ctx_'), 'left', '`c2i`.contact_id = `ctx`.id');

		// When org_id is specified, add the org_id to the conditions but only for contacts with visibility "org". If the contact is shared or public, it is returned anyway ignoring the value in the $org_id
		if(!is_null($org_id))
			$q-> where("(CASE WHEN `visibility` = 'org' THEN `org_id` = ? ELSE 1 END)", $org_id);

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r) == 0)
			return array(); // Contact link not found (or DB error)

		$r-> split_by_prefixes = array(
			// extra table
			'contact'=> "_ctx_"
		);
		$link = $r-> current();

		// Some post-processing
		$link['contact_id'] = (int)$link['contact_id'];
		if(!is_null($link['cnv_id']))
			$link['cnv_id'] = (int)$link['cnv_id'];
		if(!is_null($link['org_id']))
			$link['org_id'] = (int)$link['org_id'];
		if(!is_null($link['user_id']))
			$link['user_id'] = (int)$link['user_id'];

		if($read_contact && $full_contact_info)
			$link['xdata'] = @json_decode($link['xdata'], true);
		return $link;
	}

	/**
	 * Reads contact links for an issue, filtering by visibility
	 * -- This is used to display the contacts in the various issue view modes
	 *
	 * @param int $issue_id
	 * @param bool $full_contact_info null = Don't read contact at all, just the link; false = Read just Name/Email for contacts; true = Fully decode contact info (xdata)
	 * @param string $visibility_type Read mode: "public", "partner", "org"
	 * @param int $org_id Used when $visibility_type is "org"
	 */
	public function con2iss_Get_List($issue_id, $full_contact_info = false, $visibility_type = 'public', $org_id = null)
	{
		// $visibility_type effect:
		// public - Only shows the contacts linked as "public" - This is used when viewing the issue from the frontend
		// partner - Reads the "public" and "shared" contacts - Usually used for assembling the e-mail to send out the issue data, or for issue transfer
		// org - Reads all "public", all "shared" and the "org" ones that match the $org_id - Used when viewing the issue in the organization account
		// -- "org" is the equivalent of private contacts
		// TODO: Maybe implement department level contacts as well, after department restrictions on issue visibility are implemented
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('c2i'), array('contact_id', 'role', 'dt_link', 'label', 'cnv_id', 'visibility'))
		-> where("`c2i`.issue_id = ?", $issue_id)
		-> order("`c2i`.dt_link", "ASC");

		switch($visibility_type) {
			// Public mode only shows contacts that should be visibile for the external submitter. Not related to any organization.
			case 'public':
				$q-> where("`c2i`.visibility = 'public'");
				break;
			// Show only the contacts which are not private to an organization. This could be the usual mode for sending the issue via e-mail
			case 'partner':
				$q-> where("`c2i`.visibility IN('public','shared')");
				break;
			// When visibility type is org, the user can see all public and shared, and only those of type "org" from his own organization.
			case 'org':
				if(empty($org_id))
					return false; // Error, $org_id must not be null
				$q-> where("(CASE WHEN `c2i`.visibility = 'org' THEN `c2i`.org_id = ? ELSE 1 END)", $org_id);
				break;
			// No visibility filter for admin, just show everything (including the same contact linked multiple times for different organizations)
			// Organization name is joined for display
			case 'unrestricted':
				$q-> table(MVC_Core::$db-> table('org'), QueryFactory::autoalias_Add_Prefix("name,type_id", '_org_'), 'left', '`c2i`.org_id = `org`.id');

				break;
			// Error, invalid visibility type
			default:
				return false;
		}

		if(!is_null($full_contact_info))
			$q-> table(array(MVC_Core::$db-> tab['ctx'], 'ctx'), QueryFactory::autoalias_Add_Prefix("org_id,user_id,type,ctime,email,name".($full_contact_info? ",xdata": ""), '_ctx_'), 'left', '`c2i`.contact_id = `ctx`.id');

		$r = MVC_Core::$db-> query($q);

		if(!$r || count($r) == 0)
			return array(); // Nothing found (or DB error)

		$r-> split_by_prefixes = array(
			// extra tables
			'contact'=> "_ctx_",
			'org'=> "_org_"
		);

		// Some post-processing
		$all = array();
		foreach($r as $row) {
			$row['contact_id'] = (int)$row['contact_id'];
			if(!empty($row['cnv_id']))
				$row['cnv_id'] = (int)$row['cnv_id'];
//			if(!empty($row['org_id']))
//				$row['org_id'] = (int)$row['org_id'];
//			if(!empty($row['user_id']))
//				$row['user_id'] = (int)$row['user_id'];

			if($full_contact_info)
				$row['contact']['xdata'] = @json_decode($row['contact']['xdata'], true);
			$all[] = $row;
		}
		return $all;
	}

	/**
	 * Updates parameters for an existing link.
	 * -- Public and Shared links have an unique combination of $issue_id and $contact_id, however Org links must have $org_id specified
	 *
	 * @param int $issue_id
	 * @param int $contact_id
	 * @param array $new_params
	 * @param int $org_id Required when "visibility" has been set to "org" / Ignored otherwise
	 */
	public function con2iss_Update($issue_id, $contact_id, $new_params = array(), $org_id = null)
	{
		// Any key outside the correct fields is discarded (to avoid database errors)
		$fields = array('role', 'label', 'cnv_id', 'visibility', 'org_id', 'user_id');
		foreach($new_params as $field=> $value)
			if(!in_array($field, $fields))
				unset($new_params[$field]);

		// Values check
		if(isset($new_params['role']) && !in_array($new_params['role'], array('submitter', 'transfer', 'recipient', 'assignee', 'custom')))
			unset($new_params['role']);
		if(isset($new_params['visibility']) && !in_array($new_params['visibility'], array("org", "shared", "public")))
			unset($new_params['visibility']);
		if(isset($new_params['label']) && strlen($new_params['label']>40))
			$new_params['label'] = substr($new_params['label'], 0, 40);

		// NOTE: It not possible to transfer a contact link from one organization to another, once a contact link is created it stays with the same org_id until deleted.

		$q=QueryFactory::update()
		-> table(array(MVC_Core::$db-> tab['c2i'], 'c2i'))
		-> values($new_params)
		-> where("`issue_id` = ?", $issue_id)
		-> where("`contact_id` = ?", $contact_id);

		if(isset($new_params['visibility']) && $new_params['visibility'] == 'org')
			$q-> where("`org_id` = ?", $org_id);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Deletes an existing contact link
	 *
	 * @param int $issue_id
	 * @param int $contact_id
	 * @param int $org_id Organization ID for org. only contacts
	 */
	public function con2iss_Delete($issue_id, $contact_id, $org_id = null, $only_role = null)
	{
		$q=QueryFactory::delete()
		-> table(array(MVC_Core::$db-> tab['c2i'], 'c2i'))
		-> where("`issue_id` = ?", $issue_id)
		-> where("`contact_id` = ?", $contact_id);

		// When org_id is specified, add the org_id to the conditions but only for contacts with visibility "org"
		if(!is_null($org_id))
			$q-> where("(CASE WHEN `visibility` = 'org' THEN `org_id` = ? ELSE 1 END)", $org_id);

		// Only deletes links that have a certain role (Even if contact id matches)
		if(!is_null($only_role))
			$q-> where("`role` = ?", $only_role);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Deletes multiple contacts linked to an issue (either all or just those added by a certain organization)
	 *
	 * @param int $issue_id
	 * @param int $org_id If specified, only the links belonging to a certain organization are deleted
	 */
	public function con2iss_Cleanup($issue_id, $org_id = null)
	{
		$q=QueryFactory::delete()
		-> table(array(MVC_Core::$db-> tab['c2i'], 'c2i'))
		-> where("`issue_id` = ?", $issue_id);

		if(!is_null($org_id))
			$q-> where("`org_id` = ?", $org_id);

		$r = MVC_Core::$db-> query($q);
		if($r && MVC_Core::$db-> affected_rows > 0) {
			// Deleted successfully, substract from "count_ctx" value for the issue
			$m_issue = new App_Model_Issue();
			$m_issue-> update($issue_id, array(
				'count_ctx'=> "`count_ctx` - ".MVC_Core::$db-> affected_rows
			), null, null, true);
		}
		return $r;
	}


	/**
	 * Checks if the connection parameters are acceptable
	 * @param string $host
	 * @param string $smtp_encryption
	 * @param string $smtp_auth
	 * @param array $auth_details
	 */
	private function server_CheckConfig($host, $smtp_encryption, $smtp_auth, $auth_details)
	{
		// 1. Host, it must not contain any invalid characters (only valid as IP address or DNS entry)_
		$host_valid = false;
		if(filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
			// Not an IP, check if DNS value
			if	(	preg_match('/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i', $host) //valid chars check
				&&	preg_match('/^.{1,253}$/', $host) //overall length check
				&&	preg_match('/^[^\.]{1,63}(\.[^\.]{1,63})*$/', $host)) {
					$ip = gethostbyname($host);
					if(!$ip != $host)
						$host_valid = true; // Host found and has an IP address
				}
		} else
			$host_valid = true;
		if(!$host_valid)
			return MVC_Core::$debug-> dump("SMTP host is not a vaild domain or IP address", __METHOD__, null, 'error');

		$encryption_valid = in_array($smtp_encryption, array('none', 'ssl', 'tls'));
		if(!$encryption_valid)
			return MVC_Core::$debug-> dump("SMTP Encryption not supported", __METHOD__, null, 'error');

		$auth_valid = in_array($smtp_auth, array('anon', 'plain', 'login', 'cram-md5')); // xoauth2 not supported because needed libraries do not support currently used PHP version (5.6 or higher needed)
		if($auth_valid && $smtp_auth!='anon') {
			// Also check if credentials are present
			if(empty($auth_details['username']) || empty($auth_details['password'])) {
				$auth_valid = false;
			} elseif(strpos($auth_details['username'], ' ')!==false) {
				// Username must not contains spaces inside it
				$auth_valid = false;
			} elseif($auth_details['username'] == $auth_details['password']) {
				// Same username and password ? Must be a mistake...
				$auth_valid = false;
			}
		}

		if(!$auth_valid)
			return MVC_Core::$debug-> dump("SMTP Authentication method not supported or missing/incorrect credentials", __METHOD__, null, 'error');

		return true;
	}

	/**
	 * Automatic port (if missing/null)
	 * @param unknown $smtp_encryption
	 */
	private function server_AutoPort($smtp_encryption)
	{
		$auto_port = array(
			'none'=> 25,
			'ssl'=> 465,
			'tls'=> 587
		);
		return $auto_port[$smtp_encryption];
	}

	/**
	 * Creates a server configuration for an e-mail (server settings associated to a contact)
	 * @param int $contact_id ID of the contact for which this server config is created (can only have one configuration for a server address) - Must be of type "user_self", "org_self" or "dep_self"
	 * @param string $host_or_method E-mail server host or the keywords "local" or "spf"
	 * @param string $smtp_encryption One of "none", "ssl", "tls" (Note - The "ssl" seems to be outdated)
	 * @param string $smtp_auth  One of "anon", "plain", "login", "cram-md5", "xoauth2" ( xoauth2 not implemented )
	 * @param array $auth_details Can be null, but if $smtp_auth is other than "anon", it must contain at least [username] and [password], or extra information for the other types
	 * @param number $port 25 is normal SMTP, 465 for SSL, 587 for TLS , but other custom ones can be used. If missing, it's determined based on $smtp_encryption
	 * @param string $method "smtp" or "spf" (which doesn't really connect to SMTP, but can be used with certain accounts)
	 * @return int ID of the server config
	 */
	public function server_Create($contact_id, $host_or_method, $smtp_encryption = 'none', $smtp_auth ='anon', $auth_details = null, $port = null)
	{
		if($host_or_method == 'local' || $host_or_method == 'spf') {
			// When using SPF there are no settings, just a marker that e-mail is being sent under own address
			$q=QueryFactory::insert()
			-> table(MVC_Core::$db-> tab['mtp'])
			-> values(array(
				'contact_id'=> $contact_id,
				'method'=> $host_or_method,
				'tested'=> 0,
				'user_id'=> MVC_Core::$ss-> user['id']
			));
			return MVC_Core::$db-> query($q);
		}

		// Check full SMTP settings
		$check = $this-> server_CheckConfig($host_or_method, $smtp_encryption, $smtp_auth, $auth_details);
		if(!$check)
			return false; // Unable to create because something is wrong with parameters
		if(empty($port))
			$port = $this-> server_AutoPort($smtp_encryption);

		$q=QueryFactory::insert()
		-> table(MVC_Core::$db-> tab['mtp'])
		-> values(array(
			'contact_id'=> $contact_id,
			'method'=> 'smtp',
			'tested'=> 0,
			'test_dt'=> null,
			'user_id'=> MVC_Core::$ss-> user['id'],
			'host'=> $host_or_method,
			'port'=> $port,
			'smtp_encryption'=> $smtp_encryption,
			'smtp_auth'=> $smtp_auth,
			'username'=> $auth_details['username'],
			'password'=> $auth_details['password']
			// TODO - Encrypt this with openssl_encrypt()
			// ... it should depend on something only the user knows, and for example allow decryption only after authenticated in session
			// The idea is that if the database is leaked, the E-mail passwords cannot be decrypted without actually knowing the user's login password
			// which cannot be decrypted because login us using sha256 hashing
		));

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Returns the server information (note, this one returns the username/password and other credentials info! Use with caution)
	 * @param int $contact_id
	 * @param bool $full_data Read complete server settings ( warning, returns the password and connection log ! Only use internally for comparasion purposes )
	 * @return array Server info if found. False if entry not found
	 */
	public function server_Read($contact_id, $full_data = false)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['mtp'], $full_data? null: array('method', 'tested', 'test_dt', 'host', 'port', 'smtp_encryption', 'smtp_auth', 'username')) // Add the extra parameters if necessary
		-> where("`contact_id` = ?", $contact_id);

		$r = MVC_Core::$db-> query($q);
		if(!$r || count($r)==0)
			return false;

		return $r-> current();
	}

	/**
	 * Tests the SMTP connection and updates the server configuration, marking it as "tested"
	 * @param int $contact_id
	 * @param bool $retest Retest anyway, even if previously tested
	 * @return array|bool Returns the connection info if test already done or successful. False if test failed or connection not found
	 */
	public function server_Test($contact_id, $retest = false)
	{
		$srv = $this-> server_Read($contact_id, true);
		if($srv['method'] == 'smtp' && (int)$srv['tested']===0 || $retest) {
			// Found but not tested (or forced), so perform the test
			// Start capturing output (connection log)
			ob_start();

			$smtp = new SMTP;
			$smtp-> do_debug = SMTP::DEBUG_CONNECTION;
			try {
				// Begin with the assumption that everything will go well. If exception is thrown, $r will become false
				$r = true;

				//Connect to an SMTP server
				if (!$smtp-> connect($srv['host'], $srv['port'])) {
					throw new Exception('Connect failed');
				}
				//Say hello
				if (!$smtp-> hello(gethostname())) {
					$err = $smtp-> getError();
					throw new Exception('EHLO failed: '. $err['error']);
				}
				//Get the list of ESMTP services the server offers
				$e = $smtp-> getServerExtList();
				//If server can do TLS encryption, use it
				if ($srv['smtp_encryption']=='tls' && is_array($e) && array_key_exists('STARTTLS', $e)) {
					$tlsok = $smtp-> startTLS();
					if (!$tlsok) {
						$err = $smtp-> getError();
						throw new Exception('Failed to start encryption: '. $err['error']);
					}
					//Repeat EHLO after STARTTLS
					if (!$smtp-> hello(gethostname())) {
						$err = $smtp-> getError();
						throw new Exception('EHLO (2) failed: '. $err['error']);
					}
					//Get new capabilities list, which will usually now include AUTH if it didn't before
					$e = $smtp-> getServerExtList();
				}
				//If server supports authentication, do it (even if no encryption)
				if ($srv['smtp_auth'] != 'anon' && is_array($e) && array_key_exists('AUTH', $e)) {

					if($srv['smtp_auth'] == 'xoauth2') {
						// NO CLUE !!

					} else
						$OAuth = null;

					// Basic SMTP Auth
					if ($smtp-> authenticate($srv['username'], $srv['password'], strtoupper($srv['smtp_auth']), '', '', $OAuth))
						$r = true;
					else {
						$err = $smtp-> getError();
						throw new Exception('Authentication failed: '. $err['error']);
					}
				}
			} catch (Exception $e) {
				echo "\n". sprintf("SMTP error: %s", $e-> getMessage());
				$r = MVC_Core::$debug-> dump($e-> getMessage(), __METHOD__, null, 'error');
			}
			// Whatever happened, always close the connection.
			$smtp-> quit(true);

			// End capture output, store this with the server connection
			$srv_update = array(
				'test_dt'=> date("Y-m-d H:i:s"),
				'test_log'=> ob_get_clean(),
				'tested'=> (bool)$r // Test result can go both ways...
			);

			// Test successful, update the record
			if($r)
				MVC_Core::$debug-> dump("SMTP Connection for {$contact_id} successful !", __METHOD__);

			$q=QueryFactory::update()
			-> table(MVC_Core::$db-> tab['mtp'])
			-> where("`contact_id` = ?", $contact_id)
			-> values($srv_update);
			MVC_Core::$db-> query($q);

			$srv = array_merge($srv, $srv_update);

			// And return info
			return $srv;

		} elseif(is_array($srv))
			return $srv; // Found and already tested (or not using SMTP)
		else
			return false;
	}

	/**
	 * Updates server info with new information. The update resets the "tested" parameter, requiring the connection to be tested again.
	 * @param int $contact_id
	 * @param string|boolean $host_or_method E-mail server host or if "FALSE", it means - TURN OFF (reverse of Test success), but doesn't delete any information; If set to "spf" or "local", it disables SMTP
	 * @param string $smtp_encryption One of "none", "ssl", "tls" (Note - The "ssl" seems to be outdated)
	 * @param string $smtp_auth  One of "anon", "plain", "login", "cram-md5", "xoauth2" ( xoauth2 not implemented )
	 * @param array $auth_details Can be null, but if $smtp_auth is other than "anon", it must contain at least [username] and [password], or extra information for the other types
	 * @param number $port 25 is unencrypted, 465 for SSL, 587 for TLS , but other custom ones can be used. If missing, it's determined based on $smtp_encryption
	 * @return bool Update success/fail
	 */
	public function server_Update($contact_id, $host_or_method, $smtp_encryption = 'none', $smtp_auth ='anon', $auth_details = null, $port = null)
	{
		if($host_or_method === 'local' || $host_or_method === 'spf') {
			$q=QueryFactory::update()
			-> table(MVC_Core::$db-> tab['mtp'])
			-> where("`contact_id` = ?", $contact_id)
			-> values(array(
				'method'=> $host_or_method,
				'tested'=> 0
			));
			return MVC_Core::$db-> query($q);
		}

		$check = $this-> server_CheckConfig($host_or_method, $smtp_encryption, $smtp_auth, $auth_details);
		if(!$check)
			return false; // Unable to update because something is wrong with parameters
		if(empty($port))
			$port = $this-> server_AutoPort($smtp_encryption);

		$upd_data = array(
			'method'=> 'smtp',
			'tested'=> 0,
			'test_dt'=> null,
			'host'=> $host_or_method,
			'port'=> $port,
			'smtp_encryption'=> $smtp_encryption,
			'smtp_auth'=> $smtp_auth,
			'username'=> $auth_details['username'],
			'password'=> $auth_details['password']
			// TODO - Encrypt this with openssl_encrypt()
			// ... it should depend on something only the user knows, and for example allow decryption only after authenticated in session
			// The idea is that if the database is leaked, the E-mail passwords cannot be decrypted without actually knowing the user's login password
			// which cannot be decrypted because login us using sha256 hashing
		);

		$q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['mtp'])
		-> values($upd_data)
		-> where("`contact_id` = ?", $contact_id);

		return MVC_Core::$db-> query($q);
	}

	/**
	 * Reads the connection info and if found in working state, prepares the PHPMailer object with the correct parameters
	 * @param int $contact_id
	 * @param PHPMailer $mailer
	 * @param int $debug SMTP Debugging 0 = off (for production use) / 1 = client messages / 2 = client and server messages
	 * @return bool Prepare succes/fail
	 */
	public function server_PrepConnection($contact_id, PHPMailer &$mailer, $debug = 0)
	{
		$srv = $this-> server_Read($contact_id, true);
		if($srv===false) {
			return 'local';
		} elseif($srv['method'] == 'smtp' && (int)$srv['tested']===0) {
			MVC_Core::$debug-> dump("SMTP server connection for {$contact_id} untested, unable to use. Falling back to mail()", __METHOD__, null, 'error');
			return 'local';
		} elseif($srv['method'] != 'smtp') {
			return $srv['method'];
		}

		$mailer-> isSMTP();
		$mailer-> SMTPDebug = $debug;
		$mailer-> Host = $srv['host'];
		// SMTP port number - likely to be 25, 465 or 587
		$mailer-> Port = $srv['port'];
		// SMTP connection encryption ?
		if($srv['smtp_encryption'] != 'none') {
			$mailer-> SMTPSecure = $srv['smtp_encryption'];
			// TODO: Support SSL parameters ?
		}

		// SMTP authentication ?
		if($srv['smtp_auth'] != 'anon') {
			$mailer-> SMTPAuth = true;
			// Username/Password to use for SMTP authentication
			$mailer-> Username = $srv['username'];
			$mailer-> Password = $srv['password'];
		}

		return 'smtp';
	}

	/**
	 * Deletes an existing connection record (usually when the contact E-mail address is changed or removed)
	 * @param int $contact_id
	 */
	public function server_Delete($contact_id)
	{

	}
        
        public function getContactDetByUser($id, $read_xdata = true, $read_note = false)
	{
		$q= QueryFactory::select()
		-> where("`user_id` = ?", $id)
		-> limit(1);

		if($read_xdata)
			$q-> table(MVC_Core::$db-> tab['ctx']);
		else
			$q-> table(MVC_Core::$db-> tab['ctx'], 'id,state,org_id,dep_id,user_id,type,ctime,email,name,count_msgs,note_id'); // All fields except xdata (which might be the longest by far)

		$r = MVC_Core::$db-> query($q);
		if($r && count($r)==1) {
			$contact = $r-> current();
			// decode xdata
			if($read_xdata)
				$contact['xdata'] = @json_decode($contact['xdata'], true);

			if($read_note) {
				// TODO: Read the associated note
			}

			return $contact;
		} else
			return false; // not found or other DB error
	}

}