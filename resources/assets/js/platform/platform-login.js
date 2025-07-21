'use strict';

(function() {
  let formAuthentication = $("#formAuthentication");
  let alertWrapper = $('.alert-wrapper');

  $("#phoneGroup").phoneNumber(
    window.variables.routes.raw_countries, {
      messages: window.variables.messages.phone_number_messages,
    },
  );

  formAuthentication.submit(function (_) {
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
      let username = '';
      let usernameInput = formAuthentication.find("input[name=username]");

      if(window.variables.vars.admin_login === 1) {
        username = usernameInput.val();
      } else {
        let _usernameInput = formAuthentication.find("input[name=_username]");
        username = _usernameInput.attr('data-bs-value').trim();
      }

      if(username.length) {
        usernameInput.val(username);

        return true;
      }
    }

    return false;
  });
})();
