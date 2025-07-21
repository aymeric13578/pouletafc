/**
 *  Page auth register multi-steps
 */

'use strict';

// Select2 (jquery)
$(function () {
  var select2 = $('.select2');

  // select2
  if (select2.length) {
    select2.each(function () {
      var $this = $(this);
      $this.wrap('<div class="position-relative"></div>');
      $this.select2({
        placeholder: 'Select an country',
        dropdownParent: $this.parent()
      });
    });
  }

  $("#phoneGroup").phoneNumber(
    window.variables.routes.raw_countries,
    {
      messages: window.variables.messages.phone_number_messages,
    },
  );
});


// Multi Steps Validation
// --------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function (e) {
  (function () {
    $("#loaderSpn").hide();
    const stepsValidation = document.querySelector('#multiStepsValidation');
    if (typeof stepsValidation !== undefined && stepsValidation !== null) {
      // Multi Steps form
      const stepsValidationForm = stepsValidation.querySelector('#multiStepsForm');
      // Form steps
      const stepsValidationFormStep1 = stepsValidationForm.querySelector('#personalInfoValidation');
      const stepsValidationFormStep2 = stepsValidationForm.querySelector('#telephoneValidation');
      const stepsValidationFormStep3 = stepsValidationForm.querySelector('#accountDetailsValidation');
      // Multi steps next prev button
      const stepsValidationNext = [].slice.call(stepsValidationForm.querySelectorAll('.btn-next'));
      const stepsValidationPrev = [].slice.call(stepsValidationForm.querySelectorAll('.btn-prev'));

      const multiStepsExDate = document.querySelector('.multi-steps-exp-date'),
        multiStepsCvv = document.querySelector('.multi-steps-cvv'),
        multiStepsMobile = document.querySelector('.multi-steps-mobile'),
        multiStepsPincode = document.querySelector('.multi-steps-pincode'),
        multiStepsCard = document.querySelector('.multi-steps-card');

      // Expiry Date Mask
      if (multiStepsExDate) {
        new Cleave(multiStepsExDate, {
          date: true,
          delimiter: '/',
          datePattern: ['m', 'y']
        });
      }

      // CVV
      if (multiStepsCvv) {
        new Cleave(multiStepsCvv, {
          numeral: true,
          numeralPositiveOnly: true
        });
      }

      // Mobile
      if (multiStepsMobile) {
        new Cleave(multiStepsMobile, {
          phone: true,
          phoneRegionCode: 'CMR'
        });
      }

      // Pincode
      /*if (multiStepsPincode) {
        new Cleave(multiStepsPincode, {
          delimiter: '',
          numeral: true
        });
      }*/

      // Credit Card
      if (multiStepsCard) {
        new Cleave(multiStepsCard, {
          creditCard: true,
          onCreditCardTypeChanged: function (type) {
            if (type != '' && type != 'unknown') {
              document.querySelector('.card-type').innerHTML =
                '<img src="' + assetsPath + 'img/icons/payments/' + type + '-cc.png" height="28"/>';
            } else {
              document.querySelector('.card-type').innerHTML = '';
            }
          }
        });
      }

      let validationStepper = new Stepper(stepsValidation, {
        linear: true
      });


      // Personal info
      const multiSteps1 = FormValidation.formValidation(stepsValidationFormStep1, {
        fields: {
        },
        plugins: {
          trigger: new FormValidation.plugins.Trigger(),
          bootstrap5: new FormValidation.plugins.Bootstrap5({
            // Use this for enabling/changing valid/invalid class
            // eleInvalidClass: '',
            eleValidClass: '',
            rowSelector: function (field, ele) {
              // field is the field name
              // ele is the field element
              switch (field) {
                case 'multiStepsFirstName':
                  return '.col-sm-6';
                case 'multiStepsAddress':
                  return '.col-md-12';
                default:
                  return '.row';
              }
            }
          }),
          autoFocus: new FormValidation.plugins.AutoFocus(),
          submitButton: new FormValidation.plugins.SubmitButton()
        }
      }).on('core.form.valid', function () {

        //Validate data
        var url = "";
        var telephone = "";
        if ($("#telephone").val() != "") {
          //telephone = '+237' + $("#telephone").val();
          telephone = $("#telephone").attr("data-bs-value").trim();
        }
        var token;
        var formData = {
          //_token : $("#token").val(),
          telephone: telephone,
          email: $("#multiStepsEmail").val(),
          first_name: $("#multiStepsFirstName").val(),
          last_name: $("#multiStepsLastName").val(),
          post_box: $("#multiStepsPincode").val(),
          address: $("#multiStepsAddress").val(),
          unique_id_number: $("#multiStepsUniqueIdNumber").val()
        };

        url = $("#registerStep1").attr("action1");

        $.ajax({
          url: url,
          data: formData,
          type: "POST",
          dataType: "json"
        })
          .done(function (response) {
            if (response.code === 100) {
              //Confirm telephone number
              var telephone = '+237' + $("#telephone").val();
              var email = $("#multiStepsEmail").val();
              Swal.fire({
                title: response.title,
                text: response.message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'OK',
                customClass: {
                  confirmButton: 'btn btn-primary flex-fill bd-highlight',
                  cancelButton: 'btn btn-label-secondary flex-fill bd-highlight',
                  actions: 'w-100 p-3'
                },
                buttonsStyling: false
              }).then(function (result) {
                if (result.value) {
                  url = $("#registerStep1").attr("action");
                  $("#loaderSpn").show();
                  $.ajax({
                    url: url,
                    data: formData,
                    type: "POST",
                    dataType: "json"
                  })
                    .done(function (response) {
                      Swal.fire({
                        icon: 'success',
                        title: response.title,
                        text: response.message,
                        customClass: {
                          confirmButton: 'btn btn-success'
                        }
                      });
                      $("#errors_list").html('');
                      // Jump to the next step when all fields in the current step are valid
                      $("#loaderSpn").hide();
                      validationStepper.next();
                    }).fail(function (response) {
                      toastr.error(response.message);
                      $("#loaderSpn").hide();
                    })
                }
              });

            } else if (response.code === 105) {
              printErrorMsg(response.errors);
            } else {
              toastr.error(response.message);
            }
          })
          .fail(function (response) {
            toastr.error(response.message);
          })

      });

      // Telephone verification
      const multiSteps2 = FormValidation.formValidation(stepsValidationFormStep2, {
        fields: {

        },
        plugins: {
          trigger: new FormValidation.plugins.Trigger(),
          bootstrap5: new FormValidation.plugins.Bootstrap5({
            // Use this for enabling/changing valid/invalid class
            // eleInvalidClass: '',
            eleValidClass: '',
            rowSelector: function (field, ele) {
              // field is the field name
              // ele is the field element
              switch (field) {
                case 'multiStepsCard':
                  return '.col-md-12';

                default:
                  return '.col-dm-6';
              }
            }
          }),
          autoFocus: new FormValidation.plugins.AutoFocus(),
          submitButton: new FormValidation.plugins.SubmitButton()
        },
        init: instance => {
          instance.on('plugins.message.placed', function (e) {
            if (e.element.parentElement.classList.contains('input-group')) {
              e.element.parentElement.insertAdjacentElement('afterend', e.messageElement);
            }
          });
        }
      }).on('core.form.valid', function () {
        // You can submit the form
        // stepsValidationForm.submit()
        // or send the form data to server via an Ajax request
        // To make the demo simple, I just placed an alert

        //Create person and send otp
        var url = "";
        var token;
        var formData = {
          _token: $("#token2").val(),
          otpCode: $("#otpCode").val(),
          emailOtpCode: $("#emailOtpCode").val(),
          telephone: '+237' + $("#telephone").val(),
          email: $("#multiStepsEmail").val(),
        };

        url = $("#registerStep2").attr("action");

        $.ajax({
          url: url,
          data: formData,
          method: "POST",
          dataType: "json"
        })
          .done(function (response) {
            console.log(response['code']);
            if (response.code === 100) {
              $("#telephone2").val($("#telephone").val());
              $("#multiStepsEmail2").val($("#multiStepsEmail").val());
              toastr.success(response.message);

              // Jump to the next step when all fields in the current step are valid
              validationStepper.next();
            } else {
              toastr.error(response.message);
            }

            if (response.code === 102) {
              $("#emailOtpCode").prop('disabled', true);
              $("#resendEmailCode").prop('disabled', true);
            }

            if (response.code === 103) {
              $("#otpCode").prop('disabled', true);
              $("#resendTelephoneCode").prop('disabled', true);
            }

          })
          .fail(function (response) {
            toastr.error(response.message);
          })
      });

      // Account details
      const multiSteps3 = FormValidation.formValidation(stepsValidationFormStep3, {
        fields: {
          telephone2: {
            validators: {
              notEmpty: {
                message: 'Please enter your phone number'
              }
            }
          },
          multiStepsEmail2: {
            validators: {
              notEmpty: {
                message: 'Please enter email address'
              },
              emailAddress: {
                message: 'The value is not a valid email address'
              }
            }
          },
        },
        plugins: {
          trigger: new FormValidation.plugins.Trigger(),
          bootstrap5: new FormValidation.plugins.Bootstrap5({
            // Use this for enabling/changing valid/invalid class
            // eleInvalidClass: '',
            eleValidClass: '',
            rowSelector: '.col-sm-6'
          }),
          autoFocus: new FormValidation.plugins.AutoFocus(),
          submitButton: new FormValidation.plugins.SubmitButton()
        },
        init: instance => {
          instance.on('plugins.message.placed', function (e) {
            if (e.element.parentElement.classList.contains('input-group')) {
              e.element.parentElement.insertAdjacentElement('afterend', e.messageElement);
            }
          });
        }
      }).on('core.form.valid', function () {
        //Create person and send otp
        var url = "";
        var token;
        var formData = {
          _token: $("#token3").val(),
          telephone: $("#telephone").attr("data-bs-value"),
          email: $("#multiStepsEmail").val(),
          password: $("#multiStepsPass").val(),
          password_confirmation: $("#multiStepsConfirmPass").val(),
          first_name: $("#multiStepsFirstName").val(),
          last_name: $("#multiStepsLastName").val(),
          post_box: $("#multiStepsPincode").val(),
          address: $("#multiStepsAddress").val(),
          unique_id_number: $("#multiStepsUniqueIdNumber").val()
        };

        url = $("#registerStep3").attr("action");

        $.ajax({
          url: url,
          data: formData,
          method: "POST",
          dataType: "json"
        })
          .done(function (response) {
            console.log(response['code']);
            if (response.code === 100) {
              toastr.success(response.message);
              // Jump to the next step when all fields in the current step are valid
              setTimeout(function () {
                validationStepper.next();
                var url = baseUrl;
                $(location).attr('href', url);
              }, 4000);

            } else if (response.code === 105) {
              toastr.error(response.message);
            } else {
              toastr.error(response.message);
            }
          })
          .fail(function (response) {
            toastr.error(response.message);
          })
      });

      stepsValidationNext.forEach(item => {
        item.addEventListener('click', event => {
          // When click the Next button, we will validate the current step
          switch (validationStepper._currentIndex) {
            case 0:
              multiSteps1.validate();
              break;

            case 1:
              multiSteps2.validate();
              break;

            case 2:
              multiSteps3.validate();
              break;

            default:
              break;
          }
        });
      });

      stepsValidationPrev.forEach(item => {
        item.addEventListener('click', event => {
          switch (validationStepper._currentIndex) {
            case 2:
              validationStepper.previous();
              break;

            case 1:
              validationStepper.previous();
              break;

            case 0:

            default:
              break;
          }
        });
      });
    }
  })();
});

$("#resendTelephoneCode").on("click", function () {
  resendOtp('telephone')
})

$(function () {
  $("#resendEmailCode").on("click", function () {
    resendOtp('email')
  })
});


function resendOtp(typeCode) {
  //Create person and send otp
  var url = "";
  var formData = {
    telephone: '+237' + $("#telephone").val(),
    email: $("#multiStepsEmail").val(),
    typeCode: typeCode
  };

  url = $("#resendTelephoneCode").attr("action");

  $.ajax({
    url: url,
    data: formData,
    method: "GET",
    dataType: "json"
  })
    .done(function (response) {
      console.log(response['code']);
      if (response.code === 100) {
        toastr.success(response.message);
      } else {
        toastr.error(response.message)
      }
    })
    .fail(function (response) {
      toastr.success(response.message);
      setTimeout(10000)
    })
}



function printErrorMsg(msg) {
  $("#errors_list").html('');
  $("#errors_list").css('display', 'block');

  $("#errors_list").append('<ul></ul>');
  $.each(msg, function (key, value) {
    $("#errors_list").find("ul").append('<li>' + value + '</li>');
  });

}
