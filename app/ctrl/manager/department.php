<?php
/**
 * Editing an organization's department (basic info, department level access overrides, user membership)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2.9
 */
class App_Ctrl_Manager_Department extends App_Ctrl_Base
{
	/**
	 * Department model, used by the actions of this module
	 * @var App_Model_Department
	 */
	private $dep;

	public function __construct() 
	{
		parent::__construct(true);

		$this-> dep = $this-> factory_Model("Department", "dep");
	}
                        
	public static $_module_name = "Department Manager";

	/**
	 * Index action for the Department management page
	 */
	public function action_Index(MVC_Request $in)
	{
		// This entire module is only available for department managers
		if(!$this-> user-> test_ACL('privileges', 'org_deps_manager')) {
			$this-> evan-> add("You are not allowed to manage departments.", 'lock');
			$this-> redirect($this-> CFG['home']);
		}

		$this-> CFG['components'] = array("modal", "hashnav"); // "acls_editor" component loaded dynamically
		array_push($this-> CFG['modules'], "department/_manager");
		$this-> CFG['jqwidgets'][] = array("switchbutton", "tabs", "splitter", "scrollbar", "listbox", "dropdownlist", "combobox");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete", "input", "navigationbar");
		$this-> CFG['jqwidgets'][] = array("grid.edit"); // Edit needed for ACLs Editor
		$this-> CFG['xjs'] = array(
			'jqform' => true
		);

		// prepare output
		$this-> prepare_Header("Department Manager");

		// read department information (used in tabs)
		$dep = $this-> dep-> read($in-> id, array('department', 'chief', 'organization'));
		if(!$dep) {
			// Trying to edit a non-existing department ? Redirect to org
			$this-> evan-> add("Missing or invalid department", "error");
			$this-> redirect("/manager/organization#deps");
		}

		// And these go to JS, for proper editors initialization
		$this-> CFG['department_manager'] = array(
			'module_name'=> self::$_module_name,
			'id'=> (int)$dep['id'],				// Department and Organization ID are sent to JS (they are needed for the other tabs)
			'org_id'=> (int)$dep['org_id'],
			'name'=> $dep['name'],				// Name,Chief ID and "Listed" are used in the "settings" tab
			'chief_id'=> (int)$dep['chief_id'],
			'listed'=> (bool)$dep['listed'],
			'acls'=> array(						// ACLs overrides are used in the "acls" tab
				'privileges'=> $dep['privileges_or'],
				'access_ids'=> $dep['access_ids_or']
			),
			'acls_overrides'=> !(empty($dep['privileges_or']) && empty($dep['access_ids_or']))
		);
		if(!empty($dep['use4ist']))
			$this-> CFG['department_manager']['use4ist'] = $dep['use4ist'];
		if(!empty($dep['use4grp']))
			$this-> CFG['department_manager']['use4grp'] = $dep['use4grp'];

		// The department member list, used for selecting the chief (settings tab) and listing the current members (members tab)
		// Note: A department should always have at least one member, as the first user is added as chief when creating the department
		// It should not be possible to remove the last member, and also not removing current chief (another chief must be selected first)
		$this-> CFG['department_manager']['count_members'] = (int)$dep['count']['members'];
		$this-> CFG['department_manager']['members'] = !empty($dep['count']['members'])? $this-> dep-> get_Dep_User_List($in-> id): array();

		// Load the issue types which are "ON" for an organization ( from the Setup tab )
		$ist_list_r = $this-> org-> ist_Get_List($dep['org_id'], true);
		$ist_source = array();
		foreach($ist_list_r as $row) {
			$ist_source[] = array(
				'id'=> (int)$row['id'],
				'name'=> $row['name'],
				'key'=> $row['key']
			);
		}
		$this-> CFG['department_manager']['ist_source'] = $ist_source;

		// Tabs related to department module
		$all_available_tabs = array(
			// Info tab, shows an overview of department data (name, chief)
			array(
				'id'=> "tab_settings",
				'title'=> "Settings",
				'icon'=> "wrench.png",
				'privilege'=> "org_deps_manager",
				'spacey'=> true
			),
			array(
				'id'=> "tab_acls",
				'title'=> "Dept. level access",
				'icon'=> "lock.png",
				'privilege'=> "org_access_manager",
				'spacey'=> true
			),
			array(
				'id'=> "tab_members",
				'title'=> "Members",
				'icon'=> "group.png",
				'privilege'=> "org_users_view",
				'spacey'=> true
			)
		);

		// The actual tabs that will be shown, depending on access rights
		$user_tabs = array();
		foreach($all_available_tabs as $tab) {
			// General case, tab visibility depends on a standard privilege
			if($tab['privilege'] === true || $this-> user-> test_ACL('privileges', $tab['privilege']))
				$user_tabs[] = $tab;
		}

		$this-> module = array(
			'icon'=> "plugin.png", 'icon_alt'=> "(Department)",
			'title'=> $dep['organization']['name'].' - '.$dep['name'], 'title_para'=> !empty($dep['count']['members'])? "{$dep['count']['members']} members": "Empty, no members",
			'description'=> self::$_module_name,
			'tabs'=> $user_tabs
		);

		// which body template to use, and run the view
		$this-> site_section = "general_tabs_container.html";
		$this-> view("private.html");
	}

