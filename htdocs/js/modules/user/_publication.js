$(function () {
    getDocumentationDocList('department');
    $('.myReadSignNotiSec').show();// 28012025 read only user documentation add Announcement
//    console.log('zsf');

});


// function getDocumentationDocList(tab_type) {
//     console.log('getDocumentationDocList');
//     $("#publish_documents").html('');
//     $(".simple-spinner").show();
//     var search_type = $.map($('input[name="search_publish_documents_type"]:checked'), function (c) {
//         return c.value;
//     });
//     var keyword = $('#search_publish_documents').val();
//     var search_by_department_list = '';
//     var search_by_department_list_value = '';
//     if (tab_type != 'private') {
//         var search_by_department_list = $('#search_by_department_list').val();
//         var search_by_department_list_value = $('#search_by_department_list option:selected').text();
//     }
//     $.post("?action=get_Documentation_By_Search", {
//         keyword: keyword,
//         search_by_department_list: search_by_department_list, 
//         tab_type: tab_type,
//         search_type: search_type,
//     }, function (x) {
//         getaData = x.recent;
//         $("#rt-list11").css("float", "right");
//         var recentRecord = "";
//         recentRecord += "<tr>";
//         recentRecord += "<td>View</td>";
//         recentRecord += "<td style='width: 38%;'>Tracking</td>";
//         recentRecord += "<td style='width: 35%;'>Document Title</td>";
//         if (tab_type == 'department') {
//             recentRecord += "<td style='width: 10%;'>Access</td>";
//         }
//         recentRecord += "<td style='width: 10%;'>Group Name</td>";
//         recentRecord += "<td style='width: 10%;'>Vs.</td>";
//         recentRecord += "<td style='width: 10%;'>Updated</td>";
//         recentRecord += "</tr>";
//         if (getaData.length > 0) {
//             for (c = 0; c < getaData.length; c++) {
                
        
//                 if (getaData[c].pri == "" || getaData[c].pri == null) {
//                     detailsPri = "	 ";
//                 } else {
//                     detailsPri = "" + getaData[c].pri + "";
//                 }
//                 recentRecord += "<tr id='" + getaData[c].rt_key + "'>";
//                 recentRecord += "<td style=''><a href='/?action=document&qp=orptwp&key=" + getaData[c].wv_key + "&nid=" + getaData[c].nid + "' target='_blank'><img src='/img/icons16/web_layout.png' alt='[Web View]'></a>"
//                 if(getaData[c].read_sign==0){
//                      recentRecord += "<span style='margin-left: 5px'><img src='/img/icons16/statuses/new.png' alt='new'></span>";
//                 }
//     recentRecord +="</td>";
//                 if(x.user_type=='readonly'){
//                      recentRecord += "<td style=''>" + getaData[c].track_id + "</td>";
//                 }else{
//                      recentRecord += "<td style=''><a href='/record/process:" + getaData[c].track_id + "#panels' id='" + getaData[c].rt_key + "' style='font-size: 12px;'>" + getaData[c].track_id + "</a></td>";
//                 }
               
//                 recentRecord += "<td style=''><img src='/img/icons16/ist/" + getaData[c].rt_key + ".png' alt='" + getaData[c].rt_key + "' style='padding-right: 6px;'/>" + getaData[c].title + "</td>";
//                 if (tab_type == 'department') {
//                     recentRecord += "<td><div class=' highlight-text displayThis' data-id='" + getaData[c].id + "'> (" + getaData[c].department_count + ") </div>\n\
// \n\
// \n\<span style='display:none' class='display_" + getaData[c].id + "' >" + getaData[c].department.replace(",", '</br>') + "</span>\n\
// </td>";
//                 }
//                 recentRecord += "<td>" + getaData[c].group_name + "</td>";
//                 recentRecord += "<td>" + getaData[c].version + "</td>";
//                 recentRecord += "<td>" + getaData[c].dt + "</td>";
//                 recentRecord += "</tr>";
//                 recentRecord += "<tr><td colspan='2'></td></tr>";
//             }
//         } else {
//             recentRecord += "<tr id='cargoclaim'><td colspan='7'> No data Found</td></tr>";
//         }
//         $("#publish_documents").html(recentRecord);
//         recentRecord = '';
//         $(".simple-spinner").hide();
//     });

