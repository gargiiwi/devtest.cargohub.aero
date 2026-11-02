<?php
/**
 * User Settings/Preferences - The configuration options available for user himself
 * - Step one for this module is the Notification Preferences "Tree Grid"
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoITS
 * @version 2.5
 */
class App_Ctrl_User_Settings extends App_Ctrl_Base
{
	public static $_module_name = "Preferences and Settings";

	/**
	 * Contact model, which is necessary for user display/editor to work properly
	 * @var App_Model_Contact
	 */
	private $con;

	public function __construct() 
	{
		parent::__construct(true);
		$this-> con = $this-> factory_Model("Contact", "con");
	}

	/**
	 * User Settings init
	 */
	public function action_Index()
	{
		// setup frontend config
		$this-> CFG['components'] = array("modal", "hashnav");
		array_push($this-> CFG['modules'], "user/_settings");
		$this-> CFG['jqwidgets'][] = array("tabs", "expander", "navigationbar");
		$this-> CFG['jqwidgets'][] = array("listbox", "dropdownlist", "datatable"); // "input", "calendar", "datetimeinput"
		$this-> CFG['jqwidgets'][] = array("treegrid"); // Depends on "Datatable"
		$this-> CFG['user_overview'] = array();
		$this-> CFG['xjs'] = array(
			'jqform' => true
		);

		// prepare output
		$this-> prepare_Header(self::$_module_name);

		// User Overview tabs, in the order they will be presented to the user:
		// TODO: Maybe a preference of changing this order?
		$all_available_tabs = array(
			// The preferences tab, contains various checkboxes and radiobuttons - TODO: Move from "My Overview"
			array(
				'id'=> "tab_preferences",
				'title'=> "Preferences",
				'icon'=> 'wrench.png',
				'spacey'=> true
			),
			// User's account information form ( used to change password, name, e-mail, signature ) and also view some details about the organization and department the user is part of
			array(
				'id'=> "tab_notifysub",
				'title'=> "Notification subscriptions",
				'icon'=> "bell.png",
				'spacey'=> true
			)//,
			// NOT YET
// 			array(
// 				'id'=> "tab_profile",
// 				'title'=> "My profile",
// 				'icon'=> "user1.png"
// 			)
		);

		$this-> module = array(
			'icon'=> "cog.png",
			'icon_alt'=> "(cog)",
			'name'=> self::$_module_name,
			'title'=> "Configure the software for your account",
			'tabs'=> $all_available_tabs
		);

		// which body template to use, and run the view
		$this-> site_section = "general_tabs_container.html";
		$this-> view("private.html");
	}

