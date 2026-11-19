@props([
    'variant' => 'primary',
    'size'    => 'sm',
    'href'    => null,
    'type'    => 'button',
])

@php
$variantMap = [
    'primary' => 'btn-primary',
    'approve' => 'btn-success',
    'warning' => 'btn-warning',
    'danger'  => 'btn-danger',
    'neutral' => 'btn-outline-secondary',
    'export'  => 'btn-outline-primary',
];

$variantClass = $variantMap[$variant] ?? 'btn-primary';
$sizeClass = $size ? "btn-{$size}" : 'btn-sm';
$baseClass = "btn {$sizeClass} {$variantClass}";
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $baseClass]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $baseClass]) }}>
        {{ $slot }}
    </button>
@endif

