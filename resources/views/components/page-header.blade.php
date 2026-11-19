@props([
    'title',
    'subtitle' => null,
])

<div class="page-header">
    <div>
        <h1 class="page-title">{{ $title }}</h1>
        @if($subtitle)
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if(isset($actions) || $slot->isNotEmpty())
        <div class="d-flex gap-2">
            {{ $actions ?? $slot }}
        </div>
    @endif
</div>

