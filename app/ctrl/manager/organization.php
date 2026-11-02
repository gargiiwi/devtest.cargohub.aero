<?php
/**
 * Organization information and management
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2020.01.30
 */
class App_Ctrl_Manager_Organization extends App_Ctrl_Base
{
	/**
	 * Department model, used by some actions of this module
	 * @var App_Model_Department
	 */
	protected $dep;
                                
	/** 
	 * Reference to the Grid View Model - Helps dev by providing autocompletion (Only initialized for certain methods)
	 * @var App_Model_GridView
	 */
	protected $gridview; 

	public static $_module_name = "Organization Manager";

	public function __construct()
	{ 
		parent::__construct(true);
	}

	/**
	 * Index action for the Organization management page
	 * TODO: Convert this if possible to "hashnav" component and continue modularization of JS code
	 */
	public function action_Index(MVC_Request $in)
	{
		// This has become quite heavy, lots of tabs with lots of controls...
		$this-> CFG['components'] = array("modal");
		$this-> CFG['modules'][] = "organization/_manager";
		$this-> CFG['jqwidgets'][] = array("switchbutton", "tabs", "splitter", "scrollbar", "listbox", "dropdownlist");
		$this-> CFG['jqwidgets'][] = array("input", "calendar", "datetimeinput", "navigationbar", "expander");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete");
		$this-> CFG['jqwidgets'][] = array("grid.edit", "combobox", "numberinput", "slider");
		// These are used only by the WYSIWYG editor (Canned Messages)
		// TODO: Replace with TinyMCE when possible
//		$this-> CFG['jqwidgets'][] = array("dropdownbutton", "colorpicker", "editor");
		$this-> CFG['xjs'] = array(
			'jqform' => true,
			'timeentry'=> true
// 			'fancybox2' => true // Not sure if used?
		);

		// prepare output
		$this-> prepare_Header(self::$_module_name);

		// Administrator can view/edit any org - Also gets a link to organization search
		if($this-> user-> is_Unrestricted()) {
			$org_id = isset($in-> id)? $in-> id: MVC_Core::$ss-> user['org_id'];
			// If the edited org is different from the last one, reset all grid state caches for this module
			if(MVC_Core::$ss-> perspective != $org_id) {
				unset(MVC_Core::$ss-> cache['grid_state']['org_templates']);
			}
			// ... then save the new org id in session
			MVC_Core::$ss-> perspective = $org_id;
		} else
			$org_id = MVC_Core::$ss-> user['org_id'];
                
                
                
                if(isset($in-> publication)){
                    $this->publication($in->id);
                    exit();
                }
                
                
//                IWI report cron settings for admin start
                if(isset($in-> reportcron)){
                    $this->reportcron($in->id);
                    exit();
                }
                if(isset($in-> reportcronlog)){
                    
                    $this->reportcronlog($in->cid,$in->orgid);
                    exit();
                }
					
//                IWI report cron settings for admin end
                
                

		// read organization information (will be used in multiple tabs)
		$org = $this-> org-> read($org_id, array('organization', 'contact', 'manager', 'orgtype'));

		// TODO: Review what's being sent to template and javascript part and make it cleaner
		$org['manager']['id'] = $org['manager_id'];

		MVC_NLS::load_Groups("contact,acls");

		// Prepare the contact information variable
		if(!empty($org['contact'])) {
			$m_con = new App_Model_Contact();
			$org['contact']['xdata'] = $m_con-> decode_Xdata($org['contact']['xdata'], false);
		}

		// Assamble the correct list of ACLs (privileges, access ids) for the organization:
		$acls_text = MVC_NLS::get_Object("acls", true);
                
                // IWI Get Total active users count of this org start
                $orgUserCount = $this->org->getOrgUserCount($org_id);
                $org['tot_active_users'] = $orgUserCount['tot_active_users'];
                // IWI Get Total active users count of this org end

		$this-> org_info = $org;

		// Display Org Privileges (and translated text) - used in "Overview" tab
		$display_acls = array();
		$display_acls['privileges'] = array();
		if(!empty($org['privileges']))
			foreach($org['privileges'] as $privilege)
				// Ignore privileges present in the DB but not in the $acls_text - Privileges have been changed through time.
				if(isset($acls_text['privileges'][$privilege]))
					$display_acls['privileges'][$privilege] = $acls_text['privileges'][$privilege];

		$this-> display_acls = $display_acls;
		$this-> privilege_groups = $acls_text['privilege_groups'];
//print_r($org);
//
//
		// And these go to JS, for various operations
		$this-> CFG['organization_manager'] = array(
			'id'=> (int)$org_id,
			'name'=> $org['name'],
			'type_id'=> $org['type_id'],
			'status'=> $org['status'],
			'type_name'=> $org['orgtype']['name'],
			'manager_id'=> (int)$org['manager_id'], // Required to autoselect manager in the Setup tab
			'contact_id'=> (int)$org['contact_id'],
			'features'=> $org['features'],	// Necessary to determine if certain tabs are available or not for this organization, and if certain commands are available for it's users
                        'org_logo'=>$org['org_logo'], // Add option to set or change logo from organization 
			'acls'=> array(					// Access control lists are used on both the overview tab (autoloaded) and the ACLs editor tab
				'privileges'=> $org['privileges'],
				'access_ids'=> $org['access_ids']
			),
			'count'=> $org['count']	,	// Primary counters are important to enable/disable certain operations
//			'xdata'=> @json_decode($org['xdata'])			// Outdated
		);

		// Load the issue types which are "ON" for an organization (used in serveral places)
		// Non-Superusers get this list filtered by their own access rights - And they also don't have access to the list of RT for the Setup tab, even if they have access to the tab itself)
		// - Only Admin/Supervisor can decide which record types are given to a certain organization (and all access rights at any level depend on that)
		$acls_rt = isset(MVC_Core::$ss-> user['acls']['ist'])? MVC_Core::$ss-> user['acls']['ist']: true;

		$ist_list_r = $this-> org-> ist_Get_List($org_id, true);
		$ist_source = array();
		foreach($ist_list_r as $row) {
			if($acls_rt !== true)
				if(!in_array($row['id'], $acls_rt))
					continue; // Skip if no access

			$ist_source[] = array(
				'id'=> (int)$row['id'],
				'name'=> $row['name'],
				'key'=> $row['key']
			);
		}
		$this-> CFG['organization_manager']['ist_source'] = $ist_source;

		// Load the list of groups this organization is connected to (used in several places)
		$org_grp_r = $this-> org-> get_Groups($org_id, null, true);
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
		$this-> CFG['organization_manager']['groups_source'] = $groups_source;

// 		dd($this-> CFG['organization_manager']['acls']);
// 		dd($ist_source);
// 		dd($groups_source);

		// All existing tabs, in the order they will be presented to the user:
		// TODO: Maybe an user preference of changing this order and/or hiding certain tabs when not needed?
		$all_available_tabs = array(
			// Core organization configuration (Setup)
			array(
				'id'=> "tab_setup",
				'title'=> "Setup",
				'icon'=> "administrator.png",
				'privilege'=> false // Only admin has access to this
			),
			// Departments tab - Displays the list of departments in the organization
			array(
				'id'=> "tab_deps",
				'title'=> "Departments",
				'icon'=> "plugin.png",
				'privilege'=> "org_deps_view"
			),
			// Organization Users tab - Shows all members of this organization
			array(
				'id'=> "tab_users",
				'title'=> "Users",
				'icon'=> "users.png",
				'privilege'=> "org_users_view"
			),
			// Organization Security tab - Access overrides search (Department and User level)
			array(
				'id'=> "tab_security",
				'title'=> "Security",
				'icon'=> "user_policeman.png",
				'privilege'=> "org_user_manager"
			),
				// Grid views manager: Allows copying existing views between users, departments and organization-wide views, and also copying to other organizations (Admin/Supervisor only)
			// - Cannot be used to create views, that must be done in the grid itself
			array(
				'id'=> "tab_gridviews",
				'title'=> "Grid views",
				'icon'=> "eye.png",
				'privilege'=> "grid_manage_views"
			),
			// Partners tab, shows information about linked partners (those that this organization can share or tranfer issues to)
			array(
				'id'=> "tab_partners",
				'title'=> "Partners",
				'icon'=> "handshake.png",
				'privilege'=> "org_partners_view"
			),
			// Record groups manager, for configuring overall access of organizations to data
			array(
				'id'=> "tab_groups",
				'title'=> "Record Groups",
				'icon'=> 'folders.png',
				'privilege'=> false, // Admin only for now
			),
			// Templates and Documents (will eventually replace "canned" as well)
			array(
				'id'=> "tab_templates",
				'title'=> "Templates",
				'icon'=> "template.png",
				'privilege'=> "org_templates_manager"
			),
			// (sub)domains from which the application can be used. Contain some frontend settings for each domain
			array(
				'id'=> "tab_domains",
				'title'=> "Domains",
				'icon'=> "www_page.png",
				'privilege'=> "org_domains_view"
			),
			// Access Control Lists for the entire organization
			array(
				'id'=> "tab_acls",
				'title'=> "Org. level access",
				'icon'=> "lock.png",
				'privilege'=> "org_access_manager"
			),
			// IP Address manager -
			// -- disabled because it doesn't exist yet --
/* 			array(
				'id'=> "tab_ipaddr",
				'title'=> "IP Addresses",
				'icon'=> "ip.png",
				'privilege'=> "org_ip_manager"
			),
 */
			// Organization defaults for user profile entries, used to control preferences, overview, data columns and other aspects for existing and new users. Admin only
			array(
				'id'=> "tab_profile_defaults",
				'title'=> "User profile defaults",
				'icon'=> "table_gear.png",
				'privilege'=> false // Only admin has access to this (for now)
			)
		);

		// The actual tabs that will be show, depending on access rights
		$user_tabs = array();
		foreach($all_available_tabs as $tab) {
			// special case, this tab is only visible when departments exist, even if the user has the ability to view departments
			// However deps manager (or admin) can see it anyway, with the purpose of being able to create a department by using the tab's toolbar button "Add new"
			if($tab['id'] == 'tab_deps') {
				if((!isset($org['features']['departments']) || $org['features']['departments']==0) && !$this-> user-> test_ACL('privileges', 'org_deps_manager'))
					continue;
			}

			// General case, tab visibility depends on a standard privilege
			if($tab['privilege'] === true || $this-> user-> test_ACL('privileges', $tab['privilege']))
				$user_tabs[] = $tab;
			else
				continue;
		}

		$this-> user_tabs = $user_tabs;

		$this-> module = array(
			'icon'=> "company-building-icon.png",
			'icon_alt'=> "(org)",
			'description'=> "Organization information and management",
			'title'=> $org['name'], 'title_para'=> $org['orgtype']['name']
		);

		// which body template to use, and run the view
		$this-> site_section = "organization". DS. "manager.html";
		$this-> view("private.html");
	}

