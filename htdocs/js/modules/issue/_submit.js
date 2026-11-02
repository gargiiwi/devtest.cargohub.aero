/**
 * Module "Issue Submit" (The form that is used to create issues and submit them to a target contact)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScriptUI
  * @version 2.7
 */

cargohub.issue_submit = donrey.BASE._extend(function() {
	// private
 	var self = this,   
		c = 0,
		popups = {}, // state of the pop-up windows used by this page - Used for lazy initialization ( on first use )
		attachment_ids = [], // List of attachments - Existing or added through DropZone
	/** 
	 * Used to search and change value of a comboBox as the response of a Change event on a field which has a link set up
	 */
	change_Linked_ComboBox = function(id, link_key, data_source_key, link_error)
	{

		var data_src = self.config[data_source_key], found = false;
		if(!data_src)
			return; // Nothing to do, field improperly linked
		if(link_key == 0)
			return; // If link-key equals 0 (is empty, or has the value "0"), the search is not performed.
		// Search the link_key in the source data
		for(c=0; c< data_src.length; c++)
			if(data_src[c].link_key == link_key) {
				$("#"+id).jqxComboBox('selectIndex', c);
				found = true;
			}
		if(!found)
			cargohub.alert(link_error);
	},
	// The list of contacts present in the form. Always starts with the submitter.
	// -- All labels are editable in the modal that shows the universal contact editor
	contact_lines = {},
	contact_lines_count = 0,
	contact_next_idx = 1, // When creating new contact lines, this number will be used in the Dom_ID. Keeps increasing, but not decreasing when a contact is removed.
	/**
	 * Sets up the window that contains the contact viewer/editor
	 * -- The actual contact editing is done through the cargohub.editor_contact module
	 */
	setup_Contact_Window = function($window, config)
	{
		cargohub.application.reset_Session_Timer();
		// The original contact row containing the button used to open the contact window (and the associated data)
		var $contact_row = $("#issue-contacts #"+config.key), crow = contact_lines[config.key.substr(8)], request_params = null, status = null, edit_mode = false;
		// Contact editor toolbar buttons.
		// - First, show the hidden ones if it is the case
		if(crow.key != "contact-submitter")
			$(".toolbar button[data-op='vcard-remove-row']", $window).show();
		// - Then jqx-ize them and set events
		$(".toolbar button", $window)
		.jqxButton({ theme: cargohub.jqxtheme })
		.on('click', function()	{
			var data = $(this).data(), short_key = config.key.substr(8);
			if(edit_mode)
				status = cargohub.components.contact_editor.check_Status();
			else
				status = null;
			switch(data.op) {
				case 'vcard-save':
					// Simulates pressing the window's "Ok" button, which should save the contact and close the window if everything checks.
					$("#modal_contact-window_ok", $window).trigger("click");
					break;
				case 'vcard-close':
					if(status && status.changed)
						if(!confirm("There are unsaved changes, are you sure you want to close the editor?"))
							return false;
					$window.jqxWindow('close'); // Just close directly if there are no changes.
					break;
				case 'vcard-remove-row':
					// Remove the row if possible
					if(crow.key == "contact-submitter")
						// Hacking ? :)
						return cargohub.alert("Intruder alert! Secure all bulkheads.", "Kirk screams:", "ncc1701.png");
					if(status && status.changed)
						if(!confirm("There are unsaved changes, are you sure you want to close the editor and remove the contact?"))
							return false;
					// Remove the row and the matching data
					cargohub.contact_search.destroy(config.key);
					delete contact_lines[config.key.substr(8)];
					contact_lines_count--;
					$contact_row.remove();
					$window.jqxWindow('close');
					break;
				case 'vcard-delete':
					if(!contact_lines[short_key].contact)
						// The "Delete contact" button should not exist for new contacts (drafts) - Actually it should not exist at all... for now.
						return cargohub.alert("Intruder alert! Raise all forcefields.", "Kirk screams:", "ncc1701.png");
					if(!confirm("Are you sure you want to remove this contact from the database?\nIt will not be available for any future searches and any data linked to it\n will show \"Unknown\" instead of the contact's name."))
						return false;
					$.post("/global/contact?action=delete_contact", {
						id: contact_lines[short_key].contact.id
					}, function(x) {
						// On success, x.id should contain the ID
						if(x.success) {
							// Check if the line is the submitter one. If so, empty the line, if not, delete the line.
							if(crow.key == "contact-submitter") {

								cargohub.contact_search.reset(config.key);

								contact_lines[short_key].contact = null;

								contact_lines[short_key].combobox_value = null;

								$("#issue-contacts #"+config.key+" > span").addClass('gray').removeClass('contact-error').text("(Type an E-mail address)").jqxTooltip('destroy');

							} else {

								cargohub.contact_search.destroy(config.key);

								delete contact_lines[short_key];

								contact_lines_count--;

								$contact_row.remove();

							}



						} else {

							cargohub.alert(x.reason, "Message from server", "events.png");

						}

					});



					$window.jqxWindow('close');

					break;

			}

		});



		// Begin with the "Save" button disabled (It will get enabled at first change in the editor, assuming the content is valid)

		$("div.toolbar button#contact-save-button", $window).jqxButton().jqxButton('render');



		// The label edit field

		$("#contact-label-input", $window).addClass("jqx-input-"+cargohub.jqxtheme).val(crow.label);



		// Load the contact information

		// -- (Same "VCard" as used everywhere where contact information is shown)

		// -- The difference is that editing abilities are added onto the HTML



		// This works in hybrid mode.... either load an existing contact, or preinitialize the vcard with information sent by the contact search ( name, or name+email ), and at the moment of save a new contact is created



		if(crow.contact && crow.contact.id)

			// Edit mode, the vCard loads an existing contact, which might or might not be editable by the user (the check is made before even showing the window), using the data returned by the contact search system

			request_params = {

				id: crow.contact.id,

				edit: true

			};

		else {

			// Creation mode, the vCard is loaded without an ID, then the Name/Email (depending on which ones are present) are set on the content

			request_params = {

				create: true

			};

			// Name/Email identified in the input value (changes the "Create" into an Edit if contact is found)

			if(crow.contact)

				request_params.init_contact = crow.contact;

			else if(crow.combobox_value && crow.combobox_value.length > 0)

				// ... nothing, just use the combobox_value as the "name"

				request_params.init_contact = {

					name: crow.combobox_value

				};

			if(self.config.user_mode)

				request_params.deny_edit_user_self = 1; // A flag for the vCard search, when the located contact is found to be of type "user_self", the contact is loaded read only

		}

		$.get("/global/contact?action=visual_card", request_params, function(vcard, xhrstatus)

		{

			var contact_editor_params = {};



			$("#contact-modal", $window).html(vcard);



			// Test if we have the "Edit" button, if so init the VCard editor, if not just leave it in read-only

			if($("#contact-field-edit", $window).length) {



				cargohub.components.contact_editor.events = {

					changed: function(valid_contact) {

						// Not doing anything with this for now (Save button always on, even for invalid contact)

					}

				};



				// If the submitter is being edited, certain fields are mandatory.

				if(config.key == 'contact-submitter')

					contact_editor_params.mandatory_fields = ['company_name'];



				cargohub.components.contact_editor.setup({

					container: $("#contact-modal", $window),

					params: contact_editor_params

				}, self);



				edit_mode = true;

			} else {

				if(self.config.user_mode) {

					$("#contact-editing-helper div:eq(0)", $window).html("<b>Editing system user's contact information is not allowed here.");

					$("#contact-editing-helper div:eq(1)", $window).html("Close the window or press [Cancel] to return to the form");

					$(".jqx-window-header > div:eq(0)").html("<b style='color: red;'>System user's contact is loaded READ-ONLY</b>");

				} else {

					$("#contact-editing-helper div:eq(0)", $window).html("<b>We couldn't determine if you are allowed to edit this address. <a href='/contact/access?action=login&return=issue-submit'>Log-in for identification</a><b/>");

					$("#contact-editing-helper div:eq(1)", $window).html("Press [OK] or [Close] to use this contact, or [Cancel] to enter a different e-mail address.");

					$(".jqx-window-header > div:eq(0)").html("<b style='color: red;'>Contact loaded as READ-ONLY. Please log-in to edit your details</b>");

				}

//				cargohub.alert("This contact has been loaded as READ-ONLY!<br />Please log-in to edit your details.", "Read-Only Mode", "vcard.png", 450, 150);

			}

		});



		// Setup the action for "OK" button

		$('#modal_contact-window_ok', $window).off('click').on('click', function()

		{

			var randomAuth =Math.floor((Math.random() * 10000) + 9000);

			$('#ran_auth').val(randomAuth);

			var contact = null, short_key = config.key.substr(8), $loaded = $("#vcard-loaded-id", $window);



			// When contact window has detected a match and loaded an existing contact, update the line and contact ID

			// If edit is enabled, saving will happen on the loaded contact

			if($loaded.length) {

				contact = cargohub.components.contact_editor.read_vCard(!edit_mode);

				contact.id = parseInt($loaded.val());



				// Update CSS

				cargohub.contact_search.set(config.key, {

					combobox_value: contact.name+ " <"+contact.email+">",

					contact_id: contact.id

				});



				// ... and the stored data

				contact_lines[short_key].contact = contact;

				contact_lines[short_key].combobox_value = null;

				$("#issue-contacts #"+config.key+" > span").removeClass('gray contact-error').text(contact.name+ " <"+contact.email+">").jqxTooltip('destroy');

			}

			if(!edit_mode) {

				// Update only the contact label

				contact_lines[short_key].label = $("#contact-label-input", $window).val();

				$("#issue-contacts #"+config.key+" div.clabel > label").html(contact_lines[short_key].label+ ':'+ (short_key=='submitter'?" <span>&nbsp;</span>": ""));

				return $window.jqxWindow('close');

			}



			// Editing is enabled, check if valid, changed and save

			status = cargohub.components.contact_editor.check_Status();

			if (status.valid) {

                            // If changed, update the contact line again

                            contact = cargohub.components.contact_editor.read_vCard();

                            if ($('#user-menu-bar ul nav li:eq(1) b').text() !== '') {

                                dataProcess();

                            } else {

                                // off the OTP validation for staff user start 

                                if (self.config.user_mode) {

                                    dataProcess();

                                } else {

                                    $.post("/global/contact?action=email_contact", {

                                        name: contact.name,

                                        email: contact.email,

                                        otp: randomAuth

                                    }, function (x) {



                                    }).fail(cargohub.its.http_fail);

                                    cargohub.components.modal.emailconfirm(function () {

                                        dataProcess()

                                    }, "Please check your mail for OTP!", randomAuth, 'process');

                                }





                                // off the OTP validation for staff user end

                            }

                        }else

				cargohub.alert("Problems with some fields.<br />Please fix <span style='color: red; text-decoration: line-through;'>invalid</span> or <span style='background-color: #ffffa0;'>&nbsp;mandatory fields&nbsp;</span>", null, null, 350, 150);



			function dataProcess(){

				if(status.changed) {

					if(contact_lines[short_key].contact)

						contact.id = contact_lines[short_key].contact.id; // Maintain existing ID ... The editor itself doesn't care.



					if(!$loaded.length) {

						// When not loading existing contact, initialize with what was typed in the contact editor

						contact_lines[short_key].contact = contact;

						contact_lines[short_key].combobox_value = null;

						$("#issue-contacts #"+config.key+" > span").removeClass('gray contact-error').text(contact.name + " <"+contact.email+">").jqxTooltip('destroy');

					}



					// Save the contact changes to the database (or, create the contact draft if it didn't previously exist)

					$.post("/global/contact?action=save_contact", {

						id: contact.id, // Might be missing (null), in this case the contact is actually created

						name: contact.name,

						email: contact.email,

						xdata: JSON.stringify(contact.xdata),

						type: "draft", // Only used when creating new draft contact (will be converted to type "isspost" and allocated to an organization when form is actually submitted)

						role: short_key

					}, function(x) {

						// On success, x.id should contain the real ID

						if(x.success) {

							// Server sends different name (when detecting existing contact for the specified e-mail, and under a different name)

							if(x.name) {

								contact.name = x.name;

								// When server sends different name, also update the fake input after it was closed

								$("#issue-contacts #"+config.key+" > span").text(contact.name+ " <"+contact.email+">");

							}



							// Update CSS with update values

							cargohub.contact_search.set(config.key, {

								combobox_value: contact.name+ " <"+contact.email+">",

								contact_id: x.id // ID of the newly created contact

							});



							// ... and the stored value



							contact_lines[short_key].contact.id = x.id;

							contact_lines[short_key].combobox_value = contact.name + " <"+contact.email+">";



							// Update the contact label

							contact_lines[short_key].label = $("#contact-label-input", $window).val();

							$("#issue-contacts #"+config.key+" div.clabel > label").html(contact_lines[short_key].label+ ':'+ (short_key=='submitter'?" <span>&nbsp;</span>": ""));

							// Then finally close the editor window

							$window.jqxWindow('close');



							cargohub.alert(x.response, "Success !", "thumb_up.png");

						} else

							cargohub.its.http_fail(null, "Submit::Save_Contact");

					})

						.fail(cargohub.its.http_fail);

				} else {

					// TODO: Here, check if the contact editor was preinitialized from name/email in the search box, in which case the contact needs to be saved anyway

//					cargohub.alert("Contact valid but not changed");



					$window.jqxWindow('close');

				}

			}

		});

	},



	/**

	 * Prepares the contact search system (CSS) on an existing contact line (with id matching the css key)

	 */

	setup_Contact_Line = function(key, data_defaults)

	{

		// First contact is mandatory, and is set up at the beginning of form submission. Others are created on demand

		cargohub.contact_search.setup_CSS(key, "#issue-contacts #"+key, {

			result_template: $("#template-contact-result").html(),

			placeholder: "(Type an E-mail address)",

			placeholder_addclass: "fake-input jqx-input-"+cargohub.jqxtheme+" jqx-widget-content jqx-widget-content-"+cargohub.jqxtheme+" jqx-rc-all jqx-rc-all-"+cargohub.jqxtheme,

//			width: 335, dropdown_width: 500,
                        width: '61%', dropdown_width: 500, //Cargoclaim add post page modification 

			css: JSON.stringify({"user_self": true, "draft": true, "recent": true}), // "personal": true

			initialize: "recent",

			on_focus: function(key)

			{

				// Slight delay (1s) to allow all the focus events to happen before permitting the CSS to close

				setTimeout(function() {

					self.open_css_key = key;

				}, 1000);

			},

			/**

			 * Event: The value of the combobox changes. Contact search returns either the found contact or just the combobox value

			 * @param key

			 * @param state

			 */

			on_change: function(key, state)

			{

				key = key.substr(8);

				contact_lines[key].combobox_value = state.combobox_value;

				if(state.contact_record) {

					contact_lines[key].contact = state.contact_record;

					contact_lines[key].contact.id = state.contact_id;

				} else

					contact_lines[key].contact = null;

			},

			/**

			 * Event: Contact search is closed without user selecting anything

			 * @param key

			 * @param emaildata

			 */

			on_close: function(key, emaildata)

			{

				self.open_css_key = null;

				key = key.substr(8);



				if(emaildata) {

					// An actual contact has not been found, but still save the analysed information with the puropose of using this in the contact editor / create version

					if(!contact_lines[key].contact) {

						// E-mail based create mode

						contact_lines[key].contact = emaildata;

					}



					// When there is no id, as soon as this is closed it opens the matching contact editor (to allow entering more info)

					// -- When there is an ID, there is no need, as the contact is probably already completed

					if(!emaildata.id) {

						var tpl_data = {

							buttons: {}

						};

						if(key != "submitter")

							tpl_data.buttons.remove = true;



						// The contact window contains a toolbar with operations (like the contact editor in the Communication Center), but the actual contact information is loaded through ajax

						cargohub.components.modal.open('contact-window', {

							width: 694,	height: 590,

							title: "Enter your contact details",

							content: $("script#template_contact_operate").html().present(tpl_data),

							callback: setup_Contact_Window,

							cb_config: {"key": "contact-"+key}

						}, self);

					}

				} else {

					// Raw create mode

					contact_lines[key].contact = null;

				}

			}

		}, data_defaults);



		cargohub.components.modal.create('contact-window', {

			btn_roles: {cancel: "cancel"}

		});



		// The add/edit/delete button

		$("#issue-contacts #"+key+" > button").jqxButton({

			theme: cargohub.jqxtheme,

			width: 22,

			height: 22

		})

		/**

		 * Event: Clicking on the contact editing button brings the Contact Editor plugin

		 */

		.on('click', function(e)

		{

			// Attempt to close any open contact search system just in case it is still open when pushing the button

			cargohub.contact_search.close(self.open_css_key);



			var current_contact = contact_lines[key.substr(8)].contact;



			if(current_contact && current_contact.type == "user_self")

				cargohub.alert("<b>Editing system user contact is disallowed here.</b> You may still enter another E-mail to create a new contact.");

			else {

				var tpl_data = {

					buttons: {}

				};

				if(key != "contact-submitter")

					tpl_data.buttons.remove = true;



				// The contact window contains a toolbar with operations (like the contact editor in the Communication Center), but the actual contact information is loaded through ajax

				cargohub.components.modal.open('contact-window', {

					width: 694,	height: 590,

					title: "Change or create a contact",

					content: $("script#template_contact_operate").html().present(tpl_data),

					callback: setup_Contact_Window,

					cb_config: {"key": key}

				}, self);



				e.preventDefault();

			}

		});

	},



	/**

	 * Creates a new contact line and focuses it so the user can start searching or entering the contact info

	 */

	create_Contact_Line = function(defaults, required)

	{

		if(!defaults)

			defaults = {};

		if(defaults.label)

			defaults.label = defaults.label.charAt(0).toUpperCase() + defaults.label.substr(1);

		var key = defaults.key||"contact-i"+contact_next_idx,

		contact_line = {

			label: defaults.label||"Contact",

			contact: defaults.contact||null,

			combobox_value: defaults.combobox_value||null

		}, tpl_data = clone(contact_line), data_defaults = {};



		tpl_data.key = key,

		tpl_data.jqxtheme = cargohub.jqxtheme;

		tpl_data.required = required;

		if(contact_line.combobox_value)

			tpl_data.combobox_value = contact_line.combobox_value.escapeHtmlEntities();



		$("#issue-contacts").append($("script#template-contact-line").html().present(tpl_data));

		// Contact info storage is an object

		contact_lines[key.substr(8)] = contact_line;

		contact_lines_count++;



		if(defaults.contact) {

			data_defaults.contact_id = defaults.contact.id;

			data_defaults.combobox_value = defaults.combobox_value;

		}

		setup_Contact_Line(key, data_defaults);

		contact_next_idx++;

	},



	/**

	 * Setup the contacts adding, searching and editing controls.

	 */

	setup_Contacts = function()

	{

		var contact_line = null;

		contact_lines_count = 0;

		// Is there already a contact list in place?

		if(self.config.contacts) {

			for(c in self.config.contacts) {

				contact_line = self.config.contacts[c];

				contact_line.key = "contact-"+c;

				contact_line.combobox_value = contact_line.contact.name + " <"+contact_line.contact.email+">";

				create_Contact_Line(contact_line, c=='submitter'); // The submitter is mandatory



				if(c=='submitter')

					$("#subm-email-verif").val(contact_line.contact.email);

			}

		} else {

			// The submitter contact, always present by default. Others are created by the interface if needed.

			create_Contact_Line({

				key: "contact-submitter",

				label: "Submitted by",

				contact: null, // The contact record when found by search or after being typed in

				combobox_value: null, // The value entered into the combobox - Only used when opening the contact editor on a new name or e-mail address

			}, true);

		}



		// Extra empty contacts, an array of labels

		if(self.config.extra_contacts)

			for(c=0; c<self.config.extra_contacts.length; c++) {

				create_Contact_Line({

					label: self.config.extra_contacts[c]

				});

			}



		// Setup the Contact Editor component

		cargohub.components.contact_editor.fields_structure = self.config.vcard_fields_structure;

	},



	/**

	 * Setup group search combobox (this is a local search, with the list inside self.config.groups

	 */

	setup_Groups_Search = function()

	{

		var $group_combo = $("#issue_group_combo");



		$group_combo.jqxComboBox({

			theme: cargohub.jqxtheme,

			searchMode: "containsignorecase",

			autoComplete: true,	minLength: 0,

			source: self.config.groups,

			displayMember: "name", valueMember: "id",

			placeHolder: "Type to search",

			width: 360, height: 22,

			autoDropDownHeight: self.config.groups.length < 10,

			dropDownWidth: 360,

			animationType: "none"

		})

		// Event: An item is selected from the group

		.on('select', function(event) { // value on select event - Which puts the group id in the hidden input

			if(event.args == undefined)

				return false;



			if (event.args.item != null && event.args.item.originalItem.id > 0) {

				$("#issue_group").val(event.args.item.originalItem.id);
                                
//IWI Training Scheduler Training Title issue fix start

        console.log(event.args.item.label);
        $("#xfd_training_ttl").val(event.args.item.label);

//IWI Training Scheduler Training Title issue fix end

			} else {

				$group_combo.jqxComboBox('clearSelection');

				$("#issue_group").val("");

				return false;

			}

		});



		// Add keyboard event to the INPUT inside the combobox

		$group_combo.find("input.jqx-combobox-input").on('keyup', function(event) {

			if(event.keyCode == 13 || event.key == "Enter") {

				if($group_combo.jqxComboBox("getSelectedIndex") == -1) {

					var items = $group_combo.jqxComboBox('getVisibleItems');

					if(items.length > 0) {

						$group_combo.jqxComboBox('selectIndex', items[0].index);

						$group_combo.jqxComboBox("close");

					} else

						return false;

				}

			}

		});



		if(self.config.groups.length == 1)

			// If only one group exists, autoselect it

			$group_combo.jqxComboBox('selectIndex', 0);

		else

			// Otherwise, find the selected index and select it if found

			for(c=0; c< self.config.groups.length; c++) {

				if(self.config.groups[c].id == $("#issue_group").val())

					$group_combo.jqxComboBox('selectIndex', c);

			}},



	/**

	 * Setup scripted (jqWidgets) controls which might appear in various places in the form (dropdowns, combobox, radios, etc.)

	 * -- Classes are used to determine the control's type

	 */

	setup_Scripted_Controls = function()

	{

		var filtered_paste = null;



		// Setup Calendar and Clock inputs

		$("input.datepick").each(cargohub.components.ui.datepicker_input);

		$("input.theclock").each(cargohub.components.ui.clock_input);



		$(".calendar").each(cargohub.components.ui.calendar_input); // Keeping existing jqX control until all templates are modified



		// Special comboboxes used for searching/suggestion (Example: Airport search)

		$(".searchbox").each(cargohub.components.ui.remotesearch_input);



		// Dropdowns (replaces "select")

		$(".dropselect").each(cargohub.components.ui.drop_select);



		// Keyboard and Copy/Paste filters:

		$(".only-numbers").each(function()

		{

			// This kind of input only supports typing numbers into it

			$(this)

			.on('keydown', function(e) {

				// Allow: backspace, delete, tab, escape, enter and .

				if($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||

					// Allow: Ctrl+A / Ctrl+C (copy) / Ctrl+V (paste) / Ctrl+X (cut)

					(e.keyCode == 65 && e.ctrlKey === true) || (e.keyCode == 67 && e.ctrlKey === true) || (e.keyCode == 86 && e.ctrlKey === true) || (e.keyCode == 88 && e.ctrlKey === true) ||

					// Allow: Ctrl+Insert (copy) / Shift-Insert (Paste) / Shift-Delete (cut)

					(e.keyCode == 45 && e.ctrlKey === true) || (e.keyCode == 45 && e.shiftKey === true) || (e.keyCode == 46 && e.shiftKey === true) ||

					// Allow: home, end, left, right

					(e.keyCode >= 35 && e.keyCode <= 39)) {

					// let it happen, don't do anything

						return;

					}

				// Ensure that it is a number and stop the keypress

				if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {

					e.preventDefault();

				}

			})

			.on('paste', function(e) {

				var paste = "", maxlen = $(this).prop("maxlength"), cut_paste = "";

				e.preventDefault();

				if(e.clipboardData || e.originalEvent.clipboardData)

					paste = (e.originalEvent || e).clipboardData.getData('text/plain');

				else if(window.clipboardData)

					paste = window.clipboardData.getData("Text");

				if(paste.length > 0) {

					filtered_paste = paste.replace(/[^0-9.]/g, '');



					// Check "maxlength" property and cut the paste before

					if(maxlen)

						cut_paste = filtered_paste.substr(0, maxlen);

					$(this).val(cut_paste).trigger('change');

				}

			});

		});



		// Setup auto-advance fields (that move to another field when enough charactes have been typed into them)

		$(".auto-move").each(function()

		{

			$(this).on('keyup', function(e) {

				var data = $(this).data(), val = $(this).val(), automove_maxlen = null;

				// Don't do anything for: shift, ctrl, backspace, delete, tab, escape, enter and .

				if($.inArray(e.keyCode, [16, 17, 46, 8, 9, 27, 13, 110, 190]) !== -1 ||

					// Allow: Ctrl+A / Ctrl+C

					(e.keyCode == 65 && e.ctrlKey === true) || (e.keyCode == 67 && e.ctrlKey === true) ||

					// Allow: home, end, left, right

					(e.keyCode >= 35 && e.keyCode <= 39)) {

					// let it happen, don't do anything

						return;

					}

				// On typing anything else, check length and focus next

				if(val.length >= data.automove_len) {

					// To support simultaneous pasting into two fields (when first one has .auto-move), attempt to "paste" the remainded of "filtered_paste" into the second field

					if(filtered_paste && filtered_paste.substr(0, val.length) == val) {

						automove_maxlen = $("#"+data.automove_to).prop("maxlength");

						$("#"+data.automove_to).val(filtered_paste.substr(val.length, automove_maxlen));

					}



					$("#"+data.automove_to).focus();

				}

			});

		});



		//Validate MAWB

		$('#xfd_mawb_s').on('keyup', function(e) {

			// AWB validity check

			var awbp = $("#xfd_mawb_p").val(), awbs = $("#xfd_mawb_s").val();

			if(awbs.length == 8){

				if(parseInt(awbs.substr(0, 7)) % 7 != parseInt(awbs.substr(7))){

					$("#xfd_mawb_s").val('');

					cargohub.components.modal.customalert("Invalid MAWB number. Please make sure you entered the correct digits", "Error", "error.png");

					$('#modal_alert_yes').text("Ok");

					$('#modal_alert_notok').css("display","none");

					return false;

				}



				$.post("?action=match_awb", {

					p: awbp,

					s: awbs,

					fk:record_type_checker()

				}, function(x) {

					if(x){

						if(x.mawb_outcome == 1){

							cargohub.components.modal.customalert(x.message, "Alert", "exclamation.png");

							$('#modal_alert_yes').text("Yes");

							$('#modal_alert_notok').css("display","inline-block");

							return false;

						}

					}

				}).fail(cargohub.its.http_fail);

			}



		});



		// Setup fields which change the value of another field when onChange event is triggered

		$(".link-field").each(function()

		{

			$(this).on('change', function(e) {

				var data = $(this).data(), val = $(this).val();



				switch(data.link_type) {

					case 'comboBox':

						change_Linked_ComboBox(data.link_to, val, data.link_source, data.link_error);

						break;

				}

			});



			// If there's already content in the field, trigger change

			if($(this).val().length > 0)

				$(this).trigger('change');

		});

	},



	file_submit_fallback = false,



	/**

	 * Prepares the classic file submit area.

	 * -- Consists of "file" browser upload fields, for compatibility with browsers that do not support the DropZone (IE9 and older, Firefox less than version 4, Old Opera or Safari)

	 */

//	setup_File_Submit_Area = function()

//	{

//		file_submit_fallback = true;

//		$("div#attachments").show();

//

//		// The hover effect on the file fields

//		$("div#file-submit-area")

//		/**

//		 * Event: Entering a file field's enclosing span creates the "delete" button and assigns it's "click" event.

//		 */

//		.on("mouseenter", "span", function(event) {

//			$(this).prepend($('#field-delete-button').html());

//			$("button", this).jqxButton({

//				theme: cargohub.jqxtheme,

//				width: 22,

//				height: 22

//			})

//			/**

//			 * Event: Clicking the field delete button simply removes the DOM table row and the entry from the "existing_fields" array

//			 */

//			.on('click', function(event) {

//				$(this).parent().remove();

//			});

//		})

//		/**

//		 * Event: No longer hovering over that span removes the button from the DOM

//		 */

//		.on("mouseleave", "span", function(event) {

//			$(this).find('button').remove();

//		});

//	},



	// The DropZone object, which contains the Files entry -> On normal uploads, each file should have a value "ID" which represents the file's database id

	// On successful submit, these files are connected to the issue. (Only for modern browsers. IE8 still handles uploads the classic way)

	attachments = null,
        attachments_2 = null,// add form step 4 multi attach in cargoclaim start

	upload_in_progress = false,



	/**

	 * Replacement for the classic file submit, using DropZoneJS, for fancy uploading

	 */

	setup_DropZone = function()

	{

		attachments = new Dropzone("#uploads-dropzone", {

			url: "?action=upload",

			maxFilesize: 250,

			addRemoveLinks: true,

			init: function() {

				this.on("addedfile", function(file) {

					file.uid = donrey.generate_UniqId();

					if (file.is_mock || !file.type.match(/image.*/)) // This is not an image, so Dropzone doesn't create a thumbnail.

						this.emit("thumbnail", file, "/img/mime/"+ file.type+ ".png");



					$("#uploads-helper").fadeIn();

				});



				this.on("sending", function(file, xhr, formData) {

					// When using the form in update mode, send the issue ID as well

					if(self.config.stored_issue)

						formData.append("issue_id", self.config.stored_issue.id);



					if(!file.is_mock)

						cargohub.components.spinner.On("Uploading files", "Please wait until complete", null, null, 500);

					upload_in_progress++;

				});



				this.on("canceled", function(file) {

					upload_in_progress--;

				});



				this.on("success", function(file, x) {

					// Appends the database ID which is found in the response.

					if(x.success)

						file.id = x.id;

					else {

						// Sometimes there might be a different kind of error, even if file is uploaded successfuly (like session timeout)

						file.status = 'error';

						cargohub.alert(x.reason, "Message from server");

					}

				});



				this.on("error", function(file, x) {

					if(file.status == 'error' && x.error)

						cargohub.alert(x.error, "Message from server");

					else

						cargohub.alert(x);

				});



				this.on('removedfile', function(file) {

					if(file.id)

						// Tell PHP to remove the file if it has a database ID

						$.post("?action=remove_file", {

							id: file.id

						}, function(x) {

							if(x.success) {

								// File was removed, update the total bytes counter, etc...

//								dd(file.size);

							} else {

								cargohub.alert(x.reason, "Message from server", "events.png");

							}

						});

				});



				// Show a progressbar for ALL uploads in the queue (todo later)

				this.on('totaluploadprogress', function(progress, totalBytes, totalBytesSent) {

//					dd(progress);

//					dd(totalBytesSent);

				});



				// Notify the user that all files have finished uploading, and also permit submitting the form

				this.on('queuecomplete', function() {

//					dd("Queue Complete !");

//					cargohub.alert("Files upload complete !");

//					dd(upload_in_progress);

					cargohub.components.spinner.Off(500);

					upload_in_progress = false;

				});



				this.on('reset', function() {

					$("#uploads-helper").fadeOut();

				});

			},

			// Fallback for using IE8/9 ...

//			forceFallback: true,

//			fallback: setup_File_Submit_Area

		});



		// Mockup files shown in the dropzone, for the purpose of forwarding previously uploaded files between form reloads (when fields have errors)

		if(self.config.attachments) {

			var mockFile = null;

			for(c=0; c< self.config.attachments.length; c++) {

				attachment_ids.push(parseInt(self.config.attachments[c].id));

				mockFile = clone(self.config.attachments[c]);

				mockFile.status = "success";

				mockFile.is_mock = true;

				attachments.files.push(mockFile);

				attachments.emit("addedfile", mockFile);

			}

		}
                
                
                
// add form step 4 multi attach in cargoclaim start
//if ($("#uploads-dropzone_2").length > 0 && $("#custom-preview-template").length > 0) {
if ($("#uploads-dropzone_2").length > 0) {
                                attachments_2 = new Dropzone("#uploads-dropzone_2", {

                                    url: "?action=upload",

                                    maxFilesize: 250,

                                    addRemoveLinks: true,

                                    init: function () {

                                        this.on("addedfile", function (file) {

                                            file.uid = donrey.generate_UniqId();

                                            if (file.is_mock || !file.type.match(/image.*/)) // This is not an image, so Dropzone doesn't create a thumbnail.
                                                this.emit("thumbnail", file, "/img/mime/" + file.type + ".png");



                                            $("#uploads-helper_2").fadeIn();

                                        });



                                        this.on("sending", function (file, xhr, formData) {

                                            // When using the form in update mode, send the issue ID as well

                                            if (self.config.stored_issue)
                                                formData.append("issue_id", self.config.stored_issue.id);



                                            if (!file.is_mock)
                                                cargohub.components.spinner.On("Uploading files", "Please wait until complete", null, null, 500);

                                            upload_in_progress++;

                                        });



                                        this.on("canceled", function (file) {

                                            upload_in_progress--;

                                        });



                                        this.on("success", function (file, x) {

                                            // Appends the database ID which is found in the response.

                                            if (x.success)
                                                file.id = x.id;

                                            else {

                                                // Sometimes there might be a different kind of error, even if file is uploaded successfuly (like session timeout)

                                                file.status = 'error';

                                                cargohub.alert(x.reason, "Message from server");

                                            }

                                        });



                                        this.on("error", function (file, x) {

                                            if (file.status == 'error' && x.error)
                                                cargohub.alert(x.error, "Message from server");

                                            else
                                                cargohub.alert(x);

                                        });



                                        this.on('removedfile', function (file) {

                                            if (file.id)
                                                // Tell PHP to remove the file if it has a database ID

                                                $.post("?action=remove_file", {

                                                    id: file.id

                                                }, function (x) {

                                                    if (x.success) {

                                                        // File was removed, update the total bytes counter, etc...

//								dd(file.size);

                                                    } else {

                                                        cargohub.alert(x.reason, "Message from server", "events.png");

                                                    }

                                                });

                                        });



                                        // Show a progressbar for ALL uploads in the queue (todo later)

                                        this.on('totaluploadprogress', function (progress, totalBytes, totalBytesSent) {

//					dd(progress);

//					dd(totalBytesSent);

                                        });



                                        // Notify the user that all files have finished uploading, and also permit submitting the form

                                        this.on('queuecomplete', function () {

//					dd("Queue Complete !");

//					cargohub.alert("Files upload complete !");

//					dd(upload_in_progress);

                                            cargohub.components.spinner.Off(500);

                                            upload_in_progress = false;

                                        });



                                        this.on('reset', function () {

                                            $("#uploads-helper_2").fadeOut();

                                        });

                                    },

                                    // Fallback for using IE8/9 ...

//			forceFallback: true,

//			fallback: setup_File_Submit_Area

                                });
                                if (self.config.attachments_2) {

                                    var mockFile = null;

                                    for (c = 0; c < self.config.attachments_2.length; c++) {

                                        attachment_ids.push(parseInt(self.config.attachments_2[c].id));

                                        mockFile = clone(self.config.attachments_2[c]);

                                        mockFile.status = "success";

                                        mockFile.is_mock = true;

                                        attachments.files.push(mockFile);

                                        attachments.emit("addedfile", mockFile);

                                    }

                                }
                                }
                                
                                // add form step 4 multi attach in cargoclaim end
	



	},



	/**

	 * Prepares the radiobuttons uset to choose submit priority (Staff side only)

	 */

	setup_Priority_Select = function()

	{

		// The Priority selector using radiobuttons (replaces the "priorities_list" dropdown)

		$("div#priority_radios > div").each(function()

		{

			var is_active = $('#issue_priority').val() == this.id.substr(5);

			$(this)

				.jqxRadioButton({

					theme: cargohub.jqxtheme, height: 22,

					groupName: "priority",

					checked: is_active

				})

				.on('change', function(e) {

					if(e.args.checked) {

						$('#issue_priority').val(this.id.substr(5));

					}

				});

		});

	},



	/**

	 * Prepares other Staff side only options

	 */

	setup_Submit_Options = function()

	{

		var first = true;



		// Submit success processing options

		$("div#submit-process-options > div").each(function()

		{

			$(this).jqxRadioButton({

				theme: cargohub.jqxtheme, height: 20,

				groupName: "process-options",

				checked: self.config.submit_options && $(this).data('value') == self.config.submit_options.process? true: first

			});

			first = false;

		});



		first = true;



		// Department access options

/*

		$("div#submit-department-options > div").each(function()

		{

			$(this).jqxRadioButton({

				theme: cargohub.jqxtheme, height: 20,

				groupName: "department-options",

				checked: self.config.submit_options && $(this).data('value') == self.config.submit_options.department? true: first

			});

			first = false;

		});



		first = true;

*/



		// Submit success processing options

/*

		$("div#submit-finalize-options > div").each(function()

		{

			$(this).jqxRadioButton({

				theme: cargohub.jqxtheme, height: 20,

				groupName: "finalize-options",

				checked: self.config.submit_options && $(this).data('value') == self.config.submit_options.finalize? true: first

			});

			first = false;

		});

*/

	},



	/**

	 * Sets up the actions possible on the form ( not related to the confirm submit window )

	 */

	setup_Actions = function()

	{

		// These are repeatable form elements which are not visible initially. They become visible with some event.

		var multi_areas = {}, temp = null;



		// The buttons on the form (including the fake "submit" one)

		$('form#issue_form input[type="button"]').jqxButton({

			theme: cargohub.jqxtheme

		})

		/**

		 * Event: Button is clicked

		 */

		.on('click', function()

		{

			cargohub.application.reset_Session_Timer();

			var data = $(this).data();

			switch(data.action) {

				// Opens a jqxWindow with information present in a JS template

				case 'open-window':

					// Setup the reusable window which can be used to open information or other modal-type stuff

					if(!popups.general) {

						cargohub.its.init_Modal_Window('general', {	"ok": "Ok" }, null, null, null, cargohub.fullwidth, 768);

						popups.general = true;

					}



					var window = data.window.split(',');

					cargohub.its.open_Modal_Window('general', window[0], window[1], window[2],

						$('#'+data.template).html());

					break;



				// Adds another contact line to the form

				case 'add-contact':

					create_Contact_Line({

						label: "Other contact"

					});

					break;



				// Reveals a sequence of hidden areas, with the purpose of keeping the form uncluttered

				case 'reveal-multi-area':

					if(!multi_areas[data.idprefix])

						multi_areas[data.idprefix] = data.idstart;

					else if(multi_areas[data.idprefix] > data.idxmax)

						break;

					// ok, ready to reveal

					$('#'+data.idprefix+multi_areas[data.idprefix]).fadeIn();

					multi_areas[data.idprefix]++;



					// when reaching idmax, hide the button

					if(multi_areas[data.idprefix] > data.idmax)

						$(this).fadeOut();

					break;



				// Adds more file fields (IE compatibility mode file upload system)

				case 'add-file-input':

					$("#file-submit-area").append("<span><input type=\"file\" name=\"uploads[]\"></span>");

					break;

					

				case 'import-form-check':

					//alert('XLS file uploaded successfully');

					return cargohub.alert("XLS file uploaded successfully");

					break;



				// Submits the form (which is either a create or an update) ...

				case 'form-check':

					// ... but first, check if the form contents seems to be correct (primary check, secondary is done on the server)

					if(check_Form_Inputs()) {

						//20092024 start
						var date_train = $('input[name="xfd[date_train]"]').val();
						if(date_train ==''){
							cargohub.alert("Please Fill Training Date.");
							return false;
						}
						//20092024 end
						
						// 06092024 by iwi start

						if ($('input[name="xfd[certificate_expiry]"]').length) {

							var certificate_expiry = $("input[name='xfd[certificate_expiry]']:checked").val();

							var train_exp = $('input[name="xfd[train_exp]"]').val();

							if(certificate_expiry == 1 && train_exp ==''){

								cargohub.alert("Please Fill Training Expiry Date.");

								return false;

							}

						}
						// 06092024 by iwi end

						// 26092024   train_scheduler_classroom_type addded by iwi start
						if ($('input[name="xfd[certificate_expiry]"]').length) {
							var train_loc = $("input[name='xfd[train_loc]']:checked").val();
							var classroom_type = $("input[name='xfd[classroom_type]']:checked").val();

							if(train_loc == 'Classroom' && classroom_type == undefined){
								cargohub.alert("Please Select Classroom.");
								return false;
							}
						}
						//  26092024   train_scheduler_classroom_type addded by iwi end

						// Send the contact_lines variable and let PHP sort it out ( which ones are existing contacts, which ones are fresh, etc.), after cleaning it of values not used by PHP

						for(c in contact_lines) {

							delete contact_lines[c].combobox_value;

							delete contact_lines[c].contact.xdata;

						}

						$('form#issue_form').append($("<input>").attr("type", "hidden").attr("name", "contacts").val(JSON.stringify(contact_lines)));



						// Append a hidden item with the file ID's before the form is submitted (if any)

						if(attachment_ids.length > 0)

							$('form#issue_form').append($("<input>").attr("type", "hidden").attr("name", "attachment_ids").val(JSON.stringify(attachment_ids)));



						// Grab the data for the user options (if in user mode)

						if(self.config.user_mode)

							$('form#issue_form')

								.append($("<input>").attr("type", "hidden").attr("name", "submit_options[process]").val(cargohub.application.jqxRadioButtons_Get_Checked_Value("#submit-process-options")));

//								.append($("<input>").attr("type", "hidden").attr("name", "submit_options[department]").val(cargohub.application.jqxRadioButtons_Get_Checked_Value("#submit-department-options")));

//								.append($("<input>").attr("type", "hidden").attr("name", "submit_options[finalize]").val(cargohub.application.jqxRadioButtons_Get_Checked_Value("#submit-finalize-options")));



						// Just a little feedback that something is happening...

						cargohub.components.spinner.On("Submitting", "Please wait", 8000, function() {

							cargohub.alert("Submitting is taking longer than expected... please wait!", "Slow connection detected");

							setTimeout(function() {

								cargohub.components.spinner.Off();

								cargohub.application.evan_Standard_Notify('error', "Your internet is either too slow or the connection failed.", null, null, "If nothing happens in the next minute, please close the browser, reopen it and try again.");

								$("#modal_alert_ok").trigger('click');

								$('html, body').animate({

									scrollTop: 0

								}, 1000);

							}, 30000);

						}, 1000);



						// If we're here, all the data should be ready. Send this stuff to the server

						$('form#issue_form').submit();

					}

					break;

			}

		});

	},



	/**

	 * @TODO: Function is not used / Deprecated !

	 * Sets up the content of Submit Confirm window, which has multiple versions and purposes:

	 *

	 * User mode (when staff user is logged in):

	 * - 1. Acknowledge that the user wants to submit the form

	 * - 2. Choose communication options on submit ( send confirmation e-mail to the "submitter" contact, or not )

	 * - 3. Option to show the form again ( keeping same data, with the purpose of submitting multiple similar data sets consecutively )

	 *

	 * Visitor mode or "email login":

	 * - 1. Acknowledge that the user wants to submit the form and show a disclaimer (terms and conditions), with a confirm checkbox (which needs to be clicked).

	 * - 2. Show information about the organization that will become the initial "master" of this submitted information (depends on various aspects, active domain, selected group)

	 * - 3. If the organization is "inactive", search and choose a contact from the detected organization, to which this form will be submitted (forwarded) via e-mail

	 *   ( and in the future, also via API interfaces )

	 */

	setup_Submit_Confirm = function($window, config)

	{

		cargohub.application.reset_Session_Timer();



/*		dd("Setting up window...");

		dd(self.config);

		dd(config);

		dd(contact_lines);

*/

		var email_cc_list = [], cc_id = 0, cc_length = 0;



//		if(!self.config.user_mode)

//			$("div#submit-disclaimer").fadeIn(1200);



		// The confirmation e-mail choice (well, not always an actual choice)

		$("#mail_confirm_options > div", $window).each(function()

		{

			$(this).jqxRadioButton({

				theme: cargohub.jqxtheme, height: 20,

				groupName: "mail_options",

				checked: self.config.mail_confirm_option == $(this).data('value')

			});

		});

		// Tooltips on the helper icons

		$("#mail_confirm_options_help img.info-tip", $window).each(function() {

			$(this).jqxTooltip({

				content: $(this).data('tooltip'),

				position: 'bottom',

				theme: cargohub.jqxtheme

			});

		});



		if(config.multiple_contacts) {

			var additional_contacts_list = "", contact_key = null;

			// Scan contact_lines and make a list of all e-mail addresses to which stuff will be sent

			for(contact_key in contact_lines)

				if(contact_key != "submitter")

					additional_contacts_list+='<div style="text-align: left;"><b>'+contact_lines[contact_key].contact.name+'</b> &lt;'+contact_lines[contact_key].contact.email+'&gt;</div>';



			// Recipients list tooltip

			$("#mail_confirm_options_help #multiple-contacts-list", $window).jqxTooltip({

				content: additional_contacts_list,

				position: 'bottom',

				theme: cargohub.jqxtheme,

				autoHide: false,

				showArrow: false

			});

		}



		// The window is initialized with the recipient contacts search active (submitting to an e-mail address beside recording the issue into the application's database)

		// For public version the possible contacts are determined by the detected master organization, for staff version the contacts are the contacts of the partners that can receive the issue by e-mail)

		if(config.submit_by_email) {

			// Activate the contact search system on the primary recipient

			cargohub.contact_search.setup_CSS('submit-contact-search', "#modal_confirm-window #submit-contact-search", {

				result_template: $("#template-contact-result").html(),

				placeholder: "(Type an E-mail address)",

				placeholder_addclass: "fake-input jqx-input-"+cargohub.jqxtheme,

				width: 360,

				// TODO: What is being searched for here ?

				css: JSON.stringify({"global": true, "organization": true}),

				on_focus: function(key) {

					// Slight delay (1s) to allow all the focus events to happen before permitting the CSS to close

					setTimeout(function() {

						self.open_css_key = key;

					}, 1000);

				},

				on_change: function(key, state) {

//					key = key.substr(8);

//					contact_lines[key].id = state.contact_id;

//					contact_lines[key].combobox_value = state.combobox_value;

//					contact_lines[key].contact = state.contact_record;

				},

				on_close: function(key, emaildata) {

					self.open_css_key = null;

//					key = key.substr(8);

//

//					if(emaildata) {

//						// An actual contact has not been found, but still save the analysed information with the puropose of using this in the contact editor / create version

//						if(!contact_lines[key].contact) {

//							// E-mail based create mode

//							contact_lines[key].id = null;

//							contact_lines[key].contact = emaildata;

//						}

//					} else {

//						// Raw create mode

//						contact_lines[key].id = null;

//						contact_lines[key].contact = null;

//					}

				}

			}); // , submitter_cssdata (inialization when submitter is known - for example, a logged in user)





		};



		if(self.config.user_mode) {	// User mode specific controls

			// The repeat form checkbox

			$("#form_submit_options > div", $window).each(function()

			{

				$(this).jqxRadioButton({

					theme: cargohub.jqxtheme, height: 20,

					groupName: "submit_options",

					checked: $(this).data('value')=='show'

				});

			});



		} else { // Visitor mode specific controls

			// ... "I have read and accepted the terms and conditions." ...

			$("#submit_disclaimer_accept").jqxCheckBox({

				theme: cargohub.jqxtheme

			});

		}



		/**

		 * Event: The Submit Confirmation window is closed (by any means).

		 * -- Destroy the contact-search control

		 * -- Clean the recipients list

		 */

		$window.on('close', function() {

			cargohub.contact_search.destroy('submit-contact-search');

		});



		/**

		 * Event: The OK Button is pressed, attempt submit the form

		 * - Verify if the options on the confirmation window are correct

		 *   - the submit-by-mail has some special requirements

		 *   - in public, mode, a checkbox must be checked in order to submit ( I accept and... )

		 */

		$('#modal_confirm-window_ok', $window).off('click').on('click', function()

		{

			// Mail confirmation option value

			$('form#issue_form').append($("<input>").attr("type", "hidden").attr("name", "mail_confirm_option").val(cargohub.application.jqxRadioButtons_Get_Checked_Value($("#mail_confirm_options", $window))));



			if(self.config.user_mode) {

				// User mode specific checks

				$('form#issue_form').append($("<input>").attr("type", "hidden").attr("name", "form_submit_option").val(cargohub.application.jqxRadioButtons_Get_Checked_Value($("#form_submit_options", $window))));



			} else {

				// Public mode specific checks

				if(!$("#submit_disclaimer_accept").jqxCheckBox('checked')) {

					return cargohub.alert("Please confirm that you have read and accepted the terms and conditions !");

				}

			}



			// Send the contact_lines variable and let PHP sort it out ( which ones are existing contacts, which ones are fresh, etc.), after cleaning it of values not used by PHP

			for(c in contact_lines) {

				delete contact_lines[c].combobox_value;

				delete contact_lines[c].contact.xdata;

			}

			$('form#issue_form').append($("<input>").attr("type", "hidden").attr("name", "contacts").val(JSON.stringify(contact_lines)));



			// If we're here, all the data should be ready. Send this stuff to the server

			$('form#issue_form').submit();

		});



		return;



		// TODO: This is replaced by the contacts search, review the code, the effect of (+) button, etc....





		// The intendend target for this issue is not actively using the system yet, so ITS is forwarding the issue to one or more e-mail addresses (beside recording it in the database)

		if(config.submit_by_email) {

			// E-mail Combobox, taps into the user's contacts, displaying basic contact information for each entry (name, email) using customized combobox renderer

			$("#forward-address", $window).jqxComboBox({

				theme: cargohub.jqxtheme,

				source: email_forward.contacts,

				displayMember: "name",

				valueMember: "email",

				searchMode: "containsignorecase",

				width: 300, height: 24,

				dropDownHeight: 160, itemHeight: 40,

				/**

				 * Element renderer for the combobox, displays both name and e-mail while searching

				 */

				renderer: function(index, label, value)

				{

					return "<div><b>" + label + "</b><br />" + value + "</div>";

				},

				/**

				 * Renders the selected item in a certain way

				 */

				renderSelectedItem: function(index, item)

				{

					return item.label + " <" + item.value + ">";

				},

				selectedIndex: email_forward.selectedIndex

			});



			$("#forward-address-add", $window).jqxTooltip({

				content: "Add this address to the list of recipients",

				position: "mouse",

				opacity: 1

			})

			/**

			 * Event: By clicking the (+) button the e-mail address which is currently in the Address input is added to a list shown below

			 */

			.on("click", function(event) {

				var email = $("#forward-address", $window).val();

				// Check to see if this email is already present in the list, in which case the value is not added



				if(!validate_email(email))

					return cargohub.alert("Please enter a valid e-mail address.");



				if($.inArray(email, email_cc_list) > -1)

					return cargohub.alert("This e-mail is already part of the list !");



				// Visually display the new e-mail in the list

				if(cc_length == 0) {

					$("div#email-cc-list", $window).fadeIn();

					$("div#email-cc-list", $window).append('<ul></ul>').jqxTooltip({

						content: "Click on any of the e-mail addresses to remove them",

						position: "mouse"

					});

				}



				$("div#email-cc-list ul").append($("<li data-ccid='"+cc_id+"'>" + email + "</li>").click(function() {

					delete email_cc_list[$(this).data("ccid")];

					$(this).remove();

					cc_length--;

					if(cc_length == 0)

						$("div#email-cc-list", $window).fadeOut().find('ul').remove();

				}));

				$("div#forward-address", $window).jqxComboBox("clearSelection");

				$("div#forward-address input[type='hidden']", $window).val("");



				email_cc_list.push(email);

				cc_id++;

				cc_length++;

			});

		}



		/**

		 * Event: The confirm window is closed

		 * - check if it was closed by the press of the "OK" button, in which case the issue is submitted.

		 * - otherwise, nothing happens, going back to the form

		 */

		$('#modal_confirm-window_ok', $window).off('click').on('click', function()

		{

			// find which e-mail confirmation radiobutton is checked

			var mail_confirm_option = 0;

			$("#mail_confirm_options > div").each(function()

			{

				if($(this).jqxRadioButton('checked'))

					mail_confirm_option = $(this).data('value');

			});

			// add the option to the data being submitted

			$('form#issue_form').append($("<input>").attr("type", "hidden").attr("name", "mail_confirm_option").val(mail_confirm_option));



			// The Form Repeat Option

			if($("#form_repeat_option").jqxCheckBox('checked'))

				$('form#issue_form').append($("<input>").attr("type", "hidden").attr("name", "form_repeat_option").val(1));



			if(!self.config.user_mode && !$("#submit_disclaimer_accept").jqxCheckBox('checked')) {

				return cargohub.alert("Please confirm that you have read and accepted the disclaimer !");

			}



			if(email_forward.on) {



				var email_list = [], main_email = $("div#forward-address", $window).val(), recipients = main_email;



				// If main e-mail exists, attempt to validate it

				if(main_email) {

					if(validate_email(main_email)) {

						email_list.push(main_email);

					} else {

						return cargohub.alert("Please enter a valid e-mail address.");

					}

				}



				// Check if email_cc_list is non-empty and has non-null values:

				if(email_cc_list.length > 0)

					for(c = 0; c< email_cc_list.length; c++)

						if(email_cc_list[c])

							email_list.push(email_cc_list[c]);



				if(email_list.length == 0)

					return cargohub.alert("No e-mails have been specified, either primary or extra recipients");

				else if(email_list.length == 1 && email_list[0] == main_email)

					// When just the main e-mail is part of the recipients list, remove the extra list

					email_list = [];



				// Add the e-mail lists as a concatenated string to the data being submitted

				if(email_list.length)

					recipients = email_list.join(',');



				$('form#issue_form').append($("<input>").attr("type", "hidden").attr("name", "mail_forward_recipients").val(recipients));



				if(confirm("Are you sure you want to submit this to the e-mail address(es):\n"+recipients+" ?"))

					$('form#issue_form').off('submit').submit();

				else

					return false;

			} else

				// Without e-mail forward options, the OK button submits directly

				$('form#issue_form').off('submit').submit();

		});

	},



	/**

	 * Do an ajax request to discover which organization handles the selected or auto group,

	 * - and some information about it (like if it's actively using the system)

	 * - with the purpose of being displayed in the Submit Confirm window and also select the result set for the submit-via-email contact search

	 */

	test_Group_Master = function(group_id, complete_Callback)

	{

//		dd(group_id);

		return;



		$.get("/global/operations?action=test_group_master", {

			group_type: self.config.group_type,

			group_id: group_id

		}, function(x) {

			if(x.success) {

				if(x.forward_on) {

					email_forward = {

						on: true,

						selectedIndex: -1,

						contacts: x.org.contacts

					};

					// If auto_email exists, find it in contacts and return index

					if(x.org.auto_email)

						for(c=0; c<x.org.contacts.length; c++)

							if(x.org.contacts[c].id == x.org.auto_email)

								email_forward.selectedIndex = c;

					delete x.org.contacts;

				} else

					email_forward = {

						on: false

					};



				submit_confirm.org = x.org;

				submit_confirm.email_forward = email_forward;

				cargohub.its.open_Modal_Window('confirm-window', 600, 480 + 200 * email_forward.on, "Submitting the form", $("script#template_confirm_submit").html().present(submit_confirm), setup_Submit_Confirm);

			} else

				cargohub.alert("Unfortunately, no organization is handling this "+self.config.group_name+", cannot continue");

		});

	},



	/**

	 * Shows the Submit Confirm modal, in which several options are available to be set before actually submitting the form

	 * -- Some information in this confirm modal depends on the data inside the form ( system fields, like the primary contact, group selector, priority, etc. )

	 * -- In case the window shows the option to submit via e-mail, it also allows previewing the e-mail

	 * @TODO: Function is not used / Deprecated !

	 */

	show_Submit_Confirm = function()

	{

		var

		tpl_data = { // Template variables for the confirm window

			jqxtheme: cargohub.jqxtheme,

			submitter: contact_lines.submitter.contact,

			ist: self.config.ist,

			multiple_contacts: contact_lines_count > 1,

			mail_confirm_option: self.config.mail_confirm_option // The preselected mail confirmation option (user option)

		},

		window_config = { // Configuration sent to the setup function for the confirm window

			width: 600,

			height: 400 + 20 * (contact_lines_count > 1),

			multiple_contacts: contact_lines_count > 1

		};



		// For now, test the submit by e-mail ...

//		tpl_data.submit_by_email = true;

//		window_config.submit_by_email = true;



		// Mode of operation for Submit Confirm window - Staff User or Visitor (there are differences in what is being displayed)

		if(self.config.user_mode) {

			// User is logged in, no need to check group master - issue will be logged into the organization account (e-mail forwarding not used)

			tpl_data.user_mode = true;



		} else {



		}



		// Initialize window on first use.

		if(!popups.confirm) {

			cargohub.its.init_Modal_Window('confirm-window', null, {"cancel": "cancel"});

			popups.confirm = true;

		}



//		dd("Opening submit confirm window...");

		cargohub.its.open_Modal_Window('confirm-window', window_config.width, window_config.height, "Form submit confirmation", $("script#template_confirm_submit").html().present(tpl_data), setup_Submit_Confirm, window_config);

	},



	/**

	 * Checks if it is possible to submit the form

	 * -- Verifies if the primary (submitter) contact has been set properly

	 * -- Verifies if a group has been selected (or is auto-selected)

	 * -- Primary verification on all inputs inside rows having class "required" - The should at least have something typed in them

	 *    -- First detected "required" input will get focused, but all of them receive the "fail" treatment.

	 *

	 * @returns true if the form appears to be "okay". The server does a secondary check however.

	 */

	check_Form_Inputs = function()

	{



if ($("#issue-contacts").length > 0){
 if(!contact_lines)

			return cargohub.alert("Captain, communication channels are being subverted !", "Uhura alerts:", "ncc1701.png");



		if(!contact_lines.submitter)

			return cargohub.alert("We just lost our communication officer...", "Kirk moans:", "ncc1701.png");



		// 1. Verifies if submitter contact is valid

		// -- Either an exact contact (with ID), or at least an E-mail (minimum required for contact info), in which case a draft contact is automatically created from that e-mail address and used

		if(!contact_lines.submitter.contact) {

			cargohub.alert("Please enter submitter contact (yourself)", null, null, null, null, function($alert) {

				/**

				 * Event: When the Alert is closed, focus the Submitter Contact line

				 */

				$alert.on('close', function() {

					$("#contact-submitter").focus();

				});

			});

			return false;

		}



		if(!contact_lines.submitter.contact.id) {

			cargohub.alert("Your contact information is incomplete. Click OK to type in the mandatory details...", "Contact verification", null, null, null, function($alert) {

				/**

				 * Event: When the Alert is closed, focus the Submitter Contact line

				 */

				$alert.on('close', function() {

					$("div#contact-submitter > button").trigger('click');

				});

			});

			return false;

		}



}
		


		// 1.b - Verify if the disclaimer checkbox is ticked.

	
	if ($("#accept-disclaimer").length > 0){
		if(!self.config.hide_tos && !$("#accept-disclaimer").jqxCheckBox('checked'))

			return cargohub.alert("Please confirm that you have read and accepted the Terms and Conditions !");



		// 1.c - If no contacts predefined, asks for the submitter contact e-mail to be entered again.

		if(!self.config.user_mode && !self.config.contacts) {

			if(!$("#subm-email-verif").val())

				return cargohub.alert("Please type your submitter e-mail again for verification");

			else

				if(contact_lines.submitter.contact.email != $("#subm-email-verif").val()) {

					return cargohub.alert("Verification E-mail does not match the submitter contact. Please check it and try again !");

				}

		}



		// 2. Scans contacts lines for invalid contacts, which must be removed from the form before submit can proceeed

		var invalid_contacts = [], contact_key = null, confirm_text = null;

		$("#issue-contacts > div.issue-contact").each(function() {

			contact_key = $(this).prop('id').substr(8);

			if(contact_key!="submitter" && !contact_lines[contact_key].contact)

				invalid_contacts.push(contact_key);

		});



		// Removes the invalid contacts

		if(invalid_contacts.length > 0) {

			if(invalid_contacts.length > 1)

				confirm_text = "Detected "+invalid_contacts.length+ " incomplete or invalid contacts.\nRemove them ?";

			else

				confirm_text = "Detected one incomplete or invalid contact.\nRemove it ?";



			if(confirm(confirm_text)) {

				for(c=0; c< invalid_contacts.length; c++) {

					// Remove the invalid row and the matching data

					cargohub.contact_search.destroy('contact-'+invalid_contacts[c]);

					delete contact_lines[invalid_contacts[c]];

					contact_lines_count--;

					$("#issue-contacts > div#contact-"+invalid_contacts[c]).remove();

				}

			} else

				return false; // Don't continue if not removing invalid contacts.

		}
}


		// 3. Check if the form uses issue groups, and if an issue group has been set.

		if(self.config.groups && ($('#issue_group_combo').jqxComboBox('getSelectedIndex') == -1 || $('#issue_group_combo').jqxComboBox('getSelectedItem').value != $("#issue_group").val())) {

			cargohub.alert("Please select the "+self.config.group_name, null, null, null, null, function($alert) {

				/**

				 * Event: When the Alert is closed, focus on the group selector combo

				 */

				$alert.on('close', function() {

					$("#issue_group_combo").jqxComboBox('focus');

				});

			});

			return false;

		}



		// 4. Verify inputs (text, textarea) inside rows with class "required", they should be non-empty (have a value), but only in <td>'s which are direct to that "required" row. Any other sub-tables are ignored

		// -- Due to the complexity of other possible inputs, anything else is verified on the server side

		var found_first_empty = false;

		$(".required > td input[type*='text'], .required > td textarea, .required > td input.jqx-combobox-input, .required select").each(function() {



			if($(this).hasClass('skipcheck'))

				return;



			if(!$(this).val() || $(this).val().length == 0) {

				// All inputs detected empty receive the "field_error" class

				$(this).addClass('field_error');



				// Only alert and focus the first of the empty ones.

				if(found_first_empty)

					return;

				found_first_empty = true;

				var $input = $(this), label = $.trim($input.parent().parent().find('th > label').text());

				if(label.length == 0)

					label = "An input";

				cargohub.alert(label + " is required and been detected empty or incomplete. Please enter the information.", null, null, null, null, function($alert) {

					$alert.on('close', function() {

						$input.focus();

					});

				});

			} else

				$(this).removeClass('field_error');

		});



		if(found_first_empty)

			return false;



		// 5. Verify if uploads are still in progress.

		if(upload_in_progress) {

			cargohub.alert("There are uploads currently in progress. Please wait...");

			return false;

		}



		var error_count = 0;



		// 6. Assamble the list of uploaded file ids (or pass-through the existing ones)

		if(file_submit_fallback) {



		} else

			// Verify the files in the DropZone ( if it exists ), and don't allow submit with files with status other than "success"

			if(attachments && attachments.files && !file_submit_fallback) {

				for(c in attachments.files) {

					if(attachments.files[c].status == "success")

						attachment_ids.push(parseInt(attachments.files[c].id));

					else

						error_count++;

				}



				if(error_count > 0)

					return cargohub.alert("Some files failed upload or were cancelled. Please remove them before continuing !");

			}



		// 7. Prepare data from managed controls which are unable to transmit directly (as inputs)

		// 7.A - dropselect multi-select

		$(".dropselect.multi-select").each(function() {

			var

			name = $('#'+ $(this).prop('id') + '_jqxDropDownList').data('name'),

			$hidden_vals = $("<input type='hidden' name="+ name.substr(0, name.length - 2)+">"); // Name gets the end [] cut, as the original was designed for multiple checkboxes

			;

			// Create an input of type hidden which has the original name, and contains the options as json-encoded



			$hidden_vals.val($(this).val());

			$('form#issue_form').append($hidden_vals);

		});



		return true;

	};



	this._public({

		// section initialization

		init: function()

		{

			// Default theme for when none is selected (used only in public mode)

			if(!cargohub.jqxtheme)

				cargohub.jqxtheme = "base";



			// System fields and elements

			if(self.config.groups)

				// Groups combobox should exist and be visible

				setup_Groups_Search();

			else

				// Not showing group search to the user, instead, there should be a group_id default. If not, there's a problem - Warn the user and make the form dissapear

				if(!self.config.auto_group) {

					var critical_error = "There is an error in the configuration of this domain, please contact support@cargohub.nl";

					cargohub.alert(critical_error, "Critical error");

					$("div.page").html('<div style="text-align: center; padding: 40px;">'+critical_error+'</div>');

					return false;

				}



			if(self.config.user_mode) {

				// User Flags

				if(self.config.user_flags.priority !== false)

					setup_Priority_Select();



				setup_Submit_Options();

			}



			// Controls in the form which are not standard HTML (must be controlled by JS)

			setup_Scripted_Controls();



			// Elements with tooltips

			$(".info-tip, .field_error").each(cargohub.components.ui.add_Tooltip);



			// Element which open an info window when clicked

			$(".info-click").each(function()

			{

				$(this).on('click', function() {

					cargohub.alert($(this).data('tooltip'), "Did you know ?", "help.png");

				});

			});



			// The disclaimer (only when non-logged in)

			if(!self.config.hide_tos) {


if ($("#accept-disclaimer").length > 0){
  
				$("#accept-disclaimer").jqxCheckBox({

					theme: cargohub.jqxtheme,

					checked: self.config.auto_accept_tos == true

				});
}

			}



			setup_Contacts();

			setup_DropZone();



			// Various actions that the user can do on the form (submit is included here)

			setup_Actions();



			// If this variable is present, right after the form is displayed a modal window must be shown, containg either a confirmation or request for more information from the user

			if(self.config.process_config) {



				switch(self.config.process_config.mode) {



					// Use the issue2message module/component to support transfer message creation

					case 'transfer':

						// Setup the window

						if(!popups.transfer) {

							cargohub.its.init_Modal_Window('transfer-message', {"ok": "Send", "cancel": "Cancel"}, {"cancel": "cancel"}, null, null, cargohub.fullwidth, 600);

							popups.transfer = true;

						}



						var

						header_tpl_data = {

							sender: contact_lines.submitter.contact, // TODO - If in user mode, this should actually be the logged in user's email, not the submitter contact

							ist: self.config.ist,

							organization: {

								name: self.config.process_config.name

							}

						},



						tpl_data = {

							jqxtheme: cargohub.jqxtheme,

							user_mode: self.config.user_mode,

							sender: contact_lines.submitter.contact, // Same as above

//							multiple_contacts: contact_lines_count > 1,

							header: $("#template-transfer-header").html().present(header_tpl_data)

						},

						i2m_config = { // Configuration sent to the setup function for the confirm window

							caller_module: self,

							width: 1010,

							height: 600,

							issue: self.config.stored_issue,

							org_id: self.config.process_config.org_id,

							setup_Finalize: function($container) {

								// Setup the action for "OK" button of the window (which should send

								$('#modal_transfer-message_ok', $container).off('click').on('click', function()

								{

									var message = cargohub.issue2message.get_Message();



									if(!message.recipient)

										return cargohub.alert("No recipient has been selected !");



									$.post("/issue?action=issue2message", {

										email: true,

										operation: "submit",

										transfer: true,

										evan: true,

										target_org_id: self.config.process_config.org_id,

										issue: self.config.stored_issue,

										message: JSON.stringify(message),

										sender_id: contact_lines.submitter.contact.id

									}, function(x) {

										// On success, x.id should contain the ID

										if(x.success) {



											if(self.config.submit_options) {

												// Submit from staff side ?

												document.location = "/record/process:" + self.config.stored_issue.track+ "#panels";

												// At this point, if the transfer was successful, depnding on the value of the submit option/finalize ( if it exists ),

												// The system should redirect to some location.

											} else {

												// No, just redirect to issue view (might request login if the submit was fully public).

												document.location = "/issue?action=view&track=" + self.config.stored_issue.track;

											}



											// For public submit, there is no finalize option - Instead, the system should redirect to the "issue view" zone, which should show the status "transferred"

										} else

											cargohub.its.http_fail(null, "Record.Submit::send_message");

									})

									.fail(cargohub.its.http_fail);

								});

							},

							sender: contact_lines.submitter.contact, // Same as above

							message_defaults: {},

						};



						// If the dropzone is used, the files for the dropzone are also the files used in the selector for the issue message builder

						if(self.config.attachments)

							i2m_config.attachments = self.config.attachments;



						// Multiple contacts ? Offer the possibility to select which ones will be included in the generated message

//						if(sizeof(contact_lines)>1) {

							i2m_config.contacts = [];

							for(c in contact_lines)

								i2m_config.contacts.push({

									id: contact_lines[c].contact.id,

									ashihara: contact_lines[c].label+ ': '+ contact_lines[c].contact.name// + " ("+ contact_lines[c].contact.email+ ")" // Might be too long and break the control

								});

//						}



						i2m_config.message_defaults.recipient = {

							css: { isstarget: self.config.process_config.org_id },

							initialize: JSON.stringify({

								method: "comm_target",

								org: self.config.process_config.org_id,

								rt_key: self.config.process_config.rt_key,

								dep_ids: self.config.process_config.dep_ids

							})

						};



						// Target organization has an autoselected outgoing contact ?

						// Auto contact is disabled - Instead, the dropdown opens automatically to show any potential contacts.

						// - User is not forced into using one, but they should be able to see them and click on one.

//						if(self.config.process_config.auto_contact_id && self.config.process_config.auto_contact) {

//							i2m_config.message_defaults.recipient.contact_id = self.config.process_config.auto_contact_id;

//							i2m_config.message_defaults.recipient.combobox_value = self.config.process_config.auto_contact.name;// + " <"+self.config.process_config.auto_contact.email+">"

//						};



						if(self.config.process_config.real_attach)

							i2m_config.files_mode = true;



						cargohub.its.open_Modal_Window('transfer-message', i2m_config.width, i2m_config.height, "<b>STEP 2</b> - Send your data via E-mail to <b>"+ self.config.process_config.name+ "</b>",

							$("script#template-issue2message_builder").html().present(tpl_data), cargohub.issue2message.setup_MessageBuilder, i2m_config);

						break;



					case 'transfer_success':



						break;



					// There was an error in relation to post-verify processing ( some data is missing or there's a configuration error )

					case 'error':

					case 'transfer_error':

						cargohub.alert(self.config.process_config.error, "Error reported by server");

						break;

				}

			}



			self.open_css_key = null; // Indicates which contact search system is currently open on the page. Used by the global focus to determine which one to close.



			// Global focus event related to contacts search system

			// -- When something is focused outside the active CSS, it is closed

			$(document).on('focus', '*', function(e) {

				e.stopPropagation();

				if(!self.open_css_key || e.target.id == self.open_css_key || $(e.target).parents('#'+self.open_css_key).length || $(e.target).hasClass('jqx-listbox'))

					return; // Do not attempt to close when target is the master container itself, or anything inside it, to prevent immediate reopening when clicking in the contact bar itself, or when there is nothing to close



				cargohub.contact_search.close(self.open_css_key);

			});

		}

	});

});

