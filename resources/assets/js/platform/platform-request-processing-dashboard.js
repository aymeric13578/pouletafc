'use strict';

document.addEventListener('DOMContentLoaded', function () {
  (function () {
    let wrapperScroll = document.getElementById('wrapper-scroll');
    if (wrapperScroll) {
      new PerfectScrollbar(wrapperScroll, {
        wheelPropagation: true,
        useBothWheelAxes: false,
      });
    }
  })();
});

$(function () {
  const toastAnimationExample = $('.toast-wrapper');
  let requestFilterModal = $('#requestProcessFilter_Modal');
  let filterValues = requestFilterModal.find("select#filterValues");
  let filterTypeValues = requestFilterModal.find("select#filterTypeValues");
  let refControl =  $('.ref-control');
  let toastAnimation;

  /** Init all components */

  initSelect2();
  getToastInstance();
  let allFilterValues = filterValues.find('option');
  let queryTarget = window.variables.request.query;
  let queryRenewal = window.variables.request.renewal;
  let queryRequestStatus = window.variables.request.request_status;

  filterSelect(filterValues, filterTypeValues.val(), allFilterValues, 'data-bs-targettype', queryTarget);

  /** Events handler */

  refControl.on('keyup', function(event){
    if (event.keyCode === 13) {
      computeSearchFilter();
    }
  });

  $('.mark-request-for-processing').on('click', function(event) {
    event.preventDefault();
    let $this = $(this);

    // Progress ...
    handleToastProgress();

    setTimeout(function() {
      let wrapperId = $this.attr('data-bs-targetrequest');
      $.ajax({
        url: $this.attr('href').replace(/id_/gi, wrapperId),
        type:'POST',
        dataType: "json",
        data: {
          "_token": window.variables.request.csrf_token,
        },
        complete: function () {},
        error: function () {
          handleToastError(window.variables.messages.unable_to_process_request);
        },
        success: function (result) {
          if(result.code === 100) {
            toastAnimation.dispose();
            getToastInstance();
            renderProcessingUser(wrapperId, result.data);
          } else {
            handleToastError(result.message);
          }
        },
      });
    }, 2000);
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

  requestFilterModal.find('button#btnSave').on('click', function(event) {
    computeSearchFilter(false);
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

  function getToastInstance() {
    toastAnimation = new bootstrap.Toast(toastAnimationExample, {
      autohide: false
    });
  }

  /** Render elements */

  function handleToastProgress() {
    toastAnimationExample.find('button.btn-close').addClass('d-none');
    toastAnimationExample.find('div.toast-body').empty().append(
      $(
        `
        <div class="d-flex align-items-start mb-4">
          <div class="spinner-border spinner-border-md text-secondary" role="status"></div>
          <span class="ps-4 fs-6 text-truncate" style="padding-top: 0.40rem;">${window.variables.messages.processing_in_progress} ...</span>
        </div>
       `
      )
    );
    toastAnimation.show();
  }

  function handleToastError(message) {
    toastAnimationExample.find('button.btn-close').removeClass('d-none');
    toastAnimationExample.find('div.toast-body').empty().append(
      $(
        `
          <div class="d-flex align-items-start mb-4">
            <span class="tf-icons bx bx-info-circle fs-4 me-1 mt-1""></span>
            <span class="ps-4 fs-6 text-wrap" style="padding-top: 0.16rem;">${message}</span>
          </div>
        `
      )
    );
    toastAnimation.show()
  }

  function renderProcessingUser(wrapperId, request) {
    let wrapper = $('#requestWrapper_' + wrapperId);
    let routeValue = window.variables.routes.requests_processing_index + '?' + new URLSearchParams({q: wrapperId, type: 'process'}).toString();

    wrapper.find('a.request__itemAnchor').attr('href', routeValue);

    // Remove mark processing menu
    wrapper.find('.request__optionsDropdownMenu .request__menuOptionMarkForProcessing').replaceWith(`
      <a class="dropdown-item mark-request-for-processing text-secondary request__menuOptionMarkForProcessing" href="${routeValue}" data-bs-targetrequest="${wrapperId}">
        <i class="bx bx-task me-3"></i><span class="text-truncate">${window.variables.messages.continue_processing}</span>
      </a>`
    )

    // Add processing user
    let processingUserWrapper = wrapper.find('.request__processingUserWrapper');
    processingUserWrapper.empty();

    let processingUser = request.processing_user;
    if(processingUser.avatar_url === null) {
      processingUserWrapper.append(
        `
        <div class="avatar avatar-sm me-2 cursor-pointer" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <span class="avatar-initial rounded-circle ${randomBgLabel()}">
            ${Helpers.initials(processingUser.full_name)}
          </span>
        </div>
        `
      );
    } else {
      processingUserWrapper.append(
        `
        <div class="avatar avatar-sm ms-2 cursor-pointer" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <img src="${processingUser.avatar_url}" alt="" class="rounded-circle">
        </div>
        `
      );
    }

    processingUserWrapper.append(renderProcessingUserDropdown(processingUser));
  }

  function renderProcessingUserDropdown(processingUser) {
    // Avatar and name
    let avatarWrapper = null;
    if(processingUser.avatar_url === null) {
      avatarWrapper = (
        `
        <div class="avatar avatar-md ms-2 align-self-end cursor-pointer">
          <span class="avatar-initial rounded-circle ${randomBgLabel()}">
            ${Helpers.initials(processingUser.full_name)}
          </span>
        </div>
        `
      );
    } else {
      avatarWrapper = (
        `
        <div class="avatar avatar-md ms-2 cursor-pointer">
          <img src="${processingUser.avatar_url}" alt="" class="rounded-circle">
        </div>
        `
      );
    }
    // staff post
    let staffPostWrapper = null;
    if(processingUser.staff_post !== null) {
      staffPostWrapper = `<small class="text-muted text-truncate d-block mt-1">${processingUser.staff_post}</small>`;
    }

    // Details wrapper
    let detailsWrapper = $("<div />", {class: "px-3 pt-2"});
    detailsWrapper.append(`<p>${window.variables.messages.request_is_processing_by.replace(/name_/gi, processingUser.full_name)}</p>`);
    if(processingUser.staff_post !== null) {
      detailsWrapper.append(`
          <h6 class="dropdown-header p-0 m-0 mb-3 text-uppercase">${window.variables.messages.details}</h6>
          <span class="d-block text-muted">${window.variables.messages.unit_management}</span>
          <p class="">${processingUser.unit}</p>
          <span class="d-block text-muted">${window.variables.messages.staff_service}</span>
          <p class="">${processingUser.staff_service}</p>
      `);
    }

    let inner_1 = $("<div />", {class: "py-2"});
    return $("<div />", {
      class: "dropdown-menu w-px-350 text-wrap",
    }).append(
      inner_1.append(
        `
          <div class="d-flex align-items-start justify-content-between mb-3">
            <div class="flex-shrink-0 ms-1">${avatarWrapper}</div>
            <div class="ms-2 flex-grow-1">
               <h6 class="text-truncate fw-semibold m-0 mt-2">${processingUser.full_name}</h6>
              ${staffPostWrapper !== null ? staffPostWrapper : ''}
            </div>
            <button type="button" class="btn-close fs-tiny me-3 mt-1" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <hr class="dropdown-divider">
          ${$("<div />").append(detailsWrapper).html()}
          <hr class="dropdown-divider">
          <div class="px-3 pt-1">
            <small class="text-truncate" data-bs-toggle="tooltip" data-bs-offset="0,4" data-bs-placement="bottom" data-bs-html="true" title="<small><i class='tf-icons bx bx-time-five fs-6 me-1'></i>${window.variables.messages.since_at} ⸱ ${processingUser.processing_start_date}</span>">
              <i class="tf-icons bx bx-time-five fs-6 me-1"></i>
              ${window.variables.messages.since_at} ⸱ ${processingUser.processing_start_date}
            </small>
          </div>
         `
      )
    );
  }

  function computeSearchFilter(withRef = true) {
    if (withRef && refControl.val().length === 0) {
      return false;
    }

    let url = new URL(window.variables.routes.requests_processing_index);
    let urlParams = new URLSearchParams(url.search);

    if (withRef) {
      urlParams.set('ref', refControl.val());

      if (queryTarget && queryTarget.length > 0) {
        urlParams.set('q', queryTarget);
      }
      if (type && queryTarget.length > 0) {
        urlParams.set('type', type);
      }
      if (queryRenewal && queryTarget.length > 0) {
        urlParams.set('renewal', queryRenewal);
      }
      if (queryRequestStatus && queryTarget.length > 0) {
        urlParams.set('status', queryRequestStatus);
      }
    } else {
      if (refControl.val().length > 0) {
        urlParams.set('ref', refControl.val());
      }

      // Add type value
      let selectedFilter = filterValues.find('option:selected');
      if (filterValues.val().length > 0) {
        urlParams.set('q', filterValues.val());
        urlParams.set('type', selectedFilter.attr('data-bs-type'));
      }

      // Other filters ...
      let renewalFilters = $('input[name=renewal]:checked');
      let requestStatusFilters = $('input[name=request-status]:checked');
      if(renewalFilters.length > 0 && renewalFilters.val() !== '-1') {
        urlParams.set('renewal', renewalFilters.val());
      }
      if(requestStatusFilters.length > 0 && requestStatusFilters.val() !== '-1') {
        urlParams.set('status', requestStatusFilters.val());
      }
    }

    window.location.search = urlParams.toString();
  }

  /** Utilities */

  function randomBgLabel() {
    let bgs = [
      'bg-label-secondary',
      'bg-label-primary',
      'bg-label-success',
      'bg-label-info',
    ];

    return bgs[Math.floor(Math.random() * bgs.length)];
  }

  function filterSelect(selectEl, value, catalog, attr, selectorValue) {
    selectEl.find('option').remove();

    catalog.each(function(index) {
      if($(this).attr('value').length === 0 || (value && attr && $(this).attr(attr) === value)) {
        $(this).prop('selected', $(this).attr('value').length !== 0 && selectorValue === $(this).attr('value'));
        selectEl.append($(this));
      }
    });
    selectEl.trigger('change');
  }
});
