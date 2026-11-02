$(function () {
    getDocumentationDocList('department');
//    console.log('zsf');
});

function getDocumentationDocList(tab_type) {
    console.log('getDocumentationDocList');
    $("#publish_documents").html('');
    $(".simple-spinner").show();
    var search_type = $.map($('input[name="search_publish_documents_type"]:checked'), function (c) {
        return c.value;
    });
    var keyword = $('#search_publish_documents').val();
    var search_by_department_list = '';
    var search_by_department_list_value = '';
    if (tab_type != 'private') {
        var search_by_department_list = $('#search_by_department_list').val();
        var search_by_department_list_value = $('#search_by_department_list option:selected').text();
    }
    $.post("?action=get_Documentation_By_Search", {
        keyword: keyword,
        search_by_department_list: search_by_department_list,
        tab_type: tab_type,
        search_type: search_type,
    }, function (x) {
        getaData = x.recent;
        $("#rt-list11").css("float", "right");
        var recentRecord = "";
        recentRecord += "<tr>";
        recentRecord += "<td style='width: 5% !important;white-space: nowrap;'>View</td>";
//        recentRecord += "<td style='width: 28%;'>Tracking</td>";
        recentRecord += "<td style='width: 34% !important;white-space: nowrap;'>Document Title</td>";                            //IWI Create section for Notification start / end
        // recentRecord += "<td style='width: 10%; !important;'>Ref. No</td>";// IWI ref no for documents 
        recentRecord += "<td style='white-space: nowrap;'>Vs.</td>";
        if (tab_type == 'department') {
            recentRecord += "<td style='white-space: nowrap;'>Access</td>";
        }
        recentRecord += "<td style='width: 10% !important;'>Record Type</td>";
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Doc Type</td>";
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Updated</td>"; 
        recentRecord += "<td style='width: 10% !important;'>Published By</td>";
        // recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Record type</td>";
        recentRecord += "<td style='width: 10% !important;'>Tracking</td>";
        recentRecord += "</tr>";
        if (getaData.length > 0) {
            for (c = 0; c < getaData.length; c++) {

                console.log(getaData);
                if (getaData[c].pri == "" || getaData[c].pri == null) {
                    detailsPri = "	 ";
                } else {
                    detailsPri = "" + getaData[c].pri + "";
                }
                recentRecord += "<tr id='" + getaData[c].rt_key + "'>";
//                IWI Replace the globe icon for an eye icon start
//                recentRecord += "<td style=''><a href='/?action=document&qp=orptwp&key=" + getaData[c].wv_key + "&nid=" + getaData[c].nid + "' target='_blank'><img src='/img/icons16/web_layout.png' alt='[Web View]'></a>"

 recentRecord += "<td style=''><a href='/?action=document&qp=orptwp&key=" + getaData[c].wv_key + "&nid=" + getaData[c].nid + "' target='_blank'><i class='fa fa-eye' aria-hidden='true'style='color: #000; font-size: 15px;' ></i></a>"
                
//                IWI Replace the globe icon for an eye icon end
                
                if (getaData[c].read_sign == 0) {
                    recentRecord += "<span style='margin-left: 5px'><img src='/img/icons16/statuses/new.png' alt='new'></span>";
                }
//                IWI if any update in the doc section, then the update icon will show in publication list�start
                else if (getaData[c].read_sign_update == 1) {
                    recentRecord += "<span style='margin-left: 5px'><img src='/img/icons16/statuses/update.png' alt='new'></span>";
                }
//                IWI if any update in the doc section, then the update icon will show in publication list�end

//                IWI Refresh after days�start
                else if (getaData[c].is_need_refresh == 1) {
                    recentRecord += "<span style='margin-left: 5px'><img src='/img/icons16/statuses/refresh2.png' alt='new'></span>";
                }
            //    IWI Refresh after days end
            //    recentRecord += "</td>";
            //    if (x.user_type == 'readonly') {
            //        recentRecord += "<td style=''>" + getaData[c].track_id + "</td>";
            //    } else {
            //        recentRecord += "<td style=''><a href='/record/process:" + getaData[c].track_id + "#docs' id='" + getaData[c].rt_key + "' style='font-size: 12px;'>" + getaData[c].track_id + "</a></td>";
            //    }

                recentRecord += "<td style=''>" + getaData[c].title + "</td>";
                 
                recentRecord += "<td>" + getaData[c].version + "</td>";
               
                // recentRecord += "<td>" + getaData[c].rt_name + "</td>"; 
                if (tab_type == 'department') {
                   
                    recentRecord += "<td><div class=' highlight-text displayThis' data-id='" + getaData[c].id + "'> (" + getaData[c].department_count + ") </div>\n\
\n\
\n\<span style='display:none' class='display_" + getaData[c].id + "' >" + getaData[c].department.replace(",", '</br>') + "</span>\n\
</td>";
                
                }
                recentRecord += "<td>" + getaData[c].rt_name + "</td>";
                recentRecord += "<td>" + getaData[c].doc_type_full_name + "</td>"; // IWI ref no for documents
                recentRecord += "<td>" + getaData[c].dt + "</td>"; 
                recentRecord += "<td>" + getaData[c].publishedByName + "</td>";
                recentRecord += "</td>";
               if (x.user_type == 'readonly') {                                                          //IWI Create section for Notification start / end 
                   recentRecord += "<td style=''>" + getaData[c].track_id + "</td>";
               } else {
                   recentRecord += "<td style=''><a href='/record/process:" + getaData[c].track_id + "#docs' id='" + getaData[c].rt_key + "' style='font-size: 12px;'>" + getaData[c].track_id + "</a></td>";
               }
                recentRecord += "</tr>";
                recentRecord += "<tr><td colspan='2'></td></tr>";
            }
        } else {
            recentRecord += "<tr id='cargoclaim'><td colspan='8'> No data Found</td></tr>";
        }
        $("#publish_documents").html(recentRecord);
        recentRecord = '';
        $(".simple-spinner").hide();
    });

}






