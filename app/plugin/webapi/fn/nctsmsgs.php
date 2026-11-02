<?php
/**
 * WebAPI Function plugin for function "NCTS Messages"
*
* @author Wavetrex Infinity <wavetrex@gmail.com>
* @package CargoConnect
* @subpackage WebAPI Plugins
* @version 2.10
*/
class App_Plugin_WebAPI_fn_NCTSMsgs implements App_Plugin_WebAPI_fn
{
	// TODO: There's a lot of repetition in the functions of this plugin, make some sub-methods to handle common operations

	public $direction = "in";

	private $CFG;

	/**
	 * WebAPI model object which instanced this function plugin
	 * @var App_Model_WebAPI
	 */
	private $master;

	/**
	 * Record model
	 * @var App_Model_Issue
	 */
	private $rec;

	/**
	 * Record type information
	 * @var array
	 */
	private $rt;

	/**
	 * Field used for Quickindex
	 * @var unknown
	 */
	private $qi_field = null;

	/**
	 * Constructor receives configuration for this instance of the plugin
	 * @param array $CFG
	 */
	public function __construct($CFG, &$master = null)
	{
		$this-> CFG = $CFG;
		if(!isset($CFG['rt'])) {
			MVC_Core::$debug-> dump("Record Type missing from function config", __METHOD__, null, 'error');
			throw new Exception();
		}

		if($master instanceof App_Model_WebAPI) {
			$this-> master = &$master;
			if(!isset($master-> plugin_report['function']))
				$master-> plugin_report['function'] = array(
					'created'=> 0,
					'updated'=> 0
				);
		}

		// This function deals with records- Load the RT config
		$this-> rec = new App_Model_Issue();
		$this-> rt = $this-> rec-> ist_Read($CFG['rt'], false, false, false, true);
		if($this-> rt === false) {
			MVC_Core::$debug-> dump("Invalid Record Type in the function config", __METHOD__, null, 'error');
			throw new Exception();
		}

		$this-> qi_field = @$this-> rt['settings']['xf_cache']['quickindex'][$this-> CFG['qi_field']];
		if(empty($this-> qi_field)) {
			MVC_Core::$debug-> dump("QuickIndex field not defined in the function config", __METHOD__, null, 'error');
			throw new Exception();
		}
	}

	// These do not change, save them in execute
	private $org_id;

	private $user_id;

	/**
	 * Scans incoming data and processes the content
	 * (it does either record create or update or other operations, depending on data)
	 *
	 * @param array $data
	 * @param int $org_id Organization ID
	 * @param int $user_id Execute the import as this user !?
	 */
	public function execute($data, $org_id, $user_id = null)
	{
		if(in_array($data['type'], array('nondischarged','arrived', 'cancelled', 'mrnalloc', 'released','inspection','writeoff', 'rejected', 'xerror'))) {
			$this-> org_id = $org_id;
			$this-> user_id = $user_id;
			/*
			 * arrived = Arrived at destination ? // Status "arr"
			 * cancelled = ... Cancelled
			 * mrnalloc = NCTS number assigned (CREATE RECORD) // Status "mrn"
			 * departure = Released at departure (Detail information about the transport) // Status "dep"
			 * writeoff = Control Results sent by office of Destination // Status "isen"
			 * rejected = Declaration rejected
			 * xerror = Error - Check content
			 */
			$method_name = 'msg_'.$data['type'];
			if(method_exists($this, $method_name)) {
				$this-> no_action = false;
				$result = call_user_func(array($this, $method_name), $data);
				if($result === false)
					return false; // Some error generated above, exit execute with error

				return $result; // NCTS Message import success
			} else
				return MVC_Core::$debug-> dump("Message type [{$data['type']}] not yet implemented.", __METHOD__, null, 'error');
		} else
			return MVC_Core::$debug-> dump("Unsupported message type detected: [{$data['type']}]", __METHOD__, null, 'error');
	}

	/**
	 * ID of the record after being located by locate_Record
	 * @var unknown
	 */
	private $record_id;

	/**
	 * If True, Execute method didn't fail, but there's nothing to do
	 * @var boolean
	 */
	private $no_action = false;

