/**
 * Module "Issue View" (The page used to view an issue in read-only mode, but also allows limited update to contacts, documents and certain values)
 * - This one is loaded in three modes: Staff preview mode (read only mode), Contact account mode (limited update mode), Key access mode (read only mode)
 */
cargohub.issue_view = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = 0,
		popups = {}, // state of the pop-up windows used by this page - Used for lazy initialization ( on first use )

	/**
	 * Shows the modal window that loads contact details via a html ajax request.
	 */
	contact_Open_Details_Modal = function(contact_id) 
	{ 
		if(!popups.vcard) {
			cargohub.its.init_Modal_Window('vcard', {"ok": "Ok"});
			popups.vcard = true;
		}
		cargohub.its.open_Modal_Window('vcard', 600, 450, "Viewing contact details", "<div id='contact-modal'></div>", function($window) // Setup the window
		{
			// Load the Vcard HTML via AJAX inside this window
			$("div#contact-modal", $window).load("/global/contact?action=visual_card&id="+contact_id);
		});
	},

	file_submit_fallback = false,

	/**
	 * Prepares the classic file submit area.
	 * -- Consists of "file" browser upload fields, for compatibility with browsers that do not support the DropZone (IE9 and older, Firefox less than version 4, Old Opera or Safari)
	 */
	setup_File_Submit_Area = function()
	{
		file_submit_fallback = true;

		// The hover effect on the file fields
		$("div#file-submit-area")
		/**
		 * Event: Entering a file field's enclosing span creates the "delete" button and assigns it's "click" event.
		 */
		.on("mouseenter", "span", function(event) {
			$(this).prepend($('#field-delete-button').html());
			$("button", this).jqxButton({
				theme: cargohub.jqxtheme,
				width: 22,
				height: 22
			})
			/**
			 * Event: Clicking the field delete button simply removes the DOM table row and the entry from the "existing_fields" array
			 */
			.on('click', function(event) {
				$(this).parent().remove();
			});
		})
		/**
		 * Event: No longer hovering over that span removes the button from the DOM
		 */
		.on("mouseleave", "span", function(event) {
			$(this).find('button').remove();
		});
	},

	// The DropZone object, which contains the Files entry -> On normal uploads, each file should have a value "ID" which represents the file's database id
	// On successful submit, these files are connected to the issue. (Only for modern browsers. IE8 still handles uploads the classic way)
	attachments = null,
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
					if(self.config.issue)
						formData.append("issue_id", self.config.issue.id);

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
						cargohub.its.alert(x.reason, "Message from server");
					}
				});

				this.on("error", function(file, x) {
					if(file.status == 'error' && x.error)
						cargohub.its.alert(x.error, "Message from server");
					else
						cargohub.its.alert(x);
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
								cargohub.its.alert(x.reason, "Message from server", "events.png");
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
//					cargohub.its.alert("Files upload complete !");
//					dd(upload_in_progress);
					upload_in_progress = false;
				});

				this.on('reset', function() {
					$("#uploads-helper").fadeOut();
				});
			},
			// Fallback for using IE8/9 ...
