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
        if ($user) {
            app(\App\Services\RealtimeBroadcastService::class)->touchUserPresence($user);
        }

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
                    'middle_name' => $user->middle_name,
                    'middle_initial' => $user->middle_initial,
                    'suffix' => $user->suffix,
                    'sex' => $user->sex,
                    'email' => $user->email,
                    'pending_email' => $user->pending_email,
                    'contact_no' => $user->contact_no,
                    'pending_phone' => $user->pending_phone,
                    'birthday' => $user->birthday ? (is_string($user->birthday) ? substr($user->birthday, 0, 10) : $user->birthday->format('Y-m-d')) : null,
                    'age' => $user->age,
                    'address' => $user->address,
                    'street_address' => $user->street_address,
                    'barangay' => $user->barangay,
                    'municipality' => $user->municipality,
                    'province' => $user->province,
                    'postal_code' => $user->postal_code,
                    'usertype' => $user->usertype,
                    'status' => $user->status,
                    'avatar' => $user->avatar,
                    'avatar_url' => $user->avatar_url,
                    'email_verified_at' => $user->email_verified_at,
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

            /*
            |--------------------------------------------------------------------------
            | REAL-TIME CONFIGURATION & AUTHORITATIVE SERVER CLOCK
            |--------------------------------------------------------------------------
            */
            'realtimeConfig' => fn () => [
                'driver' => config('broadcasting.default', 'reverb'),
                'key' => config('broadcasting.connections.reverb.key', 'alona-reverb-key'),
                'host' => config('broadcasting.connections.reverb.options.host', '127.0.0.1'),
                'port' => (int) config('broadcasting.connections.reverb.options.port', 8080),
                'scheme' => config('broadcasting.connections.reverb.options.scheme', 'http'),
                'server_time' => now()->toIso8601String(),
                'server_time_ms' => (int) round(microtime(true) * 1000),
            ],
        ];
    }
}
