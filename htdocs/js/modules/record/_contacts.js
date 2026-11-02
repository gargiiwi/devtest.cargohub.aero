/**
 * Module "Record Process", interface for the Adding/Editing and operating the attached Contacts
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules.Record Process
 * @version 2020.06.19
 */
cargohub.record_contacts = donrey.BASE._extend(function() {
	// private
	var self = this,
	c = null,
	master = cargohub.record_process,
	contact_editor = null,
	user_session = cargohub.application.user_Get_Session(),
	edit_privilege = cargohub.application.user_Test_Privileges('issue_edit_contacts'),

	selected_row = null,

	// Data from the table containing currently selected note. Used for View, Edit, Delete
	contacts_Source = new $.jqx.dataAdapter({
		url: '?action=get_contacts',
		data: {
			rec_id: master.config.id
		},
		datatype: "json",
		datafields: [
			// Link data
			{name: 'contact_id', type: 'int'},
			{name: 'role', type: 'string'},
			{name: 'label', type: 'string'},
			{name: 'visibility', type: 'string'},
			{name: 'user_id', type: 'int', map: 'contact>user_id'},		// User & Org who created this contact? - Not very useful without the name
			{name: 'org_id', type: 'int', map: 'contact>org_id'},
			{name: 'org_name', type: 'string', map: 'org>name'},		// When it exists (Admin view)
			{name: 'org_type', type: 'string', map: 'org>type_id'},
			// Contact data
			{name: 'ctime', type: 'date', map: 'contact>ctime'},
			{name: 'email', type: 'string', map: 'contact>email'},
			{name: 'name', type: 'string', map: 'contact>name'}
		]
	}),

	/**
	 * A contact row has been selected - Store information in preparation for a double-click ( for Viewing or Editing ), or for clicking on one of the toolbar buttons
	 */
	select_Contact = function(row)
	{
		$('#tab_contacts #contacts-row-ops').hide();
		$('#tab_contacts #contacts-row-editops').hide();

		donrey.cancel_Delay('hide-bar_contact-select');
		if(row)
			selected_row = row;

		if(cargohub.application.user_Is_Admin() || (edit_privilege && user_session.id == selected_row.user_id)) {
			$('#tab_contacts #contacts-row-editops button').jqxButton({disabled: false});
			$('#tab_contacts #contacts-row-editops').fadeIn();
		} else {
			$('#tab_contacts #contacts-row-ops button').jqxButton({disabled: false});
			$('#tab_contacts #contacts-row-ops').fadeIn();
		}

		if(row.role == 'submitter')
			$('#tab_contacts #contacts-row-editops button[data-op="contact-setsubmitter"]').hide();
		else
			$('#tab_contacts #contacts-row-editops button[data-op="contact-setsubmitter"]').show();
	},

	/**
	 * No rows are currently selected - Make the toolbar buttons dissapear
	 */
	select_Clear = function()
	{
		selected_row = null;
		$('#tab_contacts #contacts-row-editops button').jqxButton({disabled: true});

		donrey.single_Delay('hide-bar_contact-select', function() {
			if(edit_privilege)
				$('#tab_contacts #contacts-row-editops').fadeOut();
			else
				$('#tab_contacts #contacts-row-ops').fadeOut();
		}, 500);
	},

	/**
	 * Opens a window allowing to choose from an existing contact from the Address Book (This is actually the Address Book in a window)
	 */
	link_Contact = function()
	{
		// Change callback variable before opening the window.
		master.cb_Contacts_List_Selected = function(contacts, contact_ids) {
			$.post("?action=Link_Contacts", {
				ids: contact_ids.join(',')
			}, function(x) {
				master.refresh_DataTable($("#tab_contacts div.jqx-datatable"));
				// Update counter on deletion (should be reduced by one compared to current if deleting from the perspective organization)
				if(x.update_count)
					cargohub.components.hashnav.update_Tab_Header(7, "Contacts"+(x.count? " <span class='fiftycent'>["+x.count+"]</span>": ""), "/img/icons16/vcard.png");
				cargohub.alert(x.response, "Success !", "thumb_up.png");
			})
			.fail(cargohub.its.http_fail);
		};
		master.open_Modal_Contacts_List();
	},

	/**
	 * Sets up the window that contains the contact viewer/editor
	 * -- The actual contact editing is done through the cargohub.editor_contact module
	 */
	setup_Contact_Editor = function($window, config)
	{
		cargohub.application.reset_Session_Timer();
		var request_params = null, status = null;

		// The label edit field
		if(config.label)
			$("#contact-label-input", $window).addClass("jqx-input-"+cargohub.jqxtheme).val(config.label);

		// Load the contact information
		// -- (Same "VCard" as used everywhere where contact information is shown)
		// -- The difference is that editing abilities are added onto the HTML
		if(config.mode == "edit")
			// Edit mode, the vCard loads an existing contact, which might or might not be editable by the user (the check is made before even showing the window), using the data returned by the contact search system
			request_params = {
				id: selected_row.contact_id,
				edit: 1
			};
		else {
			// Creation mode, the vCard is loaded without an ID, then the Name/Email (depending on which ones are present) are set on the content
			request_params = {
				create: 1
			};
			// Name/Email identified in the input value
			// request_params.init_contact = ??? Not used here
		}
		$.get("/global/contact?action=visual_card", request_params, function(vcard, xhrstatus)
		{
			$("#contact-modal", $window).html(vcard);

			self.contact_editor.events = {
				changed: function(valid) {
					if(valid) {
						// Enable window OK button (maybe later)
					} else {
						// Disable window OK button
					}
				}
			};

			self.contact_editor.setup({
				container: $("#contact-modal", $window),
				mandatory_fields: []
			}, self);

		});

		// Setup the action for "OK" button
		$('#modal_contact-editor_ok', $window).off('click').on('click', function()
		{
			var contact = null, $loaded = $("#vcard-loaded-id", $window);

			// When contact window has detected a match and loaded an existing contact, update the line and contact ID
			// If edit is enabled, saving will happen on the loaded contact
			if($loaded.length) {
				contact = self.contact_editor.read_vCard();
				contact.id = parseInt($loaded.val());
			}

			// Check if valid, changed and save
			status = self.contact_editor.check_Status();
			if(status.valid) {
				// If changed, read the content and save
				if(status.changed) {
					// If changed, update the contact line again
					contact = self.contact_editor.read_vCard();
					if(selected_row && selected_row.contact_id)
						contact.id = selected_row.contact_id; // Maintain existing ID ... The editor itself doesn't care.
					contact.type = "draft";

					// Save the contact changes to the database (or, create the contact draft if it didn't previously exist)
					$.post("/global/contact?action=save_contact", {
						id: contact.id, // Might be missing (null), in this case the contact is actually created
						name: contact.name,
						email: contact.email,
						xdata: JSON.stringify(contact.xdata),
						type: contact.type,
						stealth: true	// Do not add to user's recently used contacts
					}, function(x) {
						// On success, x.id should contain the real ID
						if(x.id)
							contact.id = x.id;
						// Sometimes, the server sends different name (when detecting existing contact for the specified e-mail, and under a different name)
						if(x.name)
							contact.name = x.name;
						// Type is also appended, in case an existing contact is located
						if(x.type)
							contact.type = x.type;

						// With the contact created or detected, link the ID to the record
						if(config.mode == "create") {
							$.post("?action=Link_Contacts", {
								ids: contact.id,
								label: $("#contact-label-input", $window).val(),
								type: contact.type
							}, function(y) {
								master.refresh_DataTable($("#tab_contacts div.jqx-datatable"));
								// Update counter on deletion (should be reduced by one compared to current if deleting from the perspective organization)
								if(y.update_count)
									cargohub.components.hashnav.update_Tab_Header(7, "Contacts"+(y.count? " <span class='fiftycent'>["+y.count+"]</span>": ""), "/img/icons16/vcard.png");
							})
							.fail(cargohub.its.http_fail);
						} else {
							// In edit mode, there's nothing to link (Existing contact is simply edited/changed)
							master.refresh_DataTable($("#tab_contacts div.jqx-datatable"));
						}
						cargohub.alert(x.response, "Success !", "thumb_up.png");
						$window.jqxWindow('close');
					})
					.fail(cargohub.its.http_fail);
				} else {
					// TODO: Here, check if the contact editor was preinitialized from name/email in the search box, in which case the contact needs to be saved anyway
//					cargohub.alert("Contact valid but not changed");

					$window.jqxWindow('close');
				}
			} else
				cargohub.alert("Some fields are invalid.<br />Please fix any empty <span style='background-color: #ffffa0;'>mandatory fields</span> or fields <span style='color: red; text-decoration: line-through;'>with errors</span>", null, null, 420, 150);
		});
	},

	/**
	 * Opens a view window for the currently selected contact (Read-Only VCard template). Valid for anyone that has access to contacts
	 */
	view_Contact = function(row)
	{
		if(row)
			selected_row = row;

		master.contact_Open_Details_Modal(selected_row.contact_id);
	},

	/**
	 * Setup the contacts position window
	 */
	setup_Contacts_Position = function($window, config)
	{
		var
		// Source for the template selector in document create
		labels_Source = new $.jqx.dataAdapter({
			datatype: "json",
			datafields: [
				{name: 'id', type: 'int'},
				{name: 'name', type: 'string'},
				{name: 'type', type: 'string'},
				{name: 'color', type: 'string'}
			],
			id: "id"
		}),
		// Label selector dropDownList
		$label_selector = $("#contacts-label-select", $window),
		enable_Label_Selector = function(url, data, placeholder) {
			labels_Source._source.url = url;
			labels_Source._options.data = data;
			$label_selector.jqxDropDownList('clearSelection');
			$label_selector.jqxDropDownList({
				source: labels_Source,
				placeHolder: placeholder||"(Loading labels)"
			});
		},
		levels = ['personal', 'department', 'organization'],
		selected_level = null;

		$("#contacts-level", $window).jqxButtonGroup({
			theme: cargohub.jqxtheme,
			mode: "radio",
			enableHover: true
		})
		.on('selected', function(event) {
			selected_level = levels[event.args.index];
			enable_Label_Selector('?action=Get_Contact_Labels', {
				level: selected_level
			});
		});

		$label_selector.jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: labels_Source,
			placeHolder: "(Select address book level first)",
			displayMember: "name", valueMember: "id",
			renderer: function (index, label, value) {
				var color = labels_Source.records[index].color;
				if(color)
					color = 'tag_'+color;
				else
					color = selected_level;

				return "<div><img style='float: left; margin-right: 4px;' src='/img/icons16/commlabel/"+ color+ ".png' />" + label + "</div>";
			},
			width: 515,	height: 24, autoDropDownHeight: true,
			animationType: "fade", enableBrowserBoundsDetection: true,
			disabled: true
		})
		// Event: The list of labels is loaded (or has an error)
		.on('bindingComplete', function(event) {
			if(labels_Source.records.length == 0) {
				$(this).jqxDropDownList({
					placeHolder: "(There are no labels for the selected level)",
					disabled: true
				});
			} else {
				$(this).jqxDropDownList({
					dropDownHeight: labels_Source.records.length> 8? 210: null,
					autoDropDownHeight: labels_Source.records.length<= 8? true: false,
					placeHolder: "(Choose the label - Optional)",
					disabled: false
				});
			}
		});

		// Setup the action for "OK" button
		$('#modal_contacts-position_ok', $window).off('click').on('click', function()
		{
			if(!selected_level)
				return cargohub.alert("You must choose an address book level");

			$.post("?action=Copy_Con2Contacts", {
				con_id: selected_row.contact_id,
				level: selected_level,
				label_id: $label_selector.val()
			}, function(x) {
				cargohub.alert(x.response, "Success !", "thumb_up.png");
				$window.jqxWindow('close');
			})
			.fail(cargohub.its.http_fail);
		});
	},

	/**
	 * Opens a modal to ask where in the address book will this contact be copied.
	 * - User level -> display labels in the 2nd control
	 * - Dep level -> on user's department, if there are labels, show them in the 2nd control
	 * - Org level -> show labels if they exist
	 * (For any level, if there are no labels, 2nd control should say "No labels defined" and be disabled
	 */
	save_Contact = function(row)
	{
		if(row)
			selected_row = row;

		cargohub.components.modal.forget['contacts-position'];
		// The window contains two controls and a bit of text
		cargohub.components.modal.create('contacts-position', {
			btn_roles: {cancel: "cancel"}
		});

		var data = {
			row: selected_row
		};

		// TODO: Check user access rights for address book, to determine which of the buttons are enabled

		cargohub.components.modal.open('contacts-position', {
			width: 543,	height: 350,
			title: "<img src='/img/icons16/book_addresses.png' style='top: 0;' /> Create a copy of the selected contact into the address book",
			content: $("script#tpl-contacts_position").html().present(data),
			callback: setup_Contacts_Position
		}, self);
	},

	/**
	 * Opens a window used to configure visibility for this contact
	 */
	config_Contact = function(row)
	{
		if(row)
			selected_row = row;

		cargohub.alert("Patience, my young padawan, you must have!");
	},

	/**
	 * Detaches the contact with an Ajax call. Refreshes the DataTable on success
	 * -- The contact is not actually removed ! It can be reattached later
	 */
	unlink_Contact = function(row)
	{
		if(row)
			selected_row = row;

		$.post("?action=Unlink_Contact", {
			id: selected_row.contact_id
		}, function(x) {
			master.refresh_DataTable($("#tab_contacts div.jqx-datatable"));
			// Update counter on deletion (should be reduced by one compared to current if deleting from the perspective organization)
			if(x.update_count)
				cargohub.components.hashnav.update_Tab_Header(7, "Contacts"+(x.count? " <span class='fiftycent'>["+x.count+"]</span>": ""), "/img/icons16/vcard.png");
			cargohub.alert(x.response, "Success !", "thumb_up.png");
		})
		.fail(cargohub.its.http_fail);
	},

	/**
	 * Sets the selected contact as the record's submitter. Original contact gets the role "custom" (for now)
	 */
	set_Submitter = function(row)
	{
		if(row)
			selected_row = row;

		$.post("?action=Set_Submitter", {
			id: selected_row.contact_id
		}, function(x) {
			master.refresh_DataTable($("#tab_contacts div.jqx-datatable"));
			cargohub.alert(x.response, "Success !", "thumb_up.png");
		})
		.fail(cargohub.its.http_fail);
	},

	/**
	 * Initialize the Contacts tab toolbar (Link, View, Edit, Unlink contacts)
	 */
	init_Toolbar = function()
	{
//		var tpl_data = {_itc: cargohub.input_theme_classes};

		$("#tab_contacts .toolbar button")
		.jqxButton({ theme: cargohub.jqxtheme })
		/**
		 * Event: Toolbar button is clicked
		 */
		.on('click', function()
		{
			switch($(this).data('op')) {
			case "contact-link":
				link_Contact();
				break;
			case "contact-add":
				// Regenerates the window every time it's opened
				cargohub.components.modal.forget['contact-editor'];
				cargohub.components.modal.create('contact-editor', {
					buttons: {
						ok: "Ok"
					}
				});

				cargohub.components.modal.open('contact-editor', {
					width: 694,	height: 540,
					title: "<img src='/img/icons16/vcard.png' style='top: 0;' /> New record-linked contact",
					content: $("script#tpl-contact_editor").html().present(),
					callback: setup_Contact_Editor,
					cb_config: {
						mode: "create",
						label: "Manually added"
					}
				}, self);
				break;
			case "contact-edit":
				// Regenerates the window every time it's opened
				cargohub.components.modal.forget['contact-editor'];
				cargohub.components.modal.create('contact-editor', {
					buttons: {
						ok: "Ok"
					}
				});

				cargohub.components.modal.open('contact-editor', {
					width: 694,	height: 540,
					title: "<img src='/img/icons16/vcard_edit.png' style='top: 0;' /> Edit contact",
					content: $("script#tpl-contact_editor").html().present(selected_row),
					callback: setup_Contact_Editor,
					cb_config: {
						mode: "edit"
					}
				}, self);
				break;

			case "contact-view":
				view_Contact();
				break;
			// Copies a record-linked contact into the user's address book ( At either User level, Department level or Organization level, depending on access rights )
			case "contact-save":
				save_Contact();
				break;

			case "contact-config":
				config_Contact();
				break;

			case "contact-unlink":
				if(!confirm("Are you sure you want to detach this contact ?"))
					return;
				unlink_Contact();
				break;

			// Master Handler function - Allows setting another linked contact as the submitter of the record
			case "contact-setsubmitter":
				if(!confirm("Are you sure you to set this contact as the submitter ?"))
					return;
				set_Submitter();
				break;
			}
		});

		if(edit_privilege)
			$('#tab_contacts #contacts-editops').fadeIn();
		// If record belongs to the organization of logged in user, "superops" is also visible
		if(cargohub.application.user_Is_Admin() || master.config.master_org_id == user_session.org_id)
			$('#tab_contacts .contacts-superops').show();
	},

	/**
	 * Initialize the Contacts DataTable. (This uses common functionality from the main Record Process module - cargohub.record_process )
	 */
	init_DataTable = function()
	{
		cargohub.record_process.init_DataTable($("#tab_contacts div.jqx-datatable"), {
			// Config
			source: contacts_Source,
			columns: [
				{dataField: 'visibility', text: '<span class="access16 visi-public"></span>', width: 25, filterable: false,
					cellsRenderer: function(row, column, value, rowData) {
						return '<span class="access16 visi-'+rowData.visibility+'"></span>';
					}
				},
				{dataField: 'label', text: "Label", width: 150},
				{dataField: 'name', text: "Name"},
				{dataField: 'email', text: "E-mail"},
				{dataField: 'org_name', text: "Organization", width: 160, hidden: !cargohub.application.user_Is_Admin(), filterable: cargohub.application.user_Is_Admin()}
			]
		}, {
			// Callbacks (for events happening in the datatable itself) - In most cases it will be either View or Edit
			"getrows": function() { /* Nothing here atm */ },
			"rowselect": select_Contact,
			"rowopen": view_Contact,
			"clearselect": select_Clear
		}, "contacts");
	}
	;

	this._public({
		/**
		 * Setup Contacts Tab for Record Process module
		 */
		init: function()
		{
			// This uses contact editor component to create or edit linked contacts
			donrey.load_Components(["contact_editor"], function() {
				self.contact_editor = cargohub.components.contact_editor;
				self.contact_editor.fields_structure = self.config.fields_structure;
				// Only initialized (and tinymce itself loaded) when first used
			}, "?v="+cargohub.version);

			init_Toolbar();
			init_DataTable();

			// Tooltips inside the Contacts tab
			$("#tab_contacts .info-tip").each(cargohub.components.ui.add_Tooltip);
		},

		/**
		 * External call to reload the contacts table (following e-mail sending and assignment change)
		 */
		reload: function()
		{
			master.refresh_DataTable($("#tab_contacts div.jqx-datatable"));
		}
	});

});