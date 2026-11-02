<?php
/**
 * CODEC for the ancieng EDIFACT format
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage WebAPI Plugins
 * @version 2.10
 */
class App_Plugin_WebAPI_codec_Edifact implements App_Plugin_WebAPI_codec
{
	private $CFG;

	/**
	 * Constructor receives configuration for this instance of the plugin
	 * @param array $CFG
	 */
	public function __construct($CFG)
	{
		$this-> CFG = $CFG;
	}

	/**
	 * Final output of decoded data
	 * @var array
	 */
	private $decoded;

	/**
	 * Decodes a CSV data stream into a bidimensional array
	 * @param unknown $data
	 */
	public function decode($data)
	{
		$this-> decoded = array();
		// This type of data doesn't have direct "fields", it's a hierarchic structure which needs to be analysed further by the mapper or function
		$data['meta']['structure'] = "hierarchy";

			// Replace all the + signs with space, as a continuous stream of non-space characters is not needed here
		$message = str_replace("+", " ", $data['stream']);
		// Split the string into chunks separated by '
		$chunks = explode("'", $message);
		// Cut the last one since it always ends with an '
		array_pop($chunks);

		$segment = array(); $last_tag = "+++"; $in_list = false; $list_element = array();
		// Phase 1 - Iterate through chunks
		foreach($chunks as $chnk) {
			$tag = substr($chnk, 0, 3);

			// When encountering new segment, save existing one and create a new one
			if($tag != $last_tag) {
				if(!empty($segment)) {
					$segment['tag'] = $last_tag;

					// Phase 2 - Decode segment
					$this-> segment_decode($segment, $data['meta']['extension'], $in_list);

					// Phase 3 - Build up data by understanding the segment's position and role (might depend on previous existing data)
					if($in_list)
						$this-> data_buildup($segment, $data['meta']['extension'], $list_element, true);
					else
						$this-> data_buildup($segment, $data['meta']['extension']);
				}
				// Reset $segment in preparation for new one
				$segment = array('content'=> array());
				$last_tag = $tag;

				// Special tag "UNS"
				if($tag == 'UNS') {
					// When value "D" is encountered, a list of elements follow
					if(substr($chnk, 4) == 'D') {
						$in_list = true;
						$this-> decoded['list'] = array();
					} elseif(substr($chnk, 4) == 'S') {
						// List is ended by value "S" in UNS
						$in_list = false;
						if(!empty($list_element))
							$this-> decoded['list'][] = $list_element;
					}
					continue;
				}
				// "CST" = Customs status of goods > Next element in list
				if($in_list && $tag == 'CST') {
					if(!empty($list_element))
						$this-> decoded['list'][] = $list_element;
					$list_element = array();
					continue;
				}
			}

			$segment['content'][] = substr($chnk, 4);
		}

		return array(
			'meta'=> $data['meta'],
			'contents'=> $this-> decoded,
			'stream'=> $data['stream'] // This "Decoder" saves the original stream, because it's not fully decoded, and might be re-decoded later when more information is available
		);
	}

