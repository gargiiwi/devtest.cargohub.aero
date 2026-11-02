/**
 * Module "Task Manager"
 * - Overview of user, department or organizationt tasks.
 * - Supports creating "unlinked" tasks. To create tasks linked to records, the record has to be opened first and the Tasks tab used for that purpose.
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2.7
 */
cargohub.task_manager = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,
		edit_privilege = null,
		// Data from the table containing currently selected row. Used for View, Edit, Delete - Set on any row operation.
		selected_row = null,
		// Source for the Grid
		tasks_Source = new $.jqx.dataAdapter({
			url: "/global/task?action=get_list",
			data: {
				virtualmode: true,
				show_closed: true,
				nodescr: true  
			},
			datatype: "json",
			id: "id",
			root: "data",
			datafields: [
                            {name: 'id', type: 'int'},	//IWI All Task grid changes 16072026
			    {name: 'task_type', type: 'string'},             //task_type feild added 
				{name: 'link_type', type: 'string'},			// empty, or "record"
				{name: 'link_id', type: 'int'},					// Should be record ID or nothing
				{name: 'assign_mode', type: 'string'},			// "user", "dep" or "usrgrp" (not implemented)
				{name: 'assign_id', map: 'assign>id', type: 'int'},
				{name: 'assign_name', map: 'assign>name'},	// This is either user name, department name or usergroup name
				{name: 'creator_id', map: 'added_by>id', type: 'int'},
				// {name: 'creator_name', map: 'added_by>name'},	// Creator is always an user
				{name: 'creator_email', map: 'added_by>email'},
				{name: 'dt', type: 'date'},
				{name: 'deadline', type: 'date'},
				{name: 'status', type: 'string'},
				{name: 'title', type: 'string'},
				{name: 'progress', type: 'int'},
				// TODO: These should not be permanent, when reading "all task" ( not related to records ), they will be empty; REC = the linked RECord, RT = it's Record Type
				{name: 'rec_track', type: 'string', map: 'rec>track'},
				{name: 'group_id', type: 'string', map: 'group>id'},
				{name: 'group_name', type: 'string', map: 'group>name'},
				{name: 'rec_primary', type: 'string', map: 'rec>cache_primary'},
				//{name: 'rec_secondary', type: 'string', map: 'rec>cache_secondary'},
				{name: 'rt_id', type: 'string', map: 'rt>id'},
				{name: 'rt_key', type: 'string', map: 'rt>key'},
				{name: 'rt_name', type: 'string', map: 'rt>name'},
				{name: 'tDate',type:'date'}
			],
			// server side operations - filtering and sorting:
			cache: false,
			filter: function() {
				$("#tasks-grid").jqxGrid('updatebounddata', 'filter');
			},
			sort: function() {
				$("#tasks-grid").jqxGrid('updatebounddata', 'sort');
			},
			beforeprocessing: function(source) {
				this.totalrecords = source.found_rows;
			},
			/**
			 * Client-Side pre-processing of filter data, because the way the grid sends it by default is total crap.
			 */
			formatData: cargohub.its.jqxGrid_FormatFilters
		}),
		// Common configuration for Status control, for both Handler and Creator/Editor mode
		status_config = {
			displayMember: "text", valueMember: "value",
			width: 160, height: 25,
			theme: cargohub.jqxtheme,
			autoDropDownHeight: true
		},
		/**
		 * A task row has been selected - Store information in preparation for a double-click ( for Viewing or Editing ), or for clicking on one of the toolbar buttons
		 */
		
                
                
//                IWI All Task grid changes 16072026 start
		/* 
                 select_Row = function(event)
		{
			var operations_bar = edit_privilege? "#tasks-row-editops": "#tasks-row-ops";
			if(!selected_row) {
				donrey.cancel_Delay('hide-operations_bar');
				$(operations_bar+ ' button').jqxButton({disabled: false});
				$(operations_bar).fadeIn();
			}
			if(event.args.row)
				selected_row = event.args.row;
			if(self.config.link_type != "all" && selected_row.link_type) {
				$(operations_bar+ ' button[data-op="task-linked"]').show();
			} else
				$(operations_bar+ ' button[data-op="task-linked"]').hide();
		},*/
		/**
		 * No rows are currently selected - Make the toolbar buttons dissapear
		 */
                
                updateSelectedTasks = function (event) {
                var grid = $("#tasks-grid");

                // Selected row indexes
                var indexes = grid.jqxGrid("getselectedrowindexes");
                var operations_bar = edit_privilege ? "#tasks-row-editops" : "#tasks-row-ops";

                // Get all selected row objects
                var selectedRows = indexes.map(function (index) {
                    return grid.jqxGrid("getrowdata", index);
                });

                // Get only the task IDs
                var selectedTaskIds = selectedRows.map(function (row) {
                    return row.id;
                });

//    console.log(selectedTaskIds);

                if (selectedRows.length === 0) {
                    selected_row = null;
                    $(operations_bar).fadeOut();
                    $(operations_bar + ' button[data-op="task-complete"]').hide();
                } else if (selectedRows.length === 1) {
                    selected_row = selectedRows[0];

                    donrey.cancel_Delay('hide-operations_bar');
                    $(operations_bar + ' button').jqxButton({disabled: false});
                    $(operations_bar).fadeIn();

                    // Bulk button
                    $(operations_bar + ' button[data-op="task-complete"]').show();

                    // Linked button
                    if (self.config.link_type != "all" && selected_row.link_type) {
                        $(operations_bar + ' button[data-op="task-linked"]').show();
                    } else {
                        $(operations_bar + ' button[data-op="task-linked"]').hide();
                    }

                    // Show single-row buttons
                    $(operations_bar + ' button[data-op="task-view"]').show();
                } else {
                    // Multiple selected
                    selected_row = null;

                    donrey.cancel_Delay('hide-operations_bar');
                    $(operations_bar).fadeIn();

                    // Hide single-row buttons
                    $(operations_bar + ' button[data-op="task-view"]').hide();
                    $(operations_bar + ' button[data-op="task-linked"]').hide();

                    // Show only bulk action
                    $(operations_bar + ' button[data-op="task-complete"]').show();
                }
            },
               updateTaskStatus = function () {

    var grid = $("#tasks-grid");
    var indexes = grid.jqxGrid("getselectedrowindexes");

    var selectedTaskIds = indexes.map(function (index) {
        return grid.jqxGrid("getrowdata", index).id;
    });

    if (selectedTaskIds.length === 0) {
        Swal.fire({
            icon: "warning",
            title: "No Tasks Selected",
            text: "Please select at least one task."
        });
        return;
    }

    var statusMap = {
        inprogress: { label: "In Progress" },
        onhold: { label: "On Hold" },
        completed: { label: "Completed" },
        failed: { label: "Failed" },
        expired: { label: "Expired" },
        closed: { label: "Closed" }
    };

    var html =
        '<div style="text-align:left;">' +
            '<label style="font-weight:600;display:block;margin-bottom:8px;">Select the new status</label>' +
            '<select id="bulkStatus" class="swal2-select" style="display:block;width:100%;margin:0;">' +
                '<option value="">-- Select Status --</option>';

    $.each(statusMap, function (key, value) {
        html += '<option value="' + key + '">' + value.label + '</option>';
    });

    html += '</select></div>';

    Swal.fire({
        title: "Update Task Status",
        html: html,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: '<i class="fa fa-check"></i> Update',
        cancelButtonText: "Cancel",
        confirmButtonColor: "#28a745",
        cancelButtonColor: "#6c757d",
        width: "500px",
        focusConfirm: false,
        preConfirm: function () {
            var status = $("#bulkStatus").val();

            if (!status) {
                Swal.showValidationMessage("Please select a status.");
                return false;
            }

            return status;
        }
    }).then(function (result) {

        if (!result.isConfirmed) {
            return;
        }

        $.ajax({
            url: "/global/task?action=updateBulkTaskStatus",
            type: "POST",
            traditional: true,
            data: {
                "ids[]": selectedTaskIds,
                status: result.value
            },
            success: function () {

                $("#tasks-grid").jqxGrid("updatebounddata");
                $("#tasks-grid").jqxGrid("clearselection");

                Swal.fire({
                    icon: "success",
                    title: "Tasks Updated",
                    text: selectedTaskIds.length + " task(s) updated successfully.",
                    timer: 1500,
                    showConfirmButton: false
                });
            },
            error: function (xhr) {
                Swal.fire({
                    icon: "error",
                    title: "Update Failed",
                    text: xhr.responseText || "Unable to update tasks."
                });
            }
        });

    });

},
                