	/**
	 * Loads and prepares HTML document which will become the content of one of the tabs for the User Settings module
	 * -- Loads specific data on demand, for the dynamic content in that tab.
	 *
	 * @param MVC_Request $in
	 */
	public function action_Load_Tab_HTML(MVC_Request $in)
	{
		// Group depenent data which might be needed for the template (nothing yet)
		switch($in-> tab) {
			// Preferences tab
			case 'preferences':
				// User's preferences are sent directly to JavaScript (1. Operation Preferences, 2. Interface configuration)
				$data_response = array();
				$data_response['preferences'] = MVC_Core::$ss-> user['preferences'];
				$this-> prepare_Template_ACLs();

				// 2. E-mail aliases (if they exist) - One can be used as the default Alias (to start with, can be changed from the E-mail Sending area)
				$aliases = $this-> con-> get_List(null, $this-> user-> ID(), 'user_self', array('contact'), "`ctx`.ctime ASC");
				if(count($aliases)>1)
					$this-> aliases = $aliases;

				// 3. Module to load after login (Landing choices)
				$landing = array();
				// UCP and My account - Available for everyone
				/*
                                 * $landing[] = array(                                 
					'url'=> "/user/overview#dashboard",
					'page'=> "Welcome, what's next?"
				);
				$landing[] = array(
					'url'=> "/user/overview#modules",
					'page'=> "Modules dashboard"
				);
				$landing[] = array(
					'url'=> "/user/overview#notify",
					'page'=> "Notifications"
				);
				$landing[] = array(
					'url'=> "/user/overview#account",
					'page'=> "My account settings"
				);
				// Isssue listing and creation depend on some access rights
				if($this-> user-> test_ACL('privileges', 'issue_add'))
					$landing[] = array(
						'url'=> "/issue?action=form",
						'page'=> "Submit new Record"
					);
				if($this-> user-> test_ACL('privileges', 'issues_view'))
					$landing[] = array(
						'url'=> "/search/global",
						'page'=> "Global Search"
					);
                                 * 
                                 */
                                $landing[] = array(
					'url'=> "/user/overview#dashboard",
					'page'=> "Cockpit"
				);
				if($this-> user-> test_ACL('privileges', 'issues_view'))
					$landing[] = array(
						'url'=> "/issue/list",
						'page'=> "List and Search"
					);
                                /*
				// Show either everyone's tasks or just user's tasks
				if($this-> user-> test_ACL('privileges', 'tasks_access'))
					$landing[] = array(
						'url'=> $this-> user-> test_ACL('privileges', 'tasks_access_org')? "/manager/tasks:record?list=org": "/manager/tasks:record?list=self",
						'page'=> $this-> user-> test_ACL('privileges', 'tasks_access_org')? "Task Manager": "My assigned tasks"
					);
				$landing[] = array(
					'url'=> "/manager/organization#overview",
					'page'=> "Organization Overview"
				);
				$landing[] = array(
					'url'=> "/datasource/exchange",
					'page'=> "Currency exchange"
				);
				// A manager/supervisor might want to check users activity as their first option
				if($this-> user-> test_ACL('privileges', 'org_users_view'))
					$landing[] = array(
						'url'=> "/manager/organization#users",
						'page'=> "Organization's Users"
					);
				$landing[] = array(
					'url'=> "/manager/contacts#search",
					'page'=> "Address Book"
				);
				// Admin has access to a few other options
				if($this-> user-> is_Unrestricted()) {
					$landing[] = array(
						'break'=> true,
						'url'=> "/admin/center",
						'page'=> "<b>Administration Center</b>"
					);
					$landing[] = array(
						'url'=> "/admin/center#online",
						'page'=> "Online Users"
					);
					$landing[] = array(
						'url'=> "/admin/cmsys",
						'page'=> "Contents Management System"
					);
					$landing[] = array(
						'url'=> "/admin/orgs",
						'page'=> "Organization Search"
					);
					$landing[] = array(
						'url'=> "/admin/groups",
						'page'=> "Data groups Manager"
					);
					$landing[] = array(
						'url'=> "/admin/users",
						'page'=> "Global user Manager"
					);
					$landing[] = array(
						'url'=> "/admin/contacts",
						'page'=> "Global contacts Search"
					);
					$landing[] = array(
						'url'=> "/manager/tasks?list=global",
						'page'=> "Global tasks Manager"
					);
				}
*/
				$this-> landing_choices = $landing;

				// Hybrid response (HTML+JSON)
				$this-> send_JSON_Headers($data_response);
				break;

			// Notifications subscriptions
			case 'notifysub':
				break;

			// Account profile tab (manages settings)
			case 'profile':
				break;
		}

		// This is an AJAX request, but needs the template as it's a HTML response (not JSON or XML), so, initialize the template object on demand
		$this-> init_Template_System();
		echo $this->_drytpl-> render_File("user". DS. "settings_tabs". DS.  "tab_". $in-> tab. ".html", $this->_out);
	}

