/**
 * Module "Administration Center" - Provides function to core administrative functions
 * Depends on the widgets:
 * - cargohub.components.hashnav (Tab based navigation)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2020.05.18
 */
cargohub.admin_center = cargohub.baseclass.docklets._extend(function() {
	// private
	var self = this,
		c = null,

		/**
		 * Initializes an Admin Center tab by loading its corresponding code
		 */
		event_Admin_Tab = function(tab_id)
		{
			// Load the module handing data for this tab
			donrey.load_Modules("admin/_"+tab_id, function() {
				cargohub["admin_"+tab_id].config = self.config["tab_"+tab_id]; delete self.config["tab_"+tab_id];
				cargohub["admin_"+tab_id].init();
			}, "?v="+cargohub.version);
		}
	;

	this._public({
		// section initialization
		init: function()
		{
			// setup the tab-navigation interface used in this module
			// Prepare component event callbacks
			cargohub.components.hashnav.events = {
				tab_init: event_Admin_Tab
			};

			// Initialize the Hash-based Tabs navigation Component
			cargohub.components.hashnav.setup({
				use_tabs: true,
				tab0: 'docklets',
				tab_load_params: {
					docklets: {
					},
					online: {
					}
				}
			}, self);
		}
	});
});