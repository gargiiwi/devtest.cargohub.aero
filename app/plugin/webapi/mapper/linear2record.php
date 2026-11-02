<?php
/**
 * Linear to Record mapper - Converts field values from a single-level array of texts (such as the output from a CSV row) into record fields (Meta or Xfields)
 */
class App_Plugin_WebAPI_Mapper_Linear2Record implements App_Plugin_WebAPI_mapper
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
	 * Scans META information, analizes actions according to configuration and meta and gets the plugin ready to receive data
	 * @param unknown $meta
	 */
	public function meta_prepare($meta)
	{
		$this-> CFG['field_p'] = $meta['fields'];
	}

	/**
	 * Maps incoming existing data according to the specified configuration
	 *
	 * @param array $data
	 */
	public function incoming($data)
	{
		$rec = array(
			'xfields'=> array()
		);

		foreach($this-> CFG['fields'] as $field=> $field_op) {
			/* $field_op parameter order:
			 * 0 - Type of mapping
			 * - 0 = directly to primary record meta
			 * - 1 = directly to an xfield (most text/number fields work)
			 * - 2 = run conversion function(s)
			 * 1
			 * - For mapping 0/1 - Meta or Xfield key
			 * - For mapping 2 - List of converter functions to apply
			 * 2 - Only for mode 2 (conversion):
			 * - 0 = maps to record meta
			 * - 1 = maps to xfield
			 * 3 - Only for mode 2 (conversion)
			 * - Field key (like 1)
			 * 4 - Only for mode 2 (conversion)
			 * - Extra parameter(s) for the conversion function(s) - Must be array if it contains texts which might include a comma, or it will be incorrectly split
			 */
			if(!isset($data[$this-> CFG['field_p'][$field]])) {
				$value = null;
				continue; // Skip non-existing values
			}
			$value = trim($data[$this-> CFG['field_p'][$field]]);
			// Skip cells with nothing in them
			if(strlen($value)==0)
				continue;

			switch($field_op[0]) {
				case 0: // This maps to the primary record meta
					$rec[$field_op[1]] = $value;
					break;
				case 1: // This maps to xfields
					$rec['xfields'][$field_op[1]] = $value;

					break;
				case 2: // An operation is performed before the field is prepared

					if(!is_array($field_op[1]))
						$field_op[1] = explode(',', $field_op[1]);
					if(isset($field_op[4]) && !is_array($field_op[4]))
						$field_op[4] = explode(',', $field_op[4]);
					foreach($field_op[1] as $fn_index=> $convert_fn)
						switch($convert_fn) {
							case 'totime': // Value is interpreted as unixtime using str2time before stored
								$value = strtotime($value);
								break;
							case 'tolower': // Value is converted to all lowercase characters
								$value = strtolower($value);
								break;
							case 'toupper': // Value is converted to all lowercase characters
								$value = strtoupper($value);
								break;
							case 'join': // Value is joined together with an already existing field
								$pre = $field_op[2] == 1? $rec['xfields'][$field_op[3]]: $rec[$field_op[3]];
								if(isset($field_op[4]))
									$pre.=$field_op[4][$fn_index];
								$value=$pre.$value;
								break;
							case 'array': // Value becomes part of an array. Parameter 3 contains the field where the array is created or updated
								// Important ! This must be the last converter, and this field not used into another operation, as the others won't know how to operate on an array
								// This only operates on Xfields, as the normal meta fields do not support arrays. Value [2] is ignored
								if(!isset($rec['xfields'][$field_op[3]]))
									$rec['xfields'][$field_op[3]] = array();
								$rec['xfields'][$field_op[3]][ $field_op[4][$fn_index] ] = $value;
								$value = null; // Otherwise it gets overwritten
								break;
							case 'explode': // Value is exploded into an array, using either comma or a specified delimiter - Must be the last converter, or the others won't know how to operate on an array
								if(isset($field_op[4]))
									$deli = $field_op[4][$fn_index];
								else
									$deli = ',';
								$value = explode($deli, $value);
								break;
							case 'choices': // Value is exploded then each entry becomes a key into an array where all values are "1" - Useful for the "SET" field type
								if(isset($field_op[4]))
									$deli = $field_op[4][$fn_index];
								else
									$deli = ',';
								$list = explode($deli, $value);
								$value = array();
								foreach($list as $entry)
									$value[$entry] = 1;
								break;
							// Special
							case 'ignore': // Value is ignored (not mapped, do nothing)
								$value = null;
								break;
						}

					if(!is_null($value) && isset($field_op[2])) {
						if($field_op[2] == 1)
							$rec['xfields'][$field_op[3]] = $value;
						else
							$rec[$field_op[3]] = $value;
					}
					break;
			}
		}
		return $rec;
	}

}