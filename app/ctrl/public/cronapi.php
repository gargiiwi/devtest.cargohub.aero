<?php

class App_Ctrl_Public_CronAPI extends App_Ctrl_Base {

    /**
     * Cron operations controller Constructor
     */
    function __construct() {
        parent::__construct();

        // Controller specific models
        $this->cron = $this->factory_Model('Cron'); 
        
        
         $this->m_comm = $this->factory_Model("Comm", "m_comm"); //IWI report cron settings for admin
         $this->record = $this->factory_Model('Issue', "record"); // IWI report cron settings for admin
    }

    public function action_sendreadsignreminder() {
//        echo 12121; 
        $getUserDetails = $this->cron->getUserDetails();   
//         print_r($getUserDetails);
          
         $myfile = fopen("remcron.txt", "a") or die("Unable to open file!");
$txt = "execute   "; 
fwrite($myfile, $txt);  
 
 
fclose($myfile); 
        $getAllSignedDoc_array = array(); 
        foreach ($getUserDetails as $users) { 
            $getAllSignedDoc = $this->cron->getAllSignedDoc($users['id']);
            if (!empty($getAllSignedDoc)) {
                $getAllSignedDoc_array[] = array(
                    'user_id' => $users['id'],
                    'dep_id' => $users['cdep_id'],
                    'org_id' => $users['org_id'],
                    'getAllSignedDoc' => $getAllSignedDoc, 
                );
            }
        }

        $needToSendReminderData = array();
        foreach ($getAllSignedDoc_array as $key => $val) {
            $getList = $this->cron->getAllPendingReadSign($val['user_id'], $val['dep_id'],$val['org_id'], $val['getAllSignedDoc']);
            foreach ($getList as $getListVal) {
                if (!empty($getListVal)) {
//                $needToSendReminderData[$val['user_id']][]=$getListVal;
//                    $needToSendReminderData[] = $val['user_id'];
                    
                    $needToSendReminderData[$val['user_id']][] =$getListVal ;
                    $needToSendReminderData_user_ids[]=$val['user_id'];
                }
            }
        }
        
//print_r($needToSendReminderData);
//        $needToSendReminderData = array_unique($needToSendReminderData);
        $needToSendReminderData_user_ids = array_unique($needToSendReminderData_user_ids);
        $getCronReminderSentLastDate = $this->cron->getCronReminderSentLastDate($needToSendReminderData_user_ids);
        

        $needToSendNow = array();
        $temp_array = array();
        
        
        foreach ($getCronReminderSentLastDate as $data) {
            
            


            if (($key = array_search($data['user_id'], $needToSendReminderData)) !== false) {
                unset($needToSendReminderData[$data['user_id']]);
            }
            if (($key = array_search($data['user_id'], $needToSendReminderData_user_ids)) !== false) {
                unset($needToSendReminderData_user_ids[$key]);
            }

            $givenDate = $data['created_at'];

// Create DateTime object for the given date
            $givenDateTime = new DateTime($givenDate);

// Get the current date and time
            $currentDateTime = new DateTime();

// Subtract 5 days from the current date
            $fiveDaysAgo = new DateTime();
            $fiveDaysAgo->modify('-5 days');
//            $fiveDaysAgo->modify('-1 days');

// Compare if the given date is greater than 5 days ago
            if ($givenDateTime < $fiveDaysAgo) {
                if (!in_array($data['user_id'], $temp_array)) {
                    $needToSendNow[] = $data['user_id'];
                }
            }

            $temp_array[] = $data['user_id'];
        }


        


        $needToSendUserIds = array();
        $needToSendUserIds = array_unique(array_merge($needToSendNow, $needToSendReminderData_user_ids));
        
        
//        echo "<pre>";
//        
//        print_r($needToSendUserIds);
//        exit();
        if (!empty($needToSendUserIds)) {
            $this->cron->sendReadSignReminderMail($needToSendUserIds,$needToSendReminderData);
        }
        
//        IWI cron run log start
        $cron_log_data=array(
            'page_name'=>'cronapi',
            'function'=>'sendreadsignreminder',
        );
        $q = QueryFactory::insert()
                ->table('cron_run_log')
                ->values(array('cron_details'=> json_encode($cron_log_data)));
        MVC_Core::$db->query($q);
//        IWI cron run log end
    }
    
    
    public function action_sendTestMail() {
//    echo "hhh11";
//        $this->cron->sendTestMail('anirban.in.net@gmail.com');
     
    $this->cron->sendTestMail('gargiiwi@gmail.com');
    }

    
//    IWI Refresh after days start
    public function action_refreshdocuments() {
        $getAllNoteNeedToRefresh = $this->cron->getAllNoteNeedToRefresh();
        foreach ($getAllNoteNeedToRefresh as $noteData) {
            $givenDate = ($noteData['update_dt'] != '') ? $noteData['update_dt'] : $noteData['dt'];
            $currentDateTime = new DateTime();
            if (isset($noteData['last_refresh_date']) && $noteData['last_refresh_date'] != '') {
                $NextRefreshDate = new DateTime($noteData['last_refresh_date']);
                $NextRefreshDate->modify('+' . $noteData['refresh_after_days'] . ' days');
            } else {
                $NextRefreshDate = new DateTime($givenDate);
                $NextRefreshDate->modify('+' . $noteData['refresh_after_days'] . ' days');
            }

            if ($NextRefreshDate <= $currentDateTime) {
                $this->cron->updateReadSignNeedRefresh($noteData['id']);
                $this->cron->updateRefreshLog($noteData['id']);
            }
        }
        
        
    $this->cron->sendTestCheckMail('gargiiwi@gmail.com','Refresh document');
    }

//    IWI Refresh after days end
    
    
    
    
    
//    IWI Create section for Notification start
    public function action_checknotitoarchive() { 
        $getAllNotiNeedToArchive = $this->cron->getAllNotiNeedToArchive();
        
    $this->cron->sendTestCheckMail('gargiiwi@gmail.com','Check Noti to Archive');
        
        
    }

//    IWI Create section for Notification end
    
    

//    IWI report cron settings for admin start 



