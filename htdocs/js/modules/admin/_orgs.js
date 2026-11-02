/**
 * Module "Organizations List" (Admin only)
 * -- Display all Organizations in the system ( For now, only admin has access to this. In the future, "HeadQuarters" organizations will have access to sub-organizations )
 */
cargohub.admin_orgs = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,
		selected_org = null, // Selected organization in the grid
	/**
	 * Sets up the organizations display/search grid
	 */
	setup_Organizations_Grid = function()
	{
		var
		// the data definition 
		gridDataFields = [
			{name: 'id', type: 'number'},
			{name: 'type_id', type: 'string'},
			{name: 'orgtype', map: 'orgtype>name'},
			{name: 'status'},
			{name: 'update_dt', type: 'date'},
			{name: 'name', type: 'string'},
			{name: 'manager_name', map: 'manager>name'},
			{name: 'pri_url', map: 'features>primary_domain', type: 'string'},
			{name: 'count_users', map: 'count>users', type: 'int'},
			{name: 'count_issues', map: 'count>issues', type: 'int'},
			{name: 'count_files', map: 'count>files', type: 'int'},
			{name: 'feat_deps', map: 'features>departments', type: 'int'},
			{name: 'feat_groups', map: 'features>groups_total', type: 'int'},
			{name: 'feat_active', map: 'features>active', type: 'bool'},
			{name: 'feat_lock', map: 'features>delete_lock', type: 'bool'}
		],
		// List of possible organization types
		orgtypes_list = function() {
			var list = [];
			for(c=0; c< self.config.orgtypes.length; c++)
				list.push(self.config.orgtypes[c].id);
			return list;
		}(),

		// the column definition
		gridColumns = [
			{text: "Status", dataField: 'status', width: 90,
				filtertype: "checkedlist", filteritems: ["placeholder", "trial", "payer", "enterprise", "internal"],
				createfilterwidget: function (column, columnElement, widget) {
					widget.jqxDropDownList({
						dropDownWidth: 110,
						autoDropDownHeight: true,
						animationType: "fade"
					});
				}
			},
			{text: "Type", dataField: 'type_id', width: 80,
				filtertype: "checkedlist", filteritems: orgtypes_list,
				createfilterwidget: function (column, columnElement, widget) {
					widget.jqxDropDownList({
						dropDownWidth: 110,
						autoDropDownHeight: true,
						animationType: "fade"
					});
				}
			},
			{text: "Name", dataField: 'name', width: 200},
			{text: "Updated on", dataField: 'update_dt', width: 110, cellsformat: "MMM-dd HH:mm", align: "center", cellsalign: "center", filtertype: "range"},
//			{text: "Listed?", dataField: 'feat_active', width: 55, align: "center", cellsalign: "center", columntype: "checkbox", filtertype: "bool"},
			{text: "Primary URL", dataField: 'pri_url', filterable: false, sortable: false, // Filter/Sort not resolved at DB level
				cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
					return '<div style="text-overflow: ellipsis; overflow: hidden; padding-bottom: 2px; text-align: left; margin-right: 2px; margin-left: 4px; margin-top: 4px;"><a href="http://'+value+'/" target="_blank">'+value+'</a></div>';
				}
			},
			{text: "Manager", dataField: 'manager_name', width: 120},
			{text: "Records", dataField: 'count_issues', width: 55, align: "right", cellsalign: "right", filtertype: "number"},
			{text: "Users", dataField: 'count_users', width: 55, align: "right", cellsalign: "right", filtertype: "number"},
			{text: "Files", dataField: 'count_files', width: 55, align: "right", cellsalign: "right", filtertype: "number"},
			{text: "Dept's", dataField: 'feat_deps', width: 55, align: "right", cellsalign: "right",  filterable: false, sortable: false}, // Filter/Sort not resolved at DB level
			{text: "Groups", dataField: 'feat_groups', width: 55, align: "right", cellsalign: "right",  filterable: false, sortable: false,} // Filter/Sort not resolved at DB level
		],
		// prepare the data
		listGridAdapter = new $.jqx.dataAdapter({
			url: '?action=get_list',
			datatype: "json",
			datafields: gridDataFields,
			id: 'id',
			root: "data",
			// server side operations - filtering and sorting:
			cache: false,
			filter: function() {
				$("#orgs_grid").jqxGrid('updatebounddata', 'filter');
			},
			sort: function() {
				$("#orgs_grid").jqxGrid('updatebounddata', 'sort');
			},
			beforeprocessing: function(source) {
				this.totalrecords = source.found_rows;
			},
			// Initial Sorting
			sortcolumn: 'update_dt',
			sortdirection: 'desc'
		});

		// ... and init the grid
		$("#orgs_grid").jqxGrid({
			theme: cargohub.jqxtheme,
			source: listGridAdapter,
			width: cargohub.fullwidth, autoheight: true,
			columns: gridColumns, columnsresize: true, columnsmenu: false,
			selectionmode: "singlerow",	altrows: true,
			pageable: true,	pagesize: 20, pagesizeoptions: [10, 20, 50, 100],
			virtualmode: true, // virtual paging
			rendergridrows: function(params)
			{
				return params.data;
			},
			filterable: true, showfilterrow: true, sortable: true
		})
		/**
		 * Event: When a row is selected, the name of the organization is displayed on the toolbar, and is show with fade in (when selecting a row first time)
		 */
		.on('rowselect', function (event)
		{
		    var row = event.args.row;
			$("span#selected_org").html(row.name);
			if(!selected_org) {
				$("#org_edit_operations button").jqxButton({disabled: false});
				donrey.cancel_Delay('hide_bar_edit_ops');

				$('#org_edit_operations').fadeIn();
			}
			// Disable the delete button on certain organizations
			if(row.type_id == "admin" || row.feat_lock)
				$("#org_edit_operations #button-delete").jqxButton({disabled: true});
			else
				$("#org_edit_operations #button-delete").jqxButton({disabled: false});

			selected_org = row;
                        
                        
                        // IWI create api for getting record details start 
                        $('#selected_org_secretKey').show();
                        $('.toolbar button[data-op="secretKey"]').attr('data-org', selected_org.id);
                        // IWI create api for getting record details end
		})
		/**
		 * Event: When the grid is filtered, clear selection because the selected row might not actually be visible anymore.
		 */
		.on('filter', function(event)
		{
			if(selected_org) {
				$(this).jqxGrid('clearselection');
				selected_org = null;
				$("span#selected_org").empty();
				$("#org_edit_operations button").jqxButton({disabled: true});

				donrey.single_Delay('hide_bar_edit_ops', function() {
					$("#org_edit_operations").fadeOut();
				}, 500);
			}
		})
		/**
		 * Event: On doubleclicking, do the same as simple clicking then using the View/Edit button
		 */
		.on('rowdoubleclick', function(){
			$('.toolbar button[data-op="edit"]').trigger("click");
		});
	},

	/**
	 * Shows the modal window used to create new organization (and sets it up on first use)
	 */
	open_Add_Org_Window = function()
	{
		cargohub.its.init_Modal_Window('editor', null, {"cancel": "cancel"}); // init the modal window, but with OK button disabled. It will be set up below
		cargohub.its.open_Modal_Window('editor', 420, 230, "Create New Organization", $("#template_add_org").html(), function($editor) // Setup the controls inside the window
		{
			// Organization Ttype selector
			$('#org_type', $editor).jqxDropDownList({
				theme: cargohub.jqxtheme,
				source: self.config.orgtypes, selectedIndex: 0,
				displayMember: "name", valueMember: "id",
				width: 208,	height: 25,
				autoDropDownHeight: true
			});

			// css themes (fixed list for now)
			var css_themes = [
				{value: "default", text: "Cargohub (Default)"}
				// The other types are incomplete, not implemented
//				{value: "desert", text: "Scorched desert"},
//				{value: "mask", text: "Evil blue"}
			];

			$('#domain_css_theme', $editor).jqxDropDownList({
				theme: cargohub.jqxtheme,
				source: css_themes, selectedIndex: 0,
				displayMember: "text", valueMember: "value",
				width: 208,	height: 25,
				autoDropDownHeight: true
			});

			// Setup the action for "OK" button
			$('#modal_editor_ok', $editor).off('click').on('click', function() {
				// submit the add/edit form using ajax
				$('.modal_content > form', $editor).ajaxSubmit({
					dataType: 'json',
					beforeSubmit: function(formData, jqForm, options) {
						// setup the field checking object
						var f = {};
						for(var c=0; c< formData.length; c++)
							f[formData[c].name] = formData[c].value;

						if(f['org[name]'].length < 3) {
							cargohub.its.alert("The organization's name must have at least 3 characters.");
							return false;
						}

						return true;
					},
					success: function(x) {
						if(x.success==true) {
							document.location = "/manager/organization?id="+ x.org_id + "#setup";
						} else {
							cargohub.its.alert(x.reason, "Message from server", "information.png");
						}
					}
				});
			});
		});
	},
              
        
