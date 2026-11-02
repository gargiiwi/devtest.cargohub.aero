<?php
/**
 * Record Blueprint Editor v2
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2019.07.01
 */
class App_Ctrl_Admin_RTEdit extends App_Ctrl_Base
{
	public static $_module_name = "Record Blueprint Editor";

	/**
	 * Reference to the record model (old name: Issue)
	 * @var App_Model_Issue
	 */
	protected $rec;

	/**
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct();

		// Admin-only module
		if(!$this-> user-> is_Unrestricted()) {
			if(App_Router::$ajax)
				return header("HTTP/1.0 403 Move along now, nothing to see here");
			else {
				$this-> evan-> add("Move along now, nothing to see here.", 'lock');
				$this-> redirect($this-> CFG['home']);
			}
		}

		// Controller specific models
		$this-> rec = $this-> factory_Model('Issue');
		$this-> factory_Model('Syslog');
	}

	/**
	 * Edits the selected
	 */
	public function action_Index(MVC_Request $rq)
	{
		if($rq-> id) {
			// Edit mode, loads information about an existing record type and prepares for editing
			$mode = "edit";
			$rt = $this-> rec-> ist_Read($rq-> id, null, true, true, true);
			if(!$rt) {
				$this-> evan-> add("The record type with id: {$rq-> id} does not exist. Possible interface error.", 'error');
				return MVC_Core::shutdown('/kb?page=op_fail');
			}
		} else {
			// Create mode, allows defining a new record type
			$mode = "create";
			$rt = array(
				'id'=> 0,
				'key'=> '_new',
				'scope'=> 'admin'
			);
		}

		$this-> CFG['components'] = array('modal');
		$this-> CFG['modules'][] = "admin/_rteditor";

		$this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "dropdownlist", "combobox", "dragdrop", "panel", "expander");
		$this-> CFG['jqwidgets'][] = array("validator", "input", "numberinput", "navigationbar", "switchbutton");
		$this-> CFG['jqwidgets'][] = array("datatable","-gridcomplete");
		$this-> CFG['xjs'] = array(
			'jqform'=> true
		);

		$this-> CFG['admin_rteditor'] = $rt;
		$this-> CFG['admin_rteditor']['_mode'] = $mode;

		$this-> prepare_Header(self::$_module_name);

		// Initial grid state (not persistent) - Sort by section
		$this-> CFG['admin_rteditor']['grid_state'] = array(
			'sort'=> array(
				'column'=> "s",
				'direction'=> "asc"
			)
		);

		// Extra required information for the editor
		$this-> CFG['admin_rteditor']['statuses_global'] = $this-> rec-> statuses;
		$this-> CFG['admin_rteditor']['priorities_global'] = $this-> rec-> priorities;

		$this-> module = array(
			'icon'=> "blueprint.png",
			'icon_alt'=> "({$rt['key']})",
			'title'=> self::$_module_name,
			'description'=> "Define and Configure a Record Type"
		);

