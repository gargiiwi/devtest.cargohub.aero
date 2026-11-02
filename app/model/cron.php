<?php

/**
 * "Cron API " Related functions - Allows accessing the system through HTTP interface

 */
class App_Model_Cron extends MVC_Model {

    private $dateFrom;
    private $scheduleDate;
    private static $allowed_actions = array(
        'Execute' // Runs a predefined command which is stored in "webapi_commands"
    );

    public function getUserDetails() {
        $q = QueryFactory::select()->table(array(MVC_Core::$db->tab['usr'], 'usr'), 'id,status,cdep_id,org_id')->where("`usr`.status != ?", 'disabled')->where("`usr`.status != ?", 'invisible');
        $r = MVC_Core::$db->query($q);
        return $r; 
    } 
  
    public function getAllSignedDoc($user_id) { 
        $q = QueryFactory::select()
                ->table(array('user_read_sign_note', 'ursn'), '*') 
                ->where("`ursn`.user_id = ?", $user_id); 
        $r = MVC_Core::$db->query($q); 
        $signedNoteId = array();  
        foreach ($r as $data) { 
            $signedNoteId[] = $data['note_id'];
        }
        return $signedNoteId;
    }

    public function getAllPendingReadSign($user_id, $dep_id,$user_org_id, $getAllSignedDoc) {
        $process_not_in = array('pending', 'webapi', 'shared', 'closed', 'hidden');
        $q = QueryFactory::select()
                ->table(array(MVC_Core::$db->tab['nte'], 'nte'), 'id,wv_key,title')
                ->table(array(MVC_Core::$db->tab['iod'], 'iod'), 'process', 'Left', 'iod.issue_id = nte.link_id')
                ->table(array(MVC_Core::$db->tab['iss'], 'iss'), array('track'), 'Left', 'iss.id = nte.link_id')
                ->table(array(MVC_Core::$db->tab['usr'], 'usr'), array('published_id' => 'id', 'published_name' => 'name'), 'Left', 'usr.id = nte.published_by')
                ->where("`nte`.wv_publish = ?", '1')
                ->where("`nte`.wv_publish_for = ?", 'department')
                ->where("`nte`.visibility = ?", 'org')
                ->where("`nte`.is_notification = ?", '0') // IWI reminder not send for notification
                ->where("`nte`.org_id = ?", $user_org_id) // IWI reminder  send for same org
                ->where("`nte`.id NOT IN (?)", $getAllSignedDoc)
                ->where('`nte`.wv_publish_for_ids  like "%' . $dep_id . '%"')
                ->where("`iod`.process NOT IN (?)", $process_not_in);
//        echo $q;
        
        $r = MVC_Core::$db->query($q);
        return $r;
    }
    public function getCronReminderSentLastDate($user_ids) {
        
        $q = QueryFactory::select()
                ->table(array('user_read_sign_reminder_log', 'ursrl'),'id,user_id,created_at')
                ->where('`ursrl`.reminder_type  = ?' ,'cron')
                ->where("`ursrl`.user_id IN (?)", $user_ids)
                ->order('id','DESC');
//        echo $q;
        $r = MVC_Core::$db->query($q);
        return $r;
    }

    
    public function sendReadSignReminderMail($needToSendUserIds,$needToSendReminderData) {
        $baseUrl='https://cargohub.aero';
        
        foreach ($needToSendUserIds as $key => $user_id) {
            $get_user_details = $this->get_user_details($user_id);
//             $msg = "<div style='max-width: 750px;  padding: 20px; border-radius: 10px; '>
//    <h2>Dear " . $get_user_details['name'] . ",</h2>
//    <p data-type='cron' style=\"\">This message was sent as the reminder that you have " . count($needToSendReminderData[$user_id]) . " pending publication(s) to <b>read and sign </b>. </p>
//    <p  style=\"\">We kindly request you to read and sign the publications for your interest.</p>
//    <p  style=\"\">Please <a href='" . $baseUrl . "/user/publication?action=get_all_read_sign' >click here</a> to see all the open publications listed in your department overview. </p>
//    <p  style=\"\">Have a great day.</p>
//     <hr style=\"background-color: lightgray; border: none; height: 1px; margin: 40px 0 10px;\" />
//            <p  style=\"\">This email may contain confidential information for ".$get_user_details['org_name'] .". Do not forward to unrelated parties.</p>
//                 <p  style=\"\">Powered by CARGOHUB  | email: support@cargohub.nl </p>
//    </div>";
             
             
              $msg='<div style=\'max-width: 750px; margin: 30px auto; border: 1px solid lightgray; padding: 20px; border-radius: 10px; \'>
<h2>Dear '. $get_user_details['name'] . ',</h2>
<p>This message was sent as the reminder that you have ' . count($needToSendReminderData[$user_id]) . ' pending publication(s) to <b>read and sign </b></p>

<p>We kindly request you to read and sign the publications for your interest.</p>
<p>Please <a href="' . $baseUrl . '/user/publication?action=get_all_read_sign"> click here</a> to see all the open publications listed in your department overview.</p>
<p>Have a great day. <br/><br/></p>
        

<hr style="background-color: lightgray; border: none; height: 1px; margin: 5px 0 9px;" />
<p>This email may contain confidential information for ' . $get_user_details['org_name'] . '.<br>Do not forward to unrelated parties.</p>

</div>';
             
            
            
//            echo $msg;exit();
            $m_comm = new App_Model_Comm();
            $content = $msg; 
            $subject = "Read and sign reminder";
//            $to = 'gargiiwi@gmail.com'; 
            $to = $get_user_details['email'];
//            echo $content; exit();
            
              $r = $m_comm->send_cron_email($to, $get_user_details['name'], $content, $subject); // true = standalone message, no conversation.
              
//            exit();
            $mailSendingLogData = array(
                'user_id' => $user_id,
//                'doc_id' => $allData['id'],
                'reminder_type' => 'cron',
                'sending_report' => $r['sent'],
                'mail_details' => json_encode($r),
                'details' => json_encode(array('mail_report'=>$r['sent'],'to_mail_id'=>$to,'body'=>$msg)),
            );

           // $this->submit_read_sign_mail_log($mailSendingLogData);
//            exit();
        }
    }

