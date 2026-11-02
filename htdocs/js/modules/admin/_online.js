/**
 * Module "Administration Center", Tab "Online Users"
 * - (will be renamed into "Dashboard" after the old Dashboard is replaced)
 * Instances:
 * - cargohub.baseclass.fullgrid
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2020.05.18
 */

cargohub.admin_online = cargohub.baseclass._instance(cargohub.baseclass.fullgrid, function() {
	// private
	var self = this,
		c = null,
		master = cargohub.admin_center,

	selected_ss = null, // Selected user in the grid

	// Source for the Online Users Grid
	source_Sessions = new $.jqx.dataAdapter({
		url: "?action=Sessions_List",
		datatype: "json",
		datafields: [
			{name: 'user_id', type: 'int'},
			{name: 'session_id', type: 'string'},
			{name: 'name', type: 'string', map: 'user>name'},
			{name: 'email', type: 'string', map: 'user>email'},
			{name: 'status', type: 'string', map: 'user>status'},
			{name: 'org_name', type: 'string', map: 'org>name'},
			{name: 'org_type', type: 'string', map: 'org>type_id'},
			{name: 'liftoffs', type: 'int', map: 'count_liftoffs'},
			{name: 'logon', type: 'date'},
			{name: 'dt', type: 'date'},
			{name: 'request_uri', type: 'string'},
			{name: 'ipaddr', type: 'string'}
		],
		id: "session_id",
		cache: false,
		filter: function() {
			$("#tab_online #online_grid").jqxGrid('updatebounddata', 'filter');
		},
		sort: function() {
			$("#tab_online #online_grid").jqxGrid('updatebounddata', 'sort');
		},
		beforeprocessing: function(source) {
			this.totalrecords = source.found_rows;
		},
		// Initial Sorting
		sortcolumn: 'dt',
		sortdirection: 'desc'
	}),

	sessions_Update_Interval = null,

	/**
	 * Sets up the users display/search grid
	 */
	setup_Online_Grid = function()
	{
		var
		// the column definition
		gridColumns = [
			{text: "Status", dataField: 'status', width: 80, filtertype: "checkedlist",
				filteritems: ["active", "online", "eula", "disabled", "invisible", "supervisor", "admin", "jumper"],
				cellclassname: "statusrender",
				cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
					return "<div class='"+ value.replace(/[ *!.0-9]/gi,'_') +"' title='"+ value +"'><sup>"+ value +"</sup></div>";
				},
				createfilterwidget: function (column, columnElement, widget) {
					widget.jqxDropDownList({
						dropDownWidth: 124,
						autoDropDownHeight: true,
						renderer: function (index, label, value) {
							if(value == "(Select All)")
								return value;
							else
								return "<div class='statusselect "+ value.replace(/[ *!.0-9]/gi,'_') +"'>"+ value +"</div>";
						},
						animationType: "fade"
					});
				}
			},
			{text: "Name", dataField: 'name'},
			{text: 'Rqs', dataField: 'liftoffs', width: 50, align: "right", cellsalign: "right", filtertype: "number"},
			{text: "Organization", dataField: 'org_name', width: 150, filtertype: "text"},
			{text: "Logon", dataField: 'logon', width: 110, cellsformat: "MMM-dd HH:mm", align: "center", cellsalign: "center", filtertype: "range"},
			{text: "E-Mail", dataField: 'email'},
/*			{text: "Login with", dataField: 'user', width: 100},*/
			{text: "Current Dept.", dataField: 'dep_name', width: 100},
//			{text: "Profile", dataField: 'count_profile_recs', width: 50, align: "right", cellsalign: "right", filtertype: "number"}
		];

		// ... and init the grid
		$("#tab_online #online_grid").jqxGrid({
			theme: cargohub.jqxtheme,
			source: source_Sessions,
			width: cargohub.fullwidth - 14, autoheight: true,
			columns: gridColumns, columnsresize: true, columnsmenu: false,
			selectionmode: "singlerow",	altrows: true,
			pageable: true,	pagesize: 20, pagesizeoptions: [10, 20, 50, 100],
			virtualmode: true, // virtual paging - Currently disabled as server side support is not yet implemented
			rendergridrows: function(params)
			{
				return params.data;
			},
			filterable: true, showfilterrow: true, sortable: true
		})
		/**
		 * Event: When a row is selected, the name of the organization is displayed on the toolbar, and is show with fade in (when selecting a row first time)
		 */
		.on('rowselect', function (event)
		{
			var row = event.args.row;
			$("span#selected_ss").html(row.name);
			if(!selected_ss) {
				$("#online_user_operations button").jqxButton({disabled: false});
				donrey.cancel_Delay('hide_bar_edit_ops');
				$('#online_user_operations').fadeIn();
			}
			selected_ss = row;
		})
		/**
		 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
		 */
		.on('filter', function(event)
		{
			if(selected_ss) {
				$(this).jqxGrid('clearselection');
				selected_ss = null;
				$("span#selected_ss").empty();
				$("#online_user_operations button").jqxButton({disabled: true});

				donrey.single_Delay('hide_bar_edit_ops', function() {
					$("#online_user_operations").fadeOut();
				}, 500);
			}
		})
		/**
		 * Event: On doubleclicking, do the same as simple clicking then using the View/Edit button
		 */
		.on('rowdoubleclick', function(){
			$('.toolbar button[data-op="edit"]').trigger("click");
		});

		// Setup Auto-update of sessions
		// TODO: UI Function to pause and resume auto-update
		sessions_Update_Interval = setInterval(function() {
			$("#tab_online #online_grid").jqxGrid("updatebounddata");
		}, 60000);

		$("#tab_online #online_grid").jqxGrid("updatebounddata");
	},

	/**
	 * Sets up the toolbar with operations related to users
	 */
	setup_Toolbar = function()
	{
		// Buttons to add/edit/remove an user from the department
		$('.toolbar button')
		.jqxButton({ theme: cargohub.jqxtheme })
		.on('click', function() {
			var data = $(this).data();

			switch(data.op) {
			// Simple export function
			case 'exportxlsx':
				$("#tab_online #online_grid").jqxGrid('exportdata', "xls", "grid-export", true, null, false, "/?action=export");
				break;

			// Logs in as the user to test the effect of the configuration changes
			case 'login_as':
				if(selected_ss.status == 'disabled')
					return cargohub.alert("User is currently disabled, not possible to login as the user");

				if(!confirm("This action will logout your own user. Proceed?"))
					return;

				$.post("/manager/user?action=login_as", {
					'id': selected_ss.user_id
				}, function(x) {
					document.location = x.redirect; // user's page after login
				})
				.fail(cargohub.its.http_fail);
				break;
			// Opens the user manager module with the specified user ID
			case 'edit':
				document.location = "/manager/user?id=" + selected_ss.user_id + '#configure';
				break;

			case 'disconnect':
				if(selected_ss.status == 'admin' || selected_ss.status == 'supervisor')
					return cargohub.alert("For safety reasons, it's not possible to disconnect an administrator or supervisor. Sorry !");

				if(!confirm("Are you sure you want to disconnect selected user ?"))
					return;

				$.post("?action=Session_Disconnect", {
					user_id: selected_ss.user_id,
					session_id: selected_ss.session_id
				}, function(x) {
					$("#tab_online #online_grid").jqxGrid("updatebounddata");

					cargohub.components.spinner.On("Success!", x.response, 2500, function() {
						cargohub.components.spinner.Off();
					});
				})
				.fail(cargohub.its.http_fail);
				break;
			}
		});
	};

	this.init = function()
	{
		setup_Online_Grid();
		setup_Toolbar();

		// Info Explander
		$(".info-expander").jqxExpander({
			theme: cargohub.jqxtheme,
			expanded: false
		});
	};
});