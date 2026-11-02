<?php
/**
 * Editing an an user (account info, department membership, per department settings and access overrides, user's custom data, assigned issues, assigned tasks (quicklists))
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2018.10
 */
class App_Ctrl_Manager_User extends App_Ctrl_Base
{
	public static $_module_name = "User Manager";

	/**
	 * Department model, used by some actions of this module
	 * @var App_Model_Department
	 */
	private $dep;

	/**
	 * Contact model, which is necessary for user display/editor to work properly
	 * @var App_Model_Contact
	 */
	private $con;

	/**
	 * Communication model, used to inform users of account changes (creation, disabling, password change, account id change, etc.)
	 * - Initialized only demand
	 *
	 * @var App_Model_Comm
	 */
	private $comm;

	/**
	 * User contact labels
	 * @var App_Model_Commlabel
	 */
	private $lab;

	public function __construct()
	{
		parent::__construct(true);

		$this-> dep = $this-> factory_Model("Department", "dep");
		$this-> con = $this-> factory_Model("Contact", "con");
	}

	/**
	 * Index action for the User management
	 */
	public function action_Index(MVC_Request $in)
	{
		// This module is only available for user managers
		if(!$this-> user-> test_ACL('privileges', 'org_user_manager')) {
			$this-> evan-> add("You are not allowed to manage users.", 'lock');
			return $this-> redirect("/manager/organization#users");
		}

		$this-> CFG['components'] = array("modal", "contact_editor");
		array_push($this-> CFG['modules'], "user/_manager");
		$this-> CFG['jqwidgets'][] = array("switchbutton", "tabs", "splitter", "scrollbar", "listbox", "dropdownlist");
		$this-> CFG['jqwidgets'][] = array("validator", "input", "navigationbar", "calendar", "datetimeinput");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete");
		$this-> CFG['jqwidgets'][] = array("grid.edit");
		$this-> CFG['xjs'] = array(
			'jqform' => true,
			'tinymce'=> true,
			'pwstrength'=> true
		);

		// prepare output
		$this-> prepare_Header("User Manager");

		// read basic account information - Only used for the "title", which goes outside the tabs - Everything else is read on demand when loading the tab
		$account = $this-> user-> read($in-> id, array('user', 'organization'));

		if(!$account) {
			// Ooops, attempting to access an user account which does not exist.
			$this-> evan-> add("The user ID you are attempting to edit does not exist. Cannot proceed.");
			return $this-> redirect("/manager/organization#users");
		}

		if(!$this-> user-> is_Unrestricted()) {
			// Non-admin users cannot load and see users from another organization
			if($account['org_id'] != MVC_Core::$ss-> user['org_id']) {
				$this-> evan-> add("This user cannot be edited by yourself because it belongs to another organization.");
				return $this-> redirect("/manager/organization#users");
			}
		}

		// some information about the user being managed (edited) is sent to JS as well
		$this-> CFG['user_manager'] = array(
			'id'=> (int)$account['id'],
			'status'=> $account['status'],
			'org_id'=> (int)$account['org_id'],
			'cdep_id'=> (int)$account['cdep_id'],
			'contact'=> array(
				'id'=> (int)$account['contact_id'],
				'name'=> $account['name'],
				'email'=> $account['email']
			)
		);

		// Load the issue types which are "ON" for an organization ( from the Setup tab )
		$ist_list_r = $this-> org-> ist_Get_List($account['org_id'], true);
		$ist_source = array();
		foreach($ist_list_r as $row) {
			$ist_source[] = array(
				'id'=> (int)$row['id'],
				'name'=> $row['name'],
				'key'=> $row['key']
			);
		}
		$this-> CFG['user_manager']['ist_source'] = $ist_source;

		// Load the list of groups this organization is connected to (used in several places)
		$org_grp_r = $this-> org-> get_Groups($account['org_id'], null, true);
		$groups_source = array();
		if($org_grp_r) {
			foreach($org_grp_r as $grp) {
				$groups_source[] = array(
					'id'=> (int)$grp['id'],
					'name'=> $grp['name'],
					'type'=> $grp['type'],
					'count_el'=> (int)$grp['count_el']
				);
			}
		}
		$this-> CFG['user_manager']['groups_source'] = $groups_source;

		// Tabs related to user editor module
		$all_available_tabs = array(
			// Info/Configure tab, shows an overview of the user (and also the form to edit the user and his active department)
			array(
				'id'=> "tab_configure",
				'title'=> "User configuration",
				'icon'=> "user_gray.png",
				'privilege'=> true, // Allow it, since "org_user_manager" is needed to open the module itself.
				'spacey'=> true
			),
			array(
				'id'=> "tab_vcard",
				'title'=> "Contact editor",
				'icon'=> "vcard.png",
				'privilege'=> "org_contacts_manager", // The ability to edit organization contacts is also required to edit the user's contact information
				'spacey'=> true
			),
			// ACLs tab, allows editing of user rights in relation to the active department (which can be set in the "configure"), or directly to organization - No active department
			array(
				'id'=> "tab_acls",
				'title'=> "User level access",
				'icon'=> "lock.png",
				'privilege'=> "org_access_manager",
				'spacey'=> true
			),
			// Lists the user's profile entries, which can be deleted ( so they revert to default ) - Used to reset user setttings, grid columns or panels config to the organization defaults
			array(
				'id'=> "tab_profile_records",
				'title'=> "Profile records",
				'icon'=> "table_gear.png",
				'privilege'=> false, // Only admin has access to this (for now)
				'spacey'=> true
			)
			//, // Temporarely disabled, since this doesn't contain anything yet.
// 			array(
// 				'id'=> "tab_assignments",
// 				'title'=> "Assignments",
// 				'icon'=> "assigned.png",
// 				'privilege'=> "issue_assign_to" // Can only see assignments of other users if allowed to assign stuff to users
// 			)
			// TODO: Other tabs?
		);

		// The actual tabs that will be shown, depending on access rights
		$user_tabs = array();
		foreach($all_available_tabs as $tab) {
			// General case, tab visibility depends on a standard privilege
			if($tab['privilege'] === true || $this-> user-> test_ACL('privileges', $tab['privilege']))
				$user_tabs[] = $tab;
		}

		$this-> module = array(
			'icon'=> "user1.png",
			'icon_alt'=> "(user)",
			'title'=> $account['name'], 'title_para'=> $account['organization']['name'],
			'description'=> "Configure user information and User-level access",
			'tabs'=> $user_tabs,
		);

		//== Contact editor JS data ==//

		// Read the contact field groups and settings so the dynamic contact editor can be initialized
		MVC_NLS::load_Groups("contact");
		$fields_structure = MVC_NLS::get_Object("contact", true);

		// Process the fields stucture a bit before sending it to JS
		foreach($fields_structure as &$group) {
			foreach($group['fields'] as $field=> $string) {
				if(strpos($string, "~"))
					$group['fields'][$field] = explode("~", $string);
			}
		}

		$this-> CFG['user_manager']['vcard_fields_structure'] = $fields_structure;

		// which body template to use, and run the view
		$this-> site_section = "general_tabs_container.html";
		$this-> view("private.html");
	}

