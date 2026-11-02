<?php
/**
 * Web Application Programming Interface
 * - Supports data access calls (create, read, update) - Delete not supported through API!
 * -
 */
class App_Ctrl_Public_WebAPI extends App_Ctrl_Base
{

	/** 
	 * WebAPI Data Model
	 * @var App_Model_WebAPI
	 */
	private $api;

	/**
	 * Controller Constructor 
	 */
	function __construct()
	{
		parent::__construct();
		$this-> api = $this-> factory_Model("WebAPI", "api"); 
	} 
 
	/** 
	 * Main Root Action - Denies access to "default" action (index)
	 */
	public function action_Index()
	{
		return header("HTTP/1.0 403 WebAPI - Access denied on default action");
		MVC_Core::shutdown();
	}

	/**
	 * Checks if the user is allowed to use an API function
	 * @param int $uid
	 * @param string $key
	 * @param string $func
	 */
	protected function check_Access($key, $func)
	{
		$api_check = $this-> api-> check($key, $func);
		if(empty($api_check))
			return MVC_Core::$debug-> get_Last_Dump();
//var_dump($api_check);
		return $api_check;
	}

	/**
	 * Record the fact that an API call has completed successfuly
	 *
	 * @param string $key
	 * @param string $action
	 */
	protected function record_Success($key, $action, $org_id = null, $user_id = null, $logdata = null)
	{
		$calls_feedback = $this-> api-> update($key, array(
			'calls'=> 1 // This is not the raw number of calls, but how much they increase with this update
		));
  		//var_dump($calls_feedback);

		$this-> api-> log_Entry($action, $org_id, $user_id, 'success', $logdata);
	}
	
	// Preliminary Cargo Claims Time Barred Status change older than 2 years and 1 month
	public function action_PreliminaryTimeBarredStatusChange (MVC_Request $rq)
    {
       if (!$rq->key) {
            echo "<h1>Preliminary Cargo Claims Time Barred Status change older than 2 years and 1 month</h1>";
        }   
       $this->api->preliminary_cargo_claims_time_barred_status();
        
    }
	
	
	// Update MRN Open Days as per MRN Date in MRN Monitor 
	public function action_MRNOpenDaysUpdate (MVC_Request $rq)
    {
       if (!$rq->key) {
            echo "<h1>Update MRN Open Days as per MRN Date in MRN Monitor </h1>";
        }   
       $this->api->mrn_opendays_update();
        
    }

