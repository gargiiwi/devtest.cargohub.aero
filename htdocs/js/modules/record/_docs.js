/**
 * Module "Record Process"->Section "Documents", interface for operating integrated documents (database stored Rich-Text notes)
 * 
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules.Record Process
 * @version 2020.06.18
 */
cargohub.record_docs = donrey.BASE._extend(function () {
    // private
    var self = this,
            c = null,
            today = moment().set({hour: 0, minute: 0, second: 0, millisecond: 0}),
            master = cargohub.record_process,
            // Manage restricted Notes or Docs - Ability to edit or delete documents which have been set to private/department only (this is an override)
            docs_manager_priv = cargohub.application.user_Test_Privileges('issue_edit_notes'),
            // Necessary to determine who is operating the component
            user_session = cargohub.application.user_Get_Session(),
            // Data from the table containing currently selected row. Used for opening the conversation and for Reply/Forward/Trash - Set on any row operation.
            selected_row = null, // A row is selected - There should be visible buttons: Open/ Duplicate/ Delete
            doc_editor_mode='', //IWI doc tab changes 2072028

            // Source for the DataTable 
            docs_Source = new $.jqx.dataAdapter({
                url: "/global/note", // At the moment using notes backend - That might change in the future !
                data: {
                    action: "Get_List", 
                    link: "issue",
                    link_id: master.config.id,
                    content_type: "html",
                    order: "`nte`.id ASC, `nte`.version DESC"
                },
                datatype: "json",
                datafields: [
                    {name: 'wv_key', type: 'string'},
                    {name: 'wv_secure', type: 'number'},
                    {name: 'wv_publish', type: 'number'},
                    {name: 'wv_publish_for', type: 'string'},
                    {name: 'wv_publish_for_ids', type: 'string'},
                    {name: 'name', type: 'string', map: 'title'}, // Originally called "title" on notes in the database
                    {name: 'description', type: 'string'},
                    {name: 'version', type: 'numberinput'},
                    {name: 'dt', type: 'date'},
                    {name: 'editor_id', type: 'int', map: 'editor>id'},
                    {name: 'editor_name', type: 'string', map: 'editor>name'},
                    {name: 'org_id', type: 'int', map: (cargohub.application.user_Is_Admin() ? 'org>id' : 'org_id')},
                    {name: 'org_name', type: 'string', map: 'org>name'},
                    {name: 'dep_id', type: 'int'},
                    {name: 'visibility', type: 'string'},
                    {name: 'edit_access', type: 'string'},
                    {name: 'published_by', type: 'string'}, //IWI doc seen & sign
                    {name: 'created_by', type: 'string'}, //IWI doc seen & sign
                    {name: 'last_updated', type: 'string'}, //IWI doc seen & sign
                    {name: 'is_required_read_sign', type: 'number'}, //IWI Refresh after days 
                    {name: 'refresh_after_days', type: 'number'}, //IWI Refresh after days 
                    {name: 'is_notification', type: 'number'}, //IWI Create section for Notification
                    {name: 'notification_days', type: 'number'}, //IWI Create section for Notification
                    {name: 'ref_number', type: 'string'}, //IWI Create section for Notification
                ],
                id: "id",
                // Initial Sorting
//                sortcolumn: 'version',
//                sortdirection: 'desc'
              sortcolumn: 'last_updated',
             sortdirection: 'desc'
            }),
            /**
             * A row has been selected - Store information in preparation for a double-click ( for Viewing or Editing ), or for clicking on one of the toolbar buttons
             */
            select_Row = function (row)
            {
                var edit_access = docs_manager_priv ||
                        (row.edit_access == 'shared'
                                || (row.edit_access == 'org' && (row.visibility == 'dep' || row.visibility == 'org'))
                                || (row.edit_access == 'dep' && row.dep_id == user_session.cdep_id)
                                || (row.edit_access == 'user' && row.editor_id == user_session.id)
                                );

                // If no row was previously selected, show the available commands
                if (!selected_row) {
                    donrey.cancel_Delay('hide-docs-row-ops');
                    $('#tab_docs #docs-row-ops button').jqxButton({disabled: false});
                    $('#tab_docs #docs-row-ops').fadeIn();
                }
                selected_row = row;

                // Show or hide the "Delete" button
                if (edit_access)
                    $('#tab_docs .docs-write-ops').fadeIn();
                else
                    $('#tab_docs .docs-write-ops').fadeOut();

                // Show or hide the Web-View section (if the document has web-view enabled or not)
                if (row.wv_key)
                    $('#tab_docs #docs-open-webview').show();
                else
                    $('#tab_docs #docs-open-webview').hide();
            },
            /**
             * Event: Table selection is cleared - Make the toolbar buttons dissapear
             */
            select_Clear = function ()
            {
                $('#tab_docs #docs-row-ops button').jqxButton({disabled: true});

                donrey.single_Delay('hide-docs-row-ops', function () {
                    $('#tab_docs #docs-row-ops').fadeOut();
                }, 500);

                selected_row = null;
            },
            // Document editor area (expander) variables:
            $doc_editor = null, // Expander containing various controls, including TinyMCE "rich_editor"
            editor_open = false, // Non-false when Editor is open (in either Create or Edit modes) - Save button should be visible
            open_row_id = null, // ID of the row that has been opened (selected one might have changed in the mean time) - Only has a value when opening existing row (not for create/duplicate)
            editor_nonempty = false, // Editor is open and non non-empty, show a warning when trying to open another row or select a template
            // Indicates if TinyMCE component is already loaded and the editor used here is initialized
            rich_editor_ready = false,
            rich_editor_instance_key = "docedit",
            $doc_viewer = $("#tab_docs #document-viewer"),
            docs_viewer_Ready = false,
            viewer_open = false, // True when viewer is expanded (must be closed when switching to Editor)

            // Visibility selector TODO: Get the texts from Language files
            doc_Visibility_Source = [
                {k: 'org', text: '<span class="access16 visi-org"></span>Organization members with access to the record'},
////		{k: 'private', text: '<span class="access16 visi-private"></span> Private document, only visible to the editor'},
////		{k: 'dep', text: '<span class="access16 visi-dep"></span>Only the editor\'s department members have access'},
////		{k: 'shared', text: '<span class="access16 visi-shared"></span>Users from organizations sharing this '+master.config.type.name},
////		{k: 'public', text: '<span class="access16 visi-public"></span>No restrictions (use for public Web-view)'}
            ],
           
//            IWI Refresh after days start
             doc_refresh_after_days_Source = [
                {k: '0', text: 'One Time'},
                {k: '30', text: 'Monthly'},
                {k: '90', text: 'Quarterly'},
                {k: '180', text: 'Bi Annually'},
                {k: '365', text: 'Yearly'},
            ],
//            IWI Refresh after days end
            
            
            // Edit access selector TODO: Get the texts from Language files
            doc_Edit_access_Source = [
                {k: 'user', text: '<span class="access16 edit-user"></span>Only the editor can change or delete this document'},
                {k: 'dep', text: '<span class="access16 edit-dep"></span>All members of the editors\'s department can edit'},
                {k: 'org', text: '<span class="access16 edit-org"></span>Anyone in the organization can modify the document'},
                {k: 'shared', text: '<span class="access16 edit-shared"></span>Anyone with access to the parent record is an editor'}
            ],
            // Source for the template selector in document create
            templates_Source = new $.jqx.dataAdapter({
                url: "/global/operations?action=Get_Templates",
                data: {
                    role: "print", // "print" templates used as document source templates
                    include_default: false,
                    rt_id: (master.config.type.base_id && master.config.type.base_id != master.config.type.id) ? master.config.type.base_id : master.config.type.real_id
                },
                datatype: "json",
                datafields: [
                    {name: 'id', type: 'int'},
                    {name: 'description', type: 'string'}
                ],
                id: "id"
            }),
            
//            IWI doc section file upload start
            doc_Attach_Multi_File_Source = new $.jqx.dataAdapter({
                url: "/global/note?action=Get_Attach_File_Source",
                data: {
                    link_id: master.config.id
                },
                datatype: "json",
                datafields: [
                    {name: 'file_id', type: 'int'},
                    {name: 'file_desc', type: 'string'}
                ],
                id: "id"
            }),
            selectedAttachIds = [],
            $combo = $('#doc-attach-multi-file', $doc_editor),
            defaultText = '0 selected',
            genarate_jqxComboBox = () => {
                $combo.jqxComboBox({
                    theme: cargohub.jqxtheme,
                    source: doc_Attach_Multi_File_Source,
                    displayMember: "file_desc",
                    valueMember: "file_id",
                    width: 226,
                    height: 27,
                    checkboxes: true,
                    // autoDropDownHeight: true,
                    dropDownHeight: 200,
                    animationType: "fade",
                    placeHolder: ' Select attachments...'
                });
            },
            preSelectAttachments = (ids = []) => {
               // console.log('preSelectAttachments',ids);
                const combo = $('#doc-attach-multi-file', $doc_editor);
                const allItems = combo.jqxComboBox('getItems');

                if (!allItems || allItems.length === 0)
                    return;

                // Clear existing selections
                allItems.forEach(item => combo.jqxComboBox('uncheckItem', item.value));

                // Select new ones
                if (Array.isArray(ids) && ids.length > 0) {
                  //  console.log('preSelectAttachments if ids');
                    ids.forEach(id => combo.jqxComboBox('checkItem', id));
                    combo.jqxComboBox('input').val(`${ids.length} selected`);
                } else {
                   // console.log('preSelectAttachments if not ids');
                    combo.jqxComboBox('input').val('Select attachments...');
                }
            },


//            IWI doc section file upload end
            /**
             * Loads selected document "body" into the existing editor. If currently no body, just use the editor with a blank result
             */
            load_Body = function ()
            {
                $('#file-input').val('');//IWI doc section file upload
                $('#file-info').text('');//IWI doc section file upload
                $.get('/global/note', {
                    action: "Load_Body",
                    id: open_row_id,
                    content_type: 'html'
                }, function (body) {
                    if (body.length > 0)
                        master.rich_editor.content_Write(rich_editor_instance_key, body);
                    else
                        master.rich_editor.content_Write(rich_editor_instance_key, "\n");
                });
//                IWI doc section file upload start
                $.get('/global/note', {
                    action: "load_AttachFiles",
                    note_id: open_row_id,
                    content_type: 'json'
                }, function (body) {
                    var getData = $.parseJSON(body);
                    // console.log(getData);
                    selectedAttachIds = getData;

                    const combo = $('#doc-attach-multi-file', $doc_editor);

                    // Wait for data to load, then clear and reselect
                    combo.off('bindingComplete.loadBody').on('bindingComplete.loadBody', function () {
                        preSelectAttachments(selectedAttachIds);
                    });

                    // Rebind data to force reload and trigger selection
                    doc_Attach_Multi_File_Source.dataBind();
                });
//                IWI doc section file upload end

                $.get('/global/note', {
                    action: "Load_Data",
                    id: open_row_id,
                    content_type: 'json'
                }, function (body) {
                    var getData = $.parseJSON(body);
//                    console.log('sdfdsfdf',getData);

// IWI Refresh after days start 
//                    if (getData.is_required_read_sign == '1') {
//                        $("input[name=doc_read_sign]").prop('checked', true);
//                    } else {
//                        $("input[name=doc_read_sign]").prop('checked', false);
//                    }
                    
                    
//IWI Refresh after days end
                });
                
                
                
            },
            /**
             * Setup the document editor expander, placed above the grid
             *
             * @param config Information now how the editor area is initialized
             */
            init_Editor = function (config)
            {
                var row_data = $.extend({}, selected_row);

                if ($doc_editor) {
                    // If already initialized just change jqxExpander according to config
                    if (config && config.expander)
                        $doc_editor.jqxExpander(config.expander);
                } else {
                    // Otherwise, start full initialization
                    $doc_editor = $("#tab_docs #document-editor");
                    if (!config.expander)
                        config.expander = {
                            disabled: false,
                            expanded: false
                        };
                    config.expander.theme = cargohub.jqxtheme;
                    $doc_editor.jqxExpander(config.expander);

                    // Since the HTML inside the expander is permanent, initialize widgets:
                    


//IWI doc section file upload start

//                    const $combo = $('#doc-attach-multi-file', $doc_editor);
//                    const defaultText = '0 selected';

// Init ComboBox
                    genarate_jqxComboBox();
//                    preSelectAttachments([]);

// Function to get and apply selected count
                    function updateSelectedCount() {
                        const selectedItems = $combo.jqxComboBox('getCheckedItems');
                        const label = selectedItems.length > 0
                                ? `${selectedItems.length} selected`
                                : defaultText;

                        // Force update input field
                        const input = $combo.find('.jqx-combobox-input')[0];
                        if (input)
                            input.value = label;

                        // Log selected file_ids
                         selectedAttachIds = selectedItems.map(item => item.value);
                       // console.log('Selected file_ids:', selectedAttachIds);
                    }

// Attach checkChange
                    $combo.on('checkChange', updateSelectedCount);

// Also observe mutations in case jqx re-renders input
                    const input = $combo.find('.jqx-combobox-input')[0];
                    if (input) {
                        const observer = new MutationObserver(() => {
                            updateSelectedCount();
                        });

                        observer.observe(input, {
                            attributes: true,
                            attributeFilter: ['value']
                        });
                    }



//IWI doc section file upload end

                    // Visibility selector
                    $('#doc-visibility', $doc_editor).jqxDropDownList({
                        theme: cargohub.jqxtheme,
                        source: doc_Visibility_Source,
                        displayMember: "text", valueMember: "k",
                         height: 24,width: 398, autoDropDownHeight: true,
                        animationType: "fade"
                    });
                    setTimeout(function() {
$('#doc-visibility', $doc_editor).jqxDropDownList('selectIndex', 0);
}, 100); 


//                    IWI Refresh after days start
                    $('#refresh_after_days', $doc_editor).jqxDropDownList({
//                        theme: cargohub.jqxtheme,
                        source: doc_refresh_after_days_Source,
                        displayMember: "text", valueMember: "k",
                        width: 200, height: 24, autoDropDownHeight: true,
                        animationType: "fade"
                    });
                    
//                    IWI Refresh after days end
                    
                    
                    
                    
                    // Webview toggle
                    $("#doc-webview-state", $doc_editor).jqxSwitchButton({
                        theme: cargohub.jqxtheme,
                        height: 25, width: 75
                    }).on('change', function (event) {
                                // Check ALL if "event.args.check" is true, and revert to the currently selected deps ( or none selected? ) if false
                                if (event.args.check) {
                                    $(".doc-webview-info", $doc_editor).show();
//                                     setTimeout(function () {
//                                     $("#doc-webview-required-read-sign", $doc_editor).jqxSwitchButton('check');
//                                     $('#refresh_after_days', $doc_editor).jqxDropDownList('selectIndex', 0);
//                                     }, 100);
//                                     console.log('doc_editor_mode',doc_editor_mode); 
// //                                     IWI doc tab changes 2072028 start
//                                         if (doc_editor_mode === 'doc-create') {
//                                             $("#pulish_link").hide();
//                                         }else{
//                                             $("#pulish_link").show();
//                                         }
                                        
// //                                        IWI doc tab changes 2072028 end
//                                  IWI Refresh after days start
//                                    setTimeout(function () {
//                                        $("#doc_read_sign", $doc_editor).jqxSwitchButton('check');
//                                    }, 100);
//                                  IWI Refresh after days end
                                } else {
                                    $(".doc-webview-info", $doc_editor).hide();
//                                  IWI Refresh after days start

//                                    setTimeout(function () {
//                                        $("#doc_read_sign", $doc_editor).jqxSwitchButton('uncheck');
//                                    }, 50);
//                                  IWI Refresh after days end
                                }
                            });

                    // Webview secure mode toggle
                    $("#doc-webview-secure", $doc_editor).jqxSwitchButton({
                        theme: cargohub.jqxtheme,
                        height: 25, width: 75
                    });
                    
                    
//                   IWI Create section for Notification start
                    $("#doc-webview-notification", $doc_editor).jqxSwitchButton({
                        theme: cargohub.jqxtheme,
                        height: 25, width: 75
                    }).on('change', function (event) {
                        // Check ALL if "event.args.check" is true, and revert to the currently selected deps ( or none selected? ) if false
                        if (event.args.check) {
                            $(".doc-webview-document", $doc_editor).hide();
                            $(".doc-webview-notification_sec", $doc_editor).show();
                            setTimeout(function () {
                                $('input[name="publish_for"][value="department"]').prop('checked', true);
                                 $('.pfdsc').css({"display": "block"});
                            }, 100);
                            
                            

                        } else {
                            $(".doc-webview-notification_sec", $doc_editor).hide();
                            $(".doc-webview-document", $doc_editor).show();
                        }
                    });
//                    IWI Create section for Notification end


                    
//                   IWI read and sign required start
                    $("#doc-webview-required-read-sign", $doc_editor).jqxSwitchButton({
                        theme: cargohub.jqxtheme,
                        height: 25, width: 75
                    }).on('change', function (event) {
                        // Check ALL if "event.args.check" is true, and revert to the currently selected deps ( or none selected? ) if false
                        if (event.args.check) {
                            $(".repeat-read-sign-sec", $doc_editor).show();
                        } else {
                            $(".repeat-read-sign-sec", $doc_editor).hide();
                        }
                    });
//                    IWI read and sign required end





                    // Publich secure mode toggle
//			$("#doc-publish-secure", $doc_editor).jqxSwitchButton({
//				theme: cargohub.jqxtheme,
//				height: 25, width: 75
//			});

                    // Editing access selector
                    $('#doc-edit_access', $doc_editor).jqxDropDownList({
                        theme: cargohub.jqxtheme,
                        source: doc_Edit_access_Source,
                        displayMember: "text", valueMember: "k",
                        width: 400, height: 24, autoDropDownHeight: true,
                        animationType: "fade"
                    });

                    // Template selector (only in create mode)
                    $("#doc-template", $doc_editor).jqxDropDownList({
                        theme: cargohub.jqxtheme,
                        source: templates_Source,
                        displayMember: "description", valueMember: "id",
                        placeHolder: "(Loading templates)",
                        width: 496, dropDownWidth: 496,
                        animationType: "fade", enableBrowserBoundsDetection: true,
                        disabled: true
                    })
                    
                            /**
                             * Event: The list of templates has finished loading - Enabled the selector
                             */
                            .on('bindingComplete', function () {
                                if (templates_Source.records.length == 0)
                                    $(this).jqxDropDownList({
                                        placeHolder: "(There are no organization Document templates for this record type)",
                                        disabled: true
                                    });
                                else {
                                    $(this).jqxDropDownList({
                                        placeHolder: "Select a template to set Content and Description:",
                                        dropDownHeight: templates_Source.records.length > 7 ? 184 : null,
                                        autoDropDownHeight: templates_Source.records.length <= 7 ? true : false,
                                        disabled: false
                                    });
                                }
                            })
                            /**
                             * Event: A template is selected. Grab the information and fill in the document area
                             */
                            .on("select", function (e) {
                                if (e.args && e.args.item) {
                                    // Check if Compose was already changed - Give a warning that override will happen
                                    if (editor_nonempty)
                                        if (!confirm("This document was already edited or contains another template.\nAre you sure you want to overwrite with new template?"))
                                            return;

                                    // Template was selected - Read the rendered template (Title/Body) and setup e-mail
                                    $.get("/global/operations?action=Render_Template", {
                                        id: e.args.item.originalItem.id,
                                        org_id: master.config.perspective, // Only relevant when superuser is logged in
                                        rt_id: master.config.type.base_id ? master.config.type.base_id : master.config.type.id,
                                        rec_id: master.config.id
                                    }, function (r, textStatus, jqXHR) {

                                        $("#doc-description", $doc_editor).val(r.title);
                                        if (r.body.length > 0)
                                            master.rich_editor.content_Write(rich_editor_instance_key, r.body);
                                        // Short timeout before focus to allow TinyMCE to sort itself out
                                        setTimeout(function () {
                                            master.rich_editor.focus();
                                        }, 100);
                                        editor_nonempty = true;

                                    }, 'json')
                                            .fail(cargohub.its.http_fail);
                                }
                            });
                }

                // Mark the editor as open (in one of the specified modes)
                editor_open = config.mode;

                $('#doc-title, #doc-version, #doc-description, #doc-webview-link', $doc_editor).addClass(cargohub.input_theme_classes);
                $('#doc-version', $doc_editor).each(cargohub.components.ui.numbers_input);

                $('#doc-webview-link', $doc_editor).on('click', function () {
                    $(this).select();
                });

                // Set/Change title (header)
                if (config.title)
                    $doc_editor.jqxExpander('setHeaderContent', '<img src="/img/icons16/' + (config.icon || 'document_image_ver.png') + '" alt="[o]" class="inline-icon"> ' + config.title);

                switch (config.mode) {
                    case 'create':
                        row_data = null;
                        editor_nonempty = false;
                        $("input:checkbox[name='doc_read_sign']").prop('checked', false);
                        // Empty Title/Subject
                        $('#doc-title, #doc-description', $doc_editor).val("");
                        // Default version is "1" (written as 1.0)
                        var def_version = 1;
                        $("#doc-version", $doc_editor).val(def_version.toFixed(1));

                        // Version up button is disabled while creating
                        $('#doc-cmd-upver').hide();

                        // Default visibility and Edit access is (v)"Org" and (e)"Editor only"
                        $('#doc-visibility', $doc_editor).jqxDropDownList('selectIndex', 2);
                        $('#doc-edit_access', $doc_editor).jqxDropDownList('selectIndex', 1);//10032025 doc module default select

                        // Webview is off for new documents
                        $(".doc-webview-info", $doc_editor).hide();
                        $("#doc-webview-state", $doc_editor).val(false);
                        $("#doc-webview-link", $doc_editor).val("-=- Save your document to receive a link -=-");
                        $("#doc-webview-secure", $doc_editor).val(true);
                        $("#doc-publish-secure", $doc_editor).val(true);

                        // Template line is always reset to "no selection" so it's ready to select (even with only one option available)
                        $("#doc-tpl-line", $doc_editor).show();
                        $("#doc-template", $doc_editor).jqxDropDownList('clearSelection');

                        // Finally, clear editor
                        if (rich_editor_ready)
                            master.rich_editor.content_Write(rich_editor_instance_key, "\n");
                        break;

                    case 'edit':
                        // Check if there's overrides or defaults for the data/values - Process "row_data" before editing
                        if (config.overrides)
                            row_data = $.extend(row_data, config.overrides);
                        console.log(row_data);


                        //IWI doc seen & sign start

//                        if (user_session.id != row_data.published_by) {
//                            $('#tab_docs [data-op="doc-save"]').hide();
//                        } else {
//                            $('#tab_docs [data-op="doc-save"]').show();
//                        }

                        //IWI doc seen & sign end
                        //
                        // Fill the doc. Name, Description and Version
                        $('#doc-title', $doc_editor).val(row_data.name);
                        $('#doc-description', $doc_editor).val(row_data.description);
                        if (!row_data.version)
                            row_data.version = 1;
//			$("#doc-version", $doc_editor).val(row_data.version.toFixed(1));
                        $("#doc-version", $doc_editor).val(row_data.version);

                        // Version up button is enabled for editing
                        $('#doc-cmd-upver').show();

                        // Visibility and Editing access
                        for (c = 0; c < doc_Visibility_Source.length; c++)
                            if (doc_Visibility_Source[c].k == row_data.visibility)
                                $('#doc-visibility', $doc_editor).jqxDropDownList('selectIndex', c);
                        for (c = 0; c < doc_Edit_access_Source.length; c++)
                            if (doc_Edit_access_Source[c].k == row_data.edit_access)
                                $('#doc-edit_access', $doc_editor).jqxDropDownList('selectIndex', c);

                        // Webview related (second column)
                        if (row_data.wv_key) {
                            setTimeout(function () {
                                $("#doc-webview-state", $doc_editor).jqxSwitchButton('check');
                            }, 100);
                            $("#doc-webview-link", $doc_editor).val("https://www.cargohub.aero/?action=document&qp=" + donrey.qp_conv(open_row_id) + "&key=" + row_data.wv_key);



                        } else {
                            setTimeout(function () {
                                $("#doc-webview-state", $doc_editor).jqxSwitchButton('uncheck');
                            }, 100);
                            $("#doc-webview-link", $doc_editor).val("-=- Save your document to receive a link -=-");
                        }

//                        console.log(row_data);
                        var value = row_data.wv_publish_for;
                        $("input[name=publish_for][value=" + value + "]").prop('checked', true);
//                                checkValueToshoForPullish();
                        if (value != 'private') {
                            $('.pfdsc').css({"display": "block"});
                        } else {
                            $('.pfdsc').css({"display": "none"});
                        }
                        if (row_data.wv_publish_for_ids != '') {
                            var wv_publish_for_ids = $.parseJSON(row_data.wv_publish_for_ids);
//                            console.log(wv_publish_for_ids);
                            $.each(wv_publish_for_ids, function (i, val) {
                                $("input:checkbox[name='publish_for_department[]'][value='" + val + "']").prop('checked', true);
                            });
                        }

//                        console.log('row_data',row_data);
                        // Webview related (fourth column)
                        if (row_data.wv_publish == '1') {
                            console.log('yes', row_data.wv_publish);
                            setTimeout(function () {
                                if($("#doc-publish-secure").length>0){
                                $("#doc-publish-secure", $doc_editor).jqxSwitchButton('check');
                            }
                                if($("#doc-webview-state").length>0){
                                $("#doc-webview-state", $doc_editor).jqxSwitchButton('check');
                            }
                            }, 100);
                        } else {
//                            console.log('no ', row_data.wv_publish);
                            setTimeout(function () {
                                $("#doc-publish-secure", $doc_editor).jqxSwitchButton('uncheck');
                            }, 100);

                        }
                        if (row_data.wv_secure == '1') {
//                            console.log('yes', row_data.wv_secure);
                            setTimeout(function () {
                                if ($("#doc-webview-secure").length > 0) {
                                    $("#doc-webview-secure", $doc_editor).jqxSwitchButton('check');
                                }
                            }, 100);
                        } else {
                            console.log('no ', row_data.wv_secure);
                            setTimeout(function () {
                                $("#doc-webview-secure", $doc_editor).jqxSwitchButton('uncheck');
                            }, 100);

                        }
                        
                        
//                        IWI Refresh after days start

                        console.log('row_data.is_required_read_sign', row_data.is_required_read_sign);
                        if (row_data.is_required_read_sign == '1') {
                            console.log('yes is_required_read_sign', row_data.is_required_read_sign);
                            setTimeout(function () {
                                 $("#doc-webview-required-read-sign", $doc_editor).jqxSwitchButton('check');
//                                if ($("#doc_read_sign").length > 0) {
//                                    $("#doc_read_sign", $doc_editor).jqxSwitchButton('check');

//                                    $("#doc-webview-required-read-sign", $doc_editor).jqxSwitchButton('check');
//                                    $("#refresh_after_days").val(row_data.refresh_after_days);
//                                    $('.doc-webview-info_refresh_after_days_sec').show();
//                                }
                            }, 100);
                        } else {
                            console.log('no is_required_read_sign ', row_data.is_required_read_sign);
                            setTimeout(function () {
//                                $("#doc_read_sign", $doc_editor).jqxSwitchButton('uncheck');
console.log($("#doc-webview-required-read-sign", $doc_editor).length);
                                $("#doc-webview-required-read-sign").jqxSwitchButton('val', false);
                                $('.doc-webview-info_refresh_after_days_sec').hide();
                            }, 100);

                        }
                        if (row_data.refresh_after_days > 0 ) {
                            setTimeout(function () {
                                $("#refresh_after_days").val(row_data.refresh_after_days);
//                                if ($("#doc_read_sign").length > 0) {
//                                    $("#doc_read_sign", $doc_editor).jqxSwitchButton('check');
//
////                                    $("#doc-webview-required-read-sign", $doc_editor).jqxSwitchButton('check');
//                                    $("#refresh_after_days").val(row_data.refresh_after_days);
//                                    $('.doc-webview-info_refresh_after_days_sec').show();
//                                }
                            }, 100);
                        }
                        
                        
//                        IWI Refresh after days end

//                     IWI Create section for Notification start
                        if (row_data.is_notification == 1) {
                                    $("#doc-webview-notification", $doc_editor).jqxSwitchButton('check');
                                    $("#doc-webview-notification-days").val(row_data.notification_days);
                                    
                             setTimeout(function () {
console.log('row_data.is_notification',row_data.is_notification);
                               $(".doc-webview-document", $doc_editor).hide();
                               $(".doc-webview-info_refresh_after_days_sec", $doc_editor).hide();
                            $(".doc-webview-notification_sec", $doc_editor).show();
                            }, 1000);
                                    
                        } else {
                            
                        }
//                    IWI Create section for Notification end



//                        $("#doc-webview-secure", $doc_editor).val(row_data.wv_secure);
//			$("#doc-publish-secure", $doc_editor).val(row_data.wv_publish);
                        $("#doc-tpl-line", $doc_editor).hide();

                        // If editor already initialized (moving from another record, reload new body)
                        if (rich_editor_ready)
                            load_Body();
                        break;
                }

                // Finished initialization ( or reconfiguration ) - Show the expander and expand
                $doc_editor.show().jqxExpander('enable');
                $doc_editor.on('expanded', function () {
                    // When first expanded, initialize Content editor
                    if (!rich_editor_ready) {
                        master.rich_editor.create_Instance(rich_editor_instance_key,
                                {// Config
                                    container: $("#doc-content", $doc_editor),
                                    params: {
                                        // Only useful for TinyMCE
                                        tinyMCE: {
                                            plugins: "link autolink autoresize paste textcolor image hr fullscreen lists table colorpicker charmap searchreplace insertdatetime",
                                            toolbar: "bold italic underline | alignleft aligncenter alignright | formatselect fontselect fontsizeselect | hr link image table bullist numlist charmap insertdatetime | forecolor backcolor removeformat | undo redo | searchreplace fullscreen",
                                            autoresize_min_height: 100, //IWI notification changes 17032026
                                            autoresize_max_height: 400, //IWI notification changes 17032026
                                        }
                                    },
                                    print: {
                                        enabled: true // Enable printing function
                                    }
                                },
                                {// Events
                                    on_init: function () {
                                        // If first initialization starts in "edit" mode, load content. If not, just enable the editor
                                        $("#doc-content-placeholder", $doc_editor).hide();
                                        if (config.mode == 'edit') {
                                            load_Body();
                                        } else
                                            master.rich_editor.enable(rich_editor_instance_key);

                                        rich_editor_ready = true;
                                    },
                                    on_change: function (e) {
                                        console.log(e);
                                        editor_nonempty = true;
                                    }
                                }
                        );
                    }
                });
                $doc_editor.jqxExpander('expand');

                // Add tooltips for relevant elements
                $(".info-tip", $doc_editor).each(cargohub.components.ui.add_Tooltip);

                // Show the Save/Close controls
                $('#docs-open-ops button').jqxButton({disabled: false});
                $('#docs-open-ops').fadeIn();
            },
            // Becomes true when document is copied under a new name
            copy_mode = false,
            /**
             * Saves the content of the document editor (name, description, content)
             */

            save_Editor = function (version_update)
            {
                
//                $('#tab_docs [data-op="doc-save"]').addClass('btnDisabled');
//            console.log('save doc');

//            console.log('publish_for',$('input[type=radio][name=publish_for]:checked').val());

                var open = [];
                $.each($("input:checkbox[name='publish_for_department[]']:checked"), function () {
                    open.push($(this).val());
                });


//            console.log('publish_for_radio',JSON.stringify(open));

//IWI Refresh after days start
//                if ($('input[name=doc_read_sign]').prop('checked') == true) {
//                    var doc_read_sign = '1';
//                } else {
//                    var doc_read_sign = '0';
//                }
//                
//                var doc_read_sign =$("#doc_read_sign", $doc_editor).val();
                var doc_read_sign =$("#doc-webview-required-read-sign", $doc_editor).val();
                
                
//                IWI Refresh after days end




                
                // TODO: Warn if no changes have been made ("changed" variable)
                var
                        // Content extracted from TinyMCE
                        doc_content = master.rich_editor.content_Read(rich_editor_instance_key),
                        // Extra information used to record the document as linked to a record (or "issue" as it's former name)
                        submit_data = (open_row_id && !copy_mode) ?
                        {// When editing an existing document
                            id: open_row_id
                        } : {// Create new document or copy existing one
                    link: "issue"								// Create a document for a "Record" (issue, rename this when possible) - This cannot be changed after !
                };
                submit_data.link_id = master.config.id;			// The Record's ID, the document is linked to this record.
                submit_data["doc[org_id]"] = master.config.perspective;	// Current organization (perspective for admin - Not useful for non-admin as it will default to the user's organization anyway)
                submit_data["doc[content_type]"] = "html";		// Document manager stores documents as "HTML", so they can be directly edited by TinyMCE
                submit_data.webview_state = $("#doc-webview-state", $doc_editor).val();
                submit_data.webview_secure = $("#doc-webview-secure", $doc_editor).val();
//		submit_data.webview_publish = $("#doc-publish-secure", $doc_editor).val()?'1':'0';
                submit_data.webview_publish = $("#doc-webview-state", $doc_editor).val();
                submit_data.wv_publish_for = $('input[type=radio][name=publish_for]:checked').val();
                submit_data.wv_publish_for_ids = $('input[type=radio][name=publish_for]:checked').val() != 'private' ? JSON.stringify(open) : '';
                submit_data.doc_read_sign = doc_read_sign;
                
//                IWI if update any doc without any change, then no update icon will show start

                // submit_data["doc[wv_secure]"] = $("#doc-webview-state", $doc_editor).val();	
                 submit_data["doc[wv_publish]"] = $("#doc-webview-state", $doc_editor).val();	
                 submit_data["doc[webview_state]"] = $("#doc-webview-state", $doc_editor).val();
                 submit_data["doc[wv_publish_for]"] = $('input[type=radio][name=publish_for]:checked').val();	
                 submit_data["doc[wv_publish_for_ids]"] = $('input[type=radio][name=publish_for]:checked').val() != 'private' ? JSON.stringify(open) : '';	
                 submit_data["doc[is_required_read_sign]"] = doc_read_sign;	
//                IWI if update any doc without any change, then no update icon will show end


                 submit_data["doc[refresh_after_days]"] = $("#refresh_after_days", $doc_editor).val();; //IWI Refresh after days
                 
                 
//IWI Create section for Notification start

 submit_data["doc[is_notification]"]  =$("#doc-webview-notification", $doc_editor).val();
 submit_data["doc[notification_days]"]  =$("#doc-webview-notification-days", $doc_editor).val();
 
 
  $('#tab_docs [data-op="doc-save"]').removeClass('btnDisabled');
//console.log('submit_data',submit_data); return false;
//IWI Create section for Notification end
                
                // Content of TinyMCE
                if (doc_content === false) {
                    $('#tab_docs [data-op="doc-save"]').removeClass('btnDisabled');
                    return cargohub.alert("Error occurred in the document editor. Try copying content out, reloading and pasting it back in.");
                } else {
                    submit_data["doc[text]"] = doc_content;
                }
                if (version_update) {
                    submit_data.version_update = true;
                    $("#doc-version", $doc_editor).val((Math.floor($("#doc-version", $doc_editor).val()) + 1).toFixed(1));
                }
//                console.log('doc_content',doc_content); 
//                
////               var image = $(doc_content).find('img');
////               console.log(image);
//               $(doc_content).find('img').each(function( index ) {
//  console.log(  $( this ) );
//  
//   $( this ).attr('width','800px');
//});
//
//                console.log('doc_content',doc_content); 
//
//
//                return false;


//                IWI doc section file upload start
//                console.log('selectedAttachIds',selectedAttachIds);
                submit_data['selectedAttachIds']=selectedAttachIds;
                
//                IWI doc section file upload end


//console.log(submit_data);exit();
                // submit the add/edit form using ajax
                $($doc_editor).find('form').ajaxSubmit({
                    dataType: 'json',
                    data: submit_data,
                    beforeSubmit: function (formData, jqForm, options)
                    {
                        if (!$('#doc-title', $doc_editor).val()) {
                            $('#tab_docs [data-op="doc-save"]').removeClass('btnDisabled');
                            return cargohub.alert("The document must have a name or title !");
                        }

                        var doc_desc = $("#doc-description", $doc_editor).val();
                        if (doc_desc && doc_desc < 5) {
                            $('#tab_docs [data-op="doc-save"]').removeClass('btnDisabled');
                            return cargohub.alert("A document description is not necessary, but when completed it must be longer than 5 characters");
                        }
                        // Check if Title is too long ( over 100 characters ) and refuse names which are too long.
                        if ($("#doc-title", $doc_editor).val().length > 100) {
                            $('#tab_docs [data-op="doc-save"]').removeClass('btnDisabled');
                            return cargohub.alert("Document names longer than 100 characters are currently not supported. Please shorten it.");
                        }

                        if (doc_content.length < 20) {
                            $('#tab_docs [data-op="doc-save"]').removeClass('btnDisabled');
                            return cargohub.alert("Document content is empty or too short. Minimum 20 characters needed");
                        }

                        return true;
                    },
                    success: function (x) {
                        
//                        IWI if update any doc without any change, then no update icon will show start
                        if(x.response=='409'){
                            Swal.fire(x.message);
                            $('#tab_docs [data-op="doc-save"]').removeClass('btnDisabled');
                            return false;
                        }
//                        IWI if update any doc without any change, then no update icon will show end
//                        IWI if trying to version up any doc without any change, then there will be showing a alert end restrict start
                        else if(x.response=='408'){
                            Swal.fire(x.message);
                             $("#doc-version", $doc_editor).val((Math.floor($("#doc-version", $doc_editor).val()) - 1).toFixed(1));
                            $('#tab_docs [data-op="doc-save"]').removeClass('btnDisabled');
                            return false;
                        }
                        
//                        IWI if trying to version up any doc without any change, then there will be showing a alert end restrict end
                        
                        master.refresh_DataTable($("#tab_docs div.jqx-datatable"), true); // true = do not clear

                        // TODO - Resolve the tab id to idx first
//					if(x.count)
//						cargohub.components.hashnav.update_Tab_Header(5, "Notes <span class='fiftycent'>["+x.count+"]</span>", "/img/icons16/note.png");
                        // Finally, show the operation result
                        master.toolbar_Report("#tab_docs", x.response);
                        
                        
                        
                        //IWI if any update in the doc section, send reminder mail for read & sign  start 
                        
                        if (submit_data.webview_state) {
                            Swal.fire({
                                title: "Do you wish to inform readers instantly? <br><p style='font-size: 14px; color: #bbb5b5; margin-top: auto;'> If no, user can get notificaion autometically from within 24 hours.  </p>",
                                showDenyButton: true,
                                confirmButtonText: "Yes",
                                cancelButtonText: "No",
                            }).then((result) => {
                                /* Read more about isConfirmed, isDenied below */
                                if (result.isConfirmed) {
//            console.log(open_row_id);return false;
                                    $(".simple-spinner").show();
                                    $.post("/user/publication/?action=send_reminder_mail_to_all_user", {
                                        note_id: x.curr_note_id, // IWI Create a button to bulk mail send
                                        mail_for: 'send_reminder_mail_to_all_user'
                                    }, function (x) {
//        console.log(x);
                                        if (Object.keys(x.success)) {
                                            $(".simple-spinner").hide();
                                            Swal.fire({
                                                title: "",
                                                text: "Reminder mail has been sent",
                                                icon: "success"
                                            });
                                        } else {
                                            $(".simple-spinner").hide();
                                            Swal.fire({
                                                title: "",
                                                text: "Opps, Mail not send. Please try again!",
                                                icon: "error"
                                            });

                                        }


                                        close_Editor();
                                        cargohub.components.spinner.On("Success!", x.response, 2500, function () {
                                            cargohub.components.spinner.Off();
                                        });
                                        // Show the "Create" button again
                                        $('#tab_docs #docs-create-op').fadeIn();
                                        $('#tab_docs [data-op="doc-save"]').removeClass('btnDisabled');

                                    });
                                } else {
                                    close_Editor();
                                    cargohub.components.spinner.On("Success!", x.response, 2500, function () {
                                        cargohub.components.spinner.Off();
                                    });
                                    // Show the "Create" button again
                                    $('#tab_docs #docs-create-op').fadeIn();
                                    $('#tab_docs [data-op="doc-save"]').removeClass('btnDisabled');
                                }


                            });
                        } else {
                            close_Editor();
                            cargohub.components.spinner.On("Success!", x.response, 2500, function () {
                                cargohub.components.spinner.Off();
                            });
                            // Show the "Create" button again
                            $('#tab_docs #docs-create-op').fadeIn();
                            $('#tab_docs [data-op="doc-save"]').removeClass('btnDisabled');
                        }

                        //IWI if any update in the doc section, send reminder mail for read & sign  end
                    
//                        location.reload();
                    },
                    error: cargohub.its.http_fail
                });
            },
            /**
             * Minimize and close the editor if it was open.
             */
            close_Editor = function (transition)
            {
                $('#tab_docs [data-op="doc-save"]').removeClass('btnDisabled');
                if (!$doc_editor) // Not yet initialized
                    return;

                // TODO: Check if changes have been made, and warn if so (to avoid closing unsaved document)

                // Minimize and close the editor
                if ($doc_editor.jqxExpander('expanded')) {
                    $doc_editor.on('collapsed', function () {
                        // When finishing collapse... hide the editor
                        $doc_editor.hide();
                        // ... and disable the event
                        $doc_editor.off('collapsed');
                    });
                    $doc_editor.jqxExpander('collapse');
                } else {
                    // If already collapsed, just hide it
                    $doc_editor.hide();
                }

                // Also fadeout the Save/Close buttons
                $('#tab_docs #docs-open-ops button').jqxButton({disabled: true});
                $('#tab_docs #docs-open-ops').fadeOut();

                if (!transition) {
                    if (selected_row)
                        $('#tab_docs #docs-row-ops').fadeIn();
                    // Show the "Create" button in case it was hidden by a previous operation
                    $('#tab_docs #docs-create-op').fadeIn();
                }

                $('#taskAct').click();  //IWI docs list  export , active ,  closed button create 

                editor_nonempty = false;
                editor_open = false;
                open_row_id = null;
                copy_mode = false;
                //IWI doc section file upload start
                //location.reload(); 
                init_Toolbar();
            init_DataTable();
            docs_Counter();
            
                //IWI doc section file upload end
                return true;
            },
            /**
             * Open the document viewer (No form, just the data in a static, read-only format)
             */
            view_Document = function (config)
            {
                if (docs_viewer_Ready) {
                    // Update expander with new settings (if necessary)
                    if (config && config.expander)
                        $doc_viewer.jqxExpander(config.expander);

                } else {
                    // Init the expander itself on first use
                    if (!config.expander)
                        config.expander = {
                            disabled: false,
                            expanded: false
                        };
                    config.expander.theme = cargohub.jqxtheme;
                    $doc_viewer.jqxExpander(config.expander);

                    docs_viewer_Ready = true;
                }
                $doc_viewer.show();

                // Set/Change title (header)
                if (config.title)
                    $doc_viewer.jqxExpander('setHeaderContent', '<img src="/img/icons16/' + (config.icon || 'document_image_ver.png') + '" alt="[:]" class="inline-icon"> ' + config.title);

                var row_data = $.extend({}, selected_row);
                // Check if there's overrides or defaults for the data/values - Process "row_data" before rendering the template
                if (config.overrides)
                    row_data = $.extend(row_data, config.overrides);

                row_data.dt_format = moment(row_data.dt).format("YYYY-MM-DD (dddd) HH:mm:ss") + ' <span class="gray">(' + today.diff(row_data.dt, 'days') + ' days ago)</span>';

                // Render the doc viewer template into the body of the expander
                $doc_viewer.jqxExpander('setContent', $('#template-doc-sec-viewer').html().present(row_data));

                // Document body is not loaded in the grid, so load it now
                $("#document-viewer-body", $doc_viewer).html("<div class='center'>(loading, please wait...)</div>");
                $.get('/global/note', {
                    action: "Load_Body",
                    id: row_data.uid,
                    content_type: 'html'
                }, function (body) {
                    if (body.length > 0)
                        $("#document-viewer-body", $doc_viewer).html(body);
                    else
                        $("#document-viewer-body", $doc_viewer).html("<span class='gray'>(This document has no content)</span>");
                });

                // Add tooltips for relevant elements
                $(".info-tip", $doc_viewer).each(cargohub.components.ui.add_Tooltip);

                $doc_viewer.jqxExpander('expand');

                // Show the Close control
                donrey.cancel_Delay('hide-tasks-open-ops');
                $('#tab_docs #docs-open-ops button').jqxButton({disabled: false});
                $('#tab_docs #docs-open-ops').fadeIn();

                viewer_open = true;
            },
            /**
             * Close the document Viewer expander
             */
            close_Viewer = function (transition)
            {
                if (!$doc_viewer) // Not yet initialized
                    return;

                if ($doc_viewer.jqxExpander('expanded')) {
                    $doc_viewer.on('collapsed', function () {
                        // When finishing collapse... hide the editor
                        $doc_viewer.hide();
                        // ... and disable the event
                        $doc_viewer.off('collapsed');
                    });
                    $doc_viewer.jqxExpander('collapse');
                } else {
                    // If already collapsed, just hide it
                    $doc_viewer.hide();
                }

                // Also fadeout the Close buttons
                $('#tab_docs #docs-open-ops button').jqxButton({disabled: true});
                $('#tab_docs #docs-open-ops').fadeOut();

                if (!transition) {
                    if (selected_row)
                        $('#tab_docs #docs-row-ops').fadeIn();
                    // Show the "Create" button in case it was hidden by a previous operation
                    $('#tab_docs #docs-create-op').fadeIn();
                }

                viewer_open = false;

                return true;
            },
            /**
             * Opens the document for either editing or viewing (depending on access rights)
             * In Editing mode, the call is then passed to init_Editor(), which loads TinyMCE and then places the document content in it. A "Save" command is added to the toolbar
             * In Viewing mode, the document is simply loaded into a container, without any editing capabilities. The only available command is "Close"
             */
            open_Document = function (row)
            {
                // Fadeout Create command when Editor or Viewer are active
                $('#tab_docs #docs-create-op').fadeOut();

                if (row)
                    selected_row = row;

                var edit_access = docs_manager_priv ||
                        (selected_row.edit_access == 'shared'
                                || (selected_row.edit_access == 'org' && (selected_row.visibility == 'dep' || selected_row.visibility == 'org'))
                                || (selected_row.edit_access == 'dep' && selected_row.dep_id == user_session.cdep_id)
                                || (selected_row.edit_access == 'user' && selected_row.editor_id == user_session.id)
                                );

                if (edit_access) {
                    doc_editor_mode='doc-edit'; //IWI doc tab changes 2072028
                    // Edit mode
                    close_Viewer(true);
                    $('#tab_docs #docs-open-ops, #tab_docs .docs-write-ops').fadeIn();

                    open_row_id = selected_row.uid;

                    init_Editor({
                        title: "Edit document: " + selected_row.name,
                        icon: "pencil.png",
                        mode: "edit"
                    });
                } else {
                    // Read-only mode
                    close_Editor(true);

                    $('#tab_docs .docs-write-ops').hide(); // No "Save" function in View mode
                    $('#tab_docs #docs-open-ops').fadeIn();

                    // View mode, switch to the task viewer
                    view_Document({
                        title: "Viewing document: " + selected_row.name
                    });
                }
            },
            /**
             * Removes a Record-linked document. Refreshes the DataTable on success
             */
            delete_Document = function ()
            {
                $.post("/global/note?action=remove", {
                    link: "issue",
                    link_id: master.config.id, // Linked record ID
                    id: selected_row.uid, // Document ID
                }, function (x) {
                    master.refresh_DataTable($("#tab_docs div.jqx-datatable"));
                    selected_row = null;

                    master.toolbar_Report("#tab_docs", x.response);

                    close_Editor();
                    cargohub.components.spinner.On("Success!", x.response, 2500, function () {
                        cargohub.components.spinner.Off();
                    });
                })
                        .fail(cargohub.its.http_fail);
            },
//        IWI docs list  export , active ,  closed button create start
            docs_Counter = function () {
                $.post("/global/note?action=Get_DocsCount", {
                    link: "issue",
                    link_id: master.config.id,
                    content_type: "html",
                }, function (x) {
                    if (x.success) {
                        $('#act_task').text(x.active);
                        $('#old_task').text(x.old);
                    }
                });
            },
//        IWI docs list  export , active ,  closed button create end

            /**
             * Initialize the Docs tab toolbar (Add, Edit, Copy, Export, Delete documents) - Future: Import documents via uploading (should recognize MS Word?)
             */
            init_Toolbar = function ()
            {

                $("#tab_docs .toolbar button")
                        .jqxButton({theme: cargohub.jqxtheme})
                        /**
                         * Event: Toolbar button is clicked
                         */
                        .on('click', function ()
                        {
                            switch ($(this).data('op')) {
                                // Create new blank document command - Initializes the editor in create mode
//                        IWI docs list  export , active ,  closed button create start

                                case 'filteractive':
                                    var filtertype = 'stringfilter';
                                    var dataField = 'visibility';
                                    var filtergroup = new $.jqx.filter();
                                    var checkedItems = ['org', 'dep', 'shared', 'public'];
                                    for (var i = 0; i < checkedItems.length; i++) {
                                        var filter_or_operator = 1;
                                        // set filter's value.
                                        var filtervalue = checkedItems[i];
                                        // set filter's condition.
                                        var filtercondition = 'equal';
                                        // create new filter.
                                        var filter = filtergroup.createfilter(filtertype, filtervalue, filtercondition);
                                        // add the filter to the filter group.
                                        filtergroup.addfilter(filter_or_operator, filter);
                                    }
                                    // add the filters.
                                    $("#_record-docs").jqxDataTable('addFilter', dataField, filtergroup);
                                    // apply the filters. 
//                                    console.log('hmbm',filtergroup); 
                                    $("#_record-docs").jqxDataTable('applyFilters');
                                    $('.jqx-icon-close-fresh').fadeIn();
                                    break;

                                case 'filterclosed':
                                    var filtertype = 'stringfilter';
                                    var dataField = 'visibility';
                                    var filtergroup = new $.jqx.filter();
                                    var checkedItems = ['private'];
                                    for (var i = 0; i < checkedItems.length; i++) {
                                        var filter_or_operator = 1;
                                        // set filter's value.
                                        var filtervalue = checkedItems[i];
                                        // set filter's condition.
                                        var filtercondition = 'equal';
                                        // create new filter.
                                        var filter = filtergroup.createfilter(filtertype, filtervalue, filtercondition);
                                        // add the filter to the filter group.
                                        filtergroup.addfilter(filter_or_operator, filter);
                                    }
                                    // add the filters.
                                    $("#_record-docs").jqxDataTable('addFilter', dataField, filtergroup);
                                    // apply the filters. 
//                                    console.log('hmbm',filtergroup); 
                                    $("#_record-docs").jqxDataTable('applyFilters');
                                    $('.jqx-icon-close-fresh').fadeIn();
                                    break;


                                case 'exportxlsx':
                                    $("#tab_docs div.jqx-datatable").jqxDataTable('exportData', 'xls');
                                    break;
//                         IWI docs list  export , active ,  closed button create end 
                                case "doc-create":
                                    doc_editor_mode='doc-create'; //IWI doc tab changes 2072028 
                                    master.clear_DataTable($("#tab_docs div.jqx-datatable")); // Also unselect row if one is selected (which hides unneeded buttons)
                                    open_row_id = null;
                                    copy_mode = false;

                                    if ($doc_viewer && viewer_open)
                                        close_Viewer(true);

                                    $('#tab_docs .docs-write-ops').fadeIn(); // Make sure Save is enabled
                                    init_Editor({
                                        title: "Create new blank document",
                                        icon: "add.png",
                                        mode: "create"
                                    });
                                    $('#tab_docs #docs-create-op').fadeOut(); // Hide the "Create" button when the editor is active, will be shown again on Save or Close
                                    preSelectAttachments([]);//IWI doc section file upload
                                     $('#file-input').val('');//IWI doc section file upload
                                     $('#file-info').text('');//IWI doc section file upload

                                    break;

                                    // Create a copy of the existing open document, which must be saved under a new name - by default, the system adds " (copy)" to the existing name
                                case "doc-copy":
                                    
                                    open_row_id = selected_row.uid;
                                    copy_mode = true; // ID is ignored, so a new document is created instead

                                    if ($doc_viewer && viewer_open)
                                        close_Viewer(true);

                                    $('#tab_docs .docs-write-ops').fadeIn(); // Make sure Save is enabled
                                    init_Editor({
                                        title: "Create copy of document: " + selected_row.name,
                                        icon: "page_copy.png",
                                        mode: "edit",
                                        overrides: {
                                            "name": selected_row.name + " (copy)",
                                            "version": 1 // It's first version of the copy !
                                        }
                                    });
                                    $('#tab_docs #docs-create-op').fadeOut();
                                    break;

                                    // Open document for editing or viewing (determined inside the open_Document function)
                                case "doc-open":
                                    open_row_id = selected_row.uid;
                                    open_Document();
                                    break;

                                    // Temporary - Test of Viewer
                                case "doc-view":
                                    if (editor_open)
                                        close_Editor(true);
                                    $('#tab_docs .docs-write-ops').hide(); // No "Save" function in View mode
                                    $('#tab_docs #docs-open-ops').fadeIn();

                                    view_Document({
                                        title: "Viewing document: " + selected_row.name
                                    });

                                    $('#tab_docs #docs-create-op').fadeOut();
                                    break;

                                    // Opens the document in a new window/tab in Webview mode
                                case "doc-webview-newwin":
                                    var win = window.open("/?action=document&qp=" + donrey.qp_conv(selected_row.uid) + "&key=" + selected_row.wv_key, '_blank');
                                    win.focus();
                                    break;

                                    // Deletes existing document (Manager privilege is required)
                                case "doc-delete": //
                                    if (!confirm("Are you sure you want to delete this document ?"))
                                        return;
                                    delete_Document();
                                    break;

                                    // Save existing editor content (If no changes is done, a warning is thrown, to avoid unnecessary database operations/logging)
                                case 'doc-save':
//                                    $('#tab_docs [data-op="doc-save"]').addClass('btnDisabled');
                                    var $btn = $('#tab_docs [data-op="doc-save"]');

                                    // Immediately disable the button
                                    $btn.addClass('btnDisabled');
                                    $btn.prop('disabled', true); 
                                    save_Editor();
                                    break;

                                    // Save with Version update
                                case 'doc-upver':
                                    save_Editor(true);
                                    break;

                                    // Closes the Editor without saving (discards existing content - Warns if new or existing document has been changed, or when copy mode is active)
                                    // Close the Viewer when document is opened in View-only mode
                                case 'doc-close':
                                    if (editor_open)
                                        close_Editor();
                                    if (viewer_open)
                                        close_Viewer();
                                    break;
                            }
                        });

                // Checkbox that adds "classic" notes to the Documents grid (since they are really the same table) - Once a note is saved as a document, it's not visible anymore in notes
                // Only user with Manage access can convert Notes into Docs (as it's equivalent to a Delete+Create operation)
                if (docs_manager_priv) {
                    $('#tab_docs #doc-import').jqxCheckBox({
                        theme: cargohub.jqxtheme,
                        height: 18
                    })
                            .show()
                            .on('change', function (e) {
                                if (e.args.checked)
                                    docs_Source._source.data.content_type = 'plain';
                                else
                                    docs_Source._source.data.content_type = 'html';

                                // Reload the documents table when the checkbox is changed
                                master.refresh_DataTable($("#tab_docs div.jqx-datatable"));
                            })
                            .find("div.jqx-checkbox-default").css('margin-top', "2px");
                }
            },
            /**
             * Initialize the Documents DataTable. (This uses common functionality from the main Record Process module - cargohub.record_process )
             */
            init_DataTable = function ()
            {
                /**
                 * Highlights the background of each cell in a row (effectively highlighting the row itself) to indicate the conditions:
                 * - Shared note: Yellow
                 * - User private note: Pale Red
                 * Current perspective orgaization will have all it's text with "italic" style (Admin only)
                 */
                var cell_highlight = function (row, column, value, rowData) {
                    var classname = "";

                    if (rowData.visibility == 'shared' || rowData.visibility == 'public')
                        classname += "grid-cell-shared";
                    else if (rowData.visibility == 'private')
                        classname += "grid-cell-private";

                    // Mark notes for the perspective org for the admin user
                    if (cargohub.application.user_Is_Admin() && rowData.org_id == master.config.perspective)
                        classname += " grid-cell-perspective_org";
                    return classname;
                };



                master.init_DataTable($("#tab_docs div.jqx-datatable"), {
                    exportSettings: {
                        columnsHeader: true,
                        hiddenColumns: false,
                        serverURL: "/?action=export",
                        characterSet: null,
                        collapsedRecords: false,
                        recordsInView: true,
                        fileName: "docs"
                    },
                    // Config
                    source: docs_Source,
                    columns: [ // IWI doc tab changes 2072028
                        {dataField: 'wv_key', text: '<span class="access16 state-web"></span>', width: 35, filterable: false, cellsRenderer: function (row, column, value, rowData) {
                                return '<span class="access16 state-' + (rowData.wv_key ? "on" : "off") + '"></span>';
                            }, cellClassName: cell_highlight},
                        {dataField: 'wv_secure', text: '<span class="access16" style="background-image: url(/img/icons16/globe_lock.png);"></span>', width: 25, filterable: false, cellsRenderer: function (row, column, value, rowData) {
                                return rowData.wv_secure ? '<span class="access16 state-secure"></span>' : "";
                            }, cellClassName: cell_highlight},

//				{dataField: 'wv_publish', text: '<span class="access16" style="background-image: url(/img/icons16/globe_lock.png);"></span>', width: 25, filterable: false, cellsRenderer: function(row, column, value, rowData) {
//					return rowData.wv_publish?'<span class="access16 state-secure"></span>':"";
//				}, cellClassName: cell_highlight},
//                            	{dataField: 'wv_publish_for', text: '<span class="access16" style="background-image: url(/img/icons16/globe_lock.png);"></span>', width: 25, filterable: false, cellsRenderer: function(row, column, value, rowData) {
//					return rowData.wv_publish_for?'<span class="access16 state-secure"></span>':"";
//				}, cellClassName: cell_highlight},
//                            	{dataField: 'wv_publish_for_ids', text: '<span class="access16" style="background-image: url(/img/icons16/globe_lock.png);"></span>', width: 25, filterable: false, cellsRenderer: function(row, column, value, rowData) {
//					return rowData.wv_publish_for_ids?'<span class="access16 state-secure"></span>':"";
//				}, cellClassName: cell_highlight},

                        {dataField: 'name', text: "Document name", cellClassName: cell_highlight},
//				{dataField: 'dt', text: "Updated on", width: 160, cellsFormat: cargohub.datetime_format, filterable: false, cellClassName: cell_highlight},	
                        {dataField: 'ref_number', text: "Ref. No.", width: 200, cellClassName: cell_highlight, filterable: false},//IWI Create section for Notification
                        {dataField: 'version', text: "Version", width: 80, cellClassName: cell_highlight, filterable: false},
                        // {dataField: 'dt', text: "Created", width: 110, cellsFormat: cargohub.datetime_format, filterable: false, cellClassName: cell_highlight},//20032025 add created field
                        {dataField: 'dt', text: "Created", width: 160, cellsFormat: cargohub.datetime_format, filterable: false, cellClassName: cell_highlight},	

                        {dataField: 'last_updated', text: "Last Updated", width: 140, cellsFormat: cargohub.datetime_format, filterable: false, cellClassName: cell_highlight},
                        {dataField: 'editor_name', text: "Last edited by", width: 140, cellClassName: cell_highlight},
                        {dataField: 'org_name', text: "Organization", width: 140, hidden: !cargohub.application.user_Is_Admin(), filterable: cargohub.application.user_Is_Admin(), cellClassName: cell_highlight},
                        {dataField: 'visibility', text: "<span class='access16' style='background-image: url(/img/icons16/eye.png);'></span>", width: 35, filterable: false, cellClassName: cell_highlight},
                    ]
                }, {
                    // Callbacks (for events happening in the datatable itself) - In most cases it will be either View or Edit
                    "getrows": function () {
                        $('#taskAct').click();  //IWI docs list  export , active ,  closed button create 

//                        console.log('getrows callback executed');

                        // Check if the element exists and perform actions accordingly
                        if ($("#getFromPanelCreateDoc").length) {
                            console.log('Element found, triggering addDocAct click');
                            $('#tab_docs [data-op="doc-create"]').click();
                            $('#addDocAct').click();
                            $('#getFromPanelCreateDoc').remove();
                        }
                    },
                    "rowselect": select_Row,
                    "clearselect": select_Clear,
                    "rowopen": open_Document
                }, "docs");
            }
    ;

    this._public({
        /**
         * Setup Notes Tab for Record Process module
         */
        init: function ()
        {
            // We need TinyMCE here
            master.load_rich_editor();

            init_Toolbar();
            init_DataTable();




//                        IWI docs list  export , active ,  closed button create start
            docs_Counter();
//                        IWI docs list  export , active ,  closed button create end

            // Tooltips inside the Documents tab
            $("#tab_docs .info-tip").each(cargohub.components.ui.add_Tooltip);
        },

        /**
         * Callback for actions to be performed when this tab is selected
         */
        selected: function (params)
        {
            if (params && params.trigger)
                switch (params.trigger) {
                    case "doc-create":
                        init_Editor('create');
                        break;
                }
        }
    });


//IWI doc section file upload start
$(document).on('change', '#file-input', function () {
  const files = this.files;
  const infoBox = $('#file-info');

  if (files.length === 1) {
    infoBox.text(files[0].name);
  } else if (files.length > 1) {
    infoBox.text(`${files.length} files selected`);
  } else {
    infoBox.text('');
  }
});


//IWI doc section file upload  end
    $(document).on('click', '#clickAllDepartment', function () {
        if ($("#clickAllDepartment").prop('checked') == true) {
            $("input:checkbox[name='publish_for_department[]']").prop('checked', true);
            $('.pfdsc details summary').html('All ');
        } else {
            $('.pfdsc details summary').html('Choose Department ');
            $("input:checkbox[name='publish_for_department[]']").prop('checked', false);
        }

    });



    $(document).on('change', '.publish_for_radio', function () {
//                console.log($(this).val());
        checkValueToshoForPullish();

    });




    function checkValueToshoForPullish() {
        var publish_for_radio = $('input[name="publish_for"]:checked').val();
    
        if (publish_for_radio != 'private') {
            $('.pfdsc').css({"display": "block"});
//                    if (publish_for_radio == 'organization') {
//                        $('.pfdsc details summary').html('All ');
//                        $("#clickAllDepartment").prop('checked', true);
//                        $("input:checkbox[name='publish_for_department[]']").prop('checked', true);
//                    }else{
//                        $("#clickAllDepartment").trigger('click');
//                        $('.pfdsc details summary').html('Choose Department ');
//                    }
console.log('1678');
         $("#doc-webview-required-read-sign", $doc_editor).jqxSwitchButton('check');   //read and sign task
        } else {
            $('.pfdsc').css({"display": "none"});
            $("#doc-webview-required-read-sign", $doc_editor).jqxSwitchButton('uncheck');  //read and sign task
        }
        
    }

    
    
    
//IWI pdf to doc start

   $(document).on('click', '#doc-file-opload', function () {
        var fileInput = $('.doc-file')[0];
        // Check if any files are selected
        if (fileInput.files.length > 0) {
            var formData = new FormData();
            // Append the file to the FormData object
            formData.append('file', fileInput.files[0]);
            $('#doc-file-opload').html('Loading...');
            $.ajax({
                url: '/global/note?action=Get_filetohtml', // Replace with your server URL
                type: 'POST',
                data: formData,
                processData: false, // Prevent jQuery from automatically transforming the data into a query string
                contentType: false, // Prevent jQuery from setting contentType header
                success: function (response) {
//                    console.log('File uploaded successfully',response.length);
                    master.rich_editor.content_Write(rich_editor_instance_key, response);                    
            $('#doc-file-opload').html('Click to generate body');
                },
                error: function (xhr, status, error) {                    
            $('#doc-file-opload').html('Click to generate body');
                    console.error('File upload failed:', error);
                }
            });
        } else {
            console.log('No file selected.');
        }
    });
    
//    IWI pdf to doc end


// Iwi setting button task start 
 
//  document.getElementById('open-settings').addEventListener('click', function () {
//    document.getElementById('settings-modal').style.display = 'flex';
//  });
//
//  document.getElementById('close-settings').addEventListener('click', function () {
//    document.getElementById('settings-modal').style.display = 'none';
//  });
//
//  document.getElementById('settings-modal').addEventListener('click', function (e) {
//    if (e.target === this) {
//      this.style.display = 'none';
//    }
//  });
$(document).ready(function () {
  $('#open-settings').on('click', function () {
    $('#settings-modal').css('display', 'flex');
  });

  $('#close-settings').on('click', function () {
    $('#settings-modal').css('display', 'none');
  });

  $('#settings-modal').on('click', function (e) {
    if (e.target === this) {
      $(this).css('display', 'none');
    }
  });
});

  // Iwi setting button task end

//IWI Refresh after days start

//    $("#doc_read_sign", $doc_editor).jqxSwitchButton({
//        theme: cargohub.jqxtheme,
//        height: 25, width: 75
//    }).on('change', function (event) {
//        if (event.args.check)
//            $('.doc-webview-info_refresh_after_days_sec').show();
//        else
//            $('.doc-webview-info_refresh_after_days_sec').hide();
//    });
                    
           
    
//    IWI Refresh after days end


//IWI doc tab changes 2072028 start 
  $('#copyPublishLink').on('click', function () {
   var $input = $('#doc-webview-link');
        $input.select();
        document.execCommand('copy');

        // Show message
        $('#copy-msg').fadeIn(200).delay(2000).fadeOut(400);

        // Deselect input
        $input.blur();
  });
//IWI doc tab changes 2072028 end


});