//        IWI create api for getting record details start

            open_Org_SecretKey_Window = function () {
    var curr_org_id = selected_org.id;

    cargohub.its.open_Modal_Window(
        'editor',
        480,
        280,
        selected_org.name + " Secret Key",
        $("#template_org_secret").html(),
        function ($editor) {

            // Show loading state
            $('#the_secret_key', $editor).text("Loading...");
            $('#copy_secret', $editor).hide();
            $('#regenerate_key', $editor).hide();
            $('.status-box', $editor).hide(); // ? hide status initially

            // Fetch secret key and status
            $.getJSON('/admin/orgs?action=getSecretKey', { org_id: curr_org_id }, function (resp) {
                if (!resp.success) {
                    // No key exists
                    $('#the_secret_key', $editor).text("No key found");
                    $('#copy_secret', $editor).hide();
                    $('#regenerate_key', $editor).hide();
                    $('.status-box', $editor).hide(); // ? hide status when no key

                    // Show create button
                    if ($('#create_new_key', $editor).length === 0) {
                        $('.key-wrapper', $editor).append('<button id="create_new_key" class="btn create-btn">Create New</button>');
                        $('#create_new_key', $editor).on('click', function () {
                            $.post('/admin/orgs?action=createSecretKey', { org_id: curr_org_id }, function (resp) {
                                if (resp.success) {
                                    renderSecretKey(resp.secretKey, $editor);
                                    renderStatus(resp.status, $editor);
                                    $('#create_new_key', $editor).remove();
                                } else {
                                    cargohub.its.alert(resp.reason || "Failed to create new key");
                                }
                            }, 'json');
                        });
                    }

                    return;
                }

                // Key exists
                renderSecretKey(resp.secretKey, $editor);
                renderStatus(resp.status, $editor);
                $('#regenerate_key', $editor).show();
                $('.status-box', $editor).show(); // ? show status when key exists
            });

            // Regenerate key button
            $('#regenerate_key', $editor).off('click').on('click', function () {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "This will regenerate the secret key. The old key will stop working.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, regenerate it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('/admin/orgs?action=regenerateSecretKey', { org_id: curr_org_id }, function (resp) {
                            if (resp.success) {
                                renderSecretKey(resp.secretKey, $editor);
                            } else {
                                cargohub.its.alert(resp.reason || "Failed to regenerate key");
                            }
                        }, 'json');
                    }
                });
            });

            // Copy button
            $editor.on('click', '#copy_secret', function () {
                var $temp = $("<input>");
                $("body").append($temp);
                $temp.val($('#the_secret_key', $editor).text()).select();
                document.execCommand("copy");
                $temp.remove();
                cargohub.its.alert("Secret key copied to clipboard!");
                setTimeout(function () {
                    cargohub.its.close_Modal_Window('editor');
                }, 1000);
            });

            // ---------------- Helper functions ---------------- //
            function renderSecretKey(secretKey, $editor) {
                $('#the_secret_key', $editor).text(secretKey);
                $('#copy_secret', $editor).show();
                $('#create_new_key', $editor).remove();
            }

            function renderStatus(status, $editor) {
                var isActive = status === "1" || status === true || status === "active";
                var statusText = isActive ? "Active" : "Inactive";
                var toggleText = isActive ? "Deactivate" : "Activate";

                $('#org_status_text', $editor)
                    .text(statusText)
                    .removeClass('active inactive')
                    .addClass(isActive ? 'active' : 'inactive');

                $('#toggle_status', $editor)
                    .text(toggleText)
                    .removeClass('btn-success btn-danger')
                    .addClass(isActive ? 'btn-success' : 'btn-danger');

                // Toggle click
                $('#toggle_status', $editor).off('click').on('click', function () {
                    $.post('/admin/orgs?action=toggleSecretKeyStatus', { org_id: curr_org_id, curr_status: status }, function (resp) {
                        if (resp.success) {
                            renderStatus(resp.status, $editor);
                            cargohub.its.alert('Organization is now ' + (resp.active ? "Active" : "Inactive"));
                        } else {
                            cargohub.its.alert(resp.reason || "Failed to update status");
                        }
                    }, 'json');
                });
            }
        }
    );
};






