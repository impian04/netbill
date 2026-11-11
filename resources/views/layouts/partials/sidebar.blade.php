<nav id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="bi bi-wifi text-white"></i>
        </div>
        <span class="brand-name">NetBill</span>
    </div>

    <div class="nav-section-label">Menu</div>

    <ul class="sidebar-nav nav flex-column">
        <li class="nav-item">
            <a href="{{ route('dashboard') }}"
               class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i>
                Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ \Illuminate\Support\Facades\Route::has('customers.index') ? route('customers.index') : '#' }}"
               class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
                <i class="bi bi-people"></i>
                Pelanggan
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ \Illuminate\Support\Facades\Route::has('invoices.index') ? route('invoices.index') : '#' }}"
               class="nav-link {{ request()->routeIs('invoices.*') ? 'active' : '' }}">
                <i class="bi bi-receipt"></i>
                Tagihan
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('imports.index') }}"
               class="nav-link {{ request()->routeIs('imports.*') ? 'active' : '' }}">
                <i class="bi bi-upload"></i>
                Import Data
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('announcements.index') }}"
               class="nav-link {{ request()->routeIs('announcements.*') ? 'active' : '' }}">
                <i class="bi bi-megaphone"></i>
                Pengumuman
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('settings.noc') }}"
               class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <i class="bi bi-telephone"></i>
                Kontak NOC
            </a>
        </li>
    </ul>

    <div class="mt-auto p-3" style="border-top:1px solid rgba(255,255,255,.06);">
        <form method="POST" action="{{ route('logout') }}" id="sidebar-logout-form">
            @csrf
            <button type="button" class="btn btn-sm w-100 d-flex align-items-center gap-2"
                    id="btn-sidebar-logout"
                    style="background:rgba(239,68,68,.15);color:#fca5a5;border:none;">
                <i class="bi bi-box-arrow-left"></i>
                Keluar
            </button>
        </form>
    </div>

    <script>
        document.getElementById('btn-sidebar-logout')?.addEventListener('click', async function () {
            if (typeof window.confirmAction === 'function') {
                const result = await window.confirmAction({
                    title: 'Keluar dari Sistem?',
                    text: 'Anda akan mengakhiri sesi administrasi ini.',
                    confirmButtonText: 'Ya, Keluar',
                    cancelButtonText: 'Batal',
                    confirmButtonClass: 'btn btn-danger',
                    icon: 'question'
                });
                if (result.isConfirmed) {
                    document.getElementById('sidebar-logout-form').submit();
                }
            } else {
                document.getElementById('sidebar-logout-form').submit();
            }
        });
    </script>
</nav>
