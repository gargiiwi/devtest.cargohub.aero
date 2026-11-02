/**
 * Module "Record Process"->Section "Panels", the first tab showing record
 * information
 * 
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules.Record Panels
 * @version 2021.05.17
 */
cargohub.record_panels = donrey.BASE._extend(function () {
    // private
    var self = this,
            c = null,
            master = cargohub.record_process,
            // ids of all the panels - "open" - has been created and visible, "closed" -
            // exists but in a closed state, (missing: - not yet created)
            panels = {},
            panels_ready = false,
            panels_saved_dom = {}, // To support "Edit" operation in panels, here the
            // original content is saved when entering edit mode





            // IWI manage extra section only for data panel selected module  start
            selected_row = null,
            
	// Dropzone component
	file_uploader_ready = false,
	file_uploader_instance_key = "tabfiles",
        
        
            $trainee_editor = null, // Expander containing various controls, including TinyMCE "rich_editor"
            editor_open = false, // Non-false when Editor is open (in either Create or Edit modes) - Save button should be visible
            open_row_id = null, // ID of the row that has been opened (selected one might have changed in the mean time) - Only has a value when opening existing row (not for create/duplicate)
            editor_nonempty = false, // Editor is open and non non-empty, show a warning when trying to open another row or select a template
            // Indicates if TinyMCE component is already loaded and the editor used here is initialized
            rich_editor_ready = false,
            rich_editor_instance_key = "traineedit",
            $trainee_viewer = $("#tab_trainee #document-viewer"),
            docs_viewer_Ready = false,
            viewer_open = false,
            docs_manager_priv = cargohub.application.user_Test_Privileges('issue_edit_notes'),

 
            docs_Source = new $.jqx.dataAdapter({
                url: "/record/process?action=load_list_trainee_management", // At the moment using notes backend - That might change in the future !
                data: {
                    link_id: master.config.id,
                    track: master.config.track,
                },
                datatype: "json",
                datafields: [
                    {name: 'name', type: 'string'}, // Originally called "title" on notes in the database
                    {name: 'number', type: 'string'},
                    {name: 'email', type: 'string'},
                    {name: 'training_date', type: 'string'},
                    {name: 'result', type: 'string'},
                    {name: 'score', type: 'string'},
                    {name: 'files', type: 'string'},
                ],
                id: "id",
                sortcolumn: 'version',
                sortdirection: 'desc'
            }),
            select_Row = function(row)
	{
		var edit_access = docs_manager_priv ||
			(row.edit_access == 'shared'
				|| (row.edit_access == 'org' && (row.visibility == 'dep' || row.visibility == 'org'))
				|| (row.edit_access == 'dep' && row.dep_id == user_session.cdep_id)
				|| (row.edit_access == 'user' && row.editor_id == user_session.id)
			);

        
		// If no row was previously selected, show the available commands
		if(!selected_row) {
			donrey.cancel_Delay('hide-docs-row-ops');
			$('#tab_trainee #trainee-row-ops button').jqxButton({disabled: false});
			$('#tab_trainee #trainee-row-ops').fadeIn();
		}
		selected_row = row;

		// Show or hide the "Delete" button
		if(edit_access)
			$('#tab_trainee .docs-write-ops').fadeIn();
		else
			$('#tab_trainee .docs-write-ops').fadeOut();

		// Show or hide the Web-View section (if the document has web-view enabled or not)
		if(row.wv_key)
			$('#tab_trainee #trainee-open-webview').show();
		else
			$('#tab_trainee #trainee-open-webview').hide();
	},
    	// File visibility selector TODO: Get the texts from Language files
	file_visibility_source = [
		{k: 'private', text: '<span class="access16 visi-private"></span> Private attachment, only visible to the uploader'},
		{k: 'dep', text: '<span class="access16 visi-dep"></span>Visible for the uploader\'s department'},
		{k: 'org', text: '<span class="access16 visi-org"></span>Everyone in the owner organization can see it'},
		{k: 'shared', text: '<span class="access16 visi-shared"></span>Organizations sharing this '+master.config.type.name},
		{k: 'public', text: '<span class="access16 visi-public"></span>No visibility restrictions'}
	],

	/**
	 * A file row has been selected - Store information in preparation for a double-click ( Configure Window ), or for clicking on one of the toolbar buttons or the download link
	 */
	select_File = function(row)
	{
		if(!selected_row) {
			donrey.cancel_Delay('hide-bar_files-select');
			if(edit_privilege)
				$('#tab_trainee #files-row-editops').fadeIn();
			$('#tab_trainee #files-row-ops button').jqxButton({disabled: false});
			$('#tab_trainee #files-row-ops').fadeIn();

		}
		selected_row = row;
		// Prepare the download link
		$("#tab_trainee #dl-link").attr('href', "/files?action=download_attachment&ch="+selected_row.chash+"&qp="+donrey.qp_conv(selected_row.id));
	},
        select_Clear = function()
	{
		$('#tab_trainee #trainee-row-ops button').jqxButton({disabled: true});

		donrey.single_Delay('hide-docs-row-ops', function() {
			$('#tab_trainee #docs-row-ops').fadeOut();
		}, 500);

		selected_row = null;
	},
          
            /**
             * Loads selected document "body" into the existing editor. If currently no body, just use the editor with a blank result
             */
            load_Body = function ()
            {
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
            },
            // IWI manage extra section only for data panel selected module  end




            /**
             * Initializes the Panels object, containing record Xfields - jqxDockng
             * interface, with automatic saving of panel state (open, minimized, closed,
             * position) - Tabs can have an "Edit" function in them, allowing ajax
             * replacing of content with a server-sent form - Closed panels can be
             * reopened from the main menu, as well as shortcut "locate" buttons; The
             * locating function also moves to #panels tab when used
             */
            init_Panels = function ()
            {
                $("#tab_panels #panels-docker").removeClass("loading-pleasewait");
                $("#tab_panels #panels-docker").jqxDocking({
                    theme: cargohub.jqxtheme,
                    mode: "docked",
                    width: cargohub.fullwidth + 8,
                    orientation: 'horizontal',
                    windowsOffset: 8
                });

                // automatically add the Panels to the Docking
                $("#tab_panels #panels-content > div").each(function () {
                    panel_Operation('init', this.id);
                });

                // and arrange the layout to the saved state (if it exists)
                if (self.config.panel_layout) {
                    // Variable fix: Because json_encode creates an empty php array as a
                    // PHP array but in this case it should be an object, detect and fix
                    // the empty entries "floating, panel0, panel1" and replace them
                    // with objects
                    if ($.isArray(self.config.panel_layout.floating))
                        self.config.panel_layout.floating = {};
                    if ($.isArray(self.config.panel_layout.panel0))
                        self.config.panel_layout.panel0 = {};
                    if ($.isArray(self.config.panel_layout.panel1))
                        self.config.panel_layout.panel1 = {};
                } else {
                    // Default config, will be constructed in the next area
                    self.config.panel_layout = {floating: {}, orientation: "horizontal", panel0: {}, panel1: {}};
                }

                // On the PHP side it is not known before loading the template which
                // panels are actually available (as they depend on the record type,
                // access rights, selected organization perspective),
                // Check here if the panel ID's from "panel_layout" actually exist in
                // the DOM before attempting to importLayout, to avoid the dreaded
                // "Invalid JSON String." message.
                var panel_dom_list = new Array, index = -1, place_in_panel = null;
                $("div.jqx-docking-panel > div.jqx-window").each(function () {
                    panel_dom_list.push($(this).attr('id'));
                });

                // Scan the panel_layout object (panel0, panel1) and clean entries which
                // are not available in the template
                for (c in self.config.panel_layout.panel0) {
                    index = $.inArray(c, panel_dom_list);
                    if (index !== -1)
                        panel_dom_list.splice(index, 1);
                    else
                        delete self.config.panel_layout.panel0[c];
                }
                for (c in self.config.panel_layout.panel1) {
                    index = $.inArray(c, panel_dom_list);
                    if (index !== -1)
                        panel_dom_list.splice(index, 1);
                    else
                        delete self.config.panel_layout.panel1[c];
                }

                // Any leftovers (present in dom but not in the saved state) are added
                // to the layout in their default positions
                if (panel_dom_list.length) {
                    for (c = 0; c < panel_dom_list.length; c++) {
                        place_in_panel = $("div.jqx-docking-panel > div#" + panel_dom_list[c]).data('panel');
                        self.config.panel_layout["panel" + place_in_panel][panel_dom_list[c]] = {collapsed: false};
                    }
                }

                $("#tab_panels #panels-docker").jqxDocking('importLayout', JSON.stringify(self.config.panel_layout));

                // The Edit all / Save all panels buttons (initially hidden)
                $("#tab_panels div.multi-commands button").jqxButton({
                    theme: cargohub.jqxtheme
                })
                        .on('click', function () {
                            var multicmd = $(this).data('op');
                            $('#tab_panels span.panel-cmds button[data-cmd="' + multicmd + '-xf"]:visible').each(function () {
                                $(this).trigger('click');
                            });
                        });
                // When having access to edit, FadeIn the Multi-Edit slowly (to attract
                // a bit attention of it's existence)
                if (cargohub.application.user_Test_Privileges('issue_edit_xfields'))
                    $('#tab_panels .multi-commands button[data-op="edit"]').fadeIn('slow');

                // Tooltips on elements inside the tab
                $("#tab_panels .info-tip").each(cargohub.components.ui.add_Tooltip);
                // InfoWindow buttons inside panels
                $('#tab_panels .info-window').each(cargohub.components.ui.button_InfoWindow);
                // Wall-of-text popup on fields with a lot of text
                $('#tab_panels .walloftext-view').each(cargohub.components.ui.walloftext_view);
                panel_ready_for_parent(cargohub.record_process.config.type.base_id, cargohub.record_process.config.id);
                panels_ready = true;



            },
            // IWI manage extra section only for data panel selected module start

            init_Toolbar = function ()
            {
                $("#tab_panels div.show_extra_manage_sec button").jqxButton({
                    theme: cargohub.jqxtheme
                })
                        /**
                         * Event: Toolbar button is clicked
                         */
                        .on('click', function () {
                            switch ($(this).data('op')) {
                                // Create new blank document command - Initializes the editor in create mode
                                case "trainee-create":
                                    //  master.clear_DataTable($("#tab_trainee div.jqx-datatable")); // Also unselect row if one is selected (which hides unneeded buttons)
                                    // open_row_id = null;
                                    // copy_mode = false;

                                    // if ($trainee_viewer && viewer_open)
                                    //     close_Viewer(true);

                                    // $('#tab_trainee .docs-write-ops').fadeIn(); // Make sure Save is enabled
                                    // init_Editor({
                                    //     title: "Create a Trainee",
                                    //     icon: "add.png",
                                    //     mode: "create"
                                    // });

                                    $('#tab_trainee #trainee-create-op').fadeOut(); // Hide the "Create" button when the editor is active, will be shown again on Save or Close
                                    //$('label[for="error"]').css('visibility', 'visible');
                                    break;

                                    // Create a copy of the existing open document, which must be saved under a new name - by default, the system adds " (copy)" to the existing name
                                // case "trainee-copy":
                                //     open_row_id = selected_row.uid;
                                //     copy_mode = true; // ID is ignored, so a new document is created instead

                                //     if ($trainee_viewer && viewer_open)
                                //         close_Viewer(true);

                                //     $('#tab_trainee .docs-write-ops').fadeIn(); // Make sure Save is enabled
                                //     init_Editor({
                                //         title: "Create copy of document: " + selected_row.name,
                                //         icon: "page_copy.png",
                                //         mode: "edit",
                                //         overrides: {
                                //             "name": selected_row.name + " (copy)",
                                //             "version": 1 // It's first version of the copy !
                                //         }
                                //     });
                                //     $('#tab_trainee #trainee-create-op').fadeOut();
                                //     break;

                                    // Open document for editing or viewing (determined inside the open_Document function)
                                // case "trainee-open":
                                //     open_row_id = selected_row.uid;
                                //     open_Document();
                                //     break;

                                    // Temporary - Test of Viewer
                                // case "trainee-view":
                                //     if (editor_open)
                                //         close_Editor(true);
                                //     $('#tab_trainee .docs-write-ops').hide(); // No "Save" function in View mode
                                //     $('#tab_trainee #trainee-open-ops').fadeIn();

                                //     view_Document({
                                //         title: "Viewing document: " + selected_row.name
                                //     });

                                //     $('#tab_trainee #trainee-create-op').fadeOut();
                                //     break;

                                    // Opens the document in a new window/tab in Webview mode
                                // case "trainee-webview-newwin":
                                //     var win = window.open("/?action=document&qp=" + donrey.qp_conv(selected_row.uid) + "&key=" + selected_row.wv_key, '_blank');
                                //     win.focus();
                                //     break;

                                    // Deletes existing document (Manager privilege is required)
                                case "trainee-delete": //
                                    if (!confirm("Are you sure you want to delete this document ?"))
                                        return;
                                    delete_Document();
                                    break;

                                    // Save existing editor content (If no changes is done, a warning is thrown, to avoid unnecessary database operations/logging)
                                case 'trainee-save':
                                    save_Editor();
                                    break;

                                    // Save with Version update
                                // case 'trainee-upver':
                                //     save_Editor(true);
                                //     break;

                                    // Closes the Editor without saving (discards existing content - Warns if new or existing document has been changed, or when copy mode is active)
                                    // Close the Viewer when document is opened in View-only mode
                                // case 'trainee-close':
                                //     alert("editor_open"+editor_open);
                                //     alert("viewer_open"+viewer_open);

                                //     if (editor_open)
                                //         close_Editor();
                                //     if (viewer_open)
                                //         close_Viewer();
                                //     break;
                            }
                        });

                // Checkbox that adds "classic" notes to the Documents grid (since they are really the same table) - Once a note is saved as a document, it's not visible anymore in notes
                // Only user with Manage access can convert Notes into Docs (as it's equivalent to a Delete+Create operation)
                if (docs_manager_priv) {
                    $('#tab_trainee #trainee-import').jqxCheckBox({
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
                                master.refresh_DataTable($("#tab_trainee div.jqx-datatable"));
                            })
                            .find("div.jqx-checkbox-default").css('margin-top', "2px");
                }
            },
            init_Editor = function (config)
            {
                var row_data = $.extend({}, selected_row);

                if ($trainee_editor) {
                    // If already initialized just change jqxExpander according to config
                    if (config && config.expander)
                        $trainee_editor.jqxExpander(config.expander);
                } else {
                    // Otherwise, start full initialization
                    $trainee_editor = $("#tab_trainee #document-editor");
                    if (!config.expander)
                        config.expander = {
                            disabled: false,
                            expanded: false
                        };
                    config.expander.theme = cargohub.jqxtheme;
                    $trainee_editor.jqxExpander(config.expander);

                    // Since the HTML inside the expander is permanent, initialize widgets:

                    // Visibility selector
//                    $('#trainee-visibility', $trainee_editor).jqxDropDownList({
//                        theme: cargohub.jqxtheme,
//                        source: doc_Visibility_Source,
//                        displayMember: "text", valueMember: "k",
//                        width: 400, height: 24, autoDropDownHeight: true,
//                        animationType: "fade"
//                    });

                    // Webview toggle
//                    $("#trainee-webview-state", $trainee_editor).jqxSwitchButton({
//                        theme: cargohub.jqxtheme,
//                        height: 25, width: 75
//                    })
//                            .on('change', function (event) {
//                                // Check ALL if "event.args.check" is true, and revert to the currently selected deps ( or none selected? ) if false
//                                if (event.args.check)
//                                    $(".trainee-webview-info", $trainee_editor).show();
//                                else
//                                    $(".trainee-webview-info", $trainee_editor).hide();
//                            });

                    // Webview secure mode toggle
//                    $("#trainee-webview-secure", $trainee_editor).jqxSwitchButton({
//                        theme: cargohub.jqxtheme,
//                        height: 25, width: 75
//                    });

                    // Publich secure mode toggle
//			$("#trainee-publish-secure", $trainee_editor).jqxSwitchButton({
//				theme: cargohub.jqxtheme,
//				height: 25, width: 75
//			});

                    // Editing access selector
//                    $('#trainee-edit_access', $trainee_editor).jqxDropDownList({
//                        theme: cargohub.jqxtheme,
//                        source: doc_Edit_access_Source,
//                        displayMember: "text", valueMember: "k",
//                        width: 400, height: 24, autoDropDownHeight: true,
//                        animationType: "fade"
//                    });

                    // Template selector (only in create mode)
//                    $("#trainee-template", $trainee_editor).jqxDropDownList({
//                        theme: cargohub.jqxtheme,
//                        source: templates_Source,
//                        displayMember: "description", valueMember: "id",
//                        placeHolder: "(Loading templates)",
//                        width: 853, dropDownWidth: 853,
//                        animationType: "fade", enableBrowserBoundsDetection: true,
//                        disabled: true
//                    })
                    /**
                     * Event: The list of templates has finished loading - Enabled the selector
                     */
//                            .on('bindingComplete', function () {
//                                if (templates_Source.records.length == 0)
//                                    $(this).jqxDropDownList({
//                                        placeHolder: "(There are no organization Document templates for this record type)",
//                                        disabled: true
//                                    });
//                                else {
//                                    $(this).jqxDropDownList({
//                                        placeHolder: "Select a template to set Content and Description:",
//                                        dropDownHeight: templates_Source.records.length > 7 ? 184 : null,
//                                        autoDropDownHeight: templates_Source.records.length <= 7 ? true : false,
//                                        disabled: false
//                                    });
//                                }
//                            })
                    /**
                     * Event: A template is selected. Grab the information and fill in the document area
                     */
//                            .on("select", function (e) {
//                                if (e.args && e.args.item) {
//                                    // Check if Compose was already changed - Give a warning that override will happen
//                                    if (editor_nonempty)
//                                        if (!confirm("This document was already edited or contains another template.\nAre you sure you want to overwrite with new template?"))
//                                            return;
//
//                                    // Template was selected - Read the rendered template (Title/Body) and setup e-mail
//                                    $.get("/global/operations?action=Render_Template", {
//                                        id: e.args.item.originalItem.id,
//                                        org_id: master.config.perspective, // Only relevant when superuser is logged in
//                                        rt_id: master.config.type.base_id ? master.config.type.base_id : master.config.type.id,
//                                        rec_id: master.config.id
//                                    }, function (r, textStatus, jqXHR) {
//
//                                        $("#trainee-description", $trainee_editor).val(r.title);
//                                        if (r.body.length > 0)
//                                            master.rich_editor.content_Write(rich_editor_instance_key, r.body);
//                                        // Short timeout before focus to allow TinyMCE to sort itself out
//                                        setTimeout(function () {
//                                            master.rich_editor.focus();
//                                        }, 100);
//                                        editor_nonempty = true;
//
//                                    }, 'json')
//                                            .fail(cargohub.its.http_fail);
//                                }
//                            });
                }

                // Mark the editor as open (in one of the specified modes)
                editor_open = config.mode;

                $('#trainee-title, #trainee-version, #trainee-description, #trainee-webview-link', $trainee_editor).addClass(cargohub.input_theme_classes);
                $('#trainee-version', $trainee_editor).each(cargohub.components.ui.numbers_input);

                $('#trainee-webview-link', $trainee_editor).on('click', function () {
                    $(this).select();
                });
                $(".calendar").each(cargohub.components.ui.calendar_input);
                // Set/Change title (header)
                if (config.title)
                    $trainee_editor.jqxExpander('setHeaderContent', '<img src="/img/icons16/' + (config.icon || 'document_image_ver.png') + '" alt="[o]" class="inline-icon"> ' + config.title);

                switch (config.mode) {
                    case 'create':
                        row_data = null;
                        editor_nonempty = false;
                        

                        // Empty Title/Subject
//                        $('#trainee-title, #trainee-description', $trainee_editor).val("");
                        // Default version is "1" (written as 1.0)
                        var def_version = 1;
//                        $("#trainee-version", $trainee_editor).val(def_version.toFixed(1));

                        // Version up button is disabled while creating
                        $('#trainee-cmd-upver').hide();

                        // Default visibility and Edit access is (v)"Org" and (e)"Editor only"
//                        $('#trainee-visibility', $trainee_editor).jqxDropDownList('selectIndex', 2);
//                        $('#trainee-edit_access', $trainee_editor).jqxDropDownList('selectIndex', 0);

                        // Webview is off for new documents
//                        $(".trainee-webview-info", $trainee_editor).hide();
//                        $("#trainee-webview-state", $trainee_editor).val(false);
//                        $("#trainee-webview-link", $trainee_editor).val("-=- Save your document to receive a link -=-");
//                        $("#trainee-webview-secure", $trainee_editor).val(true);
//                        $("#trainee-publish-secure", $trainee_editor).val(true);

                        // Template line is always reset to "no selection" so it's ready to select (even with only one option available)
                        $("#trainee-tpl-line", $trainee_editor).show();
//                        $("#trainee-template", $trainee_editor).jqxDropDownList('clearSelection');

                        // Finally, clear editor
                        if (rich_editor_ready)
                            master.rich_editor.content_Write(rich_editor_instance_key, "\n");
                        break;

                    case 'edit':
                        // Check if there's overrides or defaults for the data/values - Process "row_data" before editing
                        if (config.overrides)
                            row_data = $.extend(row_data, config.overrides);

                        // Fill the doc. Name, Description and Version
//                        $('#trainee-title', $trainee_editor).val(row_data.name);
//                        $('#trainee-description', $trainee_editor).val(row_data.description);
                        if (!row_data.version)
                            row_data.version = 1;
//                        $("#trainee-version", $trainee_editor).val(row_data.version.toFixed(1));

                        // Version up button is enabled for editing
                        $('#trainee-cmd-upver').show();

                        // Visibility and Editing access
//                        for (c = 0; c < doc_Visibility_Source.length; c++)
//                            if (doc_Visibility_Source[c].k == row_data.visibility)
//                                $('#trainee-visibility', $trainee_editor).jqxDropDownList('selectIndex', c);
//                        for (c = 0; c < doc_Edit_access_Source.length; c++)
//                            if (doc_Edit_access_Source[c].k == row_data.edit_access)
//                                $('#trainee-edit_access', $trainee_editor).jqxDropDownList('selectIndex', c);

                        // Webview related (second column)
//                        if (row_data.wv_key) {
////                            setTimeout(function () {
////                                $("#trainee-webview-state", $trainee_editor).jqxSwitchButton('check');
////                            }, 100);
//                            $("#trainee-webview-link", $trainee_editor).val("https://www.cargohub.aero/?action=document&qp=" + donrey.qp_conv(open_row_id) + "&key=" + row_data.wv_key);
//
//
//
//                        } else {
////                            setTimeout(function () {
////                                $("#trainee-webview-state", $trainee_editor).jqxSwitchButton('uncheck');
////                            }, 100);
//                            $("#trainee-webview-link", $trainee_editor).val("-=- Save your document to receive a link -=-");
//                        }

//                        console.log(row_data);
//                        var value = row_data.wv_publish_for;
//                        $("input[name=publish_for][value=" + value + "]").prop('checked', true);
////                                checkValueToshoForPullish();
//                        if (value != 'private') {
//                            $('.pfdsc').css({"display": "block"});
//                        } else {
//                            $('.pfdsc').css({"display": "none"});
//                        }
//                        if (row_data.wv_publish_for_ids != '') {
//                            var wv_publish_for_ids = $.parseJSON(row_data.wv_publish_for_ids);
//                            console.log(wv_publish_for_ids);
//                            $.each(wv_publish_for_ids, function (i, val) {
//                                $("input:checkbox[name='publish_for_department[]'][value='" + val + "']").prop('checked', true);
//                            });
//                        }

//                        console.log('row_data',row_data);
                        // Webview related (fourth column)
//                        if (row_data.wv_publish == '1') {
//                            console.log('yes', row_data.wv_publish);
//                            setTimeout(function () {
//                                $("#trainee-publish-secure", $trainee_editor).jqxSwitchButton('check');
//                            }, 100);
//                        } else {
//                            console.log('no ', row_data.wv_publish);
//                            setTimeout(function () {
//                                $("#trainee-publish-secure", $trainee_editor).jqxSwitchButton('uncheck');
//                            }, 100);
//
//                        }




//                        $("#trainee-webview-secure", $trainee_editor).val(row_data.wv_secure);
//			$("#trainee-publish-secure", $trainee_editor).val(row_data.wv_publish);
                        $("#trainee-tpl-line", $trainee_editor).hide();

                        // If editor already initialized (moving from another record, reload new body)
                        if (rich_editor_ready)
                            load_Body();
                        break;
                }

                // Finished initialization ( or reconfiguration ) - Show the expander and expand
                $trainee_editor.show().jqxExpander('enable');
                $trainee_editor.on('expanded', function () {
                    // When first expanded, initialize Content editor
                    if (!rich_editor_ready) {
                        master.rich_editor.create_Instance(rich_editor_instance_key,
                                {// Config
                                    container: $("#trainee-content", $trainee_editor),
                                    params: {
                                        // Only useful for TinyMCE
                                        tinyMCE: {
                                            plugins: "link autolink autoresize paste textcolor image hr fullscreen lists table colorpicker charmap searchreplace insertdatetime",
                                            toolbar: "bold italic underline | alignleft aligncenter alignright | formatselect fontselect fontsizeselect | hr link image table bullist numlist charmap insertdatetime | forecolor backcolor removeformat | undo redo | searchreplace fullscreen"
                                        }
                                    },
                                    print: {
                                        enabled: true // Enable printing function
                                    }
                                },
                                {// Events
                                    on_init: function () {
                                        // If first initialization starts in "edit" mode, load content. If not, just enable the editor
                                        $("#trainee-content-placeholder", $trainee_editor).hide();
                                        if (config.mode == 'edit') {
                                            load_Body();
                                        } else
                                            master.rich_editor.enable(rich_editor_instance_key);

                                        rich_editor_ready = true;
                                    },
                                    on_change: function () {
                                        editor_nonempty = true;
                                    }
                                }
                        );
                    }
                });
                $trainee_editor.jqxExpander('expand');

                // Add tooltips for relevant elements
                $(".info-tip", $trainee_editor).each(cargohub.components.ui.add_Tooltip);

                // Show the Save/Close controls
                $('#trainee-open-ops button').jqxButton({disabled: false});
                $('#trainee-open-ops').fadeIn();
            },
            // Becomes true when document is copied under a new name
            copy_mode = false,
            /**
             * Saves the content of the document editor (name, description, content)
             */

            save_Editor = function ()
            {
                if(!$('#trainee_name').val()){
                    return cargohub.alert("The name field is required !");
                }
                // email validaction
                var email = $('#trainee_email').val();
                var pattern = /^\b[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b$/i

                if(email){
                    if(!pattern.test(email)){
                        return cargohub.alert("The email is not valid !");
                    }
                }
                var submit_data = {
                    'link_id': master.config.id,
                    'track_id': master.config.track,
                    'trainee_name': $('#trainee_name').val(),
                    'trainee_email': $('#trainee_email').val(),
                    'trainee_files':   $('#trainee_files')[0].files[0] ? $('#trainee_files')[0].files[0].name : '',
                    'trainee_phone': $('#trainee_phone').val(),
                    'trainee_score': $('#trainee_score').val(),
                    'training_date': $('#training_date').val(),
                    'trainee_management_id': $('#trainee_management_id').val(),
                    'trainee_result': $('input[type=radio][name=trainee_result]:checked').val(),
                };
                console.log(submit_data);
                // submit the add/edit form using ajax
                $($trainee_editor).find('form').ajaxSubmit({
                    dataType: 'json',
                    data: submit_data,
                    // beforeSubmit: function (formData, jqForm, options)
                    // {
                        
                    //     // console.log("695 jqForm",jqForm);
                    //     // console.log("695 options",options);
                    //     // if (!$('#trainee-title', $trainee_editor).val())
                    //     //     return cargohub.alert("The document must have a name or title !");

                    //     // var doc_desc = $("#trainee-description", $trainee_editor).val();
                    //     // if (doc_desc && doc_desc < 5)
                    //     //     return cargohub.alert("A document description is not necessary, but when completed it must be longer than 5 characters");

                    //     // // Check if Title is too long ( over 100 characters ) and refuse names which are too long.
                    //     // if ($("#trainee-title", $trainee_editor).val().length > 100)
                    //     //     return cargohub.alert("Document names longer than 100 characters are currently not supported. Please shorten it.");

                    //     // if (doc_content.length < 20)
                    //     //     return cargohub.alert("Document content is empty or too short. Minimum 20 characters needed");

                    //     return true;
                    // },
                    success: function (x) {
                //         master.refresh_DataTable($("#tab_trainee div.jqx-datatable"), true); // true = do not clear\
				// master.toolbar_Report("#tab_trainee", x.response);

				// close_Editor();

                //         cargohub.components.spinner.On("Success!", x.response, 2500, function() {
                //             cargohub.components.spinner.Off();
                //         });
//                        location.reload();
                       
                        // master.refresh_DataTable($("#tab_trainee div.jqx-datatable"), true); // true = do not clear

                        // // TODO - Resolve the tab id to idx first
                        // //					if(x.count)
                        // //						cargohub.components.hashnav.update_Tab_Header(5, "Notes <span class='fiftycent'>["+x.count+"]</span>", "/img/icons16/note.png");
                        //                         // Finally, show the operation result
                        // master.toolbar_Report("#tab_trainee", x.response);

                        // close_Editor();
                        // cargohub.components.spinner.On("Success!", x.response, 2500, function () {
                        //     cargohub.components.spinner.Off();
                        // });
                        // // Show the "Create" button again
                        // $('#tab_trainee #trainee-create-op').fadeIn();
                        // location.reload();
                    },
                    error: cargohub.its.http_fail
                });
                location.reload();
            },
             
            /**
             * Minimize and close the editor if it was open.
             */
            close_Editor = function (transition)
            {   
                if (!$trainee_editor) // Not yet initialized
                    return;

                // TODO: Check if changes have been made, and warn if so (to avoid closing unsaved document)
                // Minimize and close the editor
                if ($trainee_editor.jqxExpander('expanded')) {
                    $trainee_editor.on('collapsed', function () {
                        // When finishing collapse... hide the editor
                        $trainee_editor.hide();
                        // ... and disable the event
                        $trainee_editor.off('collapsed');
                    });
                    $trainee_editor.jqxExpander('collapse');
                } else {
                    // If already collapsed, just hide it
                    $trainee_editor.hide();
                }

                // Also fadeout the Save/Close buttons
                $('#tab_trainee #trainee-open-ops button').jqxButton({disabled: true});
                $('#tab_trainee #trainee-open-ops').fadeOut();

                if (!transition) {
                    if (selected_row)
                        $('#tab_trainee #trainee-row-ops').fadeIn();
                    // Show the "Create" button in case it was hidden by a previous operation
                    $('#tab_trainee #trainee-create-op').fadeIn();
                }

                // editor_nonempty = false;
                // editor_open = false;
                open_row_id = null;
                // copy_mode = false;
                return true;
            },
            /**
             * Open the document viewer (No form, just the data in a static, read-only format)
             */
            // view_Document = function (config)
            // {
            //     // alert('view_Document');
            //     if (docs_viewer_Ready) {
            //         // Update expander with new settings (if necessary)
            //         if (config && config.expander)
            //             $trainee_viewer.jqxExpander(config.expander);

            //     } else {
            //         // Init the expander itself on first use
            //         if (!config.expander)
            //             config.expander = {
            //                 disabled: false,
            //                 expanded: false
            //             };
            //         config.expander.theme = cargohub.jqxtheme;
            //         $trainee_viewer.jqxExpander(config.expander);

            //         docs_viewer_Ready = true;
            //     }
            //     $trainee_viewer.show();

            //     // Set/Change title (header)
            //     if (config.title)
            //         $trainee_viewer.jqxExpander('setHeaderContent', '<img src="/img/icons16/' + (config.icon || 'document_image_ver.png') + '" alt="[:]" class="inline-icon"> ' + config.title);

            //     var row_data = $.extend({}, selected_row);
            //     // Check if there's overrides or defaults for the data/values - Process "row_data" before rendering the template
            //     if (config.overrides)
            //         row_data = $.extend(row_data, config.overrides);

            //     row_data.dt_format = moment(row_data.dt).format("YYYY-MM-DD (dddd) HH:mm:ss") + ' <span class="gray">(' + today.diff(row_data.dt, 'days') + ' days ago)</span>';

            //     // Render the doc viewer template into the body of the expander
            //     $trainee_viewer.jqxExpander('setContent', $('#template-trainee-viewer').html().present(row_data));

            //     // Document body is not loaded in the grid, so load it now
            //     $("#document-viewer-body", $trainee_viewer).html("<div class='center'>(loading, please wait...)</div>");
            //     $.get('/global/note', {
            //         action: "Load_Body",
            //         id: row_data.uid,
            //         content_type: 'html'
            //     }, function (body) {
            //         if (body.length > 0)
            //             $("#document-viewer-body", $trainee_viewer).html(body);
            //         else
            //             $("#document-viewer-body", $trainee_viewer).html("<span class='gray'>(This document has no content)</span>");
            //     });

            //     // Add tooltips for relevant elements
            //     $(".info-tip", $trainee_viewer).each(cargohub.components.ui.add_Tooltip);

            //     $trainee_viewer.jqxExpander('expand');

            //     // Show the Close control
            //     donrey.cancel_Delay('hide-tasks-open-ops');
            //     $('#tab_trainee #trainee-open-ops button').jqxButton({disabled: false});
            //     $('#tab_trainee #trainee-open-ops').fadeIn();

            //     viewer_open = true;
            // },
            /**
             * Close the document Viewer expander
             */
            close_Viewer = function (transition)
            {
                if (!$trainee_viewer) // Not yet initialized
                    return;

                if ($trainee_viewer.jqxExpander('expanded')) {
                    $trainee_viewer.on('collapsed', function () {
                        // When finishing collapse... hide the editor
                        $trainee_viewer.hide();
                        // ... and disable the event
                        $trainee_viewer.off('collapsed');
                    });
                    $trainee_viewer.jqxExpander('collapse');
                } else {
                    // If already collapsed, just hide it
                    $trainee_viewer.hide();
                }

                // Also fadeout the Close buttons
                $('#tab_trainee #trainee-open-ops button').jqxButton({disabled: true});
                $('#tab_trainee #trainee-open-ops').fadeOut();

                if (!transition) {
                    if (selected_row)
                        $('#tab_trainee #trainee-row-ops').fadeIn();
                    // Show the "Create" button in case it was hidden by a previous operation
                    $('#tab_trainee #trainee-create-op').fadeIn();
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
                $('#tab_trainee #trainee-create-op').fadeOut();


                if (row)
                    selected_row = row;
                    console.log('selected_row',selected_row);
                   var trainee_phone = +selected_row.number;
                   var trainee_score   = +selected_row.score;
                   var uid   = +selected_row.uid;
                    $('#trainee_name').val(selected_row.name);
                    
                    $('#trainee_email').val(selected_row.email);
                    $('#trainee_phone').val(trainee_phone);
                    $('#trainee_management_id').val(uid);
                    $('#trainee_score').val(trainee_score);
                    $('#download_link').css('display','inline-block');
                    $('#download_link').html(selected_row.files);
                    $("#labdownload").css('display','inline-block');
                    $(".training_date_div").html("");
                    $(".training_date_div").html("<div id='xfd_date_train' style='width: 400px;'  class='form-control calendar training_date' name='training_date' data-value=''></div>");
                    $('.training_date').attr("data-value",selected_row.training_date);

                    if(selected_row.training_date != '0000-00-00'){
                        $('.training_date').attr("data-value",selected_row.training_date);
                    }else{
                        $('.training_date').attr("data-value",'');                
                    }   
                    if (selected_row.result == 'Pass') {
                        $('.trainee_pass').prop('checked', true);
                    } else {
                        $('.trainee_fail').prop('checked', true);
                    }
                    $('#tab_trainee #trainee-open-ops, #tab_trainee .docs-write-ops').fadeIn();
                    close_Viewer(true);
                    init_Editor({
                        title: "Edit document: " + selected_row.name,
                        icon: "pencil.png",
                        mode: "edit"
                    });
                   /*
                    var edit_access = docs_manager_priv ||
                    (selected_row.edit_access == 'shared'
                    || (selected_row.edit_access == 'org' && (selected_row.visibility == 'dep' || selected_row.visibility == 'org'))
                    || (selected_row.edit_access == 'dep' && selected_row.dep_id == user_session.cdep_id)
                    || (selected_row.edit_access == 'user' && selected_row.editor_id == user_session.id)
                    );

                    if (edit_access) {
                        // Edit mode
                        close_Viewer(true);
                        $('#tab_trainee #trainee-open-ops, #tab_trainee .docs-write-ops').fadeIn();

                        open_row_id = selected_row.uid;

                        init_Editor({
                            title: "Edit document: " + selected_row.name,
                            icon: "pencil.png",
                            mode: "edit"
                        });
                    } else {
                        // Read-only mode
                        close_Editor(true);

                        $('#tab_trainee .docs-write-ops').hide(); // No "Save" function in View mode
                        $('#tab_trainee #trainee-open-ops').fadeIn();

                        // View mode, switch to the task viewer
                        view_Document({
                            title: "Viewing document: " + selected_row.name
                        });
                    }
                */


            },
            delete_Document = function ()
            {
                $.post("/record/process?action=delete_trainee_management", {
                    link: "issue",
                    link_id: master.config.id, // Linked record ID
                    id: selected_row.uid, // Document ID
                }, function (x) {
                    // master.refresh_DataTable($("#tab_trainee div.jqx-datatable"));
                    // selected_row = null;
                    // master.toolbar_Report("#tab_trainee", x.response);
                    // close_Editor();
                    cargohub.components.spinner.On("Success!", x.response, 2500, function () {
                        cargohub.components.spinner.Off();
                    });
                    location.reload();

                }).fail(cargohub.its.http_fail);
            },
            
            
            

	upload_default_visibility_index = 2,
	master_org_index = null,

	/**
	 * Setup for the upload window, containing Dropzone and two dropdown selectors
	 */
	setup_Upload = function($window)
	{
		var
		$access_orgs = $('#uploads-access_orgs', $window),
		uploaded_files = {
			error: 1	// Beings with Error code "1" - Empty dropzone
		},
		/**
		 * Helper: Links a list of succesfully uploaded files to the current record
		 * @param on_success_CB Callback for when the linking is successful
		 */
		link_pre_files = function(on_success_CB) {
			$.post("?action=link_pre_files", {
				file_ids: JSON.stringify(uploaded_files.ids),
				upload_size: uploaded_files.size,
				org_id: master.config.perspective, // Current organization (perspective for admin - Not useful for non-admin as it will default to the user's organization anyway)
				visibility: $('#uploads-visibility', $window).val(),
				access_orgs: $access_orgs.val()
			}, function(x) {
				if(on_success_CB)
					on_success_CB(x);
			})
			.fail(cargohub.its.http_fail);
		};

		// Setup Dropzone instance
		if(!file_uploader_ready) {
            console.log(' master file_uploader 1050 ',master.file_uploader);
			master.file_uploader.create_Instance(file_uploader_instance_key,
				{	// Config
                    
					container: $("#files-dropzone", $window),
					height: 290
				},
				{	// Events
					/**
					 * Ready event - When Dropzone finished loading, stuff can be performed (For example adding mock-files?)
					 */
					on_ready: function() {
						file_uploader_ready = true;
					},
					/**
					 * Content event - At least one file has been dropped, or a mock-file is prepared
					 */
					on_content: function() {
						$("#files-dropzone-helper", $window).css('visibility', "visible").fadeTo(1);
						$('#modal_files-upload_cancel', $window).css({
							backgroundColor: "#C40",
							color: "white"
						}).text("Cancel uploads");
					},
					/**
					 * Empty event - The dropzone is empty of files or mock-files. Reset the window
					 */
					on_empty: function() {
						uploaded_files = {error: 1};

						$('#modal_files-upload_ok', $window).jqxButton({disabled: false}).jqxButton('render');
						$('#modal_files-upload_cancel', $window).css({
							backgroundColor: "inherit",
							color: "inherit"
						}).text("Close the window");

						$("#files-dropzone-helper", $window).fadeTo(0);
					},
					/**
					 * File Sending event - A file has begun uploading. Disable the OK button until upload is complete
					 */
					on_filesending: function() {
						$('#modal_files-upload_ok', $window).jqxButton({disabled: true}).jqxButton('render');
					},
					// - on_filesuccess		A file has completed uploading
					// - on_fileremove		A file or mock file has been removed via a remove link
					/**
					 * Upload Complete event - All queued files have finished uploading. Allow pressing the OK button
					 */
					on_uploadcomplete: function(files)
					{
						uploaded_files = files;
						$('#modal_files-upload_ok', $window).jqxButton({disabled: false}).jqxButton('render');
					}
				}
			);
		}

		$('#uploads-visibility', $window).jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: file_visibility_source,
			displayMember: "text", valueMember: "k",
			width: 428,	height: 24, autoDropDownHeight: true,
			selectedIndex: upload_default_visibility_index,
			placeHolder: "Please select visibility for all new uploads"
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

		// Access IDs selector (for sharing mode)
		$access_orgs.jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: master.source_Sharing(function(records) {
				for(c=0; c< records.length; c++) {
					if(records[c].id == master.config.perspective) {
						// Check the item for the perspective organization and disable turning off
						master_org_index = c;
						$access_orgs.jqxDropDownList('updateAt', {
							value: master.config.perspective,
							label: records[c].name+" [my org]",
							checked: true
						}, c);
						$access_orgs.jqxDropDownList('checkIndex', c);
						$access_orgs.jqxDropDownList('disableAt', c);
					}
				}
			}),
			checkboxes: true,
			displayMember: "name", valueMember: "id",
			width: 428,	height: 24, autoDropDownHeight: true,
			renderer: function(index, label, value) {
				var sho = master.dataSources.shareList.records[index];
				label="<span class='right'>("+sho.type_id+")</span>"+label;
				if(sho.owner)
					label+=" <b>[my org]</b>";
				if(sho.disabled)
					label+=" [disabled]";
				return label;
			}
		});

		// Setup the action for "OK" button
		/**
		 * OK button event connects the uploaded files to the record
		 * (Just closing the window does not, like the cancel uploads button)
		 */
		$('#modal_files-upload_ok', $window).off('click').on('click', function()
		{
			if(uploaded_files.error)
				switch(uploaded_files.error) {
					// Dropzone is empty, nothing to do, just close the window
					case 1:
						$window.jqxWindow('close');
						return;
					// Some errors while uploading
					case 3:
						cargohub.alert("Some files failed upload or were cancelled during transfer. Check the individual items for errors.");
						return;
				}

			if(uploaded_files.ids && uploaded_files.ids.length) {
				link_pre_files(function(x) {
					// clear Dropzone as we are done, but without triggering the remove events (that includes server-side delete)
					master.file_uploader.empty(file_uploader_instance_key, true);

					// Close the window, update the attachments panel and display a message
					$window.jqxWindow('close');
					master.refresh_DataTable($("#tab_panels div.jqx-datatable"));

					if(x.count)
						cargohub.components.hashnav.update_Tab_Header(3, "Files <span class='fiftycent'>["+x.count+"]</span>", "/img/icons16/attach.png");
					// Finally, show the operation result
					master.toolbar_Report("div#tab_panels", x.response);
				});
			} else
				cargohub.alert("Unknown error in the upload mechanism. Try emptying the window with the <b class='red'>Cancel uploads</b> button");
		});

		$('#modal_files-upload_cancel', $window).off('click').on('click', function() {
			if(uploaded_files.error)
				switch(uploaded_files.error) {
					// Dropzone is empty, nothing to do, just close the window
					case 1:
						$window.jqxWindow('close');
						break;
					// Uploads in progress
					case 2:
						if(!confirm("Uploads are still in progress. Are you sure you want to cancel all uploads ?"))
							return;
						master.file_uploader.empty(file_uploader_instance_key);
						break;
					// Some errors while uploading
					case 3:
						if(!confirm("Some uploads were not completed successfully. Are you sure you want to cancel all uploads ?"))
							return;
						master.file_uploader.empty(file_uploader_instance_key);
						break;
				}
			else
				if(uploaded_files.ids && uploaded_files.ids.length) {
					if(!confirm("Files are already uploaded. Are you sure you want to discard them ?"))
						return;
					master.file_uploader.empty(file_uploader_instance_key);
				}

			$window.jqxWindow('close');
		});
	},

	/**
	 * Opens the upload window, based on Dropzone component
	 */
	files_Upload = function()
	{
		// Nothing changes between uses, just reopen existing window
		if(cargohub.components.modal.windows['files-upload']) {
			return cargohub.components.modal.reopen('files-upload', function($window) {
				// Reset visibility controls after the window is reopened (Dropzone was reset when it was originally closed, either by cancelling or by finishing uploads)
				var $access_orgs = $('#uploads-access_orgs', $window);
				$('#uploads-visibility', $window).jqxDropDownList('selectIndex', upload_default_visibility_index);
				$access_orgs.jqxDropDownList('uncheckAll');
				$access_orgs.jqxDropDownList('enableAt', master_org_index);
				$access_orgs.jqxDropDownList('checkIndex', master_org_index);
				$access_orgs.jqxDropDownList('disableAt', master_org_index);
				$access_orgs.parent().hide();
			});
		}

		// This modal doesn't have a close button, instead it has a special "Cancel" button that will remove any uploaded files
		// Instead, if pressing Ok, the uploaded files are linked to the record
		cargohub.components.modal.create('files-upload', {
			buttons: {cancel: "Close the window", ok: "Ok"},
			btn_roles: {},
			showCloseButton: false,
			btn_styles: {cancel: "float: left; margin-left: 0; background-image: none; background-color: inherit !important; color: inherit !important;"}
		});

		cargohub.components.modal.open('files-upload', {
			width: 650,	height: 480,
			title: "Upload files for: " + master.config.type.name+ " - "+master.config.track,
			content: $('#win-upload_files').html().present({
				_itc: cargohub.input_theme_classes
			}),
			callback: setup_Upload
		});
	},

	/**
	 * Opens the file attachment configuration window, used to change access for the record, set labels and perform other operations
	 */
	file_Config = function(row)
	{
		if(row)
			selected_row = row;

		selected_row.mtime_formatted = $.format.date(selected_row.mtime, cargohub.datetime_format);
		selected_row.qp = donrey.qp_conv(selected_row.id);

		cargohub.components.modal.create('file-config', {
			btn_roles: {cancel: "cancel"}
		});

		cargohub.components.modal.open('file-config', {
			width: 600,	height: 350,
			title: "Configure attached file for: " + master.config.type.name+ " - "+master.config.track,
			content: $('#template-file-config').html().present({
				_itc: cargohub.input_theme_classes,
				file: selected_row,
				master: master.config
			}),
			callback: function($window) // Setup the controls inside the window
			{
				// Visibility selector
				var visibility_index = -1, $access_orgs = $('#file-access_orgs', $window);
				for(c = 0; c< file_visibility_source.length; c++)
					if(selected_row.visibility == file_visibility_source[c].k)
						visibility_index = c;
				$('#file-visibility', $window).jqxDropDownList({
					theme: cargohub.jqxtheme,
					source: file_visibility_source,
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
				if(visibility_index == 3)
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
				$('#modal_file-config_ok', $window).off('click').on('click', function()
				{
					// submit the create/edit form using ajax
					$('.modal_content > form', $window).ajaxSubmit({
						data: {
							id: selected_row.id,
							link_id: master.config.id // ID check for link - The file is not updated if link_id is missing or wrong
						},
						dataType: 'json',
						beforeSubmit: function(formData, jqForm, options) {
							// Checks if file name has not been emptied
							if($("#file-name", $window).val().length < 5) {
								cargohub.alert("The name of the file should have at least 5 characters.");
								return false;
							}
							return true;
						},
						success: function(x) {
							if(x.success) {
								// note has been updated - Close the window / Refresh the datapanel / Update the tab title to include the returned number of notes (counter)
								$window.jqxWindow('close');
								master.refresh_DataTable($("#tab_panels div.jqx-datatable"));

								// Finally, show the operation result
								master.toolbar_Report("div#tab_panels", x.response);
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
	 * Setup the window used for pasting files (duplicate the sources) from the Clipboard into the current record
	 */
	setup_Files_Clipboard = function($window)
	{
		var
		// DataAdapter for showing the clipboard content
		fclip_dataAdapter = new $.jqx.dataAdapter({
			url: "/files?action=Clipboard",
			datatype: "json",
			datafields: [
				{name: 'file_id', type: 'int'},
				{name: 'name', type: 'string'},
				{name: 'mimetype', type: 'string'},
				{name: 'doctype', type: 'string'},
				{name: 'size', type: 'int'},
				{name: 'chash', type: 'string'},
				{name: 'added', type: 'date'}
			],
			// Initial Sorting
			sortcolumn: 'name',
			sortdirection: 'asc'
		}),

		/**
		 * Unlinks (Removes) one or more existing clipboard files (clips)
		 *
		 * @param array ids
		 * @param callback on_Complete Extra function to execute after attachment has been unlinked
		 */
		remove_From_Clipboard = function(ids, on_Complete)
		{
			$.post("/files?action=Remove_Clips", {
				file_ids: JSON.stringify(ids)
			}, function(x)
			{
				if(on_Complete)
					on_Complete(x);
			})
			.fail(cargohub.its.http_fail);
		},
		/**
		 * Copies an existing metadata of a file into the current record
		 *
		 * @param int id
		 */
		paste_File = function(clip)
		{
			$.post("?action=File_Paste", {
				link_type: 'issue',
				link_id: master.config.id,	// Linked record ID
				chash: clip.chash
			}, function(x) {
				// Nothign here; Grid is refreshed when the clipboard window is closed
			})
			.fail(cargohub.its.http_fail);
		},
		pasted = false,		// Becomes True on first paste (to refresh the grid on closing the Clipboard modal)
		pasted_ids = [];	// Used to show the prompt on closing the window

		// #files-clipboard
		master.init_DataTable($("#files-clipboard", $window), {
			// Config
			localization: {
				emptydatastring: "The clipboard is currently empty"
			},
			width: 720,
			source: fclip_dataAdapter,
			columns: [
				{hidden: true, dataField: 'doctype', text: "File type", filterable: true}, // Searchable by document type
				{dataField: 'mimetype', text: "Type", width: 50, filterable: false, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
					return '<img src="/img/mime/'+rowData.mimetype+'.png" alt="'+rowData.ext+'" />';
				}},
				{dataField: 'chash', text: "Down!", width: 50, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
					return '<a href="/files?action=download_attachment&ch='+rowData.chash+'&qp='+donrey.qp_conv(rowData.file_id)+'" title="Download this file"><img style="border: none;" src="/img/icons/download_cloud.png" alt="Down!" /></a>';
				}, sortable: false, filterable: false},
				{dataField: 'name', text: "File name", filterable: true},
				{dataField: 'file_id', text: "Operations", width: 110, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
					return	'<img style="border: none;" src="/img/icons/page_paste.png" alt="Paste" class="pasteclip-button" data-row="'+row+'" title="Paste this file" /> &nbsp; '+
							'<img style="border: none;" src="/img/icons/delete.png" alt="Del!" class="deleteclip-button" data-row="'+row+'" title="Remove from clipboard (non-destructive)" />';
				}, sortable: false, filterable: false}
			],
			rowDetails: true, initRowDetails: function(id, datarow, element, rowinfo) { // Row details renderer
				var tpl_data = clone(datarow);
				// Shiny...
				tpl_data.size = number_format(tpl_data.size);
				rowinfo.detailsHeight = 60;
				element.addClass('datatable att-details').html($("#template-clipfile-details").html().present(tpl_data)).height(rowinfo.detailsHeight);
			}
		}, {
			// Callbacks (for events happening in the datatable itself)
			"rowopen": function(row, $c) { // Opens the details on doubleclick
				$c.jqxDataTable('showDetails', $c.data('selected_index'));
			},
			"getrows": function() {
				if(fclip_dataAdapter.records.length > 0)
					$("#fclipsize").show().text(" ("+fclip_dataAdapter.records.length+")");
				else
					$("#fclipsize").text("").hide();
			}
		}, "fclip");

		// Event for the attachment deletion button
		$("#files-clipboard")
		/**
		 * The button to "Paste" the clipboard file into the record
		 */
		.on("click", ".pasteclip-button", function() {
			var row_data = fclip_dataAdapter.records[$(this).data('row')];

			if(!row_data.original_name) { // Only do this once
				paste_File(row_data);
				row_data.original_name = row_data.name;
				row_data.name = row_data.original_name + " <span class='gray'>(pasted)</span>";
				pasted_ids.push(parseInt(row_data.file_id));

				// Show the file as "Pasted"
				$("#files-clipboard").jqxDataTable('updateRow', $("#files-clipboard").data('selected_index'), row_data);
			} else
				return cargohub.alert("The file <b>"+ row_data.original_name+ "</b> has already been pasted into the record");

			pasted = true;
		})
		/**
		 * The button to "Paste" the clipboard file into the record
		 */
		.on("click", ".deleteclip-button", function() {
			if(!confirm("Are you sure you want to remove the file from Clipboard ?"))
				return;

			var file_id = fclip_dataAdapter.records[$(this).data('row')].file_id,
				srch = $.inArray(file_id, pasted_ids);
			if(srch > -1)
				pasted_ids.splice(srch, 1);

			if(fclip_dataAdapter.records.length > 0)
				$("#fclipsize").text(" ("+fclip_dataAdapter.records.length+")");
			else
				$("#fclipsize").text("").hide();

			remove_From_Clipboard([file_id], function() {
				// Refresh the Clipboard after deletion
				master.refresh_DataTable($("#files-clipboard", $window));
			});
		});

		// On Finished (Ok), the window is closed and the inline preview shows number of attachments and total size
		$('#modal_files-clipboard_ok').off('click').on('click', function()
		{
			if(pasted_ids.length > 0)
				if(confirm(pasted_ids.length+" file(s) have been pasted in the record and are still in the Clipboard.\nDo you wish to remove them ?"))
					remove_From_Clipboard(pasted_ids, function() {
						var clips_left = fclip_dataAdapter.records.length - pasted_ids.length;
						if(clips_left > 0)
							$("#fclipsize").text(" ("+clips_left+")");
						else
							$("#fclipsize").text("").hide();
					});

			// close the window
			$window.jqxWindow('close');
		});

		// On Empty Clipboard button, ask for the mass unlink of clips for the current user
		$('#modal_files-clipboard_clear').off('click').on('click', function()
		{
			if(!confirm("Are you sure you want to empty the Clipboard ?"))
				return;

			// Empty the clipboard
			$.post("/files?action=Remove_Clips", {
				empty_clipboard: true
			}, function(x)
			{
				$("#fclipsize").text("").hide();
				$window.jqxWindow('close');

				cargohub.alert("Clipboard has been emptied !", "Success !", "thumb_up.png");
			})
			.fail(cargohub.its.http_fail);
		});

		$window.on('close', function() {
			if(pasted)
				master.refresh_DataTable($("#tab_panels div.jqx-datatable"));
		});
	},




            /**
             * Initialize the Docs tab toolbar (Add, Edit, Copy, Export, Delete documents) - Future: Import documents via uploading (should recognize MS Word?)
             */
            init_Toolbar = function ()
            {
                $("#tab_trainee .toolbar button")
                        .jqxButton({theme: cargohub.jqxtheme})
                        /**
                         * Event: Toolbar button is clicked
                         */
                        .on('click', function ()
                        {
                            console.log('case',$(this).data('op'));
                            switch ($(this).data('op')) {
                                
                                case 'exportxlsx':
                                    $("#_record-docs").jqxDataTable('exportData', 'xls');
                                    break;
                                // Create new blank document command - Initializes the editor in create mode
                                case "trainee-create":
                                    document.getElementById("configform").reset();
                                    $(".training_date_div").html("");
                                    $("#trainee_management_id").val(0);
                                    $(".training_date_div").html("<div id='xfd_date_train' style='width: 400px;'  class='form-control calendar training_date' name='training_date' data-value=''></div>");
                                    $("#labdownload").css("display","none");
                                    $("#download_link").css("display","none");
                                    master.clear_DataTable($("#tab_trainee div.jqx-datatable")); // Also unselect row if one is selected (which hides unneeded buttons)
                                    open_row_id = null;
                                    copy_mode = false;

                                    if ($trainee_viewer && viewer_open)
                                        close_Viewer(true);

                                    $('#tab_trainee .docs-write-ops').fadeIn(); // Make sure Save is enabled
                                    init_Editor({
                                        title: "Create a Trainee",
                                        icon: "add.png",
                                        mode: "create"
                                    });
                                    $('#tab_trainee #trainee-create-op').fadeOut(); // Hide the "Create" button when the editor is active, will be shown again on Save or Close
                                    break;

                                    // Create a copy of the existing open document, which must be saved under a new name - by default, the system adds " (copy)" to the existing name
                                case "trainee-copy":
                                    open_row_id = selected_row.uid;
                                    copy_mode = true; // ID is ignored, so a new document is created instead

                                    if ($trainee_viewer && viewer_open)
                                        close_Viewer(true);

                                    $('#tab_trainee .docs-write-ops').fadeIn(); // Make sure Save is enabled
                                    init_Editor({
                                        title: "Create copy of document: " + selected_row.name,
                                        icon: "page_copy.png",
                                        mode: "edit",
                                        overrides: {
                                            "name": selected_row.name + " (copy)",
                                            "version": 1 // It's first version of the copy !
                                        }
                                    });
                                    $('#tab_trainee #trainee-create-op').fadeOut();
                                    break;

                                    // Open document for editing or viewing (determined inside the open_Document function)
                                case "trainee-open":
                                    open_row_id = selected_row.uid;
                                    open_Document();
                                    break;

                                    // Temporary - Test of Viewer
                                case "trainee-view":
                                    if (editor_open)
                                        close_Editor(true);
                                    $('#tab_trainee .docs-write-ops').hide(); // No "Save" function in View mode
                                    $('#tab_trainee #trainee-open-ops').fadeIn();

                                    view_Document({
                                        title: "Viewing document: " + selected_row.name
                                    });

                                    $('#tab_trainee #trainee-create-op').fadeOut();
                                    break;

                                    // Opens the document in a new window/tab in Webview mode
                                // case "trainee-webview-newwin":
                                //     var win = window.open("/?action=document&qp=" + donrey.qp_conv(selected_row.uid) + "&key=" + selected_row.wv_key, '_blank');
                                //     win.focus();
                                //     break;

                                    // Deletes existing document (Manager privilege is required)
                                case "trainee-delete": //
                                    if (!confirm("Are you sure you want to delete this document ?"))
                                        return;
                                    delete_Document();
                                    break;

                                    // Save existing editor content (If no changes is done, a warning is thrown, to avoid unnecessary database operations/logging)
                                case 'trainee-save':
                                    save_Editor();
                                    break;

                                    // Save with Version update
                                // case 'trainee-upver':
                                //     save_Editor(true);
                                //     break;

                                    // Closes the Editor without saving (discards existing content - Warns if new or existing document has been changed, or when copy mode is active)
                                    // Close the Viewer when document is opened in View-only mode
                                case 'trainee-close':
                                    if (editor_open)
                                        close_Editor();
                                    // if (viewer_open)
                                    //     close_Viewer();
                                    break;
                                    
                                    
                                    
                                
                                
                            }
                        });
                        $("#files-editops button")
                        .jqxButton({theme: cargohub.jqxtheme})
                        /**
                         * Event: Toolbar button is clicked
                         */
                        .on('click', function ()
                        {
                             console.log('case',$(this).data('op'));
                            switch ($(this).data('op')) {
                                case "upload":
                                    files_Upload();
                                    break;
			case "file-config":
				file_Config();
				break;
			// Direct operation, file is being linked into Clipboard (unless it's already there)
			case "file-copy":
				$.post("?action=File_CopyClip", {
					link_id: master.config.id,	// Linked record ID
					chash: selected_row.chash
				}, function(x) {
					// New clipboard size returned, make visible and update the number beside the "Clipboard" button
					if(x.fclipsize)
						$("#fclipsize").text(" ("+x.fclipsize+")").show();
				})
				.fail(cargohub.its.http_fail);
				break;
			case "file-remove":
				if(!confirm("Are you sure you want to delete the selected attached file?"))
					return;

				// Local Action method for now (the function needs to be aware of where the file is disconnected from)
				$.post("?action=Delete_File", {
					link_id: master.config.id,	// Linked record ID
					chash: selected_row.chash,
					org_id: master.config.perspective// Current organization (perspective for admin - Not useful for non-admin as it will default to the user's organization anyway)
				}, function(x) {
					master.refresh_DataTable($("#tab_panels div.jqx-datatable"));
					// Update counter on deletion (should be reduced by one compared to current if deleting from the perspective organization)
					if(x.update_count)
						cargohub.components.hashnav.update_Tab_Header(3, "Files"+(x.count? " <span class='fiftycent'>["+x.count+"]</span>": ""), "/img/icons16/attach.png");
					// Update clipboard size (just in case this file was in the clipboard)
					if(x.fclipsize)
						$("#fclipsize").text(" ("+x.fclipsize+")").show();
					cargohub.alert(x.response, "Success !", "thumb_up.png");
				})
				.fail(cargohub.its.http_fail);
				break;

			case "clipboard":
				cargohub.components.modal.create('files-clipboard', {
					buttons: {clear: "Empty Clipboard and Close", ok: "Finished"},
					btn_roles: {},
					showCloseButton: false,
					btn_styles: {clear: "float: left; margin-left: 0; background-image: none; background-color: #C40; color: white;"}
				});

				cargohub.components.modal.open('files-clipboard', {
					width: 735,	height: 470,
					title: "Files Clipboard",
					content: $('#win-files_clipboard').html().present({
						_itc: cargohub.input_theme_classes
					}),
					callback: setup_Files_Clipboard
				});
				break;
                            }
                        });
                        if(edit_privilege)
			$('#tab_panels #files-editops').fadeIn();

                // Checkbox that adds "classic" notes to the Documents grid (since they are really the same table) - Once a note is saved as a document, it's not visible anymore in notes
                // Only user with Manage access can convert Notes into Docs (as it's equivalent to a Delete+Create operation)
                if (docs_manager_priv) {
                    $('#tab_trainee #trainee-import').jqxCheckBox({
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
                                master.refresh_DataTable($("#tab_trainee div.jqx-datatable"));
                            })
                            .find("div.jqx-checkbox-default").css('margin-top', "2px");
                }
            },
            /**
             * Initialize the Documents DataTable. (This uses common functionality from the main Record Process module - cargohub.record_process )
             */
            init_DataTable = function ()
            {
                // console.log('sdds',docs_Source);
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
                master.init_DataTable($("#tab_trainee div.jqx-datatable"), {
                    exportSettings: {
                        columnsHeader: true,
                        hiddenColumns: false,
                        serverURL: "/?action=export",
                        characterSet: null,
                        collapsedRecords: false,
                        recordsInView: true,
                        fileName: "docs"
                    },
                   
                   
                    pageable:true,
                     pageSizeOptions: [5, 10,20, 25, 100],                    
                    pageSize: 20,  
                    height:'auto',
                    // rowNum: 5,
                    // rowList: [5, 10, 20, 30],
                    // viewrecords: true,
                    // autoheight: true,                  
                    // 
                    // Config
                    source: docs_Source,
                    columns: [           
                        {dataField: 'name', text: "Trainee Name", cellClassName: cell_highlight,'width':'25%'},
                        {dataField: 'number', text: "Phone Number", cellClassName: cell_highlight,'width':'24%'},
                        {dataField: 'email', text: "Email", cellClassName: cell_highlight,'width':'15%'},
                        {dataField: 'training_date', text: "Training Date", cellClassName: cell_highlight,'width':'10%'},
                        {dataField: 'result', text: "Result", cellClassName: cell_highlight,'width':'8%'},
                        {dataField: 'score', text: "Score (%)", cellClassName: cell_highlight,'width':'8%'},
                        {dataField: 'files', text: "Certificate", cellClassName: cell_highlight,'width':'10%'},
                    ],
                    
                    //  pageable: true,
                    //  pageSize: 5
                 rowDetails: true, initRowDetails: function(id, datarow, element, rowinfo) { // Row details renderer
                    var tpl_data = clone(datarow), metadata = null;
                    // Process labels, by splitting them, so they can be colorized in the template
                    if(tpl_data.labels) {
                        tpl_data.labels = tpl_data.labels.split(",");
                        for(c=0; c<tpl_data.labels.length; c++)
                            tpl_data.labels[c] = "<span class='attlabel'>"+$.trim(tpl_data.labels[c])+"</span>";
                        tpl_data.labels = tpl_data.labels.join(" &middot; ");
                    } else
                        delete tpl_data.labels;
                    // Shiny...
                    tpl_data.size = number_format(tpl_data.size);
                    switch(tpl_data.visibility) {
                        case 'private': tpl_data.visi_text = "Private file, visible only to the uploader"; break;
                        case 'dep': tpl_data.visi_text = "Uploader's department members"; break;
                        case 'org': tpl_data.visi_text = "The uploader's organization"; break;
                        case 'shared': tpl_data.visi_text = "Sharing this "+master.config.type.name;
                            if(tpl_data.access_orgs)
                                tpl_data.visi_text+= " ("+ tpl_data.access_orgs.split(",").length+ " organizations)";
                            break;
                        case 'public': tpl_data.visi_text = "No access restrictions"; break;
                        default: tpl_data.visi_text = "(Unknown, possible database error)";
                    }
    
                    // If the row contains image metadata (and the analyzer is of type "image" it means we have a thumbnail to display - use the "thms" version
                    if(datarow.analyzer == "image") {
                        metadata = JSON.parse(datarow.metadata);
                        if(metadata && metadata.scaler && metadata.scaler.thml)
                            tpl_data._thml = "/uimg/thml/"+ datarow.chash.substr(0,2)+ '/'+ datarow.chash.substr(2)+ '.jpg';
                        rowinfo.detailsHeight = metadata.scaler.thml.height;
                    } else
                        rowinfo.detailsHeight = 100;
                    element.addClass('datatable att-details').html($("#template-file-details").html().present(tpl_data)).height(rowinfo.detailsHeight);
                }
            },
                {
                    // Callbacks (for events happening in the datatable itself) - In most cases it will be either View or Edit
                    "getrows": function () {},
                    "rowselect": select_Row,
                    "clearselect": select_Clear,
                    "rowopen": open_Document
                }, "docs");
            }
    ;
// IWI manage extra section only for data panel selected module end





    /**
     * Open or locate panels on the issue overview
     */
    panel_Operation = function (op, window_id)
    {
        cargohub.application.reset_Session_Timer();
        // is window created?
        if (panels[window_id]) {
            // yes, check if it's also visible
            if (panels[window_id] == "closed") {
                // not visible, show it again and expand if it was previously
                // collapsed (but a bit later, since it appears it's not
                // possible to open and expand immediatly)
                $("#" + window_id).jqxWindow('open');
                panels[window_id] = "open";
                if (op != "init")
                    save_Docking_State();
            }
            // expand the new window
            setTimeout(function () {
                $("#" + window_id).jqxWindow('expand');
            }, 1000);

        } else {
            // no, add the window to the docking in it's default panel (column)
            // - It already exists in HTML, hidden, just transform it into a
            // window
            var $window = $("#" + window_id), win_data = $window.data();

            if ($window.length == 0) {
                alert("Window " + window_id + " not available (Possible template error, please contact Support)");
                return; // window not found, nothing to open !
            }

            // Append the editing buttons if win_data.editable is True
            if (win_data.editable && cargohub.application.user_Test_Privileges('issue_edit_xfields')) {
                $window.find("> div:eq(0)").append($("script#tpl_panel-edit-buttons").html());
                // Add events for the buttons; They always affect the panel in
                // which they exist
                $window.find("> div:eq(0) span.panel-cmds button").each(function () {
                    $(this).jqxButton({
                        theme: cargohub.jqxtheme
                    })
                            .click(function (event) {
                                cargohub.components.spinner.Off(); // Remove any
                                // previous spinner
                                // before possibly
                                // creating new ones

                                var $button = $(this);
                                // Start by disabling the button (save, edit, cancel) to
                                // avoid accidental double-press. It will be enabled
                                // once the operation completes.
                                $button.jqxButton({disabled: true}).jqxButton('render');
                                console.log('  1243 cmd   ',$(this).data('cmd'));
                                switch ($(this).data('cmd')) {
                                   
                                    // Load the editing form via AJAX request
                                    case 'edit-xf':
                                        cargohub.components.spinner.On(null, "Loading edit form...", null, null, 1000);
                                        console.log('  1249 cmd   ',$(this).data('cmd'));
                                        panels_saved_dom[window_id] = $window.find("div.jqx-window-content > *").detach();
                                        if (win_data.editheight) {
                                            $window.data('currentheight', $window.height());
                                            $window.jqxWindow({height: win_data.editheight});
                                        }
                                        $window.find("div.jqx-window-header span.panel-report").remove();
                                        $window.find("div.jqx-window-content").html("<div class='loading-pleasewait'></div>");

                                        $.get("?action=Edit_XFields", {
                                            rt_id: master.config.type.real_id,
                                            template: win_data.edittpl
                                        }, function (contents) { // , textStatus, jqXHR
                                            cargohub.components.spinner.Off(500); // Edit
                                            // form
                                            // loaded

                                            // Choose one of the patterns at random ;) -
                                            // Later we'll make a user setting
                                            var patts = ["1.png", "2b.jpg", "3.jpg", "4.png", "5.jpg"];

                                            $window.find("div.jqx-window-content").css('background', "#f8f8f8 url(/img/bgs/gray_patt" + patts[Math.floor(Math.random() * patts.length)] + ")").html(contents);
                                            // Loading complete ? Show the Save/Cancel
                                            $window.find("div.jqx-window-header span.toggle-cmds").toggle();

                                            // call the form initialization method after
                                            // loading ( to setup actions for the form
                                            // elements )
                                            setup_Editform($window, function (html) {
                                                console.log('  1278 cmd   ',html);
                                                // Submit success callback
                                                cargohub.components.spinner.Off(500);

                                                // Delete old "view" content (only used for
                                                // "cancel" button)
                                                delete panels_saved_dom[window_id];

                                                // Restore the view mode window on
                                                // successful save
                                                if (win_data.currentheight)
                                                    $window.jqxWindow({height: win_data.currentheight});

                                                $window.find("div.jqx-window-content").empty().css('background', "").append(html);
                                                // Regenerate controls on the view
                                                $window.find("div.jqx-window-content").find(".info-tip").each(cargohub.components.ui.add_Tooltip);
                                                $window.find("div.jqx-window-content").find(".info-window").each(cargohub.components.ui.button_InfoWindow);
                                                $window.find("div.jqx-window-content").find(".walloftext-view").each(cargohub.components.ui.walloftext_view);

                                                // Switch back buttons and add a self
                                                // disappearing message that content was
                                                // saved
                                                $window.find("div.jqx-window-header span.toggle-cmds").toggle().find('button').jqxButton({disabled: false}).jqxButton('render');
                                                $window.find("div.jqx-window-header > div:first").append("<span class='panel-report'>" + 'Data was saved' + "</span>");
                                                $window.find("div.jqx-window-header span.panel-report").fadeOut(5000, function () {
                                                    $(this).remove();
                                                });

                                                panel_edit_count--;
                                                $('#tab_panels .multi-commands button[data-op="edit"]').fadeIn();
                                                if (panel_edit_count == 0) {
                                                    $('#tab_panels .multi-commands button[data-op="save"]').fadeOut();
                                                    $(window).off("beforeunload");
                                                }
                                            }, function (xhr) {
                                                // Submit failure callback
                                                cargohub.components.spinner.Off(500);
                                                cargohub.its.http_fail(xhr);
                                                // Reenable all buttons on failure (to allow
                                                // trying again)
                                                $window.find("div.jqx-window-header span.toggle-cmds").find('button').jqxButton({disabled: false}).jqxButton('render');
                                            });

                                            // Add the "onbeforeunload" event
                                            if (panel_edit_count == 0)
                                                $(window).on("beforeunload", function () {
                                                    return "Panel data is not saved!";
                                                });

                                            $('#tab_panels .multi-commands button[data-op="save"]').fadeIn();
                                            panel_edit_count++;
                                            if (panel_edit_count == sizeof(panels))
                                                $('#tab_panels .multi-commands button[data-op="edit"]').fadeOut();

                                            awb_match();
                                        })
                                                /**
                                                 * The panel edit command failed
                                                 */
                                                .fail(function (xhr) {
                                                    cargohub.components.spinner.Off(500);
                                                    cargohub.its.http_fail(xhr);
                                                    // Reenable Edit button to try again.
                                                    $button.jqxButton({disabled: false}).jqxButton('render');
                                                });
                                        break;

                                        // Triggers the form submit (via AJAX) which should
                                        // update the fields present inside it;
                                        // On completion the content is replaced with a new
                                        // rendering of the panel using "panels/*_view.html"
                                        // templates
                                    case 'save-xf':
                                        cargohub.components.spinner.On(null, "Submitting changes...", null, null, 1000);
                                        console.log('  1251 cmd   ',$(this).data('cmd'));
                                        // Prepare data from managed controls which are
                                        // unable to transmit directly (as inputs)
                                        // A - dropselect multi-select
                                        $(".dropselect.multi-select", $window).each(function () {
                                            var
                                                    name = $('#' + $(this).prop('id') + '_jqxDropDownList', $window).data('name'),
                                                    $hidden_vals = $("<input type='hidden' name=" + name.substr(0, name.length - 2) + ">"); // Name
                                            // gets
                                            // the
                                            // end
                                            // []
                                            // cut,
                                            // as
                                            // the
                                            // original
                                            // was
                                            // designed
                                            // for
                                            // multiple
                                            // checkboxes
                                            ;
                                            // Create an input of type hidden which has the
                                            // original name, and contains the options as
                                            // json-encoded

                                            $hidden_vals.val($(this).val());
                                            $window.find("form").append($hidden_vals);
                                        });

                                        $window.find("form").trigger('submit');
                                        break;

                                        // Cancels the editing process by replacing the window
                                        // content with the original one
                                    case 'cancel-xf':
                                        if (panels_saved_dom[window_id]) {
                                            if (win_data.currentheight)
                                                $window.jqxWindow({height: win_data.currentheight});
                                            $window.find("div.jqx-window-content").empty().css('background', "").append(panels_saved_dom[window_id]);
                                            delete panels_saved_dom[window_id];
                                        }

                                        // Reenable all buttons on cancelling
                                        $window.find("div.jqx-window-header span.toggle-cmds").toggle().find('button').jqxButton({disabled: false}).jqxButton('render');
                                        $window.find("div.jqx-window-header > div:first").append("<span class='panel-report red'>" + 'Editing Cancelled ' + "</span>");
                                        $window.find("div.jqx-window-header span.panel-report").fadeOut(3000, function () {
                                            $(this).remove();
                                        });

                                        panel_edit_count--;
                                        $('#tab_panels .multi-commands button[data-op="edit"]').fadeIn();
                                        if (panel_edit_count == 0) {
                                            $('#tab_panels .multi-commands button[data-op="save"]').fadeOut();
                                            $(window).off("beforeunload");
                                        }
                                        break;
                                }
                            });

                });
            }

            $('#tab_panels #panels-docker').jqxDocking('addWindow', window_id, 'default', $window.data('panel'));
            // Normal users do not have the Close button anymore - Still valid
            // for admin, in case certain layouts need to be set up which
            // include closed panels.
            if (!cargohub.application.user_Is_Admin())
                $('#tab_panels #panels-docker').jqxDocking('hideCloseButton', window_id);
            $('#tab_panels #panels-docker').jqxDocking('showCollapseButton', window_id);
            $window
                    /**
                     * Event: A window is closed - Save the new docking state
                     */
                    .on('close', function (e) {
                        if (e.target == this) {
                            panels[window_id] = "closed";
                            save_Docking_State(e);
                            // Show a warning that this panel has been closed !
                            cargohub.application.evan_Standard_Notify("warning", "A panel has been closed! If you did not intend this, you can reopen from the <b>View &amp; Print menu</b>", 15);
                            $('html, body').animate({
                                scrollTop: 0
                            }, 1000);
                        }
                    })
                    /**
                     * Event: A panel is collapsed. If this is a "Data Table" panel,
                     * clear any row selection and hide the Edit/Delete buttons if they
                     * exist
                     */
                    .on('collapse', function (e) {
                        if (e.target == this) {
                            if ($(this).hasClass("datatable-panel"))
                                datatable_Panel_Unselect(e.target.id);
                            save_Docking_State(e);
                        }
                    })
                    .on('expand', save_Docking_State)
                    .on('moved', save_Docking_State);
// .on('resized', save_docking_state); // Disabled for now, as windows can't be
// pulled out of the dock anymore

            panels[window_id] = "open";

            // capture the state of the docking panel every time a window is
            // opened
            if (op != "init")
                save_Docking_State();
        }

        var header_animate = function ($hdr) {
            $hdr
                    .animate({'margin-left': 30}, 200)
                    .animate({'margin-left': 0}, 200)
                    .animate({'margin-left': 20}, 250)
                    .animate({'margin-left': 0}, 300)
                    .animate({'margin-left': 10}, 350)
                    .animate({'margin-left': 0}, 400);
        }, single_scroll_trigger = true;

        // finally, jump to the visibile window
        if (op == 'jump-to')
            $('html, body').animate({
                scrollTop: $("#" + window_id).offset().top
            }, 800, function () {
                if (single_scroll_trigger)
                    header_animate($("#" + window_id).find("div.jqx-window-header > div:eq(0)"));
                single_scroll_trigger = false;
            });
        if (op == 'jump-to-delayed')
            setTimeout(function () {
                $('html, body').animate({
                    scrollTop: $("#" + window_id).offset().top
                }, 800, function () {
                    if (single_scroll_trigger)
                        header_animate($("#" + window_id).find("div.jqx-window-header > div:eq(0)"));
                    single_scroll_trigger = false;
                });
            }, 500);
    },
            /**
             * Sends the current state of the docking panel to the controller, so it's
             * saved for next time the user opens this page
             */
            save_Docking_State = function (e)
            {
                // Don't save docking state while panels haven't yet finished
                // initializing
                if (!panels_ready)
                    return;

                // Introduce a small delay when doing panel operations to avoid doing
                // unneeded ajax calls (for example when someone is playing with
                // minimize-maximize button)
                donrey.final_Delay('save_docking_state', function () {
                    $.post("?action=Save_Docking_State", {
                        panels: panels,
                        rt_id: master.config.type.real_id,
                        panel_export: $('#tab_panels #panels-docker').jqxDocking('exportLayout')
                    });
                    // Do nothing for reporting, not worth bugging the user for problems
                    // here.
                }, 2000);
            },
            // How many panels are being currently edited ?
            panel_edit_count = 0,
            /**
             * Sets up the fields edit form which is dynamically loaded with ajax
             * 
             * @param $window
             *            The jqxWindow panel which contains the form
             * @param success_callback
             *            When submit complete with success, call this to finish the
             *            operation (This usually replaces the content with the returned
             *            HTML)
             * @param fail_callback
             *            When submit failed (for any reason), call this to cancel the
             *            operation and inform the user
             */
            setup_Editform = function ($window, success_callback, fail_callback)
            {
                var $the_form = $window.find("form"), win_data = $window.data();

                // setup the ajaxed form
                $the_form.ajaxForm({
                    url: "?action=Save_XFields",
                    data: {
                        rt_id: master.config.type.real_id,
                        template: win_data.edittpl
                    },
                    beforeSubmit: function (formData, jqForm, options) {
                        // TODO: setup field checking
                        if (master.config.type.real_id == 34 && win_data.edittpl == 'meta') {
                            var dup_there = "";
                            var rec_Pos_Details = "";
                            var startDate = "";
                            var endDate = "";
                            var repetation = "";
                            for (let i = 0; i < formData.length; i++) {
                                if (formData[i].name == "xfd[rec_Pos]") {
                                    rec_Pos_Details = formData[i].value;
                                }
                                //dup_there
                                if (formData[i].name == "xfd[has_Child]") {
                                    dup_there = formData[i].value;
                                }
                                if (formData[i].name == "xfd[date_start]") {
                                    startDate = formData[i].value;
                                }
                                if (formData[i].name == "xfd[date_end]") {
                                    endDate = formData[i].value;
                                }
                                if (formData[i].name == "xfd[sched_rep]") {
                                    repetation = formData[i].value;
                                }


                            }
                            if (dup_there == "n" && rec_Pos_Details == "par") {
                                if (startDate != "" && endDate != "" && repetation != "") {
                            //	if(confirm("You have edited parent record and you submitted all the details which needs to create duplicates?\n" +
                            //			"System will take sometime to create duplicates")){
                            //		return true;
                            //	}else{
                            //		return false;
                            //	}
                                    cargohub.alert("You have scheduled audits which will be created by duplicating the current audit record. The new records will be automatically created within a few mins.");
                                }
                            }
                        }
                        return true;
                    },
                    success: success_callback,
                    error: fail_callback
                });

                // All the following code is about interactive actions in the elements
                // inside the form ( might be various jqWidgets objects or other
                // elements)

                // jqxstyle any buttons in the form
                $('.op-button', $the_form).each(function () {
                    $(this).jqxButton({
                        theme: cargohub.jqxtheme
                    });
                });
                // Setup Calendar and Clock inputs
                $(".calendar", $the_form).each(cargohub.components.ui.calendar_input);
                $(".theclock", $the_form).each(cargohub.components.ui.clock_input);

                // Setup special comboboxes used for searching/suggestion
                $(".searchbox", $the_form).each(cargohub.components.ui.remotesearch_input);

                // Dropdowns (replaces "select")
                $(".dropselect", $the_form).each(cargohub.components.ui.drop_select);

                // Textarea which usually contains more text than it normally fits. On
                // hover, shows complete text in a floating div
                $(".walloftext", $the_form).each(cargohub.components.ui.walloftext_input);

                // Keyboard filters:
                $(".only-numbers", $the_form).each(cargohub.components.ui.numbers_input);

                $(".sync-container", $the_form).each(function () {
                    var $parent = $(this).parent().parent();
                    if ($(this).prop('checked'))
                        $parent.addClass('active');
                    $(this).on('change', function () {
                        if ($(this).prop('checked'))
                            $parent.addClass('active');
                        else
                            $parent.removeClass('active');
                    });
                });

                // Some buttons open a window with help about form content
                $('.info-window', $the_form).each(cargohub.components.ui.button_InfoWindow);

                // Add tool-tips to any element matching "info-tip"
                $(".info-tip", $the_form).each(cargohub.components.ui.add_Tooltip);
            }
    ;

    this._public({
        /**
         * Setup Notes Tab for Record Process module
         */
        init: function ()
        {
            init_Panels();
             init_DataTable();
            // Tooltips inside the Documents tab
            $("#tab_panels .info-tip").each(cargohub.components.ui.add_Tooltip);
            // IWI manage extra section only for data panel selected module  start
            init_Toolbar();
            $("#tab_trainee .info-tip").each(cargohub.components.ui.add_Tooltip);
            
			// We need DropzoneJS here
			master.load_file_uploader();
           
            // IWI manage extra section only for data panel selected module end
            
        },

        /**
         * Callback for actions to be performed when this tab is selected
         */
        selected: function (params)
        {
            if (params && params.trigger)
                switch (params.trigger) {
                    case "jump-to-panel":
                        if (panels_ready)
                            panel_Operation('jump-to', params.id);
                        break;
                    case "open-panel":
                        if (panels_ready)
                            panel_Operation('jump-to-delayed', params.id);
                        break;
                        case "files-upload":
					files_Upload(); break;
                }
        }
    });





    //For multi save  on blur START  | 21022024 code added by iwi | start


    // $(document).on('change ', "#tab_panels #panels-docker checked ,#tab_panels #panels-docker input,#tab_panels #panels-docker textarea,#tab_panels #panels-docker select ,#tab_panels #panels-docker selector ", function () {
    //     $(this).closest("form").submit();
    // });

    //For multi save  on blur END    | 21022024 code added by iwi | end






});

function awb_match() {
    //Validate MAWB
    $('#xfd_mawb_s').on('keyup', function (e) {
        // AWB validity check
        var awbp = $("#xfd_mawb_p").val(), awbs = $("#xfd_mawb_s").val();
        if (awbs.length == 8) {
            if (parseInt(awbs.substr(0, 7)) % 7 != parseInt(awbs.substr(7))) {
                $("#xfd_mawb_s").val('');
                cargohub.components.modal.customalert("Invalid MAWB number. Please make sure you entered the correct digits", "Error", "error.png");
                $('#modal_alert_yes').text("Ok");
                $('#modal_alert_notok').css("display", "none");
                return false;

            }

            $.post("../../issue?action=match_awb", {
                p: awbp,
                s: awbs,
                fk: record_type_checker()
            }, function (x) {
                if (x) {
                    if (x.mawb_outcome == 1) {
                        cargohub.components.modal.customalert(x.message, "Alert", "exclamation.png");
                        $('#modal_alert_yes').text("Yes");
                        $('#modal_alert_notok').css("display", "inline-block");
                        return false;
                    }

                }
            }).fail(cargohub.its.http_fail);
        }

    });
}
function record_type_checker() {
    var rt_ty = $('#record-type div a').text();
    var output;
    if (rt_ty === "Quality Event") {
        output = 1225;
    } else {
        output = 3;
    }
    return output;
}
function child_delete() {
    $.post("process?action=check_AND_deleteChilds", {
        urlData: window.location.href
    }, function (x) {
        if (x.success) {
            var dI = x.dI;
            if (confirm(x.message)) {
                alert('Ok we can remove childs from  ' + x.trackId);
                $.post("process?action=delete_audit_childs", {
                    id: dI
                },
                        function (x) {
                            if (x.success) {
                                if (x.message != "") {
                                    alert(x.message);
                                    document.location.reload();
                                } else {
                                    alert("Something went wrong,please try again");
                                }
                            } else {
                                if (x.message != "") {
                                    alert(x.message);
                                    document.location.reload();
                                } else {
                                    alert("Something went wrong,please try again");
                                }
                            }
                        });
            }
        } else {
            alert(x.message);
        }
    });
}
function panel_ready_for_parent(baseid, id) {
    $.post("process?action=check_childCount", {
        baseid: baseid,
        id: id
    }, function (x) {
        if (x.success) {
            if (x.type == 'par') {
                document.getElementById('showchild').innerHTML += "  [" + x.count + "]";
                //open parent should not appear
                document.getElementById("open_Parent").style.visibility = "hidden";
                if (x.count <= 0) {
                    //delete childs should not appear
                    document.getElementById("delete_childs").style.visibility = "hidden";
                }
            } else if (x.type == 'chi') {
                //delete childs should not appear
                document.getElementById("delete_childs").style.visibility = "hidden";
            }
        } else {
            //alert(x.message);
        }
    });
}
function par_Route(id) {
    if (id != null && id != "") {
        document.location = "/record/process:" + id + "";
    } else {
        alert("There is no parent for selected record");
    }
}
function getAuditData(item, index) {
    console.log(index + " " + item);
}
// function(x) {
// debugger;
// console.log(x.active);
// cargohub.alert("test data");
// cargohub.alert("test data1");
// if(x.success) {
// cargohub.alert("test");
// }else{
// cargohub.alert('data1111');
// }
// else
// cargohub.alert(x.reason, "Message from server", "information.png");

