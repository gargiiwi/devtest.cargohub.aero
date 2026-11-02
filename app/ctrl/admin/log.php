<?php
/**
 * System log viewer and editor
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2020.05.20
 */
class App_Ctrl_Admin_Log extends App_Ctrl_Base
{
	public static $_module_name = "System Log";

	/**
	 * Syslog model, used in most of the functions
	 *
	 * @var App_Model_Syslog
	 */
	private $syslog;

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
		$this-> syslog = $this-> factory_Model('Syslog');
	}

	public function action_Post(MVC_Request $rq)
	{
		$iid = $this-> syslog-> log($this-> user-> ID(), "var", null, null, $rq-> comment);

		$this-> send_JSON(array("response"=> sprintf("Created item ID: %d", $iid)));
	}

	/**
	 * Reads list of system log items
	 */
	public function action_Read_Log(MVC_Request $rq)
	{
		$rq-> add_Filter("virtualmode", "boolean");
		if($rq-> virtualmode) {
			$count_rows = $rq-> pagesize;
			$start_row = $rq-> pagenum * $rq-> pagesize;
		} else {
			$count_rows = 1000; // Hard limit on returned rows when working in client-side filter - TODO: Send a warning to the user that there are too many records and they can't all be loaded
			$start_row = 0;
		}

		// Requested sort order?
		if($rq-> sortdatafield && $rq-> sortorder) {

			// Sorting on one of the attached tables
			switch($rq-> sortdatafield) {
				// Basic task fields
				case 'user_name':
					$ss_sort = "`usr`.name "; break;
				default:
					$ss_sort = "`log`.{$rq-> sortdatafield} ";
			}
			$ss_sort.= $rq-> sortorder;
		}

		// Set some defaults
		if(!isset($ss_sort))
			$ss_sort = "`log`.dt DESC"; // default sorting by date, descending (most recent entries on top)

		list($ss_filters, $filter_callback) = self::helper_Grid_Filters($rq);

		$entries = $this-> syslog-> get_List(
			/**
			 * Filtering function
			 * @param QueryFactory $q
			 * @uses:
			 * - $rq = Request Model
			 * - $ss_filters, $filter_callback = Necessary for grid filters
			 */
			function(QueryFactory &$q) use ($rq, $ss_filters, $filter_callback) {
				// Virtual mode jqxGrid filters
				if($rq-> virtualmode == "true") {
					foreach($ss_filters as $fkey=> $filter_set) {
						// Which table and database field is used for the filter column ?
						switch($fkey) {
							case 'user_name':
								$dbfield = "`usr`.name"; break;
							default:
								$dbfield = "`log`.{$fkey}";
						}
						$filter_callback($q, $filter_set, $dbfield);
					}
				}

			},
			null, $ss_sort, $rq-> pagesize, $rq-> pagenum * $rq-> pagesize, $rq-> virtualmode
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
			"found_rows"=> $this-> syslog-> found_rows,
			"data"=> $rows
		));
	}

	/**
	 * Loads a log item for editing
	 * @param MVC_Request $rq
	 */
	public function action_Load_Item(MVC_Request $rq)
	{
		if(!$rq-> id)
			return header("HTTP/1.0 400 Log item ID not present in the request");

// 		echo $this-> syslog-> blog_Read_Entry($rq-> id, true);
	}

	/**
	 * Saves a log item
	 *
	 * @param MVC_Request $rq
	 * @return boolean
	 */
 	public function action_Save(MVC_Request $rq)
	{
		$item = $rq-> item;

		if(!$rq-> id)
			return header("HTTP/1.0 400 Log item ID not present in the request");

		$r = $this-> syslog-> update((int)$rq-> id, $rq-> item);
		if(!$r)
			return header("HTTP/1.0 500 Update failed. Some data might be incorrect");

		$this-> send_JSON(array('response'=> "Log item was updated"));
	}

	/**
	 * Deletes existing log item
	 *
	 * @param MVC_Request $rq
	 */
	public function action_Delete(MVC_Request $rq)
	{
		if(!$rq-> id)
			return header("HTTP/1.0 400 Log item ID not present in the request");

		$r = $this-> syslog-> delete((int)$rq-> id);
		if(!$r)
			return header("HTTP/1.0 500 Delete failed. Reason unknown");

		$this-> send_JSON(array('response'=> "Log item was deleted"));
	}

}