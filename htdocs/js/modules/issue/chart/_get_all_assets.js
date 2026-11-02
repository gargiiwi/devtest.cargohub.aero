(function () {
    let tableInstance = null;
    let currentStatus = 'active';

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
            link.href = "/js/components/datatable2/datatables.min.css";
            document.head.appendChild(link);
        }
        let script = document.createElement("script");
        script.src = "/js/components/datatable2/datatables.min.js";
        script.onload = callback;
        document.body.appendChild(script);
    }
    $(document).ready(function () {
        setTimeout(function () {
            try {
                loadDataTable(function () {
                    try {
                         initAssetReport();
                    } catch (e) {
                        console.error("initAssetReport failed", e);
                    }
                });
            } catch (e) {
                console.error("Page initialization failed", e);
            }

        }, 1000);

    });

    function initAssetReport() {
        bindStatusFilter();
        bindSearch();
        bindApplyFilter();
        bindResetFilter();
        bindExport();
        loadSupplierFilter();
        loadGroupFilter();
        currentStatus = 'active';
        $('.crv-status-btn').removeClass('active');
        $('.crv-status-btn[data-status="active"]').addClass('active');
        loadTable();
    }
    function loadSupplierFilter() {
        $.ajax({
            url: "/record/process?action=load_suppliers",
            type: "POST",
            dataType: "json",
            success: function (response) {
                let rows = response.data || [];
                $("#filter_supplier").html(
                        '<option value="">All Suppliers</option>'
                        );
                rows.forEach(function (row) {
                    $("#filter_supplier").append(
                            '<option value="' + row.value + '">' +
                            row.label +
                            '</option>'
                            );
                });
            }
        });
    }

    $(document).off("change", "#filter_supplier").on("change", "#filter_supplier", function () {
        var supplierId = $(this).val();
        $("#filter_contract").html('<option value="">All Contract</option>');
        if (!supplierId) {
            return;
        }
        $.ajax({
            url: "/record/process?action=load_supplier_contracts",
            type: "POST",
            dataType: "json",
            data: {
                supplier_id: supplierId
            },
            success: function (response) {
                var rows = response.data || [];
                rows.forEach(function (row) {
                    $("#filter_contract").append('<option value="' + row.value + '">' + row.label + '</option>');
                });
            }
        });
    });

    function loadGroupFilter() {
        $.ajax({
            url: "/record/process?action=load_groups",
            type: "POST",
            data: {
                group_type: 'asset'
            },
            dataType: "json",
            success: function (response) {
                let rows = response.data || [];
                $("#filter_group").html('<option value="">All Type</option>');
                rows.forEach(function (row) {
                    $("#filter_group").append('<option value="' + row.id + '">' + row.name + '</option>');
                });
            }
        });
    }

    function loadTable() {
        if (tableInstance) {
            return;
        }
        $('#asset_report_table').html(`
            <table id="asset_table" class="display nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Asset Track</th>
                        <th>Asset Name</th>
                        <th>Location</th>
                        <th>Service Expiry</th>
                        <th>Supplier Name</th>
                        <th>Contract Name</th>
                        <th>Contract Nr</th>
                        <th>Contract Start Date</th>
                        <th>Contract End Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
            </table>
        `);
        tableInstance = $('#asset_table').DataTable({
            processing: true,
            serverSide: false,
            dom: 't<"bottom d-flex justify-content-between align-items-center mt-2"ilp>',
            buttons: [{
                    extend: 'excelHtml5',
                    title: 'Asset_Management_Report',
                    text: 'Export Excel',
                    className: 'd-none',
                    exportOptions: {
                        columns: ':visible,:hidden'
                    }
                }],
            destroy: true,
            pageLength: 50,
            lengthChange: true,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            searching: true,
            ordering: true,
            responsive: false,
            scrollX: true,
            scrollCollapse: true,
            autoWidth: false,
            ajax: {
                url: "/record/process?action=load_all_asset_management",
                type: "POST",
                cache: false,
                data: function (d) {
                    d.status = currentStatus;
                    d.supplier_id = $("#filter_supplier").val();
                    d.contract_id = $("#filter_contract").val();
                    d.group = $("#filter_group").val();
                    d.end_date = $("#filter_end_date").val();
                },
                dataSrc: function (json) {
                    let rows = json.data || [];
                    $("#kpi_total_assets").html(json.total_assets || rows.length);
                    $("#kpi_active_assets").html(json.total_active || 0);
                    $("#kpi_inactive_assets").html(json.total_inactive || 0);
                    updateKPIs(rows);
                    updateDateKPIs(rows);
                    return rows;
                }
            },
            columns: [
                {
                    data: 'asset_track',
                    render: function (data) {
                        return '<a target="_blank" href="/record/process:' + data + '#panels">' + data + '</a>';
                    }
                },
                {data: 'asset_name'},
                {data: 'location'},
                {data: 'service_expiry',
    render: function (data, type, row) {

        if (!data || data == 0) {
            return '';
        }

        var date = new Date(parseInt(data, 10) * 1000); // Unix timestamp (seconds)

        var day = ('0' + date.getDate()).slice(-2);
        var month = ('0' + (date.getMonth() + 1)).slice(-2);
        var year = date.getFullYear();

        return day + '-' + month + '-' + year;
    }
},
//                {
//                    data: 'supplier_track',
//                    render: function (data) {
//                        return '<a target="_blank" href="/record/process:' + data + '#panels">' + data + '</a>';
//                    }
//                },
                {data: 'supplier_name'},
//                {data: 'supplier_group'},
                {data: 'contract_name'},
                {data: 'contract_nr'},
                {data: 'start_date'},
                {data: 'end_date'},
//                {
//                    data: 'purchase_price',
//                    render: function (d) {
//                        return parseFloat(d || 0).toFixed(2) + " EUR";
//                    }
//                },
//                {
//                    data: 'rest_price',
//                    render: function (d) {
//                        return parseFloat(d || 0).toFixed(2) + " EUR";
//                    }
//                },
//                {
//                    data: 'yearly_cost',
//                    render: function (d) {
//                        return parseFloat(d || 0).toFixed(2) + " EUR";
//                    }
//                },
//                {
//                    data: 'monthly_cost',
//                    render: function (d) {
//                        return parseFloat(d || 0).toFixed(2) + " EUR";
//                    }
//                },
                {
                    data: 'status',
                    visible: false,
                    render: function (data) {
                        if (data === 'active') {
                            return `
                            <span style="
                                padding:4px 10px;
                                background:#dcfce7;
                                color:#15803d;
                                border-radius:20px;
                                font-size:12px;
                                font-weight:600;
                            ">
                                ACTIVE
                            </span>
                        `;
                        }
                        return `
                        <span style="
                            padding:4px 10px;
                            background:#fee2e2;
                            color:#b91c1c;
                            border-radius:20px;
                            font-size:12px;
                            font-weight:600;
                        ">
                            INACTIVE
                        </span>
                    `;
                    }
                }
            ],
            initComplete: function () {
                console.log("Asset table loaded");
            }
        });
    }

    function updateKPIs(rows) {
        let warranty30 = 0;
        let warranty60 = 0;
        let warranty90 = 0;
        let totalOwned = 0;
        let totalLeased = 0;
        let totalAssigned = 0;
        let totalPurchaseValue = 0;
        let totalCurrentValue = 0;
        let today = new Date();
        rows.forEach(function (row) {
            totalPurchaseValue += parseFloat(row.purchase_value || 0);
            totalCurrentValue += parseFloat(row.current_value || 0);
            if (row.asset_type === 'Owned') {
                totalOwned++;
            }
            if (row.asset_type === 'Leased') {
                totalLeased++;
            }
            if (row.asset_status === 'Assigned') {
                totalAssigned++;
            }
            if (row.warranty_date) {
                let warrantyDate = new Date(row.warranty_date);
                let diffDays = Math.ceil(
                        (warrantyDate - today) / (1000 * 60 * 60 * 24));
                if (diffDays > 0 && diffDays <= 30) {
                    warranty30++;
                } else if (diffDays > 30 && diffDays <= 60) {
                    warranty60++;
                } else if (diffDays > 60 && diffDays <= 90) {
                    warranty90++;
                }
            }
        });
        // Total Asset Value
        $("#kpi_asset_value_total").html(totalPurchaseValue.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + " &euro;");
        // Asset Type KPI
        $("#kpi_owned_assets").html(totalOwned);
        $("#kpi_leased_assets").html(totalLeased);
        $("#kpi_assigned_assets").html(totalAssigned);
        // Warranty KPI
        $("#kpi_warranty_total").html(warranty30 + warranty60 + warranty90);
        $("#kpi_warranty_30").html(warranty30);
        $("#kpi_warranty_60").html(warranty60);
        $("#kpi_warranty_90").html(warranty90);
        // Optional Current Value KPI
        $("#kpi_current_asset_value").html(totalCurrentValue.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + " &euro;");
    }
    function updateDateKPIs(rows) {

    var today = new Date();
    today.setHours(0,0,0,0);

    var service30 = 0,
        service60 = 0,
        service90 = 0,
        serviceExpired = 0,
        serviceTotal = 0;

    var end30 = 0,
        end60 = 0,
        end90 = 0,
        endEnded = 0,
        endTotal = 0;

    rows.forEach(function(row){

        // ---------- Service Expiry ----------
        if (row.service_expiry && row.service_expiry != 0) {

    serviceTotal++;

    var serviceDate = new Date(parseInt(row.service_expiry, 10) * 1000);
    serviceDate.setHours(0,0,0,0);

    var diffDays = Math.floor((serviceDate - today) / 86400000);

    if (diffDays < 0) {
        serviceExpired++;
    } else {
        if (diffDays <= 30) service30++;
        if (diffDays <= 60) service60++;
        if (diffDays <= 90) service90++;
    }
}

        // ---------- Contract End Date ----------
        if (row.end_date) {

    endTotal++;

    var endDate = new Date(row.end_date);
    endDate.setHours(0,0,0,0);

    var diffDays = Math.floor((endDate - today) / 86400000);

    if (diffDays < 0) {
        endEnded++;
    } else {
        if (diffDays <= 30) end30++;
        if (diffDays <= 60) end60++;
        if (diffDays <= 90) end90++;
    }
}
    });

    // Service Expiry KPI
    $("#kpi_service_expiry_total").text(serviceTotal);
    $("#kpi_service_expiry_30").text(service30);
    $("#kpi_service_expiry_60").text(service60);
    $("#kpi_service_expiry_90").text(service90);
    $("#kpi_service_expired").text(serviceExpired);

    // Upcoming End Date KPI
    $("#kpi_upcoming_end_date_total").text(endTotal);
    $("#kpi_upcoming_end_date_30").text(end30);
    $("#kpi_upcoming_end_date_60").text(end60);
    $("#kpi_upcoming_end_date_90").text(end90);
    $("#kpi_upcoming_ended").text(endEnded);
}

    function bindExport() {
        $(document).off('click', '.crv-btn-export').on('click', '.crv-btn-export', function () {
            if (tableInstance) {
                tableInstance.button('.buttons-excel').trigger();
            }
        });
    }

    function bindStatusFilter() {
        $(document).off('click', '.crv-status-btn').on('click', '.crv-status-btn', function () {
            $('.crv-status-btn').removeClass('active');
            $(this).addClass('active');
            currentStatus = $(this).data('status');
            if (tableInstance) {
                tableInstance.ajax.reload();
            }
        });
    }

    function bindSearch() {
        $(document).off('keyup', '#asset_search').on('keyup', '#asset_search', function () {
            let value = $(this).val();
            if (tableInstance) {
                tableInstance.search(value).draw();
            }
        });
    }

    function bindApplyFilter() {
        $(document).off('click', '#btn_apply_filter').on('click', '#btn_apply_filter', function () {
            if (tableInstance) {
                tableInstance.ajax.reload();
            }
        });
    }

    function bindResetFilter() {
        $(document).off('click', '#btn_reset_filter').on('click', '#btn_reset_filter', function () {
            $("#assetReportPage select").val("");
            $("#assetReportPage input[type='date']").val("");
            $("#asset_search").val("");
            currentStatus = "active";
            $('.crv-status-btn').removeClass('active');
            $('.crv-status-btn[data-status="active"]').addClass('active');
            if (tableInstance) {
                tableInstance.search("").draw();
                tableInstance.ajax.reload();
            }
        });
    }
})();
