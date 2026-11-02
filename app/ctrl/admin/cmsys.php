<?php
/**
 * Content Management System page editor (Admin only module)
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2.9
 */
class App_Ctrl_Admin_CMSys extends App_Ctrl_Base
{
	public static $_module_name = "Content Management System";

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
		$this-> CFG['modules'][] = "admin/_cmsys";
		$this-> CFG['jqwidgets'][] = array("scrollbar", "panel", "tree", "expander", "splitter");
		$this-> CFG['xjs'] = array(
			'animatecolors'=> true,
			'jqform'=> true
		);
		$this-> texted = $texted = isset(MVC_Core::$ss-> user['preferences']['texted'])? MVC_Core::$ss-> user['preferences']['texted']: "TinyMCE";
		$this-> CFG['admin_cmsys'] = array(
			'texted'=> $texted
		);

		// prepare output
		$this-> prepare_Header("Content Management ({$texted})");

		$this-> module = array(
			'icon'=> "cmsys.png",
			'icon_alt'=> "(cm)",
			'title'=> self::$_module_name,
			'description'=> "Edits pages and information documents"
		);

		// output tree
		$this-> templates = $this-> cms-> read_FileTree();

		// which body template to use, and run the view
		$this-> site_section = "admin". DS. "cmsys.html";
		$this-> view("private.html");
	}

	/**
	 * Load CMS Page
	 */
	public function action_Load_Page(MVC_Request $in)
	{
		$relative_path = @base64_decode($in-> path);

		if($relative_path === false)
			return $this-> send_Fail_JSON("Invalid path was specified, unable to load the page.");

		$page = $this-> cms-> read($relative_path);

		// calculate response based on data
		if($page) {
 			$this-> send_JSON(array(
 				'success'=> true,
				'page'=> $page
 			));
		} else
			$this-> send_Fail_JSON("There was a problem loading this page. Database might be desynchronized.");
	}

	/**
	 * Update the CMS page
	 * - Contents goes into the file
	 * - Metadata go into the database
	 *
 	 * @param MVC_Request $post
	 */
	public function action_Save_Page(MVC_Request $post)
	{
		$r = $this-> cms-> update(base64_decode($post-> path), $post-> contents, $post-> meta);
		//print_r($r);
		if(!$r)
			return $this-> send_Fail_JSON("Error while attempting to save the page.");

		$this-> send_JSON(array('success' => true));
 	}

 	/**
 	 * Creates a CMS page, both the file and the matching database entry
 	 */
    public function action_Create_Page(MVC_Request $post)
    {
		if(!isset($post-> page))
			return $this-> send_Fail_JSON("Parameters not specified, possible interface error.");

		$r = $this-> cms-> create($post-> page['path'], "(please write me)", $post-> page['title'], null, $post-> page['description']?: null);

		if(!$r || is_string($r))
			return $this-> send_Fail_JSON($r?:"Error while attempting to create the page.");

		$this-> evan-> add(sprintf("The page <b>%s</b> has been added to the Content System", $post-> page['title']), 'add', array(
			'permanent'=> true,
			'timeout'=> time()+ 30
		));
		$this-> send_JSON(array('success' => true));
    }

    /**
     * Deletes a CMS page and removes the corresponding entry from the database
     */
	public function action_Delete_Page(MVC_Request $post)
	{
		$path = base64_decode($post-> path);

		$r = $this-> cms-> delete($path);
		if(!$r)
			return $this-> send_Fail_JSON("This page cannot be deleted (Operation might be restricted for it).");

		$this-> evan-> add(sprintf("The page on path: <b>%s</b> has been removed.", $path), 'remove', array(
			'permanent'=> true,
			'timeout'=> time()+ 30
		));
		$this-> send_JSON(array('success' => true));
	}

	/**
	 * Saves the special preview file which will be used inside an iframe
	 *
	 * @param MVC_Request $post
	 */
	public function action_Save_Preview(MVC_Request $post)
	{
		$r = $this-> cms-> save_Preview($post-> contents);
		if(!$r)
			return $this-> send_Fail_JSON("Error while creating the preview file.");

		$this-> send_JSON(array('success' => true));
	}

	/**
	 * Shows the inside of the iframe containing the preview of the CMS page.
	 * The preview uses the current application styles as it loads through a real HTML file. Plugins however are not loaded
	 */
	public function action_Show_Preview()
	{
		$this-> contents = $this->_drytpl-> render_String($this-> cms-> read_Preview()); // No data yet?
		$this-> view("cmspreview.html");
	}

	/**
	 * Allows switching to another editor
	 */
	public function action_CMS_Preferences(MVC_Request $post)
	{
		// Read existing editor
		$preferences = MVC_Core::$ss-> user['preferences'];
		$texted = isset($preferences['texted'])? $preferences['texted']: "TinyMCE";

		if($post-> texted == $texted)
			return header("HTTP/1.0 409 This editor is the current option, please choose another or press Cancel");

		$preferences['texted'] = $post-> texted;

		switch($post-> texted) {
			case 'CodeMirror':
				$texted_fullname = "CodeMirror (Programer Editor)"; break;
			case 'TinyMCE':
				$texted_fullname = "TinyMCE (Visual Editor)"; break;
			default:
				return header("HTTP/1.0 400 Invalid option or request error");
		}
		$r = $this-> user-> profile_Write(MVC_Core::$ss-> user['id'], "preferences", $preferences);
		if(!$r)
			return header("HTTP/1.0 500 Server error. Unable to save preferences");

		MVC_Core::$ss-> user['preferences'] = $preferences;

		$this-> evan-> add(sprintf("The default content editor has been changed to %s.", $texted_fullname), 'ok', array(
			'permanent'=> true,
			'timeout'=> time()+ 30
		));
		$this-> send_JSON(array('success'=> true));
	}
}