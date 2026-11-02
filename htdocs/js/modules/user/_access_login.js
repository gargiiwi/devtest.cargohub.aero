/**
 * Module "System User Login" - Handles the system user login form by doing an AJAX request for the authenticate process
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoITS
 * @subpackage JavaScript Modules
 * @version 2.4.6
 */
cargohub.user_access_login = donrey.BASE._extend(function() {
	// Private
	var self = this,
		otp = null
		,
	/**
	 * Prepare the login form events
	 */
	init_LoginForm = function() {
		// style the other buttons on the form
		$("#remember").jqxCheckBox({ theme: cargohub.jqxtheme, height: 22 });
		$("#login form input[type='submit']").jqxButton({ theme: cargohub.jqxtheme, width: 80 });

		// ajaxForm plugin
		$('#login form').ajaxForm({
			url: "?action=authenticate",
			dataType: 'json',
			beforeSubmit: function(formData, jqForm, options) {
				// check if the fields are not empty
				for(var c=0; c< formData.length; c++) {
					if(!formData[c].value) {
						cargohub.alert("Please enter your Username and Password !");
						return false;
					}
				}

				// Add the value of the "Remember Me" checkbox
				formData.push({
					name: "remember",
					value: $("#remember").jqxCheckBox('checked')
				});

				if(otp) {
					formData.push({
						name: "otp",
						value: otp
					});
				}

				return true;
			},
			success: function(x) {
				if(x.twofa) {
					// Two-Factor Authentication required, open a prompt for the OTP
					otp = prompt("Please enter your PIN");
					if(!otp || otp.length!=6) {
						otp = null;
						return cargohub.alert("You must enter a PIN which is 6-charactes long");
					}

					$('#login form').submit();

				} else
				// when completed successfully, refresh the page
				if(x.success) {
					$('#login form').off('submit').submit();
				} else
				if(x.eula) {
					document.location.href = '/cms?path=eula';
					return;
				} else
				if(x.redirect) {
					document.location.href = '/';
					return;
				} else {
					otp = null;
					cargohub.alert(x.reason, "Message from server", "information.png");
				}
			}
		});
	},
	init_GetUpdates = function(){
			$.post("/public/access?action=recent_updates", {
				day: 700,
				limit: 4,
			},function(x) {
				if(x.success){
					if(x.result.length > 0){
						var html='';
						$('.rec-updates').css('display','block');
						$.each(x.result, function( index, value ) {
							var postedDate =	value.dt.split(" ");
							html+='<div style="\n' +
								'    background: #f5f5f5;\n' +
								'    padding: 15px;\n' +
								'    margin-bottom: 20px;\n' +
								'    border-radius: 5px;\n' +
								'    box-shadow: 0px 9px 14px 0px #dddddd;\n' +
								'">';
							html+= '<h3 class="rec-title"  style="\n' +
								'    font-size: 12px;\n' +
								'    font-weight: 600;\n' +
								'    color: #007bb7;\n' +
								'">'+value.title+'</h3>';
							html+='<span class="rec-date"  style="\n' +
								'    color: #0077b4;font-size: 10px;\n' +
								'">'+postedDate[0]+'</span>';
							html+='</div>';
						});
						$('#rec-upt').html(html);
						$('#rec-upt').append('<style>.rec-content p img{max-width: 100%; height: auto;}</style>');
					}
				}
			}).fail(cargohub.its.http_fail);
		};

	// Public methods
	this._public({
		/**
		 * Module Initialization
		 */
		init: function() {
			self.name = "System User Login";
			init_LoginForm();
			init_GetUpdates();
		}
	});
});