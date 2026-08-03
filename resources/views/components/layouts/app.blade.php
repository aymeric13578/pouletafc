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
            ['dashboard.categories', 'Catégories', 'M6 6.878V6a2.25 2.25 0 012.25-2.25h7.5A2.25 2.25 0 0118 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 004.5 9v.878m13.5-3A2.25 2.25 0 0119.5 9v.878m0 0a2.246 2.246 0 00-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0121 12v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6c0-.98.626-1.813 1.5-2.122'],
            ['dashboard.sub-categories', 'Sous-catégories', 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12'],
            ['dashboard.articles', 'Articles', 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'],
        ]],
        ['Activité', [
            ['dashboard.commands', 'Commandes', 'M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12A1.125 1.125 0 0119.75 22H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z'],
            ['dashboard.transactions', 'Transactions', 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z'],
            ['dashboard.clando', 'Clando', 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-8.25m0-11.25h6.75'],
        ]],
        ['Personnes', [
            ['dashboard.customers', 'Clients', 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'],
            ['dashboard.agents', 'Agents', 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0z'],
            ['dashboard.operators', 'Opérateurs', 'M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3'],
            ['dashboard.users', 'Utilisateurs', 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'],
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
    </script>

    @livewireScripts
</body>

</html>
