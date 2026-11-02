/**
 * Module "Admin's Users Management"
 * -- Display all existing Users in the system ( For now, only admin has access to this. In the future, "HeadQuarters" organizations will have access to all users in their managed sub-organizations )
 */
cargohub.admin_users = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,
		selected_user = null, // Selected user in the grid
	/**
	 * Sets up the users display/search grid
	 */
	setup_Users_Grid = function()
	{
		var
		// the data definition
		gridDataFields = [
			{name: 'id', type: 'number'},
			{name: 'status'},
			{name: 'logon_dt', type: 'date'},
			{name: 'user', type: 'string'},
			{name: 'name', type: 'string'},
			{name: 'email', type: 'string'},
			{name: 'email_smtp', type: 'boolean'},
			{name: 'org_id', type: 'number'},
			{name: 'org_name', map: 'organization>name'},
			{name: 'dep_name', map: 'department>name'},
			{name: 'count_msgs', map: 'count>msgs', type: 'int'}, 
			{name: 'count_files', map: 'count>files', type: 'int'},
			{name: 'count_tasks', map: 'count>tasks', type: 'int'},
			{name: 'count_issues', map: 'count>issues', type: 'int'},
			{name: '2auth', type: 'string'},//IWI show user’s 2auth status 
//			{name: 'count_profile_recs', map: 'count>profile_recs', type: 'int'}
		],
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
			{text: "Organization", dataField: 'org_name', width: 150, filtertype: "list",
				filteritems: self.config.orgList,
				createfilterwidget: function (column, columnElement, widget) {
					widget.jqxDropDownList({
						dropDownWidth: 300,
						dropDownHeight: 500
					});
				}
			},
			{text: "Logon", dataField: 'logon_dt', width: 110, cellsformat: "MMM-dd HH:mm", align: "center", cellsalign: "center", filtertype: "range"},
			{text: "E-Mail", dataField: 'email', width: 150},
			{text: "SMTP", dataField: 'email_smtp', width: 50, columntype: "checkbox", filtertype: "list"},
/*			{text: "Login with", dataField: 'user', width: 100},*/
			{text: "Current Dept.", dataField: 'dep_name', width: 100},
			{text: "Msgs", dataField: 'count_msgs', width: 50, align: "right", cellsalign: "right", filtertype: "number"},
			{text: "Files", dataField: 'count_files', width: 50, align: "right", cellsalign: "right", filtertype: "number"},
			{text: "Tasks", dataField: 'count_tasks', width: 50, align: "right", cellsalign: "right", filtertype: "number"},
			{text: "Asgn.", dataField: 'count_issues', width: 50, align: "right", cellsalign: "right", filtertype: "number"},
			{text: "2auth", dataField: '2auth',width: 50,cellsalign: "center",},//IWI show user’s 2auth status 
//			{text: "Profile", dataField: 'count_profile_recs', width: 50, align: "right", cellsalign: "right", filtertype: "number"}
		],
		// prepare the data
		listGridAdapter = new $.jqx.dataAdapter({
			url: '?action=get_list',
			datatype: "json",
			datafields: gridDataFields,
			id: 'id',
			root: "data",
			// server side operations - filtering and sorting:
			cache: false,
			filter: function() {
				$("#users_grid").jqxGrid('updatebounddata', 'filter');
			},
			sort: function() {
				$("#users_grid").jqxGrid('updatebounddata', 'sort');
			},
			beforeprocessing: function(source) {
				this.totalrecords = source.found_rows;
			},
			// Initial Sorting
			sortcolumn: 'logon_dt',
			sortdirection: 'desc'
		});

		// ... and init the grid
		$("#users_grid").jqxGrid({
			theme: cargohub.jqxtheme,
			source: listGridAdapter,
			width: cargohub.fullwidth, autoheight: true,
			columns: gridColumns, columnsresize: true, columnsmenu: false,
			selectionmode: "singlerow",	altrows: true,
			pageable: true,	pagesize: 20, pagesizeoptions: [10, 20, 50, 100, 250, 1000],
			virtualmode: true, // virtual paging
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
			$("span#selected_user").html(row.name);
			if(!selected_user) {
				$("#user_edit_operations button").jqxButton({disabled: false});
				donrey.cancel_Delay('hide_bar_edit_ops');
				$('#user_edit_operations').fadeIn();
			}
			selected_user = row;
		})
		/**
		 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
		 */
		.on('filter', function(event)
		{
			if(selected_user) {
				$(this).jqxGrid('clearselection');
				selected_user = null;
				$("span#selected_user").empty();
				$("#user_edit_operations button").jqxButton({disabled: true});

				donrey.single_Delay('hide_bar_edit_ops', function() {
					$("#user_edit_operations").fadeOut();
				}, 500);
			}
		})
		/**
		 * Event: On doubleclicking, do the same as simple clicking then using the View/Edit button
		 */
		.on('rowdoubleclick', function(){
			$('.toolbar button[data-op="edit"]').trigger("click");
		});
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
				$("#users_grid").jqxGrid('exportdata', "xls", "grid-export", true, null, false, "/?action=export");
				break;

			// Logs in as the user to test the effect of the configuration changes
			case 'login_as':
				if(selected_user.status == 'disabled')
					return cargohub.its.alert("User is currently disabled, not possible to login as the user");

				if(confirm("This action will logout your own user. Proceed?")) {
					$.post("/manager/user?action=login_as", {
						'id': selected_user.id
					}, function(x) {
						document.location = x.redirect; // user's page after login
					})
					.fail(cargohub.its.http_fail);
				}
				break;
			// Opens the user manager module with the specified user ID
			case 'edit':
				document.location = "/manager/user?id=" + selected_user.id + '#configure';
				break;
			// Opens the contacts editor with the specified user ID (to edit any user's contacts)
			case 'contacts':
				document.location = "/manager/contacts?user_id=" + selected_user.id + '#search';
				break;
			// Opens the new Task Manager in user-editing mode
			case 'tasks':
				document.location = "/manager/tasks?user_id=" + selected_user.id;
				break;

			// Opens the Organization module into admin edit mode with the currently selected user's organization, and link directly to users tab
			case 'organization':
				document.location = "/manager/organization?id=" + selected_user.org_id + '#users';
				break;

			case 'delete':
				// TODO: Put the same limitation in user manager module as well, admin users can't be deleted, not even by admin itself.
				if(selected_user.status == 'admin' || selected_user.status == 'supervisor')
					return cargohub.its.alert("For safety reasons, it's not possible to delete an administrator or supervisor. Sorry !");

				if(!confirm("Are you sure you want to remove selected user ?"))
					return;

				$.post("/manager/user?action=delete", {
					id: selected_user.id
				}, function(x) {
					if(x.success)
						$("#users_grid").jqxGrid("updatebounddata");
					else
						cargohub.its.alert(x.reason, "Message from server", "events.png");
				});
				break;
			}
		});
	};

	this._public({
		/**
		 * Section initialization
		 */
		init: function()
		{
			setup_Users_Grid();
			setup_Toolbar();

			// Info Explander
			$(".info-expander").jqxExpander({
				theme: cargohub.jqxtheme,
				expanded: false
			});
		}
	});
});
