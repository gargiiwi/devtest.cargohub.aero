<?php
/**
 * The router which is used to load controllers for the Cargo ITS Application
 * -- Also perform Auto-Login when a special cookie exists in the fridge.
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Framework
 * @version 2021.02.17
 */
class App_Router
{
	/**
	 * Indicates if the request that reached the router was done through an ajax request.
	 * -- If yes, several things should not happen ( like autologin, redirects, etc. )
	 * @var bool
	 */
	public static $ajax;

	/**
	 * Checks if launched on a HTTPS address
	 * @return boolean
	 */ 
	public static function isSecure() {
		return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
	}

	/**
	 * The Global Launcher
	 */
	public static function launch()
	{
		// Early initialization of the language system, which defaults to English
		// If language is changed later in the code without reloading the page, run MVC_NLS::set_Language again to load the proper strings
		MVC_NLS::set_Language(MVC_Core::$ss-> language?: "English");
		MVC_NLS::load_Groups('common,evan');

		// The primary input ($_REQUEST), contains zone, module from the url rewrite
		$rq = new MVC_Request();

		// A controller is identified by two variables (zone, module), and it's name becomes zone_module
		// In the URI, those variables are in the format /zone/module
		if(!$rq->_zone && !$rq->_module) { // the root (nothing in the URL)
			$controller = "public_index";
			$_zone = "public";
		} else { // all others (from the RewriteRule)
			$controller = $rq->_zone."_".$rq->_module;
			$_zone = $rq->_zone;
		}

		// detect if this was an ajax request or an "inside request" which is usually an iframe or inside-the-application standard http get request
		if((!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || isset($rq-> ireq)) {
			self::$ajax = true;
			// No redirects or other checks are attempted on ajax/inside requests ! It's assumed that the user is already on a page when these calls happen
			// Sometimes the session might have expired during an ajax request, or something else has happened, the controller must reply accordingly

		} else {
			self::$ajax = false;
			// First, check if the domain configuration exists in the session, if not attempt to load it from database on current SERVER_NAME

			// Attempt to read information about the domain where the application is accessed from
			// -- It determines certain things like https redirect availability, visual aspects, record types and others.
			// -- The server uses a "wildcard" mode in which any subdomain address can be typed, but only those that are present in the database can actually be used.
			// -- If the critical "Index Route" is missing for any reason, re-read the domain settings
			if(empty(MVC_Core::$ss-> domain_CFG) || empty(MVC_Core::$ss-> domain_CFG['html_settings']) || empty(MVC_Core::$ss-> domain_CFG['html_settings']['index_route'])) {
				$m_org = new App_Model_Organization();
				$ord = $m_org-> domain_Read($_SERVER['SERVER_NAME'], array('domain', 'organization', 'orgtype'));

				if($ord) {
					// Certain values are not necessary for the "Domain Owner Organization" and are removed before storing into session (at least in THIS variable) - When logged in the user's organization is considered, even if on another organization's domain.
					unset($ord['organization']['orgtype']['controllers'], $ord['organization']['orgtype']['mainmenu'], $ord['organization']['orgtype']['disallowcreate']);
					unset($ord['organization']['orgtype']['default_features'], $ord['organization']['orgtype']['default_privileges'], $ord['organization']['orgtype']['default_access_ids']);
					// When domain exists, save the cached domain info in the session (where it can easily be accessed from everywhere)
					MVC_Core::$ss-> domain_CFG = $ord;
				} else
					// Domain not found in the whitelist
					// Auto-redirect to a the default URL (in main config)
					MVC_Core::shutdown(MVC_Core::$settings['core']['security']['invalid_domain_redirect']);
			}

			// If we are not on HTTPS and we have HTTPS redirect in the domain config, none of the rest matters, so calculate the redirect URI and then shut down
			if(!self::isSecure() && MVC_Core::$ss-> domain_CFG['https']) {
				return MVC_Core::shutdown(sprintf("https://%s%s", MVC_Core::$ss-> domain_CFG['domain'], $_SERVER['REQUEST_URI']));
			}

			// Second, check if address is correct and redirect in various modes in case it's not
			if($_zone == 'public') {
				// The public area is accessible to everyone, if the asked controller exists, if not, "fail" (redirects to a KB page)
				$valid_public_controllers = array(
					'public_index',			// Main index, contains the auto-redirect function (Reads domain config, redirects to defined "entry" url)
					'public_cms',			// CMS pages, used for presentation purposes - Also for building customer portals
					'public_access',		// E-mail based account access (login, register, account features)
					'public_issue',			// Record functions (original submit, web view, others) - Will eventually get replaced by all the record_* controllers
					'public_files',			// External document access (for accessing stored files)
					'public_webapi',		// WebAPI functions access
					'public_cronapi',		// Cron functions IWI
					'public_kb'				// Deprecated, is replaced by CMS - Check if this is still used anywhere and replace/remove
				);
				if(!in_array($controller, $valid_public_controllers))
					$fail = true;
			} else {
				// Always clear local_acls on next launch (they might be recreated downstream)
				unset(MVC_Core::$ss-> user['local_acls']);

				// "global" and "contact" zones are shared between private and public
				if(in_array($_zone, array('global', 'contact', 'customer', 'record')) && !isset(MVC_Core::$ss-> organization)) {
					$valid_global_controllers = array(
						'global_contact', 'contact_access', 'contact_account', // global_contact will eventually be moved into "contact" zone.
						// New naming
						'customer_access', 'customer_overview', // Not yet used
						'record_gate',		// New record access gateway controller
						'record_view'		// New record view with multiple tabs
					);

					// TODO: Fix this redundancy...
					if(!in_array($controller, $valid_global_controllers)) {
						// Not logged in ?
						// first, attempt to autologin the user if the special cake is in the fridge.
						// -- If that doesn't work, redirect to login page
						$m_User = new App_Model_User();
						if($m_User-> check_The_Cake()) {
							$m_Evan = new App_Model_Evan();
							$m_Evan-> add(MVC_NLS::get_Object("evan.user.autologon", true), "notify", array(
								'permanent'=> true,
								'timeout'=> time()+ 60
							));
						} else {
							$evan = new App_Model_Evan();
							$evan-> add("This area requires authentication. Please log in - You will be redirected on successful authentication.", 'stop');
							// no organization in the session, no cake, redirect to login page:
							if($_SERVER['REQUEST_URI']!='/access?action=login') {
								MVC_Core::$ss-> original_url = $_SERVER['REQUEST_URI'];
								MVC_Core::shutdown("/access?action=login");
							}
						}
					}
				} else {
					// Every other zone is "private" and depends on the organization type for logged in user
					if(isset(MVC_Core::$ss-> organization)) {
						$orgtype = MVC_Core::$ss-> organization['orgtype'];
						if(!in_array($controller, $orgtype['controllers']))
							// error, this controller is not available for this organization type, or the url is wrong
							$fail = true;
					} else {
						// Not logged in ?
						// first, attempt to autologin the user if the special cake is in the fridge.
						// -- If that doesn't work, redirect to login page
						$m_User = new App_Model_User();
						if($m_User-> check_The_Cake()) {
							$m_Evan = new App_Model_Evan();
							$m_Evan-> add(MVC_NLS::get_Object("evan.user.autologon", true), "notify", array(
								'permanent'=> true,
								'timeout'=> time()+ 60
							));
						} else {
							$evan = new App_Model_Evan();
							$evan-> add("This area requires authentication. Please log in - You will be redirected on successful authentication.", 'stop');
							// no organization in the session, no cake, redirect to login page:
							if($_SERVER['REQUEST_URI']!='/access?action=login') {
								MVC_Core::$ss-> original_url = $_SERVER['REQUEST_URI'];
								MVC_Core::shutdown("/access?action=login");
							}
						}
					}
				}
			}

			// TODO: Review how this is handled
			if(isset($fail)) {
				header("HTTP/1.0 403 Access denied");
				die;
				// No access to this controller from the currently logged in user, or when not being logged in
				return MVC_Core::shutdown('/kb?page=403', true);
			}
		}

		//===== Everything fine, no more redirects, ready to launch ! =====//
		MVC_Core::$debug-> dump("Liftoff! We have a liftoff ...".(self::$ajax? " in AJAX mode": ""), "App_Router");

		// run the controller by dispatching the GET.action variable to it's "Action Methods"
		$controller = MVC_Core::factory_Controller($controller);
		$controller->_dispatch($rq);
	}
}