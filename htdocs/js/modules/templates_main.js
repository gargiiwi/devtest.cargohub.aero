/**
 * Module "Templates"
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoITS
 * @version 2.5
 */
cargohub.templates_main = donrey.BASE._extend(function() {
	// private
	var cmEditor = null,
		origContents = null, // cached original template contents (for the discard functionality)
		currentPath = null,
		alreadyDirty = false,
		input_theme_classes = "jqx-widget jqx-widget-content jqx-widget-"+cargohub.jqxtheme+" jqx-input-"+cargohub.jqxtheme+" jqx-widget-content-"+cargohub.jqxtheme,

		messages = {
			"load_hint": "Click on a template in the left side tree to load a template in the editor",
			"loaded": "Template <b>{$name}</b> has been loaded",
			"changed": "Contents of the editor has been changed. Please save !",
			"revert": "Contents of the editor has been reverted to the original template",
			"unclean": "The editor's contents has been changed.",
			"tplswitch": "\nSave before switching to another template?",
			"tplclose": "\nSave before closing the editor?",
			"unload": "\nSave before moving to another page?",
			"saved": "The template has been updated",
			"closed": "Current template has been closed",
			"delete": "Are you sure?"
		},

    setup_Toolbar = function(){
		$('.toolbar button')
                .jqxButton({ theme: cargohub.jqxtheme })
                .on('click', function(){
                    var data = $(this).data();
                    if(data.confirm){
                            if(!confirm('Are you sure you want to ' + data.confirm_msg))
                                    return;
                    }

                    switch(data.op) {
                            case 'preview':
                                    onPreview();
                                    break;
                            case 'add':
                                    onCreate();
                                    break;
							case 'delete':
									onDelete();
									break;
							case 'save':
								onSave();
								break;
							case 'revert':
								onRevert();
								break;
							case 'close':
								onClose();
								break;
                    }
                });
				//$("div#command_buttons #cmdSave").on('click', onSave);
				//$("div#command_buttons #cmdRevert").on('click', onRevert);
				//$("div#command_buttons #cmdClose").on('click', onClose);
        },

        /**
         *
         * Open preview window
         */

    open_Preview_Window = function(event)
    {

            cargohub.its.init_Modal_Window('editor', null, {"cancel": "cancel"});
            cargohub.its.open_Modal_Window('editor', 1000, 580, "Preview Template", $('#template_preview').html(), function($editor) // Setup the controls inside the window
            {
                    	//$('#modal_editor_ok').css('display','none');

                    //current path
                    currentPath = window.args.element.attributes[1].value;

                    //Load content into div
                    $.post('?action=load', {"path": currentPath}, function(x)
                    {
                            if(x.success) {
                                var $frame = $('<iframe style="width:100%; height:500px;" frameBorder="0">');
                                    //alert(x.contents);
                                $('.modal_content').html($frame);
                                var doc = $frame[0].contentWindow.document;
                                doc.write(x.contents);
                            }else{
                                alert(x.reason);
                            }
                    });
             });
    },

	/**
	 * Open create window
	 */
	open_Create_Window = function()
    {
		cargohub.its.init_Modal_Window('editor', null, {"cancel": "cancel"});
        cargohub.its.open_Modal_Window('editor', 350, 120, "New Template", $('#template_create').html(), function($editor) // Setup the controls inside the window
		{
            $('#modal_editor_ok', $editor).off('click').on('click', function() {
				$('.modal_content > form', $editor).ajaxSubmit({
					dataType: 'json',
					beforeSubmit: function(formData, jqForm, options) {
						// setup the field checking object
						var f = {};
						for(var c=0; c< formData.length; c++)
							f[formData[c].name] = formData[c].value;
						return true;
					},
					success: function(x) {
						if(x.success==true) {
							document.location.reload();
						} else {
							cargohub.its.alert(x.reason, "Message from server", "information.png");
						}
					}
				});
			});
        });
    },

	open_Delete_Window = function()
	{
		currentPath = window.args.element.attributes[1].value;
		if(window.confirm(messages.delete) == true) {
			$.post('?action=delete', {"path": currentPath}, function(x)
			{
				if(x.success){
					document.location.reload();
				}else
					alert(x.reason);
			});
		} else
			$("#message_box").html(messages.load_hint);
	}

    /**
	 * Load data into the editor
	 */
	loadContents = function(event)
	{
		currentPath = $(event.args.element).data('path');
		// show loading
 		// ...
		// load based on path
		$.post('?action=load', {"path": currentPath}, function(x)
		{
			// hide loading
			// ...
			if(x.success) {
				origContents = x.contents;

				alreadyDirty = true;
				cmEditor.setValue(origContents); // This triggers the onEditorChange event
				alreadyDirty = false;
				cmEditor.markClean();

				cmEditor.setOption("readOnly", false);
				var msgVars = {
					name: event.args.element.id.substr(5)
				};
				$("#message_box").html(messages.loaded.present(msgVars));

				// After loading, the Close button is shown
				$("#cmdClose").fadeIn();

				//Check if the file is from static folder, if is true then show delete button
				if(x.path.lastIndexOf('/static/') != -1)
					$('#delete').fadeIn();
				else
					$('#delete').fadeOut();
				//Preview button
				$('#tem_prev_operations').fadeIn();

			} else
				// Unable to load?
				alert(x.reason);
		});

	},
	/**
	 * Clicked on an item which does not represent a template, cancel selection
	 */
	cancelSelect = function()
	{
		// check state
		var state = $('li[data-path="'+ currentPath +'"]').data('selectable');

		// if selectable, make unselectable
		if(state)
			$('li[data-path="'+ currentPath +'"]').data('selectable', false);

		// switch back to correct tree item
		$("div.templates div#tree").jqxTree('selectItem', $('li[data-path="'+ currentPath +'"]')[0]);

		// if selectable, make selectable back
		if(state)
			$('li[data-path="'+ currentPath +'"]').data('selectable', true);
	},

	/**
	 * When selecting a template in a tree
	 */
	onSelect = function(event)
	{
		// check if clicking on a folder
		if(!$(event.args.element).data('selectable'))
			return cancelSelect();

                //alert(cmEditor.isClean());
                // Check if editor is dirty, and propose to save contents if so
		if(!cmEditor.isClean()) {
                        //dd('da');
			if(window.confirm(messages.unclean + messages.tplswitch) == true) {
				onSave();
			}else{
				cancelSelect();
                return false;
            }
		} else
			$("#message_box").html(messages.tplswitch);


		// Load (new) contents
		loadContents(event);

	},

	/**
	 * Called when the content off the editor has been changed (so we know that the content must be saved or discarded)
	 */
	onEditorChange = function()
	{
		if(alreadyDirty)
			return; // already changed, nothing special to do

		// On first change, do some operations (show buttons, show a message)
		alreadyDirty = true;
		$("#message_box").html(messages.changed);
		$("#cmdRevert").fadeIn();
		$("#cmdSave").fadeIn();
	},

	/**
	 * When pressing the Save button
	 */
	onSave = function()
	{
		if(currentPath == null)
			return alert('No content loaded in the editor right now');
		// show ajax wait
		// ...
		// load based on path
		$.post('?action=save', {
			"path": currentPath,
			"contents": cmEditor.getValue()
		}, function(x) {
			// hide ajax wait
			// ...
			if(x.success) {
				// saved successfully
				origContents = cmEditor.getValue(); // update the cached contents, so discard shows the updated content, not the original one
				cmEditor.markClean();
				alreadyDirty = false;
				$("#cmdRevert").fadeOut();
				$("#cmdSave").fadeOut();

				$("#message_box").html(messages.saved);
			} else
				// Error while saving ?
				alert(x.reason);
		});
	},

	/**
	 * When pressing the Revert button, the original contents of the template is put in the editor (discarding any changes)
	 */
	onRevert = function()
	{
		// fill editor with original data
		cmEditor.setValue(origContents);
		alreadyDirty = false;
		cmEditor.markClean();

		$("#message_box").html(messages.revert);

		// hide the Save/Revert buttons
		$("#cmdRevert").fadeOut();
		$("#cmdSave").fadeOut();
	},

	/**
	 * When pressing the Close button, the contents of the editor is emptied, and template is unselected in the tree
	 */
	onClose = function()
	{
		// Check if editor is dirty, and propose to save contents if so
		if(!cmEditor.isClean()) {
			if(confirm(messages.unclean + messages.tplclose) == true) {
				onSave();
			} else
				$("#message_box").html(messages.load_hint);
		} else
			$("#message_box").html(messages.load_hint);

		alreadyDirty = true;
		cmEditor.setValue(messages.load_hint);
		alreadyDirty = false;
		cmEditor.markClean();
		cmEditor.setOption("readOnly", "nocursor");

		// Hide all buttons
		$("div#command_buttons input").fadeOut();

        //Hide preview buttons
        $('#tem_prev_operations').fadeOut();

		// And remove the tree selection
//		$("div.templates div#tree").jqxTree('selectItem', null); // Unfortunately it's bugged and doesn't work
	},

	onUnload = function()
	{
		// Check if editor is dirty, and propose to save contents if so
		if(!cmEditor.isClean()) {
			if(confirm(messages.unclean + messages.unload) == true) {
				onSave();
			}
		}
	},


    /**
    * Open a preview template
    */
    onPreview = function(event)
    {
		open_Preview_Window(event);
    },

    /**
	 * Open create template window
	 */
	onCreate = function(event)
    {
		open_Create_Window(event);
    },

	/**
	 * Open delete confirmation
	 */
	onDelete = function()
	{
		onClose();
		open_Delete_Window();
	},

	/**
	 * Sets up the actions that the Save/Discard buttons do
	 */
	setup_ActionBar = function()
	{
		// Initial message
		$("#message_box").html(messages.load_hint);

		// jqWidgetize buttons
//		$("div#command_buttons input").jqxButton({ width: 120, theme: cargohub.jqxtheme });

		// Button actions
//		$("div#command_buttons #cmdSave").on('click', onSave);
//		$("div#command_buttons #cmdRevert").on('click', onRevert);
//		$("div#command_buttons #cmdClose").on('click', onClose);
	}
	;

	// public, yay
	this._public({
		init: function() {
			//setup Toolbar
            setup_Toolbar();

			// create splitter
			$("div.templates").jqxSplitter({ theme: cargohub.jqxtheme, width: cargohub.fullwidth, height: 600, panels: [{ size: 250}] });

			// create the tree and bind select
			$('div.templates div#tree').jqxTree({ theme: cargohub.jqxtheme, height: '100%', width: '100%' });
			$('div.templates div#tree').on('select', onSelect);

			// Setup actions on buttons
			setup_ActionBar();

			// init editor (codemirror)
			cmEditor = CodeMirror.fromTextArea($('div#editor textarea#content')[0], {
				mode: 'htmlmixed',
				lineNumbers: true,
				indentWithTabs: true,
				readOnly: "nocursor"
			});

			cmEditor.on("change", onEditorChange);

			// onUnload event
			$(window).on("unload", onUnload);



		}
	});

});
