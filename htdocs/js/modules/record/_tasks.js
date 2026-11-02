/**
 * Module "Record Process"->Section "Tasks", interface for the Adding/Editing and Operating the attached Tasks
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules.Record.Tasks
 * @version 2021.02.09
 */
cargohub.record_tasks = donrey.BASE._extend(function() { 
    // private
    var self = this,
        c = null,
        today = moment().set({hour: 0, minute: 0, second: 0, millisecond: 0}),
        master = cargohub.record_process,
        user_session = cargohub.application.user_Get_Session(),
        tasks_manager_priv = cargohub.application.user_Test_Privileges('tasks_manager'), // Full edit ability on tasks that are visible ( Create, Edit, Delete )

        // Data from the table containing currently selected row. Used for View, Edit, Delete - Set on any row operation.
        selected_row = null,

        // @DEPRECATED: Becomes true when something is typed in either Title or Description, or a template is used. Becomes false when both are emptied
        task_is_set = false,

        // Task Editor Expander control containing various controls, including TinyMCE "rich_editor"
        $task_editor = $("#tab_tasks #task-editor"),
        rich_editor_ready = false,		// Indicates if TinyMCE component is already loaded and the editor used here is initialized
        open_row_id = null,				// ID of the row that has been opened (selected one might have changed in the mean time) - Only has a value when opening existing row (not for create)
        // CONSTANTS
        RICH_EDITOR_INSTANCE_KEY = "taskedit",
        DEFAULT_TASK_ASSIGN = 'user',	// Which one of the options is the default assignment mode - Must be set !
        assign_Autocheck = false,
        NOTIFICATION_SETUP = [
            {value: "d,3,1,1", text: "Due = in 3 days, Rem = the day before"},
            {value: "d,7,2,1", text: "Due = in a week, Rem = two days before"},
            {value: "d,14,3,3", text: "Due = two weeks, Rem = 3 days before"},
            {value: "M,1,7,3", text: "Due = one month, Rem = a week before"},
            {value: "M,2,7,7", text: "Due = two months, Rem = a week before"},
            {value: "M,3,7,7", text: "Due = 3 months, Rem = a week before"},
            {value: "0", text: "<span class='gray'>Custom values (template/selected)</span>"},
            {value: "-", text: "<hr />", disabled: true},
            {value: "R", text: "<div class='center red'>No notifications (reset)</div>"},
        ],
        OVERDUE_OPTIONS = [
            {value: "1", text: "Every day after due date"},
            {value: "3", text: "Every 3 days after due date"},
            {value: "7", text: "Every week after due date"},
            {value: "30", text: "Every 30 days"},
            {value: "0", text: "<span class='gray'>Disabled (no extra notifications)</span>"}
        ],
        OVERDUE_LIMIT_OPTIONS = [
            {value: "999", text: "Unlimited"},
            {value: "25", text: "25 times"},
            {value: "10", text: "Ten times"},
            {value: "5", text: "Five times"},
            {value: "3", text: "Three times"},
            {value: "2", text: "Only twice"}
        ],
        QUICK_SETUP_TEXT = "(Quick setup the Due & Reminder dates)",
        STATUS_TITLE = {
            assigned: "Assigned",
            inprogress: "Active",
            onhold: "Paused",
            completed: "Completed",
            failed: "Failed",
            expired: "Disabled"
        },
        STATUS_TRANSLATE = {
            assigned: "Assigned (ready to start)",
            inprogress: "Activity is in progress",
            onhold: "Activity paused (on hold)",
            completed: "Task has been completed",
            failed: "Task failed (unable to complete)",
            expired: "System disabled | Very old task"//,
//		closed: "Task has been hidden from view"
        },
        // Work variables for when editor is open (they may be necessary for Save operation)
        editor_mode = '',				// Non-false when Editor is open (in any of the modes) - Save/Close buttons should be visible
        editor_nonempty = false,		// Editor is open and non non-empty, show a warning when trying to open another row or select a template
        assign_mode,					// During create or master-edit, contains the specific assignment type option (as other controls depend on it)
        notification_type = 'email',	// Which type of notification has been selected for the assignee: email (default), sys (don't spam)

        // Status related
        available_statuses = [],		// Array of objects defining the status buttongroup set (contains information for the button template, and is also used on the select event)
        $status_ctrl = null,			// Current status buttongroup (used to read value on saving)
        /**
         * Reads the existing status value from the status buttongroup
         */
        helper_Status_GetVal = function()
        {
            var sel = $status_ctrl.jqxButtonGroup('getSelection');
            if(sel === undefined)
                return null;
            return available_statuses[sel].status;
        },

        // Task Viewer Expander control doesn't contain any active elements, but it can be contracted and expanded just like the full editor
        $task_viewer = $("#tab_tasks #task-viewer"),

        // Source for the DataTable
        tasks_Source = new $.jqx.dataAdapter({
            url: "/global/task",
            data: {
                action: "Get_List",
                link_type: "record",
                link_id: master.config.id,
                show_closed: false	// Only show currently open/visible tasks ; (They can be shown with a click on the "Show Closed" checkbox)
            },
            datatype: "json",
            datafields: [
                {name: 'org_id', type: 'string', map: (cargohub.application.user_Is_Admin()? 'org>id': 'org_id')},
                {name: 'org_name', type: 'string', map: 'org>name'},
                {name: 'assign_id', map: 'assign>id', type: 'int'},
                {name: 'assign_mode', type: 'string'},
                {name: 'delegate_id', type: 'int'},
                {name: 'delegate_name', map: 'delegate>name'},	// Delegate user (for department tasks)
                {name: 'delegate_email', map: 'delegate>email'},
                {name: 'assign_name', map: 'assign>name'},	// This is either user name, department name or usergroup name
                {name: 'creator_id', map: 'added_by>id', type: 'int'},
                {name: 'creator_name', map: 'added_by>name'},	// Creator is always an user
                {name: 'creator_email', map: 'added_by>email'},
                {name: 'dt', type: 'date'},
                {name: 'dl_action_id', type: 'int'},	// Necessary to determine the state of the action (when editing)
                {name: 'deadline', type: 'date'},
                {name: 'rem_action_id', type: 'int'},	// ... same
                {name: 'reminder', type: 'date'},
                {name: 'ovd_action_id', type: 'int'},	// ... same
                {name: 'ovd_intv', type: 'int'},
                {name: 'ovd_repeat', type: 'int'},	// Number of times the overdue notification has been sent
                {name: 'ovd_rep_limit', type: 'int'},
                {name: 'action_stage', type: 'string'},
                {name: 'action_ntf_type', type: 'string'},
                {name: 'status', type: 'string'},
                {name: 'title', type: 'string'},
                {name: 'progress', type: 'int'},
                {name: 'description', type: 'string'},
				{name: 'tDate',type:'date'},
                {name: 'task_ref', type: 'string'},
                {name: 'task_type', type: 'string'},
            ],
            id: "id",
            // Initial Sorting
            sortcolumn: 'status',
            sortdirection: 'asc'
        }),

        // Templates selector (dropdown)
        templates_Source = new $.jqx.dataAdapter({
            url: "/global/operations",
            datatype: "json",
            data: {
                action: "Get_Templates",
                role: "task",		// Load Task templates for this control
                include_default: false,
                rt_id: (master.config.type.base_id && master.config.type.base_id != master.config.type.id)? master.config.type.base_id: master.config.type.real_id
            },
            datafields: [
                {name: 'id', type: 'int'},
                {name: 'description', type: 'string'}
            ],
            id: "id"
        }),

        // Departments selector for task assign/reassign (dropdown)
        departments_Source = new $.jqx.dataAdapter({
            url: "/manager/department?action=get_list",
            data: {
                track: master.config.track,
                org_id: master.config.perspective
            },
            datatype: "json",
            datafields: [
                {name: 'id', type: 'int'},
                {name: 'name', type: 'string'},
                {name: 'count_members', type: 'int'}
            ],
            id: "id"
        }),

        // Department user selector for assign/reassign (combobox)
        members_Source = new $.jqx.dataAdapter({
            datatype: "json",
            datafields: [
                {name: 'id', type: 'int'},
                {name: 'name', type: 'string'}
            ],
            id: "id"
        }),

        // List of task updates
        task_history_Source = new $.jqx.dataAdapter({
            data: {},
            datatype: "json",
            datafields: [
                {name: 'dt', type: 'date'},
                {name: 'change_by',  map: 'user>name', type: 'string'},
                {name: 'status_change', type: 'string'},
                {name: 'update_text', type: 'string'},
                {name: 'comment', type: 'string'}
            ],
            // Initial Sorting
            sortcolumn: 'dt',
            sortdirection: 'desc'
        }),

        /**
         * A row has been selected - Store information in preparation for a double-click ( for Viewing or Editing ), or for clicking on one of the toolbar buttons
         */
        select_Row = function(row)
        {
            if(!selected_row) {
                donrey.cancel_Delay('hide-operations_bar');
                $('#tab_tasks .tasks-row-ops button').jqxButton({disabled: false});
                $('#tab_tasks .tasks-row-ops').fadeIn();
            }
            selected_row = row;
        },

        /**
         * Event: Table selection is cleared - Make the toolbar buttons dissapear
         */
        select_Clear = function()
        {
            selected_row = null;
            $('#tab_tasks .tasks-row-ops button').jqxButton({disabled: true});
            donrey.single_Delay('hide-operations_bar', function() {
                $('#tab_tasks .tasks-row-ops').fadeOut();
            }, 500);
        },

        task_editor_Ready = false,	// All controls ready (but not necessarely loaded with data) - If True, the editor opens directly
        tpls_Ready = false,			// True if the Templates selector is loaded and ready
        deps_Ready = false,			// True if the Departments selector is loaded and ready
        history_Ready = false,		// True if the Task History table is loaded and ready
        copy_mode = false,			// Becomes true when the selected task is copied (create new from existing)
        system_change = false,		// Used for some automation of controls to avoid endless loops
        task_viewer_Ready = false,	// Task viewer Expander is ready to be shown

        autoselect_Dep = null,		// Department to be selected automatically via a task template (will be set on choosing the template) or by loading a department-assigned task
        autoselect_User = null,		// User to be selected automatically on loading existing tasks

        /**
         * Setup the task editor expander, placed above the grid
         *
         * @param config Information now how the editor area is initialized
         */

//            IWI doc section file upload start
            doc_Attach_Multi_File_Source = new $.jqx.dataAdapter({
                url: "/global/note?action=Get_Attach_File_Source",
                data: {
                    link_id: master.config.id
                },
                datatype: "json",
                datafields: [
                    {name: 'file_id', type: 'int'},
                    {name: 'file_desc', type: 'string'}
                ],
                id: "id"
            }),
            selectedAttachIds = [],
            $combo = $('#doc-attach-task-file', $task_editor),
            defaultText = ' 0 selected ',
            genarate_jqxComboBox = () => {
                $combo.jqxComboBox({
                    theme: cargohub.jqxtheme,
                    source: doc_Attach_Multi_File_Source,
                    displayMember: "file_desc",
                    valueMember: "file_id",
                    width: 247,
                    height: 24,
                    checkboxes: true,
                    // autoDropDownHeight: true,
                    dropDownHeight: 200,
                    animationType: "fade",
                    placeHolder: ' Select attachments...'
                });
            },
            preSelectAttachments = (ids = []) => {
               
               // console.log('preSelectAttachments',ids);
                const combo = $('#doc-attach-task-file', $task_editor);
                const allItems = combo.jqxComboBox('getItems');

                if (!allItems || allItems.length === 0)
                    return;

                // Clear existing selections
                allItems.forEach(item => combo.jqxComboBox('uncheckItem', item.value));

                // Select new ones
                if (Array.isArray(ids) && ids.length > 0) {
                  //  console.log('preSelectAttachments if ids');
                    ids.forEach(id => combo.jqxComboBox('checkItem', id));
                    combo.jqxComboBox('input').val(`${ids.length} selected`);
                } else {
                   // console.log('preSelectAttachments if not ids');
                    combo.jqxComboBox('input').val('Select attachments...');
                }
            },


//            IWI doc section file upload end
        
        init_Editor = function(config)
        {
            // Variables for operation of the task editor widgets
            var
                row_data,					// Contains a copy of the selected task row when editing or duplicating
                $task_assign_dep = $("#task-assign-dep", $task_editor),		// Department and User (Member) selectors (used only when needed)
                $task_assign_user = $("#task-user-line > div", $task_editor),

              


                /**
                 * Initializes the template selector (used only in create mode)
                 */
                init_Templates = function(on_Ready) {
                    var $task_template = $("#task-template", $task_editor);

                    if(tpls_Ready) {
                        if(on_Ready)
                            on_Ready($task_template);
                        return;
                    }

                    $task_template.jqxDropDownList({
                        theme: cargohub.jqxtheme,
                        source: templates_Source,
                        displayMember: "description", valueMember: "id",
                        placeHolder: "(Loading templates)",
                        width: 853, dropDownWidth: 853,
                        autoDropDownHeight: true, animationType: "fade", enableBrowserBoundsDetection: true,
                        disabled: true
                    })
                        /**
                         * Event: The list of templates has finished loading - Enable the selector if there is data
                         */
                        .on('bindingComplete', function() {
                            if(templates_Source.records.length == 0)
                                $task_template.jqxDropDownList({
                                    placeHolder: "(There are no organization Task templates for this record type)",
                                    disabled: true
                                });
                            else {
                                $task_template.jqxDropDownList({
                                    placeHolder: "Select a template to set Title, Description, Departement assignment and Notification setup:",
                                    dropDownHeight: templates_Source.records.length> 7? 184: null,
                                    autoDropDownHeight: templates_Source.records.length<= 7? true: false,
                                    disabled: false
                                });

                                // Callback event for when the templates are loaded
                                if(on_Ready)
                                    on_Ready($task_template);

                                tpls_Ready = true;
                            }
                        })
                        /**
                         * Event: Selecting a template will replace Title, Deadline and Description with the information returned by the selector
                         * -- If "auto_assign" is present, it will also auto-click on "Department" radio, and when loaded, select a department
                         */
                        .on('select', function(e) {
                            if (e.args && e.args.item) {
                                // Check if Compose was already changed - Give a warning that override will happen
                                if(editor_nonempty)
                                    if(!confirm("The current task is unsaved.\nAre you sure you want to overwrite with new template?"))
                                        return;

                                // Template was selected - Read the rendered template (Title/Body + Deadline) and setup the teask
                                $.get("/global/operations?action=Render_Template", {
                                    id: e.args.item.originalItem.id,
                                    org_id: master.config.perspective,
                                    rt_id: (master.config.type.base_id&&master.config.type.base_id!=master.config.type.id)?master.config.type.base_id:master.config.type.real_id,
                                    rec_id: master.config.id
                                }, function(tpl, textStatus, jqXHR) {
                                    var xdata = JSON.parse(tpl.xdata);

                                    $("#task-title", $task_editor).val(tpl.title);
                                    $("#doc-description-tasks", $task_editor).val(tpl.title);

                                    if(tpl.body.length > 0)
                                        master.rich_editor.content_Write(RICH_EDITOR_INSTANCE_KEY, tpl.body);
                                    // Short timeout before focus to allow TinyMCE to sort itself out
                                    setTimeout(function() {
                                        master.rich_editor.focus();
                                    }, 100);
                                    editor_nonempty = true;

                                    // Xdata contains elements for other controls (assignment, notifications)
                                    if(xdata) {
                                        // Auto-Assign task to a department
                                        if(xdata.auto_assign) {
                                            $("#asgtype-radios > div:eq(2)", $task_editor).trigger('click');

                                            autoselect_Dep = xdata.auto_assign;
                                            helper_Autoselect_Dep();
                                        }

                                        // Set deadline; Reminder will be auto-calculated on the on-change
                                        if(xdata.deadline_days) {
                                            $("#task-deadline", $task_editor).jqxDateTimeInput('setDate', moment(today).add(xdata.deadline_days, 'd').toDate());
                                        }

                                        // Set overdue interval; Repeat limit will be auto-set to "unlimited" by default (but it can be changed after)
                                        if(xdata.ovd_intv) {
                                            $("#task-overdue", $task_editor).val(xdata.ovd_intv);
                                        }
                                    }
                                }, 'json')
                                    .fail(cargohub.its.http_fail);
                            }
                        });
                },

              
                /**
                 * Helper to auto-select department in certain conditions
                 */
                helper_Autoselect_Dep = function()
                {
                    $task_assign_dep.jqxDropDownList({disabled: false});

                    if(autoselect_Dep && departments_Source.records.length) {
                        for(var c=0; c< departments_Source.records.length; c++)
                            if(departments_Source.records[c].id == autoselect_Dep) {
                                $task_assign_dep.jqxDropDownList('selectIndex', c);
                                break;
                            }
                        autoselect_Dep = null; // Disable auto-selection after first time
                    }
                    $task_assign_dep.jqxDropDownList({disabled: config.delegate});
                },

                /**
                 * Helper to auto-select a user (or delegate) in certain conditions
                 */
                helper_Autoselect_User = function()
                {
                    // Only at first time initializing ...
                    if(autoselect_User && members_Source.records.length) {
                        for(var c=0; c< members_Source.records.length; c++)
                            if(members_Source.records[c].id == autoselect_User)
                                $task_assign_user.jqxComboBox('selectIndex', c);
                        autoselect_User = false;	// Disable auto-selection after first time
                    }
                },

                /**
                 * Initializes the department selector on first use and if in Update mode also selects the proper department (This in turn triggers selecting the user after the list is loaded)
                 */
                init_Deps = function(on_Ready) {
                    if(deps_Ready) {
                        if(on_Ready)
                            on_Ready($task_assign_dep);
                        return;
                    }

                    // The Active Departments selector
                    $task_assign_dep.jqxDropDownList({
                        theme: cargohub.jqxtheme,
                        displayMember: "name", valueMember: "id",
                        placeHolder: "(Loading departments)",
                        source: departments_Source,
                        width: 460, dropDownWidth: 460,
                        autoDropDownHeight: true, animationType: "fade", enableBrowserBoundsDetection: true,
                        // Member count for department appears on the right side of the rows
                        renderer: function(index, label, value) {
                            return '<span class=" right">('+ departments_Source.records[index].count_members+' members)</span>'+ label;
                        },
                        disabled: false
                    })
                        /**
                         * Event: The department list finished loading, set the currently active department if assign mode is "department"
                         */
                        .on('bindingComplete', function() {
                            $task_assign_dep.jqxDropDownList({
                                placeHolder: editor_mode == 'master-edit'? "(Select only when reassigning the task)": "Select department:",
                            });

                            // Callback event for when the templates are loaded
                            if(on_Ready)
                                on_Ready($task_assign_dep);

                            deps_Ready = true;

                            $task_assign_dep.jqxDropDownList({disabled: config.delegate});
                        })
                        /**
                         * Event: The department selector - Load the member list (visible or not, depending on the radiobuttons)
                         */
                        .on("select", function(event) {
                            if(event.args.item.value) {
                                // Read from a department membership list
//                        IWI When a task needs to be assigned, the system should not populate readonly user in the user list start
//                                members_Source._source.url = '/manager/department?action=Get_Members',
//                                    members_Source._options.data = {
//                                        id: event.args.item.value
//                                    };
                                members_Source._source.url = '/manager/department?action=Get_MembersForAssign',
                                    members_Source._options.data = {
                                        id: event.args.item.value
                                    };
                                    
//                                    IWI When a task needs to be assigned, the system should not populate readonly user in the user list end
                            }
                            // Enable the Users selector
                            $task_assign_user.jqxComboBox('clearSelection');
                            $("#task-user-line > button", $task_editor).hide();
                            $task_assign_user.jqxComboBox({
                                source: members_Source,
                                placeHolder: "Type here to search or click arrow to open",
                                disabled: false
                            });
                        });
                },

                /**
                 * Prepares the left side assignemnt area controls
                 */
                init_Assignment = function()
                {
                    // The assignment type radio buttons are always initialized (even if invisible), as they don't require external data to be loaded
                    $("#asgtype-radios > div", $task_editor).each(function() {
                        var option_data = $(this).data();
                        $(this).css({'display': 'inline-block', 'width': '140px'}).jqxRadioButton({
                            theme: cargohub.jqxtheme, height: 16,
                            groupName: "asgtype",
                            checked: false,
                            disabled: option_data.disabled
                        })
                            /**
                             * Event: A radiobutton is checked.
                             * Switch the operation mode for assignment search (after reseting existing value
                             */
                            .on('checked', function(e) {
                                assign_mode = option_data.value;
                                switch(assign_mode) {
                                    case 'self':
                                        autoselect_Dep = null; // Doesn't do anything here, but clear it anyway since the control doesn't exist

                                        $("#task-user-line > label", $task_editor).text("My user name:");
                                       $("#user_deligated").css("margin-top", "-12px");
                                        $("#task-user-info > div", $task_editor).text("The task can only be updated by yourself. Others may see it though");
                                        $("#task-user-line > button", $task_editor).hide();
                                        $task_assign_dep.parent().hide(); // No departments here

                                        // Current logged in user (and disabled as this isn't a selection with an ID, just the name placed in the control)
                                        $task_assign_user.jqxComboBox({
                                            disabled: true
                                        }).val(cargohub.application.user_Get_Session().contact.name);

                                        if(!assign_Autocheck) {
                                            $task_assign_dep.jqxDropDownList('clearSelection');
                                        }
                                        assign_Autocheck = false;
                                        break;
                                    case 'user':
                                        if(editor_mode == 'master-edit') {
                                            autoselect_Dep = null; // No department auto-selection when editing in user assign mode
                                        }

                                        $task_assign_dep.parent().show();
                                        $("#task-user-line > label", $task_editor).text("Assigned user:");
                                        $("#user_deligated").css("margin-top", "-12px");
                                        $("#task-user-info > div", $task_editor).text("The user can add progress updates and change status but not the dates");
                                        $("#task-user-line > button", $task_editor).hide();
                                        // ... initialize the list of departments to get them ready for selection
                                        init_Deps(function() {
                                            $task_assign_dep.jqxDropDownList({
                                                placeHolder: editor_mode == 'master-edit'? "(Select only when reassigning the task)": "Select department:",
                                            });
                                            // Auto-selects a department if one is set
                                            helper_Autoselect_Dep();

                                            // Enable the User control when departments are ready
                                            $task_assign_user.jqxComboBox({disabled: false});

                                            // Clear the user when switching manually, but not when autochecking (initialization)
                                            if(!assign_Autocheck) {
                                                $task_assign_user.jqxComboBox('clearSelection');
                                            }
                                            assign_Autocheck = false;
                                        });

                                        break;
                                    case 'dep':
                                        if(editor_mode == 'master-edit') {
                                            // On editing department-assigned tasks, automatically select the task in the list (but not for the other modes)
                                            autoselect_Dep = selected_row.assign_id;

                                            if(selected_row.delegate_id)
                                                autoselect_User = selected_row.delegate_id;
                                        }

                                        // Show the departments line and switch the user line to "Delegated"
                                        $task_assign_dep.parent().show();
                                        $("#task-user-line > label", $task_editor).text("Delegated user:");  //miscellaneous changes
                                        $("#user_deligated").css("margin-top", "-12px");
                                        $("#task-user-info > div", $task_editor).text("Delegate can partially manage this task but not remove it");
                                        // ... initialize the list of departments to get them ready for selection
                                        init_Deps(function() {
                                            // When switching to department mode, change placeholder (even in Edit mode)
                                            $task_assign_dep.jqxDropDownList({
                                                placeHolder: "Select department:"
                                            });
                                            // Auto-selects a department if one is set
                                            helper_Autoselect_Dep();
                                            // If delegate is set, auto-select when ready; If not, clear selection
                                            helper_Autoselect_User();

                                            // Clear the user when switching manually
                                            if(!assign_Autocheck) {
                                                $task_assign_user.jqxComboBox('clearSelection');
                                            }
                                            assign_Autocheck = false;
                                        });

                                        break;
                                }
                            });

                        // Tooltips on the radios
                        cargohub.components.ui.add_Tooltip.call(this);
                    });

                    // The Users control is always initialized, but without any data
                    // - When a department is selected or a user is already known (self, or existing assignee, the control is activated)
                    $task_assign_user.jqxComboBox({
                        theme: cargohub.jqxtheme,
                        searchMode: "containsignorecase",
                        autoComplete: true,	minLength: 0,
                        displayMember: "name", valueMember: "id",
                        placeHolder: "(Select a department first)",
                        width: 460, height: 24, dropDownWidth: 460, dropDownHeight: 220,
                        animationType: "fade", enableBrowserBoundsDetection: true,
                        disabled: true
                    })
                        /**
                         * Event: The user list has been loaded - If a user has been picked for autoselection, do that
                         */
                        .on('bindingComplete', helper_Autoselect_User)
                        /**
                         * Event: A user is selected in this list
                         */
                        .on('change', function(e) {
                            if(assign_mode == 'dep') {
                                $("#task-user-line > button", $task_editor).show();

                            }
                        });

                    $("#task-user-line > button", $task_editor).jqxButton({ theme: cargohub.jqxtheme })
                        /**
                         * Event: Unassign delegate click
                         */
                        .on('click', function() {
                            $task_assign_user.jqxComboBox('clearSelection');
                            $("#task-user-line > button", $task_editor).fadeOut('slow');
                        });
                },

                /**
                 * Prepares the right-side area containing calendar controls
                 */
                init_Notification = function()
                {
                    // This doesn't set anything in the task, however it changes the "deadline" and "reminder" dates
                    $("#task-ntfsetup", $task_editor).jqxDropDownList({
                        theme: cargohub.jqxtheme,
                        source: NOTIFICATION_SETUP,
                        displayMember: "text", valueMember: "value",
                        width: 344, dropDownWidth: 300,
                        autoDropDownHeight: true, animationType: "fade", enableBrowserBoundsDetection: true,
                        placeHolder: QUICK_SETUP_TEXT
                    })
                        .on('change', function(e) {
                            // When this control is operated, dates are always re-enabled
                            $("#task-deadline, #task-reminder", $task_editor).jqxDateTimeInput({disabled: false});   
                            $("#task-deadline + span, #task-reminder + span", $task_editor).empty();

                            // Nothing to do when set to custom
                            if(e.args.item.value === "0")
                                return;

                            // Empty values and reset "extra reminders" on "reset" option
                            if(e.args.item.value == "R") {
                                system_change = true;
                                $("#task-deadline", $task_editor).val(null);
                                $("#task-reminder", $task_editor).val(null);
                                system_change = false;
                                $("#task-overdue, #task-ovdlimit", $task_editor).jqxDropDownList('clearSelection');
                                $("#task-ovdlimit", $task_editor).parent().hide();
                                // Finally, reset the field itself
                                $("#task-ntfsetup", $task_editor).jqxDropDownList('clearSelection');
                                $("#task-ntfsetup", $task_editor).jqxDropDownList({
                                    placeHolder: QUICK_SETUP_TEXT
                                });
                                return;
                            }

                            // ... otherwise, set Due Date and Reminder date
                            var days_interval = e.args.item.value.split(","),
                                deadline = moment(today).add(days_interval[1], days_interval[0]);
                            reminder = moment(deadline).subtract(days_interval[2], 'days');
                            system_change = true;
                            $("#task-deadline", $task_editor).val(deadline.toDate());
                            $("#task-reminder", $task_editor).val(reminder.toDate());
                            $("#task-overdue", $task_editor).val(days_interval[3]);
                            system_change = false;
                        });

                    $("#task-ntfsetup", $task_editor).jqxDropDownList({disabled: config.delegate});

                    // The Deadline and Reminder are always initialized (no external data loaded)
                    cargohub.components.ui.calendar_config = {limit_future: true, height: 24};
                    $("#task-deadline, #task-reminder", $task_editor).each(cargohub.components.ui.calendar_input);
                    cargohub.components.ui.calendar_config = {};

                    $("#task-deadline", $task_editor).on('change', function(e) {
                        if(system_change)
                            return; // Don't do anything if date is set by the system itself (via task template or quick setup)

                        // Changing deadline manually will also change Reminder date, to be following the same partern as for "setup" (below)
                        system_change = true;
                        if(e.args.date) {
                            // Date is set, calculate reminder
                            var deadline = moment(e.args.date), days_left = deadline.diff(today, 'days'), reminder = null;
                            if(days_left == 0 || days_left == 1) {
                                // No reminder when setting deadline to now or tomorrow, clear the reminder
                                $("#task-reminder", $task_editor).val(null);
                                $("#task-overdue", $task_editor).val(1);
                            } else if(days_left <= 3) {
                                // -1 day from deadline for up to 3 days
                                reminder = moment(deadline).subtract(1, 'days');
                                $("#task-overdue", $task_editor).val(1);
                            } else if(days_left <= 7) {
                                // -2 days when deadline is up to a week
                                reminder = moment(deadline).subtract(2, 'days');
                                $("#task-overdue", $task_editor).val(3);
                            } else if(days_left <=14) {
                                // -3 days when deadline is up to two weeks
                                reminder = moment(deadline).subtract(3, 'days');
                                $("#task-overdue", $task_editor).val(3);
                            } else if(days_left <=21) {
                                // -5 days when deadline is up to three weeks (this one not present in quick setup)
                                reminder = moment(deadline).subtract(5, 'days');
                                $("#task-overdue", $task_editor).val(3);
                            } else {
                                // For any longer period, the reminder is a week before
                                reminder = moment(deadline).subtract(1, 'week');
                                $("#task-overdue", $task_editor).val(7);
                            }

                            if(reminder)
                                $("#task-reminder", $task_editor).val(reminder.toDate());

                            // Finally, set the template to "custom"
                            $("#task-ntfsetup", $task_editor).val("0");
                        } else {
                            // Date is cleared, also clear Reminder, Setup and Overdue Notif
                            $("#task-reminder", $task_editor).val(null);
                            $("#task-ntfsetup", $task_editor).jqxDropDownList({
                                placeHolder: QUICK_SETUP_TEXT
                            });
                            $("#task-ntfsetup, #task-overdue, #task-ovdlimit", $task_editor).jqxDropDownList('clearSelection');
                            $("#task-ovdlimit", $task_editor).parent().hide();
                        }
                        system_change = false;
                    });

                    $("#task-reminder", $task_editor).on('change', function(e) {
                        if(system_change)
                            return; // Don't do anything if date is set by the system itself (via task template or quick setup)

                        // Prevent the reminder to be set on, after due date, or on current day (today)
                        if(e.args.date) {
                            var reminder = moment(e.args.date), deadline = moment($("#task-deadline", $task_editor).jqxDateTimeInput('getDate')), due_diff = deadline.diff(reminder, 'days');

                            if(!deadline.isValid()) {
                                $("#task-reminder", $task_editor).val(null);
                                return cargohub.alert("Please set Due date before Reminder date, or use the quick setup");
                            }
                            if(reminder.isSame(today, 'day')) {
                                $("#task-reminder", $task_editor).val(null);
                                return cargohub.alert("Reminder cannot be set to Today. It needs to be set at minimum tomorrow");
                            }
                            if(due_diff <= 0) {
                                $("#task-reminder", $task_editor).val(null);
                                return cargohub.alert("Reminder must be at least one day before Due date, not after or same day");
                            }

                            // When manually setting reminder and accepted as correct, quick setup is changed to "custom"
                            $("#task-ntfsetup", $task_editor).val("0");
                        }
                    });


                    // Overdue selector - saved with the task, and when > 0, it creates a self-sustaining future event loop at the specified interval
                    $("#task-overdue", $task_editor).jqxDropDownList({
                        theme: cargohub.jqxtheme,
                        source: OVERDUE_OPTIONS,
                        displayMember: "text", valueMember: "value",
                        width: 300, dropDownWidth: 300,
                        autoDropDownHeight: true, animationType: "fade", enableBrowserBoundsDetection: true,
                        placeHolder: "(Select if extra reminders are needed)"
                    })
                        /**
                         * When this control is changed, enable or disable the limit depending what is selected
                         */
                        .on('change', function(e) {
                            if(e.args.item.value == "0")
                                $("#task-ovdlimit", $task_editor).parent().hide();
                            else {
                                $("#task-ovdlimit", $task_editor).parent().show();
                                $("#task-ovdlimit", $task_editor).jqxDropDownList('selectIndex', 0);
                                //  $("#task-ovdlimit").jqxDropDownList('val', 25);   //reminder date hide

                            }
                        })
                        .val(3);

                    // When creating the future events, the type of notification will be stored in them
                    // TODO: Temporarily disabled, as non-email type of notifications is non-functional
                    /*			$("#task-ntf-type > div", $task_editor).each(function() {
                                    var option_data = $(this).data();
                                    $(this).css({'display': 'inline-block', 'width': '140px'}).jqxRadioButton({
                                        theme: cargohub.jqxtheme, height: 16,
                                        groupName: "ntftype",
                                        checked: option_data.value == 'email' // default
                                    });
                                })*/
                    /**
                     * Event: Switch the mode of notification
                     */
                    /*			.on('checked', function(e) {
                                    notification_type = option_data.value;
                                });*/

                    $("#task-ovdlimit", $task_editor).jqxDropDownList({
                        theme: cargohub.jqxtheme,
                        source: OVERDUE_LIMIT_OPTIONS,
                        displayMember: "text", valueMember: "value",
                        width: 104, dropDownWidth: 104,
                        autoDropDownHeight: true, animationType: "fade", enableBrowserBoundsDetection: true,
                        selectedIndex: 1                                             //reminder date hide
                    });
                },

                // Set to True when progress control is affecting the status
                status_system_change = false,
                /**
                 * Prepares the status buttongroup by reseting any group and reinitializing it
                 */
                init_Status = function(initial_status)
                {
                    if(!available_statuses.length)
                        return false; // Error, no statuses defined, the status selection ctrl-line is not shown

                    // Destroy previous group if it exists...
                    if($status_ctrl)
                        $status_ctrl.jqxButtonGroup('destroy');
                    // ... and reconstruct it
                    $status_ctrl = $('<div></div>');
                    $("#task-status-line", $task_editor).append($status_ctrl);

                    // Build and initialize
                    for(c = 0; c< available_statuses.length; c++) {
                        $status_ctrl.append($('#template-status-button').html().present(available_statuses[c]));
                    }
                    $(".info-tip", $status_ctrl).each(cargohub.components.ui.add_Tooltip);

                    $status_ctrl.jqxButtonGroup({
                        theme: cargohub.jqxtheme,
                        mode: "radio",
                        enableHover: true
                    })
                        .on('selected', function(event) {
                            // Don't do anything if this was triggered by the system itself
                            if(status_system_change)
                                return;

                            if(available_statuses[event.args.index].status == 'completed') {
                                progress_system_change = true;
                                $('#task-progress', $task_editor).val(100);
                                $('#task-progress_slider', $task_editor).val(100);
                                progress_system_change = false;

                                // freeze (disable) the two controls after they've been set to 100%
                                $('#task-progress', $task_editor).jqxNumberInput({disabled : true});
                                $('#task-progress_slider', $task_editor).jqxSlider({disabled : true});
                            } else {
                                // On other selections, progress can be set
                                $('#task-progress', $task_editor).jqxNumberInput({disabled : false});
                                $('#task-progress_slider', $task_editor).jqxSlider({disabled : false});
                            }
                        });

                    if(initial_status) {
                        for(c = 0; c< available_statuses.length; c++) {
                            if(initial_status == available_statuses[c].status)
                                $status_ctrl.jqxButtonGroup('setSelection', c);
                        }
                    }
                },

                /**
                 * Helper function which "pushes" one of the radiobuttons programatically
                 */
                helper_Status_SetVal = function(status)
                {
                    status_system_change = true;
                    for(c = 0; c< available_statuses.length; c++) {
                        if(status == available_statuses[c].status)
                            $status_ctrl.jqxButtonGroup('setSelection', c);
                    }
                    status_system_change = false;
                },

                progress_system_change = false,

                /**
                 * Sets up the progress line
                 * -- Has two interconnected controls, and can also affect status (switches the status to "Completed" when 100% is set)
                 */
                init_Progress = function()
                {
                    var auto_Status = function(val)
                    {
                        if(helper_Status_GetVal() != 'onhold' && helper_Status_GetVal() != 'failed') {
                            if(val == 100) {
                                helper_Status_SetVal('completed');
                            } else if(val > 0) {
                                helper_Status_SetVal('inprogress');
                            }
                        }
                    };

                    // The completion box and range slider (connected)
                    $('#task-progress', $task_editor).jqxNumberInput({
                        theme: cargohub.jqxtheme,
                        width: 40, height: 21,
                        inputMode: 'simple',
                        min: 0, max: 100, decimalDigits: 0,
                        decimal: 0
                    }).on('valueChanged', function(ev) {
                        if(progress_system_change)
                            return;

                        progress_system_change = true;
                        $('#task-progress_slider', $task_editor).val(ev.args.value);
                        progress_system_change = false;

                        auto_Status(ev.args.value);

                    });

                    $('#task-progress_slider', $task_editor).jqxSlider({
                        theme: cargohub.jqxtheme,
                        width: 500, height: 25,
                        mode: 'fixed',
                        min: 0, max: 100, step: 5,
                        value: 0,
                        showButtons: false,
                        ticksFrequency: 10, ticksPosition: 'bottom'
                    }).on('change', function(ev) {

                        if(progress_system_change)
                            return;

                        progress_system_change = true;
                        $('#task-progress', $task_editor).val(ev.args.value);
                        progress_system_change = false;

                        auto_Status(ev.args.value);
                    });
                },

                /**
                 * Initializes the task history table (loads external data) - Not available in Create or Copy modes
                 */
                init_History = function(on_Ready) {
                    if(history_Ready) {
                        if(on_Ready)
                            on_Ready(); // $task_history
                        return;
                    }

                    var cell_highlight = function(row, column, value, rowData) {
                        var cell_class = "";

                        switch(rowData.status_change) {
                            case 'closed':
                                cell_class+= " fiftycent"; break;
                            case 'onhold':
                                cell_class+= " grid-cell-onhold"; break;
                            case 'completed':
                                cell_class+= " grid-cell-completed"; break;
                            case 'failed':
                                cell_class+= " grid-cell-failed"; break;
                        }

                        return cell_class;
                    };

                    // master.init_DataTable($('#_record-task_history', $task_editor), {
                    //     // Config
                    //     source: task_history_Source,
                    //     width: 1110, //height: 250,
                    //     localization: {
                    //         emptydatastring: "Currently there are no history entries"
                    //     },
                    //     columns: [
                    //         {dataField: 'dt', text: 'Date', width: 95, cellsFormat: cargohub.date_format, align: "center", cellsAlign: "center", filterable: false, cellClassName: cell_highlight},
                    //         {dataField: 'status_change', text: "New status?", width: 95, align: "center", cellsAlign: "center", cellClassName: cell_highlight},
                    //         {dataField: 'update_text', text: "Extra changes", cellClassName: cell_highlight},
                    //         {dataField: 'change_by', text: "Change by", width: 200, cellClassName: cell_highlight},
                    //         {hidden: true, dataField: 'comment', text: "Update reason", filterable: true}, // Update reason doesn't display, but can be used for searching/filtering
                    //     ],
                    //     rowDetails: true, initRowDetails: function(id, datarow, element, rowinfo) { // Row details renderer
                    //         if(datarow.comment) {
                    //             rowinfo.detailsHeight = datarow.comment.split(/\r\n|\r|\n/).length * parseInt(element.css('line-height')) + 25;
                    //             element.addClass('update-content').html(datarow.comment.nl2br()).height(rowinfo.detailsHeight);
                    //         } else {
                    //             rowinfo.detailsHeight = 20;
                    //             element.addClass('center gray').html("-- no update reason specified --");
                    //         }
                    //     }
                    // }, {
                    //     // Callbacks
                    //     "loaded": function(e) {
                    //         history_Ready = true;
                    //         if(on_Ready)
                    //             on_Ready(e);
                    //     },
                    //     "rowopen": function(row, $c) {
                    //         $c.jqxDataTable('showDetails', $c.data('selected_index'));
                    //     }
                    // }, "task_history");
                
                  master.init_DataTable($('#_record-task_history', $task_editor), {         //  IWI update reason grid view_start
                                source: task_history_Source,
                                width: 1110,
                                localization: {
                                    emptydatastring: "Currently there are no history entries"
                                },
                                columns: [
                                    {dataField: 'dt', text: 'Date', width: 95, cellsFormat: cargohub.date_format, align: "center", cellsAlign: "center", filterable: false, cellClassName: cell_highlight},
                                    {dataField: 'status_change', text: "New status?", width: 95, align: "center", cellsAlign: "center", cellClassName: cell_highlight},
                                    {dataField: 'update_text', text: "Extra changes", cellClassName: cell_highlight},
                                    {dataField: 'change_by', text: "Change by", width: 200, cellClassName: cell_highlight},
                                    {dataField: 'comment', text: "Update reason", cellClassName: cell_highlight} 
                                ]
                            }, {
                                "loaded": function(e) {
                                    history_Ready = true;
                                    if(on_Ready) on_Ready(e);
                                }
                            }, "task_history");                                             //  IWI update reason grid view_end
                }
            ;// End variables and functions

            
//IWI doc section file upload start

//                    const $combo = $('#doc-attach-task-file', $task_editor);
//                    const defaultText = '0 selected';

// Init ComboBox
                    genarate_jqxComboBox();
//                    preSelectAttachments([]);

// Function to get and apply selected count
                    function updateSelectedCount() {
                        const selectedItems = $combo.jqxComboBox('getCheckedItems');
                        const label = selectedItems.length > 0
                                ? `${selectedItems.length} selected`
                                : defaultText;

                        // Force update input field
                        const input = $combo.find('.jqx-combobox-input')[0];
                        if (input)
                            input.value = label;

                        // Log selected file_ids
                         selectedAttachIds = selectedItems.map(item => item.value);
                       // console.log('Selected file_ids:', selectedAttachIds);
                    }

// Attach checkChange
                    $combo.on('checkChange', updateSelectedCount);

// Also observe mutations in case jqx re-renders input
                    const input = $combo.find('.jqx-combobox-input')[0];
                    if (input) {
                        const observer = new MutationObserver(() => {
                            updateSelectedCount();
                        });

                        observer.observe(input, {
                            attributes: true,
                            attributeFilter: ['value']
                        });
                    }



//IWI doc section file upload end


            if(task_editor_Ready) {
                // Update expander with new settings (if necessary)
                if(config && config.expander)
                    $task_editor.jqxExpander(config.expander);
            } else {
                // Init the expander itself on first use
                if(!config.expander)
                    config.expander = {
                        disabled: false,
                        expanded: false
                    };
                config.expander.theme = cargohub.jqxtheme;
                $task_editor.jqxExpander(config.expander);

                // Assignment (but without departments or users), Notifications, Status, progress % and progress bar are always initialized
                // (even if not visible in Create or Update modes)
                init_Assignment();
                init_Notification();
                // Note: Status is reinitialized every time after mode is determined (as values can be different which results in different buttons)
                init_Progress();

                task_editor_Ready = true;
            }

            // Mark the editor as open (in one of the specified modes)
            editor_mode = config.mode;

            $('#task-title','#doc-description-tasks', $task_editor).addClass(cargohub.input_theme_classes);
            // Set/Change title (header)
            if(config.title)
                $task_editor.jqxExpander('setHeaderContent', '<img src="/img/icons16/task_statuses/'+ (config.icon || '_task')+ '.png" alt="[:]" class="inline-icon"> '+ config.title);

            // Controls and data initialization (depends on the operation mode)
            editor_nonempty = false;
            $("#task-ref").css("width","460px");
            $('#viewDetails').hide();
            switch(editor_mode) {
                // Create mode (Show creation template)
                case 'create':
                     $('#task-image-preview').hide();
                    $(".task-edit-master, #task-tpl-line", $task_editor).show();
                    $("#task-overview, #task-readonly, #task-update, #task-history", $task_editor).hide();

                    row_data = null;

                    init_Templates(function() {
                        // Template line is always reset to "no selection" so it's ready to select (even with only one option available)
                        $("#task-template", $task_editor).jqxDropDownList('clearSelection');
                    });

                    // Clear editor fields (Title, Description, ?Assignment, ?Notification setup
                    $("#task-title", $task_editor).val(null);
                    if(rich_editor_ready)
                        master.rich_editor.content_Write(RICH_EDITOR_INSTANCE_KEY, "\n");

                    // Clears any previous selections for department and user, also any previous selection of assign mode
                    if(deps_Ready) {
                        $("#asgtype-radios > div", $task_editor).jqxRadioButton('uncheck');
                        $task_assign_dep.jqxDropDownList('clearSelection');
                        $task_assign_user.jqxComboBox('clearSelection');
                    }

                    // Set the minimum date for the date controls to be "now"
                    $("#task-ntfsetup", $task_editor).jqxDropDownList('clearSelection');
                    $("#task-deadline, #task-reminder", $task_editor).jqxDateTimeInput({disabled: false});
                    
                    $("#task-deadline", $task_editor).val(null);
                    $("#task-reminder", $task_editor).val(null);
                    $("#task-deadline + span, #task-reminder + span, #task-ovdlimit + span", $task_editor).empty();
                    $("#task-deadline", $task_editor).jqxDateTimeInput('setMinDate', new Date());
                    $("#task-reminder", $task_editor).jqxDateTimeInput('setMinDate', new Date());

                    // Attempt to autoselect the user's own department as initial choice (will be set on selecting the radio, if DEFAULT_TASK_ASSIGN is "user" or "dep"
                    autoselect_Dep = cargohub.application.user_Get_Session().cdep_id;

                    // Assignment and notifications section
                    $("#asgtype-radios > div", $task_editor).each(function() {
                        if($(this).data('value') == DEFAULT_TASK_ASSIGN) {
                            assign_Autocheck = true;
                            $(this).jqxRadioButton('check');
                        }
                    });

                    break;
                // Master edit (Can edit title, description, update deadline, reassign or close the task) - Also sees history
                case 'master-edit':
                     $('#task-image-preview').hide();//IWI doc section file upload
                     $('#task-file-input').val('');//IWI doc section file upload
                     $('#task-file-info').text('');//IWI doc section file upload
                    
                    $("#task-overview, .task-edit-master, #task-update, #task-history", $task_editor).show();
                    $("#task-tpl-line, #task-readonly", $task_editor).hide();

                    row_data = $.extend({}, selected_row);
                    
//                    console.log('row_data',row_data);
                    // console.log('adddd',selected_row);
                    // console.log('row_data_id',row_data.id);
                    
                     master.rich_editor.content_Write(RICH_EDITOR_INSTANCE_KEY, '');
                    // Check if there's overrides or defaults for the data/values - Process "row_data" before editing
                    if(config.overrides)
                        row_data = $.extend(row_data, config.overrides);
                        //console.log('row_data_id',row_data.id);

                    // Data initialization; Overview area
                    $("#task-dt > div", $task_editor).html(moment(row_data.dt).format("YYYY-MM-DD (dddd) HH:mm:ss")+ ' <span class="gray">('+today.diff(row_data.dt, 'days')+ ' days ago)</span>');
                    $("#task-createdby > div", $task_editor).html(row_data.creator_name);
                    $("#task-asginfo > div", $task_editor).html(row_data.assign_name+ ' <span class="gray">('+(row_data.assign_mode == 'dep'?"Department":"Person")+ ')</span>');
                    if(row_data.assign_mode == 'dep' && row_data.delegate_name) {
                        $("#task-delegate", $task_editor).show().find('div').html(row_data.delegate_name + ((selected_row.delegate_id && selected_row.delegate_id == user_session.id)?" <span class='gray'>(You are the current Delegate)</span>":''));
                    } else {
                        $("#task-delegate", $task_editor).hide();
                    }

                    if(row_data.deadline) {
                        var dl_diff = moment(row_data.deadline).diff(today, 'days'),
                            dl_diff_text = dl_diff > 0?(' <span class="gray">(in '+dl_diff+ ' days)</span>'):(' <span class="gray">(was '+(-dl_diff)+ ' days ago)</span>');
                        $("#task-dlinfo > div", $task_editor).html(moment(row_data.deadline).format("YYYY-MM-DD (dddd)")+ dl_diff_text);
                    } else
                        $("#task-dlinfo > div", $task_editor).html("&mdash;");
                    $("#task-statinfo > div", $task_editor).html(STATUS_TRANSLATE[row_data.status]+ ' <span class="gray">('+row_data.progress+ '%)</span>');


                    $("#task-title", $task_editor).val(row_data.title);
                    
                    $("#task-ref", $task_editor).val(row_data.task_ref);
                    $("#task-type", $task_editor).val(row_data.task_type);
                    $("#task-ref").css("width","456px");
                    $('#viewDetails').html('  <a target="_blank" href="/record/process:'+row_data.task_ref+'">View ref. details</a> '); 
                    $('#viewDetails').show();
                    
                    

                    // If editor already initialized (moving from another record, reload new body)
                    if(row_data.description && rich_editor_ready)
                        master.rich_editor.content_Write(RICH_EDITOR_INSTANCE_KEY, row_data.description);

                    if(deps_Ready) {
                        $("#asgtype-radios > div", $task_editor).jqxRadioButton('uncheck');
                        $task_assign_dep.jqxDropDownList('clearSelection');
                        $task_assign_user.jqxComboBox('clearSelection');
                    }

                    // Assignment area
                    $("#asgtype-radios > div", $task_editor).each(function() {
                        if(row_data.assign_id && $(this).data('value') == row_data.assign_mode)
                            $(this).jqxRadioButton('check'); // This should also trigger the "checked" event which will modify the Department and User lines
                    });

                    // Setup The Notification area
                    system_change = true;
                    // Begin by reseting all controls so new values can be entered without affecting
                    $("#task-ntfsetup", $task_editor).jqxDropDownList('clearSelection');
                    $("#task-deadline, #task-reminder", $task_editor).jqxDateTimeInput({disabled: false});
                    $("#task-deadline", $task_editor).val(null);
                    $("#task-reminder", $task_editor).val(null);
                    $("#task-deadline + span, #task-reminder + span, #task-ovd_limit + span", $task_editor).empty();
                    // Set the minimum date for the date controls to the task creation date (so deadline, remidner can be in the past, but not lower than create date "dt")
                    $("#task-deadline", $task_editor).jqxDateTimeInput('setMinDate', row_data.dt);
                    $("#task-reminder", $task_editor).jqxDateTimeInput('setMinDate', row_data.dt);

                    // Deadline change is not allowed for Delegate
                    $("#task-deadline", $task_editor).jqxDateTimeInput({disabled: config.delegate});

                    // Then set new dates if avaiable
                    if(row_data.deadline) {
                        $("#task-deadline", $task_editor).val(row_data.deadline);
                        if(row_data.dl_action_id < 0) {
                            $("#task-deadline", $task_editor).jqxDateTimeInput({disabled: true});
                            if(row_data.dl_action_id == -1)
                                $("#task-deadline + span", $task_editor).css('color', 'green').html("&nbsp; Sent !");
                            if(row_data.dl_action_id == -2)
                                $("#task-deadline + span", $task_editor).css('color', 'gray').html("&nbsp; Cancelled");
                        }

                        $("#task-ntfsetup", $task_editor).jqxDropDownList({
                            placeHolder: "(Dates were previously set)"
                        });
                    } else
                        $("#task-ntfsetup", $task_editor).jqxDropDownList({
                            placeHolder: QUICK_SETUP_TEXT
                        });
                    if(row_data.reminder) {
                        $("#task-reminder", $task_editor).val(row_data.reminder);
                        if(row_data.rem_action_id < 0) {
                            $("#task-reminder", $task_editor).jqxDateTimeInput({disabled: true});
                            if(row_data.rem_action_id == -1)
                                $("#task-reminder + span", $task_editor).css('color', 'green').html("&nbsp; Sent !");
                            if(row_data.rem_action_id == -2)
                                $("#task-reminder + span", $task_editor).css('color', 'gray').html("&nbsp; Cancelled");
                        }
                    }

                    system_change = false;

                    // Overdue options
                    if(row_data.ovd_intv != null) {
                        $("#task-overdue", $task_editor).val(row_data.ovd_intv);
                        if(row_data.ovd_rep_limit != null)
                            $("#task-ovdlimit", $task_editor).val(row_data.ovd_rep_limit);
                        else
                            $("#task-ovdlimit", $task_editor).jqxDropDownList('clearSelection');

                        if(row_data.ovd_repeat != null)
                            $("#task-ovdlimit + span", $task_editor).html("&nbsp; (Sent "+ row_data.ovd_repeat+ " times)");
                    } else
                        $("#task-overdue", $task_editor).jqxDropDownList('clearSelection');

                    // Task status choices; The list for master-edit has all options except "expired" which is set by the system
                    available_statuses = [
                        {status: "assigned", text: "Assigned", explain: STATUS_TRANSLATE["assigned"]},
                        {status: "inprogress", text: "In progress", explain: STATUS_TRANSLATE["inprogress"]},
                        {status: "onhold", text: "On hold", explain: STATUS_TRANSLATE["onhold"]},
                        {status: "completed", text: "<i>Completed</i>", explain: STATUS_TRANSLATE["completed"]},
                        {status: "failed", text: "<i>Failed</i>", explain: STATUS_TRANSLATE["failed"]}
//				{status: "expired", text: "<i>Auto-Expired</i>", disabled: true}
                    ];
                    init_Status(row_data.status);

                    progress_system_change = true;
                    $("#task-progress", $task_editor).val(row_data.progress);
                    $("#task-progress_slider", $task_editor).val(row_data.progress);
                    progress_system_change = false;

                    // Always clear the comment line at every edit
                    $('#task-update-comment', $task_editor).addClass(cargohub.input_theme_classes).val("");
                    $('#taskXls,#taskAct,#taskFal,#task-create').css('display','none');
                    // Final section shows a readout of the task update history (from task_updates)
                    init_History();
                    task_history_Source._source.url = '/global/task?action=Get_Updates';
                    task_history_Source._options.data = {
                        task_id: row_data.uid
                    };
                    master.refresh_DataTable($('#_record-task_history', $task_editor));
     
                    //file_images show in the task tab _start
//                    console.log('row_data.id',row_data.uid);
                    $.ajax({ 
                            url: '/record/process?action=Get_task_files', 
                            type: 'POST',
                            dataType: 'json',
                            data: {task_id: row_data.uid},
                            success: function(response) {
//                               console.log("Files for this task:", response);
                                $('#task-image-preview .image-box').html(response.html);
                                if(response.selectedIds.length>0){
                                $('#task-image-preview').show();
                            }
                               // preSelectAttachments(response.selectedIds);
                                
                                
                                
                                
                                selectedAttachIds = response.selectedIds;

                    const combo = $('#doc-attach-task-file', $task_editor);

                    // Wait for data to load, then clear and reselect
                    combo.off('bindingComplete.loadBody').on('bindingComplete.loadBody', function () {
                        preSelectAttachments(selectedAttachIds);
                    });

                    // Rebind data to force reload and trigger selection
                    doc_Attach_Multi_File_Source.dataBind();
                    
                    
                                
                            },
                            error: function(xhr, status, error) {
                                console.error("Error fetching task files:", error);
                            }
                        });
                    //file_images show in the task tab _end
                    
//                    IWI show description update history start

                        $.ajax({
                            url: '/global/task?action=Get_task_updates',
                            type: 'POST',
                            dataType: 'json',
                            data: {task_id: row_data.uid},
                            success: function (response) {
//                                console.log(response.description_html);
                                $('#task-history-grid').html(response.description_html);                              
                            },
                            error: function (xhr, status, error) {
                                console.error("Error fetching task files:", error);
                            }
                        });

//                    IWI show description update history end   
                    
                    

                    break;
                // Task assignee update (Can update task progress and see history, but not change the title, description, assignment or deadlines)
                case 'update':
                    $("#task-overview, #task-readonly, #task-update, #task-history", $task_editor).show();
                    $(".task-edit-master", $task_editor).hide();

                    row_data = $.extend({}, selected_row);
                    // Check if there's overrides or defaults for the data/values - Process "row_data" before editing
                    if(config.overrides)
                        row_data = $.extend(row_data, config.overrides);

                    // Data initialization
                    $("#task-dt > div", $task_editor).html(moment(row_data.dt).format("YYYY-MM-DD (dddd) HH:mm:ss")+ ' <span class="gray">('+today.diff(row_data.dt, 'days')+ ' days ago)</span>');
                    $("#task-createdby > div", $task_editor).html(row_data.creator_name);
                    $("#task-asginfo > div", $task_editor).html(row_data.assign_name+ ' <span class="gray">('+(row_data.assign_mode == 'dep'?"Department":"Person")+ ')</span>');
                    if(row_data.assign_mode == 'dep' && row_data.delegate_name) {
                        $("#task-delegate", $task_editor).show().find('div').html(row_data.delegate_name);
                    } else {
                        $("#task-delegate", $task_editor).hide();
                    }

                    if(row_data.deadline) {
                        var dl_diff = moment(row_data.deadline).diff(today, 'days'),
                            dl_diff_text = dl_diff > 0?(' <span class="gray">(in '+dl_diff+ ' days)</span>'):(' <span class="gray">(was '+(-dl_diff)+ ' days ago)</span>');
                        $("#task-dlinfo > div", $task_editor).html(moment(row_data.deadline).format("YYYY-MM-DD (dddd)")+ dl_diff_text);
                    } else
                        $("#task-dlinfo > div", $task_editor).html("&mdash;");
                    $("#task-statinfo > div", $task_editor).html(STATUS_TRANSLATE[row_data.status]+ ' <span class="gray">('+row_data.progress+ '%)</span>');

                    $("#task-title-ro", $task_editor).html(row_data.title);
                    $("#task-descr-ro", $task_editor).html(row_data.description);

                    // Task status choices; The Update mode only has two modes for active task (Progress, Hold), and two end modes (Completed, Failed)
                    available_statuses = [
                        {status: "inprogress", text: "In progress", explain: STATUS_TRANSLATE["inprogress"]},
                        {status: "onhold", text: "On hold", explain: STATUS_TRANSLATE["onhold"]},
                        {status: "completed", text: "<i>Completed</i>", explain: STATUS_TRANSLATE["completed"]},
                        {status: "failed", text: "<i>Failed</i>", explain: STATUS_TRANSLATE["failed"]}
                    ];
                    init_Status(row_data.status);

                    progress_system_change = true;
                    $("#task-progress", $task_editor).val(row_data.progress);
                    $("#task-progress_slider", $task_editor).val(row_data.progress);
                    progress_system_change = false;

                    // Always clear the comment line at every edit
                    $('#task-update-comment', $task_editor).addClass(cargohub.input_theme_classes).val("");
                    $('#taskXls,#taskAct,#taskFal,#task-create').css('display','none');
                    // Final section shows a readout of the task update history (from task_updates)
                    init_History();
                    task_history_Source._source.url = '/global/task?action=Get_Updates';
                    task_history_Source._options.data = {
                        task_id: row_data.uid
                    };
                    master.refresh_DataTable($('#_record-task_history', $task_editor));

                    break;
            }

           
            // Finished initialization ( or reconfiguration ) - Show the expander and expand
            $task_editor.show().jqxExpander('enable');
            $task_editor.on('expanded', function() {
                // When first expanded, initialize Content editor (TinyMCE)
                if((editor_mode == 'create' || editor_mode == 'master-edit') && !rich_editor_ready) {
                    master.rich_editor.create_Instance(RICH_EDITOR_INSTANCE_KEY,
                        {	// Config
                            container: $("#task-descr", $task_editor),
                            params: {
                                // Only useful for TinyMCE
                                tinyMCE: {
                                    plugins: "link autolink autoresize paste textcolor image code hr fullscreen lists table colorpicker charmap searchreplace insertdatetime",
                                    toolbar: "bold italic underline | alignleft aligncenter alignright | formatselect fontselect fontsizeselect | hr link image table bullist numlist charmap insertdatetime | forecolor backcolor removeformat | undo redo | searchreplace fullscreen",
                                    image_title: true,
                                    automatic_uploads: true,
                                    file_picker_types: 'image',
                                    paste_data_images:true,
                                    images_upload_url:'/files?action=open_upload',
                                    file_picker_callback: function (cb, value, meta) {
                                        var input = document.createElement('input');
                                        input.setAttribute('type', 'file');
                                        input.setAttribute('accept', 'image/*');

                                        /*
                                          Note: In modern browsers input[type="file"] is functional without
                                          even adding it to the DOM, but that might not be the case in some older
                                          or quirky browsers like IE, so you might want to add it to the DOM
                                          just in case, and visually hide it. And do not forget do remove it
                                          once you do not need it anymore.
                                        */

                                        input.onchange = function () {
                                            var file = this.files[0];

                                            var reader = new FileReader();
                                            reader.onload = function () {
                                                /*
                                                  Note: Now we need to register the blob in TinyMCEs image blob
                                                  registry. In the next release this part hopefully won't be
                                                  necessary, as we are looking to handle it internally.
                                                */
                                                var id = 'blobid' + (new Date()).getTime();
                                                var blobCache =  tinymce.activeEditor.editorUpload.blobCache;
                                                var base64 = reader.result.split(',')[1];
                                                var blobInfo = blobCache.create(id, file, base64);
                                                blobCache.add(blobInfo);

                                                /* call the callback and populate the Title field with the file name */
                                                cb(blobInfo.blobUri(), { title: file.name });
                                            };
                                            reader.readAsDataURL(file);
                                        }
                                    }
                                }
                            },
                            print: {
                                enabled: true // Enable printing function
                            }
                        },
                        {	// Events
                            on_init: function() {
                                // If first initialization starts in "master-edit" mode, load content;
                                $("#task-descr-placeholder", $task_editor).hide();
                                if(editor_mode == 'create')
                                    master.rich_editor.content_Write(RICH_EDITOR_INSTANCE_KEY, "\n");
                                else if(editor_mode == 'master-edit' && row_data.description)
                                    master.rich_editor.content_Write(RICH_EDITOR_INSTANCE_KEY, row_data.description);

                                master.rich_editor.enable(RICH_EDITOR_INSTANCE_KEY);
                                rich_editor_ready = true;
                            },
                            on_change: function() {
                                editor_nonempty = true;
                            }
                        }
                    );
                }
            });

            $task_editor.jqxExpander('expand');

            // Add tooltips for relevant elements
            $(".info-tip", $task_editor).each(cargohub.components.ui.add_Tooltip);

            // Show the Save/Close controls
            donrey.cancel_Delay('hide-tasks-open-ops');
            $('.tasks-open-ops button').jqxButton({disabled: false});
            $('.tasks-open-ops').fadeIn();
        },
        
         
        /**
         * Saves the task currently open in the editor (new/copy/existing)
         */
        task_Save = function()
        {
            // TODO: Warn if no changes have been made ("changed" variable)
            var
                $task_assign_dep = $("#task-assign-dep", $task_editor),		// Department and User (Member) selectors (used only when needed)
                $task_assign_user = $("#task-user-line > div", $task_editor),
                action = (open_row_id && !copy_mode)? "Update": "Create",
                // Extra information used to record the task as linked to a record (or "issue" as it's former name)
                submit_data = (open_row_id && !copy_mode)?
                    { // When editing an existing task
                        id: open_row_id
                    }: { // Create new task or copy existing one
                        org_id: master.config.perspective,		// Current organization (perspective for admin - Not useful for non-admin as it will default to the user's organization anyway)
                        link_type: "record",					// Create a task for a "Record" (issue, rename this when possible); Cannot be changed
                        link_id: master.config.id				// The Record's ID, the task is linked to this record; Cannot be changed
                    },
                selected_dep = null, selected_assignee = null, selected_delegate = null;
            submit_data.task = {};

            // Verifications and data preparation
            if(editor_mode == 'create' || editor_mode == 'master-edit') {

                submit_data.task.title = $("#task-title", $task_editor).val();
                submit_data.task.task_ref = $("#task-ref",$task_editor).val();
		submit_data.task.task_type = $("#task-type",$task_editor).val();
        submit_data.task.doc_desc = $("#doc-description-tasks", $task_editor).val();
                // Check if Title is too short (or missing) or too long
                if(submit_data.task.title.length < 5)
                    return cargohub.alert("Task Title should be at least five characters long (eg. Abcde)");
                if(submit_data.task.title.length > 200)
                    return cargohub.alert("Task Title character limit is 200. Please shorten the Title.");

                // Check if description is not having an error in TinyMCE
                submit_data.task.description = master.rich_editor.content_Read(RICH_EDITOR_INSTANCE_KEY); // Content extracted from TinyMCE
                if(submit_data.task.description === false)
                    return cargohub.alert("Error occurred in the description editor. Try copying content out, reloading (Ctrl+R) and pasting it back in.");

                // Assignment
                submit_data.task.assign_mode = assign_mode;
                switch(assign_mode) {
                    case 'self':
                        // Nothing to do here, PHP will take care of it
                        break;
                    case 'user':
                        selected_assignee = $task_assign_user.jqxComboBox('getSelectedItem');
                        if(action=='Create') {
                            if(!selected_assignee)
                                return cargohub.alert("User assignment mode selected, but no user has been chosen !");
                        } else {
                            // While Editing, only check if user is selected only if a department is also selected
                            selected_dep = $task_assign_dep.jqxDropDownList('getSelectedItem');
                            if(selected_dep && !selected_assignee)
                                return cargohub.alert("User re-assignment mode activated, but no user has been chosen !");
                        }

                        break;
                    case 'dep':
                        selected_assignee = $task_assign_dep.jqxDropDownList('getSelectedItem');
                        selected_delegate = $task_assign_user.jqxComboBox('getSelectedItem');

                        if(!selected_assignee)
                            return cargohub.alert("Department assignment mode selected, but no department has been chosen");

                        if(selected_delegate) {
                            submit_data.task.delegate_id = selected_delegate.value;
                        } else if(selected_row && selected_row.delegate_id) {
                            // A delegate was present in the current row, but it was cleared
                            submit_data.task.delegate_id = -1; // Indicator for the remote code to clear the existing delegate
                        }

                        break;
                    case 'usrgrp':
                        // Not implemented!
                        return;
                }
                if(selected_assignee) {
                    submit_data.task.assign_to = selected_assignee.value;
                    submit_data.task.assign_name = selected_assignee.label;
                }

                // Notification setup (Dates, Overdue, Method)
                submit_data.task.deadline = $("#task-deadline", $task_editor).val();
                submit_data.task.reminder = $("#task-reminder", $task_editor).val();
                submit_data.task.ovd_intv = $("#task-overdue", $task_editor).val();
                submit_data.task.ovd_rep_limit = $("#task-ovdlimit", $task_editor).val();
                submit_data.task.action_ntf_type = notification_type;
                
                
                
            }

            // Valid only for operational editing (task progress update), also on master-edit (task creator can also update progress)
            if(editor_mode == 'master-edit' || editor_mode == 'update') {

                submit_data.task.status = helper_Status_GetVal();
                submit_data.task.progress = $("#task-progress", $task_editor).val();

                // Grab the update reason if one is mentioned
                submit_data.comment = $("#task-update-comment", $task_editor).val();
                if(submit_data.comment.length < 1)
                    delete submit_data.comment;

                //delete submit_data.title;

            }

            // Checks only in master-edit mode
            if(editor_mode == 'master-edit') {
                // Check if current user is the delegate and is removed and the tasks_access_dep right is not granted, warn about losing the ability to edit the task
                if(selected_row && selected_row.delegate_id && submit_data.task.delegate_id != selected_row.delegate_id && !cargohub.application.user_Test_Privileges('tasks_access_dep')) {
                    if(!confirm("You have assigned the task to another member or dismissed yourself as the delegate\nThis will remove your ability to manage this task.\n\n*** Please confirm the change before saving ! ***"))
                        return false;
                }
            }

            //    IWI doc section file upload start
//                console.log('selectedAttachIds',selectedAttachIds);
                submit_data.task.selectedAttachIds=selectedAttachIds;
                
//                IWI doc section file upload end

            // Checks valid for all cases (Create/Master Edit/Update)
            // ... nothing here yet ...
            
            
            
                
//                IWI Create a files upload section in task start
//                    console.log('submit_data', submit_data);
//                    var fileInput = $('.task-file')[0];
//                    if (fileInput.files.length > 0) {
//                        submit_data.task.files = Array.from(fileInput.files);
//                    }


                var fileInput = $('.task-file')[0];

                if (fileInput.files.length > 0) {
                    const formData = new FormData();

                    // Flatten and append all fields from submit_data
                    appendFormData(formData, submit_data);

                    // Append files to formData properly
                    Array.from(fileInput.files).forEach((file, index) => {
                        formData.append(`file[${index}]`, file);
                    });

                    // Submit using ajaxSubmit with FormData
                    $.ajax({
                        url: "/global/task?action=" + action,
                        type: "POST",
                        data: formData,
                        dataType: "json",
                        processData: false,
                        contentType: false,
                        success: function (x) {
                            console.log("Success", x);
                            master.refresh_DataTable($("#tab_tasks div.jqx-datatable"), true);
                            master.toolbar_Report("#tab_tasks", x.response);
                            cargohub.components.spinner.On("Success!", x.response, 2500, function () {
                                cargohub.components.spinner.Off();
                            });
                            close_Editor();
                            if (action === 'Create') {
                                var count_task = $('#taskerc').text().replace('[', '').replace(']', '');
                                if (count_task === '') {
                                    $('#taskerc').text('[1]');
                                } else {
                                    var total = parseInt(count_task) + 1;
                                    $('#taskerc').text('[' + total + ']');
                                }
                            }
                        },
                        error: function (xhr, status, err) {
                            console.error("Error", err);
                        }
                    });

                } else {
                    $($task_editor).find('form').ajaxSubmit({
                        dataType: 'json',
                        url: "/global/task?action=" + action,
                        data: submit_data,
                        success: function (x) {
                            master.refresh_DataTable($("#tab_tasks div.jqx-datatable"), true); // true = do not clear

                            // TODO - Resolve the tab id to idx first
//				if(x.count)
//					cargohub.components.hashnav.update_Tab_Header(4, "Tasks <span class='fiftycent'>["+x.count+"]</span>", "/img/icons16/to_do_list.png");
                            // Finally, show the operation result
                            master.toolbar_Report("#tab_tasks", x.response);

                            cargohub.components.spinner.On("Success!", x.response, 2500, function () {
                                cargohub.components.spinner.Off();
                            });
                            // Close editor and show the "Create" button again
                            close_Editor();
                            //check task count and increase by one , since new task is created
                            if (action === 'Create') {
                                var count_task = $('#taskerc').text().replace('[', '').replace(']', '');
                                if (count_task === '') {
                                    $('#taskerc').text('[1]');
                                } else {
                                    var total = parseInt(count_task) + 1;
                                    $('#taskerc').text('[' + total + ']');
                                }
                            }
                        },
                        error: cargohub.its.http_fail
                    });
                }

            
                    
                    
//                  IWI Create a files upload section in task end
//IWI Create a files upload section in task start

/*
            $($task_editor).find('form').ajaxSubmit({
                dataType: 'json',
                url: "/global/task?action="+action,
                data: submit_data,
                success: function(x) {
                    master.refresh_DataTable($("#tab_tasks div.jqx-datatable"), true); // true = do not clear

                    // TODO - Resolve the tab id to idx first
//				if(x.count)
//					cargohub.components.hashnav.update_Tab_Header(4, "Tasks <span class='fiftycent'>["+x.count+"]</span>", "/img/icons16/to_do_list.png");
                    // Finally, show the operation result
                    master.toolbar_Report("#tab_tasks", x.response);

                    cargohub.components.spinner.On("Success!", x.response, 2500, function() {
                        cargohub.components.spinner.Off();
                    });
                    // Close editor and show the "Create" button again
                    close_Editor();
                    //check task count and increase by one , since new task is created
                    if(action === 'Create'){
                        var count_task = $('#taskerc').text().replace('[', '').replace(']', '');
                        if(count_task === ''){
                            $('#taskerc').text('[1]');
                        } else{
                            var total = parseInt(count_task) + 1;
                            $('#taskerc').text('['+total+']');
                        }
                    }
                },
                error: cargohub.its.http_fail
            });
            
            */
//           IWI Create a files upload section in task end

        },

        /**
         * Minimize and close the editor if it was open.
         */
        close_Editor = function()
        {
            // TODO: Check if changes have been made, and warn if so (to avoid closing and erasing unsaved task)

            // Minimize and close the editor
            if($task_editor.jqxExpander('expanded')) {
                $task_editor.on('collapsed', function() {
                    // When finishing collapse... hide the editor
                    $task_editor.hide();
                    // ... and disable the event
                    $task_editor.off('collapsed');
                });
                $task_editor.jqxExpander('collapse');
            } else {
                // If already collapsed, just hide it
                $task_editor.hide();
            }

            // Also fadeout the Save/Close buttons
            $('.tasks-open-ops button').jqxButton({disabled: true});
            $('.tasks-open-ops').fadeOut();

            editor_nonempty = false;
            editor_mode = '';
            copy_mode = false;

            // If a row is selected, show the Open/Copy controls again
            if(selected_row)
                $('#tab_tasks .tasks-row-ops').fadeIn();
            // When nothing is open (for create, edit, view), show the Create button
            if(tasks_manager_priv)
                $('#tab_tasks .tasks-master-ops').fadeIn();
            // Finally, show the table just in case it was hidden by a "Create" operation
            $("#tab_tasks #_record-tasks").show();
            // Hide the "table title" which is only needed when editor is on (but not for Create, since the table is hidden then)
            $("#tab_tasks #tasks-table-title").hide();
            
             //$('#taskAct').click(); //IWI first load all active tasks //IWI all task should be list in one page , start with open tasks

            available_statuses = [];

            return true;
        },

        close_Viewer = function()
        {
            if($task_viewer.jqxExpander('expanded')) {
                $task_viewer.on('collapsed', function() {
                    // When finishing collapse... hide the editor
                    $task_viewer.hide();
                    // ... and disable the event
                    $task_viewer.off('collapsed');
                });
                $task_viewer.jqxExpander('collapse');
            } else {
                // If already collapsed, just hide it
                $task_viewer.hide();
            }

            // Also fadeout the Close buttons
            $('.tasks-open-ops button').jqxButton({disabled: true});
            $('.tasks-open-ops').fadeOut();

            if(selected_row)
                $('#tab_tasks .tasks-row-ops').fadeIn();

            // Hide the "table title" which is only needed when editor is on (but not for Create, since the table is hidden then)
            $("#tab_tasks #tasks-table-title").hide();

            return true;
        },

        /**
         * Opens the task for editing (master-edit or assignee update)
         * The call is then passed to init_Editor(), which loads TinyMCE and then places the task description content in it. A "Save" command is added to the toolbar
         */
        task_Open = function(row)
        {
            var allow_edit = tasks_manager_priv, delegate_editing = false;

            $('#tab_tasks .tasks-row-ops').fadeOut();

            if(row) {
                selected_row = row;
                open_row_id = row.uid;
            }

            // Check if user receives (partial) edit ability from being Assigned or Delegated to the selected task)
            // -- Partial = Only Status, progress and update reason ( cannot change task title/description/dates or assignment)
            // -- Delegate mode = All except Due date, Changing departemnt (restricted within current department) - Can reassign to other users or self (at which point the delegate ability is lost)
            if(!allow_edit) {
                switch(selected_row.assign_mode) {
                    // When user is assigned, update editing is enabled
                    case 'user':
                        if(selected_row.assign_id == user_session.id)
                            allow_edit = true;
                        break;
                    // When department in which user belongs is assigned, update editing is enabled as well as delegate mode when valid
                    case 'dep':
                        if(selected_row.assign_id == user_session.cdep_id) {

                            // Delegate edit ability ?
                            if((selected_row.delegate_id && selected_row.delegate_id == user_session.id) || cargohub.application.user_Test_Privileges('tasks_access_dep')) {
                                allow_edit = true;
                                delegate_editing = true;
                            } else {
                                // No, just normal department member
                                allow_edit = true;
                            }
                        }
                        break;
                }
            }

            if(allow_edit) {
                // Edit mode
                $task_viewer.hide();
                $('#tab_tasks .tasks-open-ops, #tab_tasks .tasks-write-ops').fadeIn();
                // Make sure the rich editor is already loaded before invoking it
                master.load_rich_editor(function() {
                    // When using the call externally, not from the toolbar button event
                    init_Editor({
                        title: ((tasks_manager_priv || delegate_editing)? "Edit task: ": STATUS_TITLE[selected_row.status]+ " task: ")+ selected_row.title,
                        icon: selected_row.status,
                        mode: (tasks_manager_priv || delegate_editing)? "master-edit": "update",
                        delegate: delegate_editing	// Boolean: When present and True certain features are disabled for the current edit
                    });
                });
            } else {
                // Read-only mode
                $task_editor.hide();

                $('#tab_tasks .tasks-write-ops').hide(); // No "Save" function in View mode
                $('#tab_tasks .tasks-open-ops').fadeIn();

                // View mode, switch to the task viewer
                task_View({
                    title: STATUS_TITLE[selected_row.status]+ " task: "+ selected_row.title,
                    icon: selected_row.status
                });
            }

            // Show the table title bar
            $("#tab_tasks #tasks-table-title").show();
        },

        /**
         * Open the task for viewing (no editable form elements or controls - just HTML)
         */
        task_View = function(config)
        {
            if(task_viewer_Ready) {
                // Update expander with new settings (if necessary)
                if(config && config.expander)
                    $task_viewer.jqxExpander(config.expander);

            } else {
                // Init the expander itself on first use
                if(!config.expander)
                    config.expander = {
                        disabled: false,
                        expanded: false
                    };
                config.expander.theme = cargohub.jqxtheme;
                $task_viewer.jqxExpander(config.expander);

                task_viewer_Ready = true;
            }
            $task_viewer.show();

            // Set/Change title (header)
            if(config.title)
                $task_viewer.jqxExpander('setHeaderContent', '<img src="/img/icons16/task_statuses/'+ (config.icon || '_task')+ '.png" alt="[:]" class="inline-icon"> '+ config.title);

            var row_data = $.extend({}, selected_row);
            // Check if there's overrides or defaults for the data/values - Process "row_data" before rendering the template
            if(config.overrides)
                row_data = $.extend(row_data, config.overrides);

            // Prepare some derived information from task data
            row_data.dt_format = moment(row_data.dt).format("YYYY-MM-DD (dddd) HH:mm:ss")+ ' <span class="gray">('+today.diff(row_data.dt, 'days')+ ' days ago)</span>';
            row_data.asgn_format = row_data.assign_name+ ' <span class="gray">('+(row_data.assign_mode == 'dep'?"Department":"Person")+ ')</span>';
            if(row_data.deadline) {
                var dl_diff = moment(row_data.deadline).diff(today, 'days'),
                    dl_diff_text = dl_diff > 0?(' <span class="gray">(in '+dl_diff+ ' days)</span>'):(' <span class="gray">(was '+(-dl_diff)+ ' days ago)</span>');
                row_data.deadline_format = moment(row_data.deadline).format("YYYY-MM-DD (dddd)")+ dl_diff_text;
            } else
                row_data.deadline_format = "&mdash;";
            row_data.status_format = STATUS_TRANSLATE[row_data.status]+ ' <span class="gray">('+row_data.progress+ '%)</span>';

            // Render the task template into the body of the expander
            $task_viewer.jqxExpander('setContent', $('#template-task-viewer').html().present(row_data));
            // Add tooltips for relevant elements
            $(".info-tip", $task_viewer).each(cargohub.components.ui.add_Tooltip);

            $task_viewer.jqxExpander('expand');

            // Show the Close control
            donrey.cancel_Delay('hide-tasks-open-ops');
            $('#tab_tasks .tasks-open-ops button').jqxButton({disabled: false});
            $('#tab_tasks .tasks-open-ops').fadeIn();
        },

        /**
         * Removes a Record-linked task with an Ajax call. Refreshes the DataTable on success
         */
        task_Delete = function()
        {
            $.post("/global/task?action=Remove", {
                link_type: "record",
                link_id: master.config.id,
                id: selected_row.uid,
                org_id: master.config.perspective// Current organization (perspective for admin - Not useful for non-admin as it will default to the user's organization anyway)
            }, function(x) {
                if(x.success) {
                    master.refresh_DataTable($("#tab_tasks div.jqx-datatable"));
                    //Note: Div id not to be changed in html . atleast better from adding static position
                    let taskPosition = $('#tab_tasks').index();
                    // Update counter on deletion (should be reduced by one compared to current if deleting from the perspective organization)
                    // Disabled, position might change
                    if(x.update_count)
                        cargohub.components.hashnav.update_Tab_Header(taskPosition, "Tasks"+(x.count? " <span class='fiftycent'>["+x.count+"]</span>": ""), "/img/icons16/to_do_list.png");

                    task_Counter();
                    master.toolbar_Report("div#tab_tasks", x.response);
                } else
                    cargohub.its.http_fail(null, "Global.Task::remove");
            })
                .fail(cargohub.its.http_fail);
        },

        /**
         *  Count total active and  failed task
         */
        task_Counter = function () {
            $.post("?action=Get_TaskCount", {
                link_type: "record",
                link_id: master.config.id,
                org_id: master.config.perspective// Current organization (perspective for admin - Not useful for non-admin as it will default to the user's organization anyway)
            },function (x) {
                if (x.success) {
//                    $('#act_task').text(x.active);//IWI all task should be list in one page , start with open tasks
//                    $('#fail_task').text(x.failed);//IWI all task should be list in one page , start with open tasks
                    
                    $('#all_task').text(parseFloat(x.active + x.failed));//IWI all task should be list in one page , start with open tasks
                }
            });
        },

        /**
         * Initialize the Tasks tab toolbar (Add, Edit, Delete tasks)
         */
        init_Toolbar = function()
        {
            $("#tab_tasks .toolbar button")
                .jqxButton({ theme: cargohub.jqxtheme })
                /**
                 * Event: Toolbar button is clicked
                 */
                .on('click', function()
                {
                    switch($(this).data('op')) {
                        //Export data to Excel file
                        case 'exportxlsx':
                            $("#_record-tasks").jqxDataTable('exportData', 'xls');
                            break;
                        case 'filteractive':
                            var filtertype = 'stringfilter';
                            var dataField = 'status';
                            var filtergroup = new $.jqx.filter();
                            var checkedItems = ['assigned','inprogress','onhold','new'];
                            for (var i = 0; i < checkedItems.length; i++) {
                                var filter_or_operator = 1;
                                // set filter's value.
                                var filtervalue = checkedItems[i];
                                // set filter's condition.
                                var filtercondition = 'equal';
                                // create new filter.
                                var filter = filtergroup.createfilter(filtertype, filtervalue, filtercondition);
                                // add the filter to the filter group.
                                filtergroup.addfilter(filter_or_operator, filter);
                            }
                            // add the filters.
                            $("#_record-tasks").jqxDataTable('addFilter', dataField, filtergroup);
                            // apply the filters.
                            $("#_record-tasks").jqxDataTable('applyFilters');
                            $('.jqx-icon-close-fresh').fadeIn();
                            break;
                        case 'filterfailed':
                            var filtertype = 'stringfilter';
                            var dataField = 'status';
                            var filtergroup = new $.jqx.filter();
                            var checkedItems = ['failed','completed'];
                            for (var i = 0; i < checkedItems.length; i++) {
                                var filter_or_operator = 1;
                                // set filter's value.
                                var filtervalue = checkedItems[i];
                                // set filter's condition.
                                var filtercondition = 'equal';
                                // create new filter.
                                var filter = filtergroup.createfilter(filtertype, filtervalue, filtercondition);
                                // add the filter to the filter group.
                                filtergroup.addfilter(filter_or_operator, filter);
                            }
                            // add the filters.
                            $("#_record-tasks").jqxDataTable('addFilter', dataField, filtergroup);
                            // apply the filters.
                            $("#_record-tasks").jqxDataTable('applyFilters');
                            $('.jqx-icon-close-fresh').fadeIn();
                            break;
                        // Define new task. Hides the table while the Editor is active
                        case "task-create":
                            console.log('task-create');
                            $("#task-type").val('');
                            $("#task-type :nth-child(1)").prop('selected', true)
                    
                            $('.jqx-icon-close-fresh').css('display','none');
                            $('#taskXls,#taskAct,#taskFal').css('display','none');
                            master.clear_DataTable($("#tab_tasks div.jqx-datatable")); // Also unselect row if one is selected (which hides unneeded buttons)
                            $("#tab_tasks #tasks-table-title, #tab_tasks #_record-tasks").hide();
                            open_row_id = null;
                            copy_mode = false;

                            $('#tab_tasks .tasks-master-ops').fadeOut(); // Hide the "Create" button and the "View Hidden" checkbox when the editor is active, will be shown again on Save or Close
                            $('#tab_tasks .tasks-write-ops').fadeIn();
                            // Make sure the rich editor is already loaded before invoking it
                            master.load_rich_editor(function() {
                                init_Editor({
                                    title: "Define a new task",//IWI doc section file upload
                                    icon: "_add",
                                    mode: "create"
                                });
                            });
                            task_Counter();
                            
                             preSelectAttachments([]);//IWI doc section file upload
                                     $('#task-file-input').val('');//IWI doc section file upload
                                     $('#task-file-info').text('');//IWI doc section file upload
                                     
                            break;
                        // Copy is a "create mode" with already filled in data; The table is still shown
                        case "task-copy":
                            // TODO: Copy op
                            break;
                        // Open for editing (both master edit and assignee update)
                        case "task-open":
                            open_row_id = selected_row.uid;
                            task_Open();
                            $('.jqx-icon-close-fresh').css('display','none');
                            $('#taskXls,#taskAct,#taskFal,#task-create').css('display','none');
                            break;

                        // Save the task by collecting data from the editor (both master edit and assignee update)
                        case "task-save":
                            if(editor_mode.length) // Only if editor is open
                                task_Save();

                            $('#taskXls,#taskAct,#taskFal,#task-create').css('display','block');
                            task_Counter();
                            break;

                        // Closes either the Editor or the Viewer
                        case "task-close":
                            if(editor_mode.length)
                                close_Editor();
                            else
                                close_Viewer();
                            open_row_id = null;
                            $('#taskXls,#taskAct,#taskFal,#task-create').css('display','block');
                            break;

                        case "task-delete":
                            if(!tasks_manager_priv) // Just a secondary check
                                return;

                            cargohub.components.modal.delcustomconfirm(task_Delete, "Are you sure you want to remove this task ?",null,null,null,null,'delete');
                            break;
                    }
                });
            if(tasks_manager_priv) {
                $('#tab_tasks .tasks-master-ops').fadeIn();
                // Checkbox for show closed (those with status "closed")
                /*			$('#tab_tasks #tasks-show_closed').show().jqxCheckBox({
                                theme: cargohub.jqxtheme,
                                height: 18,
                                checked: false
                            })
                            .on('change', function(e) {
                                tasks_Source._source.data.show_closed = e.args.checked;

                                // Reload the tasks table when the checkbox is changed
                                master.refresh_DataTable($("#tab_tasks #_record-tasks"));
                            })
                            .find("div.jqx-checkbox-default").css('margin-top', "2px");*/
            }
        },

        /**
         * Initialize the Tasks DataTable. (This uses common functionality from the main Record Process module - cargohub.record_process )
         */
        init_DataTable = function()
        {
            var cell_highlight = function(row, column, value, rowData) {
                var cell_class = "";

                if(cargohub.application.user_Is_Admin() && rowData.org_id == master.config.perspective)
                    cell_class+= " grid-cell-perspective_org";

                switch(rowData.status) {
                    case 'closed':
                        cell_class+= " fiftycent"; break;
                    case 'onhold':
                        cell_class+= " grid-cell-onhold"; break;
                    case 'completed':
                        cell_class+= " grid-cell-completed"; break;
                    case 'failed':
                        cell_class+= " grid-cell-failed"; break;
                }

                return cell_class;
            };

            if(cargohub.application.user_Is_Admin())
                tasks_Source._source.data.read_org = true;
			
            master.init_DataTable($("#tab_tasks #_record-tasks"), {
                exportSettings: {
                    columnsHeader: true,
                    hiddenColumns: false,
                    serverURL: "/?action=export",
                    characterSet: null,
                    collapsedRecords: false,
                    recordsInView: true,
                    fileName: "task"
                },
                pageable:true,
                pageSizeOptions: ['5'],
                // Config
                source: tasks_Source,
                columns: [
					{dataField: 'status', text: "Status", width: 80, align: "center", cellsAlign: "center", cellClassName: cell_highlight},
                    {dataField: 'dt', text: 'Created on', width: 95, cellsFormat: cargohub.date_format, align: "center", cellsAlign: "center", filterable: false, cellClassName: cell_highlight},
                    {dataField: 'title', text: "Task Title", cellClassName: cell_highlight},
                    {dataField: 'deadline', text: "Due date", width: 95, cellsFormat: cargohub.date_format, align: "center", cellsAlign: "center", filterable: false, cellClassName: cell_highlight},
                    {dataField: 'assign_name', text: "Assigned to", width: 180, cellsRenderer: function(row, column, value, rowData) {
                            if(value)
                                if(rowData.assign_mode == 'dep')
                                    return "<span class='thick'>"+ value+ "</span>";
                                else
                                    return value;
                            else
                                return "<span class='fiftycent'>(not assigned)</span>";
                        }, cellClassName: cell_highlight},
                    {hidden: true, dataField: 'creator_name', text: "Created by", width: 155, filterable: true, cellClassName: cell_highlight}, // Can search by creator, but saving space by not displaying it
                    {dataField: 'progress', text: "Progress", width: 68, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
                            if(!value)
                                return "<span class='fiftycent'>---</span>";
                            if(value == 100)
                                return "<b>Done!</b>";
                            else
                                return value+ "%";
                        }, cellClassName: cell_highlight},
					{dataField: 'tDate', text: "Completed Date",width: 120, cellsFormat: cargohub.date_format, align: "center", cellsAlign: "center", cellClassName: cell_highlight},
                    {hidden: true, dataField: 'description', text: "Task Description", filterable: true}, // Task descriptions doesn't display, but is used for searching/filtering
                    {dataField: 'org_name', text: "Organization", width: 160, hidden: !cargohub.application.user_Is_Admin(), filterable: true, cellClassName: cell_highlight}
                ]
            }, {
                // Callbacks (for events happening in the datatable itself) - In most cases it will be either View or Edit
                "getrows": function() { /* Nothing here atm */
                // $('#taskAct').click(); //IWI first load all active tasks // IWI all task should be list in one page , start with open tasks
                },
                "rowselect": select_Row,
                "rowopen": task_Open,
                "clearselect": select_Clear
            }, "tasks");
        }
    ;
    this._public({
        /**
         * Setup Tasks Tab for Record Process module
         */
        init: function()
        {
            init_Toolbar();
            init_DataTable();
            task_Counter();
            // Tooltips inside the Tasks tab
            $("#tab_tasks .info-tip").each(cargohub.components.ui.add_Tooltip);
        },

        /**
         * Callback for actions to be performed when this tab is selected
         */
        selected: function(params)
        {
            if(params && params.trigger)
                switch(params.trigger) {
                    // Trigger the Define Task function
                    case "task-create":
                        $("#tab_tasks #tasks-table-title, #tab_tasks #_record-tasks").hide();
                        open_row_id = null;
                        copy_mode = false;

                        $('#tab_tasks .tasks-master-ops').fadeOut(); // Hide the "Create" button and the "View Hidden" checkbox when the editor is active, will be shown again on Save or Close
                        $('#tab_tasks .tasks-write-ops').fadeIn();
                        // Make sure the rich editor is already loaded before invoking it
                        master.load_rich_editor(function() {
                            init_Editor({
                                title: "Define a new task",
                                icon: "_add",
                                mode: "create"
                            });
                        });
                        break;
                }
        }
    });
   
    
//     IWI Create a files upload section in task start
    function appendFormData(formData, data, parentKey = '') {
//        console.log('appendFormData');
    if (data && typeof data === 'object' && !(data instanceof File)) {
        Object.keys(data).forEach(key => {
            const fullKey = parentKey ? `${parentKey}[${key}]` : key;
            appendFormData(formData, data[key], fullKey);
        });
    } else {
        formData.append(parentKey, data);
    }
}

//                  IWI Create a files upload section in task end


$(document).on('click', '.removeTaskFile', function () {
    var $button = $(this);
    var data_id = $button.attr('data-id');

    Swal.fire({
        title: 'Are you sure?',
        text: "If you delete this file, it won't be reverted back!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '/global/task?action=remove_task_file',
                type: 'POST',
                dataType: 'json',
                data: { file_id: data_id },
                success: function (response) {
                    console.log("Response:", response);
                    if (response.success === true) {
                        $button.closest('div[data-file-id]').remove();

                        Swal.fire(
                            'Deleted!',
                            'The file has been deleted.',
                            'success'
                        );
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Error deleting file:", error);
                    Swal.fire(
                        'Error',
                        'Something went wrong while deleting the file.',
                        'error'
                    );
                }
            });
        }
    });
});

