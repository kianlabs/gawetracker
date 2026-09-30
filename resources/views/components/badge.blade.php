@props([
    'variant' => 'default',
])

<span {{ $attributes->merge(['class' => 'badge badge-' . ($variant ?? 'default')]) }}>{{ $slot }}</span>
