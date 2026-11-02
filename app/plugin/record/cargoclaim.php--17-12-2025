<?php
/**
 * Record processing plugin for "Air Cargo Claims"
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Plugins
 * @version 2019.12.15
 */
class App_Plugin_Record_Cargoclaim extends App_Model_Statistics implements App_Plugin_Record
{
	/**
	 * "Money" data module used by this plugin
	 * @var App_Model_Data_Money
	 */
	protected $m_money;

	public function __construct()
	{
		$this-> m_money = new App_Model_Data_Money();
	}
                                                        
	private static $irreg_code2sev = array(
		'acc'=> 1,
		'bbu'=> 1,
		'cco'=> 1,
		'cus'=> 1,
		'dam'=> 1,
		'del'=> 1, // This should be either 1 or 3, based on a delay length field which doesn't exist yet
		'dev'=> 3,
		'dgr'=> 5,
		'doc'=> 1,
		'hdl'=> 3,
		'lcl'=> 3,
		'msc'=> 1,
		'msd'=> 3,
		'msg'=> 1,
		'msr'=> 3,
		'ofl'=> 3,
		'oth'=> 1,
		'ovc'=> 3,
		'pro'=> 1,
		'rfs'=> 1,
		'sec'=> 3,
		'ssp'=> 3,
		'trf'=> 1,
		'trp'=> 1,
		'whs'=> 1,
		'res'=> 1
	);

