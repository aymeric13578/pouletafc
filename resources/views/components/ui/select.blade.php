@props(['error' => false])

<select {{ $attributes->merge([
    'class' => 'block w-full rounded-lg border bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-colors duration-200 focus:outline-none focus:ring-2 disabled:bg-gray-50 disabled:text-gray-500 '
        . ($error
            ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
            : 'border-gray-300 focus:border-brand-500 focus:ring-brand-100'),
]) }}>
    {{ $slot }}
</select>
