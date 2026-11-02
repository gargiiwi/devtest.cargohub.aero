<?php

class App_Ctrl_Record_Reports extends App_Ctrl_Base {

    protected $gridview;
    protected $group;
    protected $current_rt;

    public function __construct() {
        parent::__construct();

        $this->record = $this->factory_Model('Issue', "record");
        $this->user = $this->factory_Model('User');
    }

    public function action_Index($action = null) {
        $this->prepare_Header("Documentation & License monitor Link Report");

        $this->CFG['modules'][] = "record/_reports";
        $this->site_section = "record" . DS . "doc_lic_report.html";
        $this->view("ext_grid_container.html");
    }

    public function action_Doc_connected(MVC_Request $rq) {
//        echo "action_Doc_connected";
        $wtr = array('issue', 'org_specific', 'xfields');
//        if ($this->user->is_Unrestricted()) {
//            if (isset(MVC_Core::$ss->perspective))
//                $wtr['org_id'] = MVC_Core::$ss->perspective; // Could be 0, in which case all issues from all organizations are shown
//        } else {
//            $wtr['org_id'] = MVC_Core::$ss->user['org_id']; // Normal user accesses only issues visible in his own organization
//// implied "else" is when user is admin, no "perspective" used, all issues are shown.
//            $wtr[] = 'marker';
//            $wtr['user_id'] = MVC_Core::$ss->user['id'];
//        }

        $wtr['org_id'] = MVC_Core::$ss->user['org_id'];
        $wtr[] = 'marker';
        $wtr['user_id'] = MVC_Core::$ss->user['id'];
        $ss_custom = array();
        $ss_filters = array();
        $fields = array();
        $xfields = array();
        if (empty($ss_sort)) {
            $ss_sort = "`iss`.id DESC";
        }
        if (!empty($fields))
            $wtr['flist'] = $fields;
        else
            $wtr['flist'] = array();


//        print_r(MVC_Core::$ss->user['org_id']);exit();


        $rt_id = '6';
        $issues = $this->record->get_Doc_connected($rt_id, $wtr, array($this, "filter_Get_List"), array(
            'custom' => $ss_custom,
            'filters' => $ss_filters
                ), $ss_sort, $rq->pagesize, $rq->pagenum * $rq->pagesize, true, true);

        if ($issues && count($issues) > 0) {
            // a bit of processing on the values before sending the data


            foreach ($issues as &$issue) {
                $issue['id'] = (int) $issue['license_monitor_issue_id'];

                $issue['license_monitor_issue_id'] = (int) $issue['license_monitor_issue_id'];
                $issue['license_link'] = '<a href="/record/process:' . $issue['license_link'] . '#panels" target="_blank">' . $issue['license_link'] . '</a>';
                $issue['license_type'] = $issue['license_type'];
                $issue['name_or_title'] = $issue['name_or_title'];
                $issue['chapter'] = $issue['chapter'];
            }
        }
        $this->send_JSON(array(
            "found_rows" => $this->record->found_rows,
            "data" => array_values($issues),
            "issues data" => $wtr,
            "wtr org_id" => $wtr['org_id'],
            "ss_filters" => $ss_filters,
            'ss_custom' => $ss_custom,
            'current_rt' => $this->current_rt['base_id']
        ));
    }

    public function action_Doc_not_connected(MVC_Request $rq) {
//        echo "action_Doc_not_connected";
        $wtr = array('issue', 'org_specific', 'xfields');
//        if ($this->user->is_Unrestricted()) {
//            if (isset(MVC_Core::$ss->perspective))
//                $wtr['org_id'] = MVC_Core::$ss->perspective; // Could be 0, in which case all issues from all organizations are shown
//        } else {
//        $wtr['org_id'] = MVC_Core::$ss->user['org_id']; // Normal user accesses only issues visible in his own organization
//// implied "else" is when user is admin, no "perspective" used, all issues are shown.
//        $wtr[] = 'marker';
//        $wtr['user_id'] = MVC_Core::$ss->user['id'];
//        }
        $wtr['org_id'] = MVC_Core::$ss->user['org_id'];
        $wtr[] = 'marker';
        $wtr['user_id'] = MVC_Core::$ss->user['id'];
        $ss_custom = array();
        $ss_filters = array();
        $fields = array();
        $xfields = array();
        if (empty($ss_sort)) {
            $ss_sort = "`iss`.id DESC";
        }
        if (!empty($fields))
            $wtr['flist'] = $fields;
        else
            $wtr['flist'] = array();


//        print_r(MVC_Core::$ss->user['org_id']);exit();


        $rt_id = '6';
        $issues = $this->record->get_Doc_not_connected($rt_id, $wtr, array($this, "filter_Get_List"), array(
            'custom' => $ss_custom,
            'filters' => $ss_filters
                ), $ss_sort, $rq->pagesize, $rq->pagenum * $rq->pagesize, true, true);

        if ($issues && count($issues) > 0) {
            // a bit of processing on the values before sending the data



            foreach ($issues as &$issue) {
                $issue['id'] = (int) $issue['license_monitor_issue_id'];

                $issue['license_monitor_issue_id'] = (int) $issue['license_monitor_issue_id'];
                $issue['license_link'] = '<a href="/record/process:' . $issue['license_link'] . '#panels" target="_blank">' . $issue['license_link'] . '</a>';
                $issue['license_type'] = $issue['license_type'];
                $issue['name_or_title'] = $issue['name_or_title'];
                $issue['chapter'] = $issue['chapter'];
            }
        }
        $this->send_JSON(array(
            "found_rows" => $this->record->found_rows,
            "data" => array_values($issues),
            "issues data" => $wtr,
            "wtr org_id" => $wtr['org_id'],
            "ss_filters" => $ss_filters,
            'ss_custom' => $ss_custom,
            'current_rt' => $this->current_rt['base_id']
        ));
    }

