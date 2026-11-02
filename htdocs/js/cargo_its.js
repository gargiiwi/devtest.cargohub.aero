/**
 * CargoConnect JavaScript Core
 * - Includes very commonly used interface elements ( windows setup )
 * - TODO: Merge certain things from Application.js in here (Heartbeat, EVAN), as "Components"; Others which are bigger should be implemented as independent components
 *
 * @see http://www.cargohub.aero/
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScriptCore
 * @version 2019.08.05
 */
cargohub.its = donrey.BASE._extend(function() {
	var self = this,
		c = null, // common counter
		modal_windows_ready = {},
		// a queue of asynchronous actions which are executed one by one, next event is activated only when previous has completed
		action_queue = new Array(),	action_to = null, cr_action_idx = 0,

	/**
	 * Adds an event to the queue. init_callback() functions are called one by one.
	 *   init_callback must set the completion callback to advance_Action_Queue(), in order to move to the next event
	 * @param name Name this action, so when it fails, the error could say which action has failed
	 * @param init_callback What function to be called when it's this action's turn to execute
	 * @param max_wait If specified, the action Queue doesn't wait for the call to advance_Action_Queue to occur, instead it displays an error when one of the actions has timed out
	 */
	queue_Action = function(name, init_callback, max_wait, cb_params)
	{
		action_queue.push({
			name: name,
			icb: init_callback,
			wait: max_wait,
			par: cb_params
		});
	},

	/**
	 * Begins or advances the action queue
	 */
	advance_Action_Queue = function()
	{
		if(cr_action_idx >= action_queue.length)
			return true; // done, no more actions left in the queue

		var cr_action = action_queue[cr_action_idx];
		$('#loading_console').append("<br />"+cr_action.name);
		clearTimeout(action_to);
		if(cr_action.wait)
			action_to = setTimeout(function() {
				$('#loading_console').append("<br /> "+cr_action.name+ " failed to complete in the allocated time: " + cr_action.wait);
			});
		cr_action.icb(cr_action.par);
		cr_action_idx++;
	},

	/**
	 * (Re)Initialize Action Queue
	 */
	reset_Action_Queue = function()
	{
		action_queue = [];
		clearTimeout(action_to);
		action_to = null;
		cr_action_idx = 0;
	};

	// public/priviledged functions
	this._public({
		/**
		 * Initialize the application
		 * -- Callback from jQuery, here there is no "this"
		 */
		init: function()
		{
                    
                    if ($('#user_on_this_record_page').length > 0 && $('#user_on_this_record_page').data('cmd') != '') {
                        var user_on_this_record_page = $('#user_on_this_record_page').data('cmd');
                        if (user_on_this_record_page > 0) {
                        var user_on_this_record_page_all_name = $('#user_on_this_record_page_all_name').data('cmd');
                            if (!confirm("\nRecord is already opened by '"+user_on_this_record_page_all_name+"':\n" + "Want to open it?  ")) {
                                window.history.back();
                                return false;
                            }
                        }
                    }

			var total_to_load = 0;
			self.config = CFG; delete CFG;

			reset_Action_Queue();
			self.time_now = new Date();

			// Initialize the application loading tracker
			var events_list = new Array();
			if(self.config.images) {
				events_list.push('images');
				total_to_load++;
			}

			if(self.config.jqwidgets) { // Framework - Always minified on Live server
				if(self.config.jqwidgets === true) { // all merged (~2.5MB of JS in one massive chunk !)
					total_to_load++;
					events_list.push('jqx');
				} else {
					// jqxWidgets are divided into loading slices
					for(c=0; c<self.config.jqwidgets.length; c++) {
						total_to_load++;
						events_list.push('jqx'+c);
					}
				}
			}
			if(self.config.xjs) { // Extra JS
				events_list.push('xjs');
				total_to_load++;
			}
			if(self.config.components) { // Reusable Components (a few) - Support minification ( min: prefix )
				events_list.push('components');
				total_to_load++;
			}
			if(self.config.modules) { // Application Modules (in most cases two, three max) - Support minification ( min: prefix )
				events_list.push('modules');
				total_to_load++;
			}

			donrey.eventTracker_Init(events_list, function(event_id, completed_so_far, when) {
				$('#loading_overlay span#per_complete').html(parseInt(completed_so_far * 100 / total_to_load) + "%");
			}, function(completed, completed_total, when) {
				if(completed) {
					$('#loading_console').append("<br />Loading finished, initializing...");
					if(self.config)
						setTimeout(function() {
							// Run the "jqxResponse" code to get browser information - It might be used by modules to change behaviour depending on the browser
							cargohub.response = new $.jqx.response();

							// Initialize modules when everything has been loaded (in the order specified in the self.config)
							for(c=0; c<self.config.modules.length; c++) {
								var module_name = self.config.modules[c].replace(/\//g, ''); // remove any forward slashes from module name (to allow subdirs)
								// If the name starts with "min:" DonReY will load the minified version from the alternate folder ( modules.min )
								if(module_name.substr(0,4) == 'min')
									module_name = module_name.substr(4);

								if(cargohub[module_name]) {
									if(self.config[module_name]) {
										// set module config if it exists (and remove from self)
										cargohub[module_name].config = self.config[module_name];
										delete self.config[module_name];
									}
									else
										// No ? Just create empty config, the module might initialize it by itself from other sources
										cargohub[module_name].config = {};
								}

								// check if module exists and has an init function
								if(typeof cargohub[module_name] === 'object' && typeof cargohub[module_name].init === 'function')
									cargohub[module_name].init(self.config.controller);
							}
							$('#loading_console').append("<br /><span style='color: green;'>Initialization complete !</span>");

							// remove overlay once all initialization has completed, to reveal the application in all it's glory :)
							$('body').css('overflow', 'auto');
							$('#loading_overlay').fadeOut(250);
						}, 100); // Slight delay to allow the browser to finish it's things
				} else {
					// TODO: More information about loading problems.
					$('#loading_console')
						.append("<p><span style='color: red;'>Unable to complete loading in the allocated time, please try reloading the page!</span></p><p>In case reloading doesn't help and the problem persists, please copy the above mentioned information and inform CargoHub Support Desk at <a href='mailto:support@cargohub.nl'>support@cargohub.nl</a>.</p>")
						.show();
				}

			}, self.config.timeout||total_to_load * 2500); // wait max 2.5 seconds for each fragment to load (just to avoid loading too long on smaller modules)

		// Load required images (if any)
			if(self.config.images) {
				donrey.load_ImageResources(self.config.images, function(images, success) {
					donrey.eventTracker_Signal('images');

					cargohub.claims.images = images;
				}, self.config.images_timeout||5000);
			}

		// Jquery Cookie - Always loaded
			donrey.LAB.queueScript(donrey.CFG.paths.jquery + "others/jquery.cookie.js?v=1.4.1");
			// Add jquery UI effects if full version not specified
			// (Disabled, not working properly yet)
//			if(!self.config.xjs || (self.config.xjs && !self.config.xjs.jqueryui))
//				donrey.LAB.queueScript(donrey.CFG.paths.jquery + "jquery-ui/jquery-ui-effects.min.js?v=1.12.1");

		// Various external scripts ( which might be needed by some modules or jqwidgets itself )
			if(self.config.xjs) {
				var c_keys = new Array;
				for(c in self.config.xjs)
					c_keys.push(c);

				queue_Action("Loading eXtra JS: "+c_keys.join(', '), function() {
					var xjs = self.config.xjs;

					// Inline editable form controls
					if(xjs.jeditable)
						donrey.LAB.queueScript(donrey.CFG.paths.jquery + "jquery-jeditable/jquery.jeditable.mini.js?v=1.7.1");

					// jquery Autogrow for Textareas
					if(xjs.jqautosize) {
						donrey.LAB.queueScript(donrey.CFG.paths.jquery + "jquery-autosize/jquery.autosize.min.js?v=1.18.7");
					}

					// jquery Form ( Ajax Form Submit )
					if(xjs.jqform)
						donrey.LAB.queueScript(donrey.CFG.paths.jquery + "jquery-form/jquery.form.js?v=3.50");

					// jquery UI (For non-jqwidgets areas)
					if(xjs.jqueryui) {
						cargohub.jquitheme = xjs.jqueryui_theme||"smoothness";
						donrey.load_CSS(donrey.CFG.paths.jquery + "jquery-ui/"+theme+"/"+theme+".min.css?v=1.12.1");
						donrey.LAB.queueScript(donrey.CFG.paths.jquery + "jquery-ui/jquery-ui.min.js?v=1.12.1");
					}

					// TinyMCE editor, jQuery version
					if(xjs.tinymce) {
						donrey.LAB.queueScript(donrey.CFG.paths.lib + "tiny_mce/jquery.tinymce.min.js?v=4.4.3");
						donrey.LAB.queueScript(donrey.CFG.paths.lib + "tiny_mce/tinymce.min.js?v=4.4.3");
					}

					// Globalization plugin for jqWidgets
					if(xjs.global) {
						donrey.LAB.queueScript(donrey.CFG.paths.lib + "jqwidgets/globalization/globalize.js?v=3.6.0");
					}

					// CodeMirror highlighted code editor (necessary for templates and perhaps online code modifications of various files?):
					if(xjs.codemirror) {
						donrey.load_CSS(donrey.CFG.paths.lib + "codemirror/codemirror.css?v=4.1");
						donrey.LAB.queueScript(donrey.CFG.paths.lib + "codemirror/codemirror.min.js?v=4.1");

						// codemirror theme (optional):
						// TODO: This might need some work to function properly, it's not simply loading the CSS ...
						if(xjs.codemirror_theme) {
							donrey.load_CSS(donrey.CFG.paths.lib + "codemirror/theme/" + xjs.codemirror_theme + ".css?v=4.1");
						}
					}

					// FancyBox2 (image display) - Deprecated, v3 already exists
					if(xjs.fancybox2) {
						donrey.load_CSS(donrey.CFG.paths.jquery + "jquery-fancybox2/jquery.fancybox.css?v=2.1.7");
						donrey.LAB.queueScript(donrey.CFG.paths.jquery + "jquery-fancybox2/jquery.fancybox.pack.js?v=2.1.7");
						// TODO: Optional FancyBox2 Components
					}

					// DropZone HTML5 interactive file uploader
					if(xjs.dropzone) {
						donrey.load_CSS(donrey.CFG.paths.lib + "dropzone/dropzone.css?v=5.5.0");
						donrey.LAB.queueScript(donrey.CFG.paths.lib + "dropzone/dropzone.min.js?v=5.5.0");
					}

					// Air Datepicker
					if(xjs.airdate) {
						donrey.load_CSS(donrey.CFG.paths.jquery + "airdate/css/datepicker.min.css?v=2.2.3");
						donrey.LAB.queueScript(donrey.CFG.paths.jquery + "airdate/datepicker.min.js?v=2.2.3");
						donrey.LAB.queueScript(donrey.CFG.paths.jquery + "airdate/i18n/datepicker.en.js?v=2.2.3");
					}

					// Smart Time Entry Field
					if(xjs.timeentry) {
						donrey.load_CSS(donrey.CFG.paths.jquery + "jquery-timeentry/jquery.timeentry.css?v=2.0.1");
						donrey.LAB.queueScript(donrey.CFG.paths.jquery + "jquery-timeentry/jquery.plugin.min.js?v=1.0");
					}

					// Dateformat plugin (very nice function to format date directly in JS)
					if(xjs.dateformat)
						donrey.LAB.queueScript(donrey.CFG.paths.jquery + "others/jquery.dateFormat-1.0.js");

					if(xjs.moment)
						donrey.LAB.queueScript(donrey.CFG.paths.lib + "moment/moment.min.js?v=2.24.0");

					// Filter paste into an input to remove unsupported characters
					if(xjs.filteredpaste)
						donrey.LAB.queueScript(donrey.CFG.paths.jquery + "others/jquery-filteredPaste.js");

					// Password strength calculator
					if(xjs.pwstrength)
						donrey.LAB.queueScript(donrey.CFG.paths.jquery + "others/jquery.pwstrength.js?v=0.1.1");

					// Responsive Slider (uses jQuery)
					if(xjs.slider) {
						donrey.load_CSS(donrey.CFG.paths.lib + "responsive-slider/bootstrap.min.css?v=3.0.3");
						donrey.LAB.queueScript(donrey.CFG.paths.lib + "responsive-slider/jquery.event.move.js?v=1.3.6");
						donrey.LAB.queueScript(donrey.CFG.paths.lib + "responsive-slider/responsive-slider.js?v=0.10");
					}

					donrey.LAB.queueWait(function() {
						// This one is called when the secondary scripts have been loaded, so the application's module can be initialized
						donrey.eventTracker_Signal('xjs');
						advance_Action_Queue();

						// Because BLOODY FUCKING HELL, some operations need to be done after the main XJS finished loading
						if(xjs.codemirror) {
							// mode of operation? (html/xml/js, etc)
							if(xjs.codemirror == "htmlmixed")
								// xml + js + css + htmlmixed
								donrey.LAB.queueScript(donrey.CFG.paths.lib + "codemirror/mode/htmlmixed/htmlmixed.combined.min.js?v=4.1");
							else
								// simple modes:
								donrey.LAB.queueScript(donrey.CFG.paths.lib + "codemirror/mode/"+xjs.codemirror+"/"+xjs.codemirror+".js?v=4.1");

							// codemirror addons:
							if(xjs.codemirror_addons)
								for(c=0; c<xjs.codemirror_addons.length; c++) {
									switch(xjs.codemirror_addons[c]) {
									// TODO: Other addons
									case "hint":
										donrey.load_CSS(donrey.CFG.paths.lib + "codemirror/addon/hint/show-hint.css?v=4.1");
										donrey.LAB.queueScript(donrey.CFG.paths.lib + "codemirror/addon/hint/"+xjs.codemirror_addons[c].language+"-hint.js?v=4.1");
										break;
									case "active-line":
										donrey.LAB.queueScript(donrey.CFG.paths.lib + "codemirror/addon/selection/active-line.js?v=4.1");
										break;
									}
								}
						}

						// Part 2 of timeentry
						if(xjs.timeentry) {
							donrey.LAB.queueScript(donrey.CFG.paths.jquery + "jquery-timeentry/jquery.timeentry.js?v=2.0.1"); // TODO: MIN
						}

						if(xjs.dropzone) {
							Dropzone.autoDiscover = false;
						}
					});
					donrey.LAB.runQueue();
				});
			}

		// Load required jqWidgets files
			if(self.config.jqwidgets) {
				cargohub.jqxtheme = self.config.theme.jqx||"classic";
				cargohub.input_theme_classes = "jqx-widget jqx-widget-content jqx-widget-"+ cargohub.jqxtheme +
					" jqx-input-"+cargohub.jqxtheme +" jqx-widget-content-"+cargohub.jqxtheme;
				donrey.load_CSS(donrey.CFG.paths.lib + "jqwidgets/styles/jqx.base.css?v=3.9.0");
				donrey.load_CSS(donrey.CFG.paths.lib + "jqwidgets/styles/jqx."+cargohub.jqxtheme+".css?v=3.9.0");

				if(self.config.jqwidgets === true) { // load the entire jqwidgets at once - NOT recommended due to big size

					queue_Action("Loading jqWidgets Combined (All.js)", function() {

						donrey.LAB.queueScript(donrey.CFG.paths.lib + "jqwidgets/jqx-all.js?v=3.9.0");
						donrey.LAB.queueWait(function() {
							// Called when all required jqWidgets have loaded
							donrey.eventTracker_Signal('jqx');
							advance_Action_Queue();
						});
						donrey.LAB.runQueue();
					});

				} else { // load the core + the specified widgets in slices
					for(c = 0; c< self.config.jqwidgets.length; c++) {
						queue_Action("Loading jqWidgets Slice (" + self.config.jqwidgets[c].join(', ') +")", function(par) {
							for(c = 0; c< par.slice.length; c++)
								donrey.LAB.queueScript(donrey.CFG.paths.lib + "jqwidgets/jqx"+par.slice[c]+".js?v=3.9.0");
							donrey.LAB.queueWait(function() {
								// Called when all required jqWidgets have loaded
								donrey.eventTracker_Signal('jqx'+par.c);
								advance_Action_Queue();
							});
							donrey.LAB.runQueue();
						}, null, {c: c, slice: self.config.jqwidgets[c]});
					}
				}
			}

			// Style paths are dynamic, and might depend on domain settings and even user preferences!
			donrey.CFG.paths.components_css = "/css/" + self.config.theme.css + "/components/";
			donrey.CFG.paths.modules_css = "/css/" + self.config.theme.css + "/modules/";

		// Load components (Independent containers which can be reused in various application modules)
		// -- Components do not have mandatory initialization like modules, and may just be a collection of methods
		// -- Also, components do not have matching CSS file, so in case they need some CSS, make sure it exists as part of the caller module or loaded before running the component
			if(self.config.components)
				queue_Action("Loading Reusable Component(s): "+self.config.components.join(', '), function() {
					donrey.load_Components(self.config.components, function() {
						// done with modules, signal event
						donrey.eventTracker_Signal('components');
						advance_Action_Queue();
					}, "?v="+cargohub.version);
				});

		// Load application modules (and their corresponding CSS). Initialization function is automatically called on loading.
			if(self.config.modules)
				queue_Action("Loading Module(s): "+self.config.modules.join(', '), function() {
					donrey.load_Modules(self.config.modules, function() {
						// done with modules, signal event
						donrey.eventTracker_Signal('modules');
						advance_Action_Queue();
					}, "?v="+cargohub.version);
				});

			// All queued, Start Action Queue
			advance_Action_Queue();
                        
                        
                        
                        
//For grid and list view START
                        $(document).on('click','#clickToGridView',function(){
                            $('.recordDataPanelsSection').removeClass('section_list_view');
                            $('.recordDataPanelsSection').addClass('section_grid_view');
                            $('#clickToGridView').hide();
                            $('#clickToListView').show();
                        });
                        $(document).on('click','#clickToListView',function(){
                            $('.recordDataPanelsSection').removeClass('section_grid_view');
                            $('.recordDataPanelsSection').addClass('section_list_view');
                            $('#clickToListView').hide();
                            $('#clickToGridView').show();
                        });
                        
//For grid and list view END
                        
                 
                        
//For multi edit cancel button START
                        $(document).on('click','#multiEdit',function(){
                            $('#multiEditCancel').show();
                        });
                        $(document).on('click','#multiEditCancel',function(){
                            
                            
                            Swal.fire({
                            title: 'Are you sure?',
                            text: "Changes you made may not be saved.",
                            icon: 'warning',
//                            showCancelButton: true,
//                            confirmButtonColor: '#3085d6',
//                            cancelButtonColor: '#d33',
                            showDenyButton: true,
                            showCancelButton: false,
                            denyButtonText: "Cancel",
                            confirmButtonText: "Yes",
                            reverseButtons: true,
                          }).then((result) => {
                            if (result.isConfirmed) {
                               $('#multiEditCancel').hide();
                            $("button[data-cmd='cancel-xf']").click();
                            }
                          })


                            
                          
                        });
                        
//For multi edit cancel button END       
                        
                        
		},

		/**
		 * Sets up the standardized "Modal Window" type object (having class: modal_window and a custom id)
		 */
		init_Modal_Window: function(id, buttons, btn_roles, setup_callback, cb_config, width, height)
		{
			if(modal_windows_ready[id])
				return;

			// default buttons config
			if(!buttons)
				buttons = {
					"ok": "Ok",
					"cancel": "Cancel"
				};
			if(!btn_roles)
				btn_roles = {
					ok: "ok",
					cancel: "cancel"
				};

			// The standard HTML for the modal window - TODO: NLS for the ok/cancel buttons
			var modal_window_html = '<div id="modal_'+id+'" class="modal_window">'+
				'<div></div>'+ // Title
				'<div tabindex="0">'+ // Body
					'<div class="modal_content"></div>'+
					'<div class="modal_buttons">'; // buttons
			for(c in buttons)
				modal_window_html += '<button id="modal_'+id+'_'+c+'">'+ buttons[c]+ '</button>';

			modal_window_html += '</div></div></div>'; // end buttons, body, window

			// Create the window object
			var $modal_window = $(modal_window_html),
				window_settings = {	theme: cargohub.jqxtheme, resizable: false, isModal: true, autoOpen: false };

			if(btn_roles.ok)
				window_settings.okButton = $('#modal_'+id+'_'+btn_roles.ok, $modal_window);
			if(btn_roles.cancel)
				window_settings.cancelButton = $('#modal_'+id+'_'+btn_roles.cancel, $modal_window);

			if(width && height) {
				window_settings.maxWidth = width;
				window_settings.maxHeight = height;
			}

			$modal_window.jqxWindow(window_settings);

			// jqx-ize the window buttons
			$('div.modal_buttons button', $modal_window).jqxButton({ theme: cargohub.jqxtheme });

			// initial setup (usually when this modal window is built once)
			if(setup_callback)
				setup_callback($modal_window, cb_config);

			modal_windows_ready[id] = true;
		},

		/**
		 * Opens the standardized "Modal Window" (with dom id: modal_window) in the center of the screen
		 * This function can be used on all various modules which need that
		 */
		open_Modal_Window: function(id, width, height, title, content, setup_callback, cb_config, caller_module)
		{
			// auto-init with default buttons ( ok, cancel ) if not already initialized
			if(!modal_windows_ready[id])
				self.init_Modal_Window(id, null, null, null, null, cargohub.fullwidth, 1000);

			var x = ($(window).width() - width) / 2 + $(window).scrollLeft(),
				y = ($(window).height() - height) / 2 + $(window).scrollTop(),
				$modal_window = $("div.modal_window#modal_"+id);

			// set size, position, title and content
			$modal_window.jqxWindow({ width: width, height: height, position: {x: x, y: y} });

			if(title)
				$modal_window.jqxWindow("setTitle", title);
			if(content)
				$(".modal_content", $modal_window).html(content);

			// Clear the common events (which might have been set up by previous setup_callback functions)
			$modal_window.off('moved').off('close').off('resized');
			$modal_window.jqxWindow("open");

			// Setup callback, may initialize elements inside the window
			if(setup_callback)
				setup_callback($modal_window, cb_config, caller_module);
		},

		/**
		 * Repoens an already initialized window, after centering on the screen on the current page scroll position
		 */
		reopen_Modal_Window: function(id)
		{
			var $modal_window = $("div.modal_window#modal_"+id),
				width = $modal_window.width(), height = $modal_window.height(),
				x = ($(window).width() - width) / 2 + $(window).scrollLeft(),
				y = ($(window).height() - height) / 2 + $(window).scrollTop();

			// set new position
			$modal_window.jqxWindow({ position: {x: x, y: y} });

			// And open it.
			$modal_window.jqxWindow("open");
		},

		/**
		 * Shows a standard shape modal message with an icon in it's left side. Only for 1-line short texts.
		 * -- Width is automatically determined from the length of the text (approximately), but it can be overwritten.
		 * -- The modal window with just one OK button needs to be preinitialized before using this function.
		 * -- TODO: Allow the Alert modal to suport more than a single line of text, perhaps full html content ?
		 *
		 * @param string message Message that will be displayed by this Alert modal
		 * @param string title Optional: Title of the alert modal
		 * @param string icon Custom icon to use instead of the "exclamation" (which symbolizes an Error)
		 * @param int width Optional: width Set custom width of the modal
		 * @param int height Optional: width Set cusotm height of the modal
		 * @param function setup_callback Optional: Function which will be called to provide extra setup on the alert (like a close function)
		 * @param mixed cb_config Optional: Configuration of the setup_callback
		 */
		alert: function(message, title, icon, width, height, setup_callback, cb_config)
		{
			if(!message)
				message = "(message unavailable)";

			// Initialize the special "Alert" type modal window (if necessary)
			self.init_Modal_Window('alert', { "ok": "Ok" });

			// And open it
			if(!title) title = "Error";
			if(!icon) icon = "exclamation.png";
			if(!width) width = parseInt(message.length * 7.5 + 85);
			if(!height) height = 125;

			self.open_Modal_Window('alert', width, height, title, "<div class='spacey center'><img src='/img/icons/"+icon+"' style='vertical-align:middle;' /> "+message+"</div>", setup_callback, cb_config);
			// Auto-focus on the OK button so the alert can be closed with a simple Enter key
			$("input#modal_alert_ok").focus();
			return false;
		},

		/**
		 * General HTTP "Fail" function used by ajax requests following a header("HTTP/1.0 4xx The error message");
		 */
		http_fail: function(xhr, r)
		{
			if(!xhr)
				cargohub.alert("No response from server. Please contact support!", r? r: "Unknown error", "emotion_question.png");
			else if(xhr.status != 200) {
				cargohub.alert(xhr.statusText, "Server response code: "+xhr.status, "stop.png");
			}
			// 200 might only mean nothing was returned (should not show an error - Just silently "do nothing")
			cargohub.components.spinner.Off();
		},

		/**
		 * Critical error encountered during javascript initialization
		 * -- Used to clear the frontend of any data and display an error message
		 */
		init_critical_error: function(message, title)
		{
			if(!title)
				title = "Critical Error !";
			$("div.page").html("<div class='center'><h1 style='padding: 30px; background-color: red; color: white; font-size: 200%; margin-bottom: 30px;'>"+title+"</h1><h2>&quot;"+message+"&quot;</h2><p>"+
			"Please contact support at <a href='mailto:support@cargohub.nl'>support@cargohub.nl</a>, quote the message above and explain what operations were done before encountering this problem"+
			"</p></div>");
			cargohub.alert(message, title, "stop.png");
		},

		/**
		 * Helper for jqxGrid virtual mode that structures filters before sending them to the server (because jqxGrid sucks at that and sends garbage)
		 * -- This is used currently in the "Tasks" grid, but it should be used in every virtual mode grid
		 */
		jqxGrid_FormatFilters: function(data) {
			var filters = {}, filterscount = 0, field, c2, set_vals, not_set_vals, set_test;
			if(data.filterscount > 0) {
				for(c=0; c< data.filterscount; c++) {
					field = data["filterdatafield" + c];
					if(!filters[field]) {
						filters[field] = [];
						filterscount++;
					}
					filters[field].push({
						c: data["filtercondition" + c], // condition
						v: data["filtervalue" + c] // value
//						o: data[field + "operator"] // Operator not really needed, it's always "and"
					});
				}
				// Detect checkbox filters (with 3 or more "EQUAL" conditions on the same field) - They are converted to a "IN_SET" condition and the value as an array - for even more compact data transmission
				for(c in filters) {
					if(filters[c].length > 2) {
						set_test = 1; set_vals = []; not_set_vals = [];
						for(c2 = 0; c2< filters[c].length; c2++) {
							if(filters[c][c2].c == 'EQUAL')
								set_vals.push(filters[c][c2].v);
							else if(filters[c][c2].c == 'NOT_EQUAL') {
								not_set_vals.push(filters[c][c2].v);
								set_test = -1;
							} else {
								set_test = 0;
								break;
							}
						}
						if(set_test == 1)
							filters[c] = [{
								c: "IN_SET",
								v: set_vals
							}];
						else if(set_test == -1)
							filters[c] = [{
								c: "NOT_IN_SET",
								v: not_set_vals
							}];
					}
				}
				// Filters are processed, removed the old format which eats a lot of request space
				for(c in data) {
					if(c.substr(0,15) == 'filtercondition' || c.substr(0,15) == 'filterdatafield' || c.substr(0,14) == 'filteroperator' || c.substr(0,11) == 'filtervalue' || c.slice(-8) == "operator")
						delete data[c];
				}
				// ... and add the compressed format as a JSON string
				data.filters = JSON.stringify(filters);
				data.filterscount = filterscount;
			}
		}
	});
});

// All universal Components container - Some are loaded directly here in this file, others are loaded dynamically on demand
cargohub.components = {};

/**
 * Reusable UI widgets
 */
cargohub.components.ui = donrey.BASE._extend(function() {
	var self = this
	;

	// exported methods
	this._public({
		/**
		 * Adds a jqxTooltip to an arbitrary DOM element, considering it's data-{} information
		 */
		add_Tooltip: function() {
			var data = $(this).data();
			if(data.tooltip)
				$(this).jqxTooltip({
					theme: cargohub.jqxtheme,
					content: data.tooltip,
					position: data.tooltip_position || 'bottom',
					left: data.tooltip_left || 0,
					top: data.tooltip_top || 0,
					width: data.tooltip_width || "auto",
					showDelay: data.tooltip_delay || 400,
					animationHideDelay: 100, // Very fast fadeout
					autoHideDelay: 10000
				});
		},

		// Configuration for Calendar Inputs ( must be set before setting the control on a set of elements and should be removed after )
		calendar_config: {},
		/**
		 * General Datepicker Input control (Air Datepicker) - Used in some forms.
		 * - Allows initialization by a value from the data-* attributes, TODAY or no value at all
		 */
		datepicker_input: function()
		{
                    
                        
                    
			// Try again with "Air Datepicker"
			var cal_setup = {
				language: "en",
				dateFormat: "yyyy-mm-dd",
				todayButton: new Date(),
				clearButton: true,
				autoClose: true,
				firstDay: 1,
				offset: 8,
				position: (self.calendar_config && self.calendar_config.vertical? self.calendar_config.vertical: "bottom ")+(self.calendar_config && self.calendar_config.align? self.calendar_config.align: "left")
			},
                                
			ini_date = $(this).data('value');
                
                
//                      Cargoclaim add post page modification  start

                        var cfg = $(this).data("calendar_config");
                        if (cfg || !$.isEmptyObject(cfg)) {
                            self.calendar_config = cfg;
                        }
//                      Cargoclaim add post page modification  end

			if(self.calendar_config && self.calendar_config.limit_past) {
				var max_Date = new Date(); max_Date.setDate(max_Date.getDate() + 1);
				cal_setup.maxDate = max_Date;
			} else if(self.calendar_config && self.calendar_config.limit_future) {
				var min_Date = new Date(); max_Date.setDate(max_Date.getDate() + 1);
				cal_setup.minDate = min_Date;
			}
                        
                        


			$(this)
			.css("width", self.calendar_config && self.calendar_config.width? self.calendar_config.width: 90)
			.css("height", self.calendar_config && self.calendar_config.width? self.calendar_config.width: 22)
			.datepicker(cal_setup);

			if(ini_date && (ini_date.toUpperCase() === 'TODAY' || ini_date.toUpperCase() === 'NOW')) {
				$(this).datepicker().data('datepicker').selectDate(new Date());
			} else if(ini_date) // For anything non-null, attempt to initialize with the specified value
				$(this).datepicker().data('datepicker').selectDate(new Date(ini_date));
                            
		},

		/**
		 * jQX version, seems bugged on old Safari - Still here until modifying templates everywhere
		 * - Allows initialization by a value from the data-* attributes, TODAY or no value at all
		 */
		calendar_input: function()
		{
 			var cal_setup = {
				theme: cargohub.jqxtheme,
				formatString: cargohub.date_format,
				firstDayOfWeek: 1,
				width: self.calendar_config && self.calendar_config.width? self.calendar_config.width: 104,
				height: self.calendar_config && self.calendar_config.height? self.calendar_config.height: 22,
				dropDownHorizontalAlignment: self.calendar_config && self.calendar_config.align? self.calendar_config.align: "left",
				showFooter: true, todayString: "<span class='jqx-rc-all' style='display: inline-block; font-weight: bold; padding: 0 6px; background-color: #ffffdb; border: 1px solid #fdc066;'>Today</span>" // This is not so good, but for now...
			},
			ini_date = $(this).data('value');

			// TODO: Make the past and future limit dates configurable
			if(self.calendar_config && self.calendar_config.limit_past) {
//				cal_setup.min = new Date(2010, 0, 1);
				cal_setup.max = new Date();
				cal_setup.max.setDate(cal_setup.max.getDate() + 1);
			} else if(self.calendar_config && self.calendar_config.limit_future) {
				cal_setup.min = new Date();
				cal_setup.min.setDate(cal_setup.min.getDate() - 1);
//				cal_setup.max = new Date(2021, 12, 31);
			}

			if(ini_date && (ini_date.toUpperCase() === 'TODAY' || ini_date.toUpperCase() === 'NOW'))
				cal_setup.value = new $.jqx._jqxDateTimeInput.getDateTime(new Date());
			else if(ini_date) // For anything non-null, attempt to initialize with the specified value
				cal_setup.value = new $.jqx._jqxDateTimeInput.getDateTime(new Date(ini_date));
			else
				cal_setup.value = null; // If there's no initial date, start with no date, not with "Today"

			$(this).jqxDateTimeInput(cal_setup);
		},

		/**
		 * General "Clock" Input ( 24:00:00 hour clock with a control to enter current time and change values )
		 * - If initial time is missing, it starts up as empty. Also supports "NOW" to initializes as current time
		 */
		clock_input: function()
		{
			var time_cfg = {
				show24Hours: true,
				showSeconds: true,
				timeSteps: [1,1,10],
				spinnerImage: "spinnerBlue.png"
			}, ini_time = $(this).data('value')||null;

			$(this).css('text-align', 'center').timeEntry(time_cfg);

			if(ini_time && ini_time.toUpperCase() === 'NOW')
				$(this).timeEntry('setTime', new Date());
			else if(ini_time)
				$(this).timeEntry('setTime', ini_time);
		},

		/**
		 * Converts a select into a jqWidgets dropdown select
		 * Also supports checkbox mode for data sets (multiple options selected - This is not supported by default in HTML) - Replaces sets of checkboxes
		 */
		drop_select: function()
		{
			var jqx_ddl_cfg = {
				theme: cargohub.jqxtheme
			},
			dropdown = $(this),
			$options = $('option', this), size = $options.length,
			checked = [], opts_checked = 0, c = null,
			multi_select = dropdown.hasClass("multi-select");

			if(dropdown.data("filterable")) {
				jqx_ddl_cfg.filterable = true;
				jqx_ddl_cfg.searchMode = 'containsignorecase';
			}
			if(dropdown.data("autoheight"))
				jqx_ddl_cfg.autoDropDownHeight = true;
			if(dropdown.data("width"))
				jqx_ddl_cfg.width = parseInt(dropdown.data("width"));
			if(dropdown.data("dalign"))
				jqx_ddl_cfg.dropDownHorizontalAlignment = dropdown.data("dalign");
			if(dropdown.data("dwidth"))
				jqx_ddl_cfg.dropDownWidth = dropdown.data("dwidth");
			if(dropdown.data("dheight"))
				jqx_ddl_cfg.dropDownHeight = dropdown.data("dheight");
			if(multi_select) {
				jqx_ddl_cfg.checkboxes = true;
				jqx_ddl_cfg.selectionRenderer = function(orig,index,str) {
					var string = index==-1? "None selected": function() {
						var checked = dropdown.jqxDropDownList("getCheckedItems"), string = '';
						if(checked.length == size)
							string = "All selected!";
						else
							string = checked.length + " / " + size + " selected";
						return string;
					}();
					return "<span style='display: block; width: 278px; text-overflow: ellipsis; margin-top: 3px; background-color: transparent;'>"+string+"</span>";
				};

				// Go through the options and see which ones contain data-checked
				$options.each(function() {
					checked.push($(this).data('checked')? (++opts_checked > 0): 0);
				});
			}

			dropdown.jqxDropDownList(jqx_ddl_cfg);
			if(multi_select) {
				dropdown.jqxDropDownList('uncheckAll');
				if(opts_checked)
					for(c=0; c< checked.length; c++)
						if(checked[c])
							dropdown.jqxDropDownList('checkIndex', c);
			}
		},

		/**
		 * Input which only supports typing or pasting numbers into it, but still supports editing and pasting via keyboard filter
		 */
		numbers_input: function()
		{
			// This kind of input
			$(this)
			.on('keydown', function(e) {
				// Allow: backspace, delete, tab, escape, enter and .
				if($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
					// Allow: Ctrl+A / Ctrl+C (copy) / Ctrl+V (paste) / Ctrl+X (cut)
					(e.keyCode == 65 && e.ctrlKey === true) || (e.keyCode == 67 && e.ctrlKey === true) || (e.keyCode == 86 && e.ctrlKey === true) || (e.keyCode == 88 && e.ctrlKey === true) ||
					// Allow: Ctrl+Insert (copy) / Shift-Insert (Paste) / Shift-Delete (cut)
					(e.keyCode == 45 && e.ctrlKey === true) || (e.keyCode == 45 && e.shiftKey === true) || (e.keyCode == 46 && e.shiftKey === true) ||
					// Allow: home, end, left, right
					(e.keyCode >= 35 && e.keyCode <= 39) ||
					// Allow dash (minus) sign, but only if it's the first thing typed (doesn't work anymore when there's content, so even a 2nd dash is denied)
					((e.keyCode == 109 || (e.keyCode == 173 && e.shiftKey === false)) && $(this).val().length == 0)
					){
					// let keydown happen, don't filter
						return;
					}
				// Ensure that it is a number and stop the keypress
				if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
					e.preventDefault();
				}
			})
			.on('paste', function(e) {
				var paste = "";
				e.preventDefault();
				if(e.clipboardData || e.originalEvent.clipboardData)
					paste = (e.originalEvent || e).clipboardData.getData('text/plain');
				else if(window.clipboardData)
					paste = window.clipboardData.getData("Text");
				if(paste.length > 0) {
					var filtered = paste.replace(/[^0-9.]/g, ''), maxlen = $(this).prop("maxlength");
					// Check "maxlength" property and cut the paste before
					if(maxlen)
						filtered = filtered.substr(0,maxlen);
					$(this).val(filtered);
				}
			});
		},

		/**
		 * Complex Remote-Search Combobox, uses a remote request to return results
		 * - Uses two associated hidden inputs to prepare and save it's value
		 * - Expects the returned server data to contin the values "id" and "text" for each of the returned records.
		 * - A value with ID = 0 is considered invalid and the combobox is cleared when selecting it.
		 * - The combobox is also cleared when not actually selecting a value (impossible to exit the control with a non-valid selection)
		 */
		remotesearch_input: function()
		{
			var $this = $(this), $assoc_id = $($this.data('id')), $assoc_text = $($this.data('id')+'_text'), q = $assoc_id.val(),
			// setup the suggestion source
			source_setup = new $.jqx.dataAdapter({
				datatype: "json",
				datafields: [
					{ name: 'id' },
					{ name: 'text' }
				],
				url: $this.data('sourceurl')
			}, {
				formatData: function (data) {
					if (q) {
						data.q = q;
						return data;
					}
				}
			}),
			// setup the combo search
			combo_setup = {
				theme: cargohub.jqxtheme,
				source: source_setup,
				remoteAutoComplete: true,
				minLength: 0,
				search: function(search)	// While typing...
				{
					if(search.length>=2) { // Search only when at least 2 characters are typed
						q = search;
						source_setup.dataBind();
					} else
						q = null;
				},
				valueMember: "id", displayMember: "text",
				width: $this.data('width')||360, height: 22,
				placeHolder: $this.data('placeholder')||"Type to search",
				autoDropDownHeight: true,
				dropDownWidth: $this.data('width')||360,
				animationType: $this.data('animation')||"none"
			}, change_on_select = null;

			// finally, prepare the combobox and events on it
			$this.jqxComboBox(combo_setup)
			/**
			 * Event - An item is selected in the combobox
			 */
			.on('select', function(event) { // value on select event
				if(event.args == undefined)
					return false;

				var item = $this.jqxComboBox('getItem', event.args.index);

				if(item != null && item.originalItem.id) {
					$assoc_id.val(item.originalItem.id);
					$assoc_text.val(item.originalItem.text);
					change_on_select = true;
				} else {
					$this.jqxComboBox('clearSelection');
					$assoc_id.val(0); $assoc_text.val('');
					return false;
				}
			})
			.on('change', function(event) { // The combobox is cleared, it should clear the associated ID and text as well
				if(change_on_select) {
					change_on_select = false;
					return;
				}
				// If the entered value does not result following a selection (bogus text entered), empties the search box
				// Not sure if this works properly, more testing required...
				if($('input.jqx-combobox-input', $this).val() !== $assoc_text.val()) {
					$this.jqxComboBox('clearSelection');
					$assoc_id.val(0); $assoc_text.val('');
					return false;
				}
			})
			.val($assoc_text.val()); // initial default, from the associated hidden text

			// Since version 3.3.0 of jqx, The Combobox doesn't databind() automatically, so do it if there's a default value
			if(q)
				source_setup.dataBind();

			// Add keyboard event to the INPUT inside the combobox
			$this.find("input.jqx-combobox-input").on('keyup', function(event) {
				if(event.keyCode == 13 || event.key == "Enter") {
					if($this.jqxComboBox("getSelectedIndex") == -1) {
						$this.jqxComboBox("selectIndex", 0);
						$this.jqxComboBox("close");
					}
				}
			});
		},

		/**
		 * Editing a big text inside it's own expanded textarea
		 */
		walloftext_input: function()
		{
			var $this = $(this), w = $this.width(), jse = $this[0], show_to = null;
			if((jse.clientHeight < jse.scrollHeight) || (jse.clientWidth < jse.scrollWidth))
				$this
				.on('mouseover', function() {
					show_to = setTimeout(function() {
						var p = $this.position(), $ov = $("<textarea style='position: absolute; overflow: auto; box-sizing: border-box; padding: 5px; background-color: white; border: 3px dashed orange; z-index: 1000000; box-shadow: 3px 3px 5px rgba(0, 0, 0, 0.3);'>"+ $this.text()+ "</textarea>");
						$ov.css('left', p.left - 7).css('top', p.top - 6).css('width', w + 14).css('height', 500);
						$ov
						// Event: When mousing out, OV vanishes (but saves any changed content into the caller)
						.on('mouseout', function() {
							$this.text($ov.val());
							donrey.final_Delay("walloftext", function() {
								$ov.fadeOut(function() {
									$ov.remove();
								});
							}, 1500);
						})
						.on('mouseover', function() {
							donrey.cancel_Delay("walloftext");
						});

						$this.parent().append($ov);
					}, 400);
				})
				.on('mouseout', function() {
					clearTimeout(show_to);
				});
		},

		/**
		 * View only version of the wall of text
		 */
		walloftext_view: function()
		{
			var $this = $(this), w = $this.width(), jse = $this[0], show_to = null;
			// Only apply if there is really that much text that it overflows
			if((jse.clientHeight < jse.scrollHeight) || (jse.clientWidth < jse.scrollWidth))
				$this
				.on('mouseover', function() {
					show_to = setTimeout(function() {
						var p = $this.position(), $ov = $("<div style='position: absolute; overflow: auto; box-sizing: border-box; padding: 5px; background-color: white; border: 1px solid blue; z-index: 1000000; box-shadow: 3px 3px 5px rgba(0, 0, 0, 0.3);'>"+ $this.html()+ "</div>");
						$ov.css('left', p.left-1).css('top', p.top-1).css('width', w + 12).css('height', 500);

						$ov
						// Event: When mousing out, OV vanishes (but saves any changed content into the caller)
						.on('mouseout', function() {
							$ov.fadeOut(function() {
								$ov.remove();
							});
						});
						$this.parent().append($ov);
					}, 400);
				})
				.on('mouseout', function() {
					clearTimeout(show_to);
				});
		},

		/**
		 * Button which opens a window with information
		 * - The HTML which is placed inside the window must already exist in the dom, and it's ID present in data-template
		 * - Window width, height and title must also exist in the data
		 */
		button_InfoWindow: function() {
			$(this).jqxButton({
				theme: cargohub.jqxtheme
			})
			.on('click', function() {
				var window = $(this).data('window').split(',');

				cargohub.components.modal.create('general', {
					buttons: {ok: "Close"}
				});

				cargohub.components.modal.open('general', {
					width: window[0],
					height: window[1],
					title: window[2],
					content: $('#'+$(this).data('template')).html()
				});
			});
		}
	});

});

