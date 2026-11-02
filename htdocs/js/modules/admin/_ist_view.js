/**
 * Module "Admin IST View"
 */
cargohub.admin_ist_view = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null, // general counter used in for()

	/**
	 * Sets up the toolbar with operations related to organizations
	 */
	setup_Toolbar = function()
	{
		// Buttons to add/edit/remove an user from the department
		$('.toolbar button')
		.jqxButton({ theme: cargohub.jqxtheme })
		.on('click', function() {
			var data = $(this).data();

			switch(data.op) {
				case 'back':
					// Back to grid
					document.location = "/admin/ist";
					break;

				case 'edit':
					// Opens the editor for this issue type
					document.location = "?action=edit&id="+ self.config.id;
					break;
			}
		});
	};

	this._public({
		/**
		 * Section initialization
		 */
		init: function()
		{
			setup_Toolbar();
		}
	});
});