function checkWhatHappended(){

	var selectedVal=$("input[name='xfd[pstage]']:checked").val();

	var whatHappened=$("textarea[name='xfd[what_happened]']").val().trim();

	if(selectedVal=='prel'&&whatHappened!=""){	

		$(".claim_Notice_Detail").show();

	}else{

		$(".claim_Notice_Detail").hide();

	}

}

 function fetchapidata()
                {

                    var selectedPrefixVal = $("input[name='xfd[mawb][p]']").val();
                    var selectedSuffixVal = $("input[name='xfd[mawb][s]']").val();
                    if (selectedPrefixVal == "" || selectedSuffixVal == "") {
                        return false;
                    } else
                    {
                        $.post("?action=fetchawbdata", {
                            prefix: selectedPrefixVal,
                            suffix: selectedSuffixVal

                        }, function (x) {
                            if (x.AWBInfo) {
                                Pieces = x.AWBInfo[0]['PiecesCount'];
                                ChargedWeight = x.AWBInfo[0]['ChargedWeight'];
                                GrossWeight = Pieces = x.AWBInfo[0]['GrossWeight'];
                               var OriginCode = x.AWBInfo[0]['OriginCode'];
                               var  DestinationCode = x.AWBInfo[0]['DestinationCode'];
							   var shippingAgentCode = x.AWBInfo[0]['sac'];
							   var shippingAgentName = x.AWBInfo[0]['san'];
//IWI Invoice Dispute add form changes 13092024 start
console.log('OriginCode',OriginCode);
//$("div[data-id='#xfd_orig_location']>div>div>div>input[type='textarea").val(OriginCode);
//$("div[data-id='#xfd_orig_location']>div>div>div>input[type='textarea").keyup();



                                $.post("/issue?action=suggest_airport", {
                                    q: OriginCode
                                }, function (x) {
//                                            console.log(x[0]['id']);
//                                            console.log(x[0]['text']);
                                    $("div[data-id='#xfd_orig_location']>div>div>div>input[type='textarea").val(x[0]['text']);
                                    $("#xfd_orig_location").val(x[0]['id']);
                                    $("#xfd_orig_location_text").val(x[0]['text']);


                                });
                                $.post("/issue?action=suggest_airport", {
                                    q: DestinationCode
                                }, function (x) {
                                    $("div[data-id='#xfd_dest_location']>div>div>div>input[type='textarea").val(x[0]['text']);
                                    $("#xfd_dest_location").val(x[0]['id']);
                                    $("#xfd_dest_location_text").val(x[0]['text']);


                                });

 var comboid = $("div[data-id='#xfd_orig_location']").attr('aria-activedescendant');
 
$("div[data-id='#xfd_orig_location']>div>div>div>input[type='textarea").on('keyup', function () {
                // Get the current value of the textarea (or any other logic you need)
                var currentText = $(this).val();
                //document.getElementById('test').getAttribute('aria-expanded')

        
                // Set the value in jqxComboBox
                // For this example, we assume the value is directly usable
                $("div[data-id='#xfd_orig_location']").jqxComboBox('val', currentText);
            });

 //$("#jqxWidgetd800dc93").jqxComboBox({ showArrow: false });
//$("#listitem0innerListBoxjqxWidget3a6d23e0").click();

//IWI Invoice Dispute add form changes 13092024 end

                                $("#xfd_orig_location").val(OriginCode);

                                $("input[name='xfd[pieces]").val(Pieces);
                                $("input[name='xfd[cwt]']").val(ChargedWeight);
                                $("input[name='xfd[g_weight]']").val(GrossWeight);
								$("input[name='xfd[san]']").val(shippingAgentName);
								$("input[name='xfd[sac]']").val(shippingAgentCode);

                                $("#xfd_orig_location").jqxComboBox('selectItem', OriginCode);
                                $("#jqxWidgetcdd6204e").jqxComboBox('selectItem', OriginCode);
                                $("input[name='xfd[dest_location]']").jqxComboBox('selectItem', DestinationCode);


                            } else {
                                cargohub.alert("AWB data Not Found", "Message from server", "events.png");
                            }
                        });

                    }



                }



