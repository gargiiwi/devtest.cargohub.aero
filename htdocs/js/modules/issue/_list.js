/**
 * Interface for searching and listing Primary Records (Main Grid)
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2020.04.29
 */
cargohub.issue_list = donrey.BASE._extend(function() {
	// private
	var self = this,
	c = null, // general counter used in for()
	// state of the pop-up windows used by this page - Used for lazy initialization ( on first use )
	popups = {},
 
	gridDataAdapter = null, 
	gridInterval = null,
	// Xfilters and Ufilters, which also affect the hash string for the List module
	xfilters = {exgroup: "active", ugroup: []},
	disable_onhashchange = false, 
	xfilters_sysinit = false, 

	// Superuser-only variables (if not SU, they will be empty)( 
	dataAdapter_Admin_Orgs = null,	// Contains all the system organization with access to current record type - Used by the Perspective selector as well as other admin functions
	dataAdapter_Record_XF = null,	// List of all fields available for this record type (ignoring any access restrictions)
         
         need_to_reset=[]; //  IWI reset button only visible if filter active


     // security audit start
	//  $audit_activity_viewer = $("#tab_security_activity #document-viewer"),
	//  $audit_activity_editor = null,
	//  panel_audit_activity_Source = new $.jqx.dataAdapter({
	// 	 url: "/record/process?action=load_list_audit_activity", // At the moment using notes backend - That might change in the future !
	// 	 data: {
	// 		 link_id: master.config.id,
	// 		 track: master.config.track,
	// 	 },
	// 	 datatype: "json",
	// 	 datafields: [
	// 		 {name: 'ID', type: 'string'},
	// 		 {name: 'reference_no', type: 'string'},
	// 		 {name: 'dsc', type: 'string'},
	// 		 {name: 'risk', type: 'string'},
	// 		 {name: 'improvement', type: 'string'},
	// 		 {name: 'status', type: 'string'},
	// 		 {name: 'risk_val', type: 'string'},

	// 		 {name: 'title', type: 'string'},
	// 		 {name: 'type', type: 'string'},
	// 		 {name: 'person', type: 'string'},
	// 		 {name: 'result', type: 'string'},
	// 		 {name: 'comments', type: 'string'},
	// 		 {name: 'deadline_date', type: 'string'},
	// 		 {name: 'closing_date', type: 'string'},

	// 		 {name: 'isCreateTask', type: 'string'},
	// 		 {name: 'isCreateTaskLable', type: 'string'},
	// 		 {name: 'isCreateTaskId', type: 'string'},
			 
	// 	 ],
	// 	 id: "id",
	// 	 sortcolumn: 'id',
	// 	 sortdirection: 'desc'
	//  }),
	 // security audit end

	/**
	 * Sets up the filters which apply on properties not normally controlled from the grid itself (custom filtering), which also affects #hash
	 */
	setup_Special_Filters_Bar = function()
	{
		var xfilter_tooltips = {
			"showall": "Show all records, including the ones that have been marked as <b>Closed</b>",
			"active": "Show active records (normal grid view)",
			"closed": "Show only closed records, hiding active ones",
			"assigned": "Only shows records that are assigned to me<br />(even if I can access others)",
			//"notshared": "Only shows records that have not yet been shared with other orgs",
			"notasks": "Only shows records which don't have any tasks defined yet",
			// Non-admin only
			"notours": "Only shows records created by other organizations but visible for us as well",
			// Admin only
			"noorg": "<span class='thick'>[ADMIN]</span> Records which do not belong to any organization<br />This also shows hidden records",
			// User-related toggle
			"unopen": "Records which you have not yet opened for viewing<br /><br />(Those submitted by yourself are considered <i>viewed</i>)",
			"unexp": "Records which you have not yet exported yet via Export table function"
		},
		ugroup_index = {
			0: "assigned",
			1: "unopen",
			2: "unexp"
		},
		/**
		 * Event method which is called every time a button from the special filters bar is clicked (changed, On or Off).
		 * This should reload the grid with new filters applied to it, and affect the #hash string
		 */
		update_Xfilters = function()
		{
			if(!self.config.hash)
				self.config.hash = {};
			self.config.hash.xfilters = xfilters.exgroup+ (xfilters.ugroup.length? '|'+ xfilters.ugroup.join(','):'');

			disable_onhashchange = true;

			// Change Hash string ?
			if(xfilters.exgroup == 'active' && !xfilters.ugroup.length)
				history.pushState("", document.title, window.location.pathname + window.location.search);
			else {
				// TODO: Support combo hash (not just xfilters, for example to initialize grid on a certain page via URL)
				document.location.hash = "#xfilters="+ self.config.hash.xfilters;
			}

			setTimeout(function() {
				disable_onhashchange = false;
			}, 100);

			// Update the grid to new xfilters
			gridDataAdapter._source.data["custom[xfilters]"] = xfilters.exgroup+ (xfilters.ugroup.length? '|'+ xfilters.ugroup.join(','):'');
			// reload grid (including filters)
			$("#issues-grid").jqxGrid('updatebounddata', 'filter');
		};

		// Global exclusive filters
		$("div#ex-filter-group").jqxButtonGroup({
			theme: cargohub.jqxtheme,
			enableHover: true,
			mode: 'radio'
		})
		/**
		 * Event: One of the buttons in the group is clicked
		 */
		.on('selected', function(event)
		{
			if(xfilters_sysinit)
				return;
			xfilters.exgroup = event.args.button.attr('id').substr(8);
			update_Xfilters();
		});

		// User specific filters
		$("div#user-filter-group").jqxButtonGroup({
			theme: cargohub.jqxtheme,
			enableHover: true,
			mode: 'checkbox'
		})
		/**
		 * Event: One of the buttons in the group is clicked
		 */
		.on('buttonclick', function(event)
		{
			if(xfilters_sysinit)
				return;
			var sel = $("div#user-filter-group").jqxButtonGroup('getSelection');
			xfilters.ugroup = [];
			for(c = 0; c< sel.length; c++)
				xfilters.ugroup.push(ugroup_index[sel[c]]);

			update_Xfilters();
		});

		// add a tooltip to each of the buttons from the group. P.S. - Stupid jqWidgets doesn't move the other attributes, so can't specify the titles(tooltip content) there :|
		$("#special-filter-bar div.jqx-group-button-normal").each(function()
		{
			$(this).jqxTooltip({
				theme: cargohub.jqxtheme,
				content: xfilter_tooltips[$(this).attr("id").substr(8)],
				autoHideDelay: 3000,
				position: 'top'
			});
		});

		$("#special-filter-bar > button").jqxButton({
			theme: cargohub.jqxtheme
		});

		if(cargohub.application.user_Is_Admin()) {
			var sysChange = false;

			// When admin, read the list of organizations that have access to the current issue type
			$("#special-filter-bar #xfilter-selectorg").jqxComboBox({
				source: dataAdapter_Admin_Orgs,
				theme: cargohub.jqxtheme,
				displayMember: "name", valueMember: "id",
				searchMode: "containsignorecase",
				autoComplete: true,	minLength: 0,
				renderer: function (index, label, value) {
					if(index == -1)
						if(label == '.')
							return "";
						else
							return "<div class='thick center' style='background-color: #dfdfdf; line-height: 26px; border-top: 1px solid gray;'>&middot;&middot;&middot; "+label+ " &middot;&middot;&middot;</div>";
					if(index == 0)
						return "<div class='thick center green'>"+label+"</div>";
//					var rec = dataAdapter_Admin_Orgs.records[index];
//					return '<span class="right gray">['+rec.group+']</span>'+ rec.name;
					return label;
				},
				placeHolder: "(Loading organizations...)",
				animationType: "none",
				width: 250, dropDownWidth: 383, dropDownHorizontalAlignment: "right",
				disabled: true
			})
			/**
			 * Event, the dropdown is loaded, show the default value and enable the control
			 */
			.on('bindingComplete', function() {
				$(this).jqxComboBox({
					placeHolder: "[Admin] Org. Perspective:",
					dropDownHeight: dataAdapter_Admin_Orgs.records.length> 7? 184: null,
					autoDropDownHeight: dataAdapter_Admin_Orgs.records.length<= 7? true: false,
					disabled: false
				});

				// Autoselect the current organization.
				if(self.config.perspective) {
					sysChange = true;
					for(c=0; c< dataAdapter_Admin_Orgs.records.length; c++)
						if(dataAdapter_Admin_Orgs.records[c].id == self.config.perspective) {
							$(this).jqxComboBox('selectIndex', c);
							self.config.admin_org_name = dataAdapter_Admin_Orgs.records[c].name;
						}
				}
				sysChange = false;
			})
			/**
			 * Event: The organization selector is changed - Reload the page with organization selected which would view the grid as from the selected organization.
			 */
			.on("change", function(event) {
				if(sysChange)
					return;
				if(!event.args)
					return;

				if(event.args.item.value)
					document.location = "/issue/list?org_id="+event.args.item.value;
				else
					document.location = "/issue/list?org_id=";
			});
		}
	},

	/**
	 * Sets up the grid that is used to display and search for issues
	 */
	setup_Records_Grid = function()
	{
//           
		// The Issues grid ( for the currently selected issue type )
		var gridDataFields = [
			{name: '_track', map: 'track', type: 'string'},
			{name: 'isCapa',type: 'string'},// 15012025 grid changes code changes 
			{name: '_access_key', map: 'access_key', type: 'string'}, // Invisible but required for the web view link
			{name: 'active_docs_count', map: 'active_docs>count', type: 'string'}, //get active published doc details
			{name: 'connected_docs_count', map: 'active_docs>count', type: 'string'}, //IWI get connected doc for licence monitor
		],
                        
		gridColumns = [
			// Tracking Number is absolute, it can't be disabled or otherwise changed
			{
				text: "Tracking #", datafield: '_track',
				align: "center", cellsalign: "center",
				width: 150, minwidth: 80, maxwidth: 200,
				cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties,rowdata) {
					// 15012025 grid changes code changes 
//					console.log("	row	",row);
//					console.log("	value	",value);
//					console.log("	columnfield	",columnfield);
//					console.log("	defaulthtml	",defaulthtml);
//					console.log("	rowdata	",rowdata);
//					console.log("	columnproperties	",columnproperties);
					if(self.config.ist.id == 34){
						if(rowdata.isCapa > 0){
							return '<div class="259-test-class capa-active-status" style="text-overflow: ellipsis; text-align: center; overflow: hidden; padding-bottom: 2px; margin-top: 5px;"><a href="/record/process:' +
							value + (self.config.perspective? '?perspective='+self.config.perspective:'')+'#panels">' + value + '</a></div>';
						}else{
							return '<div class="259-test-class capa-inactive-status" style="text-overflow: ellipsis; text-align: center; overflow: hidden; padding-bottom: 2px; margin-top: 5px;"><a href="/record/process:' +
							value + (self.config.perspective? '?perspective='+self.config.perspective:'')+'#panels">' + value + '</a></div>';
						}
						
					}
//                                        get active published doc details start
                                        else if (self.config.ist.id == 12) {
                                            if (rowdata.active_docs_count > 0) {
//                                                return '<div style="text-align: center; margin-top: 5px;"><a style="padding-right: 10px;" href="/record/process:' + value + (self.config.perspective ? '?perspective=' + self.config.perspective : '') + '#panels">' + value + '</a><img src="/img/active_doc_on.png" alt="Active Docs Available" /><span style=" position: absolute; top: -1px; right: 12px; border-radius: 50%; padding: 1px 4px; font-size: 10px; font-weight: bold; ">' + rowdata.active_docs_count + '</span></div>';
                                                return '<div style=" margin-top: 5px;"><a style="    padding-left: 5px;" href="/record/process:' + value + (self.config.perspective ? '?perspective=' + self.config.perspective : '') + '#panels">' + value + '</a><img style=" float: right; padding-right: 10px; " src="/img/active_doc_on.png" alt="Active Docs Available" /></div>';
                                            } else {
                                                return '<div style=" margin-top: 5px;"><a style="    padding-left: 5px;" href="/record/process:' + value + (self.config.perspective ? '?perspective=' + self.config.perspective : '') + '#panels">' + value + '</a><img style=" float: right; padding-right: 10px; " src="/img/active_doc_off.png" alt="No Active Docs" /></div>';
                                            }

                                        }
//                                        get active published doc details end
//                                        IWI get connected doc for licence monitor stert
                                        else if (self.config.ist.id == 6) {
                                            if (rowdata.connected_docs_count > 0) {
//                                                return '<div style="text-align: center; margin-top: 5px;"><a style="padding-right: 10px;" href="/record/process:' + value + (self.config.perspective ? '?perspective=' + self.config.perspective : '') + '#panels">' + value + '</a> <img src="/img/active_doc_on.png" alt="Active Docs Available" /> <span style=" position: absolute; top: -1px; right: -3px; border-radius: 50%; padding: 1px 4px; font-size: 10px; font-weight: bold; ">' + rowdata.connected_docs_count + '</span></div>';
return '<div style=" margin-top: 5px;"><a style="    padding-left: 5px;" href="/record/process:' + value + (self.config.perspective ? '?perspective=' + self.config.perspective : '') + '#panels">' + value + '</a> <img style=" float: right; padding-right: 10px; " src="/img/active_doc_on.png" alt="Active Docs Available" /> </div>';
                                            } else {
                                                return '<div style=" margin-top: 5px;"><a style="    padding-left: 5px;"  href="/record/process:' + value + (self.config.perspective ? '?perspective=' + self.config.perspective : '') + '#panels">' + value + '</a><img style=" float: right; padding-right: 10px; " src="/img/active_doc_off.png" alt="No Active Docs" /></div>';
                                            }

                                        }
//                                        IWI get connected doc for licence monitor end
                                        else{
						return '<div style="text-overflow: ellipsis; text-align: center; overflow: hidden; padding-bottom: 2px; margin-top: 5px;"><a href="/record/process:' +
						value + (self.config.perspective? '?perspective='+self.config.perspective:'')+'#panels">' + value + '</a></div>';
					}
					
				},
				//pinned: true,
				hidden: self.config.special_settings.hide_trackid == 1,
				groupable: false
			},
//                        get active published doc details start
//                            {
//                                text: "Published",
//                                datafield: "active_docs_count",
//                                align: "center",
//                                cellsalign: "center",
//                                width: 75,
//                                minwidth: 70,
//                                maxwidth: 200,
//                                filterable: false,
//
//                                cellsrenderer: function (row, columnfield, value, defaulthtml, columnproperties, rowdata) {
//                                    if (rowdata.active_docs_count > 0) {
//                                        return '<div style="text-align: center; margin-top: 5px;"><img src="/img/active_doc_on.png" alt="Active Docs Available" /><span style=" position: absolute; top: -1px; right: 12px; border-radius: 50%; padding: 1px 4px; font-size: 10px; font-weight: bold; ">'+rowdata.active_docs_count+'</span></div>';
//                                    } else {
//                                        return '<div style="text-align: center; margin-top: 5px;"><img src="/img/active_doc_off.png" alt="No Active Docs" /></div>';
//                                    }
//                                },
//                                hidden: self.config.special_settings.hide_trackid == 1,
//                                groupable: false
//                            },



//                        get active published doc details end
//                        IWI get connected doc for licence monitor start
//                            {
//                                text: "Docs",
//                                datafield: "connected_docs_count",
//                                align: "center",
//                                cellsalign: "center",
//                                width: 75,
//                                minwidth: 70,
//                                maxwidth: 200,
//                                filterable: false,
//
//                                cellsrenderer: function (row, columnfield, value, defaulthtml, columnproperties, rowdata) {
//                                    
//                                    if (rowdata.connected_docs_count > 0) {
//                                        return '<div style="text-align: center; margin-top: 5px;"><img src="/img/active_doc_on.png" alt="Active Docs Available" /> <span style=" position: absolute; top: -1px; right: 12px; border-radius: 50%; padding: 1px 4px; font-size: 10px; font-weight: bold; ">'+rowdata.connected_docs_count+'</span></div>';
//                                    } else {
//                                        return '<div style="text-align: center; margin-top: 5px;"><img src="/img/active_doc_off.png" alt="No Active Docs" /></div>';
//                                    }
//                                },
//                                hidden: self.config.special_settings.hide_trackid == 1,
//                                groupable: false
//                            },



//                        IWI get connected doc for licence monitor end
		],
		gridColumnsIndex = {"_track": 0}, cidx = 1,
		/**
		 * The grid's filter, sorting, page or page size is changed - Save the information in session so on next load the same initial conditions are kept.
		 * -- Grouping is treated differently, because grouping is a post-bind effect, taking into the consideration the data already loaded
		 */
        
		gridServerStateEvent = function(event)
		{
                    
			switch(event.type) {
			// Filter event, it affects number of returned rows, so paging must be reset
			case 'filter':
				if(event.args.filters) {
					self.config.grid_state.filters = [];
					for(c=0;c<event.args.filters.length;c++) {
						self.config.grid_state.filters.push({
							column: event.args.filters[c].datafield,
							filters: event.args.filters[c].filter.getfilters()
						});
					}
                                        need_to_reset.push('filters'); // IWI reset button only visible if filter active
				}
				break;
			// Sort by a certain column. Doesn't affect the total returned rows, or the page number
			case 'sort':
				self.config.grid_state.sort = {
					column: event.args.sortinformation.sortcolumn
				};
				if(event.args.sortinformation.sortdirection.ascending)
					self.config.grid_state.sort.direction = 'asc';
				if(event.args.sortinformation.sortdirection.descending)
					self.config.grid_state.sort.direction = 'desc';
                                    
                                     need_to_reset.push('sort'); // IWI reset button only visible if filter active
				break;
			// This changes the number of rows on a page. The grid also sends a "pagechanged" event after it, so state only needs to be saved with that event
			case 'pagesizechanged':
				return;
			// Page number is changed. Nothing else is affected, this is the lowest priority event
			case 'pagechanged':
				self.config.grid_state.pageNum = event.args.pagenum;
				self.config.grid_state.pageSize = event.args.pagesize;
                                
                                need_to_reset.push('pageNum'); // IWI reset button only visible if filter active
                                need_to_reset.push('pageSize'); // IWI reset button only visible if filter active
				break;
			case 'groupschanged':
				self.config.grid_state.grouping = event.args.groups;
                                need_to_reset.push('grouping'); // IWI reset button only visible if filter active
				break;
			}

                            // IWI reset button only visible if filter active start
                           if (need_to_reset.length > 0) {
                               $('#listViewSec [data-op="grid-state-reset"]').show();
                            }else{
                               $('#listViewSec [data-op="grid-state-reset"]').hide();
                            }
                            // IWI reset button only visible if filter active end
			// Only save the state to the server when choosing to preserve; However the browser keeps the state permanently synchronized for possibly use in the "Save View" function.
			if(!self.config.preferences.preserve)
				return;
                            
                            
                            

			$.post("?action=Save_Grid_State", {
				state: JSON.stringify(self.config.grid_state)
			}, function(x) {
				// Nothing is done here, this is just a state-update operation when the user is navigating the data. It should not bother him
			})
			.fail(cargohub.its.http_fail);
		},
		/**
		 * One of the visual aspects of the grid (column widths or position) is changed from the interface - Save the new column settings config
		 */
		gridColumnStateEvent = function(event)
		{
			switch(event.type) {
			// Column is moved to a different position, should save "column settings" and update the internal copy, just like when operating the "Columns Configuration" window and moving columns up/down
			case 'columnreordered':

				break;
			// Column width is changed. Should save the value in "column settings" for the current active view.
			case 'columnresized':

				break;
			}
		}
		;


	
        
        
		for(c=0; c<self.config.columns.length; c++) {
			var datafield = null, joinedField = null,
				column = null,
				col_cfg = self.config.columns[c],
				fk = col_cfg.fk; // the field key

			if(self.config.columns[c].v) {
				if(fk.substr(0,1)=='_') { // one of the built-in columns (system meta + org specific + cached fields)
					switch(fk.substr(1)) {
				// Primary table fields
					case 'id':
						datafield = {name: '_id', type: 'number', map: "id"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 50, filtertype: "number", align: "center", cellsalign: "right",
							groupable: false
						};
						break;
					case 'ctime':
						datafield = {name: '_ctime', type: 'date', map: "ctime"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 100, cellsformat: cargohub.date_format, align: "center", cellsalign: "center", groupable: false,
							filtertype: 'range',
							cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
								return '<div style="text-overflow: ellipsis; text-align: center; overflow: hidden; padding-bottom: 2px; margin-top: 5px;">' + cargohub.application.calc_Relative_Time(value) + '</div>';
							}
						};
						break;
					case 'edtime':
						datafield = {name: '_edtime', type: 'date', map: "edtime"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 100, cellsformat: cargohub.date_format, groupable: false, // align: "center", cellsalign: "center",
							filtertype: 'range',
							cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
								return '<div style="text-overflow: ellipsis; text-align: center; overflow: hidden; padding-bottom: 2px; margin-top: 5px;">' + cargohub.application.calc_Relative_Time(value) + '</div>';
							}
						};
						break;
					case 'master_org':
						datafield = {name: '_master_org', map: "organization>name", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 200
						};
						break;
					case 'shared':
						datafield = {name: '_shared', map: "shared", type: "bool"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							columntype: 'checkbox', filtertype: 'bool',
							width: col_cfg.w || 75,
							editable: false
						};
						break;
					// The record group
					case 'group':
						datafield = {name: '_group', map: "group>name", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 200,
							filtertype: "checkedlist", filteritems: self.config.groups, // Dropdown selector from available groups for this record type
							cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
								return '<div style="overflow: hidden; text-overflow: ellipsis; padding-bottom: 2px; text-align: left; margin-right: 2px; margin-left: 4px; margin-top: 5px;">' + $.trim(value.replace(/\[.*?\]/, '')) + '</div>';
							},
							createfilterwidget: function (column, columnElement, widget) {
								widget.jqxDropDownList({
									dropDownWidth: 320,
									animationType: "fade",
									renderer: function(index, label, value)
									{
										return $.trim(value.replace(/\[.*?\]/, ''));
									}
								});
							}
						};
						break;
					case 'submit_domain':
						datafield = {name: '_submit_domain', type: "string", map: "submit_domain"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 200
						};
						break;
					// Submitter organization (name) is taken by joining a second "organization" table for the field "submit_org_id"
					case 'submit_org':
						datafield = {name: '_submit_org', map: "submitter_org>name", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 200
						};
						break;
						// Submitter name and email are take from the joined submitter contact
					case 'submit_name':
						datafield = {name: '_submit_name', map: "submitter>name", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 120
						};
						break;
					case 'submit_email':
						datafield = {name: '_submit_email', map: "submitter>email", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 120
						};
						break;
					case 'submit_ip':
						datafield = {name: '_submit_ip', map: "submitter>ip", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 130
						};
						break;
				// SuperUser Info fields
					case 'c_convos':
						datafield = {name: '_c_convos', type: 'number', map: "count>convos"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 90, filtertype: "number", cellsalign: "center", groupable: false
						};
						break;
					case 'c_files':
						datafield = {name: '_c_files', type: 'number', map: "count>files"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 90, filtertype: "number", cellsalign: "center", groupable: false
						};
						break;
					case 'c_tasks':
						datafield = {name: '_c_tasks', type: 'number', map: "count>tasks"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 90, filtertype: "number", cellsalign: "center", groupable: false
						};
						break;
					case 'c_docs':
						datafield = {name: '_c_docs', type: 'number', map: "count>docs"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 90, filtertype: "number", cellsalign: "center", groupable: false
						};
						break;
					case 'c_contacts':
						datafield = {name: '_c_ctx', type: 'number', map: "count>ctx"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 90, filtertype: "number", cellsalign: "center", groupable: false
						};
						break;
					case 'c_updates':
						datafield = {name: '_c_updates', type: 'number', map: "count>updates"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 90, filtertype: "number", cellsalign: "center", groupable: false
						};
						break;
					case 'sui_lmdt':
						datafield = {name: '_sui_lmdt', type: 'date', map: "sui>lmdt"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 100, cellsformat: cargohub.date_format, align: "center", cellsalign: "center", groupable: false,
							filtertype: 'range'
						};
						break;
					case 'sui_lmctx':
						datafield = {name: '_sui_lmctx', type: "string", map: "sui>lmctx"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 200, groupable: false
						};
						break;

				// Per organization fields
					case 'process':
						datafield = {name: '_process', map: "process", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 90,
							filtertype: "checkedlist", filteritems: ["pending", "opened", "active", "shared", "closed", "webapi"],
							createfilterwidget: function (column, columnElement, widget) {
								widget.jqxDropDownList({
									dropDownWidth: 105,
									animationType: "fade"
								});
							}
						};
						break;
					case 'deps':
						datafield = {name: '_deps', map: "deps", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 150
						};
						break;
					case 'linktime':
						datafield = {name: '_linktime', type: 'date', map: "linktime"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 100, cellsformat: cargohub.date_format, align: "center", cellsalign: "center", groupable: false,
							filtertype: 'range'
						};
						break;
					case 'time_open':
						datafield = {name: '_time_open', type: 'date', map: "time_open"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 100, cellsformat: cargohub.date_format, align: "center", cellsalign: "center", groupable: false,
							filtertype: 'range'
						};
						break;
					case 'time_start':
						datafield = {name: '_time_start', type: 'date', map: "time_start"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 100, cellsformat: cargohub.date_format, align: "center", cellsalign: "center", groupable: false,
							filtertype: 'range'
						};
						break;
					case 'utime':
						datafield = {name: '_utime', type: 'date', map: "utime"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 100, cellsformat: cargohub.date_format, align: "center", cellsalign: "center", groupable: false,
							filtertype: 'range',
							cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
								return '<div style="text-overflow: ellipsis; text-align: center; overflow: hidden; padding-bottom: 2px; margin-top: 5px;">' + cargohub.application.calc_Relative_Time(value) + '</div>';
							}
						};
						break;
					case 'time_close':
						datafield = {name: '_time_close', type: 'date', map: "time_close"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 100, cellsformat: cargohub.date_format, align: "center", cellsalign: "center", groupable: false,
							filtertype: 'range'
						};
						break;
					case 'pending_length':
						datafield = {name: '_pending_length', type: 'number', map: "pending_length"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 100, filtertype: "number", cellsalign: "center", groupable: false,
							cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
								return '<div style="text-overflow: ellipsis; text-align: center; overflow: hidden; padding-bottom: 2px; margin-top: 5px;">' + cargohub.application.calc_Relative_Time(value, true) + '</div>';
							}
						};
						break;
					case 'process_length':
						datafield = {name: '_process_length', type: 'number', map: "process_length"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 100, filtertype: "number", cellsalign: "center", groupable: false,
							cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
								return '<div style="text-overflow: ellipsis; text-align: center; overflow: hidden; padding-bottom: 2px; margin-top: 5px;">' + cargohub.application.calc_Relative_Time(value, true) + '</div>';
							}
						};
						break;
					case 'filesize_total':
						datafield = {name: '_filesize_total', type: 'number', map: "filesize_total"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 100, filtertype: "number", cellsalign: "right", groupable: false
						};
						break;
					case 'count_active_tasks':
						datafield = {name: '_count_active_tasks', type: 'number', map: "count>active_tasks"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 90, filtertype: "number", cellsalign: "center", groupable: false
						};
						break;
					case 'count_propfields':
						datafield = {name: '_count_propfields', type: 'number', map: "count>propfields"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 90, filtertype: "number", cellsalign: "center", groupable: false
						};
						break;
					case 'commtime':
						datafield = {name: '_commtime', type: 'date', map: "commtime"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 100, cellsformat: cargohub.date_format, align: "center", cellsalign: "center", groupable: false,
							filtertype: 'range',
							cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
								return '<div style="text-overflow: ellipsis; text-align: center; overflow: hidden; padding-bottom: 2px; margin-top: 5px;">' + cargohub.application.calc_Relative_Time(value) + '</div>';
							}
						};
						break;
					case 'priority':
						datafield = {name: '_priority', map: "priority", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: '_priority',
							width: col_cfg.w || 80, align: "center", cellsalign: "center",
							filtertype: "checkedlist", filteritems: self.config.priorities,
							// Special cells renderer for priority - It displays an icon instead of text
							cellclassname: "priorityrender",
							cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
								if(value == "(unavailable)")
									return "<div class='unavailable' title='"+ value +"'>&nbsp;</div>";
								else
									return "<div class='"+ value.replace(/[ *!.0-9]/gi,'_') +"' title='"+ value +"'></div>";
							},
							createfilterwidget: function (column, columnElement, widget) {
								widget.jqxDropDownList({
									dropDownWidth: 140,
									autoDropDownHeight: true,
									renderer: function (index, label, value) {
										if(value == "(Select All)")
											return value;
										if(value && value != "(unavailable)")
											return "<div class='priorityselect "+ value.replace(/[ *!.0-9]/gi,'_') +"'>"+ (index>0?value.substr(2):value) +"</div>";
										else
											return "(unavailable)";
									},
									animationType: "fade"
								});
							}
						};
						// hidden: true,
						break;
					case 'status':
						datafield = {name: '_status', map: "status", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 120, align: "center", cellsalign: "center",
							filtertype: "checkedlist", filteritems: self.config.statuses,
							cellclassname: "statusrender",
							cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
								if(value == "(unavailable)")
									return "<div class='unavailable' title='"+ value +"'>&nbsp;</div>";
								else
									return "<div class='"+ value.replace(/[ \/\\*!.0-9]/gi,'_') +"' title='"+ value +"'   testtitle='"+ value +"'><sup>"+ value +"</sup></div>";
							},
							createfilterwidget: function (column, columnElement, widget) {
								widget.jqxDropDownList({
									dropDownWidth: 200,
									renderer: function (index, label, value) {
										if(value == "(Select All)")
											return value;
										if(value && value != "(unavailable)")
											return "<div class='statusselect "+ value.replace(/[ \/\\*!.0-9]/gi,'_') +"'>"+ value +"</div>";
										else
											return "(unavailable)";
									},
									animationType: "fade"
								});
							}
						};
						break;
					case 'xfer_time':
						datafield = {name: '_xfer_time', type: 'date', map: "xfer>time"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 100, cellsformat: cargohub.date_format, align: "center", cellsalign: "center", groupable: false,
							filtertype: 'range',
							cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
								return '<div style="text-overflow: ellipsis; text-align: center; overflow: hidden; padding-bottom: 2px; margin-top: 5px;">' + cargohub.application.calc_Relative_Time(value) + '</div>';
							}
						};
						break;
					case 'xfer_ip':
						datafield = {name: '_xfer_ip', map: "xfer>ip", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 130
						};
						break;
					case 'xfer_confirmip':
						datafield = {name: '_xfer_confirmip', map: "xfer>confirmip", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 130
						};
						break;
					case 'xfer_delivered':
						datafield = {name: '_xfer_delivered', map: "xfer>delivered", type: "bool"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							columntype: 'checkbox', filtertype: 'bool',
							width: col_cfg.w || 90
						};
						break;
					// Contacts involved into inter-organization communication
					case 'sender_name':
						datafield = {name: '_sender_name', map: "sender>name", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 120
						};
						break;
					case 'sender_email':
						datafield = {name: '_sender_email', map: "sender>email", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 120
						};
						break;
					case 'recipient_name':
						datafield = {name: '_recipient_name', map: "recipient>name", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 120
						};
						break;
					case 'recipient_email':
						datafield = {name: '_recipient_email', map: "recipient>email", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 120
						};
						break;

					// The assigned user
					case 'assigned':
						datafield = {name: '_assigned', map: "assigned>name", type: "string"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 120
						};
						break;

					// Per user data (Markers)
					case 'access':
						datafield = {name: '_access', type: 'date', map: "marker>access_ut"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 100, cellsformat: cargohub.date_format, align: "center", cellsalign: "center", groupable: false,
							filtertype: 'range',
							cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
								return '<div style="text-overflow: ellipsis; text-align: center; overflow: hidden; padding-bottom: 2px; margin-top: 5px;">' + cargohub.application.calc_Relative_Time(value) + '</div>';
							}
						};
						break;
					case 'export':
						datafield = {name: '_export', type: 'date', map: "marker>export_ut"};
						column = {
							text: self.config.bic[datafield.name], datafield: datafield.name,
							width: col_cfg.w || 100, cellsformat: cargohub.date_format, align: "center", cellsalign: "center", groupable: false,
							filtertype: 'range',
							cellsrenderer: function(row, columnfield, value, defaulthtml, columnproperties) {
								return '<div style="text-overflow: ellipsis; text-align: center; overflow: hidden; padding-bottom: 2px; margin-top: 5px;">' + cargohub.application.calc_Relative_Time(value) + '</div>';
							}
						};
						break;

					default:
						// Is it a cached field ? They are always standard strings, searchable with a text input - Should be disabled, not working properly
						if(fk.substr(0,7)=='_cache_') {
							datafield = {name: fk, map: "cache>"+fk.substr(7) };
							column = {
								text: self.config.bic[fk], datafield: datafield.name,
								width: col_cfg.w || 200, cellsalign: "center"
							};
							break;
						} else
							continue; // unknown (yet) column?
					}
					// Default section
					if(!self.config.columns[c].s)
						self.config.columns[c].s = "(Global)";
				} else if(fk.substr(0,1)=='@') { // Submitter Contact Columns - These are all text fields, searchable with a text input
					datafield = {name: fk, map: "submitter>xdata>"+fk.substr(1), type: "string"};
					column = {
						text: "Subm. "+self.config.sconcs[fk], datafield: datafield.name,
						width: col_cfg.w || 150, sortable: false, // Disable sorting, at the moment it's impossible to sort by contact xdata (it's stored as JSON in the db)
						filtertype: 'textbox', filtercondition: 'STARTS_WITH'
					};
					self.config.columns[c].s = "(Submitter)";
				} else { // xfields

					var fd = self.config.xfield_def[fk]; // the field definition
					if(!fd)
						continue;

					// default datafield and column settings
					datafield = {
						type: 'string',
						name: fk,
						map: "xfields>"+ fk
					};
					column = {
						text: fd.n,
						datafield: fk,
						width: parseInt(col_cfg.w || (fd.o? fd.o.colw: false) || 120) // defaults to 120 but can be overrriden by the "colw" option or user's custom column width (in col_cfg)
					};
					self.config.columns[c].s = fd.s? ((self.config.xf_sections && self.config.xf_sections[fd.s])? self.config.xf_sections[fd.s]: '__'+ fd.s+ '__'): "(not sorted)";

					// other column parameters depending on type
					switch(fd.t) {
						case 'text':
							column.filtertype = 'textbox';
							column.filtercondition = 'CONTAINS';
							break;
						case 'number':
							datafield.type = 'number';
							column.filtertype = 'number';
							column.align = "right";
							column.cellsalign = "right";
							break;
						case 'date':
							datafield.type = 'date';
							column.filtertype = 'range';
							column.cellsformat = cargohub.date_format;
							break;
						case 'enum':
							column.filtertype = "checkedlist";
							column.filteritems = object_to_array(fd.cd_ex); // many => many
							break;
						case 'set':
							column.filtertype = "checkedlist";
							column.filteritems = object_to_array(fd.cd_ex); // server should assamble the correct unique filter out of all the passed options
							break;
						// plugin based columns:
						case 'money':
							datafield.type = 'number';
							datafield.map = "xfields>"+ fk+ ">amtf";
							column.cellsformat = 'd2';
							column.filtertype = 'textbox';
							column.filtercondition = '_MONEY';
							column.align = "right";
							column.cellsalign = "right";
							// Note that for the moment this special joined field cannot be modified from the IST - No options
							joinedField = {
								'datafield': {
									type: 'string',
									name: '~'+fk, // prefix ~ indicates it is an attached column to another field, so the search knows what plugin to use
									map: "xfields>"+ fk+ ">curr"
								},
								'column': {
									text: "(Curr)",
									datafield: '~'+fk,
									width: 60,
									filterable: false,
//									filtertype: "list",
//									filteritems: self.config.ext_col_data.currencies, // Use a list of all existing countries when this field exists (loaded in advance)
									cellsalign: "center",
									createfilterwidget: function (column, columnElement, widget) {
										widget.jqxDropDownList({
											dropDownWidth: 130,
											animationType: "fade",
											placeHolder: "???"
										});
									},
									sortable: false
								}
							};
							break;
						case 'airport':
							column.sortable = false;
							datafield.map = "xfields>"+ fk+ ">airport";
							// Note that for the moment this special joined field cannot be modified from the IST - No options
							joinedField = {
								'datafield': {
									type: 'string',
									name: '~'+fk, // prefix ~ indicates it is an attached column to another field, so the search knows what plugin to use
									map: "xfields>"+ fk+ ">country"
								},
								'column': {
									text: "(and Country)",
									datafield: '~'+fk,
									width: col_cfg.w || 150,
									filtertype: "list",
									filteritems: self.config.ext_col_data.countries, // Use a list of all existing countries when this field exists (loaded in advance)
									sortable: false
								}
							};
							break;
					}

					// column rendering option/override (changes some parameters)
					if(fd.o && fd.o.colr) {
						switch(fd.o.colr) {
						case 1: // Currency - Adds .nn to the number in ALL cases (even for integer values)
							column.cellsformat = "d2";
							break;
						case 2: // Yes/No (Checkbox) - Is not working (MUST be bool, Yes/No doesn't work)
//							datafield.type = 'bool';
//							column.columntype = 'checkbox';
//							column.filtertype = 'bool';
							column.editable = false;
							break;
						case 3: // Center align
							column.align = "center";
							column.cellsalign = "center";
							break;
						case 4: // Right align
							column.align = "right";
							column.cellsalign = "right";
							break;
						case 5: // Relative date
							// TODO
							break;
						case 6: // Only date, no time
							column.cellsformat = cargohub.date_format;
							break;
						case 7: // 24 Hour Clock
							datafield.type = 'text';
							column.filterable = false;
							column.cellsalign = "center";
							column.align = "center";
							break;
						default:
							// For anything else, the visual is not changed
						}
					}

				}
				if(column != null){
					self.config.columns[c].text = column.text; // Plug in information for the column editor
					gridDataFields.push(datafield);
					gridColumns.push(column);
					gridColumnsIndex[column.datafield] = cidx; cidx++;

					// Add a secondary column for some special fields:
					if(joinedField) {
						gridDataFields.push(joinedField.datafield);
						gridColumns.push(joinedField.column);
						gridColumnsIndex[joinedField.column.datafield] = cidx; cidx++;
					}
				}
			} else {
				// when not visible, the text (column name) is still needed for the columns editor, even if that one doesn't show up in the grid
				// TODO: the "s" value is redundant and inflexible, should be added outside regardless of visibility
				if(fk.substr(0,1)=='_') { // built in fields
					self.config.columns[c].text = self.config.bic[fk];
					self.config.columns[c].s = "(Global)";
				} else if(fk.substr(0,1)=='@') { // submitter contact columns
					self.config.columns[c].text = "Subm. "+self.config.sconcs[fk];
					self.config.columns[c].s = "(Submitter)";
				} else {
					var fd = self.config.xfield_def[fk];
					self.config.columns[c].text = fd.n;
					self.config.columns[c].s = fd.s? ((self.config.xf_sections && self.config.xf_sections[fd.s])? self.config.xf_sections[fd.s]: '__'+ fd.s+ '__'): "(not sorted)";
				}
			}
		}

		// initial filters are added to the "columns" (take from the saved grid_state)
		if(self.config.grid_state.filters) {
			var filterdata = null, jqxfilter = null, fd = null, c2;
			for(c=0; c<self.config.grid_state.filters.length; c++) {
				filterdata = self.config.grid_state.filters[c];
                                

				if(!gridColumnsIndex[filterdata.column])
					continue; // Column not found, skip

				// Create the jqxFilter (may have multiple filters in it - checkboxes for example)
				jqxfilter = new $.jqx.filter();
				for(c2=0; c2< filterdata.filters.length; c2++) {
					fd = filterdata.filters[c2];
					// Some little defaults
					if(!fd.operator) fd.operator = 1;
					if(!fd.type) fd.type = "stringfilter";
					// Add the filter
					jqxfilter.addfilter(fd.operator, jqxfilter.createfilter(fd.type, fd.value, fd.condition));
				}

				// ... and apply it to the proper column
				gridColumns[gridColumnsIndex[filterdata.column]].filter = jqxfilter;
                                
                                need_to_reset.push('filters');  // IWI reset button only visible if filter active
			}
		}

		// prepare the data
                console.log('dsfdfdf',xfilters.exgroup+ (xfilters.ugroup.length? '|'+ xfilters.ugroup.join(','):''));
		gridDataAdapter = new $.jqx.dataAdapter({ // source
			url: "?action=get_list",
			type: "POST", // Do it as post due to possible long request URL (so it works with large filters)
			data: { // extra vars, do the interface to set/configure those?
			//"custom[maxrows]": 100
				"custom[xfilters]": xfilters.exgroup+ (xfilters.ugroup.length? '|'+ xfilters.ugroup.join(','):'')
			},
			datatype: "json",
			datafields: gridDataFields,
			id: "id",
			root: "data",
			// server side operations - filtering and sorting:
			cache: false,
			filter: function() {
				$("#issues-grid").jqxGrid('updatebounddata', 'filter');
			},
			sort: function() {
				$("#issues-grid").jqxGrid('updatebounddata', 'sort');
			},
			beforeprocessing: function(source) {
				this.totalrecords = source.found_rows;
			},
			formatData: function(data) {
				var filters = {}, filterscount = 0, field, c2, set_vals, set_test;
				// Formats the filters that are sent from the grid in a more compact and logical format
				if(data.filterscount > 0) {
					for(c=0; c< data.filterscount; c++) {
						field = data["filterdatafield" + c];
						if(!filters[field]) {
							filters[field] = [];
							filterscount++;
						}
						filters[field].push({
							c: data["filtercondition" + c], // condition
							v: data["filtervalue" + c] // value
						});
					}
					// Detect checkbox filters (with 3 or more "EQUAL" conditions on the same field) - They are converted to a "IN_SET" condition and the value as an array - for even more compact data transmission
					for(c in filters) {
						if(filters[c].length > 2) {
							set_test = 1; set_vals = []; not_set_vals = [];
							for(c2 = 0; c2< filters[c].length; c2++) {
								if(filters[c][c2].c == 'EQUAL')
									set_vals.push(filters[c][c2].v);
								else if(filters[c][c2].c == 'NOT_EQUAL') {
									not_set_vals.push(filters[c][c2].v);
									set_test = -1;
								} else {
									set_test = 0;
									break;
								}
							}
							if(set_test == 1)
								filters[c] = [{
									c: "IN_SET",
									v: set_vals
								}];
							else if(set_test == -1)
								filters[c] = [{
									c: "NOT_IN_SET",
									v: not_set_vals
								}];
						}
					}
					// Filters are processed, removed the old format which eats a lot of request space
					for(c in data) {
						if(c.substr(0,15) == 'filtercondition' || c.substr(0,15) == 'filterdatafield' || c.substr(0,14) == 'filteroperator' || c.substr(0,11) == 'filtervalue')
							delete data[c];
					}
					// ... and add the compressed format as a JSON string
					data.filters = JSON.stringify(filters);
					data.filterscount = filterscount;
				}
			}
		});
                
               
                //get active published doc details start