	/**
	 * The WebAPI Plugin for the specified command
	 * @var App_Plugin_WebAPI
	 */
// 	protected $api_plugin;
    public function action_DuplicateAuditRecord(MVC_Request $rq)
    {
       if (!$rq->key) {
            echo "<h1>Key missed and it is not allowed to replicate one record</h1>";
        }   
       $this->api->check_duplicateRecord();
        
    }
	/**
	 * Execute predefined command (from the "webapi_commands" table)
	 * - "uid" - User ID - Must match a system user ID, the API only works for registered users (Used to obtain org-id)
	 * - "key" - Api Key, associated with an user account. If an user doesn't have a key or the key is invalid, the API will deny access
	 * - "cmd" - Which command to execute ? It must exist in the database or an error is returned
	 * @param MVC_Request $rq
	 */
	public function action_Execute(MVC_Request $rq)
	{   
		//echo "ffdfddf";
		
		if(!$rq-> key)
			return header("HTTP/1.0 400 WebAPI - Key missing from the request");

		if(!$rq-> cmd)
			return header("HTTP/1.0 400 WebAPI - Command missing from the request");

		$api_check = $this-> check_Access($rq-> key, "Execute");
		if(in_array("error", $api_check)) {
			$api_check['key'] = $rq-> key;
			$api_check['cmd'] = $rq-> cmd;
			unset($api_check['meta']);
			$this-> api-> log_Entry("execute", null, null, 'access', $api_check);
			return header("HTTP/1.0 400 WebAPI - Access Check Failed"); // Access is not permitted (invalid key), break of the API call
		}

/*
		// Verify if the user still exists in the system - Not sure if this is really needed
		$user = $this-> user-> read($api_check['user_id']); // , array('organization', 'department')
		if(empty($user))
			return header("HTTP/1.0 404 WebAPI - User for the specified API key doesn't exist anymore. Check configuration !");
*/

		$res = $this-> api-> execute_Command($rq-> cmd, $api_check['org_id'], $api_check['user_id']);
		
		//var_dump($res);
		if(empty($res)) {
			// The command failed - Error should be in the log
			$dump = MVC_Core::$debug-> get_Last_Dump();
			$dump['key'] = $rq-> key;
			$dump['cmd'] = $rq-> cmd;
			unset($dump['meta']);
			$this-> api-> log_Entry("execute", $api_check['org_id'], $api_check['user_id'], 'error', $dump);
			return header("HTTP/1.0 500 WebAPI Failed running command {$rq-> cmd}. Please check the log");
		}

		// Command succes ! Data was imported, exported or processed in some other way. Record the success in the log and increment the log counters
		$this-> record_Success($rq-> key, 'execute', $api_check['org_id'], $api_check['user_id'], array(
			'command'=> $rq-> cmd,
			'result'=> $res
		));
//echo "succ";
		// Test run - Get user sessions (including admin)
		$q=QueryFactory::select()
		-> table(MVC_Core::$db-> table('ass'), array('user_id', 'session_id', 'dt', 'logon', 'count_liftoffs', 'request_uri', 'ipaddr'))
		-> where("`user_id` = 2")
		-> order("`ass`.dt DESC")
		-> limit(1);

		$r = MVC_Core::$db-> query($q)-> current();
		if($r) {
			$q=QueryFactory::update(MVC_Core::$db-> tab['ass'])
			-> table(MVC_Core::$db-> tab['ass'])
			-> values(array(
				'external'=> json_encode(array(
					'evan'=> array(array(
						"message"=> "WebAPI command {$rq-> cmd} executed successfuly",
						"type"=> "hal",
						"opts"=> array(
							'permanent'=> true,
							'timeout'=> time()+ 20
						)
					))
				))
			))
			-> where("`user_id` = 2");

			MVC_Core::$db-> query($q);
		}

		// ... then return the result as JSON
		$this-> send_JSON($res);
	}
	/**
	 * Temporary test action that shows a form allowing uploading a file to the webapi function "RecImport"
	 */
	public function action_TESTAPI()
	{
?>
<form action="/webapi?action=Execute" method="post"
	enctype="multipart/form-data">
	<h3>This form will test the Execute action for the API</h3>
	<p>API Key (represents the user)</p>
	<input type="text" name="key" value="ff01ae7421b58e10" />
	<p>Command to test</p>
	<select name="cmd">
		<option value="ftp-ncts-local">NCTS FTP connect test and Edifact
			scanning</option>
		<option value="load-irregs">Load Irregularities CSV from upload
			(load-irregs)</option>
		<!-- <option value="ftp-irregs">Load Irregularities CSV from FTP (ftp-irregs)</option> -->
	</select>
	<p>
		Upload the test file(s) here<br />(format must match the tested
		command or the test will fail)
	</p>
	<!-- File field Name(s) is not relevant, they all go to $_FILES anyway (but they do have to be unique) -->
	<input type="file" name="upload1"><br />
	<hr />
	<input type="submit" value="The cake is a lie">
</form>
<?php
	}
        
            
//        IWI create api for getting record details start

