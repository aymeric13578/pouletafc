'use strict';

$(function () {
  // Var ...
  let globalAlert = $('#globalAlert');
  let requestEditionEquipment_Modal = $('#requestEditionEquipment_Modal');
  let requestEditionEquipment_Form = $('#requestEditionEquipment_Form');
  let equipmentCategory_Edition = requestEditionEquipment_Form.find('select#equipmentCategory_Edition');
  let equipmentSubCategory_Edition = requestEditionEquipment_Form.find('select#equipmentSubCategory_Edition');
  let requestEquipmentEditionLink_Action = $('a.request_equipment_edition_action');
  let globalActionWrapper_Edition = requestEditionEquipment_Form.find('div.global-actions-wrapper');
  let editionConfirmationWrapper_Edition = requestEditionEquipment_Form.find('div.edition-confirmation-wrapper');
  let requestEditionEquipmentRecalculation_Modal = $('#requestEditionEquipmentRecalculation_Modal');
  let requestEditionEquipmentRecalculation_Form = $('#requestEditionEquipmentRecalculation_Form');
  let confirmForBillingElementRecalculation_Modal = $('#confirmForBillingElementRecalculation_Modal');
  let confirmForBillingElementRecalculation_Form = $('#confirmForBillingElementRecalculation_Form');

  let equipmentData = null;
  let allSubCategoryOptions = [];

  // Init
  if(equipmentSubCategory_Edition.length > 0) {
    allSubCategoryOptions = equipmentSubCategory_Edition.find('option');
    handleEquipmentCategoryChanges(
      equipmentSubCategory_Edition,
      equipmentCategory_Edition.val(),
      allSubCategoryOptions,
      'data-bs-category',
      equipmentSubCategory_Edition.attr('data-bs-defaultvalue')
    );
  }

  // Events handler

  if(equipmentCategory_Edition.length > 0) {
    equipmentCategory_Edition.on('change', function(event) {
      event.preventDefault();

      handleEquipmentCategoryChanges(
        equipmentSubCategory_Edition,
        $(this).val(),
        allSubCategoryOptions,
        'data-bs-category',
        equipmentSubCategory_Edition.attr('data-bs-defaultvalue')
      );
    });
  }

  if(equipmentSubCategory_Edition.length > 0) {
    equipmentSubCategory_Edition.on('change', function(event) {
      event.preventDefault();
      let element = $(this);

      element.find('option').each(function() {
        if($(this).prop('selected')) {
          let withPs = $(this).val().length > 0 && parseInt($(this).attr('data-bs-perPs')) === 1;
          handleQuantityUnit(withPs);
        }
      });
    });
  }

  requestEquipmentEditionLink_Action.on('click', function(event) {
    event.preventDefault();

    // Show modal
    requestEditionEquipment_Modal.modal('show');
  });

  // Handle action with ajax request

  requestEditionEquipment_Form.find('button#btnConfirm').on('click', function(event) {
    event.preventDefault();

    if(equipmentData !== null && equipmentData['can_recalculate_approval']) {
      resetModalInputs('requestEditionEquipment_Form');
      requestEditionEquipment_Modal.modal('hide');
      requestEditionEquipmentRecalculation_Modal.modal('show');
    } else {
      // Get form
      let formElement = requestEditionEquipment_Form;

      let jsonData = {
        ...JSON.parse(Helpers.convertFormToJSON(formElement[0])),
        request_id: formElement.attr('data-bs-requesttarget'),
        equipment_id:  formElement.attr('data-bs-equipmenttarget')
      };

      if(equipmentData !== null) {
        jsonData = {
          ...equipmentData,
          ...jsonData,
          recalculation_confirmation: true,
        };
      }

      $.ajax({
        url: formElement.attr('action'),
        type: "POST",
        dataType : 'json',
        data: jsonData,
        beforeSend: function () {
          resetModalInputErrors(formElement);

          requestEditionEquipment_Modal.find('.request-spinner').removeClass('d-none');
          requestEditionEquipment_Modal.find('.actions-wrapper').addClass('d-none');
          requestEditionEquipment_Modal.find('button.btn-close').addClass('d-none');
          Helpers.handleAlertVisibility(globalAlert, false);
        },
        complete: function () {
          requestEditionEquipment_Modal.find('.request-spinner').addClass('d-none');
          requestEditionEquipment_Modal.find('.actions-wrapper').removeClass('d-none');
          requestEditionEquipment_Modal.find('button.btn-close').removeClass('d-none');
        },
        error: function () {
          Helpers.customInputErrorsValidation({
            request_id: window.variables.messages.unable_to_perform_request
          }, requestEditionEquipment_Modal);
        },
        success: function (result) {
          switch(result.code){
            case 100:
              // Notify
              Helpers.handleAlertVisibility(globalAlert, true, 'alert-success', window.variables.messages.successful_operation);
              // Hide modal
              requestEditionEquipment_Modal.modal('hide');
              // Reload
              setTimeout(() => {
                window.location.reload();
              }, 1000);
              break;
            case 120:
              // Continue process
              editionConfirmationWrapper_Edition.removeClass('d-none');

              // Update page section
              let listWrapper = editionConfirmationWrapper_Edition.find('ol.elements-to-updated');
              listWrapper.empty();
              result.data['to_update'].forEach(function (element) {
                listWrapper.append(`
                <li class="text-body">
                  <span class="">${element}</span>
                </li>
              `);
              });

              // Store data ...
              equipmentData = {
                can_recalculate_approval: parseInt(result.data['can_recalculate_approval']) === 1,
                request_id: formElement.attr('data-bs-requesttarget'),
                equipment_id: formElement.attr('data-bs-equipmenttarget'),
                quantity: jsonData['quantity'],
                equipment_category_id: jsonData['equipment_category_id'],
                equipment_sub_category_id: jsonData['equipment_sub_category_id'],
              };
              break;
            case 150:
              Helpers.customInputErrorsValidation(result.data, requestEditionEquipment_Modal);
              break;
            case 160:
              equipmentData = jsonData;

              requestEditionEquipment_Modal.modal('hide');
              handleZeroBillingElementModal(confirmForBillingElementRecalculation_Modal, result.message);
              break;
            default:
              Helpers.customInputErrorsValidation({
                request_id: result.message
              }, requestEditionEquipment_Modal);
              break;
          }
        },
      });
    }
  });

  requestEditionEquipmentRecalculation_Form.find('button#btnConfirm').click(function (event) {
    event.preventDefault();

    let jsonData = JSON.parse(Helpers.convertFormToJSON(requestEditionEquipmentRecalculation_Form[0]));
    jsonData = {
      ...equipmentData,
      ...jsonData,
      recalculation_confirmation: true,
    };

    $.ajax({
      url: requestEditionEquipmentRecalculation_Form.attr('action'),
      type: "POST",
      dataType: 'json',
      data: jsonData,
      beforeSend: function () {
        resetModalInputErrors(requestEditionEquipmentRecalculation_Form);

        requestEditionEquipmentRecalculation_Modal.find('.request-spinner').removeClass('d-none');
        requestEditionEquipmentRecalculation_Modal.find('.actions-wrapper').addClass('d-none');
        requestEditionEquipmentRecalculation_Modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        requestEditionEquipmentRecalculation_Modal.find('.request-spinner').addClass('d-none');
        requestEditionEquipmentRecalculation_Modal.find('.actions-wrapper').removeClass('d-none');
        requestEditionEquipmentRecalculation_Modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          request_id: window.variables.messages.unable_to_perform_request,
        }, requestEditionEquipmentRecalculation_Modal);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            requestEditionEquipmentRecalculation_Modal.modal('hide');

            setTimeout(() => {
              window.location.reload();
            }, 1000);

            if(result.data && result.data.doc_url) {
              window.open(result.data.doc_url, '_blank');
            }
            break;
          case 150:
            Helpers.customInputErrorsValidation(
              result.data,
              requestEditionEquipmentRecalculation_Modal
            );
            break;
          case 160:
            equipmentData = jsonData;

            requestEditionEquipmentRecalculation_Modal.modal('hide');
            handleZeroBillingElementModal(confirmForBillingElementRecalculation_Modal, result.message);
            break;
          default:
            Helpers.customInputErrorsValidation({
              request_id: result.message,
            }, requestEditionEquipmentRecalculation_Modal);
            break;
        }
      },
    });
  });

  confirmForBillingElementRecalculation_Form.find('button#btnConfirm').on('click', function(event) {
    event.preventDefault();

    let actionForm = confirmForBillingElementRecalculation_Form;

    if(equipmentData !== null) {
      equipmentData['confirmation'] = true;

      $.ajax({
        url: actionForm.attr('action'),
        type: actionForm.attr('method'),
        dataType : 'json',
        data: equipmentData,
        beforeSend: function () {
          resetModalInputErrors(confirmForBillingElementRecalculation_Form);

          confirmForBillingElementRecalculation_Form.find('.request-spinner').removeClass('d-none');
          confirmForBillingElementRecalculation_Form.find('.actions-wrapper').addClass('d-none');
          confirmForBillingElementRecalculation_Form.find('button.btn-close').addClass('d-none');
        },
        complete: function () {
          confirmForBillingElementRecalculation_Form.find('.request-spinner').addClass('d-none');
          confirmForBillingElementRecalculation_Form.find('.actions-wrapper').removeClass('d-none');
          confirmForBillingElementRecalculation_Form.find('button.btn-close').removeClass('d-none');
        },
        error: function () {
          Helpers.customInputErrorsValidation({
            error: window.variables.messages.unable_to_perform_request
          }, confirmForBillingElementRecalculation_Form);
        },
        success: function (result) {
          switch (result.code) {
            case 100:
              equipmentData = null;

              confirmForBillingElementRecalculation_Modal.modal('hide');
              Helpers.handleAlertVisibility(
                globalAlert,
                true,
                'alert-success',
                window.variables.messages.successful_operation
              );

              setTimeout(() => {
                window.location.reload();
              }, 1000);

              // Redirect ...
              if(result.data && result.data.doc_url) {
                window.open(result.data.doc_url, '_blank');
              }
              break;
            case 150:
              Helpers.customInputErrorsValidation({
                error: result.data[Object.keys(result.data)[0]][0],
              }, confirmForBillingElementRecalculation_Form);
              break;
            default:
              Helpers.customInputErrorsValidation({
                error: result.message,
              }, confirmForBillingElementRecalculation_Form);
              break;
          }
        },
      });
    }
  });

  // Close actions

  $('#requestEditionEquipment_Modal #btnCancel, #requestEditionEquipment_Modal .btn-close').on('click', function(event) {
    event.preventDefault();

    equipmentData = null;
    // Hide modal
    handleOverlayWrapper(requestEditionEquipment_Form);
    requestEditionEquipment_Modal.modal('hide');
    resetModalInputErrors(requestEditionEquipment_Form);
    resetModalInputs('requestEditionEquipment_Form');
  });

  $('#requestEditionEquipmentRecalculation_Modal #btnCancel, #requestEditionEquipmentRecalculation_Modal .btn-close').on('click', function(event) {
    event.preventDefault();

    equipmentData = null;
    // Hide modal
    requestEditionEquipmentRecalculation_Modal.modal('hide');
    resetModalInputErrors(requestEditionEquipmentRecalculation_Form);
    resetModalInputs('requestEditionEquipmentRecalculation_Form');
  });

  $('#confirmForBillingElementRecalculation_Modal #btnCancel, #confirmForBillingElementRecalculation_Modal .btn-close').on('click', function(event) {
    event.preventDefault();

    equipmentData = null;
    // Hide modal
    confirmForBillingElementRecalculation_Modal.modal('hide');
    resetModalInputErrors(confirmForBillingElementRecalculation_Form);
    resetModalInputs('confirmForBillingElementRecalculation_Form');
  });

  /** Utilities */

  function resetModalInputErrors(wrapper) {
    wrapper.find('.error-helper').addClass('d-none').html('');
    wrapper.find('input').removeClass('is-invalid').removeClass('border-danger');
    wrapper.find('.select2').removeClass('is-invalid').removeClass('border-danger');
  }

  function resetModalInputs(wrapperId) {
    if(wrapperId === 'requestEditionEquipment_Form') {
      let select2 = requestEditionEquipment_Form.find('select.select2');

      requestEditionEquipment_Form.trigger('reset');
      select2.val(select2.attr('data-bs-defaultvalue')).trigger('change');
      editionConfirmationWrapper_Edition.addClass('d-none');
    }
  }

  function handleEquipmentCategoryChanges(selectElement, value, catalog, attr, selectorValue) {
    if(selectElement.length > 0) {
      selectElement.find('option').remove();
      catalog.each(function(index) {
        if($(this).attr('value').length === 0 || (value && attr && $(this).attr(attr) === value)) {
          let selected = $(this).attr('value').length !== 0 && selectorValue === $(this).attr('value');

          $(this).prop('selected', selected);
          selectElement.append($(this));
        }
      });

      selectElement.trigger('change');
    }
  }

  function handleQuantityUnit(withPerPs) {
    let input = requestEditionEquipment_Form.find('input#equipmentQuantity_Edition');
    let defaultPsUnit = '';

    // Update ...
    if(input.length > 0 && withPerPs) {
      defaultPsUnit = window.variables.default_ps_unit;
    }

    input.next('span.quantity-unit').text(defaultPsUnit);
  }

  function handleOverlayWrapper(wrapper, state =false) {
    let overlay = wrapper.find('div.form-content').find('div.overlay-wrapper');

    if(overlay.length > 0) {
      if(state) {
        overlay.removeClass('d-none');
      } else {
        overlay.addClass('d-none');
      }
    }
  }

  function handleZeroBillingElementModal(wrapper, message) {
    wrapper.find('p.content-message').html(message);
    //  Show
    wrapper.modal('show');
  }
});
