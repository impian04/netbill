@props([
    'action' => null,
    'method' => 'GET',
    'mode' => 'instant', // 'instant' | 'apply'
    'id' => null,
])

@php
    $effectiveMode = in_array($mode, ['instant', 'apply'], true) ? $mode : 'instant';
@endphp

@if($action)
<form method="{{ strtoupper($method) }}" action="{{ $action }}" @if($id) id="{{ $id }}" @endif data-filter-mode="{{ $effectiveMode }}" {{ $attributes->except(['action','method','id','mode'])->merge(['class' => 'card filter-card']) }}>
    <div class="card-body">
        <div class="row g-2 align-items-end">
            {{ $slot }}
        </div>
    </div>
</form>
@else
<div @if($id) id="{{ $id }}" @endif data-filter-mode="{{ $effectiveMode }}" {{ $attributes->except(['id','mode'])->merge(['class' => 'card filter-card']) }}>
    <div class="card-body">
        <div class="row g-2 align-items-end">
            {{ $slot }}
        </div>
    </div>
</div>
@endif
