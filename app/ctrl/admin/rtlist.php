<?php
/**
 * Issue type definition lister and editor
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers.Admin
 * @version 2019.06.23
 */
class App_Ctrl_Admin_RTList extends App_Ctrl_Base
{
	public static $_module_name = "Record Types Overview";

	/**
	 * Reference to the Record model (still named "Issue" internally)
	 * @var App_Model_Issue
	 */
	protected $rec;

	/**
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct();

		// Admin-only module, redirect to home if no access
		if(!$this-> user-> is_Unrestricted()) {
			$this-> evan-> add("Move along now, nothing to see here.", 'lock');
			$this-> redirect($this-> CFG['home']);
		}

		// Controller specific models
		$this-> rec = $this-> factory_Model('Issue', 'rec');
	}

	/**
	 * Displays the list of issue types with commands to create, view or edit them (no delete for now)
	 */
	public function action_Index()
	{
		$this-> CFG['components'] = array('modal');
		$this-> CFG['modules'][] = "admin/_rtlist";
		$this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "dropdownlist", "expander");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete");
		$this-> CFG['xjs'] = array(
			'jqform'=> true
		);

		$this-> prepare_Header(self::$_module_name);
		// Persistent grid state (session)
		if(isset(MVC_Core::$cache-> grid_state['rtlist']))
			$this-> CFG['admin_rtlist']['grid_state'] = MVC_Core::$cache-> grid_state['rtlist'];
		else {
			// Default grid state
			$this-> CFG['admin_rtlist']['grid_state'] = array(
				'pagenum'=> 0,
				'pagesize'=> 25,
				'filters'=> null
			);
		}

		$this-> module = array(
			'icon'=> "bricks.png",
			'icon_alt'=> "(RTO)",
			'title'=> self::$_module_name,
			'description'=> "Lists all available record types"
		);

