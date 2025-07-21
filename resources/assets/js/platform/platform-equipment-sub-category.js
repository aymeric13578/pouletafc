'use strict';

$(function () {
  let currentPricing = null;
  let pricingData = null;
  let datatable = $('#pricingDataTable'), dtBasic;
  let addPricingLabel= window.variables.messages.add_configuration;
  let actionIsEdition = false;
  let globalAlert = $('#globalAlert');
  let addPricingModal = $('#addPricingModal');
  let addPricingModalForm = $('#addPricingModalForm');
  let pricingFilters = $('#pricingFilters');
  let dataTableColumns = [
    { data: '' },
    { data: '' },
    { data: 'code' },
    { data: '' },
    { data: '' },
    { data: '' },
    { data: 'created_at' },
    { data: 'actions' },
  ];
  let dataTableLength = null;
  let dataLengthContent = null;
  let masks = [];
  let currency = window.variables.constants.default_currency;

  // Ajax main ...
  loadInitialDataPage();
  initInputMask();
  initSelect2();

  /** Events handler */

  $('#globalAlert .btn-close').click(function (event) {
    event.preventDefault();
    Helpers.handleAlertVisibility(globalAlert, false);
  });

  $('#addPricingModal .btn-close, #addPricingModal #btnCancel').click(function (event) {
    event.preventDefault();
    resetInputs();
    resetInputErrors();
    addPricingModal.modal('hide');
  });

  addPricingModal.find('form').submit(function (event) {
    event.preventDefault();
  });

  addPricingModal.find('button#btnAdd').click(function (event) {
    event.preventDefault();
    addOrUpdatePricingAction();
  });

  $('#btnResetFilter').on('click', function (event) {
    event.preventDefault();

    pricingFilters.val('');
    pricingFilters.trigger('change');
    applyFilterSearch('');
  });

  pricingFilters.on('change', function (event) {
    event.preventDefault();
    let $this = $(this);

    applyFilterSearch($this.val());
  });

  addPricingModal.find('select#subcategory').on('change', function (event) {
    event.preventDefault();
    let $this = $(this);
    let minMaxWrapper = addPricingModal.find('#minMaxWrapper');

    if(parseInt($this.find('option:selected').attr('data-bs-perps')) === 1) {
      minMaxWrapper.removeClass('d-none');
    } else {
      minMaxWrapper.addClass('d-none');
    }
  });

  /** Other methods */

  function loadInitialDataPage() {
    $.ajax({
      url: window.variables.routes.all_approval_pricing,
      dataType: "json",
      beforeSend: function () {
        $('.global-spinner').removeClass('d-none');
      },
      complete: function () {
        $('.global-spinner').addClass('d-none');
      },
      error: function () {},
      success: function (result) {
        if(result.code === 100) {
          pricingData = result.data;

          initMainDataTable();
          $('#pageWrapper').removeClass('d-none');
        } else {}
      },
    });
  }

  function applyFilterSearch(val) {
    let filterData = pricingData;
    if(val.length > 0) {
      filterData = pricingData.filter(function(el) {
        return parseInt(el.equipment_sub_category.id) === parseInt(val);
      });
    }

    dtBasic.clear();
    dtBasic = dtBasic.rows.add(filterData);
    dtBasic.draw(false);
  }

  function initMainDataTable() {
    if (datatable.length) {
      dtBasic = datatable.DataTable(dataTableOption(pricingData));
      datatableActionEvent();

      dataTableLength = $('.dataTables_length');
      dataLengthContent = dataTableLength.html();

      dtBasic.on( 'order.dt search.dt', function () {
        dtBasic.column(0, {search:'applied', order:'applied'}).nodes().each( function (cell, i) {
          cell.innerHTML = '#' + (i + 1);
        } );
      } ).draw();
    }
  }

  function dataTableOption(data) {
    return {
      data: JSON.parse(JSON.stringify(data)),
      paging: true,
      ordering: true,
      info: true,
      displayLength: 10,
      searching: true,
      lengthChange: true,
      orderCellsTop: true,
      order: [[0, "asc"]],
      language: window.variables.messages.languages,
      columns: dataTableColumns,
      createdRow: function(row, data, dataIndex, cells) {
        if(parseInt(data.pricing_type) === 0) {
          $(cells[1]).addClass('p-0');
          $(cells[1]).addClass('border-bottom-0');
        }
        $(cells[1]).addClass('text-center');
        $(cells[3]).addClass('text-center');
        $(cells[4]).addClass('text-center');
        $(cells[5]).addClass('text-center');
      },
      columnDefs: [
        {
          targets: '_all',
          defaultContent: "--",
          className: ''
        },
        {
          targets: 0,
          searchable: false,
          orderable: false,
          render: function (data, type, full, meta) {
            return '#' + full.id;
          }
        },
        {
          targets: 1,
          orderable: false,
          searchable: false,
          render: function (data, type, full, meta) {
            // Per ps
            if(parseInt(full.pricing_type) === 0) {
              return (
                `
                    <div class="w-100 text-center bg-lighter">
                      <div class="d-inline-block w-px-200 text-wrap text-center fw-bold" style="padding: 0.6rem 1.2rem;">
                          ${Helpers.ucWords(full.equipment_sub_category.label)}
                      </div>
                    </div>
                    <table id="" class="datatables-basic table m-0 table-bordered">
                        <tbody>
                         <tr>
                          <td class="text-center" style="padding: 0.4rem 1rem 0.3rem 1rem;">Min Ps</td>
                          <td class="text-center" style="padding: 0.4rem 1rem 0.3rem 1rem;;">Max Ps</td>
                         </tr>
                         <tr>
                          <td class="text-center" style="padding: 0.3rem 1rem 0.2rem 1rem;;">${parseInt(full.min, 10)}</td>
                          <td class="text-center" style="padding: 0.3rem 1rem 0.2rem 1rem;;">${parseInt(full.max, 10)}</td>
                         </tr>
                       </tbody>
                    </table>
                    `
              );
            }

            return (
              '<span class="w-px-200 d-inline-block text-wrap fw-bold">'+ Helpers.ucWords(full.equipment_sub_category.label) +'</span>'
            );
          }
        },
        {
          targets: 2,
          orderable: true,
          searchable: true,
          classname: 'text-uppercase',
          render: function (data, type, full, meta) {
            return full.equipment_sub_category.code;
          }
        },
        {
          targets: 3,
          orderable: true,
          searchable: true,
          classname: 'text-center',
          render: function (data, type, full, meta) {
            return (
              '<span class="fw-bold">'+ Helpers.formatAmount(full.study_fees) + ' ' + currency +'</span>'
            );
          }
        },
        {
          targets: 4,
          orderable: true,
          searchable: true,
          classname: 'text-center',
          render: function (data, type, full, meta) {
            return (
              '<span class="fw-bold">'+ Helpers.formatAmount(full.approval_fees) + ' ' + currency +'</span>'
            );
          }
        },
        {
          targets: 5,
          orderable: true,
          searchable: true,
          render: function (data, type, full, meta) {
            let unit = window.variables.messages.unit;

            return (
              '<span class="fw-bold">'+ Helpers.formatAmount(full.sticker_fees) + ' ' + currency + ' / ' + unit +'</span>'
            );
          }
        },
        {
          targets: 6,
          orderable: true,
          searchable: true,
          render: function (data, type, full, meta) {
            let utcDate = moment.utc(full.created_at);
            return moment(utcDate).local().format(window.variables.messages.js_date_format);
          }
        },
        {
          targets: 7,
          orderable: false,
          searchable: false,
          className: 'dt-right',
          render: function (data, type, full, meta) {
            let deleteMessage = window.variables.messages.delete_equipment_sub_category_pricing_confirmation;
            deleteMessage = deleteMessage.replace(/name_/gi, Helpers.ucWords(full.equipment_sub_category.label));
            let deleteLabel = window.variables.messages.delete;

            let actionWrapper =  $('<div class=""></div>');

            let editAction =  $(
              '<button type="button" data-bs-target="'+ full.id +'" data-bs-pricingtype="'+ full.pricing_type +'" type="button" class="btn btn-icon btn-label-primary btn-sm ms-2 btn-edit">' +
              '<span class="tf-icons bx bx-edit"></span>' +
              '</a>'
            );

            let trashDropContent = ('' +
              '<div class="dropdown-menu dropdown-menu-end w-px-250">' +
              '<div class="pt-2 px-3">' +
              '<span class="fs-6 text-wrap">' + deleteMessage + '</span>' +
              '</div>' +
              '<div class="dropdown-divider"></div>' +
              '<div class="pt-1 pb-2 px-3">' +
              '<button type="button" class="btn btn-sm btn-danger d-block btn-delete" data-bs-target="'+ full.id +'" data-bs-pricingtype="'+ full.pricing_type +'" data-bs-subcategory="'+ full.equipment_sub_category.id +'">'+ deleteLabel +'</button>' +
              '</div>' +
              '</div>' +
              ''
            );

            let deleteAction =  $(
              '<div class="btn-group dropstart">' +
              '<button type="button" class="btn btn-icon btn-label-warning btn-sm ms-2 btn-trash" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' +
              '<span class="tf-icons bx bx-trash"></span>' +
              '</button>' +
              '' + trashDropContent +
              '</div>'
            );

            actionWrapper.append(editAction);
            actionWrapper.append(deleteAction);

            return actionWrapper.html()
          }
        },
      ],
      dom:
        '<"row mx-2"' +
        '<"col-md-2"<"me-3"l>>' +
        '<"col-md-10"<"dt-action-buttons text-xl-end text-lg-start text-md-end text-start d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>>' +
        '>t' +
        '<"row mx-2"' +
        '<"col-sm-12 col-md-6"i>' +
        '<"col-sm-12 col-md-6"p>' +
        '>',
      buttons: [
        {
          text: '<span class="">'+ addPricingLabel +'</span>',
          className: 'add-new btn btn-primary ms-0 ms-md-3',
          action: function(e, dt, node, config) {
            actionIsEdition = false;
            addPricingModal.find('button#btnAdd').html(window.variables.messages.save);
            addPricingModal.modal('show');
          },
          attr: {
            'id': 'addApprovalPricing'
          }
        },
      ],
      initComplete: function (settings, json) {
        $('.dataTables_length').find('label').addClass('ms-4 ms-md-0');
        $('.dataTables_filter .form-control').removeClass('form-control-sm').attr('placeholder', window.variables.messages.search + " ...");
        $('.dataTables_length .form-select').removeClass('form-select-sm');
      }
    };
  }

  function datatableActionEvent() {
    let tbody = $('#pricingDataTable tbody');

    tbody.on('click', 'td button.btn-delete', function (event) {
      event.preventDefault();
      let button = $(this);

      let url = window.variables.routes.delete_approval_pricing;
      url = url.replace(/:id_/gi, button.attr('data-bs-subcategory'));
      url = url.replace(/q_/gi, button.attr('data-bs-target'));

      $.ajax({
        url: url,
        type: "DELETE",
        dataType: "json",
        data: {
          "_token": window.variables.constants.csrf_token,
        },
        beforeSend: function () {
          handleOverlayLoading(false);
          Helpers.handleAlertVisibility(globalAlert, false);
        },
        complete: function () {
          handleOverlayLoading(true);
        },
        error: function () {
          Helpers.handleAlertVisibility(
            globalAlert,
            true,
            'alert-danger',
            window.variables.messages.unable_to_delete_configuration,
          );
        },
        success: function (result) {
          if (result.code === 100) {
            Helpers.handleAlertVisibility(globalAlert, true, 'alert-success ', result.message);
            // Remove from original data
            let sResult = findDataTablePricing({
              id: parseInt(button.attr('data-bs-target')),
              pricing_type: button.attr('data-bs-target')
            });
            if(sResult) {
              pricingData.splice(sResult.index, 1);
            }
            // Delete row data table ...
            dtBasic.row(button.parents('tr')).remove().draw();
          } else {
            Helpers.handleAlertVisibility(globalAlert, true, 'alert-danger', result.message);
          }
        }
      });
    });

    tbody.on('click', 'td button.btn-edit', function (event) {
      event.preventDefault();

      buildModalForEdition({
        id: $(this).attr('data-bs-target'),
        pricing_type: $(this).attr('data-bs-pricingtype'),
      });
    });
  }

  function initSelect2() {
    // Select2
    let select2 = $('.select2');
    // Select2 API Key
    if (select2.length) {
      select2.each(function () {
        let $this = $(this);
        $this.wrap('<div class="position-relative"></div>');
        $this.select2({
          dropdownParent: $this.parent()
        });
      });
    }
  }

  function addOrUpdatePricingAction() {
    if(! actionIsEdition) {
      let jsonData = JSON.parse(Helpers.convertFormToJSON(addPricingModalForm[0]));
      Object.keys(masks).forEach(function(key) {
        jsonData[key] = masks[key].getRawValue();
      });

      $.ajax({
        url: addPricingModalForm.attr('action'),
        type: "POST",
        dataType : 'json',
        data: jsonData,
        beforeSend: function () {
          resetInputErrors();
          Helpers.handleAlertVisibility(globalAlert, false);
          addPricingModal.find('.request-spinner').removeClass('d-none');
          addPricingModal.find('.actions-wrapper').addClass('d-none');
          addPricingModal.find('button.btn-close').addClass('d-none');
        },
        complete: function () {
          addPricingModal.find('.request-spinner').addClass('d-none');
          addPricingModal.find('.actions-wrapper').removeClass('d-none');
          addPricingModal.find('button.btn-close').removeClass('d-none');
        },
        error: function () {
          Helpers.customInputErrorsValidation({
            subcategory: window.variables.messages.unable_to_add_configuration,
          }, addPricingModal);
        },
        success: function (result) {
          switch(result.code){
            case 100:
              resetInputs();
              addDataTableRow(result.data);

              // Close modal
              addPricingModal.modal('hide');
              Helpers.handleAlertVisibility(globalAlert, true, 'alert-success ', result.message);
              break;
            case 150:
              Helpers.customInputErrorsValidation(result.data, addPricingModal);
              break;
            default:
              Helpers.customInputErrorsValidation({
                subcategory: result.message,
              }, addPricingModal);
              break;
          }
        },
      });
    } else {
      let jsonData = JSON.parse(Helpers.convertFormToJSON(addPricingModalForm[0]));
      Object.keys(masks).forEach(function(key) {
        jsonData[key] = masks[key].getRawValue();
      });

      // Edition ...
      let url = window.variables.routes.edit_approval_pricing;
      url = url.replace(/id_/gi, currentPricing.id).replace(/type_/gi, currentPricing.equipment_sub_category.approval_pricing_type);

      $.ajax({
        url: url,
        type: "POST",
        dataType : 'json',
        data: jsonData,
        beforeSend: function () {
          resetInputErrors();
          Helpers.handleAlertVisibility(globalAlert, false);
          addPricingModal.find('.request-spinner').removeClass('d-none');
          addPricingModal.find('.actions-wrapper').addClass('d-none');
          addPricingModal.find('button.btn-close').addClass('d-none');
        },
        complete: function () {
          addPricingModal.find('.request-spinner').addClass('d-none');
          addPricingModal.find('.actions-wrapper').removeClass('d-none');
          addPricingModal.find('button.btn-close').removeClass('d-none');
        },
        error: function () {
          Helpers.customInputErrorsValidation({
            subcategory: window.variables.messages.unable_to_update_configuration,
          }, addPricingModal);
        },
        success: function (result) {
          switch(result.code){
            case 100:
              // Close modal
              addPricingModal.modal('hide');
              // Reset inputs
              resetInputs();
              // SHow success message
              Helpers.handleAlertVisibility(globalAlert, true, 'alert-success ', result.message);
              // Refresh data table
              let sResult = findDataTablePricing({
                id: result.data.id,
                pricing_type: result.data.pricing_type,
              });

              if(sResult) {
                pricingData[sResult.index] = result.data;
                dtBasic.clear();
                dtBasic = dtBasic.rows.add(pricingData);
                dtBasic.draw(false);
              }
              break;
            case 150:
              Helpers.customInputErrorsValidation(result.data, addPricingModal);
              break;
            default:
              Helpers.customInputErrorsValidation({
                subcategory: result.message,
              }, addPricingModal);
              break;
          }
        },
      });
    }
  }

  function resetInputErrors() {
    $('.error-helper').addClass('d-none').html('');
    $('input').removeClass('is-invalid').removeClass('border-danger');
    $('select').removeClass('is-invalid').removeClass('border-danger');
  }

  function addDataTableRow(row) {
    pricingData.push(row);
    dtBasic = datatable.DataTable().row.add(row).draw(false);
  }

  function resetInputs() {
    addPricingModalForm.trigger('reset');
    initInputMask();
    $('#subcategory').val('').trigger('change');
    addPricingModal.find('#minMaxWrapper').removeClass('d-none');
  }

  function initInputMask() {
    $('.numeral-mask').toArray().forEach(function(field) {
      masks[$(field).attr('name')] = new Cleave(field, {
        numeral: true,
        numeralDecimalMark: '.',
        delimiter: ' ',
        numeralThousandsGroupStyle: 'thousand'
      });
    });
  }

  function findDataTablePricing(element) {
    let result = null;

    for(let i = 0; i < pricingData.length; i++) {
      if(pricingData[i].id === element.id && parseInt(element.pricing_type) === parseInt(pricingData[i].pricing_type)) {
        result = {
          index: i
        }
      }
    }

    return result;
  }

  function buildModalForEdition(checkElement) {
    pricingData.forEach(function(el) {
      console.dir(el)
      if(el.id === parseInt(checkElement.id) && parseInt(checkElement.pricing_type) === parseInt(el.pricing_type)) {
        currentPricing = el;
      }
    });

    if(currentPricing) {
      actionIsEdition = true;
      addPricingModal.find('button#btnAdd').html(window.variables.messages.save_changes);
      let minMaxWrapper = addPricingModal.find('#minMaxWrapper');

      if(parseInt(currentPricing.pricing_type) === 0) {
        minMaxWrapper.removeClass('d-none');
        addPricingModal.find('input.min_ps').val(currentPricing.min);
        addPricingModal.find('input.max_ps').val(currentPricing.max);
      } else {
        minMaxWrapper.addClass('d-none');
      }

      addPricingModal.find('select.subcategory').val(currentPricing.equipment_sub_category.id);
      addPricingModal.find('select.subcategory').trigger('change');
      addPricingModal.find('input.study_fees').val(currentPricing.study_fees);
      addPricingModal.find('input.approval_fees').val(currentPricing.approval_fees);
      addPricingModal.find('input.sticker_fees').val(currentPricing.sticker_fees);

      // Show modal ...
      addPricingModal.modal('show');
    }
  }

  function handleOverlayLoading(isVisible) {
    let deletionLabel = window.variables.messages.deletion;
    let blockUi = $('.light-overlay');
    let localSpinner = `
          <div class="local-spinner pt-2">
            <div class="spinner-border spinner-border-sm text-light" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <div class="d-inline-block ps-3">${deletionLabel} ...</div>
          </div>
        `;

    if(isVisible) {
      dataTableLength.html(dataLengthContent);
      blockUi.removeClass('show');
    } else {
      dataTableLength.html(localSpinner);
      blockUi.addClass('show');
    }
  }
});