function record_type_checker(){

	var rt_ty =$('#record-type div a').text();

	var output;

	if(rt_ty === "Quality Event"){

		output = 1225;

	}else{

		output = 3;

	}

	return output;

}



function copyttltoval_old() {
 
    // Retrieve the container for checkboxes or inputs
    var reasonContainer = document.getElementById("dropdownlistContentissue_group_combo_event");
    var namettl = "";
    var namettl1 = "";

    if (reasonContainer) {
        var checkboxes = reasonContainer.querySelectorAll("input[type='checkbox']:checked");
        var selectedValues = [];
        var selectedLabels = [];

        // Loop through all checked checkboxes and get their values and labels
        checkboxes.forEach(function(cb) {
            selectedValues.push(cb.value);  // Get the value of the checkbox
            selectedLabels.push(cb.parentElement.textContent.trim());  // Get the label (UI)
        });

        // Join the selected values into a comma-separated string
        namettl = selectedValues.join(",");
        namettl1 = selectedLabels.join(", ");
    }

    // Update the hidden input field with selected values
    var categoryEventInput = document.getElementById("category_event");
    if (categoryEventInput) {
        categoryEventInput.value = namettl;
    }

    // Update the visible UI with the selected checkboxes' labels
    var eventReasonDisplay = document.getElementById("eventReason");
    if (eventReasonDisplay) {
        if (namettl1) {
            // If there are selected values, display them
            eventReasonDisplay.innerHTML = "Event reason: <span style='color: blue;'>" + namettl1 + "</span>";
        } 
//        else {
//            // If no checkboxes are selected, display a default message
//            eventReasonDisplay.innerHTML = "Event reason: <span style='color: blue;'>None selected</span>";
//        }
    }

    // Optionally update other fields if needed (for example, training TTL)
    var trainingInput = document.getElementById("xfd_training_ttl");
    if (trainingInput) {
//        trainingInput.value = namettl1;//IWI Training Scheduler Training Title issue fix
    }

    // Optionally handle the event aspects section if relevant (you can uncomment this if needed)
    /*
    var element2 = document.getElementById("dropdownlistContentissuecombo2");
    var namettl2 = "";

    if (typeof(element2) !== 'undefined' && element2 !== null) {
        namettl2 = element2.getElementsByTagName('input')[0]?.value || "";
    }

    if (namettl2 !== "") {
        var eventAspectsEl = document.getElementById("eventAspects");
        if (eventAspectsEl != null) {
            eventAspectsEl.innerHTML = "Event Aspects: <span style='color: blue;'>" + namettl2 + "</span>";
        }
    }
    */
}




