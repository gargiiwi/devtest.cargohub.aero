/**
 * Record Type Editor v2
 * - Allows creation and editing of record types
 */
cargohub.admin_updates = cargohub.baseclass._instance(cargohub.baseclass.fullgrid, function() {
	// private
	var self = this,
		c = null,

		// Data from the grid containing currently selected row. Used for View, Edit, Delete - Set on any row operation.
		selected_row = null,
		selected_row_open = false,	// The selected row is open in the editor (save/close buttons should be on)
		content_editor = null,		// Indicates if and which editor is loaded
		content_editor_loaded = false,

		// Blog entry editor
		$entry_editor = null,

		// Data Source for the fields grid
		grid_Source = null,

		/**
		 * Setup the window used to choose which editor is being used for editing the blog entry
		 * -- The choice is saved in the session until the user logs out.
		 */
		setup_Options = function($window)
		{
			// The RadioButtons for choosing an editor
			$(".texted_option", $window).each(function()
			{
				var $option = $(this), is_active = self.config.texted == $option.data('value');
				$option.jqxRadioButton({
					theme: cargohub.jqxtheme, height: 22,
					groupName: "texted_option",
					checked: is_active
				});
			});

			// Setup the action for "OK" button
			$('#modal_entry-editor-options_ok', $window).off('click').on('click', function()
			{
				// submit the add/edit form using ajax
				$('.modal_content > form', $window).ajaxSubmit({
					dataType: 'json',
					beforeSubmit: function(formData, jqForm, options)
					{
						// Grab the value of the radiobuttons and submit
						var texted_option = cargohub.application.jqxRadioButtons_Get_Checked_Value($('.modal_content > form', $window));

						if(!texted_option) {
							cargohub.alert("Please select which editor will be used.");
							return false;
						}

						formData.push({
							name: "texted",
							value: texted_option
						});
						return true;
					},
					success: function(x) {
						if(x.success)
							document.location.reload();
						else
							cargohub.its.http_fail(null, "Admin.Updates::Entry_Editor_Options");
					},
					error: cargohub.its.http_fail
				});
			});
		},

		/**
		 * Sets up the toolbar with operations related to organizations
		 */
		init_Toolbar = function()
		{
			var tpl_data = {_itc: cargohub.input_theme_classes};

			// Buttons to add/edit/remove an user from the department
			$('.toolbar button')
				.jqxButton({ theme: cargohub.jqxtheme })
				.on('click', function() {
					var data = $(this).data();

					switch(data.op) {
						// Blog entry posting and editing
						case 'entry-post':
							init_Editor({
								title: "Post new blog entry",
								icon: "system_updated.png",
								mode: "create"
							});
							break;
						case 'entry-edit':
							init_Editor({
								title: "Edit entry: "+ selected_row.title,
								icon: "pencil.png",
								mode: "edit"
							});
							break;

						case 'entry-delete':
							if(!confirm("Are you sure you want to remove this blog entry ?"))
								return;

							$.post("?action=Delete_Blog_Entry", {
								id: selected_row.id
							}, function(x) {
								if(x.success) {
									// TODO: Change to select/unselect functions to remove this redundancy
									$("#entries-grid").jqxGrid('clearselection');
									selected_row = null;
									$('#updates-rowops button').jqxButton({disabled: true});

									donrey.single_Delay('hide_bar_updates-rowops', function() {
										$('#updates-rowops').fadeOut();
									}, 500);

									$("#entries-grid").jqxGrid('updatebounddata');
									close_Editor();
									cargohub.alert(x.response, "Success !", "thumb_up.png");
								} else
									cargohub.its.http_fail(null, "Admin.Updates::delete_blog_entry");
							})
								.fail(cargohub.its.http_fail);
							break;

						// Save existing editor content
						// TODO: Warn if no changes have been made
						case 'editor-save':
							save_Editor();
							break;

						// Simply closes the editor without saving
						// TODO: Check if changes have been made
						case 'editor-close':
							close_Editor();
							break;

						// Options command, allows switching editor
						case 'editor-options':
							cargohub.components.modal.create('entry-editor-options', {
								btn_roles: {cancel: "cancel"}
							});

							cargohub.components.modal.open('entry-editor-options', {
								width: 520, height: 200,
								title: "Choose the Blog Entry Editor",
								content: $('#window-entry_editor_options').html().present(tpl_data),
								callback: setup_Options
							}, self);
							break;

						// Removes any sorting, filters and paging from the grid, bringing it to the default (sort by date descending)
						case "grid-state-reset":
							if(!confirm("Are you sure you want to reset the grid ?"))
								return;

							$.post("/global/operations?action=Save_Grid_State", {
								grid_id: "sysupdates",
								state: false
							}, function(x) {
								if(x.success)
									// Reload the page after clearing the state
									document.location.reload();
								else
									cargohub.its.http_fail(null, "Global.Operations::save_grid_state");
							})
								.fail(cargohub.its.http_fail);
							break;
					}
				});
		},

		/**
		 * Saves the content of the template editor (all fields)
		 */
		save_Editor = function()
		{
			// submit the add/edit form using ajax
			$($entry_editor).find('form').ajaxSubmit({
				dataType: 'json',
				data: {
					"id": selected_row? selected_row.id: null,
					"content": content_editor.content_Read()
				},
				beforeSubmit: function(formData, jqForm, options)
				{
					// Check if Title is not empty, deny save if it is (must have a value for menus)
					var template_title = $("input#entry-title", $entry_editor).val();
					if(!template_title || template_title.length < 5)
						return cargohub.alert("The template's title is empty or too short, minimum 5 characters required");

					// ... tags are not mandatory, continue
					return true;
				},
				success: function(x) {
					if(x.success) {
						// TODO: Change to select/unselect functions to remove this redundancy
						$("#entries-grid").jqxGrid('clearselection');
						selected_row = null;
						$('#updates-rowops button').jqxButton({disabled: true});

						donrey.single_Delay('hide_bar_updates-rowops', function() {
							$('#updates-rowops').fadeOut();
						}, 500);

						$("#entries-grid").jqxGrid('updatebounddata');
						close_Editor();
						cargohub.alert(x.response, "Success !", "thumb_up.png");
					} else
						cargohub.its.http_fail(null, "Admin.Updates::Save_Blog_Entry");
				},
				error: cargohub.its.http_fail
			});
		},

		/**
		 * Minimize and close the editor if it was open.
		 */
		close_Editor = function()
		{
			// Minimize and close the editor
			if(selected_row_open) {
				if($entry_editor.jqxExpander('expanded')) {
					$entry_editor.on('collapsed', function() {
						// When finishing collapse... hide the editor
						$entry_editor.hide();
						// ... and disable the event
						$entry_editor.off('collapsed');
					});
					$entry_editor.jqxExpander('collapse');
				} else {
					// If already collapsed, just hide it
					$entry_editor.hide();
				}

				// Also fadeout the Save/Close buttons
				$('#updates-editorops button').jqxButton({disabled: true});
				$('#updates-editorops').fadeOut();

				selected_row_open = false;
			}
		},

		/**
		 * Loads selected template "body" into the existing editor. If currently no body, just use the editor with a blank result
		 */
		load_Body = function()
		{
			$.get('?action=Load_Blog_Entry', {
				id: selected_row.uid
			}, function(body) {
				if(body.length > 0)
					content_editor.content_Write(body);
				else
					content_editor.content_Write("\n");
			});
		},

		/**
		 * Setup the blog entries editor (Can use TinyMCE or CodeMirror)
		 *
		 * @param config Information now how the editor area is initialized
		 */
		init_Editor = function(config)
		{
			if($entry_editor) {
				// If already initialized just change jqxExpander according to config
				if(config && config.expander)
					$entry_editor.jqxExpander(config.expander);
			} else {
				// Otherwise, start full initialization
				$entry_editor = $("#entry-editor");
				if(!config.expander)
					config.expander = {
						disabled: false,
						expanded: false
					};
				config.expander.theme = cargohub.jqxtheme;
				$entry_editor.jqxExpander(config.expander);

				// Since the HTML inside the expander is permanent, initialize widgets

				$('#entry-title, #entry-taglist', $entry_editor).addClass(cargohub.input_theme_classes);

				content_editor.events.on_init = function() {
					// If first initialization starts in "edit" mode, load content. If not, just enable the editor
					$("#entry-content-placeholder", $entry_editor).hide();
					if(config.mode == 'edit') {
						load_Body();
					} else
						content_editor.enable();

					content_editor_loaded = true;
				};

				/**
				 * Extra modifications to TinyMCE
				 */
				if(self.config.texted == "TinyMCE")
					content_editor.events.setup = function(editor) {
						// Nothing here for now
					};
			}

			// Set/Change title (header)
			if(config.title)
				$entry_editor.jqxExpander('setHeaderContent', '<img src="/img/icons16/'+ (config.icon || 'blog.png')+ '" alt="[o]" class="inline-icon"> '+ config.title);

			switch(config.mode) {
				case 'create':
					selected_row = null;
					selected_row_open = false;

					// Empty Title/Subject
					$('#entry-title, #entry-taglist', $entry_editor).val("");

					// Finally, clear editor
					content_editor.content_Write(" \n");
					break;
				case 'edit':

					// Fill the Title and Tag List
					$('#entry-title', $entry_editor).val(selected_row.title);
					$('#entry-taglist', $entry_editor).val(selected_row.taglist);

					// If editor already initialized (moving from another record, reload new body)
					if(content_editor_loaded)
						load_Body();
					break;
			}

			// Finished initialization ( or reconfiguration ) - Show the expander and expand
			$entry_editor.show().jqxExpander('enable');
			$entry_editor.on('expanded', function() {
				// When first expanded, initialize Content editor
				if(!content_editor_loaded)
					content_editor.setup({
						container: $("#entry-content", $entry_editor),
						params: {
							// Only useful for TinyMCE
							tinyMCE: {
								plugins: "link autolink autoresize paste textcolor image code hr fullscreen lists table colorpicker charmap searchreplace insertdatetime",
								toolbar: "insert_placeholder | bold italic underline | alignleft aligncenter alignright | formatselect fontselect fontsizeselect | hr link image table bullist numlist charmap insertdatetime | forecolor backcolor removeformat | undo redo | searchreplace fullscreen",
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
								}
							}
						}
					}, self);
			});
			$entry_editor.jqxExpander('expand');

			if(!selected_row_open) {
				donrey.cancel_Delay('hide-rowops_bar');

				$('#updates-editorops button').jqxButton({disabled: false});
				$('#updates-editorops').fadeIn();
			}
			selected_row_open = true;
		},

		/**
		 * A record row has been selected - Store information in preparation for a double-click ( for Previewing or Opening ), or for clicking on one of the toolbar buttons
		 */
		select_Row = function(event)
		{
			var operations_bar = "#updates-rowops";

			if(!selected_row) {
				donrey.cancel_Delay('hide-rowops_bar');
				$(operations_bar+ ' button').jqxButton({disabled: false});
				$(operations_bar).fadeIn();
			}

			if(event.args.row)
				selected_row = event.args.row;
		},

		/**
		 * No rows are currently selected - Make the toolbar buttons dissapear
		 */
		select_Clear = function()
		{
			var operations_bar = "#updates-rowops";

			selected_row = null;
			$(operations_bar+ ' button').jqxButton({disabled: true});
			donrey.single_Delay('hide-rowops_bar', function() {
				$(operations_bar).fadeOut();
			}, 500);

			$("#entries-grid").jqxGrid('clearselection');
		},

		/**
		 * Setup the fields grid - This one allows editing each field individually
		 * (Old variant of this module would load and save all fields at once and is very inefficient for data types with lots of fields - 50 or more)
		 */
		init_EntriesGrid = function()
		{
			// Prepare the grid (Configurable)
			var gridDataFields = [
				{name: 'id', type: 'number'},		// Numeric DB id
				{name: 'group_id', type: 'number'},	// Name of the group (when it exists)
				{name: 'group', type: 'string'},	// Name of the group (when it exists)
				{name: 'dt', type: 'date'},
				{name: 'title', type: 'string'},	// Title of this blog entry
				{name: 'clen', type: 'number'},		// Content length ( characters ) - Just s
				{name: 'taglist', type: 'string'},	// List of tags used by this entry
			];

			grid_Source = self.source({
				url: "?action=Get_Entries",
				type: "POST", // Do it as post due to possible long request URL (so it works with large filters)
				data: {
					org_id: null,	// TODO: Support editing blog entries for arbitrary organization?
					virtualmode: true
				},
				datatype: "json",
				root: "data",
				id: 'id',
				datafields: gridDataFields,
				// server side operations - filtering and sorting:
				cache: false,
				filter: function() {
					$("#entries-grid").jqxGrid('updatebounddata', 'filter');
				},
				sort: function() {
					$("#entries-grid").jqxGrid('updatebounddata', 'sort');
				},
				beforeprocessing: function(source) {
					this.totalrecords = source.found_rows;
				},
				/**
				 * Client-Side pre-processing of filter data, because the way the grid sends it by default is total crap.
				 */
				formatData: cargohub.its.jqxGrid_FormatFilters
			});

			// Columns
			self.column({
				text: "Update's Title", datafield: 'title',
				width: 500, minwidth: 200, maxwidth: 700,
				groupable: false
			});
			// TODO: Support category filter (after implementing categories for blog entries)
			/*		self.column({
                        text: "Content Type", datafield: 't',
                        filtertype: "checkedlist", filteritems: ft_list,
                        width: 150, minwidth: 150, maxwidth: 150,
                        cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
                            return '<div style="margin: 4px 4px 2px 4px; overflow: hidden; padding-bottom: 2px;">'+ ft_translate[value]+ '</div>';
                        },
                        createfilterwidget: function (column, columnElement, widget) {
                            widget.jqxDropDownList({
                                dropDownWidth: 140,
                                dropDownHeight: 220,
                                animationType: "fade"
                            });
                        }
                    });
            */

			self.column({text: "Posted on", dataField: 'dt', width: 110, cellsformat: "MMM-dd HH:mm", align: "center", cellsalign: "center", filtertype: "range"});
			self.column({text: "Length", dataField: 'clen', width: 60, align: "right", cellsalign: "right", filtertype: "number"});
			self.column({
				text: "Tag list", datafield: 'taglist',
				sortable: false,
				cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
					var c, ts, tags_string = "", tooltip_string = "";
					if(value.length > 0) {
						try {
							ts = value.split(',');
							for(c=0; c< ts.length; c++) {
								tags_string += '<span class="tag">'+ ts[c]+ "</span>";
								tooltip_string += ts[c]+ "\n";
								first = false;
							}

							// Total choice data length below threashold ? Display choices in boxes. Too big ? Display a fixed text with tooltip
							if(value.length > 66)
								tags_string = '<span title="'+tooltip_string +'">This entry has <b>'+ ts.length+ '</b> tags (Hover to see them)</span>';
						} catch(err) {
							// nothing ?
						}
					}
					return '<div style="margin: 4px 4px 2px 4px; overflow: hidden; padding-bottom: 2px;">'+ tags_string+ '</div>';
				},
				groupable: false
			});

			// Init the grid as persistent in session
			self.init_Grid($("#entries-grid"), {
				width: cargohub.fullwidth,
				virtualmode: true, // virtual state
				rendergridrows: function(params) { return params.data; },
				pageable: true, pagesizeoptions: [10, 25, 50, 100, 250], pagesize: 25
			}, {
				persistent: true,
				grid_id: 'sysupdates'
			})
				/**
				 * Event: When a row is selected, the editing commands appear
				 */
				.on('rowselect', select_Row)
				/**
				 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
				 */
				.on('filter', select_Clear)
				/**
				 * Event: On doubleclicking, do the same as simple clicking then using the Edit button
				 */
				.on('rowdoubleclick', function() {
					$('.toolbar button[data-op="entry-edit"]').trigger("click");
				});
		}

	;
	/**
	 * Section initialization
	 */
	this.init = function()
	{
		init_Toolbar();

		// Initial grid state
		if(!self.config.grid_state) {
			self.config.grid_state = {
				sort: {
					column: 'dt',
					direction: 'desc'
				}
			};
		}
		init_EntriesGrid();

		// Load the configured editor component
		donrey.load_Components(self.config.texted, function() {
			content_editor = cargohub.components[self.config.texted];
			// init_Editor(); // Only initialized (and tinymce itself loaded) when first used
		}, "?v="+cargohub.version);

		// Info Explander
		$(".info-expander").jqxExpander({
			theme: cargohub.jqxtheme,
			expanded: false
		});
	};
});