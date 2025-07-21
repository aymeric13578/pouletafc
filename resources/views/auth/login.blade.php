<!DOCTYPE html>





























<!-- =========================================================
* Vuexy - Bootstrap Admin Template | v9.0.0
==============================================================

* Product Page: https://1.envato.market/vuexy_admin
* Created by: Pixinvent
* License: You must have a valid license purchased in order to legally use the theme for your project.
* Copyright Pixinvent (https://pixinvent.com)

=========================================================
 -->
<!-- beautify ignore:start -->


<html lang="en" class="light-style layout-wide  customizer-hide" dir="ltr" data-theme="theme-semi-dark" data-assets-path="dash_release/assets/" data-template="vertical-menu-template-semi-dark">


<!-- Mirrored from demos.pixinvent.com/vuexy-html-admin-template/html/vertical-menu-template-semi-dark/auth-login-cover.html by HTTrack Website Copier/3.x [XR&CO'2014], Wed, 28 Feb 2024 22:34:09 GMT -->
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Login </title>


    <meta name="description" content="POULET AFC" />
    <meta name="keywords" content="POULET AFC">
    <!-- Canonical SEO -->
    <link rel="canonical" href="">


    <!-- ? PROD Only: Google Tag Manager (Default ThemeSelection: GTM-5DDHKGP, PixInvent: GTM-5J3LMKC) -->
    <script>
        (function(w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({
                'gtm.start': new Date().getTime(),
                event: 'gtm.js'
            });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s),
                dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src =
                '../../../../www.googletagmanager.com/gtm5445.html?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-5J3LMKC');
    </script>
    <!-- End Google Tag Manager -->

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('logo_blue.png') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&amp;ampdisplay=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/fonts/fontawesome.css') }}" />
    <link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/fonts/tabler-icons.css') }}"/>
    <link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/fonts/flag-icons.css') }}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/css/rtl/core.css" class="template-customizer-core-css') }}" />
    <link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/css/rtl/theme-semi-dark.css" class="template-customizer-theme-css') }}" />
    <link rel="stylesheet" href="{{ asset('dash_release/assets/css/demo.css') }}" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/libs/node-waves/node-waves.css') }}" />
    <link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/libs/typeahead-js/typeahead.css') }}" />
    <link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/libs/apex-charts/apex-charts.css') }}" />
<link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/libs/swiper/swiper.css') }}" />
<link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css') }}">

    <!-- Page CSS -->
    <link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/css/pages/cards-advance.css') }}" />

    <!-- Helpers -->
    <script src="{{ asset('dash_release/assets/vendor/js/helpers.js') }}"></script>
    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->
    <script src="{{ asset('dash_release/assets/vendor/js/template-customizer.js') }}"></script>
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="{{ asset('dash_release/assets/js/config.js') }}"></script>

</head>

<body>


  <!-- ?PROD Only: Google Tag Manager (noscript) (Default ThemeSelection: GTM-5DDHKGP, PixInvent: GTM-5J3LMKC) -->
  <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5DDHKGP" height="0" width="0" style="display: none; visibility: hidden"></iframe></noscript>
  <!-- End Google Tag Manager (noscript) -->

  <!-- Content -->


    <!-- Login -->
    <div class="d-flex col-12 col-lg-12 p-sm-5 p-4">
      <div class="w-px-600 mx-auto my-auto">
        <!-- Logo -->
        <div class="app-brand mb-4">

        </div>
        <!-- /Logo -->
        <h3 class="mb-1">Bienvenue sur PouletAFC 👋</h3>
        <p class="mb-4">Entrez les information pour vous connecter</p>

        <form method="POST" action="{{ route('login') }}">
                        @csrf
                           <div class="mb-3">
            <label for="email" class="form-label">Email or Username</label>
            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="Enter your email or username" autofocus>

            @error('email')
    <span class="invalid-feedback" role="alert">
                                                <strong>{{ $message }}</strong>
                                            </span>
@enderror
        </div>
          <div class="mb-3 form-password-toggle">
            <div class="d-flex justify-content-between">
              <label class="form-label" for="password">Password</label>
              <a href="auth-forgot-password-cover.html">
                <small>Mot de passe oublié ?</small>
              </a>
            </div>
            <div class="input-group input-group-merge">
              <input type="password"  id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password" class="form-control"  placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" aria-describedby="password" />
              <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
            </div>
          </div>

          <button class="btn btn-primary d-grid w-100">
           Valider
          </button>
        </form>

        <p class="mt-6 text-center">
          <span>Pas de compte?</span>
          <a href="/register">
            <span>Créer un compte?</span>
          </a>
        </p>



      </div>
    </div>
    <!-- /Login -->
  </div>
</div>

<!-- / Content -->





  <!-- Core JS -->
  <!-- build:js assets/vendor/js/core.js -->

  <script src="{{ asset('dash_release/assets/vendor/libs/jquery/jquery.js') }}"></script>
  <script src="{{ asset('dash_release/assets/vendor/libs/popper/popper.js') }}"></script>
  <script src="{{ asset('dash_release/assets/vendor/js/bootstrap.js') }}"></script>
  <script src="{{ asset('dash_release/assets/vendor/libs/node-waves/node-waves.js') }}"></script>
  <script src="{{ asset('dash_release/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
  <script src="{{ asset('dash_release/assets/vendor/libs/hammer/hammer.js') }}"></script>
  <script src="{{ asset('dash_release/assets/vendor/libs/i18n/i18n.js') }}"></script>
  <script src="{{ asset('dash_release/assets/vendor/libs/typeahead-js/typeahead.js') }}"></script>
   <script src="{{ asset('dash_release/assets/vendor/js/menu.js') }}"></script>

  <!-- endbuild -->




  <!-- endbuild -->

  <!-- Vendors JS -->
  <script src="{{ asset('dash_release/assets/vendor/libs/%40form-validation/popular.js') }}"></script>
<script src="{{ asset('dash_release/assets/vendor/libs/%40form-validation/bootstrap5.js') }}"></script>
<script src="{{ asset('dash_release/assets/vendor/libs/%40form-validation/auto-focus.js') }}"></script>

  <!-- Main JS -->
  <script src="{{ asset('dash_release/assets/js/main.js') }}"></script>


  <!-- Page JS -->
  <script src="{{ asset('dash_release/assets/js/pages-auth.js') }}"></script>

</body>


<!-- Mirrored from demos.pixinvent.com/vuexy-html-admin-template/html/vertical-menu-template-semi-dark/auth-login-cover.html by HTTrack Website Copier/3.x [XR&CO'2014], Wed, 28 Feb 2024 22:34:10 GMT -->
</html>

<!-- beautify ignore:end -->