	/**
	 * Processes a CargoClaim type record before being created or updated
	 *
	 * @see App_Plugin_Record::process_Data()
	 * @param $orgdata The plugin can set organization specific data (for create or update)
	 * @param $org_id Org ID - In some cases external data must be obtained abou the org (like specific values which might be included in the calculation)
	 */
	public function process_Data($rec_id, &$metadata, &$xfields, &$orgdata = null, $org_id = null)
	{
		//Praneeth changes start
		$xf_original_data= $metadata['_original']['xfields_raw'];
		
		//UPDATE mode only
		//Update prelimi claim and form claim based on claim type (pstage) when pstage value changed
		if(!empty($rec_id)) {	
			if(isset($xfields['claim_amt'])) {				
				//Update data based on pstage when metadata array contains _original data otherwise .... 
				if(isset($xf_original_data)){
					foreach($xfields as $key=> $xf_store) {
                        if($key=='pstage'){
                            if($xf_store != $xf_original_data[$key]) {
                                if($xf_store['value']=='prel'){
									if(empty($xfields['claim_amt']['value']) || $xfields['claim_amt']['value'] < 0) {
										$xfields['prelimi_claim']['int_value']=time();
									}
                                }else if($xf_store['value']=='fpr'){
									if(!empty($xfields['claim_amt']['value']) && $xfields['claim_amt']['value'] > 0) {
										$xfields['form_clm']['int_value']=time();
									}
                                }
                            }else{
								if($xfields['claim_amt']['value']!=$xf_original_data['claim_amt']['value']){
									if(!empty($xfields['claim_amt']['value']) && $xfields['claim_amt']['value'] > 0) {
									   // $xfields['pstage']['value'] = 'fpr';
										$xfields['form_clm']['int_value']=time();
									}
									if(empty($xfields['claim_amt']['value']) || $xfields['claim_amt']['value'] < 0) {
										$xfields['prelimi_claim']['int_value']=time();
										$xfields['form_clm']['int_value']='';
										//$xfields['pstage']['value'] = 'prel';
									}
								}
                            }
                        }
					}
				}else{
					//Update data form clm based on claim_amt when metadata does not contains _original data
					if(array_key_exists('claim_amt',$xfields)){
						if($xfields['claim_amt']['value']!=""){
							$xfields['form_clm']['int_value']=time();
						}
					}
				}
			}
		}
		//Praneeth changes end
// === General processing (Create, Update) ===

	// Submit form or Dashboard panel
		if(isset($xfields['claim_amt'])) {
			if(empty($xfields['pstage']))
				$xfields['pstage'] = array();
			// Claim amount is > 0 ? Make "Formal" - Otherwise Preliminary
			if(!empty($xfields['claim_amt']['value']) && $xfields['claim_amt']['value'] > 0) {
				$xfields['pstage']['value'] = 'fpr';
				
				// Praneeth changes start
				$xfields['claim_notice']['value']=null;
				if(empty($rec_id)) {
				    $xfields['form_clm']['int_value']=$metadata['ctime'];
				}
				// Praneeth changes end
				
				// Currency conversion to USD to the field "claim_usd", on current date (as no other useful date is available)
				$xfields['claim_usd'] = array('value' => null, 'int_value' => 840);
				$xerate_usd = $this-> m_money-> currency_Convert($xfields['claim_amt'], $xfields['claim_usd']); // Convert using live data (present moment)

				// Exchange rate for Claim -> USD is also stored (xerate_usd), which might be "1" if Claim currency is also USD
				if($xerate_usd > 0)
					$xfields['xerate_usd'] = array('value'=> round($xerate_usd, 4));
				else
					$xfields['claim_usd']['value'] = null; // When conversion failed (for example, no rate was found - empty the target value to indicate that there's no rate for this pair)
			} else {
				$xfields['pstage']['value'] = 'prel';
				
				// Praneeth changes start
				if(empty($rec_id)) {
				   $xfields['prelimi_claim']['int_value']=$metadata['ctime'];
				}		    
				// Praneeth changes end
				
				$xfields['claim_usd']['value'] = null; // Also remove the claim in USD when claim amount is cleared, and the conversion rate
				$xfields['xerate_usd'] = null;
				$xfields['xerate_paidusd'] = null;
			}
		}

// === CREATE mode only ===
		if(empty($rec_id)) {
			// Sets the priority to Medium when claim is Formal (Default = Low)
			if(isset($xfields['pstage']) && $xfields['pstage']['value'] == 'fpr') {
				$orgdata['priority_key'] = "4med";
			}

			// Claim receive date has an initial value set to the record creation moment (but can be edited in the Airline panel)
			// TODO: Check if ctime actually present in $metadata on new claim submit
			$xfields['crdate'] = array(
				'int_value'=> $metadata['ctime']
			);
		}

// === UPDATE mode only ===
		if(!empty($rec_id)) {

			// Fix for Formal Claim in USD (claim_usd) missing the currency value, which is not calculated here, but presented as a read-only input - Always)
			// - Does not depend if calculation is On or Off, this just makes sure USD is set as the currency of this field
			if(isset($xfields['claim_usd'])) {
				// Should trigger on Financial or Dashboard panels (both Read-only for this field)
				$xfields['claim_usd']['int_value'] = 840;
			}
            
		// Financial panel

			// If claim_usd or claim_amt are missing, retrieve them from $metadata['_original'] so they can be used in calculation
			if(!isset($xfields['claim_amt']) && isset($metadata['_original']['xfields_raw']['claim_amt']))
				$xfields['claim_amt'] = $metadata['_original']['xfields_raw']['claim_amt'];
			if(!isset($xfields['claim_usd']) && isset($metadata['_original']['xfields_raw']['claim_usd']))
				$xfields['claim_usd'] = $metadata['_original']['xfields_raw']['claim_usd'];

			// If Auto-Calculation is on (1), a lot of financial fields get calculated here instead of entered manually
			// - Also any values currently stored are overwritten if calculation is successful
			if(!empty($xfields['money_auto']['int_value'])) {

				// If a custom date is used for liability calculation ? If not, "today" is used
				if(!empty($xfields['xchg_date']['int_value'])) {
					$xchg_date = date("Y-m-d", $xfields['xchg_date']['int_value']);
					// Check if this is "today", then also set '0000-00-00' (which means, live currency data)
					if($xchg_date == date("Y-m-d"))
						$xchg_date = '0000-00-00';
				} else
					$xchg_date = '0000-00-00';

				// Formal Claim value is also calculated in Local if a local currency is specified
				if(!empty($xfields['claim_local']['int_value']) && isset($xfields['claim_usd'])) {
					$xerate_local = $this-> m_money-> currency_Convert($xfields['claim_usd'], $xfields['claim_local'], $xchg_date);
					// Exchange rate for Claim -> Local is also stored (xerate_local)
					if($xerate_local > 0)
						$xfields['xerate_local'] = array('value'=> round($xerate_local, 4));
				} else {
					$xfields['claim_local'] = null;
					$xfields['xerate_local'] = null;
					$xfields['xerate_paidlocal'] = null;
				}

				// All "maxliab" fields are affected only if a weight is entered
				if(isset($xfields['calc_weight']) && !empty($xfields['calc_weight']['value'])) {

					// Convention affects calculation paremeters - With no convention the amounts are all 0
					if(empty($xfields['convention'])) {
						$xfields['maxliab_usd']['value'] = null;
						$xfields['maxliab_local']['value'] = null;
						$xfields['maxliab_amt']['value'] = null;
						$xfields['xerate_xdrusd'] = null;
					} else {
						$weight = (float)$xfields['calc_weight']['value'];
						$xfields['maxliab_usd'] = array('value' => null, 'int_value' => 840);

						switch($xfields['convention']['int_value']) {
							case 1: // IATA - 19 XDR per KG

							case 4: // MC99-19
								$xdr_w19 = array(
									'int_value'=> 960,
									'value'=> $weight * 19
								);

								$xerate_xdrusd = $this-> m_money-> currency_Convert($xdr_w19, $xfields['maxliab_usd'], $xchg_date);
								break;
							case 22: // MC99-22
								$xdr_w22 = array(
									'int_value'=> 960,
									'value'=> $weight * 22
								);

								$xerate_xdrusd = $this-> m_money-> currency_Convert($xdr_w22, $xfields['maxliab_usd'], $xchg_date);
								break;
								
							case 10: // Domestic-1$
								$xerate_xdrusd = 1;
								$xfields['maxliab_usd'] = array(
									'int_value'=> 840,
									'value'=> $weight * 1
								);

								//$xerate_xdrusd = $this-> m_money-> currency_Convert($xdr_w10, $xfields['maxliab_usd'], $xchg_date);
								break;
                case 26: // MC99-22
								$xdr_w22 = array(
									'int_value'=> 960,
									'value'=> $weight * 26
								);

								$xerate_xdrusd = $this-> m_money-> currency_Convert($xdr_w22, $xfields['maxliab_usd'], $xchg_date);
								break;

							case 3: // MP4
								$xerate_xdrusd = 1;
								$xfields['maxliab_usd'] = array(
									'int_value'=> 840,
									'value'=> $weight * 20
								);
								break;

							// To be done later
/*							case 4: // MC99 - automated
								// u 1577491200 - 2019.12.28 (date after which ratio becomes 22 instead of 19)
								if(isset($metadata['_original']) && $metadata['_original']['ctime'] > 1577491200) $xdrw_ratio = 22; else $xdrw_ratio = 19;

								$xdr_value = array(
									'int_value'=> 960,
									'value'=> $weight * $xdrw_ratio
								);

								$xerate_xdrusd = $this-> m_money-> currency_Convert($xdr_value, $xfields['maxliab_usd'], $xchg_date);
								break;*/
							case 5: // Hague (600b) - 17 XDR per KG
								$xdr_w17 = array(
									'int_value'=> 960,
									'value'=> $weight * 17
								);

								$xerate_xdrusd = $this-> m_money-> currency_Convert($xdr_w17, $xfields['maxliab_usd'], $xchg_date);
								break;
						}
						if(!empty($xerate_xdrusd)) {
							$xfields['xerate_xdrusd'] = array('value'=> round($xerate_xdrusd, 4));
							// Also converted in "Formal Claim" currency (from USD)...
							if(!empty($xfields['claim_amt']['int_value'])) {
								$xfields['maxliab_amt'] = array('value' => null, 'int_value'=> $xfields['claim_amt']['int_value']);
								$this-> m_money-> currency_Convert($xfields['maxliab_usd'], $xfields['maxliab_amt'], $xchg_date);
							} else
								$xfields['maxliab_amt']['value'] = null;

							// ...as well as "Local" if defined
							if(!empty($xfields['claim_local']['int_value'])) {
								$xfields['maxliab_local'] = array('value' => null, 'int_value'=> $xfields['claim_local']['int_value']);
								$this-> m_money-> currency_Convert($xfields['maxliab_usd'], $xfields['maxliab_local'], $xchg_date);
							} else
								$xfields['maxliab_local']['value'] = null;
						} else {
							$xfields['maxliab_usd']['value'] = null;
							$xfields['maxliab_local']['value'] = null;
							$xfields['maxliab_amt']['value'] = null;
						}
					}

				} else {
					// No weight entered clears the maxliab fields
					$xfields['maxliab_usd']['value'] = null;
					$xfields['maxliab_local']['value'] = null;
					$xfields['maxliab_amt']['value'] = null;
				}

				// Insurance entered ?
				if(!empty($xfields['insured_usd']['value'])) {
					$xfields['insured_usd']['int_value'] = 840;
					// ... local?
					if(!empty($xfields['claim_local']['int_value'])) {
						$xfields['insured_local'] = array('value' => null, 'int_value'=> $xfields['claim_local']['int_value']);
						$this-> m_money-> currency_Convert($xfields['insured_usd'], $xfields['insured_local'], $xchg_date);
					} else
						$xfields['insured_local'] = null;

					// ... claimant?
					if(!empty($xfields['claim_amt']['int_value'])) {
						$xfields['insured_amt'] = array('value' => null, 'int_value'=> $xfields['claim_amt']['int_value']);
						$this-> m_money-> currency_Convert($xfields['insured_usd'], $xfields['insured_amt'], $xchg_date);
					} else
						$xfields['insured_amt'] = null;
				} else {
					$xfields['insured_local'] = null;
					$xfields['insured_amt'] = null;
				}

				// Provision and Suggested are using $provision_date if one is specified, otherwise the already determined $xchg_date
				if(!empty($xfields['provi_date']['int_value'])) {
					$provi_date = date("Y-m-d", $xfields['provi_date']['int_value']);
					// Check if this is "today", then also set '0000-00-00' (which means, live currency data)
					if($provi_date == date("Y-m-d"))
						$provi_date = '0000-00-00';
				}
				if(!isset($provi_date))
					$provi_date = $xchg_date;

				// Provision entered ?
				if(!empty($xfields['provision_usd']['value'])) {
					$xfields['provision_usd']['int_value'] = 840;
					// ... local?
					if(!empty($xfields['claim_local']['int_value'])) {
						$xfields['provision_local'] = array('value' => null, 'int_value'=> $xfields['claim_local']['int_value']);
						$this-> m_money-> currency_Convert($xfields['provision_usd'], $xfields['provision_local'], $provi_date);
					} else
						$xfields['provision_local'] = null;

					// ... claimant?
					if(!empty($xfields['claim_amt']['int_value'])) {
						$xfields['provision_amt'] = array('value' => null, 'int_value'=> $xfields['claim_amt']['int_value']);
						$this-> m_money-> currency_Convert($xfields['provision_usd'], $xfields['provision_amt'], $provi_date);
					} else
						$xfields['provision_amt'] = null;
				} else {
					$xfields['provision_local'] = null;
					$xfields['provision_amt'] = null;
				}

				// Suggested entered ?
				if(!empty($xfields['suggested_usd']['value'])) {
					$xfields['suggested_usd']['int_value'] = 840;
					// ... local?
					if(!empty($xfields['claim_local']['int_value'])) {
						$xfields['suggested_local'] = array('value' => null, 'int_value'=> $xfields['claim_local']['int_value']);
						$this-> m_money-> currency_Convert($xfields['suggested_usd'], $xfields['suggested_local'], $provi_date);
					} else
						$xfields['suggested_local'] = null;

					// ... claimant?
					if(!empty($xfields['claim_amt']['int_value'])) {
						$xfields['suggested_amt'] = array('value' => null, 'int_value'=> $xfields['claim_amt']['int_value']);
						$this-> m_money-> currency_Convert($xfields['suggested_usd'], $xfields['suggested_amt'], $provi_date);
					} else
						$xfields['suggested_amt'] = null;
				} else {
					$xfields['suggested_local'] = null;
					$xfields['suggested_amt'] = null;
				}

				// Paid amount is using Payment date if one is specified, otherwise the already determined $provi_date (or $xchg_date)
				if(!empty($xfields['settle_date']['int_value'])) {
					$settle_date = date("Y-m-d", $xfields['settle_date']['int_value']);
					// Check if this is "today", then also set '0000-00-00' (which means, live currency data)
					if($settle_date == date("Y-m-d"))
						$settle_date = '0000-00-00';
				}
				if(!isset($settle_date))
					$settle_date = $xchg_date;

				// Paid amount entered ?
				// Xchange rates for Paid are also stored, as this is an important value
				if(!empty($xfields['paid_usd']['value'])) {
					$xfields['paid_usd']['int_value'] = 840;
					// ... local?
					if(!empty($xfields['claim_local']['int_value'])) {
						$xfields['paid_local'] = array('value' => null, 'int_value'=> $xfields['claim_local']['int_value']);
						$xerate_paidlocal = $this-> m_money-> currency_Convert($xfields['paid_usd'], $xfields['paid_local'], $settle_date);
						if($xerate_paidlocal > 0)
							$xfields['xerate_paidlocal'] = array('value'=> round($xerate_paidlocal, 4));
					} else
						$xfields['paid_local'] = null;

					// ... claimant?
					if(!empty($xfields['claim_amt']['int_value'])) {
						$xfields['paid_amt'] = array('value' => null, 'int_value'=> $xfields['claim_amt']['int_value']);
						$xerate_paidusd = $this-> m_money-> currency_Convert($xfields['paid_usd'], $xfields['paid_amt'], $settle_date);
						if($xerate_paidusd > 0)
							$xfields['xerate_paidusd'] = array('value'=> round($xerate_paidusd, 4));
					} else
						$xfields['paid_amt'] = null;

				} else {
					$xfields['paid_local'] = null;
					$xfields['paid_amt'] = null;
				}

			} elseif(isset($xfields['money_auto']['int_value']) && $xfields['money_auto']['int_value'] == 0) {
				// With calculation off (but still in this panel), the only thing needed is to set currencies for the rest of the fields as they are for claim_*
				$xfields['maxliab_amt']['int_value'] = $xfields['claim_amt']['int_value'];
				$xfields['insured_amt']['int_value'] = $xfields['claim_amt']['int_value'];
				$xfields['provision_amt']['int_value'] = $xfields['claim_amt']['int_value'];
				$xfields['suggested_amt']['int_value'] = $xfields['claim_amt']['int_value'];
				$xfields['paid_amt']['int_value'] = $xfields['claim_amt']['int_value'];

				// USD column
				$xfields['maxliab_usd']['int_value'] = 840;
				$xfields['insured_usd']['int_value'] = 840;
				$xfields['provision_usd']['int_value'] = 840;
				$xfields['suggested_usd']['int_value'] = 840;
				$xfields['paid_usd']['int_value'] = 840;

				// Is local set?
				if(!empty($xfields['claim_local']['int_value'])) {
					$xfields['maxliab_local']['int_value'] = $xfields['claim_local']['int_value'];
					$xfields['insured_local']['int_value'] = $xfields['claim_local']['int_value'];
					$xfields['provision_local']['int_value'] = $xfields['claim_local']['int_value'];
					$xfields['suggested_local']['int_value'] = $xfields['claim_local']['int_value'];
					$xfields['paid_local']['int_value'] = $xfields['claim_local']['int_value'];
				}
			}

	// Charge-back panel - Procurement
			$proc_binary = array(
				'ntf'=> false,
				'st'=> false,
				'end'=> false,
			);

			// Grab existing values for inv_done (The checkboxes aren't turned off, only on)
			if(!empty($xfields['procur']['value']))
				$procur_cb_user = json_decode($xfields['procur']['value'], true);
			else
				$procur_cb_user = array();

			// If dates are present, turn on values inside $proc_binary
			if(!empty($xfields['proc_ntf_date']['int_value']) || in_array('ntf', $procur_cb_user)) // ds
				$proc_binary['ntf'] = true;
			if(!empty($xfields['proc_start_date']['int_value']) || in_array('st', $procur_cb_user)) // st
				$proc_binary['st'] = true;
			if(!empty($xfields['proc_end_date']['int_value']) || in_array('end', $procur_cb_user)) // end
				$proc_binary['end'] = true;

			$plist = array_keys(array_filter($proc_binary));
			if(!empty($plist))
				$xfields['procur']['value'] = json_encode($plist);

	// Handling and investigation panel (Airline)

			// Irregularity severity, based on the Irregularity code used
			if(!empty($xfields['irreg_code']['value'])) {
				$xfields['irreg_sev'] = array('int_value'=> self::$irreg_code2sev[$xfields['irreg_code']['value']]);
			}

			// Dossier length, Deadline - Calculated only when "Formal claim date" (acdate) is set
			if(!empty($xfields['acdate']['int_value'])) {

				$acdate = new DateTime("@{$xfields['acdate']['int_value']}");

				// SLA levels (30 days, 60 days, 90 days)
				$acd = clone $acdate;
				$xfields['sla_30'] = array('int_value'=> $acd-> add(new DateInterval("P30D"))-> getTimestamp());
				$xfields['sla_60'] = array('int_value'=> $acd-> add(new DateInterval("P30D"))-> getTimestamp());
				$xfields['sla_90'] = array('int_value'=> $acd-> add(new DateInterval("P30D"))-> getTimestamp());

				// Length ( Close date - Claim date )
				if(!empty($xfields['doss_close_date']['int_value'])) {
					$doss_close = new DateTime("@{$xfields['doss_close_date']['int_value']}");
					$proc_len = $acdate-> diff($doss_close);
					if($proc_len-> invert) {
						// This means the close date is before claim date (Error?) - Remove the date and cancel all calculations
						$xfields['doss_length'] = null;
						$xfields['doss_close_date'] = null;
						$xfields['sla_level'] = null;
					} else {
						$xfields['doss_length'] = array();
						$xfields['doss_length']['value'] = $xfields['doss_length']['int_value'] = (int)$proc_len-> days;

						// SLA level calculation (if doss_close_date exists)
						if(!empty($xfields['doss_length'])) {
							if(!empty($xfields['sla_extend']['int_value'])) {
								// Extension used? (days) (sla_level + 10)
								$extd = $xfields['sla_extend']['int_value'];
							} else
								$extd = 0;

							if($doss_close-> diff($acdate)-> days <= 30)
								$level = 1;
							elseif($doss_close-> diff($acdate)-> days <= 30 + $extd)
								$level = 11;
							elseif($doss_close-> diff($acdate)-> days <= 60)
								$level = 2;
							elseif($doss_close-> diff($acdate)-> days <= 60 + $extd)
								$level = 12;
							elseif($doss_close-> diff($acdate)-> days <= 90)
								$level = 3;
							elseif($doss_close-> diff($acdate)-> days <= 90 + $extd)
								$level = 13;
							else
								$level = -1;

							$xfields['sla_level'] = array('int_value'=> $level);
						}
					}
				} else {
					$xfields['doss_length'] = null;
					$xfields['doss_close_date'] = null;
					$xfields['sla_level'] = null;
				}
			}
		}
	}

