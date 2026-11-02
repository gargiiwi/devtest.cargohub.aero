<?php

/**
 * Communication Center - Conversation and E-Mails
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package Cargo ITS
 * @version 1.0.dev 
 */
class App_Ctrl_User_Publication extends App_Ctrl_Base {

    /**
     * Communication model, used extensively in this module
     * @var App_Model_Comm 
     */
    private $comm;

    /**
     * Conversation model, used in various places around the module
     * @var App_Model_Conversation
     */
    private $cnv;

    /**
     * Contacts model, also used all around this module
     * @var App_Model_Contact
     */
    private $con;

    /**
     * Labels model, part of the communication system
     * @var App_Model_Commlabel
     */
    private $lab;

    /**
     * Makes initializations useful for any action
     */
    public function __construct() {
        parent::__construct();

        // Communication related models
        $this->comm = $this->factory_Model("Comm");
        $this->user = $this->factory_Model("User", "user");
        $this->dep = $this->factory_Model("Department", "dep");
        $this->org = $this->factory_Model("Organization", "org");
        $this->note = $this->factory_Model("Note", "note");
        $this->record = $this->factory_Model('Issue', 'record');

        //        Add option to set or change logo from organization start
        $this->orgLogo = "";
        $getOrgDetails = $this->org->getOrgDetails(MVC_Core::$ss->user['org_id']);
        if (!empty($getOrgDetails['org_logo'])) {
            $this->orgLogo = $getOrgDetails['org_logo'];
        }
//            Add option to set or change logo from organization end
    }

    /**
     * Index action for the Users display and management page
     * @see MVC_Ctrl::action_Index()
     */
    public function action_Index(MVC_Request $in) {
        if (MVC_Core::$ss->user['status'] == 'readonly') {
            $this->readonlyPublication();
        } else {
            $this->get_publications();
        }
    }

    public function readonlyPublication(MVC_Request $in) {
        array_push($this->CFG['modules'], "user/_publication"); // for loading js page 
        $this->prepare_Header("Publication Center"); //       
        $get_user_details = $this->user->get_user_details(MVC_Core::$ss->user['id']);
        if (MVC_Core::$ss->user['status'] == 'admin') {
            $getDepartmentsByUserId = $this->dep->get_List(MVC_Core::$ss->user['org_id'], array());
        } else if ($get_user_details['publish_all_linked_department'] == '1') {
            $getDepartmentsByUserId = $this->user->get_publish_all_linked_department_ids_List(MVC_Core::$ss->user['id']);
        } else {
            $getDepartmentsByUserId = $this->user->getDepartmentsByUserId(MVC_Core::$ss->user['id']);
        }
        $assign_departments_list = '';
        $assign_departments_id_list = array(); // 28012025 read only user documentation add Announcement start
        foreach ($getDepartmentsByUserId as $d_row) {
            $selected = $get_user_details['cdep_id'] == $d_row['id'] ? 'selected' : '';
            $assign_departments_list .= '<option value="' . $d_row['id'] . '" ' . $selected . ' >' . $d_row['name'] . '</option>';
            $assign_departments_id_list[] = $d_row['id']; // 28012025 read only user documentation add Announcement start
        }
        $this->assign_departments_list = $assign_departments_list;

//        if (MVC_Core::$ss->user['org_id'] == '657') {
//            $this->module = array(
//                'orgLogo' => "logo-ait.webp"
//            );
//             $this->orgLogo="logo-ait.webp";
//        }
        // 28012025 read only user documentation add Announcement start
        $searchData = array(
            'tab_type' => 'department',
            'keyword' => 'by_name',
            'search_type' => 'by_desc',
            'search_by_department_list' => implode("','", $assign_departments_id_list),
        );
        $recent_records = $this->user->getPublishDocuments(MVC_Core::$ss->user['id'], "iss_recent", false, 1, true, $searchData);
        // echo "<pre>";
        // print_r($recent_records);
        // exit;
        $totalReadSign = 0;
        if (!empty($recent_records)) {
            foreach ($recent_records as $track => $data) {
                if ($data['is_required_read_sign'] == 1) {
                    continue;
                }
                $totalReadSign++;
            }
        }
        // 28012025 read only user documentation add Announcement end
        $this->read_sign_count = $this->Get_count_all_read_sign();
        // $this->read_sign_count=$totalReadSign;// 28012025 read only user documentation add Announcement
        $this->site_section = "user" . DS . "pubication" . DS . "pubications.html";
        $this->view("readonly_container.html");
    }

