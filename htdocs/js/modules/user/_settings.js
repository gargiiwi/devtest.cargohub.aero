/**
 * Module "User Settings"
 *
 * @author Wavetrex (wavetrex@gmail.com)
 * @package CargoITS
 * @version 2.5
 */
cargohub.user_settings = donrey.BASE._extend(function() {
	// private
	var self = this,
	c = null,
	// Interface state and hash based navigation
	popups = {},

	/**
	 * Setup the tab "Preferences"
	 */
	setup_Tab_Preferences = function()
	{
		var prefs_changed = false, hide_to = null,
		/**
		 * Setup the Notificaiton Tab toolbar
		 */
		setup_Preferences_Toolbar = function()
		{
			$("#tab_preferences .toolbar button")
			.jqxButton({ theme: cargohub.jqxtheme })
			/**
			 * Event: A button in the toolbar is clicked
			 */
			.on('click', function()
			{
				var data = $(this).data();
				switch(data.op) {
					case 'save': // stringify data_to_save and send it to PHP, where all the changed notification preferences will be updated in the DB
						cargohub.components.spinner.On("Saving", "Please wait...");
						cargohub.application.save_Preferences(self.config.preferences, function(x) {
							prefs_changed = false;
							$('#tab_preferences .change-ops button').jqxButton({disabled: true});
							hide_to = setTimeout(function() {
								$('#tab_preferences .change-ops').fadeOut();
								hide_to = null;
							}, 600);
							cargohub.components.spinner.Off();
							cargohub.application.evan_Standard_Notify('ok', "Preferences have been saved.", 10, false, "<b>Note:</b> Some of the settings might require a logout and login again in order to apply.");
						});
						break;

					case 'discard': // Preferences are discarded
						if(!confirm("Are you sure you want to discard all changes ?"))
							return;
						document.location.reload();
						break;

					case 'reset': // Preferences are reset to system defaults ( Can also be used as INIT for users )
						if(!confirm("Are you sure you want to load defaults ?\nAll your options will be erased and system defaults will be loaded."))
							return;

						cargohub.application.reset_Preferences(function(x) {
							document.location.reload();
						});
						break;
				}
			});
		},

		prefs_Panel_EnableSave = function()
		{
			$('#tab_preferences .change-ops button').jqxButton({disabled: false});
			if(!prefs_changed) {
				clearTimeout(hide_to);
				hide_to = null;
				$('#tab_preferences .change-ops').fadeIn();
			}
			prefs_changed = true;
		};

		// set some defaults in case the user lands here first time (or has not saved any preferences yet)
		// TODO: The preferences system should work globally without all these conversions
		if(!self.config.preferences)
			self.config.preferences = {};
		if(!self.config.preferences.usermenubar)
			self.config.preferences.usermenubar = {};
		if(!self.config.preferences.graphics)
			self.config.preferences.graphics = {};
		if(!self.config.preferences.grid)
			self.config.preferences.grid = {};
		if(!self.config.preferences.item)
			self.config.preferences.item = {};
		if(!self.config.preferences.operations)
			self.config.preferences.operations = {};
		if(!self.config.preferences.csscope)
			self.config.preferences.csscope = {};

		// All preferences checkboxes
		$("#tab_preferences .pref-checkbox").each(function() {
			// checked?
			var $this = $(this), cb_group = $this.data('group'), pref_group = null, pref_item = $this.data('item');

			switch(cb_group) {
				case 'umb': // user-menu-bar selectors
					pref_group = self.config.preferences.usermenubar; break;
				case 'gfx': // layout configuration
					pref_group = self.config.preferences.graphics; break;
				case 'grid': // issue grid configuration
					pref_group = self.config.preferences.grid; break;
				case 'item': // issue overview page
					pref_group = self.config.preferences.item; break;
				case 'oper': // operational preferences
					pref_group = self.config.preferences.operations; break;
				case 'css': // contact search scope
					pref_group = self.config.preferences.csscope; break;
			}

			$this.jqxCheckBox({
				theme: cargohub.jqxtheme,
				checked: pref_group[pref_item] == true
			})
			/**
			 * Event: A checkbox is changed
			 */
			.on('change', function(e) {
				pref_group[pref_item] = e.args.checked;
				prefs_Panel_EnableSave();
			});
		});

		// All preferences radiobuttons
		$("#tab_preferences .pref-radio").each(function() {
			var $this = $(this), radio_group = $this.data('group'), choice = $this.data('choice');

			$this.jqxRadioButton({
				theme: cargohub.jqxtheme,
				groupName: radio_group,
				checked: self.config.preferences[radio_group] == choice
			})
			/**
			 * Event: A radiobutton changes value
			 */
			.on('change', function(e) {
				if(e.args.checked == true) {
					self.config.preferences[$(this).jqxRadioButton('groupName')] = choice;
					prefs_Panel_EnableSave();
				}
			});
		});

		setup_Preferences_Toolbar();
	},

	/**
	 * Setup the tab "Notification Subscriptions"
	 */
	setup_Tab_NotifySub = function()
	{
		var data_to_save = {}, data_changed = false, hide_to = null,

		/**
		 * Setup the Notificaiton Tab toolbar
		 */
		setup_Subscriptions_Toolbar = function()
		{
			$("#tab_notifysub .toolbar button")
			.jqxButton({ theme: cargohub.jqxtheme })
			/**
			 * Event: A button in the toolbar is clicked
			 */
			.on('click', function()
			{
				var data = $(this).data();
				switch(data.op) {
					case 'save': // stringify data_to_save and send it to PHP, where all the changed notification preferences will be updated in the DB
						$.post("?action=save_subscriptions", {
							data: JSON.stringify(data_to_save)
						}, function(x) {
							if(x.success) {
								data_changed = false;
								$('#tab_notifysub .change-ops button').jqxButton({disabled: true});
								data_to_save = {};
								hide_to = setTimeout(function() {
									$('#tab_notifysub .change-ops').fadeOut();
									hide_to = null;
								}, 600);
								cargohub.its.alert(x.response, "Success !", "flag_finish.png");
							} else {
								cargohub.its.alert(x.reason, "Message from server", "information.png");
							}
						});
						break;

					case 'discard': // Changes to the checkboxes are discarded, the treegrid is reloaded
						$('#tab_notifysub .change-ops button').jqxButton({disabled: true});
						hide_to = setTimeout(function() {
							$('#tab_notifysub .change-ops').fadeOut();
							hide_to = null;
						}, 600);
						$("#tab_notifysub #treegrid-notifysub").jqxTreeGrid('updateBoundData'); // reload original data
						data_changed = false;
						data_to_save = {};
						break;
				}
			});
		},

		/**
		 * Sets up the tree-grid which holds all notification options ( they are all checkboxes )
		 */
		setup_Subscriptions_TreeGrid = function()
		{
			var createEditor = function(row, cellvalue, editor, cellText, height) {
				editor.jqxCheckBox({
					theme: cargohub.jqxtheme,
					boxSize: 11,
					animationShowDelay: 0, animationHideDelay: 0
				});
			}, initEditor = function(row, cellvalue, editor, celltext, width, height) {
				editor.val(cellvalue||false);
			}, getEditorValue = function(row, cellvalue, editor) {
				return editor.val();
			}, cellsRenderer = function(rowKey, dataField, value, data) {
				var act_c = 0;
				if(data.parent_key == 'issue') {
					if(dataField == "ntf_active") {
						// Count number of active notifications inside the record subgroup
						for(c=0; c<data.records.length; c++)
							if(data.records[c].ntf_active)
								act_c++;
						return "<div class='center'>"+ act_c+ "</div>";
					} else
						return "<div class='center gray'>--</div>";
				}
				return "<div style='text-align: center;'><img style='position: relative; top: 2px;' width='16' height='16' src='/img/icons16/checkbox_"+(value? "yes": "no")+".png' />";
			};

			$("#tab_notifysub #treegrid-notifysub").jqxTreeGrid({
				theme: cargohub.jqxtheme,
				width: cargohub.fullwidth - 14,
				editable: true,
				filterable: true, filterMode: 'simple',
				// This reads the user's subscriptions (all options ON/OFF), and also the structure
				source: new $.jqx.dataAdapter({
					url: '?action=get_subscriptions',
					datatype: "json",
					datafields: [
						{name: 'key'},
						{name: 'parent_key'},
						{name: 'type'},
//						{name: 'subtype'},
						{name: 'label'},
						{name: 'ntf_active', map: 'ntf>active', type: 'bool'},
						{name: 'ntf_instant', map: 'ntf>instant', type: 'bool'},
						{name: 'ntf_email', map: 'ntf>email', type: 'bool'},
						{name: 'ntf_enhanced', map: 'ntf>enhanced', type: 'bool'},
						{name: 'ntf_summary', map: 'ntf>summary', type: 'bool'}
					],
					hierarchy: {
						keyDataField: {name: 'key'},
						parentDataField: {name: 'parent_key'},
						groupingDataFields: [{name: 'type'}]
					},
					id: 'key'
				}),
				columns: [
					{text: "Subscribe to ...", dataField: 'label', editable: false},
					{text: "<b>ACTIVE?</b>", dataField: 'ntf_active', align: 'center', cellsAlign: 'center', columnType: 'template', width: 112, editable: true,
						createEditor: createEditor, initEditor: initEditor, getEditorValue: getEditorValue,	cellsRenderer: cellsRenderer},
					{text: "Instant", dataField: 'ntf_instant', align: 'center', cellsAlign: 'center', columnType: 'template', width: 112, editable: true, columnGroup: "method",
						createEditor: createEditor, initEditor: initEditor, getEditorValue: getEditorValue,	cellsRenderer: cellsRenderer},
					{text: "Receive E-mail", dataField: 'ntf_email', align: 'center', cellsAlign: 'center', columnType: 'template', width: 112, editable: true, columnGroup: "method",
						createEditor: createEditor, initEditor: initEditor, getEditorValue: getEditorValue,	cellsRenderer: cellsRenderer},
					{text: "Enhanced Mail", dataField: 'ntf_enhanced', align: 'center', cellsAlign: 'center', columnType: 'template', width: 112, editable: true, columnGroup: "method",
						createEditor: createEditor, initEditor: initEditor, getEditorValue: getEditorValue,	cellsRenderer: cellsRenderer}
//					{text: "Daily summary", dataField: 'ntf_summary', align: 'center', cellsAlign: 'center', columnType: 'template', width: 112, editable: true,
//						createEditor: createEditor, initEditor: initEditor, getEditorValue: getEditorValue,	cellsRenderer: cellsRenderer}
				],
				columnGroups: [
					{ text: "Methods of notification", name: "method", align: 'center'}
				],
				editSettings: { saveOnPageChange: true, saveOnBlur: true, saveOnSelectionChange: true, cancelOnEsc: true, saveOnEnter: true, editSingleCell: false, editOnDoubleClick: false, editOnF2: false }
			})
			/**
			 * Event: When the tree finished loading, expand some rows by default
			 */
			.on('bindingComplete', function() {
//				$(this).jqxTreeGrid('expandRow', "system");
				$(this).jqxTreeGrid('expandRow', "issue");
				$(this).jqxTreeGrid('expandRow', "task");
			})
			/**
			 * Event: Force row editing on select (there will always be one row being edited)
			 */
			.on('rowClick', function(event) {
				var key_fragments = event.args.row.key.split('_').length;
				if(key_fragments > 1 && event.args.row.parent.key != "issue")
					$(this).jqxTreeGrid('beginRowEdit', event.args.row.key);
			})
			.on('rowDoubleClick', function(event) {
				if(event.args.row.expanded)
					$(this).jqxTreeGrid('collapseRow', event.args.row.key);
				else
					$(this).jqxTreeGrid('expandRow', event.args.row.key);
			})
			/**
			 * Event: One of the checkboxes have been clicked, store all the changed values so they can be saved when the user choses so.
			 * Also display the save button on the first checkbox click
			 */
			.on('rowEndEdit', function(event)
			{
				data_to_save[event.args.key] = [
					event.args.row.ntf_active? 1: 0,
					event.args.row.ntf_instant? 1: 0,
					event.args.row.ntf_email? 1: 0,
					event.args.row.ntf_enhanced? 1: 0,
					event.args.row.ntf_summary? 1: 0
				];
				$('#tab_notifysub .change-ops button').jqxButton({disabled: false});
				if(!data_changed) {
					clearTimeout(hide_to);
					hide_to = null;
					$('#tab_notifysub .change-ops').fadeIn();
				}
				data_changed = true;
			})
			/**
			 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
			 */
			.on('filter', function(event)
			{
				$(this).jqxTreeGrid('clearSelection');
			});

		};

		setup_Subscriptions_Toolbar();
		setup_Subscriptions_TreeGrid();

		// ???
		$(".command > button").on('click', function() {
			var data = $(this).data();
			switch(data.op) {
				case 'link':
					document.location = data.link;
					break;
			}
		});
	},

	/**
	 * Setup the tab "Profile" which contains a form for the user to update his name, e-mail address, signature and possibly change password
	 */
	setup_Tab_Profile = function()
	{
	},

	/**
	 * Tab initialization
	 */
	tab_Init = function(tab_id)
	{
		switch(tab_id) {
			case 'preferences':
				setup_Tab_Preferences(); break;
			case 'notifysub':
				setup_Tab_NotifySub(); break;
			case 'profile':
				setup_Tab_Profile(); break;
		}
	}
	;

	this._public({
		/**
		 * Module initialization
		 */
		init: function()
		{
			if(!self.config)
				self.config = {};

			cargohub.components.hashnav.events = {
				tab_init: tab_Init
			};

			// Test the new universal hashnav
			cargohub.components.hashnav.setup({
				use_tabs: true,
				tab0: 'preferences',
				use_navbars: true,
				navbars_config: {
					'preferences': "preferences-navbar"
				}
			}, self);
		}
	});
});