function copyttltoval() {
var element =  document.getElementById("dropdownlistContentissue_group_combo");

var element1 =  document.getElementById("dropdownlistContentissue_group_combo");

var element2 =  document.getElementById("dropdownlistContentissuecombo2");



if (typeof(element) != 'undefined' && element != null)
{
  var namettl = document.getElementById("dropdownlistContentissue_group_combo").getElementsByTagName('input')[0].value;
}

if (typeof(element1) != 'undefined' && element1 != null)
{
  var namettl1 = document.getElementById("dropdownlistContentissue_group_combo").getElementsByTagName('input')[0].value;
}

if (typeof(element2) != 'undefined' && element2 != null)
{
  var namettl2 = document.getElementById("dropdownlistContentissuecombo2").getElementsByTagName('input')[0].value;
}
	
	
	
	
	
	
	if (namettl2 != "") {
	element1 = document.getElementById('eventAspects');
				if(element1!=null)
				document.getElementById('eventAspects').innerHTML = "Event Aspects: <span style='color: blue;'>" + namettl2 + "</span>";
	
	}
	
	if (namettl1 != "") {
	 
		
		element = document.getElementById('category_event');
			if(element!=null)
				document.getElementById('category_event').value = namettl;
				element1 = document.getElementById('eventReason');
				if(element1!=null)
				document.getElementById('eventReason').innerHTML = "Event reason: <span style='color: blue;'>" + namettl1 + "</span>";
	}
	if (namettl != "") {
		// Update the hidden input for category_event
		console.log(" name",namettl);
		element1 = document.getElementById('xfd_training_ttl'); 
		if(element1!=null)
		document.getElementById('xfd_training_ttl').value = namettl;
		
	 
	}
	
}
function updateEventAspects() {
    if (typeof (document.getElementById("dropdownlistContentissuecombo2")) != 'undefined' && document.getElementById("dropdownlistContentissuecombo2") != null)
    {
        if (document.getElementById('eventAspects') != null) {
            const eventAspects = document.getElementById("dropdownlistContentissuecombo2").getElementsByTagName('input')[0].value;
            if (eventAspects) {
            document.getElementById('eventAspects').innerHTML = "Event Aspects: <span style='color: blue;'>" + eventAspects + "</span>";
        }
        }
    }
}

 setInterval(copyttltoval_old,1000);
 setInterval(updateEventAspects,1000);


