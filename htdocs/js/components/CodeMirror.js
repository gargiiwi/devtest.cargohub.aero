/**
 * CodeMirror text editor component (singleton)
 * - Can be used in conjunction with an interface module that needs fancy text editing (like template editor, cms editor, mail content, inline text editing, etc.)
 * - The 3rd party library that does the heavy lifting is initialized at first use
 * - For the moment, it supports one single editor being present on the page at one time, and also one single engine active. Page must be reloaded with different parameters to support another engine or same engine with different configuration
 * Note: This is not using jQuery as CodeMirror is not a jQ plugin.
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Components
 * @version 2.7.3
 */
cargohub.components.CodeMirror = donrey.BASE._extend(function() {
	// private
	var self = this,
	master = null,			// Master object that initialized this component
	$c = null, 				// The DOM container which contains the editors
	// Configuration
	params = null,
	// Operation
	changed = false,
	cmEditor = null // The cmEditor object, initialized on first use of setup_Editor()
	;

	this._public({
		name: "CodeMirror",

		// Component events
		events: {},

		/**
		 * Sets up the 3rd party editor to use a specific DOM object as the editor initialization.
		 * If using jQuery, the element must be extracted from the array using [0]
		 *
		 * cmEditor expects a "textarea" to be used as the editor source
		 */
		setup: function(config, _master)
		{
			if(cmEditor)
				return cargohub.alert("CodeMirror Editor is already initialized. One single editor currently supported.");

			if(!config.container || config.container.length == 0)
				return cargohub.alert("Error cannot setup CodeMirror Component because no container for it specified.");

			$c = config.container;
			if(_master)
				master = _master;

			// Prepare default parameters
			params = config.params||{};
			if(!params.cmEditor)
				params.cmEditor = {};
			if(!params.cmEditor.mode)
				params.cmEditor.mode = 'htmlmixed';
			if(!params.cmEditor.lineNumbers)
				params.cmEditor.lineNumbers = true;
			if(!params.cmEditor.indentWithTabs)
				params.cmEditor.indentWithTabs = true;
			// Initialize as "Read-Only", until a content is put in the editor for the first time.

			// Load CodeMirror when initialized
			donrey.load_CSS(donrey.CFG.paths.lib + "codemirror/codemirror.css?v=5.7");
			donrey.load_CSS(donrey.CFG.paths.lib + "codemirror/theme/eclipse.css?v=5.7");
			donrey.LAB.script(donrey.CFG.paths.lib + "codemirror/codemirror.min.js?v=5.7").wait(function() {
				// Load the HTMLmixed mode subscript (only after main code finished)
				donrey.LAB.script(donrey.CFG.paths.lib + "codemirror/mode/htmlmixed/htmlmixed.combined.min.js?v=5.7").wait(function() {
					// Done loading ? Setup from TextArea object
					cmEditor = CodeMirror.fromTextArea($c[0], params.cmEditor);

					// Events
					cmEditor.on("change", function() {
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
						self.events.on_init(true);
				});
			});
		},

		/**
		 * Returns true if the content of the editor was changed since initialization, false if it was not
		 *
		 * @return bool
		 */
		content_Changed: function()
		{
			if(!cmEditor)
				return false;

			changed = !cmEditor.isClean();
			return changed;
		},

		/**
		 * Reads the content of the editor ( for example, with the purpose of saving it )
		 *
		 * @return string
		 */
		content_Read: function()
		{
			if(!cmEditor)
				return false;

			return cmEditor.getValue();
		},

		/**
		 * Replaces existing content editor with new text
		 *
		 * @param string content The new text. If empty, the editor will be reset
		 */
		content_Write: function(content)
		{
			if(!cmEditor)
				return false;

			changed = true;
			cmEditor.setValue(content); // This triggers the onChange event, setting "changed" true first disables the event, since this is an external call
			cmEditor.markClean();
			changed = false;
			cmEditor.setOption("readOnly", false);

			setTimeout(function() {
				cmEditor.refresh();
			}, 1);
			return true;
		},

		/**
		 * Inserts the string at cursor into the active editor
		 */
		content_InsertAtCursor: function(string)
		{
			cmEditor.replaceSelection(string, "around");
		},

		/**
		 * Marks the editor as "clean".
		 * This is usually done after the content of the editor has been externally processed (saved, sent, copied, etc.). This resets the "changed" variable to "false", and any other action inside the editor will call the "on_first_change" event handler.
		 */
		set_Clean: function()
		{
			if(!cmEditor)
				return false;

			cmEditor.markClean();
			changed = false;
			return true;
		},

		/**
		 * Focuses on the editor, placing the cursor inside the editing area
		 */
		focus: function()
		{
			if(!cmEditor)
				return false;

			cmEditor.focus();
			return true;
		},

		/**
		 * Disables the editor, disallowing the user to change it's content.
		 * Also marks it as "clean". This operation is usually used when the editor must remain in memory but there is no content to be edited.
		 */
		disable: function()
		{
			if(!cmEditor)
				return false;

			self.set_Clean();
			cmEditor.setOption("readOnly", "nocursor");
			return true;
		},

		/**
		 * Enables the editor, allowing the user to use it.
		 */
		enable: function()
		{
			if(!cmEditor)
				return false;

			cmEditor.setOption("readOnly", false);
			return true;
		},

		/**
		 * De-initializes the editor making the DOM object not react anymore
		 *
		 * @param bool clean If true, also empties the DOM object after editor is destroyed
		 */
		destroy: function(clean)
		{
			if(!cmEditor)
				return false;

			cmEditor.off("change");
			cmEditor.toTextArea();

			$c = null;
			params = null;
			cmEditor = null;
			return true;
		}

	});
});