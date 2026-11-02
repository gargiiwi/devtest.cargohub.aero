/**
 * Module "Issue Type Select"
 * (Small module used to select the submit issue type in the public area)
 */
cargohub.ist_select = donrey.BASE._extend(function() {
	// private
	var self = this,
		current_ist_key = null,
		hover_disable = false, hover_disable_to = null,

	/**
	 * Change the currently visible Issue Type description and submit link.
	 * This is called when URI Hash has changed (either by clicking on of the left side links or direct typing in the bar)
	 */
	change_IST_Description = function(key)
	{
		var $ist_con = $("div.issuetype#ist_"+key), ist_key;

		// Test if key actually exists (there is a div in the left side having ist_{key}
		if($ist_con.length == 0)
			return; // Nothing to change, bullshit typed in the hash or wrong link followed

		ist_key = $ist_con.attr('id').substr(4);

		if(ist_key != current_ist_key) {
//			dd("SWITCHING TO " + ist_key);

			// Unmark current row
			if(current_ist_key)
				$("div.issuetype#ist_"+current_ist_key).removeAttr("style");
			// Mark the selected row
			$ist_con.attr("style", "background-color: #E8E8E8;");
			// Set the description
			$("#ist_description").fadeOut(function() {
				$("#ist_description").html($ist_con.find('div').html()).fadeIn('fast');
			});

			current_ist_key = ist_key;

		}
	},

	/**
	 * Sets up display of issue type description on hovering above one of the issue types
	 */
	setup_IST_Selector = function()
	{
		$("div.issuetype > a.selector")
		/**
		 * Event: Mousing over an issue type will change to that issue type after a short delay
		 */
		.on('mouseover', function(event) {
			if(hover_disable)
				return;

			var hover_href = $(this).attr('href');

			// when hovered delayed change of active issuetype
			donrey.final_Delay('ist_select', function() {
				document.location.hash = hover_href;
			}, 500);
		})
		/**
		 * Event: Click on the issue type links
		 */
		.on('click', function(event) {
			// Disable the hover function
			hover_disable = true;
			// Reenable hovering after 5 seconds from last click
			donrey.final_Delay('hover_disable', function() {
				hover_disable = false;
			}, 5000);
		});


		// hash-based navigation between issue types
		window.onhashchange = function(event) {
//			if(disable_onhashchange)
//				return;
			change_IST_Description(location.hash.substr(1));
		};
	}
	;

	this._public({
		// section initialization
		init: function()
		{
			setup_IST_Selector();

			// Check if there is a hash already in place, initiate description change
			if(location.hash)
				change_IST_Description(location.hash.substr(1));
		}
	});
});