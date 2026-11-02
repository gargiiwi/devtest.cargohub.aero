/**
 * Module "Organization Manager" - Used both by admin (to control organizations, but also org. users with certain privileges)
 * - With no provileges, the "Overview" tab is always showing
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2.7
 */
cargohub.organization_manager = donrey.BASE._extend(function() {
	// private
	var self = this,
	c = null,
	// state of the pop-up windows used by this page - Used for lazy initialization ( on first use )
	popups = {},
	// Flags
	org_Userlist_Loaded = false, 

	/**
	 * The rebuilt Departments module (without the editing part, just the list of departments as a selector in left side, and data in right side)
	 * -- Editing is moved to another module, which has it's own tabs
	 */
	setup_Tab_Deps = function()
	{
		var selected_dep = null,
		/**
		 * Shows the modal window used to create new department
		 */
		open_Add_Dep_Window = function()
		{
			if(!popups.dep_add) {
				cargohub.its.init_Modal_Window('dep-add', null, {"cancel": "cancel"});
				popups.dep_add = true;
			}
			cargohub.its.open_Modal_Window('dep-add', 500, 240, "Create new Organization Department", $("#template_add-dep").html(), function($window) // Setup the controls inside the window
			{
				// Setup the user selector (for picking chief)
				$('#dep_chief', $window).jqxComboBox({
					theme: cargohub.jqxtheme,
					source: self.config.count.users > 0 ? self.org_Userlist(): null,
					displayMember: "name", valueMember: "id",
					width: 308,	height: 23,
					autoDropDownHeight: self.config.count.users < 7,
					dropDownHeight: self.config.count.users >= 7? 177: null,
					searchMode: "containsignorecase",
					placeHolder: "(type to search or click arrow to show all)",
					autoComplete: true
				});

				// Setup the action for "OK" button
				$('#modal_dep-add_ok', $window).off('click').on('click', function() {
					// submit the add/edit form using ajax
					$('.modal_content form', $window).ajaxSubmit({
						dataType: 'json',
						beforeSubmit: function(formData, jqForm, options) {
							// setup the field checking object
							var f = {};
							for(var c=0; c< formData.length; c++)
								f[formData[c].name] = formData[c].value;

							if(f['dep[name]'].length < 5) {
								cargohub.alert("The department's name must have at least 5 characters.");
								return false;
							}
/*
// Not really mandatory
							if(!f['dep[chief_id]']) {
								cargohub.alert("Please choose the initial department chief.");
								return false;
							}
*/

							// Add the organization's ID to the posted data
							formData.push({
								name: "org_id",
								value: self.config.id
							});

							return true;
						},
						success: function(x) {
							if(x.success) {
								// auto-redirect to the edit-mode for the newly created department
								document.location = "/manager/department?id=" + x.iid;
							} else {
								cargohub.alert(x.reason, "Message from server", "information.png");
							}
						}
					});
				});

			});
		},

		/**
		 * Prepares the toolbar for the departments tab
		 */
		setup_Deps_Toolbar = function()
		{
			// Buttons to add/edit/remove an user from the department
			$('div#tab_deps .toolbar button')
			.jqxButton({ theme: cargohub.jqxtheme })
			.on('click', function() {
				switch($(this).data('op')) {
					// Open the domain editor with blank data, with the purpose of creating a new domain for the organization
					case 'dep-add':

//						if(self.config.count.users > 0)
							open_Add_Dep_Window();
//						else
//							cargohub.alert("It is not possible to create a department if the organization has no users. Please create an user first!");
						break;

					// Open the domain editor with data for the selected domain
					case 'dep-edit':
						document.location = "/manager/department?id=" + selected_dep.id;
						break;

					// Deletes a department which will disconnect all users and remove any access overrides
					case 'dep-del':
						cargohub.components.modal.delconfirm(function() {
							$.post("/manager/department?action=delete", {
								id: selected_dep.id
							}, function(x) {
								if(x.success)
									document.location.reload();
								else {
									cargohub.alert(x.reason, "Message from server", "events.png");
								}
							})
							.fail(cargohub.its.http_fail);
						}, "Are you REALLY sure you want to remove this department?");
						// "Any existing settings and access overrides for all users will be deleted,\nand the users will revert to organization-wide access level !"
						break;
				}
			});
		},

		/**
		 * What happens when a department is selected in the left-side list
		 */
		event_Select_Dep = function(e)
		{
			var dep = e.args.item.originalItem;
			$("div#tab_deps #dep_view").html($("#template_loading").html().present(dep));

			// unselect (disable) edit/delete buttons while waiting to load, and reenable it when dep detail is loaded
			if(cargohub.application.user_Test_Privileges('org_deps_manager')) {
				selected_dep = null;
				$("div#tab_deps div#selected_dep").empty();
				$("div#tab_deps #dep_edit_operations button").jqxButton({disabled: true});
			}

			$.get("/manager/department?action=get_details&id="+ dep.id, function(dep_details, textStatus, jqXHR) {

				if(dep_details instanceof Object && dep_details.success == false) {
					$("div#tab_deps #dep_view").html("<h1 style='text-align: center;'>"+dep_details.reason+"</h1>");
					cargohub.alert(dep_details.reason, "Message from server", "events.png");
					return;
				}
				// Load extra data which was sent for the JS part, if it exists
				donrey.read_JSON_Headers(jqXHR, dep);

				if(dep.id == 0)
					cargohub.alert("Department not found, possibly database error or it was already deleted");
				else {
					// content loaded, show it
					$("div#tab_deps #dep_view").html(dep_details);

					if(cargohub.application.user_Test_Privileges('org_deps_manager')) {
						// Display the buttons to edit/delete
						$("div#tab_deps div#selected_dep").html(dep.name);
						if(!selected_dep) {
							$("div#tab_deps #dep_edit_operations button").jqxButton({disabled: false});
							$('div#tab_deps #dep_edit_operations').fadeIn();
						}
					    selected_dep = dep;
					}
				}
			});
		},

		/**
		 * Sets up the splitter for the module's 2-panel display
		 */
		setup_Splitter = function()
		{
			$("div#tab_deps div#departments_splitter").jqxSplitter({ theme: cargohub.jqxtheme, width: cargohub.fullwidth - 14, height: 500, panels: [{
				collapsible: false,
				min: 250,
				size: 250
			}, {
				min: 600,
				collapsible: false
			}] });
		},

		/**
		 * Sets up the left side of splitter, containing the department list for current organization
		 */
		setup_Deps_Quicklist = function()
		{
			var deps_quicklist_config = {
				theme: cargohub.jqxtheme,
				width: '100%', height: '100%',
				source: self.config.dataSources.deps,
				displayMember: 'name', valueMember: 'id',
				filterable: true, searchMode: 'containsignorecase'
//				renderer: function (index, label, value) {
//					var dep = self.config.dataSources.deps[index];
//					// TODO: Add number of members if the user is allowed to see that
//					return dep.name;
//				}
			};

			$('div#tab_deps div#deps_quicklist')
				.jqxListBox(deps_quicklist_config)
				.on('select', event_Select_Dep);
		};

		// the buttons and their actions only exist for deps manager or admin
		if(cargohub.application.user_Test_Privileges('org_deps_manager'))
			setup_Deps_Toolbar();

		// The departments selector and quick is set up only when departments actually exist
		if(self.config.features.departments && self.config.features.departments > 0) {
			setup_Splitter();
			setup_Deps_Quicklist();
		}
	},

	/**
	 * The Organization's user account list, which replaces the older "Users" global module.
	 * -- Editing is moved to another module, which has it's own tabs
	 */
	setup_Tab_Users = function()
	{
		var selected_user = null,

		/**
		 * Shows the modal window used to create new department
		 */
		open_Add_User_Window = function()
		{
			if(!popups.user_add) {
				cargohub.its.init_Modal_Window('user-add', null, {"cancel": "cancel"});
				popups.user_add = true;
			}
			cargohub.its.open_Modal_Window('user-add', 440, 350, "Create a new user account for " + self.config.name, $("#template_add-user").html(), function($window) // Setup the controls inside the window
			{
				// The random password generator
				$('#gen_password', $window).jqxButton({
					theme: cargohub.jqxtheme,
					width: 210
				})
				/**
				 * Event: A new random password is requested
				 */
				.on('click', function() {
					if($('#usr_password', $window).val())
						if(!confirm("Are you sure you want to replace the current password?"))
							return;

					var pwd = donrey.generate_UniqId('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*()-=[];/\\+', 12);
					$('#usr_password, #usr_passwordr', $window).val(pwd).attr('type', "text");
				});

				// Send notification Active organization selector - Determines if submitting an issue to this organization gives the choice of sending it by e-mail
				$('#welcome_message', $window).jqxSwitchButton({
					theme: cargohub.jqxtheme,
					height: 25, width: 75,
					checked: false
				});

				// Setup the action for "OK" button
				$('#modal_user-add_ok', $window).off('click').on('click', function() {
					// submit the add/edit form using ajax
					$('.modal_content > form', $window).ajaxSubmit({
						dataType: 'json',
						beforeSubmit: function(formData, jqForm, options) {
							// setup the field checking object
							var f = {};
							for(var c=0; c< formData.length; c++)
								f[formData[c].name] = formData[c].value;

							if(f['usr[user]'].length < 4) { //  || !/^[a-z0-9_]+$/i.test(f['usr[user]'])
								cargohub.alert("The account ID must have at least 4 characters"); // and contain only english letters and numbers
								return false;
							}

							if(!f['usr[password]']) {
								cargohub.alert("Please type a password.");
								return false;
							}

							if(!f['usr[passwordr]']) {
								cargohub.alert("For safety reasons, you have to enter the password again.");
								return false;
							}

							if(f['usr[password]']!=f['usr[passwordr]']) {
								cargohub.alert("The two passwords to not match, please type them again.");
								return false;
							}

							if(f['usr[name]'].length < 5) {
								cargohub.alert("Please enter at least 5 characters for the person's name.");
								return false;
							}

							if(!f['usr[email]'] || !validate_email(f['usr[email]'])) {
								cargohub.alert("The user must have a valid e-mail address.");
								return false;
							}

							// Add the value of the "send_welcome_message" to the posted data
							formData.push({
								name: "welcome_message",
								value: $('#welcome_message', $window).val()
							});

							// Add the organization's ID to the posted data
							formData.push({
								name: "org_id",
								value: self.config.id
							});

							return true;
						},
						success: function(x) {
							if(x.success) {
								// auto-redirect to the user view/edit module for the newly created user, to edit more information about the user (signature, department membership, contact information, etc.)
								document.location = "/manager/user?id=" + x.iid;
							} else {
								cargohub.alert(x.reason, "Message from server", "information.png");
							}
						}
					});
				});

			});
		},

		/**
		 * Prepares the toolbar for the users tab
		 */
		setup_Users_Toolbar = function()
		{
			// Buttons to add/edit/remove an user from the department
			$('div#tab_users .toolbar button')
			.jqxButton({ theme: cargohub.jqxtheme })
			.on('click', function() {
				switch($(this).data('op')) {
					// Open the domain editor with blank data, with the purpose of creating a new domain for the organization
					case 'user-add':
						open_Add_User_Window();
						break;

					// Open the domain editor with data for the selected domain
					case 'user-edit':
						document.location = "/manager/user?id=" + selected_user.id;
						break;

					// Deletes this user, orphaning everything that was created by this user
					case 'user-del':
						if(confirm("Are you sure you want to delete this user account?\n\nAll the data that has been associated with this user will be deleted and is not recoverable !")) {
							$.post("/manager/user?action=delete", {
								id: selected_user.id
							}, function(x) {
								if(x.success)
									document.location.reload();
								else {
									cargohub.alert(x.reason, "Message from server", "events.png");
								}
							});
						}
						break;

					case 'login-as':
						if(selected_user.status == 'disabled') {
							cargohub.alert(selected_user.name +" is currently disabled, not possible to login as the user");
							break;
						}

						if(confirm("Logout as yourself and auto-login as "+ selected_user.name +"?")) {
							$.post("/manager/user?action=login_as", {
								'id': selected_user.id
							}, function(x) {
								document.location = x.redirect; // user's page after login
							})
							.fail(cargohub.its.http_fail);
						}
						break;
				}
			});

		},

		/**
		 * What happens when an user is selected in the left-side list
		 */
		event_select_user = function(e)
		{
			var user = e.args.item.originalItem;
			$("div#tab_users #user_view").html($("#template_loading").html().present(user));

			if(cargohub.application.user_Test_Privileges('org_user_manager')) {
				// unselect (disable) edit/delete buttons while waiting to load, and reenable it when user detail is loaded
				selected_user = null;
				$("div#tab_users div#selected_user").empty();
				$("div#tab_users #user_edit_operations button").jqxButton({disabled: true});
			}

			$.get("/manager/user?action=get_details&id="+ user.id +"&acls=calculated", function(user_details, textStatus, jqXHR) {

				if(user_details instanceof Object && user_details.success == false) {
					$("div#tab_users #user_view").html("<h1 style='text-align: center;'>"+user_details.reason+"</h1>");
					cargohub.alert(user_details.reason, "Message from server", "events.png");
					return;
				}
				// Load extra data which was sent for the JS part, if it exists
				donrey.read_JSON_Headers(jqXHR, user);

				if(user.id == 0)
					cargohub.alert("User not found, possibly database error or he/she was already deleted");
				else {
					// content loaded, show it
					$("div#tab_users #user_view").html(user_details);
					if(cargohub.application.user_Test_Privileges('org_user_manager')) {
						// Display the buttons to edit/delete
						$("div#tab_users div#selected_user").html(user.name);
						if(!selected_user) {
							$("div#tab_users #user_edit_operations button").jqxButton({disabled: false});
							$('div#tab_users #user_edit_operations').fadeIn();
						}
						selected_user = user;
					}
				}
			});
		},

		/**
		 * Sets up the splitter for the module's 2-panel display
		 */
		setup_Splitter = function()
		{
			$("div#tab_users div#users_splitter").jqxSplitter({ theme: cargohub.jqxtheme, width: cargohub.fullwidth - 14, height: 500, panels: [{
				collapsible: false,
				min: 240,
				size: 280
			}, {
				min: 600,
				collapsible: false
			}] });
		},

		/**
		 * Sets up the left side of splitter, containing the department list for current organization
		 */
		setup_Users_Quicklist = function()
		{
			var users_quicklist_config = {
				theme: cargohub.jqxtheme,
				width: '100%', height: '100%',
				source: self.config.count.users > 0 ? self.org_Userlist(): null,
				displayMember: 'name', valueMember: 'id',
				filterable: true, searchMode: 'containsignorecase',
				renderer: function (index, label, value) {
					if(index == -1) {
						return "<span style='background-color: #99a; color: white; padding: 4px 0 6px 15px; display: inline-block; width: 100%; text-shadow: 2px 2px 3px black;'>"+ label.replace('...','…')+ "</span>";
					} else {
						var usr = self.userlist_Source.records[index],
							userdisplay = "<img src='/img/icons16/user_statuses/" + usr.status + ".png' alt='"+ usr.status +"' style='float: left; margin-right: 4px;'> "+ usr.name.substring(0, 27)+ (usr.name.length > 27?' …':'');
						return userdisplay;
					}
				}
			};

			$('div#tab_users div#users_quicklist')
				.jqxListBox(users_quicklist_config)
				.on('select', event_select_user);
		};

		if(cargohub.application.user_Test_Privileges('org_user_manager'))
			setup_Users_Toolbar();

		if(self.config.count.users > 0) {
			setup_Splitter();
			setup_Users_Quicklist();
		}
	},

	setup_Tab_Partners = function()
	{
		var selected_partner = null,

	  	/**
	  	 * Setup the window used to create a partnership link
	  	 * - Very similar to the connect group window, but this one displays organizations and not groups
	  	 * - Before the list of possible partners are displayed, a type must be chosen first ( org_types )
	  	 */
	  	setup_Partner_Create_Link = function($window)
	  	{
	  		// The drop-down-list with checkboxes (gets reset with org type change, also affected by the select all/none/invert buttons)
			var $ddl = $("#partner-orgs_select", $window);

			// The organization type selector
			$("#partner-org_type", $window).jqxDropDownList({
				theme: cargohub.jqxtheme,
				source: self.config.dataSources.orgtypes,
				displayMember: "name", valueMember: "id",
				width: 356, height: 25,
				autoDropDownHeight: true,
				placeHolder: "Select organization type:"
			})
			/**
			 * Event: An organization type is chose, this affects the second dropdownlist (the $ddl)
			 */
			.on('change', function(event) {
				// Activate the organization type list
				$ddl.jqxDropDownList({
					disabled: false,
					source: new $.jqx.dataAdapter({
						url: "?action=get_unconnected_orgs", // Get potential partners (all which are not yet connected)
						data: {
							type_id: event.args.item.value
						},
						datatype: "json",
						datafields: [
							{ name: 'id', type: 'number' },
							{ name: 'name' }
						],
						id: 'id'
					}),
					placeHolder: "Choose organiztions"
				});
				// ... and enable the buttons
				$("div.multiselect_buttons button", $window).jqxButton({
					disabled: false
				}).jqxButton('render');
			});

			$ddl.jqxDropDownList({
				theme: cargohub.jqxtheme,
				disabled: true,
				displayMember: "name", valueMember: "id",
				width: 356, height: 25,
				checkboxes: true,
				placeHolder: "(Select organization type first)"
			});

			$("div.multiselect_buttons button", $window).each(function() {
				// The buttons start as disabled, until an organization type is chosen.
				$(this).jqxButton({
					theme: cargohub.jqxtheme,
					disabled: true
				})
				.on("click", function() {
					var cmd = $(this).data('cmd');
					switch(cmd) {
						case 'check_all':
							$ddl.jqxDropDownList('checkAll');
							break;
						case 'clear_all':
							$ddl.jqxDropDownList('uncheckAll');
							break;
						case 'check_invert':
							var items = $ddl.jqxDropDownList('getItems');
							for(c=0; c< items.length; c++)
								$ddl.jqxDropDownList(items[c].checked? 'uncheckIndex': 'checkIndex', c);
							break;
					}
				});
			});

			// Setup the action for "OK" button
			$('#modal_par-link_ok', $window).off('click').on('click', function() {
				var selected_partners = $("#partner-orgs_select", $window).val();

				if(!selected_partners)
					return cargohub.alert("You didn't select any organizations or all have already been connected", "Warning", "error.png");

				$.post("?action=link_partners", {
					type: $("#partner-org_type", $window).val(),
					id_list: selected_partners,
					comment: $('#partner_comment', $window).val()
				}, function(x) {
					if(x.success) {
						$window.jqxWindow('close');
						$("#tab_partners #partners-grid").jqxGrid('updatebounddata');
						cargohub.alert("Partners have been connected", "Success !", "thumb_up.png");
					} else {
						cargohub.alert(x.reason, "Message from server", "events.png");
					}
				});
			});
	  	},

		/**
		 * Setup the view partner information window
		 * - loads detailed information about a partnership, read only mode
		 */
		setup_Partner_View_Link = function($window, partner)
		{
			$("td#partner_details_description", $window).load("?action=get_partnership_details", {
				org_id: self.config.id,
				partner_id: partner.id
			});
		},

		/**
		 * Setup the partnership editor
		 * - Under construction (It should allow changing description and editing relation data, like auto-share... etc.
		 */
		setup_Partner_Edit_Link = function($window, partner)
		{
//			dd(partner);
		},

		/**
		 * Setup the partners sub-module toolbar
		 */
		setup_Partners_Toolbar = function()
		{
			var tpl_data = {_itc: cargohub.input_theme_classes};

			$("#tab_partners .toolbar button")
			.jqxButton({ theme: cargohub.jqxtheme })
			.on('click', function()
			{
				switch($(this).data('op')) {
					// Choose a partner from the list of all organizations in the system, with the purpose of linking it to the current organization
					// Phase 1: First choose the organization type (fixed list, like in /admin/orgs)
					// Phase 2: Organizations of that type are loaded in the second selector, but only those who are not already linked to this organization
					case 'par-link':
						if(!popups.par_link) {
							cargohub.its.init_Modal_Window('par-link', null, {"cancel": "cancel"});
							popups.par_link = true;
						}
						cargohub.its.open_Modal_Window('par-link', 400, 300, "Connect partners",
						$("#template_partner_connect").html().present(tpl_data), setup_Partner_Create_Link);
						break;

					// Edit the partnership information
					case 'par-edit':
//						dd(selected_partner);
//						?? = clone(selected_partner);

//						cargohub.its.open_Modal_Window('partner-editor', 400, 300, "Edit partnership with "+tpl_data.name,
//							$("#template_partner_editor").html().present(tpl_data), setup_Partner_Edit_Link, {
//								id: selected_partner.id
//							});
						break;

					// Unlink partner, disallowing sharing/transfer
					// TODO: This should also disconnect all shared issues (affect shared_org_ids field in the "issues" table)
					case 'par-unlink':
						if(confirm("Are you sure you want to unlink this partner?")) {
							$.post("?action=unlink_partner", {
								id: selected_partner.id
							}, function(x) {
								if(x.success)
									document.location.reload();
								else {
									cargohub.alert(x.reason, "Message from server", "events.png");
								}
							});
						}
						break;

					// Allow users with access right "org_partners_view" to see the relation data between their org and the partner (but not modify anything - YET)
					case 'par-view':
//						dd(selected_partner);

//						var tpl_data = clone(selected_partner);
//						cargohub.its.open_Modal_Window('partner-details', 600, 300, "Viewing relation with "+tpl_data.name,
//							$("#template_partner_details").html().present(tpl_data), setup_Partner_View_Link, {
//								id: selected_partner.id
//							});
						break;
				}
			});
		},
		/**
		 * Sets up the partners information grid
		 */
		setup_Partners_Grid = function()
		{
			// Initialize the partners grid
			var // partner_type_list = [],
				gridDataAdapter = new $.jqx.dataAdapter({
					url: '?action=get_partners',
					datatype: "json",
					datafields: [
						{name: 'id', type: 'int', map: 'partner>id'},
						{name: 'name', type: 'string'},
						{name: 'type', type: 'string', map: 'partner>type'},
						{name: 'type_name', type: 'string', map: 'partner>type_name'},
						{name: 'description', type: 'string'},
						{name:	'shared', type: 'int', map: 'issues>shared'},
						{name:	'sent_total', type: 'int', map: 'issues>sent_total'}
					],
					id: 'partner_id'
				});

//			if(self.config.dataSources.orgtypes)
//				for(c = 0; c< self.config.dataSources.orgtypes.length; c++)
//					partner_type_list.push(self.config.dataSources.orgtypes[c].name);

			$("#tab_partners #partners-grid").jqxGrid({
				theme: cargohub.jqxtheme,
				width: cargohub.fullwidth - 14, autoheight: true,
				source: gridDataAdapter,
				columns: [
					{ text: "Partner Type", dataField: 'type_name', width: 160,
						filtertype: "checkedlist", // filteritems: partner_type_list,
						createfilterwidget: function (column, columnElement, widget) {
							widget.jqxDropDownList({
								dropDownWidth: 180,
								autoDropDownHeight: true,
								animationType: "fade"
							});
						}
					},
					{ text: "Name", dataField: 'name', width: 250 },
					{ text: "Short description", dataField: 'description' },
					{ text: "Shared issues", dataField: 'shared', width: 110, align: "right", cellsalign: "right", filtertype: "number" },
					{ text: "Total sent", dataField: 'sent_total', width: 110, align: "right", cellsalign: "right", filtertype: "number" }
				], columnsmenu: false, columnsresize: true,
				pageable: true,	pagesize: 20, pagesizeoptions: [10, 20, 50, 100],
				sortable: true, filterable: true, showfilterrow: true,
				altrows: true, selectionmode: "singlerow",

				theme: cargohub.jqxtheme,
			})
			/**
			 * Event: When a row is selected, the partner name is displayed on the toolbar, beside buttons to edit/delete, and all of it is show with fade in
			 */
			.on('rowselect', function (event)
			{
			    var row = event.args.row;
				$("div#tab_partners div#selected-partner").html(row.name);

				if(!selected_partner) {
					donrey.cancel_Delay('hide_bar_partner-row-ops');

					$('div#tab_partners div#par-operations button').jqxButton({disabled: false});
					$('div#tab_partners div#par-operations').fadeIn();
				}
				selected_partner = row;
			})
			/**
			 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
			 */
			.on('filter', function(event)
			{
				if(selected_partner) {
					$(this).jqxGrid('clearselection');
					selected_partner = null;
					$("div#tab_partners div#selected-partner").empty();
					$('div#tab_partners div#par-operations').fadeOut();
					$('div#tab_partners div#par-operations button').jqxButton({disabled: true});

					donrey.single_Delay('hide_bar_partner-row-ops', function() {
						$('div#tab_partners div#par-operations').fadeOut();
					}, 500);
				}
			})
			/**
			 * Event: On doubleclicking, do the same as simple clicking then using the Edit or View button
			 */
			.on('rowdoubleclick', function() {
				if(cargohub.application.user_Is_Admin())
					$('div#tab_partners .toolbar button[data-op="par-edit"]').trigger("click");
				else
					$('div#tab_partners .toolbar button[data-op="par-view"]').trigger("click");
			});
		};

		setup_Partners_Toolbar();
		setup_Partners_Grid();
	},

	/**
	 * The ACLs tab, used to edit organization level privileges and other access lists
	 */
	setup_Tab_ACLs = function()
	{
		cargohub.components.acls_editor.setup({
			container: $("#tab_acls"),
			acls: self.config.acls
		}, self);

		// Button to save ACL data
		$('#tab_acls .toolbar button')
		.jqxButton({ theme: cargohub.jqxtheme })
		.on('click', function() {
			switch($(this).data('op')) {
				// Saves the modified ACL data
				case 'acls-submit':
					$.post("?action=update_acls", {
						acls: JSON.stringify(cargohub.components.acls_editor.read())
					}, function(x) {
						if(x.success)
							document.location.reload();
						else {
							cargohub.alert(x.reason, "Message from server", "events.png");
						}
					});
					break;
			}
		});
	},

	/**
	 * Setup the tabs that display attached groups to the organization ( one tab per group type ).
	 * Usually there is only one group type per organization, but there might be more in some special cases.
	 */
	setup_Tab_Domains = function()
	{
		var selected_domain = null,

		/**
		 * Every time the window is opened, it's content is cleared and reloaded with data, so setup jqx Control for the current data
		 */
		setup_Domain_Editor_Content = function($editor, config)
		{
			//=== HTML settings ===//

			var
			// css themes (fixed list for now)
			css_themes = [
				{value: "default", text: "Cargohub (Default)"}
				// These are disabled because the styles are incomplete
//				{value: "desert", text: "Scorched desert"},
//				{value: "mask", text: "Evil blue"}
			], current_theme_idx = 0,
 			// Defaults for some of the controls
			defaults = {
				footer: false, // hardcode for now - This should be the setting which is defined in cargoits.settings.yaml
				ist_select: false,
				group_select: false,
				strings_index: 0,
				auto_sharing_index: 0
			};

			// determine current theme
			if(config && config.html_settings && config.html_settings.css_theme)
				for(c=0; c<css_themes.length; c++)
					if(config.html_settings.css_theme == css_themes[c].value) {
						current_theme_idx = c;
						break;
					}

			$('#edit_dom_html_css_theme', $editor).jqxDropDownList({
				theme: cargohub.jqxtheme,
				source: css_themes, selectedIndex: current_theme_idx,
				displayMember: "text", valueMember: "value",
				width: 238, height: 25,
				autoDropDownHeight: true
			});

			$('#edit_dom_html_footer', $editor).jqxSwitchButton({
				theme: cargohub.jqxtheme,
				height: 25, width: 75,
				checked: config && config.html_settings && config.html_settings.footer && config.html_settings.footer == true || defaults.footer
			});

			//=== Public area settings ===//

			var
			// Introduction string set (fixed list for now) - This will be changed, not flexible
			string_sets = [
				{value: "index.issue", text: "Issues (multiple)"},
				{value: "index.claim", text: "Cargo Claim"},
				{value: "index.damage", text: "Damage Report"}
			],
			// Auto-sharing options (Was named "ownership", still stored as such in the database)
			auto_sharing = [
				{value: "org", text: "This organization"},
				{value: "group", text: "Group's primary handler"},
				{value: "nobody", text: "None (E-mail based only)"},
 			];

			// determine current theme
			var strings_index = defaults.strings_index;
			if(config && config.public_settings && config.public_settings.string_set)
				for(c=0; c<string_sets.length; c++)
					if(config.public_settings.string_set == string_sets[c].value) {
						strings_index = c;
						break;
					}

			$('#edit_dom_pub_string_set', $editor).jqxDropDownList({
				theme: cargohub.jqxtheme,
				source: string_sets, selectedIndex: strings_index,
				displayMember: "text", valueMember: "value",
				width: 238, height: 25,
				autoDropDownHeight: true
			});

			// IST option and choice(s)
			var ist_option = config && config.public_settings && config.public_settings.ist && config.public_settings.ist.select == true || defaults.ist_select,
			ist_row_change = function() {
				// The Issue Type multi-checkbox dropdown
				$('#edit_dom_public_ist', $editor).jqxDropDownList('clearSelection');
				$('#edit_dom_public_ist', $editor).jqxDropDownList({
					promptText: ist_option? "Choose from issue types": "Choose the issue type",
					checkboxes: ist_option
				});
				$('#edit_dom_public_ist', $editor).jqxDropDownList('setContent', ist_option? "Choose from issue types": "Choose the issue type");

				if(ist_option) {
					// Select existing Issue Types valid for this domain and the label for the selector
					if(config && config.public_settings && config.public_settings.ist && config.public_settings.ist.select)
						for(c=0; c< self.config.ist_source.length; c++) {
							if($.inArray(self.config.ist_source[c].id, config.public_settings.ist.list) > -1)
								$('#edit_dom_public_ist', $editor).jqxDropDownList('checkIndex', c);
						};
					$('#label_ist_select', $editor).html("Issue types:");
				} else {
					if(config && config.public_settings && config.public_settings.ist && !config.public_settings.ist.select)
						for(c=0; c< self.config.ist_source.length; c++) {
							if(self.config.ist_source[c].id == config.public_settings.ist.auto)
								$('#edit_dom_public_ist', $editor).jqxDropDownList('selectIndex', c);
						}
					$('#label_ist_select', $editor).html("Auto issue type:");
				}
			};
			$('#edit_dom_ist_select', $editor).jqxSwitchButton({
				theme: cargohub.jqxtheme,
				height: 25, width: 75,
				checked: ist_option
			}).on('change', function(event) {
				ist_option = event.args.check;
				ist_row_change();
			});

			$('#edit_dom_public_ist', $editor).jqxDropDownList({
				theme: cargohub.jqxtheme,
				source: self.config.ist_source,
				promptText: ist_option? "Choose from issue types": "Choose the issue type",
				displayMember: "name", valueMember: "id",
				checkboxes: ist_option,
				width: 238, height: 25,
				autoDropDownHeight: true
			});
			ist_row_change(); // it's actually an initialization

			// Group option and choice(s)
			var group_option = config && config.public_settings && config.public_settings.group && config.public_settings.group.select == true || defaults.group_select,
			group_row_change = function() {
				// The Issue Type multi-checkbox dropdown
				$('#edit_dom_public_group', $editor).jqxDropDownList('clearSelection');
				$('#edit_dom_public_group', $editor).jqxDropDownList({
					promptText: group_option? "Choose from groups": "Choose the group",
					checkboxes: group_option
				});
				$('#edit_dom_public_group', $editor).jqxDropDownList('setContent', group_option? "Choose from groups": "Choose the group");

				if(group_option) {
					// Select existing Issue Types valid for this domain and the label for the selector
					if(config && config.public_settings && config.public_settings.group && config.public_settings.group.select)
						for(c=0; c< self.config.groups_source.length; c++) {
							if($.inArray(self.config.groups_source[c].id, config.public_settings.group.list) > -1)
								$('#edit_dom_public_group', $editor).jqxDropDownList('checkIndex', c);
						};
					$('#label_group_select', $editor).html("Groups:");
				} else {
					if(config && config.public_settings && config.public_settings.group && !config.public_settings.group.select)
						for(c=0; c< self.config.groups_source.length; c++) {
							if(self.config.groups_source[c].id == config.public_settings.group.auto)
								$('#edit_dom_public_group', $editor).jqxDropDownList('selectIndex', c);
						}
					$('#label_group_select', $editor).html("Auto group:");
				}
			};
			$('#edit_dom_group_select', $editor).jqxSwitchButton({
				theme: cargohub.jqxtheme,
				height: 25, width: 75,
				checked: group_option
			}).on('change', function(event) {
				group_option = event.args.check;
				group_row_change();
			});

			$('#edit_dom_public_group', $editor).jqxDropDownList({
				theme: cargohub.jqxtheme,
				source: self.config.groups_source,
				promptText: group_option? "Choose from groups": "Choose the group",
				displayMember: "name", valueMember: "id",
				checkboxes: group_option,
				width: 238, height: 25
			});
			group_row_change(); // it's actually an initialization

			// Determin current auto-sharing selector value
			var auto_sharing_index = defaults.auto_sharing_index;
			if(config && config.public_settings && config.public_settings.ownership)
				for(c=0; c< auto_sharing.length; c++)
					if(config.public_settings.ownership == auto_sharing[c].value) {
						auto_sharing_index = c;
						break;
					}

			// Public submit auto-sharing selector
			$('#edit_auto_sharing', $editor).jqxDropDownList({
				theme: cargohub.jqxtheme,
				source: auto_sharing, selectedIndex: auto_sharing_index,
				displayMember: "text", valueMember: "value",
				width: 238, height: 25,
				autoDropDownHeight: true
			});
		},

		/**
		 * Prepares the toolbar for the domains tab
		 */
		setup_Domains_Toolbar = function()
		{
			// Buttons to add/edit/remove an user from the department
			$('div#tab_domains .toolbar button')
			.jqxButton({ theme: cargohub.jqxtheme })
			.on('click', function() {
				switch($(this).data('op')) {
					// Open the domain editor with blank data, with the purpose of creating a new domain for the organization
					case 'dom-add':
						var tpl_data = {_itc: cargohub.input_theme_classes};

						cargohub.its.open_Modal_Window('domain-editor', 430, 550, "Add an organization domain",
						$("#template_domain_editor").html().present(tpl_data), setup_Domain_Editor_Content);
						break;

					// Open the domain editor with data for the selected domain
					case 'dom-edit':
						$.get("?action=get_domain_data&domain=" + selected_domain, function(x)
						{
							if(x.success) {
								var tpl_data = x.domain;
								tpl_data._itc = cargohub.input_theme_classes;
								tpl_data.domain = selected_domain;

								cargohub.its.open_Modal_Window('domain-editor', 430, 550, "View or edit domain",
									$("#template_domain_editor").html().present(tpl_data), setup_Domain_Editor_Content, tpl_data);
							} else {
								cargohub.alert(x.reason, "Message from server", "events.png");
							}
						});
						break;

					// Deletes a domain, which prevents users from using the domain anymore (and all links in e-mail or external sources will become invalid)
					case 'dom-del':
						if(confirm("Are you REALLY sure you want to remove this domain?\n\nYou will LOSE all settings related to it and users won't be able to access it anymore,\nall links pointing to it will become invalid !")) {
							$.post("?action=delete_domain", {
								domain: selected_domain
							}, function(x) {
								if(x.success)
									document.location.reload();
								else {
									cargohub.alert(x.reason, "Message from server", "events.png");
								}
							});
						}
						break;

					// Visits the domain in a new browser window
					case 'dom-visit':
						window.open('http://'+selected_domain+'/', '_blank');
						break;
				}
			});

		},
		/**
		 * Sets up the grids (one per tab) which display information about the attached groups
		 */
		setup_Domains_Grid = function()
		{
			$('div#tab_domains div#domains-grid').jqxGrid({
				theme: cargohub.jqxtheme,
				width: cargohub.fullwidth - 14, autoheight: true,
				source: new $.jqx.dataAdapter({
					url: '?action=get_domains',
					datatype: "json",
					datafields: [
						{name: 'domain', type: 'string'},
						{name: 'title', type: 'string'},
						{name: 'css_theme', type: 'string'},
						{name: 'logo', type: 'string'},
						{name: 'public', type: 'string'},
						{name: 'index', type: 'string'}
					],
					id: 'domain'
				}),
				columns: [
					{text: "Domain", dataField: 'domain', width: 160,
						cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
							return '<div style="text-overflow: ellipsis; overflow: hidden; padding-bottom: 2px; text-align: left; margin-right: 2px; margin-left: 4px; margin-top: 4px;"><a href="http://'+value+'/" target="_blank">'+value+'</a></div>';
						}
					},
					{text: "Site title", dataField: 'title'},
					{text: "CSS Theme", dataField: 'css_theme', width: 115, filtertype: "checkedlist",
						createfilterwidget: function (column, columnElement, widget) {
							widget.jqxDropDownList({
								autoDropDownHeight: true,
								animationType: "fade"
							});
						}
					},
					{text: "Header Picture", dataField: 'logo', width: 160,
						cellsalign: "center",
						cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
							return '<div style="text-overflow: ellipsis; overflow: hidden; padding-bottom: 2px; text-align: left; margin-right: 2px; margin-left: 4px; margin-top: 4px;"><a class="fancy2" href="/img/hdrs/'+value+'">'+value+'</a></div>';
						}
					},
					{text: "Index redirection", dataField: "index", width: 180},
					{text: "Public settings", dataField: 'public', width: 300}
				], columnsmenu: false, columnsresize: true,
				sortable: true, filterable: true, showfilterrow: true,
				altrows: true, selectionmode: "singlerow",
			})
			/**
			 * Event: When a row is selected, the domain name is displayed on the toolbar, beside buttons to edit/delete, and all of it is show with fade in
			 */
			.on('rowselect', function (event)
			{
				var row = event.args.row;
				$("div#selected-domain").html(row.domain);
				if(!selected_domain) {
					donrey.cancel_Delay('hide_bar_domain-row-ops');

					$('div#domain-edit-operations button').jqxButton({disabled: false});
					$('div#domain-edit-operations').fadeIn();
				}
				selected_domain = row.domain;
			})
			/**
			 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
			 */
			.on('filter', function(event)
			{
				if(selected_domain) {
					$(this).jqxGrid('clearselection');
					selected_domain = null;
					$("div#selected-domain").empty();
					$('div#domain-edit-operations button').jqxButton({disabled: true});

					donrey.single_Delay('hide_bar_domain-row-ops', function() {
						$('div#domain-edit-operations').fadeOut();
					}, 500);
				}
			})
			/**
			 * Event: On doubleclicking, do the same as simple clicking then using the Edit button
			 */
			.on('rowdoubleclick', function(){
				$('div#tab_domains .toolbar button[data-op="dom-edit"]').trigger("click");
			});
		},

		/**
		 * Sets up the domain editor window which is used to change domain settings (html and public)
		 */
		setup_Domain_Editor = function()
		{
			// init the modal window, with the OK button being setup in the callback
			cargohub.its.init_Modal_Window('domain-editor', null, {"cancel": "cancel"}, function($editor) {
				/**
				 * Event: Clicking the editor's OK button
				 */
				$('#modal_domain-editor_ok', $editor).on('click', function() {
					// submit the add/edit form using ajax
					$('.modal_content > form', $editor).ajaxSubmit({
						dataType: 'json',
						beforeSubmit: function(formData, jqForm, options) {
							// setup the field checking object
							var f = {};
							for(var c=0; c< formData.length; c++)
								f[formData[c].name] = formData[c].value;

							// TODO: Regex to check if a domain is really a domain ( there are several answers but I don't like any of them right now )
							// TODO: Checks for some other things

							if(!f['domain_update'] && (!f['dom[domain]'] || f['dom[domain]'].length < 5)) {
								cargohub.alert("Please type at least 5 characters in the domain name", "Warning", "error.png");
								return false;
							}

							if(!f['dom[html_settings][title]'] || f['dom[html_settings][title]'].length < 5) {
								cargohub.alert("The site title is too short, minimum 5 characters please", "Warning", "error.png");
								return false;
							}

							// add the value of the "footer" choice (a switchbutton) to the submitted data
							formData.push({
								name: "dom[html_settings][footer]",
								value: $('#edit_dom_html_footer', $editor).jqxSwitchButton("checked")
							});

							// the "public settings" related to ITS and GROUP selectors are script controlled (no actual form inputs), so grab the data here and assamble the content that will be sent to the server
							var public_settings = {};

							// String set
							public_settings.string_set = $('#edit_dom_pub_string_set', $editor).val();
							if(public_settings.string_set.length == 0)
								return cargohub.alert("Please choose a text group", "Warning", "error.png");

							// IST:
							public_settings.ist = {};
							if($('#edit_dom_ist_select', $editor).val()) {
								public_settings.ist.select = true;
								public_settings.ist.list = $('#edit_dom_public_ist', $editor).val().split(',');
								if(!public_settings.ist.list[0]) {
									cargohub.alert("You must choose at least one issue type", "Warning", "error.png");
									return false;
								}
							} else {
								public_settings.ist.select = false;
								public_settings.ist.auto = $('#edit_dom_public_ist', $editor).val();
								if(!public_settings.ist.auto) {
									cargohub.alert("You have to select the issue type for this domain", "Warning", "error.png");
									return false;
								}
							}
							// Groups:
							public_settings.group = {};
							if($('#edit_dom_group_select', $editor).val()) {
								public_settings.group.select = true;
								public_settings.group.list = $('#edit_dom_public_group', $editor).val().split(',');
								if(!public_settings.group.list[0]) {
									cargohub.alert("You must choose at least one group", "Warning", "error.png");
									return false;
								}
							} else {
								public_settings.group.select = false;
								public_settings.group.auto = $('#edit_dom_public_group', $editor).val();
								if(!public_settings.group.auto) {
									cargohub.alert("You have to select the default group for this domain", "Warning", "error.png");
									return false;
								}
							}
							// Ownership option
							public_settings.ownership = $('#edit_auto_sharing', $editor).val();

							formData.push({
								name: "dom[public_settings]",
								value: JSON.stringify(public_settings)
							});

							return true;
						},
						success: function(x) {
							if(x.success) {
								document.location.reload();
							} else {
								cargohub.alert(x.reason, "Message from server", "events.png");
							}
						}
					});
				});
			});
		};

		setup_Domains_Toolbar();
		setup_Domains_Grid();
		setup_Domain_Editor();
	},

	/**
	 * The Profile Defaults tab (Admin only), shows the saved organization-related profile defaults which are stored in the UPD table.
	 * Allows forcing a default to all users (removes homonymous profiles from all users of the organization, forcing their next profile read to use the organization default)
	 * -- Option: Select affected users from the organization using checkboxes ?
	 *
	 *
	 */
	setup_Tab_Profile_Defaults = function()
	{
		var selected_profile = null,
		// the data definition
		gridDataFields = [
  			{name: 'pkey'},					// Not visible directly in the grid, but used for copying
			{name: 'ptype', type: 'string'},// One of "Grid columns", "Grid settings", "Panels", "Recent Records", "Preferences" - Others might be added later
			{name: 'pin', type: 'string'},	// Record type ( former Issue type ), applies to lots of keys (grid columns, sentings and overview panels) . For others: (doesn't apply)
			{name: 'pik', type: 'string'},	// Record type key ( former Issue type ) - Used for the record type icon
			{name: 'pname'},
			{name: 'ctime', type: 'date'},
			{name: 'source_user_name'}
		], ist_list = function() {
			var list = ["(Doesn't apply)"], c = 0;
			for(; c< self.config.ist_source.length; c++) {
				list.push(self.config.ist_source[c].name);
			}
			return list;
		}(),
		// the column definition
		gridColumns = [
//		{text: "Profile Key", dataField: 'pkey', width: 220},	// not human friendly
			{text: "Type", dataField: 'ptype', width: 150, filtertype: 'checkedlist'},
			{text: "Record type", dataField: 'pin', width: 240,
				filtertype: "checkedlist", filteritems: ist_list,
				createfilterwidget: function (column, columnElement, widget) {
					widget.jqxDropDownList({
						renderer: function (index, label, value) {
							return "<div>"+ (index>1?"<img style='float: left; margin-right: 4px;' src='/img/icons16/ist/"+self.config.ist_source[index-2].key+".png' />" + value: value) + "</div>";
						},
						animationType: "fade"
					});
				},
				cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
					return "<div style='text-overflow: ellipsis; overflow: hidden; margin-top: 5px;' title='"+ value +"'>"+ (listGridAdapter.records[row].pik? "<img src='/img/icons16/ist/" + listGridAdapter.records[row].pik + ".png' style='margin: 0px 4px;' />": "<span style='display: inline-block; width: 22px;'></span> ") +"<span style='position: absolute;'>" + value + "</span></div>";
				}
			},
			{text: "Profile Record Name/Description", dataField: 'pname'},
			{text: "Created", dataField: 'ctime', width: 100, filtertype: "range", cellsformat: cargohub.date_format, align: "center", cellsalign: "center",
				createfilterwidget: function (column, columnElement, widget) {
					widget.jqxDateTimeInput({
						animationType: "fade",
						dropDownHorizontalAlignment: "right"
					});
				}
			},
			{text: "Sourced from user", dataField: 'source_user_name', width: 150},
		],
		// prepare the data
		listGridAdapter = new $.jqx.dataAdapter({
			url: '?action=get_profile_defaults',
			datatype: "json",
			datafields: gridDataFields
		});

		// ... and init the grid
		$("#records_grid").jqxGrid({
			theme: cargohub.jqxtheme,
			source: listGridAdapter,

			width: cargohub.fullwidth - 14, autoheight: true,
			columns: gridColumns, columnsmenu: false,
			selectionmode: "singlerow",	altrows: true,
			pageable: true,	pagesize: 20, pagesizeoptions: [10, 20, 50, 100],
			filterable: true, showfilterrow: true, sortable: true
		})
		/**
		 * Event: When a row is selected, the name of the organization is displayed on the toolbar, and is show with fade in (when selecting a row first time)
		 */
		.on('rowselect', function (event)
		{
		    var row = event.args.row;
			$("#tab_profile_defaults div.selected-item").html(row.pkey);
			if(!selected_profile) {
				$("#profile_edit_operations button").jqxButton({disabled: false});
				donrey.cancel_Delay('hide_bar_edit_ops');

				$('#profile_edit_operations').fadeIn();
			}

		    selected_profile = row;
		})
		/**
		 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
		 */
		.on('filter', function(event)
		{
			if(selected_profile) {
				$(this).jqxGrid('clearselection');
				selected_profile = null;
				$("#tab_profile_defaults div.selected-item").empty();
				$("#profile_edit_operations button").jqxButton({disabled: true});

				donrey.single_Delay('hide_bar_edit_ops', function() {
					$("#profile_edit_operations").fadeOut();
				}, 500);
			}
		})
		/**
		 * Event: On doubleclicking, do the same as simple clicking then using the View/Edit button
		 */
		.on('rowdoubleclick', function(){
			$('#tab_profile_defaults .toolbar button[data-op="view"]').trigger("click");
		});

		// Tab's toolbar commands
		$('#tab_profile_defaults .toolbar button')
		.jqxButton({ theme: cargohub.jqxtheme })
		.on('click', function() {
			switch($(this).data('op')) {
				// Views the profile data ( PHP var-export ? )
				case 'view':
					if(!popups.viewdata) {
						cargohub.its.init_Modal_Window('profile-view', {"ok": "Ok"});
						popups.viewdata = true;
					}

					cargohub.its.open_Modal_Window('profile-view', 640, 520, "View profile record data", $("#template-profile-record").html().present(selected_profile), function($window) {
						// Load the information into a read-only textarea that is the entire content of the window
						$window.find("textarea").load("?action=load_profile_default_data", {
							pkey: selected_profile.pkey
						});
					});
					break;

				// Opens a simple prompt allowing the description to be edited/customized (Note that descriptions are autogenerated by certain profile operations)
				case 'edit-descr':
					var pname = prompt("Edit profile default name/description", selected_profile.pname? selected_profile.pname: "");
					if(pname !== null) {
						// There was a return, save the new name
						$.post("?action=update_profile_default_pname", {
							pkey: selected_profile.pkey,
							pname: pname
						}, function(x) {
							if(x.success) {
								$("#records_grid").jqxGrid('updatebounddata');
								cargohub.alert(x.response, "Success !", "thumb_up.png");
								selected_profile.pname = pname;
							} else {
								cargohub.alert(x.reason, "Message from server", "events.png");
							}
						});
					}
					break;

				// Resets the selected profile by deleting it (at next read, it will auto-default to the organization default, if one exists)
				case 'reset-users':
					if(!popups.reset_usrprof) {
					    cargohub.its.init_Modal_Window('reset-usrprof', null, {"cancel": "cancel"});
					    popups.reset_usrprof = true;
					}
					cargohub.its.open_Modal_Window('reset-usrprof', 520, 396, "Reset user profile records", $("#template_reset-usrprof").html().present(selected_profile), function($window) // Setup the controls inside the window
					{
						// Setup the user selector (for picking chief)
						$('#selected_users', $window).jqxListBox({
							theme: cargohub.jqxtheme,
							source: self.config.count.users > 0 ? self.org_Userlist(): null,
							displayMember: "name", valueMember: "id",
							width: 494,	height: 250,
							checkboxes: true
						});

						// Setup the action for "OK" button
						$('#modal_reset-usrprof_ok', $window).off('click').on('click', function() {
							// submit the add/edit form using ajax
							if(!confirm("Are you sure you want to reset the selected Profile Key for these users ?\nTheir entries will revert to the organization default."))
								return false;

							$.post("?action=profile_reset_multiusers", {
								pkey: selected_profile.pkey,
								users: $('#selected_users', $window).val()
							}, function(x) {
								if(x.success) {
									cargohub.alert(x.response, "Success !", "thumb_up.png");
									$window.jqxWindow('close');
								} else {
									cargohub.alert(x.reason, "Message from server", "information.png");
								}
							});
						});
					});
					break;

				// Copies (and replaces) the selected profile into the user's organization default ( all other users that haven't customized their entry will inherit this profile setting )
				case 'copy-to':

					cargohub.alert("Copy to another organization will have to wait, I'm tired...");
					return;

					if(!confirm("Are you sure you want to copy this profile default to the selected organization ?\nThis will become the default for all new users in the organization, and also the default when users reset their configuration."))
						return;

/*					$.post("?action=set_default_profile", {
						user_id: self.config.id,
						org_id: self.config.org_id,
						pkey: selected_profile.pkey,
						variant: selected_profile.variant
					}, function(x) {
						if(x.success) {
							$("#records_grid").jqxGrid('updatebounddata');
							cargohub.alert(x.response, "Success !", "thumb_up.png");
						} else {
							cargohub.alert(x.reason, "Message from server", "events.png");
						}
					});
*/					break;
			}
		});
	},

	/**
	 * Setup the tab-navigation interface used in this module
	 */
	setup_Tabs = function()
	{
		var disable_onhashchange = true, loaded_tabs = { // Contains the list of tabs that have already been loaded and initialized
			tab_overview: true
		}, selected_tab_idx = 0, selected_tab = 'tab_overview';

		// discover if a subtab is specified in the URL ( to jump directly to it )
		c = 0;
                if($('#org-control-panel').length>0){ //IWI report cron settings for admin
		$('#org-control-panel > div').each(function() {
			if(document.location.hash.substr(1) == $(this).prop('id').substr(4)) {
				selected_tab = $(this).prop('id');
				selected_tab_idx = c;
			}
			c++;
		});

		$('#org-control-panel').jqxTabs({
			theme: cargohub.jqxtheme,
			width: cargohub.fullwidth,
			position: 'top',
			selectionTracker: false, selectedItem: selected_tab_idx,
			scrollPosition: 'both', scrollStep: 100, keyboardNavigation: false
		})
		/**
		 * Event: When selecting a tab for the first time, it is loaded from the server (except for the first one - tab_overview, which is initially loaded with the page)
		 */
		.on('selected', function(event, auto_trigger) {
			cargohub.application.reset_Session_Timer();
			disable_onhashchange = true;

			if(!auto_trigger) {
				selected_tab_idx = event.args.item;
				selected_tab = $('#org-control-panel').jqxTabs('getContentAt', selected_tab_idx).attr('id');
			}

			$('#org-control-panel').jqxTabs('ensureVisible', selected_tab_idx);
			document.location.hash = selected_tab.substr(4);

			if(!loaded_tabs[selected_tab]) {
				// Load the tab content (and optionally associated code - which might need data from the loaded content). When everything is ready, show the tab's HTML and initialize javascript
				$.get("?action=load_tab_html&tab=" + selected_tab, function(tab_contents, textStatus, jqXHR)
				{
					// Load extra data for this tab, if it exists, and store it in the local object
					donrey.read_JSON_Headers(jqXHR, self.config);

					// TODO: Step by step move code for tabs out of the main module and into sub-modules to organize and clear this very large file
					switch(selected_tab) {
						// Organization primary settings ( admin and manager only )
						case 'tab_setup':
							donrey.load_Modules("organization/_setup", function() {
								$('div#' + selected_tab).html(tab_contents);
								cargohub.organization_setup.config = self.config.tab_setup;
								cargohub.organization_setup.init();
							}, "?v="+cargohub.version);
							break;

						// Departments editing set up in another module due to it's higher complexity
						case 'tab_deps':
							$('div#' + selected_tab).html(tab_contents);
							setup_Tab_Deps();
							break;

						// Users editor is set up in a different module because it might get extended in the future
						case 'tab_users':
							$('div#' + selected_tab).html(tab_contents);
							setup_Tab_Users();
							break;

						// The security tab can be used by user managers (that can open users for edit) to locate which departments and users have access overrides for various items
						case 'tab_security':
							donrey.load_Modules("organization/_security", function() {
								$('div#' + selected_tab).html(tab_contents);
								cargohub.organization_security.config = self.config.tab_security;
								cargohub.organization_security.init();
							}, "?v="+cargohub.version);
							break;

						// Grid views Manager
						case 'tab_gridviews':
							donrey.load_Modules("organization/_gridviews", function() {
								$('div#' + selected_tab).html(tab_contents);
								cargohub.organization_gridviews.config = self.config.tab_gridviews;
								cargohub.organization_gridviews.init();
							}, "?v="+cargohub.version);
							break;

						// Partners tab shows the list of partners for the organization and has two modes: Admin mode (Add/Edit/Delete) and User mode (View)
						case 'tab_partners':
							$('div#' + selected_tab).html(tab_contents);
							setup_Tab_Partners();
							break;

						// Universal groups connection editor. Server-side filtered
						case 'tab_groups':
							donrey.load_Modules("organization/_groups", function() {
								$('div#' + selected_tab).html(tab_contents);
								cargohub.organization_groups.config = self.config.tab_groups;
								cargohub.organization_groups.init();
							}, "?v="+cargohub.version);
							break;

						// Templates editor (used for viewing, printing or exporting issues)
						case 'tab_templates':
							donrey.load_Modules("organization/_templates", function() {
								$('div#' + selected_tab).html(tab_contents);
								cargohub.organization_templates.config = self.config.tab_templates;
								cargohub.organization_templates.init();
							}, "?v="+cargohub.version);
							break;

						// Domains tab has only some simple configuration settings
						case 'tab_domains':
							$('div#' + selected_tab).html(tab_contents);
							setup_Tab_Domains();
							break;

						// ACLs are using an universal editor also present in the Deparments and Users modules
						case 'tab_acls':
							// This tab needs a component
							donrey.load_Components("acls_editor", function() {
								// only after both the tab contents and sub-modules are loaded, initialize the tab
								$('div#' + selected_tab).html(tab_contents);
								setup_Tab_ACLs();
							}, "?v="+cargohub.version);
							break;

						// Organization profile defaults
						case 'tab_profile_defaults':
							$('#' + selected_tab).html(tab_contents);
							setup_Tab_Profile_Defaults();
							break;
					}

				});
				// set the tab as initialized (so next time it's selected there's no need to reload it)
				loaded_tabs[selected_tab] = true;
			}

			setTimeout(function() {
				disable_onhashchange = false;
			}, 50);
		});
                } //IWI report cron settings for admin

		// Trigger the selected event in case the original tab isn't the "overview"
		if(selected_tab != 'tab_overview')
			$('#org-control-panel').trigger('selected', true);

		// hash-based navigation inside tabs themselves - Visually select tab when hash changes by typing in the URL or using back/forward (not by clicking on the tab itself)
		window.onhashchange = function(event) {
			// hash changes as well when the user is selecting the tab manually, in those cases there's no need to find and reselect the tab again !
			if(disable_onhashchange)
				return;

			c = 0;
			$('#org-control-panel div.jqx-tabs-content > div').each(function() {
				if(location.hash.substr(1) == $(this).prop('id').substr(4))
					selected_tab_idx = c;
				c++;
			});
			$('#org-control-panel').jqxTabs('select', selected_tab_idx);
		};
	},

	// Single-Instance data loading flags
	flag_deps_loaded = false,
	flag_ctx_loaded = false

	;

	this._public({
		// section initialization
		init: function()
		{
			// Setup the tab-navigation interface used in this module
			setup_Tabs();

			// Live setup all fancybox links (used for some image data entries):
//			$('.fancy2').fancybox({
//				live: true
//			});
		},

		/**
		 * Loads the user list for the current organizations on first use and returns it (or with a callback if loading is incomplete)
		 * -- Can be used in selecting the org. manager, or the department chief, or for adding another user in the department (maybe other roles as well)
		 */
		org_Userlist: function(onComplete_callback)
		{
			var src_def = {
				datafields: [
					{name: 'id', type: 'number' },
					{name: 'group'},
					{name: 'user'},
					{name: 'name'},
					{name: 'status'}
				],
				id: 'id'
			};

			if(org_Userlist_Loaded === true) {
				// When already loaded, return the list directly
				if(onComplete_callback)
					onComplete_callback(self.userlist_Source.records);
				return self.userlist_Source.records;
			} else {
				// Check if "self.config.dataSources.users" exists, if so use that array. If not, use the callback method
				if(self.config.dataSources && self.config.dataSources.users) {
					src_def.localdata = self.config.dataSources.users;
					src_def.datatype = "array";
					self.userlist_Source = new $.jqx.dataAdapter(src_def);
					org_Userlist_Loaded = true;
				} else {
					// When not, return the dataAdapter instead (which will do the ajax request on demand)
					src_def.url = "/manager/organization?action=get_users&quickmode=1";
					src_def.datatype = "json";
					self.userlist_Source = new $.jqx.dataAdapter(src_def, {
						loadComplete: function()
						{
							org_Userlist_Loaded = true;
							if(onComplete_callback)
								onComplete_callback(self.userlist_Source.records);
						}
					});
					org_Userlist_Loaded = "loading";
				}
				return self.userlist_Source;
			}
		},

		/**
		 * Loads the list of contacts representing the organization itself. Used in Setup, Templates and possibly other locations
		 * @param onComplete_callback If used, this method is run when the loading has completed
		 */
		source_SelfCtx: function(onComplete_callback, autoBind)
		{
			var src_def = {
				url: "/manager/organization?action=get_contacts&type=org_self&quickmode=1",
				datatype: "json",
				datafields: [
					{name: 'id'},
					{name: 'email'},
					{name: 'name'}
				],
				id: 'id'
			};

			if(!self.dataSources)
				self.dataSources = {};

			if(flag_ctx_loaded === true) {
				// When already loaded, return the dataAdapter directly
				if(onComplete_callback && self.dataSources.selfCtx)
					onComplete_callback(self.dataSources.selfCtx.records);
				return self.dataSources.selfCtx.records;
			} else {
				// When not loaded, initialize the dataAdapter and return data when ready
				self.dataSources.selfCtx = new $.jqx.dataAdapter(src_def, {
					loadComplete: function()
					{
						flag_ctx_loaded = true;
						if(onComplete_callback && self.dataSources.selfCtx)
							onComplete_callback(self.dataSources.selfCtx.records);
					},
					autoBind: autoBind
				});
				flag_ctx_loaded = "loading";
				return self.dataSources.selfCtx;
			}
		},

		/**
		 * Loads the department list associated with the currently selected organization (self.config.organization.id) on first use and returns it (or with a callback if loading is incomplete)
		 * -- Used by the department access selector and all user search function ( to select from one of the active departments first )
		 * @param onComplete_callback If used, this method is run when the loading has completed
		 */
		source_Deps: function(onComplete_callback, autoBind)
		{
			var src_def = {
				url: '/manager/department?action=get_list',
				data: {
					org_id: self.config.id
				},
				datatype: "json",
				datafields: [
					{name: 'id'},
					{name: 'name'},
					{name: 'count_members', type: 'number'}
				],
				id: "id"
			};

			if(!self.dataSources)
				self.dataSources = {};

			if(flag_deps_loaded === true) {
				// When already loaded, return the dataAdapter directly
				if(onComplete_callback && self.dataSources.depList)
					onComplete_callback(self.dataSources.depList.records);
				return self.dataSources.depList.records;
			} else {
				// When not loaded, initialize the dataAdapter and return data when ready
				self.dataSources.depList = new $.jqx.dataAdapter(src_def, {
					loadComplete: function()
					{
						flag_deps_loaded = true;
						if(onComplete_callback && self.dataSources.depList)
							onComplete_callback(self.dataSources.depList.records);
					},
					autoBind: autoBind
				});
				flag_deps_loaded = "loading";
				return self.dataSources.depList;
			}
		}
	});
});