//IWI doc section file upload start
$(document).on('change', '#task-file-input', function () {
  const files = this.files;
  const infoBox = $('#task-file-info');

  if (files.length === 1) {
    infoBox.text(files[0].name);
  } else if (files.length > 1) {
    infoBox.text(`${files.length} files selected`);
  } else {
    infoBox.text('');
  }
});


//IWI doc section file upload  end


// Iwi setting button task start 
 
document.getElementById('open-settings-task').addEventListener('click', function () {
  document.getElementById('settings-modal-task').style.display = 'flex';
});

document.getElementById('close-settings-task').addEventListener('click', function () {
  document.getElementById('settings-modal-task').style.display = 'none';
});

document.getElementById('settings-modal-task').addEventListener('click', function (e) {
  if (e.target === this) {
    this.style.display = 'none';
  }
});





  // Iwi setting button task end

  //file_images show in the task tab _end



// IWI show description update history start 
document.getElementById('toggle-task-grid').addEventListener('click', function () {   // IWI show description update history start
    var grid = document.getElementById("task-history-grid");
    var icon = document.getElementById("toggle-icon");

    if (grid.style.display === "none" || grid.style.display === "") {
        grid.style.display = "block";
        icon.textContent = "▲";
        applyShowMoreLogic();
    } else {
        grid.style.display = "none";
        icon.textContent = "▼";
    }
});

