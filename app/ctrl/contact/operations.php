<?php
/**
 * Operations related to contacts ( listing, searching, reading/writing )
 * - Used by the contact-related components, but also the modules using contacts natively
 *
 * @deprecated This module is being replaced by /global/contact - Check if anything else still uses /contact/operations
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoITS
 * @category Controller
 * @version 2020.11.01
 */
class App_Ctrl_Contact_Operations extends App_Ctrl_Base
{
	/**
	 * Contacts model, also used all around this module
	 * @var App_Model_Contact
	 */
	private $con;

	/**
	 * Labels model, used to locate contacts in user-level labels
	 * @var App_Model_Commlabel
	 */
	private $lab;

	/**
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct();

		// Contact
		$this-> con = $this-> factory_Model("Contact", "con");
		$this-> lab = $this-> factory_Model("Commlabel", "lab");
	}

	/**
	 * Creates or saves a contact.
	 * -- Creating or saving a contact also adds it to the list of recently used ($this-> action_Add_To_Recent) - CHECK.
	 */
	public function action_Save(MVC_Request $post)
	{
		$contact_data = array(
			'name'=> $post-> name,
			'email'=> $post-> email
		);
		if($post-> xdata != "{}")
			$contact_data['xdata'] = $post-> xdata;
		else
			$contact_data['xdata'] = null;

		if(!empty($post-> id)) {
			// Edit mode
			// TODO: Check if this contact can actually be edited by the logged in user or contact account (drafts can be edited by anyone, even not logged in)

			// Only admin can change the organization of a contact; Note - The interface to modify org and dep doesn't exist yet
			if($this-> user-> is_Unrestricted()) {
				if($post-> org_id)
					$contact_data['org_id'] = $post-> org_id;
				if($post-> dep_id)
					$contact_data['dep_id'] = $post-> dep_id;
			}

			$r = $this-> con-> update($post-> id, $contact_data, true);

			if(!$r)
				return $this-> send_Fail_JSON("Contact could not be updated, possible database error.");

			/*
			 * TODO: Test if this contact is the user's own contact ?
			if($post-> type == 'user_self') {
				$user_id = isset($post-> user_id)? $post-> user_id: MVC_Core::$ss-> user['id'];
				// This is the contact associated to the user
				$this-> user-> update($user_id, array(
						'name'=> $contact_data['name'],
						'email'=> $contact_data['email']
				));
			}
			*/

			$this-> send_JSON(array(
				'success'=> true,
				'id'=> (int)$post-> id,
				'response'=> "Contact has been updated"
			));
			$this-> action_Add_To_Recent(false);
		} else {
			// Create mode

			// Creating a contact connected to a "label" ( can create personal, department or organization contacts; New contacts module )
			// "Type" is determined based on the label
			if($post-> label) {
				if(substr($post-> label, 0, 5) == 'user_') {
					$selector = substr($post-> label, 5);

					if($selector == 'SELF')
						$type = "user_self";
					else
						$type = "personal";

					if(is_numeric($selector)) {
						// TODO Creating a contact connected to a label; later, after labels manager is built
					} else {
						// Just a standard personal contact, no label
					}

				} elseif(substr($post-> label, 0, 4) == 'dep_') {
					$selector = substr($post-> label, 4);

					if(is_numeric($selector)) {
						$type = "department";

					} else {
						$type = "organization";
					}

				} elseif(substr($post-> label, 0, 4) == 'org_') {
					$selector = substr($post-> label, 4);

					if($selector == 'SELF')
						$type = "org_self";
					elseif($selector == 'OLDTARGET')
						$type = "issue_target";
					else
						$type = "organization";

				} elseif($post-> label == "global") {
					$type = "global";
				} else
					return $this-> send_Fail_JSON("Unable to detect where to save the contact; Cannot create.");
			} else {
				$type = $post-> type;

				//== E-mail address descovery for "draft" contacts (created from the frontend submit) ==//
				// Try to find if the contact doesn't already exist (same e-mail). If also matching name, the contact record is reused and it's ID returned (as in, not creating another).
				// If only the e-mail matches, another contact is created, but it is added to the list of managed contacts for a contact account with that e-mail.
				// TODO: Review this part, it's not very "friendly"
				// E-mails matching system users ( contact found with type "user_self" ) are always returned. There can only be ONE conect of type "user_self" for one e-mail address. The name is also returned/corrected when detecting such a contact.

				if($type == 'draft') {

					// First, find if there is an user account contact for the specified e-mail address
					$con_find = $this-> con-> locate("user_self", $contact_data['email'], null, null, null, null, false, true);
					if(is_array($con_find)) {
						// Detected user contact, return information about it
						$this-> send_JSON(array(
								'success'=> true,
								'id'=> (int)$con_find['id'],
								'response'=> sprintf("Detected and using existing staff user contact with name: <b>%s</b>", $con_find['name']),
								'name'=> $con_find['name']
						));
						return $this-> action_Add_To_Recent(false, $con_find['id']);
					}

					// Second, attempt to locate the contact in existing issue submit contacts (isspost), and reuse if detected
					$con_find = $this-> con-> locate("isspost", $contact_data['email']);
					if(is_array($con_find)) {
						foreach($con_find as $contact) {
							// If matching one of the names, use the exact id. If not, let the contact create, but also find the contact account for the specified mail (if it exists) and add the newly created contact to it.
							if($contact['name'] == trim($contact_data['name'])) {
								$this-> send_JSON(array(
										'success'=> true,
										'id'=> (int)$contact['id'],
										'response'=> sprintf("Detected and reusing previously submitted contact with name: <b>%s</b>", $contact['name'])
								));
								return $this-> action_Add_To_Recent(false, $contact['id']);
							}
						}
						// No exact match found, then let it be added
					}
				}
			}

			// Depending on the context, $org_id, $user_id and $dep_id are set
			$org_id = null; $user_id = null; $dep_id = null;
			// Logged in ? Check if creating for self or for another
			if(isset(MVC_Core::$ss-> user)) {
				// Only admin can create or edit contacts for other organizations or users (for now); In the (near) future it will be an user function as well.
				if($this-> user-> is_Unrestricted()) {
					if($post-> org_id)
						$org_id = $post-> org_id;
					else
						$org_id = MVC_Core::$ss-> user['org_id'];
					if($post-> user_id)
						$user_id = $post-> user_id;
					else
						$user_id = MVC_Core::$ss-> user['id'];
				} else {
					// Non-admin always creates for self (or own organization)
					$org_id = MVC_Core::$ss-> user['org_id'];
					$user_id = MVC_Core::$ss-> user['id'];
				}
				// Department level contacts can be created for both own department or another
				if($post-> dep_id)
					$dep_id = $post-> dep_id;
				elseif($type == 'department') {
					if(is_numeric($selector))
						$dep_id = (int)$selector;
					else
						$dep_id = MVC_Core::$ss-> user['cdep_id'];
				}
			} else {
				// Not logged in - Create for the organization which will receive this contact, or "null" if unknown
				if($post-> org_id)
					$org_id = $post-> org_id;
			}

			switch($type) {
				// Draft contact. This isn't normally linked to anything. Drafts are converted to other types at a later stage, or automatically deleted by the system if they are too old.
				case 'draft':
					break;
				// User's own address(es) or personal contacts
				case 'personal':
				case 'user_self':
					break;
				// Department contact
				case 'department':
				case 'dep_self':
					break;
				// Organization contact
				case 'organization':
				case 'org_self':
 				case 'issue_target': // @deprecated, a different system is used now
					break;
				case 'global':
					$org_id = null; // Global contacts do not belong to an organization
					break;
				// Contact types which cannot be created by actions from the user interface. These are system-created contacts (they can be converted to other types later)
				case 'system':
				case 'auto_out':
				case 'auto_in':
				case 'unknown':
				default:
					return $this-> send_Fail_JSON("Not possible to manually create contact of type {$post-> type}.");
			}

			$iid = $this-> con-> create($type, $org_id, $contact_data, $user_id, $dep_id, true);

			if(!$iid)
				return $this-> send_Fail_JSON("Contact could not be created, possible database error.");

			// If the submitter is sent, check if there is a contact account under this e-mail address, and add the $iid to the list of managed contacts.
			if($post-> role && $post-> role == "submitter") {
				$m_cac = new App_Model_ContactAccount();
				// When contact account is logged in, use it - If not, find it
				if(MVC_Core::$ss-> contact_account)
					$cac = MVC_Core::$ss-> contact_account;
				else
					$cac = $m_cac-> read($contact_data['email']);
				if($cac) {
					$cac['managed_ctx'] = explode(',', $cac['managed_ctx']);
					if(!in_array($iid, $cac['managed_ctx'])) {
						$cac['managed_ctx'][] = (int)$iid;
						$cac['managed_ctx'] = implode(',', $cac['managed_ctx']);
						// If Contact Acccount logged in, update the in-session managed contact list to contain the newly created contact
						if(MVC_Core::$ss-> contact_account)
							MVC_Core::$ss-> contact_account['managed_ctx'] = $cac['managed_ctx'];
						// And update the database
						$m_cac-> update($cac['id'], $cac['managed_ctx']);
					}
				}
			}

			$this-> send_JSON(array(
				'success'=> true,
				'id'=> $iid,
				'response'=> sprintf("A contact record has been created for: %s", $contact_data['email'])
			));
			$this-> action_Add_To_Recent(false, $iid);
		}
	}

