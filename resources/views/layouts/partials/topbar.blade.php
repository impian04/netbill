<header id="topbar">
    <button id="sidebar-toggle" class="btn btn-sm btn-light me-2" onclick="openSidebar()" aria-label="Buka sidebar">
        <i class="bi bi-list fs-5"></i>
    </button>

    <span class="topbar-title">@yield('title', 'Dashboard')</span>

    <div class="topbar-user">
        <div class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
        <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
    </div>
</header>
