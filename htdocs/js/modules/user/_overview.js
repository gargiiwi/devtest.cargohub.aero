 /**
 * Module "User Overview"
 *  
 * @author Wavetrex Infinity <wavetrex@gmail.com> 
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2.9.1
 * 
 * FIXME: Make this more modular, it has grown too big - This is not using "hashnav", it should be converted to dynamic loading
 */
cargohub.user_overview = donrey.BASE._extend(function () {
    // private
    var self = this,
            c = null,
            user_ss = null,
            // state of the pop-up windows used by this page - Used for lazy initialization ( on first use )
            popups = {}, // FIXME: Outdated, use the "modal" component

            /** 
             * Setup the tab "Dashboard"
             */
            setup_Tab_Dashboard = function ()  
            { 
 
                init_RT_List();  
                var  
                        /**
                         * Setup the "Activity Overview" panel which (will) display the user's issue tasks and their deadlines, a report on new issues and possible other stuff
                         */
                        setup_Panel_Overview = function ()
                        {
                            // Work in progress
                        },
                        /**
                         * The notifications panels shows events that have happened recently
                         */
                        setup_Panel_Notifications = function ()
                        {
                            var source = [{
                                    id: 123,
                                    img: 'warning',
                                    event: "Something happened !",
                                    detail: "We think it's necessary to mention it"
                                }, {
                                    id: 125,
                                    img: 'add',
                                    event: "17 new Air Cargo Claims !",
                                    detail: "Added since yesterday 30 oct 2014"
                                }, {
                                    id: 129,
                                    img: 'right',
                                    event: "You have 5 active tasks",
                                    detail: "Please complete them ASAP ;)"
                                }, {
                                    id: 127,
                                    img: 'notify',
                                    event: "Recent system update",
                                    detail: "There might be some unresolved issues"
                                }];

                            $("#tab_dashboard #notifications-list").jqxListBox({source: source, displayMember: "event", valueMember: "id", itemHeight: 64, height: '100%', width: 352,
                                renderer: function (index, label, value) {
                                    var datarecord = source[index],
                                            imgurl = '/img/icons/evan/' + datarecord.img + '.png',
                                            img = '<img height="48" width="48" src="' + imgurl + '"/>',
                                            table = '<table style="min-height: 56px; width: 340px;"><tr><td style="width: 48px;" rowspan="2">' + img + '</td><td style="font-weight: bold;">' + datarecord.event + '</td></tr><tr><td>' + datarecord.detail + '</td></tr></table>';
                                    return table;
                                }
                            });
                        },
                        /**
                         * Setup the "Tools" panel which (currently) contains the ability to search and locate an issue directly
                         */
                        setup_Panel_Tools = function ()
                        {
                            // TrackID Search
                            if (cargohub.application.user_Test_Privileges('issues_view')) {
                                // The tracking ID autocomplete
                                $('#record_search #open_trackid').jqxInput({
                                    minLength: 2,
                                    theme: cargohub.jqxtheme,
                                    source: function (query, cb) {
                                        // search issues for trackid
                                        // FIXME: Make a better search. (And as reusable component, to be integrated anywhere)
                                        $.get("/issue/list?action=search_track", {
                                            q: query
                                        }, function (x) {
                                            cb(x);
                                        });
                                    }
                                })
                                        .on('keyup', function (e) {
                                            if (e.keyCode == 13)
                                                $('#record_search button').trigger("click");
                                        });

                                $('#record_search button').jqxButton({
                                    theme: cargohub.jqxtheme
                                })
                                        .on('click', function () {
                                            var trackid = $('#record_search #open_trackid').val();
                                            // prevent submitting the form if there are less than 12 characters in the Open by tracking ID control:
                                            if (trackid.length < 12)
                                                return cargohub.alert("Please enter a full Track ID ! (Use a result from the search box)");

                                            document.location = "/record/process:" + trackid + "#panels";
                                        });
                            }

                            var mn_changed = false, save_Mini_Notepad = function () {
                                if (!mn_changed)
                                    return;
                                $.post("?action=Save_MiniNotepad", {
                                    content: $('#mini_notepad_text').val()
                                }, function () {
                                    mn_changed = false;
                                })
                                        .fail(cargohub.its.http_fail);
                            };

                            // Mini Notepad
                            $('#mini_notepad_text')
                                    .on('input', function () {
                                        mn_changed = true;
                                        donrey.final_Delay('minisave', save_Mini_Notepad, 2000);
                                    })
                                    .on('propertychange', function () {
                                        donrey.final_Delay('minisave', save_Mini_Notepad, 2000);
                                    })
                                    /**
                                     * Event: When losing focus, the delay is cancelled and save proceeds imediately
                                     */
                                    .on('change', function () {
                                        donrey.cancel_Delay('minisave');
                                        save_Mini_Notepad();
                                    });
                            /*
                             // The Auto-Notification test too
                             $('#tab_dashboard #self-notify').jqxButton({
                             theme: cargohub.jqxtheme,
                             })
                             .on('click', function() {
                             $.post("?action=self_notify", function(x) {
                             if(x.success)
                             cargohub.alert(x.response, "Success !", "thumb_up.png");
                             else
                             cargohub.alert(x.reason, "Message from server");
                             });
                             
                             });
                             
                             $('#tab_dashboard #last-as-mail').jqxButton({
                             theme: cargohub.jqxtheme,
                             })
                             .on('click', function() {
                             $.post("?action=last_as_mail", function(x) {
                             if(x.success)
                             cargohub.alert(x.response, "Success !", "thumb_up.png");
                             else
                             cargohub.alert(x.reason, "Message from server");
                             });
                             });
                             */

                            $("#tab_dashboard .info-tip").each(cargohub.components.ui.add_Tooltip);
                        },
                        /**
                         * The panel containing a of recently opened issues ( all types ). DoubleClicking on one opens the issue.
                         */
                        setup_Panel_RecentIssues = function ()
                        {
                            if (!self.config.dataSources || !self.config.dataSources.recent)
                                return; // The panel doesn't exist, nothing to initialize

                            var window_id = "panel-recent_issues", $panel = $("#tab_dashboard #" + window_id), last_click_time = null, last_selected_item = null, selected_item = null;

                            // First, create the panel and attach it to the Docking, with it's events
                            $('#tab_dashboard > div:eq(0)').jqxDocking('addWindow', window_id, 'default', 2, 0);
                            $('#tab_dashboard > div:eq(0)').jqxDocking('showCollapseButton', window_id);
                            $('#tab_dashboard > div:eq(0)').jqxDocking('hideCloseButton', window_id);
                            $panel.show()
                                    /**
                                     * Event: Panel collapse - Unselect any element from the list and hide the buttons
                                     */
                                    .on('collapse', function (e) {
                                        if (e.target == this) {
                                            // When panel is collapsed, unselect any element from the list and hide the buttons
                                            $("#list-recent_issues", $panel).jqxListBox("clearSelection");
                                            $("div.jqx-window-header span.panel-commands.toggle-cmds:eq(1)", $panel).hide();
                                            $("div.jqx-window-header span.panel-commands.toggle-cmds:eq(0)", $panel).fadeIn();
                                            selected_item = null;
                                            last_selected_item = null;
                                            last_click_time = null;
                                        }
                                    })
                                    /**
                                     * Event: Panel expanded - Redraw the list
                                     */
                                    .on('expand', function (e) {
                                        $("#list-recent_issues", $panel).jqxListBox("invalidate");
                                    })
                                    ;

                            // Initialize the header bar buttons (if any) / or linkbuttons
                            $("div.jqx-window-header span.panel-commands button", $panel).each(function () {
                                $(this).jqxButton({
                                    theme: cargohub.jqxtheme
                                })
                                        /**
                                         * Event: A button is clicked
                                         */
                                        .click(function (event) {
                                            switch ($(this).data('cmd')) {
                                                // Send an ajax request indicating the list of recent items should be cleared (after a confirmation)
                                                case 'clear':
                                                    if (!confirm("Are you sure you want to clear this list ?"))
                                                        return;

                                                    $.post("?action=Issues_Recent_Clear", function (x) {
                                                        if (x.success) {
                                                            document.location.reload();
                                                        } else
                                                            cargohub.alert(x.reason, "Message from server");
                                                    });

                                                    break;
                                                    // Open an item simply loads the selected issue in the history list
                                                case 'open':
                                                    document.location = "/record/process:" + selected_item + "#panels";
                                                    return;

                                                    // Removes an item from the recent issues history
                                                case 'remove':
                                                    $.post("?action=Issues_Recent_Remove_Item", {
                                                        track: selected_item
                                                    }, function (x) {
                                                        if (x.success) {
                                                            $("#list-recent_issues", $panel).jqxListBox('removeAt', $("#list-recent_issues", $panel).jqxListBox('getSelectedIndex'));
                                                            $("#list-recent_issues", $panel).jqxListBox("clearSelection");
                                                            $("div.jqx-window-header span.panel-commands.toggle-cmds:eq(1)", $panel).hide();
                                                            $("div.jqx-window-header span.panel-commands.toggle-cmds:eq(0)", $panel).fadeIn();
                                                            selected_item = null;
                                                            last_selected_item = null;
                                                            last_click_time = null;

                                                            // Update the counter
                                                            var recent_count = parseInt($("#count-recent_issues", $panel).text()) - 1;
                                                            if (recent_count)
                                                                $("#count-recent_issues", $panel).text(recent_count);
                                                            else
                                                                $panel.jqxWindow('destroy');
                                                        } else
                                                            cargohub.alert(x.reason, "Message from server");
                                                    });
                                                    break;
                                            }
                                        });
                            });

                            // Init the Listbox
                            $("#list-recent_issues", $panel).jqxListBox({
                                source: self.config.dataSources.recent,
                                valueMember: "track",
                                height: '100%', width: 352,
                                renderer: function (index, label, value) {
                                    var datarecord = self.config.dataSources.recent[index],
                                            ist_img = '<img src="/img/icons16/ist/' + datarecord.rt_key + '.png" alt=' + datarecord.rt_name + ' style="float: left; margin-right: 4px;" />',
                                            track = '<span style="width: 120px; display: inline-block;">' + datarecord.track + "</span> ";

                                    return ist_img + track + (datarecord.pri ? '(' + datarecord.pri + ')' : '');
                                }
                            })
                                    /**
                                     * Event: When a row is selected, the buttons for Edit/Delete are made active.
                                     */
                                    .on('select', function (event) {
                                        if (!selected_item) {
                                            $("div.jqx-window-header span.panel-commands.toggle-cmds:eq(0)", $panel).hide();
                                            $("div.jqx-window-header span.panel-commands.toggle-cmds:eq(1)", $panel).fadeIn();
                                        }
                                    })
                                    /**
                                     * Event: Because double-clicking is not available as an event on lists, a trick is being made to detect if two quick clicks have been done in succession and on the same list item, in which case the issue is opened.
                                     */
                                    .on('click', function (event) {
                                        if (!last_click_time) {
                                            last_click_time = event.timeStamp;
                                            selected_item = last_selected_item = $("#list-recent_issues", $panel).val();
                                        } else {
                                            selected_item = $("#list-recent_issues", $panel).val();
                                            if (event.timeStamp - last_click_time < 300 && selected_item == last_selected_item) {
                                                document.location = "/record/process:" + selected_item + "#panels";
                                                return;
                                            } else {
                                                last_click_time = event.timeStamp;
                                                last_selected_item = selected_item;
                                            }
                                        }

                                    });
                        },
                        /**
                         * The shortcuts panel, which contains buttons to common modules
                         * TODO: Allow to user to define his shortcuts
                         */
                        setup_Panel_Shortcuts = function ()
                        {
                            // Nothing here (for now), as all the buttons are links right now
                        }
                ;

                // Setup the panel interface
                $('#tab_dashboard > div:eq(0)').jqxDocking({theme: cargohub.jqxtheme, orientation: 'horizontal', width: cargohub.fullwidth + 9});
                $('#tab_dashboard > div:eq(0)').jqxDocking("hideAllCloseButtons");
                $('#tab_dashboard > div:eq(0)').jqxDocking("showAllCollapseButtons");

                // Setup the various panels which are on the dashboard
//		setup_Panel_Overview();
//		setup_Panel_Notifications();
                setup_Panel_Tools();
                setup_Panel_RecentIssues();
                setup_Panel_Shortcuts();

                // the "command" buttons which are present in some of the panels
                $(".command > button").on('click', function () {
                    var data = $(this).data();
                    switch (data.op) {
                        // General "Link" button
                        case 'link':
                            document.location = data.link;
                            break;
                    }
                });
            },
            /**
             * Setup the tab "Profile" which contains a form for the user to update his name, e-mail address, signature and possibly change password
             */
            setup_Tab_Account = function ()
            {
                var // Current organization for a jumper user (unused for everyone else)
                        user_org_index = null,
                        // Datasource for the departments selector (might return nothing!)
                        departments_Source = new $.jqx.dataAdapter({
                            url: "?action=Get_User_Deps",
                            data: {
                            },
                            datatype: "json",
                            datafields: [
                                {name: 'id', type: 'int'},
                                {name: 'name', type: 'string'}
                            ],
                            id: "id"
                        }), $dep_select = $("#tab_account #dep_select"),
                        // Security and 2FA related
                        pwstr = 0,
                        otp = null;

                // Disable copy/paste in the password fields (must type it!)
                $("#tab_account #usr_password, #tab_account #usr_passwordr").on('paste', function (e) {
                    e.preventDefault();
                });

                // Password strength and validation
                $("#tab_account form#edit-account").jqxValidator({
                    rules: [
                        {input: "#usr_passwordr", message: "Enter same password again", action: 'blur', rule: function ($input) {
                                if ($input.val().length == 0)
                                    return true;
                                else
                                    return $input.val() == $('#usr_password').val();
                            }}
                    ]
                });
                $("#tab_account #usr_password").pwstrength({
                    callback: function (strength) {
                        pwstr = strength;
                    }
                });

                $("#tab_account form.vertiform input[type='submit']").css('padding', "5px 12px").jqxButton({theme: cargohub.jqxtheme});

                // Account form ajaxizing
                $("#tab_account form#edit-account").ajaxForm({
                    dataType: 'json',
                    beforeSubmit: function (formData, jqForm, options) {
                        // setup the field checking object
                        var f = {};
                        for (c = 0; c < formData.length; c++)
                            f[formData[c].name] = formData[c].value;
                        /*
                         if(!f['usr[email]'] || !f['usr[name]']) {
                         cargohub.alert("The fields My name and Email Address must not be empty");
                         return false;
                         }
                         */

                        if (f['usr[password]'] != f['usr[passwordr]']) {
                            cargohub.alert("Please enter the same password twice");
                            return false;
                        }

                        if (f['usr[password]']) {
                            if (pwstr < 3) {
                                cargohub.alert("Entered password is too insecure. Please read the hints for strong passwords.");
                                return false;
                            }

                            if (pwstr == 3) {
                                if (!confirm("Are you sure you want to proceed with this mediocre password?"))
                                    return false;
                            }
                        }

                        // Add the signature from the jqxEditor
                        f['usr[signature]'] = tinymce.activeEditor.getContent();
                        if (f['usr[signature]'].length > 0)
                            formData.push({
                                name: "usr[signature]",
                                value: f['usr[signature]']
                            });

                        if (otp) {
                            formData.push({
                                name: "otp",
                                value: otp
                            });
                        }

                        return true;
                    },
                    success: function (x) {
                        if (x.twofa) {
                            // Two-Factor Authentication required, open a prompt for the OTP
                            otp = prompt("Please enter your PIN");
                            if (!otp || otp.length != 6) {
                                otp = null;
                                return cargohub.alert("You must enter a PIN which is 6-charactes long");
                            }

                            // Resubmit, now with the OTP value stored
                            $("#tab_account form#edit-account").submit();
                        } else
                        if (x.success) {
                            // reload page to show the updated information
                            document.location.reload();
                        } else {
                            otp = null;
                            cargohub.alert(x.reason, "Message from server", "information.png");
                        }
                    }
                });

                // Signature editor via TinyMCE
                tinymce.baseURL = "/js/lib/tiny_mce";
                tinymce.suffix = '.min';
                tinymce.init({
                    selector: "textarea#usr_signature",
                    width: 660,
                    plugins: "link autolink autoresize paste textcolor image hr code",
                    toolbar: "bold italic underline | alignleft aligncenter alignright | fontselect fontsizeselect removeformat | forecolor backcolor | hr link image",
                    image_title: true,
                    automatic_uploads: true,
                    file_picker_types: 'image',
                    paste_data_images: true,
                    images_upload_url: '/files?action=open_upload',
                    file_picker_callback: function (cb, value, meta) {
                        var input = document.createElement('input');
                        input.setAttribute('type', 'file');
                        input.setAttribute('accept', 'image/*');

                        /*
                         Note: In modern browsers input[type="file"] is functional without
                         even adding it to the DOM, but that might not be the case in some older
                         or quirky browsers like IE, so you might want to add it to the DOM
                         just in case, and visually hide it. And do not forget do remove it
                         once you do not need it anymore.
                         */

                        input.onchange = function () {
                            var file = this.files[0];

                            var reader = new FileReader();
                            reader.onload = function () {
                                /*
                                 Note: Now we need to register the blob in TinyMCEs image blob
                                 registry. In the next release this part hopefully won't be
                                 necessary, as we are looking to handle it internally.
                                 */
                                var id = 'blobid' + (new Date()).getTime();
                                var blobCache = tinymce.activeEditor.editorUpload.blobCache;
                                var base64 = reader.result.split(',')[1];
                                var blobInfo = blobCache.create(id, file, base64);
                                blobCache.add(blobInfo);

                                /* call the callback and populate the Title field with the file name */
                                cb(blobInfo.blobUri(), {title: file.name});
                            };
                            reader.readAsDataURL(file);
                        }
                    },
                    toolbar_items_size: 'small',
                    menubar: false, statusbar: false,
                    min_height: 170, autoresize_min_height: 170, autoresize_max_height: 400,
                    // Fixes
                    convert_fonts_to_spans: true, fix_list_elements: true, browser_spellcheck: false
                });

                // The Organization jump drop-down-list
                if (self.config.features.org_selector) {
                    for (c = 0; c < self.config.dataSources.jump_orgs.length; c++) // find which organization is currently selected
                        if (self.config.dataSources.jump_orgs[c].id == self.config.user.org_id) {
                            user_org_index = c;
                            break;
                        }

                    $('#tab_account #org_select').jqxDropDownList({
                        theme: cargohub.jqxtheme,
                        source: self.config.dataSources.jump_orgs,
                        selectedIndex: user_org_index,
                        displayMember: "name", valueMember: "id",
                        width: 270, height: 23,
                        autoDropDownHeight: true
                    })
                            /**
                             * Event: Update the "Organization type" text when
                             * - If the user is the chief of the department
                             * - The user's comment related to department.
                             */
                            .on('change', function (event)
                            {
                                $("#org_type").html(self.config.dataSources.jump_orgs[event.args.item.index].orgtype.name);

                                // When organization is changed, update the datasource for the departments in order to show the list of member deps on the target organization
                                if (event.args.item.value) {
                                    // Read from a department membership list
                                    departments_Source._options.data = {
                                        org_id: event.args.item.value
                                    };
                                }
                                $dep_select.jqxDropDownList('clearSelection');
                                $dep_select.jqxDropDownList({
                                    source: departments_Source,
                                    placeHolder: "(Loading departments)",
                                    disabled: false
                                });
                            });
                }

//			for(c=0; c< self.config.dataSources.user_deps.length; c++) // find which department is currently selected
//				if(self.config.dataSources.user_deps[c].id == self.config.user.cdep_id) {
//					user_dep_index = c;
//					break;
//				}

                $dep_select.jqxDropDownList({
                    theme: cargohub.jqxtheme,
                    displayMember: "name", valueMember: "id",
                    width: 270, height: 23,
                    source: departments_Source,
                    autoDropDownHeight: true, animationType: "fade", enableBrowserBoundsDetection: true,
                    placeHolder: "(Loading departments)",
                    disabled: true
                })
                        /**
                         * Event: The department list finished loading, set the currently active department if assign mode is "department"
                         */
                        .on('bindingComplete', function () {
                            if (departments_Source.records.length == 0)
                                $(this).jqxDropDownList({
                                    placeHolder: "(Not a member of a department)",
                                    disabled: true
                                });
                            else {
                                $dep_select.jqxDropDownList({
                                    disabled: false,
                                    placeHolder: "Select department:"
                                });

                                // If this is a normal user or If user is jumper and selected org is his current org - Autoselect department
                                if (!self.config.features.org_selector || (self.config.features.org_selector && self.config.user.org_id == $('#tab_account #org_select').val())) {
                                    for (var c = 0; c < departments_Source.records.length; c++)
                                        if (departments_Source.records[c].id == self.config.user.cdep_id) {
                                            $(this).jqxDropDownList('selectIndex', c);
                                            break;
                                        }
                                }
                            }
                        });

                // Membership form ajaxizing
                $("#tab_account form#edit-membership").ajaxForm({
                    dataType: 'json',
                    beforeSubmit: function () {
                        // When there are departments to select, the user must select one to continue ! If he's not a member of any for selected organization (jumper only), nothing is needed.
                        if (departments_Source.records.length > 0 && $dep_select.jqxDropDownList('getSelectedIndex') == -1)
                            return cargohub.alert("Please choose an active department from the list");

                        return confirm("Are you sure you want to update membership ?\nThis action will affect your access to the system");
                    },
                    success: function (x) {
                        if (x.success) {
                            // logout the user after membership change
                            document.location = "/?action=logout";
                        } else
                            cargohub.its.http_fail(null, "User.Overview::Update_Membership");
                    },
                    error: function (xhr) {
                        cargohub.components.spinner.Off(500);
                        cargohub.its.http_fail(xhr, "User.Overview::Update_Membership");
                    }
                });

                // 2FA
                if (!self.config.features.twofa) {
                    $('#tab_account #twofa-enable').jqxSwitchButton({
                        theme: cargohub.jqxtheme,
                        width: 100, height: 25,
                        checked: false
                    })
                            /**
                             * Event: Change the department selector, update the value of "user_dep_index", as it might be used for organization jumper
                             */
                            .on('change', function (event)
                            {
                                if (event.args.checked) {
                                    // Attempted to turn the button on... generate a temporary key and show the window with the QR code and an input box to type in the PIN
                                    // If validated, a 2nd request will set 2FA on for the account.
                                    // Closing the window will remove the temporary key (from the session), and also set the button back to OFF.
                                    if (!popups.twofachk) {
                                        cargohub.its.init_Modal_Window('twofachk', null, {"cancel": "cancel"});
                                        popups.twofachk = true;
                                    }

                                    var config2fa = {
                                        authid: "CargoITS_" + self.config.user.email,
                                        oathkey: donrey.generate_UniqId('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', 16),
                                        size: 380
                                    };
                                    config2fa.chl = encodeURIComponent("otpauth://totp/" + config2fa.authid + "?secret=" + config2fa.oathkey);

                                    cargohub.its.open_Modal_Window('twofachk', 600, 600, "Enable Two-Factor Authentication", $("#template-twofa").html().present(config2fa), function ($window) // Setup the window
                                    {
                                        $('#modal_twofachk_ok', $window).off('click').on('click', function () {
                                            var otp = $("#twofa_otp").val();
                                            if (otp.length == 0)
                                                return cargohub.alert("You didn't type anything in the yellow box.");

                                            if (otp.length < 6)
                                                return cargohub.alert("The PIN should be 6-character long.");

                                            // TODO: Ajax request to verify and validate the entered PIN
                                            $.post("?action=check_2fa", {
                                                oathkey: config2fa.oathkey,
                                                otp: otp,
                                            }, function (x) {
                                                if (!x.success)
                                                    return cargohub.alert(x.reason, "Message from server");

                                                config2fa.validated = true;
                                                $window.jqxWindow('close');
                                            });
                                        });

                                        $window.on('close', function () {
                                            if (config2fa.validated)
                                                location.reload();
                                            else
                                                $('#tab_account #twofa-enable').jqxSwitchButton({checked: false});
                                        });
                                    });
                                }
                            });




                }





//Two factor off from user account Start


                $(document).on('click', '#twofa-off-btn', function () {

                    Swal.fire({
                        title: '',
                        text: "Are you sure you want to off Two-Factor Authentication?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, Off!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.post("?action=off_2fa", {
                                user_email: self.config.user.email,
                                user_id: self.config.user.id,
                            }, function (x) {
                                if (x.success) {
                                    window.location.replace("/?action=logout");
                                } else {
                                    return cargohub.alert(x.reason, "Message from server");
                                }

                            });

                        }
                    });

                });