	/**
	 * Loads and prepares HTML document which will become the content of one of the tabs for the My Organization module
	 *   Loads specific data on demand, for the dynamic content in that tab.
	 */
	public function action_Load_Tab_HTML(MVC_Request $rq)
	{
	
	
		$this-> prepare_Template_ACLs();

		switch($rq-> tab) {
			case 'settings':
				// Load the list of departments of the organization ( for the dep-2-dep links ) - quick mode, just ID, Name
				$this-> factory_Model("Department", "dep");

				// Load the list of groups that the organization is connected to, and has f_receive set to on (used in the "Incoming data -> Data groups" filter
				$org_grp_r = $this-> org-> get_Groups($rq-> org_id, null, true, true);
				$groups_source = array();
				if($org_grp_r) {
				$igx1= 0;
					foreach($org_grp_r as $grp) {
					if($igx1<100)
					 {
						$groups_source[] = array(
							'id'=> (int)$grp['id'],
							'name'=> $grp['name'],
							'type'=> $grp['type']
						);}$igx1++;
					}
				}

				// Hybrid response (HTML+JSON)
				$this-> send_JSON_Headers(array(
					'org_deps'=> $this-> dep-> get_List($rq-> org_id, array()),
					'receive_groups'=> $groups_source
				));
				break;

			case 'acls':
				// ACLs strings, used by the template
				
				MVC_NLS::load_Groups("acls");
				$this-> acls = MVC_NLS::get_Object("acls", true);
				$this-> superuser = $this-> user-> is_Unrestricted();
				$this-> enable_org_section = true;
 
 
				// Load the list of groups that the organization is connected to, without any filters ( for the ACLs system )
				$org_grp_r = $this-> org-> get_Groups($rq-> org_id );
				$groups_source = array();
				if($org_grp_r) {
				
				$igx= 0;
					foreach($org_grp_r as $grp) {
					if($igx<110)
					 {$groups_source[] = array(
							'id'=> (int)$grp['id'],
							'name'=> $grp['name'],
							'type'=>  $grp['type'],
							'count_el'=> (int)$grp['count_el']
						); $cn1=1;}
						if($igx>109 && $igx<200)
					 {$groups_source1[] = array(
							'id'=> (int)$grp['id'],
							'name'=> $grp['name'],
							'type'=>  $grp['type'],
							'count_el'=> (int)$grp['count_el']
						);$cn1=2;}
						if($igx>199 && $igx<310)
					 {$groups_source2[] = array(
							'id'=> (int)$grp['id'],
							'name'=> $grp['name'],
							'type'=>  $grp['type'],
							'count_el'=> (int)$grp['count_el']
						);$cn1=3;}
						if($igx>309 && $igx<410)
					 {$groups_source3[] = array(
							'id'=> (int)$grp['id'],
							'name'=> $grp['name'],
							'type'=>  $grp['type'],
							'count_el'=> (int)$grp['count_el']
						);$cn1=4;}
						if($igx>409 && $igx<510)
					 {$groups_source4[] = array(
							'id'=> (int)$grp['id'],
							'name'=> $grp['name'],
							'type'=>  $grp['type'],
							'count_el'=> (int)$grp['count_el']
						);$cn1=5;}
						if($igx>509 && $igx<610)
					 {$groups_source5[] = array(
							'id'=> (int)$grp['id'],
							'name'=> $grp['name'],
							'type'=>  $grp['type'],
							'count_el'=> (int)$grp['count_el']
						);$cn1=6;}
						if($igx>609 && $igx<710)
					 {$groups_source6[] = array(
							'id'=> (int)$grp['id'],
							'name'=> $grp['name'],
							'type'=>  $grp['type'],
							'count_el'=> (int)$grp['count_el']
						);$cn1=7;}
						$igx++;
						
						
					}
				}
				//$groups_source2[] = array(0=>$groups_source,1=>$groups_source1);
				  
				  //echo  mb_strlen(serialize((array)$arr), '8bit');
				  //print_r($groups_source); exit();
				     if($cn1==1) 
			 	$this-> send_JSON_Headers(array(
				'groups_source'=> $groups_source,
				 
			 	));
				    if($cn1==2) 
			 	$this-> send_JSON_Headers(array(
				'groups_source'=> $groups_source,
				'groups_source1'=> $groups_source1,
				 
			 	));
			 	if($cn1==3) 
			 	$this-> send_JSON_Headers(array(
				'groups_source'=> $groups_source,
				'groups_source1'=> $groups_source1,
				'groups_source2'=> $groups_source2,
				 
			 	));
			 	if($cn1==4) 
			 	$this-> send_JSON_Headers(array(
				'groups_source'=> $groups_source,
				'groups_source1'=> $groups_source1,
				'groups_source2'=> $groups_source2,
				'groups_source3'=> $groups_source3,
				 
			 	));
			 	if($cn1==5) 
			 	$this-> send_JSON_Headers(array(
				'groups_source'=> $groups_source,
				'groups_source1'=> $groups_source1,
				'groups_source2'=> $groups_source2,
				'groups_source3'=> $groups_source3,
				'groups_source4'=> $groups_source4,
				 
			 	));
			 	if($cn1==6) 
			 	$this-> send_JSON_Headers(array(
				'groups_source'=> $groups_source,
				'groups_source1'=> $groups_source1,
				'groups_source2'=> $groups_source2,
				'groups_source3'=> $groups_source3,
				'groups_source4'=> $groups_source4,
				'groups_source5'=> $groups_source5,
				 
			 	));
			 	if($cn1==7) 
			 	$this-> send_JSON_Headers(array(
				'groups_source'=> $groups_source,
				'groups_source1'=> $groups_source1,
				'groups_source2'=> $groups_source2,
				'groups_source3'=> $groups_source3,
				'groups_source4'=> $groups_source4,
				'groups_source5'=> $groups_source5,
				'groups_source6'=> $groups_source6,
				 
			 	));
				
				
				break;
				
				 

			case 'members':
				// Are there any members?
				$this-> count_members = $rq-> count_members;
				break;
		}

		// This is an ajax request, but with a HTML response, so initialize the template object on demand
		$this-> init_Template_System();
		echo $this->_drytpl-> render_File("department". DS. "manager_tabs". DS. "tab_". $rq-> tab. ".html", $this->_out);
	}