//			forceFallback: true,
			fallback: setup_File_Submit_Area
		});

		// Mockup files shown in the dropzone, for the purpose of forwarding previously uploaded files between form reloads (when fields have errors)
		if(self.config.dropzone) {
			var mockFile = null;
			for(c=0; c< self.config.dropzone.length; c++) {
				mockFile = clone(self.config.dropzone[c]);
				mockFile.status = "success";
				mockFile.is_mock = true;
				attachments.files.push(mockFile);
				attachments.emit("addedfile", mockFile);
			}
		}
	},

	/**
	 * Sets up the simple communication form on the view issue page (frontend)
	 */
	setup_View_Form = function()
	{
		var multi_areas = {};

		// Setup the general "Window" which can be used to open information or other modal-type stuff
		cargohub.its.init_Modal_Window('general', {	"ok": "Ok" }, null, null, null, cargohub.fullwidth, 1000);

		// Show details for issue-linked contacts
		$("form#issue_form .contact-item").on('click', function() {
			contact_Open_Details_Modal($(this).data('con_id'));
		});

		// The buttons on the form (including the fake "submit" one)
		
		if($('form#issue_form input[type="button"]').length>0)
		$('form#issue_form input[type="button"]').jqxButton({
			theme: cargohub.jqxtheme,
			template: "success"
		})
		/**
		 * Event: Button is clicked
		 */
		.on('click', function()
		{
			cargohub.application.reset_Session_Timer();
			var data = $(this).data();
			switch(data.action) {
				// Opens a jqxWindow with information
				case 'open-window':
					// Setup the reusable window which can be used to open information or other modal-type stuff
					if(!popups.general) {
						cargohub.its.init_Modal_Window('general', {	"ok": "Ok" }, null, null, null, cargohub.fullwidth, 600);
						popups.general = true;
					}

					var window = data.window.split(',');
					cargohub.its.open_Modal_Window('general', window[0], window[1], window[2],
						$('#'+data.template).html());
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

				// Adds more submit fields (IE compatibility mode file upload system)
				case 'add-file-input':
					$("#file-submit-area").append("<span><input type=\"file\" name=\"uploads[]\"></span>");
					break;

					// Submits the form (which should link newly uploaded files and makes certain updates - depending on template)
				case 'form-submit':

					// Verify if uploads are still in progress.
					if(upload_in_progress) {
						cargohub.its.alert("There are uploads currently in progress. Please wait...");
						return false;
					}

					// Verify the status files in the DropZone ( if it exists ), and don't allow submit with files with status other than "success"
					if(attachments && attachments.files && !file_submit_fallback) {
						// Assamble the list of uploaded file ids
						var dropzone_file_ids = [], error_count = 0;
						for(c in attachments.files) {
							if(attachments.files[c].status == "success")
								dropzone_file_ids.push(attachments.files[c].id);
							else
								error_count++;
						}

						if(error_count > 0) {
							cargohub.its.alert("Some files failed upload or were cancelled. Please remove them before continuing !");
							return false;
						} else
							// Append a hidden item with the file ID's before the form is submitted (if any)
							if(dropzone_file_ids.length > 0)
								$('form#issue_form').append($("<input>").attr("type", "hidden").attr("name", "dropzone_file_ids").val(JSON.stringify(dropzone_file_ids)));
					}

					// Submit the form for update after files have been processed
					$('form#issue_form').submit();
					break;

			}
		});

		// Init the update confirm window without the OK button role, it will be defined below
		cargohub.its.init_Modal_Window('confim_window', null, {"cancel": "cancel"});
	},

	/**
	 * Setup scripted (jqWidgets) controls which might appear in various places in the form (dropdowns, combobox, radios, etc.)
	 * -- Classes are used to determine the control's type
	 */
	setup_Scripted_Controls = function()
	{
		// Setup Calendar and Clock inputs
		$("div.calendar").each(cargohub.components.ui.calendar_input);
		$("input.theclock").each(cargohub.components.ui.clock_input);

		// Special comboboxes used for searching/suggestion (Example: Airport search)
		$("div.searchbox").each(cargohub.components.ui.remotesearch_input);

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
				var paste = "";
				e.preventDefault();
				if(e.clipboardData || e.originalEvent.clipboardData)
					paste = (e.originalEvent || e).clipboardData.getData('text/plain');
				else if(window.clipboardData)
					paste = window.clipboardData.getData("Text");
				if(paste.length > 0)
					$(this).val(paste.replace(/[^0-9.]/g, ''));
			});
		});

		// Setup auto-advance fields (that move to another field when enough charactes have been typed into them)
		$(".auto-move").each(function()
		{
			$(this).on('keyup', function(e) {
				var data = $(this).data(), val = $(this).val();
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
					$("#"+data.automove_to).focus();
				}
			});
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
		});
	}
	;

	this._public({

		// section initialization
		init: function()
		{
			// Default theme for when none is selected (used only in public mode)
			if(!cargohub.jqxtheme)
				cargohub.jqxtheme = "base";

			// to be divided... ?
			setup_View_Form();

			// Controls in the form which are not standard HTML (must be controlled by JS)
			setup_Scripted_Controls();

			// E-mail update requested ? - Show the "Update-message" window
			if(self.config.update_by_mail) {
				// Setup the window
				if(!popups.transfer) {
					cargohub.its.init_Modal_Window('update-message', {"ok": "Send", "cancel": "Cancel"}, {"cancel": "cancel"}, null, null, cargohub.fullwidth, 600);
					popups.transfer = true;
				}

				// Parse contacts and determine the submitter
				for(c=0; c<self.config.contacts.length; c++) {
					if(self.config.contacts[c].submitter)
						self.config.submitter = self.config.contacts[c];
					if(self.config.contacts[c].transfer)
						self.config.target_con = self.config.contacts[c];
				}

				var
				tpl_data = {
					jqxtheme: cargohub.jqxtheme,
					user_mode: self.config.user_mode,
					sender: self.config.submitter.contact, // Same as above
					multiple_contacts: self.config.contacts.length > 1,
				},
				i2m_config = { // Configuration sent to the setup function for the confirm window
					caller_module: self,
					width: 1010,
					height: 600,
					issue: self.config.issue,
					org_id: self.config.issue.org_id,
					setup_Finalize: function($container) {
						// Setup the action for "OK" button of the window (which should send
						$('#modal_update-message_ok', $container).off('click').on('click', function()
						{
							var message = cargohub.issue2message.get_Message();

							if(!message.recipient)
								return cargohub.its.alert("No recipient has been selected !");

							$.post("/issue?action=issue2message", {
								email: true,
								operation: "update",
								issue: self.config.issue,
								message: JSON.stringify(message),
								sender_id: self.config.submitter.contact.id
							}, function(x) {
								// On success, x.id should contain the ID
								if(x.success) {
									document.location = "/contact/account#issues";

									// For public submit, there is no finalize option - Instead, the system should redirect to the "issue view" zone, which should show the status "transferred"
								} else
									cargohub.its.alert(x.reason, "Message from server", "events.png");
							});
						});
					},
					sender: self.config.submitter.contact, // Same as above
					message_defaults: {},
				};

				// If the dropzone is used, the files for the dropzone are also the files used in the selector for the issue message builder
				if(self.config.files)
					i2m_config.attachments = self.config.files;

				// Multiple contacts ? Offer the possibility to select which ones will be included in the generated message
//				if(sizeof(self.config.contacts)>1) {
					i2m_config.contacts = [];
					for(c=0; c<self.config.contacts.length; c++)
						i2m_config.contacts.push({
							id: self.config.contacts[c].contact.id,
							ashihara: self.config.contacts[c].label+ ': '+ self.config.contacts[c].contact.name// + " ("+ self.config.contacts[c].contact.email+ ")" // Might be too long and break the control
						});
//				}

				i2m_config.message_defaults.recipient = {
					css: { isstarget: self.config.issue.org_id },
					initialize: JSON.stringify({
						method: "comm_target",
						org: self.config.issue.org_id,
						rt_key: self.config.ist.rt_key//,
//						dep_ids: self.config.issue.dep_ids
					})
				};

				cargohub.its.open_Modal_Window('update-message', i2m_config.width, i2m_config.height, "Submit update for "+self.config.ist.name,
					$("script#template-issue2message_builder").html().present(tpl_data), cargohub.issue2message.setup_MessageBuilder, i2m_config);

				// Global focus anywhere in the window related to contacts search system inside the update window
				// Activated with a 2-seconds delay, as there are some focus events triggered before their time
				setTimeout(function() {
					$(document).on('focus', '*', function(e) {
						e.stopPropagation();

						if(e.target.id == 'message-recipient' || $(e.target).parents('#message-recipient').length || $(e.target).hasClass('jqx-listbox'))
							return; // Do not attempt to close when target is the master container itself, or anything inside it, to prevent immediate reopening when clicking in the contact bar itself, or when there is nothing to close

						cargohub.contact_search.close(self.open_css_key);
					});
				}, 2000);
			}

			// Elements with tooltips
			$(".info-tip, .field_error").each(function()
			{
				$(this).jqxTooltip({
					content: $(this).data('tooltip'),
					position: 'mouse',
					theme: cargohub.jqxtheme
				});
			});

			setup_DropZone();

		}
	});
});


$('body').addClass('issue_view_front_body'); // IWI front end user record details page new design 14112025
