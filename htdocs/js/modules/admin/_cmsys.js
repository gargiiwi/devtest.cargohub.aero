/**
 * Content Management System editor interface. Supports multiple document editors by using reusable components:
 * - codemirror (programmer editor) - http://codemirror.net/
 * - tinymce (wysiwyg) - http://www.tinymce.com/
 *
 * Not yet implemented:
 * - ckeditor (wysiwyg) - http://ckeditor.com/
 * - textile (textarea + MarkItUp, when viewed it formats according to textile rules) - http://markitup.jaysalvat.com/home/
 * - ace (code editor?) - http://ace.c9.io
 * - jqxEditor - The editor built into jqWidgets (not very good at the moment)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2.7.3
 */
cargohub.admin_cmsys = donrey.BASE._extend(function() {
	// private
	var self = this,
		popups = {}, // state of the pop-up windows used by this page - Used for lazy initialization ( on first use )

		content_editor = null,		// Indicates if and which editor is loaded
		current_file = null,		// Auto-allocated jqWidgets ID of the currently clicked LI element in the tree. Used by the auto-switch-back system that prevents folders from being clicked
		page_id = null,				// ID of the loaded CMS page
		page_path = null,			// Base64 encoded relative path of the clicked file. Is sent back with most of the requests, including the create file command
		page_name = null,			// Page name as it appears in the tree
		page_changed = null,		// Was the page changed in any way ( contents, title, description, disabled/enabled ) - The "Save/Revert" should only show when this is "true"
		original_contents = null,	// After loading a file, this contains the original content. Used by the "revert" command

		status_messages = {
			"idle": "Resting under the sunshine while waiting for you to click on something.",
			"loading": "Loading page <b>{$name}</b>, please wait...",
			"loaded": "<b>{$title}</b> has been successfully loaded using {$editor_name}.",
			"changed": "The CMS page has been changed and is not yet saved.",
			"askswitch": "\nContinue loading another file?",
			"askclose": "\nReally close the editor?",
			"askrevert": "\nReally revert to the original?",
			"revert": "Contents of the editor has been reverted to the original file.",
			"movewarn": "The contents of the editor has been changed, it will be lost if you move away", // Firefox ignores the message
			"saved": "The changes to the page have been saved !",
			"closed": "The editor has been closed.",
			"askdelete": "Are you sure you want to delete page: {$name}?\nAll it's contents will be lost !"
		},

		/**
		 * This module uses a statusbar to reflect what's currently going on. This function sets the content of the status bar to one of the predefined messages, or to a custom one
		 */
		set_Status_Message = function(message_or_key, data)
		{
			if(status_messages[message_or_key])
				message_or_key = status_messages[message_or_key];

			$("#status-bar").html(message_or_key.present(data)).css("background-color", "Yellow").stop().animate({
				backgroundColor: "#FFFFFF"
			}, 1200);
		},

		/**
		 * Sets up the controls on the create page modal
		 */
		setup_Create_Page = function($window)
		{
			// Setup the action for "OK" button
			$('#modal_create-page_ok', $window).off('click').on('click', function()
			{
				// submit the add/edit form using ajax
				$('.modal_content > form', $window).ajaxSubmit({
					dataType: 'json',
					beforeSubmit: function(formData, jqForm, options)
					{
						if($("#page-path", $window).val().length < 4 || $("#page-path", $window).val().charAt(0) != '/') {
							cargohub.alert("The path can't be empty and it must start with / (slash).");
							return false;
						}

						if($("#page-title", $window).val().length < 4) {
							cargohub.alert("You should give the page a title...");
							return false;
						}
					},
					success: function(x) {
						if(x.success) {
							document.location.reload();
						} else {
							cargohub.alert(x.reason, "Message from server", "information.png");
						}
					}
				});
			});
		},

		/**
		 * Setup the window used to change the current editor
		 */
		setup_Editor_Options = function($window)
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
			$('#modal_editor-options_ok', $window).off('click').on('click', function()
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
						if(x.success) {
							document.location.reload();
						} else
							cargohub.its.http_fail(null, "Admin.CMSys::cms_preferences");
					},
					error: cargohub.its.http_fail
				});
			});


		},

		/**
		 * Sets up the command toolbar
		 */
		setup_Toolbar = function()
		{
			var tpl_data = {_itc: cargohub.input_theme_classes};

			$('.toolbar button')
				.jqxButton({ theme: cargohub.jqxtheme })
				.on('click', function(){
					var data = $(this).data();
					switch(data.op) {
						// Shows the modal window used to create a new page - This will create the file and the corresponding database record, but the content will be empty.
						// The page is reloaded and the new page should be selectable in the tree with the purpose of being edited/further configured.
						// TODO: Maybe, there should be a way (using the session?) to automatically open the newly created page for editing.
						case 'create':
							if(!popups.create) {
								cargohub.its.init_Modal_Window('create-page', null, {"cancel": "cancel"});
								popups.create = true;
							}
							cargohub.its.open_Modal_Window('create-page', 500, 330, "Create new content page", $('#window-create-page').html().present(tpl_data), setup_Create_Page);
							break;

						// Opens a window containing the "_preview" CMS page inside an iframe (without the headers/footers) after current contents is saved into it (allows previewing changes without saving)
						case 'preview':

							// Attempt to save current editor content to the "preview", when finished open the preview window
							$.post('?action=save_preview', {
								"contents": content_editor.content_Read()
							}, function(x) {
								if(x.success) {
									if(!popups.preview) {
										cargohub.its.init_Modal_Window('preview-page', {"ok": "Close"}, null, null, null, 1200, 700);
										popups.preview = true;
									}

									cargohub.its.open_Modal_Window('preview-page', 1100, 750, "Preview editor contents", '<iframe src="?action=show_preview" width="1060" height="606" frameborder="0"></iframe>');
								} else
									// Error while saving ?
									cargohub.alert(x.reason);
							});
							break;

						// Saves the changes back to the file and database
						case 'save':
							var page_title = $("div#page-information #page-title").text();
							if(page_title.length < 3)
								return cargohub.alert("The page's title is empty, minimum 3 characters required");

							$.post('?action=save_page', {
								"path": page_path,
								"contents": content_editor.content_Read(),
								"meta": {
									title: page_title,
									description: $("div#page-information #page-description").text()
								}
							}, function(x) {
								if(x.success) {
									// Page saved successfully, simply remove the dirty status of the editor
									content_editor.set_Clean();
									page_changed = false;
									$("div.toolbar div.bar-opendoc-changed").fadeOut();
									return set_Status_Message("saved");
								} else
									// Error while saving ?
									cargohub.alert(x.reason);
							});
							break;

						// Reverts the content of the editor to the original contents. This is useful when a big mistake has been made and there might be too many undo steps to fix it.
						// This operation asks for confirmation.
						case 'revert':
							if(page_changed)
								if(!alert_Changed('askrevert'))
									return false;

							content_editor.content_Write(original_contents);
							content_editor.set_Clean();
							page_changed = false;
							$("div.toolbar div.bar-opendoc-changed").fadeOut();
							return set_Status_Message("revert");

						// Destroy the page editor and revert to no page being selected in the tree
						case 'close':
							if(content_editor && page_id) {
								if(content_editor.content_Changed())
									if(!alert_Changed('askclose'))
										return false;

								// Destroy the editor
								content_editor.destroy();
								$("#editor-content").hide();
								$("#editor-inactive").show();
								page_id = null;
								content_editor = null;

								// Hide the buttons and clear the info page
								page_changed = null;
								$("div.toolbar div.bar-opendoc, div.toolbar div.bar-opendoc-changed").fadeOut();
								$("#page-information").html("");

								// Unselect the element in the tree
								current_file = null;
								$("#navigator").jqxTree('selectItem', null);
							} else
								return cargohub.alert("Captain, we have detected an unknown vessel on the long range scanners.", "Spock alerts:", "ncc1701.png");

							return set_Status_Message("closed");

						// Deletes the currently open page (
						case 'delete':
							if(content_editor && page_id) {
								if(!confirm(status_messages.askdelete.present({"name": page_name})))
									return false;

								$.post('?action=delete_page', {
									"path": page_path
								}, function(x) {
									if(x.success) {
										// Page was deleted, reload the module to re-read the tree
										document.location.reload();
									} else
										// Error while deleting ?
										cargohub.alert(x.reason);
								});
							} else
								return cargohub.alert("Captain, there are strange energy readings coming from ship's starboard.", "Spock alerts:", "ncc1701.png");
							break;

						// Choose the editor which is going to be used for editing. Accepting the changes will reload the module.
						case 'options':
							if(!popups.options) {
								cargohub.its.init_Modal_Window('editor-options', null, {"cancel": "cancel"});
								popups.options = true;
							}
							cargohub.its.open_Modal_Window('editor-options', 500, 250, "CMS Editor Options", $('#window-editor-options').html().present(tpl_data), setup_Editor_Options);
							break;
					}
				});
		},

		/**
		 * Alerts that there is a page loaded which has changed and was not saved, and asks the user to confirm the operation which will lose the editor content
		 */
		alert_Changed = function(op)
		{
			return confirm(status_messages.changed + status_messages[op]);
		},

		/**
		 * Makes an ajax call to load the content of the file into the editor.
		 * If first time used, initializes the editor before loading the file
		 */
		load_Page = function()
		{
			var load_editor_contents = function(x) {
				// Set the editor content
				content_editor.content_Write(x.page.contents);

				// Set the status to show that a page has been loaded
				x.page.editor_name = content_editor.name;
				set_Status_Message("loaded", x.page);

				// Show the toolbar buttons related to an open page (preview/delete)
				$("div.toolbar div.bar-opendoc").fadeIn();
			};

			set_Status_Message("loading", {"name": page_name});

			$.get('?action=load_page', {"path": page_path}, function(x) {
				if(x.success) {
					// 1. Set internal variables
					original_contents = x.page.contents;
					page_id = x.page.id;

					// 1. Fill the page information area with content
					$("#page-information").html($("#template-page-information").html().present(x.page));

					// 1.1 Setup the events for editing title and description, so Save/Revert are available when just those are changed (not only when the editor is operated)
					$("#page-information #page-title, #page-information #page-description").on('input', function() {
						if(!page_changed) {
							page_changed = true;
							$("div.toolbar div.bar-opendoc-changed").fadeIn();
							set_Status_Message("changed");
						}
					});

					// 2. Initialize the editor plugin if necessary
					if(!content_editor) {
						// 2.1 Hide the presentation page, show the editor container object
						$("#editor-inactive").hide();
						$("#editor-content").show();
						// 2.2 Init the editor
						content_editor = cargohub.components[self.config.texted];
						$("#texted_name").text(content_editor.name);

						content_editor.events.on_init = function() {
							load_editor_contents(x);
						};

						content_editor.events.on_first_change = function() {
							page_changed = true;
							$("div.toolbar div.bar-opendoc-changed").fadeIn();
							set_Status_Message("changed");
						};

						content_editor.setup({
							container: $("#editor-content"),
							params: {
								// Only useful for TinyMCE
								height: 567,
								tinyMCE: {
									//changes by gargi (fix: position of toolbar in template editor)
									plugins: "link autolink paste textcolor image code hr fullscreen lists table colorpicker charmap searchreplace insertdatetime",
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
									},
									resize: false
								}
							}
						}, self);
					} else
						// If already initialized, just load the new contents
						load_editor_contents(x);
				} else {
					// Unable to load?
					set_Status_Message(x.reason);

					if(content_editor && page_id) {
						content_editor.destroy();
						$("#editor-content").hide();
						$("#editor-inactive").show();
						page_id = null;
						content_editor = null;
					}

					// Clear the info page
					$("#page-information").html("");

					// Hide the buttons
					page_changed = null;
					$("div.toolbar div.bar-opendoc, div.toolbar div.bar-opendoc-changed").fadeOut();
				}
			});
		},

		/**
		 * Indicates if the event event_Tree_Select is triggered by user (false) or by the code itself (true)
		 * -- This is used for the auto-select-back on tree element which are not actually selectable.
		 */
		file_CodeSelect = false,

		/**
		 * Event: An element is selected in the #navigator tree.
		 * - If a folder is selected, the system should ignore it and go back to the previously selected item ( or simply unselect it if there was no previous )
		 * - If a file is selected, it should call the Load function which will load the file and initialize the editor
		 *   - If a file is already loaded and changed (page_id is not null, editor status is changed), it should show a modal warning that the page has been modified and ask to continue or not.
		 */
		event_Tree_Select = function(event)
		{
			if(file_CodeSelect)
				return;

			// check if clicking on a folder (not selectable)
			if(!$(event.args.element).data('selectable')) {
				file_CodeSelect = true;
				$("#navigator").jqxTree('selectItem', $('li#'+current_file)[0]);
				file_CodeSelect = false;
				return;
			}

			// get the correct data
			var file = $('#navigator').jqxTree('getItem', event.args.element),
				// selected file
				selected_file = file.id;

			// clicked on the already selected one ? Ignore trigger
			if(selected_file == current_file)
				return;

			if(content_editor && page_id)
				if(content_editor.content_Changed())
					if(!alert_Changed("askswitch")) {
						file_CodeSelect = true;
						$("#navigator").jqxTree('selectItem', $('li#'+current_file)[0]);
						file_CodeSelect = false;
						return;
					}

			current_file = selected_file;

			// Hide the changed buttons and clear the info page in case they were visible
			page_changed = null;
			$("div.toolbar div.bar-opendoc-changed").fadeOut();
			$("#page-information").html("");

			// Load the page
			page_path = $('li#'+current_file+':first').data('path');
			page_name = $.trim($('li#'+current_file+':first').text());
			load_Page();
		}
	;

	this._public({
		init: function() {
			//setup Toolbar
			setup_Toolbar();

			// create splitter
			$("#splitter-naved").jqxSplitter({ theme: cargohub.jqxtheme, width: cargohub.fullwidth, height: 600, panels: [{ size: 200}, {min: 834}] });

			// create the tree and bind select
			$('#navigator').jqxTree({ theme: cargohub.jqxtheme, height: '100%', width: '100%' });
			$('#navigator').on('select', event_Tree_Select);

			set_Status_Message("idle");

			// Load the configured editor component
			donrey.load_Components(self.config.texted, function() {
				// init_Editor(); // Only initialized (and tinymce itself loaded) when first used
			}, "?v="+cargohub.version);
		}
	});

});