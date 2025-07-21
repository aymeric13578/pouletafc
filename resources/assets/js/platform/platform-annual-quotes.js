'use strict';

$(function () {
  // Init ...
  let annualQuoteGeneration_Modal = $('#annualQuoteGeneration_Modal');
  let annualQuoteGeneration_Form = $('#annualQuoteGeneration_Form');
  let annualQuotesDataTable = $('.datatables-basic'), dtBasic;
  let globalSpinner = $('.global-spinner');
  let annualQuotesWrapper = $('.quotes-wrapper');
  let generationOptions = $('ul.generation-options');
  let currentItem = null;
  let genericItems = null;

  /** Init */

  intiStaticDataTable();

  /** Handle events */

  $('#annualQuotesDataTable tbody').on('click', 'td a.generate-quote-action', function (event) {
    event.preventDefault();

    genericItems = null;
    currentItem = {
      "operating_title_id": $(this).attr('data-bs-targetid'),
      "target_designation": $(this).attr('data-bs-targetdesignation'),
      "turnover": $(this).attr('data-bs-turnover'),
    };
    handleModalContent();
    resetModalInputErrors();
    annualQuoteGeneration_Modal.modal('show');
  });

  $('.generic-quote-generation').click(function (event) {
    event.preventDefault();
    let $this = $(this);

    let codes = $this.attr('data-bs-targetcodes');
    genericItems = computeGenericItems(codes !== undefined && codes.length > 0 ? codes.split(',') : []);
    handleModalContent();
    resetModalInputErrors();
    annualQuoteGeneration_Modal.modal('show');
  });

  $('#annualQuoteGeneration_Modal button.btn-close, #annualQuoteGeneration_Modal button#btnCancel').click(function (event) {
    event.preventDefault();

    resetModalInputErrors();
    currentItem = null;
    genericItems = null;
    annualQuoteGeneration_Modal.modal('hide');
  });

  $('#annualQuoteGeneration_Modal button#btnConfirm').click(function (event) {
    event.preventDefault();

    let formData = new FormData(annualQuoteGeneration_Form[0]);
    formData.set(
      'operating_title_id',
      currentItem !== null
        ? currentItem['operating_title_id']
        : genericItems.map(e => e['operating_title_id']).join(',')
    );

    $.ajax({
      url: annualQuoteGeneration_Form.attr('action'),
      type: "POST",
      cache: false,
      contentType: false,
      processData: false,
      dataType : 'json',
      data: formData,
      beforeSend: function () {
        resetModalInputErrors();
        annualQuoteGeneration_Modal.find('.request-spinner').removeClass('d-none');
        annualQuoteGeneration_Modal.find('.actions-wrapper').addClass('d-none');
        annualQuoteGeneration_Modal.find('button.btn-close').addClass('d-none');
      },
      complete: function () {
        annualQuoteGeneration_Modal.find('.request-spinner').addClass('d-none');
        annualQuoteGeneration_Modal.find('.actions-wrapper').removeClass('d-none');
        annualQuoteGeneration_Modal.find('button.btn-close').removeClass('d-none');
      },
      error: function () {
        Helpers.customInputErrorsValidation({
          error: window.variables.messages.error_occurred_during_operation
        }, annualQuoteGeneration_Form);
      },
      success: function (result) {
        switch(result.code){
          case 100:
            annualQuoteGeneration_Modal.modal('hide');
            location.reload();
            break;
          case 150:
            Helpers.customInputErrorsValidation(result.data, annualQuoteGeneration_Form);
            break;
          default:
            Helpers.customInputErrorsValidation(
              {error: result.message},
              annualQuoteGeneration_Form,
            );
            break;
        }
      },
    });
  });

  /** Utilities */

  function intiStaticDataTable() {
    if (annualQuotesDataTable.length) {
      dtBasic = annualQuotesDataTable.DataTable(dataTableOption());
    }
  }

  function dataTableOption() {
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
          defaultContent: "--",
          className: ''
        },
      ],
      initComplete: function (settings, json) {
        $('.dataTables_filter .form-control').removeClass('form-control-sm').attr('placeholder', window.variables.messages.search + " ...");
        $('.dataTables_length .form-select').removeClass('form-select-sm');

        annualQuotesWrapper.removeClass('d-none');
        globalSpinner.addClass('d-none');
        handleGenerationOptions();
      }
    };
  }

  function handleModalContent() {
    let listWrapper = annualQuoteGeneration_Modal.find('div.notice-content ul');
    listWrapper.empty();

    if(currentItem !== null) {
      listWrapper.append(
        $('<li class="">' + currentItem['target_designation'] + '</li>')
      );
    } else {
      let designations = [];
      genericItems.forEach(function (element) {
        if(! designations.includes(element['target_designation'])) {
          designations.push(element['target_designation']);
        }
      });
      designations.forEach(function(element) {
        listWrapper.append(
          $('<li class="">' + (element[0].toUpperCase() + '' + element.substring(1).toLowerCase()) + '</li>')
        );
      });
    }
  }

  function resetModalInputErrors() {
    $('.error-helper').addClass('d-none').html('');
    $('input').removeClass('is-invalid').removeClass('border-danger');
  }

  function computeGenericItems(codes) {
    currentItem = null;
    let result = [];

    window.variables.vars.available_operating_titles.forEach(function(element) {
      let targetId = element['target_id'];
      let targetDesignation = element['target_designation'];

      if(codes.length > 0) {
        if(element['target_turnover'].length > 0 && codes.includes(element['target_code'])) {
          result.push({
            "operating_title_id": targetId,
            "target_designation": targetDesignation,
          })
        }
      } else {
        if(element['target_turnover'].length > 0) {
          result.push({
            "operating_title_id": targetId,
            "target_designation": targetDesignation,
          })
        }
      }
    });

    return result;
  }

  function handleGenerationOptions() {
    let enabled = 0;

    generationOptions.children('li').each(function() {
      let $this = $(this);
      let link = $this.find('a');
      let codes = link.attr('data-bs-targetcodes');
      codes = codes !== undefined && codes.length > 0 ? codes.split(',') : [];

      if(codes.length > 0) {
        let result = 0;
        window.variables.vars.available_operating_titles.forEach(function(element) {
          if(element['target_turnover'].length > 0 && codes.includes(element['target_code'])) {
            result = result + 1;
          }
        });

        if(result > 0) {
          link.removeClass('disabled');
          enabled = enabled + 1;
        } else {
          link.addClass('disabled');
        }
      }
    });

    if(enabled >= 1) {
      generationOptions.find('li a.generate-all').removeClass('disabled');
    } else {
      generationOptions.find('li a.generate-all').addClass('disabled');
    }
  }
})