// }

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
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>View</td>";
        recentRecord += "<td style='width: 30% !important;white-space: nowrap;'>Document Title</td>";
        recentRecord += "<td style='white-space: nowrap;'>Vs.</td>";
       
        
        if (tab_type == 'department') {
            recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Access</td>";
             
        }
         recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Group Name</td>";
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Doc Type</td>";
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Updated</td>";
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Published By</td>";
       
        
        // recentRecord += "<td style='width: 10%;'>Group Name</td>";
        recentRecord += "<td style='width: 10% !important;'>Tracking</td>";
        recentRecord += "</tr>";
        if (getaData.length > 0) {
            for (c = 0; c < getaData.length; c++) {
                
        
                if (getaData[c].pri == "" || getaData[c].pri == null) {
                    detailsPri = "	 ";
                } else {
                    detailsPri = "" + getaData[c].pri + "";
                }
                
 recentRecord += "<td style=''><a href='/?action=document&qp=orptwp&key=" + getaData[c].wv_key + "&nid=" + getaData[c].nid + "' target='_blank'><i class='fa fa-eye' aria-hidden='true'style='color: #000; font-size: 15px;' ></i></a>";
                
                if (getaData[c].read_sign == 0) {
                    recentRecord += "<span style='margin-left: 5px'><img src='/img/icons16/statuses/new.png' alt='new'></span>";
                }
                else if (getaData[c].read_sign_update == 1) {
                    recentRecord += "<span style='margin-left: 5px'><img src='/img/icons16/statuses/update.png' alt='new'></span>";
                }
                else if (getaData[c].is_need_refresh == 1) {
                    recentRecord += "<span style='margin-left: 5px'><img src='/img/icons16/statuses/refresh2.png' alt='new'></span>";
                }
                recentRecord += "</td>";
            
                

                recentRecord += "<td style=''>" + getaData[c].title + "</td>";
                recentRecord += "<td>" + getaData[c].version + "</td>";
               
                if (tab_type == 'department') {
                    recentRecord += "<td><div class=' highlight-text displayThis' data-id='" + getaData[c].id + "'> (" + getaData[c].department_count + ") </div>\n\
\n\
\n\<span style='display:none' class='display_" + getaData[c].id + "' >" + getaData[c].department.replace(",", '</br>') + "</span>\n\
</td>";

                
                }
                 recentRecord += "<td>" + getaData[c].group_name + "</td>";
                // recentRecord += "<td>" + getaData[c].version + "</td>";
                // recentRecord += "<td>" + getaData[c].dt + "</td>";
                // recentRecord += "<td>" + getaData[c].rt_name + "</td>"; 
                recentRecord += "<td>" + getaData[c].doc_type_full_name + "</td>"; 
                recentRecord += "<td>" + getaData[c].dt + "</td>"; 
                recentRecord += "<td>" + getaData[c].publishedByName + "</td>";
                recentRecord += "<td style=''>" + getaData[c].track_id + "</td>";
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


$(document).on('click', '#fetch_department_data', function () {
    console.log('fetch_department_data');
    $('.rafiltertab').show();
    $('.myReadSignNotiSec').show();// 28012025 read only user documentation add Announcement
    getDocumentationDocList('department');
    $('#fetch_all_data,#fetch_private_data,#fetch_request_to_publish_data,#fetch_all_notification').removeClass('active');
    $('#fetch_department_data').addClass('active');
    $('#search_publish_documents_type').val('department');
});
$(document).on('click', '#fetch_private_data', function () {
    console.log('fetch_private_data');
    $('.rafiltertab').hide();
    $('.myReadSignNotiSec').show();// 28012025 read only user documentation add Announcement
    getDocumentationDocList('private');
    $('#fetch_all_data,#fetch_department_data,#fetch_request_to_publish_data').removeClass('active');
    $('#fetch_private_data').addClass('active');
    $('#search_publish_documents_type').val('private');
});
$(document).on('click', '#fetch_all_data', function () {
    console.log('fetch_all_data');
    $('.rafiltertab').show();
    $('.myReadSignNotiSec').show();// 28012025 read only user documentation add Announcement
    getDocumentationDocList('all');
    $('#fetch_private_data,#fetch_department_data,#fetch_request_to_publish_data').removeClass('active');
    $('#fetch_all_data').addClass('active');
    $('#search_publish_documents_type').val('all');
});

$(document).on('click', '#fetch_request_to_publish_data', function () {
    console.log('fetch_request_to_publish_data');
    $('.rafiltertab').show();
    $('.myReadSignNotiSec').show();// 28012025 read only user documentation add Announcement
    getDocumentationDocList('all');
    $('#fetch_private_data,#fetch_department_data,#fetch_all_data').removeClass('active');
    $('#fetch_request_to_publish_data').addClass('active');
    $('#search_publish_documents_type').val('request_to_publish_data');
});


$(document).on('click', '#doments_rasearch', function () {
    console.log('search_publish_documents');
    getDocumentationDocList($('#search_publish_documents_type').val());
});
$(document).on('input propertychange paste', '#search_publish_documents', function () {
    console.log('search_publish_documents');
    getDocumentationDocList($('#search_publish_documents_type').val());
});

$(document).on('mouseenter', '#search_publish_documents_type', function () {
    console.log('mouseenter');
    getDocumentationDocList($('#search_publish_documents_type').val());
});

$(document).on('change', '#search_by_department_list', function () {
    console.log(this.val);
    getDocumentationDocList($('#search_publish_documents_type').val());
});

$(document).on('click', '.displayThis', function () {
    var id = $(this).attr('data-id');
    Swal.fire({
        title: "",
        icon: "",
        html:$('.display_' + id).html(),
        cancelButtonAriaLabel: "Thumbs down"
    });
});



// 28012025 read only user documentation add Announcement

$(document).on('click', '#fetch_all_notification', function () {
    $('.rafiltertab').hide();
    $('.myReadSignNotiSec').hide();
    getDocumentationNotification('fetch_all_notification');
    $('#fetch_all_data,#fetch_department_data,#fetch_request_to_publish_data,#fetch_private_data').removeClass('active');
    $('#fetch_all_notification').addClass('active');
});
function getDocumentationNotification(tab_type){
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
        recentRecord += "<td style='width: 50% !important;white-space: nowrap;'>Title</td>";
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Access</td>";
         recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Group Name</td>";
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Doc Type</td>";
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'> Published By</td>"; 
        recentRecord += "<td style='width: 10% !important;white-space: nowrap;'>Tracking</td>"; 
        recentRecord += "</tr>";
        if (getaData.length > 0) {
            for (c = 0; c < getaData.length; c++) {

                recentRecord += "<td style=''><a href='/?action=document&qp=orptwp&key=" + getaData[c].wv_key + "&nid=" + getaData[c].nid + "' target='_blank'><i class='fa fa-eye' aria-hidden='true'style='color: #000; font-size: 15px;' ></i></a>"
                recentRecord += "</td>";

                recentRecord += "<td style=''>" + getaData[c].title + "</td>";
               
                if(getaData[c].department!='---'){
                    recentRecord += "<td><div class=' highlight-text displayThis' data-id='" + getaData[c].id + "'> (" + getaData[c].department_count + ") </div>\n\
                    <span style='display:none' class='display_" + getaData[c].id + "' >" + getaData[c].department.replace(",", '</br>') + "</span>\n\
                    </td>";
                }else{
                    recentRecord += "<td style=''>---</td>";
                }
                // recentRecord += "<td>" + getaData[c].rt_name + "</td>"; 
                recentRecord += "<td style=''>" + getaData[c].group_name+ "</td>";
                recentRecord += "<td style=''>" + getaData[c].doc_type_full_name+ "</td>"; 
                recentRecord += "<td style=''>" + getaData[c].publishedByName + "</td>";
                recentRecord += "</td>";   
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

// 28012025 read only user documentation add Announcement