<?php
/**
 * Base interface for CMS Plugins
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package Cargo ITS
 * @version 1.0.dev
 */
interface App_Plugin_CMS
{
	/**
	 * Renders the output for this plugin, which can then be inserted in any position in the page template
	 *
	 * @param string $param_string String parameter that might come from a template or other source
	 */
	public function render_Output($param_string = null);

	/**
	 * All AJAX Requests that might come from the JavaScript side of this plugin are directed here, and the plugin decides what to do and how to respond
	 */
	public function op_Hub(MVC_Request $in);
}