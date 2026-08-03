@props(['headers' => [], 'target' => null])

{{--
    Enveloppe de tableau : défilement horizontal contenu (les tableaux du
    back-office débordaient la page sur mobile) et voile de chargement branché sur
    une action Livewire via $target — aucun écran n'avait le moindre retour visuel
    pendant une recherche ou un changement de page.
--}}
<div class="relative">
    @if ($target)
        <div wire:loading.flex wire:target="{{ $target }}"
             class="absolute inset-0 z-10 items-center justify-center rounded-2xl bg-white/70 backdrop-blur-[1px]">
            <span class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-xs font-semibold text-brand-700 shadow-md ring-1 ring-gray-200">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
                </svg>
                Chargement…
            </span>
        </div>
    @endif

    <div class="admin-scrollbar overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            @if (count($headers))
                <thead class="bg-brand-700">
                    <tr>
                        @foreach ($headers as $header)
                            <th scope="col"
                                class="whitespace-nowrap px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-white">
                                {{ $header }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
            @endif

            <tbody class="divide-y divide-gray-100 bg-white">
                {{ $slot }}
            </tbody>
        </table>
    </div>
</div>