	/**
	 * Loads contact information for editing or creating a new contact
	 *
	 * @param MVC_Request $rq
	 */
	private function load_Tab_Vcard(MVC_Request $rq)
	{
		// Check if a user_id is present in the request.
		if(!$rq-> user_id)
			return;

		// Read via User ID or Contact ID
		$account = $this-> user-> read($rq-> user_id, array('user', 'contact'), false);
		if(isset($rq-> contact_id)) {
			$contact = $this-> con-> read($rq-> contact_id);
		} else {
			$contact = $account['contact'];
			$contact['id'] = $account['contact_id'];
			$contact['user_id'] = $rq-> user_id;
			$contact['name'] = $account['name'];
			$contact['email'] = $account['email'];
		}

		if(!$contact)
			// Not found...
			return;

		// Prepare the contact information
		$contact['xdata'] = $this-> con-> decode_Xdata($contact['xdata']);
		// This tab is always in "edit" mode
		$contact['_edit'] = true;

		$this-> contact = $contact;

		// Load SMTP/SPF settings (or prepare defaults based on the $contact)
		$smtp_server = $this-> con-> server_Read($contact['id']);

		if(empty($smtp_server)) {
			list($mailbox, $domain) = explode('@', $contact['email']);
			// TODO: Guess based on E-mail and a "guess" config file

			// If nothing, just default to: smtp.(user's domain),TLS/587/user's e-mail as "username"
			$smtp_server = array(
				'method'=> "local",
				'tested'=> 0,
				'host'=> "smtp.".$domain,
				'smtp_encryption'=> "tls",
				'port'=> 587,
				'smtp_auth'=> "login",
				'username'=> $contact['email']
			);
		}

		$this-> smtp = $smtp_server;

		// Sends to the frontend the fact that the contact has loaded correctly and the contact editor can be initialized
		$jsondata = array();
		$jsondata['tab_vcard'] = array(
			'ready'=> true,
			'send_mtd'=> $smtp_server['method'],
			'smtp_active'=> (int)$smtp_server['tested']==1
		);

		// Aliases, if present, there's a box that can allow selecting one to be edited (instead of the main contact)
		// Determine if the user has multiple aliases ( contacts of type user_self ). If yes the "My aliases" button will be visible and allow switching to one of them
		$aliases = $this-> con-> get_List(null, $rq-> user_id, 'user_self', array('contact'), "`ctx`.ctime ASC");
		if($aliases) {
			if(count($aliases)>1)
				$this-> has_aliases = true;

			$alias_js = array();
			$alias_index = null;
			foreach($aliases as $idx=> $alias) {
				$alias_js[] = array(
					'id'=> (int)$alias['id'],
					'name'=> $alias['name'],
					'email'=> $alias['email']
				);
				if($contact['email'] == $alias['email'])
					$jsondata['tab_vcard']['alias_index'] = $idx;
			}
			$jsondata['tab_vcard']['aliases'] = $alias_js;
		}

		if($contact['id'] == $account['contact_id']) {
			$jsondata['tab_vcard']['primary'] = true;
			$this-> special_info = array(
				'title'=> "Primary contact",
				'description'=> "This is the user's primary contact, directly linked to his/her account.
					A change here will also change the user's E-mail (also used for password recovery), but will not affect the Account ID.

					To change any alternate address, use the <b>Aliases</b> button"
			);
		} else {
			$jsondata['tab_vcard']['primary'] = false;
			$this-> special_info = array(
				'title'=> "Editing an Alias",
				'description'=> "Using aliases, users can send E-mails using an alternate address.

					Useful when a common E-mail is being used by multiple users."
			);
		}

		// Hybrid response (HTML+JSON)
		$this-> send_JSON_Headers($jsondata);
	}

