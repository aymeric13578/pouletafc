'use strict';

$(function () {
  let addRequiredAttachments_Modal = $('#addRequiredAttachments_Modal');
  let addRequiredAttachments_Form = $('#addRequiredAttachments_Form');
  let btnAddRequiredAttachments = $('#btnAddRequiredAttachments');

  /** Event listeners */

  btnAddRequiredAttachments.on('click', function(event) {
    event.preventDefault();

    addRequiredAttachments_Modal.modal('show');
  });

  $('#addRequiredAttachments_Modal #btnAdd').click(function (event) {
    event.preventDefault();

    let formData = new FormData(addRequiredAttachments_Form[0]);
    formData.append(
      'request_id',
      addRequiredAttachments_Form.attr('data-bs-request')
    );

    $.ajax({
      url: addRequiredAttachments_Form.attr('action'),
      type: "POST",
      cache: false,
      contentType: false,
      processData: false,
      dataType : 'json',
      data: formData,
      beforeSend: function () {
        resetModalInputErrors(addRequiredAttachments_Form);
        addRequiredAttachments_Modal.find('.request-spinner').removeClass('d-none');
        addRequiredAttachments_Modal.find('.actions-wrapper').addClass('d-none');
        addRequiredAttachments_Modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        addRequiredAttachments_Modal.find('.request-spinner').addClass('d-none');
        addRequiredAttachments_Modal.find('.actions-wrapper').removeClass('d-none');
        addRequiredAttachments_Modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          request_id: window.variables.messages.unable_to_perform_request
        }, addRequiredAttachments_Modal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            addRequiredAttachments_Modal.modal('hide');
            window.location.reload();
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, addRequiredAttachments_Modal);
            break;
          default:
            Helpers.customInputErrorsValidation({
              request_id: result.message
            }, addRequiredAttachments_Modal);
            break;
        }
      },
    });
  });

  $('#addRequiredAttachments_Modal #btnCancel, #addRequiredAttachments_Modal .btn-close').on('click', function(event) {
    event.preventDefault();

    // Hide modal
    addRequiredAttachments_Modal.modal('hide');
    resetModalInputErrors(addRequiredAttachments_Form);
    resetModalInputs('addRequiredAttachments_Form');
  });

  /** Utilities */

  function resetModalInputErrors(wrapper) {
    wrapper.find('.error-helper').addClass('d-none').html('');
    wrapper.find('input').removeClass('is-invalid').removeClass('border-danger');
    wrapper.find('.select2').removeClass('is-invalid').removeClass('border-danger');
  }

  function resetModalInputs(wrapperId) {
    if(wrapperId === 'addRequiredAttachments_Form') {
      addRequiredAttachments_Form.trigger('reset');
    }
  }
});
