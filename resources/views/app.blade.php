<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" className="light-style layout-navbar-fixed layout-menu-fixed layout-compact " dir="ltr"
data-theme="theme-default" data-assets-path="publicAdmin/assets/" data-template="vertical-menu-template" data-style="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- <title inertia>{{ config('app.name', 'Laravel') }}</title> -->
        <title inertia>Poulet AFC</title>
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com/">
<link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;ampdisplay=swap"
  rel="stylesheet">

<!-- Icons -->
<link rel="stylesheet" href="{{asset('publicAdmin/assets/vendor/fonts/remixicon/remixicon.css')}}" />
<link rel="stylesheet" href="{{asset('publicAdmin/assets/vendor/fonts/flag-icons.css')}}" />

<!-- Menu waves for no-customizer fix -->
<link rel="stylesheet" href="{{asset('publicAdmin/assets/vendor/libs/node-waves/node-waves.css')}}" />

<!-- Core CSS -->
<link rel="stylesheet" href="{{asset('publicAdmin/assets/vendor/css/rtl/core.css')}}" class="template-customizer-core-css" />
<link rel="stylesheet" href="{{asset('publicAdmin/assets/vendor/css/rtl/theme-default.css')}}"       class="template-customizer-theme-css" />
<link rel="stylesheet" href="{{asset('publicAdmin/assets/css/demo.css')}}" />

<!-- Vendors CSS -->
<link rel="stylesheet" href="{{asset('publicAdmin/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css')}}" />
<link rel="stylesheet" href="{{asset('publicAdmin/assets/vendor/libs/typeahead-js/typeahead.css')}}" />
<link rel="stylesheet" href="{{asset('publicAdmin/assets/vendor/libs/apex-charts/apex-charts.css')}}" />
<link rel="stylesheet" href="{{asset('publicAdmin/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('publicAdmin/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('publicAdmin/assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css')}}">

<!-- Page CSS -->

<link rel="stylesheet" href="{{asset('publicAdmin/assets/vendor/css/pages/app-logistics-dashboard.css')}}" />

<!-- Helpers -->
<script src="{{asset('publicAdmin/assets/vendor/js/helpers.js')}}"></script>
<!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
<!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->

<!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
<script src="{{asset('publicAdmin/assets/js/config.js')}}"></script>

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead

        <link rel="icon" type="image/x-icon" href="img/blue.png" />

    </head>
    <body class="font-sans antialiased">
        @inertia

              <!-- Core JS -->
  <!-- build:js assets/vendor/js/core.js -->
  <script src="{{asset('publicAdmin/assets/vendor/libs/jquery/jquery.js')}}"></script>


  <script src="{{asset('publicAdmin/assets/vendor/libs/popper/popper.js')}}"></script>

  <script src="{{asset('publicAdmin/assets/vendor/js/bootstrap.js')}}"></script>
  <script src="{{asset('publicAdmin/assets/vendor/libs/node-waves/node-waves.js')}}"></script>
  
  <script src="{{asset('publicAdmin/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js')}}"></script>
  <script src="{{asset('publicAdmin/assets/vendor/libs/hammer/hammer.js')}}"></script>
  
  <script src="{{asset('publicAdmin/assets/vendor/libs/i18n/i18n.js')}}"></script>
  <script src="{{asset('publicAdmin/assets/vendor/libs/typeahead-js/typeahead.js')}}"></script>
  <script src="{{asset('publicAdmin/assets/vendor/js/menu.js')}}"></script>
  <script src="{{asset('publicAdmin/assets/vendor/libs/apex-charts/apexcharts.js')}}"></script>
  <script src="{{asset('publicAdmin/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>

  <!-- Main JS -->
  <script src="{{asset('publicAdmin/assets/js/main.js')}}"></script>

  <script src="{{asset('publicAdmin/assets/vendor/libs/chartjs/chartjs.js')}}"></script>
  <!-- Page JS -->
  <script src="{{asset('publicAdmin/assets/js/app-logistics-dashboard.js')}}"></script>
  <script src="{{asset('publicAdmin/assets/js/tables-datatables-advanced.js')}}"></script>

  <script src="{{asset('publicAdmin/assets/js/charts-chartjs.js')}}"></script>

  <script src="{{asset('publicAdmin/assets/js/app-ecommerce-product-list.js')}}"></script>
    </body>



</html>