    public function action_get_Documentation_By_Search(MVC_Request $post) {
        $keyword = $_POST['keyword'];
        $tab_type = $_POST['tab_type'];
        $search_type = $_POST['search_type'];
        $search_by_department_list = $_POST['search_by_department_list'];
        $searchData = array(
            'tab_type' => $tab_type,
            'keyword' => $keyword,
            'search_type' => $search_type,
            'search_by_department_list' => $search_by_department_list,
        );
        $this->current_rt = MVC_Core::$cache->current_rt;

        $datasources = array();

//         IWI Get data in published tab as per publisher department access level start
        $get_user_details = $this->user->get_user_details(MVC_Core::$ss->user['id']);
//          print_r($get_user_details);
        if ($tab_type == 'fetch_all_published_by_org') {
            $publish_all_linked_department_ids = ($get_user_details['publish_all_linked_department_ids'] != '') ? json_decode($get_user_details['publish_all_linked_department_ids']) : array();
            $searchData['publish_all_linked_department_ids'] = $publish_all_linked_department_ids;
            $recent_records = $this->user->getPublishedDocByOrgDepAccess(MVC_Core::$ss->user['id'], MVC_Core::$ss->user['org_id'], $searchData);
        } else {

            $recent_records = $this->user->getPublishDocuments(MVC_Core::$ss->user['id'], "iss_recent", false, 1, true, $searchData);
        }


//           IWI Get data in published tab as per publisher department access level end



        $datasources['recent'] = array();
        if (!empty($recent_records)) {
            foreach ($recent_records as $track => $data) {
                // 07062024 created by iwi | start
                $data['publishedByName'] = '';
                if (!empty($data['published_by'])) {
                    $getPublishedByNameSql = "SELECT `name` FROM `users` WHERE `id` = " . $data['published_by'] . " ";
                    $getPublishedByNameResult = MVC_Core::$db->query($getPublishedByNameSql);
                    if (!$getPublishedByNameResult)
                        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
                    foreach ($getPublishedByNameResult as $row1) {
                        $data['publishedByName'] = $row1['name'];
                    }
                }

                // 07062024 created by iwi | end
                // IWI doc seen & sign  start
//                $getUserReadSignData = $this->note->getUserReadSignData(MVC_Core::$ss->user['id'], $data['id']);
//                if (count($getUserReadSignData) > 0) {
//                    $read_sign = $getUserReadSignData['read_sign'];
//                } else {
//                    $read_sign = '0';
//                }


                $getUserReadSignData = $this->note->getUserReadSignData(MVC_Core::$ss->user['id'], $data['id']);

                if (is_array($getUserReadSignData) && !empty($getUserReadSignData)) {
                    $read_sign = $getUserReadSignData['read_sign'] ?? '0';
                } else {
                    $read_sign = '0';
                }
                $data['read_sign'] = $read_sign;
                $data['logged_in_user'] = MVC_Core::$ss->user['id'];
                //                IWI doc seen & sign end
                //                IWI if any update in the doc section, then the update icon will show in publication list�start
                $read_sign_update = '0';
                if ($read_sign != '0') {
                    $signed_at = $getUserReadSignData['signed_at'];
                    if (trim($data['update_dt']) != '') {
                        $update_dt_time = strtotime($data['update_dt']);
                        $signed_at_time = strtotime($signed_at);
                        if ($update_dt_time > $signed_at_time) {
                            $read_sign_update = '1';
                        }
                    }
                }
                $data['read_sign_update'] = $read_sign_update;
                $data['is_need_refresh'] = (isset($getUserReadSignData['is_need_refresh']) && ($getUserReadSignData['is_need_refresh'] != '')) ? $getUserReadSignData['is_need_refresh'] : '0'; //IWI Refresh after days
//                IWI if any update in the doc section, then the update icon will show in publication list�end

                $rt_info = &MVC_Core::$cache->record_types[MVC_Core::$cache->record_types_index[$data['ist']]];
                $datasources['recent'][] = array_merge($data, array(
                    'track' => $track,
                    'rt_key' => $rt_info['key'],
                    'rt_name' => $rt_info['name']
                ));
            }
        }
        $this->recent_issues_count = count($recent_records);
        // Read the Mini-Notepad data
        $this->mini_notepad = htmlspecialchars($this->user->profile_Read(MVC_Core::$ss->user['id'], "mini_notepad"));
        // Read list of issues for the "Recent Issues" panel.
        $this->prepare_Template_ACLs();
        // User elements count
        $this->count = MVC_Core::$ss->user['count'];
        $datasources['user_type'] = MVC_Core::$ss->user['status'];
        $this->send_JSON($datasources);
    }

    public function get_publications() {

        array_push($this->CFG['modules'], "user/_get_publication"); // for loading js page 
        $this->prepare_Header("Publication Center"); //       
        $get_user_details = $this->user->get_user_details(MVC_Core::$ss->user['id']);

//        echo "<pre>";
//        print_r($get_user_details);


        $getDepartmentsByUserId = array();
        if (MVC_Core::$ss->user['status'] == 'admin') {
            $getDepartmentsByUserId = $this->dep->get_List(MVC_Core::$ss->user['org_id'], array());
        } else if ($get_user_details['publish_all_linked_department'] == '1') {
            $getDepartmentsByUserId = $this->user->get_publish_all_linked_department_ids_List(MVC_Core::$ss->user['id']);
        } else {
            $getDepartmentsByUserId = $this->user->getDepartmentsByUserId(MVC_Core::$ss->user['id']);
        }
        $assign_departments_list = '';
        foreach ($getDepartmentsByUserId as $d_row) {
            $selected = $get_user_details['cdep_id'] == $d_row['id'] ? 'selected' : '';
            $assign_departments_list .= '<option value="' . $d_row['id'] . '" ' . $selected . ' >' . $d_row['name'] . '</option>';
        }
        $this->assign_departments_list = $assign_departments_list;

        $this->publish_all_linked_department = (MVC_Core::$ss->user['status'] == 'admin') ? '1' : $get_user_details['publish_all_linked_department'];

        $this->read_sign_count = $this->Get_count_all_read_sign();

        $this->site_section = "user" . DS . "pubication" . DS . "get_pubications.html";
        $this->view("pubication_container.html");
    }