$(document).on('click', '#allow_btn', function () {
    console.log('allow_btn');
    Swal.fire({
        title: "Do you want to save the changes?",
        showDenyButton: true,
//  showCancelButton: true,
        confirmButtonText: "Allow",
        denyButtonText: `Cancel`
    }).then((result) => {
        /* Read more about isConfirmed, isDenied below */
        if (result.isConfirmed) {
            var userId = $(this).attr('data-user-id');

            $.post("/manager/organization?action=getDepertmentsByUser", {
                user_id: userId,
            }, function (x) {
                Swal.fire({
                    title: 'Select Departments',
                    html: x.depHtml,
                    confirmButtonText: 'save',
                    preConfirm: () => {

                        var selected_departments = []
                        var checkboxes = document.querySelectorAll('.selectDep input[type=checkbox]:checked')

                        for (var i = 0; i < checkboxes.length; i++) {
                            selected_departments.push(checkboxes[i].value)
                        }
//                        console.log(selected_departments);

                        $.post("/manager/organization?action=allowPublicationAllDep", {
                            user_id: userId,
                            allow: 'yes',
                            selected_departments: selected_departments
                        }, function (x) {
                            if (x.success)
                            {
                                document.location.reload();
                            } else {
                                cargohub.alert(x.reason, "Message from server", "events.png");
                            }
                        }).fail(cargohub.its.http_fail);
                    }

                }).then((result) => {
//                    console.log(result.isConfirmed);

                })
            }).fail(cargohub.its.http_fail);
        } else if (result.isDenied) {
//    Swal.fire("Changes are not saved", "", "info");
        }
    });
});


