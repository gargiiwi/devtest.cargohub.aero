<?php
/**
 * Primary Records Search and Operations - Lists and finds the records as a searchable grid
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2020.11.17
 * For accessing the data from any external app or for creating your own external API use mysql-services
 */
class App_Ctrl_Issue_List extends App_Ctrl_Base
{
	/**
	 * Reference to the Grid View Model - Helps dev by providing autocompletion (Only initialized for certain methods)
	 * @var App_Model_GridView
	 */  
	protected $gridview;

	/**   
	 * Reference to the Data Group Model 
	 * @var App_Model_Group 
	 */  
	protected $group;  
                                                
	/**
	 * The Record-Type Definition (identical to the cached content from $issue-> ist) - This is actually a reference to $this-> current_rt
	 * @var array
	 */ 
	protected $current_rt; 

	/**
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct(true);

		// Controller specific models
		$this-> record = $this-> factory_Model('Issue', "record");
		$this-> current_rt = MVC_Core::$cache-> current_rt;
		// echo "org_id : ".MVC_Core::$ss->user['org_id'];
		// echo "<pre>42";
		// print_r($_SESSION);
	}

	public static $_module_name = "Records Grid";

	/**
	 * Temporary view convert function - Happens once when user loads a record type - Then all their views are loaded from the new format
	 * @param array $old_view_index
	 */
	protected function convert_View($old_view_index)
	{
		$rt_id = $this-> current_rt['id'];
		$view = array(
			'name'=> $old_view_index['pname'],
			'utime'=> $old_view_index['utime'],
			'rt_key'=> $this-> current_rt['key'],
			'link_id'=> MVC_Core::$ss-> user['id'],
			'columns'=> $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "columns_ist_{$rt_id}", true, $old_view_index['variant'], false),
			'state'=> $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "grid_state_{$rt_id}", true, $old_view_index['variant'], false)?:null
		);
        
		// Create the view
		$id = $this-> gridview-> create(MVC_Core::$ss-> user['org_id'], $view);
		// ... and delete the old variants (two records)
		$this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "columns_ist_{$rt_id}", null, false, null, $old_view_index['variant']);
		$this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "grid_state_{$rt_id}", null, false, null, $old_view_index['variant']);
	}

	/**
	 * Loads and Initializes all persistent grid parameters with information from a view
	 * @param array $view
	 */
	protected function initialize_View($view_id)
	{
		if(empty($this-> gridview))
			$this-> gridview = $this-> factory_Model('GridView');

		$view = $this-> gridview-> read($view_id);
		
		
		if(empty($view))
			return MVC_Core::$debug-> dump("View $view_id not found", __METHOD__, null, 'error');

		// Step 1 - Copy columns to profile (This should not be needed once views are fully used - Instead the columns will be in the view itself)
		$this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "columns_ist_".$this-> current_rt['id'], $view['columns'], true, sprintf("Loaded from View %d", $view_id), 1);

		// Step 2 - Load grid state from view
		if(!empty($view['state'])) {
			// Save the state into the session
			if(!isset(MVC_Core::$cache-> grid_state))
				MVC_Core::$cache-> grid_state = array();
			MVC_Core::$cache-> grid_state[$this-> current_rt['key']] = @json_decode($view['state'], true);
		}

		// Step 3 - Set the Special Settings if they exist in the view
		if(!empty($view['width']) || !empty($view['autoref_s']) || !empty($view['hide_trackid'])) {
			MVC_Core::$cache-> grid_ss = array(
				'width'=> (int)$view['width'],
				'autoref_s'=> (int)$view['autoref_s'],
				'hide_trackid'=> (bool)$view['hide_trackid']
			);
		}

		// Step 4 - Save some information about the view into the session, so current active view can be displayed to the user
		if(empty(MVC_Core::$cache-> view_info))
			MVC_Core::$cache-> view_info = array();
		if(!empty($this-> current_rt['key']))
			MVC_Core::$cache-> view_info[$this-> current_rt['key']] = sprintf("Using view <b title='(last updated on %s)'>%s</b>", $view['utime'], $view['name']);
		return $view;
	}

	/**
	 * Index action for the Issues display and management page
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Index(MVC_Request $rq)
	{
            
        if (isset($_GET['org_id'])) {
            $_SESSION['org_id'] = $_GET['org_id'];
        }
        if (!isset($_GET['org_id']) && isset($_SESSION['org_id'])) {
            header("Location: /issue/list?org_id=" . $_SESSION['org_id']);
            exit;
        }
        
        
		// Test ACL for record overview grid (This can be used as a "submit only" restriction, as not allowing access to the grid prevents a person from finding data)
		if(!$this-> user-> test_ACL('privileges','issues_view')) {
			$this-> evan-> add("You are not allowed to view records.", 'lock');
			$this-> redirect($this-> CFG['home']);
		}

		$this-> CFG['components'] = array('modal');
		$this-> CFG['modules'][] = "issue/_list";
		$this-> CFG['jqwidgets'][] = array("buttongroup", "switchbutton", "scrollbar", "expander", "slider"); // Slice 2 and 3 - Stuff
		$this-> CFG['jqwidgets'][] = array("listbox", "dropdownlist", "combobox", "dragdrop", "calendar", "datetimeinput");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete"); // Slices 4 and 5 - Grid and some grid plugins
		$this-> CFG['jqwidgets'][] = array("data.export", "grid.export"); // "grid.storage"
		$this-> CFG['xjs'] = array(
			'global' => true,
			'dateformat'=> true
		);
		$this-> CFG['issue_list'] = array();

		// prepare output
		$this-> prepare_Header(self::$_module_name);

//=== Record type and Organization selector ===//

		// Superuser can view the grid from the perspective of another organization (this also considers any overrides that the organization has)
		// Unlike the record viewing mode (which automatically sets the perspective to the owner org if one wasn't already selected, in the grid it has to be specified in the URL)
		if($this-> user-> is_Unrestricted()) {
			if($rq-> org_id) {
				// Organization perspective is set. Check overrides from that organization, and not from the Superuser's own organization
				$this-> perspective_org = $this-> org-> read($rq-> org_id, array('orgtype'));

				if(!isset(MVC_Core::$ss-> perspective) || MVC_Core::$ss-> perspective != $rq-> org_id) {
					$rt_id = $this-> rt_Return(true);
					MVC_Core::$ss-> perspective = (int)$rq-> org_id;
				} else
					$rt_id = $this-> rt_Return();
			} else {
				$this-> perspective_org = null; // Basic check of user's organization

				if(isset(MVC_Core::$ss-> perspective)) {
					$rt_id = $this-> rt_Return(true);
					unset(MVC_Core::$ss-> perspective);
				} else
					$rt_id = $this-> rt_Return();
			}
		} else
			// Non admin has nothing to do with perspective
			$rt_id = $this-> rt_Return();

			//echo $rt_id;exit();

		// Clear MVC_Core::$cache-> record_types of invalid entries
		MVC_Core::$cache-> record_types = array_filter(MVC_Core::$cache-> record_types);
		// The list of record types which are saved in the session are necessary for the menu, as well as the current record type
		$this-> rt_list = MVC_Core::$cache-> record_types;

//=== Group settings (depends on record type) ===//

		if(isset($this-> current_rt['settings']['group_type'])) {
			// Group model and plugin
			$this-> group = $this-> factory_Model('Group', null, $this-> current_rt['settings']['group_type']);

			// Get the groups name for this issue type (used in the groups column and also in the column selector - even if the column is not visible)
			$group_plugin = MVC_Core::factory_Plugin("group", $this-> current_rt['settings']['group_type']);
		}

//=== Views System ===//

		$this-> gridview = $this-> factory_Model('GridView');

		// Read active views and initialize grid to it. (If missing, just load default)
		// TODO: Now that we're using views, a manager should be able to set which view (from department or organization) should be the default one (for all users of that department or organization)
		if(!isset(MVC_Core::$cache-> grid_views)) {
			MVC_Core::$cache-> grid_views = $grid_views = $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "grid_views");

			// If an active view for current record type exists, it attempts to load that view (and initialize the grid to that view)
			if(isset($grid_views[$this-> current_rt['key']]))
				$this-> initialize_View($grid_views[$this-> current_rt['key']]);
		} else
			$grid_views = MVC_Core::$cache-> grid_views;

//=== Javascript configuration - Start ===//

		$this-> CFG['issue_list'] = array(
			// Statuses and Priorities available for current issue type
			'statuses'=> array_values($this-> current_rt['statuses_ex']),
			'priorities'=> array_values($this-> current_rt['priorities_ex']),
			'ist'=> array(
				'id'=> (int)$this-> current_rt['id'],
				'real_id'=> (int)$rt_id,
				'key'=> $this-> current_rt['key'],
				'name'=> $this-> current_rt['name'],
				'short_plural'=> $this-> current_rt['short_plural'],
				'group_type'=> $this-> current_rt['settings']['group_type'],
				'group_name'=> $group_plugin-> name
			),
			'xfield_def'=> array(),
			'xf_sections'=> $this-> current_rt['sections'],
			'special_settings'=> MVC_Core::$cache-> grid_ss
		);

		// To support selection of records without prio/status (those that don't have any organization, or were initialized with no prio/status)
		array_unshift($this-> CFG['issue_list']['priorities'], "(unavailable)");
		array_unshift($this-> CFG['issue_list']['statuses'], "(unavailable)");

		// Do we have an active view ? Send it to JS as well (it's ID), so the Save/Delete dropdown selectors autoselect current view;
		if(isset($grid_views[$this-> current_rt['key']]))
			$this-> CFG['issue_list']['active_view'] = $grid_views[$this-> current_rt['key']];

//=== Grid "Special Settings" (also affect the general layout, modifiying the width of the entire interface ===//
			
		if(!empty(MVC_Core::$cache-> grid_ss['width'])) {
			if(MVC_Core::$cache-> grid_ss['width'] == 0)
				MVC_Core::$cache-> grid_ss['width'] = 1880; // IWI set all views by default to 1880 
			elseif(MVC_Core::$cache-> grid_ss['width']<950)
				MVC_Core::$cache-> grid_ss['width'] = 950;
			elseif(MVC_Core::$cache-> grid_ss['width']>4070)
				MVC_Core::$cache-> grid_ss['width'] = 4070;

			$this->_out['DONREY']['cargohub']['fullwidth'] = MVC_Core::$cache-> grid_ss['width'] - 12;
		} else{
			// Force new default width for this module
            // // IWI set all views by default to 1880 start
			//$this->_out['DONREY']['cargohub']['fullwidth'] = 1308;
			$this->_out['DONREY']['cargohub']['fullwidth'] = 1880;
            // IWI set all views by default to 1880 end
        }

		if(isset(MVC_Core::$ss-> perspective))
			$this-> CFG['issue_list']['perspective'] = MVC_Core::$ss-> perspective;

//=== Available grid columns ===//

		// If using an override mode, re-read the override version of the record type
		if((int)$rt_id != (int)$this-> current_rt['id'])
			$this-> current_rt = $this-> record-> ist_Read($rt_id, false, false, true);

		// Built-In (meta) columns (The ones which are not customizable per issue type) include:
		// - Issue Metadata ( id, track, time, organization, group, if shared, ip address, assigned )
		// - Contact information for the submitter contact (Name, E-mail)
		$this-> CFG['issue_list']['bic'] = array(
		// Primary table
			'_id'=> "ID",
			'_ctime'=> "Created [Time]",		// Global
			'_edtime'=> "Fields Edited [Time]",	// Global
			'_master_org'=> "Master Organization",
			'_shared'=> "Is shared?",			// A post-process value, indicates that the fields "shared_org_ids" is non-null ( as in, the system is shared between organizations )
			'_group'=> null,					// The group name is in the RT Definition
			'_submit_domain'=> "Submit Domain",
			'_submit_org'=> "Submit Organization",	// Non-empty if submitted by logged-in system user
			'_submit_name'=> "Submit Person",		// Person name, from submitter contact
			'_submit_email'=> "Submit E-mail",		// ... and his/her e-mail
			'_submit_ip'=> "Submit IP",
		// Per organization table
			'_deps'=> "Department(s)",			// List of departments that have access to the record - This should probably not be accessible for everyone !
			'_process'=> "Record activity",
			// Organization timers
 			'_linktime'=> "Connected [Time]",			// (Internal value, not sure if useful for users)
 			'_time_open'=> "First opened [Time]",
			'_time_start'=> "First change [Time]",
			'_utime'=> "Last updated [Time]",			// Time when any update was done to organization-specific data (not affected by other organization operations)
			'_commtime'=> "Communication [Time]",		// Time when last communication was received for this organization (Check !)
			'_time_close'=> "Closed [Time]",
			//'_access_ut'=> "Accessed [Time]",		// Disabled, not currently updated
			'_pending_length'=> "Waiting length",
			'_process_length'=> "Process length",
			// Other oranization fields (Status, Priority, Transfer-related, Assigned)
			'_priority'=> "Priority",
			'_status'=> "Status",
			'_sender_name'=> "Sender name",
			'_sender_email'=> "Sender E-mail",
			'_recipient_name'=> "Recipient name",
			'_recipient_email'=> "Recipient E-mail",
			'_xfer_time'=> "Transfer time",		// It's actually share or transfer time
			'_xfer_ip'=> "Transfer IP",			// IP used when sharing/transferring an issue to this organizaiton
			'_xfer_confirmip'=> "Delivery IP",	// Used to check against the Transfer IP to acknowledge transfer
			'_xfer_delivered'=> "Is delivered?",	// Second column related to Confirm IP
			'_assigned'=> "Assigned to",		// User who is being assigned this issue
		// Markers table (User-to-Record)
			'_access'=> "Accessed by me [Time]",
			'_export'=> "Exported by me [Time]"
		);
		// Admin only speciality fields - Used for monitoring purposes
		if($this-> user-> is_Unrestricted()) {
			$this-> CFG['issue_list']['bic'] = array_merge($this-> CFG['issue_list']['bic'], array(
				'_c_convos'=> "# of Convos",
				'_c_files'=> "# of Attach.",
				'_c_tasks'=> "# of Tasks",
				'_c_docs'=> "# of Documents",
				'_c_ctx'=> "# of Contacts",
				'_c_updates'=> "# of Updates",
				// SuperUser Information fields
				'_sui_lmdt'=> "Last Msg [Time]",
				'_sui_lmctx'=> "Last Msg Recipients"
			));
		}

		// Issue type supports the cached fields - Will be disabled later, not working properly.
		if(isset($this-> current_rt['settings']['xf_cache'])) {
			$this-> CFG['issue_list']['xf_cache'] = $this-> current_rt['settings']['xf_cache']; // Send XFCache to the JS as well
			foreach($this-> current_rt['settings']['xf_cache'] as $xfc_id=> $xfc_def) {
				if($xfc_id == 'quickindex')
					continue;
				$this-> CFG['issue_list']['bic']['_cache_'.$xfc_id] = '**'.$xfc_def['name'];
			}
		}

		// Submitter Contact extended fields (company name, phone, social, others, the stuff from /nls/*/contact.yaml )
		// TODO: A way to choose which fields are available. For now, locked to just a few:
		$available_contact_columns = array('company_name', 'phone', 'phone_fax', 'social_linkedin', 'social_facebook', 'msg_skype');

		MVC_NLS::load_Groups("contact");
		$contact_fields = MVC_NLS::get_Object("contact", true);

		// Extract the name of the fields from the NLS data
		$con_xf = array(); $this-> CFG['issue_list']['sconcs'] = array(); // Submitter Contact Columns... sconcs, lol
		foreach($contact_fields as &$contact_group) {
			foreach($contact_group['fields'] as $field=> $string) {
				if(strpos($string, "~")) {
					$tmp = explode("~", $string);
					$con_xf[$field] = $tmp[0];
				} else {
					$con_xf[$field] = $string;
				}
				if(in_array($field, $available_contact_columns))
					$this-> CFG['issue_list']['sconcs']['@'.$field] = $con_xf[$field];
			}
		}

		// Are groups in use for current issue type?
		if(isset($group_plugin)) {
			$this-> CFG['issue_list']['ist']['group_type'] = $this-> current_rt['settings']['group_type'];
			$this-> CFG['issue_list']['ist']['group_name'] = $group_plugin-> name;
			$this-> CFG['issue_list']['bic']['_group'] = $group_plugin-> name;
		}

		// get the list of currently selected columns for this user and this issue type
//		if(($rt_id == 1 && ($_SESSION['organization']['type_id'] =='airline' || $_SESSION['organization']['type_id'] =='handler')) || ($rt_id == 6) || ($rt_id == 51) || ($rt_id == 31) || ($rt_id == 12) || ($rt_id == 39) || ($rt_id == 34) ){
//                    
//                }else{
                    $columns = $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "columns_ist_".$rt_id);
//                }
		// TODO: Later fully convert this to Views (column editor will actually update current view) - and not use "columns_ist_N" anymore.
