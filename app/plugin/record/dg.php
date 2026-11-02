<?php
/**
 * Record processing plugin for "Dangerous Goods"
*
* @author Wavetrex Infinity <wavetrex@gmail.com>
* @package CargoConnect
* @subpackage Plugins.Record
* @version 2018.06
*/
class App_Plugin_Record_DG extends App_Model_Statistics implements App_Plugin_Record
{
	/**
	 * Auto-Calculations on create or update of record
	 *
	 * @see App_Plugin_Record::process_Data()
	 */
	public function process_Data($rec_id, &$metadata, &$xfields)
	{
		// UPDATE mode only
		if(empty($rec_id)) {
			// Create
		} else {
			// Update
		}
	}

// Customized functions for Dangerous Goods record //

}