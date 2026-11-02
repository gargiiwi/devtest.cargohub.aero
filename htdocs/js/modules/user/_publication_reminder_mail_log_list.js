
//IWI popup to view all reminder dates start

$(document).on('click', '.reminderDateView', function () {
    var user_id = $(this).attr('data-user');
    var doc_id = $(this).attr('data-doc');
    $(".simple-spinner").show();
    $.post("?action=getReminderMailLogByUserDocId", {
        user_id: user_id,
        doc_id: doc_id,
    }, function (x) {
        var getaData = x.getList;
        var recentRecord = "";
        if (getaData.length > 0) {
            for (c = 0; c < getaData.length; c++) {
                recentRecord += "<tr> ";
                recentRecord += "<td >" + getaData[c].time + "</td>";
                recentRecord += "<td >" + getaData[c].type + "</td>";
                recentRecord += "<td >" + getaData[c].report + "</td>";
                recentRecord += "</tr>";
            }
        }

        $('#reminderDateViewTbody').html(recentRecord);
        Swal.fire({
            html: $('.reminderDateViewSec').html()
        });
        $(".simple-spinner").hide();
    });
});


//IWI popup to view all reminder dates end