    public function action_getRecordDetails(MVC_Request $rq) {
        $awb_id = $rq->awb_id;
        if (!$rq->key) {
            return header("HTTP/1.0 400 WebAPI - Key missing from the request");
        }
        $result = $this-> api-> getRecordDetails($awb_id,$rq->key);
        $this->send_JSON($result);
        exit();
    }

//       IWI create api for getting record details end
    
    
    
//     IWI API integration to fetch record details for air cargo claim by track id 21042026 start
   
    
    public function action_getAirCargoClaimDetailsByTrack(MVC_Request $rq) {
        if (!$rq->key) {
            $result['status'] = false;
            $result['message'] = 'Authentication Key missing from the request';
            $this->send_JSON($result);
            return header("HTTP/1.0 400 WebAPI - Authentication Key missing from the request");
        }
        if (!$rq->track_id) {
            $result['status'] = false;
            $result['message'] = 'Track ID not found';
            $this->send_JSON($result);
            return header("HTTP/1.0 404 WebAPI - Track ID not found");
        }

        $getSecretKey = $this->api->getAPISecretKeyByOrg('208'); // For Atlas/Polar Org Only now

        if ($rq->key && $rq->key != $getSecretKey) {
            $result['status'] = false;
            $result['message'] = 'Authentication Key missing from the request';
            $this->send_JSON($result);
            return header("HTTP/1.0 400 WebAPI - Authentication Key missing from the request");
        }

        $track_id = $rq->track_id;
        $recordDetails = array();
        $recordDetails = $this->api->getAirCargoClaimDetailsByTrack($track_id);
        $result = array();
        if (!empty($recordDetails)) {
            $result['status'] = true;
            $result['message'] = 'Record data fetched successfully';
            $result['data'] = $recordDetails;
        }



        $this->send_JSON($result);
        exit();
    }

    public function action_getAirCargoClaimDetailsByAWB(MVC_Request $rq) {
        if (!$rq->key) {
            $result['status'] = false;
            $result['message'] = 'Authentication Key missing from the request';
            $this->send_JSON($result);
            return header("HTTP/1.0 400 WebAPI - Authentication Key missing from the request");
        }
        if (!$rq->awb_id) {
            $result['status'] = false;
            $result['message'] = 'AWB Number not found';
            $this->send_JSON($result);
            return header("HTTP/1.0 404 WebAPI - AWB Number not found");
        }

        $getSecretKey = $this->api->getAPISecretKeyByOrg('208'); // For Atlas/Polar Org Only now

        if ($rq->key && $rq->key != $getSecretKey) {
            $result['status'] = false;
            $result['message'] = 'Authentication Key missing from the request';
            $this->send_JSON($result);
            return header("HTTP/1.0 400 WebAPI - Authentication Key missing from the request");
        }

        $awb_id = $rq->awb_id;
        $recordDetails = array();

        $org_id = '208';
        $getAllTrackbyAWB = $this->api->getAllTrackbyAWB($awb_id, $org_id);

        foreach ($getAllTrackbyAWB as $record) {
//                echo $record['track'];
            $recordDetails[] = $this->api->getAirCargoClaimDetailsByTrack($record['track']);
        }


//        $recordDetails = $this->api->getAirCargoClaimDetailsByTrack($track_id);
        $result = array();
        if (!empty($recordDetails)) {
            $result['status'] = true;
            $result['message'] = 'Record data fetched successfully';
            $result['data'] = $recordDetails;
        } else {
            $result['status'] = true;
            $result['message'] = 'No record found';
        }



        $this->send_JSON($result);
        exit();
    }

