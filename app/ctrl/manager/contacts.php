<?php
/**
 * Contacts Manager (Address Book), for User, Department and Organization
 * - A dedicated contacts module (May be integrated as part of Comm/Center when finished, or kept as independent)
 * - Uses components: contacts_list, contact_editor
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2.10
 */
class App_Ctrl_Manager_Contacts extends App_Ctrl_Base
{
	public static $_module_name = "Contacts Manager";

	/**
	 * Contacts model, also used all around this module
	 * @var App_Model_Contact
	 */
	private $con;

	/**
	 * Labels model, part of the communication system
	 * @var App_Model_Commlabel
	 */
	private $lab;

	/**
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct(true);

		// Data Models
		$this-> con = $this-> factory_Model("Contact", "con");
		$this-> lab = $this-> factory_Model("Commlabel", "lab");
	}

	/**
	 * Module loader
	 */
	public function action_Index(MVC_Request $rq)
	{
		if($rq-> org_id) {
			// Editing contacts for the organization ( Department and Organization level; Requires certain access rights )
			if(!$this-> user-> test_ACL('privileges', 'org_contacts_manager')) {
				$this-> evan-> add("You are not allowed to manage organization contacts.", 'lock');
				return $this-> redirect("/manager/contacts#search");
			}

			$mode = "org";
			$org_id = (int)$rq-> org_id;
			$org = $this-> org-> read($org_id, array(), false);
			$show_title = true;
		} elseif($rq-> dep_id) {
			// Viewing and editing contacts for the department level ( Only selected department contacts are loaded )
			// TODO: Implement department level only ( for managing deparment contacts from the department editor )

			$mode = "dep";

		} elseif($rq-> user_id) {
			// Editing contacts at user level, for another user (Admin, User manager)
			if(!$this-> user-> test_ACL('privileges', 'org_user_manager') || !$this-> user-> test_ACL('privileges', 'org_contacts_manager')) {
				$this-> evan-> add("You are not allowed to manage contacts for another user.", 'lock');
				return $this-> redirect("/manager/contacts#search");
			}

			$mode = "user";
			$user_id = (int)$rq-> user_id;
			$user = $this-> user-> read($user_id, array('user', 'organization'), false);
			$show_title = true;
		} else {
			// By default, the contacts manager is operating at user level, for the logged in user
			$mode = "user";
			$user = MVC_Core::$ss-> user;
			$user_id = $user['id'];
			$self = true;
		}

		$this-> CFG['components'] = array("modal", "hashnav", "contacts_list", "contact_editor");
		array_push($this-> CFG['modules'], "contact/_manager");
		$this-> CFG['jqwidgets'][] = array("tabs", "splitter", "panel", "tree"); // , "combobox", "buttongroup", "listbox", "dropdownlist"
		// The contacts datatable
		$this-> CFG['jqwidgets'][] = array("listbox", "datatable", "dropdownlist");

		$this-> CFG['contact_manager'] = array(
			'module_name'=> self::$_module_name,
			'mode'=> $mode
		);
		// Mode specific configuration
		switch($mode) {
			case 'user':
				$this-> CFG['contact_manager']['user_id'] = $user_id; // For which user are we loading contacts?
				$this-> CFG['contact_manager']['org_id'] = $user['org_id']; // The organization and department level contacts are for the user's organization
				break;
			case 'org':
				$this-> CFG['contact_manager']['org_id'] = $org_id; // Organization level only; Not related to any particular user
				break;
			case 'dep':
				break;
		}


		$this-> CFG['xjs'] = array(
			'jqform'=> true,
			'dateformat' => true
		);

		// Fields structure for the contacts editor
		MVC_NLS::load_Groups("contact");
		$fields_structure = MVC_NLS::get_Object("contact", true);

		// Process the fields stucture a bit before sending it to JS
		foreach($fields_structure as &$group) {
			foreach($group['fields'] as $field=> $string) {
				if(strpos($string, "~"))
					$group['fields'][$field] = explode("~", $string);
			}
		}

		$this-> CFG['contact_manager']['vcard_fields_structure'] = $fields_structure;

		// prepare output
		$this-> prepare_Header(self::$_module_name);

		$all_available_tabs = array(
			// The list of contacts
			array(
				'id'=> "tab_search",
				'title'=> "Search",
				'icon'=> 'users.png'
			),
			// Contact editing tab
			array(
				'id'=> "tab_vcard",
				'title'=> "No contact",
				'icon'=> "vcard.png"
			)
		);

		$this-> module = array(
			'toolbar'=> array(
				array(
					'type'=> 'opbar',
					'hidden'=> true,
					'id'=> "ops-search",
					'include'=> "includes/menubars/contact_manager-tab_search.html"
				),
				array(
					'type'=> 'opbar',
					'hidden'=> true,
					'id'=> "ops-vcard",
					'include'=> "includes/menubars/contact_manager-tab_vcard.html"
				)
			),
			'icon'=> "book_addresses.png",
			'icon_alt'=> "(abook)",
			'tabs'=> $all_available_tabs,
			'title'=> self::$_module_name,
			'description'=> "Address book and E-mail contacts"
		);

		if(isset($show_title)) {
			// Actual title/info depends on operation mode
			switch($mode) {
				case 'user':
					$this->_out['module']['title'] = $user['name'];
					$this->_out['module']['title_para'] = $user['organization']['name'];
					$this->_out['module']['description'] = "Managing contacts for another account";
					break;
				case 'org':
					$this->_out['module']['title'] = $org['name'];
					$this->_out['module']['description'] = "Managing organization contacts";
					break;
			}
		}

		// Loading a specific contact directly ?
		if($rq-> init_id) {
			// TODO: Check if the user can actually see this contact ?
			$this-> CFG['contact_manager']['init_contact_id'] = $rq-> init_id;
		}

		$this-> site_section = "general_tabs_container.html";
		$this-> view("private.html");
	}

