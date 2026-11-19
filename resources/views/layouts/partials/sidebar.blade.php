<nav id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <!-- <i class="bi bi-wifi text-white"></i> -->
             <img src="{{ asset('images/logo-jcn.png') }}" alt="Logo JCN">
        </div>
        <span class="brand-name">NetBill</span>
    </div>

    @foreach(($menuSections ?? []) as $section)
        <div class="nav-section-label {{ !$loop->first ? 'mt-3' : '' }}">{{ $section['section'] }}</div>

        <ul class="sidebar-nav nav flex-column">
            @foreach($section['items'] as $item)
                <li class="nav-item">
                    <a href="{{ $item['url'] }}"
                       class="nav-link d-flex justify-content-between align-items-center {{ $item['is_active'] ? 'active' : '' }}">
                        <div>
                            <i class="bi {{ $item['icon'] }}"></i>
                            {{ $item['label'] }}
                        </div>
                        @if(($item['badge_count'] ?? 0) > 0)
                            <span class="badge bg-warning text-dark">{{ $item['badge_count'] }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    @endforeach

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
