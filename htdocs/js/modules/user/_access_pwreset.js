/**
 * Module "Password Reset" - Handles the password reset form by doing an AJAX request that sends a password reset code via e-mail
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package Cargohub Connect
 * @subpackage JavaScript Modules
 * @version 2.10
 */
cargohub.user_access_pwreset = donrey.BASE._extend(function() {
	// private
	var self = this
		,
	/**
	 * Password reset form
	 */
	init_PWResetForm = function()
	{
		// JQXValidator on E-mail field
		$('#pwreset form').jqxValidator({
			rules: [
				{input: "#emailBox", message: "Your E-mail address is requried", action: 'keyup, blur', rule: 'required'},
				{input: "#emailBox", message: "Please enter an E-mail address without any spaces", action: 'blur', rule: 'email'},
			]
		});

		// Submit button
		$("#pwreset input[type='submit']").jqxButton({ theme: cargohub.jqxtheme, width: 150 });

		// ajaxForm plugin
		$('#pwreset form').ajaxForm({
			dataType: 'json',
			beforeSubmit: function(formData, jqForm, options) {
				// check if the fields are not empty
				for(var c=0; c< formData.length; c++) {

					switch(formData[c].name) {
					case 'email':
						if(!formData[c].value)
							return cargohub.alert("Please enter an Email Address !");
						else {
							formData[c].value = $.trim(formData[c].value);
							if(!validate_email(formData[c].value))
								return cargohub.alert("This is not a valid E-mail Address, please try again.");
						}
						break;
					}
				}
				return true;
			},
			success: function(x) {
				// when completed successfully, refresh the page
				if(x.success) {
					document.location.href = x.redirect;
				} else {
					if(x.redirect)
						document.location.href = x.redirect;
					else
						cargohub.its.http_fail(null, "Access.Pwreset::Reset_Password");
				}
			},
			error: function(xhr) {
				cargohub.its.http_fail(xhr, "Access.Pwreset::Reset_Password");
			}
		});
	};

	// Public methods
	this._public({
		/**
		 * Module Initialization
		 */
		init: function() {
			init_PWResetForm();
		}
	});
});