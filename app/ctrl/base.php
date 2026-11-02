<?php
/**
 * The base controller
 * -- Contains helper functions which are necessary for this application, but are not part of the MVC_Framework itself
 *
 * @author Wavetrex Infinity (wavetrex@gmail.com)
 * @package CargoConnect
 * @subpackage Framework
 * @version 2.7
 */
abstract class App_Ctrl_Base extends MVC_Ctrl
{
	protected static $contact_support = "System configuration is missing critical data. Please contact support@cargohub.nl with this error code: ";

	/**
	 * Controller Configuration Storage
	 * -- mostly used by the JavaScript components, this variable is the method of sending data from the controller to the JS frontend
	 * -- also available in the template as %CFG, when in "page" mode (not AJAX request)
	 * @var array
	 */
	public $CFG = array();

	//--- The common models have their own variables (which are actually references into $this-> m . The purpose is to help development as it provides autocomplete for all methods inside these models )

	/**
	 * The User Model
	 * @var App_Model_User
	 */
	public $user;

	/**
	 * The Organization Model
	 * @var App_Model_Organization
	 */
	public $org;

	/**
	 * Events and Notifications System (EVaN)
	 * @var App_Model_Evan
	 */
	public $evan;

	/**
	 * Record (Issue) Model - Not initialized by default
	 * @var App_Model_Issue
	 */
	public $record = null;

	/**
	 * An error has occured during execution of the controller. No output should be displayed
	 * @var bool
	 */
	public $error = false;

	/**
	 * Controller constructor, sets up some things like Language, View (Template System), JavaScript Config variable ($this-> CFG)
	 */
	public function __construct($user_module = false)
	{
		// Load the models which are used basically everywhere
		$this-> user = $this-> factory_Model('User');
		$this-> org = $this-> factory_Model("Organization", 'org');
		$this-> evan = $this-> factory_Model("Evan");

		if(App_Router::$ajax) {
	//=== AJAX mode: Check if domain_CFG is not present in session. This means that the session has expired while a page is loaded (and no actions done). In this case, send the "Session has expired" error to JavaScript ===//
			if(!isset(MVC_Core::$ss-> domain_CFG)) {
				$this-> error = true;
				header("HTTP/1.0 408 ".MVC_NLS::get_Object("evan.user.sessionexpired", true));
			}
			return;
		}

	//=== Page mode: HTML Settings and Template system initialization ===//

		// Combine the MVC_Core::$settings['html'] and MVC_Core::$ss-> domain_CFG['html_settings'] arrays, then save back in domain_CFG (as the domain's html_settings is an override, it might not contain full required settings)
		// -- ( domain can change theme, design or even the default language )
		$html_settings = array_merge(MVC_Core::$settings['html'], MVC_Core::$ss-> domain_CFG['html_settings']);
		$html_settings['domain'] = MVC_Core::$ss-> domain_CFG['domain']; // the domain name is used in some templates
		MVC_Core::$ss-> domain_CFG['html_settings'] = $html_settings;

		// If language not present in session, switch to the one specified in the $html_settings (when domain chooses language), and reload strings
		if(!isset(MVC_Core::$ss-> language) && isset($html_settings['language'])) {
			MVC_Core::$ss-> language = $html_settings['language'];
			MVC_NLS::set_Language(MVC_Core::$ss-> language);
		}

		// Checks the user session before doing anything else (It might trigger a forced logout if the session is missing/expired)
		if($this-> user-> check_Session())
			$this-> CFG['home'] = '/'.str_replace('_', '/', MVC_Core::$ss-> organization['orgtype']['controllers'][0]);
		else {
			if($user_module) {
				// A user-only module was requested. If no user is logged in at this point, move to the index
				$this-> evan-> add($this-> user-> last_error, 'error');
 				$this-> redirect($html_settings['index_route']);
			} else
				$this-> CFG['home'] = $html_settings['index_route']; // Redirect just once
		}

		//=== Basic JavaScript configuration, absolutely necessary parts (Current controller, Theme, Modules, Application module) ===//
		$this-> DONREY = MVC_Core::$settings['javascript'];
		$this-> CFG['controller'] = get_class($this);
		$this-> CFG['jqwidgets'] = array();
//		$this-> CFG['jqwidgets'][] = array("core");
//		$this-> CFG['jqwidgets'][] = array("response", "data", "buttons", "checkbox", "radiobutton", "menu", "window", "tooltip"); // There jqx modules are used nearly everwhere (even in frontend, when no user is logged in)
		$this-> CFG['jqwidgets'][] = array("-application"); // Slice 1 - A pack, for quicker loading
		$this-> CFG['theme'] = array(
			'css'=> MVC_Core::$ss-> domain_CFG['html_settings']['css_theme'],
			'jqx'=> MVC_Core::$ss-> domain_CFG['html_settings']['jqx_theme']
		);
		// first controller in the list is considered the (always available) home, for redirecting on some actions
		$this-> CFG['modules'] = array('application'); // Start with the always-present javascript module (everything else is added by the action)
		$this-> CFG['application'] = array(
			'liftoff'=> date("Y-m-d H:i:s"),
			'debug'=> MVC_Core::$settings['core']['debug'],
			'controller'=> get_class($this)
		);

		// Send the "background" information to the application module:
		$background = isset(MVC_Core::$ss-> domain_CFG['html_settings']['background'])? MVC_Core::$ss-> domain_CFG['html_settings']['background']: "/img/bgs/gray_patt4_tiled.png";

		if(isset(MVC_Core::$ss-> user['preferences']['graphics']['clouds'])) {
			if(MVC_Core::$ss-> user['preferences']['graphics']['clouds'])
				$this-> CFG['application']['background'] = $background;
		} else {
			$this-> CFG['application']['background'] = $background; // Always turn on when not logged in.
		}

		// Initialize the Template System with a certain theme ( either one present in the session, as an override, or the default theme specified in $html_settings )
		$this-> init_Template_System(MVC_Core::$ss-> tpl_theme?: $html_settings['tpl_theme']);

		// give the templates access to the session ( mostly useful when an user is logged in )
		// TODO: This isn't optimal, session should not have anything to do with the output !
		$this->_out['session'] = &MVC_Core::$ss;
		// give the template access to the "html" section of the configuration, which contains things that are useful for the display of the pages themselves
		$this->_out['html'] = &$html_settings;
	}