	/**
	 * Loads contact information for both viewing and editing
	 *
	 * @param MVC_Request $in
	 */
	private function load_Vcard(MVC_Request $in)
	{
		$contact = array();
		if(!$in-> contact_id && isset($in-> create)) {
			// In create mode, the returned html contains some specific helpers for the user to start typing in contact informaiton
			$contact['_create'] = array();
			if(isset($in-> init_contact['name']))
				$contact['name'] = $in-> init_contact['name'];
			else {
				$contact['name'] = "(Type the name here)";
				$contact['_create']['name'] = true;
			}
			if(isset($in-> init_contact['email']))
				$contact['email'] = $in-> init_contact['email'];
			else {
				$contact['email'] = "(Enter the e-mail address)";
				$contact['_create']['email'] = true;
			}
		} else {
			$contact = $this-> con-> read($in-> contact_id);
			if(!$contact)
				return;

			// Prepare the contact information
			$contact['xdata'] = $this-> con-> decode_Xdata($contact['xdata']);
		}

		if(isset($in-> edit) || isset($in-> create))
			$contact['_edit'] = true;

		return $contact;
	}

	/**
	 * Load the inside of a tab for a tab-navigation module
	 *
	 * @param MVC_Request $in
	 */
	public function action_Load_Tab_HTML(MVC_Request $in)
	{
		switch($in-> tab) {
			// Nothing here, the entire search tab is handled by the "contacts_list" component
			case 'search':
				echo "";
				return;
			// Contact editor tab
			case 'vcard':

				if($in-> org_id && $in-> org_id != MVC_Core::$ss-> user['org_id']) {
					// Create or edit contact for an organization?
					$org = $this-> org-> read($in-> org_id, array());

				} elseif($in-> user_id && $in-> user_id != MVC_Core::$ss-> user['id']) {
					// Create or edit contact for an user
					$user = $this-> user-> read($in-> user_id, array('user'), false);
				} else
					// In default mode, the user will create or edit contacts for himself
					$user = MVC_Core::$ss-> user;

				$this-> contact = $contact = $this-> load_Vcard($in);

				// Hybrid response (HTML+JSON) - Returns Contact ID (when found), as well as "name" field (to be used in the tab title)
				$data_response = array();

				if($in-> contact_id) {
					// Correct contact?
					if($contact && isset($contact['id'])) {
						$data_response['contact'] = array(
							'id'=> (int)$contact['id'],
							'name'=> $contact['name'],
							'email'=> $contact['email']
						);

						// Determine if this contact is being used as issue target, if so, assemble information for a "right-side-block"
						$targets = $this-> org-> targetContact_UsedFor($this->_out['contact']['org_id'], $contact['id']);

						if($targets) {
							$target_info = array();
							$target_info_ist = array();
							foreach($targets as $row) {
								$istkey = $row['istkey'];
								if(!isset($target_info_ist[$istkey])) {
									$target_info_ist[$istkey] = array(
										'istname'=> $row['istname'],
										'istkey'=> $row['istkey'],
										'departments'=> array()
									);
									$target_info[] = &$target_info_ist[$istkey];
								}
								// Add department if it exists, if empty just add the text "(Entire organization)"
								if(!empty($row['dep_id']))
									$target_info_ist[$istkey]['departments'][] = $row['dep_name'];
							}
							$this-> target_info = $target_info;
						}

						// Check if user_self contact is loaded;
						if($contact['type'] == 'user_self') {

							if($contact['id'] == $user['contact_id']) {
								$this-> special_info = array(
									'title'=> "This is your primary address",
									'description'=> "Modifying this contact will change your user details as well (Name, E-mail address), as they are linked.\nWhen sending messages, your recipients will receive the e-mails coming from this e-mail address and name.\n\n<b>This contact cannot be deleted!</b>"
								);
								$data_response['nodelete'] = true;

								// Add support for SMTP server settings for that contact (for sending from the actual address, and not through sendmail)
								$this-> smtp_settings = true;
							} else {
								$this-> special_info = array(
									'title'=> "This contact is an alias of the user",
									'description'=> "Alternative e-mail address / name that can be used in the communication system.
										Aliases can only be created and changed by a supervisor or by Cargohub Support."
								);
							}
						}
					} else
						$data_response['contact'] = false;

				} else {
					// Create mode ? Prepare a short information on the right side about the label that was used
					$create_info = array();
					if(substr($in-> label, 0, 5) == 'user_') {
						if($in-> label == 'user_ALL') {
							$create_info['title'] = "Creating a personal contact";
							$create_info['description'] = "This contact will only be visible from your own account, and nobody else will have access to it.";
							$data_response['contact'] = array('name'=> "Create personal contact");
						} elseif($in-> label == 'user_SELF') {
							$create_info['title'] = "Creating an alias for this user";
							$create_info['description'] = "These addresses are aliases for this user.<br />Allows submitting e-mails usind different addreses.<br /><br />The contact name is initialized with the current user name.";
							$data_response['contact'] = array('name'=> "Create alias");
							// Preset user name ( but can be changed )
							$this->_out['contact']['name'] = $user['name'];
							unset($this->_out['contact']['_create']['name']);
						} elseif($in-> label == 'user_AUTOOUT') {
							$create_info['title'] = "Nope!";
						} else {
							// TODO: Read the label name...
							$create_info['title'] = "Creating a personal contact";
							$create_info['description'] = "This personal contact will be attached to the selected label directly, but the label can be changed later if necessary";
							$data_response['contact'] = array('name'=> "Create contact in label");
						}

					} elseif(substr($in-> label, 0, 4) == 'dep_') {
						$selector = substr($in-> label, 4);

						if($selector == 'ALL') {
							$create_info['title'] = "Creating an organization wide contact";
							$create_info['description'] = "This contact will be visible for everyone in the organization.";
							$data_response['contact'] = array('name'=> "Create organization contact");
						} else {
							// TODO: Read the department name
							$create_info['title'] = "Creating a department level contact";
							$create_info['description'] = "This contact is accessible by anyone within the department.\nUse this type of contact either to divide organization contacts, or for setting up communication addresses for your business partners.";
							$data_response['contact'] = array('name'=> "Create department contact");
						}

					} elseif(substr($in-> label, 0, 4) == 'org_') {
						$selector = substr($in-> label, 4);
						switch($selector) {
							case 'ALL':
								$create_info['title'] = "Creating an organization wide contact";
								$create_info['description'] = "This contact will be visible for everyone in the organization.";
								$data_response['contact'] = array('name'=> "Create organization contact");
								break;
							case 'SELF':
								$create_info['title'] = "Creating a contact representing the organization";
								$create_info['description'] = "These addresses are the organization's own addresses. This information can be shown in the global organization search. The contact name starts with the current organization name (Primary contact).";
								$data_response['contact'] = array('name'=> "Create organization information");
								$this->_out['contact']['name'] = $org['name'];
								unset($this->_out['contact']['_create']['name']);
								break;
							case 'OLDTARGET':
								$create_info['title'] = "[OUTDATED] Do not create more contacts here!";
								$create_info['description'] = "Please try to avoid this method, as these contacts will not be part of the next updates.";
								$data_response['contact'] = array('name'=> "Create claim target contact");
								break;
							default:
								$create_info['title'] = "Nope!";
						}
					} elseif($in-> label == "global") {
						$create_info['title'] = "Creating a global (yellow pages) contact";
						$create_info['description'] = "This contact will be visible for anyone accessing the system.\nOnly Admin can create this type of contact; The Create button should not be visible for anyone else when this label is selected";
						$data_response['contact'] = array('name'=> "Create yellow-pages entry");
					} elseif($in-> label == "system") {
						$create_info['title'] = "Creating a special System-Wide contact";
						$create_info['description'] = "Not really needed, this is very rare...";
						$data_response['contact'] = array('name'=> "Awesome!");
					}

					$this-> create_info = $create_info;
				}

				// Hybrid response (HTML+JSON) - Returns ID (when existing), as well as name
 				$this-> send_JSON_Headers($data_response);
				break;
		}

		// This is an ajax request, but needs the template. Initialize the template object on demand
		$this-> init_Template_System();
		echo $this->_drytpl-> render_File("contact". DS. "manager_tabs". DS. "tab_". $in-> tab. ".html", $this->_out);
	}

