/**
 * Module "Contact Login" (Form to login as a contact)
 */
cargohub.contact_login = donrey.BASE._extend(function()
{
	// private
	var self = this,

	/**
	 * Prepare the login form events
	 */
	init_LoginForm = function()
	{
		// style the other buttons on the form
		$("div#login form input[type='submit']").jqxButton({ width: 80, theme: cargohub.jqxtheme });

		// ajaxForm plugin
		$('div#login form').ajaxForm({
			url: "?action=authenticate",
			dataType: 'json',
			beforeSubmit: function(formData, jqForm, options)
			{
				var f = {};
				for(var c=0; c< formData.length; c++)
					f[formData[c].name] = formData[c].value;

				// check if the e-mail field is an actual e-mail
				// not sure if this e-mail validation string actually works well ?
				if(!f['email'] || !/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/i.test(f['email'])) {
					cargohub.alert("You must enter valid e-mail address.");
					return false;
				}

				// check if the fields are not empty
				if(!f['password']) {
					cargohub.alert("Please type a password.");
					return false;
				}

				return true;
			},
			success: function(x)
			{
				// when completed successfully, submit the classic way so browser can save username/password
				if(x.success) {
					$('div#login form').off('submit').submit();
				}
			},
			error: cargohub.its.http_fail
		});
	};

	this._public({
		/**
		 * Module initialization
		 */
		init: function()
		{
			init_LoginForm();

			if(self.config && self.config.autologin)
				$('div#login form').submit();
		}
	});
});