'use strict';

$(function () {
  let sendAttachmentModal = $('#sendAttachment_Modal');
  let sendAttachmentForm = $('#sendAttachment_Form');
  let addAttachmentCommentModal = $('#addAttachmentCommentModal');
  let addRequestAttachmentCommentForm = $('#addRequestAttachmentCommentForm');
  let addRequestAttachmentForm = $('#addRequestAttachmentForm');
  let addAttachmentModal = $('#addAttachmentModal');
  let attachmentTable = $('#attachmentTable'), dtAttachment;
  let body = $('body');
  let toastAnimation;
  let commentData = {};
  let fullEditor, commentFullEditor, sendAttachmentMessagesEditor;
  let requestRefreshCalculationSheetAttachment_Modal = $('#requestRefreshCalculationSheetAttachment_Modal');
  let requestRefreshCalculationSheetAttachment_Form = $('#requestRefreshCalculationSheetAttachment_Form');
  let toastWrapper = $('.toast-wrapper');
  let lastCalculationSheetTarget = null;

  // Init ...
  initEditors();
  initSelect2();
  intiStaticDataTable();
  toastAnimation = getToastInstance();

  // Events handler

  body.on('click', 'button.refresh-attachment-action', function (event) {
    event.preventDefault();

    let $this = $(this);
    lastCalculationSheetTarget = $this.attr('data-bs-attachment-target');

    requestRefreshCalculationSheetAttachment_Modal.find('span.replaceable').text(
      $this.attr('data-bs-designation')
    );
    requestRefreshCalculationSheetAttachment_Modal.modal('show');
  });

  $('#requestRefreshCalculationSheetAttachment_Modal #btnCancel, #requestRefreshCalculationSheetAttachment_Modal .btn-close').on('click', function(event) {
    event.preventDefault();

    resetModalInputErrors();
    // Hide modal
    requestRefreshCalculationSheetAttachment_Modal.modal('hide');
  });

  $('#addAttachmentCommentModal .btn-close, #addAttachmentCommentModal #btnCancel').click(function (event) {
    event.preventDefault();

    commentData = {};
    resetModalInputs('addRequestAttachmentCommentForm');
    resetModalInputErrors();
    addAttachmentCommentModal.modal('hide');
  });

  $('#addAttachmentModal .btn-close, #addAttachmentModal #btnCancel').click(function (event) {
    event.preventDefault();

    commentData = {};
    resetModalInputs('addRequestAttachmentForm');
    resetModalInputErrors();
    addAttachmentModal.modal('hide');
  });

  $('#sendAttachment_Modal .btn-close, #sendAttachment_Modal #btnCancel').click(function (event) {
    event.preventDefault();

    commentData = {};
    resetModalInputs('sendAttachmentForm');
    resetModalInputErrors();
    sendAttachmentModal.modal('hide');
  });

  $('.global_BtnAddAttachment').click(function (event) {
    event.preventDefault();
    commentData = {
      request: attachmentTable.attr('data-bs-request'),
    };
    addAttachmentModal.modal('show');
  });

  attachmentTable.on('click', '.add-comment', function(event) {
    event.preventDefault();
    let $this = $(this);
    let dynamicTitle = window.variables.messages.dynamic_comment_attachment;

    commentData = {
      request: $this.attr('data-bs-request'),
      attachment: $this.attr('data-bs-target'),
      row_target: $this.attr('data-bs-rowtarget'),
    };
    addAttachmentCommentModal.find('.modal-title').html(
      dynamicTitle.replace(/name_/gi, $this.attr('data-bs-attachment'))
    );
    addAttachmentCommentModal.modal('show');
  });

  attachmentTable.on('click', '.share-by-mail', function(event) {
    event.preventDefault();
    let $this = $(this);

    commentData = {
      request: $this.attr('data-bs-request'),
      attachment: $this.attr('data-bs-target'),
      row_target: $this.attr('data-bs-rowtarget'),
    };

    sendAttachmentModal.find('a.attachment-wrapper').attr('href', $this.attr('data-bs-url'));
    sendAttachmentModal.find('a.attachment-wrapper > span.attachment-designation').html($this.attr('data-bs-attachment'));
    sendAttachmentModal.modal('show');
  });

  requestRefreshCalculationSheetAttachment_Form.on('click', 'button#btnConfirm', function(e) {
    e.preventDefault();

    let actionForm = requestRefreshCalculationSheetAttachment_Form;

    if(lastCalculationSheetTarget !== null) {
      let jsonData = JSON.parse(Helpers.convertFormToJSON(actionForm[0]));
      jsonData['request_attachment_id'] = lastCalculationSheetTarget;

      $.ajax({
        url: actionForm.attr('action'),
        type: actionForm.attr('method'),
        dataType : 'json',
        data: jsonData,
        beforeSend: function () {
          resetModalInputErrors(actionForm);

          requestRefreshCalculationSheetAttachment_Modal.find('.request-spinner').removeClass('d-none');
          requestRefreshCalculationSheetAttachment_Modal.find('.actions-wrapper').addClass('d-none');
          requestRefreshCalculationSheetAttachment_Modal.find('button.btn-close').addClass('d-none');
        },
        complete: function () {
          requestRefreshCalculationSheetAttachment_Modal.find('.request-spinner').addClass('d-none');
          requestRefreshCalculationSheetAttachment_Modal.find('.actions-wrapper').removeClass('d-none');
          requestRefreshCalculationSheetAttachment_Modal.find('button.btn-close').removeClass('d-none');
        },
        error: function () {
          Helpers.customInputErrorsValidation({
            request_attachment_id: window.variables.messages.unable_to_perform_request
          }, requestRefreshCalculationSheetAttachment_Modal);
        },
        success: function (result) {
          switch(result.code){
            case 100:
              requestRefreshCalculationSheetAttachment_Modal.modal('hide');

              setTimeout(() => {
                window.location.reload();
              }, 1000);
              if(result.data && result.data.redirect_url) {
                window.open(result.data.redirect_url, '_blank');
              }
              break;
            case 150:
              Helpers.customInputErrorsValidation(result.data, requestRefreshCalculationSheetAttachment_Modal);
              break;
            default:
              Helpers.customInputErrorsValidation({
                request_attachment_id: result.message
              }, requestRefreshCalculationSheetAttachment_Modal);
              break;
          }
        },
      });
    }
  });

  $('#addAttachmentCommentModal #btnAdd').click(function (event) {
    event.preventDefault();

    let jsonData = JSON.parse(Helpers.convertFormToJSON(addRequestAttachmentCommentForm[0]));
    jsonData['comment'] = fullEditor.getText().trim().length > 0 ? fullEditor.root.innerHTML : '';
    jsonData['request_id'] = commentData['request'];
    jsonData['request_attachment_id'] = commentData['attachment'];

    $.ajax({
      url: addRequestAttachmentCommentForm.attr('action'),
      type: "POST",
      dataType : 'json',
      data: jsonData,
      beforeSend: function () {
        resetModalInputErrors();
        addAttachmentCommentModal.find('.request-spinner').removeClass('d-none');
        addAttachmentCommentModal.find('.actions-wrapper').addClass('d-none');
        addAttachmentCommentModal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        addAttachmentCommentModal.find('.request-spinner').addClass('d-none');
        addAttachmentCommentModal.find('.actions-wrapper').removeClass('d-none');
        addAttachmentCommentModal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          comment: window.variables.messages.unable_to_add_request_attachment_comment
        }, addAttachmentCommentModal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            if(result.data.require_action === 0) {
              // Update comment row
              let transMessage = window.variables.messages.plural_observation;
              transMessage = transMessage.replace(/count_/gi, result.data.comment_count).replace(/value_/gi, result.data.comment_count)
              $('#' + commentData.row_target).find('td.comment-cell').empty().html('' +
                '<p class="mb-0 fs-6 text-body">'+ transMessage +'</p>'
              );

              resetModalInputs('addRequestAttachmentCommentForm');
              commentData = {};

              // Hide and show toast
              addAttachmentCommentModal.modal('hide');
              Helpers.renderToastInfo(toastWrapper, result.message);
              toastAnimation.show();
            } else {
              addAttachmentCommentModal.modal('hide');
              location.reload();
            }
            break;
          case 150:
            Helpers.customInputErrorsValidation({
              comment: result.data[Object.keys(result.data)[0]][0]
            }, addAttachmentCommentModal);
            break;
          default:
            Helpers.customInputErrorsValidation({
              comment: result.message,
            }, addAttachmentCommentModal);
            break;
        }
      },
    });
  });

  $('#addAttachmentModal #btnAdd').click(function (event) {
    event.preventDefault();

    let formData = new FormData(addRequestAttachmentForm[0]);
    formData.set('attachment_comment', commentFullEditor.getText().trim().length > 0 ? commentFullEditor.root.innerHTML : '');
    formData.set('request_id', commentData['request'].trim());

    $.ajax({
      url: addRequestAttachmentForm.attr('action'),
      type: "POST",
      cache: false,
      contentType: false,
      processData: false,
      dataType : 'json',
      data: formData,
      beforeSend: function () {
        resetModalInputErrors();
        addAttachmentModal.find('.request-spinner').removeClass('d-none');
        addAttachmentModal.find('.actions-wrapper').addClass('d-none');
        addAttachmentModal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        addAttachmentModal.find('.request-spinner').addClass('d-none');
        addAttachmentModal.find('.actions-wrapper').removeClass('d-none');
        addAttachmentModal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          attachment: window.variables.messages.unable_to_upload_attachment
        }, addAttachmentModal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            if(result.data.required_action === 0) {
              // Update data table
              addAttachmentTableRow(result.data);

              resetModalInputs('addRequestAttachmentForm');
              commentData = {};

              // Hide and show toast
              addAttachmentModal.modal('hide');
              Helpers.renderToastInfo(toastWrapper, result.message);
              toastAnimation.show();
            } else {
              addAttachmentModal.modal('hide');
              location.reload();
            }
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, addAttachmentModal);
            break;
          default:
            Helpers.customInputErrorsValidation({
              attachment: result.message,
            }, addAttachmentModal);
            break;
        }
      },
    });
  });

  attachmentTable.on('click', 'button.btn-display-attachment', function(event) {
    event.preventDefault();
    let $this = $(this)

    let url = `https://docs.google.com/viewer?url=${$this.attr('data-bs-src')}&embedded=true`;
    $('#offCanvasDocPreview').find('iframe').attr('src', url);
  });

  $('#sendAttachment_Modal #btnSend').click(function (event) {
    event.preventDefault();

    let jsonData = JSON.parse(Helpers.convertFormToJSON(
      sendAttachmentForm[0])
    );
    jsonData['message'] = sendAttachmentMessagesEditor.getText().trim().length > 0 ? sendAttachmentMessagesEditor.root.innerHTML : '';
    jsonData['request_attachment_id'] = commentData['attachment'];

    $.ajax({
      url: sendAttachmentForm.attr('action'),
      type: "POST",
      dataType : 'json',
      data: jsonData,
      beforeSend: function () {
        resetModalInputErrors();
        sendAttachmentModal.find('.request-spinner').removeClass('d-none');
        sendAttachmentModal.find('.actions-wrapper').addClass('d-none');
        sendAttachmentModal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        sendAttachmentModal.find('.request-spinner').addClass('d-none');
        sendAttachmentModal.find('.actions-wrapper').removeClass('d-none');
        sendAttachmentModal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          request_attachment_id: window.variables.messages.unable_to_perform_request
        }, sendAttachmentModal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            Helpers.renderToastInfo(toastWrapper, result.message);
            resetModalInputs("sendAttachmentForm");

            sendAttachmentModal.modal('hide');
            toastAnimation.show();
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, sendAttachmentModal);
            break;
          default:
            Helpers.customInputErrorsValidation({
              request_attachment_id: result.message
            }, sendAttachmentModal);
            break;
        }
      },
    });
  });

  // Functions

  function initEditors() {
    commentFullEditor = new Quill(
      document.querySelector(".atCommentEditor"),
      {
        modules: {toolbar: ".atCommentToolbar"},
        placeholder: window.variables.messages.type_observation + " ...",
        theme: "snow"
      }
    );

    fullEditor = new Quill(
      document.querySelector(".atcCommentEditor"),
      {
        modules: {toolbar: ".atcCommentToolbar"},
        placeholder: window.variables.messages.type_observation + " ...",
        theme: "snow"
      }
    );

    sendAttachmentMessagesEditor = new Quill(
      document.querySelector(".sendAttachmentMessagesEditor"),
      {
        modules: {toolbar: ".sendAttachmentMessagesToolbar"},
        placeholder: window.variables.messages.you_can_add_optional_message + " ...",
        theme: "snow"
      }
    );
  }

  function getToastInstance() {
    return new bootstrap.Toast(toastWrapper, {
      autohide: true
    });
  }

  function resetModalInputErrors(wrapper = null) {
    if(wrapper !== null) {
      wrapper.find('.error-helper').addClass('d-none').html('');
      wrapper.find('input').removeClass('is-invalid').removeClass('border-danger');
      wrapper.find('.select2').removeClass('is-invalid').removeClass('border-danger');
    } else {
      $('.error-helper').addClass('d-none').html('');
      $('input').removeClass('is-invalid').removeClass('border-danger');
      $('.select2').removeClass('is-invalid').removeClass('border-danger');
    }
  }

  function resetModalInputs(formId) {
    if(formId === 'addRequestAttachmentCommentForm') {
      addRequestAttachmentCommentForm.trigger('reset');
      fullEditor.setText('');
    }

    if(formId === 'addRequestAttachmentForm') {
      addRequestAttachmentForm.trigger('reset');
      commentFullEditor.setText('');
      addAttachmentModal.find('.modal-dialog').find('select.select2').val('').trigger('change');
    }

    if(formId === 'sendAttachmentForm') {
      sendAttachmentForm.trigger('reset');
      sendAttachmentMessagesEditor.setText('');
    }
  }

  function intiStaticDataTable() {
    if (attachmentTable.length) {
      dtAttachment = attachmentTable.DataTable(basicDataTableOptions());
    }
  }

  function basicDataTableOptions() {
    return {
      paging: true,
      ordering: false,
      info: true,
      displayLength: 25,
      searching: true,
      lengthChange: true,
      orderCellsTop: false,
      order: [[1, "asc"]],
      language: window.variables.messages.languages,
      columnDefs: [
        {
          targets: '_all',
          orderable: false,
        },
      ],
      initComplete: function (settings, json) {
        $('.dataTables_filter .form-control').removeClass('form-control-sm').attr('placeholder', window.variables.messages.search + " ...");
        $('.dataTables_length .form-select').removeClass('form-select-sm');
      }
    };
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
          dropdownParent: $this.parent()
        });
      });
    }
  }

  function addAttachmentTableRow(data) {
    let row = $('<tr class=""></tr>').attr('id', 'attachmentRow_' + data.attachment.id);
    row.append($('<td />').html(`<p class="mb-2 ps-1 text-wrap fs-6">#${attachmentTable.find('tbody tr').length + 1}</p>`));

    row.append(
      $('<td />')
        .attr('class', 'w-px-300 text-wrap')
        .html(`
            <p class="m-0">
              ${data.attachment_type.designation}
              <span class="badge ${data.attachment_type.extension_bg} d-inline-block px-1 text-uppercase ms-1">
                ${data.attachment_type.extension}
              </span>
            </p>
          `)
    );

    row.append(
      $('<td />')
        .attr('class', 'text-center')
        .html('<span class="badge badge-center w-px-20 h-px-20 rounded-pill bg-label-success"><i class="bx bx-check bx-xs"></i></span>')
    );

    let staffPostWrapper = null;
    if(data.user_author.staff_post !== null) {
      staffPostWrapper = '<span class="badge bg-label-secondary d-inline-block px-1 text-uppercase ms-1">'+ data.user_author.staff_post +'</span>';
    }
    row.append(
      $('<td />')
        .attr('class', 'text-center')
        .html(`<p class="m-0">${data.user_author.small_name}</p>${staffPostWrapper ?? ''}`)
    );

    let commentValue = window.variables.messages.plural_observation.replace(/value_/gi, data.attachment.comment_count);
    row.append(
      $('<td />')
        .attr('class', 'text-center comment-cell')
        .html(`<p class="m-0">${commentValue}</p>`)
    );

    let detailsRoute = window.variables.routes.attachment_details.replace(/_id/gi, data.attachment.id);
    row.append(
      $('<td />')
        .attr('class', 'text-end')
        .html(`
              <div class="btn-group dropstart zindex-5">
                <a href="${data.attachment.url}" class="btn btn-label-secondary btn-sm me-2 btn-display" target="blank_">
                  ${window.variables.messages.display}
                </a>
                <button type="button" class="btn btn-icon btn-label-secondary btn-sm" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="tf-icons bx bx-dots-vertical-rounded"></i>
                </button>
                <ul class="dropdown-menu">
                  <li>
                    <a class="dropdown-item show-comment-option" href="${detailsRoute}" data-bs-attachment="${data.attachment.id}" target="_blank">
                    ${window.variables.messages.show_details}
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item add-comment" href="javascript:void(0);" data-bs-attachment="${data.attachment_type.designation}" data-bs-target="${data.attachment.id}" data-bs-request="${data.request_id}" data-bs-rowtarget="${'attachmentRow_' + data.attachment.id}">
                     ${window.variables.messages.add_comment}
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item text-danger" href="javascript:void(0);">
                   ${window.variables.messages.delete}
                    </a>
                  </li>
                </ul>
              </div>
            `)
    );

    let emptyIndex = $('tr#emptyElement_2').index();
    if(emptyIndex >= 0) {
      dtAttachment.row(emptyIndex).remove();
    }
    dtAttachment.rows.add(row);
    dtAttachment.draw(false)
  }
});