	// ===== Actions System ===== //

	/**
	 * Dispatches the action (which usually comes from a request parameter) to the Action methods which are inside the Controller
	 *
	 * @param MVC_Request $in The GET request (input variables, also passed to the "Action" as first parameter, so the MVC_Request object is reused there)
	 * -- The $in might contain an _urlx variable (extended part of the URL (before QS), which is usually empty and should only be used for some ajax requests)
	 */
	public function _dispatch(MVC_Request $in)
	{
		if($this-> error)
			MVC_Core::shutdown(); // Dispatch should not run normally if error mode is already on ( probably because initialization failed )

		// the controller output is managed, and display only happens only after it has completed successfully
		ob_start();
		if(is_string($in-> action)) {
			if(method_exists($this, 'action_'.$in-> action)) {
				$this-> CFG['action'] = $in-> action;

				// When in AJAX mode, attempt to capture crashes in the Action to send them to the frontend as an error message (instead of empty box)
				if(App_Router::$ajax)
					try {
						call_user_func(array($this, 'action_'.$in-> action), $in);
					} catch(Exception $e) {
						$error = true;
						return header("HTTP/1.0 500 ".$e-> getMessage());
					}
				else
				// Otherwise, just run the action. if it crashes there will be a crash screen or message
					call_user_func(array($this, 'action_'.$in-> action), $in);
			} else {
				$msg = sprintf(MVC_NLS::get_Object("evan.error.missingaction", true), $in-> action, get_class($this));
				if(App_Router::$ajax) {
// 					$this-> send_Fail_JSON($msg);
					$error = true;
					return header("HTTP/1.0 501 ".$msg);
				} else
					throw new Exception($msg);
			}
		} else
			// if an action hasn't been specified, just run the "index" (if it exists)
			if(method_exists($this, 'action_Index'))
				$this-> action_Index($in);

		// Show all the buffered output; In case of an Exception, we don't reach this point.
		if(!isset($error))
			ob_end_flush();

		// After running the controller, cleanup: Remove all loaded models, empty the output and drytemplate object then shutdown
		$this-> m = array();
		$this->_out = null;
		$this->_drytpl = null;
		// then shutdown
		MVC_Core::shutdown();
	}

	// ===== Output-related Methods ===== //

	/**
	 * Contains the Drytemplate object that will render the template + $this-> output into the final HTML
	 * @var Drytemplate
	 */
	protected $_drytpl;

	/**
	 * Initializes the Drytemplate system, either when in Page Mode or on demand in certain AJAX actions (that return HTML and not JSON).
	 *
	 * @param string $tpl_theme Which template theme to use ('default' is the common theme for system files) - This does not affect $extra_paths
	 * @param array|string $extra_paths More paths in which templates are searched
	 */
	public function init_Template_System($tpl_theme = 'default', $extra_paths = null)
	{
		// Configuration for the Drytemplate object is an object itself (legacy from DonReY 2012) - For now, it only uses one member, the "paths" array.
		$dtCFG = new stdClass();
		$dtCFG-> paths = array('templates'=> array());

		// add alternative location for template variance (takes priority to the default one)
		if($tpl_theme != "default")
			$dtCFG-> paths['templates'][] = ROOT. DS. 'app'. DS. 'view'. DS. 'theme_'. $tpl_theme;

		// The default TPL path (the template system searches in all paths)
		$dtCFG-> paths['templates'][] = ROOT. DS. 'app'. DS. 'view';

		// Extra template paths, when present, allows templates in non-standard locations ( outside the "view" folder )
		if(!empty($extra_paths)) {
			if(!is_array($extra_paths))
				$extra_paths = explode(';', $extra_paths);
			$dtCFG-> paths['templates'] = array_merge($dtCFG-> paths['templates'], $extra_paths);
		}

		$this->_drytpl = new Drytemplate($dtCFG);
	}

