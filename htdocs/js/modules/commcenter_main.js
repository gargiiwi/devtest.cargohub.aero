/**
 * Module "Message Center"
 * This is the primary communication hub which allows the user to access all his messages and send information to other people
 *
 * -- TODO: Due to the fact that this module actually has two functions (messages, contacts) which have a similar interface, a lot of code is doubled. Try to merge a bit of the code to make it shorter
 */
cargohub.commcenter_main = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,
		input_theme_classes = "jqx-widget jqx-widget-content jqx-widget-"+cargohub.jqxtheme+" jqx-input-"+cargohub.jqxtheme+" jqx-widget-content-"+cargohub.jqxtheme,
		// state of the pop-up windows used by this page - Used for lazy initialization ( on first use )
		popups = {},

	// Tabs system
	loaded_tabs = {},			// Contains the list of tabs that have already been loaded and initialized (in this module, some tabs might get unloaded)
	tab_load_params = {},		// Optional parameters for when loading a tab. Usually empty, but for certain tabs it might contain information so the server knows how to assamble the tab's data
	visible_toolbar_items = {},	// List of toolbar sub-items (2nd part of the id, after cc_ops-) which are currently enabled/visible - List depends on

	// Tab: List
	current_label = null,		// Numeric ID of the currenty selected label in the tree (if not known, it will be filled when loading the tab)
	current_label_type = null,
	selected_convos = [],		// DB ID's of the selected convos ; If only one is selected the array will contain the id of the "active_convo")
	active_convo_record = null,	// Reference to the datasource record that is currently selected on the convo grid | Changed only when required to update the grid
	active_convo = null,		// Copy of "active_convo_record" - Selected conversation (when single select), used to link Conversation tab to the List tab. When multiple rows are selected, active_convo becomes "null" again
								// -- Note, this is also initialized when a conversation is directly loaded by the use of "load_config"
	// Tab: Conversation
	quickreply_init = null,		// Becomes null when the box is closed, or is used to determine how to initialize or change the box: "reply" when a reply command was issued or the box was clicked and "forward" when a forward command was issued.
	quickreply_modified = false,// If anything is typed into the reply box, this becomes true. Used for the safety check on the "Discard" button which clears the content, or when attempting to load a new conversation

	// Tab: Compose
	compose_defaults = null,	// If null, the compose tab will be empty. If an object containing values, the compose might have one or more contacts already selected, a subject, a message content
	compose_modified = false,	// If anything is typed into the "Compose" tab, this becomes true. Used for the safety check on the "Discard" button which clears the content, or a new compose is initialized

	// Tab: Contacts
	current_conlab = null,			// Selected contact type or label in the tree
	current_conlab_type = null,
	selected_ctx = [],			// DB ID's of the selected contacts ; ... same as selected_convos
	active_contact_record = null,// ... same as how convo labels work
	active_contact = null,		// ... same as convo labels

	// Tab: VCard - Uses the universal contact editor, so no variables here.

	// Linking to other application modules
	active_location = null,		// When present, there is a return location to another module. Some buttons go there instead of another tab, and some actions might also change behaviour
	active_issue = null,		// When present, the comm center has been loaded from an active issue. Some aspects change behaviour

	// Other module-wide variables
	open_css_key = null, // Indicates which contact search system is currently open in the entire comm center. Used by the global focus to determine which one to close.

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
					// Initializes compose with the chosen contact as primary recipient
					case 'compose':
						if(compose_modified) {
							if(!confirm("Compose tab contains content.\nAre you sure you want to clear it to write new message to this contact?"))
								return;
						}

						cargohub.contact_search.destroy("compose-recipient");
						open_css_key = null;
						delete loaded_tabs['tab_compose'];

						// Setup the parameters for loading the "Compose" tab with preloaded contact
						compose_defaults = {
							to_id: action_contact_id,
							to_name: action_contact_name
						};

						// Ready, switch to Compose tab
						$('#comm-center').jqxTabs('select', 2);
						break;
					// Loads the list tab into a special mode where conversations are searched for presence of the user's contact and the target contact. Only those conversations in which both contacts are presents are shown.
					case 'find_convos':

						break;
					// Attempt to edit a contact. This is done through a server request, asking if this contact can be edited by current user. Only if the server responds affirmatively, the module is switched to vcard tab with the selected contact active
					case 'edit':

						break;
					// Add contact to the list of favourites (these show up when attempting to "search" with no parameters, and also as a list in certain areas)
					case 'fav':

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
	 * Clears and optionally disables an item showing tab (Conversation or Vcard), making it ready to be loaded with new data
	 * -- Usually as an action of clearing a selection in on of the grid tabs (List, Contacts), selecting multiple rows or using the "Close" command on the tab itself
	 *
	 * @param idx Can be either 1 or 4 (for now)
	 */
	clear_Item_Tab = function(idx, title, disable)
	{
		var icon = idx==1? "conversation": "vcard", tab_key = idx==1? "tab_conversation": "tab_vcard";

		if(disable)
			$('#comm-center').jqxTabs('disableAt', idx);
		if(title)
			$('#comm-center li.jqx-tabs-title:eq('+idx+') div.jqx-tabs-titleContentWrapper').html('<img src="/img/icons16/'+icon+'.png" alt="'+title+'" /> '+title); // resets the header
		$('#comm-center').jqxTabs('setContentAt', idx, '<div style="text-align: center; padding: 30px;"><img src="/img/extra/loader3.gif" alt="Loading..." /></div>'); // clears content
		delete loaded_tabs[tab_key];
	},

	/**
	 * Shows a toolbar item and enables any buttons present on it
	 * The item might contain other sub-items (which are either hidden or visible). This function only affects main item and it's buttons
	 * TODO: Since toolbars are used all over the application, these kind of functionality might be moved in the main script so it can be used in every module?
	 */
	enable_Toolbar_Item = function(prefix, item_name)
	{
		// Checks if the item is already enabled, in this case nothing to do
		if(visible_toolbar_items[item_name])
			return;

		// First, enable the buttons
		$('div.toolbar #'+prefix+item_name+' > button').jqxButton({disabled: false}).jqxButton('render'); // Redraws the button, just in case...
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
	disable_Toolbar_Item = function(prefix, item_name)
	{
		// Checks if the item is already disabled, in which case there's nothing to do
		if(!visible_toolbar_items[item_name])
			return;

		// First, disable the buttons
		$('div.toolbar #'+prefix+item_name+' > button').jqxButton({disabled: true}).jqxButton('render'); // Redraws the button, just in case...
		// ... then prepare a timer for fading out the item itself
		donrey.single_Delay('hide_bar_'+item_name, function() {
			$('div.toolbar #'+prefix+item_name).fadeOut();
		}, 500);

		visible_toolbar_items[item_name] = false;
	},

	/**
	 * Clears the selection from the messages grid, hides an operations toolbar by disabling buttons first then fading out the bar and disables the 2nd tab ( which is used to view a message )
	 * Usually used when the grid is filtered or another label is loaded (rows change)
	 */
	convo_Grid_Unselect = function()
	{
		// clear selection after filter, as the selected row might have dissapeared
		$('#tab_list #convo-grid').jqxGrid('clearselection');

		// hide any visible operation bars
		for(c in visible_toolbar_items) {
			// some special cases, in which items are not disabled
			if(current_label_type == 'e-bin' && c == 'trash_empty')
				continue;

			disable_Toolbar_Item('cc_ops-', c);
		}

		cargohub.contact_search.destroy('reply-recipient');
		clear_Item_Tab(1, "Select conversation", true);

		active_convo = null;
		active_convo_record = null;
		selected_convos.length = 0;
	},

	/**
	 * Clears the selection from the contacts grid, hides an operations toolbar by disabling buttons first then fading out the bar and disables the 5nd tab ( which is used to view/edit contacts )
	 * Usually used when the grid is filtered or another label is loaded (rows change)
	 */
	contact_Grid_Unselect = function(tab_title)
	{
		if(!tab_title)
			tab_title = "Select contact";

		// clear selection after filter, as the selected row might have dissapeared
		$('#tab_contacts #ctx-grid').jqxGrid('clearselection');

		// hide any visible operation bars
		for(c in visible_toolbar_items) {
			disable_Toolbar_Item('cc_ops-', c);
		}

		clear_Item_Tab(4, tab_title, true);

		active_contact = null;
		active_contact_record = null;
		selected_ctx.length = 0;
	},

	/**
	 * Assambles the data from the entire contact editing page and submits it throgh an ajax call
	 */
	contact_Save = function(contact_id)
	{
		var contact = {xdata: {}};

		if(contact_id)
			// When ID is known, we're in edit mode
			contact.id = contact_id;
		else {
			// When not, we're in create mode (New Contact), needs two more values to properly link the contact
			contact.linkto = current_conlab;
			contact.type = current_conlab_type;
		}
		// base fields
		contact.name = $("#contact-name").text();
		contact.email = $("#contact-email").text();

		// find all custom fields and assamble xdata from them
		$("#tab_vcard #contact-leftside tr").each(function() {
			var id = $(this).attr('id'), value;

			if(id && id.substr(0, 11) == "contact-xd-") {
				value = $(this).find('td span').text();
				if(value == "(click to set)" || !value)
					return;
				contact.xdata[id.substr(11)] = $(this).find('td span').html();
			}
		});

		// Test if some required values exist, if not throw error message
		if(!contact.name || contact.name.length < 5)
			return cargohub.its.alert("The contact name must have at least 5 characters.");

		if(!contact.email || !validate_email(contact.email))
			return cargohub.its.alert("You did not enter a valid e-mail address for this contact.");

		// Stringify XData
		contact.xdata = JSON.stringify(contact.xdata);

		$.post("?action=save_contact", contact, function(x) {
			if(x.success) {
				// Refresh the contacts grid in the 4th tab
				contact_Grid_Unselect();
				$('#tab_contacts #ctx-grid').jqxGrid('updatebounddata');

				// For some weird freekin' reason the Address Book tree display is affected when displaying an alert?
				// ... so, introduce a delay until the message is shown, so we are already on the listing tab when it pops up.
				setTimeout(function() {
					cargohub.its.alert("Contact has been saved.", "Success !", "thumb_up.png");
				}, 500);
			} else {
				cargohub.its.alert(x.reason, "Message from server", "events.png");
			}
		});
	},

	// Deprecated, contact operations have been moved to /global/contact
	contact_Delete = function(contact_id)
	{
		$.post("?action=delete_contact", {
			id: contact_id
		}, function(x) {
			if(x.success) {
				// Refresh the contacts grid in the 4th tab
				contact_Grid_Unselect();
				$('#tab_contacts #ctx-grid').jqxGrid('updatebounddata');

				// For some weird freekin' reason the Address Book tree display is affected when displaying an alert?
				// ... so, introduce a delay until the message is shown, so we are on the listing tab when it pops up.
				setTimeout(function() {
					cargohub.its.alert("Contact has been deleted.", "Success !", "thumb_up.png");
				}, 500);
			} else {
				cargohub.its.alert(x.reason, "Message from server", "events.png");
			}
		});
	},

	/**
	 * Prepares the controls on the Message Options modal
	 */
	setup_Msg_Options_Modal = function($window, config)
	{
		// Right side - The contact search scope block (checkboxes)
		$("div#contact-search-scope > div", $window).each(function() {
			var css_id = $(this).data('css'), check_state = self.config.csscope && self.config.csscope[css_id] > 0;

			$(this).jqxCheckBox({
				theme: cargohub.jqxtheme,
				checked: check_state
			})
			/**
			 * Event: A checkbox is changed, update self.config.csscope
			 */
			.on('change', function(event)
			{
				self.config.csscope[css_id] = event.args.checked;
			});
		});

		// When closing this modal, some other controls might get updated data
		$window.on('close', function() {
			if(config.css_update)
				cargohub.contact_search.update_Params(config.css_update, {
					css: JSON.stringify(self.config.csscope)
				});
		});
	},

	/**
	 * Prepares and sets events for the global toolbar which changes content depending on the active tab and other factors
	 */
	setup_Toolbar = function()
	{
		var tpl_data = {_itc: input_theme_classes};

		// setup toolbar
		$(".toolbar button")
		.jqxButton({ theme: cargohub.jqxtheme })
		.on('click', function()	{
			var data = $(this).data();
			switch(data.op) {
				// general actions
				case 'tab-list': // Select first tab ( Conversation lists )
					$('#comm-center').jqxTabs('select', 0);
					break;

				case 'tab-compose': // Select 3rd tab ( Compose new message )
					if(compose_modified) {
						if(!confirm("Compose tab contains content.\nAre you sure you want to clear it and start fresh?"))
							return;
					}

					compose_defaults = null;
					cargohub.contact_search.destroy("compose-recipient");
					open_css_key = null;
					delete loaded_tabs['tab_compose'];
					$('#comm-center').jqxTabs('select', 2);
					break;

				case 'tab-contacts': // Select fourth tab ( Contacts list )
					$('#comm-center').jqxTabs('select', 3);
					break;

				// tab_list
				case 'con-refresh': // Refresh the currently selected list grid
					convo_Grid_Unselect();
					$('#tab_list #convo-grid').jqxGrid('updatebounddata');
					break;
				// -- The next buttons are only visible when a conversation is selected
				case 'comm-read': // View selected conversation/message
					$('#comm-center').jqxTabs('select', 1);
					break;

				case 'comm-reply': // View selected conversation/message and also activate write area with the original message sender selected
					$('#comm-center').jqxTabs('select', 1);
					setTimeout(function() {
						quickreply_init = "reply";
						$("#tab_conversation #convo-quickreply #reply-content").focus();
					}, 750); // While it only takes 250ms for the tab to switch, it waits slightly longer to allow the tab to initialize
					break;

				case 'comm-forward': // View selected conversation/message and activate write area into forward mode ( allowing to pick a contact from addressbook )
					$('#comm-center').jqxTabs('select', 1);
					setTimeout(function() {
						quickreply_init = "forward";
						$("#tab_conversation #convo-quickreply #reply-content").focus();
					}, 750); // While it only takes 250ms for the tab to switch, it waits slightly longer to allow the tab to initialize
					break;

				case 'comm-move': // Displays a dropdown menu with all custom folders, right below the button, which allow moving all selected conversations to a custom folder
					// -- Note: Only conversations from "a-inbox", "b-sent", "c-custom", "e-bin" can be moved
					break;

				case 'comm-trash': // Move operation which will put all selected conversations in the user's "e-bin" folder, while also setting the conversation-folder relation status to "trash"
					$.post("?action=change_label_relation", {
						convos: JSON.stringify(selected_convos),
						label_id: current_label,
						target_label: "e-bin",
						status: "trash",
					}, function(x) {
						if(x.success) {
							convo_Grid_Unselect();
							cargohub.its.alert("Conversation has been sent to the trash", "Operation completed", "trash.png");
							$('#tab_list #convo-grid').jqxGrid('updatebounddata');
						} else {
							cargohub.its.alert(x.reason, "Message from server", "events.png");
						}
					});
					break;

				case 'trash-restore': // Restores a trashed conversation, setting it's conversation-folder relation status back to "normal"
					// -- If the original sender is the logged in user, it's placed into "Sent mail", if not, it's placed into "Inbox". From there, the user can move it to other folders

					// TODO: Support the hybrid trash ( containing both conversations and individual messages inside conversations )
					$.post("?action=change_label_relation", {
						special_op: "trash_restore",
						item_id: active_convo.id,
						label_id: current_label
					}, function(x) {
						if(x.success) {
							convo_Grid_Unselect();
							cargohub.its.alert("Conversation has been restored", "Operation completed", "stock_undelete.png");
							$('#tab_list #convo-grid').jqxGrid('updatebounddata');
						} else {
							cargohub.its.alert(x.reason, "Message from server", "events.png");
						}
					});

					break;

				case 'trash-delete': // Set the conversation-folder relation status to "deleted" making it invisible in the "Trash"
					// -- Note: The conversation is not actually deleted, just made completely invisible for the user
					// -- The system will later periodically clean conversations which are "deleted" for all their participants
					if(confirm("Are you sure you want to permanently destroy the conversation(s) ?\nThe operation is not reversible !"))
						$.post("?action=change_label_relation", {
							convos: JSON.stringify(selected_convos),
							current_label: current_label,
							status: "deleted",
						}, function(x) {
							if(x.success) {
								convo_Grid_Unselect();
								cargohub.its.alert("Conversation has been restored", "Operation completed", "stock_undelete.png");
								$('#tab_list #convo-grid').jqxGrid('updatebounddata');
							} else {
								cargohub.its.alert(x.reason, "Message from server", "events.png");
							}
						});

					break;

				case 'trash-empty': // Set ALL conversations from the trash to status "deleted" without having to select them individually
					// -- Note: The conversations are not actually deleted, just made completely invisible for the user
					// -- The system will later periodically clean conversations which are "deleted" for all their participants
					if(confirm("Are you sure you want to permanently destroy ALL contents of the trash bin ?\nThe operation is not reversible !"))
						$.post("?action=change_label_relation", {
							special_op: "trash_empty",
							current_label: current_label
						}, function(x) {
							if(x.success) {
								if(x.removed > 0) {
									cargohub.its.alert(x.removed+" conversations(s) have been destroyed", "Janitorial services completed", "trash_empty.png");
									$('#tab_list #convo-grid').jqxGrid('clear');
								} else
									cargohub.its.alert("Trash can already shiny clean", "Nothing changed", "trash_empty.png");
							} else {
								cargohub.its.alert(x.reason, "Message from server", "events.png");
							}
						});

					break;

				// tab_conversation
				case 'cnv-back':
					// The conversation back button serves different purposes:
					// -- If normal conversation has been selected from the #list tab, it goes back there
					// -- If a special conversation has been loaded, it will return to the original module which pushed the "load_config" to this module

					if(self.config.load_config.location) {
						document.location = self.config.load_config.location.url;
					} else {
						// Nothing special, it just goes back to #list tab.
						$('#comm-center').jqxTabs('select', 0);
					}
					break;

				case 'cnv-close':
					// Closes the current active conversation and moves to #list tab
					if(quickreply_modified) {
						if(!confirm("Quick reply area contains content.\nAre you sure you want to discard the message and close the conversation ?"))
							return;
					}

					cargohub.contact_search.destroy('reply-recipient');

					// similar to unselecting the row, makes the tab_conversation disabled and redirects the user to the listing
					$('#comm-center').jqxTabs('select', 0);
					setTimeout(convo_Grid_Unselect, 300); // Waits a bit for the tab system to move (which takes 250ms), then clears the message tab by unselecting the grid
					break;

				case 'cnv-reply':
					// Simulates a click inside the reply box (which defaults to "reply" mode)
					quickreply_init = "reply";
					$("#tab_conversation #convo-quickreply #reply-content").focus();
					break;

				case 'cnv-forward':
					// Same as for reply, but a contact is not automatically chosen. Instead the entire conversation is copied into the box and no contact is selected
					quickreply_init = "forward";
					$("#tab_conversation #convo-quickreply #reply-content").focus();
					break;

				case 'cnv-options':
					// Opens the message writing options modal setup for the "Conversation" tab.
					// -- At the moment it is able to change contact search system scope (self.config.csscope)
					if(!popups.msgoptions) {
						cargohub.its.init_Modal_Window('msg-options', {"ok": "Ok"});
						popups.msgoptions = true;
					}

					tpl_data.css_options = true; // Enable the Contact Search Scope options in the options window
					if(self.config.load_config.issue_id)
						// We have an active issue, permit activating the "Current issue" option
						tpl_data.active_issue = true;

					var mo_config = {
						css_update: "reply-recipient"
					};
					cargohub.its.open_Modal_Window('msg-options', 320, 270, "Options for replying and forwarding", $('#template-msg-options').html().present(tpl_data), setup_Msg_Options_Modal, mo_config);
					break;

				case 'cnv-external':
					// Checks if the conversation has the "access_key" set and redirects to the external view of the conversation ( which allows an external party to see and/or reply to the conversation )
					if(!active_convo.access_key) {
						// Doesn't have one, must do an ajax request to update the conversation with an "access_key" ( by default all conversations that don't go to an external contact have this value as null )
						$.post("?action=convo_access_key", {
							id: active_convo.id,
						}, function(x) {
							if(x.success) {
								document.location = "/?action=external_comm&key="+x.access_key;
							} else {
								cargohub.its.alert(x.reason, "Message from server", "events.png");
							}
						});
					} else
						document.location = "/?action=external_comm&key="+active_convo.access_key;
					break;

				// tab_compose
				case 'compose-back':
					// The compose back button serves different purposes:
					// -- If normal conversation has been selected from the #list tab, it goes back there
					// -- If composing as the result of a "forward" command from the #conversation tab, it goes back to that conversation
					// -- If loading in custom mode, and an external location is present (self.config.load_config.location), it will return to that location

					if(self.config.load_config.location) {
						document.location = self.config.load_config.location.url;
					} else {
						// Nothing special, it just goes back to #list tab.
						$('#comm-center').jqxTabs('select', 0);
					}
					break;

				case 'compose-send':
					// Always active, but what it does is simply verify the status of the lower button and triggers it's click event
					if($("#tab_compose #compose-send").jqxButton('disabled'))
						cargohub.its.alert("Sending function is disabled until a valid contact has been selected");
					else
						$("#tab_compose #compose-send").trigger("click");

					break;

				case 'compose-discard':
					// Forwards the click to the lower button
					$("#tab_compose #compose-discard").trigger("click");
					break;

				case 'compose-options':
					// Opens the message writing options modal setup for the "Conversation" tab.
					// -- At the moment it is able to change contact search system scope (self.config.csscope)
					if(!popups.msgoptions) {
						cargohub.its.init_Modal_Window('msg-options', {"ok": "Ok"});
						popups.msgoptions = true;
					}

					tpl_data.css_options = true; // Enable the Contact Search Scope options in the options window
					if(self.config.load_config.issue_id)
						// We have an active issue, permit activating the "Current issue" option
						tpl_data.active_issue = true;

					var mo_config = {
						css_update: "compose-recipient"
					};
					cargohub.its.open_Modal_Window('msg-options', 320, 270, "Options for composing a message", $('#template-msg-options').html().present(tpl_data), setup_Msg_Options_Modal, mo_config);
					break;

				// tab_contacts
				case 'vcard-add': // Initialize the vcard tab with empty data, without and ID, which on save will create a new contact inside the currently selected folder

					// TODO: Check if the current contact is changed and not yet saved and warn

					contact_Grid_Unselect("Create new contact");

					tab_load_params = {
						label_id: current_conlab,
						label_type: current_conlab_type,
						create: true
					};

					$('#comm-center').jqxTabs('enableAt', 4);
					$('#comm-center').jqxTabs('select', 4);
					break;
				// Refresh the currently selected contacts grid
				case 'ctx-refresh':
					contact_Grid_Unselect();
					$('#tab_contacts #ctx-grid').jqxGrid('updatebounddata');
					break;
				// -- The next buttons are only visible when a conversation is selected
				case 'ctx-details': // Switch to the already activated tab_vcard which will read the details when selected
					$('#comm-center').jqxTabs('select', 4);
					break;

				case 'ctx-delete': // Delete selected contacts
					if(confirm("Are you sure you want to delete selected contact(s) ?\nThe operation is irreversible !"));
//					contact_Delete(selected_contact.id);
					break;

				// tab_vcard
				case 'show-contacts':
					$('#comm-center').jqxTabs('select', 3);
					break;

				case 'vcard-close':
					// similar to unselecting the row, makes the tab_vcard disabled and redirects the user to the contacts listing
					$('#comm-center').jqxTabs('select', 3);
					setTimeout(contact_Grid_Unselect, 300); // Waits a bit for the tab system to move (which takes 250ms), then clears the address editor tab
					break;

				case 'vcard-save':
					// Assambles the date from all the fields which are editable on this tab and does an ajax request with the JSON encoded string

					var contact_status = cargohub.contact_editor.check_Status(), contact = null;
					if(contact_status.changed && contact_status.valid) {
						contact = cargohub.contact_editor.read_vCard();
						if(active_contact_record)
							contact.id = active_contact_record.id;

						// Save the contact changes to the database (or, create the contact draft if it didn't previously exist)
						$.post("/global/contact?action=save_contact", {
							id: contact.id, // Might be null, in this case the contact is actually created
							name: contact.name,
							email: contact.email,
							xdata: JSON.stringify(contact.xdata),
							type: current_conlab_type
						}, function(x) {
							// On success, x.id should contain the ID
							if(x.success) {
								// If this was a create operation, close the tab and show the grid
								if(!active_contact_record) {
									contact_Grid_Unselect();
									$('#tab_contacts #ctx-grid').jqxGrid('updatebounddata');
								}

								// For some weird freekin' reason the Address Book tree display is affected when displaying an alert?
								// ... so, introduce a delay until the message is shown, so we are already on the listing tab when it pops up.
								setTimeout(function() {
									cargohub.its.alert(x.response, "Success !", "thumb_up.png");
								}, 500);
							} else
								cargohub.its.alert(x.reason, "Message from server", "events.png");
						});
					}
					break;

				case 'vcard-delete':
					// TODO: Delete button should not be visible for "New contact"
					// Asks for the server to delete current contact then refreshes the grid like for saving
					if(confirm("Are you sure you want to delete this contact ?\nThe operation is irreversible !"));
//						contact_Delete(selected_contact.id);
					break;
			}
		});
	},

//===== Global Helper Functions

	/**
	 * Widget configurator for any calendar column filter
	 */
	right_Aligned_Calendar = function (column, columnElement, widget) {
        widget.jqxDateTimeInput({
        	dropDownHorizontalAlignment: 'right'
        });
    },

//===== Tab Functionality Setup =====//

	/**
	 * Setup the "List" tab (which is the first tab of the message center - It shows all conversations linked to a communication label ( like "Inbox" )
	 * -- Is visualised as a splitter, with a tree of labels to choose from on the left side, and the list of messages on the right side as a grid.
	 */
	setup_Tab_List = function()
	{
		var tab_height = 500,
		grid_first_time = true,
		columns_changed = false, // Indicates that the column configuration has changed as the result of picking a different label

		/**
		 * Callback function which returns content of cell in the message/conversation lists with some formatting
		 */
		convo_Cell_Renderer = function(row, columnfield, value, defaulthtml, columnproperties)
		{
			var changed_value = null, data = $('#tab_list #convo-grid').jqxGrid('getrowdata', row);

			// Column specific transformations
			if(columnfield == 'starter')
				defaulthtml = defaulthtml.replace('<div ', '<div class="contact-item" data-con_id="'+data.starter_id+'" ');

			if(columnfield == 'dt')
				changed_value = $.format.date(value, cargohub.date_format);

			// Replace the untrasnformed value with the transformed one.
			if(changed_value) {
				defaulthtml = defaulthtml.replace('>'+ value +'<', '>'+ changed_value +'<');
			}

			// If unread, add bold to the default html
			if(data.status == "unread")
				defaulthtml = defaulthtml.replace('style="', 'style="font-weight: bold;');

			return defaulthtml;
		},

		// Column definitions used in the various folder types
	    convos_col_defs = {
			// There's only one type right now - The "Conversation" type
			convo: [
				{ text: 'Started by', datafield: 'starter', width: 180, filtertype: "list", cellsrenderer: convo_Cell_Renderer  },
				{ text: 'Msgs', datafield: 'count_msgs', width: 55, cellsrenderer: convo_Cell_Renderer, filtertype: 'number', align: "center", cellsalign: "center" },
				{ text: 'Conversation subject', datafield: 'descr', cellsrenderer: convo_Cell_Renderer  },
				{ text: 'Last Message', datafield: 'dt', width: 100, cellsrenderer: convo_Cell_Renderer, filtertype: 'date', createfilterwidget: right_Aligned_Calendar }

			]
		},

		/**
		 * The DataAdapter used to load the conversations into the grid
		 */
		convos_data_source = new $.jqx.dataAdapter({
			url: '?action=read_label_convos',
			data: {},
			datatype: "json",
			datafields: [
				{ name: 'status' },
				{ name: 'id', type: 'int' },
				{ name: 'type' },
				{ name: 'link_id', type: 'int' },
				{ name: 'access_key' },
				{ name: 'starter_id', type: 'int' },
				{ name: 'starter' },
				{ name: 'dt', type: 'date' },	// This is actually the last_msg_time (which indicates when the conversation was last updated)
				{ name: 'descr' },
				{ name: 'count_msgs', type: 'int' }
			],
			id: 'id'
		}),
		// Assume the first column definition is for the "Inbox" / TODO: Support ability to load another label at first load (instead of inbox)
		convos_columns = convos_col_defs.convo,

		/**
		 * Indicates if the event tree_Label_Select is triggered by user (false) or by the code itself (true)
		 * -- This is used for the auto-select-back on tree element which are not actually selectable.
		 */
		label_CodeSelect = false,

		/**
		 * Event: Select an item in the label tree
		 */
		tree_Label_Select = function(event)
		{
			if(label_CodeSelect)
				return;

			// check if clicking on a folder (not selectable)
			if(!$(event.args.element).data('selectable')) {
				label_CodeSelect = true;
				$("#tab_list #label-tree").jqxTree('selectItem', $('#label-tree li#label_'+current_label)[0]);
				label_CodeSelect = false;
				return;
			}

			// get the correct data
			var label = $('#tab_list #label-tree').jqxTree('getItem', event.args.element),

			// selected label
			selected_label = label.id.substr(6);

			// clicked on the already selected one ? Ignore trigger
			if(selected_label == current_label)
				return;

			// Prepare the grid's data source with the information related to selected label
			convos_data_source._source.data.label_id = current_label = selected_label;
			convos_data_source._source.data.label_type = current_label_type = $(label.element).data('type');

			// Is this the first time a label is selected?
			if(grid_first_time) {
				// setup the grid which shows the conversations in this label
				$('#tab_list #convo-grid').jqxGrid({
					theme: cargohub.jqxtheme,
					source: convos_data_source,
					columns: convos_columns, columnsmenu: false,
					width: $("#tab_list").jqxSplitter('panels')[1].element.width(), height: tab_height,
					pageable: true, pagesize: 16, pagesizeoptions: ['10', '16', '26', '42', '68', '110', '179', '290'],
					sortable: true, filterable: true, showfilterrow: true,
					selectionmode: "multiplerowsextended"
				})
				/**
				 * Event, the grid is loaded - Set the columns to proper mode on the selected label
				 */
				.on('bindingcomplete', function(event)
				{
					if(columns_changed) {
						$('#tab_list #convo-grid').jqxGrid({
							columns: convos_columns
						});
						columns_changed = false; // Done, grid is updated
					}

					// Every time the grid is reloaded, refresh the hover event on the contact cells (with a small delay to give the grid time to initialize)
				})
				/**
				 * Event: When a row is selected, varios things happen in the module, both in the button bar and the 2nd tab (loads the message in the background)
				 */
				.on('rowselect', function (event)
				{
					// Uses a tiny delay to make sure the actions that need to happen when a row is selected only happen once even when multiple rows are drag-selected,
					// because jqxGrid fires a "rowselect" event for each of the selected rows
					donrey.final_Delay('list_grid_rowselect', function() {

						var bar_prefix = '', convo_tab_title = '', row_indexes = $('#tab_list #convo-grid').jqxGrid('getselectedrowindexes');

						// Depending on currently selected label type, certain bars are enabled
						switch(current_label_type) {
							case 'a-inbox':
							case 'b-sent':
							case 'c-user':
								bar_prefix = "standard_";
								break;
							case 'e-bin':
								bar_prefix = "trashed_";
								break;
							default:
								// TODO: The rest
						}

						selected_convos.length = 0; // Reset list of conversations
						if(row_indexes.length > 1) {
							// Multiple rows selected, make sure the "convo_single_select" bar is hidden, and also disable Tab 1 (Conversation), while setting it's title to "Multiple selected" to indicate why it's disabled

							clear_Item_Tab(1, "Multiple selected", true);

							for(c=0; c<row_indexes.length; c++) {
								selected_convos.push($('#tab_list #convo-grid').jqxGrid('getrowdata', row_indexes[c])['uid']);
							}

							// Show only the "any" bar (valid for either single or multiple select)
							disable_Toolbar_Item('cc_ops-', bar_prefix+'single_select');
							enable_Toolbar_Item('cc_ops-', bar_prefix+'any_select');
						} else {
							active_convo = $('#tab_list #convo-grid').jqxGrid('getrowdata', row_indexes[0]);
							active_convo_record = convos_data_source.records[row_indexes[0]];

							selected_convos.push(active_convo.id);

							// Set the title of the 2nd tab, depending on folder type
							switch(current_label_type) {
								case 'a-inbox':
								case 'c-user':
								case 'b-sent':
//								case 'd-drafts': // TODO: Resolve drafts
								case 'e-bin':
									convo_tab_title = active_convo.descr.truncate(25, true);
									break;
								// TODO: The rest
								case 'system':
									break;
								case 'issue':
									break;
								case 'broadcast':
									break;
							}

							cargohub.contact_search.destroy('reply-recipient');
							clear_Item_Tab(1, convo_tab_title);
							$('div.toolbar button#cnv-back-button').attr('title', 'Back to list').html('<img src="/img/icons16/arrow_undo.png" alt="Back to list" /> Back to list'); // Back button role - Switch to tab 0

							// Prepare the tab loading parameters to the selected conversation
							tab_load_params = {
								label_id: current_label,
								label_type: current_label_type,
								convo_id: active_convo.id
							};
							// ... and enable the tab
							$('#comm-center').jqxTabs('enableAt', 1);

							enable_Toolbar_Item('cc_ops-', bar_prefix+'single_select');
							enable_Toolbar_Item('cc_ops-', bar_prefix+'any_select');
						}

					}, 50);
				})
				/**
				 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
				 */
				.on('filter', function(event)
				{
					convo_Grid_Unselect();
				})
				/**
				 * Event: On doubleclicking, do the same as selecting the Message tab
				 */
				.on('rowdoubleclick', function() {
					$('#comm-center').jqxTabs('select', 1);
				});

				// Done initializing the grid
				grid_first_time = false;
			} else {
				// remove selection, grid content will change
				convo_Grid_Unselect();

				// change columns, depending on label_type (will happen after data is loaded, on bindingcomplete event)
				switch(current_label_type) {
					case 'a-inbox':
					case 'b-sent':
					case 'c-user':
					case 'd-drafts':
						break;
					case 'e-bin':
						convos_columns = convos_col_defs.convo;
						// show the toolbar item specific to this label type
						enable_Toolbar_Item('cc_ops-', 'trash_empty');
						break;

					// TODO: Function to externally add the Issue TrackID filter, and also a way to see all replies/all exports for an issue, not necesarely for the current user (Issue communication overview)
					// -- In this case, the normal folders won't be visible (Inbox, Sent, etc.), and Message Center will be used as a communication overview, not as a personal module
					// -- Alternatively, use the code from this module in a completely new one which is used to overview messages which are not related to an user (organization messages)

					// TODO: Pseudo-labels
/*					case 'issreply':
					case 'issexport':
						grid_columns = [
							{ text: 'Issue TrackID', datafield: 'issue_track', width: 120 },
							{ text: 'To E-mail', datafield: 'email', width: 170 },
							{ text: 'Subject', datafield: 'subject' },
							{ text: 'Date', datafield: 'dt', width: 110, cellsrenderer: format_Date, filtertype: 'date' }
						];
						break;
*/				}
				columns_changed = true;

				// Trigger grid update
				$('#tab_list #convo-grid').jqxGrid('updatebounddata');
			}

			// Update the tab header to show the currently selected folder
			$('#comm-center li.jqx-tabs-title:first div.jqx-tabs-titleContentWrapper').html($(">div", label.element).html());
		}

		;

		// initialize the splitter between labels and messages
		$("#tab_list").jqxSplitter({
			theme: cargohub.jqxtheme,
			width: cargohub.fullwidth, height: tab_height,
			panels:	[{
				collapsible: true,
				min: 150,
				size: 200
			}, {
				collapsible: false,
				min: 600
			}]
		}).on('resize', function(event) {
			// resize the grid automatically when the splitter's bar is moved:
			$('#tab_list #convo-grid').jqxGrid({
				width: event.args.panels[1].size
			});
		});

		// setup the tree of labels
		$('#tab_list #label-tree').jqxTree({
			theme: cargohub.jqxtheme,
			height: '100%', width: '100%'
		})
		/**
		 * Event: An item is selected in the label tree
		 */
		.on('select', tree_Label_Select);

		$('#tab_list #label-tree').jqxTree('expandAll');

		// Autoselect a label with a certain ID (This should also trigger construction of the messages grid), if it exists
		if(self.config.load_config.label_id)
			$("#tab_list #label-tree").jqxTree('selectItem', $('#tab_list li#label_'+self.config.load_config.label_id)[0]);
		// Else just select the first item in the tree, whatever it is (it should be the "Inbox" in most cases)
		else
			$("#tab_list #label-tree").jqxTree('selectItem', $('#tab_list #label-tree li.jqx-tree-item-li:first')[0]);
	},

	/**
	 * Setup the "Conversation" tab, which while initially disabled, it shows up when selecting a conversation
	 * -- Contains list of messages, contacts and a reply box
	 */
	setup_Tab_Conversation = function()
	{
		if(!self.config.tab_conversation) {
			// Apparently there was an error in loading the tab, do not try to initialize stuff on it. Also disable the buttons... Reply/Forward/Set labels... etc.
			disable_Toolbar_Item('cc_ops-', 'conversation_actions');
			return;
		}

//		dd(self.config.tab_conversation);

		quickreply_modified = false;
		var $message_menu = null, action_message_data = null, // The buttons from which message have been pressed ( for Reply, or the Action menu ), and the Data() for the message corresponding to the button

		quickreply_active_mode = null, // Null when the box is closed, "reply" when it works in reply mode, "forward" when it works in forward mode
		primary_recipient = null, primary_recipient_cssdata = null, // Primary recipient in the reply box (id, email, name); Data used to initialize the contact search but also contains the search result
		canned_selector = false, // Is the jqxDDL selector hidden or visible ?
		signature_added = false,

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
		 * @param default_content - Optionally, place a content in the textarea, but the focus will still be on top ( above the content )
		 */
		setup_Reply_Box = function(default_content)
		{
			if(quickreply_active_mode)
				return; // Already set up and open, nothing to do.

			// Remove the orginal border (set width to 0px)
			$('#convo-quickreply #quickreply-frame').css("border-width", 0);

			// Prepare and show the primary recipient bar (To:)

			// Initial mode of operation (reply/forward), changes the picture
			if(quickreply_init == "reply")
				$("#convo-quickreply #reply-recipient > img").prop('src', "/img/icons16/mail_reply_sender.png").prop('alt', "Reply to message");
			if(quickreply_init == "forward")
				$("#convo-quickreply #reply-recipient > img").prop('src', "/img/icons16/mail_forward.png").prop('alt', "Forward message");

			if(primary_recipient_cssdata)
				$("#convo-quickreply #reply-recipient span").addClass('contact-item').removeClass('gray').text(primary_recipient_cssdata.combobox_value.escapeHtmlEntities()).data("con_id", primary_recipient_cssdata.contact_id);
			$("#convo-quickreply #reply-recipient").show();

			// Quickreply primary recipient line, is handled by the "contact search system" plugin
			cargohub.contact_search.setup_CSS("reply-recipient", "#tab_conversation #reply-recipient", {
				result_template: $("#template-contact-result").html(),
				css: JSON.stringify(self.config.csscope),
				width: 676,
				on_focus: function(key) {
					open_css_key = key;
					donrey.cancel_Delay('contact-menu');
				},
				/**
				 * Event: The value of the combobox changes. Contact search returns either the found contact or just the combobox value
				 * @param key
				 * @param state
				 */
				on_change: function(key, state)
				{
					quickreply_modified = true;
					primary_recipient_cssdata = state;
				},
				/**
				 * Event: Contact search is closed without user selecting anything
				 * @param key
				 * @param emaildata
				 */
				on_close: function(key, recipient)
				{
					open_css_key = null;
					if(recipient) {
						$("button#reply-send").jqxButton({disabled: false}).jqxButton('render');
						primary_recipient = recipient;
					} else {
						$("button#reply-send").jqxButton({disabled: true}).jqxButton('render');
						primary_recipient = null;
					}
				}
			}, primary_recipient_cssdata);

			// Set the "primary_recipient" too if contact ID is known, as it's used when pressing the "Send message" button
			if(primary_recipient_cssdata && primary_recipient_cssdata.contact_id)
				primary_recipient = {
					id: primary_recipient_cssdata.contact_id,
					name: primary_recipient_cssdata.combobox_value
				};

			// Reply canned messages selector (A jar icon on the right side) - When pressed, it temporarely hides the "Subject" input, creates a jqxDropDownList with the canned messages in it's place
			// -- Canned messages are only available in user-mode, the jar doesn't show up for public submit/transfer
			// -- The jqxDDL gets it's content remotely, from the templates with usage class "issmessage" to which the user has access to
			// -- Clicking the jar while the selector is in place reverses the process, allowing editing of the subject.
			$("#convo-quickreply #reply-canned-toggle")
			.off('click')
			.on('click', function() {
				if(canned_selector) {
					// Destroy the selector, hide the div, show the input
					$("#convo-quickreply #reply-subject > input").show();
					$("#convo-quickreply #reply-subject > div").off('select').jqxDropDownList('destroy');

					canned_selector = false;
				} else {
					// Extra data for the dataAdapter
					if(self.config.tab_conversation.type == 'issue') {
						// If this conversation is issue-linked, canned messages are of type "issreply", otherwise "personal"
						canned_data_source._source.data = {
							usage_class: "issreply", // If this conversation is issue-linked, canned messages are of type "issreply", otherwise "personal"
							issue_id: self.config.tab_conversation.link_id
						};

					} else
						canned_data_source._source.data = {
							usage_class: "personal"
						};

					// Hide the input
					$("#convo-quickreply #reply-subject > input").hide();

					// Instance the selector
					$("#convo-quickreply #reply-subject").append('<div id="reply_canned_select"></div>');
					$("#convo-quickreply #reply-subject > div").jqxDropDownList({
						theme: cargohub.jqxtheme,
						source: canned_data_source,
						displayMember: "description",
						valueMember: "message",
						width: 725, height: 22,
						promptText: "Select canned message (click to choose)",
						autoDropDownHeight: true
					}).on("select", function(e)
					{
						if (e.args && e.args.item) {
							$("#convo-quickreply #reply-subject > input").removeClass('gray').val(e.args.item.originalItem.title);
							// Short delay to allow focus to work
							$("#convo-quickreply #reply-canned-toggle").trigger('click');
							setTimeout(function() {
								$("#convo-quickreply #reply-content").val(e.args.item.value);
								$("#convo-quickreply #reply-content").jqxEditor('focus');
							}, 100);
							quickreply_modified = true;
						}
					});

					canned_selector = true;
				}
			});

			$("#convo-quickreply #reply-content").jqxEditor({
				theme: cargohub.jqxtheme,
				stylesheets: ["/css/default/cargo_jqxeditor.css"],
				width: 767,
				height: 300,
				lineBreak: "br",
				tools: 'bold italic underline | format font size | color background | left center right | outdent indent | ul ol | image link | clean'
			})
			/**
			 * Event: Content editor is changed - Set the "Modified" flag
			 * - Aparently the change event is not triggered??
			 */
			.on('change', function() {
				quickreply_modified = true;
			});

			if(default_content)
				$("#convo-quickreply #reply-content").val(default_content);
		// Show the reply toolbar
			$("#convo-quickreply #quickreply-commands").fadeIn();
			// Hide the help bar, not needed while the box is active
			$("#convo-quickreply div#quickreply-help").hide();

			if(quickreply_init == "forward")
				setTimeout(function() {
					$("#convo-quickreply #reply-recipient").focus();
				}, 100);

			// All set, scroll down to the reply box
			$('html, body').animate({
				scrollTop: $("#tab_conversation #convo-quickreply").offset().top
			}, 1000, function() {
				// Focus the editor when the scroll animation is complete.
				$("#convo-quickreply #reply-content").jqxEditor('focus');
			});
		},

		/**
		 * Changes the content of the already open reply box
		 * -- Optional new content and subject, and also sets the ContactSearch to the value in "primary_recipient_cssdata"
		 *
		 * FIXME: It does not change the reply recipient yet ! Just the content/subject
		 */
		change_Reply_Box = function(new_content, new_subject)
		{
			if(!quickreply_active_mode)
				return; // Can't change the box, it's not currenly open

//			cargohub.contact_search.set("reply-recipient", primary_recipient_cssdata);

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

		// Push the incoming convo ( returned by hybrid response ) into active_convo, if it exists
		if(self.config.tab_conversation)
			$.extend(true, active_convo, self.config.tab_conversation);

		// The "Back" button changes text and role depending on some variables
		if(active_location)
			$("button#cnv-back-button").html("<img src='/img/icons16/"+active_location.data.icon+".png' alt='Back'> "+active_location.label).prop("title", "Return to "+active_location.label);

		// If there is an active convo record and it's status is "unread", make it read (not bold) at this point
		// -- (The conversation is updated on the server side as well even before the conversation is displayed)
		if(active_convo_record && active_convo_record.status == 'unread') {
			active_convo_record.status = "normal";
			$('#tab_list #convo-grid').jqxGrid('refresh');
		}

		// Change the title of this tab to the parameter coming from the hybrid tab load response
		if(self.config.tab_conversation.tab_title)
			$('#comm-center li.jqx-tabs-title:eq(1) div.jqx-tabs-titleContentWrapper').html("<img src='/img/icons16/conversation.png' alt='"+self.config.tab_conversation.tab_title+"' /> "+self.config.tab_conversation.tab_title);
		// Then clear the title from config so next conversations loaded do not change it again to some old version
		delete self.config.tab_conversation.tab_title;

		// Clicking on closed message hides it and shows the "open" version insted
		$("#tab_conversation div.convo-msg-closed").on("click", function(e) {
			var $this = $(this);

			$this.hide();
			$("div.convo-msg-open", $this.parent()).show();
		});

		// Clicking on the header of open messages closes it and shows the "closed" version instead
		$("#tab_conversation div.convo-msg-open .msg-header").on("click", function(e) {
			var $this = $(this).parent();

			if(!(e.target.attributes.role && e.target.attributes.role.value == "button")) {
				$this.hide();
				$("div.convo-msg-closed", $this.parent()).show();
			}
		});

		// Make the message-related buttons fancy (two glued buttons)
		$("#tab_conversation div.convo-msg-open .msg-header .msg-buttons").each(function() {
			$(this).jqxButtonGroup({
				theme: cargohub.jqxtheme,
				mode: 'default',
				enableHover: true
			})
			.on('buttonclick', function(e) {
				var $this = $(this).parents("div.convo-msg-open"); // The message area that contains the button

				// Grab the message data, sender and recipient info, subject and content
				action_message_data = $this.parent().data();
				action_message_data.from_name = $.trim($this.find("span.from-name").text());
				if(!$this.find("div.msg-content").data('empty'))
					action_message_data.msg_content = $.trim($this.find("div.msg-content").html());

				action_message_data.to_id = $this.find("span.to-name").data("con_id");
				if(action_message_data.to_id)
					action_message_data.to_name = $.trim($this.find("span.to-name").text());

				if(!action_message_data.msg_content || action_message_data.msg_content.length == 0)
					delete action_message_data.msg_content;

				if(e.args.index == 0) {
					// Pressing Reply focuses the quickreply box in "reply" mode. Focusing grabs the data from "action_message_data" variable
					if(quickreply_init == "reply") {
						change_Reply_Box(quote_From_Action_Message_Data());
					} else
						$("#convo-quickreply #reply-content").focus();
					quickreply_init = "reply";
				} else
				if(e.args.index == 1) {
					// message-actions
					if(!$message_menu) {
						$message_menu = $($('#message-actions').html()).jqxMenu({
							theme: cargohub.jqxtheme,
							autoOpenPopup: false,
							animationShowDuration: 0,
							animationHideDuration: 0,
							mode: 'popup'
						})
						/**
						 * Event: Click on message context menu item
						 */
						.on('itemclick', function(e) {
							switch($(e.args).data('op')) {
								// Reply option focuses the quickreply box in "reply" mode. Focusing grabs the data from "action_message_data" variable
								case 'reply':
									if(quickreply_init == "reply") {
										change_Reply_Box(quote_From_Action_Message_Data());
									} else
										$("#convo-quickreply #reply-content").focus();
									quickreply_init = "reply";
									return;

								// This message is placed into the "Compose" box (title and content), with the purpose of forwarding information to another contact without involving this conversation
								case 'copy_to_new':
									if(compose_modified) {
										if(!confirm("Compose tab contains content.\nAre you sure you want to discard and put the content of this message instead?"))
											return;
									}

									// Clear any previous "Compose" tab content
									cargohub.contact_search.destroy("compose-recipient");
									open_css_key = null;
									delete loaded_tabs['tab_compose'];

									// Setup the parameters for loading the "Compose" tab with preloaded contact and message content + subject
									compose_defaults = {
//										subject: // TODO: Copy message subject when subject will be suported
										content: quote_From_Action_Message_Data()
									};

									// Ready, switch to Compose tab
									$('#comm-center').jqxTabs('select', 2);
									return;

								// The sender details option shows information about who sent this message
								case 'sender_details':
									contact_Open_Details_Modal(action_message_data.from_id);
									return;

								// Sends this message via E-mail to the intended recipient, after a confirmation
								case 'send_email':
									if(!action_message_data.to_id)
										return cargohub.its.alert("This message does not have a recipient !?");

									if(!confirm("Please confirm that you really intend to (re)send this message as an e-mail !"))
										return;

									$.post("?action=email_message", {
										to_id: action_message_data.to_id,
										msg_id: action_message_data.msg_id
									}, function(x) {
										if(x.success) {
											cargohub.its.alert(x.response, "Message from server", "events.png");
										} else {
											cargohub.its.alert(x.reason, "Message from server", "events.png");
										}
									});
									return;

								// Trashing a message removes hides it into the conversation flow
								// TODO: Not yet clear how to store in the database the information about which messages are "trashed"
								case 'trash':
									cargohub.its.alert("Trashing individual messages not yet implemented");
									return;
							}
						});
					}

					var ddbtn_pos = $(this).position();
					$message_menu.jqxMenu('open', ddbtn_pos.left - 68, ddbtn_pos.top + 27);
				}
			});
		});

		// Beside the quickreply help bar with the two action "links" also allow replying by clicking on the textarea frame
		$("#convo-quickreply #reply-content, #convo-quickreply #text_cmd_reply").on('click', function(e) {
			quickreply_init = "reply";
			$("#convo-quickreply #reply-content").focus();
		});

		$("#convo-quickreply #text_cmd_forward").on('click', function() {
			quickreply_init = "forward";
			$("#convo-quickreply #reply-content").focus();
		});

		// Quickreply subject (changes text color from inactive to active)
		$("#convo-quickreply #reply-subject input")
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
		 */
		.on('focus', "#reply-content", function() {

			var box_content = "";
			// The box is closed, prepare to open it
			if(!quickreply_active_mode) {

				// Check initialization mode, if not present default to "reply" mode
				if(!quickreply_init)
					quickreply_init = "reply";

				if(quickreply_init == "reply") {
					// When the message-specific reply buttons have been pressed, the "action_message_data" contains information about that message.
					// Otherwise, it attempts to use the "self.config.tab_conversation.reply_to" value to search in the "Involved contacts" the correct contact to reply to.
					// If that value doesn't exist (for any reason), the reply defaults to the sender of last message in the list
					if(!action_message_data) {
						if(self.config.tab_conversation && self.config.tab_conversation.reply_to) {
							$("#tab_conversation #convo-right-side h3.contact-item").each(function() {
								if(action_message_data)
									return; // Skip contact, already found the correct one to reply to
								if(self.config.tab_conversation.reply_to == $(this).data('con_id')) {
									action_message_data = {
										from_id: self.config.tab_conversation.reply_to,
										from_name: $(this).text()
									};
								}
							});
						}
					}

					// If everything fails, attempt to pick the sender of the last message, assuming there is a message to pick from
					if(!action_message_data && $("#tab_conversation div.convo-msg").length > 0) {
						action_message_data = $("#tab_conversation div.convo-msg:last").data();
						action_message_data.from_name = $.trim($("div.convo-msg:last").find("span.from-name:first").text());
					} else

					// Prepare primary recipient for the CSS plugin
					if(action_message_data) {
						primary_recipient_cssdata = {
							combobox_value: action_message_data.from_name,
							contact_id: action_message_data.from_id
						};

						if(action_message_data.msg_content)
							box_content = quote_From_Action_Message_Data();

						// And also enable the Send button since a contact is preselected
						$("#convo-quickreply #reply-send").jqxButton({disabled: false}).jqxButton('render');
					} else
					// if at this point there still isn't any information in "action_message_data" the quickreply will automatically enter "Forward" mode, in which a contact can be selected for sending the message to
						quickreply_init = "forward";
				}

				if(quickreply_init == "forward") {
					// When forwarding, the entire content of the conversation is placed in the box ( as a series of quotes ), however cutting messages to the first sequence "> On 20"
					// (quoted parts from inside messages are not forwarded, since they will be part of the entire forward block anyway)
					var cut_index = null;
					$("#tab_conversation div.convo-msg").each(function() {

						action_message_data = $(this).data();
						action_message_data.from_name = $.trim($(this).find("span.from-name:first").text());

						action_message_data.msg_content = $.trim($(this).find("div.msg-content").html());
						cut_index = action_message_data.msg_content.indexOf("> On 20");
						if(cut_index != -1)
							action_message_data.msg_content = action_message_data.msg_content.substr(0, cut_index);

						box_content += quote_From_Action_Message_Data();
					});

					action_message_data = {};

				}

				setup_Reply_Box(box_content);
				quickreply_active_mode = quickreply_init;
			} else {
				// Quickreply already active, check if mode is changed
//				dd(quickreply_active_mode);
//				dd(quickreply_init);

//				if(quickreply_active_mode != quickreply_init)
//					cargohub.its.alert("It is not yet possible to switch from Reply to Forward and vice-versa directly. Please press Discard first");
				// TODO: Support switching from "reply" mode to "forward" mode and vice-versa
				// -- "Forward to "Reply" auto-picks a contact if none is given ( primary_recipient_cssdata ) and contact field is currently empty
				// -- "Reply" to "Forward" clears the recipient and assambles the forward content, but only if the content box was not touched.
			}
		});

		// ... and command bar
		$("#convo-quickreply #quickreply-commands button").jqxButton({
			theme: cargohub.jqxtheme
		})
		.on('click', function(e) {
			switch(e.target.id) {
				// Sends the typed reply to the selected contact
				case 'reply-send':
					if(!primary_recipient)
						return cargohub.its.alert("Invalid recipient in the recipient field.");

					// Prepare the required variables
					var reply_data = {
						mode: quickreply_active_mode,	// "reply" will simply add the same conversation ( convo_id ). "forward" however will create a new conversation from the message being sent ( like the "Compose" tab )
						cnv_id: active_convo.id,		// Used only when <mode> is "reply"
						subject: $("#convo-quickreply #reply-subject input").val(),
						content: $("#convo-quickreply #reply-content").val(),
						recipient: primary_recipient
					};

					if(reply_data.content.length < 10)
						return cargohub.its.alert("Your message is too short, please write more !");

					// If there is an active issue, communicate with the server that the reply is for an issue-linked conversation
					if(active_issue)
						reply_data.issue = active_issue;

					$.post("?action=add_reply", reply_data, function(x) {
						if(x.success) {
							convo_load_config = {
								mode: "conversation",
								convo_id: x.result.cnv_id
							};
							// If there is an active linked issue, pass it on so the conversation also shows the linked issue after reload
							if(active_issue) {
								convo_load_config.issue_id = active_issue.id;
								convo_load_config.issue_track = active_issue.track;
								convo_load_config.location_key = self.config.load_config.location_key;
							}

							// Load the module into the conversation special mode to show the newly created conversation.
							document.location = "/user/commcenter?action=custom_load&config="+ JSON.stringify(convo_load_config);
						} else {
							cargohub.its.alert(x.reason, "Message from server", "events.png");
						}
					});
					break;

				// Activates the subject line and focuses it - Normally replies do not have a subject, since the original subject of the conversation is used, but the subject can still be set using this input
				case 'reply-set-subject':
					// Show the subject line and focus it
					if(canned_selector)
						$("#convo-quickreply #reply-canned-toggle").trigger('click'); // This should close the canned messages

					// Show the subject, select it and focus it
					$("#convo-quickreply #reply-subject").show();
					$("#convo-quickreply #reply-subject input").selectRange(0, 1000).focus();
					break;

				// Shows the subject line and activates the canned messages selector (replacing the input)
				case 'reply-canned-on':
					if(!canned_selector) {
						$("#convo-quickreply #reply-subject").show();
						$("#convo-quickreply #reply-canned-toggle").trigger('click');
					}

					// Open the canned selector box (should already be visible)
					$("#convo-quickreply #reply_canned_select").jqxDropDownList('open');
					break;

				case 'reply-signature':
					if(signature_added)
						if(!confirm("Your signature seems to be already added.\nAre you sure you want to add it again ?"))
							return;

					var user_signature = cargohub.application.user_Get_Session().signature,
						original_content = $("#convo-quickreply div#reply-content").val();

					$("#convo-quickreply div#reply-content").val(original_content + "<br /><hr />" + user_signature);
					signature_added = true;
					break;

				// Cancels the reply writing operation and clears data
				case 'reply-discard':
					if(quickreply_modified) {
						if(!confirm("Quick reply area contains content.\nAre you sure you want to discard all information ?"))
							return;
					}

					// Clear the recipient, subject and content box
					cargohub.contact_search.destroy('reply-recipient');
					open_css_key = null;
					$("#convo-quickreply #reply-recipient span").html("(click to search recipient)").removeClass('contact-item, contact-error').addClass('gray').removeData("con_id");

					// Clean the subject (simple or canned) and content (jqxeditor)
					if(canned_selector)
						$("#convo-quickreply #reply-canned-toggle").trigger('click');

					$("#convo-quickreply #reply-content, #convo-quickreply #reply-subject input").val("");
					$("#convo-quickreply #reply-content").jqxEditor("destroy");
					// Unfortunately the destroy command also removes the object... so add it back
					$("#convo-quickreply #quickreply-frame").append($('<div id="reply-content" style="height: 300px;" tabindex="2"></div>'));

					// Hide the commands, hide the recipient and subject bars
					$("#convo-quickreply #reply-recipient, #convo-quickreply #reply-subject, #convo-quickreply #quickreply-commands").hide();
					// Add back the border
					$('#convo-quickreply #quickreply-frame').css("border-width", 1);
					// ... and show the help bar again
					$("#convo-quickreply div#quickreply-help").fadeIn();

					// Reset all values, clear all data
					quickreply_modified = false;
					signature_added = false;
					quickreply_init = null; quickreply_active_mode = null;
					action_message_data = null;
					primary_recipient = null; primary_recipient_cssdata = null;
					break;
			}
		});

		// Begin with the quickreply send button disabled, it will be reenabled on correct contact selection
		$("#convo-quickreply #reply-send").jqxButton({disabled: true});

		// Trigger Click on last message (so it is by open by default)
		$("#tab_conversation div.convo-msg:last div.convo-msg-closed").trigger("click");

		// Right side - Contact buttons
		$("#tab_conversation div.convo-contact button").on('click', function() {
			contact_Open_Details_Modal($(this).data('id'));
		});
	},

	/**
	 * Setup the "Compose" tab, which has a header with recipient ( To: ) and the subject line, and a body consisting of text editor (TODO: Rich Text version)
	 * -- On the bottom, the ability to add and see attachments which will be sent with the message
	 * -- The recipient box is a ComboBox, with Hybrid ability to choose either an user from the system ( by selecting him in the dropdown or typing his name - which also searches for the  user ), or by typing/searching an e-mail address
	 *   -- The e-mail address can be chosen from the Address Book by the use of a "Find" button beside the Recipient box
	 *   -- The administrator or certain users have another ability, to send a mass-message to multiple user ( either everyone for admin only, or in-organization for certain users )
	 */
	setup_Tab_Compose = function()
	{
		compose_modified = false;
		var primary_recipient = null, primary_recipient_cssdata = null,
		email_cc_list = [], cc_id = 0, cc_length = 0, // Extra recipients ( CarbonCopy ), are contact records or email/name pairs
		/**
		 * Returns True if the email is already part of the cc_list, or false if the cc_list is empty or the entry is not a part
		 */
		check_cc_duplicate = function(email)
		{
			if(cc_length==0)
				return false;

			if(email_cc_list.indexOf(email) > -1)
				return true;

			return false;
		},
		canned_selector = false, // Is the jqxDDL selector hidden or visible ?
		signature_added = false;

		// Pre-initialization configuration
		if(compose_defaults) {
			if(compose_defaults.to_id) {
				// A contact has been preselected. This is a special mode that displays the to_name value in the To: line, but when clicking on it instead of attempting to search for the "Name", it will locate the contact directly by ID
				// -- However, if anything is typed in the box (value changes), the preselected contact will be reset and the normal contact search will apply
				primary_recipient_cssdata = {
					combobox_value: compose_defaults.to_name,
					contact_id: compose_defaults.to_id
				};
			}
		}

		// The "Back" button changes text and role depending on some variables
		if(active_location)
			$("button#compose-back-button").html("<img src='/img/icons16/"+active_location.data.icon+".png' alt='Back'> "+active_location.label).prop("title", "Return to "+active_location.label+" without sending the message.");

		// Compose primary recipient line, is handled by the "contact search system" plugin
		cargohub.contact_search.setup_CSS("compose-recipient", "#tab_compose #compose-recipient", {
			result_template: $("#template-contact-result").html(),
			width: 676,
			css: JSON.stringify(self.config.csscope),
			initialize: active_issue? "issue:"+active_issue.id: "recent", // Contact Search is initialized either in "current issue" mode, or in "recently used" mode
			on_focus: function(key) {
				open_css_key = key;
				donrey.cancel_Delay('contact-menu');
			},
			/**
			 * Event: The value of the combobox changes. Contact search returns either the found contact or just the combobox value
			 * @param key
			 * @param state
			 */
			on_change: function(key, state)
			{
				compose_modified = true;
				primary_recipient_cssdata = state;
			},
			/**
			 * Event: Contact search is closed without user selecting anything
			 * @param key
			 * @param emaildata
			 */
			on_close: function(key, recipient)
			{
				open_css_key = null;
				if(recipient) {
					$("button#compose-send").jqxButton({disabled: false}).jqxButton('render');
					primary_recipient = recipient;
				} else {
					$("button#compose-send").jqxButton({disabled: true}).jqxButton('render');
					primary_recipient = null;
				}
			}
		}, primary_recipient_cssdata);

		// The green + button
		$("#tab_compose #compose-recipient-add").jqxTooltip({
			content: "Add this address to the list of extra recipients",
			position: "bottom",
			opacity: 1
		})
		/**
		 * Event: By clicking the (+) button the e-mail address which is currently in the Address input is added to a list shown below
		 */
		.on("click", function(event)
		{
			if(!primary_recipient)
				return cargohub.its.alert("Please locate a valid recipient first !");

			if(!primary_recipient.email)
				return cargohub.its.alert("This is a preselected contact. Please click on the recipient bar to search for an e-mail to add to CC list.");

			if(check_cc_duplicate(primary_recipient.email))
				return cargohub.its.alert("This e-mail is already part of the list !");

			// Visually display the new e-mail in the list
			if(cc_length == 0)
				$("#tab_compose #compose-extra-recipients").fadeIn();

			// The Click-on-email action for the extra recipients
			$("#compose-extra-recipients ul").append($("<li data-ccid='"+cc_id+"'>" + primary_recipient.email + "</li>").click(function() {
				delete email_cc_list[$(this).data("ccid")];
				$(this).remove();
				cc_length--;
				if(cc_length == 0)
					$("#tab_compose #compose-extra-recipients").fadeOut();
			}));

			cargohub.contact_search.reset("compose-recipient");
			$("#tab_compose #compose-recipient span").html("<span class='gray jqx-input-fresh'>(click to search recipient)</span>");
			email_cc_list.push(primary_recipient.email);
			cc_id++;
			cc_length++;

			primary_recipient = null;
			primary_recipient_cssdata = null;
		});


		// Canned messages selector (A jar icon on the right side) - When pressed, it temporarely hides the "Subject" input, creates a jqxDropDownList with the canned messages in it's place
		// -- Canned messages are only available in user-mode, the jar doesn't show up for public submit/transfer
		// -- The jqxDDL gets it's content remotely, from the templates with usage class "issmessage" to which the user has access to
		// -- Clicking the jar while the selector is in place reverses the process, allowing editing of the subject.
		$("#tab_compose #compose-canned-toggle")
		.on('click', function() {
			if(canned_selector) {
				// Destroy the selector, hide the div, show the input
				$("#tab_compose #compose-subject > input").show();
				$("#tab_compose #compose-subject > div").off('select').jqxDropDownList('destroy');

				canned_selector = false;
			} else {
				// Extra data for the dataAdapter
				if(self.config.tab_compose.type == 'issue') {
					// If this conversation is issue-linked, canned messages are of type "issreply", otherwise "personal"
					canned_data_source._source.data = {
						usage_class: "issreply", // If this conversation is issue-linked, canned messages are of type "issreply", otherwise "personal"
						issue_id: self.config.tab_compose.issue.id
					};
				} else
					canned_data_source._source.data = {
						usage_class: "personal"
					};

				// Hide the input
				$("#tab_compose #compose-subject > input").hide();

				// Instance the selector
				$("#tab_compose #compose-subject").append('<div id="compose_canned_select"></div>');
				$("#tab_compose #compose-subject > div").jqxDropDownList({
					theme: cargohub.jqxtheme,
					source: canned_data_source,
					displayMember: "description",
					valueMember: "message",
					width: 725, height: 22,
					promptText: "Select canned message (click to choose)",
					autoDropDownHeight: true
				}).on("select", function(e)
				{
					if (e.args && e.args.item) {
						$("#tab_compose #compose-subject > input").removeClass('gray').val(e.args.item.originalItem.title);
						// Short delay to allow focus to work
						$("#tab_compose #compose-canned-toggle").trigger('click');
						setTimeout(function() {
							$("#tab_compose #compose-content").val(e.args.item.value);
							$("#tab_compose #compose-content").jqxEditor('focus');
						}, 100);
						compose_modified = true;
					}
				});

				canned_selector = true;
			}
		});

		// Compose subject (changes text color from inactive to active)
		$("#tab_compose #compose-subject input")
		.on('focus', function() {
			$(this).removeClass('gray');
		})
		.on('blur', function() {
			if(!$(this).val())
				$(this).addClass('gray');
		})
		.on('change', function() {
			compose_modified = true;
		});

		// Compose textarea
		$("#tab_compose #compose-content").jqxEditor({
			theme: cargohub.jqxtheme,
			stylesheets: ["/css/default/cargo_jqxeditor.css"],
			width: 767,
			height: 300,
			lineBreak: "br",
			tools: 'bold italic underline | format font size | color background | left center right | outdent indent | ul ol | image link | clean'
		})
		/**
		 * Event: Content editor is changed - Set the "Modified" flag
		 */
		.on('change', function() {
			compose_modified = true;
		});
		// Fix the editor's inside window which sometimes initializes of wrong height:
		$("#tab_compose div.jqx-editor div.jqx-editor-content").height(260);
		$("#tab_compose div.jqx-editor iframe").contents().find('body').height(260);

		// Compose command bar (a toolbar that is below the message, because the textarea increases itself when typing and the top bar might not be visible/ easily accessible anymore)
		$("#tab_compose div#compose-commands button").jqxButton({
			theme: cargohub.jqxtheme
		})
		.on('click', function(e) {
			switch(e.target.id) {
				// Calls the request to create a message to the selected contact
				// A conversation will automatically be created with this new message and then the module will switch to conversation display, showing the message just created and the conversations users
				case 'compose-send':
					if(!primary_recipient)
						return cargohub.its.alert("Invalid recipient in the <b>To:</b> field.");

					// Prepare the required variables
					var message_data = {
						subject: $("div#compose-subject input").val(),
						content: $("div#compose-content").val(),
						recipient: primary_recipient,
						cc_list: []
					};
					if(email_cc_list)
						for (c = 0; c < email_cc_list.length; ++c) {
							message_data.cc_list.push(email_cc_list[c]);
						}

					// If there is an active issue, the composed message will be added as an issue-linked conversation.
					if(active_issue)
						message_data.issue = active_issue;

					if(message_data.content.length < 10)
						return cargohub.its.alert("Your message is too short, please write more !");

					$.post("?action=add_message", message_data, function(x) {
						if(x.success) {
							convo_load_config = {
								mode: "conversation",
								convo_id: x.result.cnv_id
							};
							// If there is an active linked issue, pass it on so the conversation also shows the linked issue after reload
							if(active_issue) {
								convo_load_config.issue_id = active_issue.id;
								convo_load_config.issue_track = active_issue.track;
								convo_load_config.location_key = self.config.load_config.location_key;
							}

							// Load the module into the conversation special mode to show the newly created conversation.
							document.location = "/user/commcenter?action=custom_load&config="+ JSON.stringify(convo_load_config);
						} else {
							cargohub.its.alert(x.reason, "Message from server", "events.png");
						}
					});

					break;

				case 'compose-signature':
					if(signature_added)
						if(!confirm("Your signature seems to be already added.\nAre you sure you want to add it again ?"))
							return;

					var user_signature = cargohub.application.user_Get_Session().signature,
						original_content = $("#tab_compose div#compose-content").val();

					$("#tab_compose div#compose-content").val(original_content + "<br /><hr />" + user_signature);
					signature_added = true;
					break;

				// Same effect as pushing the "Discard" button on top, it exists the Compose tab and resets the content. Asks for confirmation first
				case 'compose-discard':
					if(compose_modified) {
						if(!confirm("Compose tab contains content.\nAre you sure you want to clear it and move away?"))
							return;
					}

					// Switches to #list tab and sets itself as cleared
					compose_defaults = null;
					signature_added = false;
					cargohub.contact_search.destroy("compose-recipient");

					if(canned_selector) {
						// Destroy the selector, hide the div, show the input
						$("#tab_compose #compose-subject > input").show();
						$("#tab_compose #compose-subject > div").off('select').jqxDropDownList('destroy');

						canned_selector = false;
					}
					$("#tab_compose div#compose-content").jqxEditor('destroy');

					delete loaded_tabs['tab_compose'];
					$('#comm-center').jqxTabs('select', 0);
					break;
			}
		});

		// If a contact is not preselected, begin with the "Send" button disabled.
		if(!(compose_defaults && compose_defaults.to_id))
			$("#tab_compose button#compose-send").jqxButton({disabled: true});

		// Post initialization defaults
		if(compose_defaults) {
			if(compose_defaults.to_id) {
				$("#tab_compose #compose-recipient span").addClass('contact-item').removeClass('gray').text(compose_defaults.to_name.escapeHtmlEntities()).data("con_id", compose_defaults.to_id);
				// In this case the focus goes to the subject, not the contact, since it's been already picked
				setTimeout(function() {
					$("#tab_compose #compose-subject input").focus();
				}, 100);
			}

			if(compose_defaults.content) {
				$("#tab_compose #compose-content").val(compose_defaults.content);
				setTimeout(function() {
					$("#tab_compose #compose-recipient").focus();
				}, 100);
			}
		} else
			$("#tab_compose #compose-recipient").focus();
	},

	/**
	 * The Address Book sub-module, containing a server-searchable grid with names, e-mail addresses (This is filled automatically by the system when receving issues, or when sending out messages)
	 * -- Manual editing also possible.
	 * -- There are limitations on what contacts can users see. Admin can see/edit all contacts that exist in the system ( with the purpose of removing spam addresses )
	 */
	setup_Tab_Contacts = function()
	{
		var tab_height = 500,
		grid_first_time = true,
		columns_changed = false, // Indicates that the column configuration has changed as the result of picking a different label / contact type

		/**
		 * Callback function which returns content of cell in the contacts lists with some formatting
		 */
		contact_Cell_Renderer = function(row, columnfield, value, defaulthtml, columnproperties)
		{
			var changed_value = null, data = $('#tab_contacts #ctx-grid').jqxGrid('getrowdata', row);

			if(columnfield == 'dt')
				changed_value = $.format.date(value, cargohub.date_format);

			if(changed_value)
				defaulthtml = defaulthtml.replace('>'+ value +'<', '>'+ changed_value +'<');

			if(data.primary == true)
				defaulthtml = defaulthtml.replace('style="', 'style="font-weight: bold;');

			return defaulthtml;
		},

		// Column definitions for the various contact types - Used to present the most relevant contact information in the grid
		// TODO: Review these after the contacts are actually properly loaded.
		contacts_col_defs = {
			// TODO: See what's important for user's self addresses?
			user_self: [
				{ text: 'E-mail', datafield: 'email', width: 270 },
				{ text: 'Name', datafield: 'name' }
//				{ text: 'Comment', datafield: 'xd_comment' },
			],
			org_self: [
				{ text: 'E-mail', datafield: 'email', width: 270  },
				{ text: 'Represents', datafield: 'name' },
				{ text: 'Phone', datafield: 'xd_phone' }
			],
			organization: [
				{ text: 'Name', datafield: 'name' },
				{ text: 'E-mail', datafield: 'email', width: 270 },
				{ text: 'Phone', datafield: 'xd_phone' },
				{ text: 'Added by', datafield: 'user_name' },
			],
			// TODO: A way to differentiate different issue target contacts, and also show which one is the default (via row bolding/coloring?)
			issue_target: [
				{ text: 'Name', datafield: 'name' },
				{ text: 'E-mail', datafield: 'email', width: 270 },
			],
			auto_out: [
				{ text: 'Name', datafield: 'name' },
				{ text: 'E-mail', datafield: 'email', width: 270 },
				{ text: 'First sent', datafield: 'xd_dt', width: 110, cellsrenderer: contact_Cell_Renderer, filtertype: 'date' }
			]//,
//			personal: [
//				{ text: 'Name', datafield: 'name', width: 200 },
//				{ text: 'E-mail', datafield: 'email', width: 270 },
//				{ text: 'Extra', datafield: 'xd_mix' }
//			]
		},

		/**
		 * The DataAdapter used to load the messages into the grid
		 */
		contacts_data_source = new $.jqx.dataAdapter({
			url: '?action=get_contacts',
			data: {},
			datatype: "json",
			datafields: [
				{ name: 'id', type: 'int' },
				{ name: 'org_id', type: 'int' },
				{ name: 'user_id', type: 'int' },					// Not all contact types have an user id
				{ name: 'user_name', map: "user>name" },
				{ name: 'type' },
				{ name: 'ctime', type: "date" },					// When was the contact created ?
				{ name: 'email' },
				{ name: 'name' },
				{ name: 'count_msgs', type: 'int' },				// How many messages were sent (created) by this contact?
//				{ name: 'commet', map: "note>comment" },			// Comment to this contact record (is actually an associated note read on demand for certain note types)
				{ name: 'xd_phone', map: "xdata>phone" },			// Phone number, important for most organization contacts
//				{ name: 'xd_track', map: "issue>track" },			// Tracking ID of the issue for issue-linked contacts (Not yet implemented here)
//				{ name: 'xd_mix', map: "xdata>mix"}					// Combined XData, for custom contacts (non-system) - Is returned as HTML by the server
				{ name: 'primary', type: 'bool' }					// Indicates this contact should be market as being the "primary" one (shown as bold in the grid), for certain lists
			],
			id: 'id'
		}),
		// Assume the first column definition is for the "user_self" / TODO: Support ability to load another label / contact type at load time
		contacts_columns = contacts_col_defs.user_self,

		/**
		 * Indicates if the event tree_Ctx_Select is triggered by user (false) or by the code itself (true)
		 */
		ctx_CodeSelect = false,

		/**
		 * Event: Select an item in the tree ( one of the contacts classes )
		 */
		tree_Ctx_Select = function(event)
		{
			if(ctx_CodeSelect)
				return;

			// check if clicking on a folder (not selectable)
			if(!$(event.args.element).data('selectable')) {
				ctx_CodeSelect = true;
				$("#tab_contacts #ctx-tree").jqxTree('selectItem', $('#ctx-tree li#label_'+current_conlab)[0]);
				ctx_CodeSelect = false;
				return;
			}

			// get the correct data
			var ctx = $('#tab_contacts #ctx-tree').jqxTree('getItem', event.args.element),

			// selected contacts class
			selected_conlab = ctx.id.substr(6);

			// clicked on the already selected one ? Ignore trigger
			if(selected_conlab == current_conlab)
				return;

			// contacts class really changed, update the "current" one
			contacts_data_source._source.data.label_id = current_conlab = selected_conlab;
			contacts_data_source._source.data.label_type = current_conlab_type = $(ctx.element).data('type');

			if(grid_first_time) {
				// setup the grid which shows the content of a contacts class
				$('#tab_contacts #ctx-grid').jqxGrid({
					theme: cargohub.jqxtheme,
					source: contacts_data_source,
					columns: contacts_columns, columnsmenu: false,
					width: $("#tab_contacts").jqxSplitter('panels')[1].element.width(), height: tab_height,
					pageable: true, pagesize: 25, pagesizeoptions: ['10', '15', '25', '50', '100'],
					sortable: true, filterable: true, showfilterrow: true,
					selectionmode: "multiplerowsextended"
				})
				/**
				 * Event, the grid is loaded - Set the columns to proper mode on the selected label
				 */
				.on('bindingcomplete', function(event)
				{
					if(columns_changed) {
						$('#tab_contacts #ctx-grid').jqxGrid({
							columns: contacts_columns
						});
						columns_changed = false; // Done, grid is updated
					}

					// Every time the grid is reloaded, refresh the hover event on the contact cells (with a small delay to give the grid time to initialize)
				})
				/**
				 * Event: When a contact is selected, the Selected Contact tab is loaded in the background and also buttons appear in the toolbar
				 */
				.on('rowselect', function (event)
				{
					// Uses a tiny delay to make sure the actions that need to happen when a row is selected only happen once even when multiple rows are drag-selected,
					// because jqxGrid fires a "rowselect" event for each of the selected rows
					donrey.final_Delay('list_grid_rowselect', function() {

						var bar_prefix = '', row_indexes = $('#tab_contacts #ctx-grid').jqxGrid('getselectedrowindexes');

						// FIXME: Not sure exactly what is this used for...
//						if(!selected_contact) {
						switch(current_conlab_type) {
							// For now, there is only one contact model
							case 'global':
							case 'organization':
							case 'org_self':
							case 'issue_target':
							case 'department':
							case 'dep_self':
							case 'personal':
							case 'user_self':
							case 'auto_out':
							case 'auto_in':
							case 'unknown':
								bar_prefix = "basiccon_";
//								selected_contact_type = "standard_contact"; // Uses the default "contact_editor.js"
								break;
							case 'isspost':
							case 'isslink':
//								selected_contact_type = "issue_linked_contact"; // TODO: The contact view/edit page also shows issue information
								break;
						}
//					}
//					selected_contact = event.args.row;

						selected_ctx.length = 0; // Reset list of contacts
						if(row_indexes.length > 1) {
							// Multiple rows selected, make sure the "contact_single_select" bar is hidden, and also disable Tab 4 (Contact Editor), while setting it's title to "Multiple selected" to indicate why it's disabled

							clear_Item_Tab(4, "Multiple selected", true);

							for(c=0; c<row_indexes.length; c++) {
								selected_ctx.push($('#tab_contacts #ctx-grid').jqxGrid('getrowdata', row_indexes[c])['uid']);
							}

							// Show only the "any" bar (valid for either single or multiple select)
							disable_Toolbar_Item('cc_ops-', bar_prefix+'single_select');
							enable_Toolbar_Item('cc_ops-', bar_prefix+'any_select');
						} else {
							active_contact = $('#tab_contacts #ctx-grid').jqxGrid('getrowdata', row_indexes[0]);
							active_contact_record = contacts_data_source.records[row_indexes[0]];

							selected_ctx.push(active_contact.id);

							// Prepare the contact tab to be loaded with the newly selected contact.
							clear_Item_Tab(4, active_contact.email);

							// Prepare the tab loading parameters to the selected conversation
							tab_load_params = {
								label_id: current_conlab,
								label_type: current_conlab_type,
								contact_id: active_contact.id,
								edit: true	// TODO: Determine if user is allowed to edit the contact or not (depending on current contact type and user's abilities)
							};
							// ... and enable the tab
							$('#comm-center').jqxTabs('enableAt', 4);

							enable_Toolbar_Item('cc_ops-', bar_prefix+'single_select');
							enable_Toolbar_Item('cc_ops-', bar_prefix+'any_select');
						}
					}, 50);
				})
				/**
				 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
				 */
				.on('filter', function(event)
				{
					contact_Grid_Unselect();
				})
				/**
				 * Event: On doubleclicking, do the same as selecting the Message tab
				 */
				.on('rowdoubleclick', function() {
					$('#comm-center').jqxTabs('select', 4);
				});

				grid_first_time = false;
			} else {
				// remove selection, grid content will change
				contact_Grid_Unselect();

				// change columns, depending on label_type (will happen after data is loaded, on bindingcomplete event)
				contacts_columns = contacts_col_defs[current_conlab_type];
				columns_changed = true;

				// Trigger grid update
				$('#tab_contacts #ctx-grid').jqxGrid('updatebounddata');
			}
		};

		// initialize the splitter between classes and contacts
		$("#tab_contacts").jqxSplitter({
			theme: cargohub.jqxtheme,
			width: cargohub.fullwidth, height: tab_height,
			panels:	[{
				collapsible: true,
				min: 150,
				size: 220
			}, {
				collapsible: false,
				min: 600
			}]
		}).on('resize', function(event) {
			// resize the grid automatically when the splitter's bar is moved:
		    $('#tab_contacts #ctx-grid').jqxGrid({
		    	width: event.args.panels[1].size
		    });
		});

		// setup the tree of classes
		$('#tab_contacts #ctx-tree').jqxTree({
			theme: cargohub.jqxtheme,
			height: '100%', width: '100%'
		});
		$('#tab_contacts #ctx-tree').jqxTree('expandAll');
		$('#tab_contacts #ctx-tree').on('select', tree_Ctx_Select);

		$("#tab_contacts #ctx-tree").jqxTree('selectItem', $('#tab_contacts #ctx-tree li.jqx-tree-item-li:eq(1)')[0]); // begin by selecting the personal addresses (top, always visible)
	},

	/**
	 * TODO: Setup the user details for a contact, with in-place editors for most data
	 */
	setup_Tab_Vcard = function()
	{
		if(!self.config.tab_vcard) {
			// Apparently there was an error in loading the tab, do not try to initialize stuff on it. Also disable the buttons... Save/Delete/Set labels... etc.
			disable_Toolbar_Item('cc_ops-', 'vcard_actions');
			return;
		}

		// Begin by having the save button disabled, it will be reenabled after a valid change.
		$("div#cc_ops-vcard_actions button#contact-save-button").jqxButton({disabled: true}).jqxButton('render');

		cargohub.contact_editor.setup_VCard($("#tab_vcard #contact-left-side"), {
			/**
			 * Callback: The Contact Editor calls this function every time something is changed in the contact table
			 * @param valid_contact
			 */
			on_change: function(valid_contact) {
				if(valid_contact)
					$("div#cc_ops-vcard_actions button#contact-save-button").jqxButton({disabled: false}).jqxButton('render');
				else
					$("div#cc_ops-vcard_actions button#contact-save-button").jqxButton({disabled: true}).jqxButton('render');
			}
		});
	},

	/**
	 * Sets up the tabs visible on the message center module (also known as "Mail")
	 */
	setup_Tabs = function()
	{
		var disable_onhashchange = true, selected_tab_idx = 0, selected_tab = 'tab_list'; // defaults to first tab

		// Load configuration present - Prepare variables
		if(self.config.load_config) {
			tab_load_params = {};

			// An issue ID is present in the loading configuration. Some functions are now related to an issue (instead of their default)
			if(self.config.load_config.issue_id) {
				active_issue = {
					id: self.config.load_config.issue_id,
					track: self.config.load_config.issue_track
				};
				tab_load_params.issue_id = active_issue.id;
				tab_load_params.issue_track = active_issue.track;
				self.config.csscope.isslink = active_issue.id; // Turns on the isslink option by default
			}

			// A custom return ( Back ) location is specified - Some tabs display a back button. which normally just switches to another tab.
			// If the custom return location is used, that button will instead move to another page.
			// The return location is also used after completing certain operations ( like composing a message linked to an external object - not a standard user to user message )
			if(self.config.load_config.location)
				active_location = self.config.load_config.location;
		}

		// Load configuration can change which tab is loaded at startup (instead of defaulting to the #list one)
		switch(self.config.load_config.mode) {
			// If this load config mode specified, the module chooses a label to load initially which is different from the default "a-inbox"
			case 'list':
				if(selected_tab != 'tab_list') {
					selected_tab = 'tab_list';
					selected_tab_idx = 0;
					location.hash = 'list';
				}
				break;

			// Supports loading a conversation directly - It also changes the behaviour of the "Back" button jump to an external saved location ( instead of going to #list tab )
			case 'conversation':

				// A conversation ID is specified, enable the conversation tab and specify the parameters for loading that conversation
				if(self.config.load_config.convo_id) {

					if(selected_tab != 'tab_conversation') {
						selected_tab = 'tab_conversation';
						selected_tab_idx = 1;
						location.hash = 'conversation';
					}

					active_convo = {
						id: self.config.load_config.convo_id
					};
					tab_load_params.convo_id = active_convo.id;

					self.config.enable_1 = true;
					tab_load_params.get_tab_title = true;
				}
				break;

			// Load the compose tab
			case 'compose':
				if(selected_tab != 'tab_compose') {
					selected_tab = 'tab_compose';
					selected_tab_idx = 2;
					location.hash = 'compose';
				}
				break;

			case 'contacts':
				// TODO: Show contact lists for another user (admin mode), or certain lists for a specific organization or department
				break;

			case 'vcard':
				// TODO: Load specific contact directly
				break;

			default:
				// Discover if a subtab is specified in the URL ( to jump directly to it ), otherwise default to #list ( tab 0 )
				c = 0;
				$('#comm-center > div').each(function() {
					if(location.hash.substr(1) == $(this).prop('id').substr(4)) {
						selected_tab = $(this).prop('id');
						selected_tab_idx = c;
					}
					c++;
				});
		}

		// When load_config exists and contains the correct data, these tabs might be enabled by default. If not, tabs 1 and 4 are initially disabled and hashtag is changed from them to tabs 0 and 3
		if(selected_tab == 'tab_conversation' && !self.config.enable_1) {
			// Switch to list mode from conversation mode if there is no valid conversation to be loaded
			selected_tab = 'tab_list';
			selected_tab_idx = 0;
			location.hash = 'list';
		}
		if(selected_tab == 'tab_vcard' && !self.config.enable_4) {
			// Switch to address book mode from vcard mode if there is no valid vcard to load
			selected_tab = 'tab_contacts';
			selected_tab_idx = 3;
			location.hash = 'contacts';
		}

		$('#comm-center')
		/**
		 * Event: Tabs system created, used to disable some tabs and other post-initialization activities
		 */
		.on('created', function(event) {
			// Disable tabs if requested by config checker
			if(!self.config.enable_1)
				$(this).jqxTabs('disableAt', 1);
			if(!self.config.enable_4)
				$(this).jqxTabs('disableAt', 4);
		})
		.jqxTabs({
			theme: cargohub.jqxtheme,
			width: cargohub.fullwidth,
			position: 'top',
			selectionTracker: true, selectedItem: selected_tab_idx,
			keyboardNavigation: false
		})
		/**
		 * Event: When selecting a tab for the first time, it is loaded from the server (except for the first one - tab_overview, which is initially loaded with the page)
		 */
		.on('selected', function(event, auto_trigger) {
			cargohub.application.reset_Session_Timer();
			disable_onhashchange = true;

			if(!auto_trigger) {
				selected_tab = $('#comm-center').jqxTabs('getContentAt', event.args.item).attr('id');
				// some tabs do not have tab_load_params, so clear them
				if(selected_tab == 'tab_list' || selected_tab == 'tab_contacts')
					tab_load_params = {};
			}
			location.hash = selected_tab.substr(4);

			if(quickreply_modified && selected_tab=='tab_list') {
				cargohub.its.alert("Your message is still unsent. If you select another conversation its content will be lost !", "Warning", "error.png");
			}

			if(!loaded_tabs[selected_tab]) {
				// doesn't yet exist, load it's HTML and initialize
				$.get("?action=load_tab_html&tab=" + selected_tab, tab_load_params, function(tab_contents, textStatus, jqXHR)
				{
					// Load extra data for this tab, if it exists, and store it in the local object
					donrey.read_JSON_Headers(jqXHR, self.config);

					$('div#' + selected_tab).html(tab_contents);

					// run init function for this tab when loading is complete
					switch(selected_tab) {
						case 'tab_list':
							setup_Tab_List(); break;
						case 'tab_conversation':
							enable_Toolbar_Item('cc_ops-', 'conversation_actions');
							setup_Tab_Conversation(); break;
						case 'tab_compose':
							setup_Tab_Compose(); break;
						case 'tab_contacts':
							setup_Tab_Contacts(); break;
						case 'tab_vcard':
							enable_Toolbar_Item('cc_ops-', 'vcard_actions');
							setup_Tab_Vcard(); break;
					}

					// Sets the tooltip on all "info-tip" elements present inside the tab
					$("#"+selected_tab+" .info-tip").each(function() {
						$(this).jqxTooltip({
							theme: cargohub.jqxtheme,
							content: $(this).data('tooltip'),
							position: 'bottom',
							width: $(this).data('tooltip_width') || "auto",
							autoHideDelay: 10000
						});
					});

					// and finally, set the tab as initialized
					loaded_tabs[selected_tab] = true;

					// Clear load config, the tab's init function should have kept and set variables for itself at this point
//					dd(self.config.load_config);
				});
			}

			// Fadeout any existing bars, and fadein the bar for the correct tab
			$('div.toolbar div.opbar').each(function() {
				$(this).fadeOut('fast');
			});
			setTimeout(function() {
				// load the bar for the freshly selected tab
				$('div#cc_ops-'+selected_tab).fadeIn('fast');
			}, 300);

			setTimeout(function() {
				disable_onhashchange = false;
			}, 50);
		});

		// initialize the selected tab by triggering the "selected" event
		$('#comm-center').trigger('selected', true);

		// hash-based navigation inside tabs themselves - Visually select tab when hash changes by typing in the URL or using back/forward (not by clicking on the tab itself)
		window.onhashchange = function(event) {
			// hash changes as well when the user is selecting the tab manually, in those cases there's no need to find and reselect the tab again !
			if(disable_onhashchange)
				return;

			c = 0;
			$('#comm-center div.jqx-tabs-content > div').each(function() {
				if(location.hash.substr(1) == $(this).prop('id').substr(4))
					selected_tab_idx = c;
				c++;
			});
			$('#comm-center').jqxTabs('select', selected_tab_idx);
		};

/*		window.onbeforeunload = function(event) {
			if(quickreply_modified || compose_modified)
				return "Your message is still unsent. If you move away from the Communication Center it will be lost. Are you sure?";
		};
*/	}

	;
	this._public({
		/**
		 * Section initialization
		 */
		init: function()
		{
			if(!self.config.load_config)
				self.config.load_config = {};

			// On top of all tabs there is one unique toolbar, which changing content depending on the current activity (tab, grid selection)
			setup_Toolbar();
			// Message Center module consists of dynamically loaded tabs
			setup_Tabs();

			// Display a notification when the module is loaded in custom mode (load_config mode is not "default")
			if(self.config.load_config.mode && self.config.load_config.mode != "default") {
				cargohub.application.evan_Standard_Notify("info", "Please <b>DO NOT</b> use the browser's REFRESH button ! The results might be unexpected. If any problems, use &laquo;BACK instead and try again.");
			}

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

			// Global focus event related to contacts search system
			// -- When something is focused outside the active CSS, it is closed
			$(document).on('focus', '*', function(e) {
				e.stopPropagation();

				if(!open_css_key || e.target.id == open_css_key || $(e.target).parents('#'+open_css_key).length || $(e.target).hasClass('jqx-listbox'))
					return; // Do not attempt to close when target is the master container itself, or anything inside it, to prevent immediate reopening when clicking in the contact bar itself, or when there is nothing to close

				cargohub.contact_search.close(open_css_key);
			});
		}
	});
});