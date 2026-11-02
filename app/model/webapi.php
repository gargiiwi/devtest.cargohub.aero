<?php
/**
 * "Web API" access credentials - Allows accessing the system through HTTP interface

 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage DataModels
 * @version 2.10
 */
class App_Model_WebAPI extends MVC_Model
{
//----- WebAPI Credentials CRUD ----//

            
    private $dateFrom;
    private $scheduleDate;

    private static $allowed_actions = array(
        'Execute'	// Runs a predefined command which is stored in "webapi_commands"
    );

    /** 
     * Adds an API access for an user. Autogenerates API key and returns it 
     * 
     * @param array $user User information ( as returned by the user model ) 
     */
    public function create($user)
    {
            
    }
	
	// Preliminary Cargo Claims Time Barred Status change older than 2 years and 1 month
	public function preliminary_cargo_claims_time_barred_status () {
		//echo "Preliminary Cargo Claims Time Barred Status";
		$issue = new App_Model_Issue();
		$query1 = MVC_Core::$db->query("UPDATE issue_orgdata AS iso SET iso.status_key = 'tbar' WHERE iso.status_key <> 'tbar' AND iso.process <> 'closed'
AND iso.issue_id IN (SELECT iss.id FROM issues AS iss WHERE iss.id IN (SELECT isf1.issue_id FROM issue_fields AS isf1 WHERE isf1.fk_id = 65 AND isf1.value = 'prel') AND iss.id IN (SELECT isf2.issue_id FROM issue_fields AS isf2 WHERE isf2.fk_id = 2399 AND FROM_UNIXTIME(isf2.int_value) < DATE_SUB(NOW(), INTERVAL 25 MONTH)) )");
		$query2 = MVC_Core::$db->query("UPDATE issue_orgdata AS iso SET iso.process = 'closed' WHERE iso.status_key = 'tbar' AND iso.process <> 'closed'
AND iso.issue_id IN ( SELECT iss.id FROM issues AS iss WHERE iss.id IN (SELECT isf1.issue_id FROM issue_fields AS isf1 WHERE isf1.fk_id = 65 AND isf1.value = 'prel') AND iss.id IN (SELECT isf2.issue_id FROM issue_fields AS isf2 WHERE isf2.fk_id = 2399 AND FROM_UNIXTIME(isf2.int_value) < DATE_SUB(NOW(), INTERVAL 25 MONTH)) );");
		$query3 = MVC_Core::$db->query("UPDATE issue_orgdata AS iso SET iso.status_key = 'tbar' WHERE iso.status_key <> 'tbar' AND iso.process <> 'closed'
AND iso.issue_id IN ( SELECT iss.id FROM issues AS iss WHERE FROM_UNIXTIME(iss.ctime) < DATE_SUB(NOW(), INTERVAL 25 MONTH) AND iss.id IN (SELECT isf1.issue_id FROM issue_fields AS isf1 WHERE isf1.fk_id = 65 AND isf1.value = 'prel') AND iss.id NOT IN (SELECT isf2.issue_id FROM issue_fields AS isf2 WHERE isf2.fk_id = 2399) );");
		$query4 = MVC_Core::$db->query("UPDATE issue_orgdata AS iso SET iso.process = 'closed' WHERE iso.status_key = 'tbar' AND iso.process <> 'closed'
AND iso.issue_id IN ( SELECT iss.id FROM issues AS iss WHERE FROM_UNIXTIME(iss.ctime) < DATE_SUB(NOW(), INTERVAL 25 MONTH) AND iss.id IN (SELECT isf1.issue_id FROM issue_fields AS isf1 WHERE isf1.fk_id = 65 AND isf1.value = 'prel') AND iss.id NOT IN (SELECT isf2.issue_id FROM issue_fields AS isf2 WHERE isf2.fk_id = 2399) );");
                        
                        
	   return true;
	}
	
	// Seconds to Days function
	function secondsToDays($seconds){
		return floor($seconds/86400);
	}
	
	// Update MRN Open Days as per MRN Date in MRN Monitor 
	public function mrn_opendays_update () {
		//echo "Update MRN Open Days as per MRN Date in MRN Monitor ";
		$issue = new App_Model_Issue();
		// Gettings issues id for MRN Date
		$issues_data = MVC_Core::$db->query("select issue_id, fk_id, int_value from issue_fields where fk_id in (2242)");  
		foreach ($issues_data as $issue_key => $issue_details) {
			$check_opendays_data = MVC_Core::$db->query("select issue_id, fk_id, int_value from issue_fields where fk_id = 3505 and issue_id = " . $issue_details['issue_id'] );
			// Calculate Days
			$seconds = time() - $issue_details['int_value'];
			$days = $this->secondsToDays($seconds);	
			if ($check_opendays_data->num_rows >= 1) {
				//if ($check_opendays_data) {
				//print "UPDATE issue_fields SET int_value = " . $days . " where fk_id = 3505 and issue_id = " . $issue_details['issue_id'];
				$update_qry = MVC_Core::$db->query("UPDATE issue_fields SET int_value = " . $days . " where fk_id = 3505 and issue_id = " . $issue_details['issue_id']);
			}
			else {
				//print "INSERT INTO issue_fields (issue_id, fk_id, int_value) VALUES ( " . $issue_details['issue_id'] . " , 3505, " . $days . ") ";
				$insert_qry = MVC_Core::$db->query("INSERT INTO issue_fields (issue_id, fk_id, int_value) VALUES ( " . $issue_details['issue_id'] . " , 3505, " . $days . ") ");
			}
			print "<br/>";
		}

	   return true;
	}

// 	select if2.issue_id,int_value from issue_fields if2  join issue_xfdef ix on ix.id =if2.fk_id where if2.fk_id =3199 and  int_value =1;
    public function get_Records_Which_has_to_run(){
        $issue_TO_RUN = new App_Model_Issue();
        //aud_Repl
        $xfDefRes = $issue_TO_RUN->get_XFDef_Value("aud_Repl",34);
        $audRepl_Id = $xfDefRes['id'];

        // it is checking aud_Repl column in fields table
        $r= MVC_Core::$db->query("select if2.issue_id,int_value,i.track from issue_fields if2  join issue_xfdef ix on ix.id =if2.fk_id join issues i on i.id=if2.issue_id  where if2.fk_id =$audRepl_Id and  int_value =0");
        return $r;
    }

    public function check_duplicateRecord()
    {
        $issue = new App_Model_Issue();
        $parentTrack = "";
        $has_Child = "";
        $rec_Pos = "";
        $child_Of = "";
        $aud_Repl = "";
        $schedule_rep = "";
        $date_start = "";
        $date_end = "";
        $scheduled_date = "";
        $res_XFDEF = $issue->get_XFDef_Value_Array(34);

        foreach($res_XFDEF as $xfData) {

            if($xfData['fk']=="parent_Track"){
                $parentTrack = $xfData['id'];
            }
            if($xfData['fk']=="has_Child"){
                $has_Child = $xfData['id'];
            }
            if($xfData['fk']=="rec_Pos"){
                $rec_Pos = $xfData['id'];
            }
            if($xfData['fk']=="child_Of"){
                $child_Of = $xfData['id'];
            }
            if($xfData['fk']=="aud_Repl"){
                $aud_Repl = $xfData['id'];
            }
            if($xfData['fk']=="sched_rep"){
                $schedule_rep = $xfData['id'];
            }
            if($xfData['fk']=="date_start"){
                $date_start = $xfData['id'];
            }
            if($xfData['fk']=="date_end"){
                $date_end = $xfData['id'];
            }
            if($xfData['fk']=="scheduled_date"){
                $scheduled_date = $xfData['id'];
            }
        }


        $fieldArray = array(
            "schedule_rep" => $schedule_rep, // audit record repetation
            "record_rep" => $aud_Repl, // repetation done or not,
            "has_Child" => $has_Child
        );
        // has child or not
        $res_get_Records = $this->get_Records_Which_has_to_run();
        /*  print_r($res_get_Records);

         foreach ($res_get_Records as $key => $value) {
             print_r($value);
         }
         exit; */
        foreach ($res_get_Records as $key => $value) {


            $trackId = $value['track'];
            $recordRes = $this->get_Data_BasedOn_Field($trackId, $fieldArray['record_rep']);
            $details = array(
                "0" => "",
                "1" => "daily",
                "7" => "weekly",
                "30" => "monthly",
                '91' => "quarterly",
                "365" => "yearly"
            );
            if (count($recordRes) > 0) {
                // will check what replication status if it is stated as 0 means need to create if it is there as 1 no need to create
                if ($recordRes['int_value'] != 1) {

                    $issue_Id = $recordRes['id'];

                    // trying to get repetation value by using track
                    $repetation_Validation_Res = $this->get_Data_BasedOn_Field($trackId, $fieldArray['schedule_rep']);
                    $repetation = $repetation_Validation_Res['int_value'];

                    // trying to get start date and end date of particualr record by using track
                    $dateValues = $this->get_Date_Details($trackId);
                    $dateStart = "";
                    $dateEnd = "";
                    $scheduleDateFromDb = "";
                    foreach ($dateValues as $dateKey => $dateValue) {
                        if ($dateValue['fk'] == 'date_start') {
                            $dateStart = $dateValue['date'];
                        }
                        if ($dateValue['fk'] == 'date_end') {
                            $dateEnd = $dateValue['date'];
                        }
                        if ($dateValue['fk'] == 'scheduled_date') {
                            $scheduleDateFromDb = $dateValue['date'];
                        }
                    }
                    if ($dateStart != "" && $dateEnd != "") {
                        // trying to get date diff between start date and end date.date_diff is the php function
                        $datediff = date_diff(date_create($dateStart), date_create($dateEnd));

                        // in the database repetation value is by default zero '0' if it is zero not allowed to create child record for parent record
                        if ($repetation > 0) {

                            // here we are trying to get repetation number for ex: if the repetation is one week and the date diff will be 14days the operation will be
                            // 14/7=2 in this scenarion will create 2 records as per the reponse of caliculation.
                            $repValue = round($datediff->days / $repetation);
                            // as per the repetation value the loop should iterate for above explanation the loop will iterate over 2 times
                            for ($i = 0; $i < $repValue; $i ++) {
                                // By using below functionality we will add number of days to start date
                                if ($this->dateFrom == "") {
                                    $this->dateFrom = date('Y-m-d', strtotime($dateStart . '+ ' . $repetation . 'days'));
                                } else {
                                    $this->dateFrom = date('Y-m-d', strtotime($this->dateFrom . '+ ' . $repetation . 'days'));
                                }
                                if ($this->dateFrom > $dateEnd) {
                                    break;
                                }
                                if($this->scheduleDate == ""){
                                    $this->scheduleDate = date('Y-m-d', strtotime($scheduleDateFromDb . '+ ' . $repetation . 'days'));
                                }else{
                                    $this->scheduleDate = date('Y-m-d', strtotime($this->scheduleDate . '+ ' . $repetation . 'days'));
                                }
                                $track = $issue->generate_TrackID();
                                $submit_ip = sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']));
                                $access_key = sha1(microtime(1) . uniqid());
                                $ctime = time();
                                $masterOrgId = $recordRes['master_org_id'];
                                $groupId = $recordRes['group_id'];
                                $submitId = $recordRes['submit_id'];
                                $submitDomain = $recordRes['submit_domain'];
                                $submitOrgId = $recordRes['submit_org_id'];
                                $submitId = $recordRes['submit_id'];
                                $submitIp = $recordRes['submit_ip'];
                                $cachePrimary = $recordRes['cache_primary'];
                                $cacheSecondary = $recordRes['cache_secondary'];
                                $cacheTertiary = $recordRes['cache_tertiary'];
                                $cacheQuaternary = $recordRes['cache_quaternary'];
                                $cacheQuinary = $recordRes['cache_quinary'];
                                $suiLmid = $recordRes['sui_lmid'];
                                $suiLmdt = $recordRes['sui_lmdt'];
                                $suiLmctx = $recordRes['sui_lmctx'];
                                $issInsert = QueryFactory::insert()->table(MVC_Core::$db->tab['iss'])->values(array(
                                        "track" => $track,
                                        "access_key" => $access_key,
                                        "type_id" => 34,
                                        "ctime" => $ctime,
                                        "master_org_id" => $masterOrgId,
                                        "group_id" => $groupId,
                                        "submit_id" => $submitId,
                                        "submit_domain" => $submitDomain,
                                        "submit_org_id" => $submitOrgId,
                                        "submit_id" => $submitId,
                                        "submit_ip" => $submitIp,
                                        "cache_primary" => $cachePrimary,
                                        "cache_secondary" => $cacheSecondary,
                                        "cache_tertiary" => $cacheTertiary,
                                        "cache_quaternary" => $cacheQuaternary,
                                        "cache_quinary" => $cacheQuinary,
                                        "sui_lmid" => $suiLmid,
                                        "sui_lmdt" => $suiLmdt,
                                        "sui_lmdt" => $suiLmctx
                                    )
                                );
                                $qw = MVC_Core::$db->query($issInsert);
                                $lastInsertId = MVC_Core::$db->insert_id;

                                $qu = "select " . $lastInsertId . " as issue_id ,if2.fk_id,if2.int_value,if2.value from " . MVC_Core::$db->tab['ixf'] . " if2 where issue_id =" . $issue_Id;
                                $res = MVC_Core::$db->query($qu);
                                foreach ($res as $key => $value) {
                                    //aud_Repl
                                    if ($value['fk_id'] == $aud_Repl) {
                                        $value['issue_id'] = $lastInsertId;
                                        $value['fk_id'] = $aud_Repl;
                                        $value['int_value'] = 1;
                                        $value['value'] = NULL;
                                    }
                                    //sched_rep
                                    /* if ($value['fk_id'] == $schedule_rep) {
                                         $value['issue_id'] = $lastInsertId;
                                         $value['fk_id'] = $schedule_rep;
                                         $value['int_value'] = 0;
                                         $value['value'] = NULL;
                                     }*/
                                    // date_start
                                    if ($value['fk_id'] == $date_start) {
                                        $value = "";
                                    }
                                    // date_end
                                    if ($value['fk_id'] == $date_end) {
                                        $value = "";
                                    }
                                    //Scheduled Date:
                                    if ($value['fk_id'] == $scheduled_date) {
                                        $value['issue_id'] = $lastInsertId;
                                        $value['fk_id'] = $scheduled_date;
                                        $value['int_value'] = strtotime($this->scheduleDate);
                                        $value['value'] = NULL;
                                    }
                                    //child_Of
                                    if ($value['fk_id'] == $child_Of) {
                                        $value['issue_id'] = $lastInsertId;
                                        $value['fk_id'] = $child_Of;
                                        $value['int_value'] = $issue_Id;
                                        $value['value'] = NULL;
                                    }
                                    // will define parent or child
                                    //rec_Pos
                                    if ($value['fk_id'] == $rec_Pos) {
                                        $value['issue_id'] = $lastInsertId;
                                        $value['fk_id'] = $rec_Pos;
                                        $value['int_value'] = 0;
                                        $value['value'] = "chi";
                                    }
                                    if ($value != "" || ! empty($value)) {
                                        $quu = QueryFactory::insert()->table(MVC_Core::$db->tab['ixf'])->values($value);
                                        MVC_Core::$db->query($quu);
                                    }
                                }
                                // $parent_Track=array();
                                // parent_Track	Parent_Track_Details
                                $parent_Track['fk_id'] = $parentTrack;
                                $parent_Track['issue_id'] = $lastInsertId;
                                $parent_Track['int_value'] = "";
                                $parent_Track['value'] = $trackId;
                                if ($parent_Track != "") {
                                    $parent_Track_Insertion = QueryFactory::insert()->table(MVC_Core::$db->tab['ixf'])->values($parent_Track);
                                    MVC_Core::$db->query($parent_Track_Insertion);
                                }
                                // To get contact data related to record based on record id(issue id)
                                $contactData = $this->get_Issue_RelateData($issue_Id, "contact");
                                $contactResult = array();
                                $contactResult['issue_id'] = $lastInsertId;
                                $contactResult['contact_id'] = $contactData['contact_id'];
                                $contactResult['role'] = $contactData['role'];
                                $contactResult['dt_link'] = $ctime;
                                $contactResult['label'] = $contactData['label'];
                                $contactResult['visibility'] = $contactData['visibility'];
                                $contactResult['org_id'] = $contactData['org_id'];
                                $contactResult['user_id'] = $contactData['user_id'];

                                $contactQuery = QueryFactory::insert()->table(MVC_Core::$db->tab['c2i'])->values($contactResult);
                                MVC_Core::$db->query($contactQuery);
                                $orgResult = array();
                                // To get org data related to record based on record id(issue id)
                                $orgResult = $this->get_Issue_RelateData($issue_Id, "org");

                                $orgResult['issue_id'] = $lastInsertId;
                                $orgResult['linktime'] = $ctime;
                                $orgResult['utime'] = NULL;

                                $orgDataQuery = QueryFactory::insert()->table(MVC_Core::$db->tab['iod'])->values($orgResult);
                                MVC_Core::$db->query($orgDataQuery);
                            }

                            $iss_FieldUp = QueryFactory::update()->table(MVC_Core::$db->tab['ixf'])
                                ->where("`issue_id` = ?", $issue_Id)
                                ->where("`fk_id` = ?", $fieldArray['record_rep'])
                                ->values(array(
                                    'int_value' => 1
                                ));

                            MVC_Core::$db->query($iss_FieldUp);

                            $iss_Filed_Has_Child = QueryFactory::update()->table(MVC_Core::$db->tab['ixf'])
                                ->where("`issue_id` = ?", $issue_Id)
                                ->where("`fk_id` = ?", $fieldArray['has_Child'])
                                ->values(array(
                                    'value' => 'y'
                                ));

                            MVC_Core::$db->query($iss_Filed_Has_Child);
                        } else {
                            echo "<h1>Record does not have any repetation, not able to replicate the record<h1> ".$trackId;
                        }
                    } else {
                        echo "<h1>For this record both start date and end date should be there.<h1> ".$trackId;
                    }
                } else {
                    echo "<h1>Record already replicated no use to give again<h1> ".$trackId;
                }
            } else {
                echo "<h1>Record not found for this track</h1> ".$trackId;
            }
        }
    }

    public function get_Data_BasedOn_Field($trackId,$value){
        $q = "select id, access_key, type_id, ctime , utime, edtime, master_org_id, shared_org_ids, group_id, submit_domain, submit_org_id, submit_id, submit_ip, cache_primary, cache_secondary, cache_tertiary, cache_quaternary, cache_quinary, c_convos, c_files , c_tasks, c_docs , c_ctx, c_updates , sui_lmid , sui_lmdt, sui_lmctx, fk_id, int_value  from " . MVC_Core::$db->tab['iss'] . "  join " . MVC_Core::$db->tab['ixf'] . " on issue_id = id where type_id = 34 and fk_id = " . $value . " and track = " . "'" . $trackId . "'";
        $res = MVC_Core::$db->query($q);
        return $res->current();
    }

    public function get_Issue_RelateData($issueId,$type){
        if ($type == 'contact') {
            $q = QueryFactory::select()->table(MVC_Core::$db->tab['c2i'])->where("`issue_id` = ?", $issueId);
            $res = MVC_Core::$db->query($q);
        } else {
            $q = QueryFactory::select()->table(MVC_Core::$db->tab['iod'])->where("`issue_id` = ?", $issueId);
            $res = MVC_Core::$db->query($q);
        }
        return $res->current();
    }


    public function get_Date_Details($track){
        $q="select FROM_UNIXTIME(if2.int_value,'%Y-%m-%d')date,ix.fk,i.track from issues i join issue_fields if2 on if2.issue_id = i.id join issue_xfdef ix on ix.id = if2.fk_id where ix.fk in ('date_start', 'date_end','scheduled_date') and i.type_id = 34 and  track = " . "'" . $track . "'" ;
        $res = MVC_Core::$db->query($q);
        return $res;
    }
    /**
     * Checks if an API key exists and returns the entry if found and the specified function is allowed.
     * -- If "calls" >= "call_limit" it denies access
     * (read)
     *
     * @param string $key
     */
    public function check($key, $action)
    {
        $q=QueryFactory::select()
            -> table(MVC_Core::$db-> tab['api'])
            -> where("`key` = ?", $key)
            -> limit(1);

        $r = MVC_Core::$db-> query($q);

        if(!$r || count($r)==0)
            return MVC_Core::$debug-> dump("Key not found", __METHOD__, null, 'error');

        $api_check = $r-> current();

        if(!$api_check['enabled'])
            return MVC_Core::$debug-> dump(sprintf("DENIED - API Call %s is currently not enabled", $key), __METHOD__, null, 'error');

        if($api_check['calls'] >= $api_check['call_limit'])
            return MVC_Core::$debug-> dump(sprintf("DENIED - Key call limit reached: %d", $api_check['call_limit']), __METHOD__, null, 'error');

        // Check if function actually exists
        if(!in_array($action, self::$allowed_actions))
            return MVC_Core::$debug-> dump(sprintf("Action '%s' is not supported", $action), __METHOD__, null, 'error');

        // If no functions are specified, full API access is permited (no restrictions)
        if(!empty($api_check['actions'])) {
            $api_check['actions'] = explode(',', $api_check['actions']);
            if(!in_array($action, $api_check['actions']))
                return MVC_Core::$debug-> dump(sprintf("DENIED - Action '%s' not permitted for this Key", $action), __METHOD__, null, 'error');
        }

        return $api_check;
    }

    /**
     * Updates information for an API Key
     *
     * @param string $key
     * @param array $update_data
     * @param array $log_data If non-null, what information to put in the WebAPI log with the update (usually indicates the purpose of this update)
     */
    public function update($key, $update_data, $log_data = null)
    {
        // filter out keys which do not belong in the organizations table (or can't be updated by this method)
        $fields = array('enabled', 'user_id', 'org_id', 'dep_id', 'actions', 'calls', 'call_warn', 'call_limit'); // Total calls can't be updated directly, it only increases with "calls"
        foreach($update_data as $field=> $value)
            if(!in_array($field, $fields))
                unset($update_data[$field]);

        if(isset($update_data['calls'])) {
            if($update_data['calls'] > 0) {
                $calls_inc = (int)$update_data['calls'];
                $update_data['total_calls'] = "`total_calls`+ {$calls_inc}";
                $update_data['calls'] = "`calls`+ {$calls_inc}";
            } else
                $update_data['calls'] = 0; // Anything not positive resets the calls counter
        }

        $q=QueryFactory::update()
            -> table(MVC_Core::$db-> tab['api'])
            -> where("`key` = ?", $key)
            -> limit(1)
            -> values($update_data, null, true);

        // TODO: If calls cross the "call_warn" - Trigger a predefined warning notification to the user (which might also create an e-mail)

        return MVC_Core::$db-> query($q);
    }

    /**
     * Cancels access to the WebAPI for a key
     *
     * @param unknown $rule_id
     */
    public function delete($key)
    {

    }

    /**
     * Connector used to grab the data - how the data is read or written
     * (At the moment only the "provider" plugin is supported, which is basically a loader for a form submission, which includes uploaded files as well)
     *
     * @var App_Plugin_WebAPI_connector
     */
    protected $plugin_connector = null;

    /**
     * CODEC used to understand the format of the data, after it has been obtained by the connector - Usually transforms text or binary formats into PHP variables
     * Some data codes are natively supported by PHP ( like JSON, XML, while others require a library or extra code: YAML, CSV, XLSX... )
     *
     * @var App_Plugin_WebAPI_codec
     */
    protected $plugin_codec = null;

    /**
     * Maps the obtained PHP variable into a format recognized by the "function" plugin, allowing flexibility of incoming data
     * @var unknown
     */
    protected $plugin_mapper = null;

    /**
     * Function specified in the command (May be an incoming or outgoing data flow)
     * (for now, the only one supported is "recimport", which creates records out of the loaded data
     *
     * @var App_Plugin_WebAPI_fn
     */
    protected $plugin_fn = null;

    /**
     * Plugins can store and update information here, which then will be added to the final success (or failure) report
     * @var array
     */
    public $plugin_report;

    /**
     * Executes a predefined API Command, decodes any configuration and loads the appropriate plugins
     *
     * @param string $command
     * @param int $org_id For which organization is this command run
     * @param int $user_id User account which is running the request. All operations are marked as being performed by specified user
     * -- If missing, no user is being specified (some commands are system only)
     */
    public function execute_Command($command, $org_id, $user_id = null)
    {
        $_ut = microtime(true);
        $this-> plugin_report = array();

        if(empty($org_id))
            return MVC_Core::$debug-> dump("Predefined commands require an Organization ID", __METHOD__, null, 'error');

        if(empty($command))
            return MVC_Core::$debug-> dump("Missing the name of the predefined command", __METHOD__, null, 'error');

        $q=QueryFactory::select()
            -> table(MVC_Core::$db-> tab['apc'])
            -> where("`org_id` = ?", $org_id)
            -> where("`command` = ?", $command)
            -> limit(1);

        $r = MVC_Core::$db-> query($q);
        if(!$r || count($r)==0)
            return MVC_Core::$debug-> dump("Command '{$command}' not found", __METHOD__, null, 'error');

        // Command found ! Instance the necessary plugins (fn, connector, codec, mapper)
        $cmd = $r-> current();

        $this-> plugin_fn = MVC_Core::factory_Plugin("WebAPI_fn", $cmd['function'], empty($cmd['function_cfg'])? null: @json_decode($cmd['function_cfg'], true), $this);
        $this-> plugin_connector = MVC_Core::factory_Plugin("WebAPI_connector", $cmd['connector'], empty($cmd['connector_cfg'])? null: @json_decode($cmd['connector_cfg'], true), $this);
        $this-> plugin_codec = MVC_Core::factory_Plugin("WebAPI_codec", $cmd['codec'], empty($cmd['codec_cfg'])? null: @json_decode($cmd['codec_cfg'], true), $this);
        // It's possible that there is no mapper - Try to load one only if a mapper is specified
        if(!empty($cmd['mapper']))
            $this-> plugin_mapper = MVC_Core::factory_Plugin("WebAPI_mapper", $cmd['mapper'], empty($cmd['mapper_cfg'])? null: @json_decode($cmd['mapper_cfg'], true), $this);

        // Everything loaded?
        if(empty($this-> plugin_fn) || empty($this-> plugin_connector) || empty($this-> plugin_codec))
            return false; //MVC_Core::$debug-> dump("Error while initializing plugin chain", __METHOD__, null, 'error');

        $mapper_fails = 0;
        $function_fails = 0;
        // Yes ! Begin the operations. First, ask the "function" for the direction of the chain ( in, out )
        $datarows = 0;
        if($this-> plugin_fn-> direction == "in") {
            // Importing data - 1. Connector, 2. Codec, 3. Mapper, 4. Run function

            // 1. Load the data through the Connector
            $dataset = $this-> plugin_connector-> incoming();
            if(empty($dataset))
                return MVC_Core::$debug-> dump("WebAPI Connector '{$cmd['connector']}' failed or has no data", __METHOD__, null, 'error');

            // Connectors can return more than one set of data. All the subsequent operations apply for each set of the data individually
            foreach($dataset as $data) {
                // 2. Decode the loaded data through the Codec
                $decoded_data = $this-> plugin_codec-> decode($data);
                if(empty($decoded_data))
                    return MVC_Core::$debug-> dump("WebAPI CODEC '{$cmd['codec']}' failed", __METHOD__, null, 'error');
                // To save memory, $data is removed once $decoded_data is available
                unset($data);

                // Is this single-record data or multiple rows ?
                if(!empty($this-> plugin_mapper))
                    $this-> plugin_mapper-> meta_prepare($decoded_data['meta']);
                    
                     
                    
                if(isset($decoded_data['meta']['structure']) && $decoded_data['meta']['structure'] == 'matrix') {
                
                    // Multiple-Elements of data inside an entry of the Dataset, must map and execute function for each element one individually
                    $datarows+= count($decoded_data['contents']);
                     
                    foreach($decoded_data['contents'] as $datarow) {
                        // 3. Map the data through the Mapper (if there is one)
                        if(!empty($this-> plugin_mapper)) {
                            $mapped_data = $this-> plugin_mapper-> incoming($datarow);
                           
                            if(empty($mapped_data)) {
                                MVC_Core::$debug-> dump("WebAPI Mapper '{$cmd['mapper']}' failed", __METHOD__, null, 'error');
                                $mapper_fails++;
                                continue;
                            }
                            // To save memory, $decoded_data is removed once $mapped_data is available
                        } else
                            $mapped_data = $datarow;
                        // 4. Finally, run the Function
                       
                        $res = $this-> plugin_fn-> execute($mapped_data, $org_id, $user_id);
                         
                        if(empty($res)) {
                            MVC_Core::$debug-> dump("WebAPI Function '{$cmd['function']}' failed", __METHOD__, null, 'error');
                            $function_fails++;
                            continue;
                        }
                    }
                } else {
                    // Data is Single-Element - Map directly and execute
                 
                 
                       $datarows++;
                    // 3. Map the data through the Mapper (if there is one)
                    if(!empty($this-> plugin_mapper)) {
                  
                        $mapped_data = $this-> plugin_mapper-> incoming($decoded_data['meta'], $decoded_data['contents']);
                        if(empty($mapped_data))
                            return MVC_Core::$debug-> dump("WebAPI Mapper '{$cmd['mapper']}' failed", __METHOD__, null, 'error');
                        // To save memory, $decoded_data is removed once $mapped_data is available
                        unset($decoded_data);
                    } else
                    
                      
                        $mapped_data = &$decoded_data['contents'];


                    // 4. Finally, run the Function
                    $res = $this-> plugin_fn-> execute($mapped_data, $org_id, $user_id, $this);
                   
                    
                    if(empty($res))
                        return MVC_Core::$debug-> dump("WebAPI Function '{$cmd['function']}' failed", __METHOD__, null, 'error');

                    // 5. If Original Stream is present, run a "process_stream" method in the Function
                    // WebAPI assumes the Function already saved necessary data from the "execute" in order for this part to complete correctly
                    if(isset($decoded_data['stream'])) {
                      echo "execute "; 
                    
                    var_dump($decoded_data['stream']);
                    
                        $res = $this-> plugin_fn-> process_stream($decoded_data['stream'], $decoded_data['meta']);
                        if(empty($res))
                            MVC_Core::$debug-> dump("WebAPI Function - Process Stream '{$cmd['function']}' failed", __METHOD__, null, 'notice');
                    }
                }
            }

            // When done, call the disconnect() method from the connector in order to close any open files or connections
            $this-> plugin_connector-> disconnect();

        } elseif($this-> plugin_fn-> direction == "out") {
            // Exporting data - 1. Run function, 2. Mapper, 3. Codec, 4. Connector
        } else {
            // This function doesn't deal with data import/export but instead does something else
        }

        $report = array(
            'runtime'=> microtime(true) - $_ut,
            'function'=> $cmd['function'],
            'direction'=> $this-> plugin_fn-> direction,
            'datarows'=> $datarows
        );
        if($mapper_fails > 0)
            $report['mapper_fails'] = $mapper_fails;
        if($function_fails > 0)
            $report['function_fails'] = $function_fails;
        if(!empty($this-> plugin_report))
            $report['plugin_report'] = $this-> plugin_report;

        return $report;
    }

    /**
     * Logs result of an API calls or errors encountered during it's execution ( necessary because WebAPI doesn't have visual reporting )
     *
     * @param int $org_id
     * @param int $user_id
     * @param string $action At the moment the only action is "Execute" command ( which triggers "run_Command" )
     * @param string $logtype
     * @param string $data JSON encoded log data
     */
    public function log_Entry($action, $org_id, $user_id, $entry_type = 'info', $data = null)
    {
        if(!in_array($entry_type, array('info', 'success', 'warning', 'error', 'critical', 'access'))) {
            if(empty($data))
                $data = array();
            $data['invalid_entry_type'] = $entry_type;
            $entry_type = "error";
        }

        $values = array(
            'action'=> $action,
            'org_id'=> $org_id,
            'user_id'=> $user_id,
            'entry_type'=> $entry_type
        );
        if(isset($_SERVER['REMOTE_ADDR']))
            $values['ip'] = sprintf('%u', ip2long($_SERVER['REMOTE_ADDR']));

        if(!is_null($data))
            $values['data'] = json_encode($data);

        $qi=QueryFactory::insert()
            -> table(MVC_Core::$db-> tab['apl'])
            -> values($values);

        $res = MVC_Core::$db-> query($qi);
        return $res? MVC_Core::$db-> insert_id: false;
    }

    /**
     * Calls an existing command from one of the plugins, from another plugin (usually function could call back on connector or codec)
     * @param string $plugin
     * @param string $command All callable commands start with 'cmd_'; Other methods from the plugins can't be called directly
     * @param mixed $data
     */
    public function callback_Plugin($plugin, $command, $data)
    {
        switch($plugin) {
            case 'connector':
                if(is_object($this-> plugin_connector) && method_exists($this-> plugin_connector, 'cmd_'.$command))
                    return call_user_func(array($this-> plugin_connector, 'cmd_'.$command), $data);
                else
                    return false;
            case 'codec':
                if(is_object($this-> plugin_codec) && method_exists($this-> plugin_codec, 'cmd_'.$command))
                    return call_user_func(array($this-> plugin_codec, 'cmd_'.$command), $data);
                else
                    return false;
            default:
                return false; // $plugin Must be one of the 4
        }
    }
    
    
     
//         IWI create api for getting record details start
        public function getRecordDetails($awb_id,$secretkey) {
            
            
            $q_secret_key=QueryFactory::select()
		-> table('org_api_secret_key')
		-> where("`secret_key` = ?", $secretkey)
		-> where("`status` = ?", '1')
		-> limit(1);

		$r_secret_key = MVC_Core::$db-> query($q_secret_key);
		if ($r_secret_key && count($r_secret_key)<1) {
                   return array('status'=>false,'msg'=>'HTTP/1.0 400 WebAPI - Key Authentication failure');  
                   exit();
                }
                $org_secret_key = $r_secret_key-> current();
		
            $org_id=$org_secret_key['org_id'];
            
        $q = <<<SQL
SELECT 
    iss.id,
    iss.track,
    iss.ctime,
    iss.utime,
    iss.master_org_id,
    iod.status_key AS status_key,
    ifd.value AS awb_id,
    ifd2.value AS info_claimant,
    TRIM(BOTH '"' FROM 
        SUBSTRING_INDEX(
            SUBSTRING_INDEX(ixfld.cd, CONCAT(ifd2.value, ':'), -1),
            '"',
            1
        )
    ) AS info_claimant_full
FROM issues AS iss
LEFT JOIN issue_orgdata AS iod 
    ON iss.id = iod.issue_id 
   AND iod.org_id = iss.master_org_id
LEFT JOIN issue_fields AS ifd 
    ON iss.id = ifd.issue_id 
   AND ifd.fk_id = 3
LEFT JOIN issue_fields AS ifd2 
    ON iss.id = ifd2.issue_id 
   AND ifd2.fk_id = 3174
LEFT JOIN issue_xfdef AS ixfld 
    ON ixfld.id = 3174
WHERE ifd.value = '$awb_id' AND iss.master_org_id='$org_id'
SQL;
//echo $q;
        $r = MVC_Core::$db->query($q);
        $res = array();
        $finalData = array();
        $res['count'] = count($r);
        if ($r && count($r)) {                
        MVC_NLS::load_Groups('issue');
        $statuses = MVC_NLS::get_Object("issue.data.statuses", true);
            foreach ($r as $row) {
//                print_r($row);
                $status = !empty($row['status_key']) && isset($statuses[$row['status_key']]) ? $statuses[$row['status_key']] : "";
                $finalData[] = array(
                    'submitted_on_date' => date("Y-m-d H:i:s", $row['ctime']),
                    'updated_on_date' =>$row['utime'],
                    'tracking_id' => $row['track'],
                    'handling_status' => $status,
                    'info_for_claimant' => $row['info_claimant_full'],
                    'awb_id' => $row['awb_id'],
                );
            }
            $res['data'] = $finalData;
        }
        return $res;
    }
    
//     IWI create api for getting record details end
    
    
    
    
    
//    IWI API integration to fetch record details for air cargo claim by track id 21042026 start

    public function getAPISecretKeyByOrg($org_id) {
        $q = QueryFactory::select()
                ->table('org_api_secret_key')
                ->where("`org_id` = ?", $org_id)
                ->where("`status` = ?", '1')
                ->limit(1);
        $r = MVC_Core::$db->query($q);

        if ($r && count($r) < 1) {
            return $r;
        } else {
            $org_secret_key = $r->current();
            return $org_secret_key['secret_key'];
        }
    }

    public function get_IssueData_ByOrg($org_id, $limit = 250, $last_id = 0) {
        $q = "SELECT DISTINCT `iss`.`cache_primary` as awb_no,`iss`.id, `iss`.track  FROM `issues` AS iss LEFT JOIN `comm_contacts` AS subm ON `iss`.submit_id = `subm`.id LEFT JOIN `organizations` AS subo ON `iss`.submit_org_id = `subo`.id RIGHT JOIN `issue_orgdata` AS iod ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = $org_id AND `iod`.is_enabled = 1) WHERE `iss`.type_id = 1 AND `iss`.id >$last_id   ORDER BY `iss`.id ASC LIMIT $limit";
//     ORDER BY `iss`.id DESC LIMIT 25
        $r = MVC_Core::$db->query($q);
        return $r;
    }

    public function getAllTrackbyAWB($awb_id, $org_id) {
        $q = "SELECT iss.track "
                . "FROM issues AS iss "
                . "LEFT JOIN issue_orgdata AS iorg ON iss.id = iorg.issue_id "
                . "LEFT JOIN issue_fields AS ifk_awb ON ( iss.id = ifk_awb.issue_id AND ifk_awb.fk_id='3' ) "
                . "WHERE iss.type_id = '1' AND iorg.org_id='$org_id' AND ifk_awb.value='$awb_id'";
        $r = MVC_Core::$db->query($q);
        return $r;
    }

    public function getAirCargoClaimDetailsByTrack($track) {
        $q_first_part = "SELECT iss.id, iss.track, iss.type_id, iss.ctime,iss.utime,"
                . "it.name as issue_type, "
                . "iorg.process as issue_current_status, "
                . "iorg.priority_key, "
                . "iorg.status_key, "
                . "ifk_awb.value as awb_no, "
                . "ifk_n_o_g.value as nature_of_goods, "
                . "ifk_mawb_p.int_value as mawb_pieces, "
                . "ifk_mawb_w.value as mawb_weight, "
                . "ifk_shipper.value as shipper_name, "
                . "ifk_consig.value as consignee_name, "
                . "ifk_issuing_agent.value as issuing_agent, "
                . "ifk_ct.value as claimant_type, "
                . "ifk_cd.int_value as carriage_date, "
                . "ifk_awb_issue_date.int_value as awb_issue_date, "
                . "ifk_arvl_date.int_value as arrival_date, "
                . "ifk_pkup_date.int_value as delivery_date, "
                . "ifk_dmg_p.int_value as dmg_pieces, "
                . "ifk_dmg_w.value as dmg_weight, "
                . "ifk_loss_p.int_value as loss_pieces, "
                . "ifk_loss_w.value as loss_weight, "
                . "ifk_delay_p.int_value as delay_pieces, "
                . "ifk_delay_w.int_value as delay_weight, "
                . "ifk_partial_loss_p.int_value as partial_loss_pieces, "
                . "ifk_partial_loss_w.value as partial_loss_weight, "
                . "ifk_pilferage_p.int_value as pilferage_pieces, "
                . "ifk_pilferage_w.value as pilferage_weight, "
                . "ifk_crdate.int_value as crdate, "
                . "ifk_what_happened.value as what_happened, "
                . "ifk_invoice_val.value as invoice_val, "
                . "ifk_invoice_val.int_value as invoice_val_curr, "
                . "ifk_repair_amt.value as repair_amt, "
                . "ifk_repair_amt.int_value as repair_amt_curr, "
                . "ifk_freight_amt.value as freight_amt, "
                . "ifk_freight_amt.int_value as freight_amt_curr, "
                . "ifk_dmg_repair.int_value as dmg_repair, "
                . "ifk_salvage_amt.value as salvage_amt, "
                . "ifk_salvage_amt.int_value as salvage_amt_curr, "
                . "ifk_insurance.value as insurance, "
                . "ifk_ins_cert.value as ins_cert, "
                . "ifk_claim_amt.value as claim_amt, "
                . "ifk_claim_amt.int_value as claim_amt_curr, "
                . "ifk_claim_usd.value as claim_usd, "
                . "ifk_claim_usd.int_value as claim_usd_curr, "
                . "ifk_hawb1.value as hawb1, "
                . "ifk_origin_air.int_value as origin_airport_id, "
                . "da1.name as origin_airport_name, "
                . "da1.IATA as origin_airport_IATA, "
                . "da1.country as origin_airport_country, "
                . "ifk_destination_air.int_value as destination_airport_id, "
                . "da2.name as destination_airport_name, "
                . "da2.IATA as destination_airport_IATA, "
                . "da2.country as destination_airport_country, "
                . "ifk_via_air.int_value as via_airport_id, "
                . "da3.name as via_airport_name, "
                . "da3.IATA as via_airport_IATA, "
                . "da3.country as via_airport_country, "
                . "ifk_flight_1.value as flight_no_1, "
                . "ifk_flight_2.value as flight_no_2, "
                . "ifk_flight_3.value as flight_no_3, "
                . "ifk_route.value as route, "
                . "ifk_notified.int_value as notified, "
                . "ifk_started.int_value as started, "
                . "ifk_completed.int_value as completed, "
                . "ifk_recharge.int_value as recharge, "
                . "ifk_recharge_to.value as recharge_to, "
                . "ifk_invoice.int_value as invoice, "
                . "ifk_invoice_no.value as invoice_no, "
                . "ifk_chargeback_amount.value as chargeback_amount, "
                . "ifk_chargeback_amount.int_value as chargeback_amount_curr, "
                . "ifk_receive_amount.value as receive_amount, "
                . "ifk_receive_amount.int_value as receive_amount_curr, "
                . "ifk_sgha.int_value as sgha, "
                . "REPLACE( REPLACE( REPLACE( SUBSTRING_INDEX( SUBSTRING_INDEX(ixfd_sgha.cd, '\",\"', ifk_sgha.int_value + 1), '\",\"', -1 ), CONCAT('#', ifk_sgha.int_value, ': '), '' ), '\"', '' ), ']', '' ) AS sgha_value, "
                . "REPLACE( REPLACE( TRIM(BOTH '\"' FROM SUBSTRING_INDEX( SUBSTRING_INDEX( ixfd_claim_type.cd, CONCAT('#', ifk_claim_type.value, ': '), -1 ), '\",\"', 1 ) ), ']', '' ), '\"', '' ) AS claim_type "
                . "FROM issues AS iss "
                . "LEFT JOIN issue_types AS it ON iss.type_id = it.id "
                . "LEFT JOIN issue_orgdata AS iorg ON iss.id = iorg.issue_id "
                . "LEFT JOIN issue_fields AS ifk_awb ON ( iss.id = ifk_awb.issue_id AND ifk_awb.fk_id='3' ) "
                . "LEFT JOIN issue_fields AS ifk_n_o_g ON ( iss.id = ifk_n_o_g.issue_id AND ifk_n_o_g.fk_id='10' ) "
                . "LEFT JOIN issue_fields AS ifk_mawb_p ON ( iss.id = ifk_mawb_p.issue_id AND ifk_mawb_p.fk_id='175' ) "
                . "LEFT JOIN issue_fields AS ifk_mawb_w ON ( iss.id = ifk_mawb_w.issue_id AND ifk_mawb_w.fk_id='174' ) "
                . "LEFT JOIN issue_fields AS ifk_shipper ON ( iss.id = ifk_shipper.issue_id AND ifk_shipper.fk_id='587' ) "
                . "LEFT JOIN issue_fields AS ifk_consig ON ( iss.id = ifk_consig.issue_id AND ifk_consig.fk_id='588' ) "
                . "LEFT JOIN issue_fields AS ifk_issuing_agent ON ( iss.id = ifk_issuing_agent.issue_id AND ifk_issuing_agent.fk_id='3629' ) "
                . "LEFT JOIN issue_fields AS ifk_ct ON ( iss.id = ifk_ct.issue_id AND ifk_ct.fk_id='1165' ) "
                . "LEFT JOIN issue_fields AS ifk_cd ON ( iss.id = ifk_cd.issue_id AND ifk_cd.fk_id='998' ) "
                . "LEFT JOIN issue_fields AS ifk_awb_issue_date ON ( iss.id = ifk_awb_issue_date.issue_id AND ifk_awb_issue_date.fk_id='11' ) "
                . "LEFT JOIN issue_fields AS ifk_arvl_date ON ( iss.id = ifk_arvl_date.issue_id AND ifk_arvl_date.fk_id='8' ) "
                . "LEFT JOIN issue_fields AS ifk_pkup_date ON ( iss.id = ifk_pkup_date.issue_id AND ifk_pkup_date.fk_id='1789' ) "
                . "LEFT JOIN issue_fields AS ifk_dmg_p ON ( iss.id = ifk_dmg_p.issue_id AND ifk_dmg_p.fk_id='12' ) "
                . "LEFT JOIN issue_fields AS ifk_dmg_w ON ( iss.id = ifk_dmg_w.issue_id AND ifk_dmg_w.fk_id='13' ) "
                . "LEFT JOIN issue_fields AS ifk_loss_p ON ( iss.id = ifk_loss_p.issue_id AND ifk_loss_p.fk_id='128' ) "
                . "LEFT JOIN issue_fields AS ifk_loss_w ON ( iss.id = ifk_loss_w.issue_id AND ifk_loss_w.fk_id='129' ) "
                . "LEFT JOIN issue_fields AS ifk_delay_p ON ( iss.id = ifk_delay_p.issue_id AND ifk_delay_p.fk_id='565' ) "
                . "LEFT JOIN issue_fields AS ifk_delay_w ON ( iss.id = ifk_delay_w.issue_id AND ifk_delay_w.fk_id='566' ) "
                . "LEFT JOIN issue_fields AS ifk_partial_loss_p ON ( iss.id = ifk_partial_loss_p.issue_id AND ifk_partial_loss_p.fk_id='956' ) "
                . "LEFT JOIN issue_fields AS ifk_partial_loss_w ON ( iss.id = ifk_partial_loss_w.issue_id AND ifk_partial_loss_w.fk_id='957' ) "
                . "LEFT JOIN issue_fields AS ifk_pilferage_p ON ( iss.id = ifk_pilferage_p.issue_id AND ifk_pilferage_p.fk_id='959' ) "
                . "LEFT JOIN issue_fields AS ifk_pilferage_w ON ( iss.id = ifk_pilferage_w.issue_id AND ifk_pilferage_w.fk_id='958' ) "
                . "LEFT JOIN issue_fields AS ifk_crdate ON ( iss.id = ifk_crdate.issue_id AND ifk_crdate.fk_id='2399' ) "
                . "LEFT JOIN issue_fields AS ifk_what_happened ON ( iss.id = ifk_what_happened.issue_id AND ifk_what_happened.fk_id='22' ) "
                . "LEFT JOIN issue_fields AS ifk_invoice_val ON ( iss.id = ifk_invoice_val.issue_id AND ifk_invoice_val.fk_id='2010' ) "
                . "LEFT JOIN issue_fields AS ifk_repair_amt ON ( iss.id = ifk_repair_amt.issue_id AND ifk_repair_amt.fk_id='2393' ) "
                . "LEFT JOIN issue_fields AS ifk_freight_amt ON ( iss.id = ifk_freight_amt.issue_id AND ifk_freight_amt.fk_id='2395' ) "
                . "LEFT JOIN issue_fields AS ifk_dmg_repair ON ( iss.id = ifk_dmg_repair.issue_id AND ifk_dmg_repair.fk_id='571' ) "
                . "LEFT JOIN issue_fields AS ifk_salvage_amt ON ( iss.id = ifk_salvage_amt.issue_id AND ifk_salvage_amt.fk_id='2394' ) "
                . "LEFT JOIN issue_fields AS ifk_insurance ON ( iss.id = ifk_insurance.issue_id AND ifk_insurance.fk_id='19' ) "
                . "LEFT JOIN issue_fields AS ifk_ins_cert ON ( iss.id = ifk_ins_cert.issue_id AND ifk_ins_cert.fk_id='2843' ) "
                . "LEFT JOIN issue_fields AS ifk_claim_amt ON ( iss.id = ifk_claim_amt.issue_id AND ifk_claim_amt.fk_id='1729' ) "
                . "LEFT JOIN issue_fields AS ifk_claim_usd ON ( iss.id = ifk_claim_usd.issue_id AND ifk_claim_usd.fk_id='2671' ) "
                . "LEFT JOIN issue_fields AS ifk_hawb1 ON ( iss.id = ifk_hawb1.issue_id AND ifk_hawb1.fk_id='4' ) "
                . "LEFT JOIN issue_fields AS ifk_origin_air ON ( iss.id = ifk_origin_air.issue_id AND ifk_origin_air.fk_id='66' ) "
                . "LEFT JOIN data_airports AS da1 ON ( ifk_origin_air.int_value = da1.id ) "
                . "LEFT JOIN issue_fields AS ifk_destination_air ON ( iss.id = ifk_destination_air.issue_id AND ifk_destination_air.fk_id='67' ) "
                . "LEFT JOIN data_airports AS da2 ON ( ifk_destination_air.int_value = da2.id ) "
                . "LEFT JOIN issue_fields AS ifk_via_air ON ( iss.id = ifk_via_air.issue_id AND ifk_via_air.fk_id='585' ) "
                . "LEFT JOIN data_airports AS da3 ON ( ifk_via_air.int_value = da3.id ) "
                . "LEFT JOIN issue_fields AS ifk_flight_1 ON ( iss.id = ifk_flight_1.issue_id AND ifk_flight_1.fk_id='5' ) "
                . "LEFT JOIN issue_fields AS ifk_flight_2 ON ( iss.id = ifk_flight_2.issue_id AND ifk_flight_2.fk_id='586' ) "
                . "LEFT JOIN issue_fields AS ifk_flight_3 ON ( iss.id = ifk_flight_3.issue_id AND ifk_flight_3.fk_id='1708' ) "
                . "LEFT JOIN issue_fields AS ifk_route ON ( iss.id = ifk_route.issue_id AND ifk_route.fk_id='2814' ) "
                . "LEFT JOIN issue_fields AS ifk_notified ON ( iss.id = ifk_notified.issue_id AND ifk_notified.fk_id='2818' ) "
                . "LEFT JOIN issue_fields AS ifk_started ON ( iss.id = ifk_started.issue_id AND ifk_started.fk_id='2817' ) "
                . "LEFT JOIN issue_fields AS ifk_completed ON ( iss.id = ifk_completed.issue_id AND ifk_completed.fk_id='2816' ) "
                . "LEFT JOIN issue_fields AS ifk_recharge ON ( iss.id = ifk_recharge.issue_id AND ifk_recharge.fk_id='944' ) "
                . "LEFT JOIN issue_fields AS ifk_recharge_to ON ( iss.id = ifk_recharge_to.issue_id AND ifk_recharge_to.fk_id='1311' ) "
                . "LEFT JOIN issue_fields AS ifk_invoice ON ( iss.id = ifk_invoice.issue_id AND ifk_invoice.fk_id='1821' ) "
                . "LEFT JOIN issue_fields AS ifk_invoice_no ON ( iss.id = ifk_invoice_no.issue_id AND ifk_invoice_no.fk_id='1822' ) "
                . "LEFT JOIN issue_fields AS ifk_chargeback_amount ON ( iss.id = ifk_chargeback_amount.issue_id AND ifk_chargeback_amount.fk_id='1823' ) "
                . "LEFT JOIN issue_fields AS ifk_receive_amount ON ( iss.id = ifk_receive_amount.issue_id AND ifk_receive_amount.fk_id='1824' ) "
                . "LEFT JOIN issue_fields AS ifk_sgha ON ( iss.id = ifk_sgha.issue_id AND ifk_sgha.fk_id='948' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_sgha ON ixfd_sgha.id = ifk_sgha.fk_id "
                . "LEFT JOIN issue_fields AS ifk_claim_type ON ( iss.id = ifk_claim_type.issue_id AND ifk_claim_type.fk_id='65' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_claim_type ON ixfd_claim_type.id = ifk_claim_type.fk_id "
                . "WHERE iss.track = '$track' LIMIT 1";
//            echo $q_first_part;
        $r_first_part = MVC_Core::$db->query($q_first_part)->current();
        $link_id = $r_first_part['id'];

        $q_second_part = "SELECT "
                . "ifk_investigation.int_value , "
                . "TRIM(BOTH '\"' FROM SUBSTRING_INDEX( SUBSTRING_INDEX( ixfd_investigation.cd, CONCAT('#', ifk_investigation.int_value, ': '), -1 ), '\",\"', 1 ) ) AS investigation, "
                . "ifk_investigation_id.value as investigation_id, "
                . "ifk_cargo_damage_id.value as cargo_damage_id, "
                . "ifk_findings.value as findings, "
                . "ifk_actions_taken.value as actions_taken, "
                . "ifk_outcome.value as outcome, "
                . "ifk_ref_no.value as internal_reference, "
                . "TRIM(BOTH '\"' FROM SUBSTRING_INDEX( SUBSTRING_INDEX( ixfd_hdlstat.cd, CONCAT('#', ifk_hdlstat.value, ': '), -1 ), '\",\"', 1 ) ) AS internal_status, "
                . "TRIM(BOTH '\"' FROM SUBSTRING_INDEX( SUBSTRING_INDEX( ixfd_hdlexstat.cd, CONCAT('#', ifk_hdlexstat.value, ': '), -1 ), '\",\"', 1 ) ) AS external_status, "
                . "REPLACE( REPLACE( REPLACE( SUBSTRING_INDEX( SUBSTRING_INDEX(ixfd_validation.cd, '\",\"', ifk_validation.int_value + 1), '\",\"', -1 ), CONCAT('#', ifk_validation.int_value, ': '), '' ), '\"', '' ), ']', '' ) AS validation_completed, "
                . "ifk_preliminary_claim.int_value as preliminary_claim, "
                . "ifk_formal_claim.int_value as formal_claim, "
                . "REPLACE( REPLACE( REPLACE( REPLACE( SUBSTRING_INDEX( SUBSTRING_INDEX(ixfd_received_in_time.cd, '\",\"', ifk_received_in_time.int_value + 1), '\",\"', -1 ), CONCAT('#', ifk_received_in_time.int_value, ': '), '' ), '\"', '' ), '[', '' ), ']', '' ) AS received_in_time, "
                . "REPLACE( REPLACE( REPLACE( REPLACE( SUBSTRING_INDEX( SUBSTRING_INDEX(ixfd_hardcopy.cd, '\",\"', ifk_hardcopy.int_value + 1), '\",\"', -1 ), CONCAT('#', ifk_hardcopy.int_value, ': '), '' ), '\"', '' ), '[', '' ), ']', '' ) AS hardcopy_of_claim, "
                . "ifk_acdate.int_value as acdate, "
                . "TRIM(BOTH '\"' FROM SUBSTRING_INDEX( SUBSTRING_INDEX( ixfd_subm_type.cd, CONCAT('#', ifk_subm_type.value, ': '), -1 ), '\",\"', 1 ) ) AS claimant_type,  "
                . "ifk_cass.value as cass, "
                . "REPLACE( REPLACE( REPLACE( REPLACE( SUBSTRING_INDEX( SUBSTRING_INDEX(ixfd_subrog.cd, '\",\"', ifk_subrog.int_value + 1), '\",\"', -1 ), CONCAT('#', ifk_subrog.int_value, ': '), '' ), '\"', '' ), '[', '' ), ']', '' ) AS subrogation_required,  "
                . "ixfd_como_type.cd as como_type_all_val, "
                . "ifk_como_type.value as como_type_selected, "
                . "ixfd_code_srv.cd as code_srv_all_val, "
                . "ifk_code_srv.value as code_srv_selected, "
                . "TRIM(BOTH '\"' FROM SUBSTRING_INDEX( SUBSTRING_INDEX( ixfd_code_billing.cd, CONCAT('#', ifk_code_billing.value, ': '), -1 ), '\",\"', 1 ) ) AS code_billing,  "
                . "ifk_cust_weight.value as claimed_weight, "
                . "ifk_g_weight.value as gross_weight, "
                . "ifk_cust_weight.value as chargable_weight, "
                . "ifk_handling_rem.value as investigation_remarks, "
                . "ifk_cause.value as investigation_cause, "
                . "TRIM(BOTH '\"' FROM SUBSTRING_INDEX( SUBSTRING_INDEX( ixfd_irreg_code.cd, CONCAT('#', ifk_irreg_code.value, ': '), -1 ), '\",\"', 1 ) ) AS investigation_irregularity,  "
                . "TRIM(BOTH '\"' FROM SUBSTRING_INDEX( SUBSTRING_INDEX( ixfd_inc_class.cd, CONCAT('#', ifk_inc_class.value, ': '), -1 ), '\",\"', 1 ) ) AS investigation_conclusion,  "
                . "TRIM(BOTH '\"' FROM SUBSTRING_INDEX( SUBSTRING_INDEX( ixfd_liable.cd, CONCAT('#', ifk_liable.value, ': '), -1 ), '\",\"', 1 ) ) AS investigation_carrier_liability,  "
                . "TRIM(BOTH '\"' FROM SUBSTRING_INDEX( SUBSTRING_INDEX( ixfd_caused_by.cd, CONCAT('#', ifk_caused_by.value, ': '), -1 ), '\",\"', 1 ) ) AS investigation_responsible_partner,  "
                . "ifk_caused_other.value as investigation_company_name, "
                . "ifk_sla_30.int_value as sla_30, "
                . "ifk_sla_60.int_value as sla_60, "
                . "ifk_sla_90.int_value as sla_90, "
                . "ifk_doss_close_date.int_value as doss_close_date, "
                . "ifk_doss_length.value as doss_length, "
                . "TRIM(BOTH '\"' FROM SUBSTRING_INDEX( SUBSTRING_INDEX( ixfd_sla_extend.cd, CONCAT('#', ifk_sla_extend.int_value, ': '), -1 ), '\",\"', 1 ) ) AS sla_extend,  "
                . "TRIM(BOTH '\"' FROM SUBSTRING_INDEX( SUBSTRING_INDEX( ixfd_sla_level.cd, CONCAT('#', ifk_sla_level.int_value, ': '), -1 ), '\",\"', 1 ) ) AS sla_level,  "
                . "ifk_srv_txt.value as srv_txt "
                . "FROM issues AS iss "
                . "LEFT JOIN issue_types AS it ON iss.type_id = it.id "
                . "LEFT JOIN issue_orgdata AS iorg ON iss.id = iorg.issue_id "
                . "LEFT JOIN issue_fields AS ifk_investigation ON ( iss.id = ifk_investigation.issue_id AND ifk_investigation.fk_id='1989' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_investigation ON ixfd_investigation.id = ifk_investigation.fk_id "
                . "LEFT JOIN issue_fields AS ifk_investigation_id ON ( iss.id = ifk_investigation_id.issue_id AND ifk_investigation_id.fk_id='1987' ) "
                . "LEFT JOIN issue_fields AS ifk_cargo_damage_id ON ( iss.id = ifk_cargo_damage_id.issue_id AND ifk_cargo_damage_id.fk_id='1988' ) "
                . "LEFT JOIN issue_fields AS ifk_findings ON ( iss.id = ifk_findings.issue_id AND ifk_findings.fk_id='938' ) "
                . "LEFT JOIN issue_fields AS ifk_actions_taken ON ( iss.id = ifk_actions_taken.issue_id AND ifk_actions_taken.fk_id='26' ) "
                . "LEFT JOIN issue_fields AS ifk_outcome ON ( iss.id = ifk_outcome.issue_id AND ifk_outcome.fk_id='941' ) "
                . "LEFT JOIN issue_fields AS ifk_ref_no ON ( iss.id = ifk_ref_no.issue_id AND ifk_ref_no.fk_id='891' ) "
                . "LEFT JOIN issue_fields AS ifk_hdlstat ON ( iss.id = ifk_hdlstat.issue_id AND ifk_hdlstat.fk_id='2618' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_hdlstat ON ixfd_hdlstat.id = ifk_hdlstat.fk_id "
                . "LEFT JOIN issue_fields AS ifk_hdlexstat ON ( iss.id = ifk_hdlexstat.issue_id AND ifk_hdlexstat.fk_id='3174' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_hdlexstat ON ixfd_hdlexstat.id = ifk_hdlexstat.fk_id "
                . "LEFT JOIN issue_fields AS ifk_validation ON ( iss.id = ifk_validation.issue_id AND ifk_validation.fk_id='1807' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_validation ON ixfd_validation.id = ifk_validation.fk_id "
                . "LEFT JOIN issue_fields AS ifk_preliminary_claim ON ( iss.id = ifk_preliminary_claim.issue_id AND ifk_preliminary_claim.fk_id='3127' ) "
                . "LEFT JOIN issue_fields AS ifk_formal_claim ON ( iss.id = ifk_formal_claim.issue_id AND ifk_formal_claim.fk_id='3128' ) "
                . "LEFT JOIN issue_fields AS ifk_received_in_time ON ( iss.id = ifk_received_in_time.issue_id AND ifk_received_in_time.fk_id='1806' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_received_in_time ON ixfd_received_in_time.id = ifk_received_in_time.fk_id "
                . "LEFT JOIN issue_fields AS ifk_hardcopy ON ( iss.id = ifk_hardcopy.issue_id AND ifk_hardcopy.fk_id='1318' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_hardcopy ON ixfd_hardcopy.id = ifk_hardcopy.fk_id "
                . "LEFT JOIN issue_fields AS ifk_acdate ON ( iss.id = ifk_acdate.issue_id AND ifk_acdate.fk_id='567' ) "
                . "LEFT JOIN issue_fields AS ifk_subm_type ON ( iss.id = ifk_subm_type.issue_id AND ifk_subm_type.fk_id='1165' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_subm_type ON ixfd_subm_type.id = ifk_subm_type.fk_id "
                . "LEFT JOIN issue_fields AS ifk_cass ON ( iss.id = ifk_cass.issue_id AND ifk_cass.fk_id='2813' ) "
                . "LEFT JOIN issue_fields AS ifk_subrog ON ( iss.id = ifk_subrog.issue_id AND ifk_subrog.fk_id='1805' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_subrog ON ixfd_subrog.id = ifk_subrog.fk_id "
                . "LEFT JOIN issue_fields AS ifk_como_type ON ( iss.id = ifk_como_type.issue_id AND ifk_como_type.fk_id='2141' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_como_type ON ixfd_como_type.id = ifk_como_type.fk_id "
                . "LEFT JOIN issue_fields AS ifk_code_srv ON ( iss.id = ifk_code_srv.issue_id AND ifk_code_srv.fk_id='2675' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_code_srv ON ixfd_code_srv.id = ifk_code_srv.fk_id "
                . "LEFT JOIN issue_fields AS ifk_code_billing ON ( iss.id = ifk_code_billing.issue_id AND ifk_code_billing.fk_id='1320' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_code_billing ON ixfd_code_billing.id = ifk_code_billing.fk_id "
                . "LEFT JOIN issue_fields AS ifk_cust_weight ON ( iss.id = ifk_cust_weight.issue_id AND ifk_cust_weight.fk_id='982' ) "
                . "LEFT JOIN issue_fields AS ifk_g_weight ON ( iss.id = ifk_g_weight.issue_id AND ifk_g_weight.fk_id='989' ) "
                . "LEFT JOIN issue_fields AS ifk_c_weight ON ( iss.id = ifk_c_weight.issue_id AND ifk_c_weight.fk_id='990' ) "
                . "LEFT JOIN issue_fields AS ifk_handling_rem ON ( iss.id = ifk_handling_rem.issue_id AND ifk_handling_rem.fk_id='1440' ) "
                . "LEFT JOIN issue_fields AS ifk_cause ON ( iss.id = ifk_cause.issue_id AND ifk_cause.fk_id='23' ) "
                . "LEFT JOIN issue_fields AS ifk_irreg_code ON ( iss.id = ifk_irreg_code.issue_id AND ifk_irreg_code.fk_id='2083' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_irreg_code ON ixfd_irreg_code.id = ifk_irreg_code.fk_id "
                . "LEFT JOIN issue_fields AS ifk_inc_class ON ( iss.id = ifk_inc_class.issue_id AND ifk_inc_class.fk_id='2577' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_inc_class ON ixfd_inc_class.id = ifk_inc_class.fk_id "
                . "LEFT JOIN issue_fields AS ifk_liable ON ( iss.id = ifk_liable.issue_id AND ifk_liable.fk_id='25' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_liable ON ixfd_liable.id = ifk_liable.fk_id "
                . "LEFT JOIN issue_fields AS ifk_caused_by ON ( iss.id = ifk_caused_by.issue_id AND ifk_caused_by.fk_id='24' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_caused_by ON ixfd_caused_by.id = ifk_caused_by.fk_id "
                . "LEFT JOIN issue_fields AS ifk_caused_other ON ( iss.id = ifk_caused_other.issue_id AND ifk_caused_other.fk_id='2575' ) "
                . "LEFT JOIN issue_fields AS ifk_sla_30 ON ( iss.id = ifk_sla_30.issue_id AND ifk_sla_30.fk_id='2819' ) "
                . "LEFT JOIN issue_fields AS ifk_sla_60 ON ( iss.id = ifk_sla_60.issue_id AND ifk_sla_60.fk_id='2824' ) "
                . "LEFT JOIN issue_fields AS ifk_sla_90 ON ( iss.id = ifk_sla_90.issue_id AND ifk_sla_90.fk_id='2825' ) "
                . "LEFT JOIN issue_fields AS ifk_doss_close_date ON ( iss.id = ifk_doss_close_date.issue_id AND ifk_doss_close_date.fk_id='2396' ) "
                . "LEFT JOIN issue_fields AS ifk_doss_length ON ( iss.id = ifk_doss_length.issue_id AND ifk_doss_length.fk_id='2397' ) "
                . "LEFT JOIN issue_fields AS ifk_sla_extend ON ( iss.id = ifk_sla_extend.issue_id AND ifk_sla_extend.fk_id='2820' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_sla_extend ON ixfd_sla_extend.id = ifk_sla_extend.fk_id "
                . "LEFT JOIN issue_fields AS ifk_sla_level ON ( iss.id = ifk_sla_level.issue_id AND ifk_sla_level.fk_id='2821' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_sla_level ON ixfd_sla_level.id = ifk_sla_level.fk_id "
                . "LEFT JOIN issue_fields AS ifk_srv_txt ON ( iss.id = ifk_srv_txt.issue_id AND ifk_srv_txt.fk_id='2154' ) "
                . "WHERE iss.track = '$track' LIMIT 1";

        $r_second_part = MVC_Core::$db->query($q_second_part)->current();

        $q_third_part = "SELECT "
                . "iss.id as issue_id, "
                . "REPLACE( REPLACE( TRIM(BOTH '\"' FROM SUBSTRING_INDEX( SUBSTRING_INDEX( ixfd_money_auto.cd, CONCAT('#', ifk_money_auto.int_value, ': '), -1 ), '\",\"', 1 ) ), ']', '' ), '\"', '' ) AS money_auto, "
                . "ifk_calc_weight.value as calc_weight, "
                . "REPLACE( REPLACE( TRIM(BOTH '\"' FROM SUBSTRING_INDEX( SUBSTRING_INDEX( ixfd_convention.cd, CONCAT('#', ifk_convention.int_value, ': '), -1 ), '\",\"', 1 ) ), ']', '' ), '\"', '' ) AS convention, "
                . "ifk_xchg_date.value as xchg_date, "
                . "ifk_claim_amt.value as claim_amt, "
                . "ifk_claim_amt.int_value as claim_amt_curr, "
                . "ifk_insured_usd.value as insured_usd, "
                . "ifk_provision_usd.value as provision_usd, "
                . "ifk_ins_ref_no.value as ins_ref_no, "
                . "ifk_provi_date.int_value as provi_date, "
                . "ifk_suggested_usd.value as suggested_usd, "
                . "ifk_paid_usd.value as paid_usd, "
                . "ifk_settle_date.int_value as settle_date, "
                . "REPLACE( REPLACE( TRIM(BOTH '\"' FROM SUBSTRING_INDEX( SUBSTRING_INDEX( ixfd_settle_basis.cd, CONCAT('#', ifk_settle_basis.value, ': '), -1 ), '\",\"', 1 ) ), ']', '' ), '\"', '' ) AS settle_basis, "
                . "ifk_pcur.int_value as pcur_curr "
                . "FROM issues AS iss "
                . "LEFT JOIN issue_types AS it ON iss.type_id = it.id "
                . "LEFT JOIN issue_orgdata AS iorg ON iss.id = iorg.issue_id "
                . "LEFT JOIN issue_fields AS ifk_money_auto ON ( iss.id = ifk_money_auto.issue_id AND ifk_money_auto.fk_id='1809' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_money_auto ON ixfd_money_auto.id = ifk_money_auto.fk_id "
                . "LEFT JOIN issue_fields AS ifk_calc_weight ON ( iss.id = ifk_calc_weight.issue_id AND ifk_calc_weight.fk_id='997' ) "
                . "LEFT JOIN issue_fields AS ifk_convention ON ( iss.id = ifk_convention.issue_id AND ifk_convention.fk_id='974' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_convention ON ixfd_convention.id = ifk_convention.fk_id "
                . "LEFT JOIN issue_fields AS ifk_xchg_date ON ( iss.id = ifk_xchg_date.issue_id AND ifk_xchg_date.fk_id='1808' ) "
                . "LEFT JOIN issue_fields AS ifk_claim_amt ON ( iss.id = ifk_claim_amt.issue_id AND ifk_claim_amt.fk_id='1729' ) "
                . "LEFT JOIN issue_fields AS ifk_insured_usd ON ( iss.id = ifk_insured_usd.issue_id AND ifk_insured_usd.fk_id='2832' ) "
                . "LEFT JOIN issue_fields AS ifk_provision_usd ON ( iss.id = ifk_provision_usd.issue_id AND ifk_provision_usd.fk_id='2834' ) "
                . "LEFT JOIN issue_fields AS ifk_ins_ref_no ON ( iss.id = ifk_ins_ref_no.issue_id AND ifk_ins_ref_no.fk_id='892' ) "
                . "LEFT JOIN issue_fields AS ifk_provi_date ON ( iss.id = ifk_provi_date.issue_id AND ifk_provi_date.fk_id='968' ) "
                . "LEFT JOIN issue_fields AS ifk_suggested_usd ON ( iss.id = ifk_suggested_usd.issue_id AND ifk_suggested_usd.fk_id='2836' ) "
                . "LEFT JOIN issue_fields AS ifk_paid_usd ON ( iss.id = ifk_paid_usd.issue_id AND ifk_paid_usd.fk_id='2826' ) "
                . "LEFT JOIN issue_fields AS ifk_settle_date ON ( iss.id = ifk_settle_date.issue_id AND ifk_settle_date.fk_id='962' ) "
                . "LEFT JOIN issue_fields AS ifk_settle_basis ON ( iss.id = ifk_settle_basis.issue_id AND ifk_settle_basis.fk_id='965' ) "
                . "LEFT JOIN issue_xfdef AS ixfd_settle_basis ON ixfd_settle_basis.id = ifk_settle_basis.fk_id "
                . "LEFT JOIN issue_fields AS ifk_pcur ON ( iss.id = ifk_pcur.issue_id AND ifk_pcur.fk_id='3843' ) "
                . "WHERE iss.track = '$track' LIMIT 1";

        $r_third_part = MVC_Core::$db->query($q_third_part)->current();

//       echo $r_third_part['issue_id'];
//        Special handling codes data start
        $all_special_handling_codes = json_decode($r_second_part['como_type_all_val'], true);
        $codes_special_handling_codes = json_decode($r_second_part['como_type_selected'], true);

        $special_handling_codes = array();

        foreach ($all_special_handling_codes as $entry) {

            // Extract code (val, pax, etc.)
            if (preg_match('/^#([^:]+):\s*(.*)$/', $entry, $matchesspecial_handling_codes)) {
                $codespecial_handling_codes = $matchesspecial_handling_codes[1]; // val
                $labelspecial_handling_codes = $matchesspecial_handling_codes[2]; // SHR | High risk cargo

                if (in_array($codespecial_handling_codes, $codes_special_handling_codes)) {
                    $special_handling_codes[] = $labelspecial_handling_codes;
                }
            }
        }
//        Special handling codes data end
//        Service codes data start
        $all_service_codes = json_decode($r_second_part['code_srv_all_val'], true);
        $codes_service_codes = json_decode($r_second_part['code_srv_selected'], true);

        $service_codes = array();

        foreach ($all_service_codes as $entry) {

            // Extract code (val, pax, etc.)
            if (preg_match('/^#([^:]+):\s*(.*)$/', $entry, $matchesservice_codes)) {
                $codeservice_codes = $matchesservice_codes[1]; // val
                $labelservice_codes = $matchesservice_codes[2]; // SHR | High risk cargo

                if (in_array($codeservice_codes, $codes_service_codes)) {
                    $service_codes[] = $labelservice_codes;
                }
            }
        }
//        Service codes data end




        $Claimant_type = array(
            'sh' => 'Shipper',
            'con' => 'Consignee',
            'fwd' => 'Forwarder',
            'isn' => 'Insurance agent',
            'oth' => 'Other',
        );

        $currencies = array(
            "840" => "USD $",
            "608" => "PHP ?",
            "978" => "EUR €",
            "392" => "JPY ¥",
            "826" => "GBP £",
            "36" => "AUD $",
            "756" => "CHF Fr",
            "124" => "CAD $",
            "484" => "MXN $",
            "156" => "CNY ¥",
            "554" => "NZD $",
            "752" => "SEK kr",
            "643" => "RUB ?",
            "344" => "HKD $",
            "578" => "NOK kr",
            "702" => "SGD $",
            "949" => "TRY ?",
            "410" => "KRW ?",
            "710" => "ZAR R",
            "986" => "BRL R$",
            "356" => "INR ?",
            "682" => "SAR ?",
            "208" => "DKK kr",
            "946" => "RON L",
            "950" => "XAF FCFA",
            "952" => "XOF CFA",
            "784" => "AED ?.?",
            "214" => "DOP RD$",
            "332" => "HTG G",
            "52" => "BBD Bds$",
            "780" => "TTD TT$",
            "951" => "XCD $",
            "532" => "ANG NAƒ",
            "170" => "COP $",
            "960" => "XDR",
            "1001" => "XBT ?",
            "1002" => "WGH W",
        );
//        status_key and priority_key details information (this is not in DB, it's loaded from YAML files) start
        $filename = ROOT . DS . 'app' . DS . 'nls' . DS . 'en' . DS . 'issue.yaml';
        if (file_exists($filename)) {
            $priority_status_data = MVC_Core::$spyc->loadFile($filename);
            $priorityKey = $r_first_part['priority_key'];
            $priorityValue = isset($priority_status_data['data']['priorities'][$priorityKey]) ? $priority_status_data['data']['priorities'][$priorityKey] : null;
            $statusKey = $r_first_part['status_key'];
            $statusValue = isset($priority_status_data['data']['statuses'][$statusKey]) ? $priority_status_data['data']['statuses'][$statusKey] : null;
        }
//        status_key and priority_key details information (this is not in DB, it's loaded from YAML files) end
//        $finalData = $r;
        $finalData = array();
        $finalData['record_details'] = array(
            'track' => $r_first_part['track'],
            'last_updated' =>  $r_first_part['utime'],
            'type' => $r_first_part['issue_type'],
            'issue_current_status' => $r_first_part['issue_current_status'],
            'status' => isset($statusValue) ? $statusValue : '',
            'priority' => isset($priorityValue) ? $priorityValue : '',
            'priority' => isset($priorityValue) ? $priorityValue : '',
            'airline' => array(
                'open_days' => floor((time() - $r_first_part['ctime']) / 86400),
            ),
            'dashboard' => array(
                'master_AirWayBill' => $r_first_part['awb_no'],
                'claim_type' => $r_first_part['claim_type'],
                'nature_of_goods' => $r_first_part['nature_of_goods'],
                'MAWB_total' => array(
                    'pieces' => $r_first_part['mawb_pieces'],
                    'weight' => $r_first_part['mawb_weight']
                ),
                'shipper_name' => $r_first_part['shipper_name'],
                'consignee_name' => $r_first_part['consignee_name'],
                'issuing_agent' => $r_first_part['issuing_agent'],
                'claimant_type' => $r_first_part['claimant_type'],
                'carriage_date' => isset($r_first_part['carriage_date']) ? date('Y-m-d', $r_first_part['carriage_date']) : '',
                'awb_issue_date' => isset($r_first_part['awb_issue_date']) ? date('Y-m-d', $r_first_part['awb_issue_date']) : '',
                'arrival_date' => isset($r_first_part['arrival_date']) ? date('Y-m-d', $r_first_part['arrival_date']) : '',
                'delivery_date' => isset($r_first_part['delivery_date']) ? date('Y-m-d', $r_first_part['delivery_date']) : '',
                'claim_notice' => isset($r_first_part['crdate']) ? date('Y-m-d', $r_first_part['crdate']) : '', // claim notice is record claim notice date
                'claim_aspect' => array(
                    'damage' => array(
                        'pieces' => $r_first_part['dmg_pieces'],
                        'weight' => $r_first_part['dmg_weight']
                    ),
                    'total_loss' => array(
                        'pieces' => $r_first_part['loss_pieces'],
                        'weight' => $r_first_part['loss_weight']
                    ),
                    'delay' => array(
                        'pieces' => $r_first_part['delay_pieces'],
                        'weight' => $r_first_part['delay_weight']
                    ),
                    'partial_loss' => array(
                        'pieces' => $r_first_part['partial_loss_pieces'],
                        'weight' => $r_first_part['partial_loss_weight']
                    ),
                    'pilferage' => array(
                        'pieces' => $r_first_part['pilferage_pieces'],
                        'weight' => $r_first_part['pilferage_weight']
                    ),
                ),
                'what_happened' => $r_first_part['what_happened'],
                'financial_data' => array(
                    'invoice_value' => array(
                        'value' => $r_first_part['invoice_val'],
                        'curr' => isset($r_first_part['invoice_val_curr']) ? $currencies[$r_first_part['invoice_val_curr']] : '',
                    ),
                    'cost_of_repair' => array(
                        'value' => $r_first_part['repair_amt'],
                        'curr' => isset($r_first_part['repair_amt_curr']) ? $currencies[$r_first_part['repair_amt_curr']] : '',
                    ),
                    'freight_charges' => array(
                        'value' => $r_first_part['freight_amt'],
                        'curr' => isset($r_first_part['freight_amt_curr']) ? $currencies[$r_first_part['freight_amt_curr']] : '',
                    ),
                    'damage_repairable' => (isset($r_first_part['dmg_repair']) && $r_first_part['dmg_repair'] == '1') ? 'Yes' : 'No',
                    'cost_of_salvage' => array(
                        'value' => $r_first_part['salvage_amt'],
                        'curr' => isset($r_first_part['salvage_amt_curr']) ? $currencies[$r_first_part['salvage_amt_curr']] : '',
                    ),
                    'cargo_insurance' => (isset($r_first_part['insurance']) && $r_first_part['insurance'] == '1') ? 'Yes' : 'No',
                    'insurance_cert_no' => $r_first_part['ins_cert'],
                    'final_claim_amount' => array(
                        'value' => $r_first_part['claim_amt'],
                        'curr' => isset($r_first_part['claim_amt_curr']) ? $currencies[$r_first_part['claim_amt_curr']] : '',
                    ),
                    'final_claim_in_USD' => array(
                        'value' => $r_first_part['claim_usd'],
                        'curr' => isset($r_first_part['claim_usd_curr']) ? $currencies[$r_first_part['claim_usd_curr']] : '',
                    ),
                ),
                'provided_documents' => array(
                    '1st_house_awb' => $r_first_part['hawb1'],
                ),
                'flight_information' => array(
                    'origin_airport' => $r_first_part['origin_airport_IATA'] . ':' . $r_first_part['origin_airport_name'] . ', ' . $r_first_part['origin_airport_country'],
                    'destination_airport' => $r_first_part['destination_airport_IATA'] . ':' . $r_first_part['destination_airport_name'] . ', ' . $r_first_part['destination_airport_country'],
                    'via_airport' => $r_first_part['via_airport_IATA'] . ':' . $r_first_part['via_airport_name'] . ', ' . $r_first_part['via_airport_country'],
                    'flight_no_1' => $r_first_part['flight_no_1'],
                    'flight_no_2' => $r_first_part['flight_no_2'],
                    'flight_no_3' => $r_first_part['flight_no_3'],
                    'route' => $r_first_part['route'],
                ),
            ),
            'charge_back_followup' => array(
                'procurement' => array(
                    'notified' => isset($r_first_part['notified']) ? date('Y-m-d', $r_first_part['notified']) : '',
                    'started' => isset($r_first_part['started']) ? date('Y-m-d', $r_first_part['started']) : '',
                    'completed' => isset($r_first_part['completed']) ? date('Y-m-d', $r_first_part['completed']) : '',
                ),
                'chargeback' => isset($r_first_part['recharge']) && $r_first_part['recharge'] == 1 ? 'Yes' : 'No',
                'chargeback_from' => $r_first_part['recharge_to'],
                'invoice' => isset($r_first_part['invoice']) && $r_first_part['invoice'] == 1 ? 'Yes' : 'No',
                'invoice_no' => $r_first_part['invoice_no'],
                'chargeback_amount' => array(
                    'value' => $r_first_part['chargeback_amount'],
                    'curr' => isset($r_first_part['chargeback_amount_curr']) ? $currencies[$r_first_part['chargeback_amount_curr']] : '',
                ),
                'receive_amount' => array(
                    'value' => $r_first_part['receive_amount'],
                    'curr' => isset($r_first_part['receive_amount_curr']) ? $currencies[$r_first_part['receive_amount_curr']] : '',
                ),
                'sgha_value' => $r_first_part['sgha_value'],
                'investigation' => array(
                    'investigation' => $r_second_part['investigation'],
                    'investigation_ID' => $r_second_part['investigation_id'],
                    'cargo_damage_ID' => $r_second_part['cargo_damage_id'],
                    'findings' => $r_second_part['findings'],
                    'actions_taken' => $r_second_part['actions_taken'],
                    'outcome' => $r_second_part['outcome'],
                ),
            ),
            'handling_investigation' => array(
                'internal_reference' => $r_second_part['internal_reference'],
                'internal_status' => $r_second_part['internal_status'],
                'external_status' => $r_second_part['external_status'],
                'validation_completed' => $r_second_part['validation_completed'],
                'claim_notice_date' => isset($r_first_part['crdate']) ? date('Y-m-d', $r_first_part['crdate']) : '', // claim notice is record claim notice date
                'preliminary_claim' => isset($r_second_part['preliminary_claim']) ? date('Y-m-d', $r_second_part['preliminary_claim']) : '',
                'formal_claim' => isset($r_second_part['formal_claim']) ? date('Y-m-d', $r_second_part['formal_claim']) : '',
                'received_in_time' => $r_second_part['received_in_time'],
                'hardcopy_of_claim' => $r_second_part['hardcopy_of_claim'],
                'processing_date' => isset($r_second_part['acdate']) ? date('Y-m-d', $r_second_part['acdate']) : '',
                'claimant_type' => $r_second_part['claimant_type'],
                'CASS_number' => $r_second_part['cass'],
                'subrogation_required' => $r_second_part['subrogation_required'],
                'special_handling_codes_and_extra_details' => array(
                    'special_handling_codes' => $special_handling_codes,
                    'service_codes' => $service_codes,
                    'billing_code' => $r_second_part['code_billing'],
                    'claimed_weight' => $r_second_part['claimed_weight'] . '(kg)',
                    'gross_weight' => $r_second_part['gross_weight'] . '(kg)',
                    'chargable_weight' => $r_second_part['chargable_weight'] . '(kg)',
                ),
                'investigation' => array(
                    'movement' => $r_second_part['movement'],
                    'reporting_station' => $r_second_part['reporting_station'],
                    'incident_station' => $r_second_part['incident_station'],
                    'investigation_remarks' => array(
                        'details' => $r_second_part['investigation_remarks'],
                        'cause' => $r_second_part['investigation_cause'],
                        'irregularity' => $r_second_part['investigation_irregularity'],
                        'conclusion' => $r_second_part['investigation_conclusion'],
                        'carrier_liability' => $r_second_part['investigation_carrier_liability'],
                        'responsible_partner' => $r_second_part['investigation_responsible_partner'],
                        'company_name' => $r_second_part['investigation_company_name'],
                    ),
                ),
                'dossier_closing' => array(
                    'sla_30' => isset($r_second_part['sla_30']) ? date('Y-m-d', $r_second_part['sla_30']) : '',
                    'sla_60' => isset($r_second_part['sla_60']) ? date('Y-m-d', $r_second_part['sla_60']) : '',
                    'sla_90' => isset($r_second_part['sla_90']) ? date('Y-m-d', $r_second_part['sla_90']) : '',
                    'doss_close_date' => isset($r_second_part['sla_90']) ? date('Y-m-d', $r_second_part['doss_close_date']) : '',
                    'doss_length' => $r_second_part['doss_length'],
                    'claimant_extention' => $r_second_part['sla_extend'],
                    'SLA_level_achieved' => $r_second_part['sla_level'],
                ),
                'survey_result' => array(
                    'feedback' => $r_second_part['srv_txt'],
                ),
            ),
            'liability_calculateion_&_settlement' => array(
                'auto_calculation' => $r_third_part['money_auto'],
                'mishandled_weight' => $r_third_part['calc_weight'],
                'convention' => $r_third_part['convention'],
                'exchange_date' => isset($r_third_part['xchg_date']) ? date('Y-m-d', $r_third_part['xchg_date']) : '',
//                'calculation'=>'',
//                'exchange_rate'=>'',
                'financial_details' => array(
                    'final_claim_amount' => array(
                        'value' => $r_third_part['claim_amt'],
                        'curr' => isset($r_third_part['claim_amt_curr']) ? $currencies[$r_third_part['claim_amt_curr']] : '',
                    ),
                    'insurance_and_provision' => array(
                        'insured_amount' => $r_third_part['insured_usd'],
                        'provision' => $r_third_part['provision_usd'],
                        'insurance_reference' => $r_third_part['ins_ref_no'],
                        'provision_date' => isset($r_third_part['provi_date']) ? date('Y-m-d', $r_third_part['provi_date']) : '',
                    ),
                    'settlement' => array(
                        'suggested_amount' => $r_third_part['suggested_usd'],
                        'paid_amount' => $r_third_part['paid_usd'],
                        'payment_date' => isset($r_third_part['settle_date']) ? date('Y-m-d', $r_third_part['settle_date']) : '',
//                        'exchange_rate'=>'',
                        'payment_basis' => $r_third_part['settle_basis'],
                        'payment_currency' => isset($r_third_part['pcur_curr']) ? $currencies[$r_third_part['pcur_curr']] : '',
                    ),
                ),
            ),
        );

        $task_q = "SELECT `tsk`.*,
                    `iss`.`track`AS `_iss_track`,
                    `iss`.`cache_primary`AS `_iss_cache_primary`,
                    `iss`.`cache_secondary`AS `_iss_cache_secondary`,
                    `ist`.`id`AS `_ist_id`,
                    `ist`.`key`AS `_ist_key`,
                    `ist`.`name`AS `_ist_name`,
                    `grp`.`id`AS `_grp_id`,
                    `grp`.`type`AS `_grp_type`,
                    `grp`.`name`AS `_grp_name`,
                    `usr`.`id`AS `_cre_id`,
                    `usr`.`name`AS `_cre_name`,
                    `usr`.`email`AS `_cre_email`,
                    `usr`.`contact_id`AS `_cre_contact_id`,
                    `dlg`.`name`AS `_dlg_name`,
                    `dlg`.`email`AS `_dlg_email`,
                    `dlg`.`contact_id`AS `_dlg_contact_id`,
                    `org`.`id`AS `_org_id`,
                    `org`.`type_id`AS `_org_type_id`,
                    `org`.`name`AS `_org_name`,
                    `org`.`contact_id`AS `_org_contact_id`FROM `tasks`AS tsk 
                    LEFT JOIN `issues`AS iss ON `tsk`.link_id=`iss`.id 
                    LEFT JOIN `issue_types`AS ist ON `iss`.type_id=`ist`.id 
                    LEFT JOIN `groups`AS grp ON `grp`.id=`iss`.group_id 
                    LEFT JOIN `users`AS usr ON `tsk`.added_by=`usr`.id 
                    LEFT JOIN `users`AS dlg ON `tsk`.delegate_id=`dlg`.id 
                    LEFT JOIN `organizations`AS org ON `tsk`.org_id=`org`.id 
                    WHERE `tsk`.link_type='record'AND `tsk`.link_id='$link_id'AND `tsk`.link_type='record' "
                . "ORDER BY IF(ISNULL(`tsk`.status),1,0), `tsk`.status asc";
        $task_r = MVC_Core::$db->query($task_q);
        $task_response = array(
            'open_tasks' => 0,
            'details' => array()
        );

        foreach ($task_r as $task) {
            if ($task['status'] != 'closed') {
                $task_response['open_tasks']++;
            }
            $task_response['details'][] = array(
                'task_title' => $task['title'], // task name column
                'assign_to' => $task['assign_name'], // delegated user
                'task_status' => $task['status'],
            );
        }
        $finalData['record_task_details'] = $task_response;

        $file_q = "SELECT `fil`.*,
                `usr`.`name`AS `_usr_name`,
                `usr`.`contact_id`AS `_usr_contact_id`,
                `org`.`name`AS `_org_name`,
                `org`.`type_id`AS `_org_type_id`,
                `org`.`contact_id`AS `_org_contact_id`FROM `files`AS fil 
                LEFT JOIN `users`AS usr ON `fil`.user_id=`usr`.id 
                LEFT JOIN `organizations`AS org ON `fil`.org_id=`org`.id 
                WHERE `fil`.linked_with='issue' AND `fil`.linked_id='$link_id' ORDER BY `fil`.name ASC";
        $file_r = MVC_Core::$db->query($file_q);

        foreach ($file_r as $file) {
            $file_result[] = array(
                'file_name' => $file['name'],
                'uploaded_on' => $file['mtime'],
                'uploaded_by' => $file['_usr_name'],
            );
        }
        $finalData['record_file_details'] = $file_result;
        unset($r);

        $contact_q = QueryFactory::select()
                ->table(MVC_Core::$db->table('c2i'), array('contact_id', 'role', 'dt_link', 'label', 'cnv_id', 'visibility'))
                ->where("`c2i`.issue_id = ?", $link_id)
                ->order("`c2i`.dt_link", "ASC");
        $contact_q->where("`c2i`.visibility = 'public'");
        $contact_q->table(MVC_Core::$db->table('org'), array("org_name" => "name", "type_id"), 'left', '`c2i`.org_id = `org`.id');
        $contact_q->table(array(MVC_Core::$db->tab['ctx'], 'ctx'), array("org_id", "user_id", "type", "ctime", "email", "name"), 'left', '`c2i`.contact_id = `ctx`.id');
        $contact_r = MVC_Core::$db->query($contact_q);
        $contact_result = array();
        foreach ($contact_r as $contact) {
            $contact_result[] = array(
                'name' => $contact['name'],
                'email' => $contact['email'],
                'org_name' => $contact['org_name'],
                'label' => $contact['label'],
                'visibility' => $contact['visibility'],
                'created' => $contact['ctime'],
            );
        }
        $finalData['contact_details'] = $contact_result;

        $history_q = QueryFactory::select()
                ->table(MVC_Core::$db->table('isu'))
                ->where("`isu`.issue_id = ?", $link_id)
                ->order("`isu`.dt DESC");
        $history_q->table(MVC_Core::$db->table('usr'), array("id", "org_id", "cdep_id", "user_name" => "name", "user_email" => "email", "contact_id"), 'left', '`usr`.id = `isu`.update_by');
        $history_r = MVC_Core::$db->query($history_q);

        $history_updates = array();

        foreach ($history_r as $history) {

            // Initialize safely
            $history['op'] = '';
            $history['op_info'] = '';

            // Safe date conversion
            if (isset($history['dt']) && is_numeric($history['dt'])) {
                $history['dt'] = date("Y-m-d H:i:s", $history['dt']);
            } else {
                $history['dt'] = '';
            }

            // Decode JSON safely
            $data = array();

            if (!empty($history['update_data'])) {

                $decoded = json_decode($history['update_data'], true);

                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }

            // Process update types
            if (!empty($history['update_type'])) {

                switch ($history['update_type']) {

                    case 'create':

                        $history['op'] = "Record created";

                        if (isset($data['ip'])) {

                            $ip = is_numeric($data['ip']) ? long2ip($data['ip']) : $data['ip'];

                            $history['op_info'] = sprintf(
                                    "IP address: %s",
                                    $ip
                            );
                        } else {

                            $history['op_info'] = "Record created";
                        }

                        break;

                    case 'group':

                        $history['op'] = "Data group altered";
                        $history['op_info'] = "Group information updated";

                        break;

                    case 'msgsent':

                        $history['op'] = "Message sent";
                        $history['op_info'] = "A message was sent";

                        break;

                    case 'msgrecv':

                        $history['op'] = "Message received";
                        $history['op_info'] = "A message was received";

                        break;

                    case 'xfd':

                        if (isset($data['_panel'])) {

                            if (
                                    isset($history['comment']) &&
                                    !empty($history['comment'])
                            ) {

                                $history['op'] = "Fields updated";
                                $history['op_info'] = $history['comment'];
                            } else {

                                $history['op'] = "Panel edited";

                                if (
                                        isset($data['_xfd_c']) &&
                                        intval($data['_xfd_c']) > 0
                                ) {

                                    $history['op_info'] = sprintf(
                                            "%d field%s altered in panel [%s]",
                                            intval($data['_xfd_c']),
                                            intval($data['_xfd_c']) > 1 ? "s were" : " was",
                                            $data['_panel']
                                    );
                                } else {

                                    $history['op_info'] = sprintf(
                                            "Contents of the fields in panel [%s] has not changed",
                                            $data['_panel']
                                    );
                                }
                            }
                        } else {

                            $history['op'] = "Fields update";

                            if (
                                    isset($data['_xfd_c']) &&
                                    intval($data['_xfd_c']) > 0
                            ) {

                                $history['op_info'] = sprintf(
                                        "%d field%s changed by automated process",
                                        intval($data['_xfd_c']),
                                        intval($data['_xfd_c']) > 1 ? "s" : ""
                                );
                            } else {

                                $history['op_info'] = "Update triggered but no changes detected";
                            }
                        }

                        break;

                    case 'cache':

                        $history['op'] = "Index cache change";
                        $history['op_info'] = "Original values:";

                        if (array_key_exists('primary', $data)) {

                            $history['op_info'] .= sprintf(
                                    " 1st: %s",
                                    empty($data['primary']) ? "_Removed_" : $data['primary']
                            );
                        }

                        if (array_key_exists('secondary', $data)) {

                            $history['op_info'] .= sprintf(
                                    " 2nd: %s",
                                    empty($data['secondary']) ? "_Removed_" : $data['secondary']
                            );
                        }

                        if (array_key_exists('tertiary', $data)) {

                            $history['op_info'] .= sprintf(
                                    " 3rd: %s",
                                    empty($data['tertiary']) ? "_Removed_" : $data['tertiary']
                            );
                        }

                        break;

                    case 'sharing':

                        $history['op'] = "Sharing updated";
                        $history['op_info'] = "Inter-organization sharing updated";

                        break;

                    case 'process':

                        $history['op'] = "Process change";
                        $history['op_info'] = "Process updated";

                        if (isset($data['process'])) {

                            switch ($data['process']) {

                                case 'opened':

                                    $history['op_info'] = "Viewed by staff for the first time";

                                    break;

                                case 'reactive':

                                    $history['op_info'] = "Reopened previously closed record";

                                    break;

                                case 'active':

                                    if (isset($data['pending_length'])) {

                                        $history['op_info'] = sprintf(
                                                "Record active (waiting %01.1f days)",
                                                $data['pending_length'] / 86400
                                        );
                                    } else {

                                        $history['op_info'] = "Record active";
                                    }

                                    break;

                                case 'shared':

                                    $history['op_info'] = "Shared between organizations";

                                    break;

                                case 'closed':

                                    if (isset($data['process_length'])) {

                                        $history['op_info'] = sprintf(
                                                "Closed record (%01.1f days)",
                                                $data['process_length'] / 86400
                                        );
                                    } else {

                                        $history['op_info'] = "Record closed";
                                    }

                                    break;
                            }
                        }

                        break;

                    case 'statprio':
                        if (isset($data['status_key'])) {

                            $history['op'] = "Status change";

                            $history['op_info'] = $priority_status_data['data']['statuses'][$data['status_key']];
                        } elseif (isset($data['priority_key'])) {

                            $history['op'] = "Priority change";

                            $history['op_info'] = $priority_status_data['data']['priorities'][$data['priority_key']];
                        }

                        break;

                    case 'assgn':

                        $history['op'] = "Assignment";

                        if (isset($data['assign'])) {

                            if (
                                    isset($data['assign']['name']) &&
                                    $data['assign']['name'] == "#SELF#"
                            ) {

                                $username = 'Unknown User';

                                if (
                                        isset($history['user_name']) &&
                                        !empty($history['user_name'])
                                ) {
                                    $username = $history['user_name'];
                                }

                                $history['op_info'] = sprintf(
                                        "Self-assignment by %s",
                                        $username
                                );
                            } else {

                                $assignName = isset($data['assign']['name']) ? $data['assign']['name'] : 'Unknown';

                                $history['op_info'] = sprintf(
                                        "Record assigned to user: %s",
                                        $assignName
                                );
                            }
                        }

                        if (isset($data['unassign'])) {

                            if (!isset($history['op_info'])) {
                                $history['op_info'] = '';
                            }

                            if (isset($data['assign'])) {

                                $prevName = isset($data['unassign']['name']) ? $data['unassign']['name'] : 'Unknown';

                                $history['op_info'] .= sprintf(
                                        "<br />Previous assignee: %s",
                                        $prevName
                                );
                            } else {

                                $unassignName = isset($data['unassign']['name']) ? $data['unassign']['name'] : 'Unknown';

                                $history['op_info'] = sprintf(
                                        "%s is no longer assigned",
                                        $unassignName
                                );
                            }
                        }

                        break;

                    case 'file':

                        $history['op'] = "File operations";

                        if (isset($data['uploaded'])) {

                            $history['op_info'] = sprintf(
                                    "%d file(s) uploaded",
                                    intval($data['uploaded'])
                            );
                        }

                        if (isset($data['deleted'])) {

                            $name = isset($data['name']) ? $data['name'] : 'Unknown';

                            $size = isset($data['size']) ? intval($data['size']) : 0;

                            $history['op_info'] = sprintf(
                                    "File named %s (%d bytes) deleted",
                                    $name,
                                    $size
                            );
                        }

                        break;

                    case 'task':

                        $history['op'] = "Task operation";
                        $history['op_info'] = "Task updated";

                        break;

                    case 'note':

                        $history['op'] = "Note updated";
                        $history['op_info'] = "Text-content entry changed";

                        break;

                    case 'contact':

                        $history['op'] = "Contact updated";
                        $history['op_info'] = "Attached contact altered";

                        break;

                    case 'xfo':

                        $history['op'] = "Legacy field update";
                        $history['op_info'] = "Old format xfields update";

                        break;

                    case 'oth':

                        if (isset($data['_tr_record'])) {

                            $history['op'] = "Training updated";
                            $history['op_info'] = "Training form updated";
                        } else {

                            $history['op'] = "Other operation";
                            $history['op_info'] = "Unknown operation";
                        }

                        break;

                    default:

                        $history['op'] = "Unknown operation";
                        $history['op_info'] = "Unsupported update type";

                        break;
                }
            } else {

                if (empty($data)) {

                    $history['op'] = "Errored entry";
                    $history['op_info'] = "No change recorded";
                } else {

                    $history['op'] = "Unknown";
                    $history['op_info'] = "Unrecognized history entry format";
                }
            }

            // Final safe push
            $history_updates[] = array(
                'log_date' => $history['dt'],
                'operation' => $history['op'],
                'operation_details' => $history['op_info'],
                'user_name' => isset($history['user_name']) ? $history['user_name'] : '',
                'user_email' => isset($history['user_email']) ? $history['user_email'] : '',
            );
        }

//print_r($history_updates);

        $finalData['history'] = $history_updates;
        return $finalData;

//        print_r($finalData);
    }

    public function updateLastSyncTime($api_url, $org_id, $time) {
        $api_url = addslashes($api_url);
        $org_id = intval($org_id);
        $time = addslashes($time);

        $q = "INSERT INTO api_sync_state 
            (api_url, org_id, last_use_time)
          VALUES 
            ('$api_url', '$org_id', '$time')
          ON DUPLICATE KEY UPDATE 
            last_use_time = VALUES(last_use_time)";

        return MVC_Core::$db->query($q);
    }

//    IWI API integration to fetch record details for air cargo claim by track id 21042026 end
//    IWI API integration to fetch last updated record 28052026 start
    public function getLastSyncTime($org_id, $api_url) {
        $q = QueryFactory::select()
                ->table('api_sync_state')
                ->where("org_id = ?", $org_id)
                ->where("api_url = ?", $api_url)
                ->limit(1);

        $r = MVC_Core::$db->query($q);

        if ($r && count($r) > 0) {
            $row = $r->current();
            return $row['last_use_time'];
        }

        return null;
    }

    public function get_Issues_UpdatedSince($org_id, $from_time) {
        $q = "SELECT iss.id, iss.track
            FROM issues AS iss
            LEFT JOIN issue_orgdata AS iorg 
                ON iss.id = iorg.issue_id
            WHERE iss.type_id = '1'
            AND iorg.org_id = '$org_id'
            AND iss.utime > '$from_time'";
        $r = MVC_Core::$db->query($q);

        return $r;
    }
    public function get_Issues_ByDateRange(
            $org_id,
            $from_date,
            $to_date
    ) {

        $q = "
        SELECT iss.id, iss.track
        FROM issues AS iss
        LEFT JOIN issue_orgdata AS iorg
            ON iss.id = iorg.issue_id
        WHERE iss.type_id = '1'
        AND iorg.org_id = '$org_id'
        AND iss.utime >= '$from_date'
        AND iss.utime <= '$to_date'
        ORDER BY iss.id ASC
    ";

        $r = MVC_Core::$db->query($q);

        return $r;
    }

//    IWI API integration to fetch last updated record 28052026 end

    
    
    
    
}

/**
 * Base interface for WebAPI Functions
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage WebAPI Plugins
 * @version 2.10
 */
interface App_Plugin_WebAPI_fn
{

}

/**
 * Base interface for WebAPI Connectors
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage WebAPI Plugins
 * @version 2.10
 */
interface App_Plugin_WebAPI_connector
{

}

/**
 * Base interface for WebAPI CODECs
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage WebAPI Plugins
 * @version 2.10
 */
interface App_Plugin_WebAPI_codec
{

}

/**
 * Base interface for WebAPI Data Mappers
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage WebAPI Plugins
 * @version 2.10
 */
interface App_Plugin_WebAPI_mapper
{

}