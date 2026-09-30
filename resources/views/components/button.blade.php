@props([
    'variant' => 'default',
    'size' => null,
])

<button {{ $attributes->merge(['class' => 'btn btn-' . ($variant ?? 'default') . ($size ? ' btn-' . $size : '')]) }}>{{ $slot }}</button>
