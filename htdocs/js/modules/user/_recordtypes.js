/**
 * Module "User Recordtypes", Code for the "Modules" tab of the User Overview
 *
 * @author Wavetrex Infinity <wavetrex@gmail.com>
 * @package CargoConnect
 * @subpackage JavaScript Modules
 * @version 2.9.1
 */
cargohub.user_recordtypes = donrey.BASE._extend(function()
{
	// private
	var self = this,
		c = null,
	/**
	 * Information returned in the response; Always reset when loading a new type
	 */
	rtinfo = {},
	/**
	 * Instances of cargohub.baseclass.chart which were created and loaded.
	 * Chart data is read from the server at instancing time, and reused as long as the module/tab is still in memory
	 */
	chart_instances = {},
	// For the Configure Charts control
	charts_source = [],
	/**
	 * Chart instance models - Each one contains special code which only applies for that type of chart. If missing, it will return null, which means a default object is initialized
	 */
	chart_models = {
		// Define when customized functions are needed for certain charts (normally not needed)
/*		"total_records": function() {
			var self = this
			;
		}*/
	},

	/**
	 * The window that allows the user to configure the charts (from all available ones)
	 */

	open_Charts_Config_Modal = function()
	{
		if(cargohub.components.modal.windows['charts-config'])
			return cargohub.components.modal.reopen('charts-config');

		cargohub.components.modal.create('charts-config', {
			btn_roles: {cancel: "cancel"}
		});

		cargohub.components.modal.open('charts-config', {
			width: 400, height: 370,
			title: "Configure charts for "+ rtinfo.rt_def.name,
			content: $('#window-chart_config').html(),
			callback: function($window) {
				// The one and only drag+checkboxes control that exists in the software :-)
				$("#charts-selector", $window).jqxListBox({
					theme: cargohub.jqxtheme,
					source: charts_source,
					width: 348, height: 240,
					valueMember: 'model', displayMember: 'name',
					checkboxes: true,
					allowDrag: true, allowDrop: true
				});

				$('#modal_charts-config_ok', $window).off('click').on('click', function() {
					// Save this form
					$.post('?action=Save_Charts_Config', {
						rt_key: rtinfo.rt_def.key,
						charts: $("#charts-selector", $window).val()
					}, function(r) {
						$window.jqxWindow('close');

						// Complete refresh of RTInfo (but perhaps it's not needed? ... rebuild only the charts? With a slight delay to allow the window to properly close first
						setTimeout(function() {
							$("#tab_modules #rt-description").html("<div class='loading-pleasewait'>&nbsp;</div>").css("height", $("#tab_modules #rt-list").jqxListBox('height'))
							.load("?action=load_rt", {
								rt_id: rtinfo.rt_def.id,
								rt_key: rtinfo.rt_def.key,
							}, function(response, status, jqXHR) {
								if(status == "error") {
									$(this).html("<div class='center'>Error, please try again.</div>");
									return cargohub.its.http_fail(jqXHR);
								}

								// No error, read the passed data (if it exists)
								rtinfo = {};
								donrey.read_JSON_Headers(jqXHR, rtinfo);
								$(this).css("height", 'auto');
								init_RT_Controls(rtinfo);
							});
						}, 250);

					}, "json")
					.fail(cargohub.its.http_fail);
				});
			}
		});
	},

	/**
	 * After a record information page is loaded, init the controls on it
	 * The entire thing is reinitialized when a record type is loaded, as the DOM for it is completely reset (by reloading)
	 * - Action buttons (right side)
	 * - Charts configuration window
	 * - Charts themselves
	 * @param rtinfo Object containing everything needed to prepare the page
	 */
	init_RT_Controls = function()
	{

		
		// Action buttons
		$("#tab_modules div.rt-functions button").on('click', function() {
			var data = $(this).data();
			
			console.log("data >>",data);
			switch(data.op) {
			// General "Link" button
			case 'link':
				document.location = data.link;
				break;
			// Switch record type, when done, go to the new module (via the link)
			case 'sw-ist':
				cargohub.application.save_Preferences({
					"rt_id": rtinfo.rt_def.id
				}, function() {
					document.location = data.link;
				});
				break;
			case 'cfg-charts':
				// The chart configuration window
				open_Charts_Config_Modal(rtinfo.rt_def);
				break;
			}
		});

		if(rtinfo.functions.charts) {
			// Related to Configure Charts modal
			cargohub.components.modal.forget('charts-config'); // Reset on new RT
			charts_source = [];
			for(c=0; c<rtinfo.charts.length; c++)
				charts_source.push({
					'model': rtinfo.charts[c],
					'checked': rtinfo.charts_config[rtinfo.charts[c]].visible, // Apparently it's metavalue for the checkboxes to ... check themselves ;-)
					'name': rtinfo.charts_config[rtinfo.charts[c]].name
				});

			// Construct multiple chart objects, as specified in the "charts" config parameter
			for(c=0; c<rtinfo.charts.length; c++) {
				if(!rtinfo.charts_config[rtinfo.charts[c]].visible)
					continue; // Skip the ones that are not visible

				// Build the necesary HTML for one chart then init
				var model_name = rtinfo.charts[c],
					$container = $($("#template-rt_chart").html().present({name: rtinfo.charts[c]}));
					 
				$("#rt-charts").append($container);

				// Instance the chart using model name and the DOM id:
				delete rtinfo.charts_config[model_name]['name']; // Avoids an error in jqxChart
				chart_instances[model_name] = cargohub.baseclass._instance(cargohub.baseclass.chart, chart_models[model_name]);
				chart_instances[model_name].init($container, rtinfo.rt_def, model_name, rtinfo.charts_config[model_name]);
			}
		}
	},

	/**
	 * Prepare the list of data types on the left side. When one is clicked, show more information about the module on the right side.
	 * It consists of a fixed left side showing the modules in a jqxListBox and a right side which loads a template combined with a CMS page referring to that module.
	 * Used for to inform the user on what the module can do, and also basic operations for that module ( if enabled ) - Submit, List and Search
	 *
	 * In certain cases, this also shows "charts"
	 */
	init_RT_List = function()
	{
		$("#tab_modules #rt-list").jqxListBox({
			theme: cargohub.jqxtheme,
			width: 250, autoHeight: true,
			source: self.config.rtlist,
			valueMember: "key",
			allowDrag: false, allowDrop: false,
			renderer: function (index, label, value) {
				var rtype = self.config.rtlist[index];
				return "<img class='inline-icon16' src='/img/icons16/ist/" + rtype.key + ".png' alt='"+ rtype.key +"'> " + (rtype.yep? "<b>"+ rtype.name+ "</b>": "<span class='gray'>"+ rtype.name + "</span>");
			}
		})
		/**
		 * Event: A record type is selected from the list - Show the information on the right side
		 */
		.on('select', function(e) {
			var
				item = e.args.item.originalItem; // Selected record type

			$("#tab_modules #rt-description").html("<div class='loading-pleasewait'>&nbsp;</div>").css("height", $("#tab_modules #rt-list").jqxListBox('height'))
			.load("?action=load_rt", {
				rt_id: item.id,
				rt_key: item.key
			}, function(response, status, jqXHR) {
				if(status == "error") {
					$(this).html("<div class='center'>Error, please try again.</div>");
					return cargohub.its.http_fail(jqXHR);
				}

				// No error, read the passed data (if it exists)
				rtinfo = {};
				donrey.read_JSON_Headers(jqXHR, rtinfo);
				$(this).css("height", 'auto');
				init_RT_Controls(rtinfo);
			});
		});

		// Done with setup, automatically select active record type
		for(c=0; c< self.config.rtlist.length; c++)
			if(self.config.rtlist[c].id == self.config.current_rt.id)
				$("#tab_modules #rt-list").jqxListBox('selectIndex', c);
	}
	;

	this._public({
		/**
		 * Setup Tab - Admin-only module for configuring primary settings for an organization ( name, module access, "manager" user and presence in the Organization Search System )
		 */
		init: function()
		{
			// List of record types on the left side
			init_RT_List();
		}
	});

});

