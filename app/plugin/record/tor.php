<?php
/**
 * Record processing plugin for "Truck Order"
*
* @author Wavetrex Infinity <wavetrex@gmail.com>
* @package CargoConnect
* @subpackage Plugins.Record
* @version 2019.05.02
*/
class App_Plugin_Record_Tor extends App_Model_Statistics implements App_Plugin_Record
{
	/**
	 * Auto-Calculations for record submission
	 *
	 * @see App_Plugin_Record::process_Data()
	 * @param $orgdata The plugin can set organization specific data (for create or update)
	 * @param $org_id Org ID - In some cases external data must be obtained abou the org (like specific values which might be included in the calculation)
	 */
	public function process_Data($rec_id, &$metadata, &$xfields, &$orgdata = null, $org_id = null)
	{
		// CREATE mode only
	}

// Customized functions for Truck Order record //

}