	/**
	 * Uses Quickindex to locate a record based on the Quickindex value (which is the MRN Number {mrn_no} for this function)
	 * Returns the record id if it exists, or null if not
	 *
	 * @param string $qi_value Returns "false" if an error occured, in which case the function should stop.
	 */
	protected function locate_Record($qi_value, $caller_fn)
	{
		// Check if QuickIndex exists on the fields that are being searched (xfmatch). If yes, locate records via quickindex. If not, perform a join
			// This should contain the field ID, for locating directly in the index table. Run the index search query and grab the ID's
		$qi_q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['rqi'], array('rec_id'))
		-> where("`fk_id` = ?", $this-> qi_field)
		-> where("`value` = ?", $qi_value);

		$res = MVC_Core::$db-> query($qi_q);
		if(empty($res)) {
			$this-> record_id = null;
			return null;
		}

		if(count($res) > 1) {
			// Multiple records found. Unfortunately this function doesn't know how to deal with this case, so it does nothing and throws an error instead
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> $caller_fn,
				'message'=> "Multiple records found for MRN number: {$qi_value}",
				'data'=> $qi_value
			));
			// Function failed, but the import continues
			$this-> record_id = null;
			return MVC_Core::$debug-> dump(sprintf("Error(s) encountered during function call - Log ID: %d", $log_iid), __METHOD__, null, 'error');
		} elseif(count($res) == 1) {
			$search_result = $res-> current();
			$this-> record_id = (int)$search_result['rec_id'];
			return $this-> record_id;
		} else {
			// Not found
			$this-> record_id = null;
			return null;
		}
	}

	/**
	 * This is the message which generates the record when it is encountered
	 *
	 * @param array $data
	 * @param int $org_id
	 * @param int $user_id
	 */
	protected function msg_mrnalloc($data)
	{
		$ret = $this-> locate_Record($data['MRN'], "mrnalloc");
		if($ret === false)
			return false;

		if(!empty($this-> record_id)) {
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "create",
				'message'=> "Record for MRN number {$data['MRN']} already exists. Ignoring message.",
				'data'=> $this-> record_id
			));
			MVC_Core::$debug-> dump("Record for MRN number {$data['MRN']} already exists. Ignoring message.", __METHOD__, null, 'warning');
			$this-> no_action = true;
			return true;
		}
		if(empty($this-> CFG['create']['group_id']))
			return MVC_Core::$debug-> dump("Initial Data Group ID not present in the configuration. Cannot create new record.", __METHOD__, null, 'error');

		// No record currently exists, create one
		// Add some meta information:
		$record_meta = array(
			'ctime'=> $data['datetime']-> getTimestamp(),
			'master_org_id'=> $this-> org_id,
			'shared_org_ids'=> null,
			'group_id'=> (int)$this-> CFG['create']['group_id'],
			'submit_org_id'=> $this-> org_id
		);
		$dep_ids = isset($this-> CFG['create']['dep_ids'])? $this-> CFG['create']['dep_ids']: null;
		$orgdata = array(
			'linktime'=> $record_meta['ctime'],
			'process'=> "webapi",
			'status_key'=> "mrn" // MRN Allocated
		);

		// There is no "mapper" for these messages, instead the "function" analyses the content and builds Xfields directly
		$xfields = array(
			'mrn_no'=> strtoupper($data['MRN']),
			'reference'=> $data['references']['number'],
			'orig_msg_id'=> $data['reference_to'],
			'ncts_recv'=> array('mrnalloc'=>true)
		);
		if(!empty($data['locations']['event_loc']))
			$xfields['issued_loc'] = $data['locations']['event_loc'];
		if(!empty($data['names']['principal']))
			$xfields['issued_by'] = $data['names']['principal'];

		if(!empty($data['dates']['accept_date']))
			$xfields['mrn_date'] = $data['dates']['accept_date']-> format("Y-m-d H:i:s");

		$ric = $this-> rec-> create($this-> rt['base_id'], $record_meta, $xfields, false, false); // 2nd "false" - Skip checking for mandatory fields (since this is not a human form submit)
		if($ric===false || is_array($ric)) {
			// there is a problem with some xfields - Log the error and skip the record
			if(is_array($ric))
				foreach($ric as $ic_err) {
					if(isset($ic_err['plugin_err']))
						// plugin field error
						$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
							'label'=> __METHOD__,
							'operation'=> "create",
							'message'=> "Field '{$ic_err['fn']}' error: {$ic_err['plugin_err']}",
							'data'=> $xfields
						));
						else
							// standard field error
							$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
								'label'=> __METHOD__,
								'operation'=> "create",
								'message'=> "Field '{$ic_err['fn']}' error: ".__('issue.'.$ic_err['err'], true),
								'data'=> $xfields
							));
				}
			else
				$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
					'label'=> __METHOD__,
					'operation'=> "create",
					'message'=> "Error while attempting to create record",
					'data'=> $data
				));
			// Function failed, but the import continues
			return MVC_Core::$debug-> dump(sprintf("Error(s) encountered in Record Create - (Last) Log ID: %d", $log_iid), __METHOD__, null, 'warning');
		}

		// Success, store the ID
		$this-> record_id = $ric;

		$this-> rec-> org_Add($ric, $this-> org_id, $dep_ids, $orgdata, null, true, false, 'webapi'); // No checks - Avoid unnecesary queries on data which is prepared in advance ( like import settings )
		$this-> rec-> ist_Counter_Update($this-> rt['base_id'], 1);
		$this-> master-> plugin_report['function']['created']++;

		return true;
	}

	/**
	 * Cargo inspection at departure, before release. The record must exist for this to apply
	 *
	 * @param array $data
	 */
	protected function msg_inspection($data)
	{
		$ret = $this-> locate_Record($data['MRN'], "inspection");
		if(empty($ret)) {
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Record for MRN number {$data['MRN']} was not found, cannot update. Ignoring message.",
				'data'=> $data['id']
			));
			MVC_Core::$debug-> dump("Record for MRN number {$data['MRN']} was not found, cannot update. Ignoring message.", __METHOD__, null, 'warning');
			$this-> no_action = true;
			return true;
		}

		$rec = $this-> rec-> read($this-> record_id, array('xfields_raw'), $this-> org_id, null, null, array('access_hidden'=> true));

		// If status is already "insp", ignores the message
		if($rec['status_key'] == "insp") {
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Current Status for MRN number {$data['MRN']} is already [Inspection]. Ignoring message.",
				'data'=> $data['id']
			));
			MVC_Core::$debug-> dump("Current Status for MRN number {$data['MRN']} is already [Inspection]. Ignoring message.", __METHOD__, null, 'warning');
			$this-> no_action = true;
			return true;
		}

		// Record meta
		$record_meta = array(
			'_org_id'=> $this-> org_id,
			'status_key'=> "insp"
		);

		$xfields = array();
		// Merge field ID 2539 ( ncts_recv )
		foreach($rec['xfields_raw'] as $raw_field) {
			if($raw_field['fk_id']!= 2539)
				continue;

			$val2539 = json_decode($raw_field['value']);
			$val2539[] = "inspection";
			$xfields['ncts_recv'] = array();
			foreach($val2539 as $val)
				$xfields['ncts_recv'][$val] = true;
			break;
		}

		// Inspection date ; However the message Date/Time is used instead
		$xfields['insp_date'] = $data['datetime']-> format("Y-m-d H:i:s");
		// 		if(!empty($data['dates']['ctrl_date']))
		// 			$xfields['insp_date'] = $data['dates']['ctrl_date']-> format("Y-m-d H:i:s");
		// Not needed yet
