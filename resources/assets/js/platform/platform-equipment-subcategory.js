'use strict';

$(function () {
  let currentSubCategory = null;
  let subCategoriesData = null;
  let datatable = $('#subCategoriesDataTable'), dtBasic;
  let pricingTypeColors = null;
  let actionIsEdition = false;
  let globalAlert = $('#globalAlert');
  let addSubCategoryModal = $('#addSubCategoryModal');
  let addSubCategoryModalForm = $('#addSubCategoryModalForm');

  let dataTableColumns = [
    { data: '' },
    { data: '' },
    { data: 'code' },
    { data: '' },
    { data: '' },
    { data: 'created_at' },
    { data: 'actions' },
  ];
  let prefixMask = document.querySelector('.prefix-mask');
  let dataTableLength = null;
  let dataLengthContent = null;

  // Ajax main ...
  loadInitialDataPage();
  initInputMask();

  // Events handler
  $('#globalAlert .btn-close').click(function (event) {
    event.preventDefault();
    Helpers.handleAlertVisibility(globalAlert, false);
  });

  // Click on .print-sub-categories button
  $("body").on("click", ".print-sub-categories", function(event) {
    event.preventDefault();
    let $this = $(this);
    Loader.setLoading($this);
    printJS(window.variables.routes.print_subcategories);
    setTimeout(function() {
      Loader.removeLoading($this);
          }, 1000);
  });

  $('#addSubCategoryModal .btn-close, #addSubCategoryModal #btnCancel').click(function (event) {
    event.preventDefault();
    resetInputs();
    resetInputErrors();
    addSubCategoryModal.modal('hide');
  });

  addSubCategoryModal.find('form').submit(function (event) {
    event.preventDefault();
  });

  addSubCategoryModal.find('button#btnAdd').click(function (event) {
    event.preventDefault();
    addOrUpdateSubCategoryAction();
  });

  // Others methods ...
  function loadInitialDataPage() {
    $.ajax({
      url: window.variables.routes.all_subcategories,
      ataType: "json",
      beforeSend: function () {
        $('.global-spinner').removeClass('d-none');
      },
      complete: function () {
        $('.global-spinner').addClass('d-none');
      },
      error: function () {},
      success: function (result) {
        if(result.code === 100) {
          subCategoriesData = result.data;

          initMainDataTable();
          initSelect2();
          $('#pageWrapper').removeClass('d-none');
        } else {}
      },
    });
  }

  function initMainDataTable() {
    if (datatable.length) {
      pricingTypeColors = computePricingTypeColor();
      dtBasic = datatable.DataTable(dataTableOption(subCategoriesData));
      datatableActionEvent();

      dataTableLength = $('.dataTables_length');
      dataLengthContent = dataTableLength.html();
    }
  }

  function formatTableRowDetails(row) {
    let approvalFeesContent = null;
    let code = row.code !== null ? row.code.toUpperCase() : '--';
    let created = moment(moment.utc(row.created_at)).local().format(window.variables.messages.js_date_format);
    let updated = moment(moment.utc(row.updated_at)).local().format(window.variables.messages.js_date_format);

    if(parseInt(row.approval_pricing_type) === parseInt(window.variables.constants.approval_pricing_types['fixed'])) {
      let fees = row.approval_pricing_fees;
      if(fees !== null) {
        approvalFeesContent = $(
          '<div class="row mt-4 mb-2">' +
          '<div class="col-md-3"><span class="text-muted">'+ window.variables.messages.approval_pricing_fees_and_study_fees.toUpperCase() +'<span></div>' +
          '<div class="col-md-9"></div>' +
          '</div>' +
          '<div class="row mb-2">' +
          '<div class="col-md-3"><span class="text-muted">'+ window.variables.messages.study_fees +'<span></div>' +
          '<div class="col-md-9"><span class="">'+ Helpers.formatAmount(fees.study_fees) + ' ' + window.variables.config.default_currency +'<span></div>' +
          '</div>' +
          '<div class="row mb-2">' +
          '<div class="col-md-3"><span class="text-muted">'+ window.variables.messages.approval_fees +'<span></div>' +
          '<div class="col-md-9"><span class="">'+ Helpers.formatAmount(fees.approval_fees) + ' ' + window.variables.config.default_currency +'<span></div>' +
          '</div>' +
          '<div class="row mb-4">' +
          '<div class="col-md-3"><span class="text-muted">'+ window.variables.messages.sticker_fees +'<span></div>' +
          '<div class="col-md-9"><span class="">'+ Helpers.formatAmount(fees.sticker_fees) + ' ' + window.variables.config.default_currency +'<span></div>' +
          '</div>'
        );
      }
    }

    if(parseInt(row.approval_pricing_type) === parseInt(window.variables.constants.approval_pricing_types['perPs'])) {
      let fees = row.approval_pricing_fees_per_ps;
      if(fees !== null && fees.length > 0) {
        approvalFeesContent = $(
          '<div class="row mt-4 mb-2">' +
          '<div class="col-md-3"><div class="text-muted mb-2">'+ window.variables.messages.approval_pricing_fees_and_study_fees.toUpperCase() +'<span></div>' +
          '<div class="col-md-9"></div>' +
          '</div>'
        );

        fees.forEach(function(fee) {
          approvalFeesContent.append($(
            '<div class="row mb-2">' +
            '<div class="col-md-3 fw-bold d-flex align-items-center">'+ Helpers.formatAmount(fee.min) + ' - ' + Helpers.formatAmount(fee.max) + ' ' + window.variables.config.default_ps_unit + '</div>' +
            '<div class="col-md-9">' +
            '<div class="row">' +
            '<div class="col-md-2 px-0">' +
            '<p class="m-0 p-0 mb-1 text-muted">'+ window.variables.messages.study_fees +'</p>' +
            '<span>'+ Helpers.formatAmount(fee.study_fees) + ' ' + window.variables.config.default_currency +'</span>' +
            '</div>' +
            '<div class="col-md-2 px-0">' +
            '<p class="m-0 p-0 mb-1 text-muted">'+ window.variables.messages.approval_fees +'</p>' +
            '<span>'+ Helpers.formatAmount(fee.approval_fees) + ' ' + window.variables.config.default_currency +'</span>' +
            '</div>' +
            '<div class="col-md-2 px-0">' +
            '<p class="m-0 p-0 mb-1 text-muted">'+ window.variables.messages.approval_fees +'</p>' +
            '<span>'+ Helpers.formatAmount(fee.sticker_fees) + ' ' + window.variables.config.default_currency + ' / '  + window.variables.messages.unit + '</span>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>'
          ));
        });
      }
    }

    let rootWrapper = $('<div><div id="wrapper" style="margin-left: 4rem;"></div></div>');

    rootWrapper.find('div#wrapper').append(
      $('<div class="row mb-2">' +
        '<div class="col-md-3"><span class="text-muted">'+ window.variables.messages.full_subcategory_name +'<span></div>' +
        '<div class="col-md-9"><span class="fw-bold fs-5">'+ Helpers.ucWords(row.label) +'<span></div>' +
        '</div>'
      )
    );
    rootWrapper.find('div#wrapper').append($(
      '<div class="row mb-2">' +
      '<div class="col-md-3"><span class="text-muted">'+ window.variables.messages.category_code +'<span></div>' +
      '<div class="col-md-9"><span class="">'+ code +'<span></div>' +
      '</div>'
    ));
    rootWrapper.find('div#wrapper').append($(
      '<div class="row mb-2">' +
      '<div class="col-md-3"><span class="text-muted">'+ window.variables.messages.approval_study_pricing_type +'<span></div>' +
      '<div class="col-md-9"><span class="badge ' + pricingTypeColors[row.approval_pricing_type_message.toLowerCase()] + '">'+ row.approval_pricing_type_message.toUpperCase() +'</span></div>' +
      '</div>'
    ));
    rootWrapper.find('div#wrapper').append(($(
      '<div class="row mb-2">' +
      '<div class="col-md-3"><span class="text-muted">'+ window.variables.messages.equipment_category +'<span></div>' +
      '<div class="col-md-9"><span class="">'+ Helpers.ucWords(row.equipment_category.label) +'<span></div>' +
      '</div>'
    )));
    if(approvalFeesContent) rootWrapper.find('div#wrapper').append(approvalFeesContent);
    rootWrapper.find('div#wrapper').append($(
      '<div class="row mb-2">' +
      '<div class="col-md-3"><span class="text-muted">'+ window.variables.messages.created_at +'<span></div>' +
      '<div class="col-md-9"><span class="">'+ created +'<span></div>' +
      '</div>'
    ));
    rootWrapper.find('div#wrapper').append($(
      '<div class="row">' +
      '<div class="col-md-3"><span class="text-muted">'+ window.variables.messages.updated_at +'<span></div>' +
      '<div class="col-md-9"><span class="">'+ updated +'<span></div>' +
      '</div>'
    ));

    return rootWrapper.html();
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
            return '#' + (meta.row + 1);
          }
        },
        {
          targets: 1,
          orderable: true,
          searchable: true,
          render: function (data, type, full, meta) {
            return (
              '<div class="d-flex align-items-start flex-nowrap">' +
              '<button type="button" class="btn rounded-pill btn-icon btn-label-secondary btn-xs me-3 btn-expand-details" style="margin-top: 0.1rem;"><span class="tf-icons bx bx-plus fs-6 d-inline-block" style="padding-top: 1px;"></span></button>' +
              '<span class="w-px-200 text-wrap" style="">'+ Helpers.ucWords(full.label) +'</span>' +
              '</div>'
            );
          },
        },
        {
          targets: 3,
          orderable: true,
          searchable: true,
          render: function (data, type, full, meta) {
            return '<span class="badge ' + pricingTypeColors[full.approval_pricing_type_message.toLowerCase()] + '">'+ full.approval_pricing_type_message.toUpperCase() +'</span>';
          }
        },
        {
          targets: 4,
          orderable: true,
          searchable: true,
          render: function (data, type, full, meta) {
            return (
              '<div class="w-px-250 text-wrap">'+ Helpers.ucWords(full.equipment_category.label) +'</div>'
            );
          }
        },
        {
          targets: 5,
          orderable: true,
          searchable: true,
          render: function (data, type, full, meta) {
            let utcDate = moment.utc(full.created_at);
            return moment(utcDate).local().format(window.variables.messages.js_date_format);
          }
        },
        {
          targets: 6,
          orderable: false,
          searchable: false,
          className: 'dt-right',
          render: function (data, type, full, meta) {
            var arrayVal = window.variables.constants.for_bidden_codes;
            let deleteMessage = window.variables.messages.delete_equipment_sub_category_confirmation;
            deleteMessage = deleteMessage.replace(/name_/gi, Helpers.ucWords(full.label));

            let actionWrapper =  $('<div class=""></div>');
            let editAction =  $(
              '<button type="button" data-bs-target="'+ full.id +'" type="button" class="btn btn-icon btn-label-primary btn-sm ms-2 btn-edit"><span class="tf-icons bx bx-edit"></span></a>'
            );

            let trashDropContent = ('' +
              '<div class="dropdown-menu dropdown-menu-end w-px-250">' +
              '<div class="pt-2 px-3">' +
              '<span class="fs-6 text-wrap">' + deleteMessage + '</span>' +
              '</div>' +
              '<div class="dropdown-divider"></div>' +
              '<div class="pt-1 pb-2 px-3">' +
              '<button type="button" class="btn btn-sm btn-danger d-block btn-delete" data-bs-target="'+ full.id +'">'+ window.variables.messages.delete +'</button>' +
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

            if(jQuery.inArray(full.code, arrayVal ) === -1){
              actionWrapper.append(editAction);
              actionWrapper.append(deleteAction);
            } else {
              return null;
            }

            return actionWrapper.html();
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
          text: '<span class="">'+ window.variables.messages.add_sub_category +'</span>',
          className: 'add-new btn btn-primary ms-0 ms-md-3',
          action: function(e, dt, node, config) {
            actionIsEdition = false;
            addSubCategoryModal.find('button#btnAdd').html(window.variables.messages.save);
            addSubCategoryModal.modal('show');
          },
          attr: {
            'id': 'addSubCategory'
          }
        },
        {
          text: "<i class='bx bxs-file-pdf me-0 me-sm-2'></i><span class='d-none d-lg-inline-block'>"+window.variables.messages.print_sub_categories+"</span>",
          className: 'print-sub-categories btn btn-secondary ms-0 ms-md-2',
        }
      ],
      initComplete: function (settings, json) {
        $('.dataTables_length').find('label').addClass('ms-4 ms-md-0');
        $('.dataTables_filter .form-control').removeClass('form-control-sm').attr('placeholder', window.variables.messages.search + " ...");
        $('.dataTables_length .form-select').removeClass('form-select-sm');
      },
      createdRow: function(row, data, _) {
        let forbiddenVal = window.variables.constants.for_bidden_codes;
        let forbidden = jQuery.inArray(data.code, forbiddenVal);
        if (forbidden >= 0) {
            $(row).css("background", color);
            $(row).css("color", "white");
        }
      },
    };
  }

  function datatableActionEvent() {
    let tbody = $('#subCategoriesDataTable tbody');

    tbody.on('click', 'td button.btn-delete', function (event) {
      event.preventDefault();
      let button = $(this);

      let url = window.variables.routes.delete_equipment_subcategory;
      url = url.replace(/:id_/gi, button.attr('data-bs-target'));

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
            window.variables.messages.unable_to_delete_equipment_subcategory
          );
        },
        success: function (result) {
          if (result.code === 100) {
            Helpers.handleAlertVisibility(globalAlert, true, 'alert-success ', result.message);
            // Remove from original data
            let sResult = findCategoryById(parseInt(button.attr('data-bs-target')));
            if(sResult) {
              subCategoriesData.splice(sResult.index, 1);
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
      buildModalForEdition($(this).attr('data-bs-target'));
    });

    tbody.on('click', 'td button.btn-expand-details', function (event) {
      event.preventDefault();
      let button = $(this);

      let tr = $(this).closest('tr');
      let row = dtBasic.row(tr);

      if (row.child.isShown()) {
        // This row is already open - close it
        row.child.hide();
        tr.removeClass('shown');
        button.removeClass('btn-label-primary')
        button.addClass('btn-label-secondary');
        button.find('span').removeClass('bx-minus');
        button.find('span').addClass('bx-plus');
      } else {
        // Open this row
        row.child(formatTableRowDetails(row.data())).show();
        tr.addClass('shown');
        button.addClass('btn-label-primary')
        button.removeClass('btn-label-secondary');
        button.find('span').removeClass('bx-plus');
        button.find('span').addClass('bx-minus');
      }
    });
  }

  function computePricingTypeColor() {
    let labels = {};
    let bgColors = [
      'bg-label-primary',
      'bg-label-secondary',
      'bg-label-dribbble',
      'bg-label-dark',
      'bg-label-gray',
      'bg-label-facebook',
      'bg-label-danger',
      'bg-label-info',
      'bg-label-slack',
      'bg-label-reddit',
      'bg-label-success',
      'bg-label-youtube',
      'bg-label-twitter',
      'bg-label-vimeo',
    ];

    subCategoriesData.forEach(function(sub) {
      let label = sub.approval_pricing_type_message.toLowerCase();
      let labelSize = Object.keys(labels).length;

      if(Object.keys(labels).indexOf(label) === -1){
        labels[label] = labelSize < bgColors.length ? bgColors[labelSize] : 'bg-label-primary';
      }
    });

    return labels;
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

  function addOrUpdateSubCategoryAction() {
    if(! actionIsEdition) {
      let jsonData = JSON.parse(Helpers.convertFormToJSON(addSubCategoryModalForm[0]));
      if(jsonData['code'] && jsonData['code'].length > 0) {
        let prefix = window.variables.config.equipment_sub_category_prefix;

        if(prefix === jsonData['code']) {
          jsonData['code'] = null;
        }
      }

      $.ajax({
        url: addSubCategoryModalForm.attr('action'),
        type: "POST",
        dataType : 'json',
        data: jsonData,
        beforeSend: function () {
          resetInputErrors();
          Helpers.handleAlertVisibility(globalAlert, false);
          addSubCategoryModal.find('.request-spinner').removeClass('d-none');
          addSubCategoryModal.find('.actions-wrapper').addClass('d-none');
          addSubCategoryModal.find('button.btn-close').addClass('d-none');
        },
        complete: function () {
          addSubCategoryModal.find('.request-spinner').addClass('d-none');
          addSubCategoryModal.find('.actions-wrapper').removeClass('d-none');
          addSubCategoryModal.find('button.btn-close').removeClass('d-none');
        },
        error: function () {
          Helpers.customInputErrorsValidation({
            label: window.variables.messages.unable_to_add_equipment_sub_category,
          }, addSubCategoryModal);
        },
        success: function (result) {
          switch(result.code){
            case 100:
              resetInputs();
              resetTableDetails();
              addDataTableRow(result.data);
              // Close modal
              addSubCategoryModal.modal('hide');
              Helpers.handleAlertVisibility(globalAlert, true, 'alert-success ', result.message);
              break;
            case 150:
              Helpers.customInputErrorsValidation(result.data, addSubCategoryModal);
              break;
            default:
              Helpers.customInputErrorsValidation({
                label: result.message,
              }, addSubCategoryModal);
              break;
          }
        },
      });
    } else {
      // Edition ...
      let url = window.variables.routes.edit_subcategories;
      url = url.replace(/id_/gi, currentSubCategory.id);

      $.ajax({
        url: url,
        type: "POST",
        dataType : 'json',
        data: JSON.parse(Helpers.convertFormToJSON(addSubCategoryModalForm[0])),
        beforeSend: function () {
          resetInputErrors();
          Helpers.handleAlertVisibility(globalAlert, false);
          addSubCategoryModal.find('.request-spinner').removeClass('d-none');
          addSubCategoryModal.find('.actions-wrapper').addClass('d-none');
          addSubCategoryModal.find('button.btn-close').addClass('d-none');
        },
        complete: function () {
          addSubCategoryModal.find('.request-spinner').addClass('d-none');
          addSubCategoryModal.find('.actions-wrapper').removeClass('d-none');
          addSubCategoryModal.find('button.btn-close').removeClass('d-none');
        },
        error: function () {
          Helpers.customInputErrorsValidation({
            label: window.variables.messages.unable_to_update_equipment_subcategory,
          }, addSubCategoryModal);
        },
        success: function (result) {
          switch(result.code){
            case 100:
              // Close modal
              addSubCategoryModal.modal('hide');
              // Reset inputs
              resetInputs();
              resetTableDetails();
              // SHow success message
              Helpers.handleAlertVisibility(globalAlert, true, 'alert-success ', result.message);
              // Refresh data table
              let sResult = findDataTableById(parseInt(result.data.id));

              if(sResult) {
                subCategoriesData[sResult.index] = result.data;
                dtBasic.clear();
                dtBasic = dtBasic.rows.add(subCategoriesData);
                dtBasic.draw(false);
              }
              break;
            case 150:
              Helpers.customInputErrorsValidation(result.data, addSubCategoryModal);
              break;
            default:
              Helpers.customInputErrorsValidation({
                label: result.message,
              }, addSubCategoryModal);
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
    subCategoriesData.push(row);
    dtBasic = datatable.DataTable().row.add(row).draw(false);
  }

  function resetInputs() {
    addSubCategoryModalForm.trigger('reset');
    initInputMask();
    $('#category').val('').trigger('change');
  }

  function initInputMask() {
    if (prefixMask) {
      new Cleave(prefixMask, {
        prefix: window.variables.config.equipment_sub_category_prefix,
        uppercase: true
      });
    }
  }

  function findDataTableById(id) {
    let result = null;

    for(let i = 0; i < subCategoriesData.length; i++) {
      if(subCategoriesData[i].id === id) {
        result = {
          index: i
        }
      }
    }

    return result;
  }

  function buildModalForEdition(id) {
    subCategoriesData.forEach(function(sub) {
      if(sub.id === parseInt(id)) {
        currentSubCategory = sub;
      }
    });

    if(currentSubCategory) {
      actionIsEdition = true;
      addSubCategoryModal.find('button#btnAdd').html(window.variables.messages.save_changes);

      addSubCategoryModal.find('select.category').val(currentSubCategory.equipment_category.id);
      addSubCategoryModal.find('select.category').trigger('change');
      addSubCategoryModal.find('input.label').val(currentSubCategory.label);
      if(currentSubCategory.code && currentSubCategory.code.length > 0) {
        addSubCategoryModal.find('input.code').val(currentSubCategory.code);
      } else {
        addSubCategoryModal.find('input.code').val(window.variables.config.equipment_sub_category_prefix);
      }
      addSubCategoryModal
        .find("input[name=approval_pricing_type][value=" + currentSubCategory.approval_pricing_type + "]")
        .prop('checked', true);

      // Show modal ...
      addSubCategoryModal.modal('show');
    }
  }

  function handleOverlayLoading(isVisible) {
    let blockUi = $('.light-overlay');
    let localSpinner = `
          <div class="local-spinner pt-2">
            <div class="spinner-border spinner-border-sm text-light" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <div class="d-inline-block ps-3">${window.variables.messages.deletion} ...</div>
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

  function findCategoryById(id) {
    let result = null;

    for(let i = 0; i < subCategoriesData.length; i++) {
      if(subCategoriesData[i].id === id) {
        result = {
          index: i
        }
      }
    }

    return result;
  }

  function resetTableDetails() {
    $('#subCategoriesDataTable tbody').find('td button.btn-expand-details').toArray().forEach(function(el) {
      let tr = $(el).closest('tr');
      let row = dtBasic.row(tr);

      row.child.hide();
      tr.removeClass('shown');
    });
  }
});