	/**
	 * Displays the view for this controller. (Page Mode, not used in AJAX actions)
	 * -- Also sets up all javascript configuration for the currently loaded module
	 *
	 * @param string $view_file
	 */
	public function view($view_file, $theme = null)
	{
		if($this-> error)
			return;

		// Make sure the "jqwidgets" and "modules" keys are pure arrays (because .length is used in cargo_its.js and they are treated as arrays, not as objects)
		if(isset($this-> CFG['jqwidgets']))
			$this-> CFG['jqwidgets'] = array_values($this-> CFG['jqwidgets']);
		if(isset($this-> CFG['modules']))
			$this-> CFG['modules'] = array_values($this-> CFG['modules']);

		// Output and JavaScript configuration valid only when a user is logged in
		$active_session = $this-> user-> read_Base();
		if(!empty($active_session)) {
			// user can select/override the theme specified by the html_settings (which also has a default in the config)
			$this-> CFG['theme']['jqx'] = MVC_Core::$ss-> user['preferences']['jqx_theme'];

			$this-> CFG['application']['active_session'] = $active_session;
			$this-> CFG['application']['active_session']['acls'] = array(
				'privileges'=> $this-> user-> read_ACL('privileges'),
				'rt_ids'=> $this-> user-> read_ACL('ist')
			);

			// Check if user is non-admin and has no "privileges" - In that case shoot a warning to indicate a possible misconfiguration
			if(!$this-> user-> is_Unrestricted() && empty($this-> CFG['application']['active_session']['acls']))
				$this-> evan-> add("Your account has absolutely no access privileges, there might be a misconfiguration in the Access Control Lists.", "warning");

			// ACLs (and especially privileges) are used in some templates to determine visibility
			$this-> prepare_Template_ACLs();
		}

		// Send the config variable to the template (which will then be loaded into JavaScript)
		$this->_out['CFG'] = $this-> CFG;
		$this->_out['_cache'] = &MVC_Core::$cache;

		// disable all browser caching
		header('Expires: Sat, 22 May 1979 00:00:00 GMT');									// Date in the past ;)
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");						// Always being modified
		header('Cache-Control: no-store, private, no-cache, must-revalidate');				// HTTP/1.1
		header('Cache-Control: pre-check=0, post-check=0, max-age=0, max-stale=0', false);	// HTTP/1.1
		header("Pragma: no-cache");

		// and do the primary template render
		echo $this->_drytpl-> render_File($view_file, $this->_out);
	}

	/**
	 * Sends all the data that was collected in $this->_out as a json stream
	 */
	public function view_JSON()
	{
		if($this-> error)
			return;

		$this-> send_JSON($this->_out);
	}

	// ===== Output Helper Functions ===== //

	/**
	 * Makes a list of privileges and access IDs that the current user has, in a format which can be accessed very easy from the template
	 */
	protected function prepare_Template_ACLs()
	{
		if(!isset($this->_out['acls'])) {
			$acls = array();

			if($this-> user-> is_Unrestricted())
				// User is unrestricted, full access anywhere, doesn't need lists
				$acls['unrestricted'] = true;
			else {
				$acls['privileges'] = $this-> user-> read_ACL('privileges', true); // Read privileges as indexes, not as values
				// TODO: Other Access IDs
			}

			$this->_out['acls'] = $acls;
		}
	}

	/**
	 * Checks if the logged in user (which is not admin) has the required privileges to see a menu item
	 *
	 * @param string $mi_a The definition of a menu item access (taken from Global config)
	 */
	private function check_Access_to_Menu_Item($mi_a)
	{
		if(isset(MVC_Core::$ss-> user['acls']['privileges']) && is_array(MVC_Core::$ss-> user['acls']['privileges'])) {
			$ix = array_intersect(explode(',', $mi_a), MVC_Core::$ss-> user['acls']['privileges']);
			return !empty($ix);
		}
	}

	/**
	 * The main menu is a combination of a menu model (organization specific) and limitations for a certain user privileges
	 * -- (some menu items require one or more privileges in order to be visible)
	 */
	protected function prepare_Main_Menu()
	{
		// Process the menu a bit (which is stored in the settings.yaml), before displaying it.
		$mainmenu = array();
		foreach(MVC_Core::$ss-> organization['orgtype']['mainmenu'] as $menuitem) {

			if(isset($menuitem['enable']) && $menuitem['enable']==false)
				continue; // skip an item if it's disabled

			// check to see if the logged in user has access to a certain menu item
			if(MVC_Core::$ss-> user['status'] == 'admin' || (is_bool($menuitem['access']) && $menuitem['access']==true) || $this-> check_Access_to_Menu_Item($menuitem['access'])) {

				// listing module shows the name and image of the current RT
// 				if($menuitem['route'] == "/issue/list") {
// 					$menuitem['name'] = MVC_Core::$cache-> current_rt['short_plural'];
// 					$menuitem['icon'] = 'ist/'. MVC_Core::$cache-> current_rt['key']. '.png';
// 				}

				// messages module shows the number of new messages directly in the menu
				if(strpos($menuitem['route'], "/user/commcenter")!==false && isset($menuitem['icon_open'])) {
					$menuitem['name'] = sprintf($menuitem['name'], MVC_Core::$ss-> user['count']['unread']);
					if(MVC_Core::$ss-> user['count']['unread'] > 0)
						$menuitem['icon'] = $menuitem['icon_open'];
					else
						$menuitem['icon'] = $menuitem['icon_closed'];
				}
				// external link
				if(substr($menuitem['route'], 0, 4) == 'http') {
					$menuitem['target'] = '_blank';
				}

				$mainmenu[] = $menuitem;
			}
		}

		return $mainmenu;
	}

