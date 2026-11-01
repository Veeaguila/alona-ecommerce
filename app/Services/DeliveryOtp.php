<?php

namespace App\Services;

use App\Models\Parcel;
use Illuminate\Support\Facades\{Cache, Crypt, Hash};
use Illuminate\Validation\ValidationException;

class DeliveryOtp
{
    public const LIFETIME_SECONDS = 600;
    public const MAX_ATTEMPTS = 5;
    public const LOCKOUT_SECONDS = 900;
    public const CACHE_PREFIX = 'alona:delivery-otp:v1:';

    public function ensure(Parcel $parcel): void
    {
        abort_unless($parcel->status === 'OUT_FOR_DELIVERY', 422, 'The delivery has not started.');
        Cache::lock($this->key($parcel).':lock', 10)->block(2, function () use ($parcel): void {
            if (!is_array(Cache::get($this->key($parcel)))) $this->create($parcel);
        });
    }

    public function renew(Parcel $parcel): void
    {
        abort_unless($parcel->status === 'OUT_FOR_DELIVERY', 422, 'The delivery is no longer active.');
        Cache::lock($this->key($parcel).':lock', 10)->block(2, fn () => $this->create($parcel));
    }

    // Only the authenticated owning Buyer's dedicated no-store endpoint may call this.
    public function forBuyer(Parcel $parcel): array
    {
        $this->ensure($parcel);
        $record = Cache::get($this->key($parcel));
        abort_unless(is_array($record), 503, 'Delivery OTP is temporarily unavailable. Please retry.');
        $this->assertUnlocked($record);
        abort_if($record['expires_at'] <= now()->timestamp || $record['attempts'] >= self::MAX_ATTEMPTS, 410, 'Delivery OTP expired. Request a new code.');
        abort_if($record['consumed'], 409, 'This delivery OTP has already been used.');
        return ['otp' => Crypt::decryptString($record['ciphertext']),
            'expires_at' => \Carbon\CarbonImmutable::createFromTimestampUTC($record['expires_at'])->toISOString()];
    }

    public function verify(Parcel $parcel, string $code): void
    {
        Cache::lock($this->key($parcel).':lock', 10)->block(2, function () use ($parcel, $code): void {
            $record = Cache::get($this->key($parcel));
            abort_unless(is_array($record), 410, 'Ask the Buyer to open their order and obtain a delivery OTP.');
            abort_if($record['consumed'], 409, 'This delivery OTP has already been used.');
            $this->assertUnlocked($record);
            abort_if($record['expires_at'] <= now()->timestamp, 410, 'Delivery OTP expired. Ask the Buyer to request a new code.');
            abort_if($record['attempts'] >= self::MAX_ATTEMPTS, 410, 'Ask the Buyer to request a fresh code after the lockout.');
            if (!Hash::check($code, $record['hash'])) {
                $record['attempts']++;
                if ($record['attempts'] >= self::MAX_ATTEMPTS) $record['locked_until'] = now()->timestamp + self::LOCKOUT_SECONDS;
                Cache::put($this->key($parcel), $record, now()->addDay());
                throw ValidationException::withMessages(['otp' => 'The Buyer delivery OTP is incorrect.']);
            }
        });
    }

    public function consume(Parcel $parcel): void
    {
        Cache::lock($this->key($parcel).':lock', 10)->block(2, function () use ($parcel): void {
            $record = Cache::get($this->key($parcel));
            if (is_array($record)) {
                unset($record['ciphertext']);
                $record['consumed'] = true;
                Cache::put($this->key($parcel), $record, now()->addDay());
            }
        });
    }

    private function create(Parcel $parcel): void
    {
        $previous = Cache::get($this->key($parcel));
        if (is_array($previous)) {
            $this->assertUnlocked($previous);
            abort_if($previous['consumed'], 409, 'This delivery OTP has already been used.');
        }
        $attempts = is_array($previous) && $previous['attempts'] < self::MAX_ATTEMPTS ? $previous['attempts'] : 0;
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->key($parcel), ['hash' => Hash::make($code), 'ciphertext' => Crypt::encryptString($code),
            'expires_at' => now()->timestamp + self::LIFETIME_SECONDS, 'attempts' => $attempts, 'locked_until' => null, 'consumed' => false], now()->addDay());
    }

    private function assertUnlocked(array $record): void
    {
        $remaining = (int) ($record['locked_until'] ?? 0) - now()->timestamp;
        abort_if($remaining > 0, 429, 'Delivery OTP is locked for 15 minutes after five incorrect entries. Please wait before requesting or entering a new code.', ['Retry-After' => (string) max(1, $remaining)]);
    }

    private function key(Parcel $parcel): string
    {
        return self::CACHE_PREFIX.$parcel->id;
    }
}
