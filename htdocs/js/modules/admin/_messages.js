/**
 * Module "Global Message Search"
 */
cargohub.admin_messages = cargohub.baseclass._instance(cargohub.baseclass.fullgrid, function() {
	var self = this,
	c = null,

	// Data from the grid containing currently selected row. Used for View, Edit, Delete - Set on any row operation.
	selected_row = null,

	// Data Source for the fields grid
	gridDataFields = [
		{name: 'id', type: 'int'},
		{name: 'dt', type: 'date'},		// When it was created/sent
		{name: 'from_name', type: 'string', map: 'sender>name'},
		{name: 'from_email', type: 'string', map: 'sender>email'},
		{name: 'subject', type: 'string'},
		{name: 'email_status', type: 'string'},
		{name: 'link', type: 'string', map: 'convo>type'},
		{name: 'link_id', type: 'int', map: 'convo>link_id'},
		{name: 'rec_track', type: 'string', map: 'rec>track'}
	],
	grid_Source = null,

	/**
	 * Sets up the toolbar with operations related to organizations
	 */
	init_Toolbar = function()
	{
		var tpl_data = {_itc: cargohub.input_theme_classes};

		// Buttons to add/edit/remove an user from the department
		$('.toolbar button')
		.jqxButton({ theme: cargohub.jqxtheme })
		.on('click', function() {
			var data = $(this).data();

			switch(data.op) {

			// Opens the message for viewing
			case "view":

				break;

			// If available, opens the record to which this message belongs
			case "link":
				if(selected_row.link == 'issue' && selected_row.link_id) {
					// This message belongs to a record; Open it
					document.location = "/record/process:" + selected_row.rec_track + "#comm";
				}
				break;

			// Removes any sorting, filters and paging from the grid, bringing it to the default (sort by date descending)
			case "grid-state-reset":
				if(!confirm("Are you sure you want to reset the grid ?"))
					return;

				$.post("/global/operations?action=Save_Grid_State", {
					grid_id: "sysmessages",
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
	 * A record row has been selected - Store information in preparation for a double-click ( for Previewing or Opening ), or for clicking on one of the toolbar buttons
	 */
	select_Row = function(event)
	{
		var operations_bar = "#messages-rowops";

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
	select_Clear = function()
	{
		var operations_bar = "#messages-rowops";

		selected_row = null;
		$(operations_bar+ ' button').jqxButton({disabled: true});
		donrey.single_Delay('hide-rowops_bar', function() {
			$(operations_bar).fadeOut();
		}, 500);

		$("#entries-grid").jqxGrid('clearselection');
	},

	/**
	 * Sets up the messages display/search grid
	 */
	init_MessagesGrid = function()
	{
		grid_Source = self.source({
			url: "?action=Get_Messages",
			type: "POST", // Do it as post due to possible long request URL (so it works with large filters)
			data: {
				org_id: null,	// TODO: Support editing blog entries for arbitrary organization?
				virtualmode: true
			},
			datatype: "json",
			root: "data",
			id: 'id',
			datafields: gridDataFields,
			// server side operations - filtering and sorting:
			cache: false,
			filter: function() {
				$("#messages-grid").jqxGrid('updatebounddata', 'filter');
			},
			sort: function() {
				$("#messages-grid").jqxGrid('updatebounddata', 'sort');
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
		self.column({
			text: "DB ID", datafield: 'id', width: 70,
			align: "right", cellsalign: "right", filtertype: "number"
		});
		self.column({text: "Date", dataField: 'dt', width: 128, cellsformat: "yyyy-MM-dd HH:mm", align: "center", cellsalign: "center", filtertype: "range"});
		self.column({
			text: "Status", datafield: 'email_status', width: 115, cellsalign: 'center',
			filtertype: "checkedlist", filteritems: ["none", "sent", "smtpsent", "failed", "retry", "confirmed", "bounced"],
			createfilterwidget: function (column, columnElement, widget) {
				widget.jqxDropDownList({
					dropDownWidth: 105,
					autoDropDownHeight: true,
					animationType: "fade"
				});
			}
		});
		self.column({
			text: "Subject", datafield: 'subject',
			width: 455, minwidth: 200, maxwidth: 700,
			groupable: false
		});
		self.column({
			text: "From (name)", datafield: 'from_name',
			width: 160, minwidth: 100, maxwidth: 300
		});
		self.column({
			text: "From (email)", datafield: 'from_email',
			width: 200, minwidth: 100, maxwidth: 300
		});

		// Init the grid as persistent in session
		self.init_Grid($("#messages-grid"), {
			width: cargohub.fullwidth,
			virtualmode: true, // virtual state
			rendergridrows: function(params) { return params.data; },
			pageable: true, pagesizeoptions: [10, 25, 50, 100, 250], pagesize: 25,
			groupable: true
		}, {
			persistent: true,
			grid_id: 'sysmessages'
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
			$('.toolbar button[data-op="view"]').trigger("click");
		});
	}

	;
	this.init = function()
	{
		init_Toolbar();

		// Initial grid state
		if(!self.config.grid_state) {
			self.config.grid_state = {
				sort: {
					column: 'dt',
					direction: 'desc'
				}
			};
		}
		init_MessagesGrid();

		// Information box Expander
		$(".info-expander").each(function() {
			$(this).jqxExpander({
				theme: cargohub.jqxtheme,
				expanded: $(this).data('expanded')==true
			});
		});
	};

});