	/**
	 * Constructs the Conacts List embedded-html based on input parameters (used in other modules that need to find a contact from the address book)
	 * - Personal address book, with access to labels, etc.
	 * - Issue send address book, includes partner organization "target" contacts (depends on organization and selected departments)
	 * - Organization or department address book
	 * This piece doesn't have the "Edit Contact" links, it's just for searching and using.
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Load_Contacts_List(MVC_Request $rq)
	{
		$this-> ctx_tree = $this-> setup_Contacts_Tree($rq);

		$this-> init_Template_System();
		echo $this->_drytpl-> render_File("components". DS. "contacts_list.html", $this->_out);
	}

	/**
	 * Constructs the list of entries in the contacts tree, which is actually a set of UL's and LI's, which are then processed by contact_list component
	 * @param MVC_Request $rq
	 */
	private function setup_Contacts_Tree(MVC_Request $rq)
	{
		$user = MVC_Core::$ss-> user;

		$ctx_tree = array();

		// If partner organization specified ($in-> partner_id), show "labels" representing the partner's "target contacts"
		// Note: Not implemented in the new mail system (yet)
		if($rq-> partner_id) {
			$partner_org = $this-> org-> read($rq-> partner_id, array());

			$partner_contacts = array(
				'id'=> "tgt_ALL", // Target Contacts for Organization (requires partner_id in Get_Contacts)
				'name'=> $partner_org['name'],
				'type'=> "issue_target",
				'children'=> array(
					array(
						'id'=> "tgt_ALL",
						'name'=> "All contacts",
						'type'=> "department",
						'color'=> "atoz"
					)
				)
			);
			// If a department list is specified, load the list of those departments
			if($rq-> target_dep_ids) {
				$m_dep = new App_Model_Department();
				$partner_deps = $m_dep-> get_List($partner_org['id'], array('dep_target'), function(QueryFactory &$q) use($rq) {
					// Filter to only the departments specified in the list
					$q-> where("`id` IN({$rq-> target_dep_ids})");
				});

				foreach($partner_deps as $dep) {
					$partner_contacts['children'][] = array(
						'id'=> 'tgt_'.$dep['id'], // Target Contacts for Department ID # (requires partner_id in Get_Contacts)
						'name'=> $dep['name'],
						'type'=> "department",
					);
				}
			}
			$ctx_tree[] = $partner_contacts;
		}

		// User mode contacts - Shows personal contacts and "self" (this is the default if no other mode is specified)
		if(!isset($rq-> mode) || $rq-> mode == 'user') {
			if($rq-> user_id && $rq-> user_id != MVC_Core::$ss-> user['id']) {
				// If an user_id is specified, check if it's different from the logged in user; If so, read information about the user
				$user = $this-> user-> read($rq-> user_id, array('user', 'department', 'organization'), true); // Organization ( and department ) data are necessary to compute ACLs properly
				$acls = $user['acls'];
			} else {
				// By default, the contacts are loaded for the logged in user
				$user = MVC_Core::$ss-> user;
				$acls = null;
			}
			$org_id = $user['org_id'];

			// User contacts are always available
			$personal_contacts = array(
				'type'=> 'ctx_user',
				'name'=> "User level",
				'children'=> array(
					array(
						'id'=> "user_ALL",
						'name'=> "(all Personal contacts)",
						'type'=> "personal",
						'color'=> "atoz",
						'count_el'=> $user['count']['ctx'],
						'edit'=> true // Personal contacts are always editable
					),
/*				array(
					'id'=> "user_AUTOOUT",
					'name'=> "From direct messages",
					'type'=> "auto_out"
				),*/ // Not currently implemented properly
					array(
						'id'=> "user_SELF",
						'name'=> sprintf("Myself (%s)", $user['name']),
						'type'=> "user_self",
						'edit'=> true
					)
				)
			);
			$labels = $this-> lab-> get_List($user['id'], 'con-usr');
			if(!empty($labels))
				foreach($labels as $label)
				$personal_contacts['children'][] = array(
					'id'=> 'user_'.$label['id'],
					'name'=> $label['name'],
					'type'=> "personal",
					'color'=> $label['color'],
					'count_el'=> $label['count_el']
				);
			// Add chunk to main array
			$ctx_tree[] = $personal_contacts;
		} elseif($rq-> mode == "org") {
			// Organization contacts; There is no user, instead, the list of departments for the specified organization are loaded, and of course org-level contacts
			if($rq-> org_id && $rq-> org_id != MVC_Core::$ss-> user['org_id']) {
				$org_id = (int)$rq-> org_id;
				$org = $this-> org-> read($org_id, array());
			} else {
				$org_id = MVC_Core::$ss-> user['org_id'];
				$org = MVC_Core::$ss-> organization;
			}
			// Logged in user's ACLs are considered here, he must be able to edit organization level
			$acls = null;
		}

		// Department contacts are visible to users their active department (if they have that right).
		// As for editing, only the "Department Chief" and users with "Can Edit Department Contacts" can do that
		if($this-> user-> test_ACL('privileges', 'dep_contacts_view', $acls)) {
			$department_contacts = array(
				'type'=> 'ctx_dep',
				'name'=> "Department level",
				'children'=> array()
			);

			$m_dep = new App_Model_Department();

			// A department manager can see all contacts from all departments (even all at once for certain users)
			if($this-> user-> test_ACL('privileges', 'org_deps_manager', $acls)) {
				$department_contacts['children'][] = array(
					'id'=> "dep_ALL",
					'name'=> "(All departments)",
					'type'=> "department",
					'color'=> "atoz"
// 					'columnset'=> 'reversed'
				);

				// Get list of departments for the organization
				$dep_list = $m_dep-> get_List($org_id, array());
				foreach($dep_list as $dep) {

					$dep_entry = array(
						'id'=> 'dep_'.$dep['id'],
						'name'=> $dep['name'],
						'type'=> "department",
						'count_el'=> $dep['count_ctx']
					);

					$labels = $this-> lab-> get_List($dep['id'], 'con-dep');
					if(!empty($labels)) {
						$dep_entry['children'][] = array(
							'id'=> $dep_entry['id'],
							'name'=> "(all Dep contacts)",
							'type'=> "department",
							'color'=> "mag"
						);

						foreach($labels as $label)
							$dep_entry['children'][] = array(
								'id'=> 'lab_'.$label['id'],
								'par_id'=> 'dep_'.$dep['id'],
								'name'=> $label['name'],
								'type'=> "department",
								'color'=> $label['color'],
								'count_el'=> $label['count_el']
							);
					}

					$department_contacts['children'][] = $dep_entry;
				}

			} else {
				// Show only the departments in which the user is a member
				$user_deps = $m_dep-> get_User_Dep_Relation_List($user['id'], $org_id, false); // false = quick mode (id, name)

				// In case no departements were returned, it means that that the user is not member of any yet - nothing to select, do not show the department selector
				if($user_deps) {
					// replace the key "disabled" with "_disabled" as it affects jqxDropDownList
					foreach($user_deps as $dep) {
						if($dep['disabled'])
							continue; // Skip disabled departments
						$dep_entry = array(
							'id'=> "dep_".$dep['id'],
							'name'=> $dep['name'],
							'type'=> "department"
						);

						$labels = $this-> lab-> get_List($dep['id'], 'con-dep');
						if(!empty($labels)) {
							$dep_entry['children'][] = array(
								'id'=> $dep_entry['id'],
								'name'=> "(all Dep contacts)",
								'type'=> "department",
								'color'=> "mag"
							);

							foreach($labels as $label)
								$dep_entry['children'][] = array(
									'id'=> 'lab_'.$label['id'],
									'par_id'=> 'dep_'.$dep['id'],
									'name'=> $label['name'],
									'type'=> "personal",
									'color'=> $label['color'],
									'count_el'=> $label['count_el']
								);
						}

						$department_contacts['children'][] = $dep_entry;
					}
				}
			}

			if($rq-> mode != "org")
				$ctx_tree[] = $department_contacts;
		}

		// Organization level contacts can be set visible to all users of an organization
		if($this-> user-> test_ACL('privileges', 'org_contacts_view', $acls)) {
			$organization_contacts = array(
				'type'=> 'ctx_org',
				'name'=> "Organization level",
				'children'=> array()
			);

			$able_to_edit_org = $this-> user-> test_ACL('privileges', 'org_admin', $acls) || $this-> user-> test_ACL('privileges', 'org_contacts_manager', $acls);

			// Users that can see organization contacts can see the subdivisions, but only certain users can edit them
			$organization_contacts['children'][] = array(
				'id'=> "org_ALL",
				'name'=> "(All Org. contacts)",
				'type'=> "organization"
			);

			// If able to see the organization users, also show their contacts in the address book
			if($this-> user-> test_ACL('privileges', 'org_users_view', $acls)) {
				$organization_contacts['children'][] = array(
					'id'=> "org_USERS",
					'name'=> "(Organization users)",
					'type'=> "org_users"
				);
			}

			// Add the organization labels
			$labels = $this-> lab-> get_List($org_id, 'con-org');
			if($labels)
				foreach($labels as $label)
				$organization_contacts['children'][] = array(
					'id'=> 'lab_'.$label['id'],
					'par_id'=> 'org_ALL',
					'name'=> $label['name'],
					'type'=> "organization",
					'color'=> $label['color'],
					'count_el'=> $label['count_el']
				);

			if($rq-> mode == "org") {
				// TODO: Issue targets are actually references to other contacts, they can't be removed, just "disconnected"
				// The grid should display for which department they are set
				$organization_contacts['children'][] = array(
					'id'=> "org_TARGET",
					'name'=> "Issue comm. targets",
					'type'=> "issue_target",
					'edit'=> $able_to_edit_org
				);

				$organization_contacts['children'][] = array(
					'id'=> "org_SELF",
					'name'=> $org['name'],
					'type'=> "org_self",
					'edit'=> $this-> user-> test_ACL('privileges', 'org_admin', $acls)
				);

				// Temporary: Add "old-mode" organization target contacts until everything is ready
				$organization_contacts['children'][] = array(
					'id'=> "org_OLDTARGET",
					'name'=> "[OLD Comm Targets]",
					'type'=> "issue_oldtarget",
					'edit'=> $able_to_edit_org
				);
			}

			// Order is swapped for Org mode (org first, dep second)
			$ctx_tree[] = $organization_contacts;
			if($rq-> mode == "org")
				$ctx_tree[] = $department_contacts;
		}

		$ctx_tree[] = array(
			'id'=> "global",
			'name'=> "Yellow pages",
			'type'=> "global"
		);

		if($rq-> rec_id) {
			array_unshift($ctx_tree, array(
				'id'=> "iss_".$rq-> rec_id, // Issue linked contacts
				'name'=> "Linked to this record",
				'type'=> "issue" // The icon shoud depend on issue type (somehow ?)
			));
		}

	/*
		$ctx_tree[] = array(
			'id'=> "system",
			'name'=> "Software Support",
			'type'=> "system"
		);
	*/
		return $ctx_tree;
	}

