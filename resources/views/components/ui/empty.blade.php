@props([
    'title' => 'Aucun résultat',
    'message' => null,
    'colspan' => 6,
    'inRow' => true, // false pour un usage hors <tbody>
])

@if ($inRow)
    <tr>
        <td colspan="{{ $colspan }}" class="p-0">
@endif

<div class="flex flex-col items-center justify-center px-6 py-14 text-center">
    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-6 w-6 text-gray-400">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m16.5 0a48.7 48.7 0 00-16.5 0" />
        </svg>
    </span>
    <p class="mt-3 text-sm font-semibold text-gray-900">{{ $title }}</p>
    @if ($message)
        <p class="mt-1 max-w-sm text-xs text-gray-500">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>

@if ($inRow)
        </td>
    </tr>
@endif
