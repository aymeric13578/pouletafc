<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;

name('admin.index');

?>

<x-layouts.app>
    @volt
        <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 py-8">
            <div class="container mx-auto px-4 max-w-7xl">
                
                <!-- Header Section -->
                <div class="mb-8">
                    <h1 class="text-4xl font-bold text-gray-800 mb-2">Tableau de bord</h1>
                    <p class="text-gray-600">Gérez tous les modules de votre application</p>
                </div>

                <!-- Search Bar -->
                <div class="mb-8">
                    <form class="flex items-center max-w-2xl mx-auto">
                        <label for="voice-search" class="sr-only">Search</label>
                        <div class="relative w-full">
                            <div class="absolute inset-y-0 start-0 flex items-center ps-4 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" aria-hidden="true"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 21 21">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11.15 5.6h.01m3.337 1.913h.01m-6.979 0h.01M5.541 11h.01M15 15h2.706a1.957 1.957 0 0 0 1.883-1.325A9 9 0 1 0 2.043 11.89 9.1 9.1 0 0 0 7.2 19.1a8.62 8.62 0 0 0 3.769.9A2.013 2.013 0 0 0 13 18v-.857A2.034 2.034 0 0 1 15 15Z" />
                                </svg>
                            </div>
                            <input type="text" id="voice-search"
                                class="bg-white border border-gray-200 text-gray-900 text-sm rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-transparent block w-full ps-12 p-4 shadow-sm transition duration-200"
                                placeholder="Rechercher un module..." required />
                        </div>
                        <button type="submit"
                            class="inline-flex items-center py-4 px-6 ms-3 text-sm font-semibold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 focus:ring-4 focus:outline-none focus:ring-indigo-300 shadow-md hover:shadow-lg transition duration-200">
                            <svg class="w-4 h-4 me-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 20 20">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
                            </svg>
                            Rechercher
                        </button>
                    </form>
                </div>

                <!-- Cards Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

                    <!-- Card Produits -->
                    <div class="group bg-white rounded-xl shadow-md hover:shadow-xl p-6 text-center transition-all duration-300 hover:-translate-y-1 border border-gray-100">
                        <div class="flex justify-center mb-4">
                            <div class="bg-indigo-100 p-4 rounded-full group-hover:bg-indigo-600 transition duration-300">
                                <svg class="w-8 h-8 text-indigo-600 group-hover:text-white transition duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 mb-2">Produits</h2>
                        <p class="text-gray-600 text-sm mb-6 leading-relaxed">Gérez les produits, ajoutez, modifiez ou supprimez des articles.</p>
                        <a href="/dashboard/products"
                            class="inline-block w-full bg-indigo-600 text-white py-2.5 px-6 rounded-lg font-medium hover:bg-indigo-700 transition duration-200">
                            Accéder
                        </a>
                    </div>

                    <!-- Card Catégories -->
                    <div class="group bg-white rounded-xl shadow-md hover:shadow-xl p-6 text-center transition-all duration-300 hover:-translate-y-1 border border-gray-100">
                        <div class="flex justify-center mb-4">
                            <div class="bg-purple-100 p-4 rounded-full group-hover:bg-purple-600 transition duration-300">
                                <svg class="w-8 h-8 text-purple-600 group-hover:text-white transition duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 mb-2">Catégories</h2>
                        <p class="text-gray-600 text-sm mb-6 leading-relaxed">Créez et gérez les catégories des produits.</p>
                        <a href="/dashboard/categories"
                            class="inline-block w-full bg-purple-600 text-white py-2.5 px-6 rounded-lg font-medium hover:bg-purple-700 transition duration-200">
                            Accéder
                        </a>
                    </div>

                    <!-- Card Sous-catégories -->
                    <div class="group bg-white rounded-xl shadow-md hover:shadow-xl p-6 text-center transition-all duration-300 hover:-translate-y-1 border border-gray-100">
                        <div class="flex justify-center mb-4">
                            <div class="bg-pink-100 p-4 rounded-full group-hover:bg-pink-600 transition duration-300">
                                <svg class="w-8 h-8 text-pink-600 group-hover:text-white transition duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 mb-2">Sous-catégories</h2>
                        <p class="text-gray-600 text-sm mb-6 leading-relaxed">Gérez les sous-catégories pour organiser les produits.</p>
                        <a href="/dashboard/sub-categories"
                            class="inline-block w-full bg-pink-600 text-white py-2.5 px-6 rounded-lg font-medium hover:bg-pink-700 transition duration-200">
                            Accéder
                        </a>
                    </div>

                    <!-- Card Utilisateurs -->
                    <div class="group bg-white rounded-xl shadow-md hover:shadow-xl p-6 text-center transition-all duration-300 hover:-translate-y-1 border border-gray-100">
                        <div class="flex justify-center mb-4">
                            <div class="bg-blue-100 p-4 rounded-full group-hover:bg-blue-600 transition duration-300">
                                <svg class="w-8 h-8 text-blue-600 group-hover:text-white transition duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 mb-2">Utilisateurs</h2>
                        <p class="text-gray-600 text-sm mb-6 leading-relaxed">Créez, modifiez ou supprimez des comptes utilisateurs.</p>
                        <a href="/dashboard/users"
                            class="inline-block w-full bg-blue-600 text-white py-2.5 px-6 rounded-lg font-medium hover:bg-blue-700 transition duration-200">
                            Accéder
                        </a>
                    </div>

                    <!-- Card Commandes -->
                    <div class="group bg-white rounded-xl shadow-md hover:shadow-xl p-6 text-center transition-all duration-300 hover:-translate-y-1 border border-gray-100">
                        <div class="flex justify-center mb-4">
                            <div class="bg-green-100 p-4 rounded-full group-hover:bg-green-600 transition duration-300">
                                <svg class="w-8 h-8 text-green-600 group-hover:text-white transition duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 mb-2">Commandes</h2>
                        <p class="text-gray-600 text-sm mb-6 leading-relaxed">Gérez les commandes, suivez leur statut et mettez à jour les détails.</p>
                        <a href="/dashboard/commandes"
                            class="inline-block w-full bg-green-600 text-white py-2.5 px-6 rounded-lg font-medium hover:bg-green-700 transition duration-200">
                            Accéder
                        </a>
                    </div>

                    <!-- Card Clando -->
                    <div class="group bg-white rounded-xl shadow-md hover:shadow-xl p-6 text-center transition-all duration-300 hover:-translate-y-1 border border-gray-100">
                        <div class="flex justify-center mb-4">
                            <div class="bg-yellow-100 p-4 rounded-full group-hover:bg-yellow-600 transition duration-300">
                                <svg class="w-8 h-8 text-yellow-600 group-hover:text-white transition duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 14l9-5-9-5-9 5 9 5zm0 0v7m-9-7v7m18-7v7m-9-12v2m-6 4h12"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 mb-2">Clando</h2>
                        <p class="text-gray-600 text-sm mb-6 leading-relaxed">Gérez les services ou activités liés à Clando.</p>
                        <a href="/dashboard/clando"
                            class="inline-block w-full bg-yellow-600 text-white py-2.5 px-6 rounded-lg font-medium hover:bg-yellow-700 transition duration-200">
                            Accéder
                        </a>
                    </div>

                    <!-- Card Agents -->
                    <div class="group bg-white rounded-xl shadow-md hover:shadow-xl p-6 text-center transition-all duration-300 hover:-translate-y-1 border border-gray-100">
                        <div class="flex justify-center mb-4">
                            <div class="bg-red-100 p-4 rounded-full group-hover:bg-red-600 transition duration-300">
                                <svg class="w-8 h-8 text-red-600 group-hover:text-white transition duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a2 2 0 012-2h2a2 2 0 012 2v5m-4 0h4"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 mb-2">Agents</h2>
                        <p class="text-gray-600 text-sm mb-6 leading-relaxed">Gérez les agents et affectez-les à des tâches ou services.</p>
                        <a href="/dashboard/agents"
                            class="inline-block w-full bg-red-600 text-white py-2.5 px-6 rounded-lg font-medium hover:bg-red-700 transition duration-200">
                            Accéder
                        </a>
                    </div>

                    <!-- Card Statistiques -->
                    <div class="group bg-white rounded-xl shadow-md hover:shadow-xl p-6 text-center transition-all duration-300 hover:-translate-y-1 border border-gray-100">
                        <div class="flex justify-center mb-4">
                            <div class="bg-green-100 p-4 rounded-full group-hover:bg-cyan-600 transition duration-300">
                                <svg class="w-8 h-8 text-cyan-600 group-hover:text-white transition duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 mb-2">Statistiques</h2>
                        <p class="text-gray-600 text-sm mb-6 leading-relaxed">Consultez les statistiques des ventes, produits et performances.</p>
                        <a href="/dashboard/statistiques"
                            class="inline-block w-full bg-green-600 text-white py-2.5 px-6 rounded-lg font-medium hover:bg-cyan-700 transition duration-200">
                            Accéder
                        </a>
                    </div>

                    <!-- Card Clients -->
                    <div class="group bg-white rounded-xl shadow-md hover:shadow-xl p-6 text-center transition-all duration-300 hover:-translate-y-1 border border-gray-100">
                        <div class="flex justify-center mb-4">
                            <div class="bg-yellow-100 p-4 rounded-full group-hover:bg-teal-600 transition duration-300">
                                <svg class="w-8 h-8 text-teal-600 group-hover:text-white transition duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 mb-2">Clients</h2>
                        <p class="text-gray-600 text-sm mb-6 leading-relaxed">Gérez les informations des clients et leur historique d'achat.</p>
                        <a href="/dashboard/customers"
                            class="inline-block w-full bg-yellow-600 text-white py-2.5 px-6 rounded-lg font-medium hover:bg-teal-700 transition duration-200">
                            Accéder
                        </a>
                    </div>

                    <!-- Card Articles -->
                    <div class="group bg-white rounded-xl shadow-md hover:shadow-xl p-6 text-center transition-all duration-300 hover:-translate-y-1 border border-gray-100">
                        <div class="flex justify-center mb-4">
                            <div class="bg-indigo-100 p-4 rounded-full group-hover:bg-orange-600 transition duration-300">
                                <svg class="w-8 h-8 text-orange-600 group-hover:text-white transition duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 mb-2">Articles</h2>
                        <p class="text-gray-600 text-sm mb-6 leading-relaxed">Gérez les articles, leur contenu et leur publication.</p>
                        <a href="/dashboard/articles"
                            class="inline-block w-full bg-indigo-600 text-white py-2.5 px-6 rounded-lg font-medium hover:bg-orange-700 transition duration-200">
                            Accéder
                        </a>
                    </div>

                    <!-- Card Transactions -->
                    <div class="group bg-white rounded-xl shadow-md hover:shadow-xl p-6 text-center transition-all duration-300 hover:-translate-y-1 border border-gray-100">
                        <div class="flex justify-center mb-4">
                            <div class="bg-blue-100 p-4 rounded-full group-hover:bg-emerald-600 transition duration-300">
                                <svg class="w-8 h-8 text-emerald-600 group-hover:text-white transition duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 mb-2">Transactions</h2>
                        <p class="text-gray-600 text-sm mb-6 leading-relaxed">Consultez et gérez les détails des transactions financières.</p>
                        <a href="/dashboard/transactions"
                            class="inline-block w-full bg-blue-600 text-white py-2.5 px-6 rounded-lg font-medium hover:bg-emerald-700 transition duration-200">
                            Accéder
                        </a>
                    </div>

                    <!-- Card Opérateurs -->
                    <div class="group bg-white rounded-xl shadow-md hover:shadow-xl p-6 text-center transition-all duration-300 hover:-translate-y-1 border border-gray-100">
                        <div class="flex justify-center mb-4">
                            <div class="bg-red-100 p-4 rounded-full group-hover:bg-slate-600 transition duration-300">
                                <svg class="w-8 h-8 text-slate-600 group-hover:text-white transition duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a2 2 0 012-2h2a2 2 0 012 2v5m-4 0h4"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800 mb-2">Opérateurs</h2>
                        <p class="text-gray-600 text-sm mb-6 leading-relaxed">Gérez les opérateurs et leurs rôles dans le système.</p>
                        <a href="/dashboard/operators"
                            class="inline-block w-full bg-red-600 text-white py-2.5 px-6 rounded-lg font-medium hover:bg-slate-700 transition duration-200">
                            Accéder
                        </a>
                    </div>

                </div>
            </div>
        </div>
    @endvolt
</x-layouts.app>