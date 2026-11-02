<?php
/**
 * Record processing plugin for "Cargo Damage (FWD)"
*
* @author Wavetrex Infinity <wavetrex@gmail.com>
* @package CargoConnect
* @subpackage Controllers
* @version 2.10
*/
class App_Plugin_Record_Cdmg extends App_Model_Statistics implements App_Plugin_Record
{
	/**
	 * Auto-Calculations on update of record
	 * (Not yet implemented for this record type)
	 *
	 * @see App_Plugin_Record::process_Data()
	 */
	public function process_Data($rec_id, &$metadata, &$xfields)
	{
// 		dd($xfields);
	}

// Customized functions for Cargo Damage record //

}