//                if (self.config.ist.id != '12') {
//                    $("#issues-grid").on("bindingcomplete", function () {
//                        $("#issues-grid").jqxGrid("hidecolumn", "active_docs_count");
//                    });
//                }
//                //get active published doc details end
//////IWI get connected doc for licence monitor start
//                if (self.config.ist.id != '6') {
//                    $("#issues-grid").on("bindingcomplete", function () {
//                        $("#issues-grid").jqxGrid("hidecolumn", "connected_docs_count");
//                    });
//                }
//IWI get connected doc for licence monitor end
		// Check state for filtering, sorting, page number and size and prepare the DataAdapter before the grid is initialized, so it starts with a permanent state.
		gridDataAdapter._source.pagenum = self.config.grid_state.pageNum;
		if(self.config.grid_state.sort) {
			gridDataAdapter._source.sortcolumn = self.config.grid_state.sort.column;
			gridDataAdapter._source.sortdirection = self.config.grid_state.sort.direction;
		}

		// Default Grouping?
		if(!self.config.grid_grouping)
			self.config.grid_grouping = [];

		var issuesGroupsRenderer = function (text, group, expanded, data)
		{
			// Count the number of lowest level (the actual records) for each group (go recursively into subGroups)
			var item_count = 0, count_SubGroup = function(set) {
				if(set.subItems.length > 0)
					item_count += set.subItems.length;
				else
					for(var c = 0; c< set.subGroups.length; c++) {
						count_SubGroup(set.subGroups[c]);
					}
			};

			// when there are no subgroups
			count_SubGroup(data);

			if(data.groupcolumn.filtertype == 'number') {
				if(group == "0" || !group)
					group = "--";
			}
			if(data.groupcolumn.filtertype == 'date') {
				if(group)
					group = $.format.date(group, cargohub.date_format);
				else
					group = "(not set)";
			}

			item_count = " ("+ item_count +" record" +(item_count>1?"s":"") + " on page)";
			return "<div class='groupcontainer "+ group.toString().replace(/[ *!.0-9]/gi,'_') +"'><div class='grouptext' style='font-weight: bold;'>" + data.groupcolumn.text + ":</div><div class='grouptext'>"+ group + item_count + "</div></div>";
		};

		// Test all grouping and make sure those columns actually exist in the grid
		for(var c1 = 0; c1< self.config.grid_state.grouping.length; c1++) {
			var colfound = false;
			for(var c2 = 0; c2< gridColumns.length; c2++)
				if(gridColumns[c2].datafield == self.config.grid_state.grouping[c1])
					colfound = true;
			if(!colfound)
				self.config.grid_state.grouping.splice(c1, 1);
		}
                
                
                
                
                
        
            
            
        // 25062024  created by iwi | start 
		var getCockpitname = $("#issues-grid").attr("data-rtname");    
		console.log(" 1123 ",getCockpitname);
                
                
                
            
    
    
    
    
    
        if(getCockpitname == 'cert'){
		
			//   ==========Nested= Start 25062024  created by iwi ======================
			var ordersSource =
			{
				datafields: [
					{ name: 'id', type: 'string' },
					{ name: 'document_link', type: 'string' },
					{ name: 'name_or_title', type: 'string' },
					{ name: 'internal_external', type: 'string' },
					{ name: 'valid_from', type: 'string' },
					{ name: 'expire_date', type: 'string' },
					{ name: 'next_review', type: 'string' },
					{ name: 'id_val', type: 'string' },
				],
				id: "id_val",
				root: "data",
				datatype: "json",
				url: "?action=license_monitor_grid_list",
				type: "POST", // Do it as post due to possible long request URL (so it works with large filters)
				async: false
			};
			var ordersDataAdapter = new $.jqx.dataAdapter(ordersSource, { autoBind: true });
			orders = ordersDataAdapter.records;
			var nestedGrids = new Array();
			// create nested grid.
			var initrowdetails = function (index, parentElement, gridElement, record) {
				var id = record.uid.toString();
				var grid2 = $($(parentElement).children()[0]);
				nestedGrids[index] = grid2;
				var filtergroup = new $.jqx.filter();
				var filter_or_operator = 1;
				var filtervalue = id;
				var filtercondition = 'equal';
				var filter = filtergroup.createfilter('stringfilter', filtervalue, filtercondition);
				// fill the orders depending on the id.
				var ordersbyid = [];
				for (var m = 0; m < orders.length; m++) {
					var result = filter.evaluate(orders[m]["id"]);
					if (result)
						ordersbyid.push(orders[m]);
				}
				var ordersSource = { datafields: [
					{ name: 'id', type: 'string' },
					{ name: 'document_link', type: 'string' },
					{ name: 'name_or_title', type: 'string' },
					{ name: 'internal_external', type: 'string' },
					{ name: 'valid_from', type: 'string' },
					{ name: 'expire_date', type: 'string' },
					{ name: 'next_review', type: 'string' },
					{ name: 'id_val', type: 'string' },
				],
					id: 'id_val',
					localdata: ordersbyid
				}
				var nestedGridAdapter2 = new $.jqx.dataAdapter(ordersSource);
				if (grid2 != null) {
					grid2.jqxGrid({
						source: nestedGridAdapter2, width: 1000,height: 170,
						columns: [
							{ text: 'Document Link', datafield: 'document_link', width: 150 },
							{ text: 'Name or Title', datafield: 'name_or_title', width: 450  },
							{ text: 'Type', datafield: 'internal_external', width: 100  },
							{ text: 'Valid Date', datafield: 'valid_from', width: 100  },
							{ text: 'Expire Date', datafield: 'expire_date', width: 100  },
							{ text: 'Next Review', datafield: 'next_review', width: 100  },
						]
					});
				}
			}
			//                ==========Nested====End ===================
		}else if(getCockpitname == 'tra_scheduler'){
			//   ==========Nested= Start 20082024  created by iwi ======================
			var ordersSource =
			{
				datafields: [
					{ name: 'id', type: 'string' },
					{ name: 'training_name', type: 'string' },
					{ name: 'result', type: 'string' },
					{ name: 'score', type: 'string' },
					{ name: 'trainee_type_lable', type: 'string' },
					{ name: 'id_val', type: 'string' },
				],
				id: "id_val",
				root: "data",
				datatype: "json",
				url: "?action=training_scheduler_grid_list",
				type: "POST", // Do it as post due to possible long request URL (so it works with large filters)
				async: false
			};
			var ordersDataAdapter = new $.jqx.dataAdapter(ordersSource, { autoBind: true });
			orders = ordersDataAdapter.records;
			var nestedGrids = new Array();
			// create nested grid.
			var initrowdetails = function (index, parentElement, gridElement, record) {
				var id = record.uid.toString();
				var grid2 = $($(parentElement).children()[0]);
				nestedGrids[index] = grid2;
				var filtergroup = new $.jqx.filter();
				var filter_or_operator = 1;
				var filtervalue = id;
				var filtercondition = 'equal';
				var filter = filtergroup.createfilter('stringfilter', filtervalue, filtercondition);
				// fill the orders depending on the id.
				var ordersbyid = [];
				for (var m = 0; m < orders.length; m++) {
					var result = filter.evaluate(orders[m]["id"]);
					if (result)
						ordersbyid.push(orders[m]);
				}
				var ordersSource = { datafields: [
					{ name: 'id', type: 'string' },
					{ name: 'training_name', type: 'string' },
					{ name: 'result', type: 'string' },
					{ name: 'score', type: 'string' },
					{ name: 'trainee_type_lable', type: 'string' },
					{ name: 'id_val', type: 'string' },
				],
					id: 'id_val',
					localdata: ordersbyid
				}
				var nestedGridAdapter2 = new $.jqx.dataAdapter(ordersSource);
				if (grid2 != null) {
					grid2.jqxGrid({
						source: nestedGridAdapter2, width: 700,height: 200,
						columns: [
							{ text: 'Trainee Name', datafield: 'training_name', width: 150 },
							{ text: 'Result', datafield: 'result', width: 100  },
							{ text: 'Score', datafield: 'score', width: 100  },
							{ text: 'Trainee Type', datafield: 'trainee_type_lable', width: 100  },
						]
					});
				}
			}
			//                ==========Nested====End ===================
		}
		// 24102024 Audit control Nested grid start
		else if(getCockpitname == 'audit'){
			
			var ordersSource =
			{
				datafields: [
					{ name: 'id', type: 'string' },
					{ name: 'id_val_name', type: 'string' },
					{ name: 'title', width: 350 },// 06012025 audit control grid changes
					{ name: 'reference_no', width: 150 },
					{ name: 'risk', type: 'string' },
					{ name: 'type', type: 'string' },
					{ name: 'person', type: 'string' },
					{ name: 'id_val', type: 'string' },
					{ name: 'isCreateTaskLable', type: 'string' },
					{ name: 'status', type: 'string' },
					{ name: 'deadline_date', type: 'string' },// 06012025 audit control grid changes
				],
				id: "id_val",
				root: "data",
				datatype: "json",
				url: "?action=audit_control_grid_list",
				type: "POST", // Do it as post due to possible long request URL (so it works with large filters)
				async: false
			};
			var ordersDataAdapter = new $.jqx.dataAdapter(ordersSource, { autoBind: true });
			orders = ordersDataAdapter.records;
			var nestedGrids = new Array();
			// create nested grid.
			var initrowdetails = function (index, parentElement, gridElement, record) {
				var id = record.uid.toString();
				var grid2 = $($(parentElement).children()[0]);
				nestedGrids[index] = grid2;
				var filtergroup = new $.jqx.filter();
				var filter_or_operator = 1;
				var filtervalue = id;
				var filtercondition = 'equal';
				var filter = filtergroup.createfilter('stringfilter', filtervalue, filtercondition);
				// fill the orders depending on the id.
				var ordersbyid = [];
				for (var m = 0; m < orders.length; m++) {
					var result = filter.evaluate(orders[m]["id"]);
					if (result)
						ordersbyid.push(orders[m]);
				}
				var ordersSource = { datafields: [
					{ name: 'id', type: 'string' },
					{ name: 'id_val_name', type: 'string' },
					{ name: 'title', type: 'string' },
					{ name: 'reference_no', type: 'string' },
					{ name: 'risk', type: 'string' },
					{ name: 'type', type: 'string' },
					{ name: 'person', type: 'string' },
					{ name: 'isCreateTaskLable', type: 'string' },
					{ name: 'status', type: 'string' },
					{ name: 'id_val', type: 'string' },
					{ name: 'deadline_date', type: 'string' },// 06012025 audit control grid changes
					
				],
					id: 'id_val',
					localdata: ordersbyid
				}
				var nestedGridAdapter2 = new $.jqx.dataAdapter(ordersSource);
				if (grid2 != null) {
					grid2.jqxGrid({
						source: nestedGridAdapter2, width: 1200,height: 250,
						columns: [
							// 06012025 audit control grid changes 
							{ text: 'ID', datafield: 'id_val_name', width: 150 },
							{ text: 'Title', datafield: 'title', width: 350 },// 06012025 audit control grid changes
							{ text: 'Type', datafield: 'type', width: 150 },
							{ text: 'Risk', datafield: 'risk', width: 150 },
							{ text: 'Assigned To', datafield: 'person', width: 150 },
							{ text: 'Deadline Date', datafield: 'deadline_date', width: 150 },
							{ text: 'Status', datafield: 'status', width: 150 },
							{ text: 'Closing Date', datafield: 'closing_date', width: 150 },
							{ text: 'Area', datafield: 'area', width: 150 },
							// 06012025 audit control grid changes
						]
					});
				}
			}

		}
		// 24102024 Audit control Nested grid end
		// 30122024 Quality Event Nested grid start
		else if(getCockpitname == 'event'){
			
			var ordersSource =
			{
				datafields: [
					{ name: 'id', type: 'string' },
					{ name: 'title', type: 'string' },
					{ name: 'id_val', type: 'string' },
					{ name: 'id_val_name', type: 'string' },
					{ name: 'type', type: 'string' },
					{ name: 'risk', type: 'string' },
					{ name: 'assigned_to', type: 'string' },
					{ name: 'deadline_date', type: 'string' },
					{ name: 'status', type: 'string' },
					{ name: 'closing_date', type: 'string' },
					{ text: 'Area', datafield: 'area', width: 150 },
				],
				id: "id_val",
				root: "data",
				datatype: "json",
				url: "?action=quality_event_grid_capa_list",
				type: "POST", // Do it as post due to possible long request URL (so it works with large filters)
				async: false
			};
			var ordersDataAdapter = new $.jqx.dataAdapter(ordersSource, { autoBind: true });
			orders = ordersDataAdapter.records;
			var nestedGrids = new Array();
			// create nested grid.
			var initrowdetails = function (index, parentElement, gridElement, record) {
				var id = record.uid.toString();
				var grid2 = $($(parentElement).children()[0]);
				nestedGrids[index] = grid2;
				var filtergroup = new $.jqx.filter();
				var filter_or_operator = 1;
				var filtervalue = id;
				var filtercondition = 'equal';
				var filter = filtergroup.createfilter('stringfilter', filtervalue, filtercondition);
				// fill the orders depending on the id.
				var ordersbyid = [];
				for (var m = 0; m < orders.length; m++) {
					var result = filter.evaluate(orders[m]["id"]);
					if (result)
						ordersbyid.push(orders[m]);
				}
				var ordersSource = { datafields: [
					{ name: 'id', type: 'string' },
					{ name: 'title', type: 'string' },
					{ name: 'id_val', type: 'string' },
					{ name: 'id_val_name', type: 'string' },
					{ name: 'type', type: 'string' },
					{ name: 'risk', type: 'string' },
					{ name: 'assigned_to', type: 'string' },
					{ name: 'deadline_date', type: 'string' },
					{ name: 'status', type: 'string' },
					{ name: 'closing_date', type: 'string' },
					{ text: 'Area', datafield: 'area', width: 150 },
				],
					id: 'id_val',
					localdata: ordersbyid
				}
				var nestedGridAdapter2 = new $.jqx.dataAdapter(ordersSource);
				if (grid2 != null) {
					grid2.jqxGrid({
						source: nestedGridAdapter2, width: 1200,height: 250,
						columns: [
							{ text: 'ID', datafield: 'id_val_name', width: 150 },
							{ text: 'Title', datafield: 'title', width: 350 },
							{ text: 'Type', datafield: 'type', width: 150 },
							{ text: 'Risk', datafield: 'risk', width: 150 },
							{ text: 'Assigned To', datafield: 'assigned_to', width: 150 },
							{ text: 'Deadline Date', datafield: 'deadline_date', width: 150 },
							{ text: 'Status', datafield: 'status', width: 150 },
							{ text: 'Closing Date', datafield: 'closing_date', width: 150 },
							{ text: 'Area', datafield: 'area', width: 150 },
						]
					});
				}
			}

		}
		// 30122024 Quality Event Nested grid end
		// 18022025 Documentation Nested grid start
		else if(getCockpitname == 'doc'){
			var url_pass_org_id = new URLSearchParams(window.location.search).get("org_id") || 0;
			var ordersSource =
			{
				datafields: [
					{ name: 'id', type: 'string' },
					{ name: 'title', type: 'string' },
					{ name: 'id_val', type: 'string' },	

					{ name: 'ref_number', type: 'string' },	
					{ name: 'update_dt', type: 'string' },	
					{ name: 'version', type: 'string' },	
					{ name: 'visibility', type: 'string' },	
					{ name: 'last_editor_name', type: 'string' },	
					{ name: 'org_name', type: 'string' },	
				],
				id: "id_val",
				root: "data",
				datatype: "json",
				url: "?action=doc_nested_grid_list_sec",
				type: "POST", // Do it as post due to possible long request URL (so it works with large filters)
				async: false,
				data: {
					org_id: url_pass_org_id
				}
			};
			var ordersDataAdapter = new $.jqx.dataAdapter(ordersSource, { autoBind: true });
			orders = ordersDataAdapter.records;
			var nestedGrids = new Array();
			// create nested grid.
			var initrowdetails = function (index, parentElement, gridElement, record) {
				var id = record.uid.toString();
				var grid2 = $($(parentElement).children()[0]);
				nestedGrids[index] = grid2;
				var filtergroup = new $.jqx.filter();
				var filter_or_operator = 1;
				var filtervalue = id;
				var filtercondition = 'equal';
				var filter = filtergroup.createfilter('stringfilter', filtervalue, filtercondition);
				// fill the orders depending on the id.
				var ordersbyid = [];
				for (var m = 0; m < orders.length; m++) {
					var result = filter.evaluate(orders[m]["id"]);
					if (result)
						ordersbyid.push(orders[m]);
				}
				var ordersSource1 = { datafields: [
					{ name: 'id', type: 'string' },
					{ name: 'title', type: 'string' },
					{ name: 'id_val', type: 'string' },

					{ name: 'ref_number', type: 'string' },	
					{ name: 'update_dt', type: 'string' },	
					{ name: 'version', type: 'string' },	
					{ name: 'visibility', type: 'string' },
					{ name: 'last_editor_name', type: 'string' },
					{ name: 'org_name', type: 'string' }
				],
					id: 'id_val',
					localdata: ordersbyid
				}
				var nestedGridAdapter3 = new $.jqx.dataAdapter(ordersSource1);
				if (grid2 != null) {
					grid2.jqxGrid({
						width: 1200,          // Set the width of the grid to 1200px
                                                autowidth: true, 
    height: 'auto',       // Auto-adjust height based on the content
    columnsresize: true,  // Enable resizing of columns
    autoheight: true,     // Automatically adjust the height based on content
    pageable: true,       // Enable paging (if necessary)
    sortable: true,       // Enable sorting of columns
    filterable: true,     // Enable filtering on columns
    showtoolbar: true,    // Show the toolbar for additional options
    altrows: true,  
						source: nestedGridAdapter3, 
						columns: [
							{ text: 'Document Name', datafield: 'title', width: 350 },
							{ text: 'Ref Number', datafield: 'ref_number', width: 150 },
							{ text: 'Version', datafield: 'version', width: 80 },
							{ text: 'last Updated', datafield: 'update_dt', width: 100 },
							
							{ text: 'Last edited by', datafield: 'last_editor_name', width: 100 },
							{ text: 'Organization', datafield: 'org_name', width: 100 },
							{ text: 'Visibility', datafield: 'visibility', width: 100 },
							
						]
					});



					grid2.on('bindingcomplete', function () {
                                           // console.log("Nested grid opened:", id);

                                            var rowCount = grid2.jqxGrid('getrows').length;

//                                            if (rowCount > 0) {
//                                                rowCount = rowCount + 5;
//                                            } else {
//                                                rowCount = rowCount + 5;
//                                            }
                                            rowCount = rowCount + 4;
                                            var rowHeight = 35;
                                            var newHeight = rowCount * rowHeight;

                                                console.log("rowCount", rowCount);

                                            // Adjust the height of ONLY the parent container of THIS nested grid
                                            var parentContainer = grid2.parent().filter('.jqx-enableselect, .jqx-widget-content, .jqx-widget-content-arctic');
                                            if (parentContainer.length) {
                                                parentContainer.height(newHeight);
                                                console.log("Parent container height updated:", newHeight);
                                            }
                                        });
                                        
                                         setTimeout(() => {
                                            grid2.jqxGrid('updatebounddata');
                                        }, 100);

				}
			}

		}
		// 18022025 Documentation Nested grid end
                
                
//                IWI Create nested row for contracts in supplier control list page 29062026 start
               else if (getCockpitname == 'contract') {

        var nestedGrids = [];
                var initrowdetails = function(index, parentElement, gridElement, record) {
                var parentTrack = record._track;
                        var grid2 = $($(parentElement).children()[0]);
                        nestedGrids[index] = grid2;
                        var ordersSource = {
                        datatype: "json",
                                root: "data",
                                id: "id_val",
                                url: "?action=contract_nested_grid_list_sec",
                                type: "POST",
                                data: {
                                track: parentTrack
                                },
                                datafields: [
                                {
                                name: 'id',
                                        type: 'number'
                                }, {
                                name: 'org_id',
                                        type: 'number'
                                }, {
                                name: 'contract_name',
                                        type: 'string'
                                }, {
                                name: 'contract_nr',
                                        type: 'string'
                                }, {
                                name: 'contract_type',
                                        type: 'string'
                                }, {
                                name: 'contract_period',
                                        type: 'string'
                                }, {
                                name: 'start_date',
                                        type: 'string'
                                }, {
                                name: 'end_date',
                                        type: 'string'
                                }, {
                                name: 'terminate_date',
                                        type: 'string'
                                }, {
                                name: 'notice_period',
                                        type: 'string'
                                }, {
                                name: 'year_value',
                                        type: 'string'
                                }, {
                                name: 'month_value',
                                        type: 'string'
                                }

                                ]
                        };
                        var nestedGridAdapter = new $.jqx.dataAdapter(ordersSource);
                        if (grid2 != null) {
                grid2.jqxGrid({
                width: 1341,
                        autoheight: true,
                        columnsresize: true,
                        pageable: true,
                        sortable: true,
                        filterable: true,
                        altrows: true,
                        source: nestedGridAdapter,
                        columns: [
                        {
                        dataField: 'contract_name',
                                text: 'Contract Name',
                                width: '12%'
                        },
                        {
                        dataField: 'contract_nr',
                                text: 'Contract Nr',
                                width: '10%'
                        },
                        {
                        dataField: 'contract_type',
                                text: 'Type',
                                width: '10%'
                        },
                        {
                        dataField: 'contract_period',
                                text: 'Period',
                                width: '10%'
                        },
                        {
                        dataField: 'start_date',
                                text: 'Start Date',
                                width: '8%'
                        }, {
                        dataField: 'end_date',
                                text: 'End Date',
                                width: '8%'
                        }, {
                        dataField: 'terminate_date',
                                text: 'Notice Date',
                                width: '8%'
                        },
                        {
                        dataField: 'notice_period',
                                text: 'Notice Period',
                                width: '10%'
                        }, {
                        dataField: 'year_value',
                                text: 'Year Value (&euro;)',
                                width: '12%'
                        }, {
                        dataField: 'month_value',
                                text: 'Month Value (&euro;)',
                                width: '12%'
                        }]
                });
                        grid2.on('bindingcomplete', function() {
                        var rows = grid2.jqxGrid('getrows').length;
                                if (rows < 1) {
                        rows = 1;
                        }
                        var newHeight = (rows * 35) + 60;
                                $(parentElement).height(newHeight);
                        });
                }
                };
        }

//                IWI Create nested row for contracts in supplier control list page 29062026 end
		else{
			var initrowdetails = {};
		}
        // 25062024  created by iwi | end 


		$("#issues-grid").jqxGrid({
			theme: cargohub.jqxtheme,
			// fully flexible ! How the grid is initialized depends on the issue type (and in the future, by the user's settings)
			source: gridDataAdapter,
			width: cargohub.fullwidth, rowsheight: 27, autoheight: true,
			columns: gridColumns, columnsmenu: false,
//			columnsresize: true, columnsreorder: true,
			pageable: true,
			// pagesize: self.config.grid_state.pageSize, 
			// pagesizeoptions: self.config.grid_state.pageSizeOpts,
			pagesize: 50,    														//by_default 50 rows
            pagesizeoptions: ['25', '50', '100', '250','500','1000','2500'],		//by_default 50 rows
			// server reading (reloads from server on sort, page, filter, etc.)
			virtualmode: true,
			rendergridrows: function(params)
			{
				return params.data;
			},
//			showgroupsheader: false,
			altrows: self.config.preferences.altrows,
			sortable: true, editable: false,
			filterable: true, showfilterrow: true,
			selectionmode: "multiplerowsextended",
			groupable: !self.config.grouping_disabled, groupsexpandedbydefault: true, groups: self.config.grid_state.grouping, groupsrenderer: issuesGroupsRenderer,
                        
                        
                        
                   //                ==========Nested== Start=====================     
					//    30122024 changes by iwi | start
				        initrowdetails: initrowdetails,
						//18022025 changes grid
                        rowdetails: getCockpitname == 'cert' || getCockpitname == 'tra_scheduler' || getCockpitname == 'audit' || getCockpitname == 'event' || getCockpitname == 'doc' || getCockpitname == 'contract' ?  true : false, // IWI Create nested row for contracts in supplier control list page 29062026
						//    30122024 changes by iwi | end
                        rowdetailstemplate: { rowdetails: "<div id='grid' style='margin: 10px;'></div>",   },
                ready: function () {
                        // IWI reset button only visible if filter active start
                        if (need_to_reset.length > 0) {
                            $('#listViewSec [data-op="grid-state-reset"]').show();
                        } else {
                            $('#listViewSec [data-op="grid-state-reset"]').hide();
                        }
                        // IWI reset button only visible if filter active end
//                    $("#issues-grid").jqxGrid('showrowdetails', 0);
//                    $("#issues-grid").jqxGrid('showrowdetails', 1);
                }
                
                
//                ==========Nested========End===============
                
                
                
//			autosavestate: true,
//			autoloadstate: true

			
		})
		/**
		 * Event: Doubleclicking on a data row will load the issue
		 */
		.on('rowdoubleclick', function(event)
		{
			if(!event.args.group) {
				var row_data = $(this).jqxGrid('getdisplayrows')[event.args.visibleindex];

				if(row_data)
					document.location = "/record/process:"+ row_data._track+ (self.config.perspective? "&perspective="+self.config.perspective: '')+ "#panels";
			}
		})
		/**
		 * Event: The data is loaded, expand groups ( apparently they load packed by default after new bind )
		 */
		.on('bindingcomplete', function(event)
		{
			cargohub.application.reset_Session_Timer();
			// Re-Expands groups after sorting change (which seems to default to keeping them closed)
			$('#issues-grid').jqxGrid('expandallgroups');
		})
		// Events that change what data needs to be loaded - Save the current grid state in session (Is reset by loading a different issue type or changing columns)
		/**
		 * Event: Grid's active sort is changed by the user
		 */
		.on('sort', gridServerStateEvent)
		/**
		 * Event: Grid's active filters are changed by the user
		 */
		.on('filter', gridServerStateEvent)
		/**
		 * Event: Grid's page number is changed (Pager effect)
		 */
		.on('pagechanged', gridServerStateEvent)
		/**
		 * Event: Page size changed (Pager dropdown)
		 */
		.on('pagesizechanged', gridServerStateEvent)
		// Events that change how the grid looks - Save the column settings user preference (permanent)
		/**
		 * Event: One of the columns is resized with the mouse
		 */
		.on('columnresized', gridColumnStateEvent)
		/**
		 * Event: One of the columns is moved into a different position
		 */
		.on('columnreordered', gridColumnStateEvent)
		/**
		 * Event: The groups are changed - Reload the data because sorting also changes
		 */
		.on('groupschanged', function(event) {
			if(event.args.type == "Add" || event.args.type == "Insert") {
				// When groups are added, the grid doesn't normally reload. Force it to do a reload, because server-side sorting changes as well
				// ( the groups take precedence before random column sorting, unless the column being sorted by is the grouped column itself )
				$('#issues-grid').jqxGrid('updatebounddata');
			}

			gridServerStateEvent(event); // Preserves groups as well
		});

		// Automatic refresh function
		if(self.config.special_settings.autoref_s > 0) {
			gridInterval = setInterval(function() {
				$("#issues-grid").jqxGrid('updatebounddata');
			}, self.config.special_settings.autoref_s * 1000);
		}
	},

	/**
	 * Interface to configure which columns are displayed and in what order, plus other parameters (like custom width)
	 */
	setup_Grid_Columns = function()
	{
		var
			tpl_data = {
				'ist': {
					'name': self.config.ist.name,
					'plural': self.config.ist.short_plural,
					'icon': self.config.ist.icon
				},
				// column data
				'columns': self.config.columns,
				'sections': [],	// Will be built from the columns data; Used by the dropdown selector
			},
			total_rows = self.config.columns.length,
			selected_row = null, selected_row_idx = -1,
			show_only_visible = false,
			swap = null;

		for(c = 0; c < self.config.columns.length; c++) {
			if(!self.config.columns[c].s)
				continue;
			if(!tpl_data.sections.includes(self.config.columns[c].s))
				tpl_data.sections.push(self.config.columns[c].s);
		}
		tpl_data.sections.sort();

		if(!popups.columns) {
			cargohub.its.init_Modal_Window('column-editor', null, {"cancel": "cancel"});
			cargohub.its.open_Modal_Window('column-editor', 900, 680, "Grid Columns Configuration for: <b>"+self.config.ist.name+"</b>", $('#win-column-settings').html().present(tpl_data), function($window) // Setup the controls inside the window
			{
				var $editor_rows = $('table#editor-columns tr', $window), search_val = '', section_filter = null, text = null,

				// The filter function considers both the text search and the active section
				filter_update_fn = function() {

					$editor_rows.show();
					// Nothing to filter when only one letter in the name search and "All sections" are enabled
					if(!(search_val.length >= 2 || section_filter || show_only_visible))
						return false;

					// Clears selected row when filtering
					selected_row = null, selected_row_idx = -1;

					$editor_rows.filter(function() {

						if(show_only_visible && !$(".cbvisi", this).prop('checked'))
							return true;

						// Is the row the right section?
						if(section_filter && $(this).removeClass('selected').find('td.colsec').text() != section_filter)
							return true;

						// Is excluded by search?
						text =  $(this).removeClass('selected').find('td.colname').text().replace(/\s+/g, ' ').toLowerCase();
						if(!~text.indexOf(search_val))
							return true;
					}).hide();
				},
				scroll_to_center = function()
				{
					var p = $(selected_row).position().top - 222;
					if(p < 0)
						p = 0;
					if(p > $("#editor-columns").height() - 466)
						p = $("#editor-columns").height() - 466;

					$('#tab-body').animate({
						scrollTop: p
					}, 150);
				},
				button_command = null,
				hold_down_interval = null,
				hold_down_repeat_count = 0,
				hold_down_function = function() {
					switch(button_command) {
					case 'moveup': // Move up one position by swapping with the row above. Selected_row_idx changes
						if(selected_row_idx == 0)
							return;


						swap = $(selected_row).prevAll(":visible").first();
						$(selected_row).insertBefore(swap);
						selected_row_idx = $(selected_row).index();

						scroll_to_center();
						break;
					case 'movedown': // Move down one position with the same procedure as move up
						if(selected_row_idx == total_rows-1)
							return;

						swap = $(selected_row).nextAll(":visible").first();
						$(selected_row).insertAfter(swap);
						selected_row_idx = $(selected_row).index();

						scroll_to_center();
						break;
					}
					hold_down_repeat_count++;
				};

				// Show only visible pseudo-button
				$("#only-visible").on('click', function() {
					show_only_visible = !show_only_visible;
					filter_update_fn();
				});

				// Column search
				$('#search-column-name', $window).on('keyup', function() {
					search_val = $.trim($(this).val()).replace(/ +/g, ' ').toLowerCase();
					filter_update_fn();
				});

				$('#search-column-clear', $window).jqxButton({
					theme: cargohub.jqxtheme
				})
				.on('click', function() {
					search_val = '';
					$('#search-column-name', $window).val(search_val);
					filter_update_fn();
				});

				// Section filter
				$('#columns-section-filter', $window).on('change', function() {
					section_filter = $(this).val();
					filter_update_fn();
				});

				// The command buttons
				$('#columns-editor-operations button')
				/**
				 * Clicking on single-trigger commands
				 */
				.on('click', function() {
					switch(button_command) {
					// Moves the row to the top of visible rows
					case 'movetop':
						if(!selected_row)
							return;
						if(selected_row_idx == 0)
							return cargohub.alert("Already on top, need a rocket to go higher !");

						swap = $(selected_row).prevAll(":visible").last();
						$(selected_row).insertBefore(swap);
						selected_row_idx = $(selected_row).index();

						scroll_to_center();
						break;
					// Same as movetop, but at the bottom
					case 'movebottom':
						if(!selected_row)
							return;
						if(selected_row_idx == total_rows-1)
							return cargohub.alert("Already on bottom, find a shovel and start digging !");

						swap = $(selected_row).nextAll(":visible").last();
						$(selected_row).insertAfter(swap);
						selected_row_idx = $(selected_row).index();

						scroll_to_center();
						break;
					// Clicks the "Visibility" checkbox
					case 'visibility':
						if(!selected_row)
							return cargohub.alert("Select a column first");

						$(".cbvisi", selected_row).trigger("click");
						break;
					// All columns on and off
					case 'allon':
						if(!confirm("Are you sure you want to make all columns visible ?\n(This might make your grid VERY big and slow)"))
							return;
						$(".cbvisi").prop('checked', true).parent().css('background-color',"#CCFFCC");
						filter_update_fn();
						break;
					case 'alloff':
						if(!confirm("Are you sure you want to hide all columns ?"))
							return;
						$(".cbvisi").prop('checked', false).parent().css('background-color',"#FFE4CC");
						filter_update_fn();
						break;
					}
				})
				/**
				 * Mousedown performs repetitive operations only certain buttons
				 */
				.on('mousedown', function() {
					button_command = $(this).data('cmd'); // Read always, as it's used even on the non

					// Repetitive operations only happen when a row is selected
					if(!selected_row)
						return;

					hold_down_repeat_count = 0;
					switch(button_command) {
					case 'moveup': // Move up one position by swapping with the row above. Selected_row_idx changes
					case 'movedown': // Move down one position with the same procedure as move up
						hold_down_interval = setInterval(hold_down_function, 200);
						break;
					}
				})
				.on('mouseup mouseleave', function() {
					clearInterval(hold_down_interval);
					hold_down_interval = null;
					// After pressing on one of the buttons, make sure the hold_down_function() is triggered at least once, even if the click was very short
					if(hold_down_repeat_count == 0)
						hold_down_function();

					hold_down_repeat_count = -1;
				});

				// For each row, there are several controls
				$editor_rows.each(function()
				{
					// get the correct dataset for this row
					var col = self.config.columns[$(this).index()], visible = col && col.v == true;

					// the visibility checkbox (col 1)
					$(".cbvisi", this)
					.on('change', function() {
						$(this).parent().css('background-color', $(this).prop('checked')? "#CCFFCC": "#FFE4CC");
					})
					.prop('checked', visible)
					.parent().css('background-color', visible? "#CCFFCC": "#FFE4CC");

					// Clicking on the name selects or changes selection
					$("td.colname, td.colsec", this).on('click', function() {
						var row = $(this).parent();

						if(selected_row_idx>=0) {
							// Check if same row is clicked or another, if different unselect the old one and select new one, otherwise just unselect existing one
							if(row.index() == selected_row_idx) {
								selected_row_idx = -1;
								selected_row = null;
								row.removeClass('selected');
							} else {
								var old_row = $('table#editor-columns tr:eq('+selected_row_idx+')');
								selected_row_idx = row.index();
								selected_row = row;
								old_row.removeClass('selected');
								row.addClass('selected');
							}
						} else {
							selected_row_idx = row.index();
							selected_row = row;
							row.addClass('selected');
						}
					});
				});

				// Clicking OK saves the configuration, while Cancel only closes the window
				$('#modal_column-editor_ok', $window).off('click').on('click', function()
				{
					var columns = [], visible_count = 0;
					// Read the rows, they are in natural order
					$('table#editor-columns tr').each(function() {
						// get the correct dataset for this row
						var col = {
							'fk': $(this).data('fk'),
							'v': $(".cbvisi", this).prop('checked')
						}, w = $(".tbwid", this).val();
						if(w)
							col.w = parseInt(w);
						if(col.v)
							visible_count++;
						columns.push(col);
					});


					if(visible_count > 50) {
						if(!confirm("Warning !\nMore than 50 columns are selected, this may slow down your grid significantly\nAre you sure you want to accept this configuration ?"))
							return;
					}

					$.post("?action=Save_Column_List", {
						'columns': JSON.stringify(columns),
					}, function() {
						// reload the grid, so it updates to the new column list
						document.location.reload();
					})
					.fail(cargohub.its.http_fail);
				});
			});

			popups.columns = true;
		} else
			// This window doesn't change, once it has been initialized for the first time, it can be reused as many times as necessary ( saves the reinitialization time )
			cargohub.its.reopen_Modal_Window('column-editor');
	},

	/**
	 * Configuration for the grid module itself (globally) - Width customization / Autorefresh / Show/Hide TrackID column
	 */
	setup_Special_Settings = function()
	{
		// This window doesn't change, once it has been initialized for the first time, it can be reused as many times as necessary
		if(cargohub.components.modal.windows['special-settings']) {
			cargohub.components.modal.reopen('special-settings');
			return;
		}

		cargohub.components.modal.create('special-settings', {
			btn_roles: {cancel: "cancel"}
		});

		// Little fix for the tooltip on the Autorefresh:
		if(!self.config.special_settings.autoref_s)
			self.config.special_settings.autoref_text = '<b>Turned OFF</b>';
		else
			self.config.special_settings.autoref_text = self.config.special_settings.autoref_s + ' seconds';
                    
                    

		cargohub.components.modal.open('special-settings', {
			width: 700,	height: 370,
			title: "Grid: Special Settings",
			content: $('#win-special_settings').html().present(self.config.special_settings),
			callback: function($window) // Setup the controls inside the window
			{
				$('#ss-width', $window).jqxSlider({
					theme: cargohub.jqxtheme,
					width: 400, height: 25,
					mode: 'fixed',
					min: 950, max: 4070, step: 10,
					value: self.config.special_settings.width,
					showButtons: false,
					ticksFrequency: 100, ticksPosition: 'bottom',
					tooltip: true
				});

				$('#ss-autoref', $window).jqxSlider({
					theme: cargohub.jqxtheme,
					width: 400, height: 25,
					mode: 'fixed',
					min: 0, max: 600, step: 5,
					value: self.config.special_settings.autoref_s,
					showButtons: false,
					ticksFrequency: 30, ticksPosition: 'bottom',
					tooltip: true
				});

				$('#ss-hidetrack', $window).jqxSwitchButton({
					theme: cargohub.jqxtheme,
					height: 24, width: 90,
					checked: self.config.special_settings.hide_trackid
				});

				// Clicking on the recommended width sets the Screen width to that value:
				$('#ss-screensizes b', $window).on('click', function() {
					$('#ss-width', $window).val($(this).text());
				});

				// Add tooltips for relevant elements
				$(".info-tip", $window).each(cargohub.components.ui.add_Tooltip);

				// Setup the action for "OK" button
				$('#modal_special-settings_ok', $window).off('click').on('click', function()
				{
					var request = {
						width: $('#ss-width', $window).val(),
						autoref_s: $('#ss-autoref', $window).val(),
						hide_trackid: $('#ss-hidetrack', $window).val()
					};

					// Save module options in the session and reload. They should apply immediately
					$.post("?action=Special_Settings", request, function(x) {
						if(x.success) {
							document.location.reload();
						} else
							cargohub.its.http_fail(null, "Issue.List::Special_Settings");
					})
					.fail(cargohub.its.http_fail);

				});
			}
		});
	},
        
        
