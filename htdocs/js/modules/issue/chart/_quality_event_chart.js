
let totalRecordData = [];
const dashboardData = [];
const orgId = new URLSearchParams(window.location.search).get("org_id");
//console.log(orgId);
showLoader('.chart-loader');
$.post("/user/overview?action=Load_QualityEvent_Chart", {
    rt_id: '31',
    rt_key: 'event',
    period: 'Months',
    org_id:orgId,
}, function (x) {
//    console.log(x);
    totalRecordData = convertTotalRecordChartData(x.getTotalRecordChartData);
    var records_per_group = x.getEventPerCategoryData;
    var xfield_enum_inv_done = x.getInvestigationStatus;
    var xfield_enum_severity = x.getEventSeverity;
    var xfield_set_risk_class = x.getRiskClassification;
    var records_per_comm = x.getCommodityType;
    var records_per_irr = x.getIrregularityCode;

//    console.log(xfield_enum_inv_done);
    dashboardData.total_records = {source: totalRecordData};
    dashboardData.records_per_group = {series: records_per_group.series, source: records_per_group.source};
    dashboardData.xfield_enum_inv_done = {series: xfield_enum_inv_done.series, source: xfield_enum_inv_done.source};
    dashboardData.xfield_enum_severity = {series: xfield_enum_severity.series, source: xfield_enum_severity.source};
    dashboardData.xfield_set_risk_class = {series: xfield_set_risk_class.series, source: xfield_set_risk_class.source};
    dashboardData.records_per_comm = {series: records_per_comm.series, source: records_per_comm.source};
    dashboardData.records_per_irr = {series: records_per_irr.series, source: records_per_irr.source};
    initAllCharts();

setTimeout(() => {
            hideLoader('.chart-loader');
        }, 1200);
}).fail(cargohub.its.http_fail);

function convertTotalRecordChartData(data) {
    const activeObj = data.Active || {};
    const closedObj = data.Closed || {};
    const keys = [...new Set([
            ...Object.keys(activeObj),
            ...Object.keys(closedObj)
        ])];
    const monthOrder = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

//    keys.sort((a, b) => {
//        if (!isNaN(a) && !isNaN(b))
//            return Number(a) - Number(b);
//
//        // MONTH sorting
//        if (monthOrder.includes(a) && monthOrder.includes(b)) {
//            return monthOrder.indexOf(a) - monthOrder.indexOf(b);
//        }
//
//        // QUARTER sorting (Apr-Jun)
//        if (a.includes("-") && b.includes("-")) {
//            return a.localeCompare(b);
//        }
//
//        return a.localeCompare(b);
//    });

    return keys.map(key => ({
            Month: key,
            Active: activeObj[key] || 0,
            Closed: closedObj[key] || 0
        }));
}




$(document).on('change', '.chartSelectionTimePeriod,.tableSelectionTimePeriod', function () {
    console.log($(this).attr('data-for'));
    var filter = {
        type: $(this).attr('data-for'),
        period: $(this).val()
    };
    if (filter.type == 'TotalRecord') {
        getTotalRecordData(filter);
    }
    if (filter.type == 'EventsperCategory') {
        getEventsperCategoryData(filter);
    }
    if (filter.type == 'InvestigationStatus') {
        getInvestigationStatusData(filter);
    }
    if (filter.type == 'EventSeverity') {
        getEventSeverityData(filter);
    }
    if (filter.type == 'RiskClassification') {
        getRiskClassificationData(filter);
    }
    if (filter.type == 'CommodityType') {
        getCommodityTypeData(filter);
    }
    if (filter.type == 'IrregularityCode') {
        getIrregularityCodeData(filter);
    }


});



function getTotalRecordData(filter) {
    let card = $(this).closest(".chart-card");
    showLoader(card);

    $.post("/user/overview?action=Load_QualityEventChartByType", {
        rt_id: '31',
        rt_key: 'event',
        period: filter.period,
        data_for: filter.type,
    }, function (x) {
//    console.log(x);
        totalRecordData = convertTotalRecordChartData(x.getTotalRecordChartData);
        dashboardData.total_records = {source: totalRecordData};
        createTotalRecordsChart();
        createGenericTable('tableTotalRecords', dashboardData.total_records.source);
        setTimeout(() => {
            hideLoader(card);
        }, 1200);

    }).fail(cargohub.its.http_fail);
}