	/**
	 * Prepare output data for the site header (organization logo, department selector, theme selector, main menu (module selector), notification system)
	 *
	 * @param string $zone The current location - Displayed on the userbar and in the html title
	 * @param array $features Enable or disable certain header area features (like logo, userbar, menu, evan) - Possibly more in the future
	 * -- "userbar": Show or hide the Userbar (black bar with certain information and links) - The Userbar should NOT be hidden normally, as it's important for navigation
	 * -- "mainmenu": Show or hide the Main Menu (icon bar) - Important for Staff section but unneeded for frontend
	 * -- "evan": The Events and Notifications system (displays error messages and other aspects)
	 */
	protected function prepare_Header($zone = null, $features = array("userbar", "mainmenu", "evan"))
	{
		$this->_out['header'] = array();
		if(!empty($zone))
			$this->_out['header']['zone'] = $zone;

		// User Menu Bar can be disabled, and also send a message to the javascript that it doesn't exist, so it doesn't attempt to inialize it (the UMB is a jqxMenu)
		if(in_array('userbar', $features))
			$this->_out['header']['umb'] = true;
		else
			$this-> CFG['application']['umb_disabled'] = true;

		// Main menu can only be shown for a logged in user (and in the staff section)
		if(in_array('mainmenu', $features) && isset(MVC_Core::$ss-> user))
			$this->_out['header']['mainmenu'] = $this-> prepare_Main_Menu();

		// The EVAN object has an iterator implemented, grab all messages and send them to the "application" module (EVAN display is controlled by JavaScript now)
		if(in_array('evan', $features)) {
			$this-> CFG['application']['evan'] = array();
			foreach($this-> evan as $msg)
				$this-> CFG['application']['evan'][] = $msg-> prepare_Display();
		}
	}

	/**
	 * Little helper to return error messages and other data for AJAX requests
	 * @param string $reason
	 * @param int $error_code
	 * @param mixed $extra_data
	 * @return bool false
	 */
	public function send_Fail_JSON($reason, $error_code = 500, $extra_data = null)
	{
		$data = array(
			'success'=> false,
			'reason'=> $reason
		);
		if(is_array($extra_data))
			$data = array_merge($data, $extra_data);
		header("HTTP/1.0 {$error_code} {$reason}");
		$this-> send_JSON($data);
		return false;
	}

	// ===== Location Management ===== //

	/**
	 * Redirects the browser to another page ( usually used after a POST or if the user doesn't have access to a page )
	 *
	 * @param string $where Redirect location
	 */
	public function redirect($where)
	{
		// remove all loaded models (might trigger some destructors in the process), clear the template system and other common variables, then shutdown
		$this-> evan = null;
		$this-> org = null;
		$this-> user = null;
		$this-> m = array();

		$this->_drytpl = null;
		$this-> CFG = array();
		$this->_out = null;

		MVC_Core::shutdown($where);
	}

	/**
	 * Saves a location (url) into session, under a certain key. It can be retrieved later to return to that location.
	 *
	 * @param string $key
	 * @param string $url The location string
	 * @param int $time The time when location was saved. If null, it defaults to current time
	 * @param string $label A simple text label saved in the location entry (Might be used to display where the location points to)
	 * @param mixed $data Data saved with the location, that might be needed later when returning
	 */
	public function save_Location($key, $url, $time = null, $data = null, $label = null)
	{
		if(empty(MVC_Core::$ss-> locations))
			MVC_Core::$ss-> locations = array();

		// Save or overwrites location under they specified key
		$location = array(
			"url"=> $url,
			"time"=> empty($time)? time(): $time
		);
		// Optional: Ability to give this location a label, to be used in some module that displays all saved locations (soft of a navigation index)
		if(!is_null($label))
			$location['label'] = $label;
		// Optional: Additional data that will be saved with the location, in the shape of a string (It might be JSON)
		if(!is_null($data))
			$location['data'] = $data;

		MVC_Core::$ss-> locations[$key] = $location;
	}

	/**
	 * Saves current location (url) into session, under a certain key. It can be retrieved later to return to that location.
	 *
	 * @param string $key
	 * @param string $label A simple text label saved in the location entry (Might be used to display where the location points to)
	 * @param mixed $data Data saved with the location, that might be needed later when returning
	 * @param bool $referrer Saves the referrer location instead of server location - Useful when this method is called from an AJAX request.
	 * @param bool $qs_remove Removes the QueryString from the URL ( anything after ?, including )
	 */
	public function save_Current_Location($key, $data = null, $label = null, $referrer = false, $qs_remove = false)
	{
		if(empty(MVC_Core::$ss-> locations))
			MVC_Core::$ss-> locations = array();

		$url = $referrer? $_SERVER['HTTP_REFERER']: $_SERVER['REQUEST_URI'];
		if($qs_remove)
			$url = preg_replace('/\?.*/', '', $url);

		$this-> save_Location($key, $url, $_SERVER['REQUEST_TIME'], $data, $label, $referrer);
	}

	/**
	 * Returns the data associated with a location.
	 *
	 * @param string $key
	 * @param bool $remove If true, the location is removed from memory as soon at it is read
	 * @return array|bool Location data or FALSE if not found
	 */
	public function get_Location($key, $remove = false)
	{
		if(empty($key))
			return false;

		if(!isset(MVC_Core::$ss-> locations))
			return false;

		if(empty(MVC_Core::$ss-> locations[$key]))
			return false;

		$location = MVC_Core::$ss-> locations[$key];

		if($remove)
			unset(MVC_Core::$ss-> locations[$key]);

		return $location;
	}

