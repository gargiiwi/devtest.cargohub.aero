/**
 * This module manages the ability to send issue data as a message towards a contact
 * It is similar with a Message Compose inteface, with the difference that issue data is automatically added as part of the message.
 * - Used in submit section for direct transfer to another organization, or simply send the issue to an e-mail address, in overview section or maybe in the grid too (later)
 * - Allows composing a message using message templates (canned messages), and also attaching issue data
 *   - Primary (like priority, status, etc.)
 *   - Xfields
 *   - Files
 *   - Possibly other types of content
 * The module can be used either inside a jqxWindow, or directly on the page
 *
 * Requires jqxTooltip, jqxDropDownList, cargohub.contact_search
 */
cargohub.issue2message = donrey.BASE._extend(function() { 
	// private
	var self = this, c = 0, // counter
		$c = null, // The DOM container which contains all the controls
		params = null,
		modified = false,
		// The DataAdapter which will be used (and reused) by the canned messages selector when instanced
		canned_data_source = new $.jqx.dataAdapter({
			url: '/global/operations?action=get_canned',
			datatype: "json",
			datafields: [
				{"name": 'description'},
				{"name": 'title'},
				{"name": 'message'}
			]
		}),
		canned_selector = false, // Is the jqxDDL selector hidden or visible ?
		primary_recipient = null, primary_recipient_cssdata = null,
		recipient_css = {},
		recipient_initialize = null,
		email_cc_list = [], cc_id = 0, cc_length = 0, // Extra recipients ( CarbonCopy ), are contact records or email/name pairs

		/**
		 * Returns True if the email is already part of the cc_list, or false if the cc_list is empty or the entry is not a part
		 */
		check_cc_duplicate = function(email)
		{
			if(cc_length==0)
				return false;

			if($.inArray(email, email_cc_list) > -1)
				return true;

			return false;
		},

		emailpreview = false // E-mail preview modal ready?
	;
	this._public({
		/**
		 * Prepares this editor's internal variables. (Automatically called when this module is created.)
		 */
		init: function(controller)
		{
		},

		/**
		 * Initializes the sending by e-mail controls, including:
		 * - Recipient Contact Search (uses cargohub.contact_search)
		 * - Canned message selector ( a jqxDropDownList )
		 * - Subject and Content line ( filled when selecting a canned message )
		 *
		 * @param $container Container for the entire message builder form (must have the correct HTML in it already) - Note that there might be other sub-containers until the controls themselves
		 * (This module should support using the setup_MessageBuilder method as the "setup_callback" parameter for cargohub.its.open_Modal_Window()
		 * @param _params Parameters for widgets initialization
		 * - Contact list search parameters
		 * - Canned messages filter parameters
		 * - Preset target contact/subject/content
		 * - Allow editing the subject/message
		 * - Sender's CC address
		 * - (others?)
		 * Events:
		 * - "on_change": Builder content is changed (recipient, subject, body, data parts)
		 * - "on_recipient_change": Extra change event specific only for selecting the recipient (on_change event from cargohub.contact_search)
		 */
		setup_MessageBuilder: function($container, _params)
		{
			if(!$container || $container.length == 0)
				return false;

			$c = $("#message-builder", $container);
			params = _params;

			// Pre-initialization configuration

			if(!self.config.message_defaults)
				self.config.message_defaults = {};

			if(params.message_defaults)
				$.extend(self.config.message_defaults, params.message_defaults);

			if(self.config.message_defaults) {
				// Contact Search Scope is being customized
				if(self.config.message_defaults.recipient) {
					// Check if a contact has been preselected. This is a special mode that displays the to_name value in the To: line, but when clicking on it instead of attempting to search for the "Name", it will locate the contact directly by ID
					// -- However, if anything is typed in the box (value changes), the preselected contact will be reset and the normal contact search will apply
					if(self.config.message_defaults.recipient.contact_id) {
						primary_recipient_cssdata = self.config.message_defaults.recipient;
						primary_recipient = {
							id: self.config.message_defaults.recipient.contact_id
						};
					}

					// Contact Search Scope and Read Initialization?
					if(self.config.message_defaults.recipient.css)
						recipient_css = self.config.message_defaults.recipient.css;
					if(self.config.message_defaults.recipient.initialize)
						recipient_initialize = self.config.message_defaults.recipient.initialize;
				}
			}
			// Message primary recipient line, is handled by the "contact search system" plugin
			cargohub.contact_search.setup_CSS("message-recipient", "#message-recipient", {
				result_template: $("#template-contact-result").html(),
				placeholder: "(CLICK HERE to search for a recipient or to enter an E-mail address)",
				placeholder_addclass: "jqx-input-"+cargohub.jqxtheme,
				width: 542,
				// What is being shown and searched here ?
				css: JSON.stringify(recipient_css),
				initialize: recipient_initialize,
				on_focus: function(key) {
					// Slight delay (1s) to allow some focus events to happen before permitting the CSS to close
					setTimeout(function() {
						params.caller_module.open_css_key = key;
					}, 1000);
				},
				on_change: function(key, state) {
					modified = true;
					primary_recipient_cssdata = state;
				},
				on_close: function(key, recipient) {
					params.caller_module.open_css_key = null;
					primary_recipient = recipient;
				},
				autoopen: true
			}, primary_recipient_cssdata); // (inialization when default target contact is known)

			// The green + button - Disabled, really confusing.

			$("#recipient-add", $c).jqxTooltip({
				content: "Add carbon-copy recipients to your message",
				position: "bottom",
				opacity: 1
			})
			// Event: By clicking the (+) button the e-mail address which is currently in the Address input is added to a list shown below
			.on("click", function(event)
			{
				var email = prompt("Enter e-mail of your extra recipient");
				if(!email)
					return;
				if(!validate_email(email))
					return cargohub.alert("This is not a valid E-mail address, please try again.");

				// Visually display the new e-mail in the list
				if(cc_length == 0)
					$("div#extra-recipients", $c).fadeIn();

				// The Click-on-email action for the extra recipients
				$("div#extra-recipients ul").append($("<li data-ccid='"+cc_id+"'>" + email + "</li>").click(function() {
					if(!confirm("Are you sure you want to remove: "+email_cc_list[$(this).data("ccid")]+" ?"))
						return;

					delete email_cc_list[$(this).data("ccid")];
					$(this).remove();
					cc_length--;
					if(cc_length == 0)
						$("div#extra-recipients", $c).fadeOut();
				}));

				email_cc_list.push(email);
				cc_id++;
				cc_length++;
			});

			// Canned messages selector (A jar icon on the right side) - When pressed, it temporarely hides the "Subject" input, creates a jqxDropDownList with the canned messages in it's place
			// -- Canned messages are only available in user-mode, the jar doesn't show up for public submit/transfer
			// -- The jqxDDL gets it's content remotely, from the templates with usage class "issmessage" to which the user has access to
			// -- Clicking the jar while the selector is in place reverses the process, allowing editing of the subject.
			if(self.config.user_mode)
			$("#canned-toggle", $c)
			.on('click', function() {
				if(canned_selector) {
					// Destroy the selector, hide the div, show the input
					$("#message-subject > input", $c).show();
					$("#message-subject > div", $c).off('select').jqxDropDownList('destroy');

					canned_selector = false;
				} else {
					// Extra data for the dataAdapter
					canned_data_source._source.data = {
						usage_class: "issmessage",
						org_id: params.org_id,
						issue_id: params.issue.id,
						sender_id: params.sender.id
					};

					// Hide the input
					$("#message-subject > input", $c).hide();

					// Instance the selector
					$("#message-subject", $c).append('<div id="canned_select"></div>');
					$("#message-subject > div", $c).jqxDropDownList({
						theme: cargohub.jqxtheme,
						source: canned_data_source,
						displayMember: "description",
						valueMember: "message",
						width: 590, height: 22,
						promptText: "Select canned message (click to choose)",
						autoDropDownHeight: true
					}).on("select", function(e)
					{
						if (e.args && e.args.item) {
							$("#message-subject > input", $c).removeClass('gray').val(e.args.item.originalItem.title);
							// Short delay to allow focus to work
							$("#canned-toggle", $c).trigger('click');
							setTimeout(function() {
								$("#message-text", $c).val(e.args.item.value);
								$("#message-text", $c).jqxEditor('focus');
							}, 100);
							modified = true;
						}
					});

					canned_selector = true;
				}
			})
			// Tooltip
			.jqxTooltip({
				content: "Toggle canned messages selector.<br />First click turns it on, second click shows the Subject line again.",
				position: "mouse"
			});

			// Message subject (changes text color from inactive to active)
			$("#message-subject input", $c)
			.on('focus', function() {
				$(this).removeClass('gray');
			})
			.on('blur', function() {
				if(!$(this).val())
					$(this).addClass('gray');
			})
			.on('change', function() {
				modified = true;
			});

			// Message text
			$("#message-text", $c).jqxEditor({
				theme: cargohub.jqxtheme,
				stylesheets: ["/css/default/cargo_jqxeditor.css"],
				width: 636,
				height: 247,
				lineBreak: "br",
				tools: 'bold italic underline | format font size color | left center right | ul ol | image link'
			})
			/**
			 * Event: Content editor is changed - Set the "Modified" flag
			 */
			.on('change', function() {
				modified = true;
			});

			// Data panel selector (Dropdown checkboxes) - Has some defaults (which are vaild for all issue types), but also custom data (defined issue panels)
			$("#parts_selector", $c).jqxDropDownList({
				theme: cargohub.jqxtheme,
				checkboxes: true,
				source: self.config.parts,
				displayMember: "title",
				valueMember: "id",
				width: 500, height: 24,
				autoDropDownHeight: true,
				disabled: true
			})
			/**
			 * Event: One of the checkboxes in the Parts selector is checked or unchecked - Some affect the visibility of other controls.
			 */
			.on("checkChange", function(event)
			{
				if(event.args.value == "contacts" && params.contacts) {
					// When this checkbox is changed, show or hide the "Contacts" selector
					if(event.args.checked)
						$("#contacts_selector", $c).parent().fadeIn();
					else
						$("#contacts_selector", $c).parent().fadeOut();
				}
				if(event.args.value == "attachments") {
					// When this checkbox is changed, show or hide the "Attachments" selector
					if(event.args.checked)
						$("#files_selector", $c).parent().fadeIn();
					else
						$("#files_selector", $c).parent().fadeOut();
				}
				modified = true;
			});

			// Check indexes for the type.panels which should be active by default
			for(c=0; c<self.config.parts.length; c++)
				if(self.config.parts[c].send)
					$("#parts_selector", $c).jqxDropDownList("checkIndex", c);
			// Disable first entry ( cannot be changed ) - This should be the primary (master) panel.
			$("#parts_selector", $c).jqxDropDownList("disableAt", 0);

			if(params.contacts) {
				// Contacts selector (Dropwdown checkboxes) - Present when there is more than one contact in the list of contacts. Also, that first contact cannot be turned off (Submitter)
				$("#contacts_selector", $c).jqxDropDownList({
					theme: cargohub.jqxtheme,
					checkboxes: true,
					source: params.contacts, // Should contain the list of contacts connected to the issue
					displayMember: "ashihara",
					valueMember: "id",
					width: 500, height: 24,
					dropDownHeight: params.contacts.length> 5? 127: params.contacts.length * 25 + 2
				})
				.on("checkChange", function(event) {
					modified = true;
				});

				// Check and Disable first entry ( cannot be changed ) - This should (always) be the submitter contact.
				$("#contacts_selector", $c).jqxDropDownList("checkIndex", 0);
				$("#contacts_selector", $c).jqxDropDownList("disableAt", 0);

				// Check if contacts are shown by default ?
				$("#contacts_selector", $c).parent().show();
			}

			// File attachments selector (Dropdown checkboxes) - All initially disabled, operator must manually choose which files to send
			if(!params.attachments)
				params.attachments = [];
			$("#files_selector", $c).jqxDropDownList({
				theme: cargohub.jqxtheme,
				checkboxes: true,
				source: params.attachments,
				displayMember: "name",
				valueMember: "id",
				width: 500, height: 24,
				dropDownHeight: params.attachments.length> 5? 127: params.attachments.length * 25 + 2,
				promptText: "No files currently selected or available"
			})
			.on("checkChange", function(event) {
				modified = true;
			});

			for(c=0; c<params.attachments.length; c++)
				if(params.attachments[c].visibility == 'public' || params.attachments[c].visibility == 'shared')
					$("#files_selector", $c).jqxDropDownList("checkIndex", c);

			// Check if files are shown by default ?
			$("#files_selector", $c).parent().show();

			// Signature option
			if(self.config.user_mode)
				$("#user-signature", $c).jqxCheckBox({
					theme: cargohub.jqxtheme,
					checked: false
				});
			// Attachments option
			$("#files-mode-off", $c).jqxRadioButton({
				theme: cargohub.jqxtheme,
				checked: params.files_mode != true
			});
			$("#files-mode-on", $c).jqxRadioButton({
				theme: cargohub.jqxtheme,
				checked: params.files_mode == true
			});
			// Finalize option
//			if(params.submit_mode)
//				$("#finalize-option", $c).jqxCheckBox({
//					theme: cargohub.jqxtheme,
//					checked: false
//				});
			// The CC to myself checkbox
			$("#email-cc-self", $c).jqxCheckBox({
				theme: cargohub.jqxtheme,
				checked: true,
                                 disabled: true
			});
                        $("#email-cc-self", $c).on("click", function() {
                            $(this).jqxCheckBox("check");
                        });

			// When not in user mode mode, Included data and Attachments controls are disabled)
//			if(!self.config.user_mode) {
//				$("#parts_selector", $c).jqxDropDownList({ disabled: true });
//				$("#files_selector", $c).jqxDropDownList({ disabled: true });
//			}

			// The big ass PREVIEW button (which opens an window which renders the e-mail just like it would be sent out)
			$("#email-preview", $c).jqxButton({
				theme: cargohub.jqxtheme,
				width: 300, height: 32
			})
			// Tooltip
			.jqxTooltip({
				content: "Opens a preview on how the e-mail might look into the recipients' E-mail client.<br /><br />Not all clients respect exact formatting so the results might vary!",
				position: "bottom",
				width: 300,
				autoHideDelay: 8000
			})
			/**
			 * Event: Clicked the "Preview" button which will open the e-mail into a new modal
			 */
			.on("click", self.open_Email_Preview);

			// Post initialization defaults
			if(self.config.message_defaults) {
				// Contact initialization
				if(self.config.message_defaults.recipient && self.config.message_defaults.recipient.contact_id)
					$("#message-recipient span", $c).addClass('contact-item').removeClass('gray').text(self.config.message_defaults.recipient.combobox_value).data("con_id", self.config.message_defaults.recipient.contact_id);
					// In this case the focus goes to the subject, not the contact, since it's been already picked

				// Subject initialization
				if(self.config.message_defaults.subject)
					$("#message-subject input", $c).removeClass('gray').val(self.config.message_defaults.subject);

				// Content initialization
				if(self.config.message_defaults.content)
					$("#message-text", $c).val(self.config.message_defaults.content);

				// Focus on the preview e-mail button when stuff is pre-initialized.
				setTimeout(function() {
					$("#email-preview", $c).focus();
				}, 250);

			} else
				$("#message-recipient", $c).focus();

			if(params.setup_Finalize)
				params.setup_Finalize($container);
		},

		/**
		 * Simply tells if anything was modified in the Message Builder
		 */
		is_Modified: function()
		{
			return modified;
		},

		/**
		 * Changes which parts of the issue will be used in the message.
		 * This affects both the internal params.parts variable, and also changes the visual controls of the screen that are used to select those parts.
		 * - Parts selector
		 * - XFields panel selector
		 * - Attached files selector
		 *
		 * This method can be used to update the controls after xfields have been edited via ajax in an overview, or attachments have been modified or added/removed
		 */
		set_Parts: function(parts)
		{

		},

		/**
		 * Returns complete message-building data, which can be sent via ajax to complete the sending operation
		 * Parts of the returned object can be obtained via the next four methods
		 * @returns object
		 */
		get_Message: function()
		{
			var message = {
				recipient: self.get_Recipient(),
				cc_list: self.get_CC(),
				subject: self.get_Subject(),
				text: self.get_Text(),
				parts: self.get_Parts(),
			};
			message.true_att = $("#files-mode-on", $c).jqxRadioButton('checked');
			if(self.config.user_mode)
				message.signature = $("#user-signature", $c).jqxCheckBox('checked');
			if(params.submit_mode)
				message.finalize = $("#finalize-option", $c).jqxCheckBox('checked'); // Does an inter-organization transfer (give control) instead of sharing
			message.cc_self = $("#email-cc-self", $c).jqxCheckBox('checked');

			return message;
		},

		/**
		 * Returns information about selected recipient (either complete data returned from a successful search or Name/E-mail combination, to create a new contact)
		 */
		get_Recipient: function()
		{
			return primary_recipient;
		},

		/**
		 * Returns the array with
		 */
		get_CC: function()
		{
			var clean_cc = [], c;
			if(email_cc_list)
				for (c = 0; c < email_cc_list.length; ++c) {
					clean_cc.push(email_cc_list[c]);
				}

			return clean_cc;
		},

		/**
		 * Reads the subject which is currently used by the builder
		 * @returns string
		 */
		get_Subject: function()
		{
			return $("#message-subject input", $c).val();
		},

		/**
		 * Returns the content of the message assambled from the data in this module (content + issue data, considering parts selectors)
		 * @returns string
		 */
		get_Text: function()
		{
			return $("#message-text", $c).val();
		},

		/**
		 * Returns the collected information about the parts
		 * @returns object
		 */
		get_Parts: function()
		{
			var parts = {
				list: [],
				attachments: []
			},
			// 1. List of parts themselves
			checked_parts = $("#parts_selector", $c).jqxDropDownList('getCheckedItems'),
			checked_contacts = params.contacts? $("#contacts_selector", $c).jqxDropDownList('getCheckedItems'): null,
			checked_attachments = $("#files_selector", $c).jqxDropDownList('getCheckedItems');

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
			$.each(checked_attachments, function() {
				parts.attachments.push(this.value);
			});

			return parts;
		},

		/**
		 * Creates a new modal with the content of the e-mail that would be sent out with the data currently selected in the builder.
		 * This preview doesn't need to have an actual system message recorded to be shown, instead, a simple HTML enclosure containing styles is wrapped around the message content and used as an iframe
		 */
		open_Email_Preview: function()
		{
			var builder_data = self.get_Message();

			if(params.sender)
				builder_data.sender = params.sender;
			if(params.issue)
				builder_data.issue = params.issue;

			if(!emailpreview) {
				cargohub.its.init_Modal_Window('email-preview', {"ok": "Close"});
				emailpreview = true;
			}
			cargohub.its.open_Modal_Window('email-preview', 800, 600, "E-mail preview", '<iframe src="/issue?action=preview_issue2message&amp;ireq=1&amp;message='+
				encodeURIComponent(JSON.stringify(builder_data))+
				(params.preview_params? '&amp;params='+encodeURIComponent(JSON.stringify(params.preview_params)):'')+
				'" width="790" height="525" frameborder="0"></iframe>');
		},

		/**
		 * Destroys the controls and frees memory
		 */
		destroy: function()
		{
			cargohub.contact_search.destroy("message-recipient");
			params = null;
			modified = false;
			if(canned_selector) {
				// Destroy the selector
				$("#message-subject > div", $c).off('select').jqxDropDownList('destroy');
				canned_selector = false;
			}
			$c = null;
			recipient_css = {};
			recipient_initialize = null;
		}
	});
});
