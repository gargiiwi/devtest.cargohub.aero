/**
 * Sub-Module "Airline Search" - Smart search system to pick an airline from the database
 * -- JavaScript part of the Airline search box CMS plugin
 * -- All AJAX requests this sub-module does are forwarded to the corresponding php-side plugin
 */
cargohub.cms_airlinesearch = donrey.BASE._extend(function() {
	// private
	var self = this,
		c = null,
		typed_value = null,

	/**
	 * Combobox used to search for an airline using different methods
	 */
	setup_search = function()
	{
		var $searchbox = $("#airline_searchbox"), airline_info_visible = false,
		// setup the suggestion source
		source_setup = new $.jqx.dataAdapter({
			url: "?action=plugin&plugin=airlinesearch&op=suggest_airline",
			datatype: "json",
			datafields: [
				{ name: 'id' },
				{ name: 'text' }
			]
		}, {
			formatData: function (data) {
				if ($searchbox.jqxComboBox('searchString') != undefined) {
					data.q = typed_value = $searchbox.jqxComboBox('searchString');
					return data;
				}
			}
		}),
		// setup the combo search
		combo_setup = {
			theme: cargohub.jqxtheme,
			source: source_setup,
			minLength: 0,
			remoteAutoComplete: true, remoteAutoCompleteDelay: 200,
			search: function (search) {
				if(search.length>=2)
					source_setup.dataBind();
			},
			valueMember: "id",
			displayMember: "text",
			width: self.config.width, height: 26,
			autoDropDownHeight: true, dropDownWidth: 500
		};

		// finally, prepare the combobox and events on it
		$searchbox
		.jqxComboBox(combo_setup)
		/**
		 * Event: Select - User has clicked or keyed into the search results
		 */
		.on('select', function(event) { // value on select event
			if (event.args != undefined) {
				var item = $searchbox.jqxComboBox('getItem', event.args.index);
				if(!item)
					return;

				var airline_id = item.originalItem.id;

				if(airline_id == 0) {
					// No result, clicked on the "Please type"... and so on - Clear the combobox input area (item still remains, as a reference of what was searched)
					$searchbox.jqxComboBox('clearSelection');

					if(airline_info_visible)
						$("#airline_info").fadeOut();
					airline_info_visible = false;

					return false;
				}

    			// To avoid reloading repeatedly when using keyboard navigation, introduce a delay into reading airline info
        		donrey.final_Delay('get_airline', function() {
        			// last "airline_id" is used
        			$.get("?action=plugin&plugin=airlinesearch&op=get_airline&id="+ airline_id, function(x) {


        				if(!x.success) {
        					if(airline_info_visible)
        						$("#airline_info").fadeOut();
        					airline_info_visible = false;

        					return cargohub.its.alert(x.reason, "Error !");
        				}

    					// The issue type choice or auto-select
    					if(x.airline.ord.ist && x.airline.ord.ist.select == true) {
    						var ist_choices = {}, choice;
    						for(c=0; c< x.airline.ord.ist.list.length; c++) {
    							choice = x.airline.ord.ist.list[c];
    								ist_choices[choice] = self.config.all_ist[choice.toString()];
    						}
    						x.airline.ist_choices = ist_choices;
    					}
    					if(x.airline.ord.ist && x.airline.ord.ist.select == false)
    						x.airline.ist_preselect = self.config.all_ist[x.airline.ord.ist.auto];

    					var check_awb = typed_value.replace(/[^0-9]+/g, ''), is_awb = false, awbp = null, awbs = null;

    					// was an MAWB typed ? Put it above the IST choice.]
    					if(check_awb.length == 11) {
    						awbp = check_awb.substr(0, 3);
    						awbs = check_awb.substr(3, 8);
    						if(parseInt(awbs.substr(0, 7))%7 != parseInt(awbs.substr(7, 1))) {
    							cargohub.its.alert("You have entered a Master AWB number ("+awbp+'-'+awbs+"), but it appears it's incorrect, please check !");
    						} else {
    							is_awb = true;
    							x.airline.mawb = awbp + '-' + awbs;
    						}
    					}

    					if(x.airline.status == 'nosubmit' || x.airline.status == 'hidden')
    						x.airline.nosubmit = true;

    					// show the airline info area (or change what is visible if something else was already visible)
    					$("#cms_airlinesearch #airline_info").html($("#template_airlineinfo").html().present(x.airline));
    					if(!airline_info_visible)
    						$("#cms_airlinesearch #airline_info").fadeIn();

    					airline_info_visible = true;

    					// setup action on the submit button
    					$('#go_submit').off('click').on('click', function() {
    						// build the redirect URL out of the data from x.airline
    						var url = "https://" + x.airline.domain + "/";
/*
    						if(x.airline.ord.ist && x.airline.ord.ist.select == true) {
    							url += "&ist=" + $("div#ist_choose select").val();
    						}

    						// add the group ID to the url, so it can be autoselected as well in case the form supports group selection
    						url += "&group=" + x.airline.group_id;

    						// check if the typed value is an AWB by using regex, if so, pass it as well
    						if(is_awb)
    							url += "mawb=" + awbp + '-' + awbs;
    						else
    							url += "mawbp=" + x.airline.AWB_prefix;
*/    						

    						// ... aaaand GO !
    						window.top.location = url;
    					});

    					$('#no_submit').off('click').on('click', function() {
    						cargohub.its.alert($(this).attr('title'));
    					});
        			});

        		}, 1000);
            }
		});
		/**
		 * Event: Pressing a key - Test if the enter key was pressed, and auto-select first entry in search results
		 */
/*		$("#cms_airlinesearch form").on('keypress', function(event) {
			dd(event);
			if(event.keyCode == 13) {
				if(!$searchbox.jqxComboBox('getSelectedItem')) {
					// when something was not selected when pressing enter, check if there is only one choice and pick it
					if(source_setup.records.length == 1) {
						$searchbox.jqxComboBox('selectIndex', 0);
					} else {
						// if there are multiple choices, deny the event to the combobox
						$searchbox.jqxComboBox('open');
					}
				}
				// do not allow the form to be submitted by pressing enter !
				event.preventDefault();
				event.stopPropagation();
				return false;
			}
		});
		
*/	
$('.container.manual .container-left.calc').text('Maximum Compensation');
// const label = document.querySelector(
//     '.container.manual .container-left.calc'
// );

// const datepicker = document.querySelector(
//     '.container.manual #datepicker'
// );

// if (label && datepicker) {
//     const dateRow = datepicker.parentElement;
//     dateRow.insertAdjacentElement('afterend', label);
// }

const container = document.querySelector('.container.manual');

if (container) {
    // Remove the equals signs
    container.querySelectorAll('#fnl_rr').forEach(el => {
        if (el.textContent.trim() === '=') {
            el.remove();
        }
    });

    // Find the Exchange Date row
    const datepicker = container.querySelector('#datepicker');

    // Find Maximum Compensation
    const label = container.querySelector('.container-left.calc');

    if (datepicker && label) {
        const dateRow = datepicker.closest('.container-left');

        if (dateRow) {
            // Keep the label below Exchange Date
            dateRow.appendChild(label);
            label.style.marginTop = '8px !important';
         }
    }
}
}

	;
	this._public({
		/**
		 * Section initialization
		 */
		init: function()
		{
			setup_search();
		}
	});
});