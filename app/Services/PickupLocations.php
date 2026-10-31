<?php

namespace App\Services;

use App\Models\{Parcel, Rider};
use Illuminate\Support\Facades\Cache;

class PickupLocations
{
    public const RIDER_SECONDS = 90;
    public const SELLER_SECONDS = 900;

    public function sellerKey(Parcel $parcel): string
    {
        return 'alona:pickup-point:v1:'.hash('sha256', $parcel->seller_id.'|'.trim((string) $parcel->collection_address));
    }
    public function saveSeller(Parcel $parcel, array $point): void
    {
        Cache::put($this->sellerKey($parcel), $point, now()->addSeconds(self::SELLER_SECONDS));
    }
    public function seller(Parcel $parcel): ?array
    {
        $point = Cache::get($this->sellerKey($parcel));
        return is_array($point) && $point['captured_at'] > now()->timestamp - self::SELLER_SECONDS ? $point : null;
    }
    public function saveRider(Rider $rider, array $point, string $session): void
    {
        Cache::put('alona:rider-point:v1:'.$rider->id, $point + ['session' => $session], now()->addSeconds(self::RIDER_SECONDS));
    }
    public function rider(Rider $rider): ?array
    {
        $point = Cache::get('alona:rider-point:v1:'.$rider->id);
        return is_array($point) && $point['captured_at'] > now()->timestamp - self::RIDER_SECONDS
            && app(RiderPresence::class)->currentSession($rider, $point['session'])
            && app(RiderPresence::class)->snapshot($rider)['presence'] === 'Online' ? $point : null;
    }
    public function clearRider(Rider $rider): void { Cache::forget('alona:rider-point:v1:'.$rider->id); }
    public function distance(array $a, array $b): float
    {
        $lat = deg2rad($b['latitude'] - $a['latitude']); $lon = deg2rad($b['longitude'] - $a['longitude']);
        $h = sin($lat / 2) ** 2 + cos(deg2rad($a['latitude'])) * cos(deg2rad($b['latitude'])) * sin($lon / 2) ** 2;
        return 6371000 * 2 * asin(min(1, sqrt($h)));
    }
}
