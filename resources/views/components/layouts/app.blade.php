@php
    /*
    | Navigation du back-office. Elle ne comportait jusqu'ici que « Accueil » et un
    | « Paramètres » mort : les 12 autres écrans n'étaient atteignables qu'en tapant
    | l'URL à la main ou en repassant par la page d'accueil.
    */
    $sections = [
        ['Pilotage', [
            ['admin.index', 'Accueil', 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75'],
            ['dashboard.statistiques', 'Statistiques', 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z'],
        ]],
        ['Catalogue', [
            ['dashboard.products', 'Produits', 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z'],
            ['dashboard.meilleurs-produits', 'Meilleurs produits', 'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941'],
            ['dashboard.complements', 'Compléments', 'M12 6v12m6-6H6M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['dashboard.categories', 'Catégories', 'M6 6.878V6a2.25 2.25 0 012.25-2.25h7.5A2.25 2.25 0 0118 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 004.5 9v.878m13.5-3A2.25 2.25 0 0119.5 9v.878m0 0a2.246 2.246 0 00-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0121 12v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6c0-.98.626-1.813 1.5-2.122'],
            ['dashboard.sub-categories', 'Sous-catégories', 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12'],
            ['dashboard.articles', 'Articles', 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'],
        ]],
        ['Activité', [
            ['dashboard.commands', 'Commandes', 'M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12A1.125 1.125 0 0119.75 22H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z'],
            ['dashboard.transactions', 'Transactions', 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z'],
            ['dashboard.clando', 'Clando', 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-8.25m0-11.25h6.75'],
            ['clando.board', 'Carte clando', 'M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z'],
            ['dashboard.courses', 'Courses', 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z'],
            ['dashboard.notes', 'Notes', 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z'],
            ['dashboard.lieux', 'Lieux', 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z'],
        ]],
        /*
        | Écrans de l'ancien back-office Bootstrap. Ils n'ont jamais cessé de
        | fonctionner, mais la barre latérale ne listait que les pages Folio :
        | Boutiques et Espace marchand n'étaient plus atteignables qu'en tapant
        | leur URL. On les rétablit ici en attendant leur reprise.
        */
        ['Boutiques', [
            ['dashboard.boutiques', 'Boutiques', 'M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z'],
            ['merchanddashboard', 'Espace marchand', 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25A2.25 2.25 0 0113.5 8.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z'],
        ]],
        ['Personnes', [
            ['dashboard.customers', 'Clients', 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'],
            ['dashboard.agents', 'Agents', 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0z'],
            ['dashboard.users', 'Utilisateurs', 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'],
        ]],
        ['Réglages', [
            ['dashboard.configuration', 'Configuration', 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.281z M15 12a3 3 0 11-6 0 3 3 0 016 0z'],
        ]],
    ];

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
