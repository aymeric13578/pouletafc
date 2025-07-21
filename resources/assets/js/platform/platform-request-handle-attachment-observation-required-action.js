'use strict';

$(function () {
  $('.comment-action-solved').click(function (event) {
    event.preventDefault();

    let $this = $(this);
    let modalWrapper = $("#commentLinkActionModal_" + $this.attr('data-bs-target'));
    let formWrapper = $("#commentLinkActionForm_" + $this.attr('data-bs-target'));

    // Add close events ....
    modalWrapper.find('button.btn-close, button#btnCancel').unbind();
    modalWrapper.find('button.btn-close, button#btnCancel').bind("click", function(event) {
      event.preventDefault();

      resetModalInputs(formWrapper);
      resetModalInputErrors(formWrapper);
      modalWrapper.modal('hide');
    });

    modalWrapper.find('button#btnAdd').unbind();
    modalWrapper.find('button#btnAdd').bind("click", function(event) {
      event.preventDefault();
      addRequestedCommentDoc(formWrapper, modalWrapper);
    });

    modalWrapper.modal('show');
  });

  function addRequestedCommentDoc(form, modal) {
    let formData = new FormData(form[0]);
    formData.append('request_comment', form.attr('data-bs-targetcomment'));

    $.ajax({
      url: form.attr('action'),
      type: "POST",
      cache: false,
      contentType: false,
      processData: false,
      dataType : 'json',
      data: formData,
      beforeSend: function () {
        resetModalInputErrors(form);
        modal.find('.request-spinner').removeClass('d-none');
        modal.find('.actions-wrapper').addClass('d-none');
        modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        modal.find('.request-spinner').addClass('d-none');
        modal.find('.actions-wrapper').removeClass('d-none');
        modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          request_comment: window.variables.messages.unable_to_perform_request
        }, modal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            modal.modal('hide');
            window.location.reload();
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, modal);
            break;
          default:
            Helpers.customInputErrorsValidation({
              request_comment: result.message
            }, modal);
            break;
        }
      },
    });
  }

  // Utilities

  function resetModalInputErrors(wrapper) {
    wrapper.find('.error-helper').addClass('d-none').html('');
    wrapper.find('input').removeClass('is-invalid').removeClass('border-danger');
    wrapper.find('.select2').removeClass('is-invalid').removeClass('border-danger');
  }

  function resetModalInputs(form) {
    form.trigger('reset');
  }
});
