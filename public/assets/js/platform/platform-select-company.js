/**
 * Typeahead (jquery)
 */

'use strict';

$(function () {
  // String Matcher function
  var substringMatcher = function (strs) {
    return function findMatches(q, cb) {
      var matches, substrRegex;
      matches = [];
      substrRegex = new RegExp(q, 'i');
      $.each(strs, function (i, str) {
        if (substrRegex.test(str)) {
          matches.push(str);
        }
      });

      cb(matches);
    };
  };

  var companies = [];
      $.ajax({
        url: "{{ route('approval-list-companies') }}",
        type: "get",
        dataType: "json",
        success: function(data) {
          if (data.code === 100) {
            data.data.forEach((company) => {
              companies.push(company.company_name);
            });
          }
        },
      });
      console.log(companies);
  var states = [
    'Alabama',
    'Alaska',
    'Arizona',
    'Arkansas',
    'California',
    'Colorado',
    'Connecticut',
    'Delaware',
    'Florida',
    'Georgia',
    'Hawaii',
    'Idaho',
    'Illinois',
    'Indiana',
    'Iowa',
    'Kansas',
    'Kentucky',
    'Louisiana',
    'Maine',
    'Maryland',
    'Massachusetts',
    'Michigan',
    'Minnesota',
    'Mississippi',
    'Missouri',
    'Montana',
    'Nebraska',
    'Nevada',
    'New Hampshire',
    'New Jersey',
    'New Mexico',
    'New York',
    'North Carolina',
    'North Dakota',
    'Ohio',
    'Oklahoma',
    'Oregon',
    'Pennsylvania',
    'Rhode Island',
    'South Carolina',
    'South Dakota',
    'Tennessee',
    'Texas',
    'Utah',
    'Vermont',
    'Virginia',
    'Washington',
    'West Virginia',
    'Wisconsin',
    'Wyoming'
  ];

  if (isRtl) {
    $('.typeahead').attr('dir', 'rtl');
  }

  var bloodhoundBasicExample = new Bloodhound({
    datumTokenizer: Bloodhound.tokenizers.whitespace,
    queryTokenizer: Bloodhound.tokenizers.whitespace,
    local: states
  });

  // Bloodhound Example
  // --------------------------------------------------------------------
  $('.typeahead-bloodhound').typeahead(
    {
      hint: !isRtl,
      highlight: true,
      minLength: 1
    },
    {
      name: 'states',
      source: bloodhoundBasicExample
    }
  );

  var prefetchExample = new Bloodhound({
    datumTokenizer: Bloodhound.tokenizers.whitespace,
    queryTokenizer: Bloodhound.tokenizers.whitespace,
    prefetch: assetsPath + 'json/typeahead.json'
  });

  // Prefetch Example
  // --------------------------------------------------------------------
  $('.typeahead-prefetch').typeahead(
    {
      hint: !isRtl,
      highlight: true,
      minLength: 1
    },
    {
      name: 'states',
      source: prefetchExample
    }
  );

  // Render default Suggestions
  function renderDefaults(q, sync) {
    if (q === '') {
      sync(prefetchExample.get('Alaska', 'New York', 'Washington'));
    } else {
      prefetchExample.search(q, sync);
    }
  }
  // Default Suggestions
  // --------------------------------------------------------------------
  $('.typeahead-default-suggestions').typeahead(
    {
      hint: !isRtl,
      highlight: true,
      minLength: 0
    },
    {
      name: 'states',
      source: renderDefaults
    }
  );
});