//        IWI assign multi record from grid start
source_Sharing= function(onComplete_callback, autoBind)
		{
			var src_def = {
				url: '?action=get_sharing',
				datatype: "json",
				datafields: [
					{"name": 'id', type: 'int', map: 'org>id'},
					{"name": 'is_master', type: 'bool'},
					{"name": 'name', type: 'string', map: 'org>name'},
					{"name": 'type_id', type: 'string', map: 'org>type_id'},
					// Admin only field
					{"name": 'disabled', type: 'bool'}
				],
				id: "id"
			};

			if(!self.dataSources)
				self.dataSources = {};

			if(flag_sharing_loaded === true) {
				// When already loaded, return the dataAdapter directly
				if(onComplete_callback)
					onComplete_callback(self.dataSources.shareList.records);
				return self.dataSources.shareList.records;
			} else {
				// When not loaded, initialize the dataAdapter and return data when ready
				self.dataSources.shareList = new $.jqx.dataAdapter(src_def, {
					loadComplete: function()
					{
						flag_deps_loaded = true;
						if(onComplete_callback)
							onComplete_callback(self.dataSources.shareList.records);
					},
					autoBind: autoBind
				});
				flag_sharing_loaded = "loading";
				return self.dataSources.shareList;
			}
		},
            active_DepsSource = [],
            /**
             * Loads the department list associated with the currently selected organization (self.config.organization.id) on first use and returns it (or with a callback if loading is incomplete)
             * -- Used by the department access selector and all user search function ( to select from one of the active departments first )
             * @param onComplete_callback If used, this method is run when the loading has completed
             */
            source_Deps = function (autoBind, onComplete_callback, onError_callback)
            {
                var src_def = {
                    url: '/manager/department?action=get_list',
                    data: {
                        org_id: self.config.organization.id
                    },
                    datatype: "json",
                    datafields: [
                        {name: 'id'},
                        {name: 'name'},
                        {name: 'count_members', type: 'number'}
                    ],
                    id: "id"
                };

                if (!self.dataSources)
                    self.dataSources = {};

                if (flag_deps_loaded === true) {
                    // When already loaded, return the dataAdapter records directly
                    if (self.dataSources.depList.records.length > 0) {
                        if (onComplete_callback && self.dataSources.depList)
                            onComplete_callback(self.dataSources.depList.records);
                        return self.dataSources.depList.records;
                    } else {
                        if (onError_callback)
                            onError_callback();
                        return new Array;
                    }
                } else {
                    // When not loaded, initialize the dataAdapter and return data when ready
                    self.dataSources.depList = new $.jqx.dataAdapter(src_def, {
                        loadComplete: function ()
                        {
                            flag_deps_loaded = true;
                            if (onComplete_callback && self.dataSources.depList)
                                onComplete_callback(self.dataSources.depList.records);
                        },
                        loadError: function (jqXHR, status, error)
                        {
                            flag_deps_loaded = true;
                            if (onError_callback)
                                onError_callback(); // On error, send the callback an empty array; TODO: Save jqXHR, status, error from the failure, so it can be used on subsequent calls of source_Deps
                        },
                        autoBind: autoBind
                    });
                    flag_deps_loaded = "loading";
                    return self.dataSources.depList;
                }
            },
            build_Active_DepsSource = function (records)
            {
                self.active_DepsSource.length = 0;

                // Begin by a "fake" department entry named "Entire organization"
                // NOT ok... causes problems for the other windows containing department list
//			self.active_DepsSource.push({
//				id: 0, uid: 0,
//				count_members: self.config.organization.count_users,
//				name: "Entire org.: "+self.config.organization.name
//			});

                if (self.config.dep_ids) {
                    for (c = 0; c < records.length; c++)
                        if ($.inArray(records[c].id.toString(), self.config.dep_ids) > -1)
                            self.active_DepsSource.push(records[c]);
                }
            },
            modal_Assign_User = function ()
            {
                console.log(self);
                cargohub.components.modal.create('user-search', {
                    btn_roles: {cancel: "cancel"}
                });

                cargohub.components.modal.open('user-search', {
                    width: 860, height: 550,
                    title: "Assign user to handle record",
                    content: $('#modal-user_search').html().present({
                        _itc: cargohub.input_theme_classes
                    }),
                    callback: function ($window) // Setup the controls inside the window
                    {
                        // The User Search ( Combobox )
                        var
                                members_Source = new $.jqx.dataAdapter({
                                    datatype: "json",
                                    datafields: [
                                        {name: 'id', type: 'number'},
                                        {name: 'name'}
                                    ],
                                    id: "id"
                                }),
                                // User search combobox
                                $user_search = $("#user-search", $window),
                                enable_User_Search = function (url, data, user_search_Placeholder) {
                                    members_Source._source.url = url;
                                    members_Source._options.data = data;
                                    $user_search.jqxComboBox('clearSelection');
                                    $user_search.jqxComboBox({
                                        source: members_Source,
                                        placeHolder: user_search_Placeholder || "Type to search or click the arrow to show all",
                                        disabled: false
                                    });
                                };

                        // The Active Departments selector
                        $("#user-search-dep-list", $window).jqxDropDownList({
                            theme: cargohub.jqxtheme,
                            displayMember: "name", valueMember: "id",
                            placeHolder: "(Loading departments...)",
                            width: 450,
                            dropDownWidth: 450, autoDropDownHeight: true, animationType: "fade",
                            // Member count for department
                            renderer: function (index, label, value) {
                                var dep = self.active_DepsSource[index];
                                var count = dep ? dep.count_members : 0;
                                return '<span class="gray right">(' + count + ' members)</span>' + label;
                            },

                            disabled: true
                        })
                                /**
                                 * Event: The department selector is changed - Reload the page with organization selected which would view the grid as from the selected organization.
                                 */
                                .on("select", function (event) {
                                    if (event.args.item.value)
                                        // Read from a department membership list
                                        enable_User_Search('/manager/department?action=get_members', {
                                            id: event.args.item.value
                                        });
                                    else
                                        // When no ID present, read the organization user list instead
                                        enable_User_Search('/manager/organization?action=get_users', {
                                            id: self.config.perspective,
                                            quickmode: true
                                        });

                                    // Clear the selected user text
                                    $("#quick-user-information", $window).html('<div class="autocenter gray"><div>Please select an user to see more details</div></div>');
                                });

                        // Start loading departments (or access them if already loaded), and only choose the enabled ones to build the source array for the dropdown.
                        if (self.active_DepsSource.length > 0) {
                            $("#user-search-dep-list", $window).jqxDropDownList({
                                source: self.active_DepsSource,
                                placeHolder: "Select department:",
                                disabled: false
                            });
                            if (self.active_DepsSource.length == 1)
                                $("#user-search-dep-list", $window).jqxDropDownList('selectIndex', 0);
                        } else
                            self.source_Deps(true, function (records) {
                                self.build_Active_DepsSource(records);

                                if (self.active_DepsSource.length > 0) {
                                    $("#user-search-dep-list", $window).jqxDropDownList({
                                        source: self.active_DepsSource,
                                        placeHolder: "Select department:",
                                        disabled: false
                                    });
                                    if (self.active_DepsSource.length == 1)
                                        $("#user-search-dep-list", $window).jqxDropDownList('selectIndex', 0);
                                } else {
                                    $("#user-search-dep-list", $window).jqxDropDownList({
                                        placeHolder: "(Currently no departments have access. Check Access control)",
                                        disabled: true
                                    });
                                    enable_User_Search('/manager/organization?action=get_users', {
                                        id: self.config.perspective,
                                        quickmode: true
                                    });
                                }
                            }, function () { // Error
                                self.active_DepsSource = [];
                                $("#user-search-dep-list", $window).jqxDropDownList({
                                    placeHolder: "(The organization has no departments)",
                                    disabled: true
                                });
                                enable_User_Search('/manager/organization?action=get_users', {
                                    id: self.config.perspective,
                                    quickmode: true
                                });
                            });

                        $user_search.jqxComboBox({
                            theme: cargohub.jqxtheme,
                            searchMode: "containsignorecase",
                            autoComplete: true, minLength: 0,
                            displayMember: "name", valueMember: "id",
                            width: 450, height: 22,
                            dropDownWidth: 450,
                            animationType: "none",
                            disabled: true
                        })
                                // Event: The user list is loaded (or has an error)
                                .on('bindingComplete', function (event) {
                                    var recs = members_Source.records || [];

                                    if (recs.length === 0) {
                                        $(this).jqxDropDownList({
                                            placeHolder: "(The organization has no users! Assignment not possible)",
                                            width: 450,
                                            disabled: true
                                        });
                                        $("#quick-user-information > div > div")
                                                .css("color", "red")
                                                .html("(The organization has no users! Assignment not possible)");
                                    } else {
                                        $(this).jqxComboBox({
                                            dropDownHeight: recs.length > 8 ? 210 : null,
                                            autoDropDownHeight: recs.length <= 8,
                                            disabled: false
                                        });
                                        $user_search.jqxComboBox('open');
                                    }
                                })

                                // Event: An user is selected - Load quickinfo in the box below
                                .on('select', function (event) { // value on select event - Which puts the group id in the hidden input
                                    if (event.args === undefined)
                                        return false;

                                    if (event.args.item != null && event.args.item.originalItem.id > 0) {
                                        // Load user contact information into the div below:
                                        $.get("/manager/user?action=get_details&id=" + event.args.item.originalItem.id, function (contents, textStatus, jqXHR)
                                        {
                                            $("#quick-user-information", $window).html(contents);
                                        })
                                                .fail(cargohub.its.http_fail);

                                        $('#modal_user-search_ok', $window).jqxButton({disabled: false});
                                    } else {
                                        $user_search.jqxComboBox('clearSelection');
                                        $("#quick-user-information", $window).html('<div class="autocenter gray"><div>Please select an user to see more details</div></div>');
                                        return false;
                                    }
                                });

                        // Setup the action for "OK" button (start with it disabled, enabled when user has been selected)
                        $('#modal_user-search_ok', $window).jqxButton({disabled: true}).off('click').on('click', function ()
                        {
                            $.post("?action=assign_user", {
                                id: $user_search.val(),
                                claim_type_name: $("#claim_type_name").html()
                            }, function (x) {
                                // Update the local information...
                                $("#assigned-user").html('Assigned to:<br /><b class="info-tip" data-tooltip="' + x.user_email + '">' + x.user_name + ' </b><button class="record_details_unassign_user_btn" data-op="unassign-user"  data-uid="' + $user_search.val() + '"><img alt="Off" src="/img/icons16/cancel.png"></button>');//10032025 add remove assign used by sourav start
                                $("#assigned-user .info-tip").each(cargohub.components.ui.add_Tooltip);

                                // ... and close the assign window
                                $window.jqxWindow('close');
                                console.log(x.response);
                                cargohub.alert(x.response, "Success !", "thumb_up.png");

                                if (cargohub.record_contacts)
                                    cargohub.record_contacts.reload();
                                if (cargohub.record_sharing)
                                    cargohub.record_sharing.reload();
                                //
                                createTaskWithSpecificTemplate("assignUser");

                            })
                                    .fail(cargohub.its.http_fail);
                        });
                    }
                }, self);
            },