function getEventsperCategoryData(filter) {
    let card = $(this).closest(".chart-card");
    showLoader(card);

    $.post("/user/overview?action=Load_QualityEventChartByType", {
        rt_id: '31',
        rt_key: 'event',
        period: filter.period,
        data_for: filter.type,
    }, function (x) {
var records_per_group = x.getEventPerCategoryData;
    dashboardData.records_per_group = {series: records_per_group.series, source: records_per_group.source};
    createLineChart('chartEventCategory', dashboardData.records_per_group);
    createGenericTable('tableEventCategory', dashboardData.records_per_group);
        setTimeout(() => {
            hideLoader(card);
        }, 1200);

    }).fail(cargohub.its.http_fail);
}


function getInvestigationStatusData(filter) {
    let card = $(this).closest(".chart-card");
    showLoader(card);

    $.post("/user/overview?action=Load_QualityEventChartByType", {
        rt_id: '31',
        rt_key: 'event',
        period: filter.period,
        data_for: filter.type,
    }, function (x) {
var xfield_enum_inv_done = x.getInvestigationStatus;
dashboardData.xfield_enum_inv_done = {series: xfield_enum_inv_done.series, source: xfield_enum_inv_done.source};
    createLineChart('chartInvestigation', dashboardData.xfield_enum_inv_done);
    createGenericTable('tableInvestigation', dashboardData.xfield_enum_inv_done);
        setTimeout(() => {
            hideLoader(card);
        }, 1200);

    }).fail(cargohub.its.http_fail);
}

function getEventSeverityData(filter) {
    let card = $(this).closest(".chart-card");
    showLoader(card);

    $.post("/user/overview?action=Load_QualityEventChartByType", {
        rt_id: '31',
        rt_key: 'event',
        period: filter.period,
        data_for: filter.type,
    }, function (x) {
var xfield_enum_severity = x.getEventSeverity;
dashboardData.xfield_enum_severity = {series: xfield_enum_severity.series, source: xfield_enum_severity.source};
    createLineChart('chartEventSeverity', dashboardData.xfield_enum_severity);
    createGenericTable('tableEventSeverity', dashboardData.xfield_enum_severity);
        setTimeout(() => {
            hideLoader(card);
        }, 1200);

    }).fail(cargohub.its.http_fail);
}


function getRiskClassificationData(filter) {
    let card = $(this).closest(".chart-card");
    showLoader(card);

    $.post("/user/overview?action=Load_QualityEventChartByType", {
        rt_id: '31',
        rt_key: 'event',
        period: filter.period,
        data_for: filter.type,
    }, function (x) {
var xfield_set_risk_class = x.getRiskClassification;
dashboardData.xfield_set_risk_class = {series: xfield_set_risk_class.series, source: xfield_set_risk_class.source};
    createLineChart('chartRiskClassification', dashboardData.xfield_set_risk_class);
    createGenericTable('tableRiskClassification', dashboardData.xfield_set_risk_class);
        setTimeout(() => {
            hideLoader(card);
        }, 1200);

    }).fail(cargohub.its.http_fail);
}


function getCommodityTypeData(filter) {
    let card = $(this).closest(".chart-card");
    showLoader(card);

    $.post("/user/overview?action=Load_QualityEventChartByType", {
        rt_id: '31',
        rt_key: 'event',
        period: filter.period,
        data_for: filter.type,
    }, function (x) {
var records_per_comm = x.getCommodityType;
dashboardData.records_per_comm = {series: records_per_comm.series, source: records_per_comm.source};
    createLineChart('chartCommodityType', dashboardData.records_per_comm);
    createGenericTable('tableCommodityType', dashboardData.records_per_comm);
        setTimeout(() => {
            hideLoader(card);
        }, 1200);

    }).fail(cargohub.its.http_fail);
}


function getIrregularityCodeData(filter) {
    let card = $(this).closest(".chart-card");
    showLoader(card);

    $.post("/user/overview?action=Load_QualityEventChartByType", {
        rt_id: '31',
        rt_key: 'event',
        period: filter.period,
        data_for: filter.type,
    }, function (x) {
var records_per_irr = x.getIrregularityCode;
dashboardData.records_per_irr = {series: records_per_irr.series, source: records_per_irr.source};
    createLineChart('chartIrregularityCode', dashboardData.records_per_irr);
    createGenericTable('tableIrregularityCode', dashboardData.records_per_irr);
        setTimeout(() => {
            hideLoader(card);
        }, 1200);

    }).fail(cargohub.its.http_fail);
}



