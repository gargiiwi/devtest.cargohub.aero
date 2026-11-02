/**
 * Sub-Module "Airline (system data)"
 */
cargohub.sysdata_airline = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,
		edit_mode = null,

	/**
	 * Initializes the Airline editor window
	 * -- Data entry name can also be customized here
	 */
	setup_editor = function()
	{
		// init the modal window, with the OK button being setup in the callback
		cargohub.its.init_Modal_Window('editor', null, {"cancel": "cancel"}, function($editor) {
			// Stuff that happens only on the OK button
			$('#modal_editor_ok', $editor).on('click', function() {

				// submit the add/edit form using ajax
				$('.modal_content > form', $editor).ajaxSubmit({
					dataType: 'json',
					beforeSubmit: function(formData, jqForm, options) {
						// setup the field checking object
						var f = {};
						for(var c=0; c< formData.length; c++)
							f[formData[c].name] = formData[c].value;

						if(f['data[name]'].length < 5) {
							cargohub.its.alert("The airline name must have at least 5 characters");
							return false;
						}

						if(f['data[IATA]'].length < 2) {
							cargohub.its.alert("The airline needs a 2-letter IATA code");
							return false;
						}

						if(f['data[AWB_prefix]'].length < 3) {
							cargohub.its.alert("A cargo airline requires a 3-figure Master AWB prefix");
							return false;
						}

						if(f['data[country]'].length == 0) {
							cargohub.its.alert("The airline's country is not specified");
							return false;
						}

						return true;
					},
					success: function(x) {
						$editor.jqxWindow('close');
						cargohub.admin_sysdata.refresh_Grid();
						// document.location.reload();
					},
					error: cargohub.its.http_fail
				});
			});
		});

	}

	;
	this._public({
		/**
		 * Section initialization
		 */
		init: function()
		{
			setup_editor();
		},

		/**
		 * Opens the airline entry editor, in either Add mode ( when source_data is empty ) or Edit mode (when source_data contains airline information)
		 * @param [Object] source_data
		 */
		open_Editor: function(source_data)
		{
			var editor_title;

			if(source_data) {
				editor_title = "<b>Editing</b> - "+source_data.name;
				edit_mode = true;
			} else {
				source_data = {};
				editor_title = "Creating an Airline entry";
				edit_mode = false;
			}

			source_data._itc = cargohub.admin_sysdata.input_theme_classes;
			cargohub.its.open_Modal_Window('editor', 500, 230, editor_title,
				$("#template_edit_airline").html().present(source_data),
				function($editor) {
					// Nothing here, just plain old boring text boxes
				}
			);
		}

	});
});