/**
 * Module "Administration Center", Tab "Activity Log"
 * Instances:
 * - cargohub.baseclass.fullgrid
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2020.05.21
 */

cargohub.admin_log = cargohub.baseclass._instance(cargohub.baseclass.fullgrid, function() {
	// private
	var self = this,
		c = null,
		today = moment().set({hour: 0, minute: 0, second: 0, millisecond: 0}),
		master = cargohub.admin_center,

	// Toolbar related
	operations_bar = "#tab_log #log-row-ops",

	// Grid related vars
	grid_Source = null,
	selected_row = null,
	open_row_id = null,		// ID of the item that is open in either viewer or editor
	edit_mode = null,		// True when open for editing, False when open for viewing, null when nothing is open

	$item_viewer = $("#tab_log #log-viewer"),
	item_viewer_Ready = false,	// Item viewer Expander is ready to be shown

	$item_editor = $("#tab_log #log-editor"),
	item_editor_Ready = false,	// Item editor Expander is ready to be shown

	AREA_OPTIONS = [
		{value: "var", text: "Various/Unknown/Undecided"},
		{value: "addition", text: "New feature or element added"},
		{value: "change", text: "Some part is changed"},
		{value: "fix", text: "A problem was found and fixed"},
		{value: "config", text: "Configuration updated"},
		{value: "rollback", text: "Roll-back to previous version"},
		{value: "-", text: "<hr />", disabled: true},
		{value: "bpe", text: "AUTO: Blueprint Editor", disabled: true}
	],

	/**
	 * Open the item for viewing (no editable form elements or controls - just HTML)
	 */
	item_Viewer = function(config)
	{
		if(!config)
			config = {};

		if(item_viewer_Ready) {
			// Update expander with new settings (if necessary)
			if(config && config.expander)
				$item_viewer.jqxExpander(config.expander);

		} else {
			// Init the expander itself on first use
			if(!config.expander)
				config.expander = {
					disabled: false,
					expanded: false
				};
			config.expander.theme = cargohub.jqxtheme;
			$item_viewer.jqxExpander(config.expander);

			item_viewer_Ready = true;
		}
		$item_viewer.show();

		// Set/Change title (header)
		if(!config.title)
			config.title = "Log item viewer";
		$item_viewer.jqxExpander('setHeaderContent', '<img src="/img/icons16/'+ (config.icon || 'comment.png')+ '" alt="[:]" class="inline-icon"> '+ config.title);

		var row_data = $.extend({}, selected_row);
		// Check if there's overrides or defaults for the data/values - Process "row_data" before rendering the template
		if(config.overrides)
			row_data = $.extend(row_data, config.overrides);

		// Prepare some derived information from task data
		row_data.dt_format = moment(row_data.dt).format("YYYY-MM-DD (dddd) HH:mm:ss")+ ' <span class="gray">('+today.diff(row_data.dt, 'days')+ ' days ago)</span>';

		// Render the task template into the body of the expander
		$item_viewer.jqxExpander('setContent', $('#template-log-viewer').html().present(row_data));
		// Add tooltips for relevant elements
		$(".info-tip", $item_viewer).each(cargohub.components.ui.add_Tooltip);

		$item_viewer.jqxExpander('expand');

		// Show the Close control
		donrey.cancel_Delay('hide-log-open-ops');
		$('#tab_log .log-open-ops button').jqxButton({disabled: false});
		$('#tab_log .log-open-ops').fadeIn();
	},

	/**
	 * Closes the item viewer
	 */
	close_Viewer = function()
	{
		if($item_viewer.jqxExpander('expanded')) {
			$item_viewer.on('collapsed', function() {
				// When finishing collapse... hide the editor
				$item_viewer.hide();
				// ... and disable the event
				$item_viewer.off('collapsed');
			});
			$item_viewer.jqxExpander('collapse');
		} else {
			// If already collapsed, just hide it
			$item_viewer.hide();
		}

		// Also fadeout the Close button
		$('#tab_log .log-open-ops button').jqxButton({disabled: true});
		$('#tab_log .log-open-ops').fadeOut();

		if(selected_row)
			$(operations_bar).fadeIn();

		$("#tab_log #log-table-title").hide();
		return true;
	},

	/**
	 * Editor for log items
	 */
	item_Editor = function(config)
	{
		if(!config)
			config = {};

		if(item_editor_Ready) {
			// Update expander with new settings (if necessary)
			if(config && config.expander)
				$item_editor.jqxExpander(config.expander);

		} else {
			// Init the expander itself on first use
			if(!config.expander)
				config.expander = {
					disabled: false,
					expanded: false
				};
			config.expander.theme = cargohub.jqxtheme;
			$item_editor.jqxExpander(config.expander);

			$("#log-area", $item_editor).jqxDropDownList({
				theme: cargohub.jqxtheme,
				source: AREA_OPTIONS,
				displayMember: "text", valueMember: "value",
				width: 230, dropDownWidth: 230,
				autoDropDownHeight: true, animationType: "fade", enableBrowserBoundsDetection: true
			});

			// Setup Calendar and Clock inputs
			$(".calendar", $item_editor).each(cargohub.components.ui.calendar_input);
			$(".theclock", $item_editor).each(cargohub.components.ui.clock_input);

			// Add tooltips for relevant elements
			$(".info-tip", $item_editor).each(cargohub.components.ui.add_Tooltip);

			item_editor_Ready = true;
		}
		$item_editor.show();

		// Set/Change title (header)
		if(!config.title)
			config.title = "Log item editor";
		$item_editor.jqxExpander('setHeaderContent', '<img src="/img/icons16/'+ (config.icon || 'pencil.png')+ '" alt="[/]" class="inline-icon"> '+ config.title);

		open_row_id = selected_row.uid;
		var row_data = $.extend({}, selected_row);
		// Check if there's overrides or defaults for the data/values - Process "row_data" before rendering the template
		if(config.overrides)
			row_data = $.extend(row_data, config.overrides);

		// Set values for the current line
		$('#log-user_name', $item_editor).text(row_data.user_name);
		$('#log-area', $item_editor).val(row_data.area);
		$('#log-date-day', $item_editor).val(row_data.dt);
		$('#log-date-time', $item_editor).timeEntry('setTime', moment(row_data.dt).format("HH:mm:ss"));
		$('#log-comment', $item_editor).val(row_data.comment);

		$item_editor.jqxExpander('expand');

		// Show the Close control
		donrey.cancel_Delay('hide-log-open-ops');
		$('#tab_log .log-open-ops button').jqxButton({disabled: false});
		$('#tab_log .log-open-ops').fadeIn();
	},

	close_Editor = function()
	{
		if($item_editor.jqxExpander('expanded')) {
			$item_editor.on('collapsed', function() {
				// When finishing collapse... hide the editor
				$item_editor.hide();
				// ... and disable the event
				$item_editor.off('collapsed');
			});
			$item_editor.jqxExpander('collapse');
		} else {
			// If already collapsed, just hide it
			$item_editor.hide();
		}

		// Also fadeout the Close?Save buttons
		$('#tab_log .log-open-ops button').jqxButton({disabled: true});
		$('#tab_log .log-open-ops, #tab_log .log-write-ops').fadeOut();

		if(selected_row)
			$(operations_bar).fadeIn();

		$("#tab_log #log-table-title").hide();
		return true;
	},

	save_Editor = function()
	{
		var
		submit_data = {
			id: open_row_id,
			item: {
				area: $('#log-area', $item_editor).val(),
				comment: $('#log-comment', $item_editor).val()
			}
		},
		day = $('#log-date-day', $item_editor).val(),
		time = $('#log-date-time', $item_editor).val();

		if(submit_data.item.comment.length < 5)
			return cargohub.alert("The log item should have at least 5 characters");
		if(!day.length)
			return cargohub.alert("A date must be set");
		if(!time.length)
			return cargohub.alert("A time must be set");

		submit_data.item.dt = day+ ' '+ time;

		$($item_editor).find('form').ajaxSubmit({
			dataType: 'json',
			url: "/admin/log?action=Save",
			data: submit_data,
			success: function(x) {
				$("#tab_log #log-grid").jqxGrid('updatebounddata');

				cargohub.components.spinner.On("Success!", x.response, 3500, function() {
					cargohub.components.spinner.Off();
				});
				close_Editor();
			},
			error: cargohub.its.http_fail
		});
	},

	/**
	 * A record row has been selected - Store information in preparation for a double-click ( for Previewing or Opening ), or for clicking on one of the toolbar buttons
	 */
	select_Row = function(event)
	{
		if(!selected_row) {
			donrey.cancel_Delay('hide-ops_bar');
			$(operations_bar+ ' button').jqxButton({disabled: false});
			$(operations_bar).fadeIn();
		}

		if(event.args.row)
			selected_row = event.args.row;
	},

	/**
	 * No rows are currently selected - Make the toolbar buttons dissapear
	 */
	select_Clear = function()
	{
		selected_row = null;
		$(operations_bar+ ' button').jqxButton({disabled: true});
		donrey.single_Delay('hide-ops_bar', function() {
			$(operations_bar).fadeOut();
		}, 500);

		$("#tab_log #log-grid").jqxGrid('clearselection');
	},

	/**
	 * Sets up the progress log grid
	 */
	setup_Log_Grid = function()
	{
		// Source for the Online Users Grid
		grid_Source = self.source({
			url: "/admin/log?action=Read_Log",
			data: {
				virtualmode: true
			},
			datatype: "json",
			datafields: [
				{name: 'id', type: 'int'},
				{name: 'user_id', type: 'int'},
				{name: 'user_name', type: 'string'},
				{name: 'area', type: 'string'},
				{name: 'dt', type: 'date'},
				{name: 'comment', type: 'string'}
			],
			root: "data",
			id: "id",
			cache: false,
			filter: function() {
				$("#tab_log #log-grid").jqxGrid('updatebounddata', 'filter');
			},
			sort: function() {
				$("#tab_log #log-grid").jqxGrid('updatebounddata', 'sort');
			},
			beforeprocessing: function(source) {
				this.totalrecords = source.found_rows;
			},
			/**
			 * Client-Side pre-processing of filter data
			 */
			formatData: cargohub.its.jqxGrid_FormatFilters,
			// Initial Sorting
			sortcolumn: 'dt',
			sortdirection: 'desc'
		});

		self.column({text: "Responsible", datafield: 'user_name', width: 170}); // User name
		self.column({text: "Area", datafield: 'area', width: 80, cellsalign: "center", filtertype: "checkedlist", filteritems: ["var", "addition", "change", "fix", "config", "rollback", "logerr", "bpe"],
			createfilterwidget: function (column, columnElement, widget) {
				widget.jqxDropDownList({
					dropDownWidth: 110,
					autoDropDownHeight: true,
					animationType: "fade"
				});
			}
		});
		self.column({text: "Date", dataField: 'dt', width: 110, cellsformat: "yyyy-MM-dd", cellsalign: "center", filtertype: "range"});
		self.column({text: "Description", datafield: 'comment', sortable: false});

		// Init the grid as persistent in session
		self.init_Grid($("#tab_log #log-grid"), {
			width: cargohub.fullwidth - 14,
			virtualmode: true, // virtual state
			rendergridrows: function(params) { return params.data; },
			pageable: true, pagesizeoptions: [10, 25, 50, 100, 250], pagesize: 25
		}, {
			persistent: true,
			grid_id: 'admin-log'
		})
		/**
		 * Event: When a row is selected, the editing commands appear
		 */
		.on('rowselect', select_Row)
		/**
		 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
		 */
		.on('filter', select_Clear)
		/**
		 * Event: On doubleclicking, do the same as simple clicking then using the Edit button
		 */
		.on('rowdoubleclick', function() {
			$('#tab_log .toolbar button[data-op="item-view"]').trigger("click");
		});
	},

	/**
	 * Sets up the toolbar with operations related to progress log
	 */
	setup_Toolbar = function()
	{
		// Buttons to add/edit/remove an user from the department
		$('.toolbar button')
		.jqxButton({ theme: cargohub.jqxtheme })
		.on('click', function() {
			var data = $(this).data();

			switch(data.op) {
			// Simple export function
			case 'exportxlsx':
				$("#tab_log #log-grid").jqxGrid('exportdata', "xls", "grid-export", true, null, false, "/?action=export");
				break;

			// Add the text in "New log entry" input to the database
			case 'log-post':
				var comment = $("#tab_log #log-new").val().trim();
				if(comment.length < 5)
					return cargohub.alert("The log item should have at least 5 characters");

				$.post("/admin/log?action=Post", {
					comment: comment
				}, function(x) {
					$("#tab_log #log-new").val("");
					$("#tab_log #log-grid").jqxGrid('updatebounddata');
					cargohub.components.spinner.On("Success!", x.response, 3500, function() {
						cargohub.components.spinner.Off();
					});

					// Refocus the input, to allow chaining multiple entries (long text)
					$("#tab_log #log-new").focus();
				})
				.fail(cargohub.its.http_fail);
				break;
			// Show the item in a quick read-only mode (Default action for Dbl-Click)
			case 'item-view':
				edit_mode = false;
				$item_editor.hide();
				$(operations_bar).fadeOut();
				item_Viewer();
				break;
			// Show the item editor to allow modifications to the item
			case 'item-edit':
				edit_mode = true;
				$item_viewer.hide();
				$(operations_bar).fadeOut();
				$('#tab_log .log-write-ops').fadeIn();
				item_Editor({
					title: "Edit item: "+ selected_row.id,
					icon: "pencil.png"
				});
				break;
			// Removes the item from the database after confirmation
			case 'item-delete':
				cargohub.components.modal.delconfirm(function() {
					$.post("/admin/log?action=Delete", {
						id: selected_row.id
					}, function(x) {
						select_Clear();

						$("#tab_log #log-grid").jqxGrid('updatebounddata');
						cargohub.components.spinner.On("Success!", x.response, 2500, function() {
							cargohub.components.spinner.Off();
						});
					})
					.fail(cargohub.its.http_fail);
				}, "Are you sure you want to delete the log entry?");

				break;

			// Save existing editor content
			// TODO: Warn if no changes have been made
			case 'item-save':
				save_Editor();
				select_Clear(); // Clear selection as order of rows might have changed after the update
				open_row_id = null;
				edit_mode = null;
				break;

			// Closes the viewer or the editor
			case "item-close":
				if(edit_mode)
					close_Editor();
				else
					close_Viewer();
				open_row_id = null;
				edit_mode = null;
				break;

			// Removes any sorting, filters and paging from the grid, bringing it to the default (sort by date descending)
			case "grid-state-reset":
				if(!confirm("Are you sure you want to reset the grid ?"))
					return;

				$.post("/global/operations?action=Save_Grid_State", {
					grid_id: "admin-log",
					state: false
				}, function(x) {
					// Reload the page after clearing the state
					document.location.reload();
				})
				.fail(cargohub.its.http_fail);
				break;
			}
		});

		// The "New log entry" input
		$("#tab_log #log-new").on('keydown', function(e) {
			if(e.key == "Enter")
				$('#tab_log button[data-op="log-post"]').trigger('click');
		});
	};

	this.init = function()
	{
		setup_Toolbar();

		// Initial grid state
		if(!self.config.grid_state) {
			self.config.grid_state = {
				sort: {
					column: 'dt',
					direction: 'desc'
				}
			};
		}
		setup_Log_Grid();

		// Info Explander
		$("#tab_log .info-expander").jqxExpander({
			theme: cargohub.jqxtheme,
			expanded: false
		});
	};
});