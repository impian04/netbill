@php
    $currentSort = $filters['sort'] ?? '';
    $currentDir  = $filters['direction'] ?? 'asc';
    $newDir      = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
    $isActive    = $currentSort === $col;

    $queryParams = array_filter(
        $filters,
        fn ($v, $k) => $v !== '' && $v !== null && ! in_array($k, ['sort', 'direction']),
        ARRAY_FILTER_USE_BOTH
    );
    $queryParams['sort']      = $col;
    $queryParams['direction'] = $newDir;

    $baseParams = $routeParams ?? [];
    $href = route($routeName, array_merge($baseParams, $queryParams));
@endphp
<a href="{{ $href }}"
   class="text-decoration-none d-inline-flex align-items-center gap-1 {{ $isActive ? 'text-primary' : 'text-secondary' }}"
   style="font-size:.75rem;font-weight:600;letter-spacing:.5px;text-transform:uppercase;white-space:nowrap;">
    {{ $label }}
    <span style="font-size:.65rem;">
        @if ($isActive && $currentDir === 'asc')
            <i class="bi bi-caret-up-fill"></i>
        @elseif ($isActive && $currentDir === 'desc')
            <i class="bi bi-caret-down-fill"></i>
        @else
            <i class="bi bi-caret-up text-muted" style="opacity:.3;"></i>
        @endif
    </span>
</a>