// 		if(!empty($data['locations']['event_loc']))
// 			$xfields['insp_loc'] = $data['locations']['event_loc'];

		$riu = $this-> rec-> update($this-> record_id, $record_meta, $xfields);
		if($riu===false || is_array($riu)) { // either query error ( false ) or xfields problem ( array of xfields errors )
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Error encountered while updating record with MRN number {$data['MRN']}. Invalid data in the message."
			));
			return MVC_Core::$debug-> dump("Error encountered while updating record with MRN number {$data['MRN']}. Invalid data in the message.", __METHOD__, null, 'error');
		}

		$this-> master-> plugin_report['function']['updated']++;
		return true;
	}

	/**
	 * Contains primary information about the transit record, here's where fields get filled.
	 *
	 * @param array $data
	 */
	protected function msg_released($data)
	{
		$ret = $this-> locate_Record($data['MRN'], "released");
		if(empty($ret)) {
			// TODO: If 629 is encounterd and there is no 628, leave the message there, maybe they were accessed in the wrong order or the limit has been reached.
			// Possibly a 628 will be read after and the record will be created, then at the next processing round, 629 will do it's thing
			// If message is older than 7 days and still no matching record, then message is ignored and deleted
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Record for MRN number {$data['MRN']} was not found, cannot update. Ignoring message.",
				'data'=> $data['id']
			));
			MVC_Core::$debug-> dump("Record for MRN number {$data['MRN']} was not found, cannot update. Ignoring message.", __METHOD__, null, 'warning');
			$this-> no_action = true;
			return true;
		}

		// Read existing record - Only update those that have "submit_id" Null (created via WebAPI). Normal form submit always has a contact ID as the submitter
		// $org_id = null, $dep_id = null, $user_id = null, $settings = array()
		$rec = $this-> rec-> read($this-> record_id, array('xfields_raw'), $this-> org_id, null, null, array('access_hidden'=> true));
		if(!empty($rec['submitter']['id'])) {
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Record for MRN number {$data['MRN']} is not created by WebAPI. Ignoring message.",
				'data'=> $data['id']
			));
			MVC_Core::$debug-> dump("Record for MRN number {$data['MRN']} is not created by WebAPI. Ignoring message.", __METHOD__, null, 'warning');
			$this-> no_action = true;
			return true;
		}

		// If status is already "dep", ignores the message
		if($rec['status_key'] == "dep") {
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Current Status for MRN number {$data['MRN']} is already [Departed]. Ignoring message.",
				'data'=> $data['id']
			));
			MVC_Core::$debug-> dump("Current Status for MRN number {$data['MRN']} is already [Departed]. Ignoring message.", __METHOD__, null, 'warning');
			$this-> no_action = true;
			return true;
		}

		// Record meta
		$record_meta = array(
			'_org_id'=> $this-> org_id,
			'status_key'=> "dep"  // Departed
// 			'status_key'=> "reld" // (or...) "Released at Departure"
		);

		// Lots of data in this type of message, map and update xfields
		$xfields = array(
			'movement'=> 1, // Outbound
		);

		// Merge field ID 2539 ( ncts_recv )
		foreach($rec['xfields_raw'] as $raw_field) {
			if($raw_field['fk_id']!= 2539)
				continue;

			$val2539 = json_decode($raw_field['value']);
			$val2539[] = "released";
			$xfields['ncts_recv'] = array();
			foreach($val2539 as $val)
				$xfields['ncts_recv'][$val] = true;
			break;
		}

		// The "Released" message coincides to when the truck leaves
		$xfields['truck_date'] = $data['datetime']-> format("Y-m-d H:i:s");

		// Map fields. Could this be done by a Mapper plugin instead ?
		if(!empty($data['locations']['dest_country']))
			$xfields['dest_country'] = $data['locations']['dest_country'];
		if(!empty($data['locations']['orig_country']))
			$xfields['orig_country'] = $data['locations']['orig_country'];
		if(!empty($data['locations']['load_loc']))
			$xfields['load_loc'] = $data['locations']['load_loc'];
		if(!empty($data['locations']['dest_loc']))
			$xfields['dest_loc'] = $data['locations']['dest_loc'];

		if(!empty($data['locations']['declar_loc']))
			$xfields['declar_loc'] = $data['locations']['declar_loc'];
		if(!empty($data['locations']['customs_office']))
			$xfields['customs_office'] = is_array($data['locations']['customs_office'])? implode(' ', $data['locations']['customs_office']): $data['locations']['customs_detail'];

		if(!empty($data['dates']['declar_date']))
			$xfields['declar_date'] = $data['dates']['declar_date']-> format("Y-m-d H:i:s");
		if(!empty($data['dates']['limit_date']))
			$xfields['ndis_limit_date'] = $data['dates']['limit_date']-> format("Y-m-d H:i:s");

		// Not sure if this is correct, so disabling for now
		/*
		if(isset($data['indicators']['packing']) && $data['indicators']['packing'] == 'container')
			$xfields['packing'] = 'uld';
		else
			$xfields['packing'] = 'loose';
		*/

		// Total weight, pieces and units
		if(!empty($data['measurements']['wt_total']))
			$xfields['pack_weight'] = $data['measurements']['wt_total'];
		if(!empty($data['totals']['pieces']))
			$xfields['loose_pieces'] = $data['totals']['pieces'];
		if(!empty($data['totals']['items']))
			$xfields['intact_units'] = $data['totals']['items'];

		if(!empty($data['transport']['identity']))
			$xfields['trans_id'] = is_array($data['transport']['identity'])? implode('/', $data['transport']['identity']): $data['transport']['identity'];
		if(!empty($data['transport']['mode']))
			$xfields['trans_mode'] = $data['transport']['mode'];

		if(!empty($data['names']['principal']))
			$xfields['principal'] = $data['names']['principal'];
		if(!empty($data['names']['consignor']))
			$xfields['consignor'] = $data['names']['consignor'];
		if(!empty($data['names']['consignee']))
			$xfields['consignee'] = $data['names']['consignee'];

		if(!empty($data['financial']['amount']))
			$xfields['mrn_amt'] = array(
				'curr'=> 978, // EUR
				'amount'=> $data['financial']['amount']
			);

		// List of items, they map to "awbN*" fields;
		// This also determines data group (Airline), if AWB prefix is identical for all items)
		$unique_prefix = true; $prefix = null;
		foreach($data['list'] as $idx => $item) {
			if($idx == 15)
				break; // Not having fields beyond 15, all the rest are ignored
			$kpfx = "awb".($idx+1);

			$xfields[$kpfx] = $item['package']['marks'];
			$xfields[$kpfx.'_p'] = $item['package']['pieces'];
			// At the moment both gross or net are accepted. If they both appear, NET will be stored
			if(!empty($item['measurements']['wt_gross']))
				$xfields[$kpfx.'_kg'] = $item['measurements']['wt_gross'];
			if(!empty($item['measurements']['wt_net']))
				$xfields[$kpfx.'_kg'] = $item['measurements']['wt_net'];
			$xfields[$kpfx.'_goods'] = is_array($item['goods'])? implode(' ', $item['goods']): $item['goods'];

			// Airline prefix scan
			if(!$unique_prefix)
				continue; // Once non-unique prefix is found, the others aren't checked

			$temp_prefix = substr($item['package']['marks'], 0, 3);
			if(empty($prefix)) {
				$prefix = $temp_prefix;
				continue;
			} else
				if($temp_prefix != $prefix)
					$unique_prefix = false;
		}

		// Search airline based on AWB prefix from items in "list"
		$m_airlines = new App_Model_Data("airlines");
		$airline = $m_airlines-> read($prefix, "AWB_prefix");
		if($airline !== false) {
			$record_meta['group_id'] = $airline['group_id'];
		}

		$riu = $this-> rec-> update($this-> record_id, $record_meta, $xfields);
		if($riu===false || is_array($riu)) { // either query error ( false ) or xfields problem ( array of xfields errors )

			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Error encountered while updating record with MRN number {$data['MRN']}. Invalid data in the message."
			));
			return MVC_Core::$debug-> dump("Error encountered while updating record with MRN number {$data['MRN']}. Invalid data in the message.", __METHOD__, null, 'error');
		}

		$this-> master-> plugin_report['function']['updated']++;
		// All good, ready to update Xfields, status
		return true;
	}

	/**
	 * Cargo arrived at destination. The record must exist for this to apply
	 *
	 * @param array $data
	 */
	protected function msg_arrived($data)
	{
		$ret = $this-> locate_Record($data['MRN'], "arrived");
		if(empty($ret)) {
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Record for MRN number {$data['MRN']} was not found, cannot update. Ignoring message.",
				'data'=> $data['id']
			));
			MVC_Core::$debug-> dump("Record for MRN number {$data['MRN']} was not found, cannot update. Ignoring message.", __METHOD__, null, 'warning');
			$this-> no_action = true;
			return true;
		}

		$rec = $this-> rec-> read($this-> record_id, array('xfields_raw'), $this-> org_id, null, null, array('access_hidden'=> true));

		// If status is already "arr", ignores the message
		if($rec['status_key'] == "arr") {
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Current Status for MRN number {$data['MRN']} is already [Arrived]. Ignoring message.",
				'data'=> $data['id']
			));
			MVC_Core::$debug-> dump("Current Status for MRN number {$data['MRN']} is already [Arrived]. Ignoring message.", __METHOD__, null, 'warning');
			$this-> no_action = true;
			return true;
		}

		// Record meta
		$record_meta = array(
			'_org_id'=> $this-> org_id,
			'status_key'=> "arr"
		);

		$xfields = array();
		// Merge field ID 2539 ( ncts_recv )
		foreach($rec['xfields_raw'] as $raw_field) {
			if($raw_field['fk_id']!= 2539)
				continue;

			$val2539 = json_decode($raw_field['value']);
			$val2539[] = "arrived";
			$xfields['ncts_recv'] = array();
			foreach($val2539 as $val)
				$xfields['ncts_recv'][$val] = true;
			break;
		}

		// Arrival date ; However the message Date/Time is used instead
		$xfields['arri_date'] = $data['datetime']-> format("Y-m-d H:i:s");
