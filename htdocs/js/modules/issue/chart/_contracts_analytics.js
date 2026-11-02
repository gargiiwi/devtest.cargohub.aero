cargohub.issue_chart_contracts_analytics = donrey.BASE._extend(function () {
    var self = this;
    var groupData = [];
    var locationData = [];
    var countryData = [];
    var contracts = [];
    var caCurrentTab = 'group';
    var caCurrentSearch = '';
    $(document).ready(function () {
        setTimeout(function () {
            initContractAnalytics();
        }, 300);
    });
    function initContractAnalytics() {
        bindEvents();
        loadContractAnalytics();
    }
    function loadContractAnalytics() {
        $("#ca-total-contracts").text("0");
        $("#ca-active-contracts").text("0");
        $("#ca-expiring").text("0");
        $("#ca-total-value").text(formatEuro(0));
        getContractSummary();
        getContractGroupAnalytics();
        getContractLocationAnalytics();
        getContractCountryAnalytics();
    }
    function getContractGroupAnalytics() {
        $.ajax({
            url: "/record/process?action=getContractAnalytics",
            type: "POST",
            dataType: "json",
            data: {
                type: "getContractGroupAnalytics"
            },
            success: function (response) {
                if (!response || response.success !== true) {
                    groupData = [];
                    renderGroups([]);
                    return;
                }
                groupData = response.data || [];
                renderGroups(groupData);
            },
            error: function (xhr) {
                console.log("Contract Group Analytics Error:",
                        xhr.responseText
                        );
                groupData = [];
                renderGroups([]);
            }
        });
    }
    function getContractLocationAnalytics() {
        $.ajax({
            url: "/record/process?action=getContractAnalytics",
            type: "POST",
            dataType: "json",
            data: {
                type: "getContractLocationAnalytics"
            },
            success: function (response) {
                if (!response || response.success !== true) {
                    locationData = [];
                    renderLocations([]);
                    return;
                }
                locationData = response.data || [];
                renderLocations(locationData);
            },
            error: function (xhr) {
                console.log(
                        "Contract Location Analytics Error:",
                        xhr.responseText
                        );
                locationData = [];
                renderLocations([]);
            }
        });
    }
    function getContractCountryAnalytics() {
        $.ajax({
            url: "/record/process?action=getContractAnalytics",
            type: "POST",
            dataType: "json",
            data: {
                type: "getContractCountryAnalytics"
            },
            success: function (response) {
                if (!response || response.success !== true) {
                    countryData = [];
                    renderCountries([]);
                    return;
                }
                countryData = response.data || [];
                renderCountries(countryData);
            },
            error: function (xhr) {
                console.log(
                        "Contract Country Analytics Error:",
                        xhr.responseText
                        );
                countryData = [];
                renderCountries([]);
            }
        });
    }
    function getContractSummary() {
        $.ajax({
            url: "/record/process?action=getContractAnalytics",
            type: "POST",
            dataType: "json",
            data: {
                type: "getContractSummary"
            },
            success: function (response) {
                if (!response || response.success !== true || !response.data || !response.data.length) {
                    $("#ca-total-contracts").text("0");
                    $("#ca-active-contracts").text("0");
                    $("#ca-expiring").text("0");
                    $("#ca-total-value").text(formatEuro(0));
                    return;
                }
                var data = response.data[0];
                $("#ca-total-contracts").text(parseInt(data.total_contracts || 0, 10));
                $("#ca-active-contracts").text(parseInt(data.active_contracts || 0, 10));
                $("#ca-expiring").text(parseInt(data.expiring_contracts || 0, 10));
                $("#ca-total-value").text(formatEuro(data.annual_value || 0));
            },
            error: function (xhr) {
                console.log("Contract Summary Analytics Error:", xhr.responseText);
                $("#ca-total-contracts").text("0");
                $("#ca-active-contracts").text("0");
                $("#ca-expiring").text("0");
                $("#ca-total-value").text(formatEuro(0));
            }
        });
    }
    function renderGroups(data) {
        var html = "";
        var count = data.length;
        $("#ca-group-count").text(count + (count === 1 ? " Group" : " Groups"));
        $.each(data, function (index, item) {
            html += createAnalyticsCard(item, "group", "fa-sitemap");
        });
        $("#ca-group-list").html(html);
    }
    function renderLocations(data) {
        var html = "";
        var count = data.length;
        $("#ca-location-count").text(count + (count === 1 ? " Location" : " Locations"));
        $.each(data, function (index, item) {
            html += createAnalyticsCard(item, "location", "fa-map-marker");
        });
        $("#ca-location-list").html(html);
    }
    function renderCountries(data) {
        var html = "";
        var count = data.length;
        $("#ca-country-count").text(count + (count === 1 ? " Country" : " Countries"));
        $.each(data, function (index, item) {
            html += createAnalyticsCard(item, "country", "fa-globe");
        });
        $("#ca-country-list").html(html);
    }
    function createAnalyticsCard(item, type, icon) {
        var name = item.name || "";
        var total = parseInt(item.total_contracts || 0, 10);
        var active = parseInt(item.active_contracts || 0, 10);
        var closed = parseInt(item.closed_contracts || 0, 10);
        var annualValue = parseFloat(item.annual_value || 0);
        return '<div class="ca-card" ' + 'data-type="' + escapeHtml(type) + '" ' + 'data-name="' + escapeHtml(name) + '">' + '<div class="ca-card-top">' + '<div class="ca-card-name">' + escapeHtml(name) + '</div>' + '<div class="ca-card-icon">' + '<i class="fa ' + icon + '"></i>' + '</div>' + '</div>' + '<div class="ca-card-count-row">' + '<div class="ca-card-count">' + total + '</div>' + '<div class="ca-card-count-label">' + (total === 1 ? "Contract" : "Contracts") + '</div>' + '</div>' + '<div class="ca-card-value">' + 'Annual Value: ' + '<strong>' + formatEuro(annualValue) + '</strong>' + '</div>' + '<div class="ca-card-footer">' + '<div class="ca-card-status">' + '<span class="ca-active">' + '<i class="fa fa-circle"></i> ' + active + ' Active' + '</span>' + '&nbsp;&nbsp;' + '<span class="ca-closed">' + '<i class="fa fa-circle-o"></i> ' + closed + ' Closed' + '</span>' + '</div>' + '<div class="ca-open-arrow">' + '<i class="fa fa-angle-right"></i>' + '</div>' + '</div>' + '</div>';
    }
    function bindEvents() {
        $(document).off("keyup.ca", "#ca-search").on("keyup.ca", "#ca-search", function () {
            var search = $.trim($(this).val()).toLowerCase();
            filterAnalytics(search);
        });
        $(document).off("click.ca", "#ca-reset").on("click.ca", "#ca-reset", function () {
            $("#ca-search").val("");
            renderGroups(groupData);
            renderLocations(locationData);
            renderCountries(countryData);
        });
        $(document).off("click.ca", ".ca-tab-button").on("click.ca", ".ca-tab-button", function (e) {
            e.preventDefault();
            var tab = $(this).attr("data-tab");
            $(".ca-tab-button").removeClass("active");
            $(this).addClass("active");
            $(".ca-tab-content").removeClass("active");
            $("#ca-tab-" + tab).addClass("active");
            if (tab === "group") {
                getContractGroupAnalytics();
            } else if (tab === "location") {
                getContractLocationAnalytics();
            } else if (tab === "country") {
                getContractCountryAnalytics();
            }
        });
        $(document).off("click.ca", ".ca-card").on("click.ca", ".ca-card", function () {
            var type = $(this).attr("data-type");
            var name = $(this).attr("data-name");
            openContractDetails(type, name);
        });
        $(document).off("click.ca", "#ca-detail-close").on("click.ca", "#ca-detail-close", function () {
            closeContractDetails();
        });
        $(document).off("click.ca", "#ca-detail-footer-close").on("click.ca", "#ca-detail-footer-close", function () {
            closeContractDetails();
        });
        $(document).off("click.ca", "#ca-detail-overlay").on("click.ca", "#ca-detail-overlay", function (e) {
            if (e.target === this) {
                closeContractDetails();
            }
        });
        $(document).off("keydown.ca").on("keydown.ca", function (e) {
            if (e.keyCode === 27) {
                closeContractDetails();
            }
        });
    }
    function filterAnalytics(search) {
        if (!search) {
            renderGroups(groupData);
            renderLocations(locationData);
            renderCountries(countryData);
            return;
        }
        var filteredGroups = $.grep(groupData, function (item) {
            return String(item.name || "").toLowerCase().indexOf(search) !== -1;
        });
        var filteredLocations = $.grep(locationData, function (item) {
            return String(item.name || "").toLowerCase().indexOf(search) !== -1;
        });
        var filteredCountries = $.grep(countryData, function (item) {
            return String(item.name || "").toLowerCase().indexOf(search) !== -1;
        });
        renderGroups(filteredGroups);
        renderLocations(filteredLocations);
        renderCountries(filteredCountries);
    }
    function openContractDetails(type, name) {
        var $overlay = $("#ca-detail-overlay");
        var $tbody = $("#ca-detail-table-body");
        var title = name || "Not Specified";
        var detailType = String(type || "").toUpperCase();
        $("#ca-detail-type").text(detailType);
        $("#ca-detail-title").text(title);
        var icon = "fa-sitemap";
        if (type == "location") {
            icon = "fa-map-marker";
        } else if (type == "country") {
            icon = "fa-globe";
        }
        $("#ca-detail-icon").html('<i class="fa ' + icon + '"></i>');
        $("#ca-detail-count").text("0");
        $("#ca-detail-value").text(formatEuro(0));
        $("#ca-detail-active").text("0");
        $("#ca-detail-closed").text("0");
        $("body").addClass("ca-modal-open");
        $overlay.fadeIn(140);
        $tbody.html('<tr>' + '<td colspan="17" class="ca-detail-loading">' + '<i class="fa fa-spinner fa-spin"></i>' + ' Loading contracts...' + '</td>' + '</tr>');
        $.ajax({
            url: "/record/process?action=getContractAnalytics",
            type: "POST",
            dataType: "json",
            data: {
                type: "getContractDetails",
                detail_type: type,
                detail_name: name
            },
            success: function (response) {
                if (!response || response.success !== true) {
                    $tbody.html('<tr>' + '<td colspan="17" class="ca-detail-empty">' + '<i class="fa fa-exclamation-circle"></i>' + ' Unable to load contracts.' + '</td>' + '</tr>');
                    return;
                }
                var data = response.data || [];
                if (!data.length) {
                    $tbody.html('<tr>' + '<td colspan="17" class="ca-detail-empty">' + '<i class="fa fa-inbox"></i>' + ' No contracts found.' + '</td>' + '</tr>');
                    return;
                }
                var totalContracts = data.length;
                var activeContracts = 0;
                var closedContracts = 0;
                var annualValue = 0;
                var html = "";
                $.each(data, function (index, item) {
                    
                var rowClass = "";

if (item.is_terminate == "1" || item.is_terminate.toLowerCase() == "yes") {
    rowClass = " contract-terminated-row";
}
                    var status = String(item.status || "").toLowerCase();
                    if (status == "active") {
                        activeContracts++;
                        annualValue += parseFloat(item.year_value || 0) || 0;
                    } else if (status == "closed") {
                        closedContracts++;
                    }
                    var contractName = item.contract_name || "Not Specified";
                    var contractNr = item.contract_nr || "-";
                    var group = item.group || "Not Specified";
                    var contractType = item.contract_type || "Not Specified";
                    var location = item.location || "Not Specified";
                    var country = item.country || "Not Specified";
                    var startDate = item.start_date || "-";
                    var endDate = item.end_date || "-";
                    var noticeDate = item.notice_date || "-";
                    var noticePeriod = item.notice_period || "-";
                    var yearValue = parseFloat(item.year_value || 0) || 0;
                    var monthValue = parseFloat(item.month_value || 0) || 0;
                    var periodText = item.contract_period || "Not Specified";
                    var periodHtml = '<div>' + escapeHtml(periodText);
                    if (item.contract_auto_renewal !== null && item.contract_auto_renewal !== undefined && String(item.contract_auto_renewal) !== "") {
                        var renewalValue = String(item.contract_auto_renewal);
                        var renewalText = renewalValue;
                        if (renewalValue == "1") {
                            renewalText = "Yes";
                        } else if (renewalValue == "0") {
                            renewalText = "No";
                        }
                        periodHtml += '<div class="ca-detail-subtext">' + '<i class="fa fa-refresh"></i> ' + 'Renewal: ' + escapeHtml(renewalText) + '</div>';
                    }
                    periodHtml += '</div>';
                    var statusText = "Not Specified";
                    var statusClass = "unknown";
                    if (status == "active") {
                        statusText = "Active";
                        statusClass = "active";
                    } else if (status == "closed") {
                        statusText = "Closed";
                        statusClass = "closed";
                    } else if (status !== "") {
                        statusText = String(item.status);
                        statusClass = status.replace(/[^a-z0-9_-]/g, "");
                    }
                    var statusHtml = '<span class="ca-detail-status ' + statusClass + '">' + '<i class="fa fa-circle"></i> ' + escapeHtml(statusText) + '</span>';
                    var terminated = String(item.is_terminate || "0");
                    var terminatedHtml = "";
                    if (terminated == "1" || terminated.toLowerCase() == "yes") {
                        terminatedHtml = '<span class="ca-detail-terminated yes">' + '<i class="fa fa-check"></i> Yes' + '</span>';
                    } else {
                        terminatedHtml = '<span class="ca-detail-terminated no">' + '<i class="fa fa-minus"></i> No' + '</span>';
                    }
                    var expiryHtml = "";
                    if (item.expiring == "1" || item.expiring === 1) {
                        expiryHtml = '<span class="ca-detail-expiring">' + '<i class="fa fa-clock-o"></i> ' + 'Expiring Soon' + '</span>';
                    } else if (item.end_date !== null && item.end_date !== undefined && String(item.end_date) !== "") {
                        expiryHtml = '<span class="ca-detail-normal">' + escapeHtml(item.end_date) + '</span>';
                    } else {
                        expiryHtml = '<span class="ca-detail-normal">' + '<i class="fa fa-minus"></i> ' + 'No End Date' + '</span>';
                    }
                    var fileHtml = "";
                    if (item.file_download && item.file_download !== "") {
                        fileHtml = '<span class="ca-detail-file">' + item.file_download + '</span>';
                    }
                    var contractNameHtml = '<div class="ca-contract-name">' + escapeHtml(contractName) + '</div>';
                    if (fileHtml !== "") {
                        contractNameHtml += fileHtml;
                    }
                    html += '<tr class="' + rowClass.trim() + '"  data-contract-id="' + escapeHtml(item.id || "") + '">' +
                            '<td> <a target="_blank" href="/record/process:' + escapeHtml(item.track) + '?#panels">' + escapeHtml(item.track || "Not Specified") + '</a></td>' +
                            '<td>' + escapeHtml(item.contract_name || "Not Specified") + '</td>' +
                            '<td>' + escapeHtml(item.contract_nr || "-") + '</td>' +
                            '<td>' + escapeHtml(item.group || "Not Specified") + '</td>' +
                            '<td>' + escapeHtml(item.contract_type || "Not Specified") + '</td>' +
                            '<td>' + periodHtml + '</td>' +
                            '<td>' + escapeHtml(item.location || "Not Specified") + '</td>' +
                            '<td>' + escapeHtml(item.country || "Not Specified") + '</td>' +
                            '<td>' + escapeHtml(item.start_date || "-") + '</td>' +
                            '<td>' + escapeHtml(item.end_date || "-") + '</td>' +
                            '<td>' + escapeHtml(item.notice_date || "-") + '</td>' +
                            '<td>' + escapeHtml(item.notice_period || "-") + '</td>' +
                            '<td class="ca-detail-money-cell">' +
                            '<span class="ca-detail-money">' +
                            formatEuro(item.year_value) +
                            '</span>' +
                            '</td>' +
                            '<td class="ca-detail-money-cell">' +
                            '<span class="ca-detail-money">' +
                            formatEuro(item.month_value) +
                            '</span>' +
                            '</td>' +
                            '<td>' + escapeHtml(item.assign_to_name || item.approved_by || "-") + '</td>' +
                            '<td>' + statusHtml + '</td>' +
                            '<td>' + terminatedHtml + '</td>' +
                            '<td>' + escapeHtml(item.terminate_date || "-") + '</td>' +
                            '<td>' + expiryHtml + '</td>' + '</tr>';
                });
                $tbody.html(html);
                $("#ca-detail-count").text(totalContracts);
                $("#ca-detail-value").text(formatEuro(annualValue));
                $("#ca-detail-active").text(activeContracts);
                $("#ca-detail-closed").text(closedContracts);
            },
            error: function (xhr, status, error) {
                console.log("getContractDetails error:", status, error);
                $tbody.html('<tr>' + '<td colspan="17" class="ca-detail-empty">' + '<i class="fa fa-exclamation-triangle"></i>' + ' Error loading contracts.' + '</td>' + '</tr>');
            }
        });
    }
    function closeContractDetails() {
        $("#ca-detail-overlay").fadeOut(140);
        $("body").removeClass("ca-modal-open");
    }
    function formatEuro(value) {
        var amount = parseFloat(value || 0);
        if (isNaN(amount)) {
            amount = 0;
        }
        return "\u20AC" + amount.toLocaleString("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    function escapeHtml(value) {
        return String(value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }


});