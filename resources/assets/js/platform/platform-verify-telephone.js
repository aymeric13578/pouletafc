'use strict';

$(function() {
  let verifyTelephoneForm = $("#verifyTelephoneForm");
  let btnVerifyTelephone = $("#btnVerifyTelephone");
  let alertWrapper = $('.alert-wrapper');

  $("#phoneGroup").phoneNumber(
    window.variables.routes.raw_countries, {
      messages: window.variables.messages.phone_number_messages,
    },
  );

  verifyTelephoneForm.submit(function (_) {
    alertWrapper.addClass('d-none');

    let emptyInputs = [];
    $(".form-control").each(function(index) {
      let element = $(this);
      if (element.val().length === 0) {
        element.focus();
        emptyInputs[index] = element;

        return false;
      }
    });

    if(emptyInputs.length === 0) {
      let telephone = '';
      let telephoneInput = verifyTelephoneForm.find("input[name=telephone]");
      let _telephoneInput = verifyTelephoneForm.find("input[name=_telephone]");
      telephone = _telephoneInput.attr('data-bs-value').trim();

      if(telephone.length) {
        telephoneInput.val(telephone);

        return true;
      }
    }

    return false;
  });
});
