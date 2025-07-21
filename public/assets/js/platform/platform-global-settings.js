'use strict';

$(function () {
  let datatable = $('#allSettingsDataTable'), dtBasic;
  let editSettingModal = $('#editSettingModal');
  let editSettingModalForm = null;
  let allSettings = [];
  let currentSetting;
  let currentElement;

  /** Init all components */

  loadInitialDataPage();

  /** Event handler */

  $('button#btnEdit').on('click', function (event) {
    event.preventDefault();
    computeAjaxSettings();
  });

  $('#editSettingModal .btn-close, #editSettingModal #btnCancel').click(function (event) {
    event.preventDefault();

    resetModalInputs();
    resetInputsErrors();
    $('.setting-wrapper').addClass('d-none');
    editSettingModal.modal('hide');
  });

  /** Functions */

  function loadInitialDataPage() {
    $.ajax({
      url: window.variables.routes.all_settings,
      ataType: "json",
      beforeSend: function () {
        $('.global-spinner').removeClass('d-none');
      },
      complete: function () {
        $('.global-spinner').addClass('d-none');
      },
      error: function () {},
      success: function (result) {
        if (result.code === 100) {
          allSettings = result.data;

          initMainDataTable();
        } else {
        }
      }
    });
  }

  function initMainDataTable() {
    if (datatable.length) {
      dtBasic = datatable.DataTable(dataTableOption(allSettings));
    }
  }

  function resetInputsErrors() {
    $('.error-helper').addClass('d-none').html('');
    $('input').removeClass('is-invalid').removeClass('border-danger');
    $('.select2').removeClass('is-invalid').removeClass('border-danger');
  }

  function datatableActionEvent() {
    let tbody = $('#allSettingsDataTable tbody');

    tbody.on('click', 'td button.btn-edit', function (event) {
      event.preventDefault();

      currentElement = $(this);
      buildModalForEdition(currentElement);
    });

    tbody.on('change', 'td input.application-status', function (event) {
      event.preventDefault();

      let $this = $(this);
      let value = $this.is(':checked') ? 1 : 0;

      $.ajax({
        url: window.variables.routes.edit_settings,
        type: 'POST',
        dataType: 'json',
        data: {
          setting_name: $this.attr('data-bs-target'),
          setting_value: value
        },
        beforeSend: function () {},
        complete: function () {},
        error: function () {},
        success: function (result) {
          if (result.code === 100) {
            let searchResult = findDataTableById(result.data.id);
            allSettings[searchResult.index] = result.data;
            dtBasic.clear();
            dtBasic = dtBasic.rows.add(allSettings);
            dtBasic.draw(false);
          }
        }
      });
    });
  }

  function dataTableOption(data) {
    return {
      data: JSON.parse(JSON.stringify(data)),
      paging: true,
      ordering: true,
      info: true,
      displayLength: 100,
      searching: true,
      lengthChange: true,
      orderCellsTop: false,
      order: [[0, 'asc']],
      language: window.variables.messages.languages,
      columnDefs: [
        {
          targets: '_all',
          orderable: false,
          defaultContent: '--',
          className: ''
        },
        {
          targets: 0,
          className: '',
          render: function (data, type, full, meta) {
            return '<div>' + full.description_message + '</div>';
          }
        },
        {
          targets: 1,
          className: 'w-px-200 text-nowrap',
          render: function (data, type, full, meta) {
            if (full.name === window.variables.config.apply_a_color.toUpperCase()) {
              return `
                <div about="${full.value_message}" style="width: 70px; height: 20px; border-radius: 10px; background-color: ${full.value_message}">
                </div>
              `;
            }
            if (full.name === 'GENERAL_FEES_CONFIGURATIONS') {
              return `<div>--</div>`;
            }

            if (full.name === window.variables.config.dg_visa.toUpperCase()) {
              if (full.value_message !== null && full.value_message.length) {
                return `<a href="${full.value_message}" target="_blank">${window.variables.messages.show}</a>`;
              }

              return '';
            }

            if (
              full.name === window.variables.config.apply_dg_visa.toUpperCase() ||
              full.name === window.variables.config.apply_qr_code.toUpperCase() ||
              full.name === window.variables.config.also_search_for_requests_to_be_processed_by_service.toUpperCase() ||
              full.name === window.variables.config.approve_request_with_pending_invoice.toUpperCase()
            ) {
              let switchElement = $(`
                <div class="">
                    <label class="switch switch-square">
                      <input data-bs-target="${full.name}" type="checkbox" class="switch-input application-status" ${parseInt(full.value.toString()) === 1 ? 'checked' : ''} />
                      <span class="switch-toggle-slider">
                        <span class="switch-on"></span>
                        <span class="switch-off"></span>
                      </span>
                      <span class="switch-label">${full.value_message}</span>
                    </label>
                </div>`);
              return switchElement.html();
            }

            if (full.name === window.variables.config.notification_settings.toUpperCase()) {
              return '--';
            }

            return full.value_message;
          }
        },
        {
          targets: 2,
          className: 'dt-right',
          render: function (data, type, full, meta) {
            if (
              full.name === window.variables.config.apply_dg_visa.toUpperCase() ||
              full.name === window.variables.config.apply_qr_code.toUpperCase() ||
              full.name === window.variables.config.also_search_for_requests_to_be_processed_by_service.toUpperCase() ||
              full.name === window.variables.config.approve_request_with_pending_invoice.toUpperCase()
            ) {
              return '';
            }

            let actionWrapper = $('<div class=""></div>');
            let editAction = $(
              '<button data-bs-target="' + full.id + '" data-bs-description="' + full.description_message + '" data-bs-wrapper="' +  full.name.toLowerCase() + '" type="button" class="btn btn-icon btn-label-primary btn-sm ms-2 btn-edit">' +
                '<span class="tf-icons bx bx-edit"></span>' +
              '</button>'
            );

            actionWrapper.append(editAction);
            return actionWrapper.html();
          }
        }
      ],
      initComplete: function (settings, json) {
        $('.dataTables_length').find('label').addClass('ms-4 ms-md-0');
        $('.dataTables_filter .form-control').removeClass('form-control-sm').attr('placeholder', window.variables.messages.search + ' ...');
        $('.dataTables_length .form-select').removeClass('form-select-sm');

        datatableActionEvent();
        $('#pageWrapper').removeClass('d-none');
      }
    };
  }

  function buildModalForEdition(element) {
    let el = $(element);

    currentSetting = findDataTableById(parseInt(el.attr('data-bs-target')));
    if (currentSetting !== null) {
      currentSetting = currentSetting.element;

      editSettingModal.find('input.setting_name').attr('value', currentSetting.name);
      editSettingModal.find('setting-wrapper').addClass('d-none');
      let description = el.attr('data-bs-description');
      let wrapper = el.attr('data-bs-wrapper');

      if (wrapper) {
        let elWrapper = $('.' + wrapper + '_wrapper')
        editSettingModalForm = elWrapper.find('form');
        if (description) {
          elWrapper.find('span.description').html(description);
        }

        // Set old value
        if (wrapper === window.variables.config.skip_processing_step) {
          elWrapper.find('input[type="radio"]').each(function () {
            $(this).prop('checked', $(this).attr('value') === currentSetting.value);
          });
        }

        if (wrapper === window.variables.config.apply_a_color) {
          let newTypeIndicatorColor = $('#newTypeIndicatorColor');
          newTypeIndicatorColor.css('background-color', 'inherit');
          newTypeIndicatorColor.spectrum({
            change: function (color) {
              newTypeIndicatorColor.css({
                'background-color': `${color.toHexString()}`,
                color: 'white'
              });
            },
            move: function (color) {
              newTypeIndicatorColor.css({
                'background-color': `${color.toHexString()}`,
                color: 'white'
              });
            },
            hide: function (color) {
              newTypeIndicatorColor.css({
                'background-color': `${color.toHexString()}`,
                color: 'white'
              });
            },
            dragStart: function (_, color) {
              newTypeIndicatorColor.css({
                'background-color': `${color.toHexString()}`,
                color: 'white'
              });
            },
            dragStop: function (_, color) {
              newTypeIndicatorColor.css({
                'background-color': `${color.toHexString()}`,
                color: 'white'
              });
              onChange(color.toHexString());
            }
          });
        }

        elWrapper.removeClass('d-none');
        editSettingModal.modal('show');
      }
    }
  }

  function findDataTableById(id) {
    let result = null;

    for (let i = 0; i < allSettings.length; i++) {
      if (allSettings[i].id === id) {
        result = {
          index: i,
          element: allSettings[i]
        };
      }
    }

    return result;
  }

  function resetModalInputs() {}

  function computeAjaxSettings() {
    let options = {};

    if (editSettingModalForm.find('input.setting_name').val() === window.variables.config.dg_visa.toUpperCase()) {
      let formData = new FormData(editSettingModalForm[0]);
      options = {
        cache: false,
        contentType: false,
        processData: false,
        data: formData
      };
    } else {
      let jsonData = computeSettingValue();
      options = {
        data: jsonData
      };
    }

    $.ajax({
      url: editSettingModalForm.attr('action'),
      type: 'POST',
      dataType: 'json',
      ...options,
      beforeSend: function () {
        resetInputsErrors();
        editSettingModal.find('.request-spinner').removeClass('d-none');
        editSettingModal.find('.actions-wrapper').addClass('d-none');
        editSettingModal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        editSettingModal.find('.request-spinner').addClass('d-none');
        editSettingModal.find('.actions-wrapper').removeClass('d-none');
        editSettingModal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation(
          {
            setting_name: window.variables.messages.unable_to_update_setting
          },
          wrapperEl
        );
      },
      success: function (result) {
        switch (result.code) {
          case 100:
            currentSetting = null;

            let searchResult = findDataTableById(result.data.id);
            allSettings[searchResult.index] = result.data;
            dtBasic.clear();
            dtBasic = dtBasic.rows.add(allSettings);
            dtBasic.draw(false);

            // Hide modal ...
            resetModalInputs();
            resetInputsErrors();
            $('.setting-wrapper').addClass('d-none');
            editSettingModal.modal('hide');
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, wrapperEl);
            break;
          default:
            Helpers.customInputErrorsValidation(
              {
                setting_value: result.message
              },
              wrapperEl
            );
            break;
        }
      }
    });
  }

  function computeSettingValue() {
    let wrapper = currentElement.attr('data-bs-wrapper');
    let elWrapper = $('.' + wrapper + '_wrapper');
    let jsonData = JSON.parse(Helpers.convertFormToJSON(editSettingModalForm[0]));

    if (wrapper === window.variables.config.general_fees_configurations) {
      jsonData.setting_value = elWrapper.find('[name="fees_setting_value"]').val();
      return jsonData;
    }

    if (wrapper === window.variables.config.skip_processing_step) {
      jsonData.setting_value = elWrapper.find('input[type="radio"]:checked').attr('value');
      return jsonData;
    }

    if (wrapper === window.variables.config.notification_settings) {
      jsonData.setting_value = '';
      return jsonData;
    }

    jsonData.setting_value = elWrapper.find('[name="setting_value"]').val();
    return jsonData;
  }
});