//        IWI create api for getting record details end

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
				case 'add':
					open_Add_Org_Window();
					break;

				case 'edit':
					// Switches to the Organization Manager for the selected org., which allows configuring another organization instead of the logged in user's organization
					document.location = "/manager/organization?id=" + selected_org.id + '#setup';
					break;

				case 'contacts':
					// Switches to the Contacts Manager for the selected org.; Only department and organization level contacts are available; All departments are shown
					document.location = "/manager/contacts?org_id=" + selected_org.id + '#search';
					break;

				case 'tasks':
					// Switches to the Tasks Manager for the selected org.
					document.location = "/manager/tasks:record?org_id=" + selected_org.id;
					break;

				case 'delete':
					if(selected_org.type_id == 'admin' || selected_org.feat_lock)
						return cargohub.its.alert("Captain, Red Alert, we have a warp core breach!", "Scotty shouts:", "ncc1701.png");

					// Double confirmation (for now) TODO: Make a popup in which a captcha of some kind needs to be inserted to allow orgnaization deletion
					if(data.confirm)
						if(!confirm("Are you sure you want to "+ data.confirm_msg))
							return;

					if(!confirm("! WARNING !\n\nOrganization \""+selected_org.name+"\" is about to be removed:\nE V E R Y T H I N G related to it will also get deleted - users, issues, messages, files, notes, tasks etc..."+
						"\n\nThere is no way to recover the data !!!\n\nPlease click OK if you REALLY want to proceed."))
						return;

					$.post("?action=delete_organization", {
						org_id: selected_org.id
					}, function(x) {
						if(x.success)
							document.location.reload();
						else {
							cargohub.its.alert(x.reason, "Message from server", "events.png");
						}
					});

					break;
                                        
                                        
                                
                                        
//                              IWI create api for getting record details start
				case 'secretKey':
					open_Org_SecretKey_Window();
					break;
//                                IWI create api for getting record details end  
			}
		});
	};

	this._public({
		/**
		 * Section initialization
		 */
		init: function()
		{
			setup_Organizations_Grid();
			setup_Toolbar();

			// Info Explander
			$(".info-expander").jqxExpander({
				theme: cargohub.jqxtheme,
				expanded: false
			});
		}
	});
});
