/**
 * Module "Global Record Search"
 * - Overview of records of all types (Shows "primary" cache field as column "Global Search"
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2019.03.21
 */
cargohub.record_globalsearch = cargohub.baseclass._instance(cargohub.baseclass.fullgrid, function() {
	// private
	var self = this,
	c = null,

	// Data from the table containing currently selected row. Used for View, Edit, Delete - Set on any row operation.
	selected_row = null,

	// Source for the Grid
	records_Source = null,

	/**
	 * A record row has been selected - Store information in preparation for a double-click ( for Previewing or Opening ), or for clicking on one of the toolbar buttons
	 */
	select_Row = function(event)
	{
		var operations_bar ="#record-row-ops";

		if(!selected_row) {
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
		var operations_bar ="#record-row-ops";

		selected_row = null;
		$(operations_bar+ ' button').jqxButton({disabled: true});
		donrey.single_Delay('hide-operations_bar', function() {
			$(operations_bar).fadeOut();
		}, 500);
	},


	// Opened record preview tabs : Object containing the tracking ID's which are currently open. The value is the tab index.
	// -- Attempting to open an existing TrackID will instead switch to that tab.
	// -- Closing a tab will instead remove the TrackID from this list
	// The Open Tabs list is persistent, in a session variable, and tabs are auto-opened at module load.
	open_tabs = {},
	open_tabs_length = 0,
	persistence_update_enabled = true,

	sync_Previews_Persistence = function()
	{
		$.post("?action=Persistent_Previews", {
			tabs: JSON.stringify(open_tabs)
		}, function(x) {
			if(x.success) {
				// This is a silent operation
			} else
				cargohub.its.http_fail(null, "Search.Global::Persistent_Previews");
		})
		.fail(cargohub.its.http_fail);
	},

	/**
	 * Prepares the dynamic tab toolbar (and other possible functions) after a tab has been loaded
	 */
	init_Tab_Content = function(tab_index)
	{
		// Activate the tab's toolbar buttons
		$('#global-search-tabs').jqxTabs('getContentAt', tab_index).find('div.toolbar button')
		.jqxButton({ theme: cargohub.jqxtheme })
		/**
		 * Event: Toolbar button is clicked
		 */
		.on('click', function()
		{
			switch($(this).data('op')) {
			// Opens the record visible in this tab
			case "record-open":
				document.location = "/record/process:"+ $(this).data('track')+ (self.config.perspective? "&perspective="+self.config.perspective: '')+ "#panels";
				break;
			}
		});

	},

	/**
	 * Setup the tab-system record preview (opens each preview in one tab allowing quick switch between data)
	 */
	init_Tabs = function()
	{
		$('#global-search-tabs').jqxTabs({
			theme: cargohub.jqxtheme,
			width: cargohub.fullwidth,
			position: 'top',
			selectionTracker: false, selectedItem: 0,
			scrollPosition: 'both', scrollStep: 100, keyboardNavigation: false,
			showCloseButtons: true,
		})
		/**
		 * When a tab is added, it's saved in the open_tabs object and the list updated on server side
		 */
		.on('add', function(e) {
			if(open_tabs_length == 0)
				$("#preview-tabs-ops").fadeIn();

			open_tabs[selected_row.track] = e.args.item;
			open_tabs_length++;

			// Only save when user has opened a tab, not when they are auto-initialized
			if(persistence_update_enabled)
				sync_Previews_Persistence();

			init_Tab_Content(open_tabs_length);
		})
		/**
		 * When a tab is closed (removed), it should also be removed from open_tabs and the list updated on server side
		 */
		.on('removed', function(e) {
			// Mass clear - Nothing to do here.
			if(open_tabs_length == 0)
				return;

			// Go through the "open_tabs" object and update indexes. The one found in e.args.item is removed, the ones with larger index are decreased by one.
			var found_track = null;
			for(c in open_tabs) {
				if(open_tabs[c] > e.args.item)
					open_tabs[c]--;
				else if(open_tabs[c] == e.args.item)
					found_track = c;
			}
			delete open_tabs[found_track];
			open_tabs_length--;

			if(open_tabs_length == 0)
				$("#preview-tabs-ops").fadeOut();

			if(persistence_update_enabled)
				sync_Previews_Persistence();
		});

		// First tab (grid) cannot be removed
		$('#global-search-tabs').jqxTabs('hideCloseButtonAt', 0);

		if(self.config.open_tabs) {
			persistence_update_enabled = false;

			// Attempt to load and open the tabs. Order might NOT be maintained because tabs are created as results are returned
			cargohub.components.spinner.On("Loading previews", "This might take a while", 5000, function() {
				cargohub.components.spinner.Off();
				cargohub.alert("Warning, some preview tabs failed to load. Using <b>Close all previes</b> function is recommended.");
			}, 200);

			var tabs_to_load = self.config.open_tabs.length;
			for(c=0; c< self.config.open_tabs.length; c++)
				$.get("?action=Load_Preview", {
					track: self.config.open_tabs[c]
				}, function(contents, textStatus, jqXHR) {
					var hybrid_response = {};
					donrey.read_JSON_Headers(jqXHR, hybrid_response);

					open_tabs[hybrid_response.tabinfo.track] = self.config.open_tabs.length - tabs_to_load + 1;
					open_tabs_length++;

					var tab_header = "<img src='/img/icons16/ist/{$rt_key}.png' alt='{$rt_name}' /> {$track}".present(hybrid_response.tabinfo);

					$('#global-search-tabs').jqxTabs('addLast', tab_header, contents);
					tabs_to_load--;

					init_Tab_Content(open_tabs_length);

					if(tabs_to_load == 0) {
						$('#global-search-tabs').jqxTabs('select', 0);
						cargohub.components.spinner.Off(500);
						persistence_update_enabled = true;
						$("#preview-tabs-ops").fadeIn();
					}
				});
		}
	},

	/**
	 * Opens a new tab containing a rendition of the selected record using the _view template
	 * -- This might not include complete record data, just the public parts (but it should be sufficient to figure out what the record is about)
	 */
	open_Preview_Tab = function()
	{
		var tab_header = "<img src='/img/icons16/ist/{$rt_key}.png' alt='{$rt_name}' /> {$track}".present(selected_row);

		// Remote load the record, when done, open the tab
		cargohub.components.spinner.On("Loading record", null, 3000, null, 200);

		$.get("?action=Load_Preview", {
			track: selected_row.track
		}, function(contents) { // , textStatus, jqXHR
			cargohub.components.spinner.Off(500);

			$('#global-search-tabs').jqxTabs('addLast', tab_header, contents);
		})
		.fail(function(xhr) {
			cargohub.alert(xhr.statusText, "Server response code: "+xhr.status, "stop.png");
			cargohub.components.spinner.Off(500);
			$button.jqxButton({disabled: false}).jqxButton('render');
		});
	},

	/**
	 * Initialize the Tasks tab toolbar (Add, Edit, Delete tasks)
	 */
	init_Toolbar = function()
	{
		$("div.toolbar button")
		.jqxButton({ theme: cargohub.jqxtheme })
		/**
		 * Event: Toolbar button is clicked
		 */
		.on('click', function()
		{
			switch($(this).data('op')) {
			// Opens the selected record (like the Track # link)
			case "record-open":
				document.location = "/record/process:"+ selected_row.track+ (self.config.perspective? "&perspective="+self.config.perspective: '')+ "#panels";
				break;
			// Creates a new tab in the module which can be closed via a small "x" near it
			case "record-preview":

				if(!open_tabs[selected_row.track])
					open_Preview_Tab();
				else
					// Tab is already open, just switch to it
					$('#global-search-tabs').jqxTabs('select', open_tabs[selected_row.track]);
				break;

			// Removes any sorting, filters and paging from the grid, bringing it to the default (sort by deadline ascending, not showing completed and failed statuses)
			case "grid-state-reset":
				/* Not working, possibly due to bug in JQWidgets 3.9.0 ...
				$("#records-grid").jqxGrid('beginupdate', false, true);
					$("#records-grid").jqxGrid('clearfilters');
					$("#records-grid").jqxGrid('sortby', 'ctime', 'desc');
					$("#records-grid").jqxGrid('gotopage', 0);
				$("#records-grid").jqxGrid('resumeupdate');
				*/
				// ... Just reload page (without any parameters) which will revert to default
				document.location = "/search/global";

				/*
				if(!confirm("Are you sure you want to reset your search ?"))
					return;
				$.post("/global/operations?action=Save_Grid_State", {
					grid_id: "search_global",
					state: false
				}, function(x) {
					if(x.success)
						// Reload the page after clearing the state
						document.location = "/search/global";
					else
						cargohub.its.http_fail(null, "Global.Operations::save_grid_state");
				})
				.fail(cargohub.its.http_fail);
				*/
				break;
			// Closes all preview tabs leaving just the grid. Also removes the persistence data from the server
			case "opentabs-closeall":
				if(!confirm("Are you sure you want to close all your persistent preview tabs (if any exists) ?"))
					return;

				persistence_update_enabled = false;
				var t = open_tabs_length;
				open_tabs = {};
				open_tabs_length = 0;

				for(; t>0; t--)
					$('#global-search-tabs').jqxTabs('removeLast');

				$("#preview-tabs-ops").fadeOut();

				sync_Previews_Persistence();
				persistence_update_enabled = true;
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
			{name: 'track', map: 'track', type: 'string'} // Necesary for the Open or Preview operation and the "Open" link
//			{name: 'access_key', map: 'access_key', type: 'string'} // Invisible but required for the web view link
		], rt_list = [];

		// Dropdown column: Record Type
		for(c=0; c< self.config.rt_source.length; c++)
			rt_list.push(self.config.rt_source[c].name);

		self.column({
			text: "Tracking #", datafield: 'track',
			align: "center", cellsalign: "center",
			width: 115, minwidth: 80, maxwidth: 140,
			cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
				return '<div style="text-overflow: ellipsis; text-align: center; overflow: hidden; padding-bottom: 2px; margin-top: 5px;"><a href="/record/process:' +
				value + (self.config.perspective? '?perspective='+self.config.perspective:'')+'#panels">' + value + '</a></div>';
			},
			groupable: false
		});

		for(c=0; c<self.config.columns.length; c++) {
			var col_cfg = self.config.columns[c],
			fk = col_cfg.fk, // the field key
			datafield = {name: fk},
			column = null
			;

			switch(fk) {
			// Primary table fields
				case 'id':
					datafield.type = 'number';
					column = {
						text: self.config.colnames[fk], datafield: fk,
						width: col_cfg.w || 50, filtertype: "number", align: "center", cellsalign: "right",
						groupable: false
					};

				break;
				case 'rt_key': // Invisible column, but needed to properly display the record type icon inside the grid
					datafield.type = 'string';
					datafield.map = 'type>key';
					column = null;
					break;

				case 'rt_name':
					datafield.type = 'string';
					datafield.map = 'type>name';
					column = {
						text: self.config.colnames[fk], datafield: fk,
						width: col_cfg.w || 200,
						filtertype: "checkedlist", filteritems: rt_list,
						createfilterwidget: function (column, columnElement, widget) {
							widget.jqxDropDownList({
								dropDownWidth: 250,
								renderer: function (index, label, value) {
									return "<div>"+ (index>0?"<img style='float: left; margin-right: 4px;' src='/img/icons16/ist/"+self.config.rt_source[index-1].key+".png' />" + value: value) + "</div>";
								},
								animationType: "fade"
							});
						},
						cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
							return "<div style='text-overflow: ellipsis; overflow: hidden; margin-top: 5px;' title='"+ value +"'><img src='/img/icons16/ist/" + records_Source.records[row].rt_key + ".png' style='margin: 0px 4px;' /><span style='position: absolute;'>" + value + "</span></div>";
						}
					};
				break;
				case 'ctime':
					datafield.type = 'date';
					column = {
						text: self.config.colnames[fk], datafield: fk,
						width: col_cfg.w || 100, cellsformat: cargohub.date_format, align: "center", cellsalign: "center", groupable: false,
						filtertype: 'range',
						cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
							return '<div style="text-overflow: ellipsis; text-align: center; overflow: hidden; padding-bottom: 2px; margin-top: 5px;">' + cargohub.application.calc_Relative_Time(value) + '</div>';
						}
					};
					break;
				case 'datagroup':
					datafield.type = 'string';
					datafield.map = 'group>name';
					column = {
						text: self.config.colnames[fk], datafield: fk,
						width: col_cfg.w || 200
					};
					break;
				// Per organization fields
				case 'priority':
					datafield.type = 'string';
					column = {
						text: self.config.colnames[fk], datafield: fk,
						width: col_cfg.w || 80, align: "center", cellsalign: "center",
						filtertype: "checkedlist", filteritems: self.config.priorities,
						// Special cells renderer for priority - It displays an icon instead of text
						cellclassname: "priorityrender",
						cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
							if(value == "(unavailable)")
								return "<div class='unavailable' title='"+ value +"'>&nbsp;</div>";
							else
								return "<div class='"+ value.replace(/[ *!.0-9]/gi,'_') +"' title='"+ value +"'></div>";
						},
						createfilterwidget: function (column, columnElement, widget) {
							widget.jqxDropDownList({
								dropDownWidth: 140,
								autoDropDownHeight: true,
								renderer: function (index, label, value) {
									if(value == "(Select All)")
										return value;
									if(value && value != "(unavailable)")
										return "<div class='priorityselect "+ value.replace(/[ *!.0-9]/gi,'_') +"'>"+ (index>0?value.substr(2):value) +"</div>";
									else
										return "(unavailable)";
								},
								animationType: "fade"
							});
						}
					};
					// hidden: true,
					break;
				case 'status':
					datafield.type = 'string';
					column = {
						text: self.config.colnames[fk], datafield: fk,
						width: col_cfg.w || 120, align: "center", cellsalign: "center",
						filtertype: "checkedlist", filteritems: self.config.statuses,
						cellclassname: "statusrender",
						cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
							if(value == "(unavailable)")
								return "<div class='unavailable' title='"+ value +"'>&nbsp;</div>";
							else
								return "<div class='"+ value.replace(/[ *!.0-9]/gi,'_') +"' title='"+ value +"'><sup>"+ value +"</sup></div>";
						},
						createfilterwidget: function (column, columnElement, widget) {
							widget.jqxDropDownList({
								dropDownWidth: 200,
								renderer: function (index, label, value) {
									if(value == "(Select All)")
										return value;
									if(value && value != "(unavailable)")
										return "<div class='statusselect "+ value.replace(/[ *!.0-9]/gi,'_') +"'>"+ value +"</div>";
									else
										return "(unavailable)";
								},
								animationType: "fade"
							});
						}
					};
					break;
				default:
					// Is it a cached field ? They are always standard strings, searchable with a text input - Should be disabled, not working properly
					if(fk.substr(0,6)=='cache_') {
						datafield.type = 'string';
						datafield.map = 'cache>'+fk.substr(6);
						column = {
							text: self.config.colnames[fk], datafield: fk,
							width: col_cfg.w || 150, cellsalign: "left"
						};
						break;
					} else
						continue; // unknown (yet) column?

			}

//			array('fk'=> "priority", 'v'=> true, 'w'=> 77),
//			array('fk'=> "status", 'v'=> true, 'w'=> 102)

			if(!datafield && !column)
				continue; // Skip invalid columns

			gridDataFields.push(datafield);
			if(column)
				self.column(column);
		}

		// TODO: Add gridDataFields and gridColumns one by one using the information from self.config.columns
		records_Source = self.source({
			url: "/search/global?action=get_list",
			type: "POST", // Do it as post due to possible long request URL (so it works with large filters)
			data: {
				virtualmode: true
			},
			datatype: "json",
			id: "track",
			root: "data",
			datafields: gridDataFields,
			// server side operations - filtering and sorting:
			cache: false,
			filter: function() {
				$("#records-grid").jqxGrid('updatebounddata', 'filter');
			},
			sort: function() {
				$("#records-grid").jqxGrid('updatebounddata', 'sort');
			},
			beforeprocessing: function(source) {
				this.totalrecords = source.found_rows;
			},
			/**
			 * Client-Side pre-processing of filter data, because the way the grid sends it by default is total crap.
			 */
			formatData: cargohub.its.jqxGrid_FormatFilters
		});

		// Init the grid as persistent in session
		self.init_Grid($("#records-grid"), {
			virtualmode: true, // virtual state
			rendergridrows: function(params) { return params.data; },
			pageable: true, pagesizeoptions: [10, 25, 50, 100, 250, 500, 1000], pagesize: 25
		}, { // Persistence disabled
//			persistent: true,
//			grid_id: 'search_global'
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
			$('div.toolbar button[data-op="record-preview"]').trigger("click");
		});
	}
	;

	/**
	 * Initialize the module
	 */
	this.init = function()
	{
		init_Tabs();
		// These are for Tab 1 (Always starts with it)
		init_Toolbar();
		init_Grid();
	};

});