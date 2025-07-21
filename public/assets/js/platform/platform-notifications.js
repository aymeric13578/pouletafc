'use strict';

$(function () {
  let notifications = [];
  // Elements ...
  let mainNotificationWrapper = $('#mainNotificationWrapper');
  let notificationBadge = $('#notificationBadge');
  let notification_Modal = $('#notification_Modal');
  let notification_Form = $('#notification_Form');
  let switchStatus = notification_Modal.find('#switchStatus');
  let currentNotification = null;

  // Init ...

  loadNotifications();

  // Handle events

  $('#notification_Modal .btn-close, #notification_Modal #btnCancel').click(function (event) {
    event.preventDefault();
    resetNotificationModal();
  });

  $('#dropdownNotificationsAll').on('click', function (event) {});

  if(switchStatus.length) {
    switchStatus.on('change', function (event) {
      event.preventDefault();

      let jsonData = JSON.parse(Helpers.convertFormToJSON(notification_Form[0]));
      jsonData['notification_id'] = currentNotification.id;

      $.ajax({
        url: notification_Form.attr('action'),
        type: "POST",
        dataType : 'json',
        data: jsonData,
        beforeSend: function () {},
        complete: function () {},
        error: function () {},
        success: function (result) {
          if(result.code === 100) {
            notifications = updateNotificationData(result.data);
            currentNotification = result.data;
            console.dir(currentNotification);

            renderNotificationBadge();
            renderNotificationWrapper(false, true);
            buildModalForNotificationDetails(result.data);
          }
        },
      });
    });
  }

  // Ajax

  function loadNotifications() {
    $.ajax({
      url: window.global_variables.urls.available_notifications,
      type: "GET",
      dataType : 'json',
      beforeSend: function () {
        renderNotificationWrapper(true);
      },
      complete: function () {
        renderNotificationWrapper(false);
      },
      error: function () {},
      success: function (result) {
        if(result.code === 100) {
          notifications = result.data;
        }

        renderNotificationBadge();
        renderNotificationWrapper(false, true);
      },
    });
  }

  // Render

  function emptyNotifications() {
    return $('<li class="">' +
      '<div class="d-flex align-items-center justify-content-center text-muted p-3 h-px-150 w-px-60">' +
      '<div class="">' +
      '' + window.global_variables.messages.no_available_notification + '' +
      '</div>' +
      '</div>' +
      '</li>');
  }

  function renderLoader() {
    return $('<li class="">' +
      '<div class="d-flex align-items-center justify-content-center text-muted p-3 h-px-150 w-px-60">' +
      '<div class="">' +
      '<div class="spinner-border spinner-border-sm text-light" role="status">' +
      '<span class="visually-hidden">Loading...</span>' +
      '</div>' +
      '<div class="d-inline-block ps-3">' + window.global_variables.messages.loading + ' ...</div>' +
      '</div>' +
      '</div>' +
      '</li>');
  }

  function renderNotificationWrapper(loading, refresh = false) {
    if(loading) {
      mainNotificationWrapper.empty().append(
        renderLoader()
      );
    } else {
      if(refresh) {
        if(noNotification()) {
          mainNotificationWrapper.empty().append(
            emptyNotifications()
          );
        } else {
          // Header
          mainNotificationWrapper.empty().append($('<li class="dropdown-menu-header border-bottom mb-0">' +
            '<div class="dropdown-header d-flex align-items-center py-3" style="padding-left: 1.1rem; padding-right: 1.1rem;">' +
            '<h6 class="text-body mb-0 me-auto">Notification</h6>' +
            '<a href="javascript:void(0)" id="dropdownNotificationsAll" class="text-body" data-bs-toggle="tooltip" data-bs-placement="top" title="Mark all as read"><i class="bx fs-4 bx-envelope-open"></i></a>' +
            '</div>' +
            '</li>'));

          let liContent = $('<li class="dropdown-notifications-list scrollable-container"></li>');
          let ulContent = renderUnreadNotification();
          if(notifications.hasOwnProperty('read_notifications') && notifications.read_notifications.length > 0) {
            ulContent.append(renderReadNotification());
          }
          liContent.append(ulContent);

          // Add all content
          mainNotificationWrapper.append(liContent);
        }
      }
    }
  }

  function renderReadNotification() {
    let accordionActiveClass = notifications.unread_notifications.length === 0 ? 'active' : '';
    let collapseVisibilityClass = notifications.unread_notifications.length === 0 ? 'show' : '';

    let liWrapper = $('<li class="list-group-item list-group-item-action px-0 pb-0 mb-0 mt-0 pt-0"></li>');
    let accordionWrapper = $(
      '<div class="accordion">' +
        '<div class="card accordion-item bg-transparent ' + accordionActiveClass + '">' +
          '<h2 class="accordion-header bg-transparent px-0" id="headingOne">' +
            '<button type="button" class="accordion-button text-body" data-bs-toggle="collapse" data-bs-target="#accordionNotification" aria-expanded="true" aria-controls="accordionNotification">' +
            '' + window.global_variables.messages.notification_read + '' +
            '</button>' +
          '</h2>' +
          '' +
          '<div id="accordionNotification" class="accordion-collapse collapse ' + collapseVisibilityClass + '" data-bs-parent="#accordionExample">' +
            '<ul class="list-group list-group-flush read-notification-list">' +
            '' +
            '</ul>' +
          '</div>' +
        '</div>' +
      '</div>');

    for(let index = 0; index < notifications.read_notifications.length; index++) {
      let notification = notifications.read_notifications[index];
      accordionWrapper.find('.read-notification-list').append(renderNotificationItem(
        notification
      ));
    }

    liWrapper.append(accordionWrapper);
    return liWrapper;
  }

  function renderUnreadNotification() {
    let listContent = $('<ul class="list-group list-group-flush"></ul>');

    if(notifications.unread_notifications.length === 0) {
      listContent.append($('<li class="">' +
        '<div class="d-flex align-items-center text-muted p-3 h-px-50 w-px-60">' +
        '<div class="">' +
        '' + window.global_variables.messages.no_available_unread_notification +
        '</div>' +
        '</div>' +
        '</li>'));
    }

    for(let index = 0; index < notifications.unread_notifications.length; index++) {
      let notification = notifications.unread_notifications[index];
      listContent.append(renderNotificationItem(
        notification
      ));
    }

    return listContent;
  }

  function renderNotificationItem(notification) {
    let badgeClass = notification.status === 1 ? "bg-secondary" : "";
    let title = notification.title !== null ? notification.title : "";
    let elementId = notification.status === 1 ? "readNotification_" + notification.id : "unreadNotification_" + notification.id;
    let redirectionRoute = "";
    let createdAt = moment(notification.created_at).format('MM/DD/YYYY HH:MM:ss');
    let titleWrapper = "";
    if(title.length > 0) {
      if(notification.status === 1) {
        titleWrapper = '<h6 class="title mb-1 fw-normal">'+ title +'</h6>';
      } else {
        titleWrapper = '<h6 class="title mb-1 fw-bold">'+ title +'</h6>';
      }
    }

    let notificationItemElement;
    if(notification.id) {
      notificationItemElement = $('' +
        '<li id="' + elementId + '" class="notification-item list-group-item list-group-item-action dropdown-notifications-item cursor-pointer px-3" data-bs-notification="' + notification.id + '" data-bs-status="'+ notification.status +'" data-bs-title="' + title + '" data-bs-content="' + notification.content + '" data-bs-date="'+ createdAt +'" data-bs-target="'+ redirectionRoute +'">' +
        '<div class="d-flex px-0" style="padding-left: 1.2rem; padding-right: 1.2rem;">' +
        '<div class="flex-grow-1">' +
        '' + titleWrapper + '' +
        '<div class="mb-0 textMaxLine">' +
        '' + notification.content + '' +
        '</div>' +
        '<small class="text-muted">' + createdAt + '</small>' +
        '</div>' +
        '<div class="flex-shrink-0 dropdown-notifications-actions">' +
        '<a href="javascript:void(0)" class="dropdown-notifications-read">' +
        '  <span class="state-badge badge badge-dot '+ badgeClass +'"></span>' +
        '</a>' +
        '</div>' +
        '</div>' +
        '</li>');
    } else {
      notificationItemElement = $('' +
        '<li id="' + elementId + '" class="notification-item list-group-item list-group-item-action dropdown-notifications-item cursor-pointer px-3" data-bs-target="'+ notification.redirection +'">' +
          '<div class="d-flex px-0" style="padding-left: 1.2rem; padding-right: 1.2rem;">' +
            '<div class="flex-grow-1">' +
              '' + titleWrapper + '' +
              '<div class="mb-0 textMaxLine">' +
              '' + notification.content + '' +
              '</div>' +
              '<small class="text-muted">' + createdAt + '</small>' +
            '</div>' +
          '</div>' +
        '</li>');
    }

    notificationItemElement.bind("click", {
      notification: notification,
    }, handleNotificationEventSelection);

    return notificationItemElement;
  }

  function renderNotificationBadge() {
    notificationBadge.attr('data-bs-count', notifications.unread_notifications.length);
    if(notifications.unread_notifications.length > 0) {
      notificationBadge.addClass('bg-danger');
      notificationBadge.removeClass('bg-transparent');
    } else {
      notificationBadge.removeClass('bg-danger');
      notificationBadge.addClass('bg-transparent');
    }
    notificationBadge.html(
      notifications.unread_notifications.length > 0
        ? notifications.unread_notifications.length : ''
    );
  }

  function resetNotificationModal() {
    let modalBody = notification_Modal.find('.modal-body');

    if(modalBody.length) {
      modalBody.find('.title').empty();
      modalBody.find('.content').empty();
      modalBody.find('.date').empty();
      switchStatus.prop('checked', false);
      modalBody.find('.read-wrapper').addClass('d-none');
      modalBody.find('.unread-wrapper').addClass('d-none');
    }

    $('html').css('overflow', 'auto');
    notification_Modal.modal('hide');
  }

  function buildModalForNotificationDetails(notification, refresh) {
    let modalBody = notification_Modal.find('.modal-body');

    if(modalBody.length) {
      if(notification.title !== null && notification.title.length > 0) {
        modalBody.find('.title').removeClass('d-none').empty().html(notification.title);
      } else {
        modalBody.find('.title').addClass('d-none');
      }

      modalBody.find('.content').empty().html(notification.content);
      modalBody.find('.date').empty().html(
        moment(notification.created_at).format('MM/DD/YYYY HH:MM:ss'),
      );

      switchStatus.prop('checked', notification.status === 1);
      if(notification.status === 0) {
        modalBody.find('.read-wrapper').removeClass('d-none');
        modalBody.find('.unread-wrapper').addClass('d-none');
      } else {
        modalBody.find('.read-wrapper').addClass('d-none');
        modalBody.find('.unread-wrapper').removeClass('d-none');
      }
    }

    notification_Modal.modal('show');
    $('html').css('overflow', 'hidden');
  }

  function handleNotificationEventSelection(event) {
    let notification = event.data.notification;
    currentNotification = notification;

    if(notification.request) {
      let redirectionUrl = new URL(window.global_variables.urls.redirection_route);
      redirectionUrl.searchParams.set('nt', notification.id);
      redirectionUrl.searchParams.set('ref', notification.request.reference);

      window.location.href = redirectionUrl.toString();
    } else {
      if(notification.id) {
        buildModalForNotificationDetails(notification);
        if(notification.status === 0) {
          switchStatus.trigger('change');
        }
      } else {
        if(notification.redirection && notification.redirection.length > 0) {
          location.href = notification.redirection;
        }
      }
    }
  }

  function updateNotificationData(notification) {
    let source = [
      ...notifications.unread_notifications,
      ...notifications.read_notifications,
    ];
    let index = findNotificationIndex(source, notification.id);
    source[index] = notification;

    // Group by status
    return {
      unread_notifications: source.filter((element) => element.status === 0),
      read_notifications: source.filter((element) => element.status === 1),
    }
  }

  // Utilities

  function noNotification() {
    return notifications.length <= 0 ||
      (notifications.hasOwnProperty('read_notifications') && notifications.read_notifications.length <= 0 && notifications.hasOwnProperty('unread_notifications') && notifications.unread_notifications.length <= 0);
  }

  function findNotificationIndex(source, id) {
    return source.findIndex((notification) => {
      return notification.id === id;
    });
  }
});
