<?php
/**
 * Frontend CMS module, allows displaying pages with information
 * -- Most content is static HTML however it allows integrating CMS plugins into the templates with the purpose of displaying various dynamic data
 * -- Everything in this module should be publicly accesible (not logged in, no password required), but should also work when logged in.
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoConnect
 * @subpackage Controllers
 * @version 2.9
 */
class App_Ctrl_Public_CMS extends App_Ctrl_Base
{

	/**
	 * CMS model
	 * @var App_Model_CMS
	 */
	protected $cms;

	/**
	 * CMS controller Constructor
	 */
	function __construct()
	{ 
		parent::__construct();

		$this-> cms = $this-> factory_Model("CMS");
	}

	/**
	 * All CMS pages are normally redirected through here, but html files are not directly accessible (they are in /app )
	 * -- Example URL: ( /cms?path=aa/bb/cc ), maps to /app/cms/aa/bb/dd.html
	 *    -- TODO: Rewrite rule so it works like: /cms/aa/bb/cc which then gets converted into /cms?path=aa/bb/cc
	 * -- Domain settings should be able to override the root path for CMS pages (for example, to /app/cms/domain.name/ to have customized cms trees for each domain)
	 * -- Each valid /app/cms/ path has a database record which makes it valid, but also indicates which CMS plugins will be used in that page (from those available), and also their settings
	 *
	 * @param MVC_Request $in
	 */
	public function action_Index(MVC_Request $in)
	{
		// First, locate which CMS page is supposed to be displayed
		if(!isset($in-> path))
			$path = "index";
		else
			$path = $in-> path;

		$page = $this-> cms-> check($path);

		if(!$page || !$page['active']) {
			// 404 page is also a standard cms page, but one that cannot be removed (part of the system)
			$path = "404";
			$page = $this-> cms-> check($path);
		}

		// Start loading plugins specified in the page config
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

		// Prepare for output
		$this-> prepare_Header($page['title'], array("userbar", "evan"));

		$this->_out['header']['rightmenu'] = array(
			array(
				'link'=> "/access?action=entrance",
				'text'=> isset(MVC_Core::$ss-> user)? "Staff section" : "System User Login"
			)
		);

		// Register the CMS Pages include path with drytpl
		$this->_drytpl-> register_Path(ROOT. DS. 'cmsp');
		
		$this-> site_section = $page['path']. ".html";
                
                if($page['path']=='/test_html'){
                    $this->liability_Calculator=file_get_contents('https://www.cargohub.nl/frombox/index26.php');
                    $this-> view("public_test_html.html");
                }
                else if($page['path']=='/worldwide'){
                    $this->liability_Calculator=file_get_contents('https://www.cargohub.nl/frombox/index26.php');
                    $this-> view("cms_worldwide_html.html");
                }
                else{
                    if(!empty(MVC_Core::$ss-> domain_CFG['html_settings']['embedded']))
			$this-> view("public_embedded.html");
		else
			$this-> view("public.html");
                }
                
		
	}

	/**
	 * General action to be redirected to the loaded plugins
	 *
	 * @param MVC_Request $in
	 */
	public function action_Plugin(MVC_Request $in)
	{
		// Attempt to initialize this plugin
		$plugin = MVC_Core::factory_Plugin("cms", $in-> plugin);

		if(is_object($plugin)) {
			// Yes, call it's op hub
			$plugin-> op_Hub($in);
		}
	}

}