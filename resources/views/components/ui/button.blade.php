@props([
    'variant' => 'primary', // primary | secondary | ghost | danger | success
    'size' => 'md',         // sm | md
    'type' => 'button',
    'href' => null,
])

@php
    $variants = [
        'primary' => 'bg-brand-600 text-white shadow-sm hover:bg-brand-700 focus-visible:outline-brand-600',
        'secondary' => 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus-visible:outline-gray-400',
        'ghost' => 'text-gray-500 hover:bg-gray-100 hover:text-gray-800 focus-visible:outline-gray-400',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-700 focus-visible:outline-red-600',
        'success' => 'bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 focus-visible:outline-emerald-600',
    ];

    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs gap-1.5',
        'md' => 'px-4 py-2.5 text-sm gap-2',
    ];

    $classes = implode(' ', [
        'inline-flex items-center justify-center rounded-lg font-semibold transition-all duration-200',
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
        'active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 disabled:active:scale-100',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
