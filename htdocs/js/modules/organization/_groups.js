/**
 * Module "Organization Manager", Code for the "Groups" tab
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2019.09.23
 */
cargohub.organization_groups = donrey.BASE._extend(function()
{
	// private
	var self = this,
		c = null,
		master = cargohub.organization_manager,

	// List of selected row indexes (necessary for Edit/Unlink operations)
	select_indexes = [],
	groups_selection_ops_visible = false,

	// Data Sources 
	grouptypes_source = [
		{k: 'general', text: "General Category - Multiple purposes", short_text: "Category"},
		{k: 'airline', text: "Name of an Airline - Linked to IATA code", short_text: "Airline"},
		{k: 'marine', text: "Shipping Line or Agent for sea freight", short_text: "Shipping line/Agent"},
		{k: 'dept', text: "Organization department name", short_text: "Department"},
		{k: 'cert', text: "License or Certification type", short_text: "License type"},
		{k: 'customer', text: "Primary Customer", short_text: "Customer"},
		{k: 'area', text: "Application area", short_text: "Area"},
		{k: 'class', text: "Classification of item", short_text: "Item class"},
		{k: 'training', text: "Training Group", short_text: "Training"},
		{k: 'asset', text: "Asset", short_text: "Asset"}
	],

	groups_source = new $.jqx.dataAdapter({
		url: "/manager/organization?action=get_available_groups",
		data: {},
		datatype: "json",
		datafields: [
			{name: 'id', type: 'number' },
			{name: 'name' }
		],
		id: 'id'
	}),

	/**
	 * Setup the window used to find and link one or more groups to the organization
	 */
	setup_GroupsLink = function($window)
	{
		var $grp_select = $("#grp-select", $window); // The drop-down-list with checkboxes

		$('#modal_groups-link_ok', $window).jqxButton({disabled: true});

		$("#grp-type", $window).jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: grouptypes_source,
			displayMember: "text", valueMember: "k",
			width: 322, height: 22, autoDropDownHeight: true
		})
		/**
		 * Event: When a type is selected, the system reads the groups for the 2nd dropdown, but only those who aren't already connected.
		 * When the groups have been returned, activate the dropdown and the multi-select buttons and allow submitting the form.
		 */
		.on('select', function(event) {
			groups_source._source.data = {
				type: event.args.item.value
			};

			$grp_select.jqxDropDownList({
				source: groups_source,
				placeHolder: "(Loading groups)"
			});
		});

		$grp_select.jqxDropDownList({
			theme: cargohub.jqxtheme,
			checkboxes: true,
			displayMember: "name", valueMember: "id",
			width: 322, height: 23,
			placeHolder: "(Waiting for group set selector)",
			disabled: true
		})
		.on('bindingComplete', function() {

			if(groups_source.records.length == 0) {
				$(this).jqxDropDownList({
					placeHolder: "(No other groups available)"
				});
				return;
			}

			$(this).jqxDropDownList({
				dropDownHeight: groups_source.records.length> 6? 153: null,
				autoDropDownHeight: groups_source.records.length<= 6? true: false,
				selectionRenderer: function(orig,index,str) {
					var string = index==-1? function() {
						$('#modal_groups-link_ok', $window).jqxButton({disabled: true}).jqxButton('render');
						return "Please select at least one group!";
					}(): function() {
						var checked = $grp_select.jqxDropDownList("getCheckedItems"), string = '', c = 0;
						if(checked.length <= 1) {
							for(; c< checked.length; c++)
								string+= (c>0? ', ': '') + checked[c].label;
						} else if(checked.length == groups_source.records.length)
							string = "All available groups selected";
						else
							string = checked.length + " out of " + groups_source.records.length + " groups selected";

						// Turn on OK button if there's at least one selected
						$('#modal_groups-link_ok', $window).jqxButton({disabled: false}).jqxButton('render');
						return string;
					}();
					return "<span style='display: block; width: 278px; text-overflow: ellipsis; margin-top: 3px; background-color: transparent;'>"+string+"</span>";
				},
				disabled: false
			});

			$(this).jqxDropDownList({placeHolder: "Select the groups for connecting"});
			// Turn on the multi-select buttons
			$("#grp-select-commands input", $window).each(function() {
				$(this).jqxButton({disabled: false}).jqxButton('render');
			});
		});

		$("#grp-select-commands input", $window).each(function() {
			$(this).jqxButton({
				theme: cargohub.jqxtheme,
				disabled: true
			})
			/**
			 * One of the group multi-selection buttons is pressed
			 */
			.on("click", function() {
				switch($(this).data('cmd')) {
					case 'check_all':
						$grp_select.jqxDropDownList('checkAll');
						break;
					case 'clear_all':
						$grp_select.jqxDropDownList('uncheckAll');
						break;
					// The invert command is somewhat slow as it goes item by item. For large number of groups it might take several seconds to execute!
					case 'check_invert':
						var items = $grp_select.jqxDropDownList('getItems');
						for(c=0; c< items.length; c++)
							$grp_select.jqxDropDownList(items[c].checked? 'uncheckIndex': 'checkIndex', c);
						break;
				}
			});
		});


		// Setup the action for "OK" button
		$('#modal_groups-link_ok', $window).off('click').on('click', function()
		{
			// submit the add/edit form using ajax
			$('.modal_content > form', $window).ajaxSubmit({
				dataType: 'json',
				success: function(x) {
					if(x.success) {
						// Close the windows, reload the grid and display the update message
						$window.jqxWindow('close');
						$("#tab_groups #groups-grid").jqxGrid('updatebounddata');
						cargohub.alert(x.response, "Success !", "thumb_up.png");
					} else {
						cargohub.alert(x.reason, "Message from server", "information.png");
					}
				}
			});
		});
	},

	setup_GroupsEdit = function($window, cfg)
	{
		// The two flags: Create / Receive - Switchbuttons
		// - If only one row selected they are initialized with the state of that row. If multiple, it always starts to ON, unless ALL selected rows are on off (preffer on)
		var f_create_state = false, f_receive_state = false;

		if(select_indexes.length == 1) {
			// When only one row is selected, f_create and f_receive initial value is of that row
			f_create_state = cfg.rows[0].f_create;
			f_receive_state = cfg.rows[0].f_receive;
		} else
			// When more rows are selected, the two states become OFF only if ALL rows have it as OFF
			for(c = 0; c< cfg.rows.length; c++) {
				f_create_state |= cfg.rows[c].f_create;
				f_receive_state |= cfg.rows[c].f_receive;
			}

		$('#grp-f_create', $window).jqxSwitchButton({
			theme: cargohub.jqxtheme,
			height: 23, width: 81,
			checked: f_create_state
		});

		$('#grp-f_receive', $window).jqxSwitchButton({
			theme: cargohub.jqxtheme,
			height: 23, width: 81,
			checked: f_receive_state
		});

		// Setup the action for "OK" button
		$('#modal_groups-edit_ok', $window).off('click').on('click', function()
		{
			// submit the add/edit form using ajax
			$('.modal_content > form', $window).ajaxSubmit({
				data: {
					row_ids: cfg.row_ids.join(','),
					"grp[f_create]": $('#grp-f_create', $window).val() == 1? 1: 0,
					"grp[f_receive]": $('#grp-f_receive', $window).val() == 1? 1: 0
				},
				dataType: 'json',
				success: function(x) {
					if(x.success) {
						// Close the windows, reload the grid and display the update message
						$window.jqxWindow('close');
						$("#tab_groups #groups-grid").jqxGrid('updatebounddata');
						cargohub.alert(x.response, "Success !", "thumb_up.png");
					} else
						cargohub.its.http_fail(null, "Manager.Organization::Update_Groups");
				},
				error: cargohub.its.http_fail
			});
		});
	},

	/**
	 * Does various actions on selected row(s)
	 */
	action_onSelected = function(command)
	{
		var bound_rows = $("#tab_groups #groups-grid").jqxGrid('getboundrows'), row_ids = [],
			selected_rows = [],
			tpl_data = {_itc: cargohub.input_theme_classes};

		if(select_indexes.length == 0)
			return cargohub.alert("No rows are currently selected. Please select at least one.");
		else
			for(c=0; c<select_indexes.length; c++) {
				row_ids.push(parseInt(bound_rows[select_indexes[c]]['uid']));
				selected_rows.push(bound_rows[select_indexes[c]]);
			}

		// If editing just one, comment gets a default - Otherwise empty (which doesn't change existing comment)
		if(row_ids.length == 1)
			tpl_data.comment = selected_rows[0].comment;

		switch(command.op) {
		// shows the Record Overview and Operations module /record/process
		case 'groups-edit':
			cargohub.components.modal.create('groups-edit', {
				btn_roles: {cancel: "cancel"}
			});

			cargohub.components.modal.open('groups-edit', {
				width: 488, height: 190,
				title: "Edit selected group(s)",
				content: $("#window-groups_edit").html().present(tpl_data),
				callback: setup_GroupsEdit,
				cb_config: {
					row_ids: row_ids,
					rows: selected_rows
				}
			}, self);
			break;

		case 'groups-unlink':
			if(!confirm("Are you sure you want to disconnect "+ row_ids.length+ " groups ?\nThe operation is reversible, you can reattach them later."))
				return;

			$.post("?action=detach_groups", {
				row_ids: row_ids.join(',')
			}, function(x) {
				if(x.success) {
					cargohub.alert(x.response, "Success !", "thumb_up.png");
					$("#tab_groups #groups-grid").jqxGrid('clearselection');
					$("#tab_groups #groups-grid").jqxGrid('updatebounddata');
				} else
					cargohub.its.http_fail(null, "Manager.Organization::delete_template");
			})
			.fail(cargohub.its.http_fail);
			break;

		case 'secu-search':
			if(row_ids.length > 1)
				return cargohub.alert("Currently it's only possible to search access overrides for a single data group.");

			cargohub.components.modal.create('secu-search', {
				buttons: {ok: "Close"}
			});

			cargohub.components.modal.open('secu-search', {
				width: 750, height: 550,
				title: "Security search results",
				content: $("#window-secu_search").html(),
				callback: function($window, cfg)
				{
					$("#secu_search_container", $window).load("?action=Secu_Search", {
						group_id: cfg.group_id,
						group_name: cfg.group_name
					});
				},
				cb_config: {
					group_id: row_ids[0],
					group_name: bound_rows[select_indexes[0]].name
				}
			}, self);
			break;
		}
	},

	/**
	 * Initialize the "Organization-connected Groups" sub-module toolbar
	 */
	init_Toolbar = function()
	{
		var tpl_data = {_itc: cargohub.input_theme_classes};

		$("#tab_groups .toolbar button")
		.jqxButton({ theme: cargohub.jqxtheme })
		/**
		 * Event: Toolbar button is clicked
		 */
		.on('click', function()
		{
			switch($(this).data('op')) {
			// Submit organization information with an ajax request
			case 'group-link':
				cargohub.components.modal.create('groups-link', {
					btn_roles: {cancel: "cancel"}
				});

				cargohub.components.modal.open('groups-link', {
					width: 488, height: 270,
					title: "Connect one or more data groups",
					content: $("#window-groups_link").html().present(tpl_data),
					callback: setup_GroupsLink
				}, self);
				break;

			// (Re)counts the number of records for all the groups the organization is connected to. It might be a heavy operation if the organization has a lot of records, but otherwise it should go quick.
			case 'group-count':
				$.post("?action=count_g2o_elements", function(x) {
					if(x.success) {
						cargohub.alert(x.response, "Success !", "thumb_up.png");
						// refresh the table as the data in the source has changed
						$("#tab_groups #groups-grid").jqxGrid('updatebounddata');
					} else {
						cargohub.alert(x.reason, "Message from server", "events.png");
					}
				});
				break;

			// Edit group-to-organization config (Create, Edit, Comment). Supports multiple rows at once... case bleed, same function is called.
			case 'groups-edit':
			// Disconnect a group from the organization. This doesn't remove any associations to records, however the organization won't be able to access them anymore.
			case 'groups-unlink':
			// Security search for selected group (see which departments and users have overrides for the selected group)
			case 'secu-search':
				action_onSelected($(this).data());
				break;

			case 'groups-unselect':
				$("#tab_groups #groups-grid").jqxGrid('clearselection');
				grid_OnUnselect();
				break;
			}
		});
	},

	/**
	 * Event: When a row is selected, the list of selected Indexes is updated. If a row is first selected, the Edit/Disconnect buttons appear
	 */
	grid_OnSelect = function()
	{
		select_indexes = $(this).jqxGrid('getselectedrowindexes');

		if(select_indexes.length > 0 && groups_selection_ops_visible == false) {
			donrey.cancel_Delay('hide_bar_groups-selection-ops');

			$('#groups-selection-ops button').jqxButton({disabled: false});
			$('#groups-selection-ops').fadeIn();
			groups_selection_ops_visible = true;
		}
	},

	/**
	 * Event: When a row is unselected, the list of selected Indexes is updated. If the list is empty, the Edit/Disconnect buttons are hidden
	 */
	grid_OnUnselect = function()
	{
		select_indexes = $("#tab_groups #groups-grid").jqxGrid('getselectedrowindexes');

		if(select_indexes.length == 0 && groups_selection_ops_visible == true) {
			$('#groups-selection-ops button').jqxButton({disabled: true});
			donrey.single_Delay('hide_bar_groups-selection-ops', function() {
				$('#groups-selection-ops').fadeOut();
			}, 500);
			groups_selection_ops_visible = false;
		}
	},

	/**
	 * The groups listing grid
	 */
	init_Grid = function()
	{
		var grid_dataAdapter = new $.jqx.dataAdapter({
			url: '/manager/organization?action=Get_Groups',
			datatype: "json",
			datafields: [
				{name: 'id', type: 'number'},
				{name: 'type', type: 'string'},
				{name: 'name', type: 'string'},
				{name: 'count_el', type: 'number'},
				{name: 'f_receive', type: 'bool'},
				{name: 'f_create', type: 'bool'},
				{name: 'comment', type: 'string'}
			],
			id: 'id',
			// Initial Sorting
			sortcolumn: 'type',
			sortdirection: 'asc'
		}),
		grouptypes_list = function() {
			var list = [];
			for(c=0; c< grouptypes_source.length; c++) {
				list.push(grouptypes_source[c].k);
			}
			return list;
		}(),
		grid_columns = [
			{text: "Name", dataField: 'name', width: 250},
			{text: "Group Type", dataField: 'type', width: 130,
				filtertype: "checkedlist", filteritems: grouptypes_list,
				createfilterwidget: function (column, columnElement, widget) {
					widget.jqxDropDownList({
						dropDownWidth: 180,
						autoDropDownHeight: true,
						renderer: function (index, label, value) {
							return "<div>"+ (index>0?"<img style='float: left; margin-right: 4px;' src='/img/icons16/groups/"+grouptypes_source[index-1].k+".png' />" + grouptypes_source[index-1].short_text: value) + "</div>";
						},
						animationType: "fade"
					});
				},
				cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
					return "<div style='text-overflow: ellipsis; overflow: hidden; margin-top: 5px;' title='"+ value +"'><img src='/img/icons16/groups/" + grid_dataAdapter.records[row].type + ".png' style='margin: 0px 4px;' /><span style='position: absolute;'>" + value + "</span></div>";
				},
			},
			{text: "Records", dataField: "count_el", width: 70,
				align: "right", cellsalign: "right", filtertype: "number"},
			{text: "Create?", dataField: "f_create", width: 70, columntype: "checkbox",
				align: "center", cellsalign: "center", filtertype: "bool"},
			{text: "Receive?", dataField: "f_receive", width: 70, columntype: "checkbox",
				align: "center", cellsalign: "center", filtertype: "bool"},
			{text: "Comment", dataField: "comment"}
		];

		// Make the filter list

		$("#tab_groups #groups-grid").jqxGrid({
			theme: cargohub.jqxtheme,
			width: cargohub.fullwidth - 14, autoheight: true,
			source: grid_dataAdapter,
			columns: grid_columns, columnsmenu: false, columnsresize: true,
			pageable: true,	pagesize: 20, pagesizeoptions: [10, 20, 50, 100],
			sortable: true, filterable: true, showfilterrow: true,
			altrows: true, selectionmode: "multiplerowsextended"
		})
		/**
		 * Event: When a row is selected, the list of selected Indexes is updated. If a row is first selected, the Edit/Disconnect buttons appear
		 */
		.on('rowselect', grid_OnSelect)
		/**
		 * Event: When a row is unselected, the list of selected Indexes is updated. If the list is empty, the Edit/Disconnect buttons are hidden
		 */
		.on('rowunselect', grid_OnUnselect)
		/**
		 * Event: Doubleclicking on a row performs the security search for that row
		 */
		.on('rowdoubleclick', function() {
			$('div#tab_groups .toolbar button[data-op="secu-search"]').trigger("click");
		})
		/**
		 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
		 */
		.on('filter', function(event)
		{
			if(select_indexes.length > 0) {
				$(this).jqxGrid('clearselection');
				grid_OnUnselect();
			}
		});
	};

	this._public({
		/**
		 * Setup Tab - Admin-only module for configuring primary settings for an organization ( name, module access, "manager" user and presence in the Organization Search System )
		 */
		init: function()
		{
			init_Toolbar();
			init_Grid();
		}
	});

});