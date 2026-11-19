<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — NetBill Admin</title>
    <meta name="description" content="Login admin NetBill.">
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
            width: 100%; max-width: 420px;
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
        .form-floating .form-control:focus ~ label { color: #3b82f6; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="brand-icon">
                <!-- -->
                <img src="{{ asset('images/logo-jcn.png') }}" alt="Logo JCN">
            </div>
            <h1>NetBill</h1>
            <p>Admin Panel — Manajemen Tagihan</p>
        </div>
        <div class="login-body">
            @if ($errors->any())
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" novalidate>
                @csrf

                <div class="form-floating mb-3">
                    <input type="email" id="email" name="email"
                           class="form-control @error('email') is-invalid @enderror"
                           placeholder="admin@netbill.test"
                           value="{{ old('email') }}"
                           autocomplete="email" required>
                    <label for="email">Email</label>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-floating mb-3">
                    <input type="password" id="password" name="password"
                           class="form-control @error('password') is-invalid @enderror"
                           placeholder="Password"
                           autocomplete="current-password" required>
                    <label for="password">Password</label>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label text-muted" for="remember">
                        Ingat saya
                    </label>
                </div>

                <x-btn type="submit" variant="primary" id="btn-login" class="col-12"
                         >
                    Masuk
                </x-btn>
            </form>

            <p class="text-center text-muted mt-3 mb-0">
                NetBill &copy; {{ date('Y') }}
            </p>
        </div>
    </div>
</body>
</html>
