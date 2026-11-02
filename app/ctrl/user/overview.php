<?php

/**
 * User Overview/Cockpit/Dashboard - An overview of the things that are relevant to a staff user (mostly usable as a "landing" page after login)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage Controllers 
 * @version 2.7
 */
class App_Ctrl_User_Overview extends App_Ctrl_Base { 

    /**
     * Contact model, which is necessary for user display/editor to work properly
     * @var App_Model_Contact
     */
    private $con;
            
    /** 
     * Issue model
     * @var App_Model_Issue     
     */ 
    private $issue;     

    /** 
     * Statistics model reference
     * 
     * @var App_Model_Statistics
     */
    public $stx;

    /**
     * Record Plugin, extended from Statistics model
     * @var App_Model_Statistics
     */
    protected $rec_plugin;

    public function __construct() {
        parent::__construct(true);
        $this->con = $this->factory_Model("Contact", "con");
        $this->issue = $this->factory_Model('Issue');

        $this->dep = $this->factory_Model("Department", "dep");
    }

    public static $_module_name = "My Overview";

    /**
     * User Overview Module init
     */
    public function action_Index() {
        // setup frontend config
        $this->CFG['components'] = array("modal", "contact_editor");
        array_push($this->CFG['modules'], "user/_overview");
        $this->CFG['jqwidgets'][] = array("tabs", "docking", "scrollbar", "listbox", "dragdrop", "dropdownlist", "input");
        $this->CFG['jqwidgets'][] = array("validator", "expander", "navigationbar", "calendar", "datetimeinput", "switchbutton");
        $this->CFG['jqwidgets'][] = array("-gridcomplete");

        // Chart-related stuff
        $this->CFG['jqwidgets'][] = array("chart", "draw", "chart.core", "grid.export", "data.export");

        $this->CFG['user_overview'] = array();
        $this->CFG['xjs'] = array(
            'jqform' => true,
            'tinymce' => true,
            'pwstrength' => true
        );

        // prepare output
        $this->prepare_Header(self::$_module_name);

        // User's preferences are sent to JavaScript, as they are used all over the User CP
        $this->CFG['user_overview']['preferences'] = MVC_Core::$ss->user['preferences'];

        // User privileges are needed in the organization module JS, as it determines if some operations are possible
        // FIXME: Wasn't this moved to Application module ?
        $user_privileges = array();
        if (isset(MVC_Core::$ss->user['acls']))
            foreach (MVC_Core::$ss->user['acls']['privileges'] as $privilege)
                $user_privileges[$privilege] = true;
        $this->CFG['user_overview']['user_acls'] = array(
            'is_admin' => $this->user->is_Unrestricted(),
            'privileges' => $user_privileges
        );

        // User Overview tabs, in the order they will be presented to the user:
        // TODO: Maybe a preference of changing this order?
        $all_available_tabs = array(
            // The primary tab for user overview, shows a panel-based interface (configurable) which can grab information from all around the system
            array(
                'id' => "tab_dashboard",
                'title' => MVC_Core::$ss->organization['orgtype']['mainmenu'][0]['name'],
                'icon' => MVC_Core::$ss->organization['orgtype']['mainmenu'][0]['icon']
            ),
            // Experiment: Listing all record types (modules), with information about them
            // IWI hide section start
           /* array(
                'id' => "tab_documents",
                'title' => "Publication",
                'icon' => "support.png",
                'spacey' => true
            ),*/
            array(
                'id' => "tab_modules",// hide using css in cargo_its_extra.css
                'title' => "Charts",
                'icon' => "bricks.png",
                'spacey' => true
            ),
            // IWI hide section end
            array(
                'id' => "tab_history",
                'title' => "History", //  and Updates
                'icon' => "clock_history_frame.png",
                'spacey' => true
            ),
            // User's account information form ( used to change password, name, e-mail, signature ) and also view some details about the organization and department the user is part of
            array(
                'id' => "tab_account",// hide using css in cargo_its_extra.css
                'title' => "My account",
                'icon' => "user_gray.png",
                'spacey' => true
            ),
            //
            array(
                'id' => "tab_vcard",// hide using css in cargo_its_extra.css
                'title' => "Contact information",
                'icon' => "vcard.png",
                'spacey' => true
            ),
            // User's current Access Control Lists, shows the user's privileges and other data compiled from both the organization features column and the calculated access_ids
            array(
                'id' => "tab_acls",// hide using css in cargo_its_extra.css
                'title' => "My current access",
                'icon' => "lock.png",
                'spacey' => true
            ),
            // User's group access (this is also an ACL), presented as a table, in which the user can set some options for each group he has access to
            array(
                'id' => "tab_notify",// hide using css in cargo_its_extra.css
                'title' => "Notifications",
                'icon' => "bell.png",
                'spacey' => true
            ),
            array(
                'id' => "tab_updates",// hide using css in cargo_its_extra.css
                'title' => "Support", //  and Updates
                'icon' => "support.png",
                'spacey' => true
            ),
        );

        $this->module = array(
            'icon' => "user_green.png",
            'icon_alt' => "(User)",
            'description' => "Information about your account",
            'title' => self::$_module_name, 'title_para' => MVC_Core::$ss->user['name'],
            'tabs' => $all_available_tabs
        );

        //== Contact editor JS data ==//
        // Fields structure for the contacts editor
        MVC_NLS::load_Groups("contact");
        $fields_structure = MVC_NLS::get_Object("contact", true);

        // Process the fields stucture a bit before sending it to JS
        foreach ($fields_structure as &$group) {
            foreach ($group['fields'] as $field => $string) {
                if (strpos($string, "~"))
                    $group['fields'][$field] = explode("~", $string);
            }
        }

        $this->CFG['user_overview']['vcard_fields_structure'] = $fields_structure;

        // which body template to use, and run the view
        $this->site_section = "general_tabs_container.html";
        $this->view("private.html");
    }

