/**
 * Module "Contacts Manager" (Address Book) - For an user (logged in or specified by an admin/supervisor/user manager)
 * Depends on the widgets:
 * - cargohub.components.hashnav (Tab based navigation)
 * - cargohub.components.contacts_list (Contacts list/search) - Tree+Grid
 * - cargohub.components.vcard (Contact editor)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2.10
 */
cargohub.contact_manager = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,
		visible_toolbar_items = {},	// List of toolbar sub-items (2nd part of the id, after ops-) which are currently enabled/visible
		toolbar_prefix = "ops-",
		loaded_contact_id = null,
		label_contacts_editable = false, // Indicates if the contacts in the selected label should be open with the Editor (2nd tab) or simply viewed

	/**
	 * Shows a toolbar item and enables any buttons present on it
	 * The item might contain other sub-items (which are either hidden or visible). This function only affects main item and it's buttons
	 * TODO: Since toolbars are used all over the application, these kind of functionality might be moved in the main script so it can be used in every module?
	 */
	enable_Toolbar_Item = function(item_name, prefix)
	{
		if(!prefix)
			prefix = toolbar_prefix;

		// Checks if the item is already enabled, in this case nothing to do
		if(visible_toolbar_items[item_name])
			return;

		// First, enable the buttons
		$('div.toolbar #'+prefix+item_name+' > button').each(function() {$(this).jqxButton({disabled: false}).jqxButton('render');}); // Redraws the button, just in case...
		// ... and immediatly fade in the bar
		$('div.toolbar #'+prefix+item_name).fadeIn();

		// Also, clears the "hiding" delay in case it was triggered just before asking to enable the bar
		donrey.cancel_Delay('hide_bar_'+item_name);
		visible_toolbar_items[item_name] = true;
	},

	/**
	 * Disables and a bit later hides a toolbar item and buttons present on it
	 * The item might contain other sub-items (which are either hidden or visible). This function only affects main item and it's buttons
	 * TODO: Since toolbars are used all over the application, these kind of functionality might be moved in the main script so it can be used in every module?
	 */
	disable_Toolbar_Item = function(item_name, prefix, instant)
	{
		if(!prefix)
			prefix = toolbar_prefix;

		// Checks if the item is already disabled, in which case there's nothing to do
		if(!visible_toolbar_items[item_name])
			return;

		// First, disable the buttons
		$('div.toolbar #'+prefix+item_name+' > button').each(function() {$(this).jqxButton({disabled: true}).jqxButton('render');}); // Redraws the button, just in case...
		// ... then prepare a timer for fading out the item itself

		if(instant) {
			$('div.toolbar #'+prefix+item_name).hide();
		} else
			donrey.single_Delay('hide_bar_'+item_name, function() {
				$('div.toolbar #'+prefix+item_name).fadeOut();
			}, 500);

		delete visible_toolbar_items[item_name];
	},

	/**
	 * Prepares the 2nd tab for loading a contact (or creating one).
	 * -- This action is done after the "add" command, details, or doubleclicking in the list
	 */
	load_Contact = function(load_params)
	{
		// TODO: If a contact is already loaded, warn about unloading it if it was changed (* check if it was changed)
		// Unload so new one can be loaded
		cargohub.components.hashnav.unload_Tab(1, 'vcard');
		loaded_contact_id = null;
		self.config.contact = null;

		// If creating, disable vcard toolbars
		if(load_params.create) {
			disable_Toolbar_Item('vcard_existing');
		}
		if(cargohub.application.user_Is_Admin())
			enable_Toolbar_Item('issue_target'); // This is too dangerous atm to give to users

		cargohub.components.hashnav.set_load_params('vcard', load_params);
		cargohub.components.hashnav.enable_At(1);
		cargohub.components.hashnav.select(1);
	},

	/**
	 * Shows the modal window that loads contact details via a html ajax request - Readonly mode
	 */
	view_Contact = function(contact_id)
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
	 * Setup the window used to choose for which issue type and department this contact can be used as "issue target"
	 */
	setup_Modal_Issue_Target = function($window)
	{
		// Begin by disabling the OK button. It will be enabled once both inputs have been selected
		$("#modal_issue-target_ok", $window).jqxButton({disabled: true});

		// The dataAdapter reads the available active issue types for this organization, which are allowed to receive data
		var ist_Source = new $.jqx.dataAdapter({
			url: "?action=get_org_issue_types",
			data: {
				org_id: self.config.org_id
			},
			datatype: "json",
			datafields: [
				{name: "id", type: "int"},
				{name: "name"},
				{name: "key"}
			],
			id: "id"
		}),
		// This dataAdapter reads the departments from an organization which are listed as incoming for receiving issues (listed = 1)
		dep_Source = new $.jqx.dataAdapter({
			url: "?action=get_org_departments",
			data: {
				org_id: self.config.org_id
			},
			datatype: "json",
			datafields: [
				{name: "id", type: "int"},
				{name: "name"}
			],
			id: "id"
		}),
		is_selected = {ist: false, dep: false}, check_enable_ok = function(dropdown) {
			is_selected[dropdown] = true;
			if(is_selected.ist && is_selected.dep)
				$("#modal_issue-target_ok").jqxButton({disabled: false}).jqxButton('render');
		};

		// The record type selector
		$("#istarget-istselect", $window).jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: ist_Source,
			displayMember: "name", valueMember: "key",
			width: 250, height: 24,
			placeHolder: "(Reading issue types)",
			disabled: true
		})
		/**
		 * Event: The list of partners is ready, show the control
		 */
		.on('bindingComplete', function() {
			$(this).jqxDropDownList({
				placeHolder: "Please select type:",
				dropDownHeight: ist_Source.records.length> 7? 184: null,
				autoDropDownHeight: ist_Source.records.length<= 7? true: false,
				disabled: false
			});

			// If there is only one issue type, select it directly !
			if(ist_Source.records.length == 1)
				$(this).jqxDropDownList('selectIndex', 0);
		})
		/**
		 * Event: The partner organization is selected
		 */
		.on("select", function(event) {
			check_enable_ok('ist');
		});

		// The partner organization department selector
		$("#istarget-depselect", $window).jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: dep_Source,
			displayMember: "name", valueMember: "id",
			width: 250, height: 24,
			placeHolder: "(Reading departments)",
			disabled: true
		})
		/**
		 * Event: The list of partners is ready, show the control
		 */
		.on('bindingComplete', function() {
			if(dep_Source.records.length == 0) {
				$(this).jqxDropDownList({
					placeHolder: "(No listed departments)"
				});
				// This organization has no departments, the contact will be associated with the organization itself (dep_id = null)
				check_enable_ok('dep');
				return;
			}

			$(this).jqxDropDownList({
				placeHolder: "Select deparment:",
				dropDownHeight: dep_Source.records.length> 7? 184: null,
				autoDropDownHeight: dep_Source.records.length<= 7? true: false,
				disabled: false
			});

			// If there is only one department, select it directly
			if(dep_Source.records.length == 1)
				$(this).jqxDropDownList('selectIndex', 0);
		})
		/**
		 * Event: The partner organization is selected
		 */
		.on("select", function(event) {
			check_enable_ok('dep');
		});

		// Finally, the OK button will submit the form
		$('#modal_issue-target_ok').off('click').on('click', function() {
			// submit the add/edit form using ajax
			$('form', $window).ajaxSubmit({
				data: {
					org_id: self.config.org_id,
					contact_id: loaded_contact_id
				},
				dataType: 'json',
				success: function(x) {
					if(x.success) {
						// The contact was set as issue target. Close the window, reset the VCard tab and reload it
						$window.jqxWindow('close');
						cargohub.alert(x.response, "Success !", "thumb_up.png");

						cargohub.components.hashnav.reload_Tab(1, 'vcard');
					}
				},
				error: cargohub.its.http_fail
			});
		});
	},

	/**
	 * Label editor window, used for both create and update
	 */
	setup_Modal_Label_Editor = function($window, cfg)
	{
		var submit_data = {
			mode: cfg.mode,
			// These are not always used - Only for someone editing someone else's address book ( Admin/Supervisor feature )
			user_id: self.config.user_id,
			org_id: self.config.org_id
		}, colors_source = [
			{val: "blue", text: "Blue"},
			{val: "green", text: "Green"},
			{val: "orange", text: "Orange"},
			{val: "pink", text: "Pink"},
			{val: "purple", text: "Purple"},
			{val: "red", text: "Red"},
			{val: "yellow", text: "Yellow"},
			{val: "black", text: "Black"}
		];

		$("#lbl_color", $window).jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: colors_source,
			displayMember: "text", valueMember: "val",
			width: 230, height: 24, dropDownHeight: 204,
			renderer: function (index, label, value) {
				return "<img src='/img/icons16/commlabel/tag_"+label+".png' class='inline-icon' style='margin: 0 6px;' />" + label;
			},
			selectionRenderer: function (htmlString) {
				var item = $("#lbl_color", $window).jqxDropDownList('getSelectedItem');
				if (item)
					return "<div style='margin-top: 4px;'><img src='/img/icons16/commlabel/tag_"+item.value+".png' class='inline-icon' style='margin: 0 6px 0 8px;' />"+ item.label+ "</div>";
				return "<div style='margin-top: 4px;'>(Select color)</div>";
			},
		});

		// Initialization
		if(cfg.mode == "update") {
			submit_data.label_id = cfg.label_id;

			$("#lbl_name", $window).val(cfg.label_data.name);

			// Find and select existing color
			for(c=0; c<colors_source.length; c++)
				if(cfg.label_data.color == colors_source[c].val)
					$("#lbl_color", $window).jqxDropDownList('selectIndex', c);
		} else {
			if(cfg.container_data.type!='personal' && cfg.container_id.substr(0,3) == "lab")
				submit_data.container_id = cfg.container_data.parent;
			else
				submit_data.container_id = cfg.container_id;
			submit_data.type = cfg.container_data.type;
		}

		// Finally, the OK button will submit the form
		$('#modal_label-editor_ok').off('click').on('click', function() {
			// submit the add/edit form using ajax
			$('form', $window).ajaxSubmit({
				data: submit_data,
				dataType: 'json',
				beforeSubmit: function(formData, jqForm, options)
				{
					if($("#lbl_name", $window).val().length < 3)
						return cargohub.alert("The label must not be shorter than three characters");
					return true;
				},
				success: function(x) {
					var color = $("#lbl_color", $window).val();

					if(cfg.mode == "update") {
						// On label rename, just update the selected item
						if(color)
							cargohub.components.contacts_list.update_Current_Label($("#lbl_name", $window).val(), 'tag_'+color, color);
						else
							cargohub.components.contacts_list.update_Current_Label($("#lbl_name", $window).val(), cfg.label_data.type);
						cargohub.components.contacts_list.refresh();
						$window.jqxWindow('close');

						cargohub.alert(x.response, "Success !", "thumb_up.png");
					} else {
						// On label create, module must be reloaded
						document.location.reload();
					}
				},
				error: cargohub.its.http_fail
			});
		});
	},

	/**
	 * Label selector window, used to choose one of the available labels for all the selected contacts
	 */
	setup_Modal_Label_Select = function($window, cfg)
	{
		// Begin by disabling the OK button. It will be enabled once both inputs have been selected
		$("#modal_label-select_ok", $window).jqxButton({disabled: true});

		var label_Source = new $.jqx.dataAdapter({
			url: "?action=Get_Labels",
			data: {
				user_id: self.config.user_id,
				org_id: self.config.org_id,
				selector: cfg.selector,
				selector_id: cfg.selector_id
			},
			datatype: "json",
			datafields: [
				{name: "id", type: "int"},
				{name: "name"},
				{name: "color"},
				{name: "count_el", type: "int"}
			],
			id: "id"
		});


		$("#label-select", $window).jqxListBox({
			theme: cargohub.jqxtheme,
			source: label_Source,
			displayMember: "name", valueMember: "id",
			width: 375, height: 204,
			renderer: function (index, label, value) {
				return "<img src='/img/icons16/commlabel/tag_"+label_Source.records[index].color+".png' class='inline-icon' style='margin: 0 6px;' />" + label;
			}
		})
		/**
		 * Event: A label is selected, enable the OK button
		 */
		.on('select', function() {
			$("#modal_label-select_ok", $window).jqxButton({disabled: false});
		});

		// The OK button will submit the Set Label request
		$('#modal_label-select_ok', $window).off('click').on('click', function() {
			$.post("/contact/operations?action=Set_Contacts_Label", {
				ids: cargohub.components.contacts_list.get_Selected_Contact_IDs(),
				label_id: $("#label-select", $window).val(),
				user_id: self.config.user_id, // Might be empty for org, dep level
				org_id: self.config.org_id
			}, function(x) {
				// On success, the contact labels have been updated - Nothing to do (other than confirm the operation and close the window)
				cargohub.alert(x.response, "Success !", "thumb_up.png");
				$window.jqxWindow('close');
			})
			.fail(cargohub.its.http_fail);
		});
	},

	/**
	 * Setup the global toolbar (which shows and hides opbars depending on the active tab and other various events)
	 * - Also sets up the actions which happen when buttons in the toolbar are clicked.
	 */
	setup_Toolbar = function()
	{
		var tpl_data = {_itc: cargohub.input_theme_classes};

		// setup toolbar actions
		$("div.toolbar button")
		.jqxButton({ theme: cargohub.jqxtheme })
		.on('click', function()	{
			var data = $(this).data();
			switch(data.op) {
			// Refreshes the contact datatable for the already selected label
			case 'refresh':
				cargohub.components.contacts_list.refresh();
				break;
			// Activates the contact tab and initializes it in create mode
			case 'vcard-add':
				load_Contact({
					label: cargohub.components.contacts_list.get_Current_Label(),
					user_id: self.config.user_id, // Might be empty for org, dep level
					org_id: self.config.org_id,
					create: true
				});
				break;
			// Opens an existing contact into view/edit mode (depending on rights) - Only when one single contact is selected
			case 'details':
				if(label_contacts_editable) {
					// Not all contacts are editable - Depending on the selected label, the "editable" flag is set (or not)
					load_Contact({
						contact_id: cargohub.components.contacts_list.get_Selected_Contact_IDs()[0],
						user_id: self.config.user_id, // Might be empty for org, dep level
						org_id: self.config.org_id,
						edit: true
					});
				} else {
					view_Contact(cargohub.components.contacts_list.get_Selected_Contact_IDs()[0]);
				}
				break;
			// Assigns a label to the selected contact(s) - For one or multiple contacts
			case 'set-label':

				var lab_data = cargohub.components.contacts_list.get_Curent_Label_Data(), selector = cargohub.components.contacts_list.get_Current_Label(), selector_id = null;
				if(lab_data.parent)
					selector = lab_data.parent;

				if(selector.substr(0,5) == 'user_')
					selector = "usr";
				else if(selector.substr(0,4) == 'dep_') {
					selector_id = parseInt(selector.substr(4));
					selector = "dep";
				} else if(selector.substr(0,4) == 'org_')
					selector = "org";
				else
					return cargohub.alert("Unable to set label on this kind of contact");

				// Open the label choice window (which receives one parameter - The "selector", which can be "user_*", "dep_*" or "org_ALL"
				// For User or Org, the same list of labels is shown in all cases - For Dep, the number is important, since the label lists are independent for each department

				cargohub.components.modal.create('label-select', {
					btn_roles: {cancel: "cancel"}
				});

				cargohub.components.modal.open('label-select', {
					width: 400, height: 340,
					title: "Assign label to contacts",
					content: $('#modal-label_select').html().present(),
					callback: setup_Modal_Label_Select,
					cb_config: {
						mode: "create",
						selector: selector,
						selector_id: selector_id
					}
				}, self);
				break;

			// Deletes the selected contact(s) - For one or multiple contacts
			case 'delete':
				var selection = cargohub.components.contacts_list.get_Selected_Contact_IDs();
				if(selection.length < 1)
					return cargohub.alert("No contacts are selected");
				else if(selection.length == 1) {
					if(!confirm("Are you sure you want to delete this contact?"))
						return;
				} else {
					if(!confirm("Are you sure you want to delete these "+selection.length+" contacts?"))
						return;
				}

				$.post("/contact/operations?action=delete_multiple", {
					ids: cargohub.components.contacts_list.get_Selected_Contact_IDs(),
					user_id: self.config.user_id, // Might be empty for org, dep level
					org_id: self.config.org_id
				}, function(x) {
					// On success, it means the contact is deleted the grid should be refreshed and the tab should be closed
					if(x.success) {
						cargohub.components.contacts_list.refresh();
						$("div.toolbar button#vcard-close").trigger('click');
						cargohub.alert(x.response, "Success !", "thumb_up.png");
					}
				})
				.fail(cargohub.its.http_fail);
				break;

			// Label management operations
			case 'label-create':
				var container_data = cargohub.components.contacts_list.get_Curent_Label_Data();

				cargohub.components.modal.create('label-editor', {
					btn_roles: {cancel: "cancel"}
				});

				cargohub.components.modal.open('label-editor', {
					width: 450, height: 150,
					title: "Create new "+ (container_data? container_data.type: "contact")+ " label",
					content: $('#modal-label_editor').html().present(tpl_data),
					callback: setup_Modal_Label_Editor,
					cb_config: {
						mode: "create",
						container_id: cargohub.components.contacts_list.get_Current_Label(),
						container_data: container_data
					}
				}, self);
				break;

			case 'label-rename':
				cargohub.components.modal.create('label-editor', {
					btn_roles: {cancel: "cancel"}
				});

				cargohub.components.modal.open('label-editor', {
					width: 450, height: 150,
					title: "Update selected label",
					content: $('#modal-label_editor').html().present(tpl_data),
					callback: setup_Modal_Label_Editor,
					cb_config: {
						mode: "update",
						label_id: cargohub.components.contacts_list.get_Current_Label(),
						label_data: cargohub.components.contacts_list.get_Curent_Label_Data()
					}
				}, self);
				break;

			case 'label-delete':
				if(!confirm("Are you sure you want to delete selected label?"))
					return;

				// Request deletion of a custom label
				$.post("?action=Delete_Label", {
					label_id: cargohub.components.contacts_list.get_Current_Label()
				}, function() {
					// On label delete, module must be reloaded
					document.location.reload();
				})
				.fail(cargohub.its.http_fail);
				break;

			// Moves back to the contacts search tab without closing the active contact
			case 'tab-search':
				cargohub.components.hashnav.select(0); // Selects first tab
				break;
			// Closes the active contact (ask for confirm if it was changed and not saved)
			case 'vcard-close':
				loaded_contact_id = null;
				self.config.contact = null;
				disable_Toolbar_Item('vcard_existing');

				// If the module loaded an initial contact, it actually reloads the page to the #search part (removes the contact ID from the url); If not, simply closes the tab
				if(self.config.init_contact_id)
					location.href = "/user/contacts#search";
				else {
					cargohub.components.hashnav.unload_Tab(1, 'vcard', "No contact", "/img/icons16/vcard.png", true); // Selects first tab
					cargohub.components.hashnav.disable_At(1);
				}
				break;
			// Saves any changes to the open contact (or creates the contact if it was in create mode)
			case 'vcard-save':
				var contact_status = cargohub.components.contact_editor.check_Status(), label = cargohub.components.contacts_list.get_Current_Label(), numeric_lab_id = null;

				if(!contact_status.changed)
					return cargohub.alert("Contact was not changed. Nothing to save!");

				if(contact_status.valid) {
					self.config.contact = cargohub.components.contact_editor.read_vCard();
					if(loaded_contact_id)
						self.config.contact.id = loaded_contact_id;

					if(label.substr(0,4) == "lab_") {
						numeric_lab_id = label.substr(4);
						label = cargohub.components.contacts_list.get_Curent_Label_Data().parent;
					}

					// Save the contact changes to the database (or, create the contact)
					$.post("/global/contact?action=save_contact", {
						id: loaded_contact_id, // Might be null, in this case the contact is actually created
						// Placement, for creating (ignored when updating)
						label: label,
						lab_id: numeric_lab_id,
						org_id: self.config.org_id,
						user_id: self.config.user_id, // Who created the contact ( or for who it is saved, if personal ) - If empty, the logged in user will be registered with the contact
						// Actual contact data
						name: self.config.contact.name,
						email: self.config.contact.email,
						xdata: JSON.stringify(self.config.contact.xdata)
					}, function(x) {
					// On success, x.id should contain the ID
						if(!loaded_contact_id || x.id) {
							// If this was a create operation, prepare internal variables and then show the "existing" tab; An ID is also returned on updates which create a copy of the existing contact
							loaded_contact_id = x.id;
							enable_Toolbar_Item('vcard_existing');

							// Update the tab title and remove the "create info" box;
							$('#tabs-container li.jqx-tabs-title:eq(1) div.jqx-tabs-titleContentWrapper').html('<img src="/img/icons16/vcard.png" alt="vcard" /> '+ self.config.contact.name);
							$('#vcard-create_info').remove();
						} else {
							// When updating, set the title to the new name
							$('#tabs-container li.jqx-tabs-title:eq(1) div.jqx-tabs-titleContentWrapper').html('<img src="/img/icons16/vcard.png" alt="vcard" /> '+ self.config.contact.name);
						}

						// Also refreshes the grid in the background to reflect the changed or added contact
						cargohub.components.contacts_list.refresh();

						// ... For some weird freekin' reason the Address Book tree display is affected when displaying an alert?
						cargohub.alert(x.response, "Success !", "thumb_up.png");
					})
					.fail(cargohub.its.http_fail);
				} else
					cargohub.alert("Unable to save, please check Name, Email, Company name and other fields which might contain errors.");
				break;
			// Opens a windows which allows the contact to be set as an incoming issue target for the organization
			// This operation should be restricted to certain users !
			case 'issue-target':
				if(!cargohub.components.modal.windows['issue-target'])
					cargohub.components.modal.create('issue-target', {
						btn_roles: {cancel: "cancel"}
					});

				cargohub.components.modal.open('issue-target', {
					width: 440,	height: 180,
					title: "Set as communication target for...",
					content: $("#modal-issue-target").html(),
					callback: setup_Modal_Issue_Target
				}, self);
				break;
			// Deletes the contact (after confirmation) - For the open contact (single)
			case 'vcard-delete':
				if(!confirm("Are you sure you want to delete this contact?"))
					return;

				$.post("/contact/operations?action=delete", {
					id: loaded_contact_id,
					user_id: self.config.user_id, // Might be empty for org, dep level
					org_id: self.config.org_id
				}, function(x) {
					// On success, it means the contact is deleted the grid should be refreshed and the tab should be closed
					if(x.success) {
						cargohub.components.contacts_list.refresh();
						$("div.toolbar button#vcard-close").trigger('click');
						cargohub.alert(x.response, "Success !", "thumb_up.png");
					}
				})
				.fail(cargohub.its.http_fail);
				break;
			}
		});


		// Any buttons which are "disabled" should be disabled in jqwidgets as well
		$("div.toolbar button[data-disabled='yes']").jqxButton({disabled: true});
	},

	/**
	 * Event: A label is selected in the tree
	 * @param label
	 */
	event_Label_Selected = function(label)
	{
		enable_Toolbar_Item('label_selected');

		// Depending on which (type of) label is selected, show or hide the "New Contact", "Set label" and "Delete" buttons. This also depends on user rights...
		if(label.substr(0, 5) == 'user_') {
			if(label.substr(5) == 'AUTOOUT') {
				// These are created automatically by the system. Details (edit) and Delete are still active.
				disable_Toolbar_Item('newcontact');
				enable_Toolbar_Item('delcontact');
			} else {
				// For personal contacts, Create, Delete and SetLabel are always on.
				enable_Toolbar_Item('newcontact');
				enable_Toolbar_Item('delcontact');
				label_contacts_editable = true;
				if(label.substr(5) == 'SELF') {
					disable_Toolbar_Item('setlabel', null, true);
					disable_Toolbar_Item('labels', null, true);
					// Only admin can make user_SELF contacts (Aliases)
					if(cargohub.application.user_Is_Admin()) {
						enable_Toolbar_Item('newcontact');
						enable_Toolbar_Item('delcontact');
					} else {
						disable_Toolbar_Item('newcontact');
						disable_Toolbar_Item('delcontact');
					}
				} else if(parseInt(label.substr(5))>0) {
					enable_Toolbar_Item('newcontact');
					enable_Toolbar_Item('delcontact');
					enable_Toolbar_Item('labels');
					enable_Toolbar_Item('setlabel');
					enable_Toolbar_Item('customlabel');
				} else {
					enable_Toolbar_Item('setlabel');
					enable_Toolbar_Item('labels');
					disable_Toolbar_Item('customlabel');
				}
			}
		} else if(label.substr(0, 4) == 'dep_') {
			disable_Toolbar_Item('customlabel');
			if(cargohub.application.user_Test_Privileges('org_contacts_manager')) {
				enable_Toolbar_Item('newcontact');
				enable_Toolbar_Item('delcontact');
				enable_Toolbar_Item('setlabel');
				label_contacts_editable = true;
				if(label.substr(4) == 'ALL') {
					// This is simply a list of all contacts from all departments. Cannot create here
					disable_Toolbar_Item('newcontact');
					disable_Toolbar_Item('labels');
					disable_Toolbar_Item('setlabel');
				} else {
					// Create for a specific department;
					enable_Toolbar_Item('newcontact');
					enable_Toolbar_Item('labels');
				}
			} else {
				disable_Toolbar_Item('newcontact');
				disable_Toolbar_Item('delcontact');
				disable_Toolbar_Item('setlabel');
				disable_Toolbar_Item('labels');
				label_contacts_editable = false;
			}
		} else if(label.substr(0, 4) == 'org_') {
			disable_Toolbar_Item('customlabel');
			if(label.substr(4) == 'ALL') {
				// Organization contacts managers are allowed to create or delete organization-wide contacts
				if(cargohub.application.user_Test_Privileges('org_contacts_manager')) {
					enable_Toolbar_Item('newcontact');
					enable_Toolbar_Item('delcontact');
					enable_Toolbar_Item('setlabel');
					enable_Toolbar_Item('labels');
					label_contacts_editable = true;
				} else {
					disable_Toolbar_Item('newcontact');
					disable_Toolbar_Item('delcontact');
					disable_Toolbar_Item('setlabel');
					disable_Toolbar_Item('labels');
					label_contacts_editable = false;
				}
			} else if(label.substr(4) == 'USERS') {
				// All editing disabled
				disable_Toolbar_Item('newcontact');
				disable_Toolbar_Item('delcontact');
				disable_Toolbar_Item('setlabel');
				disable_Toolbar_Item('labels');
				disable_Toolbar_Item('customlabel');
				label_contacts_editable = false;
			} else if(label.substr(4) == 'TARGET') {
				// Target contacts cannot be "created", they need to be set as target from other existing contacts;
				disable_Toolbar_Item('newcontact');
				// Delete is enabled for managers, which will not delete the contact, instead it would remove all issue types from it, disconnecting it as a target
				if(cargohub.application.user_Test_Privileges('org_contacts_manager'))
					enable_Toolbar_Item('delcontact');
				else
					disable_Toolbar_Item('delcontact');
			// ----- TEMPORARY ----
			} else if(label.substr(4) == 'OLDTARGET') {
				// Old target contacts work exactly like org-wide contacts
				disable_Toolbar_Item('setlabel', null, true);
				disable_Toolbar_Item('labels', null, true);
				if(cargohub.application.user_Test_Privileges('org_contacts_manager')) {
					enable_Toolbar_Item('newcontact');
					enable_Toolbar_Item('delcontact');
				} else {
					disable_Toolbar_Item('newcontact');
					disable_Toolbar_Item('delcontact');
				}
			// ----- END TEMPORARY -----
			} else if(label.substr(4) == 'ISSUE') {
				// Issue-linked contacts are only visible as a reference. They can't be used as targets and it's not possible to create or delete an issue-linked contact from here. It can however be edited once open.
				disable_Toolbar_Item('newcontact');
				disable_Toolbar_Item('delcontact');
			} else if(label.substr(4) == 'SELF') {
				// Only organization admin can edit the contacts representing the organization itself
				disable_Toolbar_Item('setlabel', null, true);
				disable_Toolbar_Item('labels', null, true);
				if(cargohub.application.user_Test_Privileges('org_admin')) {
					enable_Toolbar_Item('newcontact');
					enable_Toolbar_Item('delcontact');
					label_contacts_editable = true;
				} else {
					disable_Toolbar_Item('newcontact');
					disable_Toolbar_Item('delcontact');
					label_contacts_editable = false;
				}
			}
/* Not yet implemented
			else if(label.substr(4) == 'AUTOIN') {
				// The system creates these contacts when an e-mail is received (IMAP library). Not possible to create a contact with this label. However it is possible to delete one, or move to another label.
				disable_Toolbar_Item('newcontact');
				enable_Toolbar_Item('delcontact');
			}
*/
		} else if(label.substr(0, 4) == 'lab_') {
			// Custom department or organization label
			if(cargohub.application.user_Test_Privileges('org_contacts_manager')) {
				enable_Toolbar_Item('newcontact');
				enable_Toolbar_Item('delcontact');
				enable_Toolbar_Item('setlabel');
				enable_Toolbar_Item('labels');
				enable_Toolbar_Item('customlabel');
				label_contacts_editable = true;
			} else {
				disable_Toolbar_Item('newcontact');
				disable_Toolbar_Item('delcontact');
				disable_Toolbar_Item('setlabel');
				disable_Toolbar_Item('labels');
				disable_Toolbar_Item('customlabel');
				label_contacts_editable = false;
			}
		} else if(label == 'global') {
			disable_Toolbar_Item('setlabel', null, true); // No labels on global
			disable_Toolbar_Item('labels', null, true);
			if(cargohub.application.user_Is_Admin()) {
				enable_Toolbar_Item('newcontact');
				enable_Toolbar_Item('delcontact');
				label_contacts_editable = true;
			} else {
				disable_Toolbar_Item('newcontact');
				disable_Toolbar_Item('delcontact');
				label_contacts_editable = false;
			}
		} else if(label == 'system') {
			// No access here; Needs to be edited straight in the database
			disable_Toolbar_Item('newcontact');
			disable_Toolbar_Item('delcontact');
			disable_Toolbar_Item('setlabel');
			disable_Toolbar_Item('labels');
			disable_Toolbar_Item('customlabel');
			label_contacts_editable = false;
		} else {
			// When unknown, disable the buttons
			disable_Toolbar_Item('newcontact');
			disable_Toolbar_Item('delcontact');
			disable_Toolbar_Item('setlabel');
			disable_Toolbar_Item('labels');
			disable_Toolbar_Item('customlabel');
			label_contacts_editable = false;
		}
	},

	/**
	 * Event: One or more contacts are selected in the datatable
	 * @param contacts
	 */
	event_Contacts_Selected = function(contacts)
	{
		if(contacts.length == 0) {
			disable_Toolbar_Item('basiccon_single_select', null, true);
			disable_Toolbar_Item('basiccon_any_select', null, true);
		} else {
			enable_Toolbar_Item('basiccon_any_select');
			if(contacts.length == 1)
				enable_Toolbar_Item('basiccon_single_select');
			else
				disable_Toolbar_Item('basiccon_single_select');
		}
	},

	/**
	 * Tab initialization callback (used by the hashnav component)
	 * @param tab_id
	 */
	event_Tab_Init = function(tab_id)
	{
		switch(tab_id) {
		// The search tab, used to list labels and contacts
		case 'search':
			cargohub.components.contacts_list.events = {
				label_selected: event_Label_Selected,
				contacts_selected: event_Contacts_Selected,
				contacts_doubleclick: function(contact_id) {
					if(label_contacts_editable)
						load_Contact({
							contact_id: contact_id,
							user_id: self.config.user_id, // Might be empty for org, dep level
							org_id: self.config.org_id,
							edit: true
						});
					else {
						view_Contact(contact_id);
					}
				}
			};

			cargohub.components.contacts_list.setup({
				container: $("#tab_search"),
				tree: {
					mode: self.config.mode,
					user_id: self.config.user_id, // Might be empty for org, dep level
					org_id: self.config.org_id
				}
			}, self);
			break;
		// The VCard tab, used to list the contact itself ( and operate the contact xdata )
		case 'vcard':
			if(self.config.contact === false)
				return; // Error loading contact, nothing to initialize;

			// Some events that happen while editing contact
			cargohub.components.contact_editor.events = {
				changed: function(valid) {
					if(valid)
						$("#contact-save-button").jqxButton({disabled: false});
					else
						$("#contact-save-button").jqxButton({disabled: true});
				}
			};

			cargohub.components.contact_editor.setup({
				container: $("#tab_vcard")
			}, self);

			// Create mode, nothing here... nothing to setup
			if(!self.config.contact)
				return;

			$('#tabs-container li.jqx-tabs-title:eq(1) div.jqx-tabs-titleContentWrapper').html('<img src="/img/icons16/vcard.png" alt="vcard" /> '+ self.config.contact.name);
			// Existing contact...
			if(self.config.contact.id) {
				loaded_contact_id = self.config.contact.id;
				enable_Toolbar_Item('vcard_existing');

				// Check if contact is an issue comm target
				var $target_block = $("#iss-comm-target");
				if($target_block.length) {
					// Attach mouseover events to the titlebars
					$("h4", $target_block).each(function() {
						$(this).on("click", function() {
							var $this = $(this);
							if(confirm("Remove as target for "+$this.text()+" ?")) {
								$.post("?action=unlink_issue_target", {
									org_id: self.config.org_id,
									contact_id: loaded_contact_id,
									istkey: $this.parent().data('istkey')
								}, function(x) {
									if(x.success) {
										// On success, remove the row
										$this.parent().remove();
										// If there are no more rows, remove the box completely
										if(!$target_block.find('div').length) {
											$target_block.remove();
											// TODO: If "Comm Targets" label is currently selected, trigger a refresh of the grid in Tab 0 (to remove this contact)

											cargohub.alert("This contact is no longer used as an issue communication target", "Notification", "kb.png");
										} else
											cargohub.alert(x.response, "Success !", "thumb_up.png");
									}
								})
								.fail(cargohub.its.http_fail);
							}
						});
					});
				}
			}
			break;
		}
	},

	/**
	 * Event: The tab is changed; Show the proper toolbar item
	 * @param selected_tab
	 */
	event_Tab_Selected = function(tab_id)
	{
		// Fadeout any existing top-level bars, and fadein the bar for the correct tab
		$('div.toolbar > div.opbar').each(function() {
			$(this).fadeOut('fast');
		});
		setTimeout(function() {
			// load the bar for the freshly selected tab
			$('div.toolbar #ops-'+tab_id).fadeIn('fast');
		}, 300);
	}
	;

	this._public({
		/**
		 * Module initialization
		 */
		init: function()
		{
			setup_Toolbar();

			// Prepare fixed data for the contact editor
			cargohub.components.contact_editor.fields_structure = self.config.vcard_fields_structure;

			// Prepare component event callbacks
			cargohub.components.hashnav.events = {
				tab_init: event_Tab_Init,
				tab_selected: event_Tab_Selected
			};

			var hashnav_cfg = {
				use_tabs: true,
				tab0: 'search'
			};

			if(self.config.init_contact_id) {
				hashnav_cfg.tab_load_params.vcard = {
					contact_id: self.config.init_contact_id,
					edit: true	// TODO: Check if the user can actually load and edit this contact ! (php side?)
				};
			} else {
				hashnav_cfg.tabs_disabled = [1]; // 0 = search, 1 = vcard,
			}

			// Initialize the Hash-based Tabs navigation Component
			cargohub.components.hashnav.setup(hashnav_cfg, self);
		}
	});
});