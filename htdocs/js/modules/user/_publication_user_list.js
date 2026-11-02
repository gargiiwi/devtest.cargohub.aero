$(function () {
    getReadSignDetailsList($('#loadDataNoteId').val());
});

function getReadSignDetailsList(note_id) { 
var note_name=$('#note_name').val();
    $.post("?action=get_publication_readSign_user_details", {
        note_id: note_id
    }, function (x) {
        getaData = x;
//        console.log(getaData);
//        console.log(getaData.readSignUserListData);
//        console.log(Object.keys(getaData.readSignUserListData).length);
        var recentRecord = ""; 
        recentRecord += "<thead>";
        recentRecord += "<tr>";
        recentRecord += "<td style=''>Total ("+Object.keys(getaData.readSignUserListData).length+")</td>";
        recentRecord += "<td style=''>"+note_name+" </td>";
        recentRecord += "<td style=''>Sign in date</td>";
        recentRecord += "<td style=''>Action</td>";
        recentRecord += "</tr>";
        recentRecord += "</thead>";
        recentRecord += "<tbody>";
        if (Object.keys(getaData.readSignUserListData).length > 0) {
            var sl=parseInt(1);
            $.each(getaData.readSignUserListData, function (i, val) {
                recentRecord += "<tr id='" + i + "'>";
                recentRecord += "<td>" + sl++ + "</td>";
                recentRecord += "<td>" + val.user + "</td>";
                recentRecord += "<td>" + val.sign_in_date + "</td>";
                recentRecord += "<td>" + val.mail_to + "</td>";
                recentRecord += "</tr>";
            });
        } else {
            recentRecord += "<tr><td colspan='3'> No data Found</td></tr>";
        }
        recentRecord += "</tbody>";
        $("#readSignUserListData").html(recentRecord);
        recentRecord = '';
        $(".simple-spinner").hide();
    });

}


$(document).on('click', '.sendReminderMail', function () {

    Swal.showLoading();

//    console.log($(this).attr('data-user'));
    var user_id = $(this).attr('data-user');
    var note_id = $('#loadDataNoteId').val();

    Swal.fire({
        title: "Send reminder?",
        showDenyButton: true,
        confirmButtonText: "Yes",
    }).then((result) => {
        /* Read more about isConfirmed, isDenied below */
        if (result.isConfirmed) {
             $(".simple-spinner").show(); // IWI Create a log for read and sign reminder mail send 
            $.post("?action=send_reminder_mail_to_user", {
                user_id: user_id,
                note_id: note_id,
                mail_for: 'user_sign_reminder'
            }, function (x) {
//        console.log(x);
                if (Object.keys(x.success)) {
                    Swal.fire({
                        title: "",
                        text: "Reminder mail has been sent",
                        icon: "success"
                    });
                } else {
                    Swal.fire({
                        title: "",
                        text: "Opps, please try again!",
                        icon: "error"
                    });
                }
                 $(".simple-spinner").hide(); // IWI Create a log for read and sign reminder mail send 
            });
        }
    });




});



//IWI Create a button to bulk mail send start
$(document).on('click', '#publicationSendReminderAll', function () {

    
    var note_id = $('#loadDataNoteId').val();

        Swal.showLoading();
    Swal.fire({
        title: "Send reminder to all users?",
        showDenyButton: true,
        confirmButtonText: "Yes",
    }).then((result) => {
        /* Read more about isConfirmed, isDenied below */
        if (result.isConfirmed) {
             $(".simple-spinner").show();
            $.post("?action=send_reminder_mail_to_all_user", {
                note_id: note_id,
                mail_for: 'send_reminder_mail_to_all_user'
            }, function (x) {
//        console.log(x);
                if (Object.keys(x.success)) {
                     $(".simple-spinner").hide();
                    Swal.fire({
                        title: "",
                        text: "Reminder mail has been sent",
                        icon: "success"
                    });
                } else {
                     $(".simple-spinner").hide();
                    Swal.fire({
                        title: "",
                        text: "Opps, please try again!",
                        icon: "error"
                    });
                }
            });
        }
    });




});

//IWI Create a button to bulk mail send end