//Two factor off from user account End 

            },
            /**
             * Displays full contact information (with edit ability) - Using "contact_editor" system
             */
            setup_Tab_VCard = function ()
            {
                cargohub.components.contact_editor.fields_structure = self.config.vcard_fields_structure;

                // setup toolbar
                $("div#tab_vcard .toolbar button")
                        .jqxButton({theme: cargohub.jqxtheme})
                        .on('click', function () {
                            var data = $(this).data();
                            switch (data.op) {
                                // Save personal contact information (primary contact). Synched with My account (Updating E-mail/Name also updates user info)
                                case 'vcard-save':
                                    // Assambles the data from all the fields which are editable on this tab and does an ajax request with the JSON encoded string

                                    var contact_status = cargohub.components.contact_editor.check_Status(), contact = null;
                                    if (contact_status.changed && contact_status.valid) {
                                        contact = cargohub.components.contact_editor.read_vCard();

                                        // Save the contact changes to the database (or, create the contact draft if it didn't previously exist)
                                        $.post("/global/contact?action=save_contact", {
                                            id: user_ss.contact.id,
//						name: contact.name,
//						email: contact.email,
                                            xdata: JSON.stringify(contact.xdata),
                                            type: "user_self"
                                        }, function (x) {
                                            // Update the form elements on first tab, to contain the contact.name, contact.email (in case the tab is loaded, of course, otherwise both these val()'s have no effect whatsoever
                                            // - The "save_contact" procedure should have synchronized the user as well
                                            // === Disabled, Name/E-mail cannot be changed here
//						$("div#tab_account input#usr_name").val(contact.name);
//						$("div#tab_account input#usr_email").val(contact.email);
                                            $("div#tab_vcard button#contact-save-button").jqxButton({disabled: true}).jqxButton('render');
                                            cargohub.alert(x.response, "Success !", "thumb_up.png");
                                        })
                                                .fail(cargohub.its.http_fail);
                                    }
                                    break;
                            }
                        });

                // Begin by having the save button disabled, it will be reenabled after a valid change.
                $("div#tab_vcard button#contact-save-button").jqxButton({disabled: true}).jqxButton('render');

                // Some events that happen while editing contact
                cargohub.components.contact_editor.events = {
                    changed: function (valid) {
                        if (valid)
                            $("div#tab_vcard button#contact-save-button").jqxButton({disabled: false}).jqxButton('render');
                        else
                            $("div#tab_vcard button#contact-save-button").jqxButton({disabled: true}).jqxButton('render');
                    }
                };

                cargohub.components.contact_editor.setup({
                    container: $("#tab_vcard"),
                    params: {
                        disable_primary_edit: true // Username and E-mail cannot be updated in normal user account (only from Superuser interface)
                    }
                }, self);

                // TODO: Integrate this into the Contact Editor component, so it can be used everywhere without duplication

                // SMTP settings attached to contact editor
                var $smtp_form = $("form#smtp_settings"), current_send_mtd = null;
                // The ON/OFF radio buttons (show/hide the rest of the form)
                $("#smtp_enable input", $smtp_form)
                        .on('change', function (e) {
                            current_send_mtd = $(this).val();

                            if ($(this).val() == 'smtp') {
                                $("tbody#smtp_off", $smtp_form).hide();
                                $("tbody#smtp_active", $smtp_form).fadeIn();
                            } else {
                                $("tbody#smtp_active", $smtp_form).fadeOut(function () {
                                    $("tbody#smtp_off", $smtp_form).show();
                                });
                            }
                        });

                // Automate server settings
                $("#auto_provider", $smtp_form).on('change', function (e) {
                    var option = $(e.target).val(), settings = {};
                    switch (option) {
                        case 'gmail':
                            settings.host = "smtp.gmail.com";
                            settings.smtp_encryption = "tls";
                            settings.port = 587;
                            settings.smtp_auth = "login";
                            break;
                        case 'outlook':
                            settings.host = "smtp.office365.com";
                            settings.smtp_encryption = "tls";
                            settings.port = 587;
                            settings.smtp_auth = "login";
                            break;
                        case 'yahoo':
                            settings.host = "smtp.mail.yahoo.com";
                            settings.smtp_encryption = "tls";
                            settings.port = 587;
                            settings.smtp_auth = "login";
                            break;
                        case '__empty':
                            if (!confirm("Are you sure you want to clear your settings?"))
                                return;

                            settings.host = "";
                            settings.smtp_encryption = "none";
                            settings.port = 25;
                            settings.smtp_auth = "login";
                            break;
                        default:
                            return; // Do nothing on the first option
                    }

                    // Change values of other form elements
                    $("input[name='smtp[host]']", $smtp_form).val(settings.host);
                    $("#smtp_encryption input[value=" + settings.smtp_encryption + "]", $smtp_form).prop("checked", true);
                    $("#smtp_port", $smtp_form).val(settings.port);
                    $("select[name='smtp[smtp_auth]']", $smtp_form).val(settings.smtp_auth);
                });

                // Secure channel radio buttons will modify the Port field under certain conditions
                $("#smtp_encryption input", $smtp_form)
                        .on('change', function (e) {
                            var auto_port = {
                                "tls": 587,
                                "ssl": 465,
                                "none": 25
                            }, current_port = $("#smtp_port", $smtp_form).val();

                            if (!current_port || current_port == 587 || current_port == 465 || current_port == 25)
                                $("#smtp_port", $smtp_form).val(auto_port[$(this).val()]);
                        });

                // Test button submits the form via Ajax
                $("#smtp_active input[type=button]", $smtp_form)
                        .jqxButton({theme: cargohub.jqxtheme})
                        .on('click', function () {
                            $smtp_form.ajaxSubmit({
                                dataType: 'json',
                                data: {
                                    contact_id: user_ss.contact.id,
                                    user_id: user_ss.id // User owning that contact, usually the logged in user. But if admin does the editing, the user_id will be different
                                },
                                beforeSubmit: function (formData, jqForm, options) {
                                    // setup the field checking object
                                    var f = {};
                                    for (var c = 0; c < formData.length; c++)
                                        f[formData[c].name] = formData[c].value;

                                    if (f['use_smtp'] == '0')
                                        return cargohub.alert("There is nothing to test if you're not using SMTP!");

                                    if (f['smtp[host]'].length < 3)
                                        return cargohub.alert("Please enter a server address for your SMTP settings");

                                    // Check if Auth model is anything other than "anon", username/password must be present
                                    if (f['smtp[smtp_auth]'] != 'anon') {
                                        if (f['smtp[username]'].length == 0 || f['smtp[password]'].length == 0)
                                            return cargohub.alert("Your selected authentication method requires username and password to be filled in");
                                    }

                                    // Check if PORT is not empty. Also, if Secure channel is TLS or SSL, port "25" is not accepted as an option
                                    if (f['smtp[port]'].length == 0)
                                        return cargohub.alert("Please set your connection port !");

                                    if (f['smtp[smtp_encryption]'] != 'none' && f['smtp[port]'] == '25')
                                        return cargohub.alert("When the channel is secured, port 25 is not accepted");

                                    // Show the spinner
                                    cargohub.components.spinner.Off();
                                    cargohub.components.spinner.On("Connecting...", "Talking with server", null, null, 500);
                                    return true;
                                },
                                success: function (x) {
                                    cargohub.components.spinner.Off();
                                    cargohub.alert(x.response, "Success !", "thumb_up.png");
                                    self.config.tab_vcard.smtp_active = true;
                                    $("#smtp_is_active", $smtp_form).fadeIn();
                                    $("#acc_smtp_active").show();
                                },
                                error: cargohub.its.http_fail
                            });
                        });

                $("#smtp_off input[type=button]", $smtp_form)
                        .jqxButton({theme: cargohub.jqxtheme})
                        .on('click', function () {
                            // Send a message that SMTP for current account should be temporarely deactivated (settings will remain valid)
                            $.post("/global/contact?action=Sending_Settings", {
                                contact_id: user_ss.contact.id,
                                send_mtd: current_send_mtd
                            }, function (x) {
                                cargohub.alert(x.response, "Success !", "thumb_up.png");
                                $("tbody#smtp_off", $smtp_form).hide();
                                $("#smtp_is_active", $smtp_form).hide();
                                $("#acc_smtp_active").hide();
                            })
                                    .fail(cargohub.its.http_fail);
                        });

                // Add tooltips
                $('.info-tip', $smtp_form).each(cargohub.components.ui.add_Tooltip);
            },
            /**
             * Sets up the user-visible groups ( in which he can set some options )
             */
            setup_Tab_Notify = function ()
            {
                var selected_ntf = null,
                        hide_to = [], // data_to_save = {}, data_changed = false,

                        /**
                         * Opens a notification item for reading (server side rendering)
                         */
                        open_Notification = function ()
                        {
                            var importance_trans = {
                                "High": "<img src='/img/icons16/exclamation.png' /> Please read this <b>important information</b>!",
                                "Med": "<img src='/img/icons16/events.png' /> You might be interested in this",
                                "Low": "<img src='/img/icons16/information.png' /> Low priority notification"
                            };

                            if (!popups.ntf) {
                                cargohub.its.init_Modal_Window('ntf', {"ok": "Ok"});
                                popups.ntf = true;
                            }

                            cargohub.its.open_Modal_Window('ntf', 500, 300, importance_trans[selected_ntf.importance], "Loading, please wait...", function ($window) // Setup the window
                            {
                                // ... and read full notification data with a request (including basic item data)
                                $("div.modal_content", $window).load("?action=read_notification&id=" + selected_ntf.uid);
                            });
                        },
                        /**
                         * Setup the Notificaiton Tab toolbar
                         */
                        setup_Notify_Toolbar = function ()
                        {
                            $("div#tab_notify .toolbar button")
                                    .jqxButton({theme: cargohub.jqxtheme})
                                    /**
                                     * Event: A button in the toolbar is clicked
                                     */
                                    .on('click', function ()
                                    {
                                        var data = $(this).data();
                                        switch (data.op) {
                                            case 'read': // Reads the selected notification in a window
                                                if (selected_ntf)
                                                    open_Notification();
                                                break;

                                            case 'dismiss':
                                                if (selected_ntf) {
                                                    $.post("?action=dismiss_notification&id=" + selected_ntf.uid, function (x) {
                                                        if (x.success) {
                                                            cargohub.alert(x.response, "Success !", "thumb_up.png");
//									$("#tab_notify #grid-notifications").jqxGrid('clearselection');
//									$("#tab_notify #grid-notifications").jqxGrid('updatebounddata');
                                                        } else
                                                            cargohub.alert(x.reason, "Message from server", "information.png");
                                                    });

                                                }
                                                break;

                                                 /*  case 'archive':
                                                if (selected_ntf) {
                                                    $.post("?action=Archive_Notification&id=" + selected_ntf.uid+"&hideit=archived", function (x) {
                                                        if (x.success) {
                                                            cargohub.alert(x.response, "Success !", "thumb_up.png");
//									$("#tab_notify #grid-notifications").jqxGrid('clearselection');
//									$("#tab_notify #grid-notifications").jqxGrid('updatebounddata');
                                                        } else
                                                            cargohub.alert(x.reason, "Message from server", "information.png");
                                                    });

                                                }
                                                break;*/

                                            case 'open-item': // Opens the item - Depends on type ; Only type ISSUE implemented right now.
                                                switch (selected_ntf.type) {
                                                    case 'Issue':
                                                        $.get("?action=read_ntf_related&id=" + selected_ntf.uid, function (x) {
                                                            if (x.success) {
                                                                if (x.item)
                                                                    document.location = "/record/process:" + x.item.track + "#panels";
                                                                else
                                                                    cargohub.alert("Error opening related item");
                                                            } else
                                                                cargohub.alert(x.reason, "Message from server", "information.png");
                                                        });
                                                        break;
                                                    default:
                                                        cargohub.alert("This notification type doesn't have any related item");
                                                }

                                                break;

                                            case 'dismiss-all': // Dismisses all new notifications having as effect the dissapearance of the "You have N notifications" bar. All notifications are still visible in the grid.
                                                if (!confirm("Are you sure you want to dismiss all unread notifications ?"))
                                                    return;
                                                $.post("?action=dismiss_all_notifications", function (x) {
                                                    if (x.success) {
                                                        document.location.reload();
                                                    } else
                                                        cargohub.alert(x.reason, "Message from server", "information.png");
                                                });
                                                break;

                                            case 'link':
                                                document.location = data.link;
                                                break;

//					case 'groups-save': // Edit preferences for the selected group - TODO: (Maybe) - Edit them directly in the grid ?
//						// stringify data_to_save and send it to PHP, where all the changed group preferences will be updated in the DB
//						data_changed = false;
//						$.post("?action=save_groups_settings", {
//							data: JSON.stringify(data_to_save)
//						}, function(x) {
//							if(x.success) {
//								$('div#groups-ops button').jqxButton({disabled: true});
//								data_to_save = {};
//								hide_to = setTimeout(function() {
//									$('div#groups-ops').fadeOut();
//									hide_to = null;
//								}, 600);
//								cargohub.alert("Groups settings have been saved", "Success !", "flag_finish.png");
//							} else {
//								cargohub.alert(x.reason, "Message from server", "information.png");
//							}
//						});
//						break;
//
//					case 'groups-discard':
//						$('div#groups-ops button').jqxButton({disabled: true});
//						hide_to = setTimeout(function() {
//							$('div#groups-ops').fadeOut();
//							hide_to = null;
//						}, 600);
//						$("div#tab_notify div#grid-groups").jqxGrid('updatebounddata'); // reload original data
//						data_changed = false;
//						data_to_save = {};
//						break;
                                        }
                                    });
                        },
                        /**
                         * Sets up the grid containing all user's notifications
                         */
                
                         
                         
                         
                          /**       * Function : Added for the Add and Read Filter
                                     */
                         
                           addfilter_for_new_read = function()
                          
                          { 
                            var filtergroup = new $.jqx.filter();
                            var filter_or_operator = 1;
   						    var filtervalue = 'New';
   						    var filtervalue1 = 'Read';
 						    var filtercondition = 'equal';
   						    var filter1 = filtergroup.createfilter('stringfilter', filtervalue, filtercondition);
    					    var filter2 = filtergroup.createfilter('stringfilter', filtervalue1, filtercondition);
    					    filtergroup.addfilter(filter_or_operator, filter1);
   						    filtergroup.addfilter(filter_or_operator, filter2); 
   						    $("#tab_notify #grid-notifications").jqxGrid('addfilter', 'status', filtergroup); 
    					    $("#tab_notify #grid-notifications").jqxGrid('applyfilters');
     
                          }
                          
                          
                           /**      * Function: Added for the Add and Read Filter
                                     */
                                  
                        setup_Notify_Grid = function ()
                        {
                            var ntf_source = new $.jqx.dataAdapter({
                                url: '?action=get_notifications',
                                datatype: "json",
                                datafields: [
                                    {name: 'xdata_title'},
                                    {name: 'id', type: 'int'},
                                    {name: 'dt', type: 'date'},
                                    {name: 'status'},
                                    {name: 'importance'},
                                    {name: 'type'},
                                    {name: 'subtype'},
//                                    {name: 'title'},
                                    {name: 'xdata_type'},
                                    {name: 'claim_type'},
                                ],
                                id: 'id',
                                root: "data",
                                // server side operations - filtering and sorting:
                                cache: false,
                                filter: function () {
                                    $("#tab_notify #grid-notifications").jqxGrid('updatebounddata', 'filter');
                                },
                                sort: function () {
                                    $("#tab_notify #grid-notifications").jqxGrid('updatebounddata', 'sort');
                                },
                                beforeprocessing: function (source) {
                                    this.totalrecords = source.found_rows;
                                }
                            });

                            $("#tab_notify #grid-notifications").jqxGrid({
                                theme: cargohub.jqxtheme,
                                width: cargohub.fullwidth - 14, autoheight: true,
                                source: ntf_source,
                                 /**
                                     * Event: Added for the Add and Read Filter
                                     */
                                ready: function () {
                                       addfilter_for_new_read()
                                       },
                                       
                                        /**
                                     *  Event: Added for the Add and Read Filter */
                                columns: [
                                    {text: "Record Id", dataField: 'xdata_title',width: 160},
                                    {text: "Time", dataField: 'dt', width: 140, cellsformat: "MM/dd HH:mm:ss", align: "center", cellsalign: "center", groupable: false, filtertype: 'range'},
                                    {text: "Status", dataField: 'status', width: 80, filtertype: "checkedlist",
                                        filteritems: ['New', 'Read', 'Dismissed'],
                                        unselectrow:'Dismissed',
                                        createfilterwidget: function (column, columnElement, widget) {
                                            widget.jqxDropDownList({
                                                dropDownWidth: 110,
                                                autoDropDownHeight: true,
                                                animationType: "fade"
                                            });
                                        }
                                    },
                                    {text: "Importance", dataField: 'importance', width: 90, filtertype: "checkedlist",
                                        filteritems: ['High', 'Med', 'Low'],
                                        createfilterwidget: function (column, columnElement, widget) {
                                            widget.jqxDropDownList({
                                                dropDownWidth: 110,
                                                autoDropDownHeight: true,
                                                animationType: "fade"
                                            });
                                        }
                                    },
                                    {text: "Type", dataField: 'type', width: 80, filtertype: "checkedlist",
                                        filteritems: ['System', 'Issue', 'Task', 'Other'],
                                        createfilterwidget: function (column, columnElement, widget) {
                                            widget.jqxDropDownList({
                                                dropDownWidth: 110,
                                                autoDropDownHeight: true,
                                                animationType: "fade"
                                            });
                                        }
                                    },
                                    {text: "Subtype", dataField: 'subtype', width: 100},
                                {
                                    text: "Record Type",
                                    dataField: 'xdata_type',
//                                    filtertype: "checkedlist",
                                    filtertype: "list",
                                    width: 170,
                                    filteritems: [
                                        'Air Cargo Claim', 'Aircraft Incident', 'Audit control', 'Cargo Damage', 'Cargo Damage FW', 
                                        'Customer Inquiry', 'Dangerous Goods', 'Development Item', 'Documentation', 'General Claim', 
                                        'General Damage', 'Injury Report', 'License monitor', 'MRN Monitor', 'Ocean Cargo Claim', 
                                        'Project', 'Purchase Order', 'Quality Event', 'Quality Manager', 'Road Cargo Claim', 
                                        'Service Recovery', 'Support Ticket', 'Training Record', 'Training Scheduler', 'Transit Monitor', 
                                        'Truck Order', 'ULD Damage Report'
                                    ],
//                                    createfilterwidget: function (column, columnElement, widget) {
//                                        widget.jqxDropDownList({
//                                            autoDropDownHeight: true,
//                                            animationType: "fade",
//                                            source: column.filteritems,
//                                            checkboxes: true
//                                        });
//                                    }
                                },
                                    {text: "Title", dataField: 'claim_type'}, 
                                    
                                ], columnsresize: true, columnsmenu: false,
                                selectionmode: "singlerow", altrows: true,
                                pageable: true, pagesize: 15, pagesizeoptions: [10, 15, 25, 50],
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
                                        if (!selected_ntf) {
                                            $("#tab_notify #item-operations button").jqxButton({disabled: false});
                                            donrey.cancel_Delay('hide_bar_ops');

                                            $('#tab_notify #item-operations').fadeIn();
                                        }
                                        selected_ntf = row;
                                    })
                                    /**
                                     * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
                                     */
                                    .on('filter', function (event)
                                    {
                                        if (selected_ntf) {
                                            $(this).jqxGrid('clearselection');
                                            selected_ntf = null;
                                            $("#tab_notify #item-operations button").jqxButton({disabled: true});

                                            donrey.single_Delay('hide_bar_ops', function () {
                                                $("#tab_notify #item-operations").fadeOut();
                                            }, 500);
                                        }
                                    })
                                    /**
                                     * Event: On doubleclicking, do the same as simple clicking then using the View/Edit button
                                     */
                                    .on('rowdoubleclick', function () {
                                        $('#tab_notify .toolbar button[data-op="read"]').trigger("click");
                                    });

                            // Callback for Application/Heartbeat that will automatically refresh the notifications table when there is an update in the notification count.
                            cargohub.application.heartBeat_Set_Callback('update_notify', function (result) {
                                $("#tab_notify #grid-notifications").jqxGrid('clearselection');
                                selected_ntf = null;

                                $("#tab_notify #item-operations button").jqxButton({disabled: true});
                                donrey.single_Delay('hide_bar_ops', function () {
                                    $("#tab_notify #item-operations").fadeOut();
                                }, 500);
                                $("#tab_notify #grid-notifications").jqxGrid('updatebounddata');
                            });
                        },
                        /**
                         * Sets up the groups display/search grid
                         */
                        setup_Groups_Grid = function ()
                        {
                            // Initialize the user group preferences grid
                            $("div#tab_notify div#grid-groups").jqxGrid({
                                theme: cargohub.jqxtheme,
                                width: cargohub.fullwidth - 14, autoheight: true,
                                source: new $.jqx.dataAdapter({
                                    url: '?action=get_user_groups',
                                    datatype: "json",
                                    datafields: [
                                        {name: 'id', type: 'int', map: 'group>id'},
                                        {name: 'type', type: 'string', map: 'group>type'},
                                        {name: 'name', type: 'string', map: 'group>name'},
                                        {name: 'pref_ntf_new', map: 'preferences>ntf_new', type: 'bool'},
                                        {name: 'pref_ntf_trans', map: 'preferences>ntf_trans', type: 'bool'},
                                        {name: 'pref_ntf_share', map: 'preferences>ntf_share', type: 'bool'},
                                        {name: 'pref_ntf_clos', map: 'preferences>ntf_clos', type: 'bool'},
                                        {name: 'pref_hidden', map: 'preferences>hidden', 'type': 'bool'}
                                    ],
                                    id: 'id'
                                }),
                                columns: [
                                    {text: "Group Type", dataField: 'type', width: 150, filtertype: "checkedlist",
                                        createfilterwidget: function (column, columnElement, widget) {
                                            widget.jqxDropDownList({
                                                dropDownWidth: 150,
                                                autoDropDownHeight: true,
                                                animationType: "fade"
                                            });
                                        },
                                        editable: false
                                    },
                                    {text: "Name", dataField: 'name', editable: false},
                                    {text: "Notify new", dataField: 'pref_ntf_new', align: 'center', columntype: 'checkbox', width: 90, editable: true, filtertype: 'bool'},
                                    {text: "Issue transfer", dataField: 'pref_ntf_trans', align: 'center', columntype: 'checkbox', width: 100, editable: true, filtertype: 'bool'},
                                    {text: "Issue sharing", dataField: 'pref_ntf_share', align: 'center', columntype: 'checkbox', width: 100, editable: true, filtertype: 'bool'},
                                    {text: "Notify close", dataField: 'pref_ntf_clos', align: 'center', columntype: 'checkbox', width: 90, editable: true, filtertype: 'bool'},
                                    {text: "Hide issues", dataField: 'pref_hidden', align: 'center', columntype: 'checkbox', width: 90, editable: true, filtertype: 'bool'}
                                ],
                                columnsmenu: false, sortable: true, filterable: true, showfilterrow: true, pageable: true,
                                altrows: true, selectionmode: "singlerow", editable: true
                            })
                                    /**
                                     * Event: One of the checkboxes have been clicked, store all the changed values so they can be saved when the user choses so.
                                     * Also display the save button on the first checkbox click
                                     */
                                    .on('cellendedit', function (event)
                                    {
                                        if (!data_to_save[selected_group_id])
                                            data_to_save[selected_group_id] = {};

                                        data_to_save[selected_group_id][event.args.datafield] = event.args.value;

                                        $('div#groups-ops button').jqxButton({disabled: false});
                                        if (!data_changed) {
                                            clearTimeout(hide_to);
                                            hide_to = null;
                                            $('div#groups-ops').fadeIn();
                                        }
                                        data_changed = true;
                                    })
                                    /**
                                     * Event: When a row is selected, the group name is displayed on the toolbar, beside the edit button, and all of it is show with fade in
                                     */
                                    .on('rowselect', function (event)
                                    {
                                        var row = event.args.row;
                                        $('div#groups-ops button').jqxButton({disabled: false});
                                        selected_group_id = row.id;
                                    })
                                    /**
                                     * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
                                     */
                                    .on('filter', function (event)
                                    {
                                        $(this).jqxGrid('clearselection');
                                        selected_group_id = null;
                                    });
                        };

                setup_Notify_Toolbar();
                setup_Notify_Grid();
//		setup_Groups_Grid();

                // Info Explander
                $("div#tab_notify div.info-expander").jqxExpander({
                    theme: cargohub.jqxtheme,
                    expanded: false
                });
            },
            setup_Tab_History = function () {
                if (self.config.dataSources.hasOwnProperty("recent")) {
                    var detailsPri = "";

                    var recentRecord = "";
                    $("#rt-list11").css("float", "right");
                    for (c = 0; c < self.config.dataSources.recent.length; c++) {
                        if (self.config.dataSources.recent[c].pri == "" || self.config.dataSources.recent[c].pri == null) {
                            detailsPri = "	 ";
                        } else {
                            detailsPri = "(" + self.config.dataSources.recent[c].pri + ")";
                        }
                        recentRecord += "<tr id='" + self.config.dataSources.recent[c].rt_key + "'>";
                        recentRecord += "<td style='width: 38%;'><img src='/img/icons16/ist/" + self.config.dataSources.recent[c].rt_key + ".png' alt='" + self.config.dataSources.recent[c].rt_key + "' style='padding-right: 6px;'/>" + self.config.dataSources.recent[c].rt_name + "</td>";
                        recentRecord += "<td style='width: 25%;'><a href='/record/process:" + self.config.dataSources.recent[c].track + "#panels' id='" + self.config.dataSources.recent[c].rt_key + "' style='font-size: 12px;'>" + self.config.dataSources.recent[c].track + "</a></td>";
                        recentRecord += "<td>" + detailsPri + "</td>";
                        recentRecord += "</tr>";
//                        recentRecord += "<tr><td colspan='2'></td></tr>";//IWI new dashboard design 11082026
                    }
                    $("#recent_record").append(recentRecord);
                } else {
                    $("#recentRecord_Div").html("");
                    $("#rt-list11").css({"float": "left", "padding-left": "27px"});
                }
            },
            setup_Tab_Documentation = function () {
//        console.log('zd',self.config.dataSources.hasOwnProperty("recent"));
//                            console.log(self.config.dataSources.recent);
                if (self.config.dataSources.hasOwnProperty("recent")) {


                    // for document tab section start 
                    console.log('for document document section');

//            getDocumentationDocList(self.config.dataSources.recent);
                    getDocumentationDocList('department');

                    // for document tab section end  





                } else {
                    $("#publish_documents_Div").html("");
                    $("#rt-list11").css({"float": "left", "padding-left": "27px"});
                }
            },
            setup_Tab_Chart = function () {


                // $('#date-pickr input').click(function(){
                //     alert();
                // });





                Chart.register(ChartDataLabels);




                // round corners


                Chart.defaults.set('plugins.datalabels', {
                    color: '#FE777B'
                });
                $(".spinner_overlay").show();
                $.post("?action=Load_Record_Chart", {
                    org_id: 1,

                }, function (x) {

                    var formalClaimTypeByOrigin = x.formalClaimTypeByOrigin;
                    var formalClaimTypeByDestination = x.formalClaimTypeByDestination;
                    var preliminaryClaimTypeByOrigin = x.preliminaryClaimTypeByOrigin;
                    var preliminaryClaimTypeByDestination = x.preliminaryClaimTypeByDestination;

                    var destClaimAspectsData = x.destClaimAspectsData;
                    var orgClaimAspectsData = x.orgClaimAspectsData;
                    var claimAspectsCountData = x.claimAspectsCountData;

                    var claimActivityData = x.claimActivityData;
                    var serviceLevelData = x.serviceLevelData;

                    var originFinancialsClaimAmount = x.originFinancialsClaimAmount;
                    var destinationFinancialsClaimAmount = x.destinationFinancialsClaimAmount;

                    var irregularityDestData = x.irregularityDestData;
                    var irregularityOriginData = x.irregularityOriginData;

                    var conclusionDestiData = x.conclusionDestiData;
                    var conclusionOriginData = x.conclusionOriginData;



                    if (!x.success)
                        return cargohub.alert(x.reason, "Message from server");



                    // Top 10 Preliminary  Claim type by Origin start
                    var ctx = document.getElementById('myChart');


                    var myChart = new Chart(ctx, {
                        type: 'doughnut',
                        plugins: [ChartDataLabels],
                        data: {
                            // labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
                            labels: preliminaryClaimTypeByOrigin.x_axis,
                            datasets: [{
                                    label: 'MIA',
                                    // data: [60, 13, 10, 5, 2, 3],
                                    data: preliminaryClaimTypeByOrigin.y_axis,
                                    borderWidth: 0,
                                    backgroundColor: ['#49cdeb', '#5ed0c2', '#fe7096', '#ffba96', '#a49bf8', '#9baef8', '#9bf8d2', '#cbf89b', '#f8dc9b', '#f8c29b', ]


                                }],
                        },
                        options: {
                            plugins: {
                                legend: {
                                    position: 'bottom',

                                },
                                datalabels: {
                                    color: '#000',
                                    textAlign: 'center',
                                    formatter: function (value, ctx) {
                                        var index = ctx.dataIndex;
                                        var label = ctx.chart.data.labels[index];
                                        return  value;
                                    }
                                }
                            },
                            tooltips: {
                                enabled: true,
                            },

                            scales: {
                                y: {
                                    beginAtZero: true,
                                    display: false,
                                }
                            }
                        }
                    });
                    // Top 10 Preliminary Claim type by Origin start

                    // Top 10 Preliminary Claim type by Destination start


                    const ctx2 = document.getElementById('myChart2');
                    new Chart(ctx2, {
                        type: 'doughnut',
                        data: {
                            labels: preliminaryClaimTypeByDestination.x_axis,
                            // labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
                            datasets: [{
                                    label: 'MIA',
                                    data: preliminaryClaimTypeByDestination.y_axis,
                                    // data: [27, 18, 10, 7, 5, 2],
                                    borderWidth: 0,
                                    backgroundColor: ['#49cdeb', '#5ed0c2', '#fe7096', '#ffba96', '#a49bf8', '#9baef8', '#9bf8d2', '#cbf89b', '#f8dc9b', '#f8c29b', ]
                                }]
                        },
                        options: {
                            plugins: {
                                legend: {
                                    position: 'bottom',

                                },
                                datalabels: {
                                    color: '#000',
                                    textAlign: 'center',
                                    formatter: function (value, ctx) {
                                        var index = ctx.dataIndex;
                                        var label = ctx.chart.data.labels[index];
                                        return  value;
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    display: false,
                                }
                            }
                        }
                    });
                    // Top 10 Preliminary Claim type by Destination start


                    // Top 10 Formal Claim type by Origin start
                    const ctx3 = document.getElementById('myChart3');
                    new Chart(ctx3, {
                        type: 'doughnut',
                        data: {
                            // labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
                            labels: formalClaimTypeByOrigin.x_axis,
                            datasets: [{
                                    label: 'MIA',
                                    // data: [68, 10, 13, 5, 2, 3],
                                    data: formalClaimTypeByOrigin.y_axis,
                                    borderWidth: 0,
                                    backgroundColor: ['#49cdeb', '#5ed0c2', '#fe7096', '#ffba96', '#a49bf8', '#9baef8', '#9bf8d2', '#cbf89b', '#f8dc9b', '#f8c29b', ]
                                }]
                        },
                        options: {
                            plugins: {
                                legend: {
                                    position: 'bottom',

                                },
                                datalabels: {
                                    color: '#000',
                                    textAlign: 'center',
                                    formatter: function (value, ctx) {
                                        var index = ctx.dataIndex;
                                        var label = ctx.chart.data.labels[index];
                                        return  value;
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    display: false,
                                }
                            }
                        }
                    });
                    // Top 10 Formal Claim type by Origin end

                    // Top 10 Formal Claim type by Destination start
                    const ctx4 = document.getElementById('myChart4');

                    new Chart(ctx4, {
                        type: 'doughnut',
                        data: {
                            // labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
                            labels: formalClaimTypeByDestination.x_axis,
                            datasets: [{
                                    label: 'MIA',
                                    // data: [21, 15, 13, 8, 5, 2],
                                    data: formalClaimTypeByDestination.y_axis,
                                    borderWidth: 0,
                                    backgroundColor: ['#49cdeb', '#5ed0c2', '#fe7096', '#ffba96', '#a49bf8', '#9baef8', '#9bf8d2', '#cbf89b', '#f8dc9b', '#f8c29b', ]
                                }]
                        },
                        options: {

                            plugins: {
                                legend: {
                                    position: 'bottom',

                                },
                                datalabels: {
                                    color: '#000',
                                    textAlign: 'center',
                                    formatter: function (value, ctx) {
                                        var index = ctx.dataIndex;
                                        var label = ctx.chart.data.labels[index];
                                        return  value;
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    display: false,
                                }
                            }
                        }
                    });
                    // Top 10 Formal Claim type by Destination end


                    //Top 10 Claim aspects Destination start
                    const ctx7 = document.getElementById('myChart7');
                    new Chart(ctx7, {
                        type: 'bar',
                        data: {
                            labels: destClaimAspectsData.x_axis,
                            datasets: [{
                                    label: '',
                                    data: destClaimAspectsData.y_axis,
                                    borderWidth: 1,
                                    backgroundColor: '#ffbb00',
                                    borderColor: '#ffbb00',
                                    borderRadius: 8,
                                    barThickness: 8,

                                }]
                        },
                        options: {
                            plugins: {
                                datalabels: {
                                    color: '#000',
                                    textAlign: 'top',
                                    anchor: 'end',
                                    align: 'end',
                                    formatter: function (value, ctx) {
                                        var index = ctx.dataIndex;
                                        var label = ctx.chart.data.labels[index];
                                        return  value;
                                    }
                                },
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    ticks: {
                                        font: {
                                            size: 10,
                                            family: 'Roboto'
                                        }
                                    },
                                    title: {
                                        display: true,
                                        text: 'Claim aspects by station',
                                        color: '#595959',
                                        font: {
                                            family: 'Roboto',
                                            size: '12px',
                                            weight: '500',
                                        },
                                    }
                                },
                                y: {
                                    beginAtZero: true,

                                    title: {
                                        display: true,
                                        text: 'Number of claims',
                                        color: '#595959',
                                        font: {
                                            family: 'Roboto',
                                            size: '12px',
                                            weight: '500',
                                        },
                                    }
                                }
                            }
                        }
                    });
                    //Top 10 Claim aspects Destination end


                    //Top 10 Claim aspects Origin start
                    const ctx6 = document.getElementById('myChart6');
                    new Chart(ctx6, {
                        type: 'bar',
                        data: {
                            labels: orgClaimAspectsData.x_axis,
                            // labels: ['MIA-damage', 'MIA-Total loss', 'MIA-Partial loss', 'SJU-Damage', 'MIA-Delay', 'ATL-Damage', 'SDQ-Partial loss', 'JFK-Damage', 'MIA-Damage, Total loss', 'SDQ-Damage', ],
                            datasets: [{
                                    label: '',
                                    data: orgClaimAspectsData.y_axis,
                                    // data: [40, 35, 28, 20, 11, 10, 9, 8, 6, 6],
                                    borderWidth: 1,
                                    backgroundColor: '#4274be',
                                    borderColor: '#4274be',
                                    borderRadius: 8,
                                    barThickness: 8,

                                }]
                        },
                        options: {
                            plugins: {
                                datalabels: {
                                    color: '#000',
                                    textAlign: 'top',
                                    anchor: 'end',
                                    align: 'end',
                                    formatter: function (value, ctx) {
                                        var index = ctx.dataIndex;
                                        var label = ctx.chart.data.labels[index];
                                        return  value;
                                    }
                                },
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    ticks: {
                                        font: {
                                            size: 10,
                                            family: 'Roboto'
                                        }
                                    },

                                    title: {
                                        display: true,
                                        text: 'Claim aspects by station',
                                        color: '#595959',
                                        font: {
                                            family: 'Roboto',
                                            size: '12px',
                                            // style: 'normal',
                                            // lineHeight: 1.2
                                            weight: '500',
                                            color: '#99999',
                                        },
                                    }
                                },
                                y: {
                                    beginAtZero: true,

                                    title: {
                                        display: true,
                                        text: 'Number of claims',
                                        color: '#595959',
                                        font: {
                                            // family: 'Calibri,Geneva,Arial,Helvetica,sans-serif',
                                            family: 'Roboto',
                                            size: '12px',
                                            weight: '500',
                                            color: '#99999',
                                        },
                                    }
                                }
                            }



                        }
                    });
                    //Top 10 Claim aspects Origin end


                    // Top 20 Claim aspects Count start
                    const ctx5 = document.getElementById('myChart5');
                    new Chart(ctx5, {
                        type: 'line',
                        data: {
                            labels: claimAspectsCountData.x_axis,
                            // labels: ['Damage', 'Total Loss', 'Partial Loss', 'Delay', 'Damage, Total Loss', 'Damage, Partial Loss','Pilferage','Damage,Total Loss,Partial Loss','Total Loss, Delay','Delay,Partial Loss','Damage, Partial Loss, Pilferage','Damage,Total Loss, Delay','Partial Loss, Pilferage' ],
                            datasets: [{
                                    label: "",
                                    data: claimAspectsCountData.y_axis,
                                    // data: [119, 69, 45, 23, 10, 5,3,2,2,1,1,1,1],
                                    borderWidth: 1,
                                    backgroundColor: '#4274be',
                                    borderColor: '#4274be',
                                    // pointRedius:1,
                                }]
                        },
                        options: {
                            elements: {
                                point: {
                                    radius: 2,
                                    hoverRadius: 4,
                                }
                            },
                            tension: 0.5,
                            plugins: {
                                datalabels: {
                                    color: '#000',
                                    textAlign: 'top',
                                    anchor: 'end',
                                    align: 'end',
                                    formatter: function (value, ctx) {
                                        var index = ctx.dataIndex;
                                        var label = ctx.chart.data.labels[index];
                                        return  value;
                                    }
                                },
                                legend: {
                                    display: false
                                },
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    ticks: {
                                        font: {
                                            size: 10,
                                            family: 'Roboto'
                                        }
                                    },
                                    title: {
                                        display: true,
                                        text: 'Claim aspects',
                                        color: '#595959',
                                        font: {
                                            weight: '500',
                                            size: 12,
                                            family: 'Roboto'
                                        },
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Number of claims',
                                        color: '#595959',
                                        font: {
                                            weight: '500',
                                            color: '#000',
                                            size: 12,
                                            family: 'Roboto'
                                        },
                                    }
                                }
                            }
                        }
                    });
                    // Top 20 Claim aspects Count end



                    // Top 10 Claim Activity Start
                    const ctx15 = document.getElementById('myChart15');

                    new Chart(ctx15, {
                        type: 'bar',
                        data: {
                            labels: claimActivityData.x_axis,
                            // labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
                            datasets: [{
                                    label: '',
                                    data: claimActivityData.y_axis,
                                    // data: [12, 19, 3, 5, 2, 3],
                                    borderWidth: 1,
                                    backgroundColor: '#4274be',
                                    borderColor: '#4274be',
                                    borderRadius: 8,
                                    barThickness: 30,
                                }]
                        },
                        options: {
                            plugins: {
                                datalabels: {
                                    color: '#000',
                                    textAlign: 'top',
                                    anchor: 'end',
                                    align: 'end',
                                    formatter: function (value, ctx) {
                                        var index = ctx.dataIndex;
                                        var label = ctx.chart.data.labels[index];
                                        return  value;
                                    }
                                },
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    title: {
                                        display: false,
                                        text: 'Claim Activity',
                                        color: '#000000',
                                        font: {
                                            weight: '600',
                                        },
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: false,
                                        text: 'Number of Claim Activity',
                                        color: '#000000',
                                        font: {
                                            weight: '600',
                                            color: '#000',
                                        },
                                    }
                                }
                            }
                        }
                    });
                    // Top 10 Claim Activity End



                    // Top 10 Service Level start
                    const ctx14 = document.getElementById('myChart14');
                    new Chart(ctx14, {
                        type: 'bar',
                        data: {
                            labels: serviceLevelData.x_axis,
                            // labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
                            datasets: [{
                                    label: '',
                                    axis: 'y',
                                    data: serviceLevelData.y_axis,
                                    // data: [12, 19, 3, 5, 2, 3],
                                    borderWidth: 1,
                                    backgroundColor: '#ffbb00',
                                    borderColor: '#ffbb00',
                                    borderRadius: 8,
                                    barThickness: 10, // number (pixels) or 'flex'

                                }]
                        },
                        options: {
                            indexAxis: 'y',
                            plugins: {
                                datalabels: {
                                    color: '#000',
                                    textAlign: 'top',
                                    anchor: 'end',
                                    align: 'end',
                                    formatter: function (value, ctx) {
                                        var index = ctx.dataIndex;
                                        var label = ctx.chart.data.labels[index];
                                        return  value;
                                    }
                                },
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    max: 2.0, // Dynamic (extra + 2 ) 
                                    title: {
                                        display: false,
                                        text: 'Service Level',
                                        color: '#000000',
                                        font: {
                                            weight: '600',
                                        },
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: false,
                                        text: 'Number of Service Level',
                                        color: '#000000',
                                        font: {
                                            weight: '600',
                                            // color: '#999',
                                            // lineHeight: 1.2
                                        },
                                    }
                                }
                            }
                        }
                    });
                    // Top 10 Service Level end

                    // Top 10 Origin Financials Start
                    const ctx12 = document.getElementById('myChart12');

                    new Chart(ctx12, {
                        type: 'bar',
                        data: {
                            // labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
                            labels: originFinancialsClaimAmount.x_axis,
                            datasets: [{
                                    label: ['Claim Amount'],
                                    // data: [12, 19, 3, 5, 2, 3],
                                    data: originFinancialsClaimAmount.y_axis,
                                    borderWidth: 1,
                                    backgroundColor: ['#4274be', ],
                                    borderColor: ['#4274be', ],
                                    borderRadius: 8,
                                    barThickness: 24, // number (pixels) or 'flex'
                                },
                                {
                                    label: ['Paid Amount'],
                                    // data: [0, 3, 1, 2, ],
                                    data: originFinancialsClaimAmount.z_axis,
                                    borderWidth: 1,
                                    backgroundColor: ['#ffbb00', ],
                                    borderColor: ['#ffbb00', ],
                                    borderRadius: 8,
                                    barThickness: 24, // number (pixels) or 'flex'
                                }
                            ]
                        },
                        options: {
                            plugins: {
                                datalabels: {
                                    color: '#000',
                                    textAlign: 'top',
                                    anchor: 'end',
                                    align: 'end',
                                    formatter: function (value, ctx) {
                                        var index = ctx.dataIndex;
                                        var label = ctx.chart.data.labels[index];
                                        return  value;
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    title: {
                                        display: false,
                                        text: 'Station',
                                        color: '#000000',
                                        font: {
                                            weight: '600',
                                        },
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: false,
                                        text: 'Amount',
                                        color: '#000000',
                                        font: {
                                            weight: '600',
                                            color: '#000',
                                        },
                                    }
                                }
                            }
                        },

                    });
                    // Top 10 Origin Financials End


                    // Top 10 Destination Financials start 
                    const ctx13 = document.getElementById('myChart13');
                    new Chart(ctx13, {
                        type: 'bar',
                        data: {
                            labels: destinationFinancialsClaimAmount.x_axis,
                            // labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
                            datasets: [{
                                    label: 'Claim Amount',
                                    // data: [12, 19, 3, 5, 2, 3],
                                    data: destinationFinancialsClaimAmount.y_axis,
                                    borderWidth: 1,
                                    backgroundColor: ['#4274be', ],
                                    borderColor: ['#4274be', ],
                                    borderRadius: 8,
                                    barThickness: 24, // number (pixels) or 'flex'
                                },
                                {
                                    label: ['Paid Amount'],
                                    // data: [0, 3, 1, 2, ],
                                    data: destinationFinancialsClaimAmount.z_axis,
                                    borderWidth: 1,
                                    backgroundColor: ['#ffbb00', ],
                                    borderColor: ['#ffbb00', ],
                                    borderRadius: 8,
                                    barThickness: 24, // number (pixels) or 'flex'
                                }
                            ]
                        },
                        options: {
                            plugins: {
                                datalabels: {
                                    color: '#000',
                                    textAlign: 'top',
                                    anchor: 'end',
                                    align: 'end',
                                    formatter: function (value, ctx) {
                                        var index = ctx.dataIndex;
                                        var label = ctx.chart.data.labels[index];
                                        return  value;
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    title: {
                                        display: false,
                                        text: 'Station',
                                        color: '#000000',
                                        font: {
                                            weight: '600',
                                        },
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: false,
                                        text: 'Amount',
                                        color: '#000000',
                                        font: {
                                            weight: '600',
                                            color: '#000',
                                        },
                                    }
                                }
                            }
                        }
                    });
                    // Top 10 Destination Financials end


                    // Top 10 irregularity by origin start
                    const ctx8 = document.getElementById('myChart8');
                    new Chart(ctx8, {
                        type: 'bar',
                        data: {
                            labels: irregularityOriginData.x_axis,
                            datasets: [{
                                    label: 'MIA',
                                    data: irregularityOriginData.y_axis,
                                    borderWidth: 1,
                                    backgroundColor: '#4274be',
                                    borderColor: '#4274be',
                                    borderRadius: 8,
                                    barThickness: 10,
                                }]
                        },
                        options: {
                            plugins: {
                                datalabels: {
                                    color: '#000',
                                    textAlign: 'top',
                                    anchor: 'end',
                                    align: 'end',
                                    formatter: function (value, ctx) {
                                        var index = ctx.dataIndex;
                                        var label = ctx.chart.data.labels[index];
                                        return  value;
                                    }
                                },
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    max: 2.0,
                                    title: {
                                        display: false,
                                        text: '',
                                        color: '#000000',
                                        font: {
                                            weight: '600',
                                        },
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: false,
                                        text: 'Count',
                                        color: '#000000',
                                        font: {
                                            weight: '600',
                                            color: '#000',
                                        },
                                    }
                                }
                            }
                        }
                    });
                    //Top 10 irregularity by origin end

                    // Top 10 irregularity by destination start
                    const ctx9 = document.getElementById('myChart9');

                    new Chart(ctx9, {
                        type: 'bar',
                        data: {
                            labels: irregularityDestData.x_axis,
                            datasets: [{
                                    label: '',
                                    data: irregularityDestData.y_axis,
                                    borderWidth: 1,
                                    backgroundColor: '#ffbb00',
                                    borderColor: '#ffbb00',
                                    borderRadius: 8,
                                    barThickness: 10,
                                }]
                        },
                        options: {
                            plugins: {
                                datalabels: {
                                    color: '#000',
                                    textAlign: 'top',
                                    anchor: 'end',
                                    align: 'end',
                                    formatter: function (value, ctx) {
                                        var index = ctx.dataIndex;
                                        var label = ctx.chart.data.labels[index];
                                        return  value;
                                    }
                                },
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: '',
                                        color: '#000000',
                                        font: {
                                            weight: '600',
                                        },
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    max: 2.0,
                                    title: {
                                        display: true,
                                        text: 'Count',
                                        color: '#000000',
                                        font: {
                                            weight: '600',
                                            color: '#000',
                                        },
                                    }
                                }
                            }
                        }
                    });
                    // Top 10 irregularity by destination end



                    // Top-10 conclusion by origin
                    const ctx10 = document.getElementById('myChart10');

                    new Chart(ctx10, {
                        type: 'line',
                        data: {
                            labels: conclusionOriginData.x_axis,
                            datasets: [{
                                    label: '',
                                    data: conclusionOriginData.y_axis,
                                    borderWidth: 1,
                                    backgroundColor: '#ed7d31',
                                    borderColor: '#ed7d31',
                                }]
                        },
                        options: {
                            plugins: {
                                datalabels: {
                                    color: '#000',
                                    textAlign: 'top',
                                    anchor: 'end',
                                    align: 'end',
                                    formatter: function (value, ctx) {
                                        var index = ctx.dataIndex;
                                        var label = ctx.chart.data.labels[index];
                                        return  value;
                                    }
                                },
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: '',
                                        color: '#000000',
                                        font: {
                                            weight: '600',
                                            color: '#99999',
                                        },
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Count',
                                        color: '#000000',
                                        font: {
                                            weight: '600',
                                            color: '#99999',
                                        },
                                    }
                                }
                            }
                        }
                    });

                    // Top-10 conclusion by destination
                    const ctx11 = document.getElementById('myChart11');

                    new Chart(ctx11, {
                        type: 'line',
                        data: {
                            labels: conclusionDestiData.x_axis,
                            datasets: [{
                                    label: '',
                                    data: conclusionDestiData.y_axis,
                                    borderWidth: 1,
                                    backgroundColor: '#ed7d31',
                                    borderColor: '#ed7d31',
                                }]
                        },
                        options: {
                            plugins: {
                                datalabels: {
                                    color: '#000',
                                    textAlign: 'top',
                                    anchor: 'end',
                                    align: 'end',
                                    formatter: function (value, ctx) {
                                        var index = ctx.dataIndex;
                                        var label = ctx.chart.data.labels[index];
                                        return  value;
                                    }
                                },
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: '',
                                        color: '#000000',
                                        font: {
                                            weight: '600',
                                        },
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Count',
                                        color: '#000000',
                                        font: {
                                            weight: '600',
                                            color: '#000',
                                        },
                                    }
                                }
                            }
                        }
                    });





                    setTimeout(function () {
                        $(".spinner_overlay").show();
                    }, 2000);


                });




                /*
                 const ctx = document.getElementById('myChart');
                 
                 new Chart(ctx, {
                 type: 'pie',
                 data: {
                 labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
                 datasets: [{
                 label: 'MIA',
                 data: [60, 13, 10, 5, 2, 3],
                 borderWidth: 1
                 }]
                 },
                 options: {
                 plugins: {
                 datalabels: {
                 color: 'white',
                 textAlign: 'center',
                 formatter: function(value, ctx) {
                 var index = ctx.dataIndex;
                 var label = ctx.chart.data.labels[index];
                 return label + '\n' + value;
                 }
                 }
                 },
                 
                 scales: {
                 y: {
                 beginAtZero: true
                 }
                 }
                 }
                 });
                 */
                // console.log("formalClaimTypeByOrigin => ",formalClaimTypeByOrigin.x_axis);
            }



    /**
     * Sets up the tabs visible on the user overview module (also known as "dashboard")
     * TODO: Replace this with HashNav component when possible.
     */
    setup_Tabs = function ()
    {
        var disable_onhashchange = true, loaded_tabs = {}, // Contains the list of tabs that have already been loaded and initialized
                selected_tab_idx = 0, selected_tab = 'tab_dashboard';

        // discover if a subtab is specified in the URL ( to jump directly to it )
        c = 0;
        $('#tabs-container > div').each(function () {
            if (document.location.hash.substr(1) == $(this).prop('id').substr(4)) {
                selected_tab = $(this).prop('id');
                selected_tab_idx = c;
            }
            c++;
        });

        $('#tabs-container').jqxTabs({
            theme: cargohub.jqxtheme,
            width: cargohub.fullwidth,
            position: 'top',
            selectionTracker: true, selectedItem: selected_tab_idx,
            keyboardNavigation: false
        })
                /**
                 * Event: When selecting a tab for the first time, it is loaded from the server (except for the first one - tab_overview, which is initially loaded with the page)
                 */
                .on('selected', function (event, auto_trigger) {
                    cargohub.application.reset_Session_Timer();
                    disable_onhashchange = true;

                    if (!auto_trigger)
                        selected_tab = $('#tabs-container').jqxTabs('getContentAt', event.args.item).attr('id');
                    document.location.hash = selected_tab.substr(4);

                    if (!loaded_tabs[selected_tab]) {
                        // doesn't yet exist, load it's HTML and initialize
                        $.get("?action=load_tab_html&tab=" + selected_tab, function (tab_contents, textStatus, jqXHR)
                        {
                            $('div#' + selected_tab).html(tab_contents);
                            // run init function for this tab when loading is complete

                            // Load extra data for this tab, if it exists, and store it in the local object
                            donrey.read_JSON_Headers(jqXHR, self.config);


                            switch (selected_tab) {
                                case 'tab_dashboard':
                                    setup_Tab_Dashboard();
                                    break;
                                case 'tab_documents':
                                    setup_Tab_Documentation();
                                    break;
                                case 'tab_chart':
                                    setup_Tab_Chart();
                                    break;
                                case 'tab_modules':
                                    // Load the User_Modules dynamically
                                    donrey.load_Modules("user/_recordtypes", function () {
                                        $('div#' + selected_tab).html(tab_contents);
                                        cargohub.user_recordtypes.config = self.config.tab_modules;
                                        cargohub.user_recordtypes.init();
                                    }, "?v=" + cargohub.version);
                                    break;

                                case 'tab_account':
                                    setup_Tab_Account();
                                    break;

                                case 'tab_vcard':
                                    if (self.config.tab_vcard)
                                        setup_Tab_VCard();
                                    break;

                                case 'tab_notify':
                                    setup_Tab_Notify();
                                    break;
                                case 'tab_history':
                                    setup_Tab_History();
                                    break;
                            }

                            // and finally, set the tab as initialized
                            loaded_tabs[selected_tab] = true;
                        });

                    }

                    setTimeout(function () {
                        disable_onhashchange = false;
                    }, 50);
                });

        // initialize the first selected tab by triggering the "selected" event
        $('#tabs-container').trigger('selected', true);

        // hash-based navigation inside tabs themselves - Visually select tab when hash changes by typing in the URL or using back/forward (not by clicking on the tab itself)
        window.onhashchange = function (event) {
            // hash changes as well when the user is selecting the tab manually, in those cases there's no need to find and reselect the tab again !
            if (disable_onhashchange)
                return;

            c = 0;
            $('#tabs-container div.jqx-tabs-content > div').each(function () {
                if (location.hash.substr(1) == $(this).prop('id').substr(4))
                    selected_tab_idx = c;
                c++;
            });
            $('#tabs-container').jqxTabs('select', selected_tab_idx);
        };
    };
    setup_Updates = function () {
        
        
//        hide recent updates part on cockpit start 
        
//        $.post("/public/access?action=recent_updates", {
//            day: 700,
//            limit: 1,
//        }, function (x) {
//            if (x.success) {
//                if (x.result.length > 0) {
//                    var html = '';
//                    html += '<div id="rec-updates" style="margin: 22px;width:1080px;"><h4><img src="/img/icons16/system_updated.png" alt="(up)" style="float: left; margin-left: 16px;"><a href="javascript:void(0);" onclick="goToSupport();">Recent Updates</a></h4>';
//                    $.each(x.result, function (index, value) {
//                        const only_date = value.dt.split(/ (.*)/);
//                        html += '<h3><a href="javascript:void(0);" onclick="goToSupport();"><span style="color: red;">' + only_date[0] + '</span> ' + value.title + '</a></h3>';
//                        html += '<hr style="margin-bottom: 20px;">';
//                    });
//                    html += '</div>';
//                    $('#rt-list11').prepend(html);
//                    $('#rt-list11').append('<style>.rec-content p img{max-width: 100%; height: auto;}</style>');
//                }
//            }
//        }).fail(cargohub.its.http_fail);
//hide recent updates part on cockpit end


        // var html = '';
        // html += '<div id="rec-publications" style="margin: 22px;width:1080px;"><h4><img src="/img/icons16/support.png" alt="(up)" style="float: left; margin-left: 16px;"><a href="javascript:void(0);" onclick="goToPublications();">Publications</a></h4>';

        // $('#rt-list11').prepend(html);




    };
    
    
    /*
    init_RT_List = function () {
        setTimeout(setup_Updates, 500);
        var indexRowNumber = 4;
        var tableMargin = "margin-top: 20px;padding-left: 22px;";
        
        var table = "<table class='dashboardTable' border='0' width='0%'  cellpadding='0px;'  cellspacing='0px;' style='" + tableMargin + "'>";
        table += "<tr>";
        table +=
                "<tr style='display:flex;width: 1112px;justify-content: space-between;align-items: center;'>" +
                "<td style='height: 46px;width: 313px;line-height: 21px;display: flex;margin-bottom: 10px;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;margin-bottom: 5px;margin-right: 10px;'>" +
                "<p style='color: black;font-size: 14px; font-weight: 500;font-weight: bold;margin-left: 15px;margin-left: 40px;'>Active Records</p>" +
                "</td>" +
                "<td style='height: 46px;margin-right: 10px;line-height:1px;display: flex;margin-bottom: 10px;width:218px ;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;margin-bottom: 5px;'>" +
                "<p style='font-size: 14px;color:black;font-weight: 500;font-weight: bold;margin-top: 12px;margin-left: 40px;'>ORG</p>" +
                "</td>" +
                "<td style='height: 46px;margin-right: 10px;line-height:1px;display: flex;margin-bottom: 10px;width:218px ;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;margin-bottom: 5px;'>" +
                "<p style='font-size: 14px;color:black;font-weight: 500;font-weight: bold;margin-top: 12px;margin-left: 40px;'>DEP</p>" +
                "</td>" +
                "<td style='height: 46px;margin-right: 10px;line-height:1px;display: flex;margin-bottom: 10px;width:200px ;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;margin-bottom: 5px;'>" +
                "<p style='font-size: 14px;color:black;font-weight: 500;font-weight: bold;margin-top: 12px;margin-left: 20px;'>My assigned</p>" +
                "</td>" +
                "</td>" +
                "<td style='height: 46px;margin-right: 10px;line-height:1px;display: flex;margin-bottom: 10px;width:281px ;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;margin-bottom: 5px;'>" +
                "<p style='font-size: 14px;color:black;font-weight: 500;font-weight: bold;margin-top: 12px;margin-left: 20px;'>My Tasks | OverDue</p>" +
                "</td>" +
                "<td style='height: 46px;margin-right: 10px;line-height:1px;display: flex;margin-bottom: 10px;width:134px ;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;margin-bottom: 5px;'>" +
                "<p style='font-size: 14px;color:black;font-weight: 500;font-weight: bold;margin-top: 12px;margin-left: 20px;'>Charts</p>" +
                "</td>" +
                "</tr>";
        for (c = 0; c < self.config.tab_dashboard.rtlist_dashboard.length; c++) {
            if (c % indexRowNumber == 0)
                table += "</tr><tr>";

            if (self.config.tab_dashboard.rtlist_dashboard[c].yep == true) {
                var actissCount = 0;
                if (self.config.tab_dashboard.rtlist_dashboard[c].isscount != null) {
                    actissCount = self.config.tab_dashboard.rtlist_dashboard[c].isscount;
                }
                var myAssCount = "";
                if (self.config.tab_dashboard.rtlist_dashboard[c].myAssignedCount != null) {
                    myAssCount = self.config.tab_dashboard.rtlist_dashboard[c].myAssignedCount;
                }
                var myTaskCount = 0;
                if (self.config.tab_dashboard.rtlist_dashboard[c].taskCount != null) {
                    myTaskCount = self.config.tab_dashboard.rtlist_dashboard[c].taskCount;
                }
                var overDewTaskCount = 0;
                if (self.config.tab_dashboard.rtlist_dashboard[c].overDewTaskCount != null) {
                    overDewTaskCount = self.config.tab_dashboard.rtlist_dashboard[c].overDewTaskCount;
                }
//                IWI show total record count based on user�s active departmentstart
                var actissCountByDep = 0;
                if (self.config.tab_dashboard.rtlist_dashboard[c].isscount_by_dep != null) {
                    actissCountByDep = self.config.tab_dashboard.rtlist_dashboard[c].isscount_by_dep;
                }
//                IWI show total record count based on user�s active department end

let rowStyle = "height: 46px;margin-right: 10px;line-height:26px;display: flex;margin-bottom: 10px;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;";

table += "<tr style='display:flex;width:1112px;justify-content: space-between;align-items: center;'>";

// First Cell
table += "<td style='width: 480px;" + rowStyle + "'>" +
    "<img src='/img/icons/ist/" + self.config.tab_dashboard.rtlist_dashboard[c].key + ".png' style='height: 28px;' alt='airplane'>" +
    "<h2 style='padding-left:10px;color: #3B70AB;font-size: 21px;margin-bottom: 4px;position: relative;'>" +
    "<div style='background: #ddd;'></div>" +
    "<a style='cursor:pointer;text-decoration: none;color: black;font-size: 19px; font-weight: bold;' onclick='module_redirect(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")'>" +
    self.config.tab_dashboard.rtlist_dashboard[c].name + "</a></h2></td>";

// Second Cell
table += "<td style='width:340px;" + rowStyle + "'>" +
    "<a onclick='module_redirect(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")' style='margin-left: 40px;margin-top: 4px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;'>" + actissCount + "</a></td>";

// Third Cell
table += "<td style='width:340px;" + rowStyle + "'>" +
    "<a onclick='module_redirect(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")' style='margin-left: 40px;margin-top: 4px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;'>" + actissCountByDep + "</a></td>";

// Fourth Cell
table += "<td style='width:310px;" + rowStyle + "'>" +
    "<a onclick='module_redirect_assignToMe(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")' style='margin-left: 20px;margin-top: 4px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;'>" + myAssCount + "</a></td>";

// Fifth Cell
table += "<td style='width:450px;" + rowStyle + "'>" +
    "<a onclick='my_task_redirect(\"" + string_sp(self.config.tab_dashboard.rtlist_dashboard[c].name, 1) + "\",0)' style='margin-left: 49px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;text-decoration: none;margin-top: 4px;'>" + myTaskCount + "</a>" +
    "<a onclick='my_task_redirect(\"" + string_sp(self.config.tab_dashboard.rtlist_dashboard[c].name, 1) + "\",1)' style='margin-left: 78px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;text-decoration: none;margin-top: 4px;'>" + overDewTaskCount + "</a></td>";
if (self.config.tab_dashboard.rtlist_dashboard[c].id == '1') {
   
// Sixth Cell (conditional chart button)
// table += "<td style='width:200px;" + rowStyle + "'>" +
//     "<a onclick='module_redirect_chart(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")' style='margin-left: 20px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;margin-top: 4px;'>Chart</a></td>";
// }
table += "<td style='width:200px;" + rowStyle + "'>" +
    "<a onclick='module_redirect_chart(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")'>" + "<img src=\"/img/icons/ist/bar-chart.png\" alt=\"Chart\" style=\"float: left;margin-left:30px;\">" +"</a></td>";
}
//IWI chart for quality event module 07112025 start // IWI Redirect url change from dashboard to any module list
else if (self.config.tab_dashboard.rtlist_dashboard[c].id == '31' || self.config.tab_dashboard.rtlist_dashboard[c].id == '39' || self.config.tab_dashboard.rtlist_dashboard[c].id == '51' ) { 
   
// Sixth Cell (conditional chart button)
// table += "<td style='width:200px;" + rowStyle + "'>" +
//     "<a onclick='module_redirect_chart(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")'></a></td>";
// }
table += "<td style='width:200px;" + rowStyle + "'>" +
    "<a onclick='module_redirect_chart(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")'>" + "<img src=\"/img/icons/ist/bar-chart.png\" alt=\"Chart\" style=\"float: left;margin-left:30px;\">" +"</a></td>";
}
//IWI chart for quality event module 07112025 end
else{
    
// Sixth Cell (conditional chart button)
// table += "<td style='width:200px;" + rowStyle + "'>" +
//     "<a onclick='chartRedirect(" + c + ")' style='margin-left: 20px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;margin-top: 4px;'>Chart</a></td>";
// }
table += "<td style='width:200px;" + rowStyle + "'>" +
    "<a onclick='module_redirect_chart(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")'>" + "<img src=\"/img/icons/ist/bar-chart.png\" alt=\"Chart\" style=\"float: left;margin-left:30px;\">" +"</a></td>";
}

table += "</tr>";

/*
                table += "<tr style='display:flex;width: 1112px;justify-content: space-between;align-items: center;'>" +
                        "<td style='height: 46px;width: 480px;line-height:26px;display: flex;margin-bottom: 10px;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;margin-bottom: 5px;margin-right: 10px;'>" +
                        "<img src='/img/icons/ist/" + self.config.tab_dashboard.rtlist_dashboard[c].key + ".png' style='height: 28px; alt='airplane'>" +
                        "<h2 style='padding-left:10px;color: #3B70AB;font-size: 21px;margin-bottom: 4px;position: relative;'>" +
                        "<div style='background: #ddd;'></div>" +
                        "<a style='cursor:pointer;text-decoration: none;color: black;font-size: 19px; font-weight: bold;' onclick='module_redirect(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")'>" + self.config.tab_dashboard.rtlist_dashboard[c].name + "</a>" +
                        "</h2>" +
                        "</td>" +
                        "<td style='height: 46px;margin-right: 10px;line-height:26px;display: flex;margin-bottom: 10px;width:340px ;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;margin-bottom: 5px;'>" +
                        "<a onclick='module_redirect(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")' style='margin-left: 40px;margin-top: 4px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;'>" + actissCount + "</a>" +
                        "</td>" +
                        "<td style='height: 46px;margin-right: 10px;line-height:26px;display: flex;margin-bottom: 10px;width:340px ;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;margin-bottom: 5px;'>" +
                        "<a onclick='module_redirect(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")' style='margin-left: 40px;margin-top: 4px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;'>" + actissCountByDep + "</a>" +
                        "</td>" +
                        "<td style='height: 46px;margin-right: 10px;line-height:26px;display: flex;margin-bottom: 10px;width:310px ;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;margin-bottom: 5px;'>" +
                        "<a onclick='module_redirect_assignToMe(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")' style='margin-left: 20px;margin-top: 4px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;'>" + myAssCount + "</a>" +
                        "</td>" +
                        "</td>" +
                        "<td style='height: 46px;margin-right: 10px;line-height:26px;display: flex;margin-bottom: 10px;width:450px ;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;margin-bottom: 5px;'>" +
                        "<a onclick='my_task_redirect(&#39;" + string_sp(self.config.tab_dashboard.rtlist_dashboard[c].name, 1) + "&#39;,0)' style='margin-left: 49px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;text-decoration: none;margin-top: 4px;'>" + myTaskCount + "</a>" +
                        "<a onclick='my_task_redirect(&#39;" + string_sp(self.config.tab_dashboard.rtlist_dashboard[c].name, 1) + "&#39;,1)' style='margin-left: 78px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;text-decoration: none;margin-top: 4px;'>" + overDewTaskCount + "</a>" +
                        "</td>" +
                        "<td style='height: 46px;margin-right: 10px;line-height:26px;display: flex;margin-bottom: 10px;width:200px ;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;margin-bottom: 5px;'>" +
                        "<a onclick='chartRedirect(" + c + ")' style='margin-left: 20px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;margin-top: 4px;'>Chart</a>" +
                        "</td>" +
                        "</tr>";
                
                comment for go to direct chart section 21-05-2025
                
            }
        }

        $("#rt-list11").html(table);

        table += "</tr>";

        table = "</table>";

       
    }
    
    */
    /* IWI New Design start
   init_RT_List = function () {
        
         var dashboardTopSecHtml = '';
        var dashboardTableHtml = '';

        dashboardTopSecHtml += '<div  class="dashboardTopSec">';
          dashboardTopSecHtml += '<div >';
        $.post("/public/access?action=recent_updates", {
            day: 700,
            limit: 1,
        }, function (x) {
            if (x.success) {
                if (x.result.length > 0) {
                    dashboardTopSecHtml += '<h4><img src="/img/icons16/system_updated.png" alt="(up)" style="float: left;"><a href="javascript:void(0);" onclick="goToSupport();">Recent Updates</a></h4>';
                    $.each(x.result, function (index, value) {
                        const only_date = value.dt.split(/ (.*)/);
                        dashboardTopSecHtml += '<h3><a href="javascript:void(0);" onclick="goToSupport();"><span style="color: red;">' + only_date[0] + '</span> ' + value.title + '</a></h3>';
                        // dashboardTopSecHtml += '<hr style="margin-bottom: 20px;">';
                    });

                }
            }
            
              dashboardTopSecHtml += '</div>';
//            dashboardTopSecHtml += '<div class="goToPubPageSec"><img src="/img/icons16/publication-white.png" alt="" style="float: left;"><a href="javascript:void(0);" ">Publication <span>120</span></a></div>';
            dashboardTopSecHtml += '<div class="goToPubPageSec"><img src="/img/icons/publication.png" alt="" style="float: left;"><a href="javascript:void(0);" ">Publication </a></div>';

            dashboardTopSecHtml += '</div>';
            $('#rt-list11').prepend(dashboardTopSecHtml);
        }).fail(cargohub.its.http_fail);


        dashboardTableHtml += '<div class="dashboardTableSec">';
        dashboardTableHtml += '<table>';
        dashboardTableHtml += ' <tbody>';
        for (c = 0; c < self.config.tab_dashboard.rtlist_dashboard.length; c++) {
            if (self.config.tab_dashboard.rtlist_dashboard[c].yep == true) {

                var actissCount = parseInt(0);
                if (self.config.tab_dashboard.rtlist_dashboard[c].isscount != null) {
                    actissCount = parseInt(self.config.tab_dashboard.rtlist_dashboard[c].isscount);
                }
                var myAssCount =parseInt(0);
                if (self.config.tab_dashboard.rtlist_dashboard[c].myAssignedCount != null) {
                    myAssCount = self.config.tab_dashboard.rtlist_dashboard[c].myAssignedCount;
                }
                var myTaskCount = 0;
                if (self.config.tab_dashboard.rtlist_dashboard[c].taskCount != null) {
                    myTaskCount = self.config.tab_dashboard.rtlist_dashboard[c].taskCount;
                }
                var overDewTaskCount = 0;
                if (self.config.tab_dashboard.rtlist_dashboard[c].overDewTaskCount != null) {
                    overDewTaskCount = self.config.tab_dashboard.rtlist_dashboard[c].overDewTaskCount;
                }
//                IWI show total record count based on user�s active departmentstart
                var actissCountByDep = 0;
                if (self.config.tab_dashboard.rtlist_dashboard[c].isscount_by_dep != null) {
                    actissCountByDep = self.config.tab_dashboard.rtlist_dashboard[c].isscount_by_dep;
                }
//                IWI show total record count based on user�s active department end

                let percalc = parseInt((myTaskCount - overDewTaskCount) * 100 / myTaskCount);




                dashboardTableHtml += '<tr>';
                dashboardTableHtml += '<td><a onclick="module_redirect(' + self.config.tab_dashboard.rtlist_dashboard[c].id + ')"><span><img src="/img/icons/ist/' + self.config.tab_dashboard.rtlist_dashboard[c].key + '.png" style="height: 18px;margin-right: 10px;" alt="airplane"></span><span>' + self.config.tab_dashboard.rtlist_dashboard[c].name + '</span></a></td>';
//                dashboardTableHtml += '<td><div class="ractive">Active: <span class="actissCount">' + actissCount + '</span> | <span class="actissCountByDep">' + actissCountByDep + '</span></div></td>';
                dashboardTableHtml += '<td><span class="orgCountLbl">ORG:</span> <span class="orgCount">' + actissCount + '</span></td>';
                dashboardTableHtml += '<td><span class="depCountLbl">DEP:</span> <span class="depCount">' + actissCountByDep + '</span> </td>';
                dashboardTableHtml += '<td><span class="myAssCountLbl">Assigned:</span> <span class="myAssCount">' + myAssCount + '</span></td>';
                dashboardTableHtml += '<td><div class="ramydesk">My Tasks: <span class="myTaskCount"><a onclick="my_task_redirect(&#39;' + string_sp(self.config.tab_dashboard.rtlist_dashboard[c].name, 1) + '&#39;,0)">' + myTaskCount + '</a></span> | </span> OverDue: <span class="overDewTaskCount"><a onclick="my_task_redirect(&#39;' + string_sp(self.config.tab_dashboard.rtlist_dashboard[c].name, 1) + '&#39;,1)"> ' + overDewTaskCount + ' </a></div></td>';
                dashboardTableHtml += '<td><div class="goToChartPageSec"><a onclick="module_redirect_chart(' + self.config.tab_dashboard.rtlist_dashboard[c].id + ')" href="javascript:void(0);" ><img src="/img/icons/ist/bar-chart.png" alt="Chart" style="float: left;"></a> </td>';
                dashboardTableHtml += '</tr>';
            }
        }
        dashboardTableHtml += '</tbody>'; 
        dashboardTableHtml += '</table>';
        dashboardTableHtml += '</div>';

        $('#rt-list11').append(dashboardTableHtml);
        
        
        
        
        
        

    };
   IWI New Design end  */