const datalabelsScript = document.createElement("script");
datalabelsScript.src = "https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0";
datalabelsScript.onload = () => {
    Chart.register(ChartDataLabels);
};
document.head.appendChild(datalabelsScript);





function initAllCharts() {
    createTotalRecordsChart();
    createLineChart('chartEventCategory', dashboardData.records_per_group);
    createLineChart('chartInvestigation', dashboardData.xfield_enum_inv_done);
    createLineChart('chartEventSeverity', dashboardData.xfield_enum_severity);
    createLineChart('chartRiskClassification', dashboardData.xfield_set_risk_class);
    createLineChart('chartCommodityType', dashboardData.records_per_comm);
    createLineChart('chartIrregularityCode', dashboardData.records_per_irr);

    createGenericTable('tableTotalRecords', dashboardData.total_records.source);
    createGenericTable('tableEventCategory', dashboardData.records_per_group);
    createGenericTable('tableInvestigation', dashboardData.xfield_enum_inv_done);
    createGenericTable('tableEventSeverity', dashboardData.xfield_enum_severity);
    createGenericTable('tableRiskClassification', dashboardData.xfield_set_risk_class);
    createGenericTable('tableCommodityType', dashboardData.records_per_comm);
    createGenericTable('tableIrregularityCode', dashboardData.records_per_irr);
}

/********************************************
 * TOTAL RECORDS CHART
 ********************************************/
let totalRecordsChart = null;

function createTotalRecordsChart() {
    console.log('createTotalRecordsChart');
    const card = document.querySelector('.chart-card');
    if (!card)
        return;

// Create flex wrapper if not exists
    let flexContainer = card.querySelector('.chart-flex');
    if (!flexContainer) {
        flexContainer = document.createElement('div');
        flexContainer.className = 'chart-flex';
        flexContainer.style.display = 'flex';
        flexContainer.style.width = '100%';
        flexContainer.style.height = '100%';
        flexContainer.style.gap = '20px'; // 20px gap
        card.appendChild(flexContainer);
    }

// Chart area (80%)
    let chartArea = flexContainer.querySelector('.chart-area');
    if (!chartArea) {
        chartArea = document.createElement('div');
        chartArea.className = 'chart-area';
        chartArea.style.width = '80%';
        chartArea.style.height = '100%';
        flexContainer.appendChild(chartArea);
    }

    const ctx = document.getElementById("chartTotalRecords");
    chartArea.appendChild(ctx);

// Legend area (20%)
    let legendContainer = flexContainer.querySelector('.chart-legend');
    if (!legendContainer) {
        legendContainer = document.createElement('div');
        legendContainer.className = 'chart-legend';
        legendContainer.style.width = '20%';
        legendContainer.style.height = '100%';
        legendContainer.style.overflowY = 'auto';
        legendContainer.style.paddingLeft = '10px';
        flexContainer.appendChild(legendContainer);
    }
    legendContainer.innerHTML = '';

// Destroy old chart if exists
    if (totalRecordsChart)
        totalRecordsChart.destroy();

    const labels = dashboardData.total_records.source.map(r => r.Month);
    const active = dashboardData.total_records.source.map(r => r.Active || 0);
    const closed = dashboardData.total_records.source.map(r => r.Closed || 0);

    totalRecordsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {label: 'Active', data: active, borderColor: '#1F77B4', fill: false, tension: 0.3},
                {label: 'Closed', data: closed, borderColor: '#FF7F0E', fill: false, tension: 0.3}
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {legend: {display: false}, datalabels: {
                    display: c => c.dataset.data[c.dataIndex] > 0,
                    color: "#fff",
                    font: {size: 11, weight: "bold"},
                    backgroundColor: c => c.dataset.borderColor,
                    borderRadius: 4,
                    padding: 4
                }},
            scales: {
                x: {grid: {drawTicks: false, drawBorder: false, color: "#e0e0e0"}, ticks: {color: "#333"}},
                y: {beginAtZero: true, grid: {drawTicks: false, drawBorder: false, color: "#e0e0e0"}, ticks: {color: "#333", stepSize: 5}}
            }
        }
    });