    public function action_sendreportcronmail() {

        $getAllTrainingExpiry = $this->org->getReportCron();
      
        foreach ($getAllTrainingExpiry as $report) {
            if ($report['status'] != 1)
                continue;
            $send = false;
            $today = date('l');
            $todayDate = date('Y-m-d');

            switch ($report['email_frequency']) {
                case 'daily':
                    $send = true;
                    break;
                case 'weekly':
                    if ($today == $report['email_day'])
                        $send = true;
                    break;
                case 'biweekly':
                    if ($today == $report['email_day'] && (date('W') % 2 == 0))
                        $send = true;
                    break;
                case 'monthly':
                    if ($todayDate == $report['email_day'])
                        $send = true;
                    break;
            }

            if ($send) {


                $filters = json_decode($report['send_by_filter'], true);
                $filterData = array();
                foreach ($filters as $key => $val) {
                    $filterData[str_replace("'", "", $key)] = $val;
                }
                $file_path = '';
                $mailText='';
//                print_r($filterData);
                if ($report['report_type'] == 'Training Expiry') {
//                    $getDataaa = $this->action_Load_Advance_External_Training_Record_Listing($report["org_id"], $filterData);
                    $getDataaa = $this->action_Load_Training_Record_Listing($report["org_id"], $filterData);
//                    if($report['user_email']=='Stefan@cargoscreeners.nl'){
//                    echo "<pre>";
//                print_r($report);
//                    print_r($getDataaa);
//                    }
//                    exit();
                    $file_path = $this->action_CreateReportDataForCron($getDataaa);
                    $mailText='You have '.count($getDataaa).' data for Training Expiry';
                    
                }
//exit();
                if ($report['report_type'] == 'Audit Plan') {
                    $getDataaa = $this->action_Load_Audit_Plan_Listing($report["org_id"], $filterData);
                    $file_path = $this->action_CreateAuditPlanDataForCron($getDataaa);
//                    echo $file_path;
                    $mailText='You have '.count($getDataaa).' data for Audit Plan';
                }
//                echo "Sending '{$report['report_type']}' to {$report['user_email']} \n";
                $get_user_details = $this->get_user_details($report['user_id']);

//                $content = "Hello " . $report['user_name'] . ",\n\nYour report is ready.";

                $content = '<div style=\'max-width: 100%; margin: 30px auto; border: 1px solid lightgray; padding: 20px; border-radius: 10px; \'>
                            <h2>Dear ' . $report['user_name'] . ',</h2>
                            <p>Your report is ready.</p>
                            <p>'.$mailText.'.</p>
                            <p>Have a great day. <br/><br/></p>


                            <hr style="background-color: lightgray; border: none; height: 1px; margin: 5px 0 9px;" />
                            <p>This email may contain confidential information for ' . $get_user_details['org_name'] . '.<br>Do not forward to unrelated parties.</p>

                            </div>';
            $to = strtolower(trim($report['user_email']));
//                $to = 'gargiiwi@gmail.com';
//                 "Sending '{$report['report_type']}' to {$report['user_email']} \n";
                $subject = $report['report_type'] . " Report";

                $custom_file_name = str_replace(' ', '_', $report['report_type']) . '.xls';
                $r = $this->m_comm->sendReportCronMail($to, $report['user_name'], $content, $subject, $file_path, $custom_file_name); 
                
                $r2 = $this->m_comm->sendReportCronMail('gargiiwi@gmail.com', $report['user_name'], $content, $subject.$report['user_name'], $file_path, $custom_file_name); 
                $mailSendingLogData = array(
                    'rcm_id' => $report['rcm_id'],
                    'user_id' => $report['user_id'],
                    'file_path' => $file_path,
                    'sending_date_time' => $todayDate,
                    'sending_report' => $r['sent'],
                    'mail_details' => json_encode($r),
                    'details' => json_encode(array('mail_report' => $r['sent'], 'to_mail_id' => $to, 'body' => $content)),
                );

                $this->org->add_read_sign_mail_log($mailSendingLogData);
            }
        }
    }

