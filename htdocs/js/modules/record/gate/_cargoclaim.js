/**
 * Interface control for the record type Cargoclaim access gateway (asking extra information before submission)
 * This allows the user to enter their contact information in a more direct way before even seeing the submit form (and also preventing a failed submission due to missing contact)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2018.06
 */
cargohub.recordgate_cargoclaim = donrey.BASE._extend(function() {
	// private
	var self = this,
	c = null,
	countdown = null,	// A general countdown Timeout or Interval - If it has a value, something is counting down.
	$gate = null,

	filtered_paste = null,

	// Keyboard and Copy/Paste filters:
	fn_only_numbers = function()
	{
		// This kind of input only supports typing numbers into it
		$(this)
		.on('keydown', function(e) {
			// Allow: backspace, delete, tab, escape, enter and .
			if($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
				// Allow: Ctrl+A / Ctrl+C (copy) / Ctrl+V (paste) / Ctrl+X (cut)
				(e.keyCode == 65 && e.ctrlKey === true) || (e.keyCode == 67 && e.ctrlKey === true) || (e.keyCode == 86 && e.ctrlKey === true) || (e.keyCode == 88 && e.ctrlKey === true) ||
				// Allow: Ctrl+Insert (copy) / Shift-Insert (Paste) / Shift-Delete (cut)
				(e.keyCode == 45 && e.ctrlKey === true) || (e.keyCode == 45 && e.shiftKey === true) || (e.keyCode == 46 && e.shiftKey === true) ||
				// Allow: home, end, left, right
				(e.keyCode >= 35 && e.keyCode <= 39)) {
				// let it happen, don't do anything
					return;
				}
			// Ensure that it is a number and stop the keypress
			if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
				e.preventDefault();
			}
		})
		.on('paste', function(e) {
			var paste = "", maxlen = $(this).prop("maxlength"), cut_paste = "";
			e.preventDefault();
			if(e.clipboardData || e.originalEvent.clipboardData)
				paste = (e.originalEvent || e).clipboardData.getData('text/plain');
			else if(window.clipboardData)
				paste = window.clipboardData.getData("Text");
			if(paste.length > 0) {
				filtered_paste = paste.replace(/[^0-9]/g, '');

				// Check "maxlength" property and cut the paste before
				if(maxlen)
					cut_paste = filtered_paste.substr(0, maxlen);
				$(this).val(cut_paste).trigger('change');
			}
		});
	},

	// Setup auto-advance fields (that move to another field when enough charactes have been typed into them)
	fn_auto_move = function()
	{
		$(this).on('keyup', function(e) {
			var data = $(this).data(), val = $(this).val(), automove_maxlen = null;
			// Don't do anything for: shift, ctrl, backspace, delete, tab, escape, enter and .
			if($.inArray(e.keyCode, [16, 17, 46, 8, 9, 27, 13, 110, 190]) !== -1 ||
				// Allow: Ctrl+A / Ctrl+C
				(e.keyCode == 65 && e.ctrlKey === true) || (e.keyCode == 67 && e.ctrlKey === true) ||
				// Allow: home, end, left, right
				(e.keyCode >= 35 && e.keyCode <= 39)) {
				// let it happen, don't do anything
					return;
				}
			// On typing anything else, check length and focus next
			if(val.length >= data.automove_len) {
				// To support simultaneous pasting into two fields (when first one has .auto-move), attempt to "paste" the remainder of "filtered_paste" into the second field
				if(filtered_paste && filtered_paste.substr(0, val.length) == val) {
					automove_maxlen = $("#"+data.automove_to).prop("maxlength");
					$("#"+data.automove_to).val(filtered_paste.substr(val.length, automove_maxlen));
				}

				if($("#"+data.automove_to).val().length == 8)
					$("#awb-check", $gate).trigger('click');
				else
					$("#"+data.automove_to).focus();
			}
		});
	},

	/**
	 * Status of the navigation bars - Allows restarting operations by clearing and hiding extra steps
	 */
	enabled_steps = [true, false, false],
	/**
	 * To avoid creating extra events, various elements are initialized only when needed one single time
	 */
	controls_ready = {
		'email-search': false,				// Input and Button to search for the e-mail in the database
		'cac-access': false,				// Input and Buttons for the Customer Account access area
		'enter-new-contact': false,			// Button(s) used to switch the contact editor into "new contact" mode (full edit)
		'use-cookie-contact': false,		// Button for using existing contact, selected from the...
		'cookie-contacts-select': false,	// ...list of contacts with ID's already saved in the cookie
		'contact-start-edit': false,		// Button to activate contact editing
		'cac-access-button': false,			// Button to activate customer account login
		'to-contact-edit': false,			// Button to edit contact info on logged in account
		'ready-to-submit': false			// A checkbox that needs to be checked in order to allow pushing the "Proceed to submit..." (both covered by this control marker)
	},

	/**
	 * MAWB that has been verified at step1 - It will be used on Step3 (various types of redirect) and passed on to submit form if required
	 */
	checked_mawb = null,

	/**
	 * Outcome of MAWB search - Can be "not-found", "mawb-located" or "error" (in case something went wrong with MAWB)
	 * -- In the Combo Check, it can be "no-results", "unique-claim" or "multiple-claims" (depends on the E-mail or Contact ID), or also "error" or "contact-error"
	 */
	mawb_outcome = null,

	/**
	 * Indicator that the user has clicked on the "Wish to submit new claim when one already exists" function.
	 * This flag exists so the Gate doesn't ask this question twice.
	 */
	proceed_confirm = false,

	/**
	 * In case a correct AWB was used before but a new one with problems is entered, turns off steps 2 and 3
	 */
	mawb_result_error = function(message)
	{
		$("#mawb-result", $gate).html("<p class='red'>"+message+"</p>");
		// In case of an error, hides steps 2 or 3 if they were previously enabled in case of an error

		if(enabled_steps[1]) {
			$("#step2-area > div").hide();
			$gate.jqxNavigationBar("setHeaderContentAt", 1, "<b>Step 2</b> &mdash; Search results <span class='fiftycent'>(waiting on step 1)</span>");
			$gate.jqxNavigationBar('collapseAt', 1);
			$gate.jqxNavigationBar('disableAt', 1);
			enabled_steps[1] = false;
		}

		if(enabled_steps[2])
			reset_Step3("<b>Step 3</b> &mdash; Outcome <span class='fiftycent'>(waiting on steps 1 and 2)</span>");

		mawb_outcome = null;
		return false;
	},

	/**
	 * Step1 - Awb Check
	 */
	init_Step1 = function()
	{
		// AWB number fields
		$("#awbp, #awbs", $gate).each(fn_only_numbers);
		$("#awbp", $gate).each(fn_auto_move);
		// The AWBs field reacts to Enter key and performs the Check imediately without having to click it
		$("#awbs", $gate).on('keyup', function(e) {
			if(e.keyCode == 13 && $(this).val().length == 8)
				$("#awb-check", $gate).trigger('click');
		});

		$("#awb-check", $gate).jqxButton({
			theme: cargohub.jqxtheme,
		})
		/**
		 * When AWB "Check" button is pressed, send the form data (the AWB number) to the server to verify if there's already cargoclaim records with this number
		 */
		.on('click', function() {
			$("#mawb-result", $gate).empty();

			// AWB validity check
			var awbp = $("#awbp").val(), awbs = $("#awbs").val();
			if(awbp.length < 3 || awbs.length < 8)
				return mawb_result_error("MAWB number consists of a 3-digit airline prefix and 8-digit serial number");

			serial7 = awbs.substr(0, 7);
			checkdigit = awbs.substr(7);
			if(parseInt(awbs.substr(0, 7)) % 7 != parseInt(awbs.substr(7)))
				return mawb_result_error("Invalid MAWB number. Please make sure you entered the correct digits");

			// #### Extra: Contact information when AWB number is not correct

			// So far so good, send the number to the server to check if valid airline and what is the status of the claim (if one is found)
			checked_mawb = awbp+ "-"+ awbs;

			// When logged in, we already know the contact_id of the person logged in - Run Contact Check instead of basic MAWB check
			if(self.config.contact_mode == 'cac' || self.config.contact_mode == 'user') {
				$.get("?action=Gate_Op", {
					op: "contact-check", // This also does MAWB check
					data: JSON.stringify({
						"con_id": self.config.contact.id,
						"mawb": checked_mawb,
						"prefix_check": true,
						"not_owned_check": true
					})
				}, init_Step2, 'json')
				.fail(cargohub.its.http_fail);
			} else {
				// When not logged in, verify MAWB first, then go through the E-mail search/select procedure
				$.get("?action=Gate_Op", {
					op: "mawb-check",
					data: JSON.stringify({"p": awbp, "s": awbs})
				}, init_Step2, 'json')
				.fail(cargohub.its.http_fail);
			}
		});

		// Auto-fill / Auto-focus
		setTimeout(function() {
			// Is there a preset MAWB ? Fill in the fields
			if(self.config.autofill && self.config.autofill.mawb && self.config.autofill.mawb.p.length == 3) {
				$("#awbp", $gate).val(self.config.autofill.mawb.p);
				if(self.config.autofill.mawb.s.length == 8) {
					$("#awbs", $gate).val(self.config.autofill.mawb.s);
					// Autotrigger the button if preset exists
					$("#awb-check", $gate).trigger('click');
				} else
					$("#awbs", $gate).focus();
			} else
				$("#awbp", $gate).focus();
		}, 200);
	},

	/**
	 * List of recently used contacts on this computer (max 7 results)
	 */
	adapter_CookieContacts = new $.jqx.dataAdapter({
		url: "/global/contact?action=Search_Contacts",
		datatype: "json",
		data: {
			'initialize': "recent",
			'check_editable': true
		},
		datafields: [
			{"name": 'id', type: 'int'},
			{"name": 'email', type: 'string'},
			{"name": 'name', type: 'string'},
			{"name": 'type', type: 'string'},
			{"name": 'editable', type: 'boolean'}
		],
		id: "id",
		async: true
	}),

	/**
	 * Handler for the Select Contact from cookies mode
	 */
	contactMode_select = function()
	{
		$gate.jqxNavigationBar("setHeaderContentAt", 1, "Step 2</b> &mdash; Select existing contact");
		$("#step2-area > div").hide();
		$("#contact-mode-select #list-select-result", $gate).empty();
		$("#contact-mode-select", $gate).show();

		// If already loaded, only reset the select box - Everything else is prepared
		if(controls_ready['cookie-contacts-select']) {
			$("#cookie-contacts-select", $gate).jqxListBox('clearSelection');
			$("#use-cookie-contact", $gate).hide();

			$("#contact-mode-select .contact-vcard", $gate).empty(); // Empty in preparation to load new contact
		} else {
			// Setup the contact select listbox
			$("#cookie-contacts-select", $gate).jqxListBox({
				theme: cargohub.jqxtheme,
				source: adapter_CookieContacts,
				width: 646, height: 123,
				displayMember: "email", valueMember: "id",
				renderer: function(idx, label, val)
				{
					var con = adapter_CookieContacts.records[idx];
					return con.name+ ' &lt;'+ con.email+ '&gt;';
				}
			})
			/**
			 * Event - A contact is selected - Show the button
			 */
			.on('select', function() {
				$("#use-cookie-contact", $gate).show();
				$("#contact-mode-select #list-select-result", $gate).empty();
			});
			controls_ready['cookie-contacts-select'] = true;
		}

		if(!controls_ready['use-cookie-contact']) {
			$("#use-cookie-contact", $gate).jqxButton({
				theme: cargohub.jqxtheme,
			})
			/**
			 * Use Cookie Contact button - It selects a contact from the existing list.
			 * The Step 2 area is replaced by the contact details (loaded as a Vcard)
			 */
			.on('click', function() {

				$("#use-cookie-contact", $gate).hide();
				var proceed_fn = null;
				self.config.contact = adapter_CookieContacts.records[$("#cookie-contacts-select", $gate).jqxListBox('getSelectedIndex')];

				switch(mawb_outcome) {
				case 'not-found':
					contactMode_ReadOnly();
					break;

				case 'mawb-located':
					$.get("?action=Gate_Op", {
						op: "contact-check",
						data: JSON.stringify({
							"con_id": self.config.contact.id,
							"mawb": checked_mawb
						})
					}, function(r) {
						switch(self.config.contact.type) {
						// Draft contact Selected - Enter create mode, but with a preloaded contact (instead of empty)
						// This allows continuing to edit a contact after refreshing the gate, or when a new
						case 'draft':
							if(!proceed_fn)
								proceed_fn = function() {
									proceed_confirm = true;
									$("#contact-mode-edit .contact-vcard", $gate).load("/global/contact?action=Visual_Card", {
										"id": self.config.contact.id,
										"edit": 1
									}, contactMode_Edit);
								};

							if(r.mawb_outcome == 'no-results') {
								$("#contact-mode-select #list-select-result", $gate).html("<div class='wish-to-submit'><p>A claim for MAWB: "+ checked_mawb+ ", was submitted by another contact and E-mail address.</p><p>Do you wish to submit a new claim from this E-mail?</p><button>Yes, new claim</button></div>");
								$("#contact-mode-select #list-select-result button", $gate).jqxButton({
									theme: cargohub.jqxtheme
								})
								.on('click', proceed_fn);
							} else
								proceed_fn();

							break;
						// Customer account found - The e-mail should be placed in the login form and require the user to log in before any other operation
						case 'isspost':
							if(!proceed_fn)
								proceed_fn = function() {
									proceed_confirm = true;
									contactMode_ReadOnly();
								};

							if(enabled_steps[2])
								reset_Step3();

							if(r.mawb_outcome == 'unique-claim' || r.mawb_outcome == 'multiple-claims') {
								$("#cac-access-mode #cac-info-box", $gate).html("A claim was found which you previously submitted<br /><span class='blue'>To view, check status and update it, you must log-in to your account</blue>");
								r.email = self.config.contact.email;
								r.role = "claim-search";
								mode_CustomerLogin(r);
							} else {
								$("#contact-mode-select #list-select-result", $gate).html("<div class='wish-to-submit'><p>A claim for MAWB: "+ checked_mawb+ ", was submitted by another contact and E-mail address.</p><p>Do you wish to submit a new claim from this E-mail ?</p><button>Yes, new claim</button></div>");
								$("#contact-mode-select #list-select-result button", $gate).jqxButton({
									theme: cargohub.jqxtheme
								})
								.on('click', proceed_fn);
							}
							break;
						// Platform user requires moving on to premium area (Redirect) - After logging in it switches to either submit form or overview mode
						case 'user_self':
							if(enabled_steps[2])
								reset_Step3();

							// TODO: Depending on the following cases different operations should happen:
							// - if MAWB is not found, it redirects to the Submit form ( current version )
							// - if claim is found and is connected to the organization of the "self.config.contact", it redirects to the Overview to open the claim
							// - if claim is found but is not connected to the organization, it shows the name of the connected organizations with a choice of going to login to submit new one or not

							$("#contact-mode-select #list-select-result", $gate).html("<p class='green'>This e-mail belongs to one of our platform users.<br />You must log-in to your account to submit or update your claims</p>");

							if(r.mawb_outcome == 'unique-claim' || r.mawb_outcome == 'multiple-claims') {
								$("#cac-access-mode #cac-info-box", $gate).html("A claim was found which you previously submitted<br /><span class='blue'>To view, check status and update it, you must log-in to your account</blue>");
								r.email = self.config.contact.email;
								r.role = "claim-search";
								init_Step3_UserAccess(r);
							} else {
								$("#contact-mode-select #list-select-result", $gate).html("<p>A claim for MAWB: "+ checked_mawb+ ", was submitted by another contact and E-mail address.</p><p>Do you wish to submit a new claim from this E-mail ?</p><button>Yes, new claim</button>");
								$("#contact-mode-select #list-select-result button", $gate).jqxButton({
									theme: cargohub.jqxtheme
								})
								.on('click', init_Step3_UserAccess);
							}
							break;
						}
					});
					break;
				}
			});
			controls_ready['use-cookie-contact'] = true;
		}
	},

	/**
	 * Handler for E-mail search function
	 * - Depending if the e-mail has been used before or not, it will require login to Customer Account, Platform Account or complete new E-mail
	 * @param string outcome The E-mail search mode behave slightly different if MAWB is found or not
	 */
	mode_EmailSearch = function()
	{
		if(enabled_steps[2])
			reset_Step3("<b>Step 3</b> &mdash; Outcome <span class='fiftycent'>(waiting on step 2)</span>");

		$gate.jqxNavigationBar("setHeaderContentAt", 1, "Step 2</b> &mdash; Enter your E-mail address for verification");
		$("#step2-area > div").hide();
		$("#email-search-mode #email-result", $gate).empty();
		$("#email-search-mode #email-addr", $gate).val("");
		$("#email-search-mode", $gate).show();

		// Show infobox depending on MAWB search outcome
		$("#email-search-mode .infobox", $gate).hide();
		$("#email-search-mode #outcome-"+mawb_outcome, $gate).show();

		if(!controls_ready['email-search']) {
			$("#email-search-mode #email-addr", $gate).on('keyup', function(e) {
				if(e.keyCode == 13 && $(this).val().length > 5)
					$("#email-search-mode #email-search", $gate).trigger('click');
			});

			$("#email-search-mode #email-search", $gate).jqxButton({
				theme: cargohub.jqxtheme
			})
			/**
			 * When Email "Search" button is pressed, send the E-mail to the server to find if it exists in the database or not
			 */
			.on('click', function() {

				$("#email-search-mode #email-result", $gate).empty();

				// E-mail validity check
				var email = $("#email-search-mode #email-addr").val();
				if(email.length < 5) {
					if(enabled_steps[2])
						reset_Step3("<b>Step 3</b> &mdash; Outcome <span class='fiftycent'>(waiting on step 2)</span>");
					return $("#email-search-mode #email-result", $gate).html("<p class='red'>Please enter your e-mail address</p>");
				}
				if(!validate_email(email)) {
					if(enabled_steps[2])
						reset_Step3("<b>Step 3</b> &mdash; Outcome <span class='fiftycent'>(waiting on step 2)</span>");
					return $("#email-search-mode #email-result", $gate).html("<p class='red'>This is not a valid e-mail address</p>");
				}

				// So far so good, send the E-mail, the outcome and MAWB number (again) to the server to check (If outcome is mawb-located, the e-mail is verified against record submitter contact)
				$.get("?action=Gate_Op", {
					op: "email-check",
					data: JSON.stringify({
						"email": email,
						"outcome": mawb_outcome,
						"mawb": checked_mawb
					})
				}, function(r) {
					var proceed_fn = null;
					switch(r.outcome) {
					// Not found - Switch directly to Edit, in "Create" mode, to enter new contact information
					case 'not-found':
						proceed_fn = function() {
							$("#contact-mode-edit .contact-vcard", $gate).load("/global/contact?action=Visual_Card", {
								"init_contact": {
									"email": email
								},
								"edit": 1
							}, contactMode_Edit);
						};
					// ... bleed

					// Draft contact returned - Enter create mode, but with a preloaded contact (instead of empty)
					// This allows continuing to edit a contact after refreshing the gate, or when a new
					case 'draft-contact':
						if(!proceed_fn)
						proceed_fn = function() {
							proceed_confirm = true;
							$("#contact-mode-edit .contact-vcard", $gate).load("/global/contact?action=Visual_Card", {
								"id": r.id,
								"edit": 1
							}, contactMode_Edit);
						};

						if(mawb_outcome == 'mawb-located') {
							$("#email-search-mode #email-result", $gate).html("<p>A claim for MAWB: "+ checked_mawb+ ", was submitted by another contact and E-mail address.</p><p>Do you wish to submit a new claim from this E-mail ?</p><button>Yes, new claim</button>");
							$("#email-search-mode #email-result button", $gate).jqxButton({
								theme: cargohub.jqxtheme
							})
							.on('click', proceed_fn);
						} else
							proceed_fn();
						break;
					// Customer account found - The e-mail should be placed in the login form and require the user to log in before any other operation
					case 'customer-account':
						if(enabled_steps[2])
							reset_Step3();

						proceed_fn = function() {
							proceed_confirm = true;
							r.email = email;
							r.role = "claim-search";
							mode_CustomerLogin(r);
						};

						if(r.mawb_outcome == 'unique-claim' || r.mawb_outcome == 'multiple-claims')
							$("#cac-access-mode #cac-info-box", $gate).html("A claim was found which you previously submitted<br /><span class='blue'>To view, check status and update it, you must log-in to your account</blue>");
						else
							$("#cac-access-mode #cac-info-box", $gate).html("This E-mail is already associated with an account.<br /><span class='blue'>To submit your new claim, you must log-in to your account</blue>");

					// ... bleed

					// Platform user requires moving on to premium area (Redirect) - After logging in it switches to either submit form or overview mode
					case 'platform-user':

						if(!proceed_fn)
							proceed_fn = function() {
								proceed_confirm = true;
								setTimeout(init_Step3_UserAccess.bind(this, r), 500);
							};

						if(enabled_steps[2])
							reset_Step3();

						if(r.mawb_outcome == 'unique-claim' || r.mawb_outcome == 'multiple-claims') {
							// Claim found, belongs to the E-mail entered, which is either a staff user or customer account
							if(r.outcome == 'platform-user')
								$("#email-search-mode #email-result", $gate).html("<p class='green'>This e-mail belongs to one of our platform users.<br />You must log-in to your account to access your claim</p>");
							proceed_fn();
						} else if(r.mawb_outcome == 'no-results') {
							$("#email-search-mode #email-result", $gate).html("<div class='wish-to-submit'><p>A claim for MAWB: "+ checked_mawb+ ", was submitted by another contact and E-mail address.</p><p>Do you wish to submit a new claim from this E-mail ?</p><button>Yes, new claim</button></div>");
							$("#email-search-mode #email-result button", $gate).jqxButton({
								theme: cargohub.jqxtheme
							})
							.on('click', function() {
								if(r.outcome == 'platform-user')
									$("#email-search-mode #email-result", $gate).html("<p class='green'>This e-mail belongs to one of our platform users.<br />You must log-in to your account to submit a new claim</p>");
								proceed_fn();
							});
						} else {
							if(r.outcome == 'platform-user')
								$("#email-search-mode #email-result", $gate).html("<p class='green'>This e-mail belongs to one of our platform users.<br />You must log-in to your account to submit a new claim</p>");
							proceed_fn();
						}


						// TODO: if claim is found and is connected to the organization of the "self.config.contact", it redirects to the Overview to open the claim
						break;

					// Detected an E-mail which is not supposed to be used as submitter of claims
					case 'invalid-address':
						if(enabled_steps[2])
							reset_Step3("<b>Step 3</b> &mdash; Outcome <span class='fiftycent'>(waiting on step 2)</span>");
						return $("#email-search-mode #email-result", $gate).html("<p class='red'>This E-mail address cannot be used to submit or check claims!</p>");
					}

				}, 'json')
				.fail(cargohub.its.http_fail);
			});
			controls_ready['email-search'] = true;
		}

		// Auto-fill / Auto-focus
		setTimeout(function() {
			// Is there a preset E-mail ? Fill in the field
			if(self.config.autofill && self.config.autofill.email) {
				$("#email-search-mode #email-addr", $gate).val(self.config.autofill.email);
				delete self.config.autofill.email; // Only used once, after that manual e-mail entry is required
				$("#email-search-mode #email-search", $gate).trigger('click');
			} else
				$("#email-search-mode #email-addr", $gate).focus();
		}, 200);
	},

	/**
	 * Present the Customer Account login form + Password recovery option
	 * Once logged in "self.config.contact" is loaded with the new data and previous outcome of E-mail/MAWB search is handled
	 * @param object cfg Should contain "outcome" as type of contact for the specified e-mail
	 */
	mode_CustomerLogin = function(cfg)
	{
		if(enabled_steps[2])
			reset_Step3("<b>Step 3</b> &mdash; Outcome <span class='fiftycent'>(waiting on step 2)</span>");

		$gate.jqxNavigationBar("setHeaderContentAt", 1, "Step 2</b> &mdash; Please log-in to your customer account");
		$("#step2-area > div").hide();

		$("#cac-access-mode #cac-login-result", $gate).empty();
		$("#cac-access-mode input[name=email]", $gate).val(cfg.email);
		$("#cac-access-mode input[name=password]", $gate).val("");
		$("#cac-access-mode", $gate).show();

		if(!controls_ready['cac-access']) {
			$("#cac-access-mode #cac-login", $gate).jqxButton({
				theme: cargohub.jqxtheme,
			});

			// When submitting the form via the button, credentials are sent via Ajax to the Contact Access controller
			$("#cac-access-mode form", $gate).ajaxForm({
				dataType: 'json',
				beforeSubmit: function(formData, jqForm, options)
				{
					var f = {};
					for(var c=0; c< formData.length; c++)
						f[formData[c].name] = formData[c].value;

					// check if the e-mail field is an actual e-mail
					// not sure if this e-mail validation string actually works well ?
					if(!validate_email(f['email'])) {
						$("#cac-access-mode #cac-login-result", $gate).html("<p class='red'>You must enter valid e-mail address</p>");
						return false;
					}

					// check if the fields are not empty
					if(!f['password']) {
						$("#cac-access-mode #cac-login-result", $gate).html("<p class='red'>Please enter your assigned password</p>");
						return false;
					}

//					if(f['password'].length != 10) {
//						$("#cac-access-mode #cac-login-result", $gate).html("<p class='red'>The automatic Customer Account passwords have<br />" +
//							"exactly 10 characters. You have entered "+f['password'].length+".</p>");
//						return false;
//					}

					if(f['password'].length < 8) {
						$("#cac-access-mode #cac-login-result", $gate).html("<p class='red'>The automatic Customer Account passwords have<br />" +
							"exactly 8 characters. You have entered "+f['password'].length+".</p>");
						return false;
					}

					return true;
				},
				success: function(x)
				{
					// Contact mode changes, we are now logged in
					self.config.contact_mode = 'cac';

					// On success, all controls will be disabled
					$("#cac-access-mode input").prop('disabled', "disabled");
					$("#cac-access-mode button").jqxButton({disabled: true});

					// This Step3 will auto-redirect to either the detected claim (track id is available in cfg), for updating, or to submit new claim (Account is already logged in)

					switch(cfg.role) {
					case 'edit-contact':
						return $("#contact-mode-edit .contact-vcard", $gate).load("/global/contact?action=Visual_Card", {
							"id": self.config.contact.id,
							"edit": 1
						}, contactMode_Edit);
					case 'claim-search':
						return init_Step3_CAC(cfg);
					default:
//						dd("Unrecognized reason to login.");
					}

				},
				error: function(xhr, r) {
					if(!xhr)
						$("#cac-access-mode #cac-login-result", $gate).html("<p class='red'>No response from server. <a href='mailto:support@cargohub.nl'>Please contact support</a></p>");
					else if(xhr.status != 200)
						$("#cac-access-mode #cac-login-result", $gate).html("<p class='red'><b>Error code: "+ xhr.status+ "</b><br />"+ xhr.statusText+ "</p>");
					// 200 might only mean nothing was returned (should not show an error - Just silently "do nothing")
				}
			});

			// The "Send Password" button
			$("#cac-access-mode #send-cac-password", $gate).jqxButton({
				// theme: cargohub.jqxtheme,
				
			})
			.on('click', function() {
				// At the moment, it only sends existing password, not a new one.
//				if(!confirm("Are you sure you want to obtain a new password?"))
//					return;

				$("#cac-access-mode #send-cac-password", $gate).jqxButton({disabled: true});
				$.post("/contact/access?action=Send_Contact_Password", {
					email: cfg.email
				}, function(r) {
					$("#cac-access-mode #cac-login-result", $gate).html("<p class='blue'>Your new password has been sent by E-mail.<br /><b>Please check your inbox</b></p>");
				})
				.fail(cargohub.its.http_fail);
			});

			controls_ready['cac-access'] = true;
		}

		// Auto-fill / Auto-focus
		setTimeout(function() {
			// Is there a preset Password ? Fill it in and do login
			if(self.config.autofill && self.config.autofill.password) {
				$("#cac-access-mode input[name=password]", $gate).val(self.config.autofill.password);
				delete self.config.autofill.password; // Only used once, after that manual e-mail entry is required
				$("#cac-access-mode #cac-login", $gate).trigger('click');
			} else
				$("#cac-access-mode input[name=password]", $gate).focus();
		}, 200);

	},

	/**
	 * Handler for viewing a selected or previously edited contact. This is usually shown as Step 3 appears
	 */
	contactMode_ReadOnly = function()
	{
		if(!self.config.contact)
			return cargohub.alert("Attempting to proceed with a contact that doesn't exist.");

		$gate.jqxNavigationBar("setHeaderContentAt", 1, "Step 2</b> &mdash; Your contact information");
		$("#step2-area > div").hide();
		$("#contact-mode-readonly", $gate).show();

		// Alternate operations - Depending if contact is directly editable or not
		if(self.config.contact.editable) {
			$("#contact-edit-restricted", $gate).hide();
			$("#contact-edit-allowed", $gate).show();

			if(!controls_ready['contact-start-edit']) {
				$("#edit-existing-contact", $gate).jqxButton({
					theme: cargohub.jqxtheme,
				})
				.on('click', function() {
					$("#contact-mode-edit .contact-vcard", $gate).load("/global/contact?action=Visual_Card", {
						"id": self.config.contact.id,
						"edit": 1
					}, contactMode_Edit);
				});
				controls_ready['contact-start-edit'] = true;
			}
		} else {
			$("#contact-edit-allowed", $gate).hide();
			$("#contact-edit-restricted", $gate).show();

			if(!controls_ready['cac-access-button']) {
				// The contact access button for selected contacts - Switches to Contact Account login form
				$("#contact-mode-readonly #to-cac-access", $gate).jqxButton({
					theme: cargohub.jqxtheme
				})
				.on('click', function() {

					$("#cac-access-mode #cac-info-box", $gate).html("<span class='blue'>To edit your contact information, you must first log-in.</blue>");
					mode_CustomerLogin({
						email: self.config.contact.email,
						role: "edit-contact"
					});
				});
				controls_ready['cac-access-button'] = true;
			}
		}

		// Step 3 is ready - We have the AWB number and a contact is selected
		$("#contact-mode-readonly .contact-vcard", $gate).empty();
		$("#contact-mode-readonly .contact-vcard", $gate).show().load("/global/contact?action=visual_card", {
			"id": self.config.contact.id
		}, init_Step3_Submit);
	},

	/**
	 * When logged in as either user or customer account, this mode is shown
	 */
	contactMode_LoggedIn = function(cfg)
	{
		if(enabled_steps[2])
			reset_Step3("<b>Step 3</b> &mdash; Outcome <span class='fiftycent'>(waiting on step 2)</span>");

		$gate.jqxNavigationBar("setHeaderContentAt", 1, "Step 2</b> &mdash; Welcome back to CargoClaims");
		$("#step2-area > div").hide();
		$("#contact-mode-loggedin", $gate).show();

		// Switch to editing contact details (always available when logged in)
		// Currently disabled as it allows some incorrect contact editing
/*		if(!controls_ready['to-contact-edit']) {
			$("#contact-mode-loggedin #to-contact-edit", $gate).jqxButton({
				theme: cargohub.jqxtheme
			})
			.on('click', function() {
				return $("#contact-mode-edit .contact-vcard", $gate).load("/global/contact?action=Visual_Card", {
					"id": self.config.contact.id,
					"edit": 1
				}, contactMode_Edit);
			});
			controls_ready['to-contact-edit'] = true;
		}*/

		$("#contact-mode-loggedin .contact-vcard", $gate).show().load("/global/contact?action=visual_card", {
			"id": self.config.contact.id
		}, function() {
			if(!cfg)
				return; // FIXME: Handle the situation where there is no config
			switch(cfg.mawb_outcome) {
			// Automatic redirect on existing claim(s)?
			case 'unique-claim':
			case 'multiple-claims':
				setTimeout(function() {
					if(self.config.contact_mode == 'cac')
						init_Step3_CAC(cfg);
					else
						init_Step3_User(cfg);
				}, 500);
				break;
			// Found claim but is not owned?
			case 'not-owned':
				$("#contact-mode-loggedin #loggedin-info", $gate).html("<div class='wish-to-submit'><hr /><h2>The found claim with MAWB <b>"+ checked_mawb+ "</b> was not submitted from this account.</h2><p class='blue'>Do you wish to submit another claim with the same MAWB from your account?</p><button>Yes, new claim</button></div>").show();
				$("#contact-mode-loggedin #loggedin-info button", $gate).jqxButton({
					theme: cargohub.jqxtheme
				})
				.on('click', init_Step3_Submit);
				break;
			// No claim found, allow submit directly
			case 'not-found':
				$("#contact-mode-loggedin #loggedin-info", $gate).hide();
				setTimeout(init_Step3_Submit, 500);
				break;
			}
		});
	},

	/**
	 * Handler for the Edit (or Create) Contact mode (contact form)
	 */
	contactMode_Edit = function()
	{
		if(enabled_steps[2])
			reset_Step3("<b>Step 3</b> &mdash; Outcome <span class='fiftycent'>(waiting on step 2)</span>");

		if(self.config.contact_mode == 'cac' || self.config.contact_mode == 'user') {
			$gate.jqxNavigationBar("setHeaderContentAt", 1, "Step 2</b> &mdash; Editing your contact information");
			$("#contact-mode-edit .info-edit-new-contact", $gate).hide();
		} else {
			$gate.jqxNavigationBar("setHeaderContentAt", 1, "Step 2</b> &mdash; Create new contact");
			$("#contact-mode-edit .info-edit-new-contact", $gate).show();
		}

		$("#step2-area > div").hide();
		$("#contact-mode-edit", $gate).show();

		// Editor needs to be setup every time, as the content gets reloaded
		cargohub.components.contact_editor.setup({
			container: $("#contact-mode-edit", $gate),
			params: {
				mandatory_fields: ['company_name'],
			}
		}, self);

		// The save contact button - Begins by resetting it, since new data comes.
		$("#save-contact", $gate).jqxButton({
			theme: cargohub.jqxtheme,
		})
		.off('click')
		/**
		 * Enter New Contact button(s) - Ignores any contact from the list
		 * The Step 2 area is replaced by the contact form, allowing the visitor to enter new information
		 */
		.on('click', function() {
			var status = cargohub.components.contact_editor.check_Status(), $loaded = $("#contact-mode-edit #vcard-loaded-id", $gate);
			self.config.contact = cargohub.components.contact_editor.read_vCard();
			self.config.contact.editable = true; // if it was editable, it continues to be editable
			if(status.valid) {
				// Save the draft contact to the database
				$.post("/global/contact?action=Save_Contact", {
					id: $loaded.length? $loaded.val(): 0,	// Also allows updating draft contacts
					name: self.config.contact.name,
					email: self.config.contact.email,
					xdata: JSON.stringify(self.config.contact.xdata),
					type: "draft", // Draft contact will be used in the submit form - It's converted to "isspost" when the actual record is created
					role: "submitter"
				}, function(r) {
					// Update ID (to the one returned, might be existing or new)
					self.config.contact.id = parseInt(r.id);
					if(self.config.contact_mode == 'cac' || self.config.contact_mode == 'user')
						// Fuck it, taking the shortcut - Triggering MAWB check again after saving contact edit (basically, starting over)
						$("#awb-check", $gate).trigger('click');
					else {
						// No bloody clue if this is ok
						contactMode_ReadOnly();
					}
				})
				.fail(cargohub.its.http_fail);

			} else

				return cargohub.alert("Please review your contact information. Some fields might be missing or with errors");
		});
	},

	/**
	 * Step2 depends on the result of MAWB search, and if a customer account or platform user is already logged in
	 */
	init_Step2 = function(r)
	{
		if(enabled_steps[2])
			reset_Step3("<b>Step 3</b> &mdash; Outcome <span class='fiftycent'>(waiting on step 2)</span>");

		mawb_outcome = r.mawb_outcome;
		switch(mawb_outcome) {
		// Error from the server, perhaps an incorrect AWB (like invalid airline prefix
		case 'error':
			return mawb_result_error(r.message);
		// The AWB is not found, it is possible to proceed to submit new record
		case 'not-found':
			// Begin by hiding any existing areas - One of them will be shown depending on outcome
			$("#mawb-result").html("<p>No cargo claim has been submitted yet for MAWB: <b>"+ checked_mawb +"</b><p class='green thick'>You may submit your claim in the next steps:</p>");

			// Depending on the contact mode sent by the server, different ways to select who is the submitter
			switch(self.config.contact_mode) {
			// Brand new visitor, nothing known - Ask to verify e-mail address
			case 'create':
				mode_EmailSearch();
				break;
			// At least one contact was found in cookies. Construct a jqxListbox to allow the user to simply pick previous contacts
			case 'select':
				contactMode_select();
				break;
			// When logged in either with the Customer Account or as Premium User, show the contact info - It will be used as the submitter of the new claim
			case 'cac':
			case 'user':
				contactMode_LoggedIn({'mawb_outcome': mawb_outcome});
				break;
			default:
				// Error, no mode was selected?
				return cargohub.alert("An error occured while preparing contact information. Try reloading the page");
			}

			break;
		// One or more claims were found for the entered AWB - An E-mail is necessary to verify ownership, and proceed with further actions
		case 'mawb-located':
			proceed_confirm = false;
			// Begin by hiding any existing areas - One of them will be shown depending on outcome
			var mawb_search_result_message = "<p class='green'>Claim with MAWB: <b>"+ checked_mawb +"</b> &mdash; was found.</p>";
			if(self.config.contact_mode == 'create' || self.config.contact_mode == 'select')
				mawb_search_result_message+= "<p>Continue to next step - Email verification:</p>";

			$("#mawb-result").html(mawb_search_result_message);

			// Depending on the contact mode sent by the server, different ways to select who is the submitter
			// -- This outcome is only returned when no account is logged in
			switch(self.config.contact_mode) {
			// Brand new visitor, nothing known - Display the contact form
			case 'create':
				mode_EmailSearch();
				break;
			// At least one contact was found in cookies. Construct a jqxListbox to allow the user to simply pick previous contacts
			case 'select':
				contactMode_select();
				break;
			default:
				// Error, no mode was selected?
				return cargohub.alert("An error occured while preparing contact information. Try reloading the page");
			}
			break;
		// One or more claims were found but none of them were submitted by the logged in account.
		case 'not-owned':
			$("#mawb-result").html("<p class='red'>A claim with Master AWB: <b>"+ checked_mawb+ "</b> &mdash; was already submitted by someone else.</p>");

			contactMode_LoggedIn(r); // Activate dupe confirm
			break;
		// An unique claim was found for the specified MAWB, belonging to the logged in account - The only possible operation is to go to claim view/update
		case 'unique-claim':
			$("#mawb-result").html("<p class='green'>Your claim with Master AWB: <b>"+ checked_mawb+ "</b> was found &mdash; <b>redirecting</b> to your claim in 5 seconds.</p>");
			contactMode_LoggedIn(r);
			break;
		// ... bleed
		// Multiple claims under specified MAWB were found submitted by the logged in account - The nasty result of this shield not being available before.
		// Redirect to claim list (for customer accounts) or to main grid for users - This also denies submitting new claim under this MAWB number
		case 'multiple-claims':
			$("#mawb-result").html("<p class='orange'>Multiple claims Master AWB: <b>"+ checked_mawb+ "</b> were found &mdash; <b>redirecting</b> to your overview in 5 seconds.</p>");
			contactMode_LoggedIn(r);
			break;
		}

		// In either MAWB result sucessful mode, the "New contact" button must be initialized
		if(!controls_ready['enter-new-contact']) {
			$(".enter-new-contact", $gate).jqxButton({
				theme: cargohub.jqxtheme,
			})
			/**
			 * Enter New Contact button(s) - Ignores any contact from the list
			 * The Step 2 area is replaced by the contact form, allowing the visitor to enter new information
			 */
			.on('click', function() {
				mode_EmailSearch();
			});
			controls_ready['enter-new-contact'] = true;
		}

		// Expand step2
		$gate.jqxNavigationBar('enableAt', 1);
		$gate.jqxNavigationBar('expandAt', 1);
		enabled_steps[1] = true;
	},

	/**
	 * Final step - When ready to redirect to submit form
	 */
	init_Step3_Submit = function()
	{
		$("div.wish-to-submit", $gate).empty(); // No need to show this after it has already been confirmed
		$("#step3-area > div", $gate).hide();
		$gate.jqxNavigationBar("setHeaderContentAt", 2, "Step 3</b> &mdash; Please read and accept our terms of use");
		$("#outcome-disclaimer", $gate).show();

		if(!controls_ready['ready-to-submit']) {
			$("#accept-disclaimer", $gate).jqxCheckBox({
				theme: cargohub.jqxtheme,
				checked: false
			})
			/**
			 * Event - When the checkbox is checked, the submit button becomes enabled
			 */
			.on('checked', function() {
				$("#to-infinity-and-beyond", $gate).jqxButton({disabled: false});
				$("#tos", $gate).fadeOut();
			})
			.on('unchecked', function() {
				$("#to-infinity-and-beyond", $gate).jqxButton({disabled: true});
			});

			$("#to-infinity-and-beyond", $gate).jqxButton({
				theme: cargohub.jqxtheme,
				disabled: true
			})
			/**
			 * Use Cookie Contact button - It selects a contact from the existing list.
			 * The Step 2 area is replaced by the contact details (loaded as a Vcard)
			 */
			.on('click', function() {
				var autofill_data = {
					"mawb": checked_mawb
				};
				if(self.config.contact)
					autofill_data.con_id = self.config.contact.id;
				// Send the data before redirection
				$.post("?action=Gate_Op", {
					op: "setup-form-autofill",
					data: JSON.stringify(autofill_data)
				}, function(r) {
					// To Infinity and Beyond !
					document.location = "/issue?action=form&key=cargoclaim";
				});

			});

			controls_ready['ready-to-submit'] = true;
		}

		// Expand step3
		$gate.jqxNavigationBar('enableAt', 2);
		$gate.jqxNavigationBar('expandAt', 2);
		enabled_steps[2] = true;
	},

	/**
	 * Final step for staff users - Detected that user login is required - Redirecting to Account login (Not implemented within the shield itself)
	 * - However, entered AWB is saved, so when presented with the submit form the AWB is already there
	 */
	init_Step3_UserAccess = function(cfg)
	{
		$("div.wish-to-submit", $gate).empty(); // No need to show this after it has already been confirmed
		$("#step3-area > div").hide();
		$gate.jqxNavigationBar("setHeaderContentAt", 2, "Step 3</b> &mdash; <b>Redirecting</b> to platform account entrance in 5 seconds");
		$("#outcome-user", $gate).show();

		var redirect_uri = null;
		if(cfg) {
			switch(cfg.mawb_outcome) {
			case 'unique-claim':
				redirect_uri = "/record/process:"+ cfg.track_id+ "#panels";
				break;
			case 'multiple-claims':
				redirect_uri = "/issue/list"; // Not, it's not possible currently to push a value to the grid, so multiple claims on same AWB are problematic, as the gate cannot redirect to them
				break;
			case 'no-results':
				redirect_uri = "/issue?action=form&key=cargoclaim&mawb="+ checked_mawb;
				break;
			}
		} else
			redirect_uri = "/issue?action=form&key=cargoclaim&mawb="+ checked_mawb;

		// FIXME: This should save the form parameters without placing them in the redirect URI (by using form-autofill in cache)
		$.post("?action=Gate_Op", {
			op: "user-redirect-url",
			data: JSON.stringify({"uri": redirect_uri})
		}, function(r) {
			// Nothing here (but if this call failed it will not redirect properly)
		});

		var seconds_counter = 5;
		$("#outcome-user .seconds-counter", $gate).text(seconds_counter);
		countdown = setInterval(function() {
			seconds_counter--;
			if(seconds_counter == 0) {
				clearInterval(countdown);
				document.location = "/access?action=entrance";
			} else
				$("#outcome-user .seconds-counter", $gate).text(seconds_counter);
		}, 1000);

		// Expand step3
		$gate.jqxNavigationBar('enableAt', 2);
		$gate.jqxNavigationBar('expandAt', 2);
		enabled_steps[2] = true;
	},

	/**
	 * Final step for successful Customer Account login
	 * - Depending on "mawb_outcome" in cfg, it redirects to either an existing claim, or to submit form
	 */
	init_Step3_CAC = function(cfg)
	{
		$("#cac-access-mode #cac-info-box", $gate).html("<h2>Welcome back to CargoClaims !</h2>");

		$("#step3-area > div").hide();
		var redirect_uri = null, outcome_element = null;

		switch(cfg.mawb_outcome) {
		// System found the exact claim - Redirect to view/update module
		case 'unique-claim':
			$("#cac-access-mode #cac-login-result", $gate).html("<p class='green'><b>Authentication successful</b></p><p>You will be automatically redirected to your claim.</p>");
			$gate.jqxNavigationBar("setHeaderContentAt", 2, "Step 3</b> &mdash; Redirecting to claim "+ cfg.track_id+ " in 5 seconds");
			redirect_uri = "/issue&action=view?track="+ cfg.track_id;
			outcome_box = "#outcome-claim-view";

			break;
		// System found multiple claims with same MAWB from the same submitter - This is very unfortunate
		case 'multiple-claims':
			$("#cac-access-mode #cac-login-result", $gate).html("<p class='green'><b>Authentication successful</b><br />You will be automatically redirected to your account.</p>");
			$gate.jqxNavigationBar("setHeaderContentAt", 2, "Step 3</b> &mdash; Redirecting to your customer account in 5 seconds");
			// TODO: Resolve MAWB autofill in CaC grid search

			redirect_uri = "/contact/account#issues";
			outcome_box = "#outcome-cac-grid";
			break;
		// While there are claims, they were not submitted by the account which is logged in (or just logged in)
		case 'no-results':
			var result_html = "<p class='green'><b>Authentication successful</b></p>";
			if(!proceed_confirm)
				result_html += "<p>However, it seems that the verified claim was not submitted from your account</p><p>Do you wish to submit a different claim for this MAWB ?</p><button>Yes, new claim</button>";
			$("#cac-access-mode #cac-login-result", $gate).html(result_html);
			if(!proceed_confirm) {
				$("#cac-access-mode #cac-login-result button", $gate).jqxButton({
					theme: cargohub.jqxtheme
				})
				.on('click', init_Step3_Submit);
			} else
				setTimeout(init_Step3_Submit, 500);
			return;

		// No MAWB outcome, which means claim was not found - go to standard Submit (with the disclaimer)
		// - This one doesn't have a timeout because a checkbox needs to be clicked
		case 'not-found':
		default:
			$("#cac-access-mode #cac-login-result", $gate).html("<p class='green'><b>Authentication successful</b></p>");
			setTimeout(init_Step3_Submit, 500);
			return;
		}
		$(outcome_box+ " a", $gate).prop('href', redirect_uri);
		$(outcome_box, $gate).show();

		// Redirect countdown
		var seconds_counter = 5;
		$(outcome_box+ " .seconds-counter", $gate).text(seconds_counter);
		countdown = setInterval(function() {
			seconds_counter--;
			if(seconds_counter == 0) {
				clearInterval(countdown);
				document.location = redirect_uri;
			} else
				$(outcome_box+ " .seconds-counter", $gate).text(seconds_counter);
		}, 1000);

		// Step3 ready, expand
		$gate.jqxNavigationBar('enableAt', 2);
		$gate.jqxNavigationBar('expandAt', 2);
		enabled_steps[2] = true;
	},

	/**
	 * Similar with init_Step3_CAC, but this one is for platform users
	 * All redirects go to staff area (Also, this doesn't include any notifications, since it's only possible to get here for an already logged in account)
	 */
	init_Step3_User = function(cfg)
	{
		$("#step3-area > div").hide();
		var redirect_uri = null, outcome_element = null;

		$("#step3-area > div").hide();
		var redirect_uri = null, outcome_element = null;

		switch(cfg.mawb_outcome) {
		// System found the exact claim - Redirect to claim overview
		case 'unique-claim':
			$gate.jqxNavigationBar("setHeaderContentAt", 2, "Step 3</b> &mdash; Redirecting to claim "+ cfg.track_id+ " in 5 seconds");
			redirect_uri = "/record/process:"+ cfg.track_id+ "#panels";
			outcome_box = "#outcome-claim-view";

			break;
		// System found multiple claims with same MAWB from the same submitter - This is very unfortunate
		case 'multiple-claims':
			$gate.jqxNavigationBar("setHeaderContentAt", 2, "Step 3</b> &mdash; Redirecting to List and search records in 5 seconds");
			// TODO: It should be possible to inject search parameters into the grid - Figure out how

			redirect_uri = "/issue/list";
			outcome_box = "#outcome-user-grid";
			break;
		// No MAWB outcome, which means claim was not found - go to standard Submit (with the disclaimer)
		// - This one doesn't have a timeout because a checkbox needs to be clicked
		default:
			setTimeout(init_Step3_Submit, 500);
			return
		}
		$(outcome_box+ " a", $gate).prop('href', redirect_uri);
		$(outcome_box, $gate).show();

		// Redirect countdown
		var seconds_counter = 5;
		$(outcome_box+ " .seconds-counter", $gate).text(seconds_counter);
		countdown = setInterval(function() {
			seconds_counter--;
			if(seconds_counter == 0) {
				clearInterval(countdown);
				document.location = redirect_uri;
			} else
				$(outcome_box+ " .seconds-counter", $gate).text(seconds_counter);
		}, 1000);

		// Step3 ready, expand
		$gate.jqxNavigationBar('enableAt', 2);
		$gate.jqxNavigationBar('expandAt', 2);
		enabled_steps[2] = true;
	},

	/**
	 * Resets step 3, in preparation for a different outcome
	 */
	reset_Step3 = function(header_text) {
		$("#step3-area > div").hide();
		if(header_text)
			$gate.jqxNavigationBar("setHeaderContentAt", 2, header_text);
		$gate.jqxNavigationBar('collapseAt', 2);
		$gate.jqxNavigationBar('disableAt', 2);
		enabled_steps[2] = false;

		// If we have a countdown in progress, cancel it
		if(countdown) {
			clearInterval(countdown);
			countdown = null;
		}
	}
	;

	this._public({
		/**
		 * Section initialization
		 */
		init: function() {
			if(!self.config)
				return cargohub.its.init_critical_error("JavaScript frontend did not receive any configuration");
			if(!self.config.contact)
				self.config.contact = {};

			cargohub.components.contact_editor.fields_structure = self.config.vcard_fields_structure;

			if(self.config.gate_width) {
				$('#gate-container').css('width', self.config.gate_width);
			}

			// Initialize jqxNavigationBar that contains the gate steps
			$gate = $('#gate-cargoclaim').jqxNavigationBar({
				theme: cargohub.jqxtheme,
//				width: self.config.gate_width? self.config.gate_width: 'auto',
				arrowPosition: "left",
				expandMode: "multiple",
				expandedIndexes: [0]
			});

			// Begin with steps 2 and 3 disabled, they will be enabled after returning information from the server
			$gate.jqxNavigationBar('disableAt', 1);
			$gate.jqxNavigationBar('disableAt', 2);

			init_Step1();

			// Add tooltips to objects on the main page. Extra tooltips might be added inside tabs
			$(".info-tip").each(cargohub.components.ui.add_Tooltip);
		}
	});
        
        
        $('div.header > div.header-bar > a').hide(); // header design issue fix for cargoclaim

});