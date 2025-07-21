'use strict';

$(function () {
  let cancelApprovalFees_Form = $('#cancelApprovalFees_Form');
  let cancelApprovalFees_Modal = $('#cancelApprovalFees_Modal');
  let currentInvoiceId;

  /** Init */

  initDates();

  /** Handler events */

  $('a.cancel-approval-fees').on('click', function (event) {
    event.preventDefault();
    currentInvoiceId = $(this).attr('data-bs-invoiceid');
    cancelApprovalFees_Modal.modal('show');
  });

  $('#cancelApprovalFees_Modal button.btn-close, #cancelApprovalFees_Modal button#btnCancel').click(function (event) {
    event.preventDefault();

    resetModalInputs('cancelApprovalFees_Form');
    resetModalInputErrors();
    cancelApprovalFees_Modal.modal('hide');
  });

  $('#cancelApprovalFees_Form button#btnConfirm').click(function (event) {
    event.preventDefault();

    let formData = new FormData(cancelApprovalFees_Form[0]);
    formData.set('billing_id', currentInvoiceId);

    $.ajax({
      url: cancelApprovalFees_Form.attr('action'),
      type: "POST",
      cache: false,
      contentType: false,
      processData: false,
      dataType : 'json',
      data: formData,
      beforeSend: function () {
        resetModalInputErrors();
        cancelApprovalFees_Modal.find('.request-spinner').removeClass('d-none');
        cancelApprovalFees_Modal.find('.actions-wrapper').addClass('d-none');
        cancelApprovalFees_Modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        cancelApprovalFees_Modal.find('.request-spinner').addClass('d-none');
        cancelApprovalFees_Modal.find('.actions-wrapper').removeClass('d-none');
        cancelApprovalFees_Modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          request_id: window.variables.messages.error_occurred_during_operation
        }, cancelApprovalFees_Form);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            resetModalInputs('cancelApprovalFees_Form');
            cancelApprovalFees_Modal.modal('hide');

            location.reload();
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, cancelApprovalFees_Form);
            break;
          default:
            Helpers.customInputErrorsValidation(
              {request_id: result.message},
              cancelApprovalFees_Form,
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
    if(formId === 'cancelApprovalFees_Form') {
      cancelApprovalFees_Form.trigger('reset');
    }
  }

  function initDates() {
    let date = new Date();

    let expirationDate = $('input#oldExpirationDate');
    if (expirationDate.length > 0) {
      expirationDate.flatpickr({
        monthSelectorType: 'static',
      });
    }
  }
});
