/**
 * Module "Record Process", interface for viewing integrated communications conversations ( Communication list )
 * - Also allows opening said message in the same tab, under the grid, as an arbitrarely length conversation
 * - Ability for quick Reply on the bottom of the message
 * - Buttons and Links for Forwarding and Compose ( Open in the "Compose" tab of the main module )
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules.Record Process
 * @version 2.7
 */
cargohub.record_comm = donrey.BASE._extend(function() {
	// private
	var self = this,
	c = null,
	master = cargohub.record_process, 

	// Data from the table containing currently selected row. Used for opening the conversation and for Reply/Forward/Trash - Set on any row operation.
	selected_row = null,
	// The buttons from which message have been pressed ( for Reply, or the Action menu ), and the Data() for the message corresponding to the button
	action_message_data = null,

	// List (table) area - Is a jqxDataTable with all linked conversations. By selecting one, the "Conversation" area opens, showing the content
	$list_area = null,
	// Conversation area - Contains a left side with the messages and the right side with the involved contacts and other information
	$convo_area = null,
	$message_menu = null, // Lazy initialization of a jqxMenu

	// Source for the opened conversations - Linked to the current record
	convos_Source = new $.jqx.dataAdapter({ 
		url: '?action=Get_Conversations',
		data: {},
		datatype: "json",
		datafields: [
			{name: 'visibility', type: 'string'},
			{name: 'count_msgs', type: 'int'},
			{name: 'count_ctx', type: 'int'},
			{name: 'org_id', type: 'string'},
			{name: 'org_name', type: 'string', map: 'org>name'},		// Only if it exists
			{name: 'access_orgs', type: 'string'},						// Needed for the Configure control
			// TODO: Check if Starter message is still needed after conversation is opened below via ajax call; Probably not...
			{name: 'starter_id', type: 'int', map: 'starter>id'},
			{name: 'starter_time', type: 'date', map: 'starter>time'},
			{name: 'starter_from_id', type: 'int', map: 'starter>from_id'},
			{name: 'starter_from_name', type: 'string', map: 'starter>from_name'}, // Starter contact name
			{name: 'starter_from_email', type: 'string', map: 'starter>from_email'}, // ... and e-mail
//			{name: 'starter_subject', type: 'string', map: 'starter>subject'},
//			{name: 'starter_content', type: 'string', map: 'starter>content'},
//			{name: 'recipient_id', type: 'int', map: 'recipient>id'}, // Recipient of the starter message
//			{name: 'recipient_name', type: 'string', map: 'recipient>name'}, // ... and e-mail
//			{name: 'recipient_email', type: 'string', map: 'recipient>email'},
			{name: 'lastmsg_from_id', type: 'int', map: 'last_msg>from_id'}, // Last contact that has added a message to the conversation ( not used yet )
			{name: 'lastmsg_time', type: 'date', map: 'last_msg>time'},
			{name: 'contacts_pack', type: 'string'},
			{name: 'description', tpe: 'string'}
		],
		id: "id",
		// Initial Sorting
		sortcolumn: 'starter_time',
		sortdirection: 'desc'
	}),

	// File visibility selector TODO: Get the texts from Language files
	convo_visibility_source = [
		{k: 'private', text: '<span class="access16 visi-private"></span> Private conversation to involved contacts'},
		{k: 'org', text: '<span class="access16 visi-org"></span>Everyone in the sender organization can see it'},
		{k: 'shared', text: '<span class="access16 visi-shared"></span>Organizations sharing this '+master.config.type.name},
		{k: 'public', text: '<span class="access16 visi-public"></span>No visibility restrictions'}//,
//		{k: 'hidden', text: '<span class="access16 visi-public"></span>Conversation is hidden (deleted)'}
	],

	/**
	 * A conversation row has been selected - Store information in preparation for a double-click ( Open Conversation ), or for clicking on one of the toolbar buttons
	 */
	select_Convo = function(row)
	{
		if(!selected_row) {
			donrey.cancel_Delay('hide-bar_convo-select');
			$('#tab_comm #comm-row-ops button').jqxButton({disabled: false});
			$('#tab_comm #comm-row-ops').fadeIn();

		}
		selected_row = row;
	},

	/**
	 * No rows are currently selected - Make the toolbar buttons dissapear
	 */
	select_Clear = function()
	{
		selected_row = null;
		$('#tab_comm #comm-row-ops button').jqxButton({disabled: true});
		donrey.single_Delay('hide-bar_convo-select', function() {
			$('#tab_comm #comm-row-ops').fadeOut();
		}, 500);

		if($convo_area) {
			$convo_area.hide();
			$convo_area.jqxExpander('collapse');
		}
	},

	/**
	 * After a conversation is loaded, make it interactive
	 */
	setup_Convo_Controls = function()
	{
		/*
		// Not currenly used
		var
		// Creates a "quote" string out of "action_message_data" content, to be used in the reply box.
		quote_From_Action_Message_Data = function()
		{
			if(action_message_data && action_message_data.from_id && action_message_data.msg_content) {
				var quote_hdr = "On " +action_message_data.dt+ ", " +action_message_data.from_name+ " wrote:";
				return "<br /><br /><div style='margin-bottom: 4px; color: gray;'>" + quote_hdr + "</div><div style='padding-left: 12px; margin-left: 8px; border-left: 2px solid lightgray; margin-bottom: 6px;'>" + action_message_data.msg_content + "</div>";
			} else
				return "";
		};
		*/

		// Clicking on closed message hides it and shows the "open" version insted
		$("div.convo-msg-closed", $convo_area).on("click", function(e) {
			var $this = $(this);

			// Do not close if clicking on the sender contact
			if(!$(e.target).hasClass('contact-item')) {
				$this.hide();
				$("div.convo-msg-open", $this.parent()).show();
			}
		});

		// Clicking on the header of open messages closes it and shows the "closed" version instead
		$("div.convo-msg-open .msg-header", $convo_area).on("click", function(e) {
			var $this = $(this).parent();

			// Do not close if clicking on a button or on a contact
			if(!((e.target.attributes.role && e.target.attributes.role.value == "button") || $(e.target).hasClass('contact-item'))) {
				$this.hide();
				$("div.convo-msg-closed", $this.parent()).show();
			}
		});

		// Make the message-related buttons fancy (two glued buttons)
		$("div.convo-msg-open .msg-header .msg-buttons", $convo_area).each(function() {
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
                                    
                                    
////IWI  to view sender details, resend mail and print this mail in add / edit message start

                                switch ($(e.args.button.context).data('op')) {
                                    case 'msg_sender_details':
                                        master.contact_Open_Details_Modal(action_message_data.from_id);
                                        return;
                                    case 'msg_send_email':
                                        if (!confirm("Please confirm that you really intend to (re)send this message as an e-mail !"))
                                            return;

                                        $.post("/global/comm?action=Resend_Message", {
                                            msg_id: action_message_data.msg_id
                                        }, function (x) {
                                            if (x.success) {
                                                cargohub.alert(x.response, "Success !", "thumb_up.png");
                                                master.refresh_DataTable($("div.jqx-datatable", $list_area));
                                            } else
                                                cargohub.its.http_fail(null, "Global.Comm::Resend_Message");
                                        })
                                                .fail(cargohub.its.http_fail);
                                        return;
                                    case 'msg_print_message':
                                        var printContent = document.getElementsByClassName('msg-content')[0].innerHTML;
                                        //                                                                        console.log(printContent);
                                        var originalContent = document.body.innerHTML;
                                        document.body.innerHTML = printContent;
                                        window.print();
                                        document.body.innerHTML = originalContent;
                                        return;
                                }


                                    

//				if(e.args.index == 0) {
//
//					master.contact_Open_Details_Modal(action_message_data.from_id);
//					// Pressing Reply focuses the quickreply box in "reply" mode. Focusing grabs the data from "action_message_data" variable
////					if(quickreply_init == "reply") {
////						change_Reply_Box(quote_From_Action_Message_Data());
////					} else
////						$("#convo-quickreply #reply-content").focus();
////					quickreply_init = "reply";
//				} else
//				if(e.args.index == 1) {
//					// message-actions
//					if(!$message_menu) {
//						$message_menu = $($('#menu-message_actions').html()).jqxMenu({
//							theme: cargohub.jqxtheme,
//							autoOpenPopup: false,
//							animationShowDuration: 0,
//							animationHideDuration: 0,
//							mode: 'popup'
//						})
//						/**
//						 * Event: Click on message context menu item
//						 */
//						.on('itemclick', function(e)
//						{
//							switch($(e.args).data('op')) {
//								// Reply option focuses the quickreply box in "reply" mode. Focusing grabs the data from "action_message_data" variable
//								case 'reply':
////									if(quickreply_init == "reply") {
////										change_Reply_Box(quote_From_Action_Message_Data());
////									} else
////										$("#convo-quickreply #reply-content").focus();
////									quickreply_init = "reply";
//									return;
//
//								// This message is placed into the "Compose" box (title and content), with the purpose of forwarding information to another contact without involving this conversation
//								case 'copy_to_new':
////									if(compose_modified) {
////										if(!confirm("Compose tab contains content.\nAre you sure you want to discard and put the content of this message instead?"))
////											return;
////									}
////
////									// Clear any previous "Compose" tab content
////									cargohub.contact_search.destroy("compose-recipient");
////									open_css_key = null;
////									delete loaded_tabs['tab_compose'];
////
////									// Setup the parameters for loading the "Compose" tab with preloaded contact and message content + subject
////									compose_defaults = {
//////										subject: // TODO: Copy message subject when subject will be suported
////										content: quote_From_Action_Message_Data()
////									};
////
////									// Ready, switch to Compose tab
////									$('#comm-center').jqxTabs('select', 2);
//									return;
//
//								// The sender details option shows information about who sent this message
//								case 'sender_details':
//									master.contact_Open_Details_Modal(action_message_data.from_id);
//									return;
//
//								// Sends this message via E-mail to the intended recipient, after a confirmation
//								case 'send_email':
//									if(!confirm("Please confirm that you really intend to (re)send this message as an e-mail !"))
//										return;
//
//									$.post("/global/comm?action=Resend_Message", {
//										msg_id: action_message_data.msg_id
//									}, function(x) {
//										if(x.success) {
//											cargohub.alert(x.response, "Success !", "thumb_up.png");
//										} else
//											cargohub.its.http_fail(null, "Global.Comm::Resend_Message");
//									})
//									.fail(cargohub.its.http_fail);
//									return;
//
//								// Trashing a message removes hides it into the conversation flow
//								// TODO: Not yet clear how to store in the database the information about which messages are "trashed"
//								case 'trash':
//									cargohub.alert("Trashing individual messages not yet implemented");
//									return;
//                                                                        
//                                                                              
//                                                                        // The print message details option shows, can be print
//								case 'print_message':
//									var printContent = document.getElementsByClassName('msg-content')[0].innerHTML;
////                                                                        console.log(printContent);
//                                                                        var originalContent = document.body.innerHTML;
//                                                                        document.body.innerHTML = printContent;
//                                                                        window.print();
//                                                                        document.body.innerHTML = originalContent;
//									return;
//                                                                        
//							}
//						});
//					}
//
//					var ddbtn_pos = $(this).position();
//					$message_menu.jqxMenu('open', ddbtn_pos.left - 58, ddbtn_pos.top + 27);
//				}
////                            IWI  to view sender details, resend mail and print this mail in add / edit message end
			});
		});

		// Trigger Click on last message (so it is by open by default)
		$("div.convo-msg:last div.convo-msg-closed", $convo_area).trigger("click");
	},

	/**
	 * Opens conversation in the second jqxExpander (sets title, loads content via ajax)
	 */
	open_Convo = function(row)
	{
		if(row)
			selected_row = row;

		if($convo_area)
			$convo_area.show();
		else
			init_ConvoArea();

		// Set the title, then load the conversation into the body
		$("#comm-convo_title", $convo_area).html(selected_row.description+ " <span class='fiftycent'>(loading...)</span>");

		// Load and expand
		$.get("?action=Load_Conversation", {
			id: selected_row.uid
		}, function(contents, textStatus, jqXHR) {
			$("#comm-convo_body", $convo_area).html(contents);
			$("#comm-convo_title", $convo_area).html(selected_row.description);
			setup_Convo_Controls();
			$convo_area.jqxExpander('expand');

			// Scroll to the $convo_area
			$('html, body').animate({
				scrollTop: $($convo_area).offset().top
			}, 1500);
		})
		.fail(cargohub.its.http_fail);
	},

	/**
	 * Opens the file attachment configuration window, used to change access for the record, set labels and perform other operations
	 */
	convo_Config = function(row)
	{
		if(row)
			selected_row = row;

		selected_row.mtime_formatted = $.format.date(selected_row.mtime, cargohub.datetime_format);

		cargohub.components.modal.create('convo-config', {
			btn_roles: {cancel: "cancel"}
		});

		cargohub.components.modal.open('convo-config', {
			width: 600,	height: 230,
			title: "Configure conversation for: " + master.config.type.name+ " - "+master.config.track,
			content: $('#template-convo-config').html().present({
				_itc: cargohub.input_theme_classes,
				convo: selected_row,
				master: master.config
			}),
			callback: function($window) // Setup the controls inside the window
			{
				// Visibility selector
				var visibility_index = -1, $access_orgs = $('#convo-access_orgs', $window);
				for(c = 0; c< convo_visibility_source.length; c++)
					if(selected_row.visibility == convo_visibility_source[c].k)
						visibility_index = c;
				$('#convo-visibility', $window).jqxDropDownList({
					theme: cargohub.jqxtheme,
					source: convo_visibility_source,
					displayMember: "text", valueMember: "k",
					width: 428,	height: 24, autoDropDownHeight: true,
					selectedIndex: visibility_index
				})
				/**
				 * Event - The visibility selector is changed.
				 */
				.on('select', function(event) {
					if(event.args.item.value == "shared") {
						$access_orgs.parent().show();
						$access_orgs.jqxDropDownList('open');
					} else
						$access_orgs.parent().hide();
				});

				// If visibility is in sharing mode, show the orgs selector:
				if(visibility_index == 2)
					$access_orgs.parent().show();

				// Access IDs selector (for sharing mode)
				$access_orgs.jqxDropDownList({
					theme: cargohub.jqxtheme,
					source: master.source_Sharing(function(records) {
						var current_shares = selected_row.access_orgs? selected_row.access_orgs.split(","): [];
						for(c=0; c< records.length; c++) {
							if(records[c].id == selected_row.org_id) {
								// Check the item for the current organization and disable turning off
								$access_orgs.jqxDropDownList('updateAt', {
									value: selected_row.org_id,
									label: records[c].name+" [owner]",
									checked: true
								}, c);
								$access_orgs.jqxDropDownList('checkIndex', c);
								$access_orgs.jqxDropDownList('disableAt', c);
							} else if($.inArray(records[c].id.toString(), current_shares) > -1)
								// Turn on the rest considering current "shared_orgs" item in "selected_row"
								$access_orgs.jqxDropDownList('checkIndex', c);
						}
					}),
					checkboxes: true,
					displayMember: "name", valueMember: "id",
					width: 428,	height: 24, autoDropDownHeight: true,
					renderer: function(index, label, value) {
						var sho = master.dataSources.shareList.records[index];
						label="<span class='right'>("+sho.type_id+")</span>"+label;
						if(sho.owner)
							label+=" <b>[owner]</b>";
						if(sho.disabled)
							label+=" [disabled]";
						return label;
					}
				});

				// Setup the action for "OK" button
				$('#modal_convo-config_ok', $window).off('click').on('click', function()
				{
					// submit the create/edit form using ajax
					$('.modal_content > form', $window).ajaxSubmit({
						data: {
							id: selected_row.uid,
							link_id: master.config.id // ID check for link - The conversation is not updated if link_id is missing or wrong
						},
						dataType: 'json',
						beforeSubmit: function(formData, jqForm, options) {
							// Checks if file name has not been emptied
							if($("#convo-description", $window).val().length < 4) {
								cargohub.alert("The subject of the conversation cannot be empty.");
								return false;
							}
							return true;
						},
						success: function(x) {
							if(x.success) {
								// note has been updated - Close the window / Refresh the datapanel / Update the tab title to include the returned number of notes (counter)
								$window.jqxWindow('close');
								master.refresh_DataTable($("#tab_comm div.jqx-datatable"));
								// Finally, show the operation result
								master.toolbar_Report("div#tab_comm", x.response);
							} else
								cargohub.its.http_fail(null, "Record.Files::config_file");
						},
						error: cargohub.its.http_fail
					});
				});

				// Add tooltips
				$('.info-tip', $window).each(cargohub.components.ui.add_Tooltip);
			}
		});
	},

	/**
	 * Hides a Record-linked conversation from the organization. Refreshes the DataTable on success
	 * - If in public mode, visibility is switched to sharing and organization is removed from the sharing list
	 * - If in has "Sharing" visibility, it simply removes the organization from the sharing list
	 * - If in all other modes (private, org), the visibility is switched to "hidden"
	 * Conversations cannot be deleted for real from here.
	 */
	trash_Convo = function()
	{
		$.post("?action=trash_convo", {
			id: selected_row.uid,
			link_id: master.config.id // ID check for link - The conversation is not affected if link_id is missing or wrong
		}, function(x) {
			if(x.success) {
				select_Clear();
				$("#tab_comm div.jqx-datatable").jqxDataTable('updateBoundData');
				// Update counter on deletion (should be reduced by one compared to current if deleting from the perspective organization)
				if(x.update_count)
					cargohub.components.hashnav.update_Tab_Header(2, "Communication"+(x.count? " <span class='fiftycent'>["+x.count+"]</span>": ""), "/img/icons16/comments.png");
				cargohub.alert(x.response, "Success !", "thumb_up.png");
			} else
				cargohub.its.http_fail(null, "Record.Comm::trash_convo");
		})
		.fail(cargohub.its.http_fail);
	},

	/**
	 * Initialize the Communication tab toolbar (Read, Reply, Forward, Trash ... etc.)
	 */
	init_Toolbar = function()
	{
		$("#tab_comm .toolbar button")
		.jqxButton({ theme: cargohub.jqxtheme })
		/**
		 * Event: Toolbar button is clicked
		 */
		.on('click', function()
		{
			switch($(this).data('op')) {
			// Simply switches to the "Send Record" tab with Contact+E-mail mode automatically selected, and no included data turned on
			case 'comm-compose':
				master.select_Tab_With_Params('email');
				break;
			// Reloads the grid to check for any new messages.
			case 'comm-refresh':
				master.refresh_DataTable($("div.jqx-datatable", $list_area));
				break;

			case 'convo-read':
				open_Convo();
				break;
			case 'convo-reply':
				// The message compose is being activated in "empty" mode, automatically selecting as recipients everyone in the conversation but "yourself"
				// "Send Record" tab title is being changed to "Reply to..." and stays that way until the "Discard" button is used or the message is sent.
				// Recipient type is locked on "An E-mail contact" and Method to "Send an E-mail". Included data defaults to "none" however it's possible to turn the parts/contacts/files on (as part of the reply)
				// After sending, the focus is moved back to the Communication tab, which is reloaded to show the updated conversation
				// ????
				break;
			case 'convo-forward':
				// The message compose is activated with all content from previous messages quoted, and cursor being placed in the beginning of the conversation. No default recipient is set.
				// "Send Record" tab title is being changed to "Forward to..." and behaves like the "Reply" mode. However, Recipient type can be set to "organization", but "Share" mode is disabled.
				// If selecting an organization/department, the contact uses is selected automatically like for normal record send"
				// ????
				break;

			case "convo-config":
				convo_Config();
				break;

			case 'convo-trash':
				// TODO: Before even showing the option, check if the user has the necessary rights to delete the conversation (and that it belongs to his organization)
				if(!confirm("Are you sure you want to delete this conversation ?"))
					return;

				trash_Convo();
				break;
			}
		});

		if(cargohub.application.user_Is_Admin()) {
			// Checkbox for show closed (those with status "closed")
			$('#tab_comm #comm-show_trashed').jqxCheckBox({
				theme: cargohub.jqxtheme,
				height: 18
			})
			.on('change', function(e) {
				convos_Source._source.data.show_trashed = e.args.checked;

				// Reload the tasks table when the checkbox is changed
				master.refresh_DataTable($("div.jqx-datatable", $list_area));
			})
			.find("div.jqx-checkbox-default").css('margin-top', "2px");
		}
	},

	/**
	 * Initialize the Conversations DataTable. (This uses common functionality from the main Record Process module - cargohub.record_process )
	 */
	init_ListArea = function()
	{
		$list_area = $("#tab_comm #comm-list_area");

		// Options expander and controls
		$list_area.jqxExpander({
			theme: cargohub.jqxtheme,
			expanded: true
		});

		/**
		 * Highlights the background of each cell in a row (effectively highlighting the row itself) to indicate the conditions:
		 * - Shared files: Yellow
		 * - User private file: Pale Red
		 * Current perspective orgaization will have all it's text with "italic" style (Admin only)
		 */
		var cell_highlight = function(row, column, value, rowData) {
			var classname = "";

			if(rowData.visibility == 'shared' || rowData.visibility == 'public')
				classname+= "grid-cell-shared";
			else if(rowData.visibility == 'private')
				classname+= "grid-cell-private";
			else if(rowData.visibility == 'hidden' && column != "visibility")
				classname+= " fiftycent";

			// Mark files for the perspective org for the admin user
			if(cargohub.application.user_Is_Admin() && rowData.org_id == master.config.perspective)
				classname+=" grid-cell-perspective_org";
			return classname;
		};

		master.init_DataTable($("div.jqx-datatable", $list_area), {
			// Config
			source: convos_Source,
//			width: cargohub.fullwidth - 14,//IWI Record Details Page Redesign 10092026 
                        width: '100%', //IWI Record Details Page Redesign 10092026 
			columns: [
				{dataField: 'visibility', text: '<span class="access16 visi-public"></span>', width: 24, filterable: false,
					cellsRenderer: function(row, column, value, rowData) {
						return '<span class="access16 visi-'+rowData.visibility+'"></span>';
					}, cellClassName: cell_highlight},
				{dataField: 'description', text: "Description (Subject)",
					cellsRenderer: function(row, column, value, rowData) {
						return rowData.description? rowData.description: "<span class='gray'>(No description or subject was recorded)</span>";
					}, cellClassName: cell_highlight},
				{dataField: 'starter_from_name', text: "Started by", width: 160, filterable: true,
					cellsRenderer: function(row, column, value, rowData) {
						return "<span class='contact-item' data-con_id='"+rowData.starter_from_id+"' title='"+rowData.starter_from_email+"'>"+rowData.starter_from_name+"</span>";
					}, cellClassName: cell_highlight},
				{dataField: 'starter_time', text: "Date / Time", width: 90, cellsFormat: cargohub.datetime_format, align: 'center', cellsAlign: 'center',
					cellsRenderer: function(row, column, value) {
						return value? value: '<span class="gray">(unknown)</span>';
					}, filterable: false, cellClassName: cell_highlight}, // Filter not working on date :(
				{dataField: 'count_msgs', text: "Count", width: 55,
					cellsRenderer: function(row, column, value, rowData) {
						var ac = "<div><img src='/img/icons16/email.png' style='float: left; margin-left: 4px;' />&nbsp; "+rowData.count_msgs+ "</div>";
							ac+= "<div><img src='/img/icons16/vcard.png' style='float: left; margin-left: 4px;' />&nbsp; "+rowData.count_ctx+ "</div>";
						return ac;
					}, filterable: false, cellClassName: cell_highlight }, // No point in "filtering" a number
				{dataField: 'org_name', text: "Organization", width: 160, hidden: !cargohub.application.user_Is_Admin(), filterable: true, cellClassName: cell_highlight}
//				{dataField: 'starter_content', text: "1st message Content", hidden: true, filterable: true } // Message body doesn't display directly, but is used for searching/filtering and opening row details ( TODO )
			],
			rowDetails: true, initRowDetails: function(id, datarow, element, rowinfo) { // Row details renderer (shows involved contacts for the entire conversation)
				var tpl_data = clone(datarow), detailsHeight = 100;
				// Unpack the "contacts_pack" since it is displayed here.
				tpl_data.contacts = JSON.parse(datarow.contacts_pack);
				delete tpl_data.contacts_pack;

				tpl_data.starter_dt = $.format.date(datarow.starter_time, cargohub.datetime_format);

				// Add the number of contacts to the height, number of contacts might differ from conversation to conversation
				detailsHeight += tpl_data.contacts.length * 22;

				switch(tpl_data.visibility) {
					case 'private': tpl_data.visi_text = "Private conversation, visible only to the involved contacts"; break;
					case 'org': tpl_data.visi_text = "The starter's organization: "+datarow.org_name; break;
					case 'shared': tpl_data.visi_text = "Organizations sharing this "+master.config.type.name;
						if(tpl_data.access_orgs)
							tpl_data.visi_text+= " ("+ tpl_data.access_orgs.split(",").length+ " organizations)";
						break;
					case 'public': tpl_data.visi_text = "No access restrictions"; break;
					case 'hidden': tpl_data.visi_text = "<b>[Admin] This conversation has been trashed</b>."; break;
					default: tpl_data.visi_text = "(Unknown, possible database error)";
				}

				// Render the details row
				element.addClass('datatable convo-details').html($("#template-conversation-details").html().present(tpl_data)).height(rowinfo.detailsHeight);
				rowinfo.detailsHeight = detailsHeight;
			}
		}, {
			// Callbacks (for events happening in the datatable itself) - In most cases it will be either View or Edit
			"getrows": function() { /* Nothing here atm */ },
			"rowselect": select_Convo,
			"clearselect": select_Clear,
			"rowopen": open_Convo
		}, "comm");

	},

	/**
	 * Initialize the Conversations DataTable. (This uses common functionality from the main Record Process module - cargohub.record_process )
	 */
	init_ConvoArea = function()
	{
		$convo_area = $("#tab_comm #comm-convo_area");

		// Options expander and controls
		$convo_area.jqxExpander({
			theme: cargohub.jqxtheme,
			expanded: false
		}).show();
	}
	;

	this._public({
		/**
		 * Setup Communication Tab for Record Process module
		 */
		init: function()
		{
			init_Toolbar();
			init_ListArea();

			// init_ConvoArea(); // Initialized only when a message is being opened
			// init_ReplyArea(); // Initialized on first use, when one of the compose, reply or forward buttons are pressed.
			// ... probably better to use the "Send Record" tab instead in a special mode

			// Tooltips inside the Communication tab
			$("#tab_comm .info-tip").each(cargohub.components.ui.add_Tooltip);
		},

		/**
		 * Callback for actions to be performed when this tab is selected
		 */
		selected: function(params)
		{
			if(params && params.trigger)
				switch(params.trigger) {
				case "refresh":
					master.refresh_DataTable($("div.jqx-datatable", $list_area));
					break;
				}
		},

	});

});