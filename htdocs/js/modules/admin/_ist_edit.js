/**
 * Record type editor (Original version)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScriptUI.Admin
 * @version 2019.01.20
 */
cargohub.admin_ist_edit = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null, // general counter used in for()
		input_theme_classes = "jqx-input jqx-widget-content jqx-input-"+cargohub.jqxtheme+" jqx-widget-content-"+cargohub.jqxtheme,
		xfline_counter = 0,
		xfdeleted = [], // list of deleted field IDs, submitted as a list
		active_row = null,
		was_changed = false, // not yet implemented

		// state of the pop-up windows used by this page - Note editor, E-mail send. Used for lazy initialization ( on first use )
		popups = {},

	/**
	 * Sets up the toolbar with operations related to organizations
	 */
	setup_Toolbar = function()
	{
		// Buttons to add/edit/remove an user from the department
		$('.toolbar button')
		.jqxButton({ theme: cargohub.jqxtheme })
		.on('click', function() {
			var data = $(this).data(), $form = $("div.page form");

			switch(data.op) {
				case 'back':
					// Back to grid
					document.location = "/admin/ist";
					break;

				case 'save':
//					if(!was_changed)
//						return cargohub.its.alert("Nothing was changed, nothing to save.", "Notification", "error.png");

					// add the options and choices data for the xfields to the form (which are not stored as inputs or jqx pseudo-inputs), but as data fields
					$("form.vertiform li.xfline").each(function() {
						if(!$.isEmptyObject($(this).data("options"))) {
							$xfo = $("<input type='hidden' name='ist[xf]["+$(this).data("idx")+"][o]' />").val(JSON.stringify($(this).data("options")));
							$("form.vertiform").append($xfo);
						}
						if(!$.isEmptyObject($(this).data("choices"))) {
							$xfcd = $("<input type='hidden' name='ist[xf]["+$(this).data("idx")+"][cd]' />").val(JSON.stringify($(this).data("choices")));
							$("form.vertiform").append($xfcd);
						}
					});

					// add the list of deleted fields (useful for the update procedure)
					$xfdeleted = $("<input type='hidden' name='ist[xfdeleted]' />").val(xfdeleted.join(','));
					$("form.vertiform").append($xfdeleted);

					// Saves the currently open issue type by triggering the form submit
					if(self.config.id)
						$form.append("<input type='hidden' name='ist_id' value='"+self.config.id+"' />");
					$form.trigger('submit');
					break;

				case 'discard':
//					if(was_changed)
//						if(!confirm("Are you sure you want to abandon all your changes ?"))
//							return;

					// Abandons all changes that were done to the issue type and returns to View mode (if changes were done asks if it should proceed)
					if(self.config.id)
						document.location = "?action=details&id="+ self.config.id;
					else
						document.location = "/admin/ist";
					break;
			}
		});
	},

	/**
	 * Sets up the dropdowns for Statuses, Priorities and Groups
	 */
	setup_Stat_Prio_Group = function()
	{
		var
		// The types of groups currently implemented by the system
		group_source = [
			{k: 0, text: "- Not using groups -"},
			{k: "general", text: "General"},
			{k: "airline", text: "Airlines"},
			{k: "marine", text: "Shipping Line/Agents"},
			{k: "dept", text: "Organization department"},
			{k: "cert", text: "Certification type"},
			{k: "customer", text: "Primary Customer"},
			{k: "area", text: "Platform area"},
			{k: "class", text: "Class of item"}
		],
		// The IDs are loaded after the group set is selected
		group_ids_source = new $.jqx.dataAdapter({
			url: '?action=get_groups',
			data: {},
			datatype: "json",
			datafields: [
				{name: 'id', type: 'number'},
				{name: 'type'},
				{name: 'name'}
			],
			id: "id"
		}),
		statuses_source = [],
		priorities_source = []
		;

		/* Status list selector  

		// Build the array for the status list box
		// 1 - Load from "statuses" the ones which are active, and mark them as "checked", in the order they appear
		for(c=0; c< self.config.statuses.length; c++) {
			statuses_source.push({
				"key": self.config.statuses[c],
				"text": self.config.statuses_global[self.config.statuses[c]],
				"checked": true // Metavalue
			});
		} */
		// 2 - Load the rest as inactive
		for(c in self.config.statuses_global)
			if(!self.config.statuses_ex[c])
				statuses_source.push({
					"key": c,
					"text": self.config.statuses_global[c]
				});

		$('#ist_statuses').jqxListBox({
			theme: cargohub.jqxtheme,
			source: statuses_source,
			width: 248, height: 222,
			valueMember: "key", displayMember: "text",
			checkboxes: true,
			allowDrag: true, allowDrop: true,
			filterable: true, searchMode: 'containsignorecase'
		});

		// Selectors for initial status for "incoming" or "self create" modes
		$("#ist_settings_irs").jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: statuses_source,
			width: 248, height: 23,
			dropDownHeight: 222,
			valueMember: "key", displayMember: "text",
			filterable: true, searchMode: 'containsignorecase'
		});
		if(self.config && self.config.settings && self.config.settings.init && self.config.settings.init.recv) {
			for(c=0; c< statuses_source.length; c++) {
				if(statuses_source[c]['key'] == self.config.settings.init.recv.stat)
					$('#ist_settings_irs').jqxDropDownList('selectIndex', c);
			}
		}

		$("#ist_settings_iss").jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: statuses_source,
			width: 248, height: 23,
			dropDownHeight: 222,
			valueMember: "key", displayMember: "text",
			filterable: true, searchMode: 'containsignorecase'
		});

		// select the index for the active initial status ( ist settings ) (edit or init mode)
		if(self.config && self.config.settings && self.config.settings.init && self.config.settings.init.self) {
			for(c=0; c< statuses_source.length; c++) {
				if(statuses_source[c]['key'] == self.config.settings.init.self.stat)
					$('#ist_settings_iss').jqxDropDownList('selectIndex', c);
			}
		}

		/* Priority list selector
		// Build the array for the status list box
		// 1 - Load from "priorities" the ones which are active, and mark them as "checked", in the order they appear
		for(c=0; c< self.config.priorities.length; c++) {
			priorities_source.push({
				"key": self.config.priorities[c],
				"text": self.config.priorities_global[self.config.priorities[c]],
				"checked": true // Metavalue
			});
		} */
		// 2 - Load the rest as inactive
		for(c in self.config.priorities_global)
			if(!self.config.priorities_ex[c])
				priorities_source.push({
					"key": c,
					"text": self.config.priorities_global[c]
				});

		$('#ist_priorities').jqxListBox({
			theme: cargohub.jqxtheme,
			source: priorities_source,
			width: 248, height: 222,
			valueMember: "key", displayMember: "text",
			checkboxes: true,
			allowDrag: true, allowDrop: true
//			filterable: true, searchMode: 'containsignorecase'
		});

		// Selectors for initial priority for "incoming" or "self create" modes
		$("#ist_settings_irp").jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: priorities_source,
			width: 248, height: 23,
			dropDownHeight: 222,
			valueMember: "key", displayMember: "text"
		});

		if(self.config && self.config.settings && self.config.settings.init && self.config.settings.init.recv) {
			for(c=0; c< priorities_source.length; c++) {
				if(priorities_source[c]['key'] == self.config.settings.init.recv.prio)
					$('#ist_settings_irp').jqxDropDownList('selectIndex', c);
			}
		}

		$("#ist_settings_isp").jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: priorities_source,
			width: 248, height: 23,
			dropDownHeight: 222,
			valueMember: "key", displayMember: "text"
		});
		if(self.config && self.config.settings && self.config.settings.init && self.config.settings.init.self) {
			for(c=0; c< priorities_source.length; c++) {
				if(priorities_source[c]['key'] == self.config.settings.init.self.prio)
					$('#ist_settings_isp').jqxDropDownList('selectIndex', c);
			}
		}

		/* Group */

		// the group usage selector (All implemented group plugins)
		$("#ist_settings_group").jqxDropDownList({
			source: group_source,
			displayMember: "text",
			valueMember: "k",
			width: 248, height: 23,
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

			$("#ist_group_ids").jqxDropDownList({
				source: group_ids_source,
				placeHolder: "(Loading groups)"
			});
		});

		// Choose the existing groups automatically (with a tiny select to allow proper initialization of the dropdown)
		setTimeout(function() {
			if(self.config && self.config.settings && self.config.settings.group_type)
				for(c=0; c< group_source.length; c++) {
					if(group_source[c].k == self.config.settings.group_type)
						$('#ist_settings_group').jqxDropDownList('selectIndex', c);
				}
		}, 1);

		// The group filter, loaded dynamically and depending on group set
		$("#ist_group_ids").jqxDropDownList({
			theme: cargohub.jqxtheme,
			checkboxes: true,
			displayMember: "name", valueMember: "id",
			width: 248, height: 23,
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
						var checked = $("#ist_group_ids").jqxDropDownList("getCheckedItems"), string = '', c = 0;
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
	},

	// Types of fields (direct or plugin-based)
	ft_options = [
		{value: "text", text: "Text"},
		{value: "enum", text: "Single choice"},
		{value: "set", text: "Multiple choices"},
		{value: "number", text: "Numeric value"},
		{value: "date", text: "Date value"},
		{value: "money", text: "Monetary value"}, // plugin
		{value: "awb", text: "Air Waybill"}, // plugin
		{value: "airport", text: "World Airport"} // plugin
	],

	c_section = null, $c_section_div = null,

	/**
	 * Assambles the proper HTML and events for a new line of custom fields
	 */
	create_Xfield_Line = function(init_data)
	{
		if(!init_data) {
			// When init_data not present, the field is not added to a "section" but instead at the end (outside sections)
			c_section = null;
			init_data = {
				n: "Field "+xfline_counter+" name",
				add: true
			};
		}
		init_data.idx = xfline_counter;

		var $new_xfield = $($("#template_xfield_row").html().present(init_data));

		// jqWidgetize the new xfield line
		$new_xfield.find("> label").jqxCheckBox({ enableContainerClick: false, height: 25, theme: cargohub.jqxtheme});

		// discover the selected index (if it exists)
		var options_config = {
			source: ft_options,
			displayMember: "text", valueMember: "value",
			width: 148, height: 25,
			theme: cargohub.jqxtheme,
			autoDropDownHeight: true
		};
		if(init_data.t) {
			for(c=0; c<ft_options.length; c++)
				if(init_data.t == ft_options[c].value)
					options_config.selectedIndex = c;
		}

		$new_xfield.find("#ist_xf_"+init_data.idx+"_t").jqxDropDownList(options_config);
		$new_xfield.find("#ist_xf_"+init_data.idx+"_o, #ist_xf_"+init_data.idx+"_cd").jqxButton({height: 27, theme: cargohub.jqxtheme });
		$new_xfield.data("options", init_data.o||{});
		$new_xfield.data("choices", init_data.cd||[]);

		// Sections system:
		// - If c_section is empty, create the default section
		// - If section is present and c_section != init_data.s, create a new navigation bar
		// - Insert the row to the new or existing section

		if(!c_section) {
			if(!init_data.add) {
				// Add header
				if(init_data.s) {
					$("#xfd-sections").append("<div>"+ init_data.s + ":</div>\n");
					c_section = init_data.s;
				} else {
					$("#xfd-sections").append("<div>Without a section:</div>\n");
					c_section = true;
				}

				// Add body
				$c_section_div = $("<div><ul></ul></div>");
				$("#xfd-sections").append($c_section_div);
			}
		} else {
			// Section created, check if init_data.s is different, in which case create new section. If not, new fields will simply be added to last existing section
			if(init_data.s && init_data.s != c_section) {
				// Add new header
				$("#xfd-sections").append("<div>"+ init_data.s + ":</div>\n");
				c_section = init_data.s;

				// Add new body
				$c_section_div = $("<div><ul></ul></div>");
				$("#xfd-sections").append($c_section_div);
			}
		}

		if(init_data.add) {
			$("#xfd-newfields-header").show();
			$("#xfd-newfields").before($new_xfield);
		} else
			$c_section_div.find('ul').append($new_xfield);

		// append the generated line to the form
//		$("#xfd_end").before($new_xfield);

		// done, don't reuse the same idx
		xfline_counter++;
	},

	/**
	 * Removes the custom field lines that were picked using the checkboxes in the labels
	 */
	delete_Xfield_Lines = function()
	{
		if(confirm("Are you sure ?"))
			$("li.xfline label.jqx-checkbox").each(function(i, el) {
				if($(el).jqxCheckBox("checked")) {
					// save which field ID is about to be removed, so the backend knows about this as well when submitting
					xfdeleted.push($("#ist_xf_"+$(el).parent().data('idx')+"_id").val());
					// ... and delete the line
					$(el).parent().remove();
				}
			});
	},

	/**
	 * The custom fields area, one line per field
	 */
	setup_Xfields = function()
	{
		// the "Add another custom field" button
		$("#new_xfield").on("click", function() { create_Xfield_Line(); });
		// the "Delete xfields" (with selected)
		$('#del_xfields').on("click", function() { delete_Xfield_Lines(); });

		// when edit/init data exists, use it, otherwise, create a "demo" line for new IST definitions by "clicking" once
		if(self.config && self.config.xfield_def) {
			for(c in self.config.xfield_def) {
				var line = self.config.xfield_def[c];
				line.fk = c;
				create_Xfield_Line(line);
			}
		} else {
			$("#new_xfield").trigger("click");
		}

		// Add the NavBar code
		$("#xfd-sections").jqxNavigationBar({
			theme: cargohub.jqxtheme,
			arrowPosition: 'left'
		});
	},

	setup_Options_Window = function($window, data_options)
	{
		$('label', $window).jqxCheckBox({ height: 25, theme: cargohub.jqxtheme});

		// set some checkboxes on if their name exists in the data_options
		for(var opt in data_options) {
			if(opt == 'fk' || opt == 's') // "fk" and "s" are not part of the normal "o" inputs
				continue;
			$("#ist_xf_o_"+opt, $window).jqxCheckBox("check");
		}

		if(data_options.fk)
			$("#ist_xf_fk", $window).jqxCheckBox("check");
		if(data_options.s)
			$("#ist_xf_s", $window).jqxCheckBox("check");

		$("div.num", $window).jqxNumberInput({ spinButtons: true, inputMode: 'simple', decimal: data_options.len||100, min: 5, max: 32000, width: 75, height: 23, theme: cargohub.jqxtheme, decimalDigits: 0 });

		$("#ist_xf_o_colr_value", $window).jqxDropDownList({ source: [
			"Default",
			"Currency Symbol",
			"Yes/No",
			"Center align",
			"Right align",
			"Relative time diff",
			"Only date, no time",
			"24hr Clock"
		], selectedIndex: data_options.colr||0, width: 150, height: 25, autoDropDownHeight: true, dropDownWidth: 160, theme: cargohub.jqxtheme });

		// The OK button
		$("#modal_options-window_ok", $window).off('click').on('click', function()
		{
			var options = {};

			$("#options > div > label", $window).each(function() {

				// the field key is a special option
				if(this.id == 'ist_xf_fk') {
					if($(this).jqxCheckBox('checked'))
						$("#ist_xf_"+active_row+"_fk").val($("#ist_xf_fk_value", $window).val());
					return;
				}
				// the section ID is also a special option
				if(this.id == 'ist_xf_s') {
					if($(this).jqxCheckBox('checked'))
						$("#ist_xf_"+active_row+"_s").val($("#ist_xf_s_value", $window).val());
					return;
				}

				// everything else
				var selector = this.id.split('_'), option = selector[3];
				if($(this).jqxCheckBox('checked')) {
					switch(option) {
					case 'def':
						options[option] = $("#ist_xf_o_def_value", $window).val();
						break;
					case 'len':
						options[option] = $("#ist_xf_o_len_value", $window).jqxNumberInput("val");
						break;
					case 'colw':
						options[option] = $("#ist_xf_o_colw_value", $window).val();
						break;
					case 'colr':
						options[option] = $("#ist_xf_o_colr_value", $window).jqxDropDownList("selectedIndex");
						break;
					default:
						options[option] = true;
					}
				}
			});

			$("#ist_xf_"+active_row).parent().data("options", options);
			$window.jqxWindow('close');
		});
	},

	setup_Windows = function()
	{
		// setup the Live event for all the "Options" and "Choices" buttons that exist in the form
		$(document).on("click", "li.xfline input[type='button']", function() {
			var selector = this.id.substr(7).split('_'),
				xfname = $("#ist_xf_"+selector[0]+"_n").val();
			active_row = selector[0];

			switch(selector[1]) {
			case 'o': // options window
				if(!popups.options) {
					cargohub.its.init_Modal_Window('options-window', null, {"cancel": "cancel"});
					popups.options = true;
				}

				var data_options = $(this).parent().parent().data("options"), tpl_data = clone(data_options);
				tpl_data.fk = data_options.fk = $("#ist_xf_"+active_row+"_fk").val();
				tpl_data.s = data_options.s = $("#ist_xf_"+active_row+"_s").val();
				tpl_data._itc = input_theme_classes;

				cargohub.its.open_Modal_Window('options-window', 380, 430, "Options for <b>"+xfname+"</b>", $("#template_options_content").html().present(tpl_data), setup_Options_Window, data_options);
				break;
			case 'cd': // choices window
				if(!popups.choices) {
					cargohub.its.init_Modal_Window('choices-window', null, {"cancel": "cancel"});
					popups.choices = true;
				}

				var data_options = data_choices = {
					cd: $(this).parent().parent().data("choices").join("\n")
				}, tpl_data = clone(data_options);
				tpl_data._itc = input_theme_classes;

				cargohub.its.open_Modal_Window('choices-window', 350, 400, "Choice list for <b>"+xfname+"</b>", $("#template_choices_content").html().present(tpl_data), function($window) {
					// The OK button
					$("#modal_choices-window_ok", $window).off('click').on('click', function() {
						var choices = $("#ist_xf_cd", $window).val().split("\n");

						// clean empty rows before saving choices
						for(var c=0; c<choices.length; c++)
							if(choices[c].length == 0) {
								choices.splice(c,1);
								c--;
							}

						$("#ist_xf_"+active_row).parent().data("choices", choices);
						$window.jqxWindow('close');
					});
				});
				break;
			}
		});
	}
	;
	this._public({
		/**
		 * Section initialization
		 */
		init: function()
		{
			setup_Toolbar();
			// Buttons on the form (options, choices)
			$("form.vertiform input.command").jqxButton({ theme: cargohub.jqxtheme });

			// Setup the form's components
			setup_Stat_Prio_Group();
			setup_Xfields();
			setup_Windows();
		}
	});
});