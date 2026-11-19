<?php

namespace App\Providers;

use App\Enums\SurveyResult;
use App\Enums\SurveyStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Survey;
use App\Models\WorkOrder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
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

        // View Composers untuk partial offcanvas form agar aman di semua halaman (Fase 17f)
        View::composer('customers.partials.offcanvas', \App\View\Composers\CustomerOffcanvasComposer::class);
        View::composer('staff.partials.offcanvas', \App\View\Composers\StaffOffcanvasComposer::class);
        View::composer('invoices.partials.offcanvas', \App\View\Composers\InvoiceOffcanvasComposer::class);

        // Sidebar menu & badge counts via MenuService (Single Source of Truth)
        View::composer('layouts.partials.sidebar', function ($view) {
            $user = auth()->user();
            if (! $user) {
                return;
            }

            $menuSections = \App\Services\MenuService::getMenuSections($user);
            $badges = \App\Services\MenuService::calculateBadges($user);

            $view->with([
                'menuSections'                      => $menuSections,
                'sidebarUnassignedSurveysCount'     => $badges['unassigned_surveys'],
                'sidebarPendingWorkOrdersCount'     => $badges['pending_work_orders'],
                'sidebarSurveyQueueCount'           => $badges['survey_queue'],
                'sidebarTechnicianActiveTasksCount' => $badges['technician_active_tasks'],
            ]);
        });
    }
}
