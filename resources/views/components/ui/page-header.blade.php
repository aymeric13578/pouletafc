@props(['title', 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-end justify-between gap-4']) }}>
    <div class="min-w-0">
        {{-- Barre ambre à gauche du titre : repère de marque répété sur chaque écran. --}}
        <div class="flex items-center gap-3">
            <span class="h-8 w-1.5 shrink-0 rounded-full bg-accent-500"></span>
            <h1 class="text-xl font-extrabold text-brand-800 sm:text-2xl">{{ $title }}</h1>
        </div>
        @if ($subtitle)
            <p class="mt-1 pl-[1.125rem] text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