	/**
	 * Loads and prepares HTML document which will become the content of one of the tabs for the My Organization module
	 *   Loads specific data on demand, for the dynamic content in that tab.
	 */
	public function action_Load_Tab_HTML(MVC_Request $in)
	{
		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];
		$this-> prepare_Template_ACLs();

		if(substr($in-> tab, 0, 10) == "tab_group_") {
			// These are a bit special, because there can be multiple tabs with similar layout but different data
			$group_type = substr($in-> tab, 10);

			// Load the grup type
			$group_plugin = MVC_Core::factory_Plugin('group', $group_type);
			$config = $group_plugin-> get_Config();
			$this-> group_config = $group_plugin-> get_Config();

			$template_name = "tab_groups";

		} else {
			switch($in-> tab) {
				// Load the organization's departments so they are ready for the template and the corresponding JS
				case 'tab_deps':
					$this-> dep = $this-> factory_Model("Department", "dep");
					// List of departments in this organization; quick mode, just ID, Name
					$deps = $this-> dep-> get_List($org_id, array());

					$this-> has_deps = (bool)$deps;
					// Hybrid response (HTML+JSON)
					$this-> send_JSON_Headers(array(
						'dataSources'=> array( // => self.config.dataSources
							'deps'=> $deps?: array()
						)
					));
					break;

				// Load the list of organization's users along with some details to be displayed in the right-side panel
				case 'tab_users':
					$edit_access = $this-> user-> test_ACL('privileges', 'org_user_manager');

					// Read all users in the organization (Also read their active department for grouping purposes)
					// This also filters deactivate users for non-admin and users without ability to edit other users
					$users = $this-> user-> get_List(array('department'), function(QueryFactory &$q) use ($org_id, $edit_access) {
						$q-> where('`usr`.org_id = ?', $org_id);

						if(!$edit_access)
							$q-> where('`usr`.status!="disabled" AND `usr`.status!="invisible"');
					}, null, "`name` ASC");
					foreach($users as &$user) {
						if(!empty($user['department']['name']))
							$user['group'] = substr($user['department']['name'], 0, 27). (strlen($user['department']['name'])>27?'...':'');
						else
							$user['group'] = "(No active department)";
						unset($user['department'], $user['user2dep'], $user['org_id']);
					}
    
     
  

					$this-> has_users = (bool)$users;
					$this-> current_org_modify = $org_id;
					// Hybrid response (HTML+JSON)
					//$this-> send_JSON_Headers(array(
					//	'dataSources'=> array( // => self.config.dataSources
					//		'users'=> $users?: array()
					//	)
					//));
					break;
				case 'tab_security':
					// ACLs strings, used as a selector
					MVC_NLS::load_Groups("acls");
					$acls = MVC_NLS::get_Object("acls", true); // Used by the [on|off] buttons

					$this-> send_JSON_Headers(array(
						'tab_security'=> array(
							'priv_Source'=> $acls['privileges']
						)
					));
					break;

				// Grid views manager
				case 'tab_gridviews':
					// Persistent grid state (session)
					if(isset(MVC_Core::$ss-> cache['grid_state']['org_gridviews']))
						$grid_state = MVC_Core::$ss-> cache['grid_state']['org_gridviews'];
					else {
						// Default grid state
						$grid_state = array(
							'pagenum'=> 0,
							'pagesize'=> 25,
							'sort'=> array(
								'column'=> "utime",
								'direction'=> "desc"
							),
							'filters'=> null
						);
					}

					$this-> send_JSON_Headers(array(
						'tab_gridviews'=> array(
							'grid_state'=> $grid_state
						)
					));
					break;

				case 'tab_partners':
					// Scan All Orgtypes
					if($this-> user-> is_Unrestricted()) {
						$orgtypes = App_Model_Organization::orgtypes_ScanAll();
						foreach($orgtypes as $type_id=> $orgtype_data) {
							if(!empty($orgtype_data['disallowcreate']))
								unset($orgtypes[$type_id]);
							else
								$orgtypes[$type_id] = array(
									'id'=> $type_id,
									'name'=> $orgtype_data['name'],
									'shortname'=> $orgtype_data['shortname']
								);
						}
					}
					// Hybrid response (HTML+JSON)
					$this-> send_JSON_Headers(array(
						'dataSources'=> array( // => self.config.dataSources
							'orgtypes'=> $orgtypes?: array()
						)
					));
					break;

				// Output Templates sub-module
				case 'tab_templates':
					$this-> texted = $texted = isset(MVC_Core::$ss-> user['preferences']['texted'])? MVC_Core::$ss-> user['preferences']['texted']: "TinyMCE";

					// Persistent grid state (session)
					if(isset(MVC_Core::$ss-> cache['grid_state']['org_templates']))
						$grid_state = MVC_Core::$ss-> cache['grid_state']['org_templates'];
					else {
						// Default grid state
						$grid_state = array(
							'pagenum'=> 0,
							'pagesize'=> 25,
							'sort'=> array(
								'column'=> "rt_name",
								'direction'=> "asc"
							),
							'filters'=> null
						);
					}

					$this-> send_JSON_Headers(array(
						'tab_templates'=> array(
			 				'texted'=> $texted,
							'grid_state'=> $grid_state
			 			)
					));
					break;

				// Source Data for Domains is already loaded with the module
				case 'tab_domains':
					break;

				// The Access Control Levels tab, controlling access to Issue Types, Groups and Privileges
				case 'tab_acls':
					// ACLs strings, used by the template
					MVC_NLS::load_Groups("acls");
					$this-> acls = MVC_NLS::get_Object("acls", true); // Used by the [on|off] buttons
					$this-> superuser = $this-> user-> is_Unrestricted();
					break;

				case 'tab_setup':
                                        $this-> current_org_modify = $org_id;//IWI report cron settings for admin
					$this-> org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];
					break;
			}
			$template_name = $in-> tab;
		}

                
		// This is an ajax request, but with a HTML response, so initialize the template object on demand
		$this-> init_Template_System();
		echo $this->_drytpl-> render_File("organization". DS. "manager_tabs". DS. $template_name. ".html", $this->_out);
	}

	/**
	 * Reads the groups of a certain type which are connected to the current organization with the number of records (elements) in them.
	 */
	public function action_Get_Groups(MVC_Request $rq)
	{
		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		// read the groups for the organization
		$org_grp_r = $this-> org-> get_Groups($org_id, $rq-> type, true, (bool)$rq-> receive_only);
		if($org_grp_r) {
			$group_list = array();
			// Reading results and post-processing
			foreach($org_grp_r as $grp) {
				$grp['id'] = (int)$grp['id'];
				$grp['f_create']  = (int)$grp['f_create'];
				$grp['f_receive']  = (int)$grp['f_receive'];
				$grp['count_el']  = (int)$grp['count_el'];
				unset($grp['xdata']); // Not used here
				$group_list[] = $grp;
			}

			$this-> send_JSON($group_list); // sent the group list to the DropDownList (or empty array if none were found)
		} else
			$this-> send_JSON(array()); // not found, empty list
	}

	/**
	 * Reads the groups of a certain type that are not yet connected to the current organization, for displaying in the "attach group(s)" DropDownList
	 */
	public function action_Get_Available_Groups(MVC_Request $in)
	{
		if(!isset($in-> type))
			$this-> send_JSON(array()); // If type is missing, this can't work...

		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		// First get list of groups the organization is already connected to:
		$org_grp_r = $this-> org-> get_Groups($org_id, $in-> type);
		$org_grp_id_list = array();
		if($org_grp_r && count($org_grp_r)>0)
			foreach($org_grp_r as $grp)
				$org_grp_id_list[] = (int)$grp['id'];

		// Read the rest of the groups not already connected
		$this-> factory_Model("Group", null, $in-> type);
		$group_list = array();

		// read all groups of a certain type
		$group_list = $this-> group-> get_List(array(), function(QueryFactory &$q) use($org_grp_id_list) {
			if(!empty($org_grp_id_list))
				$q-> where("`grp`.id NOT IN(?)", $org_grp_id_list);
		}, null, null, 0); // No limits, all groups must be shown here as there is no search

		if(!$group_list)
			$this-> send_JSON(array()); // Nothing found, or some error

		$this-> send_JSON($group_list); // sent the group list to the DropDownList
	}

	/**
	 * Attaches one or more groups to an organization - Admin function
	 */
	public function action_Attach_Groups(MVC_Request $post)
	{
		// Admin-only function
		if(!$this-> user-> is_Unrestricted())
			return $this-> send_Fail_JSON("Access denied.");

		$groups = $post-> grp;

 		if(empty($groups['selection']))
 			return $this-> send_Fail_JSON("No groups have been choosen for connecting");

 		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];
 		$count_attached = $this-> org-> attach_Groups2Org($org_id, $groups['selection'], $groups['comment']);

 		if(!$count_attached)
 			return $this-> send_Fail_JSON("There was an error while connecting one or more groups.");

 		$this-> send_JSON(array('success'=> true, 'response'=> sprintf("%d group(s) have been connected", $count_attached)));
	}

	/**
	 * Detaches one or more data groups from an organization - Only available for the admin
	 */
	public function action_Detach_Groups(MVC_Request $post)
	{
		// Admin-only function
		if(!$this-> user-> is_Unrestricted())
			return header("HTTP/1.0 403 Access denied");

		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		$row_ids = explode(',', $post-> row_ids);

		$r = $this-> org-> detach_Groups_from_Org($org_id, $row_ids);
		if(!$r)
			return header("HTTP/1.0 500 Unable to disconnect the group(s) from the organization");

		$this-> send_JSON(array('success'=> true, 'response'=> sprintf("%d groups have been disconnected from the organization", count($row_ids))));
	}

	/**
	 * Reads the list of partners for the grid in Partners tab
	 *
	 * @param MVC_Request $in
	 */
	public function action_Get_Partners(MVC_Request $in)
	{
		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		$partners = $this-> org-> partnership_Get_List($org_id);
		if(!$partners)
			return $this-> send_JSON(array()); // empty list or error - Nothing to display

		// Nothing to post-process here, return as is
		$this-> send_JSON($partners);
	}

	/**
	 * Reads the detailed information about a partner and outputs as HTML, to be displayed in the partner info window
	 * @param MVC_Request $in
	 */
	public function action_Get_Partnership_Details(MVC_Request $in)
	{
		if(isset($in-> org_id))
			$org_id = (int)$in-> org_id;
		else
			$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		$partnership = $this-> org-> partnership_Read($org_id, $in-> partner_id);
//		dd($partnership);

		echo "[ --- details template under construction --- ]";
	}

	/**
	 * Reads the list of organizations from the system, excluding the ones already connected
	 * @param MVC_Request $in
	 */
	public function action_Get_Unconnected_Orgs(MVC_Request $in)
	{
		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		$r = $this-> org-> get_List(array(), function(QueryFactory &$q) use($in) {
			if($in-> type_id)
				$q-> where("`org`.type_id = ?", $in-> type_id);
		}, null, null, 0);

		if(!$r)
			$this-> send_JSON(array());

		// Create indexed array, which can be cleaned
		$orgs = array();
		foreach($r as $org)
			if($org['id'] != $org_id)
				$orgs[$org['id']] = array(
					'id'=> (int)$org['id'],
					'name'=> $org['name']
				);

		// Read quick list of already connected orgs
		$partners = $this-> org-> partnership_Get_List($org_id, true);
		if($partners)
			foreach($partners as $par) {
				if(isset($orgs[$par['partner_id']]))
					unset($orgs[$par['partner_id']]);
				}

		$this-> send_JSON(array_values($orgs)); // sent the orgnization list to the DropDownList (or empty array if none were found)
	}

	/**
	 * Create partnership links
	 */
	public function action_Link_Partners()
	{
		// Admin-only function
		if(!isset(MVC_Core::$ss-> user['is_admin']))
			return $this-> send_Fail_JSON("Access denied.");

		$post = new MVC_Request('post');
		if(empty($post-> id_list))
			return $this-> send_Fail_JSON("No organizations have been selected");

		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];
		$r = $this-> org-> partnership_Multi_Link($org_id, $post-> type, $post-> id_list, $post-> comment);

		if(!$r)
			return $this-> send_Fail_JSON("Unable to link one or more partners to this organization.");

		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Remove a partnership link
	 */
	public function action_Unlink_Partner()
	{
		// Admin-only function
		if(!isset(MVC_Core::$ss-> user['is_admin']))
			return $this-> send_Fail_JSON("Access denied.");

		$post = new MVC_Request('post');
		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		$r = $this-> org-> partnership_Delete($org_id, $post-> id);
		if($r) {
			$this-> evan-> add("The relation between the two organizations has been removed", 'remove', array(
				'permanent'=> true,
				'timeout'=> time()+ 30
			));
			$this-> send_JSON(array('success'=> true));
		} else
			$this-> send_Fail_JSON("Unable to unlink the two organizations");
	}

	/**
	 * Reads grid views related to an organization (for managing purposes)
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Get_Gridviews(MVC_Request $rq)
	{
		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: ($this-> user-> is_Unrestricted()? $rq-> org_id: MVC_Core::$ss-> user['org_id']);

		// When organization id is known, read the very basic organization info to get it's type, needed for global filtering
		$org = $this-> org-> read($org_id, array('orgtype'));
		if(!$org)
			return header("HTTP/1.0 404 WTF? Organization not found?");

		$this-> gridview = $this-> factory_Model('GridView');

		// Paging system
		$count_rows = $rq-> pagesize;
		$start_row = $rq-> pagenum * $rq-> pagesize;

		// Requested sort order?
		if($rq-> sortdatafield && $rq-> sortorder) {
			if($rq-> sortdatafield == 'rt_name')
				$ss_sort = "`ist`.name ";
			else
				$ss_sort = "`gvw`.{$rq-> sortdatafield} ";

			$ss_sort.= $rq-> sortorder;
		} else
			$ss_sort = "`gvw`.utime DESC";  // default sorting by date, descending (most recent entries on top)

		list($ss_filters, $filter_callback) = self::helper_Grid_Filters($rq);

		// When Visibility filter is on and only one is selected (user or dep), it's possible to filter by Linked to (as the data is joined)
		if(isset($ss_filters['visibility']) && count($ss_filters['visibility'])==1 && $ss_filters['visibility'][0]['v'] != 'org')
			$link_filter_on = $ss_filters['visibility'][0]['v'];
		else
			$link_filter_on = null;

		$views = $this-> gridview-> get_List($org_id,
			/**
			 * Filtering function
			 * @param QueryFactory $q
			 * @uses:
			 * - $rq = Request Model
			 * - $ss_filters, $filter_callback = Necessary for grid filters
			 */
			function(QueryFactory &$q, $params) use ($rq, $ss_filters, $filter_callback)
			{
				// Grid filters
				foreach($ss_filters as $fkey=> $filter_set) {
					// Which table and database field is used for the filter column ?
					if($fkey == 'rt_name') {
						$dbfield = "`ist`.name";
					} elseif($fkey == 'link_name') {
						if(!empty($params['visi_link']))
							$dbfield = "`link`.name";
						else
							continue; // Skip the filter if not on...
					} else
						$dbfield = "`gvw`.{$fkey}";

					$filter_callback($q, $filter_set, $dbfield);
				}

				// When user is non-admin and there is no rt_name filter, it implements a default rt_key filter based on user's ACLs
				if($rq-> rt_def_filter && (!isset($ss_filters['rt_name']) || $ss_filters['rt_name'][0]['c'] == 'NOT_IN_SET'))
					$q-> where("`gvw`.rt_key IN(?)", explode(',', $rq-> rt_def_filter));

				if(empty($params['is_unrestricted'])) {
					$q-> where("`gvw`.visibility != 'admin'"); // These should NOT be seen by non-admins !
				}
			},
			array('visi_link'=> $link_filter_on, 'is_unrestricted'=> $this-> user-> is_Unrestricted()), $ss_sort, $rq-> pagesize, $rq-> pagenum * $rq-> pagesize, true  // true = CALC FOUND ROWS!
		);

		if(!$views)
			return $this-> send_JSON(array(
				"found_rows"=> 0,
				"data"=> array()
			));

		// Gather data, post-process and return
		$rows = array();
		foreach($views as $row) {
			$row['id'] = (int)$row['id'];
			if($row['width'] == 0)
				$row['width'] = 1320; // Automatic default width

			if(empty($link_filter_on)) {
				// Terribly inefficient way of reading users/deps, but this will do for now - Read user or department name
				if($row['visibility'] == 'user') {
					$q2=QueryFactory::select()
					-> table(MVC_Core::$db-> table('usr'), 'name,email')
					-> where("`usr`.id = ?", $row['link_id'])
					-> limit(1);

					$user = MVC_Core::$db-> query($q2)-> current();
					if(!empty($user)) {
						$row['link_name'] = $user['name'];
						$row['link_xdata'] = $user['email'];
					}
					unset($q2, $user);
				}

				if($row['visibility'] == 'dep') {
					$q2=QueryFactory::select()
					-> table(MVC_Core::$db-> table('dep'), 'name')
					-> where("`dep`.id = ?", $row['link_id'])
					-> limit(1);

					$dep = MVC_Core::$db-> query($q2)-> current();
					if(!empty($dep)) {
						$row['link_name'] = $dep['name'];
					}
					unset($q2, $dep);
				}
			}

			$rows[] = $row;
		}

		// Send the information to the grid
		$this-> send_JSON(array(
			"found_rows"=> $this-> gridview-> found_rows,
			"data"=> $rows
		));
	}

	/**
	 * Updates configuration for an existing grid view (change name, visibility and alter screen width - for special settings)
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Update_Gridview(MVC_Request $rq)
	{
		$this-> gridview = $this-> factory_Model('GridView');

		$r = $this-> gridview-> update($rq-> id, $_POST);
		if(!$r)
			return header("HTTP/1.0 500 Unable to update the view.");

		$this-> send_JSON(array('success'=> true, 'response'=> sprintf("View %s updated with new parameters", $rq-> name)));
	}

	/**
	 * Clones an existing grid view by making an identical copy (except for the name, which can be configured)
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Clone_Gridview(MVC_Request $rq)
	{
		// Attempt to read the original view
		$this-> gridview = $this-> factory_Model('GridView');

		$orig = $this-> gridview-> read($rq-> orig_id);
		if(empty($orig))
			return header("HTTP/1.0 404 Original view not found. It might have been deleted");

		$orig['source'] = 'copy';
		unset($orig['utime']); // Allow it to be set by the database on insert
		// Changes...
		if(!empty($rq-> clone_name))
			$orig['name'] = trim($rq-> clone_name);
		if(!empty($rq-> target_id)) {
			// When copying to another organization, visibility must be reset to "admin" and link_id to null - After copying the view can be edited in the target organization
			$orig['visibility'] = 'admin';
			unset($orig['link_id']);
		}

		$view_id = $this-> gridview-> create($rq-> target_id?: $orig['org_id'], $orig);
		if(empty($view_id))
			return header("HTTP/1.0 500 Unable to store the view in the database");

		$this-> send_JSON(array('success'=> true, 'response'=> sprintf("The view <b>%s</b> has been created as a copy".(!empty($rq-> target_id)? " for another organization":""), $rq-> clone_name?: $orig['name'])));
	}

	/**
	 * Deletes an existing grid view after verifying that it actually exists
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Delete_Gridview(MVC_Request $rq)
	{
		$this-> gridview = $this-> factory_Model('GridView');

		$view = $this-> gridview-> read($rq-> id);
		if(empty($view))
			return header("HTTP/1.0 404 View not found. It might have been deleted already");

		// Delete it...
		$r = $this-> gridview-> delete($rq-> id);
		if(!$r)
			return header("HTTP/1.0 500 An error occured while deleting the view");

		// Return and reload the grid so the change is applied
		$this-> send_JSON(array('success'=> true, 'response'=> sprintf("The view <b>%s</b> has been removed !", $view['name'])));
	}

	/**
	 * Read the list of users in the organization
	 * -- Used for choosing org. manager or dep. chief, adding users to deps, or general listing of the organization's users
	 */
	public function action_Get_Users(MVC_Request $in)
	{
		// Read users from any org or just own (this function is also called from other modules). Doesn't change current perspective when asked to read from a different org!
		if($this-> user-> is_Unrestricted())
			$org_id = isset($in-> id)?
				$in-> id:
				(isset(MVC_Core::$ss-> perspective)?
					MVC_Core::$ss-> perspective:
					MVC_Core::$ss-> user['org_id']
				);
		else
			$org_id = MVC_Core::$ss-> user['org_id'];

		$wtr = $in-> quickmode? array(): array('user', 'organization', 'department');

		$edit_access = $this-> user-> test_ACL('privileges', 'org_user_manager');
		// just id and name ( for various lists throughout the application )
		$users = $this-> user-> get_List($wtr, function(QueryFactory $q) use($org_id, $edit_access) {
			$q-> where('`usr`.org_id = ?', $org_id);
//			if(!$edit_access)
				$q-> where('`usr`.status!="disabled" AND `usr`.status!="invisible"');
		}, null, "`name` ASC"); // TODO: Support paging for the enhanced users grid - , $count_rows = 100, $start_row = 0

		if(count($users)>0) {
			if(!$in-> quickmode)
				// in full mode, compress extra information as json (organization, departement, user2dep, acls), as jqxDataAdapter doesn't support complex types
				foreach($users as &$user) {
					$user['count'] = json_encode($user['count']);
					if(isset($user['acls']))
						$user['acls'] = json_encode($user['acls']);
					if(!empty($user['department']['name']))
						$user['group'] = $user['department']['name'];
					unset($user['department'], $user['user2dep'], $user['org_id']);
				}

			$this-> send_JSON($users);
		} else
			$this-> send_JSON(array()); // empty list
	}

	/**
	 * Reads the list of placeholders available for the logged in user and the specified issue type
	 * -- Placeholders are replaced when using canned messages with actual values from the issue, user, organization ( and others )
	 * -- Some placeholders are system-wide (and don't change), others depend on the organization, others are issue-type specific
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Get_Canned_Placeholders(MVC_Request $rq)
	{
		$this-> factory_Model("issue");

		// Is this an issue-related canned message ? - Read the IST Definition to grab the custom fields
		if($rq-> rt_id) {
			$rt_id = $this-> rt_Scan_Override($rq-> rt_id);
			$ist = $this-> issue-> ist_Read($rt_id, false, false, true);
			if(!$ist)
				return $this-> send_JSON(array()); // Error, wrong issue type ?
		}

		// Read the System Placeholders data
		$placeholders_struct = MVC_Core::$spyc-> loadFile(ROOT .DS. 'app'. DS. 'data'. DS. 'canned_placeholders.yaml');

		$placeholders = array();
		foreach($placeholders_struct as $group) {
			// Ignore the "Issue" and "Cache-Field" groups when this is not an issue-related canned message
			if(!$rq-> rt_id && ($group['name']=='Issue' || $group['name']=='Cache-Field'))
				continue;

			// If XF_Cache is not defined for the issue type, ignore the Cache-Field group
			if($group['name']=='Cache-Field' && !isset($ist['settings']['xf_cache']))
				continue;

			foreach($group['list'] as $phs) {

				// Replace the Cache-Field key by it's actual name
 				if($group['name'] == 'Cache-Field') {
					if(!isset($ist['settings']['xf_cache'][$phs['name']]))
						continue;
					else
 						$phs['name'] = $ist['settings']['xf_cache'][$phs['name']]['name'];
 				}

				$placeholders[] = array(
					'n'=> $phs['name'],
					'group'=> $group['name'],
					'ph'=> $phs['ph']
				);
			}
		}

		if($rq-> rt_id) {
			// When reading placeholders for an issue type, also place all x-fields (in serialized format) in the available placeholders
			$restricted_field_plugins = array();
			foreach($ist['xfield_def'] as $key => $field) {
				$group = (!empty($field['s'])? ucwords(str_replace('_', ' ', $field['s'])): "(ungrouped)");

				// Default serializable field types?
				if(in_array($field['t'], array("text", "number", "date", "enum", "set")))
					$placeholders[] = array(
						'n'=> $field['n'],
						'group'=> $group,
						'ph'=> "{issue.xfields.".$key."}"
					);
				else {
					// None of the default types, use plugin to return list of placeholders.
					if(!isset($restricted_field_plugins[$field['t']]))
						$restricted_field_plugins[$field['t']] = MVC_Core::factory_Plugin("field", $field['t'], true);

					$plugin_placeholders = $restricted_field_plugins[$field['t']]-> get_Serialized_Placeholders('issue.xfields.'.$key, $field['n'], $group);
					if(is_array($plugin_placeholders))
						foreach($plugin_placeholders as $ph)
							$placeholders[] = $ph;
				}
			}
		}

		$this-> send_JSON($placeholders);
	}

	/**
	 * Tests access for departments and users
	 * -- This function is horribly inefficient as it reads all the users in the organization and calculates ACLs for all of them
	 * -- A new way of storing access is needed to avoid the large data read and computation
	 *
	 * @param MVC_Request $rq
	 * @return boolean
	 */
	public function action_Security_Search(MVC_Request $rq)
	{
		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		// List of departments in this organization - Reads all overrides, SLOW. Must make a better way to do this...
		$this-> dep = $this-> factory_Model("Department", "dep");
		$deps = $this-> dep-> get_List($org_id, array('dep_target'));
		$users = $this-> user-> get_list(array('organization','department','acls'), function(QueryFactory $q) use($org_id) {
			$q-> where('`usr`.org_id = ?', $org_id);
			$q-> where('`usr`.status!="disabled"');
		}, null, "`name` ASC");

		$ent_list = array();
		// TODO: Redundancy is redundant...
		switch($rq-> srch_type) {
			case 'rt': // Search for Record Type overrides (access_ids_or :: ist)
				// Deps first
				foreach($deps as $dep) {
					if(!empty($dep['access_ids_or']) && !empty($dep['access_ids_or']['ist'])) {
						// There are RT(ist) overrides. Check if the specified RT(ist) is in the list
						if(in_array($rq-> srch_id, $dep['access_ids_or']['ist']))
							$ent_list[] = array(
								'type'=> "Department",
								'id'=> (int)$dep['id'],
								'name'=> $dep['name']
							);
					}
				}
				// Users second
				foreach($users as $user) {
					if(!empty($user['acls']) && !empty($user['acls']['ist'])) {
						if(in_array($rq-> srch_id, $user['acls']['ist']))
							$ent_list[] = array(
								'type'=> "User",
								'id'=> (int)$user['id'],
								'name'=> $user['name'],
								'dep'=> isset($user['department'])?$user['department']['name']: '---'
							);
					}
				}


				break;
			case 'priv': // Search for Privileges overrides (privileges_or)
				// Deps first
				foreach($deps as $dep) {
					if(!empty($dep['privileges_or'])) {
						// There are privilege overrides. Check if the specified privilege is in the list
						if(in_array($rq-> srch_id, $dep['privileges_or']))
							$ent_list[] = array(
								'type'=> "Department",
								'id'=> (int)$dep['id'],
								'name'=> $dep['name']
							);
					}
				}
				// Users second
				foreach($users as $user) {
					if(!empty($user['acls']) && !empty($user['acls']['privileges'])) {
						if(in_array($rq-> srch_id, $user['acls']['privileges']))
							$ent_list[] = array(
								'type'=> "User",
								'id'=> (int)$user['id'],
								'name'=> $user['name'],
								'dep'=> isset($user['department'])?$user['department']['name']: '---'
							);
					}
				}

				break;
			case 'grp':	// Search for Group access overrides (access_ids_or :: groups)
				// Deps first
				foreach($deps as $dep) {
					if(!empty($dep['access_ids_or']) && !empty($dep['access_ids_or']['groups'])) {
						// There are group overrides. Check if the specified group is in the list
						if(in_array($rq-> srch_id, $dep['access_ids_or']['groups']))
							$ent_list[] = array(
								'type'=> "Department",
								'id'=> (int)$dep['id'],
								'name'=> $dep['name']
							);
					}
				}
				// Users second
				foreach($users as $user) {
					if(!empty($user['acls']) && !empty($user['acls']['groups'])) {
						if(in_array($rq-> srch_id, $user['acls']['groups']))
							$ent_list[] = array(
								'type'=> "User",
								'id'=> (int)$user['id'],
								'name'=> $user['name'],
								'dep'=> isset($user['department'])?$user['department']['name']: '---'
							);
					}
				}
				break;
		}
		return $this-> send_JSON($ent_list);
	}

	/**
	 * @DEPRECATED - Will be replaced with the fully named "Security Search"
	 * Searches for the departments and users which have access to a certain data group (overrides)
	 * - For now it only considers direct override
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Secu_Search(MVC_Request $rq)
	{
		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		$this-> dep = $this-> factory_Model("Department", "dep");
		// List of departments in this organization - Reads all overrides, SLOW. Must make a better way to do this...
		$deps = $this-> dep-> get_List($org_id, array('dep_target'));

		$dep_list = array();
		foreach($deps as $dep) {

			if(!empty($dep['access_ids_or']) && !empty($dep['access_ids_or']['groups'])) {
				// There are group overrides. Check if the specified group is in the list
				if(in_array($rq-> group_id, $dep['access_ids_or']['groups']))
					$dep_list[] = array(
							'id'=> (int)$dep['id'],
							'name'=> $dep['name']
					);
			}
		}

		$this-> group_name = $rq-> group_name;

		if(!empty($dep_list))
			$this-> departments = $dep_list;
		else
			$this-> notfound = true;

		// This is an ajax request, but with a HTML response, so initialize the template object on demand
		$this-> init_Template_System();
		$this-> view("organization". DS. "secu_search.html");
	}

	/**
	 * Updates the comment which is present in the "Group-to-Organization" table
	 */
	public function action_Update_G2O(MVC_Request $post)
	{
		if(!$this-> user-> is_Unrestricted())
			return header("HTTP/1.0 403 Access denied");

		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		$row_ids = explode(',', $post-> row_ids);
		$r = $this-> org-> update_Group2Org($org_id, $row_ids, $post-> grp);

		if(!$r)
			return header("HTTP/1.0 500 Unable to update group to organization relation");

		$this-> send_JSON(array('success'=> true, 'response'=> sprintf("Configuration has been updated for %d data groups.", count($row_ids))));
	}

	/**
	 * Counts how many data records are in the specified group and are accessible by an organization
	 * -- Normally the value should automatically update for every linked record, but the functionality is not there yet.
	 *
	 * @param MVC_Request $post
	 * @return boolean
	 */
	public function action_Count_G2O_Elements(MVC_Request $post)
	{
		if(!$this-> user-> is_Unrestricted())
			return $this-> send_Fail_JSON("Access denied.");

		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		$scanresult = $this-> org-> count_Group2Org_Elements($org_id, $post-> group_id);
		if($scanresult === false)
			return $this-> send_Fail_JSON("There was an error while counting the records.");

		// Update all group counters
		foreach($scanresult['counts'] as $group_id=> $count)
			$this-> org-> update_Group2Org($org_id, $group_id, array(
				'count_el'=> $count
			));

		// Finally update the organization itself, "count_issues"
		$this-> org-> update($org_id, array('count_issues'=> $scanresult['total']));
		$this-> send_JSON(array('success'=> true, 'response'=> "Records counter has been updated!"));
	}

	/**
	 * Update organization level ACLs
	 */
	public function action_Update_ACLs(MVC_Request $post)
	{
		// Check if the logged in user is allowed to modify change the organization's access control lists
		if(!$this-> user-> test_ACL('privileges', 'org_access_manager'))
			return $this-> send_Fail_JSON("Access denied.");

		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		// TODO: This is repeated almost identically at both organization, department and user level - Make an universal function somewhere in a model to only have one copy
		if(isset($post-> acls)) {
			$acls = json_decode($post-> acls, true);

			// a bit of post-processing on the submitted data, as JS is sending strings, and we need php data here
			if(!empty($acls['access_ids']['ist']))
				$acls['access_ids']['ist'] = explode(',', $acls['access_ids']['ist']);
			else
				$acls['access_ids']['ist'] = null; // No record types !
			if(!empty($acls['access_ids']['groups']))
				$acls['access_ids']['groups'] = explode(',', $acls['access_ids']['groups']);
			else
				$acls['access_ids']['groups'] = null; // No access !

			if($this-> org-> update($org_id, $acls)) {
				$this-> evan-> add("Organization-wide Access Control Lists have been updated. They will take effect at next user login.", 'ok');
				$this-> send_JSON(array('success'=> true));
			} else
				$this-> send_Fail_JSON("Error while updating the organization's access control lists.");
		} else {
			$this-> send_Fail_JSON("Interface error: No ACL data to update.");
			return;
		}
	}

	/**
	 * Reads a list of users which are members of an organization's department, to be returned to a jqxListBox
	 */
	public function action_Get_Department_Members(MVC_Request $in)
	{
		$this-> factory_Model("Department", "dep");

		$users = $this-> dep-> get_Dep_User_List($in-> id);
		if($users)
			$this-> send_JSON($users);
		else
			$this-> send_JSON(array()); // empty list on error
	}

	/**
	 * Updates the user to department relation (usually, by changing comment, but in the future there might be more data)
	 * -- Like set visible/invisible, disabled/enabled
	 */
	public function action_Update_U2D(MVC_Request $post)
	{
		$this-> factory_Model("Department", "dep");

		$r = $this-> dep-> update_User_Dep_Relation($post-> dep_id, $post-> user_id, $post-> rel_data);
		if($r)
			$this-> send_JSON(array('success'=> true));
		else
			$this-> send_Fail_JSON("There was an error in updating the department member.");
	}

	/**
	 * Get the list of canned messages which are visible for an user, as raw data (no processing of the template)
	 * -- Used in the organization module to manage and edit the canned message
	 */
	public function action_Get_Canned_Messages()
	{
		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];
		// When organization id is known, read the very basic organization info to get it's type, needed for global filtering
		$org = $this-> org-> read($org_id, array('orgtype'));
		if(!$org)
			return $this-> send_JSON(array()); // Error, organization not found

		// What to put in the "Scope" column
		$visibility_translate = array(
			'global'=> "All of: {$org['orgtype']['name']}",
			'dep'=> "Active department",
			'org'=> $org['name'],
			'user'=> "Personal"
		);

		// Administrator/Supervisor can choose from all issue types ; Normal user only the ones that he has access to
		if(isset(MVC_Core::$ss-> user['is_admin']) || isset(MVC_Core::$ss-> user['is_supervisor']))
			$ist_access = null;
		else
			$ist_access = MVC_Core::$ss-> user['acls']['ist'];

		// Read basic issue type information
		$this-> factory_Model("Issue");
		$ist_list_r = $this-> issue-> ist_Get_List($ist_access);
		$ist_list = array();
		foreach($ist_list_r as $row) {
			$row['id'] = (int)$row['id'];
			$ist_list[$row['id']] = array(
				'name'=> $row['name'],
				'key'=> $row['key']
			);
		}

		$this-> factory_Model("Canned");
		$canned = $this-> canned-> get_List($org_id, $org['type_id'], MVC_Core::$ss-> user['id'], $ist_access, null, false, true); // IST list is link_id's
		if($canned) {
			$all = array();
			foreach($canned as $message) {
				// A little post-processing
				$message['id'] = (int)$message['id'];
				$message['link_id'] = (int)$message['link_id'];
				// Canned messages of certain classes are linked with issues, setup the "link" information
				if(in_array($message['usage_class'], array('issreply', 'issmessage', 'issletter'))) {
					$message['link_name'] = $ist_list[$message['link_id']]['name'];
					$message['link_key'] = $ist_list[$message['link_id']]['key'];
				} else
					$message['link_name'] = "(Not Linked)";

				if(!is_null($message['org_id']))
					$message['org_id'] = (int)$message['org_id'];
				$message['visibility_text'] = $visibility_translate[$message['visibility']];
				$all[] = $message;
			}
			$this-> send_JSON($all);
 		} else
 			$this-> send_JSON(array());
	}

	/**
	 * Reads the list of organization-wide output templates (used for email, tasks, printing and others.)
	 * - Supports extra filters and functions for using those templates in various circumstances
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Get_Templates(MVC_Request $rq)
	{
		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: ($this-> user-> is_Unrestricted()? $rq-> org_id: MVC_Core::$ss-> user['org_id']);

		// When organization id is known, read the very basic organization info to get it's type, needed for global filtering
		$org = $this-> org-> read($org_id, array('orgtype'));
		if(!$org)
			return header("HTTP/1.0 404 Organization not found? Possible configuration error");

		// Read all templates for the issuetypes that are accessible by the user
		$m_tpl = new App_Model_Template();
		$wtr = array('template');
		if(empty($rq-> rt_id))
			$wtr[] = 'recordtype';
// 		if(!$rq-> process)
		$wtr[] = 'editor';
// 		if($rq-> process)
// 			$wtr[] = 'body';

		$r = $m_tpl-> get_List($org_id, $wtr, function(QueryFactory &$q) use ($rq) {
			if($rq-> role)
				$q-> where("`tpl`.role = ?", $rq-> role);

			if($rq-> rt_id)
				// Load only templates for a specific type
				$q-> where("`tpl`.rt_id = ?", $rq-> rt_id);
			else
				// By default load all record types that the user can access, as well as those without a type (for example - Document View templates)
				// TODO: Perhaps different user role needed for viewing non-record templates ?
				$q-> where("`tpl`.rt_id IN(?) OR `tpl`.rt_id IS NULL", array_keys(MVC_Core::$cache-> record_types_index));
		}, null, '`tpl`.description ASC');

		$templates = array();

		// Certain templete types have a system-wide default (per record type) - Only when using templates, not when managing.
		if($rq-> rt_id && $rq-> include_default == 'true') {
			// Depending on role, the template is accessed either as a file, or from an external location.
			switch($rq-> role) {
				case 'print':
					$templates[] = array(
						'id'=> 0,
						'name'=> "Default template",
						'description'=> "Default global printing template"
					);
					break;
				default:
					// No default. If also no custom, return error 404
					if(!$r || count($r)==0)
						return header("HTTP/1.0 404 No templates currently available");
			}

			// When no custom templates are available, simply return the default as the single row
			if(!$r || count($r)==0)
				return $this-> send_JSON($templates);

		} else
			if(!$r || count($r)==0)
				return header("HTTP/1.0 404 No templates currently available");

		// TODO: Read the specified record when in process mode, so the record data is available ( meta + xfields )
// 		if($rq-> process) {

// 		}

		foreach($r as $row) {
			// Post-process
			$row['id'] = (int)$row['id'];

			if(empty($row['rt_id']))
				unset($row['rt']); // Template which is not related to a record typoe
			else
				if(!$rq-> rt_id)
					$row['rt']['id'] = (int)$row['rt_id'];

// 			if($rq-> process)
			$row['editor']['id'] = (int)$row['editor_id'];

			if(empty($row['description']))
				$row['description'] = $row['title'];

			unset($row['rt_id'], $row['editor_id'], $row['org_id']); // Moved or Already known...

			// TODO: In processing mode, send "Title" and "Body" through the template system, to include record's data
// 			if($rq-> process) {

// 			}
			// ... for now, just return them raw for testing mode

			$templates[] = $row;
		}

		$this-> send_JSON($templates);
	}

	/**
	 * Loads a template's body, which is not normally transmitted to the grid ( due to possibly very large size, since these are HTML and other text content )
	 * @param MVC_Request $in
	 */
	public function action_Load_Template_Body(MVC_Request $in)
	{
		if(!$in-> id)
			return header("HTTP/1.0 400 Template ID not present in the request");

		$m_tpl = new App_Model_Template();
		echo $m_tpl-> read($in-> id, true);
	}

	/**
	 * Creates or updates an organization template
	 *
	 * @param MVC_Request $post
	 * @return boolean
	 */
	public function action_Save_Template(MVC_Request $post)
	{
		$m_tpl = new App_Model_Template();
		if($post-> id) {
			// Updating existing template
			$r = $m_tpl-> update($post-> id, MVC_Core::$ss-> user['id'], $post-> tpl, $post-> xdata);
			if(!$r) {
				$err = MVC_Core::$debug-> get_Last_Dump();
				return header(sprintf("HTTP/1.0 500 Update failed: %s", (string)$err['data']));
			}

			$response = "Template successfully updated";

		} else {
			// For certain roles, a record type id is mandatory - For the rest (currently just "view", it is not)
			if(in_array($post-> tpl['role'], array('email', 'print', 'task', 'arem', 'export')) && empty($post-> tpl['rt_id']))
				return header("HTTP/1.0 400 Record type missing from the request");

			// Creating new template
			$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];
			$r = $m_tpl-> create($org_id, MVC_Core::$ss-> user['id'], $post-> tpl, $post-> xdata);

			if(!$r) {
				$err = MVC_Core::$debug-> get_Last_Dump();
				return header(sprintf("HTTP/1.0 500 Unable to create the template: %s", (string)$err['data']));
			}

			$response = sprintf("Template with description %s created", $post-> tpl['description']);
		}

		$this-> send_JSON(array('response'=> $response));
	}

	/**
	 * Delete an existing template
	 * @param MVC_Request $post
	 * @return boolean
	 */
	public function action_Delete_Template(MVC_Request $post)
	{
		if(!$post-> id)
			return header("HTTP/1.0 400 Template ID missing from the request");

		$m_tpl = new App_Model_Template();
		$template = $m_tpl-> read($post-> id);

		if(!$template)
			return header("HTTP/1.0 404 Template not found. It might have already been deleted. Refresh the grid");

		// Check if template Org ID matches the current organization
		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		if($template['org_id'] != $org_id)
			return header("HTTP/1.0 403 This template doesn't belong to the loaded organization. Cannot delete");

		$r = $m_tpl-> delete($post-> id);

		if(!$r)
			return header("HTTP/1.0 500 Unable to delete the template. Unknown error");

		$this-> send_JSON(array('success'=> true, 'response'=> "Template with ID {$post-> id} was deleted."));
	}

	/**
	 * Simple text-mode ajax function which returns a canned message description text (not normally loaded in the grid)
	 */
	public function action_Read_Canned_Message_Template(MVC_Request $in)
	{
		$this-> factory_Model("Canned");
		$template = $this-> canned-> read_Template($in-> id);
		if($template)
			echo $template;
	}

	/**
	 * Saves the canned message (create or edit)
	 */
	public function action_Save_Canned_Message()
	{
		$this-> factory_Model("Canned");
		// TODO: Read the canned message to see if the logged in user can actually modify it (security check)
		// privilege: org_canned_manager

		$post = new MVC_Request('post');
		if($post-> id > 0) {
			// edit
			$r = $this-> canned-> update($post-> id, $post-> can);
			if($r === true) {
				$this-> evan-> add("Canned message with id {$post-> id} has been updated.", "ok", array(
					'permanent'=> true,
					'timeout'=> time()+ 30
				));
				$this-> send_JSON(array(
					'success'=> true,
					'name'=> $post-> title
				));
			} elseif(is_array($r)) {
				switch($r[0]) {
					case '-1':
						return header("HTTP/1.0 400 Template render error - {$r[1]}"); break;
					case '-2':
						return header("HTTP/1.0 400 Subject/Title render error - {$r[1]}"); break;
				}
			} else
				return header("HTTP/1.0 500 Attempting to update canned message {$post-> can['description']} failed");
		} else {
			// create
			$can = $post-> can;
			$can['org_id'] = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];
			$can['editor_id'] = MVC_Core::$ss-> user['id'];

			switch($can['visibility']) {
				// Global ones need [orgtype_id] but not the [org_id]
				case 'global':
					unset($can['org_id']);
					break;
					// Organization-wide visibility doesn't need [orgtype_id]
				case 'org':
					unset($can['orgtype_id']);
					break;
			}

			if(empty($can['link_id']))
				unset($can['link_id']);

			$r = $this-> canned-> create($can);

			if(is_int($r) && $r>0) {
				$this-> evan-> add("The canned message {$can['description']} was created", "add", array(
					'permanent'=> true,
					'timeout'=> time()+ 30
				));
				$this-> send_JSON(array('success'=> true));
			} elseif(is_array($r)) {
				switch($r[0]) {
					case '-1':
						return header("HTTP/1.0 400 Template render error - {$r[1]}"); break;
					case '-2':
						return header("HTTP/1.0 400 Subject/Title render error - {$r[1]}"); break;
				}
			} else
				return header("HTTP/1.0 500 Unable to save the new canned message.");
		}
	}

	/**
	 * Removes a canned messages from the database
	 */
	public function action_Delete_Canned_Message()
	{
		$this-> factory_Model("Canned");
		// TODO: Read the canned message to see if the logged in user can actually delete it (security check)
		// privilege: org_canned_manager

		$post = new MVC_Request('post');
		if($post-> id > 0) {
			$r = $this-> canned-> delete($post-> id);
			if($r) {
				$this-> evan-> add("The canned message with id {$post-> id} has been deleted from the system.", "remove", array(
					'permanent'=> true,
					'timeout'=> time()+ 30
				));
				$this-> send_JSON(array('success'=> true));
			} else
				$this-> send_Fail_JSON("Unknown error while attempting to delete the canned message.");
		} else
			$this-> send_Fail_JSON("Error, no canned message ID has been specified.");
	}

	/**
	 * Reads the domains that are associated with an organization, decodes some data which is present in html_settings and public_settings and sends them to the grid
	 *
	 * @param MVC_Request $in
	 */
	public function action_Get_Domains(MVC_Request $in)
	{
		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		$domains = $this-> org-> domain_Get_List($org_id, $in-> quickmode);
		if($domains) {
			// prepare the data for the grid (
			$all = array();
			foreach($domains as $domain) {
				if($in-> quickmode)
					// QuickMode only returns the domain name itself, and no other information. Used in dropdowns or other selectors
					$dom = $domain;
				else {
					// Full Mode is ment for the domain management grid
					$dom = array(
						'domain'=> $domain['domain'],
						'title'=> isset($domain['html_settings']['title'])? $domain['html_settings']['title']: "[".MVC_Core::$settings['html']['title']."]",
						'css_theme'=> isset($domain['html_settings']['css_theme'])? $domain['html_settings']['css_theme']: "[".MVC_Core::$settings['html']['css_theme']."]",
						'logo'=> isset($domain['html_settings']['logo'])? $domain['html_settings']['logo']: MVC_Core::$settings['html']['logo'],
						'index'=> isset($domain['html_settings']['index_route'])? $domain['html_settings']['index_route']: "[".MVC_Core::$settings['html']['index_route']."]"
					);
					// Construct the short view of the "Public Settings" column
// 					$dom['public'] = "[TODO]";
				}
				$all[] = $dom;
			}
			$this-> send_JSON($all);
		} else
			$this-> send_JSON(array()); // no data
	}

	/**
	 * Reads the information for a domain and sends it to the interface
	 *
	 * @param MVC_Request $in
	 */
	public function action_Get_Domain_Data(MVC_Request $in)
	{
		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];
		$in = new MVC_Request();

		if($in-> domain) {
			$domain = $this-> org-> domain_Read($in-> domain);
			if($domain) {
				$this-> send_JSON(array(
					'success'=> true,
					'domain'=> $domain
				));
			} else
				$this-> send_Fail_JSON("Domain name {$in-> domain} not found in the database");
		} else {
			$this-> send_Fail_JSON("No domain name specified !");
		}
	}

	/**
	 * Creates or Modifies an organization domain
	 */
	public function action_Save_Domain()
	{
		// Check if the logged in user is allowed to modify domains
		if(!$this-> user-> test_ACL('privileges', 'org_domains_manager')) {
			$this-> send_Fail_JSON("Access denied.");
			return;
		}

		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];
		$post = new MVC_Request('post');

		// TODO: pre-process some values (to avoid storing wrong data)
		$domain_data = $post-> dom;

		// if nothing typed in the logo or index_route fields, null means: default, as isset(logo) will return false
		if(empty($domain_data['html_settings']['logo']))
			$domain_data['html_settings']['logo'] = null;
		if(empty($domain_data['html_settings']['index_route']))
			$domain_data['html_settings']['index_route'] = null;

		// footer (which is the cargohub logo) is transmitted as a string, convert to real boolean
		$domain_data['html_settings']['footer'] = $domain_data['html_settings']['footer'] == "true";

		// public settings are sent as a json, decode it here as the update/create methods need it decoded (for now)
		$domain_data['public_settings'] = @json_decode($domain_data['public_settings'], true);

		if($post-> domain_update) {
			// This is an update, the value domain_update indicates which domain to update
			if($this-> org-> domain_Update($post-> domain_update, $domain_data)) {
				$this-> evan-> add("The domain \"{$post-> domain_update}\" has been updated", "ok", array(
					'permanent'=> true,
					'timeout'=> time()+ 30
				));
				$this-> send_JSON(array('success'=> true));
			} else
				$this-> send_Fail_JSON("There was an error in updating the domain.");
		} else {
			// This is a create, all the data is in $domain_data
			if($this-> org-> domain_Create($org_id, $domain_data)) {
				$this-> evan-> add("The domain \"{$post-> dom['domain']}\" has been created", "add", array(
					'permanent'=> true,
					'timeout'=> time()+ 30
				));
				$this-> send_JSON(array('success'=> true));
			} else
				$this-> send_Fail_JSON("There was an error in creating the domain (Perhaps duplicate?).");
		}
	}

	/**
	 * Deletes an existing domain
	 */
	public function action_Delete_Domain()
	{
		// Check if the logged in user is allowed to modify domains
		if(!$this-> user-> test_ACL('privileges', 'org_domains_manager')) {
			$this-> send_Fail_JSON("Access denied.");
			return;
		}

		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];
		$post = new MVC_Request('post');

		if($post-> domain) {
			if($this-> org-> domain_Delete($post-> domain, $org_id)) {
				$this-> evan-> add("The domain \"{$post-> domain}\" has been deleted", "remove", array(
					'permanent'=> true,
					'timeout'=> time()+ 30
				));
				$this-> send_JSON(array('success'=> true));
			} else {
				$this-> send_Fail_JSON("Unable to delete domain, possible database error.");
			}
		} else
			$this-> send_Fail_JSON("No domain name specified !");
	}

	/**
	 * Updates primary organization information, coming from the "Setup" tab in the frontend
	 */
	public function action_Update_Organization(MVC_Request $post)
	{
            
		if(!$this-> user-> is_Unrestricted())
			return $this-> send_Fail_JSON("Access denied.");

		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		// check if any data was posted
		if(!$post-> org)
			return $this-> send_Fail_JSON("Interface error: No organization data received.");
                
                
                
                
                
                
//            Add option to set or change logo from organization start
            $org_logo='';
                if (isset($_FILES["org_logo"])) {
                    
                    $temp = explode(".", $_FILES["org_logo"]["name"]);
//                    $newfilename = $post-> org['name'].'_'.$org_id . '_'.round(microtime(true)). '.'  . end($temp);
                    $newfilename = $org_id . '_'.round(microtime(true)). '.'  . end($temp);
                    
                    $target_dir = "img/org_logo/";
                    $target_file = $target_dir . $newfilename;
                    $uploadOk = 1;
                    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                    
                    


                    if ($_FILES["org_logo"]["size"] > 500000) {
                        $logo_upload_data_reason="Sorry, your file is too large.";
                        $logo_upload_data['status']=0;
                        $uploadOk = 0;
                    }
                    
                    

                    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" && $imageFileType != "webp") {
                        $logo_upload_data_reason= "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                        $logo_upload_data['status']=0;
                        $uploadOk = 0;
                    }

                    if ($uploadOk == 0) {
                       $this-> send_JSON(array('success'=> false,'reason'=>$logo_upload_data_reason));
                       exit();
                    } else {
                        if (move_uploaded_file($_FILES["org_logo"]["tmp_name"], $target_file)) {
                            $org_logo='/'.$target_file;
                            if (file_exists($_SERVER['DOCUMENT_ROOT'].$_POST['org_logo_old'])) {
                                unlink($_SERVER['DOCUMENT_ROOT'].$_POST['org_logo_old']);
                            }
//                            echo "The file " . htmlspecialchars(basename($_FILES["org_logo"]["name"])) . " has been uploaded.";
                        } 
//                        else {
//                            echo "Sorry, there was an error uploading your file.";
//                        }
                    }
                }
