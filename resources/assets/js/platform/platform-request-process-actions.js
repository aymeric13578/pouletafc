'use strict';

$(function () {
  // Var ...
  const allActionButtons = $('#dynamicProcessingActions .processing-actions');
  const actionForm = $('#dynamicProcessingActionsForm');
  const confirmForBillingElementGeneration_Modal = $('#confirmForBillingElementGeneration_Modal');
  const confirmForBillingElementGeneration_Form = $('#confirmForBillingElementGeneration_Form');
  const approvalGenerationFeesAction = $('.generate-approval-fees-action');
  const approvalCertificateGenerationFeesAction = $('.generate-approval-certificate-fees-action');
  const temporaryCertificateGenerationFeesAction = $('.generate-approval-temporary-certificate-fees-action');
  const markRequestAsCompleteAction = $('button.mark-request-as-complete');
  const body = $('body');
  let globalAlert = $('#globalAlert');
  let approveCloseRequest_Modal = $('#approveCloseRequest_Modal');
  let archiveRequest_Modal = $('#archiveRequest_Modal');
  let archiveRequest_Form = $('#archiveRequest_Form');
  let approveCloseRequest_Form = $('#approveCloseRequest_Form');
  let declineCloseRequest_Modal = $('#declineCloseRequest_Modal');
  let declineCloseRequest_Form = $('#declineCloseRequest_Form');
  let closeRequest_Modal = $('#closeRequest_Modal');
  let closeRequest_Form = $('#closeRequest_Form');
  let requestThumbnailFeesGeneration_Modal = $('#requestThumbnailFeesGeneration_Modal');
  let requestThumbnailFeesGeneration_Form = $('#requestThumbnailFeesGeneration_Form');
  let requestApprovalCertificateFeesGeneration_Modal = $('#requestApprovalCertificateFeesGeneration_Modal');
  let requestApprovalCertificateFeesGeneration_Form = $('#requestApprovalCertificateFeesGeneration_Form');
  let updateRequestReference_Form = $('#updateRequestReference_Form');
  let updateRequestReference_Modal = $('#updateRequestReference_Modal');
  let updateRequestAttachment_Modal = $('#updateRequestAttachment_Modal');
  let updateRequestAttachment_Form = $('#updateRequestAttachment_Form');
  let btnReplaceAttachmentForComment = $('#btnReplaceAttachmentForComment');
  let markRequestAsComplete_Modal = $('#markRequestAsComplete_Modal');
  let markRequestAsComplete_Form = $('#markRequestAsComplete_Form');

  let currentRequestTask;
  let declineReasonEditor;
  let lastRequestJsonData = null;

  /** Init components */

  initEditors();
  initDates();

  /** Event handler */

  $('#globalAlert .btn-close').click(function (event) {
    event.preventDefault();
    Helpers.handleAlertVisibility(globalAlert, false);
  });

  markRequestAsCompleteAction.click(function (event) {
    event.preventDefault();

    markRequestAsComplete_Modal.modal('show');
  });

  approvalGenerationFeesAction.click(function (event) {
    event.preventDefault();

    currentRequestTask = $(this).attr('data-bs-requesttask');
    if($(this).hasClass('generate-document')) {
      requestThumbnailFeesGeneration_Modal.find('div#applyVisaNotificationWrapper').removeClass("d-none");
      requestThumbnailFeesGeneration_Modal.find('div#applyQrCodeNotificationWrapper').removeClass("d-none");
    } else {
      requestThumbnailFeesGeneration_Modal.find('div#applyVisaNotificationWrapper').addClass("d-none");
      requestThumbnailFeesGeneration_Modal.find('div#applyQrCodeNotificationWrapper').addClass("d-none");
    }

    requestThumbnailFeesGeneration_Form.trigger('reset');
    requestThumbnailFeesGeneration_Modal.modal('show');
  });

  approvalCertificateGenerationFeesAction.click(function (event) {
    event.preventDefault();

    currentRequestTask = $(this).attr('data-bs-requesttask');
    if($(this).hasClass('generate-document')) {
      requestThumbnailFeesGeneration_Modal.find('div#applyVisaNotificationWrapper').removeClass("d-none");
      requestThumbnailFeesGeneration_Modal.find('div#applyQrCodeNotificationWrapper').removeClass("d-none");
    } else {
      requestThumbnailFeesGeneration_Modal.find('div#applyVisaNotificationWrapper').addClass("d-none");
      requestThumbnailFeesGeneration_Modal.find('div#applyQrCodeNotificationWrapper').addClass("d-none");
    }

    requestThumbnailFeesGeneration_Form.trigger('reset');
    requestThumbnailFeesGeneration_Modal.modal('show');
  });

  temporaryCertificateGenerationFeesAction.click(function (event) {
    event.preventDefault();

    currentRequestTask = $(this).attr('data-bs-requesttask');
    requestThumbnailFeesGeneration_Modal.find('div#applyVisaNotificationWrapper').removeClass("d-none");
    requestThumbnailFeesGeneration_Modal.find('div#applyQrCodeNotificationWrapper').removeClass("d-none");

    requestThumbnailFeesGeneration_Form.trigger('reset');
    requestThumbnailFeesGeneration_Modal.modal('show');
  });

  $('#markRequestAsComplete_Modal #btnCancel, #markRequestAsComplete_Modal .btn-close').on('click', function(event) {
    event.preventDefault();

    // Hide modal
    markRequestAsComplete_Modal.modal('hide');
  });

  $('#requestApprovalCertificateFeesGeneration_Modal #btnCancel, #requestApprovalCertificateFeesGeneration_Modal .btn-close').on('click', function(event) {
    event.preventDefault();
    // Hide modal
    requestApprovalCertificateFeesGeneration_Modal.modal('hide');
    resetModalInputErrors(requestApprovalCertificateFeesGeneration_Form);
    resetModalInputs('requestApprovalCertificateFeesGeneration_Form');
  });

  $('#updateRequestReference_Modal #btnCancel, #updateRequestReference_Modal .btn-close').on('click', function(event) {
    event.preventDefault();
    // Hide modal
    updateRequestReference_Modal.modal('hide');
    resetModalInputErrors(updateRequestReference_Form);
    resetModalInputs('updateRequestReference_Form');
  });

  $('#approveCloseRequest_Modal #btnCancel, #approveCloseRequest_Modal .btn-close').on('click', function(event) {
    event.preventDefault();

    // Hide modal
    resetModalInputErrors(approveCloseRequest_Form);
    resetModalInputs('approveCloseRequest_Form');
    approveCloseRequest_Modal.modal('hide');
  });

  $('#archiveRequest_Modal #btnCancel, #archiveRequest_Modal .btn-close').on('click', function(event) {
    event.preventDefault();

    // Hide modal
    resetModalInputErrors(archiveRequest_Form);
    resetModalInputs('archiveRequest_Form');
    archiveRequest_Modal.modal('hide');
  });

  $('#declineCloseRequest_Modal #btnCancel, #declineCloseRequest_Modal .btn-close').on('click', function(event) {
    event.preventDefault();
    // Hide modal
    declineCloseRequest_Modal.modal('hide');
    resetModalInputErrors(declineCloseRequest_Form);
    resetModalInputs('declineCloseRequest_Form');
  });

  $('#closeRequest_Modal #btnCancel, #closeRequest_Modal .btn-close').on('click', function(event) {
    event.preventDefault();
    // Hide modal
    closeRequest_Modal.modal('hide');
    resetModalInputErrors(closeRequest_Form);
    resetModalInputs('closeRequest_Form');
  });

  $('#requestThumbnailFeesGeneration_Modal #btnCancel, #requestThumbnailFeesGeneration_Modal .btn-close').on('click', function(event) {
    event.preventDefault();

    // Hide modal
    requestThumbnailFeesGeneration_Modal.modal('hide');
    resetModalInputErrors(requestThumbnailFeesGeneration_Form);
    resetModalInputs('requestThumbnailFeesGeneration_Form');
  });

  $('#updateRequestAttachment_Modal #btnCancel, #updateRequestAttachment_Modal .btn-close').on('click', function(event) {
    event.preventDefault();

    // Hide modal
    updateRequestAttachment_Modal.modal('hide');
    resetModalInputErrors(updateRequestAttachment_Form);
    resetModalInputs('updateRequestAttachment_Form');
  });

  $('#confirmForBillingElementGeneration_Modal #btnCancel, #confirmForBillingElementGeneration_Modal .btn-close').on('click', function(event) {
    event.preventDefault();

    // Hide modal
    lastRequestJsonData = null;
    confirmForBillingElementGeneration_Modal.modal('hide');
    resetModalInputErrors(confirmForBillingElementGeneration_Form);
  });

  body.on('click', '#dynamicProcessingActions button.processing-generic-actions', function(e) {
    e.preventDefault();
    let $this = $(this);

    let requestTarget = $this.attr('data-bs-requesttarget');
    let requestTask = $this.attr('data-bs-requesttask');

    if(requestTarget && requestTask) {
      let jsonData = JSON.parse(Helpers.convertFormToJSON(actionForm[0]));
      jsonData['request_id'] = requestTarget;
      jsonData['request_task_id'] = requestTask;

      lastRequestJsonData = jsonData;

      $.ajax({
        url: actionForm.attr('action'),
        type: actionForm.attr('method'),
        dataType : 'json',
        data: jsonData,
        beforeSend: function () {
          allActionButtons.prop('disabled', true);
          allActionButtons.find('span.spinner-border').removeClass('d-none');
          Helpers.handleAlertVisibility(globalAlert, false);
        },
        complete: function () {
          allActionButtons.prop('disabled', false);
          allActionButtons.find('span.spinner-border').addClass('d-none');
        },
        error: function () {
          Helpers.handleAlertVisibility(
            globalAlert,
            true,
            'alert-danger',
            window.variables.messages.unable_to_perform_request
          );
        },
        success: function (result) {
          switch (result.code) {
            case 100:
              Helpers.handleAlertVisibility(
                globalAlert,
                true,
                'alert-success',
                window.variables.messages.successful_operation
              );
              setTimeout(() => {
                window.location.reload();
              }, 1000);
              if(result.data && result.data.doc_url) {
                window.open(result.data.doc_url, '_blank');
              }
              break;
            case 150:
              let firstKey = Object.keys(result.data)[0];
              Helpers.handleAlertVisibility(
                globalAlert,
                true,
                'alert-danger',
                result.data[firstKey][0]
              );
              break;
            case 160:
              handleZeroBillingElementModal(result.message);
              break;
            default:
              Helpers.handleAlertVisibility(
                globalAlert,
                true,
                'alert-danger',
                result.message
              );
              break;
          }
        },
      });
    }
  });

  body.on('click', '#dynamicProcessingActions button.action_Close_for_approbation', function(e) {
    e.preventDefault();
    let $this = $(this);

    currentRequestTask = $this.attr('data-bs-requesttask');
    approveCloseRequest_Modal.modal('show');
  });

  body.on('click', '#dynamicProcessingActions button.action_Archive', function(e) {
    e.preventDefault();
    let $this = $(this);

    currentRequestTask = $this.attr('data-bs-requesttask');
    archiveRequest_Modal.modal('show');
  });

  body.on('click', '#dynamicProcessingActions button.action_Close_for_rejection', function(e) {
    e.preventDefault();
    let $this = $(this);

    currentRequestTask = $this.attr('data-bs-requesttask');
    declineCloseRequest_Modal.modal('show');
  });

  body.on('click', '#dynamicProcessingActions button.action_Close', function(e) {
    e.preventDefault();
    let $this = $(this);

    currentRequestTask = $this.attr('data-bs-requesttask');
    closeRequest_Modal.modal('show');
  });

  btnReplaceAttachmentForComment.on('click', function(event) {
    e.preventDefault();
    event.preventDefault();

    // updateRequestAttachment_Modal($(this));
    updateRequestAttachment_Modal.modal('show');
  });

  $('#updateRequestReference').click(function (event) {
    event.preventDefault();

    updateRequestReference_Modal.modal('show');
  });

  $('#approveCloseRequest_Form #btnAdd').click(function (event) {
    event.preventDefault();

    let formData = new FormData(approveCloseRequest_Form[0]);
    formData.append('request_task_id', currentRequestTask);

    $.ajax({
      url: approveCloseRequest_Form.attr('action'),
      type: "POST",
      cache: false,
      contentType: false,
      processData: false,
      dataType : 'json',
      data: formData,
      beforeSend: function () {
        resetModalInputErrors(approveCloseRequest_Form);

        approveCloseRequest_Modal.find('.request-spinner').removeClass('d-none');
        approveCloseRequest_Modal.find('.actions-wrapper').addClass('d-none');
        approveCloseRequest_Modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        approveCloseRequest_Modal.find('.request-spinner').addClass('d-none');
        approveCloseRequest_Modal.find('.actions-wrapper').removeClass('d-none');
        approveCloseRequest_Modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          request_id: window.variables.messages.unable_to_perform_request
        }, approveCloseRequest_Modal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            approveCloseRequest_Modal.modal('hide');

            if(result.data && result.data.redirect_url) {
              window.location.href = result.data.redirect_url;
            } else {
              setTimeout(() => {
                window.location.reload();
              }, 1000);
            }
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, approveCloseRequest_Modal);
            break;
          default:
            Helpers.customInputErrorsValidation({
              request_id: result.message
            }, approveCloseRequest_Modal);
            break;
        }
      },
    });
  });

  $('#archiveRequest_Form #btnAdd').click(function (event) {
    event.preventDefault();

    let formData = new FormData(archiveRequest_Form[0]);
    formData.append('request_task_id', currentRequestTask);

    $.ajax({
      url: archiveRequest_Form.attr('action'),
      type: "POST",
      cache: false,
      contentType: false,
      processData: false,
      dataType : 'json',
      data: formData,
      beforeSend: function () {
        resetModalInputErrors(archiveRequest_Form);

        archiveRequest_Modal.find('.request-spinner').removeClass('d-none');
        archiveRequest_Modal.find('.actions-wrapper').addClass('d-none');
        archiveRequest_Modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        archiveRequest_Modal.find('.request-spinner').addClass('d-none');
        archiveRequest_Modal.find('.actions-wrapper').removeClass('d-none');
        archiveRequest_Modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          request_id: window.variables.messages.unable_to_perform_request
        }, archiveRequest_Modal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            archiveRequest_Modal.modal('hide');

            if(result.data && result.data.redirect_url) {
              window.location.href = result.data.redirect_url;
            } else {
              setTimeout(() => {
                window.location.reload();
              }, 1000);
            }
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, archiveRequest_Modal);
            break;
          default:
            Helpers.customInputErrorsValidation({
              request_id: result.message
            }, archiveRequest_Modal);
            break;
        }
      },
    });
  });

  $('#declineCloseRequest_Form #btnAdd').click(function (event) {
    event.preventDefault();

    let jsonData = JSON.parse(Helpers.convertFormToJSON(declineCloseRequest_Form[0]));
    jsonData['request_task_id'] = currentRequestTask;
    jsonData['comment'] = declineReasonEditor.getText().trim().length ? declineReasonEditor.root.innerHTML : null;

    $.ajax({
      url: declineCloseRequest_Form.attr('action'),
      type: "POST",
      dataType : 'json',
      data: jsonData,
      beforeSend: function () {
        resetModalInputErrors(declineCloseRequest_Form);

        declineCloseRequest_Modal.find('.request-spinner').removeClass('d-none');
        declineCloseRequest_Modal.find('.actions-wrapper').addClass('d-none');
        declineCloseRequest_Modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        declineCloseRequest_Modal.find('.request-spinner').addClass('d-none');
        declineCloseRequest_Modal.find('.actions-wrapper').removeClass('d-none');
        declineCloseRequest_Modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          request_id: window.variables.messages.unable_to_perform_request,
        }, declineCloseRequest_Modal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            declineCloseRequest_Modal.modal('hide');

            if(result.data && result.data.redirect_url) {
              window.location.href = result.data.redirect_url;
            } else {
              setTimeout(() => {
                window.location.reload();
              }, 1000);
            }
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, declineCloseRequest_Modal);
            break;
          default:
            Helpers.customInputErrorsValidation({
              request_id: result.message,
            }, declineCloseRequest_Modal);
            break;
        }
      },
    });
  });

  $('#closeRequest_Form #btnCloseRequest').click(function (event) {
    event.preventDefault();

    let jsonData = JSON.parse(Helpers.convertFormToJSON(closeRequest_Form[0]));
    jsonData['request_task_id'] = currentRequestTask;

    $.ajax({
      url: closeRequest_Form.attr('action'),
      type: "POST",
      dataType : 'json',
      data: jsonData,
      beforeSend: function () {
        resetModalInputErrors(closeRequest_Form);

        closeRequest_Modal.find('.request-spinner').removeClass('d-none');
        closeRequest_Modal.find('.actions-wrapper').addClass('d-none');
        closeRequest_Modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        closeRequest_Modal.find('.request-spinner').addClass('d-none');
        closeRequest_Modal.find('.actions-wrapper').removeClass('d-none');
        closeRequest_Modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          request_id: window.variables.messages.unable_to_perform_request,
        }, closeRequest_Modal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            closeRequest_Modal.modal('hide');

            if(result.data && result.data.redirect_url) {
              window.location.href = result.data.redirect_url;
            } else {
              setTimeout(() => {
                window.location.reload();
              }, 1000);
            }
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, closeRequest_Modal);
            break;
          default:
            Helpers.customInputErrorsValidation({
              request_id: result.message,
            }, closeRequest_Modal);
            break;
        }
      },
    });
  });

  $('#requestThumbnailFeesGeneration_Form #btnConfirm').click(function (event) {
    event.preventDefault();

    let jsonData = JSON.parse(Helpers.convertFormToJSON(requestThumbnailFeesGeneration_Form[0]));
    jsonData['request_task_id'] = currentRequestTask;

    $.ajax({
      url: requestThumbnailFeesGeneration_Form.attr('action'),
      type: "POST",
      dataType: 'json',
      data: jsonData,
      beforeSend: function () {
        resetModalInputErrors(requestThumbnailFeesGeneration_Form);

        requestThumbnailFeesGeneration_Modal.find('.request-spinner').removeClass('d-none');
        requestThumbnailFeesGeneration_Modal.find('.actions-wrapper').addClass('d-none');
        requestThumbnailFeesGeneration_Modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        requestThumbnailFeesGeneration_Modal.find('.request-spinner').addClass('d-none');
        requestThumbnailFeesGeneration_Modal.find('.actions-wrapper').removeClass('d-none');
        requestThumbnailFeesGeneration_Modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          request_id: window.variables.messages.unable_to_perform_request,
        }, requestThumbnailFeesGeneration_Modal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            requestThumbnailFeesGeneration_Modal.modal('hide');
            setTimeout(() => {
              window.location.reload();
            }, 1000);

            if(result.data && result.data.doc_url) {
              window.open(result.data.doc_url, '_blank');
            }
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, requestThumbnailFeesGeneration_Modal);
            break;
          case 160:
            lastRequestJsonData = jsonData;

            requestThumbnailFeesGeneration_Modal.modal('hide');
            handleZeroBillingElementModal(result.message);
            break;
          default:
            Helpers.customInputErrorsValidation({
              request_id: result.message,
            }, requestThumbnailFeesGeneration_Modal);
            break;
        }
      },
    });
  });

  $('#requestApprovalCertificateFeesGeneration_Form #btnConfirm').click(function (event) {
    event.preventDefault();

    let jsonData = JSON.parse(Helpers.convertFormToJSON(requestApprovalCertificateFeesGeneration_Form[0]));
    jsonData['request_task_id'] = currentRequestTask;

    $.ajax({
      url: requestApprovalCertificateFeesGeneration_Form.attr('action'),
      type: "POST",
      dataType: 'json',
      data: jsonData,
      beforeSend: function () {
        resetModalInputErrors(requestApprovalCertificateFeesGeneration_Form);

        requestApprovalCertificateFeesGeneration_Modal.find('.request-spinner').removeClass('d-none');
        requestApprovalCertificateFeesGeneration_Modal.find('.actions-wrapper').addClass('d-none');
        requestApprovalCertificateFeesGeneration_Modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        requestApprovalCertificateFeesGeneration_Modal.find('.request-spinner').addClass('d-none');
        requestApprovalCertificateFeesGeneration_Modal.find('.actions-wrapper').removeClass('d-none');
        requestApprovalCertificateFeesGeneration_Modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          request_id: window.variables.messages.unable_to_perform_request,
        }, requestApprovalCertificateFeesGeneration_Modal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            requestApprovalCertificateFeesGeneration_Modal.modal('hide');
            setTimeout(() => {
              window.location.reload();
            }, 1000);

            if(result.data && result.data.doc_url) {
              window.open(result.data.doc_url, '_blank');
            }
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, requestApprovalCertificateFeesGeneration_Modal);
            break;
          case 160:
            lastRequestJsonData = jsonData;

            requestApprovalCertificateFeesGeneration_Modal.modal('hide');
            handleZeroBillingElementModal(result.message);
            break;
          default:
            Helpers.customInputErrorsValidation({
              request_id: result.message,
            }, requestApprovalCertificateFeesGeneration_Modal);
            break;
        }
      },
    });
  });

  $('#updateRequestReference_Form #btnConfirm').click(function (event) {
    event.preventDefault();

    let jsonData = JSON.parse(Helpers.convertFormToJSON(updateRequestReference_Form[0]));

    $.ajax({
      url: updateRequestReference_Form.attr('action'),
      type: "PUT",
      dataType: 'json',
      data: jsonData,
      beforeSend: function () {
        resetModalInputErrors(updateRequestReference_Form);

        updateRequestReference_Modal.find('.request-spinner').removeClass('d-none');
        updateRequestReference_Modal.find('.actions-wrapper').addClass('d-none');
        updateRequestReference_Modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        updateRequestReference_Modal.find('.request-spinner').addClass('d-none');
        updateRequestReference_Modal.find('.actions-wrapper').removeClass('d-none');
        updateRequestReference_Modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          reference: window.variables.messages.unable_to_perform_request,
        }, updateRequestReference_Modal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            updateRequestReference_Modal.modal('hide');
            setTimeout(() => {
              window.location.reload();
            }, 800);
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, updateRequestReference_Modal);
            break;
          default:
            Helpers.customInputErrorsValidation({
              reference: result.message,
            }, updateRequestReference_Modal);
            break;
        }
      },
    });
  });

  $('#updateRequestAttachment_Modal #btnEdit').click(function (event) {
    event.preventDefault();
    let formElement = updateRequestAttachment_Form;

    let formData = new FormData(formElement[0]);
    formData.append('request_id', formElement.attr('data-bs-request'));
    formData.append('comment_id', formElement.attr('data-bs-comment'));
    formData.append('attachment_id', formElement.attr('data-bs-attachment'));

    $.ajax({
      url: formElement.attr('action'),
      type: "POST",
      cache: false,
      contentType: false,
      processData: false,
      dataType : 'json',
      data: formData,
      beforeSend: function () {
        resetModalInputErrors(formElement);

        updateRequestAttachment_Modal.find('.request-spinner').removeClass('d-none');
        updateRequestAttachment_Modal.find('.actions-wrapper').addClass('d-none');
        updateRequestAttachment_Modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        updateRequestAttachment_Modal.find('.request-spinner').addClass('d-none');
        updateRequestAttachment_Modal.find('.actions-wrapper').removeClass('d-none');
        updateRequestAttachment_Modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          request_id: window.variables.messages.unable_to_perform_request
        }, updateRequestAttachment_Modal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            updateRequestAttachment_Modal.modal('hide');
            window.location.reload();
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, updateRequestAttachment_Modal);
            break;
          default:
            Helpers.customInputErrorsValidation({
              request_id: result.message
            }, updateRequestAttachment_Modal);
            break;
        }
      },
    });
  });

  // Invoice confirmation

  $('#confirmForBillingElementGeneration_Modal #btnConfirm').on('click', function(event) {
    event.preventDefault();

    if(lastRequestJsonData !== null) {
      lastRequestJsonData['request_confirmation'] = 1;

      $.ajax({
        url: actionForm.attr('action'),
        type: actionForm.attr('method'),
        dataType : 'json',
        data: lastRequestJsonData,
        beforeSend: function () {
          resetModalInputErrors(confirmForBillingElementGeneration_Form);

          confirmForBillingElementGeneration_Form.find('.request-spinner').removeClass('d-none');
          confirmForBillingElementGeneration_Form.find('.actions-wrapper').addClass('d-none');
          confirmForBillingElementGeneration_Form.find('button.btn-close').addClass('d-none');
        },
        complete: function () {
          confirmForBillingElementGeneration_Form.find('.request-spinner').addClass('d-none');
          confirmForBillingElementGeneration_Form.find('.actions-wrapper').removeClass('d-none');
          confirmForBillingElementGeneration_Form.find('button.btn-close').removeClass('d-none');
        },
        error: function () {
          Helpers.customInputErrorsValidation({
            error: window.variables.messages.unable_to_perform_request
          }, confirmForBillingElementGeneration_Form);
        },
        success: function (result) {
          switch (result.code) {
            case 100:
              lastRequestJsonData = null;

              confirmForBillingElementGeneration_Modal.modal('hide');
              Helpers.handleAlertVisibility(
                globalAlert,
                true,
                'alert-success',
                window.variables.messages.successful_operation
              );

              setTimeout(() => {
                window.location.reload();
              }, 1000);

              if(result.data && result.data.doc_url) {
                window.open(result.data.doc_url, '_blank');
              }
              break;
            case 150:
              Helpers.customInputErrorsValidation({
                error: result.data[Object.keys(result.data)[0]][0],
              }, confirmForBillingElementGeneration_Form);
              break;
            default:
              Helpers.customInputErrorsValidation({
                error: result.message,
              }, confirmForBillingElementGeneration_Form);
              break;
          }
        },
      });
    }
  });

  // Other actions

  $('#markRequestAsComplete_Form #btnConfirm').click(function (event) {
    event.preventDefault();

    let actionForm = markRequestAsComplete_Form;

    let jsonData = JSON.parse(Helpers.convertFormToJSON(actionForm[0]));
    actionForm['request_id'] = actionForm.attr('data-bs-requesttarget');

    $.ajax({
      url: actionForm.attr('action'),
      type: actionForm.attr('method'),
      dataType : 'json',
      data: jsonData,
      beforeSend: function () {
        resetModalInputErrors(actionForm);
        Helpers.handleAlertVisibility(globalAlert, false);

        markRequestAsComplete_Modal.find('.request-spinner').removeClass('d-none');
        markRequestAsComplete_Modal.find('.actions-wrapper').addClass('d-none');
        markRequestAsComplete_Modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        markRequestAsComplete_Modal.find('.request-spinner').addClass('d-none');
        markRequestAsComplete_Modal.find('.actions-wrapper').removeClass('d-none');
        markRequestAsComplete_Modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          request_id: window.variables.messages.unable_to_perform_request
        }, markRequestAsComplete_Modal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            Helpers.handleAlertVisibility(
              globalAlert,
              true,
              'alert-success',
              window.variables.messages.successful_operation
            );

            markRequestAsComplete_Modal.modal('hide');
            setTimeout(() => {
              window.location.reload();
            }, 1000);
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, markRequestAsComplete_Modal);
            break;
          default:
            Helpers.customInputErrorsValidation({
              request_id: result.message
            }, markRequestAsComplete_Modal);
            break;
        }
      },
    });
  });

  /** Utilities */

  function resetModalInputErrors(wrapper) {
    wrapper.find('.error-helper').addClass('d-none').html('');
    wrapper.find('input').removeClass('is-invalid').removeClass('border-danger');
    wrapper.find('.select2').removeClass('is-invalid').removeClass('border-danger');
  }

  function resetModalInputs(wrapperId) {
    if(wrapperId === 'approveCloseRequest_Form') {
      approveCloseRequest_Form.trigger('reset');
      initDates();
    }

    if(wrapperId === 'declineCloseRequest_Form') {
      declineCloseRequest_Form.trigger('reset');
      declineReasonEditor.setText('');
    }
  }

  function initDates() {
    let date = new Date();
    let now = new Date(date.getFullYear(), date.getMonth(), date.getDate());

    let issueDate = $('input#issueDate');
    if (issueDate.length > 0) {
      issueDate.flatpickr({
        monthSelectorType: 'static',
        defaultDate: now,
        maxDate: now
      });
    }

    let expirationDate = $('input#expirationDate');
    let dateInNYears = null;
    if(expirationDate.attr('data-bs-durationyear')) {
      let durationInYears = parseInt(expirationDate.attr('data-bs-durationyear'));

      if(durationInYears > 0) {
        dateInNYears = new Date(now);
        dateInNYears.setFullYear(dateInNYears.getFullYear() + durationInYears);
      }
    }

    if (expirationDate.length > 0) {
      expirationDate.flatpickr({
        monthSelectorType: 'static',
        minDate: now,
        defaultDate: dateInNYears,
      });
    }
  }

  function initEditors() {
    let selEditor = $('.declineReasonEditor');

    if(selEditor.length) {
      declineReasonEditor = new Quill(
        document.querySelector(".declineReasonEditor"),
        {
          modules: {toolbar: ".declineReasonToolbar"},
          placeholder: window.variables.messages.decline_placeholder + " ...",
          theme: "snow"
        }
      );
    }
  }

  function handleZeroBillingElementModal(message) {
    confirmForBillingElementGeneration_Modal.find('p.content-message').html(message);

    //  Show
    confirmForBillingElementGeneration_Modal.modal('show');
  }
});