/**
 * A "spinner" overlay which informs the user that a background operation is in progress ( with server access ),
 * - and he should wait until complete ( or receiving an error message )
 */
cargohub.components.spinner = donrey.BASE._extend(function() {
	var self = this,

	spinner_active = false,	spinner_counter = 0, spinner_timeout = null, spinner_show_timeout = null,

	show = function(line0, line1)
	{
		if(!line0)
			line0 = "Processing";
		$("#spinner_overlay #spinner_line0").html(line0);
		if(!line1)
			line1 = "Please wait ...";
		$("#spinner_overlay #spinner_line1").html(line1);

		spinner_active = true;
		$("#spinner_overlay").show().css('right', "-250px").animate({right: '40px', opacity: '0.8'});
	},

	hide = function()
	{
		if(!spinner_active)
			return;
		$("#spinner_overlay").show().animate({right: '-250px', opacity: '0'}, "fast", function() {
			$(this).hide();
			spinner_active = false;
		});
	}
	;

	// exported methods
	this._public({
		/**
		 * Displays a "spinner" indicating there is some activity in the system.
		 * This one is always placed in the top-right corner of the website, semi-transparently, covering whatever is there as long as there is activity.
		 * -- Also contains an optional "timeout", which calls on_timeout in case the spinner is not turned off before this.
		 *    (This usually would indicate that some activity didn't finish in time and there's a problem with the system or the network)
		 * If the function is called while the spinner is already on, the timeout is updated and a counter is increased, but the displayed text is not changed.
		 */
		On: function(line0, line1, timeout, on_timeout, show_delay)
		{
			if(spinner_active) {
				spinner_counter++;
				if(timeout && on_timeout) {
					// Overwrite the timeout with new one
					if(spinner_timeout)
						clearTimeout(spinner_timeout);

					spinner_timeout = setTimeout(on_timeout, timeout);
				}
			} else {
				spinner_counter=1;

				if(show_delay)
					// Delay showing the spinner ( useful to give a chance for stuff to load before the spinner is animated to avoid jerky animation )
					spinner_show_timeout = setTimeout(function() {
						show(line0, line1);
						spinner_show_timeout = null;
						if(timeout && on_timeout)
							spinner_timeout = setTimeout(on_timeout, timeout);
					}, show_delay);
				else {
					// Delays the timeout, so if the spinner never shows, the timeout is not created
					show(line0, line1);
					if(timeout && on_timeout)
						spinner_timeout = setTimeout(on_timeout, timeout);
				}
			}
		},

		/**
		 * Reduces the spin-counter value
		 * Fades out the spinner indicating the background activity is complete if the "counter" reaches 0. Also cancels the "Timeout"
		 */
		Off: function(hide_delay)
		{
			// In case the processing is so fast the delayed spinner doesn't get a chance to show, clear it's "show" timeout and don't bother to hide what hasn't been shown
			if(spinner_show_timeout) {
				clearTimeout(spinner_show_timeout);
				spinner_show_timeout = null;
				return;
			}

			if(spinner_counter > 0) {
				spinner_counter--;

				if(spinner_timeout) {
					clearTimeout(spinner_timeout);
					spinner_timeout = null;
				}

				// Delay hiding the spinner ( even if the operation for which spinner was displayed completed very quickly )
				if(hide_delay)
					setTimeout(function() {
						hide();
					}, hide_delay);
				else
					hide();
			}
		}
	});
});

