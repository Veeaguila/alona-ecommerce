<?php

use App\Http\Controllers\AdminApplicationController;
use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\AdminDeliveryZoneController;
use App\Http\Controllers\AdminVoucherController;
use App\Http\Controllers\AdminComplaintController;
use App\Http\Controllers\AdminComplianceController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminMessageController;
use App\Http\Controllers\AdminPayoutStatusController;
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
        Route::get('/delivery-finance', [\App\Http\Controllers\DeliveryFinanceController::class, 'index']);
        Route::post('/delivery-charges/{id}/record-collection', [\App\Http\Controllers\DeliveryFinanceController::class, 'collect']);
        Route::post('/rider-earnings/{id}/record-payout', [\App\Http\Controllers\DeliveryFinanceController::class, 'payout']);

        /*
        |--------------------------------------------------------------------------
        | ADMIN DASHBOARD
        |--------------------------------------------------------------------------
        */

        Route::get('/', [
            AdminDashboardController::class,
            'index',
        ])->name('admin.dashboard');
        Route::get('/dashboard', [
            AdminDashboardController::class,
            'index',
        ]);


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

        Route::post('/compliance/{product}/verify-ai', [
            AdminComplianceController::class,
            'retryAiVerification',
        ])->name('admin.compliance.verify-ai');


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

        Route::get('/reports/sales-summary/export', [
            AdminReportingController::class,
            'exportSalesSummary',
        ])->name('admin.reports.sales-summary.export');

        Route::get('/reports/commission', [
            AdminReportingController::class,
            'commissionReport',
        ])->name('admin.reports.commission');

        Route::get('/reports/commission/export', [
            AdminReportingController::class,
            'exportCommissionReport',
        ])->name('admin.reports.commission.export');


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

        Route::post('/settings/hero-banner', [
            AdminSettingsController::class,
            'updateHeroBanner',
        ])->name('admin.settings.hero-banner.update');

        Route::post('/settings/why-alona', [
            AdminSettingsController::class,
            'updateWhyAlona',
        ])->name('admin.settings.why-alona.update');

        Route::post('/settings/footer', [
            AdminSettingsController::class,
            'updateFooter',
        ])->name('admin.settings.footer.update');

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


        /*
        |--------------------------------------------------------------------------
        | PAYOUT REQUESTS & STATUS ALERTS (SELLER-61)
        |--------------------------------------------------------------------------
        */

        Route::get('/payout-requests', [
            AdminPayoutStatusController::class,
            'index',
        ])->name('admin.payout-requests');

        Route::patch('/payout-requests/{payout}/status', [
            AdminPayoutStatusController::class,
            'updateStatus',
        ])->name('admin.payout-requests.status');


        /*
        |--------------------------------------------------------------------------
        | CATEGORIES & TAXONOMY
        |--------------------------------------------------------------------------
        */

        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('admin.categories');
        Route::post('/categories', [AdminCategoryController::class, 'store'])->name('admin.categories.store');
        Route::patch('/categories/{category}', [AdminCategoryController::class, 'update'])->name('admin.categories.update');
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('admin.categories.destroy');

        /*
        |--------------------------------------------------------------------------
        | PLATFORM VOUCHERS & CAMPAIGNS
        |--------------------------------------------------------------------------
        */

        Route::get('/vouchers', [AdminVoucherController::class, 'index'])->name('admin.vouchers');
        Route::post('/vouchers', [AdminVoucherController::class, 'store'])->name('admin.vouchers.store');
        Route::patch('/vouchers/{voucher}', [AdminVoucherController::class, 'update'])->name('admin.vouchers.update');
        Route::delete('/vouchers/{voucher}', [AdminVoucherController::class, 'destroy'])->name('admin.vouchers.destroy');

        /*
        |--------------------------------------------------------------------------
        | ADMIN AUDIT LOGS
        |--------------------------------------------------------------------------
        */

        Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('admin.audit-logs');

        /*
        |--------------------------------------------------------------------------
        | SHIPPING RATES & HUB ZONES
        |--------------------------------------------------------------------------
        */

        Route::get('/shipping-rates', [AdminDeliveryZoneController::class, 'index'])->name('admin.shipping-rates');
        Route::post('/shipping-rates', [AdminDeliveryZoneController::class, 'store'])->name('admin.shipping-rates.store');
        Route::patch('/shipping-rates/{deliveryZone}', [AdminDeliveryZoneController::class, 'update'])->name('admin.shipping-rates.update');
        Route::delete('/shipping-rates/{deliveryZone}', [AdminDeliveryZoneController::class, 'destroy'])->name('admin.shipping-rates.destroy');
    });
