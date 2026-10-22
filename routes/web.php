<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// ── Auth (guest only) ─────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->name('login.post')
        ->middleware('throttle:login');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ── Protected routes ──────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Customer CRUD + activation
    Route::resource('customers', CustomerController::class);
    Route::post('customers/{customer}/activation', [CustomerController::class, 'generateActivation'])
        ->name('customers.activation.generate');

    // Invoice CRUD (shallow nesting)
    Route::get('customers/{customer}/invoices/create', [InvoiceController::class, 'create'])
        ->name('customers.invoices.create');
    Route::post('customers/{customer}/invoices', [InvoiceController::class, 'store'])
        ->name('customers.invoices.store');
    Route::get('invoices/{invoice}/edit', [InvoiceController::class, 'edit'])
        ->name('invoices.edit');
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])
        ->name('invoices.update');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])
        ->name('invoices.destroy');

    // Import
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('imports/template', [ImportController::class, 'template'])->name('imports.template');
    Route::post('imports/upload', [ImportController::class, 'upload'])
        ->name('imports.upload')->middleware('throttle:10,1');
    Route::post('imports/confirm', [ImportController::class, 'confirm'])
        ->name('imports.confirm')->middleware('throttle:10,1');
    Route::get('imports/{importBatch}', [ImportController::class, 'show'])->name('imports.show');

    // Export
    Route::get('export/customers', [ExportController::class, 'customers'])
        ->name('export.customers')->middleware('throttle:20,1');
    Route::get('export/invoices', [ExportController::class, 'invoices'])
        ->name('export.invoices')->middleware('throttle:20,1');

    // Announcements
    Route::resource('announcements', AnnouncementController::class)->except(['show']);

    // Settings
    Route::get('settings/noc', [SettingsController::class, 'edit'])->name('settings.noc');
    Route::put('settings/noc', [SettingsController::class, 'update'])->name('settings.noc.update');
});