//        IWI assign multi record from grid end

	/**
	 * Window used to select an organization - When selected and OK pressed, the callback is triggered
	 * (several operations might use the same window/view)
	 * -- Optional - Change window title from the default
	 */
        
	setup_Org_Search = function(ok_callback, title)
	{
		cargohub.components.modal.create('org-search', {
			btn_roles: {cancel: "cancel"}
		});

		cargohub.components.modal.open('org-search', {
			width: 370, height: 580,
			title: title? title: "Select organization",
			content: $('#win-org_select').html(),
			callback: function($window) {
				// The listbox/tree is initialized based on the selected rt_id
				$("#org_select-listbox", $window).jqxListBox({
					theme: cargohub.jqxtheme,
					source: dataAdapter_Admin_Orgs,
					displayMember: "name", valueMember: "id",
					width: 358, height: 462,
					filterable: true,
					searchMode: 'containsignorecase',
					renderer: function (index, label, value) {
						if(index == -1)
							if(label == '.')
								return "";
							else
								return "<h4>&middot;&middot;&middot; "+label+ " &middot;&middot;&middot;</h4>";
						if(index == 0)
							return "<div class='thick center green'>"+label+"</div>";
						return label;
					},
				})
				.on('dblclick', function() {
					$('#modal_org-search_ok', $window).trigger('click');
				});

				// Setup the action for "OK" button
				$('#modal_org-search_ok', $window).off('click').on('click', function()
				{
					var selected_org = $("#org_select-listbox", $window).jqxListBox('getSelectedItem');
					if(!selected_org)
						cargohub.alert("Please select an organization first");

					if(ok_callback) {
						if(ok_callback(selected_org))
							$window.jqxWindow('close');
					} else // When no callback, just close the window (nothing is done actually)
						$window.jqxWindow('close');
				});

			}
		}, self);
	},

	setup_XField_Selector = function(ok_callback, title)
	{
		cargohub.components.modal.create('xf-select', {
			btn_roles: {cancel: "cancel"}
		});

		cargohub.components.modal.open('xf-select', {
			width: 370, height: 580,
			title: title? title: "Select fields",
			content: $('#win-xf_select').html(),
			callback: function($window) {
				// The listbox/tree is initialized based on the selected rt_id
				$("#xf_select-listbox", $window).jqxListBox({
					theme: cargohub.jqxtheme,
					source: dataAdapter_Record_XF,
					displayMember: "name", valueMember: "id",
					width: 358, height: 462,
					filterable: true,
					checkboxes: true,
					searchMode: 'containsignorecase',
					renderer: function (index, label, value) {
						if(index == -1)
							return "<h4>&middot;&middot;&middot; "+label+ " &middot;&middot;&middot;</h4>";
						else
							return label;
					},
				})
				// Begin by "checking all", allowing the user to uncheck those that he wants to skip
				.on('bindingComplete', function() {
					$(this).jqxListBox('checkAll');
				});

				// Setup the action for "OK" button
				$('#modal_xf-select_ok', $window).off('click').on('click', function()
				{
					// Build list of ID's for checked fields (this doesn't work properly with .val() because of groups
					var checked_items = $("#xf_select-listbox", $window).jqxListBox('getCheckedItems'), checked_ids = [];

					for(c = 0; c< checked_items.length; c++)
						if(checked_items[c].isGroup == false && checked_items[c].checked == true)
							checked_ids.push(checked_items[c].value);

					if(checked_ids.length == 0)
						return cargohub.alert("At least one field must be checked");

					if(ok_callback) {
						if(ok_callback(checked_ids))
							$window.jqxWindow('close');
					} else // When no callback, just close the window (nothing is done actually)
						$window.jqxWindow('close');
				});

			}
		}, self);

	},

	/**
	 * Grid "Views" operations
	 *
	 * Note: self.config.view only contains views that the user has access to !
	 */
	ops_Grid_Views = function(command)
	{
		switch(command.action) {
		// Load a specific view as the active one ( copy from variant to V1 ) - Both columns and state will be changed
		case 'load':
			if(command.confirm) {
				if(!confirm("Are you sure you want to load this view ?\nIt will replace your current one !"))
					return;
			}

			// The views apply to the current active issue type, so only the variant is necessary
			$.post("?action=Load_Grid_View", {
				"id": command.id
			}, function(x) {
				// The view was changed; Reload so the new grid configuration applies
				document.location.reload();
			})
			.fail(cargohub.its.http_fail);
			break;

		// Saves existing grid columns and grid state into a database record called a "View"
		case 'save':
			// This window doesn't change, once it has been initialized for the first time, it can be reused as many times as necessary
			if(cargohub.components.modal.windows['view-save']) {
				$("#grid-view-name").val("");
				cargohub.components.modal.reopen('view-save');
				break;
			}

			// Nope, first time - Create it
			cargohub.components.modal.create('view-save', {
				btn_roles: {cancel: "cancel"}
			});

			cargohub.components.modal.open('view-save', {
				width: 750,	height: 220,
				title: "Grid: Store current view ?",
				content: $('#win-save_view').html(),
				callback: function($window) // Setup the controls inside the window
				{
					var
					autoselect_visibility_fn = function(option) {
						if(cargohub.application.user_Test_Privileges('grid_manage_views')) {
							switch(option) {
							case 'user':
								$("#radio2-user", $window).jqxRadioButton('check');
								break;
							case 'dep':
								$("#radio2-dep", $window).jqxRadioButton('check');
								break;
							case 'org':
								$("#radio2-org", $window).jqxRadioButton('check');
								break;
							}
						}
					};

					// The two radiobuttrons
					$("#radio-create-view", $window).jqxRadioButton({
						theme: cargohub.jqxtheme,
						groupName: "saveview",
						checked: !self.config.views || self.config.views.length == 0 // When there are no view, this one is autochecked and focus is placed into the name box
					})
					.on('checked', function() {
						// When checking the "Create" one, disable the selector
						$("#grid-view-save-select", $window).jqxDropDownList({disabled: true});
						$("#grid-view-name", $window).attr('placeholder', "(Name the new view)");

						// On create new, the "Myself" option is auto-enabled
						if(cargohub.application.user_Test_Privileges('grid_manage_views')) {
							$("#radio2-user", $window).jqxRadioButton('check');
						}
					});

					$("#radio-existing-view", $window).jqxRadioButton({
						theme: cargohub.jqxtheme,
						groupName: "saveview",
						disabled: !self.config.views || self.config.views.length == 0 // When there are no views, the first radio is disabled
					})
					.on('checked', function() {
						var existing_view_idx = $("#grid-view-save-select", $window).jqxDropDownList('getSelectedIndex');

						$("#grid-view-save-select", $window).jqxDropDownList({disabled: false});
						$("#grid-view-name", $window).attr('placeholder', "(To RENAME, type the new name here)");

						// When selecting "Existing view", if the list of views dropdown has a value, auto-select "store the view for" depending on that value
						if(existing_view_idx && self.config.views[existing_view_idx]) {
							autoselect_visibility_fn(self.config.views[existing_view_idx].visibility);
						}
					});

					$("#grid-view-save-select", $window).jqxDropDownList({
						source: self.config.views,
						theme: cargohub.jqxtheme,
						displayMember: "name", valueMember: "id",
						renderer: function(index, label, value) {
							return "<span class='gray right'>("+ self.config.views[index].visi_text+ ")</span>" + label;
						},
						width: 520, autoDropDownHeight: true, //, dropDownWidth: 300
						disabled: !self.config.views || self.config.views.length == 0
					})
					/**
					 * When the view is selected to be changed, the "Store the view for" option changes depending on which one is selected
					 * (But by choosing another value, the view's visibility can be altered)
					 */
					.on('change', function(event) {
						autoselect_visibility_fn(event.args.item.originalItem.visibility);

						// When selecting a view, the "Existing view" is auto-checked as well
						$("#radio-existing-view", $window).jqxRadioButton('check');
					});

					if(!self.config.views || self.config.views.length == 0)
						setTimeout(function() {
							$("#grid-view-name", $window).focus();
						}, 100); // Apparently it needs a small delay to set focus properly...

					// Special settings switchbutton
					$("#grid-view-ss", $window).jqxSwitchButton({
						theme: cargohub.jqxtheme,
						height: 25, width: 90,
						checked: true
					});

					if(cargohub.application.user_Test_Privileges('grid_manage_views')) {
						$("#grid-view-save-for", $window).show();

						// The radiobuttons for visibility
						$("#radio2-user", $window).jqxRadioButton({
							theme: cargohub.jqxtheme,
							groupName: "savefor"
						});
						$("#radio2-dep", $window).jqxRadioButton({
							theme: cargohub.jqxtheme,
							groupName: "savefor"
						});
						$("#radio2-org", $window).jqxRadioButton({
							theme: cargohub.jqxtheme,
							groupName: "savefor"
						});
					}

					// Auto-initialize active view in save options (and storage option)
					if(self.config.active_view)
						$("#grid-view-save-select", $window).val(self.config.active_view);

					// Clicking OK saves the configuration, while Cancel only closes the window
					$('#modal_view-save_ok', $window).off('click').on('click', function()
					{
						var view_data = {
							'state': JSON.stringify(self.config.grid_state), // Local "State" copy is saved
							'sss': $("#grid-view-ss", $window).jqxSwitchButton('checked')
						}, view_selected_id = $("#grid-view-save-select", $window).val(), view_new_name = $("#grid-view-name", $window).val();

						// Visibility radios are enabled for certain users
						if(cargohub.application.user_Test_Privileges('grid_manage_views')) {
							if($("#radio2-user", $window).val())
								view_data.visibility = 'user';
							else if($("#radio2-dep", $window).val())
								view_data.visibility = 'dep';
							else if($("#radio2-org", $window).val())
								view_data.visibility = 'org';
						}

						// Verify what's the state of save/update radiobuttons
						if($("#radio-existing-view", $window).jqxRadioButton('checked')) {
							// Replace existing view (update)
							if(!view_selected_id)
								return cargohub.alert("Please select which view to update", "Not selected");
							view_data.id = view_selected_id;

							if(view_new_name.length >= 3)
								// Rename the view?
								view_data.name = view_new_name;
							else
								view_data.name = self.config.views[$("#grid-view-save-select", $window).jqxDropDownList('getSelectedIndex')].name;

						} else if($("#radio-create-view", $window).jqxRadioButton('checked')) {
							// Check if the new view has a name, and warn if empty or too short
							if(view_new_name.length < 3)
								return cargohub.alert("Please give your new view a name (min 3 characters)", "Invalid input");
							view_data.name = view_new_name;
						} else
							return cargohub.alert("Select if you're creating or updating a view", "No option chosen");

						$.post("?action=Store_Grid_View", view_data, function(x) {
							// Reload so the menu and title is updated
							document.location.reload();
						})
						.fail(cargohub.its.http_fail);
					});
				}
			});
			break;

		// Open the window used to select which view will be deleted - This doesn't affect current view, even if the one being deleted has just been used
		case 'delete':
			// This window doesn't change, once it has been initialized for the first time, it can be reused as many times as necessary
			if(cargohub.components.modal.windows['view-delete']) {
				cargohub.components.modal.reopen('view-delete');
				break;
			}

			// Nope, first time - Create it
			cargohub.components.modal.create('view-delete', {
				btn_roles: {cancel: "cancel"}
			});

			cargohub.components.modal.open('view-delete', {
				width: 536,	height: 116,
				title: "Grid: Delete View",
				content: $('#win-delete_view').html(),
				callback: function($window) // Setup the controls inside the window
				{
					// setup the jqxDropDownList used to select the view and the "Confirm" switchbutton
					$("#grid-view-delete-select", $window).jqxDropDownList({
						source: self.config.views,
						theme: cargohub.jqxtheme,
						displayMember: "name", valueMember: "id",
						renderer: function(index, label, value) {
							return "<span class='gray right'>("+ self.config.views[index].visi_text+ ")</span>" + label;
						},
						width: 500, autoDropDownHeight: true //, dropDownWidth: 300
					});

					// Delete also autoselects active view (to delete existing view basically)
					if(self.config.active_view)
						$("#grid-view-delete-select", $window).val(self.config.active_view);

					// Clicking OK saves the configuration, while Cancel only closes the window
					$('#modal_view-delete_ok', $window).off('click').on('click', function()
					{
						var view_id = $("#grid-view-delete-select").val();
						if(!view_id)
							return cargohub.alert("Please select a view first...");

						if(!confirm("Are you sure you want to remove the selected view ?\nThe operation is not reversible and your settings will be lost"))
							return;

						$.post("?action=Delete_Grid_View", {
							'id': view_id
						}, function(x) {
							document.location.reload();
						})
						.fail(cargohub.its.http_fail);
					});
				}
			});
			break;

		case 'reset':
			if(command.confirm) {
				if(!confirm("Are you sure you want to reset your grid's view to the default ?"))
					return;
			}

			$.post("?action=Reset_Grid", function(x) {
				if(x.success) {
					// reload the grid so it updates to the default
					document.location.reload();
				} else
					cargohub.alert(x.reason, "Message from server", "events.png");
			});
		}
	},

	/**
	 * Does various actions on selected row(s)
	 */
	action_onSelected = function(command)
	{
		var row_indexes = $('#issues-grid').jqxGrid('getselectedrowindexes'), count_selected = 0,
			rows = $('#issues-grid').jqxGrid('getboundrows'),
			data_rows = [], row_ids = [],
                        lm_selected_row_ids = [], //IWI country & location bulk edit for License monitor
		/**
		 * Since all the Bulk Operations are similar, do it in a common method
		 */
		Bulk_Operation = function(op, confirm_message, params) // Param = extra parameter for when the operation needs it
		{
			if(confirm_message)
				if(!confirm(confirm_message+ count_selected+ " records ?"))
					return false;

			if(!params)
				params = {};

			params = $.extend(params, {
				op: op,
				ids: row_ids.join(',')
			});

			$.post("?action=Bulk_Operation", params, function(x) {
				if(x.success) {
					// Data has been changed, reload the grid to display the changes
					$('#issues-grid').jqxGrid('clearselection');
					$('#issues-grid').jqxGrid('updatebounddata');
					cargohub.alert(x.response, "Success !", "thumb_up.png");
				} else
					cargohub.its.http_fail(null, "Record.Grid::bulk_operation");
			})
			.fail(cargohub.its.http_fail);
			return true;
		};

		// Process the rows, skip "group" rows (which are selected as well for no reason due to stupidity of the people who made this crap)
		count_selected = row_indexes.length;
		for(c=0; c<row_indexes.length; c++) {
			if(row_indexes[c] == -1) {
				count_selected--;
				continue; // Skip group rows
			}
			data_rows.push(rows[row_indexes[c]]);
			row_ids.push(rows[row_indexes[c]].uid);
			lm_selected_row_ids.push(rows[row_indexes[c]].uid); //IWI country & location bulk edit for License monitor
		}

		if(count_selected == 0)
			return cargohub.alert("Please select at least one row !");

		switch(command.action) {
			// shows the Record Overview and Operations module /record/process
			case 'process':
				if(count_selected > 3)
					return cargohub.alert("This function only supports opening up to 3 rows at once");

				for(c=0; c< count_selected; c++) {
					var win = window.open("/record/process:"+ data_rows[c]._track+ (self.config.perspective? "&perspective="+self.config.perspective: '')+ "#panels", '_blank');
//					win.focus();
				}
//				document.location = "/record/process:"+ data_rows[0]._track+ (self.config.perspective? "&perspective="+self.config.perspective: '')+ "#panels";
				break;

			// show the Web View for this record (accessible from outside the application when Track and Access Key are known)
			case 'webview':
				if(count_selected > 3)
					return cargohub.alert("This function only supports opening up to 3 rows at once");

				for(c=0; c< count_selected; c++) {
					var win = window.open("/issue?action=view&track="+ data_rows[c]._track+ "&key="+ data_rows[c]._access_key, '_blank');
//					win.focus();
				}
//				document.location = "/issue?action=view&track="+ data_rows[0]._track+ "&key="+ data_rows[0]._access_key;
				break;
				
                                
//                                IWI country & location bulk edit for License monitor start

                        case 'bulkdataupdate':
                            cargohub.components.modal.open('LocBulkUpdateModal', {
                                width: 300, height: 190,
                                title: "Bulk Update Country & Location ",
                                content: $('#bulkupdate').html(),
                                callback: function($window) // Setup the controls inside the window
                                {
                                    // Setup the action for "OK" button
				$('#modal_LocBulkUpdateModal_ok', $window).off('click').on('click', function()
				{
                                    
                                    var request = {
						lm_bulk_country: $('#lm_bulk_country', $window).val(),
						lm_bulk_location: $('#lm_bulk_location', $window).val(),
						lm_selected_data_rows: lm_selected_row_ids,
					};
					$.post("?action=lmBulkUpdate", request, function(x) {
						if(x.success) {
							document.location.reload();
						} else{
							cargohub.its.http_fail(null);
                                                    }
                                                    
					})
					.fail(cargohub.its.http_fail);

				});

                                }

                            });

                            $("#LocBulkUpdateModal").css("display", "block");
                            $("#LocBulkUpdateModal").addClass("in");



    //				document.location = "/issue?action=view&track="+ data_rows[0]._track+ "&key="+ data_rows[0]._access_key;
                            break;

//				IWI country & location bulk edit for License monitor end

			// Sets the status of the selected issues
			case 'status':
				if(!confirm("Are you sure you want change status of "+ count_selected +" record(s) to "+ command.detail +" ?"))
					return;
				$.post("?action=set_status_bulk", {
					status: command.status,
					ids: row_ids.join(',')
				}, function(x) {
					// reload the grid, so it updates with the new data
					$('#issues-grid').jqxGrid('clearselection');
					$('#issues-grid').jqxGrid('updatebounddata');
					cargohub.alert(x.response, "Success !", "thumb_up.png");
				})
				.fail(cargohub.its.http_fail);
				break;

			// Sets the priority of the selected issues
			case 'priority':
				if(!confirm("Are you sure you want change priority of "+ count_selected +" record(s) to "+ command.detail +" ?"))
					return;
				$.post("?action=set_priority_bulk", {
					priority: command.priority,
					ids: row_ids.join(',')
				}, function(x) {
					// reload the grid, so it updates with the new data
					$('#issues-grid').jqxGrid('clearselection');
					$('#issues-grid').jqxGrid('updatebounddata');
					cargohub.alert(x.response, "Success !", "thumb_up.png");
				})
				.fail(cargohub.its.http_fail);
				break;

			// Add department ID to the selected records (issue_orgdata)
			case 'dep_grant':
			case 'dep_revoke':
				if(!confirm("Are you sure you "+(command.action == 'dep_grant'? "grant": "revoke")+" department:\n\n "+ command.detail +"\n\n access to "+ count_selected +" record(s) ?"))
					return;
				$.post("?action=dep_access_bulk", {
					op: command.action.substr(4),
					dep_id: command.dep_id,
					ids: row_ids.join(',')
				}, function(x) {
					// reload the grid, so it updates with the new data
					$('#issues-grid').jqxGrid('clearselection');
					$('#issues-grid').jqxGrid('updatebounddata');
					cargohub.alert(x.response, "Success !", "thumb_up.png");
				})
				.fail(cargohub.its.http_fail);
				break;

			case 'hide':
				Bulk_Operation('close', "Are you sure you want close (hide) ");
				break;

			case 'unhide':
				Bulk_Operation('reactivate', "Are you sure you want reactivate (unhide) ");
				break;

			// Simulates deletion of the record by marking them invisible to the organization
			case 'delete':
				Bulk_Operation('delete', "Are you REALLY sure you want to delete ");
				break;

			case 'exmark-clr':
				Bulk_Operation('exmark-clr', "Are you sure you want to clear Export Marker from ");
				break;

			// Admin only operations on selected rows
			case 'su_copy2org':
				setup_XField_Selector(function(xf_ids) {
					// Fields chosen, pick where they are copied
					setup_Org_Search(function(selected_org) {
						return Bulk_Operation('copy2org', "Copy to organization "+selected_org.label+": ", {"org_id": selected_org.value, "xf_ids": JSON.stringify(xf_ids)});
					});
					return true;
				}, "Select fields for copy operation");
				break;

			case 'su_setmaster':
				setup_Org_Search(function(selected_org) {
					return Bulk_Operation('setmaster', "Set organization "+selected_org.label+" as master for ", {"org_id": selected_org.value});
				});
				break;
                        
                        //  IWI assign multi record from grid start
			case 'su_assign_user':
				console.log(count_selected);
                                console.log(row_ids.join(','));
                                modal_Assign_User();
                        
//                        IWI assign multi record from grid end
                                
				break;        
                                
		}
	}
	;
	    //   init_Toolbar = function ()
        //     {
		// 		 //security audit start
		// 		 $("#tab_security_activity div.show_extra_manage_sec button").jqxButton({
        //             theme: cargohub.jqxtheme
        //         }) .on('click', function () {

        //             console.log(" 621 test  ",$(this).data('op'));
        //         });
        //         //security audit end
        //     },
			 // security audit start 
            // initAuditActivityEditor = function (config)
            // { 
                
            //     if(config.mode == 'create_audit_activity'){
            //         document.getElementById("auditActivityForm").reset();
            //         $("#audit_activity_id").val(0);
            //         $("#isCreateTaskId").val(0);
            //         $('input[name="audit_status"][value="active"]').prop('checked', true);//23102024 Audit control 
            //         open_row_id = null;
            //     }


            //     $audit_activity_editor = $("#tab_security_activity #document-editor");

            //     // config.expander.theme = cargohub.jqxtheme;
            //     $audit_activity_editor.jqxExpander(config.expander);
                
            //     if (!config.expander)
            //     config.expander = {
            //         disabled: false,
            //         expanded: false
            //     };
            //     switch (config.mode) {
            //         case 'create_audit_activity':

            //         break;
            //     }
            //     editor_open = config.mode;
            //     $audit_activity_editor.show().jqxExpander('enable');

            //     $audit_activity_editor.jqxExpander('expand');
               
            //     // Add tooltips for relevant elements
            //     $(".info-tip", $audit_activity_editor).each(cargohub.components.ui.add_Tooltip);

            //     // Show the Save/Close controls
            //     $('#security-activity-open-ops button').jqxButton({disabled: false});
            //     $('#security-activity-open-ops').fadeIn();
            // },
            // initAuditActivityOpen = function (row)
            // {
            //     console.log(" 704 row ",row);
            //     // Fadeout Create command when Editor or Viewer are active
            //     $('#tab_security_activity #security-activity-create-op').fadeOut();

            //     if (row)
            //         selected_row = row;

            //     $('#audit_reference_no').val(selected_row.reference_no);    
            //     $('#audit_description').val(selected_row.dsc);    
            //     $('#audit_improvement').val(selected_row.improvement);   
            //     $('#audit_activity_id').val(selected_row.uid);   
                
            //     if (selected_row.risk_val == 'MI') {
            //         $('input[name="audit_risk"][value="MI"]').prop('checked', true);
            //     } else if (selected_row.risk_val == "MA") {
            //         $('input[name="audit_risk"][value="MA"]').prop('checked', true);
            //     } else {
            //         $('input[name="audit_risk"][value="CR"]').prop('checked', true);
            //     }

            //     if (selected_row.status == 'active') {
            //         $('input[name="audit_status"][value="active"]').prop('checked', true);
            //     } else if (selected_row.status == "closed") {
            //         $('input[name="audit_status"][value="closed"]').prop('checked', true);
            //     }
            //     $('#isCreateTask').prop('disabled', false);
            //     if(selected_row.isCreateTask == 'true') {
            //         $('input[name="isCreateTask"][value="1"]').prop('checked', true);
            //         $('#isCreateTask').prop('disabled', true); 
            //     }

            //     $('#isCreateTaskId').val(selected_row.isCreateTaskId);   
                

            //     $('#audit_title').val(selected_row.title); 
            //     $('#capa_type').val(selected_row.type); 
            //     $('#audit_responsible_person').val(selected_row.person); 
            //     $('#capa_result').val(selected_row.result); 
            //     $('#audit_cause_comments').val(selected_row.comments); 
            //     $('#audit_deadline_date').val(selected_row.deadline_date); 
            //     $('#audit_closing_date').val(selected_row.closing_date); 

            //     $('#tab_security_activity #security-activity-open-ops, #tab_security_activity .docs-write-ops').fadeIn();
            //     initAuditActivityViewerClose(true);
            //     initAuditActivityEditor({
            //         title: "Edit document: " + selected_row.ID,
            //         icon: "pencil.png",
            //         mode: "edit"
            //     });
            // },
            // initAuditActivityViewerClose = function (transition)
            // {
            //     if (!$audit_activity_viewer) // Not yet initialized
            //         return;

            //     if ($audit_activity_viewer.jqxExpander('expanded')) {
            //         $audit_activity_viewer.on('collapsed', function () {
            //             // When finishing collapse... hide the editor
            //             $audit_activity_viewer.hide();
            //             // ... and disable the event
            //             $audit_activity_viewer.off('collapsed');
            //         });
            //         $audit_activity_viewer.jqxExpander('collapse');
            //     } else {
            //         // If already collapsed, just hide it
            //         $audit_activity_viewer.hide();
            //     }

            //     // Also fadeout the Close buttons
            //     $('#tab_security_activity #security-activity-open-ops button').jqxButton({disabled: true});
            //     $('#tab_security_activity #security-activity-open-ops').fadeOut();

            //     if (!transition) {
            //         if (selected_row)
            //             $('#tab_security_activity #security-activity-row-ops').fadeIn();
            //         // Show the "Create" button in case it was hidden by a previous operation
            //         $('#tab_security_activity #create-activity-create-op').fadeIn();
            //     }

            //     // viewer_open = false;

            //     return true;
            // },
            // initAuditActivitySelectRow = function (row){
            //     var edit_access = docs_manager_priv ||
            //     (row.edit_access == 'shared'
            //     || (row.edit_access == 'org' && (row.visibility == 'dep' || row.visibility == 'org'))
            //     || (row.edit_access == 'dep' && row.dep_id == user_session.cdep_id)
            //     || (row.edit_access == 'user' && row.editor_id == user_session.id)
            //     );


            //     // If no row was previously selected, show the available commands
            //     if (!selected_row) {
            //         donrey.cancel_Delay('hide-docs-row-ops');
            //         $('#tab_security_activity #security-activity-row-ops button').jqxButton({disabled: false});
            //         $('#tab_security_activity #security-activity-row-ops').fadeIn();
            //     }
            //     selected_row = row;

            //     // Show or hide the "Delete" button
            //     if (edit_access)
            //         $('#tab_security_activity .docs-write-ops').fadeIn();
            //     else
            //         $('#tab_security_activity .docs-write-ops').fadeOut();

            //     // Show or hide the Web-View section (if the document has web-view enabled or not)
            //     if (row.wv_key)
            //         $('#tab_security_activity #trainee-open-webview').show();
            //     else
            //     $('#tab_security_activity #trainee-open-webview').hide();
            // },
            // initAuditActivityDelete = function ()
            // {
            //     // console.log("AuditActivityDelete 759 : ",selected_row);
            //     $.post("/record/process?action=Delete_audit_activity_management", {
            //         id: selected_row.uid,
            //     }, function (x) {
            //         cargohub.components.spinner.On("Success!", x.response, 2500, function () {
            //             cargohub.components.spinner.Off();
            //         });
            //         location.reload(); 

            //     }).fail(cargohub.its.http_fail);
            // },
            // initAuditActivitySave = function ()
            // {

            //     var audit_risk = $('input[name="audit_risk"]:checked').val();
            //     if (audit_risk == '' || audit_risk == undefined) {
            //         return cargohub.alert("Please select the risk type !");
                    
            //     }

            //     var audit_status = $('input[name="audit_status"]:checked').val();
            //     if (audit_status == '' || audit_status == undefined) {
            //         return cargohub.alert("Please select the status type !");
                    
            //     }

            //     /*
            //         var submit_data = {
            //             'link_id': master.config.id,
            //             'track_id': master.config.track,
            //             'org_id': master.config.master_org_id,
            //             'audit_reference_no': $('#audit_reference_no').val(),
            //             'audit_description': $('#audit_description').val(),
            //             'audit_improvement': $('#audit_improvement').val(),
            //             'audit_risk': audit_risk,//$('input[type=radio][name=audit_risk]:checked').val(),
            //             'audit_status': audit_status,//$('input[type=radio][name=audit_status]:checked').val(),
            //             'audit_title': $('#audit_title').val(),
            //             'capa_type': $('#capa_type').val(),
            //             'audit_responsible_person': $('#audit_responsible_person').val(),
            //             'audit_cause_comments': $('#audit_cause_comments').val(),
            //             'audit_deadline_date': $('#audit_deadline_date').val(),
            //             'audit_closing_date': $('#audit_closing_date').val(),
            //             'capa_result': $('#capa_result').val(),
            //             'isCreateTask': $("#isCreateTask").is(":checked"),
            //         };
            //         if ($("#isCreateTask").is(":checked")) {
            //             var isCreateTask = 1;
            //         } else {
            //             var isCreateTask = 0;
            //         }
            //         var audit_activity_id = $('#audit_activity_id').val()

            //         $($audit_activity_editor).find('form').ajaxSubmit({
            //             dataType: 'json',
            //             data: submit_data,
            //             success: function (x) {
            //                 // isCreateTask is yes then i have create a task to tasktab
            //                 if(isCreateTask == 1 && audit_activity_id == 0){
            //                     var formData = new FormData();
            //                     formData.append("org_id", master.config.master_org_id);
            //                     formData.append("link_type", "record");
            //                     formData.append("link_id", master.config.id);
            //                     formData.append("task[title]", $('#audit_title').val());
            //                     formData.append("task[task_ref]", "");
            //                     formData.append("task[task_type]", "Custom");
            //                     formData.append("task[description]", "");
            //                     formData.append("task[assign_mode]", "self");
            //                     formData.append("task[deadline]", "");
            //                     formData.append("task[reminder]", "");
            //                     formData.append("task[ovd_intv]", 3);
            //                     formData.append("task[ovd_rep_limit]", 999);
            //                     formData.append("task[action_ntf_type]", "email");
            //                     $.ajax({
            //                         url: "/global/task?action=Create",
            //                         type: 'POST',
            //                         data: formData,
            //                         async: false,
            //                         success: function (data) {
            //                             console.log(" data ",data);
            //                         },
            //                         cache: false,
            //                         contentType: false,
            //                         processData: false
            //                     });
            //                 }
            //                 // 
            //             },
            //             error: cargohub.its.http_fail
            //         });
            //     */
            //     var description = '<b>Description : </b>'+'<p>'+$('#audit_description').val()+'</p>'+' <b> Root Cause : </b>'+'<p>'+$('#audit_cause_comments').val()+'</p>'+' <b> Improvement : </b>'+'<p>'+$('#audit_improvement').val()+'</p>';

            //     var taskFormData = new FormData();
            //     taskFormData.append("org_id", master.config.master_org_id);
            //     taskFormData.append("link_type", "record");
            //     taskFormData.append("link_id", master.config.id);
            //     taskFormData.append("task[title]", $('#audit_title').val());
            //     taskFormData.append("task[task_ref]", "");
            //     taskFormData.append("task[task_type]", "Custom");
            //     taskFormData.append("task[description]", description);
            //     taskFormData.append("task[assign_mode]", "self");
            //     taskFormData.append("task[deadline]", $('#audit_deadline_date').val());
            //     taskFormData.append("task[reminder]", "");
            //     taskFormData.append("task[ovd_intv]", 1);
            //     taskFormData.append("task[ovd_rep_limit]", 999);
            //     taskFormData.append("task[action_ntf_type]", "email");

            //    var audit_activity_id = $('#audit_activity_id').val();
            //    var isCreateTask = $("#isCreateTask").is(":checked");
            //    var isCreateTaskId = $("#isCreateTaskId").val();

               
            //     var submit_data = {
            //         'link_id': master.config.id,
            //         'track_id': master.config.track,
            //         'org_id': master.config.master_org_id,
            //         'audit_reference_no': $('#audit_reference_no').val(),
            //         'audit_description': $('#audit_description').val(),
            //         'audit_improvement': $('#audit_improvement').val(),
            //         'audit_risk': audit_risk,
            //         'audit_status': audit_status,
            //         'audit_title': $('#audit_title').val(),
            //         'capa_type': $('#capa_type').val(),
            //         'audit_responsible_person': $('#audit_responsible_person').val(),
            //         'audit_cause_comments': $('#audit_cause_comments').val(),
            //         'audit_deadline_date': $('#audit_deadline_date').val(),
            //         'audit_closing_date': $('#audit_closing_date').val(),
            //         'capa_result': $('#capa_result').val(),
            //         'isCreateTask': $("#isCreateTask").is(":checked"),
            //         'audit_activity_id': audit_activity_id
            //     };
            //     if(isCreateTask == 1 && audit_activity_id == 0 && isCreateTaskId ==0){
            //         $.when(createTask(taskFormData)).done(function (data) {
            //             taskId = data.iid;
            //             saveTask(submit_data,taskId).done(function (response) {
            //                 location.reload(); //21102024 by iwi
            //             }).fail(function (error) {
            //                 console.error("Error in the second request:", error);
            //             });
            //         }).fail(function (error) {
            //             console.error("Error in the first request:", error);
            //         });
            //     }else if(isCreateTask == 1 && audit_activity_id > 0 && isCreateTaskId ==0){
            //         $.when(createTask(taskFormData)).done(function (data) {
            //             taskId = data.iid;
            //             saveTask(submit_data,taskId).done(function (response) {
            //                 location.reload(); //21102024 by iwi
            //             }).fail(function (error) {
            //                 console.error("Error in the second request:", error);
            //             });
            //         }).fail(function (error) {
            //             console.error("Error in the first request:", error);
            //         });
            //     }else{
            //         saveTask(submit_data).done(function (response) {
            //               location.reload(); //21102024 by iwi
            //         }).fail(function (error) {
            //             console.error("Error in the second request:", error);
            //         });
            //     }


              
            // }, 
			//  init_audit_activity_Toolbar = function ()
            //  {
            //     //  security audit start
            //     $("#tab_security_activity .toolbar button").jqxButton({theme: cargohub.jqxtheme}).on('click', function (){
            //         console.log('case', $(this).data('op'));
            //         switch ($(this).data('op')) {
            //             case 'exportxlsx':
            //                 $("#_security_activity_record").jqxDataTable('exportData', 'xls');
            //                 break;
            //             case 'security-activity-create':
            //                 $('#tab_security_activity').fadeIn(); // Make sure Save is enabled
            //                 console.log(" 2623 disabled remove ");
            //                 $('#isCreateTask').prop('disabled', false);
            //                 initAuditActivityEditor({
            //                         title: "Create a Activity",
            //                         icon: "add.png",
            //                         mode: "create_audit_activity"
            //                     });
            //                     $('#tab_security_activity #create-activity-create-op').fadeOut(); // Hide the "Create" button when the editor is active, will be shown again on Save or Close

            //             break;
            //             case 'audit-activity-delete':
            //                 // if (!confirm("Are you sure you want to delete this document ?"))
            //                 // return;
            //                 cargohub.components.modal.delcustomconfirm(initAuditActivityDelete, "Are you sure you want to remove this activity ?",null,null,null,null,'delete');

            //                 // initAuditActivityDelete();
            //                 break;
            //             case 'audit-activity-save':
            //                 initAuditActivitySave();                           
            //             break;
            //             case 'audit-activity-close':

            //                     // if (editor_open)
            //                     // initAuditActivityViewerClose();                          
            //                     // break;

            //                 if (!$audit_activity_editor) // Not yet initialized
            //                 return;
            //                 if ($audit_activity_editor.jqxExpander('expanded')) {
            //                     $audit_activity_editor.on('collapsed', function () {
            //                         // When finishing collapse... hide the editor
            //                         $audit_activity_editor.hide();
            //                         // ... and disable the event
            //                         $audit_activity_editor.off('collapsed');
            //                     });
            //                     $audit_activity_editor.jqxExpander('collapse');
            //                 } else {
            //                     // If already collapsed, just hide it
            //                     $audit_activity_editor.hide();
            //                 }

            //                 // Also fadeout the Save/Close buttons
            //                 $('#tab_security_activity #security-activity-open-ops button').jqxButton({disabled: true});
            //                 $('#tab_security_activity #security-activity-open-ops').fadeOut();
            //                 $('#tab_security_activity #audit-activity-delete').fadeOut();


            //                 // $('#tab_security_activity #security-activity-row-ops').fadeIn();
            //                 $('#tab_security_activity #create-activity-create-op').fadeIn();

            //                 // if (!transition) {
            //                 //     if (selected_row)
            //                 //     $('#tab_security_activity #security-activity-row-ops').fadeIn();
            //                 //     // Show the "Create" button in case it was hidden by a previous operation
            //                 //     $('#tab_security_activity #create-activity-create-op').fadeIn();
            //                 // }

            //                 // editor_nonempty = false;
            //                 // editor_open = false;
            //                 open_row_id = null;
            //                 // copy_mode = false;
            //                 return true;
            //         }
            //     });
            //     //  security audit end
            //  },
			// security audit start
            //  init_DataTable1 = function (){

            //     var cell_audit_highlight = function (row, column, value, rowData) {
            //         var classname = "";
                
            //         // if (rowData.visibility == 'shared' || rowData.visibility == 'public')
            //         //     classname += "grid-cell-shared";
            //         // else if (rowData.visibility == 'private')
            //         //     classname += "grid-cell-private";
                
            //         // // Mark notes for the perspective org for the admin user
            //         // if (cargohub.application.user_Is_Admin() && rowData.org_id == master.config.perspective)
            //         //     classname += " grid-cell-perspective_org";
            //         return classname;
            //     };
            //     master.init_DataTable($("#tab_security_activity div.jqx-datatable"), {
            //         exportSettings: {
            //             columnsHeader: true,
            //             hiddenColumns: false,
            //             serverURL: "/?action=export",
            //             characterSet: null,
            //             collapsedRecords: false,
            //             recordsInView: true,
            //             fileName: "docs1"
            //         },
            //         // Config
            //         source: panel_audit_activity_Source,
            //         // 23102024 Audit control changes start
            //         columns: [
            //             {dataField: 'ID', text: "ID", cellClassName: cell_audit_highlight, 'width': '5%'},
            //             {dataField: 'title', text: "Title", cellClassName: cell_audit_highlight, 'width': '20%'},
            //             {dataField: 'reference_no', text: "Reference No", cellClassName: cell_audit_highlight, 'width': '5%'},
            //             {dataField: 'comments', text: "Root Cause", cellClassName: cell_audit_highlight,'width': '25%'},
            //             {dataField: 'improvement', text: "CaPa", cellClassName: cell_audit_highlight, 'width': '25%'},
            //             {dataField: 'type', text: "Type", cellClassName: cell_audit_highlight, 'width': '7%'},
            //             {dataField: 'risk', text: "Risk", cellClassName: cell_audit_highlight, 'width': '7%'},
            //             {dataField: 'result', text: "Area", cellClassName: cell_audit_highlight, 'width': '7%'},
            //             {dataField: 'deadline_date', text: "Deadline", cellClassName: cell_audit_highlight, 'width': '10%'},
            //             {dataField: 'closing_date', text: "Closing Date", cellClassName: cell_audit_highlight, 'width': '10%'},
            //             {dataField: 'status', text: "Status", cellClassName: cell_audit_highlight, 'width': '7%'},
            //             {dataField: 'isCreateTaskLable', text: "Task", cellClassName: cell_audit_highlight, 'width': '8%'},
            //             {dataField: 'person', text: "Assigned To", cellClassName: cell_audit_highlight, 'width': '8%'},
            //             {dataField: 'dsc', text: "Description", cellClassName: cell_audit_highlight, 'width': '25%'},
                      
            //         ]
            //         // 23102024 Audit control changes end
            //     }, 
            //     {
            //         "rowselect": initAuditActivitySelectRow,
            //         "clearselect": select_Clear,
            //         "rowopen": initAuditActivityOpen
            //     }, "docs1");
            // };
            // security audit  end

          //  security audit start 
			// function createTask(formData) {
			// 	return $.ajax({
			// 		url: "/global/task?action=Create",
			// 		type: 'POST',
			// 		data: formData,
			// 		cache: false,
			// 		contentType: false,
			// 		processData: false
			// 	});
			// }
			// function saveTask(formData,taskId) {
			// 	if(taskId > 0){ formData.taskId = taskId; }
			// 	const formData1 = objectToFormData(formData);
			// 	return $.ajax({
			// 		url: "/record/process?action=Load_save_audit_activity_management",
			// 		type: 'POST',
			// 		data: formData1,
			// 		contentType:false,
			// 		cache: false,
			// 		processData: false
			// 	});
			// }
			// function objectToFormData(obj) {
			// 	const formData = new FormData();
			// 	for (const key in obj) {
			// 		if (obj.hasOwnProperty(key)) {
			// 			formData.append(key, obj[key]);
			// 		}
			// 	}
			// 	return formData;
			// }
    		//  security audit end   

	this._public({

		// section initialization
		init: function()
		{
                    
//                    go to direct chart section start 
                     var hash = window.location.hash;
     if (hash && hash.startsWith("#chart")) {
//        console.log(self.config.ist.id);
                var checkInterval = setInterval(function() {

                //From cockpit go to chart page for training scheduler and training record start
                if (self.config.ist.id == '1'){
                if ($('#listChartBtn').length) {
                $('#listChartBtn').trigger('click');
                        clearInterval(checkInterval);
                }
                } else if (self.config.ist.id == '39' || self.config.ist.id == '51'){
                if ($('#training-report-button').length) {
                $('#training-report-button').trigger('click');
                        clearInterval(checkInterval);
                }
                }
                //From cockpit go to chart page for training scheduler and training record end
                }, 300);
        }
    //                    go to direct chart section end 
    
                    
			if(!self.config.special_settings)
				self.config.special_settings = {}; // To avoid errors
			console.log(" config  special_settings  width => ",self.config.special_settings.width);
			console.log(" config  special_settings  => ",self.config.ist.group_type);
			// This module has the special ability to expand or contract itself (not the fixed 1140 pixels)
			// if(!self.config.special_settings.width)
			// 	self.config.special_settings.width = 1880;// IWI set all views by default to 1880 
			// else if(self.config.special_settings.width < 950)
			// 	self.config.special_settings.width = 950;
			// else if(self.config.special_settings.width > 4070)
			// 	self.config.special_settings.width = 4070;

			// IWI doc manager module set  default to 1880 | 10012025 change start
			if(!self.config.special_settings.width){
				if(self.config.ist.group_type == 'dept'){
					self.config.special_settings.width = 1880;// IWI set all views by default to 1880 
				}else{
					self.config.special_settings.width = 1880;
				}
			}else if(self.config.special_settings.width < 950){
				self.config.special_settings.width = 950;
			}else if(self.config.special_settings.width > 4070){
				self.config.special_settings.width = 4070;
			}
			// IWI doc manager module set  default to 1880 | 10012025 change end
			
			$('div.site').width(self.config.special_settings.width);
			

			// security audit start
			// init_Toolbar();
            // var type_rt_id = cargohub.record_process.config.type.base_id;
            // if(type_rt_id  == 34){
            //     init_DataTable1();
            //     init_audit_activity_Toolbar();
            // }else{
            //     init_DataTable();
            // }  
			//security audit end
              
			// Prepare superuser-only data
			if(cargohub.application.user_Is_Admin()) {
				// Organizations with access to the record type
				dataAdapter_Admin_Orgs = new $.jqx.dataAdapter({
					url: "/admin/global/?action=get_related_orgs",
					data: {
						unselect_entry: true,
						mode: "ist",
						key: self.config.ist.key,
						sort: '`type_id` ASC, `name` ASC'
					},
					datatype: "json",
					datafields: [
						{name: 'id', type: 'number'},
						{name: 'group', type: 'string', map: "type_id"}, // Organization Type_ID becomes "group"
						{name: 'name'}
					],
					id: "id"
				});

//				dd(self.config.ist); // id, key, name, real_id, short_plural

				dataAdapter_Record_XF = new $.jqx.dataAdapter({
					url: "?action=get_xf_list",
					data: {
						rt_id: self.config.ist.id,
						cols: 'id,s,n',
						sort: '`s` ASC, `n` ASC'
					},
					datatype: "json",
					datafields: [
						{name: 'id', type: 'number'},
						{name: 'group', type: 'string', map: 's'}, // Field "section" becomes "group"
						{name: 'name', type: 'string', map: 'n'}
					],
					id: "id"
				});
			}


			grid_notif_visible = false;

			// Grid State initialization (if not transmitted by the server)
			if(!self.config.grid_state)
				self.config.grid_state = {
					pageNum: 0,
					pageSize: 25,
					pageSizeOpts: [25, 50, 100, 250, 500, 1000, 2500],
					sort: null,
					filters: null,
					grouping: []
				};
			else if(self.config.preferences.preserve && !self.config.preferences.preserve_alert_off) {
				$("#grid-notification-area").append($("#notif-grid-preserved").html()).show();
				grid_notif_visible = true;
			}

			// Automatic refresh function is enabled !
			if(self.config.special_settings.autoref_s > 0) {
				$("#grid-notification-area").append($("#notif-grid-autorefresh").html().present(self.config.special_settings)).show();
				grid_notif_visible = true;
			}

			if(grid_notif_visible) {
				$("#grid-notification-area").prepend('<img id="hide-grid-notification" src="/img/icons16/cross.png" alt="[Hide]" title="Hide this notification" />');
				$("#hide-grid-notification")
				.on('click', function() {
					$("#grid-notification-area").fadeOut();
					// Shot a small AJAX Query that will add a session variable indicating the message should not show up in current session
					$.post("?action=hide_searchsystem_alert");
				});
			}

			// The operational menu
			$("#listing-menubar").jqxMenu({
				theme: cargohub.jqxtheme, width: cargohub.fullwidth,
				minimizeWidth: null, animationShowDuration: 200, animationHideDuration: 100,
				autoOpen: false
			});

			// Initialize the special filters bar
			setup_Special_Filters_Bar();

			var process_hash = function(e, sysinit)
			{
				if(disable_onhashchange)
					return;

				// Read the document.location.hash, break it into components and set the configuration ( which might be used by the setup functions )
				self.config.hash = {};
				if(document.location.hash) {
					var hash_pieces = document.location.hash.substr(1).split("&"), hash_elem;

					for(c=0; c<hash_pieces.length; c++) {
						hash_elem = hash_pieces[c].split("=");
						self.config.hash[hash_elem[0]] = hash_elem[1];
					}
				} else {
					xfilters_sysinit = true;
					$("#ex-filter-group").jqxButtonGroup('setSelection', 1);
					xfilters_sysinit = false;
				}

				if(self.config.hash.xfilters) {
					xfilters_sysinit = true;

					// We have active xfilters, split into exgroup and ugroup
					var xf_split = self.config.hash.xfilters.split('|'),
					ugroup_assoc = {
						assigned: 0,
						unopen: 1,
						unexp: 2
					};

					xfilters = {
						exgroup: xf_split[0],
						ugroup: xf_split[1]? xf_split[1].split(','): []
					};

					// Phase 1 - "Push" buttons
					switch(xfilters.exgroup) {
						// Standard
						case 'showall':
							$("#ex-filter-group").jqxButtonGroup('setSelection', 0); break;
						case 'active':
							$("#ex-filter-group").jqxButtonGroup('setSelection', 1); break;
						case 'closed':
							$("#ex-filter-group").jqxButtonGroup('setSelection', 2); break;
						case 'notasks':
							$("#ex-filter-group").jqxButtonGroup('setSelection', 3); break;
							// Admin only
						case 'noorg':
							$("#ex-filter-group").jqxButtonGroup('setSelection', 4); break;
					}

					if(xfilters.ugroup) {
						for(c = 0; c< xfilters.ugroup.length; c++)
							$("#user-filter-group").jqxButtonGroup('setSelection', ugroup_assoc[xfilters.ugroup[c]]);
					}

					xfilters_sysinit = false;

					// TODO: Phase 2 - Update grid with new xfilters
					if(!sysinit) {

					}
				}

//				else
//					$("div#ex-filter-group").jqxButtonGroup('setSelection', 1);

			};

			process_hash(null, true); // XFilter bar initialization - "sysinit" is marked true to avoid attempting to update the grid (which might not even be initialized yet)

			window.onhashchange = process_hash;

			// Commands and Operations
			$(".jqx-menu-item-"+cargohub.jqxtheme+", div.command button, div#special-filter-bar > button").on('click', function() {
				switch($(this).data('op')) {
					// Operations performed on the selected row(s)
					case "action":
						action_onSelected($(this).data());
						break;
					// Select mode + Clear selection
					case "select-mode":
						$("#issues-grid").jqxGrid('selectionmode', $(this).data('mode'));
						break;
					case "select-clear":
						$("#issues-grid").jqxGrid('clearselection');
						break;
					// Grid views operations
					case "grid-views":
						ops_Grid_Views($(this).data());
						break;
					// Operations related to how the grid loads (what columns are visible and their size)
					case "grid-columns":
						setup_Grid_Columns();
						break;
					// Configuration for the grid module itself (globally) - Width customization / Autorefresh / Show/Hide TrackID column
					case "module-options":
						setup_Special_Settings();
						break;
					// Change various settings on how the grid is loaded (per issue type) - Grouping, Sorting (Affects server-side as well). This particular group sends a request directly
					case "grid-settings":
						$.post("?action=Grid_Settings", {
							setting: $(this).data('setting'),
							mode: $(this).data('mode')
						}, function(x) {

							if(x.success) {
								// Setting has been changed, reload the grid to display the new arrangment
								document.location.reload();
							} else
								cargohub.its.http_fail(null, "Record.Grid::grid_settings");
						})
						.fail(cargohub.its.http_fail);
						break;

					// Paging settings require one more input from user
					case "grid-paging":
						// TODO Grid Paging settings
						break;

					// Clears the session store grid state ( filters, sort, pager ) so it returns to a "clean" state which contains the default (profile stored) grouping/sorting/page "1" with default number of rows.
					// This also cancels any active view for loaded record type (otherwise the reset would not work). However it does not affect Special Settings.
					case "grid-state-reset":
						$.post("?action=Save_Grid_State", {
							state: false
						}, function(x) {
							disable_onhashchange = true;
							history.pushState("", document.title, window.location.pathname + window.location.search);
							// Reload the page after clearing the state
							document.location.reload();
						})
						.fail(cargohub.its.http_fail);
						break;
					// Admin only function, saves current grid columns as "Organization default" - The organization needs to have access to the issue type first to appear in the list.
					case "grid-set_default":
						if(!self.config.perspective)
							return cargohub.alert("Please select the organization perspective to save the default for that organization.");

						if(!confirm("Are you sure you want to set your current view as the default for:\n\n "+self.config.admin_org_name+" ?"))
							return;

						$.post("?action=set_default_view", function(x) {
							if(x.success) {
								cargohub.alert("Default View for "+self.config.admin_org_name+" has been set.", "Success !", "thumb_up.png");
							} else {
								cargohub.alert(x.reason, "Message from server", "events.png");
							}
						});
						break;

					// Switch issue type, this one performs similar action with the top-bar menu
					case "sw-ist":
						cargohub.application.save_Preferences({
							"rt_id": $(this).data('id')
						}, function() {
							document.location.reload();
						});
						break;

					// Export table content
					case 'export':
						// Part 1 - Export (creates a downloadable file)
						var filename = self.config.ist.short_plural+ '-Grid Export',
							format = $(this).data('format');
						if(self.config.views.length > 0 && self.config.active_view) {
							// Locate active view in "views"
							for(c=0; c<self.config.views.length; c++)
								if(self.config.views[c].id == self.config.active_view)
									filename+= " ("+self.config.views[c].name+")";
						}

						if(format == 'cclp') {
							filename = "#cclp|"+ filename;
							if(self.config.perspective)
								filename+= "|"+ self.config.perspective;
							format = 'csv';
						}

						// The Export method is in *public/index.php
						try {
							$("#issues-grid").jqxGrid('exportdata', format, filename, true, null, false, "/?action=Export");
						}
						catch(err) {
							return cargohub.alert(err);
						}

						// Part 2 - Marks the rows which are on the screen as "Exported" (so they can be filtered out later)
						var rows = $('#issues-grid').jqxGrid('getrows'), row_ids = [];
						for(c = 0; c< rows.length; c++)
							row_ids.push(parseInt(rows[c].uid));

						$.post("?action=Mark_Export", {
							ids: row_ids.join(',')
						}, function(x) {
							if(x.success) {
								// Updates the grid, if the filter "Not exported" is no, this automatically removes the page of just exported records
								$('#issues-grid').jqxGrid('updatebounddata');
								// Feedback
								cargohub.components.spinner.On("Feedback:", x.response);
								setTimeout(function() {
									cargohub.components.spinner.Off();
								}, 3000);
							}
						}); // No failure feedback
						break;
						case "all-list":
						console.log('fdgfg');
						break;
						case "chart":
						console.log('chart');
						break;
				}
			});

			setup_Records_Grid();

			
			

			var body = $('body');
			$('[data-toggle="minimize"]').on("click", function() {
				if ((body.hasClass('sidebar-toggle-display')) || (body.hasClass('sidebar-absolute'))) {
				  body.toggleClass('sidebar-hidden');
				} else {
				  body.toggleClass('sidebar-icon-only');
				}
			  });


			// Info Explander
			$(".info-expander").jqxExpander({
				theme: cargohub.jqxtheme,
				expanded: false
			});
			
			// 11122023 created by iwi |  start
			$("#chartViewSec").css("display", "none");
			$('#listViewBtn').click(function(){
//				document.location.reload();

var currentUrl = window.location.href;
    if (currentUrl.includes('#chart')) {
        var newUrl = currentUrl.replace('#chart', '');
        window.location.href = newUrl;
    }
    
    
				$("#chartViewSec").css("display", "none");
				$("#listViewSec").css("display", "block");
				$("#cargoDamageChartViewSec").css("display", "none");
				$("#trainingReportViewSec").css("display", "none");
				$("#caPaReportViewSec").css("display", "none");//03012025 changes report section
				setup_Records_Grid();
			});

			$('#listChartBtn').click(function(){
//                            IWI Cargo claim chart page changes 22052025 start
                            $('#listChartBtn').closest('.site').css({
                                'max-width': '1320px',
                                'width': '100%',
                                'margin': '0 auto'
                            });
                            $('#listChartBtn').closest('#listing-menubar').css({
                                'max-width': '1320px',
                                'width': '100%',
                                'margin': '0 auto'
                            });
//			IWI Cargo claim chart page changes 22052025 end	
				// getYear option append start
				var currentYear = new Date().getFullYear();
				var getYearOption = '';
				for(var i = 2015; i <= currentYear; i++){
					if(i == currentYear){
						getYearOption += '<option value="'+i+'" selected>'+i+'</option>';
					}else{
						getYearOption += '<option value="'+i+'">'+i+'</option>';
					}
				}
				$('.getYear').html(getYearOption);
				$('.kpiGetYear').html(getYearOption);

				$("#startDate").jqxDateTimeInput({ width: '100%', height: '25px', theme: 'summer' });
				$("#endDate").jqxDateTimeInput({ width: '100%', height: '25px', theme: 'summer' });

				if($("#kpiStartDate").length>0){ //IWI Cargo claim chart page changes 22052025
				$("#kpiStartDate").jqxDateTimeInput({ width: '100%', height: '25px', theme: 'summer' });
				$("#kpiEndDate").jqxDateTimeInput({ width: '100%', height: '25px', theme: 'summer' });
                            }//IWI Cargo claim chart page changes 22052025


                $('head').append('<link rel="stylesheet" href="/css/default/components/bootstrap.min.css?v=2023.0" type="text/css" />');

				Chart.register(ChartDataLabels);
				
				$("#chartViewSec").css("display", "block");
				$("#listViewSec").css("display", "none");

				
//				load_filter_value();//IWI Aircargoclaim chart page update 12012026

				$('.filterSubmit').click(function(){
					var action_val = $('.claim-chart li.active').attr('data-action');
					load_filter_value(action_val);
					console.log(" filterSubmit 2743 : ");

					
				});


				$('.filterType').change(function(){
					var filterType = $(this).val();
					if(filterType == 'dates'){
						$('.start_end_date_div').css("display", "flex");
						$('.quarterly_date_div').css("display", "none");
					}else if(filterType == 'quarterly'){
						$('.quarterly_date_div').css("display", "block");
						$('.start_end_date_div').css("display", "none");
					}
					else{
						$('.start_end_date_div').css("display", "none");
						$('.quarterly_date_div').css("display", "none");
					}
				});

				$('.chart-download').click(function(){
					var action_val = $('.claim-chart li.active').attr('data-action');
					// alert(" action_val => "+action_val);
					// load_filter_value(action_val);

					switch(action_val){
						case 'claim-type':
							getPDF();
							break;
//                                                        IWI Air Cargo claim chart page new design start
						case 'formal-claim-type':
							getPDF();
							break;
//                                                        IWI Air Cargo claim chart page new design end
						case 'claim-aspects':
							getPDF();
							break;
						case 'claim-root':
							getPDF();
							break;

						case 'claim-financials':
							getPDF();
							break;
						case 'claim-service':
							getPDF();
							break;
						case 'claim-activity':
							getPDF();
					}
						
				});


				
	$(".canvas_div_pdf.active").css({
    "padding": "20px",
    "box-sizing": "border-box",
    "background-color": "#ffffff",
    "color": "#000000",
//    "max-width": "800px",
    "width": "100%",
//    "border": "1px solid #ccc",
    
});	

				function getPDF(){
					var HTML_Width = $(".canvas_div_pdf.active").width();
					var HTML_Height = $(".canvas_div_pdf.active").height();
					var top_left_margin = 15;
					var PDF_Width = HTML_Width+(top_left_margin*2);
					var PDF_Height = (PDF_Width*1.5)+(top_left_margin*2);
//					var canvas_image_width = HTML_Width;
//					var canvas_image_height = HTML_Height;
					var totalPDFPages = Math.ceil(HTML_Height/PDF_Height)-1;
var canvas_image_width = PDF_Width - (top_left_margin * 2);
var canvas_image_height = (canvas_image_width * HTML_Height) / HTML_Width;
			
					html2canvas($(".canvas_div_pdf.active")[0],{allowTaint:true,backgroundColor: "#ffffff", scale: 2}).then(function(canvas) {
						canvas.getContext('2d');
						var imgData = canvas.toDataURL("image/jpeg", 1.0);
						var pdf = new jsPDF('p', 'pt',  [PDF_Width, PDF_Height]);
						pdf.addImage(imgData, 'JPG', top_left_margin, top_left_margin,canvas_image_width,canvas_image_height);
						for (var i = 1; i <= totalPDFPages; i++) { 
							pdf.addPage(PDF_Width, PDF_Height);
							pdf.addImage(imgData, 'JPG', top_left_margin, -(PDF_Height*i)+(top_left_margin*4),canvas_image_width,canvas_image_height);
						}
						pdf.save("Cargo-Claim.pdf");
					});
				};


				$('.kpi-chart-download').click(function(){
					getPDF2();
				});


				function getPDF2(){

					var HTML_Width = $(".canvas_div_pdf2.active").width();
					var HTML_Height = $(".canvas_div_pdf2.active").height();
					var top_left_margin = 15;
					var PDF_Width = HTML_Width+(top_left_margin*2);
					var PDF_Height = (PDF_Width*1.5)+(top_left_margin*2);
//					var canvas_image_width = HTML_Width;
//					var canvas_image_height = HTML_Height;
var canvas_image_width = PDF_Width - (top_left_margin * 2);
var canvas_image_height = (canvas_image_width * HTML_Height) / HTML_Width;
					
					var totalPDFPages = Math.ceil(HTML_Height/PDF_Height)-1;
					
			
					html2canvas($(".canvas_div_pdf2.active")[0],{allowTaint:true,backgroundColor: "#ffffff", scale: 2}).then(function(canvas) {
						canvas.getContext('2d');
						
						console.log(canvas.height+"  "+canvas.width);
						
						
						var imgData = canvas.toDataURL("image/jpeg", 1.0);
						var pdf = new jsPDF('p', 'pt',  [PDF_Width, PDF_Height]);
						pdf.addImage(imgData, 'JPG', top_left_margin, top_left_margin,canvas_image_width,canvas_image_height);
						
						
						for (var i = 1; i <= totalPDFPages; i++) { 
							pdf.addPage(PDF_Width, PDF_Height);
							pdf.addImage(imgData, 'JPG', top_left_margin, -(PDF_Height*i)+(top_left_margin*4),canvas_image_width,canvas_image_height);
						}
						
						pdf.save("kpi.pdf");
					});
				};


				// kpi chart filter | start
					$('.kpiFilterSubmit').click(function(){
						load_kpi_filter_value();
					});

					$('.kpi-chart-div').click(function(){
						load_kpi_filter_value();
					});
				
					function load_kpi_filter_value(){

						kpiGetYear = $('.kpiGetYear').val();
						startDate = $('#kpiStartDate').val();
						endDate = $('#kpiEndDate').val();
						quarterlyValue = $('input[name="kpiQuarterlyValue"]:checked').val();//$('.quarterlyValue').val();
						kpiFilterType = $('input[name="kpiFilterType"]:checked').val();//$('#kpiFilterType').val();
						kpiFilterStatus = $('input[name="kpiFilterStatus"]:checked').val();
						if(kpiFilterType == 'yearly'){
							startDate = kpiGetYear+'-01-01';
							endDate = kpiGetYear+'-12-31';
						}
						else if(kpiFilterType == 'quarterly'){
							if(quarterlyValue == 'Q1'){
								startDate = kpiGetYear+'-01-01';
								endDate = kpiGetYear+'-03-31';
							}
							else if(quarterlyValue == 'Q2'){
								startDate = kpiGetYear+'-04-01';
								endDate = kpiGetYear+'-06-30';
							}
							else if(quarterlyValue == 'Q3'){
								startDate = kpiGetYear+'-07-01';
								endDate = kpiGetYear+'-09-30';
							}
							else if(quarterlyValue == 'Q4'){
								startDate = kpiGetYear+'-10-01';
								endDate = kpiGetYear+'-12-31';
							}
						}
						else if(kpiFilterType == 'dates'){
							startDate = $('#startDate').val();
							endDate = $('#endDate').val();
						}
						else{
							startDate = '';
							endDate = '';
						}
						Load_kpi_cargo_Record_Chart(startDate, endDate,kpiFilterStatus);
					}

					function Load_kpi_cargo_Record_Chart(startDate='', endDate='',kpiFilterStatus=''){
						
						$(".simple-spinner-chart").css("display", "block");
						$.post("/user/overview?action=Load_kpi_Record_Chart", {
							startDate: startDate,
							endDate: endDate,
							kpiFilterStatus: kpiFilterStatus,
						}, function(x) {
							
							jQuery("#myChart20").remove();
							jQuery("#myChart20P").append('<canvas id="myChart20"></canvas>');
	
							jQuery("#myChart21").remove();
							jQuery("#myChart21P").append('<canvas id="myChart21"></canvas>');
	
							jQuery("#myChart22").remove();
							jQuery("#myChart22P").append('<canvas id="myChart22"></canvas>');
	
							jQuery("#myChart23").remove();
							jQuery("#myChart23P").append('<canvas id="myChart23"></canvas>');
	
							
									
							// 23112023 chart config start
								var awbIssueData = x.awbIssueData;
								var awbPreliminaryFormalIssueData = x.awbPreliminaryFormalIssueData;
								var awbTotalClaimedAmountResultData = x.awbTotalClaimedAmountResultData;
								var noOfRecordClaimedAmountData = x.noOfRecordClaimedAmountData;
								
								if (!x.success)
								return cargohub.alert(x.reason, "Message from server");
							
								// Formal Claims per 1000 AWBs | start
									const ctx20 = document.getElementById('myChart20');
									const totalNoOfawbIssueData = awbIssueData.y_axis.concat(awbIssueData.rc_y_axis);
									new Chart(ctx20, {
										type: 'line',
										data: {
											labels: awbIssueData.x_axis,
											datasets: [{
													label:"Number of Claims per 1000 AWBs",
													data: awbIssueData.y_axis,
													borderWidth: 3,
													backgroundColor: '#4274be',
													borderColor: '#4274be',
													// pointRedius:1,
												},
												{
													label:"Number of Claims less Denied Claims per 1000 AWBs",
													data: awbIssueData.rc_y_axis,
													borderWidth: 3,
													backgroundColor: '#ff6384',
													borderColor: '#ff6384',
													// pointRedius:1,
												},
											
											]
										},
										options: {
											elements: {
												point: {
													radius: 2,
													hoverRadius: 4,
												}
												},
											tension:0.4,
											plugins: {
												
												datalabels: {
													color: '#000',
													textAlign: 'top',
													anchor: 'end',
													align: 'end',
													formatter: function (value, ctx) {
														var index = ctx.dataIndex;
														var label = ctx.chart.data.labels[index];
														return  value;
													}
												},
												legend: {
													position:'bottom',
												labels: {
													boxWidth: 14,
													font: {
													  size: 14,
													}
												  }
												},
											},
											scales: {
												x: {
													beginAtZero: true,
													ticks: {
														font: {
															size: 10,
															family:'Roboto'
														}
													},
													title: {
														display: true,
														text: '',
														color: '#595959',
														font: {
															weight: '500',
															size:12,
															family:'Roboto'
														},
													}
												},
												y: {
													beginAtZero: true,
													max: (Math.max(...totalNoOfawbIssueData) + 2), // Dynamic (extra + 2 ) 
													title: {
														display: true,
														text: 'Number of claims',
														color: '#595959',
														font: {
															weight: '500',
															color: '#000',
															size:12,
															family:'Roboto'
														},
													}
												}
											}
										}
									});
								// Formal Claims per 1000 AWBs | end
							
								const ctx21 = document.getElementById('myChart21');
								const totalNoOfawbPreliminaryFormalIssueData = awbPreliminaryFormalIssueData.apf_y_axis.concat(awbPreliminaryFormalIssueData.y_axis);
								new Chart(ctx21, {
									type: 'line',
									data: {
										labels: awbPreliminaryFormalIssueData.x_axis,
										datasets: [{
												label:"Total Number of Claims (Preminary + Formal Claims per 1000 AWBs)",
												data: awbPreliminaryFormalIssueData.apf_y_axis,
												borderWidth: 3,
												backgroundColor: '#4274be',
												borderColor: '#4274be',
												// pointRedius:1,
											},
											{
												label:"Number of Claims per 1000 AWBs",
												data: awbPreliminaryFormalIssueData.y_axis,
												borderWidth: 3,
												backgroundColor: '#5ee7d4',
												borderColor: '#5ee7d4',
												// pointRedius:1,
											}
										
										]
									},
									options: {
										elements: {
											point: {
												radius: 2,
												hoverRadius: 4,
											}
											},
										tension:0.4,
										plugins: {
											datalabels: {
												color: '#000',
												textAlign: 'top',
												anchor: 'end',
												align: 'end',
												formatter: function (value, ctx) {
													var index = ctx.dataIndex;
													var label = ctx.chart.data.labels[index];
													return  value;
												}
											},
											legend: {
												position:'bottom',
												labels: {
													boxWidth: 14,
													font: {
													  size: 14,
													}
												  }
											},
										},
										scales: {
											x: {
												beginAtZero: true,
												ticks: {
													font: {
														size: 10,
														family:'Roboto'
													}
												},
												title: {
													display: true,
													text: '',
													color: '#595959',
													font: {
														weight: '500',
														size:12,
														family:'Roboto'
													},
												}
											},
											y: {
												beginAtZero: true,
												max: (Math.max(...totalNoOfawbPreliminaryFormalIssueData) + 2), // Dynamic (extra + 2 ) 
												title: {
													display: true,
													text: 'Number of claims',
													color: '#595959',
													font: {
														weight: '500',
														color: '#000',
														size:12,
														family:'Roboto'
													},
												}
											}
										}
									}
								});
					
								const ctx22 = document.getElementById('myChart22');
								new Chart(ctx22, {
									type: 'line',
									data: {
										labels: awbTotalClaimedAmountResultData.x_axis,
										datasets: [{
												label:"",
												data: awbTotalClaimedAmountResultData.y_axis,
												borderWidth: 3,
												backgroundColor: '#4274be',
												borderColor: '#4274be',
											}]
									},
									options: {
										elements: {
											point: {
												radius: 2,
												hoverRadius: 4,
											}
											},
										tension:0.4,
										plugins: {
											datalabels: {
												color: '#000',
												textAlign: 'top',
												anchor: 'end',
												align: 'end',
												formatter: function (value, ctx) {
													var index = ctx.dataIndex;
													var label = ctx.chart.data.labels[index];
													return  value;
												}
											},
											legend: {
												display:false,
												position:'bottom',
												labels: {
													boxWidth: 14,
													font: {
													  size: 14,
													}
												  }
											},
										},
										scales: {
											x: {
												beginAtZero: true,
												ticks: {
													font: {
														size: 10,
														family:'Roboto'
													}
												},
												title: {
													display: true,
													text: '',
													color: '#595959',
													font: {
														weight: '500',
														size:12,
														family:'Roboto'
													},
												}
											},
											y: {
												beginAtZero: true,
												title: {
													display: true,
													text: 'Number of claims',
													color: '#595959',
													font: {
														weight: '500',
														color: '#000',
														size:12,
														family:'Roboto'
													},
												}
											}
										}
									}
								});
					
								const ctx23 = document.getElementById('myChart23');
								const totalNoOfRecordClaimedAmountData = noOfRecordClaimedAmountData.ClaimValue5000.concat(noOfRecordClaimedAmountData.ClaimValue10000
									,noOfRecordClaimedAmountData.ClaimValue25000,noOfRecordClaimedAmountData.ClaimValue50000); 
								new Chart(ctx23, {
									type: 'bar',
									data: {
										labels: noOfRecordClaimedAmountData.norca_y_axis,
										datasets: [
											{
												label:"5,000 USD",
												data: noOfRecordClaimedAmountData.ClaimValue5000,
												borderWidth: 1,
												backgroundColor: '#4274be',
												borderColor: '#4274be',
												borderRadius: 10,
												barParcentage:0.2,
												categoryPercentage:0.5,  
											},
											{
												label:"10,000 USD",
												data: noOfRecordClaimedAmountData.ClaimValue10000,
												borderWidth: 1,
												backgroundColor: '#fb0',
												borderColor: '#fb0',
												borderRadius: 10,
												barParcentage:0.2,
												categoryPercentage:0.5,   
											},
											{
												label:"25,000 USD",
												data: noOfRecordClaimedAmountData.ClaimValue25000,
												borderWidth: 1,
												backgroundColor: '#55dbc2',
												borderColor: '#55dbc2',
												borderRadius: 10,
												barParcentage:0.2,
												categoryPercentage:0.5,   
											},
											{
												label:"50,000 USD",
												data: noOfRecordClaimedAmountData.ClaimValue50000,
												borderWidth: 1,
												backgroundColor: '#fa8619',
												borderColor: '#fa8619',
												borderRadius: 10,
												barParcentage:0.2,
												categoryPercentage:0.5,   
											}
										]
									},
									options: {
										plugins: {
											datalabels: {
												color: '#000',
												rotation:90,
												textAlign: 'top',
												anchor: 'end',
												align: 'end',
												formatter: function (value, ctx) {
													var index = ctx.dataIndex;
													var label = ctx.chart.data.labels[index];
													return  value;
												}
											},
											legend: {
												position:'bottom',
												labels: {
													boxWidth: 14,
													font: {
													  size: 14,
													}
												  }
											},
											
										},
										scales: {
											x: {
												beginAtZero: true,
												ticks: {
													font: {
														size: 10,
														family:'Roboto'
													}
												},
												title: {
													display: true,
													text: '',
													color: '#595959',
													font: {
														weight: '500',
														size:12,
														family:'Roboto'
													},
												}
											},
											y: {
												beginAtZero: true,
												max: (Math.max(...totalNoOfRecordClaimedAmountData) + 2), // Dynamic (extra + 2 ) 
												title: {
													display: true,
													text: 'Number of claims',
													color: '#595959',
													font: {
														weight: '500',
														color: '#000',
														size:12,
														family:'Roboto'
													},
												}
											}
										}
									}
								});



								
					
							// 23112023 chart config end
							// setTimeout(function(){
							// 	$(".simple-spinner-chart").css("display", "none");
							// }, 500);
								
						});
						setTimeout(function(){
							$(".simple-spinner-chart").css("display", "none");
						}, 800);
					}
				// kpi chart filter | end
				
				
				$('.claim-chart-div').click(function(){
					load_filter_value('claim-type');
				});
				
				$('.claim-type').click(function(){
					load_filter_value('claim-type');
				});
				
//                                IWI Air Cargo claim chart page new design start
				$('.formal-claim-type').click(function(){
					load_filter_value('formal-claim-type');
				});
//                                IWI Air Cargo claim chart page new design end
				$('.claim-aspects').click(function(){
					load_filter_value('claim-aspects');
				});
				$('.claim-root').click(function(){
					load_filter_value('claim-root');
				});
				$('.claim-financials').click(function(){
					load_filter_value('claim-financials');
				});
				$('.claim-service').click(function(){
					load_filter_value('claim-service');
				});
				$('.claim-activity').click(function(){
					load_filter_value('claim-activity');
				});


				function load_filter_value(action='claim-type'){
					getYear = $('.getYear').val();
					startDate = $('#startDate').val();
					endDate = $('#endDate').val();
					quarterlyValue = $('input[name="quarterlyValue"]:checked').val();//$('.quarterlyValue').val();
//					filterType = $('input[name="filterType"]:checked').val();//$('#filterType').val();//IWI Cargo claim chart page changes 22052025 
//					filterStatus = $('input[name="filterStatus"]:checked').val(); //IWI Cargo claim chart page changes 22052025 



                                        filterType = $('[name="filterType"]').val();//IWI Cargo claim chart page changes 22052025 
					filterStatus = $('[name="filterStatus"]').val(); //IWI Cargo claim chart page changes 22052025 
                                        
					if(filterType == 'yearly'){
						startDate = getYear+'-01-01';
						endDate = getYear+'-12-31';
					}
					else if(filterType == 'quarterly'){
						if(quarterlyValue == 'Q1'){
							startDate = getYear+'-01-01';
							endDate = getYear+'-03-31';
						}
						else if(quarterlyValue == 'Q2'){
							startDate = getYear+'-04-01';
							endDate = getYear+'-06-30';
						}
						else if(quarterlyValue == 'Q3'){
							startDate = getYear+'-07-01';
							endDate = getYear+'-09-30';
						}
						else if(quarterlyValue == 'Q4'){
							startDate = getYear+'-10-01';
							endDate = getYear+'-12-31';
						}
					}
					else if(filterType == 'dates'){
						startDate = $('#startDate').val();
						endDate = $('#endDate').val();
					}
					else{
						startDate = '';
						endDate = '';
					}
					var chartYearName = startDate.split("-");
					$( ".chart_year").text('Year '+chartYearName[0]);


                                            console.log(action);
					switch(action){
						case 'claim-type':
							//Load_Record_Claim_Type_Record_Chart(startDate, endDate, filterStatus);
                                                        check_Airline_Selected(); //IWI Air Cargo claim chart page new design
							Load_Record_Claim_Type_Record_Chart_v2(startDate, endDate, filterStatus); //IWI Air Cargo claim chart page new design
							break;
//                                                        IWI Air Cargo claim chart page new design start
						case 'formal-claim-type':
							Load_Record_Formal_Claim_Type_Record_Chart(startDate, endDate, filterStatus); 
							break;
//                                                        IWI Air Cargo claim chart page new design end
						case 'claim-aspects':
							Load_Record_Claim_Aspects_Record_Chart(startDate, endDate, filterStatus);
							break;
						case 'claim-root':
							Load_Record_Claim_Root_Cause_Record_Chart(startDate, endDate, filterStatus);
							break;

						case 'claim-financials':
							Load_Record_Claim_Financials_Record_Chart(startDate, endDate, filterStatus);
							break;
						case 'claim-service':
								Load_Record_Claim_Service_Record_Chart(startDate, endDate, filterStatus);
								break;
						case 'claim-activity':
							Load_Record_Claim_Activity_Record_Chart(startDate, endDate, filterStatus);
							break;
					}
					
				}
//                                IWI Air Cargo claim chart page new design start
//				function Load_Record_Claim_Type_Record_Chart(startDate='', endDate='', filterStatus=''){
//					$(".simple-spinner-chart").css("display", "block");
//					$.post("/user/overview?action=Load_Claim_Type_Record_Chart", {
//						startDate: startDate,
//						endDate: endDate,
//						filterStatus: filterStatus
//					}, function(x) {
//						
//						jQuery("#myChart26").remove();
//						jQuery("#myChart26P").append('<canvas id="myChart26"></canvas>');
//
//						jQuery("#myChart27").remove();
//						jQuery("#myChart27P").append('<canvas id="myChart27"></canvas>');
//						
//						var formalPermonthResultData = x.formalPermonthResultData;
//						var totalActiveRecordEveryMonthData = x.totalActiveRecordEveryMonthData;
//						
//						var preliminaryClaimOriginValue = x.preliminaryClaimOriginValue;
//						var preliminaryClaimByOrigin = '';
//						$.each(preliminaryClaimOriginValue, function (key, val) {
//							preliminaryClaimByOrigin += '<li><label>'+val.x_axis+'</label> <span>'+val.y_axis+'</span></li>';
//						});
//						$(".preliminaryClaimByOrigin").html(preliminaryClaimByOrigin);
//
//						var preliminaryClaimDestinationValue = x.preliminaryClaimDestinationValue;
//						var preliminaryClaimByDestination = '';
//						$.each(preliminaryClaimDestinationValue, function (key, val) {
//							preliminaryClaimByDestination += '<li><label>'+val.x_axis+'</label> <span>'+val.y_axis+'</span></li>';
//						});
//						$(".preliminaryClaimByDestination").html(preliminaryClaimByDestination);
//
//
//						var formalClaimDestinationValue = x.formalClaimDestinationValue;
//						var formalClaimDestinationValueHtml = '';
//						$.each(formalClaimDestinationValue, function (key, val) {
//							formalClaimDestinationValueHtml += '<li><label>'+val.x_axis+'</label> <span>'+val.y_axis+'</span></li>';
//						});
//						$(".formalClaimDestinationValue").html(formalClaimDestinationValueHtml);
//
//
//						var formalclaimOriginValue = x.formalclaimOriginValue;
//						var formalclaimOriginValueHtml = '';
//						$.each(formalclaimOriginValue, function (key, val) {
//							formalclaimOriginValueHtml += '<li><label>'+val.x_axis+'</label> <span>'+val.y_axis+'</span></li>';
//						});
//						$(".formalClaimOriginValue").html(formalclaimOriginValueHtml);
//                                                
//                                                
////                                                IWI Cargo claim chart page changes 22052025 start
//                                                var claimByOriginTableValue = x.claimByOrigin;
//                                                var claimByDestinationTableValue = x.claimByDestination;
//
//                                                var claimByOriginTableDataHtml = '';
//                                                var claimByDestinationTableDataHtml = '';
//
//                                                var originRowCount = 0;
//                                                var destinationRowCount = 0;
//
//                                                // Build Origin Table
//                                                $.each(claimByOriginTableValue, function (key, val) {
//                                                    claimByOriginTableDataHtml += '<tr>';
//                                                    claimByOriginTableDataHtml += '<td>' + val.origin + '</td>';
//                                                    claimByOriginTableDataHtml += '<td>' + val.damage + '</td>';
//                                                    claimByOriginTableDataHtml += '<td>' + val.total_loss + '</td>';
//                                                    claimByOriginTableDataHtml += '<td>' + val.delay + '</td>';
//                                                    claimByOriginTableDataHtml += '<td>' + val.partial_loss + '</td>';
//                                                    claimByOriginTableDataHtml += '<td>' + val.pilferage + '</td>';
//                                                    claimByOriginTableDataHtml += '</tr>';
//                                                    originRowCount++;
//                                                });
//
//                                                // Build Destination Table
//                                                $.each(claimByDestinationTableValue, function (key, val) {
//                                                    claimByDestinationTableDataHtml += '<tr>';
//                                                    claimByDestinationTableDataHtml += '<td>' + val.destination + '</td>';
//                                                    claimByDestinationTableDataHtml += '<td>' + val.damage + '</td>';
//                                                    claimByDestinationTableDataHtml += '<td>' + val.total_loss + '</td>';
//                                                    claimByDestinationTableDataHtml += '<td>' + val.delay + '</td>';
//                                                    claimByDestinationTableDataHtml += '<td>' + val.partial_loss + '</td>';
//                                                    claimByDestinationTableDataHtml += '<td>' + val.pilferage + '</td>';
//                                                    claimByDestinationTableDataHtml += '</tr>';
//                                                    destinationRowCount++;
//                                                });
//
//                                                // Determine the max count (at least 10 rows)
//                                                var maxRowCount = Math.max(originRowCount, destinationRowCount);
//
//                                                // Pad Origin Table
//                                                while (originRowCount < maxRowCount) {
//                                                    claimByOriginTableDataHtml += '<tr>' + '<td>&nbsp;</td>'.repeat(6) + '</tr>';
//                                                    originRowCount++;
//                                                }
//
//                                                // Pad Destination Table
//                                                while (destinationRowCount < maxRowCount) {
//                                                    claimByDestinationTableDataHtml += '<tr>' + '<td>&nbsp;</td>'.repeat(6) + '</tr>';
//                                                    destinationRowCount++;
//                                                }
//
//                                                // Inject into DOM
//                                                $("#claimByOriginTableData").html(claimByOriginTableDataHtml);
//                                                $("#claimByDestinationTableData").html(claimByDestinationTableDataHtml);
//
//                                                
//                                                
////                                                IWI Cargo claim chart page changes 22052025 end
//
//
//						$("#preliminary_count").text(x.totalPreliminaryClaimTypeData);
//						$("#formal_count").text(x.totalFormalClaimTypeData);
//						
//						
//						const ctx26 = document.getElementById('myChart26');
//						new Chart(ctx26, {
//							type: 'bar',
//							data: {
//								labels: formalPermonthResultData.fprx_axis,
//								datasets: [{
////										label:"fpr",//IWI Cargo claim chart page changes 22052025
//										label:"Formal", //IWI Cargo claim chart page changes 22052025
//										data: formalPermonthResultData.fpry_axis,
//										borderWidth: 1,
//										backgroundColor: '#4274be',
//										borderColor: '#4274be',
//										borderRadius: 12,
//										barThickness:18,
//									},
//									{
////										label:"prel",//IWI Cargo claim chart page changes 22052025
//										label:"Preliminary", //IWI Cargo claim chart page changes 22052025
//										data: formalPermonthResultData.fprxy_axis,
//										borderWidth: 1,
//										backgroundColor: '#ffbb00',
//										borderColor: '#ffbb00',
//										borderRadius: 12,
//										barThickness:18,
//									}
//								]
//							},
//							options: {
//								elements: {
//									point: {
//										radius: 2,
//										hoverRadius: 4,
//									}
//									},
//								tension:0.4,
//								plugins: {
//									datalabels: {
//										// rotation:90,
//										color: '#000',
//										// textAlign: 'top',
//										// anchor: 'end',
//										// align: 'end',
//										formatter: function (value, ctx) {
//											var index = ctx.dataIndex;
//											var label = ctx.chart.data.labels[index];
//											return  value;
//										},
//										font: {
//											size: 14,
//											weight: 500
//										},
//									},
//									legend: {
//										display:false,
//										position:'bottom',
//										labels: {
//											boxWidth: 14,
//											font: {
//											  size: 14,
//											}
//										  }
//									},
//								},
//								scales: {
//									x: {
//										stacked: true,
//										beginAtZero: true,
//										ticks: {
//											font: {
//												size: 10,
//												family:'Roboto'
//											}
//										},
//										title: {
//											display: true,
//											text: 'Claim aspects',
//											color: '#595959',
//											font: {
//												weight: '500',
//												size:12,
//												family:'Roboto'
//											},
//										}
//									},
//									y: {
//										stacked: true,
//										beginAtZero: true,
//										title: {
//											display: true,
//											text: 'Number of claims',
//											color: '#595959',
//											font: {
//												weight: '500',
//												color: '#000',
//												size:12,
//												family:'Roboto'
//											},
//										}
//									}
//								}
//							}
//						});
//						
//
//						// start chart | by iwi
//						const ctx27 = document.getElementById('myChart27');
//						new Chart(ctx27, {
//							type: 'bar',
//							data: {
//								labels: totalActiveRecordEveryMonthData.x_axis_month,
//								datasets: [{
////										label:"fpr", //IWI Cargo claim chart page changes 22052025
//										data: totalActiveRecordEveryMonthData.y_axis,
//										borderWidth: 1,
//										backgroundColor: '#4274be',
//										borderColor: '#4274be',
//										borderRadius: 12,
//										barThickness:18,
//									}
//								]
//							},
//							options: {
//								elements: {
//									point: {
//										radius: 2,
//										hoverRadius: 4,
//									}
//									},
//								tension:0.4,
//								plugins: {
//									datalabels: {
//										color: '#000',
//										formatter: function (value, ctx) {
//											var index = ctx.dataIndex;
//											var label = ctx.chart.data.labels[index];
//											return  value;
//										},
//										font: {
//											size: 14,
//											weight: 500
//										},
//									},
//									legend: {
//										display:false,
//										position:'bottom',
//										labels: {
//											boxWidth: 14,
//											font: {
//											  size: 14,
//											}
//										  }
//									},
//								},
//								scales: {
//									x: {
//										stacked: true,
//										beginAtZero: true,
//										ticks: {
//											font: {
//												size: 10,
//												family:'Roboto'
//											}
//										},
//										title: {
//											display: true,
//											text: '',
//											color: '#595959',
//											font: {
//												weight: '500',
//												size:12,
//												family:'Roboto'
//											},
//										}
//									},
//									y: {
//										stacked: true,
//										beginAtZero: true,
//										title: {
//											display: true,
//											text: 'Number of Record',
//											color: '#595959',
//											font: {
//												weight: '500',
//												color: '#000',
//												size:12,
//												family:'Roboto'
//											},
//										}
//									}
//								}
//							}
//						});
//						// end chart | by iwi
//						 
//                                                 
//                                                 
//                                                 
//                                                 
//
//						setTimeout(function(){
//							$(".simple-spinner-chart").css("display", "none");
//						}, 200);
//					});
//				
//				}
//                                IWI Air Cargo claim chart page new design end

				function Load_Record_Claim_Aspects_Record_Chart(startDate='', endDate='', filterStatus=''){
					$(".simple-spinner-chart").css("display", "block");
					$.post("/user/overview?action=Load_Claim_aspects_Record_Chart", {
						startDate: startDate,
						endDate: endDate,
						filterStatus: filterStatus
					}, function(x) {
						jQuery("#myChart5").remove();
						jQuery("#myChart5P").append('<canvas id="myChart5"></canvas>');

						jQuery("#myChart6").remove();
						jQuery("#myChart6P").append('<canvas id="myChart6"></canvas>');

						jQuery("#myChart7").remove();
						jQuery("#myChart7P").append('<canvas id="myChart7"></canvas>');

						var destClaimAspectsData = x.destClaimAspectsData;
						var orgClaimAspectsData = x.orgClaimAspectsData;
						var claimAspectsCountData = x.claimAspectsCountData;
					

						// Top 20 Claim aspects Count start
							const ctx5 = document.getElementById('myChart5');
							new Chart(ctx5, {
								type: 'line',
								data: {
									labels: claimAspectsCountData.x_axis,
									// labels: ['Damage', 'Total Loss', 'Partial Loss', 'Delay', 'Damage, Total Loss', 'Damage, Partial Loss','Pilferage','Damage,Total Loss,Partial Loss','Total Loss, Delay','Delay,Partial Loss','Damage, Partial Loss, Pilferage','Damage,Total Loss, Delay','Partial Loss, Pilferage' ],
									datasets: [{
											label:"",
											data: claimAspectsCountData.y_axis,
											// data: [119, 69, 45, 23, 10, 5,3,2,2,1,1,1,1],
											borderWidth: 2,
											backgroundColor: '#4274be',
											borderColor: '#4274be',
											// pointRedius:1,
										}]
								},
								options: {
									elements: {
										point: {
										radius: 2,
										hoverRadius: 4,
										}
									},
								tension:0.5,
									plugins: {
										datalabels: {
											color: '#000',
											textAlign: 'top',
											anchor: 'end',
											align: 'end',
											formatter: function (value, ctx) {
												var index = ctx.dataIndex;
												var label = ctx.chart.data.labels[index];
												return  value;
											}
										},
										legend: {
											display: false
										},
									},
									scales: {
										x: {
											beginAtZero: true,
											ticks: {
												font: {
													size: 10,
													family:'Roboto'
												}
											},
											title: {
												display: true,
												text: 'Claim aspects',
												color: '#595959',
												font: {
													weight: '500',
													size:12,
													family:'Roboto'
												},
											}
										},
										y: {
											beginAtZero: true,
											title: {
												display: true,
												text: 'Number of claims',
												color: '#595959',
												font: {
													weight: '500',
													color: '#000',
													size:12,
													family:'Roboto'
												},
											}
										}
									}
								}
							});
						// Top 20 Claim aspects Count end
					
					

						//Top 10 Claim aspects Origin start
							const ctx6 = document.getElementById('myChart6');
							new Chart(ctx6, {
								type: 'bar',
								data: {
									labels: orgClaimAspectsData.x_axis,
									// labels: ['MIA-damage', 'MIA-Total loss', 'MIA-Partial loss', 'SJU-Damage', 'MIA-Delay', 'ATL-Damage', 'SDQ-Partial loss', 'JFK-Damage', 'MIA-Damage, Total loss', 'SDQ-Damage', ],
									datasets: [{
											label: '',
											data: orgClaimAspectsData.y_axis,
											// data: [40, 35, 28, 20, 11, 10, 9, 8, 6, 6],
											borderWidth: 1,
											backgroundColor: '#4274be',
											borderColor: '#4274be',
											borderRadius: 8,
											barThickness:8,

										}]
								},
								options: {
									plugins: {
										datalabels: {
											rotation:90,
											color: '#000',
											textAlign: 'top',
											anchor: 'end',
											align: 'end',
											formatter: function (value, ctx) {
												var index = ctx.dataIndex;
												var label = ctx.chart.data.labels[index];
												return  value;
											}
										},
										legend: {
											display: false
										}
									},
									scales: {
										x: {
											beginAtZero: true,
											ticks: {
												font: {
													size: 10,
													family:'Roboto'
												}
											},
											
											title: {
												display: true,
												text: 'Claim aspects by station',
												color: '#595959',
												font: {
													family:'Roboto',
													size: '12px',
													// style: 'normal',
													// lineHeight: 1.2
													weight: '500',
													color: '#99999',
												},
											}
										},
										y: {
											beginAtZero: true,
											
											title: {
												display: true,
												text: 'Number of claims',
												color: '#595959',
												font: {
													// family: 'Calibri,Geneva,Arial,Helvetica,sans-serif',
													family:'Roboto',
													size: '12px',
													weight: '500',
													color: '#99999',
												},
											}
										}
									}



								}
							});
						//Top 10 Claim aspects Origin end

						
						//Top 10 Claim aspects Destination start
							const ctx7 = document.getElementById('myChart7');
							new Chart(ctx7, {
								type: 'bar',
								data: {
									labels: destClaimAspectsData.x_axis,
									datasets: [{
											label: '',
											data: destClaimAspectsData.y_axis,
											borderWidth: 1,
											backgroundColor: '#ffbb00',
											borderColor: '#ffbb00',
											borderRadius: 8,
											barThickness:8,

										}]
								},
								options: {
									plugins: {
										datalabels: {
											rotation:90,
											color: '#000',
											textAlign: 'top',
											anchor: 'end',
											align: 'end',
											formatter: function (value, ctx) {
												var index = ctx.dataIndex;
												var label = ctx.chart.data.labels[index];
												return  value;
											}
										},
										legend: {
											display: false
										}
									},
									scales: {
										x: {
											beginAtZero: true,
											ticks: {
												font: {
													size: 10,
													family:'Roboto'
												}
											},
											title: {
												display: true,
												text: 'Claim aspects by station',
												color: '#595959',
												font: {
													family:'Roboto',
													size: '12px',
													weight: '500',
												},
											}
										},
										y: {
											beginAtZero: true,
										
											title: {
												display: true,
												text: 'Number of claims',
												color: '#595959',
												font: {
													family:'Roboto',
													size: '12px',
													weight: '500',
												},
											}
										}
									}
								}
							});
						//Top 10 Claim aspects Destination end
				
						setTimeout(function(){
							$(".simple-spinner-chart").css("display", "none");
						}, 200);
					});
				
				}

				function Load_Record_Claim_Root_Cause_Record_Chart(startDate='', endDate='', filterStatus=''){
					$(".simple-spinner-chart").css("display", "block");
					$.post("/user/overview?action=Load_Claim_Root_Cause_Record_Chart", {
						startDate: startDate,
						endDate: endDate,
						filterStatus: filterStatus
					}, function(x) {
						jQuery("#myChart8").remove();
						jQuery("#myChart8P").append('<canvas id="myChart8"></canvas>');

						jQuery("#myChart9").remove();
						jQuery("#myChart9P").append('<canvas id="myChart9"></canvas>');

						jQuery("#myChart10").remove();
						jQuery("#myChart10P").append('<canvas id="myChart10"></canvas>');

						jQuery("#myChart11").remove();
						jQuery("#myChart11P").append('<canvas id="myChart11"></canvas>');

						var irregularityDestData = x.irregularityDestData;
						var irregularityOriginData = x.irregularityOriginData;
	
						var conclusionDestiData = x.conclusionDestiData;
						var conclusionOriginData = x.conclusionOriginData;

						// Top 10 irregularity by origin start
							
							const ctx8 = document.getElementById('myChart8');
							new Chart(ctx8, {
								type: 'bar',
								data: {
									labels: irregularityOriginData.x_axis,
									datasets: [{
											label: 'MIA',
											data: irregularityOriginData.y_axis,
											borderWidth: 1,
											backgroundColor: '#4274be',
											borderColor: '#4274be',
											borderRadius: 8,
											barThickness: 10, 
										}]
								},
								options: {
									plugins: {
										datalabels: {
											rotation:90,
											color: '#000',
											textAlign: 'top',
											anchor: 'end',
											align: 'end',
											formatter: function (value, ctx) {
												var index = ctx.dataIndex;
												var label = ctx.chart.data.labels[index];
												return  value;
											}
										},
										legend: {
											display: false
										}
									},
									scales: {
										x: {
											beginAtZero: true,
											// max:2.0,
											title: {
												display: false,
												text: '',
												color: '#000000',
												font: {
													weight: '600',
												},
											}
										},
										y: {
											beginAtZero: true,
											title: {
												display: false,
												text: 'Count',
												color: '#000000',
												font: {
													weight: '600',
													color: '#000',
												},
											}
										}
									}
								}
							});
						//Top 10 irregularity by origin end

						// Top 10 irregularity by destination start
						const ctx9 = document.getElementById('myChart9');

						new Chart(ctx9, {
							type: 'bar',
							data: {
								labels:  irregularityDestData.x_axis,
								datasets: [{
										label: '',
										data: irregularityDestData.y_axis,
										borderWidth: 1,
										backgroundColor: '#ffbb00',
										borderColor: '#ffbb00',
										borderRadius: 8,
										barThickness: 10, 
									}]
							},
							options: {
								plugins: {
									datalabels: {
										rotation:90,
										color: '#000',
										textAlign: 'top',
										anchor: 'end',
										align: 'end',
										formatter: function (value, ctx) {
											var index = ctx.dataIndex;
											var label = ctx.chart.data.labels[index];
											return  value;
										}
									},
									legend: {
										display: false
									}
								},
								scales: {
									x: {
										beginAtZero: true,
										title: {
											display: true,
											text: '',
											color: '#000000',
											font: {
												weight: '600',
											},
										}
									},
									y: {
										beginAtZero: true,
										// max:2.0,
										title: {
											display: true,
											text: 'Count',
											color: '#000000',
											font: {
												weight: '600',
												color: '#000',
											},
										}
									}
								}
							}
						});
						// Top 10 irregularity by destination end



						// Top-10 conclusion by origin
						const ctx10 = document.getElementById('myChart10');

						new Chart(ctx10, {
							type: 'line',
							data: {
								labels: conclusionOriginData.x_axis,
								datasets: [{
										label: '',
										data: conclusionOriginData.y_axis,
										borderWidth: 1,
										backgroundColor: '#ed7d31',
										borderColor: '#ed7d31',
									}]
							},
							options: {
								plugins: {
									datalabels: {
										color: '#000',
										textAlign: 'top',
										anchor: 'end',
										align: 'end',
										formatter: function (value, ctx) {
											var index = ctx.dataIndex;
											var label = ctx.chart.data.labels[index];
											return  value;
										}
									},
									legend: {
										display: false
									}
								},
								scales: {
									x: {
										beginAtZero: true,
										title: {
											display: true,
											text: '',
											color: '#000000',
											font: {
											weight: '600',
												color: '#99999',
											},
										}
									},
									y: {
										beginAtZero: true,
										title: {
											display: true,
											text: 'Count',
											color: '#000000',
											font: {
												weight: '600',
												color: '#99999',
											},
										}
									}
								}
							}
						});
						
						// Top-10 conclusion by destination
						const ctx11 = document.getElementById('myChart11');
		
						new Chart(ctx11, {
							type: 'line',
							data: {
								labels: conclusionDestiData.x_axis,
								datasets: [{
										label: '',
										data: conclusionDestiData.y_axis,
										borderWidth: 1,
										backgroundColor: '#ed7d31',
										borderColor: '#ed7d31',
									}]
							},
							options: {
								plugins: {
									datalabels: {
										color: '#000',
										textAlign: 'top',
										anchor: 'end',
										align: 'end',
										formatter: function (value, ctx) {
											var index = ctx.dataIndex;
											var label = ctx.chart.data.labels[index];
											return  value;
										}
									},
									legend: {
										display: false
									}
								},
								scales: {
									x: {
										beginAtZero: true,
										title: {
											display: true,
											text: '',
											color: '#000000',
											font: {
												weight: '600',
											},
										}
									},
									y: {
										beginAtZero: true,
										title: {
											display: true,
											text: 'Count',
											color: '#000000',
											font: {
												weight: '600',
												color: '#000',
											},
										}
									}
								}
							}
						});

						
						// 23112023 chart config end
						setTimeout(function(){
							$(".simple-spinner-chart").css("display", "none");
						}, 1500);
							
					});
				}

				function Load_Record_Claim_Financials_Record_Chart(startDate='', endDate='', filterStatus=''){

					$(".simple-spinner-chart").css("display", "block");
					$.post("/user/overview?action=Load_Claim_Financials_Record_Chart", {
						startDate: startDate,
						endDate: endDate,
						filterStatus: filterStatus
					}, function(x) {
						jQuery("#myChart12").remove();
						jQuery("#myChart12P").append('<canvas id="myChart12"></canvas>');

						jQuery("#myChart13").remove();
						jQuery("#myChart13P").append('<canvas id="myChart13"></canvas>');

						var originFinancialsClaimAmount = x.originFinancialsClaimAmount;
						var destinationFinancialsClaimAmount = x.destinationFinancialsClaimAmount;

						$("#claim_amount").text(x.totalClaimActivityData);
						$("#paid_amount").text(x.totalPaidAmountData);
						

						// Top 10 Origin Financials Start
							const ctx12 = document.getElementById('myChart12');
		
							new Chart(ctx12, {
								type: 'bar',
								data: {
									labels: originFinancialsClaimAmount.x_axis,
									datasets: [{
											label: ['Claim Amount'],
											data: originFinancialsClaimAmount.y_axis,
											borderWidth: 1,
											backgroundColor: ['#4274be', ],
											borderColor: ['#4274be', ],
											borderRadius: 10,
											barParcentage:0.25,
											categoryPercentage:0.4, 
										},
										{
											label: ['Paid Amount'],
											data: originFinancialsClaimAmount.z_axis,
											borderWidth: 1,
											backgroundColor: ['#ffbb00', ],
											borderColor: ['#ffbb00', ],
											borderRadius: 10,
											barParcentage:0.25,
											categoryPercentage:0.4, 
										}
									]
								},
								options: {
									plugins: {
                                                                            //IWI Cargo claim chart page changes 22052025 start
                                                                            legend: { 
                                                                                        display: false 
                                                                                    },
                                                                            //IWI Cargo claim chart page changes 22052025 end
										datalabels: {
											rotation:90,
											color: '#000',
											textAlign: 'top',
											anchor: 'end',
											align: 'end',
											formatter: function (value, ctx) {
												var index = ctx.dataIndex;
												var label = ctx.chart.data.labels[index];
												return  value;
											}
										}
									},
									scales: {
										x: {
											beginAtZero: true,
											title: {
												display: false,
												text: 'Station',
												color: '#000000',
												font: {
													weight: '600',
												},
											}
										},
										y: {
											beginAtZero: true,
											title: {
												display: false,
												text: 'Amount',
												color: '#000000',
												font: {
													weight: '600',
													color: '#000',
												},
											}
										}
									}
								},
								
							});
						// Top 10 Origin Financials End
						  
	
						// Top 10 Destination Financials start 
							const ctx13 = document.getElementById('myChart13');
							new Chart(ctx13, {
							type: 'bar',
							data: {
								labels: destinationFinancialsClaimAmount.x_axis,
								// labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
								datasets: [{
										label: 'Claim Amount',
										// data: [12, 19, 3, 5, 2, 3],
										data: destinationFinancialsClaimAmount.y_axis,
										borderWidth: 1,
										backgroundColor: ['#4274be', ],
										borderColor: ['#4274be', ],
										borderRadius: 10,
												barParcentage:0.25,
												categoryPercentage:0.4,  
									},
									{
										label: ['Paid Amount'],
										// data: [0, 3, 1, 2, ],
										data:destinationFinancialsClaimAmount.z_axis,
										borderWidth: 1,
										backgroundColor: ['#ffbb00', ],
										borderColor: ['#ffbb00', ],
										borderRadius: 10,
												barParcentage:0.25,
												categoryPercentage:0.4,  
									}
								]
							},
							options: {
								plugins: {
                                                                    //IWI Cargo claim chart page changes 22052025 start
                                                                            legend: { 
                                                                                        display: false 
                                                                                    },
                                                                            //IWI Cargo claim chart page changes 22052025 end
									datalabels: {
										color: '#000',
										rotation:90,
										textAlign: 'top',
										anchor: 'end',
										align: 'end',
										formatter: function (value, ctx) {
											var index = ctx.dataIndex;
											var label = ctx.chart.data.labels[index];
											return  value;
										}
									}
								},
								scales: {
									x: {
										beginAtZero: true,
										title: {
											display: false,
											text: 'Station',
											color: '#000000',
											font: {
												weight: '600',
											},
										}
									},
									y: {
										beginAtZero: true,
										title: {
											display: false,
											text: 'Amount',
											color: '#000000',
											font: {
												weight: '600',
												color: '#000',
											},
										}
									}
								}
							}
							});
						// Top 10 Destination Financials end
						
						// 23112023 chart config end
						setTimeout(function(){
							$(".simple-spinner-chart").css("display", "none");
						}, 800);
							
					});
				}

				function Load_Record_Claim_Service_Record_Chart(startDate='', endDate='', filterStatus=''){
					$(".simple-spinner-chart").css("display", "block");
					$.post("/user/overview?action=Load_Claim_Service_Level_Record_Chart", {
						startDate: startDate,
						endDate: endDate,
						filterStatus: filterStatus
					}, function(x) {

					jQuery("#myChart14").remove();
					jQuery("#myChart14P").append('<canvas id="myChart14"></canvas>');
					
					var serviceLevelData = x.serviceLevelData;

					// Top 10 Service Level start
					const ctx14 = document.getElementById('myChart14');
					new Chart(ctx14, {
						type: 'bar',
						data: {
							labels: serviceLevelData.x_axis,
							// labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
							datasets: [{
									label: '',
									axis: 'y',
									data: serviceLevelData.y_axis,
									// data: [12, 19, 3, 5, 2, 3],
									borderWidth: 1,
									backgroundColor: '#ffbb00',
									borderColor: '#ffbb00',
									borderRadius: 8,
									barThickness: 10,  // number (pixels) or 'flex'
									
								}]
						},
						options: {
							indexAxis: 'y',
							plugins: {
								datalabels: {
									color: '#000',
									textAlign: 'top',
									anchor: 'end',
									align: 'end',
									formatter: function (value, ctx) {
										var index = ctx.dataIndex;
										var label = ctx.chart.data.labels[index];
										return  value;
									}
								},
								legend: {
									display: false
								}
							},
							scales: {
								x: {
									beginAtZero: true,
									//max:2.0 , // Dynamic (extra + 2 ) 
									max: (Math.max(...serviceLevelData.y_axis) + 2),
									title: {
										display: false,
										text: 'Service Level',
										color: '#000000',
										font: {
											weight: '600',
										},
									}
								},
								y: {
									beginAtZero: true,
									title: {
										display: false,
										text: 'Number of Service Level',
										color: '#000000',
										font: {
											weight: '600',
											// color: '#999',
											// lineHeight: 1.2
										},
									}
								}
							}
						}
					});
					// Top 10 Service Level end
					// 23112023 chart config end
					setTimeout(function(){
						$(".simple-spinner-chart").css("display", "none");
					}, 800);
						
					});
				}

				function Load_Record_Claim_Activity_Record_Chart(startDate='', endDate='', filterStatus=''){
					$(".simple-spinner-chart").css("display", "block");
					$.post("/user/overview?action=Load_Claim_Claim_Activity_Record_Chart", {
						startDate: startDate,
						endDate: endDate,
						filterStatus: filterStatus
					}, function(x) {

					jQuery("#myChart15").remove();
					jQuery("#myChart15P").append('<canvas id="myChart15"></canvas>');

						// Top 10 Claim Activity Start
							const ctx15 = document.getElementById('myChart15');
							var claimActivityData = x.claimActivityData;
							new Chart(ctx15, {
								type: 'bar',
								data: {
									labels: claimActivityData.x_axis,
									datasets: [{
											label: '',
											data: claimActivityData.y_axis,
											borderWidth: 1,
											backgroundColor: '#4274be',
											borderColor: '#4274be',
											borderRadius: 8,
											barThickness: 30, 
										}]
								},
								options: {
									plugins: {
										datalabels: {
											rotation:90,
											color: '#000',
											textAlign: 'top',
											anchor: 'end',
											align: 'end',
											formatter: function (value, ctx) {
												var index = ctx.dataIndex;
												var label = ctx.chart.data.labels[index];
												return  value;
											}
										},
										legend: {
											display: false
										}
									},
									scales: {
										x: {
											beginAtZero: true,
											title: {
												display: false,
												text: 'Claim Activity',
												color: '#000000',
												font: {
													weight: '600',
												},
											}
										},
										y: {
											beginAtZero: true,
											title: {
												display: false,
												text: 'Number of Claim Activity',
												color: '#000000',
												font: {
													weight: '600',
													color: '#000',
												},
											}
										}
									}
								}
							});
						// Top 10 Claim Activity End
					
					setTimeout(function(){
						$(".simple-spinner-chart").css("display", "none");
					}, 800);
						
					});
				}
                                
                                
                                
//                              IWI Air Cargo claim chart page new design start   
                                
                                function check_Airline_Selected() {
                                    var airlines = self.config.groups;
                                    var dropdown = document.getElementById("AirlineDropdown");
                                    dropdown.innerHTML = '<option value="all" selected>All</option>';
                                    airlines.forEach(name => {
                                        if (name && name.trim() !== '') {
                                            var option = document.createElement("option");
                                            option.value = name;
                                            option.textContent = name;
                                            dropdown.appendChild(option);
                                        }
                                    });

                                }

                                document.getElementById("AirlineDropdown").addEventListener("change", function () {
                                    var savedAirline = this.value;
                                    var dropdown=document.getElementById("AirlineDropdown");
                                    if (savedAirline) {
                                        dropdown.value = savedAirline;
                                    }
                                    Load_Record_Claim_Type_Record_Chart_v2(startDate, endDate, filterStatus);
                                });

                            
                function Load_Record_Claim_Type_Record_Chart_v2(startDate = '', endDate = '', filterStatus = '') {
                var airlineDropdown = $("#AirlineDropdown").val();
                        $(".simple-spinner-chart").show();
                        $.post("/user/overview?action=Load_Claim_Type_Record_Chart_v2", {
                        startDate,
                                endDate,
                                filterStatus,
                                airlineDropdown
                        }, function (x) {
                            $("#Top_10_Preliminary_Claim").remove();
                            $("#Top_10_Preliminary_Claim_sec").append('<canvas id="Top_10_Preliminary_Claim"></canvas>');
                            $("#Top_10_Formal_Claim").remove();
                            $("#Top_10_Formal_Claim_sec").append('<canvas id="Top_10_Formal_Claim"></canvas>');
                            $("#Top_10_Claim_type").remove();
                            $("#Top_10_Claim_type_sec").append('<canvas id="Top_10_Claim_type"></canvas>');
                            $("#Open_Record_Claim").remove();
                            $("#Open_Record_Claim_sec").append('<canvas id="Open_Record_Claim"></canvas>');
                            $("#Top_10_Service_Level").remove();
                            $("#Top_10_Service_Level_sec").append('<canvas id="Top_10_Service_Level"></canvas>');
                            $("#Top_10_Claim_Activity").remove();
                            $("#Top_10_Claim_Activity_sec").append('<canvas id="Top_10_Claim_Activity"></canvas>');

                        const allMonths = [
                                "January", "February", "March", "April", "May", "June",
                                "July", "August", "September", "October", "November", "December"
                        ];
                        // -----------------------------
                        // Total counts
                        // -----------------------------
                        $("#total_preliminary_count").text(x.preliminary_total_count || 0);
                        $("#total_formal_count").text(x.formal_total_count || 0);
                        // -----------------------------
                        // Top-10 Claim Tables// -----------------------------
                        function renderTable(data, containerId, fieldName) {
                        let html = '';
                                for (let i = 0; i < 10; i++) {
                        const row = data[i] || {};
                                html += `
                                <tr>
                                    <td>${row[fieldName] || ''}</td>
                                    <td>${row.damage || 0}</td>
                                    <td>${row.total_loss || 0}</td>
                                    <td>${row.delay || 0}</td>
                                    <td>${row.partial_loss || 0}</td>
                                    <td>${row.pilferage || 0}</td>
                                </tr>`;
                        }
                        $(containerId).html(html);
                        }

                        renderTable(x.claimByOrigin, "#Top_10_Claim_By_Origin", "origin");
                        renderTable(x.claimByDestination, "#Top_10_Claim_By_Destination", "destination");
                        // -----------------------------
                        // Helper for months mapping  // -----------------------------
                        function mapMonthData(monthData) {
                        return allMonths.map(m => Number(monthData[m] || 0));
                        }
                        const prelimMonthData = mapMonthData(x.preliminaryPermonthResultData);
                        const formalMonthData = mapMonthData(x.formalPermonthResultData);
                        // ----------------------------- // Chart Renderer  // -----------------------------
                        function renderLineChart(canvasId, labels, datasets) {
                        const ctx = document.getElementById(canvasId).getContext('2d');
                                new Chart(ctx, {
                                type: 'line',
                                        data: { labels, datasets },
                                        options: {
  responsive: true,
  plugins: {
    legend: { display: false },
    tooltip: { enabled: true },
    // Hide datalabels if plugin is active
    datalabels: {
      display: false
    }
  },
  elements: {
    point: {
      radius: 4,
      hoverRadius: 6
    }
  },
  scales: {
    x: {
      grid: { display: false }
    },
    y: {
      beginAtZero: true,
      ticks: {
        precision: 0 // no decimal numbers
      }
    }
  }
}
                                });
                        }

                        // -----------------------------
                        // Top-10 Claim Charts (Preliminary & Formal)
                        // -----------------------------
                        function prepareTop10Chart(originLabels, destLabels, originData, destData) {
                        const allLabels = [...new Set([...originLabels, ...destLabels])];
                                const datasetOrigin = allLabels.map(lbl => Number(originData[lbl] || 0));
                                const datasetDest = allLabels.map(lbl => Number(destData[lbl] || 0));
                                return {
                                labels: allLabels,
                                        datasets: [
                                        { label: 'Origin', data: datasetOrigin, borderColor: '#4274be', fill: false, tension: 0.4, pointRadius: 4 },
                                        { label: 'Destination', data: datasetDest, borderColor: '#ffbb00', fill: false, tension: 0.4, pointRadius: 4 }
                                        ]
                                };
                        }

                    const prelimChartData = prepareTop10Chart(
                        x.preliminary_top_10_originlebel,
                        x.preliminary_top_10_destinationlebel,
                        x.preliminary_top_10_originDataset,
                        x.preliminary_top_10_destinationDataset
                        );
                        renderLineChart('Top_10_Preliminary_Claim', prelimChartData.labels, prelimChartData.datasets);
                        const formalChartData = prepareTop10Chart(
                                x.formal_top_10_originlebel,
                                x.formal_top_10_destinationlebel,
                                x.formal_top_10_originDataset,
                                x.formal_top_10_destinationDataset
                                );
                        renderLineChart('Top_10_Formal_Claim', formalChartData.labels, formalChartData.datasets);
                        // -----------------------------
                        // Claim Type Chart
                        // -----------------------------
                        const claimLabels = [];
                        const prelimClaim = [];
                        const formalClaim = [];
                        allMonths.forEach((m, idx) => {
                        const p = prelimMonthData[idx];
                                const f = formalMonthData[idx];
                                if (p || f) {
                        claimLabels.push(m);
                                prelimClaim.push(p);
                                formalClaim.push(f);
                        }
                        });
                        renderLineChart('Top_10_Claim_type', claimLabels, [
                        { label: 'Formal', data: formalClaim, borderColor: '#4274be', fill: false, tension: 0.4, pointRadius: 2 },
                        { label: 'Preliminary', data: prelimClaim, borderColor: '#ffbb00', fill: false, tension: 0.4, pointRadius: 2 }
                        ]);
                        // -----------------------------
                        // Open Record Chart
                        // -----------------------------
                        const formalOpen = mapMonthData(x.totalFormalActiveRecordEveryMonthData);
                        const prelimOpen = mapMonthData(x.totalPreActiveRecordEveryMonthData);
                        const openDatasets = [];
                        if (formalOpen.some(v => v > 0))
                        openDatasets.push({ label: 'Formal', data: formalOpen, borderColor: '#4274be', fill: false, tension: 0.4, pointRadius: 2 });
                        if (prelimOpen.some(v => v > 0))
                        openDatasets.push({ label: 'Preliminary', data: prelimOpen, borderColor: '#ffbb00', fill: false, tension: 0.4, pointRadius: 2 });
                        renderLineChart('Open_Record_Claim', allMonths, openDatasets);
                        // -----------------------------
                        // Service Level Chart
                        // -----------------------------
                        const slMap = {
                        '-1': 'Overdue',
                                '1': 'SLA 30',
                                '2': 'SLA 60',
                                '3': 'SLA 90',
                                '11': 'SLA 30 w/ extend',
                                '12': 'SLA 60 w/ extend',
                                '13': 'SLA 90 w/ extend'
                        };
                        const slLabels = x.serviceLevelResultData?.x_axis.map(l => slMap[l] || l) || [];
                        const slValues = x.serviceLevelResultData?.y_axis.map(Number) || [];
                        const maxSL = Math.max(...slValues) || 1;
                        const slTrend = slValues.map(v => Math.round(v / maxSL * 100));
                        renderLineChart('Top_10_Service_Level', slLabels, [
                        { label: 'Trend %', data: slTrend, borderColor: '#ffbb00', borderDash: [5, 5], fill: false, tension: 0.3, pointRadius: 4 }
                        ]);
                        // -----------------------------
                        // Claim Activity Chart
                        // -----------------------------
                        const activityLabels = x.claimActivityResultData?.status_key || [];
                        const activityValues = x.claimActivityResultData?.claim_count?.map(Number) || [];
                        renderLineChart('Top_10_Claim_Activity', activityLabels, [
                        { label: 'Claim Count', data: activityValues, borderColor: '#4274be', fill: false, tension: 0.3, pointRadius: 4 }
                        ]);
                        $(".simple-spinner-chart").fadeOut(200);
                    });
                }


 function Load_Record_Formal_Claim_Type_Record_Chart(startDate = '', endDate = '', filterStatus = '') {
    console.log('Load_Record_Formal_Claim_Type_Record_Chart');
    $(".simple-spinner-chart").show();

    var airlineDropdown = $("#AirlineDropdown").val();

    $.post("/user/overview?action=Load_Formal_Claim_Type_Record_Chart", {
        startDate,
        endDate,
        filterStatus,
        airlineDropdown
    }, function (x) {
        // Destroy existing charts
        if (window.formalTop10Chart) { window.formalTop10Chart.destroy(); window.formalTop10Chart = null; }
        if (window.formalClaimActivityChart) { window.formalClaimActivityChart.destroy(); window.formalClaimActivityChart = null; }
        if (window.formalLossDetailsChart) { window.formalLossDetailsChart.destroy(); window.formalLossDetailsChart = null; }
        if (window.formalSpecialHandlingChart) { window.formalSpecialHandlingChart.destroy(); window.formalSpecialHandlingChart = null; }
        if (window.openFormalChart) { window.openFormalChart.destroy(); window.openFormalChart = null; }
        if (window.closedFormalChart) { window.closedFormalChart.destroy(); window.closedFormalChart = null; }

        // Rebuild all canvas elements
        var chartMap = {
            formalClaimTab_Top_10_Formal_Claim_Sec: 'formalClaimTab_Top_10_Formal_Claim',
            formalClaimTab_Claim_Activity_Sec: 'formalClaimTab_Claim_Activity',
            formalClaimTab_Loss_Details_Sec: 'formalClaimTab_Loss_Details',
            formalClaimTab_Special_Handling_Codes_Sec: 'formalClaimTab_Special_Handling_Codes',
            formalClaimTab_Open_Formal_Claim_Record_Sec: 'formalClaimTab_Open_Formal_Claim_Record',
            formalClaimTab_Closed_Formal_Claim_Record_Sec: 'formalClaimTab_Closed_Formal_Claim_Record'
        };

        $.each(chartMap, function (secId, canvasId) {
            $("#" + canvasId).remove();
            $("#" + secId).append('<canvas id="' + canvasId + '" style="width:100%; height:250px;"></canvas>');
        });

        $("#formalClaimTab_total_formal_count").text(x.formal_total_count || 0);

        // Render charts after DOM updated
//        setTimeout(function () {
            renderFormalTop10Chart(x.formal_top_10_originDataset, x.formal_top_10_destinationDataset);
            renderFormalClaimActivityChart(x.formalClaimActivityResultData);
            renderFormalLossDetailsChart(x.totalFormalLossDetailsData);
            renderFormalSpecialHandlingChart(x.totalFormalSpecialHandlingCodesData);
            renderOpenFormalRecordChart(x.totalOpenFormalRecordsData);
            renderClosedFormalRecordChart(x.totalClosedFormalRecordsData);
//        }, 100);

        $(".simple-spinner-chart").fadeOut(200);
    });
}

// -------------------------- Top 10 Chart --------------------------
function renderFormalTop10Chart(formal_top_10_originDataset, formal_top_10_destinationDataset) {
//    console.log('renderFormalTop10Chart');
    var canvasId = 'formalClaimTab_Top_10_Formal_Claim';
    var allLabels = [];
    var originKeys = Object.keys(formal_top_10_originDataset);
    var destKeys = Object.keys(formal_top_10_destinationDataset);
    var combined = originKeys.concat(destKeys);

    for (var i = 0; i < combined.length; i++) {
        if (allLabels.indexOf(combined[i]) === -1) { allLabels.push(combined[i]); }
    }

    var originData = [];
    var destData = [];
    for (var j = 0; j < allLabels.length; j++) {
        var lbl = allLabels[j];
        originData.push(Number(formal_top_10_originDataset[lbl] || 0));
        destData.push(Number(formal_top_10_destinationDataset[lbl] || 0));
    }

    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    var ctx = canvas.getContext('2d');

    if (window.formalTop10Chart) window.formalTop10Chart.destroy();

    window.formalTop10Chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: allLabels,
            datasets: [
                { label: 'Origin', data: originData, borderColor: '#4274be', fill: false, tension: 0.4, pointRadius: 4 },
                { label: 'Destination', data: destData, borderColor: '#ffbb00', fill: false, tension: 0.4, pointRadius: 4 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: true },datalabels: { display: false } },
            scales: {
                x: { grid: { display: false }, title: { display: false } },
                y: { beginAtZero: true, title: { display: false }, ticks: { precision: 0 } }
            }
        }
        
    });
}

// -------------------------- Claim Activity --------------------------
 function renderFormalClaimActivityChart(formalClaimActivityResultData) {
//     console.log('renderFormalClaimActivityChart');
    var labels = formalClaimActivityResultData.status_key || [];
    var dataValues = (formalClaimActivityResultData.claim_count || []).map(Number);
    
//    console.log('labels',labels);

    var canvas = document.getElementById('formalClaimTab_Claim_Activity');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');

    // Destroy existing chart if any
    if (window.formalClaimActivityChart) {
      window.formalClaimActivityChart.destroy();
    }

    // Create gradient
    var gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(0,123,255,0.3)');
    gradient.addColorStop(1, 'rgba(0,123,255,0)');

    // Create Chart
    window.formalClaimActivityChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Claim Activity',
          data: dataValues,
          borderColor: '#007bff',
          backgroundColor: gradient,
          fill: true,
          borderWidth: 3,
          pointBackgroundColor: '#fff',
          pointBorderColor: '#007bff',
          pointBorderWidth: 2,
          pointHoverRadius: 7,          
          tension: 0.4, 
          pointRadius: 4 
        }]
      },
      options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: true },datalabels: { display: false } },
            scales: {
                x: { grid: { display: false }, title: { display: false } },
                y: { beginAtZero: true, title: { display: false }, ticks: { precision: 0 } }
            }
        }
    });
  }