//    init_RT_List = function () {
//        setTimeout(setup_Updates, 500);
//        var indexRowNumber = 4;
//        var tableMargin = "margin-top: 20px;padding-left: 22px;";
//        var table = "<table class='dashboardTable' border='0' width='0%'  cellpadding='0px;'  cellspacing='0px;' style='" + tableMargin + "'>";
//        table += "<tr>";
//        table +=
//                "<tr style='display:flex;width: 1112px;justify-content: space-between;align-items: center;'>" +
//                "<td style='height: 46px;width: 313px;line-height: 21px;display: flex;margin-bottom: 10px;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;margin-bottom: 5px;margin-right: 10px;'>" +
//                "<p style='color: black;font-size: 14px; font-weight: 500;font-weight: bold;margin-left: 15px;margin-left: 40px;'>Active Records</p>" +
//                "</td>" +
//                "<td style='height: 46px;margin-right: 10px;line-height:1px;display: flex;margin-bottom: 10px;width:218px ;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;margin-bottom: 5px;'>" +
//                "<p style='font-size: 14px;color:black;font-weight: 500;font-weight: bold;margin-top: 12px;margin-left: 40px;'>ORG</p>" +
//                "</td>" +
//                "<td style='height: 46px;margin-right: 10px;line-height:1px;display: flex;margin-bottom: 10px;width:218px ;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;margin-bottom: 5px;'>" +
//                "<p style='font-size: 14px;color:black;font-weight: 500;font-weight: bold;margin-top: 12px;margin-left: 40px;'>DEP</p>" +
//                "</td>" +
//                "<td style='height: 46px;margin-right: 10px;line-height:1px;display: flex;margin-bottom: 10px;width:200px ;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;margin-bottom: 5px;'>" +
//                "<p style='font-size: 14px;color:black;font-weight: 500;font-weight: bold;margin-top: 12px;margin-left: 20px;'>My assigned</p>" +
//                "</td>" +
//                "</td>" +
//                "<td style='height: 46px;margin-right: 10px;line-height:1px;display: flex;margin-bottom: 10px;width:281px ;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;margin-bottom: 5px;'>" +
//                "<p style='font-size: 14px;color:black;font-weight: 500;font-weight: bold;margin-top: 12px;margin-left: 20px;'>My Tasks | OverDue</p>" +
//                "</td>" +
//                "<td style='height: 46px;margin-right: 10px;line-height:1px;display: flex;margin-bottom: 10px;width:134px ;display: flex;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;margin-bottom: 5px;'>" +
//                "<p style='font-size: 14px;color:black;font-weight: 500;font-weight: bold;margin-top: 12px;margin-left: 20px;'>Charts</p>" +
//                "</td>" +
//                "</tr>";
//        for (c = 0; c < self.config.tab_dashboard.rtlist_dashboard.length; c++) {
//            if (c % indexRowNumber == 0)
//                table += "</tr><tr>";
//
//            if (self.config.tab_dashboard.rtlist_dashboard[c].yep == true) {
//                var actissCount = 0;
//                if (self.config.tab_dashboard.rtlist_dashboard[c].isscount != null) {
//                    actissCount = self.config.tab_dashboard.rtlist_dashboard[c].isscount;
//                }
//                var myAssCount = "";
//                if (self.config.tab_dashboard.rtlist_dashboard[c].myAssignedCount != null) {
//                    myAssCount = self.config.tab_dashboard.rtlist_dashboard[c].myAssignedCount;
//                }
//                var myTaskCount = 0;
//                if (self.config.tab_dashboard.rtlist_dashboard[c].taskCount != null) {
//                    myTaskCount = self.config.tab_dashboard.rtlist_dashboard[c].taskCount;
//                }
//                var overDewTaskCount = 0;
//                if (self.config.tab_dashboard.rtlist_dashboard[c].overDewTaskCount != null) {
//                    overDewTaskCount = self.config.tab_dashboard.rtlist_dashboard[c].overDewTaskCount;
//                }
////                IWI show total record count based on user�s active departmentstart
//                var actissCountByDep = 0;
//                if (self.config.tab_dashboard.rtlist_dashboard[c].isscount_by_dep != null) {
//                    actissCountByDep = self.config.tab_dashboard.rtlist_dashboard[c].isscount_by_dep;
//                }
////                IWI show total record count based on user�s active department end
//
//let rowStyle = "height: 46px;margin-right: 10px;line-height:26px;display: flex;margin-bottom: 10px;background: #f9f9f9;box-shadow: 0 0 10px #ddd;border-left: 4px solid #1A73E8;padding: 10px 15px;";
//
//table += "<tr style='display:flex;width:1112px;justify-content: space-between;align-items: center;'>";
//
//// First Cell
//table += "<td style='width: 480px;" + rowStyle + "'>" +
//    "<img src='/img/icons/ist/" + self.config.tab_dashboard.rtlist_dashboard[c].key + ".png' style='height: 28px;' alt='airplane'>" +
//    "<h2 style='padding-left:10px;color: #3B70AB;font-size: 21px;margin-bottom: 4px;position: relative;'>" +
//    "<div style='background: #ddd;'></div>" +
//    "<a style='cursor:pointer;text-decoration: none;color: black;font-size: 19px; font-weight: bold;' onclick='module_redirect(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")'>" +
//    self.config.tab_dashboard.rtlist_dashboard[c].name + "</a></h2></td>";
//
//// Second Cell
//table += "<td style='width:340px;" + rowStyle + "'>" +
//    "<a onclick='module_redirect(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")' style='margin-left: 40px;margin-top: 4px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;'>" + actissCount + "</a></td>";
//
//// Third Cell
//table += "<td style='width:340px;" + rowStyle + "'>" +
//    "<a onclick='module_redirect(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")' style='margin-left: 40px;margin-top: 4px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;'>" + actissCountByDep + "</a></td>";
//
//// Fourth Cell
//table += "<td style='width:310px;" + rowStyle + "'>" +
//    "<a onclick='module_redirect_assignToMe(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")' style='margin-left: 20px;margin-top: 4px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;'>" + myAssCount + "</a></td>";
//
//// Fifth Cell
//table += "<td style='width:450px;" + rowStyle + "'>" +
//    "<a onclick='my_task_redirect(\"" + string_sp(self.config.tab_dashboard.rtlist_dashboard[c].name, 1) + "\",0)' style='margin-left: 49px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;text-decoration: none;margin-top: 4px;'>" + myTaskCount + "</a>" +
//    "<a onclick='my_task_redirect(\"" + string_sp(self.config.tab_dashboard.rtlist_dashboard[c].name, 1) + "\",1)' style='margin-left: 78px;cursor: pointer;font-size: 14px;color:black;font-weight: 500;text-decoration: none;margin-top: 4px;'>" + overDewTaskCount + "</a></td>";
//if (self.config.tab_dashboard.rtlist_dashboard[c].id == '1') {
//table += "<td style='width:200px;" + rowStyle + "'>" +
//    "<a onclick='module_redirect_chart(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")'>" + "<img src=\"/img/icons/ist/bar-chart.png\" alt=\"Chart\" style=\"float: left;margin-left:30px;cursor: pointer;\">" +"</a></td>";
//}
////IWI chart for quality event module 07112025 start // IWI Redirect url change from dashboard to any module list
//else if (self.config.tab_dashboard.rtlist_dashboard[c].id == '31' || self.config.tab_dashboard.rtlist_dashboard[c].id == '39' || self.config.tab_dashboard.rtlist_dashboard[c].id == '51' ) { 
//table += "<td style='width:200px;" + rowStyle + "'>" +
//    "<a onclick='module_redirect_chart(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")'>" + "<img src=\"/img/icons/ist/bar-chart.png\" alt=\"Chart\" style=\"float: left;margin-left:30px;cursor: pointer;\">" +"</a></td>";
//}
////IWI chart for quality event module 07112025 end
//else{
//table += "<td style='width:200px;" + rowStyle + "'>" +
//    "<a onclick='module_redirect_chart(" + self.config.tab_dashboard.rtlist_dashboard[c].id + ")'>" + "<img src=\"/img/icons/ist/bar-chart.png\" alt=\"Chart\" style=\"float: left;margin-left:30px;cursor: pointer;\">" +"</a></td>";
//}
//
//table += "</tr>";
//            }
//        }
//        $("#rt-list11").html(table);
//        table += "</tr>";
//        table = "</table>";
//    };
    
    
//   IWI dashboard page new design 16042026 start
   init_RT_List = function () {
    setTimeout(setup_Updates, 500);
 
//    IWI new dashboard design 11082026 start


$("#dashboard-view-header").remove();
$("#tabs-container").prepend('<div id="dashboard-view-header" class="dashboard-view-header"> <a href="/" id="dashboard-back-cockpit" class="dashboard-back-cockpit" > <i class="fa fa-arrow-left"></i> Back to Cockpit </a> </div>');


     let dashboard_module_list = ``;
     
    dashboard_module_list += `<div class="dashboard-card-header"> <div class="dashboard-title"> <i class="fa fa-list-alt"></i> Active Records </div> <a href="javascript:void(0)" onclick="goToHistory();" class="dashboard-action"> <i class="fa fa-history"></i> History </a> </div>`;
    
    dashboard_module_list += `<div class="dashboard-table-header"> <div>Module</div> <div>ORG</div> <div>DEP</div> <div>Tasks</div> <div>Overdue</div> <div>Analytics</div> </div>`;

    for (let c = 0; c < self.config.tab_dashboard.rtlist_dashboard.length; c++) {

        let item = self.config.tab_dashboard.rtlist_dashboard[c];
        if (!item.yep) continue;

        let actissCount = item.isscount || 0;
        let actissCountByDep = item.isscount_by_dep || 0;
        let myAssCount = item.myAssignedCount || 0;
        let myTaskCount = item.taskCount || 0;
        let overDewTaskCount = item.overDewTaskCount || 0;

        dashboard_module_list += `
<div class="dashboard-row"> <div class="module"> 
<div class="module-icon"> 
<img src="/img/icons/ist/blue/${item.key}.png" /> 
</div> 
<span onclick="module_redirect(${item.id})" style="cursor:pointer;">${item.name}</span>
 </div> 
<div  style="cursor:pointer;" onclick="module_redirect(${item.id})">${actissCount}</div>
 <div style="cursor:pointer;" onclick="module_redirect(${item.id})">${actissCountByDep}</div> 
<div> <span class="badge "> ${myTaskCount}</span> </div> 
<div style="cursor:pointer;" onclick='my_task_redirect("${string_sp(item.name, 1)}",1)'> <span class="badge ">${overDewTaskCount}</span> </div>
 <div> <a href="javascript:void(0)" onclick="module_redirect_chart(${item.id})" class="chart-btn"> <i class="fa fa-line-chart"></i> </a> </div> </div>`;
    }


    $("#dashboard_module_list").html(dashboard_module_list);
    $("#announcement-list").html(`<div id="announcement-loader" class="announcement-loader"> <i class="fa fa-spinner fa-spin"></i> <span>Loading...</span> </div>`);
    $.post("/user/publication?action=get_count_by_login_user",{ for: "read_sign" },
    function (x) {
//        console.log(x);
        $("#kpi_read_and_sign_count").html(x.read_sign);
    });
    $.post(
    "/user/publication/get_publications?action=get_Documentation_Notification_By_Search",
    {
        tab_type: "fetch_all_notification"
    },
    function (x) {

        let announcementHtml = "";

        if (x.data && x.data.length > 0) {

            x.data.forEach(function (item) {

                announcementHtml += `
                    <div class="activity-item">

                        <div class="activity-icon">
                            <img src="/img/icons/ist/blue/${item.rt_key}.png" /> 
                        </div>

                        <div class="activity-content">

                            <div class="activity-module">
                                ${item.title || ''}
                            </div>

                            <div class="activity-track">
                                ${item.track_id || ''}
                            </div>

                        </div>

                        <a href="/?action=document&qp=orptwp&key=${item.wv_key}&nid=${item.nid}"
                           class="activity-view"
                           title="View"  target='_blank'>
                            <i class="fa fa-eye"></i>
                        </a>

                    </div>
                `;

            });

        } else {
            announcementHtml = `
                <div class="activity-item">
                    <div class="activity-content">
                        <div class="activity-module">
                            No announcements found
                        </div>
                    </div>
                </div>
            `;
        }

        $("#announcement-list").html(announcementHtml);
    });
     loadTodoList(); //IWI new dashboard design 17082026
//    IWI new dashboard design 11082026 end

};
//   IWI dashboard page new design 16042026 end



