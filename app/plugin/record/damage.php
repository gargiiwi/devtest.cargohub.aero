<?php
/**
 * Record processing plugin for "Cargo Damage"
*
* @author Wavetrex Infinity <wavetrex@gmail.com>
* @package CargoConnect
* @subpackage Controllers
* @version 2019.06.23
*/
class App_Plugin_Record_Damage extends App_Model_Statistics implements App_Plugin_Record
{
	/**
	 * Auto-Calculations on update of record
	 * (Not yet implemented for this record type)
	 *
	 * @see App_Plugin_Record::process_Data()
	 */
	public function process_Data($rec_id, &$metadata, &$xfields)
	{
		// Change first 3 values of Investigation based on Risk (no, rc, req) - If it exists
		if(isset($xfields['class'])) {
			$inv_done_binary = array(
				'opt'=> false,
				'rc'=> false,
				'req'=> false,
				'ds'=> false,
				'st'=> false,
				'end'=> false,
			);
		
			if($xfields['class']['int_value'] == "1") {
				$inv_done_binary['opt'] = true;
			} elseif($xfields['class']['int_value'] == "2") {
				$inv_done_binary['rc'] = true;
			} elseif($xfields['class']['int_value'] == "3" || $xfields['class']['int_value'] == "4" || $xfields['class']['int_value'] == "5") {
				$inv_done_binary['req'] = true;
			}

			// UPDATE mode only
			if(!empty($rec_id)) {
				// Grab existing values for inv_done (The checkboxes aren't turned off, only on)
				if(!empty($xfields['inv_done']['value']))
					$inv_cb_user = json_decode($xfields['inv_done']['value'], true);
				else
					$inv_cb_user = array();

				// If dates are present, turn on values inside $inv_done_binary
				if(!empty($xfields['inv_date']['int_value']) || in_array('ds', $inv_cb_user)) // ds
					$inv_done_binary['ds'] = true;
				if(!empty($xfields['inv_start_date']['int_value']) || in_array('st', $inv_cb_user)) // st
					$inv_done_binary['st'] = true;
				if(!empty($xfields['inv_end_date']['int_value']) || in_array('end', $inv_cb_user)) // end
					$inv_done_binary['end'] = true;
			}

			$xfields['inv_done']['value'] = json_encode(array_keys(array_filter($inv_done_binary)));
		}
	}

// Customized functions for Cargo Damage record //

}