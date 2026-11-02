<?php
/**
 * Global message search module
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2.9.6
 */
class App_Ctrl_Admin_Messages extends App_Ctrl_Base
{
	public static $_module_name = "Global Message Search";

	/**
	 * The contact model
	 *
	 * @var App_Model_Contact
	 */
	private $con;

	/**
	 * Message model (Communication)
	 * @var App_Model_Comm
	 */
	private $comm;

	/**
	 * Conversation model (Used to link multiple messages together)
	 * @var App_Model_Conversation
	 */
	private $cnv;

	/**
	 * Admin-only module, redirect to home if no access
	 */
	public function __construct()
	{
		parent::__construct();

		if(!isset(MVC_Core::$ss-> user['is_admin'])) {
			$this-> evan-> add("Move along now, nothing to see here.", 'lock');
			$this-> redirect($this-> CFG['home']);
		}

		$this-> con = $this-> factory_Model("contact", 'con');
		$this-> comm = $this-> factory_Model("comm");
		$this-> cnv = $this-> factory_Model("Conversation", "cnv");
	}

	/**
	 * Index action for the Groups Display and Management page
	 * @see MVC_Ctrl::action_Index()
	 */
	public function action_Index(MVC_Request $in)
	{

		$this-> CFG['modules'][] = "admin/_messages";
		$this-> CFG['admin_messages'] = array();

		$this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "dropdownlist", "expander", "datetimeinput", "calendar");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete");
		$this-> CFG['jqwidgets'][] = array("data.export", "grid.export");

		// prepare output
		$this-> prepare_Header(self::$_module_name);

		// Persistent grid state (session)
		if(isset(MVC_Core::$cache-> grid_state['sysmessages']))
			$this-> CFG['admin_messages']['grid_state'] = MVC_Core::$cache-> grid_state['sysmessages'];
		else {
			// Default grid state
			$this-> CFG['admin_messages']['grid_state'] = array(
				'pagenum'=> 0,
				'pagesize'=> 25,
				'sort'=> array(
					'column'=> "dt",
					'direction'=> "desc"
				),
				'filters'=> null
			);
		}

		$this-> module = array(
			'icon'=> "email.png",
			'icon_alt'=> "(msg)",
			'title'=> self::$_module_name,
			'description'=> "Find and view any recorded message"
		);

		// which body template to use, and run the view
		$this-> site_section = "admin". DS. "messages.html";
		$this-> view("private.html");
	}

	/**
	 * Reads from all system messages, with filter
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Get_Messages(MVC_Request $rq)
	{
		$count_rows = $rq-> pagesize;
		$start_row = $rq-> pagenum * $rq-> pagesize;

		// Requested sort order?
		if($rq-> sortdatafield && $rq-> sortorder) {

			if($rq-> sortdatafield == 'from_name')
				$ss_sort = "`send`.name ";
			elseif($rq-> sortdatafield == 'from_email')
				$ss_sort = "`send`.email ";
			else
				$ss_sort = "`msg`.{$rq-> sortdatafield} ";

			$ss_sort.= $rq-> sortorder;
		} else
			$ss_sort = "`msg`.dt DESC";  // default sorting by date, descending (most recent entries on top)

		list($ss_filters, $filter_callback) = self::helper_Grid_Filters($rq);

		$entries = $this-> comm-> get_List(array('meta', 'sender', 'convo', 'record_link'),
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

					// Which table and database field is used for the filter column ?
					if($fkey == 'from_name') {
						$dbfield = "`send`.name";
					} elseif($fkey == 'from_email') {
						$dbfield = "`send`.email";
					} else
						$dbfield = "`msg`.{$fkey}";

					$filter_callback($q, $filter_set, $dbfield);
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
			if(!empty($row['cnv_id']))
				$row['cnv_id'] = (int)$row['cnv_id'];
			if(!empty($row['from_id']))
				$row['from_id'] = (int)$row['from_id'];
			if(!empty($row['convo']['link_id']))
				$row['convo']['link_id'] = (int)$row['convo']['link_id'];
			if(!empty($row['convo']['link_id']))
				$row['convo']['org_id'] = (int)$row['convo']['org_id'];
			$rows[] = $row;
		}

		// Send the information to the grid
		$this-> send_JSON(array(
			"found_rows"=> $this-> comm-> get_List_Save_FoundRows(),
			"data"=> $rows
		));
	}

}