	// Decodes the data inside the segment (when known)
	private function segment_decode(&$segment, $msg_type, $in_list = false)
	{
		// Note: The "Human readable" names might be commented out after the decoder understands this better
		switch($segment['tag']) {
			// UNB contains message Time and ID as the last two elements
			case 'UNB':
// 				$segment['name'] = "Message ID";
				$_split = explode(' ', $segment['content'][0]);
				$segment['dec'] = array();
				$segment['dec']['id'] = array_pop($_split);
				$segment['dec']['datetime'] = DateTime::createFromFormat('ymd:Hi', array_pop($_split));
				unset($segment['content']); // Decoded, no longer relevant
				break;
			// UNH seems to contain a reference to something else? ... as well as the $msg_type itself
			case 'UNH':
// 				$segment['name'] = "Message type and reference";
				$_split = explode(' ', $segment['content'][0]);
				$segment['dec'] = array();
				$segment['dec']['ref'] = array_shift($_split);

				$known_types = array(
					'140'=> "nondischarged",
					'606'=> "arrived",
					'609'=> "cancelled",
					'628'=> "mrnalloc",
					'629'=> "released",
					'645'=> "writeoff",
					'660'=> "inspection",
					// Errors ?
					'616'=> "xerror",
					'917'=> "xerror"
				);
				list($_type, $_unused) = explode(':', $_split[0]);
				$segment['dec']['type'] = $known_types[$_type];
				unset($segment['content']); // Decoded, no longer relevant
				break;
			// Contain Transit MRN document number for certain message types
			case 'BGM':
// 				$segment['name'] = "Message Header"; // Might contain document number (Transit MRN)
				switch($msg_type) {
					case '628':
					case '606':
					case '609':
					case '660':
						$_split = explode(' ', trim($segment['content'][0]));
						$segment['dec'] = array(
							'MRN'=> array_shift($_split)
						);
						break;
					case '629':
						$_split = explode(' ', trim($segment['content'][0]));
						$segment['dec'] = array(
							'MRN'=> array_pop($_split)
						);
						break;
					case '140':
					case '645':
						$segment['dec'] = array(
							'MRN'=> trim($segment['content'][0])
						);
				}
				unset($segment['content']); // Decoded, no longer relevant
				break;
			// Location codes
			case 'LOC':
// 				$segment['name'] = "Place/Location identification";
				$segment['dec'] = array();
				foreach($segment['content'] as $element) {
					list($_key, $_val) = explode(' ', $element, 2);
					// Attempt to discover if value is composite. If yes, explode
					$_val = trim($_val, ' :');
					if(strpos($_val, ":::") !== false)
						$_val = explode(':::', $_val);
					// Decode the shitty obfuscated keys
					switch($_key) {
						case '2':
						// case '14': // Agreed location of goods
						// case '20': // Arrival agreed location
						case '22':
							$segment['dec']['present_loc'] = $_val; // Presentation customs office (Arrival?)
							break;
						case '9':
							$segment['dec']['load_loc'] = $_val; // Place of loading
							break;
						case '35':
							$segment['dec']['orig_country'] = $_val; // Country of dispatch, export
							break;
						case '36':
							$segment['dec']['dest_country'] = $_val; // Country of destination
							break;
						case '45':
							$segment['dec']['dest_loc'] = $_val; // Destination customs office
							break;
						case '50':
							$segment['dec']['transit_loc'] = $_val; // Transit customs office
							break;
						case '60':
							$segment['dec']['arrival_loc'] = $_val; // Arrival notification place
							break;
						// case '84': Arrival authorised location
						case '91':
							$segment['dec']['declar_loc'] = $_val; // Place of declaration
							break;
						case '118':
							$segment['dec']['event_loc'] = $_val; // This is in reference to current message and it might mean different things
							break;
						case '168':
							$segment['dec']['customs_office'] = $_val;
							break;
						// Other seen values: 9, 91
						default:
							$segment['dec']['unk_loc_'.$_key] = $_val;
					}
				}
				unset($segment['content']); // Decoded, no longer relevant
				break;
			// Contains dates for certain elements
			case 'DTM':
// 				$segment['name'] = "Date/Time/Period";
				$segment['dec'] = array();
				foreach($segment['content'] as $element) {
					list($_key, $_date) = explode(':', $element);
					switch($_key) {
						case '9': // Control date
							$segment['dec']['ctrl_date'] = DateTime::createFromFormat('YmdHi', $_date.'0000');
							break;
						case '132': // Arrival notification date
						case '178':
							$segment['dec']['arrival_date'] = DateTime::createFromFormat('YmdHi', $_date.'0000');
							break;
						case '137': // Declaration date
							$segment['dec']['declar_date'] = DateTime::createFromFormat('YmdHi', $_date.'0000');
							break;
						case '148': // Acceptance date
							$segment['dec']['accept_date'] = DateTime::createFromFormat('YmdHi', $_date.'0000');
							break;
						case '182': // Issuing date
							$segment['dec']['mrn_date'] = DateTime::createFromFormat('YmdHi', $_date.'0000');
							break;
						case '184': // Date of control notification
							$segment['dec']['ctrl_date'] = DateTime::createFromFormat('YmdHi', $_date.'0000');
							break;
// 						case '153':
// 							$segment['dec']['ndis_date'] = DateTime::createFromFormat('YmdHi', $_date.'0000');
// 							break;
						case '193':
							$segment['dec']['cancel_date'] = DateTime::createFromFormat('YmdHi', $_date.'0000');
							break;
						case '204':
							$segment['dec']['discharge_date'] = DateTime::createFromFormat('YmdHi', $_date.'0000');
							break;
						case '268':
							$segment['dec']['limit_date'] = DateTime::createFromFormat('YmdHi', $_date.'0000');
							break;
						default:
							$segment['dec']['unk_date_'.$_key] = DateTime::createFromFormat('YmdHi', $_date.'0000');
					}
				}
				unset($segment['content']); // Decoded, no longer relevant
				break;
			// No clue what this is, seems to be just numbers
			case 'GIS':
// 				$segment['name'] = "Processing Indicatiors";
				foreach($segment['content'] as $element) {
					list($_key, $_code) = explode(':', $element);
					if($_key == 'A57')
						$segment['dec']['proc_A57'] = true;
					else
					switch($_code) {
						case '62': // NCTS return copy
							$segment['dec']['ncts_ret_copy'] = true;
							break;
						case '63': // Continue unloading
							$segment['dec']['cont_unload'] = true;
							break;
						case '70': // Cancellation decision
							$segment['dec']['cancel_dec'] = true;
							break;
						case '109': // Containerised indicator
							$segment['dec']['packing'] = 'container';
							break;
						case '117': // Cancellation initiated by customs
							$segment['dec']['cancel_start'] = true;
							break;
						case '119': // Simplified procedure
							$segment['dec']['simple_proc'] = true;
							break;
						default:
							$segment['dec']['unk_proc_'.$_key] = (int)$_code;
					}
				}
				unset($segment['content']); // Decoded, no longer relevant
				break;
			case 'FII':
// 				$segment['name'] = "Financial Institution Information";
				break;
			// Measurements - Might indicate weight or size
			case 'MEA':
// 				$segment['name'] = "Measurements";
				$segment['dec'] = array();
				foreach($segment['content'] as $element) {
					switch(substr($element, 0, 3)) {
						case 'WT ': // Weight
							list($_key, $_value) = explode(':', substr($element, 6));
							switch(substr($element, 3, 3)) {
								case 'AAA':
									$segment['dec']['wt_gross'] = (float)$_value;
									break;
								case 'AAB':
									$segment['dec']['wt_net'] = (float)$_value;
									break;
								case 'AAD':
									$segment['dec']['wt_total'] = (float)$_value;
									break;
							}
							break;
						// At the moment just weight is supported
					}
				}
				unset($segment['content']); // Decoded, no longer relevant
				break;
			case 'EQD':
// 				$segment['name'] = "Equipment Details";
				break;
			// Seal number ??
			case 'SEL':
// 				$segment['name'] = "Seal Number";
				$_split = explode(':', $segment['content'][0]);
				$segment['dec'] = array_pop($_split);
				unset($segment['content']); // Decoded, no longer relevant
				break;
			// Freetext - These are actually extended codes placed into a "common" tag
			case 'FTX':
// 				$segment['name'] = "Free Text";
				$segment['dec'] = array();
				if(is_array($segment['content'])) {
					foreach($segment['content'] as $line) {
						switch(substr($line, 0, 3)) {
							case 'AAA': // Goods description (only appears in CST)
								$segment['dec']['goods'] = trim(substr($line, 3));
								break;
							case 'ABL': // Binding itinerary - Not used
								break;
							case 'ABQ': // No release motivation
								$segment['dec']['norel_reason'] = trim(substr($line, 3));
								break;
							case 'ABV': // Result of control
								$segment['dec']['ctrl_result'] = trim(substr($line, 3));
								break;
							case 'ACB': // Fucking conflict of codes...
								if($in_list) { // Additional information
									$fragment = trim(substr($line, 3));
									// This is a hack. A monetary value is injected in the first item in the list...
									if(substr($fragment, 0, 3) == 'CAL') {
										$_cal = explode('EUR', trim(substr($fragment, 3)));
										$this-> decoded['financial'] = array(
											'amount'=> (float)$_cal[0],
											'account'=> $_cal[1]
										);
									}
// 									else
// 										$segment['dec']['unkn'] = trim($fragment);
								} else // Dialog language at departure
									$segment['dec']['lang_dep'] = trim(substr($line, 3));
								break;
							case 'ABY': // Cancellation justification
							case 'ACD': // Cancellation reason
								$segment['dec']['cancel_reason'] = trim(substr($line, 3));
								break;
							case 'ACP': // Representative capacity
								$segment['dec']['repres'] = trim(substr($line, 3));
								break;
							case 'ADO': // Control result code
								$segment['dec']['ctrl_code'] = trim(substr($line, 3));
								$cc_list = array(
									'A1'=>"Satisfactory",
									'A2'=>"Considered Satisfactory",
									'A3'=>"Simplified Procedure",
									'A4'=>"No action on minor regularity",
									'A5'=>"Charges collected",
									'B1'=>"Not satisfactory"
								);
								$segment['dec']['ctrl_text'] = $cc_list[$segment['dec']['ctrl_code']];
								break;
							case 'ALL': // NCTS document language
								$segment['dec']['lang_ncts'] = trim(substr($line, 3));
								break;

							default: // Not recognized, this might be just a text
								if(!isset($segment['dec']['text']))
									$segment['dec']['text'] = "";
								$segment['dec']['text'].=$line.' ';
						}
					}
					if(isset($segment['dec']['text']))
						$segment['dec']['text'] = trim($segment['dec']['text']);
				}
				unset($segment['content']); // Decoded, no longer relevant
				break;
			// References to other stuff ?
			case 'RFF':
// 				$segment['name'] = "Reference";
				$segment['dec'] = array();
				foreach($segment['content'] as $element) {
					list($_key, $_val) = explode(':', $element);
					switch($_key) {
						case 'AAQ': // Container number
							$segment['dec']['container_no'] = $_val;
							break;
						case 'ABE': // Reference number
							$segment['dec']['number'] = $_val;
							break;
						case 'ABL': // Guarantee type
/* 							$gt_list = array(
								'0'=>"Guarantee waiver",
								'1'=>"Comprehensive Guarantee",
								'2'=>"Individual Guarantee (by Guarantor)",
								'3'=>"Cash Deposit Guarantee",
								'4'=>"Individual guarantee in the form of voucher",
								'6'=>"Guarantee waived",
								'A'=>"Guarantee waived by agreement",
								'7'=>"Guarantee not required for the journey between OoDep and OoTra",
								'9'=>"Individual Guarantee with multiple usage"
							);
 */							$segment['dec']['guarantee'] = $_val; // $gt_list[$_val];

							// Note: RFF "ABL" might be followed by "PAC", with value 1
							break;
						case 'ABO': // Original message id
							$segment['dec']['msg_original'] = $_val;
							break;
						case 'AIV': // En route event
							$segment['dec']['en_route_loc'] = $_val;
							break;
						case 'LAN': // Authorisation ID
							$segment['dec']['auth_id'] = $_val;
							break;
						default:
							$segment['dec']['unk_ref_'.$_key] = $_val;
					}
				}
				unset($segment['content']); // Decoded, no longer relevant
				break;
			// Package information
			case 'PAC':
// 				$segment['name'] = "Package Content";
				$segment['dec'] = array();
				foreach($segment['content'] as $element) {
					$_code = trim(substr($element, 0, 3));
					switch($_code) {
						case '1': // No idea what this is, not in TISA05, but it seems last value to represent the package identifier; "7180" however is a mistery
							$segment['dec']['unknown_ref'] = explode(':', trim(substr($element, 3)));
							break;
						case '6': // Package of goods (type and quantity)
							list($_kind,$_pieces) = explode(':', trim(substr($element, 3)));
							$segment['dec']['kind'] = $_kind; // List 17 "Kind of Packages"; CT for example = Carton Box
							$segment['dec']['pieces'] = (int)$_pieces;
							break;
						default:
							$segment['dec']['unk_pac_'.$_code] = trim(substr($element, 3));
					}
				}
 				unset($segment['content']); // Decoded, no longer relevant
				break;
			case 'PCI':
// 				$segment['name'] = "Package ID";
				list($_key, $_val) = explode(' ', $segment['content'][0], 2);
				switch($_key) {
					case '6': // Kind of packages
						$segment['dec']['type'] = $_val;
						break;
					case '19':
						$segment['dec']['non_eu_marks'] = true;
						break;
					case '18': // Endorsements
					case '23':
						$segment['dec']['endorsment'] = $_val;
						break;
					case '21':
						$segment['dec']['seals'] = $_val;
						break;
					case '28': // Marks on the package
						$segment['dec']['marks'] = $_val;
						break;
					case '30': // Container number
						$segment['dec']['container_no'] = $_val;
						break;
					default:
						$segment['dec']['unk_pack_'.$_key] = $_val;
				}
				unset($segment['content']); // Decoded, no longer relevant
				break;
			// Possibly truck number or license plate
			case 'TDT':
// 				$segment['name'] = "Details of Transport";
				$segment['dec'] = array();
				foreach($segment['content'] as $element) {
					list($_key, $_val) = explode(' ', $element, 2);
					// Attempt to discover if value is composite. If yes, explode
					$_val = trim($_val, ' :');
					if(strpos($_val, ":") !== false)
						$_val = explode(':', $_val);
					// Decode the shitty obfuscated keys
					switch($_key) {
						case '1': // Transport mode
/* 							$tm_list = array(
								'10'=>"Sea transport",
								'12'=>"Railway wagon on sea-going vessel",
								'16'=>"Powered road vehicle on sea-going vessel",
								'17'=>"Trailer or semi-trailer on sea-going vessel",
								'18'=>"Inland waterway vessel on sea -going vessel",
								'20'=>"Rail transport",
								'23'=>"Road vehicle on railwagon",
								'30'=>"Road transport",
								'40'=>"Air transport",
								'50'=>"Postal consignment",
								'70'=>"Fixed transport installations",
								'80'=>"Inland waterway transport",
								'90'=>"Own propulsion"
							);
 */							$segment['dec']['mode'] = $_val; // $tm_list[$_val]
							break;

						case '11': // Identity of means of transport crossing border
							$segment['dec']['identity'] = $_val;
							break;
						case '12': // Identity of means of transport at departure
							$segment['dec']['identity'] = $_val;
							break;
						// Other seen values: 1
						default:
							$segment['dec']['unk_tdt_'.$_key] = $_val;
					}
				}
				unset($segment['content']); // Decoded, no longer relevant
				break;
			case 'TPL':
// 				$segment['name'] = "Transport Placement";
				break;
			// Names and Addresses of those involved (Non-standard text, but may have some logic behind it)
			case 'NAD':
// 				$segment['name'] = "Name and Address";
				$segment['dec'] = array();
				foreach($segment['content'] as $element) {
					list($_key, $_val) = explode(' ', $element, 2);
					switch($_key) {
						case 'AF': // Principal trader
							$segment['dec']['principal'] = trim($_val);
							break;
						case 'CZ': // Consignor trader
							$segment['dec']['consignor'] = trim($_val);
							break;
						case 'AH': // Representative
							$segment['dec']['repres'] = trim($_val);
							break;
						case 'CN': // Consignee trader
							$segment['dec']['consignee'] = trim($_val);
							break;
						case 'CPD': // Destination trader
							$segment['dec']['dest'] = trim($_val);
							break;
						case 'EI': // Controlled by
							$segment['dec']['control'] = trim($_val);
							break;
						case 'TD': // Authorised consignnee
							$segment['dec']['auth_consig'] = trim($_val);
							break;
						case 'SX': // Guarantor
							$segment['dec']['guarant'] = trim($_val);
							break;
						default:
							$segment['dec']['unk_name_'.$_key] = trim($_val);
					}
				}
				unset($segment['content']); // Decoded, no longer relevant
				break;
			case 'DOC':
// 				$segment['name'] = "Document/Message Details";
				break;
			case 'TOD':
// 				$segment['name'] = "Terms of Delivery or Transport";
				break;
			case 'GIR': // In-List only
// 				$segment['name'] = "Related Identification Numbers";
				break;
			case 'CNT': // Control Total - Contains some totals of stuff
// 				$segment['name'] = "Control Total";
				$segment['dec'] = array();
				foreach($segment['content'] as $element) {
					list($_key, $_val) = explode(':', $element, 2);
					switch($_key) {
						case '5': // Total number of items
							$segment['dec']['items'] = (int)$_val;
							break;
						case '11': // Total number of packages
							$segment['dec']['pieces'] = (int)$_val;
							break;
						case '13': // Number of loading lists
							$segment['dec']['lists'] = (int)$_val;
							break;
						case '16': // Number of seals
							$segment['dec']['seals'] = (int)$_val;
							break;
					}
				}
				unset($segment['content']); // Decoded, no longer relevant
				break;
			// Not useful
			case 'UNT':
// 				$segment['name'] = "Message Trailer"; // Number of segments in the message
				break;
			case 'UNZ';
// 				$segment['name'] = "Interchange Trailer"; // Interchange control reference or count
				break;
			default:
// 				$segment['name'] = "Unknown tag: {$segment['tag']}";
		}

	}