//IWI new dashboard design 17082026 start
 loadTodoList = function () {

    $("#todo-list").html(
        '<div class="announcement-loader">' +
            '<i class="fa fa-spinner fa-spin"></i>' +
            '<span>Loading...</span>' +
        '</div>'
    );

    $.post(
        "/user/overview?action=loadTodoListByUser",
        {},
        function (response) {

            console.log("Todo API:", response);

            if (!response || !response.success) {
                $("#todo-list").html(
                    '<div class="todo-item">Unable to load todos.</div>'
                );
                return;
            }

            var html = "";

            if (!response.data || response.data.length == 0) {

                html =
                    '<div class="todo-item">' +
                        '<i class="fa fa-check-circle-o"></i>' +
                        '<span>No todos found</span>' +
                    '</div>';

                $("#todo-list").html(html);
                return;
            }

            $.each(response.data, function (index, todo) {

                var id = todo.id;
                var title = escapeTodoHtml(todo.title);
                var description = escapeTodoHtml(todo.description);
                var dueDate = todo.due_date || "";
                var priority = todo.priority || "medium";
                var status = todo.status || "pending";

                var dateText = formatTodoDate(dueDate);

                var completedClass = "";
                var checkIcon = "fa-square-o";

                if (status == "completed") {
                    completedClass = " completed";
                    checkIcon = "fa-check-square-o";
                }

                html += '<div class="todo-item ' + completedClass + '" data-id="' + id + '">';

                /* CHECK */
                html += '<div class="todo-check" ';
                html += 'onclick="completeTodo(' + id + ');">';
                html += '<i class="fa ' + checkIcon + '"></i>';
                html += '</div>';

                /* MAIN */
                html += '<div class="todo-main" ';
                html += 'onclick="openTodoPopup(' + id + ');">';

                html += '<div class="todo-title">';
                html += title;
                html += '</div>';

                html += '<div class="todo-info">';

                if (description != "") {
                    html += '<span>';
                    html += description;
                    html += '</span>';
                }

                if (description != "" && dateText != "") {
                    html += '<span class="todo-dot">.</span>';
                }

                if (dateText != "") {
                    html += '<span>';
                    html += dateText;
                    html += '</span>';
                }

                html += '</div>';

                html += '</div>';

                /* PRIORITY */
                html += '<span class="todo-priority todo-priority-' + priority + '">';
                html += escapeTodoHtml(priority);
                html += '</span>';

                /* VIEW */
                html += '<a href="javascript:void(0)" ';
                html += 'class="todo-view" ';
                html += 'onclick="openTodoPopup(' + id + '); return false;" ';
                html += 'title="View">';

                html += '<i class="fa fa-eye"></i>';

                html += '</a>';

                html += '</div>';
            });

            $("#todo-list").html(html);

        },
        "json"
    );
};

 saveTodo = function () {

    console.log('saveTodo');

    var id = $("#todo-id").val();
    var title = $.trim($("#todo-title-input").val());
    var date = $("#todo-date-input").val();
    var priority = $("#todo-priority-input").val();
    var description = $("#todo-description-input").val();
    var remark = $("#todo-remark-input").val();

    if (title == "") {

        alert("Please enter todo title.");

        $("#todo-title-input").focus();

        return;
    }

    var saveData = {
        title: title,
        due_date: date,
        priority: priority,
        description: description,
        remark: remark
    };

    if (id != "") {
        saveData.id = id;
    }

    $.post(
        "/user/overview?action=saveTodo",
        saveData,
        function (response) {

            console.log("Save Todo API:", response);

            if (!response || !response.success) {
                alert("Unable to save todo.");
                return;
            }

            closeTodoPopup();

            loadTodoList();

        },
        "json"
    );
};
openTodoPopup = function (id) {

    console.log('openTodoPopup', id);

    $("#todo-popup").fadeIn(150);

    /* ADD TODO */
    if (!id) {

        $("#todo-id").val("");

        $("#todo-popup-heading").text("Add Todo");

        $("#todo-title-input").val("");
        $("#todo-date-input").val("");
        $("#todo-priority-input").val("medium");
        $("#todo-description-input").val("");
        $("#todo-remark-input").val("");

        $("#todo-delete-btn").hide();

        $("#todo-save-text").text("Add Todo");

        return;
    }

    /* EDIT TODO */

    $("#todo-popup-heading").text("Todo Details");
    $("#todo-delete-btn").show();
    $("#todo-save-text").text("Save");

    $.post(
        "/user/overview?action=getTodoDetails",
        {
            id: id
        },
        function (response) {

            console.log("Todo Details API:", response);

            if (!response || !response.success || !response.data) {
                return;
            }

            var todo = response.data;

            $("#todo-id").val(todo.id || "");

            $("#todo-title-input").val(todo.title || "");

            /*
             * IMPORTANT:
             * API field is due_date, not date
             */
            $("#todo-date-input").val(todo.due_date || "");

            $("#todo-priority-input").val(
                todo.priority || "medium"
            );

            $("#todo-description-input").val(
                todo.description || ""
            );

            $("#todo-remark-input").val(
                todo.remark || ""
            );

        },
        "json"
    );
};
 completeTodo = function (id) {

    console.log('completeTodo', id);

    var item = $('.todo-item[data-id="' + id + '"]');
    var icon = item.find('.todo-check i');

    if (!item.length) {
        return;
    }

    var isCompleted = item.hasClass('completed');

    var newStatus = isCompleted ? "pending" : "completed";

    $.post(
        "/user/overview?action=saveTodo",
        {
            id: id,
            status: newStatus
        },
        function (response) {
//            console.log("Complete Todo API:", response);

            if (!response || !response.success) {
                alert("Unable to update todo.");
                return;
            }
            if (newStatus == "completed") {
                item.addClass("completed");
                icon.removeClass("fa-square-o").addClass("fa-check-square-o");
            } else {
                item.removeClass("completed");
                icon.removeClass("fa-check-square-o").addClass("fa-square-o");
            }
        },
        "json"
    );
};
            
 closeTodoPopup = function () {
console.log('closeTodoPopup');
    $("#todo-popup").fadeOut(150);

},

 formatTodoDate = function (date) {

    if (!date) {
        return "";
    }

    // Handle YYYY-MM-DD HH:MM:SS
    var datePart = String(date).split(" ")[0];

    var parts = datePart.split("-");

    if (parts.length != 3) {
        return date;
    }

    var year = parseInt(parts[0], 10);
    var month = parseInt(parts[1], 10) - 1;
    var day = parseInt(parts[2], 10);

    var todoDate = new Date(year, month, day);

    var today = new Date();

    today.setHours(0, 0, 0, 0);
    todoDate.setHours(0, 0, 0, 0);

    var diff = Math.round(
        (todoDate.getTime() - today.getTime()) / 86400000
    );

    if (diff == 0) {
        return "Today";
    }

    if (diff == 1) {
        return "Tomorrow";
    }

    if (diff == -1) {
        return "Yesterday";
    }

    return parts[2] + "/" + parts[1] + "/" + parts[0];
},

 escapeTodoHtml = function (value) {

    if (value == null) {
        return "";
    }

    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
},



 deleteTodo = function () {
console.log('deleteTodo');
    var id = $("#todo-id").val();
    if (!id) {
        return;
    }
   Swal.fire({
        title: 'Delete Todo?',
        text: 'Are you sure you want to delete this todo?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel'
    }).then(function (result) {

        if (!result.isConfirmed) {
            return;
        }
        $.post(
        "/user/overview?action=saveTodo",
        {
            id: id,
            is_deleted: '1'
        },
        function (response) {
            if (response.success) {
                $('.todo-item[data-id="' + id + '"]').remove();

        closeTodoPopup();

        Swal.fire({
            title: "Deleted!",
            text: "Todo has been deleted.",
            type: "success",
            timer: 1200,
            showConfirmButton: false
        });
            }
        });

        

    });
};

