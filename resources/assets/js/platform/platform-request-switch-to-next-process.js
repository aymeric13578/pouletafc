'use strict';

$(function () {
  let btnSwitchToNextProcess = $('.btn_SwitchToNextProcess');
  let switchToNextProcess_Modal = $('#switchToNextProcess_Modal');
  let instructionsTextWrapper = $('#instructionsTextWrapper');
  let staffServiceSelect = $('#staffService');
  let usersSelect = $('#users');
  let managementUnitSelect = $('#managementUnit');
  let availableProcessingSelect = $('#availableProcessing');
  let switchToNextProcessForm = $('#switchToNextProcessForm');
  let commentRequestEditor;

  /** Init */
  initEditors();

  let allStaffServices = staffServiceSelect.find('option');
  let allUsers = usersSelect.find('option');
  let allProcess = availableProcessingSelect.find('option');

  let managementUnitSelectVal =  managementUnitSelect.val();
  let staffServiceSelectVal = staffServiceSelect.val();
  filterSelect(
    staffServiceSelect,
    managementUnitSelectVal,
    allStaffServices,
    'data-bs-unittarget',
    // staffServiceSelectVal
  );
  filterSelect(usersSelect, staffServiceSelectVal, allUsers, 'data-bs-servicetarget');
  filterProcessSelect(staffServiceSelectVal, managementUnitSelectVal);

  /** Handler events */

  btnSwitchToNextProcess.on('click', function(event) {
    event.preventDefault();

    // Show modal
    switchToNextProcess_Modal.modal('show');
  });

  $('select.user_id').on('change', function(event) {
    event.preventDefault();
    if($(this).val().length > 0) {
      instructionsTextWrapper.collapse('show');
    } else {
      instructionsTextWrapper.collapse('hide');
    }
  });

  managementUnitSelect.on('change', function(event) {
    event.preventDefault();
    filterSelect(staffServiceSelect, managementUnitSelect.val(), allStaffServices, 'data-bs-unittarget');
  });

  staffServiceSelect.on('change', function(event) {
    event.preventDefault();
    filterSelect(usersSelect, staffServiceSelect.val(), allUsers, 'data-bs-servicetarget');
    // Other filters
    filterProcessSelect(staffServiceSelect.val(), managementUnitSelect.val());
  });

  availableProcessingSelect.on('change', function(event) {
    event.preventDefault();
  });

  $('#switchToNextProcess_Modal #btnCancel, #switchToNextProcess_Modal .btn-close').on('click', function(event) {
    event.preventDefault();

    resetModalInputErrors();
    resetModalInputs();

    // Hide modal
    switchToNextProcess_Modal.modal('hide');
  });

  switchToNextProcessForm.on('click', '#btnSwitch', function(event) {
    event.preventDefault();

    let jsonData = JSON.parse(Helpers.convertFormToJSON(switchToNextProcessForm[0]));
    jsonData['instructions'] = commentRequestEditor.getText().trim().length > 0 ? commentRequestEditor.root.innerHTML : '';
    jsonData['request_id'] = switchToNextProcessForm.attr('data-bs-request');

    $.ajax({
      url: switchToNextProcessForm.attr('action'),
      type: "POST",
      dataType : 'json',
      data: jsonData,
      beforeSend: function () {
        resetModalInputErrors();
        switchToNextProcess_Modal.find('.request-spinner').removeClass('d-none');
        switchToNextProcess_Modal.find('.actions-wrapper').addClass('d-none');
        switchToNextProcess_Modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        switchToNextProcess_Modal.find('.request-spinner').addClass('d-none');
        switchToNextProcess_Modal.find('.actions-wrapper').removeClass('d-none');
        switchToNextProcess_Modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          error: window.variables.messages.unable_to_switch_to_next_process
        }, switchToNextProcess_Modal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            resetModalInputErrors();
            resetModalInputs();
            switchToNextProcess_Modal.modal('hide');

            location.href = result.data.redirection_url;
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, switchToNextProcess_Modal);
            break;
          default:
            Helpers.customInputErrorsValidation({
              error: result.message
            }, switchToNextProcess_Modal);
            break;
        }
      },
    });
  });

  /** Functions */

  function initEditors() {
    commentRequestEditor = new Quill(
      document.querySelector(".stnpCommentEditor"),
      {
        modules: {toolbar: ".stnpCommentToolbar"},
        placeholder: window.variables.messages.indicate_taf + " ...",
        theme: "snow"
      }
    );
  }

  function resetModalInputs() {
    switchToNextProcessForm.trigger('reset');
    switchToNextProcess_Modal.find('select.select2').val('').trigger('change');
  }

  function resetModalInputErrors() {
    switchToNextProcessForm.find('.error-helper').addClass('d-none').html('');
    switchToNextProcessForm.find('textarea').removeClass('is-invalid').removeClass('border-danger');
    switchToNextProcessForm.find('.select2').removeClass('is-invalid').removeClass('border-danger');
  }

  function filterSelect(selectEl, value, catalog, attr, defaultValue) {
    selectEl.find('option').remove();

    catalog.each(function(index) {
      if($(this).attr('value').length === 0 || (value && attr && $(this).attr(attr) === value)) {
        $(this).prop('selected', $(this).attr('value') === defaultValue);
        selectEl.append($(this));
      }
    });
    selectEl.trigger('change');
  }

  function filterProcessSelect(serviceVal, unitVal) {
    availableProcessingSelect.find('option').remove();
    let callOptions = [];

    allProcess.each(function(index) {
      if($(this).attr('value').length === 0 || (serviceVal && unitVal && $(this).attr('data-bs-services').split(',').indexOf(serviceVal) >= 0 && $(this).attr('data-bs-units').split(',').indexOf(unitVal) >= 0)) {
        $(this).prop('selected', false);
        availableProcessingSelect.append($(this));
        callOptions.push($(this));
      }
    });

    if(callOptions.length === 2) {
      callOptions[1].prop('selected', true);
    }
    availableProcessingSelect.trigger('change');
  }
});
