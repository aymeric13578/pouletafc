<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PouletAFC - Élevage et Livraison de Poulet de Qualité</title>
    <meta name="description" content="PouletAFC - Leader dans l'élevage et la commercialisation de poulet. Application mobile avec livraison géolocalisée.">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #24409d 0%, #1a2f7a 100%);
        }
        
        .gradient-text {
            background: linear-gradient(135deg, #24409d 0%, #1a2f7a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hover-scale {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .hover-scale:hover {
            transform: scale(1.05) translateY(-5px);
            box-shadow: 0 20px 40px rgba(36, 64, 157, 0.2);
        }
        
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .animate-bounce-slow {
            animation: bounce-slow 2s ease-in-out infinite;
        }
        
        @keyframes bounce-slow {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .animate-pulse-slow {
            animation: pulse-slow 3s ease-in-out infinite;
        }
        
        @keyframes pulse-slow {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }
        
        .animate-slide-in-left {
            animation: slide-in-left 0.8s ease-out;
        }
        
        @keyframes slide-in-left {
            0% { opacity: 0; transform: translateX(-50px); }
            100% { opacity: 1; transform: translateX(0); }
        }
        
        .animate-slide-in-right {
            animation: slide-in-right 0.8s ease-out;
        }
        
        @keyframes slide-in-right {
            0% { opacity: 0; transform: translateX(50px); }
            100% { opacity: 1; transform: translateX(0); }
        }
        
        .animate-fade-in {
            animation: fade-in 1s ease-out;
        }
        
        @keyframes fade-in {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in-up {
            animation: fade-in-up 0.6s ease-out forwards;
            opacity: 0;
        }
        
        @keyframes fade-in-up {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        
        .animate-rotate {
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .hover-lift {
            transition: all 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(36, 64, 157, 0.3);
        }
        
        .hover-glow:hover {
            box-shadow: 0 0 30px rgba(36, 64, 157, 0.5);
            transition: box-shadow 0.3s ease;
        }
        
        .stat-item {
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            transform: scale(1.1);
        }
        
        .service-card {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .service-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: 0 25px 50px rgba(36, 64, 157, 0.25);
        }
        
        .btn-animated {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .btn-animated::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn-animated:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .btn-animated:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(36, 64, 157, 0.4);
        }
        
        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }
        
        .shape {
            position: absolute;
            opacity: 0.1;
            animation: float-shapes 20s infinite ease-in-out;
        }
        
        @keyframes float-shapes {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(100px, -100px) rotate(90deg); }
            50% { transform: translate(-50px, -200px) rotate(180deg); }
            75% { transform: translate(-100px, -100px) rotate(270deg); }
        }
        
        .shape:nth-child(1) {
            top: 10%;
            left: 10%;
            animation-delay: 0s;
            animation-duration: 25s;
        }
        
        .shape:nth-child(2) {
            top: 60%;
            left: 80%;
            animation-delay: 5s;
            animation-duration: 20s;
        }
        
        .shape:nth-child(3) {
            top: 80%;
            left: 20%;
            animation-delay: 10s;
            animation-duration: 30s;
        }
        
        .testimonial-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .testimonial-card:hover {
            transform: translateY(-10px) rotate(-1deg);
            box-shadow: 0 20px 40px rgba(36, 64, 157, 0.2);
        }
        
        .phone-mockup {
            animation: phone-float 4s ease-in-out infinite;
        }
        
        @keyframes phone-float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(2deg); }
        }
        
        .scroll-reveal {
            opacity: 0;
            transform: translateY(50px);
            transition: all 0.8s ease-out;
        }
        
        .scroll-reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
        
        .gradient-border {
            position: relative;
            background: white;
            border-radius: 1rem;
            padding: 2px;
        }
        
        .gradient-border::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 1rem;
            padding: 2px;
            background: linear-gradient(135deg, #24409d, #1a2f7a);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
        }
        
        .section-padding {
            padding: 80px 0;
        }
        
        @media (max-width: 768px) {
            .section-padding {
                padding: 50px 0;
            }
        }
        
        /* Particles background */
        .particles {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }
        
        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            animation: particle-float 15s infinite ease-in-out;
        }
        
        @keyframes particle-float {
            0%, 100% { 
                transform: translate(0, 0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translate(100px, -1000px);
                opacity: 0;
            }
        }
        
        .particle:nth-child(1) { left: 10%; animation-delay: 0s; width: 4px; height: 4px; }
        .particle:nth-child(2) { left: 20%; animation-delay: 2s; width: 6px; height: 6px; }
        .particle:nth-child(3) { left: 30%; animation-delay: 4s; width: 3px; height: 3px; }
        .particle:nth-child(4) { left: 40%; animation-delay: 6s; width: 5px; height: 5px; }
        .particle:nth-child(5) { left: 50%; animation-delay: 8s; width: 4px; height: 4px; }
        .particle:nth-child(6) { left: 60%; animation-delay: 10s; width: 6px; height: 6px; }
        .particle:nth-child(7) { left: 70%; animation-delay: 12s; width: 3px; height: 3px; }
        .particle:nth-child(8) { left: 80%; animation-delay: 14s; width: 5px; height: 5px; }
        .particle:nth-child(9) { left: 90%; animation-delay: 16s; width: 4px; height: 4px; }
    </style>
</head>
<body class="bg-gray-50">
    
    <!-- Navigation -->
    <nav class="bg-white shadow-lg fixed w-full top-0 z-50">
        <div class="container mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <img src="{{ asset('logo_blue.png') }}" alt="PouletAFC" class="h-12 w-auto">
                </div>
                
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#accueil" class="text-gray-700 hover:text-blue-700 transition">Accueil</a>
                    <a href="#apropos" class="text-gray-700 hover:text-blue-700 transition">À propos</a>
                    <a href="#services" class="text-gray-700 hover:text-blue-700 transition">Services</a>
                    <a href="#application" class="text-gray-700 hover:text-blue-700 transition">Application</a>
                    <a href="#contact" class="text-gray-700 hover:text-blue-700 transition">Contact</a>
                    <a href="/login" class="gradient-bg text-white px-6 py-2 rounded-full hover:shadow-lg transition">Connexion</a>
                </div>
                
                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" class="md:hidden text-gray-700">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
            
            <!-- Mobile Menu -->
            <div id="mobile-menu" class="hidden md:hidden mt-4 pb-4">
                <a href="#accueil" class="block py-2 text-gray-700 hover:text-blue-700">Accueil</a>
                <a href="#apropos" class="block py-2 text-gray-700 hover:text-blue-700">À propos</a>
                <a href="#services" class="block py-2 text-gray-700 hover:text-blue-700">Services</a>
                <a href="#application" class="block py-2 text-gray-700 hover:text-blue-700">Application</a>
                <a href="#contact" class="block py-2 text-gray-700 hover:text-blue-700">Contact</a>
                <a href="/login" class="block mt-4 gradient-bg text-white px-6 py-2 rounded-full text-center">Connexion</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="accueil" class="gradient-bg pt-32 pb-20 section-padding relative overflow-hidden">
        <!-- Animated Particles -->
        <div class="particles">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
        </div>
        
        <!-- Floating Shapes -->
        <div class="floating-shapes">
            <div class="shape w-64 h-64 bg-white rounded-full" style="top: 10%; left: 5%;"></div>
            <div class="shape w-48 h-48 bg-white rounded-full" style="top: 60%; right: 10%;"></div>
            <div class="shape w-32 h-32 bg-white rounded-full" style="bottom: 10%; left: 15%;"></div>
        </div>
        
        <div class="container mx-auto px-6 relative z-10">
            <div class="flex flex-col md:flex-row items-center justify-between">
                <div class="md:w-1/2 text-white mb-10 md:mb-0 animate-slide-in-left">
                    <h1 class="text-5xl md:text-6xl font-bold mb-6 leading-tight">
                        Poulet Frais, <br>Livré à Votre Porte
                    </h1>
                    <p class="text-xl mb-8 text-gray-100">
                        Élevage de qualité supérieure et livraison rapide grâce à notre application mobile avec géolocalisation en temps réel.
                    </p>
                    <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                        <a href="#application" class="btn-animated bg-white text-blue-700 px-8 py-4 rounded-full font-semibold hover:shadow-2xl transition text-center">
                            <i class="fas fa-mobile-alt mr-2"></i>Télécharger l'app
                        </a>
                        <a href="#services" class="btn-animated border-2 border-white text-white px-8 py-4 rounded-full font-semibold hover:bg-white hover:text-blue-700 transition text-center">
                            Nos Services
                        </a>
                    </div>
                </div>
                
                <div class="md:w-1/2 flex justify-center animate-slide-in-right">
                    <div class="relative">
                        <div class="w-80 h-80 bg-white bg-opacity-20 rounded-full absolute top-10 left-10 animate-pulse-slow"></div>
                        <div class="relative z-10 bg-white rounded-3xl shadow-2xl p-8 animate-bounce-slow hover-glow">
                            <i class="fas fa-drumstick-bite text-blue-700 text-9xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="bg-white py-16">
        <div class="container mx-auto px-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div class="text-center stat-item scroll-reveal">
                    <div class="text-4xl font-bold gradient-text mb-2">10K+</div>
                    <div class="text-gray-600">Clients Satisfaits</div>
                </div>
                <div class="text-center stat-item scroll-reveal" style="animation-delay: 0.1s;">
                    <div class="text-4xl font-bold gradient-text mb-2">5000+</div>
                    <div class="text-gray-600">Poulets Élevés/Mois</div>
                </div>
                <div class="text-center stat-item scroll-reveal" style="animation-delay: 0.2s;">
                    <div class="text-4xl font-bold gradient-text mb-2">98%</div>
                    <div class="text-gray-600">Qualité Garantie</div>
                </div>
                <div class="text-center stat-item scroll-reveal" style="animation-delay: 0.3s;">
                    <div class="text-4xl font-bold gradient-text mb-2">24/7</div>
                    <div class="text-gray-600">Service Client</div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="apropos" class="section-padding bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-4">
                    À Propos de <span class="gradient-text">PouletAFC</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Leader dans l'élevage et la commercialisation de poulet de qualité supérieure
                </p>
            </div>
            
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <div class="bg-gradient-to-br from-purple-100 to-purple-200 rounded-3xl p-8 h-96 flex items-center justify-center">
                        <i class="fas fa-warehouse text-blue-700 text-9xl"></i>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-3xl font-bold mb-6">Notre Mission</h3>
                    <p class="text-gray-700 mb-6 leading-relaxed">
                        Chez PouletAFC, nous nous engageons à fournir du poulet de la plus haute qualité, 
                        élevé dans des conditions optimales et respectueuses du bien-être animal.
                    </p>
                    <p class="text-gray-700 mb-6 leading-relaxed">
                        Notre ferme moderne utilise les dernières technologies d'élevage pour garantir 
                        la fraîcheur et la qualité de nos produits.
                    </p>
                    
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <div class="w-12 h-12 gradient-bg rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-check text-white text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-lg mb-1">Élevage Contrôlé</h4>
                                <p class="text-gray-600">Normes sanitaires strictes et contrôle qualité permanent</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-12 h-12 gradient-bg rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-check text-white text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-lg mb-1">Fraîcheur Garantie</h4>
                                <p class="text-gray-600">De la ferme à votre table en moins de 24 heures</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-12 h-12 gradient-bg rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-check text-white text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-lg mb-1">Prix Compétitifs</h4>
                                <p class="text-gray-600">Meilleur rapport qualité-prix du marché</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="section-padding bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-4">
                    Nos <span class="gradient-text">Services</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Une gamme complète de services pour répondre à tous vos besoins
                </p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                <!-- Service 1 -->
                <div class="service-card bg-gradient-to-br from-blue-50 to-white rounded-3xl p-8 shadow-lg scroll-reveal">
                    <div class="w-16 h-16 gradient-bg rounded-2xl flex items-center justify-center mb-6 animate-bounce-slow">
                        <i class="fas fa-egg text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Élevage de Poulets</h3>
                    <p class="text-gray-600 mb-4">
                        Élevage moderne avec alimentation bio et conditions optimales pour garantir 
                        la qualité supérieure de nos poulets.
                    </p>
                    <ul class="space-y-2 text-gray-600">
                        <li><i class="fas fa-check text-blue-700 mr-2"></i>Poulets fermiers</li>
                        <li><i class="fas fa-check text-blue-700 mr-2"></i>Poulets de chair</li>
                        <li><i class="fas fa-check text-blue-700 mr-2"></i>Poulets bio</li>
                    </ul>
                </div>
                
                <!-- Service 2 -->
                <div class="service-card bg-gradient-to-br from-blue-50 to-white rounded-3xl p-8 shadow-lg scroll-reveal" style="animation-delay: 0.2s;">
                    <div class="w-16 h-16 gradient-bg rounded-2xl flex items-center justify-center mb-6 animate-bounce-slow" style="animation-delay: 0.3s;">
                        <i class="fas fa-store text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Commercialisation</h3>
                    <p class="text-gray-600 mb-4">
                        Vente directe de poulets frais et produits dérivés via notre réseau de 
                        distribution et notre boutique en ligne.
                    </p>
                    <ul class="space-y-2 text-gray-600">
                        <li><i class="fas fa-check text-blue-700 mr-2"></i>Poulets entiers</li>
                        <li><i class="fas fa-check text-blue-700 mr-2"></i>Découpes variées</li>
                        <li><i class="fas fa-check text-blue-700 mr-2"></i>Œufs frais</li>
                    </ul>
                </div>
                
                <!-- Service 3 -->
                <div class="service-card bg-gradient-to-br from-blue-50 to-white rounded-3xl p-8 shadow-lg scroll-reveal" style="animation-delay: 0.4s;">
                    <div class="w-16 h-16 gradient-bg rounded-2xl flex items-center justify-center mb-6 animate-bounce-slow" style="animation-delay: 0.6s;">
                        <i class="fas fa-truck text-white text-3xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Livraison Express</h3>
                    <p class="text-gray-600 mb-4">
                        Livraison rapide et fiable à domicile avec suivi en temps réel via notre 
                        application mobile géolocalisée.
                    </p>
                    <ul class="space-y-2 text-gray-600">
                        <li><i class="fas fa-check text-blue-700 mr-2"></i>Livraison en 2h</li>
                        <li><i class="fas fa-check text-blue-700 mr-2"></i>Suivi GPS</li>
                        <li><i class="fas fa-check text-blue-700 mr-2"></i>Fraîcheur garantie</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Mobile App Section -->
    <section id="application" class="section-padding gradient-bg">
        <div class="container mx-auto px-6">
            <div class="flex flex-col md:flex-row items-center justify-between">
                <div class="md:w-1/2 text-white mb-10 md:mb-0">
                    <h2 class="text-4xl md:text-5xl font-bold mb-6">
                        Notre Application Mobile
                    </h2>
                    <p class="text-xl mb-8 text-gray-100">
                        Commandez votre poulet frais en quelques clics et suivez votre livraison en temps réel
                    </p>
                    
                    <div class="space-y-6 mb-10">
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-mobile-alt text-white text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-lg mb-1">Interface Intuitive</h4>
                                <p class="text-gray-100">Design moderne et facile à utiliser pour tous</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-map-marked-alt text-white text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-lg mb-1">Géolocalisation en Temps Réel</h4>
                                <p class="text-gray-100">Suivez votre livreur sur la carte jusqu'à votre porte</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-credit-card text-white text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-lg mb-1">Paiement Sécurisé</h4>
                                <p class="text-gray-100">Plusieurs options de paiement sécurisées</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-clock text-white text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-lg mb-1">Livraison Rapide</h4>
                                <p class="text-gray-100">Commandez maintenant, recevez dans 2 heures</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                        <a href="#" class="bg-white text-blue-700 px-8 py-4 rounded-full font-semibold hover:shadow-2xl transition flex items-center justify-center">
                            <i class="fab fa-apple text-2xl mr-3"></i>
                            <div class="text-left">
                                <div class="text-xs">Télécharger sur</div>
                                <div class="text-lg font-bold">App Store</div>
                            </div>
                        </a>
                        <a href="#" class="bg-white text-blue-700 px-8 py-4 rounded-full font-semibold hover:shadow-2xl transition flex items-center justify-center">
                            <i class="fab fa-google-play text-2xl mr-3"></i>
                            <div class="text-left">
                                <div class="text-xs">Disponible sur</div>
                                <div class="text-lg font-bold">Google Play</div>
                            </div>
                        </a>
                    </div>
                </div>
                
                <div class="md:w-1/2 flex justify-center">
                    <div class="relative phone-mockup">
                        <!-- Phone Mockup -->
                        <div class="relative bg-white rounded-[3rem] shadow-2xl p-4 w-80 h-[600px] border-8 border-gray-800 hover-glow">
                            <div class="bg-gray-100 rounded-[2.5rem] h-full overflow-hidden">
                                <!-- App Screenshot Placeholder -->
                                <div class="gradient-bg h-1/3 flex items-center justify-center">
                                    <i class="fas fa-drumstick-bite text-white text-6xl animate-pulse-slow"></i>
                                </div>
                                <div class="bg-white p-6 h-2/3">
                                    <div class="bg-gray-200 rounded-xl h-24 mb-4 animate-pulse"></div>
                                    <div class="bg-gray-200 rounded-xl h-24 mb-4 animate-pulse" style="animation-delay: 0.2s;"></div>
                                    <div class="bg-gray-200 rounded-xl h-24 animate-pulse" style="animation-delay: 0.4s;"></div>
                                </div>
                            </div>
                            <!-- Notch -->
                            <div class="absolute top-0 left-1/2 transform -translate-x-1/2 bg-gray-800 w-40 h-6 rounded-b-2xl"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="section-padding bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-4">
                    Comment <span class="gradient-text">Ça Marche ?</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Commandez votre poulet frais en 3 étapes simples
                </p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="w-24 h-24 gradient-bg rounded-full flex items-center justify-center mx-auto mb-6 text-white text-4xl font-bold">
                        1
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Choisissez</h3>
                    <p class="text-gray-600">
                        Parcourez notre catalogue et sélectionnez vos produits préférés depuis l'application
                    </p>
                </div>
                
                <div class="text-center">
                    <div class="w-24 h-24 gradient-bg rounded-full flex items-center justify-center mx-auto mb-6 text-white text-4xl font-bold">
                        2
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Commandez</h3>
                    <p class="text-gray-600">
                        Passez votre commande et payez en toute sécurité avec votre mode de paiement préféré
                    </p>
                </div>
                
                <div class="text-center">
                    <div class="w-24 h-24 gradient-bg rounded-full flex items-center justify-center mx-auto mb-6 text-white text-4xl font-bold">
                        3
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Recevez</h3>
                    <p class="text-gray-600">
                        Suivez votre livreur en temps réel et recevez vos produits frais à votre porte
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="section-padding bg-white">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-4">
                    Ce Que Disent <span class="gradient-text">Nos Clients</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Des milliers de clients satisfaits nous font confiance
                </p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                <div class="testimonial-card bg-gray-50 rounded-3xl p-8 shadow-lg scroll-reveal">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 gradient-bg rounded-full flex items-center justify-center text-white font-bold text-xl mr-4 animate-pulse-slow">
                            MK
                        </div>
                        <div>
                            <h4 class="font-bold">Marie Kouam</h4>
                            <div class="text-yellow-500">★★★★★</div>
                        </div>
                    </div>
                    <p class="text-gray-600">
                        "Excellente qualité et livraison ultra rapide ! L'application est très pratique pour suivre ma commande. Je recommande vivement !"
                    </p>
                </div>
                
                <div class="testimonial-card bg-gray-50 rounded-3xl p-8 shadow-lg scroll-reveal" style="animation-delay: 0.2s;">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 gradient-bg rounded-full flex items-center justify-center text-white font-bold text-xl mr-4 animate-pulse-slow" style="animation-delay: 0.3s;">
                            PN
                        </div>
                        <div>
                            <h4 class="font-bold">Paul Nkodo</h4>
                            <div class="text-yellow-500">★★★★★</div>
                        </div>
                    </div>
                    <p class="text-gray-600">
                        "Le poulet est toujours frais et de qualité supérieure. Le système de géolocalisation est génial, je sais exactement quand mon livreur arrive."
                    </p>
                </div>
                
                <div class="testimonial-card bg-gray-50 rounded-3xl p-8 shadow-lg scroll-reveal" style="animation-delay: 0.4s;">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 gradient-bg rounded-full flex items-center justify-center text-white font-bold text-xl mr-4 animate-pulse-slow" style="animation-delay: 0.6s;">
                            ST
                        </div>
                        <div>
                            <h4 class="font-bold">Sophie Tchuente</h4>
                            <div class="text-yellow-500">★★★★★</div>
                        </div>
                    </div>
                    <p class="text-gray-600">
                        "Service impeccable ! Commander n'a jamais été aussi facile. PouletAFC est devenu mon fournisseur de poulet préféré."
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="section-padding gradient-bg">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-4 text-white">
                    Contactez-Nous
                </h2>
                <p class="text-xl text-gray-100 max-w-3xl mx-auto">
                    Nous sommes là pour répondre à toutes vos questions
                </p>
            </div>
            
            <div class="grid md:grid-cols-2 gap-12">
                <div class="text-white">
                    <h3 class="text-2xl font-bold mb-6">Nos Coordonnées</h3>
                    
                    <div class="space-y-6">
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-map-marker-alt text-white"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-lg mb-1">Adresse</h4>
                                <p class="text-gray-100">Yaoundé, Cameroun</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-phone text-white"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-lg mb-1">Téléphone</h4>
                                <p class="text-gray-100">+237 6XX XXX XXX</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-envelope text-white"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-lg mb-1">Email</h4>
                                <p class="text-gray-100">contact@pouletafc.com</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4 flex-shrink-0">
                                <i class="fas fa-clock text-white"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-lg mb-1">Horaires</h4>
                                <p class="text-gray-100">Lun - Dim: 7h00 - 22h00</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-3xl p-8 shadow-2xl">
                    <form>
                        <div class="mb-4">
                            <label class="block text-gray-700 font-semibold mb-2">Nom complet</label>
                            <input type="text" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-700 outline-none transition" placeholder="Votre nom">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-semibold mb-2">Email</label>
                            <input type="email" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-700 outline-none transition" placeholder="votre@email.com">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 font-semibold mb-2">Téléphone</label>
                            <input type="tel" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-700 outline-none transition" placeholder="+237 6XX XXX XXX">
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-gray-700 font-semibold mb-2">Message</label>
                            <textarea rows="4" class="w-full px-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-700 outline-none transition" placeholder="Votre message..."></textarea>
                        </div>
                        
                        <button type="submit" class="w-full gradient-bg text-white py-4 rounded-xl font-semibold hover:shadow-2xl transition">
                            Envoyer le Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="container mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-8 mb-8">
                <div>
                    <div class="mb-4">
                        <img src="{{ asset('logo_blue.png') }}" alt="PouletAFC" class="h-16 w-auto">
                    </div>
                    <p class="text-gray-400">
                        Votre partenaire de confiance pour du poulet frais et de qualité supérieure.
                    </p>
                </div>
                
                <div>
                    <h4 class="font-bold text-lg mb-4">Liens Rapides</h4>
                    <ul class="space-y-2">
                        <li><a href="#accueil" class="text-gray-400 hover:text-white transition">Accueil</a></li>
                        <li><a href="#apropos" class="text-gray-400 hover:text-white transition">À propos</a></li>
                        <li><a href="#services" class="text-gray-400 hover:text-white transition">Services</a></li>
                        <li><a href="#application" class="text-gray-400 hover:text-white transition">Application</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-bold text-lg mb-4">Services</h4>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Élevage</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Vente en ligne</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Livraison</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white transition">Support client</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-bold text-lg mb-4">Suivez-nous</h4>
                    <div class="flex space-x-4">
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-blue-700 transition">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-blue-700 transition">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-blue-700 transition">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-blue-700 transition">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-800 pt-8 text-center text-gray-400">
                <p>&copy; 2024 PouletAFC. Tous droits réservés. Développé avec ❤️ au Cameroun</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Mobile Menu Toggle
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        
        mobileMenuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
        
        // Smooth Scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    // Close mobile menu if open
                    mobileMenu.classList.add('hidden');
                }
            });
        });
        
        // Navbar scroll effect
        window.addEventListener('scroll', () => {
            const nav = document.querySelector('nav');
            if (window.scrollY > 50) {
                nav.classList.add('shadow-xl');
            } else {
                nav.classList.remove('shadow-xl');
            }
        });
        
        // Scroll Reveal Animation
        const scrollRevealElements = document.querySelectorAll('.scroll-reveal');
        
        const revealOnScroll = () => {
            scrollRevealElements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;
                
                if (elementTop < windowHeight - 100) {
                    element.classList.add('active');
                }
            });
        };
        
        window.addEventListener('scroll', revealOnScroll);
        window.addEventListener('load', revealOnScroll);
        
        // Counter Animation for Stats
        const animateCounters = () => {
            const counters = document.querySelectorAll('.stat-item .gradient-text');
            
            counters.forEach(counter => {
                const target = counter.textContent;
                const isPercentage = target.includes('%');
                const isPlus = target.includes('+');
                const isSlash = target.includes('/');
                
                // Extract number
                let targetNumber = parseInt(target.replace(/[^0-9]/g, ''));
                
                if (isNaN(targetNumber)) return;
                
                let current = 0;
                const increment = targetNumber / 100;
                const duration = 2000; // 2 seconds
                const stepTime = duration / 100;
                
                const updateCounter = () => {
                    current += increment;
                    if (current < targetNumber) {
                        if (isPercentage) {
                            counter.textContent = Math.floor(current) + '%';
                        } else if (target.includes('K')) {
                            counter.textContent = Math.floor(current / 1000) + 'K+';
                        } else if (isSlash) {
                            counter.textContent = '24/7';
                            return;
                        } else {
                            counter.textContent = Math.floor(current) + (isPlus ? '+' : '');
                        }
                        setTimeout(updateCounter, stepTime);
                    } else {
                        counter.textContent = target;
                    }
                };
                
                // Start animation when element is in view
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            updateCounter();
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.5 });
                
                observer.observe(counter.parentElement);
            });
        };
        
        animateCounters();
        
        // Add parallax effect to hero section
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const parallaxElements = document.querySelectorAll('.animate-float, .animate-pulse-slow');
            
            parallaxElements.forEach(element => {
                const speed = 0.5;
                element.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });
    </script>
</body>
</html>