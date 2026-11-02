<?php
/**
 * Record processing plugin for "Support Ticket"
*
* @author Wavetrex Infinity <wavetrex@gmail.com>
* @package CargoConnect
* @subpackage Controllers
* @version 2019.01.02
*/
class App_Plugin_Record_Support extends App_Model_Statistics implements App_Plugin_Record
{
	/**
	 * Auto-Calculations on update of record
	 * (Not yet implemented for this record type)
	 *
	 * @see App_Plugin_Record::process_Data()
	 */
	public function process_Data($rec_id, &$metadata, &$xfields)
	{

	}


	/**
	 * Uses the record data to generate a "keyword" used to select which automatic responder message will be used
	 * @param array $rec
	 * @param string $org_type_id Optionally, the type of organization might influence the output of the keyword generation
	 * @return string
	 */
	public function arem_Keyword(&$rec, $org_type_id = null)
	{
		return 'default';
	}

}