//                echo json_encode($logo_upload_data);
//                exit();
//                Add option to set or change logo from organization end
            
            
        // Check if the logged in user can actually modify the organization's core data

		$org_update = $post-> org;
//                Add option to set or change logo from organization start
                if($org_logo!=''){
                    $org_update['org_logo']=$org_logo;
                }
//                Add option to set or change logo from organization end

		// input fixes
		$org_update['features']['active'] = $org_update['features']['active'] == 'true';
		$org_update['features']['real_attach'] = $org_update['features']['real_attach'] == 'true';
		if(empty($org_update['manager_id']))
			unset($org_update['manager_id']);
		if(empty($org_update['contact_id']))
			$org_update['contact_id'] = null;
		$org_update['name'] = trim($org_update['name']);

		// update the organization
		if(!$this-> org-> update($org_id, $org_update, true))
			return $this-> send_Fail_JSON("Error while updating the organization.");

		// If primary update succeeded, create or save issue type links
		if(strlen($post-> ist2org)>0)
			$this-> org-> ist_Save_List($org_id, explode(',', $post-> ist2org));

		$this-> evan-> add(sprintf("The organization %s has been updated.", $post-> org['name']), 'ok', array(
			'permanent'=> true,
			'timeout'=> time()+ 30
		));

		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Reads from the contacts associated with a certain organization
	 * -- optionally filtering by contact type
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Get_Contacts(MVC_Request $rq)
	{
		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		$this-> factory_Model("Contact", 'con');
		$contacts = $this-> con-> get_List($org_id, null, $rq-> type, $rq-> quickmode? array(): array("contact", "user", "xdata"));
		if($contacts) {
			if($rq-> unshift) {
				// HACK: Prepend with a fake "No visible primary"
				array_unshift($contacts, array(
					'id'=> 0,
					'email'=> $rq-> unshift
				));
			}

			$this-> send_JSON($contacts);
		} else
			$this-> send_JSON(array());
	}

	/**
	 * Read all profile records for a certain user (all columns except the "data"). Used by the profile manager
	 *
	 * @param MVC_Request $in
	 */
	public function action_Get_Profile_Defaults(MVC_Request $in)
	{
		usleep(250000); // Introduce a small delay in returning the results, there's some kind of race condition going on in the frontend making the grid fail sometimes

		if(!$this-> user-> is_Unrestricted())
			return $this-> send_Fail_JSON("No access to this function. How did you even get here?");

		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		$profiles = $this-> org-> profile_Default_Get_List($org_id, true);
		if(!$profiles) {
			MVC_Core::$debug-> dump("Unable to read organization's {$org_id} default profiles", __METHOD__, null, 'error');
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
			if($rec['ctime'] == '0000-00-00 00:00:00')
				$rec['ctime'] = null;

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
	public function action_Load_Profile_Default_Data(MVC_Request $in)
	{
		if(!$this-> user-> is_Unrestricted())
			return $this-> send_Fail_JSON("No access to this function. How did you even get here?");
		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		$profile = $this-> org-> profile_Default_Read($org_id, $in-> pkey, true);
		if(!$profile) {
			MVC_Core::$debug-> dump("Profile data could not be read", __METHOD__, null, 'error');
			return "Error reading profile data";
		}

		echo MVC_Core::pretty_JSON($profile); // JSON not decoded when reading, so $profile used as a string.
	}

	/**
	 * Updates the profile name/description for one profile record+variant
	 *
	 * @param MVC_Request $post
	 * @return boolean
	 */
	public function action_Update_Profile_Default_Pname(MVC_Request $post)
	{
		if(!$this-> user-> is_Unrestricted())
			return $this-> send_Fail_JSON("No access to this function. How did you even get here?");
		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		$r = $this-> org-> profile_Default_Update_Pname($org_id, $post-> pkey, $post-> pname);

		if($r)
			$this-> send_JSON(array('success'=> true, 'response'=> "Profile default name/description updated successfully !"));
		else
			$this-> send_Fail_JSON("Error while attempting to update this profile record.");
	}

	/**
	 * Resets the specified profile record for several users at once
	 * -- This resets ALL variants of a profile record !
	 *
	 * @param MVC_Request $post
	 * @return boolean
	 */
	public function action_Profile_Reset_Multiusers(MVC_Request $post)
	{
		if(!$this-> user-> is_Unrestricted())
			return $this-> send_Fail_JSON("No access to this function. How did you even get here?");

		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['upr'])
		-> where("`user_id` IN(?)", explode(',', $post-> users))
		-> where("`pkey` = ?", $post-> pkey);

		MVC_Core::$db-> query($q);

		$affected = MVC_Core::$db-> affected_rows;
		$this-> send_JSON(array('success'=> true, 'response'=> "Profile record {$post-> pkey} was reset for {$affected} users !"));
	}

	/**
	 * Reads basic data for all the issuetypes which are available for an organization ( global scope ) but also those defined for just the specified organization.
	 * - Ignores overrides and "admin" only (work in progress) issue types.
	 * - Also reads if the issue type has actually been turned on for the organization.
	 * Returns as json for turning issuetypes on and off for a certain organization.
	 */
	public function action_Get_Issuetypes()
	{
		if(!$this-> user-> is_Unrestricted())
			return header("HTTP/1.0 403 No access to this function.");

		$org_id = isset(MVC_Core::$ss-> perspective)? MVC_Core::$ss-> perspective: MVC_Core::$ss-> user['org_id'];

		$r = $this-> org-> ist_Get_List($org_id);
		$all = array();
		if($r && count($r)>0) {
			foreach($r as $ist_link)
				$all[] = array(
					'id'=> (int)$ist_link['id'],
					'key'=> $ist_link['key'],
					'name'=> $ist_link['name'],
					'enabled'=> (bool)$ist_link['enabled'],
					'send'=> (bool)$ist_link['send'],
					'receive'=> (bool)$ist_link['receive']
				);
		}

 		$this-> send_JSON($all);
	}

	/**
	 * Allows switching to another editor
	 * -- This should probably not be here, but in a general "preferences" area? Since the editors are reused between several modules
	 */
	public function action_TPL_Editor_Options(MVC_Request $post)
	{
		// Read existing editor
		$preferences = MVC_Core::$ss-> user['preferences'];
		$texted = isset($preferences['texted'])? $preferences['texted']: "TinyMCE";

		if($post-> texted == $texted)
			return header("HTTP/1.0 409 This editor is the current option, please choose another or press Cancel");

		$preferences['texted'] = $post-> texted;

		switch($post-> texted) {
			case 'CodeMirror':
				$texted_fullname = "CodeMirror (Programer Editor)";
				break;
			case 'TinyMCE':
				$texted_fullname = "TinyMCE (Visual Editor)";
				break;
			default:
				return header("HTTP/1.0 400 Invalid option or request error");
		}
		$r = $this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "preferences", $preferences);
		if(!$r)
			return header("HTTP/1.0 500 Error while saving preferences");

		MVC_Core::$ss-> user['preferences'] = $preferences;

		$this-> evan-> add(sprintf("The default editor for all loaded templates has been changed to %s.", $texted_fullname), 'add', array(
			'permanent'=> true,
			'timeout'=> time()+ 30
		));
		$this-> send_JSON(array('success'=> true));
	}
        
        
        
        
	public function publication($org_id) {
            
            $login_user_id= MVC_Core::$ss->user['id'];
            $users_not_active = $this->user->get_publication_user_List($org_id, 'not_active',$login_user_id);
            $users_active = $this->user->get_publication_user_List($org_id, 'active',$login_user_id);
            $this->current_org_modify = $org_id;
            $this->users_not_active = $users_not_active;
            $this->users_active = $users_active;
            $this->last_url = $_SERVER['HTTP_REFERER'];
            $this->curr_org_id =$org_id;
            // which body template to use, and run the view
            $this->site_section = "organization" . DS . "publication.html";
            $this->view("private.html");
        }

    public function action_allowPublicationAllDep(MVC_Request $post) {

//            if (!$this->user->is_Unrestricted())
//                return $this->send_Fail_JSON("No access to this function. How did you even get here?");

//                    print_r($_POST);exit();
            $user_id = $_POST['user_id'];
            if ($_POST['allow'] == 'yes') {
                $usr_update['publish_all_linked_department'] = '1';
                $usr_update['publish_all_linked_department_ids']= json_encode($_POST['selected_departments']);
            } else {
                $usr_update['publish_all_linked_department'] = '0';
                $usr_update['publish_all_linked_department_ids']='';
            }
//print_r($usr_update);exit();
            $q = QueryFactory::update()
                    ->table(MVC_Core::$db->tab['usr'])
                    ->values($usr_update)
                    ->where("`id` = ?", $user_id)
                    ->limit(1);
            MVC_Core::$db->query($q);

            $affected = MVC_Core::$db->affected_rows;
            $this->send_JSON(array('success' => true, 'response' => "This user is now allow to publish all the departments"));
        }
        
        public function action_getDepertmentsByUser(MVC_Request $post) {
            $this->dep = $this->factory_Model("Department", "dep");

            $user_id = $_POST['user_id'];
//            $org_id = MVC_Core::$ss->user['org_id'];

            $q = QueryFactory::select()
                    ->table(MVC_Core::$db->tab['usr'], 'publish_all_linked_department_ids,org_id')
                    ->where("`id` = ?", $user_id)
                    ->limit(1);
            $publish_all_linked_department_ids = MVC_Core::$db->query($q);
            
            $publish_all_linked_department_ids_array = array();
            foreach ($publish_all_linked_department_ids as $dpIds) {
                $publish_all_linked_department_ids_array = $dpIds;
                $org_id = $dpIds['org_id'];
            }
//                        print_r($publish_all_linked_department_ids_array);
            $org_departments = $this->dep->get_List($org_id, array());
            $user_departments = $this->user->getDepartmentsByUserId($user_id);

    //                print_r($user_departments);

            $depHtml = '';
            $userDepArr = array();
                $depHtml .= '<fieldset id="userDep"> <legend  >Departments connected with the user</legend>';
            foreach ($user_departments as $deUser) {
                $userDepArr[] = $deUser['id'];
                $depHtml .= '<h3 class="selectDep" style=" text-align: left; "><label><input type="checkbox"  value="' . $deUser['id'] . '" />' . $deUser['name'] . '</label></h3>';
            }
                $depHtml .= '</fieldset>';
//                print_r($userDepArr);
                $depHtml .= '<fieldset id="otherDep"> <legend >Other Departments</legend>';
            foreach ($org_departments as $de) {
                if(!in_array($de['id'],$userDepArr)){
                $depHtml .= '<h3 class="selectDep" style=" text-align: left; "><label><input type="checkbox"  value="' . $de['id'] . '" />' . $de['name'] . '</label></h3>';
                                }               
                }
                $depHtml .= '</fieldset>'; 




            $this->send_JSON(array('success' => true, 'depHtml' => $depHtml));
        }
        
        public function action_getActiveDepertmentsByUser(MVC_Request $post) {
            $this->dep = $this->factory_Model("Department", "dep");

           
            $user_id = $_POST['user_id'];
//            $org_id = MVC_Core::$ss->user['org_id'];
            $org_id = $_POST['currOrg'];


            $q = QueryFactory::select()
                    ->table(MVC_Core::$db->tab['usr'], 'publish_all_linked_department_ids')
                    ->where("`id` = ?", $user_id)
                    ->limit(1);
            $publish_all_linked_department_ids = MVC_Core::$db->query($q);
            $publish_all_linked_department_ids_array = array();
            foreach ($publish_all_linked_department_ids as $dpIds) {
                $publish_all_linked_department_ids_array = $dpIds;
            }
            $activeDeparments= json_decode($publish_all_linked_department_ids_array['publish_all_linked_department_ids']);
//                        print_r($publish_all_linked_department_ids_array);
            $org_departments = $this->dep->get_List($org_id, array());
            $user_departments = $this->user->getDepartmentsByUserId($user_id);

    //                print_r($user_departments);

            $depHtml = '';
            $userDepArr = array();
                $depHtml .= '<fieldset id="userDep"> <legend  >Departments connected with the user</legend>';
            foreach ($user_departments as $deUser) {
                $userDepArr[] = $deUser['id'];
                $checked="";
                if(in_array($deUser['id'], $activeDeparments)){
                    $checked="checked";
                }
                $depHtml .= '<h3 class="selectDep" style=" text-align: left; "><label><input type="checkbox" '.$checked.' value="' . $deUser['id'] . '" />' . $deUser['name'] . '</label></h3>';
            }
                $depHtml .= '</fieldset>';
//                print_r($userDepArr);
                $depHtml .= '<fieldset id="otherDep"> <legend >Other Departments</legend>';
            foreach ($org_departments as $de) {
                if(!in_array($de['id'],$userDepArr)){
                     $checked="";
                if(in_array($de['id'], $activeDeparments)){
                    $checked="checked";
                }
                $depHtml .= '<h3 class="selectDep" style=" text-align: left; "><label><input type="checkbox" '.$checked.' value="' . $de['id'] . '" />' . $de['name'] . '</label></h3>';
                                }               
                }
                $depHtml .= '</fieldset>'; 




            $this->send_JSON(array('success' => true, 'depHtml' => $depHtml,));
        }

       
