/**
 * Module "Organization Manager", tab "Templates"
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2019.09.23
 */
cargohub.organization_templates = cargohub.baseclass._instance(cargohub.baseclass.fullgrid, function() {
	// private
	var self = this,
		c = null,
		master = cargohub.organization_manager,

		selected_row = null,		// The data from the selected row (used by the editor)
		selected_row_open = false,	// The selected row is open in the editor (save/close buttons should be on)
		content_editor = null,		// Indicates if and which editor is loaded
		content_editor_loaded = false,

		// Constants

		// Source for the template roles (The ones not yet fully functional are disabled
		TEMPLATE_ROLES = [
			{k: 'email', text: "Compose an E-mail based on the data", short_text: "E-mail", icon: "email"},
			{k: 'print', text: "Document creation template", short_text: "Document", icon: "page"},
			{k: 'task', text: "Define task linked to a Primary Record", short_text: "Define task", icon: "to_do_list_checked_1"},
			{k: 'view', text: "Document &quot;Webview&quot; template", short_text: "Webview", icon: "web_layout"},
			{k: 'arem', text: "Automatic Response E-mail", short_text: "Auto-Reply", icon: "email_send_receive"},
			{k: 'ntf', text: "Notification E-mail", short_text: "Notification", icon: "bell"}
//		{k: 'export', text: "Download the data as a computer file", short_text: "Export format", icon: "database"},
		],
		TEMPLATE_ROLES_INDEX = {
			'email': 0,
			'print': 1,
			'task': 2,
			'view': 3,
			'arem': 4,
			'ntf': 5,
			'export': 6 // unused
		},
		// Types of events that can trigger an automatic e-mail reponse from the system (Record received, TODO: Record updated by staff to a certain status, Record close)
		ROLE_AREM_EVENTS = [
			{'key': "recv", 'text': "New record received"},
			{'key': "inactive", 'text': "- disable response -"}
		],
		OVERDUE_OPTIONS = [
			{value: "1", text: "Every day after due date"},
			{value: "3", text: "Every 3 days after due date"},
			{value: "7", text: "Every week after due date"},
			{value: "30", text: "Every 30 days"},
			{value: "0", text: "<span class='gray'>Disabled (no extra notifications)</span>"}
		],
                //        when template will create , there will be a option to check , for auto selected this template for those record which have this issue type, claim type etc 03-01-2023  start -->
                CLAIM_OPTIONS = [
                {value: "Preliminary", text: "Preliminary"},
                {value: "Formal", text: "Formal"},
            ],
                                                            
                ASSIGNED_USER_OPTIONS = [
                   {value: "Yes", text: "Yes"},
                   {value: "No", text: "No"},
               ],
                //       when template will create , there will be a option to check , for auto selected this template for those record which have this issue type, claim type etc 03-01-2023  end -->
		// Source for the template placeholders (depend on record type) - Reloaded when opening grid
		placeholders_source = new $.jqx.dataAdapter({
			url: "?action=Get_Canned_Placeholders",
			data: {},
			datatype: "json",
			datafields: [
				{name: 'n', type: 'string'},
				{name: 'group', type: 'string'},
				{name: 'ph', type: 'string'}
			]
		}),

		// Template area (expander) vars
		$tpl_editor = null,

		/**
		 * Setup the window used to choose which editor is being used for editing the template
		 * -- The choice is saved in the session until the user logs out.
		 */
		setup_Options = function($window)
		{
			// The RadioButtons for choosing an editor
			$(".texted_option", $window).each(function()
			{
				var $option = $(this), is_active = self.config.texted == $option.data('value');
				$option.jqxRadioButton({
					theme: cargohub.jqxtheme, height: 22,
					groupName: "texted_option",
					checked: is_active
				});
			});

			// Setup the action for "OK" button
			$('#modal_tpl-editor-options_ok', $window).off('click').on('click', function()
			{
				// submit the add/edit form using ajax
				$('.modal_content > form', $window).ajaxSubmit({
					dataType: 'json',
					beforeSubmit: function(formData, jqForm, options)
					{
						// Grab the value of the radiobuttons and submit
						var texted_option = cargohub.application.jqxRadioButtons_Get_Checked_Value($('.modal_content > form', $window));

						if(!texted_option) {
							cargohub.alert("Please select which editor will be used.");
							return false;
						}

						formData.push({
							name: "texted",
							value: texted_option
						});
						return true;
					},
					success: function(x) {
						if(x.success)
							document.location.reload();
						else
							cargohub.its.http_fail(null, "Manager.Organization::TPL_Editor_Options");
					},
					error: cargohub.its.http_fail
				});
			});
		},

		/**
		 * Opens the window used to locate and insert fields
		 * TODO: This one needs a good search system / convert to tree
		 */
		open_InsertField = function(rt_id, insert_callback, title)
		{
			cargohub.components.modal.create('tpl-editor-insert-field', {
				btn_roles: {cancel: "cancel"}
			});

			cargohub.components.modal.open('tpl-editor-insert-field', {
				width: 370, height: 580,
				title: title? title: "Insert field into template",
				content: $('#window-insert_field').html(),
				callback: function($window) {
					// The listbox/tree is initialized based on the selected rt_id
					placeholders_source._source.data.rt_id = rt_id;
					$("#insert_field-listbox", $window).jqxListBox({
						theme: cargohub.jqxtheme,
						source: placeholders_source,
						displayMember: "n", valueMember: "ph",
						width: 358, height: 462,
						filterable: true,
						searchMode: 'containsignorecase',
						renderer: function(index, label, value) {
							if(value)
								return '&nbsp;&raquo;&nbsp'+ label;
							else
								return label;
						}
					})
						.on('dblclick', function() {
							$('#modal_tpl-editor-insert-field_ok', $window).trigger('click');
						});

					// Setup the action for "OK" button
					$('#modal_tpl-editor-insert-field_ok', $window).off('click').on('click', function()
					{
						// Read the list, insert the field into the editor at cursor
						insert_callback($("#insert_field-listbox", $window).jqxListBox('getSelectedItem').value);
						$window.jqxWindow('close');
					});

				}
			}, self);
		},

		/**
		 * Initialize the "organization templates" sub-module toolbar
		 */
		init_Toolbars = function()
		{
			var tpl_data = {_itc: cargohub.input_theme_classes};

			$("#tab_templates .toolbar button")
				.jqxButton({ theme: cargohub.jqxtheme })
				/**
				 * Event: Toolbar button is clicked
				 */
				.on('click', function(event)
				{
					switch($(this).data('op')) {
						// Add a new template
						case 'tpl-add':
							// If anything selected, unselect it before initializing the editor - But do not close the editor (if already open)
							select_Clear(event, true);

							copy_mode = false;
							init_Editor({
								title: "Create a template",
								icon: "add.png",
								mode: "create"
							});
							break;
						// Open the editor on the selected template
						case 'tpl-edit':
							copy_mode = false;
							init_Editor({
								title: "Edit template: "+ selected_row.description,
								icon: "pencil.png",
								mode: "edit"
							});
							break;

						case 'tpl-copy':
							copy_mode = true; // ID is not transmitted, so a new template is created instead
							init_Editor({
								title: "Create duplicate template: "+ selected_row.description,
								icon: "page_copy.png",
								mode: "edit",
								overrides: {
									"description": selected_row.description + " (copy)"
								}
							});
							break;

						// Save existing editor content
						// TODO: Warn if no changes have been made
						case 'tpl-save':
							save_Editor();
							break;

						// Simply closes the editor without saving
						// TODO: Check if changes have been made
						case 'tpl-close':
							close_Editor();
							break;

						// Delete selected template after confirming
						case 'tpl-del':
							if(!confirm("Are you sure you want to delete "+selected_row.description))
								return;

							$.post("?action=delete_template", {
								id: selected_row.id
							}, function(x) {
								if(x.success) {
									// TODO: Change to select/unselect functions to remove this redundancy
									$("#tab_templates #templates-grid").jqxGrid('clearselection');
									selected_row = null;
									$('#templates-row-ops button').jqxButton({disabled: true});

									donrey.single_Delay('hide_bar_templates-row-ops', function() {
										$('#templates-row-ops').fadeOut();
									}, 500);

									$("#tab_templates #templates-grid").jqxGrid('updatebounddata', 'cells');
									cargohub.alert(x.response, "Success !", "thumb_up.png");
								} else
									cargohub.its.http_fail(null, "Manager.Organization::delete_template");
							})
								.fail(cargohub.its.http_fail);
							break;

						// Removes any sorting, filters and paging from the grid, bringing it to the default (sort by deadline ascending, not showing completed and failed statuses)
						case "grid-state-reset":
							if(!confirm("Are you sure you want to reset the grid ?"))
								return;

							$.post("/global/operations?action=Save_Grid_State", {
								grid_id: "org_templates",
								state: false
							}, function(x) {
								if(x.success)
									// Reload the page after clearing the state
									document.location.reload();
								else
									cargohub.its.http_fail(null, "Global.Operations::save_grid_state");
							})
								.fail(cargohub.its.http_fail);
							break;

						// Options command, allows switching editor
						case 'tpl-options':
							cargohub.components.modal.create('tpl-editor-options', {
								btn_roles: {cancel: "cancel"}
							});

							cargohub.components.modal.open('tpl-editor-options', {
								width: 520, height: 200,
								title: "Choose the Template Editor",
								content: $('#window-tpl_editor_options').html().present(tpl_data),
								callback: setup_Options
							}, self);
							break;

						// Bottom toolbar

						case 'tpl-insert-field':
							open_InsertField($('#tpl-rt_id', $tpl_editor).val(), function(field_content) {
								content_editor.content_InsertAtCursor(field_content);
							});
							break;
					}
				});
		},


		/**
		 * A record row has been selected - Store information in preparation for a double-click ( for Previewing or Opening ), or for clicking on one of the toolbar buttons
		 */
		select_Row = function(event)
		{
			var operations_bar = "#templates-row-ops";

			if(!selected_row) {
				donrey.cancel_Delay('hide-rowops_bar');
				$(operations_bar+ ' button').jqxButton({disabled: false});
				$(operations_bar).fadeIn();
			}

			if(event.args.row)
				selected_row = event.args.row;
		},

		/**
		 * No rows are currently selected - Make the toolbar buttons dissapear
		 */
		select_Clear = function(event, dont_close_editor)
		{
			var operations_bar = "#templates-row-ops";

			selected_row = null;
			$(operations_bar+ ' button').jqxButton({disabled: true});
			donrey.single_Delay('hide-rowops_bar', function() {
				$(operations_bar).fadeOut();
			}, 500);

			$("#tab_templates #templates-grid").jqxGrid('clearselection');
			if(!dont_close_editor)
				close_Editor();
		},

		/**
		 * Setup and load the grid used to display the templates
		 */
		init_Grid = function()
		{
			// Initialize the templates grid
			var templates_Source = self.source({
				url: '?action=Get_Templates',
				data: {
					process: false	// This indicates the templates should be read exactly as they are (and return various other metadata) - Used in the Templates Tab (from Organization Manager)
				},
				datatype: "json",
				datafields: [
					{name: 'id', type: 'int'},
					{name: 'role', type: 'string'},
					{name: 'locator_key', type: 'string'},
					{name: 'format', type: 'string'},
					{name: 'rt_id', map: 'rt>id', type: 'int'},
					{name: 'rt_name', map: 'rt>name', type: 'string'},
					{name: 'rt_key', map: 'rt>key', type: 'string'},
					{name: 'title', type: 'string'},
					{name: 'description', type: 'string'},
					{name: 'editor', map: 'editor>name'},
					{name: 'xdata', type: 'string'}
				],
				id: 'id'
			}), rt_list = [""], role_list = [];

			// Setup columns (fixed in this module)
			self.column({
				datafield: 'role', text: "Role", width: 115, cellsalign: 'center',
				filtertype: "checkedlist", filteritems: role_list,
				createfilterwidget: function (column, columnElement, widget) {
					widget.jqxDropDownList({
						dropDownWidth: 125,
						renderer: function (index, label, value) {
							return "<div>"+ (index>0?"<img style='float: left; margin-right: 4px;' src='/img/icons16/"+TEMPLATE_ROLES[index-1].icon+".png' />" + TEMPLATE_ROLES[index-1].short_text: value) + "</div>";
						},
						animationType: "fade"
					});
				},
				cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
					return "<div style='text-overflow: ellipsis; overflow: hidden; margin-top: 5px;' title='"+ value +"'><img src='/img/icons16/" + TEMPLATE_ROLES[TEMPLATE_ROLES_INDEX[value]].icon + ".png' style='margin: 0px 4px;' /><span style='position: absolute;'>" + TEMPLATE_ROLES[TEMPLATE_ROLES_INDEX[value]].short_text + "</span></div>";
				},
			});

			self.column({
				datafield: 'rt_name', text: "Module", width: 240,
				filtertype: "checkedlist", filteritems: rt_list,
				createfilterwidget: function (column, columnElement, widget) {
					widget.jqxDropDownList({
						renderer: function (index, label, value) {
							if(index == 1)
								return "<div>(Not related to modules)</div>";
							else
								return "<div>"+ (index>1?"<img style='float: left; margin-right: 4px;' src='/img/icons16/ist/"+master.config.ist_source[index-2].key+".png' />" + value: value) + "</div>";
						},
						animationType: "fade"
					});
				},
				cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
					if(value)
						return "<div style='text-overflow: ellipsis; overflow: hidden; margin-top: 5px;' title='"+ value +"'><img src='/img/icons16/ist/" + templates_Source.records[row].rt_key + ".png' style='margin: 0px 4px;' /><span style='position: absolute;'>" + value + "</span></div>";
					else
						return "<div style='margin-top: 5px; margin-left: 20px;' class='gray'>(Not related to modules)</div>";
				},
			});
			self.column({
				datafield: 'description', text: "Description (What is the purpose of this template?)"
			});
			self.column({
				datafield: 'editor', text: "Last edited by", width: 180, filtertype: "checkedlist"
			});

			// Dropdown column: Role
			for(c=0; c< TEMPLATE_ROLES.length; c++)
				role_list.push(TEMPLATE_ROLES[c].k);

			// Dropdown column: Record Type
			for(c=0; c< master.config.ist_source.length; c++)
				rt_list.push(master.config.ist_source[c].name);

			// Init the grid as persistent in session
			self.init_Grid($("#tab_templates #templates-grid"), {
				pageable: true, pagesizeoptions: [10, 25, 50, 100], pagesize: 25,
				altrows: false
			}, {
				persistent: true,
				grid_id: 'org_templates'
			})
				/**
				 * Event: When a row is selected, the editing commands appear
				 */
				.on('rowselect', select_Row)
				/**
				 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
				 */
				.on('filter', select_Clear)
				/**
				 * Event: On doubleclicking, do the same as simple clicking then using the Edit button
				 */
				.on('rowdoubleclick', function() {
					$('#tab_templates .toolbar button[data-op="tpl-edit"]').trigger("click");
				});
		},

		copy_mode = false,

		/**
		 * Saves the content of the template editor (all fields)
		 */
		save_Editor = function()
		{
			// submit the add/edit form using ajax
			$($tpl_editor).find('form').ajaxSubmit({
				dataType: 'json',
				data: {
					id: (selected_row && !copy_mode)? selected_row.id: null,
					"tpl[body]": content_editor.content_Read()
				},
				beforeSubmit: function(formData, jqForm, options)
				{
					var tpl_role = $('#tpl-role', $tpl_editor).val();

					if(!tpl_role)
						return cargohub.alert("Please select template's role");

					// Verify some selectors and values before submitting
					if(!(tpl_role == 'view' || tpl_role == 'ntf') && !$('#tpl-rt_id', $tpl_editor).val())
						return cargohub.alert("Please select the Module relevant to this Template");

					// Description must not be empty or shorter than 5 characters (it is used in selectors)
					var tpl_desc = $("input#tpl-description", $tpl_editor).val();
					if(tpl_desc.length < 5)
						return cargohub.alert("The template's custom Description is too short, minimum 5 characters required");

					// Check if Title is too long ( over 255 characters ) - Because it uses templates, hard-cutting it is not an option
					if($("input#tpl-title", $tpl_editor).val().length > 255)
						return cargohub.alert("The Title is too long and cannot be saved correctly in the database. Please shorten it !");

					return true;
				},
				success: function(x) {
					select_Clear();
					$("#tab_templates #templates-grid").jqxGrid('updatebounddata', 'cells');
					cargohub.alert(x.response, "Success !", "thumb_up.png");
				},
				error: cargohub.its.http_fail
			});
		},

		/**
		 * Minimize and close the editor if it was open.
		 */
		close_Editor = function()
		{
			// Minimize and close the editor
			if(selected_row_open) {
				if($tpl_editor.jqxExpander('expanded')) {
					$tpl_editor.on('collapsed', function() {
						// When finishing collapse... hide the editor
						$tpl_editor.hide();
						// ... and disable the event
						$tpl_editor.off('collapsed');
					});
					$tpl_editor.jqxExpander('collapse');
				} else {
					// If already collapsed, just hide it
					$tpl_editor.hide();
				}

				// Also fadeout the Save/Close buttons
				$('#templates-editor-ops button').jqxButton({disabled: true});
				$('#templates-editor-ops').fadeOut();

				selected_row_open = false;
			}
		},

		/**
		 * Loads selected template "body" into the existing editor. If currently no body, just use the editor with a blank result
		 */
		load_Body = function()
		{
			$.get('?action=load_template_body', {
				id: selected_row.uid
			}, function(body) {
				if(body.length > 0)
					content_editor.content_Write(body);
				else
					content_editor.content_Write("\n");
			});
		},

		// Temporary store location of selected row, only changed when a row is opened for editing
		row_data = null,
		// Extra variables for the xdata controls
		contacts_loaded = false,

		/**
		 * Setup the template editor expander, placed below the grid.
		 * (This replaces the editing window, which is inappropriate to be used along TinyMCE and other large text editors
		 *
		 * @param config Information now how the editor area is initialized
		 */
		init_Editor = function(config)
		{
			var
				task_deadline_control = null,
				/**
				 * XData related - Selects the autoresponder contact automatically for the current row_data
				 */
				autoselect_contact = function($jqx_ddl_control, sender_contact_idx) {
					// Auto-Assign selector
					if(row_data && row_data.xdata && sender_contact_idx && row_data.xdata_parsed[sender_contact_idx]) {
						var items = $jqx_ddl_control.jqxDropDownList('getItems');
						for(c=0; c< items.length; c++)
							if(items[c].value == row_data.xdata_parsed[sender_contact_idx])
								$jqx_ddl_control.jqxDropDownList('selectIndex', c);
					}
				},
				current_active_role = null
			;

			// The RT selector is not mandatory for all types, so begin by hiding it - Will show when a role that uses it is selected
			$("#tpl-edit-rt_select_ctrl", $tpl_editor).hide();

			if($tpl_editor) {
				// If already initialized just change jqxExpander according to config
				if(config && config.expander)
					$tpl_editor.jqxExpander(config.expander);
			} else {
				// Otherwise, start full initialization
				$tpl_editor = $("#tab_templates #template-editor");
				if(!config.expander)
					config.expander = {
						disabled: false,
						expanded: false
					};
				config.expander.theme = cargohub.jqxtheme;
				$tpl_editor.jqxExpander(config.expander);

				// Since the HTML inside the expander is permanent, initialize widgets

				// Role selector
				$('#tpl-role', $tpl_editor).jqxDropDownList({
					theme: cargohub.jqxtheme,
					source: TEMPLATE_ROLES,
					displayMember: "text", valueMember: "k",
					width: 370, height: 22, autoDropDownHeight: true,
					renderer: function (index, label, value) {
						return "<div><img style='float: left; margin-right: 4px;' src='/img/icons16/"+TEMPLATE_ROLES[index].icon+".png' />"+ label+ "</div>";
					},
					selectionRenderer: function (originalRender) {
						var item = $('#tpl-role', $tpl_editor).jqxDropDownList('getSelectedItem');
						if (item)
							return "<div style='margin-top: 3px;'><img style='float: left; margin: 0 4px 0 2px;' src='/img/icons16/"+TEMPLATE_ROLES[item.index].icon+".png' />"+ item.label+ "</div>";
						return "<div style='margin-top: 4px;'>(Please choose)</div>";
					}
				})
					/**
					 * Event: A role is selected - Depending on the role, a special "xdata" row is being shown (or hidden) below Title
					 */
					.on('select', function(e) {
						current_active_role = e.args.item.value;

						switch(current_active_role) {
							// Extra controls related to task templates
							case 'task':
								$("#tpl-edit-rt_select_ctrl", $tpl_editor).show();

								$("#tpl-xdata", $tpl_editor).html($("#template-role_task").html().present(row_data)).show().css('height', '190px');
								// Add the deadline links
								$("#tpl-xdata", $tpl_editor).find('span.deadline_days-link').on('click', function() {
									var days = $(this).data('days');
									$('#xdata-deadline_days_slider', $tpl_editor).jqxSlider('setValue', days);
									$('#xdata-deadline_days', $tpl_editor).jqxNumberInput('setDecimal', days);
									task_deadline_control = null;
								});

								// The completion box and range slider (connected)
								$('#xdata-deadline_days', $tpl_editor).jqxNumberInput({
									theme: cargohub.jqxtheme,
									width: 35, height: 21,
									inputMode: 'simple',
									min: 1, max: 750, decimalDigits: 0,
									decimal: $('#xdata-deadline_days', $tpl_editor).data('value')
								}).on('valueChanged', function(ev) {
									if(task_deadline_control == 'slider') {
										task_deadline_control = null;
										return;
									}
									$('#xdata-deadline_days_slider', $tpl_editor).jqxSlider('setValue', ev.args.value);
									task_deadline_control = 'input';
								}).on('change', function() {
									task_deadline_control = null;
								});

								$('#xdata-deadline_days_slider', $tpl_editor).jqxSlider({
									theme: cargohub.jqxtheme,
									width: 375, height: 25,
									mode: 'fixed',
									min: 1, max: 750,
									value: $('#xdata-deadline_days_slider', $tpl_editor).data('value'),
									showButtons: false,
									ticksFrequency: 30, ticksPosition: 'bottom'
								}).on('change', function(ev) {
									if(task_deadline_control == 'input') {
										task_deadline_control = null;
										return;
									}
									$('#xdata-deadline_days', $tpl_editor).jqxNumberInput('setDecimal', ev.args.value);
									task_deadline_control = 'slider';
								}).on('blur', function() {
									task_deadline_control = null;
								});

								// Automatic assign dropdown, shows list of departments for the organization (Reads ID, Name).
								// -- When selected, when the task is defined, the assignment type "Department" is automatically chosen, and the department is selected.
								// -- Of course, another option can be manually chosen by the operator, but this provides a quick selection
								$('#xdata-auto_assign', $tpl_editor).jqxDropDownList({
									theme: cargohub.jqxtheme,
									source: master.source_Deps(),
									displayMember: "name", valueMember: "id",
									width: 370, height: 22, autoDropDownHeight: true,
									placeHolder: "[Optional] Select department for Auto-Assigning:",
								})
									.on('bindingComplete', function() {
										if(config.mode == 'edit') {
											// Auto-Assign selector
											if(row_data && row_data.xdata && row_data.xdata_parsed.auto_assign) {
												var items = $(this).jqxDropDownList('getItems');
												for(c=0; c< items.length; c++)
													if(items[c].value == row_data.xdata_parsed.auto_assign)
														$('#xdata-auto_assign', $tpl_editor).jqxDropDownList('selectIndex', c);
											}
										}
									});
								$('#xdata-assign_clear', $tpl_editor).jqxButton({
									theme: cargohub.jqxtheme,
								}).on('click', function() {
									$('#xdata-auto_assign', $tpl_editor).jqxDropDownList('clearSelection');
								});

								$('#xdata-ovd_intv', $tpl_editor).jqxDropDownList({
									theme: cargohub.jqxtheme,
									source: OVERDUE_OPTIONS,
									displayMember: "text", valueMember: "value",
									width: 370, height: 22, autoDropDownHeight: true,
									placeHolder: "[Optional] Choose interval for overdue notifications:",
								})
									.on('bindingComplete', function() {
										if(config.mode == 'edit') {
											// Auto-Assign selector
											if(row_data && row_data.xdata && row_data.xdata_parsed.ovd_intv) {
												var items = $(this).jqxDropDownList('getItems');
												for(c=0; c< items.length; c++)
													if(items[c].value == row_data.xdata_parsed.ovd_intv)
														$('#xdata-ovd_intv', $tpl_editor).jqxDropDownList('selectIndex', c);
											}
										}
									});
                                                                        
                                                                        
                                          //when template will create , there will be a option to check , for auto selected this template for those record which have this issue type, claim type etc 03-01-2023  start                              
                                        $('#xdata-claim_id', $tpl_editor).jqxDropDownList({
						theme: cargohub.jqxtheme,
						source: CLAIM_OPTIONS,
						displayMember: "text", valueMember: "value",
						width: 370, height: 22, autoDropDownHeight: true,
						placeHolder: "[Optional] Autoselect if claim type :",
					})
					.on('bindingComplete', function() {
						if(config.mode == 'edit') {
							// Auto-Assign selector
							if(row_data && row_data.xdata && row_data.xdata_parsed.claim_id) {
								var items = $(this).jqxDropDownList('getItems');
								for(c=0; c< items.length; c++)
									if(items[c].value == row_data.xdata_parsed.claim_id)
										$('#xdata-claim_id', $tpl_editor).jqxDropDownList('selectIndex', c);
							}
						}
					});
                                        $('#xdata-claim_id_clear', $tpl_editor).jqxButton({
						theme: cargohub.jqxtheme,
					}).on('click', function() {
						$('#xdata-claim_id', $tpl_editor).jqxDropDownList('clearSelection');
					});
                                        
//                                      
                                        
                                        $('#xdata-assigned_user', $tpl_editor).jqxDropDownList({
						theme: cargohub.jqxtheme,
						source: ASSIGNED_USER_OPTIONS,
						displayMember: "text", valueMember: "value",
						width: 370, height: 22, autoDropDownHeight: true,
						placeHolder: "[Optional] Autoselect if assigned to a user:",
					})
					.on('bindingComplete', function() {
						if(config.mode == 'edit') {
							// Auto-Assign selector
							if(row_data && row_data.xdata && row_data.xdata_parsed.assigned_user) {
								var items = $(this).jqxDropDownList('getItems');
								for(c=0; c< items.length; c++)
									if(items[c].value == row_data.xdata_parsed.assigned_user)
										$('#xdata-assigned_user', $tpl_editor).jqxDropDownList('selectIndex', c);
							}
						}
					});
					$('#xdata-assigned_user_clear', $tpl_editor).jqxButton({
						theme: cargohub.jqxtheme,
					}).on('click', function() {
						$('#xdata-assigned_user', $tpl_editor).jqxDropDownList('clearSelection');
					});
                                        
                                        //when template will create , there will be a option to check , for auto selected this template for those record which have this issue type, claim type etc 03-01-2023  end
                                                                        

								break;
							// Extra controls related to automatic mail-response templates
							case 'arem':
								$("#tpl-edit-rt_select_ctrl", $tpl_editor).show();
								$("#tpl-xdata", $tpl_editor).html($("#template-role_arem").html().present(row_data)).show().css('height', '56px');

								var $arem_contact = $('#xdata-arem_contact', $tpl_editor);

								// Triggering event dropdown - Fixed choices, for now - Later it will be patched into the events and notifications system
								$('#xdata-arem_event', $tpl_editor).jqxDropDownList({
									theme: cargohub.jqxtheme,
									source: ROLE_AREM_EVENTS,
									displayMember: "text", valueMember: "key",
									width: 200, height: 22, autoDropDownHeight: true,
									placeHolder: "Choose event:",
								});
								if(row_data && row_data.xdata && row_data.xdata_parsed.arem_event) {
									var items = $('#xdata-arem_event', $tpl_editor).jqxDropDownList('getItems');
									for(c=0; c< items.length; c++)
										if(items[c].value == row_data.xdata_parsed.arem_event)
											$('#xdata-arem_event', $tpl_editor).jqxDropDownList('selectIndex', c);
								}

								// Organization contact used as the "Sender" of automatic responses (can be managed through the address book)
								$arem_contact.jqxDropDownList({
									theme: cargohub.jqxtheme,
									source: master.source_SelfCtx(),
									displayMember: "name", valueMember: "id",
									width: 370, height: 22, autoDropDownHeight: true,
									placeHolder: "Select sender contact for this Auto-Response:",
								})
									.on('bindingComplete', function() {
										contacts_loaded = true;
										if(config.mode == 'edit')
											autoselect_contact($arem_contact, "arem_contact");
									});
								// Alternative autoselect for when the contacts are already loaded
								if(config.mode == 'edit' && contacts_loaded)
									autoselect_contact($arem_contact, "arem_contact");

								break;

							// Notification E-mail Template - This type doesn't use the record type, but has some extra controls like 'arem'
							case 'ntf':
								$("#tpl-edit-rt_select_ctrl", $tpl_editor).hide();
								$("#tpl-xdata", $tpl_editor).html($("#template-role_ntf").html().present(row_data)).show().css('height', '56px');

								var $ntf_contact = $('#xdata-ntf_contact', $tpl_editor);

								// Organization contact used as the "Sender" of notifications (can be managed through the address book)
								$ntf_contact.jqxDropDownList({
									theme: cargohub.jqxtheme,
									source: master.source_SelfCtx(),
									displayMember: "name", valueMember: "id",
									width: 370, height: 22, autoDropDownHeight: true,
									placeHolder: "Select sender contact for this Notification type:",
								})
									.on('bindingComplete', function() {
										contacts_loaded = true;
										if(config.mode == 'edit')
											autoselect_contact($ntf_contact, "ntf_contact");
									});
								// Alternative autoselect for when the contacts are already loaded
								if(config.mode == 'edit' && contacts_loaded)
									autoselect_contact($ntf_contact, "ntf_contact");

								$('#xdata-ntf_contact_clear', $tpl_editor).jqxButton({
									theme: cargohub.jqxtheme,
								}).on('click', function() {
									$ntf_contact.jqxDropDownList('clearSelection');
								});

								$('#tpl-locator_key', $tpl_editor).addClass(cargohub.input_theme_classes);
								break;

							// Document "Webview" Template - This type doesn't use the record type
							case 'view':
								$("#tpl-xdata", $tpl_editor).hide().empty();
								$("#tpl-edit-rt_select_ctrl", $tpl_editor).hide();
								break;

							// Anything else without a special profile
							default:
								$("#tpl-xdata", $tpl_editor).hide().empty();
								$("#tpl-edit-rt_select_ctrl", $tpl_editor).show();
						}
					});

				// Record type selector
				$('#tpl-rt_id', $tpl_editor).jqxDropDownList({
					theme: cargohub.jqxtheme,
					source: master.config.ist_source,
					displayMember: "name", valueMember: "id",
					width: 300, height: 22, autoDropDownHeight: true,
					renderer: function (index, label, value) {
						return "<div><img style='float: left; margin-right: 4px;' src='/img/icons16/ist/"+master.config.ist_source[index].key+".png' />"+ label+ "</div>";
					},
					selectionRenderer: function (originalRender) {
						var item = $('#tpl-rt_id', $tpl_editor).jqxDropDownList('getSelectedItem');
                                                
                                        //when template will create , there will be a option to check , for auto selected this template for those record which have this issue type, claim type etc 03-01-2023  start        
                                          if (item) {
                                            var role = $('#tpl-role', $tpl_editor).jqxDropDownList('getSelectedItem');
                                            console.log(role.originalItem.k);
                                            if (role.originalItem.k == 'task') {
                                                var forTemp = $('#tpl-role', $tpl_editor).jqxDropDownList('getSelectedItem');
                                                if (item && item.value == '1' && forTemp.value == 'task') {
                                                    $('#autoAssignSec').show();
                                                } else {
                                                    $('#xdata-claim_id', $tpl_editor).jqxDropDownList('clearSelection');
                                                    $('#xdata-assigned_user', $tpl_editor).jqxDropDownList('clearSelection');
                                                    $('#autoAssignSec').hide();
                                                }
                                            }
                                        }
                                        
                                        // when template will create , there will be a option to check , for auto selected this template for those record which have this issue type, claim type etc 03-01-2023  end
                                        
						if (item)
							return "<div style='margin-top: 3px;'><img style='float: left; margin: 0 4px 0 2px;' src='/img/icons16/ist/"+master.config.ist_source[item.index].key+".png' />"+ item.label+ "</div>";
						return "<div style='margin-top: 4px;'>(Please choose)</div>";
					}
				});

				$('#tpl-title, #tpl-description', $tpl_editor).addClass(cargohub.input_theme_classes);

				// The Insert template into Title button
				$('#tpl-insert-title', $tpl_editor).jqxButton({ theme: cargohub.jqxtheme })
					/**
					 * Event: Insert into Title button clicked
					 */
					.on('click', function() {
						open_InsertField($('#tpl-rt_id', $tpl_editor).val(), function(field_content) {
							var orig_title = $('#tpl-title', $tpl_editor).val();
							if(orig_title.length > 0)
								orig_title = orig_title+ ' ';
							$('#tpl-title', $tpl_editor).val(orig_title+ field_content);
						}, "Add field to Title");
					});

				content_editor.events.on_init = function() {
					// If first initialization starts in "edit" mode, load content. If not, just enable the editor
					$("#tpl-content-placeholder", $tpl_editor).hide();
					if(config.mode == 'edit') {
						load_Body();
					} else
						content_editor.enable();

					content_editor_loaded = true;
				};

				/**
				 * Extra modifications to TinyMCE
				 */
				if(self.config.texted == "TinyMCE")
					content_editor.events.setup = function(editor) {
						// The integrated Insert Field button
						editor.addButton('insert_placeholder', {
							text: 'Insert',
							icon: 'tplfield',
							onclick: function() {
								open_InsertField($('#tpl-rt_id', $tpl_editor).val(), function(field_content) {
									editor.insertContent(field_content);
								});
							}
						});
					};
			}

			// Set/Change title (header)
			if(config.title)
				$tpl_editor.jqxExpander('setHeaderContent', '<img src="/img/icons16/'+ (config.icon || 'template.png')+ '" alt="[o]" class="inline-icon"> '+ config.title);

			switch(config.mode) {
				case 'create':
					row_data = config.overrides? config.overrides: row_data;
					selected_row_open = false;
					// Role and Record type - Reset and enable
					$('#tpl-role', $tpl_editor).jqxDropDownList({disabled: false}).jqxDropDownList('clearSelection');
					$('#tpl-rt_id', $tpl_editor).jqxDropDownList({disabled: false}).jqxDropDownList('clearSelection');
					$('#tpl-rt_id', $tpl_editor).jqxTooltip('destroy');

					// Empty Title/Subject/Locator key (if present)
					$('#tpl-title, #tpl-description, #tpl-locator_key', $tpl_editor).val("");
					// Reset speciality editor
					$("#tpl-xdata", $tpl_editor).hide().empty();

					// Finally, clear editor
					content_editor.content_Write("\n");
					break;

				case 'edit':
					// Save row data from current row (so if another row is selected without being opened, it won't affect the editor)
					row_data = $.extend({}, selected_row);
					// Check if there's overrides or defaults for the data/values - Process "row_data" before editing
					if(config.overrides)
						row_data = $.extend(row_data, config.overrides);

					// Decode "xdata" from the selected row
					if(row_data && row_data.xdata)
						row_data.xdata_parsed = JSON.parse(row_data.xdata);

					// Find the correct role and set the dropdown (This should also trigger changes to visibility of extended controls)
					$('#tpl-role', $tpl_editor).jqxDropDownList('clearSelection');
					for(c=0; c< TEMPLATE_ROLES.length; c++)
						if(TEMPLATE_ROLES[c].k == row_data.role)
							$('#tpl-role', $tpl_editor).jqxDropDownList('selectIndex', c);

					// Show record type in the dropwdown
					// RT should not be changed, because the template might have variables which are only valid for a certain record type and become invalid after the change
					var rt_key_found = false;
					for(c=0; c< master.config.ist_source.length; c++)
						if(master.config.ist_source[c].key == row_data.rt_key) {
							rt_key_found = true;
							$('#tpl-rt_id', $tpl_editor).jqxDropDownList('selectIndex', c);
						}
					if(!rt_key_found)
						$('#tpl-rt_id', $tpl_editor).jqxDropDownList('clearSelection');

//			$('#tpl-rt_id', $tpl_editor).jqxDropDownList({disabled: true});
//			$('#tpl-rt_id', $tpl_editor).jqxTooltip({
//				theme: cargohub.jqxtheme,
//				content: "Record Type control is disabled for existing templates because of placeholders.",
//				position: "bottom"
//			});

					// Fill the Title and Description
					$('#tpl-title', $tpl_editor).val(row_data.title);
					$('#tpl-description', $tpl_editor).val(row_data.description);
					// Fill the Locator Key (if present)
					if(row_data.locator_key)
						$('#tpl-locator_key', $tpl_editor).val(row_data.locator_key);

					// If editor already initialized (moving from another record, reload new body)
					if(content_editor_loaded)
						load_Body();

					// Prepare specialty controls (bit delayed?)
					setTimeout(function() {
						switch(row_data.role) {
							case 'task':
								dd(row_data.xdata_parsed);

								// Deadline controls
								$('#xdata-deadline_days_slider', $tpl_editor).jqxSlider('setValue', row_data.xdata_parsed.deadline_days);
								$('#xdata-deadline_days', $tpl_editor).jqxNumberInput('setDecimal', row_data.xdata_parsed.deadline_days);
                                                                
                                                                
                                      // when template will create , there will be a option to check , for auto selected this template for those record which have this issue type, claim type etc 03-01-2023  start                          
                                        
                                        
                                        var claim_id_key_found = false;
//                                        console.log(CLAIM_OPTIONS.length);
                                        for(c=0; c< CLAIM_OPTIONS.length; c++){
                                            if(CLAIM_OPTIONS[c].value == row_data.xdata_parsed.claim_id) {
//                                                console.log(c);
                                                    claim_id_key_found = true;
                                                    $('#xdata-claim_id', $tpl_editor).jqxDropDownList('selectIndex', c);
                                            }
                                        }
                                        if(!claim_id_key_found){
                                            $('#xdata-claim_id', $tpl_editor).jqxDropDownList('clearSelection');
                                        }
                                        
                                        
                                        
                                        var assigned_user_key_found = false;
//                                        console.log(ASSIGNED_USER_OPTIONS.length);
                                        for(c=0; c< ASSIGNED_USER_OPTIONS.length; c++){
                                            if(ASSIGNED_USER_OPTIONS[c].value == row_data.xdata_parsed.assigned_user) {
//                                                console.log(c);
                                                    assigned_user_key_found = true;
                                                    $('#xdata-assigned_user', $tpl_editor).jqxDropDownList('selectIndex', c);
                                            }
                                        }
                                        if(!assigned_user_key_found){
                                            $('#xdata-assigned_user', $tpl_editor).jqxDropDownList('clearSelection');
                                        }
                            
                            
                            // when template will create , there will be a option to check , for auto selected this template for those record which have this issue type, claim type etc 03-01-2023  end
                                        
                                        
                                        
								task_deadline_control = null;

								if(row_data.xdata_parsed.auto_assign) {
									var items = $('#xdata-auto_assign', $tpl_editor).jqxDropDownList('getItems');
									for(c=0; c< items.length; c++)
										if(items[c].value == row_data.xdata_parsed.auto_assign)
											$('#xdata-auto_assign', $tpl_editor).jqxDropDownList('selectIndex', c);
								} else
									$('#xdata-auto_assign', $tpl_editor).jqxDropDownList('clearSelection');

								if(row_data.xdata_parsed.ovd_intv) {
									var items = $('#xdata-ovd_intv', $tpl_editor).jqxDropDownList('getItems');
									for(c=0; c< items.length; c++)
										if(items[c].value == row_data.xdata_parsed.ovd_intv)
											$('#xdata-ovd_intv', $tpl_editor).jqxDropDownList('selectIndex', c);
								} else
									$('#xdata-ovd_intv', $tpl_editor).jqxDropDownList('clearSelection');
								break;
							case 'arem':
								break;

							case 'ntf':
								break;
						}
					}, 100);
					break;
			}

			// Finished initialization ( or reconfiguration ) - Show the expander and expand
			$tpl_editor.show().jqxExpander('enable');
			$tpl_editor.on('expanded', function() {
				// When first expanded, initialize Content editor
				if(!content_editor_loaded)
					content_editor.setup({
						container: $("#tpl-content", $tpl_editor),
						params: {
							// Only useful for TinyMCE
							tinyMCE: {
								plugins: "link autolink autoresize paste textcolor image code hr fullscreen lists table colorpicker charmap searchreplace insertdatetime",
								toolbar: "insert_placeholder | bold italic underline | alignleft aligncenter alignright | formatselect fontselect fontsizeselect | hr link image table bullist numlist charmap insertdatetime | forecolor backcolor removeformat | undo redo | searchreplace fullscreen",
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
						}
					}, self);
			});
			$tpl_editor.jqxExpander('expand');

			if(!selected_row_open) {
				donrey.cancel_Delay('hide_bar_templates-row-ops');

				$('#templates-editor-ops button').jqxButton({disabled: false});
				$('#templates-editor-ops').fadeIn();
			}

			// Add tooltips for relevant elements
			$(".info-tip", $tpl_editor).each(cargohub.components.ui.add_Tooltip);

			selected_row_open = true;
		}
	;
	/**
	 * Templates Tab - Various format output templates, used for viewing, instancing (documents) and exporting issue data
	 */
	this.init = function()
	{
		init_Toolbars();

		// Initial grid state
		if(!self.config.grid_state) {
			self.config.grid_state = {
				sort: {
					column: 'rt_name',
					direction: 'asc'
				}
			};
		}

		init_Grid();

		// Load the configured editor component
		donrey.load_Components(self.config.texted, function() {
			content_editor = cargohub.components[self.config.texted];
			// init_Editor(); // Only initialized (and tinymce itself loaded) when first used
		}, "?v="+cargohub.version);
	};
});