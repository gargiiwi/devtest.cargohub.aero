/**
 * Record Blueprint Editor v2
 * - Allows creation and editing of record types
 */
cargohub.admin_rteditor = cargohub.baseclass._instance(cargohub.baseclass.fullgrid, function() {
	// private
	var self = this,
	c = null,

	// Field editor area (expander) variables
	selected_row = null,		// Data from the field grid containing currently selected row
	$field_editor = null,		// Expander containing various controls
	editor_open = false,		// Non-false when Editor is open (in either Create or Edit modes) - Save/Close buttons should be visible
	open_row_id = null,			// ID of the field that has been opened for editing (selected one might have changed in the mean time) - Only has a value when opening existing row (not for create/duplicate)
	field_unsaved = false,		// Editor is open and unsaved, show a warning when trying to open another row
	copy_mode = false,			// Becomes true when existing field is open for copying (saving creates new field)
	grid_Source = null,			// Data Source for the fields grid
	sections = [],				// Sections as list of k, v (decoded from self.config.sections)
	choices = [],				// Choices of currently edited field (decoded from the storage format)
	// Types of fields
	ft_options = [
		// Direct values
		{value: "text", text: "Text"},
		{value: "number", text: "Numeric value"},
		{value: "date", text: "Date value"},
		{value: "enum", text: "Single choice"},
		{value: "set", text: "Multiple choices"},
		// Plugin fields (processed by custom code)
		{value: "money", text: "Monetary value"},
		{value: "awb", text: "Air Waybill"},
		{value: "airport", text: "World Airport"}
	],

	/**
	 * Sets up the toolbar(s)
	 */
	init_Toolbars = function()
	{
		// Buttons to add/edit/remove an user from the department
		$('.toolbar button')
		.jqxButton({ theme: cargohub.jqxtheme })
		.on('click', function() {
			var data = $(this).data();

			switch(data.op) {
// === Top toolbar, primary info ===

			// Saves the Primary information (Name, Key, Statuses, Priorities, Groups)
			case 'save':
				$("#rt-main-config form").ajaxSubmit({
					dataType: 'json',
					data: {
						id: self.config.id		// Might be empty if in Create mode
					},
					beforeSubmit: function(formData, jqForm, options)
					{
						if(!$("#rt-main-config form").jqxValidator('validate'))
							return false;

						// Check if at least one status, one priority is set
						if(!$("#rt_statuses").val().length)
							return cargohub.alert("Please choose at least one status !");
						if(!$("#rt_priorities").val().length)
							return cargohub.alert("Please choose at least one priority !");

						// Convert the field sections from the list format to object format, and encode
						self.config.sections = {};
						for(c = 0; c< sections.length; c++)
							self.config.sections[sections[c].k] = sections[c].v;

						formData.push({ // the "text" from the master handler control
							name: "rt[sections]",
							value: JSON.stringify(self.config.sections)
						});

						cargohub.components.spinner.On(null, "Saving changes...", null, null, 1000);
					},
					success: function(x) {
//						cargohub.components.spinner.Off(500);
						document.location = "/admin/rtlist";
					},
					error: cargohub.its.http_fail
				});
				break;

			// Back to RT List
			case 'back':
//				if(was_changed)
//					if(!confirm("Warning! The data has been changed. Are you sure you want to discard it ?"))
//						return;

				document.location = "/admin/rtlist";
				break;

// === Middle toolbar, field definitions ===

			// Create new field
			case 'field-create':
				$("#fields-grid").jqxGrid('clearselection');
				open_row_id = null;
				copy_mode = false;

				init_Field_Editor({
					title: "Create new data field",
					icon: "add.png",
					mode: "create"
				});
				$('#field-create-op').fadeOut(); // Hide the "Create" button when the editor is active, will be shown again on Save or Close
				$("#field-save-alterdata").hide();
				break;

			case 'field-copy':
				open_row_id = null;
				copy_mode = true;

				$('#field-create-op').fadeIn();
				$("#field-save-alterdata").hide();
				init_Field_Editor({
					title: "Create new field by copying: <b>"+ selected_row.n+ "</b>",
					icon: "copy.png",
					mode: "copy",
					overrides: {
						n: selected_row.n+ " (copy)",
						fk: selected_row.fk+ "_copy"
					}
				});
				break;
			// Field editing
			case 'field-edit':
				open_row_id = selected_row.id;

				$('#field-create-op').fadeIn();
				$("#field-save-alterdata").show().jqxCheckBox('uncheck');
				init_Field_Editor({
					title: "Edit field: <b>"+ selected_row.n+ "</b>",
					icon: "pencil.png",
					mode: "edit"
				});
				break;

			// Deletes the selected field from the database, optionally removing data as well
			case 'field-delete':
				if(!confirm("Are you sure you want to delete this field"))
					return;

				cargohub.components.spinner.On(null, "Deleting field...", null, null, 500);
				$.post("?action=Delete_Field", {
					id: selected_row.id,
					removedata: $("#field-delete-removedata").jqxCheckBox('checked'),
					rt_id: self.config.id,	// Useful for cleaning up data and user profiles (TODO)
					rt_key: self.config.key
				}, function(x) {
					cargohub.components.spinner.Off(500);
					// Unselect row, Reload the grid
					cargohub.alert(x.response, "Success !", "thumb_up.png");
					self.refresh_Grid();
					// And close the editor if one was open
					field_unsaved = false;
					if(close_Field_Editor())
						$('#field-create-op').fadeIn();
				})
				.fail(cargohub.its.http_fail);
				break;

			// Save the field by ajax submitting the container form (and adding the information from "choices" which is edited sepparately)
			case 'field-save':
				if(!field_unsaved)
					return cargohub.alert("Nothing was changed, nothing to save");

				$("#rt-field-editor form").ajaxSubmit({
					dataType: 'json',
					data: {
						id: open_row_id,		// Empty for Create or Copy mode
						alterdata: $("#field-save-alterdata").jqxCheckBox('checked'), // Ignored by Create/Copy
						rt_id: self.config.id,	// Needed for Create/Copy, but also useful for Update for altering user data
						rt_key: self.config.key	// Used to update User Views that use the edited field; Doesn't work if Blueprint key has been changed !!
					},
					beforeSubmit: function(formData, jqForm)
					{
						var options = {}, cd = [], t = null;

						if($("#rt-field-editor #field-t").jqxDropDownList("selectedIndex") == -1)
							return cargohub.alert("Please select the type of data field you want to create");
						else
							t = $("#rt-field-editor #field-t").val();

						// Validate text fields
						if(!$("#rt-field-editor form").jqxValidator('validate'))
							return false;

						// Seems that Section is not captured automatically if not using the dropdown, so get the text value of the field
						formData.push({
							name: "field[s]",
							value: $("#rt-field-editor #field-s").val()
						});

						// Construct the "options" data from the enabled options/settings (if not checked, any entered value is ignored)
						$("#rt-field-editor #field-options-area label").each(function() {
							// everything else
							var selector = this.id.split('_'), option = selector[1];
							if($(this).jqxCheckBox('checked')) {
								switch(option) {
								case 'def':
									options[option] = $("#field-options-area #field-o_def-value").val();
									if(options['def'].length == 0)
										return cargohub.alert("The Default Value option is ticked but no value has been entered");
									break;
								case 'len':
									options[option] = $("#field-options-area #field-o_len-value").val();
									break;
								case 'colw':
									options[option] = $("#field-options-area #field-o_colw-value").val();
									break;
								case 'colr':
									options[option] = $("#field-options-area #field-o_colr-value").jqxDropDownList("selectedIndex");
									if(options['colr'] == -1)
										return cargohub.alert("The Column Render setting is ticked but none of the styles have been chosen");
									break;
								default:
									options[option] = true;
								}
							}
						});
						formData.push({
							name: "field[o]",
							value: JSON.stringify(options)
						});

						// Encode the choices array back into the storage format (array of strings); This might change sometime later to store choices as raw object instead (key, value)
						if(t == 'enum' || t == 'set') {
							if(choices.length == 0)
								return cargohub.alert("A field type which requires data choices is selected but no choices exist. Use the [Edit values] button");

							for(c = 0; c < choices.length; c++)
								cd.push('#'+choices[c].k+': '+choices[c].v);
							formData.push({
								name: "field[cd]",
								value: JSON.stringify(cd)
							});
						}

						cargohub.components.spinner.On(null, "Saving changes...", null, null, 500);
					},
					success: function(x) {
						cargohub.components.spinner.Off(500);
						// Unselect row, Reload the grid
						cargohub.alert(x.response, "Success !", "thumb_up.png");
						self.refresh_Grid();
						// And close the editor
						field_unsaved = false;
						if(close_Field_Editor())
							$('#field-create-op').fadeIn();
					},
					error: cargohub.its.http_fail
				});

				break;
			// Close field editor (with unsaved warning)
			case 'field-close':
				if(close_Field_Editor())
					$('#field-create-op').fadeIn();
				break;

			// Sections editor (window)
			case 'field-sections':
				cargohub.components.modal.open('assoc_editor', {
					width: 700, height: 440,
					title:  "Create, Edit and Reorder Field Sections",
					content: $('#win-assoc_editor').html(),
					callback: setup_Assoc_Editor,
					cb_config: {
						localdata: sections,
						key: "k", val: "v",
						emptystring: "No field sections defined yet"
					}
				});
				break;
			}
		});

		$("#field-save-alterdata").jqxCheckBox({
			theme: cargohub.jqxtheme,
			height: 24,
			enableContainerClick: false, checked: false
		})
		.on('checked', function() {
			cargohub.alert("Please make sure you know what you're doing ! Keep this option <b>off if unsure</b>.");
		});

		$("#field-delete-removedata").jqxCheckBox({
			theme: cargohub.jqxtheme,
			height: 24,
			enableContainerClick: false, checked: false
		})
		.on('checked', function() {
			cargohub.alert("<span class='red thick'>DELETING USER DATA IS NOT REVERSIBLE !</span> Keep this option <b>off</b> unless really needed.");
		});
	},

	/**
	 * Sets up a modal that deals with associative data (key, value)
	 */
	setup_Assoc_Editor = function($window, CFG)
	{
		var assoc_Source = new $.jqx.dataAdapter({
			localData: CFG.localdata,
			dataType: "array",
			dataFields: [
				{ name: CFG.key, type: 'string' },
				{ name: CFG.val, type: 'string' }
			]
		}),
		selected_row = null, selected_row_idx = -1,
		swap = null, // Temporary variable used to store the target position row, as the data is swapped
		total_rows = CFG.localdata.length;

		// Sections datatable
		$("#assoc-table", $window).jqxDataTable({
			theme: cargohub.jqxtheme,
			localization: {
				emptydatastring: CFG.emptystring || "Nothing defined yet"
			},
			width: 580, height: 250,
			source: assoc_Source,
			selectionMode: "singleRow", sortable: false,
			editable: true,
			editSettings: {
				saveOnPageChange: true,
				saveOnBlur: true,
				saveOnSelectionChange: true,
				cancelOnEsc: true,
				saveOnEnter: true,
				editSingleCell: true,
				editOnDoubleClick: true,
				editOnF2: true
			},
			columns: [
				{text: 'Name', dataField: CFG.val},
				{text: 'Key', dataField: CFG.key, width: 140}
			]
		})
		/**
		 * Event - When a row is selected, the commands are turned on (Delete, Move up/down, Move top/bottom)
		 */
		.on('rowSelect', function(event) {
			selected_row = event.args.row;
			selected_row_idx = event.args.index;
		})
		/**
		 * Event - When filters are engaged, clear selection and disable the commands
		 */
		.on('filter', function(event) {
			$(this).jqxDataTable('clearSelection');
		})
		/**
		 * Event - Cell is changed, update the source
		 */
		.on('cellValueChanged', function(e) {
			CFG.localdata[e.args.index][e.args.dataField] = e.args.value;
			if(CFG.on_change instanceof Function)
				CFG.on_change('cell');
		});

		// The command buttons
		$('div.command > button', $window).on('click', function() {
			var cmd = $(this).data('cmd'), createdata = {};

			if(cmd != 'create' && selected_row_idx == -1)
				return cargohub.alert("Selecting a row first would definitely help...");

			switch(cmd) {
			// Move up one position by swapping with the row above. Selected_row_idx changes
			case 'moveup':
				if(selected_row_idx == 0)
					return cargohub.alert("Already on top, cannot move higher !");

				swap = CFG.localdata[selected_row_idx -1];
				CFG.localdata[selected_row_idx -1] = selected_row;
				CFG.localdata[selected_row_idx] = swap;
				if(CFG.on_change instanceof Function)
					CFG.on_change('move');
				selected_row_idx--;
				break;
			// Move down one position with the same procedure as move up
			case 'movedown':
				if(selected_row_idx == total_rows-1)
					return cargohub.alert("Already on bottom, cannot move lower !");

				swap = CFG.localdata[selected_row_idx +1];
				CFG.localdata[selected_row_idx +1] = selected_row;
				CFG.localdata[selected_row_idx] = swap;
				if(CFG.on_change instanceof Function)
					CFG.on_change('move');
				selected_row_idx++;
				break;
			// Removes the row from current position and prepends the table with the saved copy
			case 'movetop':
				if(selected_row_idx == 0)
					return cargohub.alert("Already on top, need a Falcon 9 to go higher !");

				swap = CFG.localdata[selected_row_idx];
				CFG.localdata.splice(selected_row_idx, 1);
				CFG.localdata.unshift(swap);
				if(CFG.on_change instanceof Function)
					CFG.on_change('move');
				selected_row_idx = 0;
				break;
			// Same as movetop, but appends at the end
			case 'movebottom':
				if(selected_row_idx == total_rows -1)
					return cargohub.alert("Already on bottom, find a shovel and start digging !");

				swap = CFG.localdata[selected_row_idx];
				CFG.localdata.splice(selected_row_idx, 1);
				CFG.localdata.push(swap);
				if(CFG.on_change instanceof Function)
					CFG.on_change('move');
				selected_row_idx = total_rows -1;
				break;
			// Removes selected row from the table
			case 'delete':
				// Splice the source and reload the grid
				CFG.localdata.splice(selected_row_idx, 1);
				selected_row = null; selected_row_idx = -1;
				if(CFG.on_change instanceof Function)
					CFG.on_change('delete');
				total_rows--;
				break;
			// Appends a new row at the end of the table
			case 'create':
				var newkey = $("#assoc-key", $window).val().trim();
				// checks
				if(newkey.length < 1)
					return cargohub.alert("The element key cannot be empty");
				if(!/^[a-zA-Z0-9_]+$/.test(newkey))
					return cargohub.alert("The element key must consist only of alpha-numeric characters and _ (underscore), without spaces");
				for(c = 0; c< CFG.localdata.length; c++)
					if(newkey == CFG.localdata[c][CFG.key])
						return cargohub.alert("Key [ "+ newkey+ " ] is already used, please choose another");
				createdata[CFG.key] = newkey;
				createdata[CFG.val] = $("#assoc-val", $window).val().trim();
				if(createdata[CFG.val].length < 1)
					return cargohub.alert("Please enter content in the name field");

				// valid, create new entry
				CFG.localdata.push(createdata);
				if(CFG.on_change instanceof Function)
					CFG.on_change('create');
				selected_row_idx = total_rows;
				total_rows++;
				$("#assoc-val, #assoc-key", $window).val(""); // Clear the inputs after inserting
				break;

			}
			// After every operation, update the grid
			$("#assoc-table", $window).jqxDataTable('updateBoundData');
			$("#assoc-table", $window).jqxDataTable('selectRow', selected_row_idx);
		});

		if(CFG.on_close instanceof Function) {
			$window.on('close', CFG.on_close);
		}
	},

	/**
	 * Record definition has a set of variables like Name, List of Statuses and Priorities and other parameters.
	 * This method initializes those controls
	 */
	init_ConfigHeader = function()
	{
		// Statuses and Priorities available for this record type (from all defined)
		var statuses_source = [],
			priorities_source = [],
			// The types of groups currently implemented by the system
			group_source = [
//				{k: 0, text: "- Not using groups -"},	// Doesn't work, groups are needed
				{k: "general", text: "General"},
				{k: "airline", text: "Airlines"},
				{k: "marine", text: "Shipping Line/Agents"},
				{k: "dept", text: "Organization department"},
				{k: "cert", text: "Certification type"},
				{k: "customer", text: "Primary Customer"},
				{k: "area", text: "Platform area"},
				{k: "class", text: "Class of item"},
				{k: "training", text: "Training"},
				{k: "asset", text: "Asset"},
			],
			// The IDs are loaded after the group set is selected
			group_ids_source = new $.jqx.dataAdapter({
				url: '?action=Get_Groups',
				data: {},
				datatype: "json",
				datafields: [
					{name: 'id', type: 'number'},
					{name: 'type'},
					{name: 'name'}
					],
				id: "id"
			});

		// Convert Sections from object to list of key, value
		if(self.config.sections)
			$.each(self.config.sections, function(k, v) {
				sections.push({k: k, v: v});
			});

		// Form with standard fields
		$("#rt-main-config form").jqxValidator({
			rules: [
				{input: "#rt_name", message: "The Record Type needs a name", action: 'blur', rule: 'required'},
				{input: "#rt_name", message: "Name of the Record Type must be shorter than 50 characters", action: 'keyup', rule: 'maxLength=50'},
				{input: "#rt_short_plural", message: "The plural form is needed for correct operation", action: 'blur', rule: 'required'},
				{input: "#rt_short_plural", message: "Maximum length of Plural form is 50 characters", action: 'keyup', rule: 'maxLength=50'},
				{input: "#rt_key", message: "Folder key must be set", action: 'blur', rule: 'required'},
				{input: "#rt_key", message: "Maximum length of Folder Key is 16 characters", action: 'keyup', rule: 'maxLength=16'},
				{input: "#rt_key", message: "The Key must consist only of letters and numbers (no spaces, no punctuation signs)", action: 'keyup', rule: function($input) {
					return /^[a-zA-Z0-9_]+$/.test($input.val());
				}}
			],
			position: "bottom"
		});

		/* Status list selector */

		// 1 - Load from "statuses" the ones which are active, and mark them as "checked", in the order they appear
		for(c=0; c< self.config.statuses.length; c++) {
			statuses_source.push({
				"key": self.config.statuses[c],
				"text": self.config.statuses_global[self.config.statuses[c]],
				"checked": true // Metavalue
			});
		}
		// 2 - Load the rest as inactive
		for(c in self.config.statuses_global)
			if(!self.config.statuses_ex[c])
				statuses_source.push({
					"key": c,
					"text": self.config.statuses_global[c]
				});

		$('#rt_statuses').jqxListBox({
			theme: cargohub.jqxtheme,
			source: statuses_source,
			width: 320, height: 222,
			valueMember: "key", displayMember: "text",
			checkboxes: true,
			allowDrag: true, allowDrop: true,
			filterable: true, searchMode: 'containsignorecase'
		});

		// Selectors for initial status for "incoming" or "self create" modes
		$("#rt_settings_irs").jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: statuses_source,
			width: 320, height: 23,
			dropDownHeight: 222,
			valueMember: "key", displayMember: "text",
			filterable: true, searchMode: 'containsignorecase'
		});
		if(self.config && self.config.settings && self.config.settings.init && self.config.settings.init.recv) {
			for(c=0; c< statuses_source.length; c++) {
				if(statuses_source[c]['key'] == self.config.settings.init.recv.stat)
					$('#rt_settings_irs').jqxDropDownList('selectIndex', c);
			}
		}

		$("#rt_settings_iss").jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: statuses_source,
			width: 320, height: 23,
			dropDownHeight: 222,
			valueMember: "key", displayMember: "text",
			filterable: true, searchMode: 'containsignorecase'
		});

		// select the index for the active initial status ( ist settings ) (edit or init mode)
		if(self.config && self.config.settings && self.config.settings.init && self.config.settings.init.self) {
			for(c=0; c< statuses_source.length; c++) {
				if(statuses_source[c]['key'] == self.config.settings.init.self.stat)
					$('#rt_settings_iss').jqxDropDownList('selectIndex', c);
			}
		}

		/* Priority list selector */

		// 1 - Load from "priorities" the ones which are active, and mark them as "checked", in the order they appear
		for(c=0; c< self.config.priorities.length; c++) {
			priorities_source.push({
				"key": self.config.priorities[c],
				"text": self.config.priorities_global[self.config.priorities[c]],
				"checked": true // Metavalue
			});
		}
		// 2 - Load the rest as inactive
		for(c in self.config.priorities_global)
			if(!self.config.priorities_ex[c])
				priorities_source.push({
					"key": c,
					"text": self.config.priorities_global[c]
				});

		$('#rt_priorities').jqxListBox({
			theme: cargohub.jqxtheme,
			source: priorities_source,
			width: 320, height: 222,
			valueMember: "key", displayMember: "text",
			checkboxes: true,
			allowDrag: true, allowDrop: true
//			filterable: true, searchMode: 'containsignorecase'
		});

		// Selectors for initial priority for "incoming" or "self create" modes
		$("#rt_settings_irp").jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: priorities_source,
			width: 320, height: 23,
			dropDownHeight: 222,
			valueMember: "key", displayMember: "text"
		});

		if(self.config && self.config.settings && self.config.settings.init && self.config.settings.init.recv) {
			for(c=0; c< priorities_source.length; c++) {
				if(priorities_source[c]['key'] == self.config.settings.init.recv.prio)
					$('#rt_settings_irp').jqxDropDownList('selectIndex', c);
			}
		}

		$("#rt_settings_isp").jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: priorities_source,
			width: 320, height: 23,
			dropDownHeight: 222,
			valueMember: "key", displayMember: "text"
		});
		if(self.config && self.config.settings && self.config.settings.init && self.config.settings.init.self) {
			for(c=0; c< priorities_source.length; c++) {
				if(priorities_source[c]['key'] == self.config.settings.init.self.prio)
					$('#rt_settings_isp').jqxDropDownList('selectIndex', c);
			}
		}

		/* Group */

		// the group usage selector (All implemented group plugins)
		$("#rt_settings_group").jqxDropDownList({
			source: group_source,
			displayMember: "text",
			valueMember: "k",
			width: 320, height: 23,
			theme: cargohub.jqxtheme,
			autoDropDownHeight: true
		})
		/**
		 * Event: The group set is selected
		 */
		.on("select", function(event) {
			// Activate the source for the group filter selector and read the list of groups which are valid for the set
			group_ids_source._options.data = {
				group_type: event.args.item.value,	// Selected group set
			};

			$("#rt_group_ids").jqxDropDownList({
				source: group_ids_source,
				placeHolder: "(Loading groups)"
			});
		});

		// Choose the existing groups automatically (with a tiny select to allow proper initialization of the dropdown)
		setTimeout(function() {
			if(self.config && self.config.settings && self.config.settings.group_type)
				for(c=0; c< group_source.length; c++) {
					if(group_source[c].k == self.config.settings.group_type)
						$('#rt_settings_group').jqxDropDownList('selectIndex', c);
				}
		}, 1);

		// The group filter, loaded dynamically and depending on group set
		$("#rt_group_ids").jqxDropDownList({
			theme: cargohub.jqxtheme,
			checkboxes: true,
			displayMember: "name", valueMember: "id",
			width: 320, height: 23,
			placeHolder: "(waiting for group set selector)",
			disabled: true,
			filterable: true, searchMode: 'containsignorecase'
		})
		.on('bindingComplete', function() {
			if(group_ids_source.records.length == 0) {
				$(this).jqxDropDownList({
					placeHolder: "(Filters not available)",
					disabled: true
				});
				return;
			}

			$(this).jqxDropDownList({
				dropDownHeight: group_ids_source.records.length> 7? 222: null,
				autoDropDownHeight: group_ids_source.records.length<= 7? true: false,
				selectionRenderer: function(orig,index,str) {
					var
					span_style = "<span style='display: block; width: 278px; text-overflow: ellipsis; margin-top: 3px; background-color: transparent;'>",
					string = index==-1? "Please enable at least one group!": function() {
						var checked = $("#rt_group_ids").jqxDropDownList("getCheckedItems"), string = '', c = 0;
						if(checked.length <= 1) {
							for(; c< checked.length; c++)
								string+= (c>0? ', ': '') + checked[c].label;
						} else if(checked.length == group_ids_source.records.length)
							string = "All groups selected";
						else
							string = checked.length + " out of " + group_ids_source.records.length + " groups selected";
						return string;
					}();

					if(group_ids_source.records.length == 0)
						return span_style + "(Filters not available)</span>";
					else
						return span_style +string+"</span>";
				},
				disabled: false
			});

			// Check the enabled filters
			if(self.config.group_ids) {
				for(c=0; c<group_ids_source.records.length; c++) {
					if($.inArray(group_ids_source.records[c].id.toString(), self.config.group_ids) > -1)
						$(this).jqxDropDownList('checkIndex', c);
				}
			} else
				$(this).jqxDropDownList({placeHolder: "Please enable at least one group!"});
		});

		/* Sidebar */
