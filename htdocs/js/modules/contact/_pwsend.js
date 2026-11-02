/**
 * Module "Contact Account Password" - Handles the password recovery form by doing an AJAX request that sends the contact password via e-mail
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoITS
 * @subpackage JavaScript Modules
 * @version 2.4.6
 */
cargohub.contact_pwsend = donrey.BASE._extend(function() {
	// private
	var self = this
		,
	/**
	 * Password reset form
	 */
	init_PWResetForm = function() {
		// style the other buttons on the form
		$("#pwsend input[type='submit']").jqxButton({ theme: cargohub.jqxtheme, width: 150 });

		// ajaxForm plugin
		$('#pwsend form').ajaxForm({
			dataType: 'json',
			beforeSubmit: function(formData, jqForm, options) {
				// check if the fields are not empty
				for(var c=0; c< formData.length; c++) {
					if(!formData[c].value)
						return cargohub.alert("Please enter an Email Address !");
					else
						if(!validate_email($.trim(formData[c].value)))
							return cargohub.alert("This is not a valid E-mail Address, please try again.");
				}

				return true;
			},
			success: function(x) {
				// when completed successfully, redirect to login page
				document.location.href = "/contact/access?action=login";
			},
			error: cargohub.its.http_fail
		});
	};

	// Public methods
	this._public({
		/**
		 * Module Initialization
		 */
		init: function() {
			self.name = "Contact Password Send";
			init_PWResetForm();
		}
	});
});