// 		if(!empty($data['dates']['arrival_date']))
// 			$xfields['arri_date'] = $data['dates']['arrival_date']-> format("Y-m-d H:i:s");
		if(!empty($data['locations']['present_loc']))
			$xfields['present_loc'] = $data['locations']['present_loc'];

		$riu = $this-> rec-> update($this-> record_id, $record_meta, $xfields);
		if($riu===false || is_array($riu)) { // either query error ( false ) or xfields problem ( array of xfields errors )
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Error encountered while updating record with MRN number {$data['MRN']}. Invalid data in the message."
			));
			return MVC_Core::$debug-> dump("Error encountered while updating record with MRN number {$data['MRN']}. Invalid data in the message.", __METHOD__, null, 'error');
		}

		$this-> master-> plugin_report['function']['updated']++;
		return true;
	}

	/**
	 * Write-off message. Closes the record
	 * @param unknown $data
	 * @return boolean
	 */
	protected function msg_writeoff($data)
	{
		$ret = $this-> locate_Record($data['MRN'], "writeoff");
		if(empty($ret)) {
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Record for MRN number {$data['MRN']} was not found, cannot update. Ignoring message.",
				'data'=> $data['id']
			));
			MVC_Core::$debug-> dump("Record for MRN number {$data['MRN']} was not found, cannot update. Ignoring message.", __METHOD__, null, 'warning');
			$this-> no_action = true;
			return true;
		}

		$rec = $this-> rec-> read($this-> record_id, array('xfields_raw'), $this-> org_id, null, null, array('access_hidden'=> true));

		// If status is already "arr", ignores the message
		if($rec['status_key'] == "disc") {
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Current Status for MRN number {$data['MRN']} is already [Discharged]. Ignoring message.",
				'data'=> $data['id']
			));
			MVC_Core::$debug-> dump("Current Status for MRN number {$data['MRN']} is already [Discharged]. Ignoring message.", __METHOD__, null, 'warning');
			$this-> no_action = true;
			return true;
		}

		// Record meta
		$record_meta = array(
			'_org_id'=> $this-> org_id,
			'status_key'=> "disc"
		);

		$xfields = array();
		// Merge field ID 2539 ( ncts_recv )
		foreach($rec['xfields_raw'] as $raw_field) {
			if($raw_field['fk_id']!= 2539)
				continue;

			$val2539 = json_decode($raw_field['value']);
			$val2539[] = "writeoff";
			$xfields['ncts_recv'] = array();
			foreach($val2539 as $val)
				$xfields['ncts_recv'][$val] = true;
			break;
		}

		// Discharge date; However the message date-time is used instead
		$xfields['disch_date'] = $data['datetime']-> format("Y-m-d H:i:s");
