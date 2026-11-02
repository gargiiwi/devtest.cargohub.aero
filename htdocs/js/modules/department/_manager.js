/**
 * Module "Department Manager" (Organization department editor)
 * Depends on the widgets:
 * - cargohub.components.hashnav (Tab based navigation)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2.9
 */
cargohub.department_manager = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,

	/**
	 * Loads the user list for the current organizations on first use and returns it (or with a callback if loading is incomplete)
	 * -- Can be used in selecting the org. manager, or the department chief, or for adding another user in the department (maybe other roles as well)
	 */
	org_Userlist_Loaded = false,
	org_Userlist = function(onComplete_callback)
	{
		if(org_Userlist_Loaded === true) {
			// When already loaded, return the list directly
			if(onComplete_callback)
				onComplete_callback(self.userlist_Source.records);
			return self.userlist_Source.records;
		} else {
			// When not, return the dataAdapter instead (which will do the ajax request on demand)
			self.userlist_Source = new $.jqx.dataAdapter({
				url: "/manager/organization?action=get_users&quick_mode=1",
				datatype: "json",
				datafields: [
					{ name: 'id', type: 'number' },
					{ name: 'user' },
					{ name: 'name' }
				],
				id: 'id'
			}, {
				loadComplete: function()
				{
					org_Userlist_Loaded = true;
					if(onComplete_callback)
						onComplete_callback(self.userlist_Source.records);
				}
			});
			org_Userlist_Loaded = "loading";
			return self.userlist_Source;
		}
	},

	/**
	 * The ACLs tab, used to edit department level overrides for privileges and other access lists
	 */
	setup_Tab_ACLs = function()
	{
		if(self.config.acls_overrides) {
			$('#tab_acls #overrides_on').show();
			$("#tab_acls .toolbar button[data-op='acls-clear']").show();
		} else
			$('#tab_acls #overrides_off').show();

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
					$.post("?action=update_acls&dep_id="+self.config.id, {
						acls: JSON.stringify(cargohub.components.acls_editor.read())
					}, function(x) {
						if(x.success)
							document.location.reload();
						else {
							cargohub.alert(x.reason, "Message from server", "events.png");
						}
					});
					break;

				// Remove any overrides done at this department level ( so all member users revert to the organization-level if they don't have a personal level )
				case 'acls-clear':
					cargohub.components.modal.delconfirm(function() {
						$.post("?action=update_acls&dep_id="+self.config.id, {
							clear: true
						}, function(x) {
							if(x.success)
								document.location.reload();
							else {
								cargohub.alert(x.reason, "Message from server", "events.png");
							}
						});
					}, "Are you sure you want to remove overrides for this department ?");
					break;
			}
		});
	},

	/**
	 * The department members tab - This is a list of users which have been connected to this department
	 */
	setup_Tab_Members = function()
	{
		var selected_user = null,
			tpl_data = {
				_itc: cargohub.input_theme_classes,
			},
		/**
		 * Shows the modal window used to add an user to the department
		 */
		open_Add_Member_Window = function()
		{
			cargohub.components.modal.create('member-add', {
				btn_roles: {cancel: "cancel"}
			});

			cargohub.components.modal.open('member-add', {
				width: 600,	height: 256,
				title: "Add a new Member to the Department",
				content: $("#template_member-add").html().present(tpl_data),
				callback: function($window) // Setup the controls inside the window
				{
					// User selector
					$('#choose_user', $window).jqxComboBox({
						theme: cargohub.jqxtheme,
						source: org_Userlist(), // TODO: Currently all organization's users are shown. Find a way to only show those that are not already members of this department
						searchMode: "containsignorecase",
						autoComplete: true, minLength: 0,
						placeHolder: "(type to search or click arrow to show all)",
						displayMember: "name", valueMember: "id",
						width: 400, height: 23, dropDownWidth: 400, dropDownHeight: 172,
						animationType: "fade", enableBrowserBoundsDetection: true
					});

					$('li.dep_info_cb > div', $window).each(function(i, el) {
						var data = $(el).data();

						$(el).jqxCheckBox({
							theme: cargohub.jqxtheme,
							disabled: data.disabled,
							checked: data.checked == 1
						})
						/**
						 * Event: A checkbox is clicked; Switch it's matching text YES/NO
						 */
						.on('change', function (event) {
							if (event.args.checked) {
								$(el).find('span:eq(1)').html('YES');
							}
							else {
								$(el).find('span:eq(1)').html('NO');
							}
						});

						if(data.hint)
							$(el).jqxTooltip({
								theme: cargohub.jqxtheme,
								position: 'mouse',
								content: data.hint
							});

						$(el).find('span:eq(1)').html(data.checked == 1? 'YES': 'NO');
					});

					// Setup the action for "OK" button
					$('#modal_member-add_ok', $window).off('click').on('click', function() {
						// submit the add/edit form using ajax
						$('.modal_content > form', $window).ajaxSubmit({
							dataType: 'json',
							beforeSubmit: function(formData, jqForm, options) {
								// setup the field checking object
								var f = {};
								for(var c=0; c< formData.length; c++)
									f[formData[c].name] = formData[c].value;

								if(!f['member[user_id]']) {
									cargohub.alert("Please select an user from the list.");
									return false;
								}

								// Add the department's ID to the posted data
								formData.push({
									name: "dep_id",
									value: self.config.id
								});

								return true;
							},
							success: function(x) {
								if(x.success) {
									// reload the page so the new user is shown in the department
									document.location.reload();
								} else {
									cargohub.alert(x.reason, "Message from server", "information.png");
								}
							}
						});
					});
				}
			});
		},

		/**
		 * Prepares the toolbar for the members tab
		 */
		setup_Members_Toolbar = function()
		{
			// Buttons to add/edit/remove an user from the department
			$('div#tab_members .toolbar button')
			.jqxButton({ theme: cargohub.jqxtheme })
			.on('click', function() {
				switch($(this).data('op')) {
					// Open the domain editor with blank data, with the purpose of creating a new domain for the organization
					case 'member-add':
						open_Add_Member_Window();
						break;

					// Open the domain editor with data for the selected domain
					case 'member-edit':
						document.location = "/manager/user?id=" + selected_user.id;
						break;

					// Deletes a domain, which prevents users from using the domain anymore (and all links in e-mail or external sources will become invalid)
					case 'member-del':
						cargohub.components.modal.delconfirm(function() {
							$.post("?action=member_disconnect", {
								user_id: selected_user.id,
								dep_id: self.config.id
							}, function(x) {
								if(x.success)
									document.location.reload();
								else {
									cargohub.alert(x.reason, "Message from server", "events.png");
								}
							});
						}, "Are you sure you want to disconect this member?", "Disconnect confirmation");
						// TODO: Support extra text in the del confirm box
						//						"Any access right customization for him will be deleted,\nand the user won't be able to select this department.\nIn case this is the active department for the user, he will revert back to organization-wide access."


						break;
				}
			});
		},

		/**
		 * What happens an user is selected in the left-side list
		 */
		event_Select_Member = function(e)
		{
			var user = self.config.members[e.args.index];
			$("div#tab_members #user_view").html($("#template_loading").html().present(user));

			// unselect (disable) edit/delete buttons while waiting to load, and reenable it when dep detail is loaded
			if(cargohub.application.user_Test_Privileges('org_user_manager')) {
				selected_user = null;
				$("div#tab_members div#selected_user").empty();
				$("div#tab_members #member_edit_operations button").jqxButton({disabled: true});
			}

			$.get("/manager/user?action=get_details&id="+ user.id +"&acls=dep_or&dep_id=" + self.config.id, function(user_details, textStatus, jqXHR) {

				if(user_details instanceof Object && user_details.success == false) {
					$("div#tab_members #user_view").html("<h1 style='text-align: center;'>"+user_details.reason+"</h1>");
					cargohub.alert(user_details.reason, "Message from server", "events.png");
					return;
				}
				// Load extra data which was sent for the JS part, if it exists
				donrey.read_JSON_Headers(jqXHR, user);

				if(user.id == 0)
					cargohub.alert("User not found, possibly database error or it has already been removed from the department");
				else {
					// content loaded, show it
					$("div#tab_members #user_view").html(user_details);
					if(cargohub.application.user_Test_Privileges('org_user_manager')) {
						// Display the buttons to edit/delete
						$("div#tab_members div#selected_user").html(user.name);
						if(!selected_user) {
							$("div#tab_members #member_edit_operations button").jqxButton({disabled: false});
							$('div#tab_members #member_edit_operations').fadeIn();
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
			$("div#tab_members div#members_splitter").jqxSplitter({ theme: cargohub.jqxtheme, width: cargohub.fullwidth - 14, height: 500, panels: [{
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
		setup_Members_Quicklist = function()
		{
			var members_quicklist_config = {
				theme: cargohub.jqxtheme,
				width: "100%", height: "100%",
				source: self.config.members, valueMember: "id",
				renderer: function (index, label, value) {
					
//                                    IWI Show user type on department’s members section  start
//					var user = self.config.members[index];
//					// TODO: Show the status of the users as an icon
//					return user.name;


var usr = self.config.members[index],
							userdisplay = "<img src='/img/icons16/user_statuses/" + usr.status + ".png' alt='"+ usr.status +"' style='float: left; margin-right: 4px;'> "+ usr.name.substring(0, 27)+ (usr.name.length > 27?' â€¦':'');
						return userdisplay;
//                                                IWI Show user type on department’s members section end
				}
			};

			$('div#tab_members div#members_quicklist')
				.jqxListBox(members_quicklist_config)
				.on('select', event_Select_Member);
		};

		if(cargohub.application.user_Test_Privileges('org_user_manager'))
			setup_Members_Toolbar();

		if(self.config.members.length) {
			setup_Splitter();
			setup_Members_Quicklist();
		}
	},

	/**
	 * Tab initialization callback (used by the hashnav component)
	 * @param tab_id
	 */
	event_Tab_Init = function(tab_id)
	{
		switch(tab_id) {
		// Settings tab, contains the department name, the "chief" selection and option to list or not list the department to external parties
		case 'settings':
			donrey.load_Modules("department/_settings", function() {
				cargohub.department_settings.config = self.config;
				cargohub.department_settings.init();
			}, "?v="+cargohub.version);
			break;
		// Department level override of Access Control Lists
		case 'acls':
			// This tab needs a component
			donrey.load_Components("acls_editor", function() {
				// only after both the tab contents and sub-modules are loaded, initialize the tab
				setup_Tab_ACLs();
			}, "?v="+cargohub.version);
			break;
		// Lists the department members, allow connecting another user of the organization to this department, or disconnecting existing ones.
		case 'members':
			setup_Tab_Members();
			break;
		}
	}
	;

	this._public({
		// module initialization
		init: function()
		{
			// setup the tab-navigation interface used in this module
			// Prepare component event callbacks
			cargohub.components.hashnav.events = {
				tab_init: event_Tab_Init
			};

			// Initialize the Hash-based Tabs navigation Component
			cargohub.components.hashnav.setup({
				use_tabs: true,
				tab0: 'settings',
				tab_load_params: {
					settings: {
						org_id: self.config.org_id
					},
					acls: {
						dep_id: self.config.id,
						org_id: self.config.org_id
					},
					members: {
						count_members: self.config.count_members,
					}
				}
			}, self);
		}
	});
});