// -------------------------- Loss Details --------------------------
function renderFormalLossDetailsChart(totalFormalLossDetailsData) {
    var canvas = document.getElementById('formalClaimTab_Loss_Details');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');

    if (window.formalLossDetailsChart) window.formalLossDetailsChart.destroy();

    var labels = Object.keys(totalFormalLossDetailsData);
    var dataValues = Object.values(totalFormalLossDetailsData).map(Number);

    var gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
    gradient.addColorStop(0, 'rgba(66,190,116,0.8)');
    gradient.addColorStop(1, 'rgba(66,190,116,0.2)');

    window.formalLossDetailsChart = new Chart(ctx, {
        type: 'bar',
        data: { labels: labels, datasets: [{ label: 'Claim Count', data: dataValues, backgroundColor: gradient, borderColor: '#42be74', borderWidth: 1, borderRadius: 8, maxBarThickness: 40 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, title: { display: true, text: 'Loss Details', color: '#333', font: { size: 15, weight: '600' }, padding: { bottom: 10 } }, tooltip: { backgroundColor: '#42be74', titleColor: '#fff', bodyColor: '#fff', displayColors: false, callbacks: { label: function(ctx){return ctx.label + ': ' + ctx.parsed.y.toLocaleString();} } },datalabels: { display: false } }, scales: { x: { grid: { display: false }, ticks: { color: '#555', font: { size: 12, weight: '500' }, maxRotation: 45, minRotation: 45 } }, y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { precision: 0, color: '#555', font: { size: 12 } } } }, animation: { duration: 800, easing: 'easeOutQuart' } }
    });
}

