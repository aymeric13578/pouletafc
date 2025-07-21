'use strict';

$(function () {
  let wrapper = $('#requestProcessActions');
  let canvasBody = $('.offcanvas-body');
  let topSpacing;
  const stickyEl = $('.sticky-element');

  /** Init */

  // // Set topSpacing if the navbar is fixed
  // if (Helpers.isNavbarFixed()) {
  //   topSpacing = $('.layout-navbar').height() + 7;
  // } else {
  //   topSpacing = 0;
  // }

  // sticky element init (Sticky Layout)
  // if (stickyEl.length) {
  //   stickyEl.sticky({
  //     topSpacing: topSpacing,
  //     zIndex: 9
  //   });
  // }

  // if($('#processDetailsWrapper').length > 0) {
  //   new PerfectScrollbar('#processDetailsWrapper', {
  //     wheelPropagation: false
  //   });
  // }

  if(canvasBody.length > 0) {
    canvasBody.each(function(index, el) {
      new PerfectScrollbar(el, {
        wheelPropagation: true
      });
    });
  }

  if(wrapper.find('div.actions-wrapper button').toArray().length <= 0) {
    wrapper.addClass('d-none');
  } else {
    wrapper.removeClass('d-none');
  }

  // Event handler

  $('body').on('click', '.processing-checkpoint-task:checkbox', function(e) {
    let $this = $(this);
    $this.parent().find('label').toggleClass('text-strike-through');

    // Var ...
    let checkTaskForm = $('#checkTaskForm');
    let jsonData = {
      'checkpoint': $this.attr('value'),
      'status':  $this.is(":checked") ? 1 : 0
    };

    $.ajax({
      url: checkTaskForm.attr('action'),
      type: "POST",
      dataType : 'json',
      data: jsonData,
      beforeSend: function () {},
      complete: function () {},
      error: function () {
        if(jsonData.status === 1) {
          $this.prop("checked", false);
          $this.parent().find('label').removeClass('text-strike-through');
        } else {
          $this.prop("checked", true);
          $this.parent().find('label').addClass('text-strike-through');
        }
      },
      success: function (result) {}
    });
  });
});
