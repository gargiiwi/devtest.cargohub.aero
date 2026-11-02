<?php
/**
 * View-Mode Primary Record Controller (can be used by both staff and externally)
 * - This handles any kind of record viewing which doesn't involve editing controls - Used for printing, Page view, Template based view, Encapsulation and others
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2.7
 */
class App_Ctrl_Record_View extends App_Ctrl_Base
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
		parent::__construct();

		// Controller specific models
		$this-> issue = $this-> factory_Model('Issue',  "rec");
		$this-> con = $this-> factory_Model("Contact", "con");
	}

	public static $_module_name = "Record View and Print";

	//===== Interface Assembly Actions =====//

	/**
	 * Index action for the Record Process Controller (User Logged In)
	 * -- Constructs the main page (record metadata, access and prepare the tabs system)
	 * -- Tabs will then load content when they are initialized (And assume that security has already been checked
	 *
	 * @see MVC_Ctrl::action_Index()
	 */
	public function action_Index(MVC_Request $in)
	{
		// Check Identifier (Track)
		if(!empty($_GET['_urlx']) && $_GET['_urlx'][0] == ':')
			$in-> track = substr($_GET['_urlx'], 1);
		$in-> add_Filter('track', "Is_TrackID");

		if(!$in-> track) {
			$this-> evan-> add("Cannot view a record without a valid identifier!", "warning");
			MVC_Core::shutdown('/kb?page=403');
		}

		// TODO: Review this, it's the old "Print Page" code
		$issue_wtr = array('issue', 'type', 'xfields', 'group', 'organization', 'submitter', 'submitter_xdata', 'files');
		$iss = $this-> issue-> read($in-> track, $issue_wtr, null, null, null, array('public'=> true)); // Read files data which should only be visible to everyone (as in, not by staff)

		if($iss['access_key'] != $in-> key) {
			$this-> evan-> add("Access key doesn't match the stored record", "error");
			MVC_Core::shutdown('/kb?page=403');
		}

		$ist_read_or = $this-> rt_Scan_Override($iss['type_id']);
		if($ist_read_or != $iss['type_id'])
			$this-> issue-> cache_Reset(array('ist'));
		$ist = $this-> issue-> ist_Read($ist_read_or);

		// Do a special processing on "Set" xfield types only needed here in print view, so the $ist contains a marker if values are on and off
		// Note: This is an UGLY hack. Make better way to process SET fields
		foreach($ist['xfield_def'] as $key=>&$xfd) {
			if($xfd['t'] == 'set') {
				$set_check = array();
				foreach($xfd['cd_ex'] as $store=> $view) {
					$el = null;
					if(isset($iss['xfields'][$key]))
						foreach($iss['xfields'][$key] as $entry)
							if($entry['key'] == $store) {
								$el = array('view'=> $view, 'active'=> true);
								break;
							}
						if(!$el)
							$el = array('view'=> $view, 'active'=> false);
						$set_check[] = $el;
				}
				$xfd['set_check'] = $set_check;
			}
		}

		$this-> iss = $iss;
		$this-> ist = $ist;

		// Using templates or a default view ?
		if($in-> tpl) {
			$m_tpl = new App_Model_Template();
			$template = $m_tpl-> read($in-> tpl);
		} else {
			// ???
			$template = array();
		}

		switch($in-> mode) {
			// Print View - Uses an enclosure template optimized for printing the document
			case 'print':
				$this-> title = $template['title'];
 				$this-> body = $this->_drytpl-> render_String($template['body'], $this->_out); // Template within template ?

				$this-> view("print.html");
				break;

			// Embedded view, no menus or anything else. Only the view itself will be shown ( default, or template based )
			case 'embed':

				break;

			// Mini-Toolbar view, with just a small top bar with links and a bottom "copyright" bar
			case 'clean':

				break;
			// Full view (depends if the user is logged in or not)
			default:
			// ????
// 			$this-> site_section = "issues". DS. $ist['key']. DS. $ist['templates']['view'];
// 			$this->_out['form'] = $form-> output();
// 			$this-> view("public.html");
		}

	}

}
