'use strict';

$(function() {
  let resetPasswordForm = $("#resetPasswordForm");
  let alertWrapper = $('.alert-wrapper');

  resetPasswordForm.submit(function (_) {
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

    return emptyInputs.length === 0;
  });
});