function getDocumentationDocListJqx(tab_type) {
   console.log('getDocumentationDocListJqx');
  $("#dataTable").jqxDataTable(
{
   source: dataAdapter,
   columns: [
       { text: 'First Name', dataField: 'First Name', width: 100 },
       { text: 'Last Name', dataField: 'Last Name', width: 100 },
       { text: 'Product', dataField: 'Product', width: 180 },
       { text: 'Unit Price', dataField: 'Price', width: 90, align: 'right', cellsAlign: 'right', cellsFormat: 'c2' },
       { text: 'Quantity', dataField: 'Quantity', width: 80, align: 'right', cellsAlign: 'right' }
   ]
});

}




$(document).on('click', '#fetch_department_data', function () {
    console.log('fetch_department_data');
    $('.rafiltertab').show();
    getDocumentationDocList('department');
    $('#fetch_all_data,#fetch_private_data,#fetch_request_to_publish_data,#fetch_my_data,#fetch_my_allocate_data,#fetch_all_published_by_org,#fetch_all_notification').removeClass('active');//IWI user can view all published doc by assigned organization
    $('#fetch_department_data').addClass('active');
    $('#search_publish_documents_type').val('department');
});
$(document).on('click', '#fetch_private_data', function () {
    console.log('fetch_private_data');
    $('.rafiltertab').hide();
    getDocumentationDocList('private');
    $('#fetch_all_data,#fetch_department_data,#fetch_request_to_publish_data,#fetch_my_data,#fetch_my_allocate_data,#fetch_all_published_by_org,#fetch_all_notification').removeClass('active');//IWI user can view all published doc by assigned organization
    $('#fetch_private_data').addClass('active');
    $('#search_publish_documents_type').val('private');
});
$(document).on('click', '#fetch_all_data', function () {
    console.log('fetch_all_data');
    $('.rafiltertab').show();
    getDocumentationDocList('all');
    $('#fetch_private_data,#fetch_department_data,#fetch_request_to_publish_data,#fetch_my_data,#fetch_my_allocate_data,#fetch_all_published_by_org').removeClass('active');//IWI user can view all published doc by assigned organization
    $('#fetch_all_data').addClass('active');
    $('#search_publish_documents_type').val('all');
});

