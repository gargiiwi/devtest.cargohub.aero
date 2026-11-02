/**
 * General Application Module (should be included automatically in every controller)
 * TODO: Perhaps merge this into Cargo_ITS.js ? They seem to serve similar purpose of providing universal functions
 * - Spinner removed from here...
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScriptUI
 * @version 2.7
 */
cargohub.application = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,

	// HeartBeat System
	hbi = null,
	local_lo_time = new Date().getTime(), // Local Liftoff time ( client )
	current_session_time = 0, // used to autologoff when the user hasn't been using his browser for a long time (autologoff)
	hb_interval = 10,		// Seconds: Do not set too fast !, it might overload the server when there are many users logged in
	hb_ss_warnings = {},
	hb_fails = 0,			// Times heartbeat has failed (by not returning). If it happens multiple times, it means the connection is broken
	hb_spinner = false,
	hb_max_fails = 4,		// Maximum times heartbeat is allowed to fail (consecutively). If a success message is returned, hb_fails is returned to 0
	hb_callbacks = {},		// Callbacks for the presence of certain variables into the heartbeat response
	/**
	 * Function that gets executed every "hb_interval" seconds, and does various things like:
	 * -- An ajax request to notify the backend that the user is still on the page
	 *   -- the "active sessions" table will contain the moment of last registered heartbeat, for a precise session time calculation
	 *   -- The request might return notifications/messages/commands from the server side (which are processed here)
	 * -- Auto logs-out the user if he hasn't done anything for a long time (just staring at a page)
	 * -- Might show a session time counter / time (in minutes/seconds) since login, so the user knows how much he/she spent
	 */
	heart_Beat = function()
	{
		var
			cbkey = null,
			time_hb = new Date(), // Heartbeat time (current client time)
//			time_logon = null, // Server time for logon (returned by heartbeat); Not sure if necessary
			time_lo = new Date(self.config.liftoff.replace(/-/g,"/")), // Server time for liftoff, used to calculate time difference between client and server
			time_ss = null // Last session activity ( could happen in any browser tab ). Timeout is calculated relative to this
		;
		$.post('/global/operations?action=HeartBeat', function(x) {
			// Heartbeat success - Update current_session_time as the difference between "logon" and "dt" - This is a global server value and should be updated on all open tabs
//			time_logon = new Date(x.logon.replace(/-/g,"/")); // unused at the moment
			time_ss = new Date(x.ss.replace(/-/g,"/"));

			current_session_time = time_hb.getTime() - time_ss.getTime() - (local_lo_time - time_lo.getTime());

			// Registered callbacks for heartbeat (could be used for instant notifications, chat system, administrative popups, etc)
			if(hb_callbacks) {
				for(cbkey in hb_callbacks) {
					if(x[cbkey] && typeof hb_callbacks[cbkey] === 'function')
						hb_callbacks[cbkey](x[cbkey]);
				}
			}

			hb_fails = 0;
			if(hb_spinner) {
				cargohub.components.spinner.Off();
				hb_spinner = false;
			}
		})
		// Http fail, might indicate a connection issue... start counting
		.fail(function(xhr) {
			if(xhr && xhr.status == 408) // Auto-fail directly if server responds with error code 408
				hb_fails = hb_max_fails;
			else {
				hb_fails++;

				if(!hb_spinner)
					cargohub.components.spinner.On("Connection Error", "Autologout in 2 minutes.", 8);

				hb_spinner = true;
				dd("Warning, Hearbeat failed "+hb_fails+" time(s)", "warn");
			}
			if(hb_fails >= hb_max_fails) {
				clearInterval(hbi);
				if(xhr)
					cargohub.alert("Connection error: "+xhr.statusText, "Response code: "+xhr.status, "stop.png", null, null, function($alert) {
						$alert.on('close', function() {
							document.location = "/?action=logout&mode=auto";
						});
					});
				else {
					alert("Connection to server interrupted for more than 2 minutes. Check your network");
					document.location = "/?action=logout&mode=auto";
				}
			}
		});

		if(sizeof(hb_ss_warnings) && current_session_time<= 2400000) {
			// Closes any warning boxes if timer
			for(c in hb_ss_warnings)
				self.evan_Close_Block(hb_ss_warnings[c]);
			// ... and reset them.
			hb_ss_warnings = {};
		} else {
			// Warn the user about the fact that his session is about to expire due to inactivity.
			if(current_session_time> 2400000 && !hb_ss_warnings.first) {
				hb_ss_warnings.first = self.evan_Standard_Notify('notify', "Over 40 minutes have passed since your last activity. (Re)Loading a module resets this timer.", 3000, true,
					"This application still is connected to the server, however without any activity the server-side session automatically times out.");
			}
			if(current_session_time> 5400000 && !hb_ss_warnings.second) {
				self.evan_Close_Block(hb_ss_warnings.first);
				hb_ss_warnings.second = self.evan_Standard_Notify('warning', "Over one hour and a half has passed since last action. The software disconnects after two hours of inactivity", 1500, true,
					"This is your second notification related to the session expiration. Please logoff or continue your work in this application.");
			}
			if(current_session_time> 6900000 && !hb_ss_warnings.logoff) {
				self.evan_Close_Block(hb_ss_warnings.second);
				hb_ss_warnings.logoff = self.evan_Standard_Notify('lock', "Almost two hours have passed without any activity. Logging out automatically in 5 minutes", 300, true,
					"This is the final warning before the server will automatically disconnect you. Thank you for using CargoHub Connect !");
			}

			if(current_session_time> 7130000)
				document.location = "/?action=logout&mode=auto";
		}
	},

	// EVAN System
	evan_blocks = 0,
	evan_callbacks = {},
	evan_Timer_Tick = function()
	{
		// Check if the evan blocks have an auto-close set on them, if so reduce the timer, and when it expires make the block dissapear.
		$("#evan > *").each(function() {
			var autohide_timer = $("div.evan-hider", this).data("timer");
			if(autohide_timer) {
				autohide_timer--;
				if(autohide_timer > 0) {
					$("div.evan-hider", this).data("timer", autohide_timer).find("> span").text("Vanishing in "+autohide_timer+" seconds");
				} else {
					self.evan_Close_Block(this.id.substr(5));
				}
			}
		});

		if(evan_blocks > 0)
			setTimeout(evan_Timer_Tick, 1000);
	},

	// UserBar System
	/**
	 * Create the user bar
	 */
	create_UserBar = function(active_session)
	{
		$("#user-menu-bar").jqxMenu({
			width: cargohub.fullwidth, autoOpen: true, showTopLevelArrows: true, theme: 'itsblack',
			minimizeWidth: null, animationShowDuration: 100, animationHideDuration: 100,
			autoOpen: false
		})
		/**
		 * Event: When a menu on the userbar is shown, always force -ANY- other menus in the application (which do not have the special class "jqx-menu-popup-itsblack" to hide
		 * - This is a hack, but hopefully it helps with the annoying "menu over menu" problem
		 */
		.on('shown', function(e) {
			$(".jqx-menu-popup").each(function() {
				var $popup = $(this);
				if(!$popup.hasClass('jqx-menu-popup-itsblack'))
					$popup.hide();
			});
		});
		$("#user-menu-bar li[data-float='right']").css('float', 'right');

		if(active_session) {
			// Events active only when user is logged in ( active session )
			$(".jqx-menu-item-itsblack, .rt-switcher").on('click', function() {
				switch($(this).data('op')) {
					// switch theme menu
					case 'sw-jqxtheme':
						self.save_Preferences({
							"jqx_theme": $(this).data('id')
						}, function() {
							document.location.reload();
						});
						break;

					// switch Issue Type menu
					case 'sw-ist':
						self.save_Preferences({
							"rt_id": $(this).data('id')
						}, function() {
							// Controllers supporting direct record type switching
							if($.inArray(self.config.controller, ["App_Ctrl_User_Overview", "App_Ctrl_Public_Issue", "App_Ctrl_Issue_List"]) >= 0)
								document.location.reload();
							else
								document.location = "/issue/list";
						});
						break;
				}
			});
		}
	}
	;

	this._public({
		/**
		 * Module initialization
		 */
		init: function()
		{
			//===== jQuery Mini-Plugins =====//
			/**
			 * Tiny Jquery plugin used to move the cursor inside a textarea or input (or auto-select a range of characters)
			 */
			$.fn.selectRange = function(start, end)
			{
				return this.each(function() {
					if (this.setSelectionRange) { // Modern browsers
						this.focus();
						this.setSelectionRange(start, end);
					} else if (this.createTextRange) { // IE 8
						var range = this.createTextRange();
						range.collapse(true);
						range.moveEnd('character', end);
						range.moveStart('character', start);
						range.select();
					}
				});
			};

			/**
			 * Another tiny JQuery plugin that inserts an arbitrary text in a textarea/input at a specified cursor position or if missing, at the last cursor position, then selects the inserted text
			 */
			$.fn.insertAtPosition = function(insert, position)
			{
				return this.each(function() {
					var start, end, c_content = null, c_before, c_after;

					if(this.selectionStart || this.selectionStart == '0') { // Modern browsers
						if(position)
							start = end = position;
						else {
							start = this.selectionStart;
							end = this.selectionEnd;
						}

						c_content = $(this).val();
						c_before = c_content.substring(0, start);
						c_after = c_content.substring(end, c_content.length);
						$(this).val(c_before + insert + c_after);
						$(this).selectRange(start, start + insert.length);
					} else if (this.createTextRange) { // IE 8
						this.focus();
						var range = this.createTextRange();
						if(position) {
							range.collapse(true);
							range.moveEnd('character', position);
							range.moveStart('character', position);
						}
						range.text = insert;
						range.select();
					}
				});
			};

			// Prepare User information and User Menu Bar
			if(self.config.active_session) {
				// Private area, active user session
				if(!self.config.umb_disabled)
					create_UserBar(true);

				// activate the heartBeat - This is a function used to see what happens on server side, close session if no activity for a long time, notify incoming messages and other events that happen without direct user input
				if(self.config.debug.heartbeat)
					hbi = setInterval(heart_Beat, 1000 * (self.config.debug.hb_interval||hb_interval));
			} else {
				// Public area, user is not logged in
				if(!self.config.umb_disabled)
					create_UserBar(false);
			}

			// Events and Notificataions system
			var evan_msg, evan_block_id;

			// Delegated events on all blocks
			$("#evan")
			.on('mouseenter', "> *", function(event) {
				$("div.evan-hider", this).fadeTo(250, 1);
			})
			.on('mouseleave', "> *", function() {
				$("div.evan-hider", this).fadeTo(250, 0.3);
			});

			if(self.config.background)
			$("body").css("background-image", "url('"+self.config.background+"')");

			// Evan initialization, PHP side
			if(self.config.evan) {
				for(c=0; c< self.config.evan.length; c++) {
					evan_msg = self.config.evan[c];
					evan_block_id = self.evan_Standard_Notify(evan_msg.opts.type, evan_msg.text, evan_msg.opts.permanent? evan_msg.time_left: 0, new Date(evan_msg.time * 1000));
					// Add the notification session id to the block as data(), and also add block_id to self.config.evan so it can be found easy
					$("#evan-"+evan_block_id).data('ss_id', evan_msg.id);
					evan_msg.block_id = evan_block_id;
				}
			}

			// Some warnings for the user about the fact that they are not using the correct browser
			if(cargohub.response.browser.msie) {
				if(cargohub.response.browser.version < 10)
					self.evan_Standard_Notify("warning", "Limited support for old Internet Explorer version " + cargohub.response.browser.version, 30, null,
						"Please use a recent version (10 or 11) or switch to a FREE alternative like Firefox or Chrome!");
			}

			// Cookies!
			$.cookie.defaults = {
//				domain: "cargohub.aero",
				path: '/'
			};

			if(!$.cookie('tasty_cookie')) {
				// This cookie is not set, show a little animation with a clickable cookie
				setTimeout(function() {
					$(document.body).append('<div id="tasty_cookie"><button>I understand and accept</button><span>This website uses cookies for user interaction.<br />By using this service you consent to the general <a target="_blank" href="/cms?path=privacy">Privacy and Cookie statement</a></span></div>');
					$("#tasty_cookie").css('bottom', -50).animate({bottom: 0}, {duration: 1500});
/*					var bounce = setInterval(function() {
						$("#tasty_cookie").animate({top: -30}, {duration: 400, easing: 'easeOutQuad'});
						setTimeout(function() {
							$("#tasty_cookie").animate({top: 10}, {duration: 900, easing: 'easeOutBounce'});
						}, 400);
					}, 4000);*/

					$("#tasty_cookie button").on('click', function() {
//						clearTimeout(bounce);
/*						cargohub.its.alert(
							"<b>All websites</b> need &quot;cookies&quot; to function properly !<hr />"+
							"The law requires us to inform you that <i>this software uses them too</i>.<br />"+
							"<br />The cookie will not show up anymore after you click on <b>[ Ok ]</b>"+
							"<br /><p class='gray'>Feeling geeky ? <a target='_blank' style='color: blue;' href='https://en.wikipedia.org/wiki/HTTP_cookie'>Read more about HTTP cookies</a></p>",
							"Cookies are tasty !",
							"cookies.png",
							550, 250,
							function($alert) {
								$("#modal_alert_ok", $alert).on('click', function() {
									$.cookie('tasty_cookie', 'is_tasty', {expires: 365});
									$("#tasty_cookie").fadeOut(1000);
								});
							}
						);
*/
						$.cookie('tasty_cookie', 'is_tasty', {expires: 365});
 						$("#tasty_cookie").fadeOut(1000);
					});

				}, 500);
			}

/*
			var browser_small_notify = null, browser_small_notify_onHide = function() {
				browser_small_notify = null;
			}, browser_small_notify_Show = function() {
				browser_small_notify = self.evan_Standard_Notify("notify", "Your browser window is too small. Please enlarge it for optimal experience with this application", 30, true,
					"The software needs at least 1150 pixels in width to display properly. Make your browser full-screen or increase your screen resolution !", null, browser_small_notify_onHide);
			};

			if(cargohub.response.device.touch) {
				self.evan_Standard_Notify("warning", "Touch devices (phone, tablet) are not yet fully supported !", 30, null, "Please switch to a PC-like computer (Desktop, Laptop, iMac) for optimal experience.");
			} else {
				if($.inArray(cargohub.response.os.name, ['Windows','MacOS','Mac OS','Mac OS X'])===-1) {
					self.evan_Standard_Notify("warning", "Your operating system ("+cargohub.response.os.name+" v. "+cargohub.response.os.version+") has not been tested with this application.", null, null,
						"Contact support at address <a href='mailto:support@cargohub.nl'>support@cargohub.nl</a> if you experience difficulties.");
				}

				// The browser size check is only on non-touch (they usually auto-scale to screen size)
				if(cargohub.response.viewPort.width < 1150)
					browser_small_notify_Show();
				// Also add a message when the browser is dynamically resized to a too small value
				cargohub.response.resize(function(e) {
					donrey.final_Delay("browser_resize", function() {
						cargohub.response.refresh();
						if(cargohub.response.viewPort.width < 1150) {
							if(!browser_small_notify)
								browser_small_notify_Show();
						} else
							self.evan_Close_Block(browser_small_notify);
					}, 2000);
				});
			}
*/

			// Init the EVAN heartBeat callback
			self.heartBeat_Set_Callback('evan', function(evan)
			{
				var evan_msg, evan_block_id, c2, found;

				if(evan.length > 0)
					// Detected an EVAN response... show the notifications which are present in the response
					for(c=0; c< evan.length; c++) {
						evan_msg = evan[c];

						// Check if the message is already displayed, if so, replace it's content, otherwise create it.
						found = false;
						for(c2=0; c< self.config.evan.length; c++) {
							if(evan_msg.id == self.config.evan[c2].id) { // Found !
								$("#evan-"+self.config.evan[c2].block_id+" span.evan-title").html(evan_msg.text);
								found = true;
								break;
							}
						}

						// When a bar ID not found, create it instead.
						if(!found) {
							evan_block_id = self.evan_Standard_Notify(evan_msg.opts.type, evan_msg.text, evan_msg.opts.permanent? evan_msg.time_left: 0, new Date(evan_msg.time * 1000), evan_msg.opts.detail);
							// Add the notification session id to the block as data(), and also add this to self.config.evan so it can be found in the future
							$("#evan-"+evan_block_id).data('ss_id', evan_msg.id);
							evan_msg.block_id = evan_block_id;
							self.config.evan.push(evan_msg);
						}
					}
			});

			self.heartBeat_Set_Callback('evan_close', function(bar_list)
			{
				if(bar_list.length > 0)
					for(c=0; c< bar_list.length; c++) {
						// Check if an evan bar is displayed, and close it if so
						for(var c2=0; c< self.config.evan.length; c++) {
							if(bar_list[c] == self.config.evan[c2].id) { // Found !
								self.evan_Close_Block(self.config.evan[c2].block_id);
								break;
							}
						}
					}
			});
		},

		/**
		 * This function resets the timers related to session warning and instructs the heartbeat to communicate to the server that there is activity
		 * -- This should be called by the various ajax-only actions that do not reload the page
		 * Note: Might not be necessary, since now the server transmits the current session datetime (but that doesn't update on ajax requests)
		 * TODO: Instead of this (possibly obsolete) function, ajax requests which are not automatic should update the session timer by themselves
		 */
		reset_Session_Timer: function()
		{
			current_session_time = 0;
			// Closes any warning boxes
			for(c in hb_ss_warnings)
				if(hb_ss_warnings.hasOwnProperty(c))
					self.evan_Close_Block(hb_ss_warnings[c]);

			// And reset them.
			hb_ss_warnings = {};
		},

		/**
		 * Sets a callback that will run when a similarly named key is present in the heartbeat response
		 * @param key
		 * @param callback
		 */
		heartBeat_Set_Callback: function(key, callback)
		{
			hb_callbacks[key] = callback;
		},

		/**
		 * Clears a heartBeat callback (Is no longer called even if the hb response contains the key)
		 * @param key
		 */
		heartBeat_Clear_Callback: function(key)
		{
			delete hb_callbacks[key];
		},

		/**
		 * Creates a notification row in the EVAN area, with an optional auto-hide timer for the added notification.
		 * -- When timer expires or user clicks the close button, the notification is automatically faded out then destroyed
		 *
		 * @param innerhtml Content of the DIV that is generated and appended to the EVAN bar, might be text or a DOM element
		 * @param autohide_timer Optional autohide_timer (value 0 makes the message show permanently until the user clicks on the Evan Close button)
		 * @param on_hide Optional Callback to be executed when the block is hidden, either by user action or autohide
		 *
		 * @returns the autogenerated ID for the notification block
		 */
		evan_Notify: function(blockhtml, autohide_timer, on_hide)
		{
			var block_id = donrey.generate_UniqId(), $evan_block = $(blockhtml).prop('id', "evan-"+ block_id);
			// Add the hider to the block then set events on it
			$evan_block.prepend($("script#evan-hider").html());

			// Self-countdown timer
			if(autohide_timer) {
				$("div.evan-hider", $evan_block).data('timer', autohide_timer);
				$("div.evan-hider > span", $evan_block).text("Vanishing in "+autohide_timer+" seconds").show();
			}

			// The close button
			$("div.evan-hider > button", $evan_block).jqxButton({
				theme: cargohub.jqxtheme,
				width: 28, height: 20
			})
			.on("click", function() {
				self.evan_Close_Block(block_id);
			});

			$("#evan").prepend($evan_block);

			if(evan_blocks == 0) {
				$("#evan").fadeIn();
				setTimeout(evan_Timer_Tick, 1000);
			}

			evan_callbacks[block_id] = {};
			if(on_hide)
				evan_callbacks[block_id].on_hide = on_hide;

			evan_blocks++;
			return block_id;
		},

		/**
		 * Creates a standard notification in the EVAN bar, by displaying an icon (found in /img/icons/evan)
		 * - A class named "evan-{type}" is added to the div. Some of the standard notifications have defined styles making them show differently than others
		 *
		 * @param type Which notification icon to use (must be the name of one evan icon, like "error", "remove", "warning")
		 * @param title The text to be displayed
		 * @param autohide_timer
		 * @param show_time If true, it shows the time when this notification is added in front of the title
		 * @param detail If non-null, a second row is added to the standard notification, which shows with smaller letters detailed information about what is being notified
		 * @param css_classes Classes that is added to the created <div>, to allow styling the event
		 * @param on_hide Optional Callback to be executed when the block is hidden, either by user action or autohide
		 *
		 * @returns the autohide_timer left or 0 if the EVAN is not self-closing
		 */
		evan_Standard_Notify: function(type, title, autohide_timer, show_time, detail, css_classes, on_hide)
		{
			var tpl_data = {
				type: type,
				title: title,
				detail: detail
			};
			if(show_time)
				tpl_data.time = donrey.get_Time_Hmmss(show_time);

			return self.evan_Notify($($("script#evan-standard-notification").html().present(tpl_data)).addClass(css_classes), autohide_timer, on_hide);
		},

		/**
		 * Hides and destroys an evan block, specified by it's block ID
		 * @param block_id
		 */
		evan_Close_Block: function(block_or_id)
		{
			var $block = $(typeof block_or_id == "string"?("#evan-"+block_or_id): block_or_id);
			if($block.length == 0)
				return; // nothing to close !

			block_or_id = $block.attr('id').substr(5);
			$("div.evan-hider", $block).removeData("timer");
			$("div.evan-hider > span", $block).show().text("Closing now...");

			// This is a server-sent message, closing it calls an ajax request telling the server that the user has chosen to dismiss it (so it can be removed from session)
			if($block.data('ss_id'))
				$.post("/global/operations?action=evan_close", {
					id: $block.data('ss_id')
				});

			// Find the block being closed in self.config.evan and remove it
			for(c=0; c< self.config.evan.length; c++) {
				evan_msg = self.config.evan[c];
				if(evan_msg.block_id == block_or_id)
					self.config.evan.splice(c, 1);
			}

			$block.fadeOut(1500, function() {
				$(this).remove();

				evan_blocks--;
				if(evan_blocks == 0)
					$("#evan").hide();

				if(evan_callbacks[block_or_id]) {
					if(typeof evan_callbacks[block_or_id].on_hide === 'function')
						evan_callbacks[block_or_id].on_hide(block_or_id);
					delete evan_callbacks[block_or_id];
				}
			});
		},

		/**
		 * Saves changes to user preferences by calling the omonimous funciton in PHP under global_operations module
		 */
		save_Preferences: function(new_preferences, on_success)
		{
			$.post('/global/operations?action=save_preferences', {
				preferences: JSON.stringify(new_preferences),
				update_session: 1
			}, function(x) {
				if(x.success) {
					if(on_success)
						on_success(x);
				} else
					cargohub.its.http_fail(null, "Global.Operations::Save_Preferences");
			})
			.fail(cargohub.its.http_fail);
		},

		/**
		 * Deletes all existing user preferneces effectively resetting them to the system wide (or organization based) preferences
		 */
		reset_Preferences: function(on_success)
		{
			$.post("/global/operations?action=reset_preferences", {
				update_session: true
			}, function(x) {
				if(x.success) {
					if(on_success)
						on_success(x);
				} else
					cargohub.its.alert(x.reason, "Message from server", "information.png");
			});
		},

		/**
		 * Returns True if user is logged in (user information exists), or False otherise
		 */
		user_Logged_In: function()
		{
			if(self.config.active_session)
				return true;
			else
				return false;
		},

		/**
		 * Returns True if the logged in user is an admin, or False if no user is logged in or the user is not an admin
		 */
		user_Is_Admin: function()
		{
			if(!self.config.active_session)
				return false;

			return self.config.active_session.status == 'admin';
		},

		/**
		 * Returns True if the logged in user is an admin or has the specified privielege, or False if no user is logged in or the user doesn't have the privilege
		 * @param string|array privileges
		 * @param bool check_all If set to true, all privileges must exist. If false, matching either one returns true.
		 */
		user_Test_Privileges: function(privileges, check_all)
		{
			if(!self.config.active_session)
				return false;

			if(self.config.active_session.status == 'admin')
				return true;

			// Not asking for any privileges and not admin or no privileges... return false
			if(!privileges || !self.config.active_session.acls || !self.config.active_session.acls.privileges)
				return false;

			if(typeof privileges == "string")
				privileges = privileges.split(',');

			// If any of the privilege matches
			for(c=0; c<privileges.length; c++)
				if(check_all) {
					if($.inArray(privileges[c], self.config.active_session.acls.privileges) == -1) // If a single one doesn't exist, return false (condition AND)
						return false;
				} else
					if($.inArray(privileges[c], self.config.active_session.acls.privileges) > -1) // If any of them exists, return true (condition OR)
						return true;
			// If checked all and none matched, return false.
			return false;
		},

		/**
		 * Returns information about loggen-in user (if it exists)
		 */
		user_Get_Session: function()
		{
			return self.config.active_session;
		},

		/**
		 * Calculates relative time that has passed between now() and abs_time. Returns a string
		 * @return string
		 */
		calc_Relative_Time: function(time, absolute)
		{
			if(!time)
				return "";
			var hours, days;

			if(absolute) {
				days = Math.floor(time/86400);

				if(days <= 0)
					return "Same day";
				else if(days == 1)
					return "One day";
				else if(days < 183)
					return days + " days";
				else if(days < 1095) {
					var months = Math.floor(days/30.437); // 365.242 (average length) / 12
					return months + " months";
				} else
					return "Over 3 years";
			} else {
				hours = Math.round((cargohub.its.time_now - time)/3600000);
				days = Math.round((cargohub.its.time_now - time)/86400000);

				if(hours <= 0)
					return "Just now";
				else if(hours == 1)
					return "One hour";
				else if(hours < 24)
					return hours + " hours";
				else if(days == 1)
					return "Yesterday";
				else if(days < 91)
					return days + " days";
				else if(days < 1095) {
					var months = Math.floor(days/30.437); // 365.242 (average length) / 12
					return months + " months";
				} else
					return "Over 3 years";
			}
		},

		/**
		 * Renders a 24-hour international clock (HH:mm:ss) out of an integer number representing the number of seconds from time "00:00:00"
		 */
		render_24Clock: function(sec0)
		{
			var hours, minutes, seconds, rem;
			// First module sec0 to exactly 24 hours
			sec0 = sec0 % 86400;
			hours = parseInt(sec0/3600);
			rem = sec0 % 3600;
			minutes = parseInt(rem/60);
			seconds = rem - minutes*60;
			if(hours<10)
				hours = "0"+hours;
			if(minutes<10)
				minutes = "0"+minutes;
			if(seconds<10)
				seconds = "0"+seconds;
			return hours + ':' + minutes + ':' + seconds;
		},

		/**
		 * Search a set of jqxRadioButton elements inside a container for the one which is active and return it's data-value parameter.
		 * (un)Surprisingly, the frameword doesn't have such a function (while it does support groups of radiobuttons)
		 * - Note: The container must not contain anything else than radiobuttons for this to work !
		 *
		 * @param $container
		 */
		jqxRadioButtons_Get_Checked_Value: function($container)
		{
			var radiobutton_value = null;
			$("> div", $container).each(function()
			{
				if($(this).jqxRadioButton('checked'))
					radiobutton_value = $(this).data('value');
			});
			return radiobutton_value;
		}
	});
});