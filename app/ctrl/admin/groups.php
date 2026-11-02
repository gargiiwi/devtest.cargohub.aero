<?php
/**
 * Groups Administration controller
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2019.01.20
 */
class App_Ctrl_Admin_Groups extends App_Ctrl_Base
{
	/**
	 * Reference to the Group model
	 *
	 * @var App_Model_Group
	 */
	private $group;

	/**
	 * Makes initializations useful for any action
	 */
	public function __construct()
	{
		parent::__construct();

		$in = new MVC_Request('get');

		// Controller specific models
		if($in-> type)
			// Group model is only initialized if a group type is known (The module loads even without a group type, but cannot show the grid, instead it shows a small presentation)
			$this-> group = $this-> factory_Model('Group', null, $in-> type);
	}

	/**
	 * Index action for the Groups Display and Management page
	 * @see MVC_Ctrl::action_Index()
	 */
	public function action_Index(MVC_Request $in)
	{
		// Admin-only module, redirect to home if no access
		if(!$this-> user-> is_Unrestricted()) {
			$this-> evan-> add("Move along now, nothing to see here.", 'lock');
			$this-> redirect($this-> CFG['home']);
		}

		$this-> CFG['modules'][] = "admin/_groups";
		$this-> CFG['admin_groups'] = array();

		if($in-> type) {
			// Group type is known, editor is active
			$this-> group_config = $this-> group-> get_Config(); // for template

			// common configuration
			$this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "dropdownlist", "switchbutton", "expander");
			$this-> CFG['jqwidgets'][] = array("-gridcomplete");

			$editor_config = $this-> group-> get_Config(true, "admin_groups"); // for javascript

			// Initialize variables depending on the plugins' returned config
			if(isset($editor_config['javascript'])) {
				// Modules list
				$this-> CFG['modules'][] = "group/_". $in-> type;

				// Uses extra jqWidgets modules ?
				if(isset($editor_config['javascript']['jqwidgets']))
					$this-> CFG['jqwidgets'][] = array_unique($editor_config['javascript']['jqwidgets']);

				// Extra JS?
				if(isset($editor_config['javascript']['xjs']))
					foreach($editor_config['javascript']['xjs'] as $xjs_key=> $xjs_value)
						$this-> CFG['xjs'][$xjs_key] = $xjs_value;

				// JS Modules configuration
				foreach($editor_config['javascript']['modules'] as $module_name=> $module_config)
					$this-> CFG[$module_name] = $module_config;
			}

			// Main JS Configuration
			$this-> CFG['admin_groups']['group'] = $editor_config['group'];
		} else
			// Group type not known, only show the presentation
			$this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "dropdownlist", "expander");

		// prepare output
		$this-> prepare_Header("Groups Manager");

		// which body template to use, and run the view
		$this-> site_section = "admin". DS. "groups.html";
		$this-> view("private.html");
	}

	/**
	 * Get the list of groups of a specified type
	 */
	public function action_Get_List()
	{
		// TODO: Since groups can be numerous, do server-side filtering / sorting / paging (just like for users) ... but for now it's ok with client-side
		$groups = $this-> group-> get_List(array('group', 'foreign'));

		if($groups)
			$this-> send_JSON($groups);
 		else
 			$this-> send_JSON(array()); // Empty, this is a request from grid
	}

	/**
	 * Saves the group (create or edit)
	 * -- The group plugin will also save any other attached data (if it exists)
	 */
	public function action_Save_Group(MVC_Request $rq)
	{
		$group_cfg = $this-> group-> get_Config();
		if($rq-> id) {
			// edit
			$r = $this-> group-> update($rq-> id, $rq-> group);
			if($r) {
				$this-> send_JSON(array(
					'success'=> true,
					'response'=> "The {$group_cfg['name']} with id {$rq-> id} has been updated."
				));
			} else
				$this-> send_Fail_JSON("Unknown error when attempting to update the {$group_cfg['name']}.");
		} else {
			// create
			$r = $this-> group-> create($group_cfg['type'], $rq-> group);
			if(is_int($r) && $r>0) {
				$this-> send_JSON(array(
					'success'=> true,
					'response'=> "The {$group_cfg['name']} \"{$rq-> group['name']}\" was created"
				));
			} else
				$this-> send_Fail_JSON("Unable to create the new {$group_cfg['name']}. Unknown error.");
		}
	}

	/**
	 * Removes a group from the database
	 */
	public function action_Delete_Group()
	{
		$group_cfg = $this-> group-> get_Config();

		$post = new MVC_Request('post');

		if($post-> id > 0) {

			// Read the group data first, to check if it exists ( and get it's name )
			$group = $this-> group-> read($post-> id);
			if(!$group) {
				$this-> send_Fail_JSON("{$group_cfg['name']} with id {$post-> id} not found, perhaps it was already deleted?");
				return;
			}

			$r = $this-> group-> delete($post-> id);
			if($r) {
				$this-> evan-> add("The {$group_cfg['name']} with name <b>{$group['name']}</b> has been deleted from the system.", "remove", array(
					'permanent'=> true,
					'timeout'=> time()+ 30
				));
				if($group['count_elements'] > 0)
					$this-> evan-> add("There might be {$group['count_elements']} leftover elements which were connected to this {$group_cfg['name']}", "notify");

				$this-> send_JSON(array('success'=> true));
			} else
				$this-> send_Fail_JSON("Unknown error when attempting to delete the {$group_cfg['name']}.");
		} else
			$this-> send_Fail_JSON("Please specify a {$group_cfg['name']} id when requesting deletion.");
	}

	/**
	 * Gets the list of organizations for selecting a master organization for a certain group
	 * -- If group_id is specified, only organizations which are connected to the specified group will be returned (even none)
	 */
	public function action_Organization_List(MVC_Request $in)
	{
		if(MVC_Core::$ss-> user['is_admin']) {
			// admin can see all organizations in the system; quick list (id/name)
			if($in-> group_id) {
				$org_r = $this-> org-> get_Group_Linked_Organizations($in-> group_id, null, true);
				$organizations = array();
				foreach($org_r as $row) {
					$organizations[] = $row;
				}
			} else
				$organizations = $this-> org-> get_List(array());
		} else {
			// test if the user has the ability to see more than his own organization
			// Note: This isn't implemented yet, it's here just for future reference
// 			if($this-> user-> test_ACL("privileges", "view_ext_org")) {
// 				$org_list = MVC_Core::$ss-> user['acls']['ext_orgs'];
// 			}
		}
		if(count($organizations)>0)
			$this-> send_JSON($organizations);
		else
			$this-> send_JSON(array()); // empty list?
	}

	/**
	 * Reads the groups of a certain type which are connected to an organization
	 */
	public function action_Get_Org_Domains(MVC_Request $in)
	{
		// read the groups for the organization
		$org_domains = $this-> org-> domain_Get_List($in-> org_id, true);
		if($org_domains) {
			$this-> send_JSON($org_domains); // send the domain list to the DropDownList
		} else
			$this-> send_JSON(array()); // not found, empty list
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