function copyeventtoval(){
        const event_val = $('#dropdownlistContentissue_group_combo_event input:checked').map(function() {
    return $(this).val();
  }).get().join(',');

    console.log('event_val',event_val);
    $('#event_result').val(event_val);
}




// Cargo Claim Steps function

  function showStep(step) {

    for (let i = 1; i <= 6; i++) {

      const content = document.querySelector(`#content${i}`);

       const btns = document.querySelector(`#btn${i}`);

			if(content !== null && btns!== null){

				if (i === step) {

                    content.style.display = "block";

					btns.style.backgroundColor="#239B56";

					btns.style.color ="#ffffff";

					

                } else {

					

					btns.style.backgroundColor="#ffffff";

					btns.style.color ="#000";					

					content.style.display = "none";	

                }

			}

                

    }

  }

 window.onload =showStep(1);



   // Cargo Claim Steps function


// 26092024   train_scheduler_classroom_type addded by iwi start 
   if ($('#train_scheduler_classroom_type').length) {
	$('input[name="xfd[train_loc]"]').on('click', function() {
		if ($(this).val() === "Classroom") {
			$("#train_scheduler_classroom_type").show();
		} else {
			$('input[name="xfd[classroom_type]"]').prop('checked', false);
			$("#train_scheduler_classroom_type").hide();
		}
	});
}
// 26092024   train_scheduler_classroom_type addded by iwi end

