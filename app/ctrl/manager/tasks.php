<?php
/**
 * Tasks Manager
 * - Overview of personal, department and organization tasks with powerful search system
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2.7
 */
class App_Ctrl_Manager_Tasks extends App_Ctrl_Base
{
	public static $_module_name = "Task Manager";

	/**
	 * Task model
	 * @var App_Model_Task
	 */
	protected $task;

	/**
	 * Admin-only module, redirect to home if no access
	 */
	public function __construct()
	{
		parent::__construct(true);

		$this-> task = $this-> factory_Model("Task");
	}

	/**
	 * Tasks Manager module loader. Has multiple modes of operation
	 * - "self" Shows only tasks assigned to the user himself (Default)
	 * - "org_id=?" Admin only: Shows all tasks owned by any organization
	 * - "list=org" Shows all tasks owned by the organization (includes assigned to departments, other users and the user's own assigned tasks)
	 * - "list=dep" Shows all tasks assigned to the user's department, including their own assigned
	 * - "dep_id=?" Shows tasks assigned to another department (within the user's organization, or in case of Admin, any department)
	 * - "user_id=?" Shows assigned tasks to any arbitrary user (within the same organization, or in case of Admin, any user in the system)
	 */
	public function action_Index(MVC_Request $in)
	{
		// Grid operation viewing mode
		if(!empty($_GET['_urlx']) && $_GET['_urlx'][0] == ':')
			$link_type = substr($_GET['_urlx'], 1);
		else
			$link_type = "all"; // Start in "All mode if there's none specified

		$tasks_class = array();

		if($in-> list == "global") {
			// Admin function: List ALL SYSTEM TASKS
			$tasks_class['mode'] = $mode = "global";
			$tasks_class['name'] = "Global Tasks Overview";
		} elseif($in-> org_id) {
			// Admin function: Operating tasks for any organization
			if(!$this-> user-> is_Unrestricted()) {
				$this-> evan-> add("You are not allowed to manage tasks for organizations.", 'lock');
				return $this-> redirect("/manager/tasks:{$link_type}?list=self"); // by default the module shows only tasks assigned to self
			}

			$mode = "org";
			$tasks_class['mode'] = "org_id";
			$tasks_class['id'] = $org_id = (int)$in-> org_id;
			$org = $this-> org-> read($org_id, array('orgtype'), false);
			$tasks_class['name'] = $org['name'];
		} elseif($in-> list == "org") {
			// Power user mode: Operating all tasks owned by their organization
			if(!$this-> user-> test_ACL('privileges', 'tasks_access_org')) {
				$this-> evan-> add("You are not allowed to access organization tasks.", 'lock');
				return $this-> redirect("/manager/tasks:{$link_type}?list=self");
			}

			$tasks_class['mode'] = $mode = "org";
			$tasks_class['id'] = $org_id = (int)MVC_Core::$ss-> user['org_id'];
			$org = MVC_Core::$ss-> organization;
			$tasks_class['name'] = $org['name'];
			$tasks_class['user_name'] = MVC_Core::$ss-> user['name'];
			$tasks_class['user_id'] = (int)MVC_Core::$ss-> user['id'];
		} elseif($in-> dep_id) {
			// Operatings tasks assigned to an arbitrary department (must have both department manager and task-manager rights)
			if(!$this-> user-> test_ACL('privileges', 'tasks_access_dep') && !$this-> user-> test_ACL('privileges', 'org_deps_manager')) {
				$this-> evan-> add("You are not allowed to access tasks for departments.", 'lock');
				return $this-> redirect("/manager/tasks:{$link_type}?list=self");
			}

			$mode = "dep";
			$tasks_class['mode'] = "dep_id";
			$tasks_class['id'] = $dep_id = (int)$in-> dep_id;

			// TODO: Determine if the user can actually see/manage selected department


			// TODO: Read department information so it can be shown in title
			$dep = array(
				'name'=> "[ TODO ]"
			);

			$tasks_class['name'] = $dep['name'];
		} elseif($in-> list == "dep") {
			// Department member with the privilege to manage his department's tasks
			if(!$this-> user-> test_ACL('privileges', 'tasks_access_dep')) {
				$this-> evan-> add("You are not allowed to access department tasks.", 'lock');
				return $this-> redirect("/manager/tasks:{$link_type}?list=self");
			}

			$tasks_class['mode'] = $mode = "dep";
			$dep = MVC_Core::$ss-> department;
			$dep['org_name'] = MVC_Core::$ss-> organization['name'];
			$tasks_class['id'] = $dep_id = (int)MVC_Core::$ss-> user['cdep_id'];
			$tasks_class['name'] = $dep['name'];
		} elseif($in-> user_id) {
			// Operating another user's assigned tasks (available when being an user manager)
			if(!$this-> user-> test_ACL('privileges', 'org_user_manager')) {
				$this-> evan-> add("You are not allowed to access tasks for another user.", 'lock');
				return $this-> redirect("/manager/tasks:{$link_type}?list=self");
			}

			$mode = "user";
			$tasks_class['mode'] = "user_id";
			$tasks_class['id'] = $user_id = (int)$in-> user_id;
			$user = $this-> user-> read($user_id, array('user', 'department', 'organization'), false);
			$tasks_class['name'] = $user['name'];
			$tasks_class['org_id'] = $user['org_id'];
		} elseif($in-> list == "self") {
			// View tasks assigned to self (operating at user level, for the logged in user)
			$tasks_class['mode'] = $mode = "self";
			$user = MVC_Core::$ss-> user;
			$tasks_class['id'] = $user_id = (int)$user['id'];
			$tasks_class['name'] = $user['name'];
		} else {
			// If no special mode specified, the contacts manager loads in one of the "my own stuff" modes (org, dep, self), depending on privileges
			if($this-> user-> test_ACL('privileges', 'tasks_access_org')) {
				// "org" - When user is able to manage the org tasks
				$tasks_class['mode'] = $mode = "org";
				$tasks_class['id'] = $org_id = (int)MVC_Core::$ss-> user['org_id'];
				$org = MVC_Core::$ss-> organization;
				$tasks_class['name'] = $org['name'];
			} elseif($this-> user-> test_ACL('privileges', 'tasks_access_dep')) {
				// "dep" - When user is able to manage department tasks but not the org
				$tasks_class['mode'] = $mode = "dep";
				$tasks_class['id'] = $dep_id = (int)MVC_Core::$ss-> user['cdep_id'];
				$tasks_class['name'] = MVC_Core::$ss-> department['name'];
			} else {
				// "self" - If no special privilege
				$tasks_class['mode'] = $mode = "self";
				$tasks_class['id'] = $user_id = (int)MVC_Core::$ss-> user['id'];
				$tasks_class['name'] = MVC_Core::$ss-> user['name'];
			}
		}

		// Add extra options to the Task class - depending on user rights:
		$tasks_class['access_dep'] = $this-> user-> test_ACL('privileges', 'tasks_access_dep');
		$tasks_class['access_org'] = $this-> user-> test_ACL('privileges', 'tasks_access_org');

		$this-> CFG['components'] = array("modal");
		$this-> CFG['modules'][] = "task/_manager";

		$this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "dropdownlist", "combobox", "expander");
		$this-> CFG['jqwidgets'][] = array("calendar", "datetimeinput",  "input", "numberinput", "slider");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete");
		$this-> CFG['jqwidgets'][] = array("data.export", "grid.export");