//IWI new dashboard design 17082026 end
    
    
    this._public({
        // section initialization
        init: function () {
            if (!self.config)
                self.config = {};

            user_ss = cargohub.application.user_Get_Session();

            // User Settings module consists of dynamically loaded tabs
            setup_Tabs();
        }
    });


//    $(document).on('keyup', '#search_publish_documents', function () {
//        var search_publish_documents = $('#search_publish_documents').val();
//        console.log('hjgjgjgjhg', search_publish_documents);
//
//        $.post("?action=get_Documentation_By_Search", {
//            search_val: search_publish_documents
//        }, function (x) {
//            console.log(x);
//        });
//
//
//    });

});
function module_redirect(id) {
    user_ss = cargohub.application.user_Get_Session(); //IWI Redirect url change from dashboard to any module list
    cargohub.application.save_Preferences({
        "rt_id": id
    }, function () {
        //    IWI Redirect url change from dashboard to any module list start
        if(user_ss.org_id!=''){
            document.location = '/issue/list?org_id='+user_ss.org_id;
        }else{
            document.location = '/issue/list';
        }
//     IWI Redirect url change from dashboard to any module list end
        
    });
}
function module_redirect_assignToMe(id) {
    cargohub.application.save_Preferences({
        "rt_id": id
    }, function () {
        document.location = '/issue/list#xfilters=active|assigned';
    });
}
function task_redirect() {
    document.location = '/manager/tasks:record?list=self';
}
function my_task_redirect(string, check) {
    if (check)
        document.location = '/manager/tasks:record?list=self&dep=' + string_sp(string, 1) + '&overdue=true';
    else
        document.location = '/manager/tasks:record?list=self&dep=' + string_sp(string, 1) + '&overdue=false';
}
function string_sp(string, check) {
    if (check)
        string = string.replace(/ /g, "_");
    else
        string = string.replace(/_/g, ' ');

    return string;
}
function chartRedirect(inx) {
    document.location = '/user/overview#modules';
    setTimeout(goToList(inx), 800);
}
function goToList(index) {
    $("#rt-list").jqxListBox('selectedIndex', index);
}
function clearbtn() {
    if (!confirm("Are you sure you want to clear this list ?"))
        return;

    $.post("?action=Issues_Recent_Clear", function (x) {
        if (x.success) {
            document.location.reload();
        } else
            cargohub.alert(x.reason, "Message from server");
    });
}
function goToSupport() {
//	$("#tabs-container").jqxTabs('select', 7);
    $("#tabs-container").jqxTabs('select', 8);
}
function goToPublications() {
    $("#tabs-container").jqxTabs('select', 1);
}

