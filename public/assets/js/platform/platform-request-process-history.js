'use strict';

$(function () {
  let historyDataTable = $('#historyDataTable'), dtHistory;
  let filterDate = $('.filter-date');
  let formFilter = $('#formFilter');
  let sd = $('input[name="sd"]');
  let ed = $('input[name="ed"]');
  let body =  $("body");

  /** Init all components */

  intiStaticDataTable();
  initComponents();

  /** Events handler */

  body.on("click", ".print-data", function(event) {
    event.preventDefault();

    let $this = $(this);
    Loader.setLoading($this);
    printJS(window.variables.routes.print_processing_history);

    setTimeout(function() {
      Loader.removeLoading($this);
    }, 1000);
  });

  body.on("click", ".print-tracking-sheet", function(event) {
    event.preventDefault();

    window.open(window.variables.routes.print_request_tracking_sheet, '_blank');
  });

  formFilter.submit(function (event) {
    if(sd.val().length > 0 || ed.val().length > 0) {
      formFilter.append('<input type="hidden" name="q" value="'+ window.variables.routes.query +'" />');
      formFilter.append('<input type="hidden" name="type" value="'+ window.variables.routes.type +'" />');
      return true;
    }

    return false;
  });

  $('#btnResetFilter').on('click', function (event) {
    event.preventDefault();

    if(sd.val().length > 0 || ed.val().length > 0) {
      sd.val('');
      ed.val('');

      let url = new URL(formFilter.attr('action'));
      url.searchParams.set('q', window.variables.routes.query);
      url.searchParams.set('type', window.variables.routes.type);

      location.href = url.toString();
    }
  });

  $('#btnApplyFilter').on('click', function (event) {
    event.preventDefault();
    formFilter.trigger('submit');
  });

  /** Functions */

  /** Render elements */

  function intiStaticDataTable() {
    if (historyDataTable.length) {
      dtHistory = historyDataTable.DataTable(basicDataTableOptions());
    }
  }

  /** Utilities */

  function initComponents() {
    if (filterDate) {
      filterDate.flatpickr({
        monthSelectorType: 'static',
        // maxDate: new Date(date.getFullYear(), date.getMonth(), date.getDate())
      });
    }
  }

  function basicDataTableOptions() {
    return {
      paging: true,
      ordering: false,
      info: true,
      displayLength: 25,
      searching: true,
      lengthChange: true,
      orderCellsTop: false,
      order: [[1, "asc"]],
      language: window.variables.messages.languages,
      columnDefs: [
        {
          targets: '_all',
          orderable: false,
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
          text: "<span class='d-none d-lg-inline-block'>"+ window.variables.messages.print_data +"</span>",
          className: 'print-data btn btn-label-secondary ms-0 ms-md-2',
        },
        {
          text: "<span class='d-inline-block'>"+ window.variables.messages.print_tracking_sheet +"</span>",
          className: 'print-tracking-sheet btn btn-label-primary ms-0 ms-md-2',
        },
      ],
      initComplete: function (settings, json) {
        $('.dataTables_filter .form-control').removeClass('form-control-sm').attr('placeholder', window.variables.messages.search + " ...");
        $('.dataTables_length .form-select').removeClass('form-select-sm');

        // State visibility
        $('.global-spinner').addClass('d-none');
        $('#pageWrapper').removeClass('d-none');
      }
    };
  }
});