    public function get_user_details($user_id) {
        $q = QueryFactory::select()
                ->table(array('users', 'users'), 'id,publish_all_linked_department,cdep_id,name,email,org_id')
                ->table(array('organizations', 'organizations'), array('org_name' => 'name'), 'Left', 'organizations.id = users.org_id')
                ->where("`users`.id = ?", $user_id);
        $r = MVC_Core::$db->query($q);
        $all = $r->current();
        return $all;
    }

    public function action_Load_Advance_External_Training_Record_Listing($org_id, $filters) {
        $person_name = (isset($filters['trainee_name'])) ? $filters['trainee_name'] : '';
        $training_name = (isset($filters['training_name'])) ? $filters['training_name'] : '';
        $training_from = (isset($filters['expiry_from_date'])) ? $filters['expiry_from_date'] : '';
        $training_to = (isset($filters['expiry_to_date'])) ? $filters['expiry_to_date'] : '';
        $training_result = (isset($filters['training_result'])) ? $filters['training_result'] : '';
        $trainee_type = (isset($filters['users_type'])) ? $filters['users_type'] : '';
        $user_issue_id = (isset($filters['user_issue_id'])) ? $filters['user_issue_id'] : '';

        $havingCondition = '';
        if($person_name !=''){
            $havingCondition .= " AND user_issue_id =  $user_issue_id ";//19122024 training expiry report changes by iwi
            // $havingCondition .= "AND trainee_name =  '$person_name' ";
        }
        if($training_name !=''){
            $havingCondition .= "AND training_name =  '$training_name' ";
        }
        
        if($training_from !='' && $training_to !=''){
            $havingCondition .= "AND (expiry_date_value >=  '$training_from') AND  (expiry_date_value <=  '$training_to') ";
        }
        if($training_to !='' && $training_from ==''){
            $havingCondition .= "AND (expiry_date_value <=  '$training_to')";
        }
        if($training_from !='' && $training_to ==''){
            $havingCondition .= "AND (expiry_date_value >=  '$training_from')";
        }
        if($training_result !='' ){
            $havingCondition .= "AND result = '$training_result'";
        }
        // if($trainee_type !=''){
        //     $havingCondition .= "AND (trainee_type = '$trainee_type' OR trainee_type = '') ";
        // }
        if($trainee_type == '0'){
            $havingCondition .= "AND trainee_type = '$trainee_type' ";
        }
        if($trainee_type == '1'){
            $havingCondition .= "AND (trainee_type = '$trainee_type' OR trainee_type = '') ";
        }

        // 13112024 changes training record
		if($training_result == 3 && $trainee_type !=''){
			if($trainee_type =='1'){
				$trainingRecordListingSqlQuery = $this->record->getNoTrainingRecord($org_id);
			}			

			foreach($trainingRecordListingSqlQuery as $row) {
                if($user_issue_id > 0){ //search only user id base no trainee then set user name
                    $row['trainee_name'] = $person_name;
                }
                $trainee_name = $row['trainee_name'] !=' ' ?  $row['trainee_name'] : '---';
                if($row['track'] != '' || $row['track'] != null){
                    $cache_primary_name = '<span style="cursor: pointer;"  data-url="/record/process:'.$row['track'].'#panels" class="goUserDetailPnl" >'.$trainee_name.'</span>';
                }else{
                    $cache_primary_name = $trainee_name ;
                }
                $trainee_name = $row['training_name'] !=' ' ?  $row['training_name'] : '';
                $traineeManagementData[] = array(
                    'trainee_name'=>'',
                    'cache_primary_name'=>$cache_primary_name,
                    'training_name'=>$trainee_name,
                    'training_result'=>'',
                    'trainee_type'=>'',
                    'training_date'=>'',
                    'expiry_date_value'=>'',
                );
            }
            if(empty($traineeManagementData)){
                $traineeManagementData[] = array(
                    'cache_primary_name'=>'Data Not Found',
                    'training_name'=>'',
                    'training_result'=>'',
                    'trainee_type'=>'',
                    'training_date'=>'',
                    'expiry_date_value'=>'',
                );
            }
            $this->send_JSON(array(
                'success' => true,
                'sql'=>'',
                'traineeManagementData'=>$traineeManagementData
            ));
            exit;
		}
		// 13112024 changes training record

        //03102024  notrainee filter add
        if($training_result == 3 && $trainee_type ==''){// 13112024 changes training record
            if($training_name !=''){
                $trainingRecordListingSqlQuery = $this->record->getNoTrainingByTraineeRecord($org_id,$training_name,$person_name);
            }
            if($training_name ==''){
                $trainingRecordListingSqlQuery = $this->record->getNoTrainingRecord($org_id);
            }
            if($user_issue_id > 0 && $training_name ==''){
                $trainingRecordListingSqlQuery = $this->record->getNoTrainingByUserIdRecord($org_id,$user_issue_id);
               
            }

            if($training_to !='' || $training_from !='' || $trainee_type !=''){
                $trainingRecordListingSqlQuery = array();
            }
            
            foreach($trainingRecordListingSqlQuery as $row) {
                if($user_issue_id > 0){ //search only user id base no trainee then set user name
                    $row['trainee_name'] = $person_name;
                }

                $trainee_name = $row['trainee_name'] !=' ' ?  $row['trainee_name'] : '---';
                if($row['track'] != '' || $row['track'] != null){
                    $cache_primary_name = '<span style="cursor: pointer;"  data-url="/record/process:'.$row['track'].'#panels" class="goUserDetailPnl" >'.$trainee_name.'</span>';
                }else{
                    $cache_primary_name = $trainee_name ;
                }
                $trainee_name = $row['training_name'] !=' ' ?  $row['training_name'] : '';
                
                
                

                $traineeManagementData[] = array(
                    'trainee_name'=>'',
                    'cache_primary_name'=>$cache_primary_name,
                    'training_name'=>$trainee_name,
                    'training_result'=>'',
                    'trainee_type'=>'',
                    'training_date'=>'',
                    'expiry_date_value'=>'',
                );
            }

            if(empty($traineeManagementData)){
                $traineeManagementData[] = array(
                    'cache_primary_name'=>'Data Not Found',
                    'training_name'=>'',
                    'training_result'=>'',
                    'trainee_type'=>'',
                    'training_date'=>'',
                    'expiry_date_value'=>'',
                );
            }
            $this->send_JSON(array(
                'success' => true,
                'sql'=>'',
                'traineeManagementData'=>$traineeManagementData
            ));
            exit;
        }
        // no training sectin work 03102024 
           
        

         $traineeManagementData = array();
         $trainingRecordListingSqlQuery = $this->record->get_training_expiry_advance_report($org_id,$havingCondition);
         // 19122024 query changes by iwi
         $trainingRecordIds = array();
         foreach($trainingRecordListingSqlQuery as $row) {
                // 19122024 query changes by iwi
                if($person_name !=''){
                    if (in_array($row['tr_training_name_id'], $trainingRecordIds)) {// 19122024 query changes by iwi
                        continue;
                    }else{
                        $trainingRecordIds[] = $row['tr_training_name_id'];
                    }
                }
                // if($row['track_id'] ==''){continue;}
                // 19122024 query changes by iwi

                if($row['isExpiry'] == 1 && ($row['expiry_date_value_nw'] == "0000-00-00" || $row['expiry_date_value_nw'] == NULL)){ // 19122024 query changes by iwi
                    continue;
                }

                if($row['isExpiry'] == 0){
                   $row['expiry_date_value_nw'] ='No Expiry';// 19122024 query changes by iwi
                }



                if($row['track'] != '' || $row['track'] != null){
                    $cache_primary_name = '<span style="cursor: pointer;"  data-url="/record/process:'.$row['track'].'#panels" class="goUserDetailPnl" >'.$row['trainee_name'].'  </span>';
                }else{
                    $cache_primary_name = $row['trainee_name'];
                }
                
                if($row['result'] == 1){
                    $training_result = 'Pass';
                }else if($row['result'] == 0){
                    $training_result = 'Fail';
                }else {
                    $training_result = 'Pending';
                }


                $training_date ='';
                if($row['training_date'] !='0000-00-00' && $row['training_date'] != NULL){
                    $training_date = $row['training_date'];//date("Y-m-d",strtotime());
                }
                
                $training_name = $row['training_name'];

                if($row['trainee_type'] == '1'){
                    $_trainee_type = 'Internal';
                }else if($row['trainee_type'] == '0'){
                    $_trainee_type = 'External';
                }else{
                    $_trainee_type = '';
                }

                $traineeManagementData[] = array(
                    'trainee_name'=>$row['trainee_name'],
                    'cache_primary_name'=>$cache_primary_name,
                    'training_name'=>$training_name,
                    'training_result'=>$training_result,
                    'trainee_type'=>$_trainee_type,
                    'training_date'=>$training_date,
                    'expiry_date_value'=>$row['expiry_date_value_nw'],// 19122024 query changes by iwi
                    'user_issue_id'=>$row['user_issue_id'],// 19122024 query changes by iwi
                );
         }

        $trainingExternalUserRecordData = array();
        if($trainee_type !='1'){
            $trainingExternalUserRecordData = $this->record->get_training_expiry_external_users_report($org_id,$havingCondition);//,$havingCondition
        }
        
        foreach($trainingExternalUserRecordData as $row) {
            if($row['expiry_date_value'] == "0000-00-00" || $row['expiry_date_value'] == NULL){ 
                continue;
            }
            if($row['track'] != '' || $row['track'] != null){
                $cache_primary_name = '<span style="cursor: pointer;"  data-url="/record/process:'.$row['track'].'#panels" class="goUserDetailPnl" >'.$row['trainee_name'].'  </span>';
            }else{
                $cache_primary_name = $row['trainee_name'];
            }
            
            if($row['result'] == 1){
                $training_result = 'Pass';
            }else if($row['result'] == 0){
                $training_result = 'Fail';
            }else {
                $training_result = 'Pending';
            }

            $trainee_type = '';
            if($row['trainee_type'] == '0'){
                $trainee_type = 'External';
            }

            $training_date ='';
            if($row['training_date'] !='0000-00-00' && $row['training_date'] != NULL){
                $training_date = $row['training_date'];
            }
           
            $traineeManagementData[] = array(
                'trainee_name'=>$row['trainee_name'],
                'cache_primary_name'=>$cache_primary_name,
                'training_name'=>$training_name,
                'training_result'=>$training_result,
                'trainee_type'=>$trainee_type,
                'training_date'=>$training_date,
                'expiry_date_value'=>$row['expiry_date_value'],
                'user_issue_id'=>$row['user_issue_id'],// 19122024 | 709 query changes by iwi 
            );
        }

        if(empty($traineeManagementData)){
            $traineeManagementData[] = array(
                'cache_primary_name'=>'Data Not Found',
                'training_name'=>'',
                'training_result'=>'',
                'trainee_type'=>'',
                'training_date'=>'',
                'expiry_date_value'=>'',
            );
        }

        return $traineeManagementData;
    }
    public function action_Load_Training_Record_Listing($org_id, $filters){
        $org_id = $org_id;
        $getAllTrainingNamegSql = "SELECT cd FROM `issue_xfdef` WHERE `id` = '2116'";
        $getAllTrainingNamegSqlData = array();
        $getAllTrainingNamegSqlQuery = MVC_Core::$db->query($getAllTrainingNamegSql);
        if(!$getAllTrainingNamegSqlQuery)
        return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
        $getTrainingName = '';
        foreach($getAllTrainingNamegSqlQuery as $row) {
                $getTrainingName = $row['cd'];
        }
        $_getTrainingName = str_replace('","',",",$getTrainingName);
        
     $post= (object) $filters;
        $days = (isset($post->days)) ? $post->days : 30;

        // 23092024 Training Expiry Report filter
        $person_name = (isset($post->person_name)) ? $post->person_name : '';
        $training_name = (isset($post->training_name)) ? $post->training_name : '';
        $training_from = (isset($post->training_from)) ? $post->training_from : '';
        $training_to = (isset($post->training_to)) ? $post->training_to : '';
        $havingCondition = '';
        if($person_name !=''){
            $havingCondition .= "AND trainee_name =  '$person_name' ";
        }
        if($training_name !=''){
            $havingCondition .= "AND training_name =  '$training_name' ";
        }

        if($training_from !='' && $training_to !=''){
            $havingCondition .= "AND (expiry_date_value >=  '$training_from') AND  (expiry_date_value <=  '$training_to') ";
        }
        if($training_to !='' && $training_from ==''){
            $havingCondition .= "AND (expiry_date_value <=  '$training_to')";
        }
        if($training_from !='' && $training_to ==''){
            $havingCondition .= "AND (expiry_date_value >=  '$training_from')";
        }
            // 23092024 Training Expiry Report filter
        // expiry_date_value < CURRENT_DATE()  OR
        $havingFilter = "HAVING  (trainee_type = 1 OR trainee_type is NUll) AND result = 1 AND expiry_date_value !='' AND ( expiry_date_value < DATE_ADD(CURRENT_DATE(),INTERVAL $days DAY))";
        if($days == 1){
            $havingFilter  = "HAVING  (trainee_type = 1 OR trainee_type is NUll) AND result = 1 ";
        }elseif($days == 0){
            $havingFilter = "HAVING (trainee_type = 1 OR trainee_type is NUll) AND result = 1 AND expiry_date_value !='' AND expiry_date_value < CURRENT_DATE()";
        }
        
           $trainingRecordListingSql = "SELECT 
        tm.trainee_type,tmmu.isDisableTraining,
        (case when tm.name != '' then tm.name else CONCAT(tr_issue_fields_2.value,' ',tr_issue_fields.value) end) as 'trainee_name', 
        (case when tr_track_id != '' then tr_track_id else urs_iss.track end) as 'track', 
        (case when tm.result != '' then tm.result else tmmu.tr_training_result end) as 'result', 
        tm.track_id,tm.record_id, tmmu.tr_training_expiry_date, iof.int_value as expiry_date, 
        (case when iof_5.value != '' then iof_5.value else tmmu.tr_training_name end) as 'training_name', 
        `iss`.`id`, tm.training_date,tmmu.tr_training_start_date, 
        tmmu.tr_training_expiry_date as expiry_date_value
        FROM `trainee_management_maping_user` as tmmu 
        LEFT JOIN `trainee_management` AS tm ON( `tm`.`id` = `tmmu`.`trainee_management_id` AND tm.trainee_type = 1)
        LEFT JOIN `issues` AS iss ON (iss.track = tm.track_id)
        LEFT JOIN `issues` AS iss_tr ON (iss_tr.id = tmmu.user_issue_id) 
        LEFT JOIN `issue_orgdata` AS iod  ON (`iss`.id = `iod`.issue_id AND `iod`.org_id = `iss`.master_org_id) 

        LEFT JOIN `issues` AS iss_tmmu ON (CASE WHEN tm.track_id != '' then tm.track_id else tr_track_id end) = iss_tmmu.track 
        LEFT JOIN `issue_orgdata` AS iod_tmmu ON (iss_tmmu.id = iod_tmmu.issue_id ) 


        LEFT JOIN `issue_fields` AS iof ON (`iof`.issue_id = `iss`.id AND `iof`.fk_id = 3635) 
        LEFT JOIN `issue_fields` AS iof_5 ON (`iof_5`.issue_id = `iss`.id  AND `iof_5`.fk_id = 3630) 

        LEFT JOIN `issues` AS tr_issue ON ( `tr_issue`.track = `tmmu`.tr_track_id) 
        LEFT JOIN `issue_fields` AS tr_issue_fields ON ( `tr_issue_fields`.issue_id = tr_issue.id AND `tr_issue_fields`.fk_id = 2085) 
        LEFT JOIN `issue_fields` AS tr_issue_fields_2 ON ( `tr_issue_fields_2`.issue_id = tr_issue.id AND `tr_issue_fields_2`.fk_id = 2096) 

        LEFT JOIN `issues` AS urs_iss ON (tmmu.user_issue_id = urs_iss.id) 

        WHERE tmmu.org_id = $org_id AND isDisableTraining = 0 AND (iod_tmmu.process NOT IN('closed', 'hidden'))  $havingFilter $havingCondition
        ORDER BY trainee_name ASC ";//17122024 Training Record add checkbox  by iwi
        
        
//       echo $trainingRecordListingSql;exit(); 
        $trainingRecordListingSqlData = array();
        $trainingRecordListingSqlQuery = MVC_Core::$db->query($trainingRecordListingSql);
        if(!$trainingRecordListingSqlQuery)
        return MVC_Core::$debug-> dump("Statistics query Failed", __METHOD__, null, "error");
        $traineeManagementHtml = '';
        
        $traineeManagementData = array(); //  23092024 Training Expiry Report filter add 

        foreach($trainingRecordListingSqlQuery as $row) {
            if($row['expiry_date_value'] == "0000-00-00" || $row['expiry_date_value'] == NULL){ continue; }
            //if($row['track_id'] == '' ){ continue; }//19122024 query changes by iwi
            if($row['track'] != '' || $row['track'] != null){
                $cache_primary_name = '<span style="cursor: pointer;"  data-url="/record/process:'.$row['track'].'#panels" class="goUserDetailPnl" >'.$row['trainee_name'].'</span>';
            }else{
                $cache_primary_name = $row['trainee_name'];
            }

            
                $training_name = $row['training_name'];
                $pattern = "/#$training_name:\s*([^,]+)/";
                if (preg_match($pattern, $_getTrainingName, $matches)) {
                    $training_name = $matches[1];
                }

            
            $training_result = '';
            if($row['result'] == 1 || $row['tr_training_result'] == 1){
                    $training_result = 'Pass';
            }else{
                continue;
            }

            if($row['tr_training_start_date'] !='0000-00-00' && $row['tr_training_start_date'] != NULL){
                $training_date = date("Y-m-d",strtotime($row['tr_training_start_date']));
            }else{
                $training_date = ($row['training_date'] !='' && $row['training_date'] !='0000-00-00') ? date("Y-m-d",strtotime($row['training_date'])) : '';
            }
            //  23092024 Training Expiry Report filter add 
            // 19122024 query changes by iwi
            $expiry_date_value = $row['expiry_date_value'];
            if(isset($row['isExpiry']) && $row['isExpiry'] == 0){ // IWI Expiry date error solve 19082025 
                $expiry_date_value = '';
            }
            $traineeManagementData[] = array(
                'cache_primary_name'=>$cache_primary_name,
                'traineeManagementData'=>$row['trainee_name'],
                'training_name'=>$training_name,
                'training_result'=>$training_result,
                'training_date'=>$training_date,
                'expiry_date_value'=>$expiry_date_value,// 19122024 query changes by iwi
            );

        }

        return $traineeManagementData;
    }

