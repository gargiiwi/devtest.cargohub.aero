<?php
/**
 * Global Contacts Management (Admin)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers.Admin
 * @version 2018.11
 */
class App_Ctrl_Admin_Contacts extends App_Ctrl_Base
{
	public static $_module_name = "Admin - Global Contacts";

	/**
	 * The contact model
	 *
	 * @var App_Model_Contact
	 */
	private $con;

	/**
	 * Admin-only module, redirect to home if no access
	 */
	public function __construct()
	{
		parent::__construct();

		if(!$this-> user-> is_Unrestricted()) {
			$this-> evan-> add("Move along now, nothing to see here.", 'lock');
			$this-> redirect($this-> CFG['home']);
		}

		$this-> con = $this-> factory_Model("contact", 'con');
	}

	/**
	 * Global Contacts Search is a grid which has the ability to do server-side filtering for all contacts.
	 * -- Various functions are provided to allow editing (using the contact editor) and also merging of contacts (will affect all places where contacts are used)
	 *
	 * @see MVC_Ctrl::action_Index()
	 */
	public function action_Index()
	{
		$this-> CFG['components'] = array("modal", "contact_editor");
		array_push($this-> CFG['modules'], "admin/_contacts");
		$this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "combobox", "dropdownlist", "calendar", "datetimeinput", "expander");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete");

		$this-> CFG['xjs'] = array(
			'jqform'=> true,
			'dateformat'=> true
		);

		$this-> prepare_Header(self::$_module_name);

		// Javascript Config
		$this-> CFG['admin_contacts'] = array();

		// Read the contact field groups and settings so the dynamic contact editor can be initialized
		MVC_NLS::load_Groups("contact");
		$fields_structure = MVC_NLS::get_Object("contact", true);

		// Process the fields stucture a bit before sending it to JS
		foreach($fields_structure as &$group) {
			foreach($group['fields'] as $field=> $string) {
				if(strpos($string, "~"))
					$group['fields'][$field] = explode("~", $string);
			}
		}

		$this-> CFG['admin_contacts']['vcard_fields_structure'] = $fields_structure;

		$this-> module = array(
			'icon'=> "vcard.png",
			'icon_alt'=> "(vcard)",
			'title'=> self::$_module_name,
			'description'=> "Find and Edit any contact in the system"
		);

		// which body template to use, and run the view
		$this-> site_section = "admin". DS. "contacts.html";
		$this-> view("private.html");
	}

	/**
	 * Get the list of contacts, with server side sorting/paging
	 */
	public function action_Get_List(MVC_Request $in)
	{
		// 1. Server-side Filters
		list($ss_filters, $filter_callback) = self::helper_Grid_Filters($in);

		// 2. Server-side Sort
		if($in-> sortdatafield && $in-> sortorder) {
			// Determine the database field used for sorting
			if($in-> sortdatafield == 'company_name')
				$ss_sort = null; // Sorting should not be possible here
			else
				$ss_sort = "`ctx`.`{$in-> sortdatafield}` ";

			$ss_sort.= $in-> sortorder;
		} else {
			$ss_sort = "`ctx`.`email` ASC"; // default sorting by contact's name
		}

		$contacts = $this-> con-> get_List(null, null, null, array('contact', 'xdata'),
			$ss_sort, $in-> pagesize, $in-> pagenum * $in-> pagesize,
			function(QueryFactory $q) use ($ss_filters, $filter_callback, $in) {
				foreach($ss_filters as $fkey=> $filter_set) {
					// Which table and database field is used for the filter column ?
					if($fkey == 'company_name') {
						$dbfield = "`ctx`.xdata ";
						$filter_set[0]['value'] = "\"company_name\":\"".ltrim($filter_set[0]['value']);
					} else
						$dbfield = "`ctx`.{$fkey} ";
					$filter_callback($q, $filter_set, $dbfield);
				}

				if($in-> org_id)
					$q-> where("org_id = ?", $in-> org_id);

			}, null, true);

 		if(empty($contacts))
			return $this-> send_JSON(array()); // empty result ???

 		$this-> send_JSON(array(
 			"found_rows"=> $this-> con-> found_rows,
 			"data"=> $contacts
 		));
	}

}