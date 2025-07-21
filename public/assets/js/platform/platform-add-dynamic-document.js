'use strict';

function create_dynamic_document(selector, options) {
  let selectorEl;
  let rowWrapperEl;

  return {
    init: function init() {
      if($(selector).length > 0) {
        selectorEl = $(selector);
      } else {
        throw new Error('Invalid selector ' + selector);
      }

      selectorEl.empty();
      // Add button
      let actionButton = $('<a href="javascript:void(0);" id="addAttchmentAction" class="px-0 text-primary text-decoration-none">\n' +
        '  <i class="tf-icons bx bx-link me-1"></i>\n' +
        '  '+ (options.messages ? options.messages.actionTitle ?? '' : '') +'\n' +
        '</a>');
      selectorEl.append(actionButton);
      // Add row wrapper
      rowWrapperEl = $('<div id="dynamic_documentWrapperRow" class="mt-3 mb-2"><div');
      selectorEl.append(rowWrapperEl);
      // Bind event
      actionButton.bind('click', function(event) {
        event.preventDefault();
        // Add row
        self.addRow();
      });

      self = this;
      return self;
    },
    addRow: function addRow() {
      let countEl = rowWrapperEl.find('div.row-element').length;
      let rowId = "docRow_" + (countEl + 1);
      let attachmentTypeName = 'attachment_type_id_'+ (countEl + 1);
      let attachmentName = 'attachment_'+ (countEl + 1);

      // Add row element
      let colWrapper = $('<div class="row row-element mb-2" id="'+ rowId +'">\n' +
        '                <div class="col-md-5 col-12">\n' +
        '                  <select id="" class="select2 form-select '+ attachmentTypeName +'" name="'+ attachmentTypeName +'">\n' +
        '                    <option value="">'+ (options.messages ? options.messages.selectPlaceholder ?? '' : '') +'</option>\n' +
        '                  </select>\n' +
        '                  <div id="'+ attachmentTypeName +'ErrorHelper" class="error-helper text-danger fs-6 mt-2 d-none"></div>\n' +
        '                </div>\n' +
        '                <div class="col-md-6 col-12 ps-0 ps-sm-0">\n' +
        '                  <input class="form-control  '+ attachmentName +'" type="file" name="'+ attachmentName +'" accept="'+ (options.allowed_files ?? '*') +'">\n' +
        '                  <div id="'+ attachmentName +'ErrorHelper" class="error-helper text-danger fs-6 mt-2 d-none"></div>\n' +
        '                </div>\n' +
        '              </div>');
      let removeButton =  $('<a href="javascript:void(0);" class="btn btn-label-secondary btn-icon" data-bs-row="'+ rowId +'">\n' +
        '  <i class="bx bx-x"></i>\n' +
        '</a>'
      );
      colWrapper.append(
        $('<div class="col-md-1 col-12 ps-0 ps-sm-0 text-end"></div>').append(removeButton)
      );
      rowWrapperEl.append(colWrapper);
      // Add remove event
      removeButton.bind('click', function(event) {
        event.preventDefault();
        let $this = $(this);
        // Remove row
        rowWrapperEl.find('div#' + $this.attr('data-bs-row')).remove();
      });
    },
  };
}
