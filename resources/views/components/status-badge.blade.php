@props([
    'type'   => null,
    'status' => '',
])

@php
$config = config('ui.badges', []);
$badgeInfo = null;
$statusKey = is_bool($status) ? ($status ? '1' : '0') : (string) $status;

if ($type && isset($config[$type])) {
    $badgeInfo = $config[$type][$statusKey] ?? null;
}

if (! $badgeInfo) {
    foreach ($config as $cat => $map) {
        if (isset($map[$statusKey])) {
            $badgeInfo = $map[$statusKey];
            break;
        }
    }
}

$label = $badgeInfo['label'] ?? ($status ?: '-');
$color = $badgeInfo['color'] ?? 'secondary';
@endphp

<span {{ $attributes->merge(['class' => "badge bg-{$color}"]) }}>{{ $label }}</span>