//		echo "<pre>";
//                print_r($columns);
//                echo "</pre>";
		// When the profile read failed, use a list of default columns instead.
		if(!$columns) {
			// TODO: This should be configurable somehwere (maybe in a YAML "data" file ?)
			// 22012025 Air Cargo Claim default column  added by IWI | Start
			// echo "364 <pre>";
			//  echo "organization -> type_id : ";print_r($_SESSION['organization']['type_id']);
			// print_r(MVC_Core::$ss-> user);
			// exit;
			if($rt_id == 1 && ($_SESSION['organization']['type_id'] =='airline' || $_SESSION['organization']['type_id'] =='handler')){
				$columns = array(
					array('fk'=> "_ctime", 'v'=> true, 'w'=> 100),
					array('fk'=> "_status", 'v'=> true,'w'=>100),	
					array('fk'=> "_priority", 'v'=> true,'w'=>100),
					array('fk'=> "mawb", 'v'=> true,'w'=>100),
					array('fk'=> "pstage", 'v'=> true,'w'=>100),
					array('fk'=> "claim_usd", 'v'=> true,'w'=>80),
					array('fk'=> "_assigned", 'v'=> true,'w'=>100),
					array('fk'=> "@company_name", 'v'=> true,'w'=>100),
					array('fk'=> "claim_type", 'v'=> true,'w'=>100),
					array('fk'=> "dest_loc", 'v'=> true,'w'=>130),
					array('fk'=> "orig_loc", 'v'=> true, 'w'=> 130),
					array('fk'=> "acdate", 'v'=> true, 'w'=> 100),
					array('fk'=> "form_clm", 'v'=> true, 'w'=> 100),
					array('fk'=> "prelimi_claim", 'v'=> true, 'w'=> 80),
					array('fk'=> "handling", 'v'=> true, 'w'=> 130),
				);
			}
			// 22012025 Air Cargo Claim default column  added by IWI | End

			// 04072024 added by IWI | Start
			elseif($rt_id == 6){
				$columns = array(
					array('fk'=> "_status", 'v'=> true, 'w'=> 100),
					array('fk'=> "_priority", 'v'=> true,'w'=>100),	
					array('fk'=> "_assigned", 'v'=> true,'w'=>100),
					array('fk'=> "_group", 'v'=> true,'w'=>300),
					array('fk'=> "section", 'v'=> true,'w'=>200),
					array('fk'=> "chp_no", 'v'=> true,'w'=>100),
					array('fk'=> "chapter", 'v'=> true,'w'=>400),
					array('fk'=> "_cache_primary", 'v'=> true,'w'=>300),
					array('fk'=> "respo", 'v'=> true,'w'=>100),
					array('fk'=> "revw_date", 'v'=> true,'w'=>130),
					array('fk'=> "score_sc", 'v'=> true, 'w'=> 130),
					array('fk'=> "doc_ref", 'v'=> true, 'w'=> 100),
					array('fk'=> "score_n", 'v'=> true, 'w'=> 100),
					array('fk'=> "answer", 'v'=> true, 'w'=> 250),
				);
			}//16082024 changes by IWI | Start
			elseif($rt_id == 51){
                            //                                IWI  Set default view for training record and training scheduler 27022026 start
				/*
                            $columns = array(
					array('fk'=> "_ctime", 'v'=> true, 'w'=> 100),
					array('fk'=> "_priority", 'v'=> true, 'w'=> 77),
					array('fk'=> "_status", 'v'=> true, 'w'=> 102),
					array('fk'=> "_cache_primary", 'v'=> true, 'w'=> 150),
					array('fk'=> "place_train", 'v'=> true, 'w'=> 150),
					array('fk'=> "train_loc", 'v'=> true, 'w'=> 150),
					array('fk'=> "no_train", 'v'=> true, 'w'=> 150),
					array('fk'=> "date_train", 'v'=> true, 'w'=> 120),
					array('fk'=> "date_end", 'v'=> true, 'w'=> 120),
					array('fk'=> "train_exp", 'v'=> true, 'w'=> 120),
					array('fk'=> "max_trainee", 'v'=> true, 'w'=> 120),
				);
                                 * */
                                 
                             $columns = array(
					array('fk'=> "_ctime", 'v'=> true, 'w'=> 100),
					array('fk'=> "_priority", 'v'=> true, 'w'=> 77),
					array('fk'=> "_status", 'v'=> true, 'w'=> 102),
					array('fk'=> "_group", 'v'=> true, 'w'=> 450),
					array('fk'=> "place_train", 'v'=> true, ),
					array('fk'=> "train_loc", 'v'=> true, ),
					array('fk'=> "date_train", 'v'=> true, ),
					array('fk'=> "date_end", 'v'=> true,),
					array('fk'=> "no_train", 'v'=> true, ),
					array('fk'=> "train_exp", 'v'=> true, ),
					array('fk'=> "classroom_type", 'v'=> true, ),
					array('fk'=> "max_trainee", 'v'=> true, ),
				);
                                //                                IWI  Set default view for training record and training scheduler 27022026 end
			}//16082024 changes by IWI | End
			//30122024 Quality Event grid changes by IWI | Start
			elseif($rt_id == 31){
				$columns = array(
					array('fk'=> "event_date", 'v'=> true, 'w'=> 100),
					array('fk'=> "_utime", 'v'=> true, 'w'=> 77),
					array('fk'=> "_status", 'v'=> true, 'w'=> 102),
					array('fk'=> "_cache_primary", 'v'=> true, 'w'=> 150),
					array('fk'=> "x_cust", 'v'=> true, 'w'=> 150),
					array('fk'=> "_group", 'v'=> true, 'w'=> 150),
					array('fk'=> "risk", 'v'=> true, 'w'=> 150),
					array('fk'=> "_assigned", 'v'=> true, 'w'=> 120),
					array('fk'=> "event_result", 'v'=> true, 'w'=> 120),
					array('fk'=> "inc_type", 'v'=> true, 'w'=> 120),
					array('fk'=> "inv_pers", 'v'=> true, 'w'=> 120),
					array('fk'=> "inv_date", 'v'=> true, 'w'=> 120),
				);
			}//30122024 Quality Event grid changes by IWI | End

			//10012025 Document Management grid changes by IWI | Start
			elseif($rt_id == 12){
				$columns = array(
					array('fk'=> "_utime", 'v'=> true, 'w'=> 100),
					array('fk'=> "name", 'v'=> true, 'w'=> 100),
					array('fk'=> "doctype", 'v'=> true, 'w'=> 100),
					
					array('fk'=> "_group", 'v'=> true, 'w'=> 100),
					array('fk'=> "_status", 'v'=> true, 'w'=> 77),
					array('fk'=> "_priority", 'v'=> true, 'w'=> 102),
					array('fk'=> "vers", 'v'=> true, 'w'=> 150),
					array('fk'=> "start", 'v'=> true, 'w'=> 150),
					array('fk'=> "expire", 'v'=> true, 'w'=> 150),
					array('fk'=> "owner", 'v'=> true, 'w'=> 150),
					array('fk'=> "inex_1", 'v'=> true, 'w'=> 120),
					array('fk'=> "code_iso2", 'v'=> true, 'w'=> 120),
					array('fk'=> "revwnext", 'v'=> true, 'w'=> 120),
				);
			}//10012025 Document Management grid changes by IWI | End

//                         IWI  Set default view for training record and training scheduler 27022026 start
                             elseif ($rt_id == 39) {
                                    $columns = array(
                                        array('fk' => '_priority', 'v' => true, 'w' => 77),
                                        array('fk' => '_status', 'v' => true, 'w' => 102),
                                        array('fk' => '_group', 'v' => true, 'w' => 180),
                                        array('fk' => 'emp_name', 'v' => true, 'w' => 100),
                                        array('fk' => 'emp_surname', 'v' => true, 'w' => 180),
                                        array('fk' => 'emp_birth', 'v' => true, 'w' => 100),
                                        array('fk' => 'emp_id', 'v' => true, 'w' => 100),
                                        array('fk' => 'emp_hire', 'v' => true, 'w' => 100),
                                        array('fk' => 'emp_roles', 'v' => true, 'w' => 150),
                                        array('fk' => 'tr_nxt_name', 'v' => true, 'w' => 400),
                                        array('fk' => 'tr_nxt', 'v' => true, 'w' => 150),
                                    );
                                }
                             
//                         IWI  Set default view for training record and training scheduler 27022026 end

//                                IWI  Set default view for Audit Control start
                                elseif ($rt_id == 34) {
                                    $columns = array(
                                        array('fk' => '_group', 'v' => true, ),
                                        array('fk' => '_ctime', 'v' => true, 'w' => 100),
                                        array('fk' => '_priority', 'v' => true, 'w' => 77),
                                        array('fk' => '_status', 'v' => true, 'w' => 102),
                                        array('fk' => '_cache_primary', 'v' => true, 'w' => 400),
                                        array('fk' => '_assigned', 'v' => true, 'w' => 150),
                                        array('fk' => 'scheduled_date', 'v' => true,),
                                        array('fk' => 'date_start', 'v' => true, ),
                                        array('fk' => 'date_end', 'v' => true, ),
                                        array('fk' => 'sched_rep', 'v' => true, 'w' => 150),
                                        array('fk' => 'inex', 'v' => true, 'w' => 150),
                                    );
                                }
                                
//                                IWI  Set default view for Audit Control end

			else{
				$columns = array(
					array('fk'=> "_ctime", 'v'=> true, 'w'=> 100),
					array('fk'=> "_priority", 'v'=> true, 'w'=> 77),
					array('fk'=> "_status", 'v'=> true, 'w'=> 102),
					array('fk'=> "_submit_org", 'v'=> true, 'w'=> 180),
					array('fk'=> "_submit_email", 'v'=> true, 'w'=> 200),
					array('fk'=> "_cache_primary", 'v'=> true, 'w'=> 150),
					array('fk'=> "@company_name", 'v'=> true, 'w'=> 180),
				);
			}
			// 04072024 added by IWI | End
			
			$update_user_columns = true; // Signal that the user doesn't have a valid columns profile for current issue type, and one should be created after columns are filtered.
		}

		// Process columns data, making sure that all and only those available in the system are present.
		// - When adding columns, all new ones (non-visible) are added after the existing columns). They can be then reordered in the columns editor.
		$column_fk_list = array();
		foreach($columns as $idx=> &$column) {
			if(substr($column['fk'], 0, 1)=='_') {
				// This appears to be a Built-In Column, search in "bic"
				if(!isset($this-> CFG['issue_list']['bic'][$column['fk']])) {
					unset($columns[$idx]);
					continue;
				}
			} elseif(substr($column['fk'], 0, 1)=='@') {
				// This is a Contact Column, search in "sconcs"
				if(!isset($this-> CFG['issue_list']['sconcs'][$column['fk']])) {
					unset($columns[$idx]);
					continue;
				}
			} else {
				// X-field column
				if(!isset($this-> current_rt['xfield_def'][$column['fk']])) {
					unset($columns[$idx]);
					continue;
				}
			}
			// If we got here, the column is valid
			$column_fk_list[] = $column['fk'];
		}
		unset($column);

		// Now check if there are any new columns which are not present in the $user_columns ( or the default ), and add them at the end as hidden

		// Bics go first...
		foreach($this-> CFG['issue_list']['bic'] as $fk=> $name)
			if(!in_array($fk, $column_fk_list)) {
				$columns[] = array(
					'fk'=> $fk,
					'v'=> false
				);
				$update_user_columns = true;
			}
		// Submitter Contact Columns are next
		foreach($this-> CFG['issue_list']['sconcs'] as $fk=> $name)
			if(!in_array($fk, $column_fk_list)) {
				$columns[] = array(
					'fk'=> $fk,
					'v'=> false
				);
				$update_user_columns = true;
			}

		// All available xfields following (in their default order)
		foreach($this-> current_rt['xfield_def'] as $fk=> $def)
			if(!in_array($fk, $column_fk_list)) {
				$column = array(
					'fk'=> $fk,
					'v'=> false
				);
				if(isset($def['o']['colw']))
					$column['w'] = $def['o']['colw'];
				$columns[] = $column;
				$update_user_columns = true;
			}

		//print_r($columns);print '<br/>	';
		$columns = array_values($columns); // It must convert to an array when sent as json, and not as an object

		// The user receives column information, or gets existing information fixed (as in, columns added or deleted). Save the new profile information.
		if(isset($update_user_columns))
			$this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "columns_ist_".$rt_id, $columns, false, sprintf("Auto fixed on %s", date("Y-m-d")));

		// Scan all columns again:
		// - some built-in columns need extra data (like the _group column)
		// - add the "section" for columns, used by the column editor
		$this-> CFG['issue_list']['ext_col_data'] = array(); // init External Column Data (might be needed for certain columns)
		$visible_column_keys = array('_track');
		foreach($columns as &$column) {

			if($column['fk'][0]=='_') {		// Built-In Columns
				// If "group" column is present, read the available groups for the logged in user
				if($column['fk']=='_group') {
					// get the list of groups this user has access to (for the groups filter)
					if($this-> user-> is_Unrestricted())
						$group_id_list = array();
					else
						$group_id_list = array_keys(MVC_Core::$ss-> user['acls']['groups']);

					// Extra filter - Check if issue type has restrictions on which group IDs are visible for it ( this are absolute and do not depend on any rights or who accesses the issue type, including admin )
					// - It allows group type sharing between multiple issue types
					if(!empty($this-> current_rt['group_ids'])) {
						if(empty($group_id_list))
							$group_id_list = $this-> current_rt['group_ids'];
						else
							$group_id_list = array_intersect($group_id_list, $this-> current_rt['group_ids']);
					}

					$r = $this-> group-> get_List(array(), function (QueryFactory &$q) use ($group_id_list) {
						if(count($group_id_list)>0)
							$q-> where('`grp`.`id` IN(?)', $group_id_list);
					}, null, "`name` ASC");
					if($r && count($r)>0) {
						$this-> CFG['issue_list']['groups'] = array();
						foreach($r as $row)
							$this-> CFG['issue_list']['groups'][] = $row['name'];
					}

					$grouping_grp_enabled = true;
				} elseif($column['fk']=='_priority' && $column['v'])
					$grouping_prio_enabled = true;
				elseif($column['fk']=='_status' && $column['v'])
					$grouping_stat_enabled = true;
				elseif($column['fk']=='_master_org' && $column['v'])
					$grouping_org_enabled = true;
			} elseif($column['fk'][0]=='@') {	// Submitter Contact Columns
				// Nothing atm.
			} else {
				// eXtra-Fields Columns
				$xfield = $this-> current_rt['xfield_def'][$column['fk']];

				$this-> CFG['issue_list']['xfield_def'][$column['fk']] = $xfield;
				// Some visible columns get extra post-processing
				if($column['v']) {
					$this-> CFG['issue_list']['xfield_def'][$column['fk']] = $xfield;

					switch($xfield['t']) {
						// TODO: Move all this in the field plugin (somehow?)
						case 'airport':
							// the list of countries necessary for the Airport's attached column "Country" is read from a different table (only once, and only if such field is present in the list of columns)
							if(!isset($this-> CFG['issue_list']['ext_col_data']['countries'])) {
								$m_data_countries = new App_Model_Data("countries");
								$ctry_list = $m_data_countries-> get_List(null, null, "`name_english` ASC");
								if($ctry_list) {
									$this-> CFG['issue_list']['ext_col_data']['countries'] = array();
									foreach($ctry_list as $ctry)
										$this-> CFG['issue_list']['ext_col_data']['countries'][] = $ctry['name_english'];
								}
							}
							break;
						case 'money':
							if(!isset($this-> CFG['issue_list']['ext_col_data']['currencies'])) {
								$m_data_money = new App_Model_Data_Money();
								$this-> CFG['issue_list']['ext_col_data']['currencies'] = $m_data_money-> get_Currencies_ISO_List();
							}
							break;
					}
				}
			}

			// Save visibility information needed in Get_List
			if($column['v'])
				$visible_column_keys[] = $column['fk'];
		}
		unset($column);

		// Send the column information to JS (self.config.columns)
		$this-> CFG['issue_list']['columns'] = $columns;

		// Save in session the list of those that are supposed to be read by "action_Get_List"
		MVC_Core::$cache-> listing_columns = $visible_column_keys;

		// Put some user preferences into $this-> CFG, as they might be used by JS
		$this-> CFG['issue_list']['preferences'] = isset(MVC_Core::$ss-> user['preferences']['grid'])? MVC_Core::$ss-> user['preferences']['grid']: array();

//=== Views and search parameters - Grid state persistence ===//

		// When the preserve setting is "off", also clear the alert being hidden. Next time when it's turned on the alert will show again.
		if(empty($this-> CFG['issue_list']['preferences']['preserve']))
 			unset(MVC_Core::$cache-> preserve_alert_off);
		elseif(!empty(MVC_Core::$cache-> preserve_alert_off))
			$this-> CFG['issue_list']['preferences']['preserve_alert_off'] = true;

		// If present in session, send grid state to JavaScript (even if it's not auto-saved with changes) - Necessary for VIEWs to work.
		if(isset(MVC_Core::$cache-> grid_state) && isset(MVC_Core::$cache-> grid_state[$this-> current_rt['key']])) {
			$grid_state = MVC_Core::$cache-> grid_state[$this-> current_rt['key']];
		} else {
			// When Grid State not found in station, initialize it based on Grid Settings ( user profile entry ) and Record Type
			$grid_settings = $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "settings_ist_{$rt_id}");

			if(!empty($grid_settings)) {
				// Default page number, size, size options (non-standard) and initial sorting
				$grid_state = array(
					'pageNum'=> 0,
					'pageSize'=> $grid_settings['page'],
					'pageSizeOpts'=> $grid_settings['sizes'],
					'sort'=> array(
						'column'=> '_'.$grid_settings['sort'],
						'direction'=> $grid_settings['order']
					)
				);

				// Enable grouping ?
				if($grid_settings['group'])
					switch($grid_settings['group']) {
						case 'prio':
							if(isset($grouping_prio_enabled))
								$grid_state['grouping'] = array("_priority");
							break;
						case 'stat':
							if(isset($grouping_stat_enabled))
								$grid_state['grouping'] = array("_status");
							break;
						case 'org':
							if(isset($grouping_org_enabled))
								$grid_state['grouping'] = array("_master_org");
							break;
						case 'grp':
							if(isset($grouping_grp_enabled))
								$grid_state['grouping'] = array("_group");
							break;
						case 'disable':
							$this-> CFG['issue_list']['grouping_disabled'] = true;
							break;
					}
			} else
				// No settings whatsoever, initialize with very basic defaults
				$grid_state = array(
					'pageNum'=> 0,
					'pageSize'=> 25,
					'pageSizeOpts'=> array(25, 50, 100, 250, 500, 1000, 2500),
					'sort'=> null
				);

			// Filters storage - Will by applied by the loaded view
			$grid_state['filters'] = array();
		}

		// Always start with a grid state! + Certain fields are mandatory
		if(!isset($grid_state['grouping']))
			$grid_state['grouping'] = array();
		$this-> CFG['issue_list']['grid_state'] = $grid_state;

//=== MENU: Prepare the variable content for the menu ===//

		// Recently opened records - Add ist information (key, name)
		$recent_records = $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "iss_recent", false, 1, true);
		//print_r($recent_records);exit();
		if(!empty($recent_records))
			foreach($recent_records as $track=> $data) {
				if(!isset(MVC_Core::$cache-> record_types_index[$data['ist']])) {
					unset($recent_records[$track]);
					continue;
				}
				$rt_info = &MVC_Core::$cache-> record_types[MVC_Core::$cache-> record_types_index[$data['ist']]];
				$recent_records[$track]['rt_key'] = $rt_info['key'];
				$recent_records[$track]['rt_name'] = $rt_info['name'];
			}

		// Old format views - "user_profiles" with variant bigger than 1 (if they exist)
		// TODO: Eventually find a way to completely remove these and change into the new views, only after "configure columns" applies directly to the view
		$r_views = $this-> user-> profile_Get_List(MVC_Core::$ss-> user['id'], function(QueryFactory &$q) use($rt_id) {
			$q	-> where("`pkey` = ?", "columns_ist_".$rt_id)
				-> where("`variant` > 1");
		});

		if($r_views && count($r_views)>0) {
			// They exist - For each one of them construct a new format "grid view" then delete them (Automatic upgrade)
			foreach($r_views as $old_view_index)
				$this-> convert_View($old_view_index);
		}

		$views = $this-> gridview-> get_Compact_List('user', array(
			'rt_key'=> $this-> current_rt['key'],
			'user_id'=> MVC_Core::$ss-> user['id'],
			'dep_id'=> MVC_Core::$ss-> user['cdep_id'],
			'org_id'=> MVC_Core::$ss-> user['org_id']
		), 'menu', 'grouped');

		// Users that have the access right to move records between departments get the list of all organization departments

		if(isset(MVC_Core::$ss-> perspective) || !($this-> user-> is_Unrestricted() && $this-> user-> test_ACL('privileges','issue_access_deps'))) {
			$m_dep = new App_Model_Department();
			$departments = $m_dep-> get_List(MVC_Core::$ss-> perspective?:MVC_Core::$ss-> user['org_id'], array());
		}
                

		// Prepare the menu variable
		$this-> menu = array(
			// lists of available statuses and priorities for the current issue type
			'statuses'=> $this-> current_rt['statuses_ex'],
			'priorities'=> $this-> current_rt['priorities_ex'],
			// Recently opened records (similar to "recend documents in any software)
			'recent'=> $recent_records,
			// List of available views for the current user and record type
			'views'=> $views,
			// Department list for the "Allocate to department" command
			'departments'=> (isset($departments) && is_array($departments))? $departments: null
		);

		// The JavaScript part should only show personal views if the user doesn't have the ability to update/delete views
		if(!$this-> user-> test_ACL('privileges', 'grid_manage_views')) {
			foreach($views as $idx=> $view) {
				if($view['visibility'] != 'user')
					unset($views[$idx]);
			}
		}

		$this-> CFG['issue_list']['views'] = $views;

//=== Finally, The module header and output ===//

		$this-> module = array(
			'icon'=> isset($grid_views[$this-> current_rt['key']])? "eye.png": "table.png",
			'icon_alt'=> "(table)",
			'title'=>	(isset($grid_views[$this-> current_rt['key']]) && !empty(MVC_Core::$cache-> view_info[$this-> current_rt['key']])? MVC_Core::$cache-> view_info[$this-> current_rt['key']]: self::$_module_name).
						($this-> perspective_org? " for org. <a href='/manager/organization?id={$this-> perspective_org['id']}#overview' target='_blank'>{$this-> perspective_org['name']}</a>": ""),
			//'title_para'=> $this-> current_rt['name'], 
			'description'=> "List and search records - All data fields",
                        'show_title'=>'yes' //IWI show looad view title
		);

		$this->_out['html']['favicon'] = "/favico/{$this-> current_rt['key']}.ico";
                
                
