@props([
    'label' => null,
    'for' => null,
    'required' => false,
    'error' => null,
    'hint' => null,
])

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="mb-1.5 block text-xs font-semibold text-gray-700">
            {{ $label }}
            @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint && ! $error)
        <p class="mt-1 text-xs text-gray-400">{{ $hint }}</p>
    @endif

    @if ($error)
        <p class="mt-1 flex items-center gap-1 text-xs font-medium text-red-600">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5 shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            {{ $error }}
        </p>
    @endif
</div>
