@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'brand', // brand | success | warning | danger | accent
    'icon' => null,
])

@php
    /*
    | Tuiles en couleur pleine. Elles étaient blanches sur fond gris : sur un
    | tableau de bord entièrement composé de cartes blanches, plus rien ne
    | ressortait et l'écran paraissait vide.
    |
    | Chaque teinte porte un sens : bleu pour le neutre, ambre pour ce qui
    | demande une action, vert pour ce qui est acquis, rouge pour un problème.
    */
    $tones = [
        'brand' => 'from-brand-600 to-brand-800 text-white',
        'accent' => 'from-accent-400 to-accent-600 text-white',
        'success' => 'from-emerald-500 to-emerald-700 text-white',
        'warning' => 'from-amber-400 to-amber-600 text-white',
        'danger' => 'from-red-500 to-red-700 text-white',
    ];
    $toneClass = $tones[$tone] ?? $tones['brand'];
@endphp

<div {{ $attributes->merge([
    'class' => 'rounded-2xl bg-gradient-to-br p-5 shadow-md transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg ' . $toneClass,
]) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-xs font-bold uppercase tracking-wider text-white/70">{{ $label }}</p>
            <p class="mt-2 text-3xl font-extrabold tabular-nums">{{ $value }}</p>
            @if ($hint)
                <p class="mt-1 truncate text-xs text-white/70">{{ $hint }}</p>
            @endif
        </div>

        @if ($icon)
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/20 ring-1 ring-inset ring-white/25">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                </svg>
            </span>
        @endif
    </div>
</div>
