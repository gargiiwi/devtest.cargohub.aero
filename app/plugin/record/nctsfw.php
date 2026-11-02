<?php
/**
 * Record processing plugin for "New Computerised Transit System (Fwd)"
*
* @author Wavetrex Infinity <wavetrex@gmail.com>
* @package CargoConnect
* @subpackage Controllers
* @version 2.10
*/
class App_Plugin_Record_NCTSFw extends App_Model_Statistics implements App_Plugin_Record
{
	/**
	 * Auto-Calculations on update of record
	 * (Not yet implemented for this record type)
	 *
	 * @see App_Plugin_Record::process_Data()
	 */
	public function process_Data($rec_id, &$metadata, &$xfields)
	{
		//Sujatha changes start
		$xf_original_data= $metadata['_original']['xfields_raw'];
		if(!empty($rec_id)) {
			if(isset($xfields['mrn_date']['int_value'])){
				$seconds = time() - $xfields['mrn_date']['int_value'];
				$xfields['open_days']['int_value']=0;
				$days = $this->secondsToDays($seconds);	
				$xfields['open_days']['int_value']=$days;		
			}
		}
	}
	
	function secondsToDays($seconds){
		return floor($seconds/86400);
	}


// Customized functions for Transit Irregularity record //

}