//                IWI country & location bulk edit for License monitor start
                $cstmCountryTxt='AD,AE,AF,AG,AI,AL,AM,AO,AQ,AR,AS,AT,AU,AW,AX,AZ,BA,BB,BD,BE,BF,BG,BH,BI,BJ,BL,BM,BN,BO,BQ,BR,BS,BT,BV,BW,BY,BZ,CA,CC,CD,CF,CG,CH,CI,CK,CL,CM,CN,CO,CR,CU,CV,CW,CX,CY,CZ,DE,DJ,DK,DM,DO,DZ,EC,EE,EG,EH,ER,ES,ET,FI,FJ,FK,FM,FO,FR,GA,GB,GD,GE,GF,GG,GH,GI,GL,GM,GN,GP,GQ,GR,GS,GT,GU,GW,GY,HK,HM,HN,HR,HT,HU,ID,IE,IL,IM,IN,IO,IQ,IR,IS,IT,JE,JM,JO,JP,KE,KG,KH,KI,KM,KN,KP,KR,KW,KY,KZ,LA,LB,LC,LI,LK,LR,LS,LT,LU,LV,LY,MA,MC,MD,ME,MF,MG,MH,MK,ML,MM,MN,MO,MP,MQ,MR,MS,MT,MU,MV,MW,MX,MY,MZ,NA,NC,NE,NF,NG,NI,NL,NO,NP,NR,NU,NZ,OM,PA,PE,PF,PG,PH,PK,PL,PM,PN,PR,PS,PT,PW,PY,QA,RE,RO,RS,RU,RW,SA,SB,SC,SD,SE,SG,SH,SI,SJ,SK,SL,SM,SN,SO,SR,SS,ST,SV,SX,SY,SZ,TC,TD,TF,TG,TH,TJ,TK,TL,TM,TN,TO,TR,TT,TV,TW,TZ,UA,UG,UM,US,UY,UZ,VA,VC,VE,VG,VI,VN,VU,WF,WS,YE,YT,ZA,ZM,ZW,AC,CP,DG,EA,EU,EZ,FX,IC,SU,TA,UK,UN';
                $cstmCountryHtml='';
                foreach (explode(',',$cstmCountryTxt) as $cntryCode){
                    $cstmCountryHtml .='<option value="'.$cntryCode.'">'.$cntryCode.'</option>';
                }
                
                
                $this->cstmCountryHtml =$cstmCountryHtml;