	/**
	 * Reads a list of contacts under a certain criteria, returning data for a jqxGrid
	 * - Used in the Contacts List component (Address Book)
	 */
	public function action_Get_Contacts(MVC_Request $rq)
	{
		if($rq-> org_id && empty($rq-> user_id)) {
			// Reading contacts for an organization ( department and organization level; User contacts are ignored )
			$mode = 'org';
			$org_id = (int)$rq-> org_id;
		} elseif($rq-> user_id && $rq-> user_id != MVC_Core::$ss-> user['id']) {
			$mode = 'user';
			$user = $this-> user-> read($rq-> user_id, array('user'), false); // No need to compute ACLs here
			$org_id = $user['org_id'];
		} else {
			$mode = 'user';
			$user = MVC_Core::$ss-> user;
			$org_id = $user['org_id'];
		}

		// TODO: Extra security checks to see if logged in user can actually read these contacts...

		// Define filters for the the loading functions
		$locator = null; $qfo_filter = null; $wtr = array('contact');
		if($mode == 'user' && substr($rq-> label, 0, 5) == 'user_') {
			// Loading user contacts
			$selector = substr($rq-> label, 5);
			$type = "personal";
			$wtr = array('contact');
			$locator = $user['id'];
			if(is_numeric($selector)) {
				// Extra filter for contacts associated with one label ( not needed when ALL are read )
				$qfo_filter = function(&$q) use($selector) {
					$q-> where('`ctx`.label_id = ?', (int)$selector);
				};
			} elseif($selector == 'SELF') {
				$type = "user_self";
			} elseif($selector == 'AUTOOUT')
				$type = "auto_out";
		// Contacts connected to a label
		} elseif(substr($rq-> label, 0, 4) == 'lab_') {

			$locator = substr($rq-> label, 4);
			$type = null;
			$qfo_filter = function(&$q) use($locator) {
				$q-> where('`ctx`.label_id = ?', (int)$locator);
			};
			$wtr = array('contact');

		} elseif(substr($rq-> label, 0, 4) == 'dep_') {
			// Loading department level contacts
			$selector = substr($rq-> label, 4);
			$type = "department";
			if(is_numeric($selector)) {
				$locator = $selector;
			} else {
				$wtr = array('contact', 'department'); // Read department name for global list, so the grid shows the name of the department
				$locator = null;
			}

		} elseif(substr($rq-> label, 0, 4) == 'org_') {
			// Loading organization level contacts
			$selector = substr($rq-> label, 4);
			switch($selector) {
				// Get the list of contacts which are used as targets
				case 'TARGET':
					$locator = $this-> org-> targetContact_Get_IDs($org_id);
					$type = 'idlist';
					break;
				// Get all the contacts which are linked to issues managed by this organization
				case 'ISSUE':
					$type = array('isspost', 'isslink');
					$wtr = array('contact', 'issue'); // Note - This triggers linking "issue_contacts" table and reading the link's internal label
					break;
				case 'AUTOIN':
					$type = "auto_in";
					break;
				case 'SELF':
					$type = "org_self";
					break;
				case 'OLDTARGET':
					$type = "issue_target";
					break;
				// Lists contacts for registered organization users
				case 'USERS':
					$type = "user_self";
					$org_id = (int)$rq-> org_id;
					break;
				case 'ALL':
				default:
					$type = "organization";
					if(is_numeric($selector)) {
						// Extra filter for contacts associated with one label ( not needed when ALL are read )
						$qfo_filter = function(&$q) use($selector) {
							$q-> where('`ctx`.label_id = ?', (int)$selector);
						};
					}
			}
		} elseif(substr($rq-> label, 0, 4) == 'tgt_') {
			// Loading target contacts for a another organization ( partner or from frontend, incoming )
			$selector = substr($rq-> label, 4);
			$locator = $this-> org-> targetContact_Get_IDs($rq-> partner_id, $rq-> istkey, is_numeric($selector)? $selector: null);
			$type = 'idlist';
		} elseif(substr($rq-> label, 0, 4) == 'iss_') {
			// Loading contacts linked to a record.
			$selector = substr($rq-> label, 4);

			if($this-> user-> is_Unrestricted())
				$linked_cons = $this-> con-> con2iss_Get_List($selector, false, 'unrestricted');
			else
				$linked_cons = $this-> con-> con2iss_Get_List($selector, false, 'org', $org_id);

 			if(!$linked_cons || count($linked_cons)==0)
 				return $this-> send_JSON(array());
 			else {
 				// Process the results and return contacts directly
 				$contacts = array();
 				foreach($linked_cons as $con) {
 					$contacts[] = array(
 						'id'=> (int)$con['contact_id'],
 						'org_id'=> (int)$con['contact']['org_id'],
 						'user_id'=> (int)$con['contact']['user_id'],
 						'type'=> "record",
 						'ctime'=> $con['contact']['ctime'],
 						'email'=> $con['contact']['email'],
 						'name'=> $con['contact']['name']
 					);
 				}
 				return $this-> send_JSON($contacts);
 			}

		} elseif($rq-> label == "global") {
			// Loading YellowPages - This should have virtual search/sort/scrolling ?
			$type = "global";
		} elseif($rq-> label == "system") {
			// Loading Support contacts
			$type = "system";
		} else {
			// An invalid combination detected, return empty result
			return $this-> send_JSON(array());
		}

		$order_by = '`ctx`.name ASC'; // Until virtual filters are implemented here, contacts will always be alphabetically ordered

		// Read the list
		$contacts = $this-> con-> get_List($org_id, $locator, $type, $wtr, $order_by, 0, 0, $qfo_filter);
		if(!$contacts)
			return $this-> send_JSON(array()); // Empty result

		return $this-> send_JSON($contacts);
	}

