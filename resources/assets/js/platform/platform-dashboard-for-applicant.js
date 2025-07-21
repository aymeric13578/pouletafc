'use strict';

$(function () {
  let opStatTable = $('#opStatTable'), opStatDtBasic;
  let renewalOpStatTable = $('#renewalOpStatTable'), renewalOpStatTableBasic;

  let apStatTable = $('#apStatTable'), apStatDtBasic;
  let renewalApStatTable = $('#renewalApStatTable'), renewalApStatDtBasic;

  let operatingTitleRequestPicker = $('#operatingTitleRequestPicker');
  let approvalRequestPicker = $('#approvalRequestPicker');
  let operatingTitleRequestPickerWrapper = $('#operatingTitleRequestPickerWrapper');
  let approvalRequestPickerWrapper = $('#approvalRequestPickerWrapper');
  let dateFormat = "YYYY/MM/DD";

  /** Init */

  intiDataTables();
  initDateRangePicker();

  /** Event handler */

  operatingTitleRequestPicker.on('apply.daterangepicker', function(ev, picker) {
    $(this).val(picker.startDate.format(dateFormat) + ' - ' + picker.endDate.format(dateFormat));
    $(this).trigger('change');
  });

  approvalRequestPicker.on('apply.daterangepicker', function(ev, picker) {
    $(this).val(picker.startDate.format(dateFormat) + ' - ' + picker.endDate.format(dateFormat));
    $(this).trigger('change');
  });

  operatingTitleRequestPicker.on('change', function(event){
    event.preventDefault();
    let rangeDate = $(this).val().split('-');

    $.ajax({
      url: window.variables.routes.operating_title_request_statistics_url,
      type: "POST",
      dataType: "json",
      data: {
        _token: window.variables.constants.csrf_token,
        start_date: rangeDate[0].trim(),
        end_date: rangeDate[1].trim(),
      },
      beforeSend: function () {
        operatingTitleRequestPickerWrapper.find('.overlay').removeClass('d-none');
        operatingTitleRequestPickerWrapper.find('.global-spinner').removeClass('d-none');
      },
      complete: function () {
        operatingTitleRequestPickerWrapper.find('.overlay').addClass('d-none');
        operatingTitleRequestPickerWrapper.find('.global-spinner').addClass('d-none');
      },
      error: function () {},
      success: function (result) {
        if(result.code === 100) {
          let total = 0;
          result.data['op_title_statistics_resume'].forEach((stat) => {
            total = total + (stat.op_type !== null ? stat.total : 0);
          });
          operatingTitleRequestPickerWrapper.find('span.new-op-statistic-resume').html(` (${total})`);

          // For renewal
          total = 0;
          result.data['renewal_op_title_statistics_resume'].forEach((stat) => {
            total = total + (stat.op_type !== null ? stat.total : 0);
          });
          operatingTitleRequestPickerWrapper.find('span.renewal-op-statistic-resume').html(` (${total})`);

          opStatDtBasic.destroy();
          opStatDtBasic = opStatTable.DataTable(
            operatingTitleRequestTableOptions(result.data['op_title_statistics_resume'])
          );
          opStatDtBasic.draw(false);

          // For renewal
          renewalOpStatTableBasic.destroy();
          renewalOpStatTableBasic = renewalOpStatTable.DataTable(
            operatingTitleRequestTableOptions(
              result.data['renewal_op_title_statistics_resume'],
              true,
            )
          );
          renewalOpStatTableBasic.draw(false);
        }
      }
    });
  });

  approvalRequestPicker.on('change', function(event){
    event.preventDefault();
    let rangeDate = $(this).val().split('-');

    $.ajax({
      url: window.variables.routes.approval_request_statistics_url,
      type: "POST",
      dataType: "json",
      data: {
        _token: window.variables.constants.csrf_token,
        start_date: rangeDate[0].trim(),
        end_date: rangeDate[1].trim(),
      },
      beforeSend: function () {
        approvalRequestPickerWrapper.find('.overlay').removeClass('d-none');
        approvalRequestPickerWrapper.find('.global-spinner').removeClass('d-none');
      },
      complete: function () {
        approvalRequestPickerWrapper.find('.overlay').addClass('d-none');
        approvalRequestPickerWrapper.find('.global-spinner').addClass('d-none');
      },
      error: function () {},
      success: function (result) {
        if(result.code === 100) {
          let total = 0;
          result.data['ap_statistics_resume'].forEach((stat) => {
            total = total + (stat.ap_type !== null ? stat.total : 0);
          });
          approvalRequestPickerWrapper.find('span.new-ap-statistic-resume').html(`(${total})`);

          // For renewal
          total = 0;
          result.data['renewal_ap_statistics_resume'].forEach((stat) => {
            total = total + (stat.ap_type !== null ? stat.total : 0);
          });
          approvalRequestPickerWrapper.find('span.renewal-ap-statistic-resume').html(`(${total})`);

          apStatDtBasic.destroy();
          apStatDtBasic = apStatTable.DataTable(
            approvalRequestTableOptions(result.data['ap_statistics_resume'])
          );
          apStatDtBasic.draw(false);

          // For renewal
          renewalApStatDtBasic.destroy();
          renewalApStatDtBasic = renewalApStatTable.DataTable(
            approvalRequestTableOptions(
              result.data['renewal_ap_statistics_resume'],
              true,
            )
          );
          renewalApStatDtBasic.draw(false);
        }
      }
    });
  });

  /** Utilities */

  function initDateRangePicker() {
    if (operatingTitleRequestPicker.length) {
      operatingTitleRequestPicker.daterangepicker({
        autoUpdateInput: false,
        defaultDate: ["", ""],
        locale: {
          format: dateFormat,
          separator: " - "
        },
      });
    }

    if (approvalRequestPicker.length) {
      approvalRequestPicker.daterangepicker({
        autoUpdateInput: false,
        defaultDate: ["", ""],
        locale: {
          format: dateFormat,
          separator: " - "
        },
      });
    }
  }

  function intiDataTables() {
    if (opStatTable.length) {
      opStatDtBasic = opStatTable.DataTable(dataTableOptions());
    }

    if (renewalOpStatTable.length) {
      renewalOpStatTableBasic = renewalOpStatTable.DataTable(dataTableOptions());
    }

    if (apStatTable.length) {
      apStatDtBasic = apStatTable.DataTable(dataTableOptions());
    }

    if (renewalApStatTable.length) {
      renewalApStatDtBasic = renewalApStatTable.DataTable(dataTableOptions());
    }
  }

  function dataTableOptions() {
    return {
      paging: true,
      ordering: true,
      info: true,
      displayLength: 5,
      searching: true,
      lengthChange: true,
      orderCellsTop: true,
      order: [[0, "asc"]],
      lengthMenu: [5, 10, 25, 50, 100],
      language: window.variables.messages.tables,
      columnDefs: [
        {
          targets: '_all',
          defaultContent: "--",
          className: ''
        },
        {
          targets: 4,
          searching: false,
          sortable: false,
        },
      ],
      initComplete: function (settings, json) {
        $('.dataTables_filter .form-control').removeClass('form-control-sm').attr('placeholder', window.variables.messages.search + " ...");
        $('.dataTables_length .form-select').removeClass('form-select-sm');
      }
    };
  }

  function operatingTitleRequestTableOptions(data, renewal) {
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
      lengthMenu: [5, 10, 25, 50, 100],
      language: window.variables.messages.tables,
      columnDefs: [
        {
          targets: '_all',
          defaultContent: "--",
          className: ''
        },
        {
          targets: 0,
          className: 'w-px-700 text-wrap',
          render: function (data, type, full, meta) {
            let content = `${full.op_designation} ${full.lc_designation ?? ''}`;
            return $('<p class="m-0 ps-1">'+ content +'</p>').html();
          }
        },
        {
          targets: 1,
          className: "text-center",
          render: function (data, type, full, meta) {
            if(full.waiting > 0) {
              return $('<p class="m-0">' +
                '<span class="badge bg-label-warning fs-6">' +
                '' + full.waiting + '' +
                '</span>' +
                '</p>'
              ).html();
            }

            return $('<p class="m-0">' +
              '<span class="badge bg-label-warning fs-6">' +
              '' + full.waiting + '' +
              '</span>' +
              '</p>'
            ).html();
          }
        },
        {
          targets: 2,
          className: "text-center",
          render: function (data, type, full, meta) {
            return $('<p class="m-0">' +
              '<span class="badge bg-label-success fs-6">' +
              '' + full.approved + '' +
              '</span>' +
              '</p>'
            ).html();
          }
        },
        {
          targets: 3,
          className: "text-center",
          render: function (data, type, full, meta) {
            return $('<p class="m-0">' +
              '<span class="badge bg-label-danger fs-6">' +
              '' + full.declined + '' +
              '</span>' +
              '</p>'
            ).html();
          }
        },

        {
          targets: 4,
          className: "text-center",
          render: function (data, type, full, meta) {
            return $('<p class="m-0">' +
              '<span class="badge bg-label-primary fs-6">' +
              '' + full.archived + '' +
              '</span>' +
              '</p>'
            ).html();
          }
        },
        {
          targets: 5,
          className: "text-center",
          render: function (data, type, full, meta) {
            return $('<p class="m-0">' +
              '<span class="badge bg-label-dribbble fs-6">' +
              '' + full.closed + '' +
              '</span>' +
              '</p>'
            ).html();
          }
        },

        {
          targets: 6,
          className: "text-center",
          render: function (data, type, full, meta) {
            return $('<p class="m-0">' +
              '<span class="badge bg-label-secondary fs-6">' +
              '' + full.total + '' +
              '</span>' +
              '</p>'
            ).html();
          }
        },
      ],
      initComplete: function (settings, json) {
        $('.dataTables_filter .form-control').removeClass('form-control-sm').attr('placeholder', window.variables.messages.search + " ...");
        $('.dataTables_length .form-select').removeClass('form-select-sm');
      }
    };
  }

  function approvalRequestTableOptions(data, renewal =false) {
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
      lengthMenu: [5, 10, 25, 50, 100],
      language: window.variables.messages.tables,
      columnDefs: [
        {
          targets: '_all',
          defaultContent: "--",
          className: ''
        },
        {
          targets: 0,
          className: 'w-px-700 text-wrap',
          render: function (data, type, full, meta) {
            let content = `${full.ap_designation} ${full.lc_designation ?? ''}`;
            return $('<p class="m-0 ps-1">'+ content +'</p>').html();
          }
        },
        {
          targets: 1,
          className: "text-center",
          render: function (data, type, full, meta) {
            if(full.waiting > 0) {
              return $('<p class="m-0">' +
                '<span class="badge bg-label-warning fs-6">' +
                '' + full.waiting + '' +
                '</span>' +
                '</p>'
              ).html();
            }

            return $('<p class="m-0">' +
              '<span class="badge bg-label-warning fs-6">' +
              '' + full.waiting + '' +
              '<span>' +
              '</p>'
            ).html();
          }
        },
        {
          targets: 2,
          className: "text-center",
          render: function (data, type, full, meta) {
            return $('<p class="m-0">' +
              '<span class="badge bg-label-success fs-6">' +
              '' + full.approved + '' +
              '<span>' +
              '</p>'
            ).html();
          }
        },
        {
          targets: 3,
          className: "text-center",
          render: function (data, type, full, meta) {
            return $('<p class="m-0">' +
              '<span class="badge bg-label-danger fs-6">' +
              '' + full.declined + '' +
              '<span>' +
              '</p>'
            ).html();
          }
        },

        {
          targets: 4,
          className: "text-center",
          render: function (data, type, full, meta) {
            return $('<p class="m-0">' +
              '<span class="badge bg-label-primary fs-6">' +
              '' + full.archived + '' +
              '</span>' +
              '</p>'
            ).html();
          }
        },
        {
          targets: 5,
          className: "text-center",
          render: function (data, type, full, meta) {
            return $('<p class="m-0">' +
              '<span class="badge bg-label-dribbble fs-6">' +
              '' + full.closed + '' +
              '</span>' +
              '</p>'
            ).html();
          }
        },

        {
          targets: 6,
          className: "text-center",
          render: function (data, type, full, meta) {
            return $('<p class="m-0">' +
              '<span class="badge bg-label-secondary fs-6">' +
              '' + full.total + '' +
              '<span>' +
              '</p>'
            ).html();
          }
        },
      ],
      initComplete: function (settings, json) {
        $('.dataTables_filter .form-control').removeClass('form-control-sm').attr('placeholder', window.variables.messages.search + " ...");
        $('.dataTables_length .form-select').removeClass('form-select-sm');
      }
    };
  }
});