// This module also creates a baseclass for charts, which are being used here
/**
 * "Baseclass" for charts (using jqxChart).
 * - This function doesn't exist on it's own ( not executed until receiving an instance )
 * - It can exist in multiple copies simultaneously ( Instanced once per chart in the User/Overview#Modules )
 */
cargohub.baseclass.chart = function() {
	var instance,			// Instance object derived from this one
	c = null, c2 = null,	// Reusable temporary counters
	// About the chart
	rt_def = null,			// Record Type definition - basic stuff (id, key, name, plural); Needed for the Change/Reload request
	model = null,			// Which statistics model is this chart instance using ? (Returned data depends on this parameter)
	$chart = null,			// The jQuery object which will become jqxChart
	$c = null,				// Container including the controls
	initialized = false,	// When the first request is completed and chart becomes active, this is set to True

	/**
	 * Initializes the jqxChart object with parameters coming from PHP
	 * - Supports refreshing the chart with new data
	 */
	init_jqxChart = function(settings, refresh)
	{
		if(settings.source && settings.xAxis && settings.valueAxis) {
			// We have data. Add the necessary config to the chart and show it !

			// Labels to series?
			if(settings.seriesGroups)
				for(c=0; c<settings.seriesGroups.length; c++) {
					settings.seriesGroups[c].showLabels = true; // Makes value appear on the bars themselves. Undocumented feature?
				}
/*
			settings.seriesGroups[c].toolTipFormatFunction = function(value, itemIndex, serie, group, xAxisValue, xAxis) {
				dd(value);
				dd(itemIndex);
				dd(serie);
				dd(group);
				dd(xAxisValue);
				dd(xAxis);

				// Not sure what is this... not good.
//				var month = x.x_name;
//				var formattedTooltip = "<div>" +
//				"<b>Month: </b>" + month[xAxisValue] + "</br>" +
//				"<b>"+ serie.dataField +": </b>" + value  + "</br>" +
//				"<a onclick=\"list_filter('_status','"+serie.dataField+"','"+x.x_data_field+"','"+xAxisValue+"')\"><b> View </b></a>"+
//				"</div>";
//				return formattedTooltip;
			};
*/

			if(refresh) {
				$chart.jqxChart(settings);
			} else {
				// Prepare default settings ( These do not change )
				settings.colorScheme = "scheme01"; // TODO: Support multiple color schemes
				settings.showLegend = true;
				settings.showBorderLine = false;

				$chart = $(".jqxchart", $c).jqxChart(settings)
				/**
				 * Event: A series element is being clicked; Dropdown menu should show up with available function (like load grid?)
				 */
				.on('click', function(e) {
					// Click triggers anywhere on the chart, but only when on the series we have "args"
//					dd(e.args);
				});
			}

		} else
			$c.html("Incorrect chart configuration, unable to draw.<br />Please contact support@cargohub.nl");
	},

	/**
	 * Initializes the extra controls which affect what is displayed in the chart (Range, Export, Download as image, Print)
	 */

	init_Controls = function(settings)
	{
		// HTML should already be there, initialize controls on this chart
		var period = "Months", subset = 0, reload_Chart = function() {
			$.get("?action=Load_Record_Statistics_Chart", {
				rt_key: rt_def.key,
				model: model,
				period: period,
				subset: subset
			}, function(settings) {
				init_jqxChart(settings, true); // true = Reconstruct existing chart with new settings (This probably also reloads a different statistic, but usually on the same model)
			}, "json")
			.fail(cargohub.its.http_fail);
		}, export_basefilename = function() {
			// Base filename, generated from chart title + Period + Subset
			var basefilename = rt_def.plural+ '-'+ $(".jqx-chart-title-text", $chart).text();
			if($(".chart-process", $c).hasClass('jqx-checkbox'))
				basefilename += '-'+$(".chart-process", $c).find('span')[1].textContent;
			return basefilename;
		};

		if(settings.process)
			$(".chart-process", $c).jqxCheckBox({
				theme: cargohub.jqxtheme,
				hasThreeStates: true, checked: null
			})
			.on('change', function(e) {
				var checked=e.args.checked;
				if(checked===true) {
					$(this).find('span')[1].innerHTML = "Active";
					subset = 1;
				} else if(checked === false) {
					$(this).find('span')[1].innerHTML = "Closed";
					subset = 2;
				} else {
					// Indeterminate = All
					$(this).find('span')[1].innerHTML = "All records";
					subset = 0;
				}
				reload_Chart();
			})
			.show();

		if(settings.range)
			$(".chart-controls > .chart-period-select", $c).jqxDropDownList({
				theme: cargohub.jqxtheme,
				source: settings.range,
				width: 90, height: 23,
				autoDropDownHeight: true,
				selectedIndex: 0 // Start with first option, but this should be configurable / persistent
			})
			/**
			 * Event: When changed, reload the chart with new settings and data
			 */
			.on('change', function(e) {
				period = e.args.item.value;
				reload_Chart();
			})
			.show();

		if(settings.dump) {
			$(".chart-controls > .chart-dump", $c).jqxButton({
				theme: cargohub.jqxtheme,
				height: 25, width: 32
			})
			/**
			 * Export (to Excel) function - Must be created server side, as jqxChart doesn't have one
			 */
			.on('click', function() {

				//dd("Export data ... must be built");
				
				var subset = 0;
				 var total_prov = 0;
					  var tol_pro_cur =0;
				    var period = 'years';        
					$.get("?action=Export_Record_Statistics_Chart", {
						rt_key: rt_def.key,
						model: model,
						period: period,
						subset: subset
					}, function(response) {
						
						//dd(response);
						
				   	var source_content = '';
					$.each( response.source, function( periodKey, periodValue ) {
						
					 $.each( periodValue, function( periodDataKey, periodDataValue ) {	
					 
					 
					 
					 
						source_content += '<table border="1" style="width: 100%;margin-bottom: 20px;border-collapse: collapse;">';
						source_content += '<tr>';
						  source_content += '<th colspan="12" style="background: #cce9ff;padding: 5px;">'+periodDataValue.label+'</th>';
						source_content += '</tr>';
						
						  source_content += '<tr style="background: #dddddd;">';
							source_content += '<th  >  Tracking ID   </th>';
							source_content += '<th >  AWB No.  </th>';
							source_content += '<th >Claim in USD</th>';
							source_content += '<th >Provision in USD</th>';   
							source_content += '<th >Claim received date</th>'; 
							source_content += '<th >Formal claim</th>';
							source_content += '<th >Nature of Goods</th>';
							source_content += '<th >Shipper</th>';
							  
							
						  source_content += '</tr>';
						  
						$.each( periodDataValue.records, function( rowRecord, record ) {	
						
						
						  
						  source_content += '<tr>';
							source_content += '<td style="width:15%" ><a href="https://cargohub.aero/record/process:'+record.track+'" target="_blank">'+record.track+'</a></td>';
							source_content += '<td style="width:15%"  >'+record.cache_primary+'</td>'; 
							source_content += '<td  style="width:12%" >'+record.clm_amt_usd+'</td>'; 
							source_content += '<td  style="width:12%" >'+record.value+'</td>'; 
							source_content += '<td  style="width:12%" >'+record.Claim_receive_date+'</td>'; 
							source_content += '<td style="width:12%" >'+record.formal_claim+'</td>';
							source_content += '<td style="width:12%" >'+record.nat_goods+'</td>';
							source_content += '<td  style="width:12%" >'+record.shipper+'</td>';
							 
							 
						  source_content += '</tr>';
						  
						  
						  
						}); 
						  
						  
						if(periodDataValue.total){ 
                        source_content += '<tr>';
							source_content += '<td colspan="3"><span class="total"><b>Total Provision In USD</b></span></td>';
							source_content += '<td colspan="1"><b>'+periodDataValue.total.toFixed(2)+' $</b></td>';
							 
							 source_content += '<td colspan="3"><span class="total"><b>Total Provision Count</b></span></td>';
							source_content += '<td colspan="1"><b>'+periodDataValue.records.length+' </b></td>';
							
							
						source_content += '</tr>';
						total_prov = total_prov+periodDataValue.total;
						tol_pro_cur = tol_pro_cur+periodDataValue.records.length;
						 
						}
						
						
						
						console.log(total_prov);
						
						  console.log(tol_pro_cur);
						  
						   
							
						  
						source_content += '</table>';
						});
					});					
						
source_content += '<table border="1" style="width: 100%;margin-bottom: 20px;border-collapse: collapse;">';
 source_content += '<tr style="background: #dddddd;">';
							source_content += '<th  >  Total Active Provision   </th>';
							source_content += '<th  >  Total Active Provision in USD   </th>';
							source_content += '</tr>';
							 source_content += '<tr style="background: #fff;">';
							source_content += '<th  > <b> '+tol_pro_cur+' </b> </th>';
							source_content += '<th  > <b> '+ total_prov.toFixed(2)+' $ </b></th>';
							source_content += '</tr>';
							source_content += '</table>';
							
							
							
						
						
					pageContent =
					'<!DOCTYPE html>' +
					'<html>' +
					'<head>' +
					'<meta charset="utf-8" />' +
					'<title>'+ export_basefilename() +'</title>' +
					'<style> .Rrecords-list table tr:nth-child(even){background: #f5f5f5;} .Rrecords-list table tr th{padding: 5px;font-size:13px;} .Rrecords-list table tr td {padding: 5px;font-size:14px;} .print {position: absolute;right: 40px;}</style>' +
					'</head>' +
					'<body><div class="print"><button onclick="window.print()" ><img src="/img/icons16/printer.png" alt="Print"></button></div><h2 style="text-align: center;margin-bottom: 6px;font-size: 24px;">'+response.title+'</h2><h3 style="text-align: center;margin-top: 0;color: #1174bd;font-weight: 500;font-size: 18px;text-transform: uppercase;">(All Active Provisions Till Date)</h3><div class="Rrecords-list" style="width: 90%;margin: 0 auto;">'+source_content+'</div></body></html>';
				
					

					var myWindow = window.open("", "", "width=930,height=350");
                    myWindow.document.write(pageContent);
						
						
						
						
					}, "json")
					.fail(cargohub.its.http_fail);
				    
					
				
				
				
			})
			.show();
		}
		if(settings.image) {
			$(".chart-controls > .chart-image", $c).jqxButton({
				theme: cargohub.jqxtheme,
				height: 25, width: 32
			})
			/**
			 * Event: Click on Download button
			 * - Export to image (Automatically saves PNG as a download, by the use of the "export" feature
			 */
			.on('click', function() {
				$chart.jqxChart('saveAsPNG', export_basefilename()+'.png', "/?action=export");
			})
			.show();
		}
		if(settings.print) {
			$(".chart-controls > .chart-print", $c).jqxButton({
				theme: cargohub.jqxtheme,
				height: 25, width: 32
			})
			/**
			 * Event: Click on PRINT button
			 * - Printing by opening a new window and triggering print on it.
			 * - Is this really the best way? Perhaps a jqxwindow with an iframe...
			 */
			.on('click', function() {
				var content = $chart[0].outerHTML,
					newWindow = window.open('', '', 'width=830, height=350'), // TODO: This should depend on chart size
					document = newWindow.document.open(),
					pageContent =
					'<!DOCTYPE html>' +
					'<html>' +
					'<head>' +
					'<meta charset="utf-8" />' +
					'<title>'+ export_basefilename() +'</title>' +
					'<link href="/js/lib/jqwidgets/styles/jqx.base.css" type="text/css" rel="stylesheet" />'+
					'</head>' +
					'<body>' + content + '</body></html>';
				try
				{
					document.write(pageContent);
					document.close();
					newWindow.print();
					newWindow.close();
				}
				catch (error) {
				}
			})
			.show();
		}

		// Init jqxTooltips
		$(".info-tip", $c).each(cargohub.components.ui.add_Tooltip);
	}
	;

	/**
	 * Initialize jqxChart with a certain statistics model, the DOM $container for the chart and control UI
	 */
	this.init = function($container, record_type, model_name, config)
	{
		if(!$container || $container.length==0)
			return; // Container not found

		$c = $container;
		rt_def = record_type;
		model = model_name;
		init_jqxChart(config.chart);
		init_Controls(config.controls);
	};

	/**
	 * Returns if the chart is active and working
	 */
	this.is_Initialized = function()
	{
		return initialized;
	};

	this._linkInstance = function(_instance)
	{
		instance = _instance;
	};
};


