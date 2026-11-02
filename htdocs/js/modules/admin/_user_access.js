
$(document).ready(function () {

        $('.simple-spinner-dot-dot-full-page').show();
    $.getJSON("/admin/center?action=GetUsersListWithPublishAccess", function (result) {
        console.log('sdfsdfsdf', result);
        var tableData = '';
        $.each(result.data, function (key, val) {
            tableData = '<tr><td>' + val.name + '</td>\n\
<td>' + val.email + '</td>\n\
<td>' + val.department_name + '</td>\n\
<td>On</td>\n\
<td><a target="_blank" href="/manager/user?id=' + val.id + '#acls">View</a></td>\n\
</tr>';
            $('#users_grid tbody').append(tableData);
        });

        datatable = $('#users_grid').dataTable({
            columnDefs: [{
                    "defaultContent": "-",
                    "targets": "_all"
                }]
        });
        $('.simple-spinner-dot-dot-full-page').hide();
        $('.users_grid_section').show();

    });





});