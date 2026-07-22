<!DOCTYPE html>
<html lang="en" class="light-style layout-navbar-fixed layout-menu-fixed layout-compact" dir="ltr" data-theme="theme-semi-dark" data-assets-path="dash_release/assets/" data-template="vertical-menu-template-semi-dark">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>POULET AFC</title>
    <meta name="description" content="POULET AFC" />
    <meta name="keywords" content="POULET AFC">
    <link rel="canonical" href="">
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0], j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='../../../../www.googletagmanager.com/gtm5445.html?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-5J3LMKC');</script>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{asset('logo_blue.png')}}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="{{asset('dash_release/assets/vendor/fonts/fontawesome.css')}}" />
    <link rel="stylesheet" href="{{asset('dash_release/assets/vendor/fonts/tabler-icons.css')}}"/>
    <link rel="stylesheet" href="{{asset('dash_release/assets/vendor/fonts/flag-icons.css')}}" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/themify-icons/1.0.0/css/themify-icons.min.css" /> <!-- Ajout de Themify Icons -->

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{asset('dash_release/assets/vendor/css/rtl/core.css')}}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{asset('dash_release/assets/vendor/css/rtl/theme-semi-dark.css')}}" class="template-customizer-theme-css" />
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="{{asset('dash_release/assets/vendor/css/pages/cards-advance.css')}}" />

    <!-- Helpers -->
    <script src="{{asset('dash_release/assets/vendor/js/helpers.js')}}"></script>
    <script src="{{asset('dash_release/assets/vendor/js/template-customizer.js')}}"></script>
    <script src="{{asset('dash_release/assets/js/config.js')}}"></script>

    <!-- Core JS -->
    <script src="{{ asset('js/jquery-3.7.1.min.js') }}"></script> <!-- jQuery 3.7.1 -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script> <!-- DataTables Core -->
    <script src="{{asset('dash_release/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script> <!-- DataTables Bootstrap 5 -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script> <!-- Toastr -->
    <script src="{{asset('dash_release/assets/vendor/js/bootstrap.js')}}"></script> <!-- Bootstrap 5 -->
</head>