    public function action_CreateReportDataForCron($traineeManagementData) {
        $labels = array(
            'trainee_name' => 'Trainee Name',
            'cache_primary_name' => 'Cache Primary Name',
            'training_name' => 'Training Name',
            'training_result' => 'Training Result',
            'trainee_type' => 'Trainee Type',
            'training_date' => 'Training Date',
            'expiry_date_value' => 'Expiry Date',
        );

        $saveDir = $_SERVER['DOCUMENT_ROOT'] . '/report_corn_files';
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0777, true);
        }

        $fileName = 'trainee_report_' . date('Ymd_His') . '.xls';
        $filePath = $saveDir . '/' . $fileName;

// Start HTML table
        $html = '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;">';
        $html .= '<tr style="background-color:#CCCCCC; font-weight:bold;">';
        foreach ($labels as $header) {
            $html .= '<th>' . $header . '</th>';
        }
        $html .= '</tr>';

// Fill data
        $rowCount = 0;
        foreach ($traineeManagementData as $row) {
            $rowColor = ($rowCount % 2 == 0) ? '#FFFFFF' : '#EEEEEE';
            $html .= '<tr style="background-color:' . $rowColor . ';">';
            foreach (array_keys($labels) as $key) {
                $value = isset($row[$key]) ? $row[$key] : '';
                if (strpos($key, 'date') !== false && !empty($value)) {
                    $value = date('d-m-Y', strtotime($value));
                }
                $html .= '<td>' . $value . '</td>';
            }
            $html .= '</tr>';
            $rowCount++;
        }

        $html .= '</table>';

