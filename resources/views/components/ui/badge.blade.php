@props(['tone' => 'gray']) {{-- gray | success | warning | danger | brand | info --}}

@php
    $tones = [
        'gray' => 'bg-gray-100 text-gray-700 ring-gray-200',
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'warning' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'danger' => 'bg-red-50 text-red-700 ring-red-200',
        'brand' => 'bg-brand-50 text-brand-700 ring-brand-200',
        'info' => 'bg-sky-50 text-sky-700 ring-sky-200',
    ];
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ' . ($tones[$tone] ?? $tones['gray']),
]) }}>{{ $slot }}</span>