// Build checkbox legend with colored check/uncheck
    totalRecordsChart.data.datasets.forEach((ds, index) => {
        const row = document.createElement("label");
        row.style.display = "flex";
        row.style.alignItems = "center";
        row.style.marginBottom = "6px";
        row.style.cursor = "pointer";

        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.checked = true;
        checkbox.style.marginRight = "6px";
        checkbox.style.accentColor = ds.borderColor;

        checkbox.addEventListener("change", () => {
            const meta = totalRecordsChart.getDatasetMeta(index);
            meta.hidden = !checkbox.checked;
            totalRecordsChart.update();
            checkbox.style.accentColor = checkbox.checked ? ds.borderColor : "#888";
        });

        const text = document.createElement("span");
        text.textContent = ds.label;
        text.style.color = ds.borderColor;
        text.style.fontWeight = "600";

        row.appendChild(checkbox);
        row.appendChild(text);
        legendContainer.appendChild(row);
    });
}












let chartInstances = {}; // Store chart instances by canvas ID

function createLineChart(canvasId, dataObj) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    if (chartInstances[canvasId]) {
        chartInstances[canvasId].destroy();
    }

    const corporateColors = [
        "#1F77B4","#2CA02C","#7F7F7F","#BCBD22",
        "#AEC7E8","#98DF8A","#8C564B"
    ];

    /* ========== AUTO-DETECT TIME COLUMN ========== */
    let timeKey = null;
    if (dataObj.source.length) {
        const row = dataObj.source[0];
        if ("Month" in row) timeKey = "Month";
        else if ("Year" in row) timeKey = "Year";
        else if ("Quarter" in row) timeKey = "Quarter";
        else timeKey = Object.keys(row)[0];
    }

    const labels = dataObj.source.map(r => r[timeKey]);

    /* ========== DATASETS ========== */
    const datasets = dataObj.series.map((s, i) => {
        const values = dataObj.source.map(r => Number(r[s.field]) || 0);
        const hasPositiveValue = values.some(v => v > 0);

        return {
            label: s.label,
            data: values,
            hasPositiveValue, // ? used later
            borderColor: corporateColors[i % corporateColors.length],
            borderWidth: 3,
            pointBackgroundColor: corporateColors[i % corporateColors.length],
            pointBorderColor: "#fff",
            pointBorderWidth: 2,
            pointRadius: 6,
            tension: 0.35,
            fill: false,

            // ? dataset-level rule
            datalabels: {
                display: hasPositiveValue
            }
        };
    });

    /* TOP 3 auto visible (ONLY datasets with data) */
    const top3 = datasets
        .filter(d => d.hasPositiveValue)
        .slice()
        .sort((a, b) => b.data.reduce((x, y) => x + y, 0) - a.data.reduce((x, y) => x + y, 0))
        .slice(0, 3)
        .map(d => d.label);

    /* ========== CHART ========== */
    chartInstances[canvasId] = new Chart(ctx, {
        type: "line",
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: true },

                // per-point rule
                datalabels: {
                    formatter: value => {
                        const v = Number(value);
                        return v > 0 ? v : null;
                    },
                    color: "#fff",
                    font: { size: 11, weight: "bold" },
                    backgroundColor: ctx => ctx.dataset.borderColor,
                    borderRadius: 4,
                    padding: 4
                }
            },
            scales: {
                x: { grid: { color: "#e0e0e0" }, ticks: { color: "#333" } },
                y: { beginAtZero: true, grid: { color: "#e0e0e0" }, ticks: { color: "#333" } }
            }
        },
        plugins: [ChartDataLabels]
    });

    /* ========== FLEX LAYOUT + LEGEND ========== */
    const card = ctx.closest(".chart-card");

    let flexContainer = card.querySelector(".chart-flex");
    if (!flexContainer) {
        flexContainer = document.createElement("div");
        flexContainer.className = "chart-flex";
        flexContainer.style.display = "flex";
        flexContainer.style.width = "100%";
        flexContainer.style.height = "100%";
        flexContainer.style.gap = "20px";
        card.appendChild(flexContainer);
    }

    let chartArea = card.querySelector(".chart-area");
    if (!chartArea) {
        chartArea = document.createElement("div");
        chartArea.className = "chart-area";
        chartArea.style.width = "80%";
        chartArea.style.height = "100%";
        flexContainer.appendChild(chartArea);
        chartArea.appendChild(ctx);
    }

    let legendContainer = card.querySelector(".chart-legend");
    if (!legendContainer) {
        legendContainer = document.createElement("div");
        legendContainer.className = "chart-legend";
        legendContainer.style.width = "20%";
        legendContainer.style.height = "100%";
        legendContainer.style.overflowY = "auto";
        legendContainer.style.padding = "10px 5px";
        legendContainer.style.display = "flex";
        legendContainer.style.flexDirection = "column";
        legendContainer.style.gap = "8px";
        flexContainer.appendChild(legendContainer);
    }

    legendContainer.innerHTML = "";

    /* LEGEND — ONLY DATASETS WITH > 0 VALUES */
    datasets.forEach((ds, index) => {
        if (!ds.hasPositiveValue) return; // ? skip empty datasets

        const isChecked = top3.includes(ds.label);

        const row = document.createElement("label");
        row.style.display = "flex";
        row.style.alignItems = "center";
        row.style.cursor = "pointer";

        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.checked = isChecked;
        checkbox.style.marginRight = "8px";
        checkbox.style.accentColor = isChecked ? ds.borderColor : "#888";

        const meta = chartInstances[canvasId].getDatasetMeta(index);
        meta.hidden = !isChecked;

        checkbox.addEventListener("change", () => {
            meta.hidden = !checkbox.checked;
            chartInstances[canvasId].update();
            checkbox.style.accentColor = checkbox.checked ? ds.borderColor : "#888";
        });

        const text = document.createElement("span");
        text.textContent = ds.label;
        text.style.color = ds.borderColor;
        text.style.fontWeight = "600";

        row.appendChild(checkbox);
        row.appendChild(text);
        legendContainer.appendChild(row);
    });

    chartInstances[canvasId].update();
}







