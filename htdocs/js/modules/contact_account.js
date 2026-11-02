/**
 * Module "Contact Account"
 */
cargohub.contact_account = donrey.BASE._extend(function() {
    // private
    var self = this,
        c = null,
        input_theme_classes = "jqx-input jqx-rc-all jqx-widget jqx-widget-content jqx-widget-"+cargohub.jqxtheme+" jqx-input-"+cargohub.jqxtheme+" jqx-widget-content-"+cargohub.jqxtheme,

        // Tabs system
        loaded_tabs = {},				// Contains the list of tabs that have already been loaded and initialized (in this module, some tabs might get unloaded)
        tab_load_params = {},			// Optional parameters for when loading a tab. Usually empty, but for certain tabs it might contain information so the server knows how to assamble the tab's data

        // Tab: Issues
        selected_iss = null, // Selected issue in the grid
  
        // Tabs: Managed + Overview
        // self.config.contact = The currently connected contact user information (Contains only Id, Name, E-mail, not the xdata)
        selected_ctx = [],				// DB ID's of the selected contacts ; ... same as selected_convos
        active_contact_record = null,	// ... the selected unique managed contact (if any)
        active_contact = null,			// ... ?
 
        /**
         * Clears and optionally disables an item showing tab (Conversation or Vcard), making it ready to be loaded with new data
         * -- Usually as an action of clearing a selection in on of the grid tabs (List, Contacts), selecting multiple rows or using the "Close" command on the tab itself 
         * TODO: Doesnt' seem to be working properly.
         *
         * @param idx Can be either 1 or 3 (for now)
         */
        clear_Item_Tab = function(idx, title, disable)
        {
            var icon = idx==1? "issues": "vcard", tab_key = idx==1? "tab_vcard": "tab_issues";

            if(disable)
                $('#contact-account').jqxTabs('disableAt', idx);
            if(title)
                $('#contact-account li.jqx-tabs-title:eq('+idx+') div.jqx-tabs-titleContentWrapper').html('<img src="/img/icons16/'+icon+'.png" alt="'+title+'" /> '+title); // resets the header
            $('#contact-account').jqxTabs('setContentAt', idx, '<div style="text-align: center; padding: 30px;"><img src="/img/extra/loader3.gif" alt="Loading..." /></div>'); // clears content
            delete loaded_tabs[tab_key];
        },

        /**
         * Overview of the contact account - Lists the basic contact info (Name/Email), and possible actions
         * -- DISABLED
         */
        setup_Tab_Overview = function()
        {
            // the "any operation" buttons
            $("div#tab_overview .command > button").on('click', function() {
                var data = $(this).data();
                switch(data.op) {
                    // Opens a link specified in the action
                    case 'link':
                        document.location = data.link;
                        break;

                    // Displays an alert with an arbitrary text
                    case 'alert':
                        cargohub.alert(data.text);
                        break;

                    case 'edit-self':
                        active_contact_record = self.config.contact;
//					clear_Item_Tab(1);
                        $('#contact-account').jqxTabs('select', 1);
                        break;
                }
            });

            // Tooltips might appear anywhere
            $("div#tab_overview .info-tip").each(function() {
                $(this).jqxTooltip({
                    theme: cargohub.jqxtheme,
                    content: $(this).data('tooltip'),
                    position: 'bottom',
                    width: $(this).data('tooltip_width') || "auto",
                    autoHideDelay: 10000
                });
            });
        },

        /**
         * List of managed contacts
         */
        setup_Tab_Managed = function()
        {
            $("div#tab_managed .toolbar button")
                .jqxButton({ theme: cargohub.jqxtheme })
                .on('click', function()	{
                    var data = $(this).data();
                    switch(data.op) {

                    }
                });
        },

        /**
         * Displays full contact information (with edit ability) - Using "contact_editor" system
         */
        setup_Tab_VCard = function()
        {
            // setup toolbar
            $("div#tab_vcard .toolbar button")
                .jqxButton({ theme: cargohub.jqxtheme })
                .on('click', function()	{
                    var data = $(this).data();
                    switch(data.op) {
                        case 'vcard-close':
                            // TODO: Actually "close" the tab here, and unselect from the "managed" grid if previously selected
                            // TODO: The "Close" operation
//				setTimeout(contact_Grid_Unselect, 300); // Waits a bit for the tab system to move (which takes 250ms), then clears the address editor tab
                            break;

//			case 'tab-overview': // Select second tab ( Managed contacts list )
//				$('#contact-account').jqxTabs('select', 0);
//				break;

                        case 'vcard-save':
                            
//                            change user password from admin and user end  START
                             var go = true;

                                    if ($('#contact_password').length > 0) {
                                        selected_contact = cargohub.components.contact_editor.read_vCard();
                                        $('#old_password').removeClass('error_blank');
                                        $('#contact_password').removeClass('error_blank');
                                        $('#contact_con_password').removeClass('error_blank');
                                        var old_password = $('#old_password').val();
                                        var contact_password = $('#contact_password').val();
                                        var contact_con_password = $('#contact_con_password').val();

                                        if (old_password != '' && contact_password == '' && contact_con_password == '') {
                                            go = false;
                                            $('#contact_password').addClass('error_blank');
                                            $('#contact_con_password').addClass('error_blank');
                                        } else if (old_password != '' && contact_password != '' && contact_con_password == '') {
                                            go = false;
                                            $('#contact_con_password').addClass('error_blank');
                                        } else if (old_password == '' && contact_password != '' && contact_con_password == '') {
                                            go = false;
                                            $('#old_password').addClass('error_blank');
                                            $('#contact_con_password').addClass('error_blank');
                                        } else if (old_password != '' && contact_password != '' && contact_con_password != '') {
                                            go = false;
                                            if (contact_password != contact_con_password) {
                                                $('.error_msg_passwrod_change').show();
                                                $('#contact_password').addClass('error_blank');
                                                $('#contact_con_password').addClass('error_blank');
                                            } else {

//                                                console.log('sddsds');
                                                if (confirm('are u sure you want to chance the password as well?') == true) {
                                                    $('.error_msg_passwrod_change').hide();
                                                    $.post("/global/contact?action=Save_password_change_Contact", {
                                                        id: selected_contact.id, // Might be missing (null), in this case the contact is actually created
                                                        email: selected_contact.email,
                                                        old_password: old_password,
                                                        password: contact_password,
                                                        count_msgs: selected_contact.count_msgs,
                                                        type: "user_change_password",
                                                    }, function (getResponce) {
//                                    return false;
                                                        selected_contact = null;
                                                        if (getResponce.status == 'not_found') {
                                                            cargohub.alert(getResponce.response, "Error !", "exclamation_old.png");
                                                        } else if (getResponce.status == 'not_match') {
                                                            cargohub.alert(getResponce.response, "Error !", "exclamation_old.png");

                                                        } else if (getResponce.status == 'updated') {


// Assambles the date from all the fields which are editable on this tab and does an ajax request with the JSON encoded string
                                                            var contact_status = cargohub.components.contact_editor.check_Status(), contact = null;
                                                            if (contact_status.changed && contact_status.valid) {
                                                                contact = cargohub.components.contact_editor.read_vCard();
                                                                if (active_contact_record)
                                                                    contact.id = active_contact_record.id;

                                                                // Save the contact changes to the database (or, create the contact draft if it didn't previously exist)
                                                                $.post("/global/contact?action=save_contact", {
                                                                    id: contact.id, // Might be null, in this case the contact is actually created
                                                                    name: contact.name,
                                                                    email: contact.email,
                                                                    xdata: JSON.stringify(contact.xdata),
                                                                    type: 'draft' // When creating new contacts from the Contact Account, they are saved as drafts. Only when used in an issue they will become "isslink"
                                                                }, function (x) {
                                                                    $("div#tab_vcard button#contact-save-button").jqxButton({disabled: true}).jqxButton('render');

                                                                    cargohub.alert(x.response, "Success !", "thumb_up.png");
                                                                })
                                                                        .fail(cargohub.its.http_fail);
                                                            }




                                                        }
                                                    }).fail(cargohub.its.http_fail);
                                                } else {
                                                    $('#contact_password').val('');
                                                    $('#contact_con_password').val('');
                                                }
                                            }
                                        }
                                    }

                                    if (go) {

// Assambles the date from all the fields which are editable on this tab and does an ajax request with the JSON encoded string
                                        var contact_status = cargohub.components.contact_editor.check_Status(), contact = null;
                                        if (contact_status.changed && contact_status.valid) {
                                            contact = cargohub.components.contact_editor.read_vCard();
                                            if (active_contact_record)
                                                contact.id = active_contact_record.id;

                                            // Save the contact changes to the database (or, create the contact draft if it didn't previously exist)
                                            $.post("/global/contact?action=save_contact", {
                                                id: contact.id, // Might be null, in this case the contact is actually created
                                                name: contact.name,
                                                email: contact.email,
                                                xdata: JSON.stringify(contact.xdata),
                                                type: 'draft' // When creating new contacts from the Contact Account, they are saved as drafts. Only when used in an issue they will become "isslink"
                                            }, function (x) {
                                                $("div#tab_vcard button#contact-save-button").jqxButton({disabled: true}).jqxButton('render');

                                                cargohub.alert(x.response, "Success !", "thumb_up.png");
                                            })
                                                    .fail(cargohub.its.http_fail);
                                        }
                                    }
                                    
                                    
//                                    change user password from admin and user end  END
                            
//                            
//                            // Assambles the date from all the fields which are editable on this tab and does an ajax request with the JSON encoded string
//                            var contact_status = cargohub.components.contact_editor.check_Status(), contact = null;
//                            if(contact_status.changed && contact_status.valid) {
//                                contact = cargohub.components.contact_editor.read_vCard();
//                                if(active_contact_record)
//                                    contact.id = active_contact_record.id;
//
//                                // Save the contact changes to the database (or, create the contact draft if it didn't previously exist)
//                                $.post("/global/contact?action=save_contact", {
//                                    id: contact.id, // Might be null, in this case the contact is actually created
//                                    name: contact.name,
//                                    email: contact.email,
//                                    xdata: JSON.stringify(contact.xdata),
//                                    type: 'draft' // When creating new contacts from the Contact Account, they are saved as drafts. Only when used in an issue they will become "isslink"
//                                }, function(x) {
//                                    // On success, x.id should contain the ID
//
//                                    // ===== List of contacts is not enabled, can only edit one single contact ====
//
//                                    // If this was a create operation, close the tab and show the grid
////						if(!active_contact_record) {
////								contact_Grid_Unselect();
////							$('#tab_managed #ctx-grid').jqxGrid('updatebounddata');
////						}
//
//                                    // When the logged in contact account itself was updated, revert tab 1 ("My account") to non-loaded state, so it can be refreshed
////						if(contact.id == self.config.contact.id) {
////							clear_Item_Tab(1);
////						} else {
//                                    // Otherwise refresh the "Other contacts" grid ( tab 2 "managed" )
//                                    // $('#tab_managed #ctx-grid').jqxGrid('updatebounddata');
//
////						}
//
//                                    $("div#tab_vcard button#contact-save-button").jqxButton({disabled: true}).jqxButton('render');
//
//                                    cargohub.alert(x.response, "Success !", "thumb_up.png");
//                                })
//                                    .fail(cargohub.its.http_fail);
//                            }
                            break;

//			case 'vcard-delete':
                        // TODO: Delete button should not be visible for "New contact"
                        // Asks for the server to delete current contact then refreshes the grid like for saving
                        //			if(confirm("Are you sure you want to delete this contact ?\nThe operation is irreversible !"));
                        //			contact_Delete(selected_contact.id);
                        //		break;
                    }
                });


//                                    change user password from admin and user end  START

$('#clickToGetPassword').on('click', function () {
                    console.log('Want to get auto password');

                    if (confirm('Are you sure you want to chance your password?') == true) {
                        var selected_contact = cargohub.components.contact_editor.read_vCard();
                        console.log(selected_contact);
                        $.post("/global/contact?action=Send_auto_generated_password", {
                            id: selected_contact.id, // Might be missing (null), in this case the contact is actually created
                            email: selected_contact.email
                        }, function (getResponce) {
                            console.log(getResponce);
                            if(getResponce.status=='ok'){
                                cargohub.alert(getResponce.response, "Success !", "thumb_up.png");
                            }else{
                                cargohub.alert(getResponce.response, "Error !", "exclamation_old.png");
                            }
                            
                        }).fail(cargohub.its.http_fail);
                    }


                });
                
                
                
//                                    change user password from admin and user end  END
                

            // Begin by having the save button disabled, it will be reenabled after a valid change.
            $("div#tab_vcard button#contact-save-button").jqxButton({disabled: true}).jqxButton('render');

            // Some events that happen while editing contact
            cargohub.components.contact_editor.events = {
                changed: function(valid) {
                    if(valid)
                        $("div#tab_vcard button#contact-save-button").jqxButton({disabled: false}).jqxButton('render');
                    else
                        $("div#tab_vcard button#contact-save-button").jqxButton({disabled: true}).jqxButton('render');
                }
            };

            cargohub.components.contact_editor.setup({
                container: $("div#tab_vcard #contact-left-side"),
                params: {
                    // Must fill or it doesn't save
                    mandatory_fields: ['company_name']
                }
            }, self);
        },

        /**
         * Displays all submitted issues in a grid, which the ability to open the "view" mode for any of them ( /issue?action=view )
         */
        setup_Tab_Issues = function()
        {
            var $tab_issues = $('div#tab_issues');

            $('div#tid-query input#issue-search-tid', $tab_issues).addClass(input_theme_classes);
            $('div#tid-query input[type="submit"]', $tab_issues).jqxButton({ theme: cargohub.jqxtheme });

            $('div#tid-query form', $tab_issues).on('submit', function() {
                if($('div#tid-query input#issue-search-tid', $tab_issues).val().length<12) {
                    cargohub.alert("Please type your issue TrackID or select a row from the grid !");
                    return false;
                }
            });

            // Tooltips might appear anywhere
            $(".info-tip", $tab_issues).each(function() {
                $(this).jqxTooltip({
                    theme: cargohub.jqxtheme,
                    content: $(this).data('tooltip'),
                    position: 'bottom',
                    width: $(this).data('tooltip_width') || "auto",
                    autoHideDelay: 10000
                });
            });

            // the "any operation" buttons
            $(".command > button", $tab_issues).on('click', function() {
                var data = $(this).data();
                switch(data.op) {
                    // Opens a link specified in the action
                    case 'export':
                        $("div#iss-table", $tab_issues).jqxGrid('exportdata', $(this).data('format'), "grid-export", true, null, false, "/?action=export");
                        break;
                }
            });

            // Prepare issuetypes for the selector
            var	ist_id_list = [], issuetypes = (function() {
                    issuetypes = [];
                    for(c = 0; c<self.config.ist_selector.length; c++) {
                        issuetypes.push(self.config.ist_selector[c].name);
                        ist_id_list.push(self.config.ist_selector[c].id);
                    }
                    return issuetypes;
                }()),

                // the data definition
                gridDataFields = [
                    {name: 'id', type: 'number'},
                    {name: 'track'},
                    {name: 'access_key'},
                    {name: 'utime', type: 'date'},			// Update time
                    {name: '_group', map: 'group'},			// The group name (For several types it's "airline")
                    {name: 'type_id', type: 'number'},
                    {name: 'type_key'},
                    {name: 'type_name'},					// Issue type (Cargo Claim, Incident, etc.)
                    // Master organization for the issue
                    {name: 'org_id', type: 'number'},
                    {name: 'org_name'},
                    {name: 'xfer_time', type: 'date'},		// Time/Date when actually "submitted" to this organization (sent to)
                    {name: 'priority'},						// Priority and status for this Org.
                    {name: 'status'},
                    // Xfield Cache (5 columns)
                    {name: 'primary', map: "cache>primary"},
                    {name: 'secondary', map: "cache>secondary"},
                    {name: 'tertiary', map: "cache>tertiary"},
                    {name: 'quaternary', map: "cache>quaternary"},
                    {name: 'quinary', map: "cache>quinary"}
                ],
                // the column definition
                gridColumns = [
                    {
                        text: "Tracking #", dataField: 'track',
                        align: "center", cellsalign: "center",
                        width: "10%", minwidth: 80, maxwidth: 140,
                                cellsrenderer: function (row, columnfield, value, defaulthtml, columnproperties) {
                                    return '<div style="" class="cellStyle"><a href="/issue&action=view?track=' + value + '"  class="track_view">' + value + '</a> </div>';
                                },
                        exportable: false
                    }, {
                        text: "What kind of data?", dataField: 'type_name', width: "15%",
                        filtertype: "checkedlist", filteritems: issuetypes,
                        cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties)
                        {
                            var row_data = listGridAdapter.records[row];
                             return '<div style="" class="cellStyle">' + value + '</div>';
                        },
                        createfilterwidget: function (column, columnElement, widget)
                        {
                            widget.jqxDropDownList({
                                dropDownWidth: 140,
                                autoDropDownHeight: true,
                                renderer: function (index, label, value) {
                                    return '<div>'+(index?'<img src="/img/icons16/ist/'+ self.config.ist_selector[index-1].key +'.png" class="in-menu-icon16" style="width: 12px;"/> ':'')+value+'</div>';
                                },
                                animationType: "fade"
                            });
                        }
                    }, {
                        text: "Cache Title", dataField: 'primary', width: "25%",
                        cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties)
                        {
                             return '<div class="cacheVal cellStyle">'+value+'</div>';
                        }
                    }, 
                    // {
                    //     text: "Secondary", dataField: 'secondary', width: 110
                    // },
                    /* {
				text: "Extra", dataField: 'tertiary', width: 90
			}, {
				text: "Field 4", dataField: 'quaternary', width: 90
			}, {
				text: "Field 5", dataField: 'quinary', width: 90
			},*/ {
                        text: "E-mail sent on...", dataField: 'xfer_time',
                        width: "15%", align: "center", cellsalign: "center", cellsformat: cargohub.date_format, filtertype: 'range',
                        cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties)
                        {
                            if (!value) {
      return '<div class="cellStyle" style="color:red; ">No E-mail sent</div>';
    }

    // Convert value to Date object (works even if value is a string from server)
    var date = (value instanceof Date) ? value : new Date(value);

    // Format date manually using jqx utility
    var formatted = $.jqx.dataFormat.formatdate(date, cargohub.date_format);

    // Add ellipsis style without breaking layout
    return '<div class="cellStyle ellipsis" style="" title="' + formatted + '">' + formatted + '</div>';
  
                        }
                     },
                    // {
                    //     text: "for Organization", dataField: 'org_name',
                    //     width: 150,	filtertype: "checkedlist",
                    //     cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties)
                    //     {
                    //         if(value == "(unmanaged)")
                    //             return "<div class='gray cellStyle' style='text-align: center; margin-top: 5px;'>"+value+"</div>";
                    //         else
                    //             return "<div class=' cellStyle' style='text-align: center; margin-top: 5px;'>"+value+"</div>";
                    //     },
                    //     createfilterwidget: function (column, columnElement, widget)
                    //     {
                    //         widget.jqxDropDownList({
                    //             dropDownWidth: 350
                    //         });
                    //     }
                    // },
                     {
                        text: "Status", dataField: 'status',
                        width: "10%", align: "center", cellsalign: "center",
                        filtertype: "checkedlist",
                        cellclassname: "statusrender",
                        cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties)
                        {
                            if(value == "(unavailable)")
                                return "<div class='gray cellStyle'>(unavailable)</div>";
                            else if(value)
                                return "<div class='"+ value.replace(/[ *!.0-9]/gi,'_') +" cellStyle cellStatus' title='"+ value +"'><sup>"+ value +"</sup></div>";
                            else
                                return '';
                        },
                        createfilterwidget: function (column, columnElement, widget)
                        {
                            widget.jqxDropDownList({
                                dropDownWidth: 150,
                                renderer: function (index, label, value) {
                                    if(value == "(Select All)")
                                        return value;
                                    if(value && value != "(unavailable)") 
                                        return "<div class='statusselect  cellStyle "+ value.replace(/[ *!.0-9]/gi,'_') +"'>"+ value +"</div>";
                                    else
                                        return "<div class='statusselect  cellStyle >"+(unavailable) +"</div>";
                                },
                                animationType: "fade"
                            });
                        }
                    }, {
                        text: "Last updated", dataField: 'utime',
                        width: "10%", align: "center", cellsalign: "center", cellsformat: cargohub.date_format, filtertype: 'range',
                        cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties)
                        {
                            return '<div style="text-align: center; padding-bottom: 2px; margin-top: 5px;" class=" cellStyle">' + cargohub.application.calc_Relative_Time(value) + '</div>';
                        }
                    },
                    {
                        text: "Carrier or Category", dataField: '_group',
                        width: "15%",
                        filtertype: "checkedlist",
                        cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties)
                        {
                            return '<div  class=" cellStyle">' + value + '</div>';
                        }
                    },
                    /* {
				text: 'Priority', dataField: 'priority',
				width: 80, align: "center", cellsalign: "center", filtertype: "checkedlist",
				// Special cells renderer for priority - It displays an icon instead of text
				cellclassname: "priorityrender",
				cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties)
				{
					if(value == "(unavailable)")
						return "<div class='gray' style='text-align: center; margin-top: 5px;'>-</div>";
					else if(value)
						return "<div class='"+ value.replace(/[ *!.0-9]/gi,'_') +"' title='"+ value +"'></div>";
					else
						return "";
				},
				createfilterwidget: function (column, columnElement, widget)
				{
					widget.jqxDropDownList({
						dropDownWidth: 140,
						autoDropDownHeight: true,
						renderer: function (index, label, value) {
							if(value == "(Select All)")
								return value;
							if(value && value != "(unavailable)")
								return "<div class='priorityselect "+ value.replace(/[ *!.0-9]/gi,'_') +"'>"+ (index>0?value.substr(2):value) +"</div>";
							else
								return "(unavailable)";
						},
						animationType: "fade"
					});
				}
			},*/ 
                ],
                // prepare the data
                listGridAdapter = new $.jqx.dataAdapter({
                    url: '/issue?action=get_special_list',
                    /*			data: {
                                    locator: "contact",
                                    contact_ids: self.config.contact.id+(self.config.managed_ctx?',' +self.config.managed_ctx:''),
                                    ist_filter: ist_id_list.join(',')
                                },*/
                    // Find issues submitted by a certain e-mail address
                    data: {
                        locator: "email",
                        email: self.config.contact.email,
                        contact_types: "user_self|isspost|isslink|personal|department|organization|auto_in|draft",
                        roles: "submitter",
                        ist_filter: ist_id_list.join(',')
                    },
                    datatype: "json",
                    datafields: gridDataFields,
                    id: 'id'
                }),
                emailSelectorAdapter = new $.jqx.dataAdapter({
                    url: '?action=get_emails',
                    datatype: "json",
                    datafields: [
                        {"name": 'email'},
                        {"name": 'name'}
                    ],
                    id: "id"
                });

            // The E-mail (contact) selector - Grid lists issues submitted under a certain e-mail address. Changing the e-mail reloads the grid
            $("#contact-email-select", $tab_issues).jqxDropDownList({
                theme: cargohub.jqxtheme,
                source: emailSelectorAdapter,
                displayMember: "email",
                valueMember: "name",
                renderer: function(index, label, value) {
                    return label + ' ('+ value+')';
                },
                width: 330, //IWI front end user record list page new design
                height: 30, //IWI front end user record list page new design
                placeHolder: "Loading...",
                autoDropDownHeight: true
            })
                /**
                 * Event: One of the E-mail addresses is selected.
                 */
                .on("select", function(e)
                {
                    if (e.args && e.args.item) {
                        $("#contact-name", $tab_issues).html(e.args.item.originalItem.name);
                        listGridAdapter._source.data.email = e.args.item.originalItem.email;
                        $("#iss-table", $tab_issues).jqxGrid({source: listGridAdapter});
                    }
                })
                /**
                 * Event: Email list loaded, select the first
                 */
                .on("bindingComplete", function(e)
                {
                    $(this).jqxDropDownList({
                        placeHolder: "Select the E-mail used to submit"
                    });
                    $(this).jqxDropDownList('selectIndex', 0);
                });
            $("#iss-table", $tab_issues).jqxGrid({
    theme: cargohub.jqxtheme,
    source: gridColumns, 
    width: "100%",
    autoheight: true,
    columns: gridColumns,
    columnsmenu: false,
    selectionmode: "singlerow",
    altrows: true,
    pageable: true,
    pagesize: 10,
    pagesizeoptions: [10, 15, 25, 50, 100],
    filterable: true,
    showfilterrow: true,
    sortable: true,
    rowsheight: 35
})

            
            $("#iss-table", $tab_issues).css({
            "border": "1px solid #ddd",
            "border-radius": "6px",
            "background-color": "#fff",
            "box-shadow": "0 1px 4px rgba(0,0,0,0.1)",
            // "font-family": "Segoe UI, Arial, sans-serif",
            "font-size": "13px",
            "color": "#333"
            })

                /**
                 * Event: When a row is selected, the name of the organization is displayed on the toolbar, and is show with fade in (when selecting a row first time)
                 */
                .on('rowselect', function (event)
                {
                    selected_iss = event.args.row;
                    $('div#tid-query input#issue-search-tid', $tab_issues).val(selected_iss.track);
                })
                /**
                 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
                 */
                .on('filter', function(event)
                {
                    if(selected_iss) {
                        $(this).jqxGrid('clearselection');
                        selected_iss = null;
                    }
                })
                /**
                 * Event: On doubleclicking, do the same as simple clicking then using the View issue button
                 */
                .on('rowdoubleclick', function(){
                    $('#tid-query input[type="submit"]', $tab_issues).trigger("click");
                });
        },

        /**
         * Displays the conversations in which this contact is involved, opening one will show the conversation display, which has the embedded "reply" function ( /global/conversation )
         */