    public function action_Publication_user_list(MVC_Request $in) {
        array_push($this->CFG['modules'], "user/_publication_user_list"); // for loading js page 
        $this->prepare_Header("Publication Read & Sign"); //  
        $this->note_id = $in->id;
        $noteDetails = $this->note->read($in->id);
        $this->noteTitle = $noteDetails['title'];

        $this->site_section = "user" . DS . "pubication" . DS . "publication_user_list.html";
        $this->view("pubication_container.html");
    }

    public function action_get_publication_readSign_user_details() {
        $note_id = $_POST['note_id'];

        $get_publication_read_sign_user_list = $this->user->get_publication_read_sign_user_list($note_id);
        $getReadSignUserDetails = array();

        foreach ($get_publication_read_sign_user_list as $d_row) {
            $getReadSignUserDetails[$d_row['us_id']] = $d_row;
        }
//echo count($getReadSignUserDetails);
        $get_publication_user_list_by_noteid = $this->user->get_publication_user_list_by_noteid($note_id);
        $getPublicationUserListByNoteid = array();

        foreach ($get_publication_user_list_by_noteid as $u_row) {
            $getPublicationUserListByNoteid[$u_row['user_id']] = $u_row;
        }

        $readSignUserListData = array();
        $existData = array();
        foreach ($getReadSignUserDetails as $key => $signed) {
//            print_r($signed);
            $data['user'] = $signed['name'] . ' ( ' . $signed['email'] . ' )';
            $data['sign_in_date'] = $signed['signed_at'];
            $data['mail_to'] = '---';
            $readSignUserListData[$key] = $data;
            $existData[] = $key;
        }
        foreach ($getPublicationUserListByNoteid as $key => $not_signed) {
            if (!in_array($key, $existData)) {
                $data['user'] = $not_signed['name'] . ' ( ' . $not_signed['email'] . ' )';
                $data['sign_in_date'] = '---';
                $data['mail_to'] = "<a class='sendReminderMail' href='javascript:void(0)' data-user='" . $key . "'><img src='/img/icons/mail.png' ></a>";
                $readSignUserListData[$key] = $data;
            }
        }

//print_r($readSignUserListData);
        $datasources['readSignUserListData'] = $readSignUserListData;
        $datasources['user_type'] = MVC_Core::$ss->user['status'];
        $this->send_JSON($datasources);
    }

    public function action_send_reminder_mail_to_user(MVC_Request $in) {
        $baseUrl = 'https://cargohub.aero/';
//        print_r($_POST);
        $get_user_details = $this->user->get_user_details($_POST['user_id']);
        $noteDetails = $this->note->read($_POST['note_id']);
//        print_r($noteDetails);exit();
        if ($_POST['mail_for'] == 'user_sign_reminder') {
            $msg = "<div style='max-width: 750px; margin: 30px auto; border: 1px solid lightgray; padding: 20px; border-radius: 10px; background-color: #eee;'>
    <h2>Dear " . $get_user_details['name'] . ",</h2>
    <p>This message was sent as the reminder that you have a pending publication(s) to <b>read and sign </b>. </p>
    <p>We kindly request you to read and sign the publications for your interest.</p>
    <p >Please <a href='" . $baseUrl . "?action=document&qp=orptwp&key=" . $noteDetails['wv_key'] . "&nid=" . base64_encode($noteDetails['id']) . "' >click here</a> to see all the open publications listed in your department overview. </p>
    <p>Have a great day.</p>
    <p>Team</p>

            <hr style=\"background-color: lightgray; border: none; height: 1px; margin: 5px 0 9px;\" />
    <h4 style='text-align: center;'>Thank you for using Cargohub Connect !</h4>
    </div>";
//            echo $msg;
//            exit();

            $m_comm = new App_Model_Comm();
            $content = $msg;
            $subject = "Read and sign reminder";
//            $to = 'gargiiwi@gmail.com';
            $to = $get_user_details['email'];
            $r = $m_comm->send_custom_email($to, $mailData['name'], $content, $subject); // true = standalone message, no conversation.
//             IWI Create a log for read and sign reminder mail send start 
            $mailSendingLogData = array(
                'user_id' => $_POST['user_id'],
                'doc_id' => $noteDetails['id'],
                'reminder_type' => 'manual_single',
                'sending_report' => $r,
            );

            $this->user->submit_read_sign_mail_log($mailSendingLogData);
//            IWI Create a log for read and sign reminder mail send end 

            if ($r) {
                $this->evan->add(sprintf("Reminder mail has been sent <b>%s</b>. Please check your e-mail inbox !", $to), 'send');
                $this->send_JSON(array('success' => true, 'msg' => $r));
            } else {
                $this->evan->add(sprintf("Opps, please try again!", trim($to)), 'send');
                $this->send_JSON(array('success' => false, 'msg' => $r));
            }
        }
    }

//    IWI Create a button to bulk mail send start