function getDocumentationDocList(tab_type) {

//    $("#publish_documents").empty();
    $("#publish_documents").html('');

    $(".simple-spinner").show();


    var search_type = $.map($('input[name="search_publish_documents_type"]:checked'), function (c) {
        return c.value;
    });
    var keyword = $('#search_publish_documents').val();
    var search_by_department_list = '';
    var search_by_department_list_value = '';
    if(tab_type!='private'){
    var search_by_department_list = $('#search_by_department_list').val();
    var search_by_department_list_value = $('#search_by_department_list option:selected').text();
    }
      console.log(search_by_department_list);

    // Fetch published documents by search start G


    $.post("?action=get_Documentation_By_Search", {
        keyword: keyword,
        search_by_department_list: search_by_department_list,
        tab_type: tab_type,
        search_type: search_type,
    }, function (x) {
        console.log(x.recent);
        getaData = x.recent;
        $("#rt-list11").css("float", "right");

        var recentRecord = "";
        recentRecord += "<tr>";
        recentRecord += "<td>View</td>";
        recentRecord += "<td style='width: 38%;'>Tracking</td>";
        recentRecord += "<td style='width: 35%;'>Document Title</td>";
//        recentRecord += "<td>Owner's Department</td>";
        if (tab_type == 'department') {
            recentRecord += "<td style='width: 10%;'>Access</td>";
        }
//    recentRecord += "<td>Record Title</td>";
        recentRecord += "<td style='width: 10%;'>Group Name</td>";
        recentRecord += "<td style='width: 10%;'>Vs.</td>";
        recentRecord += "<td style='width: 10%;'>Updated</td>";
        
        recentRecord += "</tr>";

        if (getaData.length > 0) {

            for (c = 0; c < getaData.length; c++) {
//        console.log('uiuiu', getaData);

                if (getaData[c].pri == "" || getaData[c].pri == null) {
                    detailsPri = "	 ";
                } else {
                    detailsPri = "" + getaData[c].pri + "";
                }
                recentRecord += "<tr id='" + getaData[c].rt_key + "'>";
                recentRecord += "<td style=''><a href='/?action=document&qp=orptwp&key=" + getaData[c].wv_key + "&nid=" + getaData[c].nid + "' target='_blank'><img src='/img/icons16/web_layout.png' alt='[Web View]'></a></td>";
                recentRecord += "<td style=''><a href='/record/process:" + getaData[c].track_id + "#panels' id='" + getaData[c].rt_key + "' style='font-size: 12px;'>" + getaData[c].track_id + "</a></td>";
                recentRecord += "<td style=''><img src='/img/icons16/ist/" + getaData[c].rt_key + ".png' alt='" + getaData[c].rt_key + "' style='padding-right: 6px;'/>" + getaData[c].title + "</td>";
//            recentRecord += "<td>" + getaData[c].department + "</td>";
             if (tab_type == 'department') {
//                recentRecord += "<td>" + getaData[c].department.replace(",", '') + "</td>";
//                recentRecord += "<td>\n\
//" + search_by_department_list_value + "\n\
//<div class='tooltip-hover-text highlight-text'> ("+ getaData[c].department_count + ") <span class='tooltip-text tooltip-top' >"+ getaData[c].department.replace(",", '') + "</span></div></td>";
recentRecord += "<td><div class=' highlight-text displayThis' data-id='" + getaData[c].id + "'> ("+ getaData[c].department_count + ") </div>\n\
\n\
\n\<span style='display:none' class='display_" + getaData[c].id + "' >"+ getaData[c].department.replace(",", '</br>') + "</span>\n\
</td>";

            }
//        recentRecord += "<td>" + detailsPri + "</td>";
                recentRecord += "<td>" + getaData[c].group_name + "</td>";
                recentRecord += "<td>" + getaData[c].version + "</td>";
                recentRecord += "<td>" + getaData[c].dt + "</td>";
               
                recentRecord += "</tr>";
                recentRecord += "<tr><td colspan='2'></td></tr>";
            }
        } else {
            recentRecord += "<tr id='cargoclaim'><td colspan='6'> No data Found</td></tr>";
        }
//        $("#publish_documents").append(recentRecord);
//        console.log(recentRecord);
        $("#publish_documents").html(recentRecord);
        recentRecord = '';

        $(".simple-spinner").hide();
    });

    // Fetch published documents by search end G                                      

//    
}