    /**
     * Loads the Dashboard containing various useful information and navigation shortcuts
     *
     * @param MVC_Request $in
     */
    private function load_Tab_Dashboard(MVC_Request $in) {
        // Dashboard tab needs data from the session and also checks privileges
        $this->current_rt = MVC_Core::$cache->current_rt;

        $datasources = array();
        // Recently opened records - Add ist information (key, name)
        $recent_records = $this->user->profile_Read(MVC_Core::$ss->user['id'], "iss_recent", false, 1, true);
        if (!empty($recent_records)) {
            $datasources['recent'] = array();
            foreach ($recent_records as $track => $data) {
                if (!isset(MVC_Core::$cache->record_types_index[$data['ist']])) {
                    unset($recent_records[$track]);
                    continue;
                }

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

        // Hybrid response (HTML+JSON)
        if (!empty($datasources))
            $this->send_JSON_Headers(array(
                'dataSources' => $datasources
            ));
    }

    /* Dayakar code start */

    /**
     * Loads the Dashboard containing various useful information and navigation shortcuts
     *
     * @param MVC_Request $in
     */
    private function load_Tab_Dashboard_cockpit(MVC_Request $in) {
        // Extract some information about active record type
        $current_rt = array(
        );
        $issueCount = $this->user->user_IssueCount(MVC_Core::$ss->user['id'], MVC_Core::$ss->user['org_id'], MVC_Core::$ss->user['status'], MVC_Core::$cache->record_types_index);
        if (MVC_Core::$ss->user['status'] != 'admin') {
            $myAssignedissueCount = $this->user->user_IssueCountMyAssigned(MVC_Core::$ss->user['id'], MVC_Core::$ss->user['org_id'], MVC_Core::$ss->user['status'], MVC_Core::$cache->record_types_index);
        }
        $myTaskCount = $this->user->active_TaskCount(MVC_Core::$ss->user['id'], MVC_Core::$ss->user['org_id'], MVC_Core::$ss->user['status'], MVC_Core::$cache->record_types_index);

        $taskType = "overDue";
        $myOverDewTaskCount = $this->user->active_TaskCount(MVC_Core::$ss->user['id'], MVC_Core::$ss->user['org_id'], MVC_Core::$ss->user['status'], MVC_Core::$cache->record_types_index, $taskType);
        $rt_r = $this->issue->ist_Get_List();
        $acls_rt = isset(MVC_Core::$ss->user['acls']['ist']) ? MVC_Core::$ss->user['acls']['ist'] : true;

        $rt = array();
        $rt_disabled = array();
        foreach ($rt_r as $row) {
            $row['id'] = (int) $row['id'];
            // Compare with user's access rights?
            if ($acls_rt === true)
                $row['yep'] = true;
            elseif (in_array($row['id'], $acls_rt))
                $row['yep'] = true;

            if (isset($row['yep']))
                $rt[] = $row;
            else
                $rt_disabled[] = $row;
        }
        /* for recent records */
        //changes for active record count
        $issue_Details = array_merge($rt, $rt_disabled);
        $issueCountForTypes = array();
        $myAssignedissueCountForTypes = array();
        $myTasksCount = array();
        $myOverDewTasksCount = array();
        foreach ($issueCount as $key => $value) {
            $issueCountForTypes[$value['id']] = $value['issue_count'];
        }
        foreach ($issue_Details as $key => $issVal) {
            $issue_Details[$key]['isscount'] = $issueCountForTypes[$issVal['id']];
        }
        if (MVC_Core::$ss->user['status'] != 'admin') {
            foreach ($myAssignedissueCount as $key => $value) {
                $myAssignedissueCountForTypes[$value['id']] = $value['issue_count'];
            }
            foreach ($issue_Details as $key => $issVal) {
                $issue_Details[$key]['myAssignedCount'] = $myAssignedissueCountForTypes[$issVal['id']];
            }
        }
        foreach ($myTaskCount as $key => $value) {
            $myTasksCount[$value['id']] = $value['issue_count'];
        }
        foreach ($issue_Details as $key => $issVal) {
            $issue_Details[$key]['taskCount'] = $myTasksCount[$issVal['id']];
        }
        foreach ($myOverDewTaskCount as $key => $value) {
            $myOverDewTasksCount[$value['id']] = $value['issue_count'];
        }
        foreach ($issue_Details as $key => $issVal) {
            $issue_Details[$key]['overDewTaskCount'] = $myOverDewTasksCount[$issVal['id']];
        }
                
//        IWI show total record count based on user�s active department start
       
            $getAllDepRecordCount = $this->user->getAllDepRecordCount(MVC_Core::$ss->user['id'], MVC_Core::$ss->user['org_id'], MVC_Core::$ss->user['status'], MVC_Core::$cache->record_types_index);
         foreach ($getAllDepRecordCount as $key => $issRec) {
            $issueCountForTypesByDep[$issRec['id']] = $issRec['issue_count'];
        }
        
       foreach ($issue_Details as $key => $issVal) {
            $issue_Details[$key]['isscount_by_dep'] = $issueCountForTypesByDep[$issVal['id']];
        }
        
//        IWI show total record count based on user�s active department end
        
        
        
     
        $this->current_rt = MVC_Core::$cache->current_rt;
        $datasources = array();
        // Recently opened records - Add ist information (key, name)
        $recent_records = $this->user->profile_Read(MVC_Core::$ss->user['id'], "iss_recent", false, 1, true);
        if (!empty($recent_records)) {
            $datasources['recent'] = array();
            foreach ($recent_records as $track => $data) {
                if (!isset(MVC_Core::$cache->record_types_index[$data['ist']])) {
                    unset($recent_records[$track]);
                    continue;
                }

                $rt_info = &MVC_Core::$cache->record_types[MVC_Core::$cache->record_types_index[$data['ist']]];
                $datasources['recent'][] = array_merge($data, array(
                    'track' => $track,
                    'rt_key' => $rt_info['key'],
                    'rt_name' => $rt_info['name'],
                    'pri' => $recent_records[$track]['pri']
                ));
            }
        }
        $this->recent_issues_count = count($recent_records);

        // Read the Mini-Notepad data

        /* removed by castus */
        //$this-> mini_notepad = htmlspecialchars($this-> user-> profile_Read(MVC_Core::$ss-> user['id'], "mini_notepad"));
        // Read list of issues for the "Recent Issues" panel.
        $this->prepare_Template_ACLs();

        // User elements count
        $this->count = MVC_Core::$ss->user['count'];
        // Hybrid response (HTML+JSON)
        
        
        //         IWI new dashboard design 11082026 start
$this->session_count=array(
                    'capaCount'=>$_SESSION['capaCount'],
                    'taskCount'=>$_SESSION['taskCount'],
                    'notificationCount'=>$_SESSION['notificationCount'], 
                );
//$publication = new App_Ctrl_User_Publication();
//$this->read_sign_count=$publication->Get_count_all_read_sign();
// IWI new dashboard design 11082026 end

        $this->send_JSON_Headers(array(
            'tab_dashboard' => array(
                'rtlist_dashboard' => $issue_Details,
                // Extract some basic information about active record type
                'current_rt' => array(
                    'id' => MVC_Core::$cache->current_rt['id'],
                    'key' => MVC_Core::$cache->current_rt['key'],
                    'name' => MVC_Core::$cache->current_rt['name']
                )
            ),
            "dataSources" => $datasources
        ));
    }

    /* Dayakar code end */

    public function load_Tab_History(MVC_Request $in) {
        /* for recent records */


        $this->current_rt = MVC_Core::$cache->current_rt;

        $datasources = array();
        // Recently opened records - Add ist information (key, name)
        $recent_records = $this->user->profile_Read(MVC_Core::$ss->user['id'], "iss_recent", false, 1, true);
        if (!empty($recent_records)) {
            $datasources['recent'] = array();
            foreach ($recent_records as $track => $data) {
                if (!isset(MVC_Core::$cache->record_types_index[$data['ist']])) {
                    unset($recent_records[$track]);
                    continue;
                }

                $rt_info = &MVC_Core::$cache->record_types[MVC_Core::$cache->record_types_index[$data['ist']]];
                $datasources['recent'][] = array_merge($data, array(
                    'track' => $track,
                    'rt_key' => $rt_info['key'],
                    'rt_name' => $rt_info['name'],
                    'pri' => $recent_records[$track]['pri']
                ));
            }
        }
        $this->recent_issues_count = count($recent_records);

        $this->send_JSON_Headers(array(
            'tab_dashboard' => array(
                'rtlist_dashboard' => array_merge(
                        is_array($rt) ? $rt : array(),
                        is_array($rt_disabled) ? $rt_disabled : array()
                ),
                // Extract some basic information about active record type
                'current_rt' => array(
                    'id' => MVC_Core::$cache->current_rt['id'],
                    'key' => MVC_Core::$cache->current_rt['key'],
                    'name' => MVC_Core::$cache->current_rt['name']
                )
            ),
            "dataSources" => $datasources
        ));
    }

    /**
     * Tab #dashboard - Mini Notepad
     * @param MVC_Request $rq
     */
    public function action_Save_MiniNotepad(MVC_Request $rq) {
        $r = $this->user->profile_Write(MVC_Core::$ss->user['id'], "mini_notepad", $rq->content);
        if (!$r)
            return header("HTTP/1.0 500 Notepad could not be saved. Possible database error!");

        echo "1";
    }

    /**
     * Tab #modules - Now enhanced with organization record statistics
     * @param MVC_Request $in
     */
    private function load_Tab_Modules(MVC_Request $in) {
        // Extract some information about active record type
        $current_rt = array(
        );

        $rt_r = $this->issue->ist_Get_List();

        $acls_rt = isset(MVC_Core::$ss->user['acls']['ist']) ? MVC_Core::$ss->user['acls']['ist'] : true;

        $rt = array();
        $rt_disabled = array();
        foreach ($rt_r as $row) {
            $row['id'] = (int) $row['id'];

            // Compare with user's access rights?
            if ($acls_rt === true)
                $row['yep'] = true;
            elseif (in_array($row['id'], $acls_rt))
                $row['yep'] = true;

            if (isset($row['yep']))
                $rt[] = $row;
            else
                $rt_disabled[] = $row;
        }




        // Hybrid response (HTML+JSON)
        $this->send_JSON_Headers(array(
            'tab_modules' => array(
                'rtlist' => array_merge($rt, $rt_disabled),
                // Extract some basic information about active record type
                'current_rt' => array(
                    'id' => MVC_Core::$cache->current_rt['id'],
                    'key' => MVC_Core::$cache->current_rt['key'],
                    'name' => MVC_Core::$cache->current_rt['name']
                )
            )
        ));
    }

    /**
     * Loads information about a record type as a html page.
     * @param MVC_Request $rq
     */
    public function action_Load_RT(MVC_Request $rq) {

        // Determine if the record type is valid for the logged in user or not. Functions ( submit, list ) are not available if the record type is not available
        $acls_rt = $this->user->read_ACL('ist');
        if ($acls_rt === true || in_array($rq->rt_id, $acls_rt))
            $this->enabled = $rt_enabled = true; // Access to the module
            
// 18102023 - added by sourav start
        $rt_status = $rq->rt_status != '' ? $rq->rt_status : 'all';
        $claim_type = $rq->claim_type != '' ? $rq->claim_type : 'all';
        $claimant_type = $rq->claimant_type != '' ? $rq->claimant_type : 'all';
        $movement = $rq->movement != '' ? $rq->movement : 'all';
        $carrier_liability = $rq->carrier_liability != '' ? $rq->carrier_liability : 'all';
        $claim_aspects = $rq->claim_aspects != '' ? $rq->claim_aspects : 'all';

        $filter_chart = array();
        if ($claim_type != 'all') {
            $filter_chart = array('pstage'); //pstage
        }

        if ($claimant_type != 'all') {
            array_push($filter_chart, "subm_type"); //subm_type
        }

        if ($movement != 'all') {
            array_push($filter_chart, "move"); //move
        }

        if ($carrier_liability != 'all') {
            array_push($filter_chart, "liable"); //liable /xfield_enum_liable
        }


        if ($claim_aspects != 'all') {
            array_push($filter_chart, "claim_type"); //claim_type /xfield_set_claim_type
        }




        $rt_status_array = array('status' => $rt_status);
        $claim_type_array = array('claim_type' => $claim_type, 'claimant_type' => $claimant_type, 'movement' => $movement, 'carrier_liability' => $carrier_liability, 'claim_aspects' => $claim_aspects);

        $array_merge = array_merge($rt_status_array, $claim_type_array);
        $_SESSION["chart_filter"] = $array_merge;
        $_SESSION["filter_chart_name"] = $filter_chart;

        // 18102023 - added by sourav end
        // Hybrid response
        $data_response = array();

        // Top-Right Function buttons
        $functions = array();
        $functions['submit'] = true;
        $functions['list'] = true;

        // Read record type details
        $rt_def = $this->issue->ist_Read($rq->rt_id, null);

        $this->rt = $data_response['rt_def'] = array(
            'id' => (int) $rt_def['id'],
            'key' => $rt_def['key'],
            'name' => $rt_def['name'],
            'plural' => $rt_def['short_plural']
        );

        // Also load the matching CMS page for the record type key (might contain detailed description, screenshots, etc.)
        $m_cms = new App_Model_CMS();
        $rtinfo_cmsp = $m_cms->read("/rtinfo/" . $rt_def['key']);
        $this->_out['rt']['info'] = !empty($rtinfo_cmsp) ? $rtinfo_cmsp['contents'] : $rt_def['description'];

        // If enabled, build statistics information and chart configuration
        if (!empty($rt_enabled) && !empty($rt_def['settings']['plugin']) && !empty($rt_def['settings']['statistics'])) {
            // The statistics are calculated for logged in user (but it should support admin's Perspective as well, maybe later)
            $org_id = MVC_Core::$ss->user['org_id'];

            $data_response['charts'] = array(); // Just a list, used in loops
            $data_response['charts_config'] = array(); // Will contain actual display configuration data + statistics (note: Can get quite big !)
            $this->stx = $this->factory_Model("Statistics", "stx");
            $this->rec_plugin = MVC_Core::factory_Plugin("record", $rt_def['key']);

            // User preferences. If "FALSE", it means no preferences have been saved and all charts have to be enabled
            $charts_prefs = $this->user->profile_Read(MVC_Core::$ss->user['id'], "charts_" . $rt_def['key']);

            if ($charts_prefs === false)
                $charts_prefs = $rt_def['settings']['statistics_methods']; // Default to all active and enabled, and in the original order



                
// $rt_def['settings']['statistics_methods'] = array();
            // $rt_def['settings']['statistics_methods'][0] = 'xfield_set_claim_type';
            // $rt_def['settings']['statistics_methods'][0] = 'xfield_enum_move';
            // $rt_def['settings']['statistics_methods'][0] = 'xfield_set_claim_type';
            // $rt_def['settings']['statistics_methods'][1] = 'xfield_enum_pstage';
            // $rt_def['settings']['statistics_methods'][1] = 'xfield_enum_subm_type';
            $indexdata =1;
            foreach ($rt_def['settings']['statistics_methods'] as $model) {

                $visible = in_array($model, $charts_prefs);
                // echo $rq->period= 'years';
                // Read statistics for organization/record type - Caching is built into the function


                if ($model == 'records_per_provision') {
                    $stats = $this->rec_plugin->org_Statistics($org_id, $rt_def['id'], $model, array(
                        'period' => $rq->period ?: "years", // Start with months as default (Last twelve months) - Should be configurable !
                        'subset' => $rq->subset ?: 0,
                        // 18102023 - added by sourav start
                        'rt_status' => $rt_status,
                            // 18102023 - added by sourav end
                            // Start with "Show all" as default (No specific subset, 0)
                            ), $visible); // Last parameter = Visibility ; Non-visible charts are only silently checked for errors in the configuration
                } else {
                    $stats = $this->rec_plugin->org_Statistics($org_id, $rt_def['id'], $model, array(
                        'period' => $rq->period ?: "months", // Start with months as default (Last twelve months) - Should be configurable !
                        'subset' => $rq->subset ?: 0, // Start with "Show all" as default (No specific subset, 0)
                        // 18102023 - added by sourav start
                        'rt_status' => $rt_status,
                        'claim_type' => $claim_type,
                            // 18102023 - added by sourav end
                            ), $visible); // Last parameter = Visibility ; Non-visible charts are only silently checked for errors in the configuration
                }


                if ($visible) {
                    if (empty($stats))
                        continue; // Stats not found ? Skip the chart completely
                    // Test 2: Get configuration
                    $chart = $this->rec_plugin->build_Chart_Config($model, array(
                        'org_id' => $org_id,
                        'rt_id' => $rt_def['id']
                    ));
                    
                    
                    //print_r($chart);exit();

                    $chart['source'] = $stats;
                    // Append data for each correct chart
                    $data_response['charts'][] = $model;
                    
                     
                    $data_response['charts_config'][$model] = array(
                        'name' => $this->rec_plugin->get_Chart_name($model),
                        'visible' => true,
                        'chart' => $chart,
                        'controls' => array(
                            'process' => !in_array($model, array("total_records", "records_per_provision")),
                            'range' => $model != "records_per_provision" ? array("Months", "Quarters", "Years") : false,
                            'dump' => $model == "records_per_provision", // Aka "Export to Excel" - Unfortunately, this doesn't exist ... A manual exporting of data has to be built - Universal ExcelXML generator function maybe?
                            'image' => true, // PNG Export (built into jqxChart, needs HTML5)
                            'print' => true // Printing (built into jqxChart, needs HTML5
                        )
                    );
                     
                    
                } else {
                    // When non-visible, most parts are skipped - Only basic information used by the Charts Config is sent
                    $data_response['charts'][] = $model;
                    
                   
                    $data_response['charts_config'][$model] = array(
                        'name' => $this->rec_plugin->get_Chart_name($model),
                        'visible' => false
                    );
                }
                   $indexdata++;
                if($indexdata>5)break;
            }

            // End charts - Do we have anything to show ? Enable the function
            if (!empty($data_response['charts']))
                $functions['charts'] = true;

           
           
           
            // Weird array processing that puts the ones in $charts_prefs first, then appends the ones which are not part of $charts_prefs at the end)
          
            $data_response['charts'] = array_merge(array_intersect($charts_prefs, $data_response['charts']), array_diff($data_response['charts'], $charts_prefs));
        
        
         
        }
        
        $this->functions = $data_response['functions'] = $functions;

  //echo(count($data_response['charts_config']));exit();

        //chart tab ->  Service Recovery bug fixed | 03032025  start
        if($data_response['rt_def']['id'] == 13){
            // unset($data_response['charts'][1]);
            // unset($data_response['charts'][2]);
            // unset($data_response['charts'][3]);
            unset($data_response['charts'][4]);
            unset($data_response['charts'][5]);

            // unset($data_response['charts_config']['meta_status']);
            // unset($data_response['charts_config']['xfield_enum_process']);
            // unset($data_response['charts_config']['xfield_set_trc_aspct']);
            unset($data_response['charts_config']['xfield_enum_tracer']);
            // unset($data_response['charts_config']['meta_status']);
        }
        //chart tab ->  Service Recovery bug fixed | 03032025 end
        // Finished, send the response 
        $this->send_JSON_Headers($data_response);
       
        $this->init_Template_System();
        $this->view("includes" . DS . "rt_info.html");
    }

    /**
     * Loads the chart configuration and data for a certain statistics model of a record type
     * - Uses the record plugin to access certain data
     * - Chart configuration and dataset are returned with the same response
     * This method also caches the data for the specified parameters and saves it using the "Statistics" model (which needs a bit enhancing)
     * - If same parameters are asked, it returns data from the statistics cache
     *
     * TODO: Allow customization of initial parameters (time period and what to show - for certain charts)
     *
     * @param MVC_Request $rq
     */
    public function action_Load_Record_Statistics_Chart(MVC_Request $rq) {
        // The statistics are calculated for logged in user (but it should support admin's Perspective as well, maybe later)
        $org_id = MVC_Core::$ss->user['org_id'];

        // Attempt to locate record type and find it's numerical ID (used for the database queries)
        $rt_id = $this->issue->ist_Locate($rq->rt_key);
        if (empty($rt_id))
            return header("HTTP/1.0 404 Request contains invalid record type");

        $this->rec_plugin = MVC_Core::factory_Plugin("record", $rq->rt_key);

        // Read statistics for organization/record type - Caching is built into the function
        $stats = $this->rec_plugin->org_Statistics($org_id, $rt_id, $rq->model, array(
            'period' => $rq->period ?: "years", // Start with month as default (Last twelve months)
            'subset' => $rq->subset ?: 0   // Start with 0 as default (subsets are numeric indexes), and they might represent specific references for the statistic functions
        ));
        if (empty($stats))
            return header("HTTP/1.0 500 Statistics could not be calculated. Cause unknown");

        // Test 2: Get configuration
        $chart = $this->rec_plugin->build_Chart_Config($rq->model, array(
            'org_id' => $org_id,
            'rt_id' => $rt_id
        ));

        $chart['source'] = $stats;

        return $this->send_JSON($chart);
    }

    /**
     * Saves the configuration for showing charts on a certain record type, per user. When done, the charts RT page is reloaded
     * @param MVC_Request $rq
     */
    public function action_Save_Charts_Config(MVC_Request $rq) {
        $r = $this->user->profile_Write(MVC_Core::$ss->user['id'], "charts_" . $rq->rt_key, explode(",", $rq->charts));
        if (!$r)
            return header("HTTP/1.0 500 Unable to save charts configuration");

        return $this->send_JSON(array('success' => true));
    }

    /**
     * The "Account" contains primary information like username, e-mail, ability to change password, active department, statistics and Two-Factor authentication
     *
     * @param MVC_Request $in
     */
    private function load_Tab_Account(MVC_Request $in) {
        $features = array();
        $datasources = array();
        //print_r('ass');
//print_r($_SESSION['organization']['type_id']);
        //echo '<pre>' . print_r($_SESSION, TRUE) . '</pre>';
        // Special ability for "jumper" users (support accounts, which can "jump" to other organizations) - Read info about the organizations they can jump to (profile key "cynosural")
        if (MVC_Core::$ss->user['status'] == 'jumper') {
            $cynos = $this->user->profile_Read(MVC_Core::$ss->user['id'], "cynosural"); // this has to be entered in the database directly, it cannot be changed in the interface

            $datasources['jump_orgs'] = $this->org->get_List(array('orgtype'), function (QueryFactory &$q) use ($cynos) {
                $q->where("`org`.id IN (?)", $cynos);
            });

            $features['update_membership'] = true;
            $features['org_selector'] = true;
        }

        $features['twofa'] = !empty(MVC_Core::$ss->user['oath_turnon']) ? MVC_Core::$ss->user['oath_turnon'] : false;

        // Template vars
        $this->_out['session'] = &MVC_Core::$ss;
        $this->features = $features;

        // Hybrid response (HTML+JSON)
        $this->send_JSON_Headers(array(
            'features' => $features,
            'user' => array(
                'id' => (int) MVC_Core::$ss->user['id'],
                'org_id' => (int) MVC_Core::$ss->user['org_id'],
                'cdep_id' => (int) MVC_Core::$ss->user['cdep_id'],
                'email' => MVC_Core::$ss->user['email']
            ),
            'dataSources' => $datasources // => self.config.dataSources
        ));
    }

    /**
     * Reads the list of departments this user is a member of, for the department selector source - Used in My Account tab
     * Might be returning empty array if the user is not a member of any department in the specified organization
     *
     * @param MVC_Core $request
     */
    public function action_Get_User_Deps(MVC_Request $rq) {
        $org_id = $rq->org_id ?: MVC_Core::$ss->user['org_id'];

        // Read the list of departments the user is a member of, for the active department selector
        $m_dep = new App_Model_Department();
        $deps = $m_dep->get_User_Dep_Relation_List(MVC_Core::$ss->user['id'], $org_id, false); // false = quick mode (id, name)
        // In case no departements were returned, it means that that the user is not member of any yet - nothing to select, do not show the department selector
        if (empty($deps))
            return $this->send_JSON(array());

        $user_deps = array();
        foreach ($deps as $dep) {
            $user_deps[] = array(
                'id' => $dep['id'],
                'name' => $dep['name'],
                'disabled' => $dep['disabled']
            );
        }

        $this->send_JSON($user_deps);
    }

    /**
     * Loads user's contact information - This is synchronized in JavaScript with the "My account" tab (change of name and e-mail happens in both places)
     *
     * @param MVC_Request $in
     */
    private function load_Tab_Vcard(MVC_Request $in) {
        $contact = $this->con->read(MVC_Core::$ss->user['contact_id']);
        if (!$contact) // Not found... WTF!?
            return;

        // Prepare the contact information
        $contact['xdata'] = $this->con->decode_Xdata($contact['xdata']);
        // This tab is always in "edit" mode
        $contact['_edit'] = true;
        $this->contact = $contact;

        // Load SMTP/SPF settings (or prepare defaults based on the $contact)
        $smtp_server = $this->con->server_Read($contact['id']);
        if (empty($smtp_server)) {
            list($mailbox, $domain) = explode('@', $contact['email']);

            // TODO: Defaults (but "tested" is not enabled, the defaults will only show if the user actually clicks on "Yes")
            // TODO: Guess based on E-mail and a "guess" config file
            // If nothing, just default to: smtp.(user's domain),TLS/587/user's e-mail as "username"
            $smtp_server = array(
                'method' => "local",
                'tested' => 0,
                'host' => "smtp." . $domain,
                'smtp_encryption' => "tls",
                'port' => 587,
                'smtp_auth' => "login",
                'username' => $contact['email'],
            );
        }

        $this->smtp = $smtp_server;

        // Sends to the frontend the fact that the contact has loaded correctly and the contact editor can be initialized
        $data_response = array();
        $data_response['tab_vcard'] = array(
            'ready' => true,
            'send_mtd' => $smtp_server['method'],
            'smtp_active' => (int) $smtp_server['tested'] == 1
        );

        // Hybrid response (HTML+JSON)
        $this->send_JSON_Headers($data_response);
    }

    /**
     * Loads and prepares HTML document which will become the content of one of the tabs for the User Overview module
     * -- Loads specific data on demand, for the dynamic content in that tab.
     *
     * @param MVC_Request $in
     */
    public function action_Load_Tab_HTML(MVC_Request $in) {
        // Group depenent data which might be needed for the template (nothing yet)
        switch ($in->tab) {
            // Dashboard/Cockpit/Bridge
            case 'tab_dashboard':
                /* Dayakar code start */
                //$this-> load_Tab_Dashboard($in);
                $this->load_Tab_Dashboard_cockpit($in);
                $m_cms = new App_Model_Issue();
                $this->rec_iss = $m_cms->get_rec_list();
                /* Dayakar code end */
                break;
            // Statistics tab, lists all available modules in the system, and for those that are enabled charts are being displayed with organizzation statistics
            // This will most likely be moved to Organization module, or it's own independent controller
            case 'tab_modules':
                $this->load_Tab_Modules($in);
                break;

            // Account information (username, password, signature, organization, department)
            case 'tab_account':
                $this->load_Tab_Account($in);
                break;

            // Contact viewer/editor for the selected user
            case 'tab_vcard':
                $this->load_Tab_Vcard($in);
                break;

            case 'tab_acls':
                // translate the privilege set in MVC_Core::$ss-> user['acls']['privileges'], to be displayed in a list
                if ($this->user->is_Unrestricted())
                    $this->acls = array('is_admin' => true);
                else {
                    // Assamble the correct list of ACLs (privileges, access ids):
                    MVC_NLS::load_Groups("acls");
                    $acls_text = MVC_NLS::get_Object("acls", true);

                    $acls = array();
                    // Privileges (and translated text)
                    $acls['privileges'] = array();
                    foreach (MVC_Core::$ss->user['acls']['privileges'] as $privilege)
                        $acls['privileges'][$privilege] = $acls_text['privileges'][$privilege];

                    $this->acls = $acls;
                    $this->privilege_groups = $acls_text['privilege_groups'];
                }
                break;
            case 'tab_history':
                $m_cms = new App_Model_Issue();
                $this->rec_iss = $m_cms->get_rec_list();
                $this->load_Tab_History($in);
            case 'tab_updates':
                // Managed updates (this page becomes a blog system)
                $m_cms = new App_Model_CMS();
                $this->updates = $m_cms->blog_Read(function (QueryFactory &$q) {
                    $q->where("`org_id` IS NULL");
                }, null, "`dt` DESC", 25, 0);
                break;
            case 'tab_documents':
                $m_cms = new App_Model_Issue();
                $this->rec_iss = $m_cms->get_rec_list();
                $this->load_Tab_Documentation($in);
                break;
        }

        // This is an AJAX request, but needs the template as it's a HTML response (not JSON or XML), so, initialize the template object on demand
        $this->init_Template_System();
        $this->view("user" . DS . "overview_tabs" . DS . $in->tab . ".html", $this->_out);
    }

    /**
     * Updates the active logged in user's personal data
     */
    public function action_Update_Account(MVC_Request $post) {
        // If 2FA is active and the OTP value is missing from the form, return a special trigger for the Javascript so the pin is asked.
        // The form will then be resubmitted with the complete data, in which case the OTP is checked against the user's key
        if (MVC_Core::$ss->user['oath_turnon'] && !empty($post->usr['password'])) {
            if (!$post->otp)
                return $this->send_JSON(array('twofa' => true));
            else {
                $otp_valid = $this->user->test_2FA_OTP($post->otp);
                if (!$otp_valid)
                    return $this->send_Fail_JSON("One-Time Password invalid. Please try again or contact support");
            }
        }

        $account = MVC_Core::$ss->user;
        $new_account_data = $post->usr;
        $new_account_data['user'] = $account['user']; // Necessary for security hash
        unset($new_account_data['name'], $new_account_data['email']);
// Disabled, not possible to change base account info using this interface (signature and password can still be changed)
        /*

          $new_account_data['name'] = trim($new_account_data['name']);
          $new_account_data['email'] = trim($new_account_data['email']);

          // Create or Update the associated contact with information from $post-> usr (name, email)
          if(!$account['contact_id']) {
          // There is no associated contact, create one.
          $con_iid = $this-> con-> create('user_self', $account['org_id'], array(
          'name'=> $new_account_data['name'],
          'email'=> $new_account_data['email']
          ), $account['id']);

          if(!$con_iid)
          return $this-> send_Fail_JSON("Unable to create the associated contact information for this user.");

          $account['contact_id'] = $con_iid;
          } else {
          // The associated contact exists, so update it
          $r = $this-> con-> update($account['contact_id'], array(
          'name'=> $new_account_data['name'],
          'email'=> $new_account_data['email']
          ));

          if(!$r)
          return $this-> send_Fail_JSON("Unable to update the associated contact information for this user.");
          }
         */

        $res = $this->user->update(MVC_Core::$ss->user['id'], $new_account_data);
        if ($res) {
            $this->evan->add("Your account information has been updated.", "ok", array(
                'permanent' => true,
                'timeout' => time() + 30
            ));

            // update the user's data in the session as well before returning success, so when reloading the page the updated name is displayed.
// 			MVC_Core::$ss-> user['name'] = $new_account_data['name'];
// 			MVC_Core::$ss-> user['email'] = $new_account_data['email'];
            MVC_Core::$ss->user['signature'] = $new_account_data['signature'];

            $this->send_JSON(array('success' => true));
        } else
            $this->send_Fail_JSON("There was an error in updating your account.");
    }

    /**
     * Updates the active user's membership ( department and for certain users, the organization ID itself )
     */
    public function action_Update_Membership(MVC_Request $post) {
        $new_account_data = $post->usr;
        if (empty($new_account_data['cdep_id']))
            $new_account_data['cdep_id'] = 0;

        if (MVC_Core::$ss->user['status'] == 'jumper') {
            // Only "Jumpers" have the ability to move to different organizations by the use of this action.
            if ($new_account_data['org_id'] == MVC_Core::$ss->user['org_id'] && $new_account_data['cdep_id'] == MVC_Core::$ss->user['cdep_id'])
                return header("HTTP/1.0 400 No change in organization or department, nothing to do");
        } else {
            // Just in case there's a "hacking" attempt
            unset($new_account_data['org_id']);
            if ($new_account_data['cdep_id'] == MVC_Core::$ss->user['cdep_id'])
                return header("HTTP/1.0 400 No change in active department, nothing to do");
        }

        // TODO: At this point it would be good to verify again if the user is actually a member of that organization/department and that his department membership is not disabled
        $res = $this->user->update(MVC_Core::$ss->user['id'], $new_account_data);
        if (empty($res))
            return header("HTTP/1.0 500 There was an error while updating your membership");

        $this->evan->add("Your membership has been changed and you will be automatically logged out.", "ok");
        $this->send_JSON(array('success' => true));
    }

    /**
     * Reads the groups that the user has access to and personal preferences related to each one of them (if they exist, or empty data)
     */
    public function action_Get_User_Groups() {
        // Quick fix for admin - Does not see groups
        if ($this->user->is_Unrestricted())
            return $this->send_JSON(array());

        // TODO: Later, all existing groups should be displayed here, allowing the admin to be notified as well... ?

        $group_list = $this->user->user2group_Get_List(MVC_Core::$ss->user['id'], array_keys(MVC_Core::$ss->user['acls']['groups']));

        // because of some bugs in the grid system related to checkbox column, initialize all "missing" values
        foreach ($group_list as &$row) {
            if (empty($row['preferences']))
                $row['preferences'] = array(
                    'ntf_new' => false,
                    'ntf_trans' => false,
                    'ntf_share' => false,
                    'ntf_clos' => false,
                    'hidden' => false
                );
            else { // preferences not empty, but some values are missing (database content bug)
                if (!is_array($row['preferences']))
                    $row['preferences'] = array();
                if (!isset($row['preferences']['ntf_new']))
                    $row['preferences']['ntf_new'] = false;
                if (!isset($row['preferences']['ntf_trans']))
                    $row['preferences']['ntf_trans'] = false;
                if (!isset($row['preferences']['ntf_share']))
                    $row['preferences']['ntf_share'] = false;
                if (!isset($row['preferences']['ntf_clos']))
                    $row['preferences']['ntf_clos'] = false;
                if (!isset($row['preferences']['hidden']))
                    $row['preferences']['hidden'] = false;
            }
        }

        if ($group_list)
            $this->send_JSON($group_list);
        else
            $this->send_JSON(array());
    }

    /**
     * Reads the list of notifications for the logged in user, and returns the result as grid data.
     * -- Used to display and search through all notifications.
     * -- By default, sorted by date, newer first
     */
    public function action_Get_Notifications(MVC_Request $in) {
        // 1. Server-side Filters

        list($ss_filters, $filter_callback) = self::helper_Grid_Filters($in);
        // 2. Server-side Sort
        if ($in->sortdatafield && $in->sortorder) {
            // Determine the database field used for sorting
// 			if($in-> sortdatafield == 'org_name')
// 				$ss_sort = "`org`.`name` ";
// 			elseif($in-> sortdatafield == 'company_name')
// 			$ss_sort = null; // Sorting should not be possible here
// 			else
// 				$ss_sort = "`ctx`.`{$in-> sortdatafield}` ";
            $ss_sort = "`evn`.`{$in->sortdatafield}` " . $in->sortorder;
        } else {
            $ss_sort = "`evn`.`time` DESC"; // default sorting by date, descending
        }

        $notifications = $this->evan->get_List(array('notification'), function (QueryFactory $q) use ($ss_filters, $filter_callback) {
            $q->where("`evn`.`user_id` = ?", MVC_Core::$ss->user['id']);
            foreach ($ss_filters as $fkey => $filter_set) {
                if ($fkey == 'xdata_type') {
                    $dbfield = "`evn`.`xdata` ";
                    $filter_set[0]['c'] = 'CONTAINS';
                        $filter_set[0]['v'] = '"' . $filter_set[0]['v'] . '"';
                        $filter_callback($q, $filter_set, $dbfield);
                    
                } else {
                    $dbfield = "`evn`.`{$fkey}` ";
                    $filter_callback($q, $filter_set, $dbfield);
                }
            }
        }, null, $ss_sort, $in->pagesize, $in->pagenum * $in->pagesize, true); // true = calc found rows (for server side paging)

        if (empty($notifications))
            return $this->send_JSON(array()); // empty result ???
        // Filter out some data which is not needed by the grid itself
        foreach ($notifications as &$item)
            unset($item['user_id'], $item['description']);

        $this->send_JSON(array(
            "found_rows" => $this->evan->found_rows,
            "data" => $notifications
        ));
    }

    /**
     * Generates a full-feature HTML version of a notification.
     * -- Also updates status to "read" if it was "new" before. Ignores it otherwise
     *
     * @param MVC_Request $in
     */
    public function action_Read_Notification(MVC_Request $in) {
        if (!isset($in->id)) {
            echo "Error: Notification ID missing from the request!";
            return;
        }

        // Attempt to read the notification
        $ntf = $this->evan->read($in->id, array('notification', 'rel_item'));

        if ($ntf['status'] == 'new') {
            $this->evan->update($in->id, 'read');
            // Trigger the re-reading of notifications by the heart-beat by setting "check_evn" in user's session
            MVC_Core::$ss->user['check_evn'] = true;
        }

        if (empty($ntf['description'])) {
            $ntf['description'] = "";
            // When there is no stored description, build one depending on notification type - Note: This is temporary, templates should be used instead.
            switch ($ntf['type']) {
                case 'issue':
                    $ntf['description'] .= "Type: {$ntf['xdata']['type']}\n";
                    $ntf['description'] .= "Primary data: {$ntf['xdata']['primary']}\n";
                    $ntf['description'] .= "Secondary data: {$ntf['xdata']['secondary']}";
                    break;
            }
        }

        $this->ntf = $ntf;

        // This is an ajax request, but with a HTML response, so initialize the template object on demand
        $this->init_Template_System();
        $this->view("includes" . DS . "notification.html");
    }

    /**
     * Dismisses or achives a notification
     *
     * @param MVC_Request $post
     */
    public function action_Dismiss_Notification(MVC_Request $post) {


        if (!isset($post->id))
            return $this->send_Fail_JSON("Notification ID not present in the request.");

        // Attempt to read the notification to check it's current status
        $ntf = $this->evan->read($post->id, array('notification'));
        if (!$ntf)
            return $this->send_Fail_JSON("Notification not found (Possible database error).");

        $new_status = isset($post->hideit) ? 'archived' : 'dismissed';
        if ($new_status == 'archived' ? $ntf['status'] != 'archived' : ($ntf['status'] != 'archived' && $ntf['status'] != 'dismissed')) {
            $this->evan->update($post->id, $new_status);

            // Trigger the re-reading of notifications by the heart-beat by setting "check_evn" in user's session
            MVC_Core::$ss->user['check_evn'] = true;
        } else
            return $this->send_Fail_JSON("This notification has already been {$ntf['status']} !");

        return $this->send_JSON(array('success' => true, 'response' => "The notification has been dismissed."));
    }

    public function action_Archive_Notification(MVC_Request $post) {


        if (!isset($post->id))
            return $this->send_Fail_JSON("Notification ID not present in the request.");

        // Attempt to read the notification to check it's current status
        $ntf = $this->evan->read($post->id, array('notification'));
        if (!$ntf)
            return $this->send_Fail_JSON("Notification not found (Possible database error).");

        $new_status = 'archived';
        if ($new_status == 'archived') {
            $this->evan->update($post->id, $new_status);

            // Trigger the re-reading of notifications by the heart-beat by setting "check_evn" in user's session
            MVC_Core::$ss->user['check_evn'] = true;
        }
        return $this->send_JSON(array('success' => true, 'response' => "The notification has been Archived."));
    }

    /**
     * Reads the related data for a notification ( if it exists ) and sends it via JSON - Used to open or process the item
     *
     * @param MVC_Request $in
     */
    public function action_Read_Ntf_Related(MVC_Request $in) {
        if (!isset($in->id))
            return $this->send_Fail_JSON("Notification ID not present in the request.");

        $ntf = $this->evan->read($in->id, array('notification', 'rel_item'));
        if (!$ntf)
            return $this->send_Fail_JSON("Notification not found (Possible database error).");

        return $this->send_JSON(array('success' => true, 'item' => $ntf['item']));
    }

    /**
     * Changes the status of all New and Read notifications belonging to the logged in user to "dismissed"
     *
     * @param MVC_Request $post
     */
    public function action_Dismiss_All_Notifications(MVC_Request $post) {
        if (!isset(MVC_Core::$ss->user))
            $this->send_Fail_JSON("This function is available for user accounts only.");

        $count = $this->evan->update_Bulk(MVC_Core::$ss->user['id'], function (QueryFactory &$q) {
            $q->where('`status` IN(?)', array('new', 'read'))
                    ->values(array('status' => "dismissed"));
        });

        if ($count > 0) {
            $this->evan->reset();
            $this->evan->add("{$count} notifications have been dismissed", 'ok', array(
                'permanent' => true,
                'timeout' => time() + 15
            ));
            $this->send_JSON(array('success' => true));
        } else
            $this->send_Fail_JSON("No notifications to dismiss or an error has occured.");
    }

    /**
     * Saves the group settings
     * (a whole bunch of checkboxes in a N2N relationship between users and groups)
     */
    public function action_Save_Groups_Settings(MVC_Request $post) {
        $settings_data = @json_decode($post->data, true);
        if ($settings_data) {

            foreach ($settings_data as $group_id => $data) {
                // extract preferences
                $preferences = array();
                foreach ($data as $key => $value) {
                    if (substr($key, 0, 5) == 'pref_')
                        $preferences[substr($key, 5)] = $value;
                }
                // create or update preferences
                $this->user->user2group_Write(MVC_Core::$ss->user['id'], $group_id, array(
                    'preferences' => $preferences
                ));
            }

            $this->send_JSON(array('success' => true));
        } else {
            $this->send_Fail_JSON("Nothing to save !");
        }
    }

    /**
     * Removes the list of recently viewed issues from the profile memory
     * @param MVC_Request $post
     */
    public function action_Issues_Recent_Clear(MVC_Request $post) {
        $this->user->profile_Write(MVC_Core::$ss->user['id'], "iss_recent");
        $this->send_JSON(array('success' => true));
    }

    /**
     * Removes one element from the recent issues list and updates the profile memory
     * @param MVC_Request $post
     */
    public function action_Issues_Recent_Remove_Item(MVC_Request $post) {
        // Load the user profile containing the issue open history
        $recent = $this->user->profile_Read(MVC_Core::$ss->user['id'], "iss_recent", false, 1, true);
        if (empty($recent))
            return $this->send_Fail_JSON("The recent issues list is already empty !");

        // go through the profile data and find the track id. If found, it is removed from the list and the list is saved. If not, return an error
        $pos = 0;
        $found = false;
        foreach ($recent as $track => $data) {
            if ($track == $post->track) {
                // Found it !
                unset($recent[$track]);
                $found = true;
                break;
            }
            $pos++;
        }
        if (!$found)
            return $this->send_Fail_JSON("Track ID {$post->track} not found in the recent list ?!");

        if (count($recent)) {
            // Save it back to the user profile record
            $first = reset($recent);
            $this->user->profile_Write(MVC_Core::$ss->user['id'], "iss_recent", $recent, false, sprintf("Recently opened issues (Most recent time: %s)", date("Y-m-d H:i:s", $first['time'])));
        } else
        // If no more left, just remove the record itself
            $this->user->profile_Write(MVC_Core::$ss->user['id'], "iss_recent");

        $this->send_JSON(array('success' => true));
    }

    /**
     * Checks if the entered code for Two-Factor authentication matches the calculated value on the server.
     * If yes, saves the OathKey in the user account which enables 2FA.
     *
     * @param MVC_Request $post
     */
    public function action_Check_2FA(MVC_Request $post) {
        if (!$post->oathkey || strlen($post->oathkey) != 16 || !$post->otp || strlen($post->otp) != 6)
            return $this->send_Fail_JSON("Invalid parameters for 2FA activation request.");

        GA2FA::initialize();
        $valid = GA2FA::verify_key($post->oathkey, $post->otp);

        if (!$valid)
            return $this->send_Fail_JSON("The PIN is invalid. In case you keep getting this error please contact support.");

        // PIN Valid ! Activate 2FA by storing the oathkey into the user account. At next login the user will be asked to enter the PIN
        MVC_Core::$ss->user['oath_key'] = $post->oathkey;
        MVC_Core::$ss->user['oath_turnon'] = date("Y-m-d H:i:s");
        $this->user->update(MVC_Core::$ss->user['id'], array(
            'oath_key' => $post->oathkey,
            'oath_turnon' => MVC_Core::$ss->user['oath_turnon']
        ));

        $this->evan->add("Two-Factor Authentication has been added to your account !", "2fa", array(
            'permanent' => true,
            'timeout' => time() + 60
        ));
        $this->send_JSON(array('success' => true));
    }

    /**
     * Test action for the new notification system.
     */
    public function action_Self_Notify() {
        $ntf_id = $this->evan->create(MVC_Core::$ss->user['id'], 'system', 'test', "Self notification test", null, null, null, array("hello" => "World!"), "Lorem ipsum dolor sic transit gloria mundi");

        if (empty($ntf_id))
            return $this->send_Fail_JSON("Unable to create notification");

        $msg_id = $this->evan->generate_Message($ntf_id, true); // True = Send by mail

        if (empty($msg_id))
            return $this->send_Fail_JSON("Unable to send myself the message ;)");

        $this->send_JSON(array('success' => true, 'response' => "It Worked. Yey!"));
    }

    /**
     * Test action for the new notification system.
     */
    public function action_Last_As_Mail() {
        $ntfs = $this->evan->get_List(array(), function (QueryFactory $q) {
            $q->where("`evn`.`user_id` = ?", MVC_Core::$ss->user['id'])
                    ->limit(1);
        }, array(), "`evn`.`time` DESC", 1);

        $ntf_id = @$ntfs[0]['id'];

        if (empty($ntf_id))
            return $this->send_Fail_JSON("Unable to find notification");

        $msg_id = $this->evan->generate_Message($ntf_id, true); // True = Send by mail

        if (empty($msg_id))
            return $this->send_Fail_JSON("Unable to send myself the message ;)");

        $this->send_JSON(array('success' => true, 'response' => "It Worked. Yey!"));
    }

//        Two factor off from user account Start
    public function action_Off_2fa(MVC_Request $post) {
        $this->user->update(MVC_Core::$ss->user['id'], array(
            'oath_key' => "",
            'oath_turnon' => null
        ));
        $this->send_JSON(array('success' => true));
    }

//        Two factor off from user account End




    public function load_Tab_Documentation(MVC_Request $in) {
        // Dashboard tab needs data from the session and also checks privileges
        $this->current_rt = MVC_Core::$cache->current_rt;

        $datasources = array();
        $recent_records = $this->user->getPublishDocuments(MVC_Core::$ss->user['id'], "iss_recent", false, 1, true, $search = '');

//        echo "<pre>";
//        print_r($recent_records);

        $datasources['recent'] = array();
        if (!empty($recent_records)) {
            foreach ($recent_records as $track => $data) {
                $rt_info = &MVC_Core::$cache->record_types[MVC_Core::$cache->record_types_index[$data['ist']]];
                $datasources['recent'][] = array_merge($data, array(
                    'track' => $track,
                    'rt_key' => $rt_info['key'],
                    'rt_name' => $rt_info['name']
                ));
            }
        }
        $this->recent_issues_count = count($recent_records);

        $get_user_details = $this->user->get_user_details(MVC_Core::$ss->user['id']);
        if (MVC_Core::$ss->user['status'] == 'admin') {
            $getDepartmentsByUserId = $this->dep->get_List(MVC_Core::$ss->user['org_id'], array());
        } else if ($get_user_details['publish_all_linked_department'] == '1') {
            $getDepartmentsByUserId = $this->user->get_publish_all_linked_department_ids_List(MVC_Core::$ss->user['id']);
        } else {
            $getDepartmentsByUserId = $this->user->getDepartmentsByUserId(MVC_Core::$ss->user['id']);
        }
//        
//echo "<pre>";
//print_r($get_user_details);
        $assign_departments_list = '';
        foreach ($getDepartmentsByUserId as $d_row) {
            $selected = $get_user_details['cdep_id'] == $d_row['id'] ? 'selected' : '';
//            print_r($d_row);
//            $assign_departments_list .='<option value="'.$d_row['dep_id'].'">'.$d_row['name'].'</option>';
            $assign_departments_list .= '<option value="' . $d_row['id'] . '" ' . $selected . ' >' . $d_row['name'] . '</option>';
//             print_r($d_row);
        }


        $this->assign_departments_list = $assign_departments_list;

        // Read the Mini-Notepad data
        $this->mini_notepad = htmlspecialchars($this->user->profile_Read(MVC_Core::$ss->user['id'], "mini_notepad"));

        // Read list of issues for the "Recent Issues" panel.
        $this->prepare_Template_ACLs();

        // User elements count
        $this->count = MVC_Core::$ss->user['count'];

        // Hybrid response (HTML+JSON)
        if (!empty($datasources))
            $this->send_JSON_Headers(array(
                'dataSources' => $datasources
            ));
    }

    public function action_get_Documentation_By_Search(MVC_Request $post) {

//        print_r($_POST);
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

//        exit();
        $this->current_rt = MVC_Core::$cache->current_rt;

        $datasources = array();
        // Recently opened records - Add ist information (key, name)
//        echo MVC_Core::$ss->user['id'];
        $recent_records = $this->user->getPublishDocuments(MVC_Core::$ss->user['id'], "iss_recent", false, 1, true, $searchData);

//        echo "<pre>";
//        print_r($recent_records);

        $datasources['recent'] = array();
        if (!empty($recent_records)) {
            foreach ($recent_records as $track => $data) {
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

//        echo "<pre>";
//        print_r($datasources);
        $this->send_JSON($datasources);
        // Hybrid response (HTML+JSON)
//        if (!empty($datasources))
//            $this->send_JSON_Headers(array(
//                'dataSources' => $datasources
//            ));
    }

    /**
     * Export the chart data and data for a certain statistics model of a record type
     * - Uses the record plugin to access certain data
     * - Chart configuration and dataset are returned with the same response
     * This method also caches the data for the specified parameters and saves it using the "Statistics" model (which needs a bit enhancing)
     * - If same parameters are asked, it returns data from the statistics cache
     *
     * TODO: Allow customization of initial parameters (time period and what to show - for certain charts)
     *
     * @param MVC_Request $rq
     */
    public function action_Export_Record_Statistics_Chart(MVC_Request $rq) {
        // The statistics are calculated for logged in user (but it should support admin's Perspective as well, maybe later)
        $org_id = MVC_Core::$ss->user['org_id'];

        // Attempt to locate record type and find it's numerical ID (used for the database queries)
        $rt_id = $this->issue->ist_Locate($rq->rt_key);
        if (empty($rt_id))
            return header("HTTP/1.0 404 Request contains invalid record type");

        $this->rec_plugin = MVC_Core::factory_Plugin("record", $rq->rt_key);

        // Read statistics for organization/record type - Caching is built into the function
        $exports = $this->rec_plugin->export_org_Statistics($org_id, $rt_id, $rq->model, array(
            'period' => $rq->period ?: "years", // Start with month as default (Last twelve months)
            'subset' => $rq->subset ?: 0   // Start with 0 as default (subsets are numeric indexes), and they might represent specific references for the statistic functions
        ));

        if (empty($exports))
            return header("HTTP/1.0 500 Statistics could not be calculated. Cause unknown");

        // Test 2: Get configuration
        $chart = $this->rec_plugin->build_Chart_Config($rq->model, array(
            'org_id' => $org_id,
            'rt_id' => $rt_id
        ));

        $chart['source'] = $exports;

        return $this->send_JSON($chart);
    }

    public function action_Export_Record_Statistics_Chart_print(MVC_Request $rq) {
        $org_id = MVC_Core::$ss->user['org_id'];

        // Attempt to locate record type and find it's numerical ID (used for the database queries)
        $rt_id = $this->issue->ist_Locate($rq->rt_key);
        if (empty($rt_id))
            return header("HTTP/1.0 404 Request contains invalid record type");

        $this->rec_plugin = MVC_Core::factory_Plugin("record", $rq->rt_key);

        // Read statistics for organization/record type - Caching is built into the function
        $exports = $this->rec_plugin->export_org_Statistics($org_id, $rt_id, $rq->model, array(
            'period' => $rq->period ?: "years", // Start with month as default (Last twelve months)
            'subset' => $rq->subset ?: 0,   // Start with 0 as default (subsets are numeric indexes), and they might represent specific references for the statistic functions
            'page_for'=>'claim_chart' // IWI Aircargoclaim chart page update 12012026
        ));

        if (empty($exports)) {

            echo '<html><head><title>No Provision Record Found</title></head><body><style>.button {
  background-color: blue;border: none;color: white;padding: 15px 32px;text-align: center;text-decoration: none;display: inline-block;font-size: 16px;}</style>
<div style="padding: 10px; marging: 0 auto; border: 3px solid;width:630px;border-style: dashed;border-color:blue;"><h1>No Provision Record Found For This Account</h1></div><br>
<input type="button" id="cancel_edit" class="button" value="Close" onclick="javascript:window.close();"></input></body></html>';
            return header("HTTP/1.0 500 Statistics could not be calculated. Cause unknown");
        }

        // Test 2: Get configuration
        $chart = $this->rec_plugin->build_Chart_Config($rq->model, array(
            'org_id' => $org_id,
            'rt_id' => $rt_id
        ));

        $chart['source'] = $exports;

        ob_start();

        $response = $chart;
        ?>
        <head>
            <meta charset="utf-8">
            <title>Air Claims-Total Provision In USD </title>
            <style>
                .Rrecords-list table tr:nth-child(even) {
                    background: #f5f5f5;
                }

                .Rrecords-list table tr th {
                    padding: 5px;
                    font-size: 13px;
                }

                .Rrecords-list table tr td {
                    padding: 5px;
                    font-size: 14px;
                }

                .print {
                    position: absolute;
                    right: 40px;
                }
            </style>
        </head>
        <body>
            <div class="print">
                <button onclick="window.print()">
                    <img src="/img/icons16/printer.png" alt="Print">
                </button>
            </div>
            <h2 style="text-align: center;margin-bottom: 6px;font-size: 24px;"><?= $response['title'] ?></h2>
            <h3 style="text-align: center;margin-top: 0;color: #1174bd;font-weight: 500;font-size: 18px;text-transform: uppercase;">(All Active Provisions Till Date)</h3>
            <div class="Rrecords-list" style="width: 90%;margin: 0 auto;">



        <?php
        $total_prov = 0;
        $tol_pro_cur = 0;

        foreach ($response['source'] as $periodKey => $periodValue) {

            foreach ($periodValue as $periodDataKey => $periodDataValue) {
                ?>	   



                        <table border="1" style="width: 100%;margin-bottom: 20px;border-collapse: collapse;">
                            <tbody>
                                <tr>
                                    <th colspan="12" style="background: #cce9ff;padding: 5px;"><?= $periodDataValue['label'] ?></th>
                                </tr>
                                <tr style="background: #dddddd;">
                                    <th> Tracking ID </th>
                                    <th> AWB No. </th>
                                    <th>Claim in USD</th>
                                    <th>Provision in USD</th>
                                    <th>Claim received date</th>
                                    <th>Formal claim</th>
                                    <th>Nature of Goods</th>
                                    <th>Shipper</th>
                                    <th>Status</th>
                                </tr>

                        <?php foreach ($periodDataValue['records'] as $rowRecord => $record) { ?>	
                                    <tr>
                                        <td style="width:15%">
                                            <a href="/record/process:<?= $record['track'] ?>" target="_blank"><?= $record['track'] ?></a>
                                        </td>
                                        <td style="width:15%"><?= $record['cache_primary'] ?></td>
                                        <td style="width:12%"><?= number_format($record['clm_amt_usd'], 2) ?></td>
                                        <td style="width:12%"><?= number_format($record['value'], 2) ?></td>
                                        <td style="width:12%"><?= $record['Claim_receive_date'] ?></td>
                                        <td style="width:12%"><?= $record['formal_claim'] ?></td>
                                        <td style="width:12%"><?= $record['nat_goods'] ?></td>
                                        <td style="width:12%"><?= $record['shipper'] ?></td>
                                        <td style="width:12%"><?= $record['stts'] ?></td>
                                    </tr>
                <?php } ?>


                <?php if ($periodDataValue['total']) { ?>
                                    <tr>
                                        <td colspan="3">
                                            <span class="total">
                                                <b>Total Provision In USD</b>
                                            </span>
                                        </td>
                                        <td colspan="1">
                                            <b><?= number_format($periodDataValue['total'], 2) ?> $</b>
                                        </td>
                                        <td colspan="3">
                                            <span class="total">
                                                <b>Total Provision Count</b>
                                            </span>
                                        </td>
                                        <td colspan="1">
                                            <b><?= count($periodDataValue['records']) ?></b>
                                        </td>
                                    </tr>
                    <?php
                    $total_prov = $total_prov + $periodDataValue['total'];
                    $tol_pro_cur = $tol_pro_cur + count($periodDataValue['records']);
                }
                ?>	
                            </tbody>
                        </table>


                            <?php }
                        } ?>

                <table border="1" style="width: 100%;margin-bottom: 20px;border-collapse: collapse;">
                    <tbody>
                        <tr style="background: #dddddd;">
                            <th> Total Active Provision </th>
                            <th> Total Active Provision in USD </th>
                        </tr>
                        <tr style="background: #fff;">
                            <th>
                                <b> <?= $tol_pro_cur ?> </b>
                            </th>
                            <th>
                                <b> <?= number_format($total_prov, 2) ?> $ </b>
                            </th>
                        </tr>
                    </tbody>
                </table>
            </div>
        </body>

                        <?php
                        $html = ob_get_contents();

                        ob_end_clean();

                        echo $html;

                        // var_dump($chart);
                        // $this->send_JSON_Headers($data_response);
                        //$this->init_Template_System();
                        //$this->view("includes" . DS . "Prov_report.html");
                        exit;
                    }

                    // 11122023 created by iwi |  start
                    public function action_Load_kpi_Record_Chart(MVC_Request $rq) {
                        // The statistics are calculated for logged in user (but it should support admin's Perspective as well, maybe later)
                        $ids = $rq->ids;

                        $startDate = str_replace('/', '-', $rq->startDate);
                        $endDate = str_replace('/', '-', $rq->endDate);

                        $fromdate = $startDate != '' ? date("Y-m-d", strtotime($startDate)) : '';
                        $todate = $endDate != '' ? date("Y-m-d", strtotime($endDate)) : '';

                        $where = ''; //' AND YEAR(FROM_UNIXTIME(`iod`.`linktime`)) = 2023';
                        if ($fromdate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iod`.`linktime`)) >= '" . $fromdate . "'";
                        }
                        if ($todate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iod`.`linktime`)) <= '" . $todate . "'";
                        }

                        //AND `iod`.process NOT IN('closed', 'hidden')  
                        $filterStatus = $rq->kpiFilterStatus;
                        if ($filterStatus == 'all') {
                            $where1 = "  ";
                        } elseif ($filterStatus == 'active') {
                            $where1 = " AND `iod`.process NOT IN('closed', 'hidden') ";
                        } elseif ($filterStatus == 'closed') {
                            $where1 = " AND `iod`.process IN('closed') ";
                        }

                        $type_id = $this->rt_Return();
                        $org_id = MVC_Core::$ss->user['org_id'];
                        $rt_id = $this->rt_Return();

                        $year = $fromdate != '' ? date("Y", strtotime($fromdate)) : date("Y");
                        // get cargoClaimAwbs data start 15122023
                        $cargoClaimAwbsSql = "SELECT *  FROM `cargoclaim_awbs` WHERE  org_id=$org_id AND cargoclaim_awbs.year=$year ORDER BY CAST(cargoclaim_awbs.month AS UNSIGNED INTEGER)";
                        $cargoClaimAwbsResult = MVC_Core::$db->query($cargoClaimAwbsSql);
                        if (!$cargoClaimAwbsResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($cargoClaimAwbsResult as $_row) {
                            $viewAwbIssueData['id'] = $_row['org_id'];
                            $viewAwbIssueData['i_year'] = $_row['year'];
                            $viewAwbIssueData['i_month'] = $_row['month'];
                            $viewAwbIssueData['awbs_issued'] = $_row['awbs'];
                            $responseVal[$_row['year'] . '-' . $_row['month']] = $viewAwbIssueData;
                        }
                        // get cargoClaimAwbs data end 15122023
                        // getPreliminaryFormalClaimSql | start
                        $getPreliminaryFormalClaimSql = "SELECT COUNT(*) AS `y_axis`,  CONCAT(YEAR(FROM_UNIXTIME(`iod`.`linktime`)), '-', MONTH(FROM_UNIXTIME(`iod`.`linktime`))) as 'x_axis',   
            MONTHNAME(FROM_UNIXTIME(`iod`.`linktime`)) as 'x_axis_month'            
            FROM `issues`AS iss    
            LEFT JOIN `issue_orgdata`AS iod ON `iod`.issue_id=`iss`.id                        
            LEFT JOIN `issue_fields`AS ixf ON(`ixf`.issue_id=`iss`.id AND `ixf`.fk_id=65)                        
            WHERE `iss`.type_id=$type_id AND `iod`.org_id=$org_id AND `ixf`.value IN('fpr', 'prel')           
            $where  $where1   
            GROUP BY MONTH(FROM_UNIXTIME(`iod`.`linktime`))";
                        $getPreliminaryFormalClaimResult = MVC_Core::$db->query($getPreliminaryFormalClaimSql);
                        if (!$getPreliminaryFormalClaimResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($getPreliminaryFormalClaimResult as $row) {

                            $viewAwbIssueData['claimValue'] = $row['y_axis'];
                            $viewAwbIssueData['claimMonth'] = $row['x_axis'];
                            $viewAwbIssueData['claimMonthName'] = $row['x_axis_month'];
                            $viewAwbIssueData['awbIssue'] = !empty($responseVal[$row['x_axis']]['awbs_issued']) ? $responseVal[$row['x_axis']]['awbs_issued'] : '';

                            $output = ($viewAwbIssueData['awbIssue'] / 1000);
                            $clm = ($viewAwbIssueData['claimValue'] / $output);
                            $viewAwbIssueData['finalValue'] = round($clm, 3);
                            $awbPreliminaryFormalIssueData[] = $viewAwbIssueData;
                        }
                        foreach ($awbPreliminaryFormalIssueData as $row) {
                            $apf_y_axis[] = $row['finalValue'];
                            $apf_x_axis[] = $row['claimMonthName'];
                        }
                        // getPreliminaryFormalClaimSql | end
                        // getClaimValueSql | start
                        ######################################################################################################################
                        ######################################################################################################################
                        $getRejectClaimValueSql = "SELECT COUNT(*) AS `y_axis`,
                CONCAT(YEAR(FROM_UNIXTIME(`iod`.`linktime`)), '-', MONTH(FROM_UNIXTIME(`iod`.`linktime`))) as 'x_axis',
                MONTHNAME(FROM_UNIXTIME(`iod`.`linktime`)) as 'x_axis_month'
                FROM `issues`AS iss             
                LEFT JOIN `issue_orgdata`AS iod ON `iod`.issue_id=`iss`.id             
                LEFT JOIN `issue_fields`AS ixf ON(`ixf`.issue_id=`iss`.id AND `ixf`.fk_id=65)            
                WHERE `iss`.type_id=$type_id AND `iod`.org_id=$org_id AND `ixf`.`value`='fpr' 
                AND `iod`.status_key IN('rej','tbar')
                $where  $where1 
                GROUP BY MONTH(FROM_UNIXTIME(`iod`.`linktime`))";
                        $getRejectClaimResult = MVC_Core::$db->query($getRejectClaimValueSql);
                        if (!$getRejectClaimResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($getRejectClaimResult as $row) {
                            $viewRejectClaim['claimValue'] = $row['y_axis'];
                            $viewRejectClaim['claimMonth'] = $row['x_axis'];

                            $viewRejectClaim['awbIssue'] = !empty($responseVal[$row['x_axis']]['awbs_issued']) ? $responseVal[$row['x_axis']]['awbs_issued'] : '';

                            $output = ($viewRejectClaim['awbIssue'] / 1000);
                            $clm = ($viewRejectClaim['claimValue'] / $output);
                            $viewRejectClaim['rejectClaimValue'] = round($clm, 3);
                            $awbRejectClaimArrayData[] = $viewRejectClaim;
                        }

                        foreach ($awbRejectClaimArrayData as $row) {
                            $_rcd_y_axis[] = $row['rejectClaimValue'];
                            $_rcd_x_axis[] = $row['claimMonth'];
                        }
                        $awbRejectClaimArrayDataResult = array(
                            '_rcd_y_axis' => $_rcd_y_axis,
                            '_rcd_x_axis' => $_rcd_x_axis
                        );

                        ######################################################################################################################
                        ######################################################################################################################

                    $getClaimValueSql = "SELECT COUNT(*) AS `y_axis`,
            CONCAT(YEAR(FROM_UNIXTIME(`iod`.`linktime`)), '-', MONTH(FROM_UNIXTIME(`iod`.`linktime`))) as 'x_axis',
            MONTHNAME(FROM_UNIXTIME(`iod`.`linktime`)) as 'x_axis_month'
            FROM `issues`AS iss             
            LEFT JOIN `issue_orgdata`AS iod ON `iod`.issue_id=`iss`.id             
            LEFT JOIN `issue_fields`AS ixf ON(`ixf`.issue_id=`iss`.id AND `ixf`.fk_id=65)            
            WHERE `iss`.type_id=$type_id AND `iod`.org_id=$org_id AND `ixf`.`value`='fpr'
            $where  $where1 
            GROUP BY MONTH(FROM_UNIXTIME(`iod`.`linktime`))";
                        $getClaimValueResult = MVC_Core::$db->query($getClaimValueSql);
                        if (!$getClaimValueResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($getClaimValueResult as $row) {

                            $viewAwbIssueData['claimValue'] = $row['y_axis'];
                            $viewAwbIssueData['claimMonth'] = $row['x_axis'];
                            $viewAwbIssueData['claimMonthName'] = $row['x_axis_month'];
                            $viewAwbIssueData['awbIssue'] = !empty($responseVal[$row['x_axis']]['awbs_issued']) ? $responseVal[$row['x_axis']]['awbs_issued'] : '';

                            $output = ($viewAwbIssueData['awbIssue'] / 1000);
                            $clm = ($viewAwbIssueData['claimValue'] / $output);
                            $viewAwbIssueData['finalValue'] = round($clm, 3);

                            $awbIssueData[] = $viewAwbIssueData;
                        }

                        foreach ($awbIssueData as $row) {
                            $aid_y_axis[] = $row['finalValue'];
                            $aid_x_axis[] = $row['claimMonthName'];
                        }
                        $awbIssueResultData = array(
                            'y_axis' => $aid_y_axis,
                            'x_axis' => $aid_x_axis,
                            'rc_y_axis' => $_rcd_y_axis,
                        );
                        // getClaimValueSql | end


                        $awbPreliminaryFormalIssueResultData = array(
                            'y_axis' => $aid_y_axis,
                            'apf_y_axis' => $apf_y_axis,
                            'x_axis' => $apf_x_axis,
                            'responseVal' => $responseVal,
                                // 'responseVal_' => $responseVal_, 
                        );

                        // Total claimed amount in USD per 1000 AWBs | start

                        $getTotalClaimedAmountSql = "SELECT SUM(ixf.value) AS `y_axis`,
            CONCAT(YEAR(FROM_UNIXTIME(`iod`.`linktime`)), '-',MONTH(FROM_UNIXTIME(`iod`.`linktime`))) as 'x_axis',
            MONTHNAME(FROM_UNIXTIME(`iod`.`linktime`)) as 'x_axis_month'   
            FROM `issues`AS iss
            LEFT JOIN `issue_orgdata`AS iod ON `iod`.issue_id=`iss`.id
            LEFT JOIN `issue_fields`AS ixf ON(`ixf`.issue_id=`iss`.id AND `ixf`.fk_id=1729)
            WHERE `iss`.type_id=$type_id AND `iod`.org_id= $org_id          
            $where  $where1 
            GROUP BY MONTH(FROM_UNIXTIME(`iod`.`linktime`))";

                        $getTotalClaimedAmountResult = MVC_Core::$db->query($getTotalClaimedAmountSql);
                        if (!$getTotalClaimedAmountResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($getTotalClaimedAmountResult as $_row) {

                            $viewTotalClaimedAmount['totalClaimValue'] = $_row['y_axis'];
                            $viewTotalClaimedAmount['totalClaimMonth'] = $_row['x_axis'];
                            $viewTotalClaimedAmount['totalClaimMonthName'] = $_row['x_axis_month'];
                            $viewTotalClaimedAmount['awbIssue'] = !empty($responseVal[$_row['x_axis']]['awbs_issued']) ? $responseVal[$_row['x_axis']]['awbs_issued'] : '';

                            $output = ($viewTotalClaimedAmount['awbIssue'] / 1000);
                            $clm = ($viewTotalClaimedAmount['totalClaimValue'] / $output);
                            $viewTotalClaimedAmount['finaltotalClaimValue'] = round($clm, 3);
                            $awbTotalClaimedAmountData[] = $viewTotalClaimedAmount;
                        }
                        foreach ($awbTotalClaimedAmountData as $row) {
                            $tca_y_axis[] = $row['finaltotalClaimValue'];
                            $tca_x_axis[] = $row['totalClaimMonthName'];
                        }
                        $awbTotalClaimedAmountResultData = array(
                            'y_axis' => $tca_y_axis,
                            'x_axis' => $tca_x_axis
                        );

                        // Total claimed amount in USD per 1000 AWBs | end
                        // Number of records with claimed amount greater than > 5,000USD; > 10,000USD; 25,000USD and 50,000USD | start

                        $getNoOfRecordClaimedAmountSql = "SELECT ROUND((ixf.value),3) AS `y_axis`,
            CONCAT(YEAR(FROM_UNIXTIME(`iod`.`linktime`)), '-',MONTH(FROM_UNIXTIME(`iod`.`linktime`))) as 'x_axis',
            MONTHNAME(FROM_UNIXTIME(`iod`.`linktime`)) as 'x_axis_month'         
            FROM `issues`AS iss
            LEFT JOIN `issue_orgdata`AS iod ON `iod`.issue_id=`iss`.id
            LEFT JOIN `issue_fields`AS ixf ON(`ixf`.issue_id=`iss`.id AND `ixf`.fk_id=1729)
            WHERE `iss`.type_id=$type_id AND `iod`.org_id=$org_id          
            $where  $where1 
            AND ixf.value > 0";

                        $getNoOfRecordClaimedAmountResult = MVC_Core::$db->query($getNoOfRecordClaimedAmountSql);
                        if (!$getNoOfRecordClaimedAmountResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($getNoOfRecordClaimedAmountResult as $_row) {
                            $noOfRecordClaimedAmount['totalClaimValue'] = $_row['y_axis'];
                            $noOfRecordClaimedAmount['x_axis'] = $_row['x_axis'];
                            $noOfRecordClaimedAmount['x_axis_month'] = $_row['x_axis_month'];
                            $noOfRecordClaimedAmountLable = '';
                            // $noOfRecordClaimedAmountLable = 'ClaimValue5000';
                            if ($noOfRecordClaimedAmount['totalClaimValue'] <= 5000) {
                                continue;
                            }

                            if ($noOfRecordClaimedAmount['totalClaimValue'] >= 5000 && $noOfRecordClaimedAmount['totalClaimValue'] <= 10000) {
                                $noOfRecordClaimedAmountLable = 'ClaimValue5000';
                                $noOfRecordClaimedAmount['totalClaimValue'] = $_row['y_axis'];
                                $noOfRecordClaimedAmount['x_axis'] = $_row['x_axis'] . '|' . $_row['x_axis_month'];
                                $noOfRecordClaimedAmount['x_axis_month'] = $_row['x_axis_month'];
                            } elseif ($noOfRecordClaimedAmount['totalClaimValue'] >= 10000 && $noOfRecordClaimedAmount['totalClaimValue'] <= 25000) {
                                $noOfRecordClaimedAmountLable = 'ClaimValue10000';
                                $noOfRecordClaimedAmount['totalClaimValue'] = $_row['y_axis'];
                                $noOfRecordClaimedAmount['x_axis'] = $_row['x_axis'] . '|' . $_row['x_axis_month'];
                                $noOfRecordClaimedAmount['x_axis_month'] = $_row['x_axis_month'];
                            } elseif ($noOfRecordClaimedAmount['totalClaimValue'] >= 25000 && $noOfRecordClaimedAmount['totalClaimValue'] <= 50000) {
                                $noOfRecordClaimedAmountLable = 'ClaimValue25000';
                                $noOfRecordClaimedAmount['totalClaimValue'] = $_row['y_axis'];
                                $noOfRecordClaimedAmount['x_axis'] = $_row['x_axis'] . '|' . $_row['x_axis_month'];
                                $noOfRecordClaimedAmount['x_axis_month'] = $_row['x_axis_month'];
                            } elseif ($noOfRecordClaimedAmount['totalClaimValue'] >= 50000) {
                                $noOfRecordClaimedAmountLable = 'ClaimValue50000';
                                $noOfRecordClaimedAmount['totalClaimValue'] = $_row['y_axis'];
                                $noOfRecordClaimedAmount['x_axis'] = $_row['x_axis'] . '|' . $_row['x_axis_month'];
                                $noOfRecordClaimedAmount['x_axis_month'] = $_row['x_axis_month'];
                            }

                            $getNoOfRecordClaimedAmountResultData[$noOfRecordClaimedAmount['x_axis']][$noOfRecordClaimedAmountLable][] = $noOfRecordClaimedAmount;
                        }

                        $ClaimValue5000 = array();
                        $ClaimValue10000 = array();
                        $ClaimValue25000 = array();
                        $ClaimValue50000 = array();
                        $norca_y_axis = array();
                        foreach ($getNoOfRecordClaimedAmountResultData as $key => $row) {
                            $ClaimValue5000[] = count($row['ClaimValue5000']);
                            $ClaimValue10000[] = count($row['ClaimValue10000']);
                            $ClaimValue25000[] = count($row['ClaimValue25000']);
                            $ClaimValue50000[] = count($row['ClaimValue50000']);
                            $month = explode('|', $key);
                            $norca_y_axis[] = !empty($month[1]) ? $month[1] : array();
                            $month[1];
                        }
                        // Number of records with claimed amount greater than > 5,000USD; > 10,000USD; 25,000USD and 50,000USD | end


                        $noOfRecordClaimedAmount = array(
                            'ClaimValue5000' => $ClaimValue5000,
                            'ClaimValue10000' => $ClaimValue10000,
                            'ClaimValue25000' => $ClaimValue25000,
                            'ClaimValue50000' => $ClaimValue50000,
                            'norca_y_axis' => $norca_y_axis,
                        );

                        $this->send_JSON(array('success' => true, 'awbIssueData' => $awbIssueResultData,
                            'awbRejectClaimArrayData' => $awbRejectClaimArrayData, 'awbPreliminaryFormalIssueData' => $awbPreliminaryFormalIssueResultData,
                            'awbTotalClaimedAmountResultData' => $awbTotalClaimedAmountResultData, 'awbTotalClaimedAmountData' => $awbTotalClaimedAmountData,
                            'getNoOfRecordClaimedAmountResultData' => $getNoOfRecordClaimedAmountResultData,
                            'noOfRecordClaimedAmountData' => $noOfRecordClaimedAmount
                        ));
                    }

                    public function action_Load_Claim_Type_Record_Chart(MVC_Request $rq) {
                        $ids = $rq->ids;

                        $filterStatus = $rq->filterStatus;
                        if ($filterStatus == 'all') {
                            $where1 = "  ";
                        } elseif ($filterStatus == 'active') {
                            $where1 = " AND `iod`.process NOT IN('closed', 'hidden') ";
                        } elseif ($filterStatus == 'closed') {
                            $where1 = " AND `iod`.process IN('closed') ";
                        }


                        $startDate = str_replace('/', '-', $rq->startDate);
                        $endDate = str_replace('/', '-', $rq->endDate);

                        $fromdate = $startDate != '' ? date("Y-m-d", strtotime($startDate)) : '';
                        $todate = $endDate != '' ? date("Y-m-d", strtotime($endDate)) : '';

                        $where = ''; //' AND YEAR(FROM_UNIXTIME(`iod`.`linktime`)) = 2023';
                        if ($fromdate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iod`.`linktime`)) >= '" . $fromdate . "'";
                        }
                        if ($todate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iod`.`linktime`)) <= '" . $todate . "'";
                        }
                        if ($fromdate != '' && $todate != '') {
                            $from_ts = strtotime($fromdate);
                            $to_ts   = strtotime($todate);
//                            $where_count .= " AND xf_crdate.int_value BETWEEN '" .$from_ts. "' AND '" .$to_ts. "' ";
                           $where_count = "AND (xf_crdate.int_value >= $from_ts AND xf_crdate.int_value <= $to_ts )";
                        }
                        

                        $type_id = $this->rt_Return();
                        $org_id = MVC_Core::$ss->user['org_id'];
                        $rt_id = $this->rt_Return();

                        // formalClaimTypeByOrigin start
                        //`ixf`.fk_id = 65(Claim type) ,  `ixf`.fk_id = 66(Origin Airport) , `ixf`.fk_id = 67(Destination Airport), `ixf`.`value` = 'fpr'(Formal Claim)
                        $sql = "SELECT COUNT(*)AS `y_axis`,airport.IATA as x_axis
            FROM `issues` AS iss
            LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
            LEFT JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 65)
            INNER JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 66)
            LEFT JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
            WHERE `iss`.type_id = $type_id  AND `iod`.org_id = $org_id AND  `ixf`.`value` = 'fpr' 
             $where1 $where
            GROUP BY x_axis ORDER BY `y_axis` DESC LIMIT 10";
                        $r = MVC_Core::$db->query($sql);
                        if (!$r)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($r as $row) {

                            $formalclaimOriginValue[] = array(
                                'y_axis' => $row['y_axis'],
                                'x_axis' => $row['x_axis']
                            );

                            $y_axis[] = $row['y_axis'];
                            $x_axis[] = $row['x_axis'];
                        }
                        $formalClaimTypeByOrigin = array(
                            'y_axis' => $y_axis,
                            'x_axis' => $x_axis,
                        );
                        // formalClaimTypeByOrigin end
                        // formalClaimTypeByDestination start
                        //`ixf`.fk_id = 65(Claim type) ,  `ixf`.fk_id = 66(Origin Airport) , `ixf`.fk_id = 67(Destination Airport), `ixf`.`value` = 'fpr'(Formal Claim)
                        $_sql = "SELECT COUNT(*)AS `y_axis`,airport.IATA as x_axis
            FROM `issues` AS iss
            LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
            LEFT JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 65)
            INNER JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 67)
            LEFT JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
            WHERE `iss`.type_id = $type_id  AND `iod`.org_id = $org_id AND  `ixf`.`value` = 'fpr' 
            $where1 $where
            GROUP BY x_axis ORDER BY `y_axis` DESC LIMIT 10";
                        $_r = MVC_Core::$db->query($_sql);
                        if (!$r)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($_r as $row) {

                            $formalClaimDestinationValue[] = array(
                                'y_axis' => $row['y_axis'],
                                'x_axis' => $row['x_axis']
                            );

                            $fctbd_y_axis[] = $row['y_axis'];
                            $fctbd_x_axis[] = $row['x_axis'];
                        }
                        $formalClaimTypeByDestination = array(
                            'y_axis' => $fctbd_y_axis,
                            'x_axis' => $fctbd_x_axis,
                        );

//                        $totalFormalClaimTypeDataSql = "SELECT COUNT(airport.IATA)AS `y_axis`
//            FROM `issues`AS iss 
//            LEFT JOIN `issue_orgdata`AS iod ON `iod`.issue_id=`iss`.id 
//            LEFT JOIN `issue_fields`AS ixf ON(`ixf`.issue_id=`iss`.id AND `ixf`.fk_id=65)
//            INNER JOIN `issue_fields`AS ixf_1 ON(`ixf_1`.issue_id=`iss`.id AND `ixf_1`.fk_id=66)
//            LEFT JOIN `data_airports`AS airport ON(ixf_1.int_value=`airport`.id)
//            WHERE `iss`.type_id=$type_id AND `iod`.org_id=$org_id AND `ixf`.`value`='fpr' 
//            $where1  $where ";
                       
                        
                        $totalFormalClaimTypeDataSql = " SELECT COUNT(DISTINCT iss.id) AS y_axis
                                                            FROM issues AS iss
                                                            INNER JOIN issue_orgdata AS iod 
                                                                   ON iss.id = iod.issue_id 
                                                                  AND iod.org_id = $org_id 
                                                                  AND iod.is_enabled = 1
                                                            LEFT JOIN issue_fields AS xf_crdate 
                                                                   ON xf_crdate.issue_id = iss.id 
                                                                  AND xf_crdate.fk_id = 2399
                                                            LEFT JOIN issue_fields AS xf_pstage 
                                                                   ON xf_pstage.issue_id = iss.id 
                                                                  AND xf_pstage.fk_id = 65
                                                            LEFT JOIN issue_fields AS ixf_airport 
                                                                   ON ixf_airport.issue_id = iss.id 
                                                                  AND ixf_airport.fk_id = 66
                                                            LEFT JOIN data_airports AS airport 
                                                                   ON airport.id = ixf_airport.int_value
                                                            WHERE 
                                                              (iss.master_org_id = $org_id OR FIND_IN_SET($org_id, iss.shared_org_ids))
                                                              $where_count $where
                                                              AND xf_pstage.value = 'fpr'
                                                              AND iss.type_id = $type_id";


                        $totalFormalClaimTypeResult = MVC_Core::$db->query($totalFormalClaimTypeDataSql);
                        if (!$totalFormalClaimTypeResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($totalFormalClaimTypeResult as $row) {
                            $totalFormalClaimTypeData = $row['y_axis'];
                        }

                        // formalClaimTypeByDestination end
                        //Top 10 Preliminary Claim type by Origin start
                        $preliminaryClaimSql = "SELECT COUNT(*)AS `y_axis`,airport.IATA as x_axis
            FROM `issues` AS iss
            LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
            LEFT JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 65)
            INNER JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 66)
            LEFT JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
            WHERE `iss`.type_id = $type_id  AND `iod`.org_id = $org_id AND  `ixf`.`value` = 'prel' 
            $where1 $where
            GROUP BY x_axis ORDER BY `y_axis` DESC LIMIT 10";
                        $preliminaryClaimResult = MVC_Core::$db->query($preliminaryClaimSql);
                        if (!$preliminaryClaimResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($preliminaryClaimResult as $row) {

                            $preliminaryClaimOriginValue[] = array(
                                'x_axis' => $row['x_axis'],
                                'y_axis' => $row['y_axis']
                            );

                            $pctbo_y_axis[] = $row['y_axis'];
                            $pctbo_x_axis[] = $row['x_axis'];
                        }
                        $preliminaryClaimTypeByOrigin = array(
                            'y_axis' => $pctbo_y_axis,
                            'x_axis' => $pctbo_x_axis,
                        );
                        //Top 10 Preliminary Claim type by Origin end
                        //Top 10 Preliminary Claim type by Destination start
                        $preliminaryClaimDestSql = "SELECT COUNT(*)AS `y_axis`,airport.IATA as x_axis
            FROM `issues` AS iss
            LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
            LEFT JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 65)
            INNER JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 67)
            LEFT JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
            WHERE `iss`.type_id = $type_id  AND `iod`.org_id = $org_id AND  `ixf`.`value` = 'prel'
             $where1 $where
            GROUP BY x_axis ORDER BY `y_axis` DESC LIMIT 10";
                        // limit and order by clause
                        $preliminaryClaimDestResult = MVC_Core::$db->query($preliminaryClaimDestSql);
                        if (!$preliminaryClaimDestResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($preliminaryClaimDestResult as $row) {

                            $preliminaryClaimDestinationValue[] = array(
                                'y_axis' => $row['y_axis'],
                                'x_axis' => $row['x_axis']
                            );

                            $pctbd_y_axis[] = $row['y_axis'];
                            $pctbd_x_axis[] = $row['x_axis'];
                        }
                        $preliminaryClaimTypeByDestination = array(
                            'y_axis' => $pctbd_y_axis,
                            'x_axis' => $pctbd_x_axis,
                        );
                        
                        
                        
                        
//                         IWI Cargo claim chart page changes 22052025 start
                        $claimByOriginSql = "SELECT COUNT(*)AS `y_axis`,airport1.IATA as origin ,

 COUNT(ixf_2.int_value) AS damage_pieces,
    COUNT(ixf_3.int_value) AS total_loss_pieces,
    COUNT(ixf_4.int_value) AS delay_pieces,
    COUNT(ixf_5.int_value) AS partial_loss_pieces,
    COUNT(ixf_6.int_value) AS pilferage_pieces
FROM `issues` AS iss
LEFT JOIN `issue_orgdata` AS iod ON iod.issue_id = iss.id
LEFT JOIN `issue_fields` AS ixf ON (ixf.issue_id = iss.id AND ixf.fk_id = 65)
LEFT JOIN `issue_fields` AS ixf_1 ON (ixf_1.issue_id = iss.id AND ixf_1.fk_id = 66)
LEFT JOIN `issue_fields` AS ixf_2 ON (ixf_2.issue_id = iss.id AND ixf_2.fk_id = 12)
LEFT JOIN `issue_fields` AS ixf_3 ON (ixf_3.issue_id = iss.id AND ixf_3.fk_id = 128)
LEFT JOIN `issue_fields` AS ixf_4 ON (ixf_4.issue_id = iss.id AND ixf_4.fk_id = 565)
LEFT JOIN `issue_fields` AS ixf_5 ON (ixf_5.issue_id = iss.id AND ixf_5.fk_id = 956)
LEFT JOIN `issue_fields` AS ixf_6 ON (ixf_6.issue_id = iss.id AND ixf_6.fk_id = 959)
LEFT JOIN `data_airports` AS airport1 ON (ixf_1.int_value = airport1.id)
WHERE 
     iss.type_id = $type_id 
  AND iod.org_id = $org_id
    AND (
        ixf_2.int_value IS NOT NULL OR
        ixf_3.int_value IS NOT NULL OR
        ixf_4.int_value IS NOT NULL OR
        ixf_5.int_value IS NOT NULL OR
        ixf_6.int_value IS NOT NULL
    )
     $where1 $where
         GROUP BY origin ORDER BY `y_axis` DESC LIMIT 10

";
                     
                     
                        
                        $claimByOriginResult = MVC_Core::$db->query($claimByOriginSql);
                        if (!$claimByOriginResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
                        $claimByOriginData=array();
                        foreach ($claimByOriginResult as $row) {

                            
                            $claimByOriginData[]=array(
                                'origin'=>$row['origin'],
                                'damage'=>$row['damage_pieces'],
                                'total_loss'=>$row['total_loss_pieces'],
                                'delay'=>$row['delay_pieces'],
                                'partial_loss'=>$row['partial_loss_pieces'],
                                'pilferage'=>$row['pilferage_pieces'],
                            );
                        }
                        
                        
                         $claimByDestinationSql = "SELECT COUNT(*)AS `y_axis`,airport1.IATA as destination ,

 COUNT(ixf_2.int_value) AS damage_pieces,
    COUNT(ixf_3.int_value) AS total_loss_pieces,
    COUNT(ixf_4.int_value) AS delay_pieces,
    COUNT(ixf_5.int_value) AS partial_loss_pieces,
    COUNT(ixf_6.int_value) AS pilferage_pieces
FROM `issues` AS iss
LEFT JOIN `issue_orgdata` AS iod ON iod.issue_id = iss.id
LEFT JOIN `issue_fields` AS ixf ON (ixf.issue_id = iss.id AND ixf.fk_id = 65)
LEFT JOIN `issue_fields` AS ixf_1 ON (ixf_1.issue_id = iss.id AND ixf_1.fk_id = 67)
LEFT JOIN `issue_fields` AS ixf_2 ON (ixf_2.issue_id = iss.id AND ixf_2.fk_id = 12)
LEFT JOIN `issue_fields` AS ixf_3 ON (ixf_3.issue_id = iss.id AND ixf_3.fk_id = 128)
LEFT JOIN `issue_fields` AS ixf_4 ON (ixf_4.issue_id = iss.id AND ixf_4.fk_id = 565)
LEFT JOIN `issue_fields` AS ixf_5 ON (ixf_5.issue_id = iss.id AND ixf_5.fk_id = 956)
LEFT JOIN `issue_fields` AS ixf_6 ON (ixf_6.issue_id = iss.id AND ixf_6.fk_id = 959)
LEFT JOIN `data_airports` AS airport1 ON (ixf_1.int_value = airport1.id)
WHERE 
     iss.type_id = $type_id 
  AND iod.org_id = $org_id
    AND (
        ixf_2.int_value IS NOT NULL OR
        ixf_3.int_value IS NOT NULL OR
        ixf_4.int_value IS NOT NULL OR
        ixf_5.int_value IS NOT NULL OR
        ixf_6.int_value IS NOT NULL
    )
     $where1 $where
         GROUP BY destination ORDER BY `y_axis` DESC LIMIT 10";
                        $claimByDestinationResult = MVC_Core::$db->query($claimByDestinationSql);
                        if (!$claimByDestinationResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
                        $claimByDestinationData=array();
                        foreach ($claimByDestinationResult as $row) {

                            
                            $claimByDestinationData[]=array(
                                'destination'=>$row['destination'],
                                'damage'=>$row['damage_pieces'],
                                'total_loss'=>$row['total_loss_pieces'],
                                'delay'=>$row['delay_pieces'],
                                'partial_loss'=>$row['partial_loss_pieces'],
                                'pilferage'=>$row['pilferage_pieces'],
                            );
                        }
                        
//                         IWI Cargo claim chart page changes 22052025 end
                        
                        
                        

//                        $totalPreliminaryClaimTypeDataSql = "SELECT count(airport.IATA) as x_axis 
//            FROM `issues`AS iss 
//            LEFT JOIN `issue_orgdata`AS iod ON `iod`.issue_id=`iss`.id 
//            LEFT JOIN `issue_fields`AS ixf ON(`ixf`.issue_id=`iss`.id AND `ixf`.fk_id=65)
//            INNER JOIN `issue_fields`AS ixf_1 ON(`ixf_1`.issue_id=`iss`.id AND `ixf_1`.fk_id=66)
//            LEFT JOIN `data_airports`AS airport ON(ixf_1.int_value=`airport`.id)
//            WHERE `iss`.type_id=$type_id AND `iod`.org_id=$org_id AND `ixf`.`value`='prel' 
//            
//            $where1 $where";

                       
              
                        $totalPreliminaryClaimTypeDataSql = "   SELECT COUNT(DISTINCT iss.id) AS x_axis
                                                            FROM issues AS iss
                                                            INNER JOIN issue_orgdata AS iod 
                                                                   ON iss.id = iod.issue_id 
                                                                  AND iod.org_id = $org_id 
                                                                  AND iod.is_enabled = 1
                                                            LEFT JOIN issue_fields AS xf_crdate 
                                                                   ON xf_crdate.issue_id = iss.id 
                                                                  AND xf_crdate.fk_id = 2399
                                                            LEFT JOIN issue_fields AS xf_pstage 
                                                                   ON xf_pstage.issue_id = iss.id 
                                                                  AND xf_pstage.fk_id = 65
                                                            LEFT JOIN issue_fields AS ixf_airport 
                                                                   ON ixf_airport.issue_id = iss.id 
                                                                  AND ixf_airport.fk_id = 66
                                                            LEFT JOIN data_airports AS airport 
                                                                   ON airport.id = ixf_airport.int_value
                                                            WHERE 
                                                              (iss.master_org_id = $org_id OR FIND_IN_SET($org_id, iss.shared_org_ids))
                                                              $where_count $where
                                                              AND xf_pstage.value = 'prel'
                                                              AND iss.type_id = $type_id
                                                            "   ;          
                        

                        $totalPreliminaryClaimTypeResult = MVC_Core::$db->query($totalPreliminaryClaimTypeDataSql);
                        if (!$totalPreliminaryClaimTypeResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($totalPreliminaryClaimTypeResult as $row) {
                            $totalPreliminaryClaimTypeData = $row['x_axis'];
                        }

                        //Top 10 Preliminary Claim type by Destination end
                        ########################################################################################################################################################
                        ########################################################################################################################################################

                        $preliminaryPermonthSql = "SELECT COUNT(*)AS `y_axis`,MONTH(FROM_UNIXTIME(`iod`.`linktime`)) as monthName
            FROM `issues`AS iss 
            LEFT JOIN `issue_orgdata`AS iod ON `iod`.issue_id=`iss`.id 
            LEFT JOIN `issue_fields`AS ixf ON(`ixf`.issue_id=`iss`.id AND `ixf`.fk_id=65)
            WHERE `iss`.type_id=$type_id AND `iod`.org_id=$org_id 
            AND `ixf`.`value` IN('prel')
             $where1 $where
            GROUP BY monthName  ASC LIMIT 10";

                        $preliminaryPermonthResult = MVC_Core::$db->query($preliminaryPermonthSql);
                        if (!$preliminaryPermonthResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($preliminaryPermonthResult as $_row) {
                            $preliminaryPermonthResultData[$_row['monthName']] = array(
                                'y_axis' => $_row['y_axis']
                            );
                        }



                        $formalPermonthSql = "SELECT COUNT(*)AS `y_axis`,MONTH(FROM_UNIXTIME(`iod`.`linktime`)) as monthName,
            MONTHNAME(FROM_UNIXTIME(`iod`.`linktime`)) as monthNameValue
            FROM `issues`AS iss 
            LEFT JOIN `issue_orgdata`AS iod ON `iod`.issue_id=`iss`.id 
            LEFT JOIN `issue_fields`AS ixf ON(`ixf`.issue_id=`iss`.id AND `ixf`.fk_id=65)
           
            WHERE `iss`.type_id=$type_id AND `iod`.org_id=$org_id 
            AND `ixf`.`value` IN('fpr')
            $where1 $where 
            GROUP BY monthName  ASC LIMIT 10";

                        $formalPermonthResult = MVC_Core::$db->query($formalPermonthSql);
                        if (!$formalPermonthResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($formalPermonthResult as $_row) {
                            $fpry_axis[] = $_row['y_axis'];
                            $fprx_axis[] = $_row['monthNameValue'];
                            $fprxy_axis[] = !empty($preliminaryPermonthResultData[$_row['monthName']]['y_axis']) ? $preliminaryPermonthResultData[$_row['monthName']]['y_axis'] : 0;
                        }

                        $formalPermonthResultData = array(
                            'fpry_axis' => $fpry_axis,
                            'fprx_axis' => $fprx_axis,
                            'fprxy_axis' => $fprxy_axis,
                        );
                        ########################################################################################################################################################
                        ########################################################################################################################################################
                        // total number of active record every month start
                        $totalActiveRecordEveryMonthSql = "SELECT COUNT(*)AS `y_axis`,  CONCAT(YEAR(FROM_UNIXTIME(`iod`.`linktime`)), '-', MONTH(FROM_UNIXTIME(`iod`.`linktime`))) as 'x_axis', 
            MONTHNAME(FROM_UNIXTIME(`iod`.`linktime`)) as 'x_axis_month'          
            FROM `issues` AS iss           
            LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id           
            WHERE `iss`.type_id = $type_id  AND `iod`.org_id = $org_id  
            $where1 $where          
            GROUP BY MONTH(FROM_UNIXTIME(`iod`.`linktime`)) ORDER BY `x_axis` ASC";

                        $totalActiveRecordEveryMonthQuery = MVC_Core::$db->query($totalActiveRecordEveryMonthSql);
                        if (!$totalActiveRecordEveryMonthQuery)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($totalActiveRecordEveryMonthQuery as $row) {
                            $tarem_y_axis[] = $row['y_axis'];
                            $tarem_x_axis[] = $row['x_axis'];
                            $tarem_x_axis_month[] = $row['x_axis_month'];
                        }
                        $totalActiveRecordEveryMonthData = array(
                            'y_axis' => $tarem_y_axis,
                            'x_axis' => $tarem_x_axis,
                            'x_axis_month' => $tarem_x_axis_month,
                        );
                        // total number of active record every month end




                        $this->send_JSON(array('success' => true, 'formalClaimTypeByOrigin' => $formalClaimTypeByOrigin,
                            'formalClaimTypeByDestination' => $formalClaimTypeByDestination, 'preliminaryClaimTypeByOrigin' => $preliminaryClaimTypeByOrigin,
                            'preliminaryClaimTypeByDestination' => $preliminaryClaimTypeByDestination,
                            'totalFormalClaimTypeData' => $totalFormalClaimTypeData, 'totalPreliminaryClaimTypeData' => $totalPreliminaryClaimTypeData,
                            'preliminaryPermonthResultData' => $preliminaryPermonthResultData, 'formalPermonthResultData' => $formalPermonthResultData,
                            'formalclaimOriginValue' => $formalclaimOriginValue, 'formalClaimDestinationValue' => $formalClaimDestinationValue,
                            'preliminaryClaimOriginValue' => $preliminaryClaimOriginValue, 'preliminaryClaimDestinationValue' => $preliminaryClaimDestinationValue,
                            'totalActiveRecordEveryMonthData' => $totalActiveRecordEveryMonthData,
                            
                                'claimByOrigin'=>$claimByOriginData, //IWI Cargo claim chart page changes 22052025 
                                'claimByDestination'=>$claimByDestinationData, //IWI Cargo claim chart page changes 22052025 
                                ));
                    }
                    
//                    IWI Air Cargo claim chart page new design start 
                    public function action_Load_Claim_Type_Record_Chart_v2(MVC_Request $rq) {
                        $ids = $rq->ids;
                        $type_id = $this->rt_Return();
                        $org_id = MVC_Core::$ss->user['org_id'];
                        $rt_id = $this->rt_Return();
                        
                        $airlineDropdown = $rq->airlineDropdown;                        
                         if ($airlineDropdown == 'all') {
                            $where_airline = "  ";
                        }  else {
                            $getGroup=$this->issue->getGrpupIdByName($airlineDropdown);
                            $getGroupId=$getGroup['id'];
                            $where_airline = " AND `iss`.group_id ='$getGroupId' ";
                        }
                                                
                        $filterStatus = $rq->filterStatus;
                        if ($filterStatus == 'all') {
                            $where_status = "  ";
                        } elseif ($filterStatus == 'active') {
                            $where_status = " AND `iod`.process NOT IN('closed', 'hidden') ";
                        } elseif ($filterStatus == 'closed') {
                            $where_status = " AND `iod`.process IN('closed') ";
                        } else {
                            $where_status = "  ";
                        }

                        $startDate = str_replace('/', '-', $rq->startDate);
                        $endDate = str_replace('/', '-', $rq->endDate);
                        $fromdate = $startDate != '' ? strtotime($startDate) : '';
                        $todate = $endDate != '' ?  strtotime($endDate) : '';
                        $where_date = '';
                        if ($fromdate != '' && $todate != '') {
                            $where_date .= " AND (`iss`.ctime BETWEEN $fromdate AND $todate) ";
                        }
                        




                        //for get preliminary and formal total count start 
                        
                        $ClaimCombinedCountSqlResult = $this->issue->getClaimCombinedCountSqlResult($type_id,$org_id,$where_status,$where_date,$where_airline);                            $preliminary_total_count = '0';
                        $formal_total_count = '0';
                        foreach ($ClaimCombinedCountSqlResult as $row) {
                            $preliminary_total_count = $row['prel_count'];
                            $formal_total_count = $row['fpr_count'];
                        }                       
                        
                        //for get preliminary and formal total count end 
                       
                        //for get top 10 preliminary claim by origin and destination start                        
                        
                        $preliminaryClaimTopOriginData = $this->issue->getPreliminaryClaimTopOriginData($type_id,$org_id,$where_status,$where_date,$where_airline);    
                        $preliminary_top_10_originlebel = array();
                        $preliminary_top_10_originDataset = array();
                        foreach ($preliminaryClaimTopOriginData as $row) {
                            $preliminary_top_10_originlebel[]=$row['airport_code'];
                                $preliminary_top_10_originDataset[$row['airport_code']] = $row['claim_count'];
                        }
                        
                        $preliminaryClaimTopDestinationData = $this->issue->getPreliminaryClaimTopDestinationData($type_id,$org_id,$where_status,$where_date,$where_airline);    
                        $preliminary_top_10_destinationlebel = array();
                        $preliminary_top_10_destinationDataset = array();
                        foreach ($preliminaryClaimTopDestinationData as $row) {
                            $preliminary_top_10_destinationlebel[]=$row['airport_code'];
                                $preliminary_top_10_destinationDataset[$row['airport_code']] = $row['claim_count'];
                        }
                        //for get top 10 preliminary claim by origin and destination end 
                        //for get top 10 formal claim by origin and destination start 

                        
                        
                        $formalClaimTopOriginData = $this->issue->getFormalClaimTopOriginData($type_id,$org_id,$where_status,$where_date,$where_airline);    
                        $formal_top_10_originlebel = array();
                        $formal_top_10_originDataset = array();
                        foreach ($formalClaimTopOriginData as $row) {
                            $formal_top_10_originlebel[]=$row['airport_code'];
                                $formal_top_10_originDataset[$row['airport_code']] = $row['claim_count'];
                        }
                        
                        $formalClaimTopDestinationData = $this->issue->getFormalClaimTopDestinationData($type_id,$org_id,$where_status,$where_date,$where_airline);    
                        $formal_top_10_destinationlebel = array();
                        $formal_top_10_destinationDataset = array();
                        foreach ($formalClaimTopDestinationData as $row) {
                            $formal_top_10_destinationlebel[]=$row['airport_code'];
                                $formal_top_10_destinationDataset[$row['airport_code']] = $row['claim_count'];
                        }
                        //for get top 10 Formal claim by origin and destination end 
                        
                        
                        $claimByOriginSql = "SELECT COUNT(*)AS `y_axis`,airport1.IATA as origin ,

 COUNT(ixf_2.int_value) AS damage_pieces,
    COUNT(ixf_3.int_value) AS total_loss_pieces,
    COUNT(ixf_4.int_value) AS delay_pieces,
    COUNT(ixf_5.int_value) AS partial_loss_pieces,
    COUNT(ixf_6.int_value) AS pilferage_pieces
FROM `issues` AS iss
LEFT JOIN issue_orgdata AS iod ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
LEFT JOIN `issue_fields` AS ixf ON (ixf.issue_id = iss.id AND ixf.fk_id = 65)
LEFT JOIN `issue_fields` AS ixf_1 ON (ixf_1.issue_id = iss.id AND ixf_1.fk_id = 66)
LEFT JOIN `issue_fields` AS ixf_2 ON (ixf_2.issue_id = iss.id AND ixf_2.fk_id = 12)
LEFT JOIN `issue_fields` AS ixf_3 ON (ixf_3.issue_id = iss.id AND ixf_3.fk_id = 128)
LEFT JOIN `issue_fields` AS ixf_4 ON (ixf_4.issue_id = iss.id AND ixf_4.fk_id = 565)
LEFT JOIN `issue_fields` AS ixf_5 ON (ixf_5.issue_id = iss.id AND ixf_5.fk_id = 956)
LEFT JOIN `issue_fields` AS ixf_6 ON (ixf_6.issue_id = iss.id AND ixf_6.fk_id = 959)
LEFT JOIN `data_airports` AS airport1 ON (ixf_1.int_value = airport1.id)
WHERE 
     iss.type_id = $type_id 
    AND (
        ixf_2.int_value IS NOT NULL OR
        ixf_3.int_value IS NOT NULL OR
        ixf_4.int_value IS NOT NULL OR
        ixf_5.int_value IS NOT NULL OR
        ixf_6.int_value IS NOT NULL
    )
     $where_status $where_date $where_airline
         GROUP BY origin ORDER BY `y_axis` DESC LIMIT 10

";
                     
                     
                        
                        $claimByOriginResult = MVC_Core::$db->query($claimByOriginSql);
                        if (!$claimByOriginResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
                        $claimByOriginData=array();
                        foreach ($claimByOriginResult as $row) {

                            
                            $claimByOriginData[]=array(
                                'origin'=>$row['origin'],
                                'damage'=>$row['damage_pieces'],
                                'total_loss'=>$row['total_loss_pieces'],
                                'delay'=>$row['delay_pieces'],
                                'partial_loss'=>$row['partial_loss_pieces'],
                                'pilferage'=>$row['pilferage_pieces'],
                            );
                        }
                        
                        
                         $claimByDestinationSql = "SELECT COUNT(*)AS `y_axis`,airport1.IATA as destination ,

 COUNT(ixf_2.int_value) AS damage_pieces,
    COUNT(ixf_3.int_value) AS total_loss_pieces,
    COUNT(ixf_4.int_value) AS delay_pieces,
    COUNT(ixf_5.int_value) AS partial_loss_pieces,
    COUNT(ixf_6.int_value) AS pilferage_pieces
FROM `issues` AS iss
LEFT JOIN issue_orgdata AS iod ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
LEFT JOIN `issue_fields` AS ixf ON (ixf.issue_id = iss.id AND ixf.fk_id = 65)
LEFT JOIN `issue_fields` AS ixf_1 ON (ixf_1.issue_id = iss.id AND ixf_1.fk_id = 67)
LEFT JOIN `issue_fields` AS ixf_2 ON (ixf_2.issue_id = iss.id AND ixf_2.fk_id = 12)
LEFT JOIN `issue_fields` AS ixf_3 ON (ixf_3.issue_id = iss.id AND ixf_3.fk_id = 128)
LEFT JOIN `issue_fields` AS ixf_4 ON (ixf_4.issue_id = iss.id AND ixf_4.fk_id = 565)
LEFT JOIN `issue_fields` AS ixf_5 ON (ixf_5.issue_id = iss.id AND ixf_5.fk_id = 956)
LEFT JOIN `issue_fields` AS ixf_6 ON (ixf_6.issue_id = iss.id AND ixf_6.fk_id = 959)
LEFT JOIN `data_airports` AS airport1 ON (ixf_1.int_value = airport1.id)
WHERE 
     iss.type_id = $type_id 
    AND (
        ixf_2.int_value IS NOT NULL OR
        ixf_3.int_value IS NOT NULL OR
        ixf_4.int_value IS NOT NULL OR
        ixf_5.int_value IS NOT NULL OR
        ixf_6.int_value IS NOT NULL
    )
     $where_status $where_date $where_airline
         GROUP BY destination ORDER BY `y_axis` DESC LIMIT 10";
                        $claimByDestinationResult = MVC_Core::$db->query($claimByDestinationSql);
                        if (!$claimByDestinationResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
                        $claimByDestinationData=array();
                        foreach ($claimByDestinationResult as $row) {

                            
                            $claimByDestinationData[]=array(
                                'destination'=>$row['destination'],
                                'damage'=>$row['damage_pieces'],
                                'total_loss'=>$row['total_loss_pieces'],
                                'delay'=>$row['delay_pieces'],
                                'partial_loss'=>$row['partial_loss_pieces'],
                                'pilferage'=>$row['pilferage_pieces'],
                            );
                        }
                        
                         $claimByOriginSql = "SELECT COUNT(*)AS `y_axis`,airport1.IATA as origin ,

 COUNT(ixf_2.int_value) AS damage_pieces,
    COUNT(ixf_3.int_value) AS total_loss_pieces,
    COUNT(ixf_4.int_value) AS delay_pieces,
    COUNT(ixf_5.int_value) AS partial_loss_pieces,
    COUNT(ixf_6.int_value) AS pilferage_pieces
FROM `issues` AS iss
LEFT JOIN issue_orgdata AS iod ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
LEFT JOIN `issue_fields` AS ixf ON (ixf.issue_id = iss.id AND ixf.fk_id = 65)
LEFT JOIN `issue_fields` AS ixf_1 ON (ixf_1.issue_id = iss.id AND ixf_1.fk_id = 66)
LEFT JOIN `issue_fields` AS ixf_2 ON (ixf_2.issue_id = iss.id AND ixf_2.fk_id = 12)
LEFT JOIN `issue_fields` AS ixf_3 ON (ixf_3.issue_id = iss.id AND ixf_3.fk_id = 128)
LEFT JOIN `issue_fields` AS ixf_4 ON (ixf_4.issue_id = iss.id AND ixf_4.fk_id = 565)
LEFT JOIN `issue_fields` AS ixf_5 ON (ixf_5.issue_id = iss.id AND ixf_5.fk_id = 956)
LEFT JOIN `issue_fields` AS ixf_6 ON (ixf_6.issue_id = iss.id AND ixf_6.fk_id = 959)
LEFT JOIN `data_airports` AS airport1 ON (ixf_1.int_value = airport1.id)
WHERE 
     iss.type_id = $type_id 
    AND (
        ixf_2.int_value IS NOT NULL OR
        ixf_3.int_value IS NOT NULL OR
        ixf_4.int_value IS NOT NULL OR
        ixf_5.int_value IS NOT NULL OR
        ixf_6.int_value IS NOT NULL
    )
     $where_status $where_date $where_airline
         GROUP BY origin ORDER BY `y_axis` DESC LIMIT 10

";
                     
                     
                        
                        $claimByOriginResult = MVC_Core::$db->query($claimByOriginSql);
                        if (!$claimByOriginResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
                        $claimByOriginData=array();
                        foreach ($claimByOriginResult as $row) {

                            
                            $claimByOriginData[]=array(
                                'origin'=>$row['origin'],
                                'damage'=>$row['damage_pieces'],
                                'total_loss'=>$row['total_loss_pieces'],
                                'delay'=>$row['delay_pieces'],
                                'partial_loss'=>$row['partial_loss_pieces'],
                                'pilferage'=>$row['pilferage_pieces'],
                            );
                        }
                        
                        
                         $claimByDestinationSql = "SELECT COUNT(*)AS `y_axis`,airport1.IATA as destination ,

 COUNT(ixf_2.int_value) AS damage_pieces,
    COUNT(ixf_3.int_value) AS total_loss_pieces,
    COUNT(ixf_4.int_value) AS delay_pieces,
    COUNT(ixf_5.int_value) AS partial_loss_pieces,
    COUNT(ixf_6.int_value) AS pilferage_pieces
FROM `issues` AS iss
LEFT JOIN issue_orgdata AS iod ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
LEFT JOIN `issue_fields` AS ixf ON (ixf.issue_id = iss.id AND ixf.fk_id = 65)
LEFT JOIN `issue_fields` AS ixf_1 ON (ixf_1.issue_id = iss.id AND ixf_1.fk_id = 67)
LEFT JOIN `issue_fields` AS ixf_2 ON (ixf_2.issue_id = iss.id AND ixf_2.fk_id = 12)
LEFT JOIN `issue_fields` AS ixf_3 ON (ixf_3.issue_id = iss.id AND ixf_3.fk_id = 128)
LEFT JOIN `issue_fields` AS ixf_4 ON (ixf_4.issue_id = iss.id AND ixf_4.fk_id = 565)
LEFT JOIN `issue_fields` AS ixf_5 ON (ixf_5.issue_id = iss.id AND ixf_5.fk_id = 956)
LEFT JOIN `issue_fields` AS ixf_6 ON (ixf_6.issue_id = iss.id AND ixf_6.fk_id = 959)
LEFT JOIN `data_airports` AS airport1 ON (ixf_1.int_value = airport1.id)
WHERE 
     iss.type_id = $type_id 
    AND (
        ixf_2.int_value IS NOT NULL OR
        ixf_3.int_value IS NOT NULL OR
        ixf_4.int_value IS NOT NULL OR
        ixf_5.int_value IS NOT NULL OR
        ixf_6.int_value IS NOT NULL
    )
     $where_status $where_date $where_airline
         GROUP BY destination ORDER BY `y_axis` DESC LIMIT 10";
                        $claimByDestinationResult = MVC_Core::$db->query($claimByDestinationSql);
                        if (!$claimByDestinationResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
                        $claimByDestinationData=array();
                        foreach ($claimByDestinationResult as $row) {

                            
                            $claimByDestinationData[]=array(
                                'destination'=>$row['destination'],
                                'damage'=>$row['damage_pieces'],
                                'total_loss'=>$row['total_loss_pieces'],
                                'delay'=>$row['delay_pieces'],
                                'partial_loss'=>$row['partial_loss_pieces'],
                                'pilferage'=>$row['pilferage_pieces'],
                            );
                        }
                        
                        
                        
//                            $preliminaryPermonthSql = "SELECT COUNT(*)AS `count`,MONTHNAME(FROM_UNIXTIME(`iod`.`linktime`)) as monthName
//                                FROM `issues`AS iss 
//                                LEFT JOIN issue_orgdata AS iod ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
//                                LEFT JOIN `issue_fields`AS ixf ON(`ixf`.issue_id=`iss`.id AND `ixf`.fk_id=65)
//                                WHERE `iss`.type_id=$type_id 
//                                AND `ixf`.`value` IN('prel')
//                                  $where_status $where_date $where_airline
//                                GROUP BY monthName  ASC LIMIT 10";
                            $preliminaryPermonthSql = "SELECT
    MONTH(FROM_UNIXTIME(iod.linktime)) AS month_num,
    MONTHNAME(FROM_UNIXTIME(iod.linktime)) AS month_name,
    COUNT(DISTINCT iss.id) AS record_count
FROM issues AS iss
JOIN issue_orgdata AS iod ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
LEFT JOIN issue_fields AS isf 
    ON isf.issue_id = iss.id 
    AND isf.fk_id = 65  
 WHERE `iss`.type_id=$type_id 
    AND iod.process <> 'closed'
    AND isf.value = 'prel'
    $where_status $where_date $where_airline
GROUP BY month_num, month_name
ORDER BY record_count DESC
LIMIT 10";

                            $preliminaryPermonthResult = MVC_Core::$db->query($preliminaryPermonthSql);
                            if (!$preliminaryPermonthResult)
                                return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
                            $preliminaryPermonthResultData = array();
                            foreach ($preliminaryPermonthResult as $_row) {
                                $preliminaryPermonthResultData[$_row['month_name']] = $_row['record_count'];
                            }



//                            $formalPermonthSql = "SELECT COUNT(*)AS `count`,MONTHNAME(FROM_UNIXTIME(`iod`.`linktime`)) as monthName,
//                                MONTHNAME(FROM_UNIXTIME(`iod`.`linktime`)) as monthNameValue
//                                FROM `issues`AS iss 
//                                LEFT JOIN issue_orgdata AS iod ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
//                                LEFT JOIN `issue_fields`AS ixf ON(`ixf`.issue_id=`iss`.id AND `ixf`.fk_id=65)
//
//                                WHERE `iss`.type_id=$type_id 
//                                AND `ixf`.`value` IN('fpr')
//                                 $where_status $where_date $where_airline
//                                GROUP BY monthName  ASC LIMIT 10";  
                            $formalPermonthSql = "SELECT
    MONTH(FROM_UNIXTIME(iod.linktime)) AS month_num,
    MONTHNAME(FROM_UNIXTIME(iod.linktime)) AS month_name,
    COUNT(DISTINCT iss.id) AS record_count
FROM issues AS iss
JOIN issue_orgdata AS iod ON (iod.issue_id = iss.id AND iod.org_id = $org_id)
LEFT JOIN issue_fields AS isf 
    ON isf.issue_id = iss.id 
    AND isf.fk_id = 65  
WHERE `iss`.type_id=$type_id 
    AND iod.process <> 'closed'
    AND isf.value = 'fpr'
     $where_status $where_date $where_airline
GROUP BY month_num, month_name
ORDER BY record_count DESC
LIMIT 10";

                            $formalPermonthResult = MVC_Core::$db->query($formalPermonthSql);
                            if (!$formalPermonthResult)
                                return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
                            $formalPermonthResultData = array();
                            foreach ($formalPermonthResult as $_row) {
                                $formalPermonthResultData[$_row['month_name']] = $_row['record_count'];
                            }



        // total preliminary number of active record every month start

        $totalPreActiveRecordEveryMonthQuery = $this->issue->getTotalPreActiveRecordEveryMonth($type_id, $org_id, $where_status, $where_date, $where_airline);
        $totalPreActiveRecordEveryMonthData = array();
        foreach ($totalPreActiveRecordEveryMonthQuery as $row) {
            $totalPreActiveRecordEveryMonthData[$row['month']] = $row['count'];
        }

        // total preliminary number of active record every month end
        // total formal number of active record every month start
        $totalFormalActiveRecordEveryMonthQuery = $this->issue->getFormalActiveRecordEveryMonth($type_id, $org_id, $where_status, $where_date, $where_airline);
        $totalFormalActiveRecordEveryMonthData = array();
        foreach ($totalFormalActiveRecordEveryMonthQuery as $row) {
            $totalFormalActiveRecordEveryMonthData[$row['month']] = $row['count'];
        }
        // total formal number of active record every month end
        
        
        // open record every month type wise start
         
        $totalOpenRecordsDataQuery = $this->issue->getTotalOpenRecordsData($type_id, $org_id, $where_status, $where_date, $where_airline);
        $totalOpenRecordsData = array(
            'fpr' => array(),
            'prel' => array(),
            'other' => array()
        );

        foreach ($totalOpenRecordsDataQuery as $row) {
//            print_r($row);
            $month = $row['month_name'];
            $count = (int) $row['record_count'];

            if ($row['field_value'] === 'prel') {
                $totalOpenRecordsData['prel'][$month] = $count;
            } elseif ($row['field_value'] === 'fpr') {
                $totalOpenRecordsData['fpr'][$month] = $count;
            } elseif ($row['field_value'] === NULL) {
                $totalOpenRecordsData['other'][$month] = $count;
            }
        }
//        exit();


        // open record every month type wise end

                        
                 // === Service Level ===
    $serviceLevelResultData = array('x_axis' => array(), 'y_axis' => array());
    $serviceLevelSql = $this->issue->getServiceLevelData($type_id, $org_id, $where_status, $where_date, $where_airline);
    if ($serviceLevelSql) {
        $map = array(
            '-1' => 'Overdue', '1' => 'SLA 30', '2' => 'SLA 60', '3' => 'SLA 90',
            '11' => 'SLA 30 w/ extend', '12' => 'SLA 60 w/ extend', '13' => 'SLA 90 w/ extend'
        );
        foreach ($serviceLevelSql as $row) {
            $label = isset($row['int_value']) ? (string)$row['int_value'] : '';
            $serviceLevelResultData['x_axis'][] = isset($map[$label]) ? $map[$label] : '';
            $serviceLevelResultData['y_axis'][] = isset($row['total_claims']) ? (int)$row['total_claims'] : 0;
        }
    }

    // === Claim Activity ===
        $claimActivitySql = $this->issue->getClaimActivityData($type_id, $org_id, $where_status, $where_date, $where_airline);
        $status_key = array();
        $claim_count = array();
        if ($claimActivitySql) {
            $statusLabels = array(
                'new' => 'New',
                'ack' => 'Acknowledged',
                'sub' => 'Submitted',
                'rpl' => 'Replaced by Formal',
                'mdoc' => 'Missing Docs',
                'prc' => 'Processing',
                'hld' => 'On Hold',
                'ins' => 'Insurance',
                'lgl' => 'Legal proceeding',
                'rej' => 'Rejected',
                'rvk' => 'Revoked',
                'tmb' => 'Time barred',
                'prp' => 'Proposal',
                'pay' => 'Payment process',
                'stl' => 'Settled',
                'cbk' => 'Chargeback',
                'aprv' => 'Approved',
                'inpr' => 'In Progress'
            );

            foreach ($claimActivitySql as $row) {
//                $status_key[] = isset($row['status_key']) ? $statusLabels[$row['status_key']] : '';
                $status_key[] = isset($row['status_key']) ? $row['status_key'] : '';
                $claim_count[] = isset($row['claim_count']) ? (int) $row['claim_count'] : 0;
            }
        }
        $claimActivityResultData = array('status_key' => $status_key, 'claim_count' => $claim_count);

        // === Output JSON ===
    $this->send_JSON(array(
        'success' => true,
        'preliminary_total_count' => $preliminary_total_count,
        'formal_total_count' => $formal_total_count,
        'preliminary_top_10_originlebel' => $preliminary_top_10_originlebel,
        'preliminary_top_10_destinationlebel' => $preliminary_top_10_destinationlebel,
        'preliminary_top_10_originDataset' => $preliminary_top_10_originDataset,
        'preliminary_top_10_destinationDataset' => $preliminary_top_10_destinationDataset,
        'formal_top_10_originlebel' => $formal_top_10_originlebel,
        'formal_top_10_destinationlebel' => $formal_top_10_destinationlebel,
        'formal_top_10_originDataset' => $formal_top_10_originDataset,
        'formal_top_10_destinationDataset' => $formal_top_10_destinationDataset,
        'claimByOrigin' => $claimByOriginData,
        'claimByDestination' => $claimByDestinationData,
        'preliminaryPermonthResultData' => $preliminaryPermonthResultData,
        'formalPermonthResultData' => $formalPermonthResultData,
        'totalPreActiveRecordEveryMonthData' => $totalPreActiveRecordEveryMonthData,
        'totalFormalActiveRecordEveryMonthData' => $totalFormalActiveRecordEveryMonthData,
        'totalOpenRecordsData' => $totalOpenRecordsData,
        'serviceLevelResultData' => $serviceLevelResultData,
        'claimActivityResultData' => $claimActivityResultData
    ));
                        
                        
                        
                    }
                    
                    
                    
                    public function action_Load_Formal_Claim_Type_Record_Chart(MVC_Request $rq) {
                        $ids = $rq->ids;
                        $type_id = $this->rt_Return();
                        $org_id = MVC_Core::$ss->user['org_id'];
                        $rt_id = $this->rt_Return();
                        
                        $airlineDropdown = $rq->airlineDropdown;                        
                         if ($airlineDropdown == 'all') {
                            $where_airline = "  ";
                        }  else {
                            $getGroup=$this->issue->getGrpupIdByName($airlineDropdown);
                            $getGroupId=$getGroup['id'];
                            $where_airline = " AND `iss`.group_id ='$getGroupId' ";
                        }
                                                
                        $filterStatus = $rq->filterStatus;
                        if ($filterStatus == 'all') {
                            $where_status = "  ";
                        } elseif ($filterStatus == 'active') {
                            $where_status = " AND `iod`.process NOT IN('closed', 'hidden') ";
                        } elseif ($filterStatus == 'closed') {
                            $where_status = " AND `iod`.process IN('closed') ";
                        } else {
                            $where_status = "  ";
                        }

                        $startDate = str_replace('/', '-', $rq->startDate);
                        $endDate = str_replace('/', '-', $rq->endDate);
                        $fromdate = $startDate != '' ? strtotime($startDate) : '';
                        $todate = $endDate != '' ?  strtotime($endDate) : '';
                        $where_date = '';
                        if ($fromdate != '' && $todate != '') {
                            $where_date .= " AND (`iss`.ctime BETWEEN $fromdate AND $todate) ";
                        }
                        
                        //for get preliminary and formal total count start 
                        
                        $ClaimCombinedCountSqlResult = $this->issue->getClaimCombinedCountSqlResult($type_id,$org_id,$where_status,$where_date,$where_airline);
                        $formal_total_count = '0';
                        foreach ($ClaimCombinedCountSqlResult as $row) {
                            $formal_total_count = $row['fpr_count'];
                        }                       
                        
                        //for get preliminary and formal total count end 
                        
                        
                        //for get top 10 formal claim by origin and destination start 

                        
                        
                        $formalClaimTopOriginData = $this->issue->getFormalClaimTopOriginData($type_id,$org_id,$where_status,$where_date,$where_airline);    
                        $formal_top_10_originlebel = array();
                        $formal_top_10_originDataset = array();
                        foreach ($formalClaimTopOriginData as $row) {
                            $formal_top_10_originlebel[]=$row['airport_code'];
                                $formal_top_10_originDataset[$row['airport_code']] = $row['claim_count'];
                        }
                        
                        $formalClaimTopDestinationData = $this->issue->getFormalClaimTopDestinationData($type_id,$org_id,$where_status,$where_date,$where_airline);    
                        $formal_top_10_destinationlebel = array();
                        $formal_top_10_destinationDataset = array();
                        foreach ($formalClaimTopDestinationData as $row) {
                            $formal_top_10_destinationlebel[]=$row['airport_code'];
                                $formal_top_10_destinationDataset[$row['airport_code']] = $row['claim_count'];
                        }
                        //for get top 10 Formal claim by origin and destination end 
                       
//                        Formal Claim Activity start
                         $formalClaimActivitySql = $this->issue->getFormalClaimActivityData($type_id, $org_id, $where_status, $where_date, $where_airline);
                            $status_key = array();
                            $claim_count = array();
                            if ($formalClaimActivitySql) {
                                $statusLabels = array(
                                                'new' => 'New',
                                                'ack' => 'Acknowledged',
                                                'sub' => 'Submitted',
                                                'rpl' => 'Replaced by Formal',
                                                'mdoc' => 'Missing Docs',
                                                'prc' => 'Processing',
                                                'hld' => 'On Hold',
                                                'ins' => 'Insurance',
                                                'lgl' => 'Legal proceeding',
                                                'rej' => 'Rejected',
                                                'rvk' => 'Revoked',
                                                'tmb' => 'Time barred',
                                                'prp' => 'Proposal',
                                                'pay' => 'Payment process',
                                                'stl' => 'Settled',
                                                'cbk' => 'Chargeback',
                                                'aprv' => 'Approved',
                                                'inpr' => 'In Progress'
                                            );

                                foreach ($formalClaimActivitySql as $row) {
//                                    $status_key[] = isset($row['status_key']) ? $statusLabels[$row['status_key']] : '';
                                    $status_key[] = isset($row['status_key']) ? $row['status_key'] : '';
                                    $claim_count[] = isset($row['claim_count']) ? (int)$row['claim_count'] : 0;
                                }
                            }
                            
                             $formalClaimActivityResultData = array('status_key' => $status_key, 'claim_count' => $claim_count);
//                        Formal Claim Activity end
                        
                        
                        // open formal Claim record every month type wise start
         
                        $totalOpenRecordsDataQuery = $this->issue->getTotalOpenRecordsData($type_id, $org_id, $where_status, $where_date, $where_airline);
                        $totalOpenFormalRecordsData = array();

                        foreach ($totalOpenRecordsDataQuery as $row) {
                            $month = $row['month_name'];
                            $count = (int) $row['record_count'];
                            if ($row['field_value'] === 'fpr') {
                                $totalOpenFormalRecordsData[$month] = $count;
                            }
                        }


                        // open formal Claim  record every month type wise end
                        
                        // closed formal Claim record every month type wise start
         
                        $totalClosedRecordsDataQuery = $this->issue->getTotalClosedRecordsData($type_id, $org_id, $where_status, $where_date, $where_airline);
                        $totalClosedFormalRecordsData = array();

                        foreach ($totalClosedRecordsDataQuery as $row) {
                            $month = $row['month_name'];
                            $count = (int) $row['record_count'];
                            if ($row['field_value'] === 'fpr') {
                                $totalClosedFormalRecordsData[$month] = $count;
                            }
                        }


                        // closed formal Claim  record every month type wise end
                        
                        
                        
                        // Loss Details start
         
                        $totalFormalLossDetailsQuery = $this->issue->getTotalFormalLossDetails($type_id, $org_id, $where_status, $where_date, $where_airline);
                        $totalFormalLossDetailsData = array();

                        foreach ($totalFormalLossDetailsQuery as $row) {                            
                            $totalFormalLossDetailsData[$row['loss_type']] = (int)$row['claim_count'] ;
                        }


                        // Loss Details end

                        
                        // Special Handling Codes start
         
                        $totalFormalSpecialHandlingCodesQuery = $this->issue->getFormalSpecialHandlingCodes($type_id, $org_id, $where_status, $where_date, $where_airline);
                        // Create readable label map in PHP
$specialHandlingLabelMap = array(
    'cgo' => 'General Cargo',
    'val' => 'Valuable Cargo',
    'dgr' => 'Dangerous Goods',
    'cao' => 'Cargo Aircraft Only',
    'pax' => 'Passenger Aircraft OK',
    'rcx' => 'DG: Explosives 1.3C',
    'rgx' => 'DG: Explosives 1.3G',
    'rxb' => 'DG: Explosives 1.4B',
    'rxc' => 'DG: Explosives 1.4C',
    'rxd' => 'DG: Explosives 1.4D',
    'rxe' => 'DG: Explosives 1.4E',
    'rxg' => 'DG: Explosives 1.4G',
    'rxs' => 'DG: Explosives 1.4S',
    'rfg' => 'DG 2.1: Flammable Gas',
    'rfl' => 'DG 2.1: Flammable Liquid',
    'pef' => 'Perishable: Fresh Produce',
    'pep' => 'Perishable: Pharmaceuticals',
    'pes' => 'Perishable: Seafood',
    'per' => 'Perishable: General',
    'fra' => 'Fragile',
    'eli' => 'Electronic Items',
    'spx' => 'Express Shipment',
    'avi' => 'Live Animals',
    'smu' => 'Small Shipments',
    'vhc' => 'Vehicles'
);

$totalFormalSpecialHandlingCodesData = array();

foreach ($totalFormalSpecialHandlingCodesQuery as $row) {
    $code = strtolower(trim($row['special_code']));
    $label = isset($specialHandlingLabelMap[$code]) ? $specialHandlingLabelMap[$code] : strtoupper($code);

    $totalFormalSpecialHandlingCodesData[$label] = (int)$row['claim_count'];
}



                        // Special Handling Codes end
                        
                        
                        // === Output JSON ===
    $this->send_JSON(array(
        'success' => true,
        'formal_total_count' => $formal_total_count,
        'formal_top_10_originDataset' => $formal_top_10_originDataset,
        'formal_top_10_destinationDataset' => $formal_top_10_destinationDataset,
        'formalClaimActivityResultData' => $formalClaimActivityResultData,
        'totalFormalLossDetailsData' => $totalFormalLossDetailsData,
        'totalFormalSpecialHandlingCodesData' => $totalFormalSpecialHandlingCodesData,
        'totalOpenFormalRecordsData' => $totalOpenFormalRecordsData,
        'totalClosedFormalRecordsData' => $totalClosedFormalRecordsData,
    ));
                        
                    }



//                    IWI Air Cargo claim chart page new design end 

                    public function action_Load_Claim_aspects_Record_Chart(MVC_Request $rq) {

                        $ids = $rq->ids;
                        $startDate = str_replace('/', '-', $rq->startDate);
                        $endDate = str_replace('/', '-', $rq->endDate);

                        $fromdate = $startDate != '' ? date("Y-m-d", strtotime($startDate)) : '';
                        $todate = $endDate != '' ? date("Y-m-d", strtotime($endDate)) : '';

                        $filterStatus = $rq->filterStatus;
                        if ($filterStatus == 'all') {
                            $where1 = "  ";
                        } elseif ($filterStatus == 'active') {
                            $where1 = " AND `iod`.process NOT IN('closed', 'hidden') ";
                        } elseif ($filterStatus == 'closed') {
                            $where1 = " AND `iod`.process IN('closed') ";
                        }

                        $where = ''; //' AND YEAR(FROM_UNIXTIME(`iod`.`linktime`)) = 2023';
                        if ($fromdate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iod`.`linktime`)) >= '" . $fromdate . "'";
                        }
                        if ($todate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iod`.`linktime`)) <= '" . $todate . "'";
                        }

                        //AND `iod`.process NOT IN('closed', 'hidden')  

                        $type_id = $this->rt_Return();
                        $org_id = MVC_Core::$ss->user['org_id'];
                        $rt_id = $this->rt_Return();

                        //Top 10 Claim aspects Destination  start
                        $destClaimAspectsSql = "SELECT COUNT(*)AS `y_axis`,`ixf`.`value`,airport.IATA as x_axis
            FROM `issues` AS iss
            LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
            LEFT JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 9)
            INNER JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 67)
            LEFT JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
            WHERE `iss`.type_id = $type_id  AND `iod`.org_id = $org_id 
             $where1 $where
            GROUP BY x_axis ORDER BY `y_axis` DESC LIMIT 10";

                        $destClaimAspectsResult = MVC_Core::$db->query($destClaimAspectsSql);
                        if (!$destClaimAspectsResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
                        $destClaimAspectsData = array();
                        foreach ($destClaimAspectsResult as $row) {
                            $value = json_decode($row['value']);

                            if (count($value) == 1) {
                                $destClaimAspectsArrayData = array(
                                    'y_axis' => $row['y_axis'],
                                    'x_axis' => $row['x_axis'] . '-' . (!empty($value[0]) ? $this->claim_type_short_name($value[0]) : ''),
                                );
                                $destClaimAspectsData[] = $destClaimAspectsArrayData;
                            } elseif (count($value) == 2) {

                                $destClaimAspectsArrayData = array(
                                    'y_axis' => $row['y_axis'],
                                    'x_axis' => $row['x_axis'] . '-' . (!empty($value[0]) ? $this->claim_type_short_name($value[0]) : ''),
                                );
                                $destClaimAspectsData[] = $destClaimAspectsArrayData;

                                $destClaimAspectsArrayData = array(
                                    'y_axis' => $row['y_axis'],
                                    'x_axis' => $row['x_axis'] . '-' . (!empty($value[1]) ? $this->claim_type_short_name($value[1]) : ''),
                                );
                                $destClaimAspectsData[] = $destClaimAspectsArrayData;
                            } elseif (count($value) == 3) {

                                $destClaimAspectsArrayData = array(
                                    'y_axis' => $row['y_axis'],
                                    'x_axis' => $row['x_axis'] . '-' . (!empty($value[0]) ? $this->claim_type_short_name($value[0]) : ''),
                                );
                                $destClaimAspectsData[] = $destClaimAspectsArrayData;

                                $destClaimAspectsArrayData = array(
                                    'y_axis' => $row['y_axis'],
                                    'x_axis' => $row['x_axis'] . '-' . (!empty($value[1]) ? $this->claim_type_short_name($value[1]) : ''),
                                );
                                $destClaimAspectsData[] = $destClaimAspectsArrayData;

                                $destClaimAspectsArrayData = array(
                                    'y_axis' => $row['y_axis'],
                                    'x_axis' => $row['x_axis'] . '-' . (!empty($value[2]) ? $this->claim_type_short_name($value[2]) : ''),
                                );
                                $destClaimAspectsData[] = $destClaimAspectsArrayData;
                            }
                        }
                        foreach ($destClaimAspectsData as $row) {
                            $dcad_y_axis[] = $row['y_axis'];
                            $dcad_x_axis[] = $row['x_axis'];
                        }
                        $destClaimAspectsDataArray = array(
                            'y_axis' => $dcad_y_axis,
                            'x_axis' => $dcad_x_axis,
                        );

                        //Top 10 Claim aspects Destination  end
                        //Top 10 Claim aspects Origin  start
                        $orgClaimAspectsSql = "SELECT COUNT(*)AS `y_axis`,`ixf`.`value`,airport.IATA as x_axis
            FROM `issues` AS iss
            LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
            LEFT JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 9)
            INNER JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 66)
            LEFT JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
            WHERE `iss`.type_id = $type_id  AND `iod`.org_id = $org_id
             $where1 $where
            GROUP BY x_axis ORDER BY `y_axis` DESC LIMIT 10";

                        $orgClaimAspectsResult = MVC_Core::$db->query($orgClaimAspectsSql);
                        if (!$orgClaimAspectsResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
                        $orgClaimAspectsData = array();
                        foreach ($orgClaimAspectsResult as $row) {
                            $value = json_decode($row['value']);

                            if (count($value) == 1) {
                                $orgClaimAspectsArrayData = array(
                                    'y_axis' => $row['y_axis'],
                                    'x_axis' => $row['x_axis'] . '-' . (!empty($value[0]) ? $this->claim_type_short_name($value[0]) : ''),
                                );
                                $orgClaimAspectsData[] = $orgClaimAspectsArrayData;
                            } elseif (count($value) == 2) {

                                $orgClaimAspectsArrayData = array(
                                    'y_axis' => $row['y_axis'],
                                    'x_axis' => $row['x_axis'] . '-' . (!empty($value[0]) ? $this->claim_type_short_name($value[0]) : ''),
                                );
                                $orgClaimAspectsData[] = $orgClaimAspectsArrayData;

                                $orgClaimAspectsArrayData = array(
                                    'y_axis' => $row['y_axis'],
                                    'x_axis' => $row['x_axis'] . '-' . (!empty($value[1]) ? $this->claim_type_short_name($value[1]) : ''),
                                );
                                $orgClaimAspectsData[] = $orgClaimAspectsArrayData;
                            } elseif (count($value) == 3) {

                                $orgClaimAspectsArrayData = array(
                                    'y_axis' => $row['y_axis'],
                                    'x_axis' => $row['x_axis'] . '-' . (!empty($value[0]) ? $this->claim_type_short_name($value[0]) : ''),
                                );
                                $orgClaimAspectsData[] = $orgClaimAspectsArrayData;

                                $orgClaimAspectsArrayData = array(
                                    'y_axis' => $row['y_axis'],
                                    'x_axis' => $row['x_axis'] . '-' . (!empty($value[1]) ? $this->claim_type_short_name($value[1]) : ''),
                                );
                                $orgClaimAspectsData[] = $orgClaimAspectsArrayData;

                                $orgClaimAspectsArrayData = array(
                                    'y_axis' => $row['y_axis'],
                                    'x_axis' => $row['x_axis'] . '-' . (!empty($value[2]) ? $this->claim_type_short_name($value[2]) : ''),
                                );
                                $orgClaimAspectsData[] = $orgClaimAspectsArrayData;
                            }
                        }
                        foreach ($orgClaimAspectsData as $row) {
                            $ocad_y_axis[] = $row['y_axis'];
                            $ocad_x_axis[] = $row['x_axis'];
                        }
                        $orgClaimAspectsDataArray = array(
                            'y_axis' => $ocad_y_axis,
                            'x_axis' => $ocad_x_axis,
                        );
                        $totalClaimAspectsData = array_sum($ocad_y_axis) + array_sum($dcad_y_axis);
                        //Top 10 Claim aspects Origin  end
                        //Top 20 Claim aspects Count start
                        $claimAspectsCountSql = "SELECT COUNT(*)AS `y_axis`,`ixf`.`value`,airport.IATA as x_axis
            FROM `issues` AS iss
            LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
            LEFT JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 9)
            INNER JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 66)
            LEFT JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
            WHERE `iss`.type_id = $type_id  AND `iod`.org_id = $org_id 
             $where1 $where
            GROUP BY `ixf`.`value` ORDER BY `y_axis` DESC LIMIT 20";

                        $claimAspectsCountResult = MVC_Core::$db->query($claimAspectsCountSql);
                        if (!$claimAspectsCountResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");
                        $claimAspectsCountData = array();

                        foreach ($claimAspectsCountResult as $row) {
                            $value = json_decode($row['value']);

                            $_value = array();
                            foreach ($value as $key => $val) {
                                $_value[] = $this->claim_type_short_name($val);
                            }

                            $claimAspectsCountArrayData = array(
                                'y_axis' => $row['y_axis'],
                                'x_axis' => implode('-', $_value),
                            );
                            $claimAspectsCountData[] = $claimAspectsCountArrayData;
                        }



                        foreach ($claimAspectsCountData as $row) {
                            $cac_y_axis[] = $row['y_axis'];
                            $cac_x_axis[] = $row['x_axis'];
                        }
                        $claimAspectsCountDataArray = array(
                            'y_axis' => $cac_y_axis,
                            'x_axis' => $cac_x_axis,
                        );

                        //Top 20 Claim aspects Count end

                        $this->send_JSON(array('success' => true, 'destClaimAspectsData' => $destClaimAspectsDataArray,
                            'claimAspectsCountData' => $claimAspectsCountDataArray, 'totalClaimAspectsData' => $totalClaimAspectsData,
                            'orgClaimAspectsData' => $orgClaimAspectsDataArray));
                    }

                    public function action_Load_Claim_Root_Cause_Record_Chart(MVC_Request $rq) {

                        $ids = $rq->ids;
                        $startDate = str_replace('/', '-', $rq->startDate);
                        $endDate = str_replace('/', '-', $rq->endDate);

                        $fromdate = $startDate != '' ? date("Y-m-d", strtotime($startDate)) : '';
                        $todate = $endDate != '' ? date("Y-m-d", strtotime($endDate)) : '';

                        $where = ''; //' AND YEAR(FROM_UNIXTIME(`iod`.`linktime`)) = 2023';
                        if ($fromdate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iod`.`linktime`)) >= '" . $fromdate . "'";
                        }
                        if ($todate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iod`.`linktime`)) <= '" . $todate . "'";
                        }

                        $filterStatus = $rq->filterStatus;
                        if ($filterStatus == 'all') {
                            $where1 = "  ";
                        } elseif ($filterStatus == 'active') {
                            $where1 = " AND `iod`.process NOT IN('closed', 'hidden') ";
                        } elseif ($filterStatus == 'closed') {
                            $where1 = " AND `iod`.process IN('closed') ";
                        }
                        //AND `iod`.process NOT IN('closed', 'hidden')  

                        $type_id = $this->rt_Return();
                        $org_id = MVC_Core::$ss->user['org_id'];
                        $rt_id = $this->rt_Return();

                        //Top 10 irregularity by destination sql start
                        $irregularityDestSql = "SELECT airport.IATA as x_axis,ixf.value,COUNT(*)AS `y_axis`
          FROM `issues` AS iss
          LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
          INNER JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 67)
          INNER JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 2083)
          LEFT JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
          WHERE `iss`.type_id = $type_id  AND `iod`.org_id = $org_id 
           $where1 $where
          GROUP BY x_axis ORDER BY `y_axis`  DESC LIMIT 10";

                        $irregularityDestResult = MVC_Core::$db->query($irregularityDestSql);
                        if (!$irregularityDestResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($irregularityDestResult as $row) {
                            $idest_y_axis[] = $row['y_axis'];
                            $idest_x_axis[] = strtoupper($row['x_axis'] . ":" . $row['value']);
                        }
                        $irregularityDestResultData = array(
                            'y_axis' => $idest_y_axis,
                            'x_axis' => $idest_x_axis,
                        );
                        //Top 10 irregularity by destination sql end
                        //Top 10 irregularity by destination sql start
                        $irregularityOriginSql = "SELECT airport.IATA as x_axis,ixf.value,COUNT(*)AS `y_axis`
          FROM `issues` AS iss
          LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
          INNER JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 66)
          INNER JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 2083)
          LEFT JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
          WHERE `iss`.type_id = $type_id  AND `iod`.org_id = $org_id 
           $where1 $where
          GROUP BY x_axis ORDER BY `y_axis`  DESC LIMIT 10";