	/**
	 * Read the issue types for an organization that are enabled and can receive data of this type.
	 *
	 * @param MVC_Request $in
	 */
	public function action_Get_Org_Issue_Types(MVC_Request $in)
	{
		$r = $this-> org-> ist_Get_List($in-> org_id, true, true);
		$all = array();
		if($r && count($r)>0) {
			foreach($r as $ist_link)
				$all[] = array(
					'id'=> (int)$ist_link['id'],
					'key'=> $ist_link['key'],
					'name'=> $ist_link['name']
				);
		}

		$this-> send_JSON($all);
	}

	/**
	 * Read the departments from the organization which are listed as being able to receive issues
	 *
	 * @param MVC_Request $in
	 */
	public function action_Get_Org_Departments(MVC_Request $in)
	{
		$m_dep = new App_Model_Department();
		$deps = $m_dep-> get_List($in-> org_id, array(), function(QueryFactory &$q) use($in) {
			// Filter those which have "listed" to 1
			$q-> where("`dep`.listed = 1");
		});

		if(!$deps)
			return $this-> send_JSON(array()); // Empty result

		// Post-Process deps
		foreach($deps as &$dep)
			unset($dep['count_members'], $dep['count_ctx']); // Not needed here

		// Insert the "Any department" option
		array_unshift($deps, array(
			'id'=> 0,
			'name'=> "(Any department)"
		));

		$this-> send_JSON($deps);
	}