//    IWI All Task grid changes 16072026 end
		/**
		 * No rows are currently selected - Make the toolbar buttons dissapear
		 */
		select_Clear = function()
		{
			var operations_bar = edit_privilege? "#tasks-row-editops": "#tasks-row-ops";
			selected_row = null;
			$(operations_bar+ ' button').jqxButton({disabled: true});
			donrey.single_Delay('hide-operations_bar', function() {
				$(operations_bar).fadeOut();
			}, 500);
		},
		/**
		 * Sets up the status and progress line ( has three interconnected controls )
		 */
		setup_Status_Progress = function($window, status_source)
		{
			var
				// Common configuration for Status control, for both Handler and Creator/Editor mode
				status_config = {
					source: status_source,
					displayMember: "text", valueMember: "value",
					width: 160, height: 25,
					theme: cargohub.jqxtheme,
					autoDropDownHeight: true, animationType: "fade"
				},
				progress_control = null;
			// Load existing status value
			for(c=0; c<status_config.source.length; c++)
				if(selected_row.status == status_config.source[c].value)
					status_config.selectedIndex = c;
			$("#task-status", $window).jqxDropDownList(status_config)
				/**
				 * Event: Status is changed - If set to "completed", also set Progress to 100%
				 */
				.on('select', function(event) {
					if(event.args.item.value == 'completed') {
						$('#task-progress_slider', $window).jqxSlider('setValue', 100);
						progress_control = 'input';
					}
				});
			// The completion box and range slider (connected)
			$('#task-progress', $window).jqxNumberInput({
				theme: cargohub.jqxtheme,
				width: 40, height: 21,
				inputMode: 'simple',
				min: 0, max: 100, decimalDigits: 0,
				decimal: $('#task-progress', $window).data('value')
			}).on('valueChanged', function(ev) {
				if(progress_control == 'slider') {
					progress_control = null;
					return;
				}
				$('#task-progress_slider', $window).jqxSlider('setValue', ev.args.value);
				progress_control = 'input';
			}).on('change', function() {
				progress_control = null;
			});
			$('#task-progress_slider', $window).jqxSlider({
				theme: cargohub.jqxtheme,
				width: 250, height: 25,
				mode: 'fixed',
				min: 0, max: 100, step: 5,
				value: $('#task-progress_slider', $window).data('value'),
				showButtons: false,
				ticksFrequency: 10, ticksPosition: 'bottom'
			}).on('change', function(ev) {
				if(progress_control == 'input') {
					progress_control = null;
					return;
				}
				$('#task-progress', $window).jqxNumberInput('setDecimal', ev.args.value);
				progress_control = 'slider';
			}).on('blur', function() {
				progress_control = null;
			});
		},
		/**
		 * Sets up and opens the window used to create or change a selected task.
		 * -- Creation or Updating is done via an Ajax call. Refreshes the DataTable on success
		 */
		open_TaskEditor = function(mode)
		{
			var editor_title = "";
			if(!selected_row) {
				mode = "create";
				editor_title = "Define unlinked task";
				switch(self.config.tasks_class.mode) {
					case 'org':
					case 'org_id':
						editor_title+= " for organization <b>"+ self.config.tasks_class.name+ "</b>"; break;
					case 'dep':
					case 'dep_id':
						editor_title+= " for department <b>"+ self.config.tasks_class.name+ "</b>"; break;
					case 'self':
						editor_title+= " for myself"; break;
					case 'user_id':
						editor_title+= " for user <b>"+ self.config.tasks_class.name+ "</b>"; break;
				}
			} else {
				// Data preformatting on edit
				selected_row.dt_format = $.format.date(selected_row.dt, cargohub.datetime_format);
				if(selected_row.deadline)
					selected_row.deadline_format = $.format.date(selected_row.deadline, cargohub.date_format);
				//  Determine to what is the task linked ( For now, the only option is "record", however in the future tasks might be created for other purposes as well.
				if(selected_row.link_type == 'record' && selected_row.rec_track)
					editor_title = "Update task for: " + selected_row.rt_name+ " - "+ selected_row.rec_track;
				else
					editor_title = "Update general task";
			}
			cargohub.components.modal.create('task-editor', {
				btn_roles: {cancel: "cancel"}
			});
			cargohub.components.modal.open('task-editor', {
				width: 720,	height: mode == 'update'? 550: 485,
				title: editor_title,
				content: $('#template_task-editor').html().present({
					_itc: cargohub.input_theme_classes,
					action: mode,
					task: (mode == 'update')? selected_row: null
				}),
				callback: function($window) // Setup the controls inside the window
				{
					var assign_mode = null, departments_Active = false, update_mode_usermode_init = (mode == "update" && selected_row.assign_mode == "user"),
						departments_Source = new $.jqx.dataAdapter({
							url: "/manager/department?action=get_list",
							data: {},
							datatype: "json",
							datafields: [
								{name: 'id', type: 'int'},
								{name: 'name', type: 'string'},
								{name: 'count_members', type: 'int'}
							],
							id: "id"
						}),
						/**
						 * Activates the department selector and if in Update mode also selects the proper department (This in turn triggers selecting the user after the list is loaded)
						 */
						activate_Departments = function()
						{
							if(departments_Active)
								return;
							// Admin only: Editing tasks that belong to the selected organization. Load the departments of that organization
							if(self.config.tasks_class.mode == 'org_id')
								departments_Source._source.data.org_id = self.config.tasks_class.id;
							// In create mode, load departments related to the active organization for Organization or assigning to an User
							if(mode == 'create' && self.config.tasks_class.mode != "dep" && self.config.tasks_class.mode != "dep_id") {
							}
							// In update mode, load departments which are related to the selected task (which might be linked to a record)
							if(mode == 'update' && self.config.tasks_class.mode != "dep" && self.config.tasks_class.mode != "dep_id") {
								if(selected_row.link_type == "record")
									// Linked to a record ? Add the tracking ID to the request, to filter only those departments that are enabled for the record
									departments_Source._source.data.track = selected_row.rec_track;
							}
							$("#task-assign-dep", $window).jqxDropDownList({
								source: departments_Source,
								placeHolder: "(Loading departments)",
								disabled: false
							});
						}, members_Source = new $.jqx.dataAdapter({
							datatype: "json",
							datafields: [
								{name: 'id', type: 'int'},
								{name: 'name', type: 'string'}
							],
							id: "id"
						}), $task_assign_user = $("#task-assign-user", $window), progress_control = null;
					// The Active Departments selector
					$("#task-assign-dep", $window).jqxDropDownList({
						theme: cargohub.jqxtheme,
						displayMember: "name", valueMember: "id",
						placeHolder: "(Select assignment type)",
						width: 450, dropDownWidth: 450,
						autoDropDownHeight: true, animationType: "fade", enableBrowserBoundsDetection: true,
						// Member count for department appears on the right side of the rows
						renderer: function(index, label, value) {
							return '<span class="gray right">('+ departments_Source.records[index].count_members+' members)</span>'+ label;
						},
						disabled: true
					})
						/**
						 * Event: The department list finished loading, set the currently active department if assing mode is "departent"
						 */
						.on('bindingComplete', function() {
							$(this).jqxDropDownList({
								placeHolder: mode == 'update'? "(Select only when reassigning the task)": "Select:",
							});
							if(mode == 'update' && selected_row.assign_mode == 'dep')
								for(var c=0; c< departments_Source.records.length; c++)
									if(departments_Source.records[c].id == selected_row.assign_id) {
										$(this).jqxDropDownList('selectIndex', c);
										break;
									}
							departments_Active = true;
						})
						/**
						 * Event: The department selector - Load the member list (visible or not, depending on the radiobuttons)
						 */
						.on("select", function(event) {
							if(event.args.item.value) {
								// Read from a department membership list
								members_Source._source.url = '/manager/department?action=get_members',
									members_Source._options.data = {
										id: event.args.item.value
									};
							}
							$task_assign_user.jqxComboBox('clearSelection');
							$task_assign_user.jqxComboBox({
								source: members_Source,
								placeHolder: "Type to search or click arrow to show all",
								disabled: false
							});
						});
					$task_assign_user.jqxComboBox({
						theme: cargohub.jqxtheme,
						searchMode: "containsignorecase",
						autoComplete: true,	minLength: 0,
						displayMember: "name", valueMember: "id",
						placeHolder: "(Select a department first)",
						width: 450, height: 24, dropDownWidth: 450,	dropDownHeight: 220,
						animationType: "fade", enableBrowserBoundsDetection: true,
						disabled: true
					})
						/**
						 * Event: The user list has been loaded - If we're in Update mode on primary initialization, select the user automatically from the loaded list
						 */
						.on('bindingComplete', function() {
							$(this).jqxComboBox({
								placeHolder: "Type to search or choose:",
							});
							// Only at first time initializing ...
							if(update_mode_usermode_init) {
								for(c=0; c< members_Source.records.length; c++)
									if(members_Source.records[c].id == selected_row.assign_id)
										$task_assign_user.jqxComboBox('selectIndex', c);
								update_mode_usermode_init = false;
							}
						});
					// The assignment type radio buttons - They appear here after the department/user initialization because in Update mode they will trigger automatic activation of the department/user selector
					$("#asgtype-radios > div", $window).each(function() {
						var option_data = $(this).data();
						// Disable the "User" option when task class is a department. The active department is also auto-selected
						if((option_data.value == 'user' || option_data.value == 'self') && (self.config.tasks_class.mode == "dep" || self.config.tasks_class.mode == "dep_id")) {
							option_data.disabled = true;
							$(this).data('tooltip', "Disabled because the Task Manager is currently showing only Department-assigned tasks.");
						}
						// Disable the "User" and "Department" options when assigning to self
						if((option_data.value == 'dep' || option_data.value == 'user') && self.config.tasks_class.mode == "self") {
							option_data.disabled = true;
							$(this).data('tooltip', "Disabled because the Task Manager is currently in assigned-to-self mode.");
						}
						if(option_data.value == 'self' && self.config.tasks_class.mode == "org_id") {
							option_data.disabled = true;
							$(this).data('tooltip', "Cannot assign to self when editing tasks for another organization.");
						}
						$(this).css({'display': 'inline-block', 'width': '111px'}).jqxRadioButton({
							theme: cargohub.jqxtheme, height: 16,
							groupName: "asgtype",
							checked: false,
							disabled: option_data.disabled
						})
							/**
							 * Event: A radiobutton is checked.
							 * Switch the operation mode for assignment search (after reseting existing value)
							 */
							.on('checked', function(e) {
								assign_mode = option_data.value;
								if(self.config.tasks_class.mode != "self" && self.config.tasks_class.mode != "user_id")
									activate_Departments();
								switch(assign_mode) {
									case 'self':
										$task_assign_user.parent().show();
										$task_assign_user.jqxComboBox({
											disabled: true
										}).val(self.config.tasks_class.user_name);
										$("#task-assign-dep", $window).parent().hide(); // No departments here
										break;
									case 'user':
										$task_assign_user.parent().show();
										switch(self.config.tasks_class.mode) {
											// Editing tasks assigned to a specified user only
											case 'user_id':
												$task_assign_user.jqxComboBox({
													disabled: true
												}).val(self.config.tasks_class.name);
												$("#task-assign-dep", $window).parent().hide(); // No departments here
												break;
											// Editing tasks that belong to the selected organization. Load the departments of that organization
											case 'org':
											case 'org_id':
												$("#task-assign-dep", $window).parent().show();
												// In Update mode, if this is the primary initializaton, loads the user list depending on grid loading class
												if(update_mode_usermode_init) {
													members_Source._source.url = '/manager/organization?action=get_users';
													members_Source._options.data = {
														id: self.config.tasks_class.id,
														quickmode: true
													};
													$task_assign_user.jqxComboBox({
														source: members_Source,
														placeHolder: "(Loading users)",
														disabled: false
													});
												} else {
													if($("#task-assign-dep", $window).val()) {
														$task_assign_user.jqxComboBox({
															placeHolder: "(Select user)",
															disabled: false
														});
														$task_assign_user.jqxComboBox('clearSelection');
													} else {
														$task_assign_user.jqxComboBox({
															source: null,
															disabled: true,
															placeHolder: "(Select a department first)"
														});
													};
												}
												break;
										}
										break;
									case 'dep':
										$("#task-assign-dep", $window).parent().show(); // Make sure the dep selector or display is visible
										switch(self.config.tasks_class.mode) {
											// Editing tasks assigned to departments (own or another)
											case 'dep':
											case 'dep_id':
												$("#task-assign-dep", $window).jqxDropDownList({
													source: null,
													disabled: true,
													placeHolder: self.config.tasks_class.name
												});
												break;
										}
										$task_assign_user.parent().hide();
										break;
									case 'usrgrp':
										cargohub.alert("Core breach! Core breach! Evacuate the starship !");
										break;
								}
							});
						cargohub.components.ui.add_Tooltip.call(this);
						if(mode=="update" && selected_row.assign_id && option_data.value == selected_row.assign_mode)
							$(this).jqxRadioButton('check'); // This should also trigger the "checked" event
						if(option_data.value == 'self' && self.config.tasks_class.mode == "self")
							$(this).jqxRadioButton('check'); // Auto-check "Self"
						if(option_data.value == 'user' && self.config.tasks_class.mode == "user_id")
							$(this).jqxRadioButton('check'); // Auto-check "Person" when in user mode
						if(option_data.value == 'dep' && (self.config.tasks_class.mode == "dep" || self.config.tasks_class.mode == "dep_id"))
							$(this).jqxRadioButton('check'); // Auto-check "Department" when in dep mode
					});
					// Calendar control (Deadline)
					cargohub.components.ui.calendar_config = {limit_future: true, height: 24, align: "right"};
					$("div.calendar", $window).each(cargohub.components.ui.calendar_input);
					cargohub.components.ui.calendar_config = {};
					// In Update mode, three more controls appear on the form: Status, Progress (number + range slider)
					if(mode == "update")
						setup_Status_Progress($window, [
							{value: "new", text: "New task"},
							{value: "assigned", text: "Assigned (ready)"},
							{value: "inprogress", text: "In progress"},
							{value: "onhold", text: "On hold (paused)"},
							{value: "completed", text: "<i>Completed task</i>"},
							{value: "failed", text: "<i>Failed task</i>"},
							{value: "closed", text: "<span class='gray'>Hide from view</span>"}
						]);
					// Setup the action for "OK" button (start with it disabled, enabled when user has been selected)
					$('#modal_task-editor_ok', $window).off('click').on('click', function()
					{
						var submit_metadata = {
							assign_mode: assign_mode
						}, selected_item = null;
						// assign_to can remain empty if no assignment type has been selected, no department or no user
						switch(assign_mode) {
							case 'self':
								// Nothing to do here, PHP will take care of it
								break;
							case 'user':
								selected_item = self.config.tasks_class.mode == "user_id"? {
									value: self.config.tasks_class.id,
									label: self.config.tasks_class.name
								}: $task_assign_user.jqxComboBox('getSelectedItem');
								break;
							case 'dep':
								selected_item = (self.config.tasks_class.mode == "dep" || self.config.tasks_class.mode == "dep_id")? {
									value: self.config.tasks_class.id,
									label: self.config.tasks_class.name
								}: $("#task-assign-dep", $window).jqxDropDownList('getSelectedItem');
								break;
							case 'usrgrp':
								// Not implemented!
								return;
						}
						if(selected_item) {
							submit_metadata.assign_to = selected_item.value;
							submit_metadata.assign_name = selected_item.label;
						}
						// Editing tasks that belong to an arbitrary organization ( Admin only )
						if(self.config.tasks_class.mode == 'org_id')
							submit_metadata.org_id = self.config.tasks_class.id;
						// Editint tasks for an arbitrary user or department - Organization is needed too to link the task to the proper org ( Managers )
						if(self.config.tasks_class.mode == 'user_id' || self.config.tasks_class.mode == 'dep_id')
							submit_metadata.org_id = self.config.tasks_class.org_id;
						if(mode == "update") {
							submit_metadata.id = selected_row.uid;
							if(selected_row.linked_with == "record")
								submit_metadata.link_id = selected_row.rec_id;
						}
						// submit the create/edit form using ajax
						$('.modal_content > form', $window).ajaxSubmit({
							data: submit_metadata,
							dataType: 'json',
							beforeSubmit: function(formData, jqForm, options) {
								// Checks if something has been typed inside the task title
								if($("#task-title", $window).val().length < 5) {
									cargohub.alert("The title of the task should have at least 5 characters.");
									return false;
								}
								return true;
							},
							success: function(x) {
								if(x.success) {
									// Task has been created/updated - Close the window / Refresh the grid
									$window.jqxWindow('close');
									$("#tasks-grid").jqxGrid('clearselection');
									select_Clear();
									$("#tasks-grid").jqxGrid('updatebounddata');
									cargohub.alert(x.response, "Success !", "thumb_up.png");
								} else
									cargohub.its.http_fail(null, "Global.Task::"+mode);
							},
							error: cargohub.its.http_fail
						});
					});
				}
			}, self);
		},
		/**
		 * Prepares the "Options" modal, which allows changing how the grid lows (by altering the controller URL on pressing OK - and reloading the page)
		 */
		open_GridOptions = function()
		{
			var link_type_source = [
					{value: 'all', text: "<span class='right gray'>(Less detail)</span>Any entity (including unlinked)"},
					{value: 'record', text: "<span class='right gray'>(Type, TrackID, Cache)</span>Primary Records"}
				], link_type_index = -1,
				tasks_class_source = [ // Availability of these items should come from PHP
					{value: 'self', text: "Show only my assigned tasks"}
				], tasks_class_index = -1;
			// Not all "Task classes" are available all the time - It depends on user's access and how the module was loaded
			if(self.config.tasks_class.access_dep)
				tasks_class_source.push({value: 'dep', text: "View tasks assigned to my department"});
			if(self.config.tasks_class.access_org)
				tasks_class_source.push({value: 'org', text: "My organization's tasks"});
			// User, Department or Organization present in the URL, if server check was passed it means managing tasks for that entity
			if(self.config.tasks_class.id)
				switch(self.config.tasks_class.mode) {
					case 'user_id':
						tasks_class_source.push({value: 'user_id', text: "Tasks assigned to <b>"+ self.config.tasks_class.name+ "</b>"});
						break;
					case 'dep_id':
						tasks_class_source.push({value: 'dep_id', text: "View tasks assigned to <b>"+ self.config.tasks_class.name+ "</b> department"});
						break;
					case 'org_id':
						tasks_class_source.push({value: 'org_id', text: "Tasks belonging to <b>"+ self.config.tasks_class.name+ "</b> organization"});
						break;
				}
			cargohub.components.modal.create('grid-options', {
				btn_roles: {cancel: "cancel"}
			});
			cargohub.components.modal.open('grid-options', {
				width: 600,	height: 160,
				title: "Task Manager - Grid loading Options",
				content: $('#template_grid-options').html().present({
					_itc: cargohub.input_theme_classes
				}),
				callback: function($window) // Setup the controls inside the window
				{
					$('#modal_grid-options_ok', $window).jqxButton({disabled: true}).jqxButton('render');
					// Class of tasks (assigned, department, organization)
					for(c=0; c< tasks_class_source.length; c++)
						if(tasks_class_source[c].value == self.config.tasks_class.mode)
							tasks_class_index = c;
					$("#grid-tasks-class").jqxDropDownList({
						source: tasks_class_source,
						theme: cargohub.jqxtheme,
						displayMember: "text", valueMember: "value",
						width: 420, height: 24, dropDownWidth: 420, autoDropDownHeight: true, animationType: "fade",
						selectedIndex: tasks_class_index
					})
						/**
						 * Event: Something else is selected, turn the OK button on.
						 */
						.on("select", function(event) {
							$('#modal_grid-options_ok', $window).jqxButton({disabled: false}).jqxButton('render');
						});
					// Grid loading type (columns configuration)
					if(self.config.link_type)
						for(c=0; c< link_type_source.length; c++)
							if(link_type_source[c].value == self.config.link_type)
								link_type_index = c;
					$("#grid-link-type").jqxDropDownList({
						source: link_type_source,
						theme: cargohub.jqxtheme,
						displayMember: "text", valueMember: "value",
						width: 420, height: 24, dropDownWidth: 420, autoDropDownHeight: true, animationType: "fade",
						selectedIndex: link_type_index
					})
						/**
						 * Event: Something else is selected, turn the OK button on.
						 */
						.on("select", function(event) {
							$('#modal_grid-options_ok', $window).jqxButton({disabled: false}).jqxButton('render');
						});
					// Add some tooltips...
					$(".info-tip", $window).each(cargohub.components.ui.add_Tooltip);
					// Setup the action for "OK" button (start with it disabled, enabled when user has been selected)
					$('#modal_grid-options_ok', $window).off('click').on('click', function()
					{
						var location = "/manager/tasks:"+ $("#grid-link-type").val(), tasks_class = $("#grid-tasks-class").val();
						if(tasks_class.slice(-3) == "_id")
							location+= "?"+ tasks_class+ "="+ self.config.tasks_class.id;
						else
							location+= "?list="+ tasks_class;
						// Done, reload !
						document.location = location;
					});
				}
			});
		},
		/**
		 * Opens a view window for the currently selected task (Read-Only). Valid for anyone that has access to tasks
		 */
		task_View = function(event)
		{
			if(!selected_row && event.args && event.args.row)
				selected_row = event.args.row.bounddata;
			var editor_title = "";
			// Data preformatting on edit
			selected_row.dt_format = $.format.date(selected_row.dt, cargohub.datetime_format);
			if(selected_row.deadline)
				selected_row.deadline_format = $.format.date(selected_row.deadline, cargohub.date_format);
			if(selected_row.link_type == 'record' && selected_row.rec_track)
				editor_title = "Update task for: " + selected_row.rt_name+ " - "+ selected_row.rec_track;
			else
				editor_title = "Update general task";
			cargohub.components.modal.create('task-view', {
				btn_roles: {cancel: "cancel"}
			});
			cargohub.components.modal.open('task-view', {
				width: 720,	height: 420,
				title: editor_title,
				content: $('#template-task-update').html().present({
					_itc: cargohub.input_theme_classes,
					task: selected_row
				}),
				callback: function($window) // Setup the controls inside the window
				{
					setup_Status_Progress($window, [
						{value: "inprogress", text: "In progress"},
						{value: "onhold", text: "On hold (paused)"},
						{value: "completed", text: "Finished task"},
						{value: "failed", text: "Failed to complete"}
					]);
				}
			});
		},
		/**
		 * Opens the task editing window (This version can edit title, description, deadline)
		 */
		task_Edit = function(event)
		{
			if(!selected_row && event.args && event.args.row)
				selected_row = event.args.row.bounddata;
			open_TaskEditor('update');
		},
		/**
		 * Removes the selected task
		 */
		task_Delete = function()
		{
			var post_metadata = {
				id: selected_row.uid
			};
			if(selected_row.link_type)
				post_metadata.link_type = selected_row.link_type;
			if(selected_row.link_id)
				post_metadata.link_id = selected_row.link_id;
			if(self.config.tasks_class.mode == 'org_id')
				selected_row.org_id = self.config.tasks_class.id;
			$.post("/global/task?action=remove", post_metadata, function(x) {
				if(x.success) {
					$("#tasks-grid").jqxGrid('clearselection');
					select_Clear();
					$("#tasks-grid").jqxGrid('updatebounddata');
					cargohub.alert(x.response, "Success !", "thumb_up.png");
				} else
					cargohub.its.http_fail(null, "Global.Task::remove");
			})
				.fail(cargohub.its.http_fail);
		},
		/**
		 * Initialize the Tasks tab toolbar (Add, Edit, Delete tasks)
		 */
		init_Toolbar = function()
		{
			$("div.toolbar button")
				.jqxButton({ theme: cargohub.jqxtheme })
				/**
				 * Event: Toolbar button is clicked
				 */
				.on('click', function()
				{
					switch($(this).data('op')) {
						// Simple export function
						case 'exportxlsx':
							$("#tasks-grid").jqxGrid('exportdata', "xls", "grid-export", true, null, false, "/?action=export");
							break;
						case 'my-assigned':
//							let currentUser = $('#user-menu-bar ul nav li:eq(1) b').text();
//							let currentUser = $('#user-menu-bar ul nav li:eq(0) b').text();
							let currentUser = $('#cargo-user-btn').text().trim();
                                                        console.log(currentUser);
							// create a filter group for the FirstName column.
							var fnameFilterGroup = new $.jqx.filter();
							fnameFilterGroup.operator = 'or';
							// operator between the filters in the filter group. 1 is for OR. 0 is for AND.
							var filter_or_operator = 1;
							// create a string filter with 'contains' condition.
							var filtervalue = currentUser;
							var filtercondition = 'contains';
							var fnameFilter1 = fnameFilterGroup.createfilter('stringfilter', filtervalue, filtercondition);
							// create second filter.
							filtervalue = currentUser;
							filtercondition = 'starts_with';
							var fnameFilter2 = fnameFilterGroup.createfilter('stringfilter', filtervalue, filtercondition);
							// add the filters to the filter group.
							fnameFilterGroup.addfilter(filter_or_operator, fnameFilter1);
							fnameFilterGroup.addfilter(filter_or_operator, fnameFilter2);
							// add the filter group to the 'firstname' column in the Grid.
							$("#tasks-grid").jqxGrid('addfilter', 'assign_name', fnameFilterGroup);
							$("#tasks-grid").jqxGrid('applyfilters');
							window.history.pushState('', '', '?myassigned=true');
							break;
						case 'my-dept':
							window.location.href = '/manager/tasks:record?list=dep';
							break;
						case 'my-org':
							window.location.href = '/manager/tasks:record?list=org';
							break;
						case 'rediectToGrid':
							window.location.href = '/issue/list';
							break;
						case "task-create":
							open_TaskEditor('create');
							break;
						case "task-view":
							task_View();
							break;
						// Open the linked item (if it exists)
						case "task-linked":
							switch(selected_row.link_type) {
								case 'record':
//									document.location = "/record/process:"+ selected_row.rec_track+ "#tasks";//IWI All Task grid changes 16072026
                                                                        window.open("/record/process:" + selected_row.rec_track + "#tasks", "_blank"); //IWI All Task grid changes 16072026
									break;
							}
							break;
//                                                        IWI All Task grid changes 16072026 start
                                                case "task-complete":
                                                        updateTaskStatus();
                                                break;
//                                                        IWI All Task grid changes 16072026 end
						case "task-edit":
							open_TaskEditor('update');
							break;
						case "task-delete":
							if(!confirm("Are you sure you want to remove this task ?"))
								return;
							task_Delete();
							break;
						// Removes any sorting, filters and paging from the grid, bringing it to the default (sort by deadline ascending, not showing completed and failed statuses)
						case "grid-state-reset":
							if(!confirm("Are you sure you want to reset the grid ?"))
								return;
							$.post("?action=Save_Grid_State", {
								state: false
							}, function(x) {
								if(x.success) {
									// Reload the page after clearing the state
									window.location = window.location.pathname;
								} else
									cargohub.its.http_fail(null, "Task.Manager::save_grid_state");
							})
								.fail(cargohub.its.http_fail);
							break;
						case "grid-options":
							open_GridOptions();
							break;
					}
				});
			if(self.config.link_type == "all" && edit_privilege)
				$('div.toolbar #tasks-editops').fadeIn();
		},
		/**
		 * The grid's filter, sorting, page or page size is changed - Save the information in session so on next load the same initial conditions are kept.
		 */
		event_GridState = function(event)
		{
			switch(event.type) {
				// Filter event, it affects number of returned rows, so paging is also reset
				case 'filter':
					if(event.args.filters) {
						self.config.grid_state.filters = [];
						for(c=0;c<event.args.filters.length;c++) {
							self.config.grid_state.filters.push({
								column: event.args.filters[c].datafield,
								filters: event.args.filters[c].filter.getfilters()
							});
						}
					}
					self.config.grid_state.pageNum = 0;
					break;
				// Sort by a certain column. Doesn't affect the total returned rows, or the page number
				case 'sort':
					self.config.grid_state.sort = {
						column: event.args.sortinformation.sortcolumn
					};
					if(event.args.sortinformation.sortdirection.ascending)
						self.config.grid_state.sort.direction = 'asc';
					if(event.args.sortinformation.sortdirection.descending)
						self.config.grid_state.sort.direction = 'desc';
					break;
				// This changes the number of rows on a page. The grid also sends a "pagechanged" event after it, so state only needs to be saved with that event
				case 'pagesizechanged':
					return;
				// Page number is changed. Nothing else is affected, this is the lowest priority event
				case 'pagechanged':
					self.config.grid_state.pageNum = event.args.pagenum;
					self.config.grid_state.pageSize = event.args.pagesize;
					break;
			}
			// Only save the state to the server when choosing to preserve; However the browser keeps the state permanently synchronized for possibly use in the "Save View" function.
//		if(!self.config.preferences.preserve)
//			return;
			$.post("?action=Save_Grid_State", {
				state: JSON.stringify(self.config.grid_state)
			}, function(x) {
				if(x.success) {
					// Nothing is done here, this is just a state-update operation when the user is navigating the data. It should not bother him
				} else
					// Unless something went wrong...
					cargohub.its.http_fail(null, "Task.Manager::save_grid_state");
			})
				.fail(cargohub.its.http_fail);
		},
		/**
		 * Initialize the global Tasks Grid
		 */
		init_Grid = function()
		{
			// Grid-State initialization (should be coming from server, but just in case...)
			if(!self.config.grid_state)
				self.config.grid_state = {
					pageNum: 0,
					pageSize: 20,
					pageSizeOpts: [10, 20, 50, 100, 200],
					sort: null,
					filters: null
				};
			// Grid column definition (Admin has a few extra - organization name,
			var grid_columns = [
                            {datafield: 'status', text: "Status", width: 115, align: "center", cellsalign: "center",
                                filtertype: "checkedlist", 
                                filteritems: ["new", "assigned", "inprogress", "onhold", "completed", "failed", "expired", "closed"],
                                createfilterwidget: function (column, columnElement, widget) {
                                    widget.jqxDropDownList({
                                        dropDownWidth: 125,
                                        autoDropDownHeight: true,
                                        animationType: "fade"
                                    });
                                }},
				{datafield: 'task_type', text: "Task Type ", width: 155, filtercondition: "CONTAINS",                 // task_type feild added 
				filtertype: "checkedlist", filteritems: ["Custom","Finding", "Finding (minor)", "Finding (Major)", "Observation", "Observation (minor)", "Observation (Major)", "Improvement opportunity"],
					createfilterwidget: function (column, columnElement, widget) {
						widget.jqxDropDownList({
							dropDownWidth: 158,
							autoDropDownHeight: true,
							animationType: "fade"
						});
					}},
				{datafield: 'title', text: "Title ", width: 305, filtercondition: "CONTAINS", groupable: false},
				// {datafield: 'task_ref_link', text: "Task Ref ", width: 310, filtercondition: "CONTAINS", groupable: false},
				{datafield: 'assign_name', text: "Assigned to...", width: 130, filtercondition: "CONTAINS",
					cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
						if(value)
							if(tasks_Source.records[row].assign_mode == 'dep')
								return defaulthtml.replace('<div style="', '<div class="blue" style="');
							else
								return defaulthtml;
					}},
				// {datafield: 'creator_name', text: "Created by...", width: 150, filtercondition: "CONTAINS"},
				{datafield: 'dt', text: 'Created on', width: 100, minwidth: 100, maxwidth: 100, cellsformat: cargohub.date_format, align: "center", cellsalign: "center", filtertype: "range",
					createfilterwidget: function (column, columnElement, widget) {
						widget.jqxDateTimeInput({
							animationType: "fade"//,
//						dropDownHorizontalAlignment: "right"
						});
					}},
					{datafield: 'progress', text: "Progress", width: 80, minwidth: 80, maxwidth: 80, filtertype: "number", align: "center", cellsalign: "center",
					cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
						return defaulthtml.replace("</div>", "%</div>");
					}},
				{datafield: 'deadline', text: "Deadline", width: 100, minwidth: 100, maxwidth: 100, cellsformat: cargohub.date_format, align: "center", cellsalign: "center", filtertype: "range",
					createfilterwidget: function (column, columnElement, widget) {
						widget.jqxDateTimeInput({
							animationType: "fade"//,
//						dropDownHorizontalAlignment: "right"
						});
					}},
				// {dataField: 'tDate', text: "Completed Date",width: 120, cellsFormat: cargohub.date_format, align: "center", cellsAlign: "center", groupable: false,filterable:false,sortable: false },
			], grid_columns_index = {
//				"title": 1,
//				"status": 3,
//				"assign_name": 4,
//				"creator_name": 5,
//				"dt": 6,
//				"deadline": 7,
//				"progress": 8,
			}, cidx = 7, rt_list = [];
			// Add more grid columns when in different modes:
			switch(self.config.link_type) {
				case 'all':
					grid_columns.push({datafield: 'link_type', text: "Linked With", width: 115, align: "center", cellsalign: "center",
						filtertype: "checkedlist", filteritems: ["unlinked", "record"],
						createfilterwidget: function (column, columnElement, widget) {
							widget.jqxDropDownList({
								dropDownWidth: 105,
								autoDropDownHeight: true,
								animationType: "fade"
							});
						}
					});
					grid_columns_index['link_type'] = cidx; cidx++;
					break;
				case 'record':
					for(c=0; c< self.config.rt_source.length; c++)
						rt_list.push(self.config.rt_source[c].name);
					grid_columns.push({datafield: 'rt_name', text: "Record Type", width: 140,
						filtertype: "checkedlist", filteritems: rt_list,
						createfilterwidget: function (column, columnElement, widget) {
							widget.jqxDropDownList({
								renderer: function (index, label, value) {
									return "<div>"+ (index>0?"<img style='float: left; margin-right: 4px;' src='/img/icons16/ist/"+self.config.rt_source[index-1].key+".png' />" + value: value) + "</div>";
								},
								animationType: "fade"
							});
						},
						cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
							return "<div style='text-overflow: ellipsis; overflow: hidden; margin-top: 5px;' title='"+ value +"'><img src='/img/icons16/ist/" + tasks_Source.records[row].rt_key + ".png' style='margin: 0px 4px;' /><span style='position: absolute;'>" + value + "</span></div>";
						},
					});
					grid_columns_index['rt_name'] = cidx; cidx++;
					grid_columns.push({datafield: 'rec_track', text: "Record TrackID", width: 125, minwidth: 125, maxwidth: 125, align: "center", cellsalign: "center"});
					grid_columns_index['rec_track'] = cidx; cidx++;
					grid_columns.push({datafield: 'tDate', text: "Completed Date", width: 120, cellsFormat: cargohub.date_format, align: "center", cellsalign: "center",groupable: false,filterable:false,sortable: false});
					grid_columns_index['tDate'] = cidx; cidx++;
					grid_columns.push({datafield: 'group_name', text: "Record Group", width: 190, minwidth: 100, maxwidth: 250, align: "left", cellsalign: "left"});
					grid_columns_index['group_name'] = cidx; cidx++;
					// Cache fields
					grid_columns.push({datafield: 'rec_primary', text: "(Cache:Primary)", width: 200, align: "left", cellsalign: "left"});
					grid_columns_index['rec_primary'] = cidx; cidx++;
					//grid_columns.push({datafield: 'rec_secondary', text: "(Cache:Secondary)", width: 150, align: "left", cellsalign: "left"});
					//grid_columns_index['rec_secondary'] = cidx; cidx++;
					break;
			}
			// Initial filters are added to the "columns"
			if(self.config.grid_state.filters) {
				var filterdata = null, jqxfilter = null, fd = null, c2;
				for(c=0; c<self.config.grid_state.filters.length; c++) {
					filterdata = self.config.grid_state.filters[c];
					if(typeof grid_columns_index[filterdata.column] == 'undefined')
						continue; // Column not found, skip
					// Create the jqxFilter (may have multiple filters in it - checkboxes for example)
					jqxfilter = new $.jqx.filter();
					for(c2=0; c2< filterdata.filters.length; c2++) {
						fd = filterdata.filters[c2];
						jqxfilter.addfilter(fd.operator, jqxfilter.createfilter(fd.type, fd.value, fd.condition));
					}
					// ... and apply it to the proper column
					grid_columns[grid_columns_index[filterdata.column]].filter = jqxfilter;
				}
			}
			// Tasks class information is sent in the request (to filter the proper tasks)
			switch(self.config.tasks_class.mode) {
				// Loading tasks for a certain user, dep or org
				case 'user_id':
				case 'dep_id':
				case 'org_id':
					tasks_Source._source.data.class_filter = {
						mode: self.config.tasks_class.mode.slice(0,-3),
						id: self.config.tasks_class.id
					};
					break;
				// Loading tasks for the user himself, his active dep and his org (No ID present)
				case 'self':
				case 'dep':
				case 'org':
					tasks_Source._source.data.class_filter = {
						mode: self.config.tasks_class.mode
					};
			}
			// Check state for sorting, page number and size and prepare the DataAdapter before the grid is initialized
			if(self.config.grid_state.sort) {
				tasks_Source._source.sortcolumn = self.config.grid_state.sort.column;
				tasks_Source._source.sortdirection = self.config.grid_state.sort.direction;
			}
			tasks_Source._source.pagenum = self.config.grid_state.pageNum;
			// Finally, add the "link_type" to the request (necessary to read certain columns)
			tasks_Source._source.data.link_type = self.config.link_type;
			// ... and init the grid
			$("#tasks-grid").jqxGrid({
				theme: cargohub.jqxtheme,
				width: '100%', rowsheight: 27, autoheight: true, //IWI All Task grid changes 16072026
				source: tasks_Source,
				columns: grid_columns, columnsmenu: false, columnsresize: true,
//			altrows: true,
				filterable: true, showfilterrow: true, sortable: true,
				pageable: true,	pagesize: self.config.grid_state.pageSize, pagesizeoptions: self.config.grid_state.pageSizeOpts,
				virtualmode: true, // virtual state
				rendergridrows: function(params)
				{
					return params.data;
				},
//				selectionmode: "singlerow" //IWI All Task grid changes 16072026
                                selectionmode: "checkbox" //IWI All Task grid changes 16072026
			})
				// Events that change what data needs to be loaded - Save the current grid state in session (Is reset by loading a different issue type or changing columns)
				/**
				 * Event: Grid's active sort is changed by the user
				 */
				.on('sort', event_GridState)
				/**
				 * Event: Grid's active filters are changed by the user
				 */
				.on('filter', event_GridState)
				/**
				 * Event: Grid's page number is changed (Pager effect)
				 */
				.on('pagechanged', event_GridState)
				/**
				 * Event: Page size changed (Pager dropdown)
				 */
				.on('pagesizechanged', event_GridState)
				/**
				 * Event: A row is selected
				 */
				