//                IWI country & location bulk edit for License monitor end
                
                
//                print_r(MVC_Core::$cache-> grid_state[$this-> current_rt['key']]);
                

		// which body template to use, and run the view
		$this-> site_section = "issues". DS. "list.html";
		$this-> view("private.html");
	}

	/**
	 * Converts field names that come from the grid to the corresponding `table`.`dbfield` for use in joins
	 *   Also returns a filtertype or conversion indicator (for the filtering system)
	 *   An unrecognized key will return null
	 * @param string $field_name
	 * @return array|null
	 */
	private function gridField_2_dbField($fkey)
	{
		switch($fkey) {
		// The primary fields in issues table
			case '_id':
			case '_track':
			case '_submit_domain':
			case '_c_convos':
			case '_c_files':
			case '_c_tasks':
			case '_c_docs':
			case '_c_ctx':
			case '_c_updates':
			case '_sui_lmctx':
				return array('dbf'=> "`iss`.".substr($fkey,1), 'type'=> 'standard');
			case '_ctime':
			case '_edtime':
				return array('dbf'=> "`iss`.".substr($fkey,1), 'type'=> 'unixtime');
			case '_sui_lmdt':
				return array('dbf'=> "`iss`.".substr($fkey,1), 'type'=> 'mysqldt');
			case '_master_org':
				return array('dbf'=> "`org`.name", 'type'=> 'standard');
			case '_shared':
				return array('dbf'=> "`iss`.shared_org_ids", 'type'=> 'notempty');
			case '_group':
				return array('dbf'=> "`grp`.name", 'type'=> 'standard');
			case '_submit_org':
				return array('dbf'=> "`subo`.name", 'type'=> 'standard');
			case '_submit_name':
				return array('dbf'=> "`subm`.name", 'type'=> 'standard');
			case '_submit_email':
				return array('dbf'=> "`subm`.email", 'type'=> 'standard');
			case '_submit_ip':
				return array('dbf'=> "`iss`.".substr($fkey,1), 'type'=> 'ip2long');
		// Associated Per-Organization Table
			case '_process':
				return array('dbf'=> "`iod`.process", 'type'=> 'standard');
			case '_linktime':
			case '_time_open':
			case '_time_start':
			case '_time_close':
			case '_commtime':
			case '_xfer_time':
				return array('dbf'=> "`iod`.".substr($fkey,1), 'type'=> 'unixtime');
			case '_utime':
				return array('dbf'=> "`iod`.utime", 'type'=> 'mysqldt');
			case '_pending_length':
			case '_process_length':
			case '_filesize_total':
			case '_count_propfields':
				return array('dbf'=> "`iod`.".substr($fkey,1), 'type'=> 'standard');
			case '_deps':
				return array('dbf'=> "`dep`.name", 'type'=> 'standard');
			case '_priority':
				return array('dbf'=> "`iod`.priority_key", 'type'=> 'priority');
			case '_status':
				return array('dbf'=> "`iod`.status_key", 'type'=> 'status');
			case '_sender_name':
				return array('dbf'=> "`xsend`.name", 'type'=> 'standard');
			case '_sender_email':
				return array('dbf'=> "`xsend`.email", 'type'=> 'standard');
			case '_recipient_name':
				return array('dbf'=> "`xrecv`.name", 'type'=> 'standard');
			case '_recipient_email':
				return array('dbf'=> "`xrecv`.email", 'type'=> 'standard');
			case '_xfer_ip':
			case '_xfer_confirmip':
				return array('dbf'=> "`iod`.".substr($fkey,1), 'type'=> 'ip2long');
			case '_xfer_delivered':
				return array('dbf'=> "`iod`.xfer_confirmip", 'type'=> 'notempty');
			case '_assigned':
				return array('dbf'=> "`usr`.name", 'type'=> 'standard');
			case '_access':
			case '_export':
				return array('dbf'=> "`rmk`.".substr($fkey,1)."_ut", 'type'=> 'unixtime');

				// Other fields, or types of fields
			default:
				// Little hack used for plugin attached data
				if($fkey[0]=='~') {
					$fkey = substr($fkey, 1);
					$att_data_search = true;
				}
				if(substr($fkey, 0, 1)=='@') {
					// is it a contact xdata key?
					return array('dbf'=> "`subm`.xdata", 'type'=> 'standard', 'prepend'=> '%"'.substr($fkey, 1).'":"'); // Json fields can only search from start (not in the middle of the field)
				} elseif(isset($this-> current_rt['xfield_def'][$fkey])) {
					// is it an xfield?
					$fdef = &$this-> current_rt['xfield_def'][$fkey]; $plugin = null;
					// find which value is the xfield using ( int_value, value ) and the fk_id
					$ret = array('fkey'=> $fkey, 'where_field'=> $this-> record-> XField_Data_Column($fdef, $plugin), 'type'=> 'xfield', 'fdef'=> &$fdef);
					if(!is_null($plugin))
						$ret['plugin'] = &$plugin;
					if(isset($att_data_search))
						$ret['att_data_search'] = true;
					return $ret;
				} elseif(substr($fkey, 0, 7)=='_cache_') { // Cache fields disabled, not working properly
					// One of the "cache" fields
					return array('dbf'=> "`iss`.cache_".substr($fkey, 7), 'type'=> 'standard');
				} else
					// Unknown field, crash
					throw new Exception("Attempting to filter by an unknown field. Configuration error?");
		}
	}

	/**
	 * The super-filter for the grid's Get_List function below
	 *
	 * @param QueryFactory $q
	 * @param array $params
	 */
	public function filter_Get_List(QueryFactory &$q, $params)
	{	
		$usr = MVC_Core::$ss-> user;

		// Prepare Special filters //
		if(isset($params['custom'])) {
			if(isset($params['custom']['xfilters'])) {
				$xfilters = $params['custom']['xfilters'];
				if(!isset($xfilters[0]))
					$xfilters[0] = 'active';
			} else
				$xfilters = array(0=> "active");
		}

		// Restriction or permission filters //
		if(!$this-> user-> is_Unrestricted()) {
			// for non-admin users, restrict what they can view depending on privileges
			$restriction_filters = array();

			switch($xfilters[0]) {
				// Temporarily disabled
				// case 'notshared':
				// 	// When not admin, filter own records only ( excluded those shared to the user's org )
				// 	$restriction_filters[] = $q-> quote_Into("`iss`.master_org_id = ?", $usr['org_id']);
				// 	break;
				// 				case 'notours':
				// 					// Show only issues to which this organization has access but to which it is not the master
				// 					$restriction_filters[] = $q-> quote_Into("`iss`.master_org_id != ? AND FIND_IN_SET(?, `iss`.shared_org_ids)", $usr['org_id'], $usr['org_id']);
				// 					break;
				default:
					// organization ownership filters - Shows issues which are owned by the current logged in user's organization, or those that have this organization in their shared_org_ids
					$restriction_filters[] = $q-> quote_Into("(`iss`.master_org_id = ? OR FIND_IN_SET(?, `iss`.shared_org_ids))", $usr['org_id'], $usr['org_id']);
					break;
			}

			// Apply group and assignment based filters only when the user doesn't have the special "see all organization's issues" privilege assigned
			// -- That privilege ignores all visibility restrictions and allows the user to access ALL issues that are visible for his organization.
			if(!$this-> user-> test_ACL('privileges', 'issues_view_orgowned')) {
				// Group based restrictions, only show issues in the groups this user has access to
				$restriction_filters[] = $q-> quote_Into("`iss`.`group_id` IN(?)", array_keys($usr['acls']['groups']));

				// Assignment based restricitons:
				$unassgn = $this-> user-> test_ACL('privileges', 'issues_view_unassgn');
				$otherassgn = $this-> user-> test_ACL('privileges', 'issues_view_assgn_oth');

				if($unassgn == true && $otherassgn == true) {
					// see all, nothing to limit (this is just a placeholder case)
				} elseif($unassgn == true && $otherassgn == false) {
					// assigned to self and unassigned
					$restriction_filters[] = $q-> quote_Into('(`iod`.assigned_to = ? OR `iod`.assigned_to IS NULL)', $usr['id']);
				} elseif($unassgn == false && $otherassgn == true) {
					// assigned to self and to others
					$restriction_filters[] = $q-> quote_Into('(`iod`.assigned_to = ? OR `iod`.assigned_to IS NOT NULL)', $usr['id']);
				} elseif($unassgn == false && $otherassgn == false) {
					// only assigned to self
					$restriction_filters[] = $q-> quote_Into('`iod`.assigned_to = ?', $usr['id']);
				}

				// Department-level filter - User's active department must be in the list of "dep_ids" in the issue (unless the user has access to all)
				if(!$this-> user-> test_ACL('privileges', 'issue_access_deps'))
					if(!empty($usr['cdep_id'])) {
						$restriction_filters[] = $q-> quote_Into('FIND_IN_SET(?, `iod`.`dep_ids`)', $usr['cdep_id']);
					}
			}

			$permission_filters = implode(" AND ", $restriction_filters);

			// Finally, issues submited by the logged in user, which still belong to the user's organization, are always accessible (ignoring other permission filters)
//			$permission_filters.= $q-> quote_Into(" OR (`iss`.`master_org_id` = ? AND `iss`.`submit_id` = ?)", $usr['org_id'], $usr['contact_id']);

			// All restriction and permission filters are applied as one where. Search filters are then subsequently applied with AND to the permision filters
			$q-> where($permission_filters);
		}

		// Common exclusive filters (Normal + Extra condition)
		switch($xfilters[0]) {
			// Show only the issues to which the organization is master and are not currently shared with other organization ( filters out those that have been shared/sent )
// 			case 'notshared':
// 				$q-> where("`iss`.shared_org_ids IS NULL");
// 				break;
			// Shows only records which have 0 linked-tasks defined (for the organization)
			case 'notasks':
				$q-> where("`iod`.count_tasks = 0");
				break;
			// This Admin-only filter shows only issues which do not belong to any organization
			case 'noorg':
				$q-> where("`iss`.master_org_id IS NULL");
				break;
		}
		// By default do not show closed or hidden records (unless "showall" button is pushed)
		// Showing "hidden" ones only has effect for admin, because normal users can only see records with "master_org_id" or if their org id is inside "shared_org_ids"
		// -- Records with the organization disabled don't have the user's Org_ID present in it, making it completely invisible
		if(!in_array($xfilters[0], array('closed', 'showall', 'noorg')))
			$q-> where("`iod`.process NOT IN('closed', 'hidden')");
		elseif($xfilters[0] == 'closed')
			$q-> where("`iod`.process = 'closed'");

		// User filters
		if(!empty($xfilters[1])) {
			// This filter only shows records which are assigned to the logged in user, even if he has access to them
			if(in_array('assigned', $xfilters[1])) {
				$q-> where("`iod`.assigned_to = ?", $usr['id']);
			}

			if(in_array('unopen', $xfilters[1])) {
				$q-> where("`rmk`.access_ut IS NULL");
			}

			if(in_array('unexp', $xfilters[1])) {
				$q-> where("`rmk`.export_ut IS NULL");
			}
		}

		// Displays records in a random order (testing feature) - Makes paging useless
		// Disabled
// 		if(isset($xfilters['random'])) {
// 			$q-> order("RAND()");
// 		}

		// Search filters //
		foreach($params['filters'] as $fkey=> $filter_set) {
			$filter_info = $this-> gridField_2_dbField($fkey);
			switch($filter_info['type']) {
				case 'priority':
					// With Not allocated, the priority filter is ignored (there is no priority!)
					if($xfilters['exgroup'] == 'noorg')
						continue;

					foreach($filter_set as &$filter_item)
						if(is_array($filter_item['v'])) {
							foreach($filter_item['v'] as &$val) {
								if($val != "(unavailable)")
									$val = array_search($val, $this-> current_rt['priorities_ex']);
							}
							unset($val);
							$filter_item['v'] = array_filter($filter_item['v']);
						} else
							if($filter_item['v'] == "(unavailable)") {
								$filter_item['v'] = null;
								$filter_item['c'] = "NULL";
							} else
								$filter_item['v'] = array_search($filter_item['v'], $this-> current_rt['priorities_ex']);
					goto standard_filter;

				case 'status':
					// With "Not allocated", the status filter is ignored (as there are no statuses)
					if($xfilters['exgroup'] == 'noorg')
						continue;

					foreach($filter_set as &$filter_item)
						if(is_array($filter_item['v'])) {
							foreach($filter_item['v'] as &$val) {
								if($val != "(unavailable)")
									$val = array_search($val, $this-> current_rt['statuses_ex']);
							}
							unset($val);
							$filter_item['v'] = array_filter($filter_item['v']);
						} else {
							if($filter_item['v'] == "(unavailable)") {
								$filter_item['v'] = null;
								$filter_item['c'] = "NULL";
							} else
								$filter_item['v'] = array_search($filter_item['v'], $this-> current_rt['statuses_ex']);
						}
					goto standard_filter;

				case 'ip2long':
					foreach($filter_set as &$filter_item)
						$filter_item['v'] = sprintf('%u', ip2long($filter_item['v']));
					goto standard_filter;

				case 'unixtime': // Search between two unixtime numbers (large integer)
					$q-> where("{$filter_info['dbf']} BETWEEN ? AND ?", strtotime(substr($filter_set[0]['v'], 0, 34)), strtotime(substr($filter_set[1]['v'], 0, 34)));
					break;

				case 'mysqldt': // This field is based on MySQL Datetime ( Y-m-d H:i:s ), and not numeric value
					$q-> where("{$filter_info['dbf']} BETWEEN ? AND ?", date("Y-m-d H:i:s", strtotime(substr($filter_set[0]['v'], 0, 34))), date("Y-m-d H:i:s", strtotime(substr($filter_set[1]['v'], 0, 34))));
					break;

				case 'notnull':
				case 'notempty':
					if($filter_set[0]['v'] == 'true')
						$q-> where("{$filter_info['dbf']} IS NOT NULL AND {$filter_info['dbf']} != ''");
					else
						$q-> where("{$filter_info['dbf']} IS NULL OR {$filter_info['dbf']} = ''");
					break;

/*
				case 'id_set': // Used for filtering by a list of numbers (Disabled for now)
					if(empty($filter_set)) {
						// error, no ids valid, return no results
						$q = null;
						return;
					}

					if(count($filter_set) > 500) // hard cap on the $filter_set size ( makes the query too long and script crashes if allowed for more )
						$filter_set = array_slice($filter_set, 0, 500);

					$q-> where("{$filter_info['dbf']} IN(?)", $filter_set);
					break;
*/

				case 'xfield':
				case 'standard': // Standard field, nothing special, and filter type actually comes from the interface grid, as $filter_set[]['c']
				standard_filter:
					$where_string = ""; unset($filter_item);
					foreach($filter_set as $idx=> &$filter_item) {
						if($filter_info['type'] == 'xfield') {
							// Because Xfields store data in different ways, the searched data needs to be sometimes transformed before applied into the query
							$fdef = &$filter_info['fdef'];
							switch($fdef['t']) {
								case 'text':
									// text is not changed
									break;

								case 'number':

									break;
								case 'date':
									// numbers are usually integers
									if($filter_info['where_field'] == 'int_value')
										$filter_item['v'] = strtotime($filter_item['v']);
									break;

								case 'enum':
								case 'set':
									// for enum and set, transform back from the visible format into the stored format
									if(is_array($filter_item['v'])) {
										foreach($filter_item['v'] as &$val) {
											if($val != "(unavailable)") {
												$val = array_search($val, $fdef['cd_ex']);
												if($fdef['t'] == 'enum' && $filter_info['where_field'] == 'int_value')
													$val = (int)$val;
											} else
												$val = null;
										}
										unset($val);
									} else {
										$filter_item['v'] = array_search($filter_item['v'], $fdef['cd_ex']);
										// - for "enum" the value might be integer
										if($fdef['t'] == 'enum' && $filter_info['where_field'] == 'int_value')
											$filter_item['v'] = (int)$filter_item['v'];
									}
									// - for set, the values are stored inside quotes, and they must be searched with 'CONTAINS', like text fields
									if($fdef['t'] == 'set') {
										if(is_array($filter_item['v']))
											foreach($filter_item['v'] as &$val)
												$val = '"'.$val.'"';
											unset($val);
											if($filter_item['c'] == "IN_SET")
												$filter_item['c'] = "CONTAINS";
										else {
											$filter_item['v'] = '"'.$filter_item['v'].'"';
											if($filter_item['c'] == 'EQUAL')
												$filter_item['c'] = "CONTAINS";
											else
												$filter_item['c'] = "DOES_NOT_CONTAIN";
										}
									}
									break;

								default:
									$filter_info['plugin']-> fdef = &$filter_info['fdef'];
									// only the plugin knows how the data is stored, so use a plugin method to transform the value from the input into something searchable
									$filter_info['plugin']-> prepare_Data_Search($filter_item, $filter_info);
 									// test if the plugin returned NULL, in this case, it means the value can't be converted, and there are no results
 									if(is_null($filter_item['v']))
										continue;
							}
							if(isset($filter_item['cast']))
								$filter_info['dbf'] = "CAST(`xf_{$filter_info['fkey']}`.{$filter_info['where_field']} AS {$filter_item['cast']})";
							else
								$filter_info['dbf'] = "`xf_{$filter_info['fkey']}`.{$filter_info['where_field']}";
						}

						if(is_string($filter_item['v']))
							$filter_item['v'] = trim($filter_item['v']);
						if(!is_null($filter_item['v']) && $filter_item['v']!==0 && empty($filter_item['v']))
							continue;
						// Prepend and append
						if(isset($filter_info['prepend']))
							$filter_item['v'] = $filter_info['prepend'].$filter_item['v'];
						if(isset($filter_info['append']))
							$filter_item['v'] = $filter_item['v'].$filter_info['append'];
						// Condition override
						if(isset($filter_info['condition']))
							$filter_item['c'] = $filter_info['condition'];

						switch($filter_item['c']) {
							case "CONTAINS":
							case "CONTAINS_CASE_SENSITIVE":
								if(is_array($filter_item['v'])) {
									// This will generate an OR case for each value
									$multi_contains = array();
									foreach($filter_item['v'] as $val)
										$multi_contains[] = $filter_info['dbf'].' '. $q-> quote_Into("LIKE ?", "%{$val}%");
									$where_string.= ($idx?" OR ":"").implode(" OR ", $multi_contains);
								} else
									$where_string.= ($idx?" OR ":""). $filter_info['dbf'].' '. $q-> quote_Into("LIKE ?", "%{$filter_item['v']}%"); break;
							case "DOES_NOT_CONTAIN":
							case "DOES_NOT_CONTAIN_CASE_SENSITIVE":
								$where_string.= ($idx?" AND ":""). $filter_info['dbf'].' '.$q-> quote_Into("NOT LIKE ?", "%{$filter_item['v']}%"); break;
							case "EQUAL":
							case "EQUAL_CASE_SENSITIVE":
								$where_string.= ($idx?" OR ":""). $filter_info['dbf'].' '.$q-> quote_Into("= ?", $filter_item['v']); break;
							case "BETWEEN":
								$where_string.= ($idx?" AND ":""). $filter_info['dbf'].' '.$q-> quote_Into("BETWEEN ? AND ?", $filter_item['v'][0], $filter_item['v'][1]); break;
							case "NOT_EQUAL":
								$where_string.= ($idx?" AND ":""). $filter_info['dbf'].' '.$q-> quote_Into("<> ?", $filter_item['v']); break;
							case "IN_SET": // This is not directly sent by the grid, instead, multiple values (from checkbox filter) are converted into the "SET" condition
								$where_string.= ($idx?" AND ":""). $filter_info['dbf'].' '.$q-> quote_Into("IN(?)", $filter_item['v']); break;
							case "NOT_IN_SET":
								$where_string.= ($idx?" AND ":""). $q-> quote_Into("{$filter_info['dbf']} NOT IN(?) OR {$filter_info['dbf']} IS NULL", $filter_item['v']); break;
							case "GREATER_THAN":
								$where_string.= ($idx?" AND ":""). $filter_info['dbf'].' '.$q-> quote_Into("> ?", $filter_item['v']); break;
							case "LESS_THAN":
								$where_string.= ($idx?" AND ":""). $filter_info['dbf'].' '.$q-> quote_Into("< ?", $filter_item['v']); break;
							case "GREATER_THAN_OR_EQUAL":
								$where_string.= ($idx?" AND ":""). $filter_info['dbf'].' '.$q-> quote_Into(">= ?", $filter_item['v']); break;
							case "LESS_THAN_OR_EQUAL":
								$where_string.= ($idx?" AND ":""). $filter_info['dbf'].' '.$q-> quote_Into("<= ?", $filter_item['v']); break;
							case "STARTS_WITH":
							case "STARTS_WITH_CASE_SENSITIVE":
								$where_string.= ($idx?" OR ":""). $filter_info['dbf'].' '.$q-> quote_Into("LIKE ?", "{$filter_item['v']}%"); break;
							case "ENDS_WITH":
							case "ENDS_WITH_CASE_SENSITIVE":
								$where_string.= ($idx?" OR ":""). $filter_info['dbf'].' '.$q-> quote_Into("LIKE ?", "%{$filter_item['v']}"); break;
							case "NULL":
							case "EMPTY":
								$where_string.= ($idx?" AND ":""). "({$filter_info['dbf']} IS NULL OR {$filter_info['dbf']} = '')"; break;
							case "NOT_NULL":
							case "NOT_EMPTY":
								$where_string.= ($idx?" OR ":""). "({$filter_info['dbf']} IS NOT NULL AND {$filter_info['dbf']} != '')"; break;
						}

						// Allow the plugin to add an extra condition to the query (always AND)
						if(!empty($filter_item['extra_c']))
							$where_string.= " AND ". $filter_item['extra_c'];
					}

					if(!empty($where_string))
						$q-> where($where_string);
					unset($filter_item);
					break;
			}
		}
	}

	/**
	 * Get list of issues (according to user's rights and active filters + paging)
	 * Returns JSON array for jqxGrid
	 */
	public function action_Get_List(MVC_Request $rq)
	{	
		if(!isset(MVC_Core::$cache-> listing_columns)) {
			MVC_Core::$debug-> dump("Missing visible column list from the session cache", null, null, 'error');
			return array();
		}

		// 1. Custom vars
		$ss_custom = $rq-> custom;
		// Expand Xfilters
		if(isset($ss_custom['xfilters'])) {
			$ss_custom['xfilters'] = explode('|', $ss_custom['xfilters']);
			if(isset($ss_custom['xfilters'][1]))
				$ss_custom['xfilters'][1] = explode(',', $ss_custom['xfilters'][1]);
		}

		// 2. Server-side Filters (Primary/Per org) + Xfield filters
		if($rq-> filterscount > 0) {
			$ss_filters = json_decode($rq-> filters, true);
			$xf_filters = array();
			foreach($ss_filters as $field=> $filterdata) {
				if(!(substr($field,0,1) == '_' || substr($field,0,1) == '@')) // found an Xfield filter, this will require extra joins
					$xf_filters[$field] = $filterdata;
			}
		} else
			$ss_filters = array();

		// Init WTR, it will be filled with various things . By default it reads the two main tables (Primary and Organization Specific)
		// - org_specific is always needed for the "process marker" function to work
		$wtr = array('issue', 'org_specific');
		if($this-> user-> is_Unrestricted()) {
			if(isset(MVC_Core::$ss-> perspective))
				$wtr['org_id'] = MVC_Core::$ss-> perspective; // Could be 0, in which case all issues from all organizations are shown
		} else
			$wtr['org_id'] = MVC_Core::$ss-> user['org_id']; // Normal user accesses only issues visible in his own organization
		// implied "else" is when user is admin, no "perspective" used, all issues are shown.

		if(!empty($ss_custom['xfilters'][1]) && (in_array('unopen', $ss_custom['xfilters'][1]) || in_array('unexp', $ss_custom['xfilters'][1]))) {
			$wtr[] = 'marker';
			$wtr['user_id'] = $this-> user-> ID();
		}

		// Go through all the visible columns and setup what is being read and from which table, determining all necessary joins
		// - Primary issue table
		// - Secondary "Per-Organization" table
		// - Contacts table (several cases)
		// - Organization table (master org, submitter org)
		// - Users table (Assigned user)
		// - Markers table (user-to-record information)
 		$visible_cols = MVC_Core::$cache-> listing_columns;
		// array_push($visible_cols,"chp_no");
		// echo "1220<pre>";
		// print_r($visible_cols);
		// exit;
		$fields = array(); $xfields = array();

		// 3. Server-side Sort
		if($rq-> sortdatafield && $rq-> sortorder) {
			// Determine if the sorting field is one of the primaries or an xfield, in case it's x-field another join is necessary
			$sort_field = $this-> gridField_2_dbField($rq-> sortdatafield);

			// Sorting by a direct field or an xfield ?
			if($sort_field['type'] == 'xfield') {
				if(!isset($xf_filters) || !isset($xf_filters[$sort_field['fkey']])) {
					// This field is not used for filtering, so set it to be joined for the "ordering" purpose.
					if(!isset($wtr['custom']))
						$wtr['custom'] = array();
					$wtr['custom'][] = array(
						'tab'=> MVC_Core::$db-> table('ixf', true, "xf_{$sort_field['fkey']}"),
						'fields'=> false,
						'on'=> "`xf_{$sort_field['fkey']}`.issue_id = `iss`.id AND `xf_{$sort_field['fkey']}`.fk_id = ".$this-> current_rt['xfield_def'][$sort_field['fkey']]['id']
					);
				}
				$db_field = "`xf_{$sort_field['fkey']}`.{$sort_field['where_field']}";

				// For numbers, the field is cast as decimal for correct sorting even if in the non-number column
				if($sort_field['fdef']['t'] == 'number' || $sort_field['fdef']['t'] == 'money')
					$db_field = "CAST({$db_field} AS DECIMAL)";
				// For date elements, a tweak is added to sort NULLS after the actual date (aka, move NULLS from Always to Never)
				elseif($sort_field['fdef']['t'] == 'date' && $rq-> sortorder != 'desc')
					$db_field = "IF(ISNULL({$db_field}),1,0), {$db_field}";

				// Extra: Money fields are sorted by currency first (before sorting by value)
				// Disabled - this is not working properly, returning seemingly arbitrary results (CALC FOUND ROWS is fucking it up)
				//if($sort_field['fdef']['t'] == 'money')
				//	$db_field = "`xf_{$sort_field['fkey']}`.int_value ASC, ".$db_field;
			} elseif(($sort_field['type'] == 'unixtime' || $sort_field['type'] == 'mysqldt') && $rq-> sortorder != 'desc') {
				// For time fields (of any time, add the "nulls last" trick)
				$db_field = "IF(ISNULL({$sort_field['dbf']}),1,0), {$sort_field['dbf']}";
			} else
				$db_field = $sort_field['dbf'];

			$ss_sort = "{$db_field} {$rq-> sortorder}";
		}

		if($rq-> groupscount) {
			$complex_sort = "";
			// When grouping, insert the grouped column as ascending sorting (TODO: Allow revers group sorting)
			for($g = 0; $g < $rq-> groupscount; $g++) {
				if(!empty($ss_sort) && $rq-> {"group".$g} == $rq-> sortdatafield) {
					$complex_sort.=  (!empty($complex_sort)?', ':''). $ss_sort;
					$default_sort_added = true;
					continue; // Do not attempt to add another sorting if the grid is already sorted by the grouping field
				}

				$g_field = $this-> gridField_2_dbField($rq-> {"group".$g});

				if(isset($g_field['prepend']))
					// Not possible to sort correctly by fields contained into a JSON structure, so skip this field
					continue;

				// Grouping by a direct field or an xfield ?
				if($g_field['type'] == 'xfield') {
					if(!isset($xf_filters) || !isset($xf_filters[$g_field['fkey']])) {
						// This field is not used for filtering, so set it to be joined for the "ordering" purpose.
						if(!isset($wtr['custom']))
							$wtr['custom'] = array();
						$wtr['custom'][] = array(
							'tab'=> MVC_Core::$db-> table('ixf', true, "xf_{$g_field['fkey']}"),
							'fields'=> false,
							'on'=> "`xf_{$g_field['fkey']}`.issue_id = `iss`.id AND `xf_{$g_field['fkey']}`.fk_id = ".$this-> current_rt['xfield_def'][$g_field['fkey']]['id']
						);
					}
					if(empty($ss_sort) || $sort_field['type'] != 'xfield' || ($sort_field['type'] == 'xfield' && $g_field['fkey'] != $sort_field['fkey']))
						$complex_sort.= (!empty($complex_sort)?', ':''). "`xf_{$g_field['fkey']}`.{$g_field['where_field']}";
				} else
					if(empty($ss_sort) || $sort_field['type'] == 'xfield' || $g_field['dbf'] != $sort_field['dbf'])
						$complex_sort.= (!empty($complex_sort)?', ':''). "{$g_field['dbf']}";
			}

			if(!isset($default_sort_added) && !empty($complex_sort) && !empty($ss_sort))
				$complex_sort.=', '.$ss_sort;
			$ss_sort = $complex_sort;
		}

		// When no grouping, no sorting, just show newest created records (id DESC), since it's the PRIMARY index
		if(empty($ss_sort))
			$ss_sort = "`iss`.id DESC";
			
		foreach($visible_cols as $col) {
			//print $col;
			if(substr($col, 0, 1)=='_') // built in columns (can be from primary table or some associated join)
				switch(substr($col, 1)) {
					// Fields coming from the primary table, direct name
					case 'id':
					case 'ctime':
					case 'edtime':
					case 'submit_domain':
					case 'submit_ip':
						$fields[] = substr($col, 1);		break;
					// Fields related to primary table, however the search is performed on a joined table
					case 'master_org':	// Master organization, current handler of the issue (useful to determin if stuff has been transferred away or is still owned. Also for admin)
						if(!in_array('organization', $wtr))
							$wtr[] = 'organization';		break;
					case 'shared':		// The Checkbox-type shared column needs the content of this field
						$fields[] = 'shared_org_ids';		break;
					//case 'slaLevel_achi':
					//	if(!in_array('slaLevel_achi', $wtr))
					//		$wtr[] = 'slaLevel_achi'; break;
					case 'group':		// The issue's group record
						if(!in_array('group', $wtr))
							$wtr[] = 'group';				break;
					case 'submit_org':
						if(!in_array('submitter_organization', $wtr))
							$wtr[] = 'submitter_organization'; break;
					case 'submit_name':
					case 'submit_email':
						if(!in_array('submitter', $wtr))
							$wtr[] = 'submitter';			break;
					case 'c_files':
					case 'c_convos':
					case 'c_tasks':
					case 'c_docs':
					case 'c_ctx':
					case 'c_updates':
					case 'sui_lmdt':
					case 'sui_lmctx':
						if(!$this-> user-> is_Unrestricted())
							continue;
						$fields[] = substr($col, 1);		break;

					// Fields in the secondary per-organization table
					case 'deps':
						$wtr[] = 'deps';
						if(!in_array('dep_ids', $fields))
							$fields[] = 'dep_ids';			break;
					case 'process':
					case 'linktime':
					case 'time_open':
					case 'time_start':
 					case 'utime':
 					case 'time_close':
 					case 'pending_length':
 					case 'process_length':
					case 'filesize_total':
					case 'count_active_tasks':
					case 'count_propfields':
					case 'commtime':
					case 'xfer_time':
					case 'xfer_ip':
					case 'xfer_confirmip':
						if(!in_array(substr($col, 1), $fields))
							$fields[] = substr($col, 1);	break;
					case 'xfer_delivered':		// The checkbox-type column which indicates if transfer/share has been "confirmed" (without needing the IP address itself)
						if(!in_array('xfer_confirmip', $fields))
							$fields[] = 'xfer_confirmip';	break;
					case 'priority':
						$fields[] = 'priority_key';			break;
					case 'status':
						$fields[] = 'status_key';			break;
					case 'sender_name':
					case 'sender_email':
						if(!in_array('xfer_by', $wtr))
							$wtr[] = 'xfer_by';				break;
					case 'recipient_name':
					case 'recipient_email':
						if(!in_array('xfer_to', $wtr))
							$wtr[] = 'xfer_to';				break;
					case 'assigned':
						if(!in_array('assigned', $wtr))
							$wtr[] = 'assigned';			break;
					case 'access':
					case 'export':
						if(!in_array('marker', $wtr)) {
							$wtr[] = 'marker';
							$wtr['user_id'] = $this-> user-> ID();
						}									break;
					default:
						// Cached fields can have arbitrary keys, but they match the database field
						if(substr($col, 0, 7)=='_cache_')
							$fields[] = substr($col, 1);
				}
			elseif(substr($col, 0, 1)=='@') {
				// The submitter contact with it's xdata decoded must be read for any of the @contact columns
				if(!in_array('submitter_xdata', $wtr)) {
					$wtr[] = 'submitter';
					$wtr[] = 'submitter_xdata';
				}
			} else
				// Everything else is an xfield
				// echo "<pre>";
				// print_r($col);
				// exit;
				$xfields[] = $col;
		}
		
		// Direct fields
		if(!empty($fields))
			$wtr['flist'] = $fields;
		else
			$wtr['flist'] = array();
		// Xfields
		if(!empty($xfields)) {
			$wtr[] = 'xfields';
			$wtr['xflist'] = $xfields;
			// Are there filters on xfields ? If yes, make extra joins
			if(isset($xf_filters)) {
				if(!isset($wtr['custom']))
					$wtr['custom'] = array();
				foreach($xf_filters as $fkey=> $xff) {
					if($fkey[0]=='~') { // Using attached data search?
						$fkey=substr($fkey, 1);
						if(isset($xf_filters[$fkey]))
							continue; // Already using the same field in direct search (not attached data), can't join the table twice
					}
					$wtr['custom'][] = array(
						'tab'=> MVC_Core::$db-> table('ixf', true, "xf_{$fkey}"),
						'fields'=> false,
						'on'=> "`xf_{$fkey}`.issue_id = `iss`.id AND `xf_{$fkey}`.fk_id = ".$this-> current_rt['xfield_def'][$fkey]['id']
					);
				
				}
			}
		}

		$rt_id = $this-> rt_Return();
		$issues = $this-> record-> get_List($rt_id, $wtr, array($this, "filter_Get_List"), array(
			'custom'=> $ss_custom,
			'filters'=> $ss_filters
		), $ss_sort, $rq-> pagesize, $rq-> pagenum * $rq-> pagesize, true, true); 

 
		// true(1) = CALC FOUND!, true(2) = Format for Grid (all xfields become stringifiable)
		
		if($issues && count($issues)> 0) {
			// a bit of processing on the values before sending the data

			

			foreach($issues as &$issue) {
                
               $issue['id'] = (int)$issue['id'];
			    // 15012025 grid changes code changes start
					if($this-> current_rt['base_id'] == 34){
						$checkIsCapa = $this-> record-> get_capa_data_grid_capa_list(34,(int)$issue['id']);
						$issue['isCapa'] = $checkIsCapa['total'] > 0 ? $checkIsCapa['total'] : 0;
					}
				// 15012025 grid changes code changes end
                                 
                                 
			    // get active published doc details start
                                if($this-> current_rt['base_id'] == 12){
//                                        $checkIsActiveDoc = $this-> record-> get_active_published_doc_list(12,(int)$issue['id']);
//                                        $issue['isCapa'] = $checkIsCapa['total'] > 0 ? $checkIsCapa['total'] : 0;
                                    
                                    $checkActiveDocList = $this->record-> get_active_published_doc_list(12,(int)$issue['id']);
//                                    $issue['active_docs']['data']=$checkActiveDocList;
                                    $issue['active_docs']['count']=count($checkActiveDocList) ;
                                }
                            // get active published doc details end
                            // get connected doc details start
                                if($this-> current_rt['base_id'] == 6){
                                    $checkConnectedDocList = $this->record-> get_connected_doc_list(6,(int)$issue['id']);
                                    $issue['active_docs']['count']=count($checkConnectedDocList) ;
                                }
                            // get connected doc details end
                                        
                                        
				// Times which are internally stored as ints are returned as DATETIME instead (so they can be converted to Date() by the grid)
				$int_times = array('ctime', 'edtime', 'linktime', 'commtime', 'time_open', 'time_start', 'access_ut', 'time_close');
				foreach($int_times as $int_t)
					if(!empty($issue[$int_t]))
						$issue[$int_t] = date("Y-m-d H:i:s", $issue[$int_t]);
				// Assigned Set
				if(!empty($issue['assigned']['time']))
					$issue['assigned']['time'] = date("Y-m-d H:i:s", $issue['assigned']['time']);
				// Marker Set
				if(!empty($issue['marker']['access_ut']))
					$issue['marker']['access_ut'] = date("Y-m-d H:i:s", $issue['marker']['access_ut']);
				if(!empty($issue['marker']['export_ut']))
					$issue['marker']['export_ut'] = date("Y-m-d H:i:s", $issue['marker']['export_ut']);
				// Xfer Set
				if(!empty($issue['xfer']['time']))
					$issue['xfer']['time'] = date("Y-m-d H:i:s", $issue['xfer']['time']);
            }
                
			// echo "1522<pre>";
			// print_r($issues);
			// exit;

			// Send the issue data to the jqxGrid
			$this-> send_JSON(array(
				"found_rows"=> $this-> record-> found_rows,
				"data"=> array_values($issues),
				"issues data"=> $wtr,
				"wtr org_id"=>$wtr['org_id'],
				"ss_filters"=> $ss_filters,
				'ss_custom'=>$ss_custom,
				'current_rt'=>$this-> current_rt['base_id']

				
			));
		}
		else
			$this-> send_JSON(array());
	}

	/**
	 * Saves a view ID as the active one for current user and record type. The grid will go back to this view even after logoff.
	 * - In order to change active view, another view should be loaded or [Reset search] must be pressed.
	 * - User can change the columns, filters and so on but such changes are not maintained after logout (system will revert to the active view), unless the user saves/updates the view.
	 * @param unknown $view_id
	 */
	protected function set_Active_View($view_id)
	{
		if(!isset(MVC_Core::$cache-> grid_views))
			MVC_Core::$cache-> grid_views = array();

		// 1. Save or clear the view_id into local cache
		if(empty($view_id)) {
			$grid_views = MVC_Core::$cache-> grid_views; unset($grid_views[$this-> current_rt['key']]); MVC_Core::$cache-> grid_views = $grid_views;
			$view_info = MVC_Core::$cache-> view_info; unset($view_info[$this-> current_rt['key']]); MVC_Core::$cache-> view_info = $view_info;
		} else {
			MVC_Core::$cache-> grid_views[$this-> current_rt['key']] = (int)$view_id;
		}

		// 2. Save the view_id into user profiles (for persistence) - This will replace multiple "columns_ist_N" with the view IDs once the columns editor will operate on views directly
		$this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "grid_views", MVC_Core::$cache-> grid_views, false, "Current active grid views");
	}

	/**
	 * Saves the list of selected columns into the user's profile
	 */
	public function action_Save_Column_List(MVC_Request $post)
	{
		if(!empty(MVC_Core::$cache-> view_info[$this-> current_rt['key']])) {
			$ctitle = MVC_Core::$cache-> view_info[$this-> current_rt['key']];
			if(substr($ctitle, -11, 11) != " (modified)")
				MVC_Core::$cache-> view_info[$this-> current_rt['key']] = $ctitle." (modified)";
		}

		$rt_id = $this-> rt_Return();

 		$this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "columns_ist_{$rt_id}", $post-> columns, true, "User columns");

 		$this-> evan-> add("Column configuration has been updated", "ok");
		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Resets the column list to the predefined default for that type, by the stored profile info and disabling active view
	 * @param MVC_Request $post
	 */
	public function action_Reset_Grid(MVC_Request $post)
	{
		$rt_id = $this-> rt_Return();

		// Request deletion by passing null data (which would auto-reload the organization default)
		$this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "columns_ist_{$rt_id}", null);
		$this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "settings_ist_{$rt_id}", null);

		// Also clears any grid state, active view and special settings that might have been saved (Most likely not valid anymore with different columns)
		unset(MVC_Core::$cache-> grid_state[$this-> current_rt['key']]);
		$this-> set_Active_View(null);
		unset(MVC_Core::$cache-> grid_ss);

		$this-> evan-> add("Grid configuration has been reset to the organization default", "ok");
		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Changes the stored user preferences for the active issue type for how the grid functions (grouping, sorting, paging, view version)
	 * @param MVC_Request $post
	 */
	public function action_Grid_Settings(MVC_Request $post)
	{
		$rt_id = $this-> rt_Return();

		// Attempt to read the grid settings
		$settings = $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "settings_ist_{$rt_id}");
		if(!$settings) {
			// If there are no settings, initialize with some defaults (and also save these new defaults)
			$settings = array(
				'group'=> "prio",
				'sort'=> "ctime",
				'order'=> "desc", // Normal sorting for times is actually reversed
				'sizes'=> array(15, 25, 50, 100, 150, 250, 500, 1000),
				'page'=> 25
			);
			$this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "settings_ist_{$rt_id}", $settings, false, "Init Default Settings");
		}

		// Now depending on which setting is being changed, some parameters are affected
		switch($post-> setting) {
			case 'group':
				if($post-> mode == $settings['group'])
					return $this-> send_Fail_JSON("The same grouping is already active");
				$settings['group'] = $post-> mode;
				$message = "grouping mode set.";
				break;
			case 'sort':
				if($post-> mode == 'for' || $post-> mode == 'rev') {
					// Changing sort order?
					if($settings['sort'] == 'track')
						$new_order = $post-> mode == 'for'? 'asc': 'desc';
					else
						$new_order = $post-> mode == 'rev'? 'asc': 'desc';
					if($new_order == $settings['order'])
						return $this-> send_Fail_JSON("The same sort order is already active");
					$settings['order'] = $new_order;
					$message = "sort order changed.";
				} else {
					$old_sort = $settings['sort'];
					$settings['sort'] = $post-> mode;
					if($settings['sort'] == $old_sort)
						return $this-> send_Fail_JSON("The same sorting type is already active");
					// Reverse sort if switching from Track to Time and vice-versa
					if($settings['sort'] == 'track' && $old_sort != 'track' || $settings['sort'] != 'track' && $old_sort == 'track')
						$settings['order'] = $settings['order'] == 'asc'? 'desc': 'asc';
					$message = "sort field set.";
				}
				break;
			case 'page':
				// TODO
				$message = "paging defaults adjusted.";
				break;
		}

		$this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "settings_ist_{$rt_id}", $settings, false, "User settings");
		$this-> evan-> add("Grid default configuration updated, ". $message, "ok");

		// This also resets the custom grid state when changing defaults.
		unset(MVC_Core::$cache-> grid_state[$this-> current_rt['key']]);
		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * New ability to change how the grid module behaves globally (not affected by record type)
	 * Stored in session, it allows the grid to be viewed with a different width than the rest of the application, to self-refresh the grid itself (keeping the current view) and other functions
	 *
	 * @param MVC_Request $post
	 */
	public function action_Special_Settings(MVC_Request $post)
	{
		$post-> add_Filter('hide_trackid', 'boolean');

		$grid_ss = array(
			'width'=> (int)$post-> width,
			'autoref_s'=> (int)$post-> autoref_s,
			'hide_trackid'=> (bool)$post-> hide_trackid
		);

		if($grid_ss['width'] < 950 || $grid_ss['width'] > 4070)
			return header("HTTP/1.0 400 Out of range value for the screen width");
		if($grid_ss['autoref_s'] < 0 || $grid_ss['autoref_s'] > 600)
			return header("HTTP/1.0 400 Out of range value for grid autorefresh");

		MVC_Core::$cache-> grid_ss = $grid_ss;

                
		// Saved, return OK and reload.
		$this-> evan-> add("Grid: Special Settings are active until logout", "ok");
		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Followed by a grid operation which affects returned data (filter, sort, page size, page number), saves the current values into the session so the grid can be reloaded within the same state
	 * @param MVC_Request $post
	 */
	public function action_Save_Grid_State(MVC_Request $post)
	{
		// Is it a session state clear?
		if($post-> state == 'false') {
			unset(MVC_Core::$cache-> grid_state[$this-> current_rt['key']]);
			// ... if there's an active view, it shows as "modified" (as potential filters/page number in the view are removed)
			// It might not be actually modified if the view didn't have any filters in it
			if(!empty(MVC_Core::$cache-> view_info[$this-> current_rt['key']])) {
				$ctitle = MVC_Core::$cache-> view_info[$this-> current_rt['key']];
				if(substr($ctitle, -11, 11) != " (modified)")
					MVC_Core::$cache-> view_info[$this-> current_rt['key']] = $ctitle." (modified)";
			}

			return $this-> send_JSON(array('success'=> true));
		}

		$grid_state = json_decode($post-> state, true);
		if(empty($grid_state))
			return header("HTTP/1.0 500 Unable to save grid state. You can ignore this message but if it repeats often please contact support.");

		// Save the state into the session
		if(!isset(MVC_Core::$cache-> grid_state))
			MVC_Core::$cache-> grid_state = array();
		MVC_Core::$cache-> grid_state[$this-> current_rt['key']] = $grid_state;

		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Uses the new Grid Views data model to store current grid configuration into a view
	 * The "columns" part is taken from the user profile
	 * -- Later this should disappear and ONLY the new views model should be used;
	 * -- Instead, the profile will store an information on which View (ID) is used (Grid will autoload into last used view), separately for each record type
	 *
	 * @param MVC_Request $post
	 */
	public function action_Store_Grid_View(MVC_Request $post)
	{
		$post-> add_Filter('sss', 'boolean');
		$this-> gridview = $this-> factory_Model('GridView');

		if(empty($post-> id)) {
			// Create mode - A new view is generated for current user, based on the old method "columns_ist_N"
			$view = array(
				'name'=> trim($post-> name),
				'rt_key'=> $this-> current_rt['key'],
				'visibility'=> 'user',	// Defaults to user in case visibility is missing from the request (as it will be missing for most normal users)
				'source'=> 'user', // Saved by the user himself
				'columns'=> $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "columns_ist_".$this-> current_rt['id'], true, 1),
				'state'=> $post-> state
			);
			// Save current grid's Special Settings inside this view ?
			if($post-> sss && !empty(MVC_Core::$cache-> grid_ss))
				$view = array_merge($view, MVC_Core::$cache-> grid_ss);

			// If the user has the ability to store views for his active department or organization, make sure it happens:
			if(!empty($post-> visibility))
				$view['visibility'] = $post-> visibility;

			switch($view['visibility']) {
				case 'user':
					$view['link_id'] = MVC_Core::$ss-> user['id']; break;
				case 'dep':
					$view['link_id'] = MVC_Core::$ss-> user['cdep_id']; break;
				case 'org':
					unset($view['link_id']);
			}

			$view_id = $this-> gridview-> create(MVC_Core::$ss-> user['org_id'], $view);
			if(empty($view_id))
				return header("HTTP/1.0 500 Unable to store the view in the database");
		} else {
			// Updating an existing view
			$view_id = (int)$post-> id;

			$view_update = array(
				'columns'=> $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "columns_ist_".$this-> current_rt['id'], true, 1),
				'state'=> $post-> state
			);

			if(strlen($post-> name) > 3)
				$view_update['name'] = trim($post-> name);

			// Save current grid's Special Settings inside this view ?
			if($post-> sss && !empty(MVC_Core::$cache-> grid_ss))
				$view_update = array_merge($view_update, MVC_Core::$cache-> grid_ss);

			// If the user has the ability to store views for his active department or organization, make sure it happens:
			if(!empty($post-> visibility)) {
				$view_update['visibility'] = $post-> visibility;

				switch($view_update['visibility']) {
					case 'user':
						$view_update['link_id'] = MVC_Core::$ss-> user['id']; break;
					case 'dep':
						$view_update['link_id'] = MVC_Core::$ss-> user['cdep_id']; break;
					case 'org':
						$view_update['link_id'] = null;
				}
			}

 			$r = $this-> gridview-> update($view_id, $view_update); // org_id, rt_key are preserved here
 			if(!$r)
 				return header("HTTP/1.0 500 An error occured while updating the view");
		}

		// Remember which grid view was just saved - It becomes the user's active view (even after logoff), until another view is loaded or the grid is reset
		$this-> set_Active_View($view_id);

		// And initialize the grid state and title/header based on the view that was just saved
		$this-> initialize_View($view_id);

		// This is a reload, so use notification system
		$this-> evan-> add(sprintf("The view <b>%s</b> has been stored", $post-> name), "add");
		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Loads an exiting "View" by replacing the default with the stored data in the specified variant
	 * @param MVC_Request $post
	 */
	public function action_Load_Grid_View(MVC_Request $post)
	{
		if(empty($post-> id))
			return header("HTTP/1.0 400 Grid View ID not present in the request, cannot load the view.");

		// Read the view
		$view = $this-> initialize_View($post-> id);
		if(empty($view))
			return header("HTTP/1.0 404 View not found. It might have been deleted.");

		// Ok, found - Set the loaded one as persistent active view and return to reload the grid so the change is applied
		$this-> set_Active_View($view['id']);

		$this-> evan-> add(sprintf("The view <b>%s</b> has been loaded into the grid !", $view['name']), "ok");
		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Removes the selected view. If the selected one is the active one, also removes active view.
	 * - This function doesn't affect current view, so the view can be regenerated by simply saving current view !
	 *
	 * @param MVC_Request $post
	 */
	public function action_Delete_Grid_View(MVC_Request $post)
	{
		if(empty($post-> id))
			return header("HTTP/1.0 400 Grid View ID not present in the request, cannot delete.");

		$this-> gridview = $this-> factory_Model('GridView');
		$r = $this-> gridview-> delete($post-> id);
		if(!$r)
			return header("HTTP/1.0 500 An error occured while deleting the view");

		// If the removed view matches the one in cache, clear the cache (since it won't make any sense anymore)
		if($post-> id == MVC_Core::$cache-> grid_views[$this-> current_rt['key']])
			$this-> set_Active_View(null);

		// Return and reload the grid so the change is applied
		$this-> evan-> add("The view has been removed !", "remove");
		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Admin Only - Copies the admin's current grid settings to the selected organization's default grid view.
	 */
	public function action_Set_Default_View()
	{
		if(!$this-> user-> is_Unrestricted())
			return $this-> send_Fail_JSON("No access to this function. How did you even get here?");

		if(!isset(MVC_Core::$ss-> perspective))
			return $this-> send_Fail_JSON("The organization has not been selected. Cannot save default view.");

		$rt_id = $this-> rt_Return();

 		$selected_cols = $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "columns_ist_{$rt_id}", true);
 		if(!empty($selected_cols))
 			$this-> org-> profile_Default_Write(MVC_Core::$ss-> perspective, "columns_ist_{$rt_id}", $selected_cols, true, "Org. Default", MVC_Core::$ss-> user['id']);

 		$grid_settings = $this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "settings_ist_{$rt_id}", true);
 		if(!empty($grid_settings))
 			$this-> org-> profile_Default_Write(MVC_Core::$ss-> perspective, "settings_ist_{$rt_id}", $grid_settings, true, "Org. Default", MVC_Core::$ss-> user['id']);

 		// Note: Grid state (filters, groups, sorting, pager) is NOT saved !

		$this-> send_JSON(array('success'=> true));
	}

	/**
	 * Checks if the operation is allowed for the logged in user and rejects it with send_Fail_JSON()
	 *
	 * @param string $privilege_to_check
	 * @param string $privilege_error_message
	 * @param MVC_Request $rq
	 * @return array Returns the list of issues after they have been checked
	 */
	protected function check_Operation($privilege_to_check, $privilege_error_message, MVC_Request $rq, &$org_id)
	{
		$rq-> add_Filter('ids', "CSV");

		$ids = $rq-> ids;
		if(empty($ids))
			return $this-> send_Fail_JSON("There are no record IDs to process !");

		$wtr = array('org_specific', 'flist'=> array('assigned_to', 'priority_key', 'status_key', 'dep_ids'));
		if(isset(MVC_Core::$ss-> perspective))
			$wtr['org_id'] = MVC_Core::$ss-> perspective; // Could be 0, in which case all issues from all organizations are shown
		elseif(!$this-> user-> is_Unrestricted())
			$wtr['org_id'] = MVC_Core::$ss-> user['org_id']; // Normal user accesses only issues visible in his own organization

		if(isset($wtr['org_id']))
			$org_id = $wtr['org_id'];
		else
			$org_id = null;

		// read the selected records to see if they exist (and who they are assigned to, for access purposes)
		$issues = $this-> record-> get_List(null, $wtr, function(&$q) use($ids) {
			$q-> where("`id` IN (?)", $ids);
		}, null, null, 0);

		if(empty($issues))
			return $this-> send_Fail_JSON("The specified IDs do not belong to any records (or a database error was encountered)");

		$acl_check = $this-> user-> test_ACL('privileges', $privilege_to_check);
		// TODO: Not sure if assignment should simply override all privileges. It should only be the basic ones (set priority/status change data group, mail, etc., and not everything !)
		foreach($issues as $iss)
			if(!($iss['assigned_to'] == MVC_Core::$ss-> user['id'] || $acl_check))
				return $this-> send_Fail_JSON(sprintf($privilege_error_message, $iss['track']));

		return $issues;
	}

	/**
	 * Sets the status of one or multiple issues
	 */
	public function action_Set_Status_Bulk(MVC_Request $rq)
	{
		$org_id = null;
		$records = $this-> check_Operation('issue_set_status', "You are not allowed to change the status of the record %s (please unselect it).", $rq, $org_id);
		if(empty($records))
			return header("HTTP/1.0 400 No record IDs in the request.");

		// Update all records one by one ( so their history can also be updated ). This also triggers any automations if they exist
		$count = 0; $unallocated_count = 0;
		foreach($records as $rec) {
			if(empty($rec['master_org_id'])) {
				$unallocated_count++;
				continue;
			}

			if($rec['status_key'] == $rq-> status)
				continue; // Nothing to do here, move on

			// Unfortunately this reads the changed issues again, to access the shared org list
			$r = $this-> record-> org_UpdateData($rec['id'], $org_id?: $rec['master_org_id'], array(
				'status_key'=> $rq-> status
			), true);
			if($r) {
				// Clear export markers
				$count++;
				$this-> record-> marker_UpdateAll($rec['id'], array('export_ut'=> false));

				// And add to history that status is now changed
				$this-> record-> history_Create($rec['id'], array(
					"status_key"=> $rq-> status
				), 'statprio');
			}
		}

		$response = "";
		if($count > 0)
			$response.= "The status(es) of {$count} records(s) have been updated.";
		if($unallocated_count > 0)
			$response.= " {$unallocated_count} records are not allocated and have no status.";

		$this-> send_JSON(array('response'=> $response));
	}

	/**
	 * Sets the status of one or multiple issues
	 * TODO: Attempt to merge Status and Priority change methods into ton (like in process.php)
	 */
	public function action_Set_Priority_Bulk(MVC_Request $rq)
	{
		$org_id = null;
		$records = $this-> check_Operation('issue_set_priority', "You are not allowed to change the priority of the issue %s (please unselect it).", $rq, $org_id);
		if(empty($records))
			return header("HTTP/1.0 400 No record IDs in the request.");

		// Update all records one by one ( so their history can also be updated ). This also triggers any automations if they exist
		$count = 0; $unallocated_count = 0;
		foreach($records as $rec) {
			if(empty($rec['master_org_id'])) {
				$unallocated_count++;
				continue;
			}

			if($rec['priority_key'] == $rq-> priority)
				continue; // Nothing to do here, move on

			// Unfortunately this reads the changed issues again, to access the shared org list
			$r = $this-> record-> org_UpdateData($rec['id'], $org_id?: $rec['master_org_id'], array(
				'priority_key'=> $rq-> priority
			), true);
			if($r) {
				// Clear export markers
				$count++;
				$this-> record-> marker_UpdateAll($rec['id'], array('export_ut'=> false));

				// And add to history that priority is now changed
				$this-> record-> history_Create($rec['id'], array(
					"priority_key"=> $rq-> priority
				), 'statprio');
			}
		}

		$response = "";
		if($count > 0)
			$response.= "The priority(es) of {$count} records(s) have been updated.";
		if($unallocated_count > 0)
			$response.= " {$unallocated_count} records are not allocated and have no priority.";

		$this-> send_JSON(array('response'=> $response));
	}

	/**
	 * Grants or Revokes access to a department for several records (department is added/removed to the list)
	 * @param MVC_Request $rq
	 */
	public function action_Dep_Access_Bulk(MVC_Request $rq)
	{
		$org_id = null;
		$records = $this-> check_Operation('issue_access_deps', "You are not allowed to change department visibility.", $rq, $org_id);
		if(empty($records))
			return header("HTTP/1.0 400 No record IDs in the request.");

		if(!in_array($rq-> op, array('grant', 'revoke')))
			return header("HTTP/1.0 400 Invalid department operation requested: {$rq-> op}");

		if(!$rq-> dep_id)
			return header("HTTP/1.0 400 No department ID present in the request");

		// Update all records one by one ( so their history can be properly updated ). This also triggers any automations if they exist
		$count = 0;
		foreach($records as $rec) {
			if(empty($rec['dep_ids']))
				$rec['dep_ids'] = array();
			else
				$rec['dep_ids'] = explode(',', $rec['dep_ids']);
			// Grant or Revoke?
			switch($rq-> op) {
				case 'grant':
					if(!in_array($rq-> dep_id, $rec['dep_ids']))
						$rec['dep_ids'][] = $rq-> dep_id;
					else
						continue 2; // nothing to do, already present
					break;
				case 'revoke':
					if(($key = array_search($rq-> dep_id, $rec['dep_ids']))!==false)
						unset($rec['dep_ids'][$key]);
					else
						continue 2; // nothing to do, already absent
					break;
			}

			$r = $this-> record-> org_UpdateData($rec['id'], $org_id, array(
				'dep_ids'=> count($rec['dep_ids'])? implode(',', $rec['dep_ids']): null
			));
			if($r) {
				$count++;
				$this-> record-> marker_UpdateAll($rec['id'], array('export_ut'=> false));

				// TODO: This does not record anything to history (at the moment access change doesn't even exist as a history entry)
			}
		}

		if($count > 0)
			$response = "Department access was changed for {$count} records(s)";
		else {
			switch($rq-> op) {
				case 'grant':
					$response = "No changes applied, the department already had access to all selected records";
					break;
				case 'revoke':
					$response = "No changes made, the selected records were not accessible to the department";
					break;
			}
		}

		$this-> send_JSON(array('response'=> $response));
	}

	/**
	 * Marks the received list of records as "Exported" for the user by either Inserting or Updating a record in "rec_markers"
	 * - This marker can be used to filter out records which have already been exported, to only show new or updated records
	 * @param MVC_Request $rq
	 */
	public function action_Mark_Export(MVC_Request $rq)
	{
		if(empty(MVC_Core::$ss-> user))
			return header("HTTP/1.0 403 This method is only available while logged in.");

		$rq-> add_Filter('ids', "CSV");
		$ids = $rq-> ids;
		if(empty($ids))
			return header("HTTP/1.0 400 No record IDs in the request.");

		$user_id = $this-> user-> ID();
		$marker = array(
			'export_ut'=> time()
		);

		// Markers can only be added or updated one by one, as this is a hybrid insert/update operation, so a bulk update or bulk insert is not possible
		foreach($ids as $record_id)
			$this-> record-> marker_Set($record_id, $user_id, $marker);

		$this-> send_JSON(array('success'=> true, 'response'=> sprintf("%d records exported", count($ids))));
	}

	/**
	 * Changes multiple records at once ( process marker, export marker, "delete" )
	 * - Always affects own organization or Perspective org in case of admin
	 * @param MVC_Request $rq
	 */
	public function action_Bulk_Operation(MVC_Request $rq)
	{
		// Verify if the user has a global privilege to do certain operations on all records they can access (close, delete, set status/prio)
		switch($rq-> op) {
			case 'close':
				$response = "%d records have been closed";
				$privilege_to_check = "issue_close";
				break;
			case 'reactivate':
				$response = "%d records have been (re)activated";
				$privilege_to_check = "issue_close";
				break;
			case 'delete':
				$response = "%d records have been marked for deletion";
				$privilege_to_check = "issue_delete";
				break;
			case 'exmark-clr':
				$simple_check = true;
				$response = "%d record are no longer marked as exported";
				break;
			// Admin only operations - If not admin return error imediately
			case 'copy2org':
				if(!$this-> user-> is_Unrestricted())
					return header("HTTP/1.0 403 You do not have access to this function !");
				$response = "%d records have been copied to selected organization";
				break;
			case 'setmaster':
//				return header("HTTP/1.0 500 Not yet implemented.");

				if(!$this-> user-> is_Unrestricted())
					return header("HTTP/1.0 403 You do not have access to this function !");
				$response = "%d records have been given to selected organization";
				break;
			default:
				return header("HTTP/1.0 400 Unsupported operation {$rq-> op}");
		}

		if(!$this-> user-> is_Unrestricted() && isset($privilege_to_check))
			$acl_check = $this-> user-> test_ACL('privileges', $privilege_to_check);

		$rq-> add_Filter('ids', "IDList");

		$ids = $rq-> ids;
		if(empty($ids))
			return header("HTTP/1.0 400 No record IDs in the request.");

		if(isset($simple_check)) {
			// Don't bother with extra data, this is just checking if the record exists
			$wtr = array();
		} else {
			// Full organization data, used for the more complex operations (like setting status, priority, sharing, copying)
			$wtr = array('issue', 'org_specific', 'flist'=> array('master_org_id', 'shared_org_ids', 'process', 'linktime', 'assigned_to'));
			if(!empty(MVC_Core::$ss-> perspective))
				$wtr['org_id'] = MVC_Core::$ss-> perspective;
			elseif(!$this-> user-> is_Unrestricted())
				$wtr['org_id'] = MVC_Core::$ss-> user['org_id']; // Normal user can operate only records from their own organization
		}

		// read the selected issues to see if they exist and their current values
		$records = $this-> record-> get_List(null, $wtr, function(&$q) use($ids) {
			$q-> where("`id` IN (?)", $ids);
		}, null, null, 0); // No limit (read all in the list)

		if(empty($records))
			return header("HTTP/1.0 404 Records with requested IDs not found or not accessible.");

		$count = 0;
		foreach($records as $rec) {
			// User must be assigned to handle the record or have global privilege. Otherewise, skip the record (Admin has access to all and privileges are not checked)
			if(!$this-> user-> is_Unrestricted())
				if(!($rec['assigned_to'] == $this-> user-> ID() || $acl_check))
					continue;

			$org_id = empty($wtr['org_id'])? $rec['master_org_id']: $wtr['org_id']; // Primary org id for current record, unless specified via Perspective

			switch($rq-> op) {
				// Set process marker for record's organization to "closed"
				case 'close':
					if($rec['process'] != 'closed') {
						$r = $this-> record-> org_MarkProcess($rec['id'], 'closed', $org_id, false, $rec['linktime']);
						// TODO: Possible "Close" notification?
						if($r) {
							$count++;
						}
					}
					break;
				case 'reactivate':
					if($rec['process'] == 'closed') {
						$r = $this-> record-> org_MarkProcess($rec['id'], empty($rec['shared_org_ids'])? 'reactive': 'shared', $org_id, false, $rec['linktime']);
						// TODO: Possible "Reactivate" notification?
						if($r) {
							$count++;
						}
					}
					break;
				// Remove the organization from the share list, effectively removing the record from any view of a normal user
				case 'delete':
					if($rec['process'] != 'hidden') {
						$r = $this-> record-> org_Disable((int)$rec['id'], $org_id);
						// TODO: Possible "Delete" notification?
						if($r) {
							$count++;
						}
					}
					break;

				// Clears the export marker (no privileges needed)
				case 'exmark-clr':
					$r = $this-> record-> marker_Set($rec['id'], $this-> user-> ID(), array('export_ut'=> false), false); // Force update only, to avoid inserting marker records for no reason
					if($r)
						$count++;
					break;

				case 'copy2org':
					// If organization is already the one in "master_org_id" or in the "share_org_ids" list, skip it
					// -- currently disabled, apparently the need to copy within the same organization exists.
/*
					if($rec['master_org_id'] == $rq-> org_id)
						continue;
					if(!empty($rec['shared_org_ids'])) {
						if(in_array($rq-> org_id, explode(',', $rec['shared_org_ids'])))
							continue;
					}
*/
					// Read the record data (xfields)
					$wtr = array('issue', 'xfields_raw');
					if(isset($rq-> xf_ids))
						$wtr['xf_ids'] = json_decode($rq-> xf_ids);
					$rec_ex = $this-> record-> read($rec['id'], $wtr);

					// Prepare the data for reinsertion as a new record
					$rec_data = array(
						'master_org_id'=> (int)$rq-> org_id,
						'shared_org_ids'=> null,
						// Attention: The copy operation doesn't check if the target organization has access to the data group !
						'group_id'=> $rec_ex['group_id'],
						'submit_id'=> null, // Nobody is "the submitter" on copied records !
					);
					// Add cache fields
					foreach($rec_ex['cache'] as $cfield=> $cache_value)
						if(!is_null($cache_value))
							$rec_data['cache_'.$cfield] = $cache_value;

					// TODO: There should be a way to link the new record to the original (copied from?)
					$unused = null; // Reference crap (orgdata not used here)
					$iid = $this-> record-> create($rec_ex['type_id'], $rec_data, $rec_ex['xfields_raw'], false, false, $unused, true); // Last "true" = Use XF Raw on insert

					if(!empty($iid)) {
						$orgdata = array(
							'linktime'=> $rec_ex['ctime'],
							'process'=> "webapi",
							'status_key'=> "api" // Record created via WebAPI
						);
						$this-> record-> org_Add($iid, $rq-> org_id, null, $orgdata,
							false,	// Don't update record
							true,	// No checks - Avoid unnecesary queries
							false,	// Don't propagate deps
							'webapi');
						$count++;
					}

					break;
				// TODO: Set master operation (change organization)
				case 'setmaster':
//                                    $ids , $org_id
//                                    echo $rec['track'].'--';
//                                    echo $rq-> org_id.'__||';
//                                    print_r($ids);
                                        $setToOrg= $rq->org_id;
                                       $org_chk = $this->record->org_Add($rec['track'], $rq->org_id, null, null, false, false, false, 'self'); // False = Do not update issue at this stage, it will be updated anyway on the "set master"
        if (!$org_chk)
            return header("HTTP/1.0 500 Error while connecting organization to the issue.");

        // If connect succeeded (or it was already connected), now set the organization as master
        $r_chk = $this->record->org_SetAsOwner($rec['track'], $rq->org_id);

//                                    $response = "%d records have been given to selected organization";
					$count++;
					break;
			}
		}
//echo $count;exit();
		if($count)
			$this-> send_JSON(array('success'=> true, 'response'=> sprintf($response, $count)));
		else
			return header("HTTP/1.0 409 No records have been changed. Please check your selection and/or access rights.");
	}

	/**
	 * Saves the fact that the user chose to hide the grid's preservation alert.
	 */
	public function action_Hide_SearchSystem_Alert()
	{
		MVC_Core::$cache-> preserve_alert_off = true;
	}

	/**
	 * Reads list of fields for the record type (current active or another)
	 * - Used for record copy operations, record transfer, export, import and other purposes
	 * Note: This method doesn't use the default ist_Read function, as that one is too heavy for the purpose of simply showing field names
	 * TODO: For now, this method reads all fields (used by admin). In the future, it should have extra filters
	 *
	 * @param MVC_Request $in
	 */
	public function action_Get_XF_List(MVC_Request $in)
	{
		$rt_id = (int)(isset($in-> rt_id)? $in-> rt_id: $this-> current_rt['id']);
		$cols = isset($in-> cols)? explode(',', $in-> cols): array("id", "n");
		$sort = $in-> sort?:"`s` ASC, `n` ASC";

		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['xfd'], $cols)
		-> where("`ist_id` = ?", $rt_id)
		-> order($in-> sort?:"`s` ASC, `n` ASC");

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return header("HTTP/1.0 404 No field definitions found for RT_ID: {$rt_id}");

		$xf_list = array();
		foreach($r as $xf_row) {
			if(isset($xf_row['id']))
				$xf_row['id'] = (int)$xf_row['id'];
			// Fix - missing section becomes "(ungrouped)"
			if(isset($xf_row['s']) && $xf_row['s'] === "")
				$xf_row['s'] = "(ungrouped)";

			$xf_list[] = $xf_row;
		}

		$this-> send_JSON($xf_list);
	}
	public function action_Load_save_awbs_issue(MVC_Request $in)
	{
		$yearValue = (int)(isset($in-> yearValue)? $in-> yearValue: 0);
		$monthValue = (int)(isset($in-> monthValue)? $in-> monthValue: 0);
		$org_id = MVC_Core::$ss->user['org_id'];
		$issueAwbsValue = (int)(isset($in-> issueAwbsValue)? $in-> issueAwbsValue: 0);
		$dt = date('Y-m-d');
		
		$issue_awbs_id = (int)(isset($in-> issue_awbs_id)? $in-> issue_awbs_id: 0);
		// check if awbs already exists
		if($issue_awbs_id == 0){
			$awbIssueSql = "SELECT * FROM cargoclaim_awbs WHERE org_id = $org_id AND year = $yearValue AND month = $monthValue";
			$awbIssueResult = MVC_Core::$db->query($awbIssueSql);
			if(!$awbIssueResult)
				return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
			if(count($awbIssueResult) > 0){
				return $this-> send_JSON(array('status'=>false,'message'=>'Issued AWBS already exists'));
			}
		}
		
		if($issue_awbs_id > 0){
			$awbIssueSql = "SELECT * FROM cargoclaim_awbs WHERE org_id = $org_id AND year = $yearValue AND month = $monthValue";
			$awbIssueResult = MVC_Core::$db->query($awbIssueSql);
			if(!$awbIssueResult)
				return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
			$cargoclaim_awbs_id = 0;
			foreach($awbIssueResult as $row) {
				$cargoclaim_awbs_id = $row['id'];
			}
			if(count($awbIssueResult) > 0 && $cargoclaim_awbs_id != $issue_awbs_id){
				return $this-> send_JSON(array('status'=>false,'message'=>'Issued AWBS already exists'));
			}
		}

		
		if($issue_awbs_id > 0){
			$cargoclaimAwbsSql = "UPDATE `cargoclaim_awbs` SET
								`year` = $yearValue,`month` = $monthValue,`awbs` = $issueAwbsValue,`update_dt` = $dt WHERE `id` = $issue_awbs_id";
		}else{
			$cargoclaimAwbsSql = "INSERT INTO cargoclaim_awbs (org_id, year, month, awbs, dt)
									VALUES ($org_id, $yearValue, $monthValue, $issueAwbsValue, '$dt')";
		}

		
		 


		
		$cargoclaimAwbsSave = MVC_Core::$db->query($cargoclaimAwbsSql);
		if(!$cargoclaimAwbsSave)
			return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

		$xf_list = array('yearValue'=>$yearValue,'monthValue'=>$monthValue,'issueAwbsValue'=>$issueAwbsValue,
		'org_id'=>$org_id,'cargoclaimAwbsSave'=>$cargoclaimAwbsSave);
		$this-> send_JSON($xf_list);
	}

	public function action_Load_list_awbs_issue(MVC_Request $in)
	{
		$yearValue = (int)(isset($in-> yearValue)? $in-> yearValue: 0);
		$monthValue = (int)(isset($in-> monthValue)? $in-> monthValue: 0);
		$issueId = (int)(isset($in-> issueId)? $in-> issueId: 0);
		$org_id = MVC_Core::$ss->user['org_id'];

		// mysql order by id
		$where = $issueId > 0  ? ' AND id = '.$issueId : '';

		$awbIssueSql = "SELECT * FROM cargoclaim_awbs WHERE org_id = $org_id $where ORDER BY id DESC";

		$awbIssueResult = MVC_Core::$db->query($awbIssueSql);
		if(!$awbIssueResult)
			return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
		
		foreach($awbIssueResult as $row) {
			// $month_num = 1;
			$monthName =  date("F", mktime(0, 0, 0, $row['month'], 10));

			$awbIssueData['id'] = $row['id'];
			$awbIssueData['year'] = $row['year'];
			$awbIssueData['month'] = $monthName;
			$awbIssueData['month_no'] = $row['month'];
			$awbIssueData['awbs'] = $row['awbs'];
			$awbIssueData['issued_date'] = $row['issued_date'];
			$awbIssueResultData[] = $awbIssueData;
		}


		$xf_list = array('awbIssueResultData'=>$awbIssueResultData,'status'=>true);
		$this-> send_JSON($xf_list);
	}
        
