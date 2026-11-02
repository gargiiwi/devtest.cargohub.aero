<?php
/**
 * Record processing plugin for "General Incident"
*
* @author Wavetrex Infinity <wavetrex@gmail.com>
* @package CargoConnect
* @subpackage Controllers
* @version 2.9
*/
class App_Plugin_Record_GenInc extends App_Model_Statistics implements App_Plugin_Record
{
	public function __construct()
	{
		if(!isset(self::$cache_ttl['records_per_group']))
			self::$cache_ttl['records_per_group'] = 3600; // Update per hour (bla!)
	}

	private static $risk_matrix = array(
		// Columns are "Severity" (Impact), Rows are Probability => 1 = Very Low; 2 = Low; 3 = Medium; 4 = High; 5 = Very high; 6 = Extreme
		"1"=> array(-1=> 1, 1=> 1, 2=> 2, 3=> 2, 4=> 2),
		"2"=> array(-1=> 1, 1=> 2, 2=> 2, 3=> 3, 4=> 3),
		"3"=> array(-1=> 2, 1=> 2, 2=> 3, 3=> 3, 4=> 3),
		"4"=> array(-1=> 2, 1=> 3, 2=> 3, 3=> 4, 4=> 4),
		"5"=> array(-1=> 2, 1=> 3, 2=> 3, 3=> 4, 4=> 5)
	);

	private static $area_trans = array(
		'wh'=> "Warehouse",
		'ls'=> "Landside",
		'r'=> "Airside",
		'off'=> "Office",
		'te'=> "Terminal",
		'o'=> "(Other)"
	);

	private static $veh_invo_trans = array(
		1=> "Car/Bus/Truck",
		2=> "Forklift",
		3=> "Loader",
		4=> "Tractor",
		5=> "Pallet mover"
	);

	/**
	 * Auto-Calculations on update of record
	 *
	 * @see App_Plugin_Record::process_Data()
	 */
	public function process_Data($rec_id, &$metadata, &$xfields)
	{
		if(!empty($xfields['class']['int_value']) && !empty($xfields['probability']['int_value'])) {
			// We have Classification/Probability, use the $risk_matrix to get the $risk field

			$xfields['risk']['int_value'] = self::$risk_matrix[$xfields['probability']['int_value']][$xfields['class']['int_value']];
		}

		// {#if:title:{title}}{#ifnot:title:{station} {area} {place} ({place_id}){#if:veh_invo: ({veh_invo}{#if:veh_id: {veh_id}}{#if:veh_plate: {veh_plate}})}:{#ifnot:acc_person: {damage}}{#if:acc_person: {acc_person}: {injury}}}

		if(empty($xfields['title']['value']) && !empty($xfields['area']['value']) && !empty(self::$area_trans[$xfields['area']['value']])) {
			$auto_title = "";

			// Station Code
			if(!empty($xfields['station']['value'])) {
				$auto_title.=$xfields['station']['value'].' ';
			}

			$auto_title.= self::$area_trans[$xfields['area']['value']];
			$auto_title.= ' '.$xfields['place']['value'];

			// Area Code
			if(!empty($xfields['place_id']['value'])) {
				$auto_title.=' ('.$xfields['place_id']['value'].')';
			}

			// Vehicle
			if(!empty($xfields['veh_invo']['int_value'])) {
				$auto_title.= ' ['.self::$veh_invo_trans[$xfields['veh_invo']['int_value']];
				if(!empty($xfields['veh_id']['value']))
					$auto_title.= ' '.$xfields['veh_id']['value'];
				if(!empty($xfields['veh_plate']['value']))
					$auto_title.= ' '.$xfields['veh_plate']['value'];
				$auto_title.=']';
			}

			// Damage/Injury
			if(!empty($xfields['acc_person']['value'])) {
				$auto_title.= ' '.$xfields['acc_person']['value'];
				if(!empty($xfields['injury']['value']))
					$auto_title.= ': '.$xfields['injury']['value'];
			} else
				$auto_title.= ' '.$xfields['damage']['value'];
			$xfields['title']['value'] = $auto_title;
		}
	}

	/**
	 * Setup chart configuration
	 *
	 * @param int $rt_id
	 * @param array $params
	 * @return boolean
	 */
	protected function ccfg_records_per_group($rt_id, $params)
	{
		if(empty($this-> user_groups))
			$this-> user_groups = self::user_Available_Groups($rt_id);

		$this-> chart_config['name'] = "Incidents per Category";
		return $this-> ccfg_Setup_Period($params['period'], $this-> chart_config['name']);
	}

}