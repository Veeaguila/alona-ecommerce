<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Test-only retrieval for synthetic deliveries. No equivalent production API exposes this data to Riders.
    protected function deliveryOtp(int $parcelId): string
    {
        if (!$this->app->environment('testing') || \Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \LogicException('Synthetic OTP retrieval requires the isolated test database.');
        }
        $parcel = \App\Models\Parcel::findOrFail($parcelId);
        app(\App\Services\DeliveryOtp::class)->ensure($parcel);
        $record = \Illuminate\Support\Facades\Cache::get(\App\Services\DeliveryOtp::CACHE_PREFIX.$parcelId);
        return \Illuminate\Support\Facades\Crypt::decryptString($record['ciphertext']);
    }
}
