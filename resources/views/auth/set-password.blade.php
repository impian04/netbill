<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setel Password — NetBill</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 60%, #0f172a 100%);
            display: flex; align-items: center; justify-content: center;
        }
        .login-card {
            width: 100%; max-width: 440px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,.35);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #0f172a, #1e3a8a);
            padding: 2rem;
            text-align: center;
        }
        /* .login-header .brand-icon {
            width: 56px; height: 56px;
            background: rgba(59,130,246,.25);
            border: 2px solid rgba(59,130,246,.4);
            border-radius: 14px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.6rem; color: #93c5fd;
            margin-bottom: .75rem;
        } */
        .login-header .brand-icon {
            width: 56px;
            height: 56px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: .75rem;
        }
        .login-header .brand-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .login-header h1 {
            font-size: 1.4rem; font-weight: 700; color: #f8fafc; margin: 0;
        }
        .login-header p { color: #94a3b8; font-size: .85rem; margin: .25rem 0 0; }
        .login-body { padding: 2rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="brand-icon">
                <!-- -->
                <img src="{{ asset('images/logo-jcn.png') }}" alt="Logo JCN">
            </div>
            <h1>Setel Password</h1>
            <p>Atur password baru untuk akun staf Anda</p>
        </div>
        <div class="login-body">
            @if ($errors->any())
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('password.set.post') }}" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Email Akun</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $email) }}" required readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Password Baru</label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                           placeholder="Minimal 10 karakter (huruf & angka)" required autofocus>
                    <div class="form-text small">
                        Wajib minimal 10 karakter, mengandung kombinasi huruf dan angka.
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-semibold text-secondary">Konfirmasi Password</label>
                    <input type="password" name="password_confirmation" class="form-control"
                           placeholder="Ulangi password baru" required>
                </div>

                <x-btn type="submit" variant="primary"    >
                    Simpan Password &amp; Lanjutkan
                </x-btn>
            </form>
        </div>
    </div>
</body>
</html>

