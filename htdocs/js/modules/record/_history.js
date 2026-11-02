/**
 * Module "Record Process", interface for the Operating the connected organizations (Data Sharing)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules.Record Process
 * @version 2.9
 */
cargohub.record_history = donrey.BASE._extend(function() {
	// private
	var self = this,
	c = null,
	master = cargohub.record_process,
	is_admin = cargohub.application.user_Is_Admin(), // Only admin has the right to delete rows from history table

	// Data from the table containing currently selected row. Used for View, Edit, Delete - Set on any row operation.
	selected_row = null,

	// Source for the DataTable
	history_Source = new $.jqx.dataAdapter({
		url: '?action=Get_Updates',
		data: {
		},
		datatype: "json",
		datafields: [
			{name: 'dt', type: 'date'},
			{name: 'op', type: 'string'},
			{name: 'op_info', type: 'string'},
			{name: 'user_id', type: 'int', map: 'user>id'},
			{name: 'user_name', type: 'string', map: 'user>name'},
			{name: 'org_id', type: 'int', map: 'org>id'},
			{name: 'org_name', type: 'string', map: 'org>name'},
			{name: 'comment', type: 'string'}
		],
		id: "id",
		// Initial Sorting by date
		sortcolumn: 'dt',
		sortdirection: 'desc'
	}),

	/**
	 * A row has been selected - Store information in preparation for a double-click ( for Viewing or Editing ), or for clicking on one of the toolbar buttons
	 */
	select_Row = function(row)
	{
/*		if(!selected_row) {
			donrey.cancel_Delay('hide-history-row-ops');
			if(is_admin) {
				$('#tab_history #history-row-ops button').jqxButton({disabled: false});
				$('#tab_history #history-row-ops').fadeIn();
			}
		}*/
		selected_row = row;
	},

	/**
	 * Event: Table selection is cleared - Make the toolbar buttons dissapear
	 */
	select_Clear = function()
	{
/*		$('#tab_history #history-row-ops button').jqxButton({disabled: true});

		donrey.single_Delay('hide-history-row-ops', function() {
			$('#tab_history #history-row-ops').fadeOut();
		}, 500);*/

		selected_row = null;
	},

	/**
	 * Open a window with more information about the history entry (what was changed, how, etc)
	 */
	history_Details = function()
	{
		dd(selected_row);
//		$.post("?action=History_Details", {
//			org_id: selected_row.id
//		}, function(x) {
//		})
//		.fail(cargohub.its.http_fail);
	},

	/**
	 * Removes the entry
	 */
	history_Remove = function()
	{
		dd(selected_row.dt);
		$.post("?action=History_Remove", {
			rec_id: master.config.id,
			dt: parseInt(selected_row.dt.getTime()/1000)
		}, function(x) {
			master.refresh_DataTable($("#tab_history div.jqx-datatable"));
			selected_row = null;

			cargohub.components.spinner.On("Success!", x.response, 2500, function() {
				cargohub.components.spinner.Off();
			});
		})
		.fail(cargohub.its.http_fail);
	},

	/**
	 * Refreshes the metadata and the history grid
	 */
	refresh_Data = function()
	{
		var record_load_config = {
			track: master.config.track, // Record to load
			org_id: master.config.organization.id, // Viewed as organization...
			rt_id: master.config.type.real_id
		};

		$('#tab_history #rec-metadata').load("?action=Reload_Metadata", record_load_config, function(r, status, xhr) {
			if(status == 'error')
				cargohub.its.http_fail(xhr, "Record.History::History_Details");
		});

		// Refresh the history table as well
		master.refresh_DataTable($("#tab_history div.jqx-datatable"));
	},

	/**
	 * Initialize the Sharing tab toolbar (Admin operations)
	 */
	init_Toolbar = function()
	{
		$("#tab_history .toolbar button")
		.jqxButton({ theme: cargohub.jqxtheme })
		/**
		 * Event: Toolbar button is clicked
		 */
		.on('click', function()
		{
			switch($(this).data('op')) {
			// Reloads the tab
			case "history-refresh":
				refresh_Data(); break;
			case "history-remove":
				if(!is_admin)
					return;
				if(!confirm("Are you sure you want to delete this entry ?"))
					return;

				history_Remove(); break;
			}
		});
	},

	/**
	 * Initialize the Sharing DataTable. (This uses common functionality from the main Record Process module - cargohub.record_process )
	 */
	init_DataTable = function()
	{
		master.init_DataTable($("#tab_history div.jqx-datatable"), {
			// Config
			source: history_Source,
			columns: [
				{dataField: 'dt', text: "Log date/time", width: 160, cellsFormat: cargohub.datetime_format, filterable: false},
				{dataField: 'op', text: "Operation", width: 150},
				{dataField: 'op_info', text: "Operation details"},
				{dataField: 'user_name', text: "User name", width: 160},
				{dataField: 'org_name', text: "Organization", width: 160}
			],
			pageable: true,
			pageSize: 20
		}, {
			// Callbacks (for events happening in the datatable itself) - In most cases it will be either View or Edit
			"getrows": function() {},
			"rowselect": select_Row,
			"clearselect": select_Clear,
			"rowopen": history_Details
		}, "history");
	}
	;


	this._public({
		/**
		 * Setup Sharing Tab for Record Process module
		 */
		init: function()
		{
			init_Toolbar();
			init_DataTable();

			// Tooltips inside the Sharing tab
			$("#tab_history .info-tip").each(cargohub.components.ui.add_Tooltip);
		},

		/**
		 * External call to reload the history table (should happen after a lot of operations)
		 */
		reload: function()
		{
			refresh_Data();
		}
	});
});