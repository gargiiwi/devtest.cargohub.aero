(function () {

    let tableInstance = null;
    // ================= LOAD DATATABLE =================
    function loadDataTable(callback) {

        if (typeof jQuery === "undefined") {
            console.error("jQuery not loaded");
            return;
        }

        if ($.fn && $.fn.DataTable) {
            callback();
            return;
        }

        if (!document.querySelector('#dt_css')) {
            let link = document.createElement("link");
            link.id = "dt_css";
            link.rel = "stylesheet";
            link.href = "/js/components/datatable2/datatables.min.css?v=2026.05.04.0428";
            document.head.appendChild(link);
        }

        let script = document.createElement("script");
        script.src = "/js/components/datatable2/datatables.min.js?v=2026.05.04.0428";
        script.onload = callback;
        document.body.appendChild(script);
    }

    // ================= INIT =================
    $(document).ready(function () {
        loadDataTable(function () {
            initCV4();
        });
    });
    // ================= MAIN INIT =================
    function initCV4() {
        bindTabs();
        bindStatusFilter();
        populateYearDropdown();
        bindAnalyticsFilter();


        loadTable("active");
    }

    // ================= TABS =================
    let chartsLoaded = false;
    function bindTabs() {

        $('.cv4-tab').off('click').on('click', function () {

            if ($(this).hasClass('active'))
                return;
            $('.cv4-tab').removeClass('active');
            $(this).addClass('active');
            let tab = $(this).data('tab');
            $('.cv4-content').removeClass('active').hide();
            $('#cv4_' + tab).fadeIn(150).addClass('active');
            // ? LOAD CHARTS ONLY WHEN NEEDED
            if (tab === "analytics" && !chartsLoaded) {
                loadCharts('12');
                chartsLoaded = true;
            }
        });
    }

    // ================= STATUS FILTER =================
    function bindStatusFilter() {

        $(document).off('click', '.cv4-status').on('click', '.cv4-status', function () {

            $('.cv4-status').removeClass('active');
            $(this).addClass('active');
            let status = $(this).data('status');
            updateKpiTitles(status);
            loadTable(status);
        });
    }
    // ================= TABLE =================
    function loadTable(status) {

        if (tableInstance) {
            tableInstance.destroy();
            $('#cv4_tableWrap').empty();
        }

        $('#cv4_tableWrap').html(`
            <table id="cv4_tableData" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Risk</th>
                        <th>Track</th>
                        <th>Person</th>
                        <th>Created</th>
                        <th>Deadline</th>
                        <th>Closing</th>
                        <th>Title</th>
                        <th>Area</th>
                        <th>Report</th>
                        <th>Type</th>
                        <th>Result</th>
                        
                    </tr>
                </thead>
            </table>
        `);
        tableInstance = $('#cv4_tableData').DataTable({

            pageLength: 50,
            lengthChange: false,
            destroy: true,
            processing: true,
            dom: '<"top"f>rt<"bottom"ip>',
            buttons: [
                {
                    extend: 'excel',
                    text: '<i class="fa fa-file-excel-o"></i>',
                    titleAttr: 'Export Excel'
                }
            ],
            ajax: {
                url: "/record/process?action=LoadCaPaReportListing",
                type: "POST",
                data: function (d) {
                    d.status = status;
                },
                dataSrc: function (json) {

                    let data = json.auditActivityData || [];
                    // ? ONLY ON FIRST LOAD
                    if (!window.__kpiInitialized) {
                        initGlobalKPI(data);
                        window.__kpiInitialized = true;
                    }

                    updateFilteredKPIs(data, status);
                    return data;
                }
            },
            columns: [
                {data: "id_val"},
                {data: "risk"},
                {data: "track"},
                {data: "person"},
                {data: "created_time"},
                {data: "deadline_date"},
                {data: "closing_date"},
                {data: "title"},
                {data: "cache_primary"},
                {data: "report_type"},
                {data: "type"},
                {data: "result"}
                // {data: "status"}
            ],
            initComplete: function () {
                $('.cv4-center').empty().html($('.dt-search'));
                // move export button into right
                $('.cv4-right').html(tableInstance.buttons().container());
                toggleColumns(status);
            }

        });
    }

    // ================= COLUMN VISIBILITY FIXED =================
    function toggleColumns(status) {

        if (!tableInstance)
            return;
        // show all first
        tableInstance.columns().visible(true);
        if (status === "active") {
            tableInstance.column(6).visible(false); // Closing hidden
        }

        if (status === "closed") {
            tableInstance.column(5).visible(false); // Deadline hidden
        }
    }

    // ================= CARDS =================
    function updateFilteredKPIs(data) {

        let finding = 0, observation = 0, opportunity = 0;
        let minor = 0, major = 0, critical = 0;
        let assigned = 0, unassigned = 0;
        data.forEach(row => {

            if (row.type === "Finding")
                finding++;
            if (row.type === "Observation")
                observation++;
            if (row.type === "Improvement Opportunity")
                opportunity++;
            if (row.risk === "Minor")
                minor++;
            if (row.risk === "Major")
                major++;
            if (row.risk === "Critical")
                critical++;
            if (row.person && row.person.trim() !== "")
                assigned++;
            else
                unassigned++;
        });
        var typeTotal = data.filter(r =>
            ["Finding", "Observation", "Improvement Opportunity"].includes(r.type)
        ).length;
        $('#kpi_type_total').text(typeTotal);
        $('#kpi_finding').text(finding);
        $('#kpi_observation').text(observation);
        $('#kpi_opportunity').text(opportunity);
        $('#kpi_risk_total').text(minor + major + critical);
        $('#kpi_minor').text(minor);
        $('#kpi_major').text(major);
        $('#kpi_critical').text(critical);
        $('#kpi_assign_total').text(assigned + unassigned);
        $('#kpi_assigned').text(assigned);
        $('#kpi_unassigned').text(unassigned);
    }
    function updateKpiTitles(status) {

        let suffix = "";
        if (status === "active") {
            suffix = " (Active)";
        }

        if (status === "closed") {
            suffix = " (Closed)";
        }

        $("#title_action_report").text("Total Actions" + suffix);
        $("#title_type").text("Type Wise Report" + suffix);
        $("#title_risk").text("Risk Wise Report" + suffix);
        $("#title_assign").text("Assignment" + suffix);
    }


    function initGlobalKPI(data) {
        $.ajax({
            url: "/record/process?action=LoadCaPaTotalRecordCount",
            type: "POST",
            data: {
                period: "all",
                report_type: "all",
                status_type: "all"
            },
            success: function (res) {
                $('#kpi_total').text(res.total_count);
                $('#kpi_active').text(res.total_active);
                $('#kpi_closed').text(res.total_close);
            }
        });

    }
    // ================= CHARTS =================

    function loadCharts(range) {

        // fade charts
        $('#cv4_analytics canvas').css('opacity', 0.3);

        chart_renderTotalChart(range);
        chart_renderTypeWiseChart(range);
        chart_renderRiskWiseChart(range);

//        $.ajax({
//            url: "/record/process?action=Load_CaPa_Analytics",
//            type: "POST",
//            data: {range: range},
//            success: function (res) {
//
//                let data = (res && Array.isArray(res.data)) ? res.data : [];
//
//                chart_updateAnalyticsKPI(data);
//
//                chart_renderTotalChart(data);
//                chart_renderStatusChart(data);
//                chart_renderTypeChart(data);
//                chart_renderRiskChart(data);
//                chart_renderAreaChart(data);
//                chart_renderActionChart(data);
//
//                $('#cv4_analytics canvas').css('opacity', 1);
//            }
//        });


        $('#cv4_analytics canvas').css('opacity', 1);
    }

    function chart_renderTotalChart(range) {
        $.ajax({
            url: "/record/process?action=LoadCaPaTotalRecordCount",
            type: "POST",
            data: {
                period: range,
                report_type: "all",
                status_type: "all"
            },
            success: function (res) {
                $('#an_total').text(res.total_count);
                $('#an_active').text(res.total_active);
                $('#an_closed').text(res.total_close);

                if (!res.dataBrk || Object.keys(res.dataBrk).length === 0) {

                $('#cv4_totalChart').hide();
                        $('#cv4_totalChartEmpty').removeClass('d-none');
                        $('#cv4_activeClosedChart').hide();
                        $('#cv4_activeClosedChartEmpty').removeClass('d-none');
                } else {
                $('#cv4_totalChart').show();
                        $('#cv4_totalChartEmpty').addClass('d-none');
                        $('#cv4_activeClosedChart').show();
                        $('#cv4_activeClosedChartEmpty').addClass('d-none');
                        var labels = Object.keys(res.dataBrk);
                        var activeData = labels.map(m => Number(res.dataBrk[m]?.active || 0));

                var closeData = labels.map(m => Number(res.dataBrk[m]?.close || 0));


                createChart_v2(
                        'cv4_totalChart',
                        'bar',
                        labels,
                        [
                            {
                                label: 'Active',
                                data: activeData,
                                backgroundColor: '#36A2EB',
                                borderRadius: 6
                            },
                            {
                                label: 'Close',
                                data: closeData,
                                backgroundColor: '#FF6384',
                                borderRadius: 6
                            },
                        ]
                        );


                createChart_v2(
                        'cv4_activeClosedChart',
                        'doughnut',
                        ['Active', 'Closed'],
                        [
                            {
                                data: [res.total_active, res.total_close],
                                backgroundColor: ['#36A2EB', '#FF6384']
                            },
                              {
                                cutout: '70%' 
                                
                            }
                        ]
                        );
            }
            }
        });
    }
    
   

/* =========================================================
   TYPE WISE
========================================================= */

let typeBarChart = null;
let typePieChart = null;
function chart_renderTypeWiseChart(range) {

    $.ajax({

        url: "/record/process?action=LoadCaPaTypeWiseData",
        type: "POST",
        dataType: "json",

        data: {
            period: range,
            report_type: "all",
            status_type: "all"
        },

        success: function (res) {

            if (!res.dataBrk || Object.keys(res.dataBrk).length === 0) {

                $('#cv4_typeChart').hide();
                $('#cv4_typePieChart').hide();

                $('#cv4_typeChartEmpty').removeClass('d-none');
                $('#cv4_typePieChartEmpty').removeClass('d-none');

                $('#an_finding').text(0);
                $('#an_observation').text(0);
                $('#an_opportunity').text(0);
                $('#an_type_total').text(0);

                return;
            }

            $('#cv4_typeChart').show();
            $('#cv4_typePieChart').show();

            $('#cv4_typeChartEmpty').addClass('d-none');
            $('#cv4_typePieChartEmpty').addClass('d-none');

            let currentStatus = 'active';

            function renderTypeCharts(status) {

                let labels = Object.keys(res.dataBrk);

                let filteredLabels = [];

                let findingData = [];
                let observationData = [];
                let opportunityData = [];

                let findingTotal = 0;
                let observationTotal = 0;
                let opportunityTotal = 0;

                labels.forEach(month => {

                    let monthData = res.dataBrk[month] || {};

                    let f = Number((monthData.finding || {})[status] || 0);
                    let o = Number((monthData.observation || {})[status] || 0);
                    let p = Number((monthData.opportunity || {})[status] || 0);

                    if (f === 0 && o === 0 && p === 0) return;

                    filteredLabels.push(month);

                    findingData.push(f);
                    observationData.push(o);
                    opportunityData.push(p);

                    findingTotal += f;
                    observationTotal += o;
                    opportunityTotal += p;
                });

                // =========================
                // KPI
                // =========================

                $('#an_finding').text(findingTotal);
                $('#an_observation').text(observationTotal);
                $('#an_opportunity').text(opportunityTotal);

                $('#an_type_total').text(
                    findingTotal + observationTotal + opportunityTotal
                );

                // TITLE
                let statusLabel = {
                    active: 'Active',
                    closed: 'Closed'
                }[status] || status;

                $('#type_kpi_title').text(`Type Wise Report (${statusLabel})`);

                // DESTROY OLD
                if (typeBarChart) typeBarChart.destroy();
                if (typePieChart) typePieChart.destroy();

                // BAR
                let barDatasets = [];

                if (findingTotal > 0) {
                    barDatasets.push({
                        label: 'Finding',
                        data: findingData,
                        backgroundColor: '#1565C0',
                        borderRadius: 6
                    });
                }

                if (observationTotal > 0) {
                    barDatasets.push({
                        label: 'Observation',
                        data: observationData,
                        backgroundColor: '#C62828',
                        borderRadius: 6
                    });
                }

                if (opportunityTotal > 0) {
                    barDatasets.push({
                        label: 'Opportunity',
                        data: opportunityData,
                        backgroundColor: '#F9A825',
                        borderRadius: 6
                    });
                }

                // PIE
                let pieLabels = [];
                let pieData = [];
                let pieColors = [];

                if (findingTotal > 0) {
                    pieLabels.push('Finding');
                    pieData.push(findingTotal);
                    pieColors.push('#1565C0');
                }

                if (observationTotal > 0) {
                    pieLabels.push('Observation');
                    pieData.push(observationTotal);
                    pieColors.push('#C62828');
                }

                if (opportunityTotal > 0) {
                    pieLabels.push('Opportunity');
                    pieData.push(opportunityTotal);
                    pieColors.push('#F9A825');
                }

                typeBarChart = createChart_v2(
                    'cv4_typeChart',
                    'bar',
                    filteredLabels,
                    barDatasets
                );

                typePieChart = createChart_v2(
                    'cv4_typePieChart',
                    'doughnut',
                    pieLabels,
                    [{
                        data: pieData,
                        backgroundColor: pieColors,
                        cutout: '70%'
                    }]
                );
            }

            renderTypeCharts(currentStatus);

            $('.typeChartFilter')
                .off('click')
                .on('click', function () {

                    $('.typeChartFilter').removeClass('active');
                    $(this).addClass('active');

                    currentStatus = $(this).data('status');
                    renderTypeCharts(currentStatus);
                });
        }
    });
}


/* =========================================================
   RISK WISE
========================================================= */


let riskBarChart = null;
let riskPieChart = null;

function chart_renderRiskWiseChart(range) {

    $.ajax({

        url: "/record/process?action=LoadCaPaRiskWiseData",
        type: "POST",
        dataType: "json",

        data: {
            period: range,
            report_type: "all",
            status_type: "all"
        },

        success: function (res) {

            if (!res.dataBrk || Object.keys(res.dataBrk).length === 0) {

                $('#cv4_riskChart').hide();
                $('#cv4_riskPieChart').hide();

                $('#cv4_riskChartEmpty').removeClass('d-none');
                $('#cv4_riskPieChartEmpty').removeClass('d-none');

                $('#an_risk_total').text(0);
                $('#an_minor').text(0);
                $('#an_major').text(0);
                $('#an_critical').text(0);

                return;
            }

            $('#cv4_riskChart').show();
            $('#cv4_riskPieChart').show();

            $('#cv4_riskChartEmpty').addClass('d-none');
            $('#cv4_riskPieChartEmpty').addClass('d-none');

            let currentStatus = 'active';

            function renderRiskCharts(status) {

                let labels = Object.keys(res.dataBrk);

                let filteredLabels = [];

                let datasetsMap = {};
                let totalsMap = {};

                // ? FIXED ORDER (IMPORTANT)
                const riskOrder = ['MI', 'MA', 'CR'];

                const riskColors = {
                    MI: '#F9A825', // Minor
                    MA: '#1565C0', // Moderate
                    CR: '#C62828'  // Critical
                };

                const riskLabels = {
                    MI: 'Minor',
                    MA: 'Moderate',
                    CR: 'Critical'
                };

                // =========================
                // BUILD DATA
                // =========================

                labels.forEach(month => {

                    let monthData = res.dataBrk[month] || {};

                    let monthTotal = 0;

                    Object.keys(monthData).forEach(riskType => {

                        let value =
                            Number((monthData[riskType] || {})[status] || 0);

                        monthTotal += value;
                    });

                    if (monthTotal === 0) return;

                    filteredLabels.push(month);

                    Object.keys(monthData).forEach(riskType => {

                        let value =
                            Number((monthData[riskType] || {})[status] || 0);

                        if (!datasetsMap[riskType]) {

                            datasetsMap[riskType] = {
                                label: riskLabels[riskType] || riskType,
                                data: [],
                                backgroundColor: riskColors[riskType] || '#607D8B',
                                borderRadius: 6
                            };

                            totalsMap[riskType] = 0;
                        }

                        datasetsMap[riskType].data.push(value);
                        totalsMap[riskType] += value;
                    });

                    Object.keys(datasetsMap).forEach(riskType => {

                        if (datasetsMap[riskType].data.length < filteredLabels.length) {
                            datasetsMap[riskType].data.push(0);
                        }
                    });
                });

                // =========================
                // ORDERED DATASETS (FIX)
                // =========================

                let orderedDatasets = [];

                riskOrder.forEach(key => {
                    if (datasetsMap[key]) {
                        orderedDatasets.push(datasetsMap[key]);
                    }
                });

                // =========================
                // KPI UPDATE
                // =========================

                $('#an_minor').text(totalsMap['MI'] || 0);
                $('#an_major').text(totalsMap['MA'] || 0);
                $('#an_critical').text(totalsMap['CR'] || 0);

                $('#an_risk_total').text(
                    (totalsMap['MI'] || 0) +
                    (totalsMap['MA'] || 0) +
                    (totalsMap['CR'] || 0)
                );

                // =========================
                // TITLE
                // =========================

                let statusLabel = {
                    active: 'Active',
                    closed: 'Closed'
                }[status] || status;

                $('#risk_kpi_title').text(
                    `Risk Wise Report (${statusLabel})`
                );

                // =========================
                // DESTROY OLD
                // =========================

                if (riskBarChart) riskBarChart.destroy();
                if (riskPieChart) riskPieChart.destroy();

                // =========================
                // PIE DATA (ORDERED)
                // =========================

                let pieLabels = [];
                let pieData = [];
                let pieColors = [];

                let legendHtml = '';

                riskOrder.forEach(key => {

                    if (!datasetsMap[key]) return;

                    let ds = datasetsMap[key];

                    pieLabels.push(ds.label);
                    pieData.push(totalsMap[key]);
                    pieColors.push(ds.backgroundColor);

                    legendHtml += `
                        <span class="legend-item">
                            <span class="legend-color"
                                  style="background:${ds.backgroundColor};"></span>
                            ${ds.label}
                        </span>
                    `;
                });

                $('#riskLegend').html(legendHtml);
                $('#riskPieLegend').html(legendHtml);

                // =========================
                // CHARTS
                // =========================

                riskBarChart = createChart_v2(
                    'cv4_riskChart',
                    'bar',
                    filteredLabels,
                    orderedDatasets
                );

                riskPieChart = createChart_v2(
                    'cv4_riskPieChart',
                    'doughnut',
                    pieLabels,
                    [{
                        data: pieData,
                        backgroundColor: pieColors,
                        cutout: '70%'
                    }]
                );
            }

            renderRiskCharts(currentStatus);

            $('.riskChartFilter')
                .off('click')
                .on('click', function () {

                    $('.riskChartFilter').removeClass('active');
                    $(this).addClass('active');

                    currentStatus = $(this).data('status');
                    renderRiskCharts(currentStatus);
                });
        }
    });
}
    function createChart_v2(id, type, labels, datasets) {

        let el = document.getElementById(id);

        if (!el)
            return;

        if (el._chart)
            el._chart.destroy();

        el._chart = new Chart(el, {
            type: type,
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,

                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: false
                    },

                    datalabels: {
                        display: true,
                        color: '#111',

                        font: {
                            weight: 'bold',
                            size: 12
                        },

                        formatter: function (value) {

                            // prevent NaN
                            if (value === null || value === undefined || isNaN(value))
                                return '';

                            return value;
                        }
                    }
                },

                scales: type === 'bar'
                        ? {
                            y: {
                                beginAtZero: true
                            }
                        }
                : {}
            },

            plugins: [ChartDataLabels]
        });
    }
    // ================= LOAD CHARTS =================
    function loadCharts_adrija(range) {


        $('#cv4_analytics canvas').css('opacity', 0.3);

        // ---------- TOTAL ----------
        $.ajax({
            url: "/record/process?action=LoadGetTotalRecords",
            type: "POST",
            data: {
                period: range,
                report_type: "all",
                status_type: "all"
            },
            success: function (res) {

                let flatData = convertToKpiFormat(res.chart_data);

                updateAnalyticsKPI(flatData);

                renderTotalChart(res.chart_data);
            }
        });

        // ---------- TYPE ----------
        $.ajax({
            url: "/record/process?action=LoadGetTypeWise",
            type: "POST",
            data: {
                period: range,
                report_type: "all",
                status_type: "all"
            },
            success: function (res) {

                updateTypeKPI(res.chart_data);

                renderTypeCharts(res.chart_data);
            }
        });

        // ---------- RISK ----------
        $.ajax({
            url: "/record/process?action=LoadGetRiskTypeWise",
            type: "POST",
            data: {
                period: range,
                report_type: "all",
                status_type: "all"
            },
            success: function (res) {
                updateRiskKPI(res.chart_data);
                renderRiskCharts(res.chart_data);
            }
        });

        setTimeout(() => {
            $('#cv4_analytics canvas').css('opacity', 1);
        }, 400);
    }
    function getDemoAnalyticsData(range) {

        let data = [];
        let months = range === '12m' ? 12 : (range === '5y' ? 60 : 100);
        let types = ['Finding', 'Observation', 'Opportunity'];
        let risks = ['Minor', 'Major', 'Critical'];
        let status = ['Active', 'Closed'];
        let areas = ['Operations', 'Quality', 'IT', 'Safety'];
        let actions = ['Corrective', 'Preventive', 'Investigation'];
        for (let i = 0; i < months * 5; i++) {

            let randomMonth = generateRandomMonth(months);
            data.push({
                month: randomMonth,
                type: types[Math.floor(Math.random() * types.length)],
                risk: risks[Math.floor(Math.random() * risks.length)],
                status: status[Math.floor(Math.random() * status.length)],
                area: areas[Math.floor(Math.random() * areas.length)],
                action_type: actions[Math.floor(Math.random() * actions.length)]
            });
        }

        return data;
    }

    function generateRandomMonth(maxMonthsBack) {

        let now = new Date();
        let past = new Date();
        past.setMonth(now.getMonth() - Math.floor(Math.random() * maxMonthsBack));
        let year = past.getFullYear();
        let month = String(past.getMonth() + 1).padStart(2, '0');
        return `${year}-${month}`;
    }

    // ================= CHART HELPER =================

    function updateAnalyticsKPI(data) {

        let total = data.length;
        let active = 0, closed = 0;
        let finding = 0, observation = 0, opportunity = 0;
        let minor = 0, major = 0, critical = 0;
        let operations = 0, quality = 0, it = 0;
        data.forEach(d => {

            // status
            if (d.status === "Active")
                active++;
            if (d.status === "Closed")
                closed++;
            // type
            if (d.type === "Finding")
                finding++;
            if (d.type === "Observation")
                observation++;
            if (d.type === "Opportunity")
                opportunity++;
            // risk
            if (d.risk === "Minor")
                minor++;
            if (d.risk === "Major")
                major++;
            if (d.risk === "Critical")
                critical++;
            // area
            if (d.area === "Operations")
                operations++;
            if (d.area === "Quality")
                quality++;
            if (d.area === "IT")
                it++;
        });
        // TOTAL
        $('#an_total').text(total);
        $('#an_active').text(active);
        $('#an_closed').text(closed);
        // TYPE
        $('#an_type_total').text(finding + observation + opportunity);
        $('#an_finding').text(finding);
        $('#an_observation').text(observation);
        $('#an_opportunity').text(opportunity);
        // RISK
        $('#an_risk_total').text(minor + major + critical);
        $('#an_minor').text(minor);
        $('#an_major').text(major);
        $('#an_critical').text(critical);
        // AREA
        $('#an_area_total').text(operations + quality + it);
        $('#an_operations').text(operations);
        $('#an_quality').text(quality);
        $('#an_it').text(it);
    }
    function updateTypeKPI(data) {

        let finding = 0, observation = 0, opportunity = 0;

        for (let type in data) {
            for (let month in data[type]) {
                for (let status in data[type][month]) {

                    let val = data[type][month][status];

                    if (type.toLowerCase() === "finding")
                        finding += val;
                    if (type.toLowerCase() === "observation")
                        observation += val;
                    if (type.toLowerCase() === "opportunity")
                        opportunity += val;
                }
            }
        }

        $('#an_type_total').text(finding + observation + opportunity);
        $('#an_finding').text(finding);
        $('#an_observation').text(observation);
        $('#an_opportunity').text(opportunity);
    }
    function updateRiskKPI(data) {

        let minor = 0, major = 0, critical = 0;

        for (let risk in data) {
            for (let month in data[risk]) {
                for (let status in data[risk][month]) {

                    let val = data[risk][month][status];

                    if (risk.toLowerCase() === "minor")
                        minor += val;
                    if (risk.toLowerCase() === "major")
                        major += val;
                    if (risk.toLowerCase() === "critical")
                        critical += val;
                }
            }
        }

        $('#an_risk_total').text(minor + major + critical);
        $('#an_minor').text(minor);
        $('#an_major').text(major);
        $('#an_critical').text(critical);
    }
    function groupByMonth(data) {

        let map = {};
        data.forEach(d => {

            let m = d.month;
            if (!m && d.created_time) {
                m = d.created_time.substring(0, 7); // YYYY-MM
            }

            if (!m)
                m = "Unknown";
            if (!map[m])
                map[m] = 0;
            map[m]++;
        });
        return {
            labels: Object.keys(map),
            values: Object.values(map)
        };
    }


    function createChart(id, type, labels, data) {

        let el = document.getElementById(id);
        if (!el)
            return;

        if (el._chart)
            el._chart.destroy();

        el._chart = new Chart(el, {
            type: type,
            data: {
                labels: labels,
                datasets: [{
                        data: data,
                        borderRadius: 6
                    }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {display: false},

                    datalabels: {
                        display: true, // ? always show
                        color: '#111',
                        font: {
                            weight: 'bold',
                            size: 12
                        },

                        formatter: function (value) {
                            return value;       // ? prints value
                        }
                    }
                },
                scales: type === 'bar' ? {
                    y: {beginAtZero: true}
                } : {}
            },
            plugins: [ChartDataLabels]   // ? IMPORTANT
        });
    }

    function bindAnalyticsFilter() {
        $('#cv4_timeDropdown').off('change').on('change', function () {

            let range = $(this).val();
            loadCharts(range);
        });
    }
    // function renderTotalChart(data) {

    //     if (!data.length) {
    //         $('#cv4_totalChart').parent().html('<p style="text-align:center;color:#999">No data</p>');
    //         return;
    //     }

    //     let monthly = groupByMonth(data);
    //     createChart(
    //             'cv4_totalChart',
    //             'bar',
    //             monthly.labels,
    //             monthly.values
    //             );
    // }

    function convertToKpiFormat(data) {
        let arr = [];

        Object.keys(data).forEach(category => {
            Object.keys(data[category]).forEach(month => {

                let m = data[category][month];

                // Active
                for (let i = 0; i < (m.active || 0); i++) {
                    arr.push({
                        status: "Active"
                    });
                }

                // Closed
                for (let i = 0; i < (m.closed || 0); i++) {
                    arr.push({
                        status: "Closed"
                    });
                }

            });
        });

        return arr;
    }
    function renderTotalChart(data) {

        if (!data || Object.keys(data).length === 0) {
            $('#cv4_totalChart').parent().html('<p>No data</p>');
            return;
        }

        let categoryLabels = [];
        let categoryValues = [];

        let totalActive = 0;
        let totalClosed = 0;

        Object.keys(data).forEach(category => {

            let total = 0;

            Object.keys(data[category]).forEach(month => {

                let m = data[category][month];

                let active = Number(m.active || 0);
                let closed = Number(m.closed || 0);

                total += active + closed;

                totalActive += active;
                totalClosed += closed;

            });

            categoryLabels.push(category);
            categoryValues.push(total);
        });


        createChart('cv4_totalChart', 'bar', categoryLabels, categoryValues);
        createChart(
                'cv4_activeClosedChart',
                'doughnut',
                ['Active', 'Closed'],
                [totalActive, totalClosed]
                );
    }
