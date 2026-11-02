<?php
/**
 * "Records" (issues) related data access and input/output processing
 * -- Stores the most recently read record (single item) as a temporary cache
 * -- Contains methods for organization-specific part of the record (org_*)
 * -- Contains methods for record-type specific data (ist_*) -- TODO: Move these outside since only ist_Read is used inside, to declutter this class
 * -- Contains methods to handler record field definition (xfdef_*) --- TODO: Move these outside since not called from within other parts of this mega-class
 * -- Contains methods related to record history (history_*) -- TODO: Move history related methods into a secondary class
 * -- Contains methods for user-to-record markers (marker_*)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage DataModels
 * @version 2021.02.17 
 */
class App_Model_Issue extends MVC_Model
{ 
    const sMISSING = "(not set)";   
            
    /**   
     * Statuses (translated)    
     * @var array     
     */     
    public $statuses = array();  
            
    /**
     * Priorities (translated)     
     * @var array 
     */
    public $priorities = array();

    /**
     * Prepare data sets
     */
    public function __construct()
    {
        // load language specific data
        MVC_NLS::load_Groups('issue');
        $this-> statuses = MVC_NLS::get_Object("issue.data.statuses", true);
        $this-> priorities = MVC_NLS::get_Object("issue.data.priorities", true);
    }

    /**
     * DonReY Template system, initialized on first use - Used by the "cache_field" system
     * -- and possibly in the future by other functions
     * @var Drytemplate
     */
    private static $dry_tpl = null;

    /**
     * Small helper used to translate a status and priority key into the human-readable version
     * @param string $fn
     * @param string $key
     */
    public function helper_Translate_StatPrio($fn, $key)
    {
        if(empty($key))
            return self::sMISSING;

        switch($fn) {
            case 'status':
                return isset($this-> statuses[$key])? $this-> statuses[$key]: self::sMISSING;
                break;
            case 'priority':
                return isset($this-> priorities[$key])? $this-> priorities[$key]: self::sMISSING;
                break;
            default:
                return "(unsupported)";
        }
    }

    /**
     * Resets the issue cache, so next read is straight from the database
     * @param array $what_to_reset
     */
    public function cache_Reset($what_to_reset = array("issue", "ist"))
    {
        if(in_array("issue", $what_to_reset))
            $this-> issue = null;
        if(in_array("ist", $what_to_reset))
            $this-> ist_def = null;
    }

//----- Issue Primary CRUD -----//

    /**
     * Loaded "Field" plugins
     * @var array
     */
    private $field_plugins = array();
    protected $chart_config; //IWI chart for quality event module 07112025

    /**
     * Creates or returns a field plugin (singleton)
     * @param string $name
     * @param mixed $params Parameters to be sent to the constructor
     *
     * @return App_Plugin_Field
     */
    private function &field_Plugin($name, $params = null)
    {
        if(!isset($this-> field_plugins[$name]))
            $this-> field_plugins[$name] = MVC_Core::factory_Plugin("field", $name, $params);
        return $this-> field_plugins[$name];
    }

    /**
     * Creates or returns a record plugin (singleton)
     * @param string $key
     * @var App_Plugin_Record
     */
    public $rec_plugin;

    private function &record_Plugin($key, $params = null)
    {
        if(empty($this-> rec_plugin)) {
            $params['rec'] = &$this;
            $this-> rec_plugin = MVC_Core::factory_Plugin("record", $key, $params);
        }
        return $this-> rec_plugin;
    }

    /**
     * Stored last read issue data (1 entry)
     * TODO: This should be renamed in "localcache" or something like that (And check every part of the applicaiton which might use it)
     * TODO: The local cache should support partial updating on included components ( record type data, attached data, organization data, etc... to avoid clearing cache/re-reading everything)
     * @var array
     */
    public $issue;

    /**
     * Generates an unique tracking ID which can be easily dictated through a phone call or written on paper, without the confusion, in the format {3}-{3}-{4}
     * -- Possibly confusing characters: 1-I, 0-O, C-S, F-V, F-E ( eliminating F )
     */
    public function generate_TrackID()
    {
        // ----- vowels ---- consonants ------ numbers -- (some are removed because they may cause confusion when hand written)
        // Last one in grops stays: 0COQ / I1 / S5 / FE / 7Z / B8
        $chars = "AEUY"."DGHJKLMNPQRTVWXZ"."12345689"; $l = strlen($chars)-1;
        // Key positioning score (including those that do not get generated)
        // 1: 1, 2, Q (O0I)
        // 2: 3, W, E (F)
        // 3: A, S, Z (7)
        // 4: 4, R, 5 (S)
        // 5: X, D (C)
        // 6: 6, T
        // 7: G, Y, V
        // 8: 8, 9, U (B)
        // 9: H, N, J
        // 10: K, M
        // 11: P, L

        // First group ( 3 char ) score x 500
        // Second group ( 3 char ) score x 40
        // Therd group ( 4 char ) score x 3
        // Some manually calculated test scores:
        //             AN9-1T5-GKEM = (3+9+8)*500 + (1+6+4)*40 + (7+10+2+10)x3 = 10527
        // (typos)     AN9-ITS-GLEM = (3+9+8)*500 + (1+6+4)*40 + (7+11+2+10)x3 = 10530 (99.97% match)
        // (inversion) A9N-1T5-GKEN = 10524 (99.97% match)
        // (missing)   AN9-1T-GKEM = 10000 + 280 + 87 = (98.5% match)
        // (different) E6U-7V7-V7P8 = (2+6+8)*500 + (3+7+3)*40 + (7+3+11+8)x3 = 8607 (81.86% match)
        // (random bs) 8C1-L9A-8OCS = (8+5+1)*500 + (11+8+3)*40 + (8+1+5+4)x3 = 7934 (92.2% match to "different")

        while(1) {
            $tid = '';
            for($c = 0; $c< 12; $c++) {
                if($c==3 || $c==7)
                    $tid.='-';
                else
                    $tid.=$chars[mt_rand(0, $l)];
            }
            // discover some "bad" 3-letter words in the $tid, and if they are present, restart the process
            // TODO: Support custom word list, to addapt to other languages bad words
            // Note that not all alphanumeric characters are used, and since I, O, C, F, S and 0 are missing, many words cannot be formed.
            $badwords = array(
                'A55', 'GAY', 'KUM', 'PEE', '5EX', 'T1T', 'XXX', 'ANAL', 'ANU5', 'AR5E', 'BUTT', 'DYKE', 'DUMP', 'JERK', 'J1ZZ', 'KUNT', 'P155', 'PU55', 'PU5Y', '5H1T', '5LUT', 'TURD', 'TWAT', 'WANK'
            );
            foreach($badwords as $word)
                if(strpos($tid, $word)!==false)
                    continue;

            break;
        }

        return $tid;
    }

    /**
     * Returns the TrackID of a loaded issue (in $this-> issue)
     */
    public function get_TrackID()
    {
        return $this-> issue['track'];
    }

    const	ERR_XFIELD_MISSING_REQ = 'errors.xf.missreq',
        ERR_XFIELD_CHOICE_OUTOFRANGE = 'errors.xf.coor',
        ERR_XFIELD_NOT_NUMERIC = 'errors.xf.nnum',
        ERR_XFIELD_NOT_DATE = 'error.xf.notdate',
        ERR_XFIELD_PLUGIN_ERROR = 'error.xf.plugin';

    /**
     * Analyses input (that usually comes from a form post) and prepares the database-stored xfields
     * -- Uses the Issue Type Definition ( must exist in $this-> ist before this method is called )
     *
     * @param array $xfields The xfields data, which is transformed so it can be inserted
     * @param array $check_missing
     * - If true, it checks all the fields and returns error if any of them is missing
     * - If false, only checks the incoming fields for correctness
     * @param bool $update_mode In update mode, it allows mandatory fields to be empty
     * @return array Error list (if any)
     */
    private function prepare_XFields(&$xfields, $check_missing = true, $update_mode = false)
    {
		//print_r($xfields);
        // list of field errors - If none encountered, this should remain empty
        $errors = array();
        foreach($this-> ist_def['xfield_def'] as $fk=> $fdef) {
            // next field
            $field_store = null;
            // Process the field if present in the $xfields array and it has content ( don't store anything "empty" )
            if(isset($xfields[$fk])) {

                // extra field exists in the data, test if it respects some conditions and make some processings
                switch($fdef['t']) {
                    case 'text':
                        // Text is empty ?
                        if(!$update_mode && empty($xfields[$fk]) && !strlen(trim($xfields[$fk])))
                            goto is_empty;

                        // enforce max length for text-type fields (if present in xfdef)
                        $val = (isset($fdef['o']) && isset($fdef['o']['len']))? substr(trim($xfields[$fk]), 0, $fdef['o']['len']): trim($xfields[$fk]);

                        if(strlen($val)>0)
                            $field_store = array(
                                'value'=> $val
                            );
                        else
                            $field_store = array(); // Empty text: Remove data
                        break;

                    case 'enum':
                        if(!$update_mode && empty($xfields[$fk]) && $xfields[$fk]!=='0') // The string '0' is a valid option !
                            break;

                        // Check if the value is present in the valid choices for this field
                        if(!empty($xfields[$fk]) && !array_key_exists($xfields[$fk], $fdef['cd_ex'])) {
                            $errors[] = array(
                                'err'=> self::ERR_XFIELD_CHOICE_OUTOFRANGE,
                                'fk'=> $fk,
                                'ft'=> $fdef['t'],
                                'fn'=> $fdef['n'],
                                'val'=> $xfields[$fk]
                            );
                            break;
                        }

                        // <option value='' /> ; This is diffrent from when $xfields[$fk] is valid and has value '0'
                        if($xfields[$fk] === '')
                            $field_store = array();
                        else
                            $field_store = array(
                                // store in either the "value" (string, any type) or the "int_value", depending on the option "Numeric Choice Data"
                                (isset($fdef['o']) && isset($fdef['o']['num']) && $fdef['o']['num']? 'int_value': 'value')=> $xfields[$fk]
                            );
                        break;

                    case 'set':
                        // If the value is a string instead of an array, it assumes it comes from the new managed checkbox lists
                        if(is_string($xfields[$fk])) {
                            if(empty($xfields[$fk]))
                                $xfields[$fk] = array();
                            else {
                                $choices = explode(',', $xfields[$fk]);
                                $xfields[$fk] = array();
                                foreach($choices as $cho)
                                    $xfields[$fk][$cho] = 1;
                            }
                        }

                        if($update_mode) {
                            // Remove entries without values
                            foreach($xfields[$fk] as $key=>$val)
                                if(empty($val))
                                    unset($xfields[$fk][$key]); // In update mode, remove the pseudo-entry which allows the field to be submitted even if nothing is currently checked in a check-box array (empty value)
                        } else
                            if(empty($xfields[$fk])) // No option selected on submit ? Don't save, move on...
                                break;
                        // check if the list of data elements are each among the valid choices for this field
                        $diff = array_diff(array_keys($xfields[$fk]), array_keys($fdef['cd_ex']));
                        if(!empty($diff)) {
                            $errors[] = array(
                                'err'=> self::ERR_XFIELD_CHOICE_OUTOFRANGE,
                                'fk'=> $fk,
                                'ft'=> $fdef['t'],
                                'fn'=> $fdef['n'],
                                'val'=> $diff
                            );
                            break;
                        }

                        if(count($xfields[$fk]) > 0)
                            // all ok, encode the multiple choices as json array
                            $field_store = array(
                                'value'=> json_encode(array_keys($xfields[$fk]))
                            );
                        else
                            $field_store = array(); // Empty set: Remove data
                        break;

                    case 'number':
                        if(!$update_mode && empty($xfields[$fk]) && $xfields[$fk]!=='0') // The string '0' is a valid option, as it converts to an actual number
                            break;

                        if(!empty($fdef['o']['colr']) && $fdef['o']['colr'] == 7) {
                            // This is a "Clock24" field ( number from 0 to 86399) - Parse the format HH:II:SS into number of seconds.
                            $parse = array();
                            if(!preg_match ('#^(?<hours>[\d]{2}):(?<mins>[\d]{2}):(?<secs>[\d]{2})$#', $xfields[$fk], $parse))
                                $xfields[$fk] = 0;
                            else
                                $xfields[$fk] = (int) $parse['hours'] * 3600 + (int) $parse['mins'] * 60 + (int) $parse['secs'];
                        }

                        // We have content
                        if(strlen($xfields[$fk])>0) {
                            // Not a number ? Shoot error.
                            if(!is_numeric($xfields[$fk])) {
                                $errors[] = array(
                                    'err'=> self::ERR_XFIELD_NOT_NUMERIC,
                                    'fk'=> $fk,
                                    'ft'=> $fdef['t'],
                                    'fn'=> $fdef['n'],
                                    'val'=> $xfields[$fk]
                                );
                                break;
                            }
                            // store in int value
                            $field_store = array(
                                'int_value'=> (int)round($xfields[$fk])
                            );
                            // but also store in the "value" (for display purposes) if it's not an integer number (float? fractional?)
                            if((int)$xfields[$fk] != $xfields[$fk])
                                $field_store['value'] = $xfields[$fk];
                        } else
                            $field_store = array(); // Empty input: Remove data
                        break;

                    case 'date':
                        if(!$update_mode && empty($xfields[$fk]))
                            goto is_empty;

                        if(strlen($xfields[$fk])>0) {
                            // check if the field value can be transformed into a date; Min-Max accepted:  1 January 1970 -> ~19 January 2038
                            $unixt = strtotime($xfields[$fk]);
                            if($unixt===false) {
                                $errors[] = array(
                                    'err'=> self::ERR_XFIELD_NOT_DATE,
                                    'fk'=> $fk,
                                    'ft'=> $fdef['t'],
                                    'fn'=> $fdef['n'],
                                    'val'=> $xfields[$fk]
                                );
                                break;
                            }
                            $field_store = array(
                                'int_value'=> $unixt
                            );
                        } else
                            $field_store = array(); // Empty date: Remove data
                        break;

                    default:
                        $plugin = $this-> field_Plugin($fdef['t']);
                        if(is_object($plugin)) {
                            $plugin-> fdef = &$fdef;
                            // The plugin determines what an "empty" value means (input could be complex, multiple fields)
                            if(!empty($fdef['o']['req']) && $fdef['o']['req']) {
                                $check = $plugin-> check($xfields[$fk], $update_mode);
                                if($check !== false) {
                                    // plugin reports an error in the field
                                    $errors[] = array(
                                        'err'=> self::ERR_XFIELD_PLUGIN_ERROR,
                                        'fk'=> $fk,
                                        'ft'=> $fdef['t'],
                                        'fn'=> $fdef['n'],
                                        'val'=> $xfields[$fk],
                                        'plugin_err'=> $check
                                    );
                                    break;
                                }
                            }
                            $field_store = $plugin-> get_Field_Store($xfields[$fk]); // The plugin could remove data as well if it returns empty $field_store
                        } else
                            $errors[] = array(
                                'err'=> self::ERR_XFIELD_PLUGIN_ERROR,
                                'fk'=> $fk,
                                'ft'=> $fdef['t'],
                                'plugin_err'=> "Invalid plugin detected: ".$fdef['t']
                            );
                    // End Switch
                }
            } else {
                is_empty:
                // field doesn't exist in the data, test some conditions
                if($check_missing && isset($fdef['o'])) {
                    // required field, can't create without it!
                    if(isset($fdef['o']['req']) && $fdef['o']['req']==true)
                        $errors[] = array(
                            'err'=> self::ERR_XFIELD_MISSING_REQ,
                            'fk'=> $fk,
                            'ft'=> $fdef['t'],
                            'fn'=> $fdef['n']
                        );
                    // not required, test if there is a default
                    else if(isset($fdef['o']['def'])) {
                        // Note that the default value is not checked here for corectness ! It should be checked when defining issue types
                        switch($fdef['t']) {
                            case 'text':
                            case 'set':
                                $field_store = array(
                                    // store in the varchar for text and sets (which are json encoded lists of values)
                                    'value'=> $fdef['o']['def']
                                );
                                break;
                            case 'enum':
                                $field_store = array(
                                    // store in either the "value" (string, any type) or the "int_value", depending on the option "Numeric Choice Data"
                                    ((isset($fdef['o']) && isset($fdef['o']['num']) && $fdef['o']['num']==true)? 'int_value': 'value')=> $fdef['o']['def']
                                );
                                break;
                            case 'number':
                                $field_store = array(
                                    // if determined to be integer, store in int_value, otherwise store in value (real numbers)
                                    ((int)$fdef['o']['def'] == $fdef['o']['def']? 'int_value': 'value')=> $fdef['o']['def']
                                );
                                break;
                            case 'date':
                                $field_store = array(
                                    'int_value'=> strtotime($fdef['o']['def'])
                                );
                                break;
                            default:
                                $plugin = $this-> field_Plugin($fdef['t']);
                                if(is_object($plugin)) {
                                    $plugin-> fdef = &$fdef;
                                    $field_store = $plugin-> get_Default();
                                } else
                                    $errors[] = array(
                                        'err'=> self::ERR_XFIELD_PLUGIN_ERROR,
                                        'fk'=> $fk,
                                        'ft'=> $fdef['t'],
                                        'plugin_err'=> "Invalid plugin detected: ".$fdef['t']
                                    );
                        }

                    }
                }
                // else, nothing special, just ignore the fact that it doesn't exist
            }

            if(!is_null($field_store))
                // Convert received value into store value (ready for DB)
                $xfields[$fk] = $field_store;
            elseif(!$update_mode)
                // Dump the missing ones, no need to record empty rows
                unset($xfields[$fk]);
        }
        // Cleanup xfields of keys which do not exist in xfield_def (self healing data)
        foreach($xfields as $fk=> $field_store)
            if(!is_array($field_store))
                unset($xfields[$fk]);

        // return errors if any ( or empty array if there were none )
        return $errors;
    }

    /**
     * Creates the XFields for an issue by inserting into the IXF table
     *
     * @param int $rec_id Record ID for which xfields are created
     * @param array $xfields
     * TODO: @param int $org_id Organization ID which is the owner of the issue having these fields (Can be null) - Part of the future "private fields" upgrade
     */
    public function create_XFields($rec_id, $xfields)
    {
		
		//print_r($xfields);exit;
        // build the query...
        $q=QueryFactory::insert()
            -> table(MVC_Core::$db-> tab['ixf'], 'issue_id,fk_id,int_value,value');
        // multiple row inserts in one query
        $field_records = 0;
        foreach($xfields as $fk=> $field_store) {
            if(empty($field_store))
                continue; // Skip empty rows
            $field_store['issue_id'] = $rec_id;
            $field_store['fk_id'] = $this-> ist_def['xfield_def'][$fk]['id'];
            $q-> values($field_store);
            $field_records++;
        }
        // ... and run it
		//print_r($q);exit;
        if($field_records)
            return MVC_Core::$db-> query($q);
        else
            return false; // Nothing created
    }

    /**
     * Creates XFields from raw data (extracted via read_XFields_RAW) or via a database export
     * -- This method can be used for data copy or restore
     * @param int $rec_id Record ID for which xfields are created
     * @param array $xf_raw Must contain: "fk_id", "int_value", "value"
     */
    public function create_XFields_RAW($rec_id, $xf_raw)
    {
        if(empty($xf_raw))
            return false; // Nothing to do if nothing to insert (no fields ?)

        // build the query...
        $q=QueryFactory::insert()
            -> table(MVC_Core::$db-> tab['ixf'], 'issue_id,fk_id,int_value,value');

        foreach($xf_raw as $field_store) {
            $field_store['issue_id'] = $rec_id;
            $q-> values($field_store);
        }

        return MVC_Core::$db-> query($q);
    }

    /**
     * Reads the XFields for an issue from the IXF table
     *
     * @param int $rec_id Read XFields for which record ?
     * @param bool $serialize If true, the fields will be transformed into strings or arrays (in case of some plugins), ready to be used in certain templates and other purposes
     * @param bool $html_special_chars Encode characters in strings which would break HTML (quotes, lt, gt, amp)
     * @param bool $form_compat When true, the fields get decoded as they would be used by a form (similar to Factory_Form_Object method)
     * @param array $fk_id_list When present, limit the list of fields that will be read to those specified here
     * @return array All the XFields for an issue
     */
    private function read_XFields($rec_id, $serialize = false, $html_special_chars = false, $form_compat = false, $fk_id_list = null)
    {
		
        $all_xfields = array();

        $q=QueryFactory::select()
            -> table(MVC_Core::$db-> tab['ixf'], 'fk_id,int_value,value')
            -> where("`issue_id` = ?", $rec_id);

        if(is_array($fk_id_list))
            $q-> where("fk_id IN(?)", $fk_id_list);

        $r = MVC_Core::$db-> query($q);

        if($r) {
            // some post-processing depending on field type
            // construct an array fk_id => fk using data from $this-> ist['xfield_def']
            $fk_translate = array();
            foreach($this-> ist_def['xfield_def'] as $fk=> $fdef)
                $fk_translate[$fdef['id']] = $fk;

            foreach($r as $xf) {
                if(isset($fk_translate[$xf['fk_id']])) {
                    $fk = $fk_translate[$xf['fk_id']];
                    if($form_compat)
                        $this-> XField_Form_Compat($all_xfields, $xf, $fk);
                    else
                        $all_xfields[$fk] = $this-> decode_XField($xf, $fk, $serialize, $rec_id, $html_special_chars);
                }
            }
        }
		// Sujatha Code for MRN Open Days
		if(isset($all_xfields['mrn_date'])) {
			$mrn_date_sec = strtotime($all_xfields['mrn_date']);
			$seconds = time() - $mrn_date_sec;
			$all_xfields['open_days']=0;
			$days = $this->secondsToDays($seconds);	
			$all_xfields['open_days']=$days;
		}
        return $all_xfields;
    }
	
	function secondsToDays($seconds){
		return floor($seconds/86400);
	}

    /**
     * Read xfields as raw data (no type verification or processing). Might return bogus/fake/error xfields
     * @param int $rec_id Read XFields for which record ?
     * @param array $xf_ids List of xfield IDs (to limit the reading to only certain fields, instead of all available) - Expected array of numeric ID's
     * @param bool $use_keys Convert numerical IDs to keys
     * @pram bool $remove_nulls Remove any array entries from "int_value" and "value" which are null (actual PHP null, not just empty)
     * @return array Raw XFields
     */
    private function read_XFields_RAW($rec_id, $xf_ids = null, $use_keys = false, $remove_nulls = true)
    {
        $xf_raw = array();

        $q=QueryFactory::select()
            -> table(MVC_Core::$db-> tab['ixf'], 'fk_id,int_value,value')
            -> where("`issue_id` = ?", $rec_id);

        if(!is_null($xf_ids))
            $q-> where("`fk_id` IN(?)", $xf_ids);

        $r = MVC_Core::$db-> query($q);
        if(!$r)
            return null;

        if($use_keys) {
            $fk_translate = array();
            foreach($this-> ist_def['xfield_def'] as $fk=> $fdef)
                $fk_translate[$fdef['id']] = $fk;
        }

        foreach($r as $xf) {
            if($remove_nulls) {
                if(is_null($xf['int_value'])) unset($xf['int_value']);
                if(is_null($xf['value'])) unset($xf['value']);
            }

            if(isset($fk_translate[$xf['fk_id']])) {
                $fk = $fk_translate[$xf['fk_id']];
                unset($xf['fk_id']);
                $xf_raw[$fk] = $xf;
            } else
                $xf_raw[] = $xf;
        }
        return $xf_raw;
    }

    /**
     * Deletes a list of xfields for an issue by specifying the list of key ids that will be deleted
     * If the list is not specified, it will delete ALL XFields for the specified record.
     *
     * @param int $rec_id Delete XFields from which record ?
     * @param array $field_list
     */
    public function delete_XFields($rec_id, $field_list = null)
    {
        if(is_null($field_list)||!empty($field_list)) {
            $q=QueryFactory::delete()
                -> table(MVC_Core::$db-> tab['ixf'])
                -> where("`issue_id` = ?", $rec_id);

            if(!empty($field_list)) {
                // make the field_key (fk) id's list (fk_ids)
                $fk_ids = array();
                foreach($field_list as $fk)
                    $fk_ids[] = $this-> ist_def['xfield_def'][$fk]['id'];

                $q-> where("`fk_id` IN(?)", $fk_ids);
            }

            MVC_Core::$db-> query($q);
        }
    }

    /**
     * Decodes the content of an xfield value, depending on it's key
     * Assumes that $this-> ist has been preloaded with the correct issue type, so the field keys exist in $this-> ist['xfield_def']
     *
     * @param array $xf Xfield row result
     * @param string $fk Field Key
     * @param bool $serialize Return of decoding is serializable (not array/object) - It can be int, float or just string
     * @param int $rec_id Used only by self-correcting methods, to know which field in the DB to affect
     * // Note that when serializing, keys are lost, and output might depend on field definition
     */
    private function decode_XField($xf, $fk, $serialize = false, $rec_id = null, $html_special_chars = false)
    {   
        
        
        if(empty($xf))
            return null;

        $fdef = &$this-> ist_def['xfield_def'][$fk];
//         echo "decode_XField 677 xf <pre>";
//         print_r($xf);
//         echo "677 fk <pre>";
//         print_r($fk);
//         echo "679 <pre>";
//         print_r($fdef);
//         echo "679 rec_id".$rec_id."<br>";

        switch($fdef['t']) {
            case 'text':
                if ($fk == 'audit_number') {
                    if (!empty($xf['value']))
                        return  $xf['value'];
                    else
                        return (float) $xf['int_value'];
                } else {
                    if ($html_special_chars)
                        return htmlspecialchars($xf['value'], ENT_QUOTES);
                    else
                        return $xf['value'];
                }


            case 'number':


                if(isset($fdef['o']) && isset($fdef['o']['colr']) && $fdef['o']['colr'] == 7) { // TIME field
                    // convers an integer number into a 24-hour international clock (00:00:00 to 23:59:59)
                    $sec0 = (int)$xf['int_value'] % 86400;
                    $hours = (int)($sec0/3600);
                    $rem = $sec0 % 3600;
                    $minutes = (int)($rem/60);
                    $seconds = $rem - $minutes*60;
                    if($hours<10)
                        $hours = "0".$hours;
                    if($minutes<10)
                        $minutes = "0".$minutes;
                    if($seconds<10)
                        $seconds = "0".$seconds;
                    return $hours. ':'. $minutes. ':'. $seconds;
                }
                if(!empty($xf['value']))
                    return (float)$xf['value'];
                else
                    return (int)$xf['int_value'];

            case 'date':
                if(!empty($xf['int_value'])) {
                    $unixtime = filter_var($xf['int_value'], FILTER_VALIDATE_INT, array('options'=> array('min_range'=> 1, 'max_range'=> 2147483647))); // Do not allow value 0, negative or bigger than MAX_INT32
                    if($unixtime)
                        return date("Y-m-d", $unixtime);
                    elseif(!is_null($rec_id)) {
                        // Self cleaning - remove the affected xfield value from DB
                        MVC_Core::$debug-> dump("Invalid Date value corrected for Rec ID: {$rec_id}, Field ID: {$fdef['id']}", __METHOD__, null, "warning");
                        MVC_Core::$db-> query(
                            QueryFactory::delete()
                                -> table(MVC_Core::$db-> tab['ixf'])
                                -> where("`issue_id` = ?", $rec_id)
                                -> where("`fk_id` = ?", $fdef['id'])
                        );
                    }
                }
                return null; // For invalid dates, return null

            case 'enum': // single choice
                if(isset($fdef['o']) && isset($fdef['o']['num']) && $fdef['o']['num']==true) {

                    


                    if(is_null($xf['int_value']))
                        return null;
                    $value = is_numeric($xf['int_value']) && isset($fdef['cd_ex'][(string)$xf['int_value']])? $fdef['cd_ex'][(string)$xf['int_value']]: '';
                    return $serialize? $value: array(
                        'key'=> $xf['int_value'],
                        'value'=> $value
                    );
                } else {
                    if(is_null($xf['value']))
                        return null;
                        
                    // check if current stored value exists in field definition, if not return empty string
                    $value = strlen($xf['value'])>0 && isset($fdef['cd_ex'][(string)$xf['value']])? $fdef['cd_ex'][(string)$xf['value']]: '';
                    // 15052024 created by iwi  | start
                    if($xf['value'] !='' && $value == ''){
                        $value = $xf['value'];
                    }
                    // 15052024 created by iwi  | end
                       
                    return $serialize? $value: array(
                        'key'=> $xf['value'],
                        'value'=> $value
                    );
                }

            case 'set': // multiple choices
                $set = @json_decode($xf['value'], true);
                if(is_array($set)) {
                    if($serialize) {
                        $values = array();
                        foreach($set as $k=> &$val) {
                            // make a simple list of all values
                            if(isset($fdef['cd_ex'][$val]))
                                $values[] = $fdef['cd_ex'][$val];
                        }
                        return implode(", ", $values); // by default, a choice set is serialized as CSV . TODO - Ability to change how an "set" is presented
                    } else {
                        if(!empty($fdef['o']['flag'])) {
                            // Treat multiple choices as "Flags", in which all possible options are returned, and each one is marked active = true if present in the field
                            $res = array();
                            foreach($fdef['cd_ex'] as $k=> $val)
                                $res[] = array(
                                    'key'=> $k,
                                    'value'=> $val,
                                    'active'=> in_array($k, $set)
                                );
                            return $res;
                        } else {
                            // A simple enumeration of active values
                            foreach($set as $k=> &$val) {
                                // test if the stored value exists in the definition, if not, it's possible that the possible choices have been changed since the issue has been created
                                // in this case, drop the entry (just like it doesn't exist in the DB) - It will be removed anyway at next field edit
                                if(isset($fdef['cd_ex'][$val])) {
                                    $val = array(
                                        'key'=> $val,
                                        'value'=> $fdef['cd_ex'][$val]
                                    );
                                } else
                                    unset($set[$k]);
                            }
                            return $set;
                        }
                    }
                } else
                    return "";

            default: // Anything else is a plugin
                $plugin = $this-> field_Plugin($fdef['t']);
                if(is_object($plugin)) {
                    $plugin-> fdef = &$fdef;
                    return $plugin-> decode($xf, $serialize);
                } else
                    return null;
        }
    }

    /**
     * Decodes the content of an xfield value in the same way as it would be used in a form
     * Assumes that $this-> ist has been preloaded with the correct issue type, so the field keys exist in $this-> ist['xfield_def']
     *
     * @param array $xf Xfield row result
     * @param string $fk Field Key
     */
    private function XField_Form_Compat(&$all_fields, $xf, $fk)
    {
        if(empty($xf))
            return null;

        $fdef = &$this-> ist_def['xfield_def'][$fk];
        switch($fdef['t']) {
            case 'text':
                $all_fields[$fk] = ((string)$xf['value']);
                return;
            case 'number':
                if(isset($fdef['o']) && isset($fdef['o']['colr']) && $fdef['o']['colr'] == 7) { // TIME field
                    // convers an integer number into a 24-hour international clock (00:00:00 to 23:59:59)
                    $sec0 = (int)$xf['int_value'] % 86400;
                    $hours = (int)($sec0/3600);
                    $rem = $sec0 % 3600;
                    $minutes = (int)($rem/60);
                    $seconds = $rem - $minutes*60;
                    if($hours<10)
                        $hours = "0".$hours;
                    if($minutes<10)
                        $minutes = "0".$minutes;
                    if($seconds<10)
                        $seconds = "0".$seconds;

                    $all_fields[$fk] = $hours. ':'. $minutes. ':'. $seconds;
                }
                if(!empty($xf['value']))
                    $all_fields[$fk] = (float)$xf['value'];
                else
                    $all_fields[$fk] = (int)$xf['int_value'];
                return;
            case 'date':
                $unixtime = filter_var($xf['int_value'], FILTER_VALIDATE_INT, array('options'=> array('min_range'=> 1, 'max_range'=> 2147483647))); // Do not allow value 0, negative or bigger than MAX_INT32
                if($unixtime)
                    $all_fields[$fk] = date("Y-m-d", $unixtime);
                return;
            case 'enum': // single choice - doesn't verify if values are valid !
                if(isset($fdef['o']) && isset($fdef['o']['num']) && $fdef['o']['num']==true) {
                    if(is_null($xf['int_value']))
                        return;

                    $all_fields[$fk] = (int)$xf['int_value'];
                } else {
                    if(is_null($xf['value']))
                        return;

                    $all_fields[$fk] = (string)$xf['value'];
                }
            case 'set':
                // This doesn't verify if the values are valid !
                $all_fields[$fk] = @json_decode($xf['value'], true);
                return;
            default:
                $plugin = $this-> field_Plugin($fdef['t'], true); // The parameter means that the field plugins are single-instance use (they should not do any preloading of data)
                if(is_object($plugin)) {
                    $plugin-> fdef = &$fdef;
                    if(method_exists($plugin, "form_Compat")) {
                        $all_fields[$fk] = $plugin-> form_Compat($xf, $fk);
                    } else
                        $all_fields[$fk] = ((string)$xf);
                }
                return;
        }
    }

    /**
     * Creates a "where" condition for the QueryFactory depending on parameters. Used by the search/filtering system(s)
     *
     * @param QueryFactory $q
     * @param string $where_field Apply the filter set to which group
     * @param array $filter_set The filter set
     * @param Callback $data_prep_cb If !null, for each item in filter set, call this function to modify the value
     */
    private function filter_Database_XField(QueryFactory &$q, $where_field, &$filter_set, $data_prep_cb = null)
    {
        $where_string = ""; $where_field = "`$where_field` ";
        foreach($filter_set as $idx=> $filter_item) {

            if(is_callable($data_prep_cb))
                if(!$data_prep_cb($filter_item))
                    return false;

            // when value is an array, switch the condition to IN or NOT_IN
            switch($filter_item['c']) {
                case "CONTAINS":
                    $where_string.= ($idx?" OR ":""). $where_field. $q-> quote_Into("LIKE ?", "%{$filter_item['v']}%"); break;
                case "DOES_NOT_CONTAIN":
                    $where_string.= ($idx?" AND ":""). $where_field. $q-> quote_Into("NOT LIKE ?", "%{$filter_item['v']}%"); break;
                case "EQUAL": // When value is array, it has to be in set
                    if(is_array($filter_item['v']))
                        $where_string.= ($idx?" OR ":""). $where_field. $q-> quote_Into("IN(?)", $filter_item['v']);
                    else
                        $where_string.= ($idx?" OR ":""). $where_field.$q-> quote_Into("= ?", $filter_item['v']);
                    break;
                case "NOT_EQUAL": // when value is array, it must not be in set
                    if(is_array($filter_item['v']))
                        $where_string.= ($idx?" AND ":""). $where_field. $q-> quote_Into("NOT IN(?)", $filter_item['v']);
                    else
                        $where_string.= ($idx?" AND ":""). $where_field.$q-> quote_Into("<> ?", $filter_item['v']);
                    break;
                case "GREATER_THAN":
                    $where_string.= ($idx?" AND ":""). $where_field.$q-> quote_Into("> ?", $filter_item['v']); break;
                case "LESS_THAN":
                    $where_string.= ($idx?" AND ":""). $where_field.$q-> quote_Into("< ?", $filter_item['v']); break;
                case "GREATER_THAN_OR_EQUAL":
                    $where_string.= ($idx?" AND ":""). $where_field.$q-> quote_Into(">= ?", $filter_item['v']); break;
                case "LESS_THAN_OR_EQUAL":
                    $where_string.= ($idx?" AND ":""). $where_field.$q-> quote_Into("<= ?", $filter_item['v']); break;
                case "STARTS_WITH":
                    $where_string.= ($idx?" OR ":""). $where_field.$q-> quote_Into("LIKE ?", "{$filter_item['v']}%"); break;
                case "ENDS_WITH":
                    $where_string.= ($idx?" OR ":""). $where_field.$q-> quote_Into("LIKE ?", "%{$filter_item['v']}"); break;
                case "NULL":
                    $where_string.= ($idx?" OR ":""). $where_field." IS NULL"; $condition_operator = 'OR'; break;
                case "NOT_NULL":
                    $where_string.= ($idx?" AND ":""). $where_field." IS NOT NULL"; break;
            }
        }

        $q-> where($where_string);
        return true;
    }

    /**
     * Which database field does the xfield use ?
     * @param array $fdef
     * @return string
     */
    public function XField_Data_Column(&$fdef, &$plugin = null)
    {
        switch($fdef['t']) {
            case 'text':
            case 'set':
                return 'value';
            case 'number':
                return 'int_value';
            case 'date': // date and normal number is stored in int_value
                return 'int_value';
            case 'enum':
                if(isset($fdef['o']) && isset($fdef['o']['num']) && $fdef['o']['num']==true)
                    return 'int_value';
                else
                    return 'value';
                break;
            // Everything not covered above goes to the field plugin system
            default:
                $plugin = $this-> field_Plugin($fdef['t']);
                if(is_object($plugin)) {
                    $plugin-> fdef = &$fdef;
                    // let the plugin choose which one
                    return $plugin-> data_column;
                } else
                    return false; // Error, a column could not be chosen
        }
    }

    /**
     * Searches for certain values inside the xfields and return the issue ID's of the fields that correspond
     *
     * @param string $fk
     * @param array $filter_set List of conditions to search for
     * - Each element consists of two keys: "condition" and "value"
     *   The condition can be any of these: "CONTAINS", "DOES_NOT_CONTAIN", "EQUAL", "EQUAL_CASE_SENSITIVE", NOT_EQUAL","GREATER_THAN", "GREATER_THAN_OR_EQUAL", "LESS_THAN", "LESS_THAN_OR_EQUAL", "STARTS_WITH", "STARTS_WITH_CASE_SENSITIVE", "ENDS_WITH", "ENDS_WITH_CASE_SENSITIVE", "NULL", "NOT_NULL", "EMPTY", "NOT_EMPTY"
     * @param bool $visual_values If true, attempt to convert the visual values ( shown in the grid ) to the storage values. If not, raw value is searched
     * @return array
     */
    public function search_XFields($fk, $filter_set = array(), $visual_values = false)
    {
        if($fk[0]=='~') { // attached column controlled by a plugin
            $fk = substr($fk, 1);
            $is_attached = true;
        } else
            $is_attached = false;

        // please make sure the IST is known ( $this-> ist has values ), before running this method !
        if(!isset($this-> ist_def['xfield_def'][$fk]))
            return array(); // xfield key not found - no results

        $fdef = &$this-> ist_def['xfield_def'][$fk];
        $plugin = null;

        $where_field = $this-> xField_Data_Column($fdef, $plugin);
        if(empty($where_field))
            return array(); // Invalid plugin, cannot search

        $q=QueryFactory::select()
            -> table(MVC_Core::$db-> tab['ixf'], array('issue_id'))
            -> where('`fk_id` = ?', $fdef['id']);

        if($visual_values) {
            if(!$this-> filter_Database_XField($q, $where_field, $filter_set, function(&$filter_item) use ($where_field, $fdef, $plugin, $is_attached)
            {	// The prepare/transform callback for each row
                switch($fdef['t']) {
                    case 'text':
                        // text is not changed
                        break;

                    case 'number':

                        break;
                    case 'date':
                        // numbers are usually integers
                        if($where_field == 'int_value')
                            $filter_item['v'] = strtotime($filter_item['v']);
                        break;

                    case 'enum':
                    case 'set':
                        // for enum and set, transform back from the visible format into the stored format
                        $filter_item['v'] = array_search($filter_item['v'], $fdef['cd_ex']);
                        // - for "enum" the value might be integer
                        if($fdef['t'] == 'enum' && $where_field == 'int_value')
                            $filter_item['v'] = (int)$filter_item['v'];
                        // - for set, the values are stored inside quotes, and they must be searched with 'CONTAINS', like text fields
                        if($fdef['t'] == 'set') {
                            $filter_item['v'] = '"'.$filter_item['v'].'"';
                            if($filter_item['c'] == 'EQUAL')
                                $filter_item['c'] = "CONTAINS";
                            else
                                $filter_item['c'] = "DOES_NOT_CONTAIN";
                        }
                        break;

                    default:
                        // only the plugin knows how the data is stored, so use a plugin method to transform the value from the input into something searchable
                        $plugin-> prepare_Data_Search($filter_item, $is_attached);
                        // test if the plugin returned NULL, in this case, it means the value can't be converted, and there are no results
                        if(is_null($filter_item['v']))
                            return false;
                }
                return true; // conversion successful
            }))
                return array(); // incorrect values, don't attempt to run the query
        } else
            if(!$this-> filter_Database_XField($q, $where_field, $filter_set))
                return array();

        $issue_id_list = array();

        $r = MVC_Core::$db-> query($q);
        if($r && count($r) > 0) {
            foreach($r as $row)
                $issue_id_list[] = (int)$row['issue_id'];
        }

        return $issue_id_list;
    }

    protected static $cache_columns = array('primary', 'secondary', 'tertiary', 'quaternary', 'quinary');

    protected static $org_columns = array(
        'process', 'utime', 'dep_ids', 'time_open', 'time_start', 'time_close', 'time_hide', 'commtime', // New "Process" marker and Times
        'count_contacts', 'count_files', 'filesize_total', 'count_convos', 'count_notes', 'count_tasks', 'count_active_tasks', 'count_propfields',
        'priority_key', 'status_key',
        'xfer_by', 'xfer_to', 'xfer_time', 'xfer_ip', 'xfer_confirmip',
        'assigned_to', 'assign_ut',	'exclusive_edit', 'access_users', 'access_ut',
        'pending_length', 'process_length'
    );

    /**
     * Temporary storage array for quickindex data. If non-empty, on create and update the information is stored in RQI
     * @var array
     */
    private $quickindex = array();

    /**
     * Common function for determining content of cache fields from the externally linked XFields.
     * Cache fields are arbitrary choices of content, special for each record type. They have to be changed/updated when the matching XField is present in the parameter, but otherwise left untouched
     *
     * @param array $XFields
     * @param bool $enclose Enclose the cache output in single quotes ( for DB ) - Necessary when "string_expressions" mode is used on update queries (which doesn't automatically enclose strings)
     * @param bool $decode_XF If false, indicates the receiving array ($XFields is already decoded and stringified - Grid format . Only used in the bulk update procedure) . Don't decode incoming field
     * @param array $xfchange_list List of $XFields keys that were actually changed, even if $XFields parameter contains a lot more fields
     * -- Used to check if CField should be computed or not (only change when an XF was actually altered and is present in the pattern)
     * @param bool $quickindex_only Calculates and Returns Quickindex instead of Cfields
     * @return array
     */
    public function prepare_CFields($XFields, $enclose = false, $decode_XF = true, $xfchange_list = null, $quickindex_only = false)
    {
        // This method also handles "quickindex", used for fast location of records on bulk or automated processes (only certain fields are being indexed)
        if(isset($this-> ist_def['settings']['xf_cache']['quickindex'])) {
            $this-> quickindex = array();
            foreach($this-> ist_def['settings']['xf_cache']['quickindex'] as $fk => $fk_id) {
                // If not part of the changes or current set of xfields, nothing to do
                if(is_array($xfchange_list) && !in_array($fk, $xfchange_list))
                    continue;
                elseif(!isset($XFields[$fk]))
                    continue;

                if($decode_XF)
                    $qi_value = $this-> decode_XField($XFields[$fk], $fk, true);
                else
                    $qi_value = $XFields[$fk];
                if(empty($qi_value))
                    continue; // Skill empty values, need actual content for the quickindex

                $this-> quickindex[] = array('fk_id'=> $fk_id, 'value'=> $qi_value);
            }
        }

        // Stop here when CFields don't need to be updated
        if($quickindex_only)
            return $this-> quickindex;

        if(empty(self::$dry_tpl))
            self::$dry_tpl = new Drytemplate(new stdClass());

        // Fully process cache fields and return them
        $CFields = array();
        foreach(self::$cache_columns as $cache_field) {
            $cache_output = false;
            if(isset($this-> ist_def['settings']['xf_cache'][$cache_field])) {
                $cache_settings = $this-> ist_def['settings']['xf_cache'][$cache_field];
                // Cache-field settings indicate which fields are used, and if the cache is a direct copy operation or by using Drytemplate
                if(!empty($cache_settings['direct'])) {
                    // Original method, using direct string copy (Only one field per cache field)
                    $process_direct = false;
                    if(is_array($xfchange_list))
                        $process_direct = in_array($cache_settings['fk'], $xfchange_list) && isset($XFields[$cache_settings['fk']]);
                    else
                        $process_direct = isset($XFields[$cache_settings['fk']]);

                    if($process_direct) {
                        if($decode_XF)
                            $cache_output = $this-> decode_XField($XFields[$cache_settings['fk']], $cache_settings['fk'], true);
                        else
                            $cache_output = $XFields[$cache_settings['fk']];
                    }
                } else {
                    // Template mode - Process multiple fields, allow merging of different fields into one cache field (for searching purposes)
                    $xf_decoded = array();
                    $any_xfchanged = !is_array($xfchange_list); // When no list present, start with "true" (always update)
                    foreach($cache_settings['fk'] as $fk) {
                        if(!$any_xfchanged && in_array($fk, $xfchange_list))
                            $any_xfchanged = true; // When at least one field is changed, recompute the template-based cache field

                        if(isset($XFields[$fk]))
                            if($decode_XF)
                                $xf_decoded[$fk] = $this-> decode_XField($XFields[$fk], $fk, true); // Decode and Serialize
                            else
                                $xf_decoded[$fk] = $XFields[$fk];
                    }

                    if($any_xfchanged && !empty($xf_decoded))
                        $cache_output = self::$dry_tpl-> render_String($cache_settings['tpl'], $xf_decoded);
                }

                if(is_string($cache_output)) {
                    $cache_output = substr(trim($cache_output), 0, 100); // Cut to 100 characters (current limit of the database field)
                    // When data exists but is empty (empty string), also clean the cache field (don't save empty-string cache fields). It does keep cache output for number "0"
                    if(strlen($cache_output) == 0)
                        $cache_output = null;
                }
            }

            // Skip cache elements which have not been affected in any way (still contain the initial "false" value) - Also skipped on error
            if($cache_output === false)
                continue;

            // Enclose if the output is non-null
            if(!is_null($cache_output) && $enclose)
                $cache_output = "'{$cache_output}'";

            // if an entry is stored as "null", it means the cache field has no value (and is supposed to be deleted by writing "null" in the db)
            $CFields['cache_'.$cache_field] = $cache_output;
        }

        return $CFields;
    }

    /**
     * Adds a record to the system, according to the specified type
     *
     * @param int $rt_id
     * @param array $issue_data Information placed in the primary table (lots of fields).
     * @param array $xfields Extra fields stored in the issue (can be anything) - Optional, but without them the "issue" is kinda useless as it has almost no information
     * @param bool $read_ist Attempt to read IST before creating (not really sure for what is this useful - might be a relic. TODO: Check !)
     * @param bool $check_missing_xf For processes that are creating records without human input, setting to FALSE will skip mandatory field checks
     * @param array $org_data Allows the create function to feed back organization data into the calling method. This is used by the processing plugin.
     * @param bool $raw_mode If True, the xfields data is raw data as stored in the system (copy from anoter record, or from a backup) - Doesn't check RT or process xfields
     * -- In raw mode, cache fields (primary, secondary,...) are not prepared, so they must be present in $issue_data or nothing will be stored!
     * -- Also, in raw mode, issue type definition is not needed, as nothing is converted
     */
    public function create($rt_id, $issue_data, $xfields=array(), $read_ist = false, $check_missing_xf = true, &$org_data = null, $raw_mode = false)
    {

       	//echo $xfields['shipper_n'] . '     Test Praneeth';
		// $raw_mode = true;
        
        if($read_ist)
            $this-> ist_Read($rt_id);
        if($raw_mode == false && empty($this-> ist_def))
            return false; // error, wrong issue type id?

           
    
        // add the auto-generated data (track, ctime, submit_ip, access_key) - The rest are defaulted by the DB
        if(!isset($issue_data['track']))
            $issue_data['track'] = $this-> generate_TrackID();
        $issue_data['type_id'] = $rt_id;
        if(!isset($issue_data['ctime']))
            $issue_data['ctime'] = time();
           if($rt_id=='55' && isset($xfields['name_title']) && $xfields['name_title']!=''){ // for contract manager module , record name or title field will be cache primary 
            $issue_data['cache_primary']=$xfields['name_title'];            
        }
        if($rt_id=='56' && isset($xfields['title']) && $xfields['title']!=''){ // for asset control module , record name or title field will be cache primary 
            $issue_data['cache_primary']=$xfields['title'];            
        }
           
        $issue_data['submit_ip'] = sprintf('%u', ip2long(isset($issue_data['submit_ip'])? $issue_data['submit_ip']: $_SERVER['REMOTE_ADDR']));
        $issue_data['access_key'] = sha1(microtime(1) . uniqid()); // Extra security step for some public operations
        // prepare the extra fields if $xfields is not empty and not in RAW mode (actually, $xfields most likely will never be empty!)
        if(!empty($xfields) && $raw_mode == false) {
            $r = $this-> prepare_XFields($xfields, $check_missing_xf, false);
            if(!empty($r))
                return $r; // there was a problem with xfields, so return the problematic fields

            // Processing Plugin Inject, right before the data is created
            if(!empty($this-> rec_plugin))
                $this-> rec_plugin-> process_Data(null, $issue_data, $xfields, $org_data);

            // If XField Cache is enabled for this issue type, determine the data that will be placed in the cache, from the xf_cache settings and the value of the xfields
            // That function also generates QuickIndex values
            if(!empty($this-> ist_def['settings']['xf_cache']))
                $issue_data = array_merge($issue_data, $this-> prepare_CFields($xfields));
        }      
        // build the query and run it - Primary table

     
        $q=QueryFactory::insert()
            -> table(MVC_Core::$db-> tab['iss'])
            -> values($issue_data);

        if(MVC_Core::$db-> query($q)===false)
            return false; // error, unable to insert the issue into it's primary table (wrong data sent?)

        $this-> issue = $issue_data;
        $this-> issue['id'] = MVC_Core::$db-> insert_id;

        // If there are xfields, store them !
        // echo "<pre>";
		// print_r($xfields);
        // exit;

        if(!empty($xfields))
            if($raw_mode)
                $this-> create_XFields_RAW($this-> issue['id'], $xfields);
            else {
                $this-> create_XFields($this-> issue['id'], $xfields);

                // Post process the cache data a bit so it looks like from a normal read
                if(!empty($this-> ist_def['settings']['xf_cache'])) {
                    $this-> issue['cache'] = array();
                    foreach($this-> ist_def['settings']['xf_cache'] as $cache_field=> $cache_settings) {
                        if(!empty($this-> issue['cache_'.$cache_field]))
                            $this-> issue['cache'][$cache_field] = $this-> issue['cache_'.$cache_field];
                        unset($this-> issue['cache_'.$cache_field]);
                    }
                }
            }

        // Insert the record ID into quickindex, and prepare the insert query
        // Also prepare a query which would delete older values containing same fk_id and rec_id
        if(!empty($this-> ist_def['settings']['xf_cache']['quickindex']) && !empty($this-> quickindex)) {
            $qi=QueryFactory::insert()
                -> table(MVC_Core::$db-> tab['rqi']);
            foreach($this-> quickindex as &$qi_entry) {
                $qi_entry['rec_id'] = $this-> issue['id'];
                $qi-> values($qi_entry);
            }
            // Add new data to the quickindex
            MVC_Core::$db-> query($qi);
            $this-> quickindex = array();
        }

        // save into the history that the issue has just been created
        $changes = array(
            'created'=> $issue_data['ctime'],
            'track'=> $issue_data['track'],
            'ip'=> $issue_data['submit_ip']
        );
        $this-> history_Create($this-> issue['id'], $changes, 'create');

        // DONE ! Issue has been inserted and can be accessed by calling $this-> read (already cached)
        return $this-> issue['id'];
    }

    /**
     * Very quick function that searches a record by it's Tracking ID or Access Key - Used to discover "id" when only the Track ID (or Access Key) is known.
     * - Aa an added bonus, when $track_id is numeric, it doesn't perform the search and simply returns it back
     * @param unknown $track_id
     * @return int The record's database ID
     */
    public function locate($locator)
    {
        if(is_numeric($locator))
            return (int)$locator;

        $q=QueryFactory::select()
            -> table(MVC_Core::$db-> tab['iss'], array('id'))
            -> limit(1);

        if(preg_match("/^[A-Z0-9]{3}-[A-Z0-9]{3}-[A-Z0-9]{4}$/", strtoupper($locator))) // Track ID
            $q-> where("`track` = ?", strtoupper($locator));
        elseif(strlen($locator)==40)
            $q-> where("`access_key` = ?", strtolower($locator));

        $r = MVC_Core::$db-> query($q)-> current();
        if(empty($r))
            return false; // Error, not found

        return (int)$r['id'];
    }

    /**
     * Searches records based on value of a QuickIndex field
     * - Optionally only returns records that have $org_id in either master_org_id or shared_org_ids
     * - Optionally skip record with mentioned ID
     * Doesn't return xfields or other complex data (this is used only to find certain records)
     * Note: Does not have a limit ! Returns ALL records that match.
     *
     * @param int $qi_id
     * @param string $value
     * @param array $wtr What to read
     * @param string $org_id Access restriction - Org ID for searcher
     * @param string $rec_id To avoid showing self when looking for "similar" records, when this value is present it skips one found record
     * @return array Short information about found records
     */
    public function search_By_Quickindex($qi_id, $value, $wtr = null, $org_id = null, $rec_id = null)
    {
        if(empty($wtr))
            $wtr = array('issue');

        $q=QueryFactory::select()
            -> table(MVC_Core::$db-> table('rqi'), array('id'=>"rec_id"))
            -> where("`rqi`.fk_id = ?", $qi_id)
            -> where("`rqi`.value = ?", $value)
            -> limit(50); // Displayed in a menu, avoid making it too long if too many results

        if(in_array('issue', $wtr))
            $q-> table(MVC_Core::$db-> table('iss'), "track,type_id,master_org_id,group_id,submit_id,cache_primary,cache_secondary", 'left', "`iss`.id = `rqi`.rec_id");
        else
            $q-> table(MVC_Core::$db-> table('iss'), "track", 'left', "`iss`.id = `rqi`.rec_id");

        if(in_array('submitter', $wtr)) // Submitter contact - Join to Contacts table
            $q-> table(array(MVC_Core::$db-> tab['ctx'],'subm'), QueryFactory::autoalias_Add_Prefix("type,user_id,email,name".(in_array('submitter_xdata', $wtr)? ",xdata": ""), 'submit_'), 'left', "`iss`.submit_id = `subm`.id");
        if(in_array('submitter_org', $wtr))
            $q-> table(array(MVC_Core::$db-> tab['org'],'subo'), QueryFactory::autoalias_Add_Prefix("name,contact_id", '_subo_'), 'left', "`iss`.submit_org_id = `subo`.id");

        // Organization filter?
        if(!empty($org_id))
            $q-> where("`iss`.submit_org_id = ? OR `iss`.master_org_id = ? OR FIND_IN_SET(?, `iss`.shared_org_ids)", $org_id, $org_id, $org_id);

        // Do not return self?
        if(!empty($rec_id))
            $q-> where("`iss`.id != ?", $rec_id);

        $r = MVC_Core::$db-> query($q);

        if(!$r || !count($r))
            return $r;

        $r-> split_by_prefixes = array(
            // tidy
            'cache'=> "cache_",
            'group'=> "_grp_",
            'submitter'=> "submit_",
            'submitter_org'=> "_subo_"
        );

        $all = array();
        foreach($r as $issue) {
            $issue['id'] = (int)$issue['id'];

            // Move the submitter organization information into the submitter key itself
            if(in_array('submitter_org', $wtr)) {
                if(!in_array('submitter', $wtr))
                    $issue['submitter'] = array();
                $issue['submitter']['org'] = $issue['submitter_org'];
                unset($issue['submitter_org']);
            }

            // Decode submitter xdata
            if(in_array('submitter_xdata', $wtr)) {
                if(!empty($issue['submitter']['xdata']))
                    $issue['submitter']['xdata'] = @json_decode($issue['submitter']['xdata'], true);
            }

            $all[] = $issue;
        }

        return $all;
    }

    /**
     * Finds and returns a single issue based on ID or tracking ID, and also stores it in the model.
     * -- The issue must be visible to the specified organization and department ( fields master_org_id, shared_org_ids, submit_org_id ) in order to even return any result.
     *
     * @param string $locator Key to search for ( number or string )
     * @param array $wtr Indicates what information to read ( if null or empty, just verify that the issue track(ing) id actually exists )
     * -- Filters (for notes, tasks, conversations and files)
     * @param int $org_id Organization filter ID - If present, contacts/notes/tasks/files and conversations might get filtered to only what the organization should see (own and shared/public visibility)
     * @param int $dep_id Department filter ID - Same thing but at department level (partially implemented)
     * @param int $user_id ID of the logged in user To support private notes/tasks/files and control visibility of conversations (partially implemented)
     * -- Note, it might be possible to check visibility under different conditions ( with partial filters, instead of all three parameters set )
     * @param array $settings Various settings for reading data (if empty, use defaults)
     */
    public function &read($locator, $wtr = array(), $org_id = null, $dep_id = null, $user_id = null, $settings = array()) // TODO: Support multiple organizations ($org_id = array(), for the supervisor organization abilities)
    {
        if(is_numeric($locator) && (int)$locator == $locator) {
            // if asking to read the issue with the ID which is already in the cache, return the cache instead of reading the issue from DB
            // Disabled, cache is causing problems
// 			if($this-> issue['id'] == $locator)
// 				return $this-> issue;
            $wtl = "id";
        } elseif(substr($locator, 0, 8) == '#random#') {
            $wtl = "type_id"; $locator = (int)substr($locator, 8);
            $random = true;
        } else {
            // Disabled, cache is causing problems
// 			if($this-> issue['track'] == $locator)
// 				return $this-> issue;
            $wtl = "track";
        }

        // Begin building the query using QF, its content depends on $wtr, $wtl
        $q=QueryFactory::select()
            -> where("`iss`.{$wtl} = ?", $locator)
            -> limit(1);

        if(isset($random))
            $q-> order("rand()");

        // Built-in access filters/limitations
        if(!empty($org_id))
            // If $org_id is not null, the issue's "org_id" field must be this org_id or the field "shared_org_ids" must contain this value - Otherwise the query will not return the issue
            // Also, users from the submitter organization (if submitted from staff side) can ALWAYS see the issue, even if they transferred control
            $q-> where("`iss`.submit_org_id = ? OR `iss`.master_org_id = ? OR FIND_IN_SET(?, `iss`.shared_org_ids)", $org_id, $org_id, $org_id);

        // What to read?
        $wtr = $this-> wtr_Helper($wtr);

        if(in_array('issue', $wtr))
            // Complete primary table
            $q-> table(MVC_Core::$db-> table('iss'));
        else
            // Basic inforamtion (to test the $locator, but also for organization-wide visibility/access purposes)
            $q-> table(MVC_Core::$db-> table('iss'), 'id,track,type_id,ctime,master_org_id,shared_org_ids,group_id,submit_domain,submit_org_id,submit_id');

        // Read information for the Master (automatic) or the Perspective organization ($org_id)
        // -- If no organization is master (orphaned, incomplete records) and no perspective is specified, all these fields will be null)
        if(in_array('organization', $wtr)) {
            // Join organization specific data to the issue (status, priority, some timers, statistics) - Always read for individual issue accessed
            $os_fields = array(
                'process', // Process marker, indicates what's going on with this record at organization level
                'dep_ids','count_contacts','count_files','filesize_total','count_convos','count_notes','count_tasks','count_active_tasks','count_propfields',
                'priority_key','status_key','xfer_by','xfer_to','xfer_time','xfer_ip','xfer_confirmip',
                'assigned_to', 'exclusive_edit', 'access_users',
                'linktime', 'time_open', 'time_start', 'commtime', 'access_ut', 'time_close', // Time fields ; These should actually have reverse name ( time_* ) and be packed in an array, along with ctime, utime,  edtime
                'pending_length', 'process_length'
            );
            $q-> table(MVC_Core::$db-> table('iod'), QueryFactory::autoalias_Add_Prefix($os_fields), 'left', "`iss`.id = `iod`.issue_id AND `iod`.org_id = ". (!empty($org_id)? $org_id: "`iss`.master_org_id"));
            $q-> table(MVC_Core::$db-> table('org'), QueryFactory::autoalias_Add_Prefix("id,type_id,name,count_users,contact_id", '_org_'), 'left', "`org`.id = `iod`.org_id");

            // Assigned user (Related to the organization)
            if(in_array('assigned', $wtr))
                $q-> table(MVC_Core::$db-> table('usr'), QueryFactory::autoalias_Add_Prefix("id,name,email,contact_id", '_usr_'), 'left', '`usr`.id = `iod`.assigned_to');

        } else {
            // Even if linked organization itself is not read, some fields from the organization related data are necessary;
            // It might happen however that the IOD data is completely missing (record not linked to any organization); In that case "process", "priority_key", "status_key" and others will be Null
            $os_fields = array(
                'process', 'dep_ids', // Process marker and department list (necessary for visibility)
                'priority_key', 'status_key',
                'assigned_to', 'exclusive_edit', 'access_users',
                'linktime' // Used to calculate time differences on process changes
            );
            $q-> table(MVC_Core::$db-> table('iod'), QueryFactory::autoalias_Add_Prefix($os_fields), 'left', "`iss`.id = `iod`.issue_id AND `iod`.org_id = ". (!empty($org_id)? $org_id: "`iss`.master_org_id"));
        }

        // Allow reading a hidden record (archived/pseudo-deleted)
        if(empty($settings['access_hidden']))
            $q-> where("`iod`.process != 'hidden' OR `iod`.process IS NULL");

        // Department filter can have effect only when organization-specific data was actually read
        if(!is_null($dep_id) && !isset($settings['ignore_dep_filter']))
            // If $dep_id is not null, this value must be present in the "dep_ids" if that field is not null - Otherwise the query will not return the issue
            $q-> where("`iod`.dep_ids IS NULL OR FIND_IN_SET(?, `iod`.dep_ids)", $dep_id);

        $typejoin_cond = isset($settings['ist_or'])? "`ist`.id = {$settings['ist_or']}": '`ist`.id = `iss`.type_id';
        // Extra Joins
        if(in_array('type', $wtr)) // Quick type information, usually used when the full IST data definition ($wtr:typedef) is not needed
            $q-> table(MVC_Core::$db-> table('ist'), QueryFactory::autoalias_Add_Prefix("scope,name,key,short_plural", '_ist_'), 'left', $typejoin_cond);

        if(in_array('typeex', $wtr)) // Extended type information, without the fields (includes description, statuses, priorities, settings). Decodes JSON fields
            $q-> table(MVC_Core::$db-> table('ist'), QueryFactory::autoalias_Add_Prefix("scope,name,key,short_plural,description,statuses,priorities,settings", '_ist_'), 'left', $typejoin_cond);

        if(in_array('group', $wtr)) // The group to which this issue is connected
            $q-> table(MVC_Core::$db-> table('grp'), QueryFactory::autoalias_Add_Prefix("type,name", '_grp_'), 'left', '`grp`.id = `iss`.group_id');

        if(in_array('submitter', $wtr)) // Submitter contact - Join to Contacts table
            $q-> table(array(MVC_Core::$db-> tab['ctx'],'subm'), QueryFactory::autoalias_Add_Prefix("type,user_id,email,name".(in_array('submitter_xdata', $wtr)? ",xdata": ""), 'submit_'), 'left', "`iss`.submit_id = `subm`.id");
        if(in_array('submitter_organization', $wtr))
            $q-> table(array(MVC_Core::$db-> tab['org'],'subo'), QueryFactory::autoalias_Add_Prefix("type_id,name,contact_id", '_subo_'), 'left', "`iss`.submit_org_id = `subo`.id");

        // when reading xfields, the complete field definition is necessary
        if(in_array('xfields', $wtr))
            $wtr[] = 'typedef';

        // read the issue information and arrange the data
        $r = MVC_Core::$db-> query($q);

        if(!$r || count($r) == 0) {
            // Error, issue could not be read (not found?)
            $this-> issue = null;
            return $this-> issue;
        }

        $r-> split_by_prefixes = array(
            // tidy
            'cache'=> "cache_",
            'count'=> "count_", // from IOD
            'transfer'=> "xfer_", // from IOD
            // extra tables
            'type'=> "_ist_",
            'organization'=> "_org_", // Perspective
            'group'=> "_grp_",
            'submitter'=> "submit_",
            'submitter_org'=> "_subo_",
            'assigned'=> "_usr_"
        );

        $this-> issue = $r-> current();

        // List of organizations with access to this issue should always be an array
        if(!empty($this-> issue['shared_org_ids'])) {
            $this-> issue['shared_org_ids'] = explode(',', $this-> issue['shared_org_ids']);
            $this-> issue['shared'] = true;
        } else {
            $this-> issue['shared_org_ids'] = array();
            $this-> issue['shared'] = false;
        }

        // Always present elements
        $this-> issue['id'] = (int)$this-> issue['id'];
        $this-> issue['group_id'] = (int)$this-> issue['group_id'];

        // Post-Processing of record primary data
        if(in_array('issue', $wtr)) {
            // Attached data counters should be int and default to 0
            if(isset($this-> issue['count'])) {
                $count_fields = array('contacts', 'convos', 'files', 'notes', 'propfields', 'tasks');
                foreach($count_fields as $cf)
                    $this-> issue['count'][$cf] = (int)$this-> issue['count'][$cf];
            }

            // Creation, Update (database automatic), Editing time - These are used to show when important events have happened
            $this-> issue['ctime'] = (int)$this-> issue['ctime'];
            $this-> issue['utime'] = strtotime($this-> issue['utime']);
            if(!empty($this-> issue['edtime']))
                $this-> issue['edtime'] = (int)$this-> issue['edtime'];

            // Move the submitter organization information into the submitter key itself
            if(in_array('submitter_org', $wtr)) {
                if(!in_array('submitter', $wtr))
                    $this-> issue['submitter'] = array();
                $this-> issue['submitter']['org'] = $this-> issue['submitter_org'];
                unset($this-> issue['submitter_org']);
            }

            // Decode submitter xdata
            if(in_array('submitter_xdata', $wtr)) {
                if(!empty($this-> issue['submitter']['xdata']))
                    $this-> issue['submitter']['xdata'] = @json_decode($this-> issue['submitter']['xdata'], true);
            }
            $this-> issue['submitter']['ip'] = long2ip($this-> issue['submitter']['ip']);
        }

        if(in_array('typeex', $wtr)) {
            $this-> issue['type']['statuses'] = explode(',', $this-> issue['type']['statuses']);
            $this-> issue['type']['priorities'] = explode(',', $this-> issue['type']['priorities']);
            $this-> issue['type']['settings'] = @json_decode($this-> issue['type']['settings'], true);
        }

        // When typedef is requested, read complete IST
        if(in_array('typedef', $wtr))
            $this-> ist_Read(isset($settings['ist_or'])? $settings['ist_or']: $this-> issue['type_id'], false, false, false, isset($settings['ignore_xf_hidden']));

        // Extra filter for the group name, remove any and all tags which are between square brackets
        if(in_array('group', $wtr))
            $this-> issue['group']['name'] = trim(preg_replace('/\[.*?\]/', "", $this-> issue['group']['name']));

        // there is a list of fields that should be read, instead of all xfields, so assamble the array with IDs
        // Can be used by both xfields and xfields_raw reading
        if(isset($wtr['xflist'])) {
            $fk_id_list = array();
            foreach($wtr['xflist'] as $fk) {
                if(isset($this-> ist_def['xfield_def'][$fk]))
                    $wtr['xf_ids'] = $this-> ist_def['xfield_def'][$fk]['id'];
            }
        } else
            $wtr['xf_ids'] = null;

        // Read the eXtra fields the fields storage table, and place them inside $this-> issue;
        if(in_array('xfields', $wtr)) {
            $this-> issue['xfields'] = $this-> read_XFields($this-> issue['id'], !empty($settings['serialize']), !empty($settings['htmlsc']), !empty($settings['form_compat']), $wtr['xf_ids']);
            if(!empty($settings['serialize']) && !empty($settings['serialize_source']))
                // Request the xfields again, without serialization or other conversions
                $this-> issue['xfields_source'] = $this-> read_XFields($this-> issue['id'], null, null, null, $fk_id_list);

            // Extra verifications related to xfields which should have values even if nothing is coming from the database
            foreach($this-> ist_def['xfield_def'] as $fk=> &$fdef) {
                switch($fdef['t']) {
                    case 'set':
                        // SET fields with "flag" = true must have values (all 0) even if not present in the database (But not in serialize mode - Ignored)
                        if(empty($settings['serialize']) && !empty($fdef['o']) && !empty($fdef['o']['flag']) && !isset($this-> issue['xfields'][$fk])) {
                            $this-> issue['xfields'][$fk] = array();
                            foreach($fdef['cd_ex'] as $opt=> $value)
                                $this-> issue['xfields'][$fk][] = array(
                                    'key'=> $opt,
                                    'value'=> $value,
                                    'active'=> false
                                );
                        }
                }
            }
            unset($fdef);
        }

        // Special version of xfields reading where the record type is not considered - Only pure raw data. The values of the fields are not processed in anyway.
        // -- This is only used for data extraction/copy/backup purposes
        if(in_array('xfields_raw', $wtr))
            $this-> issue['xfields_raw'] = $this-> read_XFields_RAW($this-> issue['id'], isset($wtr['xf_ids'])? $wtr['xf_ids']: null, !empty($settings['raw_use_keys']));

        // Post process organization-specific data
        if(in_array('organization', $wtr)) {

            $this-> issue['organization']['id'] = (int)$this-> issue['organization']['id'];
            $this-> issue['organization']['count_users'] = (int)$this-> issue['organization']['count_users']; // If there are no users, assign should be disabled

            // Organization type information (this is not in DB, it's loaded from YAML files)
            if(in_array('orgtype', $wtr)) {
                $m_org = new App_Model_Organization();
                $this-> issue['organization']['orgtype'] = $m_org-> orgtype_Read($this-> issue['organization']['type_id'], true); // Restricted orgtype info
            }

            // Organization "Primary" contact (Only if one has been set)
            if(!empty($this-> issue['organization']['contact_id'])) {
                $this-> issue['organization']['contact_id'] = (int)$this-> issue['organization']['contact_id'];
                if(in_array('orgcontact', $wtr)) {
                    $m_con = new App_Model_Contact();
                    $this-> issue['organization']['contact'] = $m_con-> read($this-> issue['organization']['contact_id']);
                }
            }

            $this-> issue['dep_ids'] = !empty($this-> issue['dep_ids'])? explode(',', $this-> issue['dep_ids']): array();

            if(in_array('departments', $wtr)) {
                // TODO: Read the basic information about the departments that are given access to this record
            }

            // Status and Priority translation
            $this-> issue['status'] = !empty($this-> issue['status_key']) && isset($this-> statuses[$this-> issue['status_key']])? $this-> statuses[$this-> issue['status_key']]: self::sMISSING;
            $this-> issue['priority'] = !empty($this-> issue['priority_key']) && isset($this-> priorities[$this-> issue['priority_key']])? $this-> priorities[$this-> issue['priority_key']]: self::sMISSING;

            // Time fields present in the IOD:
            // After everything is renamed (including linktime, commtime, which are currently used in other parts), this will be simpler (array walk)
            foreach(array('linktime', 'time_open', 'time_start', 'commtime', 'access_ut', 'time_close') as $time_field) {
                if(!empty($this-> issue[$time_field]))
                    $this-> issue[$time_field] = (int)$this-> issue[$time_field];
            }

            // Xfer (Share/Transfer)
            if(!empty($this-> issue['transfer']['time']))
                $this-> issue['transfer']['time'] = (int)$this-> issue['transfer']['time'];
            if(!empty($this-> issue['transfer']['ip']))
                $this-> issue['transfer']['ip'] = long2ip($this-> issue['transfer']['ip']);
            if(!empty($this-> issue['transfer']['confirmip']))
                $this-> issue['transfer']['confirmip'] = long2ip($this-> issue['transfer']['confirmip']);

            // Assignment
            if(in_array('assigned', $wtr) && !empty($this-> issue['organization']['access_ut'])) {
                $this-> issue['assigned']['time'] = (int)$this-> issue['organization']['access_ut'];
                unset($this-> issue['organization']['access_ut']);
            }

// 			'assigned_to', 'exclusive_edit', 'access_users', 'access_time'
            // Active Accessing Users - Should be ON when loading the record in "Process" mode
            if(in_array('access_users', $wtr)) {
                // TODO: Implement list of users currently accessing the record (updated every time the record is being read/opened with this wtr option present)
            }
        }

        // Read the names of the organizations sharing the record, and their "enabled" flag (to be listed in Admin's "Perspective" selector, and also used for certain special linking functions)
        if(in_array('shared_orgs', $wtr)) {
            $q=QueryFactory::select()
                -> table(MVC_Core::$db-> table('iod'), 'process,is_enabled')
                -> table(MVC_Core::$db-> table('org'), 'id,type_id,name', 'left', "`org`.id = `iod`.org_id")
                -> where("`iod`.issue_id = ?", $this-> issue['id'])
                -> order("`org`.name ASC");

            $r = MVC_Core::$db-> query($q);
            if($r && count($r)>0) {
                $this-> issue['shared_orgs'] = array();
                foreach($r as $sho) {
                    $sho['id'] = (int)$sho['id'];
                    $sho['disabled'] = !$sho['is_enabled'];
                    if($sho['id'] == $this-> issue['master_org_id'])
                        $sho['master'] = true;
                    $this-> issue['shared_orgs'][] = $sho;
                }
            } else
                // Error, organizations could not be read or there are no organizations connected to the issue
                $this-> issue['shared_orgs'] = array();
        }

        // Read quickindex entries and add them to the record
        // This can be used to show one or multiple important xfields (in text render format), without actually reading xfields
        if(in_array('quickindex', $wtr)) {
            $q=QueryFactory::select()
                -> table(MVC_Core::$db-> table('rqi'), 'fk_id,value')
                -> where("`rqi`.rec_id = ?", $this-> issue['id']);

            $r = MVC_Core::$db-> query($q);
            if($r && count($r)>0) {
                $this-> issue['quickindex'] = array();
                foreach($r as $qiv)
                    $this-> issue['quickindex'][(int)$qiv['fk_id']] = $qiv['value'];
            } else
                // No index values found, perhaps this record was not updated since QuickIndex was defined? Or there is no QuickIndex at all for this record type.
                $this-> issue['quickindex'] = array();
        }

        // Linked contacts (issue_contacts)
        if(in_array('contacts', $wtr)) {
            $m_con = new App_Model_Contact();

            if(isset($settings['public']))
                $contacts_vis = "public";
            elseif(isset($settings['partner']))
                $contacts_vis = "partner";
            elseif(empty($org_id))
                $contacts_vis = "unrestricted"; // Show everything (for admin use)
            else
                $contacts_vis = "org";

            $r = $m_con-> con2iss_Get_List($this-> issue['id'], in_array('contacts.xdata', $wtr), $contacts_vis, $org_id);

            $this-> issue['contact_links'] = array();
            if($r && count($r)>0)
                foreach($r as $contact_links) {
                    if(!isset($this-> issue['contact_links'][$contact_links['contact_id']]))
                        $this-> issue['contact_links'][$contact_links['contact_id']] = $contact_links;
                }
        }

        // Read the conversations metadata
        // FIXME: Work in progress...
        if(in_array('conversations', $wtr)) {
            $m_cnv = new App_Model_Conversation();
            if(!isset($m_con))
                $m_con = new App_Model_Contact();
            $m_comm = new App_Model_Comm();

            $r = $m_cnv-> get_List("issue", $this-> issue['id'], array('convo'), function(QueryFactory &$q) use($user_id, $dep_id, $org_id, $settings) {
                // Filter list of conversations depending on logged in user's access and visibility settings
                if(isset($settings['public']) && $settings['public'])
                    $q-> where("`cnv`.visibility = 'public'");
                else {
                    // TODO: Review the conversations visibility ! This is actually terribly bad...

                    if(isset(MVC_Core::$ss-> user['is_admin']))
                        return; // No restrictions, all conversations linked to the issue ar visible for admin.

                    $q-> where("`cnv`.visibility != 'hidden'");
                    if(!empty($org_id))
                        $q-> where("(CASE WHEN `cnv`.visibility = 'org' THEN `cnv`.org_id = ? ELSE 1 END)", $org_id);
                }
            }, null, "`cnv`.`last_msg_time` DESC");

            if($r && count($r)> 0) {
                $this-> issue['conversations'] = array();
                foreach($r as $convo) {
                    $convo['contact_id_list'] = explode(',', $convo['contact_ids']);
                    // Read the contacts involved in the conversation - They will be needed in the details panel
                    $convo['contacts'] = $m_con-> get_List(null, $convo['contact_id_list'], 'idlist', array('contact'),
                        "FIELD(`ctx`.id, {$convo['contact_ids']})", 0);// Sort the contacts present in the conversation by their order in the list, so that the first one is always the conversation starter

                    // Prepare the data that will be returned to javascript
                    $row = array(
                        'id'=> (int)$convo['id'],
                        'key'=> $convo['access_key'],
                        'visibility'=> $convo['visibility'],
                        'count_msgs'=> (int)$convo['count']['msgs'],
                        'count_ctx'=> count($convo['contact_id_list']),
                        'description'=> $convo['description'],
                    );
                    if($convo['start_msg_id'] > 0) {
                        $row['starter'] = array(
                            'id'=> (int)$convo['start_msg_id'],
                            'from_id'=> (int)$convo['contact_id_list'][0],
                            'from_email'=> $convo['contacts'][0]['email'],
                            'from_name'=> $convo['contacts'][0]['name'],
                            'time'=> date("Y-m-d H:i:s", $convo['start_msg_time'])
                        );
                    }

                    if(!empty($convo['last_msg_sender'])) {
                        $row['last_msg'] = array();
                        $row['last_msg']['from_id'] = (int)$convo['last_msg_sender'];
                        $row['last_msg']['time'] = date("Y-m-d H:i:s", $convo['last_msg_time']);
                    }
                }
                $this-> issue['conversations'][] = $row;
            }
        }

        // Read the list of attachments for this issue
        // FIXME: The way files are accessed here by visibility is no longer ok, check and improve. However, this thing is still used for external view, rendering e-mails and so on.
        if(in_array('files', $wtr)) {
            $files_read = array('file', 'extra');
            if(in_array('files_user', $wtr))
                $files_read[] = "extra_user";
            if(in_array('files_org', $wtr))
                $files_read[] = "extra_org";

            $files = new App_Model_File();
            $r = $files-> get_List('issue', $this-> issue['id'], $files_read, function(QueryFactory &$q) use($user_id, $dep_id, $org_id, $settings) {
                // Filter list of files depending on logged in user's access and visibility settings

                if(isset($settings['public']) && $settings['public'])
                    // Force public visibility for files?
                    $q-> where("`fil`.visibility = 'public'");
                else {
                    if(isset(MVC_Core::$ss-> user['is_admin']))
                        return; // No restrictions, all conversations linked to the issue ar visible for admin.

                    if(!empty($org_id))
                        $q-> where("(CASE WHEN `fil`.visibility = 'org' THEN `fil`.org_id = ? ELSE 1 END)", $org_id);
                    if(!empty($dep_id))
                        $q-> where("(CASE WHEN `fil`.visibility = 'dep' THEN `fil`.dep_id = ? ELSE 1 END)", $dep_id);
                    if(!empty($user_id))
                        $q-> where("(CASE WHEN `fil`.visibility = 'private' THEN `fil`.user_id = ? ELSE 1 END)", $user_id);
                }
            }, null, isset($settings['files'])? '`fil`.'.$settings['files']['order']: null);
            if($r && count($r)>0)
                foreach($r as $file) {
                    if(is_null($file['user_id']))
                        unset($file['user']);
                    // Combine some values into a "title" (for file selectors)
                    $file['title'] = !empty($file['description'])? $file['description']: preg_replace('/\\.[^.\\s]{2,4}$/', '', $file['name']);
                    $this-> issue['files'][$file['id']] = $file;
                }
            else
                $this-> issue['files'] = array();
        }
        return $this-> issue;
    }

    /**
     * Updates a record with new data
     *
     * @param int|string $locator
     * @param array $metadata Information for the primary and organization tables (automatically filtered) to be updated; May contain special entries:
     * -- "_org_id" - For which organization are the metadata fields being updated ? (Used with organization sharing)
     * -- "_original" - Readout of the original metadata (not always present) - Sent to the processing plugin for reference
     * -- "_update_type" - New format Update type
     * -- "_update_data" - New format pre-set Update data (extra information stored in the update entry, not necessarily related to the record content)
     * -- "_con_id" - When the update comes from a contact account or external event (eg: clicking on an e-mail link)
     * @param array $xfields The extra fields which contain the flexible record contents
     * @param bool $clean_xfields False (default) - only remove the fields that have actually been changed; True - remove all fields and only recreate the sent ones (Dangerous !)
     * @param string $string_expressions This parameter is sent to query factory, and is used to disable string escaping (in order to allow expressions, for example to increment a counter)
     * @param bool $check_missing_xfields If true, Update verifies all xfields just like for the create operation
     * @param string $comment Comment on the update/human readable information, will be stored in the update entry if present
     * @return int|bool|array ID of the record that was updated | false = error | array() = xfields incorrect, the return contains a report
     */
    public function update($locator, $metadata, $xfields=array(), $clean_xfields = false, $string_expressions = false, $check_missing_xfields = false, $comment = null)
    {
        if(($id = $this-> locate($locator))===false)
            return MVC_Core::$debug-> dump("Cannot find record: {$locator}", __METHOD__, null, "warning");

        if(empty($metadata)) {
            $metadata = array(); // No metadata changed, only $xfields
            if(empty($xfields))
                return MVC_Core::$debug-> dump("Neither metadata nor xfields contain any information - Nothing to update", __METHOD__, null, "notify");
        }

        // Caller function also asked to update organization-specific data, for one of the orgs sharing the record (could be status, priority, custom fields, etc.)
        // Known org-specific fields are moved out of $metadata into $orgdata
        if(isset($metadata["_org_id"])) {
            $org_id = (int)$metadata["_org_id"];
            $orgdata = array();
            foreach($metadata as $field=> $value)
                if(in_array($field, self::$org_columns)) {
                    $orgdata[$field] = $value;
                    unset($metadata[$field]);
                }

            // Also move from $metadata['_original'] if it exists and this is an organization-table field
            if(isset($metadata['_original'])) {
                $orgdata['_original'] = array();
                foreach($metadata['_original'] as $field=> $value)
                    if(in_array($field, self::$org_columns)) {
                        $orgdata['_original'][$field] = $metadata['_original'][$field];
                        unset($metadata['_original'][$field]);
                    }
            }
        }

        // Filter out keys which cannot be updated by this function or do not belong to this table
        $fields = array(
            '_original', '_update_type', '_update_data', '_con_id',		// Keep special entires until reaching the save stage
            'track', 'access_key', 'edtime', 'master_org_id', 'shared_org_ids', 'group_id', 'submit_org_id', 'submit_id', 'submit_ip',
            'cache_primary', 'cache_secondary', 'cache_tertiary', 'cache_quaternary', 'cache_quinary',	// Cache fields
//            'form_update',                                              //fields update data
//			'c_convos', 'c_files', 'c_tasks', 'c_docs', 'c_ctx',		// Global counters (no longer used)
            'sui_lmid', 'sui_lmdt', 'sui_lmctx'							// SuperUser Information
        );
        foreach($metadata as $field=> $value)
            if(!in_array($field, $fields))
                unset($metadata[$field]);

        // Prepare and update xfields if there are any
        if(!empty($xfields)) {
            $r = $this-> prepare_XFields($xfields, $check_missing_xfields, true); // Update mode, allows clearing fields
            if(!empty($r))
                return $r; // there was a problem with one or more xfields, so return the list of errors (multiple)
        }

        // get original and current form values of updated field
//        $comment = $this->_processFieldUpdate($metadata['form_update'], $metadata['_original']['xfields_raw'], $xfields);

        // primary table pre-process
        if(isset($metadata['submit_ip']))
            $metadata['submit_ip'] = sprintf('%u', ip2long($metadata['submit_ip']));

        if(isset($metadata['shared_org_ids']))
            if(!empty($metadata['shared_org_ids'])) {
                if(is_array($metadata['shared_org_ids']))
                    $metadata['shared_org_ids'] = implode(',', $metadata['shared_org_ids']);
            } else
                $metadata['shared_org_ids'] = null;

        // ... and run through the processing plugin
        if(isset($this-> ist_def['settings']['plugin']) && !empty($xfields)) {
            // Process Plugin Inject, right before the data is changed in the DB
            if(!empty($this-> rec_plugin))
                if(isset($org_id))
                    $this-> rec_plugin-> process_Data($id, $metadata, $xfields, $orgdata, $org_id);
                else
                    $this-> rec_plugin-> process_Data($id, $metadata, $xfields);
        }

        // Plugin and Caching finished - Compute differential data; Note that due to panel split, fields which are not present in $xfields are ignored from original
        if(isset($metadata['_original']) && isset($metadata['_original']['xfields_raw'])) {
            $xf_original = &$metadata['_original']['xfields_raw'];

            // Build up Add, Update, Remove lists for the history/updates data
            $xf_diff = array(
                'add'=> array(),
                'upd'=> array(),
                'rem'=> array()
            );
            $xf_diff_count = 0;

            // Determine differences between original and updated fields
            foreach($xfields as $key=> $xf_store) {
                // Empty rows get checked if they had value before
                if(empty($xf_store) || (is_array($xf_store) && empty($xf_store)))
                    if(isset($xf_original[$key])) {
                        $xf_diff['rem'][$key] = $xf_original[$key]; // Store the original before deletion
                        $xf_diff_count++;
                        continue;
                    }
                    else {
                        unset($xfields[$key]);
                        continue; // Just skip empty ones that didn't exist before, nothing to do
                    }

                if(isset($xf_original[$key])) {
                    if($xf_store == $xf_original[$key]) {
                        unset($xfields[$key]); // Don't touch (skip) UNCHANGED field (like it wasn't part of the update form)
                    } else {
                        $xf_diff['upd'][$key] = $xf_original[$key]; // Store the original before updating
                        $xf_diff_count++;
                    }
                } else {
                    $xf_diff['add'][] = $key; // Store the fact that this field was added (but don't store the content, since it exists in the record itself)
                    $xf_diff_count++;
                }
            }
        }

        // If XField Cache is enabled for this record type, determine the data that will be placed in the cache, from the value of the xfields
        // ... but only if changes actually happened
        if(!empty($xfields) && !empty($this-> ist_def['settings']['xf_cache'])) {
            // When original fields are also present, send the combined data to prepare_CFields so the cache fields can be computed from any XF, not just those that were updated
            if(isset($metadata['_original']) && isset($metadata['_original']['xfields_raw']))
                $cf_source = array_merge($metadata['_original']['xfields_raw'], $xfields);
            else
                $cf_source = $xfields;

            $metadata = array_merge($metadata, $this-> prepare_CFields($cf_source, $string_expressions, true, array_keys($xfields)));

            // Special processing for $metadata['cache_primary'] in case it exists but it's null (resulted from empty fields)
            // This should not apply when fields affecting cache_primary were not present in the source
            if(array_key_exists('cache_primary', $metadata) && is_null($metadata['cache_primary'])) {
                $metadata['cache_primary'] = "ID: {$id}";
                if(isset($metadata['_original']['track']))
                    $metadata['cache_primary'].=", Track: {$metadata['_original']['track']}";
            }

            $update_cache = array();
            // If _original.cache exists, compare the values for cache entries and if unchanged drop them
            // (so primary metadata and history only receive cache entries only when they are actually changed - replaced, added or removed)
            if(isset($metadata['_original']['cache'])) {
                if(array_key_exists('primary', $metadata['_original']['cache']) && array_key_exists('cache_primary', $metadata))
                    if($metadata['cache_primary'] == $metadata['_original']['cache']['primary']) // Drop when equal
                        unset($metadata['cache_primary']);
                    else // Store previous value in update
                        $update_cache['primary'] = $metadata['_original']['cache']['primary'];

                if(array_key_exists('secondary', $metadata['_original']['cache']) && array_key_exists('cache_secondary', $metadata))
                    if($metadata['cache_secondary'] == $metadata['_original']['cache']['secondary'])
                        unset($metadata['cache_secondary']);
                    else
                        $update_cache['secondary'] = $metadata['_original']['cache']['secondary'];

                if(array_key_exists('tertiary', $metadata['_original']['cache']) && array_key_exists('cache_tertiary', $metadata))
                    if($metadata['cache_tertiary'] == $metadata['_original']['cache']['tertiary'])
                        unset($metadata['cache_tertiary']);
                    else
                        $update_cache['tertiary'] = $metadata['_original']['cache']['tertiary'];
                // ---- Skipped quaternary and quinary as they are neve used (so far)
            }

            // When $metadata['cache_*'] contains values, add an entry to the History
            if(!empty($update_cache))
                $this-> history_Create($id, $update_cache, "cache", $comment);
        }

        // The updating is actually deletion of fields and recreation of the non-empty ones (optionally, only clean/recreate the affected xfields). Only if there's anything to update
        // - The reason for this is because empty Xfields are not stored (at all), so any form field which doesn't represent a valid entry is completely eliminated from the database (on both create and update)
        // - This keeps the number of rows in the "fields" table to the minimum possible.
        // - A future version of the xfields storage mechanism might even spread xfields into multiple tables, to make searching for one particular field as fast as possible.

        // - Extra: QuickIndex for Xfields is also processed here
        if(!empty($xfields)) {
            if($clean_xfields)
                $this-> delete_XFields($id);
            else
                $this-> delete_XFields($id, array_keys($xfields));

            $this-> create_XFields($id, $xfields);
        }

        if(!empty($this-> quickindex)) {
            // Insert the record ID into quickindex, and prepare the insert query
            // Also prepare a query which would delete older values containing same fk_id and rec_id
            $qi=QueryFactory::insert()
                -> table(MVC_Core::$db-> tab['rqi']);
            $qd=QueryFactory::delete()
                -> table(MVC_Core::$db-> tab['rqi']);
            foreach($this-> quickindex as &$qi_entry) {
                $qi_entry['rec_id'] = $id;
                $qi-> values($qi_entry);
                // Delete any previous matching entries
                $qd-> where("`fk_id` = ? AND `rec_id` = ?", $qi_entry['fk_id'], $id);
            }
            // Execute the delete...
            MVC_Core::$db-> query($qd);
            // ... and the insert. New data will be placed in the index
            MVC_Core::$db-> query($qi);
        }

        // Plugin implemented and there are differences ?
        if(isset($this-> ist_def['settings']['plugin']) && !empty($xf_diff_count)) {
            // Process Plugin Inject, right before the data is changed in the DB
            if(!empty($this-> rec_plugin) && method_exists($this-> rec_plugin, "xfdiff_Trigger"))
                if(isset($org_id))
                    $this-> rec_plugin-> xfdiff_Trigger($id, $xf_diff, $metadata, $orgdata, $org_id);
                else
                    $this-> rec_plugin-> xfdiff_Trigger($id, $xf_diff, $metadata);
        }

        // Update organization-specific data if it contains anything

        if(isset($org_id) && !empty($orgdata)) {
            unset($orgdata['_original']);
            if(!empty($orgdata))
                $this-> org_UpdateData($id, $org_id, $orgdata);
        }

        $update_type = isset($metadata['_update_type'])? $metadata['_update_type']: null;
        $preset_update_data = isset($metadata['_update_data'])? $metadata['_update_data']: array();
        $con_id = isset($metadata['_con_id'])? (int)$metadata['_con_id']: null;

        unset($metadata['_original'], $metadata['_update_type'], $metadata['_update_data'], $metadata['_con_id'], $metadata['form_update']);
        // Update primary metadata
        if(!empty($metadata)) {
            $q= QueryFactory::update()
                -> table(MVC_Core::$db-> tab['iss'])
                -> values($metadata, null, $string_expressions)
                -> where("`id` = ?", $id);

            $r = MVC_Core::$db-> query($q);
            if(!$r)
                return MVC_Core::$debug-> dump("Error while attempting to update record {$locator}", __METHOD__, null, "error");
        }
        // On successful update, clear export markers for the record (for all users)
        $this-> marker_UpdateAll($id, array('export_ut'=> false));

        // Prepare and store the record update history entry
        $update_data = array_merge($metadata, $preset_update_data);

        if(!empty($orgdata)) { // Orgadata updates (secondary table)
            $update_data['_org_id'] = $org_id; // Indicates for which organization did the changes happen
            $update_data['_orgdata'] = $orgdata;
        }
        if(!empty($xf_diff_count)) { // Differences in xfields (tertiary table)
            $update_data['_xfd_c'] = $xf_diff_count;
            if(empty($xf_diff['add'])) unset($xf_diff['add']); else
                foreach($xf_diff['add'] as $fkey=> $fval)
                    if(empty($fval)) unset($xf_diff['add'][$fkey]);
            if(empty($xf_diff['upd'])) unset($xf_diff['upd']); else
                foreach($xf_diff['upd'] as $fkey=> $fval)
                    if(empty($fval)) unset($xf_diff['upd'][$fkey]);
            if(empty($xf_diff['rem'])) unset($xf_diff['rem']); else
                foreach($xf_diff['rem'] as $fkey=> $fval)
                    if(empty($fval)) unset($xf_diff['rem'][$fkey]);

            $update_data['_xfd'] = $xf_diff;
        } else
            $update_data['_xfd_c'] = 0; // No changes detected

        // Finally, the data is ready, store the update information (except some fields which are not needed for update record)
        unset($update_data['utime'], $update_data['edtime']); // time fields (as the history table has it's own time entry)
        unset($update_data['cache_primary'], $update_data['cache_secondary'], $update_data['cache_tertiary']); // Content of cache fields, separate history entry is created for them
        // echo "<pre>";
        // print_r($update_data);
        // exit;
        $this-> history_Create($id, $update_data, $update_type, $comment, $con_id);

        return $id;
    }

    /**
     * Process the changes in fields when form is updated
     * @param $changes
     * @param $oldData
     * @param $currentData
     *
     * @return string|null
     */
    private function _processFieldUpdate($changes, $oldData, $currentData)
    {
        $pstageData = array("prel" => "Preliminary",
            "fpr" => "Formal");
        $viceVersaCond = array("prel" => "Formal",
            "fpr" => "Preliminary");
        $res_arr = array();
        $comment = '';
        if (!empty($changes)) {
            foreach ($changes as $key => $val) {
                //specific condition for pstage
                if($key == "pstage"){
                    if($currentData[$key]['value'] != $oldData[$key]['value']){
                        $res_arr[$key]['original'] = $pstageData[$oldData[$key]['value']];
                        $res_arr[$key]['current'] = $pstageData[$currentData[$key]['value']];
                    }else{
                        $res_arr[$key]['original'] = $pstageData[$currentData[$key]['value']];
                        $res_arr[$key]['current'] = $viceVersaCond[$oldData[$key]['value']];
                    }
                }else{
                    $res_arr[$key]['current'] = $currentData[$key]['value'];
                    $res_arr[$key]['original'] = $oldData[$key]['value'];
                }
                $res_arr[$key]['title'] = $val;
            }
            if (!empty($res_arr)) {
                foreach ($res_arr as $key => $val) {
                    $comment = $val['title'] . ' has been updated from ' . $val['original'] . ' to ' . $val['current'];
                }
            } else {
                return NULL;
            }
        } else {
            return NULL;
        }

        return $comment;
    }

    /**
     * A specialized method for counters, designed to update only the attached data counters for a record
     *
     * @param int $rec_id The record ID
     * @param int $var_convos How much is added or substracted from c_convos
     * @param int $var_files How much is added or substracted from c_files
     * @param int $var_tasks How much is added or substracted from c_tasks
     * @param int $var_docs How much is added or substracted from c_docs
     * @param int $var_ctx How much is added or substracted from c_ctx
     */
    public function counter_Update($rec_id, $var_convos = 0, $var_files = 0, $var_tasks = 0, $var_docs = 0, $var_ctx = 0)
    {
        if($var_convos == 0 && $var_files == 0 && $var_tasks == 0 && $var_docs == 0 && $var_ctx == 0)
            return; // nothing to update
        if($var_convos> 0)
            $var_convos = "+ ".$var_convos;
        if($var_files> 0)
            $var_files = "+ ".$var_files;
        if($var_tasks> 0)
            $var_tasks = "+ ".$var_tasks;
        if($var_docs> 0)
            $var_docs = "+ ".$var_docs;
        if($var_ctx> 0)
            $var_ctx = "+ ".$var_ctx;
        $variation = array();
        if($var_convos)
            $variation['c_convos'] = "`c_convos` ".$var_convos;
        if($var_files)
            $variation['c_files'] = "`c_files` ".$var_files;
        if($var_tasks)
            $variation['c_tasks'] = "`c_tasks` ".$var_tasks;
        if($var_docs)
            $variation['c_docs'] = "`c_docs` ".$var_docs;
        if($var_ctx)
            $variation['c_ctx'] = "`c_ctx` ".$var_ctx;

        // build the query and run it
        $q=QueryFactory::update()
            -> table(MVC_Core::$db-> tab['iss'])
            -> values($variation, null, true)
            -> where("`id` = ?", $rec_id);

        return MVC_Core::$db-> query($q);
    }

    /**
     * Deletes an issue from the system and optionally all it's associated data
     * - Note: If not all the data is removed, it will remain orphaned and cannot be directly accessed by the application.
     * - However, it can be kept for archival purposes. By default, everything is cleaned.
     *
     * @param int|string $locator Issue locator ( numeric or trackID )
     * @param array $wtd (What to delete) - Contains a list of flags to indicate what other data is removed (Defaults to all being active!) - Full issue cleanup
     * "orgdata" - Organization specific data (including statistics)
     * "contacts" - Issue linked contacts
     * "convos" - Conversations associated with the issue
     * "files" - All the files that are associated with this issue
     * "tasks" - All the issue-related tasks
     * "notes" - Notes connected to the issue
     */
    public function delete($locator, $wtd = array('orgdata', 'contacts', 'convos', 'files', 'tasks', 'notes'))
    {
        if(($id = $this-> locate($locator))===false)
            return MVC_Core::$debug-> dump("Cannot find record: {$locator}", __METHOD__, null, "warning");;

        // First, cleanup associated data:
        if(is_array($wtd)) {

            // TODO: Disconnect organizations from the issue
            if(in_array('orgdata', $wtd)) {

            }

            // TODO: Disconnect contacts linked to this issue
            if(in_array('contacts', $wtd)) {

            }

            // TODO: Cleanup the conversations linked to this issue (and all their associated messages)
            if(in_array('convos', $wtd)) {
// 				$comm = new App_Model_Comm();
// 				$comm-> cleanup_Collection('connect', $id);
            }

            // Cleanup the attachments that this issue has
            if(in_array('files', $wtd)) {
                $files = new App_Model_File();
                $files-> cleanup_Collection('issue', $id);
            }

            // Delete the tasks for this issue
            if(in_array('tasks', $wtd)) {
                $tasks = new App_Model_Task();
                $tasks-> delete_All($id);
            }

            // Delete the notes for this issue
            if(in_array('notes', $wtd)) {
                $notes = new App_Model_Note();
                $notes-> delete_All($id);
            }
        }

        // Second, delete the xfields from their own table
        $this-> delete_XFields($id);

        // Third, delete the history for this issue
        // TODO: History should instead be moved to a permanent secondary table, as proof of what happened
        $this-> history_Delete_All($id);

        // Finally, remove the issue record itself
        $q=QueryFactory::delete()
            -> table(array(MVC_Core::$db-> tab['iss'],'iss'))
            -> where('`id` = ?', $id)
            -> limit(1);

        $r = MVC_Core::$db-> query($q);
        if(!$r)
            return false; // There was a problem deleting the issue?

        // TODO: Update issue type statistics

        // TODO: Update organization, user, other statistics?

        $this-> issue = null;
        return true; // deleted that crap!
    }

//----- Lists -----//

    /**
     * Reads records from the database
     *
     * @param int $issue_type_id When reading xfields ($wtr contains 'xfields') a type id must be known in advance or the function returns false
     * @param array $wtr Indicates what information to read ( if null or empty, just verify that the claim trackid actually exists )
     * @param Callback $qfo_filter If no callback specified, it simply reads all the tasks of the specified type_id
     * @param array $filter_params
     * @param string $order_by Which field to be used for ordering
     * @param int $count_rows
     * @param int $start_row
     * @param bool $calc_found_rows If true, uses SQL_CALC_FOUND_ROWS to determine the total number of rows if limits were not set
     * @param bool $serialize_xfields Make sure all the fields are representable as strings ( for displaying in the grid ), no complex types (array/object)
     * @return array
     */
    public function get_List($issue_type_id = null, $wtr = array(),
                             $qfo_filter = null, $filter_params = array(),
                             $order_by = null, $count_rows = 25, $start_row = 0,
                             $calc_found_rows = false, $serialize_xfields = false, $html_special_chars = false)
    {
        // make sure the correct Record Type is loaded if asked to read the xfields (force load for the issue type id)
        // if(in_array('xfields', $wtr)) {
        // if(is_null($issue_type_id))
        // return false; // error, cannot read xfields without knowing the type
        if(!is_null($issue_type_id)) {
            if(empty($this-> ist_def) || $this-> ist_def['id']!=$issue_type_id)
                $this-> ist_Read($issue_type_id, false, false, true);
            if(empty($this-> ist_def))
                return false; // error, wrong issue type id?
        }
        // $count_rows = 1;
        // echo "<pre>";
        // print_r($qfo_filter);
        // echo "<pre>";
        // print_r($filter_params);
        
        // exit;
        $q = $this-> get_List_QueryFactory_Helper($qfo_filter, $filter_params, $order_by, $count_rows, $start_row, $calc_found_rows);
        // echo "2554 ".$q;exit;
        if(is_null($q))
            return false; // Error, probably there was a problem with $qfo_filter

        // What to read?
        $wtr = $this-> wtr_Helper($wtr);

        //print_r($this-> wtr_Helper($wtr));

        if(!is_null($issue_type_id))
            $q-> where("`iss`.type_id = ?", $this-> ist_def['base_id']);
            
           

        // Filter out fields which are in the organization specific, so the are not requested from the main table
        if(isset($wtr['flist']) && is_array($wtr['flist'])) {
            $os_fields = array(
                'process', 'linktime', 'time_open', 'time_start', 'utime',
                'dep_ids', 'filesize_total', // 'count_contacts', 'count_files', 'count_convos', 'count_notes', 'count_tasks', 'count_active_tasks', 'count_propfields'
                'commtime', 'priority_key', 'status_key', 'xfer_by', 'xfer_to', 'xfer_time', 'xfer_ip', 'xfer_confirmip', 'assigned_to',
                'exclusive_edit', 'access_users', 'access_ut', 'time_close',
                'pending_length', 'process_length'
            );
            $oslist = array();
            // Some of the "fields" are actually coming from org. specific table
            foreach($os_fields as $os_f)
                if(($p = array_search($os_f, $wtr['flist']))!==false) {
                    $oslist[] = $os_f;
                    unset($wtr['flist'][$p]);
                }
        } else {
            $os_fields = false;
            $wtr['flist'] = $oslist = array();
        }
        
        // What to read?
        if(in_array('issue', $wtr)) {
            if(!empty($wtr['flist']) && !in_array('track', $wtr['flist'])) // always return Track ID and external access key when any fields are specified (used by the grid module)
                array_unshift($wtr['flist'], 'track', 'access_key');
            if(!empty($wtr['flist']) && !in_array('id', $wtr['flist'])) // always return ID - necessary for the rest of the method
                array_unshift($wtr['flist'], 'id');
            $q-> table(MVC_Core::$db-> table('iss'), !empty($wtr['flist'])? $wtr['flist']: null); // fields in the primary table
        } else
            $q-> table(MVC_Core::$db-> table('iss'), 'id,track,master_org_id'); // just id, track, master_org_id from the primary table
            
        if(in_array('type', $wtr))
            $q-> table(MVC_Core::$db-> table('ist'), QueryFactory::autoalias_Add_Prefix("name,key", '_ist_'), 'left', '`iss`.type_id = `ist`.id');
        if(in_array('group', $wtr))
            $q-> table(MVC_Core::$db-> table('grp'), QueryFactory::autoalias_Add_Prefix("name", '_grp_'), 'left', '`iss`.group_id = `grp`.id');
        if(in_array('organization', $wtr)) // Issue owner organization
            $q-> table(MVC_Core::$db-> table('org'), QueryFactory::autoalias_Add_Prefix("name,type_id,contact_id", '_org_'), 'left', '`iss`.master_org_id = `org`.id');
        // Submitter is a "contact" record, while sumitter organization is an "organization" record
        if(in_array('submitter', $wtr))
            $q-> table(array(MVC_Core::$db-> tab['ctx'],'subm'), QueryFactory::autoalias_Add_Prefix("id,name,email".(in_array('submitter_xdata', $wtr)? ',xdata': ''), '_subm_'), 'left', '`iss`.submit_id = `subm`.id');
        if(in_array('submitter_organization', $wtr))
            $q-> table(array(MVC_Core::$db-> tab['org'],'subo'), QueryFactory::autoalias_Add_Prefix("name,type_id,contact_id", '_subo_'), 'left', '`iss`.submit_org_id = `subo`.id');
           
        
        // Organization specific data
        if(in_array('org_specific', $wtr)) {
            if(isset($wtr['org_id']) && $wtr['org_id']>0)
                // When organization is specified, only issues that are accessible by the organization are loaded, using a right-join, and that specific organization's data is read
                $q-> table(MVC_Core::$db-> table('iod'), QueryFactory::autoalias_Add_Prefix($oslist), 'right', "`iss`.id = `iod`.issue_id AND `iod`.org_id = {$wtr['org_id']} AND `iod`.is_enabled = 1");
            else
                // When organization is not specified, all issues are shown ( usually for admin ), and master organization's data is read
                $q-> table(MVC_Core::$db-> table('iod'), QueryFactory::autoalias_Add_Prefix($oslist), 'left', "`iss`.id = `iod`.issue_id AND `iod`.org_id = `iss`.master_org_id");

            if(in_array('xfer_by', $wtr))
                $q-> table(array(MVC_Core::$db-> tab['ctx'],'xsend'), QueryFactory::autoalias_Add_Prefix("id,name,email", '_xsend_'), 'left', '`iod`.xfer_by = `xsend`.id');

            if(in_array('xfer_to', $wtr))
                $q-> table(array(MVC_Core::$db-> tab['ctx'],'xrecv'), QueryFactory::autoalias_Add_Prefix("id,name,email", '_xrecv_'), 'left', '`iod`.xfer_to = `xrecv`.id');

            if(in_array('assigned', $wtr)) // Assigned user (only when organization specified)
                $q-> table(MVC_Core::$db-> table('usr'), QueryFactory::autoalias_Add_Prefix("id,name,email,contact_id", '_usr_'), 'left', '`iod`.assigned_to = `usr`.id');

            // Warning - For some reason this is slow ( probably the grouping causes it )
            // FIXME: Find another way to do it which doesn't involve grouping with the main results... (perhaps a secondary query?)
            if(in_array('deps', $wtr) && in_array('dep_ids', $oslist)) {
                $q
                    -> table(MVC_Core::$db-> table('dep'), array('deps'=> "GROUP_CONCAT(`dep`.name SEPARATOR ', ')"), 'left', '`dep`.org_id = `iod`.org_id AND FIND_IN_SET(`dep`.id, `iod`.dep_ids)')
                    -> group('`iss`.id');
            }
        }
        
        // Marker (for one user)
        if(in_array('marker', $wtr) && isset($wtr['user_id'])) {
            $q-> table(MVC_Core::$db-> table('rmk'), QueryFactory::autoalias_Add_Prefix("access_ut,export_ut", '_rmk_'), 'left', "`iss`.id = `rmk`.rec_id AND `rmk`.user_id = {$wtr['user_id']}");
        }
        $split_by_prefixes = array(
            // tidy up
            'cache'=> "cache_",
            'xfer'=> "xfer_",		// Inter-organization Transfer information
            // extra tables
            'group'=> "_grp_",
            'type'=> "_ist_",
            'assigned'=> "_usr_",	// Assigned user
            'marker'=> "_rmk_",		// User-related marker(s)
            'organization'=> "_org_",
            'submitter'=> "_subm_",
            'submitter_org'=> "_subo_",
            'sender'=> "_xsend_",
            'recipient'=> "_xrecv_",
            'count'=> "c_",			// Admin only, primary table
            'sui'=> "sui_"			// Admin only, primary table
        );

        // custom join $wtr (could be anything)
//$_SESSION['user']['cdep_id']
//$_SESSION['user']['status']
        

             
//          IWI user can view records based on only there current active depertment  start
        
        

        
        $org_lebel_issues_view_orgowned=false;
        $dep_lebel_issues_view_orgowned=false;
        $usr_lebel_issues_view_orgowned=false;
        
        $q_access_org = QueryFactory::select()
                ->table(MVC_Core::$db->tab['org'], 'privileges') // the json-encoded fields
                ->where("id = ?", $_SESSION['user']['org_id'])
                ->limit(1);
        $r_access_org = MVC_Core::$db->query($q_access_org);
        $org_access_org_data = $r_access_org->current();
        $orl_lebel_issues_view_orgowned=false;
        if (!empty($org_access_org_data['privileges']) && in_array("issues_view_orgowned", explode(",", $org_access_org_data['privileges']))) {
            $org_lebel_issues_view_orgowned=true;
        }
        
         $q_access_dep = QueryFactory::select()
                ->table(MVC_Core::$db->tab['dep'], 'privileges_or') // the json-encoded fields
                ->where("id = ?", $_SESSION['user']['cdep_id'])
                ->limit(1);
        $r_access_dep = MVC_Core::$db->query($q_access_dep);
        $org_access_dep_data = $r_access_dep->current();
        $orl_lebel_issues_view_orgowned=false;
        if (!empty($org_access_dep_data['privileges_or']) && in_array("issues_view_orgowned", explode(",", $org_access_dep_data['privileges_or']))) {
            $dep_lebel_issues_view_orgowned=true;
        }
 
        
        
        if(!empty($org_access_dep_data['privileges_or'])){
          if(!$dep_lebel_issues_view_orgowned){
            // echo "2519<br>";
              if ($_SESSION['user']['status'] != 'admin') {
                $q->where('find_in_set("' . $_SESSION['user']['cdep_id'] . '", dep_ids) <> 0');
            }
          }
        }else if(!empty($org_access_org_data['privileges'])){
             if(!$org_lebel_issues_view_orgowned){
            // echo "2525<br>";
              if ($_SESSION['user']['status'] != 'admin') {
                $q->where('find_in_set("' . $_SESSION['user']['cdep_id'] . '", dep_ids) <> 0');
            }
          }
        }else{
            // echo "2532<br>";
            if (!empty($_SESSION['user']['status']) && ($_SESSION['user']['status'] != 'admin')) {
                $q->where('find_in_set("' . $_SESSION['user']['cdep_id'] . '", dep_ids) <> 0');
            }
        }
      
//        if (!empty($org_access_data['privileges']) && in_array("issues_view_orgowned", explode(",", $org_access_data['privileges']))) {
//            
//        } else {
//            if ($_SESSION['user']['status'] != 'admin') {
//                $q->where('find_in_set("' . $_SESSION['user']['cdep_id'] . '", dep_ids) <> 0');
//            }
//        }


//          IWI user can view records based on only there current active depertment  end

        if(isset($wtr['custom']))
            $this-> get_List_Custom_Joins($q, $wtr['custom'], $split_by_prefixes);

    //    echo "2558 ".$q;exit;
        $r = MVC_Core::$db-> query($q);
       
        if(!$r || empty($r))
            return $r;

        $r-> split_by_prefixes = $split_by_prefixes;
        // get data from all joins ( and all ID's at the same time)
        $all = array();
        foreach($r as $issue) {
            //print_r($issue);
            if(is_array($wtr) && in_array('xfields', $wtr))
                $issue['xfields'] = array();

            $issue['id'] = (int)$issue['id'];

            // Base Issue fields
            if(in_array('issue', $wtr)) {
                if(!empty($issue['submit_ip']))
                    $issue['submit_ip'] = long2ip($issue['submit_ip']);

                if(isset($wtr['flist']) && in_array('shared_org_ids', $wtr['flist'])) {
                    if(!empty($issue['shared_org_ids']))
                        $issue['shared'] = true;
                    else
                        $issue['shared'] = false;
                }
            }

            // Issue OrgData fields
            if(in_array('org_specific', $wtr)) {
                if(!empty($issue['status_key']) && isset($this-> statuses[$issue['status_key']]))
                    $issue['status'] = $this-> statuses[$issue['status_key']];
                else
                    $issue['status'] = self::sMISSING;
                if(!empty($issue['priority_key']) && isset($this-> priorities[$issue['priority_key']]))
                    $issue['priority'] = $this-> priorities[$issue['priority_key']];
                else
                    $issue['priority'] = self::sMISSING;

                if(!empty($issue['xfer']['ip']))
                    $issue['xfer']['ip'] = long2ip($issue['xfer']['ip']);

                if(isset($oslist) && in_array('xfer_confirmip', $oslist)) {
                    if(!empty($issue['xfer']['confirmip'])) {
                        $issue['xfer']['confirmip'] = long2ip($issue['xfer']['confirmip']);
                        $issue['xfer']['delivered'] = true;
                    } else
                        $issue['xfer']['delivered'] = false;
                }
            }

            // Move the submitter organization information into the submitter key itself
            if(in_array('submitter_org', $wtr)) {
                if(!in_array('submitter', $wtr))
                    $issue['submitter'] = array();
                $issue['submitter']['org'] = $issue['submitter_org'];
                unset($issue['submitter_org']);
            }

            // Decode submitter xdata
            if(in_array('submitter_xdata', $wtr)) {
                if(!empty($issue['submitter']['xdata']))
                    $issue['submitter']['xdata'] = @json_decode($issue['submitter']['xdata'], true);
            }
            
            $all[$issue['id']] = $issue;
        }

        // Check if the calc_found flag is used
        if($calc_found_rows)
            $this-> get_List_Save_FoundRows();

        // read the xfields from the dynamic and fixed length tables that contain them, using two queries containing GROUP BY
        // -- xfields cannot be read by joins, as their data is in an 1toN relation
        // echo "abcdefgh";
        // print_r($wtr);

        if(is_array($wtr) && in_array('xfields', $wtr)) {
            $id_list = array_keys($all);

            $q=QueryFactory::select()
                -> table(MVC_Core::$db-> tab['ixf'], array(
                    'issue_id',
                    'key_ids'=> "GROUP_CONCAT(fk_id)",
                    'values'=> "GROUP_CONCAT(CONCAT_WS('|', COALESCE(int_value, ''), COALESCE(value, '')) SEPARATOR '~~')"
                ))
                -> group("issue_id");

            $join_mode_limit = 100;
            
            // When number of returned issues is large, instead of using issue_id IN ($id_list), make a temp table and join.
            // This is done to support extremely large results which would make a huge query (unsupported)
            if(count($id_list)> $join_mode_limit) {
                MVC_Core::$db-> query(
                    QueryFactory::create('table')
                        -> name("__id_list")
                        -> flags("temporary", "MEMORY")
                        -> column('issue_id')
                );

                $id_list_chunks = array_chunk($id_list, $join_mode_limit);
                foreach($id_list_chunks as $chunk) {
                    $qt=QueryFactory::insert()
                        -> table("__id_list");
                    foreach($chunk as $id)
                        $qt-> values(array('issue_id'=> $id));
                    MVC_Core::$db-> query($qt);
                }

                // Right-Join the ID List to the concat values so only those ID's mentioned in the temporary table are loaded
                $q-> table("__id_list", false, 'right', '(issue_id)');
            } else
                $q-> where("issue_id IN(?)", $id_list);

            $fk_translate = array();
            
            foreach($this-> ist_def['xfield_def'] as $fk=> $fdef)
                $fk_translate[$fdef['id']] = $fk;

            // echo "query : ".$q;
            // there is a list of fields that should be read, instead of all xfields, so assamble the list
            if(isset($wtr['xflist'])) {
                $fk_id_list = array();
                foreach($wtr['xflist'] as $fk) {
                    // echo "2594 fk : ".$fk;
                    if(isset($this-> ist_def['xfield_def'][$fk]))
                        $fk_id_list[] = $this-> ist_def['xfield_def'][$fk]['id'];
                }

                // and filter the xfields that will be read
                $q-> where("fk_id IN(?)", $fk_id_list);
            }
          
            $r = MVC_Core::$db-> query($q);
            
            if($r && !empty($r)) {
                if(count($id_list)> $join_mode_limit) {
                    MVC_Core::$db-> query(
                        QueryFactory::drop('table')
                            -> flags("temporary")
                            -> name("__id_list")
                    );
                }

               
                foreach($r as $ixf) {
                    $key_ids = explode(',', $ixf['key_ids']);
                    $values = explode('~~', $ixf['values']);

                    

                    foreach($key_ids as $index=> $key_id) {
                      
                        // Self cleaning - When wrong field is loaded for an issue (wrong field key id), it removes the field from the database
                        if(!isset($fk_translate[$key_id])) {
                            MVC_Core::$db-> query(
                                QueryFactory::delete()
                                    -> table(MVC_Core::$db-> tab['ixf'])
                                    -> where('`issue_id` = ?', $ixf['issue_id'])
                                    -> where('`fk_id` = ?', $key_id)
                            );
                            continue;
                        }

                        $fk = $fk_translate[$key_id];
                        $xf = array('fk'=> $fk);
                        
                        //  IWI License monitor list fetching data issue fixed start
                        if (empty($values[$index])) {
                            $issueXfld = MVC_Core::$db->query(
                                    QueryFactory::select('value')
                                            ->table(MVC_Core::$db->tab['ixf'])
                                            ->where('`issue_id` = ?', $ixf['issue_id'])
                                            ->where('`fk_id` = ?', $key_id)
                            );
                            $values[$index]=array();
                            foreach ($issueXfld as $xfldR) {
                                $values[$index] = $xfldR['int_value'] . " | " . $xfldR['value'];
                            }
                        }
                        //   IWI License monitor list fetching data issue fixed end



                        list($xf['int_value'], $xf['value']) = explode('|', $values[$index]);
                                               
                        $all[(int)$ixf['issue_id']]['xfields'][$fk] = $this-> decode_XField($xf, $fk, $serialize_xfields, $ixf['issue_id'], $html_special_chars);
                        
                        // 21082024 changes by iwi
                        // if($fk == 'no_train'){
                        //     $all[(int)$ixf['issue_id']]['xfields']['no_train'] = 2;
                        // }
                        // 21082024 changes by iwi

                        // 18062024 changes by iwi
                        if($fk == 'document_li' && $all[(int)$ixf['issue_id']]['xfields']['document_li'] == 1){

                            $get_license_monitor = $this->get_all_document_by_license_monitor($ixf['issue_id']);
                            $html = '<select name="" class="license_monitor_grid_sw_doc" id="license_monitor_grid_sw_doc">';
                            $html .= '<option value="">---</option>';
                            $html .= $get_license_monitor;
                            $html .= '</select>';
                          
                            $all[(int)$ixf['issue_id']]['xfields']['document_li'] = $html;
                        }
                       
                    }
                }
            }
        }
        
        return $all;
    }

    //--- Organization related issue methods ---//

    /**
     * Adds the specified organization to the list of organizations
     * -- Every new connected org starts as enabled (can access the issue, becomes visible to them)
     *
     * @param int|string $locator Record locator (numeric ID or tracking code)
     * @param (string) $org_ids Organization that will receive access to this issue (shared with)
     * @param array|string|int $dep_ids Specific department(s) which will have access (or all of them if the value is missing)
     * @param array $data_fields Other organization-specific fields which can go in this table
     * @param bool $update_issue Updates the primary issue table with the new organization information
     * @param bool $no_checks If true, the function skips all checks and assumes the current cached issue is the one that will be connected to an organization (and already contains proper information)
     * @param bool $propagate_deps If ture, checks the list of departments which should also be connected to the record, depending on source department (dep sharing feature)
     * -- Only works when there is only one single DEP_ID
     * @param string $link_mode 'self' = The record was created by an user of this organization. 'recv' = The record has been shared with this organization from an external source (public, or another organization)
     * -- Certain defaults are set differently depending on the link mode. Usually when using 'self', the organization also becomes the owner (org_SetAsOwner)
     *
     * @return array|bool On completion, it returns the Organization data that was read for checking or "True" if $no_checks was true
     */
    public function org_Add($locator, $org_id, $dep_ids = null, $data_fields = null, $update_issue = true, $no_checks = false, $propagate_deps = false, $link_mode = 'self')
    {
        $m_org = new App_Model_Organization();
        if(!$no_checks) {
            $this-> read($locator);

            if(empty($this-> issue))
                return MVC_Core::$debug-> dump("Record could not be located", __METHOD__, null, 'error');

            // Try to detect is the organization is not already connected to the issue ( even if disabled ), and succeed silently
            // The information is also used to return the organization type (when already connected)
            $q=QueryFactory::select()
                -> table(MVC_Core::$db-> table('iod'), array('org_id','is_enabled','linktime'))
                -> table(MVC_Core::$db-> table('org'), array('type_id'), 'left', '`iod`.org_id = `org`.id')
                -> where("`issue_id` = ?", $this-> issue['id'])
                -> where("`org_id` = ?", $org_id)
                -> limit(1);

            $r = MVC_Core::$db-> query($q);
            if($r && count($r)==1) {
                $orglink = $r-> current();
                if(!(int)$orglink['is_enabled']) {
                    // When connected but disabled, this will reenable it.
                    if(!$this-> org_Enable($this-> issue['id'], $org_id, true))
                        return false;
                    $orglink['is_enabled'] = true;
                } else {
                    // Organization already connected (and enabled), just return it's info
                    MVC_Core::$debug-> dump(sprintf("Organization was already connected to the record on %s", date("Y-m-d H:i:s", $orglink['linktime'])), __METHOD__, null, 'warning');
                    $orglink['previously_linked'] = true;
                }
                return $orglink;
            }

            // Make sure the organization exists, by attempting to read it's base data.
            $org = $m_org-> read($org_id, array());
            if(empty($org))
                return MVC_Core::$debug-> dump("The organization could not be found.", __METHOD__, null, 'error');
        }

        // Always read record type, needed for various connections
        $rt = $this-> ist_Read($this-> issue['type_id'], null);

        // If $dep_ids is empty, read the list of departments in the organization which are set to receive the record type

        // TODO: CHECK if this is still needed, for inter-organization sharing
        /*		if(empty($dep_ids)) {
			$m_dep = new App_Model_Department();
			$org_deps = $m_dep-> get_List($org_id, array('dep_target'), function(QueryFactory &$q) use($rt) {
				// Filter those which have "listed" to 1
				$q	-> where("`dep`.listed = 1")
					-> where("FIND_IN_SET(?, `dep`.use4ist)", $rt['key']);
			});

			if($org_deps!==false) {
				$dep_ids = array();
				foreach($org_deps as $dep)
					$dep_ids[] = $dep['id'];
				$dep_ids = implode(',', $dep_ids);
			} else
				// Note: The list might still be empty if no departments are set to receive !
				$dep_ids = null;
		}
*/

        if($propagate_deps && !empty($dep_ids)) {
            $complete_dep_list = array();

            if(is_string($dep_ids))
                $dep_ids = explode(',', $dep_ids);
            elseif(is_numeric($dep_ids))
                $dep_ids = array((int)$dep_ids);

            // DEP IDs propagation (depends on record type) - $rt['key']
            $m_dep = new App_Model_Department();
            foreach($dep_ids as $dep_id) {
                $reclinks = $m_dep-> reclink_Read($dep_id, $rt['key']);
                if(empty($reclinks['disc_src']))
                    $complete_dep_list[$dep_id] = true;
                if(!empty($reclinks['link_deps']))
                    foreach($reclinks['link_deps'] as $link_dep_id)
                        $complete_dep_list[$link_dep_id] = true;
            }
            // When done, repack the sparse list
            $dep_ids = array_keys($complete_dep_list);
        }

        // Little error checks
        if(!in_array($link_mode, array('self', 'recv')))
            $link_mode = 'self';

        if(is_array($dep_ids))
            $dep_ids = implode(',', $dep_ids);
        elseif(is_numeric($dep_ids))
            $dep_ids = (string)$dep_ids;

        // Connect the organization to the issue
        $insert_fields = array(
            'issue_id'=> (int)$this-> issue['id'],
            'org_id'=> (int)$org_id,
            'is_enabled'=> 1,
            'linktime'=> isset($data_fields['linktime'])? $data_fields['linktime']: time(),
            'dep_ids'=> $dep_ids,
            // Status and Priority initialization contained into record type definition (IST), but can be overridden by the $data_fields
            'status_key'=> $this-> ist_def['settings']['init'][$link_mode]['stat'],
            'priority_key'=> $this-> ist_def['settings']['init'][$link_mode]['prio']
        );


        // Check fields which can be added here; $data_fields overwrites the defaults from $insert_fields
        if(is_array($data_fields)) {
            $fields = array(
                // Prepare to remove counters from org-specific table ; These will get replaced by per-user statistics;
                // Because of access rights and departments, global org counters are not useful - Different users from different departments might see different elements
                'process', 'filesize_total', // 'count_contacts', 'count_files', 'count_convos', 'count_notes', 'count_tasks', 'count_active_tasks', 'count_propfields',
                'commtime', 'priority_key', 'status_key', 'xfer_by', 'xfer_to', 'xfer_time', 'xfer_ip', 'xfer_confirmip', 'assigned_to', 'exclusive_edit'
            );
            foreach($data_fields as $field=> $value)
                if(!in_array($field, $fields))
                    unset($data_fields[$field]);

            $insert_fields = array_merge($insert_fields, $data_fields);
        }

        // echo "<pre>2906";
        // print_r($insert_fields);
        // exit;


        $q=QueryFactory::insert()
            -> table(MVC_Core::$db-> tab['iod'])
            -> values($insert_fields);

        $r = MVC_Core::$db-> query($q);
        if(!$r)
            return false; // Error, could not create connection record (Maybe already connected?)

        // Success! Increase the issue counter for the organization
        $m_org-> update($org_id, array(
            'count_issues'=> "`count_issues` + 1"
        ), false, true);

        // Add the organization in the shared_org_ids field in primary table and update the issue
        if($update_issue && !in_array($org_id, $this-> issue['shared_org_ids'])) {
            $this-> issue['shared_org_ids'][] = $org_id;

            $this-> update($this-> issue['id'], array(
                'shared_org_ids'=> $this-> issue['shared_org_ids']
            ));
        }

        if($no_checks)
            return true;
        else
            // "orglink" data: org_id,is_enabled,linktime
            return array(
                'org_id'=> $org_id,
                'type_id'=> $org['type_id'],
                'is_enabled'=> true, // Of course, it was just linked
                'linktime'=> $insert_fields['linktime']
            );

    }

    /**
     * Minimal read function for organization data used to make process marker calculation (time differences).
     * If nothing found, it means either that the organization is not connected, or the record doesn't exist.
     *
     * @param int $rec_id
     * @param int $org_id
     */
    public function org_ReadProcess($rec_id, $org_id)
    {
        $q=QueryFactory::select()
            -> table(MVC_Core::$db-> tab['iod'], array('process', 'linktime', 'time_open', 'time_start', 'utime', 'commtime', 'time_close'))
            -> where("`issue_id` = ?", $rec_id)
            -> where("`org_id` = ?", $org_id);

        return MVC_Core::$db-> query($q)-> current();
    }

    /**
     * Changes the process marker (field: process) to a value, by updating IOD via self::org_UpdateData
     *
     * @param int $rec_id Record ID for which the marker and timers are updated - This method doesn't verify if the record or organization exist!
     * @param string $process The new process marker [ 'opened', 'reactive', 'active', 'shared', 'closed', 'hidden' ]
     * @param int $org_id For which organization sharing the record?
     * @param bool $data_return Instead of directly updating, just return data ( used when org_UpdateData is going to be used in another call )
     * @param int $linktime If present, for certain process changes times are calculated (between NOW and $linktime)
     */
    public function org_MarkProcess($rec_id, $process, $org_id, $data_return = false, $linktime = null)
    {
        if(!in_array($process, array('opened', 'reactive', 'active', 'shared', 'closed', 'hidden')))
            return false; // Invalid $mark

        $fields_update = array(
            'process'=> $process,
        );
        $history_update = $fields_update;
        switch($process) {
            case 'opened':
                $fields_update['time_open'] = time();
                break;
            case 'reactive':
                $fields_update['time_close'] = null; // Reset this, since it's no longer closed
                $fields_update['process'] = 'active';
            case 'active':
                $fields_update['time_start'] = time();
                if(!empty($linktime) && $process == 'active')
                    $history_update['pending_length'] = $fields_update['pending_length'] = time() - $linktime;
                $clear_export_mark = true;
                break;
            case 'shared':
                $fields_update['time_close'] = null;
                $clear_export_mark = true;
                break;
            case 'closed':
                $fields_update['time_close'] = time();
                if(!empty($linktime))
                    $history_update['process_length'] = $fields_update['process_length'] = time() - $linktime;
                $clear_export_mark = true;
                break;
            case 'hidden':
                $fields_update['time_hide'] = time();
                $fields_update['is_enabled'] = 0; // Compatibility mode
                break;
        }

        if($data_return)
            return $fields_update;
        else {
            $r = $this-> org_UpdateData($rec_id, $org_id, $fields_update, true);
            if(empty($r))
                return false;
			
			if($process == "closed"){
				$record_Type = $this->get_RecordType_Details($rec_id);
				if($record_Type != ""){
					$this->update_A_Field($rec_id,$record_Type,"doss_close_date","cargoclaim");
				}
			}
            // On successful update to "active", "shared", "closed", clear export markers for the record (for all users)
            if(isset($clear_export_mark))
                $this-> marker_UpdateAll($rec_id, array('export_ut'=> false));
            // ... and create a History record
            if($process != 'hidden') // Hiding a record is an admin-only operation, not shown
                $this-> history_Create($rec_id, $history_update, 'process');
            return true;
        }
    }

    /**
     * Updates the organization specific record data (secondary table)
     * -- Contains: process marker, timestamps, department links, status/priority, user links, durations, attached data count (totals), transfer info
     *
     * @param int|string $locator Record locator ( numeric or trackID )
     * @param int $org_id
     * @param array $fields_update
     * @param bool $no_checks If true, it is assumed that $locator is `issue_id` and organization membership has already been checked.
     * @param string $string_expressions This parameter is sent to query factory, and is used to disable string escaping (in order to allow expressions, for example to increment a counter)
     */
    public function org_UpdateData($locator, $org_id, $fields_update, $no_checks = false, $string_expressions = false)
    {
        if(($id = $this-> locate($locator))===false)
            return MVC_Core::$debug-> dump("Cannot find record: {$locator}", __METHOD__, null, "warning");

        // TODO: Since the record is not fully read here, not possible to determine these. Find another way
        // TODO: $no_checks has not effect now !
// 		if($org_id != $this-> issue['master_org_id'] && !in_array($org_id, $this-> issue['shared_org_ids']))
// 			return MVC_Core::$debug-> dump("Not part of the list of organizations with access to this issue.", __METHOD__, 'warning');

        // Check if the $fields_update is an array and remove any fields which are not part of the orgdata
        if(!is_array($fields_update))
            $fields_update = array();
        else {
            foreach($fields_update as $field=> $value)
                if(!in_array($field, self::$org_columns))
                    unset($fields_update[$field]);
        }

        if(empty($fields_update))
            return MVC_Core::$debug-> dump("No data to update for the organization-specific record information.", __METHOD__, 'warning');

        // The special value "true" for [dep_ids] means that all departments of the organization will be listed. Read that list to store in the IOD
        if(isset($fields_update['dep_ids']) && $fields_update['dep_ids']===true) {
            $m_dep = new App_Model_Department();
            $org_deps = $m_dep-> get_List($org_id, array());
            $dep_ids = array();
            foreach($org_deps as $dep)
                $dep_ids[] = $dep['id'];
            $fields_update['dep_ids'] = implode(',', $dep_ids);
        }

        // Note: If the organization is not linked with the record, this query will do nothing (row not found)
        $q=QueryFactory::update()
            -> table(MVC_Core::$db-> tab['iod'])
            -> where("`issue_id` = ?", $id)
            -> where("`org_id` = ?", $org_id)
            -> values($fields_update, null, $string_expressions);

         $r = MVC_Core::$db-> query($q);

		return $r;
    }

    /**
     * The specified organization will become the "owner" (or master) for this issue (the one allowed to change xfields and share/unshare data with other organizations).
     * This org must first be connected to the issue ( by using org_Add ) before it becomes the owner. If not the function will return an error
     * -- This method is called following a successful issue "transfer" (message sent, etc.)
     * -- If there was an original owner organization, the issue is shared back with that organization (and any other orgs which are sharing this issue)
     *
     * @todo I don't like how this method works; It should not read the record on it's own...
     *
     * @param int|string $locator Issue locator ( numeric or trackID )
     * @param int $org_id New owner for organization
     * @param int $xfer_by ID of the contact that is performing the transfer/sharing
     * @param int $xfer_to ID of the contact which was messaged by a normal transfer/sharing (If null, it's usually the admin moving issues between organizations)
     * @param bool $set_commtime If true, current time is recorded into 'commtime' field as well, indicating that a message has been created for the target organization at the moment of the transfer
     */
   
public function org_SetAsOwner($locator, $org_id, $xfer_by = null, $xfer_to = null, $set_commtime = false)
{
    $this->read($locator);

    if (empty($this->issue)) {
        return MVC_Core::$debug->dump("Issue could not be located", __METHOD__, 'error');
    }

    $org_id = (int) $org_id;

    if ((int) $this->issue['master_org_id'] === $org_id) {
        return !MVC_Core::$debug->dump("Organization is already the owner of the issue", __METHOD__, 'warning');
    }

    $q = QueryFactory::select()
        ->table(MVC_Core::$db->tab['iod'], 'org_id,is_enabled')
        ->where("`issue_id` = ?", $this->issue['id']);

    $r = MVC_Core::$db->query($q);

    if (!$r || (is_countable($r) && count($r) === 0)) {
        return MVC_Core::$debug->dump("No organizations are connected to the issue.", __METHOD__, 'error');
    }

    $share_list = array();
    $target_found = false;

    foreach ($r as $iod_row) {
        $row_org_id = isset($iod_row['org_id']) ? (int) $iod_row['org_id'] : 0;
        $is_enabled = !empty($iod_row['is_enabled']);

        if ($row_org_id === $org_id) {
            $target_found = true;

            $update_data = array(
                'is_enabled' => 1,
                'xfer_by' => $xfer_by ? $xfer_by : (isset(MVC_Core::$ss->user['contact_id']) ? MVC_Core::$ss->user['contact_id'] : null),
                'xfer_to' => $xfer_to,
                'xfer_time' => time(),
                'xfer_ip' => sprintf('%u', ip2long(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0')),
                'xfer_confirmip' => null
            );

            if ($set_commtime) {
                $update_data['commtime'] = $update_data['xfer_time'];
            }

            MVC_Core::$db->query(
                QueryFactory::update()
                    ->table(MVC_Core::$db->tab['iod'])
                    ->values($update_data)
                    ->where("`issue_id` = ?", $this->issue['id'])
                    ->where("`org_id` = ?", $org_id)
            );
        } elseif ($is_enabled) {
            $share_list[] = $row_org_id;
        }
    }

    if (!$target_found) {
        return MVC_Core::$debug->dump("Target not part of the list of organizations with access to this issue.", __METHOD__, 'error');
    }

    $q = QueryFactory::update()
        ->table(MVC_Core::$db->tab['iss'])
        ->where("`id` = ?", $this->issue['id'])
        ->values(array(
            'master_org_id' => $org_id,
            'shared_org_ids' => !empty($share_list) ? implode(',', $share_list) : null
        ));

    return MVC_Core::$db->query($q);
}


    /**
     * The specified organization will become the "owner" (or master) for this issue (the one allowed to change xfields
     * and share/unshare data with other organizations). This org must first be connected to the issue ( by using
     * org_Add ) before it becomes the owner. If not the function will return an error
     * -- This method is called following a successful issue "transfer" (message sent, etc.)
     * -- If there was an original owner organization, the issue is shared back with that organization (and any other
     * orgs which are sharing this issue) without time
     *
     * @param int|string $locator      Issue locator ( numeric or trackID )
     * @param int        $org_id       New owner for organization
     * @param int        $xfer_by      ID of the contact that is performing the transfer/sharing
     * @param int        $xfer_to      ID of the contact which was messaged by a normal transfer/sharing (If null, it's
     *                                 usually the admin moving issues between organizations)
     * @param bool       $set_commtime If true, current time is recorded into 'commtime' field as well, indicating that
     *                                 a message has been created for the target organization at the moment of the
     *                                 transfer
     *
     *
     */
    public function org_SetAsOwnerWithoutTime($locator, $org_id, $xfer_by = null, $xfer_to = null, $set_commtime = false)
    {
        
        $this->read($locator);
        if (empty($this->issue))
            return MVC_Core::$debug->dump("Issue could not be located", __METHOD__, 'error');

        // Check if organization is already master. If yes, return true (Just like the function succeded)
        if ($this->issue['master_org_id'] == $org_id)
            return !MVC_Core::$debug->dump("Organization is already the owner of the issue", __METHOD__, 'warning');

        // Read the list of all organizations that are sharing this issue and check their "enabled" status
        $q = QueryFactory::select()
            ->table(MVC_Core::$db->tab['iod'], 'org_id,is_enabled')
            ->where("`issue_id` = ?", $this->issue['id']);

        $r = MVC_Core::$db->query($q);

        if (!$r || empty($r))
            return MVC_Core::$debug->dump("No organizations are connected to the issue.", __METHOD__, 'error');

        $share_list = array();
        $target_found = false;
        foreach ($r as $iod_row) {
            if ($iod_row['org_id'] == $org_id) {
                $target_found = true;
                if (!$iod_row['is_enabled'])
                    // If the organization connection was not enabled, enable it now (Master must always be enabled, for obvious reasons).
                    $iod_row['is_enabled'] = 1;
                // Set the xfer_* values
                $iod_row['xfer_by'] = $xfer_by ?: MVC_Core::$ss->user['contact_id'];
                $iod_row['xfer_to'] = $xfer_to;
                $iod_row['xfer_confirmip'] = null;
                if ($set_commtime)
                    $iod_row['commtime'] = $iod_row['xfer_time'];

                // And update the org-specific record with the transfer information
                unset($iod_row['org_id']);
                MVC_Core::$db->query(
                    QueryFactory::update()
                        ->table(MVC_Core::$db->tab['iod'])
                        ->values($iod_row)
                        ->where("`issue_id` = ?", $this->issue['id'])
                        ->where("`org_id` = ?", $org_id)
                );
            } else
                if ($iod_row['is_enabled'])
                    $share_list[] = (int)$iod_row['org_id'];
        }

        if (!$target_found)
            return MVC_Core::$debug->dump("Target not part of the list of organizations with access to this issue.", __METHOD__, 'error');

        // Finally, update the issue with the new data
        $q = QueryFactory::update()
            ->table(MVC_Core::$db->tab['iss'])
            ->where("`id` = ?", $this->issue['id'])
            ->values(array(
                'master_org_id' => $org_id,
                'shared_org_ids' => !empty($share_list) ? implode(',', $share_list) : null
            ));

        return MVC_Core::$db->query($q);
    }

    public function mailSendTimeUpdate($issueId,$orgId){
        // Read the list of all organizations that are sharing this issue and check their "enabled" status
        $q = QueryFactory::select()
            ->table(MVC_Core::$db->tab['iod'], 'org_id,is_enabled')
            ->where("`issue_id` = ?", $issueId);

        $r = MVC_Core::$db->query($q);

        if (!$r || empty($r))
            return MVC_Core::$debug->dump("No organizations are connected to the issue.", __METHOD__, 'error');

        foreach ($r as $iod_row) {
            if ($iod_row['org_id'] == $orgId) {
                $iod_row['xfer_time'] = time();
                $iod_row['xfer_ip'] = sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']));

                // And update the org-specific record with the transfer information
                unset($iod_row['org_id']);
                MVC_Core::$db->query(
                    QueryFactory::update()
                        ->table(MVC_Core::$db->tab['iod'])
                        ->values($iod_row)
                        ->where("`issue_id` = ?", $issueId)
                        ->where("`org_id` = ?", $orgId)
                );
            }
        }
    }

    /**
     * Disables access for an organization to an issue
     * -- Only sets a flag, doesn't actually remove any data, but prevents the organization from accessing the issue (and their data)
     *
     * @param int|string $locator Issue locator ( numeric or trackID )
     * @param int $org_id
     * @param bool $no_checks If true, it is assumed that $locator is `issue_id` and organization membership has already been checked.
     */
    public function org_Disable($locator, $org_id, $no_checks = false)
    {
        if($no_checks) {
            if(!is_numeric($locator))
                return MVC_Core::$debug-> dump("No-Checks Locator is not numeric !", __METHOD__, 'warning');
            $this-> issue['id'] = $locator;

        } else {
            $this-> read($locator, array());
            if(empty($this-> issue))
                return MVC_Core::$debug-> dump("Issue could not be located", __METHOD__, 'warning');


            if($org_id != $this-> issue['master_org_id'] && !in_array($org_id, $this-> issue['shared_org_ids']))
                return MVC_Core::$debug-> dump("Not part of the list of organizations with access to this issue.", __METHOD__, 'warning');

        }


       // Set the organization as "not enabled".

        $q=QueryFactory::update()
            -> table(MVC_Core::$db-> tab['iod'])
            -> where("`issue_id` = ?", $this-> issue['id'])
            -> where("`org_id` = ?", $org_id)
            -> values(array(
                'is_enabled'=> false,
                'process'=> 'hidden',
                'time_hide'=> time()
            ));
        $r = MVC_Core::$db-> query($q);

        if(!$r)
            return MVC_Core::$debug-> dump("Error while attempting to disable the organization access to the issue", __METHOD__, 'warning');

        if($org_id == $this-> issue['master_org_id']) {
            // If the organization was master, update the issue and set it to have no master.
            $this-> update($this-> issue['id'], array(
                'master_org_id'=> null
            ));
        } else {
            // Otherwise, just remove the organization from the "shared_org_ids" list
            $key = array_search($org_id, $this-> issue['shared_org_ids']);
            unset($this-> issue['shared_org_ids'][$key]);
            $this-> update($this-> issue['id'], array(
                'shared_org_ids'=> $this-> issue['shared_org_ids']
            ));
        }

        $this-> history_Create($this-> issue['id'], array('org_disabled'=> $org_id), 'sharing');
        // Reduce the organization issue counter since they no longer see this particular issue
        $m_org = new App_Model_Organization();
        $m_org-> update($org_id, array(
            'count_issues'=> "`count_issues` - 1"

        ), false, true);
        return true;
    }



    public function org_Disable_v2($locator, $org_id, $no_checks = false)
    {
        if($no_checks) {
            if(!is_numeric($locator))
                return MVC_Core::$debug-> dump("No-Checks Locator is not numeric !", __METHOD__, 'warning');
            $this-> issue['id'] = $locator;

        } else {
            $this-> read($locator, array());
            if(empty($this-> issue))
                return MVC_Core::$debug-> dump("Issue could not be located", __METHOD__, 'warning');


            if($org_id != $this-> issue['master_org_id'] && !in_array($org_id, $this-> issue['shared_org_ids']))
                return MVC_Core::$debug-> dump("Not part of the list of organizations with access to this issue.", __METHOD__, 'warning');

        }


       // Set the organization as "not enabled".

        $q=QueryFactory::update()
            -> table(MVC_Core::$db-> tab['iod'])
            -> where("`issue_id` = ?", $this-> issue['id'])
            -> where("`org_id` = ?", $org_id)
            -> values(array(
                'is_enabled'=> false,
                'process'=> 'hidden',
                'time_hide'=> time()
            ));
        $r = MVC_Core::$db-> query($q);

        if(!$r)
            return MVC_Core::$debug-> dump("Error while attempting to disable the organization access to the issue", __METHOD__, 'warning');

        if($org_id == $this-> issue['master_org_id']) {
            // If the organization was master, update the issue and set it to have no master.
            $this-> update($this-> issue['id'], array(
                'master_org_id'=> null
            ));
        } else {
            // Otherwise, just remove the organization from the "shared_org_ids" list
            $key = array_search($org_id, $this-> issue['shared_org_ids']);
            unset($this-> issue['shared_org_ids'][$key]);
            $this-> update($this-> issue['id'], array(
                'shared_org_ids'=> $this-> issue['shared_org_ids']
            ));
        }

        $this-> history_Create($this-> issue['id'], array('org_disabled'=> $org_id), 'sharing');
        // Reduce the organization issue counter since they no longer see this particular issue
        $m_org = new App_Model_Organization();
        $m_org-> update($org_id, array(
            'count_issues'=> "`count_issues` - 1"

        ), false, true);
        return true;
    }

    /**
     * Reenables access for an organization to a record. The organizaton must be already connected for this to work.
     *
     * @param int|string $locator Record locator ( numeric or trackID )
     * @param int $org_id
     * @param bool $no_checks If true, it is assumed that $locator is `issue_id` and organization membership has already been checked.
     */
    public function org_Enable($locator, $org_id, $no_checks = false)
    {
        $this-> read($locator, array());
        if(empty($this-> issue))
            return MVC_Core::$debug-> dump("Issue could not be located", __METHOD__, 'warning');

        if($org_id == $this-> issue['master_org_id'] || in_array($org_id, $this-> issue['shared_org_ids']))
            return MVC_Core::$debug-> dump("The organization connection is already enabled, nothing to do.", __METHOD__, 'warning');

        // Set the organization as "enabled".
        $q=QueryFactory::update()
            -> table(MVC_Core::$db-> tab['iod'])
            -> where("`issue_id` = ?", $this-> issue['id'])
            -> where("`org_id` = ?", $org_id)
            -> values(array(
                'is_enabled'=> true,
                'process'=> 'active',
                'time_hide'=> null
            ));

        $r = MVC_Core::$db-> query($q);
        if(!$r)
            return MVC_Core::$debug-> dump("Error while attempting to enable the organization access to this record", __METHOD__, 'warning');

        // Add the organization back to the list of "shared_org_ids" ; Note, this method cannot be used to set master - Instead, use self::org_SetAsOwner()
        $this-> issue['shared_org_ids'][] = $org_id;
        $this-> update($this-> issue['id'], array(
            'shared_org_ids'=> $this-> issue['shared_org_ids']
        ));

        $this-> history_Create($this-> issue['id'], array('org_enabled'=> $org_id), 'sharing');

        // Increase the organization issue counter
        $m_org = new App_Model_Organization();
        $m_org-> update($org_id, array(
            'count_issues'=> "`count_issues` + 1"
        ), false, true);

        return true;
    }

    /**
     * Remove an organization from the list.
     * -- Owner organization cannot be disconnected.
     *
     * [ WARNING ] This method removes all the data that an organization might have in relation to an issue ! Use with caution !
     *			 The removal is permanent and not reversible ! Re-adding the same organization will start with a clean sheet !
     *
     * @param int|string $locator Issue locator ( numeric or trackID )
     * @param int $org_id The organization which will lose access and data related to the issue.
     * @param bool $update_issue Update the "shared_org_ids" for the issue or not
     */
    public function org_Remove($locator, $org_id, $update_issue = true, $removed_attached_data = false)
    {
        $this-> read($locator, array());
        if(empty($this-> issue))
            return MVC_Core::$debug-> dump("Record could not be located", __METHOD__, 'warning');

        if($this-> issue['master_org_id'] == $org_id)
            return MVC_Core::$debug-> dump("Cannot remove the current owner organization", __METHOD__, 'warning');

        // TODO: Verify if the link exists by accessing IOD, and not the "shared_org_ids" index (so it works when if the organization is disabled)
// 		if(!in_array($org_id, $this-> issue['shared_org_ids']))
// 			return MVC_Core::$debug-> dump("Not part of the list of organizations with access to this issue.", __METHOD__, 'warning');

        // WARNING ! Deleting organization specific data

        $q=QueryFactory::delete()
            -> table(MVC_Core::$db-> tab['iod'])
            -> where("`issue_id` = ?", $this-> issue['id'])
            -> where("`org_id` = ?", $org_id);

        $r = MVC_Core::$db-> query($q);
        if(!$r)
            return MVC_Core::$debug-> dump("Error while attempting to remove organization connnected data", __METHOD__, 'warning');

        // update the issue then show it by redirecting to the index action.
        if($update_issue) {
            // Remove the organization from the "shared_org_ids" list
            $key = array_search($org_id, $this-> issue['shared_org_ids']);
            unset($this-> issue['shared_org_ids'][$key]);
            $this-> update($this-> issue['id'], array(
                'shared_org_ids'=> $this-> issue['shared_org_ids']
            ));

            $this-> history_Create($this-> issue['id'], array('org_removed'=> $org_id), 'sharing');
        }

        if($removed_attached_data) {
            // TODO: Remove Contacts, Convos, Files, Tasks and Notes which are not shared/public (and considered private for the organization)

        }

        return true;
    }

    /**
     * Reads extended information about organizations sharing a record.
     * -- Used for the Sharing tab in the record overview, but also for selectors needing the list of organizations
     *
     * @param int|string $locator Record locator ( numeric or trackID )
     * @param bool $wtr What to read
     * @param bool $unrestricted_mode If TRUE, read disabled links as well (to allow reenabling)
     */
    public function org_GetList($locator, $wtr = array(), $unrestricted_mode = false)
    {
        $this-> read($locator, array());
        if(empty($this-> issue))
            return MVC_Core::$debug-> dump("Record could not be located", __METHOD__, 'warning');

        $wtr = $this-> wtr_Helper($wtr);

        $iod_fields = array("commtime", "priority_key", "status_key", "xfer_time");
        if($unrestricted_mode)
            array_push($iod_fields, "is_enabled", "linktime");

        if(in_array('extra', $wtr))
            array_push($iod_fields, "count_files", "count_convos", "count_notes", "count_tasks", "xfer_by", "xfer_to", "xfer_ip");

        $q=QueryFactory::select()
            // Issue organization data and information about the organization itself
            -> table(MVC_Core::$db-> table('iod'), $iod_fields)
            -> table(MVC_Core::$db-> table('org'), QueryFactory::autoalias_Add_Prefix("id,type_id,name,contact_id", '_org_'), 'left', "`org`.id = `iod`.org_id")
            -> where("`iod`.issue_id = ?", $this-> issue['id'])
            -> order("`org`.name ASC");

        // Join the assigned user to each of the shared organizations
        if(in_array('assigned', $wtr))
            $q-> table(MVC_Core::$db-> table('usr'), QueryFactory::autoalias_Add_Prefix("id,name,contact_id", '_usr_'), 'left', '`usr`.id = `iod`.assigned_to');

        // To normal users (non-admin), only show the enabled organizations
        if(!$unrestricted_mode)
            $q-> where("`iod`.is_enabled = 1");

        // Run the query and get the results
        $r = MVC_Core::$db-> query($q);
        if(!$r)
            return MVC_Core::$debug-> dump("Could not read record-linked organizations", __METHOD__, 'warning');

        $r-> split_by_prefixes = array(
            // tidy
            'count'=> "count_",
            'transfer'=> "xfer_",
            // extra tables
            'org'=> "_org_",
            'assigned'=> "_usr_"
        );

        $shared_orgs = array();
        foreach($r as $sho) {
            if($sho['org']['id'] == $this-> issue['master_org_id'])
                $sho['master'] = true;
            $sho['org']['id'] = (int)$sho['org']['id'];

            $sho['disabled'] = $unrestricted_mode? !$sho['is_enabled']: false;
            unset($sho['is_enabled']);

            // Operation times
            if(!empty($sho['commtime']))
                $sho['commtime'] = date("Y-m-d H:i:s", $sho['commtime']);
            if(!empty($sho['linktime']))
                $sho['linktime'] = date("Y-m-d H:i:s", $sho['linktime']);
            if(!empty($sho['transfer']['time']))
                $sho['transfer']['time'] = date("Y-m-d H:i:s", $sho['transfer']['time']);

            if(in_array('assigned', $wtr) && !empty($sho['assigned']['id'])) {
                $sho['assigned']['id'] = (int)$sho['assigned']['id'];
                $sho['assigned']['contact_id'] = (int)$sho['assigned']['contact_id'];
                unset($sho['assigned_to']);
            }

            // Translate status/priority
            $sho['status'] = !empty($sho['status_key'])? $this-> statuses[$sho['status_key']]: "";
            $sho['priority'] = !empty($sho['priority_key'])? $this-> priorities[$sho['priority_key']]: "";

            $shared_orgs[] = $sho;
        }

        return $shared_orgs;
    }

//----- Record Blueprints CRUD -----//

    /**
     * Stored last read issue type definition (1 entry) - Will be used by Issue Primary CRUD
     *
     * @var array
     */
    protected $ist_def;

    /**
     * Makes preparations (decoding) on some xfield definition elements (if necessary)
     *
     * @param array $fdef
     * @param bool $cd_ex False = Return only CD original (for editing record type) / True = Return CD_EX (for using the record type data), and discard CD original
     */
    public function ist_XfieldDef_Prep(&$fdef, $cd_ex = true) // FIXME: Temporary "public" for the admin's DBm Single -> Multi function to work
    {
    
    
      // echo $q;
 //echo $iid;
// echo $this-> ist_def['id'];

        // Remove unnecessary/duplicated data:
        unset($fdef['ist_id'], $fdef['fk']);


        // Make the field's numeric ID an Int, or remove it if missing ( for when creating a new field )
        if(!empty($fdef['id']))
            $fdef['id'] = (int)$fdef['id'];
        else
            unset($fdef['id']);

        // Decodes the optional "Options" field
        if(isset($fdef['o']) && is_string($fdef['o']))
            $fdef['o'] = json_decode($fdef['o'], true);

        // Decodes the optional "Choice Data" field ( which might or might not have a "key" for the data choices )
        if(isset($fdef['cd']) && is_string($fdef['cd'])) {
            $fdef['cd'] = json_decode($fdef['cd'], true);
            if($cd_ex) {
                $choices = array();
                foreach($fdef['cd'] as $c) {
                    $c = trim($c);
                    if(preg_match("/#([^:]+): (.*)/", $c, $matches))
                        $choices[$matches[1]] = $matches[2];
                    else
                        $choices[$c] = $c;
                }
                $fdef['cd_ex'] = $choices;
                unset($fdef['cd']);
            }
        }
    }

    /**
     * Adds a record blueprint to the system
     *
     * @param array $blueprint
     * @return array|bool
     */
    public function ist_Create($blueprint)
    {
        if(!is_array($blueprint))
            return MVC_Core::$debug-> dump("Blueprint definition must be an array", __METHOD__, null, "error");

        $ist_fields = array('xfield_def', 'scope', 'name', 'key', 'short_plural', 'description', 'templates', 'statuses', 'priorities', 'settings', 'group_ids', 'sections');
        // Any data which is not part of the $ist_fields is discarded automatically
        foreach($blueprint as $field=> $value)
            if(!in_array($field, $ist_fields))
                unset($blueprint[$field]);

        // TODO: Test if the data is properly formatted and contains all the required information before attempting to encode/enter in the DB

        // For now just check a few base values
        if(empty($blueprint['name']))
            return MVC_Core::$debug-> dump("Record type doesn't have a name", __METHOD__, null, "error");
        if(empty($blueprint['key']))
            return MVC_Core::$debug-> dump("Record type doesn't have a key", __METHOD__, null, "error");
        if(empty($blueprint['short_plural']))
            return MVC_Core::$debug-> dump("A plural form is needed for proper functionality", __METHOD__, null, "error");


//echo $path;
			 	
        // Encode the Blueprint for storing (Templates, Statuses, Priorities, Settings)
        if(isset($blueprint['templates']))
            $blueprint['templates'] = MVC_Core::pretty_JSON(@json_encode($blueprint['templates'])); // template file names (for Create, View, Edit, Render, Print) - possibly add more later?
        if(isset($blueprint['statuses']) && is_array($blueprint['statuses']))
            $blueprint['statuses'] = implode(',', $blueprint['statuses']); // list of statuses keys ( 2-4 letter keys, searched in translation tables )
        if(isset($blueprint['priorities']) && is_array($blueprint['priorities']))
            $blueprint['priorities'] = implode(',', $blueprint['priorities']); // list of statuses keys ( 2-4 letter keys, searched in translation tables )
        if(isset($blueprint['settings']))
            $blueprint['settings'] = MVC_Core::pretty_JSON(@json_encode($blueprint['settings'])); // General configuration for this Record Blueprint
        if(isset($blueprint['group_ids']) && is_array($blueprint['group_ids']))
            $blueprint['group_ids'] = implode(',', $blueprint['group_ids']); // Selection of Group ID's which are visible for this issue type
        // TODO: Sections cannot be defined at create time yet
// 		if(isset($blueprint['sections']))
// 			$blueprint['sections'] = MVC_Core::pretty_JSON($blueprint['sections']); // List of field sections (key, value) - Already encoded from JS, just make it pretty

        // If present in the blueprint, prepare xfields for local storage (note that they are missing the numeric ID, as they haven't yet been inserted in the database)
        // -- The current version of the editor does not create fields on create, but this method may be called from some other automated process
        if(!empty($blueprint['xfield_def'])) {
            foreach($blueprint['xfield_def'] as &$fdef)
                $this-> ist_XfieldDef_Prep($fdef);

            // extract the xfield_def as it doesn't go in the primary table
            $xfield_def = $blueprint['xfield_def'];
        }
        unset($blueprint['xfield_def'], $ist_fields[0]);

        // build the query and run it
        $q=QueryFactory::insert()
            -> table(MVC_Core::$db-> tab['ist'], $ist_fields)
            -> values($blueprint);
           
        $r = MVC_Core::$db-> query($q);
        if(!$r)
            return false;
            
           
        $iid = MVC_Core::$db-> insert_id;


 
        // success, the IST has been inserted, proceed to inserting the xfield definitions in the XFD table (if any exist)
        if(!empty($xfield_def)) {
        
          
            $q=QueryFactory::insert()
                -> table(MVC_Core::$db-> tab['xfd']);
            foreach($xfield_def as $fdef) {
                $q-> values(array(
                    'ist_id'=> $iid,
                    'fk'=> $fdef['fk'],
                    's'=> $fdef['s'],
                    'n'=> $fdef['n'],
                    't'=> $fdef['t'],
                    'o'=> isset($fdef['o'])? json_encode($fdef['o']): null,
                    'cd'=> isset($fdef['cd'])? json_encode($fdef['cd']): null
                ));
            }
            
          
            MVC_Core::$db-> query($q);
            
            
        }

        return $iid;
    }

    /**
     * Discovers which record type has a certain key.
     * - This is used to quickly convert a text key into a numeric ID (after a public record type selector which returns the text keys, not the numeric IDs)
     *
     * @param string $key
     * @return int Numeric Issue Type ID
     */
    public function ist_Locate($key)
    {
        $q=QueryFactory::select()
            -> table(MVC_Core::$db-> tab['ist'], array('id'))
            -> where("`key` = ?", $key)
            -> where("`scope` != 'override'") // Overrides are excluded here!
            -> limit(1);

        $r = MVC_Core::$db-> query($q);
        if(!$r || empty($r))
            return false;

        $ist_raw = $r-> current();
        return (int)$ist_raw['id'];
    }

    /**
     * Reads the Record Blueprint from the database and prepares the data
     *
     * @param int $ist_id_or_key
     * @param bool $xfields_mode What to do with xfields
     * - true = keep the original Choice-Data (only used when editing issue-type. Normally cd-ex is used)
     * - false = remove Choice-Data, keep CD-EX(panded)
     * - null = do not read xfield definitions at all
     * @param bool $force Force reading even if $this-> ist exists and contains the correct data
     * @param bool $return_true_id The ID returned is the row's real ID ( including overrides and compatibles ). Otherwise the base type id is returned.
     * @param bool $ignore_xf_hidden ( Reads all fields, including hidden ones )
     */
    public function &ist_Read($ist_id_or_key, $xfields_mode = false, $force = false, $return_true_id = false, $ignore_xf_hidden = false)
    {
        $field = is_numeric($ist_id_or_key)? "id": "key";
        if($force || empty($this-> ist_def) || $ist_id_or_key!=$this-> ist_def[$field]) {
            // read the IST from the database
            $q=QueryFactory::select()
                -> table(MVC_Core::$db-> tab['ist'])
                -> where("`{$field}` = ?", $ist_id_or_key)
                -> limit(1);

            // If accessed by key, overrides are ignored (only base type can be read)
            if($field == 'key')
                $q-> where("`scope` != 'override'");

            $r = MVC_Core::$db-> query($q);

            if(!$r || empty($r)) {
                // Issue type not found (either by key or ID)
                $false = false;
                return $false;
            }

            if($r && count($r)==1) {
                $ist_raw = $r-> current();

                // Decodes a database-stored Issue Type data into the internal member self::ist_def
                $this-> ist_def = array();

                // base data
                if($return_true_id) {
                    $this-> ist_def['id'] = (int)$ist_raw['id'];
                    $this-> ist_def['base_id'] = (int)$ist_raw['compatible_with'];
                } else {
                    if($ist_raw['scope'] == 'override')
                        $this-> ist_def['base_id'] = $this-> ist_def['id'] = (int)$ist_raw['compatible_with'];
                    else
                        $this-> ist_def['base_id'] = $this-> ist_def['id'] = (int)$ist_raw['id'];
                }
                if(empty($this-> ist_def['base_id']))
                    $this-> ist_def['base_id'] = $this-> ist_def['id'];
                $this-> ist_def['scope'] = $ist_raw['scope'];
                $this-> ist_def['key'] = $ist_raw['key'];
                $this-> ist_def['name'] = $ist_raw['name'];
                $this-> ist_def['short_plural'] = $ist_raw['short_plural'];
                $this-> ist_def['description'] = $ist_raw['description'];
                $this-> ist_def['count_issues'] = (int)$ist_raw['count_issues']; // Note that incorrect count of issues is returned for overrides (always 0)

                // Templates (JSON), Statuses (CSV), Priorities (CSV), Settings (JSON), Group IDs (CSV), Sections (JSON)
                $this-> ist_def['templates'] = @json_decode($ist_raw['templates'], true);
                $this-> ist_def['statuses'] = explode(',', $ist_raw['statuses']);
                $this-> ist_def['priorities'] = explode(',', $ist_raw['priorities']);
                $this-> ist_def['settings'] = @json_decode($ist_raw['settings'], true);
                if(!empty($ist_raw['group_ids']))
                    $this-> ist_def['group_ids'] = explode(',', $ist_raw['group_ids']);
                $this-> ist_def['sections'] = @json_decode($ist_raw['sections'], true);

                // also make the "human readable" list of statuses and priorities
                $this-> ist_def['statuses_ex'] = array();
                foreach($this-> ist_def['statuses'] as $status)
                    $this-> ist_def['statuses_ex'][$status] = $this-> statuses[$status];
                $this-> ist_def['priorities_ex'] = array();
                foreach($this-> ist_def['priorities'] as $priority)
                    $this-> ist_def['priorities_ex'][$priority] = $this-> priorities[$priority];

                // Do we need XFields?
                if(!is_null($xfields_mode)) {
                    // read the xfield definitions from their own table
                    $q=QueryFactory::select()
                        -> table(MVC_Core::$db-> tab['xfd'])
                        -> where("`ist_id` = ?", $ist_raw['scope'] == 'override'? $ist_raw['compatible_with']: $ist_raw['id']) // Check if this is an override, in this case the xfields are actually read from the "original"
                        -> order("`s` ASC, `n` ASC");

                    if(!$ignore_xf_hidden) {
                        if(isset($this-> ist_def['settings']['xf_hidden']))
                            $q-> where("`fk` NOT IN(?)", $this-> ist_def['settings']['xf_hidden']);
                    }

                    $r = MVC_Core::$db-> query($q);

                    $this-> ist_def['xfield_def'] = array();
                    foreach($r as $xf_row) {
                        $fdef = $xf_row;
                        $this-> ist_XfieldDef_Prep($fdef, !$xfields_mode);
                        $this-> ist_def['xfield_def'][$xf_row['fk']] = $fdef;
						//print_r($fdef);
						//print $this-> ist_def['xfield_def'][$xf_row['fk']];
                    }
                }
            } else {
                $false = false;
                return $false;
            }
            // When IST is read, also instance the processing plugin (when previously instanced, this does nothing)
            if(isset($this-> ist_def['settings']['plugin']))
                $this-> record_Plugin($this-> ist_def['key']);
        }
        return $this-> ist_def;
    }

    /**
     * Update a record blueprint
     *
     * @param int $ist_id
     * @param array $blueprint
     * @return array|bool
     */
    public function ist_Update($ist_id, $blueprint)
    {
        if(!is_array($blueprint))
            return false; // error, type definition must be array

        // TODO: Test if the data is properly formatted and contains all the required information before attempting to encode/enter in the DB
        if(isset($blueprint['key']) || isset($blueprint['settings'])) {
            // When changing key or settings, read the existing data so stuff is being checked and merged before updating
            // TODO: This might be removed once the form is capable of editing all the settings
            $q=QueryFactory::select()
                -> table(MVC_Core::$db-> tab['ist'], array("key", "settings"))
                -> where("`id` = ?", $ist_id)
                -> limit(1);
            $r = MVC_Core::$db-> query($q);

            if($r && count($r)==1) {
                $ist_raw = $r-> current();
                // First check if key was changed
                if(isset($blueprint['key'])) {
                    if($blueprint['key'] != $ist_raw['key']) {
                        // Rename folders, but first check if the new name doesn't already exist, in which case deny change
                        $new_path = ROOT. DS. 'app'. DS. 'view'. DS. 'issues'. DS. $blueprint['key'];
                        if(file_exists($new_path))
                            throw new Exception("Unable to change folder key, it already exists. Please click BACK!");

                        // Rename templates folder
                        rename(ROOT. DS. 'app'. DS. 'view'. DS. 'issues'. DS. $ist_raw['key'], $new_path);
                        // Also attempt to rename the "icons" if they exists (silent error if it doesn't exist)
                        @rename(ROOT. DS. 'htdocs'. DS. 'img'. DS. 'icons'. DS. 'ist'. DS. $ist_raw['key'].".png", ROOT. DS. 'htdocs'. DS. 'img'. DS. 'icons'. DS. 'ist'. DS. $blueprint['key'].".png");
                        @rename(ROOT. DS. 'htdocs'. DS. 'img'. DS. 'icons16'. DS. 'ist'. DS. $ist_raw['key'].".png", ROOT. DS. 'htdocs'. DS. 'img'. DS. 'icons16'. DS. 'ist'. DS. $blueprint['key'].".png");
                    }
                }

                // And merge settings
                if(isset($blueprint['settings'])) {
                    $original_settings = @json_decode($ist_raw['settings'], true);
                    $blueprint['settings'] = array_merge($original_settings, $blueprint['settings']);
                }
            } else
                throw new Exception("Issue type with id {$ist_id} not found. Cannot update...");
        }

        // TODO: Xfield def should not be saved all at once !
        // Use the create_XFields, delete_XFields (and add update_Xfield) methods to handle them individually
// 		if(isset($blueprint['xfield_def']))
// 			$xfield_def = $blueprint['xfield_def'];

        // save the "xfdeleted" before it is removed
// 		if(isset($blueprint['xfdeleted']))
// 			$xfdeleted = explode(',', $blueprint['xfdeleted']);

        // filter out other keys which do not belong here
        $fields = array('scope', 'compatible_with', 'name', 'key', 'short_plural', 'description', 'count_issues', 'templates', 'statuses', 'priorities', 'settings', 'group_ids', 'sections');
        foreach($blueprint as $field=> $value)
            if(!in_array($field, $fields))
                unset($blueprint[$field]);

        // Encode the blueprint Templates (JSON), Statuses (CSV), Priorities (CSV), Settings (JSON), Group_ids (CSV), Sections (JSON)
        if(isset($blueprint['templates']))
            $blueprint['templates'] = MVC_Core::pretty_JSON(@json_encode($blueprint['templates'])); // template file names (for Create, View, Edit, Render, Print) - possibly add more later?
        if(isset($blueprint['statuses']) && is_array($blueprint['statuses']))
            $blueprint['statuses'] = implode(',', $blueprint['statuses']); // list of statuses keys ( 2-4 letter keys, searched in translation tables )
        if(isset($blueprint['priorities']) && is_array($blueprint['priorities']))
            $blueprint['priorities'] = implode(',', $blueprint['priorities']); // list of statuses keys ( 2-4 letter keys, searched in translation tables )
        if(isset($blueprint['settings']))
            $blueprint['settings'] = MVC_Core::pretty_JSON(@json_encode($blueprint['settings'])); // General configuration for this Record Blueprint
        if(isset($blueprint['group_ids']) && is_array($blueprint['group_ids']))
            $blueprint['group_ids'] = implode(',', $blueprint['group_ids']); // Selection of Group ID's which are visible for this issue type
        if(isset($blueprint['sections']))
            $blueprint['sections'] = MVC_Core::pretty_JSON($blueprint['sections']); // List of field sections (key, value) - Already encoded from JS, just make it pretty

        // build the query and run it
        $q=QueryFactory::update()
            -> table(MVC_Core::$db-> tab['ist'])
            -> values($blueprint)
            -> where("`id` = ?", $ist_id);
        /*
		if($r && isset($xfield_def)) {
			// IST update successful, proceed updating xfield definitions (one query for each, well, this update happens very rarely, so no performance problem)
			foreach($xfield_def as $fdef) {
				// if $fdef['cd'] is missing, reassamble it from 'cd_ex' (if it exists)
				if(!isset($fdef['cd']) && isset($fdef['cd_ex'])) {
					$fdef['cd'] = array();
					foreach($fdef['cd_ex'] as $kw=> $string)
						if($kw != $string)
							$fdef['cd'][] = "#{$kw}: {$string}";
						else
							$tdef['cd'][] = $string;
				}
				// not needed in options, instead, $fdef['fk'] is used
				unset($fdef['o']['fk'], $fdef['o']['s']);

				// update or insert, if the field has an ID or not
				$values = array(
					'fk'=> $fdef['fk'], // change of field Key is now possible, because fields have numerical IDs and key is only useful for forms
					's'=> $fdef['s'],
					'n'=> $fdef['n'],
					't'=> $fdef['t'],
					'o'=> isset($fdef['o'])?json_encode($fdef['o']):null,
					'cd'=> isset($fdef['cd'])?json_encode($fdef['cd']):null
				);

				if($fdef['id'] > 0) {
					$q=QueryFactory::update()
					-> table(MVC_Core::$db-> tab['xfd'])
					-> where("`ist_id` = ?", $ist_id)
					-> where("`id` = ?", $fdef['id'])
					-> values($values);
				} else {
					$values['ist_id'] = $ist_id;

					$q=QueryFactory::insert()
					-> table(MVC_Core::$db-> tab['xfd'])
					-> values($values);
				}

				MVC_Core::$db-> query($q);
			}

			// Finally, delete from the definition all the fields that have been removed
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['xfd'])
			-> where("`ist_id` = ?", $ist_id)
			-> where("`id` IN(?)", $xfdeleted);

			MVC_Core::$db-> query($q);

			// ... and remove the orphaned data from the issue fields table ( WARNING ! Don't delete a field if the data is still important ! )
			$q=QueryFactory::delete()
			-> table(MVC_Core::$db-> tab['ixf'])
			-> where("`fk_id` IN(?)", $xfdeleted);

			MVC_Core::$db-> query($q);
		}
*/
        return MVC_Core::$db-> query($q);
    }

    /**
     * A simpler method than the one above, designed to update only the counter of issues per issue type
     *
     * @param int $ist_id
     * @param int $variation How much is added or substracted from the count_issues
     */
    public function ist_Counter_Update($ist_id, $variation = 0)
    {
        if($variation == 0)
            return; // nothing to update
        if($variation> 0)
            $variation = "+ ".$variation;

        // build the query and run it
        $q=QueryFactory::update()
            -> table(MVC_Core::$db-> tab['ist'])
            -> values(array("count_issues"=> "`count_issues` {$variation}"), null, true)
            -> where("`id` = ?", $ist_id);

        return MVC_Core::$db-> query($q);
    }

    /**
     * Delete an issue type
     * -- Used only for testing purposes.
     *
     * @param int $ist_id
     * @param bool $include_data Deletes all data of that type (issues, issue_fields, issue_orgdata, issue_updates, issue_contacts) // Not yet implemted
     */
    public function ist_Delete($ist_id, $include_data = false)
    {
        return; // Disabled, too dangerous (and also incomplete)
        /*
		// first, check if the issue type id actually exists
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> tab['ist'], 'id')
		-> where("`id` = ?", $ist_id)
		-> limit(1);

		$r = MVC_Core::$db-> query($q);


		if(!$r)
			return MVC_Core::$debug-> dump("Cannot find issue type id {$ist_id}", __METHOD__, null, "error");

		// okay, it exists, burn it !

		// Step (n): Delete all issues of this specific type
		// TODO: Affects users, organizations, tasks, notes... many things

		// Step (n): Delete the xfield definitions for this issue type (multiple rows)
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['xfd'])
		-> where("`ist_id` = ?", $ist_id);

		MVC_Core::$db-> query($q);

		// Final step: Delete the issue type record itself
		$q=QueryFactory::delete()
		-> table(MVC_Core::$db-> tab['ist'])
		-> where("`id` = ?", $ist_id)
		-> limit(1);

		if($this-> ist_def['id'] == $ist_id)
			$this-> ist_def = null;

		return MVC_Core::$db-> query($q);
*/
    }

    /**
     * Reads non-complex issue data (for selectors or grids). This method doesn't read xfield definitions.
     *
     * @param array|int $id_list Allow selecting only certain id's (when array), or a single item (when int)
     * @param bool $use_keys Uses "key" field for filtering out, not the "id" field (when $id_list is not null) - In this case only Base scope can be accessed (global, org)
     * @param string $read_mode One of the following:
     * - "basic" - Reads only information for a selector/dropdown (id, name, key) - Reads only "global" and "org" scopes
     * - "converter" - Reads basic information as "basic" however covers all scopes. Used for issue type ID to Name converter.
     * - "extended" - Reads all fields necessary for issue type selector grid (Admin's issue type editor) (id, scope, name, key, short_plural, description, count_issues) - Reads all scopes
     * - "full" - Reads the entire table with everything (Not currently used)
     */
    public function ist_Get_List($id_list = null, $use_keys = false, $read_mode = 'basic')
    {
        switch($read_mode) {
            case 'basic':
                $fields = array('id', 'name', 'key','description');
                $scopes = array('global', 'org');
                break;
            case 'converter':
                $fields = array('id', 'scope', 'name', 'key', 'short_plural','description');
                $scopes = null;
                break;
            case 'extended':
                $fields = array('id', 'scope', 'name', 'key', 'short_plural', 'description', 'count_issues', 'sections');
                $scopes = null;
                break;
            case 'full':
                $fields = null;
                $scopes = null;
                break;
        }

        $q=QueryFactory::select()
            -> table(MVC_Core::$db-> tab['ist'], $fields)
            -> order("FIELD(`scope`, 'global', 'org', 'override', 'admin'), `name` ASC");

        // ID Filter
        $searchfield = $use_keys? 'key': 'id';
        if(is_array($id_list)) {
            if(!empty($id_list))
                $q-> where("`{$searchfield}` IN(?)", $id_list);
        } elseif(is_scalar($id_list))
            $q-> where("`{$searchfield}` = ?", $id_list)-> limit(1);

        // Scope filter
        if(!empty($scopes))
            $q-> where("`scope` IN(?)", $scopes);
		return MVC_Core::$db-> query($q);
    }

    /**
     * New version of Record Type Get_List which support filters and ordering - Used by the revamped RT Editor
     * @param string $read_mode Preset list of fields to read
     * @param Callback $qfo_filter If no callback specified, it simply reads all the tasks of the specified type_id
     * @param array $filter_params
     * @param string $order_by Which field to be used for ordering
     * @param int $count_rows
     * @param int $start_row
     * @param bool $calc_found_rows If true, uses SQL_CALC_FOUND_ROWS to determine the total number of rows if limits were not set
     * @return MVC_Database_Result
     */
    public function rt_Get_List($read_mode = 'basic', $qfo_filter = null, $filter_params = array(), $order_by = null, $count_rows = 100, $start_row = 0, $calc_found_rows = false)
    {
        switch($read_mode) {
            case 'basic':
                $fields = array('id', 'name', 'key'); break;
            case 'converter':
                $fields = array('id', 'scope', 'name', 'key', 'short_plural'); break;
            case 'extended':
                $fields = array('id', 'scope', 'name', 'key', 'short_plural', 'description', 'count_issues', 'sections'); break;
            case 'full':
                $fields = null;	break;
        }


        $q = $this-> get_List_QueryFactory_Helper($qfo_filter, $filter_params, is_null($order_by)? "FIELD(`scope`, 'global', 'org', 'override', 'admin', 'archive'), `name` ASC": $order_by, $count_rows, $start_row, $calc_found_rows);
        if(empty($q))
            return false; // Error, filter failed

        $q-> table(MVC_Core::$db-> tab['ist'], $fields);

        $r = MVC_Core::$db-> query($q);
        if(!$r || !empty($r))
            return false;// failed to read any records

        return $r;
    }

    /**
     * Assambles an MVC_Form object which is used to process incoming form data (and generate field names, IDs, default values) out of the xfield_def data
     * Assumes that the RT_DEF has already been read and exists in $this-> ist_def;
     *
     * @param array|bool $xfields Used when editing an issue, this will contain the values for xfields (that will be used as form initial values, instead of the defaults)
     * -- Set to false to prevent any kind of default values (for an empty form or when input comes from a previous post)
     * @param array $xfield_def Use this xfields definition, and not the one stored locally in ist_def
     * @return MVC_Form
     */
    public function ist_Factory_Form_Object($xfields = null, &$xfield_def = null)
    {
        if(empty($xfield_def))
            $xfield_def = &$this-> ist_def['xfield_def'];

        if($xfields!==false && !is_array($xfields))
            $xfields = array();
        $form = new MVC_Form();
        foreach($xfield_def as $key=> $fdef) {
            $required = isset($fdef['o']) && isset($fdef['o']['req']) && ($fdef['o']['req'] == true);
            $default = null;
            switch($fdef['t']) {
                case 'text':
                case 'number':
                case 'date':
                    if($xfields!==false) {
                        if(isset($xfields[$key]))
                            $default = (string)$xfields[$key]; // scalar (string) value
                        elseif(isset($fdef['o']) && isset($fdef['o']['def']))
                            $default = (string)$fdef['o']['def'];
                    }
                    $form-> add_Element("Scalar", $key, null, $default, null, "xfd", array('label'=> $fdef['n'], 'req'=> (bool)$required));

                    break;
                case 'enum':
                    if($xfields!==false) {
                        if(isset($xfields[$key]))
                            $default = (string)$xfields[$key]['key']; // one string only
                        elseif(isset($fdef['o']) && isset($fdef['o']['def'])) {
                            if(isset($fdef['o']['num']) && $fdef['o']['num']==true)
                                $default = (int)$fdef['o']['def'];
                            else
                                $default = (string)$fdef['o']['def'];
                        }
                    }
                    $form-> add_Element("Enum", $key, null, $default, $fdef['cd_ex'], "xfd", array('label'=> $fdef['n'], 'req'=> (bool)$required));
                    break;

                case 'set':
                    if($xfields!==false) {
                        if(isset($xfields[$key])) {
                            $default = array();
                            if(!empty($fdef['o']['flag'])) {
                                // Treat multiple choices as "Flags". All options present but a marker "active" exists only for those that have been turned on
                                foreach($xfields[$key] as $field)
                                    if($field['active'])
                                        $default[] = $field['key'];
                            } else {
                                // Only the actual active values are present in the $xfields
                                foreach($xfields[$key] as $field)
                                    $default[] = $field['key'];
                            }

                        } elseif(isset($fdef['o']) && isset($fdef['o']['def']))
                            $default = explode(',', $fdef['o']['def']);
                    }

                    $form-> add_Element("Set", $key, null, $default, $fdef['cd_ex'], "xfd", array('label'=> $fdef['n'], 'req'=> (bool)$required));
                    break;

                default:
                    $plugin = $this-> field_Plugin($fdef['t']); // , true - The parameter means that the field plugins are single-instance use (they should not do any preloading of data) (Not working)
                    if(is_object($plugin)) {
                        $plugin-> fdef = &$fdef;
                        $plugin-> setup_Form($form, $key, $xfields);
                    }
                    break;
            }
        }
        return $form;
    }

//----- Record Blueprint - Field Definitions CRUD -----//

    /**
     * Creates a new XField Definition for a certain Record Type
     * @param int $rt_id
     * @param array $fdef Must contain: fk, n, t; Optional: s, o, cd (mandatory when t is "set", "enum")
     */
    public function xfdef_Create($rt_id, $fdef)
    {
        if(!is_array($fdef))
            return MVC_Core::$debug-> dump("Field definition must be an array", __METHOD__, null, "error");

        // Any data which is not part of the $ist_fields is discarded automatically
        $fields = array('fk', 'n', 't', 's', 'o', 'cd');
        foreach($fdef as $field=> $value)
            if(!in_array($field, $fields))
                unset($fdef[$field]);

        $fdef['ist_id'] = $rt_id;

        // Some checks
        if(!isset($fdef['fk']) || !isset($fdef['n']) || !isset($fdef['t']))
            return MVC_Core::$debug-> dump("Some of the definition entries missing (fk, n, t)", __METHOD__, null, "error");

        $types = array('text', 'number', 'date', 'enum', 'set', 'money', 'awb', 'airport');
        if(!in_array($fdef['t'], $types))
            return MVC_Core::$debug-> dump(sprintf("Unsupported field type %s in definition data", $fdef['t']), __METHOD__, null, "error");

        if(($fdef['t'] == 'enum' || $fdef['t'] == 'set') && !isset($fdef['cd']))
            return MVC_Core::$debug-> dump("Choices data (cd) is missing for Enum or Set field", __METHOD__, null, "error");

        // If present "o" and "cd" must be decodable as JSON and be object/array when decoded
        if($fdef['o'] == '{}')
            $fdef['o'] = null;
        if(!empty($fdef['o'])) {
            $o_dec = @json_decode($fdef['o']);
            if(!is_object($o_dec))
                return MVC_Core::$debug-> dump("Options data (o) is present but is not an array", __METHOD__, null, "error");
        }
        if(!empty($fdef['cd'])) {
            $cd_dec = @json_decode($fdef['cd'], true);
            if(!is_array($cd_dec))
                return MVC_Core::$debug-> dump("Choices data (cd) is present but is not an array", __METHOD__, null, "error");
        }

        if(empty($fdef['s']))
            unset($fdef['s']);

        // All okay, insert
        $q=QueryFactory::insert()
            -> table(MVC_Core::$db-> tab['xfd'])
            -> values($fdef);

        $r=MVC_Core::$db-> query($q);
        if(!$r)
            return MVC_Core::$debug-> dump("Insert field failed", __METHOD__, null, "error");

        return MVC_Core::$db-> insert_id;
    }

    /**
     * Reads a single field definition - At the moment only used for checking and logging
     * @param int $id
     */
    public function xfdef_Read($id)
    {
        $rq=QueryFactory::select()
            -> table(MVC_Core::$db-> tab['xfd'])
            -> where("`id` = ?", (int)$id)
            -> limit(1);

        $r = MVC_Core::$db-> query($rq);
        if(!$r || !empty($r))
            return false; // Error, field not found

        return $r-> current();
    }

    /**
     * Updates an XField Definition
     *
     * @param int $id Field's ID
     * @param array $fdef_new Newe field definition. Note: "t" (type) is ignored here, type cannot be changed once field is created (Expects correct data!)
     * @param bool $alterdata Updates stored data to match the changes to the field (Will read the existing field definition first)
     * -- Supports updating "fk" (field key) in user profiles and views
     * -- Supports renaming keys (and data using those keys) if the matching texts are untouched. If an identical text is not found, the key is not updated (must be updated manually)
     * @param int $rt_id When asking for data update, this is needed to know what to update
     * @param string $rt_key When asking for data update, this is needed to know what to update
     */
    public function xfdef_Update($id, $fdef_new, $alterdata = false, $rt_id = null, $rt_key = null)
    {
        unset($fdef_new['t']);

        // When data update is requested, the existing definition is read first, to allow making comparison between old and new
        // - TODO: This part should not be here but as a spearate "Read Field Definition" method used in the caller
        $ret = array();
        if($alterdata) {
            $rq=QueryFactory::select()
                -> table(MVC_Core::$db-> tab['xfd'], array('fk', 'cd'))
                -> where("`id` = ?", $id);

            $ofdef = MVC_Core::$db-> query($rq)-> current();
            if(!$ofdef)
                return MVC_Core::$debug-> dump("Existing field was not found. Possible error in the UI", __METHOD__, 'error');
        }

        // Fix for empty options
        if($fdef_new['o'] == '{}')
            $fdef_new['o'] = null;

        if(empty($fdef_new['s']))
            $fdef_new['s'] = null;

        $q=QueryFactory::update()
            -> table(MVC_Core::$db-> tab['xfd'])
            -> where("`id` = ?", $id)
            -> values($fdef_new);

        $ret['success'] = MVC_Core::$db-> query($q);

        // Perform data alterations if the update query was successful
        // - TODO: This part should not be here but in the caller function, as it's not part of current model
        if($alterdata && $ret['success']) {
            // Part 1 - If "fk" changed, update user profiles/views - But only if RT id/key are present, otherwise does nothing.
            if($fdef_new['fk'] != $ofdef['fk'] && is_numeric($rt_id) && is_string($rt_key)) {
                $q1 ="UPDATE `user_profiles` SET `data`= replace(`data`, '\"{$ofdef['fk']}\"', '\"{$fdef_new['fk']}\"') WHERE `pkey`='columns_ist_{$rt_id}';";
                $q2 ="UPDATE `user_profile_defs` SET `data`= replace(`data`, '\"{$ofdef['fk']}\"', '\"{$fdef_new['fk']}\"') WHERE `pkey`='columns_ist_{$rt_id}';";
                $q3 ="UPDATE `grid_views` SET `columns`= replace(`columns`, '\"{$ofdef['fk']}\"', '\"{$fdef_new['fk']}\"') WHERE `rt_key`='{$rt_key}';";
                // Run the queries
                MVC_Core::$db-> query($q1);
                $response = MVC_Core::$db-> get_Response();
                $ret['u_pro'] = $response['affected_rows'];

                MVC_Core::$db-> query($q2);
                $response = MVC_Core::$db-> get_Response();
                $ret['u_pro_def'] = $response['affected_rows'];

                MVC_Core::$db-> query($q3);
                $response = MVC_Core::$db-> get_Response();
                $ret['u_vw'] = $response['affected_rows'];
            }

            // TODO: Part 2 - Value key updates for fields of types "enum" (replace one value with another), "set" (replace content in list of values)
            // TODO: Implement as a separate field data updater function (eg. replace_XField_Data)
        }

        return $ret;
    }

    /**
     * Deletes an XField Definition and optionally the data associated with it.
     *
     * @param int $id Field definition ID
     * @param bool $removedata Deletes all data relevant for this field. If False (default), the data remains orphaned and need to be manually cleaned from the database
     * @param int $rt_id When asking for data/settings removal, this is needed to know what to remove
     * @param string $rt_key When asking for data/settings removal, this is needed to know what to remove
     */
    public function xfdef_Delete($id, $removedata = false, $rt_id = null, $rt_key = null)
    {
        if($removedata) {
            // Remove the record data from the fields table
            // TODO: This part should be implemeted as a separate "mass-delete" field method (near delete_XFields)
            $q=QueryFactory::delete()
                -> table(MVC_Core::$db-> tab['ixf'])
                -> where("`fk_id` = ?", $id);

            MVC_Core::$db-> query($q);

            // TODO: Update all user profiles to remove the soon-to-be defunct field from their views
            // TODO: This should be parte of the caller function as it's not relevant for this model
        }

        // Remove the field itself
        $q=QueryFactory::delete()
            -> table(MVC_Core::$db-> tab['xfd'])
            -> where("`id` = ?", $id);

        return MVC_Core::$db-> query($q);
    }

    /**
     * Lists/searches fields used by a record type - Used by the Record Blueprint Editor
     *
     * @param int $rt_id Record Type ID; TODO: Make it possible to have a global field search, to determine if other record types have a similar field
     * @param string $qfo_filter
     * @param array $filter_params
     * @param string $order_by
     * @param int $count_rows
     * @param int $start_row
     * @param bool $calc_found_rows
     * @return boolean|MVC_Database_Result
     */
    public function xfdef_List($rt_id, $qfo_filter = null, $filter_params = array(), $order_by = null, $count_rows = 1000, $start_row = 0, $calc_found_rows = false)
    {
        $q= $this-> get_List_QueryFactory_Helper($qfo_filter, $filter_params, $order_by, $count_rows, $start_row, $calc_found_rows);
        if(empty($q))
            return false; // Error, filter failed

        $q	-> table(MVC_Core::$db-> tab['xfd'])
            -> where('`ist_id` = ?', $rt_id);
        $r= MVC_Core::$db-> query($q);

        if(!$r || !empty($r))
            return false;// failed to read any records

        return $r;
    }

//----- Record update history -----//

    /**
     * Accepted group types (for which there is code to handle the data operations)
     * -- If a new type is implemented, add it here
     * @var array[string]
     */
    public static $accepted_update_types = array(
        'oth',		// Other, anything - Data will be viewed in raw format
        'create',	// Record was just created - History entry contains initial creator user, contact information or system identifier
        'group',	// Data group was altered
        'msgsent',	// Record-connected message was sent
        'msgrecv',	// Record-connected message received (Not implemented yet)
        'xfd',		// Custom Fields altered
        'cache',	// Cache values altered externally (not via field editing)
        'sharing',	// Inter-organization sharing of the record (organization added, removed, enabled or disabled)
        'process',	// Internal Processing level altered (timers might have been set)
        'statprio',	// Status or Priority changed (might have triggered an automatic action)
        'assgn',	// User or Department assignment, unassignment, assignment change
        'file',		// Attached file altered (added, edited, removed)
        'task',		// Task operated (added, changed, removed, time-based actions triggered)
        'note',		// Text-content entry changed (note, documnent - added, edited, removed)
        'contact',	// Attached contact altered (connected, disconnected)
        'xfo'		// Old format xfields (lists only field keys, not very useful) - Kept for compatibility reasons
    );

    /**
     * Creates an record history entry - Stores information about what was updated in JSON format
     *
     * @param array $update_data What was changed - This will be encoded in the table
     * @param string $update_type New format update contains a type identifier which must match the $update_data format to be correctly opened for viewing
     * @param string $comment The update contains a comment (might be auto-generated or human entered)
     * @param int $contact_id Contact ID - when updates are done by entities outside the system, frontent accounts or via clicking something in an e-mail)
     */
    public function history_Create($issue_id, $update_data, $update_type = null, $comment = null, $contact_id = null)
    {
        if(empty($update_data))
            return false; // There must be -some- data otherwise this function refused to enter an empty entry

        // Check if $update_type has a valid value
        if(is_string($update_type) && !in_array($update_type, self::$accepted_update_types))
            return MVC_Core::$debug-> dump("Invalid record history type: {$update_type}", __METHOD__, 'warning');

        $update_entry = array(
            'issue_id'=> $issue_id,
            'dt'=> time(),
            'update_by'=> isset(MVC_Core::$ss-> user)? MVC_Core::$ss-> user['id']: 0, // 0 = not from an user (possibly frontend update, $con_id might contain a value)
            'update_type'=> $update_type,
            'update_data'=> json_encode($update_data)
        );

        if(is_string($comment))
            $update_entry['comment'] = $comment;

        if(!is_null($contact_id))
            $update_entry['contact_id'] = $contact_id;

        $q=QueryFactory::insert()
            -> table(MVC_Core::$db-> tab['isu'])
            -> values($update_entry);

        MVC_Core::$db-> query($q);
        return MVC_Core::$db-> insert_id;
    }

    /**
     * Updates the comment for an issue history item
     * @param int $issue_id Updates the comment for the last history entry, and only if it's from the same user that is logged in.
     * -- If another change is added, previous entries cannot be modified.
     * @param string $comment
     */
    public function history_Update_Last_Comment($issue_id, $comment)
    {

    }

    /**
     * Reads the issue update history, searching by issue_id or by update_by (user). Always orders by date, descending ( newest updates first )
     * @param int|string $locator Record locator ( numeric or trackID )
     * @param bool $wtr What to read
     * TODO: Allow filtering by organization, so only changes done by a certain organization are returned
     */
    public function history_GetList($locator, $wtr = array('history', 'user'))
    {
        $this-> read($locator, array('typeex'));
        if(empty($this-> issue))
            return MVC_Core::$debug-> dump("Record could not be located", __METHOD__, 'warning');

        $wtr = $this-> wtr_Helper($wtr);

        $q=QueryFactory::select()
            // Issue organization data and information about the organization itself
            -> table(MVC_Core::$db-> table('isu'))
            -> where("`isu`.issue_id = ?", $this-> issue['id'])
            -> order("`isu`.dt DESC");

        // Join the user information
        if(in_array('user', $wtr)) {
            $q-> table(MVC_Core::$db-> table('usr'), QueryFactory::autoalias_Add_Prefix("id,org_id,cdep_id,name,email,contact_id", '_usr_'), 'left', '`usr`.id = `isu`.update_by');
            // Join the user's organization as well
            if(in_array('organization', $wtr))
                $q-> table(MVC_Core::$db-> table('org'), QueryFactory::autoalias_Add_Prefix("id,type_id,name,contact_id", '_org_'), 'left', '`org`.id = `usr`.org_id');
        }

        // Run the query and get the results
        $r = MVC_Core::$db-> query($q);
        
        if(!$r)
            return MVC_Core::$debug-> dump("Could not read record history", __METHOD__, 'warning');

        $r-> split_by_prefixes = array(
            // extra tables
            'user'=> "_usr_",
            'org'=> "_org_"
        );
        $updates = array();
        foreach($r as $up) {
            $up['dt'] = date("Y-m-d H:i:s", $up['dt']);
            $data = json_decode($up['update_data'], true);

            // New format, which has the "update_type" marker - When this is detected, the rest of the checks are skipped
            if(!empty($up['update_type'])) {
                switch($up['update_type']) {
                    case 'create':	// Record was just created - History entry contains initial creator user, contact information or system identifier
                        $up['op'] = "Record created";
                        $up['op_info'] = sprintf("IP address: %s", long2ip($data['ip']));
                        break;
                    case 'group':	// Data group was altered
                        break;
                    case 'msgsent':	// Record-connected message was sent
                        break;
                    case 'msgrecv':	// Record-connected message received (Not implemented yet)
                        break;
                    case 'xfd':		// Custom Fields altered
                        if(isset($data['_panel'])) {
                            if (!is_null($up['comment'])) {
                                $up['op'] = "Fields updated";
                                $up['op_info'] = $up['comment'];
                            } else {
                                $up['op'] = "Panel edited";
                                if(!empty($data['_xfd_c']) && $data['_xfd_c'] > 0)
                                    $up['op_info'] = sprintf("%d field%s altered in panel [<b>%s</b>]", $data['_xfd_c'], $data['_xfd_c']>1? "s were": " was", $data['_panel']);
                                else
                                    $up['op_info'] = sprintf("Contents of the fields in panel [<b>%s</b>] has not changed", $data['_panel']);
                            }
                        } else {
                            $up['op'] = "Fields update";

                            if(!empty($data['_xfd_c']) && $data['_xfd_c'] > 0)
                                $up['op_info'] = sprintf("%d field%s changed by an automated process", $data['_xfd_c'], $data['_xfd_c']>1?"s":"");
                            else
                                $up['op_info'] = "A data update operation was triggered but no changes were detected";
                        }
                        break;
                    case 'cache':	// Cache values altered
                        $up['op'] = "Index cache change";
                        $up['op_info'] = "Original values:";
                        if(array_key_exists('primary', $data))
                            $up['op_info'].= sprintf(" 1st: %s", empty($data['primary'])? "_Removed_": $data['primary']);
                        if(array_key_exists('secondary', $data))
                            $up['op_info'].= sprintf(" 2nd: %s", empty($data['secondary'])? "_Removed_": $data['secondary']);
                        if(array_key_exists('tertiary', $data))
                            $up['op_info'].= sprintf(" 3rd: %s", empty($data['tertiary'])? "_Removed_": $data['tertiary']);
                        break;
                    case 'sharing':	// Inter-organization sharing of the record (organization added, removed, enabled or disabled)
                        break;
                    case 'process':	// Internal Processing level altered (timers might have been set)
                        $up['op'] = "Process change";
                        switch($data['process']) {
                            case 'opened': $up['op_info'] = "Viewed by staff for the first time"; break;
                            case 'reactive': $up['op_info'] = "Reopened the previously closed record"; break;
                            case 'active': $up['op_info'] = sprintf("Record is now active (was waiting for %01.1f days)", $data['pending_length'] / 86400); break;
                            case 'shared': $up['op_info'] = "Shared between Cargohub member organizations"; break;
                            case 'closed': $up['op_info'] = sprintf("Closed the record (%01.1f days since created or received)", $data['process_length'] / 86400); break;
                        }

                        break;
                    case 'statprio':	// Status or Priority changed (might have triggered an automatic action)
                        if(isset($data['status_key'])) {
                            $up['op'] = "Status change";
                            $up['op_info'] = self::helper_Translate_StatPrio('status', $data['status_key']);
                        } elseif(isset($data['priority_key'])) {
                            $up['op'] = "Priority change";
                            $up['op_info'] = self::helper_Translate_StatPrio('priority', $data['priority_key']);
                        }
                        break;
                    case 'assgn':	// User or Department assignment, unassignment, assignment change
                        $up['op'] = "Assignment";
                        if(isset($data['assign'])) {
                            if($data['assign']['name'] == "#SELF#")
                                $up['op_info'] = sprintf("Self-assignment by %s", $up['user']['name']);
                            else
                                $up['op_info'] = sprintf("Record assigned to user: %s", $data['assign']['name']);

                        }
                        if(isset($data['unassign'])) {
                            if(isset($data['assign']))
                                $up['op_info'].= sprintf("<br />Previous assignee: %s", $data['unassign']['name']);
                            else
                                $up['op_info'] = sprintf("%s is no longer assigned", $data['unassign']['name']);
                        }

                        break;
                    case 'file':		// Attached file altered (added, edited, removed)
                        $up['op'] = "File operations";
                        if(isset($data['uploaded']))
                            $up['op_info'] = sprintf("%d file(s) uploaded", $data['uploaded']);
                        if(isset($data['deleted']))
                            $up['op_info'] = sprintf("File named %s (%d bytes) deleted", $data['name'], $data['size']);

                        break;
                    case 'task':		// Task operated (added, changed, removed, time-based actions triggered)
                        break;
                    case 'note':		// Text-content entry changed (note, documnent - added, edited, removed)
                        break;
                    case 'contact':	// Attached contact altered (connected, disconnected)
                        break;
                    case 'xfo':		// Old format xfields (lists only field keys, not very useful) - Kept for compatibility reasons
                        break;
                    case 'oth': // Other, anything - Data will be viewed in raw format
                        // 10092024 added by iwi | start
                        if(isset($data['_tr_record'])) {
                            $up['op'] = " Training updated";
                            $up['op_info'] = "The training form data has been updated.";
                        }
                        // 10092024 added by iwi | end
                }

                $updates[] = $up;
                continue;
            }

            // Construct Operation and Operation info out of the "update_data"

            if(empty($data)) {
                $up['op'] = "Errored entry";
                $up['op_info'] = "No change was recorded";
                goto history_row_processed;
            }

            // Super user info is skipped (not shown)
            if(isset($data['sui_lmid']))
                continue;

            if(isset($data['created'])) {
                $up['op'] = "Record created";
                $up['op_info'] = sprintf("IP address: %s", long2ip($data['ip']));
                goto history_row_processed;
            }

            if(isset($data['group_id'])) {
                $up['op'] = "Data-Group change";
                $m_con = new App_Model_Group($this-> issue['type']['settings']['group_type']);
                $group = $m_con-> read($data['group_id']);
                $up['op_info'] = $group['name'];
                goto history_row_processed;
            }

            if(isset($data['commtime'])) {
                $up['op'] = "A message was sent";
                $up['op_info'] = "No other information is available";
                goto history_row_processed;
            }

            if(isset($data['submit_id'])) {
                $up['op'] = "Submitter contact changed";
                // Read contact info for the new contact
                $m_con = new App_Model_Contact();
                $con = $m_con-> read($data['submit_id'], false);
                $up['op_info'] = sprintf("%s &lt;%s&gt;", $con['name'], $con['email']);
                goto history_row_processed;
            }

            if(isset($data['xf'])) {
                $up['op'] = "Fields edited";
                $up['op_info'] = sprintf("Up to %d fields changed", count($data['xf']));

                if(isset($data['cache_primary']))
                    $up['op_info'].= sprintf(" (New title: %s)", $data['cache_primary']);

                goto history_row_processed;
            }

            if(isset($data['access_key'])) {
                $up['op'] = "Access key set";
                $up['op_info'] = $data['access_key'];
                goto history_row_processed;
            }

            if(isset($data['cache_primary'])) {
                $up['op'] = "Global search index";
                $up['op_info'] = $data['cache_primary'];

                if(isset($data['cache_secondary']))
                    $up['op_info'].= sprintf(" (Secondary: %s)", $data['cache_secondary']);
                goto history_row_processed;
            }

            // TODO - Translate status/prio?
            if(isset($data['status_key'])) {
                $up['op'] = "Status changed";
                $up['op_info'] = self::helper_Translate_StatPrio('status', $data['status_key']);
                goto history_row_processed;
            }
            if(isset($data['priority_key'])) {
                $up['op'] = "Priority changed";
                $up['op_info'] = self::helper_Translate_StatPrio('priority', $data['priority_key']);
                goto history_row_processed;
            }

            if(isset($data['shared_org_ids'])) {
                $up['op'] = "Organization(s) connected";
                $up['op_info'] = sprintf("%d organizations are now connected", count(explode(',', $data['shared_org_ids'])));
                goto history_row_processed;
            }

            if(isset($data['count_msgs'])) {
                $up['op'] = "Message count changed";
                $up['op_info'] = substr($data['count_msgs'], -2);
                goto history_row_processed;
            }

            $up['op'] = "Unknown";
            $up['op_info'] = "Unrecognized history entry format";

            history_row_processed:
            $updates[] = $up;
        }

        return $updates;
    }

    /**
     * Removes all history entries for an issue_id . Used only when removing issues
     * @param int $issue_id
     */
    public function history_Clear($issue_id)
    {

    }

//----- User markers/indicators -----//

    protected static $marker_fields = array('access_ut', 'export_ut');

    /**
     * Creates or updates a record user marker (Insert-> Update on Insert fail)
     * - Keeps existing data, only updates what's present in $data
     * - Note: This method doesn't validate if rec_id or user_id actually exists
     *
     * @param int $rec_id
     * @param int $user_id
     * @param array $marker_data [access_ut, export_ut]
     * @param bool $attempt_insert_first If set to false, it disables the insert attempt and goes straight to update
     * -- used when it is known that a marker record exists, or a failed update not relevant
     */
    public function marker_Set($rec_id, $user_id, $marker_data = array(), $attempt_insert_first = true)
    {
        if(empty($rec_id))
            return MVC_Core::$debug-> dump("Record ID missing", __METHOD__, 'warning');
        if(empty($user_id))
            return MVC_Core::$debug-> dump("User ID missing", __METHOD__, 'warning');

        // Any data which is not part of the $marker_fields or is "null" is discarded automatically (to prevent updating inexistent values)
        // On the other hand, if any set to "false" (actual boolean false), the marker is nullified (removed), without removing the complete record
        foreach($marker_data as $field=> $value) {
            if(!in_array($field, self::$marker_fields) || is_null($value))
                unset($marker_data[$field]);
            elseif($value === false)
                $marker_data[$field] = null;
        }

        // There must be -some- valid data otherwise this function refused to enter an empty entry
        if(empty($marker_data))
            return MVC_Core::$debug-> dump("Marker data is empty, nothing to set", __METHOD__, 'warning');

        if($attempt_insert_first)
            try {
                // Attempt to insert the marker data; If fails (blocked by PRIMARY), it means it already exists, so proceed to update instead
                $insert_data = $marker_data;
                $insert_data['rec_id'] = (int)$rec_id;
                $insert_data['user_id'] = (int)$user_id;

                $qi=QueryFactory::insert()
                    -> table(MVC_Core::$db-> tab['rmk'])
                    -> values($insert_data);

                MVC_Core::$db-> query($qi);
                return 1; // one inserted
            } catch(Exception $e) {
                if($e-> getCode() != 1062) // Symbol: ER_DUP_ENTRY; SQLSTATE: 23000 - https://dev.mysql.com/doc/refman/8.0/en/server-error-reference.html#error_er_dup_entry
                    return MVC_Core::$debug-> dump(sprintf("Database error %d: %s", $e-> getCode(), $e-> getMessage()), __METHOD__, 'error'); // Some other error, not the duplicate entry

                // Insert failed, try update
                MVC_Core::$db-> query(
                    QueryFactory::update()
                        -> table(MVC_Core::$db-> tab['rmk'])
                        -> values($marker_data)
                        -> where("`rec_id` = ? AND `user_id` = ?", $rec_id, $user_id)
                );
                return MVC_Core::$db-> affected_rows; // 0 or 1, 0 if nothing was actually changed in the marker
            }
        else {
            // Straight to Update
            MVC_Core::$db-> query(
                QueryFactory::update()
                    -> table(MVC_Core::$db-> tab['rmk'])
                    -> values($marker_data)
                    -> where("`rec_id` = ? AND `user_id` = ?", $rec_id, $user_id)
            );
            return MVC_Core::$db-> affected_rows; // 0 or 1, 0 if nothing was actually changed in the marker
        }
    }

    /**
     * TODO: Reads the marker for a specific record and user (no other data is joined)
     * - Normally markers are used for filtering, or are accessed by the record reading table
     *
     * @param int $rec_id
     * @param int $user_id
     * @param array Returns the other columns (currently only access_ut and export_ut); If marker not found, it returns false
     */
    public function marker_Get($rec_id, $user_id)
    {
    }

    /**
     * Updates all existing markers for one record at once.
     * -- Ex: to set new dates or values for all users that have interacted with the record
     * -- Ex: to nullify markers for all users (like export_ut, when record itself is updated)
     *
     * @param int $rec_id This method doesn't check if the record ID actually exists, is only attempts an update operation
     * @param array $marker_data
     * @return int Number of affected markers
     */
    public function marker_UpdateAll($rec_id, $marker_data = array())
    {
        if(empty($rec_id))
            return MVC_Core::$debug-> dump("Record ID missing", __METHOD__, 'warning');

        // Any data which is not part of the $marker_fields or is "null" is discarded automatically (to prevent updating inexistent values)
        // On the other hand, if any set to "false" (actual boolean false), the marker is nullified (removed), without removing the complete record
        foreach($marker_data as $field=> $value) {
            if(!in_array($field, self::$marker_fields) || is_null($value))
                unset($marker_data[$field]);
            elseif($value === false)
                $marker_data[$field] = null;
        }

        if(empty($marker_data))
            return MVC_Core::$debug-> dump("Marker data is empty, nothing to update", __METHOD__, 'notice');

        MVC_Core::$db-> query(
            QueryFactory::update()
                -> table(MVC_Core::$db-> tab['rmk'])
                -> values($marker_data)
                -> where("`rec_id` = ?", $rec_id)
        );
        return MVC_Core::$db-> affected_rows;
    }

    /**
     * TODO: Reads either all users involved with a record, or all records that a user interacted with by accessing the markers list
     * - joins basic user data or record data
     * - One of $rec_id or $user_id must be null, the other must be an Int. If both are null or both have values, this function returns false
     * TODO: Use this instead of the existing implementation of "Recent records"
     * TODO: Implement an UI for listing which records have been accessed, grouped on a daily basis (timeline)
     *
     * @param int $rec_id
     * @param int $user_id
     * @param array $wtr What to read alongside the markers (could be user data, record data, organization data...)
     * @return bool False if incorrect parameters; Empty array if no markers found
     */
    public function marker_Get_List($rec_id = null, $user_id = null, $wtr = array())
    {
        // TODO: The Get List function (later, when adding the markers to the UI)
    }

    /**
     * Deletes a marker for a specific record-user combination, or all markers for a record (for example, when the record is deleted)
     */
    public function marker_Delete($rec_id, $user_id = null)
    {
        // TODO: The delete function
    }

//----- Commonly Used Tools -----//

    /**
     * Generates a string from an issue data and a template. The full record data can be read or parts of it, however for template purposes it reads most of the metadata
     * -- This method is mainly used by the messaging functions related to issues.
     * -- Also used for generating print output
     *
     * @param int|string $locator
     * @param array $parts Contains information on what parts of the issue data will be present in the message. Can have keys: "list", "attachments", "contacts"
     * @param int $org_id Organization ID necessary to read/render the record. If missing, it uses the current Master of the record.
     * @param string $template If not empty, this string is used as the template (instead of the default one read from /view/issues/{rt_type}/_render.html)
     * @param array $tpl_data The assambled template data. Values can be set here before the function is called, and reading the issue adds issue data (and more) to this reference
     * @param array $includes_list If an array, it uses includes in the render template, as specified
     * @param bool $return_att When attachemnts exist and this is "true", it returns the attachments to this reference, so they can be used outside | @deprecated - Record linked files should not be used with _render anymore !
     * @param int $rt_or RT Override - Use another version of the record type for rendering (some fields will be different, and another global template)
     */
    public function render($locator, $parts, $org_id = null, $template = null, &$tpl_data = array(), $includes_list = null, &$return_att = false, $rt_or = null)
    {
        if(empty($tpl_data))
            $tpl_data = array();

        $wtr = array('issue', 'organization', 'orgtype', 'orgcontact', 'assigned', 'group', 'xfields', 'submitter', 'submitter_xdata');
        // Which parts of the issue are being used?
        if(!empty($parts)) {
            $parts_list = array();
            foreach($parts['list'] as $part_key)
                $parts_list[$part_key] = true;

            if(isset($parts_list['attachments']))
                $wtr[] = 'files';
            if(isset($parts_list['contacts'])) {
                $wtr[] = 'contacts';
                $wtr[] = 'contacts.xdata';
            }

            $tpl_data['parts'] = $parts_list;
        }

        // Read the issue for which the message is built
        // Hybrid mode:
        // - "Global" template is using RAW values from xfields
        // - "Template" mode is using SERIALIZED values, because that's how the template editor works
        // This should be permanently resolved by having the serialized values used everywhere where a text is generated ( message generation, any templates )
        // On the other hand, it limits flexibility ( like not allowing MAWB to have a different color prefix... ). Will have to think about this.
        $settings = array('htmlsc'=> true, 'ignore_xf_hidden'=> true);
        if(is_string($template))
            $settings['serialize'] = true;
        if(!is_null($rt_or))
            $settings['ist_or'] = $rt_or;

        $this-> cache_Reset(array('issue'));
        $this-> read($locator, $wtr, $org_id, null, null, $settings);
        if(empty($this-> issue))
            return
                "<h2><span style='color: red;'>Error:</span> Record unreadable. Unable to build the view</h2>".
                "<p>The record might have been deleted or a database error has occurred</p>";

        if(!empty($parts_list)) {
            // Filter the contacts which will be shown in the message
            if(isset($parts_list['contacts']))
                foreach($this-> issue['contact_links'] as $id=> $clink) {
                    if(!in_array($id, $parts['contacts']))
                        unset($this-> issue['contact_links'][$id]);
                }

            // Filter out files depending on the selector
            if(isset($parts_list['attachments']))
                foreach($this-> issue['files'] as $id=> $file) {
                    if(!in_array($id, $parts['attachments']))
                        unset($this-> issue['files'][$id]);
                    if($return_att)
                        $return_att = $this-> issue['files'];
                }
        }

        // Global information (related to where the software runs)
        if(!isset($tpl_data['html'])) {
            $tpl_data['html'] = MVC_Core::$ss-> domain_CFG['html_settings']; // The domain HTML settings
            $tpl_data['html']['domain'] = MVC_Core::$ss-> domain_CFG['domain'];
        }

        // Logged in User
        if(!isset($tpl_data['user'])) {
            $tpl_data['user'] = MVC_Core::$ss-> user;
            $tpl_data['user']['department'] = MVC_Core::$ss-> department['name'];
            // Remove unnecessary data
            unset($tpl_data['user']['is_admin'], $tpl_data['user']['status_ex'], $tpl_data['user']['preferences']);
        }

        // Record information and linked data
        $tpl_data['issue'] = $this-> issue;
        if(isset($tpl_data['issue']['organization'])) {
            $tpl_data['organization'] = $this-> issue['organization'];
            unset($tpl_data['issue']['organization']);
        }

        $tpl_data['ist'] = &$this-> ist_def;

        // Issue Type Definition contains the default template to use for this, and might also include template locations
        if(is_string($template)) {
            $drytpl = new Drytemplate(new stdClass());
            return trim($drytpl-> render_String($template, $tpl_data));
        } elseif(is_array($template)) {
            // File names (from the issue type)
            $CFG = new stdClass();
            $CFG-> paths = array('templates'=> array(
                ROOT. DS. 'app'. DS. 'view'. DS. 'issues'. DS. $this-> ist_def['key']
            ));
            $drytpl = new Drytemplate($CFG); $s='';
            foreach($template as $template_file)
                $s.= $drytpl-> render_File($template_file, $tpl_data)."\n";
            return trim($s);

        } elseif(isset($this-> ist_def['templates']['render'])) {
            $template_file = $this-> ist_def['templates']['render'];

            if(is_array($includes_list) && isset($this-> ist_def['templates']['_includes'])) {
                $tpl_data['_includes'] = array();
                foreach($includes_list as $include)
                    if(isset($this-> ist_def['templates']['_includes'][$include]))
                        $tpl_data['_includes'][] = $this-> ist_def['templates']['_includes'][$include];
            }

            $CFG = new stdClass();
            $CFG-> paths = array('templates'=> array(
                ROOT. DS. 'app'. DS. 'view'. DS. 'issues'. DS. $this-> ist_def['key']
            ));
            $drytpl = new Drytemplate($CFG);
            return trim($drytpl-> render_File($template_file, $tpl_data));
        } else
            return "No template was found or is set up. Cannot render the record !";
    }
    function get_rec_list(){

        $q = QueryFactory::select()->table(array(
            MVC_Core::$db->tab['iss'],
            'iss'
        ), QueryFactory::autoalias_Add_Prefix("track,edtime", 'iss'))
            ->table(array(
                MVC_Core::$db->tab['ist'],
                'ist'
            ),QueryFactory::autoalias_Add_Prefix("name,key", 'ist'), 'left', "`iss`.`type_id` = `ist`.`id`")->group("type_id");
        $r = MVC_Core::$db-> query($q);
        //echo json_encode($r->num_rows);
        $all = array();
        $cnt=$r->num_rows;
        foreach($r as $issue) {

            $issue['isstrack'] = $issue['isstrack'];
            $issue['issedtime'] = $issue['issedtime'];
            $issue['istname'] = $issue['istname'];
            $issue['istkey'] = $issue['istkey'];
            //$issue['istrows'] = $r->num_rows;
            $details[] = $issue;

            //$cnt++;
        }
        $all[]=array('istrows'=>$cnt);

        $all['details']=$details;

        return $all;
    }


    /**
     * @param $oldData
     * @param $newData
     *
     * @return array
     */
    public function formFieldsSorter($oldData, $newData)
    {
        $response = array();
        //set of fields which needs to be checked if updated
        $formFields = array("inex" => "The type of audit",
            "pstage" => "The claimtype of cargo"
        );
        foreach ($formFields as $key => $val) {
            //Special condition for claim type update
            if ($key == "pstage") {
                if ($newData['claim_amt']['amount'] != $oldData['claim_usd']['amount']) {
                    // print_r($newData['claim_amt']['amount']);
                    if ($oldData['claim_usd']['amount'] > 0 && $newData['claim_amt']['amount'] > 0) {
                        //Do nothing
                    } else if($oldData['claim_usd']['amount'] > 0 && !isset($newData['claim_amt']['amount'])){
                        //Do nothing
                    }else {
                        $response['pstage'] = $val;
                    }
                }
            }
            else {
                if ($this->_array_Key_Filter($this->_array_Sorter_Form($oldData, $newData), $key))
                    $response[$key] = $val;
            }
        }
        return $response;
    }

    /**
     * array pusher generic and filter the changed value
     */
    /**
     * @param $updatedData
     * @param $oldData
     *
     * @return array
     */
    private function _array_Sorter_Form($updatedData, $oldData)
    {
        $result = array();
        foreach ($updatedData as $key => $val) {
            if (is_array($val)) {
                $result[$key] = $val['value'];
            } else {

                $result[$key] = $val;
            }
        }
        return array_filter(array_diff($oldData, $result));
    }

    /*
     * filter array as per key given
     */
    /**
     * @param $arr
     * @param $key
     *
     * @return bool
     */
    private function _array_Key_Filter($arr, $key)
    {
        if (array_key_exists($key, $arr))
            return true;
        else
            return false;

    }

    private static $airlines_table = "data_airlines";

    public function _match_awb_org($data,$user_id){
        $output=array();
        // 1. Test if the prefix is a valid airline
        $q=QueryFactory::select()
            -> table(self::$airlines_table)
            -> where("`AWB_prefix` = ?", $data->p)
            -> limit(1);

        $r = MVC_Core::$db-> query($q);
        if(!$r || !empty($r) ) {
            $output['mawb_outcome']= 1;
            $output['mawb']=$data->p.'-'.$data->s;
            $output['message']= "MAWB prefix {$data->p} is not a valid airline ID assigned by IATA";
        }
        if(!empty($user_id)){
            $org_data = MVC_Core::$db->query('select org_id from users where id = '.$user_id);
            if($q){
                foreach ($org_data as $row){
                    $org_id = $row['org_id'];
                }
            }

            if(isset($data->fk) && !empty($data->fk)){
                $fk=$data->fk;
            }else{
                $fk=3;
            }
            if(!empty($org_id)){
                $r2 = MVC_Core::$db->query("select t.issue_id,u.org_id,t.fk_id,t.value  from issue_fields t ,issue_orgdata u where
                t.issue_id = u.issue_id and u.org_id = ".$org_id." and t.fk_id = ".$fk." and t.value='".$data->p."-".$data->s."'");
                if(!$r2 || count($r2) == 0){
                    $output['mawb_outcome']= 0;
                    $output['mawb']=$data->p.'-'.$data->s;
                    $output['message']= "MAWB is available";
                }else{
                    $output['mawb_outcome']= 1;
                    $output['mawb']=$data->p.'-'.$data->s;
                    $output['message']= "MAWB is already used, Do you wish to continue?";
                }
            }
        }
        return $output;
    }

    public function trackExistOrNot($track){
        $q=QueryFactory::select()
            -> table(MVC_Core::$db-> tab['iss'])
            -> where("`track` = ?", $track);
        return  MVC_Core::$db-> query($q)-> current();
    }


    public function child_there_or_not($id)
    {
        //to get has_Child details
        $xfDefRes =$this->get_XFDef_Value("has_Child", 34);
        $hasChild_Id = $xfDefRes['id'];

        return $q = MVC_Core::$db->query("select if2.issue_id, if2.fk_id, if2.int_value, if2.value from issues i join issue_fields if2 on if2.issue_id = i.id where if2.fk_id = $hasChild_Id and if2.issue_id =" . $id)->current();
    }
    public function delete_Audit_Record($id){
        //to get child_Of details
        $xfDefRes =$this->get_XFDef_Value("child_Of", 34);
        $childOf_Id = $xfDefRes['id'];

        $q = MVC_Core::$db->query("select if2.* from issues i join issue_fields if2 on if2.issue_id = i.id where (if2.fk_id = $childOf_Id and int_value=" . $id . ")");
        return $q;
    }

    public function getChildCount($recordId){
        //to get child_Of details
        $xfDefRes =$this->get_XFDef_Value("child_Of", 34);
        $childOf_Id = $xfDefRes['id'];
        $q=QueryFactory::select()
            -> table(MVC_Core::$db-> tab['ixf'])
            -> where("`fk_id` = ?", $childOf_Id)
            -> where("`int_value` = ?", $recordId);
        return  MVC_Core::$db-> query($q)-> num_rows;
    }

    public function child_Or_Parent($recordId)
    {
        // to get rec_Pos details
        $xfDefRes = $this->get_XFDef_Value("rec_Pos", 34);
        $recPos_Id = $xfDefRes['id'];

        $q = QueryFactory::select()->table(MVC_Core::$db->tab['ixf'])
            ->where("`fk_id` = ?", $recPos_Id)
            ->where("`issue_id` = ?", $recordId);

        $rec = MVC_Core::$db->query($q)->current(); // -> num_rows;
        return $rec['value']; // -> num_rows;
    }
    /*public function deleteInf_From_Childs($child_Record_id, $parent_Record_id)
    {
        MVC_Core::$db->query("delete from issue_contacts  where issue_id = " . $child_Record_id);

        MVC_Core::$db->query("delete from issue_fields  where issue_id = " . $child_Record_id);

        MVC_Core::$db->query("delete from issues where id = " . $child_Record_id);

        MVC_Core::$db->query("delete from issue_orgdata where issue_id = " . $child_Record_id);

		//to get has_Child details
        $xfDefRes =$this->get_XFDef_Value("has_Child", 34);
        $hasChild_Id = $xfDefRes['id'];

        $q = MVC_Core::$db->query("update issue_fields set value ='n' where fk_id = $hasChild_Id and issue_id = " . $parent_Record_id);
    }

    public function removeStartAndEndDate($record_Id){
        MVC_Core::$db->query("delete from issue_fields where issue_id=".$record_Id." and fk_id in (1592,1593)");
    }

	public function get_XFDef_Value($name,$recordType){
        $xfDef = QueryFactory::select()->table(MVC_Core::$db->tab['xfd'])
        ->where("`fk` = ?", $name)
        ->where("`ist_id`=", $recordType);
        return MVC_Core::$db->query($xfDef)->current();
    }

	public function get_XFDef_Value_Array($recordType){
		$q = QueryFactory::select()->table(MVC_Core::$db->tab['xfd'])
        ->where("`ist_id`=", $recordType);
		return  MVC_Core::$db->query($q);
	}  */
    public function deleteInf_From_Childs($child_Record_id, $parent_Record_id)
    {
        $issUpdates = QueryFactory::select()->table(MVC_Core::$db->tab['isu'])->where("`issue_id`=", $child_Record_id);
        $resUpd = MVC_Core::$db->query($issUpdates);
        $updateResCount = count($resUpd);

        $mesQue = QueryFactory::select()->table(MVC_Core::$db->tab['cnv'])->where("`link_id`=", $child_Record_id)->where("`type`=","issue");
        $resMes = MVC_Core::$db->query($mesQue);
        $mesQueRes = count($resMes);

        $fileQue = QueryFactory::select()->table(MVC_Core::$db->tab['fil'])->where("`linked_id`=", $child_Record_id)->where("`linked_with`=","issue");
        $resQue = MVC_Core::$db->query($fileQue);
        $fileQueRes = count($resQue);

        // notes
        $notesQue = QueryFactory::select()->table(MVC_Core::$db->tab['nte'])->where("`link_id`=", $child_Record_id);
        $resNot = MVC_Core::$db->query($notesQue);
        $noteQueRes = count($resNot);

        $taskQue = QueryFactory::select()->table(MVC_Core::$db->tab['tsk'])->where("`link_id`=", $child_Record_id)->where("`link_type`=","record");
        $resTask = MVC_Core::$db->query($taskQue);
        $resTaskRes = count($resTask);


        if ($updateResCount <= 0 && $mesQueRes <= 0 && $fileQueRes <= 0 && $noteQueRes <= 0 && $resTaskRes <= 0) {

            // c2i issue_contacts
            $q1 = QueryFactory::delete()->table(MVC_Core::$db->tab['c2i'])->where("`issue_id` = ?", $child_Record_id);
            MVC_Core::$db->query($q1);

            // ixf issue_contacts

            $q2 = QueryFactory::delete()->table(MVC_Core::$db->tab['ixf'])->where("`issue_id` = ?", $child_Record_id);
            MVC_Core::$db->query($q2);

            // iss issues
            $q3 = QueryFactory::delete()->table(MVC_Core::$db->tab['iss'])->where("`id` = ?", $child_Record_id);
            MVC_Core::$db->query($q3);

            // iod issue_orgdata
            $q4 = QueryFactory::delete()->table(MVC_Core::$db->tab['iod'])->where("`issue_id` = ?", $child_Record_id);
            MVC_Core::$db->query($q4);
            return 1;
        } else {
            return 0;
        }
    }

    public function removeStartAndEndDate_AND_PARENT($record_Id)
    {
        $xfDefRes = $this->get_XFDef_Value("date_start", 34);
        $dateStart = $xfDefRes['id'];

        $xfDefRes = $this->get_XFDef_Value("date_end", 34);
        $dateEnd = $xfDefRes['id'];

        MVC_Core::$db->query("delete from issue_fields where issue_id=" . $record_Id . " and fk_id in (" . $dateStart . "," . $dateEnd . ")");

        // to get has_Child details
        $xfDefRes = $this->get_XFDef_Value("has_Child", 34);
        $hasChild_Id = $xfDefRes['id'];

        $q = MVC_Core::$db->query("update issue_fields set value ='n' where fk_id = $hasChild_Id and issue_id = " . $record_Id);
    }

    public function get_XFDef_Value($name, $recordType)
    {
        $xfDef = QueryFactory::select()->table(MVC_Core::$db->tab['xfd'])
            ->where("`fk` = ?", $name)
            ->where("`ist_id`=", $recordType);
        return MVC_Core::$db->query($xfDef)->current();
    }

    public function get_XFDef_Value_Array($recordType)
    {
        $q = QueryFactory::select()->table(MVC_Core::$db->tab['xfd'])->where("`ist_id`=", $recordType);
        return MVC_Core::$db->query($q);
    }
	
	public function updateProcessingDate($recTypeDetails,$recId,$fields_update){
		$operation = "";
		if($recTypeDetails['key']=='cargoclaim' && $recId  !=""){
			$xfId = $recTypeDetails['xfield_def']['acdate']['id'];
			$xfFields = $recTypeDetails['xfield_def']['acdate'];
			$r = $this->getXfDetails_BasedOnXf($recId,$xfId);
			if($r==""){
				$operation = "insert";
				$this->update_Xfiled_Value($recId,$operation,$xfFields);
			}else{
				$operation = "update";
				$this->update_Xfiled_Value($recId,$operation,$xfFields,$r);
			}
		}
	}
	
	public function getXfDetails_BasedOnXf($recId,$xfId){
		$q = QueryFactory::select()->table(MVC_Core::$db->tab['ixf'])->where("`issue_id`=?", $recId)->where("`fk_id`=?",$xfId);
		$r =  MVC_Core::$db->query($q)->current();
		return $r;
	}
	
		//To update any field in the ixfields table it iwll help us
	//it can accept two arguments which are issue id and issue module type details.
	public function update_A_Field($recId,$recordTypeDetails,$fieldName,$recordType){
		 $operation = "";
		 $moduleTypeId = $recordTypeDetails['type_id'];
		 $recordTypeFromDb =$recordTypeDetails['key'];
		 if($recId  !=""&& $recordTypeFromDb == $recordType){	 
			 //doss_close_date
			 $xfDefRes = $this->get_XFDef_Value($fieldName, $moduleTypeId);
			 $field_Id = $xfDefRes['id'];
			 $r = $this->getXfDetails_BasedOnXf($recId,$field_Id);
			 $xfFields['id']=$field_Id;			
			 if($r == ""){
				$this->update_Xfiled_Value($recId,$operation,$xfFields);
					$insert_data['fk_id'] = $field_Id;
					$insert_data['issue_id'] = $recId;
					$insert_data['int_value']=time();
					$qi=QueryFactory::insert()
						-> table(MVC_Core::$db-> tab['ixf'])-> values($insert_data);			
					MVC_Core::$db-> query($qi);	
			}
		}
	}
	
	public function update_Xfiled_Value($recId,$operation,$xfFields,$existedRec=array()){
		$ixf_id = $xfFields['id'];
		if($operation == "insert"){
			$insert_data['fk_id'] = $ixf_id;
            $insert_data['issue_id'] = $recId;
			$insert_data['int_value']=time();
			$qi=QueryFactory::insert()
				-> table(MVC_Core::$db-> tab['ixf'])-> values($insert_data);			
            MVC_Core::$db-> query($qi);
			
			$this->update_OrgData($recId,$fieldName = 'status_key');
		}
		/*if($operation == "update"){
			$existedRec['int_value'] = time();
			$qi = QueryFactory::update()
               -> table(MVC_Core::$db-> tab['ixf'])-> values($existedRec)-> where("`issue_id`=?", $recId)->where("`fk_id`=?",$ixf_id);
			MVC_Core::$db-> query($qi);
		}*/
	}
	
	public function update_OrgData($recId,$fieldName){
		$existedRec[$fieldName] = "inpr";
		$qi = QueryFactory::update()
               -> table(MVC_Core::$db-> tab['iod'])-> values($existedRec)-> where("`issue_id`=?", $recId);
			MVC_Core::$db-> query($qi);		
	}	
	public function get_RecordType_Details($recId){

       $q = QueryFactory::select()->table(MVC_Core::$db->table('iss'), array('type_id'))
		-> table(MVC_Core::$db-> table('ist'), "key", 'left', "`ist`.id = `iss`.type_id")
		-> where("`iss`.id = ?", $recId);
		$r =  MVC_Core::$db->query($q)->current();
		return $r;
	}
	
	public function templateExistsOrnot($orgId){
		$templateTitle = "test notification title-1 {issue.xfields.pstage}";
		
		$q = QueryFactory::select()->table(MVC_Core::$db->tab['tpl'])->where("`org_id`=?", $orgId)->where("`title`=?",$templateTitle);
		$r =  MVC_Core::$db->query($q)->current();
		return $r;
	}


    // 13062024 CREATED BY IWI | START
    public function get_ttl_doc_link_by_license_monitor($org_id,$user_id,$lm_issue_id=0)
    {
       return $q = MVC_Core::$db->query("SELECT count(*) as total FROM `license_monitor_management` WHERE `org_id` = '$org_id' AND `user_id` = '$user_id' AND `license_monitor_issue_id` = '$lm_issue_id'")->current();
    }

    public function license_monitor_doc_link_value_update($issue_id,$ttl_doc_link)
    {   
        $q = MVC_Core::$db->query("SELECT issue_id,fk_id,value FROM `issue_fields` WHERE `issue_id` = '$issue_id' AND `fk_id` = '2422'")->current();
        if(empty($q)){
            $insert_data['fk_id'] = 2422;
            $insert_data['issue_id'] = $issue_id;
            $insert_data['value'] = $ttl_doc_link;
            $qi=QueryFactory::insert()
                -> table(MVC_Core::$db-> tab['ixf'])-> values($insert_data);            
            MVC_Core::$db-> query($qi);
        }else{
            $update_data['value'] = $ttl_doc_link;
            $qi = QueryFactory::update()
               -> table(MVC_Core::$db-> tab['ixf'])-> values($update_data)-> where("`issue_id`=?", $issue_id)->where("`fk_id`=?",2422);
            MVC_Core::$db-> query($qi);
        }
    }
    // 13062024 CREATED BY IWI | END

    // 19062024 CREATED BY IWI | START
    public function get_all_document_by_license_monitor($lm_issue_id=0)
    {
        $user_id = MVC_Core::$ss-> user['id'];
        $org_id = MVC_Core::$ss-> user['org_id'];
        $q = MVC_Core::$db->query("SELECT document_link FROM `license_monitor_management` WHERE `org_id` = '$org_id' AND `user_id` = '$user_id' AND `license_monitor_issue_id` = '$lm_issue_id'");
        $html = '';
        foreach($q as $row){
           $html .= '<option  value="/record/process:'.$row['document_link'].'#panels">'.$row['document_link'].'</option>'; 
        }
        return $html;

    }
    // 19062024 CREATED BY IWI | END

    // 18072024 CREATED BY IWI | START
    public function get_all_country_code()
    {

        $q = MVC_Core::$db->query("SELECT cd FROM `issue_xfdef` WHERE `id` = '2796'")->current();
        $q = json_decode($q['cd'],true);
        return $q;
    }
    // 18072024 CREATED BY IWI | END

    
//    IWI country & location bulk edit for License monitor start
    public function lmBulkUpdate($issue_id,$getUpData)
    {
        foreach ($getUpData as $key=>$rw){
            if($key=='lm_bulk_country'){
                 $q = MVC_Core::$db->query("SELECT value FROM `issue_fields` WHERE `issue_id` = '$issue_id' AND `fk_id` = '2796'")->current();
                 if(empty($q)){
                     $insert_data['fk_id'] = 2796;
            $insert_data['issue_id'] = $issue_id;
            $insert_data['value'] = $rw;
            $qi=QueryFactory::insert()
                -> table(MVC_Core::$db-> tab['ixf'])-> values($insert_data);            
            MVC_Core::$db-> query($qi);
                 }else{
                     $update_data['value'] = $rw;
            $qi = QueryFactory::update()
               -> table(MVC_Core::$db-> tab['ixf'])-> values($update_data)-> where("`issue_id`=?", $issue_id)->where("`fk_id`=?",2796);
            MVC_Core::$db-> query($qi);
                 }
            }
            if($key=='lm_bulk_location'){
                 $q = MVC_Core::$db->query("SELECT value FROM `issue_fields` WHERE `issue_id` = '$issue_id' AND `fk_id` = '2795'")->current();
                 if(empty($q)){
                     $insert_data['fk_id'] = 2795;
            $insert_data['issue_id'] = $issue_id;
            $insert_data['value'] = $rw;
            $qi=QueryFactory::insert()
                -> table(MVC_Core::$db-> tab['ixf'])-> values($insert_data);            
            MVC_Core::$db-> query($qi);
                 }else{
                     $update_data['value'] = $rw;
            $qi = QueryFactory::update()
               -> table(MVC_Core::$db-> tab['ixf'])-> values($update_data)-> where("`issue_id`=?", $issue_id)->where("`fk_id`=?",2795);
            MVC_Core::$db-> query($qi);
                 }
            }
            
            
        }
        return true;

    }
//    IWI country & location bulk edit for License monitor end


    public function get_ttl_training_record_by_training_scheduler($org_id,$record_id)
    {
        return $q = MVC_Core::$db->query("SELECT count(*) as total FROM trainee_management 
        LEFT JOIN trainee_management_maping_user ON trainee_management.id = trainee_management_maping_user.trainee_management_id 
        LEFT JOIN issues ON trainee_management_maping_user.user_issue_id = issues.id 
        WHERE trainee_management.org_id = '$org_id' AND record_id = '$record_id' ")->current();

    }
    // 22082024 CREATED BY IWI | START
    public function update_ttl_training_record_by_issue_id($issue_id,$total){
        $is_exist_no_train  = MVC_Core::$db->query("SELECT int_value FROM `issue_fields` WHERE `issue_id` = '$issue_id' AND `fk_id` = '3633'")->current();
        if(!empty($is_exist_no_train)){
            $issueFieldsUpdSql = "UPDATE `issue_fields` SET  `int_value` = '$total' WHERE `issue_id` = '$issue_id' AND `fk_id` = 3633";
        }else{
            $issueFieldsUpdSql = "INSERT INTO issue_fields (issue_id,fk_id,int_value)
            VALUES ('$issue_id',3633,'$total')";
        }
        $issueFieldsUpd = MVC_Core::$db->query($issueFieldsUpdSql);
        if(!$issueFieldsUpd)
        return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

        return $total;
    }

    public function current_ttl_training_record_by_issue_id($issue_id)
    {
        $q = MVC_Core::$db->query("SELECT int_value FROM `issue_fields` WHERE `issue_id` = '$issue_id' AND `fk_id` = '3633'")->current();
        return $q['int_value'];
    }
    // 22082024 CREATED BY IWI | END


    // 30082024 CREATED BY IWI | START
        public function update_training_record_date_by_training_scheduler($training_date,$record_id){
            $updateTrainingRecordSql = "UPDATE `trainee_management` SET  `training_date` = '$training_date' WHERE record_id = $record_id";
            $updateTrainingRecordUpd = MVC_Core::$db->query($updateTrainingRecordSql);
            if(!$updateTrainingRecordUpd)
            return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

        }

        public function update_expiry_date_by_training_scheduler($expiry_date,$record_id,$org_id,$certificate_expiry){
            $get_duplicated_trainee_query="SELECT tr_training_expiry_date,trainee_management_maping_user.id as tmmu,
            trainee_management_maping_user.user_issue_id
            FROM trainee_management 
            LEFT JOIN trainee_management_maping_user ON trainee_management.id = trainee_management_maping_user.trainee_management_id
            WHERE trainee_management.org_id = $org_id AND trainee_management.record_id = '$record_id' AND result = 1 AND is_archived = 0";
            $get_duplicated_trainee_result = MVC_Core::$db->query($get_duplicated_trainee_query);
            $get_duplicated_trainee_data = array();
            $user_issue_id = array();
            foreach($get_duplicated_trainee_result as $row) {
            $get_duplicated_trainee_data[] = $row['tmmu'];
            $user_issue_id[] = $row['user_issue_id'];
            }

            if(count($get_duplicated_trainee_data) > 0){
                // update tr_training_expiry_date date from trainee_management_maping_user table
                $updateTrainingRecordSql = "UPDATE `trainee_management_maping_user` SET  `isExpiry` = '$certificate_expiry',`tr_training_expiry_date` = '$expiry_date' WHERE id IN (".implode(',',$get_duplicated_trainee_data).")";
                $updateTrainingRecordUpd = MVC_Core::$db->query($updateTrainingRecordSql);
                if(!$updateTrainingRecordUpd)
                MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
            }


            // get track_id from issue table    


            foreach($user_issue_id as $row) {
                $get_track_id_query="SELECT track FROM issues WHERE id = $row";
                $get_track_id_result = MVC_Core::$db->query($get_track_id_query);
                $get_track_id = array();
                foreach($get_track_id_result as $_row) {
                    $get_track_id = $_row['track'];
                    $get_row_id = $row;
                
                    $this->latestTrainingRecordExpiryDateUpdate($get_row_id,$get_track_id,$org_id);
                }
            }
        }

        public function checkDuplicatedTraineeName($org_id,$track_id,$user_issue_id){

            $get_duplicated_trainee_query="SELECT trainee_management_maping_user.id as tmmu
            FROM trainee_management 
            LEFT JOIN trainee_management_maping_user ON trainee_management.id = trainee_management_maping_user.trainee_management_id
            WHERE trainee_management.org_id = $org_id AND track_id = '$track_id' AND user_issue_id ='$user_issue_id'";
            $get_duplicated_trainee_result = MVC_Core::$db->query($get_duplicated_trainee_query);
            $get_duplicated_trainee_data = array();
            foreach($get_duplicated_trainee_result as $row) {
            $get_duplicated_trainee_data[] = $row['tmmu'];
            }
            if(count($get_duplicated_trainee_data) > 0){
                // get_duplicated_trainee_data update to archived
                $updateTrainingRecordSql = "UPDATE `trainee_management_maping_user` SET  `is_archived` = 1 WHERE id IN (".implode(',',$get_duplicated_trainee_data).")";
                $updateTrainingRecordUpd = MVC_Core::$db->query($updateTrainingRecordSql);
                if(!$updateTrainingRecordUpd)
                return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
            }
        }

        public function updateTrainingRecordIsArchived($id){
            $updateTrainingRecordSql = "UPDATE `trainee_management_maping_user` SET  `is_archived` = 1 WHERE id = $id";
            $updateTrainingRecordUpd = MVC_Core::$db->query($updateTrainingRecordSql);
            if(!$updateTrainingRecordUpd)
            return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
        }


        public function latestTrainingRecordExpiryDateUpdate($issue_id,$track_id,$org_id){
            $query="SELECT Case  WHEN tr_training_name = '' OR tr_training_name IS NULL THEN fie_3.value ELSE tr_training_name END as `training_name_val`,
            tmmu.tr_training_expiry_date,tmmu.id
            FROM `trainee_management_maping_user` as tmmu 
            LEFT JOIN trainee_management as tm ON tmmu.trainee_management_id = tm.id
            LEFT JOIN `issue_fields` AS fie_2 ON `fie_2`.issue_id=tm.record_id AND (fie_2.fk_id=3635) 
            LEFT JOIN `issue_fields` AS fie_3 ON `fie_3`.issue_id=tm.record_id AND (fie_3.fk_id=3630) 
            WHERE tmmu.org_id = $org_id  AND  (tmmu.user_issue_id = '$issue_id' OR tmmu.tr_track_id = '$track_id') 
            AND is_archived = 0 HAVING tr_training_expiry_date  !='0000-00-00' ORDER BY tr_training_expiry_date ASC LIMIT 0,1";//12092024 
        //    echo $query;exit;
            $get_trainee_management_result = MVC_Core::$db->query($query);
            $get_trainee_management_data = array();
            $nearest_expiry_date = '';
            $nearest_training_name = '';
            foreach($get_trainee_management_result as $row) {
                $nearest_expiry_date = $row['tr_training_expiry_date'];
                $nearest_training_name = $row['training_name_val'];
            }

        

            $currentTrainingSchedulerExpiryDateSql = "SELECT int_value,value,fk_id  FROM `issue_fields` WHERE `issue_id` = '$issue_id' AND (`fk_id` = '2115' OR `fk_id` = '2116')";
            $currentTrainingSchedulerExpiryDate = MVC_Core::$db->query($currentTrainingSchedulerExpiryDateSql);
            $current_expiry_date = '';
            $current_training_name = '';
            $currentTrainingSchedulerExpiryDateData = array();
            foreach($currentTrainingSchedulerExpiryDate as $row) {
                if($row['fk_id'] == 2115){
                    $current_expiry_date =  $row['int_value'] != '' ?  date("Y-m-d",$row['int_value'])  : '';
                }
                if($row['fk_id'] == 2116){
                    $current_training_name = $row['value'];
                }
            }

            if($current_expiry_date ==''){
                $nearest_expiry_date = strtotime($nearest_expiry_date);
                $traineeManagementSql = "INSERT INTO issue_fields(issue_id,fk_id,int_value)
                VALUES ('$issue_id','2115','$nearest_expiry_date')";
                $traineeManagementQuery = MVC_Core::$db->query($traineeManagementSql);
                if(!$traineeManagementQuery)
                return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
                $last_trainee_exp_date_id = MVC_Core::$db->insert_id;
            }

            if($current_training_name ==''){
                $traineeManagementSql = "INSERT INTO issue_fields(issue_id,fk_id,`value`)
                VALUES ('$issue_id','2116','$nearest_training_name')";
                $traineeManagementQuery = MVC_Core::$db->query($traineeManagementSql);
                if(!$traineeManagementQuery)
                return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
                $last_trainee_name_id = MVC_Core::$db->insert_id;
            }


            if(($current_expiry_date !='' &&  $current_training_name !='' && $nearest_expiry_date !='' && $nearest_training_name !='')){
                $updateTrainingExpDateSql = "UPDATE `issue_fields` SET  `int_value` = '".strtotime($nearest_expiry_date)."' WHERE `issue_id` = '$issue_id' AND `fk_id` = '2115'";
                $updateTrainingExpDateUpd = MVC_Core::$db->query($updateTrainingExpDateSql);
                if(!$updateTrainingExpDateUpd)
                MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");


                $updateTrainingNameSql = "UPDATE `issue_fields` SET  `value` = '$nearest_training_name' WHERE `issue_id` = '$issue_id' AND `fk_id` = '2116'";
                $updateTrainingNameUpd = MVC_Core::$db->query($updateTrainingNameSql);
                if(!$updateTrainingNameUpd)
                MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
            }

            if($nearest_expiry_date =='' && $nearest_training_name =='' && $issue_id > 0){
                // delete issue fields table 
                $deleteTrainingExpDateSql = "DELETE FROM issue_fields WHERE issue_id = '$issue_id' AND (fk_id = '2115' OR fk_id = '2116')";
                $deleteTraineeManagementQuery = MVC_Core::$db->query($deleteTrainingExpDateSql);
                if(!$deleteTraineeManagementQuery)
                return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
            }

        }


        public function trainingSchedulerExpiryDateByTrackId($issue_id){
            $sql ="SELECT int_value FROM `issue_fields` WHERE `issue_id` = '$issue_id' AND `fk_id` = '3635'";
            $result = MVC_Core::$db->query($sql);
            $expiryDate = '';
            foreach($result as $row) {
                $expiryDate =  $row['int_value'] != '' ?  date("Y-m-d",$row['int_value'])  : '';
            }
            return $expiryDate;
        }
    // 30082024 CREATED BY IWI | END

    // 09092024 CREATED BY IWI | START
    public function getDefaultTrainingNameByOrgId($org_id){
        $q = QueryFactory::select()
        ->table('groups', 'id')//'name','id', 23122024 changes training record related  query
        ->table(MVC_Core::$db-> table('g2o'), array(), 'left', "`groups`.id = `g2o`.group_id ")
        ->where("groups.type = ?", 'training')
        ->where("g2o.org_id = ?", $org_id);
        $getTraineenameResult = MVC_Core::$db->query($q);
        $training_name = array();
        foreach($getTraineenameResult as $row1) {
            $training_name[]=$row1['id'];// 23122024 changes training record related  query
        }
        return $training_name;
    }
    public function getDefaultTrainingDataByOrgId($org_id){
            $q = QueryFactory::select()
                ->table('groups', array('name','id'))
                ->table(MVC_Core::$db-> table('g2o'), array(), 'left', "`groups`.id = `g2o`.group_id ")
                ->where("groups.type = ?", 'training')
                ->where("g2o.org_id = ?", $org_id);
        $getTraineenameResult = MVC_Core::$db->query($q);
        $training_name = array();
        foreach($getTraineenameResult as $row1) {
            $training_name[$row1['id']]=$row1['name'];
        }
        return $training_name;
    }
    
    //status change according to the training details in training record _ start
    public function trainingExpiryDateStatus($user_issue_id, $track_id,$org_id){
//       $sql = "SELECT tr_training_name,tr_training_expiry_date 
//      FROM `trainee_management_maping_user` WHERE `user_issue_id` = '$user_issue_id' or `tr_track_id` = '$track_id' 
//        ORDER BY tr_training_expiry_date ASC  LIMIT 1 ";
        $query="SELECT Case WHEN tr_training_name = '' OR tr_training_name IS NULL THEN fie_3.value ELSE tr_training_name END as `training_name_val`, tmmu.tr_training_expiry_date,tmmu.id FROM `trainee_management_maping_user` as tmmu LEFT JOIN trainee_management as tm ON tmmu.trainee_management_id = tm.id LEFT JOIN `issue_fields` AS fie_2 ON `fie_2`.issue_id=tm.record_id AND (fie_2.fk_id=3635) LEFT JOIN `issue_fields` AS fie_3 ON `fie_3`.issue_id=tm.record_id AND (fie_3.fk_id=3630) WHERE tmmu.org_id =$org_id AND (tmmu.user_issue_id = '$user_issue_id' OR tmmu.tr_track_id = '$track_id') AND is_archived = 0 HAVING tr_training_expiry_date !='0000-00-00' AND tmmu.tr_training_expiry_date >= CURDATE() ORDER BY tr_training_expiry_date ASC LIMIT 0,1";

        $ReportResult = MVC_Core::$db->query($query)->current(); 
         return $ReportResult;
    }
    public function trainingLastExpiryDate($user_issue_id, $track_id,$org_id){
        $query="SELECT Case WHEN tr_training_name = '' OR tr_training_name IS NULL THEN fie_3.value ELSE tr_training_name END as `training_name_val`, tmmu.tr_training_expiry_date,tmmu.id FROM `trainee_management_maping_user` as tmmu LEFT JOIN trainee_management as tm ON tmmu.trainee_management_id = tm.id LEFT JOIN `issue_fields` AS fie_2 ON `fie_2`.issue_id=tm.record_id AND (fie_2.fk_id=3635) LEFT JOIN `issue_fields` AS fie_3 ON `fie_3`.issue_id=tm.record_id AND (fie_3.fk_id=3630) WHERE tmmu.org_id =$org_id AND (tmmu.user_issue_id = '$user_issue_id' OR tmmu.tr_track_id = '$track_id') AND is_archived = 0 HAVING tr_training_expiry_date !='0000-00-00'  ORDER BY tr_training_expiry_date DESC LIMIT 0,1";

        $ReportResult = MVC_Core::$db->query($query)->current(); 
         return $ReportResult;
    }
     public function trainingDateStatus($user_issue_id, $track_id){
       $sql = "SELECT tr_training_name,tr_training_date 
      FROM `trainee_management_maping_user` WHERE `user_issue_id` = '$user_issue_id' or `tr_track_id` = '$track_id' 
        ORDER BY tr_training_date ASC  LIMIT 1 ";

        $ReportResult = MVC_Core::$db->query($sql)->current(); 
         return $ReportResult;
    }


    public function getuserissueid($track_id){
         $sql="SELECT id FROM `issues` WHERE `track` LIKE '$track_id' ";
         $Result = MVC_Core::$db->query($sql)->current(); 
         return $Result;
    }
    
    
    public function getRecordType($track_id){
         $sql="SELECT type_id FROM `issues` WHERE `track` LIKE '$track_id' ";
         $Result = MVC_Core::$db->query($sql)->current(); 
         return $Result;
    }
    //status change according to the training details in training record _ end
    
    public function trainingSchedulerAddNewTraineeRecord($user_issue_id, $org_id) {

        $q = QueryFactory::select()
        // ->table('trainee_management_maping_user','tr_training_start_date,tr_training_end_date,tr_training_expiry_date,isExpiry,tr_training_name_id,isDisableTraining,trainee_management_maping_user.id AS id_val') //17122024 Training Record add checkbox by iwi
        ->table('trainee_management_maping_user',array('tr_training_start_date','tr_training_end_date','tr_training_expiry_date','isExpiry','tr_training_name','tr_training_name_id','isDisableTraining','tr_record_id_val'=>'id'))
        ->table('trainee_management','id,track_id, result, training_date, files, create_dt', 'INNER', "trainee_management_maping_user.trainee_management_id = trainee_management.id")
        ->table('files', 'filepath,analyzer,chash', 'LEFT', "files.id = trainee_management.files")
        ->table('issues', array(), 'INNER', "trainee_management.track_id = issues.track")
        ->table('issue_fields', ' value', 'INNER', "issues.id = issue_fields.issue_id  AND issue_fields.fk_id = 3630 ")
        ->table(array('issue_fields','fie_2'), "int_value", 'LEFT', "fie_2.issue_id = issue_fields.issue_id AND fie_2.fk_id = 3635 ")
        ->where("trainee_management_maping_user.user_issue_id = ?", $user_issue_id)
        ->where("trainee_management_maping_user.org_id = ?", $org_id)
        ->where("trainee_management_maping_user.is_archived = ?", '0')
            -> order("`fie_2`.int_value DESC");//17122024 Training Record add checkbox  by iwi
        // echo " 19122024 : ".$q;exit;
        $getTraineenameResult = MVC_Core::$db->query($q);
        return $getTraineenameResult;
    }

     public function getInactiveTrainingList($user_issue_id, $org_id)           //active/archieved training task 04-12-25 start
{
  $q = QueryFactory::select()
        ->table('trainee_management_maping_user',array('tr_training_start_date','tr_training_end_date','tr_training_expiry_date','isExpiry','tr_training_name','tr_training_name_id','isDisableTraining','tr_record_id_val'=>'id'))
        ->table('trainee_management','id,track_id, result, training_date, files, create_dt', 'INNER', "trainee_management_maping_user.trainee_management_id = trainee_management.id")
        ->table('files', 'filepath,analyzer,chash', 'LEFT', "files.id = trainee_management.files")
        ->table('issues', array(), 'INNER', "trainee_management.track_id = issues.track")
        ->table('issue_fields', ' value', 'INNER', "issues.id = issue_fields.issue_id  AND issue_fields.fk_id = 3630 ")
        ->table(array('issue_fields','fie_2'), "int_value", 'LEFT', "fie_2.issue_id = issue_fields.issue_id AND fie_2.fk_id = 3635 ")
        ->where("trainee_management_maping_user.user_issue_id = ?", $user_issue_id)
        ->where("trainee_management_maping_user.org_id = ?", $org_id)
        ->where("trainee_management_maping_user.is_archived = ?", '1')
            -> order("`fie_2`.int_value DESC");
        $getTraineenameResult = MVC_Core::$db->query($q);
        return $getTraineenameResult;
}                                                                           //active/archieved training task 04-12-25 end


    
    public function getDefaultTrainingName($track,$Training_name){



        $Training_name = !empty($Training_name) ? $Training_name : '';//16092024 added by iwi

        $q = QueryFactory::select()
        ->table('trainee_management_maping_user','id,tr_training_name,tr_training_date, tr_training_start_date,tr_training_end_date,tr_training_expiry_date,tr_training_files,tr_training_result,isExpiry,tr_training_name,tr_training_name_id,isDisableTraining')//17122024 Training Record add checkbox by iwi | //IWI mapping with training type id
        ->table('files', 'filepath,analyzer,chash', 'LEFT', "files.id = trainee_management_maping_user.tr_training_files")
        ->where("trainee_management_maping_user.tr_track_id = ?", $track)
        ->where("trainee_management_maping_user.tr_training_name_id IN(?)",$Training_name);//23122024 changes training record related  query  tr_training_name,
        // echo $q;exit;
        $checkDefaultTrainingNameQuery = MVC_Core::$db->query($q);



        return $checkDefaultTrainingNameQuery;

    }

    
    public function getNextExpDate($user_issue_id){
          $q = QueryFactory::select()
                ->table('issue_fields')               
                ->where("issue_id = ?",$user_issue_id)
                ->where("fk_id ='2115'  OR fk_id ='2116'");
//        echo $q;exit();
        $nextExpirySqlResult = MVC_Core::$db->query($q);

        return $nextExpirySqlResult;
    }

    
    
    

    // 10092024 CREATED BY IWI | START
    public function getTrainingRecordDetailsById($t_manage_maping_id,$training_id){
        if($training_id > 0){
            $getTraineeManagementMapingSql = "SELECT issue_fields.value as trainee_name,trainee_management.id,trainee_management.track_id,
            trainee_management.name,trainee_management.email,
            trainee_management.number,trainee_management.score,trainee_management.result,trainee_management.training_date,trainee_management.files,
            trainee_management.training_date as create_dt,fie_1.int_value as `start_date`,fie_2.int_value as `expiry_date`,
            fie_3.int_value as end_date,trainee_management_maping_user.id as tm_maping_user_id,files.filepath as certificate,
            files.analyzer,files.chash,trainee_management_maping_user.isExpiry,
            Case WHEN tr_training_expiry_date = '' OR tr_training_expiry_date IS NULL  
            THEN fie_2.int_value 
            ELSE tr_training_expiry_date END as `upd_expiry_date`, 

            Case WHEN tr_training_start_date = '' OR tr_training_start_date IS NULL  
            THEN fie_1.int_value 
            ELSE tr_training_start_date END as `start_date`,


            Case WHEN tr_training_end_date = '' OR tr_training_end_date IS NULL  
            THEN fie_3.int_value 
            ELSE tr_training_end_date END as `end_date`
            

            FROM trainee_management_maping_user
            INNER JOIN trainee_management ON trainee_management_maping_user.trainee_management_id=trainee_management.id
            INNER JOIN issues  ON trainee_management.track_id=issues.track
            INNER JOIN issue_fields ON issues.id=issue_fields.issue_id AND issue_fields.fk_id = 3630

            LEFT JOIN `issue_fields` AS fie_1 ON `fie_1`.issue_id=issue_fields.issue_id AND (fie_1.fk_id=3634) 
            LEFT JOIN `issue_fields` AS fie_2 ON `fie_2`.issue_id=issue_fields.issue_id AND (fie_2.fk_id=3635) 
            LEFT JOIN `issue_fields` AS fie_3 ON `fie_3`.issue_id=issue_fields.issue_id AND (fie_3.fk_id=3642) 
            LEFT JOIN files ON files.id = trainee_management.files
            WHERE  trainee_management.id = $training_id";
        }
        
        if($t_manage_maping_id > 0){
            $getTraineeManagementMapingSql = "SELECT trainee_management_maping_user.id,tr_track_id,tr_training_name as trainee_name,tr_training_date as create_dt,
            tr_training_start_date as `start_date`,tr_track_id
            ,tr_training_end_date as end_date,tr_training_expiry_date as `expiry_date`,files.filepath as certificate,
            files.analyzer,files.chash,tr_training_result as result,tr_training_score as score,trainee_management_maping_user.isExpiry
            FROM trainee_management_maping_user
            LEFT JOIN files ON files.id = trainee_management_maping_user.tr_training_files
            WHERE  trainee_management_maping_user.id = $t_manage_maping_id";
        }

        $getTraineeManagementMapingResult = MVC_Core::$db->query($getTraineeManagementMapingSql);
        if(!$getTraineeManagementMapingResult)
        return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

        foreach($getTraineeManagementMapingResult as $row) {
            if($training_id > 0){

                if (preg_match($pregMatchCheckDate,$row['start_date'])) {
                    $row['start_date'] =   $row['start_date'] ;
                } else {
                    $row['start_date'] =  date("Y-m-d",($row['start_date'])) ;
                }

                if (preg_match($pregMatchCheckDate,$row['end_date'])) {
                    $row['end_date'] =   $row['end_date'] ;
                } else {
                    $row['end_date'] =  date("Y-m-d",($row['end_date'])) ;
                }

                if($row['upd_expiry_date'] !='' && $row['upd_expiry_date'] != '0000-00-00'){
                    if (preg_match($pregMatchCheckDate,$row['upd_expiry_date'])) {
                        $row['expiry_date'] =   $row['upd_expiry_date'] ;
                    } else {
                        $row['expiry_date'] =  date("Y-m-d",($row['upd_expiry_date'])) ;
                    }
                }else{
                    $row['expiry_date'] = $row['expiry_date'] !='' ?  date("Y-m-d",($row['expiry_date'])) : '';
                }
                if($row['result'] !=1){
                    $row['expiry_date'] = '';
                }
            }
            if($t_manage_maping_id > 0){
                $row['start_date'] = $row['start_date'] !='0000-00-00' ?  date("Y-m-d",strtotime($row['start_date'])) : '';
                $row['end_date'] =$row['end_date'] !='0000-00-00' ?   date("Y-m-d",strtotime($row['end_date'])) : '';
                $row['expiry_date'] = $row['expiry_date'] !='0000-00-00' ?  date("Y-m-d",strtotime($row['expiry_date'])) : '';
            }
            if($row['isExpiry'] == 0){ $row['expiry_date'] = ''; }

            $getTraineeResult = $row;
        }
        
    }
    // 10092024 CREATED BY IWI | END


    //07102024 Agent Report filter modify start
    public function getAgentClaimRecordChart($org_id,$where,$having){
        $agentReportSql = "SELECT 
        iss.id as issue_id,airport.IATA as origin_name,iod.process as status,
        COUNT(subm.id) as y_axis,subm.id AS comm_contacts_id,iss.ctime,
        SUBSTRING_INDEX(SUBSTRING_INDEX(subm.xdata, '\"company_name\":\"', -1), '\"', 1) AS x_axis, 
        SUBSTRING_INDEX(SUBSTRING_INDEX(subm.xdata, '\"company_name\":\"', -1), '\"', 1) AS company_name 
        FROM issues AS iss
        LEFT JOIN comm_contacts AS subm ON iss.submit_id = subm.id
        LEFT JOIN issue_orgdata AS iod ON (iss.id = iod.issue_id AND iod.org_id = iss.master_org_id) 

        LEFT  JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 66)
        LEFT  JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)

        WHERE iod.process NOT IN ('hidden') 
        AND iss.type_id = 1 AND iss.master_org_id = $org_id $where
        GROUP BY x_axis $having
        ORDER BY comm_contacts_id DESC";
        $agentReportResult = MVC_Core::$db->query($agentReportSql);
        if (!$agentReportResult)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
        
        return $agentReportResult;
    }

    public function getClaimTypeRecordSql($org_id,$claimType,$where,$having){

        $reportSql = "SELECT 
        iss.id as issue_id,airport.IATA as x_axis,airport.IATA as origin_name,
        airport_2.IATA as dest_name,
        ixf_2.value as clamim_type,iod.process as status,
        COUNT(subm.id) as y_axis,subm.id AS comm_contacts_id,iss.ctime,
        SUBSTRING_INDEX(SUBSTRING_INDEX(subm.xdata, '\"company_name\":\"', -1), '\"', 1) AS company_name 
        FROM issues AS iss
        LEFT JOIN comm_contacts AS subm ON iss.submit_id = subm.id
        LEFT JOIN issue_orgdata AS iod ON (iss.id = iod.issue_id AND iod.org_id = iss.master_org_id) 
        LEFT  JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 66)

        LEFT  JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
        LEFT  JOIN `issue_fields` AS ixf_2 ON (`ixf_2`.issue_id = `iss`.id AND `ixf_2`.fk_id = 65)

        LEFT  JOIN `issue_fields` AS ixf_3 ON (`ixf_3`.issue_id = `iss`.id AND `ixf_3`.fk_id = 67)
        LEFT  JOIN `data_airports` AS airport_2 ON (ixf_3.int_value = `airport_2`.id)

        WHERE iod.process NOT IN ('hidden') 
        AND iss.type_id = 1 AND iss.master_org_id = $org_id   $where
        GROUP BY 1 $having AND clamim_type = '$claimType'  ORDER BY comm_contacts_id DESC";
        $reportResult = MVC_Core::$db->query($reportSql);
        if (!$reportResult)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
        
        return $reportResult;
    }

    public function getClaimTypeTotalRecordSql($org_id,$claimType,$where,$agentname,$reportStatus,$originVal){
        $where2 = '';
        if($agentname !=''){
            $where2 = " AND SUBSTRING_INDEX(SUBSTRING_INDEX(subm.xdata, '\"company_name\":\"', -1), '\"', 1) = \"$agentname\" ";
        }
        $where3 = '';
        if($reportStatus =='active'){
            $where3 = ' AND iod.process != "closed" ';
        }
        if($reportStatus =='closed'){
            $where3 = ' AND iod.process = "closed" ';
        }
        $where4 = '';
        if($originVal !=''){
            $where4 = ' AND airport.IATA = "'.$originVal.'" ';
        }
 
        $reportSql = "SELECT COUNT(*) AS `y_axis`, iss.track,`ixf`.`value` as claim_type,
        airport.IATA,
        SUBSTRING_INDEX(SUBSTRING_INDEX(subm.xdata, '\"company_name\":\"', -1), '\"', 1) AS company_name 
        FROM `issues` AS iss
        LEFT JOIN comm_contacts AS subm ON iss.submit_id = subm.id 
        LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
        LEFT JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 65)

        LEFT JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 66)
        LEFT JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)

        WHERE `iod`.process NOT IN('hidden')  AND `iss`.type_id = 1  AND `iod`.org_id = $org_id 
        AND  `ixf`.`value` = '$claimType' $where $where2 $where3 $where4";
        $reportResult = MVC_Core::$db->query($reportSql);
        if (!$reportResult)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
        
        return $reportResult;
    }

    public function getAgentClaimRecordListData($org_id,$claimType,$where,$having){
        $having2 = '';
        if($claimType !=''){
            $having2 = " AND claim_type = '$claimType'";
        }
        $agentReportSql = "SELECT 
        iss.track,iss.cache_primary as awb_no,
        iss.id as issue_id,airport.IATA as origin_name,iod.process as status,ixf_2.value as claim_type,
        subm.id AS comm_contacts_id,ixf_3.value as claim_amount,
        SUBSTRING_INDEX(SUBSTRING_INDEX(subm.xdata, '\"company_name\":\"', -1), '\"', 1) AS x_axis, 
        SUBSTRING_INDEX(SUBSTRING_INDEX(subm.xdata, '\"company_name\":\"', -1), '\"', 1) AS company_name 
        FROM issues AS iss
        LEFT JOIN comm_contacts AS subm ON iss.submit_id = subm.id
        LEFT JOIN issue_orgdata AS iod ON (iss.id = iod.issue_id AND iod.org_id = iss.master_org_id) 
        LEFT  JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 66)
        LEFT  JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
        LEFT  JOIN `issue_fields` AS ixf_2 ON (`ixf_2`.issue_id = `iss`.id AND `ixf_2`.fk_id = 65)
        LEFT  JOIN `issue_fields` AS ixf_3 ON (`ixf_3`.issue_id = `iss`.id AND `ixf_3`.fk_id = 2671)
        WHERE iod.process NOT IN ('hidden') 
        AND iss.type_id = 1 AND iss.master_org_id = $org_id $where
        $having $having2 
        ORDER BY comm_contacts_id DESC";
      
        $agentReportResult = MVC_Core::$db->query($agentReportSql);
        if (!$agentReportResult)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
        
        return $agentReportResult;
    }
    public function getAgentClaimRecordAmountData($org_id,$claimType,$where,$having){
        $where2 = '';
        if($claimType !=''){
            $where2 = " AND ixf_2.value = '$claimType'";
        }
        $agentReportSql = "SELECT 
        iod.process as status,
        ixf_3.value AS 'claim_amount_value',
        SUBSTRING_INDEX(SUBSTRING_INDEX(subm.xdata, '\"company_name\":\"', -1), '\"', 1) AS company_name 
        FROM issues AS iss
        LEFT JOIN comm_contacts AS subm ON iss.submit_id = subm.id
        LEFT JOIN issue_orgdata AS iod ON (iss.id = iod.issue_id AND iod.org_id = iss.master_org_id) 
        LEFT  JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 66)
        LEFT  JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
        LEFT  JOIN `issue_fields` AS ixf_2 ON (`ixf_2`.issue_id = `iss`.id AND `ixf_2`.fk_id = 65)
        LEFT  JOIN `issue_fields` AS ixf_3 ON (`ixf_3`.issue_id = `iss`.id AND `ixf_3`.fk_id = 2671)
        WHERE iod.process NOT IN ('hidden') 
        AND iss.type_id = 1 AND iss.master_org_id = $org_id $where $where2
        $having $having2 ";
       
        $agentReportResult = MVC_Core::$db->query($agentReportSql);
        if (!$agentReportResult)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
        
        return $agentReportResult;
    }
    //07102024 Agent Report filter modify end
    
    
    // IWI delete all task when delete record start
       public function setAllTaskClosedByRecord($rec_id,$org_id){
            $q_task = QueryFactory::select()
                    ->table(MVC_Core::$db->tab['tsk'], 'id')
                    ->where("`link_id` = ?", $rec_id)
                    ->where("`org_id` = ?", $org_id)
                    ->where('status != ', 'completed')
                    ->or_group_start() //this will start grouping
                    ->where('status != ', 'failed')
                    ->where('status != ', 'expired')
                    ->where('status != ', 'closed')
                    ->group_end();
            $r_task = MVC_Core::$db->query($q_task);
            //            echo $q_task;
            $allTid = array();
            foreach ($r_task as $t_id) {
                $allTid[] = $t_id['id'];
            }
            if(!empty($allTid)){
            $q_task_update = QueryFactory::update()
                    ->table(MVC_Core::$db->tab['tsk'])
                    ->where("`id` IN (?)", $allTid)
                    ->values(array(
                'status' => 'closed'
            ));
            MVC_Core::$db->query($q_task_update);
            }
        }
        
        //IWI delete all task when delete record end

    // 26092024  train_scheduler_classroom_type  addded by iwi start
    public function updateClassRoomTypeByIssueId($issue_id,$fk_id=3704){
        $updateTrainingExpDateSql = "UPDATE `issue_fields` SET  `value` = '' WHERE `issue_id` = '$issue_id' AND `fk_id` = '$fk_id'";
        $updateTrainingExpDateUpd = MVC_Core::$db->query($updateTrainingExpDateSql);
        if(!$updateTrainingExpDateUpd)
        MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
    }


    public function get_external_training_record_listing_advance_filter($org_id,$where,$havingCondition)
    {

        $trainingRecordListingSql = "SELECT 
        tm.id,iof_2.value as training_name,tm.record_id,tm.track_id as track, DATE_FORMAT(
        FROM_UNIXTIME(iof_3.int_value), '%Y-%m-%d') AS 'expiry_date_value',
        cache_primary,iof_3.int_value as expiry_date,tm.name,tm.result,tm.training_date
        FROM 
        trainee_management as tm
        LEFT JOIN issues AS iss ON tm.record_id = iss.id
        LEFT JOIN issue_fields AS iof_2 ON (iof_2.issue_id = tm.record_id AND iof_2.fk_id = 3630) 
        LEFT JOIN issue_fields AS iof_3 ON (iof_3.issue_id  = tm.record_id AND iof_3.fk_id  = 3635) 
        LEFT JOIN `issue_orgdata` AS iod  ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = `iss`.master_org_id) 
        WHERE tm.org_id  = $org_id AND (`iod`.process NOT IN('closed', 'hidden') OR `iod`.process is null) 
        AND tm.trainee_type  = 0 AND tm.result  = 1 
        HAVING  expiry_date_value  !='' $where $havingCondition ORDER BY    
        training_name ASC";

        $trainingRecordListingResult = MVC_Core::$db->query($trainingRecordListingSql);
        if (!$trainingRecordListingResult)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
        
        return $trainingRecordListingResult;
    }

    //  27092024 expiry report changes
//    public function get_training_expiry_advance_report($org_id,$havingCondition){
//        // 17122024 Training Record add checkbox  by iwi
//        // 19122024 query changes by iwi *(case when tr_track_id != '' then tr_track_id else urs_iss.track end  ) as 'track',*
//        $trainingRecordListingSql = "SELECT tmmu.isDisableTraining,tmmu.tr_training_name_id,
//        tmmu.isExpiry,iod.process as tmmu_process,  iod_tm.process as tm_process,
//        (case when tmmu.user_issue_id != ''  then tmmu.user_issue_id else tr_issue.id end) as 'user_issue_id',
//        (case when iod.process != ''  then iod.process else iod_tm.process end) as 'process_status',
//        (case when tm.trainee_type != ''  then tm.trainee_type else '' end) as 'trainee_type',
//        --(case when tm.name != '' then tm.name else CONCAT(tr_issue_fields_2.value, ' ', tr_issue_fields.value) end) as 'trainee_name', 
//        (case when tm.name != '' then tm.name else CONCAT(tr_issue_fields_2.value, ' ', tr_issue_fields.value) end) as 'trainee_name', 
//        --(case when tm.track_id != '' then tm.track_id else tr_track_id end  ) as 'track', 
//        (case when tr_track_id != '' then tr_track_id else urs_iss.track end  ) as 'track',
//        (case when tm.result != '' then tm.result else tmmu.tr_training_result end) as 'result', 
//        tm.track_id,tm.record_id,tmmu.tr_training_expiry_date,
//        --(case when iof_5.value != '' then iof_5.value else tmmu.tr_training_name end) as 'training_name',
//        (case when tmmu.tr_training_name != '' then tmmu.tr_training_name else iof_5.value end) as 'training_name',
//        (case when DATE_FORMAT(FROM_UNIXTIME(iof.int_value),'%Y-%m-%d') != '' then DATE_FORMAT(FROM_UNIXTIME(iof.int_value), '%Y-%m-%d') 
//        else tr_training_expiry_date end) as 'expiry_date_value',
//        (case when tr_training_expiry_date != '' then tr_training_expiry_date 
//        else DATE_FORMAT(FROM_UNIXTIME(iof.int_value),'%Y-%m-%d') end) as 'expiry_date_value_nw'
//       
//
//        FROM `trainee_management_maping_user` as tmmu  
//        LEFT JOIN `trainee_management` AS tm ON(`tm`.`id` = `tmmu`.`trainee_management_id`) 
//
//        LEFT JOIN `issues` AS tr_issue ON (`tr_issue`.track = `tmmu`.tr_track_id) 
//        LEFT JOIN `issue_fields` AS tr_issue_fields ON (`tr_issue_fields`.issue_id = tr_issue.id AND `tr_issue_fields`.fk_id = 2085) 
//        LEFT JOIN `issue_fields` AS tr_issue_fields_2 ON (`tr_issue_fields_2`.issue_id = tr_issue.id  AND `tr_issue_fields_2`.fk_id = 2096) 
//        LEFT JOIN `issue_orgdata` AS iod ON (`tr_issue`.id = `iod`.issue_id  AND `iod`.org_id = `tr_issue`.master_org_id) 
//
//        LEFT JOIN `issues` AS iss ON (iss.track = tm.track_id) 
//        LEFT JOIN `issue_fields` AS iof_5 ON (`iof_5`.issue_id = `iss`.id AND `iof_5`.fk_id = 3630) 
//        LEFT JOIN `issue_fields` AS iof ON (`iof`.issue_id = `iss`.id  AND `iof`.fk_id = 3635) 
//        LEFT JOIN `issue_orgdata` AS iod_tm ON (iss.id = iod_tm.issue_id) 
//
//        LEFT JOIN `issues` AS urs_iss ON (tmmu.user_issue_id = urs_iss.id) 
//
//        WHERE tmmu.org_id = $org_id AND isDisableTraining = 0 
//        HAVING (process_status NOT IN('closed', 'hidden')) $havingCondition ORDER BY trainee_name ASC";
//         // 19122024 query changes by iwi *(case when tr_track_id != '' then tr_track_id else urs_iss.track end  ) as 'track',*
//        // 17122024 Training Record add checkbox  by iwi
//            // echo  $trainingRecordListingSql;exit;
//
//        $trainingRecordListingResult = MVC_Core::$db->query($trainingRecordListingSql);
//        if (!$trainingRecordListingResult)
//        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
//        return $trainingRecordListingResult;
//    }
    public function get_training_expiry_advance_report($org_id, $havingCondition) {
    $trainingRecordListingSql = "
        SELECT *
        FROM (
            SELECT 
                tmmu.isDisableTraining,
                tmmu.tr_training_name_id,
                tmmu.isExpiry,
                iod.process AS tmmu_process,
                iod_tm.process AS tm_process,

                (CASE 
                    WHEN tmmu.user_issue_id != '' THEN tmmu.user_issue_id 
                    ELSE tr_issue.id 
                END) AS user_issue_id,

                (CASE 
                    WHEN iod.process != '' THEN iod.process 
                    ELSE iod_tm.process 
                END) AS process_status,

                (CASE 
                    WHEN tm.trainee_type != '' THEN tm.trainee_type 
                    ELSE '' 
                END) AS trainee_type,

                (CASE 
                    WHEN tm.name != '' THEN tm.name 
                    ELSE CONCAT(tr_issue_fields_2.value, ' ', tr_issue_fields.value) 
                END) AS trainee_name,

                (CASE 
                    WHEN tr_track_id != '' THEN tr_track_id 
                    ELSE urs_iss.track 
                END) AS track,

                (CASE 
                    WHEN tm.result != '' THEN tm.result 
                    ELSE tmmu.tr_training_result 
                END) AS result,

                tm.track_id,
                tm.record_id,
                tmmu.tr_training_expiry_date,

                (CASE 
                    WHEN tmmu.tr_training_name != '' THEN tmmu.tr_training_name 
                    ELSE iof_5.value 
                END) AS training_name,

                (CASE 
                    WHEN DATE_FORMAT(FROM_UNIXTIME(iof.int_value), '%Y-%m-%d') != '' 
                    THEN DATE_FORMAT(FROM_UNIXTIME(iof.int_value), '%Y-%m-%d') 
                    ELSE tr_training_expiry_date 
                END) AS expiry_date_value,

                (CASE 
                    WHEN tr_training_expiry_date != '' 
                    THEN tr_training_expiry_date 
                    ELSE DATE_FORMAT(FROM_UNIXTIME(iof.int_value), '%Y-%m-%d') 
                END) AS expiry_date_value_nw

            FROM trainee_management_maping_user AS tmmu
            LEFT JOIN trainee_management AS tm 
                ON tm.id = tmmu.trainee_management_id
            LEFT JOIN issues AS tr_issue 
                ON tr_issue.track = tmmu.tr_track_id
            LEFT JOIN issue_fields AS tr_issue_fields 
                ON tr_issue_fields.issue_id = tr_issue.id 
               AND tr_issue_fields.fk_id = 2085
            LEFT JOIN issue_fields AS tr_issue_fields_2 
                ON tr_issue_fields_2.issue_id = tr_issue.id 
               AND tr_issue_fields_2.fk_id = 2096
            LEFT JOIN issue_orgdata AS iod 
                ON tr_issue.id = iod.issue_id 
               AND iod.org_id = tr_issue.master_org_id
            LEFT JOIN issues AS iss 
                ON iss.track = tm.track_id
            LEFT JOIN issue_fields AS iof_5 
                ON iof_5.issue_id = iss.id 
               AND iof_5.fk_id = 3630
            LEFT JOIN issue_fields AS iof 
                ON iof.issue_id = iss.id 
               AND iof.fk_id = 3635
            LEFT JOIN issue_orgdata AS iod_tm 
                ON iss.id = iod_tm.issue_id
            LEFT JOIN issues AS urs_iss 
                ON tmmu.user_issue_id = urs_iss.id

            WHERE tmmu.org_id = $org_id 
              AND tmmu.isDisableTraining = 0
        ) t
        WHERE t.process_status NOT IN ('closed','hidden') $havingCondition
        ORDER BY t.trainee_name ASC
    ";

    $trainingRecordListingResult = MVC_Core::$db->query($trainingRecordListingSql);
    if (!$trainingRecordListingResult) {
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
    }
    return $trainingRecordListingResult;
}


    public function get_training_expiry_external_users_report($org_id,$havingCondition=''){

        // if(empty($havingCondition)){ $havingCondition = 1; }
        //19122024 | 709 query changes by iwi 
        $trainingRecordListingSql = "SELECT 
        tm.user_id as user_issue_id,
        tm.id,iof_2.value as training_name,tm.record_id,tm.track_id as track,0 as trainee_type, DATE_FORMAT(
        FROM_UNIXTIME(iof_3.int_value), '%Y-%m-%d') AS 'expiry_date_value',
        cache_primary,iof_3.int_value as expiry_date,tm.name as trainee_name,tm.result,tm.training_date
        FROM 
        trainee_management as tm
        INNER JOIN issues AS iss ON tm.record_id = iss.id
        LEFT JOIN issue_fields AS iof_2 ON (iof_2.issue_id = tm.record_id AND iof_2.fk_id = 3630) 
        LEFT JOIN issue_fields AS iof_3 ON (iof_3.issue_id  = tm.record_id AND iof_3.fk_id  = 3635) 
        LEFT JOIN `issue_orgdata` AS iod  ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = `iss`.master_org_id) 
        WHERE tm.org_id  = $org_id AND (`iod`.process NOT IN('closed', 'hidden') OR `iod`.process is null) 
        AND tm.trainee_type  = 0 
        HAVING  1 $havingCondition ORDER BY  training_name ASC";
        // echo $trainingRecordListingSql;exit;
        $trainingRecordListingResult = MVC_Core::$db->query($trainingRecordListingSql);
        if (!$trainingRecordListingResult)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
        return $trainingRecordListingResult;
    }
    //  27092024 expiry report changes end



    // 03102024  notrainee filter add
    public function getNoTrainingRecord($org_id){

        $trainingRecordListingSql = "SELECT i.id,i.track,
        CONCAT(COALESCE(tr_issue_fields_2.value, ''),' ',COALESCE(tr_issue_fields.value, '')) as trainee_name,
        tmu_1.tr_track_id,tmu.user_issue_id
        FROM issues i
        LEFT JOIN trainee_management_maping_user tmu ON i.id = tmu.user_issue_id
        LEFT JOIN trainee_management_maping_user tmu_1 ON i.track = tmu_1.tr_track_id
        LEFT JOIN `issue_fields` AS tr_issue_fields ON ( `tr_issue_fields`.issue_id = i.id AND `tr_issue_fields`.fk_id = 2085) 
        LEFT JOIN `issue_fields` AS tr_issue_fields_2 ON ( `tr_issue_fields_2`.issue_id = i.id AND `tr_issue_fields_2`.fk_id = 2096) 
        LEFT JOIN `issue_orgdata` AS iod  ON (`i`.id = `iod`.issue_id AND `iod`.org_id = `i`.master_org_id) 
        WHERE i.type_id IN (39) AND (iod.process NOT IN('closed', 'hidden')) 
        AND i.master_org_id = '$org_id' AND tmu_1.tr_track_id IS NULL AND tmu.user_issue_id IS NULL order by trainee_name desc";
        // echo $trainingRecordListingSql;exit;
        $trainingRecordListingResult = MVC_Core::$db->query($trainingRecordListingSql);
        if (!$trainingRecordListingResult)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
        return $trainingRecordListingResult;
    }

    public function getNoTrainingByTraineeRecord($org_id,$training_name,$trainee_name){
        $havingCondition = '';
        if($trainee_name !=''){
            $havingCondition = " having trainee_name = '$trainee_name'";
        }
        $trainingRecordListingSql = "SELECT iss.issue_id as user_id,CONCAT(fie_2.value, ' ',fie_1.value) as trainee_name,
        issues.track as track
        FROM `issue_orgdata`AS iss
        INNER JOIN `issues` ON `issues`.id = iss.issue_id                   
        INNER JOIN `issue_fields`AS fie_1 ON `fie_1`.issue_id=iss.issue_id AND (fie_1.fk_id=2085) 
        LEFT JOIN `issue_fields`AS fie_2 ON `fie_2`.issue_id=iss.issue_id AND (fie_2.fk_id=2096) 
        WHERE iss.org_id='$org_id' AND issues.type_id = 39  AND  process NOT IN ('closed', 'hidden')
        AND iss.issue_id NOT IN (
        SELECT user_issue_id  FROM `trainee_management_maping_user` 
        WHERE `tr_training_name` = '$training_name' AND `org_id` = '$org_id' AND `user_issue_id` > '0'
        ) $havingCondition ";
        $trainingRecordListingResult = MVC_Core::$db->query($trainingRecordListingSql);
        if (!$trainingRecordListingResult)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
        return $trainingRecordListingResult;
    }


    public function getNoTrainingByUserIdRecord($org_id,$user_id){

        $trainingRecordListingSql = "SELECT 
        (case when tm.user_issue_id != '' then tm.user_issue_id else tmu_1.id end  ) as 'user_issue_id',
        (case when tm.tr_track_id != '' then tm.tr_track_id else trm.track_id end ) as 'track',  
        tm.tr_training_name as training_name,tm.isDisableTraining
        FROM `trainee_management_maping_user` as tm
        LEFT JOIN issues tmu_1 ON tm.tr_track_id= tmu_1.track
        LEFT JOIN trainee_management trm ON tm.trainee_management_id = trm.id 

        WHERE tm.org_id = '$org_id' AND isDisableTraining = 0
        group by tm.tr_training_name having user_issue_id !='$user_id'";//17122024 Training Record add checkbox  by iwi
        

        $trainingRecordListingResult = MVC_Core::$db->query($trainingRecordListingSql);
        if (!$trainingRecordListingResult)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
        return $trainingRecordListingResult;
    }


    // 15102024 Audit control add  extra sec start
    public function saveAuditActivityData($insertData)
    {
        $q=QueryFactory::insert()
        -> table('audit_activity')
        -> values($insertData);
        $r = MVC_Core::$db-> query($q);
        if(!$r)
        return false;

        $audit_activity_id = MVC_Core::$db-> insert_id;


        // $id_val = $insertData['risk'].$rowsId;
        // $q=QueryFactory::update()
        // -> table('audit_activity')
        // -> where("`id` = ?", $audit_activity_id)
        // -> values(array(
        //     'id_val'=> $id_val
        // ));
        // MVC_Core::$db-> query($q);

        return $audit_activity_id;
    }
    public function updateCapaiodStatus($link_id,$insertiodData){
  
        $q=QueryFactory::update()
		-> table(MVC_Core::$db-> tab['iod'])
		-> values($insertiodData)
		-> where("`issue_id` = ?", $link_id)
		-> limit(1);
		$r = MVC_Core::$db-> query($q);
        return $r;
    }
     //10042026 audit control status |start
      public function getRecordXfieldsCapa($link_id){
  
        $q="SELECT 
            i.issue_id,
            i1592.int_value AS start_date,
            i1593.int_value AS end_date,
            i1976.value AS evaluated

                FROM issue_fields i

                LEFT JOIN issue_fields i1592 
                    ON i.issue_id = i1592.issue_id AND i1592.fk_id = 1592

                LEFT JOIN issue_fields i1593 
                    ON i.issue_id = i1593.issue_id AND i1593.fk_id = 1593

                LEFT JOIN issue_fields i1976 
                    ON i.issue_id = i1976.issue_id AND i1976.fk_id = 1976

                LEFT JOIN issue_xfdef xf 
                    ON xf.id = i.fk_id

                WHERE i.issue_id = $link_id 
                 GROUP BY i.issue_id";

		$r = MVC_Core::$db-> query($q)-> current();
        $final_data = array('issue_id'=>$link_id,'start_date'=>$r['start_date'],'end_date'=>$r['end_date'],'evaluated'=>$r['evaluated']);
        
        $q2 = "SELECT COUNT(*) AS total_capa,
            SUM(CASE WHEN aa.status = 'active' THEN 1 ELSE 0 END) AS total_capa_active,
            SUM(CASE WHEN aa.status = 'closed' THEN 1 ELSE 0 END) AS total_capa_closed
        FROM audit_activity AS aa
        WHERE aa.issue_id = $link_id";

     $r2 = MVC_Core::$db-> query($q2)-> current();
    $final_data['total_capa']=$r2['total_capa'];
    $final_data['total_capa_active']=$r2['total_capa_active'];
    $final_data['total_capa_closed']=$r2['total_capa_closed'];
     return $final_data;

    }
    //10042026 audit control status |end
    // public function getCapaStatusByTrackId($link_id)
    // {
    //     $query = "SELECT status FROM audit_activity WHERE issue_id = ?";
    //     $result = $this->db->query($query, array($link_id));
    //     return $result->result_array();
    // }
    
    public function getAuditActivityData($issue_id)
    {
        // $auditActivityDataSql = "SELECT * FROM `audit_activity` WHERE `issue_id` = '$issue_id'";
       $auditActivityDataSql = " SELECT audit_activity.*, users.name FROM audit_activity LEFT JOIN users ON audit_activity.created_by = users.id WHERE audit_activity.issue_id = '$issue_id'";  //user added to capa
        $auditActivityDataResult = MVC_Core::$db->query($auditActivityDataSql);
        if (!$auditActivityDataResult)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
        return $auditActivityDataResult;
    }

    public function updateAuditActivityData($audit_activity_id,$insertData)
    {
        // $insertData['id_val'] = $insertData['risk'].$audit_activity_id;
        $q=QueryFactory::update()
        -> table('audit_activity')
        -> where("`id` = ?", $audit_activity_id)
        -> values($insertData);
        return MVC_Core::$db-> query($q);
    }
    


    public function deleteAuditActivityData($id)
    {
        $auditActivitySql = "DELETE FROM audit_activity WHERE `id` = '$id'";     
        $auditActivityDelete = MVC_Core::$db->query($auditActivitySql);
        if(!$auditActivityDelete)
         return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
    }
    //15102024 Audit control add  extra sec end

    // 24102024 Audit control Nested grid start
    public function get_Audit_Control_List($id)
    {
        $audit_control_data_sql = "SELECT * FROM audit_activity";
		$auditControlResult = MVC_Core::$db->query($audit_control_data_sql);
		if(!$auditControlResult)
		return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

        return $auditControlResult;
    }

    public function get_ttl_audit_control_action_issue_id($org_id,$record_id,$where='')
    {
        if($where !=''){
            $where = " AND status ='active'";
        }
        return $q = MVC_Core::$db->query("SELECT count(*) as total FROM audit_activity WHERE issue_id= '$record_id' $where")->current();
    }

    public function update_ttl_audit_control_total_action_issue_id($issue_id,$total){
        $is_exist_no_train  = MVC_Core::$db->query("SELECT `value` FROM `issue_fields` WHERE `issue_id` = '$issue_id' AND `fk_id` = '3706'")->current();
        if(!empty($is_exist_no_train)){
            $issueFieldsUpdSql = "UPDATE `issue_fields` SET  `value` = '$total' WHERE `issue_id` = '$issue_id' AND `fk_id` = 3706";
        }else{
            $issueFieldsUpdSql = "INSERT INTO issue_fields (issue_id,fk_id,value)
            VALUES ('$issue_id',3706,'$total')";
        }
        $issueFieldsUpd = MVC_Core::$db->query($issueFieldsUpdSql);
        if(!$issueFieldsUpd)
        return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

        return $total;
    }
    public function update_ttl_audit_control_active_action_issue_id($issue_id,$total){
        $is_exist_no_train  = MVC_Core::$db->query("SELECT `value` FROM `issue_fields` WHERE `issue_id` = '$issue_id' AND `fk_id` = '3707'")->current();
        if(!empty($is_exist_no_train)){
            $issueFieldsUpdSql = "UPDATE `issue_fields` SET  `value` = '$total' WHERE `issue_id` = '$issue_id' AND `fk_id` = 3707";
        }else{
            $issueFieldsUpdSql = "INSERT INTO issue_fields (issue_id,fk_id,`value`)
            VALUES ('$issue_id',3707,'$total')";
        }
        $issueFieldsUpd = MVC_Core::$db->query($issueFieldsUpdSql);
        if(!$issueFieldsUpd)
        return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

        return $total;
    }

    // 24102024 Audit control Nested grid end



    // 06122024 Audit control chart query modify | start 
    public function get_audit_activity_result($org_id,$havingCondition,$havingCondition2){    //iwi record title fetch 
        $audit_activity_Sql = "SELECT audit_activity.*,issue_orgdata.process,issues.master_org_id,issues.shared_org_ids, issues.cache_primary 
        FROM `audit_activity`
        LEFT JOIN issue_orgdata ON issue_orgdata.issue_id = audit_activity.issue_id
        INNER JOIN issues ON audit_activity.track = issues.track
        WHERE audit_activity.org_id = $org_id  $havingCondition  $havingCondition2 ";
        $audit_activity_Result = MVC_Core::$db->query($audit_activity_Sql);
        if (!$audit_activity_Result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        return $audit_activity_Result;
    }
    // 06122024 Audit control chart query modify | end
 

    // 06122024 Audit control chart query modify | start 
    public function get_audit_activity_status_report_result($org_id,$whereCondition='',$whereCondition2=''){
        $audit_activity_Sql = "SELECT status as x_axis,COUNT(audit_activity.id) as y_axis
        FROM audit_activity  
        LEFT JOIN issue_orgdata ON issue_orgdata.issue_id = audit_activity.issue_id
        INNER JOIN issues ON audit_activity.track = issues.track
        WHERE audit_activity.org_id = $org_id  $whereCondition $whereCondition2   GROUP BY status";
        $audit_activity_Result = MVC_Core::$db->query($audit_activity_Sql);
        if (!$audit_activity_Result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        return $audit_activity_Result;
    }

    public function get_audit_activity_risk_report_result($org_id,$whereCondition='',$whereCondition2=''){
        $audit_activity_Sql = "SELECT risk as x_axis,COUNT(audit_activity.id) as y_axis
        FROM audit_activity  
        LEFT JOIN issue_orgdata ON issue_orgdata.issue_id = audit_activity.issue_id
        INNER JOIN issues ON audit_activity.track = issues.track
        WHERE audit_activity.org_id = $org_id $whereCondition $whereCondition2  GROUP BY risk";
        $audit_activity_Result = MVC_Core::$db->query($audit_activity_Sql);
        if (!$audit_activity_Result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        return $audit_activity_Result;
    }

    public function get_audit_activity_type_report_result($org_id,$whereCondition='',$whereCondition2=''){
        $audit_activity_Sql = "SELECT type as x_axis,COUNT(audit_activity.id) as y_axis
        FROM audit_activity  
        LEFT JOIN issue_orgdata ON issue_orgdata.issue_id = audit_activity.issue_id
        INNER JOIN issues ON audit_activity.track = issues.track
        WHERE audit_activity.org_id = $org_id $whereCondition $whereCondition2   GROUP BY type";
        $audit_activity_Result = MVC_Core::$db->query($audit_activity_Sql);
        if (!$audit_activity_Result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        return $audit_activity_Result;
    }

    public function get_audit_activity_area_report_result($org_id,$whereCondition='',$whereCondition2=''){
        $audit_activity_Sql = "SELECT result as x_axis,COUNT(audit_activity.id) as y_axis
        FROM audit_activity  
        LEFT JOIN issue_orgdata ON issue_orgdata.issue_id = audit_activity.issue_id
        INNER JOIN issues ON audit_activity.track = issues.track
        WHERE audit_activity.org_id = $org_id $whereCondition  $whereCondition2  GROUP BY result";
        $audit_activity_Result = MVC_Core::$db->query($audit_activity_Sql);
        if (!$audit_activity_Result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        return $audit_activity_Result;
    }
    
    public function get_audit_activity_total_report_result($org_id,$whereCondition='',$whereCondition2=''){
        $audit_activity_Sql = "SELECT COUNT(audit_activity.id) as count
        FROM audit_activity  
        LEFT JOIN issue_orgdata ON issue_orgdata.issue_id = audit_activity.issue_id
        INNER JOIN issues ON audit_activity.track = issues.track
        WHERE audit_activity.org_id = $org_id $whereCondition $whereCondition2";
        
        $audit_activity_total_result = MVC_Core::$db->query($audit_activity_Sql);
        if (!$audit_activity_total_result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        return $audit_activity_total_result;
    }
    // 06122024 Audit control chart query modify | start 


    // 07112024 Audit control Plan  start

    // public function get_All_Audit_Type_Name(){
    //     $audit_activity_Sql = "SELECT id,name FROM `groups` WHERE (`name` LIKE '% [Audit]%')";
    //     $audit_activity_total_result = MVC_Core::$db->query($audit_activity_Sql);
    //     if (!$audit_activity_total_result)
    //     return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

    //     return $audit_activity_total_result;
    // }


    public function saveAuditPlanData($insertData)
    {
        $q=QueryFactory::insert()
        -> table('audit_plan')
        -> values($insertData);
        $r = MVC_Core::$db-> query($q);
        if(!$r)
        return false;

        $audit_plan_id = MVC_Core::$db-> insert_id;
        return $audit_plan_id;
    }

    public function saveAuditPlanReferenceData($insertData)
    {
        $_q=QueryFactory::insert()
        -> table('audit_plan_reference')
        -> values($insertData);
        $_r = MVC_Core::$db-> query($_q);
        if(!$_r)
        return false;
        $audit_plan_id = MVC_Core::$db-> insert_id;
        return $audit_plan_id;
    }

    // public function updateAuditPlanReferenceData($audit_plan_id,$updateData)
    // {
    //     $q=QueryFactory::update()
    //     -> table('audit_plan_reference')
    //     -> where("`id` = ?", $audit_plan_id)
    //     -> values($updateData);
    //     return MVC_Core::$db-> query($q);
    // }

    public function get_audit_plan_result($org_id,$havingCondition){
        $audit_plan_Sql = "SELECT audit_plan.*,REPLACE(REPLACE(groups.name, '[', ''), ']', '') AS audit_type FROM `audit_plan`
        LEFT JOIN groups ON audit_plan.type = groups.id
        WHERE org_id = $org_id AND is_delete = 0  $havingCondition  ORDER BY audit_plan.id DESC ";
        $audit_plan_result = MVC_Core::$db->query($audit_plan_Sql);
        if (!$audit_plan_result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        return $audit_plan_result;
    }

    
    public function updateAuditPlanData($audit_plan_id,$insertData)
    {
        $q=QueryFactory::update()
        -> table('audit_plan')
        -> where("`id` = ?", $audit_plan_id)
        -> values($insertData);
        return MVC_Core::$db-> query($q);
    }

    public function get_audit_plan_data_by_id($id){
        $get_audit_plan_Sql = "SELECT * FROM `audit_plan` WHERE `id` = '$id'";
        $get_audit_plan = MVC_Core::$db->query($get_audit_plan_Sql);
        if (!$get_audit_plan)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        return $get_audit_plan;
    }

    //  13112024 audit plan Schedule section work start

    public function get_audit_plan_reference_by_id($id){

        $get_audit_plan_Sql = "SELECT audit_plan_reference.id as reference_id,issues.track,fie_1.value as audit_name,
        DATE_FORMAT(FROM_UNIXTIME(fie_2.int_value), '%Y-%m-%d') as schedule_time
        FROM audit_plan_reference
        LEFT JOIN issues ON audit_plan_reference.issue_id = issues.id
        LEFT JOIN `issue_fields` AS fie_1 ON `fie_1`.issue_id=audit_plan_reference.issue_id AND (fie_1.fk_id=1495) 
        LEFT JOIN `issue_fields` AS fie_2 ON `fie_2`.issue_id=audit_plan_reference.issue_id AND (fie_2.fk_id=1930) 
        where audit_plan_reference.audit_plan_id = '$id'";
        $get_audit_plan = MVC_Core::$db->query($get_audit_plan_Sql);
        if (!$get_audit_plan)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        return $get_audit_plan;
    }

    //  13112024 audit plan Schedule section work start

    // public function delete_audit_plan_reference_by_id($id){ //13112024 audit plan Schedule section work start
    //     $q_audit_plan = "DELETE FROM audit_plan_reference WHERE id IN ($id)";
    //     $r = MVC_Core::$db-> query($q_audit_plan);
    //     if(!$r)
    //     return false;

    //     return $r;
    // }



    // 07112024 Audit control Plan  end


    // 19112024 Audit control Results section work start

    public function get_audit_plan_result_value($issue_id,$value){

        $sql = "SELECT issue_xfdef.id,issue_xfdef.n ,issue_fields.int_value,issue_fields.issue_id 
        FROM `issue_xfdef` 
        LEFT JOIN issue_fields ON issue_xfdef.id = issue_fields.fk_id AND issue_fields.issue_id = $issue_id 
        WHERE issue_xfdef.s = 'results' AND issue_xfdef.n LIKE '%$value%'";
        $get_result = MVC_Core::$db->query($sql);
        if (!$get_result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        return $get_result;
    }

    public function get_audit_plan_result_type_by_issue_id($issue_id){

        $sql = "SELECT result,type,CONCAT(result,' ',CASE WHEN type = 'opportunity' THEN 'improvement' ELSE type END) AS result_type,
        COUNT(*) AS count
        FROM audit_activity 
        WHERE `issue_id` = '$issue_id'  AND result !='' AND type !='' 
        GROUP BY result_type";// 06122024 query changes audit_plan
        
        $get_result = MVC_Core::$db->query($sql);
        if (!$get_result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        return $get_result;
    }

    public function reset_audit_plan_result_value_by_issue_id($issue_id){
        // $sql = "UPDATE `issue_fields` SET `int_value` = '' 
        //     WHERE `issue_fields`.`issue_id` = $issue_id AND fk_id IN (
        //     2020, 2021, 2022, 2023, 2024, 2025,2026, 2027, 2028, 2029, 2030, 2031,
        //     2032, 2033, 2034, 2131, 2132, 2133,2381, 2382, 2383, 2634, 2635, 2636,
        //     2637, 2638, 2639, 2640, 2641, 2642,2643, 2644, 2645, 2646, 2647, 2648)";
        $sql = '';
        if($issue_id > 0){ // 05122024 
            $sql = "DELETE FROM issue_fields WHERE issue_id = '$issue_id'
            AND fk_id IN (
                2020, 2021, 2022, 2023, 2024, 2025,
                2026, 2027, 2028, 2029, 2030, 2031,
                2032, 2033, 2034, 2131, 2132, 2133,
                2381, 2382, 2383, 2634, 2635, 2636,
                2637, 2638, 2639, 2640, 2641, 2642,
                2643, 2644, 2645, 2646, 2647, 2648
            )";
            // echo $sql;exit;
            $reset_query = MVC_Core::$db->query($sql);
            if (!$reset_query)
            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

            return $reset_query;
        }     
    }


    public function update_add_audit_plan_result_value($issue_id,$value,$fk_id,$action='add'){
        if($action =='add'){
            $sql = "INSERT INTO issue_fields (issue_id,fk_id,int_value)
            VALUES ('$issue_id',$fk_id,'$value')";
        }else{
            $sql = "UPDATE `issue_fields` SET `int_value` = '$value' 
            WHERE `issue_fields`.`issue_id` = $issue_id AND `issue_fields`.`fk_id` = $fk_id";
        }    
        $get_result = MVC_Core::$db->query($sql);
        if (!$get_result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
        return $get_result;
    }
    // 19112024 Audit control Results section work end


    public function update_training_scheduler_trainee_result($result,$tr_maping_id){
        
        $updTrManagementMapingUser = "UPDATE trainee_management_maping_user SET tr_training_result = '$result' WHERE trainee_management_id IN  ($tr_maping_id)";
        $updTrManagementMapingUserQuery = MVC_Core::$db->query($updTrManagementMapingUser);
        if(!$updTrManagementMapingUserQuery)
        return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

        $updTrManagementUser = "UPDATE trainee_management SET result = $result,score='',files='' WHERE id IN  ($tr_maping_id)";
        $updTrManagementUserQuery = MVC_Core::$db->query($updTrManagementUser);
        if(!$updTrManagementUserQuery)
        return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

        return true;
    
    }





    // 02122024 Training Scheduler upload multiple files
    public function get_files_data_by_trainee_management_id($id){

        $sql = "SELECT * FROM `files` WHERE `id` IN ($id)";
        $get_result = MVC_Core::$db->query($sql);
        if (!$get_result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        $get_files = array();
        foreach($get_result as $val){
            if($val['analyzer'] == 'image'){
                $filepath = $val['filepath'];
                $file = explode('/',$filepath);
                $file2 = $file[0].$file[1];
                $file = $file[2];
                $file =   str_replace('.png','.jpg',$file);
                $file = substr($file,2);
                $tr_file_path = '/uimg/thms/'.$file2.'/'.$file;
                $certificate = $tr_file_path;
                $files = '<div id="'.$val['id'].'"><a href="'.$certificate.'" title='.$val['name'].' download><img src="/img/icons16/file_extension_zip.png"></a><sup class="tr_scheduler_tab_single_trainee_file_id" data-id="'.$val['id'].'"><i class="fa fa-window-close" aria-hidden="true" style="color: red;"></i></sup></div>';
            }else{
                $files = '<div id="'.$val['id'].'"><a href="/files?action=download_attachment&ch='.$val['chash'].'" download title='.$val['name'].'><img src="/img/icons16/file_extension_zip.png"></a><sup class="tr_scheduler_tab_single_trainee_file_id" data-id="'.$val['id'].'"><i class="fa fa-window-close" aria-hidden="true" style="color: red;"></i></sup></div>';

            }  
            $get_files[] = array(
                'files'=>$files
            );
        }
        return $get_files;
    }
    public function update_files_data_by_trainee_management_id($id){

        $sql = "UPDATE trainee_management
        SET attach_all_files = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', attach_all_files, ','), ',$id,', ','))
        WHERE FIND_IN_SET('$id', attach_all_files)";
        $get_result = MVC_Core::$db->query($sql);
        if (!$get_result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
        
        return true;
    }
    // 02122024 Training Scheduler upload multiple files


    // 17122024 Training Record add checkbox  by iwi | start
    public function Disable_training_record_by_id($id,$status=1){

        $sql = "UPDATE trainee_management_maping_user SET isDisableTraining = '$status' WHERE id IN ($id)";
        $get_result = MVC_Core::$db->query($sql);
        if (!$get_result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
        
        return true;
    }
    // 17122024 Training Record add checkbox  by iwi | end


    
    // 23122024 Quality Event get Event type value by iwi | start
    public function Get_Event_type_by_record_id($record_id){

        $sql = "SELECT issue_fields.value FROM `issue_fields` WHERE issue_id = $record_id AND fk_id = 2801";

        $get_result = MVC_Core::$db->query($sql);
        if (!$get_result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        $get_data = '';
        foreach($get_result as $val){
            $get_data = $val['value'];
        }

        return $get_data;
    }
    // 23122024 Quality Event get Event type value by iwi | end


    //30122024 Quality Event grid changes by IWI | Start
    public function get_quality_event_grid_capa_list($org_id){

        $getQualityEventSql = "SELECT track,issue_id as id,title,id_val,type,risk,person,deadline_date,status,closing_date,result,
		improvement,comments,closing_date
		FROM `audit_activity` WHERE org_id = '$org_id'  AND issue_type_id = 31";
        
        $getQualityEventSqlQuery = MVC_Core::$db->query($getQualityEventSql);
        if(!$getQualityEventSqlQuery)
        return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

        return $getQualityEventSqlQuery;
    }
    //30122024 Quality Event grid changes by IWI | End


    // 15012025 grid changes code changes start
    public function get_capa_data_grid_capa_list($issue_type_id,$issue_id){

        $getQualityEventSql = "SELECT count(audit_activity.id) as total
		FROM `audit_activity` WHERE issue_type_id = '$issue_type_id'  AND issue_id = '$issue_id'";
        
        $getQualityEventSqlQuery = MVC_Core::$db->query($getQualityEventSql);
        if(!$getQualityEventSqlQuery)
        return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

        $currentTotalVal = 0;
        foreach($getQualityEventSqlQuery as $val){
            $currentTotalVal = $val['total'];
        }
        return $currentTotalVal;
    }
    // 15012025 grid changes code changes end



    // 17012025 Sharing Tab Add Track Section | start
    public function saveTrackLinkBySharingTabData($insertData)
    {
        $q=QueryFactory::insert()
        -> table('module_data_sharing')
        -> values($insertData);
        $r = MVC_Core::$db-> query($q);
        if(!$r)
        return false;
        $id = MVC_Core::$db-> insert_id;
        return $id;
    }

    public function updateTrackLinkBySharingTabData($id,$insertData)
    {
        $q=QueryFactory::update()
        -> table('module_data_sharing')
        -> where("`id` = ?", $id)
        -> values($insertData);
        return MVC_Core::$db-> query($q);
    }

    public function get_track_link_by_sharing_tab_list($issue_link=''){

        // $getSharingTabSql = "SELECT module_data_sharing.*,issue_types.name 
        // FROM module_data_sharing 
        // LEFT JOIN issues ON module_data_sharing.ref_track_id = issues.track 
        // LEFT JOIN issue_types ON issues.type_id= issue_types.id 
        // WHERE module_data_sharing.from_issue_track = '$issue_link'  OR module_data_sharing.ref_track_id= '$issue_link'";


        $getSharingTabSql = "SELECT module_data_sharing.*,issue_types.name,
        CASE  WHEN module_data_sharing.from_issue_track = '$issue_link' THEN module_data_sharing.ref_track_id
        WHEN module_data_sharing.ref_track_id = '$issue_link' THEN module_data_sharing.from_issue_track
        END AS related_track_id
        FROM module_data_sharing 
        LEFT JOIN issues ON module_data_sharing.ref_track_id = issues.track 
        LEFT JOIN issue_types ON issues.type_id = issue_types.id 
        WHERE module_data_sharing.from_issue_track = '$issue_link' OR module_data_sharing.ref_track_id = '$issue_link'";



        // echo $getSharingTabSql;exit;
        $getSharingTabSqlQuery = MVC_Core::$db->query($getSharingTabSql);
        if(!$getSharingTabSqlQuery)
        return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

        return $getSharingTabSqlQuery;
    }

    public function deleteSharingTabData($id)
    {
        $auditActivitySql = "DELETE FROM module_data_sharing WHERE `id` = '$id'";     
        $auditActivityDelete = MVC_Core::$db->query($auditActivitySql);
        if(!$auditActivityDelete)
         return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
    } 

    public function get_sharing_tab_check_duplicate_track_link($issue_link='',$ref_track_id='',$id=''){
        $where = '';
        if($id > 0){
            $where = "AND module_data_sharing.id != '$id'";
        }
        $getSharingTabSql = "SELECT count(module_data_sharing.id) as count,module_data_sharing.id 
        FROM module_data_sharing 
        LEFT JOIN issues ON module_data_sharing.ref_track_id = issues.track 
        LEFT JOIN issue_types ON issues.type_id= issue_types.id 
        WHERE 
        ((module_data_sharing.from_issue_track = '$ref_track_id' AND module_data_sharing.ref_track_id = '$issue_link') OR
        (module_data_sharing.from_issue_track = '$issue_link' AND module_data_sharing.ref_track_id = '$ref_track_id')) $where";
        $getSharingTabSqlQuery = MVC_Core::$db->query($getSharingTabSql);
        if(!$getSharingTabSqlQuery)
        return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

        $getSharingTabSqlData = array();
        foreach($getSharingTabSqlQuery as $val){
            $getSharingTabSqlData = $val;
        }
        return $getSharingTabSqlData;
    }





    public function get_sharing_tab_check_valid_track_link($issue_link=''){
        $getSharingTabSql = "SELECT count(issues.id) as count FROM `issues` WHERE track = '$issue_link'";
        // echo $getSharingTabSql;
        $getSharingTabSqlQuery = MVC_Core::$db->query($getSharingTabSql);
        if(!$getSharingTabSqlQuery)
        return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

        $getSharingTabSqlData = array();
        foreach($getSharingTabSqlQuery as $val){
            $getSharingTabSqlData = $val;
        }
        return $getSharingTabSqlData;
    }
    // 17012025 Sharing Tab Add Track Section | end


    // 30012025 header count add by iwi | start
    public function get_total_number_of_capa_audit($org_id,$havingCondition,$havingCondition2){
        $audit_activity_Sql = "SELECT count(audit_activity.id) as total
        FROM `audit_activity`
        LEFT JOIN issue_orgdata ON issue_orgdata.issue_id = audit_activity.issue_id
        INNER JOIN issues ON audit_activity.track = issues.track
        WHERE audit_activity.org_id = $org_id  $havingCondition  $havingCondition2 ";
        $audit_activity_Result = MVC_Core::$db->query($audit_activity_Sql);
        if (!$audit_activity_Result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        return $audit_activity_Result;
    }

    public function get_total_number_of_notification($user_id){
        
        $user_notification_Sql = "SELECT count(evn.id) as total FROM `notifications` AS evn WHERE `evn`.`user_id` = $user_id 
        AND (`evn`.`status` = 'New' OR `evn`.`status` = 'Read') ORDER BY `evn`.`time` DESC";

        $user_notification_Result = MVC_Core::$db->query($user_notification_Sql);
        if (!$user_notification_Result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        $get_total_number_of_notification =0;
        foreach($user_notification_Result as $val){
            $get_total_number_of_notification = $val['total'];
        }

        return $get_total_number_of_notification;
    }
    // 30012025 header count add by iwi | end

    // 06022025 get same awb number track id new function | start
    public function get_check_duplicate_awb_number_by_issue_id($track_id=''){
        $duplicate_awb_number_by_issue_id_Sql = "SELECT i.track, f.value AS awb_value
        FROM issues i
        JOIN issue_fields f ON i.id = f.issue_id
        WHERE f.fk_id = 3 AND i.track = '$track_id'";
       
        $duplicate_awb_number_by_issue_id_Result = MVC_Core::$db->query($duplicate_awb_number_by_issue_id_Sql);
        if (!$duplicate_awb_number_by_issue_id_Result)
        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        $awb_value ='';
        foreach($duplicate_awb_number_by_issue_id_Result as $val){
            $awb_value = $val['awb_value'];
        }

        $track_value =array();
        if($awb_value !=''){
            $get_track_id_value_Sql = "SELECT f.value AS awb_value,i.track FROM issue_fields f 
            JOIN issues i ON i.id = f.issue_id WHERE f.fk_id = 3 AND f.value = '$awb_value'";
            $get_track_id_value_result = MVC_Core::$db->query($get_track_id_value_Sql);
            if (!$get_track_id_value_result)
            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
            
            foreach($get_track_id_value_result as $val){
                $track_value[] = $val['track'];
            }
        }
        return $track_value;
    }
    // 06022025 get same awb number track id new function | end

    // 21112024 Training Scheduler Training Date section work start
    // public function get_trainee_management_attach_all_files($id){
    //     $sql ="SELECT attach_all_files FROM `trainee_management` WHERE `id` = '$id'";
    //     $result = MVC_Core::$db->query($sql);
    //     $attach_all_files = array();
    //     foreach($result as $row) {
    //         $attach_all_files =  !empty($row['attach_all_files']) ?  explode(",",$row['attach_all_files'])  : array();
    //     }
    //     return $attach_all_files;
    // }
    // public function update_trainee_management_attach_all_files($id,$attach_all_files){
    //     $updTrManagementAllFiles = "UPDATE trainee_management SET attach_all_files = '$attach_all_files' WHERE id =  $id";
    //     $updTrManagementAllFilesQuery = MVC_Core::$db->query($updTrManagementAllFiles);
    //     if(!$updTrManagementAllFilesQuery)
    //     return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
        
    //     return true;
    // }

    // public function get_files_tb_all_files_details_by_id($id){
    //     $sql ="SELECT id,filepath,name,analyzer FROM `files` WHERE `id`  IN ($id)";
    //     $result = MVC_Core::$db->query($sql);
    //     return $result;
    // }
    // 21112024 Training Scheduler Training Date section work end


    //30122024 Quality Event grid changes by IWI | Start
    public function get_documentation_grid_capa_list($org_id){
        $where = '';
        if($org_id > 0){
            $where = "notes.org_id = '$org_id'  AND ";
        }
       
        $getDocumentationGridSql = "SELECT link_id,title,ref_number,version,visibility,
        notes.update_dt,users.name as last_editor_name,organizations.name as org_name
		FROM `notes` 
        INNER JOIN users ON notes.editor_id = users.id
        INNER JOIN organizations ON notes.org_id = organizations.id
        WHERE $where wv_publish = 1";
        
        $getDocumentationGridSqlQuery = MVC_Core::$db->query($getDocumentationGridSql);
        if(!$getDocumentationGridSqlQuery)
        return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

        return $getDocumentationGridSqlQuery;
    }
    //30122024 Quality Event grid changes by IWI | End
    
    
    
    
    

    // get active published doc details start
    public function get_active_published_doc_list($issue_type_id,$issue_id){

         $q=QueryFactory::select()
//            -> table(MVC_Core::$db-> tab['nte'], 'id,editor_id,org_id,dep_id,wv_publish_for,dt,title,created_by,published_by')
            -> table(MVC_Core::$db-> tab['nte'], 'id')
            -> where("`wv_publish` = ?", '1')
            -> where("`is_notification` = ?", '0')
            -> where("`visibility` = ?", 'org')
            -> where("`link_id` = ?", $issue_id);


        $r = MVC_Core::$db-> query($q);
        
        foreach($r as $val){
            $getData[] = $val;
        }
//        exit();
        return $getData;
    }
    // get active published doc details end

    
    // 05032025 Training Scheduler get user id start
    public function getTraineeUserEmailById($issue_id=''){
        $getSharingTabSql = "SELECT issue_fields.issue_id,issue_fields.value FROM `issue_fields`  WHERE `issue_id` = '$issue_id' AND fk_id = 3658";
        $getResult = MVC_Core::$db->query($getSharingTabSql);
        if(!$getResult)
        return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");

        $user_email = array();
        foreach($getResult as $val){
            $user_email = $val['value'];
        }
        return $user_email;
    }
    // 05032025 Training Scheduler get user id end
    
    
    
    
    public function get_license_monitor_management($license_monitor_issue_id,$doc_id,$org_id,$user_id){
          $q=QueryFactory::select()
            -> table('license_monitor_management', 'id')
            -> where("`issue_id` = ?", $doc_id)
            -> where("`org_id` = ?", $org_id)
            -> where("`user_id` = ?", $user_id)
            -> where("`license_monitor_issue_id` = ?", $license_monitor_issue_id);
//         exit();
        $r = MVC_Core::$db->query($q);
        if(!empty($r)){
             return false;
        }else{
             return true;
        }       
    }
    public function insert_license_monitor_management($saveData)
    {
        $q=QueryFactory::insert()
            -> table('license_monitor_management')
            -> values($saveData);
        return MVC_Core::$db-> query($q);
    }
    public function get_license_monitor_management_by_doc_id($doc_id,$org_id,$user_id){

        $q="SELECT 
        license_monitor_management.issue_id, 
        license_monitor_management.id, 
        fie_1.value as chapter,
        issues.track as  license_link,
        fie_2.value as name_or_title, 
        fie_8.value as chapter_no, 
        fie_4.value as country_code,
fie_5.value as section,
        groups.name as license_type
        FROM 
        license_monitor_management 
        INNER JOIN issue_fields ON license_monitor_management.license_monitor_issue_id = issue_fields.issue_id 
        INNER JOIN issues ON license_monitor_management.license_monitor_issue_id = issues.id
        LEFT JOIN `issue_fields` AS fie_1 ON `fie_1`.issue_id = issue_fields.issue_id 
        AND (fie_1.fk_id = 2432) 
        LEFT JOIN `issue_fields` AS fie_2 ON `fie_2`.issue_id = issue_fields.issue_id 
        AND (fie_2.fk_id = 2419) 
         LEFT JOIN `issue_fields` AS fie_8 ON `fie_1`.issue_id = issue_fields.issue_id 
        AND (fie_8.fk_id = 2414)
        LEFT JOIN `issue_fields` AS fie_4 ON `fie_4`.issue_id = issue_fields.issue_id AND (fie_4.fk_id = 2796)
LEFT JOIN `issue_fields` AS fie_5 ON `fie_5`.issue_id = issue_fields.issue_id AND (fie_5.fk_id = 2435)
         INNER JOIN groups ON issues.group_id=groups.id 
         WHERE `license_monitor_management`.`issue_id` = '$doc_id'
          AND `license_monitor_management`.`org_id` = $org_id
          GROUP BY license_monitor_management.license_monitor_issue_id";
        $r = MVC_Core::$db->query($q);
        
             return $r;      
    }
    
    
    
    
    
    
   public function get_documentation_management_by_license_id($doc_id,$org_id,$user_id){
//         $q="SELECT 
//    iss.id, 
//    iss.track AS license_link, 
//    iss.cache_primary AS name_or_title, 
//    lmm.document_link,
//    MAX(fie_1.value) AS chapter,
//    MAX(fie_2.value) AS title,
//    MAX(fie_4.int_value) AS valid_from,
//    MAX(fie_5.int_value) AS expire_date,
//    MAX(fie_6.int_value) AS next_review,
//    MAX(fie_7.value) AS doctype,
//    CASE MAX(fie_7.value)
//        WHEN 'cert' THEN 'Certificate'
//        WHEN 'man' THEN 'Manual'
//        WHEN 'ctr' THEN 'Contract'
//        WHEN 'ag' THEN 'Agenda'
//        ELSE MAX(fie_7.value)
//    END AS doctype_label,
//    xf_7.cd AS doc_type_def,
//   MAX(fie_8.value) AS publish,
//CASE 
//    WHEN MAX(fie_8.value) LIKE '%\"in\"%' AND MAX(fie_8.value) LIKE '%\"ex\"%' THEN 'Intranet & Extranet'
//    WHEN MAX(fie_8.value) LIKE '%\"in\"%' THEN 'Intranet'
//    WHEN MAX(fie_8.value) LIKE '%\"ex\"%' THEN 'Extranet'
//    ELSE MAX(fie_8.value)
//END AS publish_label,
//    xf_8.cd AS publish_def,
//    MAX(fie_9.int_value) AS review_date,
//    groups.name AS license_type
//FROM issues AS iss
//RIGHT JOIN issue_orgdata AS iod 
//    ON iss.id = iod.issue_id AND iod.org_id = 5 AND iod.is_enabled = 1
//INNER JOIN license_monitor_management AS lmm 
//    ON iod.issue_id = lmm.issue_id
//INNER JOIN issue_fields AS base_fields 
//    ON base_fields.issue_id = lmm.license_monitor_issue_id
//INNER JOIN groups 
//    ON iss.group_id = groups.id
//LEFT JOIN issue_fields AS fie_1 
//    ON fie_1.issue_id = base_fields.issue_id AND fie_1.fk_id = 2432
//LEFT JOIN issue_fields AS fie_2 
//    ON fie_2.issue_id = base_fields.issue_id AND fie_2.fk_id = 2419
//LEFT JOIN issue_fields AS fie_4 
//    ON fie_4.issue_id = lmm.issue_id AND fie_4.fk_id = 2016
// 
//LEFT JOIN issue_fields AS fie_5 
//    ON fie_5.issue_id = lmm.issue_id AND fie_5.fk_id = 2017
//LEFT JOIN issue_fields AS fie_6 
//    ON fie_6.issue_id = lmm.issue_id AND fie_6.fk_id = 2809
//LEFT JOIN issue_fields AS fie_7 
//    ON fie_7.issue_id = lmm.issue_id AND fie_7.fk_id = 779
//LEFT JOIN issue_xfdef AS xf_7 
//    ON fie_7.fk_id = xf_7.id
//LEFT JOIN issue_fields AS fie_8 
//    ON fie_8.issue_id = lmm.issue_id AND fie_8.fk_id = 2672
//LEFT JOIN issue_xfdef AS xf_8 
//    ON fie_8.fk_id = xf_8.id
//LEFT JOIN issue_fields AS fie_9 
//    ON fie_9.issue_id = lmm.issue_id AND fie_9.fk_id = 2413
//         WHERE `lmm`.`license_monitor_issue_id` = '$doc_id' 
//         AND `lmm`.`org_id` = $org_id AND 
//         `lmm`.`user_id` = $user_id 
//         GROUP BY lmm.issue_id"; // IWI License monitor get connected document issue fix
         
         
         $q="SELECT 
    lmm.issue_id,
    iss2.id, 
    iss2.track AS license_link, 
    iss2.cache_primary AS name_or_title, 
    lmm.document_link,

    MAX(fie_1.value) AS chapter,
    MAX(fie_2.value) AS title,

    MAX(fie_4.int_value) AS valid_from,
    MAX(fie_5.int_value) AS expire_date,
    MAX(fie_6.int_value) AS next_review,

    MAX(fie_7.value) AS doctype,
    CASE MAX(fie_7.value)
        WHEN 'cert' THEN 'Certificate'
        WHEN 'man' THEN 'Manual'
        WHEN 'ctr' THEN 'Contract'
        WHEN 'ag' THEN 'Agenda'
        ELSE MAX(fie_7.value)
    END AS doctype_label,

    xf_7.cd AS doc_type_def,

    MAX(fie_8.value) AS publish,
    CASE 
        WHEN MAX(fie_8.value) LIKE '%\"in\"%' AND MAX(fie_8.value) LIKE '%\"ex\"%' THEN 'Intranet & Extranet'
        WHEN MAX(fie_8.value) LIKE '%\"in\"%' THEN 'Intranet'
        WHEN MAX(fie_8.value) LIKE '%\"ex\"%' THEN 'Extranet'
        ELSE MAX(fie_8.value)
    END AS publish_label,

    xf_8.cd AS publish_def,

    MAX(fie_9.int_value) AS review_date,

    g.name AS license_type

FROM license_monitor_management AS lmm

RIGHT JOIN issue_orgdata AS iod 
    ON lmm.license_monitor_issue_id = iod.issue_id 
    AND iod.org_id = $org_id 
    AND iod.is_enabled = 1

-- same as first query
INNER JOIN issues AS iss 
    ON iod.issue_id = iss.id

-- important second join (your logic)
INNER JOIN issues AS iss2 
    ON iss2.id = lmm.issue_id

INNER JOIN issue_fields AS base_fields 
    ON base_fields.issue_id = lmm.license_monitor_issue_id

INNER JOIN groups AS g 
    ON iss.group_id = g.id

LEFT JOIN issue_fields AS fie_1 
    ON fie_1.issue_id = base_fields.issue_id AND fie_1.fk_id = 2432

LEFT JOIN issue_fields AS fie_2 
    ON fie_2.issue_id = base_fields.issue_id AND fie_2.fk_id = 2419

LEFT JOIN issue_fields AS fie_4 
    ON fie_4.issue_id = lmm.issue_id AND fie_4.fk_id = 2016

LEFT JOIN issue_fields AS fie_5 
    ON fie_5.issue_id = lmm.issue_id AND fie_5.fk_id = 2017

LEFT JOIN issue_fields AS fie_6 
    ON fie_6.issue_id = lmm.issue_id AND fie_6.fk_id = 2809

LEFT JOIN issue_fields AS fie_7 
    ON fie_7.issue_id = lmm.issue_id AND fie_7.fk_id = 779

LEFT JOIN issue_xfdef AS xf_7 
    ON fie_7.fk_id = xf_7.id

LEFT JOIN issue_fields AS fie_8 
    ON fie_8.issue_id = lmm.issue_id AND fie_8.fk_id = 2672

LEFT JOIN issue_xfdef AS xf_8 
    ON fie_8.fk_id = xf_8.id

LEFT JOIN issue_fields AS fie_9 
    ON fie_9.issue_id = lmm.issue_id AND fie_9.fk_id = 2413

WHERE lmm.license_monitor_issue_id = '$doc_id'
AND lmm.org_id = $org_id

-- ? critical logic from first query
AND iss2.master_org_id = '$org_id'
AND lmm.issue_id != lmm.license_monitor_issue_id

GROUP BY lmm.issue_id";
         
         
        $r = MVC_Core::$db->query($q);
        
             return $r;      
    }
    
    

    
//     IWI If record close then all active capa should be close start
    
    public function getActiveCapaDetails($issue_id) {
        $q = QueryFactory::select()
                ->table('audit_activity')
                ->where("`issue_id` = ?", $issue_id)
                // ->where("`status` IN (?, ?)", 'closed', 'active');   //close record getting error bcz of the capa close status 
                 ->where("`status` != ?", 'closed');
        return MVC_Core::$db->query($q);
    }

    public function setAllCapaClosedByRecord($rec_id) {
        $q_update = QueryFactory::update()
                ->table('audit_activity')
                ->where("`issue_id` = ?", $rec_id)
                ->where("`status` != ?", 'closed')
                ->values(array(
            'status' => 'closed'
        ));
        MVC_Core::$db->query($q_update);
    }

//     IWI If record close then all active capa should be close end
    
    
     
    
//     IWI page for all linkup with license monitor and documentation   start
    public function get_Doc_connected($issue_type_id = null, $wtr = array(),
                             $qfo_filter = null, $filter_params = array(),
                             $order_by = null, $count_rows = 25, $start_row = 0,
                             $calc_found_rows = false, $serialize_xfields = false, $html_special_chars = false)
    {
//        print_r($count_rows);
//        echo "[[[";
//        print_r($start_row);
        $org_id=$wtr['org_id'];
        $user_id=$wtr['user_id'];
           $q="SELECT DISTINCT `license_monitor_management`.`license_monitor_issue_id`, 
`iss`.`track` as license_link,
fie_1.value as chapter, 
fie_2.value as name_or_title,
fie_3.value as chapter_no, 
fie_4.value as country_code,
fie_5.value as section,
groups.name as license_type FROM license_monitor_management
INNER JOIN issues as iss ON license_monitor_management.license_monitor_issue_id = iss.id 
INNER JOIN issue_fields ON license_monitor_management.license_monitor_issue_id = issue_fields.issue_id
INNER JOIN issues ON license_monitor_management.license_monitor_issue_id = issues.id
 
RIGHT JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = 5 AND `iod`.is_enabled = 1)
LEFT JOIN `issue_fields` AS fie_1 ON `fie_1`.issue_id = issue_fields.issue_id AND (fie_1.fk_id = 2432)
LEFT JOIN `issue_fields` AS fie_2 ON `fie_2`.issue_id = issue_fields.issue_id AND (fie_2.fk_id = 2419) 
LEFT JOIN `issue_fields` AS fie_3 ON `fie_3`.issue_id = issue_fields.issue_id AND (fie_3.fk_id = 2432) 
LEFT JOIN `issue_fields` AS fie_4 ON `fie_4`.issue_id = issue_fields.issue_id AND (fie_4.fk_id = 2796)
LEFT JOIN `issue_fields` AS fie_5 ON `fie_5`.issue_id = issue_fields.issue_id AND (fie_5.fk_id = 2435)
        LEFT JOIN groups ON issues.group_id=groups.id 
        WHERE license_monitor_management.org_id = $org_id AND iss.type_id = $issue_type_id AND license_monitor_management.user_id=$user_id 
        GROUP BY license_monitor_management.id ";
//        LIMIT $start_row,$count_rows ";
        
        
//        echo $q;exit;
        $r = MVC_Core::$db-> query($q);
       
        if(!$r || empty($r))
            return $r;
        // get data from all joins ( and all ID's at the same time)
        $all = array();
        foreach($r as $issue) {
           
            $all[$issue['license_monitor_issue_id']] = $issue;
        }

        
        return $all;
    }
    
    
    
    
    public function get_Doc_not_connected($issue_type_id = null, $wtr = array(),
                             $qfo_filter = null, $filter_params = array(),
                             $order_by = null, $count_rows = 25, $start_row = 0,
                             $calc_found_rows = false, $serialize_xfields = false, $html_special_chars = false)
    {
        $org_id=$wtr['org_id'];
        $user_id=$wtr['user_id'];
            $q="SELECT 
  iss.id as license_monitor_issue_id,
  iss.track AS license_link,
  MAX(fie_1.value) AS chapter,
  MAX(fie_2.value) AS name_or_title,
  MAX(fie_3.value) AS chapter_no,
  MAX(groups.name) AS license_type
FROM issues AS iss
RIGHT JOIN issue_orgdata AS iod 
  ON iss.id = iod.issue_id AND iod.org_id = 5 AND iod.is_enabled = 1
LEFT JOIN issue_fields AS fie_1 
  ON fie_1.issue_id = iss.id AND fie_1.fk_id = 2432
LEFT JOIN issue_fields AS fie_2 
  ON fie_2.issue_id = iss.id AND fie_2.fk_id = 2419
LEFT JOIN issue_fields AS fie_3 
  ON fie_3.issue_id = iss.id AND fie_3.fk_id = 2433  
LEFT JOIN groups 
  ON iss.group_id = groups.id
WHERE iss.type_id = $issue_type_id
  AND iss.id NOT IN (
    SELECT license_monitor_issue_id
    FROM license_monitor_management
    WHERE org_id = $org_id  AND license_monitor_management.user_id=$user_id 
  )
GROUP BY iss.id, iss.track
ORDER BY iss.id DESC;
 ";
        
       
        
//        echo $q;exit;
        $r = MVC_Core::$db-> query($q);
       
        if(!$r || empty($r))
            return $r;
        // get data from all joins ( and all ID's at the same time)
        $all = array();
        foreach($r as $issue) {
           
            $all[$issue['license_monitor_issue_id']] = $issue;
        }

        
        return $all;
    }
    
    
    
    
    
    public function get_License_connected($issue_type_id = null, $wtr = array(),
                             $qfo_filter = null, $filter_params = array(),
                             $order_by = null, $count_rows = 25, $start_row = 0,
                             $calc_found_rows = false, $serialize_xfields = false, $html_special_chars = false)
    {
        
            $org_id=$wtr['org_id']; 
            $user_id=$wtr['user_id'];

        
        $q="SELECT iss.id,
iss.track AS license_link,
iss.cache_primary AS name_or_title ,
lmm.document_link,
MAX(fie_1.value)AS chapter,
MAX(fie_2.value)AS title,
MAX(fie_4.int_value)AS valid_from,
MAX(fie_5.int_value)AS expire_date,
MAX(fie_6.int_value)AS next_review,
MAX(fie_7.value)AS doctype,
CASE MAX(fie_7.value)WHEN 'cert'THEN 'Certificate'WHEN 'man'THEN 'Manual'WHEN 'ctr'THEN 'Contract'WHEN 'ag'THEN 'Agenda'ELSE MAX(fie_7.value)END AS doctype_label,
xf_7.cd AS doc_type_def,
MAX(fie_8.value)AS publish,
CASE WHEN MAX(fie_8.value)LIKE '%\"in\"%'AND MAX(fie_8.value)LIKE '%\"ex\"%'THEN 'Intranet & Extranet'WHEN MAX(fie_8.value)LIKE '%\"in\"%'THEN 'Intranet'WHEN MAX(fie_8.value)LIKE '%\"ex\"%'THEN 'Extranet'ELSE MAX(fie_8.value)END AS publish_label,
xf_8.cd AS publish_def,
MAX(fie_9.int_value)AS review_date,
groups.name AS license_type FROM issues AS iss RIGHT JOIN issue_orgdata AS iod ON iss.id=iod.issue_id AND iod.org_id=5 AND iod.is_enabled=1 INNER JOIN license_monitor_management AS lmm ON iod.issue_id=lmm.issue_id INNER JOIN issue_fields AS base_fields ON base_fields.issue_id=lmm.license_monitor_issue_id INNER JOIN groups ON iss.group_id=groups.id LEFT JOIN issue_fields AS fie_1 ON fie_1.issue_id=base_fields.issue_id AND fie_1.fk_id=2432 LEFT JOIN issue_fields AS fie_2 ON fie_2.issue_id=base_fields.issue_id AND fie_2.fk_id=2419 LEFT JOIN issue_fields AS fie_4 ON fie_4.issue_id=lmm.issue_id AND fie_4.fk_id=2016 LEFT JOIN issue_fields AS fie_5 ON fie_5.issue_id=lmm.issue_id AND fie_5.fk_id=2017 LEFT JOIN issue_fields AS fie_6 ON fie_6.issue_id=lmm.issue_id AND fie_6.fk_id=2809 LEFT JOIN issue_fields AS fie_7 ON fie_7.issue_id=lmm.issue_id AND fie_7.fk_id=779 LEFT JOIN issue_xfdef AS xf_7 ON fie_7.fk_id=xf_7.id LEFT JOIN issue_fields AS fie_8 ON fie_8.issue_id=lmm.issue_id AND fie_8.fk_id=2672 LEFT JOIN issue_xfdef AS xf_8 ON fie_8.fk_id=xf_8.id LEFT JOIN issue_fields AS fie_9 ON fie_9.issue_id=lmm.issue_id AND fie_9.fk_id=2413
 
WHERE iss.type_id = $issue_type_id  AND lmm.user_id=$user_id 
 
GROUP BY iss.id
 
ORDER BY iss.id DESC
 
LIMIT $start_row,$count_rows";
//        echo $q;exit;
        $r = MVC_Core::$db-> query($q);
       
        if(!$r || empty($r))
            return $r;
        // get data from all joins ( and all ID's at the same time)
        $all = array();
        foreach($r as $issue) {
           
            $all[$issue['id']] = $issue;
        }

        
        return $all;
    }
    
    
    
    
    public function get_License_not_connected($issue_type_id = null, $wtr = array(),
                             $qfo_filter = null, $filter_params = array(),
                             $order_by = null, $count_rows = 25, $start_row = 0,
                             $calc_found_rows = false, $serialize_xfields = false, $html_special_chars = false)
    {
         $org_id=$wtr['org_id'];
          $user_id=$wtr['user_id'];
            $q="SELECT 
  iss.id,
  iss.track AS document_link,
  iss.cache_primary,
  MAX(fie_1.value) AS chapter,
 iss.cache_primary AS name_or_title ,
  MAX(fie_4.int_value) AS valid_from,
  MAX(fie_5.int_value) AS expire_date,
  MAX(fie_6.int_value) AS next_review,
  MAX(groups.name) AS license_type
FROM issues AS iss
RIGHT JOIN issue_orgdata AS iod 
  ON iss.id = iod.issue_id AND iod.org_id = $org_id AND iod.is_enabled = 1
LEFT JOIN license_monitor_management AS lmm 
  ON lmm.issue_id = iss.id AND lmm.org_id = $org_id
LEFT JOIN issue_fields AS fie_1 
  ON fie_1.issue_id = iss.id AND fie_1.fk_id = 2432
LEFT JOIN issue_fields AS fie_2 
  ON fie_2.issue_id = iss.id AND fie_2.fk_id = 2419
LEFT JOIN issue_fields AS fie_4 
  ON fie_4.issue_id = iss.id AND fie_4.fk_id = 2016
LEFT JOIN issue_fields AS fie_5 
  ON fie_5.issue_id = iss.id AND fie_5.fk_id = 2017
LEFT JOIN issue_fields AS fie_6 
  ON fie_6.issue_id = iss.id AND fie_6.fk_id = 2809
LEFT JOIN groups 
  ON iss.group_id = groups.id
WHERE iss.type_id = $issue_type_id
  AND iss.id NOT IN (
    SELECT iod.issue_id
    FROM issue_orgdata AS iod
    INNER JOIN license_monitor_management AS lmm_sub 
      ON iod.issue_id = lmm_sub.issue_id
    WHERE iod.org_id = $org_id AND iod.is_enabled = 1  AND lmm_sub.user_id=$user_id 
  )
GROUP BY iss.id, iss.track, iss.cache_primary, lmm.document_link
ORDER BY iss.id DESC;";
        
        
//        echo $q;exit;
        $r = MVC_Core::$db-> query($q);
       
        if(!$r || empty($r))
            return $r;

        $all = array();
        foreach($r as $issue) {
           
            $all[$issue['id']] = $issue;
        }

        
        return $all;
    }
    
//     IWI page for all linkup with license monitor and documentation  end
    
    
    // get connected doc details start
    public function get_connected_doc_list($issue_type_id, $issue_id) {

        $org_id = MVC_Core::$ss->user['org_id'];
        $user_id = MVC_Core::$ss->user['id'];

//        $q = "SELECT * FROM `license_monitor_management` "
//                . "RIGHT JOIN issue_orgdata AS iod ON license_monitor_management.license_monitor_issue_id = iod.issue_id AND iod.org_id = $org_id AND iod.is_enabled = 1 "
//                . "INNER JOIN issues AS iss ON iod.issue_id = iss.id "
//                . "INNER JOIN issue_fields AS base_fields ON base_fields.issue_id = license_monitor_management.license_monitor_issue_id "
//                . "INNER JOIN groups ON iss.group_id = groups.id "
//                . "WHERE `license_monitor_issue_id` = $issue_id AND `user_id` = $user_id AND license_monitor_management.issue_id!= license_monitor_management.license_monitor_issue_id GROUP BY license_monitor_management.issue_id";
        
        
        $q = "SELECT license_monitor_management.issue_id
FROM license_monitor_management
RIGHT JOIN issue_orgdata AS iod 
  ON license_monitor_management.license_monitor_issue_id = iod.issue_id 
  AND iod.org_id = $org_id 
  AND iod.is_enabled = 1
INNER JOIN issues AS iss 
  ON iod.issue_id = iss.id
INNER JOIN issues AS iss2 
  ON iss2.id = license_monitor_management.issue_id
INNER JOIN issue_fields AS base_fields 
  ON base_fields.issue_id = license_monitor_management.license_monitor_issue_id
INNER JOIN groups 
  ON iss.group_id = groups.id
WHERE license_monitor_management.license_monitor_issue_id = $issue_id 
  AND iss2.master_org_id = '$org_id' 
  AND license_monitor_management.issue_id != license_monitor_management.license_monitor_issue_id
GROUP BY license_monitor_management.issue_id";

// echo $q;

        $r = MVC_Core::$db->query($q);

        foreach ($r as $val) {
            $getData[] = $val;
        }
//        exit();
        return $getData;
    }

    // get connected doc details end
    
    
    
       
    
//    IWI Air Cargo claim chart page new design start 
     public function getPreliminaryClaimTopOriginData($type_id,$org_id,$where_status,$where_date, $where_airline){
        $q = "SELECT 'origin' AS category,
                        airport.IATA AS airport_code,
                        COUNT(*) AS claim_count
                    FROM issues AS iss
                    LEFT JOIN issue_orgdata AS iod ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
                    LEFT JOIN issue_fields AS ixf ON (ixf.issue_id = iss.id AND ixf.fk_id = 65)
                    INNER JOIN issue_fields AS ixf_1 ON (ixf_1.issue_id = iss.id AND ixf_1.fk_id = 66)
                    LEFT JOIN data_airports AS airport ON (ixf_1.int_value = airport.id)
                    WHERE iss.type_id = $type_id
                      AND ixf.value = 'prel'
                     $where_status $where_date $where_airline
                    GROUP BY airport.IATA
                    ORDER BY claim_count DESC
                    LIMIT 10";
        $r = MVC_Core::$db-> query($q);
        return $r;
    }
    public function getPreliminaryClaimTopDestinationData($type_id,$org_id,$where_status,$where_date ,$where_airline){
        $q = "SELECT 'destination' AS category,
                        airport.IATA AS airport_code,
                        COUNT(*) AS claim_count
                    FROM issues AS iss
                    LEFT JOIN issue_orgdata AS iod ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
                    LEFT JOIN issue_fields AS ixf ON (ixf.issue_id = iss.id AND ixf.fk_id = 65)
                    INNER JOIN issue_fields AS ixf_1 ON (ixf_1.issue_id = iss.id AND ixf_1.fk_id = 67)
                    LEFT JOIN data_airports AS airport ON (ixf_1.int_value = airport.id)
                    WHERE iss.type_id = $type_id
                      AND ixf.value = 'prel'
                      $where_status $where_date $where_airline
                    GROUP BY airport.IATA
                    ORDER BY claim_count DESC
                    LIMIT 10";
        $r = MVC_Core::$db-> query($q);
        return $r;
    }
     public function getFormalClaimTopOriginData($type_id,$org_id,$where_status,$where_date ,$where_airline){
        $q = "SELECT
        'origin' AS category,
        airport.IATA AS airport_code,
        COUNT(*) AS claim_count
    FROM issues AS iss
    LEFT JOIN issue_orgdata AS iod ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
    LEFT JOIN issue_fields AS ixf ON (ixf.issue_id = iss.id AND ixf.fk_id = 65)
    INNER JOIN issue_fields AS ixf_1 ON (ixf_1.issue_id = iss.id AND ixf_1.fk_id = 66)
    LEFT JOIN data_airports AS airport ON (ixf_1.int_value = airport.id)
    WHERE iss.type_id = $type_id
      AND ixf.value = 'fpr'
     $where_status $where_date $where_airline
    GROUP BY airport.IATA
    ORDER BY claim_count DESC
    LIMIT 10";
        $r = MVC_Core::$db-> query($q);
        return $r;
    }
    public function getFormalClaimTopDestinationData($type_id,$org_id,$where_status,$where_date, $where_airline){
        $q = "SELECT
        'destination' AS category,
        airport.IATA AS airport_code,
        COUNT(*) AS claim_count
    FROM issues AS iss
    LEFT JOIN issue_orgdata AS iod ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
    LEFT JOIN issue_fields AS ixf ON (ixf.issue_id = iss.id AND ixf.fk_id = 65)
    INNER JOIN issue_fields AS ixf_1 ON (ixf_1.issue_id = iss.id AND ixf_1.fk_id = 67)
    LEFT JOIN data_airports AS airport ON (ixf_1.int_value = airport.id)
    WHERE iss.type_id = $type_id
      AND ixf.value = 'fpr'
      $where_status $where_date $where_airline 
    GROUP BY airport.IATA
    ORDER BY claim_count DESC
    LIMIT 10";
        $r = MVC_Core::$db-> query($q);
        return $r;
    }
    public function getTotalPreActiveRecordEveryMonth($type_id,$org_id,$where_status,$where_date, $where_airline){
        $q = "SELECT 
        COUNT(*) AS `count`,
        MONTHNAME(FROM_UNIXTIME(iod.linktime)) AS `month`,
        MONTH(FROM_UNIXTIME(iod.linktime)) AS `month_num`
    FROM issues AS iss    
    LEFT JOIN issue_orgdata AS iod 
    ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
    LEFT JOIN issue_fields AS ixf 
    ON (ixf.issue_id = iss.id AND ixf.fk_id = 65) 
    WHERE 
        iss.type_id = $type_id
        AND ixf.value = 'prel'
        $where_status $where_date $where_airline
    GROUP BY month_num
    ORDER BY month_num ASC";
        $r = MVC_Core::$db-> query($q);
        return $r;
    }
    public function getFormalActiveRecordEveryMonth($type_id,$org_id,$where_status,$where_date, $where_airline){
        $q = "SELECT 
        COUNT(*) AS `count`,
        MONTHNAME(FROM_UNIXTIME(iod.linktime)) AS `month`,
        MONTH(FROM_UNIXTIME(iod.linktime)) AS `month_num`
    FROM issues AS iss
    LEFT JOIN issue_orgdata AS iod 
    ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
    LEFT JOIN issue_fields AS ixf 
    ON (ixf.issue_id = iss.id AND ixf.fk_id = 65) 
    WHERE 
        iss.type_id = $type_id
        AND ixf.value = 'fpr'
        $where_status $where_date $where_airline
    GROUP BY month_num
    ORDER BY month_num ASC";
        $r = MVC_Core::$db-> query($q);
        return $r;
    }
    public function getClaimCombinedCountSqlResult($type_id,$org_id,$where_status,$where_date, $where_airline){
        $q = "SELECT 
                COUNT(CASE WHEN ixf.value = 'prel' THEN 1 END) AS prel_count,
                COUNT(CASE WHEN ixf.value = 'fpr' THEN 1 END) AS fpr_count
              FROM issues AS iss
              LEFT JOIN issue_orgdata AS iod 
                ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
              LEFT JOIN issue_fields AS ixf 
                ON (ixf.issue_id = iss.id AND ixf.fk_id = 65)
              WHERE iss.type_id = $type_id
                $where_status $where_date $where_airline";
        $r = MVC_Core::$db-> query($q);
        return $r;
    }
    public function getTotalOpenRecordsData($type_id, $org_id, $where_status, $where_date, $where_airline) {
    $q = "SELECT 
    MONTH(FROM_UNIXTIME(iod.linktime)) AS month_num,
    MONTHNAME(FROM_UNIXTIME(iod.linktime)) AS month_name,
    isf.value AS field_value,
    COUNT(DISTINCT iss.id) AS record_count
FROM issues AS iss
JOIN issue_orgdata AS iod 
            ON iod.issue_id = iss.id 
            AND iod.org_id = 5
LEFT JOIN issue_fields AS isf 
    ON isf.issue_id = iss.id 
    AND isf.fk_id = 65
WHERE
     iss.type_id = $type_id
            AND iod.process <> 'closed'
            AND iod.process <> 'hidden'
            $where_status $where_date $where_airline
GROUP BY 
    month_num,
    month_name,
    isf.value
ORDER BY month_name ASC;";

//     echo $q;

    $r = MVC_Core::$db->query($q);
    return $r;
}
public function getServiceLevelData($type_id, $org_id, $where_status, $where_date, $where_airline) {
//     $q = "SELECT COUNT(*) AS total_claims, ixf.int_value
//              FROM issues AS iss
//              JOIN issue_orgdata AS iod ON (iod.issue_id = iss.id AND iod.org_id = 9)
//              INNER JOIN issue_fields AS ixf ON ixf.issue_id = iss.id AND ixf.fk_id = 2821
//              WHERE iss.type_id = $type_id $where_status  $where_airline
//              GROUP BY ixf.int_value";
     
        $q = "SELECT COUNT(*) AS total_claims, ixf.int_value
              FROM issues AS iss
              JOIN issue_orgdata AS iod ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
              INNER JOIN issue_fields AS ixf ON ixf.issue_id = iss.id AND ixf.fk_id = 2821
              WHERE iss.type_id = $type_id $where_status $where_date $where_airline
              GROUP BY ixf.int_value";
        $r = MVC_Core::$db->query($q);
        return $r ? $r : array();
    }

    public function getClaimActivityData($type_id, $org_id, $where_status = '', $where_date = '', $where_airline = '') {
        $q = "SELECT COUNT(*) AS claim_count, iod.status_key AS status_key
              FROM issues AS iss
              LEFT JOIN issue_orgdata AS iod ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
              WHERE iss.type_id = $type_id $where_status $where_date $where_airline
              GROUP BY iod.status_key
              ORDER BY claim_count DESC";
        $r = MVC_Core::$db->query($q);
        return $r ? $r : array();
    }
    public function getTotalClosedRecordsData($type_id, $org_id, $where_status, $where_date, $where_airline) {
        $q = "SELECT 
            MONTH(FROM_UNIXTIME(iod.linktime)) AS month_num,
            MONTHNAME(FROM_UNIXTIME(iod.linktime)) AS month_name,
            isf.value AS field_value,
            COUNT(DISTINCT iss.id) AS record_count
            FROM issues AS iss
            JOIN issue_orgdata AS iod 
                        ON iod.issue_id = iss.id 
                        AND iod.org_id = 5
            LEFT JOIN issue_fields AS isf 
                ON isf.issue_id = iss.id 
                AND isf.fk_id = 65
            WHERE
                 iss.type_id = $type_id
                        AND iod.process = 'closed'
                        $where_status $where_date $where_airline
            GROUP BY 
                month_num,
                month_name,
                isf.value
            ORDER BY month_name ASC;";

//     echo $q;

        $r = MVC_Core::$db->query($q);
        return $r;
    }
    
    public function getFormalClaimActivityData($type_id, $org_id, $where_status = '', $where_date = '', $where_airline = '') {
        $q = "SELECT COUNT(*) AS claim_count, iod.status_key AS status_key
            FROM issues AS iss
            LEFT JOIN issue_orgdata AS iod ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
            LEFT JOIN issue_fields AS ixf_fpr ON (ixf_fpr.issue_id = iss.id AND ixf_fpr.fk_id = 65) 
            WHERE iss.type_id = $type_id
              AND ixf_fpr.value = 'fpr'  
              $where_status
              $where_date
              $where_airline
            GROUP BY iod.status_key
            ORDER BY claim_count DESC;";
        $r = MVC_Core::$db->query($q);
        return $r ? $r : array();
    }

    public function getTotalFormalLossDetails($type_id, $org_id, $where_status = '', $where_date = '', $where_airline = '') {
         $q = "SELECT 
    loss_type,
    COUNT(*) AS claim_count
FROM (
    SELECT 
        iss.id,
        CASE 
            WHEN ixf_2.int_value IS NOT NULL THEN 'Damage'
            WHEN ixf_3.int_value IS NOT NULL THEN 'Total Loss'
            WHEN ixf_4.int_value IS NOT NULL THEN 'Delay'
            WHEN ixf_5.int_value IS NOT NULL THEN 'Partial Loss'
            WHEN ixf_6.int_value IS NOT NULL THEN 'Pilferage'
        END AS loss_type
    FROM issues AS iss
    LEFT JOIN issue_orgdata AS iod 
        ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
    LEFT JOIN issue_fields AS ixf_fpr 
        ON (ixf_fpr.issue_id = iss.id AND ixf_fpr.fk_id = 65)
    LEFT JOIN issue_fields AS ixf_2 
        ON (ixf_2.issue_id = iss.id AND ixf_2.fk_id = 12)
    LEFT JOIN issue_fields AS ixf_3 
        ON (ixf_3.issue_id = iss.id AND ixf_3.fk_id = 128)
    LEFT JOIN issue_fields AS ixf_4 
        ON (ixf_4.issue_id = iss.id AND ixf_4.fk_id = 565)
    LEFT JOIN issue_fields AS ixf_5 
        ON (ixf_5.issue_id = iss.id AND ixf_5.fk_id = 956)
    LEFT JOIN issue_fields AS ixf_6 
        ON (ixf_6.issue_id = iss.id AND ixf_6.fk_id = 959)
    WHERE 
        iss.type_id = $type_id
        AND ixf_fpr.value = 'fpr'
        AND (
            ixf_2.int_value IS NOT NULL OR
            ixf_3.int_value IS NOT NULL OR
            ixf_4.int_value IS NOT NULL OR
            ixf_5.int_value IS NOT NULL OR
            ixf_6.int_value IS NOT NULL
        )
        
$where_status
              $where_date
              $where_airline
) AS loss_summary
WHERE loss_type IS NOT NULL
GROUP BY loss_type
ORDER BY claim_count DESC";
        $r = MVC_Core::$db->query($q);
        return $r ? $r : array();
    }
    public function getFormalSpecialHandlingCodes($type_id, $org_id, $where_status = '', $where_date = '', $where_airline = '') {
          $q = "SELECT expanded.special_code, COUNT(*) AS claim_count
FROM (
    SELECT 
        iss.id AS issue_id,
        TRIM(BOTH '\"' FROM SUBSTRING_INDEX(SUBSTRING_INDEX(ixf.value, '\"', n.n * 2), '\"', -1)) AS special_code
    FROM issues AS iss
    LEFT JOIN issue_fields AS ixf 
        ON ixf.issue_id = iss.id AND ixf.fk_id = 2141 AND ixf.value IS NOT NULL AND ixf.value <> '[]'
    LEFT JOIN issue_orgdata AS iod
        ON iod.issue_id = iss.id AND iod.org_id = 5
    CROSS JOIN (
        SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 
        UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
        UNION ALL SELECT 9 UNION ALL SELECT 10
    ) n
   WHERE (iod.process IS NULL OR iod.process NOT IN('closed','hidden'))
      $where_status
              $where_date
              $where_airline
) AS expanded
WHERE expanded.special_code NOT IN ('', '[', ']')
GROUP BY expanded.special_code
ORDER BY claim_count DESC
    ";
        $r = MVC_Core::$db->query($q);
        return $r ? $r : array();
    }
    
//    IWI Air Cargo claim chart page new design end
    
   
//    IWI chart for quality event module 07112025 start
    
    
/*
    public function QualityEventPerCategory($org_id, $rt_id, $params) {
        if (!is_array($params)) {
            // Default parameters - Show record statistics per month
            $params = array(
                'period' => "months",
                'subset' => 0
            );
        }
        // Start building the chart and data query
        // This function uses the organization specific data
        $q = QueryFactory::select()
                ->table(MVC_Core::$db->table('iss'), array("group_id", 'y_axis' => "COUNT(*)")) // We're counting records, no need for other fields
                ->where("`iss`.type_id = ?", $rt_id);

        $grouping = $this->mqy_grouping($q, $params);

        // Join IOD for organization and process filter
        $q->table(MVC_Core::$db->table('iod'), array("time" => $grouping), "left", "`iod`.issue_id = `iss`.id") // Not even sure if field name is needed?-> ta
                ->table('groups', array("group_name" => 'name'), "left", "`groups`.id = `iss`.group_id") // Not even sure if field name is needed?-> ta		
                ->where("`iod`.org_id = ?", $org_id)
                ->where("`iod`.process != 'hidden'")
                ->group($grouping . ", `iss`.group_id")
                ->order('MONTH(FROM_UNIXTIME(iod.linktime))', 'ASC');
        $this->ac_subset($q, $params['subset']);
//                echo $q;
        $data = MVC_Core::$db->query($q);
        if (!$data)
            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        $groups = array();
        $fieldCodes = array();
        $series = array();
        $source = array();
        $dataArr = array();

        foreach ($data as $row) {
            $dataArr[] = $row;
            $cleanName = preg_replace('/ \[event\]/i', '', $row["group_name"]);

            $groups[$row["group_id"]] = $cleanName;
        }


        $i = 1;
        foreach ($groups as $gid => $label) {
            $field = str_pad($i, 3, "0", STR_PAD_LEFT);

            $fieldCodes[$gid] = $field;

            $series[] = array(
                "field" => $field,
                "label" => $label
            );

            $i++;
        }


        foreach ($dataArr as $row) {

            $time = $row["time"];
            $gid = $row["group_id"];
            $value = $row["y_axis"];
            $code = $fieldCodes[$gid];

            if (!isset($source[$time])) {

                $source[$time] = array(
                    "Month" => $time
                );

                foreach ($fieldCodes as $fc) {
                    $source[$time][$fc] = 0;
                }
            }
            $source[$time][$code] = $value;
        }


        $source = array_values($source);

        $result = array(
            "series" => $series,
            "source" => $source
        );

        return $result;
    }
    */
    

    public function QualityEventCommodityType($org_id, $rt_id, $params) {
        if (!is_array($params)) {
            $params = array(
                'period' => "months",
                'subset' => 0
            );
        }

        $q = QueryFactory::select()
                ->table(MVC_Core::$db->table('iss'), array("group_id", 'y_axis' => "COUNT(*)"))
                ->where("`iss`.type_id = ?", $rt_id);

        $grouping = $this->mqy_grouping($q, $params);
       
        
        $timeLimit = $this->mqy_timeLimit($q, $params);
//    $timeLimit='AND iod.linktime > 1735686000';
//        $timeLimit = '';

//      echo  $q = "SELECT iss.group_id, $grouping AS time, SUBSTRING_INDEX(SUBSTRING_INDEX(csv.commodity_csv, ',', n.n), ',', -1) AS commodity_code, COUNT(*) AS y_axis FROM issues AS iss LEFT JOIN issue_orgdata AS iod ON iod.issue_id = iss.id LEFT JOIN ( SELECT issue_id, REPLACE(REPLACE(REPLACE(value, '[', ''), ']', ''), '\"', '') AS commodity_csv FROM issue_fields WHERE fk_id = 2008 ) AS csv ON csv.issue_id = iss.id JOIN ( SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 ) AS n WHERE iss.type_id = $rt_id  AND iod.org_id = $org_id $timeLimit AND csv.commodity_csv IS NOT NULL AND csv.commodity_csv != '' AND n.n <= 1 + LENGTH(csv.commodity_csv) - LENGTH(REPLACE(csv.commodity_csv, ',', '')) GROUP BY $grouping , iss.group_id, commodity_code ORDER BY MONTH(FROM_UNIXTIME(iod.linktime)) ASC";
        
        
        
        $q = "SELECT 
    iss.group_id,
    $grouping AS time,
    SUBSTRING_INDEX(
        SUBSTRING_INDEX(csv.commodity_csv, ',', n.n),
        ',', 
        -1
    ) AS commodity_code,
    COUNT(*) AS y_axis
FROM issues AS iss
 
LEFT JOIN issue_orgdata AS iod 
    ON iod.issue_id = iss.id
 
LEFT JOIN issue_fields 
    ON iss.id = issue_fields.issue_id 
    AND issue_fields.fk_id = 1228
 
LEFT JOIN (
    SELECT 
        issue_id,
        REPLACE(
            REPLACE(
                REPLACE(value, '[', ''), 
            ']', ''), 
        '\"', ''
        ) AS commodity_csv
    FROM issue_fields 
    WHERE fk_id = 2008
) AS csv 
    ON csv.issue_id = iss.id
 
JOIN (
    SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
    UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8
) AS n
 
WHERE iss.type_id = $rt_id
  AND iod.org_id = $org_id
  AND iod.process != 'hidden'
  $timeLimit
  AND csv.commodity_csv IS NOT NULL
  AND csv.commodity_csv != ''
  AND n.n <= 1 + LENGTH(csv.commodity_csv) 
           - LENGTH(REPLACE(csv.commodity_csv, ',', ''))
GROUP BY 
    $grouping,
    iss.group_id,
    commodity_code
ORDER BY 
    issue_fields.int_value ASC";

        $q_2 = QueryFactory::select()
                ->table('issue_xfdef', array('commodity_type' => "cd"))
                ->where("`issue_xfdef`.id = ?", '2008');
        $commodity_type_data = MVC_Core::$db->query($q_2)->current();
        $commodity_types_result = array();

        if (!empty($commodity_type_data['commodity_type'])) {
            $commodity_types = $commodity_type_data['commodity_type'];
            $matches = array();
            preg_match_all('/"\\#([^:]+):\\s*([^"]+)"/', $commodity_types, $matches, PREG_SET_ORDER);

            foreach ($matches as $m) {
                $key = trim($m[1]);
                $value = trim($m[2]);
                $commodity_types_result[$key] = $value;
            }

            if (empty($commodity_types_result)) {
                $commodity_types = trim($commodity_types, "[] \n\r\t");
                $lines = preg_split('/\r\n|\r|\n|,(?=\s*\"#)/', $commodity_types);
                foreach ($lines as $line) {
                    $line = trim($line, " \t\n\r\" ,");
                    if (!$line)
                        continue;
                    if (strpos($line, ':') === false)
                        continue;
                    list($k, $v) = explode(':', $line, 2);
                    $k = ltrim(trim($k), '# ');
                    $v = trim($v);
                    $commodity_types_result[$k] = $v;
                }
            }
        }
        $data = MVC_Core::$db->query($q);
        if (!$data) {
            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
        }

        $result_counts = array();
        $time_counts = array();
        foreach ($data as $row) {
            if (!isset($row['commodity_code']) || !isset($row['time']))
                continue;

    $time = isset($row['time']) ? $row['time'] : 'Unknown';

            
            $code = $row['commodity_code'];
            $y = isset($row['y_axis']) ? (int) $row['y_axis'] : 0;

            if (!isset($time_counts[$time])) { 
                $time_counts[$time] = array();
                foreach ($commodity_types_result as $c => $label) {
                    $time_counts[$time][$c] = 0;
                }
            }
            if (!isset($time_counts[$time][$code])) {
                $time_counts[$time][$code] = 0;
            }
            $time_counts[$time][$code] += $y;
        }

        $series = array();
        foreach ($commodity_types_result as $code => $label) {
            $series[] = array(
                "field" => $code,
                "label" => $label
            );
        }
        $source = array();
        foreach ($time_counts as $time => $counts) {
            
            if($params['period']=='Quarters'){
                $entry = array("Quarter" => $time);
            }else if($params['period']=='Years'){
                $entry = array("Year" => $time);
            }else{
                $entry = array("Month" => $time);
            }
            
            foreach ($counts as $code => $value) {
                $entry[$code] = $value;
            }
            $source[] = $entry;
        }

        $records_per_comm = array(
            "series" => $series,
            "source" => $source
        );

        return $records_per_comm;
    }
    
    public function QualityEventIrregularityCode($org_id, $rt_id, $params) {
        if (!is_array($params)) {
            $params = array(
                'period' => "months",
                'subset' => 0
            );
        }

        $q = QueryFactory::select()
                ->table(MVC_Core::$db->table('iss'), array("group_id", 'y_axis' => "COUNT(*)"))
                ->where("`iss`.type_id = ?", $rt_id);

        $grouping = $this->mqy_grouping($q, $params);
        $timeLimit = $this->mqy_timeLimit($q, $params);
//    $timeLimit='AND iod.linktime > 1735686000';
//        $timeLimit = '';
//         $q="SELECT iss.group_id, $grouping AS time, SUBSTRING_INDEX( SUBSTRING_INDEX(ixf_clean.csv_value, ',', n.n), ',', -1 ) AS irr_code, COUNT(*) AS y_axis FROM issues AS iss "
//                . "LEFT JOIN issue_orgdata AS iod ON iod.issue_id = iss.id "
//                . "LEFT JOIN ( SELECT issue_id, REPLACE(REPLACE(REPLACE(value, '[', ''), ']', ''), '\"', '') AS csv_value FROM issue_fields WHERE fk_id = 3129 ) AS ixf_clean ON ixf_clean.issue_id = iss.id  JOIN ( SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 ) AS n  WHERE iss.type_id = $rt_id  AND iod.org_id = $org_id $timeLimit   And ixf_clean.csv_value IS NOT NULL AND ixf_clean.csv_value != '' AND n.n <= 1 + LENGTH(ixf_clean.csv_value) - LENGTH(REPLACE(ixf_clean.csv_value, ',', '')) GROUP BY $grouping , irr_code ORDER BY MONTH(FROM_UNIXTIME(iod.linktime)) ASC, irr_code ASC";
         
         
         $q="SELECT 
        iss.group_id,
        $grouping AS time,
        MONTH(FROM_UNIXTIME(issue_fields.int_value)) AS x_axis,
        SUBSTRING_INDEX(
            SUBSTRING_INDEX(ixf_clean.csv_value, ',', n.n),
            ',', 
            -1
        ) AS irr_code,
        COUNT(*) AS y_axis
    FROM issues AS iss
 
    LEFT JOIN issue_orgdata AS iod 
        ON iod.issue_id = iss.id
 
    LEFT JOIN issue_fields 
        ON iss.id = issue_fields.issue_id 
        AND issue_fields.fk_id = 1228
 
    LEFT JOIN (
        SELECT 
            issue_id,
            REPLACE(
                REPLACE(
                    REPLACE(value, '[', ''), 
                ']', ''), 
            '\"', ''
            ) AS csv_value
        FROM issue_fields 
        WHERE fk_id = 3129
    ) AS ixf_clean 
        ON ixf_clean.issue_id = iss.id
 
    JOIN (
        SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
        UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8
    ) AS n
 
    WHERE iss.type_id = $rt_id
      AND iod.org_id = $org_id
      AND iod.process != 'hidden'
      $timeLimit
      AND ixf_clean.csv_value IS NOT NULL
      AND ixf_clean.csv_value != ''
      AND n.n <= 1 + LENGTH(ixf_clean.csv_value)
               - LENGTH(REPLACE(ixf_clean.csv_value, ',', ''))
 
    GROUP BY 
        $grouping,
        iss.group_id,
        irr_code
    ORDER BY 
        issue_fields.int_value ASC,
        irr_code ASC";
         
         
         
         
         
// echo $q;
        $data = MVC_Core::$db->query($q);
        
        
         $q_2 = QueryFactory::select()
                ->table('issue_xfdef', array('irr_code' => "cd"))
                ->where("`issue_xfdef`.id = ?", '3129');
        $irr_code_data = MVC_Core::$db->query($q_2)->current();
        $irr_codes_result = array();

        if (!empty($irr_code_data['irr_code'])) {
            $irr_codes = $irr_code_data['irr_code'];
            $matches = array();
            preg_match_all('/"\\#([^:]+):\\s*([^"]+)"/', $irr_codes, $matches, PREG_SET_ORDER);

            foreach ($matches as $m) {
                $key = trim($m[1]);
                $value = trim($m[2]);
                $irr_codes_result[$key] = $value;
            }

            if (empty($irr_codes_result)) {
                $irr_codes = trim($irr_codes, "[] \n\r\t");
                $lines = preg_split('/\r\n|\r|\n|,(?=\s*\"#)/', $irr_codes);
                foreach ($lines as $line) {
                    $line = trim($line, " \t\n\r\" ,");
                    if (!$line)
                        continue;
                    if (strpos($line, ':') === false)
                        continue;
                    list($k, $v) = explode(':', $line, 2);
                    $k = ltrim(trim($k), '# ');
                    $v = trim($v);
                    $irr_codes_result[$k] = $v;
                }
            }
        }
        
//        print_r($irr_codes_result);
        
        if (!$data) {
            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
        }
        
        
        
        $result_counts = array();
        $time_counts = array();
        foreach ($data as $row) {
            if (!isset($row['irr_code']) || !isset($row['time']))
                continue;

            $time = $row['time'];
            $code = $row['irr_code'];
            $y = isset($row['y_axis']) ? (int) $row['y_axis'] : 0;

            if (!isset($time_counts[$time])) {
                $time_counts[$time] = array();
                foreach ($irr_codes_result as $c => $label) {
                    $time_counts[$time][$c] = 0;
                }
            }
            if (!isset($time_counts[$time][$code])) {
                $time_counts[$time][$code] = 0;
            }
            $time_counts[$time][$code] += $y;
        }

        $series = array();
        foreach ($irr_codes_result as $code => $label) {
            $series[] = array(
                "field" => $code,
                "label" => $label
            );
        }
        $source = array();
        foreach ($time_counts as $time => $counts) {
           if($params['period']=='Quarters'){
                $entry = array("Quarter" => $time);
            }else if($params['period']=='Years'){
                $entry = array("Year" => $time);
            }else{
                $entry = array("Month" => $time);
            }
            foreach ($counts as $code => $value) {
                $entry[$code] = $value;
            }
            $source[] = $entry;
        }

        $records_per_irr = array(
            "series" => $series,
            "source" => $source
        );
      return $records_per_irr;
    }

    protected function mqy_grouping(QueryFactory &$q, $params) {
//        print_r($params);
        switch (strtolower($params['period'])) {
            // Shows last 12 months, ending with previous month
            case 'months':
                $grouping = "DATE_FORMAT(FROM_UNIXTIME(issue_fields.int_value), '%b')";
//                $q->where("issue_fields.int_value > ?", strtotime(date("Y-m-t") . " +1 day -1 year")); // Show all exactly from 12 months before end of this month
                break;

            // Shows quarters for current year
            case 'quarters':
                $grouping = " QUARTER(FROM_UNIXTIME(issue_fields.int_value)) ";
//                $q->where("YEAR(FROM_UNIXTIME(issue_fields.int_value)) = YEAR(CURDATE())"); // Show data from this year
                break;

            // Show five years ; TODO: It should be more configurable
            case 'years':
                $grouping = "YEAR(FROM_UNIXTIME(issue_fields.int_value))";
                break;

            default:
                return MVC_Core::$debug->dump("Invalid parameter: Period: {$params['period']}", __METHOD__, null, "error");
        }
        return $grouping;
    }

    protected function mqy_timeLimit(QueryFactory &$q, $params) {
//        print_r($params);
        switch (strtolower($params['period'])) {
            // Shows last 12 months, ending with previous month
            case 'months':
                $timeLimit = "AND issue_fields.int_value > ".strtotime(date("Y-m-t") . " +1 day -1 year");
                break;

            // Shows quarters for current year
            case 'quarters':
                $timeLimit = "AND YEAR(FROM_UNIXTIME(issue_fields.int_value)) = YEAR(CURDATE()) ";
                break;

            // Show five years ; TODO: It should be more configurable
            case 'years':
                $timeLimit = "";
                break;

            default:
                return MVC_Core::$debug->dump("Invalid parameter: Period: {$params['period']}", __METHOD__, null, "error");
        }
        return $timeLimit;
    }

    protected function ac_subset(QueryFactory &$q, $subset) {
        switch ($subset) {
            case 0: // All
                // Nothing to do here, all non-hidden are already counted
                break;
            case 1: // Active
                $q->where("`iod`.process != 'closed'");
                break;
            case 2: // Closed
                $q->where("`iod`.process = 'closed'");
                break;
            default:
                return MVC_Core::$debug->dump("Invalid parameter - Subset: {$subset}", __METHOD__, null, "error");
        }
    }

//    IWI chart for quality event module 07112025 end
        
       
//     IWI Aircargoclaim chart page update 12012026 start

    public function getPreClaimDetails($param) {
        $where = $param['where'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];
//
//        $sql = "SELECT DATE_FORMAT(FROM_UNIXTIME(iod.linktime), '%b') AS month, SUM(CASE WHEN iod.process IN ('closed','hidden') THEN 1 ELSE 0 END) AS closed_count, SUM(CASE WHEN iod.process NOT IN ('closed','hidden') THEN 1 ELSE 0 END) AS opened_count FROM `issues` AS iss RIGHT JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1) INNER JOIN issue_fields pf ON (pf.issue_id = iss.id AND pf.fk_id = 65 AND pf.value = 'prel') WHERE `iss`.type_id = $type_id $where GROUP BY month ORDER BY `iss`.id DESC";
        $sql = "SELECT COUNT(DISTINCT iss.id) AS opened_count, COUNT(DISTINCT CASE WHEN iod.process = 'closed' THEN iss.id END) AS closed_count, COUNT(DISTINCT CASE WHEN iod.process NOT IN ('closed', 'hidden') THEN iss.id END) AS opened_count FROM issues AS iss LEFT JOIN groups AS grp ON iss.group_id = grp.id LEFT JOIN comm_contacts AS subm ON iss.submit_id = subm.id RIGHT JOIN issue_orgdata AS iod ON ( iss.id = iod.issue_id AND iod.org_id = $org_id AND iod.is_enabled = 1 ) LEFT JOIN users AS usr ON iod.assigned_to = usr.id LEFT JOIN issue_fields AS xf_pstage ON ( xf_pstage.issue_id = iss.id AND xf_pstage.fk_id = 65 ) WHERE ( iss.master_org_id = $org_id OR FIND_IN_SET($org_id, iss.shared_org_ids) ) AND xf_pstage.value = 'prel' AND iss.type_id = $type_id";

        $data = MVC_Core::$db->query($sql);

        return $data ? $data : array();
    }

    public function getFormalClaimDetails($param) {
        $where = $param['where'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];

//        $sql = "SELECT DATE_FORMAT(FROM_UNIXTIME(iod.linktime), '%b') AS month, SUM(CASE WHEN iod.process IN ('closed','hidden') THEN 1 ELSE 0 END) AS closed_count, SUM(CASE WHEN iod.process NOT IN ('closed','hidden') THEN 1 ELSE 0 END) AS opened_count FROM `issues` AS iss RIGHT JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1) INNER JOIN issue_fields pf ON (pf.issue_id = iss.id AND pf.fk_id = 65 AND pf.value = 'fpr') WHERE `iss`.type_id = $type_id $where GROUP BY month ORDER BY `iss`.id DESC";
        $sql = "
SELECT 
    DATE_FORMAT(FROM_UNIXTIME(iod.linktime), '%b') AS month,

    COUNT(DISTINCT CASE
        WHEN iod.process IN ('closed', 'hidden')
        THEN iss.id
    END) AS closed_count,

    COUNT(DISTINCT CASE
        WHEN iod.process NOT IN ('closed', 'hidden')
        THEN iss.id
    END) AS opened_count,

    COUNT(DISTINCT iss.id) AS total_count

FROM `issues` AS iss

RIGHT JOIN `issue_orgdata` AS iod
    ON (
        `iss`.id = `iod`.issue_id
        AND `iod`.org_id = $org_id
        AND `iod`.is_enabled = 1
    )

INNER JOIN `issue_fields` pf
    ON (
        pf.issue_id = iss.id
        AND pf.fk_id = 65
        AND pf.value = 'fpr'
    )

WHERE `iss`.type_id = $type_id
$where

GROUP BY DATE_FORMAT(FROM_UNIXTIME(iod.linktime), '%b')

ORDER BY MIN(iod.linktime) DESC
";

        $data = MVC_Core::$db->query($sql);

        return $data ? $data : array();
    }

    public function getFormalClaimTop_5_OriginData($param) {
        $where = $param['where'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];

        $q = "SELECT
    airport1.IATA AS origin,
    COUNT(DISTINCT iss.id) AS y_axis,

    SUM(CASE WHEN ixf_2.int_value IS NOT NULL THEN 1 ELSE 0 END) AS damage_pieces,
    SUM(CASE WHEN ixf_3.int_value IS NOT NULL THEN 1 ELSE 0 END) AS total_loss_pieces,
    SUM(CASE WHEN ixf_4.int_value IS NOT NULL THEN 1 ELSE 0 END) AS delay_pieces,
    SUM(CASE WHEN ixf_5.int_value IS NOT NULL THEN 1 ELSE 0 END) AS partial_loss_pieces,
    SUM(CASE WHEN ixf_6.int_value IS NOT NULL THEN 1 ELSE 0 END) AS pilferage_pieces
	 FROM `issues` AS iss
	 RIGHT JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1 AND iod.process NOT IN ('closed','hidden'))
     LEFT JOIN issue_fields ixf_1
    ON ixf_1.issue_id = iss.id
   AND ixf_1.fk_id = 66

LEFT JOIN issue_fields ixf_2
    ON ixf_2.issue_id = iss.id
   AND ixf_2.fk_id = 12

LEFT JOIN issue_fields ixf_3
    ON ixf_3.issue_id = iss.id
   AND ixf_3.fk_id = 128

LEFT JOIN issue_fields ixf_4
    ON ixf_4.issue_id = iss.id
   AND ixf_4.fk_id = 565

LEFT JOIN issue_fields ixf_5
    ON ixf_5.issue_id = iss.id
   AND ixf_5.fk_id = 956

LEFT JOIN issue_fields ixf_6
    ON ixf_6.issue_id = iss.id
   AND ixf_6.fk_id = 959

LEFT JOIN data_airports airport1
    ON airport1.id = ixf_1.int_value
	 WHERE  `iss`.type_id = $type_id
         $where GROUP BY origin
	 ORDER BY `iss`.id DESC LIMIT 5";
        $r = MVC_Core::$db->query($q);
        return $r;
    }

    public function getFormalClaimTop_5_DestinationData($param) {
        $where = $param['where'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];
        $q = "SELECT
    COUNT(DISTINCT iss.id) AS y_axis,
    airport1.IATA AS destination,

    SUM(CASE WHEN ixf_2.int_value IS NOT NULL THEN 1 ELSE 0 END) AS damage_pieces,
    SUM(CASE WHEN ixf_3.int_value IS NOT NULL THEN 1 ELSE 0 END) AS total_loss_pieces,
    SUM(CASE WHEN ixf_4.int_value IS NOT NULL THEN 1 ELSE 0 END) AS delay_pieces,
    SUM(CASE WHEN ixf_5.int_value IS NOT NULL THEN 1 ELSE 0 END) AS partial_loss_pieces,
    SUM(CASE WHEN ixf_6.int_value IS NOT NULL THEN 1 ELSE 0 END) AS pilferage_pieces
	 FROM `issues` AS iss
	 RIGHT JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1 AND iod.process NOT IN ('closed','hidden'))
     LEFT JOIN issue_fields ixf_1
    ON ixf_1.issue_id = iss.id
   AND ixf_1.fk_id = 67

LEFT JOIN issue_fields ixf_2
    ON ixf_2.issue_id = iss.id
   AND ixf_2.fk_id = 12

LEFT JOIN issue_fields ixf_3
    ON ixf_3.issue_id = iss.id
   AND ixf_3.fk_id = 128

LEFT JOIN issue_fields ixf_4
    ON ixf_4.issue_id = iss.id
   AND ixf_4.fk_id = 565

LEFT JOIN issue_fields ixf_5
    ON ixf_5.issue_id = iss.id
   AND ixf_5.fk_id = 956

LEFT JOIN issue_fields ixf_6
    ON ixf_6.issue_id = iss.id
   AND ixf_6.fk_id = 959

LEFT JOIN data_airports AS airport1
    ON airport1.id = ixf_1.int_value

WHERE iss.type_id = $type_id
  $where
 
GROUP BY destination
ORDER BY y_axis DESC
LIMIT 5";
        $r = MVC_Core::$db->query($q);
        return $r;
    }

    public function getTotalAmountFormalClaimedData($param) {
        $where = $param['where'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];
        $q = "SELECT 
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y') AS year,
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%b') AS month,
    SUM(ixf.value) AS amount
FROM issues AS iss
RIGHT JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1) 
    INNER JOIN issue_fields pf ON (pf.issue_id = iss.id AND pf.fk_id = 65 AND pf.value = 'fpr')
LEFT JOIN issue_fields AS ixf
    ON ixf.issue_id = iss.id
   AND ixf.fk_id = 2671

WHERE iss.type_id = $type_id
  $where

GROUP BY DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%b')
ORDER BY `iss`.id DESC";
        $r = MVC_Core::$db->query($q);
        return $r;
    }

    public function getUltimateAmountProvisionData($param) {
        $where = $param['where'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];
        $q = "SELECT 
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%b') AS month,
    SUM(ixf.value) AS provision_usd_amount
FROM issues AS iss

RIGHT JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1) 
    INNER JOIN issue_fields pf ON (pf.issue_id = iss.id AND pf.fk_id = 65 AND pf.value = 'fpr')

LEFT JOIN issue_fields AS ixf
    ON ixf.issue_id = iss.id
   AND ixf.fk_id = 2834

WHERE iss.type_id = $type_id
  $where
  AND ixf.int_value IS NOT NULL

GROUP BY month
ORDER BY `iss`.id DESC";

        $r = MVC_Core::$db->query($q);
        return $r;
    }

    public function getActiveFormalClaimsDashboardTotals($param) {
        $where = $param['where'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];

        $q_assignment = " SELECT  COUNT(DISTINCT iss.id) AS total_records,
                    SUM( CASE  WHEN iod.assigned_to IS NOT NULL  AND iod.assigned_to <> ''  THEN 1 ELSE 0  END ) AS assigned_count, 
                    SUM(  CASE  WHEN iod.assigned_to IS NULL OR iod.assigned_to = '' THEN 1 ELSE 0  END ) AS unassigned_count 
                    FROM issues AS iss 
                    INNER JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1 AND `iod`.process NOT IN ('closed', 'hidden')) "
                . "INNER JOIN issue_fields pf  ON ( pf.issue_id = iss.id AND pf.fk_id = 65 AND pf.value = 'fpr') "
                . "INNER JOIN issue_fields iss_awb ON(iss_awb.issue_id=iss.id AND iss_awb.fk_id=3)"
                . "WHERE iss.type_id = $type_id "
                . "$where ";

        $r_assignment = MVC_Core::$db->query($q_assignment)->current();

        $result['total_records'] = $r_assignment['total_records'];
        $result['assigned_count'] = $r_assignment['assigned_count'];
        $result['unassigned_count'] = $r_assignment['unassigned_count'];

        $q_claimed_total_amount = "SELECT SUM(ixf.value) AS claimed_total_amount ,COUNT(DISTINCT iss.id) AS claimed_total_records
           FROM issues AS iss 
           INNER JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1 AND `iod`.process NOT IN ('closed', 'hidden')) 
           INNER JOIN issue_fields pf ON (pf.issue_id = iss.id AND pf.fk_id = 65 AND pf.value = 'fpr') 
           INNER JOIN issue_fields iss_awb ON(iss_awb.issue_id=iss.id AND iss_awb.fk_id=3)
           LEFT JOIN issue_fields AS ixf ON ixf.issue_id = iss.id AND ixf.fk_id = 2671 
           WHERE iss.type_id = $type_id 
           $where
           HAVING SUM(ixf.value) IS NOT NULL";

        $r_claimed_total_amount = MVC_Core::$db->query($q_claimed_total_amount)->current();

        $result['totalAmountFormalClaimed_total_amount'] = number_format($r_claimed_total_amount['claimed_total_amount'], 2, '.', ',');
        $result['totalAmountFormalClaimed_total_records'] = $r_claimed_total_amount['claimed_total_records'];

        $q_suggested_total_amount = "SELECT SUM(ixf.value) AS suggested_total_amount, COUNT(DISTINCT iss.id) AS suggested_total_records "
                . "FROM issues AS iss "
                . "INNER JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1 AND `iod`.process NOT IN ('closed', 'hidden')) "
                . "INNER JOIN issue_fields pf ON (pf.issue_id = iss.id AND pf.fk_id = 65 AND pf.value = 'fpr') "
                . "INNER JOIN issue_fields iss_awb ON(iss_awb.issue_id=iss.id AND iss_awb.fk_id=3)"
                . "LEFT JOIN issue_fields AS ixf ON ixf.issue_id = iss.id AND ixf.fk_id = 2836 "
                . "WHERE iss.type_id = $type_id "
                . "$where "
                . "HAVING SUM(ixf.value) IS NOT NULL";

        $r_suggested_total_amount = MVC_Core::$db->query($q_suggested_total_amount)->current();

        $result['totalSuggestedAmount_total_amount'] = number_format($r_suggested_total_amount['suggested_total_amount'], 2, '.', ',');
        $result['totalSuggestedAmount_total_record'] = $_suggested_total_amount['suggested_total_records'];

        $q_provision_total_amount = "SELECT SUM(ixf.value) AS provision_total_amount, COUNT(DISTINCT iss.id) AS provision_total_records "
                . "FROM issues AS iss "
                . "INNER JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1 AND `iod`.process NOT IN ('closed', 'hidden')) "
                . "LEFT JOIN issue_fields AS ixf ON ixf.issue_id = iss.id AND ixf.fk_id = 2834 "
                . "INNER JOIN issue_fields iss_awb ON(iss_awb.issue_id=iss.id AND iss_awb.fk_id=3)"
                . "WHERE iss.type_id = $type_id "
                . "$where "
                . "HAVING SUM(ixf.value) IS NOT NULL";

        $r_provision_total_amount = MVC_Core::$db->query($q_provision_total_amount)->current();

        $result['totalProvisionAmount_total_amount'] = number_format($r_provision_total_amount['provision_total_amount'], 2, '.', ',');
        $result['totalProvisionAmount_total_record'] = $_provision_total_amount['provision_total_records'];

//print_r($r);
        return $result;
    }

    public function getActiveFormalClaimsClaimedCount($param) {
        $where = $param['where'];
        $where_lime_limit = $param['where_lime_limit'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];
        $get_data_from = (int) $param['get_data_from'];
        $q = "SELECT 
     DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y') AS year,
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y-%b') AS time,
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%b') AS month,
    COUNT(DISTINCT iss.id) AS total_records,
    SUM(CASE WHEN iod.process IN('closed','hidden')THEN 1 ELSE 0 END)AS closed_count,
    SUM(CASE WHEN iod.process NOT IN('closed','hidden')THEN 1 ELSE 0 END)AS opened_count
FROM issues AS iss
INNER JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1 ) 
    INNER JOIN issue_fields pf ON (pf.issue_id = iss.id AND pf.fk_id = 65 AND pf.value = 'fpr')
    INNER JOIN issue_fields iss_awb ON(iss_awb.issue_id=iss.id AND iss_awb.fk_id=3)
     WHERE iss.type_id = $type_id
  $where

  $where_lime_limit
GROUP BY DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y-%m')


ORDER BY  year DESC,
    MONTH(FROM_UNIXTIME(iss.ctime)) ASC";
        $r = MVC_Core::$db->query($q);
        return $r;
    }

    public function getActiveFormalClaimsClaimedAmountData($param) {
        $where = $param['where'];
        $where_lime_limit = $param['where_lime_limit'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];
        $get_data_from = (int) $param['get_data_from'];
        $q = "SELECT 
     DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y') AS year,
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y-%b') AS time,
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%b') AS month,
    SUM(ixf.value) AS amount,
    COUNT(DISTINCT iss.id) AS total_records
FROM issues AS iss
INNER JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1 AND `iod`.process NOT IN ('closed', 'hidden')) 
    INNER JOIN issue_fields pf ON (pf.issue_id = iss.id AND pf.fk_id = 65 AND pf.value = 'fpr')
    INNER JOIN issue_fields iss_awb ON(iss_awb.issue_id=iss.id AND iss_awb.fk_id=3)
LEFT JOIN issue_fields AS ixf
    ON ixf.issue_id = iss.id
   AND ixf.fk_id = 2671

WHERE iss.type_id = $type_id
  $where
$where_lime_limit
GROUP BY DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y-%m')

HAVING SUM(ixf.value) IS NOT NULL

ORDER BY  year DESC,
    MONTH(FROM_UNIXTIME(iss.ctime)) ASC";
        $r = MVC_Core::$db->query($q);
        return $r;
    }

    public function getTotalSuggestedAmountData($param) {
        $where = $param['where'];
        $where_lime_limit = $param['where_lime_limit'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];
//        $org_id = 114;
        $get_data_from = (int) $param['get_data_from'];
        $q = "SELECT 
     DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y') AS year,
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y-%b') AS time,
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%b') AS month,
    SUM(ixf.value) AS amount,
    COUNT(DISTINCT iss.id) AS total_records
FROM issues AS iss
INNER JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1 AND `iod`.process NOT IN ('closed', 'hidden')) 
    INNER JOIN issue_fields pf ON (pf.issue_id = iss.id AND pf.fk_id = 65 AND pf.value = 'fpr')
    INNER JOIN issue_fields iss_awb ON(iss_awb.issue_id=iss.id AND iss_awb.fk_id=3)
LEFT JOIN issue_fields AS ixf
    ON ixf.issue_id = iss.id
   AND ixf.fk_id = 2836

WHERE iss.type_id = $type_id
  $where
$where_lime_limit
GROUP BY DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y-%m')

HAVING SUM(ixf.value) IS NOT NULL

ORDER BY year DESC,
    MONTH(FROM_UNIXTIME(iss.ctime)) ASC";
        $r = MVC_Core::$db->query($q);
        return $r;
    }

    public function getTotalProvisionAmountData($param) {
        $where = $param['where'];
        $where_lime_limit = $param['where_lime_limit'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];
//        $org_id = 114;
        $get_data_from = (int) $param['get_data_from'];

        $q = "SELECT 
     DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y') AS year,
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y-%b') AS time,
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%b') AS month,
    SUM(ixf.value) AS amount,
    COUNT(DISTINCT iss.id) AS total_records
FROM issues AS iss
INNER JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1 AND `iod`.process NOT IN ('closed', 'hidden')) 
LEFT JOIN issue_fields AS ixf
    ON ixf.issue_id = iss.id
   AND ixf.fk_id = 2834
   INNER JOIN issue_fields iss_awb ON(iss_awb.issue_id=iss.id AND iss_awb.fk_id=3)

WHERE iss.type_id = $type_id
  $where
$where_lime_limit
GROUP BY DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y-%m')

HAVING SUM(ixf.value) IS NOT NULL

ORDER BY year DESC,
    MONTH(FROM_UNIXTIME(iss.ctime)) ASC";
        $r = MVC_Core::$db->query($q);
        return $r;
    }

    public function getTotalSettledAmountData($param) {
        $where = $param['where'];
        $where_lime_limit = $param['where_lime_limit'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];
//        $org_id = 114;
        $get_data_from = (int) $param['get_data_from'];

        $q = "SELECT 
     DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y') AS year,
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y-%b') AS time,
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%b') AS month,
    SUM(ixf.value) AS amount,
    COUNT(DISTINCT iss.id) AS total_records
FROM issues AS iss
INNER JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1 AND `iod`.process IN ('closed', 'hidden')) 
    INNER JOIN issue_fields pf ON (pf.issue_id = iss.id AND pf.fk_id = 65 AND pf.value = 'fpr')
    INNER JOIN issue_fields iss_awb ON(iss_awb.issue_id=iss.id AND iss_awb.fk_id=3)
LEFT JOIN issue_fields AS ixf
    ON ixf.issue_id = iss.id
   AND ixf.fk_id = 2826

WHERE iss.type_id = $type_id
  $where
$where_lime_limit
GROUP BY DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y-%m')

HAVING SUM(ixf.value) IS NOT NULL

ORDER BY year DESC,
    MONTH(FROM_UNIXTIME(iss.ctime)) ASC";
        $r = MVC_Core::$db->query($q);
        return $r;
    }

    public function getActiveFormalClaimsAssignmentDetails($param) {
        $where = $param['where'];
        $where_lime_limit = $param['where_lime_limit'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];
        $q = "
SELECT COUNT(DISTINCT iss.id) AS total_records,
    SUM(
        CASE 
            WHEN iod.assigned_to IS NOT NULL 
             AND iod.assigned_to <> '' 
            THEN 1 ELSE 0 
        END
    ) AS assigned_count,

    SUM(
        CASE 
            WHEN iod.assigned_to IS NULL 
             OR iod.assigned_to = '' 
            THEN 1 ELSE 0 
        END
    ) AS unassigned_count
FROM issues AS iss
INNER JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1 AND `iod`.process NOT IN ('closed', 'hidden'))

INNER JOIN issue_fields pf 
    ON pf.issue_id = iss.id
   AND pf.fk_id = 65
   AND pf.value = 'fpr'
   INNER JOIN issue_fields iss_awb ON(iss_awb.issue_id=iss.id AND iss_awb.fk_id=3)

WHERE iss.type_id = $type_id
  $where
  $where_lime_limit
";

        $r = MVC_Core::$db->query($q);
        return $r;
    }

    
    
    
    public function getActiveFormalClaimedData($org_id, $rt_id,$where) {
        $q = "SELECT 
            DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y') AS year,
            iss.track,
            iss.cache_primary,
            ixf.value AS claim_amount,
            ixf_date.int_value as claim_date,
            iod.process
          FROM issues AS iss 
          INNER JOIN issue_orgdata AS iod 
            ON (iss.id = iod.issue_id 
            AND iod.org_id = $org_id 
            AND iod.is_enabled = 1 
            AND iod.process NOT IN ('closed', 'hidden')) 

          INNER JOIN issue_fields pf 
            ON (pf.issue_id = iss.id AND pf.fk_id = 65 AND pf.value = 'fpr')
            INNER JOIN issue_fields iss_awb ON(iss_awb.issue_id=iss.id AND iss_awb.fk_id=3)

          LEFT JOIN issue_fields AS ixf 
            ON ixf.issue_id = iss.id AND ixf.fk_id = 2671 

          LEFT JOIN issue_fields AS ixf_date 
            ON ixf_date.issue_id = iss.id AND ixf_date.fk_id = 2399 

          WHERE iss.type_id = $rt_id
              $where
          AND ixf.value IS NOT NULL

          ORDER BY year DESC, MONTH(FROM_UNIXTIME(iss.ctime)) ASC";
        $r = MVC_Core::$db->query($q);
        return $r;
    }

    function getClaimAspectsCount($param) {
        $where = $param['where'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];

        $row = MVC_Core::$db->query("SELECT cd FROM issue_xfdef WHERE id = 9")->fetch_assoc();
        $defs = json_decode($row['cd'], true);

        $columns = array();

        foreach ($defs as $d) {
            if (strpos($d, '#') === 0) {
                list($code, $label) = explode(': ', substr($d, 1));

                $columns[] = "SUM(FIND_IN_SET('$code', cleaned_value) > 0) AS `$label`";
            } else {
                $columns[] = "SUM(FIND_IN_SET('$d', cleaned_value) > 0) AS `$d`";
            }
        }

        $dynamicColumns = implode(",\n", $columns);

        $claimAspectsCountSql = "
                                SELECT
                                    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y-%b') AS time,
                                    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%b') AS month,
                                    $dynamicColumns

                                FROM issues iss
                                JOIN issue_orgdata iod 
                                    ON iod.issue_id = iss.id AND iod.org_id = $org_id

                                JOIN (
                                    SELECT 
                                        issue_id,
                                        REPLACE(REPLACE(REPLACE(value,'[',''),']',''), '\"','') AS cleaned_value
                                    FROM issue_fields
                                    WHERE fk_id = 9
                                ) x
                                    ON x.issue_id = iss.id
                                    INNER JOIN issue_fields iss_awb ON(iss_awb.issue_id=iss.id AND iss_awb.fk_id=3)

                                WHERE iss.type_id = $type_id
                                $where

                                GROUP BY time
                                ORDER BY time DESC
                                ";

        $r = MVC_Core::$db->query($claimAspectsCountSql);
        return $r;
    }

//     IWI Aircargoclaim chart page update 12012026 end


//     IWI Aircargoclaim chart page update 20022026 start
    
    
     public function getActiveFormalClaimByUserChart($param) {
//        $where = $param['where'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];

//        $sql = "
//SELECT 
//    YEAR(FROM_UNIXTIME(iod.linktime)) AS year,
//    DATE_FORMAT(FROM_UNIXTIME(iod.linktime), '%b') AS month,
//    MONTH(FROM_UNIXTIME(iod.linktime)) AS month_number,
//
//    COALESCE(u.id, 0) AS user_id,
//    COALESCE(u.name, 'Unassigned') AS user_name,
//
//    COUNT(*) AS active_count
//
//FROM `issues` AS iss
//
//INNER JOIN `issue_orgdata` AS iod 
//    ON iss.id = iod.issue_id
//    AND iod.org_id = $org_id
//    AND iod.is_enabled = 1
//
//LEFT JOIN `users` u 
//    ON u.id = iod.assigned_to
//
//INNER JOIN issue_fields pf 
//    ON pf.issue_id = iss.id 
//    AND pf.fk_id = 65 
//    AND pf.value = 'fpr'
//
//WHERE 
//    iss.type_id = $type_id
//    AND iod.process NOT IN ('closed','hidden')
//    $where
//
//GROUP BY 
//    year,
//    month_number,
//    user_id
//
//ORDER BY 
//    year ASC,
//    month_number ASC;
//";
        
        
        $sql="SELECT 
    CASE
        WHEN iod.assigned_to IS NULL
          OR iod.assigned_to = ''
        THEN 0
        ELSE u.id
    END AS user_id,

    CASE
        WHEN iod.assigned_to IS NULL
          OR iod.assigned_to = ''
        THEN 'Unassigned'
        ELSE u.name
    END AS user_name,

    COUNT(DISTINCT iss.id) AS active_count

FROM issues AS iss

INNER JOIN issue_orgdata AS iod
    ON (
        iss.id = iod.issue_id
        AND iod.org_id = $org_id
        AND iod.is_enabled = 1
        AND iod.process NOT IN ('closed', 'hidden')
    )

LEFT JOIN users u
    ON u.id = iod.assigned_to

INNER JOIN issue_fields pf
    ON (
        pf.issue_id = iss.id
        AND pf.fk_id = 65
        AND pf.value = 'fpr'
    )

INNER JOIN issue_fields iss_awb
    ON (
        iss_awb.issue_id = iss.id
        AND iss_awb.fk_id = 3
    )

WHERE iss.type_id = $type_id
    AND (
      iod.assigned_to IS NULL
      OR iod.assigned_to = ''
      OR u.id IS NOT NULL
    )
$where
GROUP BY user_id, user_name

ORDER BY active_count DESC";

        $data = MVC_Core::$db->query($sql);

        return $data ? $data : array();
    }
    
    
    public function getTotalActiveFormalChart($param){
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];

        $sql = "SELECT 
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y') AS year,
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y-%b') AS time,
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%b') AS month,

    COUNT(DISTINCT iss.id) AS total_records,

    SUM(
        CASE 
            WHEN iod.assigned_to IS NOT NULL 
             AND iod.assigned_to <> '' 
            THEN 1 ELSE 0 
        END
    ) AS assigned_count,

    SUM(
        CASE 
            WHEN iod.assigned_to IS NULL 
             OR iod.assigned_to = '' 
            THEN 1 ELSE 0 
        END
    ) AS unassigned_count

FROM issues AS iss
INNER JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1 AND `iod`.process NOT IN ('closed', 'hidden'))

INNER JOIN issue_fields pf 
    ON pf.issue_id = iss.id
   AND pf.fk_id = 65
   AND pf.value = 'fpr'
   INNER JOIN issue_fields iss_awb ON(iss_awb.issue_id=iss.id AND iss_awb.fk_id=3)

WHERE iss.type_id = $type_id

GROUP BY DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y-%m')


ORDER BY 
    year DESC,
    MONTH(FROM_UNIXTIME(iss.ctime)) ASC";

        $data = MVC_Core::$db->query($sql);

        return $data ? $data : array();
    }
    
    public function getActiveFormalUnassignedData($param){

    $type_id = (int) $param['type_id'];
    $org_id  = (int) $param['org_id'];

    $where = "";

    if(isset($param['page_for']) && $param['page_for'] == 'unassigned_details'){
        if((isset($param['year']) && $param['year']!='') && ( isset($param['month']) && $param['month']!='')){
            $year  = (int)$param['year'];
        $month = date('m', strtotime($param['month'])); 
        $start = strtotime("$year-$month-01 00:00:00");
        $end   = strtotime("+1 month", $start);
        $where .= " 
            AND iss.ctime >= $start
            AND iss.ctime < $end
            AND (iod.assigned_to IS NULL OR iod.assigned_to = '')
        ";
        }        
    }
  $sql = "SELECT 
    iss.id,
    iss.cache_primary,
    iss.track,
    iss.ctime,
    FROM_UNIXTIME(iss.ctime) AS created_date,
    ixf_date.int_value AS claim_date,
    DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%Y') AS year,
          DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%M') AS month

FROM issues AS iss

INNER JOIN issue_orgdata AS iod 
    ON iss.id = iod.issue_id
    AND iod.org_id = $org_id
    AND iod.is_enabled = 1
    AND iod.process NOT IN ('closed', 'hidden')

INNER JOIN issue_fields pf 
    ON pf.issue_id = iss.id
    AND pf.fk_id = 65
    AND pf.value = 'fpr'

LEFT JOIN issue_fields AS ixf_date 
    ON ixf_date.issue_id = iss.id
    AND ixf_date.fk_id = 2399

INNER JOIN issue_fields iss_awb 
    ON iss_awb.issue_id = iss.id
    AND iss_awb.fk_id = 3

WHERE 
    iss.type_id = $type_id
    AND (iod.assigned_to IS NULL OR iod.assigned_to = '')
    $where

ORDER BY iss.ctime DESC";

    
    $data = MVC_Core::$db->query($sql);

    return $data ? $data : array();
}
    
//     IWI Aircargoclaim chart page update 20022026 end

     //capa_chart_enhancement 16-02-26 start
    
    
     public function capaGetTotalRecords($param) {
        $org_id = $param['org_id'];
        if ($param['report_type'] == "all") {
            $report_type = "'7','31','34'";
        } else {
            $report_type = "'" . $param['report_type'] . "'";
        }

        $period = $param['period'];

        $q = " 
        SELECT 
            COUNT(DISTINCT audit_activity.id) as total,
            DATE_FORMAT(FROM_UNIXTIME(issues.ctime), '%b') AS month,
            audit_activity.action_type,
            audit_activity.status
        FROM audit_activity
        INNER JOIN issue_orgdata 
            ON issue_orgdata.issue_id = audit_activity.issue_id 
            AND audit_activity.org_id = $org_id
        INNER JOIN issues 
            ON audit_activity.track = issues.track
        WHERE issues.type_id IN ($report_type)
        AND audit_activity.action_type IN ('Corrective','Preventive','Investigation')
        AND audit_activity.ctime >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)
        GROUP BY 
            audit_activity.action_type,
            audit_activity.status,
            month
        ORDER BY issues.ctime ASC ";

        $r = MVC_Core::$db->query($q);
        return $r;
    }

    public function capaGetTypeWise($param) {
        $org_id = $param['org_id'];
        if ($param['report_type'] == "all") {
            $report_type = "'7','31','34'";
        } else {
            $report_type = "'" . $param['report_type'] . "'";
        }

        $period = $param['period'];
        $status_type = $param['status_type'];

        $where = '';
        if ($status_type == 'active') {
            $where .= " AND audit_activity.status IN ('Active') ";
        } else if ($status_type == 'closed') {
            $where .= " AND audit_activity.status IN ('Closed') ";
        } else {
            $where .= " AND audit_activity.status IN ('Active','Closed') ";
        }

        $q = " 
                SELECT 
        COUNT(DISTINCT audit_activity.id) AS count,DATE_FORMAT(FROM_UNIXTIME(issues.ctime), '%b') AS month,
        audit_activity.status,
        audit_activity.type
        FROM audit_activity
        INNER JOIN issue_orgdata 
            ON(issue_orgdata.issue_id = audit_activity.issue_id AND  audit_activity.org_id = $org_id)
        INNER JOIN issues 
            ON audit_activity.track = issues.track
        WHERE issues.type_id IN ($report_type)
            $where
        AND audit_activity.type IN ('finding','observation','opportunity')
        AND audit_activity.ctime >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)
        GROUP BY audit_activity.type,
                    audit_activity.status,
                    month
                ORDER BY issues.ctime ASC ";

        $r = MVC_Core::$db->query($q);
        return $r;
    }

    public function capaGetRiskTypeWise($param) {
        $org_id = $param['org_id'];
        if ($param['report_type'] == "all") {
            $report_type = "'7','31','34'";
        } else {
            $report_type = "'" . $param['report_type'] . "'";
        }

        $period = $param['period'];
        $status_type = $param['status_type'];
        $where = '';
        if ($status_type == 'active') {
            $where .= " AND audit_activity.status IN ('Active') ";
        } else if ($status_type == 'closed') {
            $where .= " AND audit_activity.status IN ('Closed') ";
        } else {
            $where .= " AND audit_activity.status IN ('Active','Closed') ";
        }

        $q = " 
        SELECT 
        COUNT(DISTINCT audit_activity.id)  AS count,
        DATE_FORMAT(FROM_UNIXTIME(issues.ctime), '%b') AS month,
         audit_activity.status, 
         audit_activity.risk
        FROM audit_activity
        INNER JOIN issue_orgdata 
            ON(issue_orgdata.issue_id = audit_activity.issue_id AND  audit_activity.org_id = $org_id)
        INNER JOIN issues 
            ON audit_activity.track = issues.track
        WHERE 
--        issues.type_id IN ($report_type) AND
         audit_activity.risk IN ('MI','MA','CR')
         $where
        
        AND audit_activity.ctime >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)
        GROUP BY audit_activity.action_type,
                            audit_activity.status,
                            month
                        ORDER BY issues.ctime ASC ";

        $r = MVC_Core::$db->query($q);
        return $r;
    }


    
     //capa_chart_enhancement 16-02-26 end
    
    
       
//IWI audit control page chart new 11032026 start

public function getAuditControlTotalRecord($param) {
       $where = $param['where'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];

        $sql = "SELECT DATE_FORMAT(FROM_UNIXTIME(iod.linktime), '%b') AS month, SUM(CASE WHEN iod.process IN ('closed','hidden') THEN 1 ELSE 0 END) AS closed_count, SUM(CASE WHEN iod.process NOT IN ('closed','hidden') THEN 1 ELSE 0 END) AS opened_count FROM `issues` AS iss RIGHT JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1)  WHERE `iss`.type_id = $type_id $where GROUP BY month ORDER BY (iss.ctime)";

        $data = MVC_Core::$db->query($sql);

        return $data ? $data : array();
    }


    
    public function getAuditControlstatus($param) {
       $where = $param['where'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];

        $sql ="SELECT DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%b') AS month,issue_orgdata.status_key ,COUNT(*) AS total
        FROM issues as iss JOIN issue_orgdata 
            ON iss.id = issue_orgdata.issue_id
        WHERE issue_orgdata.org_id = $org_id AND iss.type_id = $type_id $where
            GROUP BY  DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%b'), issue_orgdata.status_key ORDER BY (iss.ctime)";


        $data = MVC_Core::$db->query($sql);

        return $data ? $data : array();
    }

        public function getAuditControlpriority($param) {
       $where = $param['where'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];

         $sql ="SELECT DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%b') AS month,issue_orgdata.priority_key,COUNT(*) AS total
        FROM issues as iss  JOIN issue_orgdata 
            ON iss.id = issue_orgdata.issue_id
        WHERE issue_orgdata.org_id = $org_id AND iss.type_id = $type_id $where
                GROUP BY DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%b'),issue_orgdata.priority_key  ORDER BY (iss.ctime)";


        $data = MVC_Core::$db->query($sql);

        return $data ? $data : array();
    }

      public function getAuditControlRisk($param) {
       $where = $param['where'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];

           $sql ="SELECT DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%b') AS month,audit_activity.risk,COUNT(audit_activity.id) AS total
        FROM audit_activity INNER JOIN issues as iss ON audit_activity.track = iss.track
        WHERE audit_activity.org_id = $org_id
        AND audit_activity.issue_type_id = $type_id $where GROUP BY month ORDER BY (iss.ctime)";

        $data = MVC_Core::$db->query($sql);

        return $data ? $data : array();
    }

     public function getAuditControlType($param) {
       $where = $param['where'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];

       $sql ="SELECT DATE_FORMAT(FROM_UNIXTIME(iss.ctime), '%b') AS month,audit_activity.action_type,COUNT(audit_activity.id) AS total
        FROM audit_activity INNER JOIN issues as iss ON audit_activity.track = iss.track
        WHERE audit_activity.org_id = $org_id
        AND audit_activity.issue_type_id = $type_id
        AND audit_activity.action_type IN ('Corrective','Preventive','Investigation') 
         $where GROUP BY month ORDER BY (iss.ctime)";

        $data = MVC_Core::$db->query($sql);

        return $data ? $data : array();
    }

     public function getAuditControlAssesmentDetails($param) {
       $where = $param['where'];
        $type_id = (int) $param['type_id'];
        $org_id = (int) $param['org_id'];
         $q_assignment = " SELECT  COUNT(DISTINCT iss.id) AS total_records,
                    SUM( CASE  WHEN iod.assigned_to IS NOT NULL  AND iod.assigned_to <> '' AND users.id IS NOT NULL  THEN 1 ELSE 0  END ) AS assigned_count, 
                    SUM(  CASE  WHEN iod.assigned_to IS NULL OR iod.assigned_to = '' OR users.id IS NULL THEN 1 ELSE 0  END ) AS unassigned_count 
                    FROM issues AS iss 
                    RIGHT JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1 AND `iod`.process NOT IN ('closed', 'hidden'))  "
                . "LEFT JOIN `users` ON (iod.assigned_to = `users`.id) "
                . "WHERE iss.type_id = $type_id "
                . "$where ";

        $data = MVC_Core::$db->query($q_assignment)->current();

        return $data ? $data : array();
    }

//IWI audit control page chart new 11032026 end

    //    10042026 audit control status |start
        public function getRecordDetais($track_id){
            $sql="SELECT iss.type_id,iss.id,iod.status_key FROM `issues` as iss left join `issue_orgdata` as iod ON iss.id = iod.issue_id
          WHERE iss.`track` LIKE '$track_id'";
            $Result = MVC_Core::$db->query($sql)->current(); 
            return $Result;
        }
    //    10042026 audit control status |end
        
        
//    IWI capa new chart page design 30042026 start

    public function get_CaPaReportListing($org_id, $havingCondition) {     //iwi record title fetch 
        $audit_activity_Sql = "SELECT audit_activity.*,issue_orgdata.process,issues.master_org_id,issues.shared_org_ids , issues.cache_primary
        FROM `audit_activity`
        INNER JOIN issue_orgdata ON ( issue_orgdata.issue_id = audit_activity.issue_id AND issue_orgdata.org_id=$org_id AND issue_orgdata.process NOT IN ('closed','hidden')) 
        INNER JOIN issues ON audit_activity.track = issues.track
        WHERE audit_activity.org_id = $org_id  $havingCondition  ";
        $audit_activity_Result = MVC_Core::$db->query($audit_activity_Sql);
        if (!$audit_activity_Result)
            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

        return $audit_activity_Result;
    }
    public function caPaTotalRecordCount($param) {
        $org_id = $param['org_id'];
        $where = '';
        $field_if_time = '';
        $group_if_time = '';

        if (isset($param['period']) && $param['period'] != '' && $param['period'] != 'all') {
            if($param['period'] == '12'){
            $period = $param['period'];
            $where .= " AND datetime >= DATE_SUB(CURDATE(), INTERVAL $period MONTH) ";
            }else{
                $period = (int)$param['period'];
        $startDate = $period . '-01-01 00:00:00';
        $endDate   = $period . '-12-31 23:59:59';
            $where .= " AND datetime  BETWEEN '$startDate' AND '$endDate' ";
            $where .= " AND datetime IS NOT NULL ";
            }
            $field_if_time = " , DATE_FORMAT(datetime, '%M') AS time ";
            $group_if_time = " ,time ";
        }
        if (isset($param['period']) && $param['period'] == 'all') {
            $field_if_time = " , DATE_FORMAT(datetime, '%Y') AS time ";
            $group_if_time = " ,time ";
        }

        // $q = "SELECT COUNT(DISTINCT audit_activity.id) as total,  audit_activity.status $field_if_time"
        //         . "FROM audit_activity "
        //         . " INNER JOIN issue_orgdata ON ( issue_orgdata.issue_id = audit_activity.issue_id AND issue_orgdata.org_id=$org_id  AND issue_orgdata.process NOT IN ('closed', 'hidden') ) "
        //         . " INNER JOIN issues ON audit_activity.track=issues.track "
        //         . " WHERE audit_activity.org_id = $org_id  $where"
        //         . " GROUP BY audit_activity.status $group_if_time"
        //         . " ORDER BY issues.ctime ASC";
                $q = "SELECT 
                COUNT(DISTINCT id) AS total,
                status $field_if_time,
                datetime
               FROM (
                SELECT 
                    audit_activity.id,
                    audit_activity.status,
                    CASE 
                        WHEN audit_activity.ctime IS NULL 
                            OR audit_activity.ctime = '0000-00-00 00:00:00'
                        THEN FROM_UNIXTIME(issue_orgdata.linktime)
                        ELSE audit_activity.ctime
                    END AS datetime
                FROM audit_activity
                INNER JOIN issue_orgdata 
                    ON issue_orgdata.issue_id = audit_activity.issue_id 
                    AND issue_orgdata.org_id = $org_id
                    AND issue_orgdata.process NOT IN ('closed','hidden')
                INNER JOIN issues 
                    ON audit_activity.track = issues.track 
                WHERE audit_activity.org_id = $org_id
            ) t
            GROUP BY status $group_if_time
            ORDER BY STR_TO_DATE(time, '%M')  ASC";

        return MVC_Core::$db->query($q);
    }
    public function caPaTypeWiseData($param) {
        $org_id = $param['org_id'];
        $where = '';
        $field_if_time = '';
        $group_if_time = '';

        if (isset($param['period']) && $param['period'] != '' && $param['period'] != 'all') {
            if($param['period'] == '12'){
            $period = $param['period'];
            $where .= " AND datetime >= DATE_SUB(CURDATE(), INTERVAL $period MONTH) ";
            }else{
                $period = (int)$param['period'];
        $startDate = $period . '-01-01 00:00:00';
        $endDate   = $period . '-12-31 23:59:59';
            $where .= " AND datetime  BETWEEN '$startDate' AND '$endDate' ";
            $where .= " AND datetime IS NOT NULL ";
            }
            $field_if_time = " , DATE_FORMAT(datetime, '%M') AS time ";
            $group_if_time = " ,time ";
        }
        if (isset($param['period']) && $param['period'] == 'all') {
            $field_if_time = " , DATE_FORMAT(datetime, '%Y') AS time ";
            $group_if_time = " ,time ";
        }

        // $q = "SELECT COUNT(DISTINCT audit_activity.id) as total,  audit_activity.status ,audit_activity.type $field_if_time"
        //         . "FROM audit_activity "
        //         . " INNER JOIN issue_orgdata ON ( issue_orgdata.issue_id = audit_activity.issue_id AND issue_orgdata.org_id=$org_id  AND issue_orgdata.process NOT IN ('closed', 'hidden') ) "
        //         . " INNER JOIN issues ON audit_activity.track=issues.track "
        //         . " WHERE audit_activity.org_id = $org_id  "
        //         . " AND audit_activity.type IN ('finding','observation','opportunity') "
        //         . "$where"
        //         . " GROUP BY audit_activity.status , audit_activity.type $group_if_time"
        //         . " ORDER BY issues.ctime ASC";
          $q = "SELECT 
                    COUNT(DISTINCT id) AS total,
                   status,type $field_if_time,
                     datetime
                    FROM (
                        SELECT 
                        audit_activity.id,
                        audit_activity.status,
                       audit_activity.type,
                        CASE 
                            WHEN audit_activity.ctime IS NULL 
                                OR audit_activity.ctime = '0000-00-00 00:00:00'
                            THEN FROM_UNIXTIME(issue_orgdata.linktime)
                            ELSE audit_activity.ctime
                       END AS datetime
                FROM audit_activity
                INNER JOIN issue_orgdata
                 ON issue_orgdata.issue_id = audit_activity.issue_id 
                    AND issue_orgdata.org_id = $org_id
                    AND issue_orgdata.process NOT IN ('closed','hidden')
                INNER JOIN issues 
                    ON audit_activity.track = issues.track 
                WHERE 
                    audit_activity.org_id = $org_id 
                    AND audit_activity.type IN ('finding','observation','opportunity')
                 ) t
                GROUP BY status ,type $group_if_time
                ORDER BY STR_TO_DATE(time, '%M') ASC";

        return MVC_Core::$db->query($q);
    }
    public function caPaRiskWiseData($param) {
         $org_id = $param['org_id'];
        $where = '';
        $field_if_time = '';
        $group_if_time = '';

        if (isset($param['period']) && $param['period'] != '' && $param['period'] != 'all') {
            if($param['period'] == '12'){
            $period = $param['period'];
            $where .= " AND datetime >= DATE_SUB(CURDATE(), INTERVAL $period MONTH) ";
            }else{
                $period = (int)$param['period'];
        $startDate = $period . '-01-01 00:00:00';
        $endDate   = $period . '-12-31 23:59:59';
            $where .= " AND datetime  BETWEEN '$startDate' AND '$endDate' ";
            $where .= " AND datetime IS NOT NULL ";
            }
            $field_if_time = " , DATE_FORMAT(datetime, '%M') AS time ";
            $group_if_time = " ,time ";
        }
        if (isset($param['period']) && $param['period'] == 'all') {
            $field_if_time = " , DATE_FORMAT(datetime, '%Y') AS time ";
            $group_if_time = " ,time ";
        }

    //    $q = "SELECT COUNT(DISTINCT audit_activity.id) as total,  audit_activity.status ,audit_activity.risk $field_if_time"
    //             . "FROM audit_activity "
    //             . " INNER JOIN issue_orgdata ON ( issue_orgdata.issue_id = audit_activity.issue_id AND issue_orgdata.org_id=$org_id  AND issue_orgdata.process NOT IN ('closed', 'hidden') ) "
    //             . " INNER JOIN issues ON audit_activity.track=issues.track "
    //             . " WHERE audit_activity.org_id = $org_id  "
    //             . " AND audit_activity.risk IN ('MI','MA','CR')  "
    //             . "$where"
    //             . " GROUP BY audit_activity.status , audit_activity.risk $group_if_time"
    //             . " ORDER BY issues.ctime ASC";

                   $q = "SELECT 
                    COUNT(DISTINCT id) AS total,
                   status,risk $field_if_time,
                     datetime
                    FROM (
                        SELECT 
                        audit_activity.id,
                        audit_activity.status,
                       audit_activity.risk,
                        CASE 
                            WHEN audit_activity.ctime IS NULL 
                                OR audit_activity.ctime = '0000-00-00 00:00:00'
                            THEN FROM_UNIXTIME(issue_orgdata.linktime)
                            ELSE audit_activity.ctime
                       END AS datetime
                FROM audit_activity
                INNER JOIN issue_orgdata
                 ON issue_orgdata.issue_id = audit_activity.issue_id 
                    AND issue_orgdata.org_id = $org_id
                    AND issue_orgdata.process NOT IN ('closed','hidden')
                INNER JOIN issues 
                    ON audit_activity.track = issues.track 
                WHERE 
                    audit_activity.org_id = $org_id 
                    AND audit_activity.risk IN ('MI','MA','CR')
                 ) t
                GROUP BY status ,risk $group_if_time
                ORDER BY STR_TO_DATE(time, '%M') ASC";

        return MVC_Core::$db->query($q);
    }
//IWI capa new chart page design 30042026 end
    
    
//    IWI  manage contracts for Supplier control 19052026 start
//    public function getContractManagementData($param) {
//        $link_id = $param['link_id'];
//        $q = QueryFactory::select()
//                ->table('contract_management', '*')
//                ->table('users', array('assign_to_name' => 'name'), 'LEFT', "`users`.id = `contract_management`.assign_to")
//                ->where("contract_management.issue_id = ?", $link_id)
//                ->order("contract_management.id DESC");
////        echo $q;
//        $r = MVC_Core::$db->query($q);
//        $getData = array();
//        if ($r && count($r) > 0) {
//            foreach ($r as $row) {
//                $getData[] = $row;
//            }
//        }
//        return $getData;
//    }
     public function getContractManagementData($param) {
        $link_id = $param['link_id'];
        $q = QueryFactory::select()
                ->table('contract_management', '*')
                ->table('users', array('assign_to_name' => 'name'), 'LEFT', "`users`.id = `contract_management`.assign_to")
                ->table('files', array('filepath' , 'analyzer','chash'), 'LEFT', "`files`.id = `contract_management`.files")
                ->where("contract_management.issue_id = ?", $link_id)
                ->order("contract_management.id DESC");
//        echo $q;
        $r = MVC_Core::$db->query($q);
        $getData = array();
        if ($r && count($r) > 0) {
            foreach ($r as $row) {
                $row['file_id']=$row['files']; //files upload in contract management form
                $row['is_terminate']=$row['is_terminate']=='1'?'Yes':'No';
                if($row['filepath'] != '' || $row['filepath'] != null){
                if($row['analyzer'] == 'image'){
                    $filepath = $row['filepath'];
                    $file = explode('/',$filepath);
                    $file2 = $file[0].$file[1];
                    $file = $file[2];
                    $file =   str_replace('.png','.jpg',$file);
                    $file = substr($file,2);
                    $tr_file_path = '/uimg/thms/'.$file2.'/'.$file;
                    $certificate = $tr_file_path;
                     $row['files'] = '<a href="'.$certificate.'" download><img src="/img/icons16/file_extension_zip.png"></a>';
                }else{
                     $row['files'] = '<a href="/files?action=download_attachment&ch='.$row['chash'].'" download><img src="/img/icons16/file_extension_zip.png"></a>';

                }                   
                
            }else{
                 $row['files'] = '';
            }
            
            
                $getData[] = $row;
            }
        }
        return $getData;
    }
    public function updateYearValue($param) {

    $link_id = $param['link_id'];

    $q = QueryFactory::select()
            ->table('contract_management', array(
                'id',
                'start_date',
                'end_date',
                'month_value'
            ))
            ->where("issue_id = ?", $link_id);

    $contracts = MVC_Core::$db->query($q);

    $currentYear = date('Y');
    $yearEnd = new DateTime($currentYear . '-12-31');

    foreach ($contracts as $row) {

        if (empty($row['start_date']) || $row['start_date'] == '0000-00-00') {
            continue;
        }

        $start = new DateTime($row['start_date']);

        // Use actual end date if available, otherwise end of current year
        if (!empty($row['end_date']) && $row['end_date'] != '0000-00-00') {
            $end = new DateTime($row['end_date']);
        } else {
            $end = clone $yearEnd;
        }

        // Calculate inclusive months
        $months = (($end->format('Y') - $start->format('Y')) * 12)
                + ($end->format('n') - $start->format('n'))
                + 1;

        // Business rule
        if ($months > 12) {
            $months = 12;
        }

        if ($months <= 0) {
            $months = 12;
        }

        $yearValue = $months * (float)$row['month_value'];

        $u = QueryFactory::update()
                ->table('contract_management')
                ->where("id = ?", $row['id'])
                ->values(array(
                    'year_value' => $yearValue
                ));

        MVC_Core::$db->query($u);
    }
}
    public function getContractManagementSummary($param) {

    $link_id = (int) $param['link_id'];

//    $q = "SELECT
//    COUNT(id) AS total_contracts,
//    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS total_active,
//    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS total_closed,
//    SUM(COALESCE(year_value, 0)) AS total_year_value,
//    SUM(COALESCE(month_value, 0)) AS total_month_value
//FROM `contract_management`
//WHERE issue_id = $link_id";
    
    $q = "SELECT
    COUNT(id) AS total_contracts,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS total_active,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS total_closed,
    SUM(CASE WHEN is_terminate = '1' THEN 1 ELSE 0 END) AS total_terminated,

    SUM(CASE 
        WHEN status = 'active' THEN COALESCE(year_value, 0) 
        ELSE 0 
    END) AS total_year_value,

    SUM(CASE 
        WHEN status = 'active' THEN COALESCE(month_value, 0) 
        ELSE 0 
    END) AS total_month_value

FROM `contract_management`
WHERE issue_id = $link_id";

    $result = MVC_Core::$db->query($q)->current();

    if ($result) {

        return $result;

    } else {

        return array(
            'total_contracts' => 0,
            'total_active' => 0,
            'total_closed' => 0,
            'total_year_value' => 0,
            'total_month_value' => 0
        );
    }
}

/*

    public function saveContractManagementData($param,$contract_data) {
        $link_id = $param['link_id'];
        
        if(!isset($param['contract_id']) || $param['contract_id']<1){
            $contract_data['status']='active';
            $q=QueryFactory::insert()
            -> table('contract_management')
            -> values($contract_data);
        }else{
            $contract_id=$param['contract_id'];
             $q= QueryFactory::update()
                -> table('contract_management')
                -> values($contract_data)
                ->where("issue_id = ?", $link_id)
                -> where("`id` = ?", $contract_id);
        }
//        echo $q;
        return MVC_Core::$db-> query($q);  
    }
    
    */


//IWI Adding assets from Supplier control contract section 28072026 start
    public function saveContractManagementData($param,$contract_data) {
        $link_id = $param['link_id'];
        
        if(!isset($param['contract_id']) || $param['contract_id']<1){
            $contract_data['status']='active';
            $q=QueryFactory::insert()
            -> table('contract_management')
            -> values($contract_data);
             $result = MVC_Core::$db->query($q);
             if (!$result) {
            return false;
            
        }
        return MVC_Core::$db-> insert_id;
        }else{
            $contract_id=$param['contract_id'];
             $q= QueryFactory::update()
                -> table('contract_management')
                -> values($contract_data)
                ->where("issue_id = ?", $link_id)
                -> where("`id` = ?", $contract_id);
              $result = MVC_Core::$db->query($q);
              if (!$result) {
            return false;
        }
        return $contract_id;
        }
        
    }
    public function getContractAssets($contract_id)
{
    $q = QueryFactory::select()
        ->table('asset_contract_link', array(
            'id',
            'asset_issue_id',
            'contract_id'
        ))

        ->table(
            MVC_Core::$db->table('iss'),
            array(
                'asset_name'=>'cache_primary',
                'asset_track'=>'track'
            ),
            'left',
            "`iss`.id = `asset_contract_link`.asset_issue_id"
        )

        // Location
        ->table(
            'issue_fields',
            array(
                'location_code' => 'value'
            ),
            'left',
            "`issue_fields`.issue_id = `asset_contract_link`.asset_issue_id
             AND `issue_fields`.fk_id = 3879"
        )

        // Department
        ->table(
            array('issue_fields', 'issue_fields_2'),
            array(
                'department' => 'value'
            ),
            'left',
            "`issue_fields_2`.issue_id = `asset_contract_link`.asset_issue_id
             AND `issue_fields_2`.fk_id = 3880",
            'issue_fields_2'
        )

        // Brand
        ->table(
            array('issue_fields', 'issue_fields_3'),
            array(
                'brand_name' => 'value'
            ),
            'left',
            "`issue_fields_3`.issue_id = `asset_contract_link`.asset_issue_id
             AND `issue_fields_3`.fk_id = 3890",
            'issue_fields_3'
        )

        ->where("asset_contract_link.contract_id = ?", $contract_id);

    $r= MVC_Core::$db->query($q);
    $getData=array();
    if ($r && count($r) > 0) {
            foreach ($r as $row)
                $getData[] = $row;
        }
        return $getData;
}
//   IWI Adding assets from Supplier control contract section 28072026 end 
    public function updateContractStatus($ids,$status) {
             $q= QueryFactory::update()
                -> table('contract_management')
                -> values(array('status'=>$status))
                -> where("`id` IN (?)", $ids);
        return MVC_Core::$db-> query($q);  
    }
    public function assignContractStatus($ids,$assign_to) {
             $q= QueryFactory::update()
                -> table('contract_management')
                -> values(array('assign_to'=>$assign_to))
                -> where("`id` IN (?)", $ids);
        return MVC_Core::$db-> query($q);  
    }
    public function updateContract($ids,$value) {
             $q= QueryFactory::update()
                -> table('contract_management')
                -> values($value)
                -> where("`id` IN (?)", $ids);
        return MVC_Core::$db-> query($q);  
    }
     //   terminatedContract status start
     public function updateContractAssetsStatus($link_id, $status_key) {
        $contract_ids = implode(',', array_map('intval', $link_id));

        $q = "UPDATE issue_orgdata iod
      INNER JOIN asset_contract_link acl
          ON acl.asset_issue_id = iod.issue_id
      SET iod.status_key = '$status_key'
      WHERE acl.contract_id IN ($contract_ids)";

        return MVC_Core::$db->query($q);
    }

    //   terminatedContract status end

//    IWI  manage contracts for Supplier control 19052026 end
    
//    IWI  link Supplier and contracts for Asset Control 21052026 start
    public function getSupplierDataByOrg($param) {
        $org_id = $param['org_id'];
        $type_id = $param['type_id'];
        $q = QueryFactory::select()
                ->table(MVC_Core::$db->table('iss'), 'id,track,cache_primary,type_id')
                ->table(MVC_Core::$db->table('iod'), 'process,is_enabled', 'left', "`iod`.issue_id = `iss`.id")
                ->where("`iss`.type_id = ?", $type_id)
                ->where("`iod`.org_id = ?", $org_id)
                ->where("`iod`.process NOT IN (?)", array('closed', 'hidden'))
                ->order("`iss`.id DESC");
//       echo $q;
        $r = MVC_Core::$db->query($q);
        $getData = array();
        $r = MVC_Core::$db->query($q);
        if ($r && count($r) > 0) {
            foreach ($r as $row)
                $getData[] = array(
                    'value' => $row['id'],
                    'label' => $row['cache_primary'],
                    'track' => $row['track'],
                );
        }
        return $getData;
    }

    public function getSupplierContracts($param) {
        $link_id = $param['supplier_id'];
        $q = QueryFactory::select()
                ->table('contract_management', '*')
                ->where("issue_id = ?", $link_id)
                ->where("status = ?", 'active')
                ->order("id DESC");
        $r = MVC_Core::$db->query($q);
        $getData = array();
        $r = MVC_Core::$db->query($q);
        if ($r && count($r) > 0) {
            foreach ($r as $row)
                $getData[] = array(
                    'value' => $row['id'],
                    'label' => $row['contract_name'] .' [ '.$row['contract_nr'] .' ]',
                );
        }
        return $getData;
    }

    public function getLinkedSupplierData($param) {
        $link_id = $param['link_id'];
        $org_id = $param['org_id'];
        $q = QueryFactory::select()
                ->table('asset_contract_link', array('*','asset_contract_link_id'=>'id','asset_contract_link_status'=>'status'))
                ->table(
                        'contract_management',
                        '*',
                        'LEFT',
                        "`contract_management`.id = `asset_contract_link`.contract_id"
                )
                ->table(
                        'issues',
                        array('supplier_track'=>'track','supplier_name'=>'cache_primary'),
                        'LEFT',
                        "`issues`.id = `asset_contract_link`.supplier_issue_id"
                )
                ->where("`asset_contract_link`.asset_issue_id = ?", $link_id)
//                ->where("`asset_contract_link`.status = ?", 'active') //IWI Asset Control -> Link Supplier Add action buttons for inactive/active and delete 09072026
                ->order("`asset_contract_link`.id DESC");
//        echo $q ;
        $r = MVC_Core::$db->query($q);
        $getData = array();
        $r = MVC_Core::$db->query($q);
        if ($r && count($r) > 0) {
            foreach ($r as $row)
                $getData[] = array(
                    'id'=>$row['asset_contract_link_id'], //IWI Asset Control -> Link Supplier Add action buttons for inactive/active and delete 09072026
                    'supplier_name'=>$row['supplier_name'],
                    'contract_name'=>$row['contract_name'],
                    'supplier_track'=>$row['supplier_track'],
                    'contract_name'=>$row['contract_name'],
                    'contract_nr'=>$row['contract_nr'],
                    'start_date'=>$row['start_date'],
                    'end_date'=>$row['end_date'],
                    'status'=>$row['asset_contract_link_status'], //IWI Asset Control -> Link Supplier Add action buttons for inactive/active and delete 09072026
                );
        }
        return $getData;
    }

    public function saveLinkedSupplier($param, $data) {
        $link_id = $param['link_id'];

        $checkQuery = QueryFactory::select()
                ->table('asset_contract_link', 'id')
                ->where("asset_issue_id = ?", $link_id)
                ->limit(1);

        $existing = MVC_Core::$db->query($checkQuery)->current();

        if ($existing && count($existing) > 0) {

            $record_id = $existing['id'];

            $q = QueryFactory::update()
                    ->table('asset_contract_link')
                    ->values($data)
                    ->where("id = ?", $record_id);
        } else {

            $q = QueryFactory::insert()
                    ->table('asset_contract_link')
                    ->values($data);
        }

        return MVC_Core::$db->query($q);
    }
    
    
    public function getUsersByOrg($param) {
        $org_id = $param['org_id'];
       $q = QueryFactory::select()
    ->table(
        array(MVC_Core::$db->tab['usr'], 'usr'),
        "id,user,status,cdep_id,name,email,contact_id"
    )
    ->table(
        array(MVC_Core::$db->tab['u2d'], 'u2d'),
        "user_id,comment,disabled,hidden",
        'left',
        '`u2d`.user_id = `usr`.id'
    )
    ->where("`u2d`.`org_id` = ?", $org_id)
    ->where("`usr`.`cdep_id` > ?", 0)
    ->where("`usr`.status NOT IN (?)", array('disabled', 'invisible','readonly'))
    ->group("`usr`.`id`")
    ->order("`usr`.`name` ASC");

        $r = MVC_Core::$db->query($q);
        
        return $r;
    }

    
//    IWI Asset Control -> Link Supplier Add action buttons for inactive/active and delete 09072026 start
    public function deleteLinkedSupplier($param, $data) {
        $link_id = $param['link_id'];
        $id = $param['id'];

        $checkQuery = QueryFactory::select()
                ->table('asset_contract_link', 'id')
                ->where("asset_issue_id = ?", $link_id)
                ->limit(1);

        $existing = MVC_Core::$db->query($checkQuery)->current();

        if ($existing && count($existing) > 0) {
            $q = QueryFactory::delete()
                    ->table('asset_contract_link')
                    ->where("asset_issue_id = ?", $link_id)
                    ->where("id = ?", $id);
        } 

        return MVC_Core::$db->query($q);
        
    }
//    IWI Asset Control -> Link Supplier Add action buttons for inactive/active and delete 09072026 end

//    IWI  link Supplier and contracts for Asset Control 21052026 end
    
//     IWI separate listing page for all contracts 28052026 start
    public function getAllContractManagementData($param) {
        $org_id = (int) $param['org_id'];
        $status = !empty($param['status']) ? $param['status'] : '';
        $supplier = !empty($param['supplier']) ? $param['supplier'] : '';
        $group = !empty($param['group']) ? $param['group'] : '';
        $contract_type = !empty($param['contract_type']) ? $param['contract_type'] : '';
        $end_date = !empty($param['end_date']) ? $param['end_date'] : '';
        $terminate = !empty($param['terminate']) ? $param['terminate'] : '';
        $q = QueryFactory::select()
                ->table('contract_management', '*')
                ->table(MVC_Core::$db->table('iss'), array('track','group_id','supplier_name'=>'cache_primary'), 'LEFT', "`contract_management`.issue_id = `iss`.id") // IWI changes in contract management report page 09062026
                ->table('groups', array('group_name'=>'name'), 'LEFT', "`iss`.group_id = `groups`.id") // IWI changes in contract management report page 09062026
                ->table('users', array('assign_to_name' => 'name'), 'LEFT', "`users`.id = `contract_management`.assign_to")
                ->table(MVC_Core::$db->table('iod'), array('org_id'), 'LEFT', "`contract_management`.issue_id = `iod`.issue_id")
                ->where("`iod`.org_id = {$org_id}")
                ->where("`iod`.is_enabled = 1");
        if (!empty($status) && $status != 'all') {
            $q->where("`contract_management`.status = '{$status}'");
        }
        if (!empty($supplier)) {
            $q->where("`contract_management`.issue_id = '{$supplier}'");
        }
        if (!empty($group)) {
            $q->where("`iss`.`group_id` = '{$group}'");
        }
        if (!empty($contract_type)) {
            $q->where("`contract_management`.contract_type = '{$contract_type}'");
        }
        if (!empty($end_date)) {
            $q->where("DATE(`contract_management`.end_date) <= '{$end_date}'");
        }
        if (!empty($terminate) && $terminate != 'all') {
            $days = (int) $terminate;
            $q->where("DATE(`contract_management`.terminate_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL {$days} DAY)");
        }
        $q->order("`contract_management`.id DESC");
//        echo $q;
        $result = MVC_Core::$db->query($q);
        $getData = array();
        if ($result && count($result) > 0) {
            foreach ($result as $row) {
                $getData[] = $row;
            }
        }
        return $getData;
    }
    public function getContractManagementCounts($param) {
        $org_id = (int) $param['org_id'];
        $supplier = !empty($param['supplier']) ? $param['supplier'] : '';
        $group = !empty($param['group']) ? $param['group'] : '';
        $contract_type = !empty($param['contract_type']) ? $param['contract_type'] : '';
        $end_date = !empty($param['end_date']) ? $param['end_date'] : '';
        $terminate = !empty($param['terminate']) ? $param['terminate'] : '';

        $q = QueryFactory::select()
                ->table('contract_management', "COUNT(`contract_management`.id) AS total_contracts, 
            SUM( CASE WHEN `contract_management`.status = 'active' THEN 1 ELSE 0 END ) AS total_active,
            SUM( CASE WHEN `contract_management`.status = 'closed' THEN 1 ELSE 0 END ) AS total_closed ")
                ->table(MVC_Core::$db->table('iss'), array('track'), 'LEFT', "`contract_management`.issue_id = `iss`.id")
                ->table(MVC_Core::$db->table('iod'), array('org_id'), 'LEFT', "`contract_management`.issue_id = `iod`.issue_id")
                ->where("`iod`.org_id = {$org_id}")
                ->where("`iod`.is_enabled = 1");
        if (!empty($supplier)) {
            $q->where("`contract_management`.issue_id = '{$supplier}'");
        }
        if (!empty($group)) {
            $q->where("`iss`.`group_id` = '{$group}'");
        }
        if (!empty($contract_type)) {
            $q->where("`contract_management`.contract_type = '{$contract_type}'");
        }
        if (!empty($end_date)) {
            $q->where("DATE(`contract_management`.end_date) <= '{$end_date}'");
        }
        if (!empty($terminate) && $terminate != 'all') {
            $days = (int) $terminate;
            $q->where("
            DATE(`contract_management`.terminate_date)
            BETWEEN CURDATE()
            AND DATE_ADD(CURDATE(), INTERVAL {$days} DAY)
        ");
        }
        $result = MVC_Core::$db->query($q)->current();
        if ($result && count($result) > 0) {
            return $result;
        }else{
            return array(
            'total_contracts' => 0,
            'total_active' => 0,
            'total_closed' => 0
        );
        }
        
    }

    public function getGroupsData($param) {
        $group_type = !empty($param['group_type']) ? $param['group_type'] : '';
        $q = QueryFactory::select()->table('groups', '*');
        if (!empty($group_type)) {
            $q->where("`groups`.type = '{$group_type}'");
        }
        $q->order("`groups`.id DESC");
//    echo $q;
        $result = MVC_Core::$db->query($q);
        $getData = array();
        if ($result && count($result) > 0) {
            foreach ($result as $row) {
                $getData[] = $row;
            }
        }
        return $getData;
    }

//     IWI separate listing page for all contracts 28052026 end
    
//    IWI Make changes in panel for supplier & asset 05062026 start
    public function updateXfield($param) {
         $get_q = QueryFactory::select()
                ->table(MVC_Core::$db->tab['ixf'], 'fk_id,int_value,value')
                ->where("`issue_id` = ?", $param['issue_id'])
                ->where("`fk_id` = ?", $param['fk_id']);

        $get_r = MVC_Core::$db->query($get_q);
        if (!$get_r || count($get_r) == 0) {
            $field_store = array(
                'issue_id' => $param['issue_id'],
                'fk_id' => $param['fk_id'],
                'int_value' => $param['int_value'],
                'value' => $param['value'],
            );
            $q = QueryFactory::insert()
                    ->table(MVC_Core::$db->tab['ixf'])
                    ->values($field_store);
        } else {
            $field_store = array(
                'int_value' => $param['int_value'],
                'value' => $param['value'],
            );
            $q = QueryFactory::update()
                    ->table(MVC_Core::$db->tab['ixf'])
                    ->values($field_store)
                    ->where("`issue_id` = ?", $param['issue_id'])
                    ->where("`fk_id` = ?", $param['fk_id']);
        }
        return MVC_Core::$db->query($q);
    }
    
        
public function getAssetIdBySuppier($supp_link_id) {
        $q = QueryFactory::select()
                ->table('asset_contract_link', 'asset_issue_id')
                ->table(MVC_Core::$db->table('iss'), array('supplier_name'=>'cache_primary'), 'LEFT', "`asset_contract_link`.supplier_issue_id = `iss`.id")
                ->table('contract_management', array('contract_nr','start_date','end_date'), 'LEFT', "`asset_contract_link`.contract_id = `contract_management`.id")
                ->where("`asset_contract_link`.supplier_issue_id = ?", $supp_link_id);
//       echo $q;
        $r = MVC_Core::$db->query($q);        
        return $r;
    }

    public function update_asset_xfield($contract_id,$supplier_issue_id) {

        $q = "SELECT
            cm.contract_nr,
            cm.start_date,
            cm.end_date,
            i.cache_primary AS supplier_name
        FROM contract_management cm
      LEFT JOIN issues i ON i.id = $supplier_issue_id
      WHERE cm.id = $contract_id
      LIMIT 1";
//       echo $q;
        $row = MVC_Core::$db->query($q)->current(); 
       return $row;

    }
 
    
//    IWI Make changes in panel for supplier & asset 05062026 end
    
    
//    IWI Create nested row for contracts in supplier control list page 29062026 start
    public function getContractNestedGridList($track) {
        $q = QueryFactory::select()
                ->table('contract_management', '*')
                ->table('users', array('assign_to_name' => 'name'), 'LEFT', "`users`.id = `contract_management`.assign_to")
                ->where("contract_management.track = ?", $track)
                ->where("contract_management.status = ?", 'active')
                ->order("contract_management.id DESC");
//        echo $q;
        $r = MVC_Core::$db->query($q);
        $getData = array();
        if ($r && count($r) > 0) {
            foreach ($r as $row) {
                $getData[] = $row;
            }
        }
        return $getData;
    }
    
//    IWI Create nested row for contracts in supplier control list page 29062026 end
       
//    IWI separate listing page for all assets�07072026 start
    
    public function getAllAssetManagementData($param) {
        $org_id = (int) $param['org_id'];
        $status = !empty($param['status']) ? $param['status'] : '';
        $supplier_id = !empty($param['supplier_id']) ? $param['supplier_id'] : '';
        $contract_id = !empty($param['contract_id']) ? $param['contract_id'] : '';
        $group = !empty($param['group']) ? $param['group'] : '';
        $end_date = !empty($param['end_date']) ? (int) $param['end_date'] : 0;

        $q = QueryFactory::select()
                ->table('asset_contract_link', '*')

                // Asset
                ->table(MVC_Core::$db->table('iss'),
                        array(
                            'asset_id' => 'track',
                            'asset_name' => 'cache_primary',
                            'group_id'
                        ),
                        'LEFT',
                        "`asset_contract_link`.asset_issue_id = `iss`.id")

                // Contract
                ->table('contract_management',
                        array(
                            'supplier_track' => 'track',
                            'contract_name' => 'contract_name',
                            'contract_nr' => 'contract_nr',
                            'start_date' => 'start_date',
                            'end_date' => 'end_date',
                        ),
                        'RIGHT',
                        "`asset_contract_link`.contract_id = `contract_management`.id")
                ->table(array(MVC_Core::$db->tab['iss'], 'iss2'),
                        array(
                            'supplier_name' => 'cache_primary'
                        ),
                        'LEFT',
                        "`iss2`.track = contract_management.track")
                // Asset Org
                ->table(MVC_Core::$db->table('iod'),
                        array('org_id'),
                        'LEFT',
                        "`asset_contract_link`.asset_issue_id = `iod`.issue_id")

                // Group
                ->table('groups',
                        array('group_name' => 'name'),
                        'LEFT',
                        "`iss`.group_id=`groups`.id")
                ->table(array('issue_fields', 'loc'),
                        array('location' => 'value'),
                        'LEFT',
                        "`loc`.fk_id=3857 AND `loc`.issue_id=`iss`.id")
                ->table(array('issue_fields', 'ser'),
                        array('service_expiry' => 'int_value'),
                        'LEFT',
                        "`ser`.fk_id=3893 AND `ser`.issue_id=`iss`.id")
                ->where("`iod`.org_id={$org_id}")
                ->where("`iod`.is_enabled=1");

        if (!empty($status) && $status != 'all') {
            $q->where("`asset_contract_link`.status='{$status}'");
        }
        if (!empty($supplier_id)) {
            $q->where("`asset_contract_link`.supplier_issue_id='{$supplier_id}'");
        }
        if ($contract_id != '') {
            $q->where("`asset_contract_link`.contract_id='{$contract_id}'");
        }
        if (!empty($group)) {
            $q->where("`iss`.group_id='{$group}'");
        }
        if ($end_date > 0) {

            $today = date('Y-m-d');

            if ($end_date == 30) {
                $future = date('Y-m-d', strtotime('+30 days'));

                $q->where("`contract_management`.end_date >= '{$today}'");
                $q->where("`contract_management`.end_date <= '{$future}'");
            } elseif ($end_date == 60) {

                $future = date('Y-m-d', strtotime('+60 days'));

                $q->where("`contract_management`.end_date >= '{$today}'");
                $q->where("`contract_management`.end_date <= '{$future}'");
            } elseif ($end_date == 90) {

                $future = date('Y-m-d', strtotime('+90 days'));

                $q->where("`contract_management`.end_date >= '{$today}'");
                $q->where("`contract_management`.end_date <= '{$future}'");
            }
        } elseif ($end_date === 0 && isset($param['end_date']) && $param['end_date'] !== '') {

            // Ended contracts
            $today = date('Y-m-d');
            $q->where("`contract_management`.end_date < '{$today}'");
        }
        $q->order("`asset_contract_link`.id DESC");
//        echo $q;
        $result = MVC_Core::$db->query($q);
        $getData = array();
        if ($result && count($result) > 0) {
            foreach ($result as $row) {
                $getData[] = $row;
            }
        }
        return $getData;
    }

    public function getAssetManagementCounts($param)
{
    $org_id      = (int)$param['org_id'];
    $supplier_id = !empty($param['supplier_id']) ? $param['supplier_id'] : '';
    $contract_id = !empty($param['contract_id']) ? $param['contract_id'] : '';
    $group       = !empty($param['group']) ? $param['group'] : '';
    
        $q = QueryFactory::select()
            ->table('asset_contract_link',  array(
                'total_assets' => 'COUNT(`asset_contract_link`.id)',
                'total_active'    => 'SUM(CASE WHEN `asset_contract_link`.status="active" THEN 1 ELSE 0 END)',
                'total_inactive'    => 'SUM(CASE WHEN `asset_contract_link`.status="closed" THEN 1 ELSE 0 END)'
            ))

            // Asset
            ->table(MVC_Core::$db->table('iss'),
                    array(
                    ),
                    'LEFT',
                    "`asset_contract_link`.asset_issue_id = `iss`.id")

            // Contract
            ->table('contract_management',
                    array(),
                    'RIGHT',
                    "`asset_contract_link`.contract_id = `contract_management`.id")
            // Asset Org
            ->table(MVC_Core::$db->table('iod'),
                    array(),
                    'LEFT',
                     "`asset_contract_link`.asset_issue_id = `iod`.issue_id")

            // Group
            ->table('groups',
                    array(),
                    'LEFT',
                    "`iss`.group_id=`groups`.id")

            ->where("`iod`.org_id={$org_id}")
            ->where("`iod`.is_enabled=1");
            
        if (!empty($supplier_id)) {
    $q->where("`asset_contract_link`.supplier_issue_id='{$supplier_id}'");
}
        if ($contract_id != '') {
    $q->where("`asset_contract_link`.contract_id='{$contract_id}'");
}
        if (!empty($group)) {
              $q->where("`iss`.group_id='{$group}'");
        }
        $q->order("`asset_contract_link`.id DESC");
//echo $q;
    $result = MVC_Core::$db->query($q)->current();
        if ($result && count($result) > 0) {
            return $result;
        }else{
            return array(
            'total_assets' => 0,
            'total_active' => 0,
            'total_inactive' => 0
        );
        }
}
    
//    IWI separate listing page for all assets�07072026 end



//IWI contracts report page based on group location 27082026 start
   public function getContractGroupAnalytics() {

    $org_id = MVC_Core::$ss->user['org_id'];

    $q = "SELECT
            g.id AS group_id,
            g.name AS name,

            COUNT(cm.id) AS total_contracts,

            SUM(
                CASE
                    WHEN cm.status = 'active' THEN 1
                    ELSE 0
                END
            ) AS active_contracts,

            SUM(
                CASE
                    WHEN cm.status = 'closed' THEN 1
                    ELSE 0
                END
            ) AS closed_contracts,

            SUM(
                CASE
                    WHEN cm.status = 'active'
                    THEN COALESCE(cm.year_value, 0)
                    ELSE 0
                END
            ) AS annual_value

        FROM contract_management AS cm

        INNER JOIN issues AS i
            ON i.id = cm.issue_id
            AND i.master_org_id = $org_id

        INNER JOIN `groups` AS g
            ON g.id = i.group_id

        GROUP BY
            g.id,
            g.name

        ORDER BY
            g.name";

    $result = MVC_Core::$db->query($q);

    $getData = array();

    if ($result && count($result) > 0) {
        foreach ($result as $row) {
            $getData[] = $row;
        }
    }

    return $getData;
}


public function getContractLocationAnalytics() {

    $org_id = MVC_Core::$ss->user['org_id'];

    $q = "SELECT
            cm.location AS name,

            COUNT(cm.id) AS total_contracts,

            SUM(
                CASE
                    WHEN cm.status = 'active' THEN 1
                    ELSE 0
                END
            ) AS active_contracts,

            SUM(
                CASE
                    WHEN cm.status = 'closed' THEN 1
                    ELSE 0
                END
            ) AS closed_contracts,

            SUM(
                CASE
                    WHEN cm.status = 'active'
                    THEN COALESCE(cm.year_value, 0)
                    ELSE 0
                END
            ) AS annual_value

        FROM contract_management AS cm

        INNER JOIN issues AS i
            ON i.id = cm.issue_id
            AND i.master_org_id = $org_id

        GROUP BY
            cm.location

        ORDER BY
            cm.location";

    $result = MVC_Core::$db->query($q);

    $getData = array();

    if ($result && count($result) > 0) {
        foreach ($result as $row) {
            $getData[] = $row;
        }
    }

    return $getData;
}


public function getContractCountryAnalytics() {

    $org_id = MVC_Core::$ss->user['org_id'];

    $q = "SELECT
            cm.country AS name,

            COUNT(cm.id) AS total_contracts,

            SUM(
                CASE
                    WHEN cm.status = 'active' THEN 1
                    ELSE 0
                END
            ) AS active_contracts,

            SUM(
                CASE
                    WHEN cm.status = 'closed' THEN 1
                    ELSE 0
                END
            ) AS closed_contracts,

            SUM(
                CASE
                    WHEN cm.status = 'active'
                    THEN COALESCE(cm.year_value, 0)
                    ELSE 0
                END
            ) AS annual_value

        FROM contract_management AS cm

        INNER JOIN issues AS i
            ON i.id = cm.issue_id
            AND i.master_org_id = $org_id

        GROUP BY
            cm.country

        ORDER BY
            cm.country";

    $result = MVC_Core::$db->query($q);

    $getData = array();

    if ($result && count($result) > 0) {
        foreach ($result as $row) {
            $getData[] = $row;
        }
    }

    return $getData;
}


public function getContractSummary() {

    $org_id = MVC_Core::$ss->user['org_id'];

    $q = "SELECT

            COUNT(cm.id) AS total_contracts,

            SUM(
                CASE
                    WHEN cm.status = 'active' THEN 1
                    ELSE 0
                END
            ) AS active_contracts,

            SUM(
                CASE
                    WHEN cm.status = 'active'
                    AND cm.end_date IS NOT NULL
                    AND cm.end_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                    AND cm.end_date >= CURDATE()
                    THEN 1
                    ELSE 0
                END
            ) AS expiring_contracts,

            SUM(
                CASE
                    WHEN cm.status = 'active'
                    THEN COALESCE(cm.year_value, 0)
                    ELSE 0
                END
            ) AS annual_value

        FROM contract_management AS cm

        INNER JOIN issues AS i
            ON i.id = cm.issue_id
            AND i.master_org_id = $org_id";

    $result = MVC_Core::$db->query($q);

    $getData = array();

    if ($result && count($result) > 0) {
        foreach ($result as $row) {
            $getData[] = $row;
        }
    }

    return $getData;
}


public function getContractDetails($type, $name) {

    $org_id = MVC_Core::$ss->user['org_id'];

    $q = QueryFactory::select()
            ->table(
                    'contract_management',
                    '*'
            )
            ->table(
                    'issues',
                    array(),
                    'INNER',
                    "`issues`.id = `contract_management`.issue_id
                     AND `issues`.master_org_id = $org_id"
            )
            ->table(
                    'users',
                    array(
                        'assign_to_name' => 'name'
                    ),
                    'LEFT',
                    "`users`.id = `contract_management`.assign_to"
            )
            ->table(
                    'files',
                    array(
                        'filepath',
                        'analyzer',
                        'chash'
                    ),
                    'LEFT',
                    "`files`.id = `contract_management`.files"
            );


    /*
     * GROUP
     */
    if ($type == 'group') {

        /*
         * issues is already joined above.
         * No need to join issues again.
         */
        $q->table(
                'groups',
                array(),
                'INNER',
                "`groups`.id = `issues`.group_id"
        );

        $q->where(
                "`groups`.name = ?",
                $name
        );
    }


    /*
     * LOCATION
     */
    elseif ($type == 'location') {

        $q->where(
                "`contract_management`.location = ?",
                $name
        );
    }


    /*
     * COUNTRY
     */
    elseif ($type == 'country') {

        $q->where(
                "`contract_management`.country = ?",
                $name
        );
    }


    /*
     * Latest contracts first
     */
    $q->order(
            "`contract_management`.id DESC"
    );


    $r = MVC_Core::$db->query($q);

    $getData = array();


    if ($r && count($r) > 0) {

        foreach ($r as $row) {

            /*
             * File ID
             */
            $row['file_id'] = $row['files'];


            /*
             * Expiring flag
             *
             * Active contracts only
             * End date between today and next 90 days
             */
            $row['expiring'] = 0;

            if (
                    $row['status'] == 'active'
                    && $row['end_date'] != ''
                    && $row['end_date'] != null
            ) {

                $endDate = strtotime(
                        $row['end_date']
                );

                $today = strtotime(
                        date('Y-m-d')
                );

                $ninetyDays = strtotime(
                        '+90 days',
                        $today
                );

                if (
                        $endDate >= $today
                        && $endDate <= $ninetyDays
                ) {
                    $row['expiring'] = 1;
                }
            }


            /*
             * File download
             */
            $row['file_download'] = '';

            if (
                    $row['filepath'] != ''
                    && $row['filepath'] != null
            ) {

                /*
                 * IMAGE FILE
                 */
                if ($row['analyzer'] == 'image') {

                    $filepath = $row['filepath'];

                    $file = explode(
                            '/',
                            $filepath
                    );

                    if (count($file) >= 3) {

                        $file2 = $file[0] . $file[1];

                        $fileName = $file[2];

                        $fileName = str_replace(
                                '.png',
                                '.jpg',
                                $fileName
                        );

                        $fileName = substr(
                                $fileName,
                                2
                        );

                        $tr_file_path = '/uimg/thms/'
                                . $file2
                                . '/'
                                . $fileName;

                        $certificate = $tr_file_path;

                        $row['file_download'] =
                                '<a href="' .
                                $certificate .
                                '" download>' .
                                '<img src="/img/icons16/file_extension_zip.png">' .
                                '</a>';
                    }
                }


                /*
                 * NORMAL FILE
                 */
                else {

                    if (
                            $row['chash'] != ''
                            && $row['chash'] != null
                    ) {

                        $row['file_download'] =
                                '<a href="/files?action=download_attachment&ch=' .
                                $row['chash'] .
                                '" download>' .
                                '<img src="/img/icons16/file_extension_zip.png">' .
                                '</a>';
                    }
                }
            }


            $getData[] = $row;
        }
    }


    return $getData;
}

//IWI contracts report page based on group location 27082026 end
}

/**
 * Base interface for Record Plugins
 * - Applies per record type, with the plugin class matching the record type key
 * - Can change certain fields automatically by processing them before being saved in the database
 * - Can add or remove values in record fields based on current user and his/her organization
 * - Can trigger notifications and other actions based on field values and other conditions
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage DataModels
 * @version 2.8
 */
interface App_Plugin_Record
{
    /**
     * Processes the record's data before being created or updated. It can change various parameters, including the main metadata or org-specific data
     * - When updating, not all the data is available (depends on what is updated). Usually xfields are only from the panel being changed.
     * - If the plugin cannot find the data it needs, it should read the record again, then perform the processing and the update (*But ONLY if it needs extra data)
     * @param int $rec_id Record ID. Not always necessary, but useful when the record needs to be read again
     * - If empty, it means the record is being created (first time processing). The plugin might act differently in this case
     * @param array $metadata
     * @param array $xfields
     */
    public function process_Data($rec_id, &$metadata, &$xfields);
}