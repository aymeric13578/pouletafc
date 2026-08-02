@props([
    'show',          // expression Livewire, ex. "showModal"
    'title' => '',
    'subtitle' => null,
    'close' => 'closeModal',
    'width' => 'max-w-3xl',
])

{{--
    Modale unifiée du back-office.

    Les écrans utilisaient chacun leur propre markup, tous en position fixe sans
    défilement interne : sur un écran d'ordinateur portable, le bas des formulaires
    longs (produits, agents, utilisateurs) était inatteignable. Ici le corps défile
    seul, l'en-tête et le pied restent visibles.
--}}
@if ($show)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" wire:click="{{ $close }}"></div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative flex max-h-[90vh] w-full {{ $width }} animate-scale-in flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">

                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-gray-100 px-6 py-4">
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-bold text-gray-900">{{ $title }}</h2>
                        @if ($subtitle)
                            <p class="mt-0.5 truncate text-xs text-gray-500">{{ $subtitle }}</p>
                        @endif
                    </div>
                    <button type="button" wire:click="{{ $close }}"
                            class="shrink-0 rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700">
                        <span class="sr-only">Fermer</span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="admin-scrollbar min-h-0 flex-1 overflow-y-auto px-6 py-5">
                    {{ $slot }}
                </div>

                @isset($footer)
                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-gray-100 bg-gray-50 px-6 py-4">
                        {{ $footer }}
                    </div>
                @endisset
            </div>
        </div>
    </div>
@endif