$(document).on('click','#disallow_btn',function(){
    console.log('disallow_btn');
    Swal.fire({
        title: "Do you want to save the changes?",
        showDenyButton: true,
//  showCancelButton: true,
        confirmButtonText: "Don't Allow",
        denyButtonText: "Cancel"
    }).then((result) => {
        /* Read more about isConfirmed, isDenied below */
        if (result.isConfirmed) {
            var userId = $(this).attr('data-user-id');
            $.post("/manager/organization?action=allowPublicationAllDep", {
                user_id: userId,
                allow: 'no'
            }, function (x) {
                if (x.success)
                {
                    document.location.reload();
                } else {
                    cargohub.alert(x.reason, "Message from server", "events.png");
                }
            }).fail(cargohub.its.http_fail);
        } else if (result.isDenied) {
//    Swal.fire("Changes are not saved", "", "info");
        }
    });
});




$(document).on('click', '#edit_btn', function () {
    console.log('edit_btn');

    var userId = $(this).attr('data-user-id');

    var currOrg = $('#currOrg').val();
    
    $.post("/manager/organization?action=getActiveDepertmentsByUser", {
        user_id: userId,
        currOrg: currOrg,
    }, function (x) {
        Swal.fire({
            title: 'Select Departments',
            html: x.depHtml,
            confirmButtonText: 'save',
            preConfirm: () => {

                var selected_departments = []
                var checkboxes = document.querySelectorAll('.selectDep input[type=checkbox]:checked')

                for (var i = 0; i < checkboxes.length; i++) {
                    selected_departments.push(checkboxes[i].value)
                }
//                        console.log(selected_departments.length);
//                        return false ;
                if (selected_departments.length > 0) {
                    $.post("/manager/organization?action=allowPublicationAllDep", {
                        user_id: userId,
                        allow: 'yes',
                        selected_departments: selected_departments
                    }, function (x) {
                        if (x.success)
                        {
                            document.location.reload();
                        } else {
                            cargohub.alert(x.reason, "Message from server", "events.png");
                        }
                    }).fail(cargohub.its.http_fail);
                } else {
                    console.log('ff');
                    Swal.fire({
                        title: "Are you sure you want to disallow to pubcation access?",
                        showDenyButton: true,
                        confirmButtonText: "Yes",
                        denyButtonText: `Cancel`
                    }).then((result) => {
                        /* Read more about isConfirmed, isDenied below */
                        if (result.isConfirmed) {
                $.post("/manager/organization?action=allowPublicationAllDep", {
                        user_id: userId,
                        allow: 'no',
                    }, function (x) {
                        if (x.success)
                        {
                            document.location.reload();
                        } else {
                            cargohub.alert(x.reason, "Message from server", "events.png");
                        }
                    }).fail(cargohub.its.http_fail);
                        } else if (result.isDenied) {
                        }
                    });
                }


            }

        }).then((result) => {
//                    console.log(result.isConfirmed);

        })
    }).fail(cargohub.its.http_fail);

});







$(document).on('click', '.loginAsBtn', function () {
    var userId = $(this).attr('data-user-id');
    var userName = $(this).attr('data-user-name');
    Swal.fire({
        title: "Logout as yourself and auto-login as " + userName + "?",
        showDenyButton: true,
//  showCancelButton: true,
        confirmButtonText: "Yes",
        denyButtonText: "Cancel"
    }).then((result) => {
        if (result.isConfirmed) {
            $.post("/manager/user?action=login_as", {
                'id': userId
            }, function (x) {
                document.location = x.redirect; // user's page after login
            }).fail(cargohub.its.http_fail);
        }
    });
});