//        IWI country & location bulk edit for License monitor start
        public function action_lmBulkUpdate(MVC_Request $post)
	{
//            print_r($_POST);
            
            $lm_bulk_country=$_POST['lm_bulk_country'];
            $lm_bulk_location=$_POST['lm_bulk_location'];
            $lm_selected_data_rows=$_POST['lm_selected_data_rows'];
            $getUpData=array(
                'lm_bulk_country'=>$lm_bulk_country,
                'lm_bulk_location'=>$lm_bulk_location,
            );
            
            
            $count=0;
            foreach ($lm_selected_data_rows as $trk){
                $issue_id=$trk;                
                $lmBulkUpdate=$this->record->lmBulkUpdate($issue_id, $getUpData);     
                if($lmBulkUpdate){
                    $count++;
                }
                
            }            
		if($count)
			$this-> send_JSON(array('success'=> true, 'response'=>'Successfully Updated'));
		else
			return header("HTTP/1.0 409 No records have been changed. Please check your selection and/or access rights.");
	}
//        IWI country & location bulk edit for License monitor end

	// 24062024 Created by iwi | Start
	public function action_license_monitor_grid_list(MVC_Request $post)
	{
        $org_id = MVC_Core::$ss->user['org_id'];
		$user_id = MVC_Core::$ss->user['id'];
		//                IWI License monitor get connected document issue fix start
                
//		$getLicenseMonitorManagementSql = "SELECT license_monitor_management.issue_id,license_monitor_management.id as id_val,
//        license_monitor_management.document_link,fie_1.value as chapter,license_monitor_management.license_monitor_issue_id as id,
//        fie_2.value as name_or_title,fie_3.value as internal_external,fie_4.int_value as valid_from
//        ,fie_5.int_value as expire_date,fie_6.int_value as next_review
//        FROM license_monitor_management
//        INNER JOIN issue_fields ON license_monitor_management.issue_id=issue_fields.issue_id
//        LEFT JOIN `issue_fields` AS fie_1 ON `fie_1`.issue_id=issue_fields.issue_id AND (fie_1.fk_id=3644) 
//        LEFT JOIN `issue_fields` AS fie_2 ON `fie_2`.issue_id=issue_fields.issue_id AND (fie_2.fk_id=774) 
//        LEFT JOIN `issue_fields` AS fie_3 ON `fie_3`.issue_id=issue_fields.issue_id AND (fie_3.fk_id=1391) 
//        LEFT JOIN `issue_fields` AS fie_4 ON `fie_4`.issue_id=issue_fields.issue_id AND (fie_4.fk_id=2016) 
//        LEFT JOIN `issue_fields` AS fie_5 ON `fie_5`.issue_id=issue_fields.issue_id AND (fie_5.fk_id=2017) 
//        LEFT JOIN `issue_fields` AS fie_6 ON `fie_6`.issue_id=issue_fields.issue_id AND (fie_6.fk_id=2809) 
//        WHERE license_monitor_management.org_id = '$org_id'  AND license_monitor_management.user_id = '$user_id'
//        GROUP BY license_monitor_management.issue_id ";
//        $getLicenseMonitorManagementSql = "SELECT license_monitor_management.issue_id as llm_issue_id,license_monitor_management.id as id_val,
//        license_monitor_management.document_link,fie_1.value as chapter,license_monitor_management.license_monitor_issue_id as id,
//        fie_2.value as name_or_title,fie_3.value as internal_external,fie_4.int_value as valid_from
//        ,fie_5.int_value as expire_date,fie_6.int_value as next_review
//        FROM license_monitor_management
//        LEFT JOIN issue_fields ON license_monitor_management.issue_id=issue_fields.issue_id
//        LEFT JOIN `issue_fields` AS fie_1 ON `fie_1`.issue_id=issue_fields.issue_id AND (fie_1.fk_id=3644) 
//        LEFT JOIN `issue_fields` AS fie_2 ON `fie_2`.issue_id=issue_fields.issue_id AND (fie_2.fk_id=774) 
//        LEFT JOIN `issue_fields` AS fie_3 ON `fie_3`.issue_id=issue_fields.issue_id AND (fie_3.fk_id=1391) 
//        LEFT JOIN `issue_fields` AS fie_4 ON `fie_4`.issue_id=issue_fields.issue_id AND (fie_4.fk_id=2016) 
//        LEFT JOIN `issue_fields` AS fie_5 ON `fie_5`.issue_id=issue_fields.issue_id AND (fie_5.fk_id=2017) 
//        LEFT JOIN `issue_fields` AS fie_6 ON `fie_6`.issue_id=issue_fields.issue_id AND (fie_6.fk_id=2809) 
//        WHERE license_monitor_management.org_id = '$org_id'  AND license_monitor_management.user_id = '$user_id' AND license_monitor_management.issue_id!=license_monitor_management.license_monitor_issue_id";
        $getLicenseMonitorManagementSql = "SELECT license_monitor_management.issue_id as llm_issue_id,
license_monitor_management.id as id_val,
license_monitor_management.document_link,
fie_1.value as chapter,
license_monitor_management.license_monitor_issue_id as id,
fie_2.value as name_or_title,
fie_3.value as internal_external,
fie_4.int_value as valid_from,
fie_5.int_value as expire_date,
fie_6.int_value as next_review,
issues.master_org_id
FROM license_monitor_management 
INNER JOIN issues ON license_monitor_management.issue_id=issues.id
LEFT JOIN issue_fields ON license_monitor_management.issue_id=issue_fields.issue_id
LEFT JOIN `issue_fields`AS fie_1 ON `fie_1`.issue_id=issue_fields.issue_id AND(fie_1.fk_id=3644)
LEFT JOIN `issue_fields`AS fie_2 ON `fie_2`.issue_id=issue_fields.issue_id AND(fie_2.fk_id=774)LEFT JOIN `issue_fields`AS fie_3 ON `fie_3`.issue_id=issue_fields.issue_id AND(fie_3.fk_id=1391)LEFT JOIN `issue_fields`AS fie_4 ON `fie_4`.issue_id=issue_fields.issue_id AND(fie_4.fk_id=2016)LEFT JOIN `issue_fields`AS fie_5 ON `fie_5`.issue_id=issue_fields.issue_id AND(fie_5.fk_id=2017)LEFT JOIN `issue_fields`AS fie_6 ON `fie_6`.issue_id=issue_fields.issue_id AND(fie_6.fk_id=2809)WHERE 
license_monitor_management.org_id='$org_id' AND issues.master_org_id='$org_id'

AND license_monitor_management.issue_id!=license_monitor_management.license_monitor_issue_id";
//        IWI License monitor get connected document issue fix end
        $getLicenseMonitorManagementSqlData = array();
        $getLicenseMonitorManagementSqlQuery = MVC_Core::$db->query($getLicenseMonitorManagementSql);
        if(!$getLicenseMonitorManagementSqlQuery)
        return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
        $licenseMonitorManagementHtml = '';
        foreach($getLicenseMonitorManagementSqlQuery as $row) {

			$internal_external = $row['internal_external'] !='' ? json_decode($row['internal_external']) : array();
            if(!empty($internal_external)){
                if($internal_external[0] == 'in'){
                    $internal_external = 'internal';
                }
                if($internal_external[0] == 'ex'){
                    $internal_external = 'External';
                }
            }else{
                 $internal_external = '';
            }

			$document_link =  "<a href='/record/process:".$row['document_link']."#panels' target='_blank'>".$row['document_link']."</a>";
			$licenseMonitorManagement[] = array(
				'id'=>$row['id'],
				'issue_id'=>$row['issue_id'],
				'id_val'=>$row['id_val'],
				'document_link'=>$document_link,
				'chapter'=>$row['chapter'],
				'name_or_title'=>$row['name_or_title'],
				'internal_external'=>$internal_external,
				'valid_from'=>($row['valid_from'] !=''? date("Y-m-d",($row['valid_from'])) : ''),
				'expire_date'=>($row['expire_date'] !=''? date("Y-m-d",($row['expire_date'])) : ''),
				'next_review'=>($row['next_review'] !=''? date("Y-m-d",($row['next_review'])) : ''),
			);
            // $getTotalNotesSql = "SELECT count(id) as total_notes  FROM notes WHERE link_id = '$row[issue_id]' AND visibility != 'private'  AND wv_publish != 'private' ";
            // $getTotalNotesSqlQuery = MVC_Core::$db->query($getTotalNotesSql);
            // if(!$getTotalNotesSqlQuery)
            // return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
            // $total_notes = 0;
            // foreach($getTotalNotesSqlQuery as $row1) {
            //     $total_notes = $row1['total_notes'];
            // }


            // $internal_external = $row['internal_external'] !='' ? json_decode($row['internal_external']) : array();
            // if(!empty($internal_external)){
            //     if($internal_external[0] == 'in'){
            //         $internal_external = 'internal';
            //     }
            //     if($internal_external[0] == 'ex'){
            //         $internal_external = 'External';
            //     }
            // }else{
            //      $internal_external = '';
            // }
            // $licenseMonitorManagementHtml .= '<tr>
            //     <td style="cursor: pointer;" data-url="/record/process:'.$row['document_link'].'#panels" class="goDetailFrmlm">'.$row['document_link'].'</td>
            //     <td>'.$row['name_or_title'].'</td>
            //     <td>'.$internal_external.'</td>
            //     <td>'.($row['valid_from'] !=''? date("Y-m-d",($row['valid_from'])) : '').'</td>
            //     <td>'.($row['expire_date'] !=''? date("Y-m-d",($row['expire_date'])) : '').'</td>
            //     <td>'.($row['next_review'] !=''? date("Y-m-d",($row['next_review'])) : '').'</td>
            //     <td>'.($total_notes !=''? $total_notes : '').'</td>
            //     <td><img src="/img/deleteRecord.png" alt=""  onclick="deleteLicenseMonitorData(this,'.$row['id'].')"/></td>
            // </tr>';

        }
        $this->send_JSON(array(
            'success' => true,
            'data'=>$licenseMonitorManagement,
        ));

		// $this-> send_JSON(array('success'=> true, 'data'=>$array_data,'_data'=>$_array_data));
	}
	// 24062024 Created by iwi | End