$(document).on('click', '#fetch_my_data', function () {
    console.log('fetch_my_data');
    $('.rafiltertab').hide();
    getDocumentationDocListMyData('mydata');

//    getDocumentationDocListJqx('private');

    $('#fetch_all_data,#fetch_department_data,#fetch_request_to_publish_data,#fetch_private_data,#fetch_all_published_by_org').removeClass('active');//IWI user can view all published doc by assigned organization
    $('#fetch_my_data').addClass('active');
    $('#search_publish_documents_type').val('mydata');
});



$(document).on('click', '#fetch_my_allocate_data', function () {
    console.log('fetch_my_allocate_data');
    $('.rafiltertab').hide();
    getDocumentationDocListMyData('fetch_my_allocate_data');

//    getDocumentationDocListJqx('private');

    $('#fetch_all_data,#fetch_department_data,#fetch_request_to_publish_data,#fetch_private_data,#fetch_my_data,#fetch_all_published_by_org,#fetch_all_notification').removeClass('active');//IWI user can view all published doc by assigned organization // IWI Create section for Notification
    $('#fetch_my_allocate_data').addClass('active');
    $('#search_publish_documents_type').val('fetch_my_allocate_data'); //IWI publication search functionality and design  check and fix
});




$(document).on('input propertychange paste', '#search_publish_documents', function () {
    console.log($('#search_publish_documents_type').val());
    console.log('search_publish_documents');
    if ($('#search_publish_documents_type').val() == 'mydata') {
        getDocumentationDocListMyData($('#search_publish_documents_type').val());
    } 
//    IWI user can view all published doc by assigned organization start
    else if ($('#search_publish_documents_type').val() == 'fetch_all_published_by_org') {
        getPublishedAllByOrg($('#search_publish_documents_type').val());
    }
//    IWI user can view all published doc by assigned organization end
    else {
        getDocumentationDocList($('#search_publish_documents_type').val());
    }

});

$(document).on('mouseenter', '#search_publish_documents_type', function () {
    console.log('mouseenter');
    getDocumentationDocList($('#search_publish_documents_type').val());
});

$(document).on('change', '#search_by_department_list', function () {
    if ($('#search_publish_documents_type').val() == 'mydata') {
        getDocumentationDocListMyData($('#search_publish_documents_type').val());
    } 
//    IWI user can view all published doc by assigned organization start
    else if ($('#search_publish_documents_type').val() == 'fetch_my_allocate_data') {
        getPublishedAllByOrg($('#search_publish_documents_type').val());
    }
//    IWI user can view all published doc by assigned organization end
    else {
        getDocumentationDocList($('#search_publish_documents_type').val());
    }
});

$(document).on('click', '.displayThis', function () {
    var id = $(this).attr('data-id');
    Swal.fire({
        title: "",
        icon: "",
        html: $('.display_' + id).html(),
        cancelButtonAriaLabel: "Thumbs down"
    });
});