	/**
	 * Smart Ajax Search function for locating contacts by their e-mail, name, organization ( and other parameters )
	 * -- TODO: For empty query string, it returns the user's favourite contacts (if any)
	 * -- Prioritized search order ( personal > department > organization > auto_out > isspost > global )
	 * -- Attempts to determine what the search string represents (multi-tier search):
	 *    - If it contains (at) or search string ends with a typical web TLD (.com, .net, .org, .aero), it searches in e-mail addresses
	 *    - Otherwise (or when not located by e-mail), it attempts to locate the contact name starting with the query string
	 *    - If nothing found, looks into xdata, by prepending the search string with: "company":" or "phone":" (the most common fields)
	 *    - If still nothing found it returns a special contact (with id 0) which says: "No results", + TODO: user's favourite contacts (if any)
	 *
	 * @param MVC_Request $in
	 */
	public function action_Search(MVC_Request $in)
	{
		$empty_init = array(
			'id'=> 0,
			'org_id'=> 0,
			'type'=> 'system',
			'name'=> "Start typing to search contacts",
			'email'=> "<div style='margin-bottom: 8px;'>You can always type an e-mail address into the box.</div>"
		);

		if($in-> id) {
			// We have an exact id, this function should return exactly one row, ignoring any other parameters (this is used to reopen the contact that has been previously selected)
			$contact = $this-> con-> read($in-> id);
			if($contact)
				return $this-> send_JSON(array($contact));
			else
				return $this-> send_JSON(array());
		}

		// Preset mode - Return a fixed list of results (controlled by the "initialize" parameter) that do not depend on any other factors like query, scope
		if($in-> initialize && !$in-> q) {
			$initialize = $in-> initialize;

			// Check some special prefixes to the initialize variable:
			if(substr($initialize, 0, 5) == 'issue') {
				// CSS will be initialized with the contacts linked to an issue
				$issue_id = (int)substr($initialize, 6);
				$initialize = "issue";
			}

			if(substr($initialize, 0, 3) == 'org') {
				// CSS will be initialized with the contacts of type "issue_target" belonging to a certain organization
				$org_id = (int)substr($initialize, 4);
				$initialize = "org_target";
			}

			switch($initialize) {
				// Do not return anything ( the dropdown doesn't show )
				case 'empty':
					return $this-> send_JSON(array());

				case 'recent':
					$cookie_name = "its_recent_contacts";
					// User mode?
					if(isset(MVC_Core::$ss-> user))
						$cookie_name.="_".MVC_Core::$ss-> user['id'];

					$cookies = new MVC_Persist('cookie');
					$id_list = $cookies-> {$cookie_name};
					// Initialize if necessary ( if there is nothing saved yet )
					if(!$id_list || !is_array($id_list))
						$id_list = array();
					break;

				case 'issue':
					// Get the list
					if(MVC_Core::$ss-> user) {
						if(isset(MVC_Core::$ss-> user['is_admin']))
							$visibility_type = 'unrestricted';
						else {
							// Read very basic information about the issue, to see if the user from the organization owning the issue, or a partner to which the issue was shared with. Visiblity depends on this.
							$m_issue = new App_Model_Issue();
							$issue_basic = $m_issue-> read($issue_id, array()); // , MVC_Core::$ss-> user['org_id']
							if(empty($issue_basic))
								return $this-> send_JSON(array()); // Ooops, attempting to see contacts from an issue the user doesn't have access to

							if($issue_basic['org_id'] == MVC_Core::$ss-> user['org_id'])
								$visibility_type = 'org';
							else
								$visibility_type = 'partner';
						}
					} else {
						$visibility_type = 'public';
					}

					// Read the issue-linked contacts and offer them as initial list
					$contact_links = $this-> con-> con2iss_Get_List($issue_id, null, $visibility_type, MVC_Core::$ss-> user['org_id']);
					$id_list = array();
					foreach($contact_links as $link)
						$id_list[] = (int)$link['contact_id'];

					break;

				// This reads directly the contacts
				case 'org_target':
					$ctx = $this-> con-> get_List($org_id, null, "issue_target", array('contact', 'xdata'));
					if(is_array($ctx))
						return $this-> send_JSON($ctx);
					else
						return $this-> send_JSON(array(array(
							'id'=> 0,
							'org_id'=> 0,
							'type'=> 'system',
							'name'=> "No contacts are currently available",
							'email'=> "<div style='margin-bottom: 8px;'>You can always type an e-mail address into the box.</div>"
						)));

					// When the initialize variable is not present or contains something else
				default:
					return $this-> send_JSON(array($empty_init));
			}

			if(empty($id_list))
				return $this-> send_JSON(array($empty_init));
			else {
				$id_csv_list = implode(",", $id_list);
				$ctx = $this-> con-> get_List(null, $id_list, "idlist", array('contact', 'xdata'),
					"FIELD(`ctx`.id, {$id_csv_list})", 7); // Order by the presented ID list ; Max 7 results

				return $this-> send_JSON($ctx);
			}
		}

		// Search mode - $csscope and $q are used

		// Decode contact search scope
		if(isset($in-> css))
			$csscope = @json_decode($in-> css, true);
		if(!isset($csscope) or is_null($csscope))
			$csscope = array();

		// Prepare parameters
		$types = array(); $locator = array();

		// Issue linked - They take priority above everything else
		$id_list = array();
		if(isset($csscope['isslink'])) {
			// Get the list
			if($this-> user-> is_Unrestricted())
				$visibility_type = 'unrestricted';
			else {
				// Read very basic information about the issue, to see if the user from the organization owning the issue, or a partner to which the issue was shared with. Visiblity depends on this.
				$m_issue = new App_Model_Issue();
				$issue_basic = $m_issue-> read($csscope['isslink'], array()); // , MVC_Core::$ss-> user['org_id']
				if(empty($issue_basic))
					return $this-> send_JSON(array()); // Ooops, attempting to see contacts from an issue the user doesn't have access to

				if($issue_basic['master_org_id'] == MVC_Core::$ss-> user['org_id'])
					$visibility_type = 'org';
				else
					$visibility_type = 'partner';
			}

			// Read the issue-linked and offer them in the search results as well.
			$contact_links = $this-> con-> con2iss_Get_List($csscope['isslink'], null, $visibility_type, MVC_Core::$ss-> user['org_id']);
			foreach($contact_links as $link)
				$id_list[] = (int)$link['contact_id'];
		}

		// Personal contacts, user_id is needed (so only contacts of the logged in user are searched for)
		if(isset($csscope['personal']) && $csscope['personal']==true) {
			$org_id = (int)MVC_Core::$ss-> user['org_id'];
			$locator['user_id'] = (int)MVC_Core::$ss-> user['id'];
			$types[] = "personal";
		}
		// Department contacts, con_id is needed and will match the user's active department. TODO: Includes system users that are in the user's active department (TODO: but only if the user has a certain privilege to see them)
		if(isset($csscope['department']) && $csscope['department']==true) {
			$org_id = (int)MVC_Core::$ss-> user['org_id'];
			$locator['dep_id'] = (int)MVC_Core::$ss-> user['cdep_id'];
			$types[] = "department";
		}
		// Organization contacts (those available to all users of the organization).
		if(isset($csscope['organization']) && $csscope['organization']==true) {
			$org_id = (int)MVC_Core::$ss-> user['org_id'];
			$types[] = "organization";
		}

		// Issue submit targets - Search among the contacts of type "issue_target" belonging to a certain organization
		if(isset($csscope['isstarget']) && $csscope['isstarget']>0) {
			$org_id = (int)$csscope['isstarget'];
			$types[] = "issue_target";
		}

		// When searching in either department and organization contacts, also search system user contacts
		if((isset($csscope['department']) && $csscope['department']==true) || (isset($csscope['organization']) && $csscope['organization']==true) || (isset($csscope['user_self']) && $csscope['user_self']==true)) {
			// if(having privilege to see colleagues)
			$types[] = "user_self"; // Includes system users in the same department or organization (TODO: but only if the user has a certain privilege to see them)
		}

		// Other contacts to which the user has send messages to (auto_out), when address was manually typed (instead of using the contact locator)
		// -- These contacts can be converted by the user into personal, department, organization or global contacts (assuming he has the required privileges)
		if(isset($csscope['auto_out']) && $csscope['auto_out']==true) {
			$locator['user_id'] = (int)MVC_Core::$ss-> user['id'];
			$types[] = "auto_out";
		}
		// Contacts which have been created from posting issues to the system - They are related to the user's organization
		if(isset($csscope['isspost']) && $csscope['isspost']==true) {
			$org_id = (int)MVC_Core::$ss-> user['org_id'];
			$types[] = "isspost";
		}

		// Yellow-pages type contacts, available to anyone
		if(isset($csscope['global']) && $csscope['global']==true) {
			$types[] = "global";
		}

		// Drafts are similar to personal contacts, but the might not have an associated user - They are checked last
		if(isset($csscope['draft']) && $csscope['draft']==true) {
			if(isset(MVC_Core::$ss-> user)) {
				$org_id = (int)MVC_Core::$ss-> user['org_id'];
				$locator['user_id'] = (int)MVC_Core::$ss-> user['id'];
			}
			$types[] = "draft";
		}

		// Also use recent. Append to IDList
		if(isset($csscope['recent']) && $csscope['recent']==true) {
			$cookie_name = "its_recent_contacts";
			// User mode?
			if(isset(MVC_Core::$ss-> user))
				$cookie_name.="_".MVC_Core::$ss-> user['id'];

			$cookies = new MVC_Persist('cookie');
			$recent_list = $cookies-> {$cookie_name};

			if(!is_array($recent_list))
				$recent_list = array();

			// Append (merge) $id_list with $recent_list
			$id_list = array_unique(array_merge($id_list, $recent_list));
		}

		// When no types are specified, only the favourite contacts are returned
		// TODO: Helper function for loading user's favourite contacts - since there are several cases in which these are needed
		if(empty($types) && empty($id_list)) {
			return $this-> send_JSON(array(array(
				'id'=> 0,
				'org_id'=> 0,
				'type'=> 'system',
				'name'=> "All search options are unchecked",
				'email'=> "<div style='margin-bottom: 8px;'>Please select at least one type of contact or there's nothing to search for !</div>"
			)));
		}

		if(!empty($types)) {
			// Prepare the "list" for the FIELD() function of MySQL, which is used for custom sorting (results are returned in this preffered order)
			$mysql_field_function = "FIELD(`ctx`.`type`";
			foreach($types as $type)
				$mysql_field_function.=", '{$type}'";
 			$mysql_field_function.=")";
		}

		$wtr = array('contact', 'xdata');
		if(isset($csscope['department']) && $csscope['department']==true && !(isset($csscope['organization']) && $csscope['organization']==true)) {
			$wtr[] = 'user';
			$dep_user_filter = true;
		}

		// First, attempt to locate the text withing the $id_list, but still checking the query string
		/// The order is by the list of IDs present in $id_list
		if(!empty($id_list)) {
			$id_csv_list = implode(",", $id_list);
			$ctx = $this-> con-> get_List(null, $id_list, "idlist", $wtr,
				"FIELD(`ctx`.id, {$id_csv_list})",
				6, 0,
				function(QueryFactory &$q) use($in) {// The extra filtering, since this is a search
					$qs = trim($in-> q);

					if(preg_match("/<(.*@.*)>/", $qs, $m))
						// If the query string is in the format: "Name <email@address>", extract the exact e-mail and search by it
						$q-> where("`ctx`.email = ?", $m[1]);
					elseif(preg_match("/<(.*)/", $qs, $m))
						// Search string contains <, search for what imediately follows it in the e-mail field
						$q-> where("`ctx`.email LIKE(?)", $m[1]."%");
					elseif(strpos($qs, "@")!==false || in_array(substr($qs, -4), array(".com", ".org", ".net")) || in_array(substr($qs, -5), array(".aero", ".info")))
					// The query string contains an @ sign or it ends with a typical domain name, an e-mail address is probably typed in, only attempt to locate in the "email" field
						$q-> where("`ctx`.email LIKE(?)", "%$qs%");
					elseif(strpos($qs, " ")!==false)
					// The query string contains a space, it's definitely not an e-mail, search just in names
						$q-> where("`ctx`.name LIKE(?)", "%$qs%");
					else
						// Unknown, might be name or e-mail, search in both
						$q-> where("`ctx`.name LIKE(?) OR `ctx`.email LIKE(?)", "%$qs%", "%$qs%");
				}
			);

			// If not located in the ID list and there's no other type of contact to search, return the empty result information
			if(empty($ctx) && empty($types)) {
				$ctx[] = array(
					'id'=> 0,
					'org_id'=> 0,
					'type'=> 'system',
					'name'=> "Search returned no results",
					'email'=> "<div style='margin-bottom: 8px;'>You can always type an e-mail address into the box.</div>"
				);
			}
		}
		// Initial query search
		if(empty($ctx)) {
			$ctx = $this-> con-> get_List(
				isset($org_id)? $org_id: null,		// Org ID if one of types "personal", "department", "organization" are present in the search scope
				$locator,							// The "locator" might contain user id and department id (for certain types)
				$types,								// Array with types of contacts that will be searched
				$wtr,			// Read and decode Xdata too, it will be needed
				$mysql_field_function.", `ctx`.`name` ASC",	// Sort by type first (custom order), then name
				6, 0,								// Up to 6 results here, first in the list
				function(QueryFactory &$q) use($in) {// The extra filtering, since this is a search
					$qs = trim($in-> q);

					if(preg_match("/<(.*@.*)>/", $qs, $m))
						// If the query string is in the format: "Name <email@address>", extract the e-mail and search by it
						$q-> where("`ctx`.email = ?", $m[1]);
					elseif(strpos($qs, "@")!==false || in_array(substr($qs, -4), array(".com", ".org", ".net")) || in_array(substr($qs, -5), array(".aero")))
						// The query string contains an @ sign or it ends with a typical domain name, an e-mail address is probably typed in, only attempt to locate in the "email" field
						$q-> where("`ctx`.email LIKE(?)", "%$qs%");
					elseif(strpos($qs, " ")!==false)
						// The query string contains a space, it's definitely not an e-mail, search just in names
						$q-> where("`ctx`.name LIKE(?)", "%$qs%");
					else
						// Unknown, might be name or e-mail, search in both
						$q-> where("`ctx`.name LIKE(?) OR `ctx`.email LIKE(?)", "%$qs%", "%$qs%");

					// Only in the intial search, if only department-wide is selected but not the organization-wide, limit system user search to the same department as the logged in user's
					if(isset($dep_user_filter))
						$q-> where("CASE WHEN `ctx`.`type` IN('user_self') THEN `usr`.`cdep_id` = ? ELSE 1 END", $locator['dep_id']);
				}
			);
		}
		// Initial search by e-mail or name failed, attempt to locate stuff into Xdata, but only when search list for missing Q value is not present
		if(empty($ctx)) {
			$ctx = $this-> con-> get_List(
				isset($org_id)? $org_id: null, $locator, $types, array('contact', 'xdata'),
				$mysql_field_function.", `ctx`.`name` ASC", 6, 0,
				function(QueryFactory &$q) use($in) {
					$qs = trim($in-> q);
					// Search in Xdata field (which might be slow) - Only for start of JSON strings
					// TODO: Special indexing algorithm that uses xdata fields to locate contacts, and searches very fast (and also more correct, current search in xdata is very rudimentary)
					$q-> where("`ctx`.`xdata` LIKE (?)", "%\":\"$qs%");
				}
			);
		}

		// Still no results ? Create a fake contact entry which has ID 0 and contains the name "No results" - JavaScript will know not to use this when clicked on
		if(empty($ctx)) {
			$ctx[] = array(
				'id'=> 0,
				'org_id'=> 0,
				'type'=> 'system',
				'name'=> "Search returned no results",
				'email'=> "<div style='margin-bottom: 8px;'>You can always type an e-mail address into the box.</div>"
			);
		}

		// TODO: Also append user's favourites if they exist
		// $ctx[0]['name'].=", returning only your favourites";

		$this-> send_JSON($ctx);
	}