	/**
	 * Sets the selected contact as issue target for the organization and chosen issue type and department
	 *
	 * @param MVC_Request $post
	 */
	public function action_SetAs_Issue_Target(MVC_Request $post)
	{
		$tlinks = $this-> org-> targetContact_Get_IDs($post-> org_id, $post-> istkey, $post-> dep_id);
		// Check if contact_id already exists in the list
		foreach($tlinks as $contact_id)
			if($post-> contact_id == $contact_id)
				return $this-> send_Fail_JSON("This contact has already been set as target for the same record type (and department)");

		// Ready to link
		$this-> org-> targetContact_Set($post-> org_id, $post-> istkey, $post-> contact_id, $post-> dep_id);
		$this-> send_JSON(array('success'=> true, 'response'=> "The contact has been set as a communication target."));
	}

	/**
	 * Detaches a contact from the list of issue communication targets for a certain issue type
	 *
	 * @param MVC_Request $post
	 */
	public function action_Unlink_Issue_Target(MVC_Request $post)
	{
		$this-> org-> targetContact_Unlink($post-> org_id, $post-> contact_id, $post-> istkey);
		$this-> send_JSON(array('success'=> true, 'response'=> "The contact is no longer a target for that issue type."));
	}

	/**
	 * Creates or saves a contact label
	 * @param MVC_Request $post
	 */
	public function action_Save_Label(MVC_Request $post)
	{
		if($post-> mode == 'update') {
			// Update existing label - the "label_id" contains both type and ID
			list($raw_type, $id) = explode('_', $post-> label_id);

			// TODO: While this is not a very destructive operation, there should be a check if the logged in user actually has the right to rename labels.
			$r = $this-> lab-> rename($id, trim($post-> lbl['name']), $post-> lbl['color']);
			if(!$r)
				return header("HTTP/1.0 500 Error while updating the label");

			return $this-> send_JSON(array("response"=> "Label has been updated"));
		} else {
			// Create new label - the "container_id" indicates where the label should be created, and what type
			switch($post-> type) {
				// Personal labels are for the logged in user or for the selected user
				case 'personal':
					$user_id = !empty($post-> user_id)? (int)$post-> user_id: MVC_Core::$ss-> user['id'];
					$iid = $this-> lab-> create($user_id, trim($post-> lbl['name']), 'con-usr', $post-> lbl['color']);
					break;

				// Independent labels can be created for each department - The container_id contains the department ID
				case 'department':
					list($_unused, $dep_id) = explode('_', $post-> container_id);
					$iid = $this-> lab-> create($dep_id, trim($post-> lbl['name']), 'con-dep', $post-> lbl['color']);
					break;

				// Organization labels can be created for the user's organizaiton, or in case of admin editing contacts for another organization - for that
				case 'organization':
					$org_id = !empty($post-> org_id)? (int)$post-> org_id: MVC_Core::$ss-> user['org_id'];
					$iid = $this-> lab-> create($org_id, trim($post-> lbl['name']), 'con-org', $post-> lbl['color']);
					break;
			}
			if(!empty($iid)) {
				$this-> evan-> add(sprintf("Label <b>%s</b> has been created", $post-> lbl['name']), 'add', array(
					'permanent'=> true,
					'timeout'=> time()+ 30
				));

				return $this-> send_JSON(array(
					"success"=> true,
					"id"=> $iid
				));
			}
			return header("HTTP/1.0 500 Error during label creation");
		}
	}

