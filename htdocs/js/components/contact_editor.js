/**
 * Component "Contact Editor"
 * - Primarely loaded by the contact viewer/editor of the Communication Center
 * - Allows viewing and editing of issue related contacts in:
 *   - Submit issue
 *   - Issue overview (staff)
 *   - E-mail based login/account
 * - Can be opened in read-write or read-only mode (depending on who is opening which contact)
 * - Receives a container element as initialization parameter, which can be in a persistent area or in a modal (whose content dissapears when closed)
 *
 * @depend component:modal (For Alerts), jqxMenu, jqxTooltip
 * @todo Enhance the "change" variable to reflect if fields were actually changed (not just blurred with valid option)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Components
 * @version 2020.11.01
 */
cargohub.components.contact_editor = donrey.BASE._extend(function() {
	// private
	var self = this,
		master = null,			// Master object that initialized this component
		$c = null, 				// The DOM container which contains the contact display/editor
		// Configuration
		params = null,
		defaults = {},
		active_groups = {},		// Groups which are visible in the editor (value is "true" for all)
		active_fields = {},		// Fields which are visible and editable (value is the group key)
		$field_dd = null, // The DOM element containing the Add/Edit field dropdown, transformed into jqxMenu - Created on first use
		changed = false,
		valid = false,
		mandatory_color = "#FFFFA0",

	paste_filter = function(e) {
		var paste = "";
		e.preventDefault();
		if(e.clipboardData || e.originalEvent.clipboardData)
			paste = (e.originalEvent || e).clipboardData.getData('text/plain');
		else if(window.clipboardData)
			paste = window.clipboardData.getData("Text");
		if(paste.length > 0)
			$(this).val(paste);
	},

	/**
	 * Creates the HTML used to display the group
	 *
	 * @returns The jQuery object in which field entries can be added
	 */
	spawn_Group = function(group)
	{
		var group_meta = self.fields_structure[group].meta;
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

		var field_cfg = self.fields_structure[group].fields[field], tpl_data = {
			key: field,
			info: value,
			label: field_cfg instanceof Array? field_cfg[0]: field_cfg
		};

		// Add row to the table, just before the "contact-group-end"
		$spawned_field = $($("#template-contact-field").html().present(tpl_data));
		$spawned_field.find("td > input")
		.prop("spellcheck", false)
		.on("paste", paste_filter);

		// Mandatory fields check for spawning new ones?
		if(params.mandatory_fields && params.mandatory_fields.indexOf(field)>=0) {
			$spawned_field
				.data("mandatory", true)
				.css("background-color", mandatory_color);
			$spawned_field.find("td > input").prop("placeholder", "Mandatory field, please enter a value here !");
		}

		$("table.contact-table", $c).find("tr#contact-group-end-"+group).before($spawned_field);
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
		// Start by assuming all is ok, if any of the fields is not ok then set valid = false (but also trigger some potential visual indicators)
		valid = true;

		if($("#contact-name > input", $c).data('empty') == true)
			valid = false; // Empty
		if($("#contact-name > input", $c).val().length < 3)
			valid = false; // Too short
		if(/[<>:;@\,"]/.test($("#contact-name > input", $c).val()))
			valid = false; // Invalid chars
		if($("#contact-email > input", $c).data('empty') == true)
			valid = false; // Empty e-mail
		if(!validate_email($("#contact-email > input", $c).val()))
			valid = false; // Invalid e-mail

		// If there are any mandatory fields, check if they have content here:
		if(params.mandatory_fields) {

			// Read all the xdata fields
			$("table.contact-table tr", $c).each(function() {
				row_id = $(this).prop('id');
				if(!row_id)
					return; // Not an XD group or field, probably the header row (with picture, name, email), the button row or a separator

				if(row_id.substr(8,5) == 'field') {
					field = row_id.substr(14);

					if($(this).data('mandatory')) {
						$xdata_input = $(this).find('input');
						if($xdata_input.data('empty') == true) {
							valid = false;
							$xdata_input.jqxTooltip({
								theme: cargohub.jqxtheme,
								content: "This field is mandatory and is currently empty,<br />please complete it !",
								position: 'bottom'
							});
						}
					}
				}
			});
		}

		return valid;
	},

	/**
	 * Checks the name field for problems, trim extra spaces and auto-capitalize first letter if name is detected as valid.
	 * -- TODO: Might get smarter in the future, to remove/correct invalid characters which should not appear in a "name".
	 *
	 * @param $input Containing the name
	 * @param content
	 */
	check_Name_Field = function($input, content)
	{
		if(!content)
			content = $input.val();
		content = $.trim(content);
		if(content.length < 3) {
			$input.addClass('field-error').jqxTooltip({
				theme: cargohub.jqxtheme,
				content: "The name is too short, minimum 3 characters are required",
				position: 'bottom',
				width: 250
			});
			$input.jqxTooltip('open');
			return content;
		} else if(/[<>:;@\,"]/.test(content)) {
			$input.addClass('field-error').jqxTooltip({
				theme: cargohub.jqxtheme,
				content: "The name must not contain invalid characters: &lt; &gt; , : ; @ \\ &quot;",
				position: 'bottom',
				width: 250,
				left: 15
			});
			$input.jqxTooltip('open');
			return content;
		} else {
			if(content.length > 100) {
				content = content.substr(0, 100);
				$input.jqxTooltip({
					theme: cargohub.jqxtheme,
					content: "The name was longer than 100 characters and it was cut",
					position: 'bottom',
					width: 250
				}).jqxTooltip('open');
			}
			// Value is correct, auto-capitalize first letter of the contact name
			return content.charAt(0).toUpperCase() + content.substr(1);
		}
	},

	/**
	 * Checks if the E-mail field is correct, remove
	 * @param $input Containing the email
	 * @param content
	 */
	check_Email_Field = function($input, content)
	{
		if(!content)
			content = $input.val();
		content = $.trim(content);

		// Verify E-mail
		if(content.length > 100) {
			$input.addClass('field-error').jqxTooltip({
				theme: cargohub.jqxtheme,
				content: "The E-mail address field is too long, maximum 100 characters supported.",
				position: 'bottom',
				width: 300,
				left: 15
			}).jqxTooltip('open');
			$input.val(content);
			return content;
		} else
		if(!validate_email(content)) {
			$input.addClass('field-error').jqxTooltip({
				theme: cargohub.jqxtheme,
				content: "This is not a valid e-mail address, unable to use it",
				position: 'bottom',
				width: 250,
				left: 15
			}).jqxTooltip('open');
			$input.val(content);
			return content;
		} else
			// Value is correct, return it (trimmed)
			return content;
	}
	;
	this._public({
		name: "Contact Editor",

		// Component events
		events: {},

		/**
		 * Activates the ability to edit contact data on an existing VCard HTML which is inside a $container object
		 * - Analyses the DOM inside $container and sets up editor's variables
		 * - Initializes the add/edit drop-down having id "contact-field-edit"
		 * - Sets up events on all live elements (inputs with invisible borders), using the delegate event method
		 *
		 * @param $container
		 */
		setup: function(config, _master)
		{
			if(!config.container || config.container.length == 0)
				return cargohub.alert("Error, could not setup Contacts List because container not specified or not found.");

			// Check if self.fields_structure is set
			if(!self.fields_structure)
				return cargohub.alert("Error, the contact editor fields structure doesn't exist, cannot initialize.");

			$c = config.container;
			if(_master)
				master = _master;

			params = config.params||{};
			defaults = config.defaults||{};

			// (Re)Set the values first
			changed = false;
			valid = false;
			active_groups = {};
			active_fields = { name: true, email: true };

			// Controls for the Name/E-mail inputs
			if(!params.disable_primary_edit)
			$("#contact-name > input, #contact-email > input", $c).prop("spellCheck", 'false')
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
				var which_item = $(this).parent().prop('id').substr(8), content = $.trim($(this).val());

				$(this).removeClass("jqx-input jqx-input-"+cargohub.jqxtheme);
				if(content.length == 0)
					$(this).addClass("gray").val("").data('empty', true);
				else {
					$(this).data('empty', false);
					// Not empty - check if the name is too short or the e-mail address doesn't look like an e-mail address.
					switch(which_item) {
						case 'name':
							$(this).val(check_Name_Field($(this), content));
							break;
						case 'email':
							$(this).val(check_Email_Field($(this), content));
							break;
					}
				}

				changed = true;
				valid = check_Validity();

				if(self.events.changed)
					self.events.changed(valid);
			})
			/**
			 * Event: These two ( name and E-mail ) are single lines. Make sure the "Enter" key actually blurs the input, instead of typing a newline into the content
			 */
			.on("keypress", function(event) {
				if(event.keyCode == 13 || event.key == "Enter") {
					$(this).blur();
					event.preventDefault();
				}
			});

			// Is there a default Name or E-mail specified ? Fill in the fields
			if(defaults) {
				if(defaults.name)
					$("#contact-name > input", $c).val(defaults.name).data('empty', false).removeClass("gray field-error").jqxTooltip('destroy');
				if(defaults.email) {
					$("#contact-email > input", $c).val(defaults.email).data('empty', false).removeClass("gray field-error").jqxTooltip('destroy');
				}
				// TODO: Defaults for the other fields in field structure (below, using spawn_Field)
			}

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
					$(this).find("td > input").prop("spellCheck", 'false')
					.on("paste", paste_filter);

					// If this field "row" is present among the mandatory fields, mark it with a yellow(ish) background
					if(params.mandatory_fields && params.mandatory_fields.indexOf(field)>=0) {
						$(this)
							.data("mandatory", true)
							.css("background-color", mandatory_color);
						$(this).find("td > input").prop("placeholder", "Mandatory field, please enter a value here !");
					}


					return;
				}
			});

			// Scan the fields config for "autogen" flag in "Meta" and auto-generate those groups and fields if they don't already exist
			for(group in self.fields_structure) {
				group_cfg = self.fields_structure[group];
				if(group_cfg.meta.autogen)
					for(field in group_cfg.fields) {
						if(!active_fields[field])
							spawn_Field(group, field, null, true);
					}
			}

			// Finally, setup the events on all rows, current and future ones, using delegate event format
			$("table.contact-table", $c)
			/**
			 * Event: An xdata field input is focused.
			 */
			.on("focus", "td > input", function() {
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
			.on("blur", "td > input", function() {
				var content = $(this).val();
				$(this).removeClass("jqx-input jqx-input-"+cargohub.jqxtheme);
				if(content.length == 0)
					$(this).data('empty', true).addClass("gray").val("");
				else
					// No special checking on the fields yet. They might be empty or not, empty ones are not saved in the database anyway.
					$(this).data('empty', false).val($.trim(content).replace(/(<br>\s*)+$/,''));

				changed = true;
				valid = check_Validity();

				if(self.events.changed)
					self.events.changed(valid);
			})
			/**
			 * When pressing Enter key, blur the field and move to the next one
			 */
			.on("keypress", "td > input", function(event) {
				if(event.keyCode == 13 || event.key == "Enter") {
					$(this).blur();
					event.preventDefault();

					$(this).parent().parent().next().find('input').trigger('focus');
				}
			})
			/**
			 * Event: Mousing over a xdata field label should show a delete button (-), which removes a field from the table
			 */
			.on("mouseenter", "th", function() {
				if(!$(this).parent().prop('id'))
					return; // Nothing to do, above a group or header

				// If the field is mandatory, do not show the button
				if(!$(this).parent().data('mandatory'))
					$(this).find('button').fadeIn('fast');
			})
			/**
			 * Event: Moving out of the field's label hides the delete button
			 */
			.on("mouseleave", "th", function() {
				if(!$(this).parent().prop('id'))
					return; // Nothing to do, above a group or header

				// Will do nothing for mandatory fields, as the button wasn't shown anyway
				$(this).find('button').hide();
			})
			/**
			 * Event: Clicking on row button should delete the row
			 */
			.on("click", "th > button", function() {
				var $tr = $(this).parent().parent();
				if(!$tr.find('td > input').data('empty') && $tr.find('td > input').val().length > 0)
					if(!confirm("Remove non-empty field:\n\n"+$tr.find('label').text()+" ?"))
						return;

				delete active_fields[$tr.prop('id').substr(14)];
				$tr.remove();
			})
			/**
			 * Event: Clicking on a label focuses the corresponding field
			 */
			.on("click", "th > label", function() {
				var $tr = $(this).parent().parent();
				$tr.find("td > input").focus();
			})
			;

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
					for(group in self.fields_structure) {
						group_cfg = self.fields_structure[group];
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
					$(document).on("click", ".jqx-menu-item", function(event)
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
							$("#contact-field-"+data.field, $c).find('input').trigger('focus');
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
		 * @param bool read_only Indicates if this contact is in view mode only (has no inputs, the values are just spans)
		 * @returns Contact object
		 */
		read_vCard: function(read_only)
		{
			var contact = {}, $xdata_input = null;

			// Read the content of "name" and "email" fields
			if(read_only) {
				contact.name = $.trim($("#contact-name > span", $c).text());
				contact.email = $.trim($("#contact-email > span", $c).text());
			} else {
				contact.name = $.trim($("#contact-name > input", $c).val());
				contact.email = $.trim($("#contact-email > input", $c).val());
			}
			contact.xdata = {};

			// Read all the xdata fields
			$("table.contact-table tr", $c).each(function() {
				row_id = $(this).prop('id');
				if(!row_id)
					return; // Not an XD group or field, probably the header row (with picture, name, email), the button row or a separator

				// TODO: This probably doesn't work anymore, recheck
				if(row_id.substr(8,5) == 'field') {
					field = row_id.substr(14);
					if(read_only) {
						contact.xdata[field] = $.trim($(this).find('td').text());
					} else {
						$xdata_input = $(this).find('input');
						if(!$xdata_input.data('empty') == true) {
							contact.xdata[field] = $.trim($xdata_input.val() || "");
						}
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

	// Load CSS on first use, because this module needs styles
	donrey.load_CSS(donrey.CFG.paths.components_css + "contact_editor.css?v=2.7.3");
});