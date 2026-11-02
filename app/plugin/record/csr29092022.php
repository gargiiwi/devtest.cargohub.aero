<?php
/**
 * Record processing plugin for "Cargo Service Recovery"
*
* @author Wavetrex Infinity <wavetrex@gmail.com>
* @package CargoConnect
* @subpackage Controllers
* @version 2.9
*/
class App_Plugin_Record_Csr extends App_Model_Statistics implements App_Plugin_Record
{
	/**
	 * Auto-Calculations on update of record
	 * (Not yet implemented for this record type)
	 *
	 * @see App_Plugin_Record::process_Data()
	 */
	public function process_Data($rec_id, &$metadata, &$xfields)
	{
		//Praneeth changes start
		$xf_original_data= $metadata['_original']['xfields_raw'];
		if(!empty($rec_id)) {
			
				if(isset($xfields['trc_status'])){
					if($xfields['trc_status']['int_value']!=$xf_original_data['trc_status']['int_value']){
						if($xfields['trc_status']['int_value']==1){
							$xfields['trc_date']['int_value']=time();
						}
						// else{
							// $xfields['trc_date']['int_value']=0;
						// }
					}
				}
				if($xfields['trc_date']['int_value']!=$xf_original_data['trc_date']['int_value']){
					//echo "yes i went inside";
					// if($xfields['trc_status']['int_value']!=1){
						// $xfields['trc_date']['int_value']=0;
					// }
				}
				if(isset($xfields['trc_date'])||isset($xfields['procDate'])){
					if(($xfields['trc_date']['int_value']!=$xf_original_data['trc_date']['int_value']) || ($xfields['procDate']['int_value']!=$xf_original_data['procDate']['int_value'])){
						$seconds = $xfields['trc_date']['int_value']-$xfields['procDate']['int_value'];
						
						$dossLength=0;
						if($seconds < 86400 || $xfields['trc_date']['int_value'] == 0 || $xfields['procDate']['int_value'] == 0 ){
							$xfields['dossLength_Days']['int_value']=0;
							$dossLength = 0;
						}else{
							$days = $this->secondsToDays($seconds);
							$xfields['dossLength_Days']['int_value']=$days;
							$dossLength = $days;
						}
						
						if($dossLength <= 0 ){
							$xfields['slaLevel_achi']['value'] = "";
						}
						else if($dossLength >1 && $dossLength <= 2){
							$xfields['slaLevel_achi']['value'] = "SLA 2 Days";
						}else if($dossLength >2 && $dossLength <= 5){
							$xfields['slaLevel_achi']['value'] = "SLA 5 Days";
						}
						else if($dossLength >5 && $dossLength <= 10){
							$xfields['slaLevel_achi']['value'] = "SLA 10 Days";	
						}
						else if($dossLength >10 && $dossLength <= 20){
							$xfields['slaLevel_achi']['value'] = "SLA 20 Days";	
						}else {
							$xfields['slaLevel_achi']['value'] = "OverDue"; 
						}					
					}
				}
			}else{
				$xfields['procDate']['int_value']=time();
			}
		}

// Customized functions for Cargo Service Recovery (Tracing) record //

	function secondsToDays($seconds){
		return floor($seconds/86400);
	}
}