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

    <title>Register</title>

    
    <meta name="description" content="POULET AFC" />
    <meta name="keywords" content="POULET AFC">
    <!-- Canonical SEO -->
    <link rel="canonical" href="">
    
    
    <!-- ? PROD Only: Google Tag Manager (Default ThemeSelection: GTM-5DDHKGP, PixInvent: GTM-5J3LMKC) -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
      new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
      j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
      '../../../../www.googletagmanager.com/gtm5445.html?id='+i+dl;f.parentNode.insertBefore(j,f);
      })(window,document,'script','dataLayer','GTM-5J3LMKC');</script>
    <!-- End Google Tag Manager -->
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{asset('logo_blue.png')}}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&amp;ampdisplay=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{asset('dash_release/assets/vendor/fonts/fontawesome.css')}}" />
    <link rel="stylesheet" href="{{asset('dash_release/assets/vendor/fonts/tabler-icons.css')}}"/>
    <link rel="stylesheet" href="{{asset('dash_release/assets/vendor/fonts/flag-icons.css')}}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{asset('dash_release/assets/vendor/css/rtl/core.css" class="template-customizer-core-css')}}" />
    <link rel="stylesheet" href="{{asset('dash_release/assets/vendor/css/rtl/theme-semi-dark.css" class="template-customizer-theme-css')}}" />
    <link rel="stylesheet" href="{{asset('dash_release/assets/css/demo.css')}}" />
    
    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{asset('dash_release/assets/vendor/libs/node-waves/node-waves.css')}}" />
    <link rel="stylesheet" href="{{asset('dash_release/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css')}}" />
    <link rel="stylesheet" href="{{asset('dash_release/assets/vendor/libs/typeahead-js/typeahead.css')}}" /> 
    <link rel="stylesheet" href="{{asset('dash_release/assets/vendor/libs/apex-charts/apex-charts.css')}}" />
<link rel="stylesheet" href="{{asset('dash_release/assets/vendor/libs/swiper/swiper.css')}}" />
<link rel="stylesheet" href="{{asset('dash_release/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('dash_release/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('dash_release/assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css')}}">

    <!-- Page CSS -->
    <link rel="stylesheet" href="{{asset('dash_release/assets/vendor/css/pages/cards-advance.css')}}" />

    <!-- Helpers -->
    <script src="{{asset('dash_release/assets/vendor/js/helpers.js')}}"></script>
    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->
    <script src="{{asset('dash_release/assets/vendor/js/template-customizer.js')}}"></script>
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="{{asset('dash_release/assets/js/config.js')}}"></script>
    
</head>

<body>


  <!-- ?PROD Only: Google Tag Manager (noscript) (Default ThemeSelection: GTM-5DDHKGP, PixInvent: GTM-5J3LMKC) -->
  <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5DDHKGP" height="0" width="0" style="display: none; visibility: hidden"></iframe></noscript>
  <!-- End Google Tag Manager (noscript) -->
  
  <!-- Content -->

<div class="authentication-wrapper authentication-cover authentication-bg">
  <div class="authentication-inner row">

    <!-- /Left Text -->
    <div class="d-none d-lg-flex col-lg-3 p-0">
      <div class="auth-cover-bg auth-cover-bg-color d-flex justify-content-center align-items-center">
        <!-- <img src="../../assets/img/illustrations/auth-register-illustration-light.png" alt="auth-register-cover" class="img-fluid my-5 auth-illustration" data-app-light-img="illustrations/auth-register-illustration-light.png" data-app-dark-img="illustrations/auth-register-illustration-dark.html">

        <img src="../../assets/img/illustrations/bg-shape-image-light.png" alt="auth-register-cover" class="platform-bg" data-app-light-img="illustrations/bg-shape-image-light.png" data-app-dark-img="illustrations/bg-shape-image-dark.html">
       -->
      
      
      </div>
    </div>
    <!-- /Left Text -->

    <!-- Register -->
    <div class="d-flex col-12 col-lg-12 align-items-center p-sm-5 p-4">
      <div class="w-px-400 mx-auto">
        <!-- Logo -->
        <div class="app-brand mb-4">
          <a href="index.html" class="app-brand-link gap-2">
            <span class="app-brand-logo demo">