	/**
	 * Loads and prepares HTML document which will become the content of one of the tabs for the My Organization module
	 *   Loads specific data on demand, for the dynamic content in that tab.
	 */
	public function action_Load_Tab_HTML(MVC_Request $rq)
	{
		$this-> prepare_Template_ACLs();

		switch($rq-> tab) {
			case 'tab_configure':
				// Read the compelte user's data, full ACLs calc, this is a combo info/editor page
				$account = $this-> user-> read($rq-> user_id, array('user', 'organization', 'department'));
				$strings = array();
				$strings['privileges_mode'] = "Displaying the user's active privileges in the organization";
				if(isset($account['department']))
					$strings['privileges_mode'] .= " and his/her current department";

				if(isset($account['is_admin']) || isset($account['is_supervisor'])) {
					// Has no special privileges/lists - Instead, has access to everything.
				} else {
					// Assamble the correct list of ACLs (privileges, access ids):
					MVC_NLS::load_Groups("acls");
					$acls_text = MVC_NLS::get_Object("acls", true);

					// Privileges (and translated text)
					if(!empty($account['acls']['privileges'])) {
						// Assamble the correct list of ACLs (privileges, access ids):
						$acls_text = MVC_NLS::get_Object("acls", true);
						$old_privs = array();
						$privileges_copy = $account['acls']['privileges'];
						$account['acls']['privileges'] = array();
						foreach($privileges_copy as $privilege) {
							if(!isset($acls_text['privileges'][$privilege]))
								$old_privs[] = $privilege;
							else
								$account['acls']['privileges'][$privilege] = $acls_text['privileges'][$privilege];
						}
						if(!empty($old_privs))
							MVC_Core::$debug-> dump($old_privs, "Invalid privilege strings detected", null, 'notice');
						$this-> privilege_groups = $acls_text['privilege_groups'];
					} else
						unset($account['acls']['privileges']);
				}
                //check if oath key is present or not
                if (is_null($account['oath_key']) || $account['oath_key'] == '') {
                    $account['oath_key'] = 1;
                } else {
                    $account['oath_key'] = 0;
                }

				// Read the list of departments the user is a member of, for the active department selector
				$user_deps = $this-> dep-> get_User_Dep_Relation_List($account['id'], $account['org_id'], false); // false = quick mode (id, name)

				// In case no departements were returned, it means that that the user is not member of any yet - nothing to select, do not show the department selector
				if($user_deps) {
					// replace the key "disabled" with "_disabled" as it affects jqxDropDownList
					foreach($user_deps as &$entry) {
						$entry['_disabled'] = $entry['disabled'];
						unset($entry['disabled']);
					}

					// insert the fake "no department" entry in the list, to allow the user to be removed from a department
					array_unshift($user_deps, array(
						'comment'=> null,
						'_disabled'=> 0,
						'hidden'=> 0,
						'id'=> 0,
						'name'=> "(none active)"
					));

					$this-> has_deps = true;
				}

				// Interface features for logged in user?
				$features = array();
				if(MVC_Core::$ss-> user['status'] == 'admin' || MVC_Core::$ss-> user['status'] == 'supervisor') {
					// Some special users cannot be edited by normal users, even if they have the right to edit users. Only admin can change these types of users.
					if($account['status'] == 'admin' || $account['status'] == 'supervisor' || $account['status'] == 'jumper')
						$features['status_ops'] = "view";
					else
						$features['status_ops'] = "edit";
				}

				// Finally, template vars
				$this-> account = $account;
				$this-> strings = $strings;
				$this-> features = $features;

				if(isset(MVC_Core::$ss-> perspective))
					$this-> perspective = MVC_Core::$ss-> perspective;

				// Hybrid response (HTML+JSON)
				$this-> send_JSON_Headers(array(
					'dataSources'=> array( // => self.config.dataSources
						'user_deps'=> $user_deps, // Possible choices for the active department selector dropdown (might be empty, if there are no choices)
						'features'=> $features
					)
				));
				break;

			// Contact viewer/editor for the selected user
			case 'tab_vcard':
				$this-> load_Tab_Vcard($rq);
				break;

			case 'tab_acls':
				// ACLs strings, used by the template
				MVC_NLS::load_Groups("acls");
				$this-> acls = MVC_NLS::get_Object("acls", true);

				// Load the ACLs overrides for the user in the active department, if not-null
				if($rq-> dep_id > 0) {
					$u2d = $this-> dep-> read_User_Dep_Relation($rq-> dep_id, $rq-> user_id);

					// due to the fact that the javascript expects the keys "privileges" and "access_ids", and not the suffixed ones (with _or), change the name
					$u2d['acls'] = array();
					$u2d['acls']['privileges'] = $u2d['privileges_or'];
					$u2d['acls']['access_ids'] = $u2d['access_ids_or'];

					$this-> superuser = $this-> user-> is_Unrestricted();
					$this-> enable_org_section = true;

					// Hybrid response (with JSON)
					$this-> send_JSON_Headers(array(
						'acls'=> $u2d['acls'],
						'acls_overrides'=> !(empty($u2d['privileges_or']) && empty($u2d['access_ids_or']))
					));
				}
				break;

			case 'tab_profile_records':
				// Nothing to do here, for now...
				break;
		}
		$template_name = $rq-> tab. ".html";

		// This is an ajax request, but with a HTML response, so initialize the template object on demand
		$this-> init_Template_System();
		echo $this->_drytpl-> render_File("user". DS. "manager_tabs". DS. $template_name, $this->_out);
	}