// Save file
        file_put_contents($filePath, $html);

        return $filePath;
    }

    public function action_Load_Audit_Plan_Listing($org_id, $filters) {
        $interval = intval($filters['audit_scheduled_in']); // ensure it's an integer
        $havingCondition = "AND audit_plan.start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL $interval DAY)";
        $audit_plan_result = $this->record->get_audit_plan_result($org_id, $havingCondition);
        $audit_plan_data = array();
        foreach ($audit_plan_result as $val) {
            $audit_plan_data[] = $val;
        }

        return $audit_plan_data;
    }

    public function action_CreateAuditPlanDataForCron($auditPlanData) {
        $saveDir = $_SERVER['DOCUMENT_ROOT'] . '/report_corn_files';
        if (!is_dir($saveDir))
            mkdir($saveDir, 0777, true);

        $fileName = 'audit_report_' . date('Ymd_His') . '.xls';
        $filePath = $saveDir . '/' . $fileName;
        // Start HTML table
        $html = '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;">';
        $html .= '<tr style="background-color:#CCCCCC; font-weight:bold;">';
        $headers = array('Title', 'Type', 'Area', 'Start Date', 'End Date', 'Period');
        foreach ($headers as $header) {
            $html .= '<th>' . $header . '</th>';
        }
        $html .= '</tr>';

        // Fill data
        $rowCount = 0;
        foreach ($auditPlanData as $row) {
            $rowColor = ($rowCount % 2 == 0) ? '#FFFFFF' : '#EEEEEE'; // alternate row color
            $html .= '<tr style="background-color:' . $rowColor . ';">';
            $html .= '<td>' . $row['title'] . '</td>';
            $html .= '<td>' . $row['audit_type'] . '</td>';
            $html .= '<td>' . $row['area'] . '</td>';
            $html .= '<td>' . date('d-m-Y', strtotime($row['start_date'])) . '</td>';
            $html .= '<td>' . date('d-m-Y', strtotime($row['end_date'])) . '</td>';
            $html .= '<td style="text-align:center;">' . $row['schedule_type'] . '</td>';
            $html .= '</tr>';
            $rowCount++;
        }

        $html .= '</table>';

        // Save file
        file_put_contents($filePath, $html);

        return $filePath;
    }

//    IWI report cron settings for admin end

}
