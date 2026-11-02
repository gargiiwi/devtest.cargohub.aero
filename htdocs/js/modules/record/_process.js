/**
 * Interface control for opening and processing a primary data record
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2020.07.27
 *
 * @todo Review the order of the methods
 * @todo Review the construction pattern for modals (it should be similar for all)
 *
 * Private Methods:
 * - modal_SetMaster 
 * - setup_Org_Perspective
 * - call_Set_StatPrio
 * - modal_Access_Deps
 * - modal_Print 					(Kinda deprecated)
 * - setup_Transfer				Setup for Transfer modal - Opened with Public::open_Modal_Transfer
 * - modal_Change_Group
 * - modal_Assign_User
 * - setup_Menu_and_Operations
 * - init_Overview_Tab
 *
 * Public Methods:
 * - init
 * - contact_Open_Details_Modal
 * - toolbar_Report
 * - select_Tab_With_Params
 * - init_DataTable
 * - clear_DataTable
 * - refresh_DataTable
 * - source_Sharing
 * - source_Deps
 * - build_Active_DepsSource
 * - open_Modal_Transfer
 * - open_Modal_Contacts_List
 * - load_rich_editor
 * - load_file_uploader
 */
cargohub.record_process = donrey.BASE._extend(function() {
	// private
	var self = this,
	c = null,

	/**
	 * Prepares the modal "Set master organization"
	 */
	modal_SetMaster = function($window, config)
	{
		cargohub.components.modal.create('set-master', {
			btn_roles: {cancel: "cancel"}
		});

		cargohub.components.modal.open('set-master', {
			width: 630,	height: 400,
			title: "Set new Master Organization",
			content: $('#template-master-org').html().present({
				_itc: cargohub.input_theme_classes
			}),
			callback: function($window) // Setup the controls inside the window
			{
				var orgs_Source = new $.jqx.dataAdapter({
					url: "/admin/global/?action=get_related_orgs",
					data: {
						mode: "ist",
						key: self.config.type.key,
						sort: '`type_id` ASC, `name` ASC'
					},
					datatype: "json",
					datafields: [
						{name: 'id', type: 'number'},
						{name: 'type_id'},
						{name: 'name'}
					],
					id: "id"
				}), $org_search_combo = $("#admin-org-search", $window);

				$org_search_combo.jqxComboBox({
					theme: cargohub.jqxtheme,
					searchMode: "containsignorecase",
					autoComplete: true,	minLength: 0,
					source: orgs_Source,
					displayMember: "name", valueMember: "id",
					renderer: function (index, label, value, html) {
						var rec = orgs_Source.records[index];
						return '<span style="float: right;">['+rec.type_id+']</span>'+ rec.name;
					},
					placeHolder: "Type to search or click the arrow to show all",
					width: 383, height: 22,
					dropDownWidth: 383, dropDownHeight: 220,
					animationType: "none"
				})
				// Event: An item is selected from the group
				.on('select', function(event) { // value on select event - Which puts the group id in the hidden input
					if(event.args == undefined)
						return false;

					if (event.args.item != null && event.args.item.originalItem.id > 0) {
						// Load basic information about an organization into the div below:
						$.get("/admin/orgs?action=quick_org_info&id=" + event.args.item.originalItem.id, function(contents, textStatus, jqXHR)
						{
							$("#quick-org-information", $window).html(contents);
						});
					} else {
						$org_search_combo.jqxComboBox('clearSelection');
						$("#quick-org-information", $window).html('<div class="quick-org-info center"><span class="gray">Please select an organization</span></div>');
						return false;
					}
				});

				// Setup the action for "OK" button
				$('#modal_set-master_ok', $window).off('click').on('click', function()
				{
					$.post("?action=set_master_org", {
						org_id: $org_search_combo.val(),
						reload_mode: true
					}, function(x) {
						if(x.success) {
							// Master org updated - Reload with perspective to the new organization

							document.location = "/record/process:"+self.config.track+"?perspective="+$org_search_combo.val()+"#sharing"; // Also shows the sharing tab to confirm the change to master organization
//							$window.jqxWindow('close');
//							self.config.master_org_id = $org_search_combo.val();
//							cargohub.alert(x.response, "Success !", "thumb_up.png");
						} else
							cargohub.its.http_fail(null, "Record.Process::set_org_master");
					})
					.fail(cargohub.its.http_fail);
				});
			}
		});
	},

	/**
	 * Sets up the organization perspective toolbar which is used by admins and supervisors to select the point-of-view on how the record is being displayed (different organizations have different views)
	 */
	setup_Org_Perspective = function()
	{
		if(!$('#org_perspective').length)
			return; // Nothing to do there, the bar doesn't exist

		var sysChange = true;

		// When admin, read the list of organizations that have access to the current issue type
		$("#org_perspective #org_perspective-select").jqxDropDownList({
			source: self.config.shared_orgs || null,
			theme: cargohub.jqxtheme,
			displayMember: "name", valueMember: "id",
			placeHolder: self.config.shared_orgs.length>0? "Select organization:": "No organizations connected !",
			width: 250, height: 24, dropDownWidth: 450, autoDropDownHeight: true, animationType: "fade",
			// Display a bit more information about the shared organizations
			renderer: function(index, label, value) {
				var sho = self.config.shared_orgs[index];
				label="<span class='right'>("+sho.type_id+")</span>"+label;
				if(sho.master)
					label+=" <b>[master]</b>";
				if(sho.disabled)
					label+=" [disabled]";
				return label;
			}
		})
		/**
		 * Event: The organization selector is changed - Reload the page with organization selected which would view the grid as from the selected organization.
		 */
		.on("select", function(event) {
			if(sysChange)
				return;
			document.location = "/record/process:"+self.config.track+"?perspective="+event.args.item.value+location.hash;
		});

		// Initialize current organization
		if(self.config.organization.id && self.config.shared_orgs)
			for(c=0; c< self.config.shared_orgs.length; c++)
				if(self.config.shared_orgs[c].id == self.config.organization.id)
					$("#org_perspective #org_perspective-select").jqxDropDownList('selectIndex', c);
		sysChange = false;

		// The admin tools buttons
		$("#org_perspective button").jqxButton({
			theme: cargohub.jqxtheme
		})
		/**
		 * Event: One of the admin buttons is clicked (special functions)
		 */
		.click(function(event) {
			switch($(this).data('cmd')) {
			case 'set_master':
				modal_SetMaster();
				break;
			}
		});
	},

	/**
	 * Runs an AJAX call designed to change Status and Priority of a record
	 * -- On successful call it changes the Status or Priority button and text from the "Record Overview"
	 * @param fn Which one will be changed ("status", "priority")
	 * @param key Status or Priority key
	 */
	call_Set_StatPrio = function(fn, key)
	{
		// Update status or priority
		$.post("?action=Set_Stat_Prio", {
			track: self.config.track,
			org_id: self.config.organization.id,
			fn: fn,
			key: key
		}, function(x) {
			switch(x.fn) {
			case 'status':
                                $("#rec-operations .btn-stat-record-details").css('background-image', "url(/img/icons/statuses/"+x.key+".png)"); //IWI status popup menu design issue fix
				$("#rec-operations #txt-stat").text(x.value);
				break;
			case 'priority':
				$("#rec-operations .btn-prio-record-details").css('background-image', "url(/img/icons/priorities/"+x.key+".png)");//IWI status popup menu design issue fix
                                $("#rec-operations #txt-prio").text(x.value);
				break;
			}

			if(x.autoclose) {
				if(confirm("The status: "+ x.value+ " asks for the record to be closed. Confirm ?")) {
					$.post("?action=Mark_Process", {
						command: "close"
					}, function(x) {
						// Record is closed
						document.location.reload();
					})
					.fail(cargohub.its.http_fail);
				}
			} else
				cargohub.alert(x.response, "Success !", "thumb_up.png");

			// TODO: Check if the record is actually closed before enabling this
//			if(x.autoopen) {
//				if(confirm("The status: "+ x.value+ " asks for the record to be reactivated. Confirm ?")) {
//					$.post("?action=Mark_Process", {
//						command: "open"
//					}, function(x) {
//						// Record is reopened
//						document.location.reload();
//					})
//					.fail(cargohub.its.http_fail);
//				}
//			}

			if(cargohub.record_sharing)
				cargohub.record_sharing.reload();
		})
		.fail(cargohub.its.http_fail);
	},

	/**
	 * Setup and show the modal used to control which departments have access on this record.
	 * Only the enabled departments are visible in other controls which require choosing a department (select user for assigning, tasks)
	 */
	modal_Access_Deps = function()
	{
		cargohub.components.modal.create('access-deps', {
			btn_roles: {cancel: "cancel"}
		});

		cargohub.components.modal.open('access-deps', {
			width: 555,	height: 320,
			title: "Department access control",
			content: $('#modal-access_deps').html().present({
				_itc: cargohub.input_theme_classes
			}),
			callback: function($window) // Setup the controls inside the window
			{
				// The "All organization" switch - Enables or disables individual department selection
				$("#dep-access-all", $window).jqxSwitchButton({
					theme: cargohub.jqxtheme,
					height: 25, width: 75,
					disabled: true
				})
				.on('change', function(event) {
					// Check ALL if "event.args.check" is true, and revert to the currently selected deps ( or none selected? ) if false
					if(event.args.check) {
						$("#dep-access-list", $window).jqxListBox('checkAll');
						$("#dep-access-list", $window).jqxListBox({ disabled: true });
					} else {
						$("#dep-access-list", $window).jqxListBox({ disabled: false });
						// Uncheck all first if not all deps have access, then recheck the active ones
						if(self.config.dep_ids.length < self.dataSources.depList.records.length)
							$("#dep-access-list", $window).jqxListBox('uncheckAll');

						if(self.config.dep_ids) {
							for(c=0; c<self.dataSources.depList.records.length; c++)
								if($.inArray(self.dataSources.depList.records[c].id.toString(), self.config.dep_ids) > -1)
									$("#dep-access-list", $window).jqxListBox('checkIndex', c);
						}
					}
				});

				cargohub.components.spinner.On(null, null, null, null, 500);
				// List of departments for the user's organization or the issue owner organization (sent from PHP)
				$("#dep-access-list", $window).jqxListBox({
					theme: cargohub.jqxtheme,
					width: 400, height: 172,
					checkboxes: true,
					source: self.source_Deps(false, function(records) {
						// Event, the list is ready (loaded or already available)
						var count_deps_on = 0;
						if(self.config.dep_ids) {
							for(c=0; c<records.length; c++)
								if($.inArray(records[c].id.toString(), self.config.dep_ids) > -1)
									count_deps_on++;
						}

						$("#dep-access-all", $window).jqxSwitchButton({ disabled: false });
						$("#dep-access-all", $window).val(count_deps_on == records.length);
						cargohub.components.spinner.Off(500);
					}, function() { // Error function
						$("#dep-access-list", $window).jqxListBox({ disabled: true });
					}),
					displayMember: 'name', valueMember: 'id',
					renderer: function(index, label, value) {
						return '<span class="gray right">('+self.dataSources.depList.records[index].count_members+' members)</span>'+ label;
					}
				});

				// Setup the action for "OK" button
				$('#modal_access-deps_ok', $window).off('click').on('click', function()
				{
					var dep_ids = $("#dep-access-list", $window).val();

					// Update department access list
					$.post("?action=update_deps_access", {
						track: self.config.track,
						org_id: self.config.organization.id,
						dep_ids: dep_ids
					}, function(x) {
						if(x.success) {
							// Update the local copy:
							self.config.dep_ids = dep_ids.split(',');
							self.build_Active_DepsSource(self.dataSources.depList.records); // Rebuild this on deps enabled update
							$window.jqxWindow('close');
							cargohub.alert(x.response, "Success !", "thumb_up.png");
						} else
							cargohub.its.http_fail(null, "Record.Process::update_departments");
					})
					.fail(cargohub.its.http_fail);
				});
			}
		}, self);
	},

	/**
	 * Opens the print options modal to select
	 * - Template - Organization's custom or the always available "Default" print template
	 * - Included parts - They match the Data Panels
	 * - Contacts - If the "contacts" part is selected, the template may includes the list of linked contacts - By default, only the submitter
	 *
	 * On pressing ok, a new tab is opened containing a full-page TinyMCE editor that includes "Print" command
	 */
	modal_Print = function()
	{
		// This is using data loaded with the module itself, no need to rebuild on reopen
		if(cargohub.components.modal.windows['print'])
			return cargohub.components.modal.reopen('print');

		/**
		 * Source for the Print Templates
		 */
		var print_Templates_Source = new $.jqx.dataAdapter({
			url: "/global/operations?action=Get_Templates",
			datatype: "json",
			data: {
				role: "print",			// Loading print templates for this control
				include_default: true,	// Include the default print template (mostly a copy of _view.html)
				rt_id: (self.config.type.base_id && self.config.type.base_id != self.config.type.id)? self.config.type.base_id: self.config.type.real_id
			},
			datafields: [
				{name: 'title', type: 'string'},		// Already formatted from variables
				{name: 'description', type: 'string'}
			],
			id: "id",
		}), data_parts = self.config.parts.slice(0); // Clone

		// Append the "Contacts" as an option for data parts
		data_parts.unshift({
			id: 'contacts',
			title: 'Linked Contacts',
			send: false // Not on by default for printing !
		});

		cargohub.components.modal.create('print', {
			buttons: {
				ok: "Print preview",
				cancel: "Cancel"
			},
			btn_roles: {cancel: "cancel"}
		});

		cargohub.components.modal.open('print', {
			width: 580, height: 180,
			title: "Print setup for: "+ self.config.type.name+ " - "+ self.config.track,
			content: $('#modal-print_setup').html().present({
				_itc: cargohub.input_theme_classes
			}),
			callback: function($window) // Setup the controls inside the window
			{
				// Task Template selector - Allows quick defining of Tasks * Depends on current record type and the active organization.
				// -- Might return no results, in which case the function will not be available (and control disabled)
				$("#print-template_selector", $window).jqxDropDownList({
					theme: cargohub.jqxtheme,
					source: print_Templates_Source,
					displayMember: "description", valueMember: "uid",
					placeHolder: "(Loading templates)",
					width: 400, dropDownWidth: 400,
					autoDropDownHeight: true, animationType: "fade", enableBrowserBoundsDetection: true,
					disabled: true
				})
				/**
				 * Event: The list of templates has finished loading - Enabled the selector
				 */
				.on('bindingComplete', function() {
					if(print_Templates_Source.records.length == 0)
						$(this).jqxDropDownList({
							placeHolder: "<b>*** Can't print! ***</b> No templates currently available.",
							disabled: true
						});
					else {
						$(this).jqxDropDownList({
							placeHolder: "Choose the template:",
							disabled: false
						});

						// Begin by selecting first item (if it exists)
						$("#print-template_selector", $window).jqxDropDownList('selectIndex', 0);
					}
				})
				/**
				 * Event: Selecting a template will replace Title, Deadline and Description with the information returned by the selector
				 */
				.on('select', function(event) {
					$('#modal_print_ok', $window).jqxButton({disabled: false});
				});

				$("#print-parts_selector", $window).jqxDropDownList({
					theme: cargohub.jqxtheme,
					checkboxes: true,
					source: data_parts,
					displayMember: "title",	valueMember: "id",
					width: 400, dropDownWidth: 400,
					autoDropDownHeight: true,
					selectionRenderer: function(orig,index,str) {
						var checked = $("#print-parts_selector", $window).jqxDropDownList("getCheckedItems"), string = '', c2 = 0;
						if(checked.length == 0)
							string = "No parts were selected. Please check !";
						else if(checked.length <= 2) {
							for(; c2< checked.length; c2++)
								string+= (c2>0? ', ': '') + checked[c2].label;
						} else if(checked.length == data_parts.length)
							string = "All parts are selected";
						else
							string = checked.length + " out of " + data_parts.length + " parts selected";
						return "<span style='display: block; width: 365px; text-overflow: ellipsis; margin-top: 3px; background-color: transparent;'>"+string+"</span>";
					},
				})
				/**
				 * Event: One of the checkboxes in the Parts selector is checked or unchecked - Some affect the visibility of other controls.
				 */
				.on("checkChange", function(event)
				{
					if(event.args.value == "contacts") {
						// When this checkbox is changed, show or hide the "Contacts" selector
						if(event.args.checked)
							$("#print-contacts_selector", $window).parent().fadeIn();
						else
							$("#print-contacts_selector", $window).parent().fadeOut();
					}
				});

				// Check indexes for the type.panels which should be active by default (record type config, includes contacts)
				for(c=0; c<data_parts.length; c++)
					if(data_parts[c].send)
						$("#print-parts_selector", $window).jqxDropDownList("checkIndex", c);

				// Contacts selector (Dropwdown checkboxes) - Note: First contact cannot be turned off (should always be the Submitter?)
				$("#print-contacts_selector", $window).jqxDropDownList({
					theme: cargohub.jqxtheme,
					checkboxes: true,
					source: self.config.contacts, // Should contain the list of contacts connected to the record
					displayMember: "name", valueMember: "id",
					width: 400, dropDownWidth: 400,
					dropDownHeight: self.config.contacts.length> 8? 202: self.config.contacts.length * 25 + 2,
					selectionRenderer: function(orig,index,str) {
						var checked = $("#print-contacts_selector", $window).jqxDropDownList("getCheckedItems"), string = '';
						if(checked.length == 1)
							string = checked[0].label;
						else if(checked.length == self.config.contacts.length)
							string = "All contacts are selected";
						else
							string = checked.length + " out of " + self.config.contacts.length + " contacts selected";
						return "<span style='display: block; width: 365px; text-overflow: ellipsis; margin-top: 3px; background-color: transparent;'>"+string+"</span>";
					}
				});

				// Check and Disable first entry ( cannot be changed ) - This should (always) be the submitter contact.
				$("#print-contacts_selector", $window).jqxDropDownList("checkIndex", 0);
				$("#print-contacts_selector", $window).jqxDropDownList("disableAt", 0);

				// Setup the action for "Print Preview" button (used as the "Ok" button)
				$('#modal_print_ok', $window).jqxButton({disabled: true}).off('click').on('click', function()
				{
					// submit the create/edit form using ajax
					$('.modal_content > form', $window).submit();
//						data: {
//							rec_id: self.config.id,			// The record's ID
//							org_id: self.config.perspective	// Current organization (perspective for admin - Not useful for non-admin as it will default to the user's organization anyway)
//						},
				});
			}
		}, self);
	},

	// Necessary to access this externally for reset
	transfer_zone = null,		// Zone of the Transfer /Build-Email
	zone_complete = 1,			// Which one of the zone "steps" are ready (have been clicked at least once)
	checked_files = [],	// Which ones of the files are "checked" for attachment

	/**
	 * Prepares the modal named "Build E-mail" with options for selecting Content of a data transfer E-mail, as well as recipients and attachments
	 * - Data parts (triggers some conditions in the templates)
	 * - Record Contacts (list)
	 * - Record Attachments (list)
	 * - Recipients (automatically selected based on certain rules)
	 *
	 * If everything is ready, it opens the "E-mail" tab and automatically inserts data by using the Compose API to create recipient contacts and autoselect a Template for Subject/Content
	 */
	setup_Transfer = function($window)
	{
		var
		zones = {},					// Which zones are already initialized ?
		// The "Zone" of the transfer window that is currently active
		// -- Note: On first opening the attachments and recipients are disabled and must be done in steps, but once they are active navigation is free between them

		// DataAdapter for showing the record attachments
		attachments_Source = new $.jqx.dataAdapter({
			url: "/files?action=get_list",
			data: {
				link: "issue",
				link_id: self.config.id
			},
			datatype: "json",
			datafields: [
				{name: 'id', type: 'int'},
				{name: 'mimetype', type: 'string'},
				{name: 'analyzer', type: 'string'},
				{name: 'metadata', type: 'string'},	// Used to show the image preview
				{name: 'visibility', type: 'string'},
				{name: 'doctype', type: 'string'},
				{name: 'name', type: 'string'},
				{name: 'title', type: 'string'},	// Description or file name, depending if description is set or not
				{name: 'ext', type: 'string'},
				{name: 'size', type: 'int'},
				{name: 'mtime', type: 'date'},
				{name: 'chash', type: 'string'},
				{name: 'description', type: 'string'}
			]//,
			// Initial Sorting
//			sortcolumn: 'title',
//			sortdirection: 'asc'
		}),

		/**
		 * DataSource for the organization selector - Scans the partnership system on available organizations that can receive a certain record
		 */
		partners_Source = new $.jqx.dataAdapter({
			url: '?action=Get_Partners',
			data: {
				org_id: self.config.perspective,	// Current organization
				rt_key: self.config.type.key,		// The record type
				group_id: self.config.group.id	// Current group
			},
			datatype: "json",
			datafields: [
				{name: 'id', type: 'number'},
				{name: 'type_id'},
				{name: 'name'},
				{name: 'contact_id', type: 'number'},
				{name: 'true_att', type: 'boolean'}
			],
			id: "id"
		}),
		/**
		 * DataSource for the departments within the selected partner organization (if it exists)
		 */
		target_deps_Source = new $.jqx.dataAdapter({
			url: "?action=Get_Partner_Deps",		// Find the departments that can receive the record type in the target organization
			data: {
				rt_key: self.config.type.key,		// The record type
				group_id: self.config.group.id		// Data group
			},
			datatype: "json",
			datafields: [
				{name: 'id', type: 'number'},
				{name: 'name'},
				{name: 'contact_id', type: 'number'},
				{name: 'receive', type: 'boolean'}
			],
			id: 'id'
		}),
		deps_already_initialized = false, dep_system_change = false,

		/**
		 * Zone: E-mail Contents - Template, Data panels, Contacts
		 */
		setup_Contents = function()
		{
			var
			/**
			 * Source for the E-mail Template selector (inside this modal)
			 */
			email_Templates_Source = new $.jqx.dataAdapter({
				url: "/global/operations?action=Get_Templates",
				datatype: "json",
				data: {
					role: "email",			// Load E-mail templates for this control
					include_default: true,	// Include the default E-mail template (_render.html) - Still necessary, as the per-organization templates are not yet defined
					rt_id: (self.config.type.base_id && self.config.type.base_id != self.config.type.id)? self.config.type.base_id: self.config.type.real_id
				},
				datafields: [
		 			{name: 'id', type: 'int'},
					{name: 'description', type: 'string'}
				],
				id: "id",
			}),
			data_parts = self.config.parts.slice(0), // Clone
			contacts_enabled = false;

			$("#transfer-email_template", $window).jqxDropDownList({
				theme: cargohub.jqxtheme,
				source: email_Templates_Source,
				displayMember: "description", valueMember: "id",
				placeHolder: "(Loading templates)",
				width: "100%", dropDownWidth: 770,
				enableBrowserBoundsDetection: true,
				disabled: true
			})
			/**
			 * Event: The list of templates has finished loading - Enabled the selector
			 */
			.on('bindingComplete', function() {
				if(email_Templates_Source.records.length == 0)
					$(this).jqxDropDownList({
						placeHolder: "(There are no E-mail templates for this record type!)",
						disabled: true
					});
				else {
					$(this).jqxDropDownList({
						placeHolder: "The template will automatically set Title and Subject in the preview:",
						dropDownHeight: email_Templates_Source.records.length> 7? 184: null,
						autoDropDownHeight: email_Templates_Source.records.length<= 7? true: false,
						disabled: false
					});
					// Select the "Global template" here to maintain usage consistency with the previous version
					$(this).jqxDropDownList('selectIndex', 0);
				}
			})
			/**
			 * When another template other than the default is selected, the "Record data parts" checkbox is enabled;
			 */
			.on('change', function(e) {
				// If the index is 0, the checkbox is checked after enabling, to show the parts (they always do for the _render template)
				if(e.args.index == 0)
					$("#transfer-include_render", $window).jqxCheckBox('check');

				$("#transfer-include_render", $window).jqxCheckBox({
					disabled: e.args.index == 0
				});
			});

			// The checkbox for the "Include record data" (on top)
			$("#transfer-include_render", $window).jqxCheckBox({
				disabled: true,
				theme: cargohub.jqxtheme,
				checked: true	// Always starts checked, but it can be unchecked (if using custom template)
			})
			/**
			 * Event: When this checkbox is checked and unchecked, enable/disable the parts selector
			 */
			.on('change', function(e) {
				if(e.args.checked) {
					$("#transfer-parts_selector", $window).jqxListBox({disabled: false});
					if(contacts_enabled)
						$("#transfer-contacts_selector", $window).jqxListBox({disabled: false});
				} else {
					$("#transfer-parts_selector", $window).jqxListBox({disabled: true});
					$("#transfer-contacts_selector", $window).jqxListBox({disabled: true});
				}
			});

			// Record parts selector (Dropdown checkboxes) - Has some defaults (which are vaild for all types), but also custom data (defined record "panels" - fragments)

			// Contacts selector (Dropdown checkboxes) - Note: First contact cannot be turned off (should always be the Submitter?)
			$("#transfer-contacts_selector", $window).jqxListBox({
				theme: cargohub.jqxtheme,
				checkboxes: true,
				source: self.config.contacts, // Should contain the list of contacts connected to the issue
				displayMember: "name", valueMember: "id",
				width: "100%", height: 220,
				renderer: function(index, label, value) {
					var contact = self.config.contacts[index];
					return contact.name + " <span class='gray'>&lt;"+ contact.email+ "&gt;</span>";
				}
			});
			// Check first entry - This should be the submitter contact in most cases (but it might be changed my record master)
			$("#transfer-contacts_selector", $window).jqxListBox("checkIndex", 0);

			// Append the "Contacts" as an option for data parts
			data_parts.unshift({
				id: 'contacts',
				title: 'Linked Contacts',
				send: true // "Transfer" E-mail should contain the contacts (which are e-mail addresses)
			});

			$("#transfer-parts_selector", $window).jqxListBox({
				theme: cargohub.jqxtheme,
				checkboxes: true,
				source: data_parts,
				displayMember: "title",	valueMember: "id",
				width: "95%", height: 220
			})
			/**
			 * Event: One of the checkboxes in the Parts selector is checked or unchecked - Some affect the visibility of other controls.
			 */
			.on("checkChange", function(event)
			{
				if(event.args.value == "contacts") {
					// When this checkbox is changed, show or hide the "Contacts" selector
					if(event.args.checked) {
						$("#transfer-contacts_selector", $window).jqxListBox({disabled: false});
						contacts_enabled = true;
					} else {
						$("#transfer-contacts_selector", $window).jqxListBox({disabled: true});
						contacts_enabled = false;
					}
				}
			});

			// Check indexes for the type.panels which should be active by default
			for(c=0; c<data_parts.length; c++)
				if(data_parts[c].send)
					$("#transfer-parts_selector", $window).jqxListBox("checkIndex", c);

			zones.contents = true;
		},

		/**
		 * Zone: File attachments (coming from "Files" tab)
		 */
		setup_Attachments = function()
		{
			$("#attach-helpers > button", $window).hide();
			self.init_DataTable($("#transfer-attachments > div", $window), {
				// Config
				localization: {
					emptydatastring: "No files are available for attaching"
				},
				width: 750,
				source: attachments_Source,
				sortable: false,
				columns: [
					{dataField: 'id', text: "Attach?", width: 60, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
						return '<input type="checkbox" class="transfer-row-toggle" data-rowidx="'+row+'"'+(checked_files[row]?' checked="checked"':'')+'" />';
					}, sortable: false, filterable: false},
					{dataField: 'mimetype', text: "Preview", width: 90, sortable: false, filterable: false, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
						if(rowData.analyzer == "image") {
							var metadata = JSON.parse(rowData.metadata);
							if(metadata.scaler && metadata.scaler.thms)
								return '<img src="/uimg/thms/'+rowData.chash.substr(0,2)+ '/'+ rowData.chash.substr(2)+ '.jpg" alt="'+rowData.ext+'" />';
						}
						return '<img src="/img/mime/'+rowData.mimetype+'.png" alt="'+rowData.ext+'" />';
					}},
					{dataField: 'title', text: "File description or Name", sortable: false, filterable: true},
					{hidden: true, dataField: 'doctype', text: "File type", sortable: false, filterable: true}, // Searchable by document type
					{dataField: 'chash', text: "Down!", width: 60, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
						return '<a href="/files?action=download_attachment&ch='+rowData.chash+'&qp='+donrey.qp_conv(rowData.id)+'" title="Download this file"><img style="border: none;" src="/img/icons/download_cloud.png" alt="Down!" /></a>';
					}, sortable: false, filterable: false}
				],
				rowDetails: true, initRowDetails: function(id, datarow, element, rowinfo) { // Row details renderer
					var tpl_data = clone(datarow), metadata = null;
					// Shiny...
					tpl_data.size = number_format(tpl_data.size);

					// If the row contains image metadata (and the analyzer is of type "image" it means we have a thumbnail to display - use the "thms" version
					if(datarow.analyzer == "image") {
						metadata = JSON.parse(datarow.metadata);
						if(metadata.scaler && metadata.scaler.thml)
							tpl_data._thml = "/uimg/thml/"+ datarow.chash.substr(0,2)+ '/'+ datarow.chash.substr(2)+ '.jpg';
						rowinfo.detailsHeight = metadata.scaler.thml.height;
					} else
						rowinfo.detailsHeight = 60;
					element.addClass('datatable att-details').html($("#template-attachment-details").html().present(tpl_data)).height(rowinfo.detailsHeight);
				},
				selectionMode: "custom"
			}, {
				// Load complete callback
				"getrows": function(rows) {
					$("#transfer-attachments > div", $window).on("change", "input.transfer-row-toggle", function(e) {
						var rowidx = $(this).data('rowidx');
						checked_files[rowidx] = this.checked;
						if(this.checked)
							$("#transfer-attachments > div", $window).jqxDataTable('selectRow', rowidx);
						else
							$("#transfer-attachments > div", $window).jqxDataTable('unselectRow', rowidx);
					});

					// Default checked files (depend on visibility)
					if(rows.length > 0) {
						for(c=0; c<rows.length; c++)
							if(rows[c].visibility == 'public' || rows[c].visibility == 'shared') {
								checked_files[c] = true;
								$("#transfer-attachments > div", $window).jqxDataTable("selectRow", c);
							}
					}

					// The buttons "Check all", "Clear all" as helpers for the attachments list
					$("#attach-check-all", $window).jqxButton({
						theme: cargohub.jqxtheme,
						width: "46%", height: 32
					})
					.on('click', function() {
						for(c=0; c<rows.length; c++)
							checked_files[c] = true;
						$("#transfer-attachments > div", $window).jqxDataTable('selectAll');
					});
					$("#attach-clear-all", $window).jqxButton({
						theme: cargohub.jqxtheme,
						width: "46%", height: 32
					})
					.on('click', function() {
						checked_files = [];
						$("#transfer-attachments > div", $window).jqxDataTable('clearSelection');
					});

					$("#attach-helpers > button", $window).show();
				}
			}, "attachments");

			zones.attachments = true;
		},

		/**
		 * Zone: Recipients - Preset recipients selection (will be extended with information about the recipient)
		 */
		setup_Recipients = function()
		{
			// The target organization selector (single choice)
			$("#transfer-org_select", $window).jqxDropDownList({
				theme: cargohub.jqxtheme,
				source: partners_Source,
				displayMember: "name", valueMember: "id",
				width: "100%", height: 24,
				placeHolder: "(Scanning available organizations)",
				disabled: true
			})
			/**
			 * Event: The list of partners is ready, enable the control
			 */
			.on('bindingComplete', function() {
				if(deps_already_initialized)
					$("#transfer-dep_select", $window).jqxListBox('uncheckAll');

				if(partners_Source.records.length == 0) {
					$(this).jqxDropDownList({
						placeHolder: "(No organizations are available for selection)"
					});

					$("#transfer-dep_select", $window).jqxListBox({
						disabled: true
					});

					// Allow sending the e-mail even without an organization/department - However no contacts will be available
					$('#modal_transfer_ok', $window).jqxButton({disabled: false});
					return;
				}

				$(this).jqxDropDownList({
					placeHolder: "<span class='red thick'>Choose the organization first:</span>",
					dropDownHeight: partners_Source.records.length> 8? 202: partners_Source.records.length * 25 + 2,
					disabled: false
				});

				// If there is only one partner to select, select it directly !
				// - This should also trigger the "select" event, which should load target departments into the second dropdown
				if(partners_Source.records.length == 1) {
					// Indicate that the system is automatically selecting the organization (which should load the departments)
					org_system_select = true;
					$(this).jqxDropDownList('selectIndex', 0);
				} else {
					$("#transfer-dep_select", $window).jqxListBox({
						disabled: true
					});
				}
			})
			/**
			 * Event: The partner organization is selected
			 */
			.on("select", function(event) {
				org_system_select = false;

				// (Re)Activate the source for the department selector and read the list of departments which are externally visible
				target_deps_Source._source.data.org_id = $("#transfer-org_select", $window).val();

				if(deps_already_initialized)
					target_deps_Source.dataBind();
				else {
					$("#transfer-dep_select", $window).jqxListBox({
						source: target_deps_Source,
					});
					deps_already_initialized = true;
				}
			});

			// The partner organization departments selector (checkboxes)
			$("#transfer-dep_select", $window).jqxListBox({
				theme: cargohub.jqxtheme,
				displayMember: "name", valueMember: "id",
				width: "100%", height: 220,
				checkboxes: true
			})
			.on('bindingComplete', function() {
				// Looks like bindingComplete is triggered for addItem, which is why we need a recursion breaker
				if(dep_system_change)
					return;

				$("#transfer-dep_select", $window).jqxListBox('clearSelection');

				if(target_deps_Source.records.length == 0) {
					dep_system_change = true;
					$("#transfer-dep_select", $window).jqxListBox('addItem', {
						label: "(No Departments/Stations are available)",
						value: 0,
						checked: true
					});
					$("#transfer-dep_select", $window).jqxListBox({disabled: true});
					$("#transfer-dep_select", $window).trigger('checkChange');

					dep_system_change = false;
					return;
				}

				// We have a selection, enable control
				$(this).jqxListBox({disabled: false});

				// When there is only one department to select, save the user one click by selecting it automatically
				if(target_deps_Source.records.length == 1) {
					$(this).jqxListBox('checkIndex', 0);
				}

			})
			/**
			 * Event: Department is checked - When at least one is checked, enable the "E-mail preview" button
			 */
			.on('checkChange', function() {
				var checked_deps = $(this).jqxListBox('getCheckedItems');
				if(checked_deps.length >= 1) {
					$('#modal_transfer_ok', $window).jqxButton({disabled: false});
				} else {
					$('#modal_transfer_ok', $window).jqxButton({disabled: true});
				}
			});

			zones.recipients = true;
		},

		/**
		 * Prepares and activates a certain zone (only on first use)
		 */
		activate_Zone = function(zone)
		{
			$("#transfer-navbar button[data-zone='"+zone+"']", $window).jqxButton({template: "success"});
			$("#transfer-"+zone, $window).show();

			transfer_zone = zone;

			switch(zone) {
			// Contents has controls for Template, Data sections and Contacts
			case 'contents':
				if(!zones.contents)
					setup_Contents();
				if(!zone_complete)
					zone_complete = 1;
				break;
			// Attachments is a Datatable with a mirror of record-linked files
			case 'attachments':
				if(!zones.attachments)
					setup_Attachments();
				if(zone_complete == 1) {
					$("#transfer-navbar button[data-zone='recipients']", $window).jqxButton({disabled: false});
					$('#modal_transfer_ok', $window).val("Next step > Recipients");
					zone_complete = 2;
				}

				// Sometimes the table doesn't render fully for some weird reason... do a visual refresh after a second? ... hopefully the table has loaded by then
				setTimeout(function() {
					$("#transfer-attachments > div", $window).jqxDataTable('refresh');
				}, 999);
				break;
			// Recipients contains Organizations and Departments
			case 'recipients':
				if(!zones.recipients)
					setup_Recipients();

				$('#modal_transfer_ok', $window).val("Preview your E-mail");
				zone_complete = 3;
				break;
			}

			if(zone == 'attachments')
				$("#attach-helpers", $window).show();
			else
				$("#attach-helpers", $window).hide();
		},

		/**
		 * Returns the collected information about the message parts
		 * - Note that attachments are currently inside "parts" (might be changed later)
		 *
		 * @returns object
		 */
		get_Parts = function($window)
		{
			var parts = {
				list: [],
				auto_render: $("#transfer-include_render", $window).val() // The new ability to turn on/off the _render completely
			},
			// List of parts themselves
			checked_parts = $("#transfer-parts_selector", $window).jqxListBox('getCheckedItems'),
			checked_contacts = $("#transfer-contacts_selector", $window).jqxListBox('getCheckedItems'),
			attachments = $("#transfer-attachments > div", $window).jqxDataTable('getSelection');

			// Parse the data
			$.each(checked_parts, function() {
				parts.list.push(this.value);
			});
			if(checked_contacts) {
				parts.contacts = [];
				$.each(checked_contacts, function() {
					parts.contacts.push(this.value);
				});
			}

			if(attachments.length) {
				parts.attachments = [];
				$.each(attachments, function() {
					parts.attachments.push(this.id);
				});
			}

			return parts;
		},

		/**
		 * This method is called after the E-mail tab is loaded an the compose component is ready.
		 * It's purpose is to inject all the data from the transfer window into the E-mail Compose:
		 * - Contacts (To, Cc)
		 * - Subject (Selected template's processed title)
		 * - Body (Selected template's body, combined with the Template Parts)
		 * - (Real) Attachments (When selected)
		 */
		transfer_Callback = function(com_compose)
		{
			var parts = get_Parts();

			// Contacts (Remotely, depending on Partner info)
			$.post("?action=Target_Presets", {
				type_key: self.config.type.key,
				org_id: $("#transfer-org_select", $window).val(),
				deps_ids: $("#transfer-dep_select", $window).val(),
			}, function(x) {
				if(x.success) {
					com_compose.add_Contacts(x.contacts, 'to');
				} else
					cargohub.its.http_fail(null, "Record.Process::Target_Presets");
			})
			.fail(cargohub.its.http_fail);

			// Files which have been selected are reinserted as "pre" (with new ID's) - Those will be connected to the message being sent (independent entries in the files database).
			// This allows the message/conversation viewer to show the files which have been attached to the message (permanently); Even if the original record/issue attachment was unlinked
			if(parts.attachments && parts.attachments.length > 0) {
				cargohub.record_email.import_Attachments(parts.attachments);
				// With the new linking system, the e-mail content itself should use the record-linked attachments directly. Instead, support arbitrary attachments into any message.
				delete parts.attachments;
			}

			// Subject/Template + Body (includes parts)
			$.get("/global/operations?action=Render_Template", {
				id: $("#transfer-email_template", $window).val(),
				org_id: self.config.perspective,
				rt_id: self.config.type.base_id?self.config.type.base_id:self.config.type.id,
				rec_id: self.config.id,
				parts: JSON.stringify(parts),
				// In case of templates other than the "Default global", it automatically adds the _render.html to the template ( to the bottom ), unless {#plugin:RecordRender} is already present in the template
//				auto_render: parts.auto_render,
				// TODO: This is not the proper way ! It should be part of "Render_Template", with the auto-message injected in the template itself (as a variable?)
				auto_message: true	// Also read the Automatic Message (this will trigger a re-read of the complete record in case the template exists and is non-empty, in order to assemble the title/content
			}, function(r, textStatus, jqXHR) {

				com_compose.set_Subject(r.title);
				com_compose.set_Body(r.body);

				// Short timeout before focus to allow TinyMCE to sort itself out
				setTimeout(function() {
					// com_compose.focus_Body(0);
				}, 100);

			}, 'json')
			.fail(cargohub.its.http_fail);

			// Turn on share record (Contacts are checked if they are a Target E-mail of an organization)
			cargohub.record_email.set_option('share', true);

			// Close the window and move focus to the TinyMCE content
			cargohub.components.spinner.Off(250);
			$window.jqxWindow('close');
		}
		;

		// Setup the zone switcher area
		$("#transfer-navbar button", $window).jqxButton({
			theme: cargohub.jqxtheme,
			height: 32,
			width: 212
		})
		/**
		 * Event: One of the admin buttons is clicked (special functions)
		 */
		.click(function(event)
		{
			// Begin by hiding the previous zone and it's button
			$("#transfer-navbar button[data-zone='"+transfer_zone+"']", $window).jqxButton({template: "default"});
			$("#transfer-"+transfer_zone, $window).hide();
			// ... then activate the new zone
			activate_Zone($(this).data('zone'));
		});

		// Disable Preset Recipients button until File Attachments is first activated
		$("#transfer-navbar button[data-zone='recipients']", $window).jqxButton({disabled: true});

		// Tooltips
		$(".info-tip", $window).each(cargohub.components.ui.add_Tooltip);

		// Begin with "contents" zone (should be default)
		activate_Zone(transfer_zone);


		// Setup the action for "E-mail Preview" button (used as the "Ok" button)
		$('#modal_transfer_ok', $window).off('click').on('click', function()
		{
			// Begin by hiding the previous zone and it's button
			$("#transfer-navbar button[data-zone='"+transfer_zone+"']", $window).jqxButton({template: "default"});
			$("#transfer-"+transfer_zone, $window).hide();
			switch(zone_complete) {
			case 1:
				activate_Zone('attachments');
				break;
			case 2:
				activate_Zone('recipients');
				break;
			case 3:
				$("#attach-helpers", $window).hide();

				cargohub.components.spinner.On(null, null, null, null, 250);
				// Select the E-mail tab amd use it's callback system to call "compose-callback" which will then prepare everything and use the data retrieved by transfer_Callback
				self.select_Tab_With_Params('email', {
					trigger: "compose-callback",
					callback: transfer_Callback
				});
				return;
			default:
				cargohub.alert("Error while initializing the Transfer window. Try refreshing the page");
				$window.jqxWindow('close');
			}
		});
	},

	/**
	 * Opens the modal for searching a data group for the record. Search is done by using a combobox, as some record types might have hundreds or thousands of groups
	 */
	modal_Change_Group = function()
	{
		cargohub.components.modal.create('change-group', {
			btn_roles: {cancel: "cancel"}
		});

		cargohub.components.modal.open('change-group', {
			width: 600,	height: 120,
			title: "Move to another " +self.config.group.config.name,
			content: $('#modal-set_group').html().present({
				_itc: cargohub.input_theme_classes,
				config: self.config.group.config
			}),
			callback: function($window) // Setup the controls inside the window
			{
				// The User Search ( Combobox )
				var groups_Source = new $.jqx.dataAdapter({
					url: "?action=Get_Groups",
					datatype: "json",
					data: {
						org_id: self.config.perspective,
						rt_id: self.config.type.id
					},
					datafields: [
						{name: 'id', type: 'number'},
						{name: 'name'}
					],
					id: "id"
				}), $group_search = $("#group-search", $window);

				$group_search.jqxComboBox({
					theme: cargohub.jqxtheme,
					searchMode: "containsignorecase",
					autoComplete: true,	minLength: 0,
					source: groups_Source,
					displayMember: "name", valueMember: "id",
					placeHolder: "Type to search or click the arrow to show all",
					width: 400, dropDownWidth: 400, dropDownHeight: 178,
					animationType: "none"
				})
				//
				/**
				 * Event: A data group is selected - Show information about that group
				 */
				.on('select', function(event) {

					$('#modal_change-group_ok', $window).jqxButton({disabled: false});
				});

				// Setup the action for "OK" button
				$('#modal_change-group_ok', $window).jqxButton({disabled: true}).off('click').on('click', function()
				{
					// Check if a group has been actually selected
					if(!$group_search.val())
						return cargohub.alert("Please select a new "+ self.config.group.config.name);

					if($group_search.val() == self.config.group.id)
						return cargohub.alert("The selected "+ self.config.group.config.name +" is already set.");

					// Update department access list
					$.post("?action=Set_Group", {
						id: $group_search.val(),
					}, function(x) {
						if(x.success) {
							// Update the local information...
							self.config.group.id = $group_search.val();
							$("#record-group").html(self.config.group.config.name+ ':<br /><b>'+x.group_name+'</b>');

							// ... and close the assign window
							$window.jqxWindow('close');
							cargohub.alert(x.response, "Success !", "thumb_up.png");

							if(cargohub.record_send)
								cargohub.record_send.reset_options(); // Reload the possible partners list. It depends on active group
						} else
							cargohub.its.http_fail(null, "Record.Process::set_group");
					})
					.fail(cargohub.its.http_fail);
				});
			}
		}, self);

		// Signature option
//		if(self.config.user_mode)
//			$("#user-signature", $opt_area).jqxCheckBox({
//				theme: cargohub.jqxtheme,
//				checked: false
//			});
		// Finalize option
//		if(params.submit_mode)
//			$("#finalize-option", $opt_area).jqxCheckBox({
//				theme: cargohub.jqxtheme,
//				checked: false
//			});

	},

	/**
	 * Opens the modal used to select an user for assignment. This is a two-step process, first select department then find/click user.
	 * When the operation is complete, the record might change status as well, and assignment notification is being sent to the selected user.
	 */
	modal_Assign_User = function()
	{
		// 07062024 changes by iwi | start
		cargohub.components.modal.create('user-search', {
			btn_roles: {cancel: "cancel"}
		});
		// 07062024 changes by iwi | end

		cargohub.components.modal.open('user-search', {
			width: 860,	height: 550,
			title: "Assign user to handle record",
			content: $('#modal-user_search').html().present({
				_itc: cargohub.input_theme_classes
			}),
			callback: function($window) // Setup the controls inside the window
			{
				// The User Search ( Combobox )
				var
				members_Source = new $.jqx.dataAdapter({
					datatype: "json",
					datafields: [
						{name: 'id', type: 'number'},
						{name: 'name'}
					],
					id: "id"
				}),
				// User search combobox
				$user_search = $("#user-search", $window),
				enable_User_Search = function(url, data, user_search_Placeholder) {
					members_Source._source.url = url;
					members_Source._options.data = data;
					$user_search.jqxComboBox('clearSelection');
					$user_search.jqxComboBox({
						source: members_Source,
						placeHolder: user_search_Placeholder||"Type to search or click the arrow to show all",
						disabled: false
					});
				};

				// The Active Departments selector
				$("#user-search-dep-list", $window).jqxDropDownList({
					theme: cargohub.jqxtheme,
					displayMember: "name", valueMember: "id",
					placeHolder: "(Loading departments...)",
					width: 450,
					dropDownWidth: 450, autoDropDownHeight: true, animationType: "fade",
					// Member count for department
					renderer: function(index, label, value) {
						return '<span class="gray right">('+self.active_DepsSource[index].count_members+' members)</span>'+ label;
					},
					disabled: true
				})
				/**
				 * Event: The department selector is changed - Reload the page with organization selected which would view the grid as from the selected organization.
				 */
				.on("select", function(event) {
					if(event.args.item.value)
						// Read from a department membership list
						enable_User_Search('/manager/department?action=get_members', {
							id: event.args.item.value
						});
					else
						// When no ID present, read the organization user list instead
						enable_User_Search('/manager/organization?action=get_users', {
							id: self.config.perspective,
							quickmode: true
						});

					// Clear the selected user text
					$("#quick-user-information", $window).html('<div class="autocenter gray"><div>Please select an user to see more details</div></div>');
				});

				// Start loading departments (or access them if already loaded), and only choose the enabled ones to build the source array for the dropdown.
				if(self.active_DepsSource.length > 0) {
					$("#user-search-dep-list", $window).jqxDropDownList({
						source: self.active_DepsSource,
						placeHolder: "Select department:",
						disabled: false
					});
					if(self.active_DepsSource.length == 1)
						$("#user-search-dep-list", $window).jqxDropDownList('selectIndex', 0);
				} else
					self.source_Deps(true, function(records) {
						self.build_Active_DepsSource(records);

						if(self.active_DepsSource.length > 0) {
							$("#user-search-dep-list", $window).jqxDropDownList({
								source: self.active_DepsSource,
								placeHolder: "Select department:",
								disabled: false
							});
							if(self.active_DepsSource.length == 1)
								$("#user-search-dep-list", $window).jqxDropDownList('selectIndex', 0);
						} else {
							$("#user-search-dep-list", $window).jqxDropDownList({
								placeHolder: "(Currently no departments have access. Check Access control)",
								disabled: true
							});
							enable_User_Search('/manager/organization?action=get_users', {
								id: self.config.perspective,
								quickmode: true
							});
						}
					}, function() { // Error
						self.active_DepsSource = [];
						$("#user-search-dep-list", $window).jqxDropDownList({
							placeHolder: "(The organization has no departments)",
							disabled: true
						});
						enable_User_Search('/manager/organization?action=get_users', {
							id: self.config.perspective,
							quickmode: true
						});
					});

				$user_search.jqxComboBox({
					theme: cargohub.jqxtheme,
					searchMode: "containsignorecase",
					autoComplete: true,	minLength: 0,
					displayMember: "name", valueMember: "id",
					width: 450, height: 22,
					dropDownWidth: 450,
					animationType: "none",
					disabled: true
				})
				// Event: The user list is loaded (or has an error)
				.on('bindingComplete', function(event) {
					if(members_Source.records.length == 0) {
						$(this).jqxDropDownList({
							placeHolder: "(The organization has no users! Assignment not possible)",
							width: 450,
							disabled: true
						});
						$("#quick-user-information > div > div").css("color", "red").html("(The organization has no users! Assignment not possible)");
					} else {
						$(this).jqxComboBox({
							dropDownHeight: members_Source.records.length> 8? 210: null,
							autoDropDownHeight: members_Source.records.length<= 8? true: false,
							disabled: false
						});
						// Autoopen the user search when loaded
						$user_search.jqxComboBox('open');
					}
				})
				// Event: An user is selected - Load quickinfo in the box below
				.on('select', function(event) { // value on select event - Which puts the group id in the hidden input
					if(event.args === undefined)
						return false;

					if (event.args.item != null && event.args.item.originalItem.id > 0) {
						// Load user contact information into the div below:
						$.get("/manager/user?action=get_details&id=" + event.args.item.originalItem.id, function(contents, textStatus, jqXHR)
						{
							$("#quick-user-information", $window).html(contents);
						})
						.fail(cargohub.its.http_fail);

						$('#modal_user-search_ok', $window).jqxButton({disabled: false});
					} else {
						$user_search.jqxComboBox('clearSelection');
						$("#quick-user-information", $window).html('<div class="autocenter gray"><div>Please select an user to see more details</div></div>');
						return false;
					}
				});

				// Setup the action for "OK" button (start with it disabled, enabled when user has been selected)
				$('#modal_user-search_ok', $window).jqxButton({disabled: true}).off('click').on('click', function()
				{
					$.post("?action=assign_user", {
						id: $user_search.val(),
                                                claim_type_name: $("#claim_type_name").html()
					}, function(x) {
						// Update the local information...
                                                 $("#assigned-user").html('Assigned to:<br /><b class="info-tip" data-tooltip="'+x.user_email+'">'+x.user_name+' </b><button class="record_details_unassign_user_btn" data-op="unassign-user"  data-uid="'+$user_search.val()+'"><img alt="Off" src="/img/icons16/cancel.png"></button>');
//						$("#assigned-user").html('Assigned to:<br /><b class="info-tip" data-tooltip="'+x.user_email+'">'+x.user_name+'</b>');
//						$("#assigned-user .info-tip").each(cargohub.components.ui.add_Tooltip);

						// ... and close the assign window
						$window.jqxWindow('close');
						console.log(x.response);
						cargohub.alert(x.response, "Success !", "thumb_up.png");

						if(cargohub.record_contacts)
							cargohub.record_contacts.reload();
						if(cargohub.record_sharing)
							cargohub.record_sharing.reload();
						//
						createTaskWithSpecificTemplate("assignUser");	
						
					})
					.fail(cargohub.its.http_fail);
				});
			}
		}, self);
	},

	/**
	 * Setup actions for each entry in the menu and also shortcut buttons present in the "operations" bar ( each of the entries and buttons contain a data-op="..." value )
	 */
	setup_Menu_and_Operations = function()
	{
		var popup_menus = new Array; // Menus which are created first time they are needed (pop-up)

		// link menu entries, #rec-operations bar buttons and any .op(eration)-buttons on the page
		$(document).on("click", ".jqx-menu-item, .jqx-menu-item-top, .op-button, .op-link, #rec-operations button", function(event) {
			var data = $(this).data();//,  tpl_data = {"jqxtheme":cargohub.jqxtheme, "_itc":cargohub.input_theme_classes}, cmd_elem = this;
			switch(data.op) {
			// Load another URL (might be dynamically constructed from the content of the record)
			case 'link':
				if(data.confirm == true) {
					if(!confirm($(this).parent().data('confirm_text'))) {
						event.preventDefault();
						break;
					}
				}

				var win = window.open(data.url, '_blank');
				win.focus();
				break;

			// Find and open a panel
			case 'open-panel':
				self.select_Tab_With_Params('panels', {
					trigger: "open-panel",
					id: data.id
				});
				break;

			// One of the four buttons from "Append data" ( or Create )
			case 'create':

				switch(data.item) {
				// Moves to the files tab and triggers the upload window
				case 'file':
					self.load_file_uploader(function() {
						self.select_Tab_With_Params('files', {
							trigger: "files-upload"
						});
					});
					break;

				// Moves to task tab and and triggers the task create command
				case 'task':
					self.select_Tab_With_Params('tasks', {
						trigger: "task-create"
					});
					break;

				// Moves to the notes tab and triggers the note create command
				case 'note':
					self.select_Tab_With_Params('notes', {
						trigger: "note-create"
					});
					break;

				// Moves to contacts tab and triggers the contact link command
				case 'contact':
					break;
				}
				break;

			// Switch issue type and load the search grid
			case 'sw_rt':
				cargohub.application.save_Preferences({
					"rt_id": $(this).data('id')
				}, function() {
					document.location = "/issue/list";
				});
				break;

			case 'open_trackid':
				var trackid = $('#record_search #open_trackid').val();
				// prevent submitting the form if there are less than 12 characters in the Open by tracking ID control:
				if(trackid.length < 12)
					return cargohub.alert("Please enter a full Track ID ! (Use a result from the search box)");

				document.location = "/record/process:"+ trackid+ location.hash;
				break;

			// Set Status and Priority for current organization via an ajax call. This does not reload the record, but the information in the "Overview" does get changed
			case 'set-stat-prio':
				call_Set_StatPrio(data.fn, data.key);
				break;

			// Opens a modal window (for different purposes)
			case 'open-modal':
				switch(data.modal) {
				// Find another data group and move the record to it (global, only owner organization can change)
				case 'change-group':
					if(!cargohub.application.user_Test_Privileges('issue_set_group'))
						return cargohub.alert("Data group change is not permitted", "Not allowed", "denied.png");

					modal_Change_Group(); break;
				// Choose an organization user to handle the record (per organization)
				case 'assign-user':
					if(cargohub.application.user_Test_Privileges('issue_self_assign,issue_assign_to'))
						modal_Assign_User();
					else
						return cargohub.alert("Changing assignment is disallowed", "Not permitted", "denied.png");
					break;
				// Control which departments from the organization have access to the record (per organization)
				case 'access-deps':
					self.source_Deps(true, function(records) {
						modal_Access_Deps();
					}, function() {
						return cargohub.alert("The organization has no departments");
					});
					break;
				// Print options - Allows a template and data parts to be selected for printing
				case 'print':
//					cargohub.alert("This function is deprecated. Use the &quot;Docs&quot; tab to create a document and select a template"); break;
					modal_Print(); break;
				// Transfer selector - Allows a template and data parts to be selected for data transfer (E-mail + Sharing)
				case 'transfer':
					self.open_Modal_Transfer(); break;
				}
				break;
                                
                                
                            case 'unassign-user':
				if(!confirm("Are you sure you want to unassign this record ?"))
					return;

				$.post("?action=unassign_user",{
						id: $(this).data('uid')
					}, function(x) {
					// Update the local information...
					$("#assigned-user").html('<span class="gray info-tip" data-tooltip="Record is not assigned anymore.<br />Click on the button to reassign to someone else">No longer assigned</span>');
					$("#assigned-user .info-tip").each(cargohub.components.ui.add_Tooltip);

					cargohub.alert(x.response, "Success !", "thumb_up.png");

					//cargohub.reload();
				})
				.fail(cargohub.its.http_fail);
                        
                        
                         
                        
				break;

			// After confirmation, it removes the assignment through an ajax call and updates the "Assigned to:" area
			case 'unassign':
				if(!confirm("Are you sure you want to unassign this record ?"))
					return;

				$.post("?action=unassign_user", function(x) {
					// Update the local information...
					$("#assigned-user").html('<span class="gray info-tip" data-tooltip="Record is not assigned anymore.<br />Click on the button to reassign to someone else">No longer assigned</span>');
					$("#assigned-user .info-tip").each(cargohub.components.ui.add_Tooltip);

					cargohub.alert(x.response, "Success !", "thumb_up.png");

					if(cargohub.record_contacts)
						cargohub.record_contacts.reload();
					if(cargohub.record_sharing)
						cargohub.record_sharing.reload();
				})
				.fail(cargohub.its.http_fail);
				break;

			// Displays a pop-up menu (jqxMenu) below the triggering button from which a single selection is available. Used for simple commands.
			case 'popup-menu':

				// First checks access rights on the button that would trigger a popup menu
				switch(data.menu) {
					case 'statuses':
						if(!cargohub.application.user_Test_Privileges('issue_set_status'))
							return cargohub.alert("Status changing is not permitted", "Not allowed", "denied.png");
						break;
					case 'priorities':
						if(!cargohub.application.user_Test_Privileges('issue_set_priority'))
							return cargohub.alert("Priority changing is not permitted", "Not allowed", "denied.png");
						break;
				}

				var pos = $(this).position(),
				popup_initialized = function() {

				},
				popup_itemclick = function(e) {
					// Checks popup element entry if it requires confirmation
					if($(e.args).data('confirm') == true) {
						if(!confirm($(e.args).data('confirm_text')))
							e.preventDefault();
					}
				};

				// check if this popup menu is used first time, if so, create the jqx object
				if(!popup_menus[data.menu]) {
					popup_menus[data.menu] = $($('#popup_menu-'+data.menu).html()).jqxMenu({
						theme: cargohub.jqxtheme,
						autoOpenPopup: false,
						mode: 'popup'
					})//.css({position: "absolute"})
					/**
					 * Event: When finished initializing, add some events on the entries
					 */
					.on('initialized', popup_initialized)
					/**
					 * Event: On clicking on some items, do extra processing if necessary
					 */
					.on('itemclick', popup_itemclick);

					popup_menus[data.menu].jqxMenu('open', pos.left + 1, pos.top + 39);
				} else {
					popup_menus[data.menu].css({left: pos.left+1, left: pos.left+39});
					popup_menus[data.menu].jqxMenu('open', pos.left + 1, pos.top + 39);
				}
				break;

			// Other commands (Temporary location, they should eventually get their own op/category)
			case 'command':
				switch(data.cmd) {
				// Switch to the E-mail tab
				case 'send-email':
					self.select_Tab_With_Params('email');
					break;

				// Mark this record as "Closed" (removed from default grid view)
				case 'close':
					let taskCounter = 0;
					let counterMsg = '';
					$.post("?action=Mark_Process", {
						command:'count'
					}, function(x) {
						if(x.success){
							taskCounter= x.count;
							if(taskCounter > 0){
								counterMsg = '\nActive tasks ('+taskCounter+') will be closed also';
							}
							_markProcess(counterMsg);
						}
					})
                                         //IWI If record close then all active capa should be close start
                                    function _markProcess(counterMsg) {
                                        if (!confirm("Are you sure you want to close this record ?\nIt can be reopened if necessary." + counterMsg))
                                            return;

                                        if (self.config.type.id == '31' || self.config.type.id == '34' || self.config.type.id == '7') {
                                            let capaCounter = 0; //IWI If record close then all active capa should be close
                                            let capaCounterMsg = ''; //IWI If record close then all active capa should be close
                                            $.post("?action=Mark_Process", {
                                                command: 'active_capa_count',
                                                link_id: self.config.id,
                                                track: self.config.track,
                                            }, function (x) {
                                                if (x.success) {
                                                    capaCounter = x.active_capa_count;
                                                    console.log('capaCounter',capaCounter);
                                                    if (capaCounter > 0) {
                                                        // capaCounterMsg = '\nActive CaPa (' + capaCounter + ') will be closed also';
							capaCounterMsg = '';

                                                        if (!confirm("Are you sure you want to close this record ?\nIt can be reopened if necessary." + capaCounterMsg)) {
                                                            return;
                                                        } else {

                                                            $.post("?action=Mark_Process", {
                                                                command: "close"
                                                            }, function (x) {
                                                                // Record is closed
                                                                document.location.reload();
                                                            }).fail(cargohub.its.http_fail);
                                                        }

                                                    }else{
                                                        $.post("?action=Mark_Process", {
                                                                command: "close"
                                                            }, function (x) {
                                                                // Record is closed
                                                                document.location.reload();
                                                            }).fail(cargohub.its.http_fail);
                                                    }
                                                }
                                            });

                                        } else {
                                            $.post("?action=Mark_Process", {
                                                command: "close"
                                            }, function (x) {
                                                // Record is closed
                                                document.location.reload();
                                            }).fail(cargohub.its.http_fail);
                                        }
//function _markProcess(counterMsg){
//						if(!confirm("Are you sure you want to close this record ?\nIt can be reopened if necessary."+counterMsg))
//							return;
//
//						$.post("?action=Mark_Process", {
//							command: "close"
//						}, function(x) {
//							// Record is closed
//							document.location.reload();
//						}).fail(cargohub.its.http_fail);
//					}

 //IWI If record close then all active capa should be close end


                                    }
					break;

				// Mark this record as "Open" (Make visible again in the main grid)
				case 'open':
					$.post("?action=Mark_Process", {
						command: "open"
					}, function(x) {
						// Record is reopened
						document.location.reload();
					})
					.fail(cargohub.its.http_fail);
					break;

				// Mark this record as "Hidden" (organization access disabled)
				case 'delete':
					if(!confirm("Are you sure you want to DELETE this record ?"))
						return;

					$.post("?action=Mark_Process", {
						command: "delete"
					}, function(x) {
						// Record was closed, it should not be visible anymore (by default). * TODO: Admin should still see it -> redirect to sharing tab to show the change
						document.location = "/issue/list";
					})
					.fail(cargohub.its.http_fail);
					break;
				}
				break;
			}
		});
	},

	// Temporary container for parameters sent to the tab.selected() callback, used to perform certain actions after a tab has been selected. Normally auto-cleaned after action has been performed
	tab_select_params = null,

	/**
	 * Most of the tabs in this module contain similar functionality:
	 * - Menu Bar
	 * -- DataTable
	 * -- Window for adding new data
	 * -- Window for editing data
	 * -- Delete (with confirmation)
	 * Those windows are defined in the tab-templates themselves, and their functionality is in dynamically loaded
	 *
	 */
	init_Overview_Tab = function(tab_id)
	{
		// Load the module handing data for this tab
		donrey.load_Modules("record/_"+tab_id, function() {
			cargohub["record_"+tab_id].config = self.config["tab_"+tab_id]; delete self.config["tab_"+tab_id];
			cargohub["record_"+tab_id].init();
			if(cargohub["record_"+tab_id].selected) {
				cargohub["record_"+tab_id].selected(tab_select_params);
				tab_select_params = null;
			}
		}, "?v="+cargohub.version);


		// Documentation datapanel tab show doc tab data   26022025  changes by iwi | start
		if(self.config.type.id == 12 && tab_id == 'panels'){
			var _tab_id = 'docs';
			// Load the module handing data for this tab
			donrey.load_Modules("record/_"+_tab_id, function() {
				cargohub["record_"+_tab_id].config = self.config["tab_"+_tab_id]; delete self.config["tab_"+_tab_id];
				cargohub["record_"+_tab_id].init();
				if(cargohub["record_"+_tab_id].selected) {
					cargohub["record_"+_tab_id].selected(tab_select_params);
					tab_select_params = null;
				}
			}, "?v="+cargohub.version);
		}
		// Documentation datapanel tab show doc tab data   26022025  changes by iwi |  end
	},

	// Single-Instance data loading flags
	flag_deps_loaded = false,
	flag_sharing_loaded = false
	;

	this._public({
		/**
		 * Section initialization
		 */
		init: function() {
			if(!self.config)
				return cargohub.its.init_critical_error("JavaScript frontend did not receive any configuration");

			var record_load_config = {
				track: self.config.track, // Record to load
				org_id: self.config.organization.id, // Viewed as organization...
				rt_id: self.config.type.real_id
			};

			// Organization perspective area (only if user is admin), above main menu
			if(cargohub.application.user_Is_Admin())
				setup_Org_Perspective();

			// I don't like HTML errors...
			$("#record-process-mainmenu").find("li.menu-separator").each(function() {
				$(this).attr('type', 'separator');
			});
			$("#record-process-mainmenu").jqxMenu({
				theme: cargohub.jqxtheme, width: cargohub.fullwidth,
				minimizeWidth: null, animationShowDuration: 200, animationHideDuration: 100,
				autoOpen: false
			});

			// Initialize the Hash-based Tabs navigation Component
			cargohub.components.hashnav.events = {
				/**
				 * Tab initialization callback (used by the hashnav component)
				 * @param tab_id
				 */
				tab_init: init_Overview_Tab,

				/**
				 * Extra processing when a certain tab is selected via #hash (or at initialization)
				 * - This method is called before the tab is initalized, so values present here could determine how the tab is initialized/loaded
				 */
				new_hash: function(hash)
				{
					if(!hash)
						return;
					switch(hash[0]) {
					case 'send':
						if(!hash[1])
							return; // Nothing here, nothing to do

						switch(hash[1]) {
						// An external link has asked for the comm tab to be switched to "Transfer" mode (like pressing the Transfer Record button)
						case 'transfer':
							tab_select_params = {
								trigger: "setup-options",
								target: "partner",
								method: "email",
								parts: true
							};

							// Check if already loaded, in that case trigger the action immediately
							if(cargohub['record_send']) {
								cargohub['record_send'].selected(tab_select_params);
								tab_select_params = null;
							}
						}
						break;
					}
				},

				// Triggers acting on hash change
				triggers: {
					'email': function(trigger, hashnav) {
						switch(trigger) {
						case 'share':
							self.open_Modal_Transfer(); break;
						}
					},
					'tasks': function(trigger, hashnav) {
						switch(trigger) {
						case 'create':
							self.select_Tab_With_Params('tasks', {
								trigger: "task-create"
							});
						}
					}
				}
			};

			cargohub.components.hashnav.setup({
				use_tabs: true,
				use_triggers: true,
				tab0: self.config.tab0,
				tab_load_params: {
					"panels": record_load_config,
					"email": record_load_config,
					"comm": record_load_config,
					"history": record_load_config
				}//,
//				tabs_disabled: self.config.tabs_disabled
			}, self);

			// Setup the menu actions and operations bar. Everything else is set up with the tabs themselves, in their own module.
			setup_Menu_and_Operations();

			// Add tooltips to objects on the main page. Extra tooltips might be added inside tabs
			$(".info-tip, .jqx-menu-item").each(cargohub.components.ui.add_Tooltip);

			// Any "contact-item" anywhere in the module would allow viewing the contact information. For editing, there's specialized functions:

			$(document)
			/**
			 * Any "contact-item" anywhere in the module would allow viewing the contact information. For editing, there's specialized functions:
			 */
			.on("click", ".contact-item", function() {
/*
				var $con_popover = null, $con = $(this);

				// We have a valid contact id - Trigger a request to the server to grab information about the contact
				$.get("/global/contact?action=Visual_Card", {
					id: $(this).data('con_id'),
					template: 'vcard_mini'
				}, function(html) {
					// Remove existing one...
					if($con_popover) {
						$con_popover.jqxPopover('close');
						$con_popover.jqxPopover('destroy');
					}

					$con_popover = $(html);
					$con_popover.jqxPopover({
						theme: cargohub.jqxtheme,
						title: "Contact Details",
						selector: $con,
						showCloseButton: true,
						width: 548,
						position: 'bottom',
						offset: {
							left: 110,
							top: 0
						},
						arrowOffsetValue: -110
					});
					$con_popover.on('open', function() {
						// In case there is a jqxTooltip on the same element, hide it, it conflicts with the popover
						if($con.hasClass('info-tip'))
							$con.jqxTooltip('close');
					});
					$con_popover.jqxPopover('open');
				})
				// Contact not found or something weird
				.fail(function(xhr) {
					cargohub.alert(xhr.statusText, "Server response code: "+xhr.status, "stop.png");
				});
*/
// Popover seems to be bugged or behaves weird, revert to original method

				self.contact_Open_Details_Modal($(this).data('con_id'));
			});
		},

		/**
		 * Shows the modal window that loads contact details via a html ajax request.
		 */
		contact_Open_Details_Modal: function(contact_id)
		{
			if(!contact_id)
				return; // Do nothing if a contact has not been specified

			cargohub.components.modal.create('vcard', {
				buttons: {ok: "Close"}
			});

			cargohub.components.modal.open('vcard', {
				width: 600,	height: 450,
				title: "Viewing contact details",
				content: "<div id='contact-modal'></div>",
				callback: function($window) // Setup the controls inside the window
				{
					// Load the Vcard HTML via AJAX inside this window
					$("div#contact-modal", $window).load("/global/contact?action=visual_card&id="+contact_id);
				}
			});
		},

		/**
		 * Shows a self-disappearing short-text report on the tab toolbar (replaces the modal and saves a click; Reduces the "CargoClick" feeling)
		 */
		toolbar_Report: function(tab_selector, message)
		{
			// Begin by removing any existing report (to make room for new one)
			$(tab_selector+" div.toolbar div.toolbar-report").remove();
			// Finally, show the operation result
			$(tab_selector+" div.toolbar").append("<div class='toolbar-report toolbar-accessory'><span>"+ message + "<span></div>");
			$(tab_selector+" div.toolbar div.toolbar-report").fadeOut(6000, function() {
				$(this).remove();
			});
		},

		/**
		 * Selects a tab and injects select parameters into the action, to allow an operation to be done after the tab has been selected or finished loading
		 */
		select_Tab_With_Params: function(tab, params)
		{
			if(typeof(self.config.tab_positions[tab])=="undefined")
				return false; // Error, this tab doesn't exist.

			tab_select_params = params;
			cargohub.components.hashnav.select(self.config.tab_positions[tab]);

			// Check if already loaded, in that case trigger the action immediately
			if(cargohub["record_"+tab] && cargohub["record_"+tab].selected && typeof cargohub["record_"+tab].selected == 'function') {
				cargohub["record_"+tab].selected(tab_select_params);
				tab_select_params = null;
			}
		},

		/**
		 * Prepares a common "DataTable" control present on several tabs with similar functionality
		 * @param $container jQuery object that will be transformed into a jqxDataTable
		 * @param config - Configuration for the DataTable object itself (columns, renderer for row details, source, etc.)
		 * - Some parameters are added within the common function itself (like theme, width), but they can be overwritten from the config
		 * @param event_callbacks - An object containing function callbacks for internal jqxDataTable events
		 */
		init_DataTable: function($container, config, event_callbacks, tab_id)
		{
			$container.removeData('gotrows');

			// A few common defaults
			if(!config.theme)
				config.theme = cargohub.jqxtheme;
			if(!config.width)
				config.width = "100%";
			if(!config.selectionMode)
				config.selectionMode = "singleRow";
			if(!config.sortable)
				config.sortable = true;
			if(!config.filterable) {
				if(!cargohub.application.user_Is_Admin())
					config.filterMode = 'simple'; // Becomes "simple" for normal users but stays as "default" for admin. - Can be overwriten from the calling sub-module
				config.filterable = true;
			}

			// Initialize the DataTable and setup the Events
			$container.jqxDataTable(config)
			/**
			 * Event: Binding is complete, data is loaded.
			 * If the config contains a callback for data processing, it is called so content of the table can be exported.
			 */
			.on('bindingComplete', function(event) {
				if(event_callbacks['loaded'])
					event_callbacks['loaded'](event);

				donrey.single_Delay('datatable-reload-ops', function() {
					// Fix visual error in the "search" box
					$("#filter_record-"+tab_id+" > div:eq(0)", $container).css({"line-height": "29px", "margin-top": "", "top": ""});

					if(event_callbacks['getrows'] && !$container.data('gotrows')) {
						event_callbacks['getrows']($container.jqxDataTable('getRows'));
						$container.data('gotrows', true);
					}

					if($container.data('auto_reselect') && $container.data('selected_index'))
						$container.jqxDataTable('selectRow', $container.data('selected_index'));
					$container.removeData('auto_reselect');
				}, 50);
			})
			/**
			 * Event: A row is selected in this DataTable as elmeent data()
			 * (Also saves the selected index which might be used in the rowopen event)
			 */
			.on('rowSelect', function(event) {
				$container.data('selected_index', event.args.index);
				if(event_callbacks['rowselect'])
					event_callbacks['rowselect'](event.args.row, $container);
			})
			/**
			 * Event: A row in the DataTable is double-clicked
			 */
			.on('rowDoubleClick', function(event) {
				if(event_callbacks['rowopen'])
					event_callbacks['rowopen'](event.args.row, $container);
			})
			/**
			 * Event: The table is filtered - It should unselect any selected row
			 */
			.on('filter', function(event) {
				$container.jqxDataTable('clearSelection');
				$container.removeData('selected_index');
				if(event_callbacks['clearselect'])
					event_callbacks['clearselect']($container);
			});
		},

		/**
		 * Clears selection and filters
		 */
		clear_DataTable: function($container)
		{
			$container.jqxDataTable('clearSelection');
			$container.removeData('selected_index');
			$container.jqxDataTable('clearFilters');
			$container.removeData('gotrows');
		},

		/**
		 * Clears the active selection, any filters and refreshes the datatable content.
		 * @param $container This should already be a DataTable, or the method will fail
		 * @param do_not_clear When true, keeps selection and filters (special use case)
		 */
		refresh_DataTable: function($container, do_not_clear)
		{
			// When refreshing without clearing, it attempts to reselect the row which was selected before refreshing
			if(do_not_clear)
				$container.data("auto_reselect", true);
			else
				self.clear_DataTable($container);

			$container.jqxDataTable('updateBoundData');
			$container.jqxDataTable('refresh');
		},

		/**
		 * Loads the list of shared organizations in "simple mode"
		 * -- Used for the ability of selecting one or more of those organizations for items with visiblity "shared"
		 * @param onComplete_callback If used, this method is run when the loading has completed
		 */
		source_Sharing: function(onComplete_callback, autoBind)
		{
			var src_def = {
				url: '?action=get_sharing',
				datatype: "json",
				datafields: [
					{"name": 'id', type: 'int', map: 'org>id'},
					{"name": 'is_master', type: 'bool'},
					{"name": 'name', type: 'string', map: 'org>name'},
					{"name": 'type_id', type: 'string', map: 'org>type_id'},
					// Admin only field
					{"name": 'disabled', type: 'bool'}
				],
				id: "id"
			};

			if(!self.dataSources)
				self.dataSources = {};

			if(flag_sharing_loaded === true) {
				// When already loaded, return the dataAdapter directly
				if(onComplete_callback)
					onComplete_callback(self.dataSources.shareList.records);
				return self.dataSources.shareList.records;
			} else {
				// When not loaded, initialize the dataAdapter and return data when ready
				self.dataSources.shareList = new $.jqx.dataAdapter(src_def, {
					loadComplete: function()
					{
						flag_deps_loaded = true;
						if(onComplete_callback)
							onComplete_callback(self.dataSources.shareList.records);
					},
					autoBind: autoBind
				});
				flag_sharing_loaded = "loading";
				return self.dataSources.shareList;
			}
		},

		// List of enabled departments (also accessible from outside)
		active_DepsSource: new Array,

		/**
		 * Loads the department list associated with the currently selected organization (self.config.organization.id) on first use and returns it (or with a callback if loading is incomplete)
		 * -- Used by the department access selector and all user search function ( to select from one of the active departments first )
		 * @param onComplete_callback If used, this method is run when the loading has completed
		 */
		source_Deps: function(autoBind, onComplete_callback, onError_callback)
		{
			var src_def = {
				url: '/manager/department?action=get_list',
				data: {
					org_id: self.config.organization.id
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
				// When already loaded, return the dataAdapter records directly
				if(self.dataSources.depList.records.length > 0) {
					if(onComplete_callback && self.dataSources.depList)
						onComplete_callback(self.dataSources.depList.records);
					return self.dataSources.depList.records;
				} else {
					if(onError_callback)
						onError_callback();
					return new Array;
				}
			} else {
				// When not loaded, initialize the dataAdapter and return data when ready
				self.dataSources.depList = new $.jqx.dataAdapter(src_def, {
					loadComplete: function()
					{
						flag_deps_loaded = true;
						if(onComplete_callback && self.dataSources.depList)
							onComplete_callback(self.dataSources.depList.records);
					},
					loadError: function(jqXHR, status, error)
					{
						flag_deps_loaded = true;
						if(onError_callback)
							onError_callback(); // On error, send the callback an empty array; TODO: Save jqXHR, status, error from the failure, so it can be used on subsequent calls of source_Deps
					},
					autoBind: autoBind
				});
				flag_deps_loaded = "loading";
				return self.dataSources.depList;
			}
		},

		/**
		 * Build a secondary source for deparments containing only the enabled ones
		 */
		build_Active_DepsSource: function(records)
		{
			self.active_DepsSource.length = 0;

			// Begin by a "fake" department entry named "Entire organization"
			// NOT ok... causes problems for the other windows containing department list
//			self.active_DepsSource.push({
//				id: 0, uid: 0,
//				count_members: self.config.organization.count_users,
//				name: "Entire org.: "+self.config.organization.name
//			});

			if(self.config.dep_ids) {
				for(c=0; c<records.length; c++)
					if($.inArray(records[c].id.toString(), self.config.dep_ids) > -1)
						self.active_DepsSource.push(records[c]);
			}
		},

		/**
		 * Externally accessible method that opens the "Build E-mail" (Transfer) modal, containing options to construct the content and recipients of outgoing E-mail
		 */
		open_Modal_Transfer: function()
		{
			// Always start with the E-mail Contents zone
			transfer_zone = "contents";
			zone_complete = 1;
			checked_files = [];

			// Reopen existing window
			if(cargohub.components.modal.windows['transfer']) {
				// Reset the window to Step 1 in preparation for reopening
				return cargohub.components.modal.reopen('transfer', function($window) {
					// Zones: Begin by setting any buttons to default template and hiding all zones ...
					$("#transfer-navbar button", $window).jqxButton({template: "default"});
					$(".transfer-zone", $window).hide();
					$("#attach-helpers", $window).hide();
					$("#transfer-navbar button[data-zone='recipients']", $window).jqxButton({disabled: true});
					// ... and activate the "contents" one
					$("#transfer-navbar button[data-zone='contents']", $window).jqxButton({template: "success"});
					$("#transfer-"+transfer_zone).show();

					// Reset Contents controls:
					$("#transfer-email_template", $window).jqxDropDownList('selectIndex', 0); // Template
					$("#transfer-contacts_selector", $window).jqxListBox({disabled: false}); // Contacts
					$("#transfer-contacts_selector", $window).jqxListBox('uncheckAll');
					$("#transfer-contacts_selector", $window).jqxListBox("checkIndex", 0);
					$("#transfer-parts_selector", $window).jqxListBox({disabled: false}); // Parts
					$("#transfer-parts_selector", $window).jqxListBox('uncheckAll');
					$("#transfer-parts_selector", $window).jqxListBox("checkIndex", 0); // Enable Contacts option
					for(c=0; c<self.config.parts.length; c++)
						if(self.config.parts[c].send)
							$("#transfer-parts_selector", $window).jqxListBox("checkIndex", c+1);

					// Refresh the files every time the window is opened (as files might have changed)
					self.refresh_DataTable($("#transfer-attachments > div", $window));

					// OK button
					$('#modal_transfer_ok', $window).val("Next step > Attachments");
				});
			} else {
				cargohub.components.modal.create('transfer', {
					buttons: {cancel: "Cancel", ok: "Next step > Attachments"},
					btn_roles: {cancel: "cancel"},
					btn_styles: {cancel: "float: left; margin-left: 0; background-image: none; background-color: inherit !important; color: inherit !important;"}
				});

				cargohub.components.modal.open('transfer', {
					width: 1050, height: 480,
					title: "Build E-mail from "+ self.config.type.name+ ": "+ self.config.track,
					content: $('#modal-transfer').html().present({
						_itc: cargohub.input_theme_classes
					}),
					callback: setup_Transfer
				}, self);
			}
		},

		cb_Contacts_List_Selected: null,

		/**
		 * Opens the address book (global window)
		 * @param caller ID of the calling method. If not matching the existing one, the modal is reset and regenerated
		 * @param cb_selection Callback with parameters: 1) list of contacts as array of contact objects. 2) array of ID's in a compressed form ( for when full contact data is not needed)
		 */
		open_Modal_Contacts_List: function()
		{
			if(cargohub.components.modal.windows['contacts-list'])
				return cargohub.components.modal.reopen('contacts-list');

			// Open the contacts list in a large modal
			cargohub.components.modal.create('contacts-list', {
				btn_roles: {cancel: "cancel"}
			});

			cargohub.components.modal.open('contacts-list', {
				width: 1010, height: 630,
				title: "Address Book (Contacts)",
				callback: function($window) {
					var $container = $window.find('div.modal_content');

					// Initialize the Contacts_List component
					cargohub.components.contacts_list.events = {
						/**
						 * Doubleclicking on a contact will immediately close the addressbook and return the clicked row
						 */
						contacts_doubleclick: function(contact_id) {
							var contacts = [cargohub.components.contacts_list.get_Contact_Data(contact_id)], selected = [contact_id];

							if(typeof self.cb_Contacts_List_Selected === 'function')
								self.cb_Contacts_List_Selected(contacts, selected);
							$window.jqxWindow('close');
						}
					};

					cargohub.components.contacts_list.setup({
						container: $container,
						width: 998,
						// Pass through the configuration, so Contacts_List knows what to load for each label, for some special labels like
						// - Target Contacts, - Record-Linked, - Department and Organization ( Use the specified org_id, and not the user's org )
						// - Personal contacts however are user's only, and do not depend on selected organization or other parameters.
						tree: {
							org_id:  cargohub.application.user_Get_Session().org_id,
							user_id: cargohub.application.user_Get_Session().id,
							rec_id: self.config.id,
							rt_key: self.config.type.key,
							autoselect_idx: 0 // Already linked contacts are on top - Autoselect that entry.
						}
					}, self);
					$container.css({'overflow': 'hidden', 'border': 'none'});

					$('#modal_contacts-list_ok').off('click').on('click', function() {
						var selected = cargohub.components.contacts_list.get_Selected_Contact_IDs(), c= 0, contacts = [];
						if(selected.length < 1)
							return cargohub.alert("No contact is currently selected in the address book !");

						// Return
						for(c=0; c< selected.length; c++)
							contacts.push(cargohub.components.contacts_list.get_Contact_Data(selected[c]));

						if(typeof self.cb_Contacts_List_Selected === 'function')
							self.cb_Contacts_List_Selected(contacts, selected);
						$window.jqxWindow('close');
					});
				}
			});
		},

		// TinyMCE component with multiple instance support - Initialized on first use
		// (Currently used in Documents tab - and will be used in E-mail tab after converting from the non-instanced component. Might be used in other parts as well)
		rich_editor: null,
		/**
		 * Loads TinyMCEm, unless it's already in memory
		 */
		load_rich_editor: function(com_loaded_callback) {
			if(self.rich_editor) {
				if(com_loaded_callback)
					com_loaded_callback();
				return;
			}
			donrey.load_Components(["TinyMCEm"], function() {
				self.rich_editor = cargohub.components.TinyMCEm;
				self.rich_editor.setup(self);
				// Only initialized (and tinymce itself loaded) when first used
				if(com_loaded_callback)
					com_loaded_callback();
			}, "?v="+cargohub.version);
		},

		// DropzoneJS component, supports multiple instances - Initialized on first use
		// (For the moment only used in the new E-mail attachments; Will be used by Files uploads as well)
		file_uploader: null,
		/**
		 * Loads Dropzone uploader, unless it's already in memory
		 */
		load_file_uploader: function(com_loaded_callback) {
			if(self.file_uploader) {
				if(com_loaded_callback)
					com_loaded_callback();
				return;
			}

			donrey.load_Components(["Dropzone"], function() {
				self.file_uploader = cargohub.components.Dropzone;
				self.file_uploader.setup(self);
				// Only initialized (and Dropzone itself loaded) when first used
				if(com_loaded_callback)
					com_loaded_callback();
			}, "?v="+cargohub.version);
		}
	});

});

function createTaskWithSpecificTemplate(reqTypeFromFun){
	var reqConf;
	if(reqTypeFromFun == 'assignUser'){
		reqConf = reqTypeFromFun;
	}
	$.post("?action=get_Confirmation", {
		reqType: reqConf,
		urlData: window.location.href,
		oId: cargohub.application.user_Get_Session().org_id
	}, function(x) {
		console.log(x.response);		
	})
	.fail(cargohub.its.http_fail);
}


//IWI status popup menu design issue fix start

$(document).on("click", "#meta-overview .command button", function (event) {
    $(".meta-overview-popup-menu").fadeOut();
    var data = $(this).data();
    switch (data.menu) {

        case 'statuses':
            $(".statusGenPop").fadeIn();
            break;

        case 'priorities':
            $(".prioritiesGenPop").fadeIn();
            break;
        default:
            $(".meta-overview-popup-menu").fadeOut();
             break;

    }
});

 $(document).click(function(event) {
        if (!$(event.target).closest('.meta-overview-popup-menu').length && !$(event.target).is('#meta-overview .command button')) {
            $('.meta-overview-popup-menu').fadeOut();
        }
    });
	
//IWI status popup menu design issue fix end