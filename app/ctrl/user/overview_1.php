<?php
/**
 * User Overview/Cockpit/Dashboard - An overview of the things that are relevant to a staff user (mostly usable as a "landing" page after login)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2.7
 */
class App_Ctrl_User_Overview extends App_Ctrl_Base
{
	/**
	 * Contact model, which is necessary for user display/editor to work properly
	 * @var App_Model_Contact
	 */
	private $con;

	/**
	 * Issue model
	 * @var App_Model_Issue
	 */
	private $issue;

	/**
	 * Statistics model reference
	 *
	 * @var App_Model_Statistics
	 */
	public $stx;

	/**
	 * Record Plugin, extended from Statistics model
	 * @var App_Model_Statistics
	 */
	protected $rec_plugin;

	public function __construct()
	{
		parent::__construct(true);
		$this-> con = $this-> factory_Model("Contact", "con");
		$this-> issue = $this-> factory_Model('Issue');
	}

	public static $_module_name = "My Overview";

	/**
	 * User Overview Module init
	 */
	public function action_Index()
	{
		// setup frontend config
		$this-> CFG['components'] = array("modal", "contact_editor");
		array_push($this-> CFG['modules'], "user/_overview");
		$this-> CFG['jqwidgets'][] = array("tabs", "docking", "scrollbar", "listbox", "dragdrop", "dropdownlist", "input");
		$this-> CFG['jqwidgets'][] = array("validator", "expander", "navigationbar", "calendar", "datetimeinput", "switchbutton");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete");

		// Chart-related stuff
		$this->CFG['jqwidgets'][] = array("chart", "draw", "chart.core", "grid.export", "data.export");

		$this-> CFG['user_overview'] = array();
		$this-> CFG['xjs'] = array(
			'jqform'=> true,
			'tinymce'=> true,
			'pwstrength'=> true
		);

		// prepare output
		$this-> prepare_Header(self::$_module_name);

		// User's preferences are sent to JavaScript, as they are used all over the User CP
		$this-> CFG['user_overview']['preferences'] = MVC_Core::$ss-> user['preferences'];

		// User privileges are needed in the organization module JS, as it determines if some operations are possible
		// FIXME: Wasn't this moved to Application module ?
		$user_privileges = array();
		if(isset(MVC_Core::$ss-> user['acls']))
			foreach(MVC_Core::$ss-> user['acls']['privileges'] as $privilege)
				$user_privileges[$privilege] = true;
		$this-> CFG['user_overview']['user_acls'] = array(
			'is_admin'=> $this-> user-> is_Unrestricted(),
			'privileges'=> $user_privileges
		);

		// User Overview tabs, in the order they will be presented to the user:
		// TODO: Maybe a preference of changing this order?
		$all_available_tabs = array(
			// The primary tab for user overview, shows a panel-based interface (configurable) which can grab information from all around the system
			array(
				'id'=> "tab_dashboard",
				'title'=> MVC_Core::$ss-> organization['orgtype']['mainmenu'][0]['name'],
				'icon'=> MVC_Core::$ss-> organization['orgtype']['mainmenu'][0]['icon']
			),
			// Experiment: Listing all record types (modules), with information about them
			array(
				'id'=> "tab_modules",
				'title'=> "Charts",
				'icon'=> "bricks.png",
				'spacey'=> true
			),
			array(
				'id'=> "tab_history",
				'title'=> "History", //  and Updates
				'icon'=> "clock_history_frame.png",
				'spacey'=> true
			),
			// User's account information form ( used to change password, name, e-mail, signature ) and also view some details about the organization and department the user is part of
			array(
				'id'=> "tab_account",
				'title'=> "My account",
				'icon'=> "user_gray.png",
				'spacey'=> true
			),
			//
			array(
				'id'=> "tab_vcard",
				'title'=> "Contact information",
				'icon'=> "vcard.png",
				'spacey'=> true
			),
			// User's current Access Control Lists, shows the user's privileges and other data compiled from both the organization features column and the calculated access_ids
			array(
				'id'=> "tab_acls",
				'title'=> "My current access",
				'icon'=> "lock.png",
				'spacey'=> true
			),
			// User's group access (this is also an ACL), presented as a table, in which the user can set some options for each group he has access to
			array(
				'id'=> "tab_notify",
				'title'=> "Notifications",
				'icon'=> "bell.png",
				'spacey'=> true
			),
			array(
				'id'=> "tab_updates",
				'title'=> "Support", //  and Updates
				'icon'=> "support.png",
				'spacey'=> true
			)
		);

		$this-> module = array(
			'icon'=> "user_green.png",
			'icon_alt'=> "(User)",
			'description'=> "Information about your account",
			'title'=> self::$_module_name, 'title_para'=> MVC_Core::$ss-> user['name'],
			'tabs'=> $all_available_tabs
		);

		//== Contact editor JS data ==//

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

		$this-> CFG['user_overview']['vcard_fields_structure'] = $fields_structure;

		// which body template to use, and run the view
		$this-> site_section = "general_tabs_container.html";
		$this-> view("private.html");
	}

	/**
	 * Loads the Dashboard containing various useful information and navigation shortcuts
	 *
	 * @param MVC_Request $in
	 */
	private function load_Tab_Dashboard(MVC_Request $in)
	{
		// Dashboard tab needs data from the session and also checks privileges
		$this-> current_rt = MVC_Core::$cache-> current_rt;

		$datasources = array();
		// Recently opened records - Add ist information (key, name)
		$recent_records = $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "iss_recent", false, 1, true);
		if(!empty($recent_records)) {
			$datasources['recent'] = array();
			foreach($recent_records as $track=> $data) {
				if(!isset(MVC_Core::$cache-> record_types_index[$data['ist']])) {
					unset($recent_records[$track]);
					continue;
				}

				$rt_info = &MVC_Core::$cache-> record_types[MVC_Core::$cache-> record_types_index[$data['ist']]];
				$datasources['recent'][] = array_merge($data, array(
					'track'=> $track,
					'rt_key'=> $rt_info['key'],
					'rt_name'=> $rt_info['name']
				));
			}
		}
		$this-> recent_issues_count = count($recent_records);

