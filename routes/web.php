<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerApplicationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MarketingApplicationController;
use App\Http\Controllers\MarketingFeeController;
use App\Http\Controllers\MarketingProfileController;
use App\Http\Controllers\MasterImportController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\NocCustomerController;
use App\Http\Controllers\OdcController;
use App\Http\Controllers\OdpController;
use App\Http\Controllers\PasswordSetController;
use App\Http\Controllers\PopController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\SurveyorQueueController;
use App\Http\Controllers\SurveyorSurveyController;
use App\Http\Controllers\TechnicianTaskController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\WorkOrderPhotoController;
use Illuminate\Support\Facades\Route;

// ── Auth (guest only) ─────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->name('login.post')
        ->middleware('throttle:login');

    // Set Password (Public with token)
    Route::get('/set-password/{token}', [PasswordSetController::class, 'show'])->name('password.set');
    Route::post('/set-password', [PasswordSetController::class, 'store'])
        ->name('password.set.post')
        ->middleware('throttle:10,1');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ── Portal Staf (Non-admin roles) ────────────────────────────────
Route::middleware('auth')->get('/portal', function () {
    $user = auth()->user();
    if ($user && \App\Services\MenuService::shouldRedirectFromPortal($user->role)) {
        return redirect()->to(\App\Services\MenuService::getLandingUrl($user->role));
    }

    return view('portal.index');
})->name('portal');

// ── Protected File Routes (auth with POP/role isolation in controller) ──
Route::middleware('auth')->group(function () {
    Route::get('files/ktp/customer/{customer}', [CustomerController::class, 'streamKtp'])
        ->name('files.ktp.customer');
    Route::get('files/ktp/application/{application}', [CustomerApplicationController::class, 'streamKtp'])
        ->name('files.ktp.application');
    Route::get('files/work-orders/{photo}', [WorkOrderPhotoController::class, 'stream'])
        ->name('files.work-orders.photo');
});

// ── Protected Marketing Routes ─────────────────────────────────────
Route::middleware(['auth', 'role:marketing'])->prefix('my')->name('marketing.')->group(function () {
    Route::get('applications', [MarketingApplicationController::class, 'index'])->name('applications.index');
    Route::post('applications', [MarketingApplicationController::class, 'store'])->name('applications.store');
    Route::get('applications/{application}', [MarketingApplicationController::class, 'show'])->name('applications.show');
    Route::put('applications/{application}', [MarketingApplicationController::class, 'update'])->name('applications.update');
});

// ── Protected Surveyor Routes ──────────────────────────────────────
Route::middleware(['auth', 'role:surveyor'])->name('surveyor.')->group(function () {
    Route::get('survey-queue', [SurveyorQueueController::class, 'index'])->name('queue.index');
    Route::prefix('my')->group(function () {
        Route::get('surveys', [SurveyorSurveyController::class, 'index'])->name('surveys.index');
        Route::post('surveys', [SurveyorSurveyController::class, 'store'])->name('surveys.store');
        Route::get('surveys/{survey}', [SurveyorSurveyController::class, 'show'])->name('surveys.show');
        Route::put('surveys/{survey}', [SurveyorSurveyController::class, 'update'])->name('surveys.update');
    });
});

// ── Protected Technician Routes ────────────────────────────────────
Route::middleware(['auth', 'role:technician'])->prefix('my')->name('technician.')->group(function () {
    Route::get('tasks', [TechnicianTaskController::class, 'index'])->name('tasks.index');
    Route::get('tasks/{workOrder}', [TechnicianTaskController::class, 'show'])->name('tasks.show');
    Route::post('tasks/{workOrder}/start', [TechnicianTaskController::class, 'start'])->name('tasks.start');
    Route::post('tasks/{workOrder}/photos', [TechnicianTaskController::class, 'uploadPhoto'])
        ->name('tasks.photos.upload')
        ->middleware('throttle:30,1');
    Route::delete('tasks/{workOrder}/photos/{photo}', [TechnicianTaskController::class, 'deletePhoto'])->name('tasks.photos.delete');
    Route::post('tasks/{workOrder}/submit', [TechnicianTaskController::class, 'submit'])->name('tasks.submit');
});