function getDocumentationDocListMyData(tab_type) {
    console.log('getDocumentationDocListMyData');
    $("#publish_documents").html('');
    $(".simple-spinner").show();
    var search_type = $.map($('input[name="search_publish_documents_type"]:checked'), function (c) {
        return c.value;
    });
    var keyword = $('#search_publish_documents').val();
    var search_by_department_list = '';
    var search_by_department_list_value = '';
    var search_by_department_list = $('#search_by_department_list').val();
    var search_by_department_list_value = $('#search_by_department_list option:selected').text();
    $.post("?action=get_Documentation_By_Search", {
        keyword: keyword,
        search_by_department_list: search_by_department_list,
        tab_type: tab_type,
        search_type: search_type,
    }, function (x) {
        getaData = x.recent;
        $("#rt-list11").css("float", "right");
        var recentRecord = "";
        recentRecord += "<tr>";
        recentRecord += "<td style='width: 5% !important;'>View</td>";
//        recentRecord += "<td style='width: 20%;'>Tracking</td>";
        recentRecord += "<td style='width: 36% !important;'>Document Title</td>";
        recentRecord += "<td style='width: 20% !important;'> Published By</td>"; //IWI publication published section changes as per 26082024 
        recentRecord += "<td style='width: 10% !important;'>Signed</td>"; // IWI change title Total signed to " Signed "
        //IWI Create a button to bulk mail send start
        recentRecord += "<td style='width: 10% !important;' > Reminder</td>";
        recentRecord += "<td style='width: 10% !important;' >Readers</td>";
        recentRecord += "<td style='width: 10% !important;'>Tracking</td>";            //IWI Create section for Notification start / end
        //IWI Create a button to bulk mail end
        recentRecord += "</tr>";
        if (getaData.length > 0) { 
            for (c = 0; c < getaData.length; c++) {
//                console.log(getaData[c]); //IWI user can view all published doc by assigned organization


                if (getaData[c].pri == "" || getaData[c].pri == null) {
                    detailsPri = "	 ";
                } else {
                    detailsPri = "" + getaData[c].pri + "";
                }
                recentRecord += "<tr id='" + getaData[c].rt_key + "'>";

//                IWI Replace the globe icon for an eye icon start
//                recentRecord += "<td style=''><a href='/?action=document&qp=orptwp&key=" + getaData[c].wv_key + "&nid=" + getaData[c].nid + "' target='_blank'><img src='/img/icons16/web_layout.png' alt='[Web View]'></a>"

                recentRecord += "<td style=''><a href='/?action=document&qp=orptwp&key=" + getaData[c].wv_key + "&nid=" + getaData[c].nid + "' target='_blank'><i class='fa fa-eye' aria-hidden='true'style='color: #000; font-size: 15px;' ></i></a>"

//                IWI Replace the globe icon for an eye icon end

//                recentRecord += "</td>";
//                if (x.user_type == 'readonly') {
//                    recentRecord += "<td style=''>" + getaData[c].track_id + "</td>";
//                } else {
//                    recentRecord += "<td style=''><a href='/record/process:" + getaData[c].track_id + "#docs' id='" + getaData[c].rt_key + "' style='font-size: 12px;'>" + getaData[c].track_id + "</a></td>";
//                }

//                recentRecord += "<td style=''><img src='/img/icons16/ist/" + getaData[c].rt_key + ".png' alt='" + getaData[c].rt_key + "' style='padding-right: 6px;'/>" + getaData[c].title + "</td>";

               

                recentRecord += "<td style=''>" + getaData[c].title + "</td>";
                
                
                 recentRecord += "<td>" + getaData[c].wv_publish_for.toUpperCase();
                if (getaData[c].wv_publish_for == 'department') {
                    recentRecord += "<span class=' highlight-text displayThis' data-id='" + getaData[c].id + "'> (" + getaData[c].department_count + ") </span>\n\
\n\
\n\<span style='display:none' class='display_" + getaData[c].id + "' >" + getaData[c].department.replace(",", '</br>') + "</span>\n\
";
                }
                recentRecord += "</td>";
                
                 recentRecord += "<td style=''>" + getaData[c].publishedByName + "</td>"; //IWI publication published section changes as per 26082024 
                recentRecord += "<td>";
                if (getaData[c].wv_publish_for == 'department') {
                    recentRecord += getaData[c].getSignInDataCount + "/ " + getaData[c].getTotDepUsers;
                }
                recentRecord += "</td>";
//                 IWI Create a button to bulk mail send start
                recentRecord += "<td>";
                if (getaData[c].wv_publish_for == 'department') {
                    recentRecord += "<a href='javascript:void(0);' style='color:#fff' class='publicationSendReminderAll' data-note-id=" + getaData[c].id + "><button class='custom_green_btn' > <i class='fa fa-envelope' aria-hidden='true'style='color: #fff; font-size: 15px;' ></i></button> </a>";
                }
                recentRecord += "</td>";
//                 IWI Create a button to bulk mail send end
                recentRecord += "<td>";
                if (getaData[c].wv_publish_for == 'department') {
                    recentRecord += "<a href='/user/publication?action=publication_user_list&id=" + getaData[c].id + "'><button class='custom_green_btn'> <i class='fa fa-eye' aria-hidden='true'style='color: #fff; font-size: 15px;' ></i> </button></a>";
                }
                
                recentRecord += "</td>";
                recentRecord += "</tr>";
                recentRecord += "<tr><td colspan='2'></td></tr>";
            }
        } else {
        
            recentRecord += "<tr id='cargoclaim'><td colspan='7'> No data Found</td></tr>";
        }
        $("#publish_documents").html(recentRecord);
        recentRecord = '';
        $(".simple-spinner").hide();
    });

}