// 		if(!empty($data['dates']['discharge_date']))
// 			$xfields['disch_date'] = $data['dates']['discharge_date']-> format("Y-m-d H:i:s");
		// TODO: Do something about "indicators.proc_A57"

		$riu = $this-> rec-> update($this-> record_id, $record_meta, $xfields);
		if($riu===false || is_array($riu)) { // either query error ( false ) or xfields problem ( array of xfields errors )
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Error encountered while updating record with MRN number {$data['MRN']}. Invalid data in the message."
			));
			return MVC_Core::$debug-> dump("Error encountered while updating record with MRN number {$data['MRN']}. Invalid data in the message.", __METHOD__, null, 'error');
		}

		// Close the record for the organization on writeoff to hide it from grid
		$fields_update = array(
			'process'=> "closed",
		);
		$fields_update['time_close'] = time();
		if(!empty($rec['linktime']))
			$fields_update['process_length'] = time() - $rec['linktime'];

		$this-> rec-> org_UpdateData($this-> record_id, $this-> org_id, $fields_update, true);

		$this-> master-> plugin_report['function']['updated']++;
		return true;
	}

	/**
	 * Non-Discharged message (unable to test due to lack of messages)
	 * @param array $data
	 * @return boolean
	 */
	protected function msg_nondischarged($data)
	{
		$ret = $this-> locate_Record($data['MRN'], "nondischarged");
		if(empty($ret)) {
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Record for MRN number {$data['MRN']} was not found, cannot update. Ignoring message.",
				'data'=> $data['id']
			));
			MVC_Core::$debug-> dump("Record for MRN number {$data['MRN']} was not found, cannot update. Ignoring message.", __METHOD__, null, 'warning');
			$this-> no_action = true;
			return true;
		}

		$rec = $this-> rec-> read($this-> record_id, array('xfields_raw'), $this-> org_id, null, null, array('access_hidden'=> true));
		// If status is already "can", ignores the message
		if($rec['status_key'] == "ndis") {
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Current Status for MRN number {$data['MRN']} is already [Non-Discharged]. Ignoring message.",
				'data'=> $data['id']
			));
			MVC_Core::$debug-> dump("Current Status for MRN number {$data['MRN']} is already [Non-Discharged]. Ignoring message.", __METHOD__, null, 'warning');
			$this-> no_action = true;
			return true;
		}

		// Record meta
		$record_meta = array(
			'_org_id'=> $this-> org_id,
			'status_key'=> "ndis"
		);

		$xfields = array();
		// Merge field ID 2539 ( ncts_recv )
		foreach($rec['xfields_raw'] as $raw_field) {
			if($raw_field['fk_id']!= 2539)
				continue;

			$val2539 = json_decode($raw_field['value']);
			$val2539[] = "nondischarged";
			$xfields['ncts_recv'] = array();
			foreach($val2539 as $val)
				$xfields['ncts_recv'][$val] = true;
			break;
		}

		$riu = $this-> rec-> update($this-> record_id, $record_meta, $xfields);
		if($riu===false || is_array($riu)) { // either query error ( false ) or xfields problem ( array of xfields errors )
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Error encountered while updating record with MRN number {$data['MRN']}. Invalid data in the message."
			));
			return MVC_Core::$debug-> dump("Error encountered while updating record with MRN number {$data['MRN']}. Invalid data in the message.", __METHOD__, null, 'error');
		}

		$this-> master-> plugin_report['function']['updated']++;
		return true;
	}

	/**
	 * Cancelled message, for example when an MRN is replaced
	 * @param array $data
	 * @return boolean
	 */
	protected function msg_cancelled($data)
	{
		$ret = $this-> locate_Record($data['MRN'], "cancelled");
		if(empty($ret)) {
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Record for MRN number {$data['MRN']} was not found, cannot update. Ignoring message.",
				'data'=> $data['id']
			));
			MVC_Core::$debug-> dump("Record for MRN number {$data['MRN']} was not found, cannot update. Ignoring message.", __METHOD__, null, 'warning');
			$this-> no_action = true;
			return true;
		}

		$rec = $this-> rec-> read($this-> record_id, array('xfields_raw'), $this-> org_id, null, null, array('access_hidden'=> true));
		// If status is already "can", ignores the message
		if($rec['status_key'] == "can") {
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Current Status for MRN number {$data['MRN']} is already [Cancelled]. Ignoring message.",
				'data'=> $data['id']
			));
			MVC_Core::$debug-> dump("Current Status for MRN number {$data['MRN']} is already [Cancelled]. Ignoring message.", __METHOD__, null, 'warning');
			$this-> no_action = true;
			return true;
		}

		// Record meta
		$record_meta = array(
			'_org_id'=> $this-> org_id,
			'status_key'=> "can"
		);

		$xfields = array();
		// Merge field ID 2539 ( ncts_recv )
		foreach($rec['xfields_raw'] as $raw_field) {
			if($raw_field['fk_id']!= 2539)
				continue;

			$val2539 = json_decode($raw_field['value']);
			$val2539[] = "cancelled";
			$xfields['ncts_recv'] = array();
			foreach($val2539 as $val)
				$xfields['ncts_recv'][$val] = true;
			break;
		}

		// MRN Cancellation reason and location; However the message DateTime is used instead
		$xfields['cancel_date'] = $data['datetime']-> format("Y-m-d H:i:s");
