<?php

namespace App\Providers;

use App\Broadcasting\AlonaReverbBroadcaster;
use App\Models\AdminNotification;
use App\Models\BuyerNotification;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\Complaint;
use App\Models\ConversationMessage;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\Parcel;
use App\Models\ParcelReturn;
use App\Models\PickupRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RefundLedger;
use App\Models\Rider;
use App\Models\RiderDeliveryEarning;
use App\Models\RiderNotification;
use App\Models\SellerNotification;
use App\Models\SellerPromotion;
use App\Models\SortingCenterMessage;
use App\Models\User;
use App\Observers\RealtimeModelObserver;
use App\Observers\RiderNotificationObserver;
use App\Services\LogisticsSessionLifetime;
use App\Services\RealtimeEventStore;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RealtimeEventStore::class, fn () => new RealtimeEventStore());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::authenticateAccessTokensUsing(
            fn ($token, bool $valid): bool => app(LogisticsSessionLifetime::class)->validToken($token, $valid)
        );

        Broadcast::extend('reverb', function ($app, array $config) {
            return new AlonaReverbBroadcaster($config, $app->make(RealtimeEventStore::class));
        });

        if (file_exists(base_path('routes/channels.php'))) {
            require base_path('routes/channels.php');
        }

        foreach ([
            Parcel::class,
            PickupRequest::class,
            Rider::class,
            User::class,
            SortingCenterMessage::class,
            RiderDeliveryEarning::class,
        ] as $model) {
            $model::observe(RiderNotificationObserver::class);
        }

        foreach ([
            Product::class,
            ProductVariant::class,
            FlashSale::class,
            SellerPromotion::class,
            Order::class,
            OrderItem::class,
            Parcel::class,
            PickupRequest::class,
            OrderReturnRequest::class,
            RefundLedger::class,
            CodCollection::class,
            CodRemittance::class,
            RiderDeliveryEarning::class,
            ParcelReturn::class,
            ConversationMessage::class,
            SortingCenterMessage::class,
            BuyerNotification::class,
            SellerNotification::class,
            AdminNotification::class,
            RiderNotification::class,
            User::class,
            Complaint::class,
        ] as $realtimeModel) {
            if (class_exists($realtimeModel)) {
                $realtimeModel::observe(RealtimeModelObserver::class);
            }
        }

        Queue::failing(function (JobFailed $event) {
            Log::error('Alona Queue Job Failed', [
                'connection' => $event->connectionName,
                'job' => $event->job->resolveName(),
                'exception' => $event->exception->getMessage(),
            ]);
        });

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