    public function action_send_reminder_mail_to_all_user() {
        $baseUrl = 'https://cargohub.aero/';
//        print_r($_POST);

        if ($_POST['mail_for'] == 'send_reminder_mail_to_all_user') {

            $noteDetails = $this->note->read($_POST['note_id']);

            $get_publication_user_list_for_reminder_mail_by_noteid = $this->user->get_publication_user_list_for_reminder_mail_by_noteid($_POST['note_id']);

            $allConnectedUsers = array();
            foreach ($get_publication_user_list_for_reminder_mail_by_noteid as $u_row) {

                $allConnectedUsers[$u_row['user_id']] = $u_row;
            }
            $get_publication_read_sign_user_list = $this->user->get_publication_read_sign_user_list($_POST['note_id']);
            $getReadSignUserDetails = array();

            foreach ($get_publication_read_sign_user_list as $d_row) {
                $getReadSignUserDetails[$d_row['us_id']] = $d_row;
            }
            $needToSendMail = array_diff_key($allConnectedUsers, $getReadSignUserDetails);
// print_r($needToSendMail);
//exit();

            foreach ($needToSendMail as $key => $rowUser) {

                $msg = "<div style='max-width: 750px; margin: 30px auto; border: 1px solid lightgray; padding: 20px; border-radius: 10px; background-color: #eee;'>
    <h2>Dear " . $rowUser['name'] . ",</h2>
    <p>This message was sent as the reminder that you have a pending publication(s) to <b>read and sign </b>. </p>
    <p>We kindly request you to read and sign the publications for your interest.</p>
    <p >Please <a href='" . $baseUrl . "?action=document&qp=orptwp&key=" . $noteDetails['wv_key'] . "&nid=" . base64_encode($noteDetails['id']) . "' >click here</a> to see all the open publications listed in your department overview. </p>
    <p>Have a great day.</p>
    <p>Team</p>

            <hr style=\"background-color: lightgray; border: none; height: 1px; margin: 5px 0 9px;\" />
    <h4 style='text-align: center;'>Thank you for using Cargohub Connect !</h4>
    </div>";
//            echo $msg;
//            exit();

                $m_comm = new App_Model_Comm();
                $content = $msg;
                $subject = "Read and sign reminder";
                //$to = 'gargiiwi@gmail.com';
                $to = $rowUser['email'];
//            IWI Create a log for read and sign reminder mail send start 
                $send = $m_comm->send_email($to, $rowUser['name'], $content, $subject); // true = standalone message, no conversation.
                $r[] = $send;

                $mailSendingLogData = array(
                    'user_id' => $key,
                    'doc_id' => $noteDetails['id'],
                    'reminder_type' => 'manual_bulk',
                    'sending_report' => $send['sent'],
                    'mail_details' => json_encode($send['details']),
                );

                $this->user->submit_read_sign_mail_log($mailSendingLogData);
//            IWI Create a log for read and sign reminder mail send end 
            }
            if (count($r) > 0) {
                $this->evan->add(sprintf("Reminder mail has been sent ", $to), 'send');
                $this->send_JSON(array('success' => true, 'msg' => $r));
            } else {
                $this->evan->add(sprintf("Opps, please try again!", trim($to)), 'send');
                $this->send_JSON(array('success' => false, 'msg' => $r));
            }
        }
    }

//    IWI Create a button to bulk mail send  end
//IWI Create a log for read and sign reminder mail send  start
    public function action_Publication_reminder_mail_log(MVC_Request $in) {
        array_push($this->CFG['modules'], "user/_publication_reminder_mail_log_list"); // for loading js page 
        $this->prepare_Header("Publication Read & Sign Reminder Mail Log"); //  
        $note_id = $this->note_id = $in->id;
        $noteDetails = $this->user->getNoteDetails($in->id);
//        print_r($noteDetails);
        $this->noteTitle = $noteDetails['title'];
        $this->issue_track = $noteDetails['track'];

        $get_publication_read_sign_log_list = $this->user->get_publication_read_sign_log_list($note_id);
        $readSignReminderLogData = array();

        $this->readSignReminderLogData = $get_publication_read_sign_log_list;
//        echo "<pre>";
//        print_r($get_publication_read_sign_log_list);
//        echo "</pre>";

        $this->site_section = "user" . DS . "pubication" . DS . "publication_reminder_mail_log_list.html";
        $this->view("pubication_container.html");
    }

//IWI Create a log for read and sign reminder mail send  end
//    IWI popup to view all reminder dates start
    public function action_getReminderMailLogByUserDocId(MVC_Request $in) {
        $user_id = $_POST['user_id'];
        $doc_id = $_POST['doc_id'];
        $getList = $this->user->getReminderMailLogByUserDocId($user_id, $doc_id);
        $getListData = array();
        foreach ($getList as $key => $row) {
            $data = array(
                'time' => date('jS F Y h:i:s A ', strtotime($row['created_at'])),
                'type' => $row['reminder_type'] == 'cron' ? 'System' : 'Manual',
                'report' => $row['sending_report'] == '1' ? 'Success' : 'Error',
            );
            $getListData[$key] = $data;
        }
        $datasources['getList'] = $getListData;

        $this->send_JSON($datasources);
    }

//    IWI popup to view all reminder dates end
//    IWI all doc list needs to read & sign start


