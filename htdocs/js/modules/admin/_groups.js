/**
 * Module "Admin Groups"
 * -- Displays and allows management of groups (containers for items).
 * -- Consists of a menu bar and a grid containing the groups of a certain type ( with custom columns )
 * -- However, the "editor" for groups is in a sepparate JS module, and differs from group type to group type
 * The "group plugin" is used to retrieve the type of fields that the grid will have, and also performs the add/save/delete operations
 */
cargohub.admin_groups = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,

	/**
	 * Sets up the group display/search grid
	 */
	setup_grid = function()
	{
		// prepare the data
		var listGridAdapter = new $.jqx.dataAdapter({
			url: '?action=get_list&type=' + self.config.group.type,
			datatype: "json",
			datafields: self.config.datafields,
			id: 'id'
		});

		$("#groups-grid").jqxGrid(
		{
			theme: cargohub.jqxtheme,
			source: listGridAdapter,

			width: cargohub.fullwidth, autoheight: true,
			columns: self.config.columns, columnsresize: true, columnsmenu: false,
			selectionmode: "singlerow",	altrows: true,
			pageable: true,	pagesize: 20, pagesizeoptions: [10, 20, 50, 100],
			filterable: true, showfilterrow: true, sortable: true
		}).on('rowdoubleclick', function()
		{
			// trigger editing of selected group
			perform_Action_On_Selected({
				action: "edit"
			});
		});
	},

	/**
	 * Does various actions on selected row(s)
	 */
	perform_Action_On_Selected = function(command)
	{
		var row_index = $('#groups-grid').jqxGrid('getselectedrowindex'), row_data = {};
		if(row_index==-1) {
			cargohub.its.alert("Please select a row !");
			return;
		}
		// read row data from the selected grid row (already contains some ID's which are needed for the editor)
		row_data = clone($('#groups-grid').jqxGrid('getrowdata', row_index));

		switch(command.action) {
			// Shows a window with detailed information about a group ( not all group types have "view" option, sometimes they are too simple )
			case 'view':
				dd(row_data);
				alert("Viewing of details not yet implemented. Check console.");
				break;

			// Shows the editor in which a group's information can be modified (depends on group type)
			case 'edit':
				cargohub["group_" + self.config.group.type].open_Editor(row_data);
				break;

			// Asks for deletion of the group. What exactly happens when removing a group depends on the plugin
			case 'delete':
				if(confirm("Are you REALLY sure you want remove this "+self.config.group.name+"?\n\nAll elements that are connected to the "+self.config.group.name+" will become \"orphaned\"\nand might not be accessible from the interface anymore !")) {
					$.post("?action=delete_group&type="+self.config.group.type, {
						id: row_data.id
					}, function(x) {
						if(x.success)
							document.location.reload();
						else {
							cargohub.its.alert(x.reason, "Message from server", "information.png");
						}
					});
				}

		}
	}

	;
	this._public({

		input_theme_classes: "jqx-widget jqx-widget-content jqx-widget-"+cargohub.jqxtheme+" jqx-input-"+cargohub.jqxtheme+" jqx-widget-content-"+cargohub.jqxtheme,

		/**
		 * Section initialization
		 */
		init: function()
		{
			// The operational menu
			$("#groups-menubar").jqxMenu({
				width: cargohub.fullwidth, theme: cargohub.jqxtheme, showTopLevelArrows: true
			});

			// Menu Operations
			$(".jqx-menu-item, .jqx-menu-item-top").on('click', function() {
				switch($(this).data('op')) {
					case "create":
						cargohub["group_" + self.config.group.type].open_Editor(); // Opens editor in add mode ( with no data passed to it )
						break;

					// menu actions
					case "action":
						perform_Action_On_Selected($(this).data());
						break;
				}
			});

			// Information box Expander
			$(".info-expander").each(function() {
				$(this).jqxExpander({
					theme: cargohub.jqxtheme,
					expanded: $(this).data('expanded')==true
				});
			});

			// Only setup the grid if group type is known
			if(self.config.group)
				setup_grid();
		}
	});
});