public function action_issue_list_new23()
{
 $unused = null; // Reference crap (orgdata not used here)
$rec_data[] = array(
				'submit_domain'=>'93.119.11.177',
				'group_id'=>'995',
				'submit_id'=>'58855',
				'submit_org_id'=>'5',
				'master_org_id'=>'5'
				 
			);
			
			$rec_data1[] = array(
				'emp_surname'=>'Anirbantest',
				'emp_name'=>'Banerjeeanirban'
				 
			);
 

				   $iid = $this-> record-> create('39', $rec_data, $rec_data1, false, false, $unused, true); // Last "true" = Use XF Raw on insert
  

}




	// 18072024 | created by iwi | end
	public function action_Load_Get_All_License_Monitor_Data_By_Doc_tab(MVC_Request $post) {
        $license_type = (isset($post->license_type) ? $post->license_type : '');
        $country_code = (isset($post->country_code) ? $post->country_code : '');
        $org_id = MVC_Core::$ss->user['org_id'];
        $user_id = MVC_Core::$ss->user['id'];
        $dt = date('Y-m-d H:i:s');

        // if (empty($license_type) || empty($country_code)) {
        // 	$this->send_JSON(array('error' => 'Both license type and country code are required.'));
        // 	return;
        // }
        $errors = array();

        if (empty($license_type)) {
            $errors['license_type'] = 'Please select license type.';
        }

        if (empty($country_code)) {
            $errors['country_code'] = 'Please select country code.';
        }

        if (!empty($errors)) {
            $this->send_JSON(array('errors' => $errors));
            return;
        }

        // Set filters
        /* $wtr = array(
          'issue',
          'org_specific',
          'assigned',
          'group',
          'deps',
          'flist' => array('status_key', 'priority_key', 'cache_primary', 'ctime', 'dep_ids'),
          'xfields',
          'xflist' => array('chapter', 'chp_no','code_iso2', 'code_loc'),
          );

          $wtr['org_id'] = MVC_Core::$ss->user['org_id'];
          $wtr[] = 'marker';
          $wtr['user_id'] = $this->user->ID();

          $wtr['custom'] = array();
          $wtr['custom'][] = array(
          'tab' => array('issue_fields', 'xf_code_iso2'),
          'fields' => false,
          'on' => "`xf_code_iso2`.issue_id = `iss`.id AND `xf_code_iso2`.fk_id = 2796"
          ); */


        $wtr = array(
            'issue',
            'org_specific',
            'group',
            'flist' => array('status_key'),
            'xfields',
            'xflist' => array('code_iso2'),
        );

        $wtr['org_id'] = MVC_Core::$ss->user['org_id'];
        $wtr[] = 'marker';
        $wtr['user_id'] = $this->user->ID();

        $wtr['custom'] = array();
        $wtr['custom'][] = array(
            'tab' => array('issue_fields', 'xf_code_iso2'),
            'fields' => false,
            'on' => "`xf_code_iso2`.issue_id = `iss`.id AND `xf_code_iso2`.fk_id = 2796"
        );

        if (MVC_Core::$ss->user['status'] != 'admin') {
            $wtr[] = 'assigned';
            $wtr[] = 'deps';
        }

        $whereData_group_value = array();
        $whereData_code_iso2_value = array();

        if ($country_code) {
            $whereData_code_iso2_value[] = array('c' => 'EQUAL', 'v' => $country_code);
        }

        if ($license_type) {
            $whereData_group_value[] = array('c' => 'EQUAL', 'v' => $license_type);
        }



        $ss_filters = array();
        if (!empty($whereData_group_value)) {
            $ss_filters['_group'] = $whereData_group_value;
        }
        if (!empty($whereData_code_iso2_value)) {
            $ss_filters['code_iso2'] = $whereData_code_iso2_value;
        }

        $ss_custom = array('xfilters' => array('active'));
        $ss_sort = "`iss`.id DESC";

        $issues = $this->record->get_List(6, $wtr, array($this, "filter_Get_List"), array(
            'custom' => $ss_custom,
            'filters' => $ss_filters
                ), $ss_sort, 10000, 0, true, true);

        if (empty($issues)) {
            $this->send_JSON(array('error' => 'No records found.'));
        } else {
            $this->send_JSON(array(
                "data" => array_values($issues),
                'ss_custom' => $ss_custom,
                'ss_filters' => $ss_filters,
            ));
        }
    }

    // 16072024 | created by iwi | start
	public function action_training_scheduler_grid_list(MVC_Request $post)
	{
		$org_id = MVC_Core::$ss->user['org_id'];
		$link_id = (isset($post->link_id)? $post->link_id : '');
		$track = (isset($post->track)? $post->track : '');
		// WHERE org_id = $org_id $where ORDER BY id
		
		$trainee_data_sql = "SELECT trainee_management.*,trainee_management.record_id as id,trainee_management_maping_user.user_issue_id,issues.track as user_track_id,
		files.filepath,files.analyzer,files.chash
		FROM trainee_management 
		LEFT JOIN trainee_management_maping_user ON trainee_management.id = trainee_management_maping_user.trainee_management_id
		LEFT JOIN issues ON trainee_management_maping_user.user_issue_id = issues.id       
		LEFT JOIN files ON files.id = trainee_management.files
		WHERE trainee_management.org_id = $org_id";
		$trainingSchedulerResult = MVC_Core::$db->query($trainee_data_sql);
		if(!$trainingSchedulerResult)
			return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
		
		foreach($trainingSchedulerResult as $row) {
			$monthName =  date("F", mktime(0, 0, 0, $row['month'], 10));
			$traineeManagementData['id'] = $row['id']; 
			if($row['user_track_id'] != '' || $row['user_track_id'] != null){
				$traineeManagementData['training_name'] = '<span style="cursor: pointer;"  data-url="/record/process:'.$row['user_track_id'].'#panels" class="goDetailFrmPnl" >'.$row['name'].'</span>';
			}else{
				$traineeManagementData['training_name'] = $row['name'];
			}
			
			$traineeManagementData['company_name'] = $row['company_name'];
			$traineeManagementData['email'] = $row['email'];
			$traineeManagementData['number'] = $row['number'];
			$traineeManagementData['user_issue_id'] = $row['trainee_type'] == 1 ? $row['user_issue_id'] : '';
			$traineeManagementData['score'] = $row['score'];
	
			if($row['filepath'] != '' || $row['filepath'] != null){
				if($row['analyzer'] == 'image'){
					$filepath = $row['filepath'];
					$file = explode('/',$filepath);
					$file2 = $file[0].$file[1];
					$file = $file[2];
					$file =   str_replace('.png','.jpg',$file);
					$file = substr($file,2);
					$tr_file_path = '/uimg/thms/'.$file2.'/'.$file;
					$certificate = $tr_file_path;
					$traineeManagementData['files'] = '<a href="'.$certificate.'" download><img src="/img/icons16/file_extension_zip.png"></a>';
				}else{
					$traineeManagementData['files'] = '<a href="/files?action=download_attachment&ch='.$row['chash'].'" download><img src="/img/icons16/file_extension_zip.png"></a>';
	
				}                   
				
			}else{
				$traineeManagementData['files'] = '';
			}
			
			$traineeManagementData['training_date'] = $row['training_date'];//$row['training_date'] !='' ? date('d-m-Y',strtotime($row['training_date'])) : '';
			$traineeManagementData['result'] = $row['result'] == 1 ? 'Pass' : ($row['result'] == 0 ? 'Fail' : 'Pending');
			$traineeManagementData['trainee_type'] = $row['trainee_type'] == 1 ? 1 : 0;//$row['trainee_type'] == 1 ? 'internal' : 'external';
			$traineeManagementData['trainee_type_lable'] = $row['trainee_type'] == 1 ? 'internal' : 'external';
			$traineeManagementData['create_dt'] = date('d-m-Y ', strtotime($row['create_dt'])); 
			$traineeManagementResult[] = $traineeManagementData;
		}
		$this->send_JSON(array(
			'success' => true,
			'data'=>$traineeManagementResult,
			'sql'=>$trainee_data_sql
		));
	}
	// 16072024 | created by iwi | end
        
