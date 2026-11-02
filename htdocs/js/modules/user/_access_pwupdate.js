/**
 * Module "Password Update" - Following a successful link from E-mail, this form allows the user to enter a new password for his account
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package Cargohub Connect
 * @subpackage JavaScript Modules
 * @version 2.10
 */
cargohub.user_access_pwupdate = donrey.BASE._extend(function() {
	// private
	var self = this,

	/**
	 * Password reset form
	 */
	init_PWUpdateForm = function()
	{
		var pwstr = 0;

		// Disable copy/paste in the password fields (must type it!)
		$("#pass1Box, #pass2Box").on('paste', function(e) {
			e.preventDefault();
		});

		// Password strength and validation
		$('#pwupdate form').jqxValidator({
			rules: [
				{input: "#pass2Box", message: "Enter the same password again", action: 'blur', rule: function($input) {
					if($input.val().length == 0)
						return true;
					else
						return $input.val() == $('#pass1Box').val();
				}}
			]
		});
		$('#pass1Box').pwstrength({
			callback: function(strength) {
				pwstr = strength;
			}
		});

		$("#pwupdate input[type='submit']").jqxButton({ width: 216, theme: cargohub.jqxtheme });

		$('#pwupdate form').ajaxForm({
			dataType: 'json',
			beforeSubmit: function(formData, jqForm, options) {

				var c, f = {};
				for(c=0; c< formData.length; c++)
					f[formData[c].name] = formData[c].value;

				if(f['password'].length == 0) {
					cargohub.alert("You must enter your new password");
					return false;
				}

				// First make sure the same password has been entered in both fields (password, passwordr)
				if(f['password'] != f['passwordr']) {
					cargohub.alert("Please enter the same password twice");
					return false;
				}

				// Also, do not allow (too) insecure passwords
				if(pwstr < 3) {
					cargohub.alert("Entered password is too insecure. Please read the hints for strong passwords.");
					return false;
				}

				if(pwstr == 3) {
					if(!confirm("Are you sure you want to proceed with this mediocre password?"))
						return false;
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
						cargohub.its.http_fail(null, "Access.Pwreset::Update_Password");
				}
			},
			error: function(xhr) {
				cargohub.its.http_fail(xhr, "Access.Pwreset::Update_Password");
			}
		});
	};

	// Public methods
	this._public({
		/**
		 * Module Initialization
		 */
		init: function() {
			init_PWUpdateForm();
		}
	});
});