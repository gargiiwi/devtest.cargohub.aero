/**
 * Component "Contacts List" - Presents a list of contacts available for the user.
 * - Consists of a combination of a tree (left side) and a grid (right side)
 *   - The tree is a list of labels or system contacts selectors, and the choice depends on user access rights. The tree is initialized with the module.
 *   - The grid is the list of contacts itself, loaded remotely. Using scrolling, not paging for displaying a long list.
 * - Supports picking a contact to be used outside the control
 * - Supports integration into a TABS module and opening the contact editor for the selected contact
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @depend component:modal (For Alerts), jqxSplitter, jqxTree, jqxDataTable
 * @package CargoConnect
 * @subpackage JavaScriptUI Components
 * @version 2.9
 */
cargohub.components.contacts_list = donrey.BASE._extend(function() {
		// Core
	var self = this,
		master = null,				// Master object that initialized this component
		$c = null,					// The DOM container which contains the jqxSplitter (could be a tab content, a window's content or just plain page)
		config = {},
		// Functionality - Tree
		current_label = null,
		// Functionality - DataTable
		table_initialized = false,	// Becomes true after the table has finished first-time initialization
		columns_set = "standard",
		selected_contacts = [],

	/**
	 * Setup the splitter which contains the tree and the grid with contacts
	 */
	setup_Splitter = function()
	{
		// initialize the splitter between classes and contacts
		$c.jqxSplitter({
			theme: cargohub.jqxtheme,
			width: config.width, height: config.height,
			resizable: false,
			// TODO: Sizes should be more flexible...
			panels:	[{
				collapsible: true,
				size: 275
			}, {
				collapsible: false
			}]
		}).on('resize', function(event) {
			// resize the grid automatically when the splitter's bar is moved:
			$('#ctx-table', $c).jqxDataTable({
				width: event.args.panels[1].size
			});
		});
	},

	/**
	 * Indicates if the event tree_Select is triggered by user (false) or by the code itself (true)
	 */
	tree_CodeSelect = false,

	/**
	 * Event: Select an item in the tree ( one of the contacts classes )
	 */
	tree_Select = function(event)
	{
		if(tree_CodeSelect)
			return;

		// Check if clicking on a folder (not selectable), and jump back to the current non-folder item
		if(!$(event.args.element).data('selectable')) {
			tree_CodeSelect = true;
			$("#ctx-tree", $c).jqxTree('selectItem', $('#ctx-tree li#'+current_label, $c)[0]);
			tree_CodeSelect = false;
			return;
		}

		// get the correct data
		var tree_item = $('#ctx-tree', $c).jqxTree('getItem', event.args.element), selected_label = tree_item.id;

		// clicked on the already selected one ? Nothing to do, cancel event.
		if(selected_label == current_label)
			return;

		// New label is clicked - Initialize or reload the grid on right side.
//		dd(selected_label); // The label ID should contain everything needed (type of folder, database ID)

		contacts_data_source._source.data.label = current_label = selected_label;
		contacts_data_source._source.data.user_id = config.tree.user_id;
		contacts_data_source._source.data.org_id = config.tree.org_id;
		// In certain cases, more data is needed to properly locate contacts (target contacts, organization and department contacts, Issue-Linked)
		if(selected_label.substr(0, 4) == 'tgt_') {
			contacts_data_source._source.data.partner_id = config.partner_id;
			contacts_data_source._source.data.istkey = config.istkey; // Current issue type for which target contacts are loaded
		}

		// Initialize grid if necessary
		if(!table_initialized)
			setup_DataTable(tree_item.label);
		else {
			// Already initialized, just update data according to the new selected label
			var new_column_set = $(tree_item.element).data('columnset') || "standard";
			if(new_column_set != columns_set) {
				columns_set = new_column_set;
			}

			$('#ctx-table', $c).jqxDataTable({
				columns: contacts_col_defs[columns_set]
			});

			// Trigger datatable self-update
			$('#ctx-table', $c).jqxDataTable('updateBoundData');
		}

		if(self.events.label_selected)
			self.events.label_selected(selected_label);
	},

	/**
	 * Setup the tree containing the labels (filters) for selecting contacts
	 */
	setup_Tree = function(autoselect_idx)
	{
		// setup the tree of classes (Assumes it already exists in the HTML)
		$('#ctx-tree', $c).jqxTree({
			theme: cargohub.jqxtheme,
			height: '100%', width: 275
		});

		$('#ctx-tree ul.jqx-tree-dropdown-root > li').each(function() {
			$('#ctx-tree', $c).jqxTree('expandItem', this);
		});
//		$('#ctx-tree', $c).jqxTree('expandAll');
		$('#ctx-tree', $c).on('select', tree_Select);

		if(typeof autoselect_idx === 'undefined' || autoselect_idx === null)
			autoselect_idx = 1;

		$("#ctx-tree", $c).jqxTree('selectItem', $('#ctx-tree li.jqx-tree-item-li:eq('+autoselect_idx+')', $c)[0]); // autoselect a tree entry to begin displaying contacts
	},

	/**
	 * Callback function which returns content of cell in the contacts grid with some formatting
	 */
/*	contact_Cell_Renderer = function(row, columnfield, value, defaulthtml, columnproperties)
	{
		var changed_value = null, data = $('#ctx-grid', $c).jqxGrid('getrowdata', row);

		if(columnfield == 'dt')
			changed_value = $.format.date(value, system_date_format);

		if(changed_value)
			defaulthtml = defaulthtml.replace('>'+ value +'<', '>'+ changed_value +'<');

		if(data.primary == true)
			defaulthtml = defaulthtml.replace('style="', 'style="font-weight: bold;');

		return defaulthtml;
	},*/

	// Column definitions for the various contact types - Used to present the most relevant contact information in the grid
	contacts_col_defs = {
		standard: [
			{ text: '@label@', dataField: 'name', renderer: function (text, align, height) {
				var item = $('#ctx-tree', $c).jqxTree('getSelectedItem');
				return "<div style='line-height: 30px; margin-left: 4px; font-weight: bold;'>"+ (item.element.dataset ? item.element.dataset.name: "Name") +"</div>";
			}},
			{ text: 'E-mail', dataField: 'email'},
			{ text: 'Added on', dataField: 'ctime', width: 100, filterable: false, cellsFormat: "yyyy-MM-dd", align: "center", cellsAlign: 'center'}
		],
		// An experiment, to see if the column change is functional
		reversed: [
			{ text: 'Added on', dataField: 'ctime', width: 100, filterable: false, cellsFormat: "yyyy-MM-dd", align: "center", cellsAlign: 'center'},
			{ text: 'E-mail', datafield: 'email'},
			{ text: '@label@', datafield: 'name'}
		]
	},

	/**
	 * The DataAdapter used to load the messages into the grid
	 */
	contacts_data_source = new $.jqx.dataAdapter({
		url: '/contact/operations?action=get_contacts',
		data: {},
		datatype: "json",
		datafields: [
			{name: 'id', type: 'int'},
			{name: 'org_id', type: 'int'},
			{name: 'org_name', type: 'organization>name'},
			{name: 'dep_id', type: 'int'},
			{name: 'dep_name', type: 'department>name'},
			{name: 'user_id', type: 'int'},					// Not all contact types have an user id + name
			{name: 'user_name', map: "user>name"},
			{name: 'type'},
			{name: 'ctime', type: "date"},					// When was the contact created ?
			{name: 'email'},
			{name: 'name'},
			{name: 'count_msgs', type: 'int'},				// How many messages were sent (created) by this contact?
//			{name: 'comment', map: "note>comment"},			// Comment to this contact record (is actually an associated note read on demand for certain note types)
//			{name: 'xd_phone', map: "xdata>phone"},			// Phone number, important for most organization contacts
//			{name: 'xd_track', map: "issue>track"},			// Tracking ID of the issue for issue-linked contacts (Not yet implemented here)
//			{name: 'xd_istkey', map: "issue>type_key"},		// The issue type key, used to render the issue type icon in the type column
//			{name: 'xd_istname', map: "issue>type_name"},	// Type of the issue loaded for all issue-linked, as text
//			{name: 'xd_mix', map: "xdata>mix"}				// Combined XData, for custom contacts (non-system) - Is returned as HTML by the server
			{name: 'primary', type: 'bool'}					// Indicates this contact should be market as being the "primary" one (shown as bold in the grid), for certain lists
		],
		id: 'id'
//		root: "data",
//		beforeprocessing: function(source) {
//			this.totalrecords = source.found_rows;
//		},
		// For some reason, sorting disappears when grid is reloaded
//		sortcolumn: "name",
//		sortdirection: "asc"
	}),

	/**
	 * Reads the ID's of the selected rows (for multiple selection) in the contacts DataTable
	 */
	get_selected_contacts_IDs = function()
	{
		var rows = $('#ctx-table', $c).jqxDataTable('getSelection'), c = 0;
		selected_contacts = [];
		for (; c< rows.length; c++)
			selected_contacts.push(rows[c].id);
		return selected_contacts;
	},

	/**
	 * Sets up the datatable which is listing the contacts for a certain label.
	 * @param initial_toolbar_text
	 */
	setup_DataTable = function(initial_toolbar_text)
	{
		$('#ctx-table', $c).jqxDataTable({
			theme: cargohub.jqxtheme,
			width: $c.jqxSplitter('panels')[1].element.width(), height: config.height,
			source: contacts_data_source, selectionMode: "multipleRows",
			sortable: true, filterable: true, filterMode: 'simple',
			columns: contacts_col_defs[columns_set]
		})
		/**
		 * Event: Binding is complete, data is loaded.
		 * If the config contains a callback for data processing, it is called so content of the table can be exported.
		 */
		.on('bindingComplete', function(event) {
			// Fix position of the first div
			$("#filterctx-table > div:eq(0)", $c).css({"line-height": "29px", "margin-top": "", "top": ""});
			$(this).jqxDataTable('clearSelection');
			if(self.events.contacts_selected)
				self.events.contacts_selected([]);

			// Add a help text on the filter row - Note: This keeps getting reset-ed, so the image has to be added again and again after grid reconstruction
			if($("#filterctx-table > img", $c).length == 0) {
				// Select All (contacts) button
				$("#filterctx-table", $c).prepend("<button style='float: right; margin: 5px; border: 1px solid gray;' value='Select all' class='jqx-rc-all'>Select All</button>");
				$("#filterctx-table > button", $c)
				.on('click', function() {
					$('#ctx-table', $c).jqxDataTable('selectAll'); // This function is added to jqxDataTable, it doesn't exist natively
				});

				// The helper about selection
				$("#filterctx-table", $c).prepend("<img style='float: right; margin: 7px; cursor: help;' src='/img/icons16/question.png' />");
				$("#filterctx-table > img", $c)
				.jqxTooltip({
					content: "To select multiple contacts, keep CTRL key pressed while clicking<br />Alternatively, select first, keep SHIFT pressed and click on last.",
					position: 'left',
					theme: cargohub.jqxtheme
				});
			}
		})
		/**
		 * Event: A row is selected in this DataTable - fadeIn the Edit/Delete buttons (TODO: check if the user has edit/delete abilities on selected row)
		 */
		.on('rowSelect', function(event) {
			// The datatable generates several select/unselect events every times rows are changed; The only needed one is the last
			donrey.final_Delay('datatable_rowselect', function() {
				var row_ids = get_selected_contacts_IDs();
				if(self.events.contacts_selected)
					self.events.contacts_selected(row_ids);
			}, 60);
		})
		/**
		 * Event: A row is selected in this DataTable - fadeIn the Edit/Delete buttons (TODO: check if the user has edit/delete abilities on selected row)
		 */
		.on('rowUnselect', function(event) {
			// The datatable generates several select/unselect events every times rows are changed; The only needed one is the last
			donrey.final_Delay('datatable_rowselect', function() {
				var row_ids = get_selected_contacts_IDs();
				if(self.events.contacts_selected)
					self.events.contacts_selected(row_ids);
			}, 60);
		})
		/**
		 * Event: A row in note tabe is double-clicked - This should open the note editing window
		 */
		.on('rowDoubleClick', function(event) {
			if(self.events.contacts_doubleclick)
				self.events.contacts_doubleclick(selected_contacts[0]);
		})
		/**
		 * Event: The notes table is sorted - It should unselect any selected row and hide the Edit/Delete buttons
		 */
		.on('filter', function(event) {
			$(this).jqxDataTable('clearSelection');
		});

		table_initialized = true;
	}

	;

	// Public methods
	this._public({
		name: "Contacts List",

		// Component events
		events: {},

		/**
		 * Setup the Contacts List module
		 * @param _config
		 * @param _master Calling object - The request would look like: cargohub.components.contacts_list.setup(config, self);
		 */
		setup: function(_config, _master)
		{
			if(!_config.container || _config.container.length != 1)
				return cargohub.alert("Error, could not setup Contacts List because container not specified, not found or non-unique.");
			$c = _config.container;
			delete _config.container;

			if(_master)
				master = _master;
			if(_config)
				config = _config;

			if(!config.width)
				config.width = cargohub.fullwidth;
			if(!config.height)
				config.height = 550;

			// Remote load the address book into the container (with parameters, depending on what was selected in the "Recipient")
			// TODO: Load_Contacts_List supports various parameters - Perhaps some flexibility on how the Address Book is displayed would be nice...
			$c.load("/contact/operations?action=Load_Contacts_List", config.tree, function() {
				setup_Splitter();
				setup_Tree(config.tree.autoselect_idx);
			});
		},

		/**
		 * Reloads existing grid (usually following a data change, addition or deletion)
		 */
		refresh: function()
		{
			$('#ctx-table', $c).jqxDataTable('updateBoundData');
		},

		/**
		 * Returns the ID of the current label in the tree
		 */
		get_Current_Label: function()
		{
			return current_label;
		},

		/**
		 * Updates the selected label with new text
		 */
		update_Current_Label: function(text, icon, color)
		{
			if(!text)
				return;

			var item = $('#ctx-tree', $c).jqxTree('getSelectedItem');
			$('#ctx-tree', $c).jqxTree('updateItem', item, {
				label: '<img alt="[icn]" src="/img/icons16/commlabel/'+ icon +'.png"><span>'+ text + '</span>'
			});
			item.element.dataset.name = text;
			if(color)
				item.element.dataset.color = color;
		},

		/**
		 * Return the data for the current selected item
		 */
		get_Curent_Label_Data: function()
		{
			return $('#ctx-tree', $c).jqxTree('getSelectedItem').element.dataset;
		},

		/**
		 * Returns the list of contacts currently selected in the grid, as an array, even if only one single is selected
		 */
		get_Selected_Contact_IDs: function()
		{
			return selected_contacts;
		},

		/**
		 * Reads the information stored in the grid's adapter for a certain row
		 * @param contact_id
		 */
		get_Contact_Data: function(contact_id)
		{
			return contacts_data_source.recordids[contact_id];
		},

		/**
		 * Updates configuration variables by copying and overwriting any existing ones
		 * - Certain operations (like instant contact search or opening the address book depend on current configuration)
		 *
		 * @param _config
		 */
		update_Config: function(_config)
		{
			for(var k in _config)
				if(_config.hasOwnProperty(k)) {
					if(_config[k] === null)
						delete config[k];
					else
						config[k] = _config[k];
				}
		}
	});

	// Load CSS on first use, because this module needs styles
	donrey.load_CSS(donrey.CFG.paths.components_css + "contacts_list.css?v=2018.11");
});