//IWI Create a button to bulk mail send start

$(document).on('click', '.publicationSendReminderAll', function () {
    
    var note_id = $(this).data('note-id');
    
//    console.log(note_id);

//        Swal.showLoading();
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
    $(".simple-spinner").hide();
            });
        }
    });
});


//IWI Create a button to bulk mail send end



// IWI user can view all published doc by assigned organization start

$(document).on('click', '#fetch_all_published_by_org', function () {
    console.log('fetch_all_published_by_org');
    $('.rafiltertab').hide();
    getPublishedAllByOrg('fetch_all_published_by_org');

    $('#fetch_all_data,#fetch_department_data,#fetch_request_to_publish_data,#fetch_private_data,#fetch_my_data,#fetch_my_allocate_data,#fetch_all_notification').removeClass('active'); //IWI Create section for Notification
    $('#fetch_all_published_by_org').addClass('active');
    $('#search_publish_documents_type').val('fetch_all_published_by_org'); //IWI publication search functionality and design  check and fix
});

function getPublishedAllByOrg(tab_type){
    console.log('getPublishedAllByOrg');
    $("#publish_documents").html('');
    $(".simple-spinner").show(); 
    var search_type = $.map($('input[name="search_publish_documents_type"]:checked'), function (c) {
        return c.value;
    });
    var keyword = $('#search_publish_documents').val();
    var search_by_department_list = '';
    var search_by_department_list_value = '';
//    var search_by_department_list = $('#search_by_department_list').val();
//    var search_by_department_list_value = $('#search_by_department_list option:selected').text();
    $.post("?action=get_Documentation_By_Search", {
        keyword: keyword,
        search_by_department_list: search_by_department_list,
        tab_type: tab_type,
        search_type: search_type,
    }, function (x) {
        getaData = x.recent;
        $("#rt-list11").css("float", "right");
        var recentRecord = "";
        recentRecord += "<tr>";
        recentRecord += "<td style='width: 5% !important;white-space: nowrap;'>View</td>"; 
//        recentRecord += "<td style='width: 20%;'>Tracking</td>";
        recentRecord += "<td style='width: 20% !important;white-space: nowrap;'>Document Title</td>";
        // recentRecord += "<td style='width: 10%;'>Ref. No</td>";// IWI ref no for documents
        recentRecord += "<td style='white-space: nowrap;'>Access </td>"; 
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Record Type</td>"; 
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Doc Type</td>";
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'> Published By</td>"; 
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'> Updated</td>"; 
        // recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Record type</td>";
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Signed</td>"; 
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;' > Reminder</td>";
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;' >Readers</td>";
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Tracking</td>";  // IWI Create section for Notification start / end 
        recentRecord += "</tr>";
        if (getaData.length > 0) {
            for (c = 0; c < getaData.length; c++) {
//                console.log(getaData[c]);


                if (getaData[c].pri == "" || getaData[c].pri == null) {
                    detailsPri = "	 ";
                } else {
                    detailsPri = "" + getaData[c].pri + "";
                }
                recentRecord += "<tr id='" + getaData[c].rt_key + "'>";


                recentRecord += "<td style=''><a href='/?action=document&qp=orptwp&key=" + getaData[c].wv_key + "&nid=" + getaData[c].nid + "' target='_blank'><i class='fa fa-eye' aria-hidden='true'style='color: #000; font-size: 15px;' ></i></a>"
                recentRecord += "</td>";
//                if (x.user_type == 'readonly') {
//                    recentRecord += "<td style=''>" + getaData[c].track_id + "</td>";
//                } else {
//                    recentRecord += "<td style=''><a href='/record/process:" + getaData[c].track_id + "#docs' id='" + getaData[c].rt_key + "' style='font-size: 12px;'>" + getaData[c].track_id + "</a></td>";
//                }

                recentRecord += "<td style=''>" + getaData[c].title + "</td>";
                //  recentRecord += "<td>" + getaData[c].ref_number + "</td>"; // IWI ref no for documents 
                
              
                 recentRecord += "<td>" 
                if (getaData[c].wv_publish_for == 'department') {
                    recentRecord += "<span class=' highlight-text displayThis' data-id='" + getaData[c].id + "'> (" + getaData[c].department_count + ") </span>\n\
\n\
\n\<span style='display:none' class='display_" + getaData[c].id + "' >" + getaData[c].department.replace(",", '</br>') + "</span>\n\
";
                }
                recentRecord += "</td>";
                recentRecord += "<td>" + getaData[c].rt_name + "</td>"; 
                recentRecord += "<td>" + getaData[c].doc_type_full_name + "</td>"; 
                 recentRecord += "<td style=''>" + getaData[c].publishedByName + "</td>"; //IWI publication published section changes as per 26082024 
                 recentRecord += "<td>" + getaData[c].dt + "</td>";
                //  recentRecord += "<td>" + getaData[c].rt_name + "</td>"; 
                recentRecord += "<td>";
                if (getaData[c].wv_publish_for == 'department') {
                    recentRecord += getaData[c].getSignInDataCount + "/ " + getaData[c].getTotDepUsers;
                }
                recentRecord += "</td>";
                recentRecord += "<td>";
                if (getaData[c].wv_publish_for == 'department') {
                    recentRecord += "<a href='javascript:void(0);' style='color:#fff' class='publicationSendReminderAll' data-note-id=" + getaData[c].id + "><button class='custom_green_btn' > <i class='fa fa-envelope' aria-hidden='true'style='color: #fff; font-size: 15px;' ></i></button> </a>";
                }
                recentRecord += "</td>";
                recentRecord += "<td>";
                if (getaData[c].wv_publish_for == 'department') {
                    recentRecord += "<a href='/user/publication?action=publication_user_list&id=" + getaData[c].id + "'><button class='custom_green_btn'> <i class='fa fa-eye' aria-hidden='true'style='color: #fff; font-size: 15px;' ></i> </button></a>";
                }
                recentRecord += "</td>";                                               //IWI Create section for Notification start / end 
                if (x.user_type == 'readonly') {
                    recentRecord += "<td style=''>" + getaData[c].track_id + "</td>";
                } else {
                    recentRecord += "<td style=''><a href='/record/process:" + getaData[c].track_id + "#docs' id='" + getaData[c].rt_key + "' style='font-size: 12px;'>" + getaData[c].track_id + "</a></td>";
                }
                recentRecord += "</td>";
                recentRecord += "</tr>";
                recentRecord += "<tr><td colspan='2'></td></tr>";
            }
        } else {
            recentRecord += "<tr id='cargoclaim'><td colspan='7'> No data Found</td></tr>";
        }
        $("#publish_documents").html(recentRecord);
        recentRecord = '';
        $(".simple-spinner").hide();
    });
}

