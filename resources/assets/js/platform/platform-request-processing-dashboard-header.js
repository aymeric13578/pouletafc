'use strict';

$(function () {
  let requestFilterModal = $('#requestProcessFilter_Modal');
  let filterValues = requestFilterModal.find("select#filterValues");
  let filterTypeValues = requestFilterModal.find("select#filterTypeValues");
  let refControl =  $('.ref-control');

  /** Init all components */

  initSelect2();
  let allFilterValues = filterValues.find('option');
  let queryTarget = window.variables.request.query;
  let type = window.variables.request.type;

  filterSelect(filterValues, filterTypeValues.val(), allFilterValues, 'data-bs-targettype', queryTarget);

  /** Events handler */

  refControl.on('keyup', function(event){
    if (event.keyCode === 13) {
      computeSearchFilter();
    }
  });

  requestFilterModal.find('button#btnSave').on('click', function(event) {
    event.preventDefault();

    computeSearchFilter(false);
  });

  filterTypeValues.on('change', function(event) {
    event.preventDefault();
    filterSelect(filterValues, filterTypeValues.val(), allFilterValues, 'data-bs-targettype');
  });

  $('#requestProcessFilter_Modal .btn-close, #requestProcessFilter_Modal #btnCancel').click(function (event) {
    event.preventDefault();
    requestFilterModal.modal('hide');
  });

  $('#btnModalFilter').click(function (event) {
    event.preventDefault();
    requestFilterModal.modal('show');
  });

  /** Functions */

  function initSelect2() {
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
  }

  /** Render elements */

  function computeSearchFilter(withRef = true) {
    if (withRef && refControl.val().length === 0) {
      return false;
    }

    let url = new URL(window.variables.routes.request_tracking);
    let urlParams = new URLSearchParams(url.search);

    if (withRef) {
      urlParams.set('ref', refControl.val());

      if (queryTarget && queryTarget.length > 0) {
        urlParams.set('q', queryTarget);
      }
      if (type && queryTarget.length > 0) {
        urlParams.set('purpose', type);
      }

      window.location.search = urlParams.toString();
    } else {
      if (refControl.val().length > 0) {
        urlParams.set('ref', refControl.val());
      }

      // Add type value
      let selectedFilter = filterValues.find('option:selected');
      if(filterValues.val().length > 0) {
        urlParams.set('q', filterValues.val());
      }
      if(filterTypeValues.val() !== "0") {
        urlParams.set('purpose', selectedFilter.attr('data-bs-type'));
      }

      window.location.search = urlParams.toString();
    }
  }

  /** Utilities */

  function filterSelect(selectEl, value, catalog, attr, selectorValue) {
    selectEl.find('option').remove();

    if(value === "0") {
      selectEl.prop('disabled', true);
    } else {
      selectEl.prop('disabled', false);
    }

    catalog.each(function(index) {
      if($(this).attr('value').length === 0 || (value && attr && $(this).attr(attr) === value)) {
        $(this).prop('selected', $(this).attr('value').length !== 0 && selectorValue === $(this).attr('value'));
        selectEl.append($(this));
      }
    });
    selectEl.trigger('change');
  }
});
