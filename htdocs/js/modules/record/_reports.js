const jqxScripts = [
    "jqxcore.js", "jqxdata.js", "jqxbuttons.js", "jqxscrollbar.js", "jqxmenu.js",
    "jqxlistbox.js", "jqxdropdownlist.js", "jqxcalendar.js", "jqxdatetimeinput.js",
    "jqxgrid.js", "jqxgrid.sort.js", "jqxgrid.filter.js", "jqxgrid.pager.js", "jqxgrid.selection.js",
    "jqxgrid.columnsresize.js"
];

function loadScriptsSequentially(scripts, callback) {
    if (scripts.length === 0)
        return callback();

    const script = scripts.shift();
    const path = "/js/lib/jqwidgets/" + script + "?v=3.9.0";
    $.getScript(path)
            .done(() => loadScriptsSequentially(scripts, callback))
            .fail(() => console.error("Failed to load:", path));
}

$(document).ready(function () {
    if (!$('#report_grid').length)
        return console.warn("#report_grid not found in DOM.");

    if ($('#docLicReportViewSec').length) {
        loadScriptsSequentially([...jqxScripts], function () {
            console.log("All jqWidgets scripts loaded. Running grid setup.");
            runGridSetup();
        });
    }
});

function runGridSetup() {
    cargohub.record_reports = donrey.BASE._extend(function () {
        const self = this;

        $("#docLic-report-by-chart").show();
        $(".simple-spinner-chart").hide();

        $('#License_Monitor_Tab').addClass('active');
        $('#withLinktab').addClass('active');
        $('#sub_tab_Documentation').hide();

        setTimeout(() => $('#doc_connected').click(), 200);
        $('#sub_tab_License_Monitor #license_withLinktab').click();

        $('.tab').on('click', function () {
            const tabName = $(this).data('tab');
            $('.tab').removeClass('active');
            $(this).addClass('active');

            if (tabName === 'license') {
                $('#sub_tab_Documentation').hide();
                $('#sub_tab_License_Monitor').show();
                $('#sub_tab_License_Monitor #doc_connected').addClass('active');
                $('#sub_tab_License_Monitor #doc_not_connected').removeClass('active');
                $('#sub_tab_License_Monitor #doc_connected').click();
            } else {
                $('#sub_tab_License_Monitor').hide();
                $('#sub_tab_Documentation').show();
                $('#sub_tab_Documentation #license_connected').addClass('active');
                $('#sub_tab_Documentation #license_not_connected').removeClass('active');
                $('#sub_tab_Documentation #license_connected').click();

            }
        });


        $('.subtab').on('click', function () {
            const subTabName = $(this).data('subtab');
            $('.subtab').removeClass('active');
            $(this).addClass('active');
            console.log('subTabName', subTabName);
            let url, datafields, columnTextMapping;
            if (subTabName === 'doc_connected') {
                url = "/record/reports?action=doc_connected";
            } else if (subTabName === 'doc_not_connected') {
                url = "/record/reports?action=doc_not_connected";
            } else if (subTabName === 'license_connected') {
                url = "/record/reports?action=license_connected";
            } else if (subTabName === 'license_not_connected') {
                url = "/record/reports?action=license_not_connected";
            }
            getGrid(url, datafields, columnTextMapping, subTabName);
        });

        $(".report-content").not(".active").hide();

        function getGrid(url, datafields, columnTextMapping, subTabName) {
            const source = new $.jqx.dataAdapter({
                datatype: "json",
                datafields: datafields,
                url: url,
                cache: false,
                root: "data",
                id: "id",
                formatData: function (data) {
                    const filterData = {};
                    try {
                        const filters = $("#report_grid").jqxGrid('getfilterinformation') || [];
                        filters.forEach(filter => {
                            const field = filter.datafield;
                            const filterGroup = filter.filter;
                            if (filterGroup && typeof filterGroup.getfilters === "function") {
                                const filterItems = filterGroup.getfilters();
                                if (filterItems.length > 0) {
                                    filterData[field] = filterItems[0].value;
                                }
                            }
                        });
                    } catch (e) {
                        console.error("Error getting filters:", e);
                    }
                    return {
                        pagesize: data.pagesize || 20,
                        recordstart: data.recordstart || 0,
                        filters: JSON.stringify(filterData),
                        show_closed: true,
                        nodescr: true
                    };
                }
            });

            let gridColumns;

            if (subTabName === 'doc_connected' || subTabName === 'doc_not_connected') {
                gridColumns = [
                    {datafield: 'license_link', text: 'License Track', width: 140, filtertype: 'input'},
                    {datafield: 'country_code', text: 'Country', width: 109, filtertype: 'input'},
                    {datafield: 'license_type', text: 'License Type', width: 250, filtertype: 'input'},
                    {datafield: 'name_or_title', text: 'Title', width: 300, filtertype: 'input'},
                    {datafield: 'chapter', text: 'Chapter', width: 250, filtertype: 'input'},
                    {datafield: 'section', text: 'Section', width: 120, filtertype: 'input'}
                ];
            } else if (subTabName === 'license_connected' || subTabName === 'license_not_connected') {
                gridColumns = [
                    {datafield: 'document_link', text: 'Document Track', width: 140, filtertype: 'input'},
                    {datafield: 'name_or_title', text: 'Title', width: 350, filtertype: 'input'},
                    {datafield: 'doctype_label', text: 'Document type', width: 120, filtertype: 'input'},
                    {datafield: 'publish_label', text: 'Published', width: 80, filtertype: 'input'},
                    {datafield: 'license_type', text: 'License Type', width: 140, filtertype: 'input'},
                    {datafield: 'valid_from', text: 'Reviewed on', width: 110, filtertype: 'input'},
                    {datafield: 'review_date', text: 'Expire Date', width: 110, filtertype: 'input'},
                    {datafield: 'next_review', text: 'Next Review', width: 120, filtertype: 'input'}
                ];
            }
//    else {
//        // Fallback for undefined subTabName
//        gridColumns = datafields.map(field => ({
//            datafield: field.name,
//            text: columnTextMapping[field.name] || field.name,
//            width: 130,
//            filtertype: 'input'
//        }));
//    }

            $("#report_grid").jqxGrid({
                theme: cargohub.jqxtheme,
                width: '100%',
                columnsResize: true,
                rowsheight: 27,
                autoheight: true,
                source: source,
                columns: gridColumns,
                filterable: true,
                showfilterrow: true,
                sortable: true,
                pageable: true,
                virtualmode: false,
                selectionmode: "singlerow",
                autoshowfiltericon: true,
                rowdetails: true,
                
    pagesize: 25,
    pagesizeoptions: ['25', '50', '100'],
                rowdetailstemplate: {
                    rowdetails: "<div class='nested-grid-container' style='margin: 10px;'></div>",
                    rowdetailsheight: 200,
                    rowdetailshidden: true
                },
                initrowdetails: function (index, parentElement, gridElement, datarecord) {
                    const container = $(parentElement).find(".nested-grid-container");
                    const nestedId = "nestedGrid_" + index;
                    container.append(`<div id="${nestedId}"></div>`);

                    let nestedSource, nestedColumns;
                    const subTabNameN = $('.subtab.active').data('subtab');
//console.log('subTabName==',subTabNameN);
                    if (subTabNameN === 'doc_connected') {
                        nestedSource = new $.jqx.dataAdapter({
                            datatype: "json",
                            datafields: [
                                {name: 'document_link', type: 'string'},
                                {name: 'name_or_title', type: 'string'},
                                {name: 'license_type', type: 'string'},
                                {name: 'valid_from', type: 'string'},
                                {name: 'next_review', type: 'string'}
                            ],
                            url: "/record/reports?action=doc_connected_nested&parent=" + datarecord.uid
                        });
                        nestedColumns = [
                            {datafield: 'document_link', text: 'Document Track', width: 140, filtertype: 'input'},
                            {datafield: 'name_or_title', text: 'Title', width: 350, filtertype: 'input'},
                            {datafield: 'doctype_label', text: 'Document type', width: 120, filtertype: 'input'},
                            {datafield: 'publish_label', text: 'Published', width: 80, filtertype: 'input'},
                            {datafield: 'license_type', text: 'License Type', width: 140, filtertype: 'input'},
                            {datafield: 'valid_from', text: 'Reviewed on', width: 110, filtertype: 'input'},
                            {datafield: 'review_date', text: 'Expire Date', width: 110, filtertype: 'input'},
                            {datafield: 'next_review', text: 'Next Review', width: 110, filtertype: 'input'}
                        ];
                    } else if (subTabNameN === 'license_connected') {
                        nestedSource = new $.jqx.dataAdapter({
                            datatype: "json",
                            datafields: [
                                {name: 'license_link', type: 'string'},
                                {name: 'license_type', type: 'string'},
                                {name: 'name_or_title', type: 'string'},
                                {name: 'chapter', type: 'string'}
                            ],
                            url: "/record/reports?action=license_connected_nested&parent=" + datarecord.uid
                        });
                        nestedColumns = [
                            {datafield: 'license_link', text: 'License Track', width: 140, filtertype: 'input'},
                            {datafield: 'country_code', text: 'Country', width: 80, filtertype: 'input'},
                            {datafield: 'license_type', text: 'License Type', width: 250, filtertype: 'input'},
                            {datafield: 'name_or_title', text: 'Title', width: 310, filtertype: 'input'},
                            {datafield: 'chapter', text: 'Chapter', width: 250, filtertype: 'input'},
                            {datafield: 'section', text: 'Section', width: 120, filtertype: 'input'}
                        ];
                    }

                    $(`#${nestedId}`).jqxGrid({
                        source: nestedSource,
                        width: '100%',
                        height: 180,
                        theme: cargohub.jqxtheme,
                        columns: nestedColumns
                    });
                },
                rendergridrows: function (params) {
                    return params.data;
                }
            })
                    .off("bindingcomplete")
                    .on("bindingcomplete", function () {
                        const checkReadyAndSort = () => {
                            const rows = $("#report_grid").jqxGrid('getrows');
                            if (rows && rows.length > 0 && rows[0].ctime !== undefined) {
                                $("#report_grid").jqxGrid('sortby', 'license_type', 'desc');
                            } else {
                                // Retry after a short delay if data not ready
                                setTimeout(checkReadyAndSort, 50);
                            }
                        };
                        checkReadyAndSort();
                    })
        }

    });
}