    public function action_Get_all_read_sign() {

        array_push($this->CFG['modules'], "user/_get_all_read_sign"); // for loading js page 
        $this->prepare_Header("All Doc List To Read & Sign"); //  
        $user_id = MVC_Core::$ss->user['id'];
        $dep_id = MVC_Core::$ss->user['cdep_id'];

        $get_user_details = $this->user->get_user_details(MVC_Core::$ss->user['id']);
        $getDepartmentsByUserId = array();
        if (MVC_Core::$ss->user['status'] == 'admin') {
            $getDepartmentsByUserId = $this->dep->get_List(MVC_Core::$ss->user['org_id'], array());
        } else if ($get_user_details['publish_all_linked_department'] == '1') {
            $getDepartmentsByUserId = $this->user->get_publish_all_linked_department_ids_List(MVC_Core::$ss->user['id']);
        } else {
            $getDepartmentsByUserId = $this->user->getDepartmentsByUserId(MVC_Core::$ss->user['id']);
        }
        $assign_departments_list = '';
        $this_allocate_dep = array();
        foreach ($getDepartmentsByUserId as $d_row) {
            $this_allocate_dep[] = $d_row['id'];
        }


        $searchData = array(
            'this_allocate_dep' => $this_allocate_dep,
        );
        $this->current_rt = MVC_Core::$cache->current_rt;

        $datasources = array();
        $recent_records = $this->user->getPendingReadSignPublishDocuments(MVC_Core::$ss->user['id'], "iss_recent", false, 1, true, $searchData);

        $datasources['recent'] = array();
        if (!empty($recent_records)) {
            foreach ($recent_records as $track => $data) {
                $data['publishedByName'] = '';
                if (!empty($data['published_by'])) {
                    $getPublishedByNameSql = "SELECT `name` FROM `users` WHERE `id` = " . $data['published_by'] . " ";
                    $getPublishedByNameResult = MVC_Core::$db->query($getPublishedByNameSql);
                    if (!$getPublishedByNameResult)
                        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
                    foreach ($getPublishedByNameResult as $row1) {
                        $data['publishedByName'] = $row1['name'];
                    }
                }

                $q_doctype = "SELECT value, TRIM(BOTH '\"' FROM TRIM(TRAILING ',' FROM 
                SUBSTRING_INDEX(SUBSTRING_INDEX(issue_xfdef.cd, CONCAT('#', issue_fields.value, ': '), -1), ',', 1))) 
                AS full_form FROM issue_fields 
                INNER JOIN issue_xfdef ON issue_xfdef.id = issue_fields.fk_id 
                INNER JOIN issues ON issues.id = issue_fields.issue_id 
                WHERE issue_xfdef.fk = 'doctype' AND issues.id='" . $data['link_id'] . "'";

                $r_doctype = MVC_Core::$db->query($q_doctype);
                $result_doctype = $r_doctype->current();
                $data['doc_type_full_name'] = isset($result_doctype['full_form']) && $result_doctype['full_form'] != '' ? $result_doctype['full_form'] : '--';

                $getUserReadSignData = $this->note->getUserReadSignData(MVC_Core::$ss->user['id'], $data['id']);

                if (is_array($getUserReadSignData) && !empty($getUserReadSignData)) {
                    $read_sign = $getUserReadSignData['read_sign'] ?? '0';
                } else {
                    $read_sign = '0';
                }
                $data['read_sign'] = $read_sign;
                $data['logged_in_user'] = MVC_Core::$ss->user['id'];
                $read_sign_update = '0';
                if ($read_sign != '0') {
                    $signed_at = $getUserReadSignData['signed_at'];
                    if (trim($data['update_dt']) != '') {
                        $update_dt_time = strtotime($data['update_dt']);
                        $signed_at_time = strtotime($signed_at);
                        if ($update_dt_time > $signed_at_time) {
                            $read_sign_update = '1';
                        }
                    }
                }
                $data['read_sign_update'] = $read_sign_update;

                $rt_info = &MVC_Core::$cache->record_types[MVC_Core::$cache->record_types_index[$data['ist']]];
                $datasources['recent'][] = array_merge($data, array(
                    'track' => $track,
                    'rt_key' => $rt_info['key'],
                    'rt_name' => $rt_info['name']
                ));
                if ($read_sign == '0' || $read_sign_update == '1') {
                    $getAllPendingReadSign[] = $data;
                }
            }
        }
        //    echo "<pre>";
        //    print_r($getAllPendingReadSign);
        //    echo "</pre>";



        $this->getAllPendingReadSign = $getAllPendingReadSign;
        $this->total_pending = count($getAllPendingReadSign);
        $this->site_section = "user" . DS . "pubication" . DS . "get_all_read_sign.html";
        $this->view("pubication_container.html");
    }

//    IWI all doc list needs to read & sign end
//    IWI Create an icon for archived published doc start

    public function action_get_all_archive_doc() {

        array_push($this->CFG['modules'], "user/_get_all_archive_doc"); // for loading js page 
        $this->prepare_Header("All Archive Doc List "); //  
        $user_id = MVC_Core::$ss->user['id'];
        $dep_id = MVC_Core::$ss->user['cdep_id'];
        $org_id = MVC_Core::$ss->user['org_id'];

        $get_user_details = $this->user->get_user_details(MVC_Core::$ss->user['id']);
        $publish_all_linked_department_ids = ($get_user_details['publish_all_linked_department_ids'] != '') ? json_decode($get_user_details['publish_all_linked_department_ids']) : array();
        $searchData['publish_all_linked_department_ids'] = $publish_all_linked_department_ids;

        $getArchiveDocsByOrgId = $this->user->getArchiveDocsByOrgId($user_id, $org_id, $searchData);

//        echo "<pre>";
//        print_r($getArchiveDocsByOrgId);
//        echo "</pre>";
//        exit();


        $this->getAllPendingReadSign = $getArchiveDocsByOrgId;
        $this->site_section = "user" . DS . "pubication" . DS . "get_all_archive_doc.html";
        $this->view("pubication_container.html");
    }

