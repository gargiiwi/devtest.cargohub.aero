/**
 * Plugin "Marine Group"
 */
cargohub.group_marine = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,
		edit_mode = null,

	/**
	 * Loads the list of organizations connected to a certain group
	 * -- Fires a callback for when the list is loaded, for suplimentary loading of other data (the redirect domain), and setting an initial value to the control
	 */
	org_list = function(group_id, onComplete_callback)
	{
		// Do the ajax request, and on complete return the records
		self.orgs_Source = new $.jqx.dataAdapter({
			url: "?action=organization_list&group_id="+group_id,
			datatype: "json",
			datafileds: [
				{ name: 'id', type: 'int' },
				{ name: 'name'}
			],
			id: 'id'
		}, {
			loadComplete: function()
			{
				if(onComplete_callback)
					onComplete_callback(self.orgs_Source.records);
			}
		});
		return self.orgs_Source;
	}

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
								cargohub.its.alert("The Line or Agent's name should have at least 3 characters");
								return false;
							}

							if(edit_mode) {
								if(!f['group[xdata][master_id]']) {
									cargohub.its.alert("Please choose the master handler organization !");
									return false;
								}

								formData.push({ // the "text" from the master handler control
									name: "group[xdata][master]",
									value: $('#master_handler', $editor).jqxDropDownList('getSelectedItem').label
								});
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
				editor_title = "<b>Editing</b> the Line/Agent: "+source_data.name;
				edit_mode = true;
			} else {
				source_data = {};
				editor_title = "<b>Registering</b> a Shipping line/Agent";
				edit_mode = false;
			}

			cargohub.its.open_Modal_Window('editor', 520, 180, editor_title,
				$("#template_edit_marine").html().present(source_data),
				function($editor) {
					// Master Handler
					if(edit_mode) {
						// The Master Handler organization: Generate jqx control; Find the default value; Setup actions
						$('#master_handler', $editor).jqxDropDownList({
							theme: cargohub.jqxtheme,
							source: org_list(source_data.uid, function(records)
							{
								for(c=0; c<records.length; c++) {
									if(records[c].id == source_data.master_id) {
										// A little delay to allow the creation of the jqxDDL, then selecting the index
										setTimeout(function() {
											$('#master_handler', $editor).jqxDropDownList('selectIndex', c);
										}, 1);
										break;
									}
								}
							}),
							displayMember: "name", valueMember: "id",
							width: 320, height: 25, autoDropDownHeight: true, // dropDownHeight: 170,
							placeHolder: "Select master handler organization:"
						});
					}
				}
			);
		}
	});
});