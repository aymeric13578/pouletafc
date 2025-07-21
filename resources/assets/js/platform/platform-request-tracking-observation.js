'use strict';

$(function () {
  let commentWrapper = $('.comment-wrapper');
  let commentsTable = $('#commentsTable'), dtComment;

  intiStaticDataTable();

  // Event handler

  // Functions

  function intiStaticDataTable() {
    if (commentsTable.length) {
      dtComment = commentsTable.DataTable(basicDataTableOptions(commentWrapper));
    }
  }

  // Utilities

  function basicDataTableOptions(el) {
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
        $('.dataTables_filter .form-control')
          .removeClass('form-control-sm')
          .attr('placeholder', window.variables.messages.search + " ...");
        $('.dataTables_length .form-select').removeClass('form-select-sm');

        el.removeClass('d-none');
      }
    };
  }
});
