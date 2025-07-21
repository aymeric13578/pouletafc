/**
 * Add Attachment type Modal JS
 */

'use strict';

// Add attachment type form validation
document.addEventListener('DOMContentLoaded', function (e) {
  (function () {
    $('#saveAttachmentTypeData').click(function(e){
      e.preventDefault();
      $(this).html('Sending...');
      $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

      $.ajax({
        data: {
          designation:$('#modalAttachmentTypeDesignation')[0].value,
          code:$('#modalAttachmentTypeCode')[0].value
        },
        url: '/attachment_types/store',
        type: "POST",
        dataType: 'json',
        success: function(data){
          $('#addAttachmentTypeForm').trigger("reset");
          $('#addAttachmentTypeModal').modal('hide');
          table.draw();
          toastr.success('Data saved successfully', 'success');
            },
        error: function(data){
          $('#error').html("<div class='alert alert-danger'>"+data['responseJSON']['message']+"</div>");
          $('#saveAttachmentTypeData').html('save attachment type data');
        }
      });
      toastr.options = {
        "closeButton": true,
        "debug": false,
        "newestOnTop": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "preventDuplicates": true,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
      }

      var table = $('.data-table').DataTable({
        processing: true,
        serverSide: true,
        select: true,
        info: false,
        paging: false,
        filter:false,
        ajax: "{{url('attachment_types/attachment_management')}}",
        columns: [
          {data: 'DT_RowIndex', name: 'DT_RowIndex', class: 'text-center'},
          {data: 'code', name: 'code'},
          {data: 'designation', name: 'designation'},
          {data: 'action', name: 'action', orderable: false, searchable: false, class:'text-center'},
        ]
      });
    });
  })();
});
