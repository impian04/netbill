@props([
    'action' => 'view',
    'size'   => 'sm',
    'title'  => null,
    'href'   => null,
    'type'   => 'button',
])

@php
$actionMap = [
    'view'     => ['class' => 'btn-outline-secondary', 'icon' => 'bi-eye',       'defaultTitle' => 'Lihat Detail'],
    'edit'     => ['class' => 'btn-outline-primary',   'icon' => 'bi-pencil',    'defaultTitle' => 'Ubah'],
    'delete'   => ['class' => 'btn-outline-danger',    'icon' => 'bi-trash',     'defaultTitle' => 'Hapus'],
    'route'    => ['class' => 'btn-outline-secondary', 'icon' => 'bi-geo-alt',   'defaultTitle' => 'Rute'],
    'call'     => ['class' => 'btn-outline-secondary', 'icon' => 'bi-telephone', 'defaultTitle' => 'Telepon'],
    'copy'     => ['class' => 'btn-outline-secondary', 'icon' => 'bi-clipboard', 'defaultTitle' => 'Salin'],
    'download' => ['class' => 'btn-outline-primary',   'icon' => 'bi-download',  'defaultTitle' => 'Unduh'],
];

$config = $actionMap[$action] ?? $actionMap['view'];
$btnTitle = $title ?? $config['defaultTitle'];
$btnClass = $config['class'];
$iconClass = $config['icon'];
$sizeClass = $size ? "btn-{$size}" : 'btn-sm';
$baseClass = "btn {$sizeClass} {$btnClass}";
@endphp

@if($href)
    <a href="{{ $href }}" title="{{ $btnTitle }}" aria-label="{{ $btnTitle }}" {{ $attributes->merge(['class' => $baseClass]) }}>
        <i class="bi {{ $iconClass }}"></i>
    </a>
@else
    <button type="{{ $type }}" title="{{ $btnTitle }}" aria-label="{{ $btnTitle }}" {{ $attributes->merge(['class' => $baseClass]) }}>
        <i class="bi {{ $iconClass }}"></i>
    </button>
@endif

