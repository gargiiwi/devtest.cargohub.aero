/**
 * Sub-Module "Airline Group"
 */
cargohub.group_airline = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,
		valid_airline = false,
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
	},

	/**
	 * Initializes the Airline editor window (find/choose airline, assign a master organization, and the domain to redirect to using the airline search feature)
	 * -- Group name can also be customized here
	 */
	setup_editor = function()
	{
		// init the modal window, with the OK button being setup in the callback
		cargohub.its.init_Modal_Window('editor', null, {"cancel": "cancel"}, function($editor) {
			$('#modal_editor_ok', $editor).on('click', function() {
				// Stuff that happens only on the OK button
				if(!valid_airline) {
					cargohub.its.alert("Unable to proceed due to invalid airline selection");
					return false;
				}

				// submit the add/edit form using ajax
				$('.modal_content > form', $editor).ajaxSubmit({
					dataType: 'json',
					beforeSubmit: function(formData, jqForm, options) {
						// setup the field checking object
						var f = {};
						for(var c=0; c< formData.length; c++)
							f[formData[c].name] = formData[c].value;

						if(f['group[name]'].length < 5) {
							cargohub.its.alert("The airline's (custom) name must have at least 5 characters");
							return false;
						}

						if(edit_mode) {
							if(!f['group[xdata][master_id]']) {
								cargohub.its.alert("Please choose the master handler organization !");
								return false;
							}

							if(!f['group[airline][domain]']) {
								cargohub.its.alert("Please choose a redirect domain for this organization. If there are none yet, create one first.");
								return false;
							}

							formData.push({ // the "text" from the master handler control
								name: "group[xdata][master]",
								value: $('#master_handler', $editor).jqxDropDownList('getSelectedItem').label
							});
						}

						// checks ok, can proceed
						formData.push({
							// the value of "submit on" (a switchbutton)
							name: "group[airline][submiton]",
							value: $('#submiton', $editor).val()
						});

						return true;
					},
					success: function(x) {
						if(x.success) {
							document.location.reload();
						} else {
							cargohub.its.alert(x.reason, "Message from server", "information.png");
						}
					}
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
		 * Opens the group editor, in either Add mode ( when source_data is empty ) or Edit mode (when source_data contains group information)
		 * @param [Object] source_data
		 */
		open_Editor: function(source_data)
		{
			var editor_title;

			if(source_data) {
				editor_title = "<b>Editing</b> - "+source_data.fullname;
				edit_mode = true;
			} else {
				source_data = {};
				editor_title = "<b>Registering</b> an Airline";
				edit_mode = false;
			}

			source_data._itc = cargohub.admin_groups.input_theme_classes;
			cargohub.its.open_Modal_Window('editor', 500, edit_mode? 250: 190, editor_title,
				$("#template_edit_airline").html().present(source_data),
				function($editor) {

					// Prepares the various controls in the editor
					// Airline Search - Used when registering a new airline, not used when it already exists
					if(!source_data.airline_id) {
						valid_airline = false;

						var $searchbox = $("#airline_id", $editor),
						// setup the suggestion source
						searchbox_source = new $.jqx.dataAdapter({
							datatype: "json",
							datafields: [
								{ name: 'id' },
								{ name: 'text' }
							],
							url: "?action=plugin_call&type=airline&method=suggest_airline"
						}, {
							formatData: function (data) {
								if ($searchbox.jqxComboBox('searchString') != undefined) {
									data.q = typed_value = $searchbox.jqxComboBox('searchString');
									return data;
								}
							}
						}),
						// setup the combo search
						searchbox_combo = {
							source: searchbox_source,
							remoteAutoComplete: true, remoteAutoCompleteDelay: 200,
							search: function (search) {
								searchbox_source.dataBind();
							},
							valueMember: "id",
							displayMember: "text",
							width: 320,
							autoDropDownHeight: true,
							dropDownWidth: 320,
							animationType: "none",
							autoComplete: true,
							theme: cargohub.jqxtheme
						};

						// finally, prepare the combobox and events on it
						$searchbox
						.jqxComboBox(searchbox_combo)
						.focus()
						/**
						 * Event: Select - User has clicked or keyed into the search results
						 */
						.on('select', function(event)
						{
							if (event.args != undefined) {
				                var item = $searchbox.jqxComboBox('getItem', event.args.index);
				                if (item != null) {
				                   	var airline_id = item.originalItem.id;
				                	if(airline_id)
				                		donrey.final_Delay('get_airline', function() {
				    	        			$.get("?action=plugin_call&type=airline&method=get_airline&id="+ airline_id, function(x) {
				    	        				if(x.id) {
				    	        					if(x.ord && x.ord.org_id) {
				    	        						$('#group_name', $editor).val('').prop('disabled', true);
				    	        						valid_airline = false;
				    	        						cargohub.its.alert("This Airline has already been registered, choose another !");
				    	        					} else {
				    	        						$('#group_name', $editor).val(x.name).prop('disabled', false);
				    	        						valid_airline = true;
				    	        					}
				    	        				} else {
				    	        					cargohub.its.alert("Error while reading detailed airline data.");
			    	        						$('#group_name', $editor).val('').prop('disabled', true);
			    	        						valid_airline = false;
				    	        				}
				    	        			});
				                		}, 500);
				                }
							}
						})
						/**
						 * Event: Pressing a key - Test if the enter key was pressed, and auto-select first entry in search results
						 */
						.on('keypress', function(event)
						{
							if(event.keyCode == 13) {
								if(!$searchbox.jqxComboBox('getSelectedItem')) {
									// when something was not selected when pressing enter, check if there is only one choice and pick it
									if(source_setup.records.length == 1) {
										$searchbox.jqxComboBox('selectIndex', 0);
									} else {
										// if there are multiple choices, deny the event to the combobox
										$searchbox.jqxComboBox('open');
									}
								}
								// do not allow the form to be submitted by pressing enter !
								event.preventDefault();
								event.stopPropagation();
								return false;
							}
						});

						// The (custom) group name starts disabled if the form is in create mode
						$('#group_name', $editor).prop('disabled', true);
					} else
						valid_airline = true; // editing existing

					// Master Handler and Redirect Domain exist only for editing an airline group. When registering, they are not needed
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
						})
						/**
						 * Action: An organization is chosen, read it's connected domains for the #redirect_domain DropDownList
						 */
						.on('select', function(event)
						{
							var org_id = $(this).val(), domain_source = {};
							// data source for domains
							domain_source = new $.jqx.dataAdapter({
								url: "?action=get_org_domains&org_id="+org_id,
								datatype: "json",
								datafields: [
									{ name: 'domain' }
								],
								id: 'domain'
							}, {
								/**
								 * Find the already selected domain in the loaded records
								 */
								loadComplete: function()
								{
									for(c=0; c<domain_source.records.length; c++) {
										if(domain_source.records[c].uid == source_data.domain) {
											// A little delay to allow the creation of the jqxDDL, then selecting the index
											setTimeout(function() {
												$('#redirect_domain', $editor).jqxDropDownList('selectIndex', c);
											}, 1);
											break;
										}
									}
								}
							});

							// clear, load and enable #redirect_domain DropDownList
							$("input[name='airline[domain]']").val('');
							$("#redirect_domain", $editor).jqxDropDownList({
								disabled: false,
								placeHolder: "Choose redirect domain for airline search",
								source: domain_source
							});
						});

						// The redirect domain for the handler organization used in the airline search system
						$("#redirect_domain", $editor).jqxDropDownList({
							theme: cargohub.jqxtheme,
							// source: null, // initially empty
							displayMember: "domain", valueMember: "domain",
							width: 320, height: 25, autoDropDownHeight: true,
							placeHolder: "(Please select master handler first)",
							disabled: true
						});
					}

					// The switch for visibility in search ( Translates into status-registerd or status-hidden )
					$('#submiton', $editor).jqxSwitchButton({
						theme: cargohub.jqxtheme,
						height: 25, width: 75,
						checked: source_data.submiton == true
					});

				});

		}

	});
});