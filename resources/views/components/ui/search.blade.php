@props([
    'model' => 'search',
    'placeholder' => 'Rechercher…',
])

<div class="relative w-full sm:max-w-xs">
    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 text-gray-400">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
    </span>

    {{-- debounce : la recherche partait à chaque frappe, soit une requête serveur par caractère. --}}
    <input type="search" wire:model.live.debounce.400ms="{{ $model }}" placeholder="{{ $placeholder }}"
           class="block w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-9 text-sm shadow-sm transition-colors duration-200 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">

    <span wire:loading wire:target="{{ $model }}" class="absolute inset-y-0 right-0 flex items-center pr-3">
        <svg class="h-4 w-4 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
        </svg>
    </span>
</div>
