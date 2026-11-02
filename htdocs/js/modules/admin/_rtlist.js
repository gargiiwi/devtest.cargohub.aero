/**
 * Module "Issue Types List" (Admin only)
 * -- Display all Issue Types in the system ( For now, only admin has access to this. In the (far) future, certain organizations might be able to manage their own issue types )
 */
cargohub.admin_rtlist = cargohub.baseclass._instance(cargohub.baseclass.fullgrid, function() {
	// private
	var self = this,
		c = null,

	// Grid related vars
	grid_Source = null,
	selected_row = null,

	/**
	 * Sets up the toolbar with operations related to organizations
	 */
	setup_Toolbar = function()
	{
		// Buttons to add/edit/remove an user from the department
		$('.toolbar button')
		.jqxButton({ theme: cargohub.jqxtheme })
		.on('click', function() {
			var data = $(this).data();

			switch(data.op) {
				case 'create':
					// Opens the issue create form
					document.location = "?action=create";
					break;

				case 'details':
					// Opens the Issue Details page in which all fields are visible
					document.location = "?action=details&id="+ selected_row.id;
					break;

				case 'edit':
					// Opens the Communcation module into administrative mode (admin only), which loads messages and contacts for the specified organization instead of the logged in user's organization
					document.location = "/admin/rtedit?id="+ selected_row.id;
					break;

				case 'edit-old':
					document.location = "?action=edit&id="+ selected_row.id;
					break;

				case 'count':
					// Does a recount of all issues of a certain type and then reloads the grid.
					$.post("?action=count_issues", {
						ist_id: selected_row.id
					}, function(x) {
						cargohub.alert(x.response, "Success !", "thumb_up.png");
						self.refresh_Grid();
					})
					.fail(cargohub.its.http_fail);
					break;

				// Removes any sorting, filters and paging from the grid, bringing it to the default (sort by date descending)
				case "grid-state-reset":
					if(!confirm("Are you sure you want to reset the grid ?"))
						return;

					$.post("/global/operations?action=Save_Grid_State", {
						grid_id: "rtlist",
						state: false
					}, function(x) {
						document.location.reload();
					})
					.fail(cargohub.its.http_fail);
					break;

				case 'archive':
					if(data.confirm)
						if(!confirm("Are you sure you want to "+ data.confirm_msg))
							return;

					$.post("?action=Archive", {
						rt_id: selected_row.id
					}, function(x) {
						cargohub.alert(x.response, "Success !", "thumb_up.png");
						self.refresh_Grid();
					})
					.fail(cargohub.its.http_fail);
					break;
			}
		});
	},

	/**
	 * A record row has been selected - Store information in preparation for a double-click ( for Previewing or Opening ), or for clicking on one of the toolbar buttons
	 */
	select_Row = function(event)
	{
		var operations_bar = "#rt_edit_ops";

		if(!selected_row) {
			donrey.cancel_Delay('hide-ops_bar');
			$(operations_bar+ ' button').jqxButton({disabled: false});
			$(operations_bar).fadeIn();
		}

		if(event.args.row)
			selected_row = event.args.row;

		$("#selected-item").html(selected_row.name);
	},

	/**
	 * No rows are currently selected - Make the toolbar buttons dissapear
	 */
	select_Clear = function()
	{
		var operations_bar = "#rt_edit_ops";

		selected_row = null;
		$(operations_bar+ ' button').jqxButton({disabled: true});
		donrey.single_Delay('hide-ops_bar', function() {
			$(operations_bar).fadeOut();
		}, 500);

		$("#rt-grid").jqxGrid('clearselection');
		$("#selected-item").empty();
	},


	/**
	 * Sets up the issue types display/search grid
	 */
	setup_RTGrid = function()
	{
		var
		// the data definition
		gridDataFields = [
			{name: 'id', type: 'int'},
			{name: 'scope'},
			{name: 'key'},
			{name: 'name'},
			{name: 'short_plural'},
			{name: 'description'},
			{name: 'count_issues', type: 'int'}
		];

		grid_Source = self.source({
			url: "?action=Get_List",
			type: "POST", // Do it as post due to possible long request URL (so it works with large filters)
			data: {
//				org_id: null,	// TODO: Support organization limited record types
				virtualmode: true
			},
			datatype: "json",
			root: "data",
			id: 'id',
			datafields: gridDataFields,
			// server side operations - filtering and sorting:
			cache: false,
			filter: function() {
				$("#rt-grid").jqxGrid('updatebounddata', 'filter');
			},
			sort: function() {
				$("#rt-grid").jqxGrid('updatebounddata', 'sort');
			},
			beforeprocessing: function(source) {
				this.totalrecords = source.found_rows;
			},
			/**
			 * Client-Side pre-processing of filter data, because the way the grid sends it by default is total crap.
			 */
			formatData: cargohub.its.jqxGrid_FormatFilters
		});

		self.column({text: "ID", datafield: 'id', width: 55, cellsalign: "right", filtertype: "number"});
		self.column({text: "Scope", datafield: 'scope', width: 75, cellsalign: "center", filtertype: "checkedlist", filteritems: ["global", "org", "override", "admin", "archive"],
			createfilterwidget: function (column, columnElement, widget) {
				widget.jqxDropDownList({
					dropDownWidth: 110,
					autoDropDownHeight: true,
					animationType: "fade"
				});
			}
		});
		self.column({text: "Name", datafield: 'name', width: 170});
		self.column({text: "Recs", datafield: 'count_issues', width: 55, cellsalign: "right", filtertype: "number"});
		self.column({text: "Folder Key", datafield: 'key', width: 110});
		self.column({text: "Plural form", datafield: 'short_plural', width: 120});
		self.column({text: "Description", datafield: 'description', filterable: false, sortable: false});

		// Init the grid as persistent in session
		self.init_Grid($("#rt-grid"), {
			width: cargohub.fullwidth,
			virtualmode: true, // virtual state
			rendergridrows: function(params) { return params.data; },
			pageable: true, pagesizeoptions: [10, 25, 50, 100, 250], pagesize: 25
		}, {
			persistent: true,
			grid_id: 'rtlist'
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
			$('.toolbar button[data-op="edit"]').trigger("click");
		});
	};

	;
	/**
	 * Section initialization
	 */
	this.init = function()
	{
		setup_Toolbar();

		// Initial grid state
		if(!self.config.grid_state)
			self.config.grid_state = {};
		setup_RTGrid();

		// Info Explander
		$(".info-expander").jqxExpander({
			theme: cargohub.jqxtheme,
			expanded: true
		});
	};
});
