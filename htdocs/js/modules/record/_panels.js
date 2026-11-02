/**
 * Module "Record Process"->Section "Panels", the first tab showing record
 * information
 * 
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules.Record Panels
 * @version 2021.05.17
 */
cargohub.record_panels = donrey.BASE._extend(function () {
    // private
    var self = this,
            c = null,
            master = cargohub.record_process,
            // ids of all the panels - "open" - has been created and visible, "closed" - 
            // exists but in a closed state, (missing: - not yet created)
            panels = {},
            panels_ready = false,
            panels_saved_dom = {}, // To support "Edit" operation in panels, here the
            // original content is saved when entering edit mode  
   
    
   
 
 
            // IWI manage extra section only for data panel selected module  start
            selected_row = null,
            // Dropzone component 
            file_uploader_ready = false,
            file_uploader_instance_key = "tabfiles",
            $trainee_editor = null, // Expander containing various controls, including TinyMCE "rich_editor"
            editor_open = false, // Non-false when Editor is open (in either Create or Edit modes) - Save button should be visible
            open_row_id = null, // ID of the row that has been opened (selected one might have changed in the mean time) - Only has a value when opening existing row (not for create/duplicate)
            editor_nonempty = false, // Editor is open and non non-empty, show a warning when trying to open another row or select a template
            // Indicates if TinyMCE component is already loaded and the editor used here is initialized
            rich_editor_ready = false,
            rich_editor_instance_key = "traineedit",
            $trainee_viewer = $("#tab_trainee #document-viewer"),
            docs_viewer_Ready = false,
            viewer_open = false,
            docs_manager_priv = cargohub.application.user_Test_Privileges('issue_edit_notes'),
            docs_Source = new $.jqx.dataAdapter({
                url: "/record/process?action=load_list_trainee_management", // At the moment using notes backend - That might change in the future !
                data: {
                    link_id: master.config.id,
                    track: master.config.track,
                },
                datatype: "json",
                datafields: [
                    {name: 'name', type: 'string'}, // Originally called "title" on notes in the database
                    {name: 'url', type: 'string'},//28082024 add by iwi
                    {name: 'number', type: 'string'},
                    {name: 'email', type: 'string'},
                    {name: 'training_date', type: 'string'},
                    {name: 'training_expiry_date', type: 'string'},
                    {name: 'email_lable', type: 'string'},//05032025 add email field by Training Scheduler
                    {name: 'classroom_type', type: 'string'},//26092024  train_scheduler_classroom_type  addded by iwi start
                    {name: 'result', type: 'string'},
                    {name: 'score', type: 'string'},
                    {name: 'files', type: 'string'},
                    {name: 'company_name', type: 'string'},
                    {name: 'trainee_type', type: 'string'},
                    {name: 'trainee_type_lable', type: 'string'},
                    {name: 'user_issue_id', type: 'string'},

                    {name: 'attach_all_files', type: 'string'},  // 02122024 Training Scheduler upload multiple files
                    {name: 'current_attach_all_files', type: 'string'},  // 02122024 Training Scheduler upload multiple files
                ],
                id: "id",
                sortcolumn: 'id',
                sortdirection: 'ASC',
                filter: function () {
                    $("#tab_trainee #_record-docs").jqxGrid('updatebounddata', 'filter');
                },
            }),

            // 15102024 Audit control add  extra sec start
            $audit_activity_viewer = $("#tab_audit_activity #document-viewer"),
            $audit_activity_editor = null,
            panel_audit_activity_Source = new $.jqx.dataAdapter({
                url: "/record/process?action=load_list_audit_activity", // At the moment using notes backend - That might change in the future !
                data: {
                    link_id: master.config.id,
                    track: master.config.track,
                },
                datatype: "json",
                datafields: [
                    {name: 'ID', type: 'string'},
                    {name: 'reference_no', type: 'string'},
                    {name: 'dsc', type: 'string'},
                    {name: 'risk', type: 'string'},
                    {name: 'improvement', type: 'string'},
                    {name: 'status', type: 'string'},
                    {name: 'risk_val', type: 'string'},

                    {name: 'title', type: 'string'},
                    {name: 'type', type: 'string'},
                    {name: 'person', type: 'string'},
                    {name: 'result', type: 'string'},
                    {name: 'comments', type: 'string'},
                    {name: 'deadline_date', type: 'string'},
                    {name: 'closing_date', type: 'string'},
                    { name: 'ctime', type: 'string' }, //created time added to capa
                    { name: 'created_by_name', type: 'string' }, //user added to capa

                    {name: 'isCreateTask', type: 'string'},
                    {name: 'isCreateTaskLable', type: 'string'},
                    {name: 'isCreateTaskId', type: 'string'},

                    // 31122024 Audit control add field by iwi start
                    {name: 'caPa_types', type: 'string'},
                    {name: 'caPa_procedure', type: 'string'},
                    {name: 'caPa_oth_procedures', type: 'string'},
                    // 31122024 Audit control add field by iwi end
                    {name: 'action_type', type: 'string'},//IWI Quality event record panel section Investigation modification 08122025
                    
                ],
                id: "id",
                sortcolumn: 'id',
                sortdirection: 'desc'
            }),
            // 15102024 Audit control add  extra sec end




            // 27112024 Customer Inquiry -> CAPA sec added by iwi start
            $capa_sec_by_customer_inquiry_viewer = $("#capa_sec_by_customer_inquiry #document-viewer"),
            $capa_sec_by_customer_inquiry_editor = null,
            panel_capa_sec_by_customer_inquiry_Source = new $.jqx.dataAdapter({
                url: "/record/process?action=Load_list_audit_activity", // At the moment using notes backend - That might change in the future !
                data: {
                    link_id: master.config.id,
                    track: master.config.track,
                },
                datatype: "json",
                datafields: [
                    {name: 'ID', type: 'string'},
                    {name: 'reference_no', type: 'string'},
                    {name: 'dsc', type: 'string'},
                    {name: 'risk', type: 'string'},
                    {name: 'improvement', type: 'string'},
                    {name: 'status', type: 'string'},
                    {name: 'risk_val', type: 'string'},

                    {name: 'title', type: 'string'},
                    {name: 'type', type: 'string'},
                    {name: 'person', type: 'string'},
                    {name: 'result', type: 'string'},
                    {name: 'comments', type: 'string'},
                    {name: 'deadline_date', type: 'string'},
                    {name: 'closing_date', type: 'string'},
                     { name: 'ctime', type: 'string' }, //created time added to capa
                     { name: 'created_by_name', type: 'string' }, //user added to capa

                    {name: 'isCreateTask', type: 'string'},
                    {name: 'isCreateTaskLable', type: 'string'},
                    {name: 'isCreateTaskId', type: 'string'},

                    // 28112024 added by iwi start
                    {name: 'caPa_types', type: 'string'},
                    {name: 'caPa_procedure', type: 'string'},
                    {name: 'caPa_oth_procedures', type: 'string'},
                    // 28112024 added by iwi end
                    {name: 'action_type', type: 'string'},//IWI Quality event record panel section Investigation modification 08122025
                    
                ],
                id: "id",
                sortcolumn: 'id',
                sortdirection: 'desc'
            }),
            // 27112024 Customer Inquiry -> CAPA sec added by iwi start end



            // 28112024 Quality Event -> CAPA sec added by iwi start
            $capa_sec_by_quality_event_viewer = $("#capa_sec_by_quality_event #document-viewer"),
            $capa_sec_by_quality_event_editor = null,
            panel_capa_sec_by_quality_event_Source = new $.jqx.dataAdapter({
                url: "/record/process?action=Load_list_audit_activity", // At the moment using notes backend - That might change in the future !
                data: {
                    link_id: master.config.id,
                    track: master.config.track,
                },
                datatype: "json",
                datafields: [
                    {name: 'ID', type: 'string'},
                    {name: 'reference_no', type: 'string'},
                    {name: 'dsc', type: 'string'},
                    {name: 'risk', type: 'string'},
                    {name: 'improvement', type: 'string'},
                    {name: 'status', type: 'string'},
                    {name: 'risk_val', type: 'string'},

                    {name: 'title', type: 'string'},
                    {name: 'type', type: 'string'},
                    {name: 'person', type: 'string'},
                    {name: 'result', type: 'string'},
                    {name: 'comments', type: 'string'},
                    {name: 'deadline_date', type: 'string'},
                    {name: 'closing_date', type: 'string'},
                    { name: 'ctime', type: 'string' }, //created time added to capa
                    { name: 'created_by_name', type: 'string' }, //user added to capa
                    

                    {name: 'isCreateTask', type: 'string'},
                    {name: 'isCreateTaskLable', type: 'string'},
                    {name: 'isCreateTaskId', type: 'string'},

                    {name: 'caPa_types', type: 'string'},
                    {name: 'caPa_procedure', type: 'string'},
                    {name: 'caPa_oth_procedures', type: 'string'},
                    {name: 'action_type', type: 'string'},//IWI Quality event record panel section Investigation modification 08122025
                    
                ],
                id: "id",
                sortcolumn: 'id',
                sortdirection: 'desc'
            }),
            // 28112024 Quality Event -> CAPA sec added by iwi start end


            
//            IWI  manage contracts for Supplier control 19052026 start
            panel_Contract_Management_Source = new $.jqx.dataAdapter({
                url: "/record/process?action=load_contract_management",
                type: "POST",
                data: {
                    link_id: master.config.id,
                    track: master.config.track
                },
                datatype: "json",
                loadComplete: function (records) {
                    
//                    IWI Supplier control changes 31082026 start
                    $('.contract-filter-btn[data-status="active"]').trigger('click');
                    setTimeout(function () {
                        $.each(records.data, function (i, record) {
                            if (record.is_terminate === 'Yes') {
                                $('tr[data-key="' + record.id + '"]')
                                        .addClass('contract-terminated-row');
                            }
                        });
                    }, 300);
//                    IWI Supplier control changes 31082026 end
                    if (!records.summary) {
                        return;
                    }
                    var summary = records.summary;
                    $("#count_all_contracts").html(summary.total_contracts);
                    $("#count_active_contracts").html(summary.total_active);
                    $("#count_closed_contracts").html(summary.total_closed);
                    $("#count_terminated_contracts").html(summary.total_terminated);
                    var totalYear = parseFloat(summary.total_year_value || 0);
                    var totalMonth = parseFloat(summary.total_month_value || 0);
                    $("#total_year_value").html(totalYear + ' &euro; ');
                    $("#total_month_value").html(totalMonth+ ' &euro; ');
                    $("#total_year_value_amt").val(totalYear);
                    $("#total_month_value_amt").val(totalMonth);

//                    IWI Make changes in panel for supplier & asset 05062026 start
                    $.ajax({
                        url: "/record/process?action=update_xfield",
                        type: "POST",
                        dataType: "json",
                        data: {
                            page:'supplier_panel_contract',
                            link_id: master.config.id, 
                            contract_val_year: summary.total_year_value,
                            contract_val_month: summary.total_month_value,
                            curr: '978'
                        },
                        success: function (response) {
                        console.log(response.data);
                           
                        }
                    });
//                    IWI Make changes in panel for supplier & asset 05062026 end
                setTimeout(function () {                                 // contract read_only_start
                    var rows = $("#contract_management_module div.contract-table-container")
                        .jqxDataTable('getRows');

                    if (rows.length > 0) {
                        $("#con_contract").html("Yes");
                    } else {
                        $("#con_contract").html("No");
                    }
                }, 100);                                                // contract read_only_end
                
                },
                datafields: [
                    {
                        name: 'selected',
                        type: 'bool'
                    }, {
                        name: 'id',
                        type: 'number'
                    }, {
                        name: 'track',
                        type: 'string'
                    }, {
                        name: 'issue_id',
                        type: 'number'
                    }, {
                        name: 'issue_type_id',
                        type: 'number'
                    }, {
                        name: 'org_id',
                        type: 'number'
                    }, {
                        name: 'contract_name',
                        type: 'string'
                    }, {
                        name: 'reference',
                        type: 'string'
                    }, {
                        name: 'remarks',
                        type: 'string'
                    }, {
                        name: 'location',
                        type: 'string'
                    },
                    {
                        name: 'department',
                        type: 'string'
                    },
                    {
                        name: 'country',
                        type: 'string'
                    }, {
                        name: 'start_date',
                        type: 'string'
                    }, {
                        name: 'end_date',
                        type: 'string'
                    }, {
                        name: 'year_value',
                        type: 'string'
                    }, {
                        name: 'month_value',
                        type: 'string'
                    }, {
                        name: 'contract_nr',
                        type: 'string'
                    }, {
                        name: 'contract_type',
                        type: 'string'
                    }, {
                        name: 'approved_by',
                        type: 'string'
                    }, {
                        name: 'assign_to',
                        type: 'string'
                    }, {
                        name: 'assign_to_name',
                        type: 'string'
                    }, {
                        name: 'status',
                        type: 'string'
                    }, {
                        name: 'ctime',
                        type: 'string'
                    }, {
                        name: 'utime',
                        type: 'string'
                    },  {
                        name: 'contract_period',
                        type: 'string'
                    },
                    {
                        name: 'auto_terminate',
                        type: 'number'
                    },
                    {
                        name: 'is_terminate',
                        type: 'string'
                    },
                    {
                        name: 'terminate_date',
                        type: 'string'
                    },
                    {
                        name: 'notice_period',                 //Notice Period Dropdown
                        type: 'string'
                    },
                    {
                        name: 'notice_date',                 
                        type: 'string'
                    },
                    {
                        name: 'contract_auto_renewal',                 
                        type: 'string'
                    },
                    {
                        name: 'files',                 
                        type: 'string'
                    },
                    { name: 'file_id',    type: 'string' } //files upload in contract management form
                ],
                id: "id"
            }),
            init_DataTableContractManagement = function () {
                master.init_DataTable($("#contract_management_module div.contract-table-container"), {
                    showToolbar: false,
                    altRows: true,
                    sortable: true,
                    pageable: true,
                    columnsResize: true,
                    selectionMode: 'multipleRows',
//                    showToolbar: true,
                    filterable: true,
                    filterMode: 'simple',
                    
//                    IWI Adding assets from Supplier control contract section 28072026  start
                    rowDetails: true,
                    initRowDetails: function (id, datarow, element, rowinfo) {

                        $(element).html("Loading...");

                        $.ajax({
                            url: "/record/process?action=get_contract_assets",
                            type: "POST",
                            dataType: "json",
                            data: {
                                contract_id: id
                            },
                            success: function (response) {
                                var html = '';

                                html += '<div class="contract-assets-details">';

                                html += '<table class="contract-assets-grid">';
                                html += '<thead>';
                                html += '<tr>';
                                html += '<th>Asset Track</th>';
                                html += '<th>Asset Name</th>';
                                html += '<th>Brand</th>';
                                html += '<th>Location</th>';
                                html += '<th>Department</th>';
                                html += '</tr>';
                                html += '</thead>';
                                html += '<tbody>';

                                if (response.assets && response.assets.length > 0) {

                                    $.each(response.assets, function (i, asset) {

                                        html += '<tr>';
                                        html += '<td><a target="_blank" href="/record/process:' + asset.asset_track + '#panels">' + asset.asset_track + '</a></td>';
                                        html += '<td>' + asset.asset_name + '</td>';
                                        html += '<td>' + asset.brand_name + '</td>';
                                        html += '<td>' + asset.location_code + '</td>';
                                        html += '<td>' + asset.department + '</td>';
                                        html += '</tr>';

                                    });

                                } else {

                                    html += '<tr>';
                                    html += '<td colspan="5" class="contract-assets-empty">No linked assets found.</td>';
                                    html += '</tr>';

                                }

                                html += '</tbody>';
                                html += '</table>';

                                html += '</div>';

                                $(element).html(html);
                            }
                        });
                    },
//                    IWI Adding assets from Supplier control contract section 28072026  end
                    exportSettings: {
                        columnsHeader: true,
                        hiddenColumns: false,
                        serverURL: "/?action=export",
                        characterSet: null,
                        collapsedRecords: false,
                        recordsInView: true,
                        fileName: "contract_management"
                    },
                    source: panel_Contract_Management_Source,
                    columns: [
                        {
                            text: '',
                            width: 40,
                            cellsRenderer: function (row, column, value, rowData) {

                                return '<div style="margin-top:8px;text-align:center;">' +
                                        '<input type="checkbox" class="contract-row-check" data-id="' + rowData.id + '">' +
                                        '</div>';
                            }
                        }, {
                            dataField: 'contract_name',
                            text: 'Contract Name',
                            width: '12%'
                        },
                        {
                            dataField: 'contract_nr',
                            text: 'Contract Nr',
                            width: '10%'
                        },
                        {
                            dataField: 'contract_type',
                            text: 'Type',
                            width: '9%'
                        },
                        {
                            dataField: 'contract_period',
                            text: 'Period',
                            width: '12%'
                        },
                        {
                            dataField: 'location',
                            text: 'Location',
                            width: '7%'
                        },
                        {
                            dataField: 'country',
                            text: 'Country',
                            width: '7%'
                        }, {
                            dataField: 'start_date',
                            text: 'Start Date',
                            width: '10%'
                        }, {
                            dataField: 'end_date',
                            text: 'End Date',
                            width: '10%'
                        }, {
                            dataField: 'notice_date',   
                            text: 'Notice Date',
                            width: '10%'
                        },
                         {
                            dataField: 'notice_period',   //Notice Period Dropdown
                            text: 'Notice',
                            width: '10%'
                         }, 
                        //{
                        //     dataField: 'assign_to_name',
                        //     text: 'Assigned To',
                        //     width: '10%'
                        // },
                         {
                            dataField: 'year_value',
                            text: 'Year Value (&euro;)',
                            width: '12%'
                        }, {
                            dataField: 'month_value',
                            text: 'Month Value (&euro;)',
                            width: '12%'
                        }, {
                            dataField: 'approved_by',
                            text: 'Approved By',
                            width: '10%'
                        }, 
                        {
                            text: 'Is Terminate',
                            dataField: 'is_terminate',
                            width: '10%',
                            hidden: true
                        }, {
                            dataField: 'terminate_date',
                            text: 'Terminated Date',
                            width: '10%',
                            hidden: true
                        },
                        {
                            dataField: 'files',    //files upload in contract management form
                            text: 'Link',
                            width: '10%'
                        },
                        {
                            dataField: 'status',
                            text: 'Status',
                            width: '8%',
                            cellsRenderer: function (row, column, value) {
                                if (value === 'active') {
                                    return '<div style="margin-top:8px;">' + '<span style="padding:4px 10px;background:#dcfce7;color:#15803d;border-radius:20px;font-size:12px;font-weight:600;">ACTIVE</span>' + '</div>';
                                } else {
                                    return '<div style="margin-top:8px;">' + '<span style="padding:4px 10px;background:#fee2e2;color:#b91c1c;border-radius:20px;font-size:12px;font-weight:600;">CLOSED</span>' + '</div>';
                                }
                            }
                        }]
                }
                , {
                    rowselect: initContractManagementSelectRow,
                    clearselect: select_Clear,
                    rowopen: initContractManagementOpen
                }, "contract_management");

                
            },

//          IWI Supplier control changes 31082026 start
            addTerminatedRowClass = function () {
                setTimeout(function () {
                    var rows = $("#contract_management_module div.contract-table-container").jqxDataTable('getRows');
                    $.each(rows, function (i, record) {
                        if (record.is_terminate === 'Yes') {
                            $('tr[data-key="' + record.id + '"]').addClass('contract-terminated-row');
                        }
                    });
                }, 300);
            },
//          IWI Supplier control changes 31082026 end
            init_ContractManagement_Toolbar = function () {
                $("#contract_management_module .contract-toolbar-right button").jqxButton({
                    theme: cargohub.jqxtheme
                }).on('click', function () {
                    switch ($(this).attr('id')) {
                        case 'contract_btn_export':
                            $("#contract_management_module .contract-table-container").jqxDataTable('exportData', 'xls');
                            break;
                    }
                });
                $("#contract_bulk_active_btn").on("click", function () {
                    updateContractStatus('active');
                });
                $("#contract_bulk_closed_btn").on("click", function () {
                    updateContractStatus('closed');
                    updateContractTerminated('1');
                });
                $("#contract_bulk_assign_btn").on("click", function () {
                    var assign_to = $("#contract_bulk_assign").val();
                    assignContract(assign_to);
                });
                
                $("#contract_bulk_terminate_btn").on("click", function () {
                    Swal.fire({
                        title: "Are you sure?",
                        text: "This will terminate the selected contract(s).",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#d33",
                        cancelButtonColor: "#6c757d",
                        confirmButtonText: "Yes, Terminate",
                        cancelButtonText: "Cancel"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            updateContractTerminated('1');
                        }
                    });
                });
                
                $("#contract_bulk_restore_terminate_btn").on("click", function () {
                    Swal.fire({
                        title: "Are you sure?",
                        text: "his will restore the selected contract(s) from terminated.",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#d33",
                        cancelButtonColor: "#6c757d",
                        confirmButtonText: "Yes, Terminate",
                        cancelButtonText: "Cancel"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            updateContractTerminated('0');
                        }
                    });
                });
                $(document).on("change", ".contract-row-check", function () {
                    var activeTab = $(".contract-filter-btn.active").data("status");
                    if (activeTab == 'terminated') {
                        $('#contract_bulk_assign ,#contract_bulk_assign_btn ,#contract_bulk_active_btn , #contract_bulk_closed_btn ,#contract_bulk_terminate_btn').hide();
                        $('#contract_bulk_restore_terminate_btn').show();
                    } else {
                        $('#contract_bulk_assign ,#contract_bulk_assign_btn ,#contract_bulk_active_btn , #contract_bulk_closed_btn ,#contract_bulk_terminate_btn').show();
                        $('#contract_bulk_restore_terminate_btn').hide();
                    }
                    var ids = getSelectedContractIds();
                    $("#contract_selected_count").html(ids.length + " contracts selected");
                    if (ids.length > 0) {
                        $(".contract-bulk-toolbar").slideDown(150);
                    } else {
                        $(".contract-bulk-toolbar").slideUp(150);
                    }
                });
                $(document).on("click", ".contract-filter-btn", function () {
                    $(".contract-row-check").prop("checked", false).trigger("change");
                    $(".contract-filter-btn").removeClass("active");
                    $(this).addClass("active");
                    var status = $(this).data("status");
                    console.log(status);
                    if (status === 'all') {
                        $("#_contracts_record").jqxDataTable('clearFilters');
                    } 
                   else if (status === 'terminated' ) {
                        var filtergroup = new $.jqx.filter();
                        var filter_or_operator = 1;
                        var filtervalue = status;
                        var filtercondition = 'EQUAL';
                        var filter = filtergroup.createfilter(
                                'stringfilter',
                                'Yes',
                                filtercondition
                                );
                        filtergroup.addfilter(filter_or_operator, filter);
                        $("#_contracts_record").jqxDataTable('clearFilters');
                        $("#_contracts_record").jqxDataTable('addFilter', 'is_terminate', filtergroup);
                        $("#_contracts_record").jqxDataTable('applyFilters');
                    }
                   else  {
                        var filtergroup = new $.jqx.filter();
                        var filter_or_operator = 1;
                        var filtervalue = status;
                        var filtercondition = 'EQUAL';
                        var filter = filtergroup.createfilter(
                                'stringfilter',
                                filtervalue,
                                filtercondition
                                );
                        filtergroup.addfilter(filter_or_operator, filter);
                        $("#_contracts_record").jqxDataTable('clearFilters');
                        $("#_contracts_record").jqxDataTable('addFilter', 'status', filtergroup);
                        $("#_contracts_record").jqxDataTable('applyFilters');
                    }
                    
                    if (status === "terminated") {
                        $("#_contracts_record").jqxDataTable("showColumn", "terminate_date");
                        $("#_contracts_record").jqxDataTable("showColumn", "is_terminate");
                    } else {
                        $("#_contracts_record").jqxDataTable("hideColumn", "terminate_date");
                        $("#_contracts_record").jqxDataTable("hideColumn", "is_terminate");
                    }
                    
                     addTerminatedRowClass(); // IWI Supplier control changes 31082026
                });
            },
            initContractManagementSelectRow = function (row) {
//                console.log('Selected Row', row);
                var ids = getSelectedContractIds();
                $("#contract_selected_count").html(ids.length + " Selected");
            },
            initLoadUsers = function (row) {
                $.ajax({
                    url: "/record/process?action=load_users",
                    type: "POST",
                    dataType: "json",
                    data: {
                        org_id: master.config.perspective
                    },
                    success: function (response) {
//                        console.log(response.data);
                        $("#contract_bulk_assign").html(response.data);
                    }
                });
            },
             // files upload in contract management form_start
            initLoadFiles = function (row, selectedFileId) {

                    $.ajax({
                        url: "/global/note?action=Get_Attach_File_Source",
                        type: "POST",
                        dataType: "json",
                        data: {
                            link_id: master.config.id
                        },
                        success: function (response) {

                            var html = '';

                            $('#contract-attach-file').html(
                                '<option value="">Select File</option>'
                            );

                            $.each(response, function(index, item) {

                                var temp = $('<div>').html(item.file_desc);

                                var img = temp.find('img').prop('outerHTML');
                                var fileName = temp.text().trim();

                                /*
                                * Hidden select
                                */
                                $('#contract-attach-file').append(
                                    '<option value="' + item.file_id + '">' +
                                    fileName +
                                    '</option>'
                                );

                                /*
                                * Custom dropdown
                                */
                                html += '<div class="file-option" data-value="' + item.file_id + '">';
                                html += img;
                                html += '<span class="file-option-name">' + fileName + '</span>';
                                html += '</div>';
                            });

                            $('#contract-file-dropdown').html(html);


                            /*
                            * PRESELECT FILE WHEN EDITING
                            */
                            if (selectedFileId) {

                                $('#contract-attach-file').val(selectedFileId);

                                /*
                                * Find selected file name
                                */
                                var selectedOption = $('#contract-file-dropdown')
                                    .find('.file-option[data-value="' + selectedFileId + '"]');

                                if (selectedOption.length) {

                                    var fileName = selectedOption
                                        .find('.file-option-name')
                                        .text();

                                    $('#contract-file-selector .selected-file-text')
                                        .text(fileName);
                                }
                            }
                        }
                    });
                },

            // files upload in contract management form_end

            getSelectedContractIds = function () {

                var ids = [];

                $(".contract-row-check:checked").each(function () {

                    ids.push($(this).data("id"));

                });

                $("#contract_selected_count").html(ids.length + " Selected");

                return ids;
            },
            updateContractStatus = function (status) {

                var ids = getSelectedContractIds();

                if (ids.length === 0) {

                    Swal.fire({
                        icon: 'warning',
                        title: 'Please select contracts'
                    });

                    return;
                }

                $.ajax({

                    url: "/record/process?action=bulk_status_contract",

                    type: "POST",

                    data: {
                        ids: ids,
                        status: status
                    },

                    success: function (response) {

                        $("#_contracts_record").jqxDataTable('updateBoundData');

                        Swal.fire({
                            icon: 'success',
                            title: 'Updated Successfully'
                        });
                        init_DataTableContractManagement();

                    }

                });

            },
            updateContractTerminated = function (val) {
                var ids = getSelectedContractIds();
                if (ids.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Please select contracts'
                    });
                    return;
                }
                $.ajax({
                    url: "/record/process?action=bulk_terminate_contract",
                    type: "POST",
                    data: {
                        ids: ids,
                        val: val
                    },
                    success: function (response) {
                        $("#_contracts_record").jqxDataTable('updateBoundData');
                        Swal.fire({
                            icon: 'success',
                            title: 'Updated Successfully'
                        });
                        init_DataTableContractManagement();
                    }
                });
            },
            assignContract = function (assign_to) {
                var ids = getSelectedContractIds();
                if (ids.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Please select contracts'
                    });
                    return;
                }
                if (assign_to === '') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Please select user'
                    });
                    return;
                }
                $.ajax({
                    url: "/record/process?action=bulk_assign_contract",
                    type: "POST",
                    dataType: "json",
                    data: {
                        ids: ids,
                        assign_to: assign_to
                    },
                    success: function (response) {
                        $("#_contracts_record").jqxDataTable('updateBoundData');
                        $(".contract-row-check").prop("checked", false);
                        $(".contract-bulk-toolbar").slideUp(150);
                        $("#contract_selected_count").html("0 contracts selected");
                        Swal.fire({
                            icon: 'success',
                            title: response.message || 'Assigned Successfully'
                        });
                    },
                    error: function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Server Error'
                        });
                    }
                });

            },
            initContractManagementOpen = function (row) {
                $("#contract_management_module .contract-accordion-content").stop(true, true).slideDown(200);
                $("#contract_record_id").val(row.id);
                $("#contract_create_name").val(row.contract_name);
                $("#contract_create_reference").val(row.reference);
                $("#contract_create_remarks").val(row.remarks);
                $("#contract_create_location").val(row.location);
                $("#contract_create_department").val(row.department);
                $("#contract_create_country").val(row.country);
                $("#contract_create_start_date").val(row.start_date);
                $("#contract_create_end_date").val(row.end_date);
                $("#contract_create_year_value").val(row.year_value);
                $("#contract_create_month_value").val(row.month_value);
                $("#contract_create_number").val(row.contract_nr);
                $("#contract_create_approved_by").val(row.approved_by);
                $("#contract_period").val(row.contract_period);
                // $("input[name='contract_type'][value='" + row.contract_type + "']").prop("checked", true);   //contract_type_dropdown--080626
                $("#contract_type").val(row.contract_type);   //contract_type_dropdown--080626
                toggleContractFields();

                if (row.auto_terminate == 1 || row.auto_terminate == '1') {
                    $("#contract_auto_terminate").prop("checked", true);
                    if (row.contract_type !== 'Owned') {
                        $("#notice_period_wrapper").show();  //Notice Period Dropdown
                        $("#notice_date_wrapper").show();
                        $("#contract_notice_date").val(row.notice_date);
                        $("#notice_period").val(row.notice_period);    //Notice Period Dropdown
                    }
                } else {
                    $("#contract_auto_terminate").prop("checked", false);
                     $("#notice_period_wrapper").hide();  //Notice Period Dropdown
                    $("#notice_date_wrapper").hide();
                    $("#contract_notice_date").val(''); 
                    $("#notice_period").val(''); //Notice Period Dropdown
                }
                if (row.contract_period === "Auto renewal") {
                    $("#auto_renewal_wrapper").show();
                    $("#contract_period_wrapper").css("flex", "1");
                    $("#auto_renewal").val(row.contract_auto_renewal); 
                } else {
                    $("#auto_renewal_wrapper").hide();
                    $("#contract_period_wrapper").css("flex", "100%");
                    $("#auto_renewal").val("");
                }
                $("input[name='contract_status'][value='" + row.status + "']").prop("checked", true);
                loadContractAssets(row.id); //IWI Adding assets from Supplier control contract section 28072026
                initLoadFiles(row, row.file_id); //files upload in contract management form
                $("#contract_save_btn").html("Save");
            },
            
//            IWI Adding assets from Supplier control contract section 28072026 start
             loadContractAssets = function(contractId) {

    $("#assetContainer").empty();
    $("#linkedAssetsTableContainer").empty();
    $("#linkedAssetsSection").hide();

    $.ajax({
        url: "/record/process?action=get_contract_assets",
        type: "POST",
        dataType: "json",
        data: {
            contract_id: contractId
        },
        success: function(response) {

            if (!response.success || response.assets.length === 0) {
                $("#linkedAssetsSection").hide();
                return;
            }

            var html = '';

            html += '<table class="contract-asset-table">';
            html += '<thead>';
            html += '<tr>';
            html += '<th>Asset Track</th>';
            html += '<th>Asset Name</th>';
            html += '<th>Brand</th>';
            html += '<th>Location</th>';
            html += '<th>Department</th>';
            html += '<th width="80">Action</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';

            $.each(response.assets, function(i, asset){

                html += '<tr data-asset-id="'+asset.asset_issue_id+'">';
                html += '<td><a target="_blank" href="/record/process:'+asset.asset_track+'#panels">'+asset.asset_track+'</a></td>';
                html += '<td>'+asset.asset_name+'</td>';
                html += '<td>'+asset.brand_name+'</td>';
                html += '<td>'+asset.location_code+'</td>';
                html += '<td>'+asset.department+'</td>';

                html += '<td>';
                html += '<button type="button"  class="asset-unlink-btn"  data-contract-link-id="'+asset.id+'"  data-asset-id="'+asset.asset_issue_id+'">';
                html += '<i class="fa fa-trash"></i>';
                html += '</button>';
                html += '</td>';

                html += '</tr>';

            });

            html += '</tbody>';
            html += '</table>';
$("#linkedAssetsSection").show();
            $("#linkedAssetsTableContainer").html(html);

        }
    });

},
//            IWI Adding assets from Supplier control contract section 28072026 end
            initContractManagementSave = function () {
                $(".contract-input-field").removeClass("contract-field-error");

                var contract_name = $("#contract_create_name").val().trim();
                var contract_nr = $("#contract_create_number").val().trim();
                var start_date = $("#contract_create_start_date").val();
                var end_date = $("#contract_create_end_date").val();
                var auto_terminate = $("#contract_auto_terminate").is(":checked");
                var notice_date = $("#contract_notice_date").val();
                var notice_period = $("#notice_period").val();
                var contract_period = $("#contract_period").val();
                var contract_type = $("#contract_type").val();
                var month_value = $("#contract_create_month_value").val();

                if (contract_name === '') {
                    $("#contract_create_name").addClass("contract-field-error").focus();
                    return false;
                }

                if (contract_nr === '') {
                    $("#contract_create_number").addClass("contract-field-error").focus();
                    return false;
                }

                if (!contract_period) {
                    $("#contract_period").addClass("contract-field-error").focus();
                    return false;
                }

                if (!contract_type) {
                    $("#contract_type").addClass("contract-field-error").focus();
                    return false;
                }

                if (!start_date) {
                    $("#contract_create_start_date").addClass("contract-field-error").focus();
                    return false;
                }

                if (!month_value) {
                    $("#contract_create_month_value").addClass("contract-field-error").focus();
                    return false;
                }

                var form = $("#contract_create_form");

                // Create FormData
                var formData = new FormData(form[0]);

                // Append extra values
                formData.append("track", master.config.track);
                formData.append("link_id", master.config.id);
                formData.append("type_id", master.config.type.base_id ? master.config.type.base_id : master.config.type.id);
                formData.append("org_id", master.config.perspective);
                $("#contract_save_btn").prop("disabled", true);
                $.ajax({
                    url: form.attr("action"),
                    type: form.attr("method"),
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: "json",
// IWI Adding assets from Supplier control contract section 28072026 start
                    
                    success: function (response) {
                        $("#contract_save_btn").prop("disabled", false);
                        if (!response.success) {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: response.message || "Unable to save contract",
                                confirmButtonColor: "#dc2626"
                            });
                            return;
                        }
                        var contractId = response.contract_id;  
                        var createdAssets = [];
                        var assetRequests = [];
                        $("#assetContainer .asset-row").each(function () {
                            var asset_name = $.trim($(this).find("input[name='asset_name[]']").val());
                            var brand_name = $.trim($(this).find("input[name='brand_name[]']").val());
                            var location_code = $.trim($(this).find("input[name='location_code[]']").val());
                            var department = $.trim($(this).find("input[name='department[]']").val());
                            if (asset_name === "" && brand_name === "" && location_code === "" && department === "") {
                                return;
                            }
                            
                            assetRequests.push(
                                    $.ajax({
                                        url: "/issue?action=form",
                                        type: "POST",
                                        dataType: "json",
                                        data: {
                                            _zone: "public",
                                            _module: "issue",
                                            action: "form",
                                            ajax: 1,
                                            key: 56,
                                            issue: {
                                                group: master.config.group.id 
                                            },
                                            xfd: {
                                                title: asset_name,
                                                b_name: brand_name,
                                                location: location_code,
                                                desc: department
                                            },
                                            contacts: JSON.stringify({
                                                submitter: {
                                                    label: "Submitted by",
                                                    contact: {
                                                        id: $("#login_user_contact_id").val(),
                                                        type: "user_self",
                                                        email: $("#login_user_email").val(),
                                                        name: $("#login_user_name").val()
                                                    }
                                                }
                                            }),
                                            submit_options: {
                                                process: ""
                                            }
                                        },
                                        success: function (assetResponse) {
                                            if (assetResponse.success) {
                                                createdAssets.push({
                                                    issue_id: assetResponse.issue_id,
                                                    track: assetResponse.track
                                                });
                                            }
                                        }
                                    })
                                    );
                        });
                        $.when.apply($, assetRequests).done(function () {
                            var linkRequests = [];
                            $.each(createdAssets, function (index, asset) {
                                linkRequests.push(
                                        $.ajax({
                                            url: "/record/process?action=save_linked_supplier",
                                            type: "POST",
                                            dataType: "json",
                                            data: {
                                                link_id: asset.issue_id,
                                                track: asset.track,
                                                org_id: master.config.perspective,
                                                type_id: master.config.type.base_id ? master.config.type.base_id : master.config.type.id,
                                                supplier_id: master.config.id,
                                                contract_id: contractId
                                            }
                                        })
                                        );
                            });
                            $.when.apply($, linkRequests).done(function () {
                                $("#contract_management_module .contract-accordion-content").slideUp(200);
                                $("#_contracts_record").jqxDataTable("updateBoundData");
                                $("#contract_create_form")[0].reset();
                                $("#contract_record_id").val(0);
                                $("#contract_save_btn").html("Save");
                                $("#notice_date_wrapper").hide();
                                $("#notice_period_wrapper").hide();
                                $("input[name='contract_status'][value='active']").prop("checked", true);
                                init_DataTableContractManagement();
                                Swal.fire({
                                    icon: "success",
                                    title: "Success",
                                    text: response.message || "Contract and Assets saved successfully",
                                    confirmButtonColor: "#2563eb"
                                });
                            });
                        });
                    },
// IWI Adding assets from Supplier control contract section 28072026 end
                    error: function () {
                        $("#contract_save_btn").prop("disabled", false);
                        Swal.fire({
                            icon: 'error',
                            title: 'Server Error',
                            text: 'Something went wrong',
                            confirmButtonColor: '#dc2626'
                        });
                    }
                });
            },
            toggleContractFields = function () {
                var contractType = $("input[name='contract_type']:checked").val();
                console.log(contractType);
                if (contractType === 'Owned') {
                    $("#contract_create_end_date").prop("disabled", true).val('');
                    $("#contract_auto_terminate").prop("checked", false).prop("disabled", true);
                    $("#notice_date_wrapper").hide();
                     $("#notice_period_wrapper").hide();  //Notice Period Dropdown
                    $("#contract_notice_date").val('').prop("disabled", true);
                } else {
                    $("#contract_create_end_date").removeAttr("disabled");
                    $("#contract_auto_terminate").removeAttr("disabled");
                    $("#contract_notice_date").removeAttr("disabled");
                }
            },

//            IWI  manage contracts for Supplier control 19052026 end


//IWI  link Supplier and contracts for Asset Control 21052026 start


            linkedSupplier = null,
            initSupplierCombos = function () {

                $.ajax({
                    url: "/record/process?action=load_suppliers",
                    type: "POST",
                    dataType: "json",
                    data: {
                        link_id: master.config.id,
                        track: master.config.track,
                        org_id: master.config.perspective,
                        type_id: '55'
                    },
                    success: function (response) {
                        var suppliers = response.data || [];
                        suppliers = suppliers.map(function (item) {
                            return {
                                label: item.label ? item.label : item.track,
                                value: item.value
                            };
                        });
                        $("#contract_supplier_name").jqxComboBox({
                            source: suppliers,
                            displayMember: "label",
                            valueMember: "value",
                            width: '100%',
                            height: 40,
                            autoComplete: true,
                            searchMode: 'containsignorecase',
                            placeHolder: "Select Supplier"
                        });
                    }
                });

                $("#contract_create_contract").jqxComboBox({
                    source: [],
                    displayMember: "label",
                    valueMember: "value",
                    width: '100%',
                    height: 40,
                    autoComplete: true,
                    searchMode: 'containsignorecase',
                    disabled: true,
                    placeHolder: "Select Contract"
                });

                $('#contract_supplier_name').on('select', function (event) {
                    var item = event.args.item;
                    if (!item)
                        return;
                    var supplier_id = item.value;
                    $.ajax({
                        url: "/record/process?action=load_supplier_contracts",
                        type: "POST",
                        dataType: "json",
                        data: {
                            supplier_id: supplier_id
                        },
                        success: function (response) {
                            var contracts = response.data || [];
                            $("#contract_create_contract").jqxComboBox({
                                source: contracts,
                                disabled: false
                            });
                        }
                    });

                });



            },
            bindEvents = function ()
            {
                $("#asset_supplier_create_form").on("submit", function (e) {
                    e.preventDefault();
                    var supplier = $("#contract_supplier_name")
                            .jqxComboBox('getSelectedItem');

                    var contract = $("#contract_create_contract")
                            .jqxComboBox('getSelectedItem');

                    if (!supplier || !contract) {

                        Swal.fire({
                            icon: 'warning',
                            title: 'Please select supplier and contract'
                        });

                        return;
                    }

                    $.ajax({
                        url: '/record/process?action=save_linked_supplier',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            link_id: master.config.id,
                            track: master.config.track,
                            org_id: master.config.perspective,
                            type_id: master.config.type.base_id ? master.config.type.base_id : master.config.type.id,
                            supplier_id: supplier.value,
                            contract_id: contract.value
                        },
                        success: function (response)
                        {
                            if (response.success) {
                                linkedSupplier = response.data;
                                loadLinkedSupplier();
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Linked Successfully'
                                });

                            } else {

                                Swal.fire({
                                    icon: 'error',
                                    title: response.message || 'Unable to save'
                                });

                            }

                        },

                        error: function () {

                            Swal.fire({
                                icon: 'error',
                                title: 'Server Error'
                            });

                        }

                    });

                });

                $("#asset_supplier_edit_btn").on("click", function () {

                    $("#asset_supplier_form_container").show();

                    $("#asset_supplier_name")
                            .jqxComboBox('selectItem', linkedSupplier.supplier_id);

                    $("#contract_create_contract").jqxComboBox({

                        source: [
                            {
                                label: linkedSupplier.contract_name,
                                value: linkedSupplier.contract_id
                            }
                        ],

                        disabled: false

                    });

                    $("#contract_create_contract")
                            .jqxComboBox('selectItem', linkedSupplier.contract_id);

                });

            },
//             loadLinkedSupplier = function () {
//                 $.ajax({
//                     url: "/record/process?action=load_linked_supplier",
//                     type: "POST",
//                     data: {
//                         link_id: master.config.id,
//                         track: master.config.track,
//                         org_id: master.config.perspective
//                     },
//                     dataType: "json",
//                     success: function (response)
//                     {
//                         linkedSupplier = response.data && response.data.length ? response.data[0] : null;
//                         if (linkedSupplier) {
//                             $("#sup_asset").html(linkedSupplier.supplier_name);
//                              $("#start_date").html(linkedSupplier.start_date);        //contractnum,enddate,startdate value fetching from form_start
//                             $("#end_date1").html(linkedSupplier.end_date);
//                             $("#contract_nr").html(linkedSupplier.contract_nr);        //contractnum,enddate,startdate value fetching from form_end
//                             //                    IWI Make changes in panel for supplier & asset 05062026 start
//                     $.ajax({
//                         url: "/record/process?action=update_xfield",
//                         type: "POST",
//                         dataType: "json",
//                         data: {
//                             page:'asset_panel',
//                             link_id: master.config.id, 
//                             supplier_name: linkedSupplier.supplier_name,
//                             start_date: linkedSupplier.start_date,           //contractnum,enddate,startdate value fetching from form_start
//                             end_date: linkedSupplier.end_date,
//                             contract_nr: linkedSupplier.contract_nr,         //contractnum,enddate,startdate value fetching from form_end
//                         },
//                         success: function (response) {
//                         console.log(response.data);
                           
//                         }
//                     });
// //                    IWI Make changes in panel for supplier & asset 05062026 end
//                         } else {
//                             $("#sup_asset").html('');
//                             $("#start_date").html('');           //contractnum,enddate,startdate value fetching from form_start
//                             $("#end_date1").html('');
//                             $("#contract_nr").html('');              //contractnum,enddate,startdate value fetching from form_end
//                         }
//                         renderSupplierView();
//                     }
//                 });
//             },
            loadLinkedSupplier = function () {
                $.ajax({
                    url: "/record/process?action=load_linked_supplier",
                    type: "POST",
                    data: {
                        link_id: master.config.id,
                        track: master.config.track,
                        org_id: master.config.perspective
                    },
                    dataType: "json",
                    success: function (response)
                    {
                        linkedSupplier = response.data && response.data.length ? response.data[0] : null;
                        if (linkedSupplier) {
                            $("#con_sup_asset").html(linkedSupplier.supplier_name); 
                            $("#con_start_date").html(linkedSupplier.start_date);        //contractnum,enddate,startdate value fetching from form_start
                            $("#con_end_date").html(linkedSupplier.end_date);
                            $("#con_contract_nr").html(linkedSupplier.contract_nr);  //contractnum,enddate,startdate value fetching from form_end
                            //                    IWI Make changes in panel for supplier & asset 05062026 start
                    $.ajax({
                        url: "/record/process?action=update_xfield",
                        type: "POST",
                        dataType: "json",
                        data: {
                            page:'asset_panel',
                            link_id: master.config.id, 
                            supplier_name: linkedSupplier.supplier_name,
                            start_date: linkedSupplier.start_date,           //contractnum,enddate,startdate value fetching from form_start
                            end_date: linkedSupplier.end_date,
                            contract_nr: linkedSupplier.contract_nr,         //contractnum,enddate,startdate value fetching from form_end
                        },
                        success: function (response) {
                        console.log(response.data);
                        }
                    });
//                    IWI Make changes in panel for supplier & asset 05062026 end
                        } else {
                            $("#con_sup_asset").html('');
                            $("#con_start_date").html('');           //contractnum,enddate,startdate value fetching from form_start
                            $("#con_end_date").html('');
                            $("#con_contract_nr").html('');              //contractnum,enddate,startdate value fetching from form_end
                        }
                        renderSupplierView();
                    }
                });
            },
                        
renderSupplierView = function () {
                if (!linkedSupplier) {
                    $("#asset_supplier_form_container").show();
                    $("#asset_supplier_details_container").hide();
                    return;
                }
                $("#asset_supplier_form_container").hide();
                $("#asset_supplier_details_container").show();
                $("#detail_track").html('<a target="_blank" href="/record/process:'+linkedSupplier.supplier_track+'#panels" >'+linkedSupplier.supplier_track+'</a>');
                $("#detail_supplier_name").text(linkedSupplier.supplier_name);
                $("#detail_contract_name").text(linkedSupplier.contract_name);
                $("#detail_contract_nr").text(linkedSupplier.contract_nr);
                $("#detail_start_date").text(linkedSupplier.start_date);
                $("#detail_end_date").text(linkedSupplier.end_date);
                
//                IWI Asset Control -> Link Supplier Add action buttons for inactive/active and delete 09072026 start
console.log(linkedSupplier.status);
                 if (linkedSupplier.status == 'active') {
                    $("#asset_supplier_status_btn").removeClass("inactive").addClass("active").attr("title", "Deactivate") .html('<i class="fa fa-toggle-on"></i>');
                } else {
                    $("#asset_supplier_status_btn").removeClass("active").addClass("inactive").attr("title", "Activate") .html('<i class="fa fa-toggle-off"></i>');
                }
//                IWI Asset Control -> Link Supplier Add action buttons for inactive/active and delete 09072026 end
            }
            
//            IWI Asset Control -> Link Supplier Add action buttons for inactive/active and delete 09072026 start
                $("#asset_supplier_status_btn").on("click", function () {
                    if (!linkedSupplier)
                        return;
                    var newStatus = linkedSupplier.status == 'active' ? 'closed' : 'active';
                    var actionText = newStatus == 'active' ? "activate" : "inactivate";
                    Swal.fire({
                        title: "Are you sure?",
                        text: "Do you want to " + actionText + " this supplier link?",
                        icon: "question",
                        showCancelButton: true,
                        confirmButtonText: "Yes"
                    }).then(function (result) {
                        if (!result.isConfirmed)
                            return;
                        $.ajax({
                            url: "/record/process?action=update_linked_supplier_status",
                            type: "POST",
                            dataType: "json",
                            data: {
                                link_id: master.config.id, 
                                id: linkedSupplier.id,
                                status: newStatus
                            },
                            success: function (response) {
                                if (response.success) {
                                    Swal.fire({
                                        icon: "success",
                                        title: "Status updated successfully"
                                    });
                                    loadLinkedSupplier();
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: response.message || "Unable to update status"
                                    });
                                }
                            },
                            error: function () {
                                Swal.fire({
                                    icon: "error",
                                    title: "Server Error"
                                });
                            }
                        });
                    });
                });
                
                $("#asset_supplier_delete_btn").on("click", function () {
                    if (!linkedSupplier)
                        return;
                    Swal.fire({
                        title: "Delete Linked Supplier?",
                        text: "This action cannot be undone.",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#dc2626",
                        cancelButtonColor: "#6b7280",
                        confirmButtonText: "Yes, Delete"
                    }).then(function (result) {
                        if (!result.isConfirmed)
                            return;
                        $.ajax({
                            url: "/record/process?action=delete_linked_supplier",
                            type: "POST",
                            dataType: "json",
                            data: {
                                link_id: master.config.id,
                                id: linkedSupplier.id,
                            },
                            success: function (response) {
                                if (response.success) {
                                    linkedSupplier = null;
                                    Swal.fire({
                                        icon: "success",
                                        title: "Deleted Successfully"
                                    });
                                    loadLinkedSupplier();
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: response.message || "Unable to delete."
                                    });
                                }
                            },
                            error: function () {
                                Swal.fire({
                                    icon: "error",
                                    title: "Server Error"
                                });
                            }
                        });
                    });
                });


//            IWI Asset Control -> Link Supplier Add action buttons for inactive/active and delete 09072026 end

//IWI  link Supplier and contracts for Asset Control 21052026 end




            select_Row = function (row)
            {
                var edit_access = docs_manager_priv ||
                        (row.edit_access == 'shared'
                                || (row.edit_access == 'org' && (row.visibility == 'dep' || row.visibility == 'org'))
                                || (row.edit_access == 'dep' && row.dep_id == user_session.cdep_id)
                                || (row.edit_access == 'user' && row.editor_id == user_session.id)
                                );


                // If no row was previously selected, show the available commands
                if (!selected_row) {
                    donrey.cancel_Delay('hide-docs-row-ops');
                    $('#tab_trainee #trainee-row-ops button').jqxButton({disabled: false});
                    $('#tab_trainee #trainee-row-ops').fadeIn();
                }
                selected_row = row;

                // Show or hide the "Delete" button
                if (edit_access)
                    $('#tab_trainee .docs-write-ops').fadeIn();
                else
                    $('#tab_trainee .docs-write-ops').fadeOut();

                // Show or hide the Web-View section (if the document has web-view enabled or not)
                if (row.wv_key)
                    $('#tab_trainee #trainee-open-webview').show();
                else
                    $('#tab_trainee #trainee-open-webview').hide();
            },
            // File visibility selector TODO: Get the texts from Language files
            file_visibility_source = [
                {k: 'private', text: '<span class="access16 visi-private"></span> Private attachment, only visible to the uploader'},
                {k: 'dep', text: '<span class="access16 visi-dep"></span>Visible for the uploader\'s department'},
                {k: 'org', text: '<span class="access16 visi-org"></span>Everyone in the owner organization can see it'},
                {k: 'shared', text: '<span class="access16 visi-shared"></span>Organizations sharing this ' + master.config.type.name},
                {k: 'public', text: '<span class="access16 visi-public"></span>No visibility restrictions'}
            ],
            /**
             * A file row has been selected - Store information in preparation for a double-click ( Configure Window ), or for clicking on one of the toolbar buttons or the download link
             */
            select_File = function (row)
            {
                if (!selected_row) {
                    donrey.cancel_Delay('hide-bar_files-select');
                    if (edit_privilege)
                        $('#tab_trainee #files-row-editops').fadeIn();
                    $('#tab_trainee #files-row-ops button').jqxButton({disabled: false});
                    $('#tab_trainee #files-row-ops').fadeIn();

                }
                selected_row = row;
                // Prepare the download link
                $("#tab_trainee #dl-link").attr('href', "/files?action=download_attachment&ch=" + selected_row.chash + "&qp=" + donrey.qp_conv(selected_row.id));
            },
            select_Clear = function ()
            {
                if ($('#tab_trainee #trainee-row-ops button').length > 0) {
                    $('#tab_trainee #trainee-row-ops button').jqxButton({disabled: true});
                }

                donrey.single_Delay('hide-docs-row-ops', function () {
                    $('#tab_trainee #docs-row-ops').fadeOut();
                }, 500);

                selected_row = null;
            },
            /**
             * Loads selected document "body" into the existing editor. If currently no body, just use the editor with a blank result
             */
            load_Body = function ()
            {
                $.get('/global/note', {
                    action: "Load_Body",
                    id: open_row_id,
                    content_type: 'html'
                }, function (body) {
                    if (body.length > 0)
                        master.rich_editor.content_Write(rich_editor_instance_key, body);
                    else
                        master.rich_editor.content_Write(rich_editor_instance_key, "\n");
                });
            },
            // IWI manage extra section only for data panel selected module  end

            
             
             // create new function by iwi 26042024 | start
             load_listing_by_training = function ()
             {
                // created by iwi 13052024 | start
                $.post("?action=Load_listing_by_training_v2", {
                con_id: master.config.id,
                track: master.config.track,
                }, function (x) {
                
                $("._training-record-docs_table").html(x.data);
                $("#nextExpiry").text(x.nextExpiryHtml); // 08072024 add by iwi
                 sortTable();

               


                }).fail(cargohub.its.http_fail);
                // created by iwi 13052024 | end
             },
              load_listing_by_archievedtraining = function ()       //active/archieved training task 04-12-25 start
             {
                $.post("?action=Load_listing_by_archivedtraining", {
                con_id: master.config.id,
                track: master.config.track,
                }, function (x) {
                
                $("#inactiveTrainingBody").html(x.data);
                $("#nextExpiry").text(x.nextExpiryHtml); 
                 sortTable();

                }).fail(cargohub.its.http_fail);
             },                                             //active/archieved training task 04-12-25 end
             
             
             
             // create new function by iwi 26042024 | end
              sortTable = function() {
                var table, rows, switching, i, x, y, shouldSwitch;
                table = document.getElementById("_training-record-docs");
                if (!table) {
//    console.log("Table with ID '_training-record-docs' not found.");
    return;
  }
                switching = true;
                /*Make a loop that will continue until
                no switching has been done:*/
                while (switching) {
                    //start by saying: no switching is done:
                    switching = false;
                    rows = table.rows;
                    /*Loop through all table rows (except the
                    first, which contains table headers):*/
                    for (i = 1; i < (rows.length - 1); i++) {
                    //start by saying there should be no switching:
                    shouldSwitch = false;
                    /*Get the two elements you want to compare,
                    one from current row and one from the next:*/
                    x = rows[i].getElementsByTagName("TD")[0];
                    y = rows[i + 1].getElementsByTagName("TD")[0];
                    //check if the two rows should switch place:
                    if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                        //if so, mark as a switch and break the loop:
                        shouldSwitch = true;
                        break;
                    }
                    }
                    if (shouldSwitch) {
                    /*If a switch has been marked, make the switch
                    and mark that a switch has been done:*/
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                    }
                }
                },

            // IWI manage extra section only for data panel selected module [Doccumentation] start
            load_listing_by_doc = function ()
            {
                // created by iwi 13052024 | start
                //                    $.post("?action=load_listing_by_doc", {
                //                    link: "issue",
                //                    link_id: master.config.id,
                //                    content_type: "html",
                //                }, function (x) {
                //                    $("._docs_doccumentation_table").html(x.data);
                ////                        $("#train-tlist .datatable .center .gray").text(x.date);
                //
                //                }).fail(cargohub.its.http_fail);  
                                // created by iwi 13052024 | end



                $.get('/record/process:PN6-99K-Z9J4', {
                    action: "load_tab_html",
                    tab: "docs",
                }, function (body) {
                   
                    if (body.length > 0)
                    {   
                        // 02072024 add by iwi | start
                        load_listing_by_training();
                        // 02072024 add by iwi | end
                        $("#document-pannel-viewer-body", $trainee_viewer).html(body);
                    } else
                        $("#document-pannel-viewer-body", $trainee_viewer).html("<span class='gray'>(This document has no content)</span>");
                });

                var panel_docs_Source = new $.jqx.dataAdapter({
                    url: "/global/note", // At the moment using notes backend - That might change in the future !
                    data: {
                        action: "Get_List",
                        link: "issue",
                        link_id: master.config.id,
                        content_type: "html",
                        order: "`nte`.id ASC, `nte`.version DESC"
                    },
                    datatype: "json",
                    datafields: [
                        {name: 'wv_key', type: 'string'},
                        {name: 'wv_secure', type: 'number'},
                        {name: 'wv_publish', type: 'number'},
                        {name: 'wv_publish_for', type: 'string'},
                        {name: 'wv_publish_for_ids', type: 'string'},
                        {name: 'name', type: 'string', map: 'title'}, // Originally called "title" on notes in the database
                        {name: 'description', type: 'string'},
                        {name: 'version', type: 'numberinput'},
                        {name: 'dt', type: 'date'},
                        {name: 'editor_id', type: 'int', map: 'editor>id'},
                        {name: 'editor_name', type: 'string', map: 'editor>name'},
                        {name: 'org_id', type: 'int', map: (cargohub.application.user_Is_Admin() ? 'org>id' : 'org_id')},
                        {name: 'org_name', type: 'string', map: 'org>name'},
                        {name: 'dep_id', type: 'int'},
                        {name: 'visibility', type: 'string'},
                        {name: 'edit_access', type: 'string'},
                        {name: 'published_by', type: 'string'}, //IWI doc seen & sign
                        {name: 'created_by', type: 'string'}, //IWI doc seen & sign
                        {name: 'last_updated', type: 'string'}, //IWI doc seen & sign
                    ],
                    id: "id",
                    // Initial Sorting
                    //                sortcolumn: 'version',
                    //                sortdirection: 'desc'
                });
                var cell_highlight = function (row, column, value, rowData) {
                    var classname = "";

                    if (rowData.visibility == 'shared' || rowData.visibility == 'public')
                        classname += "grid-cell-shared";
                    else if (rowData.visibility == 'private')
                        classname += "grid-cell-private";

                    // Mark notes for the perspective org for the admin user
                    if (cargohub.application.user_Is_Admin() && rowData.org_id == master.config.perspective)
                        classname += " grid-cell-perspective_org";
                    return classname;
                };
                if ($("#tab_docs_doccumentation div.jqx-datatable").length) {
                master.init_DataTable($("#tab_docs_doccumentation div.jqx-datatable"), {
                    exportSettings: {
                        columnsHeader: true,
                        hiddenColumns: false,
                        serverURL: "/?action=export",
                        characterSet: null,
                        collapsedRecords: false,
                        recordsInView: true,
                        fileName: "docs"
                    },
                    // Config
                    source: panel_docs_Source,
                    columns: [
                        {dataField: 'wv_key', text: '<span class="access16 state-web"></span>', width: 25, filterable: false, cellsRenderer: function (row, column, value, rowData) {
                                return '<span class="access16 state-' + (rowData.wv_key ? "on" : "off") + '"></span>';
                            }, cellClassName: cell_highlight},
                        {dataField: 'wv_secure', text: '<span class="access16" style="background-image: url(/img/icons16/globe_lock.png);"></span>', width: 25, filterable: false, cellsRenderer: function (row, column, value, rowData) {
                                return rowData.wv_secure ? '<span class="access16 state-secure"></span>' : "";
                            }, cellClassName: cell_highlight},

                        {dataField: 'name', text: "Document name", cellClassName: cell_highlight},
                        //{dataField: 'dt', text: "Updated on", width: 160, cellsFormat: cargohub.datetime_format, filterable: false, cellClassName: cell_highlight},	
                        {dataField: 'version', text: "Version", width: 100, cellClassName: cell_highlight, filterable: false},
                        {dataField: 'last_updated', text: "Last Updated", width: 160, cellsFormat: cargohub.datetime_format, filterable: false, cellClassName: cell_highlight},
                        {dataField: 'editor_name', text: "Last edited by", width: 160, cellClassName: cell_highlight},
                        {dataField: 'org_name', text: "Organization", width: 160, hidden: !cargohub.application.user_Is_Admin(), filterable: cargohub.application.user_Is_Admin(), cellClassName: cell_highlight},
                        {dataField: 'visibility', text: "<span class='access16' style='background-image: url(/img/icons16/eye.png);'></span>", width: 35, filterable: false, cellClassName: cell_highlight},
                    ]
                }, {
                    // Callbacks (for events happening in the datatable itself) - In most cases it will be either View or Edit
                    "getrows": function () {
                        $('#taskAct').click();  //IWI docs list  export , active ,  closed button create 
                    },
                    "rowselect": select_Row,
                    "clearselect": select_Clear,
                    "rowopen": open_Document
                }, "docs");
            }
                 
                //IWI documentation�s page data panel doc list  active ,  closed button create start
                /* 07062024 comment out by iwi | start */
                /*
                $.post("/global/note?action=Get_DocsCount", {
                    link: "issue",
                    link_id: master.config.id,
                    content_type: "html",
                }, function (x) {
                    if (x.success) {
                        $('#data_panel_doc_act').text(x.active);
                        $('#data_panel_doc_clse').text(x.old);
                    }
                });
                */
                //IWI documentation�s page data panel doc list  active ,  closed button create end
            





            },
            // IWI manage extra section only for data panel selected module [Doccumentation] end


            /**
             * Initializes the Panels object, containing record Xfields - jqxDockng
             * interface, with automatic saving of panel state (open, minimized, closed,
             * position) - Tabs can have an "Edit" function in them, allowing ajax
             * replacing of content with a server-sent form - Closed panels can be
             * reopened from the main menu, as well as shortcut "locate" buttons; The
             * locating function also moves to #panels tab when used
             */
            init_Panels = function ()
            {


                $("#tab_panels #panels-docker").removeClass("loading-pleasewait");
                $("#tab_panels #panels-docker").jqxDocking({
                    theme: cargohub.jqxtheme,
                    mode: "docked",
//                    width: cargohub.fullwidth + 8,// IWI Record Details Page Redesign 10092026
                      width: '100%', // IWI Record Details Page Redesign 10092026
                    orientation: 'horizontal',
                    windowsOffset: 8
                });

                // automatically add the Panels to the Docking
                $("#tab_panels #panels-content > div").each(function () {
                    panel_Operation('init', this.id);
                });
                //create new function by iwi 26042024 | start
                if ($('#tab_trainee_training_record').length) {
                    load_listing_by_training();
                }

                //create new function by iwi 26042024 | end

                //IWI manage extra section only for data panel selected module [Doccumentation] start
                if ($('#tab_docs_doccumentation').length) {
                    load_listing_by_doc();
                }
                //IWI manage extra section only for data panel selected module [Doccumentation] end

                // and arrange the layout to the saved state (if it exists)
                if (self.config.panel_layout) {
                    // Variable fix: Because json_encode creates an empty php array as a
                    // PHP array but in this case it should be an object, detect and fix
                    // the empty entries "floating, panel0, panel1" and replace them
                    // with objects
                    if ($.isArray(self.config.panel_layout.floating))
                        self.config.panel_layout.floating = {};
                    if ($.isArray(self.config.panel_layout.panel0))
                        self.config.panel_layout.panel0 = {};
                    if ($.isArray(self.config.panel_layout.panel1))
                        self.config.panel_layout.panel1 = {};
                } else {
                    // Default config, will be constructed in the next area
                    self.config.panel_layout = {floating: {}, orientation: "horizontal", panel0: {}, panel1: {}};
                }

                // On the PHP side it is not known before loading the template which
                // panels are actually available (as they depend on the record type,
                // access rights, selected organization perspective),
                // Check here if the panel ID's from "panel_layout" actually exist in
                // the DOM before attempting to importLayout, to avoid the dreaded
                // "Invalid JSON String." message.
                var panel_dom_list = new Array, index = -1, place_in_panel = null;
                $("div.jqx-docking-panel > div.jqx-window").each(function () {
                    panel_dom_list.push($(this).attr('id'));
                });

                // Scan the panel_layout object (panel0, panel1) and clean entries which
                // are not available in the template
                for (c in self.config.panel_layout.panel0) {
                    index = $.inArray(c, panel_dom_list);
                    if (index !== -1)
                        panel_dom_list.splice(index, 1);
                    else
                        delete self.config.panel_layout.panel0[c];
                }
                for (c in self.config.panel_layout.panel1) {
                    index = $.inArray(c, panel_dom_list);
                    if (index !== -1)
                        panel_dom_list.splice(index, 1);
                    else
                        delete self.config.panel_layout.panel1[c];
                }

                // Any leftovers (present in dom but not in the saved state) are added
                // to the layout in their default positions
                if (panel_dom_list.length) {
                    for (c = 0; c < panel_dom_list.length; c++) {
                        place_in_panel = $("div.jqx-docking-panel > div#" + panel_dom_list[c]).data('panel');
                        self.config.panel_layout["panel" + place_in_panel][panel_dom_list[c]] = {collapsed: false};
                    }
                }

                $("#tab_panels #panels-docker").jqxDocking('importLayout', JSON.stringify(self.config.panel_layout));

                // The Edit all / Save all panels buttons (initially hidden)
                $("#tab_panels div.multi-commands button").jqxButton({
                    theme: cargohub.jqxtheme
                })
                        .on('click', function () {
                            var multicmd = $(this).data('op');
                            $('#tab_panels span.panel-cmds button[data-cmd="' + multicmd + '-xf"]:visible').each(function () {
                                $(this).trigger('click');
                            });
                        });
                // When having access to edit, FadeIn the Multi-Edit slowly (to attract
                // a bit attention of it's existence)
                if (cargohub.application.user_Test_Privileges('issue_edit_xfields'))
                    $('#tab_panels .multi-commands button[data-op="edit"]').fadeIn('slow');

                // Tooltips on elements inside the tab
                $("#tab_panels .info-tip").each(cargohub.components.ui.add_Tooltip);
                // InfoWindow buttons inside panels
                $('#tab_panels .info-window').each(cargohub.components.ui.button_InfoWindow);
                // Wall-of-text popup on fields with a lot of text
                $('#tab_panels .walloftext-view').each(cargohub.components.ui.walloftext_view);
                panel_ready_for_parent(cargohub.record_process.config.type.base_id, cargohub.record_process.config.id);
                panels_ready = true;


                console.log("load1");
                getLicenseMonitorData();
                console.log("load2");

            },
            // IWI manage extra section only for data panel selected module start

            init_Toolbar = function ()
            {
                $("#tab_panels div.show_extra_manage_sec button").jqxButton({
                    theme: cargohub.jqxtheme
                })
                        /**
                         * Event: Toolbar button is clicked
                         */
                        .on('click', function () {
                            console.log(" 515 test  ",$(this).data('op'));
                            switch ($(this).data('op')) {
                                // Create new blank document command - Initializes the editor in create mode
                                case "trainee-create":
                                    //  master.clear_DataTable($("#tab_trainee div.jqx-datatable")); // Also unselect row if one is selected (which hides unneeded buttons)
                                    // open_row_id = null;
                                    // copy_mode = false;

                                    // if ($trainee_viewer && viewer_open)
                                    //     close_Viewer(true);

                                    // $('#tab_trainee .docs-write-ops').fadeIn(); // Make sure Save is enabled
                                    // init_Editor({
                                    //     title: "Create a Trainee",
                                    //     icon: "add.png",
                                    //     mode: "create"
                                    // });

                                    $('#tab_trainee #trainee-create-op').fadeOut(); // Hide the "Create" button when the editor is active, will be shown again on Save or Close
                                    //$('label[for="error"]').css('visibility', 'visible');
                                    break;

                                    // Create a copy of the existing open document, which must be saved under a new name - by default, the system adds " (copy)" to the existing name
                                    // case "trainee-copy":
                                    //     open_row_id = selected_row.uid;
                                    //     copy_mode = true; // ID is ignored, so a new document is created instead

                                    //     if ($trainee_viewer && viewer_open)
                                    //         close_Viewer(true);

                                    //     $('#tab_trainee .docs-write-ops').fadeIn(); // Make sure Save is enabled
                                    //     init_Editor({
                                    //         title: "Create copy of document: " + selected_row.name,
                                    //         icon: "page_copy.png",
                                    //         mode: "edit",
                                    //         overrides: {
                                    //             "name": selected_row.name + " (copy)",
                                    //             "version": 1 // It's first version of the copy !
                                    //         }
                                    //     });
                                    //     $('#tab_trainee #trainee-create-op').fadeOut();
                                    //     break;

                                    // Open document for editing or viewing (determined inside the open_Document function)
                                    // case "trainee-open":
                                    //     open_row_id = selected_row.uid;
                                    //     open_Document();
                                    //     break;

                                    // Temporary - Test of Viewer
                                    // case "trainee-view":
                                    //     if (editor_open)
                                    //         close_Editor(true);
                                    //     $('#tab_trainee .docs-write-ops').hide(); // No "Save" function in View mode
                                    //     $('#tab_trainee #trainee-open-ops').fadeIn();

                                    //     view_Document({
                                    //         title: "Viewing document: " + selected_row.name
                                    //     });

                                    //     $('#tab_trainee #trainee-create-op').fadeOut();
                                    //     break;

                                    // Opens the document in a new window/tab in Webview mode
                                    // case "trainee-webview-newwin":
                                    //     var win = window.open("/?action=document&qp=" + donrey.qp_conv(selected_row.uid) + "&key=" + selected_row.wv_key, '_blank');
                                    //     win.focus();
                                    //     break;

                                    // Deletes existing document (Manager privilege is required)
                                case "trainee-delete": //
                                    if (!confirm("Are you sure you want to delete this document ?"))
                                        return;
                                    delete_Document();
                                    break;

                                    // Save existing editor content (If no changes is done, a warning is thrown, to avoid unnecessary database operations/logging)
                                case 'trainee-save':
                                    save_Editor();
                                    break;

                                    // Save with Version update
                                    // case 'trainee-upver':
                                    //     save_Editor(true);
                                    //     break;

                                    // Closes the Editor without saving (discards existing content - Warns if new or existing document has been changed, or when copy mode is active)
                                    // Close the Viewer when document is opened in View-only mode
                                    // case 'trainee-close':
                                    //     alert("editor_open"+editor_open);
                                    //     alert("viewer_open"+viewer_open);

                                    //     if (editor_open)
                                    //         close_Editor();
                                    //     if (viewer_open)
                                    //         close_Viewer();
                                    //     break;

                                
                                
                            }
                        });


                //15102024 Audit control add  extra sec start
                $("#tab_audit_activity div.show_extra_manage_sec button").jqxButton({
                    theme: cargohub.jqxtheme
                }) .on('click', function () {

                    console.log(" 621 test  ",$(this).data('op'));
                });
                //15102024 Audit control add  extra sec end



                // Checkbox that adds "classic" notes to the Documents grid (since they are really the same table) - Once a note is saved as a document, it's not visible anymore in notes
                // Only user with Manage access can convert Notes into Docs (as it's equivalent to a Delete+Create operation)
                if (docs_manager_priv) {
                    $('#tab_trainee #trainee-import').jqxCheckBox({
                        theme: cargohub.jqxtheme,
                        height: 18
                    })
                            .show()
                            .on('change', function (e) {
                                if (e.args.checked)
                                    docs_Source._source.data.content_type = 'plain';
                                else
                                    docs_Source._source.data.content_type = 'html';

                                // Reload the documents table when the checkbox is changed
                                master.refresh_DataTable($("#tab_trainee div.jqx-datatable"));
                            })
                            .find("div.jqx-checkbox-default").css('margin-top', "2px");
                }
            },
            // 15102024 Audit control add  extra sec start
            initAuditActivityEditor = function (config)
            {
                
                if(config.mode == 'create_audit_activity'){
                    document.getElementById("auditActivityForm").reset();
                    $("#audit_activity_id").val(0);
                    $("#isCreateTaskId").val(0);
                    $('input[name="audit_status"][value="active"]').prop('checked', true);//23102024 Audit control 
                    open_row_id = null;
                }
                // 31122024 Audit control add field by iwi
                $("#caPa_procedure").jqxDropDownList({
                    displayMember: "label",
                    valueMember: "value",
                    width: "75%", //IWI Quality event record panel section Investigation modification 08122025
                    height: 25,
                    checkboxes: true,
                    selectedIndex: -1,
                    placeHolder: "Select options"
                });


                $audit_activity_editor = $("#tab_audit_activity #document-editor");

                // config.expander.theme = cargohub.jqxtheme;
                $audit_activity_editor.jqxExpander(config.expander);
                
                if (!config.expander)
                config.expander = {
                    disabled: false,
                    expanded: false
                };
                switch (config.mode) {
                    case 'create_audit_activity':

                    break;
                }
                editor_open = config.mode;
                $audit_activity_editor.show().jqxExpander('enable');

                $audit_activity_editor.jqxExpander('expand');
               
                // Add tooltips for relevant elements
                $(".info-tip", $audit_activity_editor).each(cargohub.components.ui.add_Tooltip);

                // Show the Save/Close controls
                $('#audit-activity-open-ops button').jqxButton({disabled: false});
                $('#audit-activity-open-ops').fadeIn();
            },
            initAuditActivityOpen = function (row)
            {
                // Fadeout Create command when Editor or Viewer are active
                $('#tab_audit_activity #audit-activity-create-op').fadeOut();

                if (row)
                    selected_row = row;

                $('#audit_reference_no').val(selected_row.reference_no);    
                $('#audit_description').val(selected_row.dsc);    
                $('#audit_improvement').val(selected_row.improvement);   
                $('#audit_activity_id').val(selected_row.uid);   
                
                if (selected_row.risk_val == 'MI') {
                    $('input[name="audit_risk"][value="MI"]').prop('checked', true);
                } else if (selected_row.risk_val == "MA") {
                    $('input[name="audit_risk"][value="MA"]').prop('checked', true);
                } else {
                    $('input[name="audit_risk"][value="CR"]').prop('checked', true);
                }
                
//                IWI Quality event record panel section Investigation modification 08122025 start
                $('input[name="action_type"]').prop('checked', false);// 12122024 changes audit control
                if (selected_row.action_type == 'Investigation') {
                    $('input[name="action_type"][value="Investigation"]').prop('checked', true);
                } else if (selected_row.action_type == "Corrective") {
                    $('input[name="action_type"][value="Corrective"]').prop('checked', true);
                } else if(selected_row.action_type == "Preventive") { // 12122024 changes audit control
                    $('input[name="action_type"][value="Preventive"]').prop('checked', true);
                }
                
//                IWI Quality event record panel section Investigation modification 08122025 end

                if (selected_row.status == 'active') {
                    $('input[name="audit_status"][value="active"]').prop('checked', true);
                } else if (selected_row.status == "closed") {
                    $('input[name="audit_status"][value="closed"]').prop('checked', true);
                }
                $('#isCreateTask').prop('disabled', false);
                if(selected_row.isCreateTask == 'true') {
                    $('input[name="isCreateTask"][value="1"]').prop('checked', true);
                    $('#isCreateTask').prop('disabled', true); 
                }

                $('#isCreateTaskId').val(selected_row.isCreateTaskId);   
                

                $('#audit_title').val(selected_row.title); 
                $('#capa_type').val(selected_row.type); 
                $('#audit_responsible_person').val(selected_row.person); 
                $('#capa_result').val(selected_row.result); 
                $('#audit_cause_comments').val(selected_row.comments); 
                $('#audit_deadline_date').val(selected_row.deadline_date); 
                $('#audit_closing_date').val(selected_row.closing_date); 

                $('#tab_audit_activity #audit-activity-open-ops, #tab_audit_activity .docs-write-ops').fadeIn();
                initAuditActivityViewerClose(true);
                initAuditActivityEditor({
                    title: "Edit document: " + selected_row.ID,
                    icon: "pencil.png",
                    mode: "edit"
                });

                $(".audit-ctime").show(); //created time added to capa 
                $(".audit-ctime").css({"margin-left": "818px", "margin-top": "-26px"});
                $("#audit_ctime").text(selected_row?.ctime || '—'); //created time added to capa

                $(".audit-person").show(); // user added to capa
                $(".audit-person").css({"margin-left": "820px", "margin-top": "2px"});
                $("#audit-person").text(selected_row?.created_by_name || '—'); // user added to capa

                // 31122024 Audit control add field by iwi start
                $('input[name="caPa_types"]').prop('checked', false);
                $("#caPa_procedure").jqxDropDownList('uncheckAll');


                if(selected_row.caPa_types.length > 0){
                    $('input[name="caPa_types"]').each(function(){
                        if (selected_row.caPa_types.includes($(this).val())){
                            $(this).prop("checked", true);
                        }
                    });
                }
                if(selected_row.caPa_procedure.length > 0){
                    selected_row.caPa_procedure.forEach(function (value) {
                        var item = $("#caPa_procedure").jqxDropDownList('getItemByValue', value);
                        if (item) {
                            $("#caPa_procedure").jqxDropDownList('checkItem', item);
                        }
                    });
                }               
                $('#caPa_oth_procedures').val(selected_row.caPa_oth_procedures); 
                // 31122024 Audit control add field by iwi end
            },
            initAuditActivityViewerClose = function (transition)
            {
                if (!$audit_activity_viewer) // Not yet initialized
                    return;

                if ($audit_activity_viewer.jqxExpander('expanded')) {
                    $audit_activity_viewer.on('collapsed', function () {
                        // When finishing collapse... hide the editor
                        $audit_activity_viewer.hide();
                        // ... and disable the event
                        $audit_activity_viewer.off('collapsed');
                    });
                    $audit_activity_viewer.jqxExpander('collapse');
                } else {
                    // If already collapsed, just hide it
                    $audit_activity_viewer.hide();
                }

                // Also fadeout the Close buttons
                $('#tab_audit_activity #audit-activity-open-ops button').jqxButton({disabled: true});
                $('#tab_audit_activity #audit-activity-open-ops').fadeOut();

                if (!transition) {
                    if (selected_row)
                        $('#tab_audit_activity #audit-activity-row-ops').fadeIn();
                    // Show the "Create" button in case it was hidden by a previous operation
                    $('#tab_audit_activity #audit-activity-create-op').fadeIn();
                }

                // viewer_open = false;

                return true;
            },
            initAuditActivitySelectRow = function (row){
                var edit_access = docs_manager_priv ||
                (row.edit_access == 'shared'
                || (row.edit_access == 'org' && (row.visibility == 'dep' || row.visibility == 'org'))
                || (row.edit_access == 'dep' && row.dep_id == user_session.cdep_id)
                || (row.edit_access == 'user' && row.editor_id == user_session.id)
                );


                // If no row was previously selected, show the available commands
                if (!selected_row) {
                    donrey.cancel_Delay('hide-docs-row-ops');
                    $('#tab_audit_activity #audit-activity-row-ops button').jqxButton({disabled: false});
                    $('#tab_audit_activity #audit-activity-row-ops').fadeIn();
                }
                selected_row = row;

                // Show or hide the "Delete" button
                if (edit_access)
                    $('#tab_audit_activity .docs-write-ops').fadeIn();
                else
                    $('#tab_audit_activity .docs-write-ops').fadeOut();

                // Show or hide the Web-View section (if the document has web-view enabled or not)
                if (row.wv_key)
                    $('#tab_audit_activity #trainee-open-webview').show();
                else
                $('#tab_audit_activity #trainee-open-webview').hide();
            },
            initAuditActivityDelete = function ()
            {
                // console.log("AuditActivityDelete 759 : ",selected_row);
                $.post("/record/process?action=Delete_audit_activity_management", {
                    id: selected_row.uid,
                    link_id: master.config.id//05122024
                }, function (x) {
                    cargohub.components.spinner.On("Success!", x.response, 2500, function () {
                        cargohub.components.spinner.Off();
                    });
                    location.reload(); 

                }).fail(cargohub.its.http_fail);
            },
            initAuditActivitySave = function ()
            {

                var audit_risk = $('input[name="audit_risk"]:checked').val();
                if (audit_risk == '' || audit_risk == undefined) {
                    return cargohub.alert("Please select the risk type !");
                    
                }

                var audit_status = $('input[name="audit_status"]:checked').val();
                if (audit_status == '' || audit_status == undefined) {
                    return cargohub.alert("Please select the status type !");
                    
                }

                var description = '<b>Description : </b>'+'<p>'+$('#audit_description').val()+'</p>'+' <b> Root Cause : </b>'+'<p>'+$('#audit_cause_comments').val()+'</p>'+' <b> Improvement : </b>'+'<p>'+$('#audit_improvement').val()+'</p>';

                var taskFormData = new FormData();
                taskFormData.append("org_id", master.config.master_org_id);
                taskFormData.append("link_type", "record");
                taskFormData.append("link_id", master.config.id);
                taskFormData.append("task[title]", $('#audit_title').val());
                taskFormData.append("task[task_ref]", "");
                taskFormData.append("task[task_type]", "Custom");
                taskFormData.append("task[description]", description);
                taskFormData.append("task[assign_mode]", "self");
                taskFormData.append("task[deadline]", $('#audit_deadline_date').val());
                taskFormData.append("task[reminder]", "");
                taskFormData.append("task[ovd_intv]", 1);
                taskFormData.append("task[ovd_rep_limit]", 999);
                taskFormData.append("task[action_ntf_type]", "email");

               var audit_activity_id = $('#audit_activity_id').val();
               var isCreateTask = $("#isCreateTask").is(":checked");
               var isCreateTaskId = $("#isCreateTaskId").val();

                //31122024 Audit control add field by iwi | start
                var selectedcaPaTypesVal = [];
                $('input[name="caPa_types"]:checked').each(function () {
                    selectedcaPaTypesVal.push($(this).val());
                });
                //31122024 Audit control add field by iwi | end
                
//                IWI Quality event record panel section Investigation modification 08122025 start
                var action_type = $('input[name="action_type"]:checked').val();
                if (action_type == '' || action_type == undefined) {
                    return cargohub.alert("Please select the action type !");
                    
                }
//                IWI Quality event record panel section Investigation modification 08122025 end
               
                var submit_data = {
                    'link_id': master.config.id,
                    'issue_type_id': 34,
                    'track_id': master.config.track,
                    'org_id': master.config.master_org_id,
                    'audit_reference_no': $('#audit_reference_no').val(),
                    'audit_description': $('#audit_description').val(),
                    'audit_improvement': $('#audit_improvement').val(),
                    'audit_risk': audit_risk,
                    'audit_status': audit_status,
                    'audit_title': $('#audit_title').val(),
                    'capa_type': $('#capa_type').val(),
                    'audit_responsible_person': $('#audit_responsible_person').val(),
                    'audit_cause_comments': $('#audit_cause_comments').val(),
                    'audit_deadline_date': $('#audit_deadline_date').val(),
                    'audit_closing_date': $('#audit_closing_date').val(),
                    'capa_result': $('#capa_result').val(),
                    'isCreateTask': $("#isCreateTask").is(":checked"),
                    'audit_activity_id': audit_activity_id,

                    // 31122024 Audit control add field by iwi | start
                    'caPa_types': selectedcaPaTypesVal,
                    'caPa_oth_procedures': $('#caPa_oth_procedures').val(),
                    'caPa_procedure': $('#caPa_procedure').val(),
                    'action_type': action_type,//IWI Quality event record panel section Investigation modification 08122025 end
                };
                if(isCreateTask == 1 && audit_activity_id == 0 && isCreateTaskId ==0){
                    $.when(createTask(taskFormData)).done(function (data) {
                        taskId = data.iid;
                        saveTask(submit_data,taskId).done(function (response) {
                            location.reload(); //19112024 by iwi
                        }).fail(function (error) {
                            console.error("Error in the second request:", error);
                        });
                    }).fail(function (error) {
                        console.error("Error in the first request:", error);
                    });
                }else if(isCreateTask == 1 && audit_activity_id > 0 && isCreateTaskId ==0){
                    $.when(createTask(taskFormData)).done(function (data) {
                        taskId = data.iid;
                        saveTask(submit_data,taskId).done(function (response) {
                             location.reload(); //19112024 by iwi
                        }).fail(function (error) {
                            console.error("Error in the second request:", error);
                        });
                    }).fail(function (error) {
                        console.error("Error in the first request:", error);
                    });
                }else{
                    saveTask(submit_data).done(function (response) {
                         location.reload(); //19112024 by iwi
                    }).fail(function (error) {
                        console.error("Error in the second request:", error);
                    });
                }
            },
            //  15102024 Audit control add  extra sec end







            // 27112024 Customer Inquiry -> CAPA sec added by iwi start
            initCapaSecByCustomerInqEditor = function (config)
            {
                
                if(config.mode == 'create_audit_activity'){
                    document.getElementById("capaSecByCustomerInquiryForm").reset();
                    $("#audit_activity_id").val(0);
                    $("#isCreateTaskId").val(0);
                    $('input[name="audit_status"][value="active"]').prop('checked', true);//23102024 Audit control 
                    open_row_id = null;
                }

                // 28112024 added by iwi start
                $("#caPa_procedure").jqxDropDownList({
                    displayMember: "label",
                    valueMember: "value",
                    width: "75%", //IWI Quality event record panel section Investigation modification 08122025
                    height: 25,
                    checkboxes: true,
                    selectedIndex: -1,
                    placeHolder: "Select options"
                });
                // 28112024 added by iwi end

                $capa_sec_by_customer_inquiry_editor = $("#capa_sec_by_customer_inquiry #document-editor");

                // config.expander.theme = cargohub.jqxtheme;
                $capa_sec_by_customer_inquiry_editor.jqxExpander(config.expander);
                
                if (!config.expander)
                config.expander = {
                    disabled: false,
                    expanded: false
                };
                switch (config.mode) {
                    case 'create_audit_activity':

                    break;
                }
                editor_open = config.mode;
                $capa_sec_by_customer_inquiry_editor.show().jqxExpander('enable');

                $capa_sec_by_customer_inquiry_editor.jqxExpander('expand');
            
                // Add tooltips for relevant elements
                $(".info-tip", $capa_sec_by_customer_inquiry_editor).each(cargohub.components.ui.add_Tooltip);

                // Show the Save/Close controls
                $('#capa-by-customer-inq-open-ops button').jqxButton({disabled: false});
                $('#capa-by-customer-inq-open-ops').fadeIn();
            },
            initCapaSecByCustomerInqOpen = function (row)
            {
                // Fadeout Create command when Editor or Viewer are active
                $('#capa_sec_by_customer_inquiry #capa-by-customer-inq-create-op').fadeOut();

                if (row)
                    selected_row = row;

                $('#audit_reference_no').val(selected_row.reference_no);    
                $('#audit_description').val(selected_row.dsc);    
                $('#audit_improvement').val(selected_row.improvement);   
                $('#audit_activity_id').val(selected_row.uid);   
                
                if (selected_row.risk_val == 'MI') {
                    $('input[name="audit_risk"][value="MI"]').prop('checked', true);
                } else if (selected_row.risk_val == "MA") {
                    $('input[name="audit_risk"][value="MA"]').prop('checked', true);
                } else {
                    $('input[name="audit_risk"][value="CR"]').prop('checked', true);
                }


//                IWI Quality event record panel section Investigation modification 08122025 start
                $('input[name="action_type"]').prop('checked', false);// 12122024 changes audit control
                if (selected_row.action_type == 'Investigation') {
                    $('input[name="action_type"][value="Investigation"]').prop('checked', true);
                } else if (selected_row.action_type == "Corrective") {
                    $('input[name="action_type"][value="Corrective"]').prop('checked', true);
                } else if(selected_row.action_type == "Preventive") { // 12122024 changes audit control
                    $('input[name="action_type"][value="Preventive"]').prop('checked', true);
                }
                
//                IWI Quality event record panel section Investigation modification 08122025 end
                if (selected_row.status == 'active') {
                    $('input[name="audit_status"][value="active"]').prop('checked', true);
                } else if (selected_row.status == "closed") {
                    $('input[name="audit_status"][value="closed"]').prop('checked', true);
                }
                $('#isCreateTask').prop('disabled', false);
                if(selected_row.isCreateTask == 'true') {
                    $('input[name="isCreateTask"][value="1"]').prop('checked', true);
                    $('#isCreateTask').prop('disabled', true); 
                }

                $('#isCreateTaskId').val(selected_row.isCreateTaskId);   
                

                $('#audit_title').val(selected_row.title); 
                $('#capa_type').val(selected_row.type); 
                $('#audit_responsible_person').val(selected_row.person); 
                $('#capa_result').val(selected_row.result); 
                $('#audit_cause_comments').val(selected_row.comments); 
                $('#audit_deadline_date').val(selected_row.deadline_date); 
                $('#audit_closing_date').val(selected_row.closing_date); 

                $('#capa_sec_by_customer_inquiry #capa-by-customer-inq-open-ops, #capa_sec_by_customer_inquiry .docs-write-ops').fadeIn();
                initCapaSecByCustomerInqViewerClose(true);
                initCapaSecByCustomerInqEditor({
                    title: "Edit document: " + selected_row.ID,
                    icon: "pencil.png",
                    mode: "edit"
                });

                 $(".audit-ctime").show(); //created time added to capa
                $(".audit-ctime").css({"margin-left": "818px", "margin-top": "-26px"});
               $("#audit_ctime").text(selected_row?.ctime || '—'); //created time added to capa

                $(".audit-person").show(); // user added to capa
                $(".audit-person").css({"margin-left": "820px", "margin-top": "2px"});
                $("#audit-person").text(selected_row?.created_by_name || '—'); // user added to capa
                // 28112024 added by iwi start
                $('input[name="caPa_types"]').prop('checked', false);
                $("#caPa_procedure").jqxDropDownList('uncheckAll');


                if(selected_row.caPa_types.length > 0){
                    $('input[name="caPa_types"]').each(function(){
                        if (selected_row.caPa_types.includes($(this).val())){
                            $(this).prop("checked", true);
                        }
                    });
                }
                if(selected_row.caPa_procedure.length > 0){
                    selected_row.caPa_procedure.forEach(function (value) {
                        var item = $("#caPa_procedure").jqxDropDownList('getItemByValue', value);
                        if (item) {
                            $("#caPa_procedure").jqxDropDownList('checkItem', item);
                        }
                    });
                }               
                $('#caPa_oth_procedures').val(selected_row.caPa_oth_procedures); 
                // 28112024 added by iwi end
            },
            initCapaSecByCustomerInqViewerClose = function (transition)
            {
                if (!$capa_sec_by_customer_inquiry_viewer) // Not yet initialized
                    return;

                if ($capa_sec_by_customer_inquiry_viewer.jqxExpander('expanded')) {
                    $capa_sec_by_customer_inquiry_viewer.on('collapsed', function () {
                        // When finishing collapse... hide the editor
                        $capa_sec_by_customer_inquiry_viewer.hide();
                        // ... and disable the event
                        $capa_sec_by_customer_inquiry_viewer.off('collapsed');
                    });
                    $capa_sec_by_customer_inquiry_viewer.jqxExpander('collapse');
                } else {
                    // If already collapsed, just hide it
                    $capa_sec_by_customer_inquiry_viewer.hide();
                }

                // Also fadeout the Close buttons
                $('#capa_sec_by_customer_inquiry #capa-by-customer-inq-open-ops button').jqxButton({disabled: true});
                $('#capa_sec_by_customer_inquiry #capa-by-customer-inq-open-ops').fadeOut();

                if (!transition) {
                    if (selected_row)
                        $('#capa_sec_by_customer_inquiry #capa-by-customer-inq-row-ops').fadeIn();
                    // Show the "Create" button in case it was hidden by a previous operation
                    $('#capa_sec_by_customer_inquiry #capa-by-customer-inq-create-op').fadeIn();
                }

                // viewer_open = false;

                return true;
            },
            initCapaSecByCustomerInqSelectRow = function (row){
                var edit_access = docs_manager_priv ||
                (row.edit_access == 'shared'
                || (row.edit_access == 'org' && (row.visibility == 'dep' || row.visibility == 'org'))
                || (row.edit_access == 'dep' && row.dep_id == user_session.cdep_id)
                || (row.edit_access == 'user' && row.editor_id == user_session.id)
                );


                // If no row was previously selected, show the available commands
                if (!selected_row) {
                    donrey.cancel_Delay('hide-docs-row-ops');
                    $('#capa_sec_by_customer_inquiry #capa-by-customer-inq-row-ops button').jqxButton({disabled: false});
                    $('#capa_sec_by_customer_inquiry #capa-by-customer-inq-row-ops').fadeIn();
                }
                selected_row = row;

                // Show or hide the "Delete" button
                if (edit_access)
                    $('#capa_sec_by_customer_inquiry .docs-write-ops').fadeIn();
                else
                    $('#capa_sec_by_customer_inquiry .docs-write-ops').fadeOut();

                // Show or hide the Web-View section (if the document has web-view enabled or not)
                if (row.wv_key)
                    $('#capa_sec_by_customer_inquiry #trainee-open-webview').show();
                else
                $('#capa_sec_by_customer_inquiry #trainee-open-webview').hide();
            },
            initCapaSecByCustomerInqDelete = function ()
            {
                // console.log("AuditActivityDelete 759 : ",selected_row);
                $.post("/record/process?action=Delete_audit_activity_management", {
                    id: selected_row.uid,
                    link_id: master.config.id
                }, function (x) {
                    cargohub.components.spinner.On("Success!", x.response, 2500, function () {
                        cargohub.components.spinner.Off();
                    });
                    location.reload(); 

                }).fail(cargohub.its.http_fail);
            },
            initCapaSecByCustomerInqSave = function ()
            {

                var audit_risk = $('input[name="audit_risk"]:checked').val();
                if (audit_risk == '' || audit_risk == undefined) {
                    return cargohub.alert("Please select the risk type !");
                    
                }

                var audit_status = $('input[name="audit_status"]:checked').val();
                if (audit_status == '' || audit_status == undefined) {
                    return cargohub.alert("Please select the status type !");
                }

                
                var description = '<b>Description : </b>'+'<p>'+$('#audit_description').val()+'</p>'+' <b> Root Cause : </b>'+'<p>'+$('#audit_cause_comments').val()+'</p>'+' <b> Improvement : </b>'+'<p>'+$('#audit_improvement').val()+'</p>';

                var taskFormData = new FormData();
                taskFormData.append("org_id", master.config.master_org_id);
                taskFormData.append("link_type", "record");
                taskFormData.append("link_id", master.config.id);
                taskFormData.append("task[title]", $('#audit_title').val());
                taskFormData.append("task[task_ref]", "");
                taskFormData.append("task[task_type]", "Custom");
                taskFormData.append("task[description]", description);
                taskFormData.append("task[assign_mode]", "self");
                taskFormData.append("task[deadline]", $('#audit_deadline_date').val());
                taskFormData.append("task[reminder]", "");
                taskFormData.append("task[ovd_intv]", 1);
                taskFormData.append("task[ovd_rep_limit]", 999);
                taskFormData.append("task[action_ntf_type]", "email");

            var audit_activity_id = $('#audit_activity_id').val();
            var isCreateTask = $("#isCreateTask").is(":checked");
            var isCreateTaskId = $("#isCreateTaskId").val();

            
                //28112024 added by iwi | start
                var selectedcaPaTypesVal = [];
                $('input[name="caPa_types"]:checked').each(function () {
                    selectedcaPaTypesVal.push($(this).val());
                });
                //28112024 added by iwi | end
                
//                IWI Quality event record panel section Investigation modification 08122025 start
                var action_type = $('input[name="action_type"]:checked').val();
                if (action_type == '' || action_type == undefined) {
                    return cargohub.alert("Please select the action type !");
                    
                }
//                IWI Quality event record panel section Investigation modification 08122025 end


                var submit_data = {
                    'link_id': master.config.id,
                    'track_id': master.config.track,
                    'org_id': master.config.master_org_id,
                    'issue_type_id': 7,
                    'audit_reference_no': $('#audit_reference_no').val(),
                    'audit_description': $('#audit_description').val(),
                    'audit_improvement': $('#audit_improvement').val(),
                    'audit_risk': audit_risk,
                    'audit_status': audit_status,
                    'audit_title': $('#audit_title').val(),
                    'capa_type': $('#capa_type').val(),
                    'audit_responsible_person': $('#audit_responsible_person').val(),
                    'audit_cause_comments': $('#audit_cause_comments').val(),
                    'audit_deadline_date': $('#audit_deadline_date').val(),
                    'audit_closing_date': $('#audit_closing_date').val(),
                    'capa_result': $('#capa_result').val(),
                    'isCreateTask': $("#isCreateTask").is(":checked"),
                    'audit_activity_id': audit_activity_id,
                    // 28112024 added by iwi | start
                    'caPa_types': selectedcaPaTypesVal,
                    'caPa_oth_procedures': $('#caPa_oth_procedures').val(),
                    'caPa_procedure': $('#caPa_procedure').val(),
                    'action_type': action_type, //IWI Quality event record panel section Investigation modification 08122025
                };

                if(isCreateTask == 1 && audit_activity_id == 0 && isCreateTaskId ==0){
                    $.when(createTask(taskFormData)).done(function (data) {
                        taskId = data.iid;
                        saveTask(submit_data,taskId).done(function (response) {
                            location.reload();
                        }).fail(function (error) {
                            console.error("Error in the second request:", error);
                        });
                    }).fail(function (error) {
                        console.error("Error in the first request:", error);
                    });
                }else if(isCreateTask == 1 && audit_activity_id > 0 && isCreateTaskId ==0){
                    $.when(createTask(taskFormData)).done(function (data) {
                        taskId = data.iid;
                        saveTask(submit_data,taskId).done(function (response) {
                            location.reload();
                        }).fail(function (error) {
                            console.error("Error in the second request:", error);
                        });
                    }).fail(function (error) {
                        console.error("Error in the first request:", error);
                    });
                }else{
                    saveTask(submit_data).done(function (response) {
                        location.reload();
                    }).fail(function (error) {
                        console.error("Error in the second request:", error);
                    });
                }


            
            },
            //  27112024 Customer Inquiry -> CAPA sec added by iwi end





            
            // 28112024 Customer Inquiry -> CAPA sec added by iwi start
            initCapaSecByQualityEventEditor = function (config)
            {
                
                if(config.mode == 'create_audit_activity'){
                    document.getElementById("capaSecByQualityEventForm").reset();
                    $("#audit_activity_id").val(0);
                    $("#isCreateTaskId").val(0);
                    $('input[name="audit_status"][value="active"]').prop('checked', true);//23102024 Audit control 
                    open_row_id = null;
                }

                $("#caPa_procedure").jqxDropDownList({
                    displayMember: "label",
                    valueMember: "value",
                     width: "75%", //IWI Quality event record panel section Investigation modification 08122025
                    height: 25,
                    checkboxes: true,
                    selectedIndex: -1,
                    placeHolder: "Select options"
                });

                $capa_sec_by_quality_event_editor = $("#capa_sec_by_quality_event #document-editor");

                // config.expander.theme = cargohub.jqxtheme;
                $capa_sec_by_quality_event_editor.jqxExpander(config.expander);
                
                if (!config.expander)
                config.expander = {
                    disabled: false,
                    expanded: false
                };
                switch (config.mode) {
                    case 'create_audit_activity':

                    break;
                }
                editor_open = config.mode;
                $capa_sec_by_quality_event_editor.show().jqxExpander('enable');

                $capa_sec_by_quality_event_editor.jqxExpander('expand');
            
                // Add tooltips for relevant elements
                $(".info-tip", $capa_sec_by_quality_event_editor).each(cargohub.components.ui.add_Tooltip);

                // Show the Save/Close controls
                $('#capa-by-quality-event-open-ops button').jqxButton({disabled: false});
                $('#capa-by-quality-event-open-ops').fadeIn();
            },
            initCapaSecByQualityEventOpen = function (row)
            {
                // Fadeout Create command when Editor or Viewer are active
                $('#capa_sec_by_quality_event #capa-by-quality-event-create-op').fadeOut();

                if (row)
                    selected_row = row;

                $('#audit_reference_no').val(selected_row.reference_no);    
                $('#audit_description').val(selected_row.dsc);    
                $('#audit_improvement').val(selected_row.improvement);   
                $('#audit_activity_id').val(selected_row.uid);   
                
                $('input[name="audit_risk"]').prop('checked', false);// 12122024 changes audit control
                if (selected_row.risk_val == 'MI') {
                    $('input[name="audit_risk"][value="MI"]').prop('checked', true);
                } else if (selected_row.risk_val == "MA") {
                    $('input[name="audit_risk"][value="MA"]').prop('checked', true);
                } else if(selected_row.risk_val == "CR") { // 12122024 changes audit control
                    $('input[name="audit_risk"][value="CR"]').prop('checked', true);
                }
                
                
//                IWI Quality event record panel section Investigation modification 08122025 start
                $('input[name="action_type"]').prop('checked', false);// 12122024 changes audit control
                if (selected_row.action_type == 'Investigation') {
                    $('input[name="action_type"][value="Investigation"]').prop('checked', true);
                } else if (selected_row.action_type == "Corrective") {
                    $('input[name="action_type"][value="Corrective"]').prop('checked', true);
                } else if(selected_row.action_type == "Preventive") { // 12122024 changes audit control
                    $('input[name="action_type"][value="Preventive"]').prop('checked', true);
                }
                
//                IWI Quality event record panel section Investigation modification 08122025 end

                if (selected_row.status == 'active') {
                    $('input[name="audit_status"][value="active"]').prop('checked', true);
                } else if (selected_row.status == "closed") {
                    $('input[name="audit_status"][value="closed"]').prop('checked', true);
                }
                $('#isCreateTask').prop('disabled', false);
                if(selected_row.isCreateTask == 'true') {
                    $('input[name="isCreateTask"][value="1"]').prop('checked', true);
                    $('#isCreateTask').prop('disabled', true); 
                }

                $('#isCreateTaskId').val(selected_row.isCreateTaskId);   
                

                $('#audit_title').val(selected_row.title); 
                $('#capa_type').val(selected_row.type); 
                $('#audit_responsible_person').val(selected_row.person); 
                $('#capa_result').val(selected_row.result); 
                $('#audit_cause_comments').val(selected_row.comments); 
                $('#audit_deadline_date').val(selected_row.deadline_date); 
                $('#audit_closing_date').val(selected_row.closing_date); 

                $('#capa_sec_by_quality_event #capa-by-quality-event-open-ops, #capa_sec_by_quality_event .docs-write-ops').fadeIn();
                initCapaSecByQualityEventViewerClose(true);
                initCapaSecByQualityEventEditor({
                    title: "Edit document: " + selected_row.ID,
                    icon: "pencil.png",
                    mode: "edit"
                });

                // 23122024 Quality Event get Event type value by iwi start
                // $('input[name="caPa_types"]').prop('checked', false);
                // if(selected_row.caPa_types.length > 0){
                //     $('input[name="caPa_types"]').each(function(){
                //         if (selected_row.caPa_types.includes($(this).val())){
                //             $(this).prop("checked", true);
                //         }
                //     });
                // }
                 $(".audit-ctime").show(); //created time added to capa
                $(".audit-ctime").css({"margin-left": "818px", "margin-top": "-26px"});
               $("#audit_ctime").text(selected_row?.ctime || '—'); //created time added to capa

                $(".audit-person").show(); // user added to capa
                $(".audit-person").css({"margin-left": "820px", "margin-top": "2px"});
                $("#audit-person").text(selected_row?.created_by_name || '—'); // user added to capa
               
                // 23122024 Quality Event get Event type value by iwi end
                $("#caPa_procedure").jqxDropDownList('uncheckAll');
                if(selected_row.caPa_procedure.length > 0){
                    selected_row.caPa_procedure.forEach(function (value) {
                        var item = $("#caPa_procedure").jqxDropDownList('getItemByValue', value);
                        if (item) {
                            $("#caPa_procedure").jqxDropDownList('checkItem', item);
                        }
                    });
                } 
                
                $('#caPa_oth_procedures').val(selected_row.caPa_oth_procedures);    
            },
            initCapaSecByQualityEventViewerClose = function (transition)
            {
                if (!$capa_sec_by_quality_event_viewer) // Not yet initialized
                    return;

                if ($capa_sec_by_quality_event_viewer.jqxExpander('expanded')) {
                    $capa_sec_by_quality_event_viewer.on('collapsed', function () {
                        // When finishing collapse... hide the editor
                        $capa_sec_by_quality_event_viewer.hide();
                        // ... and disable the event
                        $capa_sec_by_quality_event_viewer.off('collapsed');
                    });
                    $capa_sec_by_quality_event_viewer.jqxExpander('collapse');
                } else {
                    // If already collapsed, just hide it
                    $capa_sec_by_quality_event_viewer.hide();
                }

                // Also fadeout the Close buttons
                $('#capa_sec_by_quality_event #capa-by-quality-event-open-ops button').jqxButton({disabled: true});
                $('#capa_sec_by_quality_event #capa-by-quality-event-open-ops').fadeOut();

                if (!transition) {
                    if (selected_row)
                        $('#capa_sec_by_quality_event #capa-by-quality-event-row-ops').fadeIn();
                    // Show the "Create" button in case it was hidden by a previous operation
                    $('#capa_sec_by_quality_event #capa-by-quality-event-create-op').fadeIn();
                }

                // viewer_open = false;

                return true;
            },
            initCapaSecByQualityEventSelectRow = function (row){
                var edit_access = docs_manager_priv ||
                (row.edit_access == 'shared'
                || (row.edit_access == 'org' && (row.visibility == 'dep' || row.visibility == 'org'))
                || (row.edit_access == 'dep' && row.dep_id == user_session.cdep_id)
                || (row.edit_access == 'user' && row.editor_id == user_session.id)
                );


                // If no row was previously selected, show the available commands
                if (!selected_row) {
                    donrey.cancel_Delay('hide-docs-row-ops');
                    $('#capa_sec_by_quality_event #capa-by-quality-event-row-ops button').jqxButton({disabled: false});
                    $('#capa_sec_by_quality_event #capa-by-quality-event-row-ops').fadeIn();
                }
                selected_row = row;

                // Show or hide the "Delete" button
                if (edit_access)
                    $('#capa_sec_by_quality_event .docs-write-ops').fadeIn();
                else
                    $('#capa_sec_by_quality_event .docs-write-ops').fadeOut();

                // Show or hide the Web-View section (if the document has web-view enabled or not)
                if (row.wv_key)
                    $('#capa_sec_by_quality_event #trainee-open-webview').show();
                else
                $('#capa_sec_by_quality_event #trainee-open-webview').hide();
            },
            initCapaSecByQualityEventDelete = function ()
            {
                $.post("/record/process?action=Delete_audit_activity_management", {
                    id: selected_row.uid,
                    link_id: master.config.id
                }, function (x) {
                    cargohub.components.spinner.On("Success!", x.response, 2500, function () {
                        cargohub.components.spinner.Off();
                    });
                    location.reload(); 

                }).fail(cargohub.its.http_fail);
            },
            initCapaSecByQualityEventSave = function ()
            {

                var audit_risk = $('input[name="audit_risk"]:checked').val();
                if (audit_risk == '' || audit_risk == undefined) {
                    return cargohub.alert("Please select the risk type !");
                    
                }

                var audit_status = $('input[name="audit_status"]:checked').val();
                if (audit_status == '' || audit_status == undefined) {
                    return cargohub.alert("Please select the status type !");
                }

                
                var description = '<b>Description : </b>'+'<p>'+$('#audit_description').val()+'</p>'+' <b> Root Cause : </b>'+'<p>'+$('#audit_cause_comments').val()+'</p>'+' <b> Improvement : </b>'+'<p>'+$('#audit_improvement').val()+'</p>';

                var taskFormData = new FormData();
                taskFormData.append("org_id", master.config.master_org_id);
                taskFormData.append("link_type", "record");
                taskFormData.append("link_id", master.config.id);
                taskFormData.append("task[title]", $('#audit_title').val());
                taskFormData.append("task[task_ref]", "");
                taskFormData.append("task[task_type]", "Custom");
                taskFormData.append("task[description]", description);
                taskFormData.append("task[assign_mode]", "self");
                taskFormData.append("task[deadline]", $('#audit_deadline_date').val());
                taskFormData.append("task[reminder]", "");
                taskFormData.append("task[ovd_intv]", 1);
                taskFormData.append("task[ovd_rep_limit]", 999);
                taskFormData.append("task[action_ntf_type]", "email");

                var audit_activity_id = $('#audit_activity_id').val();
                var isCreateTask = $("#isCreateTask").is(":checked");
                var isCreateTaskId = $("#isCreateTaskId").val();

                // 23122024 Quality Event get Event type value by iwi
                // var selectedcaPaTypesVal = [];
                // $('input[name="caPa_types"]:checked').each(function () {
                //     selectedcaPaTypesVal.push($(this).val());
                // });
                // 23122024 Quality Event get Event type value by iwi end


//                IWI Quality event record panel section Investigation modification 08122025 start
                var action_type = $('input[name="action_type"]:checked').val();
                if (action_type == '' || action_type == undefined) {
                    return cargohub.alert("Please select the action type !");
                    
                }
//                IWI Quality event record panel section Investigation modification 08122025 end
                var submit_data = {
                    'link_id': master.config.id,
                    'track_id': master.config.track,
                    'org_id': master.config.master_org_id,
                    'issue_type_id': 31,
                    'audit_reference_no': $('#audit_reference_no').val(),
                    'audit_description': $('#audit_description').val(),
                    'audit_improvement': $('#audit_improvement').val(),
                    'audit_risk': audit_risk,
                    'audit_status': audit_status,
                    'audit_title': $('#audit_title').val(),
                    'capa_type': $('#capa_type').val(),
                    'audit_responsible_person': $('#audit_responsible_person').val(),
                    'audit_cause_comments': $('#audit_cause_comments').val(),
                    'audit_deadline_date': $('#audit_deadline_date').val(),
                    'audit_closing_date': $('#audit_closing_date').val(),
                    'capa_result': $('#capa_result').val(),
                    'isCreateTask': $("#isCreateTask").is(":checked"),
                    'audit_activity_id': audit_activity_id,
                    // 'caPa_types': selectedcaPaTypesVal,  // 23122024 Quality Event get Event type value by iwi
                    'caPa_oth_procedures': $('#caPa_oth_procedures').val(),
                    'caPa_procedure': $('#caPa_procedure').val(),
                    'action_type': action_type, //IWI Quality event record panel section Investigation modification 08122025 
                };

                if(isCreateTask == 1 && audit_activity_id == 0 && isCreateTaskId ==0){
                    $.when(createTask(taskFormData)).done(function (data) {
                        taskId = data.iid;
                        saveTask(submit_data,taskId).done(function (response) {
                            location.reload();
                        }).fail(function (error) {
                            console.error("Error in the second request:", error);
                        });
                    }).fail(function (error) {
                        console.error("Error in the first request:", error);
                    });
                }else if(isCreateTask == 1 && audit_activity_id > 0 && isCreateTaskId ==0){
                    $.when(createTask(taskFormData)).done(function (data) {
                        taskId = data.iid;
                        saveTask(submit_data,taskId).done(function (response) {
                            location.reload();
                        }).fail(function (error) {
                            console.error("Error in the second request:", error);
                        });
                    }).fail(function (error) {
                        console.error("Error in the first request:", error);
                    });
                }else{
                    saveTask(submit_data).done(function (response) {
                        location.reload(); //23122024 comment
                    }).fail(function (error) {
                        console.error("Error in the second request:", error);
                    });
                }
            },
            // 28112024 Customer Inquiry -> CAPA sec added by iwi end















            init_Editor = function (config)
            {
                var row_data = $.extend({}, selected_row);
                console.log(" 650 test ",config);
                if ($trainee_editor) {
                    // If already initialized just change jqxExpander according to config
                    if (config && config.expander)
                        $trainee_editor.jqxExpander(config.expander);
                } else {
                    $trainee_editor = $("#tab_trainee #document-editor");
                    
                    if(config.title == 'Create a Activity'){
                        $trainee_editor = $("#tab_audit_activity #document-editor");
                        console.log(" 660 ",$trainee_editor);
                    }
                   
                    // Otherwise, start full initialization
                    
                    if (!config.expander)
                    config.expander = {
                        disabled: false,
                        expanded: false
                    };
                    config.expander.theme = cargohub.jqxtheme;
                    $trainee_editor.jqxExpander(config.expander);

                    // Since the HTML inside the expander is permanent, initialize widgets:

                    // Visibility selector
                    //                    $('#trainee-visibility', $trainee_editor).jqxDropDownList({
                    //                        theme: cargohub.jqxtheme,
                    //                        source: doc_Visibility_Source,
                    //                        displayMember: "text", valueMember: "k",
                    //                        width: 400, height: 24, autoDropDownHeight: true,
                    //                        animationType: "fade"
                    //                    });

                    // Webview toggle
                    //                    $("#trainee-webview-state", $trainee_editor).jqxSwitchButton({
                    //                        theme: cargohub.jqxtheme,
                    //                        height: 25, width: 75
                    //                    })
                    //                            .on('change', function (event) {
                    //                                // Check ALL if "event.args.check" is true, and revert to the currently selected deps ( or none selected? ) if false
                    //                                if (event.args.check)
                    //                                    $(".trainee-webview-info", $trainee_editor).show();
                    //                                else
                    //                                    $(".trainee-webview-info", $trainee_editor).hide();
                    //                            });

                    // Webview secure mode toggle
                    //                    $("#trainee-webview-secure", $trainee_editor).jqxSwitchButton({
                    //                        theme: cargohub.jqxtheme,
                    //                        height: 25, width: 75
                    //                    });

                    // Publich secure mode toggle
                    //			$("#trainee-publish-secure", $trainee_editor).jqxSwitchButton({
                    //				theme: cargohub.jqxtheme,
                    //				height: 25, width: 75
                    //			});

                    // Editing access selector
                    //                    $('#trainee-edit_access', $trainee_editor).jqxDropDownList({
                    //                        theme: cargohub.jqxtheme,
                    //                        source: doc_Edit_access_Source,
                    //                        displayMember: "text", valueMember: "k",
                    //                        width: 400, height: 24, autoDropDownHeight: true,
                    //                        animationType: "fade"
                    //                    });

                    // Template selector (only in create mode)
                    //                    $("#trainee-template", $trainee_editor).jqxDropDownList({
                    //                        theme: cargohub.jqxtheme,
                    //                        source: templates_Source,
                    //                        displayMember: "description", valueMember: "id",
                    //                        placeHolder: "(Loading templates)",
                    //                        width: 853, dropDownWidth: 853,
                    //                        animationType: "fade", enableBrowserBoundsDetection: true,
                    //                        disabled: true
                    //                    })
                    /**
                     * Event: The list of templates has finished loading - Enabled the selector
                     */
                    //                            .on('bindingComplete', function () {
                    //                                if (templates_Source.records.length == 0)
                    //                                    $(this).jqxDropDownList({
                    //                                        placeHolder: "(There are no organization Document templates for this record type)",
                    //                                        disabled: true
                    //                                    });
                    //                                else {
                    //                                    $(this).jqxDropDownList({
                    //                                        placeHolder: "Select a template to set Content and Description:",
                    //                                        dropDownHeight: templates_Source.records.length > 7 ? 184 : null,
                    //                                        autoDropDownHeight: templates_Source.records.length <= 7 ? true : false,
                    //                                        disabled: false
                    //                                    });
                    //                                }
                    //                            })
                    /**
                     * Event: A template is selected. Grab the information and fill in the document area
                     */
                    //                            .on("select", function (e) {
                    //                                if (e.args && e.args.item) {
                    //                                    // Check if Compose was already changed - Give a warning that override will happen
                    //                                    if (editor_nonempty)
                    //                                        if (!confirm("This document was already edited or contains another template.\nAre you sure you want to overwrite with new template?"))
                    //                                            return;
                    //
                    //                                    // Template was selected - Read the rendered template (Title/Body) and setup e-mail
                    //                                    $.get("/global/operations?action=Render_Template", {
                    //                                        id: e.args.item.originalItem.id,
                    //                                        org_id: master.config.perspective, // Only relevant when superuser is logged in
                    //                                        rt_id: master.config.type.base_id ? master.config.type.base_id : master.config.type.id,
                    //                                        rec_id: master.config.id
                    //                                    }, function (r, textStatus, jqXHR) {
                    //
                    //                                        $("#trainee-description", $trainee_editor).val(r.title);
                    //                                        if (r.body.length > 0)
                    //                                            master.rich_editor.content_Write(rich_editor_instance_key, r.body);
                    //                                        // Short timeout before focus to allow TinyMCE to sort itself out
                    //                                        setTimeout(function () {
                    //                                            master.rich_editor.focus();
                    //                                        }, 100);
                    //                                        editor_nonempty = true;
                    //
                    //                                    }, 'json')
                    //                                            .fail(cargohub.its.http_fail);
                    //                                }
                    //                            });
                }

                // Mark the editor as open (in one of the specified modes)
                editor_open = config.mode;

                $('#trainee-title, #trainee-version, #trainee-description, #trainee-webview-link', $trainee_editor).addClass(cargohub.input_theme_classes);
                $('#trainee-version', $trainee_editor).each(cargohub.components.ui.numbers_input);

                $('#trainee-webview-link', $trainee_editor).on('click', function () {
                    $(this).select();
                });
                $(".calendar").each(cargohub.components.ui.calendar_input);
                // Set/Change title (header)
                if (config.title)
                    $trainee_editor.jqxExpander('setHeaderContent', '<img src="/img/icons16/' + (config.icon || 'document_image_ver.png') + '" alt="[o]" class="inline-icon"> ' + config.title);

                switch (config.mode) {
                    case 'create_audit_activity':

                    break;
                    case 'create':
                        row_data = null;
                        editor_nonempty = false;


                        // Empty Title/Subject
                        // $('#trainee-title, #trainee-description', $trainee_editor).val("");
                        // Default version is "1" (written as 1.0)
                        var def_version = 1;
                        //$("#trainee-version", $trainee_editor).val(def_version.toFixed(1));

                        // Version up button is disabled while creating
                        $('#trainee-cmd-upver').hide();

                        var internal = document.getElementById('internaltype');
                        var external = document.getElementById('externaltype');

                        internal.addEventListener("change", function () {
                            if (internal.checked) {
                                // 10062024 changes by iwi 
                                $("#trainee_company_div").hide();
                                $("#com_name").hide();
                                // create by iwi  25042024 | start
                                $(".internal_type_by_user_list").show();
                                $(".external_type_by_user").hide();
                                // create by iwi  25042024 | end
                            }
                        });
                        external.addEventListener("change", function () {
                            console.log("779");
                            if (external.checked) {
                                // 10062024 changes by iwi 
                                $("#trainee_company_div").show();
                                $("#com_name").show();

                                // create by iwi  25042024 | start
                                $(".internal_type_by_user_list").hide();
                                $(".external_type_by_user").show();
                                // create by iwi  25042024 | end
                            }
                        });

                        
                        // 01102024 changes Training Scheduler section
                        $(".tr_scheduler_pass_result").removeClass('disabled-section');
                        var trainee_result_pending = document.getElementById('trainee_result_pending');
                        trainee_result_pending.addEventListener("change", function () {
                            if (trainee_result_pending.checked) {
                                $(".tr_scheduler_pass_result").addClass('disabled-section');
                            }
                        });

                        var trainee_result_pass = document.getElementById('trainee_result_pass');
                        trainee_result_pass.addEventListener("change", function () {
                            if (trainee_result_pass.checked) {
                                $(".tr_scheduler_pass_result").removeClass('disabled-section');
                            }
                        });
                        var trainee_result_fail = document.getElementById('trainee_result_fail');
                        trainee_result_fail.addEventListener("change", function () {
                            if (trainee_result_fail.checked) {
                                $(".tr_scheduler_pass_result").addClass('disabled-section');
                            }
                        });
                        $("#trainee_result_pending").prop("checked", true); // IWI when create new by default select result pending 
                        get_all_Internal_users();

                        // Default visibility and Edit access is (v)"Org" and (e)"Editor only"
                        //                        $('#trainee-visibility', $trainee_editor).jqxDropDownList('selectIndex', 2);
                        //                        $('#trainee-edit_access', $trainee_editor).jqxDropDownList('selectIndex', 0);

                        // Webview is off for new documents
                        //                        $(".trainee-webview-info", $trainee_editor).hide();
                        //                        $("#trainee-webview-state", $trainee_editor).val(false);
                        //                        $("#trainee-webview-link", $trainee_editor).val("-=- Save your document to receive a link -=-");
                        //                        $("#trainee-webview-secure", $trainee_editor).val(true);
                        //                        $("#trainee-publish-secure", $trainee_editor).val(true);

                        // Template line is always reset to "no selection" so it's ready to select (even with only one option available)
                        $("#trainee-tpl-line", $trainee_editor).show();
                        //                        $("#trainee-template", $trainee_editor).jqxDropDownList('clearSelection');

                        // Finally, clear editor
                        if (rich_editor_ready)
                            master.rich_editor.content_Write(rich_editor_instance_key, "\n");
                        break;

                    case 'edit':
                        // Check if there's overrides or defaults for the data/values - Process "row_data" before editing
                        if (config.overrides)
                            row_data = $.extend(row_data, config.overrides);

                        // Fill the doc. Name, Description and Version
                        //                        $('#trainee-title', $trainee_editor).val(row_data.name);
                        //                        $('#trainee-description', $trainee_editor).val(row_data.description);
                        if (!row_data.version)
                            row_data.version = 1;
                        //                        $("#trainee-version", $trainee_editor).val(row_data.version.toFixed(1));

                        // Version up button is enabled for editing
                        $('#trainee-cmd-upver').show();

                        // Visibility and Editing access
                        //                        for (c = 0; c < doc_Visibility_Source.length; c++)
                        //                            if (doc_Visibility_Source[c].k == row_data.visibility)
                        //                                $('#trainee-visibility', $trainee_editor).jqxDropDownList('selectIndex', c);
                        //                        for (c = 0; c < doc_Edit_access_Source.length; c++)
                        //                            if (doc_Edit_access_Source[c].k == row_data.edit_access)
                        //                                $('#trainee-edit_access', $trainee_editor).jqxDropDownList('selectIndex', c);

                        // Webview related (second column)
                        //                        if (row_data.wv_key) {
                        ////                            setTimeout(function () {
                        ////                                $("#trainee-webview-state", $trainee_editor).jqxSwitchButton('check');
                        ////                            }, 100);
                        //                            $("#trainee-webview-link", $trainee_editor).val("https://www.cargohub.aero/?action=document&qp=" + donrey.qp_conv(open_row_id) + "&key=" + row_data.wv_key);
                        //
                        //
                        //
                        //                        } else {
                        ////                            setTimeout(function () {
                        ////                                $("#trainee-webview-state", $trainee_editor).jqxSwitchButton('uncheck');
                        ////                            }, 100);
                        //                            $("#trainee-webview-link", $trainee_editor).val("-=- Save your document to receive a link -=-");
                        //                        }

                        //                        console.log(row_data);
                        //                        var value = row_data.wv_publish_for;
                        //                        $("input[name=publish_for][value=" + value + "]").prop('checked', true);
                        ////                                checkValueToshoForPullish();
                        //                        if (value != 'private') {
                        //                            $('.pfdsc').css({"display": "block"});
                        //                        } else {
                        //                            $('.pfdsc').css({"display": "none"});
                        //                        }
                        //                        if (row_data.wv_publish_for_ids != '') {
                        //                            var wv_publish_for_ids = $.parseJSON(row_data.wv_publish_for_ids);
                        //                            console.log(wv_publish_for_ids);
                        //                            $.each(wv_publish_for_ids, function (i, val) {
                        //                                $("input:checkbox[name='publish_for_department[]'][value='" + val + "']").prop('checked', true);
                        //                            });
                        //                        }

                        //                        console.log('row_data',row_data);
                        // Webview related (fourth column)
                        //                        if (row_data.wv_publish == '1') {
                        //                            console.log('yes', row_data.wv_publish);
                        //                            setTimeout(function () {
                        //                                $("#trainee-publish-secure", $trainee_editor).jqxSwitchButton('check');
                        //                            }, 100);
                        //                        } else {
                        //                            console.log('no ', row_data.wv_publish);
                        //                            setTimeout(function () {
                        //                                $("#trainee-publish-secure", $trainee_editor).jqxSwitchButton('uncheck');
                        //                            }, 100);
                        //
                        //                        }




                        //                        $("#trainee-webview-secure", $trainee_editor).val(row_data.wv_secure);
                        //			$("#trainee-publish-secure", $trainee_editor).val(row_data.wv_publish);
                        $("#trainee-tpl-line", $trainee_editor).hide();

                        // If editor already initialized (moving from another record, reload new body)
                        if (rich_editor_ready)
                            load_Body();
                        break;
                }

                // Finished initialization ( or reconfiguration ) - Show the expander and expand
                $trainee_editor.show().jqxExpander('enable');
                $trainee_editor.on('expanded', function () {
                    // When first expanded, initialize Content editor
                    if (!rich_editor_ready) {
                        master.rich_editor.create_Instance(rich_editor_instance_key,
                                {// Config
                                    container: $("#trainee-content", $trainee_editor),
                                    params: {
                                        // Only useful for TinyMCE
                                        tinyMCE: {
                                            plugins: "link autolink autoresize paste textcolor image hr fullscreen lists table colorpicker charmap searchreplace insertdatetime",
                                            toolbar: "bold italic underline | alignleft aligncenter alignright | formatselect fontselect fontsizeselect | hr link image table bullist numlist charmap insertdatetime | forecolor backcolor removeformat | undo redo | searchreplace fullscreen"
                                        }
                                    },
                                    print: {
                                        enabled: true // Enable printing function
                                    }
                                },
                                {// Events
                                    on_init: function () {
                                        // If first initialization starts in "edit" mode, load content. If not, just enable the editor
                                        $("#trainee-content-placeholder", $trainee_editor).hide();
                                        if (config.mode == 'edit') {
                                            load_Body();
                                        } else
                                            master.rich_editor.enable(rich_editor_instance_key);

                                        rich_editor_ready = true;
                                    },
                                    on_change: function () {
                                        editor_nonempty = true;
                                    }
                                }
                        );
                    }
                });
                $trainee_editor.jqxExpander('expand');

                // Add tooltips for relevant elements
                $(".info-tip", $trainee_editor).each(cargohub.components.ui.add_Tooltip);

                // Show the Save/Close controls
                $('#trainee-open-ops button').jqxButton({disabled: false});
                $('#trainee-open-ops').fadeIn();
            },
            // Becomes true when document is copied under a new name
            copy_mode = false,
            /**
             * Saves the content of the document editor (name, description, content)
             */

            // save_Editor = function ()
            // {
            //     // created by iwi 13052024 | start
            //     var trainee_type = $('input[name="trainee_type"]:checked').val();
            //     if (trainee_type == 1) {
            //         var internal_user_name = document.getElementById("dropdownlistContentinternal_user_ids").innerHTML;

            //         if (internal_user_name.search("span") == 1) {
            //             return cargohub.alert("Please select the name !");
            //         }

            //         $('#trainee_name').val(internal_user_name);
            //     }
            //     // created by iwi 13052024 | end
                 
            //     // 30082024 by iwi | start
            //     // if($('.training_date').val() == '' || $('.training_date').val() == undefined){
            //     //     return cargohub.alert("Please select the training date !");
            //     // }
            //     // 30082024 by iwi | end

            //     if (trainee_type == 0) {
            //         if (!$('#trainee_name').val()) {
            //             return cargohub.alert("The name field is required !");
            //         }
            //     }

            //     // alert("735 : "+$('input[name="trainee_type"]:checked').val());

            //     // email validaction
            //     var email = $('#trainee_email').val();
            //     var pattern = /^\b[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b$/i

            //     if (email) {
            //         if (!pattern.test(email)) {
            //             return cargohub.alert("The email is not valid !");
            //         }
            //     }
            //     var submit_data = {
            //         'link_id': master.config.id,
            //         'track_id': master.config.track,
            //         'trainee_name': $('#trainee_name').val(),
            //         'trainee_email': $('#trainee_email').val(),
            //         'trainee_company': $('#trainee_company').val(),
            //         'trainee_files': $('#trainee_files')[0].files[0] ? $('#trainee_files')[0].files[0].name : '',
            //         'trainee_phone': $('#trainee_phone').val(),
            //         'trainee_score': $('#trainee_score').val(),
            //         'training_date': $('#training_date').val(),
            //         'trainee_management_id': $('#trainee_management_id').val(),
            //         'trainee_result': $('input[type=radio][name=trainee_result]:checked').val(),
            //         'trainee_type': $('input[type=radio][name=trainee_type]:checked').val(),
            //     };
            //     console.log(submit_data);
            //     // submit the add/edit form using ajax
            //     $($trainee_editor).find('form').ajaxSubmit({
            //         dataType: 'json',
            //         data: submit_data,
            //         // beforeSubmit: function (formData, jqForm, options)
            //         // {

            //         //     // console.log("695 jqForm",jqForm);
            //         //     // console.log("695 options",options);
            //         //     // if (!$('#trainee-title', $trainee_editor).val())
            //         //     //     return cargohub.alert("The document must have a name or title !");

            //         //     // var doc_desc = $("#trainee-description", $trainee_editor).val();
            //         //     // if (doc_desc && doc_desc < 5)
            //         //     //     return cargohub.alert("A document description is not necessary, but when completed it must be longer than 5 characters");

            //         //     // // Check if Title is too long ( over 100 characters ) and refuse names which are too long.
            //         //     // if ($("#trainee-title", $trainee_editor).val().length > 100)
            //         //     //     return cargohub.alert("Document names longer than 100 characters are currently not supported. Please shorten it.");

            //         //     // if (doc_content.length < 20)
            //         //     //     return cargohub.alert("Document content is empty or too short. Minimum 20 characters needed");

            //         //     return true;
            //         // },
            //         success: function (x) {
            //             //         master.refresh_DataTable($("#tab_trainee div.jqx-datatable"), true); // true = do not clear\
            //             // master.toolbar_Report("#tab_trainee", x.response);

            //             // close_Editor();

            //             //         cargohub.components.spinner.On("Success!", x.response, 2500, function() {
            //             //             cargohub.components.spinner.Off();
            //             //         });
            //             //                        location.reload();

            //             // master.refresh_DataTable($("#tab_trainee div.jqx-datatable"), true); // true = do not clear

            //             // // TODO - Resolve the tab id to idx first
            //             // //					if(x.count)
            //             // //						cargohub.components.hashnav.update_Tab_Header(5, "Notes <span class='fiftycent'>["+x.count+"]</span>", "/img/icons16/note.png");
            //             //                         // Finally, show the operation result
            //             // master.toolbar_Report("#tab_trainee", x.response);

            //             // close_Editor();
            //             // cargohub.components.spinner.On("Success!", x.response, 2500, function () {
            //             //     cargohub.components.spinner.Off();
            //             // });
            //             // // Show the "Create" button again
            //             // $('#tab_trainee #trainee-create-op').fadeIn();
            //             // location.reload();
            //         },
            //         error: cargohub.its.http_fail
            //     });
            //     location.reload(); //30082024 | iwi
            // },
            /**
             * Minimize and close the editor if it was open.
             */
           
            

            
           
            
 
            
            
            // save_Editor = function () {
            //     // created by iwi 13052024 | start
            //     var trainee_type = $('input[name="trainee_type"]:checked').val();
            //     if (trainee_type == 1) {
            //         var internal_user_name = document.getElementById("dropdownlistContentinternal_user_ids").innerHTML;
            
            //         if (internal_user_name.search("span") == 1) {
            //             return cargohub.alert("Please select the name !");
            //         }
            
            //         $('#trainee_name').val(internal_user_name);
            //     }
            //     // created by iwi 13052024 | end
            
            //     // Email validation
            //     var email = $('#trainee_email').val();
            //     var pattern = /^\b[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b$/i;
            
            //     if (email) {
            //         if (!pattern.test(email)) {
            //             return cargohub.alert("The email is not valid !");
            //         }
            //     }
            
            //     var submit_data = {
            //         'link_id': master.config.id,
            //         'track_id': master.config.track,
            //         'trainee_name': $('#trainee_name').val(),
            //         'trainee_email': $('#trainee_email').val(),
            //         'trainee_company': $('#trainee_company').val(),
            //         'trainee_files': $('#trainee_files')[0].files[0] ? $('#trainee_files')[0].files[0].name : '',
            //         'trainee_phone': $('#trainee_phone').val(),
            //         'trainee_score': $('#trainee_score').val(),
            //         'training_date': $('#training_date').val(),
            //         'trainee_management_id': $('#trainee_management_id').val(),
            //         'trainee_result': $('input[type=radio][name=trainee_result]:checked').val(),
            //         'trainee_type': $('input[type=radio][name=trainee_type]:checked').val(),
            //     };
            
            //     console.log("Submit data:", submit_data);
            
            //     // Submit form data via AJAX
            //     $($trainee_editor).find('form').ajaxSubmit({
            //         dataType: 'json',
            //         data: submit_data,
            //         success: function (response) {
            //             console.log("AJAX success", response);
            
            //             // Check if save is successful and result is "Pass"
            //             if (response.traineeManagementSave === true && submit_data.trainee_result === "1") {
            //                 console.log("Save successful and result is Pass; generating PDF");
            
            //                 const pdfContent = `
            //                     <!DOCTYPE html>
            //                     <html lang="en">
            //                     <head>
            //                         <title>Cebu Pacific Certificate</title>
            //                         <meta charset="UTF-8">
            //                         <meta name="viewport" content="width=device-width, initial-scale=1.0">
            //                         <style>
            //                             .container { width: 842px; height: 608px; margin: 0 auto; }
            //                             .certificate_bg { position: relative; }
            //                             .perticipate_details p { margin: 0; font-size: 30px; }
            //                             .perticipate_details { position: absolute; top: 57%; bottom: 0; transform: translate(-106px, -8px); height: 15px; left: 50%; }
            //                             .certificate_dt { position: absolute; bottom: 72px; transform: translate(209px, 5px); }
            //                             @media print {
            //                                 .perticipate_details { position: absolute; top: 30%; bottom: 0; transform: translate(-106px, -8px); height: 15px; left: 50%; }
            //                                 .certificate_dt { position: absolute; bottom: 0%; top: 43%; transform: translate(209px, 5px); }
            //                             }
            //                         </style>
            //                     </head>
            //                     <body>
            //                         <section class="certificate">
            //                             <div class="container">
            //                                 <div class="certficate_details">
            //                                     <div class="certificate_bg">
            //                                         <img src="/img/certificate1.jpg" alt="Certificate Background">
            //                                     </div>
            //                                     <div class="perticipate_details">
            //                                         <p class="perticipate_name">${submit_data.trainee_name}</p>
            //                                     </div>
            //                                     <div class="certificate_dt">
            //                                         <p>${submit_data.training_date}</p>
            //                                     </div>
            //                                 </div>
            //                             </div>
            //                         </section>
            //                     </body>
            //                     </html>
            //                 `;
            
            //                 const element = document.createElement('div');
            //                 element.innerHTML = pdfContent;
            //                 document.body.appendChild(element);
            
            //                 // Generate and download PDF
            //                 html2pdf().from(element).save('Trainee_Certificate.pdf').then(() => {
            //                     console.log("PDF generated and downloaded successfully");
            //                     document.body.removeChild(element);
            
            //                     // After generating the PDF, reload the page
            //                     window.location.href = window.location.href; // Forces a full page reload
            //                 }).catch(error => {
            //                     console.error("Error generating PDF", error);
            //                     // Ensure page reload even if PDF generation fails
            //                    location.reload(); // Forces a full page reload
            //                 });
            //             } else {
            //                 console.log("Save successful but result is not Pass or PDF generation condition not met");
            
            //                 // If the result is not "Pass", just reload the page without generating PDF
            //                 location.reload(); // Forces a full page reload
            //             }
            //         },
            //         error: function (err) {
            //             console.error("AJAX error", err);
            //             console.log("Status:", err.status);
            //             console.log("Response Text:", err.responseText);
            
            //             try {
            //                 // Parse response to check for traineeManagementSave flag
            //                 const response = JSON.parse(err.responseText.match(/{.*}/)[0]);
            //                 if (response.traineeManagementSave === true && submit_data.trainee_result === "1") {
            //                     console.log("Parsed response indicates success; generating PDF");
            
            //                     const pdfContent = `
            //                         <!DOCTYPE html>
            //                         <html lang="en">
            //                         <head>
            //                             <title>Cebu Pacific Certificate</title>
            //                             <meta charset="UTF-8">
            //                             <meta name="viewport" content="width=device-width, initial-scale=1.0">
            //                             <style>
            //                                 .container { width: 842px; height: 608px; margin: 0 auto; }
            //                                 .certificate_bg { position: relative; }
            //                                 .perticipate_details p { margin: 0; font-size: 30px; }
            //                                 .perticipate_details { position: absolute; top: 57%; bottom: 0; transform: translate(-106px, -8px); height: 15px; left: 50%; }
            //                                 .certificate_dt { position: absolute; bottom: 72px; transform: translate(209px, 5px); }
            //                                 @media print {
            //                                     .perticipate_details { position: absolute; top: 30%; bottom: 0; transform: translate(-106px, -8px); height: 15px; left: 50%; }
            //                                     .certificate_dt { position: absolute; bottom: 0%; top: 43%; transform: translate(209px, 5px); }
            //                                 }
            //                             </style>
            //                         </head>
            //                         <body>
            //                             <section class="certificate">
            //                                 <div class="container">
            //                                     <div class="certficate_details">
            //                                         <div class="certificate_bg">
            //                                             <img src="/img/certificate1.jpg" alt="Certificate Background">
            //                                         </div>
            //                                         <div class="perticipate_details">
            //                                             <p class="perticipate_name">${submit_data.trainee_name}</p>
            //                                         </div>
            //                                         <div class="certificate_dt">
            //                                             <p>${submit_data.training_date}</p>
            //                                         </div>
            //                                     </div>
            //                                 </div>
            //                             </section>
            //                         </body>
            //                         </html>
            //                     `;
            
            //                     const element = document.createElement('div');
            //                     element.innerHTML = pdfContent;
            //                     document.body.appendChild(element);
            
            //                     // Generate and download PDF
            //                     html2pdf().from(element).save('Trainee_Certificate.pdf').then(() => {
            //                         console.log("PDF generated and downloaded successfully");
            //                         document.body.removeChild(element);
            //                         location.reload();
            //                         window.location.href = window.location.href; // Forces a full page reload
            //                     }).catch(error => {
            //                         console.error("Error generating PDF", error);
            //                         // Ensure page reload even if PDF generation fails
            //                         window.location.href = window.location.href; // Forces a full page reload
            //                     });
            //                 }
            //             } catch (parseError) {
            //                 console.error("Failed to parse response or generate PDF", parseError);
            //                 // Ensure page reload if error parsing the response
            //                 location.reload();
            //                 window.location.href = window.location.href; // Forces a full page reload
            //             }
            
            //             cargohub.its.http_fail();
            //         }
            //     });
            // },
            
            
            
//             save_Editor = function () {
//                 console.log("save_Editor function triggered"); // Check if function is called
            
//                 // Validate trainee type and name
//                 var trainee_type = $('input[name="trainee_type"]:checked').val();
//                 console.log("Trainee type:", trainee_type);
//                 if (trainee_type == 1) {
//                     var internal_user_name = document.getElementById("dropdownlistContentinternal_user_ids").innerHTML;
//                     if (internal_user_name.search("span") == 1) {
//                         return cargohub.alert("Please select the name !");
//                     }
//                     $('#trainee_name').val(internal_user_name);
//                 }
            
//                 if (trainee_type == 0 && !$('#trainee_name').val()) {
//                     return cargohub.alert("The name field is required !");
//                 }
            
//                 // Email validation
//                 var email = $('#trainee_email').val();
//                 var pattern = /^\b[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b$/i;
//                 if (email && !pattern.test(email)) {
//                     return cargohub.alert("The email is not valid !");
//                 }
            
//                 // Prepare data for submission
//                 var submit_data = {
//                     'link_id': master.config.id,
//                     'track_id': master.config.track,
//                     'trainee_name': $('#trainee_name').val(),
//                     'trainee_email': $('#trainee_email').val(),
//                     'trainee_company': $('#trainee_company').val(),
//                     'trainee_files': $('#trainee_files')[0].files[0] ? $('#trainee_files')[0].files[0].name : '',
//                     'trainee_phone': $('#trainee_phone').val(),
//                     'trainee_score': $('#trainee_score').val(),
//                     'training_date': $('#training_date').val(),
//                     'trainee_management_id': $('#trainee_management_id').val(),
//                     'trainee_result': $('input[type=radio][name=trainee_result]:checked').val(),
//                     'trainee_type': $('input[type=radio][name=trainee_type]:checked').val(),
//                 };
//                 console.log("Submit data:", submit_data);
            
//                 // Submit form data via AJAX
//                 $($trainee_editor).find('form').ajaxSubmit({
//                     dataType: 'json',
//                     data: submit_data,
//                     success: function (response) {
//                         console.log("AJAX success", response);
//                         location.reload();
//                     },
//                     error: function (err) {
//                         console.error("AJAX error", err);
//                         console.log("Status:", err.status); // Log status
//                         console.log("Response Text:", err.responseText); // Log server response text
//                         cargohub.its.http_fail();
//                     }
//                 });
            
//                 // Generate PDF after successful submission
//                 console.log("Generating PDF");
            
//                  // Get the current date
//     const currentDate = new Date().toLocaleDateString();

//     // HTML content for the PDF with the current date
//     const pdfContent = `
//         <!DOCTYPE html>
//         <html lang="en">
//         <head>
//             <title>Cebu Pacific Certificate</title>
//             <meta charset="UTF-8">
//             <meta name="viewport" content="width=device-width, initial-scale=1.0">
//             <style>
//                 .container { width: 842px; height: 608px; margin: 0 auto; }
//                 .certificate_bg { position: relative; }
//                 .perticipate_details p { margin: 0; font-size: 30px; }
//                 .perticipate_details { position: absolute; top: 57%; bottom: 0; transform: translate(-106px, -8px); height: 15px; left: 50%; }
//                 .certificate_dt { position: absolute; bottom: 72px; transform: translate(209px, 5px); }
//                 @media print {
//                     .perticipate_details { position: absolute; top: 30%; bottom: 0; transform: translate(-106px, -8px); height: 15px; left: 50%; }
//                     .certificate_dt { position: absolute; bottom: 0%; top: 43%; transform: translate(209px, 5px); }
//                 }
//             </style>
//         </head>
//         <body>
//             <section class="certificate">
//                 <div class="container">
//                     <div class="certficate_details">
//                         <div class="certificate_bg">
//                             <img src="/img/certificate1.jpg" alt="Certificate Background">
//                         </div>
//                         <div class="perticipate_details">
//                             <p class="perticipate_name">${submit_data.trainee_name}</p>
//                         </div>
//                         <div class="certificate_dt">
//                             <p>${currentDate}</p>
//                         </div>
//                     </div>
//                 </div>
//             </section>
//         </body>
//         </html>
//     `;

//     // Create a temporary element to hold the HTML for PDF generation
//     const element = document.createElement('div');
//     element.innerHTML = pdfContent;
//     document.body.appendChild(element);

//     // Generate and download PDF
//     html2pdf().from(element).save('Trainee_Certificate.pdf').then(() => {
//         console.log("PDF generated successfully");
//         document.body.removeChild(element); // Remove the temporary element
//         location.reload();
//     }).catch(error => {
//         console.error("Error generating PDF", error);
//         location.reload();
//     });
// },
            
// save_Editor = function () {
//     console.log("save_Editor function triggered");

//     // Validate trainee type and name
//     var trainee_type = $('input[name="trainee_type"]:checked').val();
//     console.log("Trainee type:", trainee_type);
//     if (trainee_type == 1) {
//         var internal_user_name = document.getElementById("dropdownlistContentinternal_user_ids").innerHTML;
//         if (internal_user_name.search("span") == 1) {
//             return cargohub.alert("Please select the name !");
//         }
//         $('#trainee_name').val(internal_user_name);
//     }

//     if (trainee_type == 0 && !$('#trainee_name').val()) {
//         return cargohub.alert("The name field is required !");
//     }

//     // Email validation
//     var email = $('#trainee_email').val();
//     var pattern = /^\b[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b$/i;
//     if (email && !pattern.test(email)) {
//         return cargohub.alert("The email is not valid !");
//     }

//     // Get the trainee result and prepare data for submission
//     var trainingResult = $('input[type=radio][name=trainee_result]:checked').val();
//     console.log("Training result:", trainingResult);

//     var submit_data = {
//         'link_id': master.config.id,
//         'track_id': master.config.track,
//         'trainee_name': $('#trainee_name').val(),
//         'trainee_email': $('#trainee_email').val(),
//         'trainee_company': $('#trainee_company').val(),
//         'trainee_files': $('#trainee_files')[0].files[0] ? $('#trainee_files')[0].files[0].name : '',
//         'trainee_phone': $('#trainee_phone').val(),
//         'trainee_score': $('#trainee_score').val(),
//         'training_date': $('#training_date').val(),
//         'trainee_management_id': $('#trainee_management_id').val(),
//         'trainee_result': trainingResult,
//         'trainee_type': trainee_type
//     };
//     console.log("Submit data:", submit_data);



// console.log(trainingResult);



      
                
                
                

       
//               var img_path_url = '';         
//               var path_url = '';  
//                var fileName = '';
//                var file_type = 'file';
               
               
                

// //        IWI save pdf certificate start         
         
                
                
//                 if (trainingResult === "1") {
                    
// $('button[data-op="trainee-save"]').html('Loading...');
//                     console.log("Generating PDF for passed trainee");

//                     const currentDate = new Date().toLocaleDateString();
//                     const pdfContent = `
//                         <!DOCTYPE html>
//                         <html lang="en">
//                         <head>
//                             <title>Cebu Pacific Certificate</title>
//                             <meta charset="UTF-8">
//                             <meta name="viewport" content="width=device-width, initial-scale=1.0">
//                             <style>
//                                 .container { width: 100%; height: 608px; margin: 0 auto; }
//                                 .certificate_bg { position: relative; }
//                                 .perticipate_details p { margin: 0; font-size: 30px; }
//                                 .perticipate_details { position: absolute; transform: translate(-106px, -8px); height: 15px; left: 50%; }
//                                 .certificate_dt { position: absolute; transform: translate(209px, 5px); }
//                                 @media print {
//                                     .perticipate_details { position: absolute; transform: translate(-106px, -8px); height: 15px; left: 50%; }
//                                     .certificate_dt { position: absolute;  transform: translate(209px, 5px); }
//                                 }
//                             </style>
//                         </head>
//                         <body>
//                             <section class="certificate">
//                                 <div class="container" style="max-width: 100%; max-height: 608px; margin: 0 auto;">
//                                     <div class="certficate_details">
//                                         <div class="certificate_bg">
//                                             <img style=" max-width: 100%; " src="/img/certificate1.jpg" alt="Certificate Background">
//                                         </div>
//                                         <div class="perticipate_details" style="top: 57%; ">
//                                             <p class="perticipate_name"  >${submit_data.trainee_name}</p>
//                                         </div>
//                                         <div class="certificate_dt" style="top: 85%;  ">
//                                             <p>${currentDate}</p>
//                                         </div>
//                                     </div>
//                                 </div>
//                             </section>
//                         </body>
//                         </html>
//                     `;



//                     html2pdf().from(pdfContent).set({
//                         margin: [22, 32, 22, 32], // No margins (top, right, bottom, left)
// //    filename: 'output.pdf', // Custom filename
//                         image: {type: 'jpeg', quality: 0.98}, // Image quality settings
//                         html2canvas: {
//                             scale: 2, // Scale factor for rendering (adjust if needed)
//                             logging: false, // Disable logs for cleaner output
//                             useCORS: true, // Enable CORS if loading external resources
//                         },
//                         jsPDF: {
//                             unit: 'mm', // Unit of measurement (mm, cm, pt)
//                             format: 'a4', // PDF format (A4 is typical)
//                             orientation: 'landscape', // Landscape orientation
//                             compress: true, // Compress PDF output
//                         }
//                     }).toPdf().get().output('blob').then((pdfBlob) => {
// //                    html2pdf().from(element).toPdf().output('blob').then((pdfBlob) => {
//                         // Convert the blob to base64 for easy transfer
//                         const reader = new FileReader();
//                         reader.readAsDataURL(pdfBlob);
//                         reader.onloadend = () => {
//                             const base64data = reader.result;
//                             // Send PDF data to the server using jQuery AJAX
//                             $.ajax({
//                                 url: "/record/process?action=Save_trainee_management_pdf_certificate",
//                                 type: 'POST',
//                                 data: {
//                                     pdfData: base64data, // Base64 encoded PDF
//                                     filename: 'Trainee_Certificate.pdf',
//                                 },
//                                 success: function (response) {

//                                     img_path_url = '/' + response.file_name;
//                                     fileName = 'Trainee_Certificate.pdf';
//                                     file_type = 'url';

//                                     submit_data['file_type'] = file_type;
//                                     submit_data['img_path'] = response.file_name;
//                                     path_url = img_path_url;



//                                     $($trainee_editor).find('form').ajaxSubmit({
//                                         dataType: 'html', // Expecting a mixed-content response
//                                         data: submit_data,
//                                         success: function (response) {
//                                             console.log("AJAX success, raw response:", response);

//                                             try {
//                                                 const cleanedResponse = response.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, "").trim();
//                                                 const jsonResponse = JSON.parse(cleanedResponse);
//                                                 console.log("Parsed response as JSON:", jsonResponse);


// //             IWI save pdf certificate start    

//                                                 var link = document.createElement('a'); // Create an anchor element
//                                                 link.href = path_url; // Set the URL of the file to be downloaded
//                                                 link.download = fileName || ''; // Optional: set a default file name

//                                                 // Trigger the download by simulating a click event
//                                                 document.body.appendChild(link); // Append the link to the body
//                                                 link.click(); // Simulate click to trigger download
//                                                 document.body.removeChild(link); // Remove the link after download
//                                                 location.reload();

// //     IWI save pdf certificate end

//                                             } catch (error) {
//                                                 console.error("Error parsing response as JSON", error);
//                                                 cargohub.alert("Error processing server response. Please contact support.");
//                                             }
//                                         },
//                                         error: function (err) {
//                                             console.error("AJAX error", err);
//                                             cargohub.alert("No response from server. Please contact support!");
//                                             console.log("Status:", err.status);
//                                             console.log("Response Text:", err.responseText);
//                                             cargohub.its.http_fail();
//                                         }
//                                     });

//                                     //
//                                     //document.body.removeChild(element); 
//                                 },
//                                 error: function (xhr, status, error) {
//                                     console.error("Error uploading PDF", error);
                                    
// $('button[data-op="trainee-save"]').html('Save');
// //                                    location.reload();
//                                 }
//                             });
//                         };
//                     });
// //console.log("Submit data:", submit_data);

//                 }
//                 else{
                    
// $('button[data-op="trainee-save"]').html('Loading...');
                    
//     $($trainee_editor).find('form').ajaxSubmit({
//         dataType: 'html', // Expecting a mixed-content response
//         data: submit_data,
//         success: function (response) {
//             console.log("AJAX success, raw response:", response);

//             try {
//                 // Remove <script> tags from the response to get the JSON part
//                 const cleanedResponse = response.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, "").trim();
//                 const jsonResponse = JSON.parse(cleanedResponse);
//                 console.log("Parsed response as JSON:", jsonResponse);
                
//                location.reload();
    
                

//             } catch (error) {
//                 console.error("Error parsing response as JSON", error);
//                 cargohub.alert("Error processing server response. Please contact support.");
//             }
//         },
//         error: function (err) {
//             console.error("AJAX error", err);
//             cargohub.alert("No response from server. Please contact support!");
//             console.log("Status:", err.status);
//             console.log("Response Text:", err.responseText);
//             cargohub.its.http_fail();
            
// $('button[data-op="trainee-save"]').html('Save');
//         }
//     });
//                 }
                 
// //                 IWI save pdf certificate end

//     // AJAX form submission
    
//     /*
    
//     $($trainee_editor).find('form').ajaxSubmit({
//         dataType: 'html', // Expecting a mixed-content response
//         data: submit_data,
//          beforeSubmit: function(formData, jqForm, options) {
                     
                
// //                return false;
//         },
//         success: function (response) {
//             console.log("AJAX success, raw response:", response);

//             try {
//                 // Remove <script> tags from the response to get the JSON part
//                 const cleanedResponse = response.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, "").trim();
//                 const jsonResponse = JSON.parse(cleanedResponse);
//                 console.log("Parsed response as JSON:", jsonResponse);
                
                
// //             IWI save pdf certificate start    
                
//                 var link = document.createElement('a'); // Create an anchor element
//     link.href = img_path; // Set the URL of the file to be downloaded
//     link.download = fileName || ''; // Optional: set a default file name

//     // Trigger the download by simulating a click event
//     document.body.appendChild(link); // Append the link to the body
//     link.click(); // Simulate click to trigger download
//     document.body.removeChild(link); // Remove the link after download
// //    location.reload();
    
// //     IWI save pdf certificate end
    
                

//                 // Check if trainee_result is "1" (pass) to trigger PDF generation
// //                if (trainingResult === "1") {
// //                    console.log("Generating PDF for passed trainee");
// //
// //                    const currentDate = new Date().toLocaleDateString();
// //                    const pdfContent = `
// //                        <!DOCTYPE html>
// //                        <html lang="en">
// //                        <head>
// //                            <title>Cebu Pacific Certificate</title>
// //                            <meta charset="UTF-8">
// //                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
// //                            <style>
// //                                .container { width: 842px; height: 608px; margin: 0 auto; }
// //                                .certificate_bg { position: relative; }
// //                                .perticipate_details p { margin: 0; font-size: 30px; }
// //                                .perticipate_details { position: absolute; top: 57%; bottom: 0; transform: translate(-106px, -8px); height: 15px; left: 50%; }
// //                                .certificate_dt { position: absolute; bottom: 72px; transform: translate(209px, 5px); }
// //                                @media print {
// //                                    .perticipate_details { position: absolute; top: 30%; bottom: 0; transform: translate(-106px, -8px); height: 15px; left: 50%; }
// //                                    .certificate_dt { position: absolute; bottom: 0%; top: 43%; transform: translate(209px, 5px); }
// //                                }
// //                            </style>
// //                        </head>
// //                        <body>
// //                            <section class="certificate">
// //                                <div class="container">
// //                                    <div class="certficate_details">
// //                                        <div class="certificate_bg">
// //                                            <img src="/img/certificate1.jpg" alt="Certificate Background">
// //                                        </div>
// //                                        <div class="perticipate_details">
// //                                            <p class="perticipate_name">${submit_data.trainee_name}</p>
// //                                        </div>
// //                                        <div class="certificate_dt">
// //                                            <p>${currentDate}</p>
// //                                        </div>
// //                                    </div>
// //                                </div>
// //                            </section>
// //                        </body>
// //                        </html>
// //                    `;
// //
// //                    const element = document.createElement('div');
// //                    element.innerHTML = pdfContent;
// //                    document.body.appendChild(element);
// //
// //                    html2pdf().from(element).save('Trainee_Certificate.pdf').then(() => {
// //                        console.log("PDF generated and downloaded successfully");
// //                        document.body.removeChild(element);
// //                        location.reload();
// //                    }).catch(error => {
// //                        console.error("Error generating PDF", error);
// //                        location.reload();
// //                    });
// //                } else {
// //                    console.log("Training result is pending or fail; PDF generation skipped");
// //                    location.reload();
// //                }
//             } catch (error) {
//                 console.error("Error parsing response as JSON", error);
//                 cargohub.alert("Error processing server response. Please contact support.");
//             }
//         },
//         error: function (err) {
//             console.error("AJAX error", err);
//             cargohub.alert("No response from server. Please contact support!");
//             console.log("Status:", err.status);
//             console.log("Response Text:", err.responseText);
//             cargohub.its.http_fail();
//         }
//     });
//     */
    
// },

save_Editor = function () {
    console.log("save_Editor function triggered");

    // Validate trainee type and name
    var trainee_type = $('input[name="trainee_type"]:checked').val();
    console.log("Trainee type:", trainee_type);
    if (trainee_type == 1) {
        var internal_user_name = document.getElementById("dropdownlistContentinternal_user_ids").innerHTML;
        if (internal_user_name.search("span") == 1) {
            return cargohub.alert("Please select the name!");
        }
        $('#trainee_name').val(internal_user_name);
    }

    if (trainee_type == 0 && !$('#trainee_name').val()) {
        return cargohub.alert("The name field is required!");
    }

    // Email validation
    var email = $('#trainee_email').val();
    var pattern = /^\b[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b$/i;
    if (email && !pattern.test(email)) {
        return cargohub.alert("The email is not valid!");
    }

    // Get the trainee result and prepare data for submission
    var trainingResult = $('input[type=radio][name=trainee_result]:checked').val();
    console.log("Training result:", trainingResult);
    console.log("abc",master.config.master_org_id);
    
    

    var submit_data = {
        'link_id': master.config.id,
        'track_id': master.config.track,
        'trainee_name': $('#trainee_name').val(),
        'trainee_email': $('#trainee_email').val(),
        'trainee_company': $('#trainee_company').val(),
        'trainee_files': $('#trainee_files')[0].files[0] ? $('#trainee_files')[0].files[0].name : '',
        'trainee_phone': $('#trainee_phone').val(),
        'trainee_score': $('#trainee_score').val(),
        'training_date': $('#training_date').val(),
        'trainee_management_id': $('#trainee_management_id').val(),
        'trainee_result': trainingResult,
        'trainee_type': trainee_type
    };
    console.log("Submit data:", submit_data);

    // Get the training title (this could be from a dropdown or other field)
    // var training_title = $('#training_title').val();  // Assuming the training title is in a field with id `training_title`
    trainee_name = $('#trainee_name').val();
     console.log("Training Name:", trainee_name);
     console.log("Trainee Name: ", submit_data.trainee_name);
     
     
                    
     $($trainee_editor).find('form').ajaxSubmit({
         dataType: 'html', // Expecting a mixed-content response
         data: submit_data,
         success: function (response) {
             console.log("AJAX success, raw response:", response);

             try {
                 // Remove <script> tags from the response to get the JSON part
                 const cleanedResponse = response.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, "").trim();
                 const jsonResponse = JSON.parse(cleanedResponse);
                 console.log("Parsed response as JSON:", jsonResponse);
                
                location.reload();  // 12022024 comment out
    
                

             } catch (error) {
                 console.error("Error parsing response as JSON", error);
                 cargohub.alert("Error processing server response. Please contact support.");
             }
         },
         error: function (err) {
             console.error("AJAX error", err);
             cargohub.alert("No response from server. Please contact support!");
             console.log("Status:", err.status);
             console.log("Response Text:", err.responseText);
             cargohub.its.http_fail();
            
 $('button[data-op="trainee-save"]').html('Save');
         }
     });
    
     //certificate dynamic end
    
}; 










  
            
            
            
            close_Editor = function (transition)
            {
                if (!$trainee_editor) // Not yet initialized
                    return;

                // TODO: Check if changes have been made, and warn if so (to avoid closing unsaved document)
                // Minimize and close the editor
                if ($trainee_editor.jqxExpander('expanded')) {
                    $trainee_editor.on('collapsed', function () {
                        // When finishing collapse... hide the editor
                        $trainee_editor.hide();
                        // ... and disable the event
                        $trainee_editor.off('collapsed');
                    });
                    $trainee_editor.jqxExpander('collapse');
                } else {
                    // If already collapsed, just hide it
                    $trainee_editor.hide();
                }

                // 21112024 Training Scheduler Training Date section work start
                    trainingSchedulerTraineeId = [];
                    $('#trainee-result-update-ops').css('display', 'none');
                // 21112024 Training Scheduler Training Date section work end

                // Also fadeout the Save/Close buttons
                $('#tab_trainee #trainee-open-ops button').jqxButton({disabled: true});
                $('#tab_trainee #trainee-open-ops').fadeOut();

                if (!transition) {
                    if (selected_row)
                        $('#tab_trainee #trainee-row-ops').fadeIn();
                    // Show the "Create" button in case it was hidden by a previous operation
                    $('#tab_trainee #trainee-create-op').fadeIn();
                }

                // editor_nonempty = false;
                // editor_open = false;
                open_row_id = null;
                // copy_mode = false;
                return true;
            },
            /**
             * Open the document viewer (No form, just the data in a static, read-only format)
             */
            // view_Document = function (config)
            // {
            //     // alert('view_Document');
            //     if (docs_viewer_Ready) {
            //         // Update expander with new settings (if necessary)
            //         if (config && config.expander)
            //             $trainee_viewer.jqxExpander(config.expander);

            //     } else {
            //         // Init the expander itself on first use
            //         if (!config.expander)
            //             config.expander = {
            //                 disabled: false,
            //                 expanded: false
            //             };
            //         config.expander.theme = cargohub.jqxtheme;
            //         $trainee_viewer.jqxExpander(config.expander);

            //         docs_viewer_Ready = true;
            //     }
            //     $trainee_viewer.show();

            //     // Set/Change title (header)
            //     if (config.title)
            //         $trainee_viewer.jqxExpander('setHeaderContent', '<img src="/img/icons16/' + (config.icon || 'document_image_ver.png') + '" alt="[:]" class="inline-icon"> ' + config.title);

            //     var row_data = $.extend({}, selected_row);
            //     // Check if there's overrides or defaults for the data/values - Process "row_data" before rendering the template
            //     if (config.overrides)
            //         row_data = $.extend(row_data, config.overrides);

            //     row_data.dt_format = moment(row_data.dt).format("YYYY-MM-DD (dddd) HH:mm:ss") + ' <span class="gray">(' + today.diff(row_data.dt, 'days') + ' days ago)</span>';

            //     // Render the doc viewer template into the body of the expander
            //     $trainee_viewer.jqxExpander('setContent', $('#template-trainee-viewer').html().present(row_data));

            //     // Document body is not loaded in the grid, so load it now
            //     $("#document-viewer-body", $trainee_viewer).html("<div class='center'>(loading, please wait...)</div>");
            //     $.get('/global/note', {
            //         action: "Load_Body",
            //         id: row_data.uid,
            //         content_type: 'html'
            //     }, function (body) {
            //         if (body.length > 0)
            //             $("#document-viewer-body", $trainee_viewer).html(body);
            //         else
            //             $("#document-viewer-body", $trainee_viewer).html("<span class='gray'>(This document has no content)</span>");
            //     });

            //     // Add tooltips for relevant elements
            //     $(".info-tip", $trainee_viewer).each(cargohub.components.ui.add_Tooltip);

            //     $trainee_viewer.jqxExpander('expand');

            //     // Show the Close control
            //     donrey.cancel_Delay('hide-tasks-open-ops');
            //     $('#tab_trainee #trainee-open-ops button').jqxButton({disabled: false});
            //     $('#tab_trainee #trainee-open-ops').fadeIn();

            //     viewer_open = true;
            // },
            /**
             * Close the document Viewer expander
             */
            close_Viewer = function (transition)
            {
                if (!$trainee_viewer) // Not yet initialized
                    return;

                if ($trainee_viewer.jqxExpander('expanded')) {
                    $trainee_viewer.on('collapsed', function () {
                        // When finishing collapse... hide the editor
                        $trainee_viewer.hide();
                        // ... and disable the event
                        $trainee_viewer.off('collapsed');
                    });
                    $trainee_viewer.jqxExpander('collapse');
                } else {
                    // If already collapsed, just hide it
                    $trainee_viewer.hide();
                }

                // Also fadeout the Close buttons
                $('#tab_trainee #trainee-open-ops button').jqxButton({disabled: true});
                $('#tab_trainee #trainee-open-ops').fadeOut();

                if (!transition) {
                    if (selected_row)
                        $('#tab_trainee #trainee-row-ops').fadeIn();
                    // Show the "Create" button in case it was hidden by a previous operation
                    $('#tab_trainee #trainee-create-op').fadeIn();
                }

                viewer_open = false;

                return true;
            },
            /**
             * Opens the document for either editing or viewing (depending on access rights)
             * In Editing mode, the call is then passed to init_Editor(), which loads TinyMCE and then places the document content in it. A "Save" command is added to the toolbar
             * In Viewing mode, the document is simply loaded into a container, without any editing capabilities. The only available command is "Close"
             */
            open_Document = function (row)
            {
                // Fadeout Create command when Editor or Viewer are active
                $('#tab_trainee #trainee-create-op').fadeOut();

                console.log(' 1193 row', row);

                if (row)
                    selected_row = row;

                    // console.log('selected_row', selected_row);
                    
                var trainee_phone = +selected_row.number;

                var trainee_score = +selected_row.score;
                var uid = +selected_row.uid;
                $('#trainee_name').val(selected_row.name);

                $('#trainee_email').val(selected_row.email);
                $('#trainee_company').val(selected_row.company_name);


                
                //  01102024 changes Training Scheduler section 
                if (selected_row.result == 'Fail') {
                    $(".tr_scheduler_pass_result").addClass("disabled-section");
                    $('input[name="trainee_result"][value="0"]').prop('checked', true);
                } else if (selected_row.result == "Pending") {
                    $(".tr_scheduler_pass_result").addClass("disabled-section");
                    $('input[name="trainee_result"][value="2"]').prop('checked', true);
                } else {
                    $(".tr_scheduler_pass_result").removeClass("disabled-section");
                    $('input[name="trainee_result"][value="1"]').prop('checked', true);
                }
                if (selected_row.trainee_type == "1") {
                    // 10062024 changes by iwi 
                    $("#trainee_company_div").hide();
                    $("#com_name").hide();
                    $('input[name="trainee_type"][value="1"]').prop('checked', true);

                    // create by iwi  25042024 | start
                    $(".internal_type_by_user_list").show();
                    $(".external_type_by_user").hide();
                    // create by iwi  25042024 | end
                } else {
                     // 10062024 changes by iwi 
                    $("#trainee_company_div").show();
                    $("#com_name").show();
                    $('input[name="trainee_type"][value="0"]').prop('checked', true);

                    // create by iwi  25042024 | start
                    $(".internal_type_by_user_list").hide();
                    $(".external_type_by_user").show();
                    // create by iwi  25042024 | end
                }
                // $('input[name="trainee_result"]:checked').val(selected_row.result);
                $('input[name="trainee_type"]:checked').val(selected_row.trainee_type);

                // create by iwi  25042024 | start
                get_all_Internal_users(selected_row.user_issue_id);
                var internal = document.getElementById('internaltype');
                var external = document.getElementById('externaltype');
                internal.addEventListener("change", function () {
                    if (internal.checked) {
                        $(".internal_type_by_user_list").show();
                        $(".external_type_by_user").hide();
                    }
                });
                external.addEventListener("change", function () {
                    if (external.checked) {
                        $(".internal_type_by_user_list").hide();
                        $(".external_type_by_user").show();
                        $("#trainee_name").val('');
                    }
                });

                // $("#internal_user option[data-id='" + selected_row.user_issue_id + "']").prop("selected", true);

                // $(`#internal_user option[value='${selected_row.user_issue_id}']`).prop('selected', true);

                // $("#").val();/
                // create by iwi  25042024 | end


                // 01102024 changes Training Scheduler section
                // var trainee_result = document.getElementsByName('trainee_result');
                // trainee_result.addEventListener("change", function () {
                //     console.log(" trainee_result 1287  ",$(this).val());
                // });

                // $('select[name="trainee_result"]').on('change', function() {
                //     if ($(this).val() === '2') {
                //         $('.tr_scheduler_pass_result').removeClass('disabled-section');
                //     } else {
                //         $('.tr_scheduler_pass_result').addClass('disabled-section');
                //     }
                // });

                // 01102024 changes Training Scheduler section
                var trainee_result_pending = document.getElementById('trainee_result_pending');
                trainee_result_pending.addEventListener("change", function () {
                    if (trainee_result_pending.checked) {
                        $(".tr_scheduler_pass_result").addClass('disabled-section');
                    }
                });

                var trainee_result_pass = document.getElementById('trainee_result_pass');
                trainee_result_pass.addEventListener("change", function () {
                    if (trainee_result_pass.checked) {
                        $(".tr_scheduler_pass_result").removeClass('disabled-section');
                    }
                });
                var trainee_result_fail = document.getElementById('trainee_result_fail');
                trainee_result_fail.addEventListener("change", function () {
                    if (trainee_result_fail.checked) {
                        $(".tr_scheduler_pass_result").addClass('disabled-section');
                    }
                });


                $('#trainee_phone').val(trainee_phone);
                $('#trainee_management_id').val(uid);
                $('#trainee_score').val(trainee_score);

                $("#trainee-download").css('display', 'block');
                $('#download_link').css('display', 'inline-block');
                $('#download_link').html(selected_row.files);
                $("#labdownload").css('display', 'inline-block');
                $(".training_date_div").html("");
                $(".training_date_div").html("<label></label>");//28082024 changes by iwi
                // $(".training_date_div").html("<div id='xfd_date_train' style='width: 400px;'  class='form-control calendar training_date' name='training_date' data-value=''></div>");
                //console.log('  1242 1242 1242  ', selected_row.training_date);
                // $('.training_date').attr("data-value", selected_row.training_date);

                // 28082024 changes by iwi | start
                $(".training_date_div").html("<label>"+selected_row.training_date+"</label>");
                $("#training_date").val(selected_row.training_date);
                // 28082024 changes by iwi | end
               
               
                // 02122024 Training Scheduler upload multiple files
                $('#trainee_tab_display_all_files').html("");
                selected_row.attach_all_files.forEach(file => {
                    $('#trainee_tab_display_all_files').append(file.files);
                });
                $("#current_trainee_all_files_name").val(selected_row.current_attach_all_files);
                // 02122024 Training Scheduler upload multiple files
                

                if (selected_row.files == '') {
                   
                    $('#download_link').css('display', 'none');
                    $('#labdownload').css('display', 'none');
                    $("#trainee-download").css('display', 'none');
                }


                if (selected_row.training_date != '0000-00-00') {
                    $('.training_date').attr("data-value", selected_row.training_date);
                } else {
                    $('.training_date').attr("data-value", '');
                }

                $('#tab_trainee #trainee-open-ops, #tab_trainee .docs-write-ops').fadeIn();
                close_Viewer(true);
                init_Editor({
                    title: "Edit document: " + selected_row.name,
                    icon: "pencil.png",
                    mode: "edit"
                });
                /*
                 var edit_access = docs_manager_priv ||
                 (selected_row.edit_access == 'shared'
                 || (selected_row.edit_access == 'org' && (selected_row.visibility == 'dep' || selected_row.visibility == 'org'))
                 || (selected_row.edit_access == 'dep' && selected_row.dep_id == user_session.cdep_id)
                 || (selected_row.edit_access == 'user' && selected_row.editor_id == user_session.id)
                 );
                 
                 if (edit_access) {
                 // Edit mode
                 close_Viewer(true);
                 $('#tab_trainee #trainee-open-ops, #tab_trainee .docs-write-ops').fadeIn();
                 
                 open_row_id = selected_row.uid;
                 
                 init_Editor({
                 title: "Edit document: " + selected_row.name,
                 icon: "pencil.png",
                 mode: "edit"
                 });
                 } else {
                 // Read-only mode
                 close_Editor(true);
                 
                 $('#tab_trainee .docs-write-ops').hide(); // No "Save" function in View mode
                 $('#tab_trainee #trainee-open-ops').fadeIn();
                 
                 // View mode, switch to the task viewer
                 view_Document({
                 title: "Viewing document: " + selected_row.name
                 });
                 }
                */


            },
            delete_Document = function ()
            {
                $.post("/record/process?action=delete_trainee_management", {
                    link: "issue",
                    link_id: master.config.id, // Linked record ID
                    id: selected_row.uid, // Document ID
                }, function (x) {
                    // master.refresh_DataTable($("#tab_trainee div.jqx-datatable"));
                    // selected_row = null;
                    // master.toolbar_Report("#tab_trainee", x.response);
                    // close_Editor();
                    cargohub.components.spinner.On("Success!", x.response, 2500, function () {
                        cargohub.components.spinner.Off();
                    });
                     location.reload(); //22082024 by iwi

                }).fail(cargohub.its.http_fail);
            },
            // 21112024 Training Scheduler Training Date section work
            direct_result_update = function ()
            {
                if($('#trainee_result_value').val() == ''){
                    cargohub.alert("Please select the result.");
                    return;
                }
                

                
                $.post("/record/process?action=Direct_trainee_result_update", {
                    trainee_result_value:   $('#trainee_result_value').val(),
                    trainee_row_id: trainingSchedulerTraineeId,
                    link_id: master.config.id,
                    id: selected_row.uid,
                }, function (x) {
                    cargohub.components.spinner.On("Success!", x.response, 2500, function () {
                        cargohub.components.spinner.Off();
                    });
                    location.reload();

                }).fail(cargohub.its.http_fail);
            },

            // 13032025 Training Scheduler table user email work
            trainee_email_copy = function ()
            {
                // Convert the array into a string with line breaks
                var emailString = trainingSchedulerTraineeEmail.join("\n");

                // Create a temporary textarea element to copy text
                var tempTextarea = $("<textarea>");
                $("body").append(tempTextarea);
                tempTextarea.val(emailString).select();
                document.execCommand("copy");
                tempTextarea.remove();
                cargohub.alert("Emails have been copied to the clipboard.");
            },

            upload_default_visibility_index = 2,
            master_org_index = null,
            /**
             * Setup for the upload window, containing Dropzone and two dropdown selectors
             */
            setup_Upload = function ($window)
            {
                var
                        $access_orgs = $('#uploads-access_orgs', $window),
                        uploaded_files = {
                            error: 1	// Beings with Error code "1" - Empty dropzone
                        },
                        /**
                         * Helper: Links a list of succesfully uploaded files to the current record
                         * @param on_success_CB Callback for when the linking is successful
                         */
                        link_pre_files = function (on_success_CB) {
                            $.post("?action=link_pre_files", {
                                file_ids: JSON.stringify(uploaded_files.ids),
                                upload_size: uploaded_files.size,
                                org_id: master.config.perspective, // Current organization (perspective for admin - Not useful for non-admin as it will default to the user's organization anyway)
                                visibility: $('#uploads-visibility', $window).val(),
                                access_orgs: $access_orgs.val()
                            }, function (x) {
                                if (on_success_CB)
                                    on_success_CB(x);
                            })
                                    .fail(cargohub.its.http_fail);
                        };

                // Setup Dropzone instance
                if (!file_uploader_ready) {
                    console.log(' master file_uploader 1050 ', master.file_uploader);
                    master.file_uploader.create_Instance(file_uploader_instance_key,
                            {// Config

                                container: $("#files-dropzone", $window),
                                height: 290
                            },
                            {// Events
                                /**
                                 * Ready event - When Dropzone finished loading, stuff can be performed (For example adding mock-files?)
                                 */
                                on_ready: function () {
                                    file_uploader_ready = true;
                                },
                                /**
                                 * Content event - At least one file has been dropped, or a mock-file is prepared
                                 */
                                on_content: function () {
                                    $("#files-dropzone-helper", $window).css('visibility', "visible").fadeTo(1);
                                    $('#modal_files-upload_cancel', $window).css({
                                        backgroundColor: "#C40",
                                        color: "white"
                                    }).text("Cancel uploads");
                                },
                                /**
                                 * Empty event - The dropzone is empty of files or mock-files. Reset the window
                                 */
                                on_empty: function () {
                                    uploaded_files = {error: 1};

                                    $('#modal_files-upload_ok', $window).jqxButton({disabled: false}).jqxButton('render');
                                    $('#modal_files-upload_cancel', $window).css({
                                        backgroundColor: "inherit",
                                        color: "inherit"
                                    }).text("Close the window");

                                    $("#files-dropzone-helper", $window).fadeTo(0);
                                },
                                /**
                                 * File Sending event - A file has begun uploading. Disable the OK button until upload is complete
                                 */
                                on_filesending: function () {
                                    $('#modal_files-upload_ok', $window).jqxButton({disabled: true}).jqxButton('render');
                                },
                                // - on_filesuccess		A file has completed uploading
                                // - on_fileremove		A file or mock file has been removed via a remove link
                                /**
                                 * Upload Complete event - All queued files have finished uploading. Allow pressing the OK button
                                 */
                                on_uploadcomplete: function (files)
                                {
                                    uploaded_files = files;
                                    $('#modal_files-upload_ok', $window).jqxButton({disabled: false}).jqxButton('render');
                                }
                            }
                    );
                }

                $('#uploads-visibility', $window).jqxDropDownList({
                    theme: cargohub.jqxtheme,
                    source: file_visibility_source,
                    displayMember: "text", valueMember: "k",
                    width: 428, height: 24, autoDropDownHeight: true,
                    selectedIndex: upload_default_visibility_index,
                    placeHolder: "Please select visibility for all new uploads"
                })
                        /**
                         * Event - The visibility selector is changed.
                         */
                        .on('select', function (event) {
                            if (event.args.item.value == "shared") {
                                $access_orgs.parent().show();
                                $access_orgs.jqxDropDownList('open');
                            } else
                                $access_orgs.parent().hide();
                        });

                // Access IDs selector (for sharing mode)
                $access_orgs.jqxDropDownList({
                    theme: cargohub.jqxtheme,
                    source: master.source_Sharing(function (records) {
                        for (c = 0; c < records.length; c++) {
                            if (records[c].id == master.config.perspective) {
                                // Check the item for the perspective organization and disable turning off
                                master_org_index = c;
                                $access_orgs.jqxDropDownList('updateAt', {
                                    value: master.config.perspective,
                                    label: records[c].name + " [my org]",
                                    checked: true
                                }, c);
                                $access_orgs.jqxDropDownList('checkIndex', c);
                                $access_orgs.jqxDropDownList('disableAt', c);
                            }
                        }
                    }),
                    checkboxes: true,
                    displayMember: "name", valueMember: "id",
                    width: 428, height: 24, autoDropDownHeight: true,
                    renderer: function (index, label, value) {
                        var sho = master.dataSources.shareList.records[index];
                        label = "<span class='right'>(" + sho.type_id + ")</span>" + label;
                        if (sho.owner)
                            label += " <b>[my org]</b>";
                        if (sho.disabled)
                            label += " [disabled]";
                        return label;
                    }
                });

                // Setup the action for "OK" button
                /**
                 * OK button event connects the uploaded files to the record
                 * (Just closing the window does not, like the cancel uploads button)
                 */
                $('#modal_files-upload_ok', $window).off('click').on('click', function ()
                {
                    if (uploaded_files.error)
                        switch (uploaded_files.error) {
                            // Dropzone is empty, nothing to do, just close the window
                            case 1:
                                $window.jqxWindow('close');
                                return;
                                // Some errors while uploading
                            case 3:
                                cargohub.alert("Some files failed upload or were cancelled during transfer. Check the individual items for errors.");
                                return;
                        }

                    if (uploaded_files.ids && uploaded_files.ids.length) {
                        link_pre_files(function (x) {
                            // clear Dropzone as we are done, but without triggering the remove events (that includes server-side delete)
                            master.file_uploader.empty(file_uploader_instance_key, true);

                            // Close the window, update the attachments panel and display a message
                            $window.jqxWindow('close');
                            master.refresh_DataTable($("#tab_panels div.jqx-datatable"));

                            if (x.count)
                                cargohub.components.hashnav.update_Tab_Header(3, "Files <span class='fiftycent'>[" + x.count + "]</span>", "/img/icons16/attach.png");
                            // Finally, show the operation result
                            master.toolbar_Report("div#tab_panels", x.response);
                        });
                    } else
                        cargohub.alert("Unknown error in the upload mechanism. Try emptying the window with the <b class='red'>Cancel uploads</b> button");
                });

                $('#modal_files-upload_cancel', $window).off('click').on('click', function () {
                    if (uploaded_files.error)
                        switch (uploaded_files.error) {
                            // Dropzone is empty, nothing to do, just close the window
                            case 1:
                                $window.jqxWindow('close');
                                break;
                                // Uploads in progress
                            case 2:
                                if (!confirm("Uploads are still in progress. Are you sure you want to cancel all uploads ?"))
                                    return;
                                master.file_uploader.empty(file_uploader_instance_key);
                                break;
                                // Some errors while uploading
                            case 3:
                                if (!confirm("Some uploads were not completed successfully. Are you sure you want to cancel all uploads ?"))
                                    return;
                                master.file_uploader.empty(file_uploader_instance_key);
                                break;
                        }
                    else
                    if (uploaded_files.ids && uploaded_files.ids.length) {
                        if (!confirm("Files are already uploaded. Are you sure you want to discard them ?"))
                            return;
                        master.file_uploader.empty(file_uploader_instance_key);
                    }

                    $window.jqxWindow('close');
                });
            },
            /**
             * Opens the upload window, based on Dropzone component
             */
            files_Upload = function ()
            {
                // Nothing changes between uses, just reopen existing window
                if (cargohub.components.modal.windows['files-upload']) {
                    return cargohub.components.modal.reopen('files-upload', function ($window) {
                        // Reset visibility controls after the window is reopened (Dropzone was reset when it was originally closed, either by cancelling or by finishing uploads)
                        var $access_orgs = $('#uploads-access_orgs', $window);
                        $('#uploads-visibility', $window).jqxDropDownList('selectIndex', upload_default_visibility_index);
                        $access_orgs.jqxDropDownList('uncheckAll');
                        $access_orgs.jqxDropDownList('enableAt', master_org_index);
                        $access_orgs.jqxDropDownList('checkIndex', master_org_index);
                        $access_orgs.jqxDropDownList('disableAt', master_org_index);
                        $access_orgs.parent().hide();
                    });
                }

                // This modal doesn't have a close button, instead it has a special "Cancel" button that will remove any uploaded files
                // Instead, if pressing Ok, the uploaded files are linked to the record
                cargohub.components.modal.create('files-upload', {
                    buttons: {cancel: "Close the window", ok: "Ok"},
                    btn_roles: {},
                    showCloseButton: false,
                    btn_styles: {cancel: "float: left; margin-left: 0; background-image: none; background-color: inherit !important; color: inherit !important;"}
                });

                cargohub.components.modal.open('files-upload', {
                    width: 650, height: 480,
                    title: "Upload files for: " + master.config.type.name + " - " + master.config.track,
                    content: $('#win-upload_files').html().present({
                        _itc: cargohub.input_theme_classes
                    }),
                    callback: setup_Upload
                });
            },
            /**
             * Opens the file attachment configuration window, used to change access for the record, set labels and perform other operations
             */
            file_Config = function (row)
            {
                if (row)
                    selected_row = row;

                selected_row.mtime_formatted = $.format.date(selected_row.mtime, cargohub.datetime_format);
                selected_row.qp = donrey.qp_conv(selected_row.id);

                cargohub.components.modal.create('file-config', {
                    btn_roles: {cancel: "cancel"}
                });

                cargohub.components.modal.open('file-config', {
                    width: 600, height: 350,
                    title: "Configure attached file for: " + master.config.type.name + " - " + master.config.track,
                    content: $('#template-file-config').html().present({
                        _itc: cargohub.input_theme_classes,
                        file: selected_row,
                        master: master.config
                    }),
                    callback: function ($window) // Setup the controls inside the window
                    {
                        // Visibility selector
                        var visibility_index = -1, $access_orgs = $('#file-access_orgs', $window);
                        for (c = 0; c < file_visibility_source.length; c++)
                            if (selected_row.visibility == file_visibility_source[c].k)
                                visibility_index = c;
                        $('#file-visibility', $window).jqxDropDownList({
                            theme: cargohub.jqxtheme,
                            source: file_visibility_source,
                            displayMember: "text", valueMember: "k",
                            width: 428, height: 24, autoDropDownHeight: true,
                            selectedIndex: visibility_index
                        })
                                /**
                                 * Event - The visibility selector is changed.
                                 */
                                .on('select', function (event) {
                                    if (event.args.item.value == "shared") {
                                        $access_orgs.parent().show();
                                        $access_orgs.jqxDropDownList('open');
                                    } else
                                        $access_orgs.parent().hide();
                                });

                        // If visibility is in sharing mode, show the orgs selector:
                        if (visibility_index == 3)
                            $access_orgs.parent().show();

                        // Access IDs selector (for sharing mode)
                        $access_orgs.jqxDropDownList({
                            theme: cargohub.jqxtheme,
                            source: master.source_Sharing(function (records) {
                                var current_shares = selected_row.access_orgs ? selected_row.access_orgs.split(",") : [];
                                for (c = 0; c < records.length; c++) {
                                    if (records[c].id == selected_row.org_id) {
                                        // Check the item for the current organization and disable turning off
                                        $access_orgs.jqxDropDownList('updateAt', {
                                            value: selected_row.org_id,
                                            label: records[c].name + " [owner]",
                                            checked: true
                                        }, c);
                                        $access_orgs.jqxDropDownList('checkIndex', c);
                                        $access_orgs.jqxDropDownList('disableAt', c);
                                    } else if ($.inArray(records[c].id.toString(), current_shares) > -1)
                                        // Turn on the rest considering current "shared_orgs" item in "selected_row"
                                        $access_orgs.jqxDropDownList('checkIndex', c);
                                }
                            }),
                            checkboxes: true,
                            displayMember: "name", valueMember: "id",
                            width: 428, height: 24, autoDropDownHeight: true,
                            renderer: function (index, label, value) {
                                var sho = master.dataSources.shareList.records[index];
                                label = "<span class='right'>(" + sho.type_id + ")</span>" + label;
                                if (sho.owner)
                                    label += " <b>[owner]</b>";
                                if (sho.disabled)
                                    label += " [disabled]";
                                return label;
                            }
                        });

                        // Setup the action for "OK" button
                        $('#modal_file-config_ok', $window).off('click').on('click', function ()
                        {
                            // submit the create/edit form using ajax
                            $('.modal_content > form', $window).ajaxSubmit({
                                data: {
                                    id: selected_row.id,
                                    link_id: master.config.id // ID check for link - The file is not updated if link_id is missing or wrong
                                },
                                dataType: 'json',
                                beforeSubmit: function (formData, jqForm, options) {
                                    // Checks if file name has not been emptied
                                    if ($("#file-name", $window).val().length < 5) {
                                        cargohub.alert("The name of the file should have at least 5 characters.");
                                        return false;
                                    }
                                    return true;
                                },
                                success: function (x) {
                                    if (x.success) {
                                        // note has been updated - Close the window / Refresh the datapanel / Update the tab title to include the returned number of notes (counter)
                                        $window.jqxWindow('close');
                                        master.refresh_DataTable($("#tab_panels div.jqx-datatable"));

                                        // Finally, show the operation result
                                        master.toolbar_Report("div#tab_panels", x.response);
                                    } else
                                        cargohub.its.http_fail(null, "Record.Files::config_file");
                                },
                                error: cargohub.its.http_fail
                            });
                        });

                        // Add tooltips
                        $('.info-tip', $window).each(cargohub.components.ui.add_Tooltip);
                    }
                });
            },
            /**
             * Setup the window used for pasting files (duplicate the sources) from the Clipboard into the current record
             */
            setup_Files_Clipboard = function ($window)
            {
                var
                        // DataAdapter for showing the clipboard content
                        fclip_dataAdapter = new $.jqx.dataAdapter({
                            url: "/files?action=Clipboard",
                            datatype: "json",
                            datafields: [
                                {name: 'file_id', type: 'int'},
                                {name: 'name', type: 'string'},
                                {name: 'mimetype', type: 'string'},
                                {name: 'doctype', type: 'string'},
                                {name: 'size', type: 'int'},
                                {name: 'chash', type: 'string'},
                                {name: 'added', type: 'date'}
                            ],
                            // Initial Sorting
                            sortcolumn: 'name',
                            sortdirection: 'asc'
                        }),
                        /**
                         * Unlinks (Removes) one or more existing clipboard files (clips)
                         *
                         * @param array ids
                         * @param callback on_Complete Extra function to execute after attachment has been unlinked
                         */
                        remove_From_Clipboard = function (ids, on_Complete)
                        {
                            $.post("/files?action=Remove_Clips", {
                                file_ids: JSON.stringify(ids)
                            }, function (x)
                            {
                                if (on_Complete)
                                    on_Complete(x);
                            })
                                    .fail(cargohub.its.http_fail);
                        },
                        /**
                         * Copies an existing metadata of a file into the current record
                         *
                         * @param int id
                         */
                        paste_File = function (clip)
                        {
                            $.post("?action=File_Paste", {
                                link_type: 'issue',
                                link_id: master.config.id, // Linked record ID
                                chash: clip.chash
                            }, function (x) {
                                // Nothign here; Grid is refreshed when the clipboard window is closed
                            })
                                    .fail(cargohub.its.http_fail);
                        },
                        pasted = false, // Becomes True on first paste (to refresh the grid on closing the Clipboard modal)
                        pasted_ids = [];	// Used to show the prompt on closing the window

                // #files-clipboard
                master.init_DataTable($("#files-clipboard", $window), {
                    // Config
                    localization: {
                        emptydatastring: "The clipboard is currently empty"
                    },
                    width: 720,
                    source: fclip_dataAdapter,
                    columns: [
                        {hidden: true, dataField: 'doctype', text: "File type", filterable: true}, // Searchable by document type
                        {dataField: 'mimetype', text: "Type", width: 50, filterable: false, align: "center", cellsAlign: "center", cellsRenderer: function (row, column, value, rowData) {
                                return '<img src="/img/mime/' + rowData.mimetype + '.png" alt="' + rowData.ext + '" />';
                            }},
                        {dataField: 'chash', text: "Down!", width: 50, align: "center", cellsAlign: "center", cellsRenderer: function (row, column, value, rowData) {
                                return '<a href="/files?action=download_attachment&ch=' + rowData.chash + '&qp=' + donrey.qp_conv(rowData.file_id) + '" title="Download this file"><img style="border: none;" src="/img/icons/download_cloud.png" alt="Down!" /></a>';
                            }, sortable: false, filterable: false},
                        {dataField: 'name', text: "File name", filterable: true},
                        {dataField: 'file_id', text: "Operations", width: 110, align: "center", cellsAlign: "center", cellsRenderer: function (row, column, value, rowData) {
                                return	'<img style="border: none;" src="/img/icons/page_paste.png" alt="Paste" class="pasteclip-button" data-row="' + row + '" title="Paste this file" /> &nbsp; ' +
                                        '<img style="border: none;" src="/img/icons/delete.png" alt="Del!" class="deleteclip-button" data-row="' + row + '" title="Remove from clipboard (non-destructive)" />';
                            }, sortable: false, filterable: false}
                    ],
                    rowDetails: true, initRowDetails: function (id, datarow, element, rowinfo) { // Row details renderer
                        var tpl_data = clone(datarow);
                        // Shiny...
                        tpl_data.size = number_format(tpl_data.size);
                        rowinfo.detailsHeight = 60;
                        element.addClass('datatable att-details').html($("#template-clipfile-details").html().present(tpl_data)).height(rowinfo.detailsHeight);
                    }
                }, {
                    // Callbacks (for events happening in the datatable itself)
                    "rowopen": function (row, $c) { // Opens the details on doubleclick
                        $c.jqxDataTable('showDetails', $c.data('selected_index'));
                    },
                    "getrows": function () {
                        if (fclip_dataAdapter.records.length > 0)
                            $("#fclipsize").show().text(" (" + fclip_dataAdapter.records.length + ")");
                        else
                            $("#fclipsize").text("").hide();
                    }
                }, "fclip");

                // Event for the attachment deletion button
                $("#files-clipboard")
                        /**
                         * The button to "Paste" the clipboard file into the record
                         */
                        .on("click", ".pasteclip-button", function () {
                            var row_data = fclip_dataAdapter.records[$(this).data('row')];

                            if (!row_data.original_name) { // Only do this once
                                paste_File(row_data);
                                row_data.original_name = row_data.name;
                                row_data.name = row_data.original_name + " <span class='gray'>(pasted)</span>";
                                pasted_ids.push(parseInt(row_data.file_id));

                                // Show the file as "Pasted"
                                $("#files-clipboard").jqxDataTable('updateRow', $("#files-clipboard").data('selected_index'), row_data);
                            } else
                                return cargohub.alert("The file <b>" + row_data.original_name + "</b> has already been pasted into the record");

                            pasted = true;
                        })
                        /**
                         * The button to "Paste" the clipboard file into the record
                         */
                        .on("click", ".deleteclip-button", function () {
                            if (!confirm("Are you sure you want to remove the file from Clipboard ?"))
                                return;

                            var file_id = fclip_dataAdapter.records[$(this).data('row')].file_id,
                                    srch = $.inArray(file_id, pasted_ids);
                            if (srch > -1)
                                pasted_ids.splice(srch, 1);

                            if (fclip_dataAdapter.records.length > 0)
                                $("#fclipsize").text(" (" + fclip_dataAdapter.records.length + ")");
                            else
                                $("#fclipsize").text("").hide();

                            remove_From_Clipboard([file_id], function () {
                                // Refresh the Clipboard after deletion
                                master.refresh_DataTable($("#files-clipboard", $window));
                            });
                        });

                // On Finished (Ok), the window is closed and the inline preview shows number of attachments and total size
                $('#modal_files-clipboard_ok').off('click').on('click', function ()
                {
                    if (pasted_ids.length > 0)
                        if (confirm(pasted_ids.length + " file(s) have been pasted in the record and are still in the Clipboard.\nDo you wish to remove them ?"))
                            remove_From_Clipboard(pasted_ids, function () {
                                var clips_left = fclip_dataAdapter.records.length - pasted_ids.length;
                                if (clips_left > 0)
                                    $("#fclipsize").text(" (" + clips_left + ")");
                                else
                                    $("#fclipsize").text("").hide();
                            });

                    // close the window
                    $window.jqxWindow('close');
                });

                // On Empty Clipboard button, ask for the mass unlink of clips for the current user
                $('#modal_files-clipboard_clear').off('click').on('click', function ()
                {
                    if (!confirm("Are you sure you want to empty the Clipboard ?"))
                        return;

                    // Empty the clipboard
                    $.post("/files?action=Remove_Clips", {
                        empty_clipboard: true
                    }, function (x)
                    {
                        $("#fclipsize").text("").hide();
                        $window.jqxWindow('close');

                        cargohub.alert("Clipboard has been emptied !", "Success !", "thumb_up.png");
                    })
                            .fail(cargohub.its.http_fail);
                });

                $window.on('close', function () {
                    if (pasted)
                        master.refresh_DataTable($("#tab_panels div.jqx-datatable"));
                });
            },
            /**
             * Initialize the Docs tab toolbar (Add, Edit, Copy, Export, Delete documents) - Future: Import documents via uploading (should recognize MS Word?)
             */
            init_Toolbar = function ()
            {
                    $("#tab_trainee .toolbar button")
                    .jqxButton({theme: cargohub.jqxtheme})
                    /**
                     * Event: Toolbar button is clicked
                     */
                    .on('click', function ()
                    {
                        console.log('case 3736 ', $(this).data('op'));
                        switch ($(this).data('op')) {

                            case 'exportxlsx':
                                $("#_record-docs").jqxDataTable('exportData', 'xls');
                                break;
                                // Create new blank document command - Initializes the editor in create mode
                            case "trainee-create":
                                //23082024 added by iwi | start
                                var currentTotalTrainee = $('#total_trainee_count').text();
                                var maxTraineeValue = $('#total_trainee_count').data('max_trainee_value');
                                currentTotalTrainee = parseInt(currentTotalTrainee, 10);
                                maxTraineeValue = parseInt(maxTraineeValue, 10);
                                if(currentTotalTrainee >= maxTraineeValue){
                                    close_Editor();
                                    cargohub.alert("You have reached the maximum number of trainees.");
                                    return false;
                                }
                                // 23082024 added by iwi | end


                                document.getElementById("configform").reset();
                                $(".internal_type_list").hide();
                                $(".training_date_div").html("");
                                $("#trainee_management_id").val(0);
                                //31072024 added by iwi | start
                                    //$(".training_date_div").html("");
                                    //$(".training_date_div").html("<div id='xfd_date_train' style='width: 400px;'  class='form-control calendar training_date' name='training_date' data-value=''></div>");
                                
                                // 28082024 changes by iwi

                                    var training_scheduler_dt = ($('.train_scheduler_dt').text() == '0000-00-00' || $('.train_scheduler_dt').text() == '' || $('.train_scheduler_dt').text() == '---') ? '' : $('.train_scheduler_dt').text();
                                    // $(".training_date_div").html("<div id='xfd_date_train' style='width: 400px;'  class='form-control calendar training_date' name='training_date' data-value='"+training_scheduler_dt+"'></div>");
                                    $(".training_date_div").html("<label>"+training_scheduler_dt+"</label>");
                                    $("#training_date").val(training_scheduler_dt);
                                // 28082024 changes by iwi
                                
                                
                                    // 31072024 added by iwi | end
                                $("#labdownload").css("display", "none");
                                $("#download_link").css("display", "none");
                                master.clear_DataTable($("#tab_trainee div.jqx-datatable")); // Also unselect row if one is selected (which hides unneeded buttons)
                                open_row_id = null;
                                copy_mode = false;

                                if ($trainee_viewer && viewer_open)
                                    close_Viewer(true);

                                $('#tab_trainee .docs-write-ops').fadeIn(); // Make sure Save is enabled
                                init_Editor({
                                    title: "Create a Trainee",
                                    icon: "add.png",
                                    mode: "create"
                                });
                                $('#tab_trainee #trainee-create-op').fadeOut(); // Hide the "Create" button when the editor is active, will be shown again on Save or Close

                                break;
                                // Create a copy of the existing open document, which must be saved under a new name - by default, the system adds " (copy)" to the existing name
                            case "trainee-copy":
                                open_row_id = selected_row.uid;
                                copy_mode = true; // ID is ignored, so a new document is created instead

                                if ($trainee_viewer && viewer_open)
                                    close_Viewer(true);

                                $('#tab_trainee .docs-write-ops').fadeIn(); // Make sure Save is enabled
                                init_Editor({
                                    title: "Create copy of document: " + selected_row.name,
                                    icon: "page_copy.png",
                                    mode: "edit",
                                    overrides: {
                                        "name": selected_row.name + " (copy)",
                                        "version": 1 // It's first version of the copy !
                                    }
                                });
                                $('#tab_trainee #trainee-create-op').fadeOut();
                                break;

                                // Open document for editing or viewing (determined inside the open_Document function)
                            case "trainee-open":
                                open_row_id = selected_row.uid;
                                console.log('2009 open_row_id', open_row_id);
                                open_Document();
                                break;

                                // Temporary - Test of Viewer
                            case "trainee-view":
                                if (editor_open)
                                    close_Editor(true);
                                $('#tab_trainee .docs-write-ops').hide(); // No "Save" function in View mode
                                $('#tab_trainee #trainee-open-ops').fadeIn();

                                view_Document({
                                    title: "Viewing document: " + selected_row.name
                                });

                                $('#tab_trainee #trainee-create-op').fadeOut();
                                break;

                                // Opens the document in a new window/tab in Webview mode
                                // case "trainee-webview-newwin":
                                //     var win = window.open("/?action=document&qp=" + donrey.qp_conv(selected_row.uid) + "&key=" + selected_row.wv_key, '_blank');
                                //     win.focus();
                                //     break;

                                // Deletes existing document (Manager privilege is required)
                            case "trainee-delete": //
                                if (!confirm("Are you sure you want to delete this document ?"))
                                    return;
                                delete_Document();
                                break;

                                // Save existing editor content (If no changes is done, a warning is thrown, to avoid unnecessary database operations/logging)
                            case 'trainee-save':
                                save_Editor();
                                break;

                                // Save with Version update
                                // case 'trainee-upver':
                                //     save_Editor(true);
                                //     break;

                                // Closes the Editor without saving (discards existing content - Warns if new or existing document has been changed, or when copy mode is active)
                                // Close the Viewer when document is opened in View-only mode
                            case 'trainee-close':
                                if (editor_open)
                                    close_Editor();
                                // if (viewer_open)
                                //     close_Viewer();
                                break;


                            // 21112024 Training Scheduler Training Date section work
                            case 'trainee-result-update':
                                direct_result_update();
                                break;
                            // 13032025 Training Scheduler table user email
                                case 'trainee-email-copy':
                                trainee_email_copy();
                                break;



                        }
                    });
                   


                        $("#files-editops button")
                        .jqxButton({theme: cargohub.jqxtheme})
                        /**
                         * Event: Toolbar button is clicked
                         */
                        .on('click', function ()
                        {
                            console.log('case', $(this).data('op'));
                            switch ($(this).data('op')) {
                                case "upload":
                                    files_Upload();
                                    break;
                                case "file-config":
                                    file_Config();
                                    break;
                                    // Direct operation, file is being linked into Clipboard (unless it's already there)
                                case "file-copy":
                                    $.post("?action=File_CopyClip", {
                                        link_id: master.config.id, // Linked record ID
                                        chash: selected_row.chash
                                    }, function (x) {
                                        // New clipboard size returned, make visible and update the number beside the "Clipboard" button
                                        if (x.fclipsize)
                                            $("#fclipsize").text(" (" + x.fclipsize + ")").show();
                                    })
                                            .fail(cargohub.its.http_fail);
                                    break;
                                case "file-remove":
                                    if (!confirm("Are you sure you want to delete the selected attached file?"))
                                        return;

                                    // Local Action method for now (the function needs to be aware of where the file is disconnected from)
                                    $.post("?action=Delete_File", {
                                        link_id: master.config.id, // Linked record ID
                                        chash: selected_row.chash,
                                        org_id: master.config.perspective// Current organization (perspective for admin - Not useful for non-admin as it will default to the user's organization anyway)
                                    }, function (x) {
                                        master.refresh_DataTable($("#tab_panels div.jqx-datatable"));
                                        // Update counter on deletion (should be reduced by one compared to current if deleting from the perspective organization)
                                        if (x.update_count)
                                            cargohub.components.hashnav.update_Tab_Header(3, "Files" + (x.count ? " <span class='fiftycent'>[" + x.count + "]</span>" : ""), "/img/icons16/attach.png");
                                        // Update clipboard size (just in case this file was in the clipboard)
                                        if (x.fclipsize)
                                            $("#fclipsize").text(" (" + x.fclipsize + ")").show();
                                        cargohub.alert(x.response, "Success !", "thumb_up.png");
                                    })
                                            .fail(cargohub.its.http_fail);
                                    break;

                                case "clipboard":
                                    cargohub.components.modal.create('files-clipboard', {
                                        buttons: {clear: "Empty Clipboard and Close", ok: "Finished"},
                                        btn_roles: {},
                                        showCloseButton: false,
                                        btn_styles: {clear: "float: left; margin-left: 0; background-image: none; background-color: #C40; color: white;"}
                                    });

                                    cargohub.components.modal.open('files-clipboard', {
                                        width: 735, height: 470,
                                        title: "Files Clipboard",
                                        content: $('#win-files_clipboard').html().present({
                                            _itc: cargohub.input_theme_classes
                                        }),
                                        callback: setup_Files_Clipboard
                                    });
                                    break;
                            }
                        });
                if (edit_privilege)
                    $('#tab_panels #files-editops').fadeIn();

                // Checkbox that adds "classic" notes to the Documents grid (since they are really the same table) - Once a note is saved as a document, it's not visible anymore in notes
                // Only user with Manage access can convert Notes into Docs (as it's equivalent to a Delete+Create operation)
                if (docs_manager_priv) {
                    $('#tab_trainee #trainee-import').jqxCheckBox({
                        theme: cargohub.jqxtheme,
                        height: 18
                    })
                            .show()
                            .on('change', function (e) {
                                if (e.args.checked)
                                    docs_Source._source.data.content_type = 'plain';
                                else
                                    docs_Source._source.data.content_type = 'html';

                                // Reload the documents table when the checkbox is changed
                                master.refresh_DataTable($("#tab_trainee div.jqx-datatable"));
                            })
                            .find("div.jqx-checkbox-default").css('margin-top', "2px");
                }
            },
            init_audit_activity_Toolbar = function ()
            {
                //  15102024 Audit control add  extra sec start
                $("#tab_audit_activity .toolbar button").jqxButton({theme: cargohub.jqxtheme}).on('click', function (){
                    console.log('case', $(this).data('op'));
                    switch ($(this).data('op')) {
                        case 'exportxlsx':
                            if($("#_audit_activity_record").length>0){
                            $("#_audit_activity_record").jqxDataTable('exportData', 'xls');
                        }
                            break;
                        case 'audit-activity-create':
                            $('#tab_audit_activity').fadeIn(); // Make sure Save is enabled
                            console.log(" 2623 disabled remove ");
                              $(".audit-ctime").hide(); //created time added to capa
                              $(".audit-person").hide(); // user added to capa
                            $('#isCreateTask').prop('disabled', false);
                            initAuditActivityEditor({
                                    title: "Create a Activity",
                                    icon: "add.png",
                                    mode: "create_audit_activity"
                                });
                                $('#tab_audit_activity #audit-activity-create-op').fadeOut(); // Hide the "Create" button when the editor is active, will be shown again on Save or Close

                        break;
                        case 'audit-activity-delete':
                            // if (!confirm("Are you sure you want to delete this document ?"))
                            // return;
                            cargohub.components.modal.delcustomconfirm(initAuditActivityDelete, "Are you sure you want to remove this activity ?",null,null,null,null,'delete');

                            // initAuditActivityDelete();
                            break;
                        case 'audit-activity-save':
                            initAuditActivitySave();                           
                        break;
                        case 'audit-activity-close':

                                // if (editor_open)
                                // initAuditActivityViewerClose();                          
                                // break;

                            if (!$audit_activity_editor) // Not yet initialized
                            return;
                            if ($audit_activity_editor.jqxExpander('expanded')) {
                                $audit_activity_editor.on('collapsed', function () {
                                    // When finishing collapse... hide the editor
                                    $audit_activity_editor.hide();
                                    // ... and disable the event
                                    $audit_activity_editor.off('collapsed');
                                });
                                $audit_activity_editor.jqxExpander('collapse');
                            } else {
                                // If already collapsed, just hide it
                                $audit_activity_editor.hide();
                            }

                            // Also fadeout the Save/Close buttons
                            $('#tab_audit_activity #audit-activity-open-ops button').jqxButton({disabled: true});
                            $('#tab_audit_activity #audit-activity-open-ops').fadeOut();
                            $('#tab_audit_activity #audit-activity-delete').fadeOut();


                            // $('#tab_audit_activity #audit-activity-row-ops').fadeIn();
                            $('#tab_audit_activity #audit-activity-create-op').fadeIn();

                            // if (!transition) {
                            //     if (selected_row)
                            //     $('#tab_audit_activity #audit-activity-row-ops').fadeIn();
                            //     // Show the "Create" button in case it was hidden by a previous operation
                            //     $('#tab_audit_activity #audit-activity-create-op').fadeIn();
                            // }

                            // editor_nonempty = false;
                            // editor_open = false;
                            open_row_id = null;
                            // copy_mode = false;
                            return true;
                    }
                });
                //  15102024 Audit control add  extra sec end
            },
            


            // 27112024 Customer Inquiry -> CAPA sec added by iwi start
            init_capa_sec_by_customer_inquiry_Toolbar = function ()
            {
                $("#capa_sec_by_customer_inquiry .toolbar button").jqxButton({theme: cargohub.jqxtheme}).on('click', function (){
                    console.log('case', $(this).data('op'));
                    switch ($(this).data('op')) {
                        case 'exportxlsx':
                            $("#_audit_activity_record").jqxDataTable('exportData', 'xls');
                            break;
                        case 'capa-by-customer-inq-create':
                            $('#capa_sec_by_customer_inquiry').fadeIn(); // Make sure Save is enabled
                            $(".audit-ctime").hide(); //created time added to capa
                            $(".audit-person").hide(); // user added to capa
                            $('#isCreateTask').prop('disabled', false);
                            initCapaSecByCustomerInqEditor({
                                    title: "Create a Activity",
                                    icon: "add.png",
                                    mode: "create_audit_activity"
                                });
                            $('#capa_sec_by_customer_inquiry #capa-by-customer-inq-create-op').fadeOut(); // Hide the "Create" button when the editor is active, will be shown again on Save or Close

                        break;
                        case 'capa-by-customer-inq-delete':
                            cargohub.components.modal.delcustomconfirm(initCapaSecByCustomerInqDelete, "Are you sure you want to remove this activity ?",null,null,null,null,'delete');
                            break;
                        case 'capa-by-customer-inq-save':
                            initCapaSecByCustomerInqSave();                           
                        break;
                        case 'capa-by-customer-inq-close':

                            if (!$capa_sec_by_customer_inquiry_editor) // Not yet initialized
                            return;
                            if ($capa_sec_by_customer_inquiry_editor.jqxExpander('expanded')) {
                                $capa_sec_by_customer_inquiry_editor.on('collapsed', function () {
                                    // When finishing collapse... hide the editor
                                    $capa_sec_by_customer_inquiry_editor.hide();
                                    // ... and disable the event
                                    $capa_sec_by_customer_inquiry_editor.off('collapsed');
                                });
                                $capa_sec_by_customer_inquiry_editor.jqxExpander('collapse');
                            } else {
                                // If already collapsed, just hide it
                                $capa_sec_by_customer_inquiry_editor.hide();
                            }

                            // Also fadeout the Save/Close buttons
                            $('#capa_sec_by_customer_inquiry #capa-by-customer-inq-open-ops button').jqxButton({disabled: true});
                            $('#capa_sec_by_customer_inquiry #capa-by-customer-inq-open-ops').fadeOut();
                            $('#capa_sec_by_customer_inquiry #capa-by-customer-inq-delete').fadeOut();
                            $('#capa_sec_by_customer_inquiry #capa-by-customer-inq-create-op').fadeIn();
                            open_row_id = null;
                            // copy_mode = false;
                            return true;
                    }
                });
            },
            // 27112024 Customer Inquiry -> CAPA sec added by iwi end







            // 28112024 Quality Event -> CAPA sec added by iwi start
            init_capa_sec_by_quality_event_Toolbar = function ()
            {
                $("#capa_sec_by_quality_event .toolbar button").jqxButton({theme: cargohub.jqxtheme}).on('click', function (){
                     
                     switch ($(this).data('op')) {
                         case 'exportxlsx':
                             $("#_audit_activity_record").jqxDataTable('exportData', 'xls');
                             break;
                         case 'capa-by-quality-event-create':
                             $('#capa_sec_by_quality_event').fadeIn(); // Make sure Save is enabled
                              $(".audit-ctime").hide(); //created time added to capa
                              $(".audit-person").hide(); // user added to capa
                             $('#isCreateTask').prop('disabled', false);
                             initCapaSecByQualityEventEditor({
                                     title: "Create a Activity",
                                     icon: "add.png",
                                     mode: "create_audit_activity"
                                 });
                             $('#capa_sec_by_quality_event #capa-by-quality-event-create-op').fadeOut(); // Hide the "Create" button when the editor is active, will be shown again on Save or Close
 
                         break;
                         case 'capa-by-quality-event-delete':
                             cargohub.components.modal.delcustomconfirm(initCapaSecByQualityEventDelete, "Are you sure you want to remove this CaPa record ?",null,null,null,null,'delete');
                             break;
                         case 'capa-by-quality-event-save':
                            initCapaSecByQualityEventSave();                           
                         break;
                         case 'capa-by-quality-event-close':
                                 
                             if (!$capa_sec_by_quality_event_editor) // Not yet initialized
                             return;
                             if ($capa_sec_by_quality_event_editor.jqxExpander('expanded')) {
                                 $capa_sec_by_quality_event_editor.on('collapsed', function () {
                                     // When finishing collapse... hide the editor
                                     $capa_sec_by_quality_event_editor.hide();
                                     // ... and disable the event
                                     $capa_sec_by_quality_event_editor.off('collapsed');
                                 });
                                 $capa_sec_by_quality_event_editor.jqxExpander('collapse');
                             } else {
                                 // If already collapsed, just hide it
                                 $capa_sec_by_quality_event_editor.hide();
                             }
 
                             // Also fadeout the Save/Close buttons
                             $('#capa_sec_by_quality_event #capa-by-quality-event-open-ops button').jqxButton({disabled: true});
                             $('#capa_sec_by_quality_event #capa-by-quality-event-open-ops').fadeOut();
                             $('#capa_sec_by_quality_event #capa-by-quality-event-delete').fadeOut();
                             $('#capa_sec_by_quality_event #capa-by-quality-event-create-op').fadeIn();
                             open_row_id = null;
                             // copy_mode = false;
                             return true;
                     }
                });
            },
            // 28112024 Quality Event -> CAPA sec added by iwi end
 


            /**
             * Initialize the Documents DataTable. (This uses common functionality from the main Record Process module - cargohub.record_process )
             */
            init_DataTable = function ()
            {
                // console.log('sdds',docs_Source);
                
                /**
                 * Highlights the background of each cell in a row (effectively highlighting the row itself) to indicate the conditions:
                 * - Shared note: Yellow
                 * - User private note: Pale Red
                 * Current perspective orgaization will have all it's text with "italic" style (Admin only)
                 */
                var cell_highlight = function (row, column, value, rowData) {
                    var classname = "";

                    if (rowData.visibility == 'shared' || rowData.visibility == 'public')
                        classname += "grid-cell-shared";
                    else if (rowData.visibility == 'private')
                        classname += "grid-cell-private";

                    // Mark notes for the perspective org for the admin user
                    if (cargohub.application.user_Is_Admin() && rowData.org_id == master.config.perspective)
                        classname += " grid-cell-perspective_org";
                    return classname;
                };
                master.init_DataTable($("#tab_trainee div.jqx-datatable"), {
                    exportSettings: {
                        columnsHeader: true,
                        hiddenColumns: false,
                        serverURL: "/?action=export",
                        characterSet: null,
                        collapsedRecords: false,
                        recordsInView: true,
                        fileName: "docs"
                    },
                    // filterable: true, // Enable filtering
                    pageable: true,
                    pageSizeOptions: [5, 10, 20, 25, 100],
                    pageSize: 20,
                    height: 'auto',
                    // rowNum: 5,
                    // rowList: [5, 10, 20, 30],
                    // viewrecords: true,
                    // autoheight: true,                  
                    // 
                    // Config
                    source: docs_Source, 
                    columns: [
                        //21112024 Training Scheduler Training Date section work 
                        { text: "#", dataField: "select", width: 80, cellsRenderer: function (row, column, value, rowData) {
                            return `<input type="checkbox" class="row-checkbox" data-row="${row}" />`;
                        }},
                        {dataField: 'name', text: "Trainee Name", cellClassName: cell_highlight, 'width': '15%'}, //// 28052024 remove number and email
                        {dataField: 'email_lable', text: "Email", cellClassName: cell_highlight, 'width': '5%'}, //05032025 add email field by Training Scheduler
                        {dataField: 'url', text: "Url", cellClassName: cell_highlight, 'width': '5%'}, //// 28082024
                        {dataField: 'training_date', text: "Training Date", cellClassName: cell_highlight, 'width': '10%'},//26092024
                        {dataField: 'training_expiry_date', text: "Expiry Date", cellClassName: cell_highlight, 'width': '10%'}, //26092024
                        {dataField: 'company_name', text: "Company Name", cellClassName: cell_highlight, 'width': '15%'},
                        {dataField: 'classroom_type', text: "Classroom", cellClassName: cell_highlight, 'width': '8%'},//26092024  train_scheduler_classroom_type  addded by iwi start
                        //{dataField: 'result', text: "Result123", cellClassName: cell_highlight, 'width': '8%'},
                        
                        {
                            text: "Result",  // The column header text
                            dataField: 'result',  // The field to filter
                            width: 105,
                            filterable: true,  // Enable filtering for this column
                            filtertype: 'checkedlist',  // List dropdown filter type
                            filteritems: ['Pass', 'Fail'],  // Values in the dropdown list for filtering

                            // Create the filter widget
                            createfilterwidget: function (column, columnElement, widget) {
                                widget.jqxDropDownList({
                                    source: ['Pass', 'Fail'],  // The options for the dropdown list
                                    dropDownWidth: 120,
                                    autoDropDownHeight: true, 
                                    animationType: "fade",
                                    promptText: "Select Result"
                                });
                            },

                            // Initialize the dropdown list filter behavior
                            initwidget: function (column, columnElement, widget) {
                                widget.on('select', function (event) {
                                    // Get the selected value from the dropdown
                                    var selectedValue = event.args.item.value;

                                    // Apply the filter to the result column based on the selected value
                                    var filter = { field: "result", operator: "equal", value: selectedValue };
                                    $("#tab_trainee #_record-docs").jqxGrid('addfilter', 'result', filter);
                                    $("#tab_trainee #_record-docs").jqxGrid('applyfilters');
                                });
                            }
                        },
                        
                        // 06022025 Training Scheduler tranee grid add filter
                        {dataField: 'score', text: "Score (%)", cellClassName: cell_highlight, 'width': '8%'},
                        {dataField: 'files', text: "Certificate", cellClassName: cell_highlight, 'width': '10%'},
                        {dataField: 'trainee_type_lable', text: "Trainee Type", cellClassName: cell_highlight, 'width': '8%'},
                    ],
                   
                    //  pageable: true,
                    //  pageSize: 5
                    rowDetails: true, initRowDetails: function (id, datarow, element, rowinfo) { // Row details renderer
                        var tpl_data = clone(datarow), metadata = null;
                        // Process labels, by splitting them, so they can be colorized in the template
                        if (tpl_data.labels) {
                            tpl_data.labels = tpl_data.labels.split(",");
                            for (c = 0; c < tpl_data.labels.length; c++)
                                tpl_data.labels[c] = "<span class='attlabel'>" + $.trim(tpl_data.labels[c]) + "</span>";
                            tpl_data.labels = tpl_data.labels.join(" &middot; ");
                        } else
                            delete tpl_data.labels;
                        // Shiny...
                        tpl_data.size = number_format(tpl_data.size);
                        switch (tpl_data.visibility) {
                            case 'private':
                                tpl_data.visi_text = "Private file, visible only to the uploader";
                                break;
                            case 'dep':
                                tpl_data.visi_text = "Uploader's department members";
                                break;
                            case 'org':
                                tpl_data.visi_text = "The uploader's organization";
                                break;
                            case 'shared':
                                tpl_data.visi_text = "Sharing this " + master.config.type.name;
                                if (tpl_data.access_orgs)
                                    tpl_data.visi_text += " (" + tpl_data.access_orgs.split(",").length + " organizations)";
                                break;
                            case 'public':
                                tpl_data.visi_text = "No access restrictions";
                                break;
                            default:
                                tpl_data.visi_text = "(Unknown, possible database error)";
                        }

                        // If the row contains image metadata (and the analyzer is of type "image" it means we have a thumbnail to display - use the "thms" version
                        if (datarow.analyzer == "image") {
                            metadata = JSON.parse(datarow.metadata);
                            if (metadata && metadata.scaler && metadata.scaler.thml)
                                tpl_data._thml = "/uimg/thml/" + datarow.chash.substr(0, 2) + '/' + datarow.chash.substr(2) + '.jpg';
                            rowinfo.detailsHeight = metadata.scaler.thml.height;
                        } else
                            rowinfo.detailsHeight = 100;
                        element.addClass('datatable att-details').html($("#template-file-details").html().present(tpl_data)).height(rowinfo.detailsHeight);
                    },
                    
                },
                {
                    // Callbacks (for events happening in the datatable itself) - In most cases it will be either View or Edit
                    "getrows": function () {},
                    "rowselect": select_Row,
                    "clearselect": select_Clear,
                    "rowopen": open_Document
                }, "docs");
                
                
            },
            // 15102024 Audit control add  extra sec start
            init_DataTable1 = function (){

                var cell_audit_highlight = function (row, column, value, rowData) {
                    var classname = "";
                
                    // if (rowData.visibility == 'shared' || rowData.visibility == 'public')
                    //     classname += "grid-cell-shared";
                    // else if (rowData.visibility == 'private')
                    //     classname += "grid-cell-private";
                
                    // // Mark notes for the perspective org for the admin user
                    // if (cargohub.application.user_Is_Admin() && rowData.org_id == master.config.perspective)
                    //     classname += " grid-cell-perspective_org";
                    return classname;
                };
                master.init_DataTable($("#tab_audit_activity div.jqx-datatable"), {
                    exportSettings: {
                        columnsHeader: true,
                        hiddenColumns: false,
                        serverURL: "/?action=export",
                        characterSet: null,
                        collapsedRecords: false,
                        recordsInView: true,
                        fileName: "docs1"
                    },
                    // Config
                    source: panel_audit_activity_Source,
                    // 23102024 Audit control changes start
                    columns: [
                        // 19122024 position  changes by iwi | start
                        {dataField: 'ID', text: "ID", cellClassName: cell_audit_highlight, 'width': '5%'},
                        {dataField: 'title', text: "Title", cellClassName: cell_audit_highlight, 'width': '40%'},  //IWI Quality event record panel section Investigation modification 08122025  //iwi capa section enhancement
                        {dataField: 'action_type', text: "Action Type", cellClassName: cell_audit_highlight, 'width': '10%'}, //IWI Quality event record panel section Investigation modification 08122025 
                        {dataField: 'type', text: "Type", cellClassName: cell_audit_highlight, 'width': '10%'},
                        // {dataField: 'risk', text: "Risk", cellClassName: cell_audit_highlight, 'width': '10%'},
                        {                                                             //risk_color_enhance
                            dataField: 'risk',
                            text: "Risk",
                            cellClassName: cell_audit_highlight,
                            width: '10%',
                            cellsrenderer: function (row, columnfield, value, defaultHtml, columnProperties, rowData) {
                                if (!value) value = '';
                                let bgColor = '';
                                switch (value.trim().toLowerCase()) {
                                    case 'critical':
                                          bgColor = 'rgba(29, 168, 81, 0.28)';//IWI Quality event record panel section Investigation modification 08122025  
                                        break;
                                    case 'major':
                                        bgColor = 'rgba(255, 152, 0, 0.28)';//IWI Quality event record panel section Investigation modification 08122025  
                                        break;
                                    case 'minor':
                                        bgColor = 'rgba(233, 27, 0, 0.28)';//IWI Quality event record panel section Investigation modification 08122025 
                                        break; 
                                    default:
                                        bgColor = 'transparent';
                                }
                                 return `<div style="width:100%; height:100%; background-color:${bgColor}; color:black; font-weight:bold; display:flex; align-items:center; justify-content:left; padding-left:4px;">${value}</div>`;
                            }
                        },
                        {dataField: 'person', text: "Assigned To", cellClassName: cell_audit_highlight, 'width': '20%'},// 08112024 Audit control changes
                        {dataField: 'deadline_date', text: "Deadline", cellClassName: cell_audit_highlight, 'width': '10%'},
                        // 26122024 form and grid section changes | start   
                        {dataField: 'status', text: "Status", cellClassName: cell_audit_highlight, 
                        cellsrenderer: function (row, columnfield, value, defaultHtml, columnProperties, rowData) {
                            if (value == 'active') {
                                return 'started';
                            }else{
                                return value;
                            }
                        },
                        'width': '7%'},
                        // 26122024 form and grid section changes | end
                        {dataField: 'closing_date', text: "Closing Date", cellClassName: cell_audit_highlight, 'width': '10%'},
                        {dataField: 'result', text: "Area", cellClassName: cell_audit_highlight, 'width': '10%'},
                        
                        {dataField: 'comments', text: "Root Cause", cellClassName: cell_audit_highlight,'width': '25%'},
                        {dataField: 'improvement', text: "CaPa", cellClassName: cell_audit_highlight, 'width': '25%'},
                        {dataField: 'reference_no', text: "Reference No", cellClassName: cell_audit_highlight, 'width': '5%'},
                        {dataField: 'isCreateTaskLable', text: "Task", cellClassName: cell_audit_highlight, 'width': '8%'},
                        {dataField: 'dsc', text: "Description", cellClassName: cell_audit_highlight, 'width': '25%'},
                        // 19122024 position  changes by iwi | start
                    ]
                    // 23102024 Audit control changes end
                }, 
                {
                    "rowselect": initAuditActivitySelectRow,
                    "clearselect": select_Clear,
                    "rowopen": initAuditActivityOpen
                }, "docs1");

                
            }
            // 15102024 Audit control add  extra sec end


            // 27112024 Customer Inquiry -> CAPA sec added by iwi start
            init_DataTable2 = function (){

                var cell_audit_highlight = function (row, column, value, rowData) {
                    var classname = "";

                    return classname;
                };
                master.init_DataTable($("#capa_sec_by_customer_inquiry div.jqx-datatable"), {
                    exportSettings: {
                        columnsHeader: true,
                        hiddenColumns: false,
                        serverURL: "/?action=export",
                        characterSet: null,
                        collapsedRecords: false,
                        recordsInView: true,
                        fileName: "docs1"
                    },
                    // Config
                    source: panel_capa_sec_by_customer_inquiry_Source,
                    columns: [
                        // 19122024 position  changes by iwi | start
                        {dataField: 'ID', text: "ID", cellClassName: cell_audit_highlight, 'width': '5%'},
                        {dataField: 'title', text: "Title", cellClassName: cell_audit_highlight, 'width': '40%'},//IWI Quality event record panel section Investigation modification 08122025 
                        {dataField: 'action_type', text: "Action Type", cellClassName: cell_audit_highlight, 'width': '10%'}, //IWI Quality event record panel section Investigation modification 08122025 
                        {dataField: 'type', text: "Type", cellClassName: cell_audit_highlight, 'width': '10%'},
                        // {dataField: 'risk', text: "Risk", cellClassName: cell_audit_highlight, 'width': '10%'},
                       {                                                             //risk_color_enhance
                            dataField: 'risk',
                            text: "Risk",
                            cellClassName: cell_audit_highlight,
                            width: '10%',
                            cellsrenderer: function (row, columnfield, value, defaultHtml, columnProperties, rowData) {
                                if (!value) value = '';
                                let bgColor = '';
                                switch (value.trim().toLowerCase()) {
                                    case 'critical':
                                         bgColor = 'rgba(29, 168, 81, 0.28)';//IWI Quality event record panel section Investigation modification 08122025  
                                        break;
                                    case 'major':
                                        bgColor = 'rgba(255, 152, 0, 0.28)';//IWI Quality event record panel section Investigation modification 08122025  
                                        break;
                                    case 'minor':
                                        bgColor = 'rgba(233, 27, 0, 0.28)';//IWI Quality event record panel section Investigation modification 08122025  
                                        break; 
                                    default:
                                        bgColor = 'transparent';
                                }
                                 return `<div style="width:100%; height:100%; background-color:${bgColor}; color:black; font-weight:bold; display:flex; align-items:center; justify-content:left; padding-left:4px;">${value}</div>`;
                            }
                        },
                        {dataField: 'person', text: "Assigned To", cellClassName: cell_audit_highlight, 'width': '20%'},// 08112024 Audit control changes
                        {dataField: 'deadline_date', text: "Deadline", cellClassName: cell_audit_highlight, 'width': '10%'},
                        // 26122024 form and grid section changes | start   
                        {dataField: 'status', text: "Status", cellClassName: cell_audit_highlight, 
                        cellsrenderer: function (row, columnfield, value, defaultHtml, columnProperties, rowData) {
                            if (value == 'active') {
                                return 'started';
                            }else{
                                return value;
                            }
                        },
                        'width': '7%'},
                        // 26122024 form and grid section changes | end
                        {dataField: 'closing_date', text: "Closing Date", cellClassName: cell_audit_highlight, 'width': '10%'},
                        {dataField: 'result', text: "Area", cellClassName: cell_audit_highlight, 'width': '10%'},
                     
                        {dataField: 'comments', text: "Root Cause", cellClassName: cell_audit_highlight,'width': '25%'},
                        {dataField: 'improvement', text: "CaPa", cellClassName: cell_audit_highlight, 'width': '25%'},
                        {dataField: 'reference_no', text: "Reference No", cellClassName: cell_audit_highlight, 'width': '5%'},
                        {dataField: 'isCreateTaskLable', text: "Task", cellClassName: cell_audit_highlight, 'width': '8%'},
                        {dataField: 'dsc', text: "Description", cellClassName: cell_audit_highlight, 'width': '25%'},
                        // 19122024 position  changes by iwi | start
                    ]
                }, 
                {
                    "rowselect": initCapaSecByCustomerInqSelectRow,
                    "clearselect": select_Clear,
                    "rowopen": initCapaSecByCustomerInqOpen
                }, "docs1");
            }
            // 27112024 Customer Inquiry -> CAPA sec added by iwi end



            // 28112024 Quality Event -> CAPA sec added by iwi start
            init_DataTable3 = function (){

                var cell_audit_highlight = function (row, column, value, rowData) {
                    var classname = "";

                    return classname;
                };
                master.init_DataTable($("#capa_sec_by_quality_event div.jqx-datatable"), {
                    exportSettings: {
                        columnsHeader: true,
                        hiddenColumns: false,
                        serverURL: "/?action=export",
                        characterSet: null,
                        collapsedRecords: false,
                        recordsInView: true,
                        fileName: "docs1"
                    },
                    // Config
                    source: panel_capa_sec_by_quality_event_Source,
                    columns: [
                        // 19122024 position  changes by iwi | start
                        {dataField: 'ID', text: "ID", cellClassName: cell_audit_highlight, 'width': '5%'},
                        {dataField: 'title', text: "Title", cellClassName: cell_audit_highlight, 'width': '35%'}, //IWI Quality event record panel section Investigation modification 08122025  
                        {dataField: 'action_type', text: "Action Type", cellClassName: cell_audit_highlight, 'width': '10%'}, //IWI Quality event record panel section Investigation modification 08122025  
                        {dataField: 'type', text: "Type", cellClassName: cell_audit_highlight, 'width': '10%'},
                        // {dataField: 'risk', text: "Risk", cellClassName: cell_audit_highlight, 'width': '10%'},
                        {                                                             //risk_color_enhance
                            dataField: 'risk',
                            text: "Risk",
                            cellClassName: cell_audit_highlight,
                            width: '10%',
                            cellsrenderer: function (row, columnfield, value, defaultHtml, columnProperties, rowData) {
                                if (!value) value = '';
                                let bgColor = '';
                                switch (value.trim().toLowerCase()) {
                                    case 'critical':
                                        bgColor = 'rgba(29, 168, 81, 0.28)';//IWI Quality event record panel section Investigation modification 08122025  
                                        break;
                                    case 'major':
                                        bgColor = 'rgba(255, 152, 0, 0.28)';//IWI Quality event record panel section Investigation modification 08122025  
                                        break;
                                    case 'minor':
                                        bgColor = 'rgba(233, 27, 0, 0.28)';//IWI Quality event record panel section Investigation modification 08122025  
                                        break; 
                                    default:
                                        bgColor = 'transparent';
                                }
                                 return `<div style="width:100%; height:100%; background-color:${bgColor}; color:black; font-weight:bold; display:flex; align-items:center; justify-content:left; padding-left:4px;">${value}</div>`;
                            }
                        },
                        {dataField: 'person', text: "Assigned To", cellClassName: cell_audit_highlight, 'width': '20%'},// 08112024 Audit control changes
                        {dataField: 'deadline_date', text: "Deadline", cellClassName: cell_audit_highlight, 'width': '10%'},
                        // 26122024 form and grid section changes | start   
                        {dataField: 'status', text: "Status", cellClassName: cell_audit_highlight, 
                        cellsrenderer: function (row, columnfield, value, defaultHtml, columnProperties, rowData) {
                            if (value == 'active') {
                                return 'started';
                            }else{
                                return value;
                            }
                        },
                        'width': '7%'},
                        // 26122024 form and grid section changes | end

                        {dataField: 'closing_date', text: "Closing Date", cellClassName: cell_audit_highlight, 'width': '10%'},
                        {dataField: 'result', text: "Area", cellClassName: cell_audit_highlight, 'width': '10%'},
                        {dataField: 'comments', text: "Root Cause", cellClassName: cell_audit_highlight,'width': '25%'},
                        {dataField: 'improvement', text: "CaPa", cellClassName: cell_audit_highlight, 'width': '25%'},
                        {dataField: 'reference_no', text: "Reference No", cellClassName: cell_audit_highlight, 'width': '5%'},
                        {dataField: 'isCreateTaskLable', text: "Task", cellClassName: cell_audit_highlight, 'width': '8%'},
                        {dataField: 'dsc', text: "Description", cellClassName: cell_audit_highlight, 'width': '25%'},
                        // 19122024 position  changes by iwi | end
                    ]
                }, 
                {
                    "rowselect": initCapaSecByQualityEventSelectRow,
                    "clearselect": select_Clear,
                    "rowopen": initCapaSecByQualityEventOpen
                }, "docs1");
            }
            // 28112024 Quality Event -> CAPA sec added by iwi end
    ;
    // 23102024  Audit control add  extra sec start 
        $(document).on('change', '#audit_closing_date', function (e) { 
            console.log("  audit_closing_date  ",$(this).val());
            if($(this).val() == ''){//31122024 CaPa Actions form  changes by iwi
                $('input[name="audit_status"][value="active"]').prop('checked', true);
            }else{
                $('input[name="audit_status"][value="closed"]').prop('checked', true);
            }
            
        });
        $(document).on('click', 'input[name="audit_status"]', function (e) { 
            current_status_value = $(this).val();
            if(current_status_value == 'closed'){
                $('#audit_closing_date').val(setCurrentDate());
            }
            
        });
        function setCurrentDate() {
            var today = new Date();
            var yyyy = today.getFullYear();
            var mm = String(today.getMonth() + 1).padStart(2, '0'); // Months are 0-based
            var dd = String(today.getDate()).padStart(2, '0');

            return yyyy + '-' + mm + '-' + dd;
        }
    // 23102024  Audit control add  extra sec end 
    //  15102024 Audit control add  extra sec start
    function createTask(formData) {
        return $.ajax({
            url: "/global/task?action=Create",
            type: 'POST',
            data: formData,
            cache: false,
            contentType: false,
            processData: false
        });
    }
    function saveTask(formData,taskId) {
        if(taskId > 0){ formData.taskId = taskId; }
        const formData1 = objectToFormData(formData);
        return $.ajax({
            url: "/record/process?action=Load_save_audit_activity_management",
            type: 'POST',
            data: formData1,
            contentType:false,
            cache: false,
            processData: false
        });
    }
    function objectToFormData(obj) {
        const formData = new FormData();
        for (const key in obj) {
            if (obj.hasOwnProperty(key)) {
                formData.append(key, obj[key]);
            }
        }
        return formData;
    }
    //  15102024 Audit control add  extra sec end

    //  create new function by iwi 26042024 | start
    function get_all_Internal_users(issue_id = 0) {
        $.post("?action=get_internal_trainee_management", {
            org_id: 0,
        }, function (x) {

            var trainee_source = [];
            for (var i in x.data) {

                trainee_source.push({
                    "key": x.data[i].id,
                    "text": x.data[i].name//.split(" ").join('-')   23122024 training Scheduler trainee name change
                });


            }

            $("#internal_user_ids").jqxDropDownList({
                theme: cargohub.jqxtheme,
                source: trainee_source,
                width: 320, height: 23,
                dropDownHeight: 222,
                valueMember: "key", displayMember: "text",
                filterable: true, searchMode: 'containsignorecase'
            });

            for (c = 0; c < trainee_source.length; c++) {
                if (trainee_source[c]['key'] == issue_id)
                    $('#internal_user_ids').jqxDropDownList('selectIndex', c);
            }


        })
                .fail(cargohub.its.http_fail);
    }
    //  create new function by iwi 26042024 | end


// IWI manage extra section only for data panel selected module end





    /**
     * Open or locate panels on the issue overview
     */
    panel_Operation = function (op, window_id)
    {
        cargohub.application.reset_Session_Timer();
        // is window created?
        if (panels[window_id]) {
            // yes, check if it's also visible
            if (panels[window_id] == "closed") {
                // not visible, show it again and expand if it was previously
                // collapsed (but a bit later, since it appears it's not
                // possible to open and expand immediatly)
                $("#" + window_id).jqxWindow('open');
                panels[window_id] = "open";
                if (op != "init")
                    save_Docking_State();
            }
            // expand the new window
            setTimeout(function () {
                $("#" + window_id).jqxWindow('expand');
            }, 1000);

        } else {
            // no, add the window to the docking in it's default panel (column)
            // - It already exists in HTML, hidden, just transform it into a
            // window
            var $window = $("#" + window_id), win_data = $window.data();

            if ($window.length == 0) {
                alert("Window " + window_id + " not available (Possible template error, please contact Support)");
                return; // window not found, nothing to open !
            }

            // Append the editing buttons if win_data.editable is True
            if (win_data.editable && cargohub.application.user_Test_Privileges('issue_edit_xfields')) {
                $window.find("> div:eq(0)").append($("script#tpl_panel-edit-buttons").html());
                // Add events for the buttons; They always affect the panel in
                // which they exist
                $window.find("> div:eq(0) span.panel-cmds button").each(function () {
                    $(this).jqxButton({
                        theme: cargohub.jqxtheme
                    })
                            .click(function (event) {
                                cargohub.components.spinner.Off(); // Remove any
                                // previous spinner
                                // before possibly
                                // creating new ones

                                var $button = $(this);
                                // Start by disabling the button (save, edit, cancel) to
                                // avoid accidental double-press. It will be enabled
                                // once the operation completes.
                                // $button.jqxButton({disabled: true}).jqxButton('render');
                                console.log('  1243 cmd   ', $(this).data('cmd'));
                                
                                if (master.config.type.real_id == 1) {
                                        if ($("input[name='xfd[pstage]']:checked").val() == 'fpr') {
                                           let claimAmount = $("#xfd_claim_amt_amount").val().trim();
                                             if (claimAmount === "") {
                                                alert("Please enter Final Claim amount value.");
                                                // $button.jqxButton({ disabled: false }).jqxButton('render');
                                                $("#xfd_claim_amt_amount").focus();
                                                return false;
                                            }
                                        }
                                }
                                switch ($(this).data('cmd')) {

                                    // Load the editing form via AJAX request
                                    case 'edit-xf':
                                        cargohub.components.spinner.On(null, "Loading edit form...", null, null, 1000);
                                        console.log('  1249 cmd   ', $(this).data('cmd'));
                                        panels_saved_dom[window_id] = $window.find("div.jqx-window-content > *").detach();
                                        if (win_data.editheight) {
                                            // $window.data('currentheight', $window.height());
                                            // $window.jqxWindow({height: win_data.editheight});

                                            // 21112024 Training Scheduler Training Date section work start
                                            if(master.config.type.real_id == 7){
                                                $window.data('currentheight',800);
                                                $window.jqxWindow({height:800});
                                            }else{
                                                $window.data('currentheight', $window.height());
                                                $window.jqxWindow({height: win_data.editheight});
                                            }
                                            // 21112024 Training Scheduler Training Date section work end
                                        }
                                        $window.find("div.jqx-window-header span.panel-report").remove();
                                        $window.find("div.jqx-window-content").html("<div class='loading-pleasewait'></div>");

                                        $.get("?action=Edit_XFields", {
                                            rt_id: master.config.type.real_id,
                                            template: win_data.edittpl
                                        }, function (contents) { // , textStatus, jqXHR
                                            cargohub.components.spinner.Off(500); // Edit
                                            // form
                                            // loaded
                                           

                                            // Choose one of the patterns at random ;) -
                                            // Later we'll make a user setting
                                            $(document).on('click', '#capa-by-quality-event-create-additional', function() {
                                                $('[data-op="capa-by-quality-event-create"]').trigger('click');
                                                $('[data-op="capa-by-quality-event-create"]').focus();
                                            });

                                            setTimeout(function() {       
                                        var rows = $("#_audit_activity_record").jqxDataTable('getRows');
                                        console.log("avc",rows);
                                        var audit_control_total_records = rows.length; 
                                        var audit_control_active_record = rows.filter(function(row) {
                                            return row.status === 'active';
                                        }).length;

                                        var audit_control_close_record = rows.filter(function(row) {
                                            return row.status === 'closed';
                                        }).length;

                                       
                                        $("#no_trainee").html(audit_control_total_records);
                                        $("#active_trainee_count").html(audit_control_active_record);
                                        $("#closed_trainee_count").html(audit_control_close_record);

                                                
                                            }, 1000);
                                            
                                            var patts = ["1.png", "2b.jpg", "3.jpg", "4.png", "5.jpg"];

                                            $window.find("div.jqx-window-content").css('background', "#f8f8f8 url(/img/bgs/gray_patt" + patts[Math.floor(Math.random() * patts.length)] + ")").html(contents);
                                            // Loading complete ? Show the Save/Cancel
                                            $window.find("div.jqx-window-header span.toggle-cmds").toggle();

                                            // 20112024 Customer Inquiry section work start
                                            if ($('.cargo_related_event').length) {
                                                var cargo_related_event = $("input[name='xfd[cargo_related_event]']:checked").val();
                                                if(cargo_related_event == 'yes'){
                                                    $('.cargo_related_event_if_yes').css('display', 'table-row'); 
                                                } else{
                                                    $('.cargo_related_event_if_yes').css('display', 'none');
                                                }
                                            }
                                            // 20112024 Customer Inquiry section work end
                                            // 06092024 added by iwi start
                                            if ($('.certificate_expiry').length) {
                                                var certificate_expiry = $("input[name='xfd[certificate_expiry]']:checked").val();
                                                if(certificate_expiry == 0){
                                                    // $('#xfd_train_exp').addClass('div-section-disable');
                                                    $('#train_scheduler_exp_date').css('display', 'none'); 
                                                }
                                            }
                                            // 06092024 added by iwi end 

                                            //26092024  train_scheduler_classroom_type  addded by iwi start
                                            if ($('.train_scheduler_train_loc').length) {
                                                var train_scheduler_train_loc = $("input[name='xfd[train_loc]']:checked").val();
                                                if(train_scheduler_train_loc == 'Classroom'){
                                                    $('#train_scheduler_classroom_type').css('display', 'table-row'); 
                                                } else{
                                                    $('#train_scheduler_classroom_type').css('display', 'none');
                                                }
                                            }
                                            //26092024  train_scheduler_classroom_type  addded by iwi end 

                                            // call the form initialization method after
                                            // loading ( to setup actions for the form
                                            // elements )
                                            setup_Editform($window, function (html) {
                                                // console.log('  1278 cmd   ', html);
                                                // Submit success callback
                                                cargohub.components.spinner.Off(500);

                                                // Delete old "view" content (only used for
                                                // "cancel" button)
                                                delete panels_saved_dom[window_id];

                                                // Restore the view mode window on
                                                // successful save
                                                if (win_data.currentheight)
                                                    $window.jqxWindow({height: win_data.currentheight});

                                                $window.find("div.jqx-window-content").empty().css('background', "").append(html);
                                                // Regenerate controls on the view
                                                $window.find("div.jqx-window-content").find(".info-tip").each(cargohub.components.ui.add_Tooltip);
                                                $window.find("div.jqx-window-content").find(".info-window").each(cargohub.components.ui.button_InfoWindow);
                                                $window.find("div.jqx-window-content").find(".walloftext-view").each(cargohub.components.ui.walloftext_view);

                                                // Switch back buttons and add a self
                                                // disappearing message that content was
                                                // saved
                                                $window.find("div.jqx-window-header span.toggle-cmds").toggle().find('button').jqxButton({disabled: false}).jqxButton('render');
                                                $window.find("div.jqx-window-header > div:first").append("<span class='panel-report'>" + 'Data was saved' + "</span>");
                                                $window.find("div.jqx-window-header span.panel-report").fadeOut(5000, function () {
                                                    $(this).remove();
                                                });

                                                panel_edit_count--;
                                                $('#tab_panels .multi-commands button[data-op="edit"]').fadeIn();
                                                if (panel_edit_count == 0) {
                                                    $('#tab_panels .multi-commands button[data-op="save"]').fadeOut();
                                                    $(window).off("beforeunload");
                                                }

                                                

                                                if(master.config.type.real_id == 39 || master.config.type.real_id == 51 || master.config.type.real_id == 31){
                                                    setTimeout(function() {
                                                       location.reload(); //26092024 added by iwi 
                                                    }, 1500);
                                                }
                                                

                                            }, function (xhr) {
                                                // Submit failure callback
                                                cargohub.components.spinner.Off(500);
                                                cargohub.its.http_fail(xhr);
                                                // Reenable all buttons on failure (to allow
                                                // trying again)
                                                $window.find("div.jqx-window-header span.toggle-cmds").find('button').jqxButton({disabled: false}).jqxButton('render');
                                            });

                                            // Add the "onbeforeunload" event
                                            if (panel_edit_count == 0)
                                                $(window).on("beforeunload", function () {
                                                    return "Panel data is not saved!";
                                                });

                                            $('#tab_panels .multi-commands button[data-op="save"]').fadeIn();
                                            panel_edit_count++;
                                            if (panel_edit_count == sizeof(panels))
                                                $('#tab_panels .multi-commands button[data-op="edit"]').fadeOut();

                                            awb_match();
                                            
                                            
//                                            get summary old data start
                                            if ($('#xfd_summary').length > 0 && $('#xfd_summary').val() == '') {
                                                $('#xfd_summary').val($('#xfd_inv_descr').val() + ' ' + $('#xfd_inv_remarks').val());
                                            }
//                                             get summary old data end

                                             /* exchange date cant add future date in cargoclaim module_start */
                                           $("#xfd_xchg_date").jqxDateTimeInput({
                                                max: new Date(),  // today
                                                // value: new Date() // optional: default to today
                                            });
                                            /* exchange date cant add future date in cargoclaim module_end */
                                        })
                                                /**
                                                 * The panel edit command failed
                                                 */
                                                .fail(function (xhr) {
                                                    cargohub.components.spinner.Off(500);
                                                    cargohub.its.http_fail(xhr);
                                                    // Reenable Edit button to try again.
                                                    $button.jqxButton({disabled: false}).jqxButton('render');
                                                });
                                        break;

                                        // Triggers the form submit (via AJAX) which should
                                        // update the fields present inside it;
                                        // On completion the content is replaced with a new
                                        // rendering of the panel using "panels/*_view.html"
                                        // templates
                                    case 'save-xf':
                                        
                                        
//                                         get summary old data start
                                        if ($('#xfd_summary').length) {
                                                $('#xfd_inv_descr').val('');
                                               $('#xfd_inv_remarks').val('');
                                            }
//                                             get summary old data end
                                            
                                        // 06092024 by iwi start
                                            if ($('input[name="xfd[certificate_expiry]"]').length) {
                                                var certificate_expiry = $("input[name='xfd[certificate_expiry]']:checked").val();
                                                var train_exp = $('input[name="xfd[train_exp]"]').val();
                                                if(certificate_expiry == 1 && train_exp ==''){
                                                    cargohub.alert("Please Fill Training Expiry Date.");
                                                    return false;
                                                }
                                            }

                                            //26092024  train_scheduler_classroom_type  addded by iwi start
                                            if ($('#train_scheduler_classroom_type').length) {
                                                var train_scheduler_train_loc = $("input[name='xfd[train_loc]']:checked").val();
                                                var classroom_type = $('input[name="xfd[classroom_type]"]:checked').val();
                                                if(train_scheduler_train_loc == 'Classroom' && classroom_type == undefined){
                                                    cargohub.alert("Please Select Classroom.");
                                                    return false;
                                                }
                                            }
                                           

                                            cargohub.components.spinner.On(null, "Submitting changes...", null, null, 1000);
                                        // 06092024  by iwi end
                                       
                                        // Prepare data from managed controls which are
                                        // unable to transmit directly (as inputs)
                                        // A - dropselect multi-select
                                        $(".dropselect.multi-select", $window).each(function () {
                                            var
                                                    name = $('#' + $(this).prop('id') + '_jqxDropDownList', $window).data('name'),
                                                    $hidden_vals = $("<input type='hidden' name=" + name.substr(0, name.length - 2) + ">"); // Name
                                            // gets
                                            // the
                                            // end
                                            // []
                                            // cut,
                                            // as
                                            // the
                                            // original
                                            // was
                                            // designed
                                            // for
                                            // multiple
                                            // checkboxes
                                            ;
                                            // Create an input of type hidden which has the
                                            // original name, and contains the options as
                                            // json-encoded

                                            $hidden_vals.val($(this).val());
                                            $window.find("form").append($hidden_vals);
                                        });

                                        $window.find("form").trigger('submit');
                                        break;

                                        // Cancels the editing process by replacing the window
                                        // content with the original one
                                    case 'cancel-xf':
                                        if (panels_saved_dom[window_id]) {
                                            if (win_data.currentheight)
                                                $window.jqxWindow({height: win_data.currentheight});
                                            $window.find("div.jqx-window-content").empty().css('background', "").append(panels_saved_dom[window_id]);
                                            delete panels_saved_dom[window_id];
                                        }

                                        // Reenable all buttons on cancelling
                                        $window.find("div.jqx-window-header span.toggle-cmds").toggle().find('button').jqxButton({disabled: false}).jqxButton('render');
                                        $window.find("div.jqx-window-header > div:first").append("<span class='panel-report red'>" + 'Editing Cancelled ' + "</span>");
                                        $window.find("div.jqx-window-header span.panel-report").fadeOut(3000, function () {
                                            $(this).remove();
                                        });

                                        panel_edit_count--;
                                        $('#tab_panels .multi-commands button[data-op="edit"]').fadeIn();
                                        if (panel_edit_count == 0) {
                                            $('#tab_panels .multi-commands button[data-op="save"]').fadeOut();
                                            $(window).off("beforeunload");
                                        }
                                        break;
                                }
                            });

                });
            }

            $('#tab_panels #panels-docker').jqxDocking('addWindow', window_id, 'default', $window.data('panel'));
            // Normal users do not have the Close button anymore - Still valid
            // for admin, in case certain layouts need to be set up which
            // include closed panels.
            if (!cargohub.application.user_Is_Admin())
                $('#tab_panels #panels-docker').jqxDocking('hideCloseButton', window_id);
            $('#tab_panels #panels-docker').jqxDocking('showCollapseButton', window_id);
            $window
                    /**
                     * Event: A window is closed - Save the new docking state
                     */
                    .on('close', function (e) {
                        if (e.target == this) {
                            panels[window_id] = "closed";
                            save_Docking_State(e);
                            // Show a warning that this panel has been closed !
                            cargohub.application.evan_Standard_Notify("warning", "A panel has been closed! If you did not intend this, you can reopen from the <b>View &amp; Print menu</b>", 15);
                            $('html, body').animate({
                                scrollTop: 0
                            }, 1000);
                        }
                    })
                    /**
                     * Event: A panel is collapsed. If this is a "Data Table" panel,
                     * clear any row selection and hide the Edit/Delete buttons if they
                     * exist
                     */
                    .on('collapse', function (e) {
                        if (e.target == this) {
                            if ($(this).hasClass("datatable-panel"))
                                datatable_Panel_Unselect(e.target.id);
                            save_Docking_State(e);
                        }
                    })
                    .on('expand', save_Docking_State)
                    .on('moved', save_Docking_State);
            // .on('resized', save_docking_state); // Disabled for now, as windows can't be
            // pulled out of the dock anymore

            panels[window_id] = "open";

            // capture the state of the docking panel every time a window is
            // opened
            if (op != "init")
                save_Docking_State();
        }

        var header_animate = function ($hdr) {
            $hdr
                    .animate({'margin-left': 30}, 200)
                    .animate({'margin-left': 0}, 200)
                    .animate({'margin-left': 20}, 250)
                    .animate({'margin-left': 0}, 300)
                    .animate({'margin-left': 10}, 350)
                    .animate({'margin-left': 0}, 400);
        }, single_scroll_trigger = true;

        // finally, jump to the visibile window
        if (op == 'jump-to')
            $('html, body').animate({
                scrollTop: $("#" + window_id).offset().top
            }, 800, function () {
                if (single_scroll_trigger)
                    header_animate($("#" + window_id).find("div.jqx-window-header > div:eq(0)"));
                single_scroll_trigger = false;
            });
        if (op == 'jump-to-delayed')
            setTimeout(function () {
                $('html, body').animate({
                    scrollTop: $("#" + window_id).offset().top
                }, 800, function () {
                    if (single_scroll_trigger)
                        header_animate($("#" + window_id).find("div.jqx-window-header > div:eq(0)"));
                    single_scroll_trigger = false;
                });
            }, 500);
    },
            /**
             * Sends the current state of the docking panel to the controller, so it's
             * saved for next time the user opens this page
             */
            save_Docking_State = function (e)
            {
                // Don't save docking state while panels haven't yet finished
                // initializing
                if (!panels_ready)
                    return;

                // Introduce a small delay when doing panel operations to avoid doing
                // unneeded ajax calls (for example when someone is playing with
                // minimize-maximize button)
                donrey.final_Delay('save_docking_state', function () {
                    $.post("?action=Save_Docking_State", {
                        panels: panels,
                        rt_id: master.config.type.real_id,
                        panel_export: $('#tab_panels #panels-docker').jqxDocking('exportLayout')
                    });
                    // Do nothing for reporting, not worth bugging the user for problems
                    // here.
                }, 2000);
            },
            // How many panels are being currently edited ?
            panel_edit_count = 0,
            /**
             * Sets up the fields edit form which is dynamically loaded with ajax
             * 
             * @param $window
             *            The jqxWindow panel which contains the form
             * @param success_callback
             *            When submit complete with success, call this to finish the
             *            operation (This usually replaces the content with the returned
             *            HTML)
             * @param fail_callback
             *            When submit failed (for any reason), call this to cancel the
             *            operation and inform the user
             */
            setup_Editform = function ($window, success_callback, fail_callback)
            {
                var $the_form = $window.find("form"), win_data = $window.data();

                // setup the ajaxed form
                $the_form.ajaxForm({
                    url: "?action=Save_XFields",
                    data: {
                        rt_id: master.config.type.real_id,
                        template: win_data.edittpl
                    },
                    beforeSubmit: function (formData, jqForm, options) {
                        // TODO: setup field checking
                        if (master.config.type.real_id == 34 && win_data.edittpl == 'meta') {
                            var dup_there = "";
                            var rec_Pos_Details = "";
                            var startDate = "";
                            var endDate = "";
                            var repetation = "";
                            for (let i = 0; i < formData.length; i++) {
                                if (formData[i].name == "xfd[rec_Pos]") {
                                    rec_Pos_Details = formData[i].value;
                                }
                                //dup_there
                                if (formData[i].name == "xfd[has_Child]") {
                                    dup_there = formData[i].value;
                                }
                                if (formData[i].name == "xfd[date_start]") {
                                    startDate = formData[i].value;
                                }
                                if (formData[i].name == "xfd[date_end]") {
                                    endDate = formData[i].value;
                                }
                                if (formData[i].name == "xfd[sched_rep]") {
                                    repetation = formData[i].value;
                                }


                            }
                            if (dup_there == "n" && rec_Pos_Details == "par") {
                                if (startDate != "" && endDate != "" && repetation != "") {
                                    //	if(confirm("You have edited parent record and you submitted all the details which needs to create duplicates?\n" +
                                    //			"System will take sometime to create duplicates")){
                                    //		return true;
                                    //	}else{
                                    //		return false;
                                    //	}
                                    cargohub.alert("You have scheduled audits which will be created by duplicating the current audit record. The new records will be automatically created within a few mins.");
                                }
                            }
                        }
                        return true;
                    },
                    success: success_callback,
                    error: fail_callback
                });

                // All the following code is about interactive actions in the elements
                // inside the form ( might be various jqWidgets objects or other
                // elements)

                // jqxstyle any buttons in the form
                $('.op-button', $the_form).each(function () {
                    $(this).jqxButton({
                        theme: cargohub.jqxtheme
                    });
                });
                // Setup Calendar and Clock inputs
                $(".calendar", $the_form).each(cargohub.components.ui.calendar_input);
                $(".theclock", $the_form).each(cargohub.components.ui.clock_input);

                // Setup special comboboxes used for searching/suggestion
                $(".searchbox", $the_form).each(cargohub.components.ui.remotesearch_input);

                // Dropdowns (replaces "select")
                $(".dropselect", $the_form).each(cargohub.components.ui.drop_select);

                // Textarea which usually contains more text than it normally fits. On
                // hover, shows complete text in a floating div
                $(".walloftext", $the_form).each(cargohub.components.ui.walloftext_input);

                // Keyboard filters:
                $(".only-numbers", $the_form).each(cargohub.components.ui.numbers_input);

                $(".sync-container", $the_form).each(function () {
                    var $parent = $(this).parent().parent();
                    if ($(this).prop('checked'))
                        $parent.addClass('active');
                    $(this).on('change', function () {
                        if ($(this).prop('checked'))
                            $parent.addClass('active');
                        else
                            $parent.removeClass('active');
                    });
                });

                // Some buttons open a window with help about form content
                $('.info-window', $the_form).each(cargohub.components.ui.button_InfoWindow);

                // Add tool-tips to any element matching "info-tip"
                $(".info-tip", $the_form).each(cargohub.components.ui.add_Tooltip);
            }
    ;

    this._public({
        /**
         * Setup Notes Tab for Record Process module
         */
        init: function ()
        {
            init_Panels();


            // 15102024 Audit control add  extra sec start
            var type_rt_id = cargohub.record_process.config.type.base_id;
            if(type_rt_id  == 34){
                init_DataTable1();
                init_audit_activity_Toolbar();

                
                // 08012025 capa report showing no of record
                setTimeout(function() {
                    var audit_control_total_records = $("#_audit_activity_record").jqxDataTable('getRows').length;
                    $("#no_trainee_count").text(audit_control_total_records); 
                    $("#no_trainee").html(audit_control_total_records); 
                    
                }, 2000); 
                // 08012025 capa report showing no of record
            }
            //27112024 Customer Inquiry -> CAPA sec added by iwi
            else if(type_rt_id  == 7){
                init_DataTable2();
                init_capa_sec_by_customer_inquiry_Toolbar();

                 // 08012025 capa report showing no of record
                 setTimeout(function() {
                    var audit_control_total_records = $("#_audit_activity_record").jqxDataTable('getRows').length;
                    $("#no_trainee_count").text(audit_control_total_records); 
                    $("#no_trainee").html(audit_control_total_records); 
                }, 2000); 
                // 08012025 capa report showing no of record
            } 
            //28112024 Quality Event -> CAPA sec added by iwi
            else if(type_rt_id  == 31){
                init_DataTable3();
                init_capa_sec_by_quality_event_Toolbar();


                // 08012025 capa report showing no of record
                setTimeout(function() {
                    var audit_control_total_records = $("#_audit_activity_record").jqxDataTable('getRows').length;
                    $("#no_trainee_count").text(audit_control_total_records); 
                    $("#no_trainee").html(audit_control_total_records); 
                }, 2000); 
                // 08012025 capa report showing no of record

                setTimeout(function() {       
                    var rows = $("#_audit_activity_record").jqxDataTable('getRows');
                  
                    var audit_control_total_records = rows.length; 
                    var audit_control_active_record = rows.filter(function(row) {
                        return row.status === 'active';
                    }).length;

                    var audit_control_close_record = rows.filter(function(row) {
                        return row.status === 'closed';
                    }).length;

                   
                    $("#no_trainee").html(audit_control_total_records);
                    $("#active_trainee_count_view").html(audit_control_active_record);
                    $("#closed_trainee_count_view").html(audit_control_close_record);

                    $("#no_trainee").val(audit_control_total_records);
                    $("#active_trainee_count_view").val(audit_control_active_record);
                    $("#closed_trainee_count_view").val(audit_control_close_record);

                            
                }, 2000);
            }
            
            
//            IWI  manage contracts for Supplier control 19052026 start
            else if (type_rt_id == 55) {
                init_DataTableContractManagement();
                init_ContractManagement_Toolbar();
                initLoadUsers();
                initLoadFiles();   //files upload in contract management form
                $("#contract_create_btn").on("click", function () {
                    document.getElementById("contract_create_form").reset();
                     $("#contract-file-selector .selected-file-text").text("Select File"); //files upload in contract management form
                    $("#contract-attach-file").prop("selectedIndex", 0);   //files upload in contract management form
                     $("#linkedAssetsTableContainer").empty(); //IWI Adding assets from Supplier control contract section 28072026
                    $("#linkedAssetsSection").hide(); //IWI Adding assets from Supplier control contract section 28072026
                    $("#contract_record_id").val(0);
                    $("input[name='contract_status'][value='active']").prop("checked", true);
                    $("#contract_save_btn").html("Save");
                    $("#contract_management_module .contract-accordion-content").slideDown(200);
                });
                $("#contract_cancel_btn").on("click", function () {
                    $("#contract_management_module .contract-accordion-content").slideUp(200);
                });

                $("#contract_create_form").on("submit", function (e) {
                    e.preventDefault();
                    initContractManagementSave();
                });
                $(document).on("keyup change", ".contract-input-field", function () {
                    $(this).removeClass("contract-field-error");
                });
                $(document).on('change', '#contract_auto_terminate', function () {
                    if ($(this).is(':checked')) {
                        $('#notice_date_wrapper').slideDown(200);
                        $('#notice_period_wrapper').slideDown(200); //Notice Period Dropdown
            
                    } else {
                        $('#notice_date_wrapper').slideUp(200);
                        $('#notice_period_wrapper').slideUp(200); //Notice Period Dropdown
                        // optional clear value
                        $('#contract_notice_date').val('');
        
                    }
                });
                $(document).on('change', '#notice_period', function () {  //Notice Period Dropdown_start
                        calculateNoticeDate();
                    });

                    $(document).on('change', '#contract_create_end_date', function () {
                        calculateNoticeDate();
                    });                                                         //Notice Period Dropdown_end
                
                 $(document).on("change", "input[name='contract_type']", function () {
                    toggleContractFields();
                });
                
                
                

//IWI Adding assets from Supplier control contract section 28072026 start



                $(document).on("click", ".asset-unlink-btn", function () {
                    var btn = $(this);
                    var linkId = btn.data("asset-id");
                    var contract_link_id = btn.data("contract-link-id");
                    Swal.fire({
                        title: "Remove Asset?",
                        text: "This asset will be unlinked from the contract. The asset record will not be deleted.",
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonText: "Yes, Remove",
                        cancelButtonText: "Cancel",
                        confirmButtonColor: "#dc2626",
                        cancelButtonColor: "#64748b",
                        reverseButtons: true
                    }).then(function (result) {
                        if (!result.isConfirmed) {
                            return;
                        }
                        $.ajax({
                            url: "/record/process?action=delete_linked_supplier",
                            type: "POST",
                            dataType: "json",
                            data: {
                                link_id: linkId,
                                id: contract_link_id,
                            },
                            success: function (response) {

                                if (response.success) {

                                    btn.closest("tr").fadeOut(200, function () {
                                        $(this).remove();
                                    });

                                    Swal.fire({
                                        icon: "success",
                                        title: "Removed",
                                        text: response.message || "Asset has been unlinked successfully.",
                                        timer: 1500,
                                        showConfirmButton: false
                                    });

                                } else {

                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: response.message || "Unable to unlink asset."
                                    });

                                }

                            },
                            error: function () {

                                Swal.fire({
                                    icon: "error",
                                    title: "Server Error",
                                    text: "Something went wrong. Please try again."
                                });

                            }
                        });

                    });

                });


//IWI Adding assets from Supplier control contract section 28072026 end

                
            }
            //            IWI  manage contracts for Supplier control 19052026 end

//            IWI  link Supplier and contracts for Asset Control 21052026 start 

            else if (type_rt_id == 56) {
                initSupplierCombos();
                bindEvents();
                loadLinkedSupplier();
            }

//            IWI  link Supplier and contracts for Asset Control 21052026 end
            
            else{
                init_DataTable();
            }
            // 15102024 Audit control add  extra sec end
            
           
            



            // Tooltips inside the Documents tab
            $("#tab_panels .info-tip").each(cargohub.components.ui.add_Tooltip);
            // IWI manage extra section only for data panel selected module  start
            init_Toolbar();
            $("#tab_trainee .info-tip").each(cargohub.components.ui.add_Tooltip);

            // We need DropzoneJS here
            master.load_file_uploader();

            // IWI manage extra section only for data panel selected module end
        },

        /**
         * Callback for actions to be performed when this tab is selected
         */
        selected: function (params)
        {
            if (params && params.trigger)
                switch (params.trigger) {
                    case "jump-to-panel":
                        if (panels_ready)
                            panel_Operation('jump-to', params.id);
                        break;
                    case "open-panel":
                        if (panels_ready)
                            panel_Operation('jump-to-delayed', params.id);
                        break;
                    case "files-upload":
                        files_Upload();
                        break;
                }
        }
    });





    //For multi save  on blur START  | 21022024 code added by iwi | start


    // $(document).on('change ', "#tab_panels #panels-docker checked ,#tab_panels #panels-docker input,#tab_panels #panels-docker textarea,#tab_panels #panels-docker select ,#tab_panels #panels-docker selector ", function () {
    //     $(this).closest("form").submit();
    // });

    //For multi save  on blur END    | 21022024 code added by iwi | end




    $(document).on('click', '#paneltaberc', function () {
        load_listing_by_doc();
//location.reload(true);
    });
    
                                                                 // active/archieved training task 04-12-25 start
    if(document.getElementById('btnInactive'))   {                                                         
    var btnInactive = document.getElementById('btnInactive');
    var inactiveDiv = document.getElementById('inactiveTraining');
    
    btnInactive.onclick = function() {
        activeDiv.style.display = 'none';
        inactiveDiv.style.display = 'block';
         btnInactive.style.backgroundColor = '#b71818';
    btnInactive.style.color = '#fff';

    btnActive.style.backgroundColor = '#f0f0f0';
    btnActive.style.color = '#555';
    load_listing_by_archievedtraining();
    };   
}    
   if(document.getElementById('btnActive'))   {
    var activeDiv = document.getElementById('activeTraining');
     var btnActive = document.getElementById('btnActive'); 
    btnActive.onclick = function() {
        activeDiv.style.display = 'block';
        inactiveDiv.style.display = 'none';
    btnActive.style.backgroundColor = '#4caf50';
      btnActive.style.color = '#fff';

    btnInactive.style.backgroundColor = '#f0f0f0';
    btnInactive.style.color = '#555';
    load_listing_by_training();
        
    };
}
                                                       // active/archieved training task 04-12-25 end

});



$(document).on('click', '.goDetailFrmPnl', function (e) {
    e.preventDefault();
    var url = $(this).data("url");
    window.open(url, '_blank');
});
$(document).on('click', '#doc-create-frm-panel', function (e) {
    e.preventDefault();
    console.log('kk');
    $('#tabs-container #doctaberc').click();
    $('.footer').prepend('<span id="getFromPanelCreateDoc"></span>');
    $("#tab_docs .toolbar button[data-op='doc-create']").click();
});


$(document).on('click', '#doc-tab-open-frm-panel', function (e) {
    $('#tabs-container #doctaberc').click();
}); 

// iwi license enhancement in doc start
$(document).ready(function () {
    
    $(document).on("click", "[data-op='doc-create-lic']", function () {
        $("#modalContent").html($("#hiddenContent").html());

        // Show the modal and initialize dropdowns after it's visible
        $("#customModal, #customOverlay").fadeIn(function () {
            getdocLicenseMonitorData();
        });
    });

    $(document).on("click", ".btn-close, #customOverlay", function () {
        console.log("Closing modal...");
        $("#customModal, #customOverlay").fadeOut();
        $("#license_records_table tbody").empty();
    });
});
// iwi license enhancement in doc end







//$(document).on('click', '#tab_docs_doccumentation button', function (e) {
//    console.log($(this).data('op'));
//    switch ($(this).data('op')) {
//        case "doc-create":
//            master.clear_DataTable($("#tab_docs div.jqx-datatable")); // Also unselect row if one is selected (which hides unneeded buttons)
//            open_row_id = null;
//            copy_mode = false;
//
//            if ($doc_viewer && viewer_open)
//                close_Viewer(true);
//
//            $('#tab_docs .docs-write-ops').fadeIn(); // Make sure Save is enabled
//            init_Editor({
//                title: "Create new blank document",
//                icon: "add.png",
//                mode: "create"
//            });
//            $('#tab_docs #docs-create-op').fadeOut(); // Hide the "Create" button when the editor is active, will be shown again on Save or Close
//
//    }
//});

function awb_match() {
    //Validate MAWB
    $('#xfd_mawb_s').on('keyup', function (e) {
        // AWB validity check
        var awbp = $("#xfd_mawb_p").val(), awbs = $("#xfd_mawb_s").val();
        if (awbs.length == 8) {
            if (parseInt(awbs.substr(0, 7)) % 7 != parseInt(awbs.substr(7))) {
                $("#xfd_mawb_s").val('');
                cargohub.components.modal.customalert("Invalid MAWB number. Please make sure you entered the correct digits", "Error", "error.png");
                $('#modal_alert_yes').text("Ok");
                $('#modal_alert_notok').css("display", "none");
                return false;

            }

            $.post("../../issue?action=match_awb", {
                p: awbp,
                s: awbs,
                fk: record_type_checker()
            }, function (x) {
                if (x) {
                    if (x.mawb_outcome == 1) {
                        cargohub.components.modal.customalert(x.message, "Alert", "exclamation.png");
                        $('#modal_alert_yes').text("Yes");
                        $('#modal_alert_notok').css("display", "inline-block");
                        return false;
                    }

                }
            }).fail(cargohub.its.http_fail);
        }

    });
}
function record_type_checker() {
    var rt_ty = $('#record-type div a').text();
    var output;
    if (rt_ty === "Quality Event") {
        output = 1225;
    } else {
        output = 3;
    }
    return output;
}
function child_delete() {
    $.post("process?action=check_AND_deleteChilds", {
        urlData: window.location.href
    }, function (x) {
        if (x.success) {
            var dI = x.dI;
            if (confirm(x.message)) {
                alert('Ok we can remove childs from  ' + x.trackId);
                $.post("process?action=delete_audit_childs", {
                    id: dI
                },
                        function (x) {
                            if (x.success) {
                                if (x.message != "") {
                                    alert(x.message);
                                    document.location.reload();
                                } else {
                                    alert("Something went wrong,please try again");
                                }
                            } else {
                                if (x.message != "") {
                                    alert(x.message);
                                    document.location.reload();
                                } else {
                                    alert("Something went wrong,please try again");
                                }
                            }
                        });
            }
        } else {
            alert(x.message);
        }
    });
}
function panel_ready_for_parent(baseid, id) {
    $.post("process?action=check_childCount", {
        baseid: baseid,
        id: id
    }, function (x) {
        if (x.success) {
            if (x.type == 'par') {
                document.getElementById('showchild').innerHTML += "  [" + x.count + "]";
                //open parent should not appear
                document.getElementById("open_Parent").style.visibility = "hidden";
                if (x.count <= 0) {
                    //delete childs should not appear
                    document.getElementById("delete_childs").style.visibility = "hidden";
                }
            } else if (x.type == 'chi') {
                //delete childs should not appear
                document.getElementById("delete_childs").style.visibility = "hidden";
            }
        } else {
            //alert(x.message);
        }
    });
}
function par_Route(id) {
    if (id != null && id != "") {
        document.location = "/record/process:" + id + "";
    } else {
        alert("There is no parent for selected record");
    }
}
function getAuditData(item, index) {
    console.log(index + " " + item);
}


if ($("#_training-record-docs").length) {
    function closeTrainingRecordEditModal(argument) {
        var modal = document.getElementById("training-record-docs-edit-form");
        modal.style.display = "none";
    }


    function disableInput(element) {
        element.disabled = true;
        console.log('2992   disabled');
    }
    // create by iwi 13052024 | start
    $("form#trainingRecordForm").submit(function () {
        
        // 31072024 created by iwi | start
        var trainingScore = $('#trainingScore').val();
        if(isNaN(Number(trainingScore))) {
            Swal.fire({
                title: "",
                text: 'The trainingScore value is not numeric.',
                icon: "error"
            });
            return false;
        }
        
        //26082024 changes by iwi | start
        var trainingDate = $('#trainingDate').val();
        if(trainingDate != ''){
            var datePattern = /^\d{4}-\d{2}-\d{2}$/;
            if (!datePattern.test(trainingDate)) {
                Swal.fire({

                    title: "",
                    text: 'The training date is not valid.',
                    icon: "error"
                });
                return false;
            }
        }

        //06092024 changes by iwi | start
        var certificateExpiry = $("input[name='certificateExpiry']:checked").val();
        var trainingExpiryDate = $('#trainingExpiryDate').val();
        var trainingResult =  $(".trainingResult:checked").val(); 
        if(trainingExpiryDate == '' && certificateExpiry == 1 && trainingResult == 1){ //03102024
            var datePattern = /^\d{4}-\d{2}-\d{2}$/;
            if (!datePattern.test(trainingExpiryDate)) {
                Swal.fire({
                    title: "",
                    text: 'Please enter the expiry date.',
                    icon: "error"
                });
                return false;
            }
        }
        //06092024 changes by iwi | end

        var formData = new FormData($(this)[0]);
        formData.append('track', cargohub.record_process.config.track);
        formData.append('con_id', cargohub.record_process.config.id);
        $.ajax({
            url: "process?action=Load_save_training_data_by_id",
            type: 'POST',
            data: formData,
            async: false,
            success: function (data) {
              location.reload(); //15112024 changes by iwi
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });



    function editTraineeManagementMode(button, tr_id, tr_name,tr_name_id='') { //IWI mapping with training type id
        $('#trainingRecordForm')[0].reset();
        var modal = document.getElementById("training-record-docs-edit-form");
        modal.style.display = "block";
        if (tr_id == 0) {
            //$('input[type="radio"][name="trainingResult"][value="1"]').prop('checked', true);
            $('#trainingName').val(tr_name);
            $('#trainingName_id').val(tr_name_id);//IWI mapping with training type id
            $('.training_rdef_file').css('display', 'none');
            $("input[name=trainingResult][value=1]").attr('checked', 'checked');//29082024 added by iwi

            $("input[name=certificateExpiry][value=1]").attr('checked', 'checked');// 06092024 created by iwi
            return false;
        } else {
            var id = 0;
            var t_manage_maping_id = 0;

            if (tr_name != '') {
                id = 0;
                t_manage_maping_id = tr_id;
            } else {
                id = tr_id;
                t_manage_maping_id = 0;
            }

            $.post("process?action=Load_get_training_data_by_id", {
                id: id,
                t_manage_maping_id: t_manage_maping_id
            }, function (x) {
                
                $('#trainingName').val(x.data.trainee_name);
                $('#trainingEmail').val(x.data.email);//21082024 changes by iwi
                $('#trainingDate').val(x.data.create_dt);
                $('#trainingStartDate').val(x.data.start_date);
                $('#trainingEndDate').val(x.data.end_date);
                $('#trainingExpiryDate').val(x.data.expiry_date);
                $('#oldCertificate').val(x.data.certificate);
                if (x.data.certificate != '' && x.data.certificate != 0) {
                    $('.training_rdef_file').attr('href', x.data.certificate);
                    $('.training_rdef_file').css('display', 'block');
                } else {
                    $('.training_rdef_file').css('display', 'none');
                }
                $('#trainingScore').val(x.data.score);
                $("input[name=trainingResult][value=" + x.data.result + "]").attr('checked', 'checked');

                // 06092024 created by iwi  | start
                $("input[name=certificateExpiry][value=" + x.data.isExpiry + "]").attr('checked', 'checked');
                if(x.data.isExpiry == 0){
                    $('.training_record_exp_date').css('display', 'none'); //09092024 added by iwi
                }
                if(x.data.isExpiry == 1){
                    $('.training_record_exp_date').css('display', 'block'); //09092024 added by iwi
                }
                // 06092024 created by iwi | end
                if(x.data.result == 1){
                    $('.trainingResultDetailsDiv').css('display', 'block');//30082024 added by iwi
                }else{
                    $('.trainingResultDetailsDiv').css('display', 'none');//30082024  added by iwi
                }
                // 28082024 created by iwi | end

                if (tr_name != '') {
                    $('#trainingManagementFormId').val(x.data.id);
                    $('#trainingRecordFormId').val(0);
                } else {
                    $('#trainingRecordFormId').val(x.data.id);
                    $('#trainingManagementFormId').val(0);
                }


            });
        }
    }

    // 17122024 Training Record add checkbox  by iwi | start
    if ($('#nextExpiry').length) {
        $(document).on('click', '.isCheckedTraining', function (e) {
            $('#saveIsCheckboxtrRecord').css('display', 'block');

            let checkedValues = [];
            let uncheckedValues = [];
            if ($(this).prop("checked")) {
                console.log("Checkbox is checked! Value:", $(this).val());
                checkedValues.push($(this).val());
            } else {
                console.log("Checkbox is unchecked!");
                uncheckedValues.push($(this).val());
            }


            $.post("process?action=Load_disable_training_by_id", {
                isDisableTrTraining: checkedValues,
                isEnableTrTraining: uncheckedValues,
            }, function (x) {
                if(x.success == true){
                    Swal.fire({
                        // position: "top-end",
                        icon: "success",
                        title: "This training has been successfully disabled. ",
                         showConfirmButton: false,
                        timer: 3000
                    });
                }else{
                    Swal.fire({
                        title: "",
                        text: "Please try again later.",
                        icon: "error"
                    });
                }  
            });
        });
    }
    /*
    $('#saveIsCheckboxtrRecord').on('click', function (e) {
        let checkedValues = [];
        $('.isCheckedTraining:checked').each(function () {
            checkedValues.push($(this).val());
        });

        let uncheckedValues = [];
        $('.isCheckedTraining:not(:checked)').each(function () {
            uncheckedValues.push($(this).val());
        });
        
        $.post("process?action=Load_disable_training_by_id", {
            isDisableTrTraining: checkedValues,
            isEnableTrTraining: uncheckedValues,
        }, function (x) {
            if(x.success == true){
                Swal.fire({
                    // position: "top-end",
                    icon: "success",
                    title: "This training has been successfully disabled. ",
                     showConfirmButton: false,
                    timer: 5500
                });
            }else{
                Swal.fire({
                    title: "",
                    text: "Please try again later.",
                    icon: "error"
                });
                
            }
            

        });
    });
    */
    // 17122024 Training Record add checkbox  by iwi | end
    
    

    // 28082024 changes by iwi | start
        $('.trainingResult').on('change', function() {
            var trainingResult = $(this).val();
            $('.trainingResultDetailsDiv').css('display', 'none'); //30082024 added by iwi
            if(trainingResult == 1){
                $('.trainingResultDetailsDiv').css('display', 'block'); //30082024 added by iwi
            }
        });


        // $('#trainingExpiryDate').on('change', function() {
        //     $('input[type="radio"][name="trainingResult"][value="1"]').prop('checked', true);
        // });
    // 28082024 changes by iwi | end


    // create by iwi 06092024 | end
    $('.certificateExpiry').on('change', function() {
        var isCertificateExpiry = $(this).val();
        if (isCertificateExpiry == 0){
            $('.training_record_exp_date').css('display', 'none'); //09092024 added by iwi
        }
        if(isCertificateExpiry == 1){
            $('.training_record_exp_date').css('display', 'block'); //09092024 added by iwi
        }
    });

}



    // 16082024 created by iwi | start
    //  02122024 Training Scheduler upload multiple files
    $('#trainingFile, #trainee_files, #trainee_all_files').change(function () {
        var val = $(this).val().toLowerCase(),
            regex = new RegExp("(.*?)\.(pdf|jpg|png)$");
        // $('#trainingFileSubmitBtn').prop('disabled', false);
        // $('#trainingFileSubmitBtn').css('background-color', '#2866C2 !important');
        if (!(regex.test(val))) {
            $(this).val('');
            Swal.fire({
                title: "",
                text: 'The file format is not supported', 
                icon: "error"
            });
            // $('#trainingFileSubmitBtn').prop('disabled', true);
            // $('#trainingFileSubmitBtn').css('background-color', '#ccc');
            return false;
        }
    });
    // 16082024 created by iwi | end


// created by iwi 20052024 | start
if ($(".show_extra_license_monitor_list_sec").length > 0) {

    function licenseMonitorFormModal() {
        console.log("test");
        $('#licenseMonitorForm')[0].reset();
        var modal = document.getElementById("license-monitor-edit-form-modal");
        modal.style.display = "block";
    }

    $("form#licenseMonitorForm").submit(function () {

        var documentLink = $('#documentLink').val();
        if (documentLink == '') {
            Swal.fire({
                title: "",
                text: 'Please enter document link',
                icon: "error"
            });
            return false;
        }
       
        ajaxValue = {
            "documentLink": documentLink
        };
        $.post({
            url: "process?action=Load_Save_License_Monitor",
            type: 'POST',
            data: {
                documentLink: documentLink,
                con_id: cargohub.record_process.config.id,
                track: cargohub.record_process.config.track
            },
            async: false,
            success: function (data) {
                if (data.isExist == 'no') {
                    Swal.fire({
                        title: "",
                        text: data.message,
                        icon: "error"
                    });
                    return false;
                } else {
                    // 17062024 created by iwi | start
                    $('.doc_ref_value').val(data.ttl_doc_link);
                    // 17062024 created by iwi | end
                    closeLicenseMonitorEditModal(this);
                    getLicenseMonitorData();
                    Swal.fire({
                        title: "",
                        text: data.message,
                        icon: "success"
                    });
                }
            },
        });
        var formData = new FormData($(this)[0]);
    });
    //getLicenseMonitorData();
    // function getLicenseMonitorData() {
        
    //     $.post({
    //         url: "process?action=Load_Get_All_License_Monitor",
    //         type: 'POST',
    //         data: {
    //             con_id: cargohub.record_process.config.id,
    //             track: cargohub.record_process.config.track
    //         },
    //         async: false,
    //         success: function (data) {
    //             console.log(data);
    //             $(".license_monitor_record_section_table").html(data.data);
    //         },
    //     });
    // }

    function closeLicenseMonitorEditModal(argument) {
        var modal = document.getElementById("license-monitor-edit-form-modal");
        modal.style.display = "none";
    }

  


    // function deleteLicenseMonitorData(lm_id_val) {

    //     //        if (confirm('Are you sure you want to delete this?')) {
    //     //            $.post({
    //     //                url: "process?action=Load_Delete_License_Monitor",
    //     //                type: 'POST',
    //     //                data: {
    //     //                    lm_id: lm_id_val,
    //     //                    con_id: cargohub.record_process.config.id,
    //     //                    track: cargohub.record_process.config.track
    //     //                },
    //     //                async: false,
    //     //                success: function (data) {
    //     //                    if(data.success == true){
    //     //                        getLicenseMonitorData();
    //     //                        alert('Data deleted successfully');
    //     //                    }
    //     //                },
    //     //            });
    //     //        }






    //     Swal.fire({
    //         title: "Are you sure you want to delete this?",
    //         showDenyButton: true,
    //         confirmButtonText: "Yes",
    //     }).then((result) => {
    //         if (result.isConfirmed) {
    //             Swal.showLoading();
    //             $.post({
    //                 url: "process?action=Load_Delete_License_Monitor",
    //                 type: 'POST',
    //                 data: {
    //                     lm_id: lm_id_val,
    //                     con_id: cargohub.record_process.config.id,
    //                     track: cargohub.record_process.config.track
    //                 },
    //                 async: false,
    //                 success: function (data) {
    //                     if (data.success == true) {
    //                         // 17062024 created by iwi | start
    //                         $('.doc_ref_value').html(data.ttl_doc_link);
    //                         // 17062024 created by iwi | end
    //                         getLicenseMonitorData();
    //                         Swal.fire({
    //                             title: "",
    //                             text: "Data deleted successfully",
    //                             icon: "success"
    //                         });
    //                     }
    //                 },
    //                 error: function (xhr) {
    //                     console.log(xhr.responseText); // Debugging error response
    //                 }
    //             });


    //         }
    //     });




    // }

    $(document).on('click', '.goDetailFrmlm', function (e) {
        e.preventDefault();
        var url = $(this).data("url");
        window.open(url, '_blank');
    });


}
// created by iwi 20052024 | end

// created by iwi 07062024 | start
    if ($("#_doccumentation_tab_list_license_monitor_record").length > 0) {
        getdocLicenseMonitorData();
        //getLicenseTypeData();
        $(document).on('click', '.goDetailFrmlm', function (e) {
            e.preventDefault();
            var url = $(this).data("url");
            window.open(url, '_blank');
        });
    }
    
   
    
    
    
    function getdocLicenseMonitorData() {
        $.post({
            url: "process?action=Load_Get_All_Doc_License_Monitor_Data", // URL to fetch data
            type: 'POST',
            data: {
                con_id: cargohub.record_process.config.id,  
                track: cargohub.record_process.config.track
            },
            success: function (data) {
//                console.log('dsadasd',data);
                
                if (data.group_data && data.all_country_code) {
    
                    //  var group_data_source = '';
                     var group_data_source = '<option value="">Select License Type</option>';
                    for (var i in data.group_data) {
                        
                        group_data_source += '<option value="' + data.group_data[i].name + '">' + data.group_data[i].name + '</option>';
                    }
                    
                    $('#doc_tab_license_type').html(group_data_source); 
    
                    
                    var country_code_source = '<option value="">Select Country Code</option>';
                    for (var i in data.all_country_code) {
                        
                        country_code_source += '<option value="' + data.all_country_code[i] + '">' + data.all_country_code[i] + '</option>';
                    }
                    
                    $('#doc_tab_country_code').html(country_code_source); 
                } else {
                    
                    console.error('Error: Data for dropdowns is missing');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading data:', status, error);
            }
        });
    }

    // function getdocLicenseMonitorData() {
    //     $.post({
    //         url: "process?action=Load_Get_All_Doc_License_Monitor_Data", // URL to fetch data
    //         type: 'POST',
    //         data: {
    //             con_id: cargohub.record_process.config.id,  
    //             track: cargohub.record_process.config.track
    //         },
    //         success: function (data) {
    //             if (data.group_data && data.all_country_code) {
    //                 var group_data_source = '<option value="">Select License Type</option>'; // Default option
    //                 for (var i in data.group_data) {
    //                     group_data_source += '<option value="' + data.group_data[i].name + '">' + data.group_data[i].name + '</option>';
    //                 }
    //                 $('#doc_tab_license_type').html(group_data_source); 
    
    //                 var country_code_source = '<option value="">Select Country Code</option>'; // Default option
    //                 for (var i in data.all_country_code) {
    //                     country_code_source += '<option value="' + data.all_country_code[i] + '">' + data.all_country_code[i] + '</option>';
    //                 }
    //                 $('#doc_tab_country_code').html(country_code_source); 
    //             } else {
    //                 console.error('Error: Data for dropdowns is missing');
    //             }
    //         },
    //         error: function(xhr, status, error) {
    //             console.error('Error loading data:', status, error);
    //         }
    //     });
    // }
    
    
        // function getdocLicenseMonitorData() {
        //     $.post({
        //         url: "process?action=Load_Get_All_Doc_License_Monitor_Data",
        //         type: 'POST',
        //         data: {
        //             con_id: cargohub.record_process.config.id,
        //             track: cargohub.record_process.config.track
        //         },
        //         async: false,
        //         success: function (data) {
        //             $("._doccumentation_tab_list_license_monitor_record_table").html(data.data);


        //             var group_data_source = [];
        //             for (var i in data.group_data) {
        //                 group_data_source.push({
        //                     "key": data.group_data[i].name,
        //                     "text": data.group_data[i].name
        //                 });
        //             }
        //             $("#doc_tab_license_type").jqxDropDownList({
        //                 theme: cargohub.jqxtheme,
        //                 source: group_data_source,
        //                 width: 320, height: 23,
        //                 dropDownHeight: 222,
        //                 valueMember: "key", displayMember: "text",
        //                 filterable: true, searchMode: 'containsignorecase'
        //             });


        //             var country_code_source = [];
        //             for (var i in data.all_country_code) {
        //                 country_code_source.push({
        //                     "key": data.all_country_code[i],
        //                     "text": data.all_country_code[i]
        //                 });
        //             }
        //             $("#doc_tab_country_code").jqxDropDownList({
        //                 theme: cargohub.jqxtheme,
        //                 source: country_code_source,
        //                 width: 320, height: 23,
        //                 dropDownHeight: 222,
        //                 valueMember: "key", displayMember: "text",
        //                 filterable: true, searchMode: 'containsignorecase'
        //             });
        //         },
        //     });
        // }




        
        
//        function getLicenseTypeData() {
//            $.post({
//                url: "process?action=Get_Groups?rt_id=6",
//                type: 'GET',
//                
//                async: false,
//                success: function (data) {
//                    console.log(data);
//                    
//                },
//            });
//        }
//  created by iwi 07062024 | end

// created by iwi 17062024 | start
// alert('test');
// $("#xfd_concl").val("cnf");

// 21062024 changes by iwi | start
$(document).on('change', '.conclusion-value', function (e) {

    var score_sc_class = $(this).val();
    var score_sc_class_val = '';
    switch (score_sc_class) {
        case 'fdnn':
            score_sc_class_val = 'z';
            break;
        case 'loct':
            score_sc_class_val = '1';
            break;
        case 'obny':
            score_sc_class_val = '2';
            break;
        case 'cnf':
            score_sc_class_val = '3';
            break;
        case 'fdny':
            score_sc_class_val = '4';
            break;
        case 'obyn':
            score_sc_class_val = '5';
            break;
        case 'na':
            score_sc_class_val = 'na';
            break;
        default:
            score_sc_class_val = '';
            break;
    }
    $('input:radio[name="xfd[score_sc]"]').prop('checked', false);
    $('input:radio[name="xfd[score_sc]"][value="'+score_sc_class_val+'"]').prop('checked', true);
});

$(document).on('click', '.score_sc_class', function (e) {
    var score_sc_class = $(this).val();
    var score_sc_class_val = '';
    switch (score_sc_class){
        case 'z':
            score_sc_class_val = 'fdnn';
            break;
        case '1':
            score_sc_class_val = 'loct';
            break;
        case '2':
            score_sc_class_val = 'obny';
            break;
        case '3':
            score_sc_class_val = 'cnf';
            break;
        case '4':
            score_sc_class_val = 'fdny';
            break;
        case '5':
            score_sc_class_val = 'obyn';
            break;
        case 'na':
            score_sc_class_val = 'na';
            break;
        default:
            score_sc_class_val = '';
            break;
    }
    $("#xfd_concl").val(score_sc_class_val);
});
// 21062024 changes by iwi | end
 

// 18072024 changes by iwi | start
// check show_license_monitor_sec_in_doc_tab id exist or not
if ($("#license_monitor_sec_in_doc_tab").length) {
    // alert("ok");

    // $('#doc_tab_country_code').jqxDropDownList('selectIndex', 2);

    // $("#doc_tab_country_code").jqxDropDownList({
    //     theme: cargohub.jqxtheme,
    //     source: templates_Source,
    //     width: 320, height: 23,
    //     dropDownHeight: 222,
    //     // valueMember: "key", displayMember: "text",
    //     filterable: true, searchMode: 'containsignorecase'
    // });



  
    // iwi license enhancement in doc start
    // $(document).on('change', '#doc_tab_country_code, #doc_tab_license_type', function () {
    //     // $(document).on('click', '.searchLicenseData', function (e) {
    //         var license_type = $("#doc_tab_license_type").val();
    //         var country_code = $("#doc_tab_country_code").val();
    //         console.log(license_type);
    //         console.log(country_code);
    //         // Validation for empty fields
    //         // if ($(this).val() === '')
    //         //     if (license_type == '') {
    //         //     Swal.fire({
    //         //         title: "",
    //         //         text: 'Please enter license type',
    //         //         icon: "error"
    //         //     });
    //         //     return false;
    //         // }
    //         // // if (!country_code) {
    //         //   else if (country_code == '') {
    //         //     Swal.fire({
    //         //         title: "",
    //         //         text: 'Please enter country code',
    //         //         icon: "error"
    //         //     });
    //         //     return false;
    //         // }
    //         // $("#get_all_license_record").css("display", "block");
    //         $.post({
    //             url: "/issue/list?action=Load_Get_All_License_Monitor_Data_By_Doc_tab",
    //             type: 'POST',
    //             data: {
    //                 con_id: cargohub.record_process.config.id,
    //                 track: cargohub.record_process.config.track,
    //                 country_code: country_code,
    //                 license_type: license_type
    //             },
    //             success: function (data) {
    //                 console.log(data);  // Log the entire data object
        
    //                 // Clear previous options in the dropdown
    //                // $("select#get_all_license_record1").html('<option value="">Select License Record</option>');
        
    //                 // Check if the data is properly formatted and contains records
    //                 if (data.data && data.data.length > 0) {
                        
    //                     var license_record_source = '';
    //                     for (var i in data.data) {
    //                         license_record_source += '<option value="' + data.data[i].id + '">' + data.data[i].track + '</option>';
    //                         // console.log(data.data[i].priority_key);
    //                     }
                        
    //                     $("select#get_all_license_record").html(license_record_source);
    //                 } else {
    //                     $("select#get_all_license_record").html('<option value="">Select License Record</option>');
    //                     Swal.fire({
    //                         title: "",
    //                         text: 'No license records found',
    //                         icon: "info"
    //                     });
    //                 }
    
    //                 // var license_record_source = [];
    //                 // for(var i in data.data){
    //                 //     license_record_source.push({
    //                 //         "key": data.data[i].id,
    //                 //         "text": data.data[i].track
    //                 //     });
    //                 // }
    //                 // console.log("license_record_source",license_record_source);
    //                 // $("#get_all_license_record").jqxDropDownList({
    //                 //     theme: cargohub.jqxtheme,
    //                 //     source: license_record_source,
    //                 //     width: 220, height: 23,
    //                 //     dropDownHeight: 222,
    //                 //     valueMember: "key", displayMember: "text",
    //                 //     filterable: true, searchMode: 'containsignorecase'
    //                 // });
    //             },
    //             error: function (xhr, status, error) {
    //                 Swal.fire({
    //                     title: "Error",
    //                     text: "An error occurred while fetching data.",
    //                     icon: "error"
    //                 });
    //             }
    //         });
    //     });
    // iwi license enhancement in doc end
//     $(document).on('change', '#doc_tab_country_code, #doc_tab_license_type', function () {
//     var license_type = $(".license_link_doccumentation select#doc_tab_license_type").val();
//     var country_code = $(".license_link_doccumentation select#doc_tab_country_code").val();

//     $.post({
//         url: "/issue/list?action=Load_Get_All_License_Monitor_Data_By_Doc_tab",
//         type: 'POST',
//         data: {
//             con_id: cargohub.record_process.config.id,
//             track: cargohub.record_process.config.track,
//             country_code: country_code,
//             license_type: license_type
//         },
//         success: function (data) {
//             console.log(data);

//             var tableBody = $("#license_records_table tbody");
//             tableBody.empty(); // Clear existing rows

//             if (data.data && data.data.length > 0) {
//                 var rows = ``;

//                 for (var i in data.data) {  
//                     rows += `<tr>
//                         <td style="padding: 8px;  width: 5%; text-align: center;">
//                             <input type="checkbox" class="row_checkbox" style="cursor: pointer;">
//                         </td>
//                         <td style="padding: 8px;  width: 15%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
//                             <a href="/record/process:${data.data[i].track}#panels" 
//                                style="font-size: 12px; color:blue;" target="_blank">
//                                ${data.data[i].track}
//                             </a>
//                         </td>
//                         <td style="padding: 8px;  width: 40%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" 
//                             title="${data.data[i].cache.primary || 'N/A'}">
//                             ${data.data[i].cache.primary || 'N/A'}
//                         </td> 
//                         <td style="padding: 8px;  width: 20%; word-wrap: break-word;">
//                             ${data.data[i].xfields.chapter || 'N/A'}
//                         </td>
//                     </tr>`;
                    
                    
                    
//                        // <td style="padding: 8px; border: 1px solid #ccc; width: 10%; white-space: nowrap;">
//                            // ${data.data[i].priority_key || 'N/A'}
//                        // </td>
//                        // <td style="padding: 8px; border: 1px solid #ccc; width: 10%; white-space: nowrap;">
//                         //    ${data.data[i].status || 'N/A'}
//                        // </td>
//                 }

//                 tableBody.append(rows);
//             } else {
//                 tableBody.append('<tr><td colspan="7" style="padding: 10px; text-align: center;">No license records found</td></tr>');
//                 Swal.fire({ title: "", text: 'No license records found', icon: "info",background: "#007bff !important"});
//             }
//         },
//         error: function (xhr, status, error) {
//             Swal.fire({ title: "Error", text: "An error occurred while fetching data.", icon: "error" });
//         }
//     });
// });
   


$(document).on('change', '#doc_tab_country_code, #doc_tab_license_type', function () {
    var license_type = $(".license_link_doccumentation select#doc_tab_license_type").val();
    var country_code = $(".license_link_doccumentation select#doc_tab_country_code").val();

    $.post({
        url: "/issue/list?action=Load_Get_All_License_Monitor_Data_By_Doc_tab",
        type: 'POST',
        data: {
            con_id: cargohub.record_process.config.id,
            track: cargohub.record_process.config.track,
            country_code: country_code,
            license_type: license_type
        },
        success: function (data) {
            console.log(data);



            // Ensure it's an object. If it's a string, parse it.
                if (typeof data === "string") {
                    try {
                        data = JSON.parse(data);
                    } catch (e) {
                        console.error("Invalid JSON:", e);
                        return;
                    }
                }  

            var tableBody = $("#license_records_table tbody");
            tableBody.empty(); // Clear existing rows

             // Clear previous errors
             $('#license_type_error').html('');
             $('#country_code_error').html('');
 
             // If there are errors, display them
             if (data.errors) {
                 // Ensure the error div is visible
                 $('#license_type_error').css('display', 'block');
                 $('#country_code_error').css('display', 'block');
                 
                 // Display errors under the respective fields if any exist
                 if (data.errors.country_code) {
                     $('#country_code_error').html(data.errors.country_code);
                 }
                 if (data.errors.license_type) {
                     $('#license_type_error').html(data.errors.license_type);
                 }
                 
                 return; // Prevent further processing
             }
        

            // if (data.error) {
                
            //     tableBody.append('<tr><td colspan="7" style="padding: 10px; text-align: center;">' + data.error + '</td></tr>');
            //     Swal.fire({ title: "", text: data.error, icon: "info", background: "#007bff !important"});
            // } else 
            
           else if (data.data && data.data.length > 0) {
              
                var rows = ``;
                for (var i in data.data) {
                    rows += `<tr>
                        <td style="padding: 8px; width: 5%; text-align: center;">
                            <input type="checkbox" class="row_checkbox" name="link_license[]" value="${data.data[i].id}"   style="cursor: pointer;">
                        </td>
                        <td style="padding: 8px; width: 15%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <a href="/record/process:${data.data[i].track}#panels" style="font-size: 12px; color:blue;" target="_blank">
                                ${data.data[i].track}
                            </a>
                        </td>
                        <td style="padding: 8px; width: 40%;  overflow: hidden; text-overflow: ellipsis;" title="${data.data[i].cache.primary || 'N/A'}">
                            ${data.data[i].cache.primary || 'N/A'}
                        </td>
                        <td style="padding: 8px; width: 20%; word-wrap: break-word;">
                            ${data.data[i].xfields.chp_no || 'N/A'} - ${data.data[i].xfields.chapter || 'N/A'}
                        </td>
                        
                    </tr>`;
                }
                tableBody.append(rows);
            } else {
                
                tableBody.append('<tr><td colspan="7" style="padding: 10px; text-align: center;">No license records found</td></tr>');
                Swal.fire({ title: "", text: 'No license records found', icon: "info", background: "#007bff !important"});
            }
        },
        error: function (xhr, status, error) {
            
            Swal.fire({ title: "Error", text: "An error occurred while fetching data.", icon: "error" });
        }
    });
});


   
    
    // Select Checkbox Functionality
    // $(document).on('change', '.row_checkbox', function () {
    //     $('.row_checkbox').not(this).prop('checked', false);
    // });
    let selectedTrack = []; // Variable to store the selected track value

    // Ensure only one checkbox can be selected at a time and store track value
    $(document).on('change', '.row_checkbox', function () {
        //$('.row_checkbox').not(this).prop('checked', false); // Uncheck other checkboxes

        var keys = {};


        if ($(this).is(':checked')) {
//            selectedTrack = $(this).closest('tr').find('td:nth-child(2)').text().trim(); // Get Track value
selectedTrack.push($(this).val());
//            keys['id'] = $(this).val();
//            keys['track'] = $(this).data('track');
//            selectedTrack.push(keys)
        } else {
//            selectedTrack = ""; // Reset if unchecked
selectedTrack = selectedTrack.filter(id => id !== $(this).val());
        }

//        console.log(selectedTrack);
    });
    
  
    
    
    
     
  
   
    
    


    //search hide
    // $(document).on('click', '.searchLicenseData', function (e) {
    //     var country_code = $("#doc_tab_country_code").val();
    //     var license_type = $("#doc_tab_license_type").val();
        
    //     if(license_type == ''){
    //         Swal.fire({
    //             title: "",
    //             text: 'Please enter license type',
    //             icon: "error"
    //         });
    //         return false;
    //     }

    //     if(country_code == ''){
    //         Swal.fire({
    //             title: "",
    //             text: 'Please enter country code',
    //             icon: "error"
    //         });
    //         return false;
    //     }

    //     $.post({
    //         url: "/issue/list?action=Load_Get_All_License_Monitor_Data_By_Doc_tab",
    //         type: 'POST',
    //         data: {
    //             con_id: cargohub.record_process.config.id,
    //             track: cargohub.record_process.config.track,
    //             country_code: country_code,
    //             license_type : license_type
    //         },
    //         async: false,
    //         success: function (data) {
                
    //             var license_record_source = [];
    //             for(var i in data.data){
    //                 license_record_source.push({
    //                     "key": data.data[i].id,
    //                     "text": data.data[i].track
    //                 });
    //             }
    //             $("#get_all_license_record").jqxDropDownList({
    //                 theme: cargohub.jqxtheme,
    //                 source: license_record_source,
    //                 width: 220, height: 23,
    //                 dropDownHeight: 222,
    //                 valueMember: "key", displayMember: "text",
    //                 filterable: true, searchMode: 'containsignorecase'
    //             });
    //         },
    //     });
    // });

    // $(document).on('click', '.saveLicenseDataByDocTab',function (e){
    //     var documentLink = cargohub.record_process.config.track;
    //     var license_monitor_id_split = $("#get_all_license_record").val().split('|');
    //     var license_monitor_id = license_monitor_id_split[0];
    //     var license_monitor_track_id = license_monitor_id_split[1];
        
    //     if ($("#get_all_license_record").val() == '') {
    //         Swal.fire({
    //             title: "",
    //             text: 'Please enter document link',
    //             icon: "error"
    //         });
    //         return false;
    //     }

    //     $.post({
    //         url: "process?action=Load_Save_License_Monitor",
    //         type: 'POST',
    //         data: {
    //             documentLink: documentLink,
    //             con_id: license_monitor_id,
    //             track: license_monitor_track_id
    //         },
    //         async: false,
    //         success: function (data) {
    //             if (data.isExist == 'no'){
    //                 Swal.fire({
    //                     title: "",
    //                     text: data.message,
    //                     icon: "error"
    //                 });
    //                 return false;
    //             } else {
    //                 Swal.fire({
    //                     title: "Link added successfully",
    //                     confirmButtonText: "Save",
    //                 }).then((result) => {
    //                     if (result.isConfirmed) {
    //                         location.reload();
    //                     } 
    //                 });
    //             }
    //         },
    //     });

    // });

    $(document).on('click', '#addButton', function () {
        
        if (selectedTrack.length<1) {
            Swal.fire({ title: "Warning", text: "Please select a track before saving.", icon: "warning" });
            return;
        }
    
        console.log("Sending track:", selectedTrack); // Debugging log
        
        
    
        $.post({
            url: "process?action=Save_License_Monitor_Link_To_Documentation",
            type: 'POST',
            data: {
                licenseLink: JSON.stringify(selectedTrack), // Send the selected track value
                doc_id: cargohub.record_process.config.id, // Send the selected track value
                doc_track_id: cargohub.record_process.config.track
            },
            success: function (response) {
                console.log("Response from server:", response); // Debug response
                if (response.success) {
                    Swal.fire({ title: "Success", text: response.message, icon: "success" });
                    getLicenseMonitorData();
                    $("#customModal, #customOverlay").fadeOut();
                } else {
                    Swal.fire({ title: "Error", text: response.message, icon: "error" });
                }
            },
            error: function (xhr, status, error) {
                console.log("Error Details:", xhr.responseText); // Log error details
                Swal.fire({ title: "Error", text: "Failed to save data.", icon: "error" });
            }
        });
    });
    
    

}
// 18072024 changes by iwi | end


// 06092024 changes by iwi | start
$(document).on('change', '.certificate_expiry', function (e) {
    var certificateExpiry = $(this).val();
    if(certificateExpiry == 0){
        $('input[name="xfd[train_exp]"]').val('');
        $('#train_scheduler_exp_date').css('display', 'none'); //09092024 added by iwi
    }
    if(certificateExpiry == 1){
        $('#train_scheduler_exp_date').css('display', 'table-row'); //09092024 added by iwi
    }
});
// 06092024 changes by iwi | end


//  26092024  train_scheduler_classroom_type  addded by iwi start
$(document).on('change', '.train_scheduler_train_loc', function (e) {
    var train_loc = $(this).val();
    if(train_loc == 'Classroom'){
        $('#train_scheduler_classroom_type').css('display', 'table-row');
    }
    else{
        $('input[name="xfd[classroom_type]"]:checked').prop('checked', false);
        $('#train_scheduler_classroom_type').css('display', 'none');
    }
});
$(document).on('change', '.classroom_type', function (e) {
    $('button[data-cmd="save-xf"]').prop('disabled', false);
});
//  26092024  train_scheduler_classroom_type  addded by iwi end 




    // 20112024 Customer Inquiry section work start
    $(document).on('click', '.cargo_related_event', function (e) {
        var cargo_related_event = $(this).val();
        if(cargo_related_event == 'yes'){
            $('.cargo_related_event_if_yes').css('display', 'table-row');
        }else{
            $('input[name="xfd[routing]"]').val('');
            $('#xfd_shipment_type').val('');
            $('input[name="xfd[commodity]"]').val('');
            $('.cargo_related_event_if_yes').css('display', 'none');
        }
    });
    // 20112024 Customer Inquiry section work end


    // 21112024 Training Scheduler Training Date section work 
        var  trainingSchedulerTraineeId = [];
        var  trainingSchedulerTraineeEmail = [];//13032025 Training Scheduler table user email copy
        if ($('#tab_trainee').length) {
            
            $("#_record-docs").on("change", ".row-checkbox", function () {
                let rowIndex = $(this).data("row");
                let rowData = $("#_record-docs").jqxDataTable('getRows')[rowIndex];
                let rowId = rowData.uid; // Get the row's ID
                let rowEmail = rowData.email; // Get the row's Email //13032025 Training Scheduler table user email copy
                let isChecked = $(this).is(":checked");
                
                if (isChecked) {
                    if(!trainingSchedulerTraineeId.includes(rowId)){
                        trainingSchedulerTraineeId.push(rowId);
                    }
                } else {
                    trainingSchedulerTraineeId = trainingSchedulerTraineeId.filter(id => id !== rowId);
                }

                //13032025 Training Scheduler table user email copy
                if (isChecked) {
                    if(!trainingSchedulerTraineeEmail.includes(rowEmail)){
                        trainingSchedulerTraineeEmail.push(rowEmail);
                    }
                } else {
                    trainingSchedulerTraineeEmail = trainingSchedulerTraineeEmail.filter(email => email !== rowEmail);
                }
                //13032025 Training Scheduler table user email copy

                //console.log("trainingSchedulerTraineeEmail  6666 ",trainingSchedulerTraineeEmail);

                if(trainingSchedulerTraineeId.length > 0){
                    $('#trainee-result-update-ops').css('display', 'block');
                    $('#trainee-email-copy-ops').css('display', 'block');//13032025 Training Scheduler table user email copy
                }else{
                    
                    $('#trainee-result-update-ops').css('display', 'none');
                    $('#trainee-email-copy-ops').css('display', 'none');//13032025 Training Scheduler table user email copy
                }

            });

            // 02122024 Training Scheduler upload multiple files start
            $(document).on('click', '.tr_scheduler_tab_single_trainee_file_id', function (e) {

                if (!confirm("Are you sure you want to delete this files ?"))
                return;

                


                const dataId = $(this).data('id');
                const parentDiv = $(`#${dataId}`);
                parentDiv.remove();


                // const currentValue = $('#current_trainee_all_files_name').val();
                // const fileArray = currentValue.split(',');
                // console.log("fileArray fileArray:", fileArray);
                // if (fileArray.includes(dataId)) {
                //     console.log("dataId dataId:", dataId);
                //     const updatedArray = fileArray.filter(file => file !== dataId);
                //     console.log("updatedArray:", updatedArray);
                //     const updatedValue = updatedArray.join(',');
                //     console.log("updatedValue:", updatedValue);
                //     // currentValue.val(updatedValue);
                //     $('#current_trainee_all_files_name').val(updatedValue);
                //     console.log("Updated Value:", updatedValue);
                // }

                const targetValue = dataId; // Value to check and remove
                const inputField = $('#current_trainee_all_files_name'); // Select the input field
                const currentValue = inputField.val(); // Get the current value of the input field
            
                console.log("Current Value:", currentValue); // Debug: Log the current value
            
                if (currentValue.trim() !== "") {
                    // Split the value into an array, trimming spaces
                    const fileArray = currentValue.split(',').map(file => Number(file.trim()));

                    console.log("File Array:", fileArray); // Debug: Log the split array
                    // Check if the value exists
                    if (fileArray.includes(targetValue)) {
                        console.log(`${targetValue} exists. Removing it...`);
                        // Remove the value
                        const updatedArray = fileArray.filter(file => file !== targetValue);
                        // Join the array back into a comma-separated string and update the input field
                        const updatedValue = updatedArray.join(',');
                        inputField.val(updatedValue);
                        console.log("Updated Value:", updatedValue); // Debug: Log the updated value
                    } else {
                        console.log(`${targetValue} does not exist.`);
                    }
                }















                 $.post({
                    url: "process?action=trainee_management_tab_single_Trainee_file_remove",
                    type: 'POST',
                    data: {
                        dataId: dataId
                    },
                    async: false,
                    success: function (data) {
                        console.log("6323 => ",data);
                    },
                });
            });
            // 02122024 Training Scheduler upload multiple files end
            /*
            $('#trainee_all_files').on('change', function() {

                let fileInput = $('#trainee_all_files')[0];
                let file = fileInput.files[0];
                if (!file) {
                    alert('Please select a file!');
                    return;
                }
                let formData = new FormData();
                formData.append('trainee_all_files', file);
                // formData.append('trainee_record_id', master.config.id);
                // formData.append('trainee_track_val', master.config.track);
                formData.append('trainee_management_id',$('#trainee_management_id').val());
                $.ajax({
                    url: "/record/process?action=Save_trainee_management_Trainee_Files",
                    type: 'POST',
                    data: formData,
                    processData: false, 
                    contentType: false,
                    success: function (response) {
                        console.error(" response  :  ", response);
                        
                    },
                    error: function (xhr, status, error) {
                        console.error("Error uploading PDF", error);
                    }
                });
            });
            */




        }
     // 21112024 Training Scheduler Training Date section work 
   
    // $(document).on('click', '.isCreateTaskDivSec', function (e) {
                
    //     const $checkbox = $("#isCreateTask");
    //     console.log(" 6569 => test : ",$checkbox);
    //     $checkbox.prop("checked", !$checkbox.prop("checked"));
    // });

    $(document).on('click', '.isCreateTaskDivSec', function (e) {
        if (!$(e.target).is('#isCreateTask')) { 
            const $checkbox = $("#isCreateTask");
            $checkbox.prop("checked", !$checkbox.prop("checked"));
        }
    });
    
// function(x) {
// debugger;
// console.log(x.active);
// cargohub.alert("test data");
// cargohub.alert("test data1");
// if(x.success) {
// cargohub.alert("test");
// }else{
// cargohub.alert('data1111');
// }
// else
// cargohub.alert(x.reason, "Message from server", "information.png");


// raj work

// function filterOptions(event) {
//   const searchValue = event.target.value.toLowerCase();
//   const selectOptions = document.querySelectorAll('#selectDropdown option');
//   selectOptions.forEach(option => {
//     const text = option.textContent.toLowerCase();
//     if (text.includes(searchValue)) {
//       option.style.display = 'block';
//     } else {
//       option.style.display = 'none';
//     }
//   });
// }

// document.addEventListener('click', function(event) {
//   const selectWrapper = document.querySelector('.select-wrapper');
//   const selectDropdown = document.querySelector('.select-dropdown');
//   if (!selectWrapper.contains(event.target)) {
//     selectDropdown.classList.remove('show');
//   } else {
//     selectDropdown.classList.add('show');
//   }
// });

// document.querySelector('#internal_user').addEventListener('change', function() {
//     const selectedOption = this.options[this.selectedIndex].text;
//     document.querySelector('.select-search').value = selectedOption;
//     document.querySelector('.select-dropdown').classList.remove('show');
//   });


// raj work



// 05032025 Training Scheduler get user id 
$("#internal_user_ids").on('change', function (event) {
    var args = event.args;
    if (args) {
        var selectedId = $("#internal_user_ids").jqxDropDownList('getSelectedItem').value;
        var trainee_email = $("#trainee_email").val();
        
        $.ajax({
            url: "process?action=training_scheduler_trainee_email_by_id",
            type: 'POST',
            data: { userId: selectedId },
            async: false,
            success: function (data) {
                var user_email = data.user_email;
                if(user_email !=''){
                    if(trainee_email ==''){
                        $("#trainee_email").val(user_email);
                    }
                }
            },
            cache: false,
            contentType: "application/x-www-form-urlencoded", // Default for POST requests
            processData: true // Allows jQuery to correctly format the data
        });
        
       
    }


});

// 13032025 Training Scheduler table user email start
$(document).on('click', '.email_lable', function (e) {
    var email = $(this).data("email");
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(email).then(function() {
            alert("Email copied: " + email);
        }).catch(function(err) {
            console.error("Error copying email: ", err);
        });
    } else {
        var tempInput = $("<input>");
        $("body").append(tempInput);
        tempInput.val(email).select();
        try {
            var successful = document.execCommand("copy");
            var msg = successful ? "Email copied: " + email : "Failed to copy email.";
            cargohub.alert(msg);
        } catch (err) {
            console.error("Fallback: Oops, unable to copy", err);
        }
        tempInput.remove();
    }
}); 



// 20032025 file upload alert section work | start
$(document).ready(function () {
    function checkDuplicateFiles() {
        let singleFile = $("#trainee_files")[0].files[0]; // Get the selected file in trainee_files
        let multipleFiles = $("#trainee_all_files")[0].files; // Get all files in trainee_all_files

        if (singleFile) {
            for (let i = 0; i < multipleFiles.length; i++) {
                if (singleFile.name === multipleFiles[i].name && singleFile.size === multipleFiles[i].size) {
                    cargohub.alert("Error: The file selected is already present in Certificate. Please choose a different file.");
                    $("#trainee_files").val(""); // Clear the single file input
                    return false;
                }
            }
        }
        return true;
    }

    // Check when either file input changes
    $("#trainee_files, #trainee_all_files").change(function () {
        checkDuplicateFiles();
    });
 
});
function getLicenseMonitorData() {
    $.post({
        url: "process?action=Load_Get_All_License_Monitor",
        type: 'POST',
        data: {
            con_id: cargohub.record_process.config.id,
            track: cargohub.record_process.config.track,
            type_id: cargohub.record_process.config.type.base_id
        },
        async: false,
        success: function (data) {
            console.log(data);
//            $(".doccumentation_tab_list_license_monitor_record_table").html(data.data);
            $(".license_monitor_record_section_table").html(data.data);
        },
    });
}
// 20032025 file upload alert section work | end

function deleteLicenseMonitorData(lm_id_val) {

    //        if (confirm('Are you sure you want to delete this?')) {
    //            $.post({
    //                url: "process?action=Load_Delete_License_Monitor",
    //                type: 'POST',
    //                data: {
    //                    lm_id: lm_id_val,
    //                    con_id: cargohub.record_process.config.id,
    //                    track: cargohub.record_process.config.track
    //                },
    //                async: false,
    //                success: function (data) {
    //                    if(data.success == true){
    //                        getLicenseMonitorData();
    //                        alert('Data deleted successfully');
    //                    }
    //                },
    //            });
    //        }






   Swal.fire({
  title: "Are you sure you want to delete this ?",
  icon: "warning",
  showDenyButton: true,
  showCancelButton: false,
  denyButtonText: "No",
  confirmButtonText: "Yes",
  reverseButtons: true,
}).then((result) => {
        if (result.isConfirmed) {
            Swal.showLoading();
            $.post({
                url: "process?action=Load_Delete_License_Monitor",
                type: 'POST',
                data: {
                    lm_id: lm_id_val,
                    con_id: cargohub.record_process.config.id,
                    track: cargohub.record_process.config.track
                },
                async: false,
                success: function (data) {
                    if (data.success == true) {
                        // 17062024 created by iwi | start
                        $('.doc_ref_value').html(data.ttl_doc_link);
                        // 17062024 created by iwi | end
                        getLicenseMonitorData();
                        Swal.fire({
                            title: "",
                            text: "Data deleted successfully",
                            icon: "success"
                        });
                    }
                },
                error: function (xhr) {
                    console.log(xhr.responseText); // Debugging error response
                }
            });


        }
    });




}

$(document).ready(function() {
    $('#capa-by-quality-event-create-additional').on('click', function() {
        $('[data-op="capa-by-quality-event-create"]').trigger('click');
        $('[data-op="capa-by-quality-event-create"]').focus();
    });
});
 
// const currSelect = document.getElementById('xfd_pur_price_curr');
// if (currSelect) {
//     currSelect.querySelector('option[value=""]').removeAttribute('selected');
//     const eurOption = currSelect.querySelector('option[value="978"]');
//     if (eurOption) eurOption.selected = true;
//     console.log('EUR set:', currSelect.value); 
// }



function calculateNoticeDate() {

    var endDate = $('#contract_create_end_date').val();
    var noticeDays = parseInt($('#notice_period').val(), 10);

    if (!endDate || isNaN(noticeDays)) {
        $('#contract_notice_date')
            .val('')
            .prop('disabled', true);
        return;
    }

    // Parse yyyy-mm-dd manually (avoids timezone issues)
    var parts = endDate.split('-');
    var noticeDate = new Date(parts[0], parts[1] - 1, parts[2]);

    // Subtract notice days
    noticeDate.setDate(noticeDate.getDate() - noticeDays);

    var yyyy = noticeDate.getFullYear();
    var mm = String(noticeDate.getMonth() + 1).padStart(2, '0');
    var dd = String(noticeDate.getDate()).padStart(2, '0');

    $('#contract_notice_date')
        .val(`${yyyy}-${mm}-${dd}`)
        .prop('disabled', false);
}                                                //Notice Period Dropdown_end


 //Auto Renewal Dropdown start
                $("#contract_period").on("change", function () {
                if ($(this).val() === "Auto renewal") {
                    $("#auto_renewal_wrapper").show();
                    $("#contract_period_wrapper").css("flex", "1");
                } else {
                    $("#auto_renewal_wrapper").hide();
                    $("#contract_period_wrapper").css("flex", "100%");
                    $("#auto_renewal").val("");
                }

            }).trigger("change");

//        
              //Auto Renewal Dropdown end
              
              //              IWI Contract Management form update 03072026 start
  $('#contract_create_month_value,#contract_create_end_date,#contract_create_start_date').on('change', function () {
    calculateYearValue();
});
function calculateYearValue() {
    const startDate = $('#contract_create_start_date').val();
    const endDate = $('#contract_create_end_date').val();
    const monthValue = parseFloat($('#contract_create_month_value').val()) || 0;

    let result = 0;

    if (startDate && endDate) {
        const sDate = new Date(startDate);
        const eDate = new Date(endDate);

        // Difference in days (inclusive)
        const diffDays = Math.floor((eDate - sDate) / (1000 * 60 * 60 * 24)) + 1;

        // Every started 30-day period counts as one month
        const totalMonths = Math.ceil(diffDays / 30);

        result = monthValue * Math.min(totalMonths, 12);

        console.log(diffDays, totalMonths);
    }

    $('#contract_create_year_value').val(result);
}

//IWI Contract Management form update 03072026 end 
//   IWI Adding assets from Supplier control contract section 28072026 start           
$("#btnAddAsset").on("click", function(){

    var html = '';

    html += '<div class="asset-row">';

        html += '<div class="contract-form-group">';
            html += '<label>Asset Name</label>';
            html += '<input type="text" class="contract-input-field" name="asset_name[]">';
        html += '</div>';

        html += '<div class="contract-form-group">';
            html += '<label>Brand Name</label>';
            html += '<input type="text" class="contract-input-field" name="brand_name[]">';
        html += '</div>';

        html += '<div class="contract-form-group">';
            html += '<label>Location Code</label>';
            html += '<input type="text" class="contract-input-field" name="location_code[]">';
        html += '</div>';

        html += '<div class="contract-form-group">';
            html += '<label>Department</label>';
            html += '<input type="text" class="contract-input-field" name="department[]">';
        html += '</div>';

        html += '<button type="button" class="asset-remove">';
            html += '<i class="fa fa-trash"></i>';
        html += '</button>';

    html += '</div>';

    $("#assetContainer").append(html);

});

$(document).on("click",".asset-remove",function(){
    $(this).closest(".asset-row").remove();
});


//IWI Adding assets from Supplier control contract section 28072026 end

// files upload in contract management form_start

$(document).on('click', '#contract-file-selector', function(e) {

    e.stopPropagation();

    $('#contract-file-dropdown').toggle();

});


$(document).on('click', '.file-option', function(e) {

    e.stopPropagation();

    var fileId = $(this).attr('data-value');
    var fileName = $(this).find('.file-option-name').text();

    /*
     * Set actual select value
     */
    $('#contract-attach-file').val(fileId);

    /*
     * Change visible selected text
     */
    $('#contract-file-selector .selected-file-text').text(fileName);

    /*
     * Close dropdown
     */
    $('#contract-file-dropdown').hide();
});


$(document).on('click', function() {

    $('#contract-file-dropdown').hide();

});
// files upload in contract management form_end