	/**
	 * Returns detailed user information (account handle, name, e-mail, ACLs)
	 * -- Privileges will be shown in a grid, translated from the keys to their full-length names
	 * -- For known access IDs the related information is also loaded ( to show the actual content, instead of a number )
	 *      Example, the issue types that the user has access to
	 * Returns HTML
	 */
	public function action_Get_Details(MVC_Request $in)
	{
		// Read account information with or without calculating ACLs, and also prepare some variables for output
		$strings = array();
		switch($in-> acls) {
			case "calculated":
				// reads the user's data and calculates the active ACLs in relation to his active department in the organization - All data is needed so ACLs can be properly calculated
				$account = $this-> user-> read($in-> id, array('user', 'contact', 'organization', 'department'));
				$strings['privileges_mode'] = "Displaying the user's active privileges in the organization";
				if(isset($account['department']))
					$strings['privileges_mode'] .= " and his/hers current department";
				break;
			case 'dep_or':
				// reads the user's data without ACLs and append user level overrides (3rd level on access structure)
				$account = $this-> user-> read($in-> id, array('user', 'contact'), false);
				// Read the user2dep relation and add the data to $account['acls']
				$u2d = $this-> dep-> read_User_Dep_Relation($in-> dep_id, $in-> id);
				if(!is_null($u2d['privileges_or']))
					$account['acls']['privileges'] = $u2d['privileges_or'];

				$strings['privileges_mode'] = "Displaying privileges overrides for the user in this department";
				break;
			default:
				// No ACLS, just basic info
				$account = $this-> user-> read($in-> id, array('user', 'contact'), false);
		}
		$this-> strings = $strings;

		MVC_NLS::load_Groups("contact,acls");

		// Prepare the contact information variable
		$account['xdata'] = $this-> con-> decode_Xdata($account['contact']['xdata'], false);

		// Privileges (and translated text)
		if(!empty($account['acls']['privileges'])) {
			// Assamble the correct list of ACLs (privileges, access ids):
			$acls_text = MVC_NLS::get_Object("acls", true);
			$old_privs = array();
			$privileges_copy = $account['acls']['privileges'];
			$account['acls']['privileges'] = array();
			foreach($privileges_copy as $privilege) {
				if(!isset($acls_text['privileges'][$privilege]))
					$old_privs[] = $privilege;
				else
					$account['acls']['privileges'][$privilege] = $acls_text['privileges'][$privilege];
			}
			if(!empty($old_privs))
				MVC_Core::$debug-> dump($old_privs, "Invalid privilege strings detected", null, 'notice');
			$this-> privilege_groups = $acls_text['privilege_groups'];
		}

		// The $account variable has been filled with information ready for display
		$this-> account = $account;

		// This is an ajax request, but with a HTML response, so initialize the template object on demand
		$this-> init_Template_System();
		$this-> view("user". DS. "details.html");
	}

