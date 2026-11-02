<?php
/**
 * WebAPI Function plugin for function "RecImport"
*
* @author Wavetrex Infinity <wavetrex@gmail.com>
* @package CargoConnect
* @subpackage WebAPI Plugins
* @version 2.10
*/
class App_Plugin_WebAPI_fn_RecImport implements App_Plugin_WebAPI_fn
{
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

// 		dd($this-> rt);
// 		dd($this-> CFG);
	}

// 	protected function process_Uploaded_File($pathinfo, $contents)
// 	{
// 		dd($pathinfo);
// 		dd($contents);

// 		echo "<h3>".$pathinfo['filename']."</h3>";

// 		// Some extensions are natively recognized and decoded (JSON, XML, YAML) - Others require import plugins to recognize weird formats
// 		switch($pathinfo['extension']) {
// 			case 'xml':
// 				$contents = json_encode(new SimpleXMLElement($contents));
// 				// bleed
// 			case 'js':
// 			case 'json':
// 				$data = @json_decode($contents, true);
// 				break;
// 		}

// 		echo "<pre>";
// 		var_dump($data);
// 		echo "</pre>";
// 	}

	/**
	 * Executes import from the data
	 * @param array $data
	 * @param int $org_id Organization ID
	 * @param int $user_id Execute the import as this user !?
	 */
	public function execute($data, $org_id, $user_id = null)
	{
		// Should the data be checked for updates?
		// ...If yes, the records table need to be checked first if a matching record is found
		// ...If no, a new record is simply created with the specified data
		if(isset($this-> CFG['update'])) {
			$updated = false;
			// Attempt to read records of the specified type, using a custom filter
			$update_cfg = $this-> CFG['update'];

			// To search for xfields, extra joins are necessary
			$wtr = array('xfields', 'org_specific', 'org_id'=> $org_id);
			foreach($update_cfg['xfmatch'] as $fkey=> $fcfg) {
				if(is_array($fcfg)) {
					// Complex (array) data in the xfield, must pre-process before searching
					$fs = (int)$fcfg[0];
					switch($fcfg[1]) {
						case 'join': // The field values must be joined - $fcfg[2] contains the glue character
							$match_value = implode(isset($fcfg[2])? $fcfg[2]: ',', $data['xfields'][$fkey]);
							break;
					}
				} else {
					$fs = (int)$fcfg;
					$match_value = $data['xfields'][$fkey];
				}

				// Check if QuickIndex exists on the fields that are being searched (xfmatch). If yes, locate records via quickindex. If not, perform a join
				$quickindex = @$this-> rt['settings']['xf_cache']['quickindex'][$fkey];
				$qi_recs = array();
				$join_fields = array();
				if($quickindex > 0) {
					// This should contain the field ID, for locating directly in the index table. Run the index search query and grab the ID's
					$qi_q=QueryFactory::select()
					-> table(MVC_Core::$db-> tab['rqi'], array('rec_id'))
					-> where("`fk_id` = ?", $quickindex)
					-> where("`value` = ?", $match_value);
					$res = MVC_Core::$db-> query($qi_q);
					if(!empty($res) && count($res) > 0) {
						// Found some records, grab their ID's and use that in get_List condition (should increase the speed a lot compared to join/where in the fields table)
						foreach($res as $rec_qi)
							$qi_recs[] = (int)$rec_qi['rec_id'];
					}
				} else {
					// Quickindex not present on the requested fields, perform a join
					$wtr['custom'][] = array(
						'tab'=> MVC_Core::$db-> table('ixf', true, "xf_{$fkey}"),
						'fields'=> false,
						'on'=> "`xf_{$fkey}`.issue_id = `iss`.id AND `xf_{$fkey}`.fk_id = ".$this-> rt['xfield_def'][$fkey]['id']
					);
					$join_fields[$fkey] = $fs;
				}
			}

			// Test if get_List is necessary (something matched, or a field has to be searched). If not, proceed to Insert (skip update)
			if(empty($qi_recs) && empty($join_fields))
				goto _JP_after_update;

			$records = $this-> rec-> get_List($this-> rt['base_id'], $wtr, function(QueryFactory &$q) use($data, $qi_recs, $join_fields) {
				// Add Quickindex conditions
				if(!empty($qi_recs))
					$q-> where("`id` IN (?)", $qi_recs);

				// Add conditions for joined matched fields
				if(!empty($join_fields)) {
					// Note: This import search only supports exact matches ! The mapper function must take care of all the necessary conversions before the data is searched, updated or inserted
					foreach($join_fields as $fkey=> $fs)
						$q-> where("`xf_{$fkey}`.".($fs? "value": "int_value")." = ?", $match_value);
				}
			});

			if(count($records)==1) {
				$rec = current($records);
				// Found an exact match !
				// A. Merge
				if(!empty($update_cfg['xfmerge'])) {
					// Merging might consist of inserting new fields or Updating existing ones
					$insert_part = array();
					$update_part = array();
					foreach($update_cfg['xfmerge'] as $fkey=> $fs) {
						// Skip search/update on values which don't exist in the data
						if(!isset($data['xfields'][$fkey]) || (is_array($data['xfields'][$fkey])? count($data['xfields'][$fkey]): strlen($data['xfields'][$fkey]))==0)
							continue;

						if(!isset($rec['xfields'][$fkey])) {
							// No previous value found - Update by simply inserting a new field record
							switch($this-> rt['xfield_def'][$fkey]['t']) {
								case 'set':
									$new_options = array_filter(array_keys($data['xfields'][$fkey])); // Don't allow empty values
									if(!empty($new_options))
										$insert_part[$fkey] = array("value"=> json_encode($new_options));
									break;
							}
						} else {
							// Previous value found - Data must be merged. How - depends on field type
							switch($this-> rt['xfield_def'][$fkey]['t']) {
								case 'set':
									// Find if value in the $data is already part of the set - If yes, nothing to do. If no, the set must be updated
									$options = array(); // Existing options
									foreach($rec['xfields'][$fkey] as $option)
										$options[] = $option['key'];
									$new_options = array_filter(array_keys($data['xfields'][$fkey])); // Don't allow empty values
									$merged_options = array_unique(array_merge($options, $new_options));

									if($merged_options != $options)
										$update_part[$fkey] = array('value'=> json_encode($merged_options));
									else
										continue; // Nothign to do, no values to update, move to next field

									break;
							}
						}
					}

					if(!empty($insert_part)) {
						$this-> rec-> create_XFields($rec['id'], $insert_part);
						$updated = true;
					}
					if(!empty($update_part)) {
						$this-> rec-> delete_XFields($rec['id'], array_keys($update_part));
						$this-> rec-> create_XFields($rec['id'], $update_part);
						$updated = true;
					}

					// Check existing data and field type
				}

				// B. Overwrite - Delete specified fields from the record and reinsert them with new data (Field type important!)
				if(!empty($update_cfg['xfoverw'])) {

					$overwrite_part = array();
					foreach($update_cfg['xfoverw'] as $fkey=> $fs) {
						// Skip search/update on values which don't exist in the data (in this case it means that data is not removed from the record !)
						if(!isset($data['xfields'][$fkey]) || strlen($data['xfields'][$fkey])==0)
							continue;

						$fs = $fs? "value": "int_value";
						switch($this-> rt['xfield_def'][$fkey]['t']) {
							case 'text':
							case 'number':
							case 'enum':
								$overwrite_part[$fkey] = array($fs=> $data['xfields'][$fkey]);
								break;
							case 'date':
								$overwrite_part[$fkey] = array($fs=> strtotime($data['xfields'][$fkey]));
								break;
							case 'set': // Set always stores in "value", $fs is irrelevant here
								$ovw_values = array_filter(array_keys($data['xfields'][$fkey]));
								if(!empty($ovw_values))
									$overwrite_part[$fkey] = array('value'=> json_encode($ovw_values));
								break;
								// These are PLUGINS, it should not be handled this way !
							case 'awb':
								$overwrite_part[$fkey] = array($fs=> $data['xfields'][$fkey]);
								break;
								// TODO: Resolve the "Airport" plugin, it will probably be really necessary !
						}
					}

					if(!empty($overwrite_part)) {
						$this-> rec-> delete_XFields($rec['id'], array_keys($overwrite_part));
						$this-> rec-> create_XFields($rec['id'], $overwrite_part);
						$updated = true;
					}
				}

			} elseif(count($records)>1) {
				// Found multiple matches, unable to do anything - Log and Report the situation
				if(!empty($this-> master))
					$log_iid = $this-> master-> log_Entry("execute", $org_id, $user_id, "warning", array(
						'label'=> __METHOD__,
						'operation'=> "update",
						'message'=> "Multiple matches found for data entry",
						'data'=> $data
					));

				return MVC_Core::$debug-> dump(sprintf("Multiple records found with matching search data - Log ID: %d", $log_iid), __METHOD__, null, 'warning');
			} else {
				// No matches, carry on to inserting
			}
		}

		if(isset($updated)) {
			// Was something actually updated?
			if($updated)
				$this-> master-> plugin_report['function']['updated']++;
			return true; // Success: Update; Move on to next record
		}

		_JP_after_update:

		if(empty($this-> CFG['create']['group_id']))
			return MVC_Core::$debug-> dump("Data Group ID not present in the import function config. Unable to continue.", __METHOD__, null, 'error');

		// Proceed to record creation based on the $data
		$xfields = $data['xfields']; unset($data['xfields']);

		// Add some meta information:
		$data['master_org_id'] = $data['submit_org_id'] = $org_id;
		$data['shared_org_ids'] = null;
		$data['group_id'] = (int)$this-> CFG['create']['group_id'];
		$dep_ids = isset($this-> CFG['create']['dep_ids'])? $this-> CFG['create']['dep_ids']: null;
		$orgdata = array(
			'linktime'=> $data['ctime'],
			'process'=> "webapi",
			'status_key'=> "api" // Record created via WebAPI
		);

		$ric = $this-> rec-> create($this-> rt['base_id'], $data, $xfields, false, false); // 2nd "false" - Skip checking for mandatory fields (since this is not a human form submit)
		if($ric===false || is_array($ric)) {
			// there is a problem with some xfields - Log the error and skip the record
			if(is_array($ric))
				foreach($ric as $ic_err) {
					if(isset($ic_err['plugin_err']))
						// plugin field error
						$log_iid = $this-> master-> log_Entry("execute", $org_id, $user_id, "warning", array(
							'label'=> __METHOD__,
							'operation'=> "create",
							'message'=> "Field '{$ic_err['fn']}' error: {$ic_err['plugin_err']}",
							'data'=> $xfields
						));
					else
						// standard field error
						$log_iid = $this-> master-> log_Entry("execute", $org_id, $user_id, "warning", array(
							'label'=> __METHOD__,
							'operation'=> "create",
							'message'=> "Field '{$ic_err['fn']}' error: ".__('issue.'.$ic_err['err'], true),
							'data'=> $xfields
						));
				}
			else
				$log_iid = $this-> master-> log_Entry("execute", $org_id, $user_id, "warning", array(
					'label'=> __METHOD__,
					'operation'=> "create",
					'message'=> "Error while attempting to create record",
					'data'=> $data
				));
			// Function failed, but the import continues
			return MVC_Core::$debug-> dump(sprintf("Error(s) encountered while attempting to create record - Log ID: %d", $log_iid), __METHOD__, null, 'warning');
		}
		$this-> rec-> org_Add($ric, $org_id, $dep_ids, $orgdata, null, true, false, 'webapi'); // No checks - Avoid unnecesary queries on data which is prepared in advance ( like import settings )
		$this-> rec-> ist_Counter_Update($this-> rt['base_id'], 1);
		$this-> master-> plugin_report['function']['created']++;

		return true; // Success "create"
	}

}
