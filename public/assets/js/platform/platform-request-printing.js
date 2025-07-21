'use strict';

$(function () {
  let printingDocumentTypesTable = $('#printingDocumentTypesTable'), dtTable;

  intiStaticDataTable();

  function basicDataTableOptions() {
    return {
      paging: true,
      ordering: false,
      info: true,
      displayLength: 10,
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
      initComplete: function (settings, json) {
        $('.dataTables_filter .form-control').removeClass('form-control-sm').attr('placeholder', window.variables.messages.search + " ...");
        $('.dataTables_length .form-select').removeClass('form-select-sm');
        printingDocumentTypesTable.removeClass('d-none');
      }
    };
  }

  function intiStaticDataTable() {
    if (printingDocumentTypesTable.length) {
      dtTable = printingDocumentTypesTable.DataTable(basicDataTableOptions());
    }
  }
});