		$this-> CFG['xjs'] = array(
			'global' => true,
			'dateformat' => true,
			'jqform'=> true
		);

		$this-> CFG['task_manager'] = array(
			'link_type'=> $link_type,
			'rt_source'=> MVC_Core::$cache-> record_types,
			'tasks_class'=> $tasks_class
		);

		// If present in session, send grid state to JavaScript (keep tasks grid between clicks)
		if(isset(MVC_Core::$ss-> cache['grid_state']['tasks']))
			$this-> CFG['task_manager']['grid_state'] = MVC_Core::$ss-> cache['grid_state']['tasks'];
		else {
			// Default grid state
			$this-> CFG['task_manager']['grid_state'] = array(
				'pageNum'=> 0,
				'pageSize'=> 20,
				'pageSizeOpts'=> array(10, 20, 50, 100, 200),
				'sort'=> array(
					'column'=> "deadline",
					'direction'=> "asc"
				),
				'filters'=> array(
					array(
						'column'=> "status",
						'filters'=> array(
							array(
								'value'=> "failed",
								'condition'=> "NOT_EQUAL",
								'operator'=> 1,
								'type'=> "stringfilter"
							),
							array(
								'value'=> "closed",
								'condition'=> "NOT_EQUAL",
								'operator'=> 1,
								'type'=> "stringfilter"
							),
							array(
								'value'=> "completed",
								'condition'=> "NOT_EQUAL",
								'operator'=> 1,
								'type'=> "stringfilter"
							)
						)
					)
				)
			);
		}

