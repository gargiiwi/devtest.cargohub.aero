/**
 * TinyMCE visual editor component (singleton) - Supports multiple TinyMCE instances within one page
 * - Can be used in conjunction with an interface module that needs fancy text editing (like template editor, cms editor, mail content, inline text editing, etc.)
 * - TinyMCE, The 3rd party library that does the heavy lifting, is loaded with setup() and initialized with create_Instance()
 * - This version supports multiple TinyMCE instances within one page, but is not compatible with the original "single instance" editor (must be initialized and used in a different way)
 * - It's possible to run setup() and create_Instance() imediately after - the editor will auto-initialize after it finished loading
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScriptUI Components
 * @link https://www.tiny.cloud/
 * @version 2019.01.31
 */
cargohub.components.TinyMCEm = donrey.BASE._extend(function() {
	// private
	var self = this,
	master = null,			// Master object that initialized this component
	// General Configuration, and individual editor configuarion
	tinyMCE_loaded = false,
	instances = {}			// Object containing instance configuration - Key is instance name, used in all other functions to access the editor
	;
	this._public({
		name: "TinyMCEm",

		/**
		 * Loads TinyMCE before first instance
		 * @param _master Module which uses this component
		 */
		setup: function(_master)
		{
			if(tinyMCE_loaded)
				return; // Already initialized in this page, nothing to do.
			if(_master)
				master = _master;

			// Load TinyMCE when initialized
			donrey.LAB.queueScript(donrey.CFG.paths.lib + "tiny_mce/jquery.tinymce.min.js?v=4.3.4");
			donrey.LAB.queueScript(donrey.CFG.paths.lib + "tiny_mce/tinymce.min.js?v=4.3.4");
			donrey.LAB.queueWait(function() {
				tinyMCE_loaded = true;
				// Done loading ? Init already defined instances (pending)
				if(instances)
					for(var c in instances)
						if(instances[c].pending) {
							tinymce.init(instances[c].tinyMCE);
							instances[c].active = true;
							delete instances[c].pending;
						}
			});
			donrey.LAB.runQueue();
		},

		/**
		 * Sets up TinyMCE on the specified container element
		 */
		create_Instance: function(ikey, config, events)
		{
			var instance = {}; // TinyMCE instance parameters

			if(instances[ikey])
				return cargohub.alert("Error: Instance key "+ ikey+ " was already used");

			if(!config.container || config.container.length == 0)
				return cargohub.alert("Error: Cannot instance TinyMCE without a container element");

			if(config.container.length > 1)
				return cargohub.alert("Error: Container must be unique (Preferably by having a DOM ID)");

			instance = config.params||{};
			instance.key = ikey;
			instance.$c = config.container;
			if(events)
				instance.events = events;

			// TinyMCE specific configuration
			if(!instance.tinyMCE)
				instance.tinyMCE = {};

			instance.tinyMCE.selector = "#"+ instance.$c.attr('id');
			if(instance.height)
				instance.tinyMCE.height = instance.height;
			instance.tinyMCE.resize = false; // Doesn't allow manual resizing of the editor, as it might break some designs.

			instance.tinyMCE.content_css = "/css/default/cargo_tiny.css"; // Should be configurable !
			if(!instance.tinyMCE.plugins)
				instance.tinyMCE.plugins = "link autolink paste textcolor image hr fullscreen lists table colorpicker code";
			if(!instance.tinyMCE.toolbar)
				instance.tinyMCE.toolbar = "bold italic underline | alignleft aligncenter alignright | formatselect fontselect fontsizeselect | hr link image table bullist numlist | forecolor backcolor removeformat | undo redo | fullscreen";
			instance.tinyMCE.toolbar_items_size = 'small';
			// Not using menubar and statusbar
			if(!instance.extended_Chrome) {
				instance.tinyMCE.menubar = false;
				instance.tinyMCE.statusbar = false;
			}
			instance.tinyMCE.paste_data_images = true;
			// Fixes ?
			instance.tinyMCE.convert_fonts_to_spans = true;
			instance.tinyMCE.fix_list_elements = true;
			instance.tinyMCE.browser_spellcheck = false;
			instance.tinyMCE.convert_urls = false;
			instance.tinyMCE.relative_urls = false;
			instance.tinyMCE.paste_webkit_styles = "all";
			instance.tinyMCE.fontsize_formats = "8pt 10pt 11pt 12pt 14pt 16pt 18pt 20pt 24pt 28pt 32pt 36pt 40pt";
			instance.tinyMCE.image_title= true;
			instance.tinyMCE.file_picker_types= 'image';
			instance.tinyMCE.images_upload_url= '/files?action=open_upload';
			instance.tinyMCE.automatic_uploads = true;
			instance.tinyMCE.file_picker_callback = function (cb, value, meta) {
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
			// Autoresize
			instance.tinyMCE.autoresize_min_height = 220;

			// Print button
			if(config.print && config.print.enabled) {
				instance.tinyMCE.plugins = instance.tinyMCE.plugins + " print";
				if(config.print.position == "left")
					instance.tinyMCE.toolbar = "printbutton | " + instance.tinyMCE.toolbar;
				else
					instance.tinyMCE.toolbar = instance.tinyMCE.toolbar + " | printbutton";
			}

			instance.tinyMCE.setup = function(editor) {
				instance.tinyEditor = editor; // The TinyMCE object itself

				if(instance.events.setup)
					instance.events.setup(editor);

				/**
				 * Event: tinyEditor changes content.
				 * If this is the first time it does that, also call the external function "on_first_change"
				 */
				editor.on('change', function() {
					if(instance.events.on_change)
						instance.events.on_change();

					if(instance.changed)
						return; // already changed, nothing special to do

					if(instance.events.on_first_change)
						instance.events.on_first_change();

					// On first change, do some operations (show buttons, show a message)
					instance.changed = true;
				});

				if(instance.events.on_init)
					editor.on('init', function() {
						instance.events.on_init();
					});

				if(config.print && config.print.enabled) {
					editor.addButton('printbutton', {
						text: config.print.text||"PRINT",
						icon: "print",
						cmd: "mcePrint"
					});
				}
			};

			// Two modes of operation - If TinyMCE is already loaded, initialize on a new element. If not yet, add them to instances to be prepared (after it's loaded)
			instances[ikey] = instance;
			if(tinyMCE_loaded) {
				tinymce.init(instance.tinyMCE);
				instances[ikey].active = true;
			} else {
				instances[ikey].pending = true;
			}
		},

		/**
		 * Reads the content of the editor ( for example, with the purpose of saving it )
		 *
		 * @return string
		 */
		content_Read: function(ikey)
		{
			if(!instances[ikey])
				return false; // Instance not found

			if(!instances[ikey].tinyEditor)
				return false; // TinyMCE not initialized yet

			return instances[ikey].tinyEditor.getContent();
		},

		/**
		 * Replaces existing content editor with new text
		 *
		 * @param string content The new text. If empty, the editor will be reset
		 */
		content_Write: function(ikey, content)
		{
			if(!instances[ikey])
				return false; // Instance not found

			if(!instances[ikey].tinyEditor)
				return false;

			instances[ikey].changed = true;
			instances[ikey].tinyEditor.setContent(content); // This triggers the onChange event, setting "changed" true first disables the event, since this is an external call
			instances[ikey].tinyEditor.isNotDirty = true;
			instances[ikey].changed = false;
			return true;
		},

		/**
		 * Inserts the string at cursor into the selected editor
		 */
		content_InsertAtCursor: function(ikey, string)
		{
			if(!instances[ikey])
				return false; // Instance not found

			if(!instances[ikey].tinyEditor)
				return false;

			instances[ikey].tinyEditor.insertContent(string);
			return true;
		},

		/**
		 * Marks the editor as "clean".
		 * This is usually done after the content of the editor has been externally processed (saved, sent, copied, etc.). This resets the "changed" variable to "false", and any other action inside the editor will call the "on_first_change" event handler.
		 */
		set_Clean: function(ikey)
		{
			if(!instances[ikey])
				return false; // Instance not found

			if(!instances[ikey].tinyEditor)
				return false;

			instances[ikey].tinyEditor.isNotDirty = true;
			instances[ikey].changed = false;
			return true;
		},

		/**
		 * Focuses on the editor, placing the cursor inside the editing area
		 */
		focus: function(ikey)
		{
			if(!instances[ikey])
				return false; // Instance not found

			if(!instances[ikey].tinyEditor)
				return false;

			instances[ikey].tinyEditor.focus();
			return true;
		},

		/**
		 * Disables the editor, disallowing the user to change it's content.
		 * Also marks it as "clean". This operation is usually used when the editor must remain in memory but there is no content to be edited.
		 */
		disable: function(ikey)
		{
			if(!instances[ikey])
				return false; // Instance not found

			if(!instances[ikey].tinyEditor)
				return false;

//			self.set_Clean(ikey);
			instances[ikey].tinyEditor.getBody().setAttribute('contenteditable', false);
//			instances[ikey].tinyEditor.getDoc().designMode = 'Off';
			$("div.mce-toolbar-grp", instances[ikey].tinyEditor.getContainer()).append("<div class='mce-toolbar-cover'></div>");

			return true;
		},

		/**
		 * Enables the editor, allowing the user to use it.
		 */
		enable: function(ikey)
		{
			if(!instances[ikey])
				return false; // Instance not found

			if(!instances[ikey].tinyEditor)
				return false;

			instances[ikey].tinyEditor.getBody().setAttribute('contenteditable', true);
//			instances[ikey].tinyEditor.getDoc().designMode = 'On';
			$("div.mce-toolbar-grp", instances[ikey].tinyEditor.getContainer()).find("div.mce-toolbar-cover").remove();

			return true;
		}

		// TODO: Destroy instance function, especially needed here since multiple editors can exist
//		 * De-initializes the editor making the DOM object not react anymore
//				 *
//				 * @param bool clean If true, also empties the DOM object after editor is destroyed
//				 */
//				destroy: function(clean)
//				{
//					if(!tinyEditor)
//						return false;
//
//					tinyEditor.off("change");
//					tinyEditor.save();
//					tinyEditor.remove();
//
//					$c = null;
//					params = null;
//					tinyEditor = null;
//					return true;
//				}

	});

	// Load CSS on first use, because this module needs styles
	donrey.load_CSS(donrey.CFG.paths.components_css + "tinymce.css?v=2019.01");
});