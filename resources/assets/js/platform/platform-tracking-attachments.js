'use strict';

$(function () {
  let attachmentTable = $('#attachmentTable'), dtAttachment;

  // Init ...
  intiStaticDataTable();

  // Events handler

  // Functions

  function intiStaticDataTable() {
    if (attachmentTable.length) {
      dtAttachment = attachmentTable.DataTable(basicDataTableOptions());
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
      initComplete: function (settings, json) {
        $('.dataTables_filter .form-control').removeClass('form-control-sm').attr('placeholder', window.variables.messages.search + " ...");
        $('.dataTables_length .form-select').removeClass('form-select-sm');
      }
    };
  }
});