	/**
	 * Update user information (name, e-mail, signature, etc.)
	 */
	public function action_Update_User(MVC_Request $post)
	{
		// This module is only available for user managers
		if(!$this-> user-> test_ACL('privileges', 'org_user_manager'))
			return $this-> send_Fail_JSON("You are not allowed to manage users.");

		if(!$post-> user_id)
			return $this-> send_Fail_JSON("Unable to update, no user ID specified.");

		// First, read the user to see if he/she actually exists
		$account = $this-> user-> read($post-> user_id);
		if(!$account)
			return $this-> send_Fail_JSON("The user which you are attempting to update does not exist (anymore).");

 		if(isset(MVC_Core::$ss-> user['is_admin'])) {
 			// Admins can edit any user from any organization
 		} else {
			// Normal users even if they have the "org_user_manager" privilege, can only edit users which are within their own organization
			if($account['status'] == 'admin' || $account['status'] == 'supervisor' || $account['status'] == 'jumper')
				return $this-> send_Fail_JSON("Only the system administrators can modify this super-user.");

			// Check if edited user is in the same organization as the active session user
			if($account['org_id'] != MVC_Core::$ss-> user['org_id'])
				return $this-> send_Fail_JSON("This user cannot be edited by yourself because it belongs to another organization.");
 		}

 		// Create or Update the associated contact with information from $post-> usr (name, email)
 		if(!$account['contact_id']) {
 			// There is no associated contact, create one.
 			$con_iid = $this-> con-> create('user_self', $account['org_id'], array(
 				'name'=> trim($post-> usr['name']),
 				'email'=> trim($post-> usr['email'])
 			), $account['id']);

 			if(!$con_iid)
 				return $this-> send_Fail_JSON("Unable to create the associated contact information for this user.");

 			$account['contact_id'] = $con_iid;
 		} else {
 			// The associated contact exists, so update it
 			$r = $this-> con-> update($account['contact_id'], array(
				'name'=> trim($post-> usr['name']),
				'email'=> trim($post-> usr['email'])
 			));

 			if(!$r)
 				return $this-> send_Fail_JSON("Unable to update the associated contact information for this user.");
 		}
        $post->usr = $this->user->modifyOauth($post->usr);
		// Finally, all checks made, update the user account
		$r = $this-> user-> update($post-> user_id, $post-> usr);

		if(!$r)
			return $this-> send_Fail_JSON("There was an error while attempting to update the user information.");

		// Also update user-2-dep relation if selected department != 0
		if(isset($post-> usr['cdep_id']) && $post-> usr['cdep_id'] > 0) {
			$u2d = $post-> dep;
			$u2d['disabled'] = $u2d['disabled']=='true';
			$u2d['hidden'] = $u2d['hidden']=='true';

			$this-> dep-> update_User_Dep_Relation($post-> usr['cdep_id'], $post-> user_id, $u2d);
		}

		$this-> evan-> add("The user {$post-> usr['name']} has been updated.", "ok", array(
			'permanent'=> true,
			'timeout'=> time()+ 30
		));

		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Adds a new user to the system, in the specified organization id or in the current logged in user's one
	 *
	 * @param MVC_Request $in
	 */
	public function action_Add(MVC_Request $rq)
	{
		// This module is only available for user managers
		if(!$this-> user-> test_ACL('privileges', 'org_user_manager'))
			return $this-> send_Fail_JSON("You are not allowed to manage users.");

		$user_create = array(
			'user'=> trim($rq-> usr['user']),
			'password'=> trim($rq-> usr['password']),
			'name'=> trim($rq-> usr['name']),
			'email'=> trim($rq-> usr['email']),
			// the same organization as the user which created the user if none is chosen in the form (or there is no option to choose)
			'org_id'=> isset($rq-> org_id)? $rq-> org_id: MVC_Core::$ss-> user['org_id']
		);
		$iid = $this-> user-> create($user_create);

		if(!$iid)
            return $this->send_JSON(array(
                'success' => false,
                'reason' => "Unable to create user - Perhaps the same username or e-mail already exists?"
            ));

		$user_update = array();

		// Create the associated contact for this user
		$contact = array(
			'email'=> $user_create['email'],
			'name'=> $user_create['name']
		);
		$con_iid = $this-> con-> create("user_self", $user_create['org_id'], $contact, $iid);
		$user_update['contact_id'] = $contact['id'] = (int)$con_iid;
		$user_update['count_ctx'] = 1;

		// create the default communication containers for the user (Inbox, Sent, Drafts and Bin)
		$this-> comm = $this-> factory_Model("Comm");
		$this-> lab = $this-> factory_Model("Commlabel", "lab");

		$user_update['inbox_id'] = $this-> lab-> create($iid, "Inbox", 'a-inbox');
		$user_update['sentbox_id'] = $this-> lab-> create($iid, "Sent mail", 'b-sent');
		$this-> lab-> create($iid, "Drafts", 'd-drafts');
		$this-> lab-> create($iid, "Trash", 'e-bin');

		// update the user record to include the ID's of the communication containers (they are used in other places)
		$this-> user-> update($iid, $user_update);

		$rq-> add_Filter("welcome_message", "boolean");
		if($rq-> welcome_message) {
			// send the user his first message!
			$welcome_message_id = $this-> comm-> create_System_Message($con_iid, "welcome_message.html", $user_create);
			$this-> comm-> email_Message($welcome_message_id, null, null, true, true);
		}

		// Increase user counter for the user's organization
		$this-> org-> update($user_create['org_id'], array(
			"count_users"=> "`count_users` + 1"
		), false, true); // true = String expressions

		$this-> evan-> add("A new user account for <i>{$rq-> usr['name']}</i> has been created", "add", array(
			'permanent'=> true,
			'timeout'=> time()+ 30
		));

		$this-> send_JSON(array(
			'success'=> true,
			'iid'=> $iid
		));
	}

	/**
	 * Deletes the specified user
	 *
	 * @param MVC_Request $in
	 */

	/**
	 * Removes an user from the system and all his profile information (profile, group connections, etc.)
	 * -- TODO: Is automatically disconnected from the organization he belongs to, and from all departments he's a member of (with proper counter update)
	 * -- TODO: If the user is the organization manager, the organization will not have an assigned manager
	 * -- TODO: All departments which have this user as chief will not have a chief anymore ( an admin must assign a chief manually to each of them )
	 * -- TODO: All issues which have the user as "assigned_to" will become unassigned (set to null)
	 * -- TODO: All issues which have the user as "submitted_by" will become submitted by SYSETM (set to 0), not null, as null means the issue was sent from public area
	 * The user is forciby logged out (removed from active_sessions)
	 *
	 * -- TODO: Generally, all items that have this user ID must be set to "user id 0" (system) in order to avoid errors.
	 * Side-data delete procedures
	 * - TODO: "messages" - The method deletes all mail messages belonging to this user
	 * - TODO: "files" - The method deletes all the files that this user has uploaded
	 * - TODO: "tasks" - The method removes all the tasks that are currently alocated or have been completed by this user
	 *  -- TODO: Also include "Task updates"
	 * - TODO: "notes" - All notes that were created by this user are removed from the system
	 * - TODO: "reports" - Delete reports created by this user or set the created_by to 0
	 */
	public function action_Delete(MVC_Request $post)
	{
		// This module is only available for user managers
		if(!$this-> user-> test_ACL('privileges', 'org_user_manager'))
			return header("HTTP/1.0 403 You are not allowed to manage users.");

		if(!$post-> id)
			return header("HTTP/1.0 400 No user ID was found in the request.");

		$user_id = $post-> id;

		// you cannot remove yourself !
		if($user_id == MVC_Core::$ss-> user['id'])
			return header("HTTP/1.0 403 You cannot remove yourself !");

		// Read the user record to see if he/she actually exists
		// Read the user to discover certain information which is needed for proper deletion
		$user = $this-> user-> read($user_id, array("user"), false);
		if(!$user)
			return header("HTTP/1.0 404 The specified user does not exist in the database !"); // error, user not found

		$r = $this-> user-> delete($post-> id);
		if(!$r)
			return header("HTTP/1.0 500 Error occurred when attempting to delete the user."); // error, user not found

		// Delete information that was created or belongs to the user
		// FIXME: Review the entire model!
/*
		$this-> factory_Model("Comm");
// 		$flags = array("comm");
		if(in_array("comm", $flags)) {
			// Remove user's messages (This doesn't include issue-related messages - Those will remain as being sent by a ghost user), personal Contacts, user's comm Containers and personal Canned messages (templates)
			// It is strongly recommended to leave this option ON, or there will be a lot of orphaned connections

			// Comm 1: Remove messages owned by the user
			$this-> comm-> cleanup_Collection("owner", $user_id, false); // false = Do not bother to update containers and set message count for the user, all is removed
			// Comm 2: Remove contacts belonging to the user (skip it here if all contacts are to be deleted)
			if(!in_array("contacts", $flags))
				$this-> comm-> contacts_Clean(null, $user_id);
			// Comm 3: Remove the communication containers that belong to the user (might be message containers or contacts containers)
			$this-> comm-> containers_Clean($user_id);
			// Comm 4: Remove the personal canned messages
			$this-> comm-> canned_Clean(null, $user_id);
		}
		if(in_array("contacts", $flags)) {
			// Delete the contacts created by this user (even if in system/organization containers)
			$this-> comm-> contacts_Clean(null, $user_id, true);
		} else {
			// Set the creator of all remaining contacts to be Null
			$q=QueryFactory::update()
			-> table(MVC_Core::$db-> tab['ctx'])
			-> where("`user_id` = ?", $user_id)
			-> values(array(
				"user_id"=> null
			));

			MVC_Core::$db-> query($q);
		}
		if(in_array("messages", $flags)) {
			// remove issue messages created by this user
		} else {
			// Set the sender for all "isspost", "issreply", "issexport" messages to be "System" ( set from_id = 0 )
		}

		if(in_array("files", $flags)) {
			// Delete files which have been uploaded by this user
		} else {
			// Set the uploader as being System ( set user_id = 0 ) - So files can be kept in the system
		}

		if(in_array("tasks", $flags)) {
			// Delete tasks which have been assigned to this user
		} else {
			// Unassign all tasks which have been previously assigned to this user ( set assigned_to = 0 )
		}

		if(in_array("notes", $flags)) {
			// Delete all notes on which the user is the last editor
		} else {
			// Set the creator id for notes to be System ( set editor_id = 0 )
		}
*/
		// confirm the deletion and return
		$this-> evan-> add("The user with id {$user_id} has been deleted from the system.", "remove", array(
			'permanent'=> true,
			'timeout'=> time()+ 30
		));

		return $this-> send_JSON(array(
			'success'=> true,
			'redirect'=> "/manager/organization".(isset(MVC_Core::$ss-> user['is_admin'])?"?id={$user['org_id']}":"")."#users"
		));
	}

	/**
	 * Available for certain users only, to login as another user without knowing their password.
	 * (Note that old password is still required to actually change the user's password, this function is used to assume the identify of the user for testing purposes)
	 */
	public function action_Login_As(MVC_Request $in)
	{
		// This module is only available for user managers
		if(!$this-> user-> test_ACL('privileges', 'org_user_manager'))
			return header("HTTP/1.0 403 You are not allowed to manage users.");

		if(!$in-> id)
			return header("HTTP/1.0 400 No user ID was found in the request.");

		if($in-> id < 10) // ID's from 1 to 9 reserved as "admin" accounts.
			return header("HTTP/1.0 403 Unfortunately, it's not possible to login as this administrative user.");

		if($in-> id == MVC_Core::$ss-> user['id']) // cannot login as self
			return header("HTTP/1.0 400 There is no point in attempting to log-in as yourself through this function !");

		// logout as self first
		$this-> user-> do_Logout();

		// and login as the user
		$this-> user-> do_Login($in-> id);
		$this-> evan-> reset();
		$this-> evan-> add(sprintf("You have logged in as %s, be careful what you do :)", MVC_Core::$ss-> user['name']));
                
                //publication read only IWI start
                if(MVC_Core::$ss->user['status']=='readonly'){
                    $redirect_url='/user/publication';
                }else{
                    $redirect_url=MVC_Core::$ss-> user['preferences']['logon_redirect'];
                }
                //publication read only IWI end
                
		$this-> send_JSON(array('success'=> true, 'redirect'=>$redirect_url ));
	}

	/**
	 * Loads the user-2-department relation when changing department, with the purpose of displaying in the user config tab
	 */
	public function action_Load_U2D(MVC_Request $in)
	{
		if($in-> dep_id == 0) {
			// when reading the "no department" - when user selects (none active), do not attempt to read relation. Instead, return success with specification that this isn't an actual relation info
			$this-> send_JSON(array('success'=> true, 'u2d'=> false));
			return;
		}

		$u2d = $this-> dep-> read_User_Dep_Relation($in-> dep_id, $in-> user_id);
		if($u2d) {
			// when relation exists, also read basic department information, to determine if the user is the chief or not and some little additional info
			$dep = $this-> dep-> read($in-> dep_id, array('department'), false);
			$u2d['is_chief'] = $dep['chief_id'] == $in-> user_id;
			$u2d['dep_members'] = (int)$dep['count']['members'];

			// javascript expects the keys for privileges/access_ids to be without the "_or" suffix, so change them
			$u2d['acls'] = array();
			$u2d['acls']['privileges'] = $u2d['privileges_or'];
			$u2d['acls']['access_ids'] = $u2d['access_ids_or'];
			unset($u2d['privileges_or'], $u2d['access_ids_or']);

			$this-> send_JSON(array('success'=> true, 'u2d'=> $u2d));
		} else
			$this-> send_Fail_JSON("Unable to read user to department relation. Information displayed in Active department might be incorrect.");
	}

	/**
	 * Update Access Control Lists overrides for the specified user in the selected department
	 * @param MVC_Request $req
	 */
	public function action_Update_ACLs(MVC_Request $req)
	{
            
//            echo 21212;exit();
		// This module is only available for user managers
		if(!$this-> user-> test_ACL('privileges', 'org_user_manager') && !$this-> user-> test_ACL('privileges', 'org_access_manager'))
			return $this-> send_Fail_JSON("You are not allowed to manage users.");

		if(isset($req-> clear)) {
			// A clear request has been submitted ( remove any overrides )
			$u2d_update = array(
				'privileges_or'=> null,
				'access_ids_or'=> null
			);

			if($this-> dep-> update_User_Dep_Relation($req-> dep_id, $req-> user_id, $u2d_update)) {
				$this-> evan-> add("Access rights have been reverted to department/organization level. They will take effect at next user login.", 'ok');
				$this-> send_JSON(array('success'=> true));
			} else
				$this-> send_Fail_JSON("Error while clearing the user's access control lists overrides.");

			return;
		}

		// TODO: This is repeated almost identically at both organization, department and user level - Make an universal function somewhere in a model to only have one copy
		if(isset($req-> acls)) {
			// An update request has been submitted ( to set overrides )
			$acls = json_decode($req-> acls, true);

			// a bit of post-processing on the submitted data, as JS is sending strings, and we independent numbers
			if(!empty($acls['access_ids']['ist']))
				$acls['access_ids']['ist'] = explode(',', $acls['access_ids']['ist']);
			else
				unset($acls['access_ids']['ist']);
			if(!empty($acls['access_ids']['groups']))
				$acls['access_ids']['groups'] = explode(',', $acls['access_ids']['groups']);
			else
				unset($acls['access_ids']['groups']);

			if(empty($acls['access_ids']))
				$acls['access_ids'] = null;

			// Prepare the update array
			$u2d_update = array();
			$u2d_update['privileges_or'] = $acls['privileges'];
			$u2d_update['access_ids_or'] = !empty($acls['access_ids'])? json_encode($acls['access_ids'], JSON_NUMERIC_CHECK): null;

			if($this-> dep-> update_User_Dep_Relation($req-> dep_id, $req-> user_id, $u2d_update, true)) {
				$this-> evan-> add("User Access Control Lists overrides have been updated. They will take effect at next user login.", 'ok');
				$this-> send_JSON(array('success'=> true));
			} else
				$this-> send_Fail_JSON("Error while updating the user's access control lists overrides.");
		} else {
			$this-> send_Fail_JSON("Interface error: No ACL data to update.");
		}

	}

	/**
	 * Filters for the list of users, with the ability to display only a single organization
	 * @param QueryFactory $q
	 */
	public function filter_Get_List(QueryFactory &$q, $params)
	{
		$usr = MVC_Core::$ss-> user;

		if(isset($params['org_id']))
			$org_id = $params['org_id'];
		elseif($usr['status']!='admin')
			$org_id = $usr['org_id'];
		elseif(isset(MVC_Core::$ss-> perspective))
			$org_id = MVC_Core::$ss-> perspective;

		if(isset($org_id))
			$q-> where('`usr`.org_id = ?', $org_id);
	}

	/**
	 * Get the list users in the system
	 * TODO: Get only the list of users for the user's current department?
	 * -- only for admin(s) get all the users in the system (warning, possibly big list!) - Find a way to search/filter users
	 */
	public function action_Get_List(MVC_Request $in)
	{
		if($in-> quick_mode)
			// just id and name ( for various lists throughout the application )
			$users = $this-> user-> get_List(array(), array($this, "filter_Get_List"), array('org_id'=> $in-> org_id), "`name` ASC");
		else
			// complete user data
			$users = $this-> user-> get_List(array('user','contact','organization','department'), array($this, "filter_Get_List"), "`name` ASC");

		if(count($users)>0) {
			// since jqxDataAdapter doesn't support complex types, compress extra information as json (organization, departement, user2dep, acls)
			if(!$in-> quick_mode)
				foreach($users as &$user) {
				$user['count'] = json_encode($user['count']);
				if(isset($user['acls']))
					$user['acls'] = json_encode($user['acls']);
			}

			$this-> send_JSON($users);
		} else
			$this-> send_JSON(array()); // empty list
	}

	/**
	 * Read all profile records for a certain user (all columns except the "data"). Used by the profile manager
	 *
	 * @param MVC_Request $in
	 */
	public function action_Get_Profile_Records(MVC_Request $in)
	{
		usleep(250000); // Introduce a small delay in returning the results, there's some kind of race condition going on in the frontend making the grid fail sometimes

		if(!$in-> user_id) {
			MVC_Core::$debug-> dump("User ID not present in the request", __METHOD__, null, 'error');
			return $this-> send_JSON(array()); // User ID not present, cannot read anything
		}

		$profiles = $this-> user-> profile_Get_List($in-> user_id);
		if(!$profiles) {
			MVC_Core::$debug-> dump("Unable to read user {$in-> user_id} records", __METHOD__, null, 'error');
			return $this-> send_JSON(array());
		}

		// Read quick information about issue types so profile keys can be "decoded".
		$m_iss = new App_Model_Issue();
		$isstypes = $m_iss-> ist_Get_List(null, false, 'converter');
		$ist_converter = array();
		foreach($isstypes as $ist)
			$ist_converter[(int)$ist['id']] = $ist;

		$all = array();
		foreach($profiles as $rec) {
			if($rec['utime'] == '0000-00-00 00:00:00')
				$rec['utime'] = null;

			// Decode "profile key" to two other columns: "ptype", "pin", "pik"
			$ist_id = null;
			if(substr($rec['pkey'], 0, 12) == 'columns_ist_') {
				$rec['ptype'] = "Grid columns";
				$ist_id = (int)substr($rec['pkey'], 12);
			} elseif(substr($rec['pkey'], 0, 13) == 'settings_ist_') {
				$rec['ptype'] = "Grid settings";
				$ist_id = (int)substr($rec['pkey'], 13);
			} elseif(substr($rec['pkey'], 0, 11) == 'grid_state_') {
				$rec['ptype'] = "Grid state";
				$ist_id = (int)substr($rec['pkey'], 11);
			} elseif(substr($rec['pkey'], 0, 13) == 'overview_ist_') {
				$rec['ptype'] = "Panels";
				$ist_id = (int)substr($rec['pkey'], 13);
			} elseif($rec['pkey'] == "iss_recent") {
				$rec['ptype'] = "Recent records";
				$rec['pin'] = "(Doesn't apply)";
			} elseif($rec['pkey'] == "preferences") {
				$rec['ptype'] = "Preferences";
				$rec['pin'] = "(Doesn't apply)";
			} elseif($rec['pkey'] == "mini_notepad") {
				$rec['ptype'] = "Mini Notepad";
				$rec['pin'] = "(Doesn't apply)";
			} else {
				$rec['ptype'] = "Invalid record";
				$rec['pin'] = "( ? )";
			}

			if(!is_null($ist_id))
				if(isset($ist_converter[$ist_id])) {
					$rec['pin'] = $ist_converter[$ist_id]['name'];
					$rec['pik'] = $ist_converter[$ist_id]['key'];
				} else {
					$rec['pin'] = "(Deleted type)";
					unset($rec['pik']);
				}

			$all[] = $rec;
		}

		$this-> send_JSON($all);
	}

	/**
	 * Loads the profile-stored JSON data and formats it for output
	 * @param MVC_Request $in
	 * @return string
	 */
	public function action_Load_Profile_Data(MVC_Request $in)
	{
		if(!$in-> user_id) {
			echo "Error reading profile data";
			return MVC_Core::$debug-> dump("User ID not present in the request", __METHOD__, null, 'error');
		}

		$profile = $this-> user-> profile_Read($in-> user_id, $in-> pkey, true, $in-> variant);
		if(!$profile) {
			echo "Error reading profile data";
			return MVC_Core::$debug-> dump("Profile data could not be read", __METHOD__, null, 'error');
		}

		echo MVC_Core::pretty_JSON($profile); // JSON not decoded when reading, so $profile used as a string.
	}

	/**
	 * Updates the profile name/description for one profile record+variant
	 *
	 * @param MVC_Request $post
	 * @return boolean
	 */
	public function action_Update_Profile_Pname(MVC_Request $post)
	{
		if(!$this-> user-> is_Unrestricted())
			return $this-> send_Fail_JSON("No access to this function. How did you even get here?");

		if(!$post-> user_id)
			return $this-> send_Fail_JSON("User ID not present in the request");

		$r = $this-> user-> profile_Update_Pname($post-> user_id, $post-> pkey, $post-> pname, strlen($post-> variant)>0? $post-> variant: null);

		if($r)
			$this-> send_JSON(array('success'=> true, 'response'=> "Profile name/description updated successfully !"));
		else
			$this-> send_Fail_JSON("Error while attempting to update this profile record.");
	}

	/**
	 * Delete a profile entry (all variants)
	 *
	 * @param MVC_Request $post
	 * @return boolean
	 */
	public function action_Reset_Profile(MVC_Request $post)
	{
		if(!$this-> user-> is_Unrestricted())
			return $this-> send_Fail_JSON("No access to this function. How did you even get here?");

		if(!$post-> user_id)
			return $this-> send_Fail_JSON("User ID not present in the request");

		$this-> user-> profile_Write($post-> user_id, $post-> pkey, null); // Writing with empty profile clears it instead.

		$this-> send_JSON(array('success'=> true, 'response'=> "Profile record {$post-> pkey} was reset !"));
	}

	/**
	 * Copies the currently selected profile as the organization default (which is then automatically copied to every new user or when a user's profile doesn't exist)
	 * @param MVC_Request $post
	 * @return boolean
	 */
	public function action_Set_Default_Profile(MVC_Request $post)
	{
		if(!$this-> user-> is_Unrestricted())
			return $this-> send_Fail_JSON("No access to this function. How did you even get here?");

		if(!$post-> user_id)
			return $this-> send_Fail_JSON("User ID not present in the request");

		// Read the organization to which the selected user belongs
		$profile_data = $this-> user-> profile_Read($post-> user_id, $post-> pkey, true, $post-> variant?: 1);
		if(!empty($profile_data))
			$this-> org-> profile_Default_Write($post-> org_id, $post-> pkey, $profile_data, true, "Org. Default", $post-> user_id); // TODO: This name should be set from the interface somehow ( window ? )

		$this-> send_JSON(array('success'=> true, 'response'=> "The profile entry {$post-> pkey} has been set as the organization default"));
	}
}