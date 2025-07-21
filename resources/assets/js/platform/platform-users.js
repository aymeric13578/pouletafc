'use strict';

$(function () {
  let usersData = null;
  let userProfileLabels = null;
  let usersDataTable = $('#usersDataTable'), dtBasic;
  let globalAlert = $('#globalAlert');

  // Events handler
  $('#globalAlert .btn-close').click(function (event) {
    event.preventDefault();
    Helpers.handleAlertVisibility(globalAlert, false);
  });

  // Ajax main ...

  loadPageData();

  // Others methods ...

  function loadPageData() {
    $.ajax({
      url: window.variables.routes.all_users,
      ataType: "json",
      beforeSend: function () {
        $('.global-spinner').removeClass('d-none');
      },
      complete: function () {
        $('.global-spinner').addClass('d-none');
      },
      error: function () {},
      success: function (result) {
        if(result.code === 100) {
          usersData = result.data;
          userProfileLabels = computeUserProfileColor();

          initUsersDataTable();
          $('.users-wrapper').removeClass('d-none');
        } else {}
      },
    });
  }

  // Data table  ...

  function initUsersDataTable() {
    if (usersDataTable.length) {
      dtBasic = usersDataTable.DataTable(dataTableOption(usersData));
      datatableActionEvent();

      // Filter form control to default size
      // ? setTimeout used for multilingual table initialization
      setTimeout(() => {
        $('.dataTables_filter .form-control').removeClass('form-control-sm').attr('placeholder', window.variables.messages.search + " ...");
        $('.dataTables_length .form-select').removeClass('form-select-sm');
      }, 200);
    }
  }

  function dataTableOption(data) {
    return {
      data: JSON.parse(JSON.stringify(data)),
      paging: true,
      ordering: true,
      info: true,
      displayLength: 10,
      searching: true,
      lengthChange: true,
      orderCellsTop: true,
      order: [[0, "asc"]],
      dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>><"table-responsive"t><"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
      language: window.variables.messages.tables,
      columnDefs: [
        {
          targets: '_all',
          defaultContent: "--",
          className: ''
        },
        {
          targets: 0,
          searchable: false,
          orderable: false,
          render: function (data, type, full, meta) {
            return '#' + (meta.row + 1);
          }
        },
        {
          targets: 1,
          orderable: true,
          searchable: true,
          render: function (data, type, full, meta) {
            let wrapper = null;
            let fullName = full.person.last_name + ' ' + full.person.first_name;

            if(full.avatar_url) {
              wrapper = '' +
                '<div class="d-flex align-items-center">' +
                '<div class="avatar avatar-sm me-2">' +
                '<img src="'+ full.avatar_url +'" alt class="w-px-30 h-auto rounded-circle me-2">' +
                '</div>' +
                '<span class="">'+ Helpers.ucWords(fullName) +'</span>' +
                '<div">' +
                '';
            } else {
              wrapper = '' +
                '<div class="d-flex align-items-center">' +
                '<div class="avatar avatar-sm me-2">' +
                '<span class="avatar-initial rounded-circle '+ Helpers.randomBgLabelColor() +' ">'+ Helpers.initials(fullName) +'</span>' +
                '</div>' +
                '<span class="">'+ Helpers.ucWords(fullName) +'</span>' +
                '<div">' +
                ''
            }

            return (
              '<div class="d-flex align-items-center flex-nowrap">' +
              '' + wrapper + '' +
              '</div>'
            );
          }
        },
        {
          targets: 2,
          orderable: true,
          searchable: true,
          render: function (data, type, full, meta) {
            if(full.user_profile) {
              return '<span class="badge '+ userProfileLabels[full.user_profile.label.toLowerCase()] +'">' + full.user_profile.label + '</span';
            }

            return '--';
          }
        },
        {
          targets: 3,
          orderable: true,
          searchable: true,
          render: function (data, type, full, meta) {
            return full.username;
          }
        },
        {
          targets: 4,
          orderable: true,
          searchable: true,
          render: function (data, type, full, meta) {
            if(full.person.staff && full.person.staff.staff_post) {
              return full.person.staff.staff_post.designation;
            }

            return '--';
          }
        },
        {
          targets: 5,
          orderable: true,
          searchable: true,
          render: function (data, type, full, meta) {
            if(full.person.staff && full.person.staff.staff_service) {
              return '<div class="text-wrap w-px-200">'+ full.person.staff.staff_service.designation +'</div>';
            }

            return '--';
          }
        },
        {
          targets: 6,
          orderable: true,
          searchable: true,
          render: function (data, type, full, meta) {
            let bgColor = full.disabled_at !== null ? 'bg-label-danger' : 'bg-label-secondary';

            return '<span class="badge '+ bgColor +'">' + full.status_message.toUpperCase() + '</span';
          }
        },
        {
          targets: 7,
          orderable: false,
          searchable: false,
          className: 'dt-right',
          render: function (data, type, full, meta) {
            let deleteMessage = window.variables.messages.delete_user_confirmation.replace(/:name/gi, Helpers.ucWords(full.person.last_name + ' ' + full.person.first_name));

            let actionWrapper =  $('<div class=""></div>');

            let editRoute = window.variables.routes.edit_user.replace(/idv/gi, full.id );
            let editAction =  $(
              '<a href="'+ editRoute +'" target="_blank" data-target="'+ full.id +'" type="button" class="btn btn-icon btn-label-primary btn-sm ms-2 btn-edit"><span class="tf-icons bx bx-edit"></span></a>'
            );

            let showRoute = window.variables.routes.show_user.replace(/idv/gi, full.id );
            let showAction =  $(
              '<a href="'+ showRoute +'" target="_blank" class="btn btn-icon btn-label-secondary btn-sm ms-2"><span class="tf-icons bx bx-show"></span></a>'
            );

            let trashDropContent = ('' +
              '<div class="dropdown-menu dropdown-menu-end w-px-250">' +
              '<div class="pt-2 px-3">' +
              '<span class="fs-6 text-wrap">' + deleteMessage + '</span>' +
              '</div>' +
              '<div class="dropdown-divider"></div>' +
              '<div class="pt-1 pb-2 px-3">' +
              '<button type="button" class="btn btn-sm btn-danger d-block btn-delete" data-bs-target="'+ full.id +'">'+ window.variables.messages.delete_user +'</button>' +
              '</div>' +
              '</div>' +
              ''
            );

            let deleteAction =  $(
              '<div class="btn-group dropstart">' +
              '<button type="button" class="btn btn-icon btn-label-warning btn-sm ms-2 btn-trash" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' +
              '<span class="tf-icons bx bx-trash"></span>' +
              '</button>' +
              '' + trashDropContent +
              '</div>'
            );

            actionWrapper.append(showAction);
            actionWrapper.append(editAction);
            if(full.is_deletable) {
              actionWrapper.append(deleteAction);
            }

            return actionWrapper.html()
          }
        }
      ]
    };
  }

  function computeUserProfileColor() {
    let labels = {};
    let bgColors = [
      'bg-label-dribbble',
      'bg-label-dark',
      'bg-label-gray',
      'bg-label-facebook',
      'bg-label-danger',
      'bg-label-info',
      'bg-label-primary',
      'bg-label-secondary',
      'bg-label-slack',
      'bg-label-reddit',
      'bg-label-success',
      'bg-label-youtube',
      'bg-label-twitter',
      'bg-label-vimeo',
    ];

    usersData.forEach(function(user) {
      if(user.user_profile) {
        let label = user.user_profile.label.toLowerCase();
        let labelSize = Object.keys(labels).length;

        if(Object.keys(labels).indexOf(label) === -1){
          labels[label] = labelSize < bgColors.length ? bgColors[labelSize] : 'bg-label-primary';
        }
      }
    });

    return labels;
  }

  function datatableActionEvent() {
    let tbody = $('#usersDataTable tbody');
    tbody.on('click', 'td button.btn-delete', function (event) {
      event.preventDefault();
      let button = $(this);

      let blockUi = $('.light-overlay');
      let dynamicCardHeader = $('.dynamic-card-header');
      let localSpinner = $('.local-spinner');
      let url = window.variables.routes.delete_user.replace(/:id_/gi, button.attr('data-bs-target'));

      $.ajax({
        url: url,
        type: "DELETE",
        dataType: "json",
        data: {
          "_token": window.variables.constants.csrf_token,
        },
        beforeSend: function () {
          blockUi.addClass('show');
          dynamicCardHeader.addClass('d-none');
          localSpinner.removeClass('d-none');
          Helpers.handleAlertVisibility(globalAlert, false);
        },
        complete: function () {
          blockUi.removeClass('show');
          dynamicCardHeader.removeClass('d-none');
          localSpinner.addClass('d-none');
        },
        error: function () {
          Helpers.handleAlertVisibility(
            globalAlert,
            true,
            'alert-danger',
            window.variables.messages.unable_to_delete_user,
          );
        },
        success: function (result) {
          if (result.code === 100) {
            Helpers.handleAlertVisibility(globalAlert, true, 'alert-success ', result.message);

            // Delete row data table ...
            dtBasic.row(button.parents('tr')).remove().draw();
          } else {
            Helpers.handleAlertVisibility(globalAlert, true, 'alert-danger', result.message);
          }
        }
      });
    });
  }
});