//	setup_Tab_Comm = function()
//	{
//		dd("TAB Communication");
//	},

        /**
         * Sets up the tabs visible on the user control panel module (also known as "dashboard")
         */
        setup_Tabs = function()
        {
            var disable_onhashchange = true,
                selected_tab_idx = 0, selected_tab = 'tab_issues'; // defaults to first tab: List of submitted issues

            // discover if a subtab is specified in the URL ( to jump directly to it )
            c = 0;
            $('#contact-account > div').each(function() {
                if(document.location.hash.substr(1) == $(this).prop('id').substr(4)) {
                    selected_tab = $(this).prop('id');
                    selected_tab_idx = c;
                }
                c++;
            });

            $('#contact-account').jqxTabs({
                theme: cargohub.jqxtheme,
//                width: 1310,
                position: 'top',
                selectionTracker: true, selectedItem: selected_tab_idx,
                keyboardNavigation: false
            })
                /**
                 * Event: When selecting a tab for the first time, it is loaded from the server (except for the first one - tab_issues, which is initially loaded with the page)
                 */
                .on('selected', function(event, auto_trigger) {
                    cargohub.application.reset_Session_Timer();
                    disable_onhashchange = true;

                    if(!auto_trigger)
                        selected_tab = $('#contact-account').jqxTabs('getContentAt', event.args.item).attr('id');
                    document.location.hash = selected_tab.substr(4);

                    // NOTE: The "contact", "issues", "conversations" tabs can load content from another managed contact (present in the list in the "managed" tab).
                    // By default they load the primary logged in contact.
                    if(!loaded_tabs[selected_tab]) {
                        // doesn't yet exist, load it's HTML and initialize
                        $.get("?action=load_tab_html&tab=" + selected_tab, tab_load_params, function(tab_contents, textStatus, jqXHR)
                        {
                            // Load extra data for this tab, if it exists, and store it in the local object
                            donrey.read_JSON_Headers(jqXHR, self.config);

                            $('div#' + selected_tab).html(tab_contents);

                            // run init function for this tab when loading is complete
                            switch(selected_tab) {
                                case 'tab_issues': //0
                                    setup_Tab_Issues(); break;
//						case 'tab_managed':
//							setup_Tab_Managed(); break;
                                case 'tab_vcard': // 1
                                    setup_Tab_VCard(); break;
//						case 'tab_comm':
//							setup_Tab_Comm(); break;
                            }

                            // and finally, set the tab as initialized
                            loaded_tabs[selected_tab] = true;
                        });

                    }

                    setTimeout(function() {
                        disable_onhashchange = false;
                    }, 50);
                });

            // initialize the first selected tab by triggering the "selected" event
            $('#contact-account').trigger('selected', true);

            // hash-based navigation inside tabs themselves - Visually select tab when hash changes by typing in the URL or using back/forward (not by clicking on the tab itself)
            window.onhashchange = function(event) {
                // hash changes as well when the user is selecting the tab manually, in those cases there's no need to find and reselect the tab again !
                if(disable_onhashchange)
                    return;

                c = 0;
                $('#contact-account div.jqx-tabs-content > div').each(function() {
                    if(location.hash.substr(1) == $(this).prop('id').substr(4))
                        selected_tab_idx = c;
                    c++;
                });
                $('#contact-account').jqxTabs('select', selected_tab_idx);
            };
        }
    ;

    this._public({
        // section initialization
        init: function() {
//            $("div.site, div#user-menu-bar").css('width', 1310);
            
            
             
            // User Contact module consists of dynamically loaded tabs
            setup_Tabs();


   
            // Prepare fixed data for the contact editor
            cargohub.components.contact_editor.fields_structure = self.config.vcard_fields_structure;

            // Begin by setting the active_contact_record (the one used for 4th tab, contact editor) to be the logged in contact. It may be replaced by the various actions.
            active_contact_record = self.config.contact;
        }
    });
});


$('body').addClass('contact_account_issues_front_body');
function setup_UrlRedirect(that){
    if($(that).parent().parent().find('.track_view').attr('href')){
        location.href = window.location.origin + $(that).parent().parent().find('.track_view').attr('href');
    }
}