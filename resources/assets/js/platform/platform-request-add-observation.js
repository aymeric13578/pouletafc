'use strict';

$(function () {
  let toastAnimation;
  let toastWrapper = $('.toast-wrapper');
  let addRequestCommentForm = $('#addRequestCommentForm');
  let addRequestComment_Modal = $('#addRequestComment_Modal');
  let commentRequestEditor;
  let tagifyAttachmentTypeWrapper = $('#tagifyAttachmentTypeWrapper');

  // Init ...
  initEditors();
  initSelect2();
  toastAnimation = getToastInstance();

  // Events handler

  $('#applicationFeedBack').on('change', function(event) {
    event.preventDefault();
    let $this = $(this);

    addRequestCommentForm.find('.select2').val('').trigger('change');
    // tagifyAttachmentTypeWrapper.collapse($this.is(':checked') ? 'show' : 'hide');
  });

  // Request comment modal ...

  $('form#addRequestCommentForm button#btnSave').click(function (event) {
    event.preventDefault();

    let jsonData = JSON.parse(Helpers.convertFormToJSON(addRequestCommentForm[0]));
    jsonData['comment'] = commentRequestEditor.getText().trim().length ? commentRequestEditor.root.innerHTML : null;
    jsonData['request_id'] = addRequestCommentForm.attr('data-bs-request');
    jsonData['attachment_type_ids'] = Helpers.getMultipleSelectValues(tagifyAttachmentTypeWrapper);
    jsonData['comment_option'] = 0;

    $.ajax({
      url: addRequestCommentForm.attr('action'),
      type: "POST",
      dataType : 'json',
      data: jsonData,
      beforeSend: function () {
        resetModalInputErrors();
        addRequestCommentForm.find('.request-spinner').removeClass('d-none');
        addRequestCommentForm.find('.actions-wrapper').addClass('d-none');
        addRequestCommentForm.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        addRequestCommentForm.find('.request-spinner').addClass('d-none');
        addRequestCommentForm.find('.actions-wrapper').removeClass('d-none');
        addRequestCommentForm.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          comment: window.variables.messages.unable_to_add_request_comment
        }, addRequestCommentForm);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            if(result.data.require_action === 0) {
              resetModalInputs('addRequestCommentForm');
              addRequestComment_Modal.modal('hide');

              // Show toast
              Helpers.renderToastInfo(
                toastWrapper,
                window.variables.messages.request_comment_added_successfully
              );
              toastAnimation.show();
            } else {
              addRequestComment_Modal.modal('hide');
              location.href = result.data.redirection_url;
            }
            break;
          case 150:
            if(Object.keys(result.data).indexOf('comment_option') >= 0) {
              Helpers.customInputErrorsValidation({
                comment: result.data['comment_option'].toString()
              }, addRequestCommentForm);
            } else {
              Helpers.customInputErrorsValidation(result.data, addRequestCommentForm);
            }
            break;
          default:
            Helpers.customInputErrorsValidation({comment: result.message}, addRequestCommentForm);
            break;
        }
      },
    });
  });

  $('#addRequestCommentForm button.btn-close, #addRequestCommentForm button#btnCancel').click(function (event) {
    event.preventDefault();

    resetModalInputs('addRequestCommentForm');
    resetModalInputErrors();
    addRequestComment_Modal.modal('hide');
  });

  $('.btn_AddObservation').click(function (event) {
    event.preventDefault();
    addRequestComment_Modal.modal('show');
  });

  // Functions

  function initEditors() {
    commentRequestEditor = new Quill(
      document.querySelector(".stnp-comment-editor"),
      {
        modules: {toolbar: ".stnp-comment-toolbar"},
        placeholder: window.variables.messages.type_observation + " ...",
        theme: "snow"
      }
    );
  }

  function getToastInstance() {
    return new bootstrap.Toast(toastWrapper, {
      autohide: true
    });
  }

  function resetModalInputErrors() {
    $('.error-helper').addClass('d-none').html('');
    $('input').removeClass('is-invalid').removeClass('border-danger');
    $('.select2').removeClass('is-invalid').removeClass('border-danger');
  }

  function resetModalInputs(formId) {
    if(formId === 'addRequestCommentForm') {
      addRequestCommentForm.trigger('reset');
      commentRequestEditor.setText('');
      addRequestCommentForm.find('.select2').val('').trigger('change');
    }
  }

  function initSelect2() {
    // Select2
    let select2 = $('.select2');
    // Select2 API Key
    if (select2.length) {
      select2.each(function () {
        let $this = $(this);
        $this.wrap('<div class="position-relative"></div>');
        $this.select2({
          dropdownParent: $this.parent(),
        });
      });
    }
  }
});