		$this-> prepare_Header("Task Manager");

		// Actual title/info depends on operation mode
		switch($mode) {
			case 'self':
				// Default mode, shows only your own assigned tasks
				$this-> module = array(
					'name'=> self::$_module_name,
					'title'=> MVC_Core::$ss-> user['name'],
					'description'=> "My assigned tasks"
				);
				break;
			case 'global':
				$this-> module = array(
					'name'=> self::$_module_name,
					'title'=> "Global Tasks Overview",
					'description'=> "[Administration Mode]"
				);
				break;
			case 'org':
				// Shows all tasks owned by an organization
				$this-> module = array(
					'name'=> self::$_module_name,
					'title'=> $org['name'],
					'title_para'=>  $org['orgtype']['name'],
					'description'=> "Organization tasks"
				);
				break;
			case 'dep':
				// Shows tasks assigned to a department, and if that department is the user's current department (cdep_id), also shows tasks assinged to self
				$this-> module = array(
					'name'=> self::$_module_name,
					'title'=> $dep['name'],
	 				'title_para'=> $dep['org_name'],
					'description'=> "Department tasks"
				);
				break;
			case 'user':
				// Shows tasks assigned to another user, just like they are viewing their own assigned tasks
				$this-> module = array(
					'name'=> self::$_module_name,
					'title'=> $user['name'],
					'title_para'=> $user['department']['name'], // TODO: For admin, it should be organization + department, not just department
					'description'=> "User's assigned tasks"
				);
				break;
		}

		$this->_out['module']['icon'] = "to_do_list_checked_1.png";
		$this->_out['module']['icon_alt'] = "(tasks)";

		// which body template to use, and run the view
		$this-> site_section = "task". DS. "manager.html";
		$this-> view("private.html");
	}

	/**
	 * Followed by a grid operation which affects returned data (filter, sort, page size, page number), saves the current values into the session so the grid can be reloaded within the same state
	 * - Can also reset the grid state by passing "state=false"
	 * @param MVC_Request $post
	 */
	public function action_Save_Grid_State(MVC_Request $post)
	{
		if($post-> state == 'false') {
			// Is it a session state clear?
			unset(MVC_Core::$ss-> cache['grid_state']['tasks']);
			$this-> evan-> add("Task Manager has been reset to default", "ok");
			return $this-> send_JSON(array('success'=> true));
		}

		$grid_state = json_decode($post-> state, true);
		if(empty($grid_state))
			return header("HTTP/1.0 503 There was an error saving the grid state. You can ignore this message but if it repeats please contact support");

		// Save the state into the session
		if(!isset(MVC_Core::$ss-> cache['grid_state']))
			MVC_Core::$ss-> cache['grid_state'] = array();
		MVC_Core::$ss-> cache['grid_state']['tasks'] = $grid_state;
		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Resets the column list to the predefined default for that type, by deleting cached grid state
	 * @param MVC_Request $post
	 */
	public function action_Reset_Grid(MVC_Request $post)
	{
		// Also clears any grid state that might have been saved (Most likely not valid anymore with different columns)
		unset(MVC_Core::$ss-> cache['grid_state']['tasks']);

		$this-> evan-> add("Grid configuration has been reset to default", "ok");
		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Reads list of departments for an organization.
	 * - Optionally filters by the departments enabled for a Record ( `iod`.dep_ids
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Get_Departments(MVC_Request $rq)
	{
		if(!$rq-> org_id)
			return header("HTTP/1.0 400 Organization ID missing from the request");

		// Find the record and get the list of departments which are active for the specified organization
		if($rq-> track) {
			$m_rec = new App_Model_Issue();
 			$rec = $m_rec-> read($rq-> track, array('organization'), $rq-> org_id);
 			dd($rec);
		}

		$id_list = null;

		// List of departments in this organization; quick mode, just ID, Name
		$m_dep = new App_Model_Department();
		$deps = $m_dep-> get_List($rq-> org_id, array(), function(QueryFactory &$q) use ($rq, $id_list) {
			if(is_array($id_list) && count($id_list)>0)
				$q-> where("`dep`.id IN (?)", $id_list);
		});
		if(!$deps || count($deps)==0)
			return header("HTTP/1.0 404 No departments available");

		$this-> send_JSON($deps);
	}

}