	/**
	 * Reads the subscription tree and combine the user's saved subscription preferences
	 * - The tree depends on various factors:
	 * -- "system" notifications which are always visible (and for everyone) - Some cannot even be turned off (but e-mail or other options can)
	 * -- "issue" notifications depend on user's current access, and only groups for the user's existing issues are loaded/shown
	 *
	 * @param MVC_Request $in
	 */
	public function action_Get_Subscriptions(MVC_Request $in)
	{
		$m_issue = new App_Model_Issue();
		if(isset(MVC_Core::$ss-> user['is_admin']))
			// Read all the issuetypes available in the system
			$issuetypes_res = $m_issue-> ist_Get_List(null, true); // Read using Keys, and not ID's | Only "active" ones are read (scope = global, org)
		else
			$issuetypes_res = $m_issue-> ist_Get_List(MVC_Core::$ss-> user['acls']['ist']);

		// Read info about the issue types
		$issuetypes = array();
		foreach($issuetypes_res as $r)
			$issuetypes[$r['key']] = $r;

		// Read current user subscriptions
		$user_subscriptions = $this-> evan-> get_All_User_Subscriptions(MVC_Core::$ss-> user['id']);
		// Read the Notifications Tree Model
		$notifications_tree = MVC_Core::$spyc-> loadFile(ROOT .DS. 'app'. DS. 'data'. DS. 'notifications_tree.yaml');

		// ... and build the return data
		$tgrid = array();
		foreach($notifications_tree as $type_group) {
			$key = $type_group['type'];
			if(isset($type_group['children'])) {
				$parent_key = $key;
				$tgrid[] = array(
					'key'=> $key,
					'type'=> $type_group['type'],
					'label'=> $type_group['label'],
					'ntf'=> isset($user_subscriptions[$key])? $user_subscriptions[$key]: null
				);
				foreach($type_group['subtypes'] as $subtype_group) {
					// All data present in the type + subtype
					$key = $type_group['type'].'_'.$subtype_group['subtype'];
					$tgrid[] = array(
						'key'=> $key,
						'parent_key'=> $type_group['type'],
						'type'=> $type_group['type'],
// 						'subtype'=> $subtype_group['subtype'],
						'label'=> $subtype_group['label'],
						'ntf'=> isset($user_subscriptions[$key])? $user_subscriptions[$key]: null
					);

					if(isset($subtype_group['children'])) {
						$parent_key = $key;
						// At the moment only the "issue" type/subtype has children
						switch($type_group['type']) {
							case 'issue':
								foreach($issuetypes as $ist) {
									$key = $type_group['type'].'_'.$subtype_group['subtype'].'_'.$ist['key'];
									$tgrid[] = array(
										'key'=> $type_group['type'].'_'.$subtype_group['subtype'].'_'.$ist['key'],
										'parent_key'=> $parent_key,
										'type'=> $type_group['type'],
// 										'subtype'=> $subtype_group['subtype'],
										'label'=> $subtype_group['prefix'].' '.$ist['name'],
										'ntf'=> isset($user_subscriptions[$key])? $user_subscriptions[$key]: null
									);
								}
								break;
						}
					}
				}
			} else {
				// No primary group children
				$tgrid[] = array(
					'key'=> $key,
// 					'type'=> $type_group['type'],
					'label'=> $type_group['label'],
					'ntf'=> isset($user_subscriptions[$key])? $user_subscriptions[$key]: null
				);
			}
		}
        //Added By [Deepak DEV]
        //specific params for audit form
        $tgrid[] = array(
            'key'=> 'issue_updated_inex',
            'parent_key'=>'issue_updated_audit',
            'label'=> 'Internal/External',
            'ntf'=> isset($user_subscriptions['issue_updated_inex'])? $user_subscriptions['issue_updated_inex']: null
        );
        //specific params for cargo claim form
        $tgrid[] = array(
            'key'=> 'issue_updated_pstage',
            'parent_key'=>'issue_updated_cargoclaim',
            'label'=> 'Preliminary to formal',
            'ntf'=> isset($user_subscriptions['issue_updated_pstage'])? $user_subscriptions['issue_updated_pstage']: null
        );

		$this-> send_JSON($tgrid);
	}

	/**
	 * Saves the user's notification subscriptions - For each ID, only when the notification is turned "on" (if off, the other values are ignored)
	 *
	 * @param MVC_Request $post
	 */
	public function action_Save_Subscriptions(MVC_Request $post)
	{
		if(!isset(MVC_Core::$ss-> user))
			return $this-> send_Fail_JSON("This action is only valid for staff users");

		$data = @json_decode($post-> data, true);
		if(empty($data))
			return $this-> send_Fail_JSON("Error in the incoming data. Please contact support");

		$r = $this-> evan-> subscription_Write($data, MVC_Core::$ss-> user['id']);

		if(!$r)
			return $this-> send_Fail_JSON("Error when updating or nothing to update");

		return $this-> send_JSON(array('success'=> true, 'response'=> "Notification subscriptions updated !"));
	}

}