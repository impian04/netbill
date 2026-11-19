@props([
    'title' => null,
    'code' => null,
    'badge' => null,
    'badgeColor' => 'secondary',
])

<div {{ $attributes->merge(['class' => 'card border shadow-sm mb-3 task-card']) }}>
    @if ($title || $code || $badge)
        <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                @if ($code)
                    <span class="badge bg-light text-dark border me-1">{{ $code }}</span>
                @endif
                <strong class="text-dark">{{ $title }}</strong>
            </div>
            @if ($badge)
                <x-status-badge :type="null" :status="$badge" />
            @endif
        </div>
    @endif

    <div class="card-body p-3">
        {{ $slot }}
    </div>

    @isset($actions)
        <div class="card-footer bg-light py-2 px-3 d-flex justify-content-end align-items-center flex-wrap gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>

