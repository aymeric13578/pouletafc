'use strict';

$(function () {
  let opStatTable = $('#opStatTable'), opStatDtBasic;
  let renewalOpStatTable = $('#renewalOpStatTable'), renewalOpStatTableBasic;

  let apStatTable = $('#apStatTable'), apStatDtBasic;
  let renewalApStatTable = $('#renewalApStatTable'), renewalApStatDtBasic;

  let requestDistChartPicker = $('#requestDistChartPicker');
  let renewalRequestDistChartPicker = $('#renewalRequestDistChartPicker');
  let requestDistChartElWrapper = $('#requestDistChartElWrapper');
  let renewalRequestDistChartElWrapper = $('#renewalRequestDistChartElWrapper');
  let operatingTitleRequestPicker = $('#operatingTitleRequestPicker');
  let approvalRequestPicker = $('#approvalRequestPicker');
  let operatingTitleRequestPickerWrapper = $('#operatingTitleRequestPickerWrapper');
  let approvalRequestPickerWrapper = $('#approvalRequestPickerWrapper');
  let statisticPeriod = $('#statisticPeriod');
  let applyAction = $('#applyAction');
  let applyActionForm = $('#applyActionForm');
  let dateFormat = "YYYY/MM/DD";

  let requestDistChart;
  let renewalRequestDistChart;

  let applicantChartColors = [
    config.colors.primary,
  ];
  let cardColor, headingColor, labelColor, shadeColor, borderColor, legendColor;
  if (isDarkStyle) {
    cardColor = config.colors_dark.cardColor;
    legendColor = config.colors_dark.bodyColor;
    headingColor = config.colors_dark.headingColor;

    labelColor = config.colors_dark.textMuted;
    borderColor = config.colors_dark.borderColor;
    legendColor = config.colors_dark.bodyColor;
  } else {
    cardColor = config.colors.cardColor;
    legendColor = config.colors.bodyColor;
    headingColor = config.colors.headingColor;

    labelColor = config.colors.textMuted;
    borderColor = config.colors.borderColor;
    legendColor = config.colors.bodyColor;
  }

  let requestStatusStat = window.variables.vars.requestStatusStat;
  let renewalRequestStatusStat = window.variables.vars.renewalRequestStatusStat;

  /** Init */

  initDataTables();
  initDateRangePicker();
  requestDistributionChart();
  renewalRequestDistributionChart();

  /** Event handlers */

  applyAction.on('click', function(event) {
    event.preventDefault();
    applyActionForm.trigger('submit');
  });

  applyActionForm.submit( function(event) {
    let values = statisticPeriod.val().split("-");

    $("<input />")
      .attr("type", "hidden")
      .attr("name", "sd")
      .attr("value", values[0].trim())
      .appendTo("#applyActionForm");
    $("<input />")
      .attr("type", "hidden")
      .attr("name", "ed")
      .attr("value", values[1].trim())
      .appendTo("#applyActionForm");

    return true;
  });

  operatingTitleRequestPicker.on('apply.daterangepicker', function(ev, picker) {
    $(this).val(picker.startDate.format(dateFormat) + ' - ' + picker.endDate.format(dateFormat));
    $(this).trigger('change');
  });

  approvalRequestPicker.on('apply.daterangepicker', function(ev, picker) {
    $(this).val(picker.startDate.format(dateFormat) + ' - ' + picker.endDate.format(dateFormat));
    $(this).trigger('change');
  });

  requestDistChartPicker.on('change', function(event){
    event.preventDefault();
    let rangeDate = $(this).val().split('-');

    $.ajax({
      url: window.variables.routes.request_distribution_url,
      type: "POST",
      dataType: "json",
      data: {
        _token: window.variables.constants.csrf_token,
        start_date: rangeDate[0].trim(),
        end_date: rangeDate[1].trim(),
      },
      beforeSend: function () {
        requestDistChartElWrapper.find('.overlay').removeClass('d-none');
        requestDistChartElWrapper.find('.global-spinner').removeClass('d-none');
      },
      complete: function () {
        requestDistChartElWrapper.find('.overlay').addClass('d-none');
        requestDistChartElWrapper.find('.global-spinner').addClass('d-none');
      },
      error: function () {},
      success: function (result) {
        if(result.code === 100) {
          window.variables.vars.requestDistribution.op_dist = result.data.op_dist;
          window.variables.vars.requestDistribution.ap_dist = result.data.ap_dist;
          let labels = [
            ...result.data.op_dist.map((el) => el.month_year),
            ...result.data.ap_dist.map((el) => el.month_year),
          ];
          window.variables.vars.monthLabels = labels.length > 0 ? labels.filter((element, index) => {
            return labels.indexOf(element) === index;
          }) : [];

          requestDistributionChart(true);
        }
      }
    });
  });

  renewalRequestDistChartPicker.on('change', function(event){
    event.preventDefault();
    let rangeDate = $(this).val().split('-');

    $.ajax({
      url: window.variables.routes.request_distribution_url,
      type: "POST",
      dataType: "json",
      data: {
        _token: window.variables.constants.csrf_token,
        start_date: rangeDate[0].trim(),
        end_date: rangeDate[1].trim(),
        renewal: 1,
      },
      beforeSend: function () {
        renewalRequestDistChartElWrapper.find('.overlay').removeClass('d-none');
        renewalRequestDistChartElWrapper.find('.global-spinner').removeClass('d-none');
      },
      complete: function () {
        renewalRequestDistChartElWrapper.find('.overlay').addClass('d-none');
        renewalRequestDistChartElWrapper.find('.global-spinner').addClass('d-none');
      },
      error: function () {},
      success: function (result) {
        if(result.code === 100) {
          window.variables.vars.renewalRequestDistribution.op_dist = result.data.op_dist;
          window.variables.vars.renewalRequestDistribution.ap_dist = result.data.ap_dist;
          let labels = [
            ...result.data.op_dist.map((el) => el.month_year),
            ...result.data.ap_dist.map((el) => el.month_year),
          ];
          window.variables.vars.monthLabels = labels.length > 0 ? labels.filter((element, index) => {
            return labels.indexOf(element) === index;
          }) : [];

          renewalRequestDistributionChart(true);
        }
      }
    });
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
    if (statisticPeriod.length) {
      statisticPeriod.daterangepicker({
        defaultDate: ["", ""],
        locale: {
          format: dateFormat,
          separator: " - "
        },
      });
    }

    if (requestDistChartPicker.length) {
      requestDistChartPicker.daterangepicker({
        defaultDate: ["", ""],
        locale: {
          format: dateFormat,
          separator: " - "
        },
      });
    }

    if (renewalRequestDistChartPicker.length) {
      renewalRequestDistChartPicker.daterangepicker({
        defaultDate: ["", ""],
        locale: {
          format: dateFormat,
          separator: " - "
        },
      });
    }

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

  function initDataTables() {
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

  function plotColumnWidth(data) {
    if(data.length >= 12) {
      return "30%";
    }

    return "30px";
  }

  /** Compute charts */

    // Request status chart
  const requestStatusStatPieChart = document.querySelector('#requestStatusStatPieChart'),
    requestStatusStatPieChartConfig = {
      chart: {
        height: 165,
        width: 130,
        type: 'donut'
      },
      labels: [
        window.variables.messages.approved,
        window.variables.messages.declined,
        window.variables.messages.waiting,
      ],
      series: [
        requestStatusStat.approved > 0 ? (requestStatusStat.approved / requestStatusStat.total) * 100 : 0,
        requestStatusStat.declined > 0 ? (requestStatusStat.declined / requestStatusStat.total) * 100 : 0,
        requestStatusStat.waiting > 0 ? (requestStatusStat.waiting / requestStatusStat.total) * 100 : 0,
      ],
      colors: [config.colors.primary, config.colors.danger, config.colors.warning,],
      stroke: {
        width: 5,
        colors: cardColor
      },
      dataLabels: {
        enabled: false,
        formatter: function (val, opt) {
          return parseInt(val) + '%';
        }
      },
      legend: {
        show: false
      },
      grid: {
        padding: {
          top: 0,
          bottom: 0,
          right: 15
        }
      },
      plotOptions: {
        pie: {
          donut: {
            size: '70%',
            labels: {
              show: true,
              value: {
                fontSize: '1.3rem',
                fontFamily: 'Public Sans',
                color: headingColor,
                offsetY: -15,
                formatter: function (val) {
                  return parseInt(val) + '%';
                }
              },
              name: {
                offsetY: 20,
                fontFamily: 'Public Sans'
              },
              total: {
                show: true,
                fontSize: '0.8125rem',
                color: legendColor,
                label: window.variables.messages.thisYear,
                formatter: function (w) {
                  if(requestStatusStat.waiting > 0) {
                    return Math.trunc((requestStatusStat.waiting / requestStatusStat.total) * 100) + '%';
                  }

                  return requestStatusStat.waiting + '%';
                }
              }
            }
          }
        }
      }
    };
  if (typeof requestStatusStatPieChart !== undefined && requestStatusStatPieChart !== null) {
    const statisticsPieChart = new ApexCharts(requestStatusStatPieChart, requestStatusStatPieChartConfig);
    statisticsPieChart.render();
  }

  // Request status chart for renewal requests
  const renewalRequestStatusStatPieChart = document.querySelector('#renewalRequestStatusStatPieChart'),
    renewalRequestStatusStatPieChartConfig = {
      chart: {
        height: 165,
        width: 130,
        type: 'donut'
      },
      labels: [
        window.variables.messages.approved,
        window.variables.messages.declined,
        window.variables.messages.waiting,
      ],
      series: [
        renewalRequestStatusStat.approved > 0 ? (renewalRequestStatusStat.approved / renewalRequestStatusStat.total) * 100 : 0,
        renewalRequestStatusStat.declined > 0 ? (renewalRequestStatusStat.declined / renewalRequestStatusStat.total) * 100 : 0,
        renewalRequestStatusStat.waiting > 0 ? (renewalRequestStatusStat.waiting / renewalRequestStatusStat.total) * 100 : 0,
      ],
      colors: [config.colors.primary, config.colors.danger, config.colors.warning,],
      stroke: {
        width: 5,
        colors: cardColor
      },
      dataLabels: {
        enabled: false,
        formatter: function (val, opt) {
          return parseInt(val) + '%';
        }
      },
      legend: {
        show: false
      },
      grid: {
        padding: {
          top: 0,
          bottom: 0,
          right: 15
        }
      },
      plotOptions: {
        pie: {
          donut: {
            size: '70%',
            labels: {
              show: true,
              value: {
                fontSize: '1.3rem',
                fontFamily: 'Public Sans',
                color: headingColor,
                offsetY: -15,
                formatter: function (val) {
                  return parseInt(val) + '%';
                }
              },
              name: {
                offsetY: 20,
                fontFamily: 'Public Sans'
              },
              total: {
                show: true,
                fontSize: '0.8125rem',
                color: legendColor,
                label: window.variables.messages.thisYear,
                formatter: function (w) {
                  if(renewalRequestStatusStat.waiting > 0) {
                    return Math.trunc((renewalRequestStatusStat.waiting / renewalRequestStatusStat.total) * 100) + '%';
                  }

                  return renewalRequestStatusStat.waiting + '%';
                }
              }
            }
          }
        }
      }
    };
  if (typeof renewalRequestStatusStatPieChart !== undefined && renewalRequestStatusStatPieChart !== null) {
    const statisticsPieChart = new ApexCharts(renewalRequestStatusStatPieChart, renewalRequestStatusStatPieChartConfig);
    statisticsPieChart.render();
  }

  // Applicants bar chart
  const applicantChartEl = document.querySelector('#applicantChart'),
    totalBalanceChartConfig = {
      series: [
        {
          data: window.variables.vars.applicantStat.map((e) => {
            return {
              x: e.month_year,
              y: e.total,
            }
          })
        }
      ],
      chart: {
        height: 120,
        parentHeightOffset: 0,
        parentWidthOffset: 0,
        type: 'line',
        dropShadow: {
          enabled: true,
          top: 10,
          left: 5,
          blur: 3,
          color: config.colors.warning,
          opacity: 0.15
        },
        toolbar: {
          show: false
        }
      },
      dataLabels: {
        enabled: false
      },
      stroke: {
        width: 2,
        curve: 'smooth'
      },
      legend: {
        show: false
      },
      colors: [config.colors.warning],
      markers: {
        size: 6,
        colors: 'transparent',
        strokeColors: 'transparent',
        strokeWidth: 4,
        discrete: [
          ...window.variables.vars.applicantStat.map((e, index) => {
            if(index === window.variables.vars.applicantStat.length - 1){
              return {
                fillColor: config.colors.white,
                seriesIndex: 0,
                dataPointIndex: index,
                strokeColor: config.colors.warning,
                strokeWidth: 8,
                size: 6,
                radius: 8
              };
            }

            return {
              fillColor: config.colors.white,
              seriesIndex: 0,
              dataPointIndex: index,
              strokeColor: config.colors.warning,
              strokeWidth: 4,
              size: 2,
              radius: 2
            };
          }),
        ],
        hover: {
          size: 7
        }
      },
      grid: {
        show: false,
        padding: {
          top: -10,
          left: 0,
          right: 0,
          bottom: 10
        }
      },
      xaxis: {
        categories: window.variables.vars.applicantStat.map((e) => {
          return e.month_year;
        }),
        axisBorder: {
          show: false
        },
        axisTicks: {
          show: false
        },
        labels: {
          show: true,
          style: {
            fontSize: '13px',
            colors: labelColor
          }
        }
      },
      yaxis: {
        labels: {
          show: false
        }
      }
    };
  if (typeof applicantChartEl !== undefined && applicantChartEl !== null) {
    const totalBalanceChart = new ApexCharts(applicantChartEl, totalBalanceChartConfig);
    totalBalanceChart.render();
  }

  function requestDistributionChart(update) {
    let opDist = window.variables.vars.requestDistribution.op_dist.map((el) => parseInt(el.total));
    let apDist = window.variables.vars.requestDistribution.ap_dist.map((el) => {
      let total = parseInt(el.total);
      if(total > 0) {
        return -1 * total;
      }

      return 0;
    });
    let data = [...opDist, ...apDist];
    let max = Math.max(...apDist, ...opDist);

    if(update) {
      requestDistChart.updateOptions({
        series: [
          {
            name: window.variables.messages.operatingTitle,
            data: opDist,
          },
          {
            name: window.variables.messages.approval,
            data: apDist,
          }
        ],
        xaxis: {
          categories: window.variables.vars.requestDistribution.op_dist.map((el) => {
            return el.month_year;
          }),
        },
        yaxis: {
          max: max,
          tickAmount: 4,
          labels: {
            style: {
              fontSize: '13px',
              colors: labelColor
            },
            formatter: function(val, index) {
              if(val >= 0) return Math.floor(val);

              return Math.floor(-1 * val);
            }
          }
        },
      });
      return;
    }

    const requestDistChartEl = document.querySelector('#requestDistChartEl'), requestDistChartElOptions = {
      series: [
        {
          name: window.variables.messages.operatingTitle,
          data: opDist,
        },
        {
          name: window.variables.messages.approval,
          data: apDist,
        }
      ],
      chart: {
        height: 320,
        stacked: true,
        type: 'bar',
        toolbar: { show: false }
      },
      plotOptions: {
        bar: {
          horizontal: false,
          columnWidth: plotColumnWidth(data),
          borderRadius: 12,
          startingShape: 'rounded',
          endingShape: 'rounded'
        }
      },
      colors: [config.colors.primary, config.colors.info],
      dataLabels: {
        enabled: false
      },
      stroke: {
        curve: 'smooth',
        width: 6,
        lineCap: 'round',
        colors: [cardColor]
      },
      legend: {
        show: true,
        horizontalAlign: 'left',
        position: 'top',
        markers: {
          height: 8,
          width: 8,
          radius: 12,
          offsetX: -3
        },
        labels: {
          colors: legendColor
        },
        itemMargin: {
          horizontal: 10
        }
      },
      grid: {
        borderColor: borderColor,
        padding: {
          top: 0,
          bottom: -8,
          left: 20,
          right: 20
        }
      },
      xaxis: {
        categories: window.variables.vars.requestDistribution.op_dist.map((el) => {
          return el.month_year;
        }),
        labels: {
          style: {
            fontSize: '13px',
            colors: labelColor
          }
        },
        axisTicks: {
          show: false
        },
        axisBorder: {
          show: false
        }
      },
      yaxis: {
        max: max,
        tickAmount: 4,
        labels: {
          style: {
            fontSize: '13px',
            colors: labelColor
          },
          formatter: function(val, index) {
            if(val >= 0) return Math.floor(val);

            return Math.floor(-1 * val);
          }
        }
      },
      responsive: [
        {
          breakpoint: 1700,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '32%'
              }
            }
          }
        },
        {
          breakpoint: 1580,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '35%'
              }
            }
          }
        },
        {
          breakpoint: 1440,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '42%'
              }
            }
          }
        },
        {
          breakpoint: 1300,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '48%'
              }
            }
          }
        },
        {
          breakpoint: 1200,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '40%'
              }
            }
          }
        },
        {
          breakpoint: 1040,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 11,
                columnWidth: '48%'
              }
            }
          }
        },
        {
          breakpoint: 991,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '30%'
              }
            }
          }
        },
        {
          breakpoint: 840,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '35%'
              }
            }
          }
        },
        {
          breakpoint: 768,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '28%'
              }
            }
          }
        },
        {
          breakpoint: 640,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '32%'
              }
            }
          }
        },
        {
          breakpoint: 576,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '37%'
              }
            }
          }
        },
        {
          breakpoint: 480,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '45%'
              }
            }
          }
        },
        {
          breakpoint: 420,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '52%'
              }
            }
          }
        },
        {
          breakpoint: 380,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '60%'
              }
            }
          }
        }
      ],
      states: {
        hover: {
          filter: {
            type: 'none'
          }
        },
        active: {
          filter: {
            type: 'none'
          }
        }
      }
    };
    if (typeof requestDistChartEl !== undefined && requestDistChartEl !== null) {
      requestDistChart = new ApexCharts(
        requestDistChartEl,
        requestDistChartElOptions,
      );
      requestDistChart.render();
    }
  }

  function renewalRequestDistributionChart(update) {
    let opDist = window.variables.vars.renewalRequestDistribution.op_dist.map((el) => parseInt(el.total));
    let apDist = window.variables.vars.renewalRequestDistribution.ap_dist.map((el) => {
      let total = parseInt(el.total);
      if(total > 0) {
        return -1 * total;
      }

      return 0;
    });
    let data = [...opDist, ...apDist];
    let max = Math.max(...apDist, ...opDist);

    if(update) {
      renewalRequestDistChart.updateOptions({
        series: [
          {
            name: window.variables.messages.operatingTitle,
            data: opDist,
          },
          {
            name: window.variables.messages.approval,
            data: apDist,
          }
        ],
        xaxis: {
          categories: window.variables.vars.renewalRequestDistribution.op_dist.map((el) => {
            return el.month_year;
          }),
        },
        yaxis: {
          max: max,
          tickAmount: 4,
          labels: {
            style: {
              fontSize: '13px',
              colors: labelColor
            },
            formatter: function(val, index) {
              if(val >= 0) return Math.floor(val);

              return Math.floor(-1 * val);
            }
          }
        },
      });
      return;
    }

    const requestDistChartEl = document.querySelector('#renewalRequestDistChartEl'), renewalRequestDistChartEl = {
      series: [
        {
          name: window.variables.messages.operatingTitle,
          data: opDist,
        },
        {
          name: window.variables.messages.approval,
          data: apDist,
        }
      ],
      chart: {
        height: 320,
        stacked: true,
        type: 'bar',
        toolbar: { show: false }
      },
      plotOptions: {
        bar: {
          horizontal: false,
          columnWidth: plotColumnWidth(data),
          borderRadius: 12,
          startingShape: 'rounded',
          endingShape: 'rounded'
        }
      },
      colors: [config.colors.primary, config.colors.info],
      dataLabels: {
        enabled: false
      },
      stroke: {
        curve: 'smooth',
        width: 6,
        lineCap: 'round',
        colors: [cardColor]
      },
      legend: {
        show: true,
        horizontalAlign: 'left',
        position: 'top',
        markers: {
          height: 8,
          width: 8,
          radius: 12,
          offsetX: -3
        },
        labels: {
          colors: legendColor
        },
        itemMargin: {
          horizontal: 10
        }
      },
      grid: {
        borderColor: borderColor,
        padding: {
          top: 0,
          bottom: -8,
          left: 20,
          right: 20
        }
      },
      xaxis: {
        categories: window.variables.vars.renewalRequestDistribution.op_dist.map((el) => {
          return el.month_year;
        }),
        labels: {
          style: {
            fontSize: '13px',
            colors: labelColor
          }
        },
        axisTicks: {
          show: false
        },
        axisBorder: {
          show: false
        }
      },
      yaxis: {
        max: max,
        tickAmount: 4,
        labels: {
          style: {
            fontSize: '13px',
            colors: labelColor
          },
          formatter: function(val, index) {
            if(val >= 0) return Math.floor(val);

            return Math.floor(-1 * val);
          }
        }
      },
      responsive: [
        {
          breakpoint: 1700,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '32%'
              }
            }
          }
        },
        {
          breakpoint: 1580,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '35%'
              }
            }
          }
        },
        {
          breakpoint: 1440,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '42%'
              }
            }
          }
        },
        {
          breakpoint: 1300,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '48%'
              }
            }
          }
        },
        {
          breakpoint: 1200,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '40%'
              }
            }
          }
        },
        {
          breakpoint: 1040,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 11,
                columnWidth: '48%'
              }
            }
          }
        },
        {
          breakpoint: 991,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '30%'
              }
            }
          }
        },
        {
          breakpoint: 840,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '35%'
              }
            }
          }
        },
        {
          breakpoint: 768,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '28%'
              }
            }
          }
        },
        {
          breakpoint: 640,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '32%'
              }
            }
          }
        },
        {
          breakpoint: 576,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '37%'
              }
            }
          }
        },
        {
          breakpoint: 480,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '45%'
              }
            }
          }
        },
        {
          breakpoint: 420,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '52%'
              }
            }
          }
        },
        {
          breakpoint: 380,
          options: {
            plotOptions: {
              bar: {
                borderRadius: 10,
                columnWidth: '60%'
              }
            }
          }
        }
      ],
      states: {
        hover: {
          filter: {
            type: 'none'
          }
        },
        active: {
          filter: {
            type: 'none'
          }
        }
      }
    };
    if (typeof requestDistChartEl !== undefined && requestDistChartEl !== null) {
      renewalRequestDistChart = new ApexCharts(
        requestDistChartEl,
        renewalRequestDistChartEl,
      );
      renewalRequestDistChart.render();
    }
  }
});