		// which body template to use, and run the view
		$this-> site_section = "admin". DS. "rtlist.html";
		$this-> view("private.html");
	}

	/**
	 * Get the list of issue types for the grid.
	 */
	public function action_Get_List(MVC_Request $rq)
	{
		$count_rows = $rq-> pagesize;
		$start_row = $rq-> pagenum * $rq-> pagesize;

		// Requested sort order?
		if($rq-> sortdatafield && $rq-> sortorder) {
			$ss_sort = "`{$rq-> sortdatafield}` {$rq-> sortorder}";
		} else
			$ss_sort = null; // Use default sorting

		list($ss_filters, $filter_callback) = self::helper_Grid_Filters($rq);

		$entries = $this-> rec-> rt_Get_List('extended',
			/**
			 * Filtering function
			 * @param QueryFactory $q
			 * @uses:
			 * - $rq = Request Model
			 * - $ss_filters, $filter_callback = Necessary for grid filters
			 */
			function(QueryFactory &$q) use ($rq, $ss_filters, $filter_callback) {

				// Grid filters
				foreach($ss_filters as $fkey=> $filter_set) {
					$filter_callback($q, $filter_set, "`{$fkey}`");
				}
			},
			null, $ss_sort, $rq-> pagesize, $rq-> pagenum * $rq-> pagesize, true  // true = CALC FOUND ROWS!
		);

		if(!$entries)
			return $this-> send_JSON(array(
				"found_rows"=> 0,
				"data"=> array()
			));

		// Gather data, post-process and return
		$rows = array();
		foreach($entries as $row) {
			$row['id'] = (int)$row['id'];
			$row['count_issues'] = (int)$row['count_issues'];
			$rows[] = $row;
		}

		// Send the information to the grid
		$this-> send_JSON(array(
			"found_rows"=> $this-> rec-> get_List_Save_FoundRows(),
			"data"=> $rows
		));
	}

	/**
	 * Displays the form for creating a new issue type
	 * @todo Integrate create with new admin_rtedit module
	 */
	public function action_Create()
	{
		$this-> prepare_Header("Create new module");

		$this-> CFG['modules'][] = "admin/_ist_edit";
		$this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "dropdownlist", "panel", "numberinput", "navigationbar");

		$this-> CFG['admin_ist_edit'] = array();

		// which body template to use, and run the view
		$this-> site_section = "admin". DS. "ist_editor.html";
		$this-> view("private.html");
	}

	/**
	 * Shows the detailed information about an issue type without interface elements to edit it ( just display )
	 */
	public function action_Details(MVC_Request $rq)
	{
		if(!$rq-> id) {
			$this-> evan-> add("Record type not specified. How exactly did you get here ?", 'error');
			MVC_Core::shutdown('/kb?page=op_fail');
		}

		$this-> prepare_Header("Records module etails");

		$this-> CFG['modules'][] = "admin/_ist_view";
		$this-> CFG['admin_ist_view'] = array();

		$this-> ist = $ist = $this-> rec-> ist_Read($rq-> id, false, false, true);

		if(!$ist) {
			$this-> evan-> add("The record type with id {$rq-> id} does not exist. How exactly did you get here ?", 'error');
			MVC_Core::shutdown('/kb?page=op_fail');
		}

		// Initial status/priorities, translated
// 		$init_sp = $this->_out['ist']['settings']['init_sp'];
// 		$this->_out['ist']['init_sp'] = array(
// 			'status'=> $this->_out['ist']['statuses_ex'][$init_sp[0]],
// 			'priority'=> $this->_out['ist']['priorities_ex'][$init_sp[1]]
// 		);

		$this-> CFG['admin_ist_view']['id'] = (int)$rq-> id;
		$this-> CFG['admin_ist_view']['key'] = $ist['key'];

		$this-> site_section = "admin". DS. "ist_details.html";
		$this-> view("private.html");
	}

	/**
	 * Displays the form used to edit an existing issue type
	 * @deprecated Old editor
	 */
	public function action_Edit(MVC_Request $rq)
	{
		if(!$rq-> id) {
			$this-> evan-> add("Record type not specified. How exactly did you get here ?", 'error');
			MVC_Core::shutdown('/kb?page=op_fail');
		}

		$this-> prepare_Header("Record Type Editor");

		$this-> CFG['modules'][] = "admin/_ist_edit";
		$this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "dragdrop", "dropdownlist", "panel", "numberinput", "navigationbar");

		// Read in Edit mode (all xfields)
		$ist = $this-> rec-> ist_Read($rq-> id, true, false, true, true); // $xfields_mode Yes, $force NO, $return_true_id YES, $ignore_xf_hidden YES

		if(!$ist) {
			$this-> evan-> add("The record type with id {$rq-> id} does not exist. How exactly did you get here ?", 'error');
			MVC_Core::shutdown('/kb?page=op_fail');
		}

		// Add all required information for the editor
		$this-> CFG['admin_ist_edit'] = $ist;
		$this-> CFG['admin_ist_edit']['statuses_global'] = $this-> rec-> statuses;
		$this-> CFG['admin_ist_edit']['priorities_global'] = $this-> rec-> priorities;

		$this-> module = array(
			'title'=> "Record Type Editor",
			'description'=> "Configures a record type"
		);

		// which body template to use, and run the view
		$this-> site_section = "admin". DS. "ist_editor.html";
		$this-> view("private.html");
	}

	/**
	 * (re)Counts issues for a certain type and updates the record - Can be used for resynchronizing data after certain operations
	 * @param MVC_Request $post
	 */
	public function action_Count_Issues(MVC_Request $post)
	{
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['iss'], "count(*) as `issue_count`")
		-> where("`type_id`= ?", $post-> rt_id)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);
		if(!$r)
			return $this-> send_Fail_JSON("No issues of this type or an error occured.");

		$count = $r-> current();
		$count = $count['issue_count'];

		$this-> rec-> ist_Update($post-> rt_id, array(
			'count_issues'=> $count
		));

		$this-> send_JSON(array('success'=> true, 'response'=> "Issue count cache updated to real value."));
	}

	/**
	 * Action: Save IST data (from form)
	 */
	public function action_Save(MVC_Request $post)
	{
		// assamble the data for ist_Create or ist_Edit
		$ist_def = $post-> ist;
		$ist_def['xfield_def'] = array();
		foreach($ist_def['xf'] as $xfdef) {
			// make a "key" out of the name if it doesn't exist (or is set from the JS)
			if(!isset($xfdef['fk']) || empty($xfdef['fk']))
				$xfdef['fk'] = MVC_Core::generate_Named_Id($xfdef['n']);

			// decode some json ...
			if(isset($xfdef['o']))
				if(strlen($xfdef['o'])>0)
					$xfdef['o'] = json_decode($xfdef['o'], true);
				else
					unset($xfdef['o']);
			if(isset($xfdef['cd']))
				if(strlen($xfdef['cd'])>0)
					$xfdef['cd'] = json_decode($xfdef['cd'], true);
				else
					unset($xfdef['cd']);
			// ... only to be reencoded alter when stored in the db (but there's no other way, since the data is grouped in one db field, but comes as independent fields from JS
			$ist_def['xfield_def'][] = $xfdef;

			// TODO - Maybe create the JSON directly in JS ( complete format ), instead of sending as $_POST fields ?
		}
		unset($ist_def['xf']);

		if($ist_def['settings']['group_type'] == "0")
			unset($ist_def['settings']['group_type']);

		if(empty($ist_def['group_ids']))
			$ist_def['group_ids'] = null;

		if($post-> rt_id) {
			// update after editing
			$this-> rec-> ist_Update($post-> rt_id, $ist_def);
			$this-> evan-> add("The record type {$post-> ist['name']} has been updated", "ok", array(
				'permanent'=> true,
				'timeout'=> time()+ 15
			));

			$this-> redirect('/admin/rtedit&id='.$post->rt_id);

			// Note: At the moment there is no way to edit or change the names of the template files from the interface (only directly in DB)
		} else {
			$ist_def['templates'] = array(
				'post'=> "_post.html",
				'view'=> "_view.html",
				'process'=> "_process.html",
				'edit'=> "_edit%s.html",
				'render'=> "_render.html",
				'_includes'=> array(
					'transfer'=> "includes/transfer.html"
				)
			);

			$ist_def['settings']['s_closed'] = array("clos");

			// Some initial panel data - Of course, it needs to be customized.
			$ist_def['settings']['panels'] = array(
				array(
					'id'=> $ist_def['key']."-dashboard",
					'priv'=> "issue_overview",
					'title'=> $ist_def['name'],
					'send'=> true
				),
				array(
					'id'=> $ist_def['key']."-details",
					'priv'=> "issue_xfields",
					'title'=> "Details",
					'send'=> true
				)
			);
			// XFCache, Automate and other settings need to be manually configured (for now)

			$rc = $this-> rec-> ist_Create($ist_def);
			if($rc> 0) {
				// database insertion complete - Add the template folders and files files ( empty, for now )
				// TODO: Better way to handle template creation for issue type !
				$path = ROOT. DS. 'app'. DS. 'view'. DS. 'issues'. DS. $ist_def['key'];
				mkdir($path);
				file_put_contents($path. DS. $ist_def['templates']['post'], "\n[POST]\n Please write me !", FILE_APPEND);
				file_put_contents($path. DS. $ist_def['templates']['view'], "\n[VIEW]\n Please write me !", FILE_APPEND);
				file_put_contents($path. DS. $ist_def['templates']['process'], "\n[PROCESS]\n Please write me !", FILE_APPEND);
				file_put_contents($path. DS. sprintf($ist_def['templates']['edit'], "_info"), "\n[EDIT INFO]\n Please rename and write me !", FILE_APPEND);
				file_put_contents($path. DS. sprintf($ist_def['templates']['edit'], "_details"), "\n[EDIT DETAILS]\n Please rename and write me !", FILE_APPEND);
				file_put_contents($path. DS. $ist_def['templates']['render'], "\n[RENDER]\n Please write me !", FILE_APPEND);
				mkdir($path. DS. 'includes');
				file_put_contents($path. DS. $ist_def['templates']['_includes']['transfer'], "\n[RENDER]\n Please write me !", FILE_APPEND);
				// TODO: Does not create icons, they should be upload-able through the interface (and checked for size)

				$this-> evan-> add("The issue type {$post-> ist['name']} has been created. Please write the templates !", "add", array(
					'permanent'=> true,
					'timeout'=> time()+ 30
				));

				$this-> redirect('/admin/rtlist');
			} else {
				$this-> evan-> add("The issue type could not be created. Some data is missing... please try again !");
				$this-> redirect('/admin/rtlist?action=create'); // Old editor (broken for create)
			}
		}
	}

	/**
	 * Sets the mode of a record type to "Archive" (become invisible for normal users)
	 */
	public function action_Archive(MVC_Request $rq)
	{
		// read the organization to check some parameters
		$rt = $this-> rec-> ist_Read($rq-> rt_id, null);

		if(!$rt)
			// not found
			return header("HTTP/1.0 404 Record Type with id {$rq-> id} was not found");

		// Active global issuetypes cannot be directly removed
		if($rt['scope'] == 'archive')
			return header("HTTP/1.0 400 This record type has already been archived. Nothing to do");

		// Organizations can be locked by setting a certain flag in their "features" so they can't be accidentally deleted.
		if(isset($rt['settings']['delete_lock']))
			return header("HTTP/1.0 400 <b>Safety Lock</b> is active for {$rt['name']}. Contact Kal-El to resolve this ;)");

		$r = $this-> rec-> ist_Update($rt['id'], array('scope'=> "archive"));

		if(!$r)
			return header("HTTP/1.0 500 An error occured while attempting to archive the record type {$rt['name']}");

		$this-> send_JSON(array('success'=> true, 'response'=> "The issue type {$rt['name']} has been archived"));
	}

	/**
	 * Returns the list of groups available for one group set.
	 * - Note: Not all group sets return a list !
	 */
	public function action_Get_Groups(MVC_Request $in)
	{
		// Some group types do not use filters (all are connected by default)
		if(in_array($in-> group_type, array("0", 'airline', 'marine', 'land', 'dept')))
			return $this-> send_JSON(array());

		// ... for the other types, read all available groups and return the list for the Group Filter checkbox-dropdown
		$m_group = new App_Model_Group($in-> group_type);

		$groups = $m_group-> get_List();
		if(empty($groups))
			return $this-> send_JSON(array());

		$this-> send_JSON($groups);
	}

}