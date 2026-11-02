/**
 * Module "Organization Manager", tab "Security"
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @version 2019.09.23
 */
cargohub.organization_security = cargohub.baseclass._instance(cargohub.baseclass.fullgrid, function() {
	// private
	var self = this,
		c = null,
		master = cargohub.organization_manager,

	// Source for the results grid (will be set up in the init method)
	results_Source,

	// Source for group combobox
	groups_Source = new $.jqx.dataAdapter({
		url: "/manager/organization?action=Get_Groups",
		datatype: "json",
		datafields: [
			{name: 'id', type: 'number'},
			{name: 'group', type: 'string', map: "type"},
			{name: 'name', type: 'string'}
		],
		id: 'id'
	}),

	/**
	 * Initialize the search controls (RTs access, Privileges access)
	 */
	init_Locators = function()
	{
		// Record type selector
		$('#tab_security #secu-rt').jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: master.config.ist_source,
			displayMember: "name", valueMember: "id",
			width: 290, height: 22, autoDropDownHeight: true,
			placeHolder: "Select one to show results",
			renderer: function (index, label, value) {
				return "<div><img style='float: left; margin-right: 4px;' src='/img/icons16/ist/"+master.config.ist_source[index].key+".png' />"+ label+ "</div>";
			}
		})
		/**
		 * On selecting an RT, update grid source info and load results
		 */
		.on('select', function(event) {
			if(!event.args.item)
				return;

			// Update title
			$("#tab_security #secu-results-title").html("Module: "+ event.args.item.label);

			results_Source._source.url = '?action=Security_Search';
			results_Source._options.data = {
				srch_type: "rt",
				srch_id: event.args.item.value
			};
			$("#tab_security #secu-results-grid").jqxGrid({
				source: results_Source
			});

			// Unselect the other searchers
			$('#tab_security #secu-priv').jqxDropDownList('clearSelection');
			$('#tab_security #secu-grp').jqxComboBox('clearSelection');
			$('#tab_security label').css('font-weight', '');
			$('#tab_security #sl-rt').css('font-weight', 'bold');
		});

		// Privilege selector
		$('#tab_security #secu-priv').jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: self.config.priv_Source,
			displayMember: "text", valueMember: "id",
			width: 290, height: 22,
			dropDownWidth: 580, dropDownHeight: 246,
			placeHolder: "Select one to show results",
			filterable: true, searchMode: "containsignorecase"
		})
		/**
		 * On selecting a Privilege, update grid source info and load results
		 */
		.on('select', function(event) {
			if(!event.args.item)
				return;

			// Update title
			$("#tab_security #secu-results-title").html("Privilege: "+ event.args.item.label);

			results_Source._source.url = '?action=Security_Search';
			results_Source._options.data = {
				srch_type: "priv",
				srch_id: event.args.item.value
			};
			$("#tab_security #secu-results-grid").jqxGrid({
				source: results_Source
			});

			$('#tab_security #secu-rt').jqxDropDownList('clearSelection');
			$('#tab_security #secu-grp').jqxComboBox('clearSelection');
			$('#tab_security label').css('font-weight', '');
			$('#tab_security #sl-priv').css('font-weight', 'bold');
		});

		// Group search
		$('#tab_security #secu-grp').jqxComboBox({
			theme: cargohub.jqxtheme,
			source: groups_Source,
			searchMode: 'startswithignorecase',
			autoComplete: true, minLength: 2,
			displayMember: "name", valueMember: "id",
			width: 290, height: 22,
			dropDownWidth: 450, dropDownHeight: 246,
			placeHolder: "(Loading groups)",
			animationType: "none",
			renderer: function (index, label, value) {
				if(index == -1)
					return "<h4>&middot;&middot;&middot; "+label+ " &middot;&middot;&middot;</h4>";
				else
					return label;
			},

			disabled: true
		})
		.on('bindingComplete', function(event) {
			$(this).jqxComboBox({
				placeHolder: "Start typing to locate a group",
				disabled: false
			});
		})
		/**
		 * On selecting a Group, update grid source info and load results
		 */
		.on('select', function(event) {
			if(!event.args || !event.args.item)
				return;

			// Update title
			$("#tab_security #secu-results-title").html("Data group: "+ event.args.item.label);

			donrey.final_Delay('group_search', function() {
				results_Source._source.url = '?action=Security_Search';
				results_Source._options.data = {
					srch_type: "grp",
					srch_id: event.args.item.value
				};
				$("#tab_security #secu-results-grid").jqxGrid({
					source: results_Source
				});
			}, 300);

			$('#tab_security #secu-rt').jqxDropDownList('clearSelection');
			$('#tab_security #secu-priv').jqxDropDownList('clearSelection');
			$('#tab_security label').css('font-weight', '');
			$('#tab_security #sl-grp').css('font-weight', 'bold');
		});


		// Add tooltips for relevant elements
		$("#tab_security .info-tip").each(cargohub.components.ui.add_Tooltip);
	},

	/**
	 * Initialize the results grid (shows a combination of departments and users with overrides)
	 * -- Double-clicking on a row opens the respective editor for department access or user access (#acls)
	 */
	init_ResultsGrid = function()
	{
		results_Source = self.source({
			datatype: "json",
			datafields: [
				{name: 'id', type: 'int'},
				{name: 'type', type: 'string'},	// Department or User?
				{name: 'name', type: 'string'},	// D's or U's name
				{name: 'dep', type: 'string'}	// For Users, their Primary department
			],
			id: 'id'
		});

		self.column({
			datafield: 'type', text: "Type", width: 115,
			filtertype: "checkedlist", filteritems: ["Department", "User"]
		});
		self.column({
			datafield: 'name', text: "Name of the Department or User", width: "50%"
		});
		self.column({
			datafield: 'dep', text: "Department (for users)",
			filtertype: "textbox"
		});

		// Init the grid as persistent in session
		self.init_Grid($("#tab_security #secu-results-grid"), {
			width: "100%",
			pageable: true, pagesizeoptions: [10, 20, 50, 100], pagesize: 20,
			altrows: false
		})
		/**
		 * Event: On doubleclicking, do the same as simple clicking then using the Edit button
		 */
		.on('rowdoubleclick', function(event) {
			var item = event.args.row.bounddata;

			switch(item.type) {
			case 'User':
				document.location = "/manager/user?id="+ item.id+ "#configure";
				break;
			case 'Department':
				document.location = "/manager/department?id="+ item.id+ "#acls";
				break;
			}
		});

		$("#tab_security #secu-results-grid").on("bindingcomplete", function () {
			$(this).jqxGrid('localizestrings', {
				emptydatastring: "No entities have access to the selected item"
			});
		});

		$("#tab_security #secu-results-grid").jqxGrid('localizestrings', {
			emptydatastring: "Select search item from the left side"
		});
	}
	;

	this.init = function()
	{
		// Initial grid state
		if(!self.config.grid_state) {
			self.config.grid_state = {
				sort: {
					column: 'name',
					direction: 'asc'
				}
			};
		}

		init_Locators();
		init_ResultsGrid();
	};
});