	/**
	 * Automatically redirects to one of the saved locations, and deletes the location from memory.
	 *
	 * @param string $key
	 * @param bool Returns FALSE if location not found
	 */
	public function redirect_To_Location($key, $remove = false)
	{
		$location = $this-> get_Location($key, $remove);
		if($location === false)
			return false;

		$this-> redirect($location['url']);
	}

	// ===== Extra Common Methods ===== //

	/**
	 * Helper for general grid requests which involve server side filtering
	 * - Analyses parameters from the GET request ($rq)
	 * - Sets up Server-side Filters ($ss_filters)
	 * - Also provides a $filter_callback to be used with the general type of conditions that come from the grid, transformed into query "where"
	 *
	 * @param MVC_Request $rq
	 */
	protected static function helper_Grid_Filters(MVC_Request $rq)
	{
		if($rq-> filters)
			// Client mode filter preparation
			$ss_filters = json_decode($rq-> filters, true);
		else {
			// Server mode filter grouping ( To be removed once ALL grids have been switched to client mode filter structure )
			$ss_filters = array();
			// group the bloody filters nicely, and reject extra ones (as the grid doesn't seem to "delete" filter parameters when sending the request, it just reduces the count)
			if($rq-> filterscount > 0) {
				for($f_id = 0; $f_id < $rq-> filterscount; $f_id++) {
					$field = $rq-> {"filterdatafield". $f_id};

					if(!isset($ss_filters[$field]))
						$ss_filters[$field] = array();

					$f = array(
						'c'=> $rq-> {"filtercondition". $f_id},
						'v'=> $rq-> {"filtervalue". $f_id},
					);
					// Some fixes for boolean being treated differently in db and in the grid
					if($f['v'] === 'true')
						$f['v'] = 1;
					elseif($f['v'] === 'false')
						$f['v'] = "0"; // string "0" is a valid value !!

					$ss_filters[$field][] = $f;
				}
				// Detect multiple-checkbox columns (when same column has at least 3 values with condition "EQUAL"), and convert into the condition "IN_SET", value array(values)
				// - This is done to avoid multiple WHERE-AND on the same column when fixed-values are present in the grid ( using IN(...) is faster )
				// - Note: This doesn't support the negative NOT_IN_SET which is sent by JS code (but it doesn't matter since it will disappear anyway ...)
				foreach($ss_filters as $fkey=> $filter_set) {
					if(count($filter_set)>2) {
						$values = array(); $still_valid = true;
						foreach($filter_set as $filter_item) {
							if($filter_item['c']!='EQUAL') {
								$still_valid = false;
								break;
							}
							$values[] = trim($filter_item['v']);
						}
						if($still_valid) {
							$ss_filters[$fkey] = array(
								array(
									'c'=> "IN_SET",
									'v'=> $values
								)
							);
						}
						unset($filter_item);
					}
				}
			}
		}

		// Load all priorities and statuses for records to reverse transform them from the "textual" form to the "key" form
		$stat_prio = MVC_NLS::get_Object("issue.data", true);

		// Filter condition callback - Returns the "Where string"
		$filter_callback = function(QueryFactory &$q, $filter_set, $dbfield, $type = null) use ($ss_filters, $stat_prio) {
			// Server Side filters analyse input from the grid
			// When filter type was specified, apply it differently
			switch($type) {
				// Priority and status should probably not be here...
				case 'priority':
					foreach($filter_set as &$filter_item)
						if(is_array($filter_item['v'])) {
							foreach($filter_item['v'] as &$val) {
								if($val != "(unavailable)")
									$val = array_search($val, $stat_prio['priorities']);
							}
							unset($val);
							$filter_item['v'] = array_filter($filter_item['v']);
						} else
							if($filter_item['v'] == "(unavailable)") {
								$filter_item['v'] = null;
								$filter_item['c'] = "NULL";
							} else
								$filter_item['v'] = array_search($filter_item['v'], $stat_prio['priorities']);
							goto standard_filter;

				case 'status':
					foreach($filter_set as &$filter_item)
						if(is_array($filter_item['v'])) {
							foreach($filter_item['v'] as &$val) {
								if($val != "(unavailable)")
									$val = array_search($val, $stat_prio['statuses']);
							}
							unset($val);
							$filter_item['v'] = array_filter($filter_item['v']);
						} else {
							if($filter_item['v'] == "(unavailable)") {
								$filter_item['v'] = null;
								$filter_item['c'] = "NULL";
							} else
								$filter_item['v'] = array_search($filter_item['v'], $stat_prio['statuses']);
						}
						goto standard_filter;

				case 'ip2long':
					foreach($filter_set as &$filter_item)
						$filter_item['v'] = sprintf('%u', ip2long($filter_item['v']));
						goto standard_filter;

				case 'unixtime': // Search between two unixtime numbers (large integer)
					$q-> where("{$dbfield} BETWEEN ? AND ?", strtotime(substr($filter_set[0]['v'], 0, 34)), strtotime(substr($filter_set[1]['v'], 0, 34)));
					break;

				case 'mysqldt': // This field is based on MySQL Datetime ( Y-m-d H:i:s ), and not numeric value
					$q-> where("{$dbfield} BETWEEN ? AND ?", date("Y-m-d H:i:s", strtotime(substr($filter_set[0]['v'], 0, 34))), date("Y-m-d H:i:s", strtotime(substr($filter_set[1]['v'], 0, 34))));
					break;

				case 'notnull':
				case 'notempty':
					if($filter_set[0]['v'] == 'true')
						$q-> where("{$dbfield} IS NOT NULL AND {$dbfield} != ''");
					else
						$q-> where("{$dbfield} IS NULL OR {$dbfield} = ''");
					break;
				// Special similarity filter (uses a stored SQL function) - VERY SLOW, only use as last resort
				case 'levenshtein':
					foreach($filter_set as &$filter_item)
						$filter_item['c'] = "LEVENSHTEIN";
					unset($filter_item);
				default:
					standard_filter:
					$where_string = "";
					foreach($filter_set as $idx=> &$filter_item) {

						if(is_string($filter_item['v']))
							$filter_item['v'] = trim($filter_item['v']);
						if(!$filter_item['v']==="0" && empty($filter_item['v']) && !($filter_item['c'] == 'NULL' || $filter_item['c'] == 'NOT_NULL'))
							continue; // Nothing to do here, no value
						switch($filter_item['c']) {
							case "LEVENSHTEIN":
								$where_string.= ($idx?" OR ":""). $q-> quote_Into("levenshtein(?, $dbfield) < 4", $filter_item['v']); break;
							case "CONTAINS":
							case "CONTAINS_CASE_SENSITIVE":
								$where_string.= ($idx?" OR ":""). $dbfield.' '.$q-> quote_Into("LIKE ?", "%{$filter_item['v']}%"); break;
							case "DOES_NOT_CONTAIN":
							case "DOES_NOT_CONTAIN_CASE_SENSITIVE":
								$where_string.= ($idx?" AND ":""). $dbfield.' '.$q-> quote_Into("NOT LIKE ?", "%{$filter_item['v']}%"); break;
							case "EQUAL":
							case "EQUAL_CASE_SENSITIVE":
								$where_string.= ($idx?" OR ":""). $dbfield.' '.$q-> quote_Into("= ?", $filter_item['v']); break;
							case "NOT_EQUAL":
								$where_string.= ($idx?" AND ":""). $dbfield.' '.$q-> quote_Into("<> ?", $filter_item['v']); break;
							case "IN_SET": // This is not directly sent by the grid, instead, multiple values (from checkbox filter) are converted into the "IN_SET" / "NOT_IN_SET" conditions
								$where_string.= ($idx?" AND ":""). $dbfield.' '.$q-> quote_Into("IN(?)", $filter_item['v']); break;
							case "NOT_IN_SET":
								$where_string.= ($idx?" AND ":""). $q-> quote_Into("{$dbfield} NOT IN(?) OR {$dbfield} IS NULL", $filter_item['v']); break;
							case "GREATER_THAN":
								$where_string.= ($idx?" AND ":""). $dbfield.' '.$q-> quote_Into("> ?", $filter_item['v']); break;
							case "LESS_THAN":
								$where_string.= ($idx?" AND ":""). $dbfield.' '.$q-> quote_Into("< ?", $filter_item['v']); break;
							case "GREATER_THAN_OR_EQUAL":
								$where_string.= ($idx?" AND ":""). $dbfield.' '.$q-> quote_Into(">= ?", $filter_item['v']); break;
							case "LESS_THAN_OR_EQUAL":
								$where_string.= ($idx?" AND ":""). $dbfield.' '.$q-> quote_Into("<= ?", $filter_item['v']); break;
							case "STARTS_WITH":
							case "STARTS_WITH_CASE_SENSITIVE":
								$where_string.= ($idx?" OR ":""). $dbfield.' '.$q-> quote_Into("LIKE ?", "{$filter_item['v']}%"); break;
							case "ENDS_WITH":
							case "ENDS_WITH_CASE_SENSITIVE":
								$where_string.= ($idx?" OR ":""). $dbfield.' '.$q-> quote_Into("LIKE ?", "%{$filter_item['v']}"); break;
							case "NULL":
							case "EMPTY":
								$where_string.= ($idx?" AND ":""). "({$dbfield} IS NULL OR {$dbfield} = '')"; break;
							case "NOT_NULL":
							case "NOT_EMPTY":
								$where_string.= ($idx?" OR ":""). "({$dbfield} IS NOT NULL AND {$dbfield} != '')"; break;
							default:
								MVC_Core::$debug-> dump($filter_item['c'], "Unsupported filter condition sent from jqxGrid", null, "warning");
						}
					}
					if(!empty($where_string))
						$q-> where($where_string);
			}
		};

		return array(&$ss_filters, &$filter_callback);
	}

