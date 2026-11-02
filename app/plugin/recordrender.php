<?php
/**
 * Template system plugin that allows using the legacy _render.html of selecter record type to be inserted into another template
 * - (for example, to combine a custom organization text with the preset record data)
 *
 * @author Wavetrex Infinity (wavetrex@gmail.com)
 * @package CargoConnect
 * @subpackage Plugins
 * @version 2.9
 */
class App_Plugin_RecordRender
{
	/**
	 * Reference to the DryTpl object
	 *
	 * @var Drytemplate
	 */
	private $drytpl;

	/**
	 * Reference to the Record model object (used for the rendering)
	 * @var App_Model_Issue
	 */
	private $record;

	/**
	 * Variables used by $this-> record-> render
	 * @var array
	 */
	private $vars;

	/**
	 * Plugin constructor
	 *
	 * @param mixed $params
	 */
	function __construct($params)
	{
		if(is_array($params)) {
			$this-> drytpl = &$params[0];
			$this-> record = &$params[1];
			$this-> vars = $params[2];
		}
	}

	/**
	 * Renders the HTML output
	 *
	 * @param string $param_string
	 */
	public function render_Output($param_string = null)
	{
		$return_att = false; // This is not used anymore; Find where record-> render is used and fix parameter #7
		// Unfortunately, the record must be read again in non-serialized mode ... :( _render templates should be all updated to "serialized" mode ( and then only keep serialized mode )
		$this-> record-> cache_Reset(array("issue"));

		return $this-> record-> render($this-> vars['rec_id'], $this-> vars['parts'], $this-> vars['org_id'], null, $this-> vars['tpl_data'], null, $return_att, $this-> vars['rt_id']);
	}

}