		// which body template to use, and run the view
		$this-> site_section = "admin". DS. "rteditor.html";
		$this-> view("private.html");
	}

	/**
	 * Action: Save Blueprint (Primary info - Name, Key, Plural, Description, Statuses, Priorities, Groups, Sections)
	 * -- TODO: Settings+Templates have their own editor
	 * -- TODO: Each field is saved individually and does not update the Primary data of the Blueprint
	 */
	public function action_Save_Primary(MVC_Request $post)
	{
			// assamble the data for ist_Create or ist_Edit
		$blueprint = $post-> rt;

// 		$ist_def['xfield_def'] = array();
// 		foreach($ist_def['xf'] as $xfdef) {
// 			// make a "key" out of the name if it doesn't exist (or is set from the JS)
// 			if(!isset($xfdef['fk']) || empty($xfdef['fk']))
// 				$xfdef['fk'] = MVC_Core::generate_Named_Id($xfdef['n']);

// 			// decode some json ...
// 			if(isset($xfdef['o']))
// 				if(strlen($xfdef['o'])>0)
// 					$xfdef['o'] = json_decode($xfdef['o'], true);
// 				else
// 					unset($xfdef['o']);
// 			if(isset($xfdef['cd']))
// 				if(strlen($xfdef['cd'])>0)
// 					$xfdef['cd'] = json_decode($xfdef['cd'], true);
// 				else
// 					unset($xfdef['cd']);
// 			// ... only to be reencoded alter when stored in the db (but there's no other way, since the data is grouped in one db field, but comes as independent fields from JS
// 			$ist_def['xfield_def'][] = $xfdef;

// 			// TODO - Maybe create the JSON directly in JS ( complete format ), instead of sending as $_POST fields ?
// 		}
// 		unset($ist_def['xf']);

		if(empty($blueprint['group_ids']))
			$blueprint['group_ids'] = null;

		if($post-> id) {
			// update after editing
			$this-> rec-> ist_Update($post-> id, $blueprint);
			$this-> evan-> add("The blueprint <b>{$blueprint['name']}</b> has been updated", "ok", array(
				'permanent'=> true,
				'timeout'=> time()+ 15
			));

			return $this-> send_JSON(array('success'=> true));

			// Note: At the moment there is no way to edit or change the names of the template files from the interface (only directly in DB)
		} else {
			$blueprint['templates'] = array(
				'post'=> "_post.html",
				'view'=> "_view.html",
				'process'=> "_process.html",
				'edit'=> "_edit%s.html",
				'render'=> "_render.html",
				'_includes'=> array(
					'transfer'=> "includes/transfer.html"
				)
			);

			$blueprint['settings']['s_closed'] = array("clos");

			// Some initial panel data - Of course, it needs to be customized.
			$blueprint['settings']['panels'] = array(
				array(
					'id'=> $blueprint['key']."-dashboard",
					'priv'=> "issue_overview",
					'title'=> $blueprint['name'],
					'send'=> true
				),
				array(
					'id'=> $blueprint['key']."-details",
					'priv'=> "issue_xfields",
					'title'=> "Details",
					'send'=> true
				)
			);
			// XFCache, Automate and other settings need to be manually configured (for now)

			$rc = $this-> rec-> ist_Create($blueprint);
			if($rc> 0) {
				// database insertion complete - Add the template folders and files files ( empty, for now )
				// TODO: Better way to handle template creation for issue type !
/*				$path = ROOT. DS. 'app'. DS. 'view'. DS. 'issues'. DS. $ist_def['key'];
				mkdir($path);

				// TODO: This is wrong, it should depend on the actual content of the "templates" element
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
*/
// 				$this-> redirect('/admin/rtlist');
			} else {
// 				$this-> evan-> add("The issue type could not be created. Some data is missing... please try again !");
// 				$this-> redirect('/admin/rtlist?action=create');
			}
			// FIXME: This is an AJAX call, it should not do redirects !
		}
	}