<body>
    @include('flashMessage')
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5DDHKGP" height="0" width="0" style="display: none; visibility: hidden"></iframe></noscript>

    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="index.html" class="app-brand-link">
                        <span class="app-brand-text demo menu-text fw-bold">POULET AFC</span>
                    </a>
                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
                        <i class="ti menu-toggle-icon d-none d-xl-block ti-sm align-middle"></i>
                        <i class="ti ti-x d-block d-xl-none ti-sm align-middle"></i>
                    </a>
                </div>
                <div class="menu-inner-shadow"></div>
                <ul class="menu-inner py-1">
                    <li class="menu-item active">
                        <a href="/dashboard" class="menu-link">
                            <div data-i18n="Accueil">Accueil</div>
                        </a>
                    </li>
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text" data-i18n="Menu">Menu</span>
                    </li>
                    <li class="menu-item">
                        <a href="/user_list" class="menu-link">
                            <i class="menu-icon tf-icons ti ti-users"></i>
                            <div data-i18n="Utilisateurs">Utilisateurs</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="/agent-list" class="menu-link">
                            <i class="menu-icon tf-icons ti ti-users"></i>
                            <div data-i18n="Agents">Agents</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="/customers_all" class="menu-link">
                            <i class="menu-icon tf-icons ti ti-messages"></i>
                            <div data-i18n="Clients">Clients</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="{{route('clandolist')}}" class="menu-link">
                            <i class="menu-icon tf-icons ti ti-car"></i>
                            <div data-i18n="Clando">Clando</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="/order-list" class="menu-link">
                            <i class="menu-icon tf-icons ti ti-truck"></i>
                            <div data-i18n="Commandes">Commande</div>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons ti ti-file-description"></i>
                            <div data-i18n="Produits">Produits</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="/list_products" class="menu-link">
                                    <div data-i18n="Lister">Lister</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="{{route('addProduct')}}" class="menu-link">
                                    <div data-i18n="Ajouter">Ajouter</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons ti ti-archive"></i>
                            <div data-i18n="Catégories">Catégories</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="{{route('categorylist')}}" class="menu-link">
                                    <div data-i18n="Lister">Lister</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="{{route('addcategory')}}" class="menu-link">
                                    <div data-i18n="Ajouter">Ajouter</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons ti ti-home"></i>
                            <div data-i18n="Boutique">Boutique</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="{{route('shoplist')}}" class="menu-link">
                                    <div data-i18n="Lister">Lister</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="{{route('addshop')}}" class="menu-link">
                                    <div data-i18n="Ajouter">Ajouter</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons ti ti-shield"></i>
                            <div data-i18n="Sous-catégories">Sous-catégories</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="{{route('subcategorylist')}}" class="menu-link">
                                    <div data-i18n="Lister">Lister</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="{{route('addsubcategory')}}" class="menu-link">
                                    <div data-i18n="Ajouter">Ajouter</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons ti ti-file-description"></i>
                            <div data-i18n="Articles">Articles</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="{{route('listArticle')}}" class="menu-link">
                                    <div data-i18n="Lister">Lister</div>
                                </a>
                            </li>
                            <li class="menu-item">
                                <a href="{{route('addArticle')}}" class="menu-link">
                                    <div data-i18n="Ajouter">Ajouter</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons ti ti-id-badge"></i>
                            <div data-i18n="Gestion des agents">Gestion des agents</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="{{route('agentlist')}}" class="menu-link">
                                    <div data-i18n="Lister">Lister</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons ti ti-wallet"></i>
                            <div data-i18n="Finances">Finances</div>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item">
                                <a href="{{route('financelist')}}" class="menu-link">
                                    <div data-i18n="Bilan financier">Bilan financier</div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item">
                        <a href="{{route('parameters.index')}}" class="menu-link">
                            <i class="menu-icon tf-icons ti ti-settings"></i>
                            <div data-i18n="Paramètres">Parametres</div>
                        </a>
                    </li>
                    <li class="menu-item active">
                        <a href="{{ route('logoutUser') }}" class="menu-link">
                            <div data-i18n="Deconnexion">Deconnexion</div>
                        </a>
                    </li>
                </ul>
            </aside>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="ti ti-menu-2 ti-sm"></i>
                        </a>
                    </div>
                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item navbar-search-wrapper mb-0">
                                <a class="nav-item nav-link search-toggler d-flex align-items-center px-0" href="javascript:void(0);">
                                    <i class="ti ti-search ti-md me-2"></i>
                                    <span class="d-none d-md-inline-block text-muted">Search (Ctrl+/)</span>
                                </a>
                            </div>
                        </div>
                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <li class="nav-item dropdown-language dropdown me-2 me-xl-0">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <i class='ti ti-language rounded-circle ti-md'></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="javascript:void(0);" data-language="en" data-text-direction="ltr"><span class="align-middle">English</span></a></li>
                                    <li><a class="dropdown-item" href="javascript:void(0);" data-language="fr" data-text-direction="ltr"><span class="align-middle">French</span></a></li>
                                    <li><a class="dropdown-item" href="javascript:void(0);" data-language="ar" data-text-direction="rtl"><span class="align-middle">Arabic</span></a></li>
                                    <li><a class="dropdown-item" href="javascript:void(0);" data-language="de" data-text-direction="ltr"><span class="align-middle">German</span></a></li>
                                </ul>
                            </li>
                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="{{asset('dash_release/assets/img/avatars/1.png')}}" alt class="h-auto rounded-circle">
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="pages-account-settings-account.html">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="{{asset('dash_release/assets/img/avatars/1.png')}}" alt class="h-auto rounded-circle">
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <span class="fw-medium d-block">John Doe</span>
                                                    <small class="text-muted">Admin</small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="pages-profile-user.html"><i class="ti ti-user-check me-2 ti-sm"></i><span class="align-middle">My Profile</span></a></li>
                                    <li><a class="dropdown-item" href="pages-account-settings-account.html"><i class="ti ti-settings me-2 ti-sm"></i><span class="align-middle">Settings</span></a></li>
                                    <li><a class="dropdown-item" href="pages-account-settings-billing.html"><span class="d-flex align-items-center align-middle"><i class="flex-shrink-0 ti ti-credit-card me-2 ti-sm"></i><span class="flex-grow-1 align-middle">Billing</span><span class="flex-shrink-0 badge badge-center rounded-pill bg-label-danger w-px-20 h-px-20">2</span></span></a></li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="pages-faq.html"><i class="ti ti-help me-2 ti-sm"></i><span class="align-middle">FAQ</span></a></li>
                                    <li><a class="dropdown-item" href="pages-pricing.html"><i class="ti ti-currency-dollar me-2 ti-sm"></i><span class="align-middle">Pricing</span></a></li>
                                    <li><div class="dropdown-divider"></div></li>
                                    <li><a class="dropdown-item" href="auth-login-cover.html" target="_blank"><i class="ti ti-logout me-2 ti-sm"></i><span class="align-middle">Log Out</span></a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                    <div class="navbar-search-wrapper search-input-wrapper d-none">
                        <input type="text" class="form-control search-input container-xxl border-0" placeholder="Search..." aria-label="Search...">
                        <i class="ti ti-x ti-sm search-toggler cursor-pointer"></i>
                    </div>
                </nav>
                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        @yield('main')
                    </div>
                    <!-- Footer -->
                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl">
                            <div class="footer-container d-flex align-items-center justify-content-between py-2 flex-md-row flex-column">
                                <div>
                                    © <script>document.write(new Date().getFullYear())</script>, made with ❤️ by <a href="" target="_blank" class="footer-link text-primary fw-medium">Poulet AFC</a>
                                </div>
                            </div>
                        </div>
                    </footer>
                    <!-- / Footer -->
                    <div class="content-backdrop fade"></div>
                </div>
                <!-- Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
        <div class="drag-target"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS -->
    <script src="{{asset('dash_release/assets/vendor/libs/node-waves/node-waves.js')}}"></script>
    <script src="{{asset('dash_release/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js')}}"></script>
    <script src="{{asset('dash_release/assets/vendor/libs/hammer/hammer.js')}}"></script>
    <script src="{{asset('dash_release/assets/vendor/libs/i18n/i18n.js')}}"></script>
    <script src="{{asset('dash_release/assets/vendor/libs/typeahead-js/typeahead.js')}}"></script>
    <script src="{{asset('dash_release/assets/vendor/js/menu.js')}}"></script>

    <!-- Vendors JS -->
    <script src="{{asset('dash_release/assets/vendor/libs/apex-charts/apexcharts.js')}}"></script>
    <script src="{{asset('dash_release/assets/vendor/libs/swiper/swiper.js')}}"></script>

    <!-- Main JS -->
    <script src="{{asset('dash_release/assets/js/main.js')}}"></script>

    <!-- Page JS -->
    <script src="{{asset('dash_release/assets/js/dashboards-analytics.js')}}"></script>

    <!-- Placez les scripts spécifiques à la page ici -->
    @yield('page-script')
</body>
</html>