function applyShowMoreLogic() {
    const wordLimit = 75;
    const descriptions = document.querySelectorAll("#task-history-grid .description");

    descriptions.forEach(desc => {
        // Skip if already processed
        if (desc.dataset.processed === "true") return;

        const fullText = desc.textContent.trim();
        const words = fullText.split(/\s+/);

        if (words.length > wordLimit) {
            const shortText = words.slice(0, wordLimit).join(" ") + "...";

            const shortSpan = document.createElement("span");
            shortSpan.textContent = shortText;

            const fullSpan = document.createElement("span");
            fullSpan.textContent = fullText;
            fullSpan.style.display = "none";

            const toggleLink = document.createElement("a");
            toggleLink.href = "#";
            toggleLink.textContent = " Show more";
            toggleLink.style.color = "#3b82f6";
            toggleLink.style.marginLeft = "5px";

            toggleLink.addEventListener("click", function (e) {
                e.preventDefault();
                const isExpanded = fullSpan.style.display === "block";
                fullSpan.style.display = isExpanded ? "none" : "block";
                shortSpan.style.display = isExpanded ? "block" : "none";
                toggleLink.textContent = isExpanded ? " Show more" : " Show less";
            });

            // Optional: Keep formatting for line breaks
            desc.style.whiteSpace = "pre-line";

            // Rebuild content
            desc.innerHTML = "";
            desc.appendChild(shortSpan);
            desc.appendChild(fullSpan);
            desc.appendChild(toggleLink);
        }

        // Mark as processed to avoid reprocessing
        desc.dataset.processed = "true";
    });
}                                                                                  
// IWI show description update history end 





});