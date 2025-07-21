'use strict';

$(function () {
  let updateAttachment_Modal = $('#updateAttachment_Modal');
  let updateAttachment_Form = $('#updateAttachment_Form');
  let btnUpdateAttachment = $('#btnUpdateAttachment');
  let tbody = $('#attachmentTable tbody');
  let currentAttachment;

  /** Event listeners */

  btnUpdateAttachment.on('click', function(event) {
    event.preventDefault();

    renderUpdateModal($(this));
    updateAttachment_Modal.modal('show');
  });

  tbody.on('click', 'a.update-attachment-action', function(event) {
    event.preventDefault();

    renderUpdateModal($(this));
    updateAttachment_Modal.modal('show');
  });

  $('#updateAttachment_Modal #btnEdit').click(function (event) {
    event.preventDefault();

    let formData = new FormData(updateAttachment_Form[0]);
    formData.append('attachment_id', currentAttachment);

    $.ajax({
      url: updateAttachment_Form.attr('action'),
      type: "POST",
      cache: false,
      contentType: false,
      processData: false,
      dataType : 'json',
      data: formData,
      beforeSend: function () {
        resetModalInputErrors(updateAttachment_Form);
        updateAttachment_Modal.find('.request-spinner').removeClass('d-none');
        updateAttachment_Modal.find('.actions-wrapper').addClass('d-none');
        updateAttachment_Modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        updateAttachment_Modal.find('.request-spinner').addClass('d-none');
        updateAttachment_Modal.find('.actions-wrapper').removeClass('d-none');
        updateAttachment_Modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          request_id: window.variables.messages.unable_to_perform_request
        }, updateAttachment_Modal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            updateAttachment_Modal.modal('hide');
            window.location.reload();
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, updateAttachment_Modal);
            break;
          default:
            Helpers.customInputErrorsValidation({
              request_id: result.message
            }, updateAttachment_Modal);
            break;
        }
      },
    });
  });

  $('#updateAttachment_Modal #btnCancel, #updateAttachment_Modal .btn-close').on('click', function(event) {
    event.preventDefault();

    // Hide modal
    updateAttachment_Modal.modal('hide');
    resetModalInputErrors(updateAttachment_Form);
    resetModalInputs('updateAttachment_Form');
  });

  /** Utilities */

  function resetModalInputErrors(wrapper) {
    wrapper.find('.error-helper').addClass('d-none').html('');
    wrapper.find('input').removeClass('is-invalid').removeClass('border-danger');
    wrapper.find('.select2').removeClass('is-invalid').removeClass('border-danger');
  }

  function resetModalInputs(wrapperId) {
    if(wrapperId === 'addRequiredAttachments_Form') {
      updateAttachment_Form.trigger('reset');
    }
  }

  function renderUpdateModal(element) {
    currentAttachment = element.attr('data-bs-attachment');

    updateAttachment_Modal.find('.at-designation')
      .empty()
      .html(element.attr('data-bs-designation') + " *");
    updateAttachment_Modal.find('input.at-file')
      .attr('class', '')
      .attr('class', 'form-control at-file attachment_' + element.attr('data-bs-attachment'))
      .attr('name', 'attachment_' + element.attr('data-bs-attachment'));
    updateAttachment_Modal.find('.computable-error-helper')
      .attr('id', 'attachment_' + element.attr('data-bs-attachment') + 'ErrorHelper');
  }
});