    public function get_user_details($user_id) {
        $q = QueryFactory::select()
                ->table(array('users', 'users'), 'id,publish_all_linked_department,cdep_id,name,email,org_id')
                ->table(array('organizations','organizations'), array('org_name' => 'name'), 'Left', 'organizations.id = users.org_id')
                ->where("`users`.id = ?", $user_id);
        $r = MVC_Core::$db->query($q);
        $all = $r->current();
        return $all;
    }

    public function submit_read_sign_mail_log($data) {
        $q = QueryFactory::insert()
                ->table('user_read_sign_reminder_log')
                ->values($data);
        MVC_Core::$db->query($q);
    }
    
    public function sendTestMail($mail_id) {
         $baseUrl='https://cargohub.aero';
          $get_user_details = $this->get_user_details('1');
//          print_r($get_user_details);exit();
//       $msg = "<div style='max-width: 750px;  padding: 20px; border-radius: 10px; '>
//    <h2>Dear " . $get_user_details['name'] . ",</h2>
//    <p data-type='cron' style=\"\">This message was sent as the reminder that you have 0 pending publication(s) to <b>read and sign </b>. </p>
//    <p  style=\"\">We kindly request you to read and sign the publications for your interest.</p>
//    <p  style=\"\">Please <a href='" . $baseUrl . "/user/publication?action=get_all_read_sign' >click here</a> to see all the open publications listed in your department overview. </p>
//    <p  style=\"\">Have a great day.</p>
//     <hr style=\"background-color: lightgray; border: none; height: 1px; margin: 40px 0 10px;\" />
//            <p  style=\"\">This email may contain confidential information for ".$get_user_details['org_name'] .". Do not forward to unrelated parties.</p>
//                 <p  style=\"\">Powered by CARGOHUB  | email: support@cargohub.nl </p>
//    </div>";
          
          $get_user_details['name']='Test User';
        $get_user_details['org_name'] ='Cargohub';
        
       $msg='<div style=\'max-width: 750px; margin: 30px auto; border: 1px solid lightgray; padding: 20px; border-radius: 10px; \'>
<h2>Dear '. $get_user_details['name'] . ',</h2>
<p>This message was sent as the reminder that you have ' . count($needToSendReminderData[$user_id]) . ' pending publication(s) to <b>read and sign </b></p>

<p>We kindly request you to read and sign the publications for your interest.</p>
<p>Please <a href="' . $baseUrl . '/user/publication?action=get_all_read_sign"> click here</a> to see all the open publications listed in your department overview.</p>
<p>Have a great day. <br/><br/></p>
        

<hr style="background-color: lightgray; border: none; height: 1px; margin: 5px 0 9px;" />
<p>This email may contain confidential information for ' . $get_user_details['org_name'] . '.<br>Do not forward to unrelated parties.</p>

</div>';
       
//       echo $msg;exit();
            $m_comm = new App_Model_Comm();
            $content = $msg;
            $subject = "Read and sign reminder";
            $to = $mail_id; 
            $r = $m_comm->send_cron_email($to, 'Test User', $content, $subject); // true = standalone message, no conversation.
            
//            print_r($r);
    }
    
    
//    IWI Refresh after days start
    public function getAllNoteNeedToRefresh() {
        $q = QueryFactory::select()
                ->table(array(MVC_Core::$db->tab['nte'], 'nte'), 'id,dt,update_dt,is_required_read_sign,refresh_after_days')
                ->table(array('note_refresh_log', 'nrl'), 'last_refresh_date', 'LEFT', '`nrl`.note_id=`nte`.id')
                ->where("`nte`.visibility = ?", 'org')
                ->where("`nte`.is_required_read_sign = ?", '1')
                ->where("`nte`.refresh_after_days > ?", 0)
                ->where("`nte`.wv_publish_for = ?", 'department');
        $r = MVC_Core::$db->query($q);
        return $r;
    }

