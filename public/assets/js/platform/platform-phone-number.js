'use strict';

jQuery.fn.phoneNumber = function(url, options) {
  const _options = $.extend({}, {
    dialCode: '+237',
    countryCode: 'CM',
    defaultPhoneValue: "",
  }, {
    ...options,
    messages: {
      search: "Search ",
      title: "Select your country",
      ...options.messages ?? {},
    }
  });
  const $this = this;
  let countriesWrapper = null;
  let countries = [];
  let rawCountry = null;

  const _init = function() {
    console.dir('****** Init phone number');

    let defaultPhone = '';
    if(_options.defaultPhoneValue !== undefined && _options.defaultPhoneValue !== null && _options.defaultPhoneValue.length > 0) {
      defaultPhone = _options.defaultPhoneValue;
    } else {
      defaultPhone = $this.find('input').val();
    }

    rawCountry = _initRawCountry(defaultPhone);
    _renderInputDialCode();

    // Build elements
    countriesWrapper = _buildDialogRoot();

    // Load countries
    _requestCountries();

    // Init input with default value
    if(defaultPhone !== undefined && defaultPhone !== null && defaultPhone.length > 0) {
      $this.find('input').attr('data-bs-value', defaultPhone);
      $this.find('input').val(
        _formatNational(
          defaultPhone.replace(rawCountry.dial_code, "")
        )
      );

      try {
        $this.find('input').attr('data-bs-value', _parseAndGetValue(defaultPhone));
      } catch (_) {
        $(this).attr('data-bs-value', "");
      }
    }

    // Add event listeners
    $this.find('.dial-code').bind('click', function(event) {
      event.preventDefault();

      if(countriesWrapper && countriesWrapper.length) {
        countriesWrapper.modal('show');
      } else {
        throw new Error("Bad dialog element selector");
      }
    });
    countriesWrapper.find('div.modal-header button.btn-close').bind('click', function(event) {
      event.preventDefault();

      _resetAll();
    });
    countriesWrapper.find('div.modal-body input.search-control').bind('keyup', function(event) {
      let $this = $(this);
      let filteredCountries;

      if($this.val().length > 0) {
        filteredCountries = countries.filter(function (country) {
          let query = $this.val().toLowerCase();
          let bydDialCode = country.dial_code.toLowerCase().includes(query);
          let byDesignation = country.designation.toLowerCase().includes(query);

          return bydDialCode || byDesignation;
        });
      } else {
        filteredCountries = countries;
      }

      _renderCountry(filteredCountries);
    });
    $this.find('input').bind('keyup, input', function(event) {
      let currentVal = $(this).val();

      if(currentVal.length > 0) {
        /*
        $(this).val(new libphonenumber.AsYouType(rawCountry.country_code.toUpperCase()).input(
          currentVal.replace(rawCountry.dial_code, ""),
        ));
        */

        $(this).val(
          _formatNational(
            currentVal.replace(rawCountry.dial_code, "")
          )
        );
      }

      try {
        $(this).attr('data-bs-value', _parseAndGetValue(currentVal));
      } catch (_) {
        $(this).attr('data-bs-value', "");
      }
    });

    return $this;
  }

  const _renderInputDialCode = function() {
    $this.find('.dial-code')
      .css('cursor', 'pointer')
      .html(`${rawCountry.country_code.toUpperCase()} (${rawCountry.dial_code})`);
  }

  const _requestCountries = function() {
    $.ajax({
      url: url,
      ataType: "json",
      beforeSend: function () {},
      complete: function () {},
      error: function () {},
      success: function (result) {
        if(result.code === 100) {
          countries = result.data;

          _renderCountry(countries);
        } else {}
      },
    });
  }

  const _buildDialogRoot = function() {
    return $('' +
      '<div class="modal fade " data-bs-backdrop="static" tabindex="-1" aria-hidden="true">' +
      '  <div class="modal-dialog modal-dialog-centered position-relative" role="document">' +
      '    <div class="modal-content">' +
      '      <div class="modal-header pb-1">' +
      '        <h5 class="modal-title">'+ _options.messages.title +'</h5>' +
      '        <button type="button" class="btn-close"></button>' +
      '      </div>' +
      '      <div class="modal-body py-0 py-2 px-0">' +
      '        <div class="input-group input-group-merge px-4">' +
      '          <input type="text" class="form-control search-control" name="ref" value="" placeholder="'+ _options.messages.search +' ..." aria-label="'+ _options.messages.search +'" />' +
      '          <span class="input-group-text" id=""><i class="bx bx-search"></i></span>' +
      '        </div>' +
      '        <div class="countries-wrapper">' +
      '           <ul class="list-group mt-3 overflow-auto" style="height: 320px;"></ul>' +
      '        </div>' +
      '      </div>' +
      '    </div>' +
      '  </div>' +
      '</div>' +
      '');
  }

  const _renderCountry = function(data) {
    countriesWrapper.find('.list-group').empty();
    data.forEach((country) => {
      let bgColor = country.country_code.toLowerCase() === rawCountry.country_code.toLowerCase() ? 'bg-label-secondary' : '';

      // Create element
      let itemWrapper = $('' +
        ' <li class="list-group-item d-flex align-items-center list-group-item-action fs-6 cursor-pointer border-0 px-4 '+ bgColor +'" style="font-size: 0.98rem !important;">' +
        '    <div class="w-100">' +
        '       <div class="me-4 d-inline-block">' +
        '          <span class="w-px-20 h-px-20 rounded-pill fs-5 fi fi-'+ country.country_code.toLowerCase() +' fib" style="background-size: cover !important;"></span>' +
        '       </div>' +
        ''      + country.designation +'' +
        '    </div>' +
        '    <div class="flex-shrink-0">' +
        '       <span class="fw-bold fs-6">'+ country.dial_code +'</span>' +
        '    </div>' +
        '</li>'
      );
      // Add listener
      itemWrapper.bind('click', function(event) {
        event.preventDefault();

        _selectCountry(country);
      });

      // Add into a dom
      countriesWrapper.find('.list-group').append(itemWrapper);
    });
  }

  const _selectCountry = function(country) {
    if(country.country_code.toLowerCase() !== rawCountry.country_code.toLowerCase()) {
      $this.find('input').val('');
      $this.find('input').attr('data-bs-value', "");
    }

    rawCountry = country;
    _renderInputDialCode();
    _resetAll();
  }

  const _resetAll = function() {
    countriesWrapper.modal('hide');
    countriesWrapper.find('div.modal-body input.search-control').val('');
    _renderCountry(countries);
  }

  const _parseAndGetValue = function(value) {
    let currentVal = value.replace(rawCountry.dial_code, "");

    try {
      const phoneNumber = libphonenumber.parsePhoneNumber(
        rawCountry.dial_code + "" + currentVal,
        rawCountry.country_code.toUpperCase()
      );

      if (phoneNumber) {
        return phoneNumber.number;
      }
    } catch (_error) {
      console.dir(_error);
    }

    return "";
  }

  const _formatNational = function(value) {
    let currentVal = value.replace(rawCountry.dial_code, "");

    try {
      const phoneNumber = libphonenumber.parsePhoneNumber(
        rawCountry.dial_code + "" + currentVal,
        rawCountry.country_code.toUpperCase()
      );

      if (phoneNumber) {
        return phoneNumber.formatNational();
      }
    } catch (_error) {
      console.dir(_error);
    }

    return currentVal;
  }

  const _initRawCountry = function(value) {
    let result = {
      dial_code: _options.dialCode,
      country_code: _options.countryCode,
    };

    try {
      const phoneNumber = libphonenumber.parsePhoneNumber(value);
      if (phoneNumber) {
        result = {
          dial_code: "+" + phoneNumber.countryCallingCode,
          country_code: phoneNumber.country,
        };
        console.dir(phoneNumber);
      }

    } catch (_) {}

    return result;
  }

  return _init();
};
