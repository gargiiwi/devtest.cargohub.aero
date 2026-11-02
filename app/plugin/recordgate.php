<?php
/**
 * Base interface for Recordgate plugins
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Plugins
 * @version 2018.07
 */
interface App_Plugin_Recordgate extends App_Plugin
{

	/**
	 * Performs a gate operation (request or data store), via Ajax
	 *
	 * @param string $op Name of the operation - Comes from JavaScript
	 * @param array $data Data coming with the request
	 * @param array $gate_cfg Stored configuration (in $cache)
	 */
	public function gate_Operation($op, $data, &$gate_cfg);

	/**
	 * Check if gate is actually needed or there is sufficient saved configuration for automatic redirect
	 * @param array $stored_gate_cfg
	 * @return string Url/Uri where the gate controller will redirect
	 */
	public function check_Gate_Redirect($stored_gate_cfg);
}