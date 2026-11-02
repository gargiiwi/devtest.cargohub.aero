/**
 * Module "Record Process", interface for the Adding/Editing and operating the appended Notes
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules.Record Process
 * @version 2020.06.18
 */
cargohub.record_notes = donrey.BASE._extend(function() {
	// private
	var self = this,
	c = null,
	master = cargohub.record_process,
	edit_privilege = cargohub.application.user_Test_Privileges('issue_edit_notes'),

	// Data from the table containing currently selected row. Used for View, Edit, Delete - Set on any row operation.
	selected_row = null,

	// Source for the DataTable
	notes_Source = new $.jqx.dataAdapter({
		url: "/global/note?action=get_list",
		data: {
			link: "issue",
			link_id: master.config.id,
			content_type: "plain"
		},
		datatype: "json",
		datafields: [
			{name: 'title', type: 'string'},
			{name: 'text', type: 'string'},
			{name: 'dt', type: 'date'},
			{name: 'editor_id', type: 'int', map: 'editor>id'},
			{name: 'editor_name', type: 'string', map: 'editor>name'},
			{name: 'org_id', type: 'string', map: (cargohub.application.user_Is_Admin()? 'org>id': 'org_id')},
			{name: 'org_name', type: 'string', map: 'org>name'},
			{name: 'visibility', type: 'string'},
			{name: 'edit_access', type: 'string'}
		],
		id: "id",
		// Initial Sorting
		sortcolumn: 'dt',
		sortdirection: 'desc'
	}),

	// Note visibility selector TODO: Get the texts from Language files
	note_visibility_source = [
		{k: 'private', text: '<span class="access16 visi-private"></span> Private note, only visible to the editor'},
		{k: 'dep', text: '<span class="access16 visi-dep"></span>Only the editor\'s department members have access'},
		{k: 'org', text: '<span class="access16 visi-org"></span>Everyone from the owner organization can view it'},
		{k: 'shared', text: '<span class="access16 visi-shared"></span>All organizations sharing this '+master.config.type.name},
		{k: 'public', text: '<span class="access16 visi-public"></span>No visibility restrictions'}
	],

	// Note edit access selector TODO: Get the texts from Language files
	note_edit_access_source = [
		{k: 'user', text: '<span class="access16 edit-user"></span>Only the editor can change or delete this note'},
		{k: 'dep', text: '<span class="access16 edit-dep"></span>All members of the editors\'s department can edit'},
		{k: 'org', text: '<span class="access16 edit-org"></span>Anyone in the organization can change the note'},
		{k: 'shared', text: '<span class="access16 edit-shared"></span>Anyone with access to the parent record is an editor'}
	],

	/**
	 * A note row has been selected - Store information in preparation for a double-click ( for Viewing or Editing ), or for clicking on one of the toolbar buttons
	 */
	select_Note = function(row)
	{
		if(!selected_row) {
			donrey.cancel_Delay('hide-bar_notes-select');
//			if(edit_privilege) {
				$('#tab_notes #notes-row-editops button').jqxButton({disabled: false});
				$('#tab_notes #notes-row-editops').fadeIn();
//			} else {
//				$('#tab_notes #notes-row-ops button').jqxButton({disabled: false});
//				$('#tab_notes #notes-row-ops').fadeIn();
//			}
		}
		selected_row = row;
	},

	/**
	 * No rows are currently selected - Make the toolbar buttons dissapear
	 */
	select_Clear = function()
	{
		selected_row = null;
		$('#tab_notes #notes-row-editops button').jqxButton({disabled: true});

		donrey.single_Delay('hide-bar_notes-select', function() {
//			if(edit_privilege)
				$('#tab_notes #notes-row-editops').fadeOut();
//			else
//				$('#tab_notes #notes-row-ops').fadeOut();
		}, 500);
	},

	/**
	 * Sets up and opens the window used to create or change a selected note.
	 * -- Creation or Updating is done via an Ajax call. Refreshes the DataTable on success
	 */
	note_Editor = function(mode)
	{
		if(!selected_row)
			mode = "create";

		cargohub.components.modal.create('note-editor', {
			btn_roles: {cancel: "cancel"}
		});

		if(mode == 'update')
			selected_row.dt_formatted = $.format.date(selected_row.dt, cargohub.datetime_format);

		cargohub.components.modal.open('note-editor', {
			width: 750,	height: (mode == 'update')? 540: 480, // In edit mode there's two extra rows
			title: (mode == 'update'? "Update note for: ": "Create new note for: ") + master.config.type.name+ " - "+master.config.track,
			content: $('#template-note-editor').html().present({
				_itc: cargohub.input_theme_classes,
				action: mode,
				note: (mode == 'update')? selected_row: null
			}),
			callback: function($window) // Setup the controls inside the window
			{
				var submit_data = (mode=='create')? { // Create mode
					link: "issue",						// Create a note for a "Record" (issue, rename this when possible)
					link_id: master.config.id,			// The record's ID
					org_id: master.config.perspective	// Current organization (perspective for admin - For non-admin it will default to the user's organization)
				}: { // Update mode
					id: selected_row.uid,
					link_id: master.config.id, // The record ID to which this note is linked with
					org_id: master.config.perspective
				},	visibility_index = 2,
					edit_access_index = 0;

				// Visibility selector
				if(mode == 'update')
					for(c = 0; c< note_visibility_source.length; c++)
						if(selected_row.visibility == note_visibility_source[c].k)
							visibility_index = c;
				$('#note-visibility', $window).jqxDropDownList({
					theme: cargohub.jqxtheme,
					source: note_visibility_source,
					displayMember: "text", valueMember: "k",
					width: 400,	height: 24, autoDropDownHeight: true,
					selectedIndex: visibility_index
				});

				// Editing access selector
				if(mode == 'update')
					for(c = 0; c< note_edit_access_source.length; c++)
						if(selected_row.edit_access == note_edit_access_source[c].k)
							edit_access_index = c;
				$('#note-edit_access', $window).jqxDropDownList({
					theme: cargohub.jqxtheme,
					source: note_edit_access_source,
					displayMember: "text", valueMember: "k",
					width: 400,	height: 24, autoDropDownHeight: true,
					selectedIndex: edit_access_index
				});

				// Setup the action for "OK" button
				$('#modal_note-editor_ok', $window).off('click').on('click', function(event)
				{
					event.preventDefault();
					// submit the create/edit form using ajax
					$('.modal_content > form', $window).ajaxSubmit({
						data: submit_data,
						dataType: 'json',
						beforeSubmit: function(formData, jqForm, options) {
							// Checks if note has content - Title and the visibility options may be untouched, they default on the server
							if($("textarea", $window).val().length < 5) {
								cargohub.alert("The content of the note should have at least 5 characters.");
								return false;
							}
							return true;
						},
						success: function(x) {
							if(x.success) {
								// note has been updated - Close the window / Refresh the datapanel / Update the tab title to include the returned number of notes (counter)
								$window.jqxWindow('close');
								master.refresh_DataTable($("#tab_notes div.jqx-datatable"));
								if(x.count)
									cargohub.components.hashnav.update_Tab_Header(5, "Notes <span class='fiftycent'>["+x.count+"]</span>", "/img/icons16/note.png");
								// Finally, show the operation result
								master.toolbar_Report("div#tab_notes", x.response);
							} else
								cargohub.its.http_fail(null, "Record.Notes::update_note");
						},
						error: cargohub.its.http_fail
					});
				});
			}
		}, self);
	},

	/**
	 * Opens a view window for the currently selected note (Read-Only). Valid for anyone that has access to notes
	 */
	view_Note = function(row)
	{
		if(row)
			selected_row = row;

		cargohub.components.modal.create('viewnote', {
			buttons: {ok: "Close"}
		});

		cargohub.components.modal.open('viewnote', {
			width: 750,	height: 500,
			title: "<img src='/img/icons16/note.png' alt='[Note]'>View note: "+ selected_row.title,
			content: selected_row.text.nl2br(),
			callback: function($window) // Setup the controls inside the window
			{
				// Nothing here, it's just a simple viewing window.
			}
		});
	},

	/**
	 * Opens note in either view or edit mode, depending on user privilege and note's own settings
	 */
	open_Note = function(row)
	{
		if(row)
			selected_row = row;

		var user = cargohub.application.user_Get_Session();

		// Does the logged in user have edit privilege on this particular note or it belongs to someone else
//		if(cargohub.application.user_Is_Admin() ||
//			edit_privilege && (selected_row.edit_access == 'shared'
//				|| (selected_row.edit_access == 'org' && (selected_row.visibility == 'dep' || selected_row.visibility == 'org'))
//				|| (selected_row.edit_access == 'dep' && selected_row.dep_id == user.cdep_id)
//				|| (selected_row.edit_access == 'user' && selected_row.editor_id == user.id)
//			)
//		)
			note_Editor('update');
//		else
//			view_Note();
	},

	/**
	 * Removes a Record-linked note. Refreshes the DataTable on success
	 */
	delete_Note = function()
	{
		$.post("/global/note?action=remove", {
			link: "issue",
			link_id: master.config.id,			// Linked record ID
			id: selected_row.uid,				// Note ID
			org_id: master.config.perspective	// Current organization (perspective for admin - Not useful for non-admin as it will default to the user's organization anyway)
		}, function(x) {
			if(x.success) {
				master.refresh_DataTable($("#tab_notes div.jqx-datatable"));
				// Update counter on deletion (should be reduced by one compared to current if deleting from the perspective organization)
				if(x.update_count)
					cargohub.components.hashnav.update_Tab_Header(5, "Notes"+(x.count? " <span class='fiftycent'>["+x.count+"]</span>": ""), "/img/icons16/note.png");

				master.toolbar_Report("div#tab_notes", x.response);
			} else
				cargohub.its.http_fail(null, "Record.Notes::delete_note");
		})
		.fail(cargohub.its.http_fail);
	},

	/**
	 * Initialize the Notes tab toolbar (Add, Edit, Delete notes)
	 */
	init_Toolbar = function()
	{
		$("#tab_notes .toolbar button")
		.jqxButton({ theme: cargohub.jqxtheme })
		/**
		 * Event: Toolbar button is clicked
		 */
		.on('click', function()
		{
			switch($(this).data('op')) {
			case "note-create":
				note_Editor('create');
				break;
			case "note-view":
				view_Note();
				break;
			case "note-edit":
				open_Note();
				break;
			case "note-delete":
				if(!confirm("Are you sure you want to delete this note ?"))
					return;
				delete_Note();
				break;
			}
		});

//		if(edit_privilege)
			$('#tab_notes #notes-editops').fadeIn();
	},

	/**
	 * Initialize the Notes DataTable. (This uses common functionality from the main Record Process module - cargohub.record_process )
	 */
	init_DataTable = function()
	{
		/**
		 * Highlights the background of each cell in a row (effectively highlighting the row itself) to indicate the conditions:
		 * - Shared note: Yellow
		 * - User private note: Pale Red
		 * Current perspective orgaization will have all it's text with "italic" style (Admin only)
		 */
		var cell_highlight = function(row, column, value, rowData) {
			var classname = "";

			if(rowData.visibility == 'shared' || rowData.visibility == 'public')
				classname+="grid-cell-shared";
			else if(rowData.visibility == 'private')
				classname+="grid-cell-private";

			// Mark notes for the perspective org for the admin user
			if(cargohub.application.user_Is_Admin() && rowData.org_id == master.config.perspective)
				classname+=" grid-cell-perspective_org";
			return classname;
		};

		master.init_DataTable($("#tab_notes div.jqx-datatable"), {
			// Config
			source: notes_Source,
			columns: [
				{dataField: 'visibility', text: '<span class="access16 visi-public"></span>', width: 25, filterable: false,	cellsRenderer: function(row, column, value, rowData) {
					return '<span class="access16 visi-'+rowData.visibility+'"></span>';
				}, cellClassName: cell_highlight},
				{dataField: 'title', text: "Title or note's first row", cellClassName: cell_highlight},
				{dataField: 'dt', text: "Updated on", width: 160, cellsFormat: cargohub.datetime_format, filterable: false, cellClassName: cell_highlight}, // Filter not working on date :( ; / Alt: width: 90, align: "center", cellsAlign: "center"
				{dataField: 'editor_name', text: "Edited by", width: 160, cellClassName: cell_highlight},
				{hidden: true, dataField: 'text', text: "Note's content", filterable: true}, // Note text doesn't display, but is used for searching/filtering
				{dataField: 'org_name', text: "Organization", width: 160, hidden: !cargohub.application.user_Is_Admin(), filterable: cargohub.application.user_Is_Admin(), cellClassName: cell_highlight}
			],
			rowDetails: true, initRowDetails: function(id, datarow, element, rowinfo) { // Row details renderer
				rowinfo.detailsHeight = datarow.text.split(/\r\n|\r|\n/).length * parseInt(element.css('line-height')) + 25;
				element.addClass('note-content').html(datarow.text.nl2br()).height(rowinfo.detailsHeight);
			}
		}, {
			// Callbacks (for events happening in the datatable itself) - In most cases it will be either View or Edit
			"getrows": function() { /* Nothing here atm */ },
			"rowselect": select_Note,
			"clearselect": select_Clear,
			"rowopen": open_Note
		}, "notes");
	}
	;

	this._public({
		/**
		 * Setup Notes Tab for Record Process module
		 */
		init: function()
		{
			init_Toolbar();
			init_DataTable();

			// Tooltips inside the Notes tab
			$("#tab_notes .info-tip").each(cargohub.components.ui.add_Tooltip);
		},

		/**
		 * Callback for actions to be performed when this tab is selected
		 */
		selected: function(params)
		{
			if(params && params.trigger)
				switch(params.trigger) {
				case "note-create":
					note_Editor('create');
					break;
				}
		}
	});

});