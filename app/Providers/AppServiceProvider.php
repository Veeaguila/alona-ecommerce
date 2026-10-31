<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Laravel\Sanctum\Sanctum::authenticateAccessTokensUsing(
            fn ($token, bool $valid): bool => app(\App\Services\LogisticsSessionLifetime::class)->validToken($token, $valid)
        );

        foreach ([\App\Models\Parcel::class, \App\Models\PickupRequest::class, \App\Models\Rider::class,
            \App\Models\User::class, \App\Models\SortingCenterMessage::class, \App\Models\RiderDeliveryEarning::class] as $model) {
            $model::observe(\App\Observers\RiderNotificationObserver::class);
        }
        Vite::prefetch(concurrency: 3);

        Password::defaults(function () {
            return Password::min(8)
                ->max(64)
                ->mixedCase()
                ->numbers()
                ->symbols();
        });
    }
}
