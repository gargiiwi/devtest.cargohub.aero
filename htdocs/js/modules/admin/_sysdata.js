/**
 * Module "System data tables"
 * -- Displays and allows management of system data tables
 * -- Consists of a menu bar and a grid containing the tables of a certain type ( with custom columns )
 * -- However, the "editor" for tables is in a sepparate JS module, and differs from table type to table type
 * The "data plugin" is used to retrieve the type of fields that the grid will have, and also performs the add/save/delete operations
 */
cargohub.admin_sysdata = cargohub.baseclass._instance(cargohub.baseclass.fullgrid, function() {
	// private
	var self = this,
		c = null,

	// Grid related vars
	grid_Source = null,
	selected_row = null,

	/**
	 * A record row has been selected - Store information in preparation for a double-click ( for Previewing or Opening ), or for clicking on one of the toolbar buttons
	 */
	select_Row = function(event)
	{
//		var operations_bar = "#messages-rowops";
//
//		if(!selected_row) {
//			donrey.cancel_Delay('hide-rowops_bar');
//			$(operations_bar+ ' button').jqxButton({disabled: false});
//			$(operations_bar).fadeIn();
//		}

		if(event.args.row)
			selected_row = event.args.row;
	},

	/**
	 * No rows are currently selected - Make the toolbar buttons dissapear
	 */
	select_Clear = function()
	{
//		var operations_bar = "#messages-rowops";
//
//		selected_row = null;
//		$(operations_bar+ ' button').jqxButton({disabled: true});
//		donrey.single_Delay('hide-rowops_bar', function() {
//			$(operations_bar).fadeOut();
//		}, 500);

		$("#sysdata-grid").jqxGrid('clearselection');
	},

	/**
	 * Sets up the data table display/search grid
	 */
	init_Grid = function()
	{
		grid_Source = self.source({
			url: "?action=Get_List",
			type: "POST", // Do it as post due to possible long request URL (so it works with large filters)
			data: {
				type: self.config.type,
				virtualmode: true
			},
			datatype: "json",
			root: "data",
			id: 'id',
			datafields: self.config.datafields,
			// server side operations - filtering and sorting:
			cache: false,
			filter: function() {
				$("#sysdata-grid").jqxGrid('updatebounddata', 'filter');
			},
			sort: function() {
				$("#sysdata-grid").jqxGrid('updatebounddata', 'sort');
			},
			beforeprocessing: function(source) {
				this.totalrecords = source.found_rows;
			},
			/**
			 * Client-Side pre-processing of filter data, because the way the grid sends it by default is total crap.
			 */
			formatData: cargohub.its.jqxGrid_FormatFilters
		});

		for(c=0; c< self.config.columns.length; c++)
			self.column(self.config.columns[c]);

		// Init the grid as persistent in session
		self.init_Grid($("#sysdata-grid"), {
			width: cargohub.fullwidth,
			virtualmode: true, // virtual state
			rendergridrows: function(params) { return params.data; },
			pageable: true, pagesizeoptions: [10, 25, 50, 100, 250], pagesize: 25,
			groupable: true
		}, {
			persistent: true,
			grid_id: 'sysdata_'+ self.config.type
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
		 * Event: On doubleclicking, open the message for viewing
		 */
		.on('rowdoubleclick', function() {
			// trigger editing of selected table
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
		switch(command.action) {
			// Shows a window with detailed information about a data table ( not all data types have "view" option, sometimes they are too simple )
			case 'view':
				alert("At the moment, all the information that exists is already visible in the grid.");
				break;

			// Shows the editor in which a data table information can be modified (depends on data type)
			case 'edit':
				cargohub["sysdata_" + self.config.type].open_Editor(selected_row);
				break;

			// Asks for deletion of the data table. What exactly happens when removing a data entry depends on the plugin
			case 'delete':
				if(confirm("Are you REALLY sure you want remove this "+self.config.name+"?\n\nAll elements that use this "+self.config.name+" might generate or show errors !")) {
					$.post("?action=delete_entry&type="+self.config.type, {
						id: selected_row.id
					}, function(x) {
						if(x.success)
							document.location.reload();
						else {
							cargohub.its.alert(x.reason, "Message from server", "information.png");
						}
					});
				}
				break;
		}
	}

	;
	this.init = function()
	{
		// The operational menu
		$("#sysdata-menubar").jqxMenu({
			width: cargohub.fullwidth, theme: cargohub.jqxtheme, showTopLevelArrows: true
		});

		// Menu Operations
		$(".jqx-menu-item, .jqx-menu-item-top").on('click', function() {
			switch($(this).data('op')) {
				case "create":
					cargohub["sysdata_"+ self.config.type].open_Editor(); // Opens editor in add mode ( with no data passed to it )
					break;

				// Clears all filters, paging and resets grid
				case "reset":
					if(!confirm("Are you sure you want to reset the grid ?"))
						return;

					$.post("/global/operations?action=Save_Grid_State", {
						grid_id: 'sysdata_'+ self.config.type,
						state: false
					}, function(x) {
						// Reload the page after clearing the state
						document.location.reload();
					})
					.fail(cargohub.its.http_fail);
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

		// Only setup the grid if data type is known
		if(self.config.type) {

			// Initial grid state
			if(!self.config.grid_state) {
				self.config.grid_state = {
					sort: {
						column: 'id',
						direction: 'asc'
					}
				};
			}
			init_Grid();
		}
	};

	// Not needed here, might be needed in the editing sub-module
//	input_theme_classes: "jqx-widget jqx-widget-content jqx-widget-"+cargohub.jqxtheme+" jqx-input-"+cargohub.jqxtheme+" jqx-widget-content-"+cargohub.jqxtheme,
});