/**
 * Container for base classes for modules
 * -- (Maybe) Move "baseclass" to their own file structure ( like components )
 */
cargohub.baseclass = {
	// Object instancer from a "pseudo-class" - B = base, F = extension, i = configuration for constructor (if it exists)
	_instance: function(B, F, i)
	{
		F = F || function() {};
		F.prototype = new B();
		var o = new F();
		// The original object can be accessed through _parent
		o._parent = F.prototype;
		// calls constructor if it exists
		if(i && typeof o._construct === 'function')
			o._construct(i);
		// finally, link the instance to the private area of the parent
		if(typeof o._linkInstance === 'function')
			o._linkInstance(o);
		return o;
	}
};

/**
 * "Baseclass" for modules using a single "grid" - contains some common functions useful for fully-powered grids (jqxGrid)
 * - This function doesn't exist on it's own ( not executed until receiving an instance )
 * - It can exist in multiple copies simultaneously ( it can be instanced )
 */
cargohub.baseclass.fullgrid = function() {
	var instance,			// Instance object derived from this one
	c = null, c2 = null,	// Reusable temporary counters
	// About the grid itself...
	$grid = null,			// The jQuery object which became the grid. Used for all events and other operations
	grid_source = null,		// Grid's data adapter. Must be initialized before preparing grid.
	grid_columns = [],		// List of columns present in the grid
	grid_columns_index = {},// Indexes of these columns, necessary for filters. Automatically constructed
	grid_config = {},			// Grid's initial configuration. Constructed before initialization - Starts with some defaults
	// Grid state vars
	grid_persistence = false,
	grid_persistence_id = null,

	/**
	 * Sets up initial filters on the defined columns (if they exist and match)
	 */
	init_Filters = function() {
		if(instance.config.grid_state.filters) {
			var filterdata = null, jqxfilter = null, fd = null;
			for(c=0; c<instance.config.grid_state.filters.length; c++) {
				filterdata = instance.config.grid_state.filters[c];
				if(typeof grid_columns_index[filterdata.column] == 'undefined')
					continue; // Column not found, skip

				// Create the jqxFilter (may have multiple filters in it - checkboxes for example)
				jqxfilter = new $.jqx.filter();
				for(c2=0; c2< filterdata.filters.length; c2++) {
					fd = filterdata.filters[c2];
					jqxfilter.addfilter(fd.operator, jqxfilter.createfilter(fd.type, fd.value, fd.condition));
				}

				// ... and apply it to the proper column
				grid_columns[grid_columns_index[filterdata.column]].filter = jqxfilter;
			}
		}
	},

	/**
	 * The grid's filter, sorting, page or page size is changed - Save the information in session so on next load the same initial conditions are kept.
	 */
	event_GridState = function(event)
	{
		switch(event.type) {
		// Filter event, it affects number of returned rows, so paging is also reset
		case 'filter':
			if(event.args.filters) {
				instance.config.grid_state.filters = [];
				for(c=0;c<event.args.filters.length;c++) {
					instance.config.grid_state.filters.push({
						column: event.args.filters[c].datafield,
						filters: event.args.filters[c].filter.getfilters()
					});
				}
				instance.config.grid_state.pagenum = 0;
			}
			break;
		// Sort by a certain column. Doesn't affect the total returned rows, or the page number
		case 'sort':
			instance.config.grid_state.sort = {
				column: event.args.sortinformation.sortcolumn
			};
			if(event.args.sortinformation.sortdirection.ascending)
				instance.config.grid_state.sort.direction = 'asc';
			if(event.args.sortinformation.sortdirection.descending)
				instance.config.grid_state.sort.direction = 'desc';
			break;
		// This changes the number of rows on a page. The grid also sends a "pagechanged" event after it, so state only needs to be saved with that event
		case 'pagesizechanged':
			return;
		// Page number is changed. Nothing else is affected, this is the lowest priority event
		case 'pagechanged':
			instance.config.grid_state.pagenum = event.args.pagenum;
			instance.config.grid_state.pagesize = event.args.pagesize;
			break;
		}

		if(grid_persistence) {
			$.post("/global/operations?action=Save_Grid_State", {
				grid_id: grid_persistence_id,
				state: JSON.stringify(instance.config.grid_state)
			}, function(x) {
				if(x.success) {
					// Nothing is done here, this is just a state-update operation when the user is navigating the data. It should not bother him
				} else
					// Unless something went wrong...
					cargohub.its.http_fail(null, "Global.Operations::Save_Grid_State");
			})
			.fail(cargohub.its.http_fail);
		}
	}
	;

	// Exported methods

	/**
	 * Prepares the jqxDataAdapter used as source for the grid
	 */
	this.source = function(source_config)
	{
		if(!instance.config.grid_state)
			instance.config.grid_state = {};

		// Pre-sorted ?
		if(instance.config.grid_state.sort) {
			source_config.sortcolumn = instance.config.grid_state.sort.column;
			source_config.sortdirection = instance.config.grid_state.sort.direction;
		}

		// Start at a certain page (saved in the persistence)
		if(instance.config.grid_state.pagenum)
			source_config.pagenum = instance.config.grid_state.pagenum;

		grid_source = new $.jqx.dataAdapter(source_config);
		return grid_source;
	};
	/**
	 * Adds a column to the grid
	 * @param def Columns' definitions
	 */
	this.column = function(def)
	{
		grid_columns_index[def.datafield] = grid_columns.length;
		grid_columns.push(def);
	};

	/**
	 * Prepares the grid
	 */
	this.init_Grid = function($container, jqx_config_override, extra_config)
	{
		// Prepare initial config, and apply overrides if they exist
		grid_config = {
			theme: cargohub.jqxtheme,
			width: cargohub.fullwidth - 14, autoheight: true,
			source: grid_source,
			columns: grid_columns,
			columnsmenu: false, columnsresize: true,
			sortable: true, filterable: true, showfilterrow: true,
//			pageable: true, pagermode: 'simple',
			altrows: true, selectionmode: "singlerow"
		};
		if(jqx_config_override)
			$.extend(grid_config, jqx_config_override);

		// Overrides coming from grid state - Pagesize, Filters
		if(grid_config.pageable && instance.config.grid_state.pagesize)
			grid_config.pagesize = instance.config.grid_state.pagesize;
		init_Filters();

		$grid = $container.jqxGrid(grid_config);

		// Extra config, to be used after grid is initialized
		if(extra_config) {
			if(extra_config.persistent) {
				grid_persistence = true;
				grid_persistence_id = extra_config.grid_id;

				// When grid is persistent, every time the grid is changed in a way, the new state is saved in session and locally
				$grid
				/**
				 * Event: Grid's active filters are changed by the user
				 */
				.on('filter', event_GridState)
				/**
				 * Event: Grid's active sort is changed by the user
				 */
				.on('sort', event_GridState)
				/**
				 * Event: Grid's page number is changed (Pager effect)
				 */
				.on('pagechanged', event_GridState)
				/**
				 * Event: Page size changed (Pager dropdown)
				 */
				.on('pagesizechanged', event_GridState);
			}
		}

		return $grid;
	};

	/**
	 * Clears any selection and updates the grid
	 */
	this.refresh_Grid = function()
	{
		$grid.jqxGrid('clearselection');
		$grid.jqxGrid('updatebounddata');
	};

	this._linkInstance = function(_instance)
	{
		instance = _instance;
	};
};

