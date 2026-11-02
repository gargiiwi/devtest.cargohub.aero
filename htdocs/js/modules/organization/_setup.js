/**
 * Module "Organization Manager", tab "Setup"
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @version 2019.09.23
 */
cargohub.organization_setup = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,
		master = cargohub.organization_manager,

//=== Data Sources for selectors ===//

	// Customer status
	status_source = [
		{k: 'placeholder', text: "Non-member organization"},
		{k: 'trial', text: "Trial: Has active users, not paying"},
		{k: 'payer', text: "Paying customer, active users"},
		{k: 'enterprise', text: "Enterprise-class customer"},
		{k: 'internal', text: "Cargohub internal purposes"}
	],

	// Record modules available for this organization
	ist_source = new $.jqx.dataAdapter({
		url: "/manager/organization?action=get_issuetypes",
		datatype: "json",
		datafields: [
			{name: "id", type: "int"},
			{name: "name"},
			{name: "key"},
			{name: "enabled", type: "int"}
		],
		id: "id"
	}),

	domains_source = new $.jqx.dataAdapter({
		url: "/manager/organization?action=get_domains&quickmode=1",
		datatype: "json",
		datafields: [
			{name: "domain"}
		],
		id: "domain"
	}),

	// TODO: Replace this with the source_SelfCtx (adapted for the fake entry "0")
	contacts_source = new $.jqx.dataAdapter({
		url: "/manager/organization?action=get_contacts&type=org_self&quickmode=1&unshift=-%20No%20visible%20primary%20-",
		datatype: "json",
		datafields: [
			{ name: 'id' },
			{ name: 'email' },
			{ name: 'name' }
		],
		id: 'id'
	}),

	// Templates of type "View" used for document to page generation
	templates_source = new $.jqx.dataAdapter({
		url: "/global/operations?action=Get_Templates",
		data: {
			role: "view",						// "print" templates used as document source templates
			include_default: false,
			org_id: master.config.id
		},
		datatype: "json",
		datafields: [
			{name: 'id', type: 'int'},
			{name: 'description', type: 'string'}
		],
		id: "id"
	}),

	/**
	 * Initialize the "Organization Setup" sub-module toolbar
	 */
	init_Toolbar = function()
	{
//		var tpl_data = {_itc: cargohub.input_theme_classes};

		$("#tab_setup .toolbar button")
		.jqxButton({ theme: cargohub.jqxtheme })
		/**
		 * Event: Toolbar button is clicked
		 */
		.on('click', function()
		{
			switch($(this).data('op')) {
			// Submit organization information with an ajax request
			case 'org-update':
				$("#tab_setup form").ajaxSubmit({
					dataType: 'json',
					beforeSubmit: function(formData, jqForm, options)
					{
						// setup the field checking object
						var f = {};
						for(c=0; c< formData.length; c++)
							f[formData[c].name] = formData[c].value;

						if(f['org[name]'].length < 5)
							return cargohub.its.alert("The organization must have a name (minimum 5 characters)", "Warning", "error.png");
//                                                    iwi commented for manager will be not mandatory anymore start
//						if(master.config.count.users > 0 && !f['org[manager_id]'])
//							return cargohub.its.alert("Please choose the organization manager!", "Warning", 'error.png');
//                                                    iwi commented for manager will be not mandatory anymore end

						// a switchbutton doesn't provide a value to the form, so add them as hidden values
						formData.push({name: "org[features][active]", value: $('#tab_setup #features-org_active').jqxSwitchButton('checked')});
						formData.push({name: "org[features][real_attach]", value: $('#tab_setup #features-real_attachments').jqxSwitchButton('checked')});

						var ist2org = $('#tab_setup #ist2org').val();
						if(ist2org.length == 0)
							return cargohub.its.alert("Please enable at least one record type for this organization.", "Warning", "error.png");

						formData.push({name: "ist2org", value: ist2org});

						return true;
					},
					success: function(x)
					{
						// when completed successfully, refresh the page
						if(x.success) {
							document.location.reload();
						} else {
							cargohub.its.alert(x.reason, "Message from server", "events.png");
						}
					}
				});
				break;
			}
		});
	},

	/**
	 * Initialize the controls present on the tab (Primary contact, General Manager, Record types, etc.)
	 */
	init_Controls = function()
	{
		// Active organization selector - Determines if submitting an issue to this organization gives the choice of sending it by e-mail
		$('#tab_setup #features-org_active').jqxSwitchButton({
			theme: cargohub.jqxtheme,
			height: 25, width: 75,
			checked: master.config.features.active
		}).on('change', function(event) {
			master.config.features.active = event.args.check;
		});

		// The name field
                
		$('#tab_setup #org_name').val(master.config.name);
//                Add option to set or change logo from organization start
		$('#tab_setup #org_logo_view_thumb').attr('src',master.config.org_logo);
		$('#tab_setup #org_logo_old').val(master.config.org_logo);
//                Add option to set or change logo from organization end

		// Current organization status ( for sorting active/inactive ones )
		$('#tab_setup #org_status').jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: status_source,
			displayMember: "text", valueMember: "k",
			width: 308, height: 25, autoDropDownHeight: true
		});
		if(master.config.status)
			for(var c=0; c<status_source.length; c++) {
				if(status_source[c].k == master.config.status)
					$('#tab_setup #org_status').jqxDropDownList('selectIndex', c);
			}

		// The list of all users in the company, to select a chief for this department
		$('#tab_setup #org_manager_id').jqxDropDownList({
			theme: cargohub.jqxtheme,
			// Attempt to load the users list only if the organization actually has users
			source: master.config.count.users > 0 ? master.org_Userlist(function(records)
			{
				// which manager does the org currently have?
				for(var c=0; c<records.length; c++) {
					if(records[c].id == master.config.manager_id) {
						// A tiny delay to allow the creation of the jqxDDL, then selecting the index
						setTimeout(function() {
							$('#tab_setup #org_manager_id').jqxDropDownList('selectIndex', c);
						}, 1);
						break;
					}
				}
			}): null,
			displayMember: "name", valueMember: "id",
			width: 308, height: 25, autoDropDownHeight: master.config.count.users < 8,
			promptText: master.config.count.users > 0 ? "Choose manager:": "No users to choose from",
			disabled: master.config.count.users == 0
		});

		// List of record types this organization has access to (checkbox dropdown)
		$('#tab_setup #ist2org').jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: ist_source, checkboxes: true,
			displayMember: "name", valueMember: "key",
			width: 308, height: 25,
			placeHolder: "(Loading issuetypes...)",
			renderer: function (index, label, value) {
				return "<div><img style='float: left; margin-right: 4px;' src='/img/icons16/ist/"+ist_source.records[index].key+".png' />" + label + "</div>";
			},
			selectionRenderer: function(orig,index,str) {
				var string = index==-1? "Please enable at least one issuetype!": function() {
					var checked = $('#tab_setup #ist2org').jqxDropDownList("getCheckedItems"), string = '', c = 0;
					if(checked.length <= 2) {
						for(; c< checked.length; c++)
							string+= (c>0? ', ': '') + checked[c].label;
					} else if(checked.length == ist_source.records.length)
						string = "All record types are enabled";
					else
						string = checked.length + " out of " + ist_source.records.length + " record types enabled";
					return string;
				}();
				return "<span style='display: block; width: 278px; text-overflow: ellipsis; margin-top: 3px; background-color: transparent;'>"+string+"</span>";
			},
			disabled: true
		})
		/**
		 * Event: When dropdown completed loading, check the record types which are already active
		 */
		.on('bindingComplete', function() {
			$(this).jqxDropDownList({
				disabled: false,
			});

			// Check the enabled record types
			for(c=0; c<ist_source.records.length; c++)
				if(ist_source.records[c].enabled)
					$(this).jqxDropDownList('checkIndex', c);

			$(this).jqxDropDownList({placeHolder: "Please enable at least one record type!"});
		});

		// List of organization domains - The primary domain is the one visitors using the Organization Search System are redirected to,
		// and also where users beloging to the organization are redirected after login (on any other domain) - To maintain consistent corporate identity
		$('#tab_setup #features_primary_domain').jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: domains_source,
			displayMember: "domain", valueMember: "domain",
			width: 308, height: 25, autoDropDownHeight: true,
			placeHolder: "(Loading domains...)",
			disabled: true
		})
		/**
		 * Event: When dropdown completed loading, select the organization's primary domain
		 */
		.on('bindingComplete', function() {
			if(domains_source.records.length == 0)
				$(this).jqxDropDownList({
					placeHolder: "(There are no defined domains!)",
					disabled: true
				});
			else {
				$(this).jqxDropDownList({
					placeHolder: "Choose the domain",
					disabled: false
				});

				if(master.config.features && master.config.features.primary_domain)
					for(var c=0; c<domains_source.records.length; c++) {
						if(domains_source.records[c].domain == master.config.features.primary_domain)
							$(this).jqxDropDownList('selectIndex', c);
					}
			}
		});

		// Organization primary contact - Used to display stuff like phone number, address, etc. on Overview tab, and also in the (future) Organization Search System
		$('#tab_setup #org_contact_id').jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: contacts_source,
			displayMember: "email", valueMember: "id",
			width: 308, height: 25, autoDropDownHeight: true,
			placeHolder: "(Loading contacts...)",
			disabled: true
		})
		/**
		 * Event: When dropdown completed loading, select the organization's primary domain
		 */
		.on('bindingComplete', function() {
			if(contacts_source.records.length == 0)
				$(this).jqxDropDownList({
					placeHolder: "(There are no contacts!)",
					disabled: true
				});
			else {
				$(this).jqxDropDownList({
					placeHolder: "Choose the contact",
					disabled: false,
				});

				if(master.config.contact_id)
					for(var c=0; c<contacts_source.records.length; c++) {
						if(contacts_source.records[c].id == master.config.contact_id)
							$(this).jqxDropDownList('selectIndex', c);
					}
			}
		});

		// Organization primary contact - Used to display stuff like phone number, address, etc. on Overview tab, and also in the (future) Organization Search System
		$('#tab_setup #features-webview_template').jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: templates_source,
			displayMember: "description", valueMember: "id",
			width: 308, height: 25, autoDropDownHeight: true,
			placeHolder: "(Loading templates...)",
			disabled: true
		})
		/**
		 * Event: When dropdown completed loading, select the organization's primary domain
		 */
		.on('bindingComplete', function() {
			if(templates_source.records.length == 0)
				$(this).jqxDropDownList({
					placeHolder: "(There are no templates!)",
					disabled: true
				});
			else {
				$(this).jqxDropDownList({
					placeHolder: "Choose the template",
					disabled: false,
				});

				if(master.config.features && master.config.features.webview_template)
					for(var c=0; c<templates_source.records.length; c++) {
						if(templates_source.records[c].id == master.config.features.webview_template)
							$(this).jqxDropDownList('selectIndex', c);
					}
			}
		});

		// Active organization selector - Determines if submitting an issue to this organization gives the choice of sending it by e-mail
		$('#tab_setup #features-real_attachments').jqxSwitchButton({
			theme: cargohub.jqxtheme,
			height: 25, width: 75,
			checked: master.config.features.real_attach
		}).on('change', function(event) {
			master.config.features.real_attach = event.args.check;
		});

		// Automated Notification Time-of-Day selection (defaults to 00:00)
		$('#tab_setup #features-ntf_tod').timeEntry({
			show24Hours: true,
			timeSteps: [1,5],
			spinnerImage: "spinnerBlue.png"
		});

		if(master.config.features) {
			if(master.config.features.ntf_tod) {
				$('#tab_setup #features-ntf_tod').timeEntry('setTime', master.config.features.ntf_tod);
			} else {
				$('#tab_setup #features-ntf_tod').timeEntry('setTime', "00:00");
			}
		}
	}
	;

	this._public({
		/**
		 * Setup Tab - Admin-only module for configuring primary settings for an organization ( name, module access, "manager" user and presence in the Organization Search System )
		 */
		init: function()
		{
			init_Toolbar();
			init_Controls();
		}
	});
        
//        Add option to set or change logo from organization start
        $(document).on('change', '#org_logo', function () {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    $('#org_logo_view_thumb').attr('src', e.target.result);
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
//        Add option to set or change logo from organization end



});


