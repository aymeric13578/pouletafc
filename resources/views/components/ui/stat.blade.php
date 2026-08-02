@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'brand', // brand | success | warning | danger
    'icon' => null,
])

@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-700',
        'success' => 'bg-emerald-50 text-emerald-700',
        'warning' => 'bg-amber-50 text-amber-700',
        'danger' => 'bg-red-50 text-red-700',
    ];
    $toneClass = $tones[$tone] ?? $tones['brand'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-shadow duration-300 hover:shadow-md']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $label }}</p>
            <p class="mt-2 text-2xl font-extrabold text-gray-900">{{ $value }}</p>
            @if ($hint)
                <p class="mt-1 truncate text-xs text-gray-400">{{ $hint }}</p>
            @endif
        </div>

        @if ($icon)
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $toneClass }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                </svg>
            </span>
        @endif
    </div>
</div>