	/**
	 * Loads contact data for display or editing
	 */
	public function action_Vcard()
	{

	}

	/**
	 * Checks if a contact can be deleted by the current logged in user, and returns the "fail" JSON if not;
	 *
	 * @param array $con The contact structure
	 * @param int $user The user for which contacts are deleted (usually the logged in user but some users are managed by other users)
	 * @param bool $multi If TRUE, it doesn't return an error message, instead it increases the counter for number of failures
	 */
	protected function check_Contact_Deletion(&$con, &$user, $multi = false)
	{
		// Admin/Supervisor is able to delete any contact; TODO: Supervisor can only delete from their allocated organizations
		// Logged in but restricted can delete from own organization if having certain privileges
		// Any system user can delete personal contacts
		if(isset($user['is_restricted'])) {
			if($con['org_id'] != $user['org_id'])
				return $this-> send_Fail_JSON("Cannot delete ! This contact doesn't belong to your organization.");

			if($con['type'] == 'organization' || $con['type'] == 'org_self')
				if(!$this-> user-> test_ACL('privileges', 'org_contacts_manager', $user['acls']))
				return $this-> send_Fail_JSON("Unfortunately, you are not allowed to remove organization contacts");

			if($con['type'] == 'department' || $con['type'] == 'dep_self') {
				if(!$this-> user-> test_ACL('privileges', 'org_contacts_manager', $user['acls']) && !$this-> user-> test_ACL('privileges', 'dep_contacts_manager', $user['acls']) && !$con['dep_id'] == $user['cdep_id'])
					return $this-> send_Fail_JSON("Unfortunately, you are not allowed to remove department contacts");
			}

			if(!$con['type'] == 'personal')
				return $this-> send_Fail_JSON("Unfortunately, this type of contact can only be removed by the administrator");
		}

		// Check if the contact is the user's primary; It cannot be deleted (for nobody, including admin)
		if($con['id'] == $user['contact_id'])
			return $this-> send_Fail_JSON("This is the primary contact for the user account. It cannot be deleted.");

		// TODO: Perhaps more security checks here ...
		return true; // Success, delete is allowed
	}

