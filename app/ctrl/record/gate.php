<?php
/**
 * Record Access Gateway Controller (for external access)
 * - Decides what operations are possible within the current session (based on domain configuration), and redirects to the proper action
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2018.06
 */
class App_Ctrl_Record_Gate extends App_Ctrl_Base
{
	public static $_module_name = "Record Access Gateway";

	// Model references - Helps dev by providing autocompletion
	/**
	 * Reference to the Contact Model
	 * @var App_Model_Contact
	 */
	private $con;

	/**
	 * The gate support integrating it's functionality into a preset CMS page. The CMS data model is used to access that page.
	 * @var App_Model_CMS
	 */
	protected $cms;

	/**
	 * Recordgate plugin - Used to provide information for the gate
	 * @var App_Plugin_Recordgate
	 */
	private $gate_plugin;

	/**
	 * Constructor for Record Controller
	 */
	function __construct()
	{
		parent::__construct();

		// Controller specific models
		$this-> record = $this-> factory_Model('Issue',  "record");
		$this-> con = $this-> factory_Model("Contact", "con");
	}

	//===== Interface Assembly Actions =====//

	/**
	 * Index action for the Gateway - Determines what operations are possible for the current session and redirects to other controllers or actions
	 * @param MVC_Request $rq
	 */
	public function action_Index(MVC_Request $rq)
	{
		// Small helper for debugging - Resets all saved gate configuration
		if($rq-> gate_reset)
			unset(MVC_Core::$cache-> gate_cfg);

		// If domain doesn't have this info configured, don't allow issues to be submitted
		if(!is_array(MVC_Core::$ss-> domain_CFG['public_settings']) && !isset(MVC_Core::$ss-> domain_CFG['public_settings']['gate'])) {
			echo "<h1>This domain is not configured for record operations.</h1><p>Please contact support@cargohub.nl with this information [ <b>Missing GATE Config for {$_SERVER['HTTP_HOST']}</b> ]</p>";
			return;
		}
		$gate_domain_cfg = MVC_Core::$ss-> domain_CFG['public_settings']['gate'];

		// Record type is present in URL?
		if(!empty($_GET['_urlx']) && $_GET['_urlx'][0] == ':') {
			$rt_key = substr($_GET['_urlx'], 1);

			if(!isset($gate_domain_cfg['rt_accept'][$rt_key])) {
				$this-> evan-> add("This domain does not accept the specified record type. You might have followed an outdated link.", 'error');
				return $this-> redirect("/");
			}
		} else {
			// If not, use the value in rt_default - If missing throw an error
			if(!isset($gate_domain_cfg['rt_default'])) {
				$this-> evan-> add("This domain doesn't have a pre-configured record type. Cannot proceed without one being specified.", 'error');
				return $this-> redirect("/");
			}

			$rt_key = $gate_domain_cfg['rt_default'];
		}

		// Prepare the config
		if(MVC_Core::$ss-> user) {
			$access_mode = $this-> user-> is_Unrestricted()? "admin": "org";
			$user = MVC_Core::$ss-> user;
		} elseif(MVC_Core::$ss-> contact_account) {
			$access_mode = "customer";
			$cac = MVC_Core::$ss-> contact_account;
		} else {
			// Visitor access, the system has no information currently. Gateway may ask for information before other operations.
			$access_mode = "visitor";
/*
			// Not logged in ? Redirect to the customer account login
			$this-> save_Current_Location("contact_login", $rq-> track, "Record gateway");

			// Customer account autologin (by E-mail/Password)
			if(isset($rq-> email) && isset($rq-> password))
				return $this-> redirect("/contact/access?action=login&email=".$rq-> email."&password=".$rq-> password."&autologin=1");
			else
				return $this-> redirect("/contact/access?action=login");
*/
		}

		$plugin_params = array_merge(array('rq'=> &$rq, 'drytpl'=> &$this->_drytpl), $gate_domain_cfg['rt_accept'][$rt_key]);

		$this-> gate_plugin = MVC_Core::factory_Plugin("recordgate", $rt_key, $plugin_params, $this);

		// Existing data check - Proceed to show the Gate UI or redirect automatically
		$redirect_url = $this-> gate_plugin-> check_Gate_Redirect(MVC_Core::$cache-> gate_cfg);
		if($redirect_url !== false)
			return $this-> redirect($redirect_url);

		// ... No redirect, initialize the gate
		$gate_cfg = $this-> gate_plugin-> get_Config();

		// Gateway shield configuration for Submit (prevents submit if certain conditions aren't met)
		switch($access_mode) {
			// Admin can submit all record types
			case 'admin':
			// Normal users can only submit record types they have access to (based on their ACLs)
			case 'org':
				$gate_cfg['contact_mode'] = 'user';
				$gate_cfg['contact'] = array(
					'id'=> $user['contact_id'],
					'name'=> $user['name'],
					'email'=> $user['email'],
					'user_id'=> $user['id']
				);
				$gate_cfg['rt_key'] = $rt_key;
				break;
			// Customers (logged in E-mail accounts) and Visitors can only submit record types specified in "rt_accept" parameter
			case 'customer':
				$gate_cfg['contact'] = $cac['contact'];
			case 'visitor':
				$gate_cfg['rt_key'] = $rt_key;

				// TODO: Group accept, default

				if(!empty($plugin_params['check_contact'])) {
					// A submitter contact is required before the submit form can be shown. It can be obtained in a few ways
					// - Customer is logged in - In this case their contact is automatically used
					// - Visitor (not logged in), but their last used contacts are present in the cookie [its_recent_contacts] - Visitor can select one or enter a new one
					// - Visitor without a cookie - The system must ask for contact information before proceeding to submit
					if($access_mode == 'visitor') {
						// If there's an Auto-fill e-mail, cookie contacts are ignored, and the parameter E-mail is used instead
						if(isset($gate_cfg['autofill']['email']))
							$gate_cfg['contact_mode'] = "create";
						else {
							$cookies = new MVC_Persist('cookie');
							$con_ids = $cookies-> its_recent_contacts;
							if(!empty($con_ids))
								$gate_cfg['contact_mode'] = "select";
							else
								$gate_cfg['contact_mode'] = "create";
						}
					} else {
						$gate_cfg['contact_mode'] = "cac";
					}
				}

				// When a check function (or more) is required, the shield page will use the record type plugin to generate the form elements and verify submitted shield data
				// The plugin will also decide if it's possible to move to the main submit form or not (it might decide to redirect to another operation, like "view")
//				if(!empty($plugin_params['check_data']))
				break;
		}

		// Saves the complete config in the cache, so the Gate_Op calls have it available (minus the unneeded stuff)
		MVC_Core::$cache-> gate_cfg = $gate_cfg;

		// TODO: Present the shield as a CMS plugin, so it can be integrated into a customized layout page
		// Section 1 - Speciality search or custom gate data input
		// Section 2 - Contact select, search or new entry
		// Section 3 - Outcome of operations - Redirect or Stop error display
		// Section 4 - (optional) Quick information about the record type that is about to be submitted, disclaimer (static included content) /view/issues/{$rt}/gate_info.html

		$title = $gate_cfg['rt_name'].' &mdash; Access Gate';
/*		$this-> module = array(
			// The "title" bar comes after "toolbar" and "mainmenu"
			'icon'=> "question2.png", 'icon_alt'=> "?",
			'title'=> $title // 'title_para'=> $title_para
		);*/

		// Javascript Configuration depends on which record type is loaded in the gate, and it's corresponding recordgate plugin
		if(!empty($gate_cfg['components']))
			$this-> CFG['components'] = $gate_cfg['components'];
		array_push($this-> CFG['modules'], "record/gate/_".$rt_key);
		$this-> CFG['jqwidgets'][] = array("scrollbar", "listbox", "dropdownlist", "input");
		if(!empty($gate_cfg['jqwidgets']))
			$this-> CFG['jqwidgets'][] = $gate_cfg['jqwidgets'];
		if(!empty($gate_cfg['xjs']))
			$this-> CFG['xjs'] = $gate_cfg['xjs'];

		// A few extra parameters for visual styling, which are sent to JS
		if(!empty($gate_domain_cfg['gate_width']))
			$gate_cfg['gate_width'] = (int)$gate_domain_cfg['gate_width'];

		$this-> CFG['recordgate_'.$rt_key] = $gate_cfg;

		// Ready for output...
		$this-> prepare_Header($title);

		// Any template-specific vars mentioned in the config ? Pass them on
		if(!empty($gate_domain_cfg['tpl_vars']))
			foreach($gate_domain_cfg['tpl_vars'] as $var=> $value)
				$this-> {$var} = $value;

		// Integrations with a CMS page
		if(!empty($gate_domain_cfg['cms_page'])) {
			$this-> cms = $this-> factory_Model("CMS");
			$page = $this-> cms-> check($gate_domain_cfg['cms_page']);

			if($page && $page['active']) {
				// The page is ready and active, load it and use it; Start loading plugins specified in the page config
				if(is_array($page['plugins']))
					foreach($page['plugins'] as $plugin_cfg) {
						if(!isset($plugin_cfg['params']))
							$plugin_cfg['params'] = array();

						$plugin = MVC_Core::factory_Plugin("cms", $plugin_cfg['id'], array(&$this->_drytpl, $plugin_cfg['params']));
						$plugin_init = $plugin-> get_Config();

						// Initialize variables depending on the plugins' returned config
						if(isset($plugin_init['javascript'])) {
							// JavaScript config
							if(!in_array($plugin_cfg['id'], $this-> CFG['modules']))
								$this-> CFG['modules'][] = "cms/_{$plugin_cfg['id']}";

							// Uses extra jqWidgets modules ?
							if(isset($plugin_init['javascript']['jqwidgets']))
								$this-> CFG['jqwidgets'][] = array_values($plugin_init['javascript']['jqwidgets']);

							// TODO: Support multiple use of the same javascript plugin (by the use of key, and transmititng the key to to loader)
							if(isset($plugin_init['javascript']['config']))
								$this-> CFG["cms_{$plugin_cfg['id']}"] = $plugin_init['javascript']['config'];

							// XJS?
							if(isset($plugin_init['javascript']['xjs']))
								foreach($plugin_init['javascript']['xjs'] as $xjs_key=> $xjs_value)
									$this-> CFG['xjs'][$xjs_key] = $xjs_value;
						}

						// Register the plugin to the template system so it's output method can be called on demand
						$this->_drytpl-> register_Plugin($plugin_cfg['key'], $plugin);
						unset($plugin, $plugin_cfg, $plugin_init);
					}

				// Also register the Gate itself as the plugin
				$this->_drytpl-> register_Plugin("recordgate", $this-> gate_plugin);
				$this->_drytpl-> register_Path(ROOT. DS. 'cmsp');
			} else
				unset($page);
		}

		if(isset($cac)) {
			// Customer account is logged in ?
			$this->_out['header']['rightmenu'][] = array(
				'link'=> "/contact/account#issues",
				'text'=> "My submissions"
			);
			$this->_out['header']['rightmenu'][] = array(
				'spacer'=> true
			);
		}
		$this->_out['header']['rightmenu'][] = array(
			'link'=> "/contact/access?action=login&return=record/gate:".$rt_key,
			'text'=> isset($cac)? $cac['contact']['email'] : "Log in"
		);

		// Fields structure for the contacts editor - Should be present in all recordgate plugins
		MVC_NLS::load_Groups("contact");
		$fields_structure = MVC_NLS::get_Object("contact", true);
		foreach($fields_structure as &$group) {
			foreach($group['fields'] as $field=> $string) {
				if(strpos($string, "~"))
					$group['fields'][$field] = explode("~", $string);
			}
		}
		$this-> CFG['recordgate_'.$rt_key]['vcard_fields_structure'] = $fields_structure;

		// TODO: Support embedding this into a CMS page, to allow customizing per domain
		if(isset($page))
			$this-> site_section = $page['path']. ".html";
		else
			$this-> site_section = "issues". DS. $rt_key. DS. "_gate.html";
		switch($access_mode) {
			case 'admin':
			case 'org':
				$this-> view("private.html");
				break;
			case 'customer':
			case 'visitor':
				if(!empty(MVC_Core::$ss-> domain_CFG['html_settings']['embedded']))
					$this-> view("public_embedded.html");
				else
					$this-> view("public.html");
				break;
		}
	}

	/**
	 * Customized Gate operations, depend on record type - Operations are passed on to the plugin
	 */
	public function action_Gate_Op(MVC_Request $rq)
	{
		$gate_cfg = &MVC_Core::$cache-> gate_cfg;

		$this-> gate_plugin = MVC_Core::factory_Plugin("recordgate", $gate_cfg['rt_key'], null, $this);
		$result = $this-> gate_plugin-> gate_Operation($rq-> op, @json_decode($rq-> data, true), $gate_cfg);

		if(!is_array($result))
			return header("HTTP/1.0 500 The operation {$rq-> op} failed to return a result. Please contact support@cargohub.nl for assistance");

		$this-> send_JSON($result);
	}
}