/**
 * Module "Administration Center", Tab "Docklets"
 * - (will be renamed into "Dashboard" after the old Dashboard is replaced)
 * Extends from:
 * - cargohub.baseclass.docklets
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2020.05.18
 */

cargohub.admin_docklets = cargohub.baseclass.docklets._extend(function() {
	// private
	var self = this,
		c = null,
		master = cargohub.admin_center,

	/**
	 * Opens a modal used to type in the notification message.
	 * -- Depending on subtype, a default is being used for title/content, but it can be edited.
	 * -- The Subtype cannot be changed in the window itself, another button needs to be pressed to send a different type of system-notification
	 */
	open_SysNotify_Modal = function(subtype, title)
	{
		cargohub.components.modal.create('sysntf', {
			btn_roles: {cancel: "cancel"}
		});

		var tpl_data = {
			'subtype': subtype
		}, imp_index = null, time = new Date();
		// Set defaults depending on subtype
		switch(subtype) {
		case 'message':
			tpl_data.importance = 'med'; imp_index = 1;
			break;
		case 'shutdown':
			tpl_data.importance = 'high'; imp_index = 0;
			time = new Date(time.valueOf() + time.getTimezoneOffset() * 60000);
			time.setHours(time.getHours()+1);
			tpl_data.title = "Server will shutdown for maintenance at "+$.format.date(time, "HH:mm:ss")+ " UTC.";
			tpl_data.description = "Please finish the work you are doing and exit your account, to avoid losing data.\nThank you for understanding.";
			break;
		case 'boot':
			tpl_data.importance = 'low'; imp_index = 2;
			tpl_data.title = "Server has restarted and is ready for operation";
			break;
		case 'updated':
			tpl_data.importance = 'med'; imp_index = 1;
			tpl_data.title = "The software has been updated to version 2.__ !";
			tpl_data.description = "Please read our release notes to see what is new";
			break;
		case 'error':
			tpl_data.importance = 'high'; imp_index = 0;
			tpl_data.title = "We have discovered an important error in module ____";
			tpl_data.description = "Our development team is working around the clock to resolve the problem.\nPlease do not use this module until it has been announced that the problem has been resolved." +
				"\n\nWe are sorry this has happened and hope your work isn't significantly affected";
			break;
		}

		cargohub.components.modal.open('sysntf', {
			width: 480, height: 300,
			title: "Send notification: "+ title,
			content: $('#template-syswide-ntf').html().present(tpl_data),
			callback: function($window) // Setup the controls inside the window
			{
				var importance_src = [
					{value: "high", text: "Very important information"},
					{value: "med", text: "General interest message"},
					{value: "low", text: "Low priority notification"}
				];

				$('#msg_importance', $window).jqxDropDownList({
					theme: cargohub.jqxtheme,
					source: importance_src, selectedIndex: imp_index, // Defaults to "Very important"
					displayMember: "text", valueMember: "value",
					width: 300,	height: 25,
					autoDropDownHeight: true
				});

				// Setup the action for "OK" button
				$('#modal_sysntf_ok', $window).off('click').on('click', function() {
					// submit the add/edit form using ajax
					$('.modal_content > form', $window).ajaxSubmit({
						dataType: 'json',
						beforeSubmit: function(formData, jqForm, options) {
							// setup the field checking object
							var f = {};
							for(var c=0; c< formData.length; c++)
								f[formData[c].name] = formData[c].value;

							if(f['title'].length < 10) {
								cargohub.alert("The title of the notification should have at least 10 characters.");
								return false;
							}

							return true;
						},
						success: function(x) {
							if(x.success) {
								$window.jqxWindow('close');
								cargohub.alert(x.response, "Success !", "thumb_up.png");
							} else {
								cargohub.alert(x.reason, "Message from server", "information.png");
							}
						}
					});
				});
			}
		}, self);
	},

	// Source for the Online Users DataTable
	source_Sessions = new $.jqx.dataAdapter({
		url: "?action=Get_Sessions",
		datatype: "json",
		datafields: [
			{name: 'user_id', type: 'int'},
			{name: 'name', type: 'string', map: 'user>name'},
			{name: 'email', type: 'string', map: 'user>email'},
			{name: 'org_name', type: 'string', map: 'org>name'},
//			{name: 'org_type', type: 'string', map: 'org>type_id'},
			{name: 'liftoffs', type: 'int', map: 'count_liftoffs'},
			{name: 'logon', type: 'date'},
			{name: 'dt', type: 'date'},
			{name: 'request_uri', type: 'string'},
			{name: 'ipaddr', type: 'string'}
		],
		id: "session_id",
		// Initial Sorting
		sortcolumn: 'dt',
		sortdirection: 'desc'
	}),

	sessions_Update_Interval = null,

	/**
	 * Setup the "Online Users" panel which contains a self-refreshing jqxDataTable with the logged users currently accessing the software.
	 * - Double-clicking on an user row opens more information ( Last URL accessed, IP Address, Session ID, and also allows forcibly removing that user's session - Effectively logging him out remotely )
	 */
	setup_Panel_Active_Sessions = function()
	{
		$("#table-online_users").jqxDataTable({
			theme: cargohub.jqxtheme,
			localization: {
				emptydatastring: "No users are online"
			},
			width: 372, height: 372,
			source: source_Sessions,
			selectionMode: "singleRow",
			sortable: true, filterable: true, filterMode: 'simple',
			columns: [
				{text: 'Name', dataField: 'name', width: "40%"},
				{text: 'Organization', dataField: 'org_name'},
				{text: 'Rqs', dataField: 'liftoffs', width: 35, cellsAlign: 'right'}
//				{hidden: true, text: 'Last Access', dataField: 'dt', cellsFormat: cargohub.datetime_format, align: "center", cellsAlign: 'center', filterable: false}
			]
		})
		.on('rowSelect', function(event) {
			dd(event.args);
		})
		.on('rowDoubleClick', function(event) {
			dd(event.args);
		})
		.on('filter', function(event) {
			$(this).jqxDataTable('clearSelection');
//			if(event_callbacks['clearselect'])
//				event_callbacks['clearselect']($container);
		});

		// Unless it's stopped, the panel will self-refresh every 60 seconds (1 minute):
		sessions_Update_Interval = setInterval(function() {
			$("#table-online_users").jqxDataTable('updateBoundData');
		}, 60000);
	},

	/**
	 * Setup the various buttons that are used on the page (some are detected automatically by their class, and custom actions can be defined by the template
	 */
	setup_Operations = function()
	{
		// the "any operation" buttons
		$(".command > button").on('click', function() {
			var data = $(this).data();
			switch(data.op) {
				// Opens a link specified in the action
				case 'link':
					document.location = data.link;
					break;

				// Send system-wide notifications
				case 'notify':
					open_SysNotify_Modal(data.subtype, $(this).parent().find('label').text());
					break;

				// Displays an alert with an arbitrary text
				case 'alert':
					cargohub.alert(data.text);
					break;
			}
		});

		// Speciality panels setup
//		setup_Panel_Active_Sessions();

		$(".info-tip").each(cargohub.components.ui.add_Tooltip);
	}
	;

	this._public({
		// section initialization
		init: function()
		{
			// Setup the docking-panel interface
			self.init_Docking($('#admin-dashboard'), {
				slices: 3,
				// List of panels which are added to the docker. They must exist in the DOM as the specified IDs, and are moved to the docker on Init
				// TODO: This config might be sent from PHP for flexibility
				panels_init: [
					{
						id: 'panel-statistics',
						slice: 0,
						height: 390
					},
					{
						id: 'panel-systemnotify',
						slice: 0,
						height: 300
					},
					{
						id: 'panel-configuration',
						slice: 1,
						height: 390
					},
					{
						id: 'panel-db_maintenance',
						slice: 1,
						height: 300
					},
					{
						id: 'panel-issue_type_count',
						slice: 2,
						height: 470
					},
					{
						id: 'panel-issue_monthly_count',
						slice: 2,
						height: 530
					}
				],
				// WHen windows are created, they are initialized without close buttons, but collapsible
				hide_close_buttons: true,
				show_collapse_buttons: true,
				// Activate Docklet persistence
				persistent: true,
				dock_id: "admin_dashboard",
				saved_layout: self.config.docklet_config
			}, {
				width: cargohub.fullwidth + 9
			});

			// extra operations that can be done on the dashboard
			setup_Operations();
		}
	});
});