    public function updateReadSignNeedRefresh($note_id) {
        $check_q = QueryFactory::select()
                ->table('user_read_sign_note', 'id')
                ->where("`note_id` = ?", $note_id);

        $check_r = MVC_Core::$db->query($check_q);
        $c_row = $check_r->current();
        if (count($c_row) > 0) {
            $q = QueryFactory::update()
                    ->table('user_read_sign_note')
                    ->where("`note_id` = ?", $note_id)
                    ->values(array('is_need_refresh' => '1'))
                    ->limit(1);
            $r = MVC_Core::$db->query($q);
        }
    }

    public function updateRefreshLog($note_id) {
        $check_q = QueryFactory::select()
                ->table('note_refresh_log', 'id')
                ->where("`note_id` = ?", $note_id);
        $check_r = MVC_Core::$db->query($check_q);
        $c_row = $check_r->current();
        if (count($c_row) > 0) {
            $q = QueryFactory::update()
                    ->table('note_refresh_log')
                    ->where("`note_id` = ?", $note_id)
                    ->values(array('last_refresh_date' => date('Y-m-d H:i:s')))
                    ->limit(1);
            MVC_Core::$db->query($q);
        } else {
            $q = QueryFactory::insert()
                    ->table('note_refresh_log')
                    ->values(array('note_id' => $note_id, 'last_refresh_date' => date('Y-m-d H:i:s')));
            MVC_Core::$db->query($q);
        }
    }

//    IWI Refresh after days end
//     IWI Create section for Notification start
    public function getAllNotiNeedToArchive() {
        $q = QueryFactory::update()
                ->table(array(MVC_Core::$db->tab['nte'], 'nte'))
                ->where("`nte`.is_notification = ?", '1')
                ->where("`nte`.is_notification_archive = ?", '0')
                ->where("DATE_ADD(FROM_UNIXTIME(`nte`.dt), INTERVAL `nte`.notification_days DAY) <= NOW()")
                ->values(array('is_notification_archive' => '1'));
//        echo $q;exit();
        $r = MVC_Core::$db->query($q);
        return $r;
    }

//     IWI Create section for Notification end
    
    
    public function sendTestCheckMail($mail_id,$for_mail) {
        
        
       $msg='<div style=\'max-width: 750px; margin: 30px auto; border: 1px solid lightgray; padding: 20px; border-radius: 10px; \'>
<h2>Dear</h2>
<p>This test message is for '.$for_mail.' </p>

</div>';
       
//       echo $msg;exit();
            $m_comm = new App_Model_Comm();
            $content = $msg;
            $subject = "Test Check Mail";
            $to = $mail_id; 
            $r = $m_comm->send_cron_email($to, 'Test User', $content, $subject); // true = standalone message, no conversation.
            
//            print_r($r);
    }

}