//        IWI search that all the records which already closed orï¿½not have the master organization ID. Then based on that record all the tasks which are open currently I will set as failed all the tasks start
//        
//        public function action_setTaskFaildIfRecordClosed()
//	{
//            $allTaskIdArr=array();
//              $sql="SELECT t.id,t.status,t.link_id,i.process FROM tasks t  "
//                      . "JOIN issue_orgdata i ON t.link_id = i.issue_id"
//                      . " WHERE "
//                      . " t.status NOT IN ('completed', 'failed', 'expired', 'closed')"
//                      . " And i.process IN ('closed', 'hidden') ";
//            $r = MVC_Core::$db->query($sql);
//            foreach ($r as $data){
//                $allTaskIdArr[]=$data['id'];
//            }
//            
//            
//              $sql2="SELECT t.id,t.status,t.link_id,m.master_org_id FROM tasks t  "
//                      . "JOIN issues m ON t.link_id = m.id"
//                      . " WHERE "
//                      . " t.status NOT IN ('completed', 'failed', 'expired', 'closed')"
//                      . " And m.master_org_id IS NULL OR m.master_org_id = '' ";
//            
//            $r2 = MVC_Core::$db->query($sql2);
//            foreach ($r2 as $data2){
//                $allTaskIdArr[]=$data2['id'];
//            }
//            $allTaskIdArr=array_unique($allTaskIdArr);
////            print_r($allTaskIdArr);
//            echo "<pre>";
//            foreach ($allTaskIdArr as $task_id){
////                echo $task_id;
//                $sql3="SELECT t.link_id,t.status,m.master_org_id,i.process FROM tasks t  "
//                        . " JOIN issues m ON t.link_id = m.id "
//                        . " JOIN issue_orgdata i ON t.link_id = i.issue_id "
//                      . " WHERE "
//                      . " t.id = '$task_id'";
//            
//            $r3 = MVC_Core::$db->query($sql3)->current();
//            print_r($r3);
//            }
//        }
//        
        
        
        public function action_setTaskFaildIfRecordClosed()
	{
            $allTaskIdArr=array();
            
            $sql2="SELECT t.id,t.status,t.link_id,m.master_org_id FROM tasks t  "
                      . "JOIN issues m ON t.link_id = m.id"
                      . " WHERE "
                      . " t.status NOT IN ('completed', 'failed', 'expired', 'closed')"
                      . " And m.master_org_id IS NULL OR m.master_org_id = '' ";
            
            $r2 = MVC_Core::$db->query($sql2);
            foreach ($r2 as $data2){
                $allTaskIdArr[]=$data2['id'];
            }
            
            if(!empty($allTaskIdArr)){
              $sql="SELECT t.id,t.status,t.link_id,i.process,t.org_id FROM tasks t  "
                      . "JOIN issue_orgdata i ON t.link_id = i.issue_id"
                      . " WHERE "
                      . " t.status NOT IN ('completed', 'failed', 'expired', 'closed')"
                      . " And i.process IN ('closed', 'hidden') "
                      . " And i.issue_id NOT IN (". implode(',', $allTaskIdArr).")"
                    . " And t.org_id=i.org_id ";
              
            $r = MVC_Core::$db->query($sql);
            foreach ($r as $data){
                $allTaskIdArr[]=$data['id'];
            }
            }
            
              
            $allTaskIdArr=array_unique($allTaskIdArr);
//            echo "<pre>";
//            print_r($allTaskIdArr);
            foreach ($allTaskIdArr as $task_id){
//                echo $task_id;
                $sql3="Update tasks t  SET t.status = 'failed'"
                      . " WHERE "
                      . " t.id = '$task_id'";
            
            $r3 = MVC_Core::$db->query($sql3);
//            print_r($r3);
            }
        }
        
        
        