//right side box added for quality_event - 23-10-24-start
function updateEventDate() {
	var eventDate = document.getElementById('e_date').value;  
    document.getElementById('eventDateHiddenInput').value = eventDate;  
    var dateDiv = document.getElementById('eventDate');
    dateDiv.innerText = "Event Date: "; 
    var dateSpan = document.createElement('span');
    dateSpan.style.color = 'blue'; 
    dateSpan.innerText = eventDate; 
    dateDiv.appendChild(dateSpan);
}
function updateEventTime() {
	var eventTime = document.getElementById('e_time').value;  
    document.getElementById('eventTimeHiddenInput').value = eventTime;  
    var timeDiv = document.getElementById('eventTime');
    timeDiv.innerText = "Event Time: "; 
    var timeSpan = document.createElement('span');
    timeSpan.style.color = 'blue'; 
    timeSpan.innerText = eventTime; 
    timeDiv.appendChild(timeSpan); 
}
function updateTitle(input) {  
	var customValue = input.value;
    document.getElementById('eventTitleHiddenInput').value = customValue;
    var titleDiv = document.getElementById('eventTitle');
    titleDiv.innerText = "Title: "; 
    var valueSpan = document.createElement('span');
    valueSpan.style.color = 'blue'; 
    valueSpan.innerText = customValue;
    titleDiv.appendChild(valueSpan);
}
function updateDisplayereason() {
    var selectElement = document.getElementById('category_event_result'); 
    var selectedValues = Array.from(selectElement.selectedOptions).map(option => option.value); 
    console.log("Selected reason: ", selectedValues);
    document.getElementById('eventReason').innerText = "Selected Options: " + selectedValues.join(', ');
}


