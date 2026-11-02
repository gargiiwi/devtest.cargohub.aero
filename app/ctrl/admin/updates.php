<?php
/**
 * Software Updates Editor
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2.9
 */
class App_Ctrl_Admin_Updates extends App_Ctrl_Base
{
	public static $_module_name = "Software Updates Editor";

	/**
	 * CMS model, used in most of the functions
	 *
	 * @var App_Model_CMS
	 */
	private $cms;

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
		$this-> cms = $this-> factory_Model('CMS');
	}

	/**
	 * Index action for the Template Editor controller
	 * @see MVC_Ctrl::action_Index()
	 */
	public function action_Index()
	{
		$this-> CFG['components'] = array("modal");
		$this-> CFG['modules'][] = "admin/_updates";
		$this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "dropdownlist", "datetimeinput", "calendar", "expander");
		$this-> CFG['jqwidgets'][] = array("-gridcomplete");
		$this-> CFG['xjs'] = array(
			'jqform'=> true
		);

		// prepare output
		$this-> prepare_Header(self::$_module_name);

		// Persistent grid state (session)
		if(isset(MVC_Core::$cache-> grid_state['sysupdates']))
			$this-> CFG['admin_updates']['grid_state'] = MVC_Core::$cache-> grid_state['sysupdates'];
		else {
			// Default grid state
			$this-> CFG['admin_updates']['grid_state'] = array(
				'pagenum'=> 0,
				'pagesize'=> 25,
				'sort'=> array(
					'column'=> "dt",
					'direction'=> "desc"
				),
				'filters'=> null
			);
		}

		$this-> texted = $this-> CFG['admin_updates']['texted'] = isset(MVC_Core::$ss-> user['preferences']['texted'])? MVC_Core::$ss-> user['preferences']['texted']: "TinyMCE";

		$this-> module = array(
			'icon'=> "blog.png",
			'icon_alt'=> "(blog)",
			'title'=> self::$_module_name,
			'description'=> "System Blog"
		);

		// which body template to use, and run the view
		$this-> site_section = "admin". DS. "updates.html";
		$this-> view("private.html");
	}

	/**
	 * Reads list of blog entries
	 */
	public function action_Get_Entries(MVC_Request $rq)
	{
		$count_rows = $rq-> pagesize;
		$start_row = $rq-> pagenum * $rq-> pagesize;

		// Requested sort order?
		if($rq-> sortdatafield && $rq-> sortorder) {
			$ss_sort = "`blg`.{$rq-> sortdatafield} {$rq-> sortorder}";
		} else
			$ss_sort = "`blg`.dt DESC";  // default sorting by date, descending (most recent entries on top)

		list($ss_filters, $filter_callback) = self::helper_Grid_Filters($rq);

		$entries = $this-> cms-> blog_Read(
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
			$row['clen'] = strlen($row['content']); unset($row['content']);
			$rows[] = $row;
		}

		// Send the information to the grid
		$this-> send_JSON(array(
			"found_rows"=> $this-> cms-> found_rows,
			"data"=> $rows
		));
	}

	/**
	 * Loads a blog entry for editing
	 * @param MVC_Request $in
	 */
	public function action_Load_Blog_Entry(MVC_Request $rq)
	{
		if(!$rq-> id)
			return header("HTTP/1.0 400 Blog entry ID not present in the request");

		echo $this-> cms-> blog_Read_Entry($rq-> id, true);
	}

	/**
	 * Creates or updates an organization template
	 *
	 * @param MVC_Request $post
	 * @return boolean
	 */
	public function action_Save_Blog_Entry(MVC_Request $post)
	{
		$params = $post-> entry;
		unset($params['content']);

		if($post-> id) {
			// Updating existing entry
			$r = $this-> cms-> blog_Update_Entry($post-> id, $post-> content, $params); // TODO: Ability to edit/save date, taglist
			if(!$r)
				return header("HTTP/1.0 500 Update failed. Some data might be incorrect");

			$response = "Blog entry successfully updated";

		} else {
			// Posting new entry
			$r = $this-> cms-> blog_Create($post-> content, $params);

			if(!$r)
				return header("HTTP/1.0 500 Unable to post the blog entry. Some data might be incorrect");

			$response = sprintf("Blog entry created: %s", $params['title']);
		}

		$this-> send_JSON(array('success'=> true, 'response'=> $response));
	}

	/**
	 * Deletes existing blog entry
	 *
	 * @param MVC_Request $post
	 */
	public function action_Delete_Blog_Entry(MVC_Request $post)
	{
		$r = $this-> cms-> blog_Delete_Entry($post-> id);
		if(!$r)
			return header("HTTP/1.0 500 Delete failed. Reason unknown");

		$this-> send_JSON(array('success'=> true, 'response'=> "Blog entry was deleted"));
	}

	/**
	 * Allows switching to another editor
	 * FIXME: This should probably not be here, but in a general "preferences" area? Since the editors are reused between several modules
	 */
	public function action_Entry_Editor_Options(MVC_Request $post)
	{
		// Read existing editor
		$preferences = MVC_Core::$ss-> user['preferences'];
		$texted = isset($preferences['texted'])? $preferences['texted']: "TinyMCE";

		if($post-> texted == $texted)
			return header("HTTP/1.0 409 This editor is the current option, please choose another or press Cancel");

		$preferences['texted'] = $post-> texted;

		switch($post-> texted) {
			case 'CodeMirror':
				$texted_fullname = "CodeMirror (Programer Editor)";
				break;
			case 'TinyMCE':
				$texted_fullname = "TinyMCE (Visual Editor)";
				break;
			default:
				return header("HTTP/1.0 400 Invalid option or request error");
		}
		$r = $this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "preferences", $preferences);
		if(!$r)
			return header("HTTP/1.0 500 Error while saving preferences");

		MVC_Core::$ss-> user['preferences'] = $preferences;

		$this-> evan-> add(sprintf("The default editor has been changed to %s.", $texted_fullname), 'add', array(
			'permanent'=> true,
			'timeout'=> time()+ 30
		));
		$this-> send_JSON(array('success'=> true));
	}

}