//    IWI Create an icon for archived published doc end

    public function action_get_all_archive_announcement() {                                //Iwi archived announcement start
        array_push($this->CFG['modules'], "user/_get_all_archive_announcement"); // for loading js page 
        $this->prepare_Header("All Archive Announcement List "); //  
        $user_id = MVC_Core::$ss->user['id'];
        $dep_id = MVC_Core::$ss->user['cdep_id'];
        $org_id = MVC_Core::$ss->user['org_id'];

        $get_user_details = $this->user->get_user_details(MVC_Core::$ss->user['id']);
        $publish_all_linked_department_ids = ($get_user_details['publish_all_linked_department_ids'] != '') ? json_decode($get_user_details['publish_all_linked_department_ids']) : array();

        $searchData = array(
            'user_id' => $user_id,
            'dep_id' => $dep_id,
            'org_id' => $org_id,
        );
        $searchData['publish_all_linked_department_ids'] = $publish_all_linked_department_ids;

        $getArchiveDocsByOrgId = $this->note->getDocumentNotificationArchieve($searchData);

        foreach ($getArchiveDocsByOrgId as $row) {
            $noteDepArr = json_decode($row['wv_publish_for_ids']);
            if (!empty($noteDepArr)) {
                $noteDepTemp = array();
                foreach ($noteDepArr as $dp) {
                    $q_dep = QueryFactory::select()->table(MVC_Core::$db->tab['dep'])->where("`id` = ?", $dp)->limit(1);
                    $r_dep = MVC_Core::$db->query($q_dep);
                    $result_dep = $r_dep->current();
                    $noteDepTemp[] = $result_dep['name'];
                }
                $noteDep = implode(", ", array_filter($noteDepTemp));
                $noteDepCount = count(array_filter($noteDepTemp));
            } else {
                $noteDep = '---';
                $noteDepCount = intval('0');
            }
            $row['department'] = $noteDep;
            $row['department_count'] = $noteDepCount;

            $q_iss = QueryFactory::select()->table(MVC_Core::$db->tab['iss'])->where("`id` = ?", $row['link_id'])->limit(1);
            $r_iss = MVC_Core::$db->query($q_iss);
            $result_iss = $r_iss->current();
//                            $row['iss'] = $result_iss;
            if (count($result_iss) > 0) {
                $row['track_id'] = $result_iss['track'];
            } else {
                continue;
            }

            $q_usr = QueryFactory::select()->table(MVC_Core::$db->tab['usr'], array('published_by_name' => 'name'))->where("`id` = ?", $row['published_by'])->limit(1);
            $r_usr = MVC_Core::$db->query($q_usr);
            $result_usr = $r_usr->current();

            $row['publishedByName'] = $result_usr['published_by_name'];

            $row['nid'] = base64_encode($row['id']);

            $sources[] = $row;
        }




        //    echo "<pre>";
        // print_r($getArchiveDocsByOrgId);
        // echo "</pre>";
        //    exit();


        $this->getAllArchiveAnnouncement = $sources;
        $this->site_section = "user" . DS . "pubication" . DS . "get_all_archive_announcement.html";
        $this->view("pubication_container.html");
    }

//Iwi archived announcement end

    public function Get_count_all_read_sign() {

        $get_user_details = $this->user->get_user_details(MVC_Core::$ss->user['id']);
        $getDepartmentsByUserId = array();
        if (MVC_Core::$ss->user['status'] == 'admin') {
            $getDepartmentsByUserId = $this->dep->get_List(MVC_Core::$ss->user['org_id'], array());
        } else if ($get_user_details['publish_all_linked_department'] == '1') {
            $getDepartmentsByUserId = $this->user->get_publish_all_linked_department_ids_List(MVC_Core::$ss->user['id']);
        } else {
            $getDepartmentsByUserId = $this->user->getDepartmentsByUserId(MVC_Core::$ss->user['id']);
        }
        $assign_departments_list = '';
        $this_allocate_dep = array();
        foreach ($getDepartmentsByUserId as $d_row) {
            $this_allocate_dep[] = $d_row['id'];
        }


        $searchData = array(
            'this_allocate_dep' => $this_allocate_dep,
        );
        $datasources = array();
        $recent_records = $this->user->getPendingReadSignPublishDocuments(MVC_Core::$ss->user['id'], "iss_recent", false, 1, true, $searchData);

        $getAllPendingReadSign = array();

        if (!empty($recent_records)) {
            foreach ($recent_records as $track => $data) {


                $getUserReadSignData = $this->note->getUserReadSignData(MVC_Core::$ss->user['id'], $data['id']);

                if (is_array($getUserReadSignData) && !empty($getUserReadSignData)) {
                    $read_sign = $getUserReadSignData['read_sign'] ?? '0';
                } else {
                    $read_sign = '0';
                }
                $data['read_sign'] = $read_sign;
                $data['logged_in_user'] = MVC_Core::$ss->user['id'];
                $read_sign_update = '0';
                if ($read_sign != '0') {
                    $signed_at = $getUserReadSignData['signed_at'];
                    if (trim($data['update_dt']) != '') {
                        $update_dt_time = strtotime($data['update_dt']);
                        $signed_at_time = strtotime($signed_at);
                        if ($update_dt_time > $signed_at_time) {
                            $read_sign_update = '1';
                        }
                    }
                }

                if ($read_sign == '0' || $read_sign_update == '1') {
                    $getAllPendingReadSign[] = $data;
                }
            }
        }
        return count($getAllPendingReadSign);
    }

