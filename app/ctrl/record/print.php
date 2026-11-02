<?php
/**
 * Record Print-Preview Controller (staff only)
 * - Opens the record view into a full-screen TinyMCE which includes complete edit abilities + Print command ( power editor )
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2.7
 */
class App_Ctrl_Record_Print extends App_Ctrl_Base
{
	// Model references - Helps dev by providing autocompletion
	/**
	 * Reference to the Issue Model - TODO: Eventually rename "Issue" into "Record", but that will be a multi-step process since the issue concept is used in a lot of places
	 * @var App_Model_Issue
	 */
	public $issue;

	/**
	 * Reference to the Contact Model
	 * @var App_Model_Contact
	 */
	private $con;

	/**
	 * Constructor for Record Controller
	 */
	function __construct()
	{
		parent::__construct(true);

		// Controller specific models
		$this-> issue = $this-> factory_Model('Issue',  "rec");
		$this-> con = $this-> factory_Model("Contact", "con");
	}

	public static $_module_name = "Record Print Preview";

	//===== Interface Assembly Actions =====//

	/**
	 * Index action for the Record Process Controller (User Logged In)
	 * -- Constructs the main page (record metadata, access and prepare the tabs system)
	 * -- Tabs will then load content when they are initialized (And assume that security has already been checked
	 *
	 * @see MVC_Ctrl::action_Index()
	 */
	public function action_Index(MVC_Request $rq)
	{
		// Check Identifier (Track)
		if(!empty($_GET['_urlx']) && $_GET['_urlx'][0] == ':')
			$rq-> track = substr($_GET['_urlx'], 1);
		$rq-> add_Filter('track', "Is_TrackID");

		if(!$rq-> track) {
			$this-> evan-> add("Cannot print a record without a valid identifier!", "warning");
			MVC_Core::shutdown('/cms?path=/info/error');
		}

		$parts = array(
			'list'=> explode(',', $rq-> parts),
			'contacts'=> explode(',', $rq-> contacts)
		);

		// Using templates or a default view ?
		if($rq-> template_id) {
			// Custom organization template - Read data normally
			$m_tpl = new App_Model_Template();
			$template = $m_tpl-> read($rq-> template_id);
		} else {
			// Default _render template, use the built-in
			$template = array(
				'title'=> "Default Rendering",
				'body'=> null
			);
		}
		// This is actually a reference, which is constructed by the "render" function
		$tpl_data = array();

		$this-> title = $template['title'];
		$this-> body = "<br />\n".$this-> issue-> render($rq-> track, $parts, $rq-> org_id, $template['body'], $tpl_data); // Render the record

		$this-> rt = $tpl_data['ist'];

		$this-> view("print.html");
	}
}