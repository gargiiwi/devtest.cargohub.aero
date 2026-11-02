/**
 * Module "User Manager" - Can be accessed from the organization module, from the departments module or from admin user-search
 * -- Note that this isn't for user self-editing, but for a manager/admin to edit another user!
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2018.10
 */
cargohub.user_manager = donrey.BASE._extend(function() {
    // private
    var self = this,
        c = null,
        // Tabs & Windows
        loaded_tabs = {},
        popups = {},

        /**
         * Clears and optionally disables an item showing tab, making it ready to be loaded with new data
         *
         * @param idx Can be 1
         * @param title Change Tab's title into this
         * @param disable Disable the tab so it can't be selected
         */
        clear_Item_Tab = function(idx, title, disable)
        {
            var icon = "vcard", tab_key = "tab_vcard"; // For now, only the vcard tab can be cleared

            if(disable)
                $('#tabs-container').jqxTabs('disableAt', idx);
            if(title)
                $('#tabs-container li.jqx-tabs-title:eq('+idx+') div.jqx-tabs-titleContentWrapper').html('<img src="/img/icons16/'+icon+'.png" alt="'+title+'" /> '+title); // resets the header
            $('#tabs-container').jqxTabs('setContentAt', idx, '<div style="text-align: center; padding: 30px;"><img src="/img/extra/loader3.gif" alt="Loading..." /></div>'); // clears content
            delete loaded_tabs[tab_key];
        },

        /**
         * First tab, showing user configuration, signature, statistics, department and user's current ACLs (Privileges)
         */
        setup_Tab_Configure = function()
        {
            var chooser = $('#oauth_hid').val();
            var oauth_options = [
                {value: "0", text: "No"},
                {value: "1", text: "Yes"},
            ];

            $('#tab_configure #usr_oauth').jqxDropDownList({
                theme: cargohub.jqxtheme,
                source: oauth_options,
                displayMember: "text", valueMember: "value",
                selectedIndex: chooser,
                width: 258,	height: 25,
                autoDropDownHeight: true
            });
            var active_dep_index = 0,
                pwstr = 0;

            // === Left side, basic account config ===

            if(self.config.dataSources.features.status_ops == "edit") {
                // First section, the user basic data
                var status_options = [
                    {value: "active", text: "Normal user (offline)"},
                    {value: "online", text: "Normal user (online)"},
                    {value: "disabled", text: "Deactivated user"}, // Cannot login
                    {value: "invisible", text: "Invisible user"} // Should not appear when online, and various operations from this user are not visible
                ], status_selected_index = null;

                // Admin has access to a few more options
                if(cargohub.application.user_Is_Admin()) {
                    status_options.push({value: "eula", text: "Must view and accept EULA"}); // Force the user to see the EULA page before being allowed to log in
                    status_options.push({value: "supervisor", text: "Organization supervisor"}); // Full access inside the user's organization, just like being admin, but only applies to the user's org.
                    status_options.push({value: "jumper", text: "Inter-organization jumper"}); // Special user that has the ability to jump between organizations (for support purposes).
                    status_options.push({value: "readonly", text: "Publication Read Only"}); // Special user that has the ability to only view documents. // publication read only IWI 
                    // TODO: A way to select which organizations this user can jump to (there's actually a list of IDs, it simply needs an interface)
                }

                // which status is now active?
                for(c=0; c<status_options.length; c++)
                    if(status_options[c].value == self.config.status)
                        status_selected_index = c;

                $('#tab_configure #usr_status').jqxDropDownList({
                    theme: cargohub.jqxtheme,
                    source: status_options,
                    displayMember: "text", valueMember: "value",
                    selectedIndex: status_selected_index,
                    width: 258,	height: 25,
                    autoDropDownHeight: true
                })
                    /**
                     * Event: The user status is changed
                     * - Certain statuses make interface elements appear or disappear
                     */
                    .on('change',  function(event) {
                        /*
                        dd(event.args.item);
                        if(event.args.item.value == 'jumper') {
                            $("#tab_configure #jumper_control").fadeIn();
                        } else
                            $("#tab_configure #jumper_control").fadeOut();
                        */
                    });
            }

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
                paste_data_images:true,
                images_upload_url:'/files?action=open_upload',
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
                            var blobCache =  tinymce.activeEditor.editorUpload.blobCache;
                            var base64 = reader.result.split(',')[1];
                            var blobInfo = blobCache.create(id, file, base64);
                            blobCache.add(blobInfo);

                            /* call the callback and populate the Title field with the file name */
                            cb(blobInfo.blobUri(), { title: file.name });
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

            // Disable copy/paste in the password fields (must type it!)
            $("#tab_configure #usr_password, #tab_configure #usr_passwordr").on('paste', function(e) {
                e.preventDefault();
            });

            // Password strength and validation
            $("#tab_configure form#edit-account").jqxValidator({
                rules: [
                    {input: "#usr_passwordr", message: "Enter same password again", action: 'blur', rule: function($input) {
                            if($input.val().length == 0)
                                return true;
                            else
                                return $input.val() == $('#usr_password').val();
                        }}
                ]
            });
            $("#tab_configure #usr_password").pwstrength({
                callback: function(strength) {
                    pwstr = strength;
                }
            });

            // The random password generator
            $("#tab_configure #gen_password").jqxButton({
                theme: cargohub.jqxtheme,
                width: 260
            })
                /**
                 * Event: A new random password is requested
                 */
                .on('click', function() {
                    if($('#usr_password').val())
                        if(!confirm("Are you sure you want to replace the current password?"))
                            return;

                    var pwd = donrey.generate_UniqId('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789~!@#$%^&*()-+=[]|\\;:"/,<.>/?', 16);
                    $("#tab_configure #usr_password, #tab_configure #usr_passwordr").val(pwd).attr('type', "text");
                    $("#tab_configure form#edit-account").jqxValidator('validate');
                    $("#tab_configure #usr_password").trigger('keyup');
                });


            // === Right side, user to department relation ===

            // if the user has been associated with at least one department, a couple of controls. If there are no associations, these don't exist
            if(self.config.dataSources.user_deps) {
                // The Department selector drop-down-list control,
                for(c=0; c< self.config.dataSources.user_deps.length; c++) // find which department is currently selected
                    if(self.config.dataSources.user_deps[c].id == self.config.cdep_id) {
                        active_dep_index = c;
                        break;
                    }

                // Discovered that it's possible to set entries in the jqxDropDownList as disabled if there is a key named "disabled" in the source.
                // However, in this case we need to be able to select those rows, so that particular key must not be present, instead, it's replaced with _disabled

                $('#tab_configure #dep_select').jqxDropDownList({
                    theme: cargohub.jqxtheme,
                    source: self.config.dataSources.user_deps,
                    promptText: "Active department",
                    displayMember: "name", valueMember: "id",
                    width: 258, height: 23,
                    selectedIndex: active_dep_index,
                    autoDropDownHeight: true
                })
                    /**
                     * Event: When changing the department, the user-to-department relation is reloaded to the new one, to display the correct information:
                     * - If the user is the chief of the department
                     * - The user's comment related to department.
                     */
                    .on('change', function(event) {
                        $.get("?action=load_u2d", {
                            'user_id': self.config.id,
                            'dep_id': event.args.item.value
                        }, function(x) {
                            if(x.success) {
                                if(x.u2d === false) {
                                    // no department selected, hide the information
                                    $('li.row_dep_info').hide();

                                    // also disable the "ACLs" tab, as there is no department to set overrides for
                                    if(cargohub.application.user_Test_Privileges('org_access_manager'))
                                        $('#tabs-container').jqxTabs('disableAt', 2);
                                } else {
                                    // we have valid u2d return, change the displayed u2d stuff (and make it show)
                                    $('#dep_is_chief').jqxCheckBox('val', x.u2d.is_chief);
                                    $('#dep_disabled').jqxCheckBox('val', x.u2d.disabled);
                                    $('#dep_hidden').jqxCheckBox('val', x.u2d.hidden);
                                    $('#dep_comment').val(x.u2d.comment);

                                    $('li.row_dep_info').show();

                                    // Enable the "ACLs" tab to permit modification of per-user access rights in the active department
                                    self.config.cdep_id = event.args.item.value;
                                    if(cargohub.application.user_Test_Privileges('org_access_manager'))
                                        $('#tabs-container').jqxTabs('enableAt', 2);

                                    // Request update of ACLs editors if they have already been initialized (FIXME: The UPDATE command is incomplete)
                                    if(cargohub.components.acls_editor)
                                        cargohub.components.acls_editor.update(x.u2d.acls);
                                }
                            } else {
                                cargohub.alert(x.reason, "Message from server", "events.png");
                                // hide the chief/comment info
                            }
                        });
                    });

                // hide the checkboxes if no department is initially selected. They will be enabled by the dep_select on_change event.
                if(self.config.cdep_id == 0) {
                    $('li.row_dep_info').hide();
                    if(cargohub.application.user_Test_Privileges('org_access_manager'))
                        $('#tabs-container').jqxTabs('disableAt', 2);
                }

                // The hidden and disabled checkboxes
                $('#tab_configure li.dep_info_cb > div').each(function(i, el) {
                    var data = $(el).data();

                    $(el).jqxCheckBox({
                        theme: cargohub.jqxtheme,
                        disabled: data.disabled,
                        checked: data.checked == 1
                    })
                        .on('change', function (event) {
                            var checked = event.args.checked;
                            if (checked)
                                $(el).find('span:eq(1)').html('YES');
                            else
                                $(el).find('span:eq(1)').html('NO');
                        });

                    if(data.hint)
                        $(el).jqxTooltip({
                            theme: cargohub.jqxtheme,
                            position: 'mouse',
                            content: data.hint
                        });

                    $(el).find('span:eq(1)').html(data.checked == 1? 'YES': 'NO');
                });
            } else {
                // also disable ACLs tab if there are no departments to choose from
                if(cargohub.application.user_Test_Privileges('org_access_manager'))
                    $('#tabs-container').jqxTabs('disableAt', 2);
            }

            // ajax-ize the form, to avoid reloading when there are errors
            $("#tab_configure form#edit-account").ajaxForm({
                dataType: 'json',
                beforeSubmit: function(formData, jqForm, options)
                {
                    // setup the field checking object
                    var f = {};
                    for(c=0; c< formData.length; c++)
                        f[formData[c].name] = formData[c].value;

                    if(f['usr[user]'].length < 4) { //  || !/^[a-z0-9_]+$/i.test(f['usr[user]'])
                        cargohub.alert("The account ID must have at least 4 characters"); // and contain only english letters and numbers
                        return false;
                    }

                    if(f['usr[name]'].length < 5) {
                        cargohub.alert("Please enter at least 5 characters for the person's name.");
                        return false;
                    }

                    // not sure if this e-mail validation string actually works well ?
                    if(!f['usr[email]'] || !validate_email(f['usr[email]'])) {
                        cargohub.alert("The user must have a valid e-mail address.");
                        return false;
                    }

                    if(f['usr[password]'] != f['usr[passwordr]']) {
                        cargohub.alert("Please enter the same password twice");
                        return false;
                    }

                    if(f['usr[password]']) {
                        if(pwstr < 3) {
                            cargohub.alert("Entered password is too insecure. Please read the hints for strong passwords.");
                            return false;
                        }

                        if(pwstr == 3) {
                            if(!confirm("Are you sure you want to proceed with this mediocre password?"))
                                return false;
                        }
                    }


                    // Add the signature from the jqxEditor
                    f['usr[signature]'] = tinymce.activeEditor.getContent();
                    if(f['usr[signature]'].length > 0)
                        formData.push({
                            name: "usr[signature]",
                            value: f['usr[signature]']
                        });

                    // Add the User's ID to the posted data
                    formData.push({
                        name: "user_id",
                        value: self.config.id
                    });

                    return true;
                },
                success: function(x)
                {
                    // when completed successfully, refresh the page
                    if(x.success) {
                        document.location.reload();
                    } else {
                        cargohub.alert(x.reason, "Message from server", "events.png");
                    }
                }
            });

            // Other buttons in User Configuration toolbar
            $('#tab_configure .toolbar button')
                .jqxButton({ theme: cargohub.jqxtheme })
                .on('click', function(event) {
                    switch($(this).data('op')) {
                        // Basic link button
                        case 'link':
                            document.location = $(this).data('link');
                            break;

                        // Saves the user by submitting the form present inside this tab
                        case 'user-save':
                            $('#tab_configure form').submit();
                            break;

                        // Logs in as the user to test the effect of the configuration changes
                        case 'login-as':
                            if(self.config.status == 'disabled') {
                                cargohub.alert("User is currently disabled, not possible to login as the user");
                                break;
                            }

                            if(confirm("Logout as yourself and auto-login as the user?")) {
                                $.post("/manager/user?action=Login_As", {
                                    'id': self.config.id
                                }, function(x) {
                                    document.location = x.redirect; // user's page after login
                                })
                                    .fail(cargohub.its.http_fail);
                            }
                            break;

                        // Deletes this user and associated contact
                        case 'user-del':
                            if(confirm("Are you sure you want to delete this user account?\n\nAll the data that has been associated with this user will be deleted and is not recoverable !")) {
                                $.post("/manager/user?action=delete", {
                                    id: self.config.id
                                }, function(x) {
                                    document.location = x.redirect;
                                })
                                    .fail(cargohub.its.http_fail);
                            }
                            break;
                    }
                    event.preventDefault();
                });

        },

        /**
         * Displays full contact information (with edit ability) - Using "contact_editor" component
         */
        setup_Tab_VCard = function()
        {
            cargohub.components.contact_editor.fields_structure = self.config.vcard_fields_structure;

            // setup toolbar
            $("#tab_vcard .toolbar button")
                .jqxButton({ theme: cargohub.jqxtheme })
                .on('click', function()	{
                    var data = $(this).data();
                    switch(data.op) {
                        case 'tab-configure': // Select second tab ( Managed contacts list )
                            $('#tabs-container').jqxTabs('select', 0);
                            break;

                        case 'vcard-save':
                            // Assambles the date from all the fields which are editable on this tab and does an ajax request with the JSON encoded string

                            var contact_status = cargohub.components.contact_editor.check_Status(), contact = null;
                            if(contact_status.changed && contact_status.valid) {
                                contact = cargohub.components.contact_editor.read_vCard();

                                var update_contact = {
                                    id: self.config.contact.id,
                                    name: contact.name,
                                    email: contact.email,
                                    xdata: JSON.stringify(contact.xdata),
                                    type: "user_self",
                                    user_id: self.config.id
                                };
                                // Only when editing the primary contact...
                                if(self.config.tab_vcard.primary)
                                    update_contact.update_user = 1; // ...This sends a message to update the user entry as well to match the new Name/E-mail

                                // Save the contact changes to the database (or, create the contact draft if it didn't previously exist)
                                $.post("/global/contact?action=save_contact", update_contact, function(x) {
                                    // Update the form elements on first tab, to contain the contact.name, contact.email (in case the tab is loaded, of course, otherwise both these val()'s have no effect whatsoever
                                    // - The "save_contact" procedure should have synchronized the user as well
                                    if(self.config.tab_vcard.primary) {
                                        $("#user-account input#usr_name").val(contact.name);
                                        $("#user-account input#usr_email").val(contact.email);
                                        $('#tabs-container').jqxTabs('select', 0);
                                        setTimeout(function() {
                                            clear_Item_Tab(1);
                                        }, 500);
                                    }

                                    cargohub.alert(x.response, "Success !", "thumb_up.png");
                                })
                                    .fail(cargohub.its.http_fail);
                                ;
                            }
                            break;

                        // Button to select one of the available aliases. Only appears when the user has aliases defined for them
                        case 'vcard-alias':
                            if(cargohub.components.modal.windows['alias-selector'])
                                return cargohub.components.modal.reopen('alias-selector');

                            cargohub.components.modal.create('alias-selector', {
                                btn_roles: {cancel: "cancel"}
                            });

                            cargohub.components.modal.open('alias-selector', {
                                width: 500,	height: 270,
                                title: "Select the Alias to edit",
                                content: $('#win-select_alias').html(),
                                callback: function($window) // Setup the controls inside the window
                                {
                                    // The one and only drag+checkboxes control that exists in the software :-)
                                    $("#alias-selector", $window).jqxListBox({
                                        theme: cargohub.jqxtheme,
                                        source: self.config.tab_vcard.aliases,
                                        width: 475, height: 170,
                                        displayMember: 'name', valueMember: 'id',
                                        renderer: function(index, label, value) {
                                            return '<span class="gray right">'+self.config.tab_vcard.aliases[index].email+'</span>'+ label;
                                        }
                                    });

                                    // Autoselect the current loaded alias
                                    if(self.config.tab_vcard.alias_index!==null)
                                        $("#alias-selector", $window).jqxListBox('selectIndex', self.config.tab_vcard.alias_index);

                                    // Setup the action for "OK" button (start with it disabled, enabled when user has been selected)
                                    $('#modal_alias-selector_ok', $window).off('click').on('click', function()
                                    {
                                        self.config.tab_vcard.alias_index = $("#alias-selector", $window).jqxListBox('getSelectedIndex');
                                        self.config.contact.id = $("#alias-selector", $window).val();

                                        $window.jqxWindow('close');

                                        // Reloads the tab using the new contact ID, instead of the default / primary
//							$('#tabs-container').jqxTabs('setContentAt', 1, '<div style="text-align: center; padding: 30px;"><img src="/img/extra/loader3.gif" alt="Loading..." /></div>'); // clears content

                                        clear_Item_Tab(1);
                                        $.get("?action=load_tab_html", {
                                            tab: "tab_vcard",
                                            user_id: self.config.id,
                                            contact_id: self.config.contact.id
                                        }, function(tab_contents, textStatus, jqXHR) {
                                            // Regenerate the tab, since HTML has been cleared and reloaded
                                            donrey.read_JSON_Headers(jqXHR, self.config);
                                            $('#tab_vcard').html(tab_contents);
                                            setup_Tab_VCard();
                                        })
                                            .fail(cargohub.its.http_fail);
                                    });
                                }
                            });
                            break;

                        // Basic link button
                        case 'link':
                            document.location = $(this).data('link');
                            break;
                    }
                });


            // Begin by having the save button disabled, it will be reenabled after a valid change.
            $("#tab_vcard button#contact-save-button").jqxButton({disabled: true}).jqxButton('render');

            cargohub.components.contact_editor.events = {
                changed: function(valid) {
                    if(valid)
                        $("div#tab_vcard button#contact-save-button").jqxButton({disabled: false}).jqxButton('render');
                    else
                        $("div#tab_vcard button#contact-save-button").jqxButton({disabled: true}).jqxButton('render');
                }
            };

            cargohub.components.contact_editor.setup({
                container: $("#tab_vcard #contact-left-side")
            }, self);

            // TODO: Integrate this into the Contact Editor component, so it can be used everywhere without duplication

            // SMTP settings attached to contact editor
            var $smtp_form = $("form#smtp_settings"), current_send_mtd = null;
            // The ON/OFF radio buttons (show/hide the rest of the form)
            $("#smtp_enable input", $smtp_form)
                .on('change', function(e) {
                    current_send_mtd = $(this).val();

                    if($(this).val() == 'smtp') {
                        $("tbody#smtp_off", $smtp_form).hide();
                        $("tbody#smtp_active", $smtp_form).fadeIn();
                    } else {
                        $("tbody#smtp_active", $smtp_form).fadeOut(function() {
                            $("tbody#smtp_off", $smtp_form).show();
                        });
                    }
                });

            // Automate server settings
            $("#auto_provider", $smtp_form).on('change', function(e) {
                var option = $(e.target).val(), settings = {};
                switch(option) {
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
                        if(!confirm("Are you sure you want to clear your settings?"))
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
                $("#smtp_encryption input[value="+settings.smtp_encryption+"]", $smtp_form).prop("checked", true);
                $("#smtp_port", $smtp_form).val(settings.port);
                $("select[name='smtp[smtp_auth]']", $smtp_form).val(settings.smtp_auth);
            });

            // Secure channel radio buttons will modify the Port field under certain conditions
            $("#smtp_encryption input", $smtp_form)
                .on('change', function(e) {
                    var auto_port = {
                        "tls": 587,
                        "ssl": 465,
                        "none": 25
                    }, current_port = $("#smtp_port", $smtp_form).val();

                    if(!current_port || current_port == 587 || current_port == 465 || current_port == 25)
                        $("#smtp_port", $smtp_form).val(auto_port[$(this).val()]);
                });

            // Test button submits the form via Ajax
            $("#smtp_active input[type=button]", $smtp_form)
                .jqxButton({ theme: cargohub.jqxtheme })
                .on('click', function() {
                    $smtp_form.ajaxSubmit({
                        dataType: 'json',
                        data: {
                            contact_id: self.config.contact.id,
                            user_id: self.config.id // User owning that contact, usually the logged in user. But if admin does the editing, the user_id will be different
                        },
                        beforeSubmit: function(formData, jqForm, options) {
                            // setup the field checking object
                            var f = {};
                            for(var c=0; c< formData.length; c++)
                                f[formData[c].name] = formData[c].value;

                            if(f['use_smtp'] == '0')
                                return cargohub.alert("There is nothing to test if you're not using SMTP!");

                            if(f['smtp[host]'].length < 3)
                                return cargohub.alert("Please enter a server address for your SMTP settings");

                            // Check if Auth model is anything other than "anon", username/password must be present
                            if(f['smtp[smtp_auth]'] != 'anon') {
                                if(f['smtp[username]'].length == 0 || f['smtp[password]'].length == 0)
                                    return cargohub.alert("Your selected authentication method requires username and password to be filled in");
                            }

                            // Check if PORT is not empty. Also, if Secure channel is TLS or SSL, port "25" is not accepted as an option
                            if(f['smtp[port]'].length == 0)
                                return cargohub.alert("Please set your connection port !");

                            if(f['smtp[smtp_encryption]'] != 'none' && f['smtp[port]'] == '25')
                                return cargohub.alert("When the channel is secured, port 25 is not accepted");

                            // Show the spinner
                            cargohub.components.spinner.Off();
                            cargohub.components.spinner.On("Connecting...", "Talking with server", null, null, 500);
                            return true;
                        },
                        success: function(x) {
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
                .jqxButton({ theme: cargohub.jqxtheme })
                .on('click', function() {
                    // Send a message that SMTP for current account should be temporarely deactivated (settings will remain valid)
                    $.post("/global/contact?action=Sending_Settings", {
                        contact_id: self.config.contact.id,
                        send_mtd: current_send_mtd
                    }, function(x) {
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
         * The ACLs tab, used to edit user level overrides for privileges and other access lists
         */
        setup_Tab_ACLs = function(usr)
        {
            if(self.config.acls_overrides) {
                $('#tab_acls #overrides_on').show();
                $("#tab_acls .toolbar button[data-op='acls-clear']").show();
            } else
                $('#tab_acls #overrides_off').show();

            cargohub.components.acls_editor.setup({
                container: $("#tab_acls"),
                acls: self.config.acls
            }, self);

            // Button to save ACL data
            $('#tab_acls .toolbar button')
                .jqxButton({ theme: cargohub.jqxtheme })
                .on('click', function() {
                    switch($(this).data('op')) {
                        // Saves the modified ACL data
                        case 'acls-submit':
                            $.post("?action=update_acls&user_id=" + self.config.id + (self.config.cdep_id > 0? ("&dep_id=" + self.config.cdep_id) :""), {
                                acls: JSON.stringify(cargohub.components.acls_editor.read())
                            }, function(x) {
                                if(x.success)
                                    document.location.reload();
                                else {
                                    cargohub.alert(x.reason, "Message from server", "events.png");
                                }
                            });
                            break;

                        // Remove any overrides done at the user level ( so the user reverts to the organization level when he is part of this department )
                        case 'acls-clear':
                            if(!confirm("Are you sure you want to remove overrides for this user ?"))
                                return;

                            $.post("?action=update_acls&user_id=" + self.config.id + (self.config.cdep_id > 0? ("&dep_id=" + self.config.cdep_id) :""), {
                                clear: true
                            }, function(x) {
                                if(x.success){
//                                    document.location.reload();
                                }
                                else {
                                    cargohub.alert(x.reason, "Message from server", "events.png");
                                }
                            });
                            break;
                    }
                });
        },

        /**
         * The Assignments tab (TODO)
         */
        setup_Tab_Assignments = function()
        {

        },

        /**
         * The Profile Records tab (Admin only), shows the saved profile records which are stored in the UPR table.
         * Allows removing a record (reverting to default), or using that specific record AS the user's organization default
         */
        setup_Tab_Profile_Records = function()
        {
            var selected_profile = null,
                // the data definition
                gridDataFields = [
                    {name: 'pkey'},					// Not visible directly in the grid, but used for copying
                    {name: 'ptype', type: 'string'},// One of "Grid columns", "Grid settings", "Panels", "Recent Records", "Preferences" - Others might be added later
                    {name: 'pin', type: 'string'},	// Record type ( former Issue type ), applies to lots of keys (grid columns, sentings and overview panels) . For others: (doesn't apply)
                    {name: 'pik', type: 'string'},	// Record type key ( former Issue type ) - Used for the record type icon
                    {name: 'variant', type: 'int'},
                    {name: 'source'},
                    {name: 'pname'},
                    {name: 'utime', type: 'date'}
                ], ist_list = function() {
                    var list = ["(Doesn't apply)"], c = 0;
                    for(; c< self.config.ist_source.length; c++) {
                        list.push(self.config.ist_source[c].name);
                    }
                    return list;
                }(),
                // the column definition
                gridColumns = [
//			{text: "Profile Key", dataField: 'pkey', width: 220},	// not human friendly
                    {text: "Type", dataField: 'ptype', width: 150, filtertype: 'checkedlist'},
                    {text: "Record type", dataField: 'pin', width: 240,
                        filtertype: "checkedlist", filteritems: ist_list,
                        createfilterwidget: function (column, columnElement, widget) {
                            widget.jqxDropDownList({
                                renderer: function (index, label, value) {
                                    return "<div>"+ (index>1?"<img style='float: left; margin-right: 4px;' src='/img/icons16/ist/"+self.config.ist_source[index-2].key+".png' />" + value: value) + "</div>";
                                },
                                animationType: "fade"
                            });
                        },
                        cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
                            return "<div style='text-overflow: ellipsis; overflow: hidden; margin-top: 5px;' title='"+ value +"'>"+ (listGridAdapter.records[row].pik? "<img src='/img/icons16/ist/" + listGridAdapter.records[row].pik + ".png' style='margin: 0px 4px;' />": "<span style='display: inline-block; width: 22px;'></span> ") +"<span style='position: absolute;'>" + value + "</span></div>";
                        }
                    },
                    {text: "Variant", dataField: 'variant', width: 70, filtertype: 'number', align: 'center', cellsalign: 'center'},
                    {text: "Source", dataField: 'source', width: 80, filtertype: "checkedlist", filteritems: ['default', 'user', 'autofix', 'copy'], align: 'center', cellsalign: 'center',
                        createfilterwidget: function (column, columnElement, widget) {
                            widget.jqxDropDownList({
                                dropDownWidth: 120,
                                autoDropDownHeight: true,
                                animationType: "fade"
                            });
                        }
                    },
                    {text: "Profile Record Name/Description", dataField: 'pname'},
                    {text: "Last update", dataField: 'utime', width: 100, filtertype: "range", cellsformat: cargohub.date_format, align: "center", cellsalign: "center",
                        createfilterwidget: function (column, columnElement, widget) {
                            widget.jqxDateTimeInput({
                                animationType: "fade",
                                dropDownHorizontalAlignment: "right"
                            });
                        }
                    }
                ],
                // prepare the data
                listGridAdapter = new $.jqx.dataAdapter({
                    url: '?action=get_profile_records',
                    data: {
                        user_id: self.config.id
                    },
                    datatype: "json",
                    datafields: gridDataFields,
                    // Initial Sorting
                    sortcolumn: 'utime',
                    sortdirection: 'desc'
                });

            // ... and init the grid
            $("#tab_profile_records #records_grid").jqxGrid({
                theme: cargohub.jqxtheme,
                source: listGridAdapter,

                width: cargohub.fullwidth - 14, autoheight: true,
                columns: gridColumns, columnsmenu: false,
                selectionmode: "singlerow",	altrows: true,
                pageable: true,	pagesize: 20, pagesizeoptions: [10, 20, 50, 100],
                filterable: true, showfilterrow: true, sortable: true
            })
                /**
                 * Event: When a row is selected, the name of the organization is displayed on the toolbar, and is show with fade in (when selecting a row first time)
                 */
                .on('rowselect', function (event)
                {
                    var row = event.args.row;
                    $("#tab_profile_records div.selected-item").html(row.pkey);
                    if(!selected_profile) {
                        $("#profile_edit_operations button").jqxButton({disabled: false});
                        donrey.cancel_Delay('hide_bar_edit_ops');

                        $('#profile_edit_operations').fadeIn();
                    }

                    selected_profile = row;
                })
                /**
                 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
                 */
                .on('filter', function(event)
                {
                    if(selected_profile) {
                        $(this).jqxGrid('clearselection');
                        selected_profile = null;
                        $("#tab_profile_records div.selected-item").empty();
                        $("#profile_edit_operations button").jqxButton({disabled: true});

                        donrey.single_Delay('hide_bar_edit_ops', function() {
                            $("#profile_edit_operations").fadeOut();
                        }, 500);
                    }
                })
                /**
                 * Event: On doubleclicking, do the same as simple clicking then using the View/Edit button
                 */
                .on('rowdoubleclick', function(){
                    $('#tab_profile_records .toolbar button[data-op="view"]').trigger("click");
                });

            // Tab's toolbar commands
            $('#tab_profile_records .toolbar button')
                .jqxButton({ theme: cargohub.jqxtheme })
                .on('click', function() {
                    switch($(this).data('op')) {
                        // Views the profile data ( PHP var-export ? )
                        case 'view':
                            if(!popups.viewdata) {
                                cargohub.its.init_Modal_Window('profile-view', {"ok": "Ok"});
                                popups.viewdata = true;
                            }

                            cargohub.its.open_Modal_Window('profile-view', 640, 520, "View profile record data", $("#template-profile-record").html().present(selected_profile), function($window) {
                                // Load the information into a read-only textarea that is the entire content of the window
                                $window.find("textarea").load("?action=load_profile_data", {
                                    user_id: self.config.id,
                                    pkey: selected_profile.pkey,
                                    variant: selected_profile.variant
                                });
                            });
                            break;

                        // Opens a simple prompt allowing the description to be edited/customized (Note that descriptions are autogenerated by certain profile operations)
                        case 'edit-descr':
                            var pname = prompt("Edit profile record name/description", selected_profile.pname? selected_profile.pname: "");
                            if(pname !== null) {
                                // There was a return, save the new name
                                $.post("?action=update_profile_pname", {
                                    user_id: self.config.id,
                                    pkey: selected_profile.pkey,
                                    variant: selected_profile.variant,
                                    pname: pname
                                }, function(x) {
                                    if(x.success) {
                                        $("#tab_profile_records #records_grid").jqxGrid('updatebounddata');
                                        cargohub.alert(x.response, "Success !", "thumb_up.png");
                                        selected_profile.pname = pname;
                                    } else {
                                        cargohub.alert(x.reason, "Message from server", "events.png");
                                    }
                                });
                            }
                            break;

                        // Resets the selected profile by deleting it (at next read, it will auto-default to the organization default, if one exists)
                        case 'reset':
                            if(!confirm("Are you sure you want to remove (or reset) this entry ?\nThe user will revert to the organization default (if it exists)"))
                                return;

                            // NOTE: All variants are destroyed when resetting !
                            $.post("?action=reset_profile", {
                                user_id: self.config.id,
                                pkey: selected_profile.pkey,
                                variant: selected_profile.variant
                            }, function(x) {
                                if(x.success) {
                                    $("#tab_profile_records #records_grid").jqxGrid('updatebounddata');
                                    cargohub.alert(x.response, "Success !", "thumb_up.png");
                                } else {
                                    cargohub.alert(x.reason, "Message from server", "events.png");
                                }
                            });
                            break;

                        // Copies (and replaces) the selected profile into the user's organization default ( all other users that haven't customized their entry will inherit this profile setting )
                        case 'set_default':
                            if(!confirm("Are you sure you want to set the default for this user's organization to the values present in this record ?\nThis will become the default for all new users in the organization, and also the default when users reset their configuration."))
                                return;

                            $.post("?action=set_default_profile", {
                                user_id: self.config.id,
                                org_id: self.config.org_id,
                                pkey: selected_profile.pkey,
                                variant: selected_profile.variant
                            }, function(x) {
                                if(x.success) {
                                    $("#tab_profile_records #records_grid").jqxGrid('updatebounddata');
                                    cargohub.alert(x.response, "Success !", "thumb_up.png");
                                } else {
                                    cargohub.alert(x.reason, "Message from server", "events.png");
                                }
                            });
                            break;
                    }
                });
        },

        /**
         * Setup the tab-navigation interface used in this module
         * TODO: Convert this to HashNav component when possible
         */
        setup_Tabs = function()
        {
            var disable_onhashchange = true, // Contains the list of tabs that have already been loaded and initialized
                selected_tab_idx = 0, selected_tab = 'tab_configure';

            // discover if a subtab is specified in the URL ( to jump directly to it )
            c = 0;
            $('#tabs-container > div').each(function() {
                if(document.location.hash.substr(1) == $(this).prop('id').substr(4)) {
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
                 * Event: When selecting a tab for the first time, it is loaded from the server
                 */
                .on('selected', function(event, auto_trigger) {
                    cargohub.application.reset_Session_Timer();
                    disable_onhashchange = true;

                    if(!auto_trigger)
                        selected_tab = $('#tabs-container').jqxTabs('getContentAt', event.args.item).attr('id');
                    document.location.hash = selected_tab.substr(4);

                    if(!loaded_tabs[selected_tab]) {
                        // Load the tab content (and optionally associated code - which might need data from the loaded content). When everything is ready, show the tab's HTML and initialize javascript
                        $.get("?action=load_tab_html&tab=" + selected_tab + "&user_id=" + self.config.id + (self.config.cdep_id > 0? ("&dep_id=" + self.config.cdep_id) :""), function(tab_contents, textStatus, jqXHR)
                        {
                            // Load extra data for this tab, if it exists, and store it in the local object
                            donrey.read_JSON_Headers(jqXHR, self.config);

                            switch(selected_tab) {
                                case 'tab_configure':
                                    $('#' + selected_tab).html(tab_contents);
                                    setup_Tab_Configure();
                                    break;

                                case 'tab_vcard':
                                    $('#' + selected_tab).html(tab_contents);
                                    setup_Tab_VCard();
                                    break;

                                case 'tab_acls':
                                    if(!cargohub.application.user_Test_Privileges('org_access_manager'))
                                        break;

                                    // This tab needs a component
                                    donrey.load_Components("acls_editor", function() {
                                        // only after both the tab contents and sub-modules are loaded, initialize the tab
                                        $('#' + selected_tab).html(tab_contents);
                                        setup_Tab_ACLs();

                                        // Special condition: If the user doesn't have an active department, disable the tab and switch to first tab (configure)
                                        // However, the editor should still be enabled, as an active adepartment might be selected
                                        if(self.config.cdep_id == 0) {
                                            $('#tabs-container').jqxTabs('select', 0);
                                            $('#tabs-container').jqxTabs('disableAt', 2);
                                        }
                                    }, "?v="+cargohub.version);
                                    break;

                                case 'tab_assignments':
                                    $('#' + selected_tab).html(tab_contents);
                                    setup_Tab_Assignments();
                                    break;

                                // User's stored profile records
                                case 'tab_profile_records':
                                    $('#' + selected_tab).html(tab_contents);
                                    setup_Tab_Profile_Records();
                                    break;
                            }
                        });
                        // set the tab as initialized (so next time it's selected there's no need to reload it)
                        loaded_tabs[selected_tab] = true;
                    }

                    setTimeout(function() {
                        disable_onhashchange = false;
                    }, 50);
                });

            // Trigger the selected event for the initial tab
            $('#tabs-container').trigger('selected', true);

            // hash-based navigation inside tabs themselves - Visually select tab when hash changes by typing in the URL or using back/forward (not by clicking on the tab itself)
            window.onhashchange = function(event) {
                // hash changes as well when the user is selecting the tab manually, in those cases there's no need to find and reselect the tab again !
                if(disable_onhashchange)
                    return;

                c = 0;
                $('#tabs-container div.jqx-tabs-content > div').each(function() {
                    if(location.hash.substr(1) == $(this).prop('id').substr(4))
                        selected_tab_idx = c;
                    c++;
                });
                $('#tabs-container').jqxTabs('select', selected_tab_idx);
            };
        }
    ;

    this._public({
        // module initialization
        init: function()
        {
            // setup the tab-navigation interface used in this module
            setup_Tabs();
        }
    });

});