//    IWI Create section for Notification start
    public function action_get_Documentation_Notification_By_Search() {
//        print_r($_POST);
        $user_id = MVC_Core::$ss->user['id'];
        $dep_id = MVC_Core::$ss->user['cdep_id'];
        $org_id = MVC_Core::$ss->user['org_id'];
        $getData = array();
        $datasources = array();
        $sources = array(); // 28012025 read only user documentation add Announcement
        $datasources['user_type'] = MVC_Core::$ss->user['status'];

        $searchData = array(
            'user_id' => $user_id,
            'dep_id' => $dep_id,
            'org_id' => $org_id,
        );

//        IWI notification changes 17032026 start 
        $get_user_details = $this->user->get_user_details(MVC_Core::$ss->user['id']);
        $getDepartmentsByUserId = array();
        if (MVC_Core::$ss->user['status'] == 'admin') {
            $getDepartmentsByUserId = $this->dep->get_List(MVC_Core::$ss->user['org_id'], array());
        } else if ($get_user_details['publish_all_linked_department'] == '1') {
            $getDepartmentsByUserId = $this->user->get_publish_all_linked_department_ids_List(MVC_Core::$ss->user['id']);
        }
        $searchData['publish_all_linked_department_ids'] = $getDepartmentsByUserId;

//        IWI notification changes 17032026 end

        if ($_POST['tab_type'] == 'fetch_all_notification') {
            $getData = $this->note->getDocumentNotification($searchData);
        }
        if (!empty($getData)) {
            foreach ($getData as $row) {
                $row['rt_name'] = $row['rt_name'];
                $noteDepArr = json_decode($row['wv_publish_for_ids']);
                if (!empty($noteDepArr)) {
                    $noteDepTemp = array();
                    foreach ($noteDepArr as $dp) {
                        $q_dep = QueryFactory::select()->table(MVC_Core::$db->tab['dep'])->where("`id` = ?", $dp)->limit(1);
                        $r_dep = MVC_Core::$db->query($q_dep);
                        $result_dep = $r_dep->current();
                        $noteDepTemp[] = $result_dep['name'];
                    }
                    $noteDep = implode(", ", array_filter($noteDepTemp));
                    $noteDepCount = count(array_filter($noteDepTemp));
                } else {
                    $noteDep = '---';
                    $noteDepCount = intval('0');
                }
                $row['department'] = $noteDep;
                $row['department_count'] = $noteDepCount;

                $q_iss = QueryFactory::select()->table(MVC_Core::$db->tab['iss'])->where("`id` = ?", $row['link_id'])->limit(1);
                $r_iss = MVC_Core::$db->query($q_iss);
                $result_iss = $r_iss->current();
                //$row['iss'] = $result_iss;
                if (count($result_iss) > 0) {
                    $row['track_id'] = $result_iss['track'];

                    // $rt_info = &MVC_Core::$cache->record_types[MVC_Core::$cache->record_types_index[$result_iss['ist']]];
                } else {
                    continue;
                }

                $q_usr = QueryFactory::select()->table(MVC_Core::$db->tab['usr'], array('published_by_name' => 'name'))->where("`id` = ?", $row['published_by'])->limit(1);
                $r_usr = MVC_Core::$db->query($q_usr);
                $result_usr = $r_usr->current();

                $row['publishedByName'] = $result_usr['published_by_name'];

                $row['nid'] = base64_encode($row['id']);

                //iwi doctype enhancement start
                $q_doctype = "SELECT value, TRIM( BOTH '\"' FROM TRIM( TRAILING ',' FROM SUBSTRING_INDEX( SUBSTRING_INDEX(issue_xfdef.cd, CONCAT('#', issue_fields.value, ': '), -1), ',', 1 ) ) ) AS full_form FROM issue_fields INNER JOIN issue_xfdef ON issue_xfdef.id = issue_fields.fk_id INNER JOIN issues ON issues.id = issue_fields.issue_id WHERE issue_xfdef.fk = 'doctype' And issues.id='" . $row['link_id'] . "'";
                $r_doctype = MVC_Core::$db->query($q_doctype);
                $result_doctype = $r_doctype->current();
                $row['doc_type_full_name'] = isset($result_doctype['full_form']) && $result_doctype['full_form'] != '' ? $result_doctype['full_form'] : '--';

                //iwi doctype enhancement end

                $sources[] = $row;
            }
        }
        $datasources['data'] = $sources;
        $this->send_JSON($datasources);
    }

