/**
 * Module "Issue Types List" (Admin only)
 * -- Display all Issue Types in the system ( For now, only admin has access to this. In the (far) future, certain organizations might be able to manage their own issue types )
 */
cargohub.admin_ist_list = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,
		selected_ist = null, // Selected issue type in the grid

	/**
	 * Sets up the toolbar with operations related to organizations
	 */
	setup_Toolbar = function()
	{
		// Buttons to add/edit/remove an user from the department
		$('.toolbar button')
		.jqxButton({ theme: cargohub.jqxtheme })
		.on('click', function() {
			var data = $(this).data();

			switch(data.op) {
				case 'create':
					// Opens the issue create form
					document.location = "?action=create";
					break;

				case 'details':
					// Opens the Issue Details page in which all fields are visible
					document.location = "?action=details&id="+ selected_ist.id;
					break;

				case 'edit':
					// Opens the Communcation module into administrative mode (admin only), which loads messages and contacts for the specified organization instead of the logged in user's organization
					document.location = "?action=edit&id="+ selected_ist.id;
					break;

				case 'count':
					// Does a recount of all issues of a certain type and then reloads the grid.
					$.post("?action=count_issues", {
						ist_id: selected_ist.id
					}, function(x) {
						if(x.success) {
							cargohub.its.alert(x.response, "Success !", "thumb_up.png");
							$("#ist_grid").jqxGrid('updatebounddata');
						} else
							cargohub.its.alert(x.reason, "Message from server", "events.png");
					});
					break;

				case 'delete':
					if(selected_ist.scope == "global" || selected_ist.feat_lock)
						return cargohub.its.alert("Captain, Red Alert, we have a warp core breach!", "Scotty shouts:", "ncc1701.png");

					// Double confirmation (for now) TODO: Make a popup in which a captcha of some kind needs to be inserted to allow orgnaization deletion
					if(data.confirm)
						if(!confirm("Are you sure you want to "+ data.confirm_msg))
							return;

					if(!confirm("! WARNING !\n\nIssue Type \""+selected_ist.name+"\" is about to be removed:\nALL DATA of that issue type will get wiped out !"+
						"\n\nThere is no way to recover the data !!!\n\nPlease click OK if you REALLY want to proceed."))
						return;

					$.post("?action=delete_ist", {
						ist_id: selected_ist.id
					}, function(x) {
						if(x.success)
							$("#ist_grid").jqxGrid('updatebounddata');
						else {
							cargohub.its.alert(x.reason, "Message from server", "events.png");
						}
					});
					break;
			}
		});
	},

	/**
	 * Sets up the issue types display/search grid
	 */
	setup_Issuetypes_Grid = function()
	{
		var
		// the data definition
		gridDataFields = [
			{name: 'id', type: 'int'},
			{name: 'scope'},
			{name: 'key'},
			{name: 'name'},
			{name: 'short_plural'},
			{name: 'description'},
			{name: 'count_issues', type: 'int'}
		],
		// the column definition
		gridColumns = [
			{text: "ID", dataField: 'id', width: 55, cellsalign: "right", filtertype: "number"},
			{text: "Scope", dataField: 'scope', width: 75, filtertype: "checkedlist", cellsalign: "center",
				createfilterwidget: function (column, columnElement, widget) {
					widget.jqxDropDownList({
						dropDownWidth: 110,
						autoDropDownHeight: true,
						animationType: "fade"
					});
				}
			},
			{text: "Name", dataField: 'name', width: 180,
				cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
					// Href for new module ( incomplete ) /admin/rtedit?id='+ listGridAdapter.records[row].id+ '
					return '<div style="text-overflow: ellipsis; text-align: center; overflow: hidden; padding-bottom: 2px; margin-top: 5px;"><a href="/admin/ist?action=edit&id='+ listGridAdapter.records[row].id+ '">' + value + '</a></div>';
				},
			},
			{text: "Issues", dataField: 'count_issues', width: 55, cellsalign: "right", filtertype: "number"},
			{text: "Plural form", dataField: 'short_plural', width: 120},
			{text: "Folder Key", dataField: 'key', width: 120, filtertype: "checkedlist" },
			{text: "Description", dataField: 'description'}
		],
		// prepare the data
		listGridAdapter = new $.jqx.dataAdapter({
			url: '?action=get_list',
			datatype: "json",
			datafields: gridDataFields
		});

		// ... and init the grid
		$("#ist_grid").jqxGrid({
			theme: cargohub.jqxtheme,
			source: listGridAdapter,

			width: cargohub.fullwidth, autoheight: true,
			columns: gridColumns, columnsresize: true, columnsmenu: false,
			selectionmode: "singlerow",	altrows: true,
			pageable: true,	pagesize: 20, pagesizeoptions: [10, 20, 50, 100],
			filterable: true, showfilterrow: true, sortable: true
		})
		/**
		 * Event: When a row is selected, the name of the issue type is displayed on the toolbar, and is show with fade in (when selecting a row first time)
		 */
		.on('rowselect', function (event)
		{
		    var row = event.args.row;
			$("span#selected_ist").html(row.name);
			if(!selected_ist) {
				$("#ist_edit_operations button").jqxButton({disabled: false});
				donrey.cancel_Delay('hide_bar_edit_ops');

				$('#ist_edit_operations').fadeIn();
			}
			// Disable the delete button on global issue types (the ones used by everyone). Type needs to be manually changed in the database before deletion becomes available.
			// However, new ones start as "admin" only and can be edited until their type changes.
			if(row.scope == "global" || row.feat_lock)
				$("#ist_edit_operations #button-delete").jqxButton({disabled: true});
			else
				$("#ist_edit_operations #button-delete").jqxButton({disabled: false});

		    selected_ist = row;
		})
		/**
		 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
		 */
		.on('filter', function(event)
		{
			if(selected_ist) {
				$(this).jqxGrid('clearselection');
				selected_ist = null;
				$("span#selected_ist").empty();
				$("#ist_edit_operations button").jqxButton({disabled: true});

				donrey.single_Delay('hide_bar_edit_ops', function() {
					$("#ist_edit_operations").fadeOut();
				}, 500);
			}
		})
		/**
		 * Event: On doubleclicking, do the same as simple clicking then using the View/Edit button
		 */
		.on('rowdoubleclick', function(){
			$('.toolbar button[data-op="edit"]').trigger("click");
		});
	};

	this._public({
		/**
		 * Section initialization
		 */
		init: function()
		{
			setup_Toolbar();
			setup_Issuetypes_Grid();

			// Info Explander
			$(".info-expander").jqxExpander({
				theme: cargohub.jqxtheme,
				expanded: true
			});
		}
	});
});
