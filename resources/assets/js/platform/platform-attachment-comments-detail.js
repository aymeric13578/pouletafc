'use strict';

$(function () {
  let canvas = $('#offCanvasAttachmentObservations');
  let attachmentTarget;

  /** Event handler */

  canvas.on('click', 'button#btnReloadAttachmentComment', function(event) {
    event.preventDefault();
    // Request
    loadAttachmentComment();
  });

  $('#attachmentTable').on('click', '.show-comment-option', function(event) {
    event.preventDefault();
    let $this = $(this);

    attachmentTarget = $this.attr('data-bs-attachment');
    canvas.offcanvas('show');

    // Request
    // loadAttachmentComment();
  });

  /** Render */

  function renderLoader() {
    canvas
      .find('div.offcanvas-body')
      .empty()
      .append('<div class="d-flex align-items-center justify-content-center h-100">\n' +
        '        <div class="">\n' +
        '          <div class="spinner-border spinner-border-sm text-light" role="status">\n' +
        '            <span class="visually-hidden">Loading...</span>\n' +
        '          </div>\n' +
        '          <div class="d-inline-block ps-3">' + window.variables.messages.loading_data + ' ...</div>\n' +
        '        </div>\n' +
        '      </div>');
  }

  function renderError(message) {
    let error = message ??  window.variables.messages.unable_to_load_data;

    canvas
      .find('div.offcanvas-body')
      .empty()
      .append('<div class="d-flex align-items-center justify-content-center h-100">\n' +
        '        <div class="text-center">\n' +
        '          <span class="badge bg-label-secondary p-2 rounded-pill me-2">\n' +
        '            <i class="tf-icons bx bx-error-circle bx-sm"></i>\n' +
        '          </span>\n' +
        '          <div class="mt-3 w-px-300">\n' +
        '            <p class="m-0 p-0 mb-3">\n' +
        '              '+ error +
        '            </p>\n' +
        '            <button id="btnReloadAttachmentComment" type="button" class="btn btn-label-secondary">\n' +
        '              <i class="tf-icons bx bx-revision bx-sm me-1"></i>\n' +
        '              ' + window.variables.messages.retry +
        '            </button>\n' +
        '          </div>\n' +
        '        </div>\n' +
        '      </div>');
  }

  function renderCommentItem(comment) {
    let creatorUser = comment.creator_user;
    let avatar;
    let nameWrapper = $('<div class="flex-grow-1"></div>');
    let root = $('<div class=""></div>');

    let avatarWrapper = $('<div class="d-flex align-items-center mb-2"></div>');
    if(creatorUser.avatar_url === null) {
      avatar = $('<div class="avatar avatar-sm flex-shrink-0 cursor-pointer me-2 mt-2">\n' +
        '    <span class="avatar-initial rounded-circle bg-label-secondary">\n' +
        '       ' + creatorUser.initials +'\n' +
        '    </span>\n' +
        '</div>');
    } else {
      avatar = $('<div class="avatar flex-shrink-0 avatar-sm cursor-pointer me-2 mt-2">\n' +
        '    <img src="'+ creatorUser.avatar_url +'" alt="Avatar" class="rounded-circle">\n' +
        '</div>');
    }
    avatarWrapper.append(avatar);
    nameWrapper.append($(' <h6 class="text-truncate fw-semibold m-0 p-0 mt-3">\n' +
        '   '+ creatorUser.name + '\n' +
      '</h6>'));
    if(creatorUser.staff_post !== null) {
      nameWrapper.append($('<small class="text-muted text-truncate d-block">'+ creatorUser.staff_post +'</small>'));
    }
    avatarWrapper.append(nameWrapper);
    avatarWrapper.append('<small class="text-muted mt-1">\n' +
      '   '+ comment.date +'\n' +
      '</small>');

    let instructionWrapper = $('<div class="pt-1 pb-1 px-4 mb-3 mt-2 comment-bubble-item bg-lighter border-light-light"></div>');
    if(comment.required_action) {
      let intervenedWrapper = null;
      if(comment.intervened_date !== null) {
        intervenedWrapper = $('<span class="ms-1 fs-6">\n' +
          ' ⸱ '+ window.variables.messages.intervened_on + ' ' + comment.intervened_date + '\n' +
          '</span>');
      }
      instructionWrapper.append($('<div class="mt-3 me-2 d-flex align-items-center">\n' +
        '  <span class="badge bg-label-dribbble text-truncate fs-tiny" data-bs-toggle="tooltip" data-bs-offset="0,4" data-bs-placement="right" data-bs-html="true" title="'+ window.variables.messages.required_applicant_action +'">\n' +
        '    '+ window.variables.messages.required_action +'\n' +
        '  </span>\n' + (intervenedWrapper === null ? '' : intervenedWrapper.html()) +
        '</div>'));
    }
    instructionWrapper.append($('<div class="mt-3 fs-6 text-body">\n' +
      '   '+ comment.instructions +'\n' +
      '</div>'));

    root.append(avatarWrapper);
    root.append(instructionWrapper);
    return root;
  }

  function renderAttachmentDetails(result) {
    let attachment = result['attachment'];
    let comments = result['comments'];

    let wrapper = $('<div class="px-4 py-2"></div>');

    // Header ...
    let dHeader = $('<div class="d-flex align-items-center">\n' +
      '          <p class="m-0 flex-grow-1 text-wrap">\n' +
      '            '+ attachment.attachment_type.designation +
      '            <span class="badge '+ attachment.extension_bg +' d-inline-block px-1 text-uppercase">\n' +
      '              \n'+ attachment.extension +
      '            </span>\n' +
      '          </p>\n' +
      '          <a href="'+ attachment.attachment_url + '" class="btn btn-label-secondary text-decoration-none btn-sm flex-shrink-0" target="blank_">\n' +
      '            '+ window.variables.messages.display +
      '          </a>\n' +
      '        </div>');
    wrapper.append(dHeader);
    wrapper.append('');

    let accordionWrapper = $('<div class="accordion mt-3"></div>');
    // Comments ...
    let accordionCommentBody = $('<div class="accordion-body py-0 px-0"></div>');
    comments.forEach(function(comment) {
      accordionCommentBody.append(renderCommentItem(comment));
    });

    let commentAccordionItem = $('<div class="accordion-item active px-0 mx-0 shadow-none">\n' +
      '     <h2 class="accordion-header d-flex align-items-center py-0 my-0">\n' +
      '       <button type="button" class="accordion-button mx-0 px-0" data-bs-toggle="collapse" data-bs-target="#attachment_commentsAccordion" aria-expanded="true">\n' +
      '         '+ window.variables.messages.comments +' ('+ comments.length + ')\n' +
      '       </button>\n' +
      '     </h2>' +
      '     <div id="attachment_commentsAccordion" class="accordion-collapse collapse my-0 show">' +
      '       '+ accordionCommentBody.html() + '' +
      '     </div>' +
      '   </div>');
    accordionWrapper.append(commentAccordionItem);
    wrapper.append(accordionWrapper);

    canvas
      .find('div.offcanvas-body')
      .empty()
      .append(wrapper);
  }

  /** Utilities */

  function loadAttachmentComment() {
    $.ajax({
      url: window.variables.routes.attachment_comments.replace(/id_/gi, attachmentTarget),
      type:'GET',
      dataType: "json",
      beforeSend: function () {
        renderLoader();
      },
      error: function () {
        renderError();
      },
      success: function (result) {
        if(result.code === 100) {
          renderAttachmentDetails(result.data);
        } else {
          renderError(result.message);
        }
      },
    });
  }
});