	/**
	 * Deletes a contact from the database
	 *
	 * @param MVC_Request $post
	 * @return JSON
	 */
	public function action_Delete(MVC_Request $post)
	{
		if(!$post-> id)
			return $this-> send_Fail_JSON("Contact ID not present in the request.");

		// Non-logged in cannot delete anything (for now);
		if(!MVC_Core::$ss-> user)
			return $this-> send_Fail_JSON("Currently only logged in users have the ability to delete contacts");

		if($this-> user-> is_Unrestricted()) {
			// Delete for yourself (admin acc) or for another user ?
			if($post-> user_id && $post-> user_id != MVC_Core::$ss-> user['id'])
				$user = $this-> user-> read($post-> user_id, array('user'), false);
			else
				$user = MVC_Core::$ss-> user;
		} else {
			$user = MVC_Core::$ss-> user;
			$user['is_restricted'] = true;
		}

		// Load the contact to see what kind of contact it is and if the user has access to it
		$con = $this-> con-> read($post-> id, false);
		if(!$con)
			return $this-> send_Fail_JSON("Contact not found. (It might have already been deleted)");

		if(!$this-> check_Contact_Deletion($con, $user))
			return false;

		// No more real contact deletion, at least not from here...
		//	$r = $this-> con-> delete($post-> id);

		// ... instead, the contact is simply hidden
		$r = $this-> con-> update($post-> id, array('state'=> "archive"));
		if(!$r)
			$this-> send_Fail_JSON("Contact could not be deleted, possible database error.");

		// Delete other contact-related data which needs this ID (org_target_contacts)
		$this-> org-> targetContact_Unlink($con['org_id'], (int)$post-> id);
		$this-> send_JSON(array('success'=> true, 'response'=> sprintf("The contact <b>%s</b> has been removed", $con['email'])));
	}

