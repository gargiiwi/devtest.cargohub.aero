/**
 * #Hash-Based navigation Component ( Inside the same module ), supporting browser back/forward
 * - Module based on jqxTabs ( where multiple tabs contain various elements of the interface ) - Note: It only supports ONE set of tabs ( so tabs inside tabs are not supported )
 * - Module containing jqxNavigationBar ( could be inside a tab, allowing further division of the module, or independent ) - Note: It supports one navbar per tab, or one navbar in general if there are no tabs
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScriptUI Components
 * @version 2.7
 */
cargohub.components.hashnav = donrey.BASE._extend(function() {
	var self = this,
		master = null,						// Master object that initialized this component
		hash = null,						// Hash contains data split by dots in the order {tab_id}.{expander_idx} ; Works if certain navigation elements are not present, the order is preserved.
		disable_onhashchange = false,
		disable_sethash = false,
		// Tabs support
		$tabs_container = null,
		loaded_tabs = {},					// List of tabs which have already been loaded and initialized
		selected_tab_idx = 0,
		selected_tab = null,				// Currently selected tab ID (not the DOM id, that one has "tab_" added in front)
		tab_count = 0,						// Number of tabs which are available (determined at initialization)
		tabs_initialized = false,
		tab_load_action = "load_tab_html",
		tab_load_params = {},				// Extra parameters for the load_action (used to ask for specific information when loading a tab);
											// Can be changed later and applies every time that particular tab is loaded (or reloaded)
		navbars_in_tabs = false,

		/**
		 * Sets the hash depending on what is active (tab, navbar)
		 * - If only tabs are used, the hash contains the Tab ID
		 * - If only one navbar is used, the hash contains the Navbar Index (a positive number)
		 * - If tabs and navbars are used, the hash contains the Tab ID .(dot) Navbar Index
		 */
		set_Hash = function()
		{
			if(disable_onhashchange || disable_sethash)
				return; // If a change is already in progress, ignore the call

			disable_onhashchange = true;

			var new_hash = "";
			if($tabs_container)
				new_hash += selected_tab;

			if(navbars_config) {
				if(navbars_in_tabs && navbars_config[selected_tab])
					if(navbars_selected_idxs[navbars_config[selected_tab]]) // Selecting index 0 or missing index is equivalent to selecting first navbar
						new_hash += '.'+navbars_selected_idxs[navbars_config[selected_tab]];

				if(!$tabs_container)
					new_hash += navbars_selected_idxs[navbars_config];
			}
			location.hash = new_hash;

			// Give a little time for hash to be updated then reenable the event
			setTimeout(function() {
				disable_onhashchange = false;
			}, 100);
		},

		/**
		 * Checks if the first part of the hash matches an existing tab
		 *
		 * @param tabid
		 * @return The tab's index
		 */
		tabs_CheckID = function(tabid)
		{
			var c = 0, found_tab_idx = false, $tabs_array = tabs_initialized? $('div.jqx-tabs-content > div', $tabs_container): $('> div', $tabs_container);
			$tabs_array.each(function() {
				if(tabid == this.id.substr(4)) // Expects element IDs in the shape "tab_{id}"
					found_tab_idx = c;
				c++;
			});
			return found_tab_idx;
		},

		/**
		 * Setup Tabs Navigation
		 * -- Requires jqxTabs
		 */
		setup_Tabs_Navigation = function()
		{
			$tabs_container.jqxTabs({
				theme: cargohub.jqxtheme,
				width: cargohub.fullwidth,
				position: 'top',
				selectionTracker: true, selectedItem: selected_tab_idx,
				scrollPosition: 'both', scrollStep: 100, keyboardNavigation: false
			})
			/**
			 * Event: A tab is selected. Load it's content and initialize, unless it is already loaded
			 */
			.on('selected', function(event, self_trigger) {
				cargohub.application.reset_Session_Timer();

				if(!self_trigger)
					selected_tab = $tabs_container.jqxTabs('getContentAt', event.args.item).prop('id').substr(4);
				set_Hash();

				// Tab loading and initialization
				if(!loaded_tabs[selected_tab]) {
					$.get("?action="+ tab_load_action+ "&tab="+ selected_tab, tab_load_params[selected_tab], function(tab_contents, textStatus, jqXHR)
					{
						// Load tab contents and any extra data transmitted through JSON Headers; if it exists store it in the calling modules' config object
						$('#tab_' + selected_tab).html(tab_contents);
						donrey.read_JSON_Headers(jqXHR, master.config);

						// Callback the "tab_Init" method of the calling module (if it exists), to perform tab initialization after loading
						if(self.events.tab_init)
							self.events.tab_init(selected_tab);

						// Initialize any navbars inside the loaded tab (checking hash[1] as well)
						if(navbars_in_tabs)
							setup_Navbars_Navigation(selected_tab);

						// and finally, set the tab as loaded and initialized
						loaded_tabs[selected_tab] = true;
					});
				}

				// Run tab selection callback if it exists
				if(self.events.tab_selected)
					self.events.tab_selected(selected_tab);

				disable_sethash = false;
			});

			tabs_initialized = true;
		},

		// Navigation Bar support
		navbars_config = null,
		navbars_sizes = {},				// Number of elements detected for each of the navbars (count of children / 2), to check if an out-of-bounds condition is encountered
		navbars_selected_idxs = {},		// Current selected item for each navbar (or the unique navbar)

		/**
		 * Setup Navbars Navigation - Automatically initializes navigation bars in the module, or inside a tab after being loaded.
		 * -- Requires jqxNavigationBar
		 */
		setup_Navbars_Navigation = function(tab_id)
		{
			var $navbar = null, hash_expanded = (hash && hash[1])? [parseInt(hash[1])]: [];

			// Should there be a navbar inside this tab ?
			if(navbars_in_tabs && navbars_config[tab_id])
				$navbar = $("#"+navbars_config[tab_id], '#tab_'+ tab_id);
			else if(!navbars_in_tabs)
				$navbar = $("#"+navbars_config); // Nope, not using tabs, the navbars_config is just the ID of the only navbar the module has
			else
				return; // Using a navbar in a tab, but this tab doesn't have a navbar.

			navbars_sizes[$navbar.prop('id')] = parseInt($('> *', $navbar).length / 2);
			$navbar.jqxNavigationBar({
				theme: cargohub.jqxtheme,
				arrowPosition: "left",
				expandedIndexes: hash_expanded
			})
			/**
			 * Event: A navbar is expanded - Save the index in the hash (combining it with tabs if tabs are used);
			 */
			.on('expandedItem', function(event) {
				cargohub.application.reset_Session_Timer();

				if(navbars_in_tabs && navbars_config[selected_tab])
					navbars_selected_idxs[navbars_config[selected_tab]] = event.args.item;
				else if(!navbars_in_tabs)
					navbars_selected_idxs[navbars_config] = event.args.item;

				set_Hash();
				disable_sethash = false;
			});
		}
	;

	// exported methods
	this._public({
		name: "Hash-tab Navigation",

		// Component events
		events: {},

		/**
		 * Sets up the "Hash-based" navigation used in certain modules
		 *
		 * @param config Object containing configuration parameters
		 *  "use_tabs"				= "true" Initialize jqxTabs on a #tabs-container div, and setup hash-based naviation
		 *  "tab0"					= Name of the first tab in the list, which is the one being selected if no hash is present.
		 *  "$tabs_container"		= Optional: jQuery object used as the tabs widget selector (Defaults to selecting #tabs-container)
		 *  "tab_load_action"		= Specify an action to be used by the "Load Tab" event (when a tab which did not exist before is loaded) - Defaults to "tab_load_html"
		 *  "tab_load_params"		= Extra parameters added to the (array matching the tab indexes)
		 *  "disable_hashchange"	= "true" Do not allow the browser navigation to change the active tab or navigation bar
		 *
		 *  "use_navbars"			= "true" Detect in the hash the position for a navbar and maintain that position throughout navigation. It supports multiple navbars, even multiple for each tab
		 *  "navbars_config"		= An array containing objects defining navbars which will be detected, in the shape:
		 *  - "id" 			= The DOM id of the navbar (which will be matched against the Hash, as a numeric index)
		 *  - "tab" 		= Optional: If using tabs, in which tab is this navbar (The bar is checked and initialized only after the tab has loaded), and obviously only for this tab
		 *  - "noselect"	= Optional: This navbar cannot be selected from the Hash, and clicking it won't change the hash.
		 * @param _master Calling object - The request would look like: cargohub.components.contacts_list.setup(config, self);
		 */
		setup: function(config, _master)
		{
			if(_master)
				master = _master;
			var hashcheck = null;

			if(location.hash)
				hash = location.hash.substr(1).split('.');

			if(self.events.new_hash)
				self.events.new_hash(hash);

			// Set up Tabs component
			if(config.use_tabs) {
				if(config.tab0)
					selected_tab = config.tab0;

				if(config.$tabs_container)
					$tabs_container = config.$tabs_container;
				else
					$tabs_container = $("#tabs-container"); // Default ID

				if(!$tabs_container)
					return false; // Error, a container was not found, can't setup tab-based hashnav

				tab_count = $('> div', $tabs_container).length;
				if(tab_count < 1)
					return false; // Error, at least one tab should exist.

				if(hash) {
					// Begin by selecting the tab specified in the URL (if one exists)
					hashcheck = tabs_CheckID(hash[0]);
					if(hashcheck) {
						selected_tab_idx = hashcheck;
						selected_tab = hash[0];
					}

					// Make sure the hash doesn't go to one of the initially disabled tabs; Automatically redirect to tab0 if so
					if(config.tabs_disabled) {
						for(var c=0; c< config.tabs_disabled.length; c++)
							if(selected_tab_idx == config.tabs_disabled[c]) {
								selected_tab = config.tab0;
								selected_tab_idx = 0;
							}
					}

					// Trigger on selecting the tab
					if(config.use_triggers) {
						setTimeout(function() {
							if(hash[1] && self.events.triggers && self.events.triggers[hash[0]])
								self.events.triggers[hash[0]](hash[1], self);
						}, 250);
					}
				}

				if(config.tab_load_action)
					tab_load_action = config.tab_load_action;

				if(config.tab_load_params)
					tab_load_params = config.tab_load_params;

				// Are some tabs initially disabled ?
				if(config.tabs_disabled) {
					$tabs_container.on('created', function() {
						for(var c=0; c< config.tabs_disabled.length; c++)
							$(this).jqxTabs('disableAt', config.tabs_disabled[c]);
					});
				}

				setup_Tabs_Navigation();

				// Add tooltips to tab headers (if they exist)
				$($tabs_container).find("li.jqx-tabs-title").each(cargohub.components.ui.add_Tooltip);

				// initialize the startup tab by triggering the "selected" event
				$tabs_container.trigger('selected', true);
			}

			// Set up Navbar component
			if(config.use_navbars) {
				navbars_config = config.navbars_config;

				if(config.use_tabs)
					navbars_in_tabs = true;
				else
					setup_Navbars_Navigation(); // Not using tabs, initialize navbars directly.
			}

			// Allow disabling the hashchange behaviour (browser back/forward won't have any effect)
			if(config.disable_hashcange)
				return true;

			// hash-based navigation (follows browser's back/forward buttons and uri changes inside the module)
			window.onhashchange = function(event)
			{
				if(disable_onhashchange)
					// hash changes as well when the user is selecting an element, via the "selected" events. This is necessary to avoid an endless trigger loop.
					return;

				hash = location.hash.substr(1).split('.');

				// When using the "new_hash" callback, send the detected (and splitted hash to the callback)
				if(self.events.new_hash)
					self.events.new_hash(hash);

				disable_sethash = true;

				if(config.use_tabs) {
					// Begin by selecting the tab specified in the URL (if one exists)
					hashcheck = tabs_CheckID(hash[0]);
					if(hashcheck !== false) {
						selected_tab_idx = hashcheck;
						$tabs_container.jqxTabs('select', selected_tab_idx);
					}
				}

				// Some tabs may have triggers reacting to a certain value after the tab id; Trigger an event after the tab is initialized
				if(config.use_triggers) {
					setTimeout(function() {
						if(config.use_tabs && hash[1] && self.events.triggers && self.events.triggers[hash[0]])
							self.events.triggers[hash[0]](hash[1], self);
					}, 250);
				}

				// Some tabs have navigation bars inside them
				if(config.use_navbars) {
					// With a small delay to allow the tab to be selected (if it is the case, check if there is a navbar in the selected tab and expand the navbar at the specified location
					setTimeout(function() {
						if(config.use_tabs && hash[1] && navbars_config[selected_tab] && hash[1]>=0 && hash[1]< navbars_sizes[navbars_config[selected_tab]])
							$("#"+navbars_config[selected_tab], '#tab_'+selected_tab).jqxNavigationBar('expandAt', hash[1]);
						else if(navbars_config && hash[0] && hash[0]>=0 && hash[0]< navbars_sizes[navbars_config])
							$("#"+navbars_config).jqxNavigationBar('expandAt', hash[0]);
						// No navbar, or some other inconsistency
					}, 250);
				}
			};
			return true;
		},

		/**
		 * Selects a tab based on it's index
		 */
		select: function(idx) {
			if(idx<0 || idx>=tab_count)
				return false;
			$tabs_container.jqxTabs('select', idx);
			return true;
		},

		/**
		 * Updates the header of a certain tab without affecting the tab itself
		 */
		update_Tab_Header: function(idx, title, icon)
		{
			if(idx<0 || idx>=tab_count)
				return false;
			$('li.jqx-tabs-title:eq('+idx+') div.jqx-tabs-titleContentWrapper', $tabs_container).html('<img src="'+icon+'" alt="icn" /> '+title);
			return true;
		},

		/**
		 * Enables a tab at a certain location (and optionally updates header)
		 */
		enable_At: function(idx, title, icon) {
			if(idx<0 || idx>=tab_count)
				return false;
			if(title) // sets the header
				self.update_Tab_Header(idx, title, icon);
			$tabs_container.jqxTabs('enableAt', idx);
			return true;
		},

		/**
		 * Disables a tab at a certain location (and optionally updates header)
		 */
		disable_At: function(idx, title, icon) {
			if(idx<0 || idx>=tab_count)
				return false;
			if(title) // sets the header
				self.update_Tab_Header(idx, title, icon);
			$tabs_container.jqxTabs('disableAt', idx);
			return true;
		},

		/**
		 * Sets load parameters for a tab; Usually used before the tab is first selected
		 */
		set_load_params: function(tab_id, params) {
			if(loaded_tabs[tab_id])
				return false; // Error, cannot set loading params if the tab was already loaded
			tab_load_params[tab_id] = params;
			return true;
		},

		/**
		 * Clears and optionally disables an existing tab, making it ready to be loaded with new data
		 *
		 * @param idx Tab index (0 is first)
		 * @param tab_id Tab's Hash ID
		 * @param title Optional: Replace the tab header with a new title + icon
		 * @param icon If title is present, this is the url of the icon which will replace existing icon
		 * @param disable If true, the tab will be disabled after unloading.
		 */
		unload_Tab: function(idx, tab_id, title, icon, disable)
		{
			if(idx<0 || idx>=tab_count)
				return false;
			if(disable)
				$tabs_container.jqxTabs('disableAt', idx);
			if(title) // sets the header
				self.update_Tab_Header(idx, title, icon);
			$tabs_container.jqxTabs('setContentAt', idx, '<div style="text-align: center; padding: 30px;"><img src="/img/extra/loader3.gif" alt="Loading..." /></div>'); // clears content
			delete loaded_tabs[tab_id];
			return true;
		},

		/**
		 * Reloads the content for the selected tab, usually after an action that changed the underlying data
		 * @param idx
		 */
		reload_Tab: function(idx, tab_id, title, icon)
		{
			if(idx<0 || idx>=tab_count)
				return false;
			if(title) // sets the header
				self.update_Tab_Header(idx, title, icon);
			delete loaded_tabs[tab_id];
			$tabs_container.trigger('selected', true);
			return true;
		}
	});
});