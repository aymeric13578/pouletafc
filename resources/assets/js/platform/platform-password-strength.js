'use strict';

$(function () {
  let passwordCheck = $('.password-strength-check');
  let strengthPasswordWrapper = $('.password-strength-progress');

  $("#resendEmailCode").on("click", function(){
    resendOtp('email')
  });

  passwordCheck.on('keyup', function(event) {
    event.preventDefault();
    let val = $(this).val();

    if(val.length > 0) {
      strengthPasswordWrapper.find('div.progress-bar').removeClass('d-none');
      strengthPasswordWrapper.removeClass('bg-transparent');

      let strengthPercent = passwordStrength(val);
      updateProgress(strengthPercent);
    } else {
      strengthPasswordWrapper.find('div.progress-bar').addClass('d-none');
      strengthPasswordWrapper.addClass('bg-transparent');
      updateProgress(0);
    }
  });

  function updateProgress(percent) {
    let progressBar = strengthPasswordWrapper.find('div.progress-bar');
    progressBar.css('width', percent + '%').data('aria-valuenow', percent);

    let classes = progressBar.attr("class").split(" ");
    for(let i = 0; i < classes.length; i++){
      if(classes[i].startsWith('bg-')) {
        progressBar.removeClass(classes[i]);
      }
    }

    if(percent > 0) {
      progressBar.addClass(percentToBgClass(percent));
    }
  }

  function passwordStrength(value) {
    let percentSage = [
      {
        regex: /[0-9]/g,
        percent: 100 / 5
      },
      {
        regex: /[A-Z]/g,
        percent: 100 / 5
      },
      {
        regex: /[a-z]/g,
        percent: 100 / 5
      },
      {
        regex: /[^a-zA-Z\d]/g,
        percent: 100 / 5
      },
    ]

    let percent = 0;
    if(value.length < 6) {
      return 10;
    }

    if(value.length >= 6) {
      percent = percent + (100/percentSage.length + 1)
    }

    percentSage.forEach(function(element) {
      if(value.match(element.regex)) {
        percent = percent + element.percent;
      }
    });

    return percent;
  }

  function percentToBgClass(percent) {
    if(percent > 70) return "bg-success";

    if(percent >= 40 && percent <= 70) return "bg-warning";

    return "bg-danger";
  }
});
