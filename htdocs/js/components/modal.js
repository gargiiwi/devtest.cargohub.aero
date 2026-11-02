/**
 * Component that creates standardized modal windows ( jqxWindows ) which can be used either to interact with the user, or to display a message.
 * Should be included in most or all modules that use any kind of windows, alerts, prompts, etc.
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoConnect
 * @subpackage JavaScriptUIComponents
 * @version 2019.01.04
 */
cargohub.components.modal = donrey.BASE._extend(function() {
	var self = this
	;

	// exported methods
	this._public({

		windows: {}, // List of initalized modals, containing their current parameters; Can be externally accessed to verify ID's, change parameters or trigger events.

		/**
		 * Creates a modal window with the specified ID, if it doesn't exist
		 */
		create: function(id, CFG)
		{
			if(self.windows[id])
				return;

			if(!CFG)
				CFG = {};

			// default buttons config - TODO: Support NLS for button text (instead of english Ok, Cancel)
			if(!CFG.buttons)
				CFG.buttons = {
					ok: "Ok",
					cancel: "Cancel"
				};
			if(!CFG.btn_roles)
				// Standard roles are "ok" and "cancel", when a window is closed by one of these buttons a certain event is triggered in jqxWindow
				CFG.btn_roles = {
					ok: "ok",
					cancel: "cancel"
				};

			// The standard HTML for the modal window
			var btn_style = "", window_html =
			'<div id="modal_'+id+'" class="modal_window">'+
				'<div>&nbsp;</div>'+ // Title
				'<div tabindex="0">'+ // Body
					'<div class="modal_content"></div>'+ // modal_content can be used as the container for another widget
					'<div class="modal_buttons">'; // buttons area
					for(c in CFG.buttons) {
						if(CFG.btn_styles && CFG.btn_styles[c]) {
							btn_style = ' style="'+ CFG.btn_styles[c]+ '"';
						} else
							btn_style = "";

						window_html += '<button'+ btn_style +' id="modal_'+id+'_'+c+'">'+ CFG.buttons[c]+ '</button>';
					}
			window_html += '</div></div></div>'; // end buttons, body, window

			// Create the window object
			var $modal_window = $(window_html),
				window_settings = {	theme: cargohub.jqxtheme, resizable: false, isModal: true, autoOpen: false };

			if(CFG.showCloseButton !== undefined)
				window_settings.showCloseButton = CFG.showCloseButton;
			if(CFG.draggable !== undefined)
				window_settings.draggable = CFG.draggable;

			if(CFG.btn_roles.ok)
				window_settings.okButton = $('#modal_'+id+'_'+CFG.btn_roles.ok, $modal_window);
			if(CFG.btn_roles.cancel)
				window_settings.cancelButton = $('#modal_'+id+'_'+CFG.btn_roles.cancel, $modal_window);

			window_settings.maxWidth = CFG.maxwidth || cargohub.fullwidth;
			window_settings.maxHeight = CFG.maxheight || 900;

			$modal_window.jqxWindow(window_settings);

			// jqx-ize the window buttons
			$('div.modal_buttons button', $modal_window).jqxButton({ theme: cargohub.jqxtheme });

			// initial setup (usually when this modal window is built once)
			if(CFG.setup_callback)
				CFG.setup_callback($modal_window, CFG.cb_config);

			self.windows[id] = window_settings;
			return true;
		},

		/**
		 * Opens the standardized "Modal Window" (with dom id: modal_window) in the center of the screen
		 * This function can be used on all various modules which need that
		 */
		open: function(id, CFG, caller_module)
		{
			// auto-init with default buttons ( ok, cancel ) if not already initialized
			if(!self.windows[id])
				self.create(id);

			// When Width/Height not specified, default to 410x280 (modal_content will be exactly 400x200)
			if(!CFG.width)
				CFG.width = 410;
			if(!CFG.height)
				CFG.height = 280;

			var x = ($(window).width() - CFG.width) / 2 + $(window).scrollLeft(),
				y = ($(window).height() - CFG.height) / 2 + $(window).scrollTop(),
				$modal_window = $("div.modal_window#modal_"+id);

			// Set Height of "modal_content" to the window height - 80px (The content scrolls when oversized)
			$modal_window.find('div.modal_content').height(CFG.height - 80);

			// set size, position, title and content
			$modal_window.jqxWindow({ width: CFG.width, height: CFG.height, position: {x: x, y: y} });

			if(CFG.title)
				$modal_window.jqxWindow("setTitle", CFG.title);
			if(CFG.content)
				$(".modal_content", $modal_window).html(CFG.content);

			// Clear the common events (which might have been set up by previous setup_callback functions)
			$modal_window.off('moved').off('close').off('resized');
			$modal_window.jqxWindow("open");

			// Setup callback, may initialize elements inside the window
			if(CFG.callback)
				CFG.callback($modal_window, CFG.cb_config, caller_module);

			return true;
		},

		/**
		 * Repoens an already initialized window, after centering on the screen on the current page scroll position
		 */
		reopen: function(id, callback, cb_config)
		{
			if(!self.windows[id])
				return false; // Error, nothing to reopen

			var $modal_window = $("div.modal_window#modal_"+id),
				width = $modal_window.width(), height = $modal_window.height(),
				x = ($(window).width() - width) / 2 + $(window).scrollLeft(),
				y = ($(window).height() - height) / 2 + $(window).scrollTop();

			// set new position
			$modal_window.jqxWindow({ position: {x: x, y: y} });

			// Run callback to change content before the window is actually reopened
			if(callback)
				callback($modal_window, cb_config);

			// And open it.
			$modal_window.jqxWindow("open");
		},

		/**
		 * Forgets a window (deletes it) - Allows reconfiguring the window from scratch
		 */
		forget: function(id)
		{
			if(!self.windows[id])
				return false; // It wasn't remembered

			$("div.modal_window#modal_"+id).jqxWindow('destroy');
			delete self.windows[id];
			return true;
		},

		/**
		 * Returns the DOM object representing the remembered Modal
		 */
		get_window: function(id)
		{
			return $("div.modal_window#modal_"+id);
		},

		/**
		 * Shows a standard shape modal message with an icon in the left side. Only for 1-line short texts.
		 * -- Width is automatically determined from the length of the text (approximately), but it can be overwritten.
		 * -- The modal window with just one OK button needs to be preinitialized before using this function.
		 * -- TODO: Allow the Alert modal to suport more than a single line of text, perhaps full html content ?
		 *
		 * @param string message Message that will be displayed by this Alert modal
		 * @param string title Optional: Title of the alert modal
		 * @param string icon Custom icon to use instead of the "exclamation" (which symbolizes an Error)
		 * @param int width Optional: width Set custom width of the modal
		 * @param int height Optional: width Set cusotm height of the modal
		 * @param function callback Optional: Function which will be called to provide extra setup on the alert (like a close function)
		 * @param mixed cb_config Optional: Configuration of the setup_callback
		 */
		alert: function(message, title, icon, width, height, callback, cb_config)
		{
			// Initialize the special "Alert" type modal window (if necessary)
			if(!self.windows['alert'])
				self.create('alert', {buttons: {ok: "Ok"}});

			if(!message)
				message = "No response from server, possible configuration error or software bug";

			// And open it
			if(!title) title = "Alert";
			if(!icon) icon = "exclamation.png";
			if(!width) width = parseInt(message.length * 7.5 + 80);
			if(!height) height = 130;

			self.open('alert', {
				width: width, height: height,
				title: title,
				content: "<div style='text-align: center; margin-top: 8px;'><img src='/img/icons/"+icon+"' style='vertical-align:middle;' /> "+message+"</div>",
				callback: callback, cb_config: cb_config
			});
			// Auto-focus on the OK button so the alert can be closed with a simple Enter key
			$("input#modal_alert_ok").focus();
			return false;
		},
		customalert: function(message, title, icon, width, height, callback, cb_config)
		{
			// Initialize the special "Alert" type modal window (if necessary)
			if(!self.windows['alert'])
				self.create('alert', {buttons: {yes: "Yes", notok: "No"}});

			if(!message)
				message = "No response from server, possible configuration error or software bug";

			// And open it
			if(!title) title = "Alert";
			if(!icon) icon = "exclamation.png";
			if(!width) width = parseInt(message.length * 7.5 + 80);
			if(!height) height = 130;

			self.open('alert', {
				width: width, height: height,
				title: title,
				content: "<div style='text-align: center; margin-top: 8px;'><img src='/img/icons/"+icon+"' style='vertical-align:middle;' /> "+message+"</div>",
				callback: callback, cb_config: cb_config
			});
			// Auto-focus on the OK button so the alert can be closed with a simple Enter key
			$("input#modal_alert_yes").focus();


			//when user clicks on no in alert box
			$('#modal_alert_notok').click(function(){
				$('#modal_alert .jqx-window-close-button').trigger('click');
				$("#xfd_mawb_s").val('');
			});
			$('#modal_alert_yes').click(function(){
				$('#modal_alert .jqx-window-close-button').trigger('click');
			});
			return false;
		},

		/**
		 * Standard shape modal with an icon that contains multiple buttons specified by "options"
		 * -- Example: "Rename this field ? [Yes]=>1 [No]=>0
		 * -- Example: "Pick a variant: [Alpha]=>A [Beta]=>B [Gamma]=>C
		 * -- Callback's parameter is the value of the selected option (which also closes the modal). If closed without an option, callback is not called
		 * -- Number of buttons should be limited so they can fit
		 */
		options: function(message, options, confirm_callback, title, icon, width, height)
		{

		},

		/**
		 * Special modal that contains a text-input confirmation of delete (must type DELETE in a text input)
		 * Callback is only called if the confirmation was successful, by entering DELETE in the box and then hitting "Confirm" button
		 * -- If not typing DELETE correctly in the input it shows an error and doesn't close
		 *
		 * @param function confirm_callback: Function which will be called when confirmation is successful - Place the code that will run the deletion here
		 */
		delconfirm: function(confirm_callback, message, title, icon, width, height)
		{
			if(!self.windows['delconf'])
				self.create('delconf', {buttons: {accept: "Confirm"}});

			if(!message)
				message = "Are you sure you want to delete this item ?";

			if(!title) title = "Delete confirmation";
			if(!icon) icon = "delete.png";
			if(!width) width = parseInt(Math.max(message.length * 7.5 + 100, 400));
			if(!height) height = 190;

			self.open('delconf', {
				width: width, height: height,
				title: title,
				content: "<div style='text-align: center; margin-top: 8px;'>" +
					"<p><img src='/img/icons/"+icon+"' style='vertical-align:middle;' /> "+ message+
					"</p><p>Type <b>DELETE</b> in here: <input type='text' id='modal_delconf_text' maxlength='6' style='width: 60px; text-align: center; text-transform: uppercase;' /> to confirm</p>"+
					"<p id='modal_delconf_error' style='width: 100%; text-align: center; color: red;'></p>"+
				"</div>",
				callback: function($delconf) {
					$("#modal_delconf_accept", $delconf).on('click', function() {
						if($("#modal_delconf_text", $delconf).val().toUpperCase() != "DELETE") {
							$("#modal_delconf_error", $delconf).show().html("You must type <b>DELETE</b> in the box !").fadeOut(3000);
							return;
						}

						$delconf.jqxWindow("close");
						confirm_callback(true);
					});

					$("#modal_delconf_text", $delconf)
					/**
					 * When pressing Enter in the box, trigger the confirm button
					 */
					.on('keypress', function(event) {
						if(event.keyCode == 13 || event.key == "Enter") {
							$("#modal_delconf_accept", $delconf).trigger('click');
						}
					}); // And focus this input

					setTimeout(function() {
						$("#modal_delconf_text", $delconf).focus();
					}, 100);
				}
			});
		},
		/**
		 * custom function for task delete, due to callback issue on existing function
		 */
		delcustomconfirm: function(confirm_callback, message, title, icon, width, height,del)
		{
			var op = false;
			if(!self.windows['delconf'])
				self.create('delconf', {buttons: {accept: "Confirm"}});

			if(!message)
				message = "Are you sure you want to delete this item ?";

			if(!title) title = "Delete confirmation";
			if(!icon) icon = "delete.png";
			if(!width) width = parseInt(Math.max(message.length * 7.5 + 100, 400));
			if(!height) height = 190;

			self.open('delconf', {
				width: width, height: height,
				title: title,
				content: "<div style='text-align: center; margin-top: 8px;'>" +
					"<p><img src='/img/icons/"+icon+"' style='vertical-align:middle;' /> "+ message+
					"</p><p>Type <b>DELETE</b> in here: <input type='text' id='modal_delconf_text' maxlength='6' style='width: 60px; text-align: center; text-transform: uppercase;' /> to confirm</p>"+
					"<p id='modal_delconf_error' style='width: 100%; text-align: center; color: red;'></p>"+
					"</div>",
				callback: function($delconf) {
					$("#modal_delconf_accept", $delconf).on('click', function() {
						if($("#modal_delconf_text", $delconf).val().toUpperCase() != "DELETE") {
							$("#modal_delconf_error", $delconf).show().html("You must type <b>DELETE</b> in the box !").fadeOut(3000);
							return;
						}
						$delconf.jqxWindow("close");
						if(op && del === 'delete'){
							return false;
						}
						if(del === 'delete'){
							op = true;
						}
						confirm_callback(true);
					});

					$("#modal_delconf_text", $delconf)
						/**
						 * When pressing Enter in the box, trigger the confirm button
						 */
						.on('keypress', function(event) {
							if(event.keyCode == 13 || event.key == "Enter") {
								$("#modal_delconf_accept", $delconf).trigger('click');
							}
						}); // And focus this input

					setTimeout(function() {
						$("#modal_delconf_text", $delconf).focus();
					}, 100);
				}
			});
		},
		/**
		 * custom function for sending otp to new users
		 */
		emailconfirm:function(confirm_callback, message, otp,operation){
			var op = false;
			if(!self.windows['delconf'])
				self.create('delconf', {buttons: {accept: "Confirm"}});

			if(!message)
				message = "Are you sure you want to delete this item ?";

			title = "Email confirmation";
			icon = "email.png";
			width = parseInt(Math.max(message.length * 7.5 + 100, 400));
			height = 220;

			self.open('delconf', {
				width: width, height: height,
				title: title,
				content: "<div style='text-align: center; margin-top: 8px;'>" +
					"<p><img src='/img/icons/"+icon+"' style='vertical-align:middle;' /> "+ message+
					"</p><p>Type <b>OTP</b> in here: <input type='text' id='modal_delconf_text' maxlength='6' style='width: 60px; text-align: center; text-transform: uppercase;' /> to confirm</p>"+
					"<span id='timer' style='color:red;'>1:00</span><span id='resend_msg' style='display: none;'>If you didn't received any Otp then <a id='resend_otp' href='javascript:void(0);'>click here to resend</a></span><p id='modal_delconf_error' style='width: 100%; text-align: center; color: red;'></p>"+
					"</div>",
				callback: function($delconf) {
					var interval;
					function countdown() {
						clearInterval(interval);
						interval = setInterval( function() {
							var timer = $('#timer').html();
							timer = timer.split(':');
							var minutes = timer[0];
							var seconds = timer[1];
							seconds -= 1;
							if (minutes < 0) return;
							else if (seconds < 0 && minutes != 0) {
								minutes -= 1;
								seconds = 900;
							}
							else if (seconds < 10 && length.seconds != 2) seconds = '0' + seconds;

							$('#timer').html(minutes + ':' + seconds);

							if (minutes == 0 && seconds == 0){
								clearInterval(interval);
								$('#timer').css('display','none');
								$('#resend_msg').fadeIn();
							}
						}, 1000);
					}
					$('.jqx-window-close-button').click(function () {
						$('#timer').text("1:00");
						clearInterval(interval);
					});
					$('#timer').text("1:00");
					countdown();
					$('#resend_otp').click(function(){
						$('#resend_msg').css('display','none');
						$('#timer').fadeIn();
						$('#timer').text("1:00");
						countdown();
						var inputEmail = $('#contact-email input').val();
						var inputName = $('#contact-name input').val();
						var randomAuth =Math.floor((Math.random() * 10000) + 9000);
						$('#ran_auth').val(randomAuth);
						$.post("/global/contact?action=email_contact", {
							name: inputName,
							email: inputEmail,
							otp:randomAuth
						}, function(x) {

						}).fail(cargohub.its.http_fail);
					});
					$("#modal_delconf_accept", $delconf).on('click', function() {
						if($("#modal_delconf_text", $delconf).val() != $('#ran_auth').val()) {
							$("#modal_delconf_error", $delconf).show().html("You must type correct <b>OTP</b> in the box !").fadeOut(3000);
							return;
						}
						$delconf.jqxWindow("close");
						if(op && operation === 'process'){
							return false;
						}
						if(operation === 'process'){
							op = true;
						}
						$('#timer').text("1:00");
						clearInterval(interval);
						confirm_callback(true);
					});

					$("#modal_delconf_text", $delconf)
						/**
						 * When pressing Enter in the box, trigger the confirm button
						 */
						.on('keypress', function(event) {
							if(event.keyCode == 13 || event.key == "Enter") {
								$("#modal_delconf_accept", $delconf).trigger('click');
							}
						}); // And focus this input

					setTimeout(function() {
						$("#modal_delconf_text", $delconf).focus();
					}, 100);
				}
			});
		}

		// TODO: A "Confirm" modal
		// TODO: A "Prompt" modal ... with these two, fully replace the ugly/inconsistent ones from the browser
	});

	// Load CSS on first use, because this module needs styles
	donrey.load_CSS(donrey.CFG.paths.components_css + "modal.css?v=2.7");
});

// Some shortcuts
cargohub.alert = cargohub.components.modal.alert;