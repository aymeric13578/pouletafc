//Click on newRequestSaveButton
$("#newCompanySaveButton").on("click", function (event) {
  event.preventDefault();
  let $this = $(this);

  let companyName = $("#newCompanyCompanyName").val();
  if (companyName === "") {
    toastr.warning("{{ __('messages.companies.warning_empty_company_name') }}");
    return;
  }
  let email = $("#newCompanyEmail").val();
  let telephone = $("#newCompanyTelephone").val();
  if (telephone === "") {
    toastr.warning("{{ __('messages.companies.warning_empty_telephone') }}");
    return;
  }

  let postBox = $("#newCompanyPostBox").val();
  let address = $("#newCompanyAddress").val();
  if (address === "") {
    toastr.warning("{{ __('messages.companies.warning_empty_address') }}");
    return;
  }
  let registryNumber = $("#newCompanyRegistryNumber").val();
  if (registryNumber === "") {
    toastr.warning("{{ __('messages.companies.warning_registry_number') }}");
    return;
  }
  let taxepayerCardNumber = $("#newCompanyTaxePayerCardNumber").val();
  if (taxepayerCardNumber === "") {
    toastr.warning("{{ __('messages.companies.warning_taxpayer_card_number') }}");
    return;
  }
  let country = $("#newCompanyCountry").val();
  if (country === "") {
    toastr.warning("{{ __('messages.companies.warning_country') }}");
    return;
  }
  let town = $("#newCompanyTown").val();
  if (town === "") {
    toastr.warning("{{ __('messages.companies.warning_town') }}");
    return;
  }
  let taxepayerCardFile = $('#newCompanyTaxepayerCard')[0].files;
  if (taxepayerCardFile.length === 0) {
    toastr.warning("{{ __('messages.companies.warning_taxpayer_card') }}");
    return false;
  }
  let commercialRegisterFile = $('#newCompanyCommercialRegister')[0].files;
  if (commercialRegisterFile.length === 0) {
    toastr.warning("{{ __('messages.companies.warning_commercial_register') }}");
    return false;
  }
  formData.set('telephone', telephone);
  Loader.setLoading($this);
  let formData = new FormData($('#createCompanyForm')[0]);
  $.ajax({
    url: "{{ route('company-create') }}",
    type: "post",
    contentType: false,
    processData: false,
    cache: false,
    dataType: "json",
    data: formData,
    success: function (data) {
      if (data.code === 100) {
        $("#createCompanyForm").trigger("reset");
        $("#newCompanyCountry").trigger('change');
        $("#newCompanyTown").trigger('change');
        $.ajax({
          url: "{{ route('approval-list-companies') }}",
          type: "get",
          dataType: "json",
          success: function (data) {
            if (data.code === 100) {
              $("#companyId").empty();
              $("#companyId").append(`
                <option value="">
                  -- {{ __('messages.operating_titles.select_a_company') }} --
                </option>
              `)
              /*data.data.forEach((company) => {
                $("#companyId").append(`
                    <option value='${company["id"]}'>
                        ${company["company_name"]}
                    </option>
                `);
              });*/
            }
          },
        });

        toastr.success("{{ __('messages.companies.company_successfully_registered') }}");
      } else if (data.code === 101) {
        toastr.error("{{ __('messages.companies.this_commercial_registry_number_already_exist') }}");
      } else if (data.code === 104) {
        toastr.error("{{ __('messages.companies.this_taxpayer_card_number_already_exist') }}");
      } else if (data.code === 150) {
        toastr.error("{{ __('messages.companies.the_email_must_be_a_valid_email_address') }}");
      } else if (data.code === 102) {
        toastr.error("{{ __('messages.companies.this_bank_account_number_already_exist') }}");
      } else {
        //Error occurred
        toastr.error("{{ __('messages.error_occurred_during_operation') }}");
      }
      Loader.removeLoading($this);
    },
    error: function () {
      toastr.error("{{ __('messages.error_occurred_during_operation') }}");
      Loader.removeLoading($this);
    }
  });
});