//===== Fields Actions =====//

	/**
	 * Save information about one field.
	 * - In case they key has been renamed, also updates user profile (so the column doesn't disappear from their overviews - ReKey function)
	 * - TODO: Figure out how to resolve choices update (before-> after), so choices data can be updated too (if possible)
	 *
	 * @param MVC_Request $post
	 */
	public function action_Save_Field(MVC_Request $post)
	{
		$field = $post-> field;
		if(empty($field['fk']) || empty($field['n']))
			return header("HTTP/1.0 400 Field Name and Key not present in the request. Possible UI error");

		if($post-> id) {
			// Update
			$post-> add_Filter('alterdata', 'boolean');
			$r = $this-> rec-> xfdef_Update($post-> id, $field, $post-> alterdata, $post-> rt_id, $post-> rt_key);
			if(!$r || !$r['success'])
				return header("HTTP/1.0 500 There was an error while attempting to update the field");

			$response = sprintf("Field definition updated for: %s", $field['n']);
			if($post-> alterdata)
				$response.= ". Relevant stored data also updated";

			// LOG action
			$this-> syslog-> log($this-> user-> ID(), 'bpe', 'field_upd', array(
				'rt_key'=> $post-> rt_key,
				'field_id'=> (int)$post-> id,
				'fdef'=> $field,
				'alter_data'=> $post-> alterdata
			), "Field Def: Update > {$field['n']}");

			$this-> send_JSON(array('success'=> true, 'response'=> $response));
		} else {
			// Create
			$iid = $this-> rec-> xfdef_Create($post-> rt_id, $field);
			if(!$iid)
				return header("HTTP/1.0 500 There was an error while attempting to create the new field. Possible UI error");

			// LOG action
			$this-> syslog-> log($this-> user-> ID(), 'bpe', 'field_new', array(
				'rt_key'=> $post-> rt_key,
				'field_id'=> (int)$iid,
				'fdef'=> $field
			), "Field Def: Create > {$field['n']}");

			$this-> send_JSON(array('success'=> true, 'response'=> sprintf("Field %s created with id: %d", $field['n'], $iid)));
		}
	}

	/**
	 * Deletes a field definition from the database
	 * - Optionally also deletes the data that this field referenced, as well as (TODO) user settings and configuration which contained that field
	 * @param MVC_Request $post
	 */
	public function action_Delete_Field(MVC_Request $post)
	{
		if(!$post-> id)
			return header("HTTP/1.0 400 Field ID not present in the request. Possible UI error");

		$post-> add_Filter('removedata', 'boolean');

		// Attempt to read the existing field before deleting it, so it's stored in the syslog
		$xfdef = $this-> rec-> xfdef_Read($post-> id);
		if(empty($xfdef))
			return header("HTTP/1.0 400 Field with ID {$post-> id} doesn't exist. Possible UI error");
		else {

			unset($xfdef['id']);
			$xfdef['ist_id'] = (int)$xfdef['ist_id'];
			// LOG action
			$this-> syslog-> log($this-> user-> ID(), 'bpe', 'field_del', array(
				'rt_key'=> $post-> rt_key,
				'field_id'=> (int)$post-> id,
				'fdef'=> $xfdef,
				'remdata'=> $post-> removedata
			), "Field Def: Delete > {$xfdef['n']}");
		}

		$r = $this-> rec-> xfdef_Delete($post-> id, $post-> removedata, $post-> rt_id, $post-> rt_key);
		if(!$r)
			return header("HTTP/1.0 500 Error while attempting to delete the field");

		$response = sprintf("Field with id %s has been deleted", $post-> id);
			if($post-> alterdata)
				$response.= ". Related stored data also removed";

		$this-> send_JSON(array('success'=> true, 'response'=> $response));
	}

	/**
	 * Reads field definitions for currently selected record type
	 * @param MVC_Request $rq
	 */
	public function action_Get_Fields(MVC_Request $rq)
	{
		$count_rows = $rq-> pagesize;
		$start_row = $rq-> pagenum * $rq-> pagesize;

		$t_xfd = MVC_Core::$db-> tab['xfd'];
		// Requested sort order?
		if($rq-> sortdatafield && $rq-> sortorder) {
			$ss_sort = "`{$t_xfd}`.{$rq-> sortdatafield} {$rq-> sortorder}";
		} else
			$ss_sort = "`{$t_xfd}`.fk ASC";  // default sorting by name, ascending

		list($ss_filters, $filter_callback) = self::helper_Grid_Filters($rq);

		$fields = $this-> rec-> xfdef_List($rq-> rt_id,
			/**
			 * Filtering function - Also includes security
			 * @param QueryFactory $q
			 * @uses:
			 * - $rq = Request Model
			 * - $ss_filters, $filter_callback = Necessary for grid filters
			 */
			function(QueryFactory &$q) use ($rq, $ss_filters, $filter_callback) {

				// Grid filters applied to fields
				foreach($ss_filters as $fkey=> $filter_set) {
					if($fkey == 't') {
						// Needs a translation
						$ft_translate_rev = array(
							"Text"=> 'text',
							"Single choice"=> 'enum',
							"Multiple choices"=> 'set',
							"Numeric value"=> 'number',
							"Date value"=> 'date',
							// Plugin fields (processed by custom code)
							"Monetary value"=> 'money',
							"Air Waybill"=> 'awb',
							"World Airport"=> 'airport'
						);
						foreach($filter_set as &$elem) {
							if(is_array($elem['v']))
								foreach($elem['v'] as &$val)
									$val = $ft_translate_rev[$val];
							else
								$elem['v'] = $ft_translate_rev[$elem['v']];
						}
					}
					$filter_callback($q, $filter_set, "`{$fkey}`");
				}
			},
		null, $ss_sort, $rq-> pagesize, $rq-> pagenum * $rq-> pagesize, true); // true = CALC FOUND ROWS!

		if(!$fields)
			return $this-> send_JSON(array(
				"found_rows"=> 0,
				"data"=> array()
			));

		// Gather data, post-process and return
		$rows = array();
		foreach($fields as $row) {
			$rows[] = $row;
		}

		// Send the issue data to the jqxGrid
		$this-> send_JSON(array(
			"found_rows"=> $this-> rec-> get_List_Save_FoundRows(),
			"data"=> $rows
		));
	}

	/**
	 * Returns the list of groups available for one group set.
	 * - Note: Not all group sets return a list !
	 */
	public function action_Get_Groups(MVC_Request $rq)
	{
		// The flexible carrier-based group types cannot be used as group filters
		if(in_array($rq-> group_type, array('airline', 'marine', 'customer', 'dept')))
			return $this-> send_JSON(array());

		// ... for the other types, read all available groups and return the list for the Group Filter checkbox-dropdown
		$m_group = new App_Model_Group($rq-> group_type);

		$groups = $m_group-> get_List();
		if(empty($groups))
			return $this-> send_JSON(array());

		$this-> send_JSON($groups);
	}
}