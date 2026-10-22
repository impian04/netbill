<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Web: login rate limiter (5/min per IP)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function () {
                    return redirect()->route('login')
                        ->withErrors(['email' => 'Terlalu banyak percobaan login. Coba lagi dalam 1 menit.']);
                });
        });

        // API: auth endpoints (activate + login) — 5/min per customer_code + IP combo, 30/min per IP
        RateLimiter::for('api_auth', function (Request $request) {
            $code = strtoupper(trim((string) $request->input('customer_code', '')));
            $ip   = $request->ip();
            $comboKey = $code ? "{$ip}|{$code}" : $ip;

            $responseCallback = function () {
                return response()->json(['message' => 'Terlalu banyak percobaan. Coba lagi dalam 1 menit.'], 429);
            };

            return [
                Limit::perMinute(5)->by('combo:' . $comboKey)->response($responseCallback),
                Limit::perMinute(30)->by('ip:' . $ip)->response($responseCallback),
            ];
        });

        // API: authenticated endpoints — 60/min per token
        RateLimiter::for('api_customer', function (Request $request) {
            $key = $request->bearerToken() ?? $request->ip();
            return Limit::perMinute(60)->by($key)->response(function () {
                return response()->json(['message' => 'Terlalu banyak permintaan. Coba lagi sebentar lagi.'], 429);
            });
        });
    }
}
