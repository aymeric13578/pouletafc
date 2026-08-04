@php
    /*
    | Espace marchand : le responsable d'une boutique n'y voit que la sienne.
    |
    | Navigation volontairement courte. Le back-office interne compte quinze
    | écrans parce qu'il pilote toute la plateforme ; un marchand n'a besoin que
    | de son catalogue, de ses commandes et de sa fiche.
    */
    $liens = [
        ['merchand.index', 'Tableau de bord', 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75'],
        ['merchand.produits', 'Mes produits', 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m16.5 0a48.7 48.7 0 00-16.5 0'],
        ['merchand.commandes', 'Mes commandes', 'M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12A1.125 1.125 0 0119.75 22H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z'],
        ['merchand.boutique', 'Ma boutique', 'M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614'],
    ];

    $currentRoute = request()->route()?->getName();
    $boutique = $boutiqueCourante ?? null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Espace marchand' }} · {{ $boutique->shop_name ?? 'Poulet AFC' }}</title>

    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/dashboard.css'])
    @livewireStyles
</head>

<body class="h-full bg-slate-100 font-sans text-gray-900 antialiased">
    <div x-data="{ sidebar: false }" class="min-h-full lg:flex">

        <div x-show="sidebar" x-cloak x-transition.opacity @click="sidebar = false"
             class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"></div>

        {{-- Vert plutôt que le bleu du back-office : un marchand doit voir d'un
             coup d'œil qu'il est dans son espace et non dans l'administration. --}}
        <aside x-cloak
               :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col bg-gradient-to-b from-emerald-800 via-emerald-800 to-emerald-900 transition-transform duration-300 lg:translate-x-0 lg:static lg:z-auto">

            <div class="flex items-center gap-3 border-b border-white/10 px-5 py-4">
                @if ($boutique?->logo)
                    <img src="{{ $boutique->logo }}" class="h-10 w-10 rounded-xl object-cover ring-2 ring-white/20" alt="">
                @else
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 text-sm font-bold text-white">
                        {{ strtoupper(substr($boutique->shop_name ?? 'B', 0, 2)) }}
                    </span>
                @endif
                <div class="min-w-0 leading-tight">
                    <p class="truncate text-sm font-extrabold text-white">{{ $boutique->shop_name ?? 'Ma boutique' }}</p>
                    <p class="text-[11px] text-emerald-300">Espace marchand</p>
                </div>
            </div>

            <nav class="admin-scrollbar flex-1 space-y-0.5 overflow-y-auto px-3 py-5">
                @foreach ($liens as [$route, $nom, $icone])
                    @php $actif = $currentRoute === $route; @endphp
                    <a href="{{ route($route) }}"
                       @class([
                           'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-200',
                           'bg-accent-500 text-white shadow-lg shadow-accent-700/40' => $actif,
                           'text-white/70 hover:bg-white/10 hover:text-white' => ! $actif,
                       ])>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.7" class="h-5 w-5 shrink-0 {{ $actif ? '' : 'text-emerald-300/70 group-hover:text-emerald-300' }}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone }}" />
                        </svg>
                        {{ $nom }}
                    </a>
                @endforeach
            </nav>

            <div class="border-t border-white/10 p-3">
                <a href="{{ url('/') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-white/60 transition-colors hover:bg-white/10 hover:text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-5 w-5 text-emerald-300/70">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                    Voir la boutique
                </a>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b-2 border-accent-400 bg-white/95 px-4 shadow-sm backdrop-blur sm:px-6">
                <button type="button" @click="sidebar = true"
                        class="rounded-lg p-2 text-emerald-700 transition-colors hover:bg-emerald-50 lg:hidden">
                    <span class="sr-only">Ouvrir la navigation</span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <h1 class="min-w-0 flex-1 truncate text-base font-bold text-emerald-800 sm:text-lg">{{ $title ?? 'Espace marchand' }}</h1>

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

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastr@2.1.4/build/toastr.min.js"></script>
    <script>
        toastr.options = { positionClass: 'toast-bottom-right', progressBar: true, timeOut: 4000 };
    </script>

    @livewireScripts
</body>

</html>