function createGenericTable(containerId, dataObj) {
    var $container = $('#' + containerId);
    $container.empty();

    var $table = $('<table>').css({
        width: '100%',
        borderCollapse: 'collapse',
        fontSize: '14px',
        fontFamily: 'Arial, sans-serif'
    });

    var $thead = $('<thead>');
    var $tbody = $('<tbody>');

    /* AUTO-DETECT TIME COLUMN */
    let timeKey = "Month";

    if (dataObj.source && dataObj.source.length) {
        var row = dataObj.source[0];
        if ("Month" in row) timeKey = "Month";
        else if ("Year" in row) timeKey = "Year";
        else if ("Quarter" in row) timeKey = "Quarter";
    } else if (dataObj.length) {
        var row = dataObj[0];
        if ("Month" in row) timeKey = "Month";
        else if ("Year" in row) timeKey = "Year";
        else if ("Quarter" in row) timeKey = "Quarter";
    }

    /* Extract months/years/quarters */
    var months = dataObj.source
        ? dataObj.source.map(r => r[timeKey])
        : dataObj.map(r => r[timeKey]);

    /* Extract series info */
    var seriesFields = [];

    if (dataObj.series && dataObj.source) {
        seriesFields = dataObj.series.map(s => ({
            field: s.field,
            label: s.label
        }));
    } else {
        var keys = Object.keys(dataObj[0]).filter(k => k !== timeKey);
        seriesFields = keys.map(k => ({ field: k, label: k }));
    }

    /* HEADER ROW */
    var $headerRow = $('<tr>');
    $headerRow.append(
        $('<th>').css({
            backgroundColor: "#e8f2fa",
            color: "#1e4e72",
            padding: "8px",
            border: "1px solid #d6e2eb"
        })
    );

    months.forEach(m => {
        $headerRow.append(
            $('<th>')
                .text(m)
                .css({
                    backgroundColor: "#e8f2fa",
                    color: "#1e4e72",
                    padding: "8px",
                    border: "1px solid #d6e2eb"
                })
        );
    });

    $thead.append($headerRow);
    $table.append($thead);

    /* BODY ROWS */
    seriesFields.forEach(s => {
        var $tr = $('<tr>');

        $tr.append(
            $('<td>')
                .text(s.label)
                .css({
                    backgroundColor: "#e8f2fa",
                    color: "#1e4e72",
                    fontWeight: "bold",
                    padding: "8px",
                    textAlign: "left",
                    border: "1px solid #e1e5e8"
                })
        );

        months.forEach((m, j) => {
            var row = dataObj.source ? dataObj.source[j] : dataObj[j];
            var value = row[s.field] || 0;

            $tr.append(
                $('<td>')
                    .text(value)
                    .css({
                        padding: "8px",
                        textAlign: "center",
                        border: "1px solid #e1e5e8",
                        backgroundColor: value > 0 ? "#fafbfc" : "",
                    })
            );
        });

        $tbody.append($tr);
    });

    $table.append($tbody);
    $container.append($table);
}