// -------------------------- Special Handling Codes --------------------------
function renderFormalSpecialHandlingChart(totalFormalSpecialHandlingCodesData) {
    var canvas = document.getElementById('formalClaimTab_Special_Handling_Codes');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');

    if (window.formalSpecialHandlingChart) window.formalSpecialHandlingChart.destroy();

    var entries = Object.entries(totalFormalSpecialHandlingCodesData)
        .sort(function(a, b) { return b[1] - a[1]; })
        .slice(0, 10);

    var labels = entries.map(function(e) { return e[0].toUpperCase(); });
    var dataValues = entries.map(function(e) { return e[1]; });
    var colors = ['#4e79a7','#f28e2b','#e15759','#76b7b2','#59a14f','#edc948','#b07aa1','#ff9da7','#9c755f','#bab0ab'];

    window.formalSpecialHandlingChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: dataValues,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 8,
                cutout: '70%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 12,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.label + ': ' + ctx.parsed.toLocaleString();
                        }
                    }
                },
                // ? hide datalabels plugin output
                datalabels: {
                    display: false
                },
                title: {
                    display: false,
                    text: 'Top 10 Special Handling Codes',
                    font: { size: 15, weight: '600' }
                }
            }
        }
    });
}



// -------------------------- Open Formal Records --------------------------
function renderOpenFormalRecordChart(totalOpenFormalRecordsData) {
    var monthLabels = [], monthData = [];
    for (var month in totalOpenFormalRecordsData) {
        if (totalOpenFormalRecordsData.hasOwnProperty(month)) {
            monthLabels.push(month);
            monthData.push(Number(totalOpenFormalRecordsData[month] || 0));
        }
    }

    var canvas = document.getElementById('formalClaimTab_Open_Formal_Claim_Record');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');

    if (window.openFormalChart) window.openFormalChart.destroy();

    var gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
    gradient.addColorStop(0, 'rgba(66,190,116,0.3)');
    gradient.addColorStop(1, 'rgba(66,190,116,0)');

    window.openFormalChart = new Chart(ctx, {
        type: 'line',
        data: { labels: monthLabels, datasets: [{ label: 'Open Formal Records', data: monthData, borderColor: '#4274be', backgroundColor: gradient, fill: true, tension: 0.4, pointRadius: 6, pointBackgroundColor: '#fff', pointBorderColor: '#4274be', pointBorderWidth: 2, pointHoverRadius: 8 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } ,datalabels: { display: false }}, scales: { x: { grid: { display: false }, ticks: { font: { size: 14, weight: '500' }, color: '#42be74' } }, y: { beginAtZero: true, ticks: { precision: 0, font: { size: 14, weight: '500' }, color: '#42be74' }, grid: { color: 'rgba(0,0,0,0.05)' } } } }
    });
}

// -------------------------- Closed Formal Records --------------------------
function renderClosedFormalRecordChart(totalClosedFormalRecordsData) {
    var monthLabels = [], monthData = [];
    for (var month in totalClosedFormalRecordsData) {
        if (totalClosedFormalRecordsData.hasOwnProperty(month)) {
            monthLabels.push(month);
            monthData.push(Number(totalClosedFormalRecordsData[month] || 0));
        }
    }

    var canvas = document.getElementById('formalClaimTab_Closed_Formal_Claim_Record');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');

    if (window.closedFormalChart) window.closedFormalChart.destroy();

    var gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
    gradient.addColorStop(0, 'rgba(255,77,79,0.3)');
    gradient.addColorStop(1, 'rgba(255,77,79,0)');

    window.closedFormalChart = new Chart(ctx, {
        type: 'line',
        data: { labels: monthLabels, datasets: [{ label: 'Closed Formal Records', data: monthData, borderColor: '#ff4d4f', backgroundColor: gradient, fill: true, tension: 0.4, pointBackgroundColor: '#fff', pointBorderColor: '#ff4d4f', pointBorderWidth: 2, pointHoverRadius: 8, pointRadius: function(context){ return 4 + (context.raw*2); } }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false },datalabels: { display: false } }, scales: { x: { grid: { display: false }, ticks: { font: { size: 14, weight: '500' }, color: '#4c4a4a' } }, y: { beginAtZero: true, ticks: { precision: 0, font: { size: 14, weight: '500' }, color: '#4c4a4a' }, grid: { color: 'rgba(0,0,0,0.05)' } } } }
    });
}

//            formalClaimTab_Closed_Formal_Claim_Record chart end


// IWI Air Cargo claim chart page new design end
				
				// awbs issue 	chart start
				$('.awbs-issued-div').click(function(){
					console.log('awbs-issued-div');

					action_Load_list_awbs_issue();
				});

				$('.add-awbs-issued').click(function(){
					
					var currentYear = new Date().getFullYear();
	                var getYearOption = '';
	                for(var i = 2005; i <= currentYear; i++){
	                    if(i == currentYear){
	                        getYearOption += '<option value="'+i+'" selected>'+i+'</option>';
	                    }else{
	                        getYearOption += '<option value="'+i+'">'+i+'</option>';
	                   }
	                }
	                $('.getYearAWbs').html(getYearOption);

	                var getMonthOption = '';
	                for(var i = 1; i <= 12; i++){
	                    getMonthOption += '<option value="'+i+'">'+i+'</option>';
	                }
	                $('.getMonthAWbs').html(getMonthOption);
				});

				$('.submit-awbs-issued').click(function(){
					
					var yearValue = $('.year').val();
					var monthValue = $('.month').val();
					var issueAwbsValue = $('.issue_awbs').val();
					if(issueAwbsValue < 1){
						alert("please enter issue awbs number");
						return false;
					}

					$(".simple-spinner-chart").css("display", "block");
					$.post("/issue/list?action=Load_save_awbs_issue", {
						yearValue: yearValue,
						monthValue: monthValue,
						issueAwbsValue: issueAwbsValue
					}, function(x) {
						if(x.status == false){
							alert(x.message);
							// $(".simple-spinner-chart").css("display", "none");
							// return false;
						}
						if(x.cargoclaimAwbsSave == true){
							alert('Awbs issued successfully');
							$("#addAwbsModal").css("display", "none");
							$("#addAwbsModal").removeClass("in");
							$(".modal-backdrop").css("display", "none");
						}
						
						$(".simple-spinner-chart").css("display", "none");
						action_Load_list_awbs_issue();
					}
					);
				});

				// create function for list awbs issued data call action_Load_list_awbs_issue
				function action_Load_list_awbs_issue(){
					$(".simple-spinner-chart").css("display", "block");
					$.post("/issue/list?action=Load_list_awbs_issue", {

					}, function(x) {
						$('.issuedAWbsHtml').html("");
						$.each(x.awbIssueResultData, function(key,val) {
				            $('.issuedAWbsHtml').append(
		        			  '<tr>' +
		                      '<td>' + val.year+' - '+val.month + '</td>' +
		                      '<td>' + val.awbs + '</td>' +
		                      '<td><a href="javascript:void(0)" data-id="'+val.id+'" class="edit-awbs-issued glyphicon glyphicon-edit"></a></td>' +
		                    '</tr>');
				        });
						
						$(".simple-spinner-chart").css("display", "none");
					}
					);
				}
				
				
				$(document).on('click', '.addAwbsIssuedModalClose', function(){ 
					$("#addAwbsModal").css("display", "none");
					$("#addAwbsModal").removeClass("in");
				});

				$(document).on('click', '.editAwbsIssuedModalClose', function(){ 
					$("#editAwbsModal").css("display", "none");
					$("#editAwbsModal").removeClass("in");
				});

				$(document).on('click', '.edit-awbs-issued', function(){ 
				    var currentYear = new Date().getFullYear();
	                var getYearOption = '';
	                for(var i = 2005; i <= currentYear; i++){
	                    if(i == currentYear){
	                        getYearOption += '<option value="'+i+'" selected>'+i+'</option>';
	                    }else{
	                        getYearOption += '<option value="'+i+'">'+i+'</option>';
	                   }
	                }
	                $('.editGetYearAWbs').html(getYearOption);

	                var getMonthOption = '';
	                for(var i = 1; i <= 12; i++){
	                    getMonthOption += '<option value="'+i+'">'+i+'</option>';
	                }
	                $('.editGetMonthAWbs').html(getMonthOption);
				    var issueId = $(this).attr('data-id');
				    // 
				    	$.post("/issue/list?action=Load_list_awbs_issue", {
							issueId: issueId,
						}, function(x) {
							$('.editGetYearAWbs').val(x.awbIssueResultData[0].year);
							$('.editGetMonthAWbs').val(x.awbIssueResultData[0].month_no);
							$('.editIssueAwbs').val(x.awbIssueResultData[0].awbs);
							$('.issue_awbs_id').val(x.awbIssueResultData[0].id);

						}
						);
				    
			    	$("#editAwbsModal").css("display", "block");
					$("#editAwbsModal").addClass("in");
				}); 


				$(document).on('click', '.edit-submit-awbs-issued', function(){ 
				    var editGetYearAWbs = $('.editGetYearAWbs').val();
					var editGetMonthAWbs = $('.editGetMonthAWbs').val();
					var issueAwbsValue = $('.editIssueAwbs').val();
					var issue_awbs_id = $('.issue_awbs_id').val();
					if(issueAwbsValue < 1){
						alert("please enter issue awbs number");
						return false;
					}
				    $.post("/issue/list?action=Load_save_awbs_issue", {
						yearValue: editGetYearAWbs,
						monthValue: editGetMonthAWbs,
						issueAwbsValue: issueAwbsValue,
						issue_awbs_id: issue_awbs_id,
					}, function(x) {
						if(x.status == false){
							alert(x.message);
							return false;
						}else{
							alert('Awbs issued Updated successfully');
							$("#editAwbsModal").css("display", "none");
							$("#editAwbsModal").removeClass("in");
						}
						action_Load_list_awbs_issue();
					});
				    // 


			    	$("#editAwbsModal").css("display", "block");
					$("#editAwbsModal").addClass("in");
				}); 
				// awbs issue 	chart end
				$('.aWbsIssuedInput').keyup(function(event){
					var value = $(this).val().toLowerCase();
					$(".aWbsIssuedTable tr").filter(function() {
					  $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
					});
				});





				
				//  07102024 Agent Report filter modify
				function Load_Record_Agent_Record_Chart(startDate='', endDate='',agentId='',originVal='',resetAction=false,reportStatus){
					$(".simple-spinner-chart").css("display", "block");
					jQuery("#myChart28").remove();
					jQuery("#myChart28P").append('<canvas id="myChart28"></canvas>');

					$.post("/user/overview?action=Load_Record_Agent_Record_Chart", {
						startDate: startDate,
						endDate: endDate,
						agentId: agentId,
						originVal: originVal,
						reportStatus: reportStatus,
					}, function(x) {
						if(resetAction == true){ //07102024 Agent Report filter modify
							var agent_id = x.agentId;
							var $select = $('#agent_name_list');
							$select.empty();
							var agentNameHtml = '<option value="">Select</option>';//27092024 airline cargo bug fixed
							$.each(x.agentNameDetails, function(index, item) {
								agentNameHtml += '<option value="' + item.agent_id + '" ' + (item.agent_id == agent_id ? 'selected' : '') + '>' + item.agent_name + '</option>';
							});
							$select.append(agentNameHtml);

							
							var $select = $('#agent_origin_list');
							$select.empty();
							var agentOriginHtml = '<option value="">Select</option>';
							$.each(x.claimTypeOrigin, function(index, item) {
								agentOriginHtml += '<option value="' + item + '" ' + (item == originVal ? 'selected' : '') + '>' + item + '</option>';
							});
							$select.append(agentOriginHtml);
						}
						// 25092024  added by iwi  agent chart section work
						
						var preliminaryClaimOriginValue = x.preliminaryclaimOriginValue;
						var preliminaryClaimByOrigin = '';
						$.each(preliminaryClaimOriginValue, function (key, val) {
							preliminaryClaimByOrigin += '<li><label>'+val.x_axis+'</label> <span>'+val.y_axis+'</span></li>';
						});
						$(".agentReportPreliminaryClaimByOrigin").html(preliminaryClaimByOrigin);


						var preliminaryClaimDestinationValue = x.preliminaryClaimDestinationValue;
						var preliminaryClaimByDestination = '';
						$.each(preliminaryClaimDestinationValue, function (key, val) {
							preliminaryClaimByDestination += '<li><label>'+val.x_axis+'</label> <span>'+val.y_axis+'</span></li>';
						});
						$(".agentReportPreliminaryClaimByDestination").html(preliminaryClaimByDestination);

						$("#agent_report_preliminary_count").text(x.totalPreliminaryResult);


						var formalClaimDestinationValue = x.formalclaimDestinationValue;
						var formalClaimDestinationValueHtml = '';
						$.each(formalClaimDestinationValue, function (key, val) {
							formalClaimDestinationValueHtml += '<li><label>'+val.x_axis+'</label> <span>'+val.y_axis+'</span></li>';
						});
						$(".agentReportFormalClaimDestinationValue").html(formalClaimDestinationValueHtml);


						var formalclaimOriginValue = x.formalClaimTypeByOriginValue;
						var formalclaimOriginValueHtml = '';
						$.each(formalclaimOriginValue, function (key, val) {
							formalclaimOriginValueHtml += '<li><label>'+val.x_axis+'</label> <span>'+val.y_axis+'</span></li>';
						});
						$(".agentReportFormalClaimOriginValue").html(formalclaimOriginValueHtml);
						$("#agent_report_formal_count").text(x.totalFormalResult);

						$("#agent_report_formal_claim_amount").text(x.formalClaimAmountValue);//07102024 Agent Report filter modify


						// set chart data 
						var hightSet = x.agentReport.x_axis.length > 4 ? x.agentReport.x_axis.length * 25 : 200 ; 

						jQuery("#myChart28P").height(hightSet);
						jQuery("#myChart28").height(hightSet);

						var totalYaxisTotalVal = x.agentReport.y_axis != null ?  x.agentReport.y_axis : 0;
						const totalYaxisSum = totalYaxisTotalVal.reduce((accumulator, currentValue) => {
							return parseInt(accumulator) + parseInt(currentValue);
						}, 0);
						$("#no_of_claims").text(totalYaxisSum);

						var agentReportResultData = x.agentReport;
						var agentReportResultMaxValue = Math.max.apply(null, agentReportResultData.y_axis.map(Number)) + 3;
						const ctx28 = document.getElementById('myChart28');
						new Chart(ctx28, {
							type: 'bar',
							data: {
								labels: agentReportResultData.x_axis,
								datasets: [{
									label: 'No of claims by agent',
									data: agentReportResultData.y_axis,
									backgroundColor: '#4274be',
									borderColor: '#4274be',
									borderRadius: 12,
									barThickness: 10, 
									datalabels: {    anchor: 'end',    align: 'end',    offset: 5}
								}]
							},
							options: {
								indexAxis: 'y',
								scales: {
									x: {
										beginAtZero: true,
										min: 0,
                						max: agentReportResultMaxValue
									},
									y: {
										stacked: true,
										beginAtZero: true,
										title: {
											display: true,
											font: {
												size:2
											},
										},
										ticks: {             
											autoSkip: false,                  
											maxRotation: 2,   
											minRotation: 0,
											stepSize: 20,
											//24092024 filter modify Air Cargo Claim chart
											callback: function(value, index, values) {
												let label = this.getLabelForValue(value);
												return label.length > 25 ? label.substring(0, 22) + '...' : label;
											}
											 
										},
									}
								},
								maintainAspectRatio: false, 
							}
						});
						// set chart data end
						setTimeout(function(){
							$(".simple-spinner-chart").css("display", "none");
						}, 500);
						
					});
				}

				

				$('.reportFilterType').change(function(){
					var filterType = $(this).val();
					if(filterType == 'dates'){
						$("#reportStartDate").jqxDateTimeInput({ width: '100%', height: '25px', theme: 'summer' });
						$("#reportEndDate").jqxDateTimeInput({ width: '100%', height: '25px', theme: 'summer' });

//						$('.report_start_end_date_div').css("display", "block"); //IWI Cargo claim chart page changes 22052025
						$('.report_start_end_date_div').css("display", "flex"); //IWI Cargo claim chart page changes 22052025
						$('.report_quarterly_date_div').css("display", "none");
					}else if(filterType == 'quarterly'){
						
						$('.report_quarterly_date_div').css("display", "block");
						$('.report_start_end_date_div').css("display", "none");
					}
					else{
						$('.report_start_end_date_div').css("display", "none");
						$('.report_quarterly_date_div').css("display", "none");
					}
				});
				
				$('.agent-report-div').click(function(){
					var currentYear = new Date().getFullYear();
					var getYearOption = '';
					for(var i = 2015; i <= currentYear; i++){
						if(i == currentYear){
							getYearOption += '<option value="'+i+'" selected>'+i+'</option>';
						}else{
							getYearOption += '<option value="'+i+'">'+i+'</option>';
						}
					}
					$('.reportGetYear').html(getYearOption);
					
					startDate = currentYear+'-01-01';
					endDate = currentYear+'-12-31';

					//$("#agent").css("display", "block"); //IWI Cargo claim chart page changes 22052025
					Load_Record_Agent_Record_Chart(startDate,endDate,'','',true); //07102024 Agent Report filter modify
					Set_Agent_Report_Chart_Year_Value();
				});

				$('.agentReportFilterSubmit').click(function(){
					var agentId = $('#agent_name_list').val();
					var originId = $('#agent_origin_list').val();
					var reportStatus = $('#agent_status_list').val();
					var dateVal = get_current_date();
					
					var filterReload = $(this).data('filterreload');
					if(filterReload == true){
						agentId = '';
						originId = '';
						reportStatus = '';
					}
					//  07102024 Agent Report filter modify
					Load_Record_Agent_Record_Chart(dateVal.startDate,dateVal.endDate,agentId,originId,filterReload,reportStatus);

					Set_Agent_Report_Chart_Year_Value();// 24092024 filter modify Air Cargo Claim chart
				});
				// 25092024  added by iwi  agent chart section work start

				// 07102024 Agent Report filter modify
				$('.agent-report-chart-excel-download').click(function(){
					var agentId = $('#agent_name_list').val();
					var originId = $('#agent_origin_list').val();
					var reportStatus = $('#agent_status_list').val();
					var dateVal = get_current_date();

					window.location.href = "/user/overview?action=Air_Cargo_Claim_Agent_Record_Export_In_Excel&agentId="+agentId+"&originVal="+originId+
					"&reportStatus="+reportStatus+"&startDate="+dateVal.startDate+"&endDate="+dateVal.endDate;
				});
				// 07102024 Agent Report filter modify end
				
				function Set_Agent_Report_Chart_Year_Value() {
					var filterType = document.querySelector('input.reportFilterType:checked').value;

					if(filterType == 'dates'){
						var reportStartDate = $("#reportStartDate").val();
						var reportEndDate = $("#reportEndDate").val();
						$("#agent_report_chart_year").text('Start Date : '+reportStartDate+' End Date : '+reportEndDate);
					}else if(filterType == 'quarterly'){
						var reportQuarterlyValue = document.querySelector('input.reportQuarterlyValue:checked').value;
						var reportGetYear = $("#agent .reportGetYear").val();
						$("#agent_report_chart_year").text('Year : '+reportGetYear+' - '+reportQuarterlyValue);
						
					}
					else{
						$("#agent_report_chart_year").text('Year : '+$("#agent .reportGetYear").val());
					}
				}

				// 25092024  added by iwi  agent chart section work end

				function get_current_date(){
					getYear = $('.reportGetYear').val();
					startDate = $('#reportStartDate').val();
					endDate = $('#reportEndDate').val();
					quarterlyValue = $('input[name="reportQuarterlyValue"]:checked').val();
					filterType = $('input[name="reportFilterType"]:checked').val();
					if(filterType == 'yearly'){
						startDate = getYear+'-01-01';
						endDate = getYear+'-12-31';
					}
					else if(filterType == 'quarterly'){
						if(quarterlyValue == 'Q1'){
							startDate = getYear+'-01-01';
							endDate = getYear+'-03-31';
						}
						else if(quarterlyValue == 'Q2'){
							startDate = getYear+'-04-01';
							endDate = getYear+'-06-30';
						}
						else if(quarterlyValue == 'Q3'){
							startDate = getYear+'-07-01';
							endDate = getYear+'-09-30';
						}
						else if(quarterlyValue == 'Q4'){
							startDate = getYear+'-10-01';
							endDate = getYear+'-12-31';
						}
					}
					else if(filterType == 'dates'){
						startDate = $('#reportStartDate').val();
						endDate = $('#reportEndDate').val();
					}
					else{
						startDate = '';
						endDate = '';
					}
					return { startDate: startDate, endDate: endDate };
				}

				$('.agentReportFilterReset').click(function(){
					var dateVal = get_current_date();
					Load_Record_Agent_Record_Chart(dateVal.startDate,dateVal.endDate,'','',true); //07102024 Agent Report filter modify
				});

				$('.agent-report-chart-download').click(function(){
					var HTML_Width = $(".canvas_div_pdf3.active").width();
					var HTML_Height = $(".canvas_div_pdf3.active").height();
					var top_left_margin = 15;
					var PDF_Width = HTML_Width+(top_left_margin*2);
					var PDF_Height = (PDF_Width*1.5)+(top_left_margin*2);
					var canvas_image_width = HTML_Width;
					var canvas_image_height = HTML_Height;
					
					var totalPDFPages = Math.ceil(HTML_Height/PDF_Height)-1;
					
					html2canvas($(".canvas_div_pdf3.active")[0],{allowTaint:true}).then(function(canvas) {
						canvas.getContext('2d');
						
						console.log(canvas.height+"  "+canvas.width);
						
						
						var imgData = canvas.toDataURL("image/jpeg", 1.0);
						var pdf = new jsPDF('p', 'pt',  [PDF_Width, PDF_Height]);
						pdf.addImage(imgData, 'JPG', top_left_margin, top_left_margin,canvas_image_width,canvas_image_height);
						
						
						for (var i = 1; i <= totalPDFPages; i++) { 
							pdf.addPage(PDF_Width, PDF_Height);
							pdf.addImage(imgData, 'JPG', top_left_margin, -(PDF_Height*i)+(top_left_margin*4),canvas_image_width,canvas_image_height);
						}
						
						pdf.save("agent-report.pdf");
					});
				});
				// 17092024 added by iwi end 


