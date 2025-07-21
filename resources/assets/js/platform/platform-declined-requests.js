'use strict';

$(function () {
  let declinedRequestsTable = $('#declinedRequestsTable'), dtBasic;
  let requestTypes = $('#requestTypes');
  let requestFilters = $('#requestFilters');
  let requestsData = [];
  let startDateEl = $('.start_date');
  let endDateEl = $('.end_date');
  let formFilters = $('#formFilters');

  /** Init */

  loadInitialDataPage();
  initComponents();
  let allRequestTypeValues = requestFilters.find('option');
  filterRequestTypes(requestFilters, requestTypes.val(), allRequestTypeValues, 'data-bs-targettype');

  /** Event handler */

  requestTypes.on('change', function(event) {
    event.preventDefault();
    filterRequestTypes(requestFilters, requestTypes.val(), allRequestTypeValues, 'data-bs-targettype');
  });

  $('#btnApplyFilter').on('click', function (event) {
    event.preventDefault();
    computeSearchFilter();
  });

  $('#btnResetFilters').on('click', function (event) {
    event.preventDefault();

    formFilters.trigger('reset');
    requestTypes.val('').trigger('change');

    dtBasic.clear();
    dtBasic = dtBasic.rows.add(requestsData);
    dtBasic.draw(false);
  });

  /** Functions */

  function loadInitialDataPage() {
    $.ajax({
      url: window.variables.routes.declined_request,
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
          requestsData = result.data;
          initDataTable();

          $('.data-count').html(`(${Helpers.formatAmount(requestsData.length, 0).trim()})`);
          $('#pageWrapper').removeClass('d-none');
        } else {}
      },
    });
  }

  function initDataTable() {
    if (declinedRequestsTable.length > 0) {
      dtBasic = declinedRequestsTable.DataTable(
        dataTableOption(requestsData)
      );
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
      orderCellsTop: false,
      language: window.variables.messages.languages,
      columnDefs: [
        {
          targets: '_all',
          defaultContent: "--",
          className: '',
        },
        {
          targets: 0,
          render: function (data, type, full, meta) {
            return (
              '<div class="d-flex align-items-start flex-nowrap ps-1">' +
              '<span class="w-px-200 text-wrap" style="">'+ full.display_type +'</span>' +
              '</div>'
            );
          }
        },
        {
          targets: 1,
          orderable: false,
          sortable: false,
          searching: true,
          className: 'fw-semibold',
          render: function (data, type, full, meta) {
            return 'N°' + full.reference;
          }
        },
        {
          targets: 2,
          className: 'fw-semibold',
          render: function (data, type, full, meta) {
            return full.display_owner;
          }
        },
        {
          orderable: false,
          targets: 3,
          className: 'fw-semibold',
          render: function (data, type, full, meta) {
            return full.applicant_contact_phone;
          }
        },
        {
          orderable: false,
          targets: 4,
          className: 'fw-semibold',
          render: function (data, type, full, meta) {
            return full.applicant_contact_email;
          }
        },
        {
          targets: 5,
          render: function (data, type, full, meta) {
            let utcDate = moment.utc(full.created_at);
            return moment(utcDate).local().format(window.variables.messages.js_date_format);
          }
        },
        {
          targets: 6,
          render: function (data, type, full, meta) {
            let utcDate = moment.utc(full.declined_at);
            return moment(utcDate).local().format(window.variables.messages.js_date_format);
          }
        },
        {
          targets: 7,
          orderable: false,
          sortable: false,
          searching: false,
          render: function (data, type, full, meta) {
            return (
              '<div class="">' +
              '<span class="w-px-300 text-wrap" style="">'+ full.decline_reason != null ? full.decline_reason : '/' +'</span>' +
              '</div>'
            );
          }
        },
        {
          targets: 8,
          render: function (data, type, full, meta) {
            return full['processing_time'];
          }
        },
        {
          targets: 9,
          className: 'text-end',
          orderable: false,
          sortable: false,
          searching: false,
          render: function (data, type, full, meta) {
            let url = window.variables.routes.declined_request_details.replace(/id_/gi, full.id);

            let actionWrapper =  $('<div class=""></div>');
            let showAction =  $(
              '<a href="'+ url +'" target="_blank" class="btn btn-icon btn-label-secondary btn-sm ms-2"><span class="tf-icons bx bx-show"></span></a>'
            );
            actionWrapper.append(showAction);

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
          extend: 'collection',
          className: 'btn btn-label-primary dropdown-toggle me-2',
          text: '<i class="bx bx-export me-sm-2"></i> <span class="d-none d-sm-inline-block">Export</span>',
          buttons: [
            {
              extend: 'csv',
              charset: 'UTF-8',
              bom: true,
              text: '<i class="bx bx-file me-2" ></i>Excel',
              className: 'dropdown-item',
              exportOptions: {
                columns: [0, 1, 2, 3, 4, 5,6],
                format: {
                  body: function (inner, colDex, rowDex) {
                    if (inner.length <= 0) return inner;
                    let el = $.parseHTML(inner);
                    let result = '';

                    $.each(el, function (index, item) {
                      if (item.classList !== undefined && item.classList.contains('user-name')) {
                        result = result + item.lastChild.firstChild.textContent;
                        console.log(result);
                      } else if (item.innerText === undefined) {
                        result = result + item.textContent;
                      } else result = result + item.innerText;
                    });
                    return result.trim();
                  }
                }
              }
            },
          ]
        },
      ],
      initComplete: function (settings, json) {
        $('.dataTables_length').find('label').addClass('ms-4 ms-md-0');
        $('.dataTables_filter .form-control').removeClass('form-control-sm').attr(
          'placeholder',
          window.variables.messages.search + " ..."
        );
        $('.dataTables_length .form-select').removeClass('form-select-sm');
      }
    };
  }

  function initComponents() {
    let select2 = $('.select2');

    if (select2.length) {
      select2.each(function () {
        let $this = $(this);
        $this.wrap('<div class="position-relative"></div>');
        $this.select2({
          dropdownParent: $this.parent()
        });
      });
    }

    let date = new Date();
    let currYear = date.getFullYear();

    if (endDateEl.length > 0) {
      endDateEl.flatpickr({
        monthSelectorType: 'static',
        defaultDate: new Date(currYear, 12, 0)
      });
    }

    if (startDateEl.length > 0) {
      startDateEl.flatpickr({
        monthSelectorType: 'static',
        defaultDate: new Date(currYear, 0, 1)
      });
    }
  }

  function filterRequestTypes(selectEl, value, catalog, attr, selectorValue) {
    selectEl.find('option').remove();

    catalog.each(function(index) {
      if($(this).attr('value').length === 0 || (value && attr && $(this).attr(attr) === value)) {
        $(this).prop('selected', $(this).attr('value').length !== 0 && selectorValue === $(this).attr('value'));
        selectEl.append($(this));
      }
    });
    selectEl.trigger('change');
  }

  function buildFilters() {
    let jsonData = JSON.parse(Helpers.convertFormToJSON(formFilters[0]));

    if(requestTypes.val() === "1") { // Operating title
      jsonData['operating_title_type'] = requestFilters.val();
    }

    if(requestTypes.val() === '2') { // Approval
      jsonData['approval_type'] = requestFilters.val();
    }

    return jsonData;
  }

  function computeSearchFilter() {
    $.ajax({
      url: window.variables.routes.declined_request,
      type: "POST",
      dataType : 'json',
      data: buildFilters(),
      beforeSend: function () {
        $('.search-spinner').removeClass('d-none');
        $('.filter-actions').addClass('d-none');
      },
      complete: function () {
        $('.search-spinner').addClass('d-none');
        $('.filter-actions').removeClass('d-none');
      },
      error: function () {},
      success: function (result) {
        // if(result.code === 100) {
        //   requestsData = result.data;
        //
        //   $('.data-count').html(`(${Helpers.formatAmount(requestsData.length, 0).trim()})`);
        //   // Refresh data table
        //   dtBasic.clear();
        //   dtBasic = dtBasic.rows.add(requestsData);
        //   dtBasic.draw(false);
        // } else {}
      },
    });
  }
});
