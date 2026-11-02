<?php
/**
 * System Databases administration
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2019.06.17
 */
class App_Ctrl_Admin_Sysdata extends App_Ctrl_Base
{
	public static $_module_name = "System Databases";

	/**
	 * Reference to the Data model
	 *
	 * @var App_Model_Data
	 */
	private $sysdata;

	/**
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct();

		$rq = new MVC_Request();

		// Controller specific models
		if($rq-> type)
			// Specific data model is only initialized if type of data is known
			$this-> sysdata = $this-> factory_Model('Data_'.($rq-> type), "sysdata");
	}

	/**
	 * Index action for the Groups Display and Management page
	 * @see MVC_Ctrl::action_Index()
	 */
	public function action_Index(MVC_Request $rq)
	{
		// Admin-only module, redirect to home if no access
		if(!$this-> user-> is_Unrestricted()) {
			$this-> evan-> add("Move along now, nothing to see here.", 'lock');
			$this-> redirect($this-> CFG['home']);
		}

		$this-> CFG['modules'][] = "admin/_sysdata";
		$this-> CFG['admin_sysdata'] = array();

		if($rq-> type) {
			// Sysdata table type is known, editor is active
			$this-> config = $this-> sysdata-> get_Config(); // for template

			// common configuration
			$this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "dropdownlist", "switchbutton", "expander");
			$this-> CFG['jqwidgets'][] = array("-gridcomplete");

			$editor_config = $this-> sysdata-> get_Editor_Config(); // for javascript

			// Initialize variables depending on the plugins' returned config
			if(isset($editor_config['javascript'])) {
				// Modules list
				$this-> CFG['modules'][] = "sysdata/_". $rq-> type;

				// Uses extra jqWidgets modules ?
				if(isset($editor_config['javascript']['jqwidgets']))
					$this-> CFG['jqwidgets'][] = array_unique($editor_config['javascript']['jqwidgets']);

				// Extra JS?
				if(isset($editor_config['javascript']['xjs']))
					foreach($editor_config['javascript']['xjs'] as $xjs_key=> $xjs_value)
						$this-> CFG['xjs'][$xjs_key] = $xjs_value;

				// JS Modules configuration
				if(!empty($editor_config['javascript']['modules']))
					foreach($editor_config['javascript']['modules'] as $module_name=> $module_config)
						$this-> CFG[$module_name] = $module_config;
			}

			// Main JS Configuration
			$this-> CFG['admin_sysdata'] = $editor_config['base'];
		} else
			// Group type not known, only show the presentation
			$this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "dropdownlist", "expander");

		// prepare output
		$this-> prepare_Header("System databases");
		// Persistent grid state (session) - Individual for each data type
		if(isset(MVC_Core::$cache-> grid_state['sysdata_'. $rq-> type]))
			$this-> CFG['admin_sysdata']['grid_state'] = MVC_Core::$cache-> grid_state['sysdata_'. $rq-> type];
		else {
			// Default grid state
			$this-> CFG['admin_sysdata']['grid_state'] = array(
				'pagenum'=> 0,
				'pagesize'=> 25,
				'filters'=> null
			);
		}

		$this-> module = array(
			'icon'=> "database.png",
			'icon_alt'=> "(db)",
			'title'=> self::$_module_name,
			'description'=> "Sets of data used by various components"
		);

		// which body template to use, and run the view
		$this-> site_section = "admin". DS. "sysdata.html";
		$this-> view("private.html");
	}

	/**
	 * Reads data entries, with filter
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Get_List(MVC_Request $rq)
	{
		$count_rows = $rq-> pagesize;
		$start_row = $rq-> pagenum * $rq-> pagesize;

		// Requested sort order?
		if($rq-> sortdatafield && $rq-> sortorder) {
			$ss_sort = $rq-> sortdatafield.' '.$rq-> sortorder;
		} else
			$ss_sort = "`id` ASC";  // default sorting by name, ascending

		list($ss_filters, $filter_callback) = self::helper_Grid_Filters($rq);

		$entries = $this-> sysdata-> get_List(
			/**
			 * Filtering function
			 * @param QueryFactory $q
			 * @uses:
			 * - $rq = Request Model
			 * - $ss_filters, $filter_callback = Necessary for grid filters
			 */
			function(QueryFactory &$q) use ($rq, $ss_filters, $filter_callback) {

				// Grid filters
				foreach($ss_filters as $fkey=> $filter_set)
					$filter_callback($q, $filter_set, $fkey);
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
			$rows[] = $row;
		}

		// Send the information to the grid
		$this-> send_JSON(array(
			"found_rows"=> $this-> sysdata-> get_List_Save_FoundRows(),
			"data"=> $rows
		));
	}

	/**
	 * Saves the data entry (create or edit)
	 * -- The data plugin will also save any other attached data (if it exists)
	 */
	public function action_Save_Entry(MVC_Request $rq)
	{
		$sysdata_cfg = $this-> sysdata-> get_Config();
		if($rq-> id) {
			// edit
			$r = $this-> sysdata-> update($rq-> id, $rq-> data, 'id');
			if(!$r)
				return header("HTTP/1.0 500 Error encountered while updating the data entry");

			$this-> send_JSON(array(
				'success'=> true,
				'response'=> "The {$sysdata_cfg['name']} with id {$rq-> id} has been updated."
			));
		} else {
			// create
			$r = $this-> sysdata-> create($rq-> data);
			if(!$r)
				return header("HTTP/1.0 500 Error encountered creating the data entry.");

			$this-> send_JSON(array(
				'success'=> true,
				'response'=> "The {$sysdata_cfg['name']} \"{$rq-> group['name']}\" was created"
			));
		}
	}

	/**
	 * Removes a data entry from the database
	 * WARNING: Other information which might depend on it will probably show errors
	 */
	public function action_Delete_Entry(MVC_Request $rq)
	{
		$sysdata_cfg = $this-> sysdata-> get_Config();
		if($rq-> id > 0) {

			// Read the group data first, to check if it exists ( and get it's name )
			$entry = $this-> sysdata-> read($rq-> id);
			if(!$entry) {
				$this-> send_Fail_JSON("{$sysdata_cfg['name']} with id {$rq-> id} not found, perhaps it was already deleted?");
				return;
			}

			$r = $this-> sysdata-> delete($rq-> id);
			if($r) {
				$this-> evan-> add("The {$sysdata_cfg['name']} with name <b>{$entry['name']}</b> has been deleted from the system.", "remove", array(
					'permanent'=> true,
					'timeout'=> time()+ 30
				));
				if($entry['count_elements'] > 0)
					$this-> evan-> add("There might be {$entry['count_elements']} leftover elements which were connected to this {$sysdata_cfg['name']}", "notify");

				$this-> send_JSON(array('success'=> true));
			} else
				$this-> send_Fail_JSON("Unknown error when attempting to delete the {$sysdata_cfg['name']}.");
		} else
			$this-> send_Fail_JSON("Please specify a {$sysdata_cfg['name']} id when requesting deletion.");
	}

	/**
	 * Calls a custom function from the loaded plugin
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Plugin_Call(MVC_Request $rq)
	{
		$r = $this-> group-> plugin_Call($rq);
		if(!$r)
			$this-> send_JSON(array()); // not found, send empty list?
		else
			$this-> send_JSON($r);
	}

}