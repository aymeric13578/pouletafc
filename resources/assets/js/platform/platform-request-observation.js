'use strict';

$(function () {
  let commentFullEditor = $('#commentFullEditor');
  let attachmentTypesWrapper = $('#attachmentTypesWrapper');
  let filterDate = document.querySelector('.due-date');
  let addCommentForm = $('#addCommentForm');
  let formFilter = $('#formFilter');
  let tagifyAttachmentTypeWrapper = $('#tagifyAttachmentTypeWrapper');
  let radioCommentOption = $('input[type="radio"][name="comment_option"]');
  let applicantFeedback = $('#applicantFeedback');
  let commentWrapper = $('.comment-wrapper');
  let commentsTable = $('#commentsTable'), dtComment;
  let commentOption = "0";
  let date = new Date();

  initEditors();
  initSelect2();
  initComponents();
  initStaticDataTable();

  // Event handler

  $('#btnAddComment').click(function (event) {
    event.preventDefault();

    let jsonData = JSON.parse(Helpers.convertFormToJSON(addCommentForm[0]));
    jsonData['comment'] = commentFullEditor.getText().trim().length ? commentFullEditor.root.innerHTML : null;
    jsonData['request_id'] = addCommentForm.attr('data-bs-request');
    jsonData['attachment_type_ids'] = Helpers.getMultipleSelectValues(tagifyAttachmentTypeWrapper);

    $.ajax({
      url: addCommentForm.attr('action'),
      type: "POST",
      dataType : 'json',
      data: jsonData,
      beforeSend: function () {
        resetInputsErrors();
        addCommentForm.find('.request-spinner').removeClass('d-none');
        addCommentForm.find('.actions-wrapper').addClass('d-none');
        addCommentForm.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        addCommentForm.find('.request-spinner').addClass('d-none');
        addCommentForm.find('.actions-wrapper').removeClass('d-none');
        addCommentForm.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          comment: window.variables.messages.unable_to_add_request_comment
        }, addCommentForm);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            location.href = result.data.redirection_url;
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, addCommentForm);
            break;
          default:
            Helpers.customInputErrorsValidation({
              comment: result.message,
            }, addCommentForm);
            break;
        }
      },
    });
  });

  $('#btnAddCancelForComment').click(function (event) {
    event.preventDefault();
    resetInputs();
    resetInputsErrors();
  });

  formFilter.submit(function (event) {
    let query = window.variables.routes.query;
    let type = window.variables.routes.type;

    if(formFilter.find('input[name="fd"]').val().length > 0) {
      formFilter.append('<input type="hidden" name="q" value="'+ query +'" />');
      formFilter.append('<input type="hidden" name="type" value="'+ type +'" />');

      return true;
    }
    return false;
  });

  radioCommentOption.change(function() {
    resetInputsErrors();
    commentOption = this.value;

    if (commentOption === '0') {
      handleTagifyWrapper(true);
      attachmentTypesWrapper.collapse('hide');
      attachmentTypesWrapper.find('select.select2').val('').trigger('change');
    } else {
      handleTagifyWrapper(false);
      attachmentTypesWrapper.collapse('show');
    }
  });

  applicantFeedback.on('change', function(event) {
    event.preventDefault();
    // let $this = $(this);
    // handleTagifyWrapper($this.is(':checked') && commentOption === "0");
  });

  // Functions

  function initEditors() {
    commentFullEditor = new Quill(
      document.querySelector(".stnpCommentEditor"),
      {
        modules: {toolbar: ".stnpCommentToolbar"},
        placeholder: window.variables.messages.type_observation + " ...",
        theme: "snow"
      }
    );
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

  function initComponents() {
    if (filterDate) {
      filterDate.flatpickr({
        monthSelectorType: 'static',
        maxDate: new Date(date.getFullYear(), date.getMonth(), date.getDate())
      });
    }
  }

  function resetInputs() {
    addCommentForm.trigger('reset');

    commentFullEditor.setText('');
    attachmentTypesWrapper.find('select.select2').val('').trigger('change');
    addCommentForm.find('.select2').val('').trigger('change');

    attachmentTypesWrapper.collapse('hide');
    tagifyAttachmentTypeWrapper.collapse('hide');
  }

  function resetInputsErrors() {
    $('.error-helper').addClass('d-none').html('');
    $('input').removeClass('is-invalid').removeClass('border-danger');
    $('.select2').removeClass('is-invalid').removeClass('border-danger');
  }

  function handleTagifyWrapper(state) {
    if(! state) {
      addCommentForm.find('.select2').val('').trigger('change');
    }
    tagifyAttachmentTypeWrapper.collapse(state ? 'show' : 'hide');
  }

  // Utilities

  function initStaticDataTable() {
    if (commentsTable.length) {
      dtComment = commentsTable.DataTable(basicDataTableOptions(commentWrapper));
    }
  }

  function basicDataTableOptions(el) {
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

        el.removeClass('d-none');
      }
    };
  }
});
