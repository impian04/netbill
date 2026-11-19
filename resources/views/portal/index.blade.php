<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Staf — NetBill</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="card shadow-sm border-0" style="max-width: 480px; width: 100%;">
        <div class="card-body p-4 text-center">
            <div class="mb-3">
                <span class="badge bg-{{ auth()->user()->role->badgeColor() }} px-3 py-2 ">
                    {{ auth()->user()->role->label() }}
                </span>
                @if(auth()->user()->pop)
                    <span class="badge bg-secondary px-2 py-2 ms-1">
                        POP {{ auth()->user()->pop->code }}
                    </span>
                @endif
            </div>

            <h1 class="h4 fw-bold mb-2">Halo, {{ auth()->user()->name }}</h1>

            @if(auth()->user()->role === \App\Enums\Role::MARKETING)
                <p class="text-muted mb-4">
                    Kelola pendaftaran calon pelanggan baru Anda melalui menu pendaftaran.
                </p>
                <div class="mb-4">
                    <x-btn variant="primary" href="{{ route('marketing.applications.index') }}"  >
                        Buka Pendaftaran Saya
                    </x-btn>
                </div>
            @else
                <p class="text-muted mb-4">
                    Belum ada menu untuk peran Anda.
                    <br><span class="small">Fitur operasional khusus peran ini sedang disiapkan. Silakan hubungi Super Admin jika membutuhkan bantuan.</span>
                </p>
            @endif

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-btn variant="danger" type="submit"  >
                    Keluar dari Akun
                </x-btn>
            </form>
        </div>
    </div>
</body>
</html>