	/**
	 * Verifies if and how notification should be sent with specifics for this record type
	 *
	 * @param string $ntf_type Notification type (issue_created, issue_updated, etc...)
	 * @param array $rt_def Record Type definition
	 * @param array $rec Record data (complete)
	 * @param array $org_ntf_settings Organization notification settings for this record type (Optional, might affect the result)
	 * @param array $user_sub User options for subscription (Optional, might affect the result)
	 * @return array Result of the check
	 */
	public function ntf_Check($ntf_type, &$rt_def, &$rec, $org_ntf_settings = null, $user_sub = null)
	{
		$res = array();
		switch($ntf_type) {
			case 'issue_created':
			// Check if there are special organization settings for "issue_created"
				if(isset($org_ntf_settings['issue_created'])) {

					foreach($org_ntf_settings['issue_created'] as $keyword=> $value) {
						// This plugin recognizes several settings specific for it
						switch($keyword) {
							case 'enable_prelim':
								// If Air Cargo Claim is "formal" and value of this keyword is false, set the return's parameter "enable" to false -> Notification won't be generated
								// Normally, if this keyword is not present, the notification is sent
								if($value === false && $rec['xfields']['pstage']['key'] == 'prel')
									$res['enabled'] = false;
							break;
							case 'enable_formal':
								// ... same thing for formal claims
								if($value === false && $rec['xfields']['pstage']['key'] == 'fpr')
									$res['enabled'] = false;
							break;
						}
					}
				}

			break;
		}
		return $res;
	}