	/**
	 * Organization Information about the Superuser perspective (Not viewing as their own)
	 * - Necessary for calculating record type overrides but other purposes as well
	 * - Without a perspective this variable stays null
	 * @var array
	 */
	public $perspective_org = null;

	/**
	 * Returns or initializes the ID of the issue type which is used in Submit/List/etc. (MVC_Core::$cache-> current_rt)
	 * - When not logged in, an initial issue type is chosen from the domain public_settings (either by Key or ID)
	 *   (When not set, an exception is thrown as the software cannot function without an active record type)
	 * - When logged in as Contact Account, the last record type that was submitted is initialized, unless chosen otherwise (from the ones available in the Domain public_settings)
	 * - When logged in as System User, the initial record type is accessed from preferences
	 * - When logged in as superusers, the organization perspective (from the session) is considered for overrides
	 * This method considers any available record type overrides
	 *
	 * @param bool $reset_session Ignores what was saved and regenerate the session variable
	 * @return The Record-Type ID or Key
	 */
	public function rt_Return($reset_session = false)
	{
		// Active Record Type information in cache, return directly
 		if($reset_session)
 			unset(MVC_Core::$cache-> current_rt);
		elseif(isset(MVC_Core::$cache-> current_rt))
			return MVC_Core::$cache-> current_rt['id'];

		// ... nope, let's see which record type should be loaded
		if(MVC_Core::$ss-> user) {
			// User mode, the user's preferences, organization and other factors determine the initial issue type
			$user = MVC_Core::$ss-> user;
			if(isset($user['preferences']['rt_id']))
				$rt_id = (int)$user['preferences']['rt_id'];
			else {
				// When user did not yet set any preferences, default to the first record type available to him
				if($this-> user-> is_Unrestricted())
					// ... however admins do not have a list - Default to first
					$rt_id = 1;
				elseif(is_array($user['acls']['ist']) && count($user['acls']['ist'])>0)
					$rt_id = (int)reset($user['acls']['ist']);
				else
					throw new Exception(self::$contact_support."[ RT Empty List ]");
			}
		} elseif(MVC_Core::$ss-> contact_account) {
			// Contact account mode, type depends on the account record and domain settings (A contact account can only select from the types available on the domain he's currently on)
			$cac = MVC_Core::$ss-> contact_account;
			$public_settings = MVC_Core::$ss-> domain_CFG['public_settings'];

			// Public settings are needed for contact accounts - Not for users however, for which their own access rights determine things (and not the domain)
			if(!$public_settings)
				throw new Exception(self::$contact_support."[ PbSet Null ]");

			// FIXME: Contact accounts are terribly broken
// 			if(!isset($cac['cac_xdata']['rt_id'])) {
// 				if(!isset($public_settings['ist']['init']))
// 					throw new Exception(self::$contact_support."[ PbSet No Init ]");
// 				else
// 					$rt_id = (int)$public_settings['ist']['init'];
// 			} else
// 				$rt_id = (int)$cac['cac_xdata']['rt_id'];

			$rt_id = 1;
		} else {
			// Not logged in at all, only the domain settings determine the issue type
			$public_settings = MVC_Core::$ss-> domain_CFG['public_settings'];

			if(!$public_settings)
				throw new Exception(self::$contact_support."[ PbSet Null ]");

			if(isset($public_settings['ist']['auto']))
				$rt_id = (int)$public_settings['ist']['auto'];
			elseif(isset($public_settings['ist']['list']))
				$rt_id = (int)$public_settings['ist']['list'][0];
			else
				throw new Exception(self::$contact_support."[ PbSet No Auto ]");
		}

		if(empty($rt_id))
			throw new Exception(self::$contact_support."[ ART Fail ]");

		// When a record type data is not loaded, make sure it is loaded by "switching" to it
		return $this-> rt_Switch($rt_id);
	}

