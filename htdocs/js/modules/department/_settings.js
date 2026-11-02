/**
 * Module "Department Settings", Code for the primary "Settings" tab
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2.9
 */
cargohub.department_settings = cargohub.baseclass._instance(cargohub.baseclass.fullgrid, function() {
	// private
	var self = this,
	c = null,

	selected_row = null,		// The data from the selected link (used by edit/delete link)

	/**
	 * The window that is used to setup a new dept. to depts. link for a record type
	 * - Contains record type selector
	 * - Contains a listbox with all other departments from the organization
	 */
	open_RecLinks = function(mode)
	{
		if(mode == 'edit' && selected_row.ldir == 'In')
			return cargohub.alert("Incoming links can only be edited at the source department");

		cargohub.components.modal.create('link-editor', {
			btn_roles: {cancel: "cancel"}
		});

		var title = mode=='create'?
			"Create new inter-department links":
			"Edit links for "+selected_row.rt_name,
		tpl_data = {
			_itc: cargohub.input_theme_classes,
			rt_name: mode=='edit'?	selected_row.rt_name: null
		};

		cargohub.components.modal.open('link-editor', {
			width: 500,	height: 250,
			title: title,
			content: $('#template_edit-reclinks').html().present(tpl_data),
			callback: function($window) // Setup the controls inside the window
			{
				if(mode=='create') {
					// The record type selector, only when creating
					$('#erl-rt_key', $window).jqxDropDownList({
						theme: cargohub.jqxtheme,
						source: self.config.ist_source,
						displayMember: "name", valueMember: "key",
						width: 308, height: 25,
						dropDownHeight: self.config.ist_source.length> 8? 195: null,
						autoDropDownHeight: self.config.ist_source.length< 8? true: false,
						renderer: function (index, label, value) {
							return "<div><img style='float: left; margin-right: 4px;' src='/img/icons16/ist/"+self.config.ist_source[index].key+".png' />" + label + "</div>";
						}
					});
				}

				// The departments list (all organization departments for now), checkboxes
				$('#erl-link_deps', $window).jqxDropDownList({
					theme: cargohub.jqxtheme,
					source: self.config.org_deps, checkboxes: true,
					displayMember: "name", valueMember: "id",
					width: 308, height: 25,
					dropDownHeight: self.config.org_deps.length> 7? 170: null,
					autoDropDownHeight: self.config.org_deps.length< 7? true: false,
					selectionRenderer: function(orig,index,str) {
						var string = index==-1? "No departments selected": function() {
							var checked = $('#erl-link_deps', $window).jqxDropDownList("getCheckedItems"), string = '', c = 0;
							if(checked.length <= 2) {
								for(; c< checked.length; c++)
									string+= (c>0? ', ': '') + checked[c].label;
							} else if(checked.length ==  self.config.org_deps.length)
								string = "All current departments are selected";
							else
								string = checked.length + " out of " +  self.config.org_deps.length + " departments selected";
							return string;
						}();
						return "<span style='display: block; width: 278px; text-overflow: ellipsis; margin-top: 3px; background-color: transparent;'>"+string+"</span>";
					}
				});

				if(mode=='edit') {
					var link_deps = selected_row.link_deps.split(',');

					// Set the current receive-enabled record types
					for(c=0; c<self.config.org_deps.length; c++)
						if($.inArray(self.config.org_deps[c].id.toString(), link_deps) > -1)
							$('#erl-link_deps', $window).jqxDropDownList('checkIndex', c);

					$('#erl-comment', $window).val(selected_row.comment);
				}

				// Setup the action for "OK" button (start with it disabled, enabled when user has been selected)
				$('#modal_link-editor_ok', $window).off('click').on('click', function()
				{
					var request = {
						src_dep: self.config.id,
						comment: $('#erl-comment', $window).val(),
						link_deps: $('#erl-link_deps', $window).val()
					};
					if(mode=='create') {
						request.rt_key = $('#erl-rt_key', $window).val();

						if(!request.rt_key)
							return cargohub.alert("A record type must be selected !");
					} else {
						request.rt_key = selected_row.rt_key;
						request.update = 1;
					}

					if(request.link_deps.length == 0)
						return cargohub.alert("Select at least one other department");

					$.post("?action=Store_RecLinks", request, function(x) {
						if(x.success) {
							$("#tab_settings #reclinks-matrix").jqxGrid('updatebounddata');
							$("#tab_settings #reclinks-matrix").jqxGrid('clearselection');
							cargohub.alert(x.response, "Success !", "thumb_up.png");
							$window.jqxWindow('close');
						} else
							cargohub.its.http_fail(null, "Department.Manager::link_deps");
					})
					.fail(cargohub.its.http_fail);

				});

			}
		});
	},

	/**
	 * Initialize the "department settings" sub-module toolbar
	 */
	init_Toolbar = function()
	{
//		var tpl_data = {_itc: cargohub.input_theme_classes};

		// Toolbar buttons
		$('#tab_settings .toolbar button')
		.jqxButton({ theme: cargohub.jqxtheme })
		.on('click', function(event) {
			switch($(this).data('op')) {
			// Updates the department information. Data is collected from the controls and submitted via AJAX
			case 'dep-update':
				$("#tab_settings form").ajaxSubmit({
					dataType: 'json',
					data: {
						id: self.config.id
					},
					beforeSubmit: function(formData, jqForm, options)
					{
						// setup the field checking object
						var f = {};
						for(c=0; c< formData.length; c++)
							f[formData[c].name] = formData[c].value;

						if(f['dep[name]'].length < 4)
							return cargohub.alert("The department must have a name (minimum 4 characters)", "Warning", "error.png");

						// a switchbutton doesn't provide a value to the form, so add them as hidden values
						formData.push({name: "dep[listed]", value: $('#tab_settings #features-listed').jqxSwitchButton('checked')});

						// For which record types and data groups is this department being used as possible target?
						formData.push({name: "dep[use4ist]", value: $('#tab_settings #features-use4ist').val()});
						formData.push({name: "dep[use4grp]", value: $('#tab_settings #features-use4grp').val()});
						return true;
					},
					success: function(x)
					{
						// when completed successfully, refresh the page
						if(x.success) {
							document.location.reload();
						} else {
							cargohub.alert(x.reason, "Message from server", "events.png");
						}
					},
					error: cargohub.its.http_fail
				});
				break;

			// Deletes a department which will disconnect all users and remove any access overrides
			case 'dep-del':
				cargohub.components.modal.delconfirm(function() {
					$.post("?action=delete", {
						id: self.config.id
					}, function(x) {
						if(x.success)
							document.location.reload();
						else
							cargohub.its.http_fail(null, "Department.Manager::delete");
					})
					.fail(cargohub.its.http_fail);
				}, "Are you REALLY sure you want to remove this department?");
				// "Any existing settings and access overrides for all users will be deleted,\nand the users will revert to organization-wide access level !"
				break;

			// Opens the modal which is creating a department to department link
			case 'reclink-add':
				open_RecLinks('create');
				break;
			// Changes list of target departments for one link entry (Can also be used only to see existing list of departments)
			case 'reclink-edit':
				open_RecLinks('edit');
				break;
			// Removes a link for the selected record type
			case 'reclink-del':
				if(selected_row.ldir == 'In')
					return cargohub.alert("Incoming links can only be removed at the source department");

				if(!confirm("Are you sure you want to remove this link?"))
					return;

				$.post("?action=Delete_RecLinks", {
					src_dep: self.config.id,
					rt_key: selected_row.rt_key
				}, function(x) {
					if(x.success) {
						$("#tab_settings #reclinks-matrix").jqxGrid('updatebounddata');
						cargohub.alert(x.response, "Success !", "thumb_up.png");
					} else
						cargohub.its.http_fail(null, "Department.Manager::delete_reclinks");
				})
				.fail(cargohub.its.http_fail);
				break;

			// Back to the organization settings, departments tab
			case 'back-to-org':
				document.location = "/manager/organization" + (cargohub.application.user_Is_Admin()? "?id=" + self.config.org_id: "") + "#deps";
				break;
			}
		});
	},

	/**
	 * Setup the other dropdowns and buttons in the tab (except for the links grid)
	 */
	init_ExtraControls = function()
	{
		// The name field
		$('#tab_settings #dep_name').val(self.config.name);

		// The list of all users in the company, to select a chief for this department
		$('#tab_settings #dep_chief_id').jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: self.config.members,
			displayMember: "name", valueMember: "id",
			width: 308, height: 25, autoDropDownHeight: true,
			promptText: self.config.count_members > 0 ? "Choose chief:": "Currently there are no members",
			disabled: self.config.count_members == 0
		});

		// Select the current chief
		for(c=0; c<self.config.members.length; c++) {
			if(self.config.members[c].id == self.config.chief_id)
				$('#tab_settings #dep_chief_id').jqxDropDownList('selectIndex', c);
		}

		// Is department listed for external submit ?
		$('#tab_settings #features-listed').jqxSwitchButton({
			theme: cargohub.jqxtheme,
			height: 25, width: 75,
			checked: self.config.listed
		}).on('change', function(event) {
			self.config.listed = event.args.check;
		});

		// The issue types selector (also used in ACLs tab)
		$('#tab_settings #features-use4ist').jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: self.config.ist_source, checkboxes: true,
			displayMember: "name", valueMember: "key",
			width: 308, height: 25,
			dropDownHeight: self.config.ist_source.length> 8? 195: null,
			autoDropDownHeight: self.config.ist_source.length< 8? true: false,
			renderer: function (index, label, value) {
				return "<div><img style='float: left; margin-right: 4px;' src='/img/icons16/ist/"+self.config.ist_source[index].key+".png' />" + label + "</div>";
			},
			selectionRenderer: function(orig,index,str) {
				var string = index==-1? "No record type selected": function() {
					var checked = $('#tab_settings #features-use4ist').jqxDropDownList("getCheckedItems"), string = '', c = 0;
					if(checked.length <= 2) {
						for(; c< checked.length; c++)
							string+= (c>0? ', ': '') + checked[c].label;
					} else if(checked.length ==  self.config.ist_source.length)
						string = "All record types are selected";
					else
						string = checked.length + " out of " +  self.config.ist_source.length + " record types selected";
					return string;
				}();
				return "<span style='display: block; width: 278px; text-overflow: ellipsis; margin-top: 3px; background-color: transparent;'>"+string+"</span>";
			}
		});
		// Set the current receive-enabled record types
		for(c=0; c<self.config.ist_source.length; c++)
			if($.inArray(self.config.ist_source[c].key, self.config.use4ist) > -1)
				$('#tab_settings #features-use4ist').jqxDropDownList('checkIndex', c);

		// The record groups selector (lisbox with checkboxes and search filter) - Dropdownlist not okay due to possibly large number of record types
		$('#tab_settings #features-use4grp').jqxListBox({
			theme: cargohub.jqxtheme,
			source: self.config.receive_groups,
			checkboxes: true,
			displayMember: "name", valueMember: "id", groupMember: "type",
			width: 308, height: 230,
			filterable: true, searchMode: 'containsignorecase',
			renderer: function(index, label, value) {
				if(value)
					return label;
				else
					return '&nbsp;&raquo;&nbsp'+ label[0].toUpperCase()+ label.substring(1);
			}
		});

		// Check the groups the department is supposed to receive (if any)
		var groups = $('#tab_settings #features-use4grp').jqxListBox('getItems');
		for(c=0; c<groups.length; c++) {
			if(groups[c].isGroup)
				continue;
			if($.inArray(groups[c].originalItem.id, self.config.use4grp) > -1) {
				$('#tab_settings #features-use4grp').jqxListBox('checkIndex', groups[c].visibleIndex);
			}
		}

		// The Clear/Invert buttons
		$('#tab_settings #use4grp-clear').jqxButton({
			theme: cargohub.jqxtheme
		})
		.on('click',function() {
			$('#tab_settings #features-use4grp').jqxListBox('uncheckAll');
		});

		$('#tab_settings #use4grp-invert').css('margin-left', '10px')
		.jqxButton({
			theme: cargohub.jqxtheme
		})
		.on('click',function() {
			for(c=0; c<groups.length; c++) {
				if(groups[c].isGroup)
					continue;
				if(groups[c].checked)
					$('#tab_settings #features-use4grp').jqxListBox('uncheckIndex', groups[c].visibleIndex);
				else
					$('#tab_settings #features-use4grp').jqxListBox('checkIndex', groups[c].visibleIndex);
			}
		});
	},

	/**
	 * The record auto-links grid
	 */
	init_RecLinks = function()
	{
		// Initialize the autolinks grid
		var reclinks_Source = self.source({
			url: '?action=Get_RecLinks',
			data: {
				dep_id: self.config.id
			},
			datatype: "json",
			datafields: [
				{name: 'ldir', type: 'string'}, // "In", "Out"
				{name: 'rt_id', type: 'int'},
				{name: 'rt_name', type: 'string'},
				{name: 'rt_key', type: 'string'},
				{name: 'link_deps', type: 'string'},
				{name: 'link_count', type: 'int'},
				{name: 'comment', type: 'string'}
			]
		}), rt_list = [];

		// Dropdown column: Record Type
		for(c=0; c< cargohub.department_manager.config.ist_source.length; c++)
			rt_list.push(cargohub.department_manager.config.ist_source[c].name);

		self.column({datafield: 'ldir', text: "Direction", width: 70, align: "center", cellsalign: "center"});
		self.column({
			datafield: 'rt_name', text: "Record Type", width: 220,
			cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
				return "<div style='text-overflow: ellipsis; overflow: hidden; margin-top: 5px;' title='"+ value +"'><img src='/img/icons16/ist/" + reclinks_Source.records[row].rt_key + ".png' style='margin: 0px 4px;' /><span style='position: absolute;'>" + value + "</span></div>";
			},
		});
		self.column({datafield: 'link_count', text: "Links", width: 50, align: "center", cellsalign: "center"});
		self.column({datafield: 'comment', text: "Comment"});

		// Init the grid as persistent in session
		self.init_Grid($("#tab_settings #reclinks-matrix"), {
			width: "99%",
			filterable: false, showfilterrow: false,
			pageable: true, pagesizeoptions: [10, 25, 50], pagesize: 10
		}, {
			persistent: false
		})
		/**
		 * Event: When a row is selected, the editing commands appear
		 */
		.on('rowselect', function (event) {
			var row = event.args.row;
			if(!selected_row) {
				donrey.cancel_Delay('hide_bar_reclinks-select-ops');

				$('#reclinks-select-ops button').jqxButton({disabled: false});
				$('#reclinks-select-ops').fadeIn();
			}
			selected_row = row;
		})
		/**
		 * Event: On doubleclicking, do the same as simple clicking then using the Edit button
		 */
		.on('rowdoubleclick', function() {
			$('#tab_settings .toolbar button[data-op="reclink-edit"]').trigger("click");
		});

	};

	/**
	 * Templates Tab - Various format output templates, used for viewing, printing and exporting issue data
	 */
	this.init = function()
	{
		init_Toolbar();
		init_ExtraControls();

		// Initial grid state
		if(!self.config.grid_state) {
			self.config.grid_state = {
				sort: {
					column: 'src_dep_name',
					direction: 'asc'
				}
			};
		}
		// Department access links
		init_RecLinks();
		// Tooltips
		$("#tab_settings .info-tip").each(cargohub.components.ui.add_Tooltip);
	};

});
