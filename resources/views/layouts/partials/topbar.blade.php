<header id="topbar">
    <button id="sidebar-toggle" class="btn btn-sm btn-light me-2" onclick="openSidebar()" aria-label="Buka sidebar">
        </button>

    <span class="topbar-title">@yield('title', 'Dashboard')</span>

    <div class="topbar-user d-flex align-items-center gap-2">
        <div class="d-none d-md-flex flex-column text-end me-1" style="line-height:1.2;">
            <span class="fw-semibold small">{{ auth()->user()->name }}</span>
            <div class="d-flex align-items-center gap-1 justify-content-end mt-1">
                <span class="badge bg-{{ auth()->user()->role->badgeColor() }}">
                    {{ auth()->user()->role->label() }}
                </span>
                <span class="badge bg-secondary">
                    {{ auth()->user()->pop ? 'POP ' . auth()->user()->pop->code : 'Semua POP' }}
                </span>
            </div>
        </div>
        <div class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
    </div>
</header>
