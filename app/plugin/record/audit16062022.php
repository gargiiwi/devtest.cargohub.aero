<?php
/**
 * Record processing plugin for "Audit"
*
* @author Wavetrex Infinity <wavetrex@gmail.com>
* @package CargoConnect
* @subpackage Controllers
* @version 2.9
*/
class App_Plugin_Record_Audit extends App_Model_Statistics implements App_Plugin_Record
{
	public function __construct()
	{
		if(!isset(self::$cache_ttl['records_per_group']))
			self::$cache_ttl['records_per_group'] = 3600; // Update per hour (bla!)
	}

	/**
	 * Auto-Calculations on update of record
	 * (Not yet implemented for this record type)
	 *
	 * @see App_Plugin_Record::process_Data()
	 */
	public function process_Data($rec_id, &$metadata, &$xfields)
	{

	}

// Customized functions for Audit record //

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

		$this-> chart_config['name'] = "Audits per Category";
		return $this-> ccfg_Setup_Period($params['period'], $this-> chart_config['name']);
	}

}