		// Read the Mini-Notepad data
		$this-> mini_notepad = htmlspecialchars($this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "mini_notepad"));

		// Read list of issues for the "Recent Issues" panel.
		$this-> prepare_Template_ACLs();

		// User elements count
		$this-> count = MVC_Core::$ss-> user['count'];

		// Hybrid response (HTML+JSON)
		if(!empty($datasources))
			$this-> send_JSON_Headers(array(
				'dataSources'=> $datasources
			));
	}
	/*Dayakar code start */
	/**
	 * Loads the Dashboard containing various useful information and navigation shortcuts
	 *
	 * @param MVC_Request $in
	 */
	private function load_Tab_Dashboard_cockpit(MVC_Request $in)
	{
	    // Extract some information about active record type
		$current_rt = array(

		);
		$issueCount=$this->user->user_IssueCount(MVC_Core::$ss-> user['id'], MVC_Core::$ss-> user['org_id'], MVC_Core::$ss-> user['status'], MVC_Core::$cache-> record_types_index);
		if(MVC_Core::$ss-> user['status']!=admin){
		  $myAssignedissueCount=$this->user->user_IssueCountMyAssigned(MVC_Core::$ss-> user['id'], MVC_Core::$ss-> user['org_id'], MVC_Core::$ss-> user['status'], MVC_Core::$cache-> record_types_index);
		}
		$myTaskCount=$this->user->active_TaskCount(MVC_Core::$ss-> user['id'], MVC_Core::$ss-> user['org_id'], MVC_Core::$ss-> user['status'], MVC_Core::$cache-> record_types_index);
		$rt_r = $this-> issue-> ist_Get_List();
		$acls_rt = isset(MVC_Core::$ss-> user['acls']['ist'])? MVC_Core::$ss-> user['acls']['ist']: true;

		$rt = array(); $rt_disabled = array();
		foreach($rt_r as $row) {
			$row['id'] = (int)$row['id'];
			// Compare with user's access rights?
			if($acls_rt === true)
				$row['yep'] = true;
			elseif(in_array($row['id'], $acls_rt))
				$row['yep'] = true;

			if(isset($row['yep']))
				$rt[] = $row;
			else
				$rt_disabled[] = $row;
		}
		/*for recent records*/
		//changes for active record count
		$issue_Details=array_merge($rt, $rt_disabled);
		$issueCountForTypes=array();
		$myAssignedissueCountForTypes=array();
		$myTasksCount=array();
		foreach ($issueCount as $key=>$value){
		    $issueCountForTypes[$value['id']]=$value['issue_count'];
		}
		foreach ($issue_Details as $key=>$issVal){
		   $issue_Details[$key]['isscount']= $issueCountForTypes[$issVal['id']];
		}
		if(MVC_Core::$ss-> user['status']!=admin){
			foreach ($myAssignedissueCount as $key=>$value){
				$myAssignedissueCountForTypes[$value['id']]=$value['issue_count'];
			}
			foreach ($issue_Details as $key=>$issVal){
				$issue_Details[$key]['myAssignedCount']= $myAssignedissueCountForTypes[$issVal['id']];
			}
		}
		foreach ($myTaskCount as $key=>$value){
		    $myTasksCount[$value['id']]=$value['issue_count'];
		}
		foreach ($issue_Details as $key=>$issVal){
	     	   $issue_Details[$key]['taskCount']= $myTasksCount[$issVal['id']];
		}
		$this-> current_rt = MVC_Core::$cache-> current_rt;
		$datasources = array();
		// Recently opened records - Add ist information (key, name)
		$recent_records = $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "iss_recent", false, 1, true);
		if(!empty($recent_records)) {
		    $datasources['recent'] = array();
		    foreach($recent_records as $track=> $data) {
		        if(!isset(MVC_Core::$cache-> record_types_index[$data['ist']])) {
		            unset($recent_records[$track]);
		            continue;
		        }
		
		        $rt_info = &MVC_Core::$cache-> record_types[MVC_Core::$cache-> record_types_index[$data['ist']]];
		        $datasources['recent'][] = array_merge($data, array(
		            'track'=> $track,
		            'rt_key'=> $rt_info['key'],
		            'rt_name'=> $rt_info['name'],
					'pri'=>$recent_records[$track]['pri']
		        ));
		    }
		}
		$this-> recent_issues_count = count($recent_records); 
		
		
		// Read the Mini-Notepad data
        
        /*removed by castus */
		//$this-> mini_notepad = htmlspecialchars($this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "mini_notepad"));
		
		// Read list of issues for the "Recent Issues" panel.
		$this-> prepare_Template_ACLs();
		
		// User elements count
		$this-> count = MVC_Core::$ss-> user['count'];	
		// Hybrid response (HTML+JSON)
		
		$this-> send_JSON_Headers(array(
			'tab_dashboard'=> array(
				'rtlist_dashboard'=> $issue_Details,
				// Extract some basic information about active record type
				'current_rt'=> array(
					'id'=> MVC_Core::$cache-> current_rt['id'],
					'key'=> MVC_Core::$cache-> current_rt['key'],
					'name'=> MVC_Core::$cache-> current_rt['name']
				)
			), 
			"dataSources"=> $datasources
		));
	 }
	/*Dayakar code end */
	public function load_Tab_History(MVC_Request $in){
		/*for recent records*/
		
		
		$this-> current_rt = MVC_Core::$cache-> current_rt;
		
		$datasources = array();
		// Recently opened records - Add ist information (key, name)
		$recent_records = $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "iss_recent", false, 1, true);
		if(!empty($recent_records)) {
		    $datasources['recent'] = array();
		    foreach($recent_records as $track=> $data) {
		        if(!isset(MVC_Core::$cache-> record_types_index[$data['ist']])) {
		            unset($recent_records[$track]);
		            continue;
		        }
		
		        $rt_info = &MVC_Core::$cache-> record_types[MVC_Core::$cache-> record_types_index[$data['ist']]];
		        $datasources['recent'][] = array_merge($data, array(
		            'track'=> $track,
		            'rt_key'=> $rt_info['key'],
		            'rt_name'=> $rt_info['name'],
					'pri'=>$recent_records[$track]['pri']
		        ));
		    }
		}
		$this-> recent_issues_count = count($recent_records);
		
		$this-> send_JSON_Headers(array(
			'tab_dashboard'=> array(
				'rtlist_dashboard'=> array_merge($rt, $rt_disabled),
				// Extract some basic information about active record type
				'current_rt'=> array(
					'id'=> MVC_Core::$cache-> current_rt['id'],
					'key'=> MVC_Core::$cache-> current_rt['key'],
					'name'=> MVC_Core::$cache-> current_rt['name']
				)
			), 
			"dataSources"=> $datasources
		));
	}
	/**
	 * Tab #dashboard - Mini Notepad
	 * @param MVC_Request $rq
	 */
	public function action_Save_MiniNotepad(MVC_Request $rq)
	{
		$r = $this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "mini_notepad", $rq-> content);
		if(!$r)
			return header("HTTP/1.0 500 Notepad could not be saved. Possible database error!");

		echo "1";
	}

	/**
	 * Tab #modules - Now enhanced with organization record statistics
	 * @param MVC_Request $in
	 */
	private function load_Tab_Modules(MVC_Request $in)
	{
		// Extract some information about active record type
		$current_rt = array(

		);

		$rt_r = $this-> issue-> ist_Get_List();
		$acls_rt = isset(MVC_Core::$ss-> user['acls']['ist'])? MVC_Core::$ss-> user['acls']['ist']: true;

		$rt = array(); $rt_disabled = array();
		foreach($rt_r as $row) {
			$row['id'] = (int)$row['id'];

			// Compare with user's access rights?
			if($acls_rt === true)
				$row['yep'] = true;
			elseif(in_array($row['id'], $acls_rt))
				$row['yep'] = true;

			if(isset($row['yep']))
				$rt[] = $row;
			else
				$rt_disabled[] = $row;
		}

		// Hybrid response (HTML+JSON)
		$this-> send_JSON_Headers(array(
			'tab_modules'=> array(
				'rtlist'=> array_merge($rt, $rt_disabled),
				// Extract some basic information about active record type
				'current_rt'=> array(
					'id'=> MVC_Core::$cache-> current_rt['id'],
					'key'=> MVC_Core::$cache-> current_rt['key'],
					'name'=> MVC_Core::$cache-> current_rt['name']
				)
			)
		));
	}

	/**
	 * Loads information about a record type as a html page.
	 * @param MVC_Request $rq
	 */
	public function action_Load_RT(MVC_Request $rq)
	{
		// Determine if the record type is valid for the logged in user or not. Functions ( submit, list ) are not available if the record type is not available
		$acls_rt = $this-> user-> read_ACL('ist');
		if($acls_rt === true || in_array($rq-> rt_id, $acls_rt))
			$this-> enabled = $rt_enabled = true; // Access to the module

		// Hybrid response
		$data_response = array();

		// Top-Right Function buttons
		$functions = array();
		$functions['submit'] = true;
		$functions['list'] = true;

		// Read record type details
		$rt_def = $this-> issue-> ist_Read($rq-> rt_id, null);
		$this-> rt = $data_response['rt_def'] = array(
			'id'=> (int)$rt_def['id'],
			'key'=> $rt_def['key'],
			'name'=> $rt_def['name'],
			'plural'=> $rt_def['short_plural']
		);
		// Also load the matching CMS page for the record type key (might contain detailed description, screenshots, etc.)
		$m_cms = new App_Model_CMS();
		$rtinfo_cmsp = $m_cms-> read("/rtinfo/". $rt_def['key']);
		$this->_out['rt']['info'] = !empty($rtinfo_cmsp)? $rtinfo_cmsp['contents']: $rt_def['description'];

		// If enabled, build statistics information and chart configuration
		if(!empty($rt_enabled) && !empty($rt_def['settings']['plugin']) && !empty($rt_def['settings']['statistics'])) {
			// The statistics are calculated for logged in user (but it should support admin's Perspective as well, maybe later)
			$org_id = MVC_Core::$ss-> user['org_id'];

			$data_response['charts'] = array(); // Just a list, used in loops
			$data_response['charts_config'] = array(); // Will contain actual display configuration data + statistics (note: Can get quite big !)
			$this-> stx = $this-> factory_Model("Statistics", "stx");
			$this-> rec_plugin = MVC_Core::factory_Plugin("record", $rt_def['key']);

			// User preferences. If "FALSE", it means no preferences have been saved and all charts have to be enabled
			$charts_prefs = $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "charts_".$rt_def['key']);
			if($charts_prefs === false)
				$charts_prefs = $rt_def['settings']['statistics_methods']; // Default to all active and enabled, and in the original order
			foreach($rt_def['settings']['statistics_methods'] as $model) {

				$visible = in_array($model, $charts_prefs);

				// Read statistics for organization/record type - Caching is built into the function
				$stats = $this-> rec_plugin-> org_Statistics($org_id, $rt_def['id'], $model, array(
					'period'=> $rq-> period?:"months",	// Start with months as default (Last twelve months) - Should be configurable !
					'subset'=> $rq-> subset?:0			// Start with "Show all" as default (No specific subset, 0)
				), $visible); // Last parameter = Visibility ; Non-visible charts are only silently checked for errors in the configuration
				if($visible) {
					if(empty($stats))
						continue; // Stats not found ? Skip the chart completely

					// Test 2: Get configuration
					$chart = $this-> rec_plugin-> build_Chart_Config($model, array(
						'org_id'=> $org_id,
						'rt_id'=> $rt_def['id']
					));
					$chart['source'] = $stats;

					// Append data for each correct chart
					$data_response['charts'][] = $model;
					$data_response['charts_config'][$model] = array(
						'name'=> $this-> rec_plugin-> get_Chart_name($model),
						'visible'=> true,
						'chart'=> $chart,
						'controls'=> array(
							'process'=> $model != "total_records",
							'range'=> array("Months", "Quarters", "Years"),
	// 						'dump'=> true, // Aka "Export to Excel" - Unfortunately, this doesn't exist ... A manual exporting of data has to be built - Universal ExcelXML generator function maybe?
							'image'=> true,// PNG Export (built into jqxChart, needs HTML5)
							'print'=> true // Printing (built into jqxChart, needs HTML5
						)
					);
				} else {
					// When non-visible, most parts are skipped - Only basic information used by the Charts Config is sent
					$data_response['charts'][] = $model;
					$data_response['charts_config'][$model] = array(
						'name'=> $this-> rec_plugin-> get_Chart_name($model),
						'visible'=> false
					);
				}
			}

			// End charts - Do we have anything to show ? Enable the function
			if(!empty($data_response['charts']))
				$functions['charts'] = true;

			// Weird array processing that puts the ones in $charts_prefs first, then appends the ones which are not part of $charts_prefs at the end)
			$data_response['charts'] = array_merge(array_intersect($charts_prefs, $data_response['charts']), array_diff($data_response['charts'], $charts_prefs));
		}
		$this-> functions = $data_response['functions'] = $functions;

		// Finished, send the response
		$this-> send_JSON_Headers($data_response);
		$this-> init_Template_System();
		$this-> view("includes". DS. "rt_info.html");
	}

	/**
	 * Loads the chart configuration and data for a certain statistics model of a record type
	 * - Uses the record plugin to access certain data
	 * - Chart configuration and dataset are returned with the same response
	 * This method also caches the data for the specified parameters and saves it using the "Statistics" model (which needs a bit enhancing)
	 * - If same parameters are asked, it returns data from the statistics cache
	 *
	 * TODO: Allow customization of initial parameters (time period and what to show - for certain charts)
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Load_Record_Statistics_Chart(MVC_Request $rq)
	{
		// The statistics are calculated for logged in user (but it should support admin's Perspective as well, maybe later)
		$org_id = MVC_Core::$ss-> user['org_id'];

		// Attempt to locate record type and find it's numerical ID (used for the database queries)
		$rt_id = $this-> issue-> ist_Locate($rq-> rt_key);
		if(empty($rt_id))
			return header("HTTP/1.0 404 Request contains invalid record type");

		$this-> rec_plugin = MVC_Core::factory_Plugin("record", $rq-> rt_key);

		// Read statistics for organization/record type - Caching is built into the function
		$stats = $this-> rec_plugin-> org_Statistics($org_id, $rt_id, $rq-> model, array(
			'period'=> $rq-> period?:"months",	// Start with month as default (Last twelve months)
			'subset'=> $rq-> subset?:0			// Start with 0 as default (subsets are numeric indexes), and they might represent specific references for the statistic functions
		));
		if(empty($stats))
			return header("HTTP/1.0 500 Statistics could not be calculated. Cause unknown");

		// Test 2: Get configuration
		$chart = $this-> rec_plugin-> build_Chart_Config($rq-> model, array(
			'org_id'=> $org_id,
			'rt_id'=> $rt_id
		));

		$chart['source'] = $stats;
		return $this-> send_JSON($chart);
	}

	/**
	 * Saves the configuration for showing charts on a certain record type, per user. When done, the charts RT page is reloaded
	 * @param MVC_Request $rq
	 */
	public function action_Save_Charts_Config(MVC_Request $rq)
	{
		$r = $this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "charts_".$rq-> rt_key, explode(",", $rq-> charts));
		if(!$r)
			return header("HTTP/1.0 500 Unable to save charts configuration");

		return $this-> send_JSON(array('success'=> true));
	}

	/**
	 * The "Account" contains primary information like username, e-mail, ability to change password, active department, statistics and Two-Factor authentication
	 *
	 * @param MVC_Request $in
	 */
	private function load_Tab_Account(MVC_Request $in)
	{
		$features = array();
		$datasources = array();

		// Special ability for "jumper" users (support accounts, which can "jump" to other organizations) - Read info about the organizations they can jump to (profile key "cynosural")
		if(MVC_Core::$ss-> user['status'] == 'jumper') {
			$cynos = $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "cynosural"); // this has to be entered in the database directly, it cannot be changed in the interface

			$datasources['jump_orgs'] = $this-> org-> get_List(array('orgtype'), function(QueryFactory &$q) use($cynos) {
				$q-> where("`org`.id IN (?)", $cynos);
			});

			$features['update_membership'] = true;
			$features['org_selector'] = true;
		}

		$features['twofa'] = !empty(MVC_Core::$ss-> user['oath_turnon'])? MVC_Core::$ss-> user['oath_turnon']: false;

		// Template vars
		$this->_out['session'] = &MVC_Core::$ss;
		$this-> features = $features;

		// Hybrid response (HTML+JSON)
		$this-> send_JSON_Headers(array(
			'features'=> $features,
			'user'=> array(
				'id'=> (int)MVC_Core::$ss-> user['id'],
				'org_id'=> (int)MVC_Core::$ss-> user['org_id'],
				'cdep_id'=> (int)MVC_Core::$ss-> user['cdep_id'],
				'email'=> MVC_Core::$ss-> user['email']
			),
			'dataSources'=> $datasources // => self.config.dataSources
		));
	}

	/**
	 * Reads the list of departments this user is a member of, for the department selector source - Used in My Account tab
	 * Might be returning empty array if the user is not a member of any department in the specified organization
	 *
	 * @param MVC_Core $request
	 */
	public function action_Get_User_Deps(MVC_Request $rq)
	{
		$org_id = $rq-> org_id?: MVC_Core::$ss-> user['org_id'];

		// Read the list of departments the user is a member of, for the active department selector
		$m_dep = new App_Model_Department();
		$deps = $m_dep-> get_User_Dep_Relation_List(MVC_Core::$ss-> user['id'], $org_id, false); // false = quick mode (id, name)

		// In case no departements were returned, it means that that the user is not member of any yet - nothing to select, do not show the department selector
		if(empty($deps))
			return $this-> send_JSON(array());

		$user_deps = array();
		foreach($deps as $dep) {
			$user_deps[] = array(
				'id'=> $dep['id'],
				'name'=> $dep['name'],
				'disabled'=> $dep['disabled']
			);
		}

		$this-> send_JSON($user_deps);
	}

	/**
	 * Loads user's contact information - This is synchronized in JavaScript with the "My account" tab (change of name and e-mail happens in both places)
	 *
	 * @param MVC_Request $in
	 */
	private function load_Tab_Vcard(MVC_Request $in)
	{
		$contact = $this-> con-> read(MVC_Core::$ss-> user['contact_id']);
		if(!$contact) // Not found... WTF!?
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

			// TODO: Defaults (but "tested" is not enabled, the defaults will only show if the user actually clicks on "Yes")

			// TODO: Guess based on E-mail and a "guess" config file

			// If nothing, just default to: smtp.(user's domain),TLS/587/user's e-mail as "username"
			$smtp_server = array(
				'method'=> "local",
				'tested'=> 0,
				'host'=> "smtp.".$domain,
				'smtp_encryption'=> "tls",
				'port'=> 587,
				'smtp_auth'=> "login",
				'username'=> $contact['email'],
			);
		}

		$this-> smtp = $smtp_server;

		// Sends to the frontend the fact that the contact has loaded correctly and the contact editor can be initialized
		$data_response = array();
		$data_response['tab_vcard'] = array(
			'ready'=> true,
			'send_mtd'=> $smtp_server['method'],
			'smtp_active'=> (int)$smtp_server['tested']==1
		);

		// Hybrid response (HTML+JSON)
		$this-> send_JSON_Headers($data_response);
	}

	/**
	 * Loads and prepares HTML document which will become the content of one of the tabs for the User Overview module
	 * -- Loads specific data on demand, for the dynamic content in that tab.
	 *
	 * @param MVC_Request $in
	 */
	public function action_Load_Tab_HTML(MVC_Request $in)
	{
		// Group depenent data which might be needed for the template (nothing yet)
		switch($in-> tab) {
			// Dashboard/Cockpit/Bridge
			case 'tab_dashboard':
				/* Dayakar code start */
					//$this-> load_Tab_Dashboard($in);
					$this-> load_Tab_Dashboard_cockpit($in);
					$m_cms = new App_Model_Issue();
					$this->rec_iss=$m_cms->get_rec_list();
				/* Dayakar code end */
				break;
			// Statistics tab, lists all available modules in the system, and for those that are enabled charts are being displayed with organizzation statistics
			// This will most likely be moved to Organization module, or it's own independent controller
			case 'tab_modules':
				$this-> load_Tab_Modules($in);
				break;

			// Account information (username, password, signature, organization, department)
			case 'tab_account':
				$this-> load_Tab_Account($in);
				break;

			// Contact viewer/editor for the selected user
			case 'tab_vcard':
				$this-> load_Tab_Vcard($in);
				break;

			case 'tab_acls':
				// translate the privilege set in MVC_Core::$ss-> user['acls']['privileges'], to be displayed in a list
				if($this-> user-> is_Unrestricted())
					$this-> acls = array('is_admin' => true);
				else {
					// Assamble the correct list of ACLs (privileges, access ids):
					MVC_NLS::load_Groups("acls");
					$acls_text = MVC_NLS::get_Object("acls", true);

					$acls = array();
					// Privileges (and translated text)
					$acls['privileges'] = array();
					foreach(MVC_Core::$ss-> user['acls']['privileges'] as $privilege)
						$acls['privileges'][$privilege] = $acls_text['privileges'][$privilege];

					$this-> acls = $acls;
					$this-> privilege_groups = $acls_text['privilege_groups'];
				}
				break;
			case 'tab_history':
				$m_cms = new App_Model_Issue();
				$this->rec_iss=$m_cms->get_rec_list();
				$this-> load_Tab_History($in);
			case 'tab_updates':
				// Managed updates (this page becomes a blog system)
				$m_cms = new App_Model_CMS();
				$this-> updates = $m_cms-> blog_Read(function(QueryFactory &$q) {
					$q-> where("`org_id` IS NULL");
				}, null, "`dt` DESC", 25, 0);
				break;
		}

		// This is an AJAX request, but needs the template as it's a HTML response (not JSON or XML), so, initialize the template object on demand
		$this-> init_Template_System();
		$this-> view("user". DS. "overview_tabs". DS.  $in-> tab. ".html", $this->_out);
	}

	/**
	 * Updates the active logged in user's personal data
	 */
	public function action_Update_Account(MVC_Request $post)
	{
		// If 2FA is active and the OTP value is missing from the form, return a special trigger for the Javascript so the pin is asked.
		// The form will then be resubmitted with the complete data, in which case the OTP is checked against the user's key
		if(MVC_Core::$ss-> user['oath_turnon'] && !empty($post-> usr['password'])) {
			if(!$post-> otp)
				return $this-> send_JSON(array('twofa'=> true));
			else {
				$otp_valid = $this-> user-> test_2FA_OTP($post-> otp);
				if(!$otp_valid)
					return $this-> send_Fail_JSON("One-Time Password invalid. Please try again or contact support");
			}
		}

		$account = MVC_Core::$ss-> user;
		$new_account_data = $post-> usr;
		$new_account_data['user'] = $account['user']; // Necessary for security hash
		unset($new_account_data['name'], $new_account_data['email']);
// Disabled, not possible to change base account info using this interface (signature and password can still be changed)
/*

		$new_account_data['name'] = trim($new_account_data['name']);
		$new_account_data['email'] = trim($new_account_data['email']);

		// Create or Update the associated contact with information from $post-> usr (name, email)
		if(!$account['contact_id']) {
			// There is no associated contact, create one.
			$con_iid = $this-> con-> create('user_self', $account['org_id'], array(
				'name'=> $new_account_data['name'],
				'email'=> $new_account_data['email']
			), $account['id']);

			if(!$con_iid)
				return $this-> send_Fail_JSON("Unable to create the associated contact information for this user.");

			$account['contact_id'] = $con_iid;
		} else {
			// The associated contact exists, so update it
			$r = $this-> con-> update($account['contact_id'], array(
				'name'=> $new_account_data['name'],
				'email'=> $new_account_data['email']
			));

			if(!$r)
				return $this-> send_Fail_JSON("Unable to update the associated contact information for this user.");
		}
*/

		$res = $this-> user-> update(MVC_Core::$ss-> user['id'], $new_account_data);
		if($res) {
			$this-> evan-> add("Your account information has been updated.", "ok", array(
				'permanent'=> true,
				'timeout'=> time()+ 30
			));

			// update the user's data in the session as well before returning success, so when reloading the page the updated name is displayed.
// 			MVC_Core::$ss-> user['name'] = $new_account_data['name'];
// 			MVC_Core::$ss-> user['email'] = $new_account_data['email'];
			MVC_Core::$ss-> user['signature'] = $new_account_data['signature'];

			$this-> send_JSON(array('success'=> true));
		} else
			$this-> send_Fail_JSON("There was an error in updating your account.");
	}

	/**
	 * Updates the active user's membership ( department and for certain users, the organization ID itself )
	 */
	public function action_Update_Membership(MVC_Request $post)
	{
		$new_account_data = $post-> usr;
		if(empty($new_account_data['cdep_id']))
			$new_account_data['cdep_id'] = 0;

		if(MVC_Core::$ss-> user['status'] == 'jumper') {
			// Only "Jumpers" have the ability to move to different organizations by the use of this action.
			if($new_account_data['org_id'] == MVC_Core::$ss-> user['org_id'] && $new_account_data['cdep_id'] == MVC_Core::$ss-> user['cdep_id'])
				return header("HTTP/1.0 400 No change in organization or department, nothing to do");
		} else {
			// Just in case there's a "hacking" attempt
			unset($new_account_data['org_id']);
			if ($new_account_data['cdep_id'] == MVC_Core::$ss-> user['cdep_id'])
				return header("HTTP/1.0 400 No change in active department, nothing to do");
		}

		// TODO: At this point it would be good to verify again if the user is actually a member of that organization/department and that his department membership is not disabled
		$res = $this-> user-> update(MVC_Core::$ss-> user['id'], $new_account_data);
		if(empty($res))
			return header("HTTP/1.0 500 There was an error while updating your membership");

		$this-> evan-> add("Your membership has been changed and you will be automatically logged out.", "ok");
		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Reads the groups that the user has access to and personal preferences related to each one of them (if they exist, or empty data)
	 */
	public function action_Get_User_Groups()
	{
		// Quick fix for admin - Does not see groups
		if($this-> user-> is_Unrestricted())
			return $this-> send_JSON(array());

		// TODO: Later, all existing groups should be displayed here, allowing the admin to be notified as well... ?

		$group_list = $this-> user-> user2group_Get_List(MVC_Core::$ss-> user['id'], array_keys(MVC_Core::$ss-> user['acls']['groups']));

		// because of some bugs in the grid system related to checkbox column, initialize all "missing" values
		foreach($group_list as &$row) {
			if(empty($row['preferences']))
				$row['preferences'] = array(
					'ntf_new'=> false,
					'ntf_trans'=> false,
					'ntf_share'=> false,
					'ntf_clos'=> false,
					'hidden'=> false
				);
			else { // preferences not empty, but some values are missing (database content bug)
				if(!is_array($row['preferences']))
					$row['preferences'] = array();
				if(!isset($row['preferences']['ntf_new']))
					$row['preferences']['ntf_new'] = false;
				if(!isset($row['preferences']['ntf_trans']))
					$row['preferences']['ntf_trans'] = false;
				if(!isset($row['preferences']['ntf_share']))
					$row['preferences']['ntf_share'] = false;
				if(!isset($row['preferences']['ntf_clos']))
					$row['preferences']['ntf_clos'] = false;
				if(!isset($row['preferences']['hidden']))
					$row['preferences']['hidden'] = false;
			}
		}

		if($group_list)
			$this-> send_JSON($group_list);
		else
			$this-> send_JSON(array());
	}

	/**
	 * Reads the list of notifications for the logged in user, and returns the result as grid data.
	 * -- Used to display and search through all notifications.
	 * -- By default, sorted by date, newer first
	 */
	public function action_Get_Notifications(MVC_Request $in)
	{
		// 1. Server-side Filters
		list($ss_filters, $filter_callback) = self::helper_Grid_Filters($in);

		// 2. Server-side Sort
		if($in-> sortdatafield && $in-> sortorder) {
			// Determine the database field used for sorting
// 			if($in-> sortdatafield == 'org_name')
// 				$ss_sort = "`org`.`name` ";
// 			elseif($in-> sortdatafield == 'company_name')
// 			$ss_sort = null; // Sorting should not be possible here
// 			else
// 				$ss_sort = "`ctx`.`{$in-> sortdatafield}` ";
			$ss_sort = "`evn`.`{$in-> sortdatafield}` ".$in-> sortorder;
		} else {
			$ss_sort = "`evn`.`time` DESC"; // default sorting by date, descending
		}

		$notifications = $this-> evan-> get_List(array('notification'), function(QueryFactory $q) use ($ss_filters, $filter_callback) {
			$q-> where("`evn`.`user_id` = ?", MVC_Core::$ss-> user['id']);
			foreach($ss_filters as $fkey=> $filter_set) {
				$dbfield = "`evn`.`{$fkey}` ";
				$filter_callback($q, $filter_set, $dbfield);
			}
		}, null, $ss_sort, $in-> pagesize, $in-> pagenum * $in-> pagesize, true); // true = calc found rows (for server side paging)

		if(empty($notifications))
			return $this-> send_JSON(array()); // empty result ???

		// Filter out some data which is not needed by the grid itself
		foreach($notifications as &$item)
			unset($item['user_id'], $item['xdata'], $item['description']);

		$this-> send_JSON(array(
			"found_rows"=> $this-> evan-> found_rows,
			"data"=> $notifications
		));
	}

	/**
	 * Generates a full-feature HTML version of a notification.
	 * -- Also updates status to "read" if it was "new" before. Ignores it otherwise
	 *
	 * @param MVC_Request $in
	 */
	public function action_Read_Notification(MVC_Request $in)
	{
		if(!isset($in-> id)) {
			echo "Error: Notification ID missing from the request!";
			return;
		}

		// Attempt to read the notification
		$ntf = $this-> evan-> read($in-> id, array('notification', 'rel_item'));

		if($ntf['status'] == 'new') {
			$this-> evan-> update($in-> id, 'read');
			// Trigger the re-reading of notifications by the heart-beat by setting "check_evn" in user's session
			MVC_Core::$ss-> user['check_evn'] = true;
		}

		if(empty($ntf['description'])) {
			$ntf['description'] = "";
			// When there is no stored description, build one depending on notification type - Note: This is temporary, templates should be used instead.
			switch($ntf['type']) {
				case 'issue':
					$ntf['description'].="Type: {$ntf['xdata']['type']}\n";
					$ntf['description'].="Primary data: {$ntf['xdata']['primary']}\n";
					$ntf['description'].="Secondary data: {$ntf['xdata']['secondary']}";
					break;
			}
		}

		$this-> ntf = $ntf;

		// This is an ajax request, but with a HTML response, so initialize the template object on demand
		$this-> init_Template_System();
		$this-> view("includes". DS. "notification.html");
	}

	/**
	 * Dismisses or achives a notification
	 *
	 * @param MVC_Request $post
	 */
	public function action_Dismiss_Notification(MVC_Request $post)
	{
		if(!isset($post-> id))
			return $this-> send_Fail_JSON("Notification ID not present in the request.");

		// Attempt to read the notification to check it's current status
		$ntf = $this-> evan-> read($post-> id, array('notification'));
		if(!$ntf)
			return $this-> send_Fail_JSON("Notification not found (Possible database error).");

		$new_status = isset($post-> hideit)? 'archived': 'dismissed';
		if($new_status == 'archived'? $ntf['status'] != 'archived': ($ntf['status'] != 'archived' && $ntf['status'] != 'dismissed')) {
			$this-> evan-> update($post-> id, $new_status);
			// Trigger the re-reading of notifications by the heart-beat by setting "check_evn" in user's session
			MVC_Core::$ss-> user['check_evn'] = true;
		} else
			return $this-> send_Fail_JSON("This notification has already been {$ntf['status']} !");

		return $this-> send_JSON(array('success'=> true, 'response'=> "The notification has been dismissed."));
	}

	/**
	 * Reads the related data for a notification ( if it exists ) and sends it via JSON - Used to open or process the item
	 *
	 * @param MVC_Request $in
	 */
	public function action_Read_Ntf_Related(MVC_Request $in)
	{
		if(!isset($in-> id))
			return $this-> send_Fail_JSON("Notification ID not present in the request.");

		$ntf = $this-> evan-> read($in-> id, array('notification', 'rel_item'));
		if(!$ntf)
			return $this-> send_Fail_JSON("Notification not found (Possible database error).");

		return $this-> send_JSON(array('success'=> true, 'item'=> $ntf['item']));
	}

	/**
	 * Changes the status of all New and Read notifications belonging to the logged in user to "dismissed"
	 *
	 * @param MVC_Request $post
	 */
	public function action_Dismiss_All_Notifications(MVC_Request $post)
	{
		if(!isset(MVC_Core::$ss-> user))
			$this-> send_Fail_JSON("This function is available for user accounts only.");

		$count = $this-> evan-> update_Bulk(MVC_Core::$ss-> user['id'], function(QueryFactory &$q) {
			$q	-> where('`status` IN(?)', array('new', 'read'))
				-> values(array('status'=> "dismissed"));
		});

		if($count > 0) {
			$this-> evan-> reset();
			$this-> evan-> add("{$count} notifications have been dismissed", 'ok', array(
				'permanent'=> true,
				'timeout'=> time()+ 15
			));
			$this-> send_JSON(array('success'=> true));
		} else
			$this-> send_Fail_JSON("No notifications to dismiss or an error has occured.");
	}

	/**
	 * Saves the group settings
	 * (a whole bunch of checkboxes in a N2N relationship between users and groups)
	 */
	public function action_Save_Groups_Settings(MVC_Request $post)
	{
		$settings_data = @json_decode($post-> data, true);
		if($settings_data) {

			foreach($settings_data as $group_id=> $data) {
				// extract preferences
				$preferences = array();
				foreach($data as $key=> $value) {
					if(substr($key, 0, 5) == 'pref_')
						$preferences[substr($key, 5)] = $value;
				}
				// create or update preferences
				$this-> user-> user2group_Write(MVC_Core::$ss-> user['id'], $group_id, array(
					'preferences'=> $preferences
				));
			}

			$this-> send_JSON(array('success'=> true));
		} else {
			$this-> send_Fail_JSON("Nothing to save !");
		}
	}

	/**
	 * Removes the list of recently viewed issues from the profile memory
	 * @param MVC_Request $post
	 */
	public function action_Issues_Recent_Clear(MVC_Request $post)
	{
		$this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "iss_recent");
		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Removes one element from the recent issues list and updates the profile memory
	 * @param MVC_Request $post
	 */
	public function action_Issues_Recent_Remove_Item(MVC_Request $post)
	{
		// Load the user profile containing the issue open history
		$recent = $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "iss_recent", false, 1, true);
		if(empty($recent))
			return $this-> send_Fail_JSON("The recent issues list is already empty !");

		// go through the profile data and find the track id. If found, it is removed from the list and the list is saved. If not, return an error
		$pos = 0; $found = false;
		foreach($recent as $track=> $data) {
			if($track == $post-> track) {
				// Found it !
				unset($recent[$track]);
				$found = true;
				break;
			}
			$pos++;
		}
		if(!$found)
			return $this-> send_Fail_JSON("Track ID {$post-> track} not found in the recent list ?!");

		if(count($recent)) {
			// Save it back to the user profile record
			$first = reset($recent);
			$this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "iss_recent", $recent, false, sprintf("Recently opened issues (Most recent time: %s)", date("Y-m-d H:i:s", $first['time'])));
		} else
			// If no more left, just remove the record itself
			$this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "iss_recent");

		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Checks if the entered code for Two-Factor authentication matches the calculated value on the server.
	 * If yes, saves the OathKey in the user account which enables 2FA.
	 *
	 * @param MVC_Request $post
	 */
	public function action_Check_2FA(MVC_Request $post)
	{
		if(!$post-> oathkey || strlen($post-> oathkey)!=16 || !$post-> otp || strlen($post-> otp)!=6)
			return $this-> send_Fail_JSON("Invalid parameters for 2FA activation request.");

		GA2FA::initialize();
		$valid = GA2FA::verify_key($post-> oathkey, $post-> otp);

		if(!$valid)
			return $this-> send_Fail_JSON("The PIN is invalid. In case you keep getting this error please contact support.");

		// PIN Valid ! Activate 2FA by storing the oathkey into the user account. At next login the user will be asked to enter the PIN
		MVC_Core::$ss-> user['oath_key'] = $post-> oathkey;
		MVC_Core::$ss-> user['oath_turnon'] = date("Y-m-d H:i:s");
		$this-> user-> update(MVC_Core::$ss-> user['id'], array(
			'oath_key'=> $post-> oathkey,
			'oath_turnon'=> MVC_Core::$ss-> user['oath_turnon']
		));

		$this-> evan-> add("Two-Factor Authentication has been added to your account !", "2fa", array(
			'permanent'=> true,
			'timeout'=> time()+ 60
		));
		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Test action for the new notification system.
	 */
	public function action_Self_Notify()
	{
		$ntf_id = $this-> evan-> create(MVC_Core::$ss-> user['id'], 'system', 'test', "Self notification test", null, null, null, array("hello"=> "World!"), "Lorem ipsum dolor sic transit gloria mundi");

		if(empty($ntf_id))
			return $this-> send_Fail_JSON("Unable to create notification");

		$msg_id = $this-> evan-> generate_Message($ntf_id, true); // True = Send by mail

		if(empty($msg_id))
			return $this-> send_Fail_JSON("Unable to send myself the message ;)");

		$this-> send_JSON(array('success'=> true, 'response'=> "It Worked. Yey!"));
	}

	/**
	 * Test action for the new notification system.
	 */
	public function action_Last_As_Mail()
	{
		$ntfs = $this-> evan-> get_List(array(), function(QueryFactory $q) {
			$q	-> where("`evn`.`user_id` = ?", MVC_Core::$ss-> user['id'])
				-> limit(1);
		}, array(), "`evn`.`time` DESC", 1);

		$ntf_id = @$ntfs[0]['id'];

		if(empty($ntf_id))
			return $this-> send_Fail_JSON("Unable to find notification");

		$msg_id = $this-> evan-> generate_Message($ntf_id, true); // True = Send by mail

		if(empty($msg_id))
			return $this-> send_Fail_JSON("Unable to send myself the message ;)");

		$this-> send_JSON(array('success'=> true, 'response'=> "It Worked. Yey!"));
	}

}