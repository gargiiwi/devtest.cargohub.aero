/**
 * Plugin "EULA Accept" - Just a checkbox that the user has to check in order to press the submit button
 */
cargohub.cms_eulaaccept = donrey.BASE._extend(function() {
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
			if(self.config.active) {

				$("#eula-box").css('height', '480px').css('overflow', 'auto');

				$("form#eula-form input[type='submit']").jqxButton({
					theme: cargohub.jqxtheme
				});

				$("form#eula-form").on('submit', function(e)
				{
					if($("form#eula-form input#eula-confirm").prop('checked')) {
						$("form#eula-form").off('submit').submit();
					} else
						cargohub.its.alert("Please confirm that you have read the User License Agreement by clicking the checkbox !");

					// direct form submit not allowed, only after checks and confirmation window completed
					e.preventDefault();
					return false;
				});
			}
		}
	});
});