    public function action_License_connected(MVC_Request $rq) {
        $wtr = array('issue', 'org_specific');
//        if ($this->user->is_Unrestricted()) {
//            if (isset(MVC_Core::$ss->perspective))
//                $wtr['org_id'] = MVC_Core::$ss->perspective; // Could be 0, in which case all issues from all organizations are shown
//        } else {
//            $wtr['org_id'] = MVC_Core::$ss->user['org_id']; // Normal user accesses only issues visible in his own organization
//// implied "else" is when user is admin, no "perspective" used, all issues are shown.
//            $wtr[] = 'marker';
//            $wtr['user_id'] = MVC_Core::$ss->user['id'];
//        }
        $wtr['org_id'] = MVC_Core::$ss->user['org_id'];
        $wtr[] = 'marker';
        $wtr['user_id'] = MVC_Core::$ss->user['id'];
        $ss_custom = array();
        $ss_filters = array();
        $fields = array();
        $xfields = array();
        if (empty($ss_sort)) {
            $ss_sort = "`iss`.id DESC";
        }
        if (!empty($fields))
            $wtr['flist'] = $fields;
        else
            $wtr['flist'] = array();


//        print_r(MVC_Core::$ss->user['org_id']);exit();


        $rt_id = '12';
        $issues = $this->record->get_License_connected($rt_id, $wtr, array($this, "filter_Get_List"), array(
            'custom' => $ss_custom,
            'filters' => $ss_filters
                ), $ss_sort, $rq->pagesize, $rq->pagenum * $rq->pagesize, true, true);

        if ($issues && count($issues) > 0) {
            // a bit of processing on the values before sending the data



            foreach ($issues as &$issue) {
                $issue['id'] = (int) $issue['id'];

                $checkActiveDocList = $this->record->get_active_published_doc_list(12, (int) $issue['id']);
                $issue['active_docs']['count'] = count($checkActiveDocList);

                $issue['id'] = (int) $issue['id'];
                $issue['document_link'] = '<a href="/record/process:' . $issue['document_link'] . '#panels" target="_blank">' . $issue['document_link'] . '</a>';
                $issue['name_or_title'] = $issue['name_or_title'] != '' ? $issue['name_or_title'] : $issue['cache_primary'];
                $issue['license_type'] = $issue['license_type'];
                $issue['valid_from'] =  $issue['valid_from']!=''?date('Y-m-d',$issue['valid_from']):'';
                $issue['expire_date'] =  $issue['expire_date']!=''?date('Y-m-d',$issue['expire_date']):'';
                $issue['next_review'] = $issue['next_review']!=''?date('Y-m-d',$issue['next_review']):'';
                $issue['review_date'] =  $issue['review_date']!=''?date('Y-m-d',$issue['review_date']):'';
                $issue['active_docs'] = count($checkActiveDocList);
            }
        }
        $this->send_JSON(array(
            "found_rows" => $this->record->found_rows,
            "data" => array_values($issues),
            "issues data" => $wtr,
            "wtr org_id" => $wtr['org_id'],
            "ss_filters" => $ss_filters,
            'ss_custom' => $ss_custom,
            'current_rt' => $this->current_rt['base_id']
        ));
    }

