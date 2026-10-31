<?php

namespace App\Http\Middleware;

use App\Services\AccountDestination;
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
            'flash' => ['status' => fn () => $request->session()->get('status')],
            'footerSettings' => fn () => \App\Services\FooterService::get(),

            /*
            |--------------------------------------------------------------------------
            | AUTHENTICATED USER
            |--------------------------------------------------------------------------
            */
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'usertype' => $user->usertype,
                    'status' => $user->status,
                    'avatar' => $user->avatar,
                    'contact_no' => $user->contact_no,
                    'store_name' => $user->store_name,
                ] : null,
                'destination' => $user ? route(app(AccountDestination::class)->route($user)) : null,
                'googleOnboarding' => fn () => collect($request->session()->get('onboarding.google', []))->only(['name', 'email', 'role'])->all(),
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
                'notifications' => $user?->usertype === 'seller'
                        ? $user->sellerNotifications()
                            ->whereNull('read_at')
                            ->count()
                        : 0,

                'messages' => $user?->usertype === 'seller'
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
