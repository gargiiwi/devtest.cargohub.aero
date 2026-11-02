/**
 * TinyMCE visual editor component (singleton)
 * - Can be used in conjunction with an interface module that needs fancy text editing (like template editor, cms editor, mail content, inline text editing, etc.)
 * - The 3rd party library that does the heavy lifting is initialized at first use
 * - For the moment, it supports one single editor being present in DOM at one time.
 *
 * Still used on:
 * - Organization Templates
 * - Admin Content Management
 * - Admin Software updates
 * In order for the multi-instance one to be used instead, those modules have to be updated and also CodeMirror component has to be made multi-instance
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScriptUI Components
 * @version 2019.02.01
 */
cargohub.components.TinyMCE = donrey.BASE._extend(function() {
	// private
	var self = this,
		master = null,			// Master object that initialized this component
		$c = null, 				// The DOM container which contains the editors
		// Configuration
		params = null,
		// Operation
		changed = false,
		tinyEditor = null // The tinyMCE active editor, initialized on first use of setup()
	;

	this._public({
		name: "TinyMCE",

		// Component events
		events: {},

		/**
		 * Sets up the 3rd party editor to use a specific DOM object as the editor initialization.
		 * If using jQuery, the element must be extracted from the array using [0]
		 *
		 * tinyMCE expects a "textarea" to be used as the editor source
		 */
		setup: function(config, _master)
		{
			if(tinyEditor)
				return cargohub.alert("TinyMCE Editor is already initialized. One single editor currently supported.");

			if(!config.container || config.container.length == 0)
				return cargohub.alert("Error cannot setup TinyMCE Component because no container for it specified.");

			$c = config.container;
			if(_master)
				master = _master;

			// Prepare default parameters
			params = config.params||{};
			if(!params.tinyMCE)
				params.tinyMCE = {};

			params.tinyMCE.selector = "#"+ $c.attr('id');
			if(params.height)
				params.tinyMCE.height = params.height;
			params.tinyMCE.resize = false; // Doesn't allow manual resizing of the editor, as it might break some designs.

			params.tinyMCE.content_css = "/css/default/cargo_tiny.css"; // Should be configurable !
			if(!params.tinyMCE.plugins)
				params.tinyMCE.plugins = "link autolink paste textcolor image code hr fullscreen lists table colorpicker";
			if(!params.tinyMCE.toolbar)
				params.tinyMCE.toolbar = "bold italic underline | alignleft aligncenter alignright | formatselect fontselect fontsizeselect | hr link image table bullist numlist | forecolor backcolor removeformat | undo redo | fullscreen";
			params.tinyMCE.toolbar_items_size = 'small';
			// Not using menubar and statusbar
			if(!params.extended_Chrome) {
				params.tinyMCE.menubar = false;
				params.tinyMCE.statusbar = false;
			}
			params.tinyMCE.paste_data_images = true;
			params.tinyMCE.image_title= true;
			params.tinyMCE.file_picker_types= 'image';
			params.tinyMCE.images_upload_url= '/files?action=open_upload';
			params.tinyMCE.automatic_uploads = true;
			params.tinyMCE.file_picker_callback = function (cb, value, meta) {
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
				};

				input.click();
			};
			// Fixes ?
			params.tinyMCE.convert_fonts_to_spans = true;
			params.tinyMCE.fix_list_elements = true;
			params.tinyMCE.browser_spellcheck = false;
			params.tinyMCE.convert_urls = false;
			params.tinyMCE.relative_urls = false;
			params.tinyMCE.paste_webkit_styles = "all";
			params.tinyMCE.fontsize_formats = "8pt 10pt 11pt 12pt 14pt 16pt 18pt 20pt 24pt 28pt 32pt 36pt 40pt";

			// Autoresize
			params.tinyMCE.autoresize_min_height = 220;

			if(config.print && config.print.enabled) {
				params.tinyMCE.plugins = params.tinyMCE.plugins + " print";
				if(config.print.position == "left")
					params.tinyMCE.toolbar = "printbutton | " + params.tinyMCE.toolbar;
				else
					params.tinyMCE.toolbar = params.tinyMCE.toolbar + " | printbutton";
			}

			params.tinyMCE.setup = function(editor) {
				tinyEditor = editor;

				if(self.events.setup)
					self.events.setup(editor);

				/**
				 * Event: tinyEditor changes content.
				 * If this is the first time it does that, also call the external function "on_first_change"
				 */
				editor.on('change', function() {
					if(self.events.on_change)
						self.events.on_change();

					if(changed)
						return; // already changed, nothing special to do

					if(self.events.on_first_change)
						self.events.on_first_change();

					// On first change, do some operations (show buttons, show a message)
					changed = true;
				});

				if(self.events.on_init)
					editor.on('init', self.events.on_init);

				if(config.print && config.print.enabled) {
					editor.addButton('printbutton', {
						text: config.print.text||"PRINT",
						icon: "print",
						cmd: "mcePrint"
					});
				}
			};

			// Disable table toolbar because it's fucked
			params.tinyMCE.table_toolbar = "";

			// Load TinyMCE when initialized
			donrey.LAB.queueScript(donrey.CFG.paths.lib + "tiny_mce/jquery.tinymce.min.js?v=4.3.4");
			donrey.LAB.queueScript(donrey.CFG.paths.lib + "tiny_mce/tinymce.min.js?v=4.3.4");
			donrey.LAB.queueWait(function() {
				// Done loading ? Setup from TextArea object
				tinymce.init(params.tinyMCE);
			});
			donrey.LAB.runQueue();
		},

		/**
		 * Returns true if the content of the editor was changed since initialization, false if it was not
		 *
		 * @return bool
		 */
		content_Changed: function()
		{
			if(!tinyEditor)
				return false;

			changed = tinyEditor.isDirty();
			return changed;
		},

		/**
		 * Reads the content of the editor ( for example, with the purpose of saving it )
		 *
		 * @return string
		 */
		content_Read: function()
		{
			if(!tinyEditor)
				return false;

			return tinyEditor.getContent();
		},

		/**
		 * Replaces existing content editor with new text
		 *
		 * @param string content The new text. If empty, the editor will be reset
		 */
		content_Write: function(content)
		{
			if(!tinyEditor)
				return false;

			changed = true;
			tinyEditor.setContent(content); // This triggers the onChange event, setting "changed" true first disables the event, since this is an external call
			tinyEditor.isNotDirty = true;
			changed = false;
			return true;
		},

		/**
		 * Inserts the string at cursor into the active editor
		 */
		content_InsertAtCursor: function(string)
		{
			tinyEditor.insertContent(string);
		},

		/**
		 * Marks the editor as "clean".
		 * This is usually done after the content of the editor has been externally processed (saved, sent, copied, etc.). This resets the "changed" variable to "false", and any other action inside the editor will call the "on_first_change" event handler.
		 */
		set_Clean: function()
		{
			if(!tinyEditor)
				return false;

			tinyEditor.isNotDirty = true;
			changed = false;
			return true;
		},

		/**
		 * Focuses on the editor, placing the cursor inside the editing area
		 */
		focus: function()
		{
			if(!tinyEditor)
				return false;

			tinyEditor.focus();
			return true;
		},

		/**
		 * Disables the editor, disallowing the user to change it's content.
		 * Also marks it as "clean". This operation is usually used when the editor must remain in memory but there is no content to be edited.
		 */
		disable: function()
		{
			if(!tinyEditor)
				return false;

			self.set_Clean();
			tinyEditor.getBody().setAttribute('contenteditable', false);
//			tinyEditor.getDoc().designMode = 'Off';
			$("div.mce-toolbar-grp", tinyEditor.getContainer()).append("<div class='mce-toolbar-cover'></div>");

			return true;
		},

		/**
		 * Enables the editor, allowing the user to use it.
		 */
		enable: function()
		{
			if(!tinyEditor)
				return false;

			tinyEditor.getBody().setAttribute('contenteditable', true);
//			tinyEditor.getDoc().designMode = 'On';
			$("div.mce-toolbar-grp", tinyEditor.getContainer()).find("div.mce-toolbar-cover").remove();

			return true;
		},

		/**
		 * De-initializes the editor making the DOM object not react anymore
		 *
		 * @param bool clean If true, also empties the DOM object after editor is destroyed
		 */
		destroy: function(clean)
		{
			if(!tinyEditor)
				return false;

			tinyEditor.off("change");
			tinyEditor.save();
			tinyEditor.remove();

			$c = null;
			params = null;
			tinyEditor = null;
			return true;
		}

	});

	// Load CSS on first use, because this module needs styles
	donrey.load_CSS(donrey.CFG.paths.components_css + "tinymce.css?v=2.7.2");
});