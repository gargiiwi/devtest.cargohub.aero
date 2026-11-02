/**
 * Module "Record Process"->Section "E-mail" - Compose and send e-mails
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules.Record Process
 * @version 2019.02.08
 */
cargohub.record_email = donrey.BASE._extend(function()
{
	// private
	var self = this,
	c = null,
	master = cargohub.record_process,

	// Used as a callback for when tab has finished loading (including the required components)
	on_Ready = null,
	// Sender contact - May be the main user contact or one of the aliases
	sender = null,

	// All the attachments data for the next E-mail being sent (Modified by "Build e-mail" or the "Attachments" window)
	attachments = {},
	attachments_warning_maxsize = 10485760, // 10 MB
 
	// NOT USED YET:
	file_uploader_ready = false,	// Indicates if DropboxJS component is already loaded and the editor used here is initialized
	file_uploader_instance_key = "emailattach",

	/**
	 * Prepares the "File Attachments" modal that has the following roles:
	 * - Show existing attachments and how they will be sent ("Included" in the E-mail or "External Links")
	 * - Allow directly uploading new files via DropZone ("drafts", until E-mail is sent)
	 * - Allow importing existing files from the Files tab (New files are not created, use existing IDs)
	 * - Allow converting Documents into HTML files which will be attached ("drafts", until E-mail is sent)
	 */
	setup_Attachments = function($window, start_zone)
	{
		var
		attach_zones = {},						// Which zones are already initialized ?
		current_zone = start_zone||"existing",	// The "Zone" of the attachments window that is currently active.
		// Zone: New attachments
		uploaded_files = {
			error: 1	// Beings with Error code "1" - Empty dropzone
		},
		// DataAdapter for showing the list of current attachments in a table
		attachments_Source = new $.jqx.dataAdapter({
			datatype: "json",
			datafields: [
				{name: 'id', type: 'int'},
				{name: 'mimetype', type: 'string'},
				{name: 'analyzer', type: 'string'},
				{name: 'metadata', type: 'string'},	// Used to show the image preview
				{name: 'doctype', type: 'string'},
				{name: 'name', type: 'string'},
				{name: 'title', type: 'string'},	// Description or file name, depending if description is set or not
				{name: 'ext', type: 'string'},
				{name: 'size', type: 'int'},
				{name: 'mtime', type: 'date'},
				{name: 'chash', type: 'string'},
				{name: 'description', type: 'string'}
			],
			// Initial Sorting
			sortcolumn: 'title',
			sortdirection: 'asc',
			localdata: attachments.files
		}),
		// DataAdapter for showing the record files inside the Attachments window
		importfiles_Source = new $.jqx.dataAdapter({
			url: "/files?action=get_list",
			data: {
				link: "issue",
				link_id: master.config.id
			},
			datatype: "json",
			datafields: [
				{name: 'id', type: 'int'},
				{name: 'mimetype', type: 'string'},
				{name: 'analyzer', type: 'string'},
				{name: 'metadata', type: 'string'},	// Used to show the image preview
				{name: 'doctype', type: 'string'},
				{name: 'name', type: 'string'},
				{name: 'title', type: 'string'},	// Description or file name, depending if description is set or not
				{name: 'ext', type: 'string'},
				{name: 'size', type: 'int'},
				{name: 'mtime', type: 'date'},
				{name: 'chash', type: 'string'},
				{name: 'description', type: 'string'}
			],
			// Initial Sorting
			sortcolumn: 'title',
			sortdirection: 'asc'
		}),
		// DataAdapter for showing the record documents in the Attachments window
		importdocs_Source = new $.jqx.dataAdapter({
			url: "/global/note", // At the moment using notes back-end
			data: {
				action: "Get_List",
				link: "issue",
				link_id: master.config.id,
				content_type: "html",
				order: "`nte`.title ASC, `nte`.version DESC"
			},
			datatype: "json",
			datafields: [
				{name: 'id', type: 'int'},
				{name: 'name', type: 'string', map: 'title'}, // Originally called "title" on notes in the database
				{name: 'description', type: 'string'},
				{name: 'version', type: 'number'},
				{name: 'dt', type: 'date'}
			],
			id: "id",
			// Initial Sorting
			sortcolumn: 'version',
			sortdirection: 'desc'
		}),

		/**
		 * Bleach and disable the "Clear" button
		 */
		clear_bleach = function()
		{
			$('#modal_email-attachments_clear', $window).css({
				backgroundColor: "inherit",
				color: "inherit"
			}).text("No attachments").jqxButton({disabled: true});
		},

		/**
		 * Activate the "Clear" button
		 */
		clear_flameON = function()
		{
			$('#modal_email-attachments_clear', $window).css({
				backgroundColor: "#C40",
				color: "white"
			}).text("Remove all attachments").jqxButton({disabled: false});
		},

		/**
		 * Zone: Existing - Prepares the table listing current attachments
		 */
		setup_Existing = function()
		{
			// Necessary for Source
			if(attachments.error && attachments.error == 1) {
				// No error, just empty (no attachments)
				attachments = {files: []};
			}
			if(!attachments.files)
				attachments.files = [];

			master.init_DataTable($("#attach-datatable", $window), {
				// Config
				localization: {
					emptydatastring: "No files are currently attached"
				},
				width: 750,
				source: attachments_Source,
				columns: [
					{hidden: true, dataField: 'doctype', text: "File type", filterable: true}, // Searchable by document type
					{dataField: 'mimetype', text: "Preview", width: 90, filterable: false, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
						if(rowData.analyzer == "image") {
							var metadata = JSON.parse(rowData.metadata);
							if(metadata.scaler && metadata.scaler.thms)
								return '<img src="/uimg/thms/'+rowData.chash.substr(0,2)+ '/'+ rowData.chash.substr(2)+ '.jpg" alt="'+rowData.ext+'" />';
						}
						return '<img src="/img/mime/'+rowData.mimetype+'.png" alt="'+rowData.ext+'" />';
					}},
					{dataField: 'chash', text: "Down!", width: 50, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
						return '<a href="/files?action=download_attachment&ch='+rowData.chash+'&qp='+donrey.qp_conv(rowData.id)+'" title="Download this file"><img style="border: none;" src="/img/icons/download_cloud.png" alt="Down!" /></a>';
					}, sortable: false, filterable: false},
					{dataField: 'title', text: "File description or Name", filterable: true},
					{dataField: 'id', text: "Delete", width: 60, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
						return '<img style="border: none;" src="/img/icons/delete.png" alt="Del!" class="delete-attachment-button" data-row="'+row+'" title="Delete this E-mail attachment" />';
					}, sortable: false, filterable: false}
				],
				rowDetails: true, initRowDetails: function(id, datarow, element, rowinfo) { // Row details renderer
					var tpl_data = clone(datarow), metadata = null;
					// Shiny...
					tpl_data.size = number_format(tpl_data.size);
					// If the row contains image metadata (and the analyzer is of type "image" it means we have a thumbnail to display - use the "thms" version
					if(datarow.analyzer == "image") {
						metadata = JSON.parse(datarow.metadata);
						if(metadata.scaler && metadata.scaler.thml)
							tpl_data._thml = "/uimg/thml/"+ datarow.chash.substr(0,2)+ '/'+ datarow.chash.substr(2)+ '.jpg';
						rowinfo.detailsHeight = metadata.scaler.thml.height;
					} else
						rowinfo.detailsHeight = 60;
					element.addClass('datatable att-details').html($("#template-attachment-details").html().present(tpl_data)).height(rowinfo.detailsHeight);
				}
			}, {
				// Callbacks (for events happening in the datatable itself)
				"rowopen": function(row, $c) {
					// Opens the details on doubleclick
					$c.jqxDataTable('showDetails', $c.data('selected_index'));
				}
			}, "attachments");

			// Event for the attachment deletion button
			$("#attach-datatable")
			/**
			 * Any "contact-item" anywhere in the module would allow viewing the contact information. For editing, there's specialized functions:
			 */
			.on("click", ".delete-attachment-button", function() {
				if(!confirm("Are you sure you want to remove this attachment ?"))
					return;

				self.unlink_Attachment($(this).data('row'), function() {
					// Update the table
					attachments_Source._source.localdata = attachments.files;
					master.refresh_DataTable($("#attach-datatable", $window));
					update_Title();

					// If by doing this action we're left with no attachments, bleach and disable the "Clear" button
					if(attachments.count == 0)
						clear_bleach();
				});
			});

			attach_zones.existing = true;
		},
		/**
		 * Zone: Uploadnew - Setup Dropzone instance
		 */
		setup_UploadNew = function()
		{
			master.file_uploader.create_Instance(file_uploader_instance_key,
				{ // Config
					container: $("#email-dropzone", $window),
					height: 344
				},
				{ // Events
					/**
					 * Ready event - When Dropzone finished loading, stuff can be performed (For example adding mock-files?)
					 */
					on_ready: function()
					{
						attach_zones.uploadnew = true;
					},
					/**
					 * Content event - At least one file has been dropped, or a mock-file is prepared
					 */
					on_content: function()
					{
						$("#email-dropzone-helper", $window).css('visibility', "visible").fadeTo(1);
					},
					/**
					 * Empty event - The dropzone is empty of files or mock-files. Reset the window
					 */
					on_empty: function()
					{
						$("#email-dropzone-helper", $window).fadeTo(0);
						uploaded_files = {
							error: 1	// Beings with Error code "1" - Empty dropzone
						};
					},
					/**
					 * File success event - A file has finished uploading, add it to the "attachments" grid and update the preview/counters
					 */
					on_filesuccess: function(file)
					{
						if(!attachments.files || attachments.count == 0)
							attachments.files = [];
						// Append the new file to "attachments"
						attachments.files.push(file.details);

						// Update the general attachments info and the title
						self.update_Attach_Info();
						update_Title();

						// And activate the Clear all button
						clear_flameON();
					},
					/**
					 * File Remove event - Cancelling a file (and deleting the Pre). Remove from the "attachments" grid and update the preview/counters
					 */
					on_fileremove: function(file)
					{
						// Locate the index of the file in the "attachments", and splice it
						var srch = $.inArray(parseInt(file.id), attachments.ids);
						if(srch > -1) // Found!
							attachments.files.splice(srch, 1);

						// Update the general attachments info and the title
						self.update_Attach_Info();
						update_Title();

						// If none left, bleach and disable the Clear button
						if(!attachments.count)
							clear_bleach();
					},
					/**
					 * Completed uploads event
					 * then move to the "Current attachments zone" to show them
					 */
					on_uploadcomplete: function(files)
					{
						uploaded_files = files;
					}
				}
			);
		},
		/**
		 * Zone: Import Files - Prepares the table listing current attachments
		 */
		setup_ImportFiles = function()
		{
			master.init_DataTable($("#importfiles-datatable", $window), {
				// Config
				localization: {
					emptydatastring: "No files are available for attaching"
				},
				width: 750,
				source: importfiles_Source,
				columns: [
					{hidden: true, dataField: 'doctype', text: "File type", filterable: true}, // Searchable by document type
					{dataField: 'mimetype', text: "Preview", width: 90, filterable: false, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
						if(rowData.analyzer == "image") {
							var metadata = JSON.parse(rowData.metadata);
							if(metadata.scaler && metadata.scaler.thms)
								return '<img src="/uimg/thms/'+rowData.chash.substr(0,2)+ '/'+ rowData.chash.substr(2)+ '.jpg" alt="'+rowData.ext+'" />';
						}
						return '<img src="/img/mime/'+rowData.mimetype+'.png" alt="'+rowData.ext+'" />';
					}},
					{dataField: 'chash', text: "Down!", width: 50, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
						return '<a href="/files?action=download_attachment&ch='+rowData.chash+'&qp='+donrey.qp_conv(rowData.id)+'" title="Download this file"><img style="border: none;" src="/img/icons/download_cloud.png" alt="Down!" /></a>';
					}, sortable: false, filterable: false},
					{dataField: 'title', text: "File description or Name", filterable: true},
					{dataField: 'id', text: "Attach", width: 60, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
						return '<img style="border: none;" src="/img/icons/attach_add.png" alt="Att" class="importfile-button" data-row="'+row+'" title="Attach this file to the E-mail" />';
					}, sortable: false, filterable: false}
				],
				rowDetails: true, initRowDetails: function(id, datarow, element, rowinfo) { // Row details renderer
					var tpl_data = clone(datarow), metadata = null;
					// Shiny...
					tpl_data.size = number_format(tpl_data.size);
					// If the row contains image metadata (and the analyzer is of type "image" it means we have a thumbnail to display - use the "thms" version
					if(datarow.analyzer == "image") {
						metadata = JSON.parse(datarow.metadata);
						if(metadata.scaler && metadata.scaler.thml)
							tpl_data._thml = "/uimg/thml/"+ datarow.chash.substr(0,2)+ '/'+ datarow.chash.substr(2)+ '.jpg';
						rowinfo.detailsHeight = metadata.scaler.thml.height;
					} else
						rowinfo.detailsHeight = 60;
					element.addClass('datatable att-details').html($("#template-attachment-details").html().present(tpl_data)).height(rowinfo.detailsHeight);
				}
			}, {
				// Callbacks (for events happening in the datatable itself)
				"rowopen": function(row, $c) { // Opens the details on doubleclick
					$c.jqxDataTable('showDetails', $c.data('selected_index'));
				}
			}, "importfiles");

			// Event for the attach file button
			$("#importfiles-datatable")
			/**
			 * Clicking on the Import button will attach the record file to the E-mail
			 */
			.on("click", ".importfile-button", function() {
				var row = $(this).data('row'), row_data = importfiles_Source.records[row];

				if(!row_data.original_title) // Only do this once
					row_data.original_title = row_data.title;
				row_data.title = row_data.original_title + " <span class='gray'>(attached)</span>";

				// Import a record file into attachments
				if(!self.import_Attachments([row_data.id], function() {
					// Update the the title
					update_Title();

					// And activate the Clear all button
					clear_flameON();
				}))
					cargohub.alert("This file is already attached", "Attention", "error.png");

				$("#importfiles-datatable").jqxDataTable('updateRow', $("#importfiles-datatable").data('selected_index'), row_data);
			});

			attach_zones.importfiles = true;
		},
		/**
		 * Zone: Existing - Prepares the table listing current attachments
		 */
		setup_ImportDocs = function()
		{
			master.init_DataTable($("#importdocs-datatable", $window), {
				// Config
				localization: {
					emptydatastring: "No documents are available for attaching"
				},
				width: 750,
				source: importdocs_Source,
				columns: [
					{dataField: 'version', text: "Version", width: 40, align: "center", cellsAlign: "center", sortable: true, filterable: false, cellsRenderer: function(row, column, value, rowData) {
						if(value)
							return value.toFixed(1);
						else
							return "1.0";
					}}, // cellsRenderer: function(row, column, value, rowData) {return '<img style="border: none;" src="/img/icons/eye.png" alt="Att" class="viewdoc-button" data-row="'+row+'" title="View this document in a window" />';}
					{dataField: 'name', text: "Document name", filterable: true},
					{dataField: 'id', text: "Import", width: 60, align: "center", cellsAlign: "center", cellsRenderer: function(row, column, value, rowData) {
						return '<img style="border: none;" src="/img/icons/word_imports.png" alt="Att" class="importdoc-button" data-row="'+row+'" title="Import this document into the E-mail" />';
					}, sortable: false, filterable: false}
				]
			}, {
				// Callbacks (for events happening in the datatable itself)
				"rowopen": function(row, $c) { // Views the document on double-click
					// $c.data('selected_index');
				}
			}, "importdocs");

			// Event for the attachment deletion button
			$("#importdocs-datatable")
			/**
			 * Any "contact-item" anywhere in the module would allow viewing the contact information. For editing, there's specialized functions:
			 */
			.on("click", ".importdoc-button", function() {
				var row = $(this).data('row'), row_data = importdocs_Source.records[row];

				if(!row_data.original_name) // Only do this once
					row_data.original_name = row_data.name;
				row_data.name = row_data.original_name + " <span class='gray'>(imported)</span>";

				// Import a record file into attachments
				if(!self.import_Document(row_data.id, function() {
					// Update the the title
					update_Title();

					// And activate the Clear all button
					clear_flameON();
				}))
					cargohub.alert("This document has already been imported", "Attention", "error.png");

				$("#importdocs-datatable").jqxDataTable('updateRow', $("#importdocs-datatable").data('selected_index'), row_data);
			});

			attach_zones.importdocs = true;
		},
		/**
		 * Render the title (depending on how many attachments are currently prepared, and their total size)
		 */
		update_Title = function()
		{
			// Update the title
			$window.jqxWindow('setTitle', $('#template-attach_info').html().present(attachments));
			// And the number beside the "Current attachments" button
			$("#attach-navbar span#ca-count", $window).text(attachments.count);
		},
		/**
		 * Prepares and activates a certain zone (only on first use)
		 */
		activate_Zone = function(zone)
		{
			$("#attach-navbar button[data-zone='"+zone+"']", $window).jqxButton({template: "success"});
			$("#attach-"+zone).show();

			current_zone = zone;

			switch(zone) {
			case 'existing':
				if(!attach_zones.existing)
					setup_Existing();
				else {
					// Always update the table after clicking on it, because the content might have been changed (by uploading or attaching new files)
					attachments_Source._source.localdata = attachments.files;
					master.refresh_DataTable($("#attach-datatable", $window));
				}
				break;
			case 'uploadnew':
				if(!attach_zones.uploadnew)
					setup_UploadNew();
				else {
					// When clicking on Upload again, first empty existing files, because all successful ones have been placed in "attachments" anyway.
					// This way there is no need to synchronize "Current" and "Upload new"
					master.file_uploader.empty(file_uploader_instance_key, true);
					uploaded_files = {
						error: 1	// Back to code "1" - Empty dropzone
					};
				}
				break;
			case 'importfiles':
				if(!attach_zones.importfiles)
					setup_ImportFiles();
				break;
			case 'importdocs':
				if(!attach_zones.importdocs)
					setup_ImportDocs();
				break;
			}
		};

		// Setup the zone switcher area
		$("#attach-navbar button", $window).jqxButton({
			theme: cargohub.jqxtheme,
			height: 32,
			width: 212
		})
		/**
		 * Event: One of the admin buttons is clicked (special functions)
		 */
		.click(function(event)
		{
			// Disallow switching zone if there's a problem with uploads
			if(current_zone == "uploadnew") {
				uploaded_files = master.file_uploader.get_Files(file_uploader_instance_key);
				if(uploaded_files.error)
					switch(uploaded_files.error) {
						// Dropzone is empty, nothing to do, just close the window
						case 1:
							break;
						// Upload still in progress
						case 2:
						// Some errors while uploading
						case 3:
							return cargohub.alert(uploaded_files.reason);
					}
			}

			// Begin by hiding the previous zone and it's button
			$("#attach-navbar button[data-zone='"+current_zone+"']", $window).jqxButton({template: "default"});
			$("#attach-"+current_zone).hide();
			// ... then activate the new zone
			activate_Zone($(this).data('zone'));
		});

		// And start by setting the title, remove button and activating the default zone
		update_Title();
		if(attachments.count)
			clear_flameON();
		else
			clear_bleach();
		activate_Zone(current_zone);

		// On Finished (Ok), the window is closed and the inline preview shows number of attachments and total size
		$('#modal_email-attachments_ok').off('click').on('click', function()
		{
			uploaded_files = master.file_uploader.get_Files(file_uploader_instance_key);
			if(uploaded_files.error)
				switch(uploaded_files.error) {
					// Dropzone is empty, nothing to do, just close the window
					case 1:
						$window.jqxWindow('close');
						return;
					// Upload still in progress
					case 2:
					// Some errors while uploading
					case 3:
						return cargohub.alert(uploaded_files.reason);
				}

			// ... and close the window
			$window.jqxWindow('close');
		});

		// on Remove (Clear) all attachments are being deleted (that includes files which were just uploaded or imported)
		// Normally this should only be reachable if there are any attachments currently in memory
		$('#modal_email-attachments_clear').off('click').on('click', function()
		{
			if(!confirm("Are you sure you want to remove all attachments ?"))
				return;
			// Op 1: Empty dropzone (without remote deletion, as that will be done in Step 2)
			master.file_uploader.empty(file_uploader_instance_key, true);

			// Op 2: Request deletion of all "pre" files currently showing in the attachments grid (previously attached, or freshly attached via Dropzone or document import)
			if(attachments.count) {
				$.post("/files?action=Remove", {
					ids: attachments.ids.join(',')
				}, function(x)
				{
					attachments.files = [];
					self.update_Attach_Info(); // This also resets the other vars (count, size, etc)

					// Op 3: On success, refreshes the grid (to appear empty)
					attachments_Source._source.localdata = attachments.files;
					master.refresh_DataTable($("#attach-datatable", $window));
					update_Title();

					// Op 4: Bleach and disable the "Remove all" button
					clear_bleach();
				})
				.fail(cargohub.its.http_fail);
			}
		});

		// When window is closed, Dropzone is cleared (without remote deletion) and deleted, and window itself is destroyed
		// The complete window is reinitialized on open, because data might have changed since last time opened (using Build, or uploading/deleting record attachments or creating/changing documents)
		$window.on('close', function()
		{
			master.file_uploader.empty(file_uploader_instance_key, true);
			master.file_uploader.destroy(file_uploader_instance_key);
			file_uploader_ready = false;

			cargohub.components.modal.forget('email-attachments');
			attach_zones = {};
		});
	},

	/**
	 * Prepares the window that allows the user to choose a different From: address
	 */
	setup_Alias_Selector = function($window)
	{
		// Alias selector
		$("#alias-selector", $window).jqxListBox({
			theme: cargohub.jqxtheme,
			width: 475, height: 170,
			source: self.config.aliases,
			displayMember: 'name', valueMember: 'id',
			renderer: function(index, label, value)
			{
				return '<span class="gray right">'+self.config.aliases[index].email+'</span>'+ label;
			}
		});
		if(self.config.alias_index!==null)
			$("#alias-selector", $window).jqxListBox('selectIndex', self.config.alias_index);

		// Remember choice (session var)
		$("#alias-persistent", $window).jqxCheckBox({
			theme: cargohub.jqxtheme,
			checked: self.config.alias_index!==null
		});

		// Setup the action for "OK" button
		$('#modal_alias-selector_ok', $window).off('click').on('click', function()
		{
			sender = self.config.aliases[$("#alias-selector", $window).jqxListBox('getSelectedIndex')];
			// Update the "From:" line
			$("#tab_email #message-from").html("<b>From:</b> " + sender.name + " &lt;" + sender.email + "&gt;");

			// TODO: Persistent alias?
			// Update department access list
			if($("#alias-persistent", $window).jqxCheckBox('checked')) {
				$.post("?action=Persistent_Alias", {
					alias: sender.id,
				}, function(x)
				{
					if(x.success) {
						cargohub.alert("Remembering your alias selection until logout.");
					} else
						cargohub.its.http_fail(null, "Record.Process::Persistent_Alias");
				})
				.fail(cargohub.its.http_fail);
			} else if(self.config.alias_index!==null) {
				// No longer persistent ? Then forget it (reverts to the default user)
				$.post("?action=Persistent_Alias", {
					alias: 0,
				}, function(x)
				{
					if(x.success) {
						cargohub.alert("Alias will no longer be remembered.");
					} else
						cargohub.its.http_fail(null, "Record.Process::Persistent_Alias");
				})
				.fail(cargohub.its.http_fail);
			}

			$window.jqxWindow('close');
		});
	},

	/**
	 * Initialize the Notes tab toolbar (Add, Edit, Delete notes)
	 */
	init_Toolbar = function()
	{
		$("#tab_email .toolbar button")
		.jqxButton({ theme: cargohub.jqxtheme })
		/**
		 * Event: Toolbar button is clicked
		 */
		.on('click', function()
		{
			switch($(this).data('op')) {
			// Go-Go-Gadget Mailer !
			case 'send':

				setTimeout(function() {

				// TODO: Move all this into a function
				var message = cargohub.components.compose.get_Message(false); // paramenter: true = Test mode, turn off if everything checks
				if(!message)
					return; // There was an error in get_Message()

				message.recipients = JSON.stringify(message.recipients),
				message.sender_id = sender.id; // Selected alias's id is used as sender
				message.org_id = master.config.perspective; // Important - For which organization is this being recorded ?

				message.options = get_Send_Options();
				// Attachments loaded in the tab (to be sent with the E-mail) - Either as actual attachments or "Cloud Files"
				if(attachments && attachments.size > 0) {
					// If the size is very very big (>attachments_warning_maxsize) a confirm() warning should be triggered that e-mail might not be received
					// (and switch to Cloud Files instead) ... current limit on the server is ~20 megs
					if(message.options.cloud == false && attachments.size > attachments_warning_maxsize) {
						var res = confirm("Because of very large attachments\nthe e-mail will be sent with attachments as WEB LINKS.\n\nDo you wish to continue sending like this?");
						if(res)
							message.options.cloud = true;
						else
							return;
					}

					message.attachments = {
						ids: attachments.ids.join(','),
						size: attachments.size
					};
				}

				// Get values of bottom options
				message.options = JSON.stringify(message.options);

				// Link conversation to the opened record (since this message is sent from the record overview)
				message.link = JSON.stringify({
					type: 'issue',	// TODO: Rename this into "record" at another time
					id: master.config.id,
					track: master.config.track,
					rt_key: master.config.type.key
				});

				cargohub.components.spinner.On("Sending message", "It might take a bit", null, null, 500);
				$.post("/global/comm?action=Sendmail", message, function(x)
				{
					// On success, x.id should contain the message ID
					if(x.success) {
						cargohub.components.spinner.Off();
						// Reset this tab on successful send;
						cargohub.components.compose.reset();
						attachments = {};
						self.update_Attach_Info();

						master.select_Tab_With_Params('comm', {
							trigger: "refresh"
						});
						cargohub.components.spinner.On("Success !", "Message sent");
						setTimeout(function()
						{
							cargohub.components.spinner.Off();
						}, 5000);
					} else
						cargohub.its.http_fail(null, "Global.Comm::Sendmail");
				})
				.fail(cargohub.its.http_fail);
				}, 300);
				break;

			// Calls the "Build E-mail" (Transfer) modal from the main Overview module
			case 'transfer':
				master.open_Modal_Transfer();
				break;

			// Opens the attachments window (Version 2019).
			// The window is regenerated every time it's opened, as Files, Docs or current attachments might have been changed since previous opening
			case 'attach':
				cargohub.components.modal.create('email-attachments', {
					buttons: {clear: "No attachments", ok: "Finished"},
					btn_roles: {},
					showCloseButton: false,
					btn_styles: {clear: "float: left; margin-left: 0; background-image: none; background-color: inherit !important; color: inherit !important;"}
				});

				cargohub.components.modal.open('email-attachments', {
					width: 1050,
					height: 480,
					title: "Loading...",
					content: $('#win-email_attachments').html().present({
						_itc: cargohub.input_theme_classes
					}),
					callback: setup_Attachments
				});
				break;

			// Uses the "contacts_list" component to open the user's address book. Multiple selected contacts will be placed in To: and Cc: (if To: already contains something, all contacts go to Cc:)
			case 'contacts':
				master.cb_Contacts_List_Selected = function(contacts, contact_ids)
				{
					// Add selected contact(s) to the compose list (as CC)
					for(c=0; c< contacts.length; c++)
						cargohub.components.compose.add_Contacts(contacts[c]);
				};
				master.open_Modal_Contacts_List();
				break;

			// Adds the logged in user's contact to the Cc: line
			case 'ccself':
//				dd(cargohub.application.user_Get_Session().contact);
				cargohub.components.compose.add_Contacts(sender);
				break;

			// Alias selector window, used to choose the "From" part of the E-mail; Not everyone has aliases, they are set by admin
			case 'aliases':
				if(cargohub.components.modal.windows['alias-selector'])
					return cargohub.components.modal.reopen('alias-selector');

				// Open the contacts list in a large modal
				cargohub.components.modal.create('alias-selector', {
					btn_roles: {cancel: "cancel"}
				});

				cargohub.components.modal.open('alias-selector', {
					width: 500, height: 320,
					title: "Select your alias (From:)",
					content: $('#win-select_alias').html(),
					callback: setup_Alias_Selector
				});

				break;

			// Empties the entire E-mail area (cancel sending)
			case 'reset':
				if(!confirm("The recipients, subject, content of your message and all attachments will be cleared.\nAre you sure you want to do that ?"))
					return;
				// Clear Composer
				cargohub.components.compose.reset();

				// Clear and remove the temporary Attachments (delete already uploaded)
				if(attachments.count) {
					// Request deletion of all "pre" files currently attached to the E-mail
					$.post("/files?action=Remove", {
						ids: attachments.ids.join(',')
					}, function(x)
					{
						attachments.files = [];
						self.update_Attach_Info(); // This also resets the other vars (count, size, etc)
					})
					.fail(cargohub.its.http_fail);
				}
				break;
			}
		});
	},

	/**
	 * Grabs the values of the checkbox options on the bottom and builds an object with true/false
	 */
	get_Send_Options = function()
	{
		var options = {};
		// The Options at the end of the compose area (affect what happens after e-mail is sent)
		$("#tab_email .email-options > div").each(function()
		{
			var id = $(this).prop('id').substr(4);
			options[id] = $(this).val();
		});
		return options;
	},

	/**
	 * Initializes the bottom preview panels - Signature and Attachments.
	 * - Signature is simple, and appears and disappears when the [signature] option is checked and unchecked
	 * - Preview is reconstructed every time a file is uploaded or deleted (like the filestats bar)
	 */
	init_Previews = function()
	{
		// Nothing needed here yet
	}

	;
	this._public({
		/**
		 * Setup E-mail tab for Record Process module
		 */
		init: function()
		{
			// TODO: Due to dynamic loading of the three required components, tab should be covered and say *loading* until initialization is complete

			// Load the required components - Rich Editor (TinyMCE) and File Uploader (DropzoneJS)
			master.load_rich_editor();
			master.load_file_uploader();

			init_Toolbar();

			// Support aliases
			if(self.config.aliases) {
				sender = self.config.aliases[self.config.alias_index||0];
			} else
				sender = cargohub.application.user_Get_Session().contact;

			// Actions on the quick Attachmentments info:
			// -- Hovering will show current attachments
			// -- Clicking will open the Attachments window (like the Attachments button)
			$("#tab_email #message-attach_info")
			.on('click', function() {
				$("#tab_email .toolbar button[data-op=attach]").trigger('click');
			});

			// The "From" line
			$("#tab_email #message-from").html("<b>From:</b> " + sender.name + " &lt;" + sender.email + "&gt;");

			// Load the required component: E-mail Compose
			donrey.load_Components(["compose"], function()
			{
				// Callback - Components finished loading, initialize the controls
				var compose_config = {
					container: $("#tab_email #message-controls"),
					sender: sender,
					preset_contacts: [],
					org_id: master.config.perspective,
					rich_editor_instance_key: "emailcompose"
				};

				// Variables related to the link (record information)
				compose_config.link = {
					type: "issue",
					rec_id: master.config.id,
					rt_id: master.config.type.base_id?master.config.type.base_id:master.config.type.id,
					rt_key: master.config.type.key
				};

				if(on_Ready)
					cargohub.components.compose.on_Ready = on_Ready;
				cargohub.components.compose.setup(compose_config, {rich_editor: master.rich_editor}, self);

				// The Options at the end of the compose area (affect what happens when sending the E-mail - and after sending, like sharing and linking recipients)
				$("#tab_email .email-options > div").each(function()
				{
					var id = $(this).prop('id').substr(4), checked = false;

					if(id == 'signature' && self.config.options.email_sig) {
						checked = true;
						$("#message-signature_preview").fadeIn();
					} 
//                                        else if(id == 'selfcopy' && self.config.options.email_cc)
//						checked = true;
                                        else if(id == 'selfcopy')
                                            checked = true;
					else if(id == 'contacts')
						checked = true;

					$(this).jqxCheckBox({
						theme: cargohub.jqxtheme,
						checked: checked
					});
                                        
                                        if (id == 'selfcopy') {
                                            $(this).off('change.selfcopy').on('change.selfcopy', function (e) {
                                                if (!e.args.checked) {
                                                    $(this).jqxCheckBox('checked', true);
                                                }
                                            });
                                        }
				})
				/**
				 * Event: One of the options is changed ( Set On or Off - Some have an immediate effect on the interface )
				 */
				.on('change', function(e)
				{
					var id = $(this).prop('id').substr(4), checked = e.args.checked;
					// The large e-mail warning is turned on/off depending on "opt-cloud"
					if(id == 'cloud')
						self.update_Attach_Info(checked);
					// Shows or hides the signature panel
					if(id == 'signature') {
						if(checked)
							$("#message-signature_preview").fadeIn();
						else
							$("#message-signature_preview").fadeOut();
					}
				});

				// Tooltips inside the Send tab
				$("#tab_email .info-tip").each(cargohub.components.ui.add_Tooltip);

				// The compose contains a preview list of attachments with a single "delete" button per attachment.
				// Setup the action on pressing any of these delete buttons.
				$("#tab_email #email-compose-atts").on('click', "img.right", function() {
					if(!confirm("Are you sure you want to remove this attachment ?"))
						return;

					self.unlink_Attachment($(this).data('index'));
				});

			}, "?v="+cargohub.version);
		},

		/**
		 * Callback for actions to be performed when this tab is selected
		 */
		selected: function(params)
		{
			if(params && params.trigger)
				switch(params.trigger) {
				case "compose-callback":
					if(cargohub.components && cargohub.components.compose && cargohub.components.compose.is_Ready())
						params.callback(cargohub.components.compose);
					else
						on_Ready = params.callback;
					break;
				}
		},

		/**
		 * Imports a list of external Warehouse files into the E-mail composing
		 * This also updates statistics.
		 * @param Array file_ids List of Warehouse file ID's that will be duplicated into Pre files and used for sending. (After sending they are permanently linked ot the sent message)
		 * This allows the message/conversation viewer to show the files which have been attached to the message (permanently); Even if the original record/issue attachment was unlinked
		 * @param callback on_Complete Extra function to execute after attachment has been imported
		 */
		import_Attachments: function(file_ids, on_Complete)
		{

			if(!file_ids || file_ids.length == 0)
				return false; // Nothing to do
			var file_ids_filtered = [];

			if(attachments.original_ids && attachments.original_ids.length > 0) {
				// Builds a list of files which were not already attached by scanning against original_ids
				for(c=0; c< file_ids.length; c++) {
					if($.inArray(file_ids[c], attachments.original_ids) == -1)
						file_ids_filtered.push(file_ids[c]);
				}
			} else
				// Nothing to filter, there are no original ids yet
				file_ids_filtered = file_ids;

			if(file_ids_filtered.length == 0)
				return false; // Nothing to do, the file id was probably filtered, not attaching twice.

			$.post("/files?action=CopyToPre", {
				ids: file_ids_filtered.join(',')
			}, function(r) {
				// Append the list to existing attachments or start fresh
				if(attachments.count)
					$.merge(attachments.files, r.files);
				else
					attachments = {files: r.files};

				self.update_Attach_Info(); // Does the rest of the calculation and updates

				if(on_Complete)
					on_Complete();
			})
			.fail(cargohub.its.http_fail);
			return true;
		},

		/**
		 * Unlinks (Removes) an existing email attachment based on the index (from 0 to number of attachments -1)
		 * This method also calls the delete file function (which does apply for "pre" files)
		 * @param int index
		 * @param callback on_Complete Extra function to execute after attachment has been unlinked
		 */
		unlink_Attachment: function(index, on_Complete)
		{
			$.post("/files?action=Remove", {
				qp: donrey.qp_conv(attachments.ids[index]),
			}, function(x)
			{
				// Update the attach info
				attachments.files.splice(index, 1);
				self.update_Attach_Info(); // This computes the other vars

				if(on_Complete)
					on_Complete();
			})
			.fail(cargohub.its.http_fail);
		},

		/**
		 * Imports a document into the Warehouse storage, as a HTML file.
		 * This also updates statistics.
		 *
		 * @param Array file_ids List of Warehouse file ID's that will be duplicated into Pre files and used for sending. (After sending they are permanently linked ot the sent message)
		 * This allows the message/conversation viewer to show the files which have been attached to the message (permanently); Even if the original record/issue attachment was unlinked
		 * @param callback on_Complete Extra function to execute after attachment has been imported
		 */
		import_Document: function(id, on_Complete)
		{
			// Check if the document wasn't already imported
			if($.inArray(id, attachments.original_ids) != -1)
				return false;

			$.post("/files?action=ImportDocument", {
				id: id,
				type: "html"
			}, function(r) {
				// Append the list to existing attachments or start fresh
				if(attachments.count)
					attachments.files.push(r.file);
				else
					attachments = {files: [r.file]};

				self.update_Attach_Info(); // Does the rest of the calculation and updates

				if(on_Complete)
					on_Complete();
			})
			.fail(cargohub.its.http_fail);
			return true;
		},

		/**
		 * Calculates total size of attachments, counts them and updates the UI with the preview of attachments.
		 * It should be called for every operation that modifies attachments (adding or removing to the list)
		 * To see the details and operate the attachments, open the "File Attachments" window itself.
		 * * Note: Clicking on the information line should open the window as well
		 *
		 * This method also computes the list of ID's, which is used at the moment of sending
		 *
		 * @param no_warning Boolean, if True, the "Attention, very large files" is not displayed
		 * @return Returns the quick information string
		 */
		update_Attach_Info: function(no_warning)
		{
			if(!attachments.files)
				attachments.files = [];

			attachments.count = attachments.files.length;
			attachments.size = 0; // This gets re-counted anyway
			attachments.ids = []; // Gets rebuilt
			attachments.original_ids = [];

			// Calculate size and build some sub-arrays if there are any files
			if(attachments.count > 0) {
				for(c=0; c< attachments.count; c++) {
					attachments.size += parseInt(attachments.files[c].size);
					attachments.ids.push(parseInt(attachments.files[c].id));
					if(attachments.files[c].original_id)
						attachments.original_ids.push(parseInt(attachments.files[c].original_id));
					else
						attachments.original_ids.push(null); // Must have something there, even if there is no original id
				}
			} else {
				attachments.report = 0; // Reporting no attachments (gray)
				attachments.report_string = "(No attachments)";
				$("#tab_email #message-attach_info").html("<span class='gray'>"+attachments.report_string+"</span>");

				// Empties and hides the Compose attachments area
				$("#tab_email #email-compose-atts").empty().hide();
				return true;
			}

			// Some more human friendly display of size ( kilobytes, megabytes )
			if(attachments.size < 999) {
				attachments.dsize = attachments.size;
				attachments.sunit = "B";
			} else if(attachments.size < 1022976) { // 0.999 MB
				attachments.sunit = "KiB";
				attachments.dsize = (attachments.size/1024).toFixed(2);
			} else if(attachments.size < 104752742) { // 99.9 MB
				attachments.sunit = "MiB";
				if(!no_warning && attachments.size > attachments_warning_maxsize)
					attachments.oversize = true;

				attachments.dsize = (attachments.size/1048576).toFixed(2);
			} else {
				// Over 99.9 megabytes ? Something is definitely wrong... this is not Youtube.
				attachments.report = -1; // -1 is Error (red)
				attachments.report_string = "[Error in attachments calculation]";
				$("#tab_email #message-attach_info").html("<span class='red'>"+attachments.report_string+"</span>");
				return false;
			}

			attachments.report = 1; // Reporting normal output (black)
			attachments.report_string = $("#template-attach_info").html().present(attachments);
			$("#tab_email #message-attach_info").html(attachments.report_string);

			// Shows and then updates the Compose attachments area
			$("#tab_email #email-compose-atts").empty().show();

			for(c=0; c< attachments.count; c++) {
				var att_info = $.extend({index: c}, attachments.files[c]);
				$("#tab_email #email-compose-atts").append($("#template-compose_atts").html().present(att_info));
			}

			return true;
		},

		/**
		 * Sets or unsets one of the checkbox options on the bottom.
		 *
		 * @param opt_id One of "share", "contacts", "signature", "cloud" - must be correct or this has no effect
		 * @param value True or False
		 */
		set_option: function(opt_id, value)
		{
			var $opt = $("#tab_email .email-options > div#opt-"+ opt_id);
			if($opt.length)
				$opt.jqxCheckBox('val', value);
		}
	});

});