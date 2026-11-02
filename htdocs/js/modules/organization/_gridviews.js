/**
 * Module "Organization Manager", tab "Grid views"
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2019.09.23
 */
cargohub.organization_gridviews = cargohub.baseclass._instance(cargohub.baseclass.fullgrid, function() {
	// private
	var self = this,
	c = null,
	master = cargohub.organization_manager,

	selected_row = null,		// The data from the selected row (used by the editor)
	// Source for the template roles (The ones not yet fully functional are disabled
	visibility_source = [
		{k: 'user', text: "The view is only visible for the displayed user", short_text: "User only"},
		{k: 'dep', text: "The view is visible for all users in the department", short_text: "Department"},
		{k: 'org', text: "The view is visible for the entire organization", short_text: "Organization"}
	],

	/**
	 * Set up the grid view configuration modal
	 */
	setup_GridviewsEdit = function($window)
	{
		var visibility = null, departments_Active = false, department_Selected = false, users_Init = false,
		// Departments selector (dropdown)
		departments_Source = new $.jqx.dataAdapter({
			url: "/manager/department?action=get_list",
			data: {
				filter_membership: 1,
				org_id: master.config.id
			},
			datatype: "json",
			datafields: [
				{name: 'id', type: 'int'},
				{name: 'name', type: 'string'},
				{name: 'count_members', type: 'int'}
			],
			id: "id"
		}),
		$gvlink_dep = $("#gv-link-select-dep", $window),
		/**
		 * Activates the department selector and if in Update mode also selects the proper department (This in turn triggers selecting the user after the list is loaded)
		 */
		activate_Departments = function() {
			if(departments_Active)
				return;

			$gvlink_dep.jqxDropDownList({
				width: 380,
				source: departments_Source,
				placeHolder: "(Loading departments)",
				disabled: false
			});
		},
		// User selector (combobox)
		members_Source = new $.jqx.dataAdapter({
			datatype: "json",
			datafields: [
				{name: 'id', type: 'int'},
				{name: 'name', type: 'string'}
			],
			id: "id"
		}),
		$gvlink_user = $("#gv-link-select-user", $window),
		select_dep_on_bindingComplete = null;

		// The Departments selector
		$gvlink_dep.jqxDropDownList({
			theme: cargohub.jqxtheme,
			displayMember: "name", valueMember: "id",
			placeHolder: "(loading...)",
			width: 380, dropDownWidth: 380,
			autoDropDownHeight: true, animationType: "fade", enableBrowserBoundsDetection: true,
			// Member count for department appears on the right side of the rows
			renderer: function(index, label, value) {
				return '<span class="gray right">('+ departments_Source.records[index].count_members+' members)</span>'+ label;
			},
			disabled: true
		})
		/**
		 * Event: The department list finished loading, set the currently active department if assign mode is "department"
		 */
		.on('bindingComplete', function() {
			$(this).jqxDropDownList({
				placeHolder: selected_row.visibility == 'user'? "(Select only if you want to change the user)": "Select department:"
			});

			if(selected_row.visibility == 'dep')
				select_dep_on_bindingComplete = selected_row.link_id;

			if(select_dep_on_bindingComplete)
				for(var c=0; c< departments_Source.records.length; c++)
					if(departments_Source.records[c].id == select_dep_on_bindingComplete) {
						$(this).jqxDropDownList('selectIndex', c);
						break;
					}
			select_dep_on_bindingComplete = null;
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

				department_Selected = true;
			}
			$gvlink_user.jqxComboBox('clearSelection');
			$gvlink_user.jqxComboBox({
				source: members_Source,
				placeHolder: "Type to search or click arrow to show all",
				disabled: false
			});
		});

		$gvlink_user.jqxComboBox({
			theme: cargohub.jqxtheme,
			searchMode: "containsignorecase",
			autoComplete: true,	minLength: 0,
			displayMember: "name", valueMember: "id",
			placeHolder: selected_row.visibility == 'user'? selected_row.link_name: "(Select a department first)",
			width: 380, height: 24, dropDownWidth: 380, dropDownHeight: 220,
			animationType: "fade", enableBrowserBoundsDetection: true,
			disabled: true
		})
		/**
		 * Event: The user list has been loaded; If the current link is an user, auto-select it
		 */
		.on('bindingComplete', function() {
			// Only at first time initializing ...
			if(users_Init) {
				for(c=0; c< members_Source.records.length; c++)
					if(members_Source.records[c].id == selected_row.link_id)
						$gvlink_user.jqxComboBox('selectIndex', c);
				users_Init = false;
			}
		});

		// The radiobuttons for visibility
		$("#gv-visibility > div", $window).each(function() {
			var option_data = $(this).data();
			$(this).jqxRadioButton({
				theme: cargohub.jqxtheme, height: 16,
				groupName: "visibility",
				checked: false,
				disabled: option_data.disabled
			})
			/**
			 * When one of the visibility radiobuttons is checked, other controls are changed
			 */
			.on('checked', function() {
				visibility = option_data.value;
				activate_Departments();

				switch(visibility) {
				case 'user':
					// Show the department selector for filtering users (based on their membership) and also the user search ComboBox
					$gvlink_dep.parent().parent().fadeIn();
					$gvlink_user.parent().parent().fadeIn();

					if(departments_Active)
						$gvlink_dep.jqxDropDownList({
							placeHolder: selected_row.visibility == 'user'? "(Select only if you want to change the user)": "Select department:"
						});
					break;
				case 'dep':
					// Show the department selector (allowing to relocate the view to another department)
					$gvlink_dep.parent().parent().fadeIn();
					$gvlink_user.parent().parent().fadeOut();

					if(departments_Active)
						$gvlink_dep.jqxDropDownList({
							placeHolder: "Select department:"
						});
					break;
				case 'org':
					// Hide both controls, there is nothing to select
					$gvlink_dep.parent().parent().fadeOut();
					$gvlink_user.parent().parent().fadeOut();
					break;
				}
			});

			if(option_data.value == selected_row.visibility)
				$(this).jqxRadioButton('check'); // This should also trigger the "checked" event
		});

		if(selected_row.visibility == 'user')
			users_Init = true;

		// Special-Settings related (below the HR):

		$('#gvss-width', $window).jqxSlider({
			theme: cargohub.jqxtheme,
			width: 255, height: 25,
			mode: 'fixed',
			min: 950, max: 4070, step: 10,
			value: selected_row.width,
			showButtons: false,
			ticksFrequency: 100, ticksPosition: 'bottom',
			tooltip: true
		});

		// Clicking on the recommended width sets the Screen width to that value:
		$('#gvss-screensizes b', $window).on('click', function() {
			$('#gvss-width', $window).val($(this).text());
		});

		// Add tooltips for relevant elements
		$(".info-tip", $window).each(cargohub.components.ui.add_Tooltip);

		// Setup the action for "OK" button
		$('#modal_gv-edit_ok', $window).off('click').on('click', function()
		{
			var request = {
				id: selected_row.uid,
				name: $.trim($('#gv-name', $window).val()),
				width: $('#gvss-width', $window).val(),
				visibility: visibility
			}, selected_item = null;

			switch(visibility) {
			case 'user':
				selected_item = $gvlink_user.jqxComboBox('getSelectedItem');
				if(selected_row.visibility != "user" && !department_Selected)
					return cargohub.alert("Choose a department and locate an user");

				if(department_Selected && !selected_item)
					return cargohub.alert("Please select an user from the list !");
				break;
			case 'dep':
				selected_item = $gvlink_dep.jqxDropDownList('getSelectedItem');
				if(!selected_item)
					return cargohub.alert("A department must be selected in this visibility mode");
				break;
			case 'org':
				// Nothing to do here!
				break;
			}

			if(selected_item) {
				request.link_id = selected_item.value;
				request.link_name = selected_item.label;
			}

			if(request.name.length < 3)
				return cargohub.alert("The view needs a name (min 3 characters)", "Invalid input");

			// Save module options in the session and reload. They should apply immediately
			$.post("?action=Update_Gridview", request, function(x) {
				if(x.success) {
					// Close the window, reload the grid and display the update message
					$window.jqxWindow('close');
					clear_Selection();
					$("#tab_gridviews #gridviews-grid").jqxGrid('updatebounddata', 'cells');
					cargohub.alert(x.response, "Success !", "thumb_up.png");
				} else
					cargohub.its.http_fail(null, "Manager.Organization::Update_Gridview");
			})
			.fail(cargohub.its.http_fail);
		});
	},

	/**
	 * Set up the grid view copy modal (Only contains one input to choose a new name for the copy
	 * - The copy can then be moved to another entity using the Configure command)
	 */
	setup_GridviewsCopy = function($window)
	{
		// Setup the action for "OK" button
		$('#modal_gv-copy_ok', $window).off('click').on('click', function()
		{
			var request = {
				orig_id: selected_row.uid,
				clone_name: $.trim($('#gv-copyname', $window).val())
			};

			if(request.clone_name == selected_row.name)
				return cargohub.alert("The copied view should have a different name than the original", "Invalid input");

			if(request.clone_name.length < 3)
				return cargohub.alert("The copied view needs a name (min 3 characters)", "Invalid input");

			// Save module options in the session and reload. They should apply immediately
			$.post("?action=Clone_Gridview", request, function(x) {
				if(x.success) {
					// Close the window, reload the grid and display the update message
					$window.jqxWindow('close');
					clear_Selection();
					$("#tab_gridviews #gridviews-grid").jqxGrid('updatebounddata', 'cells');
					cargohub.alert(x.response, "Success !", "thumb_up.png");
				} else
					cargohub.its.http_fail(null, "Manager.Organization::Clone_Gridview");
			})
			.fail(cargohub.its.http_fail);
		});
	},

	/**
	 * A different version of the copy function which instead allows selecting an organization to copy to.
	 * - The view is reset to "admin" visibility, which means it needs to be configured on the target organization after copying.
	 */
	setup_GridviewsCopy2org = function($window)
	{
		var dataAdapter_Admin_Orgs = new $.jqx.dataAdapter({
			url: "/admin/global/?action=get_related_orgs",
			data: {
				mode: "ist",
				key: selected_row.rt_key,
				sort: '`type_id` ASC, `name` ASC'
			},
			datatype: "json",
			datafields: [
				{name: 'id', type: 'number'},
				{name: 'type_id'},
				{name: 'name'}
			],
			id: "id"
		});

		// The organization dropdown selector - Shows only organizations which are connected to the record type for which the view is defined (Admin only)
		$('#gv-targetorg', $window).jqxComboBox({
			source: dataAdapter_Admin_Orgs,
			theme: cargohub.jqxtheme,
			displayMember: "name", valueMember: "id",
			searchMode: "containsignorecase",
			autoComplete: true,	minLength: 0,
			renderer: function (index, label, value) {
				var rec = dataAdapter_Admin_Orgs.records[index];
				return '<span style="float: right;">['+rec.type_id+']</span>'+ rec.name;
			},
			placeHolder: "(Loading organizations...)",
			width: 380, dropDownWidth: 380, dropDownHorizontalAlignment: "right",
			disabled: true
		})
		/**
		 * Event, the dropdown is loaded, show the default value and enable the control
		 */
		.on('bindingComplete', function() {
			$(this).jqxComboBox({
				placeHolder: "Type to search or click arrow to show all",
				dropDownHeight: dataAdapter_Admin_Orgs.records.length> 7? 184: null,
				autoDropDownHeight: dataAdapter_Admin_Orgs.records.length<= 7? true: false,
				disabled: false
			});
		});

		// Setup the action for "OK" button
		$('#modal_gv-copy2org_ok', $window).off('click').on('click', function()
		{
			var request = {
				orig_id: selected_row.uid,
				target_id: $('#gv-targetorg', $window).val()
			};

			if(!request.target_id)
				return cargohub.alert("Please find and select the target organization");

			// Save module options in the session and reload. They should apply immediately
			$.post("?action=Clone_Gridview", request, function(x) {
				if(x.success) {
					// Close the window, reload the grid and display the update message
					$window.jqxWindow('close');
					clear_Selection();
					$("#tab_gridviews #gridviews-grid").jqxGrid('updatebounddata', 'cells');
					cargohub.alert(x.response, "Success !", "thumb_up.png");
				} else
					cargohub.its.http_fail(null, "Manager.Organization::Clone_Gridview");
			})
			.fail(cargohub.its.http_fail);
		});

	},

	/**
	 * Initialize the "organization templates" sub-module toolbar
	 */
	init_Toolbars = function()
	{
		$("#tab_gridviews .toolbar button")
		.jqxButton({ theme: cargohub.jqxtheme })
		/**
		 * Event: Toolbar button is clicked
		 */
		.on('click', function()
		{
			switch($(this).data('op')) {
			// Open the configuration editor for the selected grid view
			case 'gv-edit':
				if(!selected_row)
					return cargohub.alert("Nothing currently selected !");

				cargohub.components.modal.create('gv-edit', {
					btn_roles: {cancel: "cancel"}
				});

				// Data additions
				selected_row._itc = cargohub.input_theme_classes;
				for(c=0; c<visibility_source.length; c++)
					if(selected_row.visibility == visibility_source[c].k) {
						selected_row.visibility_text = visibility_source[c].short_text;
						selected_row.visibility_tooltip = visibility_source[c].text;
					}

				cargohub.components.modal.open('gv-edit', {
					width: 550, height: 425,
					title: '<img class="inline-icon16" src="/img/icons16/ist/'+selected_row.rt_key+'.png"> Configure '+selected_row.rt_name+" view",
					content: $("#window-gridviews_edit").html().present(selected_row),
					callback: setup_GridviewsEdit
				}, self);
				break;

			// Copies existing view under a different name - No change to configuration
			case 'gv-copy':
				if(!selected_row)
					return cargohub.alert("Nothing currently selected !");

				cargohub.components.modal.create('gv-copy', {
					btn_roles: {cancel: "cancel"}
				});

				cargohub.components.modal.open('gv-copy', {
					width: 525, height: 145,
					title: '<img class="inline-icon16" src="/img/icons16/copy.png"> Duplicate view: '+selected_row.name,
					content: $("#window-gridviews_copy").html().present(selected_row),
					callback: setup_GridviewsCopy
				}, self);
				break;

			// Delete selected gridview after confirming
			case 'gv-del':
				if(!confirm("Are you sure you want to delete: "+selected_row.name))
					return;

				$.post("?action=Delete_Gridview", {
					id: selected_row.uid
				}, function(x) {
					if(x.success) {
						clear_Selection();
						$("#tab_gridviews #gridviews-grid").jqxGrid('updatebounddata', 'cells');
						cargohub.alert(x.response, "Success !", "thumb_up.png");
					} else
						cargohub.its.http_fail(null, "Manager.Organization::Delete_Gridview");
				})
				.fail(cargohub.its.http_fail);
				break;

			// Copies the view to another organization ( admin/supervisor only function ) - No change to configuration
			case 'gv-copy2org':
				if(!selected_row)
					return cargohub.alert("Nothing currently selected !");

				cargohub.components.modal.create('gv-copy2org', {
					btn_roles: {cancel: "cancel"}
				});

				cargohub.components.modal.open('gv-copy2org', {
					width: 550, height: 145,
					title: '<img class="inline-icon16" src="/img/icons16/company-building-icon.png"> Copy view '+selected_row.name+' to another organization',
					content: $("#window-gridviews_copy2org").html().present(selected_row),
					callback: setup_GridviewsCopy2org
				}, self);
				break;

			// Removes any sorting, filters and paging from the grid, bringing it to the default (sort by deadline ascending, not showing completed and failed statuses)
			case "grid-state-reset":
				if(!confirm("Are you sure you want to reset the grid ?"))
					return;

				$.post("/global/operations?action=Save_Grid_State", {
					grid_id: "org_gridviews",
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
			}
		});
	},

	/**
	 * Clears the grid selection and hides the control bar
	 */
	clear_Selection = function(event)
	{
		if(selected_row) {
			$("#tab_gridviews #gridviews-grid").jqxGrid('clearselection');
			selected_row = null;
			$('#gridviews-row-ops button').jqxButton({disabled: true});

			donrey.single_Delay('hide_bar_gridviews-row-ops', function() {
				$('#gridviews-row-ops').fadeOut(function() {
					$('#gridviews-message').show();
				});
			}, 500);
		}
		selected_row = null;
	},

	/**
	 * Setup and load the grid used to display the records grid views
	 */
	init_Grid = function()
	{
		// Prepare the grid (Configurable)
		var gridDataFields = [
			{name: 'id', type: 'int'},
			{name: 'name', type: 'string'},
			{name: 'rt_name', map: 'rt>name', type: 'string'},
			{name: 'rt_key', map: 'rt>key', type: 'string'},
			{name: 'visibility', type: 'string'},
			{name: 'link_id', type: 'int'},
			{name: 'link_name', type: 'string'},
			{name: 'link_xdata',type: 'string'},
			{name: 'source', type: 'string'},
			{name: 'utime', type: 'date'},
			{name: 'width', type: 'int'}
			// Extra information, not sure if useful...
//			{name: 'col_count', type: 'int'}
		],

		// Lists
		rt_list = [], rt_key_list = null, make_rt_key_list = false, visi_list = [],

		/**
		 * Assamble the lists before continuing with other variable/functions
		 */
		assemble_Lists = (function()
		{
			// Dropdown column: Visibility
			for(c=0; c< visibility_source.length; c++)
				visi_list.push(visibility_source[c].k);

			// Dropdown column: Record Type
			if(!cargohub.application.user_Is_Admin()) {
				rt_key_list = [];
				make_rt_key_list = true;
			}

			for(c=0; c< master.config.ist_source.length; c++) {
				rt_list.push(master.config.ist_source[c].name);
				if(make_rt_key_list)
					rt_key_list.push(master.config.ist_source[c].key);
			}
		})(),

		// Initialize the templates grid
		gridviews_Source = self.source({
			url: '?action=Get_Gridviews',
			datatype: "json",
			datafields: gridDataFields,
			data: {
				virtualmode: true,
				rt_def_filter: rt_key_list && rt_key_list.length? rt_key_list.join(','): ''
			},
			datatype: "json",
			root: "data",
			id: 'id',
			// server side operations - filtering and sorting:
			cache: false,
			filter: function() {
				$("#tab_gridviews #gridviews-grid").jqxGrid('updatebounddata', 'filter');
			},
			sort: function() {
				$("#tab_gridviews #gridviews-grid").jqxGrid('updatebounddata', 'sort');
			},
			beforeprocessing: function(source) {
				this.totalrecords = source.found_rows;
			},
			/**
			 * Client-Side pre-processing of filter data, because the way the grid sends it by default is total crap.
			 */
			formatData: cargohub.its.jqxGrid_FormatFilters
		});

		// Setup columns (fixed in this module)
		self.column({
			datafield: 'name', text: "View name"
		});
		self.column({
			datafield: 'rt_name', text: "Record Type", width: 240,
			filtertype: "checkedlist", filteritems: rt_list,
			createfilterwidget: function (column, columnElement, widget) {
				widget.jqxDropDownList({
					renderer: function (index, label, value) {
						return "<div>"+ (index>0?"<img style='float: left; margin-right: 4px;' src='/img/icons16/ist/"+master.config.ist_source[index-1].key+".png' />" + value: value) + "</div>";
					},
					animationType: "fade"
				});
			},
			cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
				return "<div style='text-overflow: ellipsis; overflow: hidden; margin-top: 5px;' title='"+ value +"'><img src='/img/icons16/ist/" + gridviews_Source.records[row].rt_key + ".png' style='margin: 0px 4px;' /><span style='position: absolute;'>" + value + "</span></div>";
			},
		});
		self.column({
			datafield: 'visibility', text: "Visibility", width: 125, cellsalign: 'center',
			filtertype: "checkedlist", filteritems: visi_list,
			createfilterwidget: function (column, columnElement, widget) {
				widget.jqxDropDownList({
					dropDownWidth: 115,
					autoDropDownHeight: true,
					animationType: "fade",
					renderer:  function (index, label, value) {
						return index>0? visibility_source[index-1].short_text: value;
					},
				});
			}
		});
		self.column({
			datafield: 'link_name', text: "Linked to (User, Dep)", width: 270,
			cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
				var is_dep = gridviews_Source.records[row].visibility == 'dep';
				return '<div style="overflow: hidden; text-overflow: ellipsis; padding-bottom: 2px; text-align: left; margin-right: 2px; margin-left: 4px; margin-top: 5px;' + (is_dep? "font-weight: bold;":"") +'">' + value + '</div>';
			},
		});
		self.column({text: "Updated", dataField: 'utime', width: 110, cellsformat: "MMM-dd HH:mm", align: "center", cellsalign: "center", filtertype: "range"});
		self.column({
			datafield: 'width', text: "Grid width", width: 75,
				align: "right", cellsalign: "right", filtertype: "number"
		});

		// Number of enabled columns for the saved view (indication of how "heavy" the view is)
//		self.column({
//			datafield: 'col_count', text: "# of cols", width: 65,
//				align: "right", cellsalign: "right", filtertype: "number"
//		});

		// Init the grid as persistent in session
		self.init_Grid($("#tab_gridviews #gridviews-grid"), {
			virtualmode: true, // virtual state
			rendergridrows: function(params) { return params.data; },
			pageable: true, pagesizeoptions: [10, 25, 50, 100], pagesize: 25
		}, {
			persistent: true,
			grid_id: 'org_gridviews'
		})
		/**
		 * Event: When a row is selected, the editing commands appear
		 */
		.on('rowselect', function (event)
		{
			var row = event.args.row;
			if(!selected_row) {
				donrey.cancel_Delay('hide_bar_gridviews-row-ops');

				$('#gridviews-row-ops button').jqxButton({disabled: false});
				$('#gridviews-message').hide();
				$('#gridviews-row-ops').fadeIn();
			}
			selected_row = row;
		})
		/**
		 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
		 */
		.on('filter', clear_Selection)
		/**
		 * Event: On doubleclicking, do the same as simple clicking then using the Edit button
		 */
		.on('rowdoubleclick', function() {
			$('#tab_gridviews .toolbar button[data-op="gv-edit"]').trigger("click");
		});
	}
	;

	this.init = function()
	{
		init_Toolbars();

		if(cargohub.application.user_Is_Admin()) {
			visibility_source.push({k: 'admin', text: "Not visible to users, only for management purposes", short_text: "Administrator"});
		}

		// Initial grid state
		if(!self.config.grid_state) {
			self.config.grid_state = {
				sort: {
					column: 'utime',
					direction: 'desc'
				}
			};
		}
		init_Grid();
	};
});