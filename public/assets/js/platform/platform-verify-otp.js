'use strict';

$(function() {
  let resendResetPasswordOtpLink = $("#resendResetPasswordOtpLink");
  let verifyOtpForTelephoneForm = $("#verifyOtpForTelephoneForm");
  let alertWrapper = $('.alert-wrapper');

  resendResetPasswordOtpLink.click(function (event) {
    event.preventDefault();
    console.dir('555555');

    alertWrapper.addClass('d-none');
    $('#resendResetPasswordOtpForm').submit();
  });

  verifyOtpForTelephoneForm.submit(function (_) {
    alertWrapper.addClass('d-none');

    let emptyInputs = [];
    let sampleCodes = [];
    $(".auth-input").each(function(index) {
      let element = $(this);
      sampleCodes[index] = element.val();

      if (element.val().length === 0) {
        element.focus();
        emptyInputs[index] = element;

        return false;
      }
    });

    if(emptyInputs.length === 0) {
      let otpInput = verifyOtpForTelephoneForm.find("input[name=otp_code]");
      otpInput.val(sampleCodes.join(''));

      return true;
    }

    return false;
  });
});