$(document).on('click', '#fetch_department_data', function () {
    console.log('fetch_department_data');
    $('.rafiltertab').show();
    getDocumentationDocList('department');
    $('#fetch_all_data,#fetch_private_data,#fetch_request_to_publish_data').removeClass('active');
    $('#fetch_department_data').addClass('active');
    $('#search_publish_documents_type').val('department');
});
$(document).on('click', '#fetch_private_data', function () {
    console.log('fetch_private_data');
    $('.rafiltertab').hide();
    getDocumentationDocList('private');
    $('#fetch_all_data,#fetch_department_data,#fetch_request_to_publish_data').removeClass('active');
    $('#fetch_private_data').addClass('active');
    $('#search_publish_documents_type').val('private');
});
$(document).on('click', '#fetch_all_data', function () {
    console.log('fetch_all_data');
    $('.rafiltertab').show();
    getDocumentationDocList('all');
    $('#fetch_private_data,#fetch_department_data,#fetch_request_to_publish_data').removeClass('active');
    $('#fetch_all_data').addClass('active');
    $('#search_publish_documents_type').val('all');
});

$(document).on('click', '#fetch_request_to_publish_data', function () {
    console.log('fetch_request_to_publish_data');
    $('.rafiltertab').show();
    getDocumentationDocList('all');
    $('#fetch_private_data,#fetch_department_data,#fetch_all_data').removeClass('active');
    $('#fetch_request_to_publish_data').addClass('active');
    $('#search_publish_documents_type').val('request_to_publish_data');
});


