$(document).ready(function () {
    $(".simple-spinner").hide();
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
$(document).on('change', '#search_by_dep_id', function () {
   $(this).closest('form').submit();
});