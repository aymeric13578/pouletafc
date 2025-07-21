'use strict';

$(function () {
  const toastAnimationExample = $('.toast-wrapper');
  let transmissionsTable = $('#transmissionsTable'), dtBasic;
  let requestsData = [];
  let toastAnimation;

  /** Init */

  getToastInstance();
  loadInitialDataPage();

  /** Event handler */

  transmissionsTable.on('click', '.reverse-action', function(event) {
    event.preventDefault();
    let $this = $(this);

    // Progress ...
    handleToastProgress();

    setTimeout(function() {
      let target = $this.attr('data-bs-target');
      $.ajax({
        url: window.variables.routes.reverse_transmission,
        type:'POST',
        dataType: "json",
        data: {
          "_token": window.variables.request.csrf_token,
          "id": target,
        },
        complete: function () {},
        error: function () {
          handleToastError(window.variables.messages.unable_to_perform_request);
        },
        success: function (result) {
          if(result.code === 100) {
            // Remove data
            requestsData = deleteDataTableItem(parseInt(target));

            // Refresh data table
            $('.data-count').html(`(${Helpers.formatAmount(requestsData.length, 0).trim()})`);
            dtBasic.clear();
            dtBasic = dtBasic.rows.add(requestsData);
            dtBasic.draw(false);

            // ... Toast state
            toastAnimation.dispose();
            getToastInstance();
          } else {
            handleToastError(result.message);
          }
        },
      });
    }, 2000);
  });

  /** Functions */

  function loadInitialDataPage() {
    $.ajax({
      url: window.variables.routes.un_processing_requests,
      type: "GET",
      dataType : 'json',
      data: {},
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
              '<div class="ps-1">' +
                '<p class="w-px-250 text-wrap my-0 mx-0" style="">'+ full.request.display_type +'</p>' +
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
            return 'N°' + full.request.reference;
          }
        },
        {
          targets: 2,
          className: 'fw-semibold',
          render: function (data, type, full, meta) {
            return full.request.display_owner;
          }
        },
        {
          targets: 3,
          render: function (data, type, full, meta) {
            let utcDate = moment.utc(full.created_at);
            return moment(utcDate).local().format(window.variables.messages.js_date_format);
          }
        },
        {
          targets: 4,
          render: function (data, type, full, meta) {
            // ....
            let labelDesignation = full.management_unit_source !== null
              ? `${full.management_unit_source.designation} ⸱ ${full.staff_service_source.designation}`
              : "";

            return (
              '<div class="ps-1">' +
              '<p class="w-px-200 text-wrap my-0 mx-0 fst-italic" style="">' +
              ''+ labelDesignation +'' +
                '</p>' +
                '</div>'
            );
          }
        },
        {
          targets: 5,
          render: function (data, type, full, meta) {
            // ....
            let labelDesignation = full.management_unit_target !== null
              ? `${full.management_unit_target.designation} ⸱ ${full.staff_service_target.designation}`
              : "";

            return (
              '<div class="ps-1">' +
              '<p class="w-px-200 text-wrap my-0 mx-0 fst-italic" style="">' +
              ''+ labelDesignation +'' +
              '</p>' +
              '</div>'
            );
          }
        },
        {
          targets: 6,
          render: function (data, type, full, meta) {
            return full.transmitter_user.full_name;
          }
        },
        {
          targets: 7,
          render: function (data, type, full, meta) {
            return full.receiving_user !== null ? full.receiving_user.full_name : "";
          }
        },
        {
          targets: 8,
          className: 'text-end',
          orderable: false,
          sortable: false,
          searching: false,
          render: function (data, type, full, meta) {
            let actionWrapper =  $('<div class=""></div>');

            let reverseAction = (
              '<div class="btn-group dropstart me-2">' +
                '<button type="button" class="btn btn-icon btn-label-warning btn-sm ms-2 reverse-action" data-bs-target="' + full.id + '">' +
                  '<span class="tf-icons bx bx-left-arrow-alt"></span>' +
                '</button>' +
              '</div>'
            );
            let dropdown = (
              '<div class="btn-group">' +
                '<button type="button" class="btn btn-icon btn-label-secondary btn-sm" data-bs-toggle="dropdown" aria-expanded="false">' +
                '  <i class="tf-icons bx bx-dots-vertical-rounded"></i>' +
                '</button>' +
                '<ul class="dropdown-menu">' +
                  '<li class="">' +
                    '<a class="dropdown-item reverse-action" href="javascript:void(0);" data-bs-target="' + full.id + '">' +
                    '' + window.variables.messages.reverse_transmission + '' +
                    '</a>' +
                  '</li>' +
                '</ul>' +
              '</div>'
            );

            actionWrapper.append($(reverseAction));
            actionWrapper.append($(dropdown));
            return actionWrapper.html();
          }
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

  function initDataTable() {
    if (transmissionsTable.length > 0) {
      dtBasic = transmissionsTable.DataTable(
        dataTableOption(requestsData)
      );
    }
  }

  function getToastInstance() {
    toastAnimation = new bootstrap.Toast(toastAnimationExample, {
      autohide: false
    });
  }

  function handleToastProgress() {
    toastAnimationExample.find('button.btn-close').addClass('d-none');
    toastAnimationExample.find('div.toast-body').empty().append(
      $(`<div class="d-flex align-items-start mb-4">
          <div class="spinner-border spinner-border-md text-secondary" role="status"></div>
          <span class="ps-4 fs-6 text-truncate" style="padding-top: 0.40rem;">${window.variables.messages.processing_in_progress} ...</span>
        </div>
      `)
    );
    toastAnimation.show();
  }

  function handleToastError(message) {
    toastAnimationExample.find('button.btn-close').removeClass('d-none');
    toastAnimationExample.find('div.toast-body').empty().append(
      $(`<div class="d-flex align-items-start mb-4">
          <span class="tf-icons bx bx-info-circle fs-4 me-1 mt-1""></span>
          <span class="ps-4 fs-6 text-wrap" style="padding-top: 0.16rem;">${message}</span>
        </div>
      `)
    );
    toastAnimation.show()
  }

  function deleteDataTableItem(id) {
    return requestsData.filter(item => item.id !== id);
  }
});
