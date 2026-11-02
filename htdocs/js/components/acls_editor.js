/**
 * Access Control Lists Editor (used on the three levels - Organization, Department, User-2-Dep)
 * - Privileges editor (SwitchButtons)
 * - Access to Modules (DropDownList with CheckBoxes)
 * - Data Groups (Grid with editable CheckBox column) + Multi-select buttons
 *
 * @depend component:jqxDropDownList, jqxGrid(complete), jqxGrid.Edit, jqxSwitchButton
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoWave
 * @subpackage JavaScript Components
 * @version 2019.01.20
 */
cargohub.components.acls_editor = donrey.BASE._extend(function() {
	// private
	var self = this,
	master = null,			// Master object that initialized this component
	$c = null, 				// The DOM container which contains the editors
	// Configuration
	params = null,
	init_complete = false,
   
	// Data Sources 
	grouptypes_source = [
		{k: 'general', text: "General Category - Multiple purposes", short_text: "Category"},
		{k: 'airline', text: "Name of an Airline - Linked to IATA code", short_text: "Airline"},
		{k: 'marine', text: "Shipping Line or Agent for sea freight", short_text: "Sea Line/Agent"},
		{k: 'dept', text: "Organization department name", short_text: "Department"},
		{k: 'cert', text: "License or Certification type", short_text: "License type"},
		{k: 'customer', text: "Primary customer", short_text: "Customer"},
		{k: 'area', text: "Application area", short_text: "Area"},
		{k: 'class', text: "Classification of item", short_text: "Item class"},
		{k: 'training', text: "Training item", short_text: "Training"},
		{k: 'asset', text: "Asset", short_text: "Asset"}
	],

	/**
	 * Initialize privileges editor section ( a bunch of switchbuttons which can be used to activate or deactivate various functions in the system )
	 * @param privileges
	 */
	setup_Privileges = function(privileges)
	{
		// Initialize all Privileges checkboxes
		$(".privileges-array td > div", $c).each(function() {
			var priv_k = this.id.substr(5);
			$(this).jqxSwitchButton({
				theme: cargohub.jqxtheme,
				width: 80, height: 20,
				checked: $.inArray(priv_k, privileges) > -1
			});
		});

		// The NavigationBar widget used for showing and hiding sections (so it's not too big)

		$("#privileges-navbar", $c).jqxNavigationBar({
			theme: cargohub.jqxtheme,
			expandMode: "multiple",
			expandedIndexes: [0, 1, 2, 3, 4, 5], // Expand all by default, seems that keeping them closed causes confusion
			width: cargohub.fullwidth - 14
		});
	},

	/**
	 * Reads the data from all the "SwitchButtons" in the privileges editor and returns a list of privileges that were set to "On"
	 */
	read_Privileges = function()
	{
		var privs = [];
		$(".privileges-array td > div", $c).each(function() {
			if($(this).val())
				privs.push(this.id.substr(5));
		});
		return privs.join(',');
	},

	/**
	 * Updates the values of all the the switchbuttons that represent the privileges editor with new data.
	 */
	update_Privileges = function(privileges)
	{
		$(".privileges-array td > div", $c).each(function() {
			var priv_k = this.id.substr(5);
			$(this).jqxSwitchButton("val", $.inArray(priv_k, privileges) > -1);
		});
	},

	/**
	 * Initialize the checkbox-dropdownlist containing access to Record Types (or "Modules") (formely Issue Types)
	 * -- Note: This module expects the datasource for this control to be present in "master.config.ist_source"
	 * @param array ist
	 */
	setup_RecordTypes = function(ist)
	{
		if(!master.config || !master.config.ist_source)
			return false;

		// The Issue Type check-box-drop-down-list control
		var c = 0, ist_count = master.config.ist_source.length;
		$('#acls_access-ids_ist', $c).jqxDropDownList({
			theme: cargohub.jqxtheme,
			checkboxes: true,
			source: master.config.ist_source,
			promptText: "Choose modules",
			displayMember: "name", valueMember: "id",
			width: 310, height: 23,
			dropDownHeight: ist_count> 8? 195: null,
			autoDropDownHeight: ist_count< 8? true: false,
			renderer: function (index, label, value) {
				return "<div><img style='float: left; margin-right: 4px;' src='/img/icons16/ist/"+master.config.ist_source[index].key+".png' />" + label + "</div>";
			},
			selectionRenderer: function(orig,index,str) {
				var string = index==-1? "<span style='color: red;'>No modules are enabled!</span>": function() {
					var checked = $('#acls_access-ids_ist', $c).jqxDropDownList("getCheckedItems"), string = '', c = 0;
					if(checked.length <= 2) {
						for(; c< checked.length; c++)
							string+= (c>0? ', ': '') + checked[c].label;
					} else if(checked.length == master.config.ist_source.length)
						string = "All available modules enabled";
					else
						string = checked.length + " out of " + master.config.ist_source.length + " modules enabled";

					return string;
				}();
				return "<span style='display: block; width: 278px; text-overflow: ellipsis; margin-top: 3px; background-color: transparent;'>"+string+"</span>";
			},
		});
		// Select existing Issue Types in Access IDs for this organization
		for(; c< master.config.ist_source.length; c++) {
			if($.inArray(master.config.ist_source[c].id, ist) > -1)
				$('#acls_access-ids_ist', $c).jqxDropDownList('checkIndex', c);
		};
	},

	read_RecordTypes = function()
	{
		return $('#acls_access-ids_ist', $c).val() || null;
	},


	/**
	 * Initialize the grid which shows all the connected record groups. They can be turned on and off for users using the checkboxes in the first column
	 * -- Note: The module expects the datasource for this control to be present in "master.config.groups_source"
	 * @param array groups_enabled
	 */
	setup_GroupsGrid = function(groups_enabled)
	{
		if(!master.config || !master.config.groups_source)
			return false;
       if(master.config.groups_source1)
         master.config.groups_source=master.config.groups_source.concat(master.config.groups_source1);
         if(master.config.groups_source2)
master.config.groups_source=master.config.groups_source.concat(master.config.groups_source2);
 if(master.config.groups_source3)
master.config.groups_source=master.config.groups_source.concat(master.config.groups_source3);
if(master.config.groups_source4)
master.config.groups_source=master.config.groups_source.concat(master.config.groups_source4);
if(master.config.groups_source5)
master.config.groups_source=master.config.groups_source.concat(master.config.groups_source5);
if(master.config.groups_source6)
master.config.groups_source=master.config.groups_source.concat(master.config.groups_source6);
		var grid_dataAdapter = new $.jqx.dataAdapter({
		
		 
			localdata: master.config.groups_source,
			datatype: "array",
			datafields: [
				{name: 'id', type: 'int'},
				{name: 'access', type: 'bool'},
				{name: 'name', type: 'string'},
				{name: 'type', type: 'string'},
				{name: 'count_el', type: 'int'}
			],
			id: 'id'
		}), c = null, grouptypes_list = [];

		for(c=0; c< grouptypes_source.length; c++)
			grouptypes_list.push(grouptypes_source[c].k);

		// Insert the values for the "Access" field into the DataAdapter.records, so checkboxes are on or off
		for(c=0; c< master.config.groups_source.length; c++)
			master.config.groups_source[c].access = groups_enabled? groups_enabled.indexOf(master.config.groups_source[c].id) > -1: false;

		// Setup the Groups grid
		$('#acls_access-groups', $c).jqxGrid({
			theme: cargohub.jqxtheme,
			width: "100%", autoheight: true,
			editable: true,
			selectionmode: 'row', editmode: 'click',
			source: grid_dataAdapter,
			columns: [
				{text: 'Access', datafield: 'access', align: 'center', columntype: 'checkbox', filtertype: 'bool', width: 65 },
				{text: "Name", dataField: 'name', editable: false},
				{text: "Group Type", dataField: 'type', width: 130, editable: false,
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
						return "<div style='text-overflow: ellipsis; overflow: hidden; margin-top: 5px;' title='"+ value +"'><img src='/img/icons16/groups/" + master.config.groups_source[row].type + ".png' style='margin: 0px 4px;' /><span style='position: absolute;'>" + value + "</span></div>";
					},
				},
				{text: "Records", dataField: "count_el", width: 80, editable: false,
					align: "right", cellsalign: "right", filtertype: "number"}
			], columnsmenu: false, columnsresize: true,
			pageable: true,	pagesize: 10, pagesizeoptions: [10, 20, 50, 100],
			sortable: true, filterable: true, showfilterrow: true,
			altrows: true, selectionmode: "singlerow"
		})
		/**
		 * Event: Doubleclicking on a row switches access, like single-clicking on the Access checkbox
		 */
		.on('rowdoubleclick', function(event) {
			var access = $('#acls_access-groups', $c).jqxGrid('getcellvalue', event.args.rowindex, 'access');
			$('#acls_access-groups', $c).jqxGrid('setcellvalue', event.args.rowindex, 'access', !access);
		});

		$('#groups-select-commands > input', $c).each(function() {
			$(this).jqxButton({
				theme: cargohub.jqxtheme
			})
			/**
			 * One of the group multi-selection buttons is pressed
			 */
			.on("click", function() {
				var c = null, access = null;

				$('#acls_access-groups', $c).jqxGrid('beginupdate');

				switch($(this).data('cmd')) {
					case 'check_all':
						for(c=0; c< master.config.groups_source.length; c++)
							$('#acls_access-groups', $c).jqxGrid('setcellvalue', c, 'access', true);
						break;

					// Due to changes in the grid's API, now page must be considered when using "getdisplayrows"
					case 'check_visible':
						var
							filtered = $('#acls_access-groups', $c).jqxGrid('getdisplayrows'),
							paginginfo = $('#acls_access-groups', $c).jqxGrid('getpaginginformation'),
							firstrowidx = paginginfo.pagesize * paginginfo.pagenum,
							lastrowidx = filtered.length - 1;

						if(paginginfo.pagesize * (paginginfo.pagenum +1) <lastrowidx + 1)
							lastrowidx = paginginfo.pagesize * (paginginfo.pagenum +1) - 1;

						for(c=firstrowidx; c<= lastrowidx; c++)
							$('#acls_access-groups', $c).jqxGrid('setcellvalue', filtered[c].boundindex, 'access', true);
						break;

					// Sets all to off
					case 'clear_all':
						for(c=0; c< master.config.groups_source.length; c++)
							$('#acls_access-groups', $c).jqxGrid('setcellvalue', c, 'access', false);

						break;

					// Inverts existing selection (even in non-visible pages)
					case 'check_invert':
						for(c=0; c< master.config.groups_source.length; c++) {
							access = $('#acls_access-groups', $c).jqxGrid('getcellvalue', c, 'access');
							$('#acls_access-groups', $c).jqxGrid('setcellvalue', c, 'access', !access);
						}
						break;
				}

				$('#acls_access-groups', $c).jqxGrid('endupdate');
			});
		});
	},

	/**
	 * Read the "Access" column from the grid from all rows and make a list of IDs
	 */
	read_Groups = function()
	{
		var allrows = $('#acls_access-groups', $c).jqxGrid('getboundrows'), enabled_groups = [];

		for(var c=0; c< allrows.length; c++)
			if(allrows[c].access)
				enabled_groups.push(allrows[c].id);

		return enabled_groups.join(',');
	},

	/**
	 * Sets up the grid content that displays the current data field filters (xf)
	 * Setting up access on fields gives a very powerful ability to limit what users can see, depending on what the data contains
	 */
	setup_XF_Filters = function(xf)
	{
//		dd(xf);
	}
	;

	this._public({
		name: "ACLs Editor",

		/**
		 * Setup the included editors and initialize them with the passed data
		 *
		 * @param config Contains:
		 * - container jQuery object which contains the elements that need to be made interactive (Is used as the selector context)
		 * - acls
		 * - privileges
		 * @param master The object that instanced this editor.
		 * @param privileges
		 */
		setup: function(config, _master)
		{
			if(!config.container || config.container.length == 0)
				return cargohub.alert("Error, could not setup ACLs Editor because container not specified or not found.");

			$c = config.container;
			if(_master)
				master = _master;

			params = config.params||{};

			// Blank ACLs initialization, if data is missing
			if(!config.acls)
				config.acls = {"privileges": [], "access_ids": {}};
			if(!config.acls.access_ids)
				config.acls.access_ids = {};
			if(!config.acls.privileges)
				config.acls.privileges = [];

			setup_RecordTypes(config.acls.access_ids.ist); // field: access_ids.ist (simple array of ints) - Issue types (choose from those available to the organization)
			setup_GroupsGrid(config.acls.access_ids.groups); // List of groups which are ON - Will mark checkboxes in the grid rows

			// TODO: Content filter access editors (they can be complicated)
//			setup_XF_Filters(acls.access_ids.xf); // field: access_ids.xf (keyed list of field names and filter system recognizable "condition" + "value")
			setup_Privileges(config.acls.privileges); // field: privileges

			// TODO: Other types of Access Lists
//			setup_RPT_List(acls.access_ids.rpt); // field: access_ids.rpt (simple array of ints) - Report types (choose from those available to the organization)
//			setup_Canned_List(data.access_ids.canned); // field: access_ids.canned (simple array of ints) - Limit restrictions to defined canned messages (choose from all those available to the organization - Include "global")
				// -- Note: Personal canned messages, if enabled, will always show and can't be restricted

			init_complete = true;
		},

		/**
		 * Reads the data from all the included editors and returns a variable with them. The data can usually be used in an ajax request, to submit the modified data
		 * @param $container
		 * @return object
		 */
		read: function()
		{
			acls = {"privileges": "", "access_ids": {}};
			acls.access_ids.ist = read_RecordTypes();
			acls.access_ids.groups = read_Groups();
			acls.privileges = read_Privileges();

			// TODO: The others?

			return acls;
		},

		/**
		 * Update all the editors with new incoming data.
		 * This can only be used after the acls editors have been initialized, or it has no effect.
		 * @param acls
		 */
		update: function(acls)
		{
			if(!init_complete)
				return;

			if(!acls)
				acls = {"privileges": [], "access_ids": {}};
			if(!acls.access_ids)
				acls.access_ids = {};

			update_Privileges(acls.privileges);
//			update_IST_List(acls.access_ids.ist);
		}
	});

	// Load CSS on first use, because this module needs styles
	donrey.load_CSS(donrey.CFG.paths.components_css + "acls_editor.css?v=2.7");
});