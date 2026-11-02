/**
 * Module "Record Process"->Section "Files", interface for the Uploading/Configurion and downloading the linked Files (document Attachments)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules.Record Process
 * @version 2019.01.30
 */
cargohub.record_files = donrey.BASE._extend(function() {
	// private
	var self = this,
	c = null,
	master = cargohub.record_process,
	edit_privilege = cargohub.application.user_Test_Privileges('issue_edit_attachs'), // TODO: Rename into "record_edit_files"
	edit_privilege_view_attachs = cargohub.application.user_Test_Privileges('issue_view_attachs'), // TODO: Rename into "issue_view_attachs"

	// Data from the table containing currently selected row. Used for View, Edit, Delete - Set on any row operation.
	selected_row = null,

	// Dropzone component
	file_uploader_ready = false,
	file_uploader_instance_key = "tabfiles",

	// Source for the DataTable
	files_Source = new $.jqx.dataAdapter({
		url: "/files?action=get_list",
		data: {
			link: "issue",
			link_id: master.config.id
		},
		datatype: "json",
		datafields: [
			{name: 'id', type: 'int'},
			{name: 'visibility', type: 'string'},
			{name: 'mimetype', type: 'string'},
			{name: 'analyzer', type: 'string'},
			{name: 'metadata', type: 'string'},	// Used to show the image preview
			{name: 'doctype', type: 'string'},
			{name: 'user_id', type: 'int', map: 'user>id'},
			{name: 'user_name', type: 'string', map: 'user>name'},
			{name: 'org_id', type: 'string', map: (cargohub.application.user_Is_Admin()? 'org>id': 'org_id')},
			{name: 'org_name', type: 'string', map: 'org>name'},
			{name: 'access_orgs', type: 'string'},
			{name: 'name', type: 'string'},
			{name: 'title', type: 'string'},	// Description or file name, depending if description is set or not
			{name: 'ext', type: 'string'},
			{name: 'size', type: 'int'},
			{name: 'mtime', type: 'date'},
			{name: 'chash', type: 'string'},
			{name: 'description', type: 'string'},
			{name: 'labels', type: 'string'}
		],
		// Initial Sorting
		sortcolumn: 'mtime',
		sortdirection: 'desc',
                pagesize: 50, //IWI fetch files 50 per page
	}),

	// File visibility selector TODO: Get the texts from Language files
	file_visibility_source = [
		{k: 'private', text: '<span class="access16 visi-private"></span> Private attachment, only visible to the uploader'},
		{k: 'dep', text: '<span class="access16 visi-dep"></span>Visible for the uploader\'s department'},
		{k: 'org', text: '<span class="access16 visi-org"></span>Everyone in the owner organization can see it'},
		{k: 'shared', text: '<span class="access16 visi-shared"></span>Organizations sharing this '+master.config.type.name},
		{k: 'public', text: '<span class="access16 visi-public"></span>No visibility restrictions'}
	],

	/**
	 * A file row has been selected - Store information in preparation for a double-click ( Configure Window ), or for clicking on one of the toolbar buttons or the download link
	 */
	select_File = function(row)
	{
		if(!selected_row) {
			donrey.cancel_Delay('hide-bar_files-select');
			if(edit_privilege)
				$('#tab_files #files-row-editops').fadeIn();
			$('#tab_files #files-row-ops button').jqxButton({disabled: false});
			$('#tab_files #files-row-ops').fadeIn();

		}
		selected_row = row;
		// Prepare the download link
		$("#tab_files #dl-link").attr('href', "/files?action=download_attachment&ch="+selected_row.chash+"&qp="+donrey.qp_conv(selected_row.id));
	},

	/**
	 * No rows are currently selected - Make the toolbar buttons dissapear
	 */
	select_Clear = function()
	{
		selected_row = null;
		$('#tab_files #files-row-editops button').jqxButton({disabled: true});

		donrey.single_Delay('hide-bar_files-select', function() {
			if(edit_privilege)
				$('#tab_files #files-row-editops').fadeOut();
			$('#tab_files #files-row-ops').fadeOut();
		}, 500);
	},

	upload_default_visibility_index = 2,
	master_org_index = null,

	/**
	 * Setup for the upload window, containing Dropzone and two dropdown selectors
	 */
	setup_Upload = function($window)
	{
		var
		$access_orgs = $('#uploads-access_orgs', $window),
		uploaded_files = {
			error: 1	// Beings with Error code "1" - Empty dropzone
		},
		/**
		 * Helper: Links a list of succesfully uploaded files to the current record
		 * @param on_success_CB Callback for when the linking is successful
		 */
		link_pre_files = function(on_success_CB) {
			$.post("?action=link_pre_files", {
				file_ids: JSON.stringify(uploaded_files.ids),
				upload_size: uploaded_files.size,
				org_id: master.config.perspective, // Current organization (perspective for admin - Not useful for non-admin as it will default to the user's organization anyway)
				visibility: $('#uploads-visibility', $window).val(),
				access_orgs: $access_orgs.val()
			}, function(x) {
				if(on_success_CB)
					on_success_CB(x);
			})
			.fail(cargohub.its.http_fail);
		};

		// Setup Dropzone instance
		if(!file_uploader_ready) {
			master.file_uploader.create_Instance(file_uploader_instance_key,
				{	// Config
					container: $("#files-dropzone", $window),
					height: 290
				},
				{	// Events
					/**
					 * Ready event - When Dropzone finished loading, stuff can be performed (For example adding mock-files?)
					 */
					on_ready: function() {
						file_uploader_ready = true;
					},
					/**
					 * Content event - At least one file has been dropped, or a mock-file is prepared
					 */
					on_content: function() {
						$("#files-dropzone-helper", $window).css('visibility', "visible").fadeTo(1);
						$('#modal_files-upload_cancel', $window).css({
							backgroundColor: "#C40",
							color: "white"
						}).text("Cancel uploads");
					},
					/**
					 * Empty event - The dropzone is empty of files or mock-files. Reset the window
					 */
					on_empty: function() {
						uploaded_files = {error: 1};

						$('#modal_files-upload_ok', $window).jqxButton({disabled: false}).jqxButton('render');
						$('#modal_files-upload_cancel', $window).css({
							backgroundColor: "inherit",
							color: "inherit"
						}).text("Close the window");

						$("#files-dropzone-helper", $window).fadeTo(0);
					},
					/**
					 * File Sending event - A file has begun uploading. Disable the OK button until upload is complete
					 */
					on_filesending: function() {
						$('#modal_files-upload_ok', $window).jqxButton({disabled: true}).jqxButton('render');
					},
					// - on_filesuccess		A file has completed uploading
					// - on_fileremove		A file or mock file has been removed via a remove link
					/**
					 * Upload Complete event - All queued files have finished uploading. Allow pressing the OK button
					 */
					on_uploadcomplete: function(files)
					{
						uploaded_files = files;
						$('#modal_files-upload_ok', $window).jqxButton({disabled: false}).jqxButton('render');
					}
				}
			);
		}

		$('#uploads-visibility', $window).jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: file_visibility_source,
			displayMember: "text", valueMember: "k",
			width: 428,	height: 24, autoDropDownHeight: true,
			selectedIndex: upload_default_visibility_index,
			placeHolder: "Please select visibility for all new uploads"
		})
		/**
		 * Event - The visibility selector is changed.
		 */
		.on('select', function(event) {
			if(event.args.item.value == "shared") {
				$access_orgs.parent().show();
				$access_orgs.jqxDropDownList('open');
			} else
				$access_orgs.parent().hide();
		});

		// Access IDs selector (for sharing mode)
		$access_orgs.jqxDropDownList({
			theme: cargohub.jqxtheme,
			source: master.source_Sharing(function(records) {
				for(c=0; c< records.length; c++) {
					if(records[c].id == master.config.perspective) {
						// Check the item for the perspective organization and disable turning off
						master_org_index = c;
						$access_orgs.jqxDropDownList('updateAt', {
							value: master.config.perspective,
							label: records[c].name+" [my org]",
							checked: true
						}, c);
						$access_orgs.jqxDropDownList('checkIndex', c);
						$access_orgs.jqxDropDownList('disableAt', c);
					}
				}
			}),
			checkboxes: true,
			displayMember: "name", valueMember: "id",
			width: 428,	height: 24, autoDropDownHeight: true,
			renderer: function(index, label, value) {
				var sho = master.dataSources.shareList.records[index];
				label="<span class='right'>("+sho.type_id+")</span>"+label;
				if(sho.owner)
					label+=" <b>[my org]</b>";
				if(sho.disabled)
					label+=" [disabled]";
				return label;
			}
		});

		// Setup the action for "OK" button
		/**
		 * OK button event connects the uploaded files to the record
		 * (Just closing the window does not, like the cancel uploads button)
		 */
		$('#modal_files-upload_ok', $window).off('click').on('click', function()
		{
			if(uploaded_files.error)
				switch(uploaded_files.error) {
					// Dropzone is empty, nothing to do, just close the window
					case 1:
						$window.jqxWindow('close');
						return;
					// Some errors while uploading
					case 3:
						cargohub.alert("Some files failed upload or were cancelled during transfer. Check the individual items for errors.");
						return;
				}

			if(uploaded_files.ids && uploaded_files.ids.length) {
				link_pre_files(function(x) {
					// clear Dropzone as we are done, but without triggering the remove events (that includes server-side delete)
					master.file_uploader.empty(file_uploader_instance_key, true);

					// Close the window, update the attachments panel and display a message
					$window.jqxWindow('close');
					master.refresh_DataTable($("#tab_files div.jqx-datatable"));

					if(x.count)
						cargohub.components.hashnav.update_Tab_Header(3, "Files <span class='fiftycent'>["+x.count+"]</span>", "/img/icons16/attach.png");
					// Finally, show the operation result
					master.toolbar_Report("div#tab_files", x.response);
				});
			} else
				cargohub.alert("Unknown error in the upload mechanism. Try emptying the window with the <b class='red'>Cancel uploads</b> button");
		});

		$('#modal_files-upload_cancel', $window).off('click').on('click', function() {
			if(uploaded_files.error)
				switch(uploaded_files.error) {
					// Dropzone is empty, nothing to do, just close the window
					case 1:
						$window.jqxWindow('close');
						break;
					// Uploads in progress
					case 2:
						if(!confirm("Uploads are still in progress. Are you sure you want to cancel all uploads ?"))
							return;
						master.file_uploader.empty(file_uploader_instance_key);
						break;
					// Some errors while uploading
					case 3:
						if(!confirm("Some uploads were not completed successfully. Are you sure you want to cancel all uploads ?"))
							return;
						master.file_uploader.empty(file_uploader_instance_key);
						break;
				}
			else
				if(uploaded_files.ids && uploaded_files.ids.length) {
					if(!confirm("Files are already uploaded. Are you sure you want to discard them ?"))
						return;
					master.file_uploader.empty(file_uploader_instance_key);
				}

			$window.jqxWindow('close');
		});
	},

	/**
	 * Opens the upload window, based on Dropzone component
	 */
	files_Upload = function()
	{
		// Nothing changes between uses, just reopen existing window
		if(cargohub.components.modal.windows['files-upload']) {
			return cargohub.components.modal.reopen('files-upload', function($window) {
				// Reset visibility controls after the window is reopened (Dropzone was reset when it was originally closed, either by cancelling or by finishing uploads)
				var $access_orgs = $('#uploads-access_orgs', $window);
				$('#uploads-visibility', $window).jqxDropDownList('selectIndex', upload_default_visibility_index);
				$access_orgs.jqxDropDownList('uncheckAll');
				$access_orgs.jqxDropDownList('enableAt', master_org_index);
				$access_orgs.jqxDropDownList('checkIndex', master_org_index);
				$access_orgs.jqxDropDownList('disableAt', master_org_index);
				$access_orgs.parent().hide();
			});
		}

		// This modal doesn't have a close button, instead it has a special "Cancel" button that will remove any uploaded files
		// Instead, if pressing Ok, the uploaded files are linked to the record
		cargohub.components.modal.create('files-upload', {
			buttons: {cancel: "Close the window", ok: "Ok"},
			btn_roles: {},
			showCloseButton: false,
			btn_styles: {cancel: "float: left; margin-left: 0; background-image: none; background-color: inherit !important; color: inherit !important;"}
		});

		cargohub.components.modal.open('files-upload', {
			width: 650,	height: 480,
			title: "Upload files for: " + master.config.type.name+ " - "+master.config.track,
			content: $('#win-upload_files').html().present({
				_itc: cargohub.input_theme_classes
			}),
			callback: setup_Upload
		});
	},

	/**
	 * Opens the file attachment configuration window, used to change access for the record, set labels and perform other operations
	 */
	file_Config = function(row)
	{
		if(row)
			selected_row = row;

		selected_row.mtime_formatted = $.format.date(selected_row.mtime, cargohub.datetime_format);
		selected_row.qp = donrey.qp_conv(selected_row.id);

		cargohub.components.modal.create('file-config', {
			btn_roles: {cancel: "cancel"}
		});

		cargohub.components.modal.open('file-config', {
			width: 600,	height: 350,
			title: "Configure attached file for: " + master.config.type.name+ " - "+master.config.track,
			content: $('#template-file-config').html().present({
				_itc: cargohub.input_theme_classes,
				file: selected_row,
				master: master.config
			}),
			callback: function($window) // Setup the controls inside the window
			{
				// Visibility selector
				var visibility_index = -1, $access_orgs = $('#file-access_orgs', $window);
				for(c = 0; c< file_visibility_source.length; c++)
					if(selected_row.visibility == file_visibility_source[c].k)
						visibility_index = c;
				$('#file-visibility', $window).jqxDropDownList({
					theme: cargohub.jqxtheme,
					source: file_visibility_source,
					displayMember: "text", valueMember: "k",
					width: 428,	height: 24, autoDropDownHeight: true,
					selectedIndex: visibility_index
				})
				/**
				 * Event - The visibility selector is changed.
				 */
				.on('select', function(event) {
					if(event.args.item.value == "shared") {
						$access_orgs.parent().show();
						$access_orgs.jqxDropDownList('open');
					} else
						$access_orgs.parent().hide();
				});

				// If visibility is in sharing mode, show the orgs selector:
				if(visibility_index == 3)
					$access_orgs.parent().show();

				// Access IDs selector (for sharing mode)
				$access_orgs.jqxDropDownList({
					theme: cargohub.jqxtheme,
					source: master.source_Sharing(function(records) {
						var current_shares = selected_row.access_orgs? selected_row.access_orgs.split(","): [];
						for(c=0; c< records.length; c++) {
							if(records[c].id == selected_row.org_id) {
								// Check the item for the current organization and disable turning off
								$access_orgs.jqxDropDownList('updateAt', {
									value: selected_row.org_id,
									label: records[c].name+" [owner]",
									checked: true
								}, c);
								$access_orgs.jqxDropDownList('checkIndex', c);
								$access_orgs.jqxDropDownList('disableAt', c);
							} else if($.inArray(records[c].id.toString(), current_shares) > -1)
								// Turn on the rest considering current "shared_orgs" item in "selected_row"
								$access_orgs.jqxDropDownList('checkIndex', c);
						}
					}),
					checkboxes: true,
					displayMember: "name", valueMember: "id",
					width: 428,	height: 24, autoDropDownHeight: true,
					renderer: function(index, label, value) {
						var sho = master.dataSources.shareList.records[index];
						label="<span class='right'>("+sho.type_id+")</span>"+label;
						if(sho.owner)
							label+=" <b>[owner]</b>";
						if(sho.disabled)
							label+=" [disabled]";
						return label;
					}
				});

				// Setup the action for "OK" button
				$('#modal_file-config_ok', $window).off('click').on('click', function()
				{
					// submit the create/edit form using ajax
					$('.modal_content > form', $window).ajaxSubmit({
						data: {
							id: selected_row.id,
							link_id: master.config.id // ID check for link - The file is not updated if link_id is missing or wrong
						},
						dataType: 'json',
						beforeSubmit: function(formData, jqForm, options) {
							// Checks if file name has not been emptied
							if($("#file-name", $window).val().length < 5) {
								cargohub.alert("The name of the file should have at least 5 characters.");
								return false;
							}
							return true;
						},
						success: function(x) {
							if(x.success) {
								// note has been updated - Close the window / Refresh the datapanel / Update the tab title to include the returned number of notes (counter)
								$window.jqxWindow('close');
								master.refresh_DataTable($("#tab_files div.jqx-datatable"));

								// Finally, show the operation result
								master.toolbar_Report("div#tab_files", x.response);
							} else
								cargohub.its.http_fail(null, "Record.Files::config_file");
						},
						error: cargohub.its.http_fail
					});
				});

				// Add tooltips
				$('.info-tip', $window).each(cargohub.components.ui.add_Tooltip);
			}
		});
	},

	/**
	 * Setup the window used for pasting files (duplicate the sources) from the Clipboard into the current record
	 */
	setup_Files_Clipboard = function($window)
	{
		var
		// DataAdapter for showing the clipboard content
		fclip_dataAdapter = new $.jqx.dataAdapter({
			url: "/files?action=Clipboard",
			datatype: "json",
			datafields: [
				{name: 'file_id', type: 'int'},
				{name: 'name', type: 'string'},
				{name: 'mimetype', type: 'string'},
				{name: 'doctype', type: 'string'},
				{name: 'size', type: 'int'},
				{name: 'chash', type: 'string'},
				{name: 'added', type: 'date'}
			],
			// Initial Sorting
			sortcolumn: 'name',
			sortdirection: 'asc'
		}),

		/**
		 * Unlinks (Removes) one or more existing clipboard files (clips)
		 *
		 * @param array ids
		 * @param callback on_Complete Extra function to execute after attachment has been unlinked
		 */
		remove_From_Clipboard = function(ids, on_Complete)
		{
			$.post("/files?action=Remove_Clips", {
				file_ids: JSON.stringify(ids)
			}, function(x)
			{
				if(on_Complete)
					on_Complete(x);
			})
			.fail(cargohub.its.http_fail);
		},
		/**
		 * Copies an existing metadata of a file into the current record
		 *
		 * @param int id
		 */
		paste_File = function(clip)
		{
			$.post("?action=File_Paste", {
				link_type: 'issue',
				link_id: master.config.id,	// Linked record ID
				chash: clip.chash
			}, function(x) {
				// Nothign here; Grid is refreshed when the clipboard window is closed
			})
			.fail(cargohub.its.http_fail);
		},
		pasted = false,		// Becomes True on first paste (to refresh the grid on closing the Clipboard modal)
		pasted_ids = [];	// Used to show the prompt on closing the window

		// #files-clipboard
		master.init_DataTable($("#files-clipboard", $window), {
			// Config
			localization: {
				emptydatastring: "The clipboard is currently empty"
			},
			width: 720,
			source: fclip_dataAdapter,
			columns: [
				{hidden: true, dataField: 'doctype', text: "File type", filterable: true}, // Searchable by document type
				{dataField: 'mimetype', text: "Type", width: 50, filterable: false, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
					return '<img src="/img/mime/'+rowData.mimetype+'.png" alt="'+rowData.ext+'" />';
				}},
				{dataField: 'chash', text: "Down!", width: 50, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
					return '<a href="/files?action=download_attachment&ch='+rowData.chash+'&qp='+donrey.qp_conv(rowData.file_id)+'" title="Download this file"><img style="border: none;" src="/img/icons/download_cloud.png" alt="Down!" /></a>';
				}, sortable: false, filterable: false},
				{dataField: 'name', text: "File name", filterable: true},
				{dataField: 'file_id', text: "Operations", width: 110, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
					return	'<img style="border: none;" src="/img/icons/page_paste.png" alt="Paste" class="pasteclip-button" data-row="'+row+'" title="Paste this file" /> &nbsp; '+
							'<img style="border: none;" src="/img/icons/delete.png" alt="Del!" class="deleteclip-button" data-row="'+row+'" title="Remove from clipboard (non-destructive)" />';
				}, sortable: false, filterable: false}
			],
			rowDetails: true, initRowDetails: function(id, datarow, element, rowinfo) { // Row details renderer
				var tpl_data = clone(datarow);
				// Shiny...
				tpl_data.size = number_format(tpl_data.size);
				rowinfo.detailsHeight = 60;
				element.addClass('datatable att-details').html($("#template-clipfile-details").html().present(tpl_data)).height(rowinfo.detailsHeight);
			}
		}, {
			// Callbacks (for events happening in the datatable itself)
			"rowopen": function(row, $c) { // Opens the details on doubleclick
				$c.jqxDataTable('showDetails', $c.data('selected_index'));
			},
			"getrows": function() {
				if(fclip_dataAdapter.records.length > 0)
					$("#fclipsize").show().text(" ("+fclip_dataAdapter.records.length+")");
				else
					$("#fclipsize").text("").hide();
			}
		}, "fclip");

		// Event for the attachment deletion button
		$("#files-clipboard")
		/**
		 * The button to "Paste" the clipboard file into the record
		 */
		.on("click", ".pasteclip-button", function() {
			var row_data = fclip_dataAdapter.records[$(this).data('row')];

			if(!row_data.original_name) { // Only do this once
				paste_File(row_data);
				row_data.original_name = row_data.name;
				row_data.name = row_data.original_name + " <span class='gray'>(pasted)</span>";
				pasted_ids.push(parseInt(row_data.file_id));

				// Show the file as "Pasted"
				$("#files-clipboard").jqxDataTable('updateRow', $("#files-clipboard").data('selected_index'), row_data);
			} else
				return cargohub.alert("The file <b>"+ row_data.original_name+ "</b> has already been pasted into the record");

			pasted = true;
		})
		/**
		 * The button to "Paste" the clipboard file into the record
		 */
		.on("click", ".deleteclip-button", function() {
			if(!confirm("Are you sure you want to remove the file from Clipboard ?"))
				return;

			var file_id = fclip_dataAdapter.records[$(this).data('row')].file_id,
				srch = $.inArray(file_id, pasted_ids);
			if(srch > -1)
				pasted_ids.splice(srch, 1);

			if(fclip_dataAdapter.records.length > 0)
				$("#fclipsize").text(" ("+fclip_dataAdapter.records.length+")");
			else
				$("#fclipsize").text("").hide();

			remove_From_Clipboard([file_id], function() {
				// Refresh the Clipboard after deletion
				master.refresh_DataTable($("#files-clipboard", $window));
			});
		});

		// On Finished (Ok), the window is closed and the inline preview shows number of attachments and total size
		$('#modal_files-clipboard_ok').off('click').on('click', function()
		{
			if(pasted_ids.length > 0)
				if(confirm(pasted_ids.length+" file(s) have been pasted in the record and are still in the Clipboard.\nDo you wish to remove them ?"))
					remove_From_Clipboard(pasted_ids, function() {
						var clips_left = fclip_dataAdapter.records.length - pasted_ids.length;
						if(clips_left > 0)
							$("#fclipsize").text(" ("+clips_left+")");
						else
							$("#fclipsize").text("").hide();
					});

			// close the window
			$window.jqxWindow('close');
		});

		// On Empty Clipboard button, ask for the mass unlink of clips for the current user
		$('#modal_files-clipboard_clear').off('click').on('click', function()
		{
			if(!confirm("Are you sure you want to empty the Clipboard ?"))
				return;

			// Empty the clipboard
			$.post("/files?action=Remove_Clips", {
				empty_clipboard: true
			}, function(x)
			{
				$("#fclipsize").text("").hide();
				$window.jqxWindow('close');

				cargohub.alert("Clipboard has been emptied !", "Success !", "thumb_up.png");
			})
			.fail(cargohub.its.http_fail);
		});

		$window.on('close', function() {
			if(pasted)
				master.refresh_DataTable($("#tab_files div.jqx-datatable"));
		});
	},

	/**
	 * Initialize the Files tab toolbar (Upload, Configure, Download files)
	 * -- Important: Downloading is done via a link made to look as a button which the user MUST click manually. The browser's security do not allow JavaScript to trigger a download process within the same page
	 * (Alternatively, the download could be initialted withing a hidden <iframe>, however that's in the "gray" area and not recommended since it could be blocked by popup blockers)
	 */
	init_Toolbar = function()
	{
		$("#tab_files .toolbar button, #tab_files .toolbar a")
		.jqxButton({ theme: cargohub.jqxtheme })
		/**
		 * Event: Toolbar button is clicked
		 */
		.on('click', function()
		{
			switch($(this).data('op')) {
			case "upload":
				files_Upload();
				break;
			case "file-config":
				file_Config();
				break;
			// Direct operation, file is being linked into Clipboard (unless it's already there)
			case "file-copy":
				$.post("?action=File_CopyClip", {
					link_id: master.config.id,	// Linked record ID
					chash: selected_row.chash
				}, function(x) {
					// New clipboard size returned, make visible and update the number beside the "Clipboard" button
					if(x.fclipsize)
						$("#fclipsize").text(" ("+x.fclipsize+")").show();
				})
				.fail(cargohub.its.http_fail);
				break;
			case "file-remove":
				if(!confirm("Are you sure you want to delete the selected attached file?"))
					return;

				// Local Action method for now (the function needs to be aware of where the file is disconnected from)
				$.post("?action=Delete_File", {
					link_id: master.config.id,	// Linked record ID
					chash: selected_row.chash,
					org_id: master.config.perspective// Current organization (perspective for admin - Not useful for non-admin as it will default to the user's organization anyway)
				}, function(x) {
					master.refresh_DataTable($("#tab_files div.jqx-datatable"));
					// Update counter on deletion (should be reduced by one compared to current if deleting from the perspective organization)
					if(x.update_count)
						cargohub.components.hashnav.update_Tab_Header(3, "Files"+(x.count? " <span class='fiftycent'>["+x.count+"]</span>": ""), "/img/icons16/attach.png");
					// Update clipboard size (just in case this file was in the clipboard)
					if(x.fclipsize)
						$("#fclipsize").text(" ("+x.fclipsize+")").show();
					cargohub.alert(x.response, "Success !", "thumb_up.png");
				})
				.fail(cargohub.its.http_fail);
				break;

			case "clipboard":
				cargohub.components.modal.create('files-clipboard', {
					buttons: {clear: "Empty Clipboard and Close", ok: "Finished"},
					btn_roles: {},
					showCloseButton: false,
					btn_styles: {clear: "float: left; margin-left: 0; background-image: none; background-color: #C40; color: white;"}
				});

				cargohub.components.modal.open('files-clipboard', {
					width: 735,	height: 470,
					title: "Files Clipboard",
					content: $('#win-files_clipboard').html().present({
						_itc: cargohub.input_theme_classes
					}),
					callback: setup_Files_Clipboard
				});
				break;

			}
		});
		if(edit_privilege)
			$('#tab_files #files-editops').fadeIn();
		if(edit_privilege_view_attachs)
			$('#tab_files #files-editops').fadeIn();

		// Prepare the ZIP Package link
		$("#tab_files #dl-zip").attr('href', "/files?action=Download_ZIP_Package&link=issue&link_id="+master.config.id+"&track="+master.config.track);
	},

	/**
	 * Initialize the Files DataTable. (This uses common functionality from the main Record Process module - cargohub.record_process )
	 */
	init_DataTable = function()
	{
		/**
		 * Highlights the background of each cell in a row (effectively highlighting the row itself) to indicate the conditions:
		 * - Shared files: Yellow
		 * - User private file: Pale Red
		 * Current perspective orgaization will have all it's text with "italic" style (Admin only)
		 */
		var cell_highlight = function(row, column, value, rowData) {
			var classname = "";

			if(rowData.visibility == 'shared' || rowData.visibility == 'public')
				classname+="grid-cell-shared";
			else if(rowData.visibility == 'private')
				classname+="grid-cell-private";

			// Mark files for the perspective org for the admin user
			if(cargohub.application.user_Is_Admin() && rowData.org_id == master.config.perspective)
				classname+=" grid-cell-perspective_org";
			return classname;
		};

		master.init_DataTable($("#tab_files div.jqx-datatable"), {
			// Config
//                        IWI pagination file tab start
                            pageable: true,
        //                IWI pagination file tab end
			source: files_Source,
			columns: [
				{dataField: 'visibility', text: '<span class="access16 visi-public"></span>', width: 25, filterable: false, cellsRenderer: function(row, column, value, rowData) {
					return '<span class="access16 visi-'+rowData.visibility+'"></span>';
				}, cellClassName: cell_highlight},
				{hidden: true, dataField: 'doctype', text: "File type", filterable: true}, // Searchable by document type
				{dataField: 'mimetype', text: "Preview", width: 90, filterable: false, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
					if(rowData.analyzer == "image") {
						var metadata = JSON.parse(rowData.metadata);
						if(metadata && metadata.scaler && metadata.scaler.thms)
							return '<img src="/uimg/thms/'+rowData.chash.substr(0,2)+ '/'+ rowData.chash.substr(2)+ '.jpg" alt="'+rowData.ext+'" />';
					}
					return '<img src="/img/mime/'+rowData.mimetype+'.png" alt="'+rowData.ext+'" />';
				}, cellClassName: cell_highlight},
				{dataField: 'title', text: "File description or Name", cellClassName: cell_highlight, filterable: true},
				{dataField: 'mtime', text: "Uploaded on", width: 160, cellsFormat: cargohub.datetime_format, filterable: false, cellClassName: cell_highlight}, // Filter not working on date :(
				{dataField: 'user_name', text: "Uploaded by", width: 160, cellsRenderer: function(row, column, value, rowData) {
					if(rowData.user_id)
						return value;
					else
						return '<span class="gray">(from public area)</span>';
				}, cellClassName: cell_highlight},
				{dataField: 'chash', text: "Down!", width: 50, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
					return '<a href="/files?action=download_attachment&ch='+rowData.chash+'&qp='+donrey.qp_conv(rowData.id)+'"><img style="border: none;" src="/img/icons/download_cloud.png" alt="Down!" /></a>';
				}, cellClassName: cell_highlight, sortable: false, filterable: false},
				{hidden: true, dataField: 'labels', text: "Labels", filterable: true}, // File labels also aren't displayed directly in the grid, but also filterable by them
				{dataField: 'org_name', text: "Organization", width: 160, hidden: !cargohub.application.user_Is_Admin(), filterable: cargohub.application.user_Is_Admin(), cellClassName: cell_highlight}
			],
			rowDetails: true, initRowDetails: function(id, datarow, element, rowinfo) { // Row details renderer
				var tpl_data = clone(datarow), metadata = null;
				// Process labels, by splitting them, so they can be colorized in the template
				if(tpl_data.labels) {
					tpl_data.labels = tpl_data.labels.split(",");
					for(c=0; c<tpl_data.labels.length; c++)
						tpl_data.labels[c] = "<span class='attlabel'>"+$.trim(tpl_data.labels[c])+"</span>";
					tpl_data.labels = tpl_data.labels.join(" &middot; ");
				} else
					delete tpl_data.labels;
				// Shiny...
				tpl_data.size = number_format(tpl_data.size);
				switch(tpl_data.visibility) {
					case 'private': tpl_data.visi_text = "Private file, visible only to the uploader"; break;
					case 'dep': tpl_data.visi_text = "Uploader's department members"; break;
					case 'org': tpl_data.visi_text = "The uploader's organization"; break;
					case 'shared': tpl_data.visi_text = "Sharing this "+master.config.type.name;
						if(tpl_data.access_orgs)
							tpl_data.visi_text+= " ("+ tpl_data.access_orgs.split(",").length+ " organizations)";
						break;
					case 'public': tpl_data.visi_text = "No access restrictions"; break;
					default: tpl_data.visi_text = "(Unknown, possible database error)";
				}

				// If the row contains image metadata (and the analyzer is of type "image" it means we have a thumbnail to display - use the "thms" version
				if(datarow.analyzer == "image") {
					metadata = JSON.parse(datarow.metadata);
					if(metadata && metadata.scaler && metadata.scaler.thml)
						tpl_data._thml = "/uimg/thml/"+ datarow.chash.substr(0,2)+ '/'+ datarow.chash.substr(2)+ '.jpg';
					rowinfo.detailsHeight = metadata.scaler.thml.height;
				} else
					rowinfo.detailsHeight = 100;
				element.addClass('datatable att-details').html($("#template-file-details").html().present(tpl_data)).height(rowinfo.detailsHeight);
			}
		}, {
			// Callbacks (for events happening in the datatable itself) - In most cases it will be either View or Edit
			"getrows": function(rows) {
				// Updates master.config.files
				master.config.files = [];
				for(c=0; c<rows.length; c++)
					master.config.files.push({
						'id': rows[c].id,
						'title': rows[c].title.substring(0, 40),
						'visibility': rows[c].visibility
					});
			},
			"rowselect": select_File,
			"clearselect": select_Clear,
			"rowopen": file_Config
		}, "files");
                
                
                // Auto-resize the files table height so last row is always visible
