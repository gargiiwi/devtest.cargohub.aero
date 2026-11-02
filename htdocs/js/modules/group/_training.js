/**
 * Plugin "Training Group"
 */
cargohub.group_training = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,
		edit_mode = null
	;
	this._public({
		/**
		 * Plugin Initialization
		 */
		init: function()
		{
			// Create/Edit window
			cargohub.its.init_Modal_Window('editor', null, {"cancel": "cancel"}, function($editor) {
				$('#modal_editor_ok', $editor).on('click', function() {
					// submit the add/edit form using ajax
					$('.modal_content > form', $editor).ajaxSubmit({
						dataType: 'json',
						beforeSubmit: function(formData, jqForm, options) {
							// setup the field checking object
							var f = {};
							for(var c=0; c< formData.length; c++)
								f[formData[c].name] = formData[c].value;

							if(f['group[name]'].length < 3) {
								cargohub.its.alert("The category's name should have at least 3 characters");
								return false;
							}

							return true;
						},
						success: function(x) {
							if(x.success) {
								// On success, reload the grid and close the window
								$("#groups-grid").jqxGrid('updatebounddata');
								$editor.jqxWindow('close');
								cargohub.its.alert(x.response, "Success !", "thumb_up.png");
							} else {
								cargohub.its.alert(x.reason, "Message from server", "information.png");
							}
						}
					});

				});
			});
		},

		/**
		 * Opens the window used to register or edit registration for this type of data group
		 * @param [Object] source_data When present, the editor is changing an existing group. When empty, it's creating ( or registering one )
		 */
		open_Editor: function(source_data)
		{
			if(source_data) {
				editor_title = "<b>Editing</b> Training: "+source_data.name;
				edit_mode = true;
			} else {
				source_data = {};
				editor_title = "<b>Registering</b> a Training";
				edit_mode = false;
			}

			cargohub.its.open_Modal_Window('editor', 520, 150, editor_title,
				$("#template_edit_training").html().present(source_data),
				function($editor) {
					// Nothing to do here, there is a single input field
				}
			);
		}
	});
});