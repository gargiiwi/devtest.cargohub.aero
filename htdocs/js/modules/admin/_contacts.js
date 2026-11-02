/**
 * Module "Global Contacts Management" (Admin only)
 * -- Display all Organizations in the system ( For now, only admin has access to this. In the future, "HeadQuarters" organizations will have access to sub-organizations )
 */
cargohub.admin_contacts = donrey.BASE._extend(function () {
    // private
    var self = this,
            c = null,
            selected_contact = null, // Selected contact in the grid
            input_theme_classes = "jqx-input jqx-input-" + cargohub.jqxtheme, // "jqx-widget-content jqx-widget-content-"+cargohub.jqxtheme - Not sure if these are needed or if they help in any way.

            // state of the pop-up windows used by this page - Note editor, E-mail send. Used for lazy initialization ( on first use )
            popups = {},
            listGridAdapter = null,
            setup_Contacts_Grid = function ()
            {
                var
                        // the data definition
                        gridDataFields = [
                            {name: 'id', type: 'int'},
                            {name: 'type', type: 'string'},
                            {name: 'ctime', type: 'date'},
                            {name: 'email', type: 'string'},
                            {name: 'name', type: 'string'},
                            {name: 'company_name', type: 'string', map: 'xdata>company_name'}, // This comes from contact data (might be missing)
                            // org_id, user_id and dep_id are only stored (for the type-specific operations)
                            {name: 'org_id', type: 'int'},
                            {name: 'dep_id', type: 'int'},
                            {name: 'user_id', type: 'int'},
                            {name: 'org_name', type: 'string', map: 'organization>name'}, // The organization owning the contact (or empty if global)
                            {name: 'count_msgs', type: 'int'},
                            {name: 'note_id', type: 'int'},
                        ],
                        // the column definition
                        gridColumns = [
                            {text: "Type", dataField: 'type', width: 105, filtertype: "checkedlist",
                                filteritems: ['system', 'global', 'organization', 'org_self', 'issue_target', 'department', 'dep_self', 'personal', 'user_self', 'auto_out', 'auto_in', 'isspost', 'isslink', 'unknown', 'draft'],
//				cellclassname: "statusrender",
//				cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
//				    return "<div class='"+ value.replace(/[ *!.0-9]/gi,'_') +"' title='"+ value +"'><sup>"+ value +"</sup></div>";
//				},
                                createfilterwidget: function (column, columnElement, widget) {
                                    widget.jqxDropDownList({
                                        dropDownWidth: 120,
                                        autoDropDownHeight: true,
//		                renderer: function (index, label, value) {
//		                	return "<div class='statusselect "+ value.replace(/[ *!.0-9]/gi,'_') +"'>"+ value +"</div>";
//		                },
                                        animationType: "fade"
                                    });
                                }
                            },
                            {text: "Created on:", dataField: 'ctime', cellsformat: cargohub.date_format, align: "center", cellsalign: "center", filtertype: 'range', width: 105},
                            {text: "E-Mail", dataField: 'email', filtercondition: "CONTAINS", width: 300},
                            {text: "Name", dataField: 'name', filtercondition: "CONTAINS", width: 220},
                            {text: "Company name", dataField: 'company_name', filtercondition: "STARTS_WITH", sortable: false}, // Company can only be filtered from the beginning
                            {text: "Msgs", dataField: 'count_msgs', width: 60, align: "right", cellsalign: "right", filtertype: "number"},
//			{text: "System organization", dataField: 'org_name', filtercondition: "STARTS_WITH", width: 200}
//			{text: "System organization", dataField: 'org_name', width: 200, filtertype: "checkedlist",
//				filteritems: self.config.orgList,
//				createfilterwidget: function (column, columnElement, widget) {
//			        widget.jqxDropDownList({
//			        	dropDownWidth: 200
//			        });
//				}
//			}
                        ];

                // prepare the data
                listGridAdapter = new $.jqx.dataAdapter({
                    url: '?action=Get_List',
                    data: {},
                    datatype: "json",
                    datafields: gridDataFields,
                    id: 'id',
                    root: "data",
                    // server side operations - filtering and sorting:
                    cache: false,
                    filter: function () {
                        $("#contacts-grid").jqxGrid('updatebounddata', 'filter');
                    },
                    sort: function () {
                        $("#contacts-grid").jqxGrid('updatebounddata', 'sort');
                    },
                    beforeprocessing: function (source) {
                        this.totalrecords = source.found_rows;
                    },
                    // Initial Sorting - Time when created, descending
                    sortcolumn: 'ctime',
                    sortdirection: 'desc'
                });

                // ... and init the grid
                $("#contacts-grid").jqxGrid({
                    theme: cargohub.jqxtheme,
                    source: listGridAdapter,
                    width: cargohub.fullwidth, autoheight: true,
                    columns: gridColumns, columnsresize: true, columnsmenu: false,
                    selectionmode: "singlerow", altrows: true,
                    pageable: true, pagesize: 20, pagesizeoptions: [10, 20, 50, 100, 250, 1000],
                    virtualmode: true, // virtual paging
                    rendergridrows: function (params)
                    {
                        return params.data;
                    },
                    filterable: true, showfilterrow: true, sortable: true
                })
                        /**
                         * Event: When a row is selected, the name of the organization is displayed on the toolbar, and is show with fade in (when selecting a row first time)
                         */
                        .on('rowselect', function (event)
                        {
                            var row = event.args.row;

                            $("#selected-item").html(row.email);
                            if (!selected_contact) {
                                $("div.edit-operations button").jqxButton({disabled: false});
                                donrey.cancel_Delay('hide_bar_edit_ops');

                                $('div.edit-operations').fadeIn();
                            }
                            // Depending on selected contact type, other bars might be shown too
                            $("div.special-ops").hide();
                            switch (row.type) {
                                case 'system':
                                    $('div.edit-operations').hide();
                                    break;
                                case 'organization':
                                case 'org_self':
                                case 'issue_target':
                                    $("#type-org_ops").show();
                                    break;
                                case 'department':
                                case 'dep_self':
                                    $("#type-dep_ops").show();
                                    break;
                                case 'user_self':
                                    $('div.edit-operations').hide();
                                case 'personal':
                                    $("#type-user_ops").show();
                                    break;
                                    // These types of contacts can be used to "Login As"
                                case 'isspost':
                                case 'isslink':
                                case 'draft':
                                    $('#type-issue_ops').show();
                                    break;
                            }
                            selected_contact = row;
                        })
                        /**
                         * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
                         */
                        .on('filter', function (event)
                        {
                            if (selected_contact) {
                                $(this).jqxGrid('clearselection');
                                selected_contact = null;
                                $("#selected-item").empty();
                                $("div.edit-operations button").jqxButton({disabled: true});

                                donrey.single_Delay('hide_bar_edit_ops', function () {
                                    $("div.edit-operations").fadeOut();

                                }, 500);
                                $("div.special-ops").hide();
                            }
                        })
                        /**
                         * Event: On doubleclicking, do the same as simple clicking then using the View/Edit button
                         */
                        .on('rowdoubleclick', function () {
                            if (selected_contact.type == 'user_self')
                                return cargohub.alert("USER_SELF contacts cannot be edited here. Locate the user in the <b>Users</b> module.");
                            $('.toolbar button[data-op="edit"]').trigger("click");
                        });
            },
            /**
             * Setups the inside of the contact editing window ( Contains organization selector and Contact Editor area itself )
             */
            setup_Contact_Editing = function ($window, mode)
            {
                // Setup the main contact editor
                var editor_link = "/global/contact?action=visual_card";
                if (mode == 'create')
                    editor_link += "&create=true";
                else
                    editor_link += "&edit=true&id=" + selected_contact.id;

                $("#contact-container", $window).load(editor_link, function () {
                    cargohub.components.contact_editor.setup({
                        container: $("#contact-container", $window)
                    }, self);
                });



                // The OK button
                $("#modal_contact-window_ok", $window).off('click').on('click', function ()
                {

                    var go = true;
                    if ($('#contact_password').length > 0) {
                        $('#contact_password').removeClass('error_blank');
                        $('#contact_con_password').removeClass('error_blank');
                        var contact_password = $('#contact_password').val();
                        var contact_con_password = $('#contact_con_password').val();
                        if (contact_password != '' && contact_con_password == '') {
                            go = false;
                            $('#contact_con_password').addClass('error_blank');
                        } else if (contact_password != '' && contact_con_password != '') {
                            go = false;
                            if (contact_password != contact_con_password) {
                                $('.error_msg_passwrod_change').show();
                                $('#contact_password').addClass('error_blank');
                                $('#contact_con_password').addClass('error_blank');
                            } else {
                                if (confirm('are u sure you want to chance the password as well?') == true) {
                                    $('.error_msg_passwrod_change').hide();
                                    $.post("/global/contact?action=Save_password_change_Contact", {
                                        id: selected_contact.id, // Might be missing (null), in this case the contact is actually created
                                        email: selected_contact.email,
                                        password: contact_password,
                                        count_msgs: selected_contact.count_msgs,
                                        type: "admin_change_password",
                                    }, function (getResponce) {
//                                    return false;
                                        selected_contact = null;
                                        if (getResponce.status == 'not_found') {
                                            cargohub.alert(getResponce.response, "Error !", "exclamation_old.png");
                                        } else if (getResponce.status == 'updated') {

// for update the other fields start

                                            var contact = null, status = cargohub.components.contact_editor.check_Status();
                                            if (status.valid) {
                                                // If changed, read the content and save
                                                if (status.changed) {
                                                    // Update the contact line and it's corresponding data
                                                    contact = cargohub.components.contact_editor.read_vCard();
                                                    // Save the contact changes to the database (or, create the contact draft if it didn't previously exist)
                                                    if (selected_contact)
                                                        contact.id = selected_contact.id;

                                                    $.post("/global/contact?action=Save_Contact", {
                                                        id: contact.id, // Might be missing (null), in this case the contact is actually created
                                                        name: contact.name,
                                                        email: contact.email,
                                                        xdata: JSON.stringify(contact.xdata),
                                                        type: "draft", // Ignored if the contact already exists
                                                    }, function (x) {
                                                        // On success, x.id should contain the db ID
                                                        selected_contact = null;
                                                        cargohub.alert(x.response, "Success !", "thumb_up.png");
                                                        $("#contacts-grid").jqxGrid('updatebounddata');
                                                        $("#contacts-grid").jqxGrid('clearselection');
                                                        $window.jqxWindow('close');
                                                    })
                                                            .fail(cargohub.its.http_fail);
                                                } else {
                                                    $window.jqxWindow('close');
                                                    return cargohub.alert("Contact has not been altered. Nothing to do.", 'No operation', "information.png");
                                                }
                                            } else
                                                return cargohub.alert("The contact has one or more invalid fields. Please check the empty or red-cut fields.");


// for update the other fields end


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
                        var contact = null, status = cargohub.components.contact_editor.check_Status();
                        if (status.valid) {
                            // If changed, read the content and save
                            if (status.changed) {
                                // Update the contact line and it's corresponding data
                                contact = cargohub.components.contact_editor.read_vCard();
                                // Save the contact changes to the database (or, create the contact draft if it didn't previously exist)
                                if (selected_contact)
                                    contact.id = selected_contact.id;

                                $.post("/global/contact?action=Save_Contact", {
                                    id: contact.id, // Might be missing (null), in this case the contact is actually created
                                    name: contact.name,
                                    email: contact.email,
                                    xdata: JSON.stringify(contact.xdata),
                                    type: "draft", // Ignored if the contact already exists
                                }, function (x) {
                                    // On success, x.id should contain the db ID
                                    selected_contact = null;
                                    cargohub.alert(x.response, "Success !", "thumb_up.png");
                                    $("#contacts-grid").jqxGrid('updatebounddata');
                                    $("#contacts-grid").jqxGrid('clearselection');
                                    $window.jqxWindow('close');
                                })
                                        .fail(cargohub.its.http_fail);
                            } else {
                                $window.jqxWindow('close');
                                return cargohub.alert("Contact has not been altered. Nothing to do.", 'No operation', "information.png");
                            }
                        } else
                            return cargohub.alert("The contact has one or more invalid fields. Please check the empty or red-cut fields.");



                    }



//                    return false;
//                    
//			var contact = null, status = cargohub.components.contact_editor.check_Status();
//			if(status.valid) {
//				// If changed, read the content and save
//				if(status.changed) {
//					// Update the contact line and it's corresponding data
//					contact = cargohub.components.contact_editor.read_vCard();
//					// Save the contact changes to the database (or, create the contact draft if it didn't previously exist)
//					if(selected_contact)
//						contact.id = selected_contact.id;
//
//					$.post("/global/contact?action=Save_Contact", {
//						id: contact.id, // Might be missing (null), in this case the contact is actually created
//						name: contact.name,
//						email: contact.email,
//						xdata: JSON.stringify(contact.xdata),
//						type: "draft", // Ignored if the contact already exists
//					}, function(x) {
//						// On success, x.id should contain the db ID
//						selected_contact = null;
//						cargohub.alert(x.response, "Success !", "thumb_up.png");
//						$("#contacts-grid").jqxGrid('updatebounddata');
//						$("#contacts-grid").jqxGrid('clearselection');
//						$window.jqxWindow('close');
//					})
//					.fail(cargohub.its.http_fail);
//				} else {
//					$window.jqxWindow('close');
//					return cargohub.alert("Contact has not been altered. Nothing to do.", 'No operation', "information.png");
//				}
//			} else
//				return cargohub.alert("The contact has one or more invalid fields. Please check the empty or red-cut fields.");
                });

                $(document).on('click', '#clickToGetPassword', function () {
                    console.log('Want to get auto password');




                    Swal.fire({
                        title: 'Are you sure you want to chance your password?',
                        showCancelButton: true,
                        confirmButtonText: 'Yes',
                    }).then((result) => {
                        /* Read more about isConfirmed, isDenied below */
                        if (result.isConfirmed) {
                            $('.simple-spinner-dot-dot-full-page').show();
                            var selected_contact = cargohub.components.contact_editor.read_vCard();
                            console.log(selected_contact);
                            $.post("/global/contact?action=Send_auto_generated_password", {
                                id: selected_contact.id, // Might be missing (null), in this case the contact is actually created
                                email: selected_contact.email
                            }, function (getResponce) {
                                console.log(getResponce);
                                if (getResponce.status == 'ok') {
                                    //cargohub.alert(getResponce.response, "Success !", "thumb_up.png");
                                    $('.simple-spinner-dot-dot-full-page').hide();

                                    Swal.fire({
                                        title: getResponce.response,
//                                        showDenyButton: true,
                                        showCancelButton: true,
                                        confirmButtonText: 'Copy Password',
//                                        denyButtonText: `Don't save`,
                                    }).then((result) => {
                                        /* Read more about isConfirmed, isDenied below */
                                        if (result.isConfirmed) {
                                            var $temp = $("<input>");
                                            $("body").append($temp);
                                            $temp.val(getResponce.new_pass).select();
                                            document.execCommand("copy");
                                            $temp.remove();


                                            Swal.fire('Copied!', '', 'success')
                                        }
                                    })

                                } else {
                                    cargohub.alert(getResponce.response, "Error !", "exclamation_old.png");
                                }

                            }).fail(cargohub.its.http_fail);
                        } else if (result.isDenied) {

                        }
                    })




                    /* if (confirm('Are you sure you want to chance your password?') == true) {
                     var selected_contact = cargohub.components.contact_editor.read_vCard();
                     console.log(selected_contact);
                     $.post("/global/contact?action=Send_auto_generated_password", {
                     id: selected_contact.id, // Might be missing (null), in this case the contact is actually created
                     email: selected_contact.email
                     }, function (getResponce) {
                     console.log(getResponce);
                     if(getResponce.status=='ok'){
                     //cargohub.alert(getResponce.response, "Success !", "thumb_up.png");
                     
                     
                     
                     
                     }else{
                     cargohub.alert(getResponce.response, "Error !", "exclamation_old.png");
                     }
                     
                     }).fail(cargohub.its.http_fail);
                     }*/
                });

            },
            dataAdapter_Admin_Orgs = new $.jqx.dataAdapter({
                url: "/admin/global/?action=Get_Related_Orgs",
                data: {
                    unselect_entry: true,
                    mode: "all",
                    sort: '`type_id` ASC, `name` ASC'
                },
                datatype: "json",
                datafields: [
                    {name: 'id', type: 'number'},
                    {name: 'group', type: 'string', map: "type_id"}, // Organization Type_ID becomes "group"
                    {name: 'name'}
                ],
                id: "id"
            }),
            /**
             * Sets up the toolbar with operations related to contacts (add, edit, merge, remove)
             */
            setup_Toolbar = function ()
            {
                cargohub.components.modal.create('contact-window', {
                    btn_roles: {cancel: "cancel"}
                });


                // Buttons to add/edit/remove an user from the department
                $('.toolbar button')
                        .jqxButton({theme: cargohub.jqxtheme})
                        .on('click', function () {
                            var data = $(this).data();
                            switch (data.op) {
                                case 'add': // Create new contact attached to the selected organization
                                    cargohub.components.modal.open('contact-window', {
                                        width: 694, height: 490,
                                        title: "Create contact",
                                        content: $("#template-contact-editor").html().present({
                                            _itc: input_theme_classes,
                                        }),
                                        callback: setup_Contact_Editing,
                                        cb_config: 'create'
                                    }, self);
                                    break;

                                case 'edit': // Edit existing contact - Also allows moving contacts between organizations !
                                    cargohub.components.modal.open('contact-window', {
                                        width: 694, height: 490,
                                        title: "Create contact",
                                        content: $("#template-contact-editor").html().present({
                                            _itc: input_theme_classes,
                                        }),
                                        callback: setup_Contact_Editing,
                                        cb_config: 'edit'
                                    }, self);
                                    break;
                                case 'caclogin': // Verifies if the contact has an associated contact account (or is being managed by another contact account), if not it is created (silently), then admin logs in as the contact account (frontend user)
                                    // - Confirm action!
                                    if (!confirm("Are you sure you want to login as contact account:\n" + selected_contact.email + " ?\n\nThis will log out the admin account !"))
                                        return;

                                    $.post("/contact/access?action=login_as", {
                                        'email': selected_contact.email
                                    }, function (x) {
                                        document.location = x.redirect; // user's page after login
                                    })
                                            .fail(cargohub.its.http_fail);
                                    break;
                                case 'related': // Opens other modules related to a specific contact ( organization, department, user ), or opens the multi-type issue overview window ( to be done ) showing issues which are linking this contact
                                    cargohub.alert("Not yet implemented. Sorry !");

                                    break;
                                    // Completely remove a contact from the system (and unlink from anywhere it is used).
                                case 'delete':
                                    if (!confirm("Are you sure you want to delete this contact?"))
                                        return;

                                    $.post("/contact/operations?action=delete", {
                                        id: selected_contact.id
                                    }, function (x) {
                                        // On success, it means the contact is deleted the grid should be refreshed
                                        if (x.success) {
                                            $("#contacts-grid").jqxGrid('updatebounddata');
                                            cargohub.alert(x.response, "Success !", "thumb_up.png");
                                        } else
                                            cargohub.alert(x.reason, "Message from server", "events.png");
                                    });
                                    break;
                            }
                        });

                // The organization filter (only shows contacts related to a specific organization)
                $(".toolbar #xfilter-org").jqxComboBox({
                    source: dataAdapter_Admin_Orgs,
                    theme: cargohub.jqxtheme,
                    displayMember: "name", valueMember: "id",
                    searchMode: "containsignorecase",
                    autoComplete: true, minLength: 0,
                    renderer: function (index, label, value) {
                        if (index == -1)
                            if (label == '.')
                                return "";
                            else
                                return "<h4>&middot;&middot;&middot; " + label + " &middot;&middot;&middot;</h4>";
                        if (index == 0)
                            return "<div class='thick center green'>" + label + "</div>";
//				var rec = dataAdapter_Admin_Orgs.records[index];
//				return '<span class="right gray">['+rec.group+']</span>'+ rec.name;
                        return label;
                    },
                    placeHolder: "(Loading organizations...)",
                    animationType: "none",
                    width: 250, dropDownWidth: 400,
                    disabled: true
                })
                        /**
                         * Event, the dropdown is loaded, show the default value and enable the control
                         */
                        .on('bindingComplete', function () {
                            $(this).jqxComboBox({
                                placeHolder: "Select organization:",
                                dropDownHeight: dataAdapter_Admin_Orgs.records.length > 9 ? 234 : null,
                                autoDropDownHeight: dataAdapter_Admin_Orgs.records.length <= 9 ? true : false,
                                disabled: false
                            });
                            /*
                             // Autoselect the current organization - Possible link from the Organization overview to show contacts connected to an organization?
                             if(self.config.perspective) {
                             sysChange = true;
                             for(c=0; c< dataAdapter_Admin_Orgs.records.length; c++)
                             if(dataAdapter_Admin_Orgs.records[c].id == self.config.perspective) {
                             $(this).jqxComboBox('selectIndex', c);
                             self.config.admin_org_name = dataAdapter_Admin_Orgs.records[c].name;
                             }
                             }
                             sysChange = false;
                             */
                        })
                        /**
                         * Event: The organization selector is changed - Filter the grid, only showing records from the selected organization
                         */
                        .on("change", function (event) {
                            if (!event.args)
                                return; // "Change" is also returned if typing and pressing enter, which is not relevant for us
                            if (!event.args.item.value) {
                                // (Unselect) was used
                                delete listGridAdapter._source.data["org_id"];
                                $("#contacts-grid").jqxGrid('updatebounddata', 'filter');
                            } else {
                                // Organization was chosen, send new parameter and reload the grid
                                listGridAdapter._source.data["org_id"] = event.args.item.value;
                                $("#contacts-grid").jqxGrid('updatebounddata', 'filter');
                            }
                        });
            };

    this._public({
        /**
         * Section initialization
         */
        init: function ()
        {
            cargohub.components.contact_editor.fields_structure = self.config.vcard_fields_structure;

            setup_Toolbar();
            setup_Contacts_Grid();

            // Info Explander
            $(".info-expander").jqxExpander({
                theme: cargohub.jqxtheme,
                expanded: false
            });
        }
    });
});