	/**
	 * Deletes several contacts at once
	 * -- The function checks and deletes each contact individually, if there are problems it returns the number of contacts which could not be deleted for any reason ( no access, missing, etc. )
	 *
	 * @param MVC_Request $post
	 */
	public function action_Delete_Multiple(MVC_Request $post)
	{
		if(!$post-> ids)
			return $this-> send_Fail_JSON("No contact ID or IDs are present in the request.");

		// Non-logged in cannot delete anything (for now);
		if(!MVC_Core::$ss-> user)
			return $this-> send_Fail_JSON("Currently only logged in users have the ability to delete contacts");

		if($this-> user-> is_Unrestricted()) {
			// Delete for yourself (admin acc) or for another user ?
			if($post-> user_id && $post-> user_id != MVC_Core::$ss-> user['id'])
				$user = $this-> user-> read($post-> user_id, array('user'), false);
			else
				$user = MVC_Core::$ss-> user;
			$user['is_unrestricted'] = true;
		} else
			$user = MVC_Core::$ss-> user;

		// Go through the list of IDs and check and delete each contact individually; Record number of failures
		$delete_fail_counter = 0;
		foreach($post-> ids as $id) {
			$con = $this-> con-> read($id, false);
			if(!$con) {
				$delete_fail_counter++;
				continue;
			}

			// Check contact security
			if(!$this-> check_Contact_Deletion($con, $user)) {
				$delete_fail_counter++;
				continue;
			}

			// No more real contact deletion, at least not from here...
//			$r = $this-> con-> delete($id);

			// ... instead, the contact is simply hidden
			$r = $this-> con-> update($id, array('state'=> "archive"));
			if(!$r) {
				$delete_fail_counter++;
				continue;
			} else {
				// Delete other contact-related data which needs this ID (org_target_contacts)
				$this-> org-> targetContact_Unlink($con['org_id'], $id);
			}
		}

		if($delete_fail_counter == 0)
			return $this-> send_JSON(array('success'=> true, 'response'=> sprintf("%d contact(s) have been deleted !", count($post-> ids))));
		elseif($delete_fail_counter < count($post-> ids))
			return $this-> send_JSON(array('success'=> true, 'response'=> sprintf("Contacts have been deleted but %d errors encountered", $delete_fail_counter)));
		else
			return $this-> send_Fail_JSON("No contacts could be deleted ! There might be a database error, please contact support.");
	}

