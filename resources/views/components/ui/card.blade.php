@props(['title' => null, 'subtitle' => null, 'padded' => true])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-200 bg-white shadow-sm']) }}>
    @if ($title || $subtitle || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="truncate text-sm font-bold text-gray-900">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 truncate text-xs text-gray-500">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="{{ $padded ? 'p-5' : '' }}">{{ $slot }}</div>
</div>
