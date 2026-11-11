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

    // Customer routes (offcanvas: create & edit terpisah dihapus)
    Route::get('customers/suggest-code', [CustomerController::class, 'suggestCode'])
        ->name('customers.suggest-code');
    Route::resource('customers', CustomerController::class)->except(['create', 'edit']);
    Route::post('customers/{customer}/activation', [CustomerController::class, 'generateActivation'])
        ->name('customers.activation.generate');

    // Invoice routes (offcanvas: create & edit terpisah dihapus)
    Route::resource('invoices', InvoiceController::class)->except(['create', 'edit']);
    Route::post('customers/{customer}/invoices', [InvoiceController::class, 'store'])
        ->name('customers.invoices.store');

    // Import
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('imports/template', [ImportController::class, 'template'])->name('imports.template');
    Route::get('imports/template-xlsx', [ImportController::class, 'templateXlsx'])->name('imports.template.xlsx');
    Route::get('imports/template-csv', [ImportController::class, 'templateCsv'])->name('imports.template.csv');
    Route::get('imports/upload', fn () => redirect()->route('imports.index'));
    Route::post('imports/upload', [ImportController::class, 'upload'])
        ->name('imports.upload')->middleware('throttle:10,1');
    Route::get('imports/preview/{token}', [ImportController::class, 'preview'])
        ->name('imports.preview');
    Route::get('imports/confirm', fn () => redirect()->route('imports.index'));
    Route::post('imports/confirm', [ImportController::class, 'confirm'])
        ->name('imports.confirm')->middleware('throttle:10,1');
    Route::get('imports/{importBatch}', [ImportController::class, 'show'])
        ->name('imports.show')
        ->whereNumber('importBatch');

    // Export
    Route::get('export/customers', [ExportController::class, 'customers'])
        ->name('export.customers')->middleware('throttle:20,1');
    Route::get('export/invoices', [ExportController::class, 'invoices'])
        ->name('export.invoices')->middleware('throttle:20,1');

    // Announcements (offcanvas: create & edit terpisah dihapus)
    Route::resource('announcements', AnnouncementController::class)->except(['create', 'edit']);

    // Settings
    Route::get('settings/noc', [SettingsController::class, 'edit'])->name('settings.noc');
    Route::put('settings/noc', [SettingsController::class, 'update'])->name('settings.noc.update');
});