//function adjustFilesTableHeight() {
//    $dt.jqxDataTable({ height: $("#tab_files div.jqx-datatable").find('.jqx-grid-content').height() });
//}
//
//// run when data loads
//$("#tab_files div.jqx-datatable").on('bindingComplete', adjustFilesTableHeight);



                // Auto-resize the files table height so last row is always visible
                function adjustFilesTableHeight() {
                    var $dt = $(this);
                    var contentHeight = $dt.find('.jqx-grid-content').height();

                    if (contentHeight) {
                        $dt.jqxDataTable({ height: contentHeight });
                    }
                }

                // Run when data loads
                $("#tab_files div.jqx-datatable").off('bindingComplete', adjustFilesTableHeight).on('bindingComplete', adjustFilesTableHeight);

	};

	this._public({
		/**
		 * Setup Files Tab for Record Process module
		 */
		init: function()
		{
			// We need DropzoneJS here
			master.load_file_uploader();

			init_Toolbar();
			init_DataTable();

			// Tooltips inside the Files tab
			$("#tab_files .info-tip").each(cargohub.components.ui.add_Tooltip);
		},

		/**
		 * Callback for actions to be performed when this tab is selected
		 */
		selected: function(params)
		{
			if(params && params.trigger)
				switch(params.trigger) {
				case "files-upload":
					files_Upload(); break;
				}
		}
	});

});