    public function action_getAllAirCargoClaim(MVC_Request $rq) {
        if (!$rq->key) {
            $result = array(
                'status' => false,
                'message' => 'Authentication Key missing from the request'
            );
            $this->send_JSON($result);
            header("HTTP/1.0 400 WebAPI - Authentication Key missing from the request");
            exit();
        }
        $org_id = '208'; // Atlas/Polar Org Only
        $getSecretKey = $this->api->getAPISecretKeyByOrg($org_id);
        if ($rq->key != $getSecretKey) {
            $result = array(
                'status' => false,
                'message' => 'Invalid Authentication Key'
            );
            $this->send_JSON($result);
            header("HTTP/1.0 401 Unauthorized");
            exit();
        }
        $api_url = '/webapi?action=getAllAirCargoClaim';
        $this->api->updateLastSyncTime(
                $api_url,
                $org_id,
                date('Y-m-d H:i:s')
        );

        $limit = 2000;
        $last_id = 0;
        if (!empty($rq->last_id) && is_numeric($rq->last_id)) {
            $last_id = intval($rq->last_id);
        }
        $getAll_Track_ByOrg = $this->api->get_IssueData_ByOrg(
                $org_id,
                $limit,
                $last_id
        );
        $recordDetails = array();
        $new_last_id = $last_id;
        foreach ($getAll_Track_ByOrg as $awb) {
            if (!isset($awb['id'])) {
                continue;
            }
            $new_last_id = $awb['id'];
            $track = $awb['track'];
            $details = $this->api->getAirCargoClaimDetailsByTrack($track);
            if (!empty($details)) {
                $recordDetails[] = $details;
            }
        }
        $result = array();
        if (!empty($recordDetails)) {
            $result['status'] = true;
            $result['message'] = 'Record data fetched successfully';
            $result['count'] = count($getAll_Track_ByOrg);
            $result['last_id'] = $new_last_id;
            $result['has_more'] = (count($getAll_Track_ByOrg) >= $limit) ? true : false;
            $result['data'] = $recordDetails;
        } else {
            $result['status'] = true;
            $result['message'] = 'No record found';
            $result['count'] = 0;
            $result['last_id'] = $last_id;
            $result['has_more'] = false;
            $result['data'] = array();
        }
        $this->send_JSON($result);
        exit();
    }

    

//     IWI API integration to fetch record details for air cargo claim by track id 21042026 end
    
    
//IWI API integration to fetch last updated record 28052026 start
    public function action_getUpdatedAirCargoClaim(MVC_Request $rq) {

    try {
        if (!$rq->key) {
            $this->send_JSON(array(
                'status' => false,
                'message' => 'Authentication Key missing'
            ));
            header("HTTP/1.0 400 Bad Request");
            exit();
        }
        $org_id = '208';
        $getSecretKey = $this->api->getAPISecretKeyByOrg($org_id);
        if ($rq->key != $getSecretKey) {
            $this->send_JSON(array(
                'status' => false,
                'message' => 'Invalid Authentication Key'
            ));
            header("HTTP/1.0 401 Unauthorized");
            exit();
        }
        $api_url = '/webapi?action=getUpdatedAirCargoClaim';
        $is_manual_replay = false;
        if (!empty($rq->from_date)) {
            // Validate from_date
            if (!strtotime($rq->from_date)) {
                $this->send_JSON(array(
                    'status' => false,
                    'message' => 'Invalid from_date format'
                ));
                header("HTTP/1.0 400 Bad Request");
                exit();
            }
            $from_date = date('Y-m-d H:i:s', strtotime($rq->from_date));
            // Validate to_date
            if (!empty($rq->to_date)) {
                if (!strtotime($rq->to_date)) {
                    $this->send_JSON(array(
                        'status' => false,
                        'message' => 'Invalid to_date format'
                    ));
                    header("HTTP/1.0 400 Bad Request");
                    exit();
                }
                $to_date = date('Y-m-d H:i:s', strtotime($rq->to_date));
            } else {
                $to_date = date('Y-m-d H:i:s');
            }
            // Fetch data by date range
            $issues = $this->api->get_Issues_ByDateRange(
                $org_id,
                $from_date,
                $to_date
            );
            $is_manual_replay = true;
        } else {
            $last_sync_time = $this->api->getLastSyncTime(
                $org_id,
                $api_url
            );
            // First-time sync ? last 30 days
            if (empty($last_sync_time)) {
                $last_sync_time = date(
                    'Y-m-d 00:00:00',
                    strtotime('-30 day')
                );
            }
            // Fetch updated records
            $issues = $this->api->get_Issues_UpdatedSince(
                $org_id,
                $last_sync_time
            );
        }
        $data = array();
        foreach ($issues as $row) {
            $details = $this->api->getAirCargoClaimDetailsByTrack(
                $row['track']
            );
            if (!empty($details)) {
                $data[] = $details;
            }
        }
        if (!$is_manual_replay) {
            $this->api->updateLastSyncTime(
                $api_url,
                $org_id,
                date('Y-m-d H:i:s')
            );
        }
        $response = array(
            'status' => true,
            'message' => 'Updated records fetched successfully',
            'count' => count($data),
            'data' => $data
        );
        // Optional replay metadata
        if ($is_manual_replay) {
            $response['replay_mode'] = true;
            $response['from_date'] = $from_date;
            $response['to_date'] = $to_date;
        }
        $this->send_JSON($response);
        exit();
    } catch (Exception $e) {
        $this->send_JSON(array(
            'status' => false,
            'message' => $e->getMessage()
        ));
        header("HTTP/1.0 500 Internal Server Error");
        exit();
    }
}

//IWI API integration to fetch last updated record 28052026 end
}