	/**
	 * Reads basic list of departments ( for now, only basic data: id, name, count_members)
	 * - Optionally filters by the departments enabled for a Record ( `iod`.dep_ids )
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Get_List(MVC_Request $rq)
	{
		$org_id = $rq-> org_id?: MVC_Core::$ss-> user['org_id'];
		$id_list = null;

		// Record-linked department list ? Find the record and get the list of departments which are connected
		if($rq-> track) {
			$m_rec = new App_Model_Issue();
			$rec = $m_rec-> read($rq-> track, array('organization'), $org_id);
			$id_list = $rec['dep_ids'];
		}

		// For restricted users only return the list of departments to which the user is a member of, unless the user has the privilege to edit departments (in which case he/she can see all departments)
		if($rq-> filter_membership && !$this-> user-> is_Unrestricted() && !$this-> user-> test_ACL('privileges', 'org_deps_manager')) {
			$user_dep_links = $this-> dep-> get_User_Dep_Relation_List($this-> user-> ID(), $org_id, false);
			$user_dep_ids = array();
			foreach($user_dep_links as $link)
				$user_dep_ids[] = (int)$link['id'];

			if(isset($id_list))
				$id_list = array_intersect($id_list, $user_dep_ids);
			else
				$id_list = $user_dep_ids;
		}

		// TODO: Limit search to departments which have access ID overrides for
		if($rq-> rt_id) {

		}

		$deps = $this-> dep-> get_List($org_id, array(), function(QueryFactory &$q) use ($rq, $id_list) {
			if(is_array($id_list) && count($id_list)>0)
				$q-> where("`dep`.id IN (?)", $id_list);
		});
		if(!$deps || count($deps)==0)
			return header("HTTP/1.0 404 No departments available");

		$this-> send_JSON($deps);
	}

	/**
	 * Returns detailed department information (like chief, privileges and access ids overrides, count member)
	 * -- Privileges overrides will be shown in a grid, already translated
	 * -- For known access IDs the related information is also loaded ( to show the actual content, instead of a number )
	 * Returns HTML
	 */
	public function action_Get_Details(MVC_Request $in)
	{
		MVC_NLS::load_Groups("acls");
		$acls_text = MVC_NLS::get_Object("acls", true);

		$dep = $this-> dep-> read($in-> id, array('department', 'chief', 'members'));

		// Assamble the list of overrides
		$acls_or = array();

		// Access to record types override
		if(isset($dep['access_ids_or']) && isset($dep['access_ids_or']['ist'])) {

			$ist_list_r = $this-> org-> ist_Get_List($dep['org_id'], true);
			$ist_source = array();
			foreach($ist_list_r as $row) {
				$ist_source[(int)$row['id']] = array(
					'name'=> $row['name'],
					'key'=> $row['key']
				);
			}
			$acls_or['rt'] = array();
			foreach($dep['access_ids_or']['ist'] as $rt_id) {
				if(!isset($ist_source[$rt_id])) {
					MVC_Core::$debug-> dump(sprintf("Obsolete or unavailable record type detected in RT override: %d", $rt_id), __METHOD__, null, 'warning');
					continue;
				}
				$acls_or['rt'][] = $ist_source[$rt_id];
			}
			if(empty($acls_or['rt']))
				unset($dep['access_ids_or']['ist']);
		}

		// User privileges overrde
		if(!empty($dep['privileges_or'])) {
			$acls_or['privileges'] = array();
			foreach($dep['privileges_or'] as $privilege) {
				if(!isset($acls_text['privileges'][$privilege])) {
					MVC_Core::$debug-> dump(sprintf("Obsolete privilege detected in override: %s", $privilege), __METHOD__, null, 'warning');
					continue;
				}
				$acls_or['privileges'][$privilege] = $acls_text['privileges'][$privilege];
			}
		}

		$dep['acls_or'] = $acls_or;

		$this-> department = $dep;
		$this-> privilege_groups = $acls_text['privilege_groups'];

		$this-> prepare_Template_ACLs();
		// This is an ajax request, but with a HTML response, so initialize the template object on demand
		$this-> init_Template_System();
		$this-> view("department". DS. "overview.html");
	}

