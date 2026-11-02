<?php
/**
 * Record processing plugin for "Training Record"
*
* @author Wavetrex Infinity <wavetrex@gmail.com>
* @package CargoConnect
* @subpackage Controllers
* @version 2.9.3
*/
class App_Plugin_Record_Train extends App_Model_Statistics implements App_Plugin_Record
{
	protected $rec_id;

	protected $tr_nxt;

	protected $tr_nxt_name;

	/**
	 * Auto-Calculations on update of record
	 *
	 * @see App_Plugin_Record::process_Data()
	 */
	public function process_Data($rec_id, &$metadata, &$xfields)
	{
		$this-> rec_id = $rec_id;

		// Calculate earliest expire date by looking at all tr_*_to.int_value on the trns which are on
		$this-> tr_nxt = null;
		if(!empty($xfields['trns'])) {
			$trns_list = json_decode($xfields['trns']['value']);
			foreach($trns_list as $keyfrag) {
				if(!empty($xfields['tr_'.$keyfrag.'_to']['int_value'])) {
					$tr_to = $xfields['tr_'.$keyfrag.'_to']['int_value'];
					if(is_null($this-> tr_nxt) || $tr_to < $this-> tr_nxt) {
						$this-> tr_nxt = $tr_to;
						$this-> tr_nxt_name = $keyfrag;
					}
				}
			}
		}
		if(is_null($this-> tr_nxt)) {
			$xfields['tr_nxt'] = array();
			$xfields['tr_nxt_name'] = array();
		} else {
			$xfields['tr_nxt'] = array(
				'int_value'=> $this-> tr_nxt
			);
			$xfields['tr_nxt_name'] = array(
				'value'=> $this-> tr_nxt_name
			);
		}
	}

	public function process_Org_Update($m_rec, $org_id)
	{
		// If tr_nxt is bigger than current date, set record automatically as "expired" for the specified organization (if it exists)
		if(!empty($this-> tr_nxt) && $this-> tr_nxt < time()) {
			$m_rec-> org_UpdateData($this-> rec_id, $org_id, array(
				'status_key'=> "expi"
			), true);
		} else {
			// Switch to "Processing" if nothing has expired
			$m_rec-> org_UpdateData($this-> rec_id, $org_id, array(
				'status_key'=> "inpr"
			), true);
		}
	}

// Customized functions for Training Record record //

}