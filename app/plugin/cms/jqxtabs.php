<?php
/**
 * Nothing to do here !
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package Cargo ITS
 * @version 1.0.dev
 */
class App_Plugin_CMS_jqxTabs implements App_Plugin_CMS
{
	/**
	 * Reference to the DryTpl object used by the CMS module, also used for generation output in the render_Output() method
	 *
	 * @var Drytemplate
	 */
	private $drytpl;

	/**
	 * Key under this plugin instance is loaded into the CMS
	 * -- (not used yet)
	 *
	 * @var string
	 */
	private $key;

	/**
	 * The parametes sent from the database
	 * @var array
	 */
	private $params;

	/**
	 * Constructor Initializes PHP side to access the data of the Airline search box
	 *
	 * @param mixed $params
	 */
	function __construct($params)
	{
		if(is_array($params)) {
			$this-> drytpl = &$params[0];
			$this-> params = $params[1];
		}
	}

	/**
	 * Returns various configuration parameters so the CMS module knows how to initialize JavaScript and other aspects
	 *
	 * @return array
	 */
	public function get_Config()
	{
		// Plugin name
		$config = array('name'=> "jqWidgets Tabs");

		$config['javascript'] = array(
			'jqwidgets'=> array("tabs"),
			'config'=> $this-> params
		);

		return $config;
	}

	/**
	 * Renders the HTML output
	 *
	 * @param string $param_string
	 */
	public function render_Output($param_string = null)
	{
		return ""; // Doesn't have it's own content. Instead, this plugin transforms an existing DOM object into jqWidgets tabs
	}

	/**
	 * All AJAX request addressed to this plugin are redirected here, and various actions are performed depending on the parameters
	 */
	public function op_Hub(MVC_Request $in)
	{
	}
}