<?php

namespace App\Services;

use App\Models\Rider;
use Illuminate\Support\Facades\Cache;

class RiderPresence
{
    public const HEARTBEAT_SECONDS = 30;
    public const ONLINE_SECONDS = 90;

    public function snapshot(Rider $rider, bool $eligible = true): array
    {
        $signal = Cache::get($this->key($rider->id));
        $seen = is_array($signal) ? ($signal['seen_at'] ?? null) : null;
        $online = $eligible && $rider->is_active && is_array($signal) && ($signal['online'] ?? false)
            && is_int($seen) && $seen > now()->timestamp - self::ONLINE_SECONDS;
        $state = !$eligible || !$rider->is_active ? 'Offline' : ($online ? 'Online' : ($signal ? 'Offline' : 'Unknown'));
        return ['presence' => $state,
            'presence_detail' => match ($state) {
                'Online' => 'Rider opted in and sent a heartbeat within 90 seconds.',
                'Offline' => !$eligible || !$rider->is_active ? 'Operational access is unavailable.' : 'Rider went offline or the heartbeat expired.',
                default => 'No reliable presence signal is available.',
            },
            'last_heartbeat_at' => is_int($seen) ? \Carbon\CarbonImmutable::createFromTimestampUTC($seen)->toISOString() : null,
            'heartbeat_interval_seconds' => self::HEARTBEAT_SECONDS,
            'online_expires_after_seconds' => self::ONLINE_SECONDS];
    }

    public function command(Rider $rider, string $action, string $session): array
    {
        return Cache::lock($this->key($rider->id).':lock', 10)->block(2, function () use ($rider, $action, $session): array {
            $signal = Cache::get($this->key($rider->id));
            if ($action === 'heartbeat') {
                abort_unless(is_array($signal) && ($signal['online'] ?? false) && ($signal['session'] ?? null) === $session,
                    409, 'Presence session ended. Choose Go Online again if you are available.');
            }
            Cache::put($this->key($rider->id), ['online' => $action !== 'go_offline', 'session' => $session,
                'seen_at' => $action === 'go_offline' ? ($signal['seen_at'] ?? null) : now()->timestamp,
                'changed_at' => now()->timestamp], now()->addDays(7));
            return $this->snapshot($rider);
        });
    }

    public function offline(Rider $rider): void
    {
        $this->command($rider, 'go_offline', 'server');
    }

    public function currentSession(Rider $rider, string $session): bool
    {
        $signal = Cache::get($this->key($rider->id));
        return is_array($signal) && ($signal['online'] ?? false) && ($signal['session'] ?? null) === $session;
    }

    private function key(int $id): string
    {
        return 'alona:rider-presence:v1:'.$id;
    }
}
