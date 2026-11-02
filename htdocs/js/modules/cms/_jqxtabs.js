/**
 * Plugin "jqxTabs" - Transforms an arbitrary area of a CMS page into jqxTabs
 * -- The dom format must be correct or this plugin will fail/crash
 */
cargohub.cms_jqxtabs = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null
	;
	this._public({
		/**
		 * Section initialization
		 */
		init: function()
		{
			$('#'+self.config.domid).jqxTabs({
				theme: cargohub.jqxtheme,
				width: self.config.width,
				selectionTracker: true,
				keyboardNavigation: false
			});

		}
	});
});