$(document).on('click', '#doments_rasearch', function () {
    console.log('search_publish_documents');
    getDocumentationDocList($('#search_publish_documents_type').val());
});
$(document).on('input propertychange paste', '#search_publish_documents', function () {
    console.log('search_publish_documents');
    getDocumentationDocList($('#search_publish_documents_type').val());
});

$(document).on('mouseenter', '#search_publish_documents_type', function () {
    console.log('mouseenter');
    getDocumentationDocList($('#search_publish_documents_type').val());
});

$(document).on('change', '#search_by_department_list', function () {
    console.log(this.val);
    getDocumentationDocList($('#search_publish_documents_type').val());
});




$(document).on('click', '.displayThis', function () {

    var id = $(this).attr('data-id');
//    alert($('.display_' + id).html());

    Swal.fire({
        title: "",
        icon: "",
        html:$('.display_' + id).html(),
        cancelButtonAriaLabel: "Thumbs down"
    });

});


$( document ).ready(function() {
//   console.log($('#chkAuthOnOff').val().length);
    if($.trim($('#chkAuthOnOff').val())){
        $('#actauthIcon').html('<a href="/user/overview#account"><img src="/img/icons/2fag.png" alt="2FA" > </a>');
    }else{
        $('#actauthIcon').html('<a href="/user/overview#account"><img src="/img/icons/2far.png" alt="2FA" ></a>');
    }
});





//  go to direct chart section start 
function module_redirect_chart(id) {
    if(id=='1'){

     user_ss = cargohub.application.user_Get_Session(); //IWI Redirect url change from dashboard to any module list
    cargohub.application.save_Preferences({
        "rt_id": id
    }, function () {
        //    IWI Redirect url change from dashboard to any module list start
        
//         IWI Aircargoclaim chart page update 12012026  start
         
//        if(user_ss.org_id!=''){
//            document.location = '/issue/list#chart?org_id='+user_ss.org_id;
//        }else{
//            document.location = '/issue/list#chart';
//        }
          document.location = '/issue/list?action=air_cargo_claim_chart';
//          IWI Aircargoclaim chart page update 12012026 end
//     IWI Redirect url change from dashboard to any module list end
        
    });
}//IWI chart for quality event module 07112025 start
    else if (id == '31') {
        user_ss = cargohub.application.user_Get_Session(); //IWI Redirect url change from dashboard to any module list
        cargohub.application.save_Preferences({
            "rt_id": id
        }, function () {
            if (user_ss.org_id != '') {
                document.location = '/issue/list?action=quality_event_chart&org_id=' + user_ss.org_id;
            } else {
                document.location = '/issue/list?action=quality_event_chart';
            }
        });
    }
//IWI chart for quality event module 07112025 end
//From cockpit go to chart page for training scheduler and training record start

else if (id == '39' || id == '51') {
        user_ss = cargohub.application.user_Get_Session(); //IWI Redirect url change from dashboard to any module list
        cargohub.application.save_Preferences({
            "rt_id": id
        }, function () {
            if (user_ss.org_id != '') {
                document.location = '/issue/list#chart?org_id='+user_ss.org_id;
            } else {
                document.location = '/issue/list#chart';
            }
        });
    }
    
    //    IWI audit control page chart new 11032026 start
else if (id == '34' ) {
        user_ss = cargohub.application.user_Get_Session(); //IWI Redirect url change from dashboard to any module list
        cargohub.application.save_Preferences({
            "rt_id": id
        }, function () {
            if (user_ss.org_id != '') {
                document.location = '/issue/list?action=audit_control_chart&org_id=' + user_ss.org_id;
            } else {
                document.location = '/issue/list?action=audit_control_chart';
            }
        });
    }


//IWI audit control page chart new 11032026 end
//IWI separate listing page for all contracts 28052026 start
else if (id == '55' ) {
        user_ss = cargohub.application.user_Get_Session(); //IWI Redirect url change from dashboard to any module list
        cargohub.application.save_Preferences({
            "rt_id": id
        }, function () {
            if (user_ss.org_id != '') {
                document.location = '/issue/list?action=get_supplier_contracts&org_id=' + user_ss.org_id;
            } else {
                document.location = '/issue/list?action=get_supplier_contracts';
            }
        });
    }
//IWI separate listing page for all contracts 28052026 end

//IWI separate listing page for all assets 07072026 start
else if (id == '56' ) {
        user_ss = cargohub.application.user_Get_Session(); //IWI Redirect url change from dashboard to any module list
        cargohub.application.save_Preferences({
            "rt_id": id
        }, function () {
                document.location = '/issue/list?action=get_all_assets';
        });
    }
//IWI separate listing page for all assets 07072026 end
    else{
          document.location = '/user/overview#modules';
    setTimeout(goToList(id), 800);
    }
//From cockpit go to chart page for training scheduler and training record end
}
//  go to direct chart section end

//IWI new dashboard design 11082026 start
function goToHistory() {
    $("#tabs-container").jqxTabs('select', 2);
}
function goToCockpit() {
    $("#tabs-container").jqxTabs('select', 0);
}
updateDashboardViewHeader(
    $("#tabs-container").jqxTabs("selectedItem")
);
$("#tabs-container").on("selected", function () {
    var tabIndex = $("#tabs-container").jqxTabs("selectedItem");
    updateDashboardViewHeader(tabIndex);
});
function updateDashboardViewHeader(index) {
    if (index == 0) {
        $("#dashboard-back-cockpit").hide();

    } else if (index == 2) {
        $("#dashboard-back-cockpit").css("display", "flex");
    } 
}
//IWI new dashboard design 11082026 end