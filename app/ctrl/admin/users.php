<?php
/**
 * System-wide users management (Admin and HQ)
 * - Function to login as user
 * - Edit the user - Link to: /manager/user?id={user_id}
 * - Edit the user's Preferences and Settings - Link to: /user/settings?user_id={user_id} (No need to login as user)
 * - Function to manage user's contacts: /user/contacts?user_id={user_id} (No need to login as user)
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoITS
 * @version 1.0.dev
 */
class App_Ctrl_Admin_Users extends App_Ctrl_Base
{
	public static $_module_name = "Global Users Management";

	/**
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct();

		// Admin and Supervisor only module, redirect to home if no access
		if(!$this-> user-> is_Unrestricted()) {
			$this-> evan-> add("Move along now, nothing to see here.", 'lock');
			$this-> redirect($this-> CFG['home']);
		}
	}
 
	/**
	 * Index action for the Users list/search
	 *
	 * @see MVC_Ctrl::action_Index()
	 */
	public function action_Index()
	{
		$this-> CFG['modules'][] = "admin/_users";
		$this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "dropdownlist", "expander", "datetimeinput", "calendar");
 		$this-> CFG['jqwidgets'][] = array("-gridcomplete");
		$this-> CFG['jqwidgets'][] = array("data.export", "grid.export");
		$this-> CFG['xjs'] = array(
			'jqform'=> true
		);

		$this-> prepare_Header(self::$_module_name);

		// Get a quick list of organizations for the "Organization" column filter
		$orgs = $this-> org-> get_List(array(), function(QueryFactory &$q) {
			$q-> where('`count_users` > 0'); // Skip orgs without users
		}, null, null, 0);

		$this-> CFG['admin_users'] = array(
			'orgList'=> array()
		);
		// Fixme: This is difficult and cumbersome. There should be another way to choose/find organization, then list users
		foreach($orgs as $org)
			$this-> CFG['admin_users']['orgList'][] = $org['name'];

		$this-> module = array(
			'icon'=> "users.png",
			'icon_alt'=> "(org)",
			'title'=> self::$_module_name,
			'description'=> "Lists users and shows statistical information"
		);

		// which body template to use, and run the view
		$this-> site_section = "admin". DS. "users.html";
		$this-> view("private.html");
	}

	/**
	 * Get the list of users, with server side sorting/paging
	 */
	public function action_Get_List(MVC_Request $in)
	{
		// 1. Server-side Filters
		list($ss_filters, $filter_callback) = self::helper_Grid_Filters($in);

		// 2. Server-side Sort
		if($in-> sortdatafield && $in-> sortorder) {
			// Determine the database field used for sorting
			if($in-> sortdatafield == 'org_name')
				$ss_sort = "`org`.name ";
			elseif($in-> sortdatafield == 'dep_name')
				$ss_sort = "`dep`.name ";
			else
				$ss_sort = "`usr`.{$in-> sortdatafield} ";

			$ss_sort.= $in-> sortorder;
		} else {
			$ss_sort = "`usr`.logon_dt DESC"; // default sorting by most recently logged in first
		}

		$users = $this-> user-> get_List(array("user", "organization", "department"),
			function(QueryFactory $q) use ($ss_filters, $filter_callback) {
				foreach($ss_filters as $fkey=> $filter_set) {
					// Which table and database field is used for the filter column ?
					if($fkey == 'org_name')
						$dbfield = "`org`.name ";
					elseif($fkey == 'dep_name')
						$dbfield = "`dep`.name ";
					else
						$dbfield = "`usr`.{$fkey} ";
					$filter_callback($q, $filter_set, $dbfield);
				}
			}, null, $ss_sort, $in-> pagesize, $in-> pagenum * $in-> pagesize, true); // true = CALC FOUND!

		if($users && count($users)> 0) {
			// Send the list of users to the grid
			$this-> send_JSON(array(
				"found_rows"=> $this-> user-> found_rows,
				"data"=> $users
			));
		} else
			$this-> send_JSON(array()); // nothing found or error, empty grid
	}


}