	/**
	 * Adds a new department to the specified organization, returning the insert-id
	 * Returns JSON
	 */
	public function action_Add()
	{
		$post = new MVC_Request('post');
		if(!$post-> org_id) {
			$this-> send_Fail_JSON("No organization ID has been specified, unable to create a department !");
			return;
		}

		$iid = $this-> dep-> create($post-> org_id, $post-> dep);
		if($iid) {
			// Department create success !
			$this-> evan-> add(sprintf("The department %s has been created", $post-> dep['name']), 'add', array(
				'permanent'=> true,
				'timeout'=> time()+ 30
			));
			$this-> send_JSON(array("success"=> true, "iid"=> $iid));
		} else
			$this-> send_Fail_JSON("Unable to create new department! Access restriction or system error.");
	}

//===== Settings tab =====//

	/**
	 * Deletes a department from the user's organization
	 * @TODO: Move this info in the model
	 * -- First it sets the active department of all users which have this department as current to be 0 (none, use only the company's inital priv/access_id)
	 * -- Disconnects all users from this department in ( u2d ) . This will remove any privileges/access_ids overrides for the user in that department.
	 * -- Removes the department and updates the organization record
	 */
	public function action_Delete()
	{
		$post = new MVC_Request('post');
		if(!$post-> id) {
			$this-> send_Fail_JSON("No department ID has been specified, unable to delete the department !");
			return;
		}

		$r = $this-> dep-> delete($post-> id);
		if($r) {
			$this-> evan-> add(sprintf("The department %s has been deleted, %d member(s) were disconnected from it", $r['name'], $r['count_members']), "remove", array(
				'permanent'=> true,
				'timeout'=> time()+ 30
			));

			$this-> send_JSON(array('success'=> true));
		} else
			$this-> send_Fail_JSON("Unable to delete the department! Not found, access restriction or system error.");
	}