	/**
	 * Changes the active issue type in the session to a new ist_id
	 * -- This method considers overrides and admin perspective
	 * The function DOESN'T UPDATE the user's settings/preferences. It only changes the cached data (which is erased at logout)
	 *
	 * @param int|string $new_rt_id_or_key Switch session to this new base type (numeric ID or "key", in which case the issue type is first searched in the database)
	 * @return int
	 */
	public function rt_Switch($new_rt_id_or_key)
	{
		$this-> record = $this-> factory_Model("Issue", "record");

		if(is_numeric($new_rt_id_or_key)) {
			// Precise record type ID
			$rt_id = (int)$new_rt_id_or_key;
		} else {
			// Switch record type based on the text-key - Find base version
			$rt_id = $this-> record-> ist_Locate($new_rt_id_or_key);
			if(!$rt_id)
				throw new Exception("Invalid record type key in parameters. Could not locate record type by it's key");
		}

		// Checks if it's actually possible to switch to this issue type ( depending on logged in user or domain settings )
		if(MVC_Core::$ss-> user) {
			// User mode, the user's preferences, organization and other factors determine the initial issue type
			$user = MVC_Core::$ss-> user;

			if(!$this-> user-> is_Unrestricted()) {
				// TODO: When non-unrestricted, check if user has access to the specified issue type
				if(!is_array($user['acls']['ist']) || !in_array($rt_id, $user['acls']['ist'])) {
					MVC_Core::$debug-> dump(sprintf("Unable to switch to record type %d because it's not accessible from your account", $rt_id), __METHOD__, null, 'warning');
					return false;
				}
			}
		} else {
			// Contact account or Public, availability of record types depends on "domain settings"
			$public_settings = MVC_Core::$ss-> domain_CFG['public_settings'];
			if(!$public_settings)
				throw new Exception(self::$contact_support."[ PbSet Null ]");

			if($public_settings['ist']['select']) { // Multiple types are permitted for this domain
				if(!in_array($rt_id, $public_settings['ist']['list'])) {
					// Not in valid types, dump an error in the console then silently deny switching
					MVC_Core::$debug-> dump(sprintf("Unable to switch to record type %d because it's not allowed by the domain settings", $rt_id), __METHOD__, null, 'warning');
					return false;
				}
			} else {
				if($rt_id != $public_settings['ist']['auto']) {
					MVC_Core::$debug-> dump(sprintf("Unable to switch to record type %d because it's not allowed by the domain settings", $rt_id), __METHOD__, null, 'warning');
					return false;
				}
			}
		}

		// Check overrides (And in case the switch is asked for by an admin, it should contain target organization information)
		$rt_id_or = $this-> rt_Scan_Override($rt_id, $this-> perspective_org);

		// Update the cached record type information (Used by a lot of areas of the application)
		// Normally this is read only at login, but if the user changes the active record type (or for some reason it is asked to be refreshed), this information needs to be updated as well
		if(!isset(MVC_Core::$cache-> current_rt) || MVC_Core::$cache-> current_rt['id'] != $rt_id_or)
			MVC_Core::$cache-> current_rt = $this-> record-> ist_Read($rt_id_or, false, false, true);

		if($rt_id_or != $rt_id)
			MVC_Core::$cache-> current_rt['base_id'] = $rt_id;

		unset(MVC_Core::$ss-> cache['last_view']); // Insufficient - Views should be persistent overall (including after logout/login)
		return MVC_Core::$cache-> current_rt['id'];
	}

