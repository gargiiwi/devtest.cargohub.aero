<?php
/**
 * Global Record List - Shows records of all types at once (But without the X-Fields - Only PRIMARY data)
 * - Similar to the existing "Issue List" module, it shows a grid of records. Grid has similar filtering abilities, and is persistent.
 * - Data accessed is from ISS and IOD joined, but no IXF
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2.8
 */
class App_Ctrl_Search_Global extends App_Ctrl_Base
{
	/**
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct(true);

		// Controller specific models
		$this-> record = $this-> factory_Model('Issue');
	} 

	public static $_module_name = "Global Record Search";

	/**
	 * Index action for the Issues display and management page
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Index(MVC_Request $rq)
	{
		// Test ACL for issue list
		if(!$this-> user-> test_ACL('privileges', 'issues_view')) {
			$this-> evan-> add("You are not allowed to view records.", 'lock');
			$this-> redirect($this-> CFG['home']);
		}

		$this-> CFG['components'] = array('modal');
		$this-> CFG['modules'][] = "record/_globalsearch";
		$this-> CFG['jqwidgets'][] = array("tabs", "expander");
		$this-> CFG['jqwidgets'][] = array("listbox", "dropdownlist", "combobox", "calendar", "datetimeinput");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete"); // Slices 4 and 5 - Grid and some grid plugins
		$this-> CFG['jqwidgets'][] = array("data.export", "grid.export"); // "grid.storage"
		$this-> CFG['xjs'] = array(
			'global' => true,
			'dateformat'=> true
		);

		// prepare output
		$this-> prepare_Header(self::$_module_name);

		// Administrator can view the grid from the perspective of another organization
		// Unlike the record viewing mode (which automatically sets the perspective to the owner org if one wasn't already selected, in the grid it has to be specified in the URL)
		if($this-> user-> is_Unrestricted()) {
			if($rq-> org_id)
				$org_id = MVC_Core::$ss-> perspective = (int)$rq-> org_id;
			else
				unset(MVC_Core::$ss-> perspective);

			if(!empty($this-> perspective_org))
				$this-> organization = $this-> perspective_org;
		}

		// Javascript configuration
		$this-> CFG['record_globalsearch'] = array(
			'rt_source'=> MVC_Core::$cache-> record_types,

			// All Statuses and Priorities from the system (Obviously many won't make any sense for record types not related to them)
			// - Todo - A more advanced version would check all available record types and make an union of those statuses and priorities (so to show as few as possible)
			'statuses'=> array_values($this-> record-> statuses),
			'priorities'=> array_values($this-> record-> priorities)
		);
// 		array_unshift($this-> CFG['record_globalsearch']['priorities'], "(unavailable)");
// 		array_unshift($this-> CFG['record_globalsearch']['statuses'], "(unavailable)");

		if(isset($org_id))
			$this-> CFG['record_globalsearch']['perspective'] = $org_id;

		// All built-in column. Includes:
		// - Issue Metadata ( id, track, time, organization, group, if shared, ip address, assigned )
		// - Contact information for the submitter contact (Name, E-mail)
		$this-> CFG['record_globalsearch']['colnames'] = array(
			// Primary table
			'id'=> "ID",
			'rt_name'=> "Record Type",
			'ctime'=> "Created [Time]",		// Global
			'edtime'=> "Fields Edited [Time]",	// Global
			'master_org'=> "Master Organization",
			'shared'=> "Is shared?",			// A post-process value, indicates that the fields "shared_org_ids" is non-null ( as in, the system is shared between organizations )
			'datagroup'=> "Record group",
			'submit_org'=> "Submit Organization",	// Non-empty if submitted by logged-in system user
			'submit_name'=> "Submit Person",		// Person name, from submitter contact
			'submit_email'=> "Submit E-mail",		// ... and his/her e-mail
			'submit_ip'=> "Submit IP",
			// Per organization table
			'deps'=> "Department(s)",			// List of departments that have access to the record
			// Organization timers
			//'_linktime'=> "Connected [Time]",			// (Internal value, not sure if useful for users)
			'time_open'=> "First opened [Time]",
			'time_start'=> "First changed [Time]",
			'utime'=> "Any Update [Time]",				// Time when any update was done to organization-specific data (not affected by other organization operations)
			'commtime'=> "Communication [Time]",		// Time when last communication was received for this organization (Check !)
			'time_close'=> "Closed [Time]",
			//'_access_ut'=> "Accessed [Time]",		// Disabled, not currently updated
			'pending_length'=> "Waiting length",
			'process_length'=> "Process length",
			// Other oranization fields (Status, Priority, Transfer-related, Assigned)
			'priority'=> "Priority",
			'status'=> "Status",
			'sender_name'=> "Sender name",
			'sender_email'=> "Sender E-mail",
			'recipient_name'=> "Recipient name",
			'recipient_email'=> "Recipient E-mail",
			'xfer_time'=> "Transfer time",		// It's actually share or transfer time
			'xfer_ip'=> "Transfer IP",			// IP used when sharing/transferring an issue to this organizaiton
			'xfer_confirmip'=> "Delivery IP",	// Used to check against the Transfer IP to acknowledge transfer
			'xfer_delivered'=> "Is delivered?",	// Second column related to Confirm IP
			'assigned'=> "Assigned to",			// User who is being assigned this issue
			// Some counters disabled because they aren't functioning properly
		/*			'_count_contacts'=> "# of Contacts",		// The per organization counters
		 '_count_files'=> "# of Attach.",
		'_filesize_total'=> "Att. total KB",
		'_count_convos'=> "# of Convos.",*/
			'count_notes'=> "# of Notes",
			'count_tasks'=> "# of Tasks",
			'count_active_tasks'=> "Active Tasks",
			/*			'_count_propfields'=> "# of Fields",*/
			'cache_primary'=> "Global Search",
			'cache_secondary'=> "Secondary Search",
		);

		// Visible columns (used to assemble the grid) - This should be configurable from the interface
		$columns = array(
			array('fk'=> "cache_primary", 'w'=> 330),
			array('fk'=> "rt_key"),	// Record Type (name)
			array('fk'=> "rt_name", 'w'=> 200),	// Record Type (name)
			array('fk'=> "ctime", 'w'=> 100),
			array('fk'=> "datagroup", 'w'=> 190),
			array('fk'=> "priority", 'w'=> 77),
			array('fk'=> "status", 'w'=> 102)

// 			array('fk'=> "cache_secondary", 'w'=> 150)
// 			array('fk'=> "submit_org", 'v'=> true, 'w'=> 180),
// 			array('fk'=> "submit_email", 'v'=> true, 'w'=> 200),
// 			array('fk'=> "cache_primary", 'v'=> true, 'w'=> 150),
		);

		// Send the column information to JS
		$this-> CFG['record_globalsearch']['columns'] = $columns;

		// Persistent grid state (session) - DISABLED
		/*
		if(isset(MVC_Core::$ss-> cache['grid_state']['search_global']))
			$this-> CFG['record_globalsearch']['grid_state'] = MVC_Core::$ss-> cache['grid_state']['search_global'];
		else {*/
			// Default grid state
			$this-> CFG['record_globalsearch']['grid_state'] = array(
				'pagenum'=> 0,
				'pagesize'=> 25,
				'sort'=> array(
					'column'=> "ctime",
					'direction'=> "desc"
				),
				'filters'=> null
			);
		/*
		}
		*/

		// Test if the entered value is a trackid, if yes, use it in the Tracking # column and not in the global search
		$rq-> add_Filter("search", "Is_TrackID");
		if($rq-> search) {
			$filter_inject = array(
				'column'=> 'track',
				'filters'=> array(
					array(
						'value'=> $rq-> search,
						'condition'=> 'EQUAL',
						'operator'=> 1,
						'type'=> 'stringfilter'
					)
				)
			);
		} else {
			// Remove the filter
			$rq-> clean_Filters("search");

			// External injection of search based on Primary Search
			if($rq-> search) {
				$filter_inject = array(
					'column'=> 'cache_primary',
					'filters'=> array(
						array(
							'value'=> trim($rq-> search),
							'condition'=> 'CONTAINS',
							'operator'=> 1,
							'type'=> 'stringfilter'
						)
					)
				);

			}
		}

		// Go through existing filters, if column is found, it's overwritten. If not, it's added.
		if(isset($filter_inject)) {
			$state_filters = &$this-> CFG['record_globalsearch']['grid_state']['filters'];
			if(empty($state_filters))
				$state_filters = array($filter_inject);
			elseif(is_array($state_filters))
				foreach($state_filters as $idx=> $filter)
					if($filter['column'] == $filter_inject['column']) {
						$state_filters[$idx] = $filter_inject;
						break;
					}
		}

		if(!empty(MVC_Core::$cache-> global_search_previews))
			$this-> CFG['record_globalsearch']['open_tabs'] = MVC_Core::$cache-> global_search_previews;

		$this-> module = array(
			'icon'=> "magnifier.png",
			'icon_alt'=> "(Search)",
			'title'=> self::$_module_name,
			'description'=> "Find records of all data types"
		);

		// which body template to use, and run the view
		$this-> site_section = "search". DS. "global.html";
		$this-> view("private.html");
	}

	/**
	 * Returns information about the field, based on it's key:
	 *   In which db table is located, what type of filter should be used
	 *   An unrecognized key will return null
	 * @param string $field_name
	 * @return array|null
	 */
	public static function field_Info($fkey)
	{
		switch($fkey) {
			// The primary fields in issues table
			case 'track':
			case 'id':
				return array('dbf'=> "`iss`.{$fkey}", 'type'=> 'standard');
			case 'rt_name':
				return array('dbf'=> "`ist`.name", 'type'=> 'standard');
			case 'ctime':
			case 'edtime':
				return array('dbf'=> "`iss`.{$fkey}", 'type'=> 'unixtime');
			case 'master_org':
				return array('dbf'=> "`org`.name", 'type'=> 'standard');
			case 'shared':
				return array('dbf'=> "`iss`.shared_org_ids", 'type'=> 'notempty');
			case 'datagroup':
				return array('dbf'=> "`grp`.name", 'type'=> 'standard');
			case 'submit_org':
				return array('dbf'=> "`subo`.name", 'type'=> 'standard');
			case 'submit_name':
				return array('dbf'=> "`subm`.name", 'type'=> 'standard');
			case 'submit_email':
				return array('dbf'=> "`subm`.email", 'type'=> 'standard');
			case 'submit_ip':
				return array('dbf'=> "`iss`.{$fkey}", 'type'=> 'ip2long');
				// Associated Per-Organization Table
			case 'linktime':
			case 'time_open':
			case 'time_start':
			case 'time_close':
			case 'commtime':
			case 'xfer_time':
				return array('dbf'=> "`iod`.{$fkey}", 'type'=> 'unixtime');
			case 'utime':
				return array('dbf'=> "`iod`.utime", 'type'=> 'mysqldt');
			case 'pending_length':
			case 'process_length':
			case 'count_contacts':
			case 'count_files':
			case 'filesize_total':
			case 'count_convos':
			case 'count_notes':
			case 'count_tasks':
			case 'count_active_tasks':
			case 'count_propfields':
				return array('dbf'=> "`iod`.{$fkey}", 'type'=> 'standard');
			case 'deps':
				return array('dbf'=> "`dep`.name", 'type'=> 'standard');
			case 'priority':
				return array('dbf'=> "`iod`.priority_key", 'type'=> 'priority');
			case 'status':
				return array('dbf'=> "`iod`.status_key", 'type'=> 'status');
			case 'sender_name':
				return array('dbf'=> "`xsend`.name", 'type'=> 'standard');
			case 'sender_email':
				return array('dbf'=> "`xsend`.email", 'type'=> 'standard');
			case 'recipient_name':
				return array('dbf'=> "`xrecv`.name", 'type'=> 'standard');
			case 'recipient_email':
				return array('dbf'=> "`xrecv`.email", 'type'=> 'standard');
			case 'xfer_ip':
			case 'xfer_confirmip':
				return array('dbf'=> "`iod`.{$fkey}", 'type'=> 'ip2long');
			case 'xfer_delivered':
				return array('dbf'=> "`iod`.xfer_confirmip", 'type'=> 'notempty');
			case 'assigned':
				return array('dbf'=> "`usr`.name", 'type'=> 'standard');
				// Other fields, or types of fields
			default:
				if(substr($fkey, 0, 1)=='@') {
					// is it a contact xdata key?
					return array('dbf'=> "`subm`.xdata", 'type'=> 'standard', 'prepend'=> '%"'.substr($fkey, 1).'":"'); // Json fields can only search from start (not in the middle of the field)
				} elseif(substr($fkey, 0, 6)=='cache_') { // Cache fields disabled, not working properly
					// One of the "cache" fields
					return array('dbf'=> "`iss`.cache_".substr($fkey, 6), 'type'=> 'standard');
				} else
					// Unknown field, crash
					throw new Exception("Attempting to filter or sort by an unknown field. Configuration error?");
		}
	}

	/**
	 * Reads records (according to user's rights and active filters + paging)
	 * Returns JSON array for jqxGrid
	 */
	public function action_Get_List(MVC_Request $rq)
	{
		if(!MVC_Core::$ss-> user)
			return header("HTTP/1.0 403 This function is only availble while logged in");

		$count_rows = $rq-> pagesize;
		$start_row = $rq-> pagenum * $rq-> pagesize;

		// Requested sort order?
		if($rq-> sortdatafield && $rq-> sortorder) {
			$sort_field = self::field_Info($rq-> sortdatafield);
			$ss_sort = $sort_field['dbf'].' '.$rq-> sortorder;
		} else
			$ss_sort = "`iss`.ctime DESC";  // default sorting by deadline, ascending

		$wtr = array('issue', 'type', 'group', 'org_specific');
		if($this-> user-> is_Unrestricted()) {
// 			if(isset(MVC_Core::$ss-> perspective))
// 				$wtr['org_id'] = MVC_Core::$ss-> perspective; // Could be 0, in which case all issues from all organizations are shown
			// Perspective for global search not properly implemented, so disabled
			unset($wtr['org_id']);
		} else
			$wtr['org_id'] = MVC_Core::$ss-> user['org_id']; // Normal user accesses only issues visible in his own organization

		$wtr['flist'] = array('priority_key', 'status_key');


		list($ss_filters, $filter_callback) = self::helper_Grid_Filters($rq);
		$user = &$this-> user;

		$issues = $this-> record-> get_List(null, $wtr,
		/**
		 * Filtering function - Also includes security
		 * @param QueryFactory $q
		 * @@use:
		 * - $user = The User Model
		 * - $rq = Request Model
		 * - $ss_filters, $filter_callback = Necessary for grid filters
		 */
		function(QueryFactory &$q) use ($rq, $user, $ss_filters, $filter_callback) {
			// Restriction filters //
			if(!$user-> is_Unrestricted()) {
				// for non-admin users, restrict what they can view depending on privileges
				$restriction_filters = array();
				$usr = MVC_Core::$ss-> user;

				// Starts with organization ownership filter - Shows issues which are owned by the current logged in user's organization, or those that have this organization in their shared_org_ids
				$restriction_filters[] = $q-> quote_Into("(`iss`.master_org_id = ? OR FIND_IN_SET(?, `iss`.shared_org_ids))", $usr['org_id'], $usr['org_id']);

				// Filters only the record types that the user has access to, before anything else
				$rt_list = $user-> read_ACL('ist');
				if(!empty($rt_list))
					$restriction_filters[] = $q-> quote_Into("`iss`.type_id IN(?)", $rt_list);

				// Apply group and assignment based filters only when the user doesn't have the special "see all organization's issues" privilege assigned
				// -- That privilege ignores all visibility restrictions and allows the user to access ALL issues that are visible for his organization.
				if(!$user-> test_ACL('privileges', 'issues_view_orgowned')) {
					// Group based restrictions, only show issues in the groups this user has access to
					$restriction_filters[] = $q-> quote_Into("`iss`.group_id IN(?)", array_keys($usr['acls']['groups']));

					// Assignment based restricitons:
					$unassgn = $user-> test_ACL('privileges', 'issues_view_unassgn');
					$otherassgn = $user-> test_ACL('privileges', 'issues_view_assgn_oth');

					if($unassgn == true && $otherassgn == true) {
						// see all, nothing to limit (this is just a placeholder case)
					} elseif($unassgn == true && $otherassgn == false) {
						// assigned to self and unassigned
						$restriction_filters[] = $q-> quote_Into('(`iss`.assigned_to = ? OR `iss`.assigned_to IS NULL)', $usr['id']);
					} elseif($unassgn == false && $otherassgn == true) {
						// assigned to self and to others
						$restriction_filters[] = $q-> quote_Into('(`iss`.assigned_to = ? OR `iss`.assigned_to IS NOT NULL)', $usr['id']);
					} elseif($unassgn == false && $otherassgn == false) {
						// only assigned to self
						$restriction_filters[] = $q-> quote_Into('`iss`.assigned_to = ?', $usr['id']);
					}

					// Department-level filter - User's active department must be in the list of "dep_ids" in the issue
					if(!empty($usr['cdep_id'])) {
						$restriction_filters[] = $q-> quote_Into('FIND_IN_SET(?, `iod`.dep_ids)', $usr['cdep_id']);
					}
				}

				// Apply restriction filters. Search filters are then subsequently applied with AND to the permision filters
 				$q-> where(implode(" AND ", $restriction_filters));
			}

			// Grid filters
			foreach($ss_filters as $fkey=> $filter_set) {
				$filter_field = App_Ctrl_Search_Global::field_Info($fkey);
				if($fkey=='track' && strlen($filter_set[0]['v']) == 12) {
					// Switch to Levenshtein on "almost" track-id (but before that, filter one of the 3 fragments)
					$q-> where("SUBSTRING(`iss`.track,1,3) = ? OR SUBSTRING(`iss`.track,5,3) = ? OR SUBSTRING(`iss`.track,9) = ?", substr($filter_set[0]['v'], 0, 3), substr($filter_set[0]['v'], 4, 3), substr($filter_set[0]['v'], 8));
				$filter_field['type'] = "levenshtein"; 
				}
				if(empty($filter_field))
					continue; // Skip invalid filters

				$filter_callback($q, $filter_set, $filter_field['dbf'], $filter_field['type']);
			}

		}, null, $ss_sort, $rq-> pagesize, $rq-> pagenum * $rq-> pagesize, true, true); // true(1) = CALC FOUND!, true(2) = Format for Grid (all xfields become stringifiable)

		if($issues && count($issues)> 0) {
			// a bit of processing on the values before sending the data
			foreach($issues as &$issue) {
				$issue['id'] = (int)$issue['id'];
				// Times which are internally stored as ints are returned as DATETIME instead (so they can be converted to Date() by the grid)
				$int_times = array('ctime', 'edtime', 'linktime', 'commtime', 'time_open', 'time_start', 'access_ut', 'time_close');
				foreach($int_times as $int_t)
					if(!empty($issue[$int_t]))
						$issue[$int_t] = date("Y-m-d H:i:s", $issue[$int_t]);
				// Xfer Time too
				if(!empty($issue['xfer']['time']))
					$issue['xfer']['time'] = date("Y-m-d H:i:s", $issue['xfer']['time']);
			}

			// Send the issue data to the jqxGrid
			$this-> send_JSON(array(
				"found_rows"=> $this-> record-> found_rows,
				"data"=> array_values($issues)
			));
		}
		else
			$this-> send_JSON(array());
	}

	/**
	 * Export server for the grid's "Export" function
	 * - Note: This thing isn't very good...
	 */
	public function action_Export()
	{
		$post = new MVC_Request('post');

		// Create headers depending on format
		header("Content-Disposition: attachment; filename=\"{$post-> filename}.{$post-> format}\"");
		switch($post-> format) {
			case 'xls':
				header('Content-type: application/vnd.ms-excel');
				break;
			case 'html':
				header('Content-type: text/html');
				break;
			case 'csv':
				header('Content-type: text/csv');
				break;
		}

		header("Content-Transfer-Encoding: binary");

		header("Content-length: ".(string)strlen($post-> content));
		header("Pragma: public");
		header("Pragma: no-cache");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false); // required for certain browsers
		echo $post-> content;
	}

	/**
	 * Loads the preview of a record to show inside a tab.
	 * - Uses the _view.html template for the selected record type
	 */
	public function action_Load_Preview(MVC_Request $in)
	{
		if(!MVC_Core::$ss-> user) {
			$msg403 = "This function is only availble while logged in";
			header("HTTP/1.0 403 {$msg403}");
			echo $msg403;
			return false;
		}

		$in-> add_Filter('track', "Is_TrackID");

		if(!$in-> track) {
			$msg400 = "Cannot preview a record without a valid identifier";
			header("HTTP/1.0 400 {$msg400}");
			echo $msg400;
			return;
		}

		$tpl_data = array(
			'override'=> true
		);
		$this-> render = $this-> record-> render($in-> track, null, null, null, $tpl_data);
		$this-> tabinfo = array(
			'id'=> (int)$tpl_data['issue']['id'],
			'track'=> $tpl_data['issue']['track'],
			'rt_key'=> $tpl_data['ist']['key'],
			'rt_name'=> $tpl_data['ist']['name']
		);

		$this-> send_JSON_Headers(array(
			'tabinfo'=> $this->_out['tabinfo']
		));

		$this-> init_Template_System();
		$this-> view("record_preview_container.html");
	}

	/**
	 * Saves in session cache the list of persistent preview tabs for this module
	 * @param MVC_Request $post
	 */
	public function action_Persistent_Previews(MVC_Request $post)
	{
		$open_tabs = array(); $post_tabs = @json_decode($post-> tabs);
		if(!empty($post_tabs)) {
			foreach($post_tabs as $track=> $index)
				$open_tabs[] = $track;

			MVC_Core::$cache-> global_search_previews = $open_tabs;
		} else
			unset(MVC_Core::$cache-> global_search_previews);

		$this-> send_JSON(array('success'=> true));
	}
}