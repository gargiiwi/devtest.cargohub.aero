<?php
/**
 * Nothing to do here !
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package Cargo ITS
 * @version 1.0.dev
 */
class App_Plugin_CMS_EulaAccept implements App_Plugin_CMS
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
	 * Constructor Initializes PHP side to access the data of the Airline search box
	 *
	 * @param mixed $params
	 */
	function __construct($params)
	{
		if(is_array($params))
			$this-> drytpl = &$params[0];
	}

	/**
	 * Returns various configuration parameters so the CMS module knows how to initialize JavaScript and other aspects
	 *
	 * @return array
	 */
	public function get_Config()
	{
		// Plugin name
		$config = array('name'=> "Accept User License Agreement");

		$config['javascript'] = array(
			'jqwidgets'=> array("buttons", "checkbox"),
			'config'=> array("active"=> isset(MVC_Core::$ss-> eula_check))
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
		if(isset(MVC_Core::$ss-> eula_check))
			return $this-> drytpl-> render_File("_plugins". DS. "eulaaccept.html");
		else
			return ""; // Shows nothing when the check isn't active.
	}

	/**
	 * All AJAX request addressed to this plugin are redirected here, and various actions are performed depending on the parameters
	 */
	public function op_Hub(MVC_Request $in)
	{
	}
}