<!DOCTYPE html>
<html lang="fr" class="light-style layout-wide customizer-hide" dir="ltr" data-theme="theme-semi-dark" data-assets-path="dash_release/assets/" data-template="vertical-menu-template-semi-dark">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Connexion - PouletAFC</title>
    <meta name="description" content="POULET AFC" />
    <meta name="keywords" content="POULET AFC">
    <link rel="canonical" href="">

    <!-- Google Tag Manager -->
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
            j.src = '../../../../www.googletagmanager.com/gtm5445.html?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-5J3LMKC');
    </script>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('logo_blue.png') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/fonts/fontawesome.css') }}" />
    <link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/fonts/tabler-icons.css') }}"/>
    <link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/fonts/flag-icons.css') }}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/css/rtl/core.css') }}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/css/rtl/theme-semi-dark.css') }}" class="template-customizer-theme-css" />
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
    <link rel="stylesheet" href="{{ asset('dash_release/assets/vendor/css/pages/cards-advance.css') }}" />

    <!-- Helpers -->
    <script src="{{ asset('dash_release/assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('dash_release/assets/vendor/js/template-customizer.js') }}"></script>
    <script src="{{ asset('dash_release/assets/js/config.js') }}"></script>

    <style>
        body {
            background: linear-gradient(135deg, #24409d 0%, #1a2f7a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            margin: 20px;
        }

        .login-left {
            background: linear-gradient(135deg, #24409d 0%, #1a2f7a 100%);
            padding: 60px 40px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .login-left h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .login-left p {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
        }

        .login-right {
            padding: 80px 50px;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-section img {
            max-width: 120px;
            height: auto;
        }

        .welcome-text h3 {
            color: #333;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .welcome-text p {
            color: #666;
            font-size: 1rem;
            margin-bottom: 30px;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-control {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: #24409d;
            box-shadow: 0 0 0 0.2rem rgba(36, 64, 157, 0.25);
        }

        .input-group-text {
            background: transparent;
            border: 2px solid #e0e0e0;
            border-left: none;
            border-radius: 0 10px 10px 0;
        }

        .input-group .form-control {
            border-right: none;
            border-radius: 10px 0 0 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #24409d 0%, #1a2f7a 100%);
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(36, 64, 157, 0.3);
        }

        .forgot-password {
            color: #24409d;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .forgot-password:hover {
            color: #1a2f7a;
            text-decoration: underline;
        }

        .register-link {
            text-align: center;
            margin-top: 25px;
            color: #666;
        }

        .register-link a {
            color: #24409d;
            text-decoration: none;
            font-weight: 600;
            margin-left: 5px;
            transition: all 0.3s ease;
        }

        .register-link a:hover {
            color: #1a2f7a;
            text-decoration: underline;
        }

        .decorative-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .login-left {
                display: none;
            }
            
            .login-right {
                padding: 40px 30px;
            }

            .welcome-text h3 {
                font-size: 1.5rem;
            }
        }

        .invalid-feedback {
            font-size: 0.875rem;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <noscript>
        <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5DDHKGP" height="0" width="0" style="display: none; visibility: hidden"></iframe>
    </noscript>

    <div class="login-container">
        <div class="row g-0">
            <!-- Left Side - Decorative -->
           

            <!-- Right Side - Login Form -->
            <div class="col-lg-12">
                <div class="login-right" style="max-width: 500px; margin: 0 auto;">
                    <!-- Logo Section -->
                    <div class="logo-section">
                        <img src="{{ asset('logo_blue.png') }}" alt="PouletAFC Logo">
                    </div>

                    <!-- Welcome Text -->
                    <div class="welcome-text">
                        <h3>Bienvenue sur PouletAFC 👋</h3>
                        <p>Entrez vos informations pour vous connecter</p>
                    </div>

                    <!-- Login Form -->
                    <form method="POST" action="{{ route('login') }}">
                        @csrf
                        
                        <!-- Email Field -->
                        <div class="mb-4">
                            <label for="email" class="form-label">Email ou Nom d'utilisateur</label>
                            <input 
                                id="email" 
                                type="email" 
                                class="form-control @error('email') is-invalid @enderror" 
                                name="email" 
                                value="{{ old('email') }}" 
                                required 
                                autocomplete="email" 
                                autofocus 
                                placeholder="exemple@email.com">
                            @error('email')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <!-- Password Field -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0" for="password">Mot de passe</label>
                                <a href="auth-forgot-password-cover.html" class="forgot-password">
                                    Mot de passe oublié ?
                                </a>
                            </div>
                            <div class="input-group">
                                <input 
                                    type="password" 
                                    id="password" 
                                    class="form-control @error('password') is-invalid @enderror" 
                                    name="password" 
                                    required 
                                    autocomplete="current-password" 
                                    placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;">
                                <span class="input-group-text cursor-pointer" onclick="togglePassword()">
                                    <i class="ti ti-eye-off" id="toggleIcon"></i>
                                </span>
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary w-100">
                            Se connecter
                        </button>

                        <!-- Register Link -->
                        <div class="register-link">
                            <span>Pas encore de compte ?</span>
                            <a href="/register">Créer un compte</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="{{ asset('dash_release/assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('dash_release/assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('dash_release/assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('dash_release/assets/vendor/libs/node-waves/node-waves.js') }}"></script>
    <script src="{{ asset('dash_release/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('dash_release/assets/vendor/libs/hammer/hammer.js') }}"></script>
    <script src="{{ asset('dash_release/assets/vendor/libs/i18n/i18n.js') }}"></script>
    <script src="{{ asset('dash_release/assets/vendor/libs/typeahead-js/typeahead.js') }}"></script>
    <script src="{{ asset('dash_release/assets/vendor/js/menu.js') }}"></script>

    <!-- Vendors JS -->
    <script src="{{ asset('dash_release/assets/vendor/libs/%40form-validation/popular.js') }}"></script>
    <script src="{{ asset('dash_release/assets/vendor/libs/%40form-validation/bootstrap5.js') }}"></script>
    <script src="{{ asset('dash_release/assets/vendor/libs/%40form-validation/auto-focus.js') }}"></script>

    <!-- Main JS -->
    <script src="{{ asset('dash_release/assets/js/main.js') }}"></script>
    <script src="{{ asset('dash_release/assets/js/pages-auth.js') }}"></script>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('ti-eye-off');
                toggleIcon.classList.add('ti-eye');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('ti-eye');
                toggleIcon.classList.add('ti-eye-off');
            }
        }
    </script>
</body>

</html>