</span>
          </a>
        </div>
        <!-- /Logo -->
        <h3 class="mb-1">POULET AFC DASHBOARD </h3>
        <p class="mb-4">Entrez les informations pour créer votre compte</p>

        <form method="POST" action="{{ route('register') }}">
                        @csrf
        <div class="mb-3">
            <label for="username" class="form-label">Nom</label>
            <input type="text" id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>
            @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
        
        </div>
        <div class="mb-3">
            <label for="username" class="form-label">Prénom</label>
            <input type="text" id="lastname" type="text" class="form-control @error('lastname') is-invalid @enderror" name="lastname" value="{{ old('lastname') }}" required autocomplete="name" autofocus>
            @error('lastname')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
        
        </div>
          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="text" id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">
            @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
        
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Phone</label>
            <input type="text" id="phone"  class="form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone') }}" required autocomplete="email">
            @error('phone')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
        
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Pays</label>
            <div class="form-group">
                          <div class="input-group">
                            <select name="country" class="form-control" aria-label=".form-select-lg example" required>
                                @foreach($countries as $country)
                                <option value="{{$country->id}}">{{$country->name}}</option>

                                @endforeach

                            </select>

                          </div>

                    </div>
        
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Ville</label>
            <input  id="city" type="text" class="form-control @error('city') is-invalid @enderror" name="city" value="{{ old('city') }}" required autocomplete="email">
            @error('city')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
        
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Date de naissance</label>
            <input  id="birth" type="date" class="form-control @error('birth') is-invalid @enderror" name="birth" value="{{ old('birth') }}" required autocomplete="email">
            @error('birth')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
        
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Sexe</label>
            <select name="sexe" class="form-control" aria-label=".form-select-lg example" required>
                              
                                <option value="H">Homme</option>
                                <option value="F">Femme</option>
                            </select>
            @error('sexe')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
        
        </div>
          <div class="mb-3 form-password-toggle">
            <label class="form-label" for="password">Password</label>
            <div class="input-group input-group-merge">
              <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" aria-describedby="password" />
              <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
              @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
            </div>
          </div>

          <div class="mb-3 form-password-toggle">
            <label class="form-label" for="password">Password confirm </label>
            <div class="input-group input-group-merge">
              <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password" />
              <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
              @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
            </div>
          </div>
          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="terms-conditions" name="terms">
              <label class="form-check-label" for="terms-conditions">
               Je déclare avoir lu la politique de 
                <a href="javascript:void(0);">confidentialité</a>
              </label>
            </div>
          </div>
          <button class="btn btn-primary d-grid w-100">
            Valider
          </button>
        </form>

        <p class="text-center">
          <span>Déjà un compte?</span>
          <a href="/login">
            <span>Se connecter</span>
          </a>
        </p>

        <div class="divider my-4">
          <div class="divider-text">or</div>
        </div>

        <div class="d-flex justify-content-center">
          <a href="javascript:;" class="btn btn-icon btn-label-facebook me-3">
            <i class="tf-icons fa-brands fa-facebook-f fs-5"></i>
          </a>

          <a href="javascript:;" class="btn btn-icon btn-label-google-plus me-3">
            <i class="tf-icons fa-brands fa-google fs-5"></i>
          </a>

          <a href="javascript:;" class="btn btn-icon btn-label-twitter">
            <i class="tf-icons fa-brands fa-twitter fs-5"></i>
          </a>
        </div>
      </div>
    </div>
    <!-- /Register -->
  </div>
</div>

<!-- / Content -->

  
  

  <!-- Core JS -->
  <!-- build:js assets/vendor/js/core.js -->
  
  <script src="{{asset('dash_release/assets/vendor/libs/jquery/jquery.js')}}"></script>
  <script src="{{asset('dash_release/assets/vendor/libs/popper/popper.js')}}"></script>
  <script src="{{asset('dash_release/assets/vendor/js/bootstrap.js')}}"></script>
  <script src="{{asset('dash_release/assets/vendor/libs/node-waves/node-waves.js')}}"></script>
  <script src="{{asset('dash_release/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js')}}"></script>
  <script src="{{asset('dash_release/assets/vendor/libs/hammer/hammer.js')}}"></script>
  <script src="{{asset('dash_release/assets/vendor/libs/i18n/i18n.js')}}"></script>
  <script src="{{asset('dash_release/assets/vendor/libs/typeahead-js/typeahead.js')}}"></script>
   <script src="{{asset('dash_release/assets/vendor/js/menu.js')}}"></script>
  
  <!-- endbuild -->


  
  
  <!-- endbuild -->

  <!-- Vendors JS -->
  <script src="{{asset('dash_release/assets/vendor/libs/%40form-validation/popular.js')}}"></script>
<script src="{{asset('dash_release/assets/vendor/libs/%40form-validation/bootstrap5.js')}}"></script>
<script src="{{asset('dash_release/assets/vendor/libs/%40form-validation/auto-focus.js')}}"></script>

  <!-- Main JS -->
  <script src="{{asset('dash_release/assets/js/main.js')}}"></script>
  

  <!-- Page JS -->
  <script src="{{asset('dash_release/assets/js/pages-auth.js')}}"></script>
  
</body>


<!-- Mirrored from demos.pixinvent.com/vuexy-html-admin-template/html/vertical-menu-template-semi-dark/auth-login-cover.html by HTTrack Website Copier/3.x [XR&CO'2014], Wed, 28 Feb 2024 22:34:10 GMT -->
</html>

<!-- beautify ignore:end -->