@php
    /*
    | Navigation du back-office. Elle ne comportait jusqu'ici que « Accueil » et un
    | « Paramètres » mort : les 12 autres écrans n'étaient atteignables qu'en tapant
    | l'URL à la main ou en repassant par la page d'accueil.
    */
    $sections = \App\Support\MenuTableauDeBord::pourUtilisateur(auth()->user());

    $currentRoute = request()->route()?->getName();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Tableau de bord' }} · Poulet AFC</title>

    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/dashboard.css'])
    @livewireStyles
</head>

{{-- Fond légèrement teinté de bleu plutôt qu'un gris neutre : les cartes blanches
     s'en détachent, au lieu de se fondre dans un écran uniformément blanc. --}}
<body class="h-full bg-slate-100 font-sans text-gray-900 antialiased">
    <div x-data="{ sidebar: false }" class="min-h-full lg:flex">

        {{-- Voile de fermeture sur mobile --}}
        <div x-show="sidebar" x-cloak x-transition.opacity @click="sidebar = false"
             class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"></div>

        {{-- Navigation latérale --}}
        <aside x-cloak
               :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col bg-gradient-to-b from-brand-800 via-brand-800 to-brand-900 transition-transform duration-300 lg:translate-x-0 lg:static lg:z-auto">

            <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
                <img src="{{ asset('images/logo.png') }}" class="h-9 w-auto" alt="Poulet AFC">
                <div class="leading-tight">
                    <p class="text-sm font-extrabold text-white">Poulet AFC</p>
                    <p class="text-[11px] text-accent-300">Administration</p>
                </div>
            </div>

            <nav class="admin-scrollbar flex-1 space-y-6 overflow-y-auto px-3 py-5">
                @foreach ($sections as [$label, $links])
                    <div>
                        <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-wider text-white/35">{{ $label }}</p>
                        <ul class="space-y-0.5">
                            @foreach ($links as [$route, $name, $icon])
                                @php $active = $currentRoute === $route; @endphp
                                <li>
                                    {{-- L'élément actif est marqué par l'ambre de la marque : sur fond
                                         bleu foncé, c'est le seul contraste qui reste lisible en un coup d'œil. --}}
                                    <a href="{{ route($route) }}"
                                       @class([
                                           'group relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-all duration-200',
                                           'bg-accent-500 text-white shadow-lg shadow-accent-700/40' => $active,
                                           'text-white/70 hover:bg-white/10 hover:text-white' => ! $active,
                                       ])>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                             stroke="currentColor" stroke-width="1.7"
                                             class="h-5 w-5 shrink-0 {{ $active ? '' : 'text-accent-300/70 group-hover:text-accent-300' }}">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                                        </svg>
                                        {{ $name }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>

            <div class="border-t border-white/10 p-3">
                <a href="{{ url('/') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-white/60 transition-colors duration-200 hover:bg-white/10 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-5 w-5 text-accent-300/70">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                    Voir la boutique
                </a>
            </div>
        </aside>

        {{-- Colonne principale --}}
        <div class="flex min-w-0 flex-1 flex-col">

            <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b-2 border-accent-400 bg-white/95 px-4 shadow-sm backdrop-blur sm:px-6">
                <button type="button" @click="sidebar = true"
                        class="rounded-lg p-2 text-brand-700 transition-colors hover:bg-brand-50 lg:hidden">
                    <span class="sr-only">Ouvrir la navigation</span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <h1 class="min-w-0 flex-1 truncate text-base font-bold text-brand-800 sm:text-lg">{{ $title ?? 'Tableau de bord' }}</h1>

                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = ! open"
                            class="flex items-center gap-2 rounded-full py-1 pl-1 pr-3 transition-colors hover:bg-gray-100">
                        <img class="h-8 w-8 rounded-full object-cover ring-2 ring-white" src="{{ asset('images/user.png') }}" alt="">
                        <span class="hidden text-sm font-semibold text-gray-700 sm:block">{{ Auth::user()->name }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 text-gray-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div x-show="open" x-cloak @click.outside="open = false" x-transition
                         class="absolute right-0 z-50 mt-2 w-60 origin-top-right overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg">
                        <div class="border-b border-gray-100 px-4 py-3">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ Auth::user()->name }}</p>
                            <p class="truncate text-xs text-gray-500">{{ Auth::user()->email }}</p>
                            <span class="mt-1.5 inline-flex rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-brand-700">
                                {{ Auth::user()->role }}
                            </span>
                        </div>
                        <a href="{{ route('logout') }}"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                           class="block px-4 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-50">Se déconnecter</a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Notifications : conservé pour compatibilité avec les dispatch('notify') existants. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.js"></script>
    <script>
        toastr.options = { positionClass: 'toast-bottom-right', progressBar: true, timeOut: 4000 };

        /*
         * Écoute unique des notifications, pour toutes les pages.
         *
         * Chaque écran posait jusqu'ici son propre gestionnaire, et neuf sur
         * dix-huit n'en avaient aucun : leurs confirmations n'apparaissaient
         * jamais. Surtout, aucun ne fonctionnait — voir la lecture de la charge
         * ci-dessous.
         */
        window.addEventListener('notify', (evenement) => {
            const brut = evenement.detail ?? {};

            /*
             * Deux formes possibles.
             *
             * Livewire 3 transmet des paramètres nommés : dispatch('notify',
             * message: '…') donne { message: '…' }. Mais tout le tableau de bord
             * appelle dispatch('notify', ['message' => …]), c'est-à-dire un seul
             * paramètre positionnel — la charge arrive alors sous la clé 0. Lue
             * comme si elle était à plat, event.detail.type valait undefined,
             * toastr[undefined] levait une erreur, et rien ne s'affichait.
             */
            const charge = brut.message !== undefined ? brut : (brut[0] ?? {});

            if (!charge.message) return;

            // Un type inconnu ne doit pas faire échouer l'affichage du message.
            const type = ['success', 'error', 'warning', 'info'].includes(charge.type)
                ? charge.type
                : 'info';

            toastr[type](charge.message);
        });
    </script>

    @livewireScripts
</body>

</html>
