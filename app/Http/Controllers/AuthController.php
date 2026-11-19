<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required'    => 'Email wajib diisi.',
            'email.email'       => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user && ! $user->is_active) {
            ActivityLogger::log('login.failed', "Percobaan login pada akun dinonaktifkan: {$credentials['email']}", null, $user->pop_id, $user->id);
            return back()->withErrors(['email' => 'Akun Anda dinonaktifkan. Silakan hubungi Super Admin.'])->onlyInput('email');
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            if (! $user->is_active) {
                Auth::logout();
                ActivityLogger::log('login.failed', "Percobaan login pada akun dinonaktifkan: {$credentials['email']}", null, $user->pop_id, $user->id);
                return back()->withErrors(['email' => 'Akun Anda dinonaktifkan. Silakan hubungi Super Admin.'])->onlyInput('email');
            }

            $request->session()->regenerate();
            ActivityLogger::log('login.success', "Login berhasil ({$user->role->label()})", $user, $user->pop_id, $user->id);

            $landingRoute = \App\Services\MenuService::getLandingRoute($user->role);

            return redirect()->intended(route($landingRoute));
        }

        ActivityLogger::log('login.failed', "Gagal login untuk email: {$credentials['email']}");

        return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            ActivityLogger::log('logout', "Logout dari sistem ({$user->email})", $user, $user->pop_id, $user->id);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
