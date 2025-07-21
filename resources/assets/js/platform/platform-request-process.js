'use strict';

$(function () {
  let globalProgressInProgress = false;
  let dItem;
  let dUpdateWrapper;
  let globalEditionDatepicker = $('.global-edition-datepicker');

  // Init ...

  initDatePicker();
  const selectPicker = $('.selectpicker');
  if (selectPicker.length) {
    selectPicker.selectpicker();
  }

  // Handle process action

  $(document).mouseup(function(e) {
    // if the target of the click isn't the container nor a descendant of the container
    if (dUpdateWrapper && !dUpdateWrapper.is(e.target) && dUpdateWrapper.has(e.target).length === 0) {
      let inputElement = dUpdateWrapper.find('form').find('input.global-edition-datepicker');

      if(inputElement.length <= 0) {
        closeGlobalEditionWrapper();
      }
    }
  });

  $('.d-item-wrapper').on('click', 'span.edit-action', function (event) {
    event.preventDefault();

    closeGlobalEditionWrapper();
    let $this = $(this);

    if(!globalProgressInProgress) {
      dItem = $this.parent();
      dUpdateWrapper = $this.parent().parent().find('div.update-topic-item');

      dItem.addClass('d-none');
      dUpdateWrapper.removeClass('d-none');

      let formWrapper = dUpdateWrapper.find('form');
      let inputBase = formWrapper.find('.form-control');
      let attributes = inputBase.attr("data-bs-attributes").trim();

      // Init value

      inputBase.val(dItem.find('span.content').attr('data-bs-value').trim());
      resetSelect();

      // Get action elements

      let btnSave = dUpdateWrapper.find('button.btn-edition-save');
      let btnCancel = dUpdateWrapper.find('button.btn-edition-cancel');
      let progressSpinner = dUpdateWrapper.find('div.default-progress');

      // Add events
      btnSave.unbind();
      btnCancel.unbind();

      btnSave.bind('click', function (event) {
        event.preventDefault();

        let formData = new FormData(formWrapper[0]);
        let rules = inputBase.attr("data-bs-rules") !== undefined && inputBase.attr("data-bs-rules") !== null ? inputBase.attr("data-bs-rules").trim() : '';
        let resourceTarget = inputBase.attr("data-bs-resourcetarget") !== undefined && inputBase.attr("data-bs-resourcetarget") !== null ? inputBase.attr("data-bs-resourcetarget").trim() : '';

        formData.set('request_target', inputBase.attr("data-bs-requestTarget") ? inputBase.attr("data-bs-requestTarget").trim() : '');
        formData.set('request_model', inputBase.attr("data-bs-requestModel") ? inputBase.attr("data-bs-requestModel") : '');
        formData.set('target_attributes', attributes);
        formData.set('rules', rules);
        formData.set('resource_target', resourceTarget);

        let select = null;
        if(formWrapper.length > 0) {
          select = formWrapper.find('select.selectpicker');
          let unitRules = select.attr("data-bs-unitrules") !== undefined && select.attr("data-bs-unitrules") !== null ? select.attr("data-bs-unitrules").trim() : '';
          let unitAttribute = select.attr("data-bs-unitattribute") !== undefined && select.attr("data-bs-unitattribute") !== null ? select.attr("data-bs-unitattribute").trim() : '';

          if(select.length > 0) {
            formData.set('unit_rules', unitRules);
            formData.set('target_unit_attributes', unitAttribute);
          }
        }

        $.ajax({
          url: formWrapper.attr('action'),
          type: "POST",
          cache: false,
          contentType: false,
          processData: false,
          dataType : 'json',
          data: formData,
          beforeSend: function () {
            globalProgressInProgress = true;
            resetWrapperInputErrors(formWrapper);

            btnCancel.addClass('d-none');
            btnSave.addClass('d-none');
            progressSpinner.removeClass('d-none');
          },
          complete: function () {
            globalProgressInProgress = false;

            btnCancel.removeClass('d-none');
            btnSave.removeClass('d-none');
            progressSpinner.addClass('d-none');
          },
          error: function () {
            Helpers.customInputErrorsValidation({
              content: window.variables.messages.error_occurred_during_operation
            }, formWrapper);
          },
          success: function (result) {
            switch(result.code){
              case 100:
                let contentWrapper = dItem.find('span.content');
                let suffix = dItem.find('span.content').attr('data-bs-suffix');

                if(result.data.content.length > 0) {
                  if(result.data.unit !== null) suffix = result.data.unit.label;
                  contentWrapper.html(`${result.data.content} ${suffix}`);
                  contentWrapper.attr("data-bs-value", `${result.data.content}`);
                } else {
                  contentWrapper.html(`${inputBase.attr('data-bs-empty')}`);
                  contentWrapper.attr("data-bs-value", '');
                }

                if(select !== null && select.length > 0 && result.data.unit !== null) {
                  select.attr("data-bs-value", result.data.unit.id);
                }

                // Close wrappers ...
                if(dItem && dItem.length) dItem.removeClass('d-none');
                if(dUpdateWrapper && dUpdateWrapper.length) dUpdateWrapper.addClass('d-none');
                break;
              case 150:
                Helpers.customInputErrorsValidation(result.data, formWrapper);
                break;
              default:
                Helpers.customInputErrorsValidation({
                    content: result.message
                  }, formWrapper,
                );
                break;
            }
          }
        });
      });

      btnCancel.bind('click', function (event) {
        event.preventDefault();
        closeGlobalEditionWrapper();
      });
    }
  });

  // Utilities

  function closeGlobalEditionWrapper() {
    if(dUpdateWrapper && dUpdateWrapper.length) resetWrapperInputErrors(dUpdateWrapper.find('form'), true);
    if(dItem && dItem.length) dItem.removeClass('d-none');
    if(dUpdateWrapper && dUpdateWrapper.length) dUpdateWrapper.addClass('d-none');
  }

  function resetWrapperInputErrors(wrapper, reset = false) {
    if(reset) {
      wrapper.trigger('reset');
    }

    if(wrapper && wrapper.length) {
      wrapper.find('.error-helper').addClass('d-none').html('');
      wrapper.find('.form-control').removeClass('is-invalid').removeClass('border-danger');
      wrapper.find('.select2').removeClass('is-invalid').removeClass('border-danger');
      wrapper.find('.selectpicker').removeClass('is-invalid').removeClass('border-danger');
    }
  }

  function initDatePicker() {
    let date = new Date();
    let currYear = date.getFullYear();

    if (globalEditionDatepicker.length > 0) {
      globalEditionDatepicker.flatpickr({
        monthSelectorType: 'static',
        defaultDate: new Date(currYear, 12, 0),
        maxDate: date,
        dateFormat: "Y-m-d",
      });
    }
  }

  function resetSelect() {
    if(dUpdateWrapper.length > 0) {
      let formWrapper = dUpdateWrapper.find('form');

      if(formWrapper.length > 0) {
        let select = formWrapper.find('select.selectpicker');
        select.selectpicker('val', select.attr('data-bs-value'));
      }
    }
  }
});