/**
 * Baseclass for modules that contain a single jqxDocking widget
 * Has functionality to remember the state of the docked panels and other panel-related features (inserting buttons in panel headers, changing panel content, etc.)
 */

cargohub.baseclass.docklets = donrey.BASE._extend(function() {
	var self,				// Represents the extended object
	c = null, c2 = null,	// Reusable counters

	config = {},			// Dock's configuration. Constructed before initialization - Starts with some defaults
	$dock = null,			// The jQuery object which becomes the docklet. Used for all events and other operations
	panels_ready = false,
	// Docker state vars
	dock_persistence = false,	// If true, the system remember the panels as the user left them ( read from profile ). ID is necessary
	dock_persistence_id = null,

	/**
	 * Sends the current layout of the docking panel to the global controller to support persistence of the docker
	 */
	save_Layout = function(e)
	{
		// Don't save docking layout while panels haven't yet finished initializing
		if(!panels_ready || !dock_persistence)
			return;

		// Introduce a small delay when doing panel operations to avoid doing unneeded ajax calls (for example when someone is playing with minimize-maximize button)
		donrey.final_Delay('save_docking_layout', function() {
			$.post("/global/operations?action=Save_Docking_Layout", {
				dock_id: dock_persistence_id,
				layout: $dock.jqxDocking('exportLayout')
			}, function(x) {
				if(x.success) {
					// Nothing is done here, this is just a state-update operation when the user is navigating the data. It should not bother him
				} else
					// Unless something went wrong...
					cargohub.its.http_fail(null, "Global.Operations::save_docking_layout");
			})
			.fail(cargohub.its.http_fail);
		}, 2000);
	}

	;
	this._construct = function() {
		self = this;
	};
	// exported methods
	this._public({
		/**
		 * Initialize the docking widget. If no width is specified, it attempts to use full width of the application + 20 (to cover precise edge to edge)
		 * -- This method actually creates the required HTML, and may contain a list of windows that will be added to the docker on init
		 * -- When persistent, it attempts to read the persistence configuration, repairs it to match the windows that have been created, then imports the configuration
		 */
		init_Docking: function($container, configuration, jqx_config_override)
		{
			// Prepare jqx config, and apply overrides if they exist
			var jqx_config = {
				theme: cargohub.jqxtheme,
				width: cargohub.fullwidth + 21,
				orientation: 'horizontal'
			};
			if(jqx_config_override)
				$.extend(jqx_config, jqx_config_override);

			if(!configuration)
				configuration = {
					slices: 1,
					hide_close_buttons: true // By default it doesn't allow closing panels, since there might not be an interface to reopen them.
				};
			config = configuration;

			// Construct the container HTML depending on number of specified slices (for now only "orientation: horizontal" is supported)
			for(c=0; c< config.slices; c++)
				$container.append("<div></div>");

			// HTML is ready, make the Docker
			$dock = $container.jqxDocking(jqx_config);

			// Add panels (windows) to the docker, as specified in the "panels_init" array
			if(config.panels_init)
				for(c=0; c< config.panels_init.length; c++)
					self.install_Panel(config.panels_init[c]);

			// Docker starts with "Close" buttons enabled, this allows turning them off, and also showing the "collapse" ones which are not on by default
			if(config.hide_close_buttons)
				$dock.jqxDocking("hideAllCloseButtons");
			if(config.show_collapse_buttons)
				$dock.jqxDocking("showAllCollapseButtons");

			if(config.persistent && config.dock_id) {
				dock_persistence = true;
				dock_persistence_id = config.dock_id;
			}

			if(config.saved_layout)
				$dock.jqxDocking('importLayout', config.saved_layout);

			// About 2 seconds later, to give time to the importLayout to arrange it's stuff, activate the autosave:
			setTimeout(function() {
				panels_ready = true;
			}, 2000);
			return $dock;
		},

		/**
		 * Adds and prepares an existing "panel" to the Docker. The proper HTML must already exist, or an error will occur.
		 * Also adds event handlers on window operations (for example, to make the entire docker persistent when changes to the windows occur - expand/contract/open/close)
		 * Config var:
		 * - "id" DOM id of the panel to install
		 * - "slice" In which slice of the docking will the panel (window) be added (index, starting with 0)
		 */
		install_Panel: function(config)
		{
			var $panel = $('#'+ config.id);
			if(!$panel.length)
				return false; // Don't try to initialize a window that doesn't exist (not in dom)

			$dock.jqxDocking('addWindow', config.id, 'default', config.slice, 65535);

			if(config.height)
				$panel.jqxWindow({height: config.height});

			// Add events to the freshly moved window
			$panel
			.on('close', function(e) {
				save_Layout();
			})
			.on('collapse', function(e) {
				save_Layout();
			})
			.on('expand', save_Layout)
			.on('moved', save_Layout)
			.on('resized', save_Layout);
		}
	});
// 	const currSelect = document.getElementById('xfd_pur_price_curr');
// if (currSelect) {
//     currSelect.querySelector('option[value=""]').removeAttribute('selected');
//     const eurOption = currSelect.querySelector('option[value="978"]');
//     if (eurOption) eurOption.selected = true;
//     console.log('EUR set:', currSelect.value); 
// }

});



//IWI new design 17072026 start

$(function () {

    var path = window.location.pathname
        .replace(/^\/+/, "")      // Remove leading /
        .replace(/\/+/g, "_");    // Replace / with _

    var hash = window.location.hash
        .replace(/^#/, "");       // Remove #

    var pageClass = path;

    if (hash) {
        pageClass += "_" + hash;
    }

    $("body").addClass(pageClass);

});
$(function () {

    $("#cargo-menu-btn, #cargo-menu-popup").hover(

        function () {
            $("#cargo-user-popup").hide();
            $("#cargo-menu-popup").stop(true, true).show();
        },

        function () {
            $("#cargo-menu-popup").stop(true, true).hide();
        }

    );

    $("#cargo-user-btn, #cargo-user-popup").hover(

        function () {
            $("#cargo-menu-popup").hide();
            $("#cargo-user-popup").stop(true, true).show();
        },

        function () {
            $("#cargo-user-popup").stop(true, true).hide();
        }

    );

});
//IWI new design 17072026 end