$('.formal-claim-type').trigger('click');  //IWI Aircargoclaim chart page update 12012026

		    });
			// 11122023 created by iwi |  end
			
			$("#cargoDamageChartViewSec").css("display", "none");
			$('#cargoDamageListChartBtn').click(function(){
				$("#listViewSec").css("display", "none");
				$("#cargoDamageChartViewSec").css("display", "block");
			});	


			/*################################			EXPITY REPORT ####################################################  */
			// 15052024 created by iwi |  start
			$("#trainingReportViewSec").css("display", "none");
			
			// 27092024 expiry report changes
			$('#training-report-button').click(function(){
				$('#training-report-button-text').text('Please wait...');
				$("#listViewSec").css("display", "none");
				$("#trainingReportViewSec").css("display", "block");
					
                Load_Training_Record_Listing(0);
			
			});	
                        
                        
            function includeJavaScript(file, callback) {
                var script = document.createElement('script');
                script.src = file;
                script.type = 'text/javascript';
                script.async = true;

                script.onload = function () {
                    console.log(`${file} has been loaded successfully.`);
                    if (callback)
                        callback();
                };

                script.onerror = function () {
                    console.error(`Error loading script: ${file}`);
                };

                document.head.appendChild(script);
            }
                                         
			if($('#trainingReportViewSec').length){
				$('head').append('<link rel="stylesheet" href="/css/default/components/bootstrap.min.css?v=2023.0" type="text/css" />');
			}

			function Load_Training_Record_Listing(params,person_name,training_name,training_from,training_to) {
				includeJavaScript('/js/components/datatable2/datatables.min.js', function () {
					(function ($) {
						$('#training-report-button-text').text('');
						$('<link rel="stylesheet" id="dtblCss" href="/js/components/datatable2/datatables.min.css" />').appendTo('head');
						if ($.fn.DataTable) {
							console.log('DataTable is available.');

							setTimeout(function () {
									$("#agent_report_chart_year").text('Year : '+new Date().getFullYear());
									trainingExpiryReportDataHtmlReinitialize();
									$('#trainingExpiryReportData').DataTable({
										layout: {
											topStart: {
												buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
											}
										},
										// "searching": false,
										"ajax": {
											"url": "/record/process?action=Load_Training_Record_Listing",
											"type": "POST",
											"data": function (d) {
												d.days = params; 
												d.person_name = person_name; 
												d.training_name = training_name; 
												d.training_from = training_from; 
												d.training_to = training_to;
											},
											"dataSrc": function (json) {
												return json.traineeManagementData;
											}
										},
										"columns": [
											{ "data": "cache_primary_name" },
											{ "data": "training_name" },
											{ "data": "training_result" },
											// { "data": "trainee_type" },
											{ "data": "training_date" },
											{ "data": "expiry_date_value" }
										]
										
									});
											
							}, 800); // Adjust the delay as needed
						} else {
							console.error('DataTable is not loaded. Check the script URL.');
						}
					})(jQuery.noConflict());
				});
				
				$('script[src="/js/components/datatable2/datatables.min.js"]').remove();
				$('#dtblCss').remove();                       
			}
			
			function Load_All_Training_Record_Listing(params,person_name,training_name,training_from,training_to) {
                            
				includeJavaScript('/js/components/datatable2/datatables.min.js', function () {
					(function ($) {
						$('<link rel="stylesheet" href="/js/components/datatable2/datatables.min.css" />').appendTo('head');
						if ($.fn.DataTable) {
							console.log('DataTable is available.');
							setTimeout(function () {		
									$('#trainingExpiryReportData').DataTable().clear().destroy();
									trainingExpiryReportDataHtmlReinitialize();
									$('#trainingExpiryReportData').DataTable({
									layout: {
											topStart: {
												buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
											}
										},
										// "searching": false,
										"ajax": {
											"url": "/record/process?action=load_all_training_report_data",
											"type": "POST",
											"data": function (d) {
												d.days = params; 
												d.person_name = person_name; 
												d.training_name = training_name; 
												d.training_from = training_from; 
												d.training_to = training_to;
											},
											"dataSrc": function (json) {
												return json.traineeManagementData;
											}
										},
										"columns": [
											{ "data": "cache_primary_name" },
											{ "data": "training_name" },
											{ "data": "training_result" },
											{ "data": "training_date" },
											{ "data": "expiry_date_value" }
										]
									});
							
							}, 800); // Adjust the delay as needed
						} else {
							console.error('DataTable is not loaded. Check the script URL.');
						}
					})(jQuery.noConflict());
				});
				
				$('script[src="/js/components/datatable2/datatables.min.js"]').remove();
				$('#dtblCss').remove();
			}

			function trainingExpiryReportDataHtmlReinitialize() {
				$('#trainingExpiryReportDataHtml').html('');
				var newDataHtml = `<table class="rastable" id="trainingExpiryReportData">
				<thead>
					<tr>
						<th>Trainee Name</th>
						<th>Training Name</th>
						<th>Training Result</th>
						<th>Training Start </th>
						<th>Training Expiry</th>
					</tr>
				</thead>
				</table>`;
				$('#trainingExpiryReportDataHtml').html(newDataHtml);
			}

			function trainingExpiryAdvanceReportDataHtmlReinitialize() {
				$('#trainingExpiryAdvanceReportDataHtml').html('');
				var newDataHtml = `<table class="rastable" id="trainingExpiryAdvanceReportData">
					<thead>
						<tr>
							<th>Trainee Name</th>
							<th>Training Name</th>
							<th>Training Result</th>
							<th>Trainee Type</th>
							<th>Training Start </th>
							<th>Training Expiry</th>
						</tr>
					</thead>
				</table>`;
				$('#trainingExpiryAdvanceReportDataHtml').html(newDataHtml);
			}

			$("#search_person_btn").on("click", function() {
				var value = $("#externalTrainingName").val().toLowerCase();
				$("#trainingReportViewSecData tr").filter(function() {
				$(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
				});
			});
			
			$(".report_user_type").on("click", function() {
				var report_type = $(this).attr('for');
				$('.report_user_type').removeClass('rut_active');
				$('[for='+report_type+']').addClass('rut_active');
				var report_type_days = $(".rtd_active").attr('for');
				$('.report_type_days').removeClass('rtd_active');
				$('[for='+report_type_days+']').addClass('rtd_active');

				if(report_type == 'internal'){
					Load_Training_Record_Listing(report_type_days);
				}else if(report_type == 'external'){
					Load_External_Training_Record_Listing(report_type_days);
				}else if(report_type == 'all'){
					Load_All_Training_Record_Listing(report_type_days);
				}
			});	
			
			$(".report_type_days").on("click", function() {
				var report_type_days =  $(this).attr('for');
				$('.report_type_days').removeClass('rtd_active');
				$('[for='+report_type_days+']').addClass('rtd_active');
				var report_type = $(".rut_active").attr('for');
				$('.report_user_type').removeClass('rut_active');
				$('[for='+report_type+']').addClass('rut_active');
				if(report_type == 'internal'){
					Load_Training_Record_Listing(report_type_days);

				}else if(report_type == 'external'){
					Load_External_Training_Record_Listing(report_type_days);
				}else if(report_type == 'all'){
					Load_All_Training_Record_Listing(report_type_days);
				}

			});	
			
			function Load_External_Training_Record_Listing(params,person_name,training_name,training_from,training_to) {
                            
				includeJavaScript('/js/components/datatable2/datatables.min.js', function () {
					(function ($) {
						$('<link rel="stylesheet" href="/js/components/datatable2/datatables.min.css" />').appendTo('head');
						if ($.fn.DataTable) {
							console.log('DataTable is available.');

							setTimeout(function () {
		
								$(".simple-spinner-chart").css("display", "block");
								trainingExpiryReportDataHtmlReinitialize();
								$('#trainingExpiryReportData').DataTable({
									// "searching": false,
									"ajax": {
										"url": "/record/process?action=Load_External_Training_Record_Listing", // Replace with your API endpoint
										"type": "POST",
										"data": function (d) {
											d.days = params; 
											d.person_name = person_name; 
											d.training_name = training_name; 
											d.training_from = training_from; 
											d.training_to = training_to;
										},
										"dataSrc": function (json) {
											return json.traineeManagementData;
										}
									},
									"columns": [
										{ "data": "cache_primary_name" },
										{ "data": "training_name" },
										{ "data": "training_result" },
										{ "data": "training_date" },
										{ "data": "expiry_date_value" }
									]
								});
							
							}, 800); // Adjust the delay as needed
						} else {
							console.error('DataTable is not loaded. Check the script URL.');
						}
					})(jQuery.noConflict());
				});
				
				$('script[src="/js/components/datatable2/datatables.min.js"]').remove();
				$('#dtblCss').remove();
			}
			
			
			$("#advanceFilterTrainingExpiry").on("click", function() {
				var report_type_days = $(".rtd_active").attr('for');
				var person_name = $("#person_name_list").val();
				var training_name = $("#training_name_list").val();
				var training_from = $("#training_from_date").val();
				var training_to = $("#training_to_date").val();
				var training_result = $("#training_result_list").val();
				var user_type = $("#training_user_list").val();

				var selectedOption = $("#person_name_list").find('option:selected');
    			var dataUserId = selectedOption.data('userid');
				console.log(" dataUserId ",dataUserId)
				Load_Advance_External_Training_Record_Listing(report_type_days,person_name,training_name,
					training_from,training_to,training_result,user_type,false,dataUserId);
			});

			$("#advanceFfilterTrainingExpiryReset").on("click", function() {
				
				$('#training_name_list').val('');
				$('#person_name_list').val('');
				$('#training_result_list').val('');
				$('#training_user_list').val('');
				$('#training_from_date').val('');
				$('#training_to_date').val('');
				Load_Advance_External_Training_Record_Listing(0,'','','','','','',true);

				// params,person_name,training_name,training_from,training_to,training_result,trainee_type,action_reset=false

				//#####################################################################################################################
				//#####################################################################################################################
				
				//#####################################################################################################################
				//#####################################################################################################################










			});
			
			$(".advance_expiry_report_div").click(function () {

				$(".simple-spinner-chart").css("display", "block");
				$.post("/record/process?action=Load_Advance_External_Training_Record_Listing", {
				// $.post("/issue/list?action=Load_Advance_External_Training_Record_Listing", {
					'days': 0
				}, function(x) {
					var $select = $('#person_name_list');
					$select.empty();
					var personNameHtml = '<option value="">Select</option>';
					var uniquePersonNames = {};
					// no training sectin work 03102024 
					$.each(x.traineeManagementData, function(index, item) {
						if (!uniquePersonNames[item.user_issue_id]) {
							uniquePersonNames[item.user_issue_id] = true;
							
							personNameHtml += '<option value="'+item.trainee_name+'" data-userId="'+item.user_issue_id+'" >' + item.trainee_name + '</option>';
						}
					});
					$select.append(personNameHtml);

					var $select = $('#training_name_list');
					$select.empty();
					var traineeNameHtml = '<option value="">Select</option>';
					var uniqueTrainingNames = {};
					$.each(x.traineeManagementData, function(index, item) {
						var trainingName = item.training_name; 
						if (!uniqueTrainingNames[trainingName]) {
							uniqueTrainingNames[trainingName] = true;
							traineeNameHtml += '<option value="'+trainingName+'">' + trainingName + '</option>';
						}
					});
					$select.append(traineeNameHtml);
				});
				//$('<link rel="stylesheet" href="/js/components/datatable2/datatables.min.css" />').appendTo('head');
				//$('<script type="text/javascript" charset="utf8" src="/js/components/datatable2/datatables.min.js"></script>').appendTo('head');
			   $(".simple-spinner-chart").css("display", "none");
			});
			$(".expiry_report_div").click(function () {
				Load_Training_Record_Listing(0);
				var report_type = 'internal';//$(this).attr('for');
				$('.report_user_type').removeClass('rut_active');
				$('[for='+report_type+']').addClass('rut_active');
				var report_type_days = 0;//$(".rtd_active").attr('for');
				$('.report_type_days').removeClass('rtd_active');
				$('[for='+report_type_days+']').addClass('rtd_active');
			});
			// no training sectin work 03102024 
			function Load_Advance_External_Training_Record_Listing(params,person_name,training_name,training_from,training_to,training_result,trainee_type,action_reset=false,user_issue_id=0){
				
				includeJavaScript('/js/components/datatable2/datatables.min.js', function () {
					(function ($) {
						$('<link rel="stylesheet" href="/js/components/datatable2/datatables.min.css" />').appendTo('head');
						if ($.fn.DataTable) {
							console.log('DataTable is available.');

							// Append DataTables CSS
							

							setTimeout(function () {
			
									$(".simple-spinner-chart").css("display", "block");
									$('#trainingExpiryAdvanceReportData').DataTable().clear().destroy(); 
									trainingExpiryAdvanceReportDataHtmlReinitialize();
									$('#trainingExpiryAdvanceReportData').DataTable({
									layout: {
										topStart: {
											buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
										}
									},
										// "searching": false,
										"ajax": {
											// "url": "/issue/list?action=Load_Advance_External_Training_Record_Listing", 
											"url": "/record/process?action=Load_Advance_External_Training_Record_Listing", 
											"type": "POST",
											"data": function (d) {
												d.days = params; 
												d.person_name = person_name; 
												d.training_name = training_name; 
												d.training_result = training_result; 
												d.trainee_type = trainee_type; 
												d.training_from = training_from; 
												d.training_to = training_to; 
												d.user_issue_id = user_issue_id; // no training sectin work 03102024 
												
											},
											"dataSrc": function (json) {
												// 03102024 added by iwi
												/*
													if(training_result == 3){
														$('#person_name_list').empty();
														var personNameHtml = '<option value="">Select</option>';
														$('#person_name_list').append(personNameHtml);

														$('#training_name_list').empty();
														var trainingNameHtml = '<option value="">Select</option>';
														$('#training_name_list').append(trainingNameHtml);
														
														$('#training_user_list').empty();
														var trainingUserHtml = '<option value="">Select</option>';
														$('#training_user_list').append(trainingUserHtml);
														
														$('#training_from_date').empty();
														$('#training_to_date').empty();
													}
												*/
													if(action_reset == true){
														var $select = $('#person_name_list');
														$select.empty();
														var personNameHtml = '<option value="">Select</option>';
														var uniquePersonNames = {};
														$.each(json.traineeManagementData, function(index, item) {
															if (!uniquePersonNames[item.trainee_name]) {
																uniquePersonNames[item.trainee_name] = true;
																// no training sectin work 03102024 
																personNameHtml += '<option value="'+item.trainee_name+'" ' + (item.trainee_name == person_name ? 'selected' : '') + '  data-userId="'+item.user_issue_id+'" >' + item.trainee_name + '</option>';
															}
														});
														$select.append(personNameHtml);

														var $select = $('#training_name_list');
														$select.empty();
														var traineeNameHtml = '<option value="">Select</option>';
														var uniqueTrainingNames = {};
														$.each(json.traineeManagementData, function(index, item) {
															if (!uniqueTrainingNames[item.training_name]) {
																uniqueTrainingNames[item.training_name] = true;
																traineeNameHtml += '<option value="'+item.training_name+'"  ' + (item.training_name == training_name ? 'selected' : '') + ' >' + item.training_name + '</option>';
															}
														});
														$select.append(traineeNameHtml);
													}
												

												return json.traineeManagementData;
											}
										},
										"columns": [
											{ "data": "cache_primary_name" },
											{ "data": "training_name" },
											{ "data": "training_result" },
											{ "data": "trainee_type" },
											{ "data": "training_date" },
											{ "data": "expiry_date_value" }
										]
									});
							
							}, 1000); // Adjust the delay as needed
						} else {
							console.error('DataTable is not loaded. Check the script URL.');
						}
					})(jQuery.noConflict());
				});
				
				$('script[src="/js/components/datatable2/datatables.min.js"]').remove();
				$('#dtblCss').remove();
			}

			// 27092024 expiry report changes end

			/*################################			EXPITY REPORT END  ####################################################  */
			// 27092024 expiry report changes
			// $('#trainingReportViewSecBtn > .cancel').click(function (e) {                
			// 	alert('Cancel');
		   	// });
			
			// 25102024 Audit control chart | start 

			// 08012024 Audit control report changes and delete code | start
			/*
			$('#auditControlListChartBtn').click(function(){
				$("#auditPlanViewSec").css("display", "none");//12112024 Audit control report changes
				setAuditControlDeadlineDate();
				$("#auditControlChartViewSec").css("display", "block");
				$("#audit-advance-report").css("display", "block");
				$("#listViewSec").css("display", "none");
				$("#audit-report-by-chart").css("display", "none");
				$('head').append('<link rel="stylesheet" href="/css/default/components/bootstrap.min.css?v=2023.0" type="text/css" />');
				Load_Advance_Audit_Control_Listing('active','','','','','','','','active');
			});
			function setAuditControlDeadlineDate(){
				flatpickr("#audit_control_deadline_date_rang", {
					mode: "range",
					enableTime: false,
					dateFormat: "Y-m-d",
					time_24hr: false
				});
				
				flatpickr("#audit_control_closing_date_rang", {
					mode: "range",
					enableTime: false,
					dateFormat: "Y-m-d",
					time_24hr: false
				});
			}

			$('#auditControlReportBtn').click(function(){
				$("#auditControlChartViewSec").css("display", "block");
				$("#audit-advance-report").css("display", "block");
				$("#listViewSec").css("display", "none");
				$("#audit-report-by-chart").css("display", "none");
				$('#audit_control_status').val('active').change();
				$('#audit_control_capa_status').val('active').change();
				Load_Advance_Audit_Control_Listing('active','','','','','','','','active');
			});

			$('#auditControlChartReportBtn').click(function(){
				$("#audit-advance-report").css("display", "none");
				$("#audit-report-by-chart").css("display", "block");
				$("#listViewSec").css("display", "none");

				$('#audit_control_chart_status').val('active').change();
				$('#audit_control_chart_capa_status').val('active').change();
				get_audit_control_chart_report('active','active');
			});
			
			//07112024  Audit control new changes start
			function get_audit_control_chart_report(status,capaStatus){
				jQuery("#myChart29").remove();
				jQuery("#myChart29P").append('<canvas id="myChart29"></canvas>');
				jQuery("#myChart30").remove();
				jQuery("#myChart30P").append('<canvas id="myChart30"></canvas>');

				jQuery("#myChart31").remove();
				jQuery("#myChart31P").append('<canvas id="myChart31"></canvas>');

				jQuery("#myChart32").remove();
				jQuery("#myChart32P").append('<canvas id="myChart32"></canvas>');
				
				$.post("/record/process?action=Load_Audit_Control_Chart_Report", {
					status: status,
					capaStatus: capaStatus,
				}, function(x) {
					// $('head').append('<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>');
					
					$("#auditControlChartTotalRecord").text(x.audit_activity_total); //07112024  Audit control new changes
					Chart.register(ChartDataLabels);
					var auditControlStatusReportData = x.statusReport;
					var auditControlStatusMaxValue = Math.max.apply(null, auditControlStatusReportData.y_axis.map(Number)) + 3;
					const ctx29 = document.getElementById('myChart29');
					new Chart(ctx29, {
						type: 'doughnut',//radar
						data: {
							labels: auditControlStatusReportData.x_axis,
							datasets: [{
								label: 'No of Status By Report',
								data: auditControlStatusReportData.y_axis,
								backgroundColor: [
									'rgb(95,147,233)',
									'rgb(77,202,235)',
								],
							}]
						},
						options: {
							maintainAspectRatio: false,
							plugins: {
								legend: {
									display: true,           // Show the legend
									position: 'bottom',       // Position it to the right
									labels: {
										boxWidth: 20,       // Size of the legend box
										padding: 15         // Padding around each legend item
									}
								},
								tooltip: {
									enabled: false
								},
								datalabels: {
									color: '#fff',
									align: 'center',
									formatter: (value, context) => {
										const label = context.chart.data.labels[context.dataIndex];
										return `${value}`;  
									},
									
								}
							}
						}
					});


					var auditControlRiskReportData = x.riskReport;
					var auditControlRiskMaxValue = Math.max.apply(null, auditControlRiskReportData.y_axis.map(Number)) + 3;
					const ctx30 = document.getElementById('myChart30');
					new Chart(ctx30, {
						type: 'doughnut',
						data: {
							labels: auditControlRiskReportData.x_axis,
							datasets: [{
								label: 'No of Risk By Report',
								data: auditControlRiskReportData.y_axis,
								// backgroundColor: '#4274be',
								backgroundColor: [
									'rgb(95,147,233)',
									'rgb(77,202,235)',
									'rgb(254,112,150)',
									'rgb(117,153,255)',
								],
							}]
						},
						options: {
							plugins: {
								// title: {
								// 	display: true,
								// 	text: 'No of Risk By Report',
								// 	font: {
								// 		size: 18,
								// 		weight: 'bold'
								// 	},
								// 	padding: {
								// 		top: 20,
								// 		bottom: 20
								// 	}
								// },
								legend: {
									display: true,           // Show the legend
									position: 'bottom',       // Position it to the right
									labels: {
										boxWidth: 20,       // Size of the legend box
										padding: 15         // Padding around each legend item
									}
								},
								tooltip: {
									enabled: false
								},
								datalabels: {
									color: '#fff',
									align: 'center',
									formatter: (value, context) => {
										const label = context.chart.data.labels[context.dataIndex];
										return `${value}`;  
									},
									
								}
							},
							maintainAspectRatio: false, 
						}
					});


					var auditControlTypeReportData = x.typeReport;
					var auditControlTypeMaxValue = Math.max.apply(null, auditControlTypeReportData.y_axis.map(Number)) + 3;
					const ctx31 = document.getElementById('myChart31');
					new Chart(ctx31, {
						type: 'doughnut',
						data: {
							labels: auditControlTypeReportData.x_axis,
							datasets: [{
								label: 'No of Type By Report',
								data: auditControlTypeReportData.y_axis,
								backgroundColor: [
									'rgb(77 202 235)',//07112024  Audit control new changes
									'rgb(75,124,235)',//07112024  Audit control new changes
									'rgb(254,112,150)',
									'rgb(117,153,255)',
									
								],
							}]
						},
						options: {
							plugins: {
								legend: {
									display: true,           // Show the legend
									position: 'bottom',       // Position it to the right
									labels: {
										boxWidth: 20,       // Size of the legend box
										padding: 15         // Padding around each legend item
									}
								},
								tooltip: {
									enabled: false
								},
								datalabels: {
									color: '#fff',
									align: 'center',
									formatter: (value, context) => {
										const label = context.chart.data.labels[context.dataIndex];
										return `${value}`;  
									},
									
								}
							},
							maintainAspectRatio: false, 
						}
					});
					

					var auditControlAreaReportData = x.areaReport;
					var auditControlAreaMaxValue = Math.max.apply(null, auditControlAreaReportData.y_axis.map(Number)) + 3;
					const ctx32 = document.getElementById('myChart32');
					new Chart(ctx32, {
						type: 'bar',
						data: {
							labels: auditControlAreaReportData.x_axis,
							datasets: [{
								label: 'No of Area By Report',
								data: auditControlAreaReportData.y_axis,
								backgroundColor: '#5f93e9',
								// borderColor: '#4274be',
								borderRadius: 12,
								barThickness: 10, 
								datalabels: {    anchor: 'end',    align: 'end',    offset: 5}
							}]
						},
						options: {
							indexAxis: 'y',
							scales: {
								x: {
									beginAtZero: true,
									min: 0,
									max: auditControlAreaMaxValue
								},
								y: {
									stacked: true,
									beginAtZero: true,
									title: {
										display: true,
										font: {
											size:2
										},
									},
									ticks: {             
										autoSkip: false,                  
										maxRotation: 2,   
										minRotation: 0,
										stepSize: 20,
										//24092024 filter modify Air Cargo Claim chart
										callback: function(value, index, values) {
											let label = this.getLabelForValue(value);
											return label.length > 25 ? label.substring(0, 22) + '...' : label;
										}
										
									},
								}
							},
							maintainAspectRatio: false, 
							plugins: {
							    legend: {
								    display: true,
								    position: 'bottom',  // Set the legend position to bottom
									color:'#000'
								},
								
								tooltip: {
								    enabled: false
								}
							    
							}
						}
					});
					// set chart data end
					setTimeout(function(){
						$(".simple-spinner-chart").css("display", "none");
					}, 500);
				});
			}

			$('#filterChartAuditControlSub').click(function(){
				var audit_control_chart_status = $("#audit_control_chart_status").val();
				var audit_control_chart_capa_status = $("#audit_control_chart_capa_status").val();
				get_audit_control_chart_report(audit_control_chart_status,audit_control_chart_capa_status);
			});
			
			$('#filterChartAuditControlReset').click(function(){
				$("#audit_control_chart_status").val('');
				$("#audit_control_chart_capa_status").val('');
				var audit_control_chart_status = '';
				var audit_control_chart_capa_status = '';
				get_audit_control_chart_report(audit_control_chart_status,audit_control_chart_capa_status);
			});
			//07112024  Audit control new changes end


			$('#auditControlChartDownload').click(function(){
				var HTML_Width = $(".audit_control_view_chart_sec").width();
				var HTML_Height = $(".audit_control_view_chart_sec").height();
				var top_left_margin = 15;
				var PDF_Width = HTML_Width+(top_left_margin*2);
				var PDF_Height = (PDF_Width*1.5)+(top_left_margin*2);
				var canvas_image_width = HTML_Width;
				var canvas_image_height = HTML_Height;
				
				var totalPDFPages = Math.ceil(HTML_Height/PDF_Height)-1;
				
				
				html2canvas($(".audit_control_view_chart_sec")[0], {
					allowTaint: true,
					backgroundColor: '#fff' // Ensuring background is white
				}).then(function(canvas) {
					canvas.getContext('2d');
					
					console.log(canvas.height + "  " + canvas.width);
					
					var imgData = canvas.toDataURL("image/jpeg", 1.0);
					var pdf = new jsPDF('p', 'pt', [PDF_Width, PDF_Height]);
				
					// Set the title text and center it
					var titleText = "Audit Report";
					pdf.setFontSize(16); // Adjust the font size as needed
					var textWidth = pdf.getTextWidth(titleText);
					var centerX = (PDF_Width - textWidth) / 2; // Calculate centered X position
					pdf.text(titleText, centerX, 40); // Add title centered at the top

				
					// Adjust image position to avoid overlap with title
					var imageTopMargin = 60; // Space below title
					pdf.addImage(imgData, 'JPG', top_left_margin, imageTopMargin, canvas_image_width, canvas_image_height);
					
					for (var i = 1; i <= totalPDFPages; i++) { 
						pdf.addPage(PDF_Width, PDF_Height);
						pdf.addImage(imgData, 'JPG', top_left_margin, -(PDF_Height * i) + imageTopMargin, canvas_image_width, canvas_image_height);
					}
					
					pdf.save("audit-report.pdf");
				});
			});

			// 05112024 Audit control changes by iwi | end 



			// 04112024 Audit control changes by iwi | start 
			$('#advanceFilterAuditControlReset').click(function(){
				Load_Advance_Audit_Control_Listing('','','','','','','','','');
				$("#audit_control_status").val('');
				$("#audit_control_deadline_date_rang").val('');
				$("#audit_control_closing_date_rang").val('');


				$("#audit_control_area").val('');
				$("#audit_control_risk").val('');
				$("#audit_control_type").val('');

				$("#audit_control_capa_status").val('');
			});
			  
			
			$('#advanceFilterAuditControl').click(function(){
				var status = $("#audit_control_status").val();

				var deadline_date_rang = $("#audit_control_deadline_date_rang").val();
				var deadline_date = deadline_date_rang.split(" to ");
				var deadline_from = deadline_date[0];
				var deadline_to = deadline_date[1];

				var closing_date_rang = $("#audit_control_closing_date_rang").val();
				var closing_date = closing_date_rang.split(" to ");
				var closing_from = closing_date[0];
				var closing_to = closing_date[1];
				var area = $("#audit_control_area").val();
				var risk = $("#audit_control_risk").val();
				var type = $("#audit_control_type").val();

				var capa_status = $("#audit_control_capa_status").val();
				Load_Advance_Audit_Control_Listing(status,deadline_from,deadline_to,closing_from,closing_to,area,risk,type,capa_status);
			});

			
			function Load_Advance_Audit_Control_Listing(status,deadline_from,deadline_to,closing_from,closing_to,area,risk,type,capa_status){ //07112024  Audit control new changes
				
				includeJavaScript('/js/components/datatable2/datatables.min.js', function () {
					(function ($) {
						$('<link rel="stylesheet" href="/js/components/datatable2/datatables.min.css" />').appendTo('head');
						if ($.fn.DataTable) {
							// Append DataTables CSS
							setTimeout(function () {
			
									$(".simple-spinner-chart").css("display", "block");
									$('#auditControlAdvanceReportData').DataTable().clear().destroy(); 
									auditControlReportDataHtmlReinitialize();
									$('#auditControlAdvanceReportData').DataTable({
									layout: {
										topStart: {
											buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
										}
									},
										"ajax": {
											"url": "/record/process?action=Load_Advance_Audit_Control_Listing", 
											"type": "POST",
											"data": function (d) {
												d.capa_status = capa_status; //07112024  Audit control new changes
												d.status = status; 
												d.deadline_from = deadline_from; 
												d.deadline_to = deadline_to; 
												d.closing_from = closing_from; 
												d.closing_to = closing_to; 
												d.area = area; 
												d.risk = risk; 
												d.type = type; 
											},
											"dataSrc": function (json) {
												return json.auditActivityData;
											}
										},
										"columns": [
											//12112024 Audit control report changes start
											// { "data": "reference_no" },
											{ "data": "id_val",width:'6%'},
											{ "data": "track",width:'7%'},
											{ "data": "title",width:'25%'},  //04112024 Audit control changes by iwi
											// { "data": "improvement",  
											// 	render: function(data, type, row) {
											// 		if (type === 'display' && data.length > 100) {
											// 			return data.substr(0, 100) + '...';
											// 		}
											// 		return data;
											// 	}
											// }, 
											
											{ "data": "result",width:'8%' },
											{ "data": "risk",width:'8%' },
											{ "data": "type",width:'8%' },
											{ "data": "status",width:'8%' },
											{ "data": "deadline_date",width:'15%'  },
											{ "data": "closing_date",width:'14%'  },
											//12112024 Audit control report changes end
										]
										
									});
									$(".simple-spinner-chart").css("display", "none");
							}, 100); // Adjust the delay as needed
						} else {
							console.error('DataTable is not loaded. Check the script URL.');
						}
					})(jQuery.noConflict());
				});
				
				$('script[src="/js/components/datatable2/datatables.min.js"]').remove();
				$('#dtblCss').remove();
			}
			      
			function auditControlReportDataHtmlReinitialize() {
				$('#auditControlAdvanceReportDataHtml').html('');
				// 12112024 Audit control report changes start
				var newDataHtml = `<table class="rastable" id="auditControlAdvanceReportData">
					<thead>
						<tr>
							<th>ID</th>
							<th style="width: 200px;">Track</th>
							<th>Title</th>
							<th>Area</th>
							<th>Risk</th>
							<th>Type</th>
							<th>Result</th>
							<th>Deadline Date</th>
							<th>Closing Date</th>
						</tr>
					</thead>
				</table>`;
				// 12112024 Audit control report changes end
				$('#auditControlAdvanceReportDataHtml').html(newDataHtml);
			}
			*/
			//04112024 Audit control changes by iwi  | end   
			// 08012024 Audit control report changes and delete code | end

		  	



			

			// 03122024  Capa Report Chart by iwi | start 
			if ($('#caPaReportListChartBtn').length) {
				// 20012025 capa report | start
					function getQueryParam(param) {
						const urlParams = new URLSearchParams(window.location.search);
						return urlParams.get(param) || 'Parameter not found'; // Return default if not found
					}
					$(document).ready(function () {
						const paramName = 'report';
						const value = getQueryParam(paramName);
						console.log(`The value of `,value);
						if(value == 'capa'){
							var sidewidth = $('.site').width() > 0 ? $('.site').width() : 1140 ;
							$('#caPaReportViewSec').addClass('capa-report-'+sidewidth);
							$("#caPaReportViewSec").css("display", "none");
							$("#auditControlChartViewSec").css("display", "none");
							$("#auditPlanViewSec").css("display", "none");
							setCaPaReportDeadlineDate();
							$("#caPaReportViewSec").css("display", "block");
							$("#audit-advance-report").css("display", "block");
							$("#listViewSec").css("display", "none");
							$("#audit-report-by-chart").css("display", "none");
							$('head').append('<link rel="stylesheet" href="/css/default/components/bootstrap.min.css?v=2023.0" type="text/css" />');
							Load_Advance_Capa_Report_Listing('','','','','','','','','active');
						}
					});
					// 20012025 capa report | end
				$('#caPaReportListChartBtn').click(function(){
					
					// 10012025 add class capa report start
					var sidewidth = $('.site').width() > 0 ? $('.site').width() : 1140 ;
					$('#caPaReportViewSec').addClass('capa-report-'+sidewidth);
					// 10012025 add class capa report end



					$("#caPaReportViewSec").css("display", "none");
					$("#auditControlChartViewSec").css("display", "none");
					$("#auditPlanViewSec").css("display", "none");// 08012024 Audit control report changes
				
					setCaPaReportDeadlineDate();
			
					
					$("#caPaReportViewSec").css("display", "block");
					$("#audit-advance-report").css("display", "block");
					$("#listViewSec").css("display", "none");
					$("#audit-report-by-chart").css("display", "none");
					$('head').append('<link rel="stylesheet" href="/css/default/components/bootstrap.min.css?v=2023.0" type="text/css" />');

					
					Load_Advance_Capa_Report_Listing('','','','','','','','','active');// 08012024 Audit control report changes
				});
				$('#advanceFilterCaPaReportReset').click(function(){
					var status = '';//$(".capa_report_status.active_report").attr("for");// 06012025 changes report section
					var capa_status = $(".capa_report_capa_status.active_report").attr("for");
					Load_Advance_Capa_Report_Listing(status,'','','','','','','',capa_status,'');
					// $("#capa_report_status").val('');31122024 capa report changes
					// $('input[name="capa_report_status"]:checked').val('');
					// $("#capa_report_deadline_date_rang").val('');//31122024 capa report changes start
					// $("#capa_report_closing_date_rang").val('');//31122024 capa report changes start
				
					$("#capa_report_area").val('');
					$("#capa_report_risk").val('');
					$("#capa_report_type").val('');
				
					$("#capa_report_capa_status").val('');
					$("#capa_report_type_value").val('');
				});

				$('.caPaReportBtn').click(function(){//14012025 capa report filter changes
					$('.caPaReportBtn').addClass('active');//14012025 capa report filter changes
					$('.caPaChartReportBtn').removeClass('active');//14012025 capa report filter changes


					$("#auditControlChartViewSec").css("display", "none");
					$("#caPaReportViewSec").css("display", "block");
					$("#caPa-advance-report").css("display", "block");
					$("#listViewSec").css("display", "none");
					$("#caPa-report-by-chart").css("display", "none");

					$('#audit_control_status').val('active').change();
					$('#audit_control_capa_status').val('active').change();
					// Load_Advance_Audit_Control_Listing('active','','','','','','','','active');
					
					Load_Advance_Capa_Report_Listing('','','','','','','','','active');// 06012025 capa report changes 
				});

				$('.caPaChartReportBtn').click(function(){//14012025 capa report filter changes
					$('.caPaReportBtn').removeClass('active');//14012025 capa report filter changes
					$('.caPaChartReportBtn').addClass('active');//14012025 capa report filter changes

					$("#caPa-advance-report").css("display", "none");
					$("#caPa-report-by-chart").css("display", "block");
					$("#listViewSec").css("display", "none");

					$('#capa_chart_status').val('active').change();
					$('#capa_chart_capa_status').val('active').change();
					get_capa_audit_control_chart_report('active','active');
				});

				// 06012025 capa report changes start
				$('.capa_report_capa_status').on('click', function () {
					$(".capa_report_capa_status").removeClass("active_report close_btn");

					if ($(this).attr("for") === "closed") {
						$(this).addClass("active_report");
						$(".capa_report_capa_status[for='active']").addClass("close_btn");
					} else {
						$(this).addClass("active_report");
						$(".capa_report_capa_status[for='closed']").addClass("close_btn");
					}

					var status = '';//
					var deadline_from = '';
					var deadline_to = '';
					var closing_from = '';
					var closing_to = '';

					var area = $("#capa_report_area").val();
					var risk = $("#capa_report_risk").val();
					var type = $("#capa_report_type").val();
					var capa_status =$(this).attr("for");// $("#capa_report_capa_status").val();

					var report_type = $("#capa_report_type_value").val();
					Load_Advance_Capa_Report_Listing(status,deadline_from,deadline_to,closing_from,closing_to,area,risk,type,capa_status,report_type);
				
				});
				// 06012025 capa report changes end
				
				$('#advanceFilterCaPaReport').click(function(){
					// var status = $("#capa_report_status").val();
					var status = '';//$(".capa_report_status.active_report").attr("for");// 06012025 changes report section
					// 31122024 capa report changes start
					// var deadline_date_rang = $("#capa_report_deadline_date_rang").val();
					// var deadline_date = deadline_date_rang.split(" to ");
					// var deadline_from = deadline_date[0];
					// var deadline_to = deadline_date[1];
					var deadline_from = '';
					var deadline_to = '';
				
					// var closing_date_rang = $("#capa_report_closing_date_rang").val();
					// var closing_date = closing_date_rang.split(" to ");
					// var closing_from = closing_date[0];
					// var closing_to = closing_date[1];
					var closing_from = '';
					var closing_to = '';
					// 31122024 capa report changes end
				
					var area = $("#capa_report_area").val();
					var risk = $("#capa_report_risk").val();
					var type = $("#capa_report_type").val();
					var capa_status = $(".capa_report_capa_status.active_report").attr("for");// $("#capa_report_capa_status").val(); // 06012025 changes report section

					var report_type = $("#capa_report_type_value").val();
					Load_Advance_Capa_Report_Listing(status,deadline_from,deadline_to,closing_from,closing_to,area,risk,type,capa_status,report_type);
				
				});


				$('#filterChartCaPaReportSub').click(function(){
					var capa_chart_status = $("#capa_chart_status").val();
					var capa_chart_capa_status = $("#capa_chart_capa_status").val();
					var capa_chart_report_type = $("#capa_chart_report_type").val();
					get_capa_audit_control_chart_report(capa_chart_status,capa_chart_capa_status,capa_chart_report_type);
				});
				
				$('#filterChartCaPaReportReset').click(function(){
					$("#capa_chart_status").val('');
					$("#capa_chart_capa_status").val('');
					$("#capa_chart_report_type").val('');
					var capa_chart_status = '';
					var capa_chart_capa_status = '';
					var capa_chart_report_type = '';
					get_capa_audit_control_chart_report(capa_chart_status,capa_chart_capa_status,capa_chart_report_type);
				});


				$('#caPaReportChartDownload').click(function(){
					var HTML_Width = $(".caPa_report_view_chart_sec").width();
					var HTML_Height = $(".caPa_report_view_chart_sec").height();
					var top_left_margin = 15;
					var PDF_Width = HTML_Width+(top_left_margin*2);
					var PDF_Height = (PDF_Width*1.5)+(top_left_margin*2);
					var canvas_image_width = HTML_Width;
					var canvas_image_height = HTML_Height;
					
					var totalPDFPages = Math.ceil(HTML_Height/PDF_Height)-1;
					
					
					html2canvas($(".caPa_report_view_chart_sec")[0], {
						allowTaint: true,
						backgroundColor: '#fff' // Ensuring background is white
					}).then(function(canvas) {
						canvas.getContext('2d');
						
						console.log(canvas.height + "  " + canvas.width);
						
						var imgData = canvas.toDataURL("image/jpeg", 1.0);
						var pdf = new jsPDF('p', 'pt', [PDF_Width, PDF_Height]);
					
						// Set the title text and center it
						var titleText = "CaPa Report";
						pdf.setFontSize(16); // Adjust the font size as needed
						var textWidth = pdf.getTextWidth(titleText);
						var centerX = (PDF_Width - textWidth) / 2; // Calculate centered X position
						pdf.text(titleText, centerX, 40); // Add title centered at the top

					
						// Adjust image position to avoid overlap with title
						var imageTopMargin = 60; // Space below title
						pdf.addImage(imgData, 'JPG', top_left_margin, imageTopMargin, canvas_image_width, canvas_image_height);
						
						for (var i = 1; i <= totalPDFPages; i++) { 
							pdf.addPage(PDF_Width, PDF_Height);
							pdf.addImage(imgData, 'JPG', top_left_margin, -(PDF_Height * i) + imageTopMargin, canvas_image_width, canvas_image_height);
						}
						
						pdf.save("capa-report.pdf");
					});
				});
			

				function setCaPaReportDeadlineDate(){
					flatpickr("#capa_report_deadline_date_rang", {
						mode: "range",
						enableTime: false,
						dateFormat: "Y-m-d",
						time_24hr: false
					});
					
					flatpickr("#capa_report_closing_date_rang", {
						mode: "range",
						enableTime: false,
						dateFormat: "Y-m-d",
						time_24hr: false
					});
				}

				function Load_Advance_Capa_Report_Listing(status,deadline_from,deadline_to,closing_from,closing_to,area,risk,type,capa_status,report_type){
					
					includeJavaScript('/js/components/datatable2/datatables.min.js', function () {
						(function ($) {
							$('<link rel="stylesheet" href="/js/components/datatable2/datatables.min.css" />').appendTo('head');
							if ($.fn.DataTable) {
								// Append DataTables CSS
								setTimeout(function () {
				
										$(".simple-spinner-chart").css("display", "block");
										$('#caPaReportAdvanceReportData').DataTable().clear().destroy(); 
										caPaReportDataHtmlReinitialize();
										let capaReportTable = $('#caPaReportAdvanceReportData').DataTable({
										layout: {
											topStart: {
												buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
											}
										},
											"ajax": {
												"url": "/record/process?action=Load_Advance_CaPa_Report_Listing", 
												"type": "POST",
												"data": function (d) {
													d.report_type = report_type;
													d.capa_status = capa_status;
													d.status = status; 
													d.deadline_from = deadline_from; 
													d.deadline_to = deadline_to; 
													d.closing_from = closing_from; 
													d.closing_to = closing_to; 
													d.area = area; 
													d.risk = risk; 
													d.type = type; 
												},
												"dataSrc": function (json) {
													return json.auditActivityData;
												}
											},
											"columns": [
												{ "data": "id_val",width:'6%'},
												{ "data": "track",width:'7%'},
												{ "data": "title",width:'25%'}, 
												{ "data": "result",width:'8%' },
												{ "data": "risk",width:'8%' },
												{ "data": "type",width:'8%' },
												{ "data": "report_type",width:'8%' },
												{ "data": "status",width:'8%' },
												{ "data": "deadline_date",width:'15%'  },
												{ "data": "closing_date",width:'14%'  },
											],
											"initComplete": function(settings, json) {
												let status = json.auditActivityData[0]?.status || '';
												if (status === 'Active') {
													capaReportTable.column(9).visible(false);
												} else {
													capaReportTable.column(9).visible(true);
												}
											}
											
										});
										$(".simple-spinner-chart").css("display", "none");
								}, 100); // Adjust the delay as needed
							} else {
								console.error('DataTable is not loaded. Check the script URL.');
							}
						})(jQuery.noConflict());
					});
					
					$('script[src="/js/components/datatable2/datatables.min.js"]').remove();
					$('#dtblCss').remove();
				}

				function caPaReportDataHtmlReinitialize() {
					// 06012025 changes  grid changes
					$('#caPaReportAdvanceReportDataHtml').html('');
					var newDataHtml = `<table class="rastable" id="caPaReportAdvanceReportData">
						<thead>
							<tr>
								<th>ID</th>
								<th style="width: 200px;">Track</th>
								<th>Title</th>
								<th>Area</th>
								<th>Risk</th>
								<th>Type</th>
								<th>Report Type</th>
								<th>Status</th>
								<th>Deadline Date</th>
								<th>Closing Date</th>
							</tr>
						</thead>
					</table>`;
					$('#caPaReportAdvanceReportDataHtml').html(newDataHtml);
				}

				function get_capa_audit_control_chart_report(status,capaStatus,reportType){
					jQuery("#myChart33").remove();
					jQuery("#myChart33P").append('<canvas id="myChart33"></canvas>');
					jQuery("#myChart34").remove();
					jQuery("#myChart34P").append('<canvas id="myChart34"></canvas>');

					jQuery("#myChart35").remove();
					jQuery("#myChart35P").append('<canvas id="myChart35"></canvas>');

					jQuery("#myChart36").remove();
					jQuery("#myChart36P").append('<canvas id="myChart36"></canvas>');
					
					$.post("/record/process?action=Load_CaPa_Report_Chart_Report", {
						status: status,
						capaStatus: capaStatus,
						report_type: reportType,
					}, function(x) {
						$("#caPaReportChartTotalRecord").text(x.audit_activity_total);
						Chart.register(ChartDataLabels);
						var auditControlStatusReportData = x.statusReport;
						var auditControlStatusMaxValue = Math.max.apply(null, auditControlStatusReportData.y_axis.map(Number)) + 3;
						const ctx33 = document.getElementById('myChart33');
						new Chart(ctx33, {
							type: 'doughnut',//radar
							data: {
								labels: auditControlStatusReportData.x_axis,
								datasets: [{
									label: 'No of Status By Report',
									data: auditControlStatusReportData.y_axis,
									backgroundColor: [
										'rgb(95,147,233)',
										'rgb(77,202,235)',
									],
								}]
							},
							options: {
								maintainAspectRatio: false,
								plugins: {
									legend: {
										display: true,           // Show the legend
										position: 'bottom',       // Position it to the right
										labels: {
											boxWidth: 20,       // Size of the legend box
											padding: 15         // Padding around each legend item
										}
									},
									tooltip: {
										enabled: false
									},
									datalabels: {
										color: '#fff',
										align: 'center',
										formatter: (value, context) => {
											const label = context.chart.data.labels[context.dataIndex];
											return `${value}`;  
										},
										
									}
								}
							}
						});


						var auditControlRiskReportData = x.riskReport;
						var auditControlRiskMaxValue = Math.max.apply(null, auditControlRiskReportData.y_axis.map(Number)) + 3;
						const ctx34 = document.getElementById('myChart34');
						new Chart(ctx34, {
							type: 'doughnut',
							data: {
								labels: auditControlRiskReportData.x_axis,
								datasets: [{
									label: 'No of Risk By Report',
									data: auditControlRiskReportData.y_axis,
									// backgroundColor: '#4274be',
									backgroundColor: [
										'rgb(95,147,233)',
										'rgb(77,202,235)',
										'rgb(254,112,150)',
										'rgb(117,153,255)',
									],
								}]
							},
							options: {
								plugins: {
									legend: {
										display: true,           // Show the legend
										position: 'bottom',       // Position it to the right
										labels: {
											boxWidth: 20,       // Size of the legend box
											padding: 15         // Padding around each legend item
										}
									},
									tooltip: {
										enabled: false
									},
									datalabels: {
										color: '#fff',
										align: 'center',
										formatter: (value, context) => {
											const label = context.chart.data.labels[context.dataIndex];
											return `${value}`;  
										},
									}
								},
								maintainAspectRatio: false, 
							}
						});


						var auditControlTypeReportData = x.typeReport;
						var auditControlTypeMaxValue = Math.max.apply(null, auditControlTypeReportData.y_axis.map(Number)) + 3;
						const ctx35 = document.getElementById('myChart35');
						new Chart(ctx35, {
							type: 'doughnut',
							data: {
								labels: auditControlTypeReportData.x_axis,
								datasets: [{
									label: 'No of Type By Report',
									data: auditControlTypeReportData.y_axis,
									backgroundColor: [
										'rgb(77 202 235)',
										'rgb(75,124,235)',
										'rgb(254,112,150)',
										'rgb(117,153,255)',
										
									],
								}]
							},
							options: {
								plugins: {
									legend: {
										display: true,           // Show the legend
										position: 'bottom',       // Position it to the right
										labels: {
											boxWidth: 20,       // Size of the legend box
											padding: 15         // Padding around each legend item
										}
									},
									tooltip: {
										enabled: false
									},
									datalabels: {
										color: '#fff',
										align: 'center',
										formatter: (value, context) => {
											const label = context.chart.data.labels[context.dataIndex];
											return `${value}`;  
										},
										
									}
								},
								maintainAspectRatio: false, 
							}
						});
						

						var auditControlAreaReportData = x.areaReport;
						var auditControlAreaMaxValue = Math.max.apply(null, auditControlAreaReportData.y_axis.map(Number)) + 3;
						const ctx36 = document.getElementById('myChart36');
						new Chart(ctx36, {
							type: 'bar',
							data: {
								labels: auditControlAreaReportData.x_axis,
								datasets: [{
									label: 'No of Area By Report',
									data: auditControlAreaReportData.y_axis,
									backgroundColor: '#5f93e9',
									borderRadius: 12,
									barThickness: 10, 
									datalabels: {anchor: 'end',align: 'end',offset: 5}
								}]
							},
							options: {
								indexAxis: 'y',
								scales: {
									x: {
										beginAtZero: true,
										min: 0,
										max: auditControlAreaMaxValue
									},
									y: {
										stacked: true,
										beginAtZero: true,
										title: {
											display: true,
											font: {
												size:2
											},
										},
										ticks: {             
											autoSkip: false,                  
											maxRotation: 2,   
											minRotation: 0,
											stepSize: 20,
											callback: function(value, index, values) {
												let label = this.getLabelForValue(value);
												return label.length > 25 ? label.substring(0, 22) + '...' : label;
											}
										},
									}
								},
								maintainAspectRatio: false, 
								plugins: {
									legend: {
										display: true,
										position: 'bottom',  // Set the legend position to bottom
										color:'#000'
									},
									tooltip: {
										enabled: false
									}
								}
							}
						});
						// set chart data end
						setTimeout(function(){
							$(".simple-spinner-chart").css("display", "none");
						}, 500);
					});
				}
			}
			// 03122024  Capa Report Chart by iwi | end 








			//  13112024 audit plan Schedule section changes start
			$('#auditPlanListBtn').click(function(){
				$("#caPaReportViewSec").css("display", "none");// 08012024 Audit control report changes
				$("#auditPlanViewSec").css("display", "block");
				$("#auditPlanCreateFormBtn").css("display", "block");
				$("#listViewSec").css("display", "none");
				$("#auditPlanFormSec").css("display", "none");
				$("#auditControlChartViewSec").css("display", "none");
				$('head').append('<link rel="stylesheet" href="/css/default/components/bootstrap.min.css?v=2023.0" type="text/css" />');
				get_All_Audit_Type_Data()
				Load_Audit_Plan_Control_Listing('active','','','','');
			});

			function get_All_Audit_Type_Data(){
				$.post("/record/process?action=get_All_Audit_Type_Data", {
					status: '',
					}, function(x) {
					$('#audit_plan_type').html('<option value="">Select</option>'); // 15112024 audit plan Schedule changes
					$.each(x.groups, function(index, item) {
						$('#audit_plan_type').append(
							$('<option>', { value: item.id, text: item.name })
						);
					});
				});
			}
			$('#audit_plan_schedule_type').on('change', function (e) {
				const scheduleType = $('#audit_plan_schedule_type').val();
				const startDate = new Date($('#audit_plan_start_date').val());
				const endDate = new Date($('#audit_plan_end_date').val());
				var scheduleDate =  calculateScheduleCount(scheduleType,startDate,endDate);
				$('#auditPlanNoOfSchedule').text(scheduleDate.length);
			});

			function calculateScheduleCount(scheduleType,startDate,endDate) {

				 // Validate the inputs
				 if (!scheduleType || isNaN(startDate.getTime()) || isNaN(endDate.getTime()) || startDate > endDate) {
					$('#schedule_output').text("Please select valid dates and a schedule type.");
					return;
				}
		
				let scheduleDates = [];
				let current = new Date(startDate); // Start from the start date
		
				// Calculate dates based on the schedule type
				while (current <= endDate) {
					scheduleDates.push(current.toISOString().split('T')[0]); // Add a copy of the current date to the schedule list
		
					// Increment the current date based on the selected schedule type
					switch (scheduleType) {
						case 'weekly':
							current.setDate(current.getDate() + 7);
							break;
						case 'biweekly':
							current.setDate(current.getDate() + 14);
							break;
						case 'monthly':
							current.setMonth(current.getMonth() + 1);
							break;
						case 'quarterly':
							current.setMonth(current.getMonth() + 3);
							break;
					}
				}

				return scheduleDates;

			}

			// 15112024 audit plan Schedule changes start
			$('#auditPlanSaveFormBtn').on('click', function() {

				var audit_plan_type = $('#audit_plan_type').val();
				var audit_plan_title = $('#audit_plan_title').val();
				var audit_plan_area = $('#audit_plan_area').val();
				const scheduleType = $('#audit_plan_schedule_type').val();
				const auditStartDate = $('#audit_plan_start_date').val();
				const auditEndDate = $('#audit_plan_end_date').val();
				const audit_plan_id = $('#audit_plan_id').val();
				let errors = [];
				if (!audit_plan_title) {
					errors.push("Please enter the audit title.");
				}

				if (errors.length > 0) {
					alert(errors.join("\n"));
					return false;
				}

				// $.post("/record/process?action=save_Audit_Type_Data", {
				$.post("/issue/list?action=save_Audit_Type_Data", {
					type: audit_plan_type,
					audit_plan_id: audit_plan_id,
					title: audit_plan_title,
					area: audit_plan_area,
					scheduleType: scheduleType,
					start_date: auditStartDate,
					end_date: auditEndDate,
				}, function(x) {

					if(x.success){
						Load_Audit_Plan_Control_Listing();
						Audit_Plan_Form_Reset(false);
						if(audit_plan_id > 0){
							cargohub.alert("Audit plan update successfully.");
						}else{
							cargohub.alert("Audit plan saved successfully.");
						}
					}else{
						alert(x);
					}
				
				});
			});
			// 15112024 audit plan Schedule changes end


			$('#createScheduleAuditBtn').on('click', function() {
				var audit_plan_type = $('#audit_plan_type').val();
				var audit_plan_title = $('#audit_plan_title').val();
				var audit_plan_area = $('#audit_plan_area').val();
				
				const scheduleType = $('#audit_plan_schedule_type').val();
				const startDate = new Date($('#audit_plan_start_date').val());
				const endDate = new Date($('#audit_plan_end_date').val());
				var scheduleDate =  calculateScheduleCount(scheduleType,startDate,endDate);

				const auditStartDate = $('#audit_plan_start_date').val();
				const auditEndDate = $('#audit_plan_end_date').val();
				const audit_plan_id = $('#audit_plan_id').val();

				let errors = [];
				if (!audit_plan_title) {
					errors.push("Please enter the audit title.");
				}
				if (!auditStartDate) {
					errors.push("Please enter a valid start date.");
				}
				if (!auditEndDate) {
					errors.push("Please enter a valid end date.");
				}
				if (!scheduleType) {
					errors.push("Please select an schedule type.");
				}
				if (errors.length > 0) {
					alert(errors.join("\n"));
					return false;
				}

				// $.post("/issue/list?action=save_Audit_Type_Data", {
				$.post("/record/process?action=save_Audit_Type_Data", {
					type: audit_plan_type,
					title: audit_plan_title,
					scheduleDate: scheduleDate,
					area: audit_plan_area,
					start_date: auditStartDate,
					end_date: auditEndDate,
					scheduleType: scheduleType,
					audit_plan_id: audit_plan_id,
				}, function(x) {

					if(x.success){
						Load_Audit_Plan_Control_Listing();
						Audit_Plan_Form_Reset(false);
						cargohub.alert("Schedule create successfully.");
					}else{
						alert(x);
					}
				
				});
			});


			function Load_Schedule_Audit_Plan_Control_Listing(id){
				includeJavaScript('/js/components/datatable2/datatables.min.js', function () {
					(function ($) {
						$('<link rel="stylesheet" href="/js/components/datatable2/datatables.min.css" />').appendTo('head');
						if ($.fn.DataTable) {
							setTimeout(function () {
									$(".simple-spinner-chart").css("display", "block");
									// $('#scheduleAuditDataListing').DataTable().clear().destroy(); 18112024 audit plan changes by iwi
									scheduleAuditPlanDataHtmlReinitialize();
									$('#scheduleAuditDataListing').DataTable({
									layout: {
										topStart: {
											buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
										}
									},
										"ajax": {
											"url": "/record/process?action=Get_Audit_Plan_Data_By_Id", 
											"type": "POST",
											"data": function (d) {
												d.id = id; 
											},
											"dataSrc": function (json) {
												if (!json || !json.audit_plan_ref || json.audit_plan_ref.length === 0) {
													// Return an empty array if no data
													return [];
												}
												return json.audit_plan_ref;

											}
										},
										"columns": [
											{ "data": "track"},
											{ "data": "audit_name"},
											{ "data": "schedule_time"},
											
										],
										createdRow: function (row, data, dataIndex) {
											const $idCell = $(row).find('td:eq(0)');
											$idCell.css({
												'color': '#166EB7',
												'cursor': 'pointer'
											});
											$idCell.on('click', function () {
												window.open('/record/process:' + data.track + '#panels', '_blank');

											});
										},
										"order": [[0, "desc"]]
									});
									$(".simple-spinner-chart").css("display", "none");
							}, 100);
						} else {
							console.error('DataTable is not loaded. Check the script URL.');
						}
					})(jQuery.noConflict());
				});
				
				$('script[src="/js/components/datatable2/datatables.min.js"]').remove();
				$('#dtblCss').remove();
			}
				
			function scheduleAuditPlanDataHtmlReinitialize(action=true){
				$('#scheduleAuditDataListingHtml').html('');
				if(action == true){
					var newDataHtml = `<table class="rastable" id="scheduleAuditDataListing">
					<thead>
						<tr>
							<th>Track</th>
							<th>Audit Name</th>
							<th>Schedule Time</th>
						</tr>
					</thead>
					</table>`;
					$('#scheduleAuditDataListingHtml').html(newDataHtml);
				}
				
			}
			
			$(document).on('click', '.editAuditPlan', function() {
				Audit_Plan_Form_Reset();
				
				var dataId = $(this).data('id');
				// $.post("/issue/list?action=Get_Audit_Plan_Data_By_Id", {
				$.post("/record/process?action=Get_Audit_Plan_Data_By_Id", {
					id: dataId
				}, function(x) {
					
					if(x.success){
						
						$('#audit_plan_type').val(x.audit_plan.type);
						$('#audit_plan_title').val(x.audit_plan.title);
						$('#audit_plan_area').val(x.audit_plan.area);
						$('#audit_plan_start_date').val(x.audit_plan.start_date);
						$('#audit_plan_end_date').val(x.audit_plan.end_date);
						$('#audit_plan_no_of_audit').val(x.audit_plan.no_of_audit);
						$('#audit_plan_id').val(x.audit_plan.id);
						$('#audit_plan_schedule_type').val(x.audit_plan.schedule_type);
						$('#auditPlanNoOfSchedule').text(x.audit_plan.no_of_audit);

						// 15112024 audit plan Schedule changes start
						$('#auditPlanSaveFormBtn').prop('disabled', false);
						$('#createScheduleAuditBtn').prop('disabled', false);
						if(x.audit_plan.no_of_audit > 0){
							$('#auditPlanSaveFormBtn').prop('disabled', true);
							$('#createScheduleAuditBtn').prop('disabled', true);
						} 
						// 15112024 audit plan Schedule changes end

						Load_Schedule_Audit_Plan_Control_Listing(dataId);
					}else{
						alert(x);
					}
				});

			});
			
			function Audit_Plan_Form_Reset(display=true){
				$('#audit_plan_type').val('');
				$('#audit_plan_title').val('');
				$('#audit_plan_area').val('');
				$('#audit_plan_start_date').val('');
				$('#audit_plan_end_date').val('');
				$('#audit_plan_no_of_audit').val('');
				$('#total_audit_referance').val(0);
				$('#auditPlanNoOfSchedule').text('');
				$('#audit_plan_schedule_type').val('');
				$('#audit_plan_id').val(0);
				if(display == true){
					$("#auditPlanCreateFormBtn").css("display", "none");
					$("#auditPlanFormSec").slideDown(1000);
					$("#auditPlanCloseFormBtn").css("display", "block");
				}else{
					$("#auditPlanFormSec").slideUp(1000);
					$("#auditPlanCloseFormBtn").css("display", "none");
					$("#auditPlanCreateFormBtn").css("display", "block");
				}
			}

			function Load_Audit_Plan_Control_Listing(status){
				// $(".linecreate").css("display", "none");
				includeJavaScript('/js/components/datatable2/datatables.min.js', function () {
					(function ($) {
						$('<link rel="stylesheet" href="/js/components/datatable2/datatables.min.css" />').appendTo('head');
						if ($.fn.DataTable) {
							console.log('DataTable is available.');
							// Append DataTables CSS
							setTimeout(function () {
			
									$(".simple-spinner-chart").css("display", "block");
									
									// $('#auditPlanReportData').DataTable().clear().destroy();  18112024 audit plan changes by iwi
									auditPlanDataHtmlReinitialize();
									$('#auditPlanReportData').DataTable({
									layout: {
										topStart: {
											buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
										}
									},
										"ajax": {
											"url": "/record/process?action=Load_Audit_Plan_Listing", 
											"type": "POST",
											"data": function (d) {
												d.status = status; 
											},
											"dataSrc": function (json) {
												return json.data;
											}
										},
										// 26112024 audit plan Schedule section changes
										"columns": [
											{ "data": "id", "visible": false },
											{ "data": "title",width:'20%'},
											{ "data": "audit_type",width:'12%' },
											{ "data": "area",width:'8%'},
											{ 
												"data": "start_date",width:'10%',
												"render": function(data, type, row) {
													return data === "0000-00-00" ? "" : data;
												}
											}, 
											{ 
												"data": "end_date",width:'10%',
												"render": function(data, type, row) {
													return data === "0000-00-00" ? "" : data;
												}
											},
											{ 
												"data": "no_of_audit",width:'8%',
												"render": function(data, type, row) {
													return data > 0 ? "Yes" : "No";
												}
											},
											{ 
												"data": "schedule_type",width:'8%'
											},
											{ "data": "no_of_audit",width:'7%'},
											{ 
												"data": null,width:'20%',
												"render": function (data, type, row) {
													isSchedule = '';
													if(row.no_of_audit > 0){
														isSchedule = 'disabled';
													}
													return '<button class="btn btn-primary editAuditPlan" data-id="' + row.id + '"><i class="fa fa-pencil-square-o" aria-hidden="true" title="Edit"></i></button>  <button class="btn btn-primary delAuditPlan" data-id="' + row.id + '" disabled><i class="fa fa-trash-o" aria-hidden="true" title="Delete"></i></button>                     <button type="button" id="listingSecCreateScheduleAuditBtn" data-id="' + row.id + '" '+isSchedule+' class="btn btn-primary"> <i class="fa fa-calculator" title="Create Schedule"></i> </button>'
												},
												"orderable": false,
												"searchable": false
											}
										],
										// 26112024 audit plan Schedule section changes
										"order": [[0, "desc"]]
										
									});
									$(".simple-spinner-chart").css("display", "none");
									// $(".linecreate").css("display", "block");
							}, 100); // Adjust the delay as needed
						} else {
							console.error('DataTable is not loaded. Check the script URL.');
						}
					})(jQuery.noConflict());
				});
				
				$('script[src="/js/components/datatable2/datatables.min.js"]').remove();
				$('#dtblCss').remove();
			}
				
			function auditPlanDataHtmlReinitialize() {
				$('#auditPlanReportDataHtml').html('');
				// 26112024 audit plan Schedule section changes
				var newDataHtml = `<table class="rastable" id="auditPlanReportData">
					<thead>
						<tr>
							<th>Id</th>
							<th>Title</th>
							<th>Type</th>
							<th>Area</th>
							<th>Start Date</th>
							<th>End Date</th>
							<th>Scheduled</th>
							<th>Period</th> 
							<th>Count</th>
							<th>Action</th>
						</tr>
					</thead>
				</table>`;
				// 26112024 audit plan Schedule section changes
				$('#auditPlanReportDataHtml').html(newDataHtml);
			}

			
			$(document).on('click', '#auditPlanCreateFormBtn', function() {
				$('#auditPlanSaveFormBtn').prop('disabled', false);					
				$('#createScheduleAuditBtn').prop('disabled', false);
				Audit_Plan_Form_Reset();
				scheduleAuditPlanDataHtmlReinitialize(false);
			});
			

			$(document).on('click', '#auditPlanCloseFormBtn', function() {
				Audit_Plan_Form_Reset(false);
			});
			//  13112024 audit plan Schedule section changes end

			// 26112024 audit plan Schedule section changes start
			$(document).on('click', '#listingSecCreateScheduleAuditBtn', function() {
				var dataId = $(this).data('id');
				$.post("/record/process?action=Get_Audit_Plan_Data_By_Id", {
					id: dataId
				}, function(x) {
					
					if(x.success){
						  
						var audit_plan_type = x.audit_plan.type;
						var audit_plan_title = x.audit_plan.title;
						var audit_plan_area = x.audit_plan.area;
						
						var audit_plan_start_date = x.audit_plan.start_date;
						var audit_plan_end_date = x.audit_plan.end_date;
						
						var audit_plan_id = x.audit_plan.id;
						var audit_plan_schedule_type = x.audit_plan.schedule_type;
						

						if(x.audit_plan.start_date =="0000-00-00" || x.audit_plan.end_date =="0000-00-00" || x.audit_plan.schedule_type ==""){
							return cargohub.alert("Please set all schedule related data correctly.");
						}else{
							var scheduleDate =  calculateScheduleCount(audit_plan_schedule_type,new Date(audit_plan_start_date),new Date(audit_plan_end_date));
							// var scheduleDate =  calculateScheduleCount(audit_plan_schedule_type,audit_plan_start_date,audit_plan_end_date);
							$.post("/record/process?action=save_Audit_Type_Data", {
								type: audit_plan_type,
								title: audit_plan_title,
								scheduleDate: scheduleDate,
								area: audit_plan_area,
								start_date: audit_plan_start_date,
								end_date: audit_plan_end_date,
								scheduleType: audit_plan_schedule_type,
								audit_plan_id: audit_plan_id,
							}, function(x) {
			
								if(x.success){
									Load_Audit_Plan_Control_Listing();
									cargohub.alert("Schedule create successfully.");
								}else{
									alert(x);
								}
							
							});
						}

						// $('#audit_plan_type').val(x.audit_plan.type);
						// $('#audit_plan_title').val(x.audit_plan.title);
						// $('#audit_plan_area').val(x.audit_plan.area);
						// $('#audit_plan_start_date').val(x.audit_plan.start_date);
						// $('#audit_plan_end_date').val(x.audit_plan.end_date);
						// $('#audit_plan_no_of_audit').val(x.audit_plan.no_of_audit);
						// $('#audit_plan_id').val(x.audit_plan.id);
						// $('#audit_plan_schedule_type').val(x.audit_plan.schedule_type);
						// $('#auditPlanNoOfSchedule').text(x.audit_plan.no_of_audit);

						// // 15112024 audit plan Schedule changes start
						// $('#auditPlanSaveFormBtn').prop('disabled', false);
						// $('#createScheduleAuditBtn').prop('disabled', false);
						// if(x.audit_plan.no_of_audit > 0){
						// 	$('#auditPlanSaveFormBtn').prop('disabled', true);
						// 	$('#createScheduleAuditBtn').prop('disabled', true);
						// } 
						// // 15112024 audit plan Schedule changes end

						// Load_Schedule_Audit_Plan_Control_Listing(dataId);
					}else{
						alert(x);
					}
				});
			});
			// 26112024 audit plan Schedule section changes end


			
		}
		
		
	});

	// 29052024 created by iwi |  start
	if($("#trainingReportViewSec").length > 0) {
		$(document).on('click', '.goUserDetailPnl', function (e) {
			e.preventDefault();
			var url = $(this).data("url");
			window.open(url, '_blank');
		});
	}
	// 29052024 created by iwi |  end


	function printableTrainingReportFunc(divId) {
		var printContents = document.getElementById(divId).innerHTML;
		var originalContents = document.body.innerHTML;
		document.body.innerHTML = printContents;
		window.print();
		document.body.innerHTML = originalContents;
	}
	// $(document).ready(function() { 
	// 	setTimeout(function(){
	// 		$(".site").css('width','1040px');
	// 		$("#listing-menubar").css('width','1040px');
	// 		$("#issues-grid").css('width','1040px');
	// 		$("#user-menu-bar").css('width','1040px');
	// 	}, 5000);
	// });
	// $(document).ready(function () {
	// 	$('#datepicker').datepicker({
	// 		format: 'dd-mm-yyyy',
	// 		startDate: '+1d'
	// 	});
	// });
	// 19062024 created by iwi |  start
	$(document).on('change', '.license_monitor_grid_sw_doc', function (e) {
		e.preventDefault();
		var url = $(this).val();
		window.open(url, '_blank');
	});
	// 19062024 created by iwi |  end

	// 27092024 expiry report changes remove code
	// $("#trainingExpiryReportByExcel").on("click", function() {
	// 	var report_type_days = $(".rtd_active").attr('for');
	// 	var report_user_type = $(".rut_active").attr('for');

	// 	var person_name = $("#person_name_list").val();
	// 	var training_name = $("#training_name_list").val();
	// 	var training_from = $("#training_from_date").val();
	// 	var training_to = $("#training_to_date").val();
		
	// 	window.location.href = "/record/process?action=training_expiry_report_By_excel&days="+report_type_days+"&report_user_type="+report_user_type+
	// 	"&person_name="+person_name+"&training_name="+training_name+"&training_from="+training_from+"&training_to="+training_to;
	// });	
	// 24092024 Training Expiry Report filter | end


	$(document).ready(function () {
		/*
		var Onesource = {
			datatype: "json",
			datafields: [
			  {
				name: "id",
				type: "string",
			  },
			  {
				name: "date",
				type: "string",
			  },
			],
			url: "?action=issue_list_new",
		    pagesize: 25,
			cache: false,
			root: "data",
		};
		
		console.log("nestedGrid_2  4925 ");
		var initrowdetails = function (index, parentElement, gridElement, record) {
		  var row = index;
		  var nestedGrid_2 = $($(parentElement).children()[0]);
		  var orderssource_awb = {
			datatype: "json",
			datafields: [
			  {
				name: "id_a",
				type: "string",
			  },
			  {
				name: "id",
				type: "string",
			  },
			  {
				name: "length",
				type: "string",
			  }
			],
			url: "?action=issue_list_new",
			pagesize: 25,
			root: "_data",
		  };
		  var nestedGridAdapter_2 = new $.jqx.dataAdapter(orderssource_awb);
		  console.log("nestedGrid_2  4952 ",nestedGrid_2);
		  if (nestedGrid_2 != null) {
			nestedGrid_2.jqxGrid({
			  source: nestedGridAdapter_2,
			  filterable: true,
			  sortable: true,
			  clipboard: false,
			  width: "90%",
			  height: "90%",
			  // autoheight: true,
			  // height: 200,
			  columns: [
				{
				  text: "ID",
				  datafield: "id",
				  width: 160,
				  hidden: true,
				},
				{
				  text: "Take In",
				  datafield: "length",
				  width: 120,
				
				}
			  ],
			});
		  }
		};
		var OnedataAdapter = new $.jqx.dataAdapter(Onesource);
		
		$("#new-issues-grid").jqxGrid({
		  source: OnedataAdapter,
		  initrowdetails: function (index, parentElement, gridElement, record) {
			var row = index;
			var nestedGrid_2 = $($(parentElement).children()[0]);
			var orderssource_awb = {
			  datatype: "json",
			  datafields: [
				{
				  name: "id_a",
				  type: "string",
				},
				{
				  name: "id",
				  type: "string",
				},
				{
				  name: "length",
				  type: "string",
				}
			  ],
			  url: "?action=issue_list_new",
			  pagesize: 25,
			  root: "_data",
			};
			var nestedGridAdapter_2 = new $.jqx.dataAdapter(orderssource_awb);
			console.log("nestedGrid_2  4952 ",nestedGrid_2);
			if (nestedGrid_2 != null) {
			  nestedGrid_2.jqxGrid({
				source: nestedGridAdapter_2,
				filterable: true,
				sortable: true,
				clipboard: false,
				width: "90%",
				height: "90%",
				// autoheight: true,
				// height: 200,
				columns: [
				  {
					text: "ID",
					datafield: "id",
					width: 160,
					hidden: true,
				  },
				  {
					text: "Take In",
					datafield: "length",
					width: 120,
				  
				  }
				],
			  });
			}
		  },
		  sortable: true,
		  groupable: true,
		  width: "100%",
		  pageable: true,
		  autoheight: true,
		  columnsresize: true,
		  enablebrowserselection: true,
		  clipboard: false,
		  columnsreorder: true,
		  rowdetailstemplate: {
			rowdetails:
			  "<div id='test' style='margin: 10px;'></div>",
			rowdetailsheight: 300,
			rowdetailshidden: true,
		  },
		  rendergridrows: function (obj) {
			return obj.data;
		  },
		  columns: [
			{
			  text: "Actions",
			  datafield: "id",
			  width: 60,
			},
			{
			  text: "Created",
			  datafield: "date",
			  width: 100,
		
			},
		  ],
		});
		*/
		

	});

	// 20082024 created by iwi |  start
	$(document).on('click', '.goDetailFrmPnl', function (e) {
		e.preventDefault();
		var url = $(this).data("url");
		window.open(url, '_blank');
	});
	// 20082024 created by iwi |  end
        
        
        
        
        


});


  function module_redirect(id) {

     user_ss = cargohub.application.user_Get_Session(); 
    cargohub.application.save_Preferences({
        "rt_id": id
    }, function () {
        if(user_ss.org_id!=''){
            document.location = '/issue/list?org_id='+user_ss.org_id;
        }else{
            document.location = '/issue/list';
        }
        
    });
}      