	/**
	 * Sets the label for multiple contacts at once
	 * @param MVC_Request $post
	 */
	public function action_Set_Contacts_Label(MVC_Request $post)
	{
		if(!$post-> ids)
			return header("HTTP/1.0 400 No contact ID or IDs are present in the request");

		if(empty(MVC_Core::$ss-> user))
			return header("HTTP/1.0 403 Currently only logged in users can use contact labels");

		$r = $this-> con-> set_Label($post-> ids, $post-> label_id);
		if(!$r)
			return header("HTTP/1.0 400 Error while setting label for contacts");

		return $this-> send_JSON(array('response'=> sprintf("Label has been set for %d contacts", MVC_Core::$db-> affected_rows)));
	}

	/**
	 * Saves a contact id ($_POST['id']) to list of most recently used contacts (max 25?), stored in a cookie.
	 * -- The cookie name contains User ID, so different user will have different lists.
	 * -- However, if no user is logged in, there is only one unique list for each browser/cookie repository.
	 *
	 * TODO: Put this functionality in the contact model instead, not here, so it can be used by other controller/actions
	 *
	 * @param bool $respond JSON responses can be disabled if this method is called from another action.
	 */
	public function action_Add_To_Recent($respond = true, $override_id = null)
	{
		if(is_null($override_id)) {
			$post = new MVC_Request('post');
			if(!isset($post-> id))
				return $respond? $this-> send_Fail_JSON("No contact id present in the request."): false;

			$id = $post-> id;
		} else
			$id = (int)$override_id;

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

		// Check if the contact is already in the list, if yes, nothing to do
		if(count($recent_contacts)>0 && in_array($id, $recent_contacts))
			return $respond? $this-> send_JSON(array('success'=> true)): true;

		// If already at max, delete the oldest one
		if(count($recent_contacts)>=25)
			array_shift($recent_contacts);

		$recent_contacts[] = $id;

		// Finally, save the cookie.
		$cookies-> {$cookie_name} = $recent_contacts;
		return $respond? $this-> send_JSON(array('success'=> true)): true;
	}

}