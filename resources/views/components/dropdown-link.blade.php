@props(['active' => false])

@php
    $classes = ($active ?? false)
                ? 'block w-full pl-4 pr-4 py-3 text-sm font-medium text-gray-900 bg-gray-100 hover:bg-gray-100 focus:outline-none focus:text-gray-900 focus:bg-gray-100 focus:border-gray-300 transition duration-150 ease-in-out'
                : 'block w-full pl-4 pr-4 py-3 text-sm font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:text-gray-900 focus:bg-gray-100 focus:border-gray-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>