	/**
	 * Updates the primary department information ( with some settings )
	 */
	public function action_Update(MVC_Request $rq)
	{
		// TODO: If not admin, read the department, check if the user actually is able to update this department ( if it belongs to his organization )

		// TODO: Check if the logged in user is the currently selected "chief" - The chief should be able to edit his own department (or select another chief)

		// Make sure the user is actually able to edit departments
		if(!$this-> user-> test_ACL('privileges', 'org_deps_manager'))
			return header("HTTP/1.0 403 Access denied");

		$r = $this-> dep-> update($rq-> id, $rq-> dep, true); // already encoded privileges (from JS)
		if(!$r)
			return header("HTTP/1.0 500 System error. Unable to update department");

		$this-> evan-> add(sprintf("The department %s has been updated", $rq-> dep['name']), 'ok', array(
			'permanent'=> true,
			'timeout'=> time()+ 30
		));

		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Creates or updates a Department to Department link for new records (RecLinks)
	 *
	 * @param MVC_Request $rq
	 * @return void|boolean
	 */
	public function action_Store_RecLinks(MVC_Request $rq)
	{
		if(empty($rq-> src_dep))
			return header("HTTP/1.0 400 The source department is missing from the request");

		$err = $this-> dep-> reclink_Store($rq-> src_dep, $rq-> rt_key, $rq-> link_deps, $rq-> comment, !$rq-> update);

		if(!empty($err)) {
			switch($err) {
				case '1':
					return header("HTTP/1.0 409 There is already a link for selected record type");
				case '2':
					return header("HTTP/1.0 500 Database error while creating the link");
				case '3':
					return header("HTTP/1.0 400 The target department must not include current dep.");
			}
		}

		return $this-> send_JSON(array('success'=> true, 'response'=> "The links have been ".($rq-> update? 'updated': 'created')));
	}

	/**
	 * Deletes a RecLink
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Delete_RecLinks(MVC_Request $rq)
	{
		if(empty($rq-> src_dep))
			return header("HTTP/1.0 400 The source department is missing from the request");
		if(empty($rq-> rt_key))
			return header("HTTP/1.0 400 The record type is not present in the request. Cannot delete.");

		$res = $this-> dep-> reclink_Delete($rq-> src_dep, $rq-> rt_key);

		if(empty($res))
			return header("HTTP/1.0 404 The link was not found in the database. Possibly already deleted");

		return $this-> send_JSON(array('success'=> true, 'response'=> "The link has been deleted"));
	}

	/**
	 * Read the department automatic record linking setup
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Get_RecLinks(MVC_Request $rq)
	{
		$links = array();

		// List of outgoing links
		$r = $this-> dep-> reclink_GetList($rq-> dep_id);
		if($r!==false) {
			foreach($r as $row) {
				$row['ldir'] = "Out";
				$row['rt_id'] = (int)$row['rt_id'];
				$row['link_count'] = count(explode(',', $row['link_deps']));
				$links[] = $row;
			}
		}

		// List of incoming links
		$r = $this-> dep-> reclink_GetIncomingList($rq-> dep_id);
		if($r!==false) {
			foreach($r as $row) {
				$row['ldir'] = "In";
				$row['rt_id'] = (int)$row['rt_id'];
				$row['comment'] = "From: ". $row['src_name'];
				$row['link_count'] = 1;
				$links[] = $row;
			}
		}

		return $this-> send_JSON($links);
	}

//===== ACLs tab =====//

	/**
	 * Update Access Control Lists overrides for the department
	 * @param MVC_Request $in
	 */
	public function action_Update_ACLs(MVC_Request $in)
	{
		// Check if the logged in user is allowed to manage users
		if(!$this-> user-> test_ACL('privileges', 'org_deps_manager') && !$this-> user-> test_ACL('privileges', 'org_access_manager')) {
			$this-> send_Fail_JSON("Access denied.");
			return;
		}

		$post = new MVC_Request('post');

		if(isset($post-> clear)) {
			// A clear request has been submitted ( remove any overrides )
			$u2d_update = array(
				'privileges_or'=> null,
				'access_ids_or'=> null
			);

			if($this-> dep-> update($in-> dep_id, $u2d_update, true)) {
				$this-> evan-> add("Access rights overrides have been cleared. They will take effect at next user login.", 'ok');
				$this-> send_JSON(array('success'=> true));
			} else
				$this-> send_Fail_JSON("Error while clearing the department's access control lists overrides.");

			return;
		}

		// TODO: This is repeated almost identically at both organization, department and user level - Make an universal function somewhere in a model to only have one copy
		if(isset($post-> acls)) {
			// An update request has been submitted ( to set overrides )
			$acls = json_decode($post-> acls, true);

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
			$dep_update = array();
			$dep_update['privileges_or'] = $acls['privileges'];
			$dep_update['access_ids_or'] = !empty($acls['access_ids'])? json_encode($acls['access_ids'], JSON_NUMERIC_CHECK): null;

			if($this-> dep-> update($in-> dep_id, $dep_update, true)) {
				$this-> evan-> add("Department Access Control Lists overrides have been updated. They will take effect at next user login.", 'ok');
				$this-> send_JSON(array('success'=> true));
			} else
				$this-> send_Fail_JSON("Error while updating the department's access control lists overrides.");
		} else {
			$this-> send_Fail_JSON("Interface error: No ACL data to update.");
		}
	}

//===== Members tab =====//

	/**
	 * Returns the list or department members (which are enabled) as a JSON array. Used for user listing and inside a department
	 * @param MVC_Request $rq
	 */
	public function action_Get_Members(MVC_Request $rq)
	{
		if(!$rq-> id)
			return header("HTTP/1.0 400 Department ID Missing from the request");

		$members = $this-> dep-> get_Dep_User_List($rq-> id);

		if(!$members || count($members)==0)
			return $this-> send_JSON(array()); // empty list

		$this-> send_JSON($members);
	}

	public function action_Member_Connect()
	{
		$post = new MVC_Request('post');
		if(!$post-> dep_id)
			return $this-> send_Fail_JSON("Department id not present in the request. Possible software bug?");

		$member = $post-> member;
		$u2d = array(
			'comment'=> $member['comment'],
			'disabled'=> $member['disabled'] == 'true',
			'hidden'=> $member['hidden'] == 'true'
		);
		$r = $this-> dep-> attach_User_to_Dep($post-> dep_id, $member['user_id'], $u2d);
		if(!$r)
			return $this-> send_Fail_JSON("Fail to attach the user to the department. Either the user is already a member or another error occured.");

		$this-> evan-> add("The user has been added to the department", 'add', array(
			'permanent'=> true,
			'timeout'=> time()+ 30
		));

		$this-> send_JSON(array('success'=> true));
	}

	public function action_Member_Disconnect()
	{
		$post = new MVC_Request('post');
		if(!$post-> dep_id)
			return $this-> send_Fail_JSON("Department ID not present in the request. Possible software bug?");
		if(!$post-> user_id)
			return $this-> send_Fail_JSON("User ID not present in the request. Possible software bug?");

		$r = $this-> dep-> detach_User_from_Department($post-> dep_id, $post-> user_id);
		if(!$r)
			return $this-> send_Fail_JSON("Fail to detach the user from the department. Either the user not a member or another error occured.");

		$this-> evan-> add("The user has been removed from the department", "remove", array(
			'permanent'=> true,
			'timeout'=> time()+ 30
		));

		$this-> send_JSON(array('success'=> true));
	}
        
//        IWI When a task needs to be assigned, the system should not populate readonly user in the user list start
        public function action_Get_MembersForAssign(MVC_Request $rq)
	{
		if(!$rq-> id)
			return header("HTTP/1.0 400 Department ID Missing from the request");

		$members = $this-> dep-> get_Dep_User_List($rq-> id);
                
//                print_r($members);
                $withoutReadOnlyUser=array();
                foreach ($members as $key=>$user){
                    if($user['status']!='readonly'){
                    $withoutReadOnlyUser[$key]=$user;
                    }
                }

		if(!$withoutReadOnlyUser || count($withoutReadOnlyUser)==0)
			return $this-> send_JSON(array()); // empty list

		$this-> send_JSON($withoutReadOnlyUser);
	}
//        IWI When a task needs to be assigned, the system should not populate readonly user in the user list end

}