/*
		$("#sidebar button").jqxButton({ theme: cargohub.jqxtheme })
		.on('click', function() {
			var data = $(this).data();
			switch(data.op) {
			case 'config-panels':
				alert("Config Panels");
				break;
			case 'config-cache':
				alert("Config Cache-Fields");
				break;
			}
		});
*/
	},

	/**
	 * A record row has been selected - Store information in preparation for a double-click ( for Previewing or Opening ), or for clicking on one of the toolbar buttons
	 */
	select_Row = function(event)
	{
		var operations_bar = "#rtedit-fieldops";

		if(!selected_row) {
			donrey.cancel_Delay('hide-fieldops_bar');
			$(operations_bar+ ' button').jqxButton({disabled: false});
			$(operations_bar).fadeIn();
		}

		if(event.args.row)
			selected_row = event.args.row;
	},

	/**
	 * No rows are currently selected - Make the toolbar buttons dissapear
	 */
	select_Clear = function()
	{
		var operations_bar = "#rtedit-fieldops";

		$("#fields-grid").jqxGrid('clearselection');
		selected_row = null;
		// And hide the buttons
		$(operations_bar+ ' button').jqxButton({disabled: true});
		donrey.single_Delay('hide-fieldops_bar', function() {
			$(operations_bar).fadeOut();
		}, 500);
	},

	/**
	 * Setup the fields grid - This one allows editing each field individually
	 * (Old variant of this module would load and save all fields at once and is very inefficient for data types with lots of fields - 50 or more)
	 */
	init_FieldsGrid = function()
	{
		// Prepare the grid (Configurable)
		var gridDataFields = [
			{name: 'id', type: 'number'},	// Numeric DB id
			{name: 'fk', type: 'string'},	// Field Key (must be unique for one record type)
			{name: 's', type: 'string'},	// Section (a way to organize/group fields)
			{name: 'n', type: 'string'},	// Field name, how it appears in the grid, template editor and other places - Combined with the Section the fields can be easily located
			{name: 't', type: 'string'},	// Field type - Each type is processed differently
			{name: 'o', type: 'string'},	// Field options; JSON encoded (may contain multiple values)
			{name: 'cd', type: 'string'},	// Choice-Data, for certain field types, contains the options which are presented for that field; JSON encoded - Array of strings
			// NOTE: cd is stored inefficiently, in "string-encoded" form. Once the interface to control it is finished they should be converted into objects (key, value) or just "value"?
			{name: 'count', type: 'number'}	// Count on how many records of this field exist in the database - Not updated when records are created. Must be recounted manually
		], ft_list = [], ft_translate = {};

		for(c=0; c<ft_options.length; c++) {
			ft_list.push(ft_options[c].text);
			ft_translate[ft_options[c].value] = ft_options[c].text;
		}

		grid_Source = self.source({
			url: "?action=Get_Fields",
			data: {
				rt_id: self.config.id,
				virtualmode: true
			},
			datatype: "json",
			root: "data",
			datafields: gridDataFields,
			// server side operations - filtering and sorting:
			cache: false,
			filter: function() {
				$("#fields-grid").jqxGrid('updatebounddata', 'filter');
			},
			sort: function() {
				$("#fields-grid").jqxGrid('updatebounddata', 'sort');
			},
			beforeprocessing: function(source) {
				this.totalrecords = source.found_rows;
			},
			/**
			 * Client-Side pre-processing of filter data, because the way the grid sends it by default is total crap.
			 */
			formatData: cargohub.its.jqxGrid_FormatFilters
		});

		// Columns
		self.column({text: "ID", dataField: 'id', width: 55, cellsalign: "right", filtertype: "number"});
		self.column({	// TODO: Eventually, sections should be checkedlist (and list of sections part of the RT definition)
			text: "Section", datafield: 's',
//			filtertype: "checkedlist", filteritems: Object.keys(self.config.sections),
			width: 120, minwidth: 80, maxwidth: 150,
//			cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
//				return '<div style="margin: 4px 4px 2px 4px; overflow: hidden; padding-bottom: 2px;">'+ self.config.sections[value]+ '</div>';
//			}
		});
		self.column({
			text: "Field Name", datafield: 'n',
			width: 250, minwidth: 100, maxwidth: 300,
			groupable: false
		});
		self.column({
			text: "Template Key", datafield: 'fk',
			width: 125, minwidth: 80, maxwidth: 150,
			groupable: false
		});
		self.column({
			text: "Content Type", datafield: 't',
			filtertype: "checkedlist", filteritems: ft_list,
			width: 150, minwidth: 150, maxwidth: 150,
			cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
				return '<div style="margin: 4px 4px 2px 4px; overflow: hidden; padding-bottom: 2px;">'+ ft_translate[value]+ '</div>';
			},
			createfilterwidget: function (column, columnElement, widget) {
				widget.jqxDropDownList({
					dropDownWidth: 140,
					dropDownHeight: 220,
					animationType: "fade"
				});
			}
		});
		self.column({
			text: "Value choices", datafield: 'cd',
			sortable: false,
			cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
				var c, cd = null, choice = null, choice_text = '', cd_length = 0, cd_string = "", tooltip_string = "";
				if(value.length > 0) {
					try {
						cd = JSON.parse(value);
						for(c=0; c< cd.length; c++) {
							choice = /#([^:]+): (.*)/.exec(cd[c]);
							if(choice)
								choice_text = choice[2];
							else {
								choice_text = cd[c];
							}
							cd_string += '<span class="choice">'+ choice_text+ "</span>";
							tooltip_string += choice_text+ "\n";
							cd_length += choice_text.length;
							first = false;
						}

						// Total choice data length below threashold ? Display choices in boxes. Too big ? Display a fixed text with tooltip
						if(cd_length > 66)
							cd_string = '<span title="'+tooltip_string +'">This field has <b>'+ cd.length+ '</b> choices (Hover to see them)</span>';
					} catch(err) {
						// nothing ?
					}
				}
				return '<div style="margin: 4px 4px 2px 4px; overflow: hidden; padding-bottom: 2px;">'+ cd_string+ '</div>';
			},
			groupable: false
		});

		// Init the grid as persistent in session
		self.init_Grid($("#fields-grid"), {
			width: cargohub.fullwidth,
			virtualmode: true, // virtual state
			rendergridrows: function(params) { return params.data; },
			pageable: true, pagesizeoptions: [10, 25, 50, 100, 250], pagesize: 25,
			groupable: true
		}, {
			persistent: false
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
		 * Event: On doubleclicking, do the same as simple clicking then using the Field Edit button
		 */
		.on('rowdoubleclick', function() {
			$('.toolbar button[data-op="field-edit"]').trigger("click");
		});

	},

	/**
	 * Setup the field editor expander, placed above the grid
	 *
	 * @param config Information now how the editor area is initialized
	 */
	init_Field_Editor = function(config)
	{
		if(editor_open && field_unsaved)
			if(!confirm("The editor is already open with unsaved changes.\nAre you sure you want to "+config.mode+" another field?"))
				return false;

		if($field_editor) {
			// If already initialized just change jqxExpander according to config
			if(config && config.expander)
				$field_editor.jqxExpander(config.expander);
		} else {
			// Otherwise, start full initialization
			$field_editor = $("#rt-field-editor");

			if(!config.expander)
				config.expander = {
					disabled: false,
					expanded: false
				};
			config.expander.theme = cargohub.jqxtheme;
			$field_editor.jqxExpander(config.expander);
		}

		// Mark the editor as open (in one of the specified modes)
		editor_open = config.mode;

		// Set/Change title (header)
		if(config.title)
			$field_editor.jqxExpander('setHeaderContent', '<img src="/img/icons16/'+ (config.icon || 'textfield.png')+ '" alt="[  ]" class="inline-icon"> '+ config.title);

		// Validation of text inputs
		$("form", $field_editor).jqxValidator({
			rules: [
				{input: "#field-n", message: "The Field needs a name", action: 'blur', rule: 'required'},
				{input: "#field-n", message: "Field's name length is maximum 100 characters", action: 'keyup', rule: 'maxLength=100'},
				{input: "#field-fk", message: "The field must have a &quot;key&quot; (used in templates)", action: 'blur', rule: 'required'},
				{input: "#field-fk", message: "Maximum length for field key is 50 characters", action: 'keyup', rule: 'maxLength=60'},
				{input: "#field-fk", message: "The Key must consist only of letters and numbers (no spaces, no punctuation signs)", action: 'keyup', rule: function($input) {
					return /^[a-zA-Z0-9_]+$/.test($input.val());
				}}
			],
			position: "bottom"
		});

		// Managed controls
		$('#field-t', $field_editor).jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: ft_options,
			displayMember: "text", valueMember: "value",
			width: 150, height: 25,
			autoDropDownHeight: true
		})
		/**
		 * Event, when this is changed, turn on and off various elements of the Editor, relevant for the selected field type
		 */
		.on('change', function() {
			toggle_Relevant_Areas($(this).val());
		});

		$('#field-s', $field_editor).jqxComboBox({
			theme: cargohub.jqxtheme,
			source: sections,
			displayMember: "v", valueMember: "k",
			width: 150, height: 25,
			dropDownWidth: 250, autoDropDownHeight: true
		})
		/**
		 * Event: When the section data is changed, set the field as unsaved
		 */
		.on('change', function() {
			field_unsaved = true;
		});

		$('#field-cd', $field_editor).jqxButton({ theme: cargohub.jqxtheme, disabled: true })
		/**
		 * Event, Open the choices window (using the Assoc Data editor)
		 */
		.on('click', function() {
			cargohub.components.modal.open('assoc_editor', {
				width: 700, height: 440,
				title:  "Edit field choices for: "+row_data.n,
				content: $('#win-assoc_editor').html(),
				callback: setup_Assoc_Editor,
				cb_config: {
					localdata: choices,
					key: "k", val: "v",
					emptystring: "No value choices defined yet",
					on_change: function() {
						field_unsaved = true;
						$("#cd-changed", $field_editor).fadeIn();
					},
					on_close: function() {
						$('#field-choices-readonly > div', $field_editor).jqxListBox({
							source: choices
						});
					}
				}
			});
		});
		$("#cd-changed", $field_editor).hide();

		// List of existing options, as a simple listbox - For information only, to edit the button "Edit values" is used
		$('#field-choices-readonly > div', $field_editor).jqxListBox({
			theme: cargohub.jqxtheme,
			width: 680, height: 197,
			valueMember: "k", displayMember: "v",
			filterable: true, searchMode: 'containsignorecase'
		});

	//=== Options area
		$('#field-options-area label', $field_editor).jqxCheckBox({
			theme: cargohub.jqxtheme,
			height: 26,
			enableContainerClick: false, checked: false
		})
		/**
		 * Set editor as unsaved if any of the options/settings checkboxes are touched
		 */
		.on('change', function() {
			field_unsaved = true;
		});

		// Max length option (used in forms)
		$("#field-o_len-value", $field_editor).jqxNumberInput({
			theme: cargohub.jqxtheme,
			spinButtons: true, inputMode: 'simple',
			min: 5, max: 40000, decimalDigits: 0,
			width: 80, height: 23
		})
		/**
		 * If a change here is made, set the editor as unsaved, but only if the matching checkbox is ON (otherwise ignored)
		 */
		.on('change', function() {
			if($("#field-o_len", $field_editor).jqxCheckBox('checked'))
				field_unsaved = true;
		});

		// Column width option (grid)
		$("#field-o_colw-value", $field_editor).jqxNumberInput({
			theme: cargohub.jqxtheme,
			spinButtons: true, inputMode: 'simple',
			min: 40, max: 1000, decimalDigits: 0,
			width: 80, height: 23
		})
		/**
		 * If a change here is made, set the editor as unsaved, but only if the matching checkbox is ON (otherwise ignored)
		 */
		.on('change', function() {
			if($("#field-o_colw", $field_editor).jqxCheckBox('checked'))
				field_unsaved = true;
		});

		// Type of column render (grid)
		$("#field-o_colr-value", $field_editor).jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: [
				"Default",
				"Currency Symbol",
				"Yes/No",
				"Center align",
				"Right align",
				"Relative time diff",
				"Only date, no time",
				"24hr Clock"
			],
			width: 180, height: 25,
			autoDropDownHeight: true, dropDownWidth: 180,
			placeHolder: "Select only if needed:"
		})
		/**
		 * If a change here is made, set the editor as unsaved, but only if the matching checkbox is ON (otherwise ignored)
		 */
		.on('change', function() {
			if($("#field-o_colr", $field_editor).jqxCheckBox('checked'))
				field_unsaved = true;
		});

	//=== Setup input values

		var row_data = null, cd_raw = [], choice = null,
		toggle_Relevant_Areas = function(type) {
			$("#field-o_def, #field-o_len, #field-o_num, #field-o_flag", $field_editor).each(function() {
				$(this).parent().hide();
			});
			$('#field-choices-readonly', $field_editor).hide();

			switch(type) {
			case 'text':
			case 'number':
				$("#field-o_len", $field_editor).parent().show();
				$('#field-cd', $field_editor).jqxButton({ disabled: true });
				break;
			case 'enum':
				$("#field-o_def", $field_editor).parent().show();
				$("#field-o_num", $field_editor).parent().show();
				$('#field-cd', $field_editor).jqxButton({ disabled: false });
				$('#field-choices-readonly', $field_editor).show();
				break;
			case 'set':
				$("#field-o_flag", $field_editor).parent().show();
				$('#field-cd', $field_editor).jqxButton({ disabled: false });
				$('#field-choices-readonly', $field_editor).show();
				break;
			default:
				$('#field-cd', $field_editor).jqxButton({ disabled: true });
			}
		};

		// Reset fields and options to default before create or loading row
		$('#field-t', $field_editor).jqxDropDownList('clearSelection');
		$('form input', $field_editor).addClass(cargohub.input_theme_classes)
		.on('change', function() {
			field_unsaved = true;
		})
		.val("");
		// Options
		$("#rt-field-editor #field-options-area label").jqxCheckBox('uncheck');
		$("#field-o_def-value", $field_editor)
		.on('change', function() {
			if($("#field-o_def", $field_editor).jqxCheckBox('checked'))
				field_unsaved = true;
		})
		.val("");
		$("#field-o_len-value", $field_editor).val(100);
		$("#field-o_colw-value", $field_editor).val(150);
		$("#field-o_colr-value", $field_editor).jqxDropDownList('clearSelection');
		// Choices
		choices = [];
		$('#field-choices-readonly', $field_editor).hide();

		switch(config.mode) {
		// Create mode - Field information must be entered
		case "create":
			$("#field-t", $field_editor).parent().show();
			$('#field-choices-readonly > div', $field_editor).jqxListBox('clear');
			$('#field-s', $field_editor).jqxComboBox('clearSelection');
			row_data = {};
			break;
			// Edit mode loads existing field values into the Field Editor
		case "copy":
		case "edit":
			// In pure Edit mode, the type control is hidden. In Copy it allows setting the type of the copy (before creating a new control) !
			if(config.mode == 'edit')
				$("#field-t", $field_editor).parent().hide();
			else
				$("#field-t", $field_editor).parent().show();
			row_data = $.extend({}, selected_row);
			if(config.overrides) // Usually for "copy"
				row_data = $.extend(row_data, config.overrides);

			// Fill in the field information in the editor controls
			$("#field-t", $field_editor).val(row_data.t); // Hidden, but still functional
			$('#field-n', $field_editor).val(row_data.n);
			$('#field-fk', $field_editor).val(row_data.fk);
			$('#field-s', $field_editor).jqxComboBox('clearSelection');
			$('#field-s', $field_editor).val(row_data.s);

			// Prepare the Options (checkboxes, and some elements)
			row_data.o = JSON.parse(row_data.o);
			for(var opt in row_data.o) {
				$("#field-o_"+opt, $field_editor).jqxCheckBox("check");
				if(opt == 'def' || opt == 'len' || opt == 'colw')
					$("#field-o_"+opt+"-value", $field_editor).val(row_data.o[opt]);
				if(opt == 'colr')
					$("#field-o_colr-value", $field_editor).jqxDropDownList('selectIndex', row_data.o['colr']);
			}

			// Prepare choices for the specific field types
			if(row_data.t == 'enum' || row_data.t == 'set') {
				// Decode CD into {k, v} array
				cd_raw = JSON.parse(row_data.cd);
				for(c=0; c< cd_raw.length; c++) {
					if(cd_raw[c].length == 0)
						continue; // Skip empty rows
					choice = /#([^:]+): (.*)/.exec(cd_raw[c]);
					if(choice)
						choices.push({k: choice[1], v: choice[2]});
					else
						choices.push({k: cd_raw[c], v: cd_raw[c]});
				}

				$('#field-choices-readonly > div', $field_editor).jqxListBox({
					source: choices
				});
			}
			break;
		}
		toggle_Relevant_Areas(row_data.t);

		// Finished initialization ( or reconfiguration ) - Show the expander and expand
		$field_editor.show().jqxExpander('enable');
		$field_editor.on('expanded', function() {
			// Stuff to be initialized when expanded
		});
		$field_editor.jqxExpander('expand');

		// Show the Save/Close controls
		$('#field-open-ops button').jqxButton({disabled: false});
		$('#field-open-ops').fadeIn();
		if(config.mode == 'copy')
			field_unsaved = true;
		else
			field_unsaved = false;
	},

	/**
	 * Closes the field editor (but requires for confirmation in case data is unsaved)
	 */
	close_Field_Editor = function()
	{
		if(field_unsaved)
			if(!confirm("There are unsaved changes in the field editor.\nAre you sure you want to close?"))
				return false;

		// Minimize and close the editor
		if(editor_open) {
			if($field_editor.jqxExpander('expanded')) {
				$field_editor.on('collapsed', function() {
					// When finishing collapse... hide the editor
					$field_editor.hide();
					// ... and disable the event
					$field_editor.off('collapsed');
				});
				$field_editor.jqxExpander('collapse');
			} else {
				// If already collapsed, just hide it
				$field_editor.hide();
			}

			// Also fadeout the Save/Close buttons
			$('#field-open-ops button').jqxButton({disabled: true});
			$('#field-open-ops').fadeOut();
		}
		field_unsaved = false;
		editor_open = false;
		open_field_id = null;
		copy_mode = false;
		return true;
	}
	;
	/**
	 * Section initialization
	 */
	this.init = function()
	{
		// Some tools used everywhere
		cargohub.components.modal.create('assoc_editor', {
			buttons: {ok: "Finished"}
		});

		// Toolbar and Primary
		init_Toolbars();
		$("#rt-main-config").jqxExpander({
			theme: cargohub.jqxtheme,
			expanded: true
		});
		init_ConfigHeader();

		// Fields list and editor
		$("#rt-field-def").jqxExpander({
			theme: cargohub.jqxtheme,
			expanded: true
		});
		init_FieldsGrid();

		// Help and Guidance
		$("#info-container").jqxExpander({
			theme: cargohub.jqxtheme,
			expanded: false
		});

		// Tooltips for this module
		$(".info-tip").each(cargohub.components.ui.add_Tooltip);
	};
});