                        $irregularityOriginResult = MVC_Core::$db->query($irregularityOriginSql);
                        if (!$irregularityOriginResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($irregularityOriginResult as $row) {
                            $iorigin_y_axis[] = $row['y_axis'];
                            $iorigin_x_axis[] = strtoupper($row['x_axis'] . ":" . $row['value']);
                        }
                        $irregularityOriginResultData = array(
                            'y_axis' => $iorigin_y_axis,
                            'x_axis' => $iorigin_x_axis,
                        );
                        //Top 10 irregularity by destination sql end
                        //Top-10 conclusion by origin start
                        $conclusionOriginSql = "SELECT airport.IATA as x_axis,ixf.value as value,COUNT(*)AS `y_axis`
          FROM `issues` AS iss
          LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
          INNER JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 66)
          INNER JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 2577)
          LEFT JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
          WHERE `iss`.type_id = $type_id  AND `iod`.org_id = $org_id 
           $where1 $where
          GROUP BY value  ORDER BY `y_axis`  DESC LIMIT 10";

                        $conclusionOriginResult = MVC_Core::$db->query($conclusionOriginSql);
                        if (!$conclusionOriginResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($conclusionOriginResult as $row) {
                            $corigin_y_axis[] = $row['y_axis'];
                            $corigin_x_axis[] = strtoupper($row['x_axis'] . ":" . $row['value']);
                        }
                        $conclusionOriginResultData = array(
                            'y_axis' => $corigin_y_axis,
                            'x_axis' => $corigin_x_axis,
                        );
                        //Top-10 conclusion by origin end
                        //Top-10 conclusion by destination start
                        $conclusionDestiSql = "SELECT airport.IATA as x_axis,ixf.value as value,COUNT(*)AS `y_axis`
            FROM `issues` AS iss
            LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
            INNER JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 67)
            INNER JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 2577)
            LEFT JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
            WHERE `iss`.type_id = $type_id  AND `iod`.org_id = $org_id 
             $where1 $where
            GROUP BY value  ORDER BY `y_axis`  DESC LIMIT 10";

                        $conclusionDestiResult = MVC_Core::$db->query($conclusionDestiSql);
                        if (!$conclusionDestiResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($conclusionDestiResult as $row) {
                            $cdest_y_axis[] = $row['y_axis'];
                            $cdest_x_axis[] = strtoupper($row['x_axis'] . ":" . $row['value']);
                        }
                        $conclusionDestiResultData = array(
                            'y_axis' => $cdest_y_axis,
                            'x_axis' => $cdest_x_axis,
                        );
                        //Top-10 conclusion by destination end

                        $this->send_JSON(array('success' => true, 'irregularityDestData' => $irregularityDestResultData,
                            'irregularityOriginData' => $irregularityOriginResultData, 'conclusionDestiData' => $conclusionDestiResultData,
                            'conclusionOriginData' => $conclusionOriginResultData));
                    }

                    public function action_Load_Claim_Financials_Record_Chart(MVC_Request $rq) {

                        $ids = $rq->ids;

                        $startDate = str_replace('/', '-', $rq->startDate);
                        $endDate = str_replace('/', '-', $rq->endDate);

                        $fromdate = $startDate != '' ? date("Y-m-d", strtotime($startDate)) : '';
                        $todate = $endDate != '' ? date("Y-m-d", strtotime($endDate)) : '';

                        $where = ''; //' AND YEAR(FROM_UNIXTIME(`iod`.`linktime`)) = 2023';
                        if ($fromdate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iod`.`linktime`)) >= '" . $fromdate . "'";
                        }
                        if ($todate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iod`.`linktime`)) <= '" . $todate . "'";
                        }

                        $filterStatus = $rq->filterStatus;
                        if ($filterStatus == 'all') {
                            $where1 = "  ";
                        } elseif ($filterStatus == 'active') {
                            $where1 = " AND `iod`.process NOT IN('closed', 'hidden') ";
                        } elseif ($filterStatus == 'closed') {
                            $where1 = " AND `iod`.process IN('closed') ";
                        }

                        //AND `iod`.process NOT IN('closed', 'hidden')  

                        $type_id = $this->rt_Return();
                        $org_id = MVC_Core::$ss->user['org_id'];
                        $rt_id = $this->rt_Return();

                        // Top 10 Origin Financials claimAmount sql start (tb=issue_xfdef,1729=Final Claim amount,2010=Invoice Value)
                        $originFinancialsClaimAmountSql = "SELECT ixf.value as y_axis,airport.IATA as x_axis
            FROM `issues` AS iss
            LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
            INNER JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 66)
            LEFT JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
            INNER JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 1729)
            WHERE `iss`.type_id = $type_id  AND `iod`.org_id = $org_id  
             $where1 $where
            GROUP BY x_axis ORDER BY `y_axis` DESC LIMIT 10";

                        $originFinancialsClaimAmountResult = MVC_Core::$db->query($originFinancialsClaimAmountSql);
                        if (!$originFinancialsClaimAmountResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($originFinancialsClaimAmountResult as $row) {
                            $ofca_y_axis[] = $row['y_axis'];
                            $ofca_x_axis[] = $row['x_axis'];
                        }

                        $ofca_x_axisCommaList = '"' . implode('" , "', $ofca_x_axis) . '"';

                        $originFinancialsPaidAmountSql = "SELECT ixf.value as z_axis,airport.IATA as x_axis
            FROM `issues` AS iss
            LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
            INNER JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 66)
            LEFT JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
            INNER JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 2010)
            WHERE `iss`.type_id = $type_id AND `iod`.org_id = $org_id  $where1 
            AND airport.IATA IN ($ofca_x_axisCommaList)
            $where
            GROUP BY x_axis ORDER BY `z_axis` DESC LIMIT 10";

                        $originFinancialsPaidAmountResult = MVC_Core::$db->query($originFinancialsPaidAmountSql);
                        if (!$originFinancialsPaidAmountResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($originFinancialsPaidAmountResult as $row) {
                            $originFinancialsPaidAmountResultArrayData[$row['x_axis']] = $row['z_axis'];
                        }


                        foreach ($ofca_x_axis as $val) {
                            $value = !empty($originFinancialsPaidAmountResultArrayData[$val]) ? $originFinancialsPaidAmountResultArrayData[$val] : 0;
                            $zz_axis[] = $value;
                        }

                        $originFinancialsClaimAmountData = array(
                            'y_axis' => $ofca_y_axis,
                            'x_axis' => $ofca_x_axis,
                            'z_axis' => $zz_axis,
                        );
                        //Top 10 Origin Financials claimAmount sql end
                        // Top 10 Destination Financials claimAmount sql start ((tb=issue_xfdef,1729=Final Claim amount,2010=Invoice Value))
                        $destiFinancialsClaimAmountSql = "SELECT ixf.value as y_axis,airport.IATA as x_axis
            FROM `issues` AS iss
            LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
            INNER JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 67)
            LEFT JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
            INNER JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 1729)
            WHERE `iss`.type_id = $type_id  AND `iod`.org_id = $org_id  
             $where1 $where
            GROUP BY x_axis ORDER BY `y_axis` DESC LIMIT 10";

                        $destiFinancialsClaimAmountResult = MVC_Core::$db->query($destiFinancialsClaimAmountSql);
                        if (!$destiFinancialsClaimAmountResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($destiFinancialsClaimAmountResult as $row) {
                            $dfca_y_axis[] = $row['y_axis'];
                            $dfca_x_axis[] = $row['x_axis'];
                        }

                        $dfca_x_axisCommaList = '"' . implode('" , "', $dfca_x_axis) . '"';

                        $destiFinancialsPaidAmountSql = "SELECT ixf.value as z_axis,airport.IATA as x_axis
            FROM `issues` AS iss
            LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
            INNER JOIN `issue_fields` AS ixf_1 ON (`ixf_1`.issue_id = `iss`.id AND `ixf_1`.fk_id = 67)
            LEFT JOIN `data_airports` AS airport ON (ixf_1.int_value = `airport`.id)
            INNER JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 2010)
            WHERE `iss`.type_id = $type_id AND `iod`.org_id = $org_id  $where1 
            AND airport.IATA IN ($dfca_x_axisCommaList) 
             $where
            GROUP BY x_axis ORDER BY `z_axis` DESC LIMIT 10";

                        $destiFinancialsPaidAmountResult = MVC_Core::$db->query($destiFinancialsPaidAmountSql);
                        if (!$destiFinancialsPaidAmountResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($destiFinancialsPaidAmountResult as $row) {
                            $destiFinancialsPaidAmountResultArrayData[$row['x_axis']] = $row['z_axis'];
                        }


                        foreach ($dfca_x_axis as $val) {
                            $value = !empty($destiFinancialsPaidAmountResultArrayData[$val]) ? $destiFinancialsPaidAmountResultArrayData[$val] : 0;
                            $dfca_z_axis[] = $value;
                        }

                        $destiFinancialsClaimAmountData = array(
                            'y_axis' => $dfca_y_axis,
                            'x_axis' => $dfca_x_axis,
                            'z_axis' => $dfca_z_axis
                        );
                        //Top 10 Destination Financials claimAmount sql end
                        // totalClaimActivityData start
                        $totalClaimActivityDataSql = "SELECT ROUND(SUM(y_axis),2) as y_axis FROM (
                (SELECT ixf.value as y_axis,airport.IATA as x_axis 
                FROM `issues`AS iss 
                LEFT JOIN `issue_orgdata`AS iod ON `iod`.issue_id=`iss`.id 
                INNER JOIN `issue_fields`AS ixf_1 ON(`ixf_1`.issue_id=`iss`.id AND `ixf_1`.fk_id=66)
                LEFT JOIN `data_airports`AS airport ON(ixf_1.int_value=`airport`.id)
                INNER JOIN `issue_fields`AS ixf ON(`ixf`.issue_id=`iss`.id AND `ixf`.fk_id=1729)
                WHERE `iss`.type_id=$type_id AND `iod`.org_id=$org_id 
                $where1 $where
                GROUP BY x_axis ORDER BY `y_axis`DESC)
                
                UNION ALL
                
                (SELECT ixf.value as y_axis,airport.IATA as x_axis 
                FROM `issues`AS iss 
                LEFT JOIN `issue_orgdata`AS iod ON `iod`.issue_id=`iss`.id 
                INNER JOIN `issue_fields`AS ixf_1 ON(`ixf_1`.issue_id=`iss`.id AND `ixf_1`.fk_id=67)
                LEFT JOIN `data_airports`AS airport ON(ixf_1.int_value=`airport`.id)
                INNER JOIN `issue_fields`AS ixf ON(`ixf`.issue_id=`iss`.id AND `ixf`.fk_id=1729)
                WHERE `iss`.type_id=$type_id AND `iod`.org_id=$org_id 
                 $where1 $where
                GROUP BY x_axis ORDER BY `y_axis`DESC)
            ) t1";
                        $totalClaimActivityResult = MVC_Core::$db->query($totalClaimActivityDataSql);
                        if (!$totalClaimActivityResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($totalClaimActivityResult as $row) {
                            $totalClaimActivityData = $row['y_axis'];
                        }
                        // totalClaimActivityData end
                        // paid amount Data start
                        $totalPaidAmountDataSql = "SELECT ROUND(SUM(y_axis),2) as y_axis FROM (
                (SELECT ixf.value as y_axis,airport.IATA as x_axis 
                FROM `issues`AS iss 
                LEFT JOIN `issue_orgdata`AS iod ON `iod`.issue_id=`iss`.id 
                INNER JOIN `issue_fields`AS ixf_1 ON(`ixf_1`.issue_id=`iss`.id AND `ixf_1`.fk_id=66)
                LEFT JOIN `data_airports`AS airport ON(ixf_1.int_value=`airport`.id)
                INNER JOIN `issue_fields`AS ixf ON(`ixf`.issue_id=`iss`.id AND `ixf`.fk_id=2010)
                WHERE `iss`.type_id=$type_id AND `iod`.org_id=$org_id 
                $where1 $where
                GROUP BY x_axis ORDER BY `y_axis`DESC)
                
                UNION ALL
                
                (SELECT ixf.value as y_axis,airport.IATA as x_axis 
                FROM `issues`AS iss 
                LEFT JOIN `issue_orgdata`AS iod ON `iod`.issue_id=`iss`.id 
                INNER JOIN `issue_fields`AS ixf_1 ON(`ixf_1`.issue_id=`iss`.id AND `ixf_1`.fk_id=67)
                LEFT JOIN `data_airports`AS airport ON(ixf_1.int_value=`airport`.id)
                INNER JOIN `issue_fields`AS ixf ON(`ixf`.issue_id=`iss`.id AND `ixf`.fk_id=2010)
                WHERE `iss`.type_id=$type_id AND `iod`.org_id=$org_id 
                $where1  $where 
                GROUP BY x_axis  ORDER BY `y_axis`DESC)
            ) t1";

                        $totalPaidAmountDataResult = MVC_Core::$db->query($totalPaidAmountDataSql);
                        if (!$totalPaidAmountDataResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($totalPaidAmountDataResult as $row) {
                            $totalPaidAmountData = $row['y_axis'];
                        }
                        // paid amount Data end

                        $this->send_JSON(array('success' => true, "originFinancialsClaimAmount" => $originFinancialsClaimAmountData,
                            "destinationFinancialsClaimAmount" => $destiFinancialsClaimAmountData,
                            'totalClaimActivityData' => $totalClaimActivityData, 'totalPaidAmountData' => $totalPaidAmountData
                        ));
                    }

                    public function action_Load_Claim_Service_Level_Record_Chart(MVC_Request $rq) {

                        $ids = $rq->ids;

                        $startDate = str_replace('/', '-', $rq->startDate); 
                        $endDate = str_replace('/', '-', $rq->endDate);

                        $fromdate = $startDate != '' ? date("Y-m-d", strtotime($startDate)) : '';
                        $todate = $endDate != '' ? date("Y-m-d", strtotime($endDate)) : '';

                        $where = ''; //' AND YEAR(FROM_UNIXTIME(`iod`.`linktime`)) = 2023';
                        if ($fromdate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iod`.`linktime`)) >= '" . $fromdate . "'";
                        }
                        if ($todate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iod`.`linktime`)) <= '" . $todate . "'";
                        }

                        $filterStatus = $rq->filterStatus;
                        if ($filterStatus == 'all') {
                            $where1 = "  ";
                        } elseif ($filterStatus == 'active') {
                            $where1 = " AND `iod`.process NOT IN('closed', 'hidden') ";
                        } elseif ($filterStatus == 'closed') {
                            $where1 = " AND `iod`.process IN('closed') ";
                        }

                        //AND `iod`.process NOT IN('closed', 'hidden')  

                        $type_id = $this->rt_Return();
                        $org_id = MVC_Core::$ss->user['org_id'];
                        $rt_id = $this->rt_Return();

                        //serviceLevel sql start
                        //["#-1: Overdue","#1: SLA 30","#2: SLA 60","#3: SLA 90","#11: SLA 30 w/ extend","#12: SLA 60 w/ extend","#13: SLA 90 w/ extend"]
                        // 2821 = Service Level(SLA level)
                        $serviceLevelSql = "SELECT COUNT(*) AS `y_axis`,`ixf`.int_value as x_axis
                FROM `issues` AS iss
                LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id
                INNER JOIN `issue_fields` AS ixf ON (`ixf`.issue_id = `iss`.id AND `ixf`.fk_id = 2821)
                WHERE `iss`.type_id = $type_id  AND `iod`.org_id = $org_id 
                $where1 $where
            GROUP BY `ixf`.int_value ORDER BY `y_axis` DESC LIMIT 10";

                        $serviceLevelResult = MVC_Core::$db->query($serviceLevelSql);
                        if (!$serviceLevelResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($serviceLevelResult as $row) {

                            switch ($row['x_axis']) {
                                case "-1":
                                    $x_axis = "Overdue";
                                    break;
                                case "1":
                                    $x_axis = "SLA 30";
                                    break;
                                case "2":
                                    $x_axis = "SLA 60";
                                    break;
                                case "3":
                                    $x_axis = "SLA 90";
                                    break;
                                case "11":
                                    $x_axis = "SLA 30 w/ extend";
                                    break;
                                case "12":
                                    $x_axis = "SLA 60 w/ extend";
                                    break;
                                case "13":
                                    $x_axis = "SLA 90 w/ extend";
                                    break;
                                default:
                                    $x_axis = "";
                            }

                            $slr_y_axis[] = $row['y_axis'];
                            $slr_x_axis[] = $x_axis;
                        }
                        $serviceLevelResultData = array(
                            'y_axis' => $slr_y_axis,
                            'x_axis' => $slr_x_axis,
                        );
                        //serviceLevel sql end

                        $this->send_JSON(array('success' => true, 'serviceLevelData' => $serviceLevelResultData));
                    }

                    public function action_Load_Claim_Claim_Activity_Record_Chart(MVC_Request $rq) {
                        $ids = $rq->ids;

                        $startDate = str_replace('/', '-', $rq->startDate);
                        $endDate = str_replace('/', '-', $rq->endDate);

                        $fromdate = $startDate != '' ? date("Y-m-d", strtotime($startDate)) : '';
                        $todate = $endDate != '' ? date("Y-m-d", strtotime($endDate)) : '';

                        $where = ''; //' AND YEAR(FROM_UNIXTIME(`iod`.`linktime`)) = 2023';
                        if ($fromdate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iod`.`linktime`)) >= '" . $fromdate . "'";
                        }
                        if ($todate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iod`.`linktime`)) <= '" . $todate . "'";
                        }

                        $filterStatus = $rq->filterStatus;
                        if ($filterStatus == 'all') {
                            $where1 = "  ";
                        } elseif ($filterStatus == 'active') {
                            $where1 = " AND `iod`.process NOT IN('closed', 'hidden') ";
                        } elseif ($filterStatus == 'closed') {
                            $where1 = " AND `iod`.process IN('closed') ";
                        }

                        //AND `iod`.process NOT IN('closed', 'hidden')  

                        $type_id = $this->rt_Return();
                        $org_id = MVC_Core::$ss->user['org_id'];
                        $rt_id = $this->rt_Return();

                        //claim activity sql start
                        $claimActivitySql = "SELECT COUNT(*) AS `y_axis`, iod.status_key AS `x_axis` 
            FROM `issues` AS iss 
            LEFT JOIN `issue_orgdata` AS iod ON `iod`.issue_id = `iss`.id 
            WHERE `iss`.type_id = $type_id  AND `iod`.org_id = $org_id 
              $where1 $where
            GROUP BY x_axis";
                        // limit and order by clause
                        $claimActivityResult = MVC_Core::$db->query($claimActivitySql);
                        if (!$claimActivityResult)
                            return MVC_Core::$debug->dump("Statistics query Failed", __METHOD__, null, "error");

                        foreach ($claimActivityResult as $row) {
                            $car_y_axis[] = $row['y_axis'];
                            $car_x_axis[] = $row['x_axis'];
                        }
                        $claimActivityResultData = array(
                            'y_axis' => $car_y_axis,
                            'x_axis' => $car_x_axis,
                        );
                        //claim activity sql end

                        $this->send_JSON(array('success' => true, 'claimActivityData' => $claimActivityResultData));
                    }

                    public function claim_type_short_name($name = '') {
                        switch ($name) {
                            case "Damage":
                                return "Damage";
                                break;
                            case "cl":
                                return "Total Loss";
                                break;
                            case "Delay":
                                return "Delay";
                                break;
                            case "pl":
                                return "Partial Loss";
                                break;
                            case "Pilferage":
                                return "Pilferage";
                                break;
                            default:
                                return "";
                        }
                    }

                    // 11122023 created by iwi |  end


                    // 09102024 Agent Report filter modify start
                    public function action_Load_Record_Agent_Record_Chart(MVC_Request $rq) {
                       
                        $startDate = str_replace('/', '-', $rq->startDate);
                        $endDate = str_replace('/', '-', $rq->endDate);

                        $fromdate = $startDate != '' ? date("Y-m-d", strtotime($startDate)) : '';
                        $todate = $endDate != '' ? date("Y-m-d", strtotime($endDate)) : '';

                        $where = '';
                       
                        if ($fromdate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iss`.`ctime`)) >= '" . $fromdate . "'";
                        }
                        if ($todate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iss`.`ctime`)) <= '" . $todate . "'";
                        }
                        //27092024 airline cargo bug fixed
                        $agentId = ($rq->agentId !='') ?  $rq->agentId  : '';
                        $having = ' HAVING 1 ';
                        if($agentId !=''){
                            $having .= ' AND company_name = "'.$agentId.'" ';
                        }
                        //27092024 airline cargo bug fixed

                        // 07102024 Agent Report filter modify
                        $originVal = ($rq->originVal !='') ?  $rq->originVal  : '';
                        if($originVal !=''){
                            $where .= ' AND airport.IATA = "'.$originVal.'" ';
                        }
                       
                        $reportStatus = ($rq->reportStatus !='') ?  $rq->reportStatus  : '';
                        if($reportStatus =='active'){
                            $having .= ' AND `status` != "closed" ';
                        }
                        if($reportStatus =='closed'){
                            $having .= ' AND `status` = "closed" ';
                        }



                        $org_id = MVC_Core::$ss->user['org_id'];

                        $car_y_axis = array();
                        $car_x_axis = array();
                        //agent report sql start
                        $agentNameDetails = array();
                        $agentReportResult = $this->issue->getAgentClaimRecordChart($org_id,$where,$having);
                        foreach ($agentReportResult as $row) {
                            $x_axis = $row['x_axis'];
                            if($x_axis == '' || $x_axis == '{' ){ $x_axis = 'N/A';}
                            $car_y_axis[] = $row['y_axis'];
                            $car_x_axis[] = $x_axis;

                            $agentNameDetails[] = array(
                                'agent_name'=>$x_axis,
                                'agent_id'=>$x_axis,
                            );
                        }
                        $agentReportResultData = array(
                             'y_axis' => $car_y_axis,
                             'x_axis' => $car_x_axis,
                        );
                        //agent report sql end

                        $claimTypeOrigin = array();
                        
                        $type_id = 1;
                        //formal data 
                        $formalClaimTypeByOriginResult = $this->issue->getClaimTypeRecordSql($org_id,'fpr',$where,$having);
                        $formalclaimOriginValue = array();
                        $totalFormalResultValue = 0;
                        foreach ($formalClaimTypeByOriginResult as $row) {
                            $formalclaimOriginValue[] = array(
                                'y_axis' => $row['y_axis'],
                                'x_axis' => $row['x_axis']
                            );
                            $claimTypeOrigin[] = $row['x_axis']; //25092024  added by iwi  agent chart section work

                            $totalFormalResultValue += $row['y_axis'];
                            $formalclaimDestinationValue[] = array(
                                'y_axis' => $row['y_axis'],
                                'x_axis' => $row['dest_name']
                            );
                        }
                       

                        // preliminary data
                        $preliminaryclaimOriginValue = array();
                        $preliminaryClaimTypeByOriginResult = $this->issue->getClaimTypeRecordSql($org_id,'prel',$where,$having);
                        $totalPreliminaryResultValue = 0;
                        foreach ($preliminaryClaimTypeByOriginResult as $row) {
                            $preliminaryclaimOriginValue[] = array(
                                'y_axis' => $row['y_axis'],
                                'x_axis' => $row['x_axis']
                            );
                            $totalPreliminaryResultValue += $row['y_axis'];

                            $preliminaryClaimDestinationValue[] = array(
                                'y_axis' => $row['y_axis'],
                                'x_axis' => $row['dest_name']
                            );

                            $claimTypeOrigin[] = $row['x_axis'];
                        }

                        $getAgentClaimRecordListDataResult = $this->issue->getAgentClaimRecordListData($org_id,'',$where,$having);
                        $getAgentClaimRecordListData = array();
                        foreach ($getAgentClaimRecordListDataResult as $row) {
                            $getAgentClaimRecordListData[] = $row;
                        }


                        $getAgentClaimRecordFormalAmount = $this->issue->getAgentClaimRecordAmountData($org_id,'fpr',$where,$having);
                        $getAgentClaimRecordFormalAmountValue = 0;
                        foreach ($getAgentClaimRecordFormalAmount as $row) {
                            $getAgentClaimRecordFormalAmountValue += $row['claim_amount_value'] > 0 ? $row['claim_amount_value'] : 0 ;
                        }
                        
                        $claimTypeOrigin = array_unique($claimTypeOrigin); //07102024 Agent Report filter modify
                            

                        $this->send_JSON(array('success' => true,
                        'formalClaimAmountValue'=>$getAgentClaimRecordFormalAmountValue,
                        'getAgentClaimRecordListData'=>$getAgentClaimRecordListData,
                        'agentReport'=>$agentReportResultData,
                        'formalClaimTypeByOriginValue'=>$formalclaimOriginValue,
                        'claimTypeOrigin'=>$claimTypeOrigin,//25092024  added by iwi  agent chart section work
                        'formalclaimDestinationValue'=>$formalclaimDestinationValue
                        ,'preliminaryclaimOriginValue'=>$preliminaryclaimOriginValue,
                        'preliminaryClaimDestinationValue'=>$preliminaryClaimDestinationValue,'agentNameDetails'=>$agentNameDetails,
                        'agentId'=>$agentId,'totalPreliminaryResult'=>$totalPreliminaryResultValue,
                        'totalFormalResult'=>$totalFormalResultValue,'agentId'=>$agentId));
                    }

                    public function action_Air_Cargo_Claim_Agent_Record_Export_In_Excel() {
                    
                        $startDate = str_replace('/', '-', $_GET['startDate']);
                        $endDate = str_replace('/', '-', $_GET['endDate']);
                    
                        $fromdate = $startDate != '' ? date("Y-m-d", strtotime($startDate)) : '';
                        $todate = $endDate != '' ? date("Y-m-d", strtotime($endDate)) : '';
                    
                        $where = '';
                        
                        if ($fromdate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iss`.`ctime`)) >= '" . $fromdate . "'";
                        }
                        if ($todate != '') {
                            $where .= " AND DATE(FROM_UNIXTIME(`iss`.`ctime`)) <= '" . $todate . "'";
                        }
                        //27092024 airline cargo bug fixed
                        $agentId = ($_GET['agentId'] !='') ?  $_GET['agentId']  : '';
                        $having = ' HAVING 1 ';
                        if($agentId !=''){
                            $having .= ' AND company_name = "'.$agentId.'" ';
                        }
                        //27092024 airline cargo bug fixed
                    
                        // 07102024 Agent Report filter modify
                        $originVal = ($_GET['originVal'] !='') ?  $_GET['originVal']  : '';
                        if($originVal !=''){
                            $where .= ' AND airport.IATA = "'.$originVal.'" ';
                        }
                        
                        $reportStatus = ($_GET['reportStatus'] !='') ?  $_GET['reportStatus']  : '';
                        if($reportStatus =='active'){
                            $having .= ' AND `status` != "closed" ';
                        }
                        if($reportStatus =='closed'){
                            $having .= ' AND `status` = "closed" ';
                        }
                        $org_id = MVC_Core::$ss->user['org_id'];
                        $getAgentClaimRecordListDataResult = $this->issue->getAgentClaimRecordListData($org_id,'',$where,$having);
                        $getAgentClaimRecordListData = array();
                        foreach ($getAgentClaimRecordListDataResult as $row) {
                            $getAgentClaimRecordListData[] = array(
                                'Awb No' => $row['awb_no'],
                                'Track' => $row['track'],
                                'Origin' => $row['origin_name'],
                                'Company Name' => ($row['company_name'] == '' || $row['company_name'] == '{') ? 'N/A' : $row['company_name'] ,
                                'Claim Amount' => $row['claim_amount'],
                            );

                        }

                        function filterCustomerData(&$str) {
                            $str = preg_replace("/\t/", "\\t", $str);
                            $str = preg_replace("/\r?\n/", "\\n", $str);
                            if (strstr($str, '"'))
                            $str = '"' . str_replace('"', '""', $str) . '"';
                        }
                       
                        // File Name & Content Header For Download
                        $file_name = time().".xls";  
                        header("Content-Disposition: attachment; filename=\"$file_name\"");
                        header("Content-Type: application/vnd.ms-excel");
                        
                        //To define column name in first row.
                        $column_names = false;
                        // run loop through each row in $customers_data
                        foreach ($getAgentClaimRecordListData as $row) {
                            if (!$column_names) {
                                echo implode("\t", array_keys($row)) . "\n";
                                $column_names = true;
                            }
                            // The array_walk() function runs each array element in a user-defined function.
                            array_walk($row, 'filterCustomerData');
                            echo implode("\t", array_values($row)) . "\n";
                        }
                        exit;
                    }
                    // 09102024 Agent Report filter modify end
                    
                    
                  
//                    IWI chart for quality event module 07112025  start
                    

            
     public function action_Load_QualityEvent_Chart(MVC_Request $rq) {



        // Determine if the record type is valid for the logged in user or not. Functions ( submit, list ) are not available if the record type is not available
        $acls_rt = $this->user->read_ACL('ist');
        if ($acls_rt === true || in_array($rq->rt_id, $acls_rt))
            $this->enabled = $rt_enabled = true; // Access to the module

            
// 18102023 - added by sourav start
        $rt_status = $rq->rt_status != '' ? $rq->rt_status : 'all';
        $claim_type = $rq->claim_type != '' ? $rq->claim_type : 'all';
        $claimant_type = $rq->claimant_type != '' ? $rq->claimant_type : 'all';
        $movement = $rq->movement != '' ? $rq->movement : 'all';
        $carrier_liability = $rq->carrier_liability != '' ? $rq->carrier_liability : 'all';
        $claim_aspects = $rq->claim_aspects != '' ? $rq->claim_aspects : 'all';

        $filter_chart = array();
        if ($claim_type != 'all') {
            $filter_chart = array('pstage'); //pstage
        }

        if ($claimant_type != 'all') {
            array_push($filter_chart, "subm_type"); //subm_type
        }

        if ($movement != 'all') {
            array_push($filter_chart, "move"); //move
        }

        if ($carrier_liability != 'all') {
            array_push($filter_chart, "liable"); //liable /xfield_enum_liable
        }


        if ($claim_aspects != 'all') {
            array_push($filter_chart, "claim_type"); //claim_type /xfield_set_claim_type
        }




        $rt_status_array = array('status' => $rt_status);
        $claim_type_array = array('claim_type' => $claim_type, 'claimant_type' => $claimant_type, 'movement' => $movement, 'carrier_liability' => $carrier_liability, 'claim_aspects' => $claim_aspects);

        $array_merge = array_merge($rt_status_array, $claim_type_array);
        $_SESSION["chart_filter"] = $array_merge;
        $_SESSION["filter_chart_name"] = $filter_chart;

        // 18102023 - added by sourav end
        // Hybrid response
        $data_response = array();

        // Top-Right Function buttons
        $functions = array();
        $functions['submit'] = true;
        $functions['list'] = true;

        // Read record type details
        $rt_def = $this->issue->ist_Read($rq->rt_id, null);
        $rt_id=(int) $rt_def['id'];

        $this->rt = $data_response['rt_def'] = array(
            'id' => (int) $rt_def['id'],
            'key' => $rt_def['key'],
            'name' => $rt_def['name'],
            'plural' => $rt_def['short_plural']
        );

        // Also load the matching CMS page for the record type key (might contain detailed description, screenshots, etc.)
        $m_cms = new App_Model_CMS();
        $rtinfo_cmsp = $m_cms->read("/rtinfo/" . $rt_def['key']);
        $this->_out['rt']['info'] = !empty($rtinfo_cmsp) ? $rtinfo_cmsp['contents'] : $rt_def['description'];

        // If enabled, build statistics information and chart configuration
        if (!empty($rt_enabled) && !empty($rt_def['settings']['plugin']) && !empty($rt_def['settings']['statistics'])) {
            // The statistics are calculated for logged in user (but it should support admin's Perspective as well, maybe later)
            $org_id = !empty($rq->org_id) ? $rq->org_id : MVC_Core::$ss->user['org_id'];

            $data_response['charts'] = array(); // Just a list, used in loops
            $data_response['charts_config'] = array(); // Will contain actual display configuration data + statistics (note: Can get quite big !)
            $this->stx = $this->factory_Model("Statistics", "stx");
            $this->rec_plugin = MVC_Core::factory_Plugin("record", $rt_def['key']);

            // User preferences. If "FALSE", it means no preferences have been saved and all charts have to be enabled
            $charts_prefs = $this->user->profile_Read(MVC_Core::$ss->user['id'], "charts_" . $rt_def['key']);

            if ($charts_prefs === false)
                $charts_prefs = $rt_def['settings']['statistics_methods']; // Default to all active and enabled, and in the original order


            $indexdata = 1;
            $finalChart = array();
                $param=array(
                        'period' => $rq->period ?: "months",
                        'subset' => $rq->subset ?: 0,
                        'rt_status' => $rt_status,
                        'chart_page' => 'quality_event',
                            );
            foreach ($rt_def['settings']['statistics_methods'] as $model) {

                $visible = in_array($model, $charts_prefs);
                
                if ($model == 'records_per_provision') {
                    $stats = $this->rec_plugin->org_Statistics($org_id, $rt_def['id'], $model, $param, $visible);
                } else if ($model == 'records_per_group') {
//                    $stats = $this->issue->QualityEventPerCategory($org_id, $rt_def['id'], $model, $param, $visible);
//                    $chart = $this->rec_plugin->build_Chart_Config($model, $param);
                    $stats = $this->rec_plugin->org_Statistics($org_id, $rt_id, 'records_per_group', $param, true);
        $chart = $this->rec_plugin->build_Chart_Config('records_per_group', array(
            'org_id' => $org_id,
            'rt_id' => $rt_id
        ));
//                    $stats['seriesGroups'] = $chart['seriesGroups'][0]['series'];
                } else if ($model == 'xfield_enum_inv_done') {
                    $stats = $this->rec_plugin->org_Statistics($org_id, $rt_def['id'], $model, $param);
                    $chart = $this->rec_plugin->build_Chart_Config($model, $param);
//                    $stats['seriesGroups'] = $chart['seriesGroups'][0]['series'];
                } else if ($model == 'xfield_enum_severity') {
                    $stats = $this->rec_plugin->org_Statistics($org_id, $rt_def['id'], $model, $param, $visible);
                    if (empty($stats))
                        return header("HTTP/1.0 500 Statistics could not be calculated. Cause unknown");
                    $chart = $this->rec_plugin->build_Chart_Config($model, $param);
//                    $stats['seriesGroups'] = $chart['seriesGroups'][0]['series'];
                } else {
                    $stats = $this->rec_plugin->org_Statistics($org_id, $rt_def['id'], $model, $param, $visible); // Last parameter = Visibility ; Non-visible charts are only silently checked for errors in the configuration
               $chart = $this->rec_plugin->build_Chart_Config($model, $param);
//                    $stats['seriesGroups'] = $chart['seriesGroups'][0]['series'];
                    } 

if (isset($chart['seriesGroups'][0]['series'])) {
                    $stats['seriesGroups'] = $chart['seriesGroups'][0]['series'];
                } else {
                    $stats['seriesGroups']=array();
                    } 


                if ($visible) {
                    if (empty($stats))
                        continue;
                    $finalChart[$model] = $stats;
                } else {
                    $finalChart[] = array();
                }
                $indexdata++;
            }
            
            $QualityEventCommodityType=$this->issue->QualityEventCommodityType($org_id, $rt_id, $param);
            $QualityEventIrregularityCode=$this->issue->QualityEventIrregularityCode($org_id, $rt_id, $param);

            // End charts - Do we have anything to show ? Enable the function
            if (!empty($data_response['charts']))
                $functions['charts'] = true;

            // Weird array processing that puts the ones in $charts_prefs first, then appends the ones which are not part of $charts_prefs at the end)
            $data_response['charts'] = array_merge(array_intersect($charts_prefs, $data_response['charts']), array_diff($data_response['charts'], $charts_prefs));
        }
        $this->functions = $data_response['functions'] = $functions;

        // Finished, send the response
//        echo "<pre>";
//        print_r($data_response);
//        print_r($finalChart);
//        echo '--------------------------------------';
        
        
//        Total Records start
        $getTotalRecordChartData = array();

        foreach ($finalChart as $chart => $row) {

            if ($chart == 'total_records') {

                $result = array(
                    "Active" => array(),
                    "Closed" => array()
                );

                foreach ($row as $item) {   // <-- FIXED (use $row, not $data)
//                    print_r($item);
                    $time = isset($item["Month"]) ? $item["Month"] : (isset($item["Year"]) ? $item["Year"] : (isset($item["Quarter"]) ? $item["Quarter"] : null));

                    if (isset($item["Active"])) {
                        $result["Active"][$time] = $item["Active"];
                    }

                    if (isset($item["Closed"])) {
                        $result["Closed"][$time] = $item["Closed"];
                    }
                }

                $getTotalRecordChartData = $result;  // assign final result
            }
        }

//Total Records end


//        Event Categories start
//        $getEventPerCategoryData = $finalChart['records_per_group'];
 $getEventPerCategory = $finalChart['records_per_group'];
      
      $series = array();
        $source = array();
        $seriesGroups = $getEventPerCategory['seriesGroups'];
        unset($getEventPerCategory['seriesGroups']);
        
        foreach ($seriesGroups as $sg) {
            $series[] = array(
                "field" => $sg["dataField"],
                "label" => $sg["displayText"]
            );
        }
        foreach ($getEventPerCategory as $row) {
            $source[] = $row;
        }

        $result = array(
            "series" => $series,
            "source" => $source
        );
    
      $getEventPerCategoryData = $result; 
//Event Categories end



//        Investigation Status start
        $xfield_enum_inv_done = $finalChart['xfield_enum_inv_done'];
//        print_r($xfield_enum_inv_done);
        $series = array();
        $source = array();
        $seriesGroups = $xfield_enum_inv_done['seriesGroups'];
        unset($xfield_enum_inv_done['seriesGroups']);
        foreach ($seriesGroups as $sg) {
            $series[] = array(
                "field" => $sg["dataField"],
                "label" => $sg["displayText"]
            );
        }
        foreach ($xfield_enum_inv_done as $row) {
            $source[] = $row;
        }

        $result = array(
            "series" => $series,
            "source" => $source
        );
        $getInvestigationStatus = $result;
//Investigation Status end



//
//        Event Severity start
        $xfield_enum_severity = $finalChart['xfield_enum_severity'];
$series = array();
        $source = array();
        $seriesGroups = $xfield_enum_severity['seriesGroups'];
        unset($xfield_enum_severity['seriesGroups']);
        foreach ($seriesGroups as $sg) {
            $series[] = array(
                "field" => $sg["dataField"],
                "label" => $sg["displayText"]
            );
        }
        foreach ($xfield_enum_severity as $row) {
            $source[] = $row;
        }

        $result = array(
            "series" => $series,
            "source" => $source
        );
    
      $getEventSeverity = $result;  
//        Event Severity end
      
      
//      Risk Classification start
      $xfield_set_risk_class=$finalChart['xfield_set_risk_class'];
      
      $series = array();
        $source = array();
        $seriesGroups = $xfield_set_risk_class['seriesGroups'];
        unset($xfield_set_risk_class['seriesGroups']);
        foreach ($seriesGroups as $sg) {
            $series[] = array(
                "field" => $sg["dataField"],
                "label" => $sg["displayText"]
            );
        }
        foreach ($xfield_set_risk_class as $row) {
            $source[] = $row;
        }

        $result = array(
            "series" => $series,
            "source" => $source
        );
    
      $getRiskClassification = $result; 
      
      
//      Risk Classification end
      
      
//      Commodity Type start
   
      $getCommodityType=$QualityEventCommodityType;
      
//      Commodity Type end
      
//      Irregularity Code start
   
      $getIrregularityCode=$QualityEventIrregularityCode;
      
//       Irregularity Code end

        $data_response = array(
            'getTotalRecordChartData' => $getTotalRecordChartData,
            'getEventPerCategoryData' => $getEventPerCategoryData,
            'getInvestigationStatus' => $getInvestigationStatus,
            'getEventSeverity' => $getEventSeverity,
            'getRiskClassification' => $getRiskClassification,
            'getCommodityType' => $getCommodityType,
            'getIrregularityCode' => $getIrregularityCode,
        );

        $this->send_JSON($data_response);
    }
    
    
    public function action_Load_QualityEventChartByType(MVC_Request $rq) {

    /* -------------------------------------------------------
       ACL + BASIC INPUTS
    ------------------------------------------------------- */
    $acls_rt = $this->user->read_ACL('ist');
    if ($acls_rt !== true && !in_array($rq->rt_id, $acls_rt)) {
        $this->send_JSON(array("error" => "Access denied"));
        exit();
    }

    $rt_status = !empty($rq->rt_status) ? $rq->rt_status : 'all';
    $org_id = !empty($rq->org_id) ? $rq->org_id : MVC_Core::$ss->user['org_id'];

    $rt_def = $this->issue->ist_Read($rq->rt_id, null);
    $rt_id = (int)$rt_def['id'];

    $this->rec_plugin = MVC_Core::factory_Plugin("record", $rt_def['key']);

    $param = array(
            'period' => $rq->period ?: "years", // Start with month as default (Last twelve months)
            'subset' => $rq->subset ?: 0 ,
    'rt_status'  => $rt_status,
                        'chart_page' => 'quality_event',
        );

    /* -------------------------------------------------------
       SELECTED DATASET LOGIC
    ------------------------------------------------------- */
    $type = $rq->data_for;

    /* -------------------------------------------------------
       1. TOTAL RECORDS
    ------------------------------------------------------- */
    if ($type === 'TotalRecord') {

        $stats = $this->rec_plugin->org_Statistics($org_id, $rt_id, 'total_records', $param, true);
        $chart = $this->rec_plugin->build_Chart_Config('total_records', $param);

        $stats['seriesGroups'] = isset($chart['seriesGroups'][0]['series'])
            ? $chart['seriesGroups'][0]['series']
            : array();

        $result = array("Active" => array(), "Closed" => array());

        foreach ($stats as $row) {
            if (!is_array($row)) continue;

            $time = null;

            if (isset($row["Month"]))   $time = $row["Month"];
            if (isset($row["Year"]))    $time = $row["Year"];
            if (isset($row["Quarter"])) $time = $row["Quarter"];

            if ($time === null) continue;

            if (isset($row["Active"])) $result["Active"][$time] = $row["Active"];
            if (isset($row["Closed"])) $result["Closed"][$time] = $row["Closed"];
        }

        $this->send_JSON(array("getTotalRecordChartData" => $result));
        exit();
    }

    /* -------------------------------------------------------
       2. EVENTS PER CATEGORY
    ------------------------------------------------------- */
    if ($type === 'EventsperCategory') {

        $stats = $this->rec_plugin->org_Statistics($org_id, $rt_id, 'records_per_group', $param, true);
        $chart = $this->rec_plugin->build_Chart_Config('records_per_group', array(
            'org_id' => $org_id,
            'rt_id' => $rt_id
        ));
$seriesGroups = isset($chart['seriesGroups'][0]['series'])
            ? $chart['seriesGroups'][0]['series']
            : array();

        $series = array();
        foreach ($seriesGroups as $sg) {
            $series[] = array(
                "field" => $sg["dataField"],
                "label" => $sg["displayText"]
            );
        }

        unset($stats['seriesGroups']);
        $source = array_values($stats);

        $this->send_JSON(array(
            "getEventPerCategoryData" => array(
                "series" => $series,
                "source" => $source
            )
        ));
        exit();
    }

    /* -------------------------------------------------------
       3. INVESTIGATION STATUS
    ------------------------------------------------------- */
    if ($type === 'InvestigationStatus') {

        $stats = $this->rec_plugin->org_Statistics($org_id, $rt_id, 'xfield_enum_inv_done', $param, true);
        $chart = $this->rec_plugin->build_Chart_Config('xfield_enum_inv_done', array(
            'org_id' => $org_id,
            'rt_id' => $rt_id
        ));

        $seriesGroups = isset($chart['seriesGroups'][0]['series'])
            ? $chart['seriesGroups'][0]['series']
            : array();

        $series = array();
        foreach ($seriesGroups as $sg) {
            $series[] = array(
                "field" => $sg["dataField"],
                "label" => $sg["displayText"]
            );
        }

        unset($stats['seriesGroups']);
        $source = array_values($stats);

        $this->send_JSON(array(
            "getInvestigationStatus" => array(
                "series" => $series,
                "source" => $source
            )
        ));
        exit();
    }

    /* -------------------------------------------------------
       4. EVENT SEVERITY
    ------------------------------------------------------- */
    if ($type === 'EventSeverity') {

        $stats = $this->rec_plugin->org_Statistics($org_id, $rt_id, 'xfield_enum_severity', $param, true);
        $chart = $this->rec_plugin->build_Chart_Config('xfield_enum_severity', array(
            'org_id' => $org_id,
            'rt_id' => $rt_id
        ));

        $seriesGroups = isset($chart['seriesGroups'][0]['series'])
            ? $chart['seriesGroups'][0]['series']
            : array();

        $series = array();
        foreach ($seriesGroups as $sg) {
            $series[] = array(
                "field" => $sg["dataField"],
                "label" => $sg["displayText"]
            );
        }

        unset($stats['seriesGroups']);
        $source = array_values($stats);

        $this->send_JSON(array(
            "getEventSeverity" => array(
                "series" => $series,
                "source" => $source
            )
        ));
        exit();
    }

    /* -------------------------------------------------------
       5. RISK CLASSIFICATION
    ------------------------------------------------------- */
    if ($type === 'RiskClassification') {

        $stats = $this->rec_plugin->org_Statistics($org_id, $rt_id, 'xfield_set_risk_class', $param, true);
        $chart = $this->rec_plugin->build_Chart_Config('xfield_set_risk_class', array(
            'org_id' => $org_id,
            'rt_id' => $rt_id
        ));

        $seriesGroups = isset($chart['seriesGroups'][0]['series'])
            ? $chart['seriesGroups'][0]['series']
            : array();

        $series = array();
        foreach ($seriesGroups as $sg) {
            $series[] = array(
                "field" => $sg["dataField"],
                "label" => $sg["displayText"]
            );
        }

        unset($stats['seriesGroups']);
        $source = array_values($stats);

        $this->send_JSON(array(
            "getRiskClassification" => array(
                "series" => $series,
                "source" => $source
            )
        ));
        exit();
    }

    /* -------------------------------------------------------
       6. COMMODITY TYPE
    ------------------------------------------------------- */
    if ($type === 'CommodityType') {

        $result = $this->issue->QualityEventCommodityType($org_id, $rt_id, $param);

        $this->send_JSON(array("getCommodityType" => $result));
        exit();
    }

    /* -------------------------------------------------------
       7. IRREGULARITY CODE
    ------------------------------------------------------- */
    if ($type === 'IrregularityCode') {

        $result = $this->issue->QualityEventIrregularityCode($org_id, $rt_id, $param);

        $this->send_JSON(array("getIrregularityCode" => $result));
        exit();
    }

    /* -------------------------------------------------------
       UNKNOWN CHART TYPE
    ------------------------------------------------------- */
    $this->send_JSON(array("error" => "Unknown data_for: " . $type));
    exit();
}


//    IWI chart for quality event module 07112025 end





//IWI Aircargoclaim chart page update 12012026 start

// IWI Aircargoclaim chart page update 20022026 start
    public function action_Load_AirCargoClaimChartOverview(MVC_Request $rq) {
        $type_id = 1;
        $org_id = MVC_Core::$ss->user['org_id'];
        $rt_id = $this->rt_Return();

        $startDate = !empty($rq->startDate) ? str_replace('/', '-', $rq->startDate) : '';
        $endDate = !empty($rq->endDate) ? str_replace('/', '-', $rq->endDate) : '';
        $fromdate = $startDate != '' ? strtotime($startDate) : '';
        $todate = $endDate != '' ? strtotime($endDate) : '';
        $where = '';
        $where_time = '';
        if ($fromdate != '' && $todate != '') {
            $where_time .= " AND (`iss`.ctime BETWEEN $fromdate AND $todate) ";
        }

 $where .= $where_time;
        $param = array(
            'type_id' => $type_id,
            'org_id' => $org_id,
            'rt_id' => $rt_id,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'where' => $where,
        );

        
        if ($rq->chart_section == 'getTopPartCount') {
//           for get preliminary and formal total count start
$param['where']='';
        $preliminary_total_count = 0;
        $preliminary_total_active_count = 0;
        $preliminary_total_close_count = 0;
        $preClaim = array();

        $rows = $this->issue->getPreClaimDetails($param);

        foreach ($rows as $row) {

            $closed = isset($row['closed_count']) ? (int) $row['closed_count'] : 0;
            $opened = isset($row['opened_count']) ? (int) $row['opened_count'] : 0;

//            $preClaim[] = array(
//                'month' => $row['month'],
//                'closed' => $closed,
//                'active' => $opened
//            );

            $preliminary_total_count += ($closed + $opened);
            $preliminary_total_active_count += ($opened);
            $preliminary_total_close_count += ($closed);
        }
        
        // -----------------------------

        $formal_total_count = 0;
        $formal_total_active_count = 0;
        $formal_total_close_count = 0;
        $formalClaim = array();

        $rows = $this->issue->getFormalClaimDetails($param);

        foreach ($rows as $row) {

            $closed = isset($row['closed_count']) ? (int) $row['closed_count'] : 0;
            $opened = isset($row['opened_count']) ? (int) $row['opened_count'] : 0;

//            $formalClaim[] = array(
//                'month' => $row['month'],
//                'closed' => $closed,
//                'active' => $opened
//            );

            $formal_total_count += ($closed + $opened);
            $formal_total_active_count += ($opened);
            $formal_total_close_count += ($closed);
        }
        
        $getActiveFormalClaimsAssignmentDetailsData = $this->issue->getActiveFormalClaimsAssignmentDetails($param);

        foreach ($getActiveFormalClaimsAssignmentDetailsData as $row) {
            $assigned_count = isset($row['assigned_count']) ? (int) $row['assigned_count'] : 0;
            $unassigned_count = isset($row['unassigned_count']) ? (int) $row['unassigned_count'] : 0;
            $formal_total_assigned_count += $assigned_count;
            $formal_total_unassigned_count += $unassigned_count;
        }
        
        //for get preliminary and formal total count end 
        


            $this->send_JSON(array(
            'success' => true,
            'preliminary_total_count' => $preliminary_total_count,
            'preliminary_total_active_count' => $preliminary_total_active_count,
            'preliminary_total_close_count' => $preliminary_total_close_count,
            'formal_total_count' => $formal_total_count,
            'formal_total_active_count' => $formal_total_active_count,
            'formal_total_close_count' => $formal_total_close_count,
            'formal_total_assigned_count' => $formal_total_assigned_count,
            'formal_total_unassigned_count' => $formal_total_unassigned_count,
        ));

        }
        
        if ($rq->chart_section == 'preliminaryClaimChart') {
//           for get preliminary and formal total count start

        $preClaim = array();

        $rows = $this->issue->getPreClaimDetails($param);

        foreach ($rows as $row) {

            $closed = isset($row['closed_count']) ? (int) $row['closed_count'] : 0;
            $opened = isset($row['opened_count']) ? (int) $row['opened_count'] : 0;

            $preClaim[] = array(
                'month' => $row['month'],
                'closed' => $closed,
                'active' => $opened
            );

        }
        //for get preliminary and formal total count end 
        


            $this->send_JSON(array(
            'success' => true,
            'preClaim' => $preClaim,
        ));

        }
        
        
        if ($rq->chart_section == 'formalClaimChart') {
//           for get preliminary and formal total count start


        $formalClaim = array();

        $rows = $this->issue->getFormalClaimDetails($param);

        foreach ($rows as $row) {

            $closed = isset($row['closed_count']) ? (int) $row['closed_count'] : 0;
            $opened = isset($row['opened_count']) ? (int) $row['opened_count'] : 0;

            $formalClaim[] = array(
                'month' => $row['month'],
                'closed' => $closed,
                'active' => $opened
            );
        }
        //for get preliminary and formal total count end 
        


            $this->send_JSON(array(
            'success' => true,
            'formalClaim' => $formalClaim,
        ));

        }
        
        
        
        if ($rq->chart_section == 'getActiveFormalClaimByUserChart') {

        $getActiveFormalClaimByUser = array();
        
        $rows = $this->issue->getActiveFormalClaimByUserChart($param);

        foreach ($rows as $row) {


            $getActiveFormalClaimByUser[] = array(
                'user' => $row['user_name'],
                'month' => $row['month'],
                'year' => $row['year'],
                'count' => $row['active_count']
            );
        }
            $this->send_JSON(array(
            'success' => true,
            'getActiveFormalClaimByUser' => $getActiveFormalClaimByUser,
        ));

        }
        
        
        
        if ($rq->chart_section == 'getTotalActiveFormalChart') {

        $getTotalActiveFormalChart = array();
        
        $rows = $this->issue->getTotalActiveFormalChart($param);

        foreach ($rows as $row) {
           $getTotalActiveFormalChart[]=array(
               'year'=>$row['year'],
               'month'=>$row['month'],
               'active'=>$row['total_records'],
               'assigned'=>$row['assigned_count'],
               'unassigned'=>$row['unassigned_count'],
           );
        }


            $this->send_JSON(array(
            'success' => true,
            'getTotalActiveFormalChart' => $getTotalActiveFormalChart,
        ));

        }
        
        
        
        
        /*

        
        
        $formalClaimTop_5_OriginData = $this->issue->getFormalClaimTop_5_OriginData($param);
        $formal_top_5_Origin = array();
        foreach ($formalClaimTop_5_OriginData as $row) {
            $formal_top_5_Origin[] = array(
                'origin' => $row['origin'],
                'damage' => $row['damage_pieces'],
                'total_loss' => $row['total_loss_pieces'],
                'delay' => $row['delay_pieces'],
                'partial_loss' => $row['partial_loss_pieces'],
                'pilferage' => $row['pilferage_pieces'],
            );
        }


        $formalClaimTop_5_DestinationData = $this->issue->getFormalClaimTop_5_DestinationData($param);
        $formal_top_5_Destination = array();

        foreach ($formalClaimTop_5_DestinationData as $row) {
            $formal_top_5_Destination[] = array(
                'destination' => $row['destination'],
                'damage' => $row['damage_pieces'],
                'total_loss' => $row['total_loss_pieces'],
                'delay' => $row['delay_pieces'],
                'partial_loss' => $row['partial_loss_pieces'],
                'pilferage' => $row['pilferage_pieces'],
            );
        }

        $totalAmountFormalClaimedData = $this->issue->getTotalAmountFormalClaimedData($param);
        $totalAmountFormalClaimed=array();
        foreach ($totalAmountFormalClaimedData as $row) {
            $totalAmountFormalClaimed[$row['month']] = array(
                'amount' => $row['amount']
            );
        }

        $ultimateAmountProvisionData = $this->issue->getUltimateAmountProvisionData($param);
        $ultimateAmountProvision=array();
        foreach ($ultimateAmountProvisionData as $row) {
            $ultimateAmountProvision[$row['month']] = array(
                'provision_amount' => $row['provision_usd_amount'],
            );
        }
        


        $this->send_JSON(array(
            'success' => true,
            'preliminary_total_count' => $preliminary_total_count,
            'formal_total_count' => $formal_total_count,
            'preClaim' => $preClaim,
            'formalClaim' => $formalClaim,
            'formal_top_5_Origin' => $formal_top_5_Origin,
            'formal_top_5_Destination' => $formal_top_5_Destination,
            'totalAmountFormalClaimed' => $totalAmountFormalClaimed,
            'ultimateAmountProvision' => $ultimateAmountProvision,
        ));
*/
        
    }
//     IWI Aircargoclaim chart page update 20022026 end
    
    
    
    public function action_Load_AirCargoClaimChartActiveFormalClaims(MVC_Request $rq) {
        $type_id = 1;
        $org_id = MVC_Core::$ss->user['org_id'];
        $rt_id = $this->rt_Return();

//        echo $rq->airline;
        
        
        $where_lime_limit = '';
        
        $where_lime_limit .= ' AND iss.ctime >= UNIX_TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 YEAR)) ';
        $where = '';
        if(MVC_Core::$ss->user['org_id']=='208' ){     
            if(isset($rq->airline) && $rq->airline=='1'){
                $where .= " AND iss_awb.value LIKE '369-%' ";
            }   
            else if(isset($rq->airline) && $rq->airline=='2'){
                $where .= " AND iss_awb.value LIKE '403-%'  ";
            }
            
        }
        if(MVC_Core::$ss->user['org_id']=='9' ){     
                $where .= " AND iss_awb.value LIKE '065-%'  ";
            
        }
        
        $param = array(
            'type_id' => $type_id,
            'org_id' => $org_id,
//            'get_data_from' => '5',
            'rt_id' => $rt_id,
            'where' => $where,
            'where_lime_limit' => $where_lime_limit,
        );
        
        
        
        $getActiveFormalClaimsDashboardTotals = $this->issue->getActiveFormalClaimsDashboardTotals($param);
//        print_r($getActiveFormalClaimsDashboardTotals);
//
//        foreach ($getActiveFormalClaimsDashboardTotals as $row) {
//            print_r($row);
//        }
        
        
        
        

        $formal_total_active_count = 0;
        $formal_total_closed_count = 0;

        $rows = $this->issue->getActiveFormalClaimsClaimedCount($param);

        foreach ($rows as $row) {
            $opened = isset($row['opened_count']) ? (int) $row['opened_count'] : 0;
            $closed = isset($row['closed_count']) ? (int) $row['closed_count'] : 0;
            $formal_total_active_count += $opened;
            $formal_total_closed_count += $closed;
            $formal_total_count += ($closed + $opened);
        }

        $totalAmountFormalClaimed_total_record = 0;
//        $totalAmountFormalClaimed_total_amount = 0;
        $getActiveFormalClaimsClaimedAmountData = $this->issue->getActiveFormalClaimsClaimedAmountData($param);
        $totalAmountFormalClaimed = array();
        foreach ($getActiveFormalClaimsClaimedAmountData as $row) {
            $totalAmountFormalClaimed[$row['year']][$row['month']] = isset($row['amount']) ? round((float) $row['amount'], 2) : 0;
            $totalAmountFormalClaimed_total_record += isset($row['total_records']) ? (int) $row['total_records'] : 0;
//            $totalAmountFormalClaimed_total_amount += isset($row['amount']) ? round((float) $row['amount'], 2) : 0;
        }



        $totalSuggestedAmount_total_record = 0;
//        $totalSuggestedAmount_total_amount = 0;
        $totalSuggestedAmountData = $this->issue->getTotalSuggestedAmountData($param);
        $totalSuggestedAmount = array();
        foreach ($totalSuggestedAmountData as $row) {
            $totalSuggestedAmount[$row['year']][$row['month']] = isset($row['amount']) ? round((float) $row['amount'], 2) : 0;
            $totalSuggestedAmount_total_record += isset($row['total_records']) ? (int) $row['total_records'] : 0;
//            $totalSuggestedAmount_total_amount += isset($row['amount']) ? round((float) $row['amount'], 2) : 0;
        }

        $totalSettledAmount_total_record = 0;
        $totalSettledAmount_total_amount = 0;
        $totalSettledAmountData = $this->issue->getTotalSettledAmountData($param);
        $totalSettledAmount = array();
        foreach ($totalSettledAmountData as $row) {
            $totalSettledAmount[$row['year']][$row['month']] = isset($row['amount']) ? round((float) $row['amount'], 2) : 0;
            $totalSettledAmount_total_record += isset($row['total_records']) ? (int) $row['total_records'] : 0;
            $totalSettledAmount_total_amount += isset($row['amount']) ? round((float) $row['amount'], 2) : 0;
        }


        $totalProvisionAmount_total_record = 0;
//        $totalProvisionAmount_total_amount = 0;
        $totalProvisionAmountData = $this->issue->getTotalProvisionAmountData($param);
        $totalProvisionAmount = array();
        foreach ($totalProvisionAmountData as $row) {
            $totalProvisionAmount[$row['year']][$row['month']] = isset($row['amount']) ? round((float) $row['amount'], 2) : 0;
            $totalProvisionAmount_total_record += isset($row['total_records']) ? (int) $row['total_records'] : 0;
//            $totalProvisionAmount_total_amount += isset($row['amount']) ? round((float) $row['amount'], 2) : 0;
        }

        
        $getActiveFormalClaimsAssignmentDetailsData = $this->issue->getActiveFormalClaimsAssignmentDetails($param);

        foreach ($getActiveFormalClaimsAssignmentDetailsData as $row) {
            $assigned_count = isset($row['assigned_count']) ? (int) $row['assigned_count'] : 0;
            $unassigned_count = isset($row['unassigned_count']) ? (int) $row['unassigned_count'] : 0;
            $formal_total_assigned_count += $assigned_count;
            $formal_total_unassigned_count += $unassigned_count;
        }



        $this->send_JSON(array(
            'success' => true,
            'formal_total_count' => $formal_total_count,
            'financialClaimsDashboardTotals' => $getActiveFormalClaimsDashboardTotals,
            'formal_total_active_count' => $formal_total_active_count,
            'formal_total_closed_count' => $formal_total_closed_count,
            'totalAmountFormalClaimed' => $totalAmountFormalClaimed,
            'totalAmountFormalClaimed_total_record' => $totalAmountFormalClaimed_total_record,
//            'totalAmountFormalClaimed_total_amount' => number_format($totalAmountFormalClaimed_total_amount, 2, '.', ','),
            'totalAmountFormalSuggested' => $totalSuggestedAmount,
            'totalSuggestedAmount_total_record' => $totalSuggestedAmount_total_record,
//            'totalSuggestedAmount_total_amount' => number_format($totalSuggestedAmount_total_amount, 2, '.', ','),
            'totalAmountFormalSettled' => $totalSettledAmount,
            'totalSettledAmount_total_record' => $totalSettledAmount_total_record,
            'totalSettledAmount_total_amount' => number_format($totalSettledAmount_total_amount, 2, '.', ','),
            'totalProvisionAmount_total_record' => $totalProvisionAmount_total_record,
//            'totalProvisionAmount_total_amount' => number_format($totalProvisionAmount_total_amount, 2, '.', ','),
            'totalAmountFormalProvision' => $totalProvisionAmount,
            'formal_total_assigned_count' => $formal_total_assigned_count,
            'formal_total_unassigned_count' => $formal_total_unassigned_count,
        ));
    }
    
    
    
    public function action_Export_Record_Claimed_Amount(MVC_Request $rq) {

        $org_id = MVC_Core::$ss->user['org_id'];

        // Locate record type
        $rt_id = $this->issue->ist_Locate($rq->rt_key);
        if (empty($rt_id))
            return header("HTTP/1.0 404 Request contains invalid record type");

        $this->rec_plugin = MVC_Core::factory_Plugin("record", $rq->rt_key);

        /* ---------------- SQL ---------------- */

        $where = '';
        if(MVC_Core::$ss->user['org_id']=='208' ){     
            if(isset($rq->airline) && $rq->airline=='1'){
                $where .= " AND iss_awb.value LIKE '369-%' ";
            }   
            else if(isset($rq->airline) && $rq->airline=='2'){
                $where .= " AND iss_awb.value LIKE '403-%'  ";
            }
            
        }
        if(MVC_Core::$ss->user['org_id']=='9' ){     
                $where .= " AND iss_awb.value LIKE '065-%'  ";
            
        }
        
       

        $getActiveFormalClaimedData = $this->issue->getActiveFormalClaimedData($org_id, $rt_id,$where);

        if (empty($getActiveFormalClaimedData)) {

            echo '<html><head><title>No Claimed Record Found</title></head><body>
        <style>.button{background-color: blue;border: none;color: white;padding: 15px 32px;font-size: 16px;}</style>
        <div style="padding: 10px; border: 3px dashed blue;width:630px;">
        <h1>No Claimed Record Found For This Account</h1></div><br>
        <input type="button" class="button" value="Close" onclick="window.close();">
        </body></html>';

            return;
        }


        /* -------- GROUP + YEAR TOTAL -------- */

        $getActiveFormalClaimedDetails = array();
        $yearTotal = array();

        foreach ($getActiveFormalClaimedData as $value) {

            $year = $value['year'];

            if (!isset($getActiveFormalClaimedDetails[$year])) {
                $getActiveFormalClaimedDetails[$year] = array();
            }

            $getActiveFormalClaimedDetails[$year][] = $value;

            if (!isset($yearTotal[$year])) {
                $yearTotal[$year] = 0;
            }

            $yearTotal[$year] += floatval($value['claim_amount']);
        }


        /* -------- HTML -------- */

        ob_start();
        ?>
        <head>
        <meta charset="utf-8">
        <title>Air Claims-Total Claimed In USD </title>
        <style>
        .Rrecords-list table tr:nth-child(even) {
        background: #f5f5f5;
        }
        .Rrecords-list table tr th {
        padding: 5px;
        font-size: 13px;
        }
        .Rrecords-list table tr td {
        padding: 5px;
        font-size: 14px;
        }
        .print {
        position: absolute;
        right: 40px;
        }
        @media print {
    .print{
        display:none !important;
    }
}
        </style>
        </head>

        <body>
        <?php if(!isset($_GET['download'])){ ?>
<div class="print">
    <button onclick="window.print()">
        <img src="/img/icons16/printer.png" alt="Print">
    </button>

    <button onclick="window.location.href=window.location.href+'&download=1'">
       <img src="/img/icons16/download.png" alt="Download Excel">
    </button>
</div>
<?php } ?>


        <h2 style="text-align: center;margin-bottom: 6px;font-size: 24px;">Total Claimed Amount Details</h2>
        <h3 style="text-align: center;margin-top: 0;color: #1174bd;font-weight: 500;font-size: 18px;text-transform: uppercase;">
        (All Active Claimed Till Date)
        </h3>

        <div class="Rrecords-list" style="width: 90%;margin: 0 auto;">

        <?php
        $tot_claimed_coun=0;
        $total_claimed_amount=0;
        foreach ($getActiveFormalClaimedDetails as $periodKey => $periodValue) {
            ?>

            <table border="1" style="width: 100%;margin-bottom: 20px;border-collapse: collapse;">
            <tbody>

            <tr>
            <th colspan="5" style="background: #cce9ff;padding: 5px;"><?= $periodKey ?></th>
            </tr>

            <tr style="background: #dddddd;">
            <th> Tracking ID </th>
            <th> AWB No. </th>
            <th>Claim in USD</th>
            <th>Claim received date</th>
            <th>Status</th>
            </tr>

            <?php foreach ($periodValue as $record) { ?>

                <tr>
                <td style="width:15%">
                <a href="/record/process:<?= $record['track'] ?>" target="_blank"><?= $record['track'] ?></a>
                </td>

                <td style="width:15%"><?= $record['cache_primary'] ?></td>

                <td style="width:12%"><?= number_format((float) $record['claim_amount'], 2) ?></td>

                <td style="width:12%">
                <?= (!empty($record['claim_date']) ? date('d-m-Y', $record['claim_date']) : '-') ?>
                </td>

                <td style="width:12%"><?= $record['process'] ?></td>
                </tr>

            <?php } ?>

            <!-- YEAR TOTAL ROW (FIXED) -->
            <tr>
            <td colspan="2">
            <span class="total"><b>Total Claimed In USD</b></span>
            </td>

            <td colspan="1">
            <b><?= number_format($yearTotal[$periodKey], 2) ?> $</b>
            </td>

            <td colspan="1">
            <span class="total"><b>Total Claimed Count</b></span>
            </td>

            <td colspan="1">
            <b><?= count($periodValue) ?></b>
            </td>
            </tr>

            </tbody>
            </table>

        <?php 
        $tot_claimed_count +=count($periodValue);
                    $total_claimed_amount += $yearTotal[$periodKey];
            } 
            ?>
             <table border="1" style="width: 100%;margin-bottom: 20px;border-collapse: collapse;">
                    <tbody>
                        <tr style="background: #dddddd;">
                            <th> Total Active Claimed Count </th>
                            <th> Total Active Claimed in USD </th>
                        </tr>
                        <tr style="background: #fff;">
                            <th>
                                <b> <?= $tot_claimed_count ?> </b>
                            </th>
                            <th>
                                <b> <?= number_format($total_claimed_amount, 2) ?> $ </b>
                            </th>
                        </tr>
                    </tbody>
                </table>

        </div>
        </body>

        <?php
        $html = ob_get_clean();
        if(isset($_GET['download']) && $_GET['download'] == 1){

    $filename = "Claimed_Report_".date('d-m-Y').".xls";

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo $html;
    exit;
}
        echo $html;
        exit;
    }
    
    
    
     public function action_Load_AirCargoClaimChartClaimAspects(MVC_Request $rq) {
        $type_id = 1;
        $org_id = MVC_Core::$ss->user['org_id'];
        $rt_id = $this->rt_Return();

        $startDate = !empty($rq->startDate) ? str_replace('/', '-', $rq->startDate) : '';
        $endDate = !empty($rq->endDate) ? str_replace('/', '-', $rq->endDate) : '';
        $fromdate = $startDate != '' ? strtotime($startDate) : '';
        $todate = $endDate != '' ? strtotime($endDate) : '';
        $where = '';
        if ($fromdate != '' && $todate != '') {
            $where .= " AND (`iss`.ctime BETWEEN $fromdate AND $todate) ";
        }


        if(MVC_Core::$ss->user['org_id']=='208' ){     
            if(isset($rq->airline) && $rq->airline=='1'){
                $where .= " AND iss_awb.value LIKE '369-%' ";
            }   
            else if(isset($rq->airline) && $rq->airline=='2'){
                $where .= " AND iss_awb.value LIKE '403-%'  ";
            }
            
        }
        if(MVC_Core::$ss->user['org_id']=='9' ){     
                $where .= " AND iss_awb.value LIKE '065-%'  ";
            
        }
        $param = array(
            'type_id' => $type_id,
            'org_id' => $org_id,
            'rt_id' => $rt_id,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'where' => $where,
        );

        if ($rq->chart_section == 'ClaimAspectsCount') {
            $getClaimAspectsCountData = $this->issue->getClaimAspectsCount($param);
            $getClaimAspectsCount = array();
            foreach ($getClaimAspectsCountData as $key => $row) {
                $month = $row['month'];

                // create month bucket
                if (!isset($getClaimAspectsCount[$month])) {
                    $getClaimAspectsCount[$month] = array();
                }

                // add all claim columns except month
                foreach ($row as $field => $value) {
                    if ($field == 'month' || $field == 'time' || $field == 'year')
                        continue;

                    $getClaimAspectsCount[$month][$field] = (int) $value;
                }
            }

            $this->send_JSON(array(
                'success' => true,
                'getClaimAspectsCount' => $getClaimAspectsCount,
            ));
        }
    }
    
    public function action_Export_Record_Unassigned(MVC_Request $rq)
{
    $org_id = MVC_Core::$ss->user['org_id'];
    $rt_id  = $this->rt_Return();

    $param = array(
        'page_for' => 'unassigned_details',
        'type_id'  => $rt_id,
        'org_id'   => $org_id,
        'year'     => isset($rq->year) ? $rq->year : '',
        'month'    => isset($rq->month) ? $rq->month : ''
    );

    $records = $this->issue->getActiveFormalUnassignedData($param);

    if (empty($records)) {
        echo '<html><body>
        <h2>No Unassigned Record Found</h2>
        <button onclick="window.close()">Close</button>
        </body></html>';
        return;
    }

    /* -------- GROUP DATA -------- */

    $groupedData = array();
    $totalCount  = 0;

    foreach ($records as $row) {

        $year  = isset($row['year']) ? $row['year'] : '';
        $month = isset($row['month']) ? $row['month'] : '';
if(isset($rq->month)){
    $key = $year . ' - ' . $month;
}else{
    $key = $year;
}
        

        if (!isset($groupedData[$key])) {
            $groupedData[$key] = array();
        }

        $groupedData[$key][] = $row;
        $totalCount++;
    }
    
    

    /* -------- HTML -------- */

    ob_start();
?>
<html>
<head>
<meta charset="utf-8">
<title>Unassigned Records Report</title>
<style>
table { border-collapse: collapse; width:100%; margin-bottom:20px; }
table th, table td { padding:6px; border:1px solid #ccc; font-size:14px; }
th { background:#eee; }
.print { position:absolute; right:40px; }
@media print { .print { display:none !important; } }
</style>
</head>

<body>

<?php if (!isset($_GET['download'])) { ?>
<div class="print">
    <button onclick="window.print()">
        <img src="/img/icons16/printer.png" alt="Print">
    </button>

    <button onclick="window.location.href=window.location.href+'&download=1'">
       <img src="/img/icons16/download.png" alt="Download Excel">
    </button>
</div>
<?php } ?>

<h2 style="text-align:center;">Unassigned Records Details</h2>

<div style="width:90%;margin:0 auto;">

<?php foreach ($groupedData as $period => $rows) { ?>

<table>
<tr>
    <th colspan="3" style="background:#cce9ff;"><?php echo $period; ?></th>
</tr>

<tr>
    <th>Tracking ID</th>
    <th>AWB No.</th>
    <th>Created Date</th>
</tr>

<?php foreach ($rows as $record) {

    $track = isset($record['track']) ? $record['track'] : '-';
    $awb   = isset($record['cache_primary']) ? $record['cache_primary'] : '-';
    $created = isset($record['created_date']) ? $record['created_date'] : '';
?>

<tr>
    <td>
        <a href="/record/process:<?php echo $track; ?>" target="_blank">
            <?php echo $track; ?>
        </a>
    </td>
    <td><?php echo $awb; ?></td>
    <td><?php echo ($created ? date('d-m-Y', strtotime($created)) : '-'); ?></td>
</tr>

<?php } ?>

<tr>
    <td colspan="2"><b>Total Records</b></td>
    <td><b><?php echo count($rows); ?></b></td>
</tr>

</table>

<?php } ?>
<table style="width:100%; margin:25px 0; border-collapse:collapse;">
    <tr style="background:#f2f2f2;">
        <td style="padding:10px; font-weight:bold;border-right: none;">
            Total Unassigned Count
        </td>
        <td style="padding:10px; font-weight:bold; text-align:right; border-left:none;">
            <?php echo $totalCount; ?>
        </td>
    </tr>
</table>

</div>
</body>
</html>

<?php
$html = ob_get_clean();

if (isset($_GET['download']) && $_GET['download'] == 1) {

    $filename = "Unassigned_Report_" . date('d-m-Y') . ".xls";

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo $html;
    exit;
}

echo $html;
exit;
}

//IWI Aircargoclaim chart page update 12012026 end



//IWI audit control page chart new 11032026 start

public function action_Load_AuditControlOverviewChart(MVC_Request $rq) {
    $type_id = 34;
        $org_id = MVC_Core::$ss->user['org_id'];

        $startDate = !empty($rq->startDate) ? str_replace('/', '-', $rq->startDate) : '';
        $endDate = !empty($rq->endDate) ? str_replace('/', '-', $rq->endDate) : '';
        $fromdate = $startDate != '' ? strtotime($startDate) : '';
        $todate = $endDate != '' ? strtotime($endDate) : '';
        $where = '';
        $where_time = '';
        if ($fromdate != '' && $todate != '') {
            $where_time .= " AND (`iss`.ctime BETWEEN $fromdate AND $todate) ";
        }

 $where .= $where_time;
        $param = array(
            'type_id' => $type_id,
            'org_id' => $org_id,
            'rt_id' => $type_id,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'where' => $where,
        );

        
        if ($rq->chart_section == 'getTopPartCount') {
            $param['where'] = '';
            $totalTopPartCount = array();

            $getAuditControlTotalRecord = $this->issue->getAuditControlTotalRecord($param);
            $getAuditControlTotalRecord_active_count = 0;
            $getAuditControlTotalRecord_close_count = 0;

            foreach ($getAuditControlTotalRecord as $row) {
                $getAuditControlTotalRecord_active_count += (int) ($row['opened_count']);
                $getAuditControlTotalRecord_close_count += (int) ($row['closed_count']);
            }
            $getAuditControlTotalRecord_count = array(
                'active' => $getAuditControlTotalRecord_active_count,
                'close' => $getAuditControlTotalRecord_close_count
            );

            $getAuditControlAssesmentDetails_count = $this->issue->getAuditControlAssesmentDetails($param);

            $getAuditControlType = $this->issue->getAuditControlType($param);

            $getAuditControlTotalType_count = array();
            foreach ($getAuditControlType as $row) {
                $getAuditControlTotalType_count[$row['action_type']] += (int) $row['total'];
            }


            $getAuditControlRisk = $this->issue->getAuditControlRisk($param);
            $riskStatus = array(
                'MI' => 'Minor',
                'MA' => 'Major',
                'CR' => 'Critical',
            );
            $getRiskRecord_count = array();
            foreach ($getAuditControlRisk as $row) {
                $getRiskRecord_count[$riskStatus[$row['risk']]] = (int) $row['total'];
            }



            $this->send_JSON(array(
                'success' => true,
                'getAuditControlTotalRecord_count' => $getAuditControlTotalRecord_count,
                'getAuditControlAssesmentDetails_count' => $getAuditControlAssesmentDetails_count,
                'getAuditControlTotalType_count' => $getAuditControlTotalType_count,
                'getRiskRecord_count' => $getRiskRecord_count,
            ));
        }


        if ($rq->chart_section == 'getTotalRecord') {
        $totalRecord = array();

        $rows = $this->issue->getAuditControlTotalRecord($param);

        foreach ($rows as $row) {
            $closed = isset($row['closed_count']) ? (int) $row['closed_count'] : 0;
            $opened = isset($row['opened_count']) ? (int) $row['opened_count'] : 0;//
            $totalRecord[] = array(
                'month' => $row['month'],
                'closed' => $closed,
                'active' => $opened
            );

        }
            $this->send_JSON(array(
            'success' => true,
            'totalRecord' => $totalRecord,
        ));

        }

        if ($rq->chart_section == 'getStatus') {
        $StatusRecord = array();

        $rows = $this->issue->getAuditControlstatus($param);
        $statusKeys=array(
            'new'=>'New',
            'time'=>'Scheduled',
            'inpr'=>'Processing',
            'rev'=>'Reviewing',
            'capa'=>'CaPa Started',
            'eval'=>'Evaluation',
            'can'=>'Cancelled',
            'clos'=>'Resolved',
            'unr'=>'Unresolved',
        );

        foreach ($rows as $row) {
            $StatusRecord[] = array(
                'month' => $row['month'],
                'status_key' => $statusKeys[$row['status_key']],
                'total' => $row['total']
            );

        }
            $this->send_JSON(array(
            'success' => true,
            'StatusRecord' => $StatusRecord,
        ));

        }

        if ($rq->chart_section == 'getPriority') {
        $priorityRecord = array();

        $rows = $this->issue->getAuditControlpriority($param);
        $priorityStatus=array(
            '1crt'=>'Critical!',
            '2urg'=>'Urgent!',
            '3hgh'=>'High',
            '4med'=>'Medium',
            '5low'=>'Low',
        );
        foreach ($rows as $row) {
            $priorityRecord[] = array(
                'month' => $row['month'],
                'priority_key' => $priorityStatus[$row['priority_key']],
//                'priority_key' => $row['priority_key'],
                'total' => $row['total']
            );

        }
            $this->send_JSON(array(
            'success' => true,
            'priorityRecord' => $priorityRecord,
        ));

        }
        if ($rq->chart_section == 'getRisk') {
        $riskRecord = array();

        $rows = $this->issue->getAuditControlRisk($param);
 $riskStatus=array(
            'MI'=>'Minor',
            'MA'=>'Major',
            'CR'=>'Critical',
        );
        foreach ($rows as $row) {
            $riskRecord[] = array(
                'month' => $row['month'],
                'risk' => $riskStatus[$row['risk']],
                'total' => $row['total']
            );

        }
            $this->send_JSON(array(
            'success' => true,
            'riskRecord' => $riskRecord,
        ));

        }

         if ($rq->chart_section == 'getType') {
        $typeRecord = array();

        $rows = $this->issue->getAuditControlType($param);

        foreach ($rows as $row) {
            $typeRecord[] = array(
                'month' => $row['month'],
                'action_type' => $row['action_type'],
                'total' => $row['total']
            );

        }
            $this->send_JSON(array(
            'success' => true,
            'typeRecord' => $typeRecord,
        ));

        }
        
        
}



//IWI audit control page chart new 11032026 end


//     IWI new dashboard design 17082026 start
    
    public function action_loadTodoListByUser() {
        $user_id = MVC_Core::$ss->user['id'];
        $getData = $this->user->loadActiveTodoListByUser($user_id);
        $this->send_JSON(array(
            'success' => true,
            'data' => $getData,
        ));
    }
    public function action_saveTodo(MVC_Request $rq) {
        $id = '';
        $saveData = array(
            'user_id' => MVC_Core::$ss->user['id']
        );

        if (isset($rq->title)) {
            $saveData['title'] = trim($rq->title);
        }

        if (isset($rq->description)) {
            $saveData['description'] = trim($rq->description);
        }

        if (isset($rq->remark)) {
            $saveData['remark'] = trim($rq->remark);
        }

        if (isset($rq->due_date)) {
            $saveData['due_date'] = !empty($rq->due_date) ? $rq->due_date : null;
        }

        if (isset($rq->priority)) {
            $saveData['priority'] = trim($rq->priority);
        }

        if (isset($rq->status)) {
            $saveData['status'] = trim($rq->status);
        }
        if (isset($rq->is_deleted)) {
            $saveData['is_deleted'] = trim($rq->is_deleted);
            $saveData['deleted_at'] = date('Y-m-d H:i:s');
        }
        if (isset($rq->id) && !empty($rq->id)) {
            $id = $rq->id;
        }

        $r = $this->user->saveTodo($id, $saveData);

        if (!$r)
            return header("HTTP/1.0 500 Todo could not be saved. Possible database error!");

        $this->send_JSON(array(
            'success' => true,
            'effected_id' => $r,
        ));
    }

    public function action_getTodoDetails(MVC_Request $rq) {
        $id = $rq->id;
        $getData = $this->user->loadTodoDetails($id);
        $this->send_JSON(array(
            'success' => true,
            'data' => $getData,
        ));
    }
    
    
    

    public function action_alltodo() {
        array_push($this->CFG['modules'], "user/_alltodo"); // for loading js page 
        $this->prepare_Header("All Todo List"); //       
        
        
        $this->site_section = "user" . DS . "todo" . DS . "alltodo.html";
        $this->view("private.html");
    }

    public function action_getTodoList() {
        $user_id = MVC_Core::$ss->user['id'];
        $getData = $this->user->loadTodoListByUser($user_id);
        $this->send_JSON(array(
            'success' => true,
            'data' => $getData,
        ));
    }
    public function action_deleteTodo(MVC_Request $rq)  {
        $id = $rq->id;
        $getData = $this->user->deleteTodo($id);
        if($getData){            
        $this->send_JSON(array(
            'success' => true,
        ));
        }else{
           $this->send_JSON(array(
            'success' => false,
        )); 
        }
    }

//     IWI new dashboard design 17082026 end
                }
                