//                                IWI All Task grid changes 16072026 start
//				.on('rowselect', select_Row)
                        
				.on('rowselect rowunselect', updateSelectedTasks)
//                        IWI All Task grid changes 16072026 end
				/**
				 * Event: A row in the grid is double-clicked (open or edit)
				 */
				.on('rowdoubleclick', edit_privilege? task_Edit: task_View) //IWI All Task grid changes 16072026 
				/**
				 * Event: When the grid is filtered, clear selection because the active row might not be visible anymore.
				 */
				.on('filter', select_Clear);
			setTimeout(init_Checker, 500);
			setTimeout(init_Double_Checker,500);
                        setTimeout(init_statusFilter,500); // completed,failed,closed should be excluded by default in task list by IWI
		},
		init_Checker = function()
		{
			var url = window.location.href;
			var array = url.split('/');
			var lastsegment = array[array.length-1];
			if(lastsegment.indexOf('?myassigned=true') !== -1){
				setTimeout(init_All_Reset,300);
			}
			if(lastsegment.indexOf('?list=org') !== -1 || lastsegment.indexOf('?list=dep') !== -1 ){
				setTimeout(init_AllF_Reset,300);
			}
		},
		init_Double_Checker = function(){
			let currentUser = $('#user-menu-bar ul nav li:eq(1) b').text();
//                        let currentUser = $('#user-menu-bar ul nav li:eq(0) b').text();
			const urlParams = new URLSearchParams(window.location.search);
			if(urlParams.get('dep') !== null){
				// create a filter group for the FirstName column.
				var fnameFilterGroup = new $.jqx.filter();
				fnameFilterGroup.operator = 'AND';
				// operator between the filters in the filter group. 1 is for OR. 0 is for AND.
				var filter_and_operator = 0;
				// create a string filter with 'contains' condition.
				var filtervalue = currentUser;
				var filtercondition = 'contains';
				var fnameFilter1 = fnameFilterGroup.createfilter('stringfilter', filtervalue, filtercondition);
				// create second filter.
				filtervalue = urlParams.get('dep').replace(/_/g, ' ');
				filtercondition = 'contains';
				var fnameFilter2 = fnameFilterGroup.createfilter('stringfilter', filtervalue, filtercondition);
				// add the filters to the filter group.
				fnameFilterGroup.addfilter(filter_and_operator, fnameFilter1);
				fnameFilterGroup.addfilter(filter_and_operator, fnameFilter2);
				// add the filter group to the 'firstname' column in the Grid.
				$("#tasks-grid").jqxGrid('addfilter', 'assign_name', fnameFilterGroup);
				$("#tasks-grid").jqxGrid('addfilter', 'rt_name', fnameFilterGroup);
				$("#tasks-grid").jqxGrid('applyfilters');
				if(urlParams.get('overdue') === "true"){
					setTimeout(init_DateChecker,300);
				}else{
					setTimeout(init_Deadline_Reset,300);
				}
			}
		},
		init_DateChecker = function(){
			var fnameFilterGroup = new $.jqx.filter();
			fnameFilterGroup.operator = 'AND';
			// operator between the filters in the filter group. 1 is for OR. 0 is for AND.
			var filter_and_operator = 0;
			// create a string filter with 'contains' condition.
			// create filter.
			var filtervalue = new Date();
			// filtervalue = yy+'-'+mm+'-'+dd+'  00:00:00';
			var filtercondition = 'LESS_THAN';
			var fnameFilter3 = fnameFilterGroup.createfilter('datefilter', filtervalue, filtercondition);
			// add the filters to the filter group.
			fnameFilterGroup.addfilter(filter_and_operator, fnameFilter3);
			// add the filter group to the 'firstname' column in the Grid.
			$("#tasks-grid").jqxGrid('addfilter', 'deadline', fnameFilterGroup);
			$("#tasks-grid").jqxGrid('applyfilters');
		},
		init_Deadline_Reset = function(){
			$('#tasks-grid').jqxGrid('removefilter', 'deadline');
		},
		init_All_Reset = function(){
			$('#tasks-grid').jqxGrid('removefilter', 'deadline');
			$('#tasks-grid').jqxGrid('removefilter', 'rt_name');
			$('#my-assigned').trigger('click');
		},
		init_AllF_Reset = function(){
			$('#tasks-grid').jqxGrid('removefilter', 'assign_name');
			$('#tasks-grid').jqxGrid('removefilter', 'deadline');
			$('#tasks-grid').jqxGrid('removefilter', 'rt_name');
		}
                // completed,failed,closed should be excluded by default in task list by IWI start
                init_statusFilter= function(){
                    $('#tasks-grid').jqxGrid('removefilter', 'status');
			var filtergroup = new $.jqx.filter();
			var filter_or_operator = 1;
		
			var filtervalue = 'new';
			var filtercondition = 'equal';
			var filter_new = filtergroup.createfilter('stringfilter', filtervalue, filtercondition);
		
			filtervalue = 'assigned';
			filtercondition = 'equal';
			var filter_assigned = filtergroup.createfilter('stringfilter', filtervalue, filtercondition);
		
			filtervalue = 'inprogress';
			filtercondition = 'equal';
			var filter_inprogress = filtergroup.createfilter('stringfilter', filtervalue, filtercondition);
		
			filtervalue = 'onhold';
			filtercondition = 'equal';
			var filter_onhold = filtergroup.createfilter('stringfilter', filtervalue, filtercondition);
		
			filtervalue = 'expired';
			filtercondition = 'equal';
			var filter_expired = filtergroup.createfilter('stringfilter', filtervalue, filtercondition);
		
			filtergroup.addfilter(filter_or_operator, filter_new);
			filtergroup.addfilter(filter_or_operator, filter_assigned);
			filtergroup.addfilter(filter_or_operator, filter_inprogress);
			filtergroup.addfilter(filter_or_operator, filter_onhold);
			filtergroup.addfilter(filter_or_operator, filter_onhold);
			filtergroup.addfilter(filter_or_operator, filter_expired);
		
			// Add the filters to the grid
			$("#tasks-grid").jqxGrid('addfilter', 'status', filtergroup);
			// Apply the filters
			$("#tasks-grid").jqxGrid('applyfilters');
		}
                // completed,failed,closed should be excluded by default in task list by IWI end
	;
	this._public({
		/**
		 * Setup Tasks Tab for Record Process module
		 */
		init: function()
		{
			edit_privilege = cargohub.application.user_Test_Privileges('tasks_manager');
			init_Toolbar();
			init_Grid();
			// Info Explander
			$(".info-expander").jqxExpander({
				theme: cargohub.jqxtheme,
				expanded: false
			});
		}
	});
});