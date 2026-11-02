/**
 * A special control that uses a combobox to locate user's available contacts
 * - It can be activated on a container which reacts to being clicked on
 * - The combobox is not permanent, instead it displays the search result in different ways ( a contact name, e-mail address if only address is typed, e-mail format with <address> or a red strike-through for invalid contact )
 * - Supports multiple parameters to restrict or expand the search scope
 * - Multiple search boxes should be usable simultaneously into different containers
 * - Uses /global/contact/action_Search_Contacts as data source
 *
 * Requires jqxCombobox (and it's prequisites), jqxTooltip
 */
cargohub.contact_search = donrey.BASE._extend(function() {
	// private
	var self = this,
		q = null, // Search query, is updated while typing; Is cleared when an exact match has been selected; Same Q is used in all instances and is only used as a typed string transmission between search() and formatData()
		instances = {},	// Several CSS can be used at once, each one is an independent object referred to with a key

	/**
	 * Destroys the contact search box and puts back the simple text span with the contact information
	 * -- Also does some checks, if the contact doesn't exist then the input is verified as follows:
	 *    1. If an e-mail address, it's left as it is ( a contact will be created for that e-mail address )
	 *       (However, if that e-mail is among the contacts, the combobox will usually locate that contact)
	 *    2. If not an e-mail address, the span is added back with a special style, and tooltip that this is not a valid contact
	 */
	close_Contact_Search = function(key)
	{
		var i = instances[key];

		if(!i.$contact_search || !i.is_open)
			return; // Nothing to do, the combobox doesn't exist

		i.$contact_search.jqxComboBox('destroy');
		i.$contact_search = null;
		// Assambled e-mail information, either when a contact is found or when a valid e-mail /name is type in the combobox
		i.emaildata = null;
		i.is_open = false;
		q = null;

		// A contact was successfully located from the results, display it
		if(i.saved_contact_record) {
			$(i.selector).append($("<span class='contact-item' data-con_id='"+i.saved_contact_record.id+"'>"+ (i.saved_contact_record.name + " <"+i.saved_contact_record.email+">").escapeHtmlEntities()+ "</span>").addClass(i.params.placeholder_addclass));

			i.emaildata = {
				id: i.saved_contact_record.id,
				name: i.saved_contact_record.name,
				email: i.saved_contact_record.email
			};

			if(i.params.on_close)
				i.params.on_close(i.key, i.emaildata);
			return true;
		}

		// A contact was preselected (but there is no contact record)
		if(i.saved_contact_id) {
			$(i.selector).append($("<span class='contact-item' data-con_id='"+i.saved_contact_id+"'>"+ i.saved_combobox_value.escapeHtmlEntities() +"</span>").addClass(i.params.placeholder_addclass));

			i.emaildata = {
				id: i.saved_contact_id,
				name: i.saved_combobox_value
			};

			if(i.params.on_close)
				i.params.on_close(i.key, i.emaildata);
			return true;
		}

		// Contact not yet located, but there is a value typed in the combobox, see what is it about
		if(i.saved_combobox_value) {
			// 1. Attempt to extract an e-mail address from the string if it's in the format: Name <email@address>
			var parsed_emaildata = /(.*) <(.*@.*)>/.exec(i.saved_combobox_value);

			if(parsed_emaildata && parsed_emaildata.length == 3) {
				// Found embedded e-mail, test if it's an actual e-mail
				if(validate_email(parsed_emaildata[2])) {
					i.emaildata = {
						name: parsed_emaildata[1],
						email: parsed_emaildata[2]
					};
				}
			} else
				if(validate_email(i.saved_combobox_value)) {
					i.emaildata = {
						name: i.saved_combobox_value.match(/^([^@]*)@/)[1].replace(/\./g, ' ').replace(/(?:^|\s)\S/g, function(f) { return f.toUpperCase(); }), // Create Name portion out of first part of the e-mail address
						email: i.saved_combobox_value
					};
				}

			// If the typed text is either the valid full format or just a valid e-mail address, show a tooltip about new contact being created after sending
			if(i.emaildata) {
				$(i.selector).append($("<span>"+ i.saved_combobox_value.escapeHtmlEntities() +"</span>").addClass(i.params.placeholder_addclass));

				$("> span", i.selector).jqxTooltip({
					theme: cargohub.jqxtheme,
					position: "mouse",
					content: "Unrecognized e-mail address, the system might create a contact automatically if this address is used"
				});

				if(i.params.on_close)
					i.params.on_close(i.key, i.emaildata);
				return true;
			}

			// Nothing found, invalid string - Make the line red, with strikethrough
			$(i.selector).append($("<span class='contact-error'>"+ i.saved_combobox_value.escapeHtmlEntities()+ "</span>").addClass(i.params.placeholder_addclass));

			$("> span.contact-error", i.selector).jqxTooltip({
				theme: cargohub.jqxtheme,
				position: "mouse",
				content: "Invalid contact. Please type a correct e-mail address or search for one."
			});

			// Disable sending, contact not recognized
			if(i.params.on_close)
				i.params.on_close(i.key, false);
			return false;
		}

		// All empty, go back to initial state ?
		$(i.selector).append($("<span class='gray'>"+i.params.placeholder+"</span>").addClass(i.params.placeholder_addclass));

		if(i.params.on_close)
			i.params.on_close(i.key, false);
	},

	/**
	 * Initialzies the combobox on a CSS instance (usually as the result of being focused and not already being active)
	 */
	initialize_Combobox = function(key, autoopen)
	{
		var i = instances[key];

		i.contacts_Source = new $.jqx.dataAdapter({
			url: "/global/contact?action=search_contacts",
			datatype: "json",
			data: {},
			datafields: [
				{'name': "id"},
				{'name': "email"},
				{'name': "name"},
				{'name': "type"},
				{"name": "xdata"}
			],
			id: "id",
			async: true
		}, {
			/**
			 * Event: When user is typing in the combobox, the "search" function is called, which in turn calls this.dataBind / Or at first initialization -> When that happens, the dataAdapter calls formatData
			 * @param data
			 */
			formatData: function(data)
			{
				if(i.params.css) // Contact Search Scope
					data.css = i.params.css;
				// If something is typed in, attempt to search by that value
				if(q) {
					data.q = q;
					delete data.id;
					delete data.initialize;
					return data;
				} else {
					if(i.params.initialize) // How the search is initialized when empty
						data.initialize = i.params.initialize;
				}
				// If nothing is typed in the box but we have a contact match, search by exact value instead
				if(i.saved_contact_id) {
					data.id = i.saved_contact_id;
					delete data.q;
					delete data.css;
					delete data.initialize;
				}
				return data;
			},
			/**
			 * Event: the DataAdapter has finished loading, allow the combobox to be focused (and focus it at first successful load)
			 */
			loadComplete: function()
			{
				if(i.status != 'complete') {
					i.status = 'complete'; // The data is loaded !
					if(i.$contact_search)
						if(autoopen)
							i.$contact_search.jqxComboBox('open');
						else
							i.$contact_search.jqxComboBox('focus');
				}
			}
		});

		// If a contact match already exists, the control is preinitialized to search for that value at initial load.
		if(i.saved_contact_id) {
			i.contacts_Source._source.data.id = i.saved_contact_id;
		} else
		// Preinitialize datasource if saved_combobox_value is set (for example as the result of a previous search)
		if(i.saved_combobox_value) {
			i.contacts_Source._source.data.q = i.saved_combobox_value;
			if(i.params.css)
				i.contacts_Source._source.data.css = i.params.css;
		}

		// Clear the "text" version and append the Combobox
		$("> span", i.selector).remove();
		$(i.selector).append("<div id='cs_"+key+"'></div>");
		i.$contact_search = $('div#cs_'+key, i.selector);

		// Start loading contacts, when that is done build the control
		// The contact search combobox (uses the Contacts/Addressbook database)
		i.$contact_search.jqxComboBox({
			theme: cargohub.jqxtheme,
			source: i.contacts_Source,
			remoteAutoComplete: true, remoteAutoCompleteDelay: 500, minLength: 0,
			search: function(search)	// While typing...
			{
				i.saved_contact_id = null;
				if(search.length>=3) { // Search only when at least 3 characters are typed
					q = search;
					i.contacts_Source.dataBind();
				} else
					q = null;
			},
			displayMember: "name", valueMember: "id",
			placeHolder: "(Select the contact or type your own E-mail)",
			width: i.params.width || 200, height: i.params.height || 22, dropDownWidth: i.params.dropdown_width || i.params.width || 200,
			itemHeight: 102,
			autoDropDownHeight: true,
			/**
			 * Element renderer for the combobox, displays relevant contact information
			 */
			renderer: function(index, label)
			{
				var rec = i.contacts_Source.records[index];
				return i.params.result_template.present(rec);
			},
			/**
			 * Renders the selected item in the standard "email name <and@address>" format
			 */
			renderSelectedItem: function(index, item)
			{
				var rec = i.contacts_Source.records[index];
				if(rec.uid > 0)
					return item.label + " <" + rec.email + ">";
				else
					return "";
			}
		})
		/**
		 * Event: Contact selector is changed
		 * -- However, in the background the ID of that contact is kept, and clicking it again will show the proper value in the control.
		 */
		.on('change', function(e) {
			var selectedIndex = $(this).jqxComboBox('getSelectedIndex');

			if(selectedIndex>= 0) {
				// Check if this is the special "No results" contact (id = 0)
				if(i.contacts_Source.records[selectedIndex].id == 0) {
					$(this).jqxComboBox('clearSelection');
					i.saved_contact_record = null;
					i.saved_contact_id = null;
					i.saved_contact_index = -1;
					i.emaildata = null;
					return false;
				}

				// Selected a valid record from the list
				i.saved_contact_record = i.contacts_Source.records[selectedIndex];
				i.saved_contact_id = i.saved_contact_record.id;
				i.saved_contact_index = selectedIndex;
				i.saved_combobox_value = null;
				q = null;
			} else {
				// Combobox text value changed, but did not click or navigate to any valid item
				i.saved_combobox_value = $(this).val();
				if(i.saved_combobox_value) {
					i.saved_combobox_value = $.trim(i.saved_combobox_value);
				}
				i.saved_contact_record = null;
				i.saved_contact_id = null;
				i.saved_contact_index = -1;
				i.emaildata = null;
			}

			// Callback on_change
			if(i.params.on_change)
				i.params.on_change(key, self.get_Data(key));

			// Close the control after change
			close_Contact_Search(key);
		});

		// Version 3.3.0+ does not perform databind by default, so do it here.
		i.contacts_Source.dataBind();

		// Prepare the text content of the box depending on existing values
		if(i.saved_contact_index == -1) {
			if(!i.saved_contact_id)
				q = i.saved_combobox_value;
			i.$contact_search.val(i.saved_combobox_value);
		} else if(i.saved_contact_record) {
			i.saved_contact_id = i.saved_contact_record.id;
			q = null;
			// On existing contact record defaults the text to full format name + email
			i.$contact_search.val(i.saved_contact_record.name + " <" + i.saved_contact_record.email + ">");
		}

		i.is_open = true;
		i.status = "loading";
		return;
	},

	/**
	 * Activates focusing on a CSS instance
	 */
	activate_Focus = function(key, autoopen)
	{
		var i = instances[key], $container = $(i.selector),
			focus_event = function(e) {
				if(i.status == 'loading')
					return e.stopPropagation(); // Disable any extra focus events until the combobox has initialized and is loading data

				if(i.params.on_focus)
					i.params.on_focus(key, i.$contact_search !== null);

				if(!i.$contact_search)
					initialize_Combobox(key, autoopen);

				e.stopPropagation(); // Stop the bubble up ( as there's a close event on focus too )
			};

		if(cargohub.response.browser.msie) {
			var browser_version = parseInt(cargohub.response.browser.version);

			// Crap old IE doesn't propagate properly, so two ON events needed.
			if(browser_version < 11) {
				$container.on('focus', '>div, >span', focus_event);
			}
			$container.on('focus', focus_event);
		}
		else
			$container.on('focus', focus_event); // Every other browser but old IE

		if(autoopen)
			$container.trigger('focus');
	}
	;

	this._public({
		/**
		 * Sets up a contact search system on a container defined by "selector"
		 * - Supported parameters:
		 *   "result_template": String which will be used as the templet for visualising live results. If missing the control will display the contact name on a single line.
		 *   "on_focus": Called when the control receives focus. Useful if other things need to be processed when this control is being activated. Second parameter is true if the combobox was already open, false if it's about to be opened
		 *   "on_change": Callback that will receive the key and state as parameters, when the value of the control is changed ( either by typing/searching, selecting or closing )
		 *   "on_close": Callback that is run when the combobox is "closed". Receives key and "emaildata" as parameters - If no valid contact or e-mail address located, the second parameters will be "false"
		 *   "css": Contact search scope - Added to the search query (optional)
		 *   "placeholder": Text to show when nothing is typed/selected in the control. Defaults to "(click to search emaildata)"
		 *
		 * @param selector
		 * @param params Various parameters on how this control functions
		 */
		setup_CSS: function(key, selector, params, data_defaults)
		{
			if(instances[key])
				return false; // Error, key is already in use

			// Some defaults
			if(!params)
				params = {};
			if(!params.placeholder)
				params.placeholder = "(click to search recipient)";

			// Prepare the instance variable
			instances[key] = {
				key: key,
				selector: selector,
				params: params,
				$contact_search: null, // If non-null, the contact combo-box exists
				is_open: false,
				saved_combobox_value: null,	saved_contact_id: null, saved_contact_record: null, saved_contact_index: -1, // Exact text content of the combobox, selected contact record id, record data and index after being located and combobox closed
				q: null, emaildata: null,
				status: 'ready' // Start with the control on "Ready" state, which allows it to be focused
			};
			// Allow creating the control with either an already typed in text or to directly read a specific contact
			if(data_defaults && data_defaults.combobox_value)
				instances[key].saved_combobox_value = data_defaults.combobox_value;
			if(data_defaults && data_defaults.contact_id)
				instances[key].saved_contact_id = data_defaults.contact_id;

			activate_Focus(key, params.autoopen);
			return instances[key];
		},

		/**
		 * Sets the content of an existing CSS to being empty or to a external value (replaces the current saved_* values)
		 * -- Note, a contact record cannot be set, it must come from the search results
		 * -- Also closes the combobox if it were open, however it doesn't affect the container like a normal close ! An external code must sepparately change the container
		 *
		 * @param key
		 */
		set: function(key, data_values)
		{
			if(!instances[key])
				return false; // Error, instance not found

			var i = instances[key];

			if(i.$contact_search) {
				i.$contact_search.jqxComboBox('destroy');
				i.$contact_search = null;
			}

			i.saved_contact_record = null;
			i.saved_contact_index = -1;
			i.q = null;
			i.emaildata = null;

			if(data_values && data_values.combobox_value)
				i.saved_combobox_value = data_values.combobox_value;
			else
				i.saved_combobox_value = null;
			if(data_values && data_values.contact_id)
				i.saved_contact_id = data_values.contact_id;
			else
				i.saved_contact_id = null;

			return true;
		},

		/**
		 * Updates parameters for an instance (might change the behaviour of the control)
		 *
		 * @param key
		 * @returns {Boolean}
		 */
		update_Params: function(key, params)
		{
			if(!instances[key])
				return false; // Error, instance not found

			$.extend(true, instances[key].params, params);
			return true;
		},

		/**
		 * Closes the control while keeping the container click event enabled. This is usually used when the user clicks somewhere else beside the control itself.
		 *
		 * @param key
		 * @returns {Boolean}
		 */
		close: function(key)
		{
			if(!key || !instances[key])
				return false; // Error, instance not found

			close_Contact_Search(key);
			return true;
		},

		/**
		 * Closes and destroys the combobox if active, reverts the container to text mode and disables it's focus event
		 *
		 * @param key
		 * @returns {Boolean}
		 */
		disable: function(key)
		{
			if(!instances[key])
				return false; // Error, instance not found

			close_Contact_Search(key);

			$(instances[key].selector).off('focus');
			$(instances[key].selector).off('focus', '>div, >span');

			instances[key].disabled = true;
			return true;
		},

		/**
		 * Reenables the control on the container, which can again become a combobox with search function
		 *
		 * @param key
		 * @returns {Boolean}
		 */
		enable: function(key)
		{
			if(!instances[key])
				return false; // Error, instance not found

			activate_Focus(key);

			delete instances[key].disabled;
			return true;
		},

		/**
		 * Returns the current data obtained by the control
		 * - Returns an object which might contain the entered text, or the selected contact and it's ID
		 *
		 * @param key
		 * @returns {Object}
		 */
		get_Data: function(key)
		{
			if(!instances[key])
				return false; // Error, instance not found

			var i = instances[key];
			return {
				enabled: !i.disabled,
				combobox_value: i.saved_combobox_value,
				contact_id: i.saved_contact_id,
				contact_record: i.saved_contact_record,
				emaildata: i.emaildata
			};
		},

		/**
		 * Alias for set(), but without the data_values parameter
		 *
		 * @param key
		 */
		reset: function(key)
		{
			return self.set(key);
		},

		/**
		 * Closes the ComboBox if open and completely removes the instance
		 * -- Usually used when the HTML containing the control disappears
		 *
		 * @param key
		 * @returns {Boolean}
		 */
		destroy: function(key)
		{
			if(!instances[key])
				return false; // Error, instance not found

			// Deactivate focus, destory combobox and remove instance
			$(instances[key].selector).off('focus');
			$(instances[key].selector).off('focus', '>div, >span');
			if(instances[key].$contact_search)
				instances[key].$contact_search.jqxComboBox('destroy');
			instances[key] = null;
			delete instances[key];
			return true;
		}

	});
});