function updateCargoEvent(radio) {

	var selectedCargoValue = radio.value;
    document.getElementById('selectedCargoEvent').value = selectedCargoValue;
    var cargoDiv = document.getElementById('cargoRelatedEvent');
    cargoDiv.innerText = "Cargo Event: "; 
    var cargoSpan = document.createElement('span');
    cargoSpan.style.color = 'blue'; 
    cargoSpan.innerText = selectedCargoValue; 
    cargoDiv.appendChild(cargoSpan);
}

function updateCategoryEvent(dropdown) {
    var inputValue = dropdown.querySelector('input').value; 
    if (inputValue) {
        document.getElementById('category_event').value = inputValue;
        document.getElementById('eventcategory').innerHTML = "Category: <span style='color: blue;'>" + inputValue + "</span>";
        console.log("Selected category value: ", inputValue);
    } else {
        console.log("Selected category value is undefined or empty");
    }
}
function updateStationEvent(searchbox) {
	var selectedStationValue = searchbox.value || "Selected Station"; 
    document.getElementById('category_station').value = selectedStationValue; 
    document.getElementById('stationDisplay').innerHTML = "Reporting station: <span style='color: blue;'>" + selectedStationValue + "</span>"; 
}

function updateEventLocationValue(input) {
    
	var customValue = input.value;
    document.getElementById('eventLocationHiddenInput').value = customValue;  
    var locationDiv = document.getElementById('eventLocation');
    locationDiv.innerText = "Event location: "; 
    var locationSpan = document.createElement('span');
    locationSpan.style.color = 'blue'; 
    locationSpan.innerText = customValue; 
    locationDiv.appendChild(locationSpan);
}

function updateWhatHappened(textarea) {
	var impactValue = textarea.value;
    document.getElementById('eventDescriptionHiddenInput').value = impactValue;  
    var descriptionDiv = document.getElementById('whatHappened');
    descriptionDiv.innerText = "What happened: "; 
    var impactSpan = document.createElement('span');
    impactSpan.style.color = 'blue'; 
    impactSpan.innerText = impactValue; 
    descriptionDiv.appendChild(impactSpan);
}

function updateRecoveryDescrip(textarea) {
    var impactValue = textarea.value;
    document.getElementById('recoveryDescriptionHiddenInput').value = impactValue;  
    var descriptionDiv = document.getElementById('recoveryDescription');
    descriptionDiv.innerText = "Recovery Description: "; 
    var recoverySpan = document.createElement('span');
    recoverySpan.style.color = 'blue'; 
    recoverySpan.innerText = impactValue; 
    descriptionDiv.appendChild(recoverySpan);
}

function updateRadioValue(radio) {
    
	var selectedValue = radio.value;
    document.getElementById('severityHiddenInput').value = selectedValue;  
    var severityDiv = document.getElementById('severityResult');
    severityDiv.innerText = "How severe is the outcome of the event that occurred? "; 
    var severitySpan = document.createElement('span');
    severitySpan.style.color = 'blue'; 
     var selectedText = radio.nextSibling.textContent.trim(); 
    severitySpan.innerText = selectedText; 
    severityDiv.appendChild(severitySpan);
}

function updateProbabilityValue(radio) {
    var selectedValue = radio.value;
    document.getElementById('probabilityHiddenInput').value = selectedValue; 
    var probabilityDiv = document.getElementById('probabilityResult');
    probabilityDiv.innerText = "What is the chance that the same event could happen again? "; 
    var probabilitySpan = document.createElement('span');
    probabilitySpan.style.color = 'blue'; 
    var selectedText = radio.nextSibling.textContent.trim(); 
    probabilitySpan.innerText = selectedText; 
    probabilityDiv.appendChild(probabilitySpan);
}

function updateEventTheory(radio) {
	var selectedValue = radio.value; 
    document.getElementById('selectedevent').value = selectedValue; 
    var eventTheoryDiv = document.getElementById('eventTheoryResult');
    eventTheoryDiv.innerText = "Event Type: "; 
    var eventTypeSpan = document.createElement('span');
    eventTypeSpan.style.color = 'blue'; 
    eventTypeSpan.innerText = selectedValue; 
    eventTheoryDiv.appendChild(eventTypeSpan);
}
function updateDisplayRisk() {
	var rNumValue = document.getElementById('r_num').value; 
    var currencySelect = document.querySelector('.currency_select_secondary'); 
    var selectedCurrency = currencySelect.options[currencySelect.selectedIndex].text; 

    document.getElementById('rNumHiddenInput').value = rNumValue;
    document.getElementById('currencyHiddenInput').value = selectedCurrency;

    var displayDiv = document.getElementById('displayResult');
    displayDiv.innerHTML = "Risk / Claim (indication): <span style='color: blue;'>" + rNumValue + "</span>  <span style='color: blue;'>" + selectedCurrency + "</span>";
}
function updateBusinessTheory(radio) {
    var selectedValue = radio.value; 
    document.getElementById('selectedBusiness').value = selectedValue; 

    var eventBusinessDiv = document.getElementById('eventBusinessResult');
    eventBusinessDiv.innerText = "Business relation: "; 

    var businessRelationSpan = document.createElement('span');
    businessRelationSpan.style.color = 'blue'; 
    businessRelationSpan.innerText = selectedValue; 
    eventBusinessDiv.appendChild(businessRelationSpan);
}
function updateCustomValue(input) {
	var customValue = input.value; 
    document.getElementById('customHiddenInput').value = customValue; 
    var customValueDiv = document.getElementById('customValueResult');
    customValueDiv.innerText = "Relation name: "; 
    var relationNameSpan = document.createElement('span');
    relationNameSpan.style.color = 'blue'; 
    relationNameSpan.innerText = customValue; 
    customValueDiv.appendChild(relationNameSpan);
}
function updateImpactValue(textarea) {
    var impactValue = textarea.value; 
    document.getElementById('impactHiddenInput').value = impactValue; 
    var impactValueDiv = document.getElementById('impactValueResult');
    impactValueDiv.innerText = "Impact Description: "; 
    var impactValueSpan = document.createElement('span');
    impactValueSpan.style.color = 'blue'; 
    impactValueSpan.innerText = impactValue; 
    impactValueDiv.appendChild(impactValueSpan);
}
//right side box added for quality_event - 23-10-24-end

//validation on next button -23-10-24-start
// function validateMandatoryFieldsstep2() {
//     var titleEventInput = document.getElementById('title_event');
//     var titleError = document.getElementById('titleError');
    
//      var categoryEventInput = document.getElementById('issue_group');
//     var categoryError = document.getElementById('groupError');


    
    
//     var inputxfd_scheduled_date = document.getElementById('inputxfd_scheduled_date');

   
    

//    if(titleEventInput){
//     if (titleEventInput.value.trim() === "") {
//         titleError.style.display = "block";
//         titleEventInput.classList.add("field_error");  
//         return false;  
//     }
//      else {
      
//         titleError.style.display = "none";
//         titleEventInput.classList.remove("field_error");
          
//     }

//    } 

   
   
//     if(categoryEventInput && categoryError){
//     if (categoryEventInput.value.trim() === "") {
    
//         categoryError.style.display = "block";
//         categoryEventInput.classList.add("field_error");  
//         return false;  
//     }
//      else {
      
//         categoryError.style.display = "none";
//         categoryEventInput.classList.remove("field_error");
         
//     }
//    } 
   
   
//    if(inputxfd_scheduled_date){
//     if (inputxfd_scheduled_date.value.trim() === "") {
//         var parentDiv = inputxfd_scheduled_date.parentElement;
//         parentDiv.style.border = '1px solid red';
//         inputxfd_scheduled_date.focus();
//         return false;  
//     } else {     
//         return true;  
//     }
//    }

// 		const event_val = $('#dropdownlistContentissue_group_combo_event input:checked')
// 		.map(function () {
// 			return $(this).val();
// 		}).get().join(',');

// 		$('#event_result').val(event_val); 

// 		if (!event_val) {
// 		// Show red border
// 		$('#dropdownlistContentissue_group_combo_event').css('border', '1px solid red');
// 		return false;
// 		}else{
// 		document.getElementById('dropdownlistContentissue_group_combo_event').style.border = '';

// 		}


// var eventDateValue = $('#e_date').jqxDateTimeInput('val');
// if (!eventDateValue || eventDateValue.trim() === "") {
//     document.getElementById('e_date').style.border = '1px solid red';
//     return false;
// } else {
//     document.getElementById('e_date').style.border = '';
//     return true;
// }


   
//    return true;
   
   
// }

// function validateMandatoryFieldsstep2() {
//     var titleEventInput = document.getElementById('title_event');
//     var titleError = document.getElementById('titleError');
    
//      var categoryEventInput = document.getElementById('issue_group');
//     var categoryError = document.getElementById('groupError');

// 	// var areaError = document.getElementById('areaError');
    
    
//     var inputxfd_scheduled_date = document.getElementById('inputxfd_scheduled_date');
   
    

//    if(titleEventInput){
//     if (titleEventInput.value.trim() === "") {
//         titleError.style.display = "block";
//         titleEventInput.classList.add("field_error");  
//         return false;  
//     }
//      else {
      
//         titleError.style.display = "none";
//         titleEventInput.classList.remove("field_error");
          
//     }

//    } 

// //    var selectedAreaRadio = document.querySelector('input[name="area"]:checked').value;
// //    if (!selectedAreaRadio) {
// // 	   areaError.style.display = "block";
// // 	   return false;
// //    } else {
// // 	   areaError.style.display = "none";
// //    }
   
   
//     if(categoryEventInput && categoryError){
//     if (categoryEventInput.value.trim() === "") {
    
//         categoryError.style.display = "block";
//         categoryEventInput.classList.add("field_error");  
//         return false;  
//     }
//      else {
      
//         categoryError.style.display = "none";
//         categoryEventInput.classList.remove("field_error");
         
//     }
//    } 
   
//    if(inputxfd_scheduled_date){
//     if (inputxfd_scheduled_date.value.trim() === "") {
//         var parentDiv = inputxfd_scheduled_date.parentElement;
//         parentDiv.style.border = '1px solid red';
//         inputxfd_scheduled_date.focus();
//         return false;  
//     } else {     
//         return true;  
//     }
//    }
   
//    return true;
   
   
// }
function validateMandatoryFieldsstep2() {
    var titleEventInput = document.getElementById('title_event');
    var titleError = document.getElementById('titleError');
    
     var categoryEventInput = document.getElementById('issue_group');
    var categoryError = document.getElementById('groupError');

	var eventDateValue = document.getElementById('e_date');
    
    
    var inputxfd_scheduled_date = document.getElementById('inputxfd_scheduled_date');
    
	var genDamageDateValue = document.getElementById('inputxfd_date');
	var areaRadios = document.getElementsByName('xfd[area]');

		

   if(eventDateValue){
	// console.log(eventDateValue);
	 var eventDateValue1 = $('#e_date').jqxDateTimeInput('val');
	 if (!eventDateValue1 || eventDateValue1.trim() === "") {
			document.getElementById('e_date').style.border = '1px solid red';
			return false;
		} else {
			document.getElementById('e_date').style.border = '';
		}
   }	
   

   if(titleEventInput){
    if (titleEventInput.value.trim() === "") {
        titleError.style.display = "block";
        titleEventInput.classList.add("field_error");  
        return false;  
    }
     else {
      
        titleError.style.display = "none";
        titleEventInput.classList.remove("field_error");
          
    }

   } 
   
    if(categoryEventInput && categoryError){
    if (categoryEventInput.value.trim() === "") {
    
        categoryError.style.display = "block";
        categoryEventInput.classList.add("field_error");  
        return false;  
    }
     else {
      
        categoryError.style.display = "none";
        categoryEventInput.classList.remove("field_error");
         
    }
   } 
   
   if(document.getElementById('dropdownlistContentissue_group_combo_event')){
    var eventReason_val = $('#dropdownlistContentissue_group_combo_event input:checked').length;
    if (eventReason_val <= 0) {
        $('#dropdownlistContentissue_group_combo_event').css('border', '1px solid red');
        return false;
    } else {
        document.getElementById('dropdownlistContentissue_group_combo_event').style.border = '';
    }
   }
		
   if(genDamageDateValue){
	var genDamageDateValue = $('#xfd_date').jqxDateTimeInput('val');
	if (!genDamageDateValue || genDamageDateValue === "" ) {
		$('#xfd_date').css('border', '1px solid red');
		$('#xfd_time').css('border', '1px solid red');
		return false;
	} else {
		$('#xfd_date').css('border', '');
		$('#xfd_time').css('border', '');
	}
   }	

  
    if(areaRadios.length>0){
		var areaRadios = document.getElementsByName('xfd[area]');
		var areaSelected = false;

		for (var i = 0; i < areaRadios.length; i++) {
			if (areaRadios[i].checked) {
				areaSelected = true;
				break;
			}
		}

		if (!areaSelected) {
			if (areaRadios.length > 0) {
				var parentTd = areaRadios[0].closest('td');
				if (parentTd) {
					parentTd.style.border = '1px solid red';
				}
			}

			return false;
		} else {
			if (areaRadios.length > 0) {
				var parentTd = areaRadios[0].closest('td');
				if (parentTd) {
					parentTd.style.border = '';
				}
			}
		}
	}


   
   if(inputxfd_scheduled_date){
    if (inputxfd_scheduled_date.value.trim() === "") {
        var parentDiv = inputxfd_scheduled_date.parentElement;
        parentDiv.style.border = '1px solid red';
        inputxfd_scheduled_date.focus();
        return false;  
    } else {     
        return true;  
    }
   }
   
   return true;
   
   
}
//function validateMandatoryFieldsstep3() {
//	var wHappenInput = document.getElementById('w_happen');
//    var wHappenError = document.getElementById('wHappenError');
//	if(wHappenInput){
//	if (wHappenInput && wHappenInput.value.trim() === "") {
//        wHappenError.style.display = "block";
//        wHappenInput.classList.add("field_error");
//        isValid = false;
//    } else {
//        wHappenError.style.display = "none";
//        wHappenInput.classList.remove("field_error");
//		return true; 
//    }
//  }else{
//	return true;
// }
//}


