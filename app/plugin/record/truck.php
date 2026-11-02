<?php
/**
 * Record processing plugin for "NCTS"
*
* @author Wavetrex Infinity <wavetrex@gmail.com>
* @package CargoConnect
* @subpackage Controllers
* @version 2019.06.20
*/
class App_Plugin_Record_Truck extends App_Model_Statistics implements App_Plugin_Record
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
		// Sum up Items, Pieces and Total Weight from the AWB fields
		$intact_units = 0;
		$pack_weight = 0;
		$loose_pieces = 0;
		for($c=1; $c<=15; $c++) {
			if(!isset($xfields["awb{$c}"]['value']))
				continue; // awb is missing, nothing to do
			$intact_units++;
			if(!empty($xfields["awb{$c}_p"]['int_value']))
				$loose_pieces+= $xfields["awb{$c}_p"]['int_value'];
			if(!empty($xfields["awb{$c}_kg"]['int_value']))
				$pack_weight+= $xfields["awb{$c}_kg"]['int_value'];

		}
		if($intact_units > 0)
			$xfields['intact_units'] = array('int_value'=> $intact_units);
		if($pack_weight > 0)
			$xfields['pack_weight'] = array('int_value'=> $pack_weight);
		if($loose_pieces > 0)
			$xfields['loose_pieces'] = array('int_value'=> $loose_pieces);

		// CREATE mode only
		if(empty($rec_id)) {
			if(!empty($xfields['mrn_no'])) {
				$orgdata['status_key'] = "ndis";
				$orgdata['prio'] = "4med";
			} else {
				$orgdata['status_key'] = "exp";
				$orgdata['prio'] = "5low";
			}
		}
	}

// Customized functions for NCTS record //

}