// ── Tiket (super_admin, admin, noc) ────────────────────────────────
Route::middleware(['auth', 'role:super_admin,admin,noc'])->group(function () {
    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/search-customers', [TicketController::class, 'searchCustomers'])->name('tickets.search-customers');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::put('tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::post('tickets/{ticket}/status', [TicketController::class, 'changeStatus'])->name('tickets.status');
    Route::post('tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
    Route::post('tickets/{ticket}/notes', [TicketController::class, 'addNote'])->name('tickets.notes');
    Route::post('tickets/{ticket}/request-field-visit', [TicketController::class, 'requestFieldVisit'])->name('tickets.request-field-visit');

    // Ticket export — NOC, admin, super_admin
    Route::get('export/tickets', [ExportController::class, 'tickets'])
        ->name('export.tickets')->middleware('throttle:10,1');
});

// ── Cari Pelanggan NOC (super_admin, noc) ──────────────────────────
Route::middleware(['auth', 'role:super_admin,noc'])->group(function () {
    Route::get('customer-lookup', [NocCustomerController::class, 'index'])->name('noc.customers.index');
    Route::get('customer-lookup/search', [NocCustomerController::class, 'search'])
        ->name('noc.customers.search')
        ->middleware('throttle:30,1');
    Route::get('customer-lookup/{customer}', [NocCustomerController::class, 'show'])->name('noc.customers.show');
});

// ── Protected Admin Routes (super_admin & admin) ──────────────────
Route::middleware(['auth', 'role:super_admin,admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Customer applications verification
    Route::get('customer-applications', [CustomerApplicationController::class, 'index'])
        ->name('customer-applications.index');
    Route::get('customer-applications/{application}', [CustomerApplicationController::class, 'show'])
        ->name('customer-applications.show');
    Route::post('customer-applications/{application}/approve', [CustomerApplicationController::class, 'approve'])
        ->name('customer-applications.approve');
    Route::post('customer-applications/{application}/request-revision', [CustomerApplicationController::class, 'requestRevision'])
        ->name('customer-applications.request-revision');
    Route::post('customer-applications/{application}/reject', [CustomerApplicationController::class, 'reject'])
        ->name('customer-applications.reject');

    // Customer routes
    Route::get('customers/suggest-code', [CustomerController::class, 'suggestCode'])
        ->name('customers.suggest-code');
    Route::resource('customers', CustomerController::class)->except(['create', 'edit']);
    Route::post('customers/{customer}/activation', [CustomerController::class, 'generateActivation'])
        ->name('customers.activation.generate');

    // Invoice routes
    Route::resource('invoices', InvoiceController::class)->except(['create', 'edit']);
    Route::post('customers/{customer}/invoices', [InvoiceController::class, 'store'])
        ->name('customers.invoices.store');

    // Survey routes (Admin & Super Admin)
    Route::get('surveys', [SurveyController::class, 'index'])->name('surveys.index');
    Route::get('surveys/{survey}', [SurveyController::class, 'show'])->name('surveys.show');
    Route::post('surveys/{survey}/assign-technician', [WorkOrderController::class, 'assignFromSurvey'])
        ->name('surveys.assign-technician');
    Route::post('surveys/{survey}/request-resurvey', [SurveyController::class, 'requestResurvey'])
        ->name('surveys.request-resurvey');
    Route::post('surveys/{survey}/cancel-customer', [SurveyController::class, 'cancelCustomer'])
        ->name('surveys.cancel-customer');

    // Work Order routes (Penugasan Teknisi)
    Route::get('work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');
    Route::get('work-orders/{workOrder}', [WorkOrderController::class, 'show'])->name('work-orders.show');
    Route::post('work-orders/{workOrder}/reassign', [WorkOrderController::class, 'reassign'])
        ->name('work-orders.reassign');
    Route::post('work-orders/{workOrder}/assign-repair', [WorkOrderController::class, 'assignRepair'])
        ->name('work-orders.assign-repair');
    Route::post('work-orders/{workOrder}/reject-repair', [WorkOrderController::class, 'rejectRepair'])
        ->name('work-orders.reject-repair');
    Route::post('work-orders/{workOrder}/verify', [WorkOrderController::class, 'verify'])
        ->name('work-orders.verify');
    Route::post('work-orders/{workOrder}/request-revision', [WorkOrderController::class, 'requestRevision'])
        ->name('work-orders.request-revision');
    Route::post('work-orders/{workOrder}/cancel', [WorkOrderController::class, 'cancel'])
        ->name('work-orders.cancel');

    // Master Data ODC, ODP, Barang (Materials)
    Route::resource('odcs', OdcController::class)->except(['create', 'edit']);
    Route::resource('odps', OdpController::class)->except(['create', 'edit']);
    Route::resource('materials', MaterialController::class)->except(['create', 'edit']);

    // Master Data Import
    Route::get('master-import/{type}/template', [MasterImportController::class, 'template'])->name('master-import.template');
    Route::post('master-import/{type}/upload', [MasterImportController::class, 'upload'])->name('master-import.upload')->middleware('throttle:10,1');
    Route::get('master-import/{type}/preview', [MasterImportController::class, 'preview'])->name('master-import.preview');
    Route::post('master-import/{type}/confirm', [MasterImportController::class, 'confirm'])->name('master-import.confirm')->middleware('throttle:10,1');

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

    // Export (admin & super_admin only)
    Route::get('export/customers', [ExportController::class, 'customers'])
        ->name('export.customers')->middleware('throttle:20,1');
    Route::get('export/invoices', [ExportController::class, 'invoices'])
        ->name('export.invoices')->middleware('throttle:20,1');
    Route::get('export/marketing-fees', [ExportController::class, 'marketingFees'])
        ->name('export.marketing-fees')->middleware('throttle:20,1');
    Route::get('export/installation-report', [ExportController::class, 'installationReport'])
        ->name('export.installation-report')->middleware('throttle:10,1');

    // Marketing Profiles (Payment info)
    Route::get('marketing-profiles', [MarketingProfileController::class, 'index'])
        ->name('marketing-profiles.index');
    Route::get('marketing-profiles/{user}', [MarketingProfileController::class, 'show'])
        ->name('marketing-profiles.show');
    Route::put('marketing-profiles/{user}', [MarketingProfileController::class, 'update'])
        ->name('marketing-profiles.update');

    // Marketing Fees
    Route::get('marketing-fees', [MarketingFeeController::class, 'index'])
        ->name('marketing-fees.index');
    Route::put('marketing-fees/{fee}/amount', [MarketingFeeController::class, 'updateAmount'])
        ->name('marketing-fees.update-amount');
    Route::post('marketing-fees/bulk-pay', [MarketingFeeController::class, 'bulkPay'])
        ->name('marketing-fees.bulk-pay');
    Route::post('marketing-fees/{fee}/unpay', [MarketingFeeController::class, 'unpay'])
        ->name('marketing-fees.unpay');

    // ── Super Admin Only ──────────────────────────────────────────
    Route::middleware('role:super_admin')->group(function () {
        // Announcements
        Route::resource('announcements', AnnouncementController::class)->except(['create', 'edit']);

        // Settings Kontak NOC & Fee Marketing
        Route::get('settings/noc', [SettingsController::class, 'edit'])->name('settings.noc');
        Route::put('settings/noc', [SettingsController::class, 'update'])->name('settings.noc.update');
        Route::get('settings/fee', [SettingsController::class, 'editFee'])->name('settings.fee');
        Route::put('settings/fee', [SettingsController::class, 'updateFee'])->name('settings.fee.update');

        // POP Management
        Route::resource('pops', PopController::class)->except(['create', 'edit']);

        // Staff Management
        Route::resource('staff', StaffController::class)->except(['create', 'edit']);
        Route::post('staff/{staff}/reset-access', [StaffController::class, 'resetAccess'])
            ->name('staff.reset-access');

        // Activity Logs
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    });
});
