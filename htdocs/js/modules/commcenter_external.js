/**
 * Module "Message Center" - External conversation access
 * Allows an external party to reply in an existing conversation by the use of an "access_key" supplied in the conversation.
 * -- The purpose is to give entities receiving e-mails the ability to reply by using the system itself.
 * -- This is a small part of the big "commcenter_main" module which behave similarly with the Reply tab, but without the ability to select contacts, with some special conditions and restrictions:
 * -- The direct "reply" command picks one of the following type of contacts:
 *    - Last contact that wrote and is of type "user_self" (staff), "issue_target" (recipient), "isspost" (submitter) that is not the specified E-mail
 *    - If none of these types of contacts exist, replying is not addressed to a specific contact, the message is simply added without a specific target.
 * -- Only messages that are not written by the e-mail present in the request will have a "Reply" button beside them (Without any other commands which exist for staff, like forward/sender details, etc.)
 *
 *
 *
 */
cargohub.commcenter_external = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,
		input_theme_classes = "jqx-widget jqx-widget-content jqx-widget-"+cargohub.jqxtheme+" jqx-input-"+cargohub.jqxtheme+" jqx-widget-content-"+cargohub.jqxtheme,
		// state of the pop-up windows used by this page - Used for lazy initialization ( on first use )
		popups = {},

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

	// Values related to the Contact menu
	$contact_menu = null, contact_menu_is_open = false,  // The menu object ; Is the contact menu open ?
	$action_contact = null, action_contact_id = null, action_contact_name = null, // The object under which contact menu is open ; For which contact id the menu has opened (id and name) ?
	/**
	 * Opens the contact action menu ( and creates it at first run )
	 * -- Used in several places across the communication module
	 * @param $contact The jQuery item under which the contact menu is displayed
	 */
	contact_Open_Menu = function($contact)
	{
		if(contact_menu_is_open && $action_contact && $action_contact[0] === $contact[0]) {
			// Do not reopen for the same object, but do cancel the close timer - as long as the trigger (which is usually a hovering over a contact-item) is active, the menu doesn't autoclose
			donrey.cancel_Delay('contact-menu-close');
			return;
		}

		$action_contact = $contact;
		action_contact_id = $contact.data('con_id');
		if(!action_contact_id)
			return; // Not valid if the element is missing the data-con_id property
		action_contact_name = $.trim($contact.text());

		// Create contact item menu at first use (it is reused across tabs!)
		if(!$contact_menu) {
			$contact_menu = $($('#contact-actions').html()).jqxMenu({
				theme: cargohub.jqxtheme,
				autoOpenPopup: false,
				animationShowDuration: 0,
				animationHideDuration: 0,
				mode: 'popup'
			})
			/**
			 * Event: An item is clicked into the contact menu. Actions need to be functional across tabs !
			 */
			.on('itemclick', function(e) {
				if(!action_contact_id)
					return cargohub.its.alert("There is a problem in determining the contact information, cannot proceed.");

				switch($(e.args).data('op')) {
					// Opens the contact details modal for the selected contact
					case 'details':
						contact_Open_Details_Modal(action_contact_id);
						break;
					// Action done, clear the temporary values
					action_contact_id = null;
					action_contact_name = null;
				}
			})
			/**
			 * Event: Menu is closed - Cancel outstanding timers, clear contact data
			 */
			.on('closed', function(e) {
				donrey.cancel_Delay('contact-menu');
				donrey.cancel_Delay('contact-menu-close');
				contact_menu_is_open = false;
				$action_contact = null;
			});

			// Entering and exiting the menu activates or deactivates auto-closing
			$("div#contact-actions-menu").hover(function() {
				donrey.cancel_Delay('contact-menu-close');
			}, function() {
				donrey.single_Delay('contact-menu-close', function() {
					$contact_menu.jqxMenu('close');
				}, 2500);
			});
		}

		// Set the text in the first line as the text in the item that is being used
//		$("div#contact-actions-menu li:eq(0)").html('<img alt="Contact details" src="/img/icons16/user_gray.png"> <b>' + $contact.text() + '</b>');

		donrey.cancel_Delay('contact-menu-close');
		var item_pos = $contact.position(), item_left_margin = parseInt($contact.css('margin-left'));
		if(item_pos.left == 0 && item_pos.top == 0) {
			item_pos = $contact.offset(); // Something not right with position(), try the offset() function instead (which is also imprecise for some reason)
			item_pos.left-= 4;
		}

		$contact_menu.jqxMenu('open', item_pos.left + item_left_margin, item_pos.top + 22);
		contact_menu_is_open = true;
	},

	/**
	 * Setup the Conversation display
	 * -- Contains list of messages, contacts and a reply box
	 */
	setup_Conversation = function()
	{
		quickreply_modified = false;
		var $message_menu = null, action_message_data = null, // The buttons from which message have been pressed ( for Reply, or the Action menu ), and the Data() for the message corresponding to the button

		quickreply_active = null, // Null when the box is closed, "reply" when it works in reply mode, "forward" when it works in forward mode
		recipient = null, // Reply recipient used in the reply box (id, email, name)

		/**
		 * Creates a "quote" string out of "action_message_data" content, to be used in the reply box.
		 */
		quote_From_Action_Message_Data = function()
		{
			if(action_message_data && action_message_data.from_id && action_message_data.msg_content) {
				var quote_hdr = "On " +action_message_data.dt+ ", " +action_message_data.from_name+ " wrote:";
				return "<br /><br /><div style='margin-bottom: 4px; color: gray;'>" + quote_hdr + "</div><div style='padding-left: 12px; margin-left: 8px; border-left: 2px solid lightgray; margin-bottom: 6px;'>" + action_message_data.msg_content + "</div>";
			} else
				return "";
		},

		/**
		 * Sets up the reply action (box on the bottom of the conversation tab)
		 * @param default_content - Optionally, place a content in the editor, but the focus will still be on top ( above the content )
		 */
		setup_Reply_Box = function(default_content)
		{
			if(quickreply_active)
				return; // Already set up and open, nothing to do.

			// Remove the orginal border (set width to 0px)
			$('#convo-quickreply #quickreply-frame').css("border-width", 0);

			$("#convo-quickreply #reply-content").jqxEditor({
				theme: cargohub.jqxtheme,
				stylesheets: ["/css/default/cargo_jqxeditor.css"],
				width: 767,
				height: 500,
				lineBreak: "br",
				tools: 'bold italic underline | format font size | color background | left center right | outdent indent | ul ol | image link | clean'
			})
			/**
			 * Event: Content editor is changed - Set the "Modified" flag
			 */
			.on('change', function() {
				quickreply_modified = true;
			});

			if(default_content)
				$("#convo-quickreply #reply-content").val(default_content);

			// Prepare and show the primary recipient bar (To:)

			// Initial mode of operation (reply/forward), changes the picture
			$("#convo-quickreply #reply-recipient > img").prop('src', "/img/icons16/mail_reply_sender.png").prop('alt', "Reply to message");

			if(recipient)
				$("#convo-quickreply #reply-recipient span").addClass('contact-item').removeClass('gray').text(recipient.name.escapeHtmlEntities()).data("con_id", recipient.id);
			$("#convo-quickreply #reply-recipient").show();

			// Show the reply toolbar
			$("#convo-quickreply #quickreply-commands").fadeIn();
			// Hide the help bar, not needed while the box is active
			$("#convo-quickreply #quickreply-help").hide();

			// All set, scroll down to the reply box
			$('html, body').animate({
				scrollTop: $("#convo-quickreply").offset().top
			}, 1000, function() {
				// Focus the editor when the scroll animation is complete.
				$("#convo-quickreply #reply-content").jqxEditor('focus');
			});
		},

		/**
		 * Changes the content of the already open reply box
		 * -- Optional new content and subject
		 */
		change_Reply_Box = function(new_content, new_subject)
		{
			if(!quickreply_active)
				return; // Can't change the box, it's not currenly open

			// Subject is set or reset
			if(new_subject)
				$("#convo-quickreply #reply-subject input").val(new_subject).removeClass('gray');
			else if(new_subject === false)
				$("#convo-quickreply #reply-subject input").val("").addClass('gray');

			// Is content changed ?
			if(new_content) {
				$("#convo-quickreply #reply-content").val(new_content);
				$("#convo-quickreply #reply-content").jqxEditor('focus');
			}
		};

		// Clicking on closed message hides it and shows the "open" version insted
		$("div.convo-msg-closed").on("click", function(e) {
			var $this = $(this);

			$this.hide();
			$("div.convo-msg-open", $this.parent()).show();
		});

		// Clicking on the header of open messages closes it and shows the "closed" version instead
		$("div.convo-msg-open .msg-header").on("click", function(e) {
			var $this = $(this).parent();

			if(!(e.target.attributes.role && e.target.attributes.role.value == "button")) {
				$this.hide();
				$("div.convo-msg-closed", $this.parent()).show();
			}
		});

		if(self.config.reply_active) {
		// Make the message-related buttons fancy (two glued buttons)
		$("div.convo-msg-open .msg-header .msg-buttons").each(function() {
				$(this).jqxButtonGroup({
					theme: cargohub.jqxtheme,
					mode: 'default',
					enableHover: true
				})
					.on('buttonclick', function(e) {
						var $this = $(this).parents("div.convo-msg-open"); // The message area that contains the button

						// Grab the message data, sender and recipient info, subject and content
						action_message_data = $this.parent().data();
						action_message_data.recipient = $this.find(".msg-recipients  div:eq(0) .contact-item").data();
						action_message_data.recipient_name = $this.find(".msg-recipients  div:eq(0) .contact-item").text();
						action_message_data.sender_email = $('.convo-contact:eq(0) h5').text();
						action_message_data.sender_name = $('.convo-contact:eq(0) .contact-item').text();
						action_message_data.receiver_email = $('.convo-contact:eq(1) h5').text();
						action_message_data.receiver_name = $('.convo-contact:eq(1) .contact-item').text();
						action_message_data.from_name = $.trim($this.find("span.from-name").text());
						if(!$this.find("div.msg-content").data('empty'))
							action_message_data.msg_content = $.trim($this.find("div.msg-content").html());

						if(!action_message_data.msg_content || action_message_data.msg_content.length == 0)
							delete action_message_data.msg_content;
						if(e.args.index == 0) {
							// Pressing Reply focuses the quickreply box. Focusing grabs the data from "action_message_data" variable
							if(!quickreply_active)
								$("#convo-quickreply #reply-content").focus();
							else
								change_Reply_Box(quote_From_Action_Message_Data());
						} else if(e.args.index == 1){
							recipient = {
								id: action_message_data.recipient.con_id,
								sender_email: action_message_data.sender_email,
								sender_name: action_message_data.sender_name,
								receiver_email: action_message_data.receiver_email,
								receiver_name: action_message_data.receiver_name
							};

							if(!recipient)
								return cargohub.its.alert("Invalid recipient in the recipient field.");

							// Prepare the required variables
							var reply_data = {
								cnv_id: self.config.convo.id,
								subject: $('.linked-element').nextAll('div h1').text(),
								content: action_message_data.msg_content,
								recipient: recipient
							};

							if(reply_data.content.length < 10)
								return cargohub.its.alert("Your message is too short, please write more !");

							// If there is an active issue, communicate with the server that the reply is for an issue-linked conversation
							if(self.config.issue)
								reply_data.issue = self.config.issue;

							$.post("?action=external_comm_reply_custom", reply_data, function() {
								// Success ! Any messages will be shown after reloading via EVAN
								document.location.reload();
							})
								.fail(cargohub.its.http_fail);
						}else{
							contact_Open_Details_Modal(action_message_data.from_id);
						}
					});
			});

		// Beside the quickreply help bar with the two action "links" also allow replying by clicking on the frame
		$("#reply-content, #text_cmd_reply").on('click', function(e) {
			$("#convo-quickreply #reply-content").focus();
		});

		// Quickreply subject (changes text color from inactive to active)
		$("#reply-subject input")
		.on('focus', function() {
			$(this).removeClass('gray');
		})
		.on('blur', function() {
			if(!$(this).val())
				$(this).addClass('gray');
		})
		.on('change', function() {
			quickreply_modified = true;
		});

		$("#convo-quickreply")
		/**
		 * When Quickreply Box is focused (clicked or otherwise reached), set up the actions for it.
		 * -- Using delegate event because the #reply-content box might be destroyed and recreated.
		 */
		.on('focus', "#reply-content", function() {

			var box_content = "";
			// The box is closed, prepare to open it
			if(!quickreply_active) {

				// If present, find the "reply_to" id in the listed contacts. That will be the contact used as the reply target.
				if(self.config.reply_to) {
					// Find the reply_to id in
					$("#convo-right-side h3.contact-item").each(function() {
						if(recipient)
							return; // Skip contact, already found the correct one to reply to
						if(self.config.reply_to == $(this).data('con_id')) {
							recipient = {
								id: self.config.reply_to,
								name: $(this).text()
							};
						}
					});
				} else {
					// If not (or zero), the reply will go to nobody
					recipient = {
						id: 0,
						name: "(no specific contact)"
					};
				}

				// Prepare primary recipient for the CSS plugin
				if(action_message_data) {
					if(action_message_data.msg_content)
						box_content = quote_From_Action_Message_Data();

					// And also enable the Send button since a contact is preselected
					$("button#reply-send").jqxButton({disabled: false}).jqxButton('render');
				}

				setup_Reply_Box(box_content);
				quickreply_active = true;
			}
		});

		// ... and command bar
		$("#convo-quickreply div#quickreply-commands button").jqxButton({
			theme: cargohub.jqxtheme
		})
		.on('click', function(e) {
			switch(e.target.id) {
				// Sends the typed reply to the selected contact
				case 'reply-send':
					if(!recipient)
						return cargohub.its.alert("Invalid recipient in the recipient field.");

					// Prepare the required variables
					var reply_data = {
						cnv_id: self.config.convo.id,
						subject: $("#convo-quickreply #reply-subject input").val(),
						content: $("#convo-quickreply #reply-content").val(),
						recipient: recipient
					};

					if(reply_data.content.length < 10)
						return cargohub.its.alert("Your message is too short, please write more !");

					// If there is an active issue, communicate with the server that the reply is for an issue-linked conversation
					if(self.config.issue)
						reply_data.issue = self.config.issue;

					$.post("?action=external_comm_reply", reply_data, function() {
						// Success ! Any messages will be shown after reloading via EVAN
						document.location.reload();
					})
					.fail(cargohub.its.http_fail);
					break;

				// Activates the subject line and focuses it - Normally replies do not have a subject, since the original subject of the conversation is used, but the subject can still be set using this input
				case 'reply-set-subject':
					// Show the subject line and focus it
					$("#convo-quickreply #reply-subject").show();
					$("#convo-quickreply #reply-subject input").focus();
					break;

				// Cancels the reply writing operation and clears data
				case 'reply-discard':
					if(quickreply_modified) {
						if(!confirm("Quick reply area contains content.\nAre you sure you want to discard all information ?"))
							return;
					}

					// Clean the subject and content
					$("#convo-quickreply #reply-content, #convo-quickreply #reply-subject input").val("");
					$("#convo-quickreply #reply-content").jqxEditor("destroy");
					// Unfortunately the destroy command also removes the object... so add it back
					$("#convo-quickreply #quickreply-frame").append($('<div id="reply-content" style="height: 100px;" tabindex="2"></div>'));

					// Hide the commands, hide the recipient and subject bars
					$("#convo-quickreply #reply-recipient, #convo-quickreply #reply-subject, #convo-quickreply #quickreply-commands").hide();
					// Add back the border
					$('#convo-quickreply #quickreply-frame').css("border-width", 1);
					// ... and show the help bar again
					$("#convo-quickreply div#quickreply-help").fadeIn();

					// Reset all values, clear all data
					quickreply_modified = false;
					quickreply_active = null;
					action_message_data = null;
					recipient = null;
					break;
			}
		});
		// #End reply_active condition
		}

		// Trigger Click on last message (so it is by open by default)
		$("div.convo-msg:first div.convo-msg-closed").trigger("click");

		// Right side - Contact buttons
		$("div.convo-contact button").on('click', function() {
			contact_Open_Details_Modal($(this).data('id'));
		});
	}

	;
	this._public({
		/**
		 * Section initialization
		 */
		init: function()
		{
			if(!self.config || !self.config.convo) {
				// Apparently there was an error in loading the conversation, do not try to initialize stuff (as the required DOM does not exist)
				return;
			}

			setup_Conversation();

			// Sets the tooltip on all "info-tip" elements
			$(".info-tip").each(function() {
				$(this).jqxTooltip({
					theme: cargohub.jqxtheme,
					content: $(this).data('tooltip'),
					position: 'bottom',
					width: $(this).data('tooltip_width') || "auto",
					autoHideDelay: 10000
				});
			});

			// This should work anywhere in the module - Add the mouseenter/mouseleave events on any future elements of class .contact-item.
			$(document)
			.on("mouseenter", ".contact-item", function() {
				if(contact_menu_is_open)
					// if already open, do not delay
					contact_Open_Menu($(this));
				else
					// else wait a bit, because the user might move over contacts without wanting to actually do something with them
					donrey.single_Delay('contact-menu', contact_Open_Menu, 1000, $(this));
			})
			.on("mouseleave", ".contact-item", function() {
				donrey.cancel_Delay('contact-menu');
				if($contact_menu) {
					donrey.single_Delay('contact-menu-close', function() {
						$contact_menu.jqxMenu('close');
					}, 2500);
				}
			});
		}
	});
});