	/**
	 * Scans all levels of issue type overrides depending on login status
	 * - When not logged in or contact account, the domain is checked, the organization owning the domain and the type of organization owning the domain
	 * - When logged in, the domain is considered, the user's organization, it's type, the department and the user himself (in his active department)
	 * - Override order is, from lowest to highest:
	 * -- Non-User: Organization type owner of domain < Organization owner of domain < Domain
	 * -- User: Organization type of user < Organization of user < Active Department of user < User in their active department
	 *    (If no overrides are found the function returns null, which means the base type is used)
	 *
	 * @param int $base_rt_id
	 * @param int $org_data If not null, scan overrides on organization type and organization level, ignoring the active user
	 * @param int $dep_id Further refine override, when $dep_id is present, check if it has overrides (ignored if $org_data is null)
	 * @return int The override type which applies
	 */
	public function rt_Scan_Override($base_rt_id, $org_data = null, $dep_data = null)
	{
		if(!is_null($org_data)) {
			// Mode A: When $org_data, $dep_data might be present ( Usually when using Superuser perspective )

			// Check Level 3 - Organization type
			if(isset($org_data['orgtype']['ist_or']) && isset($org_data['orgtype']['ist_or'][$base_rt_id]))
				$or_id = $org_data['orgtype']['ist_or'][$base_rt_id];
			// Check Level 2 - Organization
			if(isset($org_data['ist_or']) && isset($org_data['ist_or'][$base_rt_id]))
				$or_id = $org_data['ist_or'][$base_rt_id];
			// Check Level 1 - Department
			if(!is_null($dep_data) && isset($dep_data['ist_or']) && isset($dep_data['ist_or'][$base_rt_id]))
				$or_id = $dep_data['ist_or'][$base_rt_id];
		} elseif(MVC_Core::$ss-> user) {
			// Mode B: Normal user or Superuser without a perspective

			// Check Level 3 - Organization type for the user's organization
			if(isset(MVC_Core::$ss-> organization['orgtype']['ist_or']) && isset(MVC_Core::$ss-> organization['orgtype']['ist_or'][$base_rt_id]))
				$or_id = MVC_Core::$ss-> organization['orgtype']['ist_or'][$base_rt_id];
			// Check Level 2 - Organization of the user itself
			if(isset(MVC_Core::$ss-> organization['ist_or']) && isset(MVC_Core::$ss-> organization['ist_or'][$base_rt_id]))
				$or_id = MVC_Core::$ss-> organization['ist_or'][$base_rt_id];
			// Check Level 1 - The user's department or the user's personal override in the department
			if(isset(MVC_Core::$ss-> department['ist_or']) && isset(MVC_Core::$ss-> department['ist_or'][$base_rt_id]))
				$or_id = MVC_Core::$ss-> department['ist_or'][$base_rt_id];
		} else {
			// Mode C: Not logged in, public override mode (Domain settings)

			$dcfg = MVC_Core::$ss-> domain_CFG;
			// Check Level 3 - Organization type for the organization owning this domain
			if(isset($dcfg['organization']['orgtype']['ist_or']) && isset($dcfg['organization']['orgtype']['ist_or'][$base_rt_id]))
				$or_id = $dcfg['organization']['orgtype']['ist_or'][$base_rt_id];
			// Check Level 2 - Domain's owner organization
			if(isset($dcfg['organization']['ist_or']) && isset($dcfg['organization']['ist_or'][$base_rt_id]))
				$or_id = $dcfg['organization']['ist_or'][$base_rt_id];
			// Check Level 1 - Domain settings themselves
			if(isset($dcfg['ist_or']) && isset($dcfg['ist_or'][$base_rt_id]))
				$or_id = $dcfg['ist_or'][$base_rt_id];
		}

		if(isset($or_id))
			return (int)$or_id;
		else
			return (int)$base_rt_id;
	}

}