	/**
	 * Uses the record data to generate a "keyword" used to select which automatic responder message will be used
	 * @param array $rec
	 * @param string $org_type_id Optionally, the type of organization might influence the output of the keyword generation
	 * @param bool $use_xfsrc Use $rec['xfields_source'] instead of $rec['xfields'] (Optional parameter)
	 * @return string
	 */
	public function arem_Keyword(&$rec, $org_type_id = null, $use_xfsrc = false)
	{
		if($use_xfsrc)
			$xf = &$rec['xfields_source'];
		else
			$xf = &$rec['xfields'];

		if(isset($xf['pstage'])) {
			switch($org_type_id) {
				case 'airline':
					return $xf['pstage']['key'].$xf['mawb']['p']; // Returns either "prel" or "fpr" with awb prefix appended
				case 'forwarder':
				case 'gsa':
				case 'handler':
					return $xf['pstage']['key']."all"; // prelall / fprall
				default:
					// Without the organization type ID, or when invalid/unsupported, the keyword is "default"
					return 'default';
			}
		}

		// Nothing to return without the proper field
		return null;
	}

	protected static $claim_types = array(
		'prelim'=> "Preliminary claim:",
		'formal'=> "Formal claim:"
	);

	/**
	 * Custom processing on record-type specific ajax request - this one is for Record Make
	 * @param MVC_Request $rq
	 * @param array $config
	 */
	public function proc_Prelim_Formal(MVC_Request $rq, $config)
	{
		if(!$rq-> claim_option)
			return array(
				'error'=> true,
				'message'=> "Please choose claim type before continuing"
			);

		if(!isset(self::$claim_types[$rq-> claim_option]))
			return array(
				'error'=> true,
				'message'=> sprintf("Invalid value received: %s. Please contact tech@cargohub.nl", $rq-> claim_option)
			);

		// Everything ok, return information on how to proceed
		return array(
			'op'=> "step", // Load another step
			'step'=> $rq-> claim_option, // Next step to load
			'tab_title'=> self::$claim_types[$rq-> claim_option], // Replace existing tab title with this one
			'tab_disable'=> true
		);
	}

}