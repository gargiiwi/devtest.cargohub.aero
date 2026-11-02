/**
 * Module "Record Process", interface for the Operating the connected organizations (Data Sharing)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules.Record Process
 * @version 2.7
 */
cargohub.record_sharing = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,
		master = cargohub.record_process,
		is_admin = cargohub.application.user_Is_Admin(),

	// Data from the table containing currently selected row. Used for View, Edit, Delete - Set on any row operation.
	selected_row = null,

	// Source for the DataTable
	sharing_Source = new $.jqx.dataAdapter({
		url: '?action=get_sharing',
		data: {
			read_assigned: true
		},
		datatype: "json",
		datafields: [
			{"name": 'id', type: 'int', map: 'org>id'},
			{"name": 'master', type: 'bool'},
			{"name": 'name', type: 'string', map: 'org>name'},
			{"name": 'type_id', type: 'string', map: 'org>type_id'},
			{"name": 'priority_key', type: 'string'},
			{"name": 'priority', type: 'string'},
			{"name": 'status_key', type: 'string'},
			{"name": 'status', type: 'string'},
			{"name": 'assigned_to', type: 'int', map: 'assigned>id'},
			{"name": 'assigned_name', type: 'string', map: 'assigned>name'},
			{"name": 'assigned_contact', type: 'int', map: 'assigned>contact_id'},
			{"name": 'transfer_time', type: 'date', map: 'transfer>time'},
			// Admin only fields
			{"name": 'disabled', type: 'bool'},
			{"name": 'transfer_by', type: 'int', map: 'transfer>by'},
			{"name": 'transfer_to', type: 'int', map: 'transfer>to'}
			// TODO: Counters?
		],
		id: "id"
	}),

	// 17012025  Sharing Tab Add Track Section start
    $sharing_link_activity_viewer = $("#tab_sharing_link_activity #document-viewer"),
    $sharing_link_activity_editor = null,
    panel_sharing_link_activity_Source = new $.jqx.dataAdapter({
        url: "/record/process?action=load_list_sharing_link_activity", // At the moment using notes backend - That might change in the future !
        data: {
            link_id: master.config.id,
            track: master.config.track,
        },
        datatype: "json",
        datafields: [
            {name: 'id', type: 'string'},
            {name: 'track', type: 'string'},
            {name: 'type', type: 'string'},

        ],
        id: "id",
        sortcolumn: 'id',
        sortdirection: 'desc'
    }),
    // 17012025  Sharing Tab Add Track Section end
	/**
	 * An organization has been selected - Store information in preparation for a double-click ( for setting perspective ), or for clicking on one of the toolbar buttons
	 */
	select_Organization = function(row)
	{
		if(!is_admin)
			return;

		if(!selected_row) {
			donrey.cancel_Delay('hide-bar_sharing-row-ops');
			$('#tab_sharing #sharing-row-ops button').jqxButton({disabled: false});
			$('#tab_sharing #sharing-row-ops').fadeIn();
		}
		selected_row = row;

		// Check if the row is currently enabled or disabled and transform the Toggle button to match
		if(row.disabled)
			$('#tab_sharing #sharing-toggle').html('<img alt="On:" src="/img/icons16/lightbulb.png"> Enable Org.');
		else
			$('#tab_sharing #sharing-toggle').html('<img alt="Off:" src="/img/icons16/lightbulb_off.png"> Disable Org.');
	},

	/**
	 * No rows are currently selected - Make the toolbar buttons dissapear
	 */
	select_Clear = function()
	{
		if(!is_admin)
			return;

		selected_row = null;
		$('#tab_sharing #sharing-row-ops button').jqxButton({disabled: true});

		donrey.single_Delay('hide-bar_sharing-row-ops', function() {
			$('#tab_sharing #sharing-row-ops').fadeOut();
		}, 500);
	},

	/**
	 * Reloads the module from the perspective of another organization in the list - Panels might change with template overrides, visibility of data could be different.
	 */
	set_Perspective = function(row)
	{
		if(!is_admin)
			return;

		if(row)
			selected_row = row;

		if(selected_row.id == master.config.perspective)
			return cargohub.alert("Already viewing from the perspective of: <b>"+selected_row.name+"</b>", "Unnecessary operation", "information.png");

		if(selected_row.disabled)
			return cargohub.alert("Cannot view from the perspective of a disabled organization: <b>"+selected_row.name+"</b>", "Operation not possible");

		document.location = "/record/process:"+master.config.track+"?perspective="+selected_row.id+"#sharing";
	},

	/**
	 * Give ownership to the selected organization ( unless it's already the current owner )
	 */
	set_Master = function()
	{
		if(selected_row.id == master.config.master_org_id)
			return cargohub.alert("<b>"+selected_row.name+"</b> is already set as Master", "Unnecessary operation");

		if(!confirm("Are you sure you want to give Master access to this organization ?"+(selected_row.disabled? "\nThis will also reenable the organization's access.": "")))
			return;

		// Update department access list
		$.post("?action=set_master_org", {
			org_id: selected_row.id
		}, function(x) {
			if(x.success) {
				master.config.master_org_id = selected_row.id;
				self.reload();
				cargohub.alert(x.response, "Success !", "thumb_up.png");
			} else
				cargohub.its.http_fail(null, "Record.Sharing::set_master");
		})
		.fail(cargohub.its.http_fail);
	},

	/**
	 * Disables or Enables access for an organization which is not current master.
	 * This effectively makes the record invisible for users of that organization. No data is being removed, only a flag is switched in the database.
	 */
	toggle_Org = function()
	{
		if(selected_row.id == master.config.master_org_id)
			return cargohub.alert("<b>"+selected_row.name+"</b> is the current Master of this record", "Operation not possible");

		if(!confirm("Are you sure you want to "+(selected_row.disabled? "enable": "disable")+" access for this organization ?"))
			return;

		$.post("?action=toggle_org", {
			org_id: selected_row.id,
			new_state: selected_row.disabled
		}, function(x) {
			if(x.success) {
				self.reload();
				cargohub.alert(x.response, "Success !", "thumb_up.png");
			} else
				cargohub.its.http_fail(null, "Record.Sharing::toggle_org");
		})
		.fail(cargohub.its.http_fail);
	},

	/**
	 * Disconnects the selected organization from this record. Record-related organization data (IOD tabe) will be removed
	 * - along with any private attached data (files, tasks, notes, contact link, except for conversations which remain in the database as reference )
	 */
	detach_Org = function()
	{
		if(selected_row.id == master.config.master_org_id)
			return cargohub.alert("<b>"+selected_row.name+"</b> is the current Master of this record", "Unable to disconnect");

		if(selected_row.id == master.config.perspective)
			return cargohub.alert("Currently viewing the record as: <b>"+selected_row.name+"</b>. Change perspective first !", "Unable to disconnect");

		if(!confirm("Are you sure you want to disconnect this orgnization ?\nThere is no undelete !!"))
			return;

		$.post("?action=detach_org", {
			org_id: selected_row.id,
			remove_attached_data: true
		}, function(x) {
			if(x.success) {
				self.reload();
				cargohub.alert(x.response, "Success !", "thumb_up.png");
			} else
				cargohub.its.http_fail(null, "Record.Sharing::detach_org");
		})
		.fail(cargohub.its.http_fail);
	},

	/**
	 * Initialize the Sharing tab toolbar (Admin operations)
	 */
	init_Toolbar = function()
	{
		$("#tab_sharing .toolbar button")
		.jqxButton({ theme: cargohub.jqxtheme })
		/**
		 * Event: Toolbar button is clicked
		 */
		.on('click', function()
		{
			switch($(this).data('op')) {
			case "sharing-perspective":
				set_Perspective(); break;

			case "sharing-set_master":
				set_Master(); break;

			case "sharing-toggle":
				toggle_Org(); break;

			case "sharing-detach":
				detach_Org(); break;
			}
		});
	},


	// 17012025  Sharing Tab Add Track Section start 
	initSharingLinkActivityEditor = function (config)
	{
		
		if(config.mode == 'create_audit_activity'){
			document.getElementById("auditActivityForm").reset();
			$("#audit_activity_id").val(0);
			$("#isCreateTaskId").val(0);
			$('input[name="audit_status"][value="active"]').prop('checked', true);//23102024 Audit control 
			open_row_id = null;
		}
		$sharing_link_activity_editor = $("#tab_sharing_link_activity #document-editor");

		// config.expander.theme = cargohub.jqxtheme;
		$sharing_link_activity_editor.jqxExpander(config.expander);
		
		if (!config.expander)
		config.expander = {
			disabled: false,
			expanded: false
		};
		switch (config.mode) {
			case 'create_audit_activity':

			break;
		}
		editor_open = config.mode;
		$sharing_link_activity_editor.show().jqxExpander('enable');

		$sharing_link_activity_editor.jqxExpander('expand');
	   
		// Add tooltips for relevant elements
		$(".info-tip", $sharing_link_activity_editor).each(cargohub.components.ui.add_Tooltip);

		// Show the Save/Close controls
		$('#sharing-link-activity-open-ops button').jqxButton({disabled: false});
		$('#sharing-link-activity-open-ops').fadeIn();
	},
	initSharingLinkActivityOpen = function (row)
	{
		// Fadeout Create command when Editor or Viewer are active
		$('#tab_sharing_link_activity #sharing-link-activity-create-op').fadeOut();

		if (row)
			selected_row = row;

		$('#module_data_id').val(selected_row.id); 
		$('#ref_track_id').val(selected_row.track); 

		$('#tab_sharing_link_activity #sharing-link-activity-open-ops, #tab_sharing_link_activity').fadeIn();
		initSharingLinkActivityViewerClose(true);
		initSharingLinkActivityEditor({
			title: "Edit document: " + selected_row.ID,
			icon: "pencil.png",
			mode: "edit"
		});
	},
	initSharingLinkActivityViewerClose = function (transition)
	{
		if (!$sharing_link_activity_viewer) // Not yet initialized
			return;

		if ($sharing_link_activity_viewer.jqxExpander('expanded')) {
			$sharing_link_activity_viewer.on('collapsed', function () {
				// When finishing collapse... hide the editor
				$sharing_link_activity_viewer.hide();
				// ... and disable the event
				$sharing_link_activity_viewer.off('collapsed');
			});
			$sharing_link_activity_viewer.jqxExpander('collapse');
		} else {
			// If already collapsed, just hide it
			$sharing_link_activity_viewer.hide();
		}

		// Also fadeout the Close buttons
		$('#tab_sharing_link_activity #sharing-link-activity-open-ops button').jqxButton({disabled: true});
		$('#tab_sharing_link_activity #sharing-link-activity-open-ops').fadeOut();

		if (!transition) {
			if (selected_row)
				$('#tab_sharing_link_activity #sharing-link-activity-row-ops').fadeIn();
			// Show the "Create" button in case it was hidden by a previous operation
			$('#tab_sharing_link_activity #sharing-link-activity-create-op').fadeIn();
		}

		// viewer_open = false;

		return true;
	},
	initSharingLinkActivitySelectRow = function (row){
		// docs_manager_priv ||
		var edit_access = 
		(row.edit_access == 'shared'
		|| (row.edit_access == 'org' && (row.visibility == 'dep' || row.visibility == 'org'))
		|| (row.edit_access == 'dep' && row.dep_id == user_session.cdep_id)
		|| (row.edit_access == 'user' && row.editor_id == user_session.id)
		);


		// If no row was previously selected, show the available commands
		if (!selected_row) {
			donrey.cancel_Delay('hide-docs-row-ops');
			$('#tab_sharing_link_activity #sharing-link-activity-row-ops button').jqxButton({disabled: false});
			$('#tab_sharing_link_activity #sharing-link-activity-row-ops').fadeIn();
		}
		selected_row = row;

		// Show or hide the "Delete" button
		if (edit_access)
			$('#tab_sharing_link_activity .docs-write-ops').fadeIn();
		else
			$('#tab_sharing_link_activity .docs-write-ops').fadeOut();

		// $('#tab_sharing_link_activity .docs-write-ops').fadeOut();

		// Show or hide the Web-View section (if the document has web-view enabled or not)
		if (row.wv_key)
			$('#tab_sharing_link_activity #trainee-open-webview').show();
		else
		$('#tab_sharing_link_activity #trainee-open-webview').hide();
	},
	initSharingLinkActivityDelete = function ()
	{
		$.post("/record/process?action=Delete_sharing_link_activity", {
			id: selected_row.uid,
			link_id: master.config.id
		}, function (x) {
			cargohub.components.spinner.On("Success!", x.response, 2500, function () {
				cargohub.components.spinner.Off();
			});
			location.reload(); 

		}).fail(cargohub.its.http_fail);
	},
	initSharingLinkActivitySave = function ()
	{
		var ref_track_id = $('#ref_track_id').val();
		if (ref_track_id == '' || ref_track_id == undefined) {
			return cargohub.alert("Please input the track id!");
		}

		var submit_data = {
			'from_issue_id': master.config.id,
			'from_issue_track': master.config.track,
			'ref_track_id': $('#ref_track_id').val(),
			'id': $('#module_data_id').val(),
		};

		 saveTrackLink(submit_data).done(function (response) {
			console.log("response => ",response);
			if(response.isDuplicate == true){
				return cargohub.alert("This Track ID already exists.!");
			}else if(response.isInvalid == true){
				return cargohub.alert("Invalid Track ID!");
			}
			else{
				location.reload();
			}
			
		}).fail(function (error) {
			console.error("Error in the second request:", error);
		});

		
	},
	// 17012025  Sharing Tab Add Track Section end
	init_sharing_link_activity_Toolbar = function ()
	{
		// 17012025  Sharing Tab Add Track Section start
		$("#tab_sharing_link_activity .toolbar button").jqxButton({theme: cargohub.jqxtheme}).on('click', function (){
			console.log('case', $(this).data('op'));
			switch ($(this).data('op')) {
				case 'exportxlsx':
					$("#_sharing_link_activity_record").jqxDataTable('exportData', 'xls');
					break;
				case 'sharing-link-activity-create':
					$('#tab_sharing_link_activity').fadeIn(); // Make sure Save is enabled
					initSharingLinkActivityEditor({
							title: "Create a Module Data Sharing",
							icon: "add.png",
							mode: "create_audit_activity"
						});
						$('#tab_sharing_link_activity #sharing-link-activity-create-op').fadeOut(); // Hide the "Create" button when the editor is active, will be shown again on Save or Close

				break;
				case 'sharing-link-activity-delete':
					cargohub.components.modal.delcustomconfirm(initSharingLinkActivityDelete, "Are you sure you want to remove this record ?",null,null,null,null,'delete');
					break;
				case 'sharing-link-activity-save':
					initSharingLinkActivitySave();                           
				break;
				case 'sharing-link-activity-close':
					if (!$sharing_link_activity_editor) // Not yet initialized
					return;
					if ($sharing_link_activity_editor.jqxExpander('expanded')) {
						$sharing_link_activity_editor.on('collapsed', function () {
							// When finishing collapse... hide the editor
							$sharing_link_activity_editor.hide();
							// ... and disable the event
							$sharing_link_activity_editor.off('collapsed');
						});
						$sharing_link_activity_editor.jqxExpander('collapse');
					} else {
						// If already collapsed, just hide it
						$sharing_link_activity_editor.hide();
					}

					// Also fadeout the Save/Close buttons
					$('#tab_sharing_link_activity #sharing-link-activity-open-ops button').jqxButton({disabled: true});
					$('#tab_sharing_link_activity #sharing-link-activity-open-ops').fadeOut();
					$('#tab_sharing_link_activity #sharing-link-activity-delete').fadeOut();
					$('#tab_sharing_link_activity #sharing-link-activity-create-op').fadeIn();
					open_row_id = null;
					return true;
			}
		});
	},
	// 17012025  Sharing Tab Add Track Section start 

	/**
	 * Initialize the Sharing DataTable. (This uses common functionality from the main Record Process module - cargohub.record_process )
	 */
	init_DataTable = function()
	{
		/**
		 * Highlights the background of each cell in a row (effectively highlighting the row itself) to indicate the conditions:
		 * - Organization is current Master: Lime Green
		 * - Organization is disabled: Pale Red (Admin only)
		 * Normal organization (enabled) will remain white.
		 * Current perspective orgaization will have all it's text with "italic" style (Admin only)
		 */
		var cell_highlight = function(row, column, value, rowData) {
			var classname = "";
			if(rowData.master)
				classname+="grid-cell-master_org";
			else if(rowData.disabled)
				classname+="grid-cell-disabled_org";

			if(rowData.id == master.config.perspective)
				classname+=" grid-cell-perspective_org";
			return classname;
		};

		master.init_DataTable($("#tab_sharing div.jqx-datatable"), {
			// Config
			source: sharing_Source,
			columns: [
				{ text: "Organization", dataField: 'name', width: 400, cellClassName: cell_highlight},
				// Filtering is disabled on status and priority because the codes are cryptic (hgh, pre, snd... and so on)
				{ text: "Status", dataField: 'status_key', width: 120, cellClassName: cell_highlight, cellsRenderer: function(row, column, value, rowData) {
					return '<span style="display: inline-block;	height: 16px; background-repeat: no-repeat; background-image: url(/img/icons16/statuses/'+rowData.status_key+'.png);"><sup style="margin-left: 20px;">'+ rowData.status +'</sup></span>';
				}, filterable: false},
//				{ text: "Prio", dataField: 'priority_key', width: 40, align: 'center', cellsAlign: 'center', cellClassName: cell_highlight, cellsRenderer: function(row, column, value, rowData) {
//					return '<span style="display: inline-block;	width: 16px; height: 16px; background-image: url(/img/icons16/priorities/'+rowData.priority_key+'.png);"></span>';
//				}, filterable: false},
				{ text: "Assigned person", dataField: 'assigned_name', cellClassName: cell_highlight },
				{ text: "Transfer on", dataField: 'transfer_time', width: 160, cellsFormat: cargohub.datetime_format, cellClassName: cell_highlight, cellsRenderer: function(row, column, value, rowData) {
					return value? value: '<span class="gray">(not yet)</span>';
				}, filterable: false}
			],
			filterMode: "simple" // Just two columns are filterable
		}, {
			// Callbacks (for events happening in the datatable itself) - In most cases it will be either View or Edit
			"getrows": function() { /* Nothing here atm */ },
			"rowselect": select_Organization,
			"rowopen": set_Perspective,
			"clearselect": select_Clear
		}, "sharing");
	}


	// 17012025  Sharing Tab Add Track Section start 
    init_DataTable2 = function (){

		
        var cell_audit_highlight = function (row, column, value, rowData) {
            var classname = "";
            return classname;
        };
        master.init_DataTable($("#tab_sharing_link_activity div.jqx-datatable-2"), {
            exportSettings: {
                columnsHeader: true,
                hiddenColumns: false,
                serverURL: "/?action=export",
                characterSet: null,
                collapsedRecords: false,
                recordsInView: true,
                fileName: "sharing-docs"
            },
            // Config
            source: panel_sharing_link_activity_Source,
            columns: [
                {dataField: 'track', text: "Track Id", cellClassName: cell_audit_highlight, 'width': '50%',cellsrenderer: 
				function (row, column, value, rowData) { 
					if(rowData.type == null || rowData.type == ''){
						return value;
					}else{
						return '<span style="cursor: pointer;color:#166EB7;" data-url="/record/process:'+value+'#panels" class="goDetailFrmPnl">'+value+'</span>';
					}
                }},
                {dataField: 'type', text: "Module Type", cellClassName: cell_audit_highlight, 'width': '50%'},
            ]
        }, 
        {
            "rowselect": initSharingLinkActivitySelectRow,
            "clearselect": select_Clear,
            "rowopen": initSharingLinkActivityOpen
        }, "docs1");
    }
    // 17012025  Sharing Tab Add Track Section end 

	;

	// 17012025  Sharing Tab Add Track Section start
	function saveTrackLink(formData) {
        const formData1 = objectToFormData(formData);
        return $.ajax({
            url: "/record/process?action=save_track_link_by_sharing_tab",
            type: 'POST',
            data: formData1,
            contentType:false,
            cache: false,
            processData: false
        });
    }
    function objectToFormData(obj) {
        const formData = new FormData();
        for (const key in obj) {
            if (obj.hasOwnProperty(key)) {
                formData.append(key, obj[key]);
            }
        }
        return formData;
    }
	$(document).on('click', '.goDetailFrmPnl', function (e) {
		e.preventDefault();
		var url = $(this).data("url");
		window.open(url, '_blank');
	});
	// 17012025  Sharing Tab Add Track Section end
	this._public({
		/**
		 * Setup Sharing Tab for Record Process module
		 */
		init: function()
		{
			if(is_admin)
				init_Toolbar();
			init_DataTable();


			// 17012025  Sharing Tab Add Track Section start 
            console.log(" 5884 ");
            init_DataTable2();
            init_sharing_link_activity_Toolbar();
            // 17012025  Sharing Tab Add Track Section end 

			// Tooltips inside the Sharing tab
			$("#tab_sharing .info-tip").each(cargohub.components.ui.add_Tooltip);
		},

		/**
		 * External call to reload the sharing table (usually following a status, priority or assignment change)
		 */
		reload: function()
		{
			master.refresh_DataTable($("#tab_sharing div.jqx-datatable"));
		},

		/**
		 * Callback for actions to be performed when this tab is selected
		 */
		selected: function(params)
		{
			// Redraw the table when tab is selected...
			$("#tab_sharing div.jqx-datatable").jqxDataTable('refresh');
		}
	});
});