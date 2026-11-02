/**
 * Module "DataSource Exchange" - Currency exchange list and instant-calculator for all currencies at onc
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2.8
 */
cargohub.datasource_exchange = cargohub.baseclass._instance(cargohub.baseclass.fullgrid, function() {
	// private
	var self = this,
	c = null,

	// Data from the table containing currently selected row. Used for View, Edit, Delete - Set on any row operation.
	selected_row = null,
	user_is_admin = false,

	// Source for the Grid
	date_mode = "live", // live = Showing latest exchange values (updated once per hour) ; hist = Showing historic dates (another date than today is selected )
	grid_Source = null,

	/**
	 * A record row has been selected - Store information in preparation for a double-click ( for Previewing or Opening ), or for clicking on one of the toolbar buttons
	 */
	select_Row = function(event)
	{
		var operations_bar ="#data-row-ops";

		if(user_is_admin && !selected_row) {
			donrey.cancel_Delay('hide-operations_bar');
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
		var operations_bar ="#data-row-ops";

		selected_row = null;

		if(user_is_admin) {
			$(operations_bar+ ' button').jqxButton({disabled: true});
			donrey.single_Delay('hide-operations_bar', function() {
				$(operations_bar).fadeOut();
			}, 500);
		}
	},

	/**
	 * Opens the modal used to
	 */
	open_RateEditor = function()
	{
		if(!user_is_admin)
			return; // Prevent accidental opening of the window by a non-admin

		cargohub.components.modal.create('edit-rate', {
			btn_roles: {cancel: "cancel"}
		});

		cargohub.components.modal.open('edit-rate', {
			width: 400,	height: 120,
			title: "Edit exchange rate for: "+selected_row.from_iso+' to '+selected_row.to_iso,
			content: $('#template_edit-rate').html().present({
				_itc: cargohub.input_theme_classes,
				xrate: selected_row.xrate
			}),
			callback: function($window) // Setup the controls inside the window
			{
				// Setup the action for "OK" button (start with it disabled, enabled when user has been selected)
				$('#modal_edit-rate_ok', $window).off('click').on('click', function()
				{
					$.post("?action=Update_XRate", {
						from: selected_row.from_iso,
						to: selected_row.to_iso,
						xdate: date_mode == "live"? "0000-00-00": $("div.toolbar #historic-date").val(),
						xrate: $('#xe-xrate', $window).val()
					}, function(x) {
						if(x.success) {
							// Clear selection, Got to Page 1, Reload the grid
							$window.jqxWindow('close');
							$("#exchange-grid").jqxGrid('clearselection');
							select_Clear();
							$("#exchange-grid").jqxGrid('updatebounddata');
							cargohub.alert(x.response, "Success !", "thumb_up.png");
						} else
							// Unless something went wrong...
							cargohub.its.http_fail(null, "Datasource.Exchange::Update_XRate");
					})
					.fail(cargohub.its.http_fail);
				});

			}
		});
	},

	/**
	 * Initialize the toolbar (Admin only?)
	 */
	init_Toolbar = function()
	{
		// This toolbar contains a calendar control
		$("div.toolbar #historic-date")
		.jqxDateTimeInput({
			theme: cargohub.jqxtheme,
			animationType: "fade",
			formatString: "yyyy-MM-dd",
			max: new Date(),
			width: 105,
			showFooter: true
		})
		/**
		 * Event: A date is selected
		 * - Send a cache check/update request (in the background) to update currencies from apilayer (if necessary)
		 * - When update finished, reload the grid and insert the "date" parameter (to show currencies from another date, not live ones)
		 */
		.on('change', function(e) {
			if(!e.args.date)
				return; // Nothing to do when there's no date

			var isodate = $(this).val(), now = new Date();

			if(e.args.date.toDateString() == now.toDateString()) {
				delete grid_Source._source.data.isodate;
				$("#exchange-grid").jqxGrid('updatebounddata');
				date_mode = "live";
				cargohub.alert("Showing most recent currency exchange rates", "Success !", "thumb_up.png");
				return;
			}

			// Check if selected date is "today", then unset isodate. Otherwise, use the date

			cargohub.components.spinner.On("Please wait...", "Checking currencies", null, null, 500);
			$.post("?action=xchk", {
				isodate: isodate
			}, function(x) {
				if(x.success) {
					// Clear selection, Got to Page 1, Reload the grid
					cargohub.components.spinner.Off();
					grid_Source._source.data.isodate = isodate;
					$("#exchange-grid").jqxGrid('updatebounddata');

					date_mode = "hist";
					cargohub.alert(x.response, "Success !", "thumb_up.png");
				} else
					// Unless something went wrong...
					cargohub.its.http_fail(null, "Datasource.Exchange::XChk");
			})
			.fail(cargohub.its.http_fail);
		});

		$("div.toolbar button")
		.jqxButton({ theme: cargohub.jqxtheme })
		/**
		 * Event: Toolbar button is clicked
		 */
		.on('click', function()
		{
			switch($(this).data('op')) {
			case 'edit-row':
				open_RateEditor();
				break;

			// Removes any sorting, filters and paging from the grid, bringing it to the default (sort by update time ascending)
			case "grid-state-reset":
				if(!confirm("Are you sure you want to reset the grid ?"))
					return;

				$.post("/global/operations?action=Save_Grid_State", {
					grid_id: "currency_exchange",
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
	 * Setup and load the grid used to display the records
	 */
	init_Grid = function()
	{
		// Prepare the grid (Configurable)
		var gridDataFields = [
			{name: 'upd_ts', type: 'date'},
			{name: 'from_iso', type: 'string'},
			{name: 'to_iso', type: 'string'},
			{name: 'xrate', type: 'number'},
			{name: 'xinv', type: 'number'},
			{name: 'calc', type: 'number'}, // Calculator field, automatically translates value entered into the input for every rate present in the grid
			{name: 'update_mode', type: 'string'}
		];

		// TODO: Add gridDataFields and gridColumns one by one using the information from self.config.columns
		grid_Source = self.source({
			url: "?action=get_list",
			type: "POST", // Do it as post due to possible long request URL (so it works with large filters)
			data: {
				virtualmode: true
			},
			datatype: "json",
			root: "data",
			datafields: gridDataFields,
			// server side operations - filtering and sorting:
			cache: false,
			filter: function() {
				$("#exchange-grid").jqxGrid('updatebounddata', 'filter');
			},
			sort: function() {
				$("#exchange-grid").jqxGrid('updatebounddata', 'sort');
			},
			beforeprocessing: function(source) {
				this.totalrecords = source.found_rows;
			},
			/**
			 * Client-Side pre-processing of filter data, because the way the grid sends it by default is total crap.
			 */
			formatData: cargohub.its.jqxGrid_FormatFilters
		});

		// the column definition
		self.column({text: "From", dataField: 'from_iso', width: 80, filtertype: "checkedlist", filteritems: self.config.curr_list,
			createfilterwidget: function (column, columnElement, widget) {
				widget.jqxDropDownList({
					dropDownWidth: 130,
					animationType: "fade"
				});
			},
			align: "center", cellsalign: "center"});
		self.column({text: "To", dataField: 'to_iso', width: 80, filtertype: "checkedlist", filteritems: self.config.curr_list,
			createfilterwidget: function (column, columnElement, widget) {
				widget.jqxDropDownList({
					dropDownWidth: 130,
					animationType: "fade"
				});
			},
			align: "center", cellsalign: "center"});
		self.column({text: "Direct Rate", dataField: 'xrate', width: 110, filtertype: "number", align: "center", cellsalign: "right"});
		self.column({text: "Inverse Rate", dataField: 'xinv', width: 110, filtertype: "number", align: "center", cellsalign: "right"});
		self.column({text: "Calculator", dataField: 'calc', cellsformat: 'd2', filtertype: 'textbox', width: 110, cellsalign: "right", sortable: false});
		self.column({text: "XE Verification Link", filterable: false, sortable: false, // This is just a link containing both "from" and "to" fields
			cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
				if(!grid_Source.records.length)
					return '';

				if(date_mode == "live")
					return '<div style="text-overflow: ellipsis; overflow: hidden; padding-bottom: 2px; text-align: left; margin-right: 2px; margin-left: 4px; margin-top: 4px;">'+
					'<a href="http://www.xe.com/currencyconverter/convert/?Amount=1&From='+grid_Source.records[row].from_iso+'&To='+grid_Source.records[row].to_iso+'" target="_blank">Check rate for: '+grid_Source.records[row].from_iso+' to '+grid_Source.records[row].to_iso+'</a></div>';
				else if(date_mode == "hist")
					return '<div style="text-overflow: ellipsis; overflow: hidden; padding-bottom: 2px; text-align: left; margin-right: 2px; margin-left: 4px; margin-top: 4px;">'+
					'<a href="http://www.xe.com/currencytables/?from='+grid_Source.records[row].from_iso+'&date='+$("div.toolbar #historic-date").val()+'" target="_blank">Historic rates for: '+grid_Source.records[row].from_iso+' on selected date</a></div>';
			}
		});
		self.column({text: "Source", dataField: 'update_mode', width: 80, filtertype: "checkedlist", filteritems: ['manual', 'apilayer'],
			align: "center", cellsalign: "center"});
		self.column({text: "Updated on", dataField: 'upd_ts', width: 110, cellsformat: "MMM-dd HH:mm", align: "center", cellsalign: "center", filtertype: "range"});

		// Init the grid as persistent in session
		self.init_Grid($("#exchange-grid"), {
			width: cargohub.fullwidth,
			virtualmode: true, // virtual state
			rendergridrows: function(params) { return params.data; },
			pageable: true, pagesizeoptions: [10, 25, 50, 100, 250, 500, 1000], pagesize: 25
		}, {
			persistent: true,
			grid_id: 'currency_exchange'
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
			open_RateEditor();
		});
	}
	;

	/**
	 * Initialize the module
	 */
	this.init = function()
	{
		user_is_admin = cargohub.application.user_Is_Admin();

		init_Toolbar();
		init_Grid();
	};
});