	/**
	 * Delete an existing contact label
	 * @param MVC_Request $post
	 */
	public function action_Delete_Label(MVC_Request $post)
	{
		list($raw_type, $id) = explode('_', $post-> label_id);
		switch($raw_type) {
			// User label - They can always be deleted
			case 'user':
				$this-> con-> cleanup_Label($id);
				$r = $this-> lab-> delete($id);
				if(!$r)
					return header("HTTP/1.0 500 Error encounterd on label deletion");

				$this-> evan-> add("Label ID {$id} has been deleted. Any contacts in it were are now label-less", 'remove');
				break;
			// Department or organization label - Only if the user has the right to manage organization contacts
			case 'lab':
				if(!$this-> user-> test_ACL('privileges', 'org_contacts_manager'))
					return header("HTTP/1.0 403 Access denied");

				$this-> con-> cleanup_Label($id);
				$r = $this-> lab-> delete($id);

				$this-> evan-> add("Label ID {$id} has been deleted. Any contacts in it were are now label-less", 'remove');
				if(!$r)
					return header("HTTP/1.0 500 Error encounterd on label deletion");
				break;
			default:
				// Unrecognized type, something went wrong
				return header("HTTP/1.0 500 This function cannot be used here");
		}
		return $this-> send_JSON(array(
			"success"=> true
		));
	}

	/**
	 * Reads a list of labels available for a certain selector ( user and organization labels depend on parameters, while department on a secondary selector_id )
	 * @param MVC_Request $rq
	 */
	public function action_Get_Labels(MVC_Request $rq)
	{
		switch($rq-> selector) {
			case 'usr':
				$selector_id = !empty($rq-> user_id)? (int)$rq-> user_id: MVC_Core::$ss-> user['id'];
				break;
			case 'dep':
				$selector_id = (int)$rq-> selector_id;
				break;
			case 'org':
				$selector_id = !empty($rq-> org_id)? (int)$rq-> org_id: MVC_Core::$ss-> user['org_id'];
				break;
			default:
				return header("HTTP/1.0 400 Selector has an invalid value");
		}

		$labels = $this-> lab-> get_List($selector_id, 'con-'.$rq-> selector);
		if(empty($labels))
			return header("HTTP/1.0 404 No labels found");

		return $this-> send_JSON($labels);
	}
}