	/**
	 * Constructs final output data from the segments
	 * @param array $segment
	 * @param string $msg_type (Should be a number)
	 * @param array $list_element
	 */
	private function data_buildup($segment, $msg_type, &$store_to = null, $in_list = false)
	{
		if(is_null($store_to))
			$store_to = &$this-> decoded;

		switch($segment['tag']) {
			case 'UNB':
				$store_to['id'] = $segment['dec']['id'];
				$store_to['datetime'] = $segment['dec']['datetime'];
				break;
			case 'UNH':
				$store_to['type'] = $segment['dec']['type'];
				$store_to['reference_to'] = $segment['dec']['ref'];
				break;
			case 'BGM':
				switch($msg_type) {
					case '140':
					case '606':
					case '609':
					case '628':
					case '629':
					case '645':
					case '660':
						$store_to['MRN'] = $segment['dec']['MRN'];
						break;
				}
				break;
			case 'LOC':
				$store_to['locations'] = $segment['dec'];
				break;
			case 'DTM':
				$store_to['dates'] = $segment['dec'];
				break;
			case 'GIS':
				$store_to['indicators'] = $segment['dec'];
				break;
			case 'MEA':
				if(!isset($store_to['measurements']))
					$store_to['measurements'] = array();
				$store_to['measurements'] = array_merge($store_to['measurements'], $segment['dec']);
				break;
			case 'FTX':
				if($in_list) {
					$store_to = array_merge($store_to, $segment['dec']);
				} else {
					if(is_array($segment['dec']))
						$store_to['extra'] = $segment['dec'];
					else {
						if(!isset($store_to['text']))
							$store_to['text'] = array();
						$store_to['text'][] = $segment['dec']['text'];
					}
				}
				break;
			case 'NAD':
				$store_to['names'] = $segment['dec'];
				break;
			case 'RFF':
				$store_to['references'] = $segment['dec'];
				break;
			case 'PAC':
			case 'PCI':
				if(!isset($store_to['package']))
					$store_to['package'] = array();
				$store_to['package']= array_merge($store_to['package'], $segment['dec']);
				break;
			case 'TDT':
				$store_to['transport'] = $segment['dec'];
				break;
			case 'CNT':
				$store_to['totals'] = $segment['dec'];
				break;
		}
	}

}
