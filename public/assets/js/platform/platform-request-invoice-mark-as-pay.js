'use strict';

$(function () {
  let invoiceValidation_Form = $('#invoiceValidation_Form');
  let invoiceValidation_Modal = $('#invoiceValidation_Modal');
  let currentInvoiceId;
  let commentRequestEditor;

  /** Init */
  initEditors();

  /** Handler events */

  $('#markInvoiceAsPay').click(function (event) {
    event.preventDefault();
    currentInvoiceId = $(this).attr('data-bs-invoiceid');
    invoiceValidation_Modal.modal('show');
  });

  $('#invoicesTable tbody').on('click', 'td a.mark-as-pay', function (event) {
    event.preventDefault();
    currentInvoiceId = $(this).attr('data-bs-invoiceid');
    invoiceValidation_Modal.modal('show');
  });

  $('a.mark-as-pay').on('click', function (event) {
    event.preventDefault();
    currentInvoiceId = $(this).attr('data-bs-invoiceid');
    invoiceValidation_Modal.modal('show');
  });

  $('#invoiceValidation_Modal button.btn-close, #invoiceValidation_Modal button#btnCancel').click(function (event) {
    event.preventDefault();

    resetModalInputs('invoiceValidation_Form');
    resetModalInputErrors();
    invoiceValidation_Modal.modal('hide');
  });

  $('#invoiceValidation_Modal button#btnSave').click(function (event) {
    event.preventDefault();

    let formData = new FormData(invoiceValidation_Form[0]);
    formData.set('comments', commentRequestEditor.getText().trim().length > 0 ? commentRequestEditor.root.innerHTML : '');
    formData.set('invoice_id', currentInvoiceId);


    $.ajax({
      url: invoiceValidation_Form.attr('action'),
      type: "POST",
      cache: false,
      contentType: false,
      processData: false,
      dataType : 'json',
      data: formData,
      beforeSend: function () {
        resetModalInputErrors();
        invoiceValidation_Modal.find('.request-spinner').removeClass('d-none');
        invoiceValidation_Modal.find('.actions-wrapper').addClass('d-none');
        invoiceValidation_Modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        invoiceValidation_Modal.find('.request-spinner').addClass('d-none');
        invoiceValidation_Modal.find('.actions-wrapper').removeClass('d-none');
        invoiceValidation_Modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          error: window.variables.messages.error_occurred_during_operation
        }, invoiceValidation_Form);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            resetModalInputs('invoiceValidation_Form');
            invoiceValidation_Modal.modal('hide');

            location.reload();
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, invoiceValidation_Form);
            break;
          default:
            Helpers.customInputErrorsValidation(
              {error: result.message},
              invoiceValidation_Form,
            );
            break;
        }
      },
    });
  });

  /** Functions */

  function resetModalInputErrors() {
    $('.error-helper').addClass('d-none').html('');
    $('input').removeClass('is-invalid').removeClass('border-danger');
    $('.select2').removeClass('is-invalid').removeClass('border-danger');
  }

  function resetModalInputs(formId) {
    if(formId === 'invoiceValidation_Form') {
      invoiceValidation_Form.trigger('reset');
      commentRequestEditor.setText('');
    }
  }

  /** Utilities */

  function initEditors() {
    commentRequestEditor = new Quill(
      document.querySelector(".billingValCommentEditor"),
      {
        modules: {toolbar: ".billingValCommentToolbar"},
        placeholder: window.variables.messages.add_comment + " ...",
        theme: "snow"
      }
    );
  }
});
