<?php

use App\Http\Controllers\AdminApplicationController;
use App\Http\Controllers\AdminComplaintController;
use App\Http\Controllers\AdminComplianceController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminMessageController;
use App\Http\Controllers\AdminReportingController;
use App\Http\Controllers\AdminSellerApplicationController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware([
        'auth',
        'admin',
    ])
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | ADMIN DASHBOARD
        |--------------------------------------------------------------------------
        */

        Route::get('/', [
            AdminDashboardController::class,
            'index',
        ])->name('admin.dashboard');


        /*
        |--------------------------------------------------------------------------
        | USERS
        |--------------------------------------------------------------------------
        */

        Route::get('/users', [
            AdminUserController::class,
            'index',
        ])->name('admin.users');

        Route::get('/users/{user}', [
            AdminUserController::class,
            'show',
        ])->name('admin.users.show');

        Route::patch('/users/{user}/status', [
            AdminUserController::class,
            'updateStatus',
        ])->name('admin.users.status');


        /*
        |--------------------------------------------------------------------------
        | APPLICATIONS
        |--------------------------------------------------------------------------
        */

        Route::get('/applications', [
            AdminApplicationController::class,
            'index',
        ])->name('admin.applications');

        Route::get('/applications/{user}', [
            AdminApplicationController::class,
            'show',
        ])->name('admin.applications.show');

        Route::patch('/applications/{user}/approve', [
            AdminApplicationController::class,
            'approve',
        ])->name('admin.applications.approve');

        Route::patch('/applications/{user}/reject', [
            AdminApplicationController::class,
            'reject',
        ])->name('admin.applications.reject');


        /*
        |--------------------------------------------------------------------------
        | SELLER APPLICATIONS
        |--------------------------------------------------------------------------
        */

        Route::get('/sellers/applications', [
            AdminSellerApplicationController::class,
            'index',
        ])->name('admin.seller-applications');

        Route::patch('/sellers/applications/{user}/approve', [
            AdminSellerApplicationController::class,
            'approve',
        ])->name('admin.seller-applications.approve');

        Route::patch('/sellers/applications/{user}/reject', [
            AdminSellerApplicationController::class,
            'reject',
        ])->name('admin.seller-applications.reject');


        /*
        |--------------------------------------------------------------------------
        | SELLER COMPLIANCE
        |--------------------------------------------------------------------------
        */

        Route::get('/compliance', [
            AdminComplianceController::class,
            'index',
        ])->name('admin.compliance');

        Route::patch('/compliance/{product}/status', [
            AdminComplianceController::class,
            'updateStatus',
        ])->name('admin.compliance.status');

        Route::patch('/compliance/{product}/unsuspend', [
            AdminComplianceController::class,
            'unsuspend',
        ])->name('admin.compliance.unsuspend');


        /*
        |--------------------------------------------------------------------------
        | COMPLAINTS
        |--------------------------------------------------------------------------
        */

        Route::get('/complaints', [
            AdminComplaintController::class,
            'index',
        ])->name('admin.complaints');

        Route::get('/complaints/{complaint}', [
            AdminComplaintController::class,
            'show',
        ])->name('admin.complaints.show');

        Route::patch('/complaints/{complaint}/resolve', [
            AdminComplaintController::class,
            'resolve',
        ])->name('admin.complaints.resolve');


        /*
        |--------------------------------------------------------------------------
        | REPORTS
        |--------------------------------------------------------------------------
        */

        Route::get('/reports/sales-summary', [
            AdminReportingController::class,
            'salesSummary',
        ])->name('admin.reports.sales-summary');

        Route::get('/reports/commission', [
            AdminReportingController::class,
            'commissionReport',
        ])->name('admin.reports.commission');


        /*
        |--------------------------------------------------------------------------
        | ADMIN SETTINGS
        |--------------------------------------------------------------------------
        */

        Route::get('/settings', [
            AdminSettingsController::class,
            'settings',
        ])->name('admin.settings');

        Route::get('/settings/announcements', [
            AdminSettingsController::class,
            'announcements',
        ])->name('admin.announcements');

        Route::post('/settings/announcements', [
            AdminSettingsController::class,
            'storeAnnouncement',
        ])->name('admin.announcements.store');

        Route::get('/settings/policies', [
            AdminSettingsController::class,
            'policies',
        ])->name('admin.policies');

        Route::post('/settings/policies', [
            AdminSettingsController::class,
            'storePolicy',
        ])->name('admin.policies.store');

        Route::patch('/settings/policies/{policy}', [
            AdminSettingsController::class,
            'updatePolicy',
        ])->name('admin.policies.update');

        Route::post('/settings/platform', [
            AdminSettingsController::class,
            'storePlatformSetting',
        ])->name('admin.platform-settings.store');

        Route::patch('/settings/platform/{platformSetting}', [
            AdminSettingsController::class,
            'updatePlatformSetting',
        ])->name('admin.platform-settings.update');

        Route::delete('/settings/platform/{platformSetting}', [
            AdminSettingsController::class,
            'destroyPlatformSetting',
        ])->name('admin.platform-settings.destroy');

        Route::post('/settings/why-alona', [
            AdminSettingsController::class,
            'updateWhyAlona',
        ])->name('admin.settings.why-alona.update');

        Route::get('/settings/faqs', [
            AdminSettingsController::class,
            'faqs',
        ])->name('admin.faqs');

        Route::post('/settings/faqs', [
            AdminSettingsController::class,
            'storeFaq',
        ])->name('admin.faqs.store');

        Route::patch('/settings/faqs/{faq}', [
            AdminSettingsController::class,
            'updateFaq',
        ])->name('admin.faqs.update');

        Route::delete('/settings/faqs/{faq}', [
            AdminSettingsController::class,
            'destroyFaq',
        ])->name('admin.faqs.destroy');


        /*
        |--------------------------------------------------------------------------
        | MESSAGES
        |--------------------------------------------------------------------------
        */

        Route::get('/messages', [
            AdminMessageController::class,
            'index',
        ])->name('admin.messages');

        Route::get('/messages/{conversation}', [
            AdminMessageController::class,
            'show',
        ])->name('admin.messages.show');

        Route::post('/messages/{conversation}', [
            AdminMessageController::class,
            'store',
        ])->name('admin.messages.store');

    });

