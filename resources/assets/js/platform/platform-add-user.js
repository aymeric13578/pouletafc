'use strict';

$(function () {
  let addUserForm = $('#addUserForm');
  let globalAlert = $('#globalAlert');
  let staffService = $('#staffServiceId');
  let unitManagement = $('#unitManagementId');
  let staffServiceCatalogData = staffService.find("option");
  let btnAddUser = $('#btnAddUser');
  let select2 = $('.select2');
  // let aboutEmployeeWrapper = $('.about-employee-wrapper');

  $("#phoneGroup").phoneNumber(window.variables.routes.raw_countries, {
    messages: window.variables.messages.phone_number_messages,
  });

  filterStaffServiceSelect(unitManagement.val());

  // $('input[type=radio][name=user_type]').change(function() {
  //   switch(this.value) {
  //     case 'employee':
  //       aboutEmployeeWrapper.collapse('show');
  //       break;
  //     default:
  //       aboutEmployeeWrapper.collapse('hide');
  //       break;
  //   }
  // });

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

  // Update/reset user image of account page
  let accountUserImage = document.getElementById('uploadedAvatar');
  const fileInput = document.querySelector('.account-file-input');
  const resetFileInput = document.querySelector('.account-image-reset');
  const decorationIcon = document.querySelector('.decoration-icon');

  if (accountUserImage) {
    fileInput.onchange = () => {
      if (fileInput.files[0]) {
        accountUserImage.src = window.URL.createObjectURL(fileInput.files[0]);
        accountUserImage.classList.remove('d-none');
        decorationIcon.classList.add('d-none');
      }
    };
    resetFileInput.onclick = () => {
      resetInputFile();
    };
  }

  // Other ...
  function filterStaffServiceSelect(value) {
    staffService.find('option').remove();

    staffServiceCatalogData.each(function(index, el) {
      if($(el).attr('value').length === 0 || (value && $(el).attr('data-bs-unit') === value)) {
        $(el).prop('selected', false);
        staffService.append($(el));
      }
    });
    staffService.trigger('change');
  }

  function resetInputFile() {
    const resetImage = accountUserImage.src;

    fileInput.value = '';
    accountUserImage.src = resetImage;
    accountUserImage.classList.add('d-none');
    decorationIcon.classList.remove('d-none');
  }

  function resetInputErrors() {
    $('.error-helper').addClass('d-none').html('');
    $('.image-wrapper').removeClass('border-danger');

    $('select.is-invalid, div.is-invalid, input.is-invalid, span.is-invalid, textarea.is-invalid').removeClass('is-invalid');
    $('select.border-danger, div.border-danger, input.border-danger, span.border-danger, textarea.border-danger').removeClass('border-danger');
  }

  function setInputErrors(errors) {
    Object.keys(errors).forEach(function(k) {
      let errorVal = errors[k];
      $('#' + k + 'ErrorHelper').removeClass('d-none').html(errorVal);
      $('.' + k).addClass('is-invalid border-danger');

      if(k === 'avatar') {
        $('.image-wrapper').addClass('border-danger');
      }
    });
  }

  function resetForm() {
    addUserForm.trigger("reset");
    select2.val('');
    select2.trigger('change');
    filterStaffServiceSelect(unitManagement.val());
    // aboutEmployeeWrapper.collapse('show');
    resetInputFile();
    resetInputErrors();
  }

  // Handle form ....
  $('#btnCancelForm').on('click', function(event) {
    event.preventDefault();
    resetForm();
  });

  btnAddUser.on('click', function(event) {
    event.preventDefault();

    let formData = new FormData(addUserForm[0]);
    let telephoneValue = $('#telephoneId').attr("data-bs-value");
    if(telephoneValue !== undefined && telephoneValue !== null) {
      formData.set('telephone', telephoneValue.trim());
    }

    $.ajax({
      url: addUserForm.attr('action'),
      type: "POST",
      cache: false,
      contentType: false,
      processData: false,
      dataType : 'json',
      data: formData,
      beforeSend: function () {
        resetInputErrors();
        Helpers.handleAlertVisibility(globalAlert, false);
        btnAddUser.prop('disabled', true);
        btnAddUser.find('.default-content').addClass('d-none');
        btnAddUser.find('.default-progress').removeClass('d-none');
      },
      complete: function () {
        btnAddUser.prop('disabled', false);
        btnAddUser.find('.default-content').removeClass('d-none');
        btnAddUser.find('.default-progress').addClass('d-none');
      },
      error: function () {
        Helpers.handleAlertVisibility(
          globalAlert,
          true,
          'alert-danger',
          window.variables.messages.unable_to_save_user
        );
      },
      success: function (result) {
        switch(result.code) {
          case 100:
            Helpers.handleAlertVisibility(globalAlert, true, 'alert-success', result.data.message);
            resetForm();
            break;
          case 150:
            setInputErrors(result.data);
            break;
          default:
            Helpers.handleAlertVisibility(globalAlert, true, 'alert-danger', result.message);
            break;
        }
      }
    });
  });

  unitManagement.on('change', function (event) {
    event.preventDefault();
    filterStaffServiceSelect($(this).val());
  });

  $('#globalAlert .btn-close').click(function (event) {
    event.preventDefault();
    Helpers.handleAlertVisibility(globalAlert, false);
  });
});
