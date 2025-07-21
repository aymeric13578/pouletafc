/**
 * Edit Attachment type Modal JS
 */

'use strict';

// Edit attachment type form validation
document.addEventListener('DOMContentLoaded', function (e) {
  (function () {
    FormValidation.formValidation(document.getElementById('editAttachmentTypeForm'), {
      fields: {
        editAttachmentTypeCode: {
          validators: {
            notEmpty: {
              message: 'Please enter attachment code'
            }
          }
        },
        editAttachmentTypeDesignation: {
          validators: {
            notEmpty: {
              message: 'Please enter attachment designation'
            }
          }
        }
      },
      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({
          // Use this for enabling/changing valid/invalid class
          // eleInvalidClass: '',
          eleValidClass: '',
          rowSelector: '.col-sm-9'
        }),
        submitButton: new FormValidation.plugins.SubmitButton(),
        // Submit the form when all fields are valid
         defaultSubmit: new FormValidation.plugins.DefaultSubmit(),
        autoFocus: new FormValidation.plugins.AutoFocus()
      }
    });
  })();
});
