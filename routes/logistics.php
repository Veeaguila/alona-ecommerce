<?php

use Illuminate\Support\Facades\Route;

Route::view('/sorting-center/login', 'sorting-center-login')->name('sorting-center.login');
Route::view('/sorting-center/register', 'sorting-center-registration')->name('sorting-center.register');

// These views contain only the UI shell; every data endpoint requires an approved account.
Route::prefix('sorting-center')->group(function () {
    foreach (['dashboard', 'order-management', 'delivery-management', 'rider-assignment', 'delivery-monitoring', 'delivery-reports', 'messages', 'pickup-requests'] as $page) {
        Route::view($page, 'sorting-center.'.$page)->name('sorting-center.'.$page);
    }
});