// IWI user can view all published doc by assigned organization end




//IWI Create an icon for archived published doc start
$(document).on('click', '#user-nav-tabs li', function () {
    console.log($(this).attr('id'));
    if($(this).attr('id')=='fetch_all_published_by_org'){
        $('.archivePageLinkSec').show();
    }else{
        $('.archivePageLinkSec').hide();
    }
});
//IWI Create an icon for archived published doc end

$(document).on('click', '#user-nav-tabs li', function () {             //Iwi archived announcement start
    console.log($(this).attr('id'));
    if( $(this).attr('id')=='fetch_all_notification'){
        $('.archivePageLinkSecAnnounecement').show();
    }else{
        $('.archivePageLinkSecAnnounecement').hide();
    }
});                                                                //Iwi archived announcement start


//IWI Create section for Notification start
$(document).on('click', '#fetch_all_notification', function () {
//    console.log('fetch_my_allocate_data');
    $('.rafiltertab').hide();
    getDocumentationNotification('fetch_all_notification');
    $('#fetch_all_data,#fetch_department_data,#fetch_request_to_publish_data,#fetch_private_data,#fetch_my_data,#fetch_all_published_by_org,#fetch_my_allocate_data').removeClass('active');
    $('#fetch_all_notification').addClass('active');
    
});
function getDocumentationNotification(tab_type){
//    console.log(tab_type);
    
        
    $("#publish_documents").html('');
    $(".simple-spinner").show(); 
     $.post("?action=get_Documentation_Notification_By_Search", {
        tab_type: tab_type,
    }, function (x) {
        console.log('x',x);
        getaData = x.data;
        var recentRecord = "";
        recentRecord += "<tr>";
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>View</td>";
//        recentRecord += "<td style='width: 20%;'>Tracking</td>";
        recentRecord += "<td style='width: 45% !important;white-space: nowrap;'>Title</td>"; 
        recentRecord += "<td style='white-space: nowrap;'>Access</td>";
//        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Group Name</td>";
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Doc Type</td>";
        // recentRecord += "<td style='width: 20%;'>Ref. No</td>"; 
        // recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Record type</td>";
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'> Published By</td>"; 
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Tracking</td>";                  //IWI Create section for Notification start / end 
        recentRecord += "</tr>";
        if (getaData.length > 0) {
            for (c = 0; c < getaData.length; c++) {

                recentRecord += "<td style=''><a href='/?action=document&qp=orptwp&key=" + getaData[c].wv_key + "&nid=" + getaData[c].nid + "' target='_blank'><i class='fa fa-eye' aria-hidden='true'style='color: #000; font-size: 15px;' ></i></a>"
                recentRecord += "</td>";
//                if (x.user_type == 'readonly') {
//                    recentRecord += "<td style=''>" + getaData[c].track_id + "</td>";
//                } else {
//                    recentRecord += "<td style=''><a href='/record/process:" + getaData[c].track_id + "#docs' id='" + getaData[c].rt_key + "' style='font-size: 12px;'>" + getaData[c].track_id + "</a></td>";
//                }

                recentRecord += "<td style=''>" + getaData[c].title + "</td>";
                //  recentRecord += "<td>" + getaData[c].rt_name + "</td>";
                //  recentRecord += "<td>" + getaData[c].ref_number + "</td>"; // IWI ref no for documents 
                if(getaData[c].department!='---'){
                 recentRecord += "<td><div class=' highlight-text displayThis' data-id='" + getaData[c].id + "'> (" + getaData[c].department_count + ") </div>\n\
     <span style='display:none' class='display_" + getaData[c].id + "' >" + getaData[c].department.replace(",", '</br>') + "</span>\n\
</td>";
        }else{
            recentRecord += "<td style=''>---</td>";
        }
//              recentRecord += "<td>" + getaData[c].group_name + "</td>";
              recentRecord += "<td>" + getaData[c].doc_type_full_name + "</td>";
             recentRecord += "<td style=''>" + getaData[c].publishedByName + "</td>";
             recentRecord += "</td>";                                               //IWI Create section for Notification start / end 
               if (x.user_type == 'readonly') {
                   recentRecord += "<td style=''>" + getaData[c].track_id + "</td>";
               } else {
                   recentRecord += "<td style=''><a href='/record/process:" + getaData[c].track_id + "#docs' id='" + getaData[c].rt_key + "' style='font-size: 12px;'>" + getaData[c].track_id + "</a></td>";
               }

                 
                recentRecord += "</tr>";
                recentRecord += "<tr><td colspan='2'></td></tr>";
            }
        } else {
            recentRecord += "<tr id='cargoclaim'><td colspan='7'> No data Found</td></tr>";
        }
        $("#publish_documents").html(recentRecord);
        recentRecord = '';
        $(".simple-spinner").hide();
    });
}


//IWI Create section for Notification end