$(document).ready(function () {
    // On tab click
    $('.qec-tab').on('click', function () {
        $('.qec-tab').removeClass('active');
        $(this).addClass('active');
        $('.qec-tab-content').removeClass('active');
        var target = $(this).data('target');
        $(target).addClass('active');
    });
    $('.qec-tab').first().click();
});

// Toggle dropdown visibility on button click
document.querySelectorAll('.dropdown-toggle').forEach(button => {
    button.addEventListener('click', function () {
        const dropdown = this.parentElement;
        dropdown.classList.toggle('open');
    });
});

document.addEventListener('click', function (e) {
    document.querySelectorAll('.dropdown-checkbox').forEach(dropdown => {
        if (!dropdown.contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });
});

(function () {
    function loadScript(url, callback) {
        let s = document.createElement('script');
        s.src = url;
        s.onload = callback;
        document.body.appendChild(s);
    }

    loadScript("https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js", function () {
//        console.log("html2canvas loaded");
        loadScript("https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js", function () {
//            console.log("jsPDF loaded");
        });
    });

})();

$(document).on("click", ".chart-download", function () {
//    console.log("PDF download clicked");
    let parent = $(this).closest(".qec-card");
    if (parent.length === 0) {
        console.warn("No qec-card parent found!");
        return;
    }
    if (typeof html2canvas === "undefined" || typeof window.jspdf === "undefined") {
        console.log("Required libraries not loaded yet!");
        return;
    }
    let title = parent.find("h3").first().text().trim() || "dashboard-section";
    title = title.replace(/[^a-z0-9\- ]/gi, '').replace(/\s+/g, '-').toLowerCase();
    let btn = $(this);
    btn.hide();
    $('.filter-item').hide();
    html2canvas(parent[0], {
        backgroundColor: "#ffffff",
        scale: 2,
    }).then(canvas => {
        btn.show();
        $('.filter-item').show();
        const {jsPDF} = window.jspdf;
        const pdf = new jsPDF("p", "mm", "a4");
        const imgData = canvas.toDataURL("image/png");
        const pageWidth = 210;
        const pageHeight = 297;
        let imgWidth = pageWidth - 20;
        let imgHeight = (canvas.height * imgWidth) / canvas.width;
        if (imgHeight > pageHeight - 20) {
            imgHeight = pageHeight - 20;
            imgWidth = (canvas.width * imgHeight) / canvas.height;
        }
        pdf.addImage(imgData, "PNG", 10, 10, imgWidth, imgHeight);
        pdf.save(title + ".pdf");

    });
});


// show & hide functions that accept jQuery objects or selectors
function showLoader($card) {
    // ensure jQuery object
    $card = ($card instanceof jQuery) ? $card : $($card);
    var $loader = $card.find('.chart-loader');
    if ($loader.length === 0)
        return;
    $loader.css('display', 'flex').attr('aria-hidden', 'false');
}

function hideLoader($card) {
    $card = ($card instanceof jQuery) ? $card : $($card);
    var $loader = $card.find('.chart-loader');
    if ($loader.length === 0)
        return;
    $loader.hide().attr('aria-hidden', 'true');
}

// attach handler to selects (delegated)
$('#tabChartView, #tabDataView').on('change', 'select', function () {
    var $card = $(this).closest('.chart-card, .table-card');
    showLoader($card);

    // simulate async load ï¿½ replace with AJAX/chart refresh
    setTimeout(function () {
        // update chart here...
        hideLoader($card);
    }, 1200);
});




