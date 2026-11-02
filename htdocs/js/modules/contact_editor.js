/**
 * This is a universally usable contact editor interface (singleton)
 * - Primarely loaded by the contact viewer/editor of the Communication Center
 * - Allows viewing and editing of issue related contacts in:
 *   - Submit issue
 *   - Issue overview (staff)
 *   - E-mail based login/account
 * - Can be opened in read-write or read-only mode (depending on who is opening which contact)
 * - Receives a container element as initialization parameter, which can be in a persistent area or in a modal (whose content dissapears when closed)
 * - Works using "Contenteditable" attribute for the content fields
 *
 * Requires jqxMenu, jqxTooltip
 */
cargohub.contact_editor = donrey.BASE._extend(function() {
	// private
	var self = this,
		$c = null, // The DOM container which contains the contact display/editor
		params = null,
		active_groups = {}, // Groups which are visible in the editor (value is "true" for all)
		active_fields = {}, // Fields which are visible and editable (value is the group key)
		placeholders = {
			name: "(Type the name here)",
			email: "(Type e-mail address here)",
			field: "(not set)"
		},
		$field_dd = null, // The DOM element containing the Add/Edit field dropdown, transformed into jqxMenu - Created on first use
		changed = false,
		valid = false,

	paste_filter = function(e) {
		var paste = "";
		e.preventDefault();
		if(e.clipboardData || e.originalEvent.clipboardData)
			paste = (e.originalEvent || e).clipboardData.getData('text/plain');
		else if(window.clipboardData)
			paste = window.clipboardData.getData("Text");
		if(paste.length > 0)
			$(this).text(paste);
	},

	/**
	 * Creates the HTML used to display the group
	 *
	 * @returns The jQuery object in which field entries can be added
	 */
	spawn_Group = function(group)
	{
		var group_meta = self.config.fields_structure[group].meta;
		group_meta.key = group;

		// Add the group rows to the end of the table then set the internal flag.
		$("table.contact-table", $c).append($("#template-contact-group").html().present(group_meta));
		active_groups[group] = true;
	},

	/**
	 * Creates the HTML for a field and optionally places content into the value area
	 */
	spawn_Field = function(group, field, value, no_status_change)
	{
		// Spawn group if it doesn't exist yet
		if(!active_groups[group])
			spawn_Group(group);

		var field_cfg = self.config.fields_structure[group].fields[field], tpl_data = {
			key: field,
			info: value,
			label: field_cfg instanceof Array? field_cfg[0]: field_cfg
		};

		// Mandatory fields check for spawning new ones?
		if(params.mandatory_fields && $.inArray(field, params.mandatory_fields) > -1)
			tpl_data.mandatory = true;

		// Add row to the table, just before the "contact-group-end"
		$("table.contact-table", $c).find("tr#contact-group-end-"+group).before($("#template-contact-field").html().present(tpl_data));
		active_fields[field] = group;
		if(!no_status_change)
			changed = true;
	},

	/**
	 * Verifies if all fields have proper values to allow the contact to be saved and window closed using the "Ok" button
	 * - Name must be at least 5 characters in length and field not have data[empty] = tru
	 * - E-mail must look like an e-mail
	 * - There should not be any XD fields with class "field-error" on them.
	 */
	check_Validity = function()
	{
		if($("#contact-name > span", $c).data('empty') == true)
			return false;
		if($("#contact-name > span", $c).text().length < 4)
			return false;
		if($("#contact-email > span", $c).data('empty') == true)
			return false;
		if(!validate_email($("#contact-email > span", $c).text()))
			return false;

		// If there are any mandatory fields, check if they have content here:
		var found_empty = false;
		if(params.mandatory_fields) {

			// Read all the xdata fields
			$("table.contact-table tr", $c).each(function() {
				row_id = $(this).prop('id');
				if(!row_id)
					return; // Not an XD group or field, probably the header row (with picture, name, email), the button row or a separator

				if(row_id.substr(8,5) == 'field') {
					field = row_id.substr(14);

					if($.inArray(field, params.mandatory_fields)==-1)
						return; // When the field is not in the list of mandatory ones, it is skipped from the check

					$xdata_span = $(this).find('span');
					if($xdata_span.data('empty') == true)
						found_empty = true;
					return;
				}
			});
		}

		if(found_empty)
			return false;

		return true;
	},

	/**
	 * Checks the name field for problems and auto-capitalize first letter if name is detected as valid.
	 * -- TODO: Might get smarter in the future, to remove/correct invalid characters which should not appear in a "name".
	 *
	 * @param $input Containing the name
	 * @param content
	 */
	check_Name_Field = function($input, content)
	{
		if(!content)
			content = $.trim($input.text());
		if(content.length < 4)
			$input.addClass('field-error').jqxTooltip({
				content: "The name is too short, minimum 4 characters are required",
				position: 'mouse',
				theme: cargohub.jqxtheme
			});
		else {
			if(content.length > 100) {
				content = $.trim(content).substr(0, 100);
				$input.jqxTooltip({
					content: "The name was longer than 100 characters and it was cut",
					position: 'mouse',
					theme: cargohub.jqxtheme
				});
			}
			// Value is correct, auto-capitalize first letter of the contact name
			$input.text(content.charAt(0).toUpperCase() + content.substr(1));
		}
	}
	;
	this._public({
		/**
		 * Prepares this editor's internal variables. (Automatically called when this module is created.)
		 * Configuration should contain Xdata field definitions (Grouping, Icons, Descriptions) - Used to generate the add/edit menu and also create fields and groups when fields are requested to be added/edited
		 */
		init: function(controller)
		{
//			dd(controller);
//			dd(self.config);
		},

		/**
		 * Activates the ability to edit contact data on an existing VCard HTML which is inside a $container object
		 * - Analyses the DOM inside $container and sets up editor's variables
		 * - Adds "contenteditable" attribute (prop) to all discovered fields (if not set already)
		 * - Initializes the add/edit drop-down having id "contact-field-edit"
		 * - Sets up events on all live elements (spans with contenteditable), using the delegate event method
		 *
		 * @param $container
		 */
		setup_VCard: function($container, _params)
		{
			if(!$container || $container.length == 0)
				return false;

			$c = $container;
			params = _params||{};

			// (Re)Set the values first
			changed = false;
			valid = false;
			active_groups = {};
			active_fields = { name: true, email: true };

			// Add "contenteditable" to name and e-mail fields and events for focusing them
			$("#contact-name > span, #contact-email > span", $c).prop("contentEditable", 'true').prop("spellCheck", 'false')
			.on("paste", paste_filter)
			/**
			 * Event: The Name or E-mail fields are focused - Remove the placeholder and the "gray" class
			 */
			.on("focus", function() {
				$(this).removeClass("gray field-error").jqxTooltip('destroy');

				// Just don't do this on WebKit... looks bad but at least fucking works... Try to find a fix later.
				if(!/AppleWebKit\/([\d.]+)/.exec(navigator.userAgent))
					$(this).addClass("jqx-input jqx-input-"+cargohub.jqxtheme);

				if($(this).data('empty') == true)
					$(this).empty();
			})
			/**
			 * Event: The Name or E-mail fields are no longer focused - Check if content is empty, if so put back the placeholder back and re-add the "gray" class.
			 */
			.on("blur", function() {
				var which_item = $(this).parent().prop('id').substr(8), content = $.trim($(this).text());

				$(this).removeClass("jqx-input jqx-input-"+cargohub.jqxtheme);
				if(content.length == 0)
					$(this).addClass("gray").text(placeholders[which_item]).data('empty', true);
				else {
					$(this).data('empty', false);
					// Not empty - check if the name is too short or the e-mail address doesn't look like an e-mail address.
					switch(which_item) {
						case 'name':
							check_Name_Field($(this), content);
							break;
						case 'email':
							if(content.length > 100) {
								$(this).addClass('field-error').jqxTooltip({
									content: "The content of the e-mail address field is too long, maximum 100 characters supported.",
									position: 'mouse',
									theme: cargohub.jqxtheme
								});
								break;
							}

							if(!validate_email(content))
								$(this).addClass('field-error').jqxTooltip({
									content: "This is not a valid e-mail address, unable to use it",
									position: 'mouse',
									theme: cargohub.jqxtheme
								});
							break;
					}
				}

				changed = true;
				valid = check_Validity();

				if(params.on_change)
					params.on_change(valid);
			})
			/**
			 * Event: These two ( name and E-mail ) are single lines. Make sure the "Enter" key actually blurs the contenteditable, instead of typing a newline into the content
			 */
			.on('keypress', function(event) {
				if(event.keyCode == 13 || event.key == "Enter") {
					$(this).blur();
					event.preventDefault();
				}
			});

			// Check the initial name if it has problems
			check_Name_Field($("#contact-name > span", $c));

			// Scan the table for already visible vcard groups and fields and build the list of active_groups, active_field
			var group = null, field = null, row_id = null, group_cfg = null, field_cfg = null;
			$("table.contact-table tr", $c).each(function() {
				row_id = $(this).prop('id');
				if(!row_id)
					return; // Not an XD group or field, probably the header row (with picture, name, email), the button row or a separator

				if(row_id.substr(8,5) == 'group') {
					group = row_id.substr(14);
					active_groups[group] = true;
					return;
				}

				if(row_id.substr(8,5) == 'field') {
					field = row_id.substr(14);
					active_fields[field] = group;
					$(this).find("td > span").prop("contentEditable", 'true').prop("spellCheck", 'false')
					.on("paste", paste_filter);

					// If this field "row" is present among the mandatory fields, mark it with a yellow(ish) background
					if(params.mandatory_fields && $.inArray(field, params.mandatory_fields) > -1)
						$(this).css("background-color", "#FFFFA0");

					return;
				}
			});

			// Scan the fields config for "autogen" flag in "Meta" and auto-generate those groups and fields if they don't already exist
			for(group in self.config.fields_structure) {
				group_cfg = self.config.fields_structure[group];
				if(group_cfg.meta.autogen)
					for(field in group_cfg.fields) {
						if(!active_fields[field])
							spawn_Field(group, field, null, true);
					}
			}

			// Finally, setup the events on all rows, current and future ones, using delegate event format
			$("table.contact-table", $c)
			/**
			 * Event: A xdata field span is focused. They should all already have "contenteditable" set on them
			 */
			.on("focus", "td > span", function() {
				$(this).removeClass("gray field-error").jqxTooltip('destroy');

				// Just don't do this on WebKit... looks bad but at least fucking works... Try to find a fix later.
				if(!/AppleWebKit\/([\d.]+)/.exec(navigator.userAgent))
					$(this).addClass("jqx-input jqx-input-"+cargohub.jqxtheme);

				if($(this).data('empty') == true)
					$(this).empty();
			})
			/**
			 * Event: The data field loses focus
			 */
			.on("blur", "td > span", function() {
				var content = $(this).html();
				$(this).removeClass("jqx-input jqx-input-"+cargohub.jqxtheme);
				if(content.length == 0)
					$(this).data('empty', true).addClass("gray").text(placeholders['field']);
				else
					// No special checking on the fields yet. They might be empty or not, empty ones are not saved in the database anyway.
					$(this).data('empty', false).html($.trim(content).replace(/(<br>\s*)+$/,''));

				changed = true;
				valid = check_Validity();

				if(params.on_change)
					params.on_change(valid);
			})
			/**
			 * Disallow multi-line, as these fields are usable in a table as well, and would not show up correctly
			 */
			.on("keypress", "td > span", function(event) {
				if(event.keyCode == 13 || event.key == "Enter") {
					$(this).blur();
					event.preventDefault();
				}
			})
			/**
			 * Event: Mousing over a xdata field label should show a delete button (-), which removes a field from the table
			 */
			.on("mouseenter", "th", function() {
				var row_id = $(this).parent().prop('id');
				if(!row_id)
					return; // Nothing to do, above a group or header

				if(!(params.mandatory_fields && $.inArray(row_id.substr(14), params.mandatory_fields) > -1))
					$(this).find('button').fadeIn('fast');
			})
			/**
			 * Event: Moving out of the field's label hides the delete button
			 */
			.on("mouseleave", "th", function() {
				if(!$(this).parent().prop('id'))
					return; // Nothing to do, above a group or header

				$(this).find('button').hide();
			})
			/**
			 * Event: Clicking on row button should delete the row
			 */
			.on("click", "th > button", function() {
				var $tr = $(this).parent().parent();
				if(!$tr.find('td > span').data('empty') && $tr.find('td > span').text().length > 0)
					if(!confirm("Removing non-empty field:\n== "+$tr.find('th').text()+" ==\n\nAre you sure ?"))
						return;

				delete active_fields[$tr.prop('id').substr(14)];
				$tr.remove();
			})
			/**
			 * Event: Clicking on a label focuses the corresponding field
			 */
			.on("click", "th > label", function() {
				var $tr = $(this).parent().parent();
				$tr.find("td > span").focus();
			});

			// The button that initializes the Field focus dropdown
			$("button#contact-field-edit", $c)
			.jqxButton({
				theme: cargohub.jqxtheme,
				width: 80
			})
			/**
			 * Event: Clicking on the Add new field button, which should open a drop-down menu with possible selections
			 */
			.on("click", function(event)
			{
				if(!$field_dd) {
					var tpl_data = {ddmenu: {}}, group_li = null;
					// Analyse the structure and build the drop-down menu on first use
					for(group in self.config.fields_structure) {
						group_cfg = self.config.fields_structure[group];
						group_li = {
							meta: group_cfg.meta,
							fields: {}
						};
						for(field in group_cfg.fields) {
							field_cfg = group_cfg.fields[field];
							group_li.fields[field] = { text: field_cfg instanceof Array? field_cfg[1]: field_cfg };
						};
						tpl_data.ddmenu[group] = group_li;
					}

					$field_dd = $($("#template-field-ddmenu").html().present(tpl_data)).jqxMenu({
						theme: cargohub.jqxtheme,
						autoOpenPopup: false,
						popupZIndex: 20000,
						animationShowDuration: 100,
						mode: 'popup'
					});
					// Actions on the elements in the menu, existing or future ones.
					// TODO: Move this outside of here, there might be other menus - Also support appending actions to the already existing event handler ?
					$(document).on('click', ".jqx-menu-item", function(event)
					{
						var data = $(this).data();

						if(data.op && data.op == "contact-focus-field") {
							if(!active_fields[data.field])
								spawn_Field(data.group, data.field);

							// jqx-ize the delete button on this new field
							$("#contact-field-"+data.field).find('th > button').jqxButton({
								theme: cargohub.jqxtheme,
								width: 22,
								height: 22
							});

							// Focus the selected field (should also scroll to it)
							$("#contact-field-"+data.field, $c).find('span').trigger('focus');
						}

					});
				}
				$field_dd.jqxMenu('open', $(this).offset().left, $(this).offset().top + 27);
			});

			// jqx-ize existing field delete buttons (note that the buttons are still be invisible at this point)
			$("table.contact-table th > button", $c).jqxButton({
				theme: cargohub.jqxtheme,
				width: 22,
				height: 22
			});

			// Initialize validity variable and return
			valid = check_Validity();
			return true;
		},

		/**
		 * Returns the status of the contact editor: "changed" and "valid" might be true or false
		 * - If the editor is changed and valid, the calling module should use read_vCard() to grab the information
		 *
		 * @return Object with two keys
		 */
		check_Status: function()
		{
			valid = check_Validity();
			return {
				changed: changed,
				valid: valid
			};
		},

		/**
		 * Reads the content of all existing fields and returns an object which could be serialized and sent to the server by an external function
		 * - Contains keys: "name", "email", "xdata"
		 *
		 * @returns Contact object
		 */
		read_vCard: function()
		{
			var contact = {
				// Read the content of "name" and "email" fields
				name: $.trim($("#contact-name > span", $c).text()),
				email: $.trim($("#contact-email > span", $c).text()),
				xdata: {}
			}, $xdata_span = null;

			// Read all the xdata fields
			$("table.contact-table tr", $c).each(function() {
				row_id = $(this).prop('id');
				if(!row_id)
					return; // Not an XD group or field, probably the header row (with picture, name, email), the button row or a separator

				if(row_id.substr(8,5) == 'field') {
					field = row_id.substr(14);
					$xdata_span = $(this).find('span');
					if(!$xdata_span.data('empty') == true) {
						var tmp = document.createElement("DIV");
						tmp.innerHTML = $xdata_span.text();
						contact.xdata[field] = $.trim(tmp.textContent || tmp.innerText || "");
					}
					return;
				}
			});

			// If this is empty, remove it from the returned information
			if(!contact.xdata)
				delete contact.xdata;

			return contact;
		},

		/**
		 * Sets the value of a field item
		 *
		 * @param field_key
		 */
		set_Field_Value: function(field_key)
		{

		},

		/**
		 * Activates editing (and focuses) on a field in a group.
		 * - If the field and the group are not used yet, they are created
		 *
		 * @param field_key
		 */
		trigger_Edit: function(field_key)
		{

		}
	});
});