@props([
    'id',
    'title' => 'Form Data',
    'titleId' => null,
    'subtitle' => null,
    'subtitleId' => null,
    'formId' => null,
    'method' => 'POST',
    'action' => '',
    'submitId' => null,
    'submitText' => 'Simpan',
    'submitIcon' => 'bi-save',
    'cancelText' => 'Batal',
    'hideFooter' => false,
])

@php
    $actualTitleId = $titleId ?? ($id . 'Title');
    $hasForm = !empty($formId);
@endphp

<div {{ $attributes->merge(['class' => 'offcanvas offcanvas-end']) }}
     tabindex="-1"
     id="{{ $id }}"
     data-bs-scroll="true"
     data-bs-backdrop="true"
     aria-labelledby="{{ $actualTitleId }}">
    <div class="offcanvas-header">
        @if(isset($header))
            {{ $header }}
        @else
            <div>
                <h5 class="offcanvas-title" id="{{ $actualTitleId }}">{{ $title }}</h5>
                @if($subtitle || $subtitleId)
                    <div class="small text-muted" @if($subtitleId) id="{{ $subtitleId }}" @endif>{{ $subtitle }}</div>
                @endif
            </div>
        @endif
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>

    @if($hasForm)
        <form id="{{ $formId }}" novalidate @if($action) action="{{ $action }}" @endif>
            @csrf
            <input type="hidden" name="_method" value="{{ $method }}">
    @endif

        <div class="offcanvas-body">
            {{ $slot }}
        </div>

        @unless($hideFooter)
            <div class="offcanvas-footer">
                @if(isset($footer))
                    {{ $footer }}
                @else
                    <x-btn variant="neutral" data-bs-dismiss="offcanvas">{{ $cancelText }}</x-btn>
                    <x-btn type="submit" variant="primary" class="px-3" :id="$submitId">{{ $submitText }}</x-btn>
                @endif
            </div>
        @endunless

    @if($hasForm)
        </form>
    @endif
</div>

