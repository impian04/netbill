@props([
    'title' => '',
    'value' => '0',
    'help' => null,
    'color' => 'primary',
    'borderColor' => null,
])

<div {{ $attributes->merge(['class' => 'stat-card']) }} @if($borderColor) style="border-left: 3px solid {{ $borderColor }};" @endif>
    <div class="flex-grow-1">
        <div class="stat-value text-{{ $color }}">{{ $value }}</div>
        <div class="stat-label">{{ $title }}</div>
        @if($help || $slot->isNotEmpty())
            <div class="stat-help">
                {{ $help ?? $slot }}
            </div>
        @endif
    </div>
</div>

