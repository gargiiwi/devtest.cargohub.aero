/**
 * Module "Reports Main"
 */
cargohub.reports_main = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,

	/**
	 * Sets up the splitter for the module's 2-panel display
	 */
	setup_splitter = function()
	{
		$("#reports_overview").jqxSplitter({ theme: cargohub.jqxtheme, width: cargohub.fullwidth, height: 458, panels: [{
			collapsible: false,
			min: 200,
			size: 250
		}, {
			min: 400,
			collapsible: false
		}] });
	},

	ist_cache = {},

	bic = { // built-in-columns
		'_track': "Tracking ID",
		'_id': "ID",
		'_ctime': "Creation time",
		'_utime': "Update time",
		'_priority': "Priority",
		'_status': "Status",
		'_assigned': "Assigned to",
		'_post_ip': "IP Address",
		'_count_msgs': "Message count",
		'_count_files': "Attachments"
	},

	/**
	 * Translates a raw list of fields (as an array) into an an array containing "value" and "label" ( can be used in lists )
	 */
	fields_translate_list = function(ist_id, list)
	{
		var translated = [];

		bic._group = ist_cache[ist_id].group_name;

		for(c=0; c<list.length; c++) {
			if(list[c].substr(0,1)=='_') { // built-in fields
				translated.push({
					'value': list[c],
					'label': bic[list[c]]
				});
			} else { // xfields
				translated.push({
					'value': list[c],
					'label': ist_cache[ist_id].xfields[list[c]]
				});
			}
		}

		return translated;
	},

	/**
	 * Set up controls on the view report page ( Output report section )
	 */
	setup_view_report = function(ist_id)
	{
		// set up controls on the view report page ( Output report section )

		// setup date inputs (have a built-in calendar)
		$("#reports_op div.calendar").each(function() {
			var cal_setup = {
				formatString: cargohub.date_format,
				firstDayOfWeek: 1,
				width: 200,
				height: 23,
				theme: cargohub.jqxtheme
			};
			// TODO: Determine in which case initial date is used?
//			ini_date = $(this).data('value');
			//if(ini_date)
				//cal_setup.value = new $.jqx._jqxDateTimeInput.getDateTime(new Date(ini_date));
			var startDate = new Date();
			startDate.setMonth(startDate.getMonth() - 3);
			$(this).jqxDateTimeInput(cal_setup);
			$("#date_start").jqxDateTimeInput('setDate', startDate);
			$("#date_end").jqxDateTimeInput('setDate', new Date());
		});

		self.config.groups[0].name = "(Any "+ist_cache[ist_id].group_name+")";
		$('#reports_op #group').jqxDropDownList({
			source: self.config.groups,
			selectedIndex: 0,
			displayMember: "name", valueMember: "id",
			width: 165,	height: 23,
			theme: cargohub.jqxtheme,
			dropDownWidth: 240,
			autoDropDownHeight: true,
			dropDownHorizontalAlignment: 'right'
		});

		var statusesSource = [{
			'key': 0,
			'label': "(Any Status)"
		}];
		for(c in ist_cache[ist_id].statuses)
			statusesSource.push({
				'key': c,
				'label': ist_cache[ist_id].statuses[c]
			});

		$('#reports_op #status').jqxDropDownList({
			source: statusesSource,
			selectedIndex: 0,
			displayMember: "label", valueMember: "key",
			width: 165,	height: 23,
			theme: cargohub.jqxtheme,
			autoDropDownHeight: true
		});

		$('#reports_op input[type="submit"]').jqxButton({
			theme: cargohub.jqxtheme
		});
	},

	/**
	 * Preparest the data than displays the dashboard for a report type
	 * -- with the commands to display the report using various methods (html, pdf, send by e-mail, etc.)
	 */
	view_report_type = function(report)
	{
		// load and cache the IST for this report type
		if(ist_cache[report.ist]) {
			report.columns = fields_translate_list(report.ist, report.columns);
			report.ist_name = ist_cache[report.ist].name;
			report.group_name = ist_cache[report.ist].group_name;
			$("#reports_op").html($("#template_viewreport").html().present(report));
			setup_view_report(report.ist);
		} else {
			$.get("?action=get_ist&ist="+report.ist, function(x) {
				if(x.success) {
					ist_cache[report.ist] = x.ist;
					report.columns = fields_translate_list(report.ist, report.columns);
					report.ist_name = ist_cache[report.ist].name;
					report.group_name = ist_cache[report.ist].group_name;
					$("#reports_op").html($("#template_viewreport").html().present(report));
					setup_view_report(report.ist);
				} else {
					alert(x.reason);
				}
			});
		}

	},

	// The report types data container
	reportListAdapter = null,

	/**
	 * Sets up the left side listbox which shows all report types
	 */
	setup_listbox = function()
	{
		// prepare the data
		reportListAdapter = new $.jqx.dataAdapter({
			url: '?action=get_list',
			datatype: "json",
			datafields: [
				{ name: 'id', type: 'number' },
				{ name: 'ist', type: 'number' },
				{ name: 'name'},
				{ name: 'columns' },
				{ name: 'creator_id', map: "creator>id", type: 'number'}, { name: 'creator_name', map: "creator>name"}, { name: 'creator_email', map: "creator>email"},
				{ name: 'org_id', map: "org>id", type: 'number'}, { name: 'org_name', map: "org>name"}
			],
			id: 'id'
		});

		// Create jqxListBox
		$('#reports_quicklist')
		.jqxListBox({
			theme: cargohub.jqxtheme,
			source: reportListAdapter,
			valueMember: "id",
			displayMember: "name",
			width: '100%', height: 396,
		})
		.bind('select', function (event) {
			// show the user information which is already loaded in the data adapter
			var report = clone(reportListAdapter.records[event.args.index]);
			report.index = event.args.index;

			// decode the json encoded column names
			report.columns = $.parseJSON(report.columns);

			view_report_type(report);
		});
	},

	setup_report_add_form = function(ist)
	{
		$("#reports_op").html($("#template_editreport").html().present());
		$("form.vertiform input[type='submit']").jqxButton({ width: 200, theme: cargohub.jqxtheme });

		var avail_cols = [];

		bic._group = ist_cache[ist].group_name;

		// create the list with all available columns (bic + xfields)
		for(c in bic) {
			avail_cols.push({
				'value': c,
				'label': bic[c]
			});
		}
		for(c in ist_cache[ist].xfields) { // TODO: Select issue type when creating report type !
			avail_cols.push({
				'value': c,
				'label': ist_cache[ist].xfields[c]
			});
		}

		// Create the two listboxes
		$("#columns-select").jqxListBox({
			allowDrop: true, allowDrag: true,
			source: [], // empty, nothing here since it's a "create"
			width: "100%", height: 204, theme: cargohub.jqxtheme
		});
		$("#columns-avail").jqxListBox({
			allowDrop: true, allowDrag: true,
			source: avail_cols,
			width: "100%", height: 204, theme: cargohub.jqxtheme
		});

		// form operations
		$("div#reports_op form").ajaxForm({
			dataType: 'json',
			beforeSubmit: function(formData, jqForm, options) {

				// setup the field checking object
				var f = {};
				for(c=0; c< formData.length; c++)
					f[formData[c].name] = formData[c].value;

				if(f['report[name]'].length<3) {
					alert("Please enter a report type name!");
					return false;
				}

				var sel_cols_listbox = $("#columns-select").jqxListBox("getItems"), sel_cols = [];
				for(c = 0; c< sel_cols_listbox.length; c++)
					sel_cols.push(sel_cols_listbox[c].value);

				if(sel_cols.length<1) {
					alert("Please select at least one columns for this report type");
					return false;
				}

				formData.push({"name": "report[columns]", "value": sel_cols.join(',')});
				formData.push({"name": "report[ist_id]", "value": ist});
				return true;
			},
			success: function(x) {
				// when completed successfully, refresh the page
				if(x.success) {
					document.location.reload();
				} else {
					alert(x.reason);
				}
			}
		});

	},

	/**
	 * Sets up the command buttons on top, which are used to manage report types
	 */
	setup_command_buttons = function()
	{
		// Actions for the buttons
		$(document).on("click", "div#reports_overview div.command > button", function() {
			switch($(this).data("cmd")) {
				case 'add-report':
					$("#reports_quicklist").jqxListBox('clearSelection');

					$("#reports_op").html($("#template_choose_ist").html());

					$("#report_ist").jqxDropDownList({
						source: self.config.ist_list,
						displayMember: "name", valueMember: "id",
						width: 198,	height: 25,
						theme: cargohub.jqxtheme,
						autoDropDownHeight: true
					});

					$("form.vertiform input[type='button']").jqxButton({ width: 200, theme: cargohub.jqxtheme })
					.on('click', function() {
						var ist = $("#report_ist").jqxDropDownList('val');
						if(!ist) {
							alert("Please select an issue type !");
							return;
						}

						if(ist_cache[ist]) { // immediatly, when ist field list exists in cache
							setup_report_add_form(ist);
						} else { // delayed, when ajax call responds
							$.get("?action=get_ist&ist="+ist, function(x) {
								if(x.success) {
									ist_cache[ist] = x.ist;
									setup_report_add_form(ist);
								} else {
									alert(x.reason);
								}
							});
						}
					});

					break;
				case 'edit-report':
					var index = $(this).data("index"),
						report = clone(reportListAdapter.records[index]),
						sel_cols = $.parseJSON(report.columns),
						avail_cols = [];

					// decode the json encoded fields
					report.columns = fields_translate_list(report.ist, sel_cols);

					$("#reports_op").html($("#template_editreport").html().present(report));

					$("form.vertiform input[type='submit']").jqxButton({ width: 200, theme: cargohub.jqxtheme });

					bic._group = ist_cache[report.ist].group_name;

					// everything that is not in selected cols, put in the avail_cols
					for(c in bic) {
						if(sel_cols.indexOf(c)==-1)
							avail_cols.push({
								'value': c,
								'label': bic[c]
							});
					}

					for(c in ist_cache[report.ist].xfields) {
						if(sel_cols.indexOf(c)==-1)
							avail_cols.push({
								'value': c,
								'label': ist_cache[report.ist].xfields[c]
							});
					}

					// Create the two listboxes
					$("#columns-select").jqxListBox({
						allowDrop: true, allowDrag: true,
						source: report.columns,
						width: "100%", height: 204, theme: cargohub.jqxtheme
					});
					$("#columns-avail").jqxListBox({
						allowDrop: true, allowDrag: true,
						source: avail_cols,
						width: "100%", height: 204, theme: cargohub.jqxtheme
					});

					// form operations
					$("div#reports_op form").ajaxForm({
						dataType: 'json',
						beforeSubmit: function(formData, jqForm, options) {

							// setup the field checking object
							var f = {};
							for(c=0; c< formData.length; c++)
								f[formData[c].name] = formData[c].value;

							if(f['report[name]'].length<3) {
								alert("Please enter a report type name!");
								return false;
							}

							var sel_cols_listbox = $("#columns-select").jqxListBox("getItems"), sel_cols = [];
							for(c = 0; c< sel_cols_listbox.length; c++)
								sel_cols.push(sel_cols_listbox[c].value);

							if(sel_cols.length<1) {
								alert("Please select at least one columns for this report type");
								return false;
							}

							formData.push({"name": "report[columns]", "value": sel_cols.join(',')});
							formData.push({"name": "id", "value": report.id});
							return true;
						},
						success: function(x) {
							// when completed successfully, refresh the page
							if(x.success) {
								document.location.reload();
							} else {
								alert(x.reason);
							}
						}

					});

					break;
				case 'delete-report':
					if(confirm("Are you sure you want to delete this report type?")) {
						$.post("?action=delete", {
							'id': $(this).data("id")
						}, function(x) {
							if(x.success) {
								document.location.reload();
							} else {
								alert(x.reason);
							}
						});

					}
					break;
			}
		});
	}
	;

	this._public({
		/**
		 * Section initialization
		 */
		init: function(){
			setup_splitter();
			setup_listbox();
			setup_command_buttons();
		}
	});
});