// function renderTotalChart(data) {

//     if (!data || Object.keys(data).length === 0) {
//         $('#cv4_totalChart').parent().html('<p>No data</p>');
//         return;
//     }

//     let labels = [];
//     let values = [];

//     Object.keys(data).forEach(category => {

//         let total = 0;

//         Object.keys(data[category]).forEach(month => {

//             let m = data[category][month];

//             if (m.active) total += Number(m.active);
//             if (m.closed) total += Number(m.closed);
//         });

//         labels.push(category);   // Investigation, Corrective, Preventive
//         values.push(total);
//     });

//     createChart('cv4_totalChart', 'bar', labels, values);
// }
//   function renderStatusPieChart(data) {

//     let totalActive = 0;
//     let totalClosed = 0;

//     Object.keys(data).forEach(category => {
//         Object.keys(data[category]).forEach(month => {

//             const m = data[category][month];

//             totalActive += Number(m.active || 0);
//             totalClosed += Number(m.closed || 0);

//         });
//     });

//     // destroy old chart (IMPORTANT)
//     let el = document.getElementById('cv4_statusPieChart');
//     if (el && el._chart) {
//         el._chart.destroy();
//     }

//     createChart(
//         'cv4_statusPieChart',
//         'doughnut',
//         ['Active', 'Closed'],
//         [totalActive, totalClosed]
//     );
// }
    // function renderTypeChart(data) {

    //     let map = {};
    //     data.forEach(d => {
    //         map[d.type] = (map[d.type] || 0) + 1;
    //     });
    //     createChart(
    //             'cv4_typeChart',
    //             'bar',
    //             Object.keys(map),
    //             Object.values(map)
    //             );
    //     createChart(
    //             'cv4_typePieChart',
    //             'doughnut',
    //             Object.keys(map),
    //             Object.values(map)
    //             );
    // }
    function renderTypeCharts(data) {

        if (!data || Object.keys(data).length === 0) {
            $('#cv4_typeChart').parent().html('<p style="text-align:center;color:#999">No data</p>');
            return;
        }

        let map = {
            Finding: 0,
            Observation: 0,
            Opportunity: 0
        };

        for (let type in data) {
            let formatted = type.charAt(0).toUpperCase() + type.slice(1).toLowerCase();

            for (let month in data[type]) {
                for (let status in data[type][month]) {
                    map[formatted] += data[type][month][status];
                }
            }
        }

        let labels = Object.keys(map);
        let values = Object.values(map);
        createChart('cv4_typeChart', 'bar', labels, values);
        createChart('cv4_typePieChart', 'doughnut', labels, values);
    }
    function renderRiskCharts(data) {

        if (!data || Object.keys(data).length === 0) {
            $('#cv4_riskChart').parent().html('<p>No data</p>');
            return;
        }

        let map = {
            Minor: 0,
            Major: 0,
            Critical: 0
        };

        for (let risk in data) {
            let formatted = risk.charAt(0).toUpperCase() + risk.slice(1).toLowerCase();

            for (let month in data[risk]) {
                for (let status in data[risk][month]) {
                    map[formatted] += data[risk][month][status];
                }
            }
        }

        let labels = Object.keys(map);
        let values = Object.values(map);

        createChart('cv4_riskChart', 'bar', labels, values);
        createChart('cv4_riskPieChart', 'doughnut', labels, values);
    }
    // function renderAreaChart(data) {

    //     let map = {};
    //     data.forEach(d => {
    //         map[d.area] = (map[d.area] || 0) + 1;
    //     });
    //     createChart(
    //             'cv4_areaChart',
    //             'bar',
    //             Object.keys(map),
    //             Object.values(map)
    //             );
    // }
    // function renderActionChart(data) {

    //     let map = {};
    //     data.forEach(d => {
    //         map[d.action_type] = (map[d.action_type] || 0) + 1;
    //     });
    //     createChart(
    //             'cv4_actionChart',
    //             'doughnut',
    //             Object.keys(map),
    //             Object.values(map)
    //             );
    // }


    function populateYearDropdown() {

        let currentYear = new Date().getFullYear();
        let dropdown = $('#cv4_timeDropdown');
        for (let i = 0; i < 3; i++) {              //capa_enhance_050526
            let year = currentYear - i;
            dropdown.append(`<option value="${year}">${year}</option>`);
        }
    }

    function filterLast12Months(data) {

        let now = new Date();
        let past = new Date();
        past.setMonth(now.getMonth() - 12);
        return data.filter(d => {
            let date = new Date(d.month + "-01");
            return date >= past;
        });
    }
    function filterByYear(data, year) {

        return data.filter(d => d.month.startsWith(year));
    }

    function updateAnalyticsTitle(range) {

        let text = "Last 12 Months";
        if (range === "all")
            text = "All Time";
        else if (!isNaN(range))
            text = range;
        // card title
        $('#an_title').text("Total Actions (" + text + ")");
        // ? header title
        $('#an_header').text("Analytics - " + text);
    }

})();