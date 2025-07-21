'use strict';

$(function () {
  let theme = $('html').hasClass('light-style') ? 'default' : 'default-dark';
  // Init ...
  let checkboxTree = $('#jstree-checkbox');
  let selectedTreeElements = [];
  let menuData = null;
  let profilesData = null;
  let addingProfile = false;
  let inputProfileName = $('#profileName');
  let profileDataTable = $('.datatables-basic'), dtBasic;
  let btnMenuHeader = $('#btnMenuHeader');
  let globalAlert = $('#globalAlert');

  // Ajax main ...
  loadPageData();

  // Others methods ...
  function loadPageData() {
    $.ajax({
      url: window.variables.routes.add_menu,
      ataType: "json",
      beforeSend: function () {
        $('.global-spinner').removeClass('d-none');
        $('#globalError').addClass('d-none');
      },
      complete: function () {
        $('.global-spinner').addClass('d-none');
        $('#globalError').addClass('d-none');
      },
      error: function () {
        $('#globalError').addClass('d-none');
      },
      success: function (result) {
        if(result.code === 100) {
          menuData = result.data['menus'];
          profilesData = result.data['profiles'];

          // Init menus and data table ...
          initMenuTree();
          initProfileDataTable();

          $('.profile-wrapper').removeClass('d-none');
        } else {
          $('#globalError').addClass('d-none');
        }
      },
    });
  }

  function initMenuTree() {
    if (checkboxTree.length) {
      checkboxTree
        .on('changed.jstree', function (e, data) {
          if(data.action === "select_node") {
            selectedTreeElements.push(data.node.id);
            selectedTreeElements = selectedTreeElements.concat(data.node.children);
          } else {
            selectedTreeElements = selectedTreeElements.filter(e => e !== data.node.id);
            selectedTreeElements = selectedTreeElements.filter(function (value) {
              return data.node.children.indexOf(value) < 0;
            });
          }
          // Remove duplicate values
          selectedTreeElements = selectedTreeElements.filter((element, index) => {
            return selectedTreeElements.indexOf(element) === index;
          });
        })
        .jstree({
          core: {
            themes: {
              name: theme
            },
            data: menuData,
          },
          checkbox : {
            "keep_selected_style" : false
          },
          plugins: ['types', 'checkbox', 'search'],
          types: {
            default: {
              icon: 'bx bx-coin-stack'
            },
          }
        });

      let to = false;
      $('.menu-search').bind( "keyup", function() {
        if(to) { clearTimeout(to); }

        to = setTimeout(function () {
          let value = $(".menu-search").val();
          checkboxTree.jstree(true).search(value);
        }, 250);
      });
      $('#btnCancelProfile').bind('click', function(event) {
        event.preventDefault();

        if(! addingProfile) {
          resetInputs();
        }
      });
      $('#btnAddProfile').bind('click', function(event) {
        event.preventDefault();
        if(! addingProfile) {
          if(inputProfileName.val().length === 0) {
            location.href = "#profileNameAnchor";
            inputProfileName.focus();
            return;
          }

          if(selectedTreeElements.length === 0) {
            btnMenuHeader.addClass('text-danger');
            btnMenuHeader.html(window.variables.messages.error_select_menu);
            location.href = "#jstree-checkboxAnchor";
            return;
          } else {
            btnMenuHeader.removeClass('text-danger');
            btnMenuHeader.html(window.variables.messages.available_menu);
          }

          let jsonData = {
            'profile_name': inputProfileName.val(),
            'menus': selectedTreeElements,
            "_token": window.variables.constants.csrf_token,
          };

          $.ajax({
            url: window.variables.routes.create_profile,
            type:'POST',
            dataType: "json",
            data: jsonData,
            beforeSend: function () {
              $('#btnCancelProfile').addClass('d-none');
              $('.request-spinner').removeClass('d-none');
              Helpers.handleAlertVisibility(globalAlert, false);
              addingProfile = true;
            },
            complete: function () {
              $('#btnCancelProfile').removeClass('d-none');
              $('.request-spinner').addClass('d-none');
              addingProfile = false;
            },
            error: function () {
              Helpers.handleAlertVisibility(
                globalAlert,
                true,
                'alert-danger',
                window.variables.messages.unable_to_add_user_profile_single,
              );
            },
            success: function (result) {
              switch(result.code){
                case 100:
                  resetInputs();
                  addDataTableRow(result.data);
                  Helpers.handleAlertVisibility(globalAlert, true, 'alert-success ', result.message);
                  break;
                case 150:
                  let firstKey = Object.keys(result.data)[0];
                  Helpers.handleAlertVisibility(globalAlert, true, 'alert-danger', result.data[firstKey][0]);
                  break;
                default:
                  Helpers.handleAlertVisibility(globalAlert, true, 'alert-danger', result.message);
                  break;
              }
            },
          });
        }
      });
    }
  }

  function resetInputs() {
    selectedTreeElements = [];
    checkboxTree.jstree(true).deselect_all();
    inputProfileName.val('');
    $('.menu-search').val('');
    btnMenuHeader.removeClass('text-danger');
    btnMenuHeader.html(window.variables.messages.available_menu);
  }

  function addDataTableRow(row) {
    profilesData.push(row);
    dtBasic = profileDataTable.DataTable().row.add(row).draw(false);
  }

  function initProfileDataTable() {
    if (profileDataTable.length) {
      dtBasic = profileDataTable.DataTable(dataTableOption(profilesData));
      expandProfileDetailsEvent();
      datatableActionEvent();

      // Filter form control to default size
      // ? setTimeout used for multilingual table initialization
      setTimeout(() => {
        $('.dataTables_filter .form-control').removeClass('form-control-sm').attr(
          'placeholder',
          window.variables.messages.search + " ..."
        );
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
      displayLength: 100,
      searching: true,
      lengthChange: true,
      orderCellsTop: true,
      order: [[0, "asc"]],
      language: window.variables.messages.languages,
      columns: [
        { data: 'label' },
        { data: 'code' },
        { data: 'actions' },
      ],
      columnDefs: [
        {
          targets: '_all',
          defaultContent: "--",
          className: ''
        },
        {
          // For label column
          targets: 0,
          orderable: true,
          searchable: true,
          render: function (data, type, full, meta) {
            return (
              '<div class="d-flex align-items-center flex-nowrap">' +
              '<button type="button" class="btn rounded-pill btn-icon btn-label-secondary btn-xs me-3 btn-expand-details" style="margin-top: 0.1rem;"><span class="tf-icons bx bx-plus fs-6 d-inline-block" style="padding-top: 1px;"></span></button>' +
              '<span>'+ full.label +'</span>' +
              '</div>'
            );
          }
        },
        {
          // For code column
          targets: 1,
          orderable: true,
          searchable: true,
          render: function (data, type, full, meta) {
            if(full.code !== null) {
              return (
                full.code.length > 0 ? full.code : '--'
              );
            }

            return null;
          }
        },
        {
          // For actions
          targets: 2,
          orderable: false,
          searchable: false,
          className: 'dt-right',
          render: function (data, type, full, meta) {
            let deleteMessage = window.variables.messages.delete_user_profile_confirmation;
            deleteMessage = deleteMessage.replace(/:label/gi, full.label);
            let deleteProfileLabel = window.variables.messages.delete_profile;

            let actionWrapper =  $('<div class=""></div>');

            let editAction =  $(
              '<button data-target="'+ full.id +'" type="button" class="btn btn-icon btn-label-primary btn-sm ms-2 btn-edit" data-bs-toggle="offcanvas" data-bs-target="#offcanvasEditProfile" aria-controls="offcanvasEditProfile"><span class="tf-icons bx bx-edit"></span></button>'
            );

            let trashDropContent = ('' +
              '<div class="dropdown-menu dropdown-menu-end w-px-250">' +
              '<div class="pt-2 px-3">' +
              '<p class="fs-6 text-wrap">' + deleteMessage + '</p>' +
              '</div>' +
              '<div class="dropdown-divider"></div>' +
              '<div class="pt-1 pb-2 px-3">' +
              '<button type="button" class="btn btn-sm btn-danger d-inline-block btn-delete" data-target="'+ full.id +'">'+ deleteProfileLabel +'</button>' +
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

            actionWrapper.append(editAction);
            if(full.is_deletable !== 1) {
              actionWrapper.append(deleteAction);
            }

            return actionWrapper.html();
          }
        }
      ]
    };
  }

  function formatProfileRowDetails(row) {
    // `row` is the original data object for the row
    let labelAttr = window.variables.messages.profile_name;
    let availableMenuLabel = window.variables.messages.available_menu;
    let noAssociatedMenu = window.variables.messages.no_associated_menu;
    let codeValue = row.code !== null ? (row.code.length > 0 ? row.code : '--') : '--';
    let treeViewId = 'jsTreeView_' + row.id;
    let treeViewDomElement = '<div id="'+ treeViewId +'" class=""></div>';

    if(row['menu_tree'].length > 0) {
      setTimeout(function() {
        let treeViewElement = $('#' + treeViewId);
        treeViewElement.jstree({
          core: {
            themes: {
              name: theme
            },
            data: JSON.parse(JSON.stringify(row['menu_tree'])),
          },
          plugins: ['types'],
          types: {
            default: {
              icon: 'bx bx-coin-stack'
            },
          }
        });
      }, 2000)
    } else {
      treeViewDomElement = '<span class="fs-6 fw-semibold">'+ noAssociatedMenu +'</span>';
    }

    return (
      '<div class="" style="margin-left: 32px;">' +
      '<div class="row">' +
      '<div class="col-md-4 col-12"><span class="fs-6">'+ labelAttr +'</span></div>' +
      '<div class="col-md-8 col-12"><span class="fs-6 fw-semibold">'+ row.label +'</span></div>' +
      '</div>' +
      '<div class="row">' +
      '<div class="col-md-4 col-12"><span class="fs-6">Code</span></div>' +
      '<div class="col-md-8 col-12"><span class="fs-6 fw-semibold">'+ codeValue +'</span></div>' +
      '</div>' +
      '<div class="row pt-3">' +
      '<div class="col-md-4 col-12"><span class="fs-6">'+ availableMenuLabel +'</span></div>' +
      '<div class="col-md-8 col-12">'+ treeViewDomElement +'</div>' +
      '</div>' +
      '</div>'
    );
  }

  // Events handler ...
  $('#globalAlert .btn-close').click(function (event) {
    event.preventDefault();
    Helpers.handleAlertVisibility(globalAlert, false);
  });

  $('.btn-retry').click(function (event) {
    event.preventDefault();
    loadPageData();
  });

  function expandProfileDetailsEvent() {
    $('#profileDataTable tbody').on('click', 'td button.btn-expand-details', function (event) {
      event.preventDefault();
      let button = $(this);

      let tr = $(this).closest('tr');
      let row = dtBasic.row(tr);

      if (row.child.isShown()) {
        // This row is already open - close it
        row.child.hide();
        tr.removeClass('shown');
        button.removeClass('btn-label-primary')
        button.addClass('btn-label-secondary');
        button.find('span').removeClass('bx-minus');
        button.find('span').addClass('bx-plus');
      } else {
        // Open this row
        row.child(formatProfileRowDetails(row.data())).show();
        tr.addClass('shown');
        button.addClass('btn-label-primary')
        button.removeClass('btn-label-secondary');
        button.find('span').removeClass('bx-plus');
        button.find('span').addClass('bx-minus');
      }
    });
  }

  function datatableActionEvent() {
    let tbody = $('#profileDataTable tbody');
    tbody.on('click', 'td button.btn-trash', function () {});

    tbody.on('click', 'td button.btn-delete', function (event) {
      event.preventDefault();
      let button = $(this);

      let blockUi = $('.light-overlay');
      let dynamicCardHeader = $('.dynamic-card-header');
      let localSpinner = $('.local-spinner');
      let url = window.variables.routes.delete_profile.replace(/:profileId/gi, button.attr('data-target'));

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
            window.variables.messages.unable_to_delete_profile
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

    tbody.on('click', 'td button.btn-edit', function (event) {
      event.preventDefault();
      let button = $(this);

      loadUserProfileDetails(button.attr('data-target'));
    });
  }

  // For off canvas ...
  // Init ...
  let userProfile = null;
  let offCanvasEditProfile = $('#offcanvasEditProfile');
  let offCanvasHeaderTitle = offCanvasEditProfile.find('.offcanvas-title');
  let offCanvasHeaderSpinner = offCanvasEditProfile.find('.offcanvas-spinner');
  let editProfileName = $('#editProfileName');
  let jsTreeEditProfile = $('#jsTreeEditProfile');
  let offCanvasBody = offCanvasEditProfile.find('.offcanvas-body');
  let selectedTreeElementsForEdit = [];
  let offCanvasFooter = $('#offCanvasFooter');
  let editingProfile = false;
  let editSpinner = $('#editSpinner');

  // Init elements ...
  if (document.getElementById('offCanvasBody')) {
    new PerfectScrollbar(document.getElementById('offCanvasBody'), {
      wheelPropagation: true
    });
  }

  /** Methods */

  function loadUserProfileDetails(profileId) {
    let url = window.variables.routes.profile_details_for_edition.replace(/:profileId/gi, profileId);

    $.ajax({
      url: url,
      type: 'GET',
      dataType: 'json',
      beforeSend: function () {
        offCanvasBody.addClass('d-none');
        offCanvasFooter.addClass('d-none');
        offCanvasFooter.addClass('d-none');

        offCanvasHeaderTitle.addClass('d-none');
        offCanvasHeaderSpinner.removeClass('d-none');
      },
      complete: function () {
        offCanvasHeaderTitle.removeClass('d-none');
        offCanvasHeaderSpinner.addClass('d-none');
      },
      error: function () {
        offCanvasEditProfile.find('button.btn-close').trigger('click');
      },
      success: function (result) {
        if(result.code === 100) {
          userProfile = result.data;

          // Build off canvas elements
          editProfileName.val(userProfile.label);
          initMenuTreeForEdition();
          offCanvasBody.removeClass('d-none');
          offCanvasFooter.removeClass('d-none');
        } else {
          offCanvasEditProfile.find('button.btn-close').trigger('click');
        }
      },
    });
  }

  function initMenuTreeForEdition() {
    if (jsTreeEditProfile.length) {
      jsTreeEditProfile.jstree('destroy');
      jsTreeEditProfile = jsTreeEditProfile
        .on('changed.jstree', function (e, data) {
          if(data.action === "select_node") {
            selectedTreeElementsForEdit.push(data.node.id);
            selectedTreeElementsForEdit = selectedTreeElementsForEdit.concat(data.node.children);
          } else {
            selectedTreeElementsForEdit = selectedTreeElementsForEdit.filter(e => e !== data.node.id);
            selectedTreeElementsForEdit = selectedTreeElementsForEdit.filter(function (value) {
              return data.node.children.indexOf(value) < 0;
            });
          }
          // Remove duplicate values
          selectedTreeElementsForEdit = selectedTreeElementsForEdit.filter((element, index) => {
            return selectedTreeElementsForEdit.indexOf(element) === index;
          });
        })
        .jstree({
          core: {
            themes: {
              name: theme
            },
            data: userProfile.menu_tree,
          },
          checkbox : {
            "keep_selected_style" : false
          },
          plugins: ['types', 'checkbox', 'search'],
          types: {
            default: {
              icon: 'bx bx-coin-stack'
            },
          }
        })

      initCheckedValuesForEdition(userProfile.menu_tree);
    }
  }

  function resetEditionInputs() {
    selectedTreeElementsForEdit = [];
    editProfileName.val(userProfile.label);
    jsTreeEditProfile.jstree('destroy');
    initMenuTreeForEdition();
  }

  function initCheckedValuesForEdition(data = []) {
    let sourceData = data.length > 0 ? data : userProfile.menu_tree;

    sourceData.forEach((element) => {
      if(element.children && element.children.length > 0) {
        initCheckedValuesForEdition(element.children);
      } else {
        if(element.state && element.state.selected) {
          selectedTreeElementsForEdit.push('' + element.id);
        }
      }
    });
  }

  function findProfileById(id) {
    let result = null;

    for(let i = 0; i < profilesData.length; i++) {
      if(profilesData[i].id === id) {
        result = {
          index: i
        }
      }
    }

    return result;
  }

  /** Events handlers */

  $('#btnEditProfileCancel').bind('click', function(event) {
    event.preventDefault();
    if(!editingProfile) {
      resetEditionInputs();
    }
  });

  $('#btnEditProfile').bind('click', function(event) {
    event.preventDefault();

    if(!editingProfile) {
      if(editProfileName.val().length === 0) {
        editProfileName.focus();
        return;
      }

      if(selectedTreeElementsForEdit.length === 0) {
        return;
      }

      let jsonData = {
        'profile_name': editProfileName.val(),
        'menus': selectedTreeElementsForEdit,
        "_token": window.variables.constants.csrf_token
      };

      let url = window.variables.routes.profile_edition.replace(/:profileId/gi, userProfile.id);
      $.ajax({
        url: url,
        type:'PUT',
        dataType: "json",
        data: jsonData,
        beforeSend: function () {
          editSpinner.removeClass('d-none');
          Helpers.handleAlertVisibility(globalAlert, false);
          editProfileName.removeClass('is-invalid');
          editingProfile = true;
        },
        complete: function () {
          editSpinner.addClass('d-none');
          editingProfile = false;
        },
        error: function () {},
        success: function (result) {
          switch(result.code){
            case 100:
              userProfile = result.data.updated_profile;
              // Close offCanvas
              offCanvasEditProfile.offcanvas('hide');
              // Notify success ...
              Helpers.handleAlertVisibility(globalAlert, true, 'alert-success', result.message);

              if(parseInt(result.data.refresh) === 1) {
                setTimeout(() => {
                  window.location.reload();
                }, 1000);
              } else {
                // refresh data table
                let sResult = findProfileById(parseInt(userProfile.id));
                if(sResult) {
                  profilesData[sResult.index] = userProfile;
                  dtBasic.clear();
                  dtBasic = dtBasic.rows.add(profilesData);
                  dtBasic.draw(false);
                }
              }
              break;
            case 150:
              let firstKey = Object.keys(result.data)[0];
              editProfileName.addClass('is-invalid');
              editProfileName.next('span').html(result.data[firstKey][0]);
              break;
            default:
              editProfileName.addClass('is-invalid');
              editProfileName.next('span').html(result.message);
              break;
          }
        },
      });
    }
  });
})
