

const Toast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.onmouseenter = Swal.stopTimer;
        toast.onmouseleave = Swal.resumeTimer;
    }
});

if ($('.reportCronSec').length) { 
    console.log('report cron page');
    const emailFrequency = document.getElementById('addemailFrequency');
    const daySelect = document.getElementById('adddaySelect');
    const datePicker = document.getElementById('adddatePicker');
    const day_section = document.getElementById('add_day_section');
    if (emailFrequency) {
        emailFrequency.addEventListener('change', function () {
            const val = this.value;

            if (val === 'weekly' || val === 'biweekly') {
                day_section.style.display = 'block';
                daySelect.style.display = 'block';
                datePicker.style.display = 'none';
                daySelect.value = ''; // reset selection
            } else if (val === 'monthly') {
                day_section.style.display = 'block';
                daySelect.style.display = 'none';
                datePicker.style.display = 'block';
                datePicker.value = ''; // reset date picker
            }else if (val === 'daily') {
                day_section.style.display = 'none';
                daySelect.style.display = 'none';
                datePicker.style.display = 'none';
                datePicker.value = ''; // reset date picker
                daySelect.value = ''; // reset selection
            }
        });
    }
    
    
    const edit_emailFrequency = document.getElementById('edit_emailFrequency');
    const edit_daySelect = document.getElementById('edit_daySelect');
    const edit_datePicker = document.getElementById('edit_datePicker');
    const edit_day_section = document.getElementById('edit_day_section');
    if (edit_emailFrequency) {
        edit_emailFrequency.addEventListener('change', function () {
            const val = this.value;

            if (val === 'weekly' || val === 'biweekly') {
                edit_day_section.style.display = 'block';
                edit_daySelect.style.display = 'block';
                edit_datePicker.style.display = 'none';
                edit_daySelect.value = ''; // reset selection
            } else if (val === 'monthly') {
                edit_day_section.style.display = 'block';
                edit_daySelect.style.display = 'none';
                edit_datePicker.style.display = 'block';
                edit_datePicker.value = ''; // reset date picker
            }else if (val === 'daily') {
                edit_day_section.style.display = 'none';
                edit_daySelect.style.display = 'none';
                edit_datePicker.style.display = 'none';
                edit_datePicker.value = ''; // reset date picker
                edit_daySelect.value = ''; // reset selection
            }
        });
    }

    $(document).ready(function () {
//        getFilterFormData();
//        getListData();
        modalAddBtnHideShow();
    });
    $(document).on('change', '#ChooseTrainingScheduler', function () {
        getFilterFormData();
        getListData();
        modalAddBtnHideShow();
    });
    function modalAddBtnHideShow() {
        if ($('#ChooseTrainingScheduler').val() == '') {
            $('#addModalbtn').hide();
        } else {
            $('#addModalbtn').show();
        }
    }

    function getFilterFormData() {
        $('.filterSection').hide();

        $('.filterPartHideShowSec').hide();

        if ($('#ChooseTrainingScheduler').val() == 'Training Expiry') {
            $('.filterForTrainingScheduler').show();
            $.post("/record/process?action=Load_Advance_External_Training_Record_Listing", {
                'days': 0
            }, function (x) {
                var $select = $('.person_name_list');
                $select.empty();
                var personNameHtml = '<option value="">Select</option>';
                var uniquePersonNames = {};
                // no training sectin work 03102024 
                $.each(x.traineeManagementData, function (index, item) {
                    if (!uniquePersonNames[item.user_issue_id]) {
                        uniquePersonNames[item.user_issue_id] = true;

                        personNameHtml += '<option value="' + item.trainee_name + '" data-userId="' + item.user_issue_id + '" >' + item.trainee_name + '</option>';
                    }
                });
                $select.append(personNameHtml);

                var $select = $('.training_name_list');
                $select.empty();
                var traineeNameHtml = '<option value="">Select</option>';
                var uniqueTrainingNames = {};
                $.each(x.traineeManagementData, function (index, item) {
                    var trainingName = item.training_name.trim();
                    if (!uniqueTrainingNames[trainingName]) {
                        uniqueTrainingNames[trainingName] = true;
                        traineeNameHtml += '<option value="' + trainingName + '">' + trainingName + '</option>';
                    }
                });
                $select.append(traineeNameHtml);
            });

        }



        if ($('#ChooseTrainingScheduler').val() == 'Audit Plan') {
            $('.filterForAuditPlan').show();
            console.log('sdsdf');
        }
    }

    function getListData() {
        $.post("/manager/organization.php?action=GetReportCronList",
                {type: $('#ChooseTrainingScheduler').val(), org_id: $('#curr_org_id').val()},
                function (response) {
                    if (response.success && response.getAllData) {
                        var tbody = $('table tbody');
                        tbody.empty(); // clear existing rows
                        if (response.success && response.getAllData && response.getAllData.length > 0) {
                            response.getAllData.forEach(function (item) {
                                // Parse the filterHtml if you want to use it directly
                                var filterHtml = item.filterHtml;

                                // Build the table row
                                var row = `
                        <tr id="trSec_${item.id}">
                            <td>
                                <div style="display: flex; align-items: center;">
                                    <a href="javascript:void(0);">
                                        <span class="tb-note">
                                            <i class="fa fa-pencil-square-o editFrm" aria-hidden="true" 
                                               onclick="document.getElementById('edit-modal').classList.add('show')" 
                                               data-id="${item.id}"></i>
                                        </span>
                                    </a>
                                    <a href="javascript:void(0);">
                                        <span class="tb-red">
                                            <i class="fa fa-trash-o deleteData" aria-hidden="true" data-id="${item.id}"></i>
                                        </span>
                                    </a>
                                </div>
                            </td>
                            <td>${item.user_name}</td>
                            <td>${item.email_frequency}</td>
                            <td>${item.email_day}</td>
                            <td class="text-center">
                                <a href="/manager/organization?reportcronlog&cid=${item.id}&orgid=${item.org_id}">
                                    ${item.total_logs}
                                </a>
                            </td>
                            <td>
                                <div class="details-card">
                                    <div class="details-header">
                                        <span>View Details</span>
                                        <i class="fa fa-chevron-down toggle-arrow"></i>
                                    </div>
                                    <div class="details-content">
                                        <div class="details-inner">
                                            ${filterHtml}
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr> `;
                                tbody.append(row);
                            });

                            // Re-attach toggle event for newly added rows
                            $('.details-card').off('click').on('click', '.details-header', function () {
                                var content = $(this).siblings('.details-content');
                                var arrow = $(this).find('.toggle-arrow');
                                if (content.css('max-height') !== '0px') {
                                    content.css('max-height', '0');
                                    arrow.css('transform', 'rotate(0deg)');
                                } else {
                                    content.css('max-height', content[0].scrollHeight + 'px');
                                    arrow.css('transform', 'rotate(180deg)');
                                }
                            });
                        } else {
                            // No data found
                            tbody.append(`
                    <tr>
                        <td colspan="6" style="text-align:center; color:#6b7280; padding:20px;">
                            No data found
                        </td>
                    </tr>
                `);
                        }
                    }
                }, 'json'
                );
    }


    $(document).on('change', '.person_name_list', function () {
        var selectedOption = $(this).find('option:selected');
        var userId = selectedOption.data('userid');
        console.log("Selected userId:", userId);

        var userInput = $(this).closest('form')
                .find("input[name='send_by_filter[user_issue_id]']");

        if (userInput.length) {
            userInput.val(userId !== undefined ? userId : 0);
            console.log("Updated input value:", userInput.val());
        } else {
            console.log("Input not found inside form");
        }
    });




    $(document).on('click', '.tb-note i.fa-pencil-square-o', function (e) {
        e.preventDefault();                       // stop <a href="#"> jump
        var $modal = $('#edit-modal');
        $modal.addClass('show');
        $modal.find('.filterSection').css('display', 'flex'); // override any previous .hide()
    });

    $(document).on('click', '.plus-1', function () {
        var $modal = $('#open-modal');
        $modal.find('.filterSection').css('display', 'flex');
        resetModal();
    });


    function resetModal() {
        let modal = document.getElementById('open-modal');
        modal.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
        modal.querySelectorAll('input').forEach(inp => {
            if (inp.type === 'checkbox' || inp.type === 'radio') {
                inp.checked = false;
            } else {
                inp.value = '';
            }
        });
    }


    function clearErrors(modal) {
        modal.querySelectorAll('.error-msg').forEach(err => err.remove());
    }

    function showError(element, message) {
        let errorEl = document.createElement('div');
        errorEl.style.color = 'red';
        errorEl.style.fontSize = '12px';
        errorEl.textContent = message;
        errorEl.className = 'error-msg';

        element.parentElement.appendChild(errorEl);
    }

    function validateAndSubmit() {
        let modal = document.getElementById('open-modal');
        clearErrors(modal);

        let userName = modal.querySelector('select[name="user_id"]');
        let emailFreq = modal.querySelector('select[name="email_frequency"]');
        let daySelect = modal.querySelector('#adddaySelect');


        let valid = true;

        if (!userName.value) {
            showError(userName, "User Name is required.");
            valid = false;
        }
        if (!emailFreq.value) {
            showError(emailFreq, "Email Frequency is required.");
            valid = false;
        }
        if (daySelect.style.display !== "none" && !daySelect.value) {
            showError(daySelect, "Day is required.");
            valid = false;
        }

        if (!valid)
            return;
        
        $('input[name="org_id"]').val($('#curr_org_id').val());

        let formData = $('#addNewReportCronFrm').serializeArray();
        let reportType = $('#ChooseTrainingScheduler').val();
        formData.push({name: "report_type", value: reportType});

        if (reportType === "Training Expiry") {
            formData = formData.filter(function (field) {
                return field.name !== "send_by_filter[audit_scheduled_in]";
            });
        }
        if (reportType === "Audit Plan") {
            formData = formData.filter(function (field) {
                return field.name !== "send_by_filter[trainee_name]" &&
                        field.name !== "send_by_filter[user_issue_id]" &&
                        field.name !== "send_by_filter[training_name]" &&
                        field.name !== "send_by_filter[users_type]" &&
                        field.name !== "send_by_filter[training_result]" &&
                        field.name !== "send_by_filter[expiry_from_date]" &&
                        field.name !== "send_by_filter[expiry_to_date]";
            });
        }


        $.post(
                "/manager/organization.php?action=AddNewReportCron",
                formData,
                function (response) {
                    console.log(response);
                    if (response.success) {
                        Toast.fire({
                            icon: "success",
                            title: response.message,
                            background: '#d4edda',
                            color: '#155724',
                        });
                        getListData();
                        document.getElementById('open-modal').classList.remove('show');
                    } else {

                        Toast.fire({
                            icon: "error",
                            title: response.message,
                            background: '#f8d7da',
                            color: '#721c24',
                        });
                    }
                },
                "json" // tells jQuery to parse response as JSON
                );
    }

    function validateAndSubmitEdit() {
        let modal = document.getElementById('edit-modal');
        clearErrors(modal);

        let userName = modal.querySelector('select[name="user_id"]');
        let emailFreq = modal.querySelector('select[name="email_frequency"]');
        let daySelect = modal.querySelector('#edit_daySelect');


        let valid = true;

        if (!userName.value) {
            showError(userName, "User Name is required.");
            valid = false;
        }
        if (!emailFreq.value) {
            showError(emailFreq, "Email Frequency is required.");
            valid = false;
        }
        if (daySelect.style.display !== "none" && !daySelect.value) {
            showError(daySelect, "Day is required.");
            valid = false;
        }

        if (!valid)
            return;
//    if (valid) {
//        alert("Form edited submitted successfully!");
//    }
        let formData = $('#editReportCronFrm').serializeArray();
        let reportType = $('#ChooseTrainingScheduler').val();
        formData.push({name: "report_type", value: $('#ChooseTrainingScheduler').val()});

        if (reportType === "Training Expiry") {
            formData = formData.filter(function (field) {
                return field.name !== "send_by_filter[audit_scheduled_in]";
            });
        }
        if (reportType === "Audit Plan") {
            formData = formData.filter(function (field) {
                return field.name !== "send_by_filter[trainee_name]" &&
                        field.name !== "send_by_filter[user_issue_id]" &&
                        field.name !== "send_by_filter[training_name]" &&
                        field.name !== "send_by_filter[users_type]" &&
                        field.name !== "send_by_filter[training_result]" &&
                        field.name !== "send_by_filter[expiry_from_date]" &&
                        field.name !== "send_by_filter[expiry_to_date]";
            });
        }

        $.post(
                "/manager/organization.php?action=editNewReportCron",
                formData,
                function (response) {
                    if (response.success) {
                        Toast.fire({
                            icon: "success",
                            title: response.message,
                            background: '#d4edda',
                            color: '#155724',
                        });
                        getListData();
                        document.getElementById('edit-modal').classList.remove('show')

                    } else {

                        Toast.fire({
                            icon: "error",
                            title: response.message,
                            background: '#f8d7da',
                            color: '#721c24',
                        });
                    }
                }, "json");
    }




    $(document).on('click', ".editFrm", function () {
        var id = $(this).data('id');

        $.post("/manager/organization.php?action=GetReportCronDetails", {id: id}, function (response) {
            if (response.success && response.getDetails) {
                var d = response.getDetails;

                $("#formId").val(d.id);
                $("select[name='user_id']").val(d.user_id);
                $("select[name='email_frequency']").val(d.email_frequency);
                
                if (d.email_frequency && d.email_frequency == 'monthly') {
                    document.getElementById('edit_day_section').style.display = 'block';
                    $("#edit_datePicker").val(d.email_day).show();
                    $("#edit_daySelect").hide();
                } if (d.email_frequency && d.email_frequency == 'daily') {
                    document.getElementById('edit_day_section').style.display = 'none';
                    $("#edit_datePicker").hide();
                    $("#edit_daySelect").hide();
                }else {                    
                    document.getElementById('edit_day_section').style.display = 'block';
                    $("#edit_daySelect").val(d.email_day).show();
                    $("#edit_datePicker").hide();
                }

                if (d.send_by_filter) {
                    try {
                        let filterStr = d.send_by_filter
                                .replace(/'([^']+)'/g, '$1') // remove quotes around keys
                                .replace(/\\"/g, '"');
                        let filterObj = JSON.parse(filterStr);

                        console.log("Full filter object:", filterObj);

                        // Set select values if the option exists
                        $("#editReportCronFrm select[name='send_by_filter[trainee_name]']").val(filterObj.trainee_name);

                        $("#editReportCronFrm select[name='send_by_filter[days]']").val(filterObj.days);
                        
//                        $("#editReportCronFrm select[name='send_by_filter[training_name]']")
//                                .val(filterObj.training_name);
//
//                        $("#editReportCronFrm select[name='send_by_filter[users_type]']")
//                                .val(filterObj.users_type);
//
//                        $("#editReportCronFrm select[name='send_by_filter[training_result]']")
//                                .val(filterObj.training_result);
//
//                        // Set input values
//                        $("#editReportCronFrm input[name='send_by_filter[expiry_from_date]']")
//                                .val(filterObj.expiry_from_date);
//
//                        $("#editReportCronFrm input[name='send_by_filter[expiry_to_date]']")
//                                .val(filterObj.expiry_to_date);

                    } catch (e) {
                        console.error("Failed to parse send_by_filter:", e);
                    }
                }

                document.getElementById('edit-modal').classList.add('show');
            }
        }, 'json');
    });




    $(document).on('click', ".deleteData", function () {
        var id = $(this).data('id');
        Swal.fire({
            title: "Are you sure?",
            text: "You won't be able to revert this!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Yes, delete it!"
        }).then((result) => {
            if (result.isConfirmed) {
                $.post("/manager/organization.php?action=DeleteReportCron", {id: id}, function (response) {
                    if (response.success) {
                        Toast.fire({
                            icon: "success",
                            title: response.message,
                            background: '#d4edda',
                            color: '#155724',
                        });
                        $('#trSec_' + id).remove();
                    } else {

                        Toast.fire({
                            icon: "error",
                            title: response.message,
                            background: '#f8d7da',
                            color: '#721c24',
                        });
                    }
                }, 'json');

            }
        });

    });



}



