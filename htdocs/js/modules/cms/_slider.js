/**
 * Plugin "responsive-slider" - Uses an existing div as a "Responsive Slider"
 * -- The dom format must be correct or this plugin will fail/crash
 */
cargohub.cms_slider = donrey.BASE._extend(function() {
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
			$('#'+self.config.domid).responsiveSlider({
				"autoplay": true
			}).run();
		}
	});
});