//        IWI report cron settings for admin start

    public function reportcron($org_id) {
        array_push($this->CFG['modules'], "organization/_reportcron"); // for loading js page 
        $org_id = $org_id ?: MVC_Core::$ss->user['org_id'];
        $login_user_id = MVC_Core::$ss->user['id'];
        $users = $this->user->get_List(array('department'), function (QueryFactory &$q) use ($org_id) {
            $q->where('`usr`.org_id = ?', $org_id);
        }, null, "`name` ASC");
        foreach ($users as &$user) {
            if (!empty($user['department']['name']))
                $user['group'] = substr($user['department']['name'], 0, 27) . (strlen($user['department']['name']) > 27 ? '...' : '');
            else
                $user['group'] = "(No active department)";
            unset($user['department'], $user['user2dep'], $user['org_id']);
        }

        $this->has_users = (bool) $users;
        $this->curr_org_id = $org_id;
        $this->users = $users ?: array();

//        $getAllData = $this->org->getReportCron('Training Expiry');
//        $this->getAllData = $getAllData ?: array();
        $this->site_section = "organization" . DS . "report_cron.html";
        $this->view("private.html");
    }
    
    public function action_GetReportCronList() {
        $type = $_POST['type'];
        $org_id = $_POST['org_id'] ?: MVC_Core::$ss->user['org_id'];
        $getAllData = $this->org->getReportCron($type,$org_id);
        $this->send_JSON(array('success' => true, 'getAllData' => $getAllData,));
    }



    public function action_GetReportCronDetails() {
        $id = $_POST['id'];
        $getDetails = $this->org->getReportCronDetails($id);
        $this->send_JSON(array('success' => true, 'getDetails' => $getDetails,));
    }

    public function action_AddNewReportCron() {
        $report_type = isset($_POST['report_type']) ? trim($_POST['report_type']) : '';
        $user_id = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
        $email_frequency = isset($_POST['email_frequency']) ? trim($_POST['email_frequency']) : '';
        if ($email_frequency == 'monthly') {
            $email_day = isset($_POST['date_select']) ? trim($_POST['date_select']) : '';
        } else if ($email_frequency == 'daily') {
            $email_day = '';
        }else {
            $email_day = isset($_POST['day_select']) ? trim($_POST['day_select']) : '';
        }

        $userDetails = $this->user->get_user_details($user_id);
        $user_email = $userDetails['email'];
        $user_name = $userDetails['name'];
//        $user_org_id = MVC_Core::$ss->user['org_id'];
        $user_org_id = $_POST['org_id'];
        
//        print_R($_POST);

        $getData = array(
            'report_type' => $report_type,
            'user_id' => $user_id,
            'org_id' => $user_org_id,
            'user_name' => $user_name,
            'user_email' => $user_email,
            'email_frequency' => $email_frequency,
            'email_day' => $email_day,
            'send_by_filter' => json_encode($_POST['send_by_filter']),
        );
        $org = $this->org->addNewReportCron($getData);
        if ($org > 0) {
            $this->send_JSON(array('success' => true, 'id' => $org, 'message' => 'Successfully added'));
        } else {
            $this->send_JSON(array('success' => false, 'message' => 'opps! please try again'));
        }
    }
    
    
    public function action_editNewReportCron() {
        $form_id = isset($_POST['id']) ? trim($_POST['id']) : '';
        $report_type = isset($_POST['report_type']) ? trim($_POST['report_type']) : '';
        $user_id = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
        $email_frequency = isset($_POST['email_frequency']) ? trim($_POST['email_frequency']) : '';
        if ($email_frequency == 'monthly') {
            $email_day = isset($_POST['date_select']) ? trim($_POST['date_select']) : '';
        } else if ($email_frequency == 'daily') {
            $email_day = '';
        }else {
            $email_day = isset($_POST['day_select']) ? trim($_POST['day_select']) : '';
        }

        $userDetails = $this->user->get_user_details($user_id);
        $user_email = $userDetails['email'];
        $user_name = $userDetails['name'];
//        $user_org_id = MVC_Core::$ss->user['org_id'];
        $user_org_id = $_POST['org_id'];
        

        $getData = array(
            'report_type' => $report_type,
            'user_id' => $user_id,
            'org_id' => $user_org_id,
            'user_name' => $user_name,
            'user_email' => $user_email,
            'email_frequency' => $email_frequency,
            'email_day' => $email_day,
            'send_by_filter' => json_encode($_POST['send_by_filter']),
        );
        $org = $this->org->editNewReportCron($form_id,$getData);
        if ($org > 0) {
            $this->send_JSON(array('success' => true, 'id' => $org, 'message' => 'Successfully updated'));
        } else {
            $this->send_JSON(array('success' => false, 'message' => 'opps! please try again'));
        }
    }

    public function action_DeleteReportCron() {
        $id = $_POST['id'];
        $delete = $this->org->deleteReportCron($id);
        if ($delete) {
            $this->send_JSON(array('success' => true, 'id' => $org, 'message' => 'Successfully deleted'));
        } else {
            $this->send_JSON(array('success' => false, 'message' => 'opps! please try again'));
        }
    }
    
    public function reportcronlog($cid,$orgid) {
        array_push($this->CFG['modules'], "organization/_reportcron"); // for loading js page 
        $login_user_id = MVC_Core::$ss->user['id'];
         $cronSettingId=$cid;
        $this->cid = $cid ;
        $this->orgid = $orgid;
         $getAllLogData = $this->org->getReportCronLog($cronSettingId);
//         print_r($getAllLogData);
        $this->getAllLogData = $getAllLogData ?: array();
        $this->site_section = "organization" . DS . "report_cron_log.html";
        $this->view("private.html");
    }
    
    
//        IWI report cron settings for admin end
}