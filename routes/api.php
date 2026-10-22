<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SupportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1
|--------------------------------------------------------------------------
| All responses are JSON (enforced by bootstrap/app.php & Api middleware).
| Rate limiters defined in AppServiceProvider.
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Auth (unauthenticated) ────────────────────────────────────────
    Route::prefix('auth')->middleware('throttle:api_auth')->group(function () {
        Route::post('activate', [AuthController::class, 'activate'])->name('api.auth.activate');
        Route::post('login',    [AuthController::class, 'login'])->name('api.auth.login');
    });

    // ── Protected (Sanctum token, ability: customer) ─────────────────
    Route::middleware(['auth:sanctum', 'ability:customer', 'customer.active', 'throttle:api_customer'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

        Route::get('/me',            [ProfileController::class, 'show'])->name('api.me');
        Route::post('/me/password',  [ProfileController::class, 'changePassword'])->name('api.me.password');

        Route::get('/home', [HomeController::class, 'index'])->name('api.home');

        Route::get('/invoices',        [InvoiceController::class, 'index'])->name('api.invoices.index');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('api.invoices.show');

        Route::get('/support-contacts', [SupportController::class, 'index'])->name('api.support');
    });

});