//        IWI search that all the records which already closed orï¿½not have the master organization ID. Then based on that record all the tasks which are open currently I will set as failed all the tasks end






	// 24102024 Audit control Nested grid start
	public function action_audit_control_grid_list(MVC_Request $post)
	{
		$org_id = MVC_Core::$ss->user['org_id'];
		// WHERE org_id = $org_id $where ORDER BY id
		// $audit_control_data_sql = "SELECT * FROM audit_activity";
		// $auditControlResult = MVC_Core::$db->query($audit_control_data_sql);
		// if(!$auditControlResult)
		// 	return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
		
		$auditControlResult = $this-> record-> get_Audit_Control_List(); 


		$$auditControlData = array();
		foreach($auditControlResult as $row) {
			
			// 06012025 audit control grid changes 
			$risk = $row['risk'];
            $_risk = '';
			if($risk == 'MI'){
                $_risk = 'Minor';
            }elseif($risk == 'MA'){
                $_risk = 'Major';
            }elseif($risk == 'CR'){
                $_risk = 'Critical';
            }// 12122024 changes audit control 
            elseif($risk == 'IN'){
                $_risk = 'Insignificant';
            }elseif($risk == 'MO'){
                $_risk = 'Moderate';
            }	



			// $risk = $val['risk'];//// 06012025 audit control grid changes 
            // if($risk == 'MI'){
            //     $_risk = 'Minor';
            // }elseif($risk == 'MA'){
            //     $_risk = 'Major';
            // }elseif($risk == 'CR'){
            //     $_risk = 'Critical';
            // }
			
			$auditData['isCreateTaskLable'] = $row['isCreateTask'] == 'true' ? 'Yes' : '';
			$auditData['track'] = $row['track'];
			$auditData['issue_id'] = $row['issue_id'];
			$auditData['id_val_name'] = $row['id_val'];
			$auditData['id'] = $row['issue_id'];
			$auditData['reference_no'] = $row['reference_no'];
			$auditData['root_cause'] = $row['comments'];
			$auditData['audit_description'] = $row['description'];
			$auditData['capa_details'] = $row['improvement'];
			$auditData['risk'] = $_risk;
			$auditData['risk_val'] = $risk; 
			$auditData['type'] = $row['type'];
			$auditData['person'] = $row['person'];
			$auditData['title'] = $row['title'];
			$auditData['area'] = $row['result'];
			$auditData['status'] = $row['status'];
			

			// 06012025 audit control grid changes 
			$auditData['deadline_date'] = $row['deadline_date'] !='0000-00-00' ? $row['deadline_date'] : '';
			$auditData['area'] = $row['result'];

			
			$auditControlData[] = $auditData;
		}
		$this->send_JSON(array(
			'success' => true,
			'data'=>$auditControlData
		));
	}
	// 24102024 Audit control Nested grid end











	// 15112024 process.php function call
	public function action_save_Audit_Type_Data(MVC_Request $post){ 
        $dt = date('Y-m-d H:i:s');
        $org_id = MVC_Core::$ss->user['org_id'];
        $type = (isset($post->type)) ? $post->type : '';
        $title = (isset($post->title)) ? $post->title : '';
        $area = (isset($post->area)) ? $post->area : '';
        $start_date = (isset($post->start_date)) ? $post->start_date : '';
        $end_date = (isset($post->end_date)) ? $post->end_date : '';
        $no_of_audit = (isset($post->no_of_audit)) ? $post->no_of_audit : '';
        $referance = (isset($post->referance)) ? $post->referance : '';
        $scheduleType = (isset($post->scheduleType)) ? $post->scheduleType : '';
        $audit_plan_id = (isset($post->audit_plan_id)) ? $post->audit_plan_id : 0;
        $scheduleDate = (isset($post->scheduleDate)) ? $post->scheduleDate : array();
       
        $dataArray = array(
            'org_id'=>$org_id,
            'type'=>$type,
            'title'=>$title,
            'area'=>$area,
            'start_date'=>$start_date,
            'end_date'=>$end_date,
            'schedule_type'=>$scheduleType,
            'no_of_audit'=>count($scheduleDate)
        );
	
		$org_id = MVC_Core::$ss->user['org_id']	;
		
		if($audit_plan_id > 0){
			$dataArray['utime'] = $dt;
			$update_audit_plan = $this->record->updateAuditPlanData($audit_plan_id,$dataArray);

			$save_audit_plan = $audit_plan_id;
		}else{
			$dataArray['ctime'] = $dt;
			$save_audit_plan = $this->record->saveAuditPlanData($dataArray);
		}
		

		foreach($scheduleDate as $val){
			$dataRecord = array(
				'name'=>$title,
				'scheduled_date'=>$val,
				'audit_number'=>'',
				'code_loc'=>'',
				'code_iso2'=>'',
				'dept'=>'',
				'requested'=>'',
				'auditer_name'=>'',
				'2nd_auditor'=>'',
				'3rd_auditor'=>'',
				'main_email'=>'',
				'main_rep'=>'',
				'auditer_depart'=>'',
				'comments'=>'',
			);

			$dataGroupRecord = array(
				'submit_domain'=> MVC_Core::$ss-> domain_CFG['domain'],
				'group_id'=>$type,
				'submit_id'=>MVC_Core::$ss->user['contact_id'],
				'submit_org_id'=>$org_id,
				'master_org_id'=>$org_id,
			);
			$ric = $this-> record-> create(34, $dataGroupRecord, $dataRecord, true, true, $org_specific); // $ric = Return from Issue Create
			$org_specific['assigned_to'] = $user['id'];
			$cdep_id = MVC_Core::$ss->user['cdep_id'];
			
			$this-> user-> update($user['id'], array(
				'count_issues'=> "`count_issues` + 1"
			), true);
			MVC_Core::$ss-> user['count']['issues']++;

			
			$this-> record-> org_Add($ric,$org_id,$cdep_id, $org_specific, false, true, true, 'self');


			$referanceDataArray = array(
				'audit_plan_id'=>$save_audit_plan,
				'issue_id'=>$ric,
			);
			$this->record->saveAuditPlanReferenceData($referanceDataArray);
		}
   		$this->send_JSON(array(
            'success' => true,
        ));
        return false;
    }

	public function action_Get_Audit_Plan_Data_By_Id(MVC_Request $post){
        $id = (isset($post->id)) ? $post->id : '';

        $audit_plan_result = $this->record->get_audit_plan_data_by_id($id);
        $audit_plan = array();
        foreach($audit_plan_result as $val){
            $audit_plan = $val;
        }

      

        $audit_plan_ref = array();
        $audit_plan_reference_result = $this->record->get_audit_plan_reference_by_id($id);
        foreach($audit_plan_reference_result as $val){
            $audit_plan_ref[] = $val;
        }


        $this->send_JSON(array(
        'success' => true,
        'audit_plan'=>$audit_plan,
        'audit_plan_ref'=>$audit_plan_ref
        ));
    }


	// 15112024 process.php function call end
















	// 30122024 Quality Event grid changes by IWI | Start
	public function action_quality_event_grid_capa_list(MVC_Request $post)
	{
        $org_id = MVC_Core::$ss->user['org_id'];
		$user_id = MVC_Core::$ss->user['id'];

		// $getQualityEventSql = "SELECT track,issue_id as id,title,id_val,type,risk,person,deadline_date,status,closing_date,result,
		// improvement,comments,closing_date
		//  FROM `audit_activity` WHERE org_id = '$org_id'  AND issue_type_id = 31";
        // $getQualityEventSqlData = array();
        // $getQualityEventSqlQuery = MVC_Core::$db->query($getQualityEventSql);
        // if(!$getQualityEventSqlQuery)
        // return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

		$getQualityEventSqlQuery = $this->record->get_quality_event_grid_capa_list($org_id);
        foreach($getQualityEventSqlQuery as $row) {

			$risk = $row['risk'];
            $_risk = '';
            if($risk == 'MI'){
                $_risk = 'Minor';
            }elseif($risk == 'MA'){
                $_risk = 'Major';
            }elseif($risk == 'CR'){
                $_risk = 'Critical';
            } 
            elseif($risk == 'IN'){
                $_risk = 'Insignificant';
            }elseif($risk == 'MO'){
                $_risk = 'Moderate';
            }	


			$QualityEventManagement[] = array(
				'id'=>$row['id'],
				'issue_id'=>$row['id'],
				'track'=>$row['track'],
				'id_val_name'=>$row['id_val'],
				'title'=>$row['title'],
				'type'=>$row['type'],
				'risk'=>$_risk.'	'.$val['risk'],
				'assigned_to'=>$row['person'],
				'deadline_date'=>$row['deadline_date'] !='0000-00-00' ? $row['deadline_date'] : '',
				'status'=>$row['status'],
				'closing_date'=>$row['closing_date'],
				'area'=>$row['result'],
			);
        }
        $this->send_JSON(array(
            'success' => true,
            'data'=>$QualityEventManagement,
        ));
	}
	// 30122024 Quality Event grid changes by IWI | End


	// 20022025 Documentation grid changes by IWI | Start
	public function action_doc_nested_grid_list_sec(MVC_Request $post)
	{
		$org_id = (isset($post->org_id)) ? $post->org_id : 0;
		$user_id = MVC_Core::$ss->user['id'];
		$getDocumentationSqlQuery = $this->record->get_documentation_grid_capa_list($org_id);
		$documentation = array();
		foreach($getDocumentationSqlQuery as $row) {
			$documentation[] = array(
				'id'=>$row['link_id'],
				'issue_id'=>$row['link_id'],
				'title'=>$row['title'],


				'ref_number'=>$row['ref_number'],
				'version'=>$row['version'],
				'visibility'=>$row['visibility'],
				'update_dt'=>$row['update_dt'],

				'last_editor_name'=>$row['last_editor_name'],
				'org_name'=>$row['org_name']
				
			);
						
		}
		$this->send_JSON(array(
            'success' => true,
            'org_id'=>$org_id,
            'user_id'=>$user_id,
            'data'=>$documentation,
        ));
	}
	// 20022025 Documentation grid changes by IWI | End


//IWI get all Service Recovery msg files with data 20-08-2025 start 

        public function action_getServiceRecoveryMsg()
	{
//             $m_file = $this->factory_Model("File");
//            $ch='32ffb88e68a7af7d9f2bc068275eaa9fd75e9b15';
//            $file_meta = $m_file-> find(function(QueryFactory $q) use($ch)
//			{
//				$q-> where("`chash` = ?", $ch)-> order("`id` ASC")-> limit(1);
//
//			});
//                echo '---';  
//                print_r($file_meta);
//                        $r = $m_file-> read_Content((int)$file_meta['id'], $file_meta);
//                        
//                        print_r($r);
            
            
            
            // download file
$csvUrl = "https://cargohub.aero/demo_test_files/Recoveries-Grid_1_100.csv";

// Fetch CSV content using cURL
$ch = curl_init($csvUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$csvContent = curl_exec($ch);

if ($csvContent === false) {
    die("cURL Error: " . curl_error($ch));
}
curl_close($ch);

// Open CSV content as a stream (handles quoted fields & embedded newlines correctly)
$firstColumn = array();
$stream = fopen('php://memory', 'r+');
fwrite($stream, $csvContent);
rewind($stream);

$rowIndex = 0;
while (($data = fgetcsv($stream)) !== false) {
    if ($rowIndex++ === 0) continue; // skip header
    if (isset($data[0])) {
        $firstColumn[] = $data[0];
    }
}

fclose($stream);

echo "<pre>";
//print_r($firstColumn);
foreach ($firstColumn as $trac ){
    
     $q="SELECT `iss`.`track`, `iss`.`id` ,`files`.`chash`,`files`.`name` FROM `issues` AS iss "
             . "INNER JOIN `files`  ON `files`.`linked_id` = `iss`.`id` "
             . " WHERE `iss`.track = '$trac' And `files`.`ext`='msg' ";
    //    echo "2558 ".$q;exit;
//     echo $q;exit();
        $r = MVC_Core::$db-> query($q);
        foreach($r as $files){
            print_r($files);
            
        }
    
//    $file_meta = $m_file-> find(function(QueryFactory $q) use($ch)
//			{
//				$q-> where("`chash` = ?", $ch)-> order("`id` ASC")-> limit(1);
//
//			});
//                        
//                        $r = $m_file-> read_Content((int)$file_meta['id'], $file_meta);
}
    
    
    
	}

//IWI get all Service Recovery msg files with data 20-08-2025 end 
        
        
        
//        IWI chart for quality event module 07112025 start
         public function action_quality_event_chart()
	{
    if(!$this-> user-> test_ACL('privileges','issues_view')) {
			$this-> evan-> add("You are not allowed to view records.", 'lock');
			$this-> redirect($this-> CFG['home']);
		}

		$this-> CFG['components'] = array('modal');
		$this-> CFG['modules'][] = "/issue/chart/_quality_event_chart";
		$this-> CFG['jqwidgets'][] = array("buttongroup", "switchbutton", "scrollbar", "expander", "slider"); // Slice 2 and 3 - Stuff
		$this-> CFG['jqwidgets'][] = array("listbox", "dropdownlist", "combobox", "dragdrop", "calendar", "datetimeinput");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete"); // Slices 4 and 5 - Grid and some grid plugins
		$this-> CFG['jqwidgets'][] = array("data.export", "grid.export"); // "grid.storage"
		$this-> CFG['xjs'] = array(
			'global' => true,
			'dateformat'=> true
		);
		$this-> CFG['issue_list'] = array();

		// prepare output
		$this-> prepare_Header(self::$_module_name);
                
                
                $this-> site_section = "issues". DS. "chart/quality_event_chart.html";
		$this-> view("private.html");
    
	}
        
//        IWI chart for quality event module 07112025 end

        
        
//        IWI Aircargoclaim chart page update 12012026 start
        
        public function action_air_cargo_claim_chart()
	{
    if(!$this-> user-> test_ACL('privileges','issues_view')) {
			$this-> evan-> add("You are not allowed to view records.", 'lock');
			$this-> redirect($this-> CFG['home']);
		}

		$this-> CFG['components'] = array('modal');
		$this-> CFG['modules'][] = "issue/chart/_air_cargo_claim_chart";
		$this-> CFG['jqwidgets'][] = array("buttongroup", "switchbutton", "scrollbar", "expander", "slider"); // Slice 2 and 3 - Stuff
		$this-> CFG['jqwidgets'][] = array("listbox", "dropdownlist", "combobox", "dragdrop", "calendar", "datetimeinput");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete"); // Slices 4 and 5 - Grid and some grid plugins
		$this-> CFG['jqwidgets'][] = array("data.export", "grid.export"); // "grid.storage"
		$this-> CFG['xjs'] = array(
			'global' => true,
			'dateformat'=> true
		);
		$this-> CFG['issue_list'] = array();

		// prepare output
		$this-> prepare_Header(self::$_module_name);
                if(MVC_Core::$ss->user['org_id']=='208'){
                    $this->show_airline_select='yes';
                }else{
                    $this->show_airline_select='no';
                }
                $this-> site_section = "issues". DS. "chart/air_cargo_claim_chart.html";
		$this-> view("private.html");
    
	}
        
//        IWI Aircargoclaim chart page update 12012026 end
        
        
        
//        IWI audit control page chart new 11032026 start
        
        public function action_audit_control_chart()
	{
    if(!$this-> user-> test_ACL('privileges','issues_view')) {
			$this-> evan-> add("You are not allowed to view records.", 'lock');
			$this-> redirect($this-> CFG['home']);
		}

		$this-> CFG['components'] = array('modal');
		$this-> CFG['modules'][] = "issue/chart/_audit_control_chart";
		$this-> CFG['jqwidgets'][] = array("buttongroup", "switchbutton", "scrollbar", "expander", "slider"); // Slice 2 and 3 - Stuff
		$this-> CFG['jqwidgets'][] = array("listbox", "dropdownlist", "combobox", "dragdrop", "calendar", "datetimeinput");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete"); // Slices 4 and 5 - Grid and some grid plugins
		$this-> CFG['jqwidgets'][] = array("data.export", "grid.export"); // "grid.storage"
		$this-> CFG['xjs'] = array(
			'global' => true,
			'dateformat'=> true
		);
		$this-> CFG['issue_list'] = array();

		// prepare output
		$this-> prepare_Header(self::$_module_name);
                $this-> site_section = "issues". DS. "chart/audit_control_chart.html";
		$this-> view("private.html");
    
	}
        
//        IWI audit control page chart new 11032026 end
        
//        IWI separate listing page for all contracts 28052026 start
    public function action_get_supplier_contracts() {
        if (!$this->user->test_ACL('privileges', 'issues_view')) {
            $this->evan->add("You are not allowed to view records.", 'lock');
            $this->redirect($this->CFG['home']);
        }

        $this->CFG['components'] = array('modal');
        $this->CFG['modules'][] = "issue/chart/_supplier_contracts";
        $this->CFG['jqwidgets'][] = array("buttongroup", "switchbutton", "scrollbar", "expander", "slider"); // Slice 2 and 3 - Stuff
        $this->CFG['jqwidgets'][] = array("listbox", "dropdownlist", "combobox", "dragdrop", "calendar", "datetimeinput");
        $this->CFG['jqwidgets'][] = array("-gridcomplete"); // Slices 4 and 5 - Grid and some grid plugins
        $this->CFG['jqwidgets'][] = array("data.export", "grid.export"); // "grid.storage"
        $this->CFG['xjs'] = array(
            'global' => true,
            'dateformat' => true
        );
        $this->CFG['issue_chart_supplier_contracts'] = array(
                    'org_id'=>5,
                );

        // prepare output
        $this->prepare_Header(self::$_module_name);
        $this->site_section = "issues" . DS . "chart/get_supplier_contracts.html";
        $this->view("private.html");
    }

//        IWI separate listing page for all contracts 28052026 end
    
//    IWI Create nested row for contracts in supplier control list page 29062026 start
    public function action_contract_nested_grid_list_sec(MVC_Request $post)
	{
        
        $track= $post->track;
        $getContractManagementData = $this->record->getContractNestedGridList($track);
        $this->send_JSON(array(
            'total_count' => count($getContractManagementData),
            'data' => $getContractManagementData,
        ));
	}
//      IWI Create nested row for contracts in supplier control list page 29062026 end
        
        
        
//        IWI separate listing page for all assets 07072026 start
    public function action_get_all_assets() {
        if (!$this->user->test_ACL('privileges', 'issues_view')) {
            $this->evan->add("You are not allowed to view records.", 'lock');
            $this->redirect($this->CFG['home']);
        }

        $this->CFG['components'] = array('modal');
        $this->CFG['modules'][] = "issue/chart/_get_all_assets";
        $this->CFG['jqwidgets'][] = array("buttongroup", "switchbutton", "scrollbar", "expander", "slider"); // Slice 2 and 3 - Stuff
        $this->CFG['jqwidgets'][] = array("listbox", "dropdownlist", "combobox", "dragdrop", "calendar", "datetimeinput");
        $this->CFG['jqwidgets'][] = array("-gridcomplete"); // Slices 4 and 5 - Grid and some grid plugins
        $this->CFG['jqwidgets'][] = array("data.export", "grid.export"); // "grid.storage"
        $this->CFG['xjs'] = array(
            'global' => true,
            'dateformat' => true
        );
//        $this->CFG['issue_chart_supplier_contracts'] = array(
//                    'org_id'=>5,
//                );

        // prepare output
        $this->prepare_Header(self::$_module_name);
        $this->site_section = "issues" . DS . "chart/get_all_assets.html";
        $this->view("private.html");
    }

//        IWI separate listing page for all assets 07072026 end
    
     
//    IWI contracts report page based on group location 27082026 start
    
    public function action_contracts_analytics() {
        if (!$this->user->test_ACL('privileges', 'issues_view')) {
            $this->evan->add("You are not allowed to view records.", 'lock');
            $this->redirect($this->CFG['home']);
        }

        $this->CFG['components'] = array('modal');
        $this->CFG['modules'][] = "issue/chart/_contracts_analytics";
        $this->CFG['jqwidgets'][] = array("buttongroup", "switchbutton", "scrollbar", "expander", "slider"); // Slice 2 and 3 - Stuff
        $this->CFG['jqwidgets'][] = array("listbox", "dropdownlist", "combobox", "dragdrop", "calendar", "datetimeinput");
        $this->CFG['jqwidgets'][] = array("-gridcomplete"); // Slices 4 and 5 - Grid and some grid plugins
        $this->CFG['jqwidgets'][] = array("data.export", "grid.export"); // "grid.storage"
        $this->CFG['xjs'] = array(
            'global' => true,
            'dateformat' => true
        );

        // prepare output
        $this->prepare_Header(self::$_module_name);
        $this->site_section = "issues" . DS . "chart/contracts_analytics.html";
        $this->view("private.html");
    }
    
//    IWI contracts report page based on group location 27082026 end

}