//    IWI Create section for Notification end
    // 28012025 read only user documentation add Announcement
    public function action_get_all_read_sign_by_read_only_user() {
        array_push($this->CFG['modules'], "user/_publication"); // for loading js page 
        $this->prepare_Header("Publication Center"); // 
        $user_id = MVC_Core::$ss->user['id'];
        $dep_id = MVC_Core::$ss->user['cdep_id'];

        $get_user_details = $this->user->get_user_details(MVC_Core::$ss->user['id']);
        $getDepartmentsByUserId = array();
        if (MVC_Core::$ss->user['status'] == 'admin') {
            $getDepartmentsByUserId = $this->dep->get_List(MVC_Core::$ss->user['org_id'], array());
        } else if ($get_user_details['publish_all_linked_department'] == '1') {
            $getDepartmentsByUserId = $this->user->get_publish_all_linked_department_ids_List(MVC_Core::$ss->user['id']);
        } else {
            $getDepartmentsByUserId = $this->user->getDepartmentsByUserId(MVC_Core::$ss->user['id']);
        }
        $assign_departments_list = '';
        $this_allocate_dep = array();
        foreach ($getDepartmentsByUserId as $d_row) {
            $this_allocate_dep[] = $d_row['id'];
        }


        $searchData = array(
            'this_allocate_dep' => $this_allocate_dep,
        );
        $this->current_rt = MVC_Core::$cache->current_rt;

        $datasources = array();
        $recent_records = $this->user->getPendingReadSignPublishDocuments(MVC_Core::$ss->user['id'], "iss_recent", false, 1, true, $searchData);

        $datasources['recent'] = array();
        if (!empty($recent_records)) {
            foreach ($recent_records as $track => $data) {
                $data['publishedByName'] = '';
                if (!empty($data['published_by'])) {
                    $getPublishedByNameSql = "SELECT `name` FROM `users` WHERE `id` = " . $data['published_by'] . " ";
                    $getPublishedByNameResult = MVC_Core::$db->query($getPublishedByNameSql);
                    if (!$getPublishedByNameResult)
                        return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
                    foreach ($getPublishedByNameResult as $row1) {
                        $data['publishedByName'] = $row1['name'];
                    }
                }


                $getUserReadSignData = $this->note->getUserReadSignData(MVC_Core::$ss->user['id'], $data['id']);

                if (is_array($getUserReadSignData) && !empty($getUserReadSignData)) {
                    $read_sign = $getUserReadSignData['read_sign'] ?? '0';
                } else {
                    $read_sign = '0';
                }
                $data['read_sign'] = $read_sign;
                $data['logged_in_user'] = MVC_Core::$ss->user['id'];
                $read_sign_update = '0';
                if ($read_sign != '0') {
                    $signed_at = $getUserReadSignData['signed_at'];
                    if (trim($data['update_dt']) != '') {
                        $update_dt_time = strtotime($data['update_dt']);
                        $signed_at_time = strtotime($signed_at);
                        if ($update_dt_time > $signed_at_time) {
                            $read_sign_update = '1';
                        }
                    }
                }
                $data['read_sign_update'] = $read_sign_update;

                $rt_info = &MVC_Core::$cache->record_types[MVC_Core::$cache->record_types_index[$data['ist']]];
                $datasources['recent'][] = array_merge($data, array(
                    'track' => $track,
                    'rt_key' => $rt_info['key'],
                    'rt_name' => $rt_info['name']
                ));
                if ($read_sign == '0' || $read_sign_update == '1') {
                    $getAllPendingReadSign[] = $data;
                }
            }
        }
        $this->getAllPendingReadSign = $getAllPendingReadSign;
        // $this->getAllPendingReadSign = $datasources['recent'];
        $this->total_pending = count($datasources['recent']);
        $this->site_section = "user" . DS . "pubication" . DS . "get_all_read_sign_by_read_only_user.html";
        $this->view("readonly_container.html");
    }

    // 28012025 read only user documentation add Announcement
//    IWI new dashboard design 11082026 start

    public function action_get_count_by_login_user(MVC_Request $post) {
        $for = $post->for;
        $result = array();
        if ($for == 'read_sign') {
            $get_user_details = $this->user->get_user_details(MVC_Core::$ss->user['id']);
            $getDepartmentsByUserId = array();
            if (MVC_Core::$ss->user['status'] == 'admin') {
                $getDepartmentsByUserId = $this->dep->get_List(MVC_Core::$ss->user['org_id'], array());
            } else if ($get_user_details['publish_all_linked_department'] == '1') {
                $getDepartmentsByUserId = $this->user->get_publish_all_linked_department_ids_List(MVC_Core::$ss->user['id']);
            } else {
                $getDepartmentsByUserId = $this->user->getDepartmentsByUserId(MVC_Core::$ss->user['id']);
            }
            $assign_departments_list = '';
            $this_allocate_dep = array();
            foreach ($getDepartmentsByUserId as $d_row) {
                $this_allocate_dep[] = $d_row['id'];
            }


            $searchData = array(
                'this_allocate_dep' => $this_allocate_dep,
            );
            $datasources = array();
            $recent_records = $this->user->getPendingReadSignPublishDocuments(MVC_Core::$ss->user['id'], "iss_recent", false, 1, true, $searchData);

            $getAllPendingReadSign = array();

            if (!empty($recent_records)) {
                foreach ($recent_records as $track => $data) {


                    $getUserReadSignData = $this->note->getUserReadSignData(MVC_Core::$ss->user['id'], $data['id']);
//                if (count($getUserReadSignData) > 0) {
//                    $read_sign = $getUserReadSignData['read_sign'];
//                } else {
//                    $read_sign = '0';
//                }

                    if (!empty($getUserReadSignData)) {
                        $read_sign = $getUserReadSignData['read_sign'];
                    } else {
                        $read_sign = '0';
                    }
                    $data['read_sign'] = $read_sign;
                    $data['logged_in_user'] = MVC_Core::$ss->user['id'];
                    $read_sign_update = '0';
                    if ($read_sign != '0') {
                        $signed_at = $getUserReadSignData['signed_at'];
                        if (trim($data['update_dt']) != '') {
                            $update_dt_time = strtotime($data['update_dt']);
                            $signed_at_time = strtotime($signed_at);
                            if ($update_dt_time > $signed_at_time) {
                                $read_sign_update = '1';
                            }
                        }
                    }

                    if ($read_sign == '0' || $read_sign_update == '1') {
                        $getAllPendingReadSign[] = $data;
                    }
                }
            }

            $result['read_sign'] = count($getAllPendingReadSign);
        }
        $this->send_JSON($result);
    }

//    IWI new dashboard design 11082026 end
}
