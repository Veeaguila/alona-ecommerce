<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            /*
            |--------------------------------------------------------------------------
            | AUTHENTICATED USER
            |--------------------------------------------------------------------------
            */
            'auth' => [
                'user' => $user,
            ],

            /*
            |--------------------------------------------------------------------------
            | BUYER GLOBAL COUNTS
            |--------------------------------------------------------------------------
            */
            'buyerCounts' => [
                'cart' => $user?->cartItems()
                    ->sum('quantity') ?? 0,

                'notifications' => $user?->buyerNotifications()
                    ->whereNull('read_at')
                    ->count() ?? 0,
            ],

            /*
            |--------------------------------------------------------------------------
            | BUYER NOTIFICATIONS
            |--------------------------------------------------------------------------
            |
            | Share the latest notifications so BuyerLayout can display them
            | directly inside the notification dropdown.
            |
            */
            'buyerNotifications' => $user?->buyerNotifications()
                ->latest()
                ->take(5)
                ->get([
                    'id',
                    'user_id',
                    'title',
                    'message',
                    'read_at',
                    'created_at',
                ]) ?? collect(),

            /*
            |--------------------------------------------------------------------------
            | SELLER GLOBAL COUNTS
            |--------------------------------------------------------------------------
            */
            'sellerCounts' => [
                'notifications' =>
                    $user?->usertype === 'seller'
                        ? $user->sellerNotifications()
                            ->whereNull('read_at')
                            ->count()
                        : 0,

                'messages' =>
                    $user?->usertype === 'seller'
                        ? $user->conversationsAsSeller()
                            ->whereHas(
                                'messages',
                                fn ($q) => $q
                                    ->whereNull('read_at')
                                    ->where(
                                        'sender_id',
                                        '!=',
                                        $user->id
                                    )
                            )
                            ->count()
                        : 0,
            ],
        ];
    }
}