    public function action_License_not_connected(MVC_Request $rq) {
//        echo "action_License_not_connected";
        $wtr = array('issue', 'org_specific');
//        if ($this->user->is_Unrestricted()) {
//            if (isset(MVC_Core::$ss->perspective))
//                $wtr['org_id'] = MVC_Core::$ss->perspective; // Could be 0, in which case all issues from all organizations are shown
//        } else {
//            $wtr['org_id'] = MVC_Core::$ss->user['org_id']; // Normal user accesses only issues visible in his own organization
//// implied "else" is when user is admin, no "perspective" used, all issues are shown.
//            $wtr[] = 'marker';
//            $wtr['user_id'] = MVC_Core::$ss->user['id'];
//        }
        $wtr['org_id'] = MVC_Core::$ss->user['org_id'];
        $wtr[] = 'marker';
        $wtr['user_id'] = MVC_Core::$ss->user['id'];
        $ss_custom = array();
        $ss_filters = array();
        $fields = array();
        $xfields = array();
        if (empty($ss_sort)) {
            $ss_sort = "`iss`.id DESC";
        }
        if (!empty($fields))
            $wtr['flist'] = $fields;
        else
            $wtr['flist'] = array();


//        print_r(MVC_Core::$ss->user['org_id']);exit();


        $rt_id = '12';
        $issues = $this->record->get_License_not_connected($rt_id, $wtr, array($this, "filter_Get_List"), array(
            'custom' => $ss_custom,
            'filters' => $ss_filters
                ), $ss_sort, $rq->pagesize, $rq->pagenum * $rq->pagesize, true, true);

        if ($issues && count($issues) > 0) {
            // a bit of processing on the values before sending the data




            foreach ($issues as &$issue) {
                $issue['id'] = (int) $issue['id'];

                $checkActiveDocList = $this->record->get_active_published_doc_list(12, (int) $issue['id']);
                $issue['active_docs']['count'] = count($checkActiveDocList);

                $issue['id'] = (int) $issue['id'];
                $issue['document_link'] = '<a href="/record/process:' . $issue['document_link'] . '#panels" target="_blank">' . $issue['document_link'] . '</a>';
                $issue['name_or_title'] = $issue['name_or_title'] != '' ? $issue['name_or_title'] : $issue['cache_primary'];
                $issue['license_type'] = $issue['license_type'];
                $issue['valid_from'] =  $issue['valid_from']!=''?date('Y-m-d',$issue['valid_from']):'';
                $issue['expire_date'] =  $issue['expire_date']!=''?date('Y-m-d',$issue['expire_date']):'';
                $issue['next_review'] = $issue['next_review']!=''?date('Y-m-d',$issue['next_review']):'';
                $issue['review_date'] =  $issue['review_date']!=''?date('Y-m-d',$issue['review_date']):'';
                $issue['active_docs'] = count($checkActiveDocList);
            }
        }
        $this->send_JSON(array(
            "found_rows" => $this->record->found_rows,
            "data" => array_values($issues),
            "issues data" => $wtr,
            "wtr org_id" => $wtr['org_id'],
            "ss_filters" => $ss_filters,
            'ss_custom' => $ss_custom,
            'current_rt' => $this->current_rt['base_id']
        ));
    }

    public function action_Doc_connected_nested(MVC_Request $rq) {
//        echo $rq->parent;

        $org_id = MVC_Core::$ss->user['org_id'];
        $doc_id = isset($rq->parent) ? $rq->parent : '';
        $user_id = MVC_Core::$ss->user['id'];

        $finalData = '';
        $licenseMonitorManagementHtml = '';
        // Fetch existing license monitor data
        $getDocumentationManagementByLicense = $this->record->get_documentation_management_by_license_id($doc_id, $org_id, $user_id);
        $finalData = array();
        foreach ($getDocumentationManagementByLicense as $row) {
//            print_r($row);
            $row['document_link'] = '<a href="/record/process:' . $row['document_link'] . '#panels" target="_blank">' . $row['document_link'] . '</a>'; 
                $row['valid_from'] =  $row['valid_from']!=''?date('Y-m-d',$row['valid_from']):'';
                $row['expire_date'] =  $row['expire_date']!=''?date('Y-m-d',$row['expire_date']):'';
                $row['next_review'] = $row['next_review']!=''?date('Y-m-d',$row['next_review']):'';
                $row['review_date'] =  $row['review_date']!=''?date('Y-m-d',$row['review_date']):'';
            $finalData[] = $row;
        }
        $this->send_JSON(array(
            "found_rows" => $this->record->found_rows,
            "data" => $finalData,
        ));
    }

    public function action_License_connected_nested(MVC_Request $rq) {
//        echo $rq->parent;

        $org_id = MVC_Core::$ss->user['org_id'];
        $doc_id = isset($rq->parent) ? $rq->parent : '';
        $user_id = MVC_Core::$ss->user['id'];

        $finalData = '';
        $licenseMonitorManagementHtml = '';
        // Fetch existing license monitor data
        $getLicenseMonitorManagementByDoc = $this->record->get_license_monitor_management_by_doc_id($doc_id, $org_id, $user_id);
        $finalData = array();
        foreach ($getLicenseMonitorManagementByDoc as $row) {
//            print_r($row);
            $row['license_link'] = '<a href="/record/process:' . $row['license_link'] . '#panels" target="_blank">' . $row['license_link'] . '</a>';

            $finalData[] = $row;
        }
        $this->send_JSON(array(
            "found_rows" => $this->record->found_rows,
            "data" => $finalData,
        ));
    }

}