function validateMandatoryFieldsstep3() {
    var isValid = true;

	var eventDateValue = document.getElementById('carryDate'); //  Cargoclaim add post page modification
	var airwaybill = document.getElementById('prefix_id');

	if(airwaybill){
		var airwaybillprefixValue = $('#prefix_id').val();
		var airwaybillsufixValue = $('#sufix_id').val();

		if (!airwaybillprefixValue || airwaybillprefixValue.trim() === "" && !airwaybillsufixValue || airwaybillsufixValue.trim() === "") {
			$('#prefix_id').css('border', '1px solid red');
			$('#sufix_id').css('border', '1px solid red');
			isValid = false;
		} else {
			$('#prefix_id').css('border', '');
			$('#sufix_id').css('border', '');
		}
	}
// Cargoclaim add post page modification start
    if(eventDateValue){
    var awbDateValue = $('#carryDate').val();
    if (!awbDateValue || awbDateValue.trim() === "") {
        $('#carryDate').css('border', '1px solid red');
        isValid = false;
    } else {
        $('#carryDate').css('border', '');
    }
}
// Cargoclaim add post page modification end


		var airlineEventInput = document.getElementById('issue_group');
		var airlineError = document.getElementById('airlineError');

		
		if(airlineEventInput && airlineError){
			if (airlineEventInput.value.trim() === "") {
			
				airlineError.style.display = "block";
				airlineEventInput.classList.add("field_error");  
				return false;  
			}
			 else {
			  
				airlineError.style.display = "none";
				airlineEventInput.classList.remove("field_error");
				 
			}
		   } 


		  

		var originInput = document.getElementById('xfd_orig_loc');
		var originError = document.getElementById('originError');

		
		if(originInput && originError){
			if (originInput.value.trim() === "") {
			
				originError.style.display = "block";
				originInput.classList.add("field_error");  
				return false;  
			}
			 else {
			  
				originError.style.display = "none";
				originInput.classList.remove("field_error");
				 
			}
		   } 
		 
		   
		   var destinationInput = document.getElementById('xfd_dest_loc');
		   var destinationError = document.getElementById('destinationError');

		
		if(destinationInput && destinationError){
			if (destinationInput.value.trim() === "") {
			
				destinationError.style.display = "block";
				destinationInput.classList.add("field_error");  
				return false;  
			}
			 else {
			  
				destinationError.style.display = "none";
				destinationInput.classList.remove("field_error");
				 
			}
		   } 
		    

    // What Happened validation
    var wHappenInput = document.getElementById('w_happen');
    var wHappenError = document.getElementById('wHappenError');

    if (wHappenInput) {
        var wHappenValue = wHappenInput.value.trim();
        if (wHappenValue === "") {
            wHappenError.style.display = "block";
            wHappenInput.classList.add("field_error");
            isValid = false;
        } else {
            wHappenError.style.display = "none";
            wHappenInput.classList.remove("field_error");
        }
    }

    console.log("Validation Result:", isValid);
    return isValid;
}
 function validateMandatoryFieldsstep4() {

    const allInputs = $('#dmg_pieces, #dmg_weight, #loss_pieces, #loss_weight, #delay_pieces, #delay_weight, #ploss_pieces, #ploss_weight, #pilf_pieces, #pilf_weight');

    const hasValue = allInputs.filter(function () {
        return $.trim($(this).val()) !== '';
    }).length > 0;

    if (!hasValue) {
        allInputs.css('border', '1px solid red');
        return false;
    }

    allInputs.css('border', '');
    return true;
}
//validation on next button -23-10-24-end
   // 20022024 create by iwi | start    

	function cargoClaimStep(step) {

     // new ui for quality_evet 23-10-24-start
		if (step === 2) { 
                    
//                    Cargoclaim add post page modification start
                    if ($("input[name='xfd[subm_type]']").length > 0) {
                    if ($("input[name='xfd[subm_type]']:checked").length === 0) {
                        $("input[name='xfd[subm_type]']").closest("label").addClass("req_error");
                        return;  
                    }else{
                        $("input[name='xfd[subm_type]']").closest("label").removeClass("req_error");
                    }
                }
//                Cargoclaim add post page modification end
			if (!validateMandatoryFieldsstep2()) {
				return;  
			}
		}
		if (step === 3) { 
			if (!validateMandatoryFieldsstep3()) {
				return;  
			}
		}
		if (step === 4) { 
			if($("#SubmitClaimAspects").length> 0){
				if (!validateMandatoryFieldsstep4()) {
					return;  
				}
			}
		}
		
		if (step === 1) {
			$("#infoBox").show();
			$("#additionalInfoBox").hide();
			$("#riskBox").hide();
		}
		if (step === 2) { 
			$("#infoBox").show();
			$("#additionalInfoBox").show();
			$("#riskBox").hide();
		}
		if (step === 3) { 
			$("#infoBox").show();
			$("#additionalInfoBox").show();
			$("#riskBox").show();
		}
		if (step === 4) { 
			$("#infoBox").show();
			$("#additionalInfoBox").show();
			$("#riskBox").show();
		}
      
		// new ui for quality_evet 23-10-24-end 

		for (let i = 1; i <= 6; i++) {

			const content = document.querySelector(`#content${i}`);

			const stepposition = document.querySelector(`.step-position${i}`);

			const btns = document.querySelector(`#btn${i}`);

			if(content !== null && btns!== null){

		

				content.style.display = "none";	

				if(i > step &&  i != step){

					stepposition.classList.add("notCompleted");

				}else if(i < step &&  i != step){

					stepposition.classList.add("completed");

				} else if (i === step) {

					stepposition.classList.add("active");

					btns.classList.add("active");

					content.style.display = "block";

				}

			}  

		}

	}

	window.onload =cargoClaimStep(1);

	function cargoClaimPreviousStep(currentStep) {

		var prevStep = currentStep - 1;

		$('#content'+prevStep).css("display", "block");

		$('.step-position'+prevStep).removeClass('completed');



		$('#content'+currentStep).css("display", "none");

		$('.step-position'+currentStep).removeClass('active');

		$('#btn'+currentStep).removeClass('active');

	}



	// Get all step buttons

const stepButtons = document.querySelectorAll('.step-btn');



// Add click event listener to each button

stepButtons.forEach(button => {

    button.addEventListener('click', () => {

        // Get the step number from data-step attribute

        const stepNumber = button.getAttribute('data-step');

        

        // Call a function to handle navigation based on the step number

        navigateToStep(stepNumber);

    });

});





function navigateToStep(stepNumber) {

   

    switch(stepNumber) {

        case '1':

            // Navigate to step 1 page

            cargoClaimStep(1);

            break;

        case '2':

            // Navigate to step 2 page

            cargoClaimStep(2);

            break;

        case '3':

            // Navigate to step 3 page

            cargoClaimStep(3);

            break;

        case '4':

            // Navigate to step 4 page

            cargoClaimStep(4);

            break;

        default:

            // Handle default case

            break;

    }

}



	window.onload =setTimeout(mandatoryFiled,2000);

	function mandatoryFiled(){

		console.log('ready');

		$("<span class='mandatory-field'>*</span>").insertAfter("#contact-submitter .clabel label");



		$("#submit-proceed-box table").css("display", "none");

	}

	// 20022024 create by iwi | end

//	IWI Invoice Dispute add form changes 13092024 start
	$(document).on('change','#xfd_rate_app_curr',function(){
            $('#xfd_frei_am_curr,#xfd_oth_charge_curr,#xfd_vat_awb_curr,#xfd_ttl_awb_curr,#xfd_discrep_curr,#xfd_agp_curr',).val($(this).val());           
        });
//        IWI Invoice Dispute add form changes 13092024 end


//$(function () {
//
//  console.log('test');
//
//});


	// 21112024 Training Scheduler Training Date section work start
	// if($("#xfd_date_train").length) {
	// 	$(document).on('change','#xfd_date_train',function(){
	// 		let minDate = $(this).val();// new Date();
	// 		$("#xfd_date_end").jqxDateTimeInput('setDate',minDate);
	// 		$("#xfd_train_exp").jqxDateTimeInput('setDate',minDate);
	// 		//$("#datetimeInput").jqxDateTimeInput('min', minDate);
	// 	});
	// }
	// custom exp date enhancement start
function setTrainingExpiry(yearsToAdd) {

    let minDate = $("#xfd_date_train").val();
    if (!minDate) return;

    let expDate = new Date(minDate);
    expDate.setFullYear(expDate.getFullYear() + yearsToAdd);

    $("#xfd_train_exp").jqxDateTimeInput('setDate', expDate);
}

if ($("#xfd_date_train").length) {

    $(document).on('change', '#xfd_date_train', function () {

        let minDate = $(this).val();
        $("#xfd_date_end").jqxDateTimeInput('setDate', minDate);


        if ($('input[type="radio"][value="1y"]').is(':checked')) {
            setTrainingExpiry(1);
        }
        else if ($('input[type="radio"][value="2y"]').is(':checked')) {
            setTrainingExpiry(2);
        }
		 else if ($('input[type="radio"][value="cus"]').is(':checked')) {
            $("#xfd_train_exp").jqxDateTimeInput('setDate', null);
        }
    });

    
    $(document).on('change', 'input[type="radio"]', function () {

        if (!$("#xfd_date_train").val()) return;

        if ($(this).val() === '1y') {
            setTrainingExpiry(1);
        }
        else if ($(this).val() === '2y') {
            setTrainingExpiry(2);
        }
		 else if ($(this).val() === 'cus') {
            $("#xfd_train_exp").jqxDateTimeInput('setDate', null);
        }
    });
}
// custom exp date enhancement end
	// 21112024 Training Scheduler Training Date section work end
		



//Cargoclaim add post page modification start

$(document).on('change', 'input[name="xfd[dmg_repair]"]', function() {
    if ($(this).val() === "1") {
        $("#repair_amt_td").show();
    } else {
        $("#repair_amt_td").hide();
    }
});

$(document).ready(function () {
    $("input#carryDate").each(function () {
        $(this).data("calendar_config", {
            limit_past: true, 
            maxDate: new Date(), 
            width: "96%",   
        });

        cargohub.components.ui.datepicker_input.call(this);
    });

    $("input#xfd_pickup_date").each(function () {
        $(this).data("calendar_config", {
            limit_past: true, 
            maxDate: new Date(),
            width: "82%",   
        });

        cargohub.components.ui.datepicker_input.call(this);
    });
    
     $(".xfd_loc_search").each(function () {
        var $this = $(this), $assoc_id = $($this.data('id')), $assoc_text = $($this.data('id')+'_text'), q = $assoc_id.val(),
			// setup the suggestion source
			source_setup = new $.jqx.dataAdapter({
				datatype: "json",
				datafields: [
					{ name: 'id' },
					{ name: 'text' }
				],
				url: $this.data('sourceurl')
			}, {
				formatData: function (data) {
					if (q) {
						data.q = q;
						return data;
					}
				}
			}),
			// setup the combo search
			combo_setup = {
				theme: cargohub.jqxtheme,
				source: source_setup,
				remoteAutoComplete: true,
				minLength: 0,
				search: function(search)	// While typing...
				{
					if(search.length>=2) { // Search only when at least 2 characters are typed
						q = search;
						source_setup.dataBind();
					} else
						q = null;
				},
				valueMember: "id", displayMember: "text",
				width: $this.data('width')||360, height: 30,
				placeHolder: $this.data('placeholder')||"Type to search",
				autoDropDownHeight: true,
				dropDownWidth: $this.data('width')||360,
				animationType: $this.data('animation')||"none"
			}, change_on_select = null;

			// finally, prepare the combobox and events on it
			$this.jqxComboBox(combo_setup)
			/**
			 * Event - An item is selected in the combobox
			 */
			.on('select', function(event) { // value on select event
				if(event.args == undefined)
					return false;

				var item = $this.jqxComboBox('getItem', event.args.index);

				if(item != null && item.originalItem.id) {
					$assoc_id.val(item.originalItem.id);
					$assoc_text.val(item.originalItem.text);
					change_on_select = true;
				} else {
					$this.jqxComboBox('clearSelection');
					$assoc_id.val(0); $assoc_text.val('');
					return false;
				}
			})
                                .on('change', function (event) {
                                    var inputVal = $this.jqxComboBox('getText');
                                    var selectedIndex = $this.jqxComboBox('getSelectedIndex');

                                    // If there�s no selection and the input is not empty, clear it
                                    if (selectedIndex === -1 && inputVal !== '') {
                                        $this.jqxComboBox('clearSelection');
                                        $assoc_id.val(0);
                                        $assoc_text.val('');
                                    }

                                    change_on_select = false;
                                })
			.val($assoc_text.val()); // initial default, from the associated hidden text

			// Since version 3.3.0 of jqx, The Combobox doesn't databind() automatically, so do it if there's a default value
			if(q)
				source_setup.dataBind();

			// Add keyboard event to the INPUT inside the combobox
			$this.find("input.jqx-combobox-input").on('keyup', function(event) {
				if(event.keyCode == 13 || event.key == "Enter") {
					if($this.jqxComboBox("getSelectedIndex") == -1) {
						$this.jqxComboBox("selectIndex", 0);
						$this.jqxComboBox("close");
					}
				}
			});
    });
    
    
    $("#formal_heading").show();
    $("#fprRadio").hide();
    
});


$(document).on('change keyup','#xfd_claim_amt_amount',function(){
    
    if($(this).val()!=''){
         $('#fprRadio').show(); 
         $('#prelRadio').hide(); 
         $('[name="xfd[pstage]"][value="fpr"]').prop('checked', true).trigger('change');
         $("#row_claim_prelim").hide();$("#row_claim_final").show();$("#formalText").show();$("#prelText").hide();checkWhatHappended();
    }else{
        $('#prelRadio').show(); 
         $('#fprRadio').hide(); 
         $('[name="xfd[pstage]"][value="prel"]').prop('checked', true).trigger('change');
         $("#row_claim_prelim").show();$("#row_claim_final").hide();$("#prelText").show();$("#formalText").hide();checkWhatHappended();
    }
});

//Cargoclaim add post page modification end