// 		if(!empty($data['dates']['cancel_date']))
// 			$xfields['cancel_date'] = $data['dates']['cancel_date']-> format("Y-m-d H:i:s");
		if(!empty($data['locations']['event_loc']))
			$xfields['cancel_loc'] = $data['locations']['event_loc'];
		if(!empty($data['extra']['cancel_reason']))
			$xfields['cancel_reason'] = $data['extra']['cancel_reason'];

		$riu = $this-> rec-> update($this-> record_id, $record_meta, $xfields);
		if($riu===false || is_array($riu)) { // either query error ( false ) or xfields problem ( array of xfields errors )
			$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
				'label'=> __METHOD__,
				'operation'=> "update",
				'message'=> "Error encountered while updating record with MRN number {$data['MRN']}. Invalid data in the message."
			));
			return MVC_Core::$debug-> dump("Error encountered while updating record with MRN number {$data['MRN']}. Invalid data in the message.", __METHOD__, null, 'error');
		}

		$this-> master-> plugin_report['function']['updated']++;
		return true;
	}

	// Not implemented, incoming messages are currently bugged
	protected function msg_rejected($data)
	{
		dd($data, "Rejected");
		return true;
	}

	protected function msg_xerror($data)
	{
		$log_iid = $this-> master-> log_Entry("execute", $this-> org_id, $this-> user_id, "warning", array(
			'label'=> __METHOD__,
			'operation'=> "error",
			'message'=> "Error message (917, 616) encountered. Ignoring.",
			'data'=> $data['id']
		));
		MVC_Core::$debug-> dump("Error message (917, 616) encountered. Ignoring.", __METHOD__, null, 'warning');
		return true;
	}

	/**
	 * Processes the original stream and optionally deletes the source by calling the Connector plugin
	 * @param unknown $stream
	 */
	public function process_stream($stream, $meta)
	{
		 
		// BAD, VERY BAD !
		// Creates a copy of the file on another FTP, before processing it
		$conn = ftp_connect("cargohub.aero", 21, 5);
		if($conn !== false) {
		 
			$auth_result = @ftp_login($conn, "dnata-ncts@cargohub.aero", "Wo4CHcOA");
			if(!$auth_result)
				ftp_close($conn);
			else {
				// We're good, create the file and upload
				ftp_pasv($conn, true);
				
				// If change dir succeeded, attempt to create the file
				$tmpfile = fopen('php://memory', 'r+');
				fputs($tmpfile, $stream);
				rewind($tmpfile);
				ftp_fput($conn, sprintf("%s.%s", $meta['filename'], $meta['extension']), $tmpfile, FTP_BINARY);
				

				ftp_close($conn);
			}
		}

		// Store the stream in the record as a Note
//		if(!$this-> no_action) {
		if(!empty($this-> record_id)) {
			$m_notes = new App_Model_Note();
			
			// 'visibility', 'edit_access', 'editor_id', 'org_id', 'dep_id', 'title', 'description', 'version', 'content_type', 'text', 'metadata'
			$m_notes-> create('issue', $this-> record_id, array(
				'visibility'=> "private",
				'edit_access'=> "user",
				'org_id'=> $this-> org_id,
				'editor_id'=> $this-> user_id,
				'title'=> "MSG {$meta['filename']}.{$meta['extension']} ".$meta['dt']-> format("Y-m-d H:i"),
				'description'=> "Format: EDIFACT; Size: {$meta['size']}",
				'text'=> $stream
			));
	//	}
echo "fdsfsfsfsdf55675  ";
			// .. then delete it, by calling the Connector plugin to delete the original stream after being processed
			$this-> master-> callback_Plugin("connector", "Delete", $meta);
			return true;
		} elseif($meta['extension'] == '917' || $meta['extension'] == '616') {
			// Just delete all 917 messages, there's nothing we can do with them.
			$this-> master-> callback_Plugin("connector", "Delete", $meta);
			return true;
		} else {
			$this-> master-> callback_Plugin("connector", "Delete", $meta);
			return false; // No record id, delete anyway, as a copy was created on the secondary FTP
		}
	}
}
