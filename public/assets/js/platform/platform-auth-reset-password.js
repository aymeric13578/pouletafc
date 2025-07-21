'use strict';

(function() {
  let formControl = $(".form-control");

  $('#btnChangePassword').click(handleChangePasswordAction);

  function handleChangePasswordAction(event) {
    event.preventDefault();
    let emptyInputs = [];

    $('.alert-wrapper').addClass('d-none');

    formControl.each(function(index) {
      let element = $(this);
      if (element.val().length === 0) {
        element.focus();
        emptyInputs[index] = element;

        return false;
      }
    });

    if (emptyInputs.length === 0) {
      formControl.removeClass('is-invalid');
      $('.invalid-feedback').removeClass('d